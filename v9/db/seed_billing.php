<?php
/**
 * GeriApp — Seed: Billing (planes + addons + precios)
 *
 * Inserta los 7 paquetes (Básico/Intermedio/Empresarial) y los add-ons de
 * asientos extra (familiar, residente). Es **idempotente**: usa UNIQUE keys
 * (`planes.key`, `addons.codigo`, `plan_precios(plan_id, moneda, periodo)`)
 * con `INSERT … ON DUPLICATE KEY UPDATE` para que se pueda ejecutar varias
 * veces sin duplicar datos. Los `stripe_price_id` quedan en NULL: deben
 * llenarse desde el portal de Stripe (o vía API) en F3.
 *
 * Requisitos previos: ejecutar **Auditoría BD** del superadmin para crear las
 * tablas `planes` (extendida), `plan_precios`, `addons`, `addon_precios`,
 * `plan_addon_precios`.
 *
 * Uso:
 *   - CLI:    php db/seed_billing.php
 *   - Web:    /superadmin/seed_billing.php  (requiere sesión sa_authenticated)
 *
 * Política multi-divisa: por defecto se siembran precios en **MXN** (los del
 * gráfico). Para USD/COP/CAD se dejan filas vacías que el superadmin debe
 * editar con tasas reales (no convertimos automáticamente para evitar
 * inconsistencias contables). Periodo `anual` = `mensual * 10` (2 meses
 * gratis, según política definida).
 *
 * Seguridad: todos los precios son configurables desde el panel del
 * superadmin (no se hardcodean en el flujo de checkout).
 */

declare(strict_types=1);

// Permitir uso vía CLI o vía web (con auth de superadmin).
// Si se incluye desde la API (`GERIAPP_SEED_INLINE`), no enviar headers
// (ya enviados por el endpoint) ni cargar middleware (ya autenticado).
$isCli    = (PHP_SAPI === 'cli');
$isInline = defined('GERIAPP_SEED_INLINE') && GERIAPP_SEED_INLINE;
if (!$isCli && !$isInline) {
    require_once dirname(__DIR__) . '/superadmin/auth_middleware.php';
    header('Content-Type: text/plain; charset=utf-8');
}

require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

/** Helper: imprime con timestamp tanto en CLI como en web. */
$log = function (string $msg) use ($isInline) {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    // En modo inline (incluido desde la API) NO vaciamos el buffer —
    // el endpoint lo captura con ob_get_clean() y lo envía como JSON.
    if (!$isInline) { @ob_flush(); @flush(); }
};

// ─────────────────────────────────────────────────────────────────────────────
// 1) Catálogo de PLANES (los 7 paquetes)
// ─────────────────────────────────────────────────────────────────────────────
// Estructura: cada plan define límites de "asientos" compartidos entre las
// instituciones del admin pagador. Admin/Cuidador/Médico siempre ilimitados.
// Familiares incluidos en el paquete son "gratis"; los excedentes pagan
// individualmente vía addon `familiar_extra`.
$planes = [
    // tier      | key             | nombre           | inst | res | fam | precio_mxn | cot | orden | trial_dias | tarjeta_reg
    ['basico',     'basico_1',       'Paquete 1',       1,   1,    3,   199.00,  0, 1, 30, 0],
    ['basico',     'basico_2',       'Paquete 2',       1,   2,    6,   299.00,  0, 2, 30, 0],
    ['intermedio', 'intermedio_3',   'Paquete 3',       2,   2,    6,   399.00,  0, 3, 30, 0],
    ['intermedio', 'intermedio_4',   'Paquete 4',       2,   4,    8,   599.00,  0, 4, 30, 0],
    ['intermedio', 'intermedio_5',   'Paquete 5',       3,   5,   16,   699.00,  0, 5, 30, 0],
    ['empresarial','empresarial_6',  'Paquete 6',       4,  10,   32,   799.00,  0, 6, 30, 0],
    // Paquete 7: requiere cotización. Precio placeholder (no se cobra; sirve
    // para mostrar referencia mientras el equipo comercial responde).
    ['empresarial','empresarial_7',  'Paquete 7',       null, null, null, 999.00, 1, 7, 0, 0],
];

$insertPlanSql = "INSERT INTO planes
    (`key`, nombre, tier, precio, descripcion,
     max_residentes, max_usuarios, max_instituciones, max_familiares,
     admins_ilimitados, cuidadores_ilimitados, medicos_ilimitados,
         requiere_cotizacion, solicita_tarjeta_registro, trial_dias, orden, activo)
    VALUES (:key, :nombre, :tier, :precio, :descripcion,
            :max_res, :max_usr, :max_inst, :max_fam,
             1, 1, 1, :req_cot, :tarjeta_reg, :trial_dias, :orden, 1)
    ON DUPLICATE KEY UPDATE
        nombre = VALUES(nombre),
        tier   = VALUES(tier),
        precio = VALUES(precio),
        descripcion = VALUES(descripcion),
        max_residentes    = VALUES(max_residentes),
        max_usuarios      = VALUES(max_usuarios),
        max_instituciones = VALUES(max_instituciones),
        max_familiares    = VALUES(max_familiares),
        requiere_cotizacion = VALUES(requiere_cotizacion),
        solicita_tarjeta_registro = VALUES(solicita_tarjeta_registro),
        trial_dias = VALUES(trial_dias),
        orden  = VALUES(orden),
        activo = 1";

$stPlan = $db->prepare($insertPlanSql);

$insertPrecioSql = "INSERT INTO plan_precios
    (plan_id, moneda, periodo, precio, stripe_price_id, activo)
    VALUES (:plan_id, :moneda, :periodo, :precio, NULL, :activo)
    ON DUPLICATE KEY UPDATE
        precio = VALUES(precio),
        activo = VALUES(activo)";
$stPrecio = $db->prepare($insertPrecioSql);

// Monedas soportadas: MXN sembrado con valores reales; las demás como
// placeholders inactivos para que superadmin las habilite tras configurar
// tasas y crear los Stripe Prices.
$monedasPlaceholder = ['USD', 'COP', 'CAD'];

$cnt = ['planes' => 0, 'precios' => 0, 'plan_addon_precios' => 0];
$sellablePlanIds = [];

foreach ($planes as [$tier, $key, $nombre, $maxInst, $maxRes, $maxFam, $precio, $reqCot, $orden, $trialDias, $tarjetaReg]) {
    $desc = $reqCot
        ? 'Plan corporativo personalizado. Requiere cotización con el equipo comercial.'
        : sprintf(
            '%s instituci%s, %s residente%s, %s familiar%s incluid%s.',
            $maxInst, $maxInst == 1 ? 'ón' : 'ones',
            $maxRes,  $maxRes  == 1 ? '' : 's',
            $maxFam,  $maxFam  == 1 ? '' : 'es',
            $maxFam  == 1 ? 'o' : 'os'
        );

    $stPlan->execute([
        ':key'         => $key,
        ':nombre'      => $nombre,
        ':tier'        => $tier,
        ':precio'      => $precio,
        ':descripcion' => $desc,
        ':max_res'     => $maxRes,
        ':max_usr'     => null,         // legacy: se reemplaza por max_familiares + flags ilimitados
        ':max_inst'    => $maxInst,
        ':max_fam'     => $maxFam,
        ':req_cot'     => $reqCot,
        ':tarjeta_reg' => $tarjetaReg,
        ':trial_dias'  => $trialDias,
        ':orden'       => $orden,
    ]);
    $cnt['planes']++;

    // Resolver el plan_id (auto_increment o existente).
    $planId = (int)$db->query("SELECT id FROM planes WHERE `key` = " . $db->quote($key))->fetchColumn();
    if ($planId <= 0) {
        $log("[WARN] No se pudo resolver plan_id para {$key}, se omiten precios");
        continue;
    }

    // Para el plan que requiere cotización no sembramos plan_precios (no se vende
    // por checkout estándar; se factura manualmente).
    if ($reqCot) {
        $log("✓ Plan {$key} ({$nombre}) — placeholder, sin precios (cotización manual)");
        continue;
    }
    $sellablePlanIds[] = $planId;

    // Precios MXN (mensual real + anual con descuento de 2 meses).
    $stPrecio->execute([
        ':plan_id' => $planId,
        ':moneda'  => 'MXN',
        ':periodo' => 'mensual',
        ':precio'  => $precio,
        ':activo'  => 1,
    ]);
    $stPrecio->execute([
        ':plan_id' => $planId,
        ':moneda'  => 'MXN',
        ':periodo' => 'anual',
        ':precio'  => round($precio * 10, 2),     // 2 meses gratis
        ':activo'  => 1,
    ]);
    $cnt['precios'] += 2;

    // Placeholders para otras monedas: precio 0, inactivos. Superadmin debe
    // editarlos con tasas reales y crear los Price en Stripe antes de activar.
    foreach ($monedasPlaceholder as $mon) {
        $stPrecio->execute([
            ':plan_id' => $planId,
            ':moneda'  => $mon,
            ':periodo' => 'mensual',
            ':precio'  => 0.00,
            ':activo'  => 0,
        ]);
        $stPrecio->execute([
            ':plan_id' => $planId,
            ':moneda'  => $mon,
            ':periodo' => 'anual',
            ':precio'  => 0.00,
            ':activo'  => 0,
        ]);
        $cnt['precios'] += 2;
    }

    $log("✓ Plan {$key} ({$nombre}) — MXN \${$precio}/mes, \$" . round($precio * 10, 2) . "/año");
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) ADD-ONS: asientos extra (familiar, residente)
// ─────────────────────────────────────────────────────────────────────────────
$addons = [
    [
        'codigo' => 'familiar_extra',
        'nombre' => 'Asiento de familiar extra',
        'tipo'   => 'asiento_familiar',
        'desc'   => 'Permite agregar un familiar adicional más allá del cupo del paquete. Lo paga el propio familiar al registrarse con la invitación.',
        'precio_mxn' => 100.00,
    ],
    [
        'codigo' => 'residente_extra',
        'nombre' => 'Asiento de residente extra',
        'tipo'   => 'asiento_residente',
        'desc'   => 'Permite agregar un residente adicional más allá del cupo del paquete. Se cobra al administrador.',
        'precio_mxn' => 100.00,
    ],
];

$stAddon = $db->prepare(
    "INSERT INTO addons (codigo, nombre, tipo, descripcion, activo)
     VALUES (:codigo, :nombre, :tipo, :desc, 1)
     ON DUPLICATE KEY UPDATE
        nombre = VALUES(nombre),
        tipo   = VALUES(tipo),
        descripcion = VALUES(descripcion),
        activo = 1"
);

$stAddonPrecio = $db->prepare(
    "INSERT INTO addon_precios (addon_id, moneda, periodo, precio, stripe_price_id, activo)
     VALUES (:addon_id, :moneda, :periodo, :precio, NULL, :activo)
     ON DUPLICATE KEY UPDATE
        precio = VALUES(precio),
        activo = VALUES(activo)"
);

$stPlanAddonPrecio = $db->prepare(
    "INSERT INTO plan_addon_precios (plan_id, addon_id, moneda, periodo, precio, stripe_price_id, activo)
     VALUES (:plan_id, :addon_id, :moneda, :periodo, :precio, NULL, :activo)
     ON DUPLICATE KEY UPDATE
        precio = VALUES(precio),
        activo = VALUES(activo)"
);

foreach ($addons as $a) {
    $stAddon->execute([
        ':codigo' => $a['codigo'],
        ':nombre' => $a['nombre'],
        ':tipo'   => $a['tipo'],
        ':desc'   => $a['desc'],
    ]);
    $cnt['planes']++; // reuse counter slot

    $addonId = (int)$db->query("SELECT id FROM addons WHERE codigo = " . $db->quote($a['codigo']))->fetchColumn();
    if ($addonId <= 0) {
        $log("[WARN] No se pudo resolver addon_id para {$a['codigo']}");
        continue;
    }

    // MXN: precio real (mensual y anual con 2 meses gratis).
    $stAddonPrecio->execute([
        ':addon_id' => $addonId,
        ':moneda'   => 'MXN',
        ':periodo'  => 'mensual',
        ':precio'   => $a['precio_mxn'],
        ':activo'   => 1,
    ]);
    $stAddonPrecio->execute([
        ':addon_id' => $addonId,
        ':moneda'   => 'MXN',
        ':periodo'  => 'anual',
        ':precio'   => round($a['precio_mxn'] * 10, 2),
        ':activo'   => 1,
    ]);
    $cnt['precios'] += 2;

    foreach ($monedasPlaceholder as $mon) {
        $stAddonPrecio->execute([
            ':addon_id' => $addonId,
            ':moneda'   => $mon,
            ':periodo'  => 'mensual',
            ':precio'   => 0.00,
            ':activo'   => 0,
        ]);
        $stAddonPrecio->execute([
            ':addon_id' => $addonId,
            ':moneda'   => $mon,
            ':periodo'  => 'anual',
            ':precio'   => 0.00,
            ':activo'   => 0,
        ]);
        $cnt['precios'] += 2;
    }

    foreach ($sellablePlanIds as $planId) {
        $stPlanAddonPrecio->execute([
            ':plan_id'  => $planId,
            ':addon_id' => $addonId,
            ':moneda'   => 'MXN',
            ':periodo'  => 'mensual',
            ':precio'   => $a['precio_mxn'],
            ':activo'   => 1,
        ]);
        $stPlanAddonPrecio->execute([
            ':plan_id'  => $planId,
            ':addon_id' => $addonId,
            ':moneda'   => 'MXN',
            ':periodo'  => 'anual',
            ':precio'   => round($a['precio_mxn'] * 10, 2),
            ':activo'   => 1,
        ]);
        $cnt['plan_addon_precios'] += 2;
        foreach ($monedasPlaceholder as $mon) {
            $stPlanAddonPrecio->execute([
                ':plan_id'  => $planId,
                ':addon_id' => $addonId,
                ':moneda'   => $mon,
                ':periodo'  => 'mensual',
                ':precio'   => 0.00,
                ':activo'   => 0,
            ]);
            $stPlanAddonPrecio->execute([
                ':plan_id'  => $planId,
                ':addon_id' => $addonId,
                ':moneda'   => $mon,
                ':periodo'  => 'anual',
                ':precio'   => 0.00,
                ':activo'   => 0,
            ]);
            $cnt['plan_addon_precios'] += 2;
        }
    }

    $log("✓ Addon {$a['codigo']} — MXN \${$a['precio_mxn']}");
}

$log("─────────────────────────────────────────");
$log("Seed completado.");
$log("  Planes/Addons procesados: {$cnt['planes']}");
$log("  Filas plan_precios/addon_precios: {$cnt['precios']}");
$log("  Filas plan_addon_precios: {$cnt['plan_addon_precios']}");
$log("");
$log("Próximos pasos (F3):");
$log("  1. Crear Products + Prices en Stripe (test mode).");
$log("  2. Llenar `stripe_product_id` en planes/addons.");
$log("  3. Llenar `stripe_price_id` en plan_precios/addon_precios/plan_addon_precios.");
$log("  4. Activar precios USD/COP/CAD desde el portal superadmin.");
