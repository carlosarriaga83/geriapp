<?php
/**
 * GeriApp — Correos transaccionales de billing (F10)
 *
 * Métodos estáticos que envían correos al owner de una suscripción usando el
 * SMTP configurado en la primera institución cubierta por la suscripción
 * (Mailer::fromConfig($instId)). Si no hay institución (caso suscripciones_familiar
 * sin institucion_id), se omite silenciosamente.
 *
 * Idempotencia: cada correo escribe una marca en `suscripciones.metadata` o
 * `suscripciones_familiar.metadata`/no aplica, con la fecha de último envío
 * (formato Y-m-d) bajo la clave `mail_<tipo>_at`. El método respeta esa marca
 * y no reenvía el mismo día/ciclo.
 *
 * Tipos:
 *   - subscription_started (webhook checkout.session.completed)
 *   - trial_ends_soon  (cron, 3 días antes de trial_ends_at)
 *   - payment_failed   (webhook invoice.payment_failed)
 *   - canceled         (webhook subscription.deleted + cron F8)
 */
class BillingEmails
{
    /**
     * Envía confirmación de suscripción creada tras completar Checkout.
     * key idempotencia: mail_subscription_started_at (una sola vez).
     */
    public static function sendSubscriptionStarted(int $suscripcionId, bool $esFamiliar = false): bool
    {
        $info = self::getOwnerInfo($suscripcionId, $esFamiliar);
        if (!$info) return false;
        if (!self::shouldSend($suscripcionId, $esFamiliar, 'mail_subscription_started_at', '1')) {
            return false;
        }

        $subject = "Tu suscripción de " . APP_NAME . " está lista";
        $trialLine = !$esFamiliar && !empty($info['trial_ends_at'])
            ? " Tu periodo de prueba queda activo hasta el <strong>" . self::fmtFecha($info['trial_ends_at']) . "</strong>."
            : '';
        $body = self::renderTemplate(
            $info['nombre'],
            "Tu suscripción se registró correctamente.",
            "Ya puedes operar tu institución en GeriApp con el paquete contratado." . $trialLine,
            'Ir a mi suscripción',
            $info['billing_url']
        );
        $ok = self::sendMail($info, $subject, $body);
        if ($ok) self::markSent($suscripcionId, $esFamiliar, 'mail_subscription_started_at', '1');
        return $ok;
    }

    /**
     * Envía correo "Tu prueba termina en 3 días".
     * key idempotencia: mail_trial_ends_soon_at = YYYY-MM-DD.
     */
    public static function sendTrialEndsSoon(int $suscripcionId, int $diasRestantes = 3): bool
    {
        $info = self::getOwnerInfo($suscripcionId, false);
        if (!$info) return false;
        if (!self::shouldSend($suscripcionId, false, 'mail_trial_ends_soon_at', date('Y-m-d'))) {
            return false;
        }

        $subject = "Tu prueba de " . APP_NAME . " termina en {$diasRestantes} día" . ($diasRestantes>1?'s':'');
        $body = self::renderTemplate(
            $info['nombre'],
            "Tu periodo de prueba termina en <strong>{$diasRestantes} día" . ($diasRestantes>1?'s':'') . "</strong>.",
            "Para no perder acceso a tus residentes, agrega un método de pago antes del " .
                self::fmtFecha($info['trial_ends_at']) . ".",
            'Agregar método de pago',
            $info['billing_url']
        );
        $ok = self::sendMail($info, $subject, $body);
        if ($ok) self::markSent($suscripcionId, false, 'mail_trial_ends_soon_at', date('Y-m-d'));
        return $ok;
    }

    /**
     * Envía correo "Pago fallido".
     * key idempotencia: mail_payment_failed_at = YYYY-MM-DD (uno por día).
     */
    public static function sendPaymentFailed(int $suscripcionId, bool $esFamiliar = false): bool
    {
        $info = self::getOwnerInfo($suscripcionId, $esFamiliar);
        if (!$info) return false;
        if (!self::shouldSend($suscripcionId, $esFamiliar, 'mail_payment_failed_at', date('Y-m-d'))) {
            return false;
        }

        $diasGracia = defined('BILLING_PASTDUE_GRACE_DAYS') ? (int)BILLING_PASTDUE_GRACE_DAYS : 7;
        if ($esFamiliar) $diasGracia = max(1, (int)floor($diasGracia / 2));

        $subject = "No pudimos procesar tu pago — " . APP_NAME;
        $body = self::renderTemplate(
            $info['nombre'],
            "No pudimos procesar tu último pago de la suscripción.",
            "Tienes <strong>{$diasGracia} día" . ($diasGracia>1?'s':'') .
                "</strong> para actualizar tu método de pago. Si no se completa el cobro en ese plazo, " .
                ($esFamiliar ? "tu acceso familiar" : "tus instituciones") . " quedarán suspendidas.",
            'Actualizar método de pago',
            $info['billing_url']
        );
        $ok = self::sendMail($info, $subject, $body);
        if ($ok) self::markSent($suscripcionId, $esFamiliar, 'mail_payment_failed_at', date('Y-m-d'));
        return $ok;
    }

    /**
     * Envía correo "Suscripción cancelada".
     * key idempotencia: mail_canceled_at (una sola vez por suscripción).
     */
    public static function sendCanceled(int $suscripcionId, bool $esFamiliar = false): bool
    {
        $info = self::getOwnerInfo($suscripcionId, $esFamiliar);
        if (!$info) return false;
        if (!self::shouldSend($suscripcionId, $esFamiliar, 'mail_canceled_at', '1')) {
            return false; // ya enviado alguna vez
        }

        $subject = "Tu suscripción ha sido cancelada — " . APP_NAME;
        $body = self::renderTemplate(
            $info['nombre'],
            "Tu suscripción ha sido cancelada.",
            ($esFamiliar
                ? "Tu acceso familiar ha quedado inactivo. Puedes contratar un nuevo asiento cuando lo necesites."
                : "Tus instituciones quedaron en estado suspendido. Tus datos se conservan; puedes reactivar contratando un nuevo plan."),
            'Reactivar suscripción',
            $info['billing_url']
        );
        $ok = self::sendMail($info, $subject, $body);
        if ($ok) self::markSent($suscripcionId, $esFamiliar, 'mail_canceled_at', '1');
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internos
    // ─────────────────────────────────────────────────────────────────────────

    /** Devuelve datos del owner + institución para SMTP. null si no se puede. */
    private static function getOwnerInfo(int $suscripcionId, bool $esFamiliar): ?array
    {
        $db = Database::getMaster();

        if ($esFamiliar) {
            $sql = "SELECT sf.id, sf.usuario_familiar_id AS user_id, sf.institucion_id,
                           sf.estado, NULL AS trial_ends_at,
                           u.nombre, u.email
                      FROM suscripciones_familiar sf
                      JOIN usuarios u ON u.id = sf.usuario_familiar_id
                     WHERE sf.id = ?";
        } else {
            $sql = "SELECT s.id, s.owner_user_id AS user_id,
                           (SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id = s.id LIMIT 1) AS institucion_id,
                           s.estado, s.trial_ends_at,
                           u.nombre, u.email
                      FROM suscripciones s
                      JOIN usuarios u ON u.id = s.owner_user_id
                     WHERE s.id = ?";
        }
        $st = $db->prepare($sql);
        $st->execute([$suscripcionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['email']) || empty($row['institucion_id'])) return null;

        $row['billing_url'] = (function_exists('app_public_url') ? app_public_url() : (BASE_URL ?: '')) . '/billing.php';
        return $row;
    }

    private static function sendMail(array $info, string $subject, string $bodyHtml): bool
    {
        try {
            $mailer = Mailer::fromConfig((int)$info['institucion_id']);
            $r = $mailer->send($info['email'], $subject, $bodyHtml);
            return !empty($r['ok']);
        } catch (\Throwable $e) {
            @error_log('[BillingEmails] send fail sub=' . $info['id'] . ': ' . $e->getMessage());
            return false;
        }
    }

    /** ¿Debemos enviar? Lee metadata.<key> y compara con $valorEsperado. */
    private static function shouldSend(int $subId, bool $esFamiliar, string $key, string $valorEsperado): bool
    {
        $tabla = $esFamiliar ? 'suscripciones_familiar' : 'suscripciones';
        $db = Database::getMaster();
        $st = $db->prepare("SELECT metadata FROM {$tabla} WHERE id=?");
        $st->execute([$subId]);
        $meta = $st->fetchColumn();
        if (!$meta) return true;
        $arr = json_decode((string)$meta, true);
        if (!is_array($arr)) return true;
        return (string)($arr[$key] ?? '') !== $valorEsperado;
    }

    private static function markSent(int $subId, bool $esFamiliar, string $key, string $valor): void
    {
        $tabla = $esFamiliar ? 'suscripciones_familiar' : 'suscripciones';
        $db = Database::getMaster();
        $st = $db->prepare("SELECT metadata FROM {$tabla} WHERE id=?");
        $st->execute([$subId]);
        $meta = $st->fetchColumn();
        $arr = is_string($meta) ? (json_decode($meta, true) ?: []) : [];
        $arr[$key] = $valor;
        $upd = $db->prepare("UPDATE {$tabla} SET metadata=? WHERE id=?");
        $upd->execute([json_encode($arr, JSON_UNESCAPED_UNICODE), $subId]);
    }

    private static function fmtFecha(?string $datetime): string
    {
        if (!$datetime) return '';
        $ts = strtotime($datetime);
        if (!$ts) return '';
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)-1] . ' de ' . date('Y', $ts);
    }

    /** Plantilla HTML simple (responsive, marca GeriApp). */
    private static function renderTemplate(string $nombre, string $titulo, string $cuerpo, string $ctaTexto, string $ctaUrl): string
    {
        $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
        $nombre  = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $titulo  = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        // $cuerpo puede contener <strong> ya escapado adecuadamente por el caller.
        $ctaTexto= htmlspecialchars($ctaTexto, ENT_QUOTES, 'UTF-8');
        $ctaUrl  = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 16px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
        <tr><td style="padding:24px 32px;background:#635bff;color:#ffffff;font-size:20px;font-weight:600;">{$appName}</td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 16px;font-size:16px;">Hola {$nombre},</p>
          <p style="margin:0 0 16px;font-size:16px;font-weight:600;">{$titulo}</p>
          <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">{$cuerpo}</p>
          <table cellpadding="0" cellspacing="0"><tr><td style="background:#635bff;border-radius:8px;">
            <a href="{$ctaUrl}" style="display:inline-block;padding:12px 28px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">{$ctaTexto}</a>
          </td></tr></table>
          <p style="margin:32px 0 0;font-size:12px;color:#9ca3af;">Si tienes preguntas, responde a este correo y te ayudamos.</p>
        </td></tr>
        <tr><td style="padding:16px 32px;background:#f9fafb;font-size:11px;color:#9ca3af;text-align:center;">
          © {$appName} — Este es un correo automático sobre tu suscripción.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }
}
