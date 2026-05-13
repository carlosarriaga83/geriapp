<?php
/**
 * GeriApp — Cron: Estados de suscripción y periodo de gracia (F8)
 *
 * Maneja transiciones automáticas de estado:
 *
 *   1) trial expirado sin método de pago
 *      - Si trial_ends_at < NOW y estado = 'trial' y no hay stripe_subscription_id:
 *        suscripcion → 'incompleta', instituciones → 'past_due' (gracia corta).
 *
 *   2) past_due → cancelada (GRACIA_DIAS días)
 *      - Si estado = 'past_due' y updated_at < NOW - GRACIA_DIAS:
 *        suscripcion → 'cancelada', instituciones → 'suspendida'.
 *
 *   3) Avisos al admin a 3, 1 días antes del corte (in-app vía notificaciones_sistema).
 *
 * Configuración:
 *   - Define BILLING_PASTDUE_GRACE_DAYS en conf/config.php para sobrescribir (default 7).
 *
 * Ejecución:
 *   - Diaria a las 03:00 (recomendado):
 *     0 3 * * * /usr/bin/php /path/to/v9/cron/billing_estados.php >> /var/log/geriapp_billing.log 2>&1
 *
 * Idempotencia:
 *   - Las transiciones usan condiciones WHERE estrictas; correr el cron 2 veces
 *     en el mismo día no duplica acciones.
 *   - Las notificaciones in-app revisan si ya existe una con el mismo título/dia
 *     (evita spam).
 */

declare(strict_types=1);

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !defined('CRON_ALLOW_HTTP')) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/models/Mailer.php';
require_once __DIR__ . '/../db/models/Configuracion.php';
require_once __DIR__ . '/../db/models/BillingEmails.php';

$GRACE_DAYS = defined('BILLING_PASTDUE_GRACE_DAYS') ? max(1, (int)BILLING_PASTDUE_GRACE_DAYS) : 7;
$AVISOS_EN  = [3, 1];   // días previos al corte para crear notificación in-app

$db  = Database::getMaster();
$now = date('Y-m-d H:i:s');
echo "[{$now}] billing_estados.php — inicio (gracia={$GRACE_DAYS}d)\n";

$counters = ['trial_expirado'=>0, 'past_due_cortado'=>0, 'avisos'=>0, 'fam_cortado'=>0, 'mail_trial_soon'=>0, 'mail_canceled'=>0];

// ────────────────────────────────────────────────────────────────────────
// 0) Aviso por email "trial termina en N días" (3 días antes)
// ────────────────────────────────────────────────────────────────────────
foreach ([3] as $diasPrevios) {
    $stmt = $db->prepare(
        "SELECT id FROM suscripciones
         WHERE estado = 'trial'
           AND trial_ends_at IS NOT NULL
           AND DATE(trial_ends_at) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))"
    );
    $stmt->execute([$diasPrevios]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sId) {
        try {
            if (BillingEmails::sendTrialEndsSoon((int)$sId, $diasPrevios)) $counters['mail_trial_soon']++;
        } catch (\Throwable $e) {
            @error_log('[billing_estados] mail trial soon fail: '.$e->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1) trial expirado sin método de pago → incompleta + past_due en instituciones
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id FROM suscripciones
     WHERE estado = 'trial'
       AND trial_ends_at IS NOT NULL
       AND trial_ends_at < NOW()
       AND (stripe_subscription_id IS NULL OR stripe_subscription_id = '')"
);
$stmt->execute();
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $sId) {
    $sId = (int)$sId;
    $db->prepare("UPDATE suscripciones SET estado='incompleta' WHERE id=? AND estado='trial'")->execute([$sId]);
    aplicarInstituciones($db, $sId, 'past_due');
    crearAvisoInst($db, $sId, 'Tu prueba terminó',
        'Tu periodo de prueba ha terminado. Agrega un método de pago en Suscripción y facturación para no perder acceso a tus datos.', 'alerta');
    $counters['trial_expirado']++;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) past_due con gracia agotada → cancelada + suspendida
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id FROM suscripciones
     WHERE estado = 'past_due'
       AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$stmt->execute([$GRACE_DAYS]);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $sId) {
    $sId = (int)$sId;
    $db->prepare("UPDATE suscripciones SET estado='cancelada', cancel_at_period_end=1 WHERE id=? AND estado='past_due'")
       ->execute([$sId]);
    aplicarInstituciones($db, $sId, 'cancelada');
    crearAvisoInst($db, $sId, 'Suscripción cancelada por falta de pago',
        "Tu suscripción fue cancelada después de {$GRACE_DAYS} días sin pago. Tus datos quedan en archivo. Para reactivar, contrata un nuevo plan.", 'alerta');
    try {
        if (BillingEmails::sendCanceled($sId, false)) $counters['mail_canceled']++;
    } catch (\Throwable $e) {
        @error_log('[billing_estados] mail canceled fail: '.$e->getMessage());
    }
    $counters['past_due_cortado']++;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) Avisos a 3 y 1 días antes del corte para suscripciones aún en past_due
// ─────────────────────────────────────────────────────────────────────────────
foreach ($AVISOS_EN as $diasPrevios) {
    $diasYaPasados = $GRACE_DAYS - $diasPrevios;       // ej. gracia=7, aviso a 3 días → 4 días en past_due
    if ($diasYaPasados < 0) continue;
    $stmt = $db->prepare(
        "SELECT id FROM suscripciones
         WHERE estado = 'past_due'
           AND DATE(updated_at) = DATE(DATE_SUB(NOW(), INTERVAL ? DAY))"
    );
    $stmt->execute([$diasYaPasados]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $sId) {
        $sId = (int)$sId;
        $titulo = "Tu suscripción se cancelará en {$diasPrevios} día" . ($diasPrevios>1?'s':'');
        $msg = "Detectamos un pago fallido. Si no actualizas tu método de pago, perderás acceso en {$diasPrevios} día" .
               ($diasPrevios>1?'s':'') .
               ". Ve a Suscripción y facturación → Gestionar pago / facturas.";
        if (crearAvisoInst($db, $sId, $titulo, $msg, 'alerta', /*soloUnoPorDia*/true)) {
            $counters['avisos']++;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4) Suscripciones de FAMILIAR — gracia más corta (3 días) y no afecta instituciones
// ─────────────────────────────────────────────────────────────────────────────
$famGrace = max(1, (int)floor($GRACE_DAYS / 2));
$stmt = $db->prepare(
    "SELECT id FROM suscripciones_familiar
     WHERE estado = 'past_due'
       AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$stmt->execute([$famGrace]);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $sfId) {
    $db->prepare("UPDATE suscripciones_familiar SET estado='cancelada', cancel_at_period_end=1
                  WHERE id=? AND estado='past_due'")->execute([(int)$sfId]);
    try { BillingEmails::sendCanceled((int)$sfId, true); } catch (\Throwable $e) {}
    $counters['fam_cortado']++;
}

$now2 = date('Y-m-d H:i:s');
echo "[{$now2}] billing_estados.php — fin: " . json_encode($counters) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// Helpers
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Aplica un nuevo estado a todas las instituciones cubiertas por la suscripción.
 * Reglas:
 *   activa     → instituciones IN (trial,past_due,suspendida) → activa
 *   past_due   → instituciones IN (activa,trial)              → past_due
 *   cancelada  → instituciones                                → suspendida (excepto archivada)
 */
function aplicarInstituciones(PDO $db, int $suscripcionId, string $nuevoEstado): void
{
    $st = $db->prepare("SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id=?");
    $st->execute([$suscripcionId]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;
    $in = implode(',', array_map('intval', $ids));
    if ($nuevoEstado === 'activa') {
        $db->exec("UPDATE instituciones SET estado='activa' WHERE id IN ($in) AND estado IN ('trial','past_due','suspendida')");
    } elseif ($nuevoEstado === 'past_due') {
        $db->exec("UPDATE instituciones SET estado='past_due' WHERE id IN ($in) AND estado IN ('activa','trial')");
    } elseif ($nuevoEstado === 'cancelada') {
        $db->exec("UPDATE instituciones SET estado='suspendida' WHERE id IN ($in) AND estado <> 'archivada'");
    }
}

/**
 * Crea una notificación in-app para los admins de las instituciones cubiertas.
 * - tabla `notificaciones_sistema` (scope tenant; misma BD master por ahora).
 * - Si $soloUnoPorDia, omite si ya existe una con el mismo título HOY.
 * - Devuelve true si insertó al menos una.
 */
function crearAvisoInst(PDO $db, int $suscripcionId, string $titulo, string $mensaje, string $tipo = 'alerta', bool $soloUnoPorDia = false): bool
{
    // Buscar instituciones de la suscripción + el owner_user_id como creado_por.
    $info = $db->prepare(
        "SELECT s.owner_user_id, si.institucion_id
         FROM suscripciones s
         JOIN suscripcion_instituciones si ON si.suscripcion_id = s.id
         WHERE s.id = ?"
    );
    $info->execute([$suscripcionId]);
    $rows = $info->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return false;

    $rolesDestino = json_encode(['admin','superadmin']);
    $insertados = 0;
    foreach ($rows as $r) {
        $instId = (int)$r['institucion_id'];
        $owner  = (int)$r['owner_user_id'];

        if ($soloUnoPorDia) {
            $chk = $db->prepare(
                "SELECT 1 FROM notificaciones_sistema
                 WHERE institucion_id=? AND titulo=? AND DATE(creado_at)=CURDATE() LIMIT 1"
            );
            $chk->execute([$instId, $titulo]);
            if ($chk->fetchColumn()) continue;
        }

        try {
            $db->prepare(
                "INSERT INTO notificaciones_sistema
                    (institucion_id, titulo, mensaje, tipo, creado_por, roles_destino, activo)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            )->execute([$instId, $titulo, $mensaje, $tipo, $owner, $rolesDestino]);
            $insertados++;
        } catch (\Throwable $e) {
            @error_log('[billing_estados] aviso insert fail: ' . $e->getMessage());
        }
    }
    return $insertados > 0;
}
