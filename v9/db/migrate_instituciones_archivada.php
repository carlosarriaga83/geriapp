<?php
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';
$db = Database::getMaster();
$db->exec("ALTER TABLE instituciones MODIFY COLUMN estado ENUM('activa','trial','suspendida','archivada') NOT NULL DEFAULT 'trial'");
echo "OK: instituciones.estado ENUM updated to include 'archivada'\n";
