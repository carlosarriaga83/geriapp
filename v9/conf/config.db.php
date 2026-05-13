<?php
/**
 * GeriApp — Configuración de conexión a la base de datos
 *
 * Lee credenciales desde conf/.env (fuera de VCS).
 * Fallback: variables de entorno del sistema (Hostinger panel → PHP Settings).
 *
 * §2.3 PHIPA: las credenciales NO se guardan en código fuente.
 */

// ── Cargar .env si existe ─────────────────────────────────────────────────
$_envFile = __DIR__ . '/.env';
if (is_readable($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        // No sobrescribir si ya está en el entorno (Hostinger panel)
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $val;
            putenv("$key=$val");
        }
    }
}

// ── Definir constantes desde entorno ──────────────────────────────────────
define('DB_HOST',          getenv('DB_HOST')          ?: 'localhost');
define('DB_PORT',          getenv('DB_PORT')          ?: '3306');
define('DB_NAME',          getenv('DB_NAME')          ?: 'u124132715_geriapp');
define('DB_USER',          getenv('DB_USER')          ?: 'root');
define('DB_PASS',          getenv('DB_PASS')          ?: '');
define('DB_CHARSET',       getenv('DB_CHARSET')       ?: 'utf8mb4');
define('DB_TENANT_PREFIX', getenv('DB_TENANT_PREFIX') ?: 'geriapp_i');

// SSL certificate path (producción)
define('DB_SSL_CA',        getenv('DB_SSL_CA')        ?: '');

// ── Comunes ───────────────────────────────────────────────────────────────
define('DB_MASTER_NAME', DB_NAME);
