<?php
/**
 * GeriApp — Database (multi-tenant PDO manager)
 *
 * Uso:
 *   $master = Database::getMaster();          // BD central (planes, instituciones, usuarios…)
 *   $tenant = Database::getTenant($inst_id);  // BD del tenant activo
 *   Database::getInstance();                  // alias de getMaster() — retrocompatibilidad
 *
 * Arquitectura:
 *   - getMaster() siempre apunta a DB_MASTER_NAME.
 *   - getTenant($id) consulta instituciones.db_name en la BD master:
 *       · Si db_name es NULL → devuelve la misma conexión master (modo BD compartida).
 *       · Si db_name está definido → abre una conexión independiente a esa BD.
 *   - createTenantDB($db_name) crea la BD nueva y ejecuta el schema tenant.
 */

require_once dirname(__DIR__) . '/conf/config.db.php';

class Database
{
    /** Conexión a la BD master */
    private static ?PDO $master = null;

    /** Cache de conexiones tenant: [inst_id => PDO] */
    private static array $tenants = [];

    /** Cache de db_name por inst_id: [inst_id => string|null] */
    private static array $dbNameCache = [];

    // ─────────────────────────────────────────────────────────────────────────
    // Conexión master
    // ─────────────────────────────────────────────────────────────────────────

    /** Devuelve la instancia PDO de la BD master */
    public static function getMaster(): PDO
    {
        if (self::$master === null) {
            self::$master = self::connect(DB_MASTER_NAME);
        }
        return self::$master;
    }

    /** Alias de getMaster() — retrocompatibilidad total */
    public static function getInstance(): PDO
    {
        return self::getMaster();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Conexión tenant
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve la conexión PDO para la institución dada.
     *
     * - Si la institución tiene db_name definido → conexión a esa BD separada.
     * - Si db_name es NULL (modo compartido / legacy) → devuelve la BD master.
     *
     * @param int $instId  ID de la institución (de la sesión activa)
     */
    public static function getTenant(int $instId): PDO
    {
        if ($instId <= 0) {
            return self::getMaster();
        }

        // Ya resolvimos esta institución antes
        if (isset(self::$tenants[$instId])) {
            return self::$tenants[$instId];
        }

        $dbName = self::resolveTenantDbName($instId);

        if ($dbName === null || $dbName === DB_MASTER_NAME) {
            // BD compartida: reutilizar la conexión master
            self::$tenants[$instId] = self::getMaster();
        } else {
            // BD separada
            self::$tenants[$instId] = self::connect($dbName);
        }

        return self::$tenants[$instId];
    }

    /**
     * Limpia el cache de una institución concreta.
     * Llamar después de un createTenantDB() para que getTenant() reabra la conexión.
     */
    public static function clearTenantCache(int $instId): void
    {
        unset(self::$tenants[$instId], self::$dbNameCache[$instId]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Provisioning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea una BD de tenant y ejecuta el schema tenant.
     *
     * @param  string $dbName   Nombre de la BD a crear (ej. "geriapp_i5")
     * @return bool             true si se creó correctamente
     * @throws RuntimeException si falla la creación o el schema
     */
    public static function createTenantDB(string $dbName): bool
    {
        // Validar nombre de BD: solo alfanumérico + underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException("Nombre de BD inválido: {$dbName}");
        }

        $master = self::getMaster();

        // 1. Crear la BD
        $master->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}`
             DEFAULT CHARACTER SET utf8mb4
             DEFAULT COLLATE utf8mb4_unicode_ci"
        );

        // 2. Conectar a la BD recién creada para ejecutar el schema
        $tenant = self::connect($dbName);

        // 3. Leer y ejecutar schema_tenant.sql
        $schemaFile = __DIR__ . '/schema_tenant.sql';
        if (!file_exists($schemaFile)) {
            throw new RuntimeException("No se encontró schema_tenant.sql en {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        // Ejecutar sentencia por sentencia (PDO no acepta múltiples a la vez)
        foreach (self::splitSql($sql) as $stmt) {
            if (trim($stmt) !== '') {
                $tenant->exec($stmt);
            }
        }

        return true;
    }

    /**
     * Genera el db_name estándar para una institución.
     * Ej.: instId=5 → "geriapp_i5"
     */
    public static function tenantDbName(int $instId): string
    {
        return DB_TENANT_PREFIX . $instId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────────

    /** Abre una conexión PDO a la BD indicada */
    private static function connect(string $dbName): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, $dbName, DB_CHARSET
        );
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        // §2.4 SSL en producción (host remoto, no localhost)
        if (defined('DB_SSL_CA') && DB_SSL_CA !== '' && DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1') {
            $opts[PDO::MYSQL_ATTR_SSL_CA]                = DB_SSL_CA;
            $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            self::recordConnectionMetric($dbName);
            return $pdo;
        } catch (PDOException $e) {
            // Re-lanzar sin exponer credenciales en el mensaje
            error_log('[DB] Connection error: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos');
        }
    }

    public static function getConnectionMetrics(int $hoursBack = 0): array
    {
        $hoursBack = max(0, $hoursBack);
        $hour = date('YmdH', time() - ($hoursBack * 3600));
        $file = self::connectionMetricDir() . "/db_connections_{$hour}.json";
        if (!is_readable($file)) {
            return [
                'hour' => $hour,
                'total' => 0,
                'by_db' => [],
                'by_user' => [],
                'first_seen' => null,
                'last_seen' => null,
            ];
        }

        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) $data = [];
        return [
            'hour' => $hour,
            'total' => (int)($data['total'] ?? 0),
            'by_db' => is_array($data['by_db'] ?? null) ? $data['by_db'] : [],
            'by_user' => is_array($data['by_user'] ?? null) ? $data['by_user'] : [],
            'first_seen' => $data['first_seen'] ?? null,
            'last_seen' => $data['last_seen'] ?? null,
        ];
    }

    public static function getConnectionMetricHistory(int $hours = 24): array
    {
        $hours = max(1, min(168, $hours));
        $history = [];
        for ($i = $hours - 1; $i >= 0; $i--) {
            $history[] = self::getConnectionMetrics($i);
        }
        return $history;
    }

    private static function recordConnectionMetric(string $dbName): void
    {
        $dir = self::connectionMetricDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return;

        $hour = date('YmdH');
        $now = date('c');
        $file = $dir . "/db_connections_{$hour}.json";
        $fh = @fopen($file, 'c+');
        if (!$fh) return;

        try {
            if (!@flock($fh, LOCK_EX)) return;
            $raw = stream_get_contents($fh);
            $data = $raw ? json_decode($raw, true) : [];
            if (!is_array($data)) $data = [];
            $data['hour'] = $hour;
            $data['total'] = (int)($data['total'] ?? 0) + 1;
            $data['first_seen'] = $data['first_seen'] ?? $now;
            $data['last_seen'] = $now;
            if (!isset($data['by_db']) || !is_array($data['by_db'])) $data['by_db'] = [];
            $data['by_db'][$dbName] = (int)($data['by_db'][$dbName] ?? 0) + 1;
            if (!isset($data['by_user']) || !is_array($data['by_user'])) $data['by_user'] = [];
            if (!$data['by_user'] && (int)$data['total'] > 1) {
                $data['by_user'][DB_USER] = (int)$data['total'] - 1;
            }
            $data['by_user'][DB_USER] = (int)($data['by_user'][DB_USER] ?? 0) + 1;

            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_SLASHES));
        } finally {
            @flock($fh, LOCK_UN);
            @fclose($fh);
        }
    }

    private static function connectionMetricDir(): string
    {
        return dirname(__DIR__) . '/uploads/_resource_metrics';
    }

    /**
     * Consulta la BD master para saber qué db_name tiene una institución.
     * Retorna NULL si la institución usa la BD master (compartida).
     */
    private static function resolveTenantDbName(int $instId): ?string
    {
        if (!array_key_exists($instId, self::$dbNameCache)) {
            $stmt = self::getMaster()->prepare(
                "SELECT db_name FROM instituciones WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$instId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            self::$dbNameCache[$instId] = ($row && !empty($row['db_name']))
                ? $row['db_name']
                : null;
        }
        return self::$dbNameCache[$instId];
    }

    /**
     * Divide un bloque SQL en sentencias individuales.
     * Maneja comentarios de línea (-- y #) y delimitadores ;.
     */
    private static function splitSql(string $sql): array
    {
        $stmts    = [];
        $current  = '';
        $lines    = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Ignorar comentarios de línea
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $stmts[] = trim($current);
                $current = '';
            }
        }
        if (trim($current) !== '') {
            $stmts[] = trim($current);
        }
        return $stmts;
    }

    /** Evitar instanciación / clonación */
    private function __construct() {}
    private function __clone()    {}
}
