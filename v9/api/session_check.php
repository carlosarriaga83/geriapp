<?php
/**
 * GeriApp — Lightweight session/role check
 * Called periodically by the SPA to detect role changes in real-time.
 * Returns the current role from the DB pivot (not from session cache).
 */
require_once __DIR__ . '/helpers.php';
api_require_method('GET');
api_auth();

$userId = (int) $_SESSION['user_id'];
$instId = (int) ($_SESSION['user_institucion_id'] ?? 0);
$sessionRole = $_SESSION['user_rol'] ?? '';

// Superadmins don't have per-institution roles
if ($sessionRole === 'superadmin') {
    api_json(['role' => 'superadmin', 'changed' => false]);
}

if (!$instId) {
    api_json(['role' => $sessionRole, 'changed' => false]);
}

// Query the pivot for the current role
$currentRole = UsuarioInstitucion::getRol($userId, $instId);
if (!$currentRole) {
    // Access revoked — signal reload
    api_json(['role' => null, 'changed' => true, 'revoked' => true]);
}

$changed = $currentRole !== $sessionRole;
if ($changed) {
    $_SESSION['user_rol'] = $currentRole;
}

api_json(['role' => $currentRole, 'changed' => $changed]);
