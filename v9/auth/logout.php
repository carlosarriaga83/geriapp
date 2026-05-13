<?php
/**
 * GeriApp — Logout
 */
require_once dirname(__DIR__) . '/conf/config.php';

// SA impersonation → end it instead of destroying session
if (!empty($_SESSION['sa_impersonating'])) {
    $backupKeys = ['user_id','user_nombre','user_rol','user_institucion_id','user_institucion_nombre','user_estado','user_multi_inst','_inst_check_at'];
    foreach ($backupKeys as $key) { unset($_SESSION[$key]); }
    $backup = $_SESSION['sa_imp_backup'] ?? [];
    foreach ($backup as $k => $v) { $_SESSION[$k] = $v; }
    unset($_SESSION['sa_impersonating'], $_SESSION['sa_imp_inst_id'], $_SESSION['sa_imp_inst_nombre'], $_SESSION['sa_imp_role'], $_SESSION['sa_imp_backup']);
    header('Location: ' . BASE_URL . '/superadmin/');
    exit;
}

if (!empty($_SESSION['user_id'])) {
    // Registrar en log
    try {
        require_once dirname(__DIR__) . '/db/Database.php';
        $db = Database::getInstance();
        $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,?,'sesion_cerrar','Auth',?,'ok')")
           ->execute([
               $_SESSION['user_id'],
               $_SESSION['user_institucion_id'] ?? null,
               $_SERVER['REMOTE_ADDR'] ?? null,
           ]);
        // Eliminar sesión activa
        $db->prepare("DELETE FROM sesiones_activas WHERE session_id = ?")->execute([session_id()]);
    } catch (Throwable $e) { /* silencioso */ }
}

// Destruir sesión limpiamente
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Redirección post-logout (solo rutas internas permitidas)
$allowedRedirects = ['forgot-password.php', 'reset-password.php', 'register.php'];
$redirect = $_GET['redirect'] ?? '';
if (in_array($redirect, $allowedRedirects, true)) {
    $qs = '';
    // Permitir conservar el token de invitación al redirigir a register.php
    if ($redirect === 'register.php' && !empty($_GET['inv'])) {
        $invTok = preg_replace('/[^a-zA-Z0-9]/', '', (string)$_GET['inv']);
        if ($invTok !== '') $qs = '?inv=' . $invTok;
    }
    header('Location: ' . BASE_URL . '/' . $redirect . $qs);
} else {
    header('Location: ' . BASE_URL . '/index.php?bye=1');
}
exit;
