<?php
/**
 * GeriApp — Cron: Recálculo del cache `seats_uso`
 *
 * F6 — Mantiene seats_uso al día. Aunque los endpoints de crear/eliminar
 * residente y aceptar familiar ya llaman a Seats::recalcUsageForInst(), este
 * cron sirve como red de seguridad para:
 *   - Cambios masivos vía import.php (que aún no llama al recalc).
 *   - Inconsistencias por crashes a mitad de un INSERT.
 *   - Nuevas instituciones añadidas a una suscripción existente.
 *
 * Recomendado: cada 15 minutos
 *   *\/15 * * * * /usr/bin/php /path/to/v9/cron/recalc_seats.php >> /var/log/geriapp_seats.log 2>&1
 */

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !defined('CRON_ALLOW_HTTP')) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/models/Seats.php';

$db  = Database::getMaster();
$now = date('Y-m-d H:i:s');
echo "[{$now}] recalc_seats.php — inicio\n";

// Recorrer todas las parejas (suscripcion_id, institucion_id) activas
$pairs = $db->query(
    "SELECT si.suscripcion_id, si.institucion_id
     FROM suscripcion_instituciones si
     JOIN suscripciones s ON s.id = si.suscripcion_id
     WHERE s.estado IN ('trial','activa','past_due')"
)->fetchAll(PDO::FETCH_ASSOC);

$ok = 0; $err = 0;
foreach ($pairs as $p) {
    try {
        Seats::recalcUsage((int)$p['suscripcion_id'], (int)$p['institucion_id']);
        $ok++;
    } catch (\Throwable $e) {
        $err++;
        @error_log('[recalc_seats] inst=' . $p['institucion_id'] . ' err=' . $e->getMessage());
    }
}

// Limpiar filas obsoletas (suscripciones canceladas hace >30 días)
$db->exec(
    "DELETE su FROM seats_uso su
     LEFT JOIN suscripciones s ON s.id = su.suscripcion_id
     WHERE s.id IS NULL
        OR (s.estado = 'cancelada' AND s.updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY))"
);

$now2 = date('Y-m-d H:i:s');
echo "[{$now2}] recalc_seats.php — fin: {$ok} ok, {$err} err\n";
