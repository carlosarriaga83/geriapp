<?php
/**
 * GeriApp — Migración: corregir tipo de seg_timeout_sesion
 * Antes: TINYINT(1) (clamp a 127, originalmente creado como flag boolean).
 * Ahora: SMALLINT UNSIGNED (rango 0..65535 minutos).
 *
 * Multi-tenant: se aplica a master y a cada BD tenant que tenga la tabla
 * `configuracion`.
 */

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

function fixCol(PDO $db, string $label): void {
    try {
        $check = $db->query("SHOW TABLES LIKE 'configuracion'");
        if (!$check->fetch()) { echo "[{$label}] sin tabla configuracion — omitido.\n"; return; }
        $col = $db->query("SHOW COLUMNS FROM configuracion LIKE 'seg_timeout_sesion'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $db->exec("ALTER TABLE configuracion ADD COLUMN seg_timeout_sesion SMALLINT UNSIGNED NULL DEFAULT 60");
            echo "[{$label}] columna creada (SMALLINT UNSIGNED).\n";
            return;
        }
        $type = strtolower($col['Type']);
        if (strpos($type, 'smallint') === 0) {
            echo "[{$label}] ya es SMALLINT — ok.\n";
            return;
        }
        $db->exec("ALTER TABLE configuracion MODIFY COLUMN seg_timeout_sesion SMALLINT UNSIGNED NULL DEFAULT 60");
        echo "[{$label}] migrado de '{$col['Type']}' → SMALLINT UNSIGNED.\n";

        // Si los valores existentes parecen ser flags 0/1 (porque era boolean),
        // restaurarlos al default 60. Cualquier valor >=5 se respeta.
        $db->exec("UPDATE configuracion SET seg_timeout_sesion = 60 WHERE seg_timeout_sesion IS NULL OR seg_timeout_sesion < 5");
        echo "[{$label}] valores < 5 normalizados a 60.\n";
    } catch (Throwable $e) {
        echo "[{$label}] ERROR: " . $e->getMessage() . "\n";
    }
}

$master = Database::getInstance();
fixCol($master, 'master');

try {
    $rows = $master->query("SELECT id, COALESCE(db_name,'') AS db_name FROM instituciones")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        try {
            $tdb = Database::getTenant((int)$row['id']);
            $label = "inst={$row['id']} (" . ($row['db_name'] ?: 'master compartido') . ")";
            fixCol($tdb, $label);
        } catch (Throwable $e) {
            echo "[inst={$row['id']}] conexión: " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "No se pudieron listar instituciones: " . $e->getMessage() . "\n";
}

echo "Migración completada.\n";
