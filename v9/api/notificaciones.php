<?php
/**
 * GeriApp — API /api/notificaciones.php
 *
 * ADMIN (admin, superadmin):
 *   GET    ?action=list                 → listar notificaciones de la institución
 *   GET    ?action=log&id=X             → ver log de una notificación
 *   POST   action=crear                 → crear notificación
 *   PUT    action=actualizar            → editar notificación
 *   DELETE ?id=X                        → eliminar notificación
 *
 * TODOS LOS USUARIOS:
 *   GET    ?action=pendientes           → notificaciones no vistas por el usuario
 *   POST   action=responder             → marcar como vista / responder
 */

require_once __DIR__ . '/helpers.php';

api_auth();

$method = api_method();
$action = $_GET['action'] ?? '';
$instId = api_inst_id();
$userId = api_user_id();
$rol    = api_rol();

$db = Database::getTenant($instId);

// ═══════════════════════════════════════════════════════════════════════════════
// GET
// ═══════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {

    // ── Notificaciones pendientes para el usuario actual ──────────────────
    if ($action === 'pendientes') {
        try {
            $stmt = $db->prepare("
                SELECT n.id, n.titulo, n.mensaje, n.tipo, n.opciones_respuesta, n.creado_at
                FROM notificaciones_sistema n
                WHERE n.institucion_id = ? AND n.activo = 1
                  AND n.id NOT IN (
                      SELECT nl.notificacion_id FROM notificaciones_sistema_log nl
                      WHERE nl.usuario_id = ? AND nl.institucion_id = ?
                  )
                ORDER BY n.creado_at DESC
            ");
            $stmt->execute([$instId, $userId, $instId]);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            api_ok([]);
        }
        // Enrich with imagen_url if column exists
        try {
            $imgStmt = $db->prepare("SELECT id, imagen_url FROM notificaciones_sistema WHERE institucion_id = ?");
            $imgStmt->execute([$instId]);
            $imgMap = [];
            foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $imgMap[(int)$row['id']] = $row['imagen_url'];
            foreach ($notifs as &$ni) $ni['imagen_url'] = $imgMap[(int)$ni['id']] ?? null;
            unset($ni);
        } catch (\Throwable $e) { /* column may not exist */ }
        // Filter by role if roles_destino column exists
        try {
            $rdStmt = $db->prepare("SELECT id, roles_destino FROM notificaciones_sistema WHERE institucion_id = ? AND activo = 1");
            $rdStmt->execute([$instId]);
            $rdMap = [];
            foreach ($rdStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rdMap[(int)$row['id']] = $row['roles_destino'];
            }
            $notifs = array_values(array_filter($notifs, function($n) use ($rol, $rdMap) {
                $rd = $rdMap[(int)$n['id']] ?? null;
                if (empty($rd)) return true;
                $roles = json_decode($rd, true);
                if (!is_array($roles) || empty($roles)) return true;
                return in_array($rol, $roles);
            }));
        } catch (\Throwable $e) { /* roles_destino column may not exist yet */ }
        foreach ($notifs as &$n) {
            if ($n['opciones_respuesta']) $n['opciones_respuesta'] = json_decode($n['opciones_respuesta'], true);
            unset($n['roles_destino']);
        }
        unset($n);
        api_ok($notifs);
    }

    // ── Admin: listar todas las notificaciones ───────────────────────────
    if ($action === 'list') {
        if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);
        try {
            $stmt = $db->prepare("
                SELECT n.*, u.nombre AS creador_nombre
                FROM notificaciones_sistema n
                LEFT JOIN " . DB_MASTER_NAME . ".usuarios u ON u.id = n.creado_por
                WHERE n.institucion_id = ?
                ORDER BY n.creado_at DESC
            ");
            $stmt->execute([$instId]);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Table may not exist yet
            api_ok([]);
        }

        // Count responses per notification
        foreach ($notifs as &$n) {
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM notificaciones_sistema_log WHERE notificacion_id = ? AND institucion_id = ?");
            $stmt2->execute([$n['id'], $instId]);
            $n['respuestas_count'] = (int) $stmt2->fetchColumn();
            if (!empty($n['opciones_respuesta'])) {
                $n['opciones_respuesta'] = json_decode($n['opciones_respuesta'], true);
            }
            $n['roles_destino'] = isset($n['roles_destino']) && $n['roles_destino']
                ? json_decode($n['roles_destino'], true)
                : [];
        }
        unset($n);
        api_ok($notifs);
    }

    // ── Admin: log de una notificación ───────────────────────────────────
    if ($action === 'log') {
        if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);
        $notifId = (int) ($_GET['id'] ?? 0);
        if (!$notifId) api_error('ID de notificación requerido', 400);

        $stmt = $db->prepare("
            SELECT nl.*, u.nombre AS usuario_nombre, u.email AS usuario_email
            FROM notificaciones_sistema_log nl
            LEFT JOIN " . DB_MASTER_NAME . ".usuarios u ON u.id = nl.usuario_id
            WHERE nl.notificacion_id = ? AND nl.institucion_id = ?
            ORDER BY nl.visto_at DESC
        ");
        $stmt->execute([$notifId, $instId]);
        api_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    api_error('Acción no válida', 400);
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST
// ═══════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $body   = api_body();
    $action = $body['action'] ?? '';

    // ── Usuario: responder / marcar como vista ────────────────────────────
    if ($action === 'responder') {
        $notifId   = (int) ($body['notificacion_id'] ?? 0);
        $respuesta = isset($body['respuesta']) ? trim($body['respuesta']) : null;
        if (!$notifId) api_error('ID de notificación requerido', 400);

        // Check if already responded
        $check = $db->prepare("SELECT id FROM notificaciones_sistema_log WHERE notificacion_id = ? AND usuario_id = ? AND institucion_id = ?");
        $check->execute([$notifId, $userId, $instId]);
        if ($check->fetch()) api_error('Ya respondiste a esta notificación', 409);

        $stmt = $db->prepare("INSERT INTO notificaciones_sistema_log (notificacion_id, institucion_id, usuario_id, respuesta) VALUES (?, ?, ?, ?)");
        $stmt->execute([$notifId, $instId, $userId, $respuesta]);
        api_ok(null, 'Respuesta registrada');
    }

    // ── Admin: subir media (Imagen/GIF/Video) ──────────────────────────
    if ($action === 'upload_media') {
        if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);

        if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            api_error('No se recibió archivo', 400);
        }

        $file = $_FILES['media'];
        if ($file['size'] > 10 * 1024 * 1024) api_error('El archivo excede 10 MB', 413);

        // §6.5 Validación MIME reforzada con finfo
        $allowed_notif = ['image/gif','image/jpeg','image/png','image/webp','video/mp4','video/webm'];
        $mime = api_validate_mime($file['tmp_name'], $allowed_notif);
        $ext_map = ['image/gif' => 'gif', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/webm' => 'webm'];
        $ext = $ext_map[$mime];
        $dir = dirname(__DIR__) . "/uploads/notificaciones/{$instId}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Compress static images (not GIF/video) server-side
        $isStaticImage = in_array($mime, ['image/jpeg','image/png','image/webp'], true);
        $finalExt = $isStaticImage ? 'jpg' : $ext;
        $name = 'notif_' . uniqid() . '.' . $finalExt;
        $dest = $dir . '/' . $name;
        $saved = false;

        if ($isStaticImage && function_exists('imagecreatefromjpeg')) {
            $src = $file['tmp_name'];
            $img = match(true) {
                str_contains($mime, 'png')  => @imagecreatefrompng($src),
                str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($src),
                default => @imagecreatefromjpeg($src),
            };
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                $max = 1200;
                if ($w > $max || $h > $max) {
                    if ($w > $h) { $nw = $max; $nh = (int)round($h * $max / $w); }
                    else         { $nh = $max; $nw = (int)round($w * $max / $h); }
                    $resized = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagedestroy($img);
                    $img = $resized;
                }
                if (imagejpeg($img, $dest, 80)) { $saved = true; }
                imagedestroy($img);
            }
        }
        if (!$saved) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                api_error('Error al guardar archivo', 500);
            }
        }

        $relPath = "uploads/notificaciones/{$instId}/{$name}";
        api_ok(['url' => $relPath], 'Media subida');
    }

    // ── Admin: crear notificación ─────────────────────────────────────────
    if ($action === 'crear') {
        if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);

        $titulo  = trim($body['titulo'] ?? '');
        $mensaje = trim($body['mensaje'] ?? '');
        $tipo    = $body['tipo'] ?? 'info';
        $activo  = isset($body['activo']) ? (int) $body['activo'] : 1;
        $imagen_url = isset($body['imagen_url']) ? trim($body['imagen_url']) : null;
        if ($imagen_url === '') $imagen_url = null;
        $rolesDestino = null;
        if (!empty($body['roles_destino']) && is_array($body['roles_destino'])) {
            $allowed_roles = ['admin','enfermero','medico','familiar'];
            $rolesDestino = json_encode(array_values(array_intersect(api_roles_storage($body['roles_destino']), $allowed_roles)), JSON_UNESCAPED_UNICODE);
        }

        if (!$titulo) api_error('El título es requerido', 422);
        if (!$mensaje) api_error('El mensaje es requerido', 422);
        if (!in_array($tipo, ['info', 'alerta', 'pregunta'])) api_error('Tipo no válido', 422);

        $opciones = null;
        if ($tipo === 'pregunta' && !empty($body['opciones_respuesta'])) {
            $opciones = json_encode($body['opciones_respuesta'], JSON_UNESCAPED_UNICODE);
        }

        $stmt = $db->prepare("INSERT INTO notificaciones_sistema (institucion_id, titulo, mensaje, tipo, opciones_respuesta, activo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$instId, $titulo, $mensaje, $tipo, $opciones, $activo, $userId]);
        $insertedId = (int) $db->lastInsertId();
        // Save imagen_url — auto-create column if missing
        if ($imagen_url) {
            try { $db->prepare("UPDATE notificaciones_sistema SET imagen_url = ? WHERE id = ?")->execute([$imagen_url, $insertedId]); } catch (\Throwable $e) {
                try { $db->exec("ALTER TABLE notificaciones_sistema ADD COLUMN `imagen_url` VARCHAR(500) DEFAULT NULL AFTER `opciones_respuesta`");
                      $db->prepare("UPDATE notificaciones_sistema SET imagen_url = ? WHERE id = ?")->execute([$imagen_url, $insertedId]); } catch (\Throwable $e2) {}
            }
        }
        // Save roles_destino — auto-create column if missing
        if ($rolesDestino) {
            try { $db->prepare("UPDATE notificaciones_sistema SET roles_destino = ? WHERE id = ?")->execute([$rolesDestino, $insertedId]); } catch (\Throwable $e) {
                try { $db->exec("ALTER TABLE notificaciones_sistema ADD COLUMN `roles_destino` JSON DEFAULT NULL AFTER `creado_por`");
                      $db->prepare("UPDATE notificaciones_sistema SET roles_destino = ? WHERE id = ?")->execute([$rolesDestino, $insertedId]); } catch (\Throwable $e2) {}
            }
        }

        Log::registrar([
            'usuario_id'     => $userId,
            'institucion_id' => $instId,
            'accion'         => 'notificacion_crear',
            'modulo'         => 'notificaciones',
            'detalle'        => "Notificación: {$titulo}",
        ]);

        api_ok(['id' => $insertedId], 'Notificación creada');
    }

    api_error('Acción no válida', 400);
}

// ═══════════════════════════════════════════════════════════════════════════════
// PUT — actualizar notificación
// ═══════════════════════════════════════════════════════════════════════════════
if ($method === 'PUT') {
    if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);

    $body = api_body();

    // ── Update a log entry response ──────────────────────────────────────
    if (($body['action'] ?? '') === 'update_log_entry') {
        $logId = (int) ($body['log_id'] ?? 0);
        if (!$logId) api_error('log_id requerido', 400);
        $respuesta = isset($body['respuesta']) ? trim($body['respuesta']) : null;

        $stmt = $db->prepare("UPDATE notificaciones_sistema_log SET respuesta = ? WHERE id = ? AND institucion_id = ?");
        $stmt->execute([$respuesta, $logId, $instId]);
        if ($stmt->rowCount() === 0) api_error('Entrada no encontrada', 404);
        api_ok(null, 'Respuesta actualizada');
    }

    // ── Update notification ──────────────────────────────────────────────
    $id   = (int) ($body['id'] ?? 0);
    if (!$id) api_error('ID requerido', 400);

    // Verify ownership
    $check = $db->prepare("SELECT id FROM notificaciones_sistema WHERE id = ? AND institucion_id = ?");
    $check->execute([$id, $instId]);
    if (!$check->fetch()) api_error('Notificación no encontrada', 404);

    $fields = [];
    $params = [];

    foreach (['titulo', 'mensaje', 'tipo'] as $f) {
        if (isset($body[$f]) && trim($body[$f]) !== '') {
            $fields[] = "$f = ?";
            $params[] = trim($body[$f]);
        }
    }
    if (isset($body['activo'])) {
        $fields[] = "activo = ?";
        $params[] = (int) $body['activo'];
    }
    if (isset($body['opciones_respuesta'])) {
        $fields[] = "opciones_respuesta = ?";
        $params[] = is_array($body['opciones_respuesta']) ? json_encode($body['opciones_respuesta'], JSON_UNESCAPED_UNICODE) : $body['opciones_respuesta'];
    }
    if (array_key_exists('imagen_url', $body)) {
        try {
            $db->query("SELECT imagen_url FROM notificaciones_sistema LIMIT 0");
            $fields[] = "imagen_url = ?";
            $imgUrl = isset($body['imagen_url']) ? trim($body['imagen_url']) : null;
            $params[] = $imgUrl === '' ? null : $imgUrl;
        } catch (\Throwable $e) {
            try { $db->exec("ALTER TABLE notificaciones_sistema ADD COLUMN `imagen_url` VARCHAR(500) DEFAULT NULL AFTER `opciones_respuesta`");
                  $fields[] = "imagen_url = ?";
                  $imgUrl = isset($body['imagen_url']) ? trim($body['imagen_url']) : null;
                  $params[] = $imgUrl === '' ? null : $imgUrl;
            } catch (\Throwable $e2) {}
        }
    }
    if (array_key_exists('roles_destino', $body)) {
        try {
            $db->query("SELECT roles_destino FROM notificaciones_sistema LIMIT 0");
            $fields[] = "roles_destino = ?";
            if (!empty($body['roles_destino']) && is_array($body['roles_destino'])) {
                $allowed_roles = ['admin','enfermero','medico','familiar'];
                $params[] = json_encode(array_values(array_intersect(api_roles_storage($body['roles_destino']), $allowed_roles)), JSON_UNESCAPED_UNICODE);
            } else {
                $params[] = null;
            }
        } catch (\Throwable $e) {
            try { $db->exec("ALTER TABLE notificaciones_sistema ADD COLUMN `roles_destino` JSON DEFAULT NULL AFTER `creado_por`");
                  $fields[] = "roles_destino = ?";
                  if (!empty($body['roles_destino']) && is_array($body['roles_destino'])) {
                      $allowed_roles = ['admin','enfermero','medico','familiar'];
                      $params[] = json_encode(array_values(array_intersect(api_roles_storage($body['roles_destino']), $allowed_roles)), JSON_UNESCAPED_UNICODE);
                  } else {
                      $params[] = null;
                  }
            } catch (\Throwable $e2) {}
        }
    }

    if (empty($fields)) api_error('Sin campos para actualizar', 422);

    $params[] = $id;
    $params[] = $instId;
    $db->prepare("UPDATE notificaciones_sistema SET " . implode(', ', $fields) . " WHERE id = ? AND institucion_id = ?")->execute($params);

    Log::registrar([
        'usuario_id'     => $userId,
        'institucion_id' => $instId,
        'accion'         => 'notificacion_actualizar',
        'modulo'         => 'notificaciones',
        'detalle'        => "Notificación #{$id}",
    ]);

    api_ok(null, 'Notificación actualizada');
}

// ═══════════════════════════════════════════════════════════════════════════════
// DELETE — eliminar notificación o entrada de log
// ═══════════════════════════════════════════════════════════════════════════════
if ($method === 'DELETE') {
    if (!in_array($rol, ['admin', 'superadmin'])) api_error('Acceso denegado', 403);

    // Delete a single log entry
    if ($action === 'delete_log_entry') {
        $logId = (int) ($_GET['log_id'] ?? 0);
        if (!$logId) api_error('log_id requerido', 400);

        $stmt = $db->prepare("DELETE FROM notificaciones_sistema_log WHERE id = ? AND institucion_id = ?");
        $stmt->execute([$logId, $instId]);
        if ($stmt->rowCount() === 0) api_error('Entrada no encontrada', 404);
        api_ok(null, 'Entrada eliminada');
    }

    // Delete a notification
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) api_error('ID requerido', 400);

    $stmt = $db->prepare("DELETE FROM notificaciones_sistema WHERE id = ? AND institucion_id = ?");
    $stmt->execute([$id, $instId]);

    if ($stmt->rowCount() === 0) api_error('Notificación no encontrada', 404);

    Log::registrar([
        'usuario_id'     => $userId,
        'institucion_id' => $instId,
        'accion'         => 'notificacion_eliminar',
        'modulo'         => 'notificaciones',
        'detalle'        => "Notificación #{$id}",
    ]);

    api_ok(null, 'Notificación eliminada');
}

api_error('Método no permitido', 405);
