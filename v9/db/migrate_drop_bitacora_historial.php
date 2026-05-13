<?php
/**
 * GeriApp — Migración: Eliminar tablas legacy de Bitácora e Historial Clínico
 *
 * Estas tablas fueron reemplazadas por:
 *   - cuidados_registros  (reemplaza bitacora_entradas + bitacora_turnos)
 *   - cuidados_notas      (reemplaza notas de turno)
 *   - El módulo medico/   (reemplaza historial clínico)
 *
 * Tablas a eliminar (7):
 *   - bitacora_firmas              (FK → bitacora_turnos)
 *   - bitacora_rx_administraciones (FK → bitacora_turnos)
 *   - bitacora_entradas            (FK → bitacora_turnos)
 *   - bitacora_turnos              (tabla padre)
 *   - historial_documentos         (FK → historial_expedientes)
 *   - historial_evoluciones        (FK → historial_expedientes)
 *   - historial_expedientes        (tabla padre)
 *
 * Ejecución:  php migrate_drop_bitacora_historial.php
 * O bien desde el panel Superadmin → Migraciones
 */

// Can run standalone or be included from superadmin migration runner
if (!isset($db)) {
    require_once __DIR__ . '/../conf/config.php';
    require_once __DIR__ . '/Database.php';
}

echo "═══ GeriApp: Eliminando tablas legacy de Bitácora e Historial ═══\n\n";

$masterDb = Database::getMaster();

// Order: children first, then parents (to respect FK constraints)
$tablesToDrop = [
    'bitacora_firmas',
    'bitacora_rx_administraciones',
    'bitacora_entradas',
    'bitacora_turnos',
    'historial_documentos',
    'historial_evoluciones',
    'historial_expedientes',
];

$totalDropped = 0;

/**
 * Elimina las tablas de una BD dada.
 */
function _dropLegacyTables(PDO $pdo, string $dbName, array $tables): int
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
$totalDropped += _dropLegacyTables($masterDb, $masterName, $tablesToDrop);
echo "\n";

// ── 2. BD Tenants separadas ─────────────────────────────────────────────
$tenants = $masterDb->query(
    "SELECT id, nombre, db_name FROM instituciones
     WHERE db_name IS NOT NULL AND db_name != '' AND db_name != " . $masterDb->quote($masterName) . "
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "── No hay tenants con BD separada.\n\n";
} else {
    foreach ($tenants as $t) {
        echo "── Tenant #{$t['id']}: {$t['nombre']} (BD: {$t['db_name']})\n";
        try {
            $tenantPdo = Database::getTenant((int) $t['id']);
            $totalDropped += _dropLegacyTables($tenantPdo, $t['db_name'], $tablesToDrop);
        } catch (\Exception $e) {
            echo "   ✗ No se pudo conectar: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

echo "═══ Completado: {$totalDropped} tabla(s) eliminada(s) en total ═══\n";
