<?php
/**
 * Migration: add app_url column to configuracion table.
 * Run once: http://localhost/geriapp/v2/db/migrate_add_app_url.php
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/database.php';

$db = Database::getInstance();

$steps = [
    "ALTER TABLE `configuracion` ADD COLUMN IF NOT EXISTS `app_url` VARCHAR(255) DEFAULT NULL AFTER `roles_permisos`",
];

foreach ($steps as $sql) {
    try {
        $db->exec($sql);
        echo "<p style='color:green'>✓ " . htmlspecialchars($sql) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠ " . htmlspecialchars($sql) . "<br>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
echo "<p><strong>Listo.</strong></p>";
