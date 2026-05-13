<?php
/**
 * GeriApp — Superadmin Auth Middleware
 *
 * Include this at the top of every superadmin page/API.
 * Uses its own session key, independent of the main app auth.
 */

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/conf/config.php';
}

require_once __DIR__ . '/auth_config.php';

// Check if authenticated as superadmin
if (empty($_SESSION['sa_authenticated'])) {
    // For API calls, return JSON error
    if (isset($_GET['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
    }
    // For page requests, redirect to login
    header('Location: ' . BASE_URL . '/superadmin/login.php');
    exit;
}
