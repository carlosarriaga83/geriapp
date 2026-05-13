<?php
/**
 * GeriApp — Cron de backup automatizado (PHIPA §8.1 / §8.2)
 *
 * Uso:
 *   php cron/backup.php                    # Backup de todas las instituciones
 *   php cron/backup.php --inst=3           # Solo institución ID 3
 *   php cron/backup.php --encrypt          # Forzar cifrado
 *   php cron/backup.php --no-encrypt       # Sin cifrado
 *
 * Cron sugerido (diario a las 03:00):
 *   0 3 * * * /usr/bin/php /path/to/v8/cron/backup.php >> /path/to/v8/logs/backup_cron.log 2>&1
 *
 * Retención: elimina backups más antiguos que BACKUP_RETENTION_DAYS.
 * Cifrado (§8.2): AES-256-CBC con clave de conf/.env (BACKUP_ENCRYPTION_KEY).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo ejecución CLI');
}

require_once dirname(__DIR__) . '/conf/config.php';
require_once dirname(__DIR__) . '/conf/config.db.php';
require_once dirname(__DIR__) . '/db/Database.php';
require_once dirname(__DIR__) . '/db/models/Log.php';

// ── Configuración ──────────────────────────────────────────────────────
define('BACKUP_DIR',            dirname(__DIR__) . '/backups');
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_CIPHER',         'aes-256-cbc');

// Leer clave de cifrado desde .env si existe
$_envFile = dirname(__DIR__) . '/conf/.env';
$_encKey  = '';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (preg_match('/^BACKUP_ENCRYPTION_KEY\s*=\s*(.+)$/', trim($line), $m)) {
            $_encKey = trim($m[1], '"\'');
            break;
        }
    }
}

// CLI args
$args     = getopt('', ['inst:', 'encrypt', 'no-encrypt']);
$onlyInst = isset($args['inst']) ? (int)$args['inst'] : null;
$encrypt  = isset($args['encrypt']) ? true : (isset($args['no-encrypt']) ? false : !empty($_encKey));

// Crear directorio de backups
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0750, true);
}
// Proteger con .htaccess
if (!file_exists(BACKUP_DIR . '/.htaccess')) {
    file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\n");
}

$db = Database::getInstance();
$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Iniciando backup automatizado…\n";

// ── Obtener instituciones ──────────────────────────────────────────────
$sql = "SELECT id, nombre FROM instituciones WHERE estado = 'activo'";
$params = [];
if ($onlyInst) {
    $sql .= " AND id = ?";
    $params[] = $onlyInst;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($instituciones)) {
    echo "  No se encontraron instituciones.\n";
    exit(0);
}

$backupCount = 0;
$errorCount  = 0;

foreach ($instituciones as $inst) {
    $instId     = (int)$inst['id'];
    $instNombre = $inst['nombre'];
    echo "  [{$instId}] {$instNombre}… ";

    try {
        $sqlDump = generateBackupSQL($db, $instId, $instNombre);

        $filename = 'geriapp_backup_inst' . $instId . '_' . date('Ymd_His') . '.sql';
        $filepath = BACKUP_DIR . '/' . $filename;

        if ($encrypt && !empty($_encKey)) {
            // §8.2 Cifrado AES-256-CBC
            $iv         = openssl_random_pseudo_bytes(openssl_cipher_iv_length(BACKUP_CIPHER));
            $encrypted  = openssl_encrypt($sqlDump, BACKUP_CIPHER, $_encKey, OPENSSL_RAW_DATA, $iv);
            $fileData   = $iv . $encrypted;
            $filepath  .= '.enc';
            $filename  .= '.enc';
            file_put_contents($filepath, $fileData);
        } else {
            // Sin cifrado — comprimir con gzip si disponible
            if (function_exists('gzencode')) {
                $filepath .= '.gz';
                $filename .= '.gz';
                file_put_contents($filepath, gzencode($sqlDump, 9));
            } else {
                file_put_contents($filepath, $sqlDump);
            }
        }

        $sizeMb = round(filesize($filepath) / 1048576, 2);
        echo "OK ({$filename}, {$sizeMb} MB)\n";
        $backupCount++;

        // Registrar en logs
        Log::registrar([
            'usuario_id'     => null,
            'institucion_id' => $instId,
            'accion'         => 'backup_crear_automatico',
            'modulo'         => 'backup',
            'detalle'        => "Backup generado: {$filename} ({$sizeMb} MB)" . ($encrypt ? ' [cifrado]' : ''),
            'estado'         => 'ok',
        ]);
    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        $errorCount++;
        Log::registrar([
            'usuario_id'     => null,
            'institucion_id' => $instId,
            'accion'         => 'backup_fallar',
            'modulo'         => 'backup',
            'detalle'        => "Error al generar backup: {$e->getMessage()}",
            'estado'         => 'error',
        ]);
    }
}

// ── Purga de backups antiguos ──────────────────────────────────────────
$purged   = 0;
$cutoff   = time() - (BACKUP_RETENTION_DAYS * 86400);
$iterator = new DirectoryIterator(BACKUP_DIR);
foreach ($iterator as $file) {
    if ($file->isDot() || $file->isDir()) continue;
    if ($file->getFilename() === '.htaccess') continue;
    if ($file->getMTime() < $cutoff) {
        unlink($file->getPathname());
        $purged++;
    }
}

$ts2 = date('Y-m-d H:i:s');
echo "[{$ts2}] Completado: {$backupCount} backups, {$errorCount} errores, {$purged} archivos antiguos purgados.\n";

// ═══════════════════════════════════════════════════════════════════════
// Función que genera el SQL (replicada de api/configuracion.php PATCH backup)
// ═══════════════════════════════════════════════════════════════════════
function generateBackupSQL(PDO $db, int $instId, string $instNombre): string
{
    $dbName = DB_NAME;

    $tableDefs = [
        'planes' => [
            'query'  => "SELECT p.* FROM planes p JOIN instituciones i ON i.plan_id = p.id WHERE i.id = ?",
            'params' => [$instId],
        ],
        'instituciones' => [
            'query'  => "SELECT * FROM instituciones WHERE id = ?",
            'params' => [$instId],
        ],
        'usuarios' => [
            'query'  => "SELECT * FROM usuarios WHERE institucion_id = ?",
            'params' => [$instId],
        ],
        'invitaciones' => [
            'query'  => "SELECT * FROM invitaciones WHERE institucion_id = ?",
            'params' => [$instId],
        ],
        'residentes' => [
            'query'  => "SELECT * FROM residentes WHERE institucion_id = ?",
            'params' => [$instId],
        ],
        'prescripciones' => [
            'query'  => "SELECT * FROM prescripciones WHERE institucion_id = ?",
            'params' => [$instId],
        ],
        // bitacora_* and historial_* tables dropped in v1.26.0
        'configuracion' => [
            'query'  => "SELECT * FROM configuracion WHERE institucion_id = ?",
            'params' => [$instId],
        ],
        'logs_sistema' => [
            'query'  => "SELECT * FROM logs_sistema WHERE institucion_id = ? ORDER BY id DESC LIMIT 5000",
            'params' => [$instId],
        ],
    ];

    $sql  = "-- ============================================================\n";
    $sql .= "-- GeriApp — Respaldo automático\n";
    $sql .= "-- Institución : {$instNombre} (ID: {$instId})\n";
    $sql .= "-- Base de datos: {$dbName}\n";
    $sql .= "-- Generado el  : " . date('Y-m-d H:i:s') . " (UTC" . date('P') . ")\n";
    $sql .= "-- ============================================================\n\n";
    $sql .= "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;\n";
    $sql .= "USE `{$dbName}`;\n\n";
    $sql .= "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET foreign_key_checks = 0;\nSET sql_mode = 'NO_ENGINE_SUBSTITUTION';\n\n";

    foreach ($tableDefs as $table => $def) {
        $sql .= "-- Tabla: `{$table}`\n";
        try {
            $ddlStmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $ddlRow  = $ddlStmt->fetch(PDO::FETCH_NUM);
            if ($ddlRow) {
                $ddl = preg_replace('/^CREATE TABLE\s+`?' . preg_quote($table, '/') . '`?/i',
                    "CREATE TABLE IF NOT EXISTS `{$table}`", trim($ddlRow[1]));
                $sql .= $ddl . ";\n\n";
            }
        } catch (Exception $e) {
            $sql .= "-- ADVERTENCIA: DDL no disponible para {$table}\n\n";
        }

        try {
            $stmt = $db->prepare($def['query']);
            $stmt->execute($def['params']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) { $sql .= "-- (sin datos)\n\n"; continue; }
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            foreach ($rows as $row) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), array_values($row));
                $sql .= "INSERT IGNORE INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        } catch (Exception $e) {
            $sql .= "-- ERROR: " . $e->getMessage() . "\n\n";
        }
    }

    $sql .= "SET foreign_key_checks = 1;\n-- Fin del respaldo\n";
    return $sql;
}
