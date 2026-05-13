<?php
/**
 * §5 PHIPA/NOM — Migración: columnas de cifrado (_enc)
 *
 * Agrega columnas TEXT `<campo>_enc` junto a cada campo candidato
 * a cifrado definido en EncryptionMap::FIELDS.
 *
 * Segura: verifica existencia antes de ALTER (idempotente).
 * Multi-tenant: ejecuta en master + todas las BD de tenant.
 *
 * Puede ejecutarse:
 *   1. Desde el tab "Migraciones" del superadmin (run_full_migration)
 *   2. Desde el tab "Cifrado §5" (encryption_prepare) — campo por campo
 */

require_once dirname(__DIR__) . '/conf/config.php';
require_once dirname(__DIR__) . '/db/Database.php';
require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

echo "§5 Migración de columnas de cifrado\n";
echo "====================================\n\n";

$fields = EncryptionMap::FIELDS;

$db = Database::getMaster();

/**
 * Agrega columnas _enc a una conexión PDO.
 */
function addEncColumns(PDO $pdo, array $fields, string $dbLabel): void
{
    foreach ($fields as $table => $cols) {
        // Check if table exists
        $tblCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $tblCheck->execute([$table]);
        if ((int)$tblCheck->fetchColumn() === 0) {
            echo "  ⏭ Tabla `$table` no existe en $dbLabel — omitida\n";
            continue;
        }

        foreach ($cols as $col => $meta) {
            $encCol = $col . '_enc';

            // Check if _enc column already exists
            $colCheck = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $colCheck->execute([$table, $encCol]);

            if ((int)$colCheck->fetchColumn() > 0) {
                echo "  ✓ $table.$encCol ya existe\n";
                continue;
            }

            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$encCol` TEXT NULL AFTER `$col`");
            echo "  ✅ $table.$encCol creada\n";
        }
    }
}

// Master DB
echo "── Master DB ──\n";
addEncColumns($db, $fields, 'master');

// Tenant DBs
$stmt = $db->query("SELECT id, nombre, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''");
$instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($instituciones as $inst) {
    $instId = (int)$inst['id'];
    echo "\n── Tenant [{$instId}] {$inst['nombre']} ──\n";
    try {
        $tenantDb = Database::getTenant($instId);
        addEncColumns($tenantDb, $fields, "tenant_{$instId}");
    } catch (Throwable $e) {
        echo "  ❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n✅ Migración de columnas _enc completada.\n";
