<?php
/**
 * Migration: Add 'imagen' column to prescripciones table
 * Stores the file path to the prescription photo/image.
 *
 * Run: php migrate_prescripcion_imagen.php
 */

require_once __DIR__ . '/Database.php';

try {
    $db = Database::getInstance();

    // Check if column already exists
    $cols = $db->query("SHOW COLUMNS FROM prescripciones LIKE 'imagen'")->fetchAll();
    if (count($cols) === 0) {
        $db->exec("ALTER TABLE prescripciones ADD COLUMN imagen VARCHAR(500) DEFAULT NULL AFTER indicacion");
        echo "✅ Column 'imagen' added to prescripciones.\n";
    } else {
        echo "ℹ️  Column 'imagen' already exists.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
