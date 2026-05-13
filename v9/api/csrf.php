<?php
/**
 * GeriApp — CSRF Token Refresh
 *
 * GET → devuelve un token CSRF fresco.
 * Usado por Capacitor/nativo para refrescar antes de login
 * cuando la sesión PHP expiró.
 */

require_once dirname(__DIR__) . '/conf/config.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerar token para garantizar frescura
$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['token' => $_SESSION['_csrf_token']]);
