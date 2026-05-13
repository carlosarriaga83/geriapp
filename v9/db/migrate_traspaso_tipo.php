<?php
/**
 * Migration: add 'traspaso' to bitacora_entradas.tipo ENUM
 * Run once: php v2/db/migrate_traspaso_tipo.php
 */
$isCli = PHP_SAPI === 'cli';
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); }

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/db/Database.php';

$db = Database::getInstance();

// Check current ENUM values
$row = $db->query("SHOW COLUMNS FROM bitacora_entradas LIKE 'tipo'")->fetch();
if (!$row) {
    die("ERROR: columna 'tipo' no encontrada en bitacora_entradas\n");
}
echo "Tipo actual: " . $row['Type'] . "\n";

if (str_contains($row['Type'], 'traspaso')) {
    echo "✓ 'traspaso' ya está en el ENUM — nada que hacer.\n";
    exit(0);
}

$sql = "ALTER TABLE bitacora_entradas
        MODIFY COLUMN tipo ENUM(
            'nota','alerta','medicamento','actividad',
            'incidente','visita','vitales','cuidados',
            'sueno','traspaso'
        ) NOT NULL DEFAULT 'nota'";

$db->exec($sql);
echo "✓ ENUM ampliado: 'traspaso' agregado a bitacora_entradas.tipo\n";
