<?php
/**
 * GeriApp — API /api/prescripciones.php
 *
 * GET    /api/prescripciones.php              → lista activas de la institución
 * GET    /api/prescripciones.php?res_id=N     → prescripciones de un residente
 * POST   /api/prescripciones.php              → crear o actualizar
 * DELETE /api/prescripciones.php?id=N         → desactivar (soft-delete)
 *
 * Roles: admin, medico, enfermero, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

$method = api_method();

// ─────────────────────────────────────────────────────────────────────────────
// GET
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!empty($_GET['res_id'])) {
        $res_id = api_int('res_id');
        api_assert_residente($res_id);
        api_ok(Prescripcion::getForResidente($res_id));
    }
    api_ok(Prescripcion::getForInstitucion(api_inst_id()));
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — crear o actualizar
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {

    // ── Upload prescription image ──────────────────────────────────────────
    if (!empty($_POST['action']) && $_POST['action'] === 'upload_imagen') {
        api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
        $rx_id = (int)($_POST['prescripcion_id'] ?? 0);
        if (!$rx_id) api_error('prescripcion_id requerido', 422);
        $rx = Prescripcion::getById($rx_id);
        if (!$rx) api_error('Prescripción no encontrada', 404);

        if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            api_error('No se recibió archivo de imagen', 400);
        }
        $file = $_FILES['imagen'];
        $max  = 5 * 1024 * 1024; // 5 MB
        if ($file['size'] > $max) api_error('La imagen supera el límite de 5 MB', 413);

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext  = ['jpg','jpeg','png','webp'];
        $allowed_mime = ['image/jpeg','image/png','image/webp'];
        if (!in_array($ext, $allowed_ext, true)) {
            api_error('Solo se permiten imágenes JPG, PNG o WebP', 415);
        }
        // §6.5 Validación MIME reforzada con finfo
        $mime = api_validate_mime($file['tmp_name'], $allowed_mime);

        $inst_id  = api_inst_id();
        $base_dir = dirname(__DIR__) . "/uploads/prescripciones/{$inst_id}";
        if (!is_dir($base_dir)) mkdir($base_dir, 0755, true);

        // Delete old image if exists
        if (!empty($rx['imagen'])) {
            $old_path = dirname(__DIR__) . '/' . $rx['imagen'];
            if (file_exists($old_path)) @unlink($old_path);
        }

        $safeName = $rx_id . '_' . time() . '.jpg';
        $dest     = $base_dir . '/' . $safeName;

        // Server-side compression: resize to max 1200px & save as JPEG 80%
        $src = $file['tmp_name'];
        $compressed = false;
        if (function_exists('imagecreatefromjpeg')) {
            $img = match(true) {
                str_contains($mime, 'png')  => @imagecreatefrompng($src),
                str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($src),
                default => @imagecreatefromjpeg($src),
            };
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                $max = 1200;
                if ($w > $h) { $nw = min($w, $max); $nh = (int)round($h * $nw / $w); }
                else         { $nh = min($h, $max); $nw = (int)round($w * $nh / $h); }
                if ($nw !== $w || $nh !== $h) {
                    $resized = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagedestroy($img);
                    $img = $resized;
                }
                if (imagejpeg($img, $dest, 80)) { $compressed = true; }
                imagedestroy($img);
            }
        }
        if (!$compressed) {
            if (!move_uploaded_file($src, $dest)) {
                api_error('Error al guardar la imagen', 500);
            }
        }

        $relPath = "uploads/prescripciones/{$inst_id}/{$safeName}";
        Prescripcion::update($rx_id, ['imagen' => $relPath]);
        api_ok(['imagen' => $relPath], 'Imagen guardada');
    }

    // ── Delete prescription image ──────────────────────────────────────────
    if (!empty($_POST['action']) && $_POST['action'] === 'delete_imagen') {
        api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
        $rx_id = (int)($_POST['prescripcion_id'] ?? 0);
        if (!$rx_id) api_error('prescripcion_id requerido', 422);
        $rx = Prescripcion::getById($rx_id);
        if (!$rx) api_error('Prescripción no encontrada', 404);

        if (!empty($rx['imagen'])) {
            $old_path = dirname(__DIR__) . '/' . $rx['imagen'];
            if (file_exists($old_path)) @unlink($old_path);
        }
        Prescripcion::update($rx_id, ['imagen' => null]);
        api_ok(null, 'Imagen eliminada');
    }

    $body = api_body();

    // Actualizar existente
    if (!empty($body['id'])) {
        api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
        $id = (int)$body['id'];
        $p  = Prescripcion::getById($id);
        if (!$p) api_error('Prescripción no encontrada', 404);

        unset($body['id'], $body['residente_id'], $body['institucion_id']);
        $ok = Prescripcion::update($id, $body);
        if (!$ok) api_error('Error al actualizar', 500);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'prescripcion_editar',
            'modulo'         => 'prescripciones',
            'detalle'        => "Prescripción ID {$id}",
        ]);

        api_ok(['id' => $id], 'Prescripción actualizada');
    }

    // ── Batch create: múltiples medicamentos en una sola prescripción ──────────
    if (!empty($body['action']) && $body['action'] === 'batch_create') {
        api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
        $residente_id  = (int)($body['residente_id'] ?? 0);
        if (!$residente_id) api_error('residente_id requerido', 422);
        api_assert_residente($residente_id);

        $meds = $body['medicamentos'] ?? [];
        if (empty($meds)) api_error('Debe agregar al menos un medicamento', 422);

        $inst_id       = api_inst_id();
        $medico_nombre = trim($body['medico_nombre'] ?? '');
        $inicio        = !empty($body['inicio']) ? $body['inicio'] : null;
        $fin           = !empty($body['fin'])    ? $body['fin']    : null;
        $nota_global   = trim($body['nota_global'] ?? '');

        $ids       = [];
        $med_names = [];
        foreach ($meds as $med) {
            if (empty($med['nombre'])) continue;
            $medInicio = !empty($med['inicio']) ? $med['inicio'] : $inicio;
            $medFin    = !empty($med['fin'])    ? $med['fin']    : $fin;
            $id = Prescripcion::create([
                'residente_id'   => $residente_id,
                'institucion_id' => $inst_id,
                'nombre'         => trim($med['nombre']),
                'dosis'          => trim($med['dosis']      ?? ''),
                'via'            => trim($med['via']         ?? ''),
                'frecuencia'     => trim($med['frecuencia'] ?? ''),
                'horarios'       => $med['horarios'] ?? [],
                'indicacion'     => trim($med['indicacion'] ?? ''),
                'medico_nombre'  => $medico_nombre ?: null,
                'inicio'         => $medInicio,
                'fin'            => $medFin,
            ]);
            if ($id) { $ids[] = $id; $med_names[] = trim($med['nombre']); }
        }
        if (empty($ids)) api_error('No se pudo crear ningún medicamento', 500);

        // (historial clínico legacy removed in v1.26.0 — tables dropped)

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $inst_id,
            'accion'         => 'prescripcion_crear_lote',
            'modulo'         => 'prescripciones',
            'detalle'        => count($ids) . ' prescripción(es): ' . implode(', ', $med_names),
        ]);
        api_ok(['ids' => $ids],
               count($ids) . ' prescripción(es) creada(s) exitosamente');
    }

    // Crear nueva
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
    $errors = [];
    if (empty($body['residente_id'])) $errors['residente_id'] = 'Requerido';
    if (empty($body['nombre']))       $errors['nombre']       = 'Requerido';
    if (!empty($errors)) api_error('Datos inválidos', 422, $errors);

    api_assert_residente((int)$body['residente_id']);
    $body['institucion_id'] = api_inst_id();

    $id = Prescripcion::create($body);
    if (!$id) api_error('Error al crear prescripción', 500);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'prescripcion_crear',
        'modulo'         => 'prescripciones',
        'detalle'        => "'{$body['nombre']}' — residente ID {$body['residente_id']}",
    ]);

    api_ok(['id' => $id], 'Prescripción creada');
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — desactivar
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
    $id = api_int('id');
    if (!$id) api_error('id requerido', 400);

    $p = Prescripcion::getById($id);
    if (!$p) api_error('Prescripción no encontrada', 404);

    Prescripcion::delete($id);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'prescripcion_eliminar',
        'modulo'         => 'prescripciones',
        'detalle'        => "Prescripción ID {$id}",
    ]);

    api_ok(null, 'Prescripción desactivada');
}
