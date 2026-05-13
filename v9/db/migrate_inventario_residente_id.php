<?php
/**
 * Migration: Add residente_id column to inventario_items table.
 * This allows inventory items to be optionally assigned per-resident
 * while also supporting institution-wide items (residente_id = NULL).
 *
 * Run once: php migrate_inventario_residente_id.php
 */

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/conf/config.db.php';

echo "=== Migration: inventario_items.residente_id ===\n";

try {
    $pdo = Database::getMaster();
    $stmt = $pdo->query("SELECT id, db_name FROM instituciones");
    $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($instituciones as $inst) {
        echo "  [{$inst['id']}] {$inst['db_name']} ... ";

        try {
            $db = Database::getTenant((int) $inst['id']);

            // Check if column already exists
            $cols = $db->query("SHOW COLUMNS FROM inventario_items LIKE 'residente_id'")->fetchAll();
            if (count($cols)) {
                echo "already exists\n";
                continue;
            }

            $db->exec("ALTER TABLE inventario_items ADD COLUMN residente_id INT UNSIGNED DEFAULT NULL AFTER institucion_id");
            echo "OK\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "\nDone.\n";
} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
