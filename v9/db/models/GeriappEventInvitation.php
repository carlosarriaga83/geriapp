<?php
/**
 * Invitaciones globales GeriApp para eventos masivos.
 * No se vinculan a una institución existente; al registrarse crean una nueva.
 */
require_once __DIR__ . '/../Database.php';

class GeriappEventInvitation
{
    private static bool $eventTableReady = false;

    public static function create(int $planId, int $createdBy, int $days = 30, ?string $eventName = null): array|false
    {
        $db = Database::getMaster();
        $token = bin2hex(random_bytes(32));
        $days = max(1, min(365, $days));
        $eventName = trim((string)$eventName) ?: null;

        $stmt = $db->prepare(
            "INSERT INTO invitaciones_geriapp
                (token, plan_id, nombre_evento, estado, expires_at, creado_por)
             VALUES
                (?, ?, ?, 'activa', DATE_ADD(NOW(), INTERVAL ? DAY), ?)"
        );
        $ok = $stmt->execute([$token, $planId, $eventName, $days, $createdBy ?: null]);
        if (!$ok) return false;
        return ['id' => (int)$db->lastInsertId(), 'token' => $token];
    }

    public static function getByToken(string $token): array|false
    {
        $db = Database::getMaster();
        $stmt = $db->prepare(
            "SELECT i.*, p.nombre AS plan_nombre, p.trial_dias, p.solicita_tarjeta_registro, p.activo AS plan_activo
             FROM invitaciones_geriapp i
             JOIN planes p ON p.id = i.plan_id
             WHERE i.token = ?
             LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function markUsed(string $token, ?int $usuarioId = null, ?int $institucionId = null, array $meta = []): bool
    {
        $db = Database::getMaster();
        $stmt = $db->prepare(
            "UPDATE invitaciones_geriapp
             SET usos_count = usos_count + 1,
                 last_used_at = NOW()
             WHERE token = ? AND estado = 'activa' AND expires_at >= NOW()"
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            self::recordEventByToken($token, 'registro', $usuarioId, $institucionId, $meta);
        }
        return $ok;
    }

    public static function recordScan(string $token, array $meta = []): void
    {
        self::recordEventByToken($token, 'scan', null, null, $meta);
    }

    public static function ensureEventTable(?PDO $db = null): void
    {
        if (self::$eventTableReady) return;
        $db = $db ?: Database::getMaster();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS invitaciones_geriapp_eventos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                invitacion_id INT UNSIGNED NOT NULL,
                tipo ENUM('scan','registro') NOT NULL,
                usuario_id INT UNSIGNED DEFAULT NULL,
                institucion_id INT UNSIGNED DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                meta JSON DEFAULT NULL,
                creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ige_inv_fecha (invitacion_id, creado_at),
                KEY idx_ige_tipo_fecha (tipo, creado_at),
                KEY idx_ige_usuario (usuario_id),
                KEY idx_ige_institucion (institucion_id),
                CONSTRAINT fk_ige_inv FOREIGN KEY (invitacion_id)
                    REFERENCES invitaciones_geriapp(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$eventTableReady = true;
    }

    private static function recordEventByToken(string $token, string $tipo, ?int $usuarioId, ?int $institucionId, array $meta = []): void
    {
        try {
            if (!in_array($tipo, ['scan', 'registro'], true)) return;
            $db = Database::getMaster();
            self::ensureEventTable($db);

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            if ($ua === '') $ua = null;
            $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            $stmt = $db->prepare(
                "INSERT INTO invitaciones_geriapp_eventos
                    (invitacion_id, tipo, usuario_id, institucion_id, ip, user_agent, meta)
                 SELECT id, ?, ?, ?, ?, ?, ?
                 FROM invitaciones_geriapp
                 WHERE token = ?
                 LIMIT 1"
            );
            $stmt->execute([
                $tipo,
                $usuarioId && $usuarioId > 0 ? $usuarioId : null,
                $institucionId && $institucionId > 0 ? $institucionId : null,
                $ip,
                $ua,
                $metaJson,
                $token,
            ]);
        } catch (\Throwable $e) {
            error_log('[GeriApp] event QR audit skipped: ' . $e->getMessage());
        }
    }

    public static function revoke(int $id): bool
    {
        $db = Database::getMaster();
        $stmt = $db->prepare("UPDATE invitaciones_geriapp SET estado = 'revocada' WHERE id = ? AND estado = 'activa'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
