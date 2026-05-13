<?php
/**
 * GeriApp — §10.6 Servidor de archivos protegido
 *
 * Sirve archivos de uploads/ verificando autenticación.
 * Reemplaza el acceso directo por URL que era inseguro.
 *
 * Uso: /api/serve_file.php?path=/uploads/historial/3/foto.jpg
 * O automáticamente vía .htaccess en uploads/
 */

require_once __DIR__ . '/helpers.php';

// Requiere sesión autenticada
api_auth();

// Obtener ruta solicitada
$requestPath = $_GET['path'] ?? '';
if (!$requestPath) {
    http_response_code(400);
    exit('Bad request');
}

// Normalizar y resolver ruta real
$basePath  = realpath(dirname(__DIR__) . '/uploads');
if (!$basePath) {
    http_response_code(404);
    exit('Not found');
}

// Extraer la parte relativa de la ruta (quitar /uploads o BASE_URL/uploads)
$relativePath = $requestPath;
// Quitar BASE_URL si presente
if (defined('BASE_URL') && BASE_URL !== '') {
    $relativePath = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $relativePath);
}
// Quitar /uploads/ prefix
$relativePath = preg_replace('#^/uploads/#', '', $relativePath);
$relativePath = ltrim($relativePath, '/');

// Resolver ruta real y verificar que esté dentro de uploads/
$fullPath = realpath($basePath . '/' . $relativePath);

if (!$fullPath || strpos($fullPath, $basePath) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('Not found');
}

// Verificar que el archivo pertenece a la institución del usuario (si es historial)
$instId = api_inst_id();
if ($instId && preg_match('#/historial/(\d+)/#', $requestPath, $m)) {
    if ((int)$m[1] !== $instId && api_rol() !== 'superadmin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

// MIME types seguros
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Servir archivo con headers de seguridad
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
