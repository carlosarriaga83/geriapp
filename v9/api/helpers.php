<?php
/**
 * GeriApp — Helpers de API
 *
 * Todos los endpoints hacen:
 *   require_once __DIR__ . '/helpers.php';
 *
 * Esto carga: config, sesión, modelos y los helpers JSON.
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────

// Seguridad: nunca mostrar errores PHP en respuestas API (evita exponer credenciales)
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once dirname(__DIR__) . '/conf/config.php';
require_once dirname(__DIR__) . '/db/models.php';

// Handler global: cualquier excepción no capturada → JSON limpio
set_exception_handler(function (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
    ], JSON_UNESCAPED_UNICODE);
    error_log('[API] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
});

// Warnings/notices → excepción para que el handler las capture
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// ── Autenticación: requiere sesión válida ──────────────────────────────────
function api_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        api_error('No autenticado', 401);
    }
    if (isset($_SESSION['user_estado']) && $_SESSION['user_estado'] !== 'activo') {
        session_destroy();
        api_error('Cuenta inactiva', 403);
    }

    // §1.9 CSRF: validar en métodos mutantes (POST, PUT, DELETE)
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']           // Header (fetch API)
              ?? $_POST['_csrf']                          // Form hidden field
              ?? (json_decode(file_get_contents('php://input') ?: '{}', true)['_csrf'] ?? '');
        if (!csrf_validate($token)) {
            security_alert('csrf_invalido', ['method' => $method, 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
            api_error('Token CSRF inválido. Recarga la página.', 403);
        }
    }

    // §1.11 Verificar si requiere cambio de contraseña
    if (!empty($_SESSION['password_change_required'])) {
        // Permitir solo el endpoint de cambio de contraseña
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, 'api/change_password.php') === false) {
            api_error('Debes cambiar tu contraseña antes de continuar.', 403);
        }
    }

    // Sesión incompleta: recargar desde BD y pivot
    if (!isset($_SESSION['user_rol'])) {
        $user = Usuario::getById((int)$_SESSION['user_id']);
        if (!$user) {
            session_destroy();
            api_error('Sesión inválida', 401);
        }
        $instId = (int)($_SESSION['user_institucion_id'] ?? $user['institucion_id'] ?? 0);
        // Intentar obtener rol desde el pivot
        if ($user['rol'] !== 'superadmin' && $instId) {
            $rolPivot = UsuarioInstitucion::getRol((int)$user['id'], $instId);
            $_SESSION['user_rol'] = $rolPivot ?? $user['rol'];
        } else {
            $_SESSION['user_rol'] = $user['rol'];
        }
        $_SESSION['user_estado']             = $user['estado'];
        $_SESSION['user_nombre']             = $user['nombre'];
        $_SESSION['user_email']              = $user['email'];
        $_SESSION['user_institucion_id']     = $instId ?: null;
        $_SESSION['user_institucion_nombre'] = $user['institucion_nombre'] ?? '';
    }
}

// Variante: requiere rol superadmin
function api_auth_superadmin(): void
{
    api_auth();
    if (($_SESSION['user_rol'] ?? '') !== 'superadmin') {
        api_error('Acceso denegado', 403);
    }
}

// Variante: requiere uno de los roles indicados
function api_auth_roles(array $roles): void
{
    api_auth();
    $current = api_role_storage($_SESSION['user_rol'] ?? '');
    $allowed = api_roles_storage($roles);
    if (!in_array($current, $allowed, true)) {
        api_error('Acceso denegado', 403);
    }
}

function api_role_storage(?string $role): string
{
    $role = trim((string)$role);
    return $role === 'cuidador' ? 'enfermero' : $role;
}

function api_role_public(?string $role): string
{
    $role = trim((string)$role);
    return $role === 'enfermero' ? 'cuidador' : $role;
}

function api_roles_storage(array $roles): array
{
    return array_values(array_unique(array_map('api_role_storage', $roles)));
}

// ── Respuestas JSON ──────────────────────────────────────────────────────────
function api_json(mixed $data, int $status = 200): never
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_ok(mixed $data = null, string $message = 'ok'): never
{
    api_json(['success' => true, 'message' => $message, 'data' => $data]);
}

function api_error(string $message, int $status = 400, mixed $errors = null): never
{
    api_json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
}

// ── Método HTTP ──────────────────────────────────────────────────────────────
function api_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function api_require_method(string ...$allowed): void
{
    if (!in_array(api_method(), $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error('Método no permitido', 405);
    }
}

function api_normalize_phone_mx(?string $phone): string
{
    $raw = trim((string)$phone);
    if ($raw === '') return '';
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    if (str_starts_with($raw, '+')) return '+' . $digits;
    if (strlen($digits) === 10) return '+52' . $digits;
    if (str_starts_with($digits, '52') && strlen($digits) >= 12) return '+' . $digits;
    return $raw;
}

function api_normalize_phone_fields(array $data, array $fields): array
{
    foreach ($fields as $field) {
        if (array_key_exists($field, $data) && is_string($data[$field])) {
            $data[$field] = api_normalize_phone_mx($data[$field]);
        }
    }
    return $data;
}

function api_normalize_contactos_json_phones(mixed $value): mixed
{
    if (!is_string($value) || trim($value) === '') return $value;
    $contacts = json_decode($value, true);
    if (!is_array($contacts)) return $value;
    foreach ($contacts as &$contact) {
        if (!is_array($contact)) continue;
        foreach (['telefono', 'telefono2', 'phone', 'whatsapp'] as $field) {
            if (isset($contact[$field]) && is_string($contact[$field])) {
                $contact[$field] = api_normalize_phone_mx($contact[$field]);
            }
        }
    }
    unset($contact);
    return json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ── Body JSON (para POST/PUT con Content-Type: application/json) ─────────────
function api_body(): array
{
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?? []) : [];
        // También acepta form-data / x-www-form-urlencoded
        if (empty($body)) {
            $body = $_POST;
        }
    }
    return $body;
}

// ── Shortcuts de sesión ───────────────────────────────────────────────────────
function api_user_id(): int      { return (int)($_SESSION['user_id']           ?? 0); }
function api_inst_id(): int      { return (int)($_SESSION['user_institucion_id'] ?? 0); }
function api_rol(): string       { return $_SESSION['user_rol']                 ?? 'guest'; }

// ── Paginación ────────────────────────────────────────────────────────────────
function api_pagination(): array
{
    $limit  = max(1, min(200, (int)($_GET['limit']  ?? 50)));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    return ['limit' => $limit, 'page' => $page, 'offset' => $offset];
}

// ── Sanitizar entero de URL / query string ────────────────────────────────────
function api_int(string $key, ?array $source = null): int
{
    $source ??= $_GET;
    return (int)($source[$key] ?? 0);
}

// ── Verificar que un residente pertenece a la institución en sesión ─────────
function api_assert_residente(int $residente_id): array
{
    $r = Residente::getById($residente_id, api_inst_id());
    if (!$r) api_error('Residente no encontrado', 404);
    return $r;
}

// ── Verificar que un expediente pertenece a la institución en sesión ─────────
// api_assert_expediente removed in v1.26.0 (historial tables dropped)

// ── §6.5 Validación MIME reforzada con finfo ──────────────────────────────────
/**
 * Verifica el MIME type real de un archivo usando finfo (libmagic).
 * Más confiable que mime_content_type() que puede ser spoofeable.
 */
function api_validate_mime(string $tmpPath, array $allowed): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpPath);

    // Fallback si finfo da resultado genérico
    if ($mime === 'application/octet-stream' || !$mime) {
        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
    }

    if (!in_array($mime, $allowed, true)) {
        api_error("Tipo de archivo no permitido ({$mime})", 415);
    }

    return $mime;
}

// ── §4.6 Alertas de seguridad automáticas ─────────────────────────────────────
/**
 * Registra actividad sospechosa en logs_sistema.
 * Si se supera umbral de intentos fallidos, marca alerta crítica.
 */
function security_alert(string $tipo, array $extra = []): void
{
    try {
        $db = \Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $instId = (int)($_SESSION['user_institucion_id'] ?? 0);

        $detalle = json_encode(array_merge(['tipo' => $tipo, 'ip' => $ip], $extra), JSON_UNESCAPED_UNICODE);

        $db->prepare(
            "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado, detalle)
             VALUES (?, ?, ?, 'Seguridad', ?, 'warn', ?)"
        )->execute([
            $userId ?: null,
            $instId ?: null,
            'security_alert_' . $tipo,
            $ip,
            $detalle,
        ]);

        // Evaluar umbral: >=10 intentos fallidos en 15 min desde misma IP
        if ($tipo === 'login_fallido') {
            $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND exitoso = 0 AND creado_at > ?"
            );
            $stmt->execute([$ip, $window]);
            if ((int)$stmt->fetchColumn() >= 10) {
                $db->prepare(
                    "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado, detalle)
                     VALUES (NULL, NULL, 'brute_force_detected', 'Seguridad', ?, 'error', ?)"
                )->execute([$ip, json_encode(['ip' => $ip, 'intentos_15min' => '10+'])]);
            }
        }
    } catch (\Throwable $e) {
        error_log('[SecurityAlert] ' . $e->getMessage());
    }
}
