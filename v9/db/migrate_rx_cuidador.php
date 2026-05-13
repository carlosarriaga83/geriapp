<?php
/**
 * Migration: Add nombre_cuidador column to bitacora_rx_administraciones
 * Run once: php migrate_rx_cuidador.php
 */
require_once dirname(__DIR__) . '/conf/config.db.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$cols = $db->query("SHOW COLUMNS FROM `bitacora_rx_administraciones` LIKE 'nombre_cuidador'")->fetchAll();
if ($cols) {
    echo "OK: La columna 'nombre_cuidador' ya existe. Nada que hacer.\n";
    exit(0);
}

$db->exec("ALTER TABLE `bitacora_rx_administraciones`
    ADD COLUMN `nombre_cuidador` VARCHAR(120) DEFAULT NULL
        COMMENT 'Nombre libre del cuidador que administró la dosis'
    AFTER `hora_real`");

echo "OK: Columna 'nombre_cuidador' agregada exitosamente a bitacora_rx_administraciones.\n";
