<?php
/**
 * GeriApp - Superadmin impersonation role switcher.
 * Allows a superadmin impersonating an institution to test the app as each role.
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

if (empty($_SESSION['sa_impersonating'])) {
    header('Location: ' . BASE_URL . '/superadmin/');
    exit;
}

$token = (string)($_POST['_csrf'] ?? '');
if (!csrf_validate($token)) {
    http_response_code(403);
    echo 'CSRF invalido';
    exit;
}

$role = (string)($_POST['role'] ?? 'superadmin');
$allowedRoles = ['superadmin', 'admin', 'enfermero', 'medico', 'familiar'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'superadmin';
}

$_SESSION['user_rol'] = $role;
$_SESSION['sa_imp_role'] = $role;
unset($_SESSION['_inst_check_at']);

$next = (string)($_POST['next'] ?? '/cuidados.php');
if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || preg_match('/[\r\n]/', $next)) {
    $next = '/cuidados.php';
}

header('Location: ' . $next);
exit;