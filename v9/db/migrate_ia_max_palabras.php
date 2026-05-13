<?php
/**
 * Migración: agregar columna ia_max_palabras a configuracion
 */
require_once dirname(__DIR__) . '/conf/config.db.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    $pdo->exec("ALTER TABLE configuracion ADD COLUMN ia_max_palabras INT DEFAULT 400 AFTER ia_prompt");
    echo "OK: columna ia_max_palabras agregada.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "La columna ya existe.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
