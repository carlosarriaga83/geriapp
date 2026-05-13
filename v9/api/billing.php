<?php
/**
 * GeriApp — API: Billing (suscripciones, planes, checkout, portal, asientos)
 *
 * Endpoints (todos requieren sesión activa, CSRF en mutaciones):
 *
 *   GET  ?action=status
 *        Devuelve: suscripcion actual del usuario (admin) o suscripciones_familiar,
 *        plan vigente, precios, addons disponibles, conteo de uso de asientos,
 *        flag is_native (Capacitor) para esconder botones, instituciones cubiertas.
 *
 *   GET  ?action=plans
 *        Catálogo de planes activos + sus precios por moneda/periodo.
 *
 *   POST ?action=checkout
 *        Crea Stripe Checkout Session para:
 *          - kind=admin: nuevo plan o cambio de plan (rol admin)
 *          - kind=familiar: addon de familiar (rol familiar)
 *          - kind=seat: addon residente_extra/familiar_extra (rol admin)
 *        Body: { plan_id|addon_id, moneda, periodo, kind, success_url, cancel_url, quantity? }
 *        Devuelve: { url }
 *
 *   POST ?action=portal
 *        Crea Stripe Billing Portal Session para que el usuario gestione su
 *        método de pago, vea facturas y cancele.
 *        Body: { return_url }
 *        Devuelve: { url }
 *
 *   POST ?action=cotizacion
 *        Crea fila en `cotizaciones` (lead form para plan Empresarial 7).
 *        Body: { plan_id, nombre_contacto, email, telefono?, institucion_nombre?, num_camas?, mensaje? }
 *
 * Política Apple §3.1.1 / Google Play:
 *   - El cliente debe ocultar botones de pago en Capacitor nativo (is_native=true).
 *   - Aun así, los endpoints retornan 403 si detectan que la solicitud
 *     proviene de Capacitor (cabecera X-Capacitor-Native: 1) en kind admin/familiar.
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/db/models/StripeClient.php';
require_once dirname(__DIR__) . '/db/models/Seats.php';

api_auth();

$action = $_GET['action'] ?? '';
$db     = Database::getMaster();
$userId = (int)$_SESSION['user_id'];
$rol    = $_SESSION['user_rol'] ?? '';
$isNative = !empty($_SERVER['HTTP_X_CAPACITOR_NATIVE']);

function billing_public_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

function billing_public_payment_rows(array $rows): array
{
    foreach ($rows as &$row) {
        $payload = json_decode((string)($row['raw_payload'] ?? ''), true);
        $obj = is_array($payload) ? ($payload['data']['object'] ?? []) : [];
        if (!is_array($obj)) $obj = [];
        $row['recibo_url'] = billing_public_url($obj['hosted_invoice_url'] ?? null);
        $row['recibo_pdf_url'] = billing_public_url($obj['invoice_pdf'] ?? null);
        unset($row['raw_payload']);
    }
    unset($row);
    return $rows;
}

function billing_table_columns(PDO $db, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!empty($row['Field'])) $cols[$row['Field']] = true;
        }
        return $cache[$table] = $cols;
    } catch (\Throwable $e) {
        return $cache[$table] = [];
    }
}

function billing_select_col(array $columns, string $alias, string $column, string $fallback = 'NULL', ?string $as = null): string
{
    $out = $as ?: $column;
    if (isset($columns[$column])) return "{$alias}.`{$column}` AS `{$out}`";
    return "{$fallback} AS `{$out}`";
}

function billing_plan_select(array $columns, string $alias = 'p'): string
{
    $defs = [
        ['key', 'NULL', 'plan_key'],
        ['nombre', 'NULL', 'plan_nombre'],
        ['tier', "'basico'"],
        ['descripcion', 'NULL', 'plan_descripcion'],
        ['max_residentes', 'NULL'],
        ['max_familiares', 'NULL'],
        ['max_instituciones', 'NULL'],
        ['max_usuarios', 'NULL'],
        ['max_admin', 'NULL'],
        ['max_cuidador', 'NULL'],
        ['max_medico', 'NULL'],
        ['requiere_cotizacion', '0'],
        ['solicita_tarjeta_registro', '0'],
        ['trial_dias', '0'],
        ['stripe_product_id', 'NULL'],
    ];
    return implode(",\n                    ", array_map(fn($d) => billing_select_col($columns, $alias, $d[0], $d[1], $d[2] ?? null), $defs));
}

// ─────────────────────────────────────────────────────────────────────────────
// GET status: suscripción actual + plan + uso
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'status') {
    api_require_method('GET');
    $planColumns = billing_table_columns($db, 'planes');
    $planSelect = billing_plan_select($planColumns, 'p');

    $resp = [
        'rol'       => $rol,
        'is_native' => $isNative,
        'currency_default' => 'MXN',
        'publishable_key'  => StripeClient::publishableKey(),
        'subscription' => null,
        'plan'         => null,
        'instituciones'=> [],
        'institution_trial' => null,
        'usage'        => null,
    ];

    if (in_array($rol, ['admin', 'superadmin'], true)) {
        $stmt = $db->prepare(
            "SELECT s.*, {$planSelect}
             FROM suscripciones s
             LEFT JOIN planes p ON p.id = s.plan_id
             WHERE s.owner_user_id = ?
             ORDER BY s.id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($sub) {
            $resp['subscription'] = [
                'id'                  => (int)$sub['id'],
                'plan_id'             => (int)$sub['plan_id'],
                'plan_key'            => $sub['plan_key'],
                'plan_nombre'         => $sub['plan_nombre'],
                'plan_tier'           => $sub['tier'],
                'plan_descripcion'    => $sub['plan_descripcion'],
                'moneda'              => $sub['moneda'],
                'periodo'             => $sub['periodo'],
                'estado'              => $sub['estado'],
                'trial_ends_at'       => $sub['trial_ends_at'],
                'trial_dias'          => (int)($sub['trial_dias'] ?? 0),
                'solicita_tarjeta_registro' => (int)($sub['solicita_tarjeta_registro'] ?? 0),
                'periodo_inicio'      => $sub['periodo_inicio'],
                'periodo_fin'         => $sub['periodo_fin'],
                'cancel_at_period_end'=> (int)$sub['cancel_at_period_end'],
                'seats_residente_extra'=> (int)$sub['seats_residente_extra'],
                'seats_familiar_extra' => (int)$sub['seats_familiar_extra'],
                'has_stripe'          => !empty($sub['stripe_subscription_id']),
                'has_customer'        => !empty($sub['stripe_customer_id']),
                'plan_limits' => [
                    'max_residentes'    => $sub['max_residentes']    !== null ? (int)$sub['max_residentes']    : null,
                    'max_familiares'    => $sub['max_familiares']    !== null ? (int)$sub['max_familiares']    : null,
                    'max_instituciones' => $sub['max_instituciones'] !== null ? (int)$sub['max_instituciones'] : null,
                    'max_usuarios'      => $sub['max_usuarios']      !== null ? (int)$sub['max_usuarios']      : null,
                    'max_admin'         => $sub['max_admin']         !== null ? (int)$sub['max_admin']         : null,
                    'max_cuidador'      => $sub['max_cuidador']      !== null ? (int)$sub['max_cuidador']      : null,
                    'max_medico'        => $sub['max_medico']        !== null ? (int)$sub['max_medico']        : null,
                ],
                'currency_default' => $sub['moneda'],
            ];
            $resp['currency_default'] = $sub['moneda'];

            try {
                $pay = $db->prepare(
                    "SELECT tipo, stripe_invoice_id, monto, moneda, status, recibido_at
                     FROM pagos_historial
                     WHERE suscripcion_id = ?
                       AND tipo IN ('invoice.paid','invoice.payment_succeeded')
                       AND monto IS NOT NULL
                     ORDER BY recibido_at DESC, id DESC LIMIT 1"
                );
                $pay->execute([(int)$sub['id']]);
                $resp['subscription']['ultimo_cobro'] = $pay->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $e) {
                $resp['subscription']['ultimo_cobro'] = null;
            }

            // Instituciones cubiertas
            $instStmt = $db->prepare(
                "SELECT i.id, i.nombre, i.estado, i.logo_path
                 FROM suscripcion_instituciones si
                 JOIN instituciones i ON i.id = si.institucion_id
                 WHERE si.suscripcion_id = ?
                 ORDER BY i.nombre"
            );
            $instStmt->execute([(int)$sub['id']]);
            $resp['instituciones'] = $instStmt->fetchAll(PDO::FETCH_ASSOC);

            // Uso agregado en VIVO (no dependemos de seats_uso porque su cache
            // puede no estar poblado en este entorno y porque Seats::countFamiliares
            // usa una columna 'activo' que en algunos esquemas es 'estado').
            $instIds = array_map(fn($r) => (int)$r['id'], $resp['instituciones']);

            // Fallback: si la suscripción aún no está vinculada en suscripcion_instituciones
            // (caso común: trial recién creado), usa la institución activa de la sesión y/o
            // todas las instituciones del owner (admin) para no devolver 0.
            if (empty($instIds)) {
                $sessInst = (int)($_SESSION['institucion_id'] ?? 0);
                if ($sessInst > 0) $instIds[] = $sessInst;
                // Además, recoge cualquier institución donde este usuario tenga rol admin.
                try {
                    $owns = $db->prepare(
                        "SELECT DISTINCT institucion_id FROM usuario_instituciones
                         WHERE usuario_id = ? AND rol IN ('admin','superadmin') AND estado = 'activo'"
                    );
                    $owns->execute([$userId]);
                    foreach ($owns->fetchAll(PDO::FETCH_COLUMN) as $iid) {
                        $iid = (int)$iid;
                        if ($iid && !in_array($iid, $instIds, true)) $instIds[] = $iid;
                    }
                } catch (\Throwable $e) { /* silent */ }

                // Si llenamos instIds por fallback, también poblar la lista de instituciones
                // visible para que el contador de "Instituciones" no muestre 0.
                if (!empty($instIds)) {
                    $place = implode(',', array_fill(0, count($instIds), '?'));
                    $iq = $db->prepare("SELECT id, nombre, estado, logo_path FROM instituciones WHERE id IN ($place) ORDER BY nombre");
                    $iq->execute($instIds);
                    $resp['instituciones'] = $iq->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            $usageRes = 0;
            $usageFam = 0;
            $usageStaff = 0;
            $byRol = ['admin' => 0, 'cuidador' => 0, 'medico' => 0];
            if (!empty($instIds)) {
                $place = implode(',', array_fill(0, count($instIds), '?'));
                // Residentes activos de las instituciones cubiertas.
                $rs = $db->prepare("SELECT COUNT(*) FROM residentes WHERE institucion_id IN ($place) AND estado = 'activo'");
                $rs->execute($instIds);
                $usageRes = (int)$rs->fetchColumn();

                // Familiares: vinculación vía pivot usuario_instituciones (rol/estado activo).
                $fs = $db->prepare(
                    "SELECT COUNT(DISTINCT ui.usuario_id)
                     FROM usuario_instituciones ui
                     JOIN usuarios u ON u.id = ui.usuario_id
                     WHERE ui.institucion_id IN ($place)
                       AND ui.rol = 'familiar'
                       AND ui.estado = 'activo'
                       AND u.estado = 'activo'"
                );
                $fs->execute($instIds);
                $usageFam = (int)$fs->fetchColumn();

                // Fallback legacy: si el pivot no devolvió nada, contar usuarios.institucion_id directo.
                if ($usageFam === 0) {
                    $fs2 = $db->prepare(
                        "SELECT COUNT(*) FROM usuarios
                         WHERE institucion_id IN ($place)
                           AND rol = 'familiar'
                           AND estado = 'activo'"
                    );
                    $fs2->execute($instIds);
                    $usageFam = (int)$fs2->fetchColumn();
                }

                // Personal por rol: admin / cuidador (enfermero) / medico activos.
                // Combina pivot multi-institución + legacy usuarios.institucion_id y deduplica por rol.
                $ss = $db->prepare(
                    "SELECT usuario_id, rol_ui FROM (
                        SELECT DISTINCT ui.usuario_id,
                               CASE ui.rol WHEN 'enfermero' THEN 'cuidador' ELSE ui.rol END AS rol_ui
                        FROM usuario_instituciones ui
                        JOIN usuarios u ON u.id = ui.usuario_id
                        WHERE ui.institucion_id IN ($place)
                          AND ui.rol IN ('admin','medico','enfermero')
                          AND ui.estado = 'activo'
                          AND u.estado = 'activo'
                        UNION
                        SELECT DISTINCT u.id AS usuario_id,
                               CASE u.rol WHEN 'enfermero' THEN 'cuidador' ELSE u.rol END AS rol_ui
                        FROM usuarios u
                        WHERE u.institucion_id IN ($place)
                          AND u.rol IN ('admin','medico','enfermero')
                          AND u.estado = 'activo'
                     ) x"
                );
                $ss->execute(array_merge($instIds, $instIds));
                $seenByRol = ['admin' => [], 'cuidador' => [], 'medico' => []];
                foreach ($ss->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $k = $row['rol_ui'] ?? '';
                    if (isset($seenByRol[$k])) $seenByRol[$k][(int)$row['usuario_id']] = true;
                }
                $byRol = [
                    'admin'    => count($seenByRol['admin']),
                    'cuidador' => count($seenByRol['cuidador']),
                    'medico'   => count($seenByRol['medico']),
                ];
                $usageStaff = $byRol['admin'] + $byRol['cuidador'] + $byRol['medico'];
            }
            $resp['usage'] = [
                'residentes' => $usageRes,
                'familiares' => $usageFam,
                'personal'   => $usageStaff,
                'admin'      => $byRol['admin']    ?? 0,
                'cuidador'   => $byRol['cuidador'] ?? 0,
                'medico'     => $byRol['medico']   ?? 0,
            ];

            // Best-effort: refresca el cache para dejar limpio (no afecta la respuesta).
            try {
                foreach ($instIds as $_iid) {
                    Seats::recalcUsage((int)$sub['id'], $_iid);
                }
            } catch (\Throwable $e) { /* silent */ }
        } else {
            $sessInst = (int)($_SESSION['institucion_id'] ?? 0);
            if ($sessInst > 0) {
                try {
                    $trial = $db->prepare("SELECT id, nombre, estado, trial_ends_at FROM instituciones WHERE id = ? LIMIT 1");
                    $trial->execute([$sessInst]);
                    $row = $trial->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($row && $row['estado'] === 'trial') {
                        $resp['institution_trial'] = [
                            'id' => (int)$row['id'],
                            'nombre' => $row['nombre'],
                            'estado' => $row['estado'],
                            'trial_ends_at' => $row['trial_ends_at'],
                        ];
                    }
                } catch (\Throwable $e) { /* silent */ }
            }
        }
    } elseif ($rol === 'familiar') {
        $stmt = $db->prepare(
            "SELECT sf.*, a.nombre AS addon_nombre, a.codigo AS addon_codigo
             FROM suscripciones_familiar sf
             LEFT JOIN addons a ON a.id = sf.addon_id
             WHERE sf.usuario_familiar_id = ?
             ORDER BY sf.id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $sf = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($sf) {
            $resp['subscription'] = [
                'id'              => (int)$sf['id'],
                'kind'            => 'familiar',
                'addon_nombre'    => $sf['addon_nombre'],
                'addon_codigo'    => $sf['addon_codigo'],
                'moneda'          => $sf['moneda'],
                'periodo'         => $sf['periodo'],
                'estado'          => $sf['estado'],
                'periodo_inicio'  => $sf['periodo_inicio'],
                'periodo_fin'     => $sf['periodo_fin'],
                'cancel_at_period_end' => (int)$sf['cancel_at_period_end'],
                'has_stripe'      => !empty($sf['stripe_subscription_id']),
                'has_customer'    => !empty($sf['stripe_customer_id']),
                'currency_default'=> $sf['moneda'],
            ];
            $resp['currency_default'] = $sf['moneda'];
            try {
                $pay = $db->prepare(
                    "SELECT tipo, stripe_invoice_id, monto, moneda, status, recibido_at
                     FROM pagos_historial
                     WHERE suscripcion_familiar_id = ?
                       AND tipo IN ('invoice.paid','invoice.payment_succeeded')
                       AND monto IS NOT NULL
                     ORDER BY recibido_at DESC, id DESC LIMIT 1"
                );
                $pay->execute([(int)$sf['id']]);
                $resp['subscription']['ultimo_cobro'] = $pay->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $e) {
                $resp['subscription']['ultimo_cobro'] = null;
            }
        }
    }
    api_ok($resp);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET payment_history: historial de eventos de cobro visibles para el usuario
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'payment_history') {
    api_require_method('GET');
    if (!in_array($rol, ['admin','superadmin','familiar'], true)) api_error('No autorizado', 403);

    $rows = [];
    if (in_array($rol, ['admin','superadmin'], true)) {
        $st = $db->prepare("SELECT id FROM suscripciones WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $subId = (int)($st->fetchColumn() ?: 0);
        if ($subId > 0) {
            $q = $db->prepare(
                                "SELECT tipo, stripe_invoice_id, monto, moneda, status, recibido_at, raw_payload
                 FROM pagos_historial
                 WHERE suscripcion_id = ?
                   AND tipo IN ('checkout.session.completed','invoice.paid','invoice.payment_succeeded','invoice.payment_failed')
                 ORDER BY recibido_at DESC, id DESC LIMIT 30"
            );
            $q->execute([$subId]);
                        $rows = billing_public_payment_rows($q->fetchAll(PDO::FETCH_ASSOC));
        }
    } else {
        $st = $db->prepare("SELECT id FROM suscripciones_familiar WHERE usuario_familiar_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $sfId = (int)($st->fetchColumn() ?: 0);
        if ($sfId > 0) {
            $q = $db->prepare(
                                "SELECT tipo, stripe_invoice_id, monto, moneda, status, recibido_at, raw_payload
                 FROM pagos_historial
                 WHERE suscripcion_familiar_id = ?
                   AND tipo IN ('checkout.session.completed','invoice.paid','invoice.payment_succeeded','invoice.payment_failed')
                 ORDER BY recibido_at DESC, id DESC LIMIT 30"
            );
            $q->execute([$sfId]);
                        $rows = billing_public_payment_rows($q->fetchAll(PDO::FETCH_ASSOC));
        }
    }
    api_ok(['payments' => $rows]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET usage_breakdown: desglose por institución del consumo de asientos
// Devuelve, por institución que cubre la suscripción del owner:
//   { id, nombre, residentes:[{id,nombre,apellidos,habitacion,fecha_ingreso,
//                              familiares:[{id,nombre,email,parentesco?}]}],
//     residentes_huerfanos:[…],          // sin familiares
//     familiares_sueltos:[{id,nombre,email}] // familiares en la inst sin residente vinculado
//   }
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'usage_breakdown') {
    api_require_method('GET');
    if (!in_array($rol, ['admin','superadmin'], true)) api_error('No autorizado', 403);

    // Resolver instituciones cubiertas (misma lógica que status, fallback incluido).
    $sub = $db->prepare("SELECT id FROM suscripciones WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1");
    $sub->execute([$userId]);
    $subId = (int)($sub->fetchColumn() ?: 0);

    $instIds = [];
    if ($subId > 0) {
        $st = $db->prepare("SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id = ?");
        $st->execute([$subId]);
        $instIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    if (empty($instIds)) {
        $sessInst = (int)($_SESSION['institucion_id'] ?? 0);
        if ($sessInst > 0) $instIds[] = $sessInst;
        try {
            $owns = $db->prepare(
                "SELECT DISTINCT institucion_id FROM usuario_instituciones
                 WHERE usuario_id = ? AND rol IN ('admin','superadmin') AND estado = 'activo'"
            );
            $owns->execute([$userId]);
            foreach ($owns->fetchAll(PDO::FETCH_COLUMN) as $iid) {
                $iid = (int)$iid;
                if ($iid && !in_array($iid, $instIds, true)) $instIds[] = $iid;
            }
        } catch (\Throwable $e) { /* silent */ }
    }

    if (empty($instIds)) { api_ok(['instituciones' => []]); }

    $place = implode(',', array_fill(0, count($instIds), '?'));

    // Instituciones
    $iq = $db->prepare("SELECT id, nombre, logo_path FROM instituciones WHERE id IN ($place) ORDER BY nombre");
    $iq->execute($instIds);
    $insts = $iq->fetchAll(PDO::FETCH_ASSOC);

    // Residentes activos (sin PHI sensible: nombre, apellidos, habitación, fecha_ingreso)
    $rq = $db->prepare(
        "SELECT id, institucion_id, nombre, apellidos, habitacion, fecha_ingreso, foto_path
         FROM residentes
         WHERE institucion_id IN ($place) AND estado = 'activo'
         ORDER BY apellidos, nombre"
    );
    $rq->execute($instIds);
    $residentes = $rq->fetchAll(PDO::FETCH_ASSOC);

    // Vinculación familiar↔residente: usuario_residentes pivot.
    $resIds = array_map(fn($r) => (int)$r['id'], $residentes);
    $famByRes = [];   // [res_id] => [ {usuario_id, nombre, email, parentesco} ]
    $famUserIds = []; // set de usuario_id ya asociados a algún residente
    if (!empty($resIds)) {
        $rp = implode(',', array_fill(0, count($resIds), '?'));
        try {
            $vq = $db->prepare(
                "SELECT ur.residente_id, u.id AS usuario_id, u.nombre, u.email
                 FROM usuario_residentes ur
                 JOIN usuarios u ON u.id = ur.usuario_id
                 WHERE ur.residente_id IN ($rp) AND u.estado = 'activo' AND u.rol = 'familiar'"
            );
            $vq->execute($resIds);
            foreach ($vq->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rid = (int)$row['residente_id'];
                $famByRes[$rid][] = $row;
                $famUserIds[(int)$row['usuario_id']] = true;
            }
        } catch (\Throwable $e) { /* tabla puede no existir; ignorar */ }
    }

    // Familiares de cada institución (vía pivot ui + fallback legacy).
    $famByInst = [];    try {
        $fq = $db->prepare(
            "SELECT DISTINCT ui.institucion_id, u.id AS usuario_id, u.nombre, u.email
             FROM usuario_instituciones ui
             JOIN usuarios u ON u.id = ui.usuario_id
             WHERE ui.institucion_id IN ($place)
               AND ui.rol = 'familiar' AND ui.estado = 'activo' AND u.estado = 'activo'
             ORDER BY u.nombre"
        );
        $fq->execute($instIds);
        foreach ($fq->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $famByInst[(int)$row['institucion_id']][] = $row;
        }
    } catch (\Throwable $e) {}
    // Fallback legacy
    try {
        $fq2 = $db->prepare(
            "SELECT institucion_id, id AS usuario_id, nombre, email
             FROM usuarios
             WHERE institucion_id IN ($place) AND rol = 'familiar' AND estado = 'activo'"
        );
        $fq2->execute($instIds);
        foreach ($fq2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $iid = (int)$row['institucion_id'];
            // Evitar duplicados con los del pivot
            $exists = false;
            foreach ($famByInst[$iid] ?? [] as $x) {
                if ((int)$x['usuario_id'] === (int)$row['usuario_id']) { $exists = true; break; }
            }
            if (!$exists) $famByInst[$iid][] = $row;
        }
    } catch (\Throwable $e) {}

    // Personal (no-familiares: admin/medico/enfermero) por institución.
    $staffByInst = [];
    try {
        $sq = $db->prepare(
            "SELECT DISTINCT ui.institucion_id, u.id AS usuario_id, u.nombre, u.email,
                    COALESCE(ui.rol, u.rol) AS rol
             FROM usuario_instituciones ui
             JOIN usuarios u ON u.id = ui.usuario_id
             WHERE ui.institucion_id IN ($place)
               AND COALESCE(ui.rol, u.rol) IN ('admin','medico','enfermero')
               AND ui.estado = 'activo' AND u.estado = 'activo'
             ORDER BY u.nombre"
        );
        $sq->execute($instIds);
        foreach ($sq->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $staffByInst[(int)$row['institucion_id']][] = $row;
        }
    } catch (\Throwable $e) {}
    // Fallback legacy
    try {
        $sq2 = $db->prepare(
            "SELECT institucion_id, id AS usuario_id, nombre, email, rol
             FROM usuarios
             WHERE institucion_id IN ($place)
               AND rol IN ('admin','medico','enfermero') AND estado = 'activo'"
        );
        $sq2->execute($instIds);
        foreach ($sq2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $iid = (int)$row['institucion_id'];
            $exists = false;
            foreach ($staffByInst[$iid] ?? [] as $x) {
                if ((int)$x['usuario_id'] === (int)$row['usuario_id']) { $exists = true; break; }
            }
            if (!$exists) $staffByInst[$iid][] = $row;
        }
    } catch (\Throwable $e) {}

    // Armar respuesta
    $out = [];
    foreach ($insts as $inst) {        $iid = (int)$inst['id'];
        $resOfInst = array_values(array_filter($residentes, fn($r) => (int)$r['institucion_id'] === $iid));
        $resList = [];
        foreach ($resOfInst as $r) {
            $rid = (int)$r['id'];
            $fams = $famByRes[$rid] ?? [];
            $resList[] = [
                'id'              => $rid,
                'nombre'          => $r['nombre'],
                'apellidos'       => $r['apellidos'],
                'habitacion'      => $r['habitacion'],
                'fecha_ingreso'   => $r['fecha_ingreso'],
                'familiares'      => array_map(fn($f) => [
                    'id'     => (int)$f['usuario_id'],
                    'nombre' => $f['nombre'],
                    'email'  => $f['email'],
                ], $fams),
                'familiares_count'=> count($fams),
            ];
        }
        // Familiares de la institución que no están vinculados a ningún residente (de esta inst).
        $famSueltos = [];
        foreach ($famByInst[$iid] ?? [] as $f) {
            if (empty($famUserIds[(int)$f['usuario_id']])) {
                $famSueltos[] = [
                    'id'     => (int)$f['usuario_id'],
                    'nombre' => $f['nombre'],
                    'email'  => $f['email'],
                ];
            }
        }
        // Map BD rol → bucket UI (enfermero → cuidador).
        $rolUiMap = ['admin' => 'admin', 'enfermero' => 'cuidador', 'medico' => 'medico'];
        $personalAll = $staffByInst[$iid] ?? [];
        $personalNorm = array_map(function ($s) use ($rolUiMap) {
            $rolDb = $s['rol'] ?? '';
            return [
                'id'     => (int)$s['usuario_id'],
                'nombre' => $s['nombre'],
                'email'  => $s['email'],
                'rol'    => $rolDb,
                'rol_ui' => $rolUiMap[$rolDb] ?? $rolDb,
            ];
        }, $personalAll);
        $bucket = ['admin' => [], 'cuidador' => [], 'medico' => []];
        foreach ($personalNorm as $p) {
            $k = $p['rol_ui'];
            if (isset($bucket[$k])) $bucket[$k][] = $p;
        }
        $out[] = [
            'id'                  => $iid,
            'nombre'              => $inst['nombre'],
            'logo_path'           => $inst['logo_path'],
            'residentes_count'    => count($resList),
            'familiares_count'    => count($famByInst[$iid] ?? []),
            'personal_count'      => count($personalAll),
            'admin_count'         => count($bucket['admin']),
            'cuidador_count'      => count($bucket['cuidador']),
            'medico_count'        => count($bucket['medico']),
            'residentes'          => $resList,
            'familiares_sueltos'  => $famSueltos,
            'personal'            => $personalNorm,
            'admin'               => $bucket['admin'],
            'cuidador'            => $bucket['cuidador'],
            'medico'              => $bucket['medico'],
        ];
    }
    api_ok(['instituciones' => $out]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET plans: catálogo
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'plans') {
    api_require_method('GET');
    $planColumns = billing_table_columns($db, 'planes');
    $currentPlanId = 0;
    if (in_array($rol, ['admin','superadmin'], true)) {
        $stCur = $db->prepare("SELECT plan_id FROM suscripciones WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1");
        $stCur->execute([$userId]);
        $currentPlanId = (int)($stCur->fetchColumn() ?: 0);
    }
    $planListSelect = implode(",\n        ", [
        billing_select_col($planColumns, 'p', 'id', '0', 'id'),
        billing_select_col($planColumns, 'p', 'key', 'NULL', 'key'),
        billing_select_col($planColumns, 'p', 'nombre', 'NULL', 'nombre'),
        billing_select_col($planColumns, 'p', 'tier', "'basico'", 'tier'),
        billing_select_col($planColumns, 'p', 'descripcion', 'NULL', 'descripcion'),
        billing_select_col($planColumns, 'p', 'max_residentes', 'NULL', 'max_residentes'),
        billing_select_col($planColumns, 'p', 'max_familiares', 'NULL', 'max_familiares'),
        billing_select_col($planColumns, 'p', 'max_instituciones', 'NULL', 'max_instituciones'),
        billing_select_col($planColumns, 'p', 'max_usuarios', 'NULL', 'max_usuarios'),
        billing_select_col($planColumns, 'p', 'max_admin', 'NULL', 'max_admin'),
        billing_select_col($planColumns, 'p', 'max_cuidador', 'NULL', 'max_cuidador'),
        billing_select_col($planColumns, 'p', 'max_medico', 'NULL', 'max_medico'),
        billing_select_col($planColumns, 'p', 'trial_dias', '0', 'trial_dias'),
        billing_select_col($planColumns, 'p', 'solicita_tarjeta_registro', '0', 'solicita_tarjeta_registro'),
        billing_select_col($planColumns, 'p', 'requiere_cotizacion', '0', 'requiere_cotizacion'),
        billing_select_col($planColumns, 'p', 'stripe_product_id', 'NULL', 'stripe_product_id'),
        billing_select_col($planColumns, 'p', 'orden', '0', 'orden'),
    ]);
    $orderBy = isset($planColumns['orden']) ? 'p.`orden`, p.`id`' : 'p.`id`';
    $planes = $db->query(
    "SELECT {$planListSelect}
         FROM planes p WHERE p.activo = 1
         ORDER BY {$orderBy}"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($planes) {
        $ids = array_column($planes, 'id');
        $in  = implode(',', array_map('intval', $ids));
        $precios = $db->query(
            "SELECT plan_id, moneda, periodo, precio, stripe_price_id, activo
             FROM plan_precios WHERE plan_id IN ($in) AND activo = 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byPlan = [];
        foreach ($precios as $p) {
            $byPlan[(int)$p['plan_id']][] = $p;
        }
        foreach ($planes as &$pl) {
            $pl['precios'] = $byPlan[(int)$pl['id']] ?? [];
        }
        unset($pl);
    }

    // Add-ons (asientos extra)
    $addons = $db->query(
        "SELECT a.id, a.codigo, a.nombre, a.tipo, a.descripcion, a.stripe_product_id
         FROM addons a WHERE a.activo = 1 ORDER BY a.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($addons) {
        $aids = implode(',', array_map(fn($a) => (int)$a['id'], $addons));
        $apr  = $db->query(
            "SELECT addon_id, moneda, periodo, precio, stripe_price_id, activo
             FROM addon_precios WHERE addon_id IN ($aids) AND activo = 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byA = [];
        foreach ($apr as $r) $byA[(int)$r['addon_id']][] = $r;

        $planAddonByPlan = [];
        $planAddonCurrent = [];
        try {
            $pap = $db->query(
                "SELECT plan_id, addon_id, moneda, periodo, precio, stripe_price_id, activo
                 FROM plan_addon_precios
                 WHERE activo = 1"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pap as $r) {
                $pid = (int)$r['plan_id'];
                $aid = (int)$r['addon_id'];
                $r['source'] = 'plan';
                $planAddonByPlan[$pid][] = $r;
                if ($currentPlanId > 0 && $pid === $currentPlanId) {
                    $planAddonCurrent[$aid][] = $r;
                }
            }
        } catch (\Throwable $e) {
            $planAddonByPlan = [];
            $planAddonCurrent = [];
        }

        foreach ($planes as &$pl) {
            $pl['addon_precios'] = $planAddonByPlan[(int)$pl['id']] ?? [];
        }
        unset($pl);

        foreach ($addons as &$a) {
            $global = $byA[(int)$a['id']] ?? [];
            foreach ($global as &$g) $g['source'] = 'global';
            unset($g);
            $planSpecific = $planAddonCurrent[(int)$a['id']] ?? [];
            $effectiveByKey = [];
            foreach ($global as $row) {
                $effectiveByKey[$row['moneda'] . '|' . $row['periodo']] = $row;
            }
            foreach ($planSpecific as $row) {
                $effectiveByKey[$row['moneda'] . '|' . $row['periodo']] = $row;
            }
            $a['precios_global'] = $global;
            $a['precios_plan'] = $planSpecific;
            $a['precios'] = array_values($effectiveByKey);
            $a['pricing_plan_id'] = $currentPlanId ?: null;
        }
        unset($a);
    }

    api_ok(['planes' => $planes, 'addons' => $addons]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST checkout: crear Checkout Session
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'checkout') {
    api_require_method('POST');
    if ($isNative) api_error('La gestión de suscripción no está disponible en esta app. Contacta al administrador de tu institución.', 403);

    $body = api_body();
    $kind = $body['kind'] ?? 'admin';
    $moneda = strtoupper((string)($body['moneda'] ?? 'MXN'));
    $periodo = (string)($body['periodo'] ?? 'mensual');
    if (!in_array($periodo, ['mensual','anual'], true)) api_error('periodo inválido');
    $quantity = max(1, (int)($body['quantity'] ?? 1));
    // Embedded Checkout: una sola return_url ABSOLUTA (Stripe la abre cuando
    // termina el flow dentro del iframe) con placeholder {CHECKOUT_SESSION_ID}.
    // Stripe exige https://... — usamos app_public_url() y respetamos override
    // del cliente sólo si ya es absoluto.
    $publicBase = app_public_url();
    $defaultReturn = $publicBase . '/billing.php?paid=1&session_id={CHECKOUT_SESSION_ID}';
    $rawReturn = trim((string)($body['return_url'] ?? ''));
    $returnUrl = (preg_match('#^https?://#i', $rawReturn) ? $rawReturn : $defaultReturn);
    // Si el cliente envió una URL absoluta sin el placeholder, lo añadimos.
    if (strpos($returnUrl, '{CHECKOUT_SESSION_ID}') === false) {
        $returnUrl .= (str_contains($returnUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}';
    }

    $stripe = new StripeClient();
    $email  = $_SESSION['user_email'] ?? '';
    $name   = $_SESSION['user_nombre'] ?? '';

    // Resolver price_id según kind ────────────────────────────────────────────
    $priceId = null;
    $requiresCardOnRegistration = false;
    $metadata = [
        'geriapp_kind'      => $kind,
        'geriapp_user_id'   => (string)$userId,
        'geriapp_moneda'    => $moneda,
        'geriapp_periodo'   => $periodo,
    ];

    if ($kind === 'admin') {
        if (!in_array($rol, ['admin','superadmin'], true)) api_error('Solo administradores pueden contratar planes', 403);
        $planId = (int)($body['plan_id'] ?? 0);
        if ($planId <= 0) api_error('plan_id requerido');
        $planColumns = billing_table_columns($db, 'planes');
        $checkoutPlanSelect = implode(', ', [
            billing_select_col($planColumns, 'p', 'requiere_cotizacion', '0', 'requiere_cotizacion'),
            billing_select_col($planColumns, 'p', 'solicita_tarjeta_registro', '0', 'solicita_tarjeta_registro'),
            billing_select_col($planColumns, 'p', 'stripe_product_id', 'NULL', 'stripe_product_id'),
            billing_select_col($planColumns, 'p', 'trial_dias', '0', 'trial_dias'),
        ]);
        $st = $db->prepare("SELECT {$checkoutPlanSelect} FROM planes p WHERE p.id = ? AND p.activo = 1");
        $st->execute([$planId]);
        $pl = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pl) api_error('Plan no existe');
        if ((int)$pl['requiere_cotizacion'] === 1) api_error('Este plan requiere cotización previa', 400);
        $requiresCardOnRegistration = ((int)($pl['solicita_tarjeta_registro'] ?? 0) === 1);
        $st = $db->prepare("SELECT stripe_price_id FROM plan_precios WHERE plan_id = ? AND moneda = ? AND periodo = ? AND activo = 1 LIMIT 1");
        $st->execute([$planId, $moneda, $periodo]);
        $priceId = $st->fetchColumn();
        if (!$priceId) api_error("Precio no disponible para $moneda/$periodo. Sincroniza con Stripe primero.");
        $metadata['geriapp_plan_id']  = (string)$planId;
        $trialDays = max(0, min(365, (int)($pl['trial_dias'] ?? 0)));
        $hasPreviousSubscription = false;
        $stPrev = $db->prepare("SELECT COUNT(*) FROM suscripciones WHERE owner_user_id = ? AND stripe_subscription_id IS NOT NULL");
        $stPrev->execute([$userId]);
        $hasPreviousSubscription = ((int)$stPrev->fetchColumn()) > 0;
        if ($trialDays > 0 && !$hasPreviousSubscription) {
            $metadata['geriapp_trial_dias'] = (string)$trialDays;
        }
        // Si se incluye institucion_id la mandamos como hint (el webhook lo ignora si no aplica)
        if (!empty($body['institucion_id'])) {
            $instId = (int)$body['institucion_id'];
            if ($instId > 0) {
                $metadata['geriapp_institucion_id'] = (string)$instId;
                if ($requiresCardOnRegistration && !$hasPreviousSubscription) {
                    $stTrial = $db->prepare(
                        "SELECT COALESCE(
                            (SELECT s.trial_ends_at
                             FROM suscripciones s
                             JOIN suscripcion_instituciones si ON si.suscripcion_id = s.id
                             WHERE s.owner_user_id = ? AND si.institucion_id = i.id
                             ORDER BY s.id DESC LIMIT 1),
                            CASE WHEN i.trial_ends_at IS NULL THEN NULL ELSE CONCAT(i.trial_ends_at, ' 23:59:59') END
                         ) AS trial_ends_at
                         FROM instituciones i
                         JOIN usuario_instituciones ui ON ui.institucion_id = i.id AND ui.usuario_id = ? AND ui.estado = 'activo'
                         WHERE i.id = ?
                         LIMIT 1"
                    );
                    $stTrial->execute([$userId, $userId, $instId]);
                    $trialEnd = $stTrial->fetchColumn();
                    $trialEndTs = $trialEnd ? strtotime((string)$trialEnd) : false;
                    if ($trialEndTs && $trialEndTs > time()) {
                        $metadata['geriapp_trial_end_ts'] = (string)$trialEndTs;
                    }
                }
            }
        }
        if ($requiresCardOnRegistration) {
            $metadata['geriapp_solicita_tarjeta_registro'] = '1';
        }
    } elseif ($kind === 'familiar') {
        if ($rol !== 'familiar') api_error('Solo el rol familiar puede contratar este plan', 403);
        $addonId = (int)($body['addon_id'] ?? 0);
        if ($addonId <= 0) api_error('addon_id requerido');
        $st = $db->prepare("SELECT tipo FROM addons WHERE id = ? AND activo = 1");
        $st->execute([$addonId]);
        $tipo = $st->fetchColumn();
        if ($tipo !== 'asiento_familiar') api_error('Addon no válido para familiar');
        $st = $db->prepare("SELECT stripe_price_id FROM addon_precios WHERE addon_id = ? AND moneda = ? AND periodo = ? AND activo = 1 LIMIT 1");
        $st->execute([$addonId, $moneda, $periodo]);
        $priceId = $st->fetchColumn();
        if (!$priceId) api_error("Precio no disponible para $moneda/$periodo");
        $metadata['geriapp_addon_id'] = (string)$addonId;
        if (!empty($body['institucion_id'])) $metadata['geriapp_institucion_id'] = (string)(int)$body['institucion_id'];
    } elseif ($kind === 'seat') {
        if (!in_array($rol, ['admin','superadmin'], true)) api_error('Solo administradores pueden comprar asientos', 403);
        $addonId = (int)($body['addon_id'] ?? 0);
        if ($addonId <= 0) api_error('addon_id requerido');
        $st = $db->prepare("SELECT id FROM addons WHERE id = ? AND activo = 1 LIMIT 1");
        $st->execute([$addonId]);
        if (!$st->fetchColumn()) api_error('Addon no válido');

        $currentPlanId = 0;
        $st = $db->prepare("SELECT plan_id FROM suscripciones WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $currentPlanId = (int)($st->fetchColumn() ?: 0);

        $usedPlanAddonPrice = false;
        if ($currentPlanId > 0) {
            try {
                $st = $db->prepare("SELECT stripe_price_id FROM plan_addon_precios WHERE plan_id = ? AND addon_id = ? AND moneda = ? AND periodo = ? AND activo = 1 LIMIT 1");
                $st->execute([$currentPlanId, $addonId, $moneda, $periodo]);
                $planPrice = $st->fetchColumn();
                if ($planPrice !== false) {
                    $priceId = $planPrice;
                    $usedPlanAddonPrice = true;
                }
            } catch (\Throwable $e) {
                $usedPlanAddonPrice = false;
            }
        }

        if (!$priceId) {
            if ($usedPlanAddonPrice) api_error("Costo de add-on configurado para este paquete, pero falta sincronizar Stripe para $moneda/$periodo.");
            $st = $db->prepare("SELECT stripe_price_id FROM addon_precios WHERE addon_id = ? AND moneda = ? AND periodo = ? AND activo = 1 LIMIT 1");
            $st->execute([$addonId, $moneda, $periodo]);
            $priceId = $st->fetchColumn();
        }
        if (!$priceId) api_error("Precio no disponible para $moneda/$periodo");
        $metadata['geriapp_addon_id'] = (string)$addonId;
        if ($currentPlanId > 0) $metadata['geriapp_plan_id'] = (string)$currentPlanId;
        $metadata['geriapp_addon_price_source'] = $usedPlanAddonPrice ? 'plan' : 'global';
    } else {
        api_error('kind inválido');
    }

    // Buscar customer existente
    $customerId = null;
    if ($kind === 'familiar') {
        $st = $db->prepare("SELECT stripe_customer_id FROM suscripciones_familiar WHERE usuario_familiar_id = ? AND stripe_customer_id IS NOT NULL LIMIT 1");
    } else {
        $st = $db->prepare("SELECT stripe_customer_id FROM suscripciones WHERE owner_user_id = ? AND stripe_customer_id IS NOT NULL LIMIT 1");
    }
    $st->execute([$userId]);
    $customerId = $st->fetchColumn() ?: null;

    // Crear sesión Checkout (modo embebido: se monta en un <div> dentro de
    // billing.php usando Stripe.js, sin redirección a checkout.stripe.com).
    $params = [
        'ui_mode'                 => 'embedded',
        'mode'                    => 'subscription',
        'line_items[0][price]'    => $priceId,
        'line_items[0][quantity]' => $quantity,
        'return_url'              => $returnUrl,
        'allow_promotion_codes'   => 'true',
    ];
    if ($customerId) {
        $params['customer'] = $customerId;
    } elseif ($email) {
        $params['customer_email'] = $email;
    }
    if ($kind === 'admin' && (!empty($metadata['geriapp_trial_dias']) || !empty($metadata['geriapp_trial_end_ts']))) {
        if (!empty($metadata['geriapp_trial_end_ts'])) {
            $params['subscription_data[trial_end]'] = (string)(int)$metadata['geriapp_trial_end_ts'];
        } else {
            $params['subscription_data[trial_period_days]'] = (string)(int)$metadata['geriapp_trial_dias'];
        }
    }
    if ($kind === 'admin' && $requiresCardOnRegistration) {
        $params['payment_method_collection'] = 'always';
    }
    foreach ($metadata as $k => $v) {
        $params['metadata['  . $k . ']'] = (string)$v;
        // Replicar también en subscription_data.metadata para que el webhook
        // de subscription.updated tenga acceso al kind/user_id sin ir al session.
        $params['subscription_data[metadata][' . $k . ']'] = (string)$v;
    }

    try {
        $session = $stripe->createCheckoutSession($params, "geriapp_co_{$userId}_" . bin2hex(random_bytes(4)));
    } catch (\Throwable $e) {
        api_error('Stripe rechazó la sesión: ' . $e->getMessage(), 502);
    }
    api_ok([
        'client_secret' => $session['client_secret'] ?? null,
        'id'            => $session['id'] ?? null,
        'publishable_key' => StripeClient::publishableKey(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST portal: Stripe Billing Portal Session
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'portal') {
    api_require_method('POST');
    if ($isNative) api_error('Esta opción no está disponible en la app. Contacta al administrador de tu institución.', 403);

    $body = api_body();
    $publicBase = app_public_url();
    $rawReturn  = trim((string)($body['return_url'] ?? ''));
    $returnUrl  = (preg_match('#^https?://#i', $rawReturn) ? $rawReturn : ($publicBase . '/billing.php'));

    // Buscar customer
    if ($rol === 'familiar') {
        $st = $db->prepare("SELECT stripe_customer_id FROM suscripciones_familiar WHERE usuario_familiar_id = ? AND stripe_customer_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    } else {
        $st = $db->prepare("SELECT stripe_customer_id FROM suscripciones WHERE owner_user_id = ? AND stripe_customer_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    }
    $st->execute([$userId]);
    $custId = $st->fetchColumn();
    if (!$custId) api_error('No tienes una suscripción activa todavía. Contrata un plan primero.');

    try {
        $stripe = new StripeClient();
        $sess = $stripe->createBillingPortalSession($custId, $returnUrl);
    } catch (\Throwable $e) {
        api_error('Stripe rechazó la solicitud: ' . $e->getMessage(), 502);
    }
    api_ok(['url' => $sess['url'] ?? null]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST sync: consulta directa a Stripe y refresca BD para el usuario actual.
// Usado por billing.php tras Embedded Checkout onComplete (no esperar webhook)
// y como fallback al cargar la página con ?paid=1.
// Nota: este endpoint sólo refresca la suscripción del usuario autenticado;
// los webhooks siguen siendo la fuente de verdad para eventos de Stripe que
// el navegador del usuario no inicia (renovaciones, payment_failed, etc.).
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'sync') {
    api_require_method('POST');
    $body = api_body();

    $statusMap = [
        'trialing'           => 'trial',
        'active'             => 'activa',
        'past_due'           => 'past_due',
        'unpaid'             => 'past_due',
        'canceled'           => 'cancelada',
        'incomplete'         => 'incompleta',
        'incomplete_expired' => 'cancelada',
        'paused'             => 'pausada',
    ];

    $checkoutSessionId = trim((string)($body['checkout_session_id'] ?? $body['session_id'] ?? ''));
    if ($checkoutSessionId !== '') {
        try {
            $stripe = new StripeClient();
            $session = $stripe->retrieveCheckoutSession($checkoutSessionId);
        } catch (\Throwable $e) {
            api_error('No se pudo consultar Checkout en Stripe: ' . $e->getMessage(), 502);
        }

        $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $sessionUserId = (int)($meta['geriapp_user_id'] ?? 0);
        if ($sessionUserId !== $userId) api_error('La sesión de pago no pertenece al usuario actual', 403);

        $sessionKind = (string)($meta['geriapp_kind'] ?? '');
        $customerId = (string)($session['customer'] ?? '');
        $stripeSubIdFromSession = (string)($session['subscription'] ?? '');
        if ($customerId === '' || $stripeSubIdFromSession === '') {
            api_ok(['synced' => false, 'reason' => 'checkout_not_subscription']);
        }

        if ($sessionKind === 'admin') {
            if (!in_array($rol, ['admin','superadmin'], true)) api_error('Solo administradores pueden sincronizar esta suscripción', 403);
            $planId = (int)($meta['geriapp_plan_id'] ?? 0);
            if ($planId <= 0) api_error('La sesión de pago no incluye plan', 422);
            $monedaMeta = strtoupper((string)($meta['geriapp_moneda'] ?? 'MXN'));
            $periodoMeta = (string)($meta['geriapp_periodo'] ?? 'mensual');

            $existing = $db->prepare("SELECT id FROM suscripciones WHERE owner_user_id = ? ORDER BY id DESC LIMIT 1");
            $existing->execute([$userId]);
            $rowId = (int)$existing->fetchColumn();
            if ($rowId > 0) {
                $db->prepare(
                    "UPDATE suscripciones
                     SET plan_id=?, moneda=?, periodo=?, stripe_customer_id=?, stripe_subscription_id=?, estado='incompleta'
                     WHERE id=?"
                )->execute([$planId, $monedaMeta, $periodoMeta, $customerId, $stripeSubIdFromSession, $rowId]);
            } else {
                $db->prepare(
                    "INSERT INTO suscripciones (owner_user_id, plan_id, moneda, periodo, stripe_customer_id, stripe_subscription_id, estado)
                     VALUES (?, ?, ?, ?, ?, ?, 'incompleta')"
                )->execute([$userId, $planId, $monedaMeta, $periodoMeta, $customerId, $stripeSubIdFromSession]);
                $rowId = (int)$db->lastInsertId();
            }

            $instIdMeta = (int)($meta['geriapp_institucion_id'] ?? ($_SESSION['user_institucion_id'] ?? 0));
            if ($instIdMeta > 0) {
                $db->prepare("INSERT IGNORE INTO suscripcion_instituciones (suscripcion_id, institucion_id) VALUES (?, ?)")
                   ->execute([$rowId, $instIdMeta]);
            }
            $kind = 'admin';
            $stripeSubId = $stripeSubIdFromSession;
        } elseif ($sessionKind === 'familiar') {
            if ($rol !== 'familiar') api_error('Solo el familiar puede sincronizar esta suscripción', 403);
            $instIdMeta = (int)($meta['geriapp_institucion_id'] ?? ($_SESSION['user_institucion_id'] ?? 0));
            $addonId = (int)($meta['geriapp_addon_id'] ?? 0);
            $monedaMeta = strtoupper((string)($meta['geriapp_moneda'] ?? 'MXN'));
            $periodoMeta = (string)($meta['geriapp_periodo'] ?? 'mensual');

            $existing = $db->prepare("SELECT id FROM suscripciones_familiar WHERE usuario_familiar_id = ? AND COALESCE(institucion_id,0) = ? ORDER BY id DESC LIMIT 1");
            $existing->execute([$userId, $instIdMeta]);
            $rowId = (int)$existing->fetchColumn();
            if ($rowId > 0) {
                $db->prepare(
                    "UPDATE suscripciones_familiar
                     SET addon_id=?, moneda=?, periodo=?, stripe_customer_id=?, stripe_subscription_id=?, estado='incompleta'
                     WHERE id=?"
                )->execute([$addonId ?: null, $monedaMeta, $periodoMeta, $customerId, $stripeSubIdFromSession, $rowId]);
            } else {
                $db->prepare(
                    "INSERT INTO suscripciones_familiar (usuario_familiar_id, institucion_id, moneda, periodo, stripe_customer_id, stripe_subscription_id, addon_id, estado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'incompleta')"
                )->execute([$userId, $instIdMeta ?: null, $monedaMeta, $periodoMeta, $customerId, $stripeSubIdFromSession, $addonId ?: null]);
                $rowId = (int)$db->lastInsertId();
            }
            unset($_SESSION['familiar_extra_seat_required']);
            $kind = 'familiar';
            $stripeSubId = $stripeSubIdFromSession;
        }
    }

    // Buscar la suscripción del usuario (admin o familiar)
    $kind = $kind ?? null; $rowId = $rowId ?? 0; $stripeSubId = $stripeSubId ?? null;
    if (in_array($rol, ['admin','superadmin'], true)) {
        $st = $db->prepare("SELECT id, stripe_subscription_id FROM suscripciones WHERE owner_user_id=? AND stripe_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) { $kind = 'admin'; $rowId = (int)$r['id']; $stripeSubId = $r['stripe_subscription_id']; }
    }
    if (!$kind && $rol === 'familiar') {
        $st = $db->prepare("SELECT id, stripe_subscription_id FROM suscripciones_familiar WHERE usuario_familiar_id=? AND stripe_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) { $kind = 'familiar'; $rowId = (int)$r['id']; $stripeSubId = $r['stripe_subscription_id']; }
    }
    if (!$stripeSubId) api_ok(['synced' => false, 'reason' => 'no_subscription_yet']);

    // Traer estado real desde Stripe
    try {
        $stripe = new StripeClient();
        $sub = $stripe->retrieveSubscription($stripeSubId);
    } catch (\Throwable $e) {
        api_error('No se pudo consultar Stripe: ' . $e->getMessage(), 502);
    }

    $estado     = $statusMap[$sub['status'] ?? ''] ?? 'incompleta';
    $trialEnds  = !empty($sub['trial_end'])            ? date('Y-m-d H:i:s', (int)$sub['trial_end'])            : null;
    $periodIni  = !empty($sub['current_period_start']) ? date('Y-m-d H:i:s', (int)$sub['current_period_start']) : null;
    $periodFin  = !empty($sub['current_period_end'])   ? date('Y-m-d H:i:s', (int)$sub['current_period_end'])   : null;
    $cancelEnd  = !empty($sub['cancel_at_period_end']) ? 1 : 0;

    if ($kind === 'admin') {
        $db->prepare(
            "UPDATE suscripciones
             SET estado=?, trial_ends_at=?, periodo_inicio=?, periodo_fin=?, cancel_at_period_end=?
             WHERE id=?"
        )->execute([$estado, $trialEnds, $periodIni, $periodFin, $cancelEnd, $rowId]);

        // Aplicar a instituciones cubiertas (mismo criterio que webhook)
        $instIds = $db->prepare("SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id=?");
        $instIds->execute([$rowId]);
        $ids = $instIds->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            if ($estado === 'activa') {
                $db->exec("UPDATE instituciones SET estado='activa' WHERE id IN ($in) AND estado IN ('trial','past_due','suspendida')");
            } elseif ($estado === 'past_due') {
                $db->exec("UPDATE instituciones SET estado='past_due' WHERE id IN ($in) AND estado IN ('activa','trial')");
            } elseif ($estado === 'cancelada') {
                $db->exec("UPDATE instituciones SET estado='suspendida' WHERE id IN ($in) AND estado <> 'archivada'");
            }
        }
    } else {
        // familiar: enum no tiene 'trial'
        $estadoFam = ($estado === 'trial') ? 'incompleta' : $estado;
        $db->prepare(
            "UPDATE suscripciones_familiar
             SET estado=?, periodo_inicio=?, periodo_fin=?, cancel_at_period_end=?
             WHERE id=?"
        )->execute([$estadoFam, $periodIni, $periodFin, $cancelEnd, $rowId]);
    }

    api_ok([
        'synced'        => true,
        'stripe_status' => $sub['status'] ?? null,
        'estado'        => $estado,
        'periodo_fin'   => $periodFin,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST cotizacion (lead form para Empresarial 7)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'cotizacion') {
    api_require_method('POST');
    $body = api_body();
    $planId = (int)($body['plan_id'] ?? 0);
    $nombre = trim((string)($body['nombre_contacto'] ?? ($_SESSION['user_nombre'] ?? '')));
    $email  = trim((string)($body['email'] ?? ($_SESSION['user_email'] ?? '')));
    $tel    = trim((string)($body['telefono'] ?? ''));
    $instN  = trim((string)($body['institucion_nombre'] ?? ''));
    $resN   = isset($body['num_residentes']) ? (int)$body['num_residentes'] : null;
    $msg    = trim((string)($body['mensaje'] ?? ''));

    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('Nombre y email válido son requeridos');
    }

    $st = $db->prepare(
        "INSERT INTO cotizaciones
            (usuario_id, plan_id, nombre_contacto, email, telefono, institucion_nombre, num_residentes, mensaje, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'nueva')"
    );
    $st->execute([
        $userId, $planId ?: null, $nombre, $email, $tel ?: null,
        $instN ?: null, $resN, $msg ?: null,
    ]);
    api_ok(['id' => (int)$db->lastInsertId()], 'Cotización enviada. Te contactaremos pronto.');
}

api_error('Acción no reconocida', 404);
