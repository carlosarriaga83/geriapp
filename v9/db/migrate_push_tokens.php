<?php
/**
 * Migración: Crear tabla push_tokens en master DB
 *
 * Almacena los tokens FCM de dispositivos móviles (Capacitor)
 * para enviar push notifications vía Firebase Cloud Messaging.
 *
 * Los tokens son por usuario (master), no por tenant.
 */

require_once __DIR__ . '/Database.php';

$sql = "CREATE TABLE IF NOT EXISTS `push_tokens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `token`       VARCHAR(512) NOT NULL,
  `platform`    ENUM('android','ios','web') NOT NULL DEFAULT 'android',
  `creado_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_token` (`token`(255)),
  KEY `idx_pt_user` (`usuario_id`),
  CONSTRAINT `fk_pt_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $master = Database::getInstance();
    $master->exec($sql);
    echo "Tabla push_tokens creada (o ya existía).\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migración push_tokens completada.\n";
