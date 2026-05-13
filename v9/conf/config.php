<?php
// =====================================================
// GeriApp — Configuración global
// =====================================================

// Auto-detected base path from the filesystem — no need to update when migrating versions.
// Works for both HTTP and HTTPS, safe behind reverse proxies.
// Uses parent dir (dirname) because config.php lives inside conf/.
$_baseDir  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$_docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
define('BASE_URL', $_docRoot ? str_replace($_docRoot, '', $_baseDir) : '');
unset($_baseDir, $_docRoot);
define('ASSETS_URL', BASE_URL . '/assets');  
define('APP_NAME', 'GeriApp');


define('FCM_PROJECT_ID', 'geriapp-f8815');
define('FCM_CREDENTIALS_PATH', dirname(__DIR__) . '/secretos/firebase-auth.json');

// Full public URL (protocol + host + base path).
// Used ONLY server-side (email links, etc.) where a full URL is required.
// Respects X-Forwarded-Proto set by reverse proxies / tunnels (e.g. pellu.myvnc.com).
function app_public_url(): string {
    $proto = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $proto . '://' . $host . BASE_URL;
}
define('APP_VERSION', '1.53.26');

// ── i18n ─────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/lang/i18n.php';

// ── Redirigir HTTPS → HTTP en dominios no-producción ─────────────────────
// Si Chrome (HSTS / HTTPS-First) fuerza HTTPS en un tunnel o localhost,
// redirigimos a HTTP para evitar ERR_SSL_PROTOCOL_ERROR.
$_isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
             && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$_prodHosts = ['geriapp.prepenv.com', 'testgeriapp.prepenv.com', 'miscuidados.com', 'www.miscuidados.com'];
if ($_isHttps && !in_array($_SERVER['HTTP_HOST'] ?? '', $_prodHosts, true)) {
    header('Location: http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
    exit;
}

// Dirección física global — se muestra en el pie de todos los correos
// cuando la institución no tiene una dirección registrada.
define('APP_ADDRESS', 'Calle Rocío 197, Col. Jardines del Pedregal, Álvaro Obregón, Ciudad de México, C.P. 01900');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// ── Sesión segura (PHIPA §1.8 / §1.10) ──────────────────────────────────
define('SESSION_TIMEOUT', 1800); // 30 min inactividad

if (session_status() === PHP_SESSION_NONE) {
    // Solo establecer session path en XAMPP Windows
    if (PHP_OS_FAMILY === 'Windows') {
        $sessionPath = 'C:/xampp/tmp';
        if (is_dir($sessionPath)) {
            session_save_path($sessionPath);
        }
    }

    // §1.4 Strict mode: rechazar session IDs no generados por el servidor
    ini_set('session.use_strict_mode', '1');
    // §1.5 Solo cookies: no aceptar session IDs por URL
    ini_set('session.use_only_cookies', '1');
    // §1.6 Entropía: 48 bytes = 96 hex chars para session ID
    ini_set('session.sid_length', '96');
    ini_set('session.sid_bits_per_character', '5');

    // §1.8 Cookie flags seguros: httponly + samesite + secure (prod)
    $_isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
                  && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // §1.8 Capacitor iOS envía origin capacitor://localhost — necesita SameSite=None
    $_sameSite = 'Lax';
    if (!empty($_SERVER['HTTP_ORIGIN']) && str_starts_with($_SERVER['HTTP_ORIGIN'], 'capacitor://')) {
        $_sameSite = 'None';
        $_isSecure = true; // SameSite=None requiere Secure
    }

    session_set_cookie_params([
        'lifetime' => 0,              // cookie de sesión (se cierra al cerrar navegador)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $_isSecure,      // solo HTTPS en prod
        'httponly'  => true,           // inaccesible a JS
        'samesite' => $_sameSite,      // None para Capacitor, Lax para web
    ]);
    // PHP GC: usamos un techo generoso (24h) para que el recolector NO
    // borre archivos de sesión antes de que la app aplique su propio
    // timeout configurado por institución (`seg_timeout_sesion`). Si
    // dejáramos esto en SESSION_TIMEOUT (30min), un GC paralelo borraría
    // la sesión a los 30min aunque el cfg diga 60+ y el usuario sería
    // expulsado antes de tiempo.
    ini_set('session.gc_maxlifetime', 86400);

    session_start();

    // §1.10 Timeout por inactividad (uses institution config if set in session)
    $effectiveTimeout = $_SESSION['_cfg_session_timeout'] ?? SESSION_TIMEOUT;
    if (isset($_SESSION['_last_activity'])
        && (time() - $_SESSION['_last_activity']) > $effectiveTimeout
    ) {
        // Sesión expirada → destruir
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start(); // reiniciar limpio para la página de login
        // Marcar la nueva sesión como "recién expirada" para que middleware
        // pueda mostrar el aviso correspondiente en el login.
        $_SESSION['_just_expired'] = 1;
    }
    $_SESSION['_last_activity'] = time();
}

// ── §7.1-7.3 Manejo centralizado de errores (PHIPA tabla 7) ──────────────
// Directorio de logs de la aplicación
$_errorLogDir = dirname(__DIR__) . '/logs';
if (!is_dir($_errorLogDir)) { @mkdir($_errorLogDir, 0750, true); }
define('ERROR_LOG_PATH', $_errorLogDir . '/php_errors.log');

ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_PATH);
error_reporting(E_ALL);

// §7.2 display_errors: OFF por defecto, controlable desde Config > Logs > Errores
$_displayFlag = dirname(__DIR__) . '/conf/.display_errors';
ini_set('display_errors', file_exists($_displayFlag) ? '1' : '0');

// §7.1 Custom error handler: mensaje genérico al usuario, detalle al log
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    $map = [E_ERROR => 'ERROR', E_WARNING => 'WARNING', E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE', E_STRICT => 'STRICT', E_DEPRECATED => 'DEPRECATED',
            E_USER_ERROR => 'USER_ERROR', E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE', E_USER_DEPRECATED => 'USER_DEPRECATED'];
    $level = $map[$severity] ?? 'UNKNOWN';
    error_log("[{$level}] {$message} in {$file}:{$line}");
    if ($severity === E_USER_ERROR) throw new ErrorException($message, 0, $severity, $file, $line);
    return true;
});

// §7.1 Custom exception handler: genérico al usuario, traza completa al log
set_exception_handler(function (\Throwable $e): void {
    error_log("[EXCEPTION] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
    if (!headers_sent()) http_response_code(500);
    if (php_sapi_name() !== 'cli') {
        // Si es API (JSON), responder JSON genérico
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Error</title>'
               . '<style>body{font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5}'
               . '.box{text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.1)}'
               . 'h1{font-size:1.25rem;color:#333}p{color:#666;font-size:.875rem}</style></head>'
               . '<body><div class="box"><h1>Error interno</h1><p>Ha ocurrido un error inesperado. Contacte al administrador.</p></div></body></html>';
        }
    }
    exit(1);
});

// Charset — force UTF-8 in HTTP response header
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// ── Rol activo (desde sesión real) ──────────────────────────────────────────
$_VALID_ROLES = ['superadmin', 'admin', 'enfermero', 'medico', 'familiar'];
define('DEV_ROLE', $_SESSION['user_rol'] ?? 'guest');

// ── §1.9 CSRF Token ─────────────────────────────────────────────────────────
/**
 * Genera u obtiene el token CSRF de la sesión actual.
 * Token válido durante toda la sesión (single-token-per-session pattern).
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Valida un token CSRF contra el de la sesión.
 */
function csrf_validate(string $token): bool
{
    return !empty($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Devuelve un <input type="hidden"> con el CSRF token para formularios.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Devuelve un <meta> tag con el CSRF token para JS fetch.
 */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token()) . '">';
}

// ── §1.11 Validación de contraseña ──────────────────────────────────────────
/**
 * Valida la complejidad de una contraseña.
 * Retorna null si es válida, string con error si no cumple.
 * @param int $minLen Longitud mínima (default 8, configurable por institución).
 */
function validate_password_strength(string $pass, int $minLen = 8): ?string
{
    $minLen = max(4, $minLen);
    if (strlen($pass) < $minLen) {
        return "La contraseña debe tener al menos {$minLen} caracteres.";
    }
    if (!preg_match('/[A-Z]/', $pass)) {
        return 'La contraseña debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-z]/', $pass)) {
        return 'La contraseña debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/\d/', $pass)) {
        return 'La contraseña debe incluir al menos un número.';
    }
    if (!preg_match('/[\W_]/', $pass)) {
        return 'La contraseña debe incluir al menos un carácter especial (!@#$%&*...).';
    }
    return null; // Válida
}

/**
 * Verifica si una contraseña en texto plano cumple con las nuevas reglas de complejidad.
 * Se usa en login para marcar usuarios existentes que necesitan cambiar su contraseña.
 */
function password_needs_upgrade(string $pass): bool
{
    return validate_password_strength($pass) !== null;
}
