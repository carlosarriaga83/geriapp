<?php
/**
 * GeriApp v8 — Migración: PHIPA/HIPAA Security Hardening
 *
 * Cambios en BD requeridos por la auditoría de seguridad v1.21.0:
 *   1. Columna password_change_required en usuarios (§1.11)
 *   2. Índice en login_attempts para rate limiting eficiente
 *
 * Ejecución: php migrate_phipa_security.php
 * O desde Superadmin → Migraciones
 */

if (!isset($db)) {
    require_once dirname(__DIR__) . '/conf/config.php';
    require_once dirname(__DIR__) . '/db/Database.php';
}

echo "<pre>\n=== Migración: PHIPA Security Hardening (v1.21.0) ===\n\n";

$masterDb = Database::getMaster();

// ── 1. Master DB: agregar password_change_required a usuarios ─────────────
echo "── Master DB ──\n";

try {
    $cols = $masterDb->query("SHOW COLUMNS FROM usuarios LIKE 'password_change_required'")->fetchAll();
    if (empty($cols)) {
        $masterDb->exec("ALTER TABLE usuarios ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
        echo "   ✓ usuarios.password_change_required añadido\n";
    } else {
        echo "   · usuarios.password_change_required ya existe\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// ── 2. Índice compuesto en login_attempts para rate limiting ──────────────
try {
    $indexes = $masterDb->query("SHOW INDEX FROM login_attempts WHERE Key_name = 'idx_login_rate'")->fetchAll();
    if (empty($indexes)) {
        $masterDb->exec("ALTER TABLE login_attempts ADD INDEX idx_login_rate (email, ip, exitoso, creado_at)");
        echo "   ✓ login_attempts.idx_login_rate añadido\n";
    } else {
        echo "   · login_attempts.idx_login_rate ya existe\n";
    }
} catch (\Exception $e) {
    echo "   · login_attempts índice: " . $e->getMessage() . "\n";
}

// ── 3. Índice en sesiones_activas para cleanup ───────────────────────────
try {
    $indexes = $masterDb->query("SHOW INDEX FROM sesiones_activas WHERE Key_name = 'idx_ses_ultimo'")->fetchAll();
    if (empty($indexes)) {
        $masterDb->exec("ALTER TABLE sesiones_activas ADD INDEX idx_ses_ultimo (ultimo_acceso)");
        echo "   ✓ sesiones_activas.idx_ses_ultimo añadido\n";
    } else {
        echo "   · sesiones_activas.idx_ses_ultimo ya existe\n";
    }
} catch (\Exception $e) {
    echo "   · sesiones_activas índice: " . $e->getMessage() . "\n";
}

echo "\n=== Migración completada ===\n</pre>";
