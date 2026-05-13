<?php
/**
 * GeriApp — API /api/change_password.php
 * Cambio de contraseña (voluntario o forzoso tras §1.11)
 *
 * POST { current_password, new_password, new_password2 }
 */

require_once __DIR__ . '/helpers.php';

// Autenticación sin CSRF check para este endpoint específico
// (el usuario podría no tener el token cargado si es cambio forzoso)
if (empty($_SESSION['user_id'])) {
    api_error('No autenticado', 401);
}

api_require_method('POST');

$body     = api_body();
$current  = $body['current_password'] ?? '';
$newPass  = $body['new_password']     ?? '';
$newPass2 = $body['new_password2']    ?? '';

if (!$current || !$newPass || !$newPass2) {
    api_error('Todos los campos son requeridos.');
}

if ($newPass !== $newPass2) {
    api_error('Las contraseñas nuevas no coinciden.');
}

// Load institution security config for password length
$_cpInstId = (int)($_SESSION['user_institucion_id'] ?? 0);
$_cpPassLen = 8;
if ($_cpInstId) {
    require_once dirname(__DIR__) . '/db/models/Configuracion.php';
    $_cpCfg = Configuracion::getByInstitucion($_cpInstId) ?: [];
    $_cpPassLen = max(4, (int)($_cpCfg['seg_pass_min_len'] ?? 8));
}

$pwErr = validate_password_strength($newPass, $_cpPassLen);
if ($pwErr) {
    api_error($pwErr);
}

// Verificar contraseña actual
$db   = Database::getInstance();
$stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user['password_hash'])) {
    api_error('La contraseña actual es incorrecta.');
}

// Actualizar
$hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
$db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")
   ->execute([$hash, (int)$_SESSION['user_id']]);

// Limpiar flag de cambio forzoso
unset($_SESSION['password_change_required']);

// Log
Log::registrar([
    'usuario_id'     => (int)$_SESSION['user_id'],
    'institucion_id' => (int)($_SESSION['user_institucion_id'] ?? 0) ?: null,
    'accion'         => 'password_cambiar',
    'modulo'         => 'Auth',
    'detalle'        => 'Cambio de contraseña' . (!empty($body['forced']) ? ' (forzoso por política)' : ''),
]);

api_ok(null, 'Contraseña actualizada correctamente.');
