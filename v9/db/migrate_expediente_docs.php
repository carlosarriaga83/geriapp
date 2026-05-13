<?php
/**
 * GeriApp — Migración: tabla expediente_docs
 * Documentos clínicos vinculados a residentes.
 */

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

// ── 1. Tabla expediente_docs ──────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS `expediente_docs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `residente_id`     INT UNSIGNED NOT NULL,
  `tipo`             ENUM('receta','laboratorio','imagen','interpretacion','hospitalizacion','legal','nota_enfermeria') NOT NULL DEFAULT 'receta',
  `titulo`           VARCHAR(255) NOT NULL,
  `descripcion`      TEXT NULL,
  `fuente`           ENUM('medico','familiar','residente','otro') NOT NULL DEFAULT 'medico',
  `nombre_fuente`    VARCHAR(150) NULL,
  `fecha_documento`  DATE NULL,
  `archivo_nombre`   VARCHAR(255) NULL,
  `archivo_tipo`     VARCHAR(50) NULL,
  `archivo_path`     VARCHAR(500) NULL,
  `created_by`       INT UNSIGNED NOT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expdoc_residente`   (`residente_id`),
  KEY `idx_expdoc_institucion` (`institucion_id`),
  KEY `idx_expdoc_tipo`        (`tipo`),
  KEY `idx_expdoc_fecha`       (`fecha_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "✓ Tabla expediente_docs creada/verificada.\n";

// ── 1b. Columna archivos_json para multi-archivo ──────────────────────────
$colsJ = $db->query("SHOW COLUMNS FROM expediente_docs LIKE 'archivos_json'")->fetchAll();
if (count($colsJ) === 0) {
    $db->exec("ALTER TABLE expediente_docs ADD COLUMN `archivos_json` TEXT DEFAULT NULL AFTER `archivo_path`");
    echo "✓ Columna archivos_json añadida a expediente_docs.\n";
} else {
    echo "· Columna archivos_json ya existe en expediente_docs.\n";
}

// ── 2. Campo expediente_id en prescripciones ──────────────────────────────
$cols = $db->query("SHOW COLUMNS FROM prescripciones LIKE 'expediente_id'")->fetchAll();
if (count($cols) === 0) {
    $db->exec("ALTER TABLE prescripciones ADD COLUMN `expediente_id` INT UNSIGNED NULL AFTER `imagen`");
    echo "✓ Columna expediente_id añadida a prescripciones.\n";
} else {
    echo "· Columna expediente_id ya existe en prescripciones.\n";
}

echo "Migración completada.\n";
