<?php
/**
 * Migration: Add preferencias JSON column to usuarios table
 * Stores per-user UI preferences (font_size, spacing, theme)
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../conf/config.php';

try {
    $db = Database::getInstance();

    // Check if column already exists
    $cols = $db->query("SHOW COLUMNS FROM usuarios LIKE 'preferencias'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN preferencias JSON DEFAULT NULL AFTER avatar_path");
        echo "✅ Columna 'preferencias' agregada a usuarios.\n";
    } else {
        echo "ℹ️ Columna 'preferencias' ya existe.\n";
    }

    echo "Migración completada.\n";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
