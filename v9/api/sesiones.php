<?php
/**
 * GeriApp — API /api/sesiones.php
 *
 * GET  /api/sesiones.php               → listar sesiones activas de la institución
 * DELETE /api/sesiones.php?id=N        → forzar cierre de una sesión
 * DELETE /api/sesiones.php?user_id=N   → cerrar todas las sesiones de un usuario
 *
 * Roles: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin', 'admin']);

$method = api_method();
$instId = api_inst_id();

// ─────────────────────────────────────────────────────────────────────────────
// GET — listar sesiones activas
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $db = Database::getMaster();

    // Limpiar sesiones inactivas > 24h
    $db->exec("DELETE FROM sesiones_activas WHERE ultimo_acceso < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

    $sql = "SELECT s.id, s.usuario_id, s.session_id, s.ip, s.user_agent,
                   s.ultimo_acceso, s.creado_at, s.institucion_id,
                   u.nombre AS usuario_nombre, u.email AS usuario_email,
                   u.avatar_path
            FROM sesiones_activas s
            JOIN usuarios u ON u.id = s.usuario_id";
    $params = [];

    if ($_SESSION['user_rol'] !== 'superadmin') {
        // Non-superadmin: only see sessions for their institution
        $sql .= " WHERE s.institucion_id = ?";
        $params[] = $instId;
    }

    $sql .= " ORDER BY s.ultimo_acceso DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark current session
    $currentSid = session_id();
    foreach ($sessions as &$s) {
        $s['es_actual'] = ($s['session_id'] === $currentSid);
        // Don't expose full session_id to client
        unset($s['session_id']);
    }
    unset($s);

    api_ok($sessions);
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — forzar cierre de sesión
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $db = Database::getMaster();
    $id = (int)($_GET['id'] ?? 0);
    $targetUserId = (int)($_GET['user_id'] ?? 0);

    if ($id > 0) {
        // Close specific session by row ID
        // Verify the session belongs to the admin's institution (unless superadmin)
        $sql = "SELECT s.id, s.session_id, s.usuario_id FROM sesiones_activas s WHERE s.id = ?";
        $params = [$id];
        if ($_SESSION['user_rol'] !== 'superadmin') {
            $sql .= " AND s.institucion_id = ?";
            $params[] = $instId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $sess = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sess) api_error('Sesión no encontrada', 404);

        // Don't allow closing own session via this endpoint
        if ($sess['session_id'] === session_id()) {
            api_error('No puedes cerrar tu propia sesión desde aquí', 400);
        }

        // Delete PHP session file
        $sessPath = session_save_path() ?: sys_get_temp_dir();
        $sessFile = $sessPath . '/sess_' . $sess['session_id'];
        if (is_file($sessFile)) {
            @unlink($sessFile);
        }

        // Remove from DB
        $db->prepare("DELETE FROM sesiones_activas WHERE id = ?")->execute([$id]);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'sesion_cerrar_propia',
            'modulo'         => 'Seguridad',
            'detalle'        => "Sesión #{$id} del usuario #{$sess['usuario_id']}",
        ]);

        api_ok(null, 'Sesión finalizada');
    } elseif ($targetUserId > 0) {
        // Close ALL sessions of a specific user (except the current one)
        if ($_SESSION['user_rol'] !== 'superadmin') {
            // Verify the target user is in the admin's institution
            $check = $db->prepare(
                "SELECT 1 FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ? AND estado = 'activo' LIMIT 1"
            );
            $check->execute([$targetUserId, $instId]);
            if (!$check->fetch()) api_error('Usuario no encontrado en esta institución', 404);
        }

        // Get all session IDs for this user except current
        $stmt = $db->prepare("SELECT id, session_id FROM sesiones_activas WHERE usuario_id = ? AND session_id != ?");
        $stmt->execute([$targetUserId, session_id()]);
        $toDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sessPath = session_save_path() ?: sys_get_temp_dir();
        foreach ($toDelete as $s) {
            $sessFile = $sessPath . '/sess_' . $s['session_id'];
            if (is_file($sessFile)) @unlink($sessFile);
        }

        $db->prepare("DELETE FROM sesiones_activas WHERE usuario_id = ? AND session_id != ?")
           ->execute([$targetUserId, session_id()]);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'sesion_cerrar_usuario',
            'modulo'         => 'Seguridad',
            'detalle'        => "Todas las sesiones del usuario #{$targetUserId}",
        ]);

        api_ok(null, 'Sesiones del usuario finalizadas');
    } else {
        api_error('Se requiere id o user_id', 400);
    }
}

api_error('Método no soportado', 405);
