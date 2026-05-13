<?php
/**
 * Migración: Crear tablas de notificaciones del sistema
 *   - notificaciones_sistema: notificaciones admin → usuarios
 *   - notificaciones_sistema_log: registro de lectura y respuestas
 *
 * Ejecuta en master Y en todas las BDs tenant.
 */

require_once __DIR__ . '/Database.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `notificaciones_sistema` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `institucion_id`  INT UNSIGNED NOT NULL,
      `titulo`          VARCHAR(200) NOT NULL,
      `mensaje`         TEXT         NOT NULL,
      `tipo`            ENUM('info','alerta','pregunta') NOT NULL DEFAULT 'info',
      `opciones_respuesta` JSON     DEFAULT NULL,
      `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
      `creado_por`      INT UNSIGNED NOT NULL,
      `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_ns_inst` (`institucion_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `notificaciones_sistema_log` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `notificacion_id` INT UNSIGNED NOT NULL,
      `institucion_id`  INT UNSIGNED NOT NULL,
      `usuario_id`      INT UNSIGNED NOT NULL,
      `visto_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `respuesta`       TEXT         DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_nsl_notif` (`notificacion_id`),
      KEY `idx_nsl_usuario` (`usuario_id`),
      CONSTRAINT `fk_nsl_notif` FOREIGN KEY (`notificacion_id`) REFERENCES `notificaciones_sistema` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

function runNotifMigration(PDO $db, string $label, array $queries): void {
    $ok = 0;
    $fail = 0;
    foreach ($queries as $sql) {
        try {
            $db->exec($sql);
            $ok++;
        } catch (Exception $e) {
            echo "[{$label}] Error: " . $e->getMessage() . "\n";
            $fail++;
        }
    }
    echo "[{$label}] {$ok} tablas OK, {$fail} errores.\n";
}

// Master DB
$master = Database::getInstance();
runNotifMigration($master, 'master', $queries);

// Tenant DBs
try {
    $rows = $master->query("SELECT id, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        try {
            $tenantDb = Database::getTenant((int)$row['id']);
            runNotifMigration($tenantDb, "tenant:{$row['db_name']}", $queries);
        } catch (Exception $e) {
            echo "[tenant:{$row['db_name']}] Error conexión: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "No se pudieron listar instituciones: " . $e->getMessage() . "\n";
}

echo "Migración notificaciones completada.\n";
