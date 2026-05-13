<?php
/**
 * GeriApp — Migración Cuidados v2
 * Crea tablas: cuidados_notas, inventario_items, inventario_movimientos
 * Agrega campo tipo_sangre a residentes (si no existe).
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Allow CLI: php migrate_cuidados_v2.php <instId>
$instId = $argv[1] ?? $_SESSION['user_institucion_id'] ?? null;
if (!$instId) { echo "Error: sesión sin institución. Uso CLI: php migrate_cuidados_v2.php <instId>\n"; exit; }

$db = Database::getTenant($instId);

$sqls = [
    "CREATE TABLE IF NOT EXISTS `cuidados_notas` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `institucion_id`  INT UNSIGNED NOT NULL,
        `residente_id`    INT UNSIGNED NOT NULL,
        `usuario_id`      INT UNSIGNED NOT NULL,
        `nota`            TEXT NOT NULL,
        `prioridad`       ENUM('normal','importante','urgente') NOT NULL DEFAULT 'normal',
        `leido_por`       JSON DEFAULT NULL,
        `fecha`           DATE NOT NULL,
        `creado_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_notas_res_fecha` (`residente_id`, `fecha`),
        KEY `idx_notas_inst` (`institucion_id`),
        CONSTRAINT `fk_notas_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `inventario_items` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `institucion_id`  INT UNSIGNED NOT NULL,
        `nombre`          VARCHAR(200) NOT NULL,
        `tipo`            ENUM('medicamento','suplemento','insumo','otro') NOT NULL DEFAULT 'medicamento',
        `unidad`          VARCHAR(50) NOT NULL DEFAULT 'unidades',
        `stock_actual`    INT NOT NULL DEFAULT 0,
        `stock_minimo`    INT NOT NULL DEFAULT 10,
        `vencimiento`     DATE DEFAULT NULL,
        `notas`           TEXT DEFAULT NULL,
        `activo`          TINYINT(1) NOT NULL DEFAULT 1,
        `creado_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_inv_inst` (`institucion_id`),
        KEY `idx_inv_tipo` (`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `item_id`         INT UNSIGNED NOT NULL,
        `institucion_id`  INT UNSIGNED NOT NULL,
        `tipo`            ENUM('entrada','salida','ajuste') NOT NULL,
        `cantidad`        INT NOT NULL,
        `residente_id`    INT UNSIGNED DEFAULT NULL,
        `usuario_id`      INT UNSIGNED NOT NULL,
        `motivo`          VARCHAR(255) DEFAULT NULL,
        `creado_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_mov_item` (`item_id`),
        KEY `idx_mov_inst` (`institucion_id`),
        CONSTRAINT `fk_mov_item` FOREIGN KEY (`item_id`) REFERENCES `inventario_items` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

// Add tipo_sangre column to residentes if it doesn't exist
$addCol = "ALTER TABLE `residentes` ADD COLUMN `tipo_sangre` VARCHAR(10) DEFAULT NULL AFTER `sexo`";

echo "<h2>Migración Cuidados v2</h2>";

$ok = 0;
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        $ok++;
        echo "<p style='color:green'>✓ Tabla creada</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

try {
    $db->exec($addCol);
    echo "<p style='color:green'>✓ Campo tipo_sangre añadido a residentes</p>";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "<p style='color:gray'>○ Campo tipo_sangre ya existe</p>";
    } else {
        echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p>Tablas creadas: {$ok}/3</p>";
echo "<p><a href='../cuidados.php'>← Ir a Cuidados</a></p>";
