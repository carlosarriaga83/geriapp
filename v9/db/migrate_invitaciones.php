<?php
/**
 * Migración: crea tabla invitaciones si no existe.
 * Ejecutar una sola vez: php db/migrate_invitaciones.php
 * O vía navegador (requiere acceso directo): /geriapp/v2/db/migrate_invitaciones.php
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

$sql = "CREATE TABLE IF NOT EXISTS `invitaciones` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `rol`             ENUM('admin','medico','enfermero','familiar') NOT NULL DEFAULT 'enfermero',
  `token`           VARCHAR(64)  NOT NULL,
  `estado`          ENUM('pendiente','aceptada','expirada') NOT NULL DEFAULT 'pendiente',
  `mensaje`         TEXT         DEFAULT NULL,
  `expires_at`      TIMESTAMP    NOT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_token` (`token`),
  KEY `fk_inv_inst` (`institucion_id`),
  KEY `fk_inv_usr`  (`creado_por`),
  KEY `idx_inv_estado` (`estado`),
  CONSTRAINT `fk_inv_inst` FOREIGN KEY (`institucion_id`)
    REFERENCES `instituciones` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_inv_usr` FOREIGN KEY (`creado_por`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla 'invitaciones' creada o ya existe.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// ── Agregar columnas nombre/apellido_sugerido si faltan ──────────────────
$cols = array_column($db->query("SHOW COLUMNS FROM invitaciones")->fetchAll(), 'Field');
foreach (['nombre_sugerido' => 'VARCHAR(80) DEFAULT NULL AFTER residente_ids',
          'apellido_sugerido' => 'VARCHAR(80) DEFAULT NULL AFTER nombre_sugerido',
          'telefono' => 'VARCHAR(30) DEFAULT NULL AFTER apellido_sugerido'] as $col => $def) {
    if (!in_array($col, $cols)) {
        try {
            $db->exec("ALTER TABLE invitaciones ADD COLUMN `{$col}` {$def}");
            echo "✓ Columna '{$col}' agregada.\n";
        } catch (PDOException $e) {
            echo "⚠ Columna '{$col}': " . $e->getMessage() . "\n";
        }
    }
}
