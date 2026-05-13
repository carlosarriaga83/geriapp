<?php
/**
 * GeriApp — Migración: Multi-Tenant
 * ─────────────────────────────────────────────────────────────
 * Ejecutar UNA SOLA VEZ desde navegador o CLI:
 *   php migrate_multi_tenant.php
 *
 * Qué hace:
 *   1. Agrega columna `db_name` a `instituciones`
 *   2. Crea tabla `usuario_instituciones` (pivot user ↔ institution)
 *   3. Rellena el pivot con los datos existentes de usuarios.institucion_id
 *   4. Crea directorio de uploads por institución para las existentes
 * ─────────────────────────────────────────────────────────────
 */

define('BASE_PATH', dirname(__DIR__));
require_once dirname(__DIR__) . '/conf/config.db.php';
require_once __DIR__ . '/Database.php';

$db = Database::getMaster();

$log = [];
$errors = [];

$migrate_step = function(string $titulo, callable $fn) use (&$log, &$errors, $db): void
{
    try {
        $fn($db);
        $log[] = "✓ {$titulo}";
    } catch (PDOException $e) {
        // Algunos errores son aceptables (columna ya existe, tabla ya existe)
        if (str_contains($e->getMessage(), 'Duplicate column name')
            || str_contains($e->getMessage(), 'already exists')) {
            $log[] = "⚠ {$titulo} — ya existe, omitido";
        } else {
            $errors[] = "✗ {$titulo}: " . $e->getMessage();
        }
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// Paso 1: Agregar db_name a instituciones
// ─────────────────────────────────────────────────────────────────────────────
$migrate_step('Agregar columna db_name a instituciones', function(PDO $db) {
    $db->exec("ALTER TABLE `instituciones`
               ADD COLUMN `db_name` VARCHAR(80) NULL DEFAULT NULL
               COMMENT 'Nombre de la BD del tenant. NULL = BD compartida (master)'
               AFTER `id`");
});

// ─────────────────────────────────────────────────────────────────────────────
// Paso 2: Crear tabla usuario_instituciones (pivot)
// ─────────────────────────────────────────────────────────────────────────────
$migrate_step('Crear tabla usuario_instituciones', function(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `usuario_instituciones` (
          `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `usuario_id`     INT UNSIGNED NOT NULL,
          `institucion_id` INT UNSIGNED NOT NULL,
          `rol`            ENUM('admin','medico','enfermero','familiar') NOT NULL DEFAULT 'enfermero',
          `estado`         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
          `creado_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_usr_inst` (`usuario_id`, `institucion_id`),
          KEY `fk_ui_usr`  (`usuario_id`),
          KEY `fk_ui_inst` (`institucion_id`),
          CONSTRAINT `fk_ui_usr`  FOREIGN KEY (`usuario_id`)
              REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_ui_inst` FOREIGN KEY (`institucion_id`)
              REFERENCES `instituciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Pivot: qué usuarios pertenecen a qué instituciones y con qué rol';
    ");
});

// ─────────────────────────────────────────────────────────────────────────────
// Paso 3: Poblar usuario_instituciones con datos actuales
// ─────────────────────────────────────────────────────────────────────────────
$migrate_step('Poblar usuario_instituciones desde usuarios.institucion_id', function(PDO $db) {
    // Tomar todos los usuarios con institucion_id != NULL y rol != superadmin
    $stmt = $db->query(
        "SELECT id, institucion_id, rol FROM usuarios
         WHERE institucion_id IS NOT NULL AND rol != 'superadmin'"
    );
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ins = $db->prepare(
        "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
         VALUES (?, ?, ?)"
    );

    // Los roles válidos en el pivot (admin, medico, enfermero, familiar)
    // 'superadmin' no aplica al pivot, ya lo filtramos arriba
    $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];

    $count = 0;
    foreach ($usuarios as $u) {
        $rol = in_array($u['rol'], $rolesValidos, true) ? $u['rol'] : 'enfermero';
        $ins->execute([$u['id'], $u['institucion_id'], $rol]);
        $count++;
    }
    // Adjuntar resultado al log global
    $GLOBALS['migrate_extra_log'][] = "  → {$count} entradas sincronizadas en el pivot";
});

// ─────────────────────────────────────────────────────────────────────────────
// Paso 4: Crear directorios de uploads por institución para las existentes
// ─────────────────────────────────────────────────────────────────────────────
$migrate_step('Crear directorios de uploads por institución', function(PDO $db) {
    $stmt        = $db->query("SELECT id FROM instituciones");
    $instituciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $uploadsBase   = BASE_PATH . '/uploads';

    $subdirs = ['fotos_residentes', 'historial', 'logos'];
    $count   = 0;
    foreach ($instituciones as $instId) {
        foreach ($subdirs as $sub) {
            $dir = "{$uploadsBase}/{$sub}/{$instId}";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $count++;
            }
        }
    }
    $GLOBALS['migrate_extra_log'][] = "  → {$count} directorios creados";
});

// ─────────────────────────────────────────────────────────────────────────────
// Reporte
// ─────────────────────────────────────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "\n=== GeriApp — migrate_multi_tenant ===\n\n";
    foreach ($log as $line)                           { echo $line . "\n"; }
    foreach ($GLOBALS['migrate_extra_log'] ?? [] as $l) { echo $l . "\n"; }
    if ($errors) {
        echo "\n⚠ ERRORES:\n";
        foreach ($errors as $e) { echo $e . "\n"; }
    }
    echo "\nFin.\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== GeriApp — migrate_multi_tenant ===\n\n";
    foreach ($log as $line)                           { echo $line . "\n"; }
    foreach ($GLOBALS['migrate_extra_log'] ?? [] as $l) { echo $l . "\n"; }
    if ($errors) {
        echo "\n⚠ ERRORES:\n";
        foreach ($errors as $e) { echo $e . "\n"; }
    }
    echo "\nFin. Puedes eliminar este archivo.\n";
}
