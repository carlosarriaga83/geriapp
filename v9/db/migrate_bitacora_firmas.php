<?php
/**
 * GeriApp — Migración: bitacora_firmas
 *
 * Crea la tabla `bitacora_firmas` para el sistema de firma por residente.
 * Cada (turno_id, residente_id) puede firmarse de forma independiente.
 *
 * Ejecutar: php v2/db/migrate_bitacora_firmas.php
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

$db  = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS `bitacora_firmas` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `turno_id`     INT UNSIGNED NOT NULL,
  `residente_id` INT UNSIGNED NOT NULL,
  `firmado_por`  INT UNSIGNED DEFAULT NULL,
  `firmado_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_turno_res` (`turno_id`,`residente_id`),
  KEY `fk_firma_turno` (`turno_id`),
  KEY `fk_firma_res`   (`residente_id`),
  KEY `fk_firma_usr`   (`firmado_por`),
  CONSTRAINT `fk_firma_turno` FOREIGN KEY (`turno_id`)
    REFERENCES `bitacora_turnos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_firma_res`   FOREIGN KEY (`residente_id`)
    REFERENCES `residentes`     (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_firma_usr`   FOREIGN KEY (`firmado_por`)
    REFERENCES `usuarios`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "✅ Tabla bitacora_firmas creada (o ya existía).\n";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
