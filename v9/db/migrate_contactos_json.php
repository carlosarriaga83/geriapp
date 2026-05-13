<?php
/**
 * Migration: Add contactos_json column to residentes for multiple family contacts.
 */
require_once __DIR__ . '/Database.php';

echo "=== Migrating: contactos_json column ===\n";

$pdo = Database::getMaster();

// Check if column exists in master DB
$cols = $pdo->query("SHOW COLUMNS FROM residentes LIKE 'contactos_json'")->fetchAll();
if (count($cols) === 0) {
    $pdo->exec("ALTER TABLE residentes ADD COLUMN `contactos_json` TEXT DEFAULT NULL COMMENT 'Extra contacts JSON array' AFTER `contacto_direccion`");
    echo "  Master DB: ADDED\n";
} else {
    echo "  Master DB: already exists\n";
}

// Also check tenant DBs if they have separate databases
$rows = $pdo->query("SELECT id, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $inst) {
    echo "  Tenant [{$inst['id']}] {$inst['db_name']} ... ";
    try {
        $tpdo = Database::getTenant($inst['id']);
        $tcols = $tpdo->query("SHOW COLUMNS FROM residentes LIKE 'contactos_json'")->fetchAll();
        if (count($tcols) === 0) {
            $tpdo->exec("ALTER TABLE residentes ADD COLUMN `contactos_json` TEXT DEFAULT NULL COMMENT 'Extra contacts JSON array' AFTER `contacto_direccion`");
            echo "ADDED\n";
        } else {
            echo "already exists\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "=== Done ===\n";
