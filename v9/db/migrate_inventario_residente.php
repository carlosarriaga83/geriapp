<?php
/**
 * Migration: Add residente_id to inventario_items
 * Makes inventory per-resident instead of institution-wide.
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

echo "=== Migrate: Add residente_id to inventario_items ===\n";

$db = Database::getInstance();

// Add column if not exists
try {
    $cols = $db->query("SHOW COLUMNS FROM inventario_items LIKE 'residente_id'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE inventario_items ADD COLUMN residente_id INT UNSIGNED DEFAULT NULL AFTER institucion_id");
        $db->exec("ALTER TABLE inventario_items ADD INDEX idx_ii_residente (residente_id)");
        echo "Column residente_id added to inventario_items.\n";
    } else {
        echo "Column residente_id already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
