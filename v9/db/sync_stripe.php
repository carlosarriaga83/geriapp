<?php
/**
 * GeriApp — Sync Stripe (planes + addons → Products + Prices)
 *
 * Lee la BD maestra y, para cada plan/addon ACTIVO sin `stripe_product_id`,
 * crea un Product en Stripe y persiste el ID. Luego, para cada fila de
 * `plan_precios` / `addon_precios` / `plan_addon_precios` ACTIVA sin
 * `stripe_price_id`, crea un Price recurrente y persiste el ID.
 *
 * Idempotencia:
 *  - Idempotency-Key = "geriapp_plan_<id>" / "geriapp_planprice_<id>" / etc.
 *    Stripe nunca crea duplicados aunque ejecutes el sync varias veces.
 *  - Si una fila ya tiene su `stripe_*_id`, se omite (no se actualiza).
 *
 * Uso:
 *  - CLI: php db/sync_stripe.php
 *  - Web: incluido inline desde superadmin/api.php (action=sync_stripe).
 *
 * NO crea Products para planes con `requiere_cotizacion=1` (no se venden por
 * checkout estándar). NO crea Prices para filas de precio inactivas.
 */

declare(strict_types=1);

$isCli    = (PHP_SAPI === 'cli');
$isInline = defined('GERIAPP_SYNC_STRIPE_INLINE') && GERIAPP_SYNC_STRIPE_INLINE;
if (!$isCli && !$isInline) {
    require_once dirname(__DIR__) . '/superadmin/auth_middleware.php';
    header('Content-Type: text/plain; charset=utf-8');
}

require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/models/StripeClient.php';

$db     = Database::getMaster();
$stripe = new StripeClient();
$mode   = $stripe->mode();

$out = function(string $msg) use ($isInline) {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    if (!$isInline) { @ob_flush(); @flush(); }
};

$out("Modo Stripe: $mode");

$cnt = ['products'=>0, 'prices'=>0, 'skipped_products'=>0, 'skipped_prices'=>0, 'errors'=>0];

// ─────────────────────────────────────────────────────────────────────────────
// 1) PLANES → Products
// ─────────────────────────────────────────────────────────────────────────────
$planes = $db->query(
    "SELECT * FROM planes WHERE activo = 1 AND requiere_cotizacion = 0 ORDER BY orden, id"
)->fetchAll();

foreach ($planes as $plan) {
    $planId = (int)$plan['id'];
    if (!empty($plan['stripe_product_id'])) {
        $cnt['skipped_products']++;
        continue;
    }
    try {
        $product = $stripe->createProduct(
            $plan['nombre'],
            $plan['descripcion'] ?: null,
            ['geriapp_plan_id' => $planId, 'geriapp_plan_key' => $plan['key'], 'tier' => $plan['tier']],
            "geriapp_plan_$planId"
        );
        $db->prepare("UPDATE planes SET stripe_product_id = ? WHERE id = ?")
           ->execute([$product['id'], $planId]);
        $cnt['products']++;
        $out("✓ Plan «{$plan['nombre']}» → Product {$product['id']}");
    } catch (\Throwable $e) {
        $cnt['errors']++;
        $out("✗ Plan «{$plan['nombre']}»: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) PLAN_PRECIOS → Prices
// ─────────────────────────────────────────────────────────────────────────────
$precios = $db->query(
    "SELECT pp.*, p.stripe_product_id, p.nombre AS plan_nombre, p.requiere_cotizacion
     FROM plan_precios pp
     JOIN planes p ON p.id = pp.plan_id
     WHERE pp.activo = 1 AND p.activo = 1 AND p.requiere_cotizacion = 0
     ORDER BY pp.plan_id, pp.moneda, pp.periodo"
)->fetchAll();

foreach ($precios as $pp) {
    $ppId = (int)$pp['id'];
    if (!empty($pp['stripe_price_id'])) {
        $cnt['skipped_prices']++;
        continue;
    }
    if (empty($pp['stripe_product_id'])) {
        $cnt['errors']++;
        $out("✗ Precio #$ppId: el plan padre aún no tiene stripe_product_id");
        continue;
    }
    if ((float)$pp['precio'] <= 0) {
        $cnt['errors']++;
        $out("✗ Precio #$ppId ({$pp['plan_nombre']} {$pp['moneda']} {$pp['periodo']}): precio = 0, omitido");
        continue;
    }
    try {
        $interval = ($pp['periodo'] === 'anual') ? 'year' : 'month';
        $amount = StripeClient::toStripeAmount((float)$pp['precio'], $pp['moneda']);
        $price = $stripe->createRecurringPrice(
            $pp['stripe_product_id'],
            $amount,
            $pp['moneda'],
            $interval,
            ['geriapp_plan_precio_id' => $ppId, 'geriapp_plan_id' => $pp['plan_id']],
            "geriapp_planprice_$ppId"
        );
        $db->prepare("UPDATE plan_precios SET stripe_price_id = ? WHERE id = ?")
           ->execute([$price['id'], $ppId]);
        $cnt['prices']++;
        $out("✓ Precio {$pp['plan_nombre']} {$pp['moneda']}/{$pp['periodo']} \${$pp['precio']} → {$price['id']}");
    } catch (\Throwable $e) {
        $cnt['errors']++;
        $out("✗ Precio #$ppId ({$pp['plan_nombre']} {$pp['moneda']}/{$pp['periodo']}): " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) ADDONS → Products
// ─────────────────────────────────────────────────────────────────────────────
$addons = $db->query("SELECT * FROM addons WHERE activo = 1 ORDER BY id")->fetchAll();
foreach ($addons as $a) {
    $aId = (int)$a['id'];
    if (!empty($a['stripe_product_id'])) { $cnt['skipped_products']++; continue; }
    try {
        $product = $stripe->createProduct(
            $a['nombre'],
            $a['descripcion'] ?: null,
            ['geriapp_addon_id' => $aId, 'geriapp_addon_codigo' => $a['codigo'], 'tipo' => $a['tipo']],
            "geriapp_addon_$aId"
        );
        $db->prepare("UPDATE addons SET stripe_product_id = ? WHERE id = ?")
           ->execute([$product['id'], $aId]);
        $cnt['products']++;
        $out("✓ Add-on «{$a['nombre']}» → Product {$product['id']}");
    } catch (\Throwable $e) {
        $cnt['errors']++;
        $out("✗ Add-on «{$a['nombre']}»: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4) ADDON_PRECIOS → Prices globales fallback
// ─────────────────────────────────────────────────────────────────────────────
$apRows = $db->query(
    "SELECT ap.*, a.stripe_product_id, a.nombre AS addon_nombre
     FROM addon_precios ap
     JOIN addons a ON a.id = ap.addon_id
     WHERE ap.activo = 1 AND a.activo = 1
     ORDER BY ap.addon_id, ap.moneda, ap.periodo"
)->fetchAll();

foreach ($apRows as $ap) {
    $apId = (int)$ap['id'];
    if (!empty($ap['stripe_price_id'])) { $cnt['skipped_prices']++; continue; }
    if (empty($ap['stripe_product_id'])) {
        $cnt['errors']++;
        $out("✗ Addon-precio #$apId: el addon padre aún no tiene stripe_product_id");
        continue;
    }
    if ((float)$ap['precio'] <= 0) {
        $cnt['errors']++;
        $out("✗ Addon-precio #$apId: precio = 0, omitido");
        continue;
    }
    try {
        $interval = ($ap['periodo'] === 'anual') ? 'year' : 'month';
        $amount   = StripeClient::toStripeAmount((float)$ap['precio'], $ap['moneda']);
        $price = $stripe->createRecurringPrice(
            $ap['stripe_product_id'],
            $amount,
            $ap['moneda'],
            $interval,
            ['geriapp_addon_precio_id' => $apId, 'geriapp_addon_id' => $ap['addon_id']],
            "geriapp_addonprice_$apId"
        );
        $db->prepare("UPDATE addon_precios SET stripe_price_id = ? WHERE id = ?")
           ->execute([$price['id'], $apId]);
        $cnt['prices']++;
        $out("✓ Addon-precio {$ap['addon_nombre']} {$ap['moneda']}/{$ap['periodo']} \${$ap['precio']} → {$price['id']}");
    } catch (\Throwable $e) {
        $cnt['errors']++;
        $out("✗ Addon-precio #$apId: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5) PLAN_ADDON_PRECIOS → Prices por paquete
// ─────────────────────────────────────────────────────────────────────────────
try {
    $papRows = $db->query(
        "SELECT pap.*, a.stripe_product_id, a.nombre AS addon_nombre, p.nombre AS plan_nombre
         FROM plan_addon_precios pap
         JOIN addons a ON a.id = pap.addon_id
         JOIN planes p ON p.id = pap.plan_id
         WHERE pap.activo = 1 AND a.activo = 1 AND p.activo = 1 AND p.requiere_cotizacion = 0
         ORDER BY pap.plan_id, pap.addon_id, pap.moneda, pap.periodo"
    )->fetchAll();
} catch (\Throwable $e) {
    $papRows = [];
    $cnt['errors']++;
    $out('✗ No se pudo leer plan_addon_precios. Ejecuta Auditoría BD antes del sync: ' . $e->getMessage());
}

foreach ($papRows as $pap) {
    $papId = (int)$pap['id'];
    if (!empty($pap['stripe_price_id'])) { $cnt['skipped_prices']++; continue; }
    if (empty($pap['stripe_product_id'])) {
        $cnt['errors']++;
        $out("✗ Addon-paquete-precio #$papId: el addon padre aún no tiene stripe_product_id");
        continue;
    }
    if ((float)$pap['precio'] <= 0) {
        $cnt['errors']++;
        $out("✗ Addon-paquete-precio #$papId: precio = 0, omitido");
        continue;
    }
    try {
        $interval = ($pap['periodo'] === 'anual') ? 'year' : 'month';
        $amount   = StripeClient::toStripeAmount((float)$pap['precio'], $pap['moneda']);
        $price = $stripe->createRecurringPrice(
            $pap['stripe_product_id'],
            $amount,
            $pap['moneda'],
            $interval,
            [
                'geriapp_plan_addon_precio_id' => $papId,
                'geriapp_addon_id' => $pap['addon_id'],
                'geriapp_plan_id' => $pap['plan_id'],
            ],
            "geriapp_planaddonprice_$papId"
        );
        $db->prepare("UPDATE plan_addon_precios SET stripe_price_id = ? WHERE id = ?")
           ->execute([$price['id'], $papId]);
        $cnt['prices']++;
        $out("✓ Addon-paquete {$pap['plan_nombre']} · {$pap['addon_nombre']} {$pap['moneda']}/{$pap['periodo']} \${$pap['precio']} → {$price['id']}");
    } catch (\Throwable $e) {
        $cnt['errors']++;
        $out("✗ Addon-paquete-precio #$papId: " . $e->getMessage());
    }
}

$out('─────────────────────────────────────────');
$out("Resumen:");
$out("  Products creados:  {$cnt['products']}    (omitidos: {$cnt['skipped_products']})");
$out("  Prices creados:    {$cnt['prices']}    (omitidos: {$cnt['skipped_prices']})");
$out("  Errores:           {$cnt['errors']}");
$out('');
$out('Para resincronizar tras editar un precio: borra el `stripe_price_id` correspondiente desde');
$out('Superadmin → Planes → editar plan → tablas de precios, y vuelve a ejecutar este sync.');
