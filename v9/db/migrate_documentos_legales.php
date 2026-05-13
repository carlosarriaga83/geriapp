<?php
/**
 * GeriApp — Migración: tablas documentos_legales y firmas_documentos
 * Gestión de Términos y Condiciones / Aviso de Privacidad con firma gráfica.
 *
 * Ejecutar: iniciar sesión → visitar /db/migrate_documentos_legales.php
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

echo "<pre>\n=== Migración: documentos_legales + firmas_documentos ===\n\n";

$instId = $_SESSION['user_institucion_id'] ?? null;
if (!$instId) {
    echo "ERROR: No hay institución activa en sesión.\n";
    echo "Inicia sesión primero y luego ejecuta esta migración.\n</pre>";
    exit;
}

$db = Database::getTenant($instId);

// ── Tabla documentos_legales ────────────────────────────────────────────────
$sql1 = "
CREATE TABLE IF NOT EXISTS `documentos_legales` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id` INT UNSIGNED NOT NULL,
  `tipo`           ENUM('terminos','privacidad') NOT NULL,
  `version`        VARCHAR(20)  NOT NULL DEFAULT '1.0',
  `titulo`         VARCHAR(200) NOT NULL,
  `contenido`      MEDIUMTEXT   NOT NULL,
  `vigente`        TINYINT(1)   NOT NULL DEFAULT 0,
  `requiere_firma` TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_por`     INT UNSIGNED DEFAULT NULL,
  `creado_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dl_inst_tipo` (`institucion_id`,`tipo`),
  KEY `idx_dl_vigente` (`vigente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql1);
    echo "✓ Tabla documentos_legales creada/verificada\n";
} catch (PDOException $e) {
    echo "⚠ documentos_legales: " . $e->getMessage() . "\n";
}

// ── Tabla firmas_documentos ─────────────────────────────────────────────────
$sql2 = "
CREATE TABLE IF NOT EXISTS `firmas_documentos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id`   INT UNSIGNED NOT NULL,
  `usuario_id`     INT UNSIGNED NOT NULL,
  `firma_data`     MEDIUMTEXT   NOT NULL COMMENT 'Base64 PNG de firma manuscrita',
  `ip`             VARCHAR(45)  DEFAULT NULL,
  `user_agent`     VARCHAR(512) DEFAULT NULL,
  `firmado_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fd_doc_usr` (`documento_id`,`usuario_id`),
  KEY `idx_fd_doc` (`documento_id`),
  KEY `idx_fd_usr` (`usuario_id`),
  CONSTRAINT `fk_fd_doc` FOREIGN KEY (`documento_id`) REFERENCES `documentos_legales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql2);
    echo "✓ Tabla firmas_documentos creada/verificada\n";
} catch (PDOException $e) {
    echo "⚠ firmas_documentos: " . $e->getMessage() . "\n";
}

echo "\n=== Migración completada ===\n</pre>";
