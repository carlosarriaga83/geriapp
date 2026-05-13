<?php
/**
 * GeriApp — API Expediente Médico (Documentos clínicos)
 *
 * GET    ?residente_id=X[&tipo=receta&fuente=medico&q=texto&desde=&hasta=&page=1]
 * GET    ?action=brief&residente_id=X          — listado breve para selector
 * GET    ?action=serve&id=X                     — servir archivo inline
 * POST   multipart  action=crear               — crear con archivo
 * POST   json       action=actualizar           — editar metadatos
 * POST   json       action=eliminar             — eliminar
 * POST   json       action=vincular_rx          — vincular documento a prescripción
 * POST   json       action=desvincular_rx       — desvincular
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/db/models/ExpedienteDoc.php';

function exp_request_residente_id(array $source): int
{
    $rid = (int)($source['residente_id'] ?? 0);
    if (!$rid) api_error('residente_id requerido', 400);
    api_assert_residente($rid);
    return $rid;
}

function exp_filter_docs_for_residente(array $docs, int $residenteId): array
{
    return array_values(array_filter($docs, fn($doc) => (int)($doc['residente_id'] ?? 0) === $residenteId));
}

// ── GET ──────────────────────────────────────────────────────────────────
$method = api_method();

if ($method === 'GET' || $method === 'HEAD') {
    $action = $_GET['action'] ?? '';

    // Serve file inline (image or PDF) — supports &idx=N for multi-file
    if ($action === 'serve') {
        api_auth();
        $id = api_int('id');
        if (!$id) api_error('ID requerido', 400);
        $rid = exp_request_residente_id($_GET);

        $doc = ExpedienteDoc::getById($id, api_inst_id(), $rid);
        if (!$doc) api_error('Documento no encontrado', 404);

        $idx = isset($_GET['idx']) ? intval($_GET['idx']) : -1;

        // Resolve file info from archivos_json or legacy columns
        $filePath = null;
        $fileName = null;
        $fileMime = null;

        if (!empty($doc['archivos_json'])) {
            $archivos = json_decode($doc['archivos_json'], true);
            if (is_array($archivos) && count($archivos)) {
                $i = ($idx >= 0 && $idx < count($archivos)) ? $idx : 0;
                $filePath = $archivos[$i]['path'] ?? $archivos[$i]['url'] ?? null;
                $fileName = $archivos[$i]['nombre'] ?? 'archivo';
                $fileMime = $archivos[$i]['tipo'] ?? null;
            }
        }
        // Fallback to legacy single-file columns
        if (!$filePath && !empty($doc['archivo_path'])) {
            $filePath = $doc['archivo_path'];
            $fileName = $doc['archivo_nombre'] ?: 'archivo';
            $fileMime = $doc['archivo_tipo'];
        }
        if (!$filePath) api_error('Archivo no encontrado', 404);

        $fullPath = dirname(__DIR__) . '/' . $filePath;
        if (!file_exists($fullPath)) api_error('Archivo no encontrado', 404);

        $mime = $fileMime ?: mime_content_type($fullPath);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
        header('Cache-Control: private, max-age=3600');
        header('Content-Length: ' . filesize($fullPath));
        if ($method === 'HEAD') exit;
        readfile($fullPath);
        exit;
    }

    // Brief list for selectors
    if ($action === 'brief') {
        api_auth();
        $rid = api_int('residente_id');
        if (!$rid) api_error('residente_id requerido', 400);
        api_assert_residente($rid);
        api_ok(ExpedienteDoc::listBrief(api_inst_id(), $rid));
    }

    // Full list with filters
    api_auth();
    $rid = api_int('residente_id');
    if (!$rid) api_error('residente_id requerido', 400);
    api_assert_residente($rid);

    $page  = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $filters = [
        'tipo'   => $_GET['tipo'] ?? '',
        'fuente' => $_GET['fuente'] ?? '',
        'q'      => $_GET['q'] ?? '',
        'desde'  => $_GET['desde'] ?? '',
        'hasta'  => $_GET['hasta'] ?? '',
        'orden'  => $_GET['orden'] ?? 'DESC',
        'limit'  => $limit,
        'offset' => ($page - 1) * $limit,
    ];

    $total = ExpedienteDoc::count(api_inst_id(), $rid, $filters);
    $docs  = exp_filter_docs_for_residente(ExpedienteDoc::list(api_inst_id(), $rid, $filters), $rid);

    api_ok([
        'docs'  => $docs,
        'total' => $total,
        'page'  => $page,
        'pages' => max(1, ceil($total / $limit)),
    ]);
}

// ── POST ─────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    // Determine action from either JSON body or multipart form
    $action = $_POST['action'] ?? '';
    if (!$action) {
        $body = api_body();
        $action = $body['action'] ?? '';
    }

    // ── Crear (multipart upload) ──────────────────────────────────────
    if ($action === 'crear') {
        api_auth_roles(['superadmin','admin','medico','enfermero']);

        $rid   = intval($_POST['residente_id'] ?? 0);
        $tipo  = $_POST['tipo'] ?? 'receta';
        $titulo = trim($_POST['titulo'] ?? '');
        if (!$rid || !$titulo) api_error('residente_id y titulo son obligatorios', 400);
        api_assert_residente($rid);

        $validTipos = ['receta','laboratorio','imagen','interpretacion','hospitalizacion','legal','nota_enfermeria'];
        if (!in_array($tipo, $validTipos)) api_error('Tipo inválido', 400);

        $validFuentes = ['medico','familiar','residente','otro'];
        $fuente = $_POST['fuente'] ?? 'medico';
        if (!in_array($fuente, $validFuentes)) $fuente = 'otro';

        // ── Helper: procesar un archivo subido ─────────────────────────
        $allowedMimes = ['image/jpeg','image/png','image/webp','application/pdf'];
        $instId = api_inst_id();
        $dir = "uploads/expediente/{$instId}/{$rid}";
        $fullDir = dirname(__DIR__) . '/' . $dir;

        $processFile = function(array $file) use ($allowedMimes, $dir, $fullDir): array {
            if ($file['size'] > 10 * 1024 * 1024) api_error('Archivo máximo 10 MB', 400);

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMimes)) api_error('Solo se permiten JPG, PNG, WebP o PDF', 400);

            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => 'bin',
            };

            if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

            $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destPath = $dir . '/' . $safeName;
            $fullDest = dirname(__DIR__) . '/' . $destPath;

            // Compress images server-side
            if (str_starts_with($mime, 'image/') && $mime !== 'image/webp') {
                $img = $mime === 'image/png'
                    ? imagecreatefrompng($file['tmp_name'])
                    : imagecreatefromjpeg($file['tmp_name']);
                if ($img) {
                    $w = imagesx($img);
                    $h = imagesy($img);
                    if ($w > 2048 || $h > 2048) {
                        $ratio = min(2048/$w, 2048/$h);
                        $nw = (int)($w * $ratio);
                        $nh = (int)($h * $ratio);
                        $resized = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($resized, $img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagedestroy($img);
                        $img = $resized;
                    }
                    imagejpeg($img, $fullDest, 82);
                    imagedestroy($img);
                    $ext = 'jpg';
                    $mime = 'image/jpeg';
                    if (!str_ends_with($safeName, '.jpg')) {
                        $newSafe = pathinfo($safeName, PATHINFO_FILENAME) . '.jpg';
                        rename($fullDest, $fullDir . '/' . $newSafe);
                        $safeName = $newSafe;
                        $destPath = $dir . '/' . $safeName;
                    }
                } else {
                    move_uploaded_file($file['tmp_name'], $fullDest);
                }
            } else {
                move_uploaded_file($file['tmp_name'], $fullDest);
            }

            return ['nombre' => $file['name'], 'tipo' => $mime, 'path' => $destPath];
        };

        // ── Procesar archivos (multi o single) ───────────────────────
        $archivos = [];

        // Multi-file: archivos[] array
        if (!empty($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
            $count = count($_FILES['archivos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $archivos[] = $processFile([
                    'name'     => $_FILES['archivos']['name'][$i],
                    'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                    'size'     => $_FILES['archivos']['size'][$i],
                    'type'     => $_FILES['archivos']['type'][$i],
                    'error'    => $_FILES['archivos']['error'][$i],
                ]);
            }
        }
        // Legacy single file: archivo
        elseif (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivos[] = $processFile($_FILES['archivo']);
        }

        // First file populates legacy columns for backward compat
        $archivoNombre = $archivos[0]['nombre'] ?? null;
        $archivoTipo   = $archivos[0]['tipo'] ?? null;
        $archivoPath   = $archivos[0]['path'] ?? null;
        $archivosJson  = count($archivos) ? json_encode($archivos, JSON_UNESCAPED_UNICODE) : null;

        $id = ExpedienteDoc::create([
            'institucion_id'  => api_inst_id(),
            'residente_id'    => $rid,
            'tipo'            => $tipo,
            'titulo'          => $titulo,
            'descripcion'     => trim($_POST['descripcion'] ?? '') ?: null,
            'fuente'          => $fuente,
            'nombre_fuente'   => trim($_POST['nombre_fuente'] ?? '') ?: null,
            'especialidad'    => trim($_POST['especialidad'] ?? '') ?: null,
            'fecha_documento' => $_POST['fecha_documento'] ?? null,
            'archivo_nombre'  => $archivoNombre,
            'archivo_tipo'    => $archivoTipo,
            'archivo_path'    => $archivoPath,
            'archivos_json'   => $archivosJson,
            'created_by'      => api_user_id(),
        ]);

        if (!$id) api_error('Error al guardar documento', 500);
        api_ok(['id' => $id], 'Documento guardado');
    }

    // ── Actualizar (JSON body) ─────────────────────────────────────────
    if ($action === 'actualizar') {
        api_auth_roles(['superadmin','admin','medico','enfermero']);
        $body = api_body();
        $id   = intval($body['id'] ?? 0);
        if (!$id) api_error('id requerido', 400);
        $rid = exp_request_residente_id($body);

        $doc = ExpedienteDoc::getById($id, api_inst_id(), $rid);
        if (!$doc) api_error('Documento no encontrado', 404);

        // Author-only edit: non-admin users can only edit their own docs
        // unless they have the editar_expediente permission
        $rol = $_SESSION['user_rol'] ?? '';
        if (!in_array($rol, ['superadmin', 'admin'], true)
            && (int)$doc['created_by'] !== api_user_id()) {
            // Check if role has editar_expediente permission
            $cfg = Configuracion::getCached(api_inst_id());
            $rp = !empty($cfg['roles_permisos'])
                ? (is_string($cfg['roles_permisos']) ? json_decode($cfg['roles_permisos'], true) : $cfg['roles_permisos'])
                : [];
            if (empty($rp[$rol]['editar_expediente'])) {
                api_error('Solo el autor puede editar este documento', 403);
            }
        }

        $body['institucion_id'] = api_inst_id();
        $body['residente_id'] = $rid;
        $ok = ExpedienteDoc::update($id, $body);
        api_ok(null, $ok ? 'Documento actualizado' : 'Sin cambios');
    }

    // ── Eliminar ───────────────────────────────────────────────────────
    if ($action === 'eliminar') {
        api_auth_roles(['superadmin','admin']);
        $body = api_body();
        $id   = intval($body['id'] ?? 0);
        if (!$id) api_error('id requerido', 400);
        $rid = exp_request_residente_id($body);

        $paths = ExpedienteDoc::delete($id, api_inst_id(), $rid);
        if ($paths === false) api_error('Documento no encontrado', 404);
        if (is_array($paths)) {
            foreach ($paths as $p) {
                $full = dirname(__DIR__) . '/' . $p;
                if (file_exists($full)) @unlink($full);
            }
        }
        api_ok(null, 'Documento eliminado');
    }

    // ── Vincular documento a prescripción ──────────────────────────────
    if ($action === 'vincular_rx') {
        api_auth_roles(['superadmin','admin','medico','enfermero']);
        $body  = api_body();
        $rxId  = intval($body['prescripcion_id'] ?? 0);
        $docId = intval($body['expediente_id'] ?? 0);
        if (!$rxId || !$docId) api_error('prescripcion_id y expediente_id requeridos', 400);
                $rid = exp_request_residente_id($body);

                $doc = ExpedienteDoc::getById($docId, api_inst_id(), $rid);
        if (!$doc) api_error('Documento no encontrado', 404);

        $db = Database::getTenant(api_inst_id());
        $db->prepare("UPDATE prescripciones SET expediente_id = ? WHERE id = ? AND institucion_id = ? AND residente_id = ?")
           ->execute([$docId, $rxId, api_inst_id(), $rid]);
        api_ok(null, 'Documento vinculado a prescripción');
    }

    // ── Desvincular ────────────────────────────────────────────────────
    if ($action === 'desvincular_rx') {
        api_auth_roles(['superadmin','admin','medico','enfermero']);
        $body = api_body();
        $rxId = intval($body['prescripcion_id'] ?? 0);
        if (!$rxId) api_error('prescripcion_id requerido', 400);
                $rid = exp_request_residente_id($body);

        $db = Database::getTenant(api_inst_id());
        $db->prepare("UPDATE prescripciones SET expediente_id = NULL WHERE id = ? AND institucion_id = ? AND residente_id = ?")
           ->execute([$rxId, api_inst_id(), $rid]);
        api_ok(null, 'Vinculación eliminada');
    }

    api_error('Acción no reconocida', 400);
}

api_error('Método no soportado', 405);
