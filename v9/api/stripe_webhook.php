<?php
/**
 * GeriApp — Webhook Stripe (público, verificación HMAC)
 *
 * URL pública a registrar en https://dashboard.stripe.com/test/webhooks :
 *   https://<tu-dominio>/v9/api/stripe_webhook.php
 *
 * Eventos suscritos recomendados:
 *   - checkout.session.completed         (alta inicial / asocia stripe_subscription_id)
 *   - customer.subscription.updated      (cambio de plan, trial → activa, cancel_at_period_end, past_due)
 *   - customer.subscription.deleted      (cancelación efectiva)
 *   - invoice.paid                       (renovación exitosa → estado=activa, periodo_fin)
 *   - invoice.payment_failed             (estado=past_due, dispara avisos)
 *
 * Idempotencia:
 *   - Insert primero en `pagos_historial` con UNIQUE en `stripe_event_id`.
 *   - Si MySQL devuelve duplicate-key, se responde 200 sin reprocesar.
 *
 * Seguridad:
 *   - Acepta SOLO POST.
 *   - Lee `php://input` crudo y verifica firma `Stripe-Signature` (HMAC-SHA256
 *     con tolerancia 5min anti-replay) usando `webhook_secret` de stripe.json.
 *   - Si la verificación falla → HTTP 400 sin filtrar detalles internos.
 *   - Cualquier excepción interna → log + HTTP 500 (Stripe reintentará).
 *
 * Política importante:
 *   - Esta integración cubre suscripciones DEL ADMIN PAGADOR (`suscripciones`)
 *     y suscripciones individuales DE FAMILIARES (`suscripciones_familiar`).
 *     Se distinguen por el metadata `geriapp_kind` que pusimos al crear el
 *     Checkout Session ("admin" | "familiar").
 *   - Los Capacitor (iOS/Android nativos) NO ven UI de pago — éste webhook
 *     siempre se invoca desde el backend de Stripe, no desde el cliente.
 */

declare(strict_types=1);

// No queremos warnings/notices contaminando la respuesta a Stripe.
@ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/conf/config.php';
require_once dirname(__DIR__) . '/db/Database.php';
require_once dirname(__DIR__) . '/db/models/StripeClient.php';
require_once dirname(__DIR__) . '/db/models/Mailer.php';
require_once dirname(__DIR__) . '/db/models/Configuracion.php';
require_once dirname(__DIR__) . '/db/models/BillingEmails.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === '' || $sigHeader === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_payload_or_signature']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Verificación de firma
// ─────────────────────────────────────────────────────────────────────────────
try {
    $stripe = new StripeClient();
    $event  = $stripe->verifyWebhook($payload, $sigHeader);
} catch (\Throwable $e) {
    @error_log('[stripe_webhook] firma inválida: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

$eventId   = (string)($event['id']   ?? '');
$eventType = (string)($event['type'] ?? '');
$obj       = $event['data']['object'] ?? [];

if ($eventId === '' || $eventType === '') {
    http_response_code(400);
    echo json_encode(['error' => 'malformed_event']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Idempotencia: insertar PRIMERO en pagos_historial con UNIQUE en stripe_event_id.
// Si ya existe, responder 200 sin reprocesar (Stripe reintenta hasta 3 días).
// ─────────────────────────────────────────────────────────────────────────────
$db = Database::getMaster();

try {
    $stmt = $db->prepare(
        "INSERT INTO pagos_historial
            (stripe_event_id, tipo, stripe_customer_id, stripe_invoice_id, monto, moneda, status, raw_payload)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $eventId,
        $eventType,
        $obj['customer']     ?? null,
        $obj['id'] ?? null,                           // invoice/session/subscription id genérico
        isset($obj['amount_total']) ? ((int)$obj['amount_total']) / 100 : (isset($obj['amount_paid']) ? ((int)$obj['amount_paid'])/100 : null),
        isset($obj['currency']) ? strtoupper((string)$obj['currency']) : null,
        $obj['status'] ?? null,
        $payload,
    ]);
    $historyId = (int)$db->lastInsertId();
} catch (\PDOException $e) {
    // 23000 = integrity constraint violation (duplicate event_id) → idempotente
    if ($e->getCode() === '23000') {
        http_response_code(200);
        echo json_encode(['ok' => true, 'duplicate' => true]);
        exit;
    }
    @error_log('[stripe_webhook] error insert pagos_historial: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Handlers por tipo de evento
// ─────────────────────────────────────────────────────────────────────────────
try {
    switch ($eventType) {
        case 'checkout.session.completed':
            handleCheckoutCompleted($db, $obj, $historyId);
            break;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handleSubscriptionUpdated($db, $obj, $historyId);
            break;

        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($db, $obj, $historyId);
            break;

        case 'invoice.paid':
        case 'invoice.payment_succeeded':
            handleInvoicePaid($db, $obj, $historyId);
            break;

        case 'invoice.payment_failed':
            handleInvoiceFailed($db, $obj, $historyId);
            break;

        default:
            // Evento no manejado: ya está en bitácora, respondemos 200 (Stripe lo da por procesado).
            break;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'event' => $eventType]);
} catch (\Throwable $e) {
    @error_log('[stripe_webhook] handler error (' . $eventType . '): ' . $e->getMessage());
    // 500 → Stripe reintentará. La fila en pagos_historial queda como evidencia.
    http_response_code(500);
    echo json_encode(['error' => 'handler_failed']);
}

// ═════════════════════════════════════════════════════════════════════════════
// Handlers (privados)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * checkout.session.completed
 *
 * Caso típico: usuario completó el Checkout. La sesión trae:
 *   - customer (Customer ID)
 *   - subscription (Subscription ID)  — solo si mode=subscription
 *   - metadata.geriapp_kind: "admin" | "familiar"
 *   - metadata.geriapp_user_id, geriapp_plan_id (admin) o geriapp_addon_id, geriapp_institucion_id (familiar)
 *
 * Acción: crear/actualizar fila en `suscripciones` o `suscripciones_familiar` y vincular IDs Stripe.
 */
function handleCheckoutCompleted(PDO $db, array $session, int $historyId): void
{
    $kind     = $session['metadata']['geriapp_kind'] ?? null;
    $custId   = $session['customer']     ?? null;
    $subId    = $session['subscription'] ?? null;

    if (!$custId || !$subId || !$kind) return;  // checkout no-suscripción o mal etiquetado

    if ($kind === 'admin') {
        $userId = (int)($session['metadata']['geriapp_user_id'] ?? 0);
        $planId = (int)($session['metadata']['geriapp_plan_id'] ?? 0);
        $moneda = strtoupper((string)($session['metadata']['geriapp_moneda'] ?? 'MXN'));
        $periodo= (string)($session['metadata']['geriapp_periodo'] ?? 'mensual');
        if ($userId <= 0 || $planId <= 0) return;

        // ¿Ya tenía suscripción? Actualizar; si no, crear.
        $existing = $db->prepare("SELECT id FROM suscripciones WHERE owner_user_id = ? LIMIT 1");
        $existing->execute([$userId]);
        $existingId = (int)$existing->fetchColumn();

        if ($existingId > 0) {
            $db->prepare(
                "UPDATE suscripciones
                 SET plan_id=?, moneda=?, periodo=?, stripe_customer_id=?, stripe_subscription_id=?, estado='incompleta'
                 WHERE id=?"
            )->execute([$planId, $moneda, $periodo, $custId, $subId, $existingId]);
            $suscId = $existingId;
        } else {
            $db->prepare(
                "INSERT INTO suscripciones (owner_user_id, plan_id, moneda, periodo, stripe_customer_id, stripe_subscription_id, estado)
                 VALUES (?, ?, ?, ?, ?, ?, 'incompleta')"
            )->execute([$userId, $planId, $moneda, $periodo, $custId, $subId]);
            $suscId = (int)$db->lastInsertId();
        }
        $instId = (int)($session['metadata']['geriapp_institucion_id'] ?? 0);
        if ($instId > 0) {
            $db->prepare(
                "INSERT IGNORE INTO suscripcion_instituciones (suscripcion_id, institucion_id)
                 VALUES (?, ?)"
            )->execute([$suscId, $instId]);
        }
        $db->prepare("UPDATE pagos_historial SET suscripcion_id=? WHERE id=?")->execute([$suscId, $historyId]);
        try { call_user_func(['BillingEmails', 'sendSubscriptionStarted'], $suscId, false); } catch (\Throwable $e) { @error_log('[webhook] mail subscription started: '.$e->getMessage()); }

    } elseif ($kind === 'familiar') {
        $userId   = (int)($session['metadata']['geriapp_user_id']      ?? 0);
        $instId   = (int)($session['metadata']['geriapp_institucion_id'] ?? 0);
        $addonId  = (int)($session['metadata']['geriapp_addon_id']     ?? 0);
        $moneda   = strtoupper((string)($session['metadata']['geriapp_moneda'] ?? 'MXN'));
        $periodo  = (string)($session['metadata']['geriapp_periodo'] ?? 'mensual');
        if ($userId <= 0) return;

        $existing = $db->prepare(
            "SELECT id FROM suscripciones_familiar WHERE usuario_familiar_id=? AND COALESCE(institucion_id,0)=? LIMIT 1"
        );
        $existing->execute([$userId, $instId]);
        $existingId = (int)$existing->fetchColumn();

        if ($existingId > 0) {
            $db->prepare(
                "UPDATE suscripciones_familiar
                 SET addon_id=?, moneda=?, periodo=?, stripe_customer_id=?, stripe_subscription_id=?, estado='incompleta'
                 WHERE id=?"
            )->execute([$addonId ?: null, $moneda, $periodo, $custId, $subId, $existingId]);
            $sfId = $existingId;
        } else {
            $db->prepare(
                "INSERT INTO suscripciones_familiar (usuario_familiar_id, institucion_id, moneda, periodo, stripe_customer_id, stripe_subscription_id, addon_id, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'incompleta')"
            )->execute([$userId, $instId ?: null, $moneda, $periodo, $custId, $subId, $addonId ?: null]);
            $sfId = (int)$db->lastInsertId();
        }
        $db->prepare("UPDATE pagos_historial SET suscripcion_familiar_id=? WHERE id=?")->execute([$sfId, $historyId]);
        try { call_user_func(['BillingEmails', 'sendSubscriptionStarted'], $sfId, true); } catch (\Throwable $e) { @error_log('[webhook] mail subscription started fam: '.$e->getMessage()); }
    }
}

/**
 * customer.subscription.created / customer.subscription.updated
 *
 * Refleja en BD: estado, trial_ends_at, periodo_inicio/fin, cancel_at_period_end.
 * Mapea estado Stripe → enum local:
 *   trialing   → trial
 *   active     → activa
 *   past_due   → past_due
 *   unpaid     → past_due
 *   canceled   → cancelada
 *   incomplete → incompleta
 *   incomplete_expired → cancelada
 *   paused     → pausada
 */
function handleSubscriptionUpdated(PDO $db, array $sub, int $historyId): void
{
    $subId = $sub['id'] ?? null;
    if (!$subId) return;

    $estado = mapStripeStatus($sub['status'] ?? '');
    $trialEnds = !empty($sub['trial_end'])         ? date('Y-m-d H:i:s', (int)$sub['trial_end'])         : null;
    $periodIni = !empty($sub['current_period_start'])? date('Y-m-d H:i:s', (int)$sub['current_period_start']) : null;
    $periodFin = !empty($sub['current_period_end'])  ? date('Y-m-d H:i:s', (int)$sub['current_period_end'])   : null;
    $cancelEnd = !empty($sub['cancel_at_period_end']) ? 1 : 0;

    // ¿Es admin o familiar? Buscar primero en suscripciones; si no, en suscripciones_familiar.
    $row = $db->prepare("SELECT id FROM suscripciones WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sId = (int)$row->fetchColumn();

    if ($sId > 0) {
        $db->prepare(
            "UPDATE suscripciones
             SET estado=?, trial_ends_at=?, periodo_inicio=?, periodo_fin=?, cancel_at_period_end=?
             WHERE id=?"
        )->execute([$estado, $trialEnds, $periodIni, $periodFin, $cancelEnd, $sId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_id=? WHERE id=?")->execute([$sId, $historyId]);

        // Sincronizar estado de instituciones cubiertas.
        applyEstadoToInstituciones($db, $sId, $estado);
        return;
    }

    $row = $db->prepare("SELECT id FROM suscripciones_familiar WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sfId = (int)$row->fetchColumn();
    if ($sfId > 0) {
        // El enum de familiar no incluye 'trial' — mapear.
        $estadoFam = ($estado === 'trial') ? 'incompleta' : $estado;
        $db->prepare(
            "UPDATE suscripciones_familiar
             SET estado=?, periodo_inicio=?, periodo_fin=?, cancel_at_period_end=?
             WHERE id=?"
        )->execute([$estadoFam, $periodIni, $periodFin, $cancelEnd, $sfId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_familiar_id=? WHERE id=?")->execute([$sfId, $historyId]);
    }
}

/**
 * customer.subscription.deleted — la suscripción se cerró definitivamente.
 * Marcamos como 'cancelada' y suspendemos las instituciones del admin (gracia futura → F8).
 */
function handleSubscriptionDeleted(PDO $db, array $sub, int $historyId): void
{
    $subId = $sub['id'] ?? null;
    if (!$subId) return;

    $row = $db->prepare("SELECT id FROM suscripciones WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sId = (int)$row->fetchColumn();
    if ($sId > 0) {
        $db->prepare("UPDATE suscripciones SET estado='cancelada', cancel_at_period_end=1 WHERE id=?")->execute([$sId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_id=? WHERE id=?")->execute([$sId, $historyId]);
        applyEstadoToInstituciones($db, $sId, 'cancelada');
        try { BillingEmails::sendCanceled($sId, false); } catch (\Throwable $e) { @error_log('[webhook] mail canceled: '.$e->getMessage()); }
        return;
    }

    $row = $db->prepare("SELECT id FROM suscripciones_familiar WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sfId = (int)$row->fetchColumn();
    if ($sfId > 0) {
        $db->prepare("UPDATE suscripciones_familiar SET estado='cancelada' WHERE id=?")->execute([$sfId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_familiar_id=? WHERE id=?")->execute([$sfId, $historyId]);
        try { BillingEmails::sendCanceled($sfId, true); } catch (\Throwable $e) { @error_log('[webhook] mail canceled fam: '.$e->getMessage()); }
    }
}

/**
 * invoice.paid — renovación exitosa o pago inicial.
 * Pone estado=activa y avanza periodo_fin (Stripe ya lo manda en subscription.updated, pero
 * por si acaso aquí también extraemos period_end de la primera línea).
 */
function handleInvoicePaid(PDO $db, array $invoice, int $historyId): void
{
    $subId = $invoice['subscription'] ?? null;
    if (!$subId) return;

    $row = $db->prepare("SELECT id FROM suscripciones WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sId = (int)$row->fetchColumn();
    if ($sId > 0) {
        $db->prepare("UPDATE suscripciones SET estado='activa' WHERE id=? AND estado IN ('trial','past_due','incompleta')")
           ->execute([$sId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_id=? WHERE id=?")->execute([$sId, $historyId]);
        applyEstadoToInstituciones($db, $sId, 'activa');
        return;
    }

    $row = $db->prepare("SELECT id FROM suscripciones_familiar WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sfId = (int)$row->fetchColumn();
    if ($sfId > 0) {
        $db->prepare("UPDATE suscripciones_familiar SET estado='activa' WHERE id=? AND estado IN ('past_due','incompleta')")
           ->execute([$sfId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_familiar_id=? WHERE id=?")->execute([$sfId, $historyId]);
    }
}

/**
 * invoice.payment_failed — pasa a past_due (la institución sigue activa por gracia hasta F8).
 */
function handleInvoiceFailed(PDO $db, array $invoice, int $historyId): void
{
    $subId = $invoice['subscription'] ?? null;
    if (!$subId) return;

    $row = $db->prepare("SELECT id FROM suscripciones WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sId = (int)$row->fetchColumn();
    if ($sId > 0) {
        $db->prepare("UPDATE suscripciones SET estado='past_due' WHERE id=?")->execute([$sId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_id=? WHERE id=?")->execute([$sId, $historyId]);
        applyEstadoToInstituciones($db, $sId, 'past_due');
        try { BillingEmails::sendPaymentFailed($sId, false); } catch (\Throwable $e) { @error_log('[webhook] mail pay fail: '.$e->getMessage()); }
        return;
    }

    $row = $db->prepare("SELECT id FROM suscripciones_familiar WHERE stripe_subscription_id = ? LIMIT 1");
    $row->execute([$subId]);
    $sfId = (int)$row->fetchColumn();
    if ($sfId > 0) {
        $db->prepare("UPDATE suscripciones_familiar SET estado='past_due' WHERE id=?")->execute([$sfId]);
        $db->prepare("UPDATE pagos_historial SET suscripcion_familiar_id=? WHERE id=?")->execute([$sfId, $historyId]);
        try { BillingEmails::sendPaymentFailed($sfId, true); } catch (\Throwable $e) { @error_log('[webhook] mail pay fail fam: '.$e->getMessage()); }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// Helpers internos
// ═════════════════════════════════════════════════════════════════════════════

function mapStripeStatus(string $stripeStatus): string
{
    return match ($stripeStatus) {
        'trialing'           => 'trial',
        'active'             => 'activa',
        'past_due'           => 'past_due',
        'unpaid'             => 'past_due',
        'canceled'           => 'cancelada',
        'incomplete'         => 'incompleta',
        'incomplete_expired' => 'cancelada',
        'paused'             => 'pausada',
        default              => 'incompleta',
    };
}

/**
 * Aplica el estado de la suscripción a TODAS las instituciones cubiertas.
 *  trial / activa  → estado='activa' (o 'trial' si el plan venía en trial)
 *  past_due        → no cambiamos aún (gracia se maneja en F8 con cron)
 *  cancelada       → estado='suspendida'
 */
function applyEstadoToInstituciones(PDO $db, int $suscripcionId, string $estado): void
{
    $instIds = $db->prepare("SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id = ?");
    $instIds->execute([$suscripcionId]);
    $ids = $instIds->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;

    if ($estado === 'activa') {
        $in = implode(',', array_map('intval', $ids));
        // Solo cambiar si está en trial/past_due/suspendida (no tocar archivada).
        $db->exec("UPDATE instituciones SET estado='activa' WHERE id IN ($in) AND estado IN ('trial','past_due','suspendida')");
    } elseif ($estado === 'past_due') {
        $in = implode(',', array_map('intval', $ids));
        $db->exec("UPDATE instituciones SET estado='past_due' WHERE id IN ($in) AND estado IN ('activa','trial')");
    } elseif ($estado === 'cancelada') {
        $in = implode(',', array_map('intval', $ids));
        $db->exec("UPDATE instituciones SET estado='suspendida' WHERE id IN ($in) AND estado <> 'archivada'");
    }
    // 'trial' inicial se maneja al crear la institución (register.php F1).
}
