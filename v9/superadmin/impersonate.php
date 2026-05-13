<?php
/**
 * GeriApp — Superadmin Impersonate
 *
 * Sets up an impersonation session for a specific institution and
 * redirects to cuidados.php in a single request (no race condition).
 *
 * Usage: GET /superadmin/impersonate.php?inst_id=X  (must be SA-authenticated)
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once dirname(__DIR__) . '/db/Database.php';

$instId = (int)($_GET['inst_id'] ?? 0);
if ($instId <= 0) {
    header('Location: ' . BASE_URL . '/superadmin/');
    exit;
}

$db = Database::getMaster();
$stmt = $db->prepare("SELECT id, nombre FROM instituciones WHERE id = ?");
$stmt->execute([$instId]);
$inst = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inst) {
    header('Location: ' . BASE_URL . '/superadmin/?error=inst_not_found');
    exit;
}

$impersonationUserId = -1;
try {
    $userStmt = $db->query("SELECT id FROM usuarios WHERE rol = 'superadmin' AND estado = 'activo' ORDER BY id LIMIT 1");
    $impersonationUserId = (int)($userStmt->fetchColumn() ?: -1);
} catch (Throwable $e) {
    $impersonationUserId = -1;
}

// Backup any existing main-app user session vars
$backupKeys = ['user_id','user_nombre','user_rol','user_institucion_id','user_institucion_nombre','user_estado','user_multi_inst','_inst_check_at'];
$backup = [];
foreach ($backupKeys as $key) {
    if (isset($_SESSION[$key])) $backup[$key] = $_SESSION[$key];
}

// Set impersonation flags
$_SESSION['sa_impersonating']    = true;
$_SESSION['sa_imp_inst_id']      = $instId;
$_SESSION['sa_imp_inst_nombre']  = $inst['nombre'];
$_SESSION['sa_imp_role']         = 'superadmin';
$_SESSION['sa_imp_backup']       = $backup;

// Set user_* vars so cuidados.php middleware passes
$_SESSION['user_id']                 = $impersonationUserId;
$_SESSION['user_nombre']             = $_SESSION['sa_name'] ?? 'Superadmin';
$_SESSION['user_rol']                = 'superadmin';
$_SESSION['user_institucion_id']     = $instId;
$_SESSION['user_institucion_nombre'] = $inst['nombre'];
$_SESSION['user_estado']             = 'activo';
$_SESSION['user_multi_inst']         = false;
unset($_SESSION['_inst_check_at']);

// Redirect to cuidados in the same request
header('Location: ' . BASE_URL . '/cuidados.php');
exit;
