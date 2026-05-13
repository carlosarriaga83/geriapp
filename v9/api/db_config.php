<?php
/**
 * GeriApp — API /api/db_config.php
 *
 * GET  → leer credenciales DB actuales
 * POST → guardar credenciales DB (reescribe config.db.php)
 * PUT  ?action=test → probar conexión con credenciales dadas
 *
 * Roles: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin']);

$configFile = dirname(__DIR__) . '/conf/config.db.php';

// ─────────────────────────────────────────────────────────────────────────────
// GET — leer config actual  |  GET ?action=status → métricas de la BD
// ─────────────────────────────────────────────────────────────────────────────
if (api_method() === 'GET') {

    if (($_GET['action'] ?? '') === 'status') {
        try {
            $pdo = Database::getInstance();

            $ver    = $pdo->query('SELECT VERSION()')->fetchColumn();
            $upRaw  = $pdo->query("SHOW STATUS LIKE 'Uptime'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? 0;
            $qRaw   = $pdo->query("SHOW STATUS LIKE 'Questions'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? 0;
            $conn   = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? 0;
            $maxCon = $pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? 0;
            $maxUsr = $pdo->query("SHOW VARIABLES LIKE 'max_user_connections'")->fetch(PDO::FETCH_ASSOC)['Value'] ?? 0;

            // Queries per hour (approx)
            $uptimeH   = max((int) $upRaw / 3600, 0.001);
            $queriesH  = round((int) $qRaw / $uptimeH);

            // DB size
            $stmt = $pdo->prepare("SELECT SUM(data_length + index_length) AS sz, COUNT(*) AS tbl FROM information_schema.TABLES WHERE table_schema = ?");
            $stmt->execute([DB_NAME]);
            $row     = $stmt->fetch(PDO::FETCH_ASSOC);
            $sizeMB  = round(($row['sz'] ?? 0) / (1024 * 1024), 2);
            $tables  = (int) ($row['tbl'] ?? 0);

            api_ok([
                'connected'      => true,
                'version'        => $ver,
                'uptime_seconds' => (int) $upRaw,
                'queries_total'  => (int) $qRaw,
                'queries_hour'   => $queriesH,
                'threads'        => (int) $conn,
                'max_connections' => (int) $maxCon,
                'max_user_connections' => (int) $maxUsr,
                'db_size_mb'     => $sizeMB,
                'tables'         => $tables,
                'db_name'        => DB_NAME,
                'host'           => DB_HOST,
            ]);
        } catch (Exception $e) {
            api_ok(['connected' => false, 'error' => $e->getMessage()]);
        }
    }

    api_ok([
        'host'          => DB_HOST,
        'port'          => DB_PORT,
        'name'          => DB_NAME,
        'user'          => DB_USER,
        'pass'          => DB_PASS,
        'charset'       => DB_CHARSET,
        'tenant_prefix' => DB_TENANT_PREFIX,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — probar conexión
// ─────────────────────────────────────────────────────────────────────────────
if (api_method() === 'PUT') {
    $body = api_body();
    $host = trim($body['host'] ?? DB_HOST);
    $port = trim($body['port'] ?? DB_PORT);
    $name = trim($body['name'] ?? DB_NAME);
    $user = trim($body['user'] ?? DB_USER);
    $pass = ($body['pass'] ?? '') === '••••••••' ? DB_PASS : ($body['pass'] ?? '');

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT    => 5,
        ]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $pdo = null;

        // §4.4 Registrar prueba de conexión
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'db_probar_conexion',
            'modulo'         => 'configuracion',
            'detalle'        => "Host: {$host}, DB: {$name}, User: {$user} — OK (MySQL {$ver})",
        ]);

        api_ok(['version' => $ver], "Conexión exitosa — MySQL {$ver}");
    } catch (PDOException $e) {
        api_error('Error de conexión: ' . $e->getMessage(), 502);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — guardar credenciales
// ─────────────────────────────────────────────────────────────────────────────
if (api_method() === 'POST') {
    $body = api_body();

    $host   = trim($body['host']   ?? DB_HOST);
    $port   = trim($body['port']   ?? DB_PORT);
    $name   = trim($body['name']   ?? DB_NAME);
    $user   = trim($body['user']   ?? DB_USER);
    $pass   = ($body['pass'] ?? '') === '••••••••' ? DB_PASS : ($body['pass'] ?? '');
    $charset = trim($body['charset'] ?? DB_CHARSET);
    $prefix  = trim($body['tenant_prefix'] ?? DB_TENANT_PREFIX);

    // Validate required fields
    if (!$host || !$name || !$user) {
        api_error('Host, nombre de BD y usuario son requeridos', 422);
    }

    // Test connection before saving
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo = null;
    } catch (PDOException $e) {
        api_error('No se pudo conectar con las credenciales proporcionadas: ' . $e->getMessage(), 502);
    }

    // Generate config file content
    $escapedPass = addcslashes($pass, "'\\");
    $content = <<<PHP
<?php
/**
 * GeriApp — Configuración de conexión a la base de datos
 *
 * Auto-detecta el entorno:
 *   Windows  → XAMPP local
 *   Linux    → Hostinger producción
 *
 * No necesitas modificar nada al subir/bajar archivos.
 */

\$isLocal = (PHP_OS_FAMILY === 'Windows');

if (\$isLocal) {
    // ── XAMPP local (Windows) ──────────────────────────────────────────────
    define('DB_HOST',   '{$host}');
    define('DB_PORT',   '{$port}');
    define('DB_NAME',   '{$name}');
    define('DB_USER',   '{$user}');
    define('DB_PASS',   '{$escapedPass}');
} else {
    // ── Hostinger producción (Linux) ──────────────────────────────────────
    define('DB_HOST',   '{$host}');
    define('DB_PORT',   '{$port}');
    define('DB_NAME',   '{$name}');
    define('DB_USER',   '{$user}');
    define('DB_PASS',   '{$escapedPass}');
}

// ── Comunes (ambos entornos) ──────────────────────────────────────────────
define('DB_MASTER_NAME',  DB_NAME);
define('DB_CHARSET',      '{$charset}');

/**
 * Prefijo para BDs de tenant independientes.
 * Nombre generado: DB_TENANT_PREFIX . \$institucion_id
 * Ejemplo: "geriapp_i3" para la institución con id=3
 */
define('DB_TENANT_PREFIX', '{$prefix}');

PHP;

    if (!is_writable($configFile)) {
        api_error('El archivo de configuración no tiene permisos de escritura', 500);
    }

    $ok = file_put_contents($configFile, $content);
    if ($ok === false) {
        api_error('Error al escribir el archivo de configuración', 500);
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'db_actualizar_config',
        'modulo'         => 'configuracion',
        'detalle'        => "Host: {$host}, DB: {$name}, User: {$user}",
    ]);

    api_ok(null, 'Configuración de base de datos guardada');
}

api_error('Método no permitido', 405);
