<?php
/**
 * Migration: Add max_residentes and max_usuarios columns to instituciones table.
 * These columns allow per-institution limits independent of the plan.
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$cols = [
    'max_residentes' => "INT UNSIGNED DEFAULT NULL AFTER `plan_vence_at`",
    'max_usuarios'   => "INT UNSIGNED DEFAULT NULL AFTER `max_residentes`",
];

foreach ($cols as $col => $definition) {
    $check = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instituciones' AND COLUMN_NAME = '$col'");
    if ((int)$check->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `instituciones` ADD COLUMN `$col` $definition");
        echo "✅ Added column '$col' to instituciones\n";
    } else {
        echo "⏭ Column '$col' already exists\n";
    }
}

echo "\nMigration complete.\n";
