<?php
/**
 * Migration: add especialidad column to expediente_docs.
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/database.php';

$db = Database::getInstance();

$steps = [
    "ALTER TABLE `expediente_docs` ADD COLUMN IF NOT EXISTS `especialidad` VARCHAR(100) DEFAULT NULL AFTER `nombre_fuente`",
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
