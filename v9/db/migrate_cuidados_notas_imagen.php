<?php
/**
 * Migration: Add imagen column to cuidados_notas for image attachments in shift notes.
 *
 * Run once: php migrate_cuidados_notas_imagen.php
 */

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/conf/config.db.php';

echo "=== Migration: cuidados_notas.imagen ===\n";

try {
    $pdo = Database::getMaster();
    $stmt = $pdo->query("SELECT id, db_name FROM instituciones");
    $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($instituciones as $inst) {
        $dbName = $inst['db_name'];
        echo "  [{$inst['id']}] {$dbName} ... ";

        try {
            $db = Database::getTenant((int) $inst['id']);

            // Check if column already exists
            $cols = $db->query("SHOW COLUMNS FROM cuidados_notas LIKE 'imagen'")->fetchAll();
            if (count($cols)) {
                echo "already exists\n";
                continue;
            }

            $db->exec("ALTER TABLE cuidados_notas ADD COLUMN imagen VARCHAR(255) DEFAULT NULL AFTER nota");
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
