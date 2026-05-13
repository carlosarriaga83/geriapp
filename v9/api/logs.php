<?php
/**
 * GeriApp — API /api/logs.php
 *
 * GET  /api/logs.php                → lista de logs con filtros y paginación
 * GET  /api/logs.php?action=errors  → lectura del log de errores PHP (admin only)
 * GET  /api/logs.php?action=export  → exportar logs auditoría CSV
 * POST /api/logs.php?action=display_errors → toggle display_errors (admin only)
 *
 * Query params (GET default):
 *   modulo, estado, desde (Y-m-d), hasta (Y-m-d), busqueda, page, limit
 *
 * Roles: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['admin', 'superadmin']);

$action = $_GET['action'] ?? '';

// ── GET: Leer log de errores PHP (§7.3) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'errors') {
    api_require_method('GET');
    $logPath = defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : (dirname(__DIR__) . '/logs/php_errors.log');
    $lines   = (int)($_GET['lines'] ?? 200);
    $lines   = min(max($lines, 10), 2000);

    if (!file_exists($logPath)) {
        api_ok(['lines' => [], 'total_size' => 0, 'display_errors' => _getDisplayErrors()]);
    }

    $totalSize = filesize($logPath);
    // Leer últimas N líneas eficientemente
    $result = [];
    $fp = fopen($logPath, 'r');
    if ($fp) {
        $buffer = '';
        $pos = $totalSize;
        $count = 0;
        while ($pos > 0 && $count < $lines) {
            $readSize = min(4096, $pos);
            $pos -= $readSize;
            fseek($fp, $pos);
            $buffer = fread($fp, $readSize) . $buffer;
            $count = substr_count($buffer, "\n");
        }
        fclose($fp);
        $allLines = explode("\n", trim($buffer));
        $result = array_slice($allLines, -$lines);
    }

    api_ok([
        'lines'          => $result,
        'total_size'     => $totalSize,
        'total_size_fmt' => $totalSize < 1048576
            ? round($totalSize / 1024, 1) . ' KB'
            : round($totalSize / 1048576, 2) . ' MB',
        'display_errors' => _getDisplayErrors(),
    ]);
}

// ── POST: Toggle display_errors (§7.2) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'display_errors') {
    $body    = api_body();
    $enabled = !empty($body['enabled']);
    $flag    = dirname(__DIR__) . '/conf/.display_errors';

    if ($enabled) {
        file_put_contents($flag, '1');
    } else {
        if (file_exists($flag)) unlink($flag);
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => $enabled ? 'errores_php_activar' : 'errores_php_desactivar',
        'modulo'         => 'configuracion',
        'detalle'        => 'display_errors cambiado a ' . ($enabled ? 'ON' : 'OFF'),
        'estado'         => 'warn',
    ]);

    api_ok(['display_errors' => $enabled], 'display_errors ' . ($enabled ? 'activado' : 'desactivado'));
}

// ── POST: Limpiar log de errores ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'clear_errors') {
    $logPath = defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : (dirname(__DIR__) . '/logs/php_errors.log');
    if (file_exists($logPath)) {
        file_put_contents($logPath, '');
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'errores_php_limpiar',
        'modulo'         => 'configuracion',
        'detalle'        => 'Log de errores PHP limpiado manualmente',
        'estado'         => 'warn',
    ]);

    api_ok(null, 'Log de errores limpiado');
}

// ── GET: Exportar logs auditoría CSV ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'export') {
    $filtros = ['institucion_id' => api_inst_id()];
    if (!empty($_GET['modulo']))   $filtros['modulo']   = $_GET['modulo'];
    if (!empty($_GET['estado']))   $filtros['estado']    = $_GET['estado'];
    if (!empty($_GET['desde']))    $filtros['desde']     = $_GET['desde'];
    if (!empty($_GET['hasta']))    $filtros['hasta']     = $_GET['hasta'];
    if (!empty($_GET['busqueda'])) $filtros['busqueda']  = $_GET['busqueda'];

    $logs = Log::getAll($filtros, 10000, 0);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'auditoria_exportar',
        'modulo'         => 'configuracion',
        'detalle'        => 'Exportación de logs de auditoría (' . count($logs) . ' registros)',
    ]);

    $filename = 'auditoria_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 for Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Fecha', 'Acción', 'Módulo', 'Estado', 'Usuario', 'Email', 'Institución', 'IP', 'Detalle', 'User Agent']);
    foreach ($logs as $l) {
        fputcsv($out, [
            $l['id'] ?? '',
            $l['creado_at'] ?? '',
            $l['accion'] ?? '',
            $l['modulo'] ?? '',
            $l['estado'] ?? '',
            $l['usuario_nombre'] ?? '',
            $l['usuario_email'] ?? '',
            $l['institucion_nombre'] ?? '',
            $l['ip'] ?? '',
            $l['detalle'] ?? '',
            $l['user_agent'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── GET: Lista de logs (default) ────────────────────────────────────────
api_require_method('GET');

$pag = api_pagination();

$filtros = [];
$filtros['institucion_id'] = api_inst_id();

if (!empty($_GET['modulo']))   $filtros['modulo']   = $_GET['modulo'];
if (!empty($_GET['estado']))   $filtros['estado']    = $_GET['estado'];
if (!empty($_GET['desde']))    $filtros['desde']     = $_GET['desde'];
if (!empty($_GET['hasta']))    $filtros['hasta']     = $_GET['hasta'];
if (!empty($_GET['busqueda'])) $filtros['busqueda']  = $_GET['busqueda'];

$logs  = Log::getAll($filtros, $pag['limit'], $pag['offset']);
$total = Log::count($filtros);

api_ok([
    'logs'  => $logs,
    'total' => $total,
    'page'  => $pag['page'],
    'limit' => $pag['limit'],
    'pages' => ceil($total / $pag['limit']),
]);

// ── Helpers ─────────────────────────────────────────────────────────────
function _getDisplayErrors(): bool
{
    return file_exists(dirname(__DIR__) . '/conf/.display_errors');
}
