<?php
/**
 * GeriApp — API /api/switch_institucion.php
 *
 * POST /api/switch_institucion.php
 *   body: { inst_id: N }
 *
 * Cambia la institución activa en la sesión del usuario.
 * Valida que el usuario realmente pertenezca a esa institución.
 * Disponible para todos los roles (excepto superadmin que no tiene inst).
 */

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../db/Database.php';

// Requiere sesión activa
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = $raw ? (json_decode($raw, true) ?? []) : $_POST;
$instId = (int)($body['inst_id'] ?? 0);

if ($instId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'inst_id requerido']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = Database::getMaster();

// Verificar que el usuario tenga acceso a esa institución
$stmt = $db->prepare(
    "SELECT ui.rol, i.nombre AS inst_nombre, i.estado AS inst_estado
     FROM usuario_instituciones ui
     JOIN instituciones i ON i.id = ui.institucion_id
     WHERE ui.usuario_id = ?
       AND ui.institucion_id = ?
       AND ui.estado = 'activo'
     LIMIT 1"
);
$stmt->execute([$userId, $instId]);
$pivot = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback: si el pivot no existe, verificar via usuarios.institucion_id (legacy)
if (!$pivot) {
    $stmtLegacy = $db->prepare(
        "SELECT u.rol, i.nombre AS inst_nombre, i.estado AS inst_estado
         FROM usuarios u
         JOIN instituciones i ON i.id = u.institucion_id
         WHERE u.id = ? AND u.institucion_id = ?
         LIMIT 1"
    );
    $stmtLegacy->execute([$userId, $instId]);
    $pivot = $stmtLegacy->fetch(PDO::FETCH_ASSOC);
}

if (!$pivot) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a esta institución']);
    exit;
}

if (in_array($pivot['inst_estado'], ['suspendida', 'archivada'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Esta institución no está disponible']);
    exit;
}

// Actualizar sesión
$_SESSION['user_rol']                = $pivot['rol'];
$_SESSION['user_institucion_id']     = $instId;
$_SESSION['user_institucion_nombre'] = $pivot['inst_nombre'];
$_SESSION['user_multi_inst']         = true; // sigue siendo multi-inst

// Refrescar timeout de inactividad con la config de la nueva institución.
// Sin esto, un usuario multi-inst que entra por A (60min) y cambia a B
// (30min, o el legacy 1min) seguía con el timeout viejo y la sesión podía
// caer mucho antes (o después) del valor que muestra Configuración.
require_once __DIR__ . '/../db/models/Configuracion.php';
try {
    $_secCfg = Configuracion::getByInstitucion($instId) ?: [];
    $_sessionMin = max(5, (int)($_secCfg['seg_timeout_sesion'] ?? 60));
    $_SESSION['_cfg_session_timeout'] = $_sessionMin * 60;
    // Resetear el contador de inactividad para que el cambio no expulse
    // inmediatamente al usuario por un valor anterior ya vencido.
    $_SESSION['_last_activity'] = time();
} catch (\Throwable $e) { /* fallback: SESSION_TIMEOUT global */ }

// Limpiar pending_instituciones una vez seleccionada
unset($_SESSION['pending_instituciones']);

// Limpiar cache de tenant para forzar reconexion
Database::clearTenantCache($instId);

// Resetear timer de verificación del middleware
unset($_SESSION['_inst_check_at']);

// Actualizar sesión activa con institución
try {
    $db->prepare("UPDATE sesiones_activas SET institucion_id = ?, ultimo_acceso = NOW() WHERE session_id = ?")
       ->execute([$instId, session_id()]);
} catch (\Throwable $e) { /* table may not exist yet */ }

// Registrar el switch en logs
$db->prepare(
    "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
     VALUES (?, ?, 'switch_institucion', 'Auth', ?, 'ok')"
)->execute([$userId, $instId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'       => true,
    'message'       => 'Institución cambiada',
    'institucion'   => [
        'id'     => $instId,
        'nombre' => $pivot['inst_nombre'],
        'rol'    => $pivot['rol'],
    ],
]);
exit;
