<?php
/**
 * GeriApp — Migración: tabla cuidados_registros
 * Almacena registros de cuidados estructurados por categoría.
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

echo "<pre>\n=== Migración: cuidados_registros ===\n\n";

$instId = $_SESSION['user_institucion_id'] ?? null;
if (!$instId) {
    echo "ERROR: No hay institución activa en sesión.\n";
    echo "Inicia sesión primero y luego ejecuta esta migración.\n</pre>";
    exit;
}

$db = Database::getTenant($instId);

// ── Tabla cuidados_registros ────────────────────────────────────────────────
$sql = "
CREATE TABLE IF NOT EXISTS `cuidados_registros` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL COMMENT 'Ref. a usuarios en master',
  `categoria`       VARCHAR(30)  NOT NULL COMMENT 'sueno|alimentacion|medicacion|higiene|terapia|movilidad|eliminacion|comportamiento|signos_vitales',
  `datos`           JSON         DEFAULT NULL COMMENT 'Datos estructurados del formulario',
  `observaciones`   TEXT         DEFAULT NULL,
  `fecha`           DATE         NOT NULL,
  `hora`            VARCHAR(5)   NOT NULL COMMENT 'HH:MM',
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_residente` (`residente_id`),
  KEY `idx_cr_fecha`     (`fecha`),
  KEY `idx_cr_categoria` (`categoria`),
  KEY `idx_cr_inst_fecha` (`institucion_id`, `fecha`),
  CONSTRAINT `fk_cr_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "✓ Tabla cuidados_registros creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Migración completada ===\n</pre>";
