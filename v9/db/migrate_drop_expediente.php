<?php
/**
 * GeriApp — Migración: Eliminar tablas del Expediente Médico
 * 
 * El módulo de Expediente Médico fue migrado a MediApp (app independiente).
 * Este script elimina las 7 tablas de expediente de la BD master Y de
 * todas las BD tenant separadas.
 * 
 * Tablas a eliminar:
 *   - expediente_auditoria
 *   - expediente_consentimientos
 *   - expediente_estudios
 *   - expediente_enfermeria
 *   - expediente_notas
 *   - expediente_hc
 *   - cie10_catalogo
 * 
 * Ejecución:  php migrate_drop_expediente.php
 * O bien desde el panel Superadmin → Migraciones
 */

// Can run standalone or be included from superadmin migration runner
if (!isset($db)) {
    require_once __DIR__ . '/../conf/config.php';
    require_once __DIR__ . '/Database.php';
}

echo "═══ GeriApp: Eliminando tablas de Expediente Médico ═══\n\n";

$masterDb = Database::getMaster();

$tablesToDrop = [
    'expediente_auditoria',
    'expediente_consentimientos', 
    'expediente_estudios',
    'expediente_enfermeria',
    'expediente_notas',
    'expediente_hc',
    'cie10_catalogo'
];

$totalDropped = 0;

/**
 * Elimina las tablas de expediente de una BD dada.
 */
function _dropExpedienteTables(PDO $pdo, string $dbName, array $tables): int
{
    $dropped = 0;
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables as $table) {
        try {
            $exists = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                   AND TABLE_NAME = " . $pdo->quote($table)
            )->fetchColumn();

            if ($exists) {
                $rowCount = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $pdo->exec("DROP TABLE `$table`");
                echo "   ✓ DROP $table ($rowCount registros)\n";
                $dropped++;
            } else {
                echo "   · $table no existe (ya eliminada)\n";
            }
        } catch (\Exception $e) {
            echo "   ✗ Error en $table: " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    return $dropped;
}

// ── 1. BD Master ────────────────────────────────────────────────────────
$masterName = defined('DB_MASTER_NAME') ? DB_MASTER_NAME : DB_NAME;
echo "── BD Master: {$masterName}\n";
$totalDropped += _dropExpedienteTables($masterDb, $masterName, $tablesToDrop);
echo "\n";

// ── 2. BD Tenants separadas ─────────────────────────────────────────────
$tenants = $masterDb->query(
    "SELECT id, nombre, db_name FROM instituciones 
     WHERE db_name IS NOT NULL AND db_name != '' AND db_name != " . $masterDb->quote($masterName) . "
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "No hay instituciones con BD tenant separada.\n\n";
} else {
    foreach ($tenants as $inst) {
        echo "── Institución #{$inst['id']}: {$inst['nombre']} ({$inst['db_name']})\n";
        try {
            $tenantDb = Database::getTenant((int)$inst['id']);
            $totalDropped += _dropExpedienteTables($tenantDb, $inst['db_name'], $tablesToDrop);
        } catch (\Exception $e) {
            echo "   ✗ No se pudo conectar a {$inst['db_name']}: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

echo "═══ Completado: $totalDropped tablas eliminadas ═══\n";
