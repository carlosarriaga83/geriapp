<?php
/**
 * Migración: Agregar columna imagen_url a notificaciones_sistema
 * para soportar GIFs / imágenes ilustrativas en notificaciones.
 *
 * Ejecuta en master Y en todas las BDs tenant que la tengan.
 */

require_once __DIR__ . '/Database.php';

function addImagenUrlColumn(PDO $db, string $label): void {
    try {
        // Primero verificar si la tabla existe
        $check = $db->query("SHOW TABLES LIKE 'notificaciones_sistema'");
        if (!$check->fetch()) {
            echo "[{$label}] Tabla notificaciones_sistema no existe — omitido.\n";
            return;
        }
        $db->exec("ALTER TABLE `notificaciones_sistema` ADD COLUMN `imagen_url` VARCHAR(500) DEFAULT NULL AFTER `opciones_respuesta`");
        echo "[{$label}] Columna imagen_url agregada.\n";
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "[{$label}] Columna imagen_url ya existe.\n";
        } else {
            echo "[{$label}] Error: " . $e->getMessage() . "\n";
        }
    }
}

// Master DB
$master = Database::getInstance();
addImagenUrlColumn($master, 'master');

// Tenant DBs
try {
    $rows = $master->query("SELECT id, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        try {
            $tenantDb = Database::getTenant((int)$row['id']);
            addImagenUrlColumn($tenantDb, "tenant:{$row['db_name']}");
        } catch (Exception $e) {
            echo "[tenant:{$row['db_name']}] Error conexión: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "No se pudieron listar instituciones: " . $e->getMessage() . "\n";
}

echo "Migración completada.\n";
