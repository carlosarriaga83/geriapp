<?php
/**
 * Migration: Add AI configuration columns to configuracion table
 */
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$columns = [
    "ia_proveedor VARCHAR(30) DEFAULT 'openai'",
    "ia_api_key   VARCHAR(500) DEFAULT NULL",
    "ia_modelo    VARCHAR(100) DEFAULT NULL",
    "ia_prompt    TEXT         DEFAULT NULL",
    "inst_nombre  VARCHAR(200) DEFAULT NULL",
    "moneda       VARCHAR(10)  NOT NULL DEFAULT 'MXN'",
];

foreach ($columns as $col) {
    $name = trim(explode(' ', trim($col))[0]);
    $check = $db->query("SHOW COLUMNS FROM `configuracion` LIKE '$name'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE `configuracion` ADD COLUMN $col");
        echo "Added column: $name\n";
    } else {
        echo "Column already exists: $name\n";
    }
}

echo "Migration complete.\n";
