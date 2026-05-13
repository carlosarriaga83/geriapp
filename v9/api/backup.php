<?php
/**
 * GeriApp — API /api/backup.php
 *
 * GET  /api/backup.php              → lista de backups disponibles
 * POST /api/backup.php?action=now   → generar backup manual ahora
 * GET  /api/backup.php?action=download&file=xxx → descargar un backup
 * POST /api/backup.php?action=delete&file=xxx   → eliminar un backup
 *
 * Roles: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['admin', 'superadmin']);

$action    = $_GET['action'] ?? '';
$backupDir = dirname(__DIR__) . '/backups';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0750, true);
}
if (!file_exists($backupDir . '/.htaccess')) {
    file_put_contents($backupDir . '/.htaccess', "Require all denied\n");
}

// ── GET: Listar backups ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$action) {
    $instId = api_inst_id();
    $files  = [];

    $iterator = new DirectoryIterator($backupDir);
    foreach ($iterator as $file) {
        if ($file->isDot() || $file->isDir()) continue;
        if ($file->getFilename() === '.htaccess') continue;
        $name = $file->getFilename();

        // Solo mostrar backups de esta institución (o todos si superadmin)
        if ($_SESSION['user_rol'] !== 'superadmin') {
            if (!preg_match('/inst' . $instId . '_/', $name)) continue;
        }

        $sizeMb   = round($file->getSize() / 1048576, 2);
        $sizeStr  = $file->getSize() < 1048576
            ? round($file->getSize() / 1024, 1) . ' KB'
            : $sizeMb . ' MB';
        $encrypted = str_ends_with($name, '.enc');
        $compressed = str_ends_with($name, '.gz') || str_ends_with($name, '.sql.gz');

        $files[] = [
            'name'       => $name,
            'size'       => $file->getSize(),
            'size_fmt'   => $sizeStr,
            'date'       => date('Y-m-d H:i:s', $file->getMTime()),
            'encrypted'  => $encrypted,
            'compressed' => $compressed,
        ];
    }

    // Ordenar por fecha desc
    usort($files, fn($a, $b) => strcmp($b['date'], $a['date']));

    // Leer config de backup
    $cfg = Configuracion::getOrCreate($instId);

    api_ok([
        'backups'    => $files,
        'frecuencia' => $cfg['backup_frecuencia'] ?? 'diario',
        'hora'       => $cfg['backup_hora'] ?? '03:00',
        'total'      => count($files),
    ]);
}

// ── POST: Generar backup ahora ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'now') {
    $instId = api_inst_id();

    require_once dirname(__DIR__) . '/db/Database.php';
    require_once dirname(__DIR__) . '/conf/config.db.php';
    $db = Database::getInstance();

    // Obtener nombre institución
    $stmtInst = $db->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
    $stmtInst->execute([$instId]);
    $instNombre = $stmtInst->fetchColumn() ?: "Institución #{$instId}";

    // Reusar la función de generación del cron si existe, o inline
    require_once dirname(__DIR__) . '/cron/backup.php';

    try {
        $sqlDump = generateBackupSQL($db, $instId, $instNombre);

        $filename = 'geriapp_backup_inst' . $instId . '_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        // Comprimir si disponible
        if (function_exists('gzencode')) {
            $filepath .= '.gz';
            $filename .= '.gz';
            file_put_contents($filepath, gzencode($sqlDump, 9));
        } else {
            file_put_contents($filepath, $sqlDump);
        }

        $sizeMb = round(filesize($filepath) / 1048576, 2);
        $sizeStr = filesize($filepath) < 1048576
            ? round(filesize($filepath) / 1024, 1) . ' KB'
            : $sizeMb . ' MB';

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'backup_crear_manual',
            'modulo'         => 'backup',
            'detalle'        => "Backup manual: {$filename} ({$sizeStr})",
        ]);

        api_ok([
            'file'     => $filename,
            'size_fmt' => $sizeStr,
        ], 'Backup generado correctamente');
    } catch (Exception $e) {
        error_log("[BACKUP] Error generando backup manual: " . $e->getMessage());
        api_error('Error al generar backup: ' . $e->getMessage(), 500);
    }
}

// ── GET: Descargar backup ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download') {
    $fileName = basename($_GET['file'] ?? '');
    if (!$fileName || !preg_match('/^geriapp_backup_inst\d+_\d{8}_\d{6}\.sql/', $fileName)) {
        api_error('Nombre de archivo inválido', 400);
    }

    $filePath = $backupDir . '/' . $fileName;
    if (!file_exists($filePath)) {
        api_error('Archivo no encontrado', 404);
    }

    // Verificar que el backup es de su institución (a menos que sea superadmin)
    $instId = api_inst_id();
    if ($_SESSION['user_rol'] !== 'superadmin' && !str_contains($fileName, 'inst' . $instId . '_')) {
        api_error('Acceso denegado', 403);
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => $instId,
        'accion'         => 'backup_descargar',
        'modulo'         => 'backup',
        'detalle'        => "Descargado: {$fileName}",
    ]);

    $contentType = str_ends_with($fileName, '.gz')
        ? 'application/gzip'
        : (str_ends_with($fileName, '.enc') ? 'application/octet-stream' : 'application/sql');

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($filePath);
    exit;
}

// ── POST: Eliminar backup ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $body     = api_body();
    $fileName = basename($body['file'] ?? '');
    if (!$fileName || !preg_match('/^geriapp_backup_inst\d+_\d{8}_\d{6}\.sql/', $fileName)) {
        api_error('Nombre de archivo inválido', 400);
    }

    $filePath = $backupDir . '/' . $fileName;
    if (!file_exists($filePath)) {
        api_error('Archivo no encontrado', 404);
    }

    $instId = api_inst_id();
    if ($_SESSION['user_rol'] !== 'superadmin' && !str_contains($fileName, 'inst' . $instId . '_')) {
        api_error('Acceso denegado', 403);
    }

    unlink($filePath);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => $instId,
        'accion'         => 'backup_eliminar',
        'modulo'         => 'backup',
        'detalle'        => "Eliminado: {$fileName}",
        'estado'         => 'warn',
    ]);

    api_ok(null, 'Backup eliminado');
}

api_error('Acción no reconocida', 400);
