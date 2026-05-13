<?php
/**
 * Migration: Create residentes_estado_log table
 * Tracks state changes (activo → egresado → fallecido) for residents.
 */
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$db->exec("
CREATE TABLE IF NOT EXISTS `residentes_estado_log` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `estado_anterior` VARCHAR(30)  NOT NULL,
  `estado_nuevo`    VARCHAR(30)  NOT NULL,
  `usuario_id`      INT UNSIGNED DEFAULT NULL,
  `usuario_nombre`  VARCHAR(150) DEFAULT NULL,
  `nota`            TEXT         DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estado_log_res` (`residente_id`),
  KEY `idx_estado_log_inst` (`institucion_id`),
  KEY `idx_estado_log_fecha` (`creado_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ residentes_estado_log table created.\n";
