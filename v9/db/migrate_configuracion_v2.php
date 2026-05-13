<?php
/**
 * GeriApp — Migración configuración v2
 * Agrega columnas de turnos, seguridad y roles_permisos a la tabla configuracion.
 * Ejecutar UNA vez: http://localhost/geriapp/v2/db/migrate_configuracion_v2.php
 */

$allowedIPs = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs, true)) {
    http_response_code(403);
    die('403 — Solo desde localhost.');
}

require_once dirname(__DIR__) . '/conf/config.db.php';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$migrations = [
    // ── SMTP extra ─────────────────────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS smtp_timeout       SMALLINT UNSIGNED DEFAULT 20 COMMENT 'Tiempo de espera conexión SMTP (segundos)'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS smtp_sandbox       TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Modo prueba SMTP — no despacha correos'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS wa_sandbox         TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Modo prueba WhatsApp — no envía mensajes'",
    // ── Turnos ────────────────────────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_mat_inicio    VARCHAR(5)  DEFAULT '07:00'  COMMENT 'Hora inicio turno matutino'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_mat_fin       VARCHAR(5)  DEFAULT '15:00'  COMMENT 'Hora fin turno matutino'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_mat_siglas    VARCHAR(4)  DEFAULT 'TM'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_ves_inicio    VARCHAR(5)  DEFAULT '15:00'  COMMENT 'Hora inicio turno vespertino'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_ves_fin       VARCHAR(5)  DEFAULT '23:00'  COMMENT 'Hora fin turno vespertino'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_ves_siglas    VARCHAR(4)  DEFAULT 'TV'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_noc_inicio    VARCHAR(5)  DEFAULT '23:00'  COMMENT 'Hora inicio turno nocturno'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_noc_fin       VARCHAR(5)  DEFAULT '07:00'  COMMENT 'Hora fin turno nocturno'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS turno_noc_siglas    VARCHAR(4)  DEFAULT 'TN'",
    // ── Seguridad ──────────────────────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_pass_min_len   TINYINT UNSIGNED DEFAULT 8",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_pass_expira_dias SMALLINT UNSIGNED DEFAULT 90",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_2fa            TINYINT(1) DEFAULT 0",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_timeout_sesion TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_una_sesion     TINYINT(1) DEFAULT 0",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_log_accesos    TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_max_intentos   TINYINT UNSIGNED DEFAULT 5",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS seg_bloqueo_min    SMALLINT UNSIGNED DEFAULT 15",
    // ── Sistema (backup automático) ────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS backup_frecuencia  VARCHAR(20) DEFAULT 'diario'",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS backup_hora        VARCHAR(5)  DEFAULT '02:00'",
    // ── Notificaciones extra ───────────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_vitales      TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_meds         TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_caida        TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_condicion    TINYINT(1) DEFAULT 0",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_canal_sistema TINYINT(1) DEFAULT 1",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_canal_email  TINYINT(1) DEFAULT 0",
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS notif_canal_wa     TINYINT(1) DEFAULT 0",
    // ── Roles / Permisos (JSON) ────────────────────────────────────────────
    "ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS roles_permisos     JSON DEFAULT NULL COMMENT 'Matriz de permisos por rol (enfermero/medico/familiar)'",
];

$log    = [];
$errors = [];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $preview = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 100);
        $log[]   = "✅ <code>{$preview}…</code>";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            $log[] = "⚠️ Columna ya existe (OK): <code>" . mb_substr($sql, 0, 80) . "</code>";
        } else {
            $errors[] = "❌ " . htmlspecialchars($e->getMessage()) . "<pre>{$sql}</pre>";
        }
    }
}

// También agregar columnas institucion que faltan para panel "Institución"
$instMigrations = [
    "ALTER TABLE instituciones ADD COLUMN IF NOT EXISTS rfc          VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE instituciones ADD COLUMN IF NOT EXISTS ciudad       VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE instituciones ADD COLUMN IF NOT EXISTS estado_inst  VARCHAR(60)  DEFAULT NULL",
    "ALTER TABLE instituciones ADD COLUMN IF NOT EXISTS num_camas    SMALLINT UNSIGNED DEFAULT NULL",
];
foreach ($instMigrations as $sql) {
    try {
        $pdo->exec($sql);
        $log[] = "✅ <code>" . mb_substr($sql, 0, 100) . "</code>";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            $log[] = "⚠️ Ya existe: <code>" . mb_substr($sql, 0, 80) . "</code>";
        } else {
            $errors[] = "❌ " . htmlspecialchars($e->getMessage());
        }
    }
}

// Columnas extra de usuarios para perfil
$usuarioMigrations = [
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) DEFAULT NULL",
];
foreach ($usuarioMigrations as $sql) {
    try {
        $pdo->exec($sql);
        $log[] = "✅ <code>" . mb_substr($sql, 0, 100) . "</code>";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            $log[] = "⚠️ Ya existe: <code>" . mb_substr($sql, 0, 80) . "</code>";
        } else {
            $errors[] = "❌ " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Migración configuración v2</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f1f5f9;padding:40px 20px;color:#1e293b}
  .card{max-width:860px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden}
  .hdr{background:#1e3a6e;color:#fff;padding:24px 32px}
  .hdr h1{font-size:20px;font-weight:700}
  .body{padding:32px}
  .log-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;max-height:500px;overflow-y:auto;font-size:12.5px;line-height:1.9}
  .errors{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:16px;margin-top:16px;font-size:12.5px}
  .ok  {background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-top:16px;font-weight:600}
  .fail{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:14px;margin-top:16px;font-weight:600}
  code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:11px}
</style></head>
<body>
<div class="card">
  <div class="hdr"><h1>🏥 GeriApp — Migración configuración v2</h1></div>
  <div class="body">
    <div class="log-box"><?= implode('<br>', $log) ?></div>
    <?php if (!empty($errors)): ?>
    <div class="errors"><strong>Errores:</strong><br><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <div class="<?= empty($errors) ? 'ok' : 'fail' ?>">
      <?= empty($errors) ? '✅ Migración completada sin errores.' : '⚠️ Migración con ' . count($errors) . ' error(es).' ?>
    </div>
  </div>
</div>
</body></html>
