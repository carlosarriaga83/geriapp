<?php
/**
 * GeriApp — Migración: agregar campos de contacto y cuidados a residentes
 *
 * Nuevos campos: estado_civil, cuidados_especiales,
 *   contacto_nombre, contacto_parentesco, contacto_telefono,
 *   contacto_telefono2, contacto_email, contacto_direccion
 *
 * Ejecutar UNA SOLA VEZ:
 *   php v3/db/migrate_residentes_campos.php
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../conf/config.php';
    require_once __DIR__ . '/../auth/middleware.php';
    if (($_SESSION['user_rol'] ?? '') !== 'superadmin') {
        http_response_code(403);
        die('Acceso denegado');
    }
}

require_once dirname(__DIR__) . '/conf/config.db.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$columns = [
    "estado_civil         VARCHAR(50)  DEFAULT NULL AFTER nss",
    "cuidados_especiales  TEXT         DEFAULT NULL AFTER notas",
    "contacto_nombre      VARCHAR(200) DEFAULT NULL AFTER cuidados_especiales",
    "contacto_parentesco  VARCHAR(50)  DEFAULT NULL AFTER contacto_nombre",
    "contacto_telefono    VARCHAR(30)  DEFAULT NULL AFTER contacto_parentesco",
    "contacto_telefono2   VARCHAR(30)  DEFAULT NULL AFTER contacto_telefono",
    "contacto_email       VARCHAR(200) DEFAULT NULL AFTER contacto_telefono2",
    "contacto_direccion   VARCHAR(300) DEFAULT NULL AFTER contacto_email",
];

$ok = 0;
$skip = 0;

foreach ($columns as $def) {
    $colName = trim(explode(' ', trim($def))[0]);
    // Check if column already exists
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residentes' AND COLUMN_NAME = ?"
    );
    $stmt->execute([$colName]);
    if ((int)$stmt->fetchColumn() > 0) {
        $skip++;
        continue;
    }
    $db->exec("ALTER TABLE residentes ADD COLUMN $def");
    $ok++;
}

$msg = "Migración completada: $ok columnas añadidas, $skip ya existían.";
if ($isCli) {
    echo $msg . PHP_EOL;
} else {
    echo "<p style='font-family:monospace;padding:20px;'>✓ $msg</p>";
}
