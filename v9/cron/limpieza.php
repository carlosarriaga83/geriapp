<?php
/**
 * GeriApp — Cron: Limpieza de sesiones y purga de logs
 *
 * §1.7 Limpieza de sesiones inactivas
 * §4.5 Retención y purga programada de logs
 *
 * Recomendado: ejecutar cada hora
 *   0 * * * * /usr/bin/php /path/to/v8/cron/limpieza.php >> /var/log/geriapp_limpieza.log 2>&1
 */

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !defined('CRON_ALLOW_HTTP')) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../db/Database.php';

$db = Database::getInstance();
$now = date('Y-m-d H:i:s');

echo "[{$now}] limpieza.php — inicio\n";

// ═══════════════════════════════════════════════════════════════════
// §1.7 Limpiar sesiones inactivas (>30 min sin actividad)
// ═══════════════════════════════════════════════════════════════════
$timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
$cutoff  = date('Y-m-d H:i:s', time() - $timeout);

try {
    $stmt = $db->prepare("DELETE FROM sesiones_activas WHERE ultimo_acceso < ?");
    $stmt->execute([$cutoff]);
    $deleted = $stmt->rowCount();
    echo "  [sesiones] Eliminadas {$deleted} sesiones inactivas (antes de {$cutoff})\n";
} catch (\Throwable $e) {
    echo "  [sesiones] Error: " . $e->getMessage() . "\n";
}

// ═══════════════════════════════════════════════════════════════════
// §4.5 Purga de logs antiguos — retención configurable
// ═══════════════════════════════════════════════════════════════════

// Días de retención por tabla
$retenciones = [
    'logs_sistema'    => 180,  // 6 meses
    'login_attempts'  => 90,   // 3 meses
    'notificaciones_log' => 90,
];

foreach ($retenciones as $tabla => $dias) {
    $limite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
    $col    = 'creado_at'; // columna de timestamp

    try {
        // Verificar que la tabla existe
        $check = $db->query("SHOW TABLES LIKE '{$tabla}'");
        if (!$check->fetch()) {
            echo "  [purga] Tabla {$tabla} no existe, saltando\n";
            continue;
        }

        // Verificar columna
        $cols = $db->query("SHOW COLUMNS FROM `{$tabla}` LIKE '{$col}'");
        if (!$cols->fetch()) {
            // Intentar con 'created_at' o 'fecha'
            foreach (['created_at', 'fecha', 'timestamp'] as $alt) {
                $cols2 = $db->query("SHOW COLUMNS FROM `{$tabla}` LIKE '{$alt}'");
                if ($cols2->fetch()) { $col = $alt; break; }
            }
        }

        $stmt = $db->prepare("DELETE FROM `{$tabla}` WHERE `{$col}` < ?");
        $stmt->execute([$limite]);
        $purged = $stmt->rowCount();
        echo "  [purga] {$tabla}: eliminados {$purged} registros anteriores a {$limite} ({$dias}d retención)\n";
    } catch (\Throwable $e) {
        echo "  [purga] {$tabla} error: " . $e->getMessage() . "\n";
    }
}

// ═══════════════════════════════════════════════════════════════════
// Purgar en TODAS las BDs tenant
// ═══════════════════════════════════════════════════════════════════
try {
    $prefix = defined('DB_TENANT_PREFIX') ? DB_TENANT_PREFIX : 'geriapp_i';
    $stmt   = $db->query("SHOW DATABASES LIKE '{$prefix}%'");
    $dbs    = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($dbs as $dbName) {
        // Validar nombre (§6.6)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) continue;

        try {
            $tenant = Database::connectTenant($dbName);

            // Sesiones inactivas del tenant
            try {
                $s = $tenant->prepare("DELETE FROM sesiones_activas WHERE ultimo_acceso < ?");
                $s->execute([$cutoff]);
                $d = $s->rowCount();
                if ($d) echo "  [{$dbName}] sesiones: {$d} eliminadas\n";
            } catch (\Throwable $e) { /* tabla puede no existir */ }

            // Purga de logs del tenant
            foreach ($retenciones as $tabla => $dias) {
                $limite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
                try {
                    $s = $tenant->prepare("DELETE FROM `{$tabla}` WHERE `creado_at` < ?");
                    $s->execute([$limite]);
                    $d = $s->rowCount();
                    if ($d) echo "  [{$dbName}] {$tabla}: {$d} purgados\n";
                } catch (\Throwable $e) { /* tabla puede no existir */ }
            }
        } catch (\Throwable $e) {
            echo "  [{$dbName}] Error conectando: " . $e->getMessage() . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "  [tenants] Error listando BDs: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] limpieza.php — fin\n";
