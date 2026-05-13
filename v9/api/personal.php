<?php
/**
 * GeriApp — API /api/personal.php
 *
 * GET    /api/personal.php             → usuarios de la institución
 * GET    /api/personal.php?id=N        → detalle de un usuario
 * POST   /api/personal.php             → crear usuario
 * PUT    /api/personal.php?id=N        → editar usuario
 * DELETE /api/personal.php?id=N        → desactivar usuario (soft)
 *
 * También:
 * POST action=cambiar_password         → cambiar contraseña (propio o admin)
 * POST action=toggle_estado            → activar/desactivar
 * POST action=cambiar_rol              → cambiar rol (solo admin/superadmin)
 *
 * Roles: admin, superadmin (lectura: medico, enfermero)
 */

require_once __DIR__ . '/helpers.php';

/**
 * Helper: find an institution ID that has SMTP configured.
 * Used as fallback when the request's institution has no SMTP.
 */
function _arco_fallback_smtp_inst(): ?int {
    try {
        $row = Database::getInstance()
            ->query("SELECT id FROM configuracion WHERE smtp_host IS NOT NULL AND smtp_host != '' AND smtp_from_email IS NOT NULL AND smtp_from_email != '' LIMIT 1")
            ->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

api_auth();

$method = api_method();

// ─────────────────────────────────────────────────────────────────────────────
// GET
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id = api_int('id');

    if ($id > 0) {
        $u = Usuario::getById($id);
        if (!$u) api_error('Usuario no encontrado', 404);

        // Scoping: superadmin ve todo; demás verifican acceso vía pivot o institucion_id
        if (api_rol() !== 'superadmin') {
            $instActiva = api_inst_id();
            $acceso = UsuarioInstitucion::hasAccess($id, $instActiva)
                   || (int)($u['institucion_id'] ?? 0) === $instActiva;
            if (!$acceso) api_error('Acceso denegado', 403);
        }
        unset($u['password_hash']);

        // Include linked residents
        $instForRes = api_inst_id() ?: (int)($u['institucion_id'] ?? 0);
        if ($instForRes) {
            $u['residentes_vinculados'] = UsuarioResidente::getResidentes($id, $instForRes);
        }

        api_ok($u);
    }

    // Lista: usar pivot para incluir usuarios multi-institución
    $filtros = [
        'rol'      => $_GET['rol']      ?? '',
        'estado'   => $_GET['estado']   ?? '',
        'busqueda' => $_GET['busqueda'] ?? '',
    ];

    if (api_rol() === 'superadmin') {
        // Si pasa ?institucion_id=N → filtra por esa institución usando el pivot
        // (mismo comportamiento que admin para una vista sin "fugas" de otras
        // instituciones desde el panel de configuración).
        $inst_id = api_int('institucion_id') ?: (api_inst_id() ?: null);
        if ($inst_id) {
            $lista = UsuarioInstitucion::getByInstitucion($inst_id, array_filter($filtros));
        } else {
            // Sin institución activa (ej. superadmin antes de seleccionar) → vista global.
            $lista = Usuario::getAll(null, array_filter($filtros));
        }
    } else {
        // Admin / médico / enfermero: siempre scoped a su institución activa vía pivot.
        $lista = UsuarioInstitucion::getByInstitucion(api_inst_id(), array_filter($filtros));
    }

    foreach ($lista as &$u) unset($u['password_hash']);

    // Include linked resident IDs for each user
    $instForLinks = api_inst_id();
    if ($instForLinks) {
        foreach ($lista as &$u2) {
            $uid = (int)($u2['usuario_id'] ?? $u2['user_id'] ?? $u2['id'] ?? 0);
            if ($uid) {
                try {
                    $u2['residentes_ids'] = UsuarioResidente::getResidenteIds($uid, $instForLinks);
                } catch (\Throwable $e) {
                    $u2['residentes_ids'] = [];
                }
            }
        }
        unset($u2);
    }

    api_ok($lista);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — crear o acción
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = api_body();
    $action = $body['action'] ?? '';

    // ── Crear usuario ────────────────────────────────────────────────────────
    if (empty($action)) {
        api_auth_roles(['superadmin', 'admin']);

        $errors = [];
        if (empty($body['nombre']))   $errors['nombre']   = 'Requerido';
        if (empty($body['email']))    $errors['email']    = 'Requerido';
        if (empty($body['password'])) $errors['password'] = 'Requerido';
        if (empty($body['rol']))      $errors['rol']      = 'Requerido';
        if (!empty($errors)) api_error('Datos inválidos', 422, $errors);

        if (strlen($body['password']) < 8) {
            api_error('La contraseña debe tener al menos 8 caracteres', 422);
        }

        // Email único
        if (Usuario::findByEmail(trim($body['email']))) {
            api_error('El email ya está registrado', 409);
        }

        $inst_id = api_rol() === 'superadmin'
            ? ((int)($body['institucion_id'] ?? 0) ?: null)
            : api_inst_id();

        $data = [
            'institucion_id' => $inst_id,
            'nombre'         => trim($body['nombre']),
            'email'          => strtolower(trim($body['email'])),
            'password_hash'  => password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'rol'            => api_role_storage($body['rol'] ?? 'cuidador'),
            'estado'         => 'activo',
        ];

        $newId = Usuario::create($data);
        if (!$newId) api_error('Error al crear usuario', 500);

        // Agregar al pivot usuario_instituciones
        if ($inst_id) {
            $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
            $rolPivot     = in_array($data['rol'], $rolesValidos, true) ? $data['rol'] : 'enfermero';
            UsuarioInstitucion::add($newId, $inst_id, $rolPivot);
        }

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'usuario_crear',
            'modulo'         => 'personal',
            'detalle'        => "ID {$newId}: {$data['nombre']} ({$data['rol']})",
        ]);

        $u = Usuario::getById($newId);
        unset($u['password_hash']);
        api_ok($u, 'Usuario creado correctamente');
    }

    // ── Verificar contraseña (para biometría) ──────────────────────────────
    if ($action === 'verificar_password') {
        if (empty($body['password'])) api_error('Contraseña requerida', 422);
        $u = Usuario::getById(api_user_id());
        if (!$u) api_error('Usuario no encontrado', 404);
        if (!password_verify($body['password'], $u['password_hash'])) {
            api_error('Contraseña incorrecta', 401);
        }
        api_ok(null, 'ok');
    }

    // ── Cambiar contraseña ───────────────────────────────────────────────────
    if ($action === 'cambiar_password') {
        $target_id = (int)($body['usuario_id'] ?? api_user_id());
        $propio    = ($target_id === api_user_id());

        if (!$propio) {
            api_auth_roles(['superadmin', 'admin']);
        }

        if (empty($body['password'])) api_error('Contraseña requerida', 422);
        if (strlen($body['password']) < 8) api_error('Mínimo 8 caracteres', 422);

        $u = Usuario::getById($target_id);
        if (!$u) api_error('Usuario no encontrado', 404);
        if (!$propio && api_rol() !== 'superadmin' && (int)$u['institucion_id'] !== api_inst_id()) {
            api_error('Acceso denegado', 403);
        }

        // Si es el propio usuario, verificar contraseña actual
        if ($propio && !empty($body['current_password'])) {
            if (!password_verify($body['current_password'], $u['password_hash'])) {
                api_error('Contraseña actual incorrecta', 401);
            }
        }

        Usuario::updatePassword($target_id, password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]));

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'password_cambiar',
            'modulo'         => 'personal',
            'detalle'        => "Usuario ID {$target_id}",
        ]);

        api_ok(null, 'Contraseña actualizada');
    }

    // ── Toggle estado ────────────────────────────────────────────────────────
    if ($action === 'toggle_estado') {
        api_auth_roles(['superadmin', 'admin']);

        $target_id = (int)($body['usuario_id'] ?? 0);
        if (!$target_id) api_error('usuario_id requerido', 400);

        $u = Usuario::getById($target_id);
        if (!$u) api_error('Usuario no encontrado', 404);
        if (api_rol() !== 'superadmin') {
            $instActiva = api_inst_id();
            $acceso = UsuarioInstitucion::hasAccess($target_id, $instActiva)
                   || (int)($u['institucion_id'] ?? 0) === $instActiva;
            if (!$acceso) api_error('Acceso denegado', 403);
        }
        if ($target_id === api_user_id()) api_error('No puedes suspenderte a ti mismo', 400);

        Usuario::toggleEstado($target_id);

        // Sincronizar estado en el pivot (solo para la institución activa)
        $instActiva = (int)$u['institucion_id'];
        if ($instActiva) {
            $nuevo_estado = ($u['estado'] === 'activo') ? 'inactivo' : 'activo';
            UsuarioInstitucion::update($target_id, $instActiva, ['estado' => $nuevo_estado]);
        }

        $nuevo = Usuario::getById($target_id);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'usuario_toggle_estado',
            'modulo'         => 'personal',
            'detalle'        => "Usuario ID {$target_id} → {$nuevo['estado']}",
        ]);

        api_ok(['estado' => $nuevo['estado']], 'Estado actualizado');
    }

    // ── Cambiar rol ──────────────────────────────────────────────────────────
    if ($action === 'cambiar_rol') {
        api_auth_roles(['superadmin', 'admin']);

        $target_id = (int)($body['usuario_id'] ?? 0);
        $nuevo_rol = api_role_storage($body['rol'] ?? '');
        $roles_validos = ['admin', 'medico', 'enfermero', 'familiar'];
        if (api_rol() === 'superadmin') $roles_validos[] = 'superadmin';

        if (!$target_id || !in_array($nuevo_rol, $roles_validos, true)) {
            api_error('usuario_id y rol válido requeridos', 422);
        }

        $u = Usuario::getById($target_id);
        if (!$u) api_error('Usuario no encontrado', 404);
        if (api_rol() !== 'superadmin') {
            $instActiva = api_inst_id();
            $acceso = UsuarioInstitucion::hasAccess($target_id, $instActiva)
                   || (int)($u['institucion_id'] ?? 0) === $instActiva;
            if (!$acceso) api_error('Acceso denegado', 403);
        }

        Usuario::update($target_id, ['rol' => $nuevo_rol]);

        // Sincronizar rol en el pivot para la institución activa
        $instActiva = api_inst_id() ?: (int)$u['institucion_id'];
        if ($instActiva && in_array($nuevo_rol, ['admin','medico','enfermero','familiar'], true)) {
            UsuarioInstitucion::update($target_id, $instActiva, ['rol' => $nuevo_rol]);
        }

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'usuario_cambiar_rol',
            'modulo'         => 'personal',
            'detalle'        => "Usuario ID {$target_id}: {$u['rol']} → {$nuevo_rol}",
        ]);

        api_ok(['rol' => $nuevo_rol], 'Rol actualizado');
    }

    // ── Actualizar perfil propio ─────────────────────────────────────────────
    if ($action === 'update_profile') {
        $target_id = api_user_id();
        $u = Usuario::getById($target_id);
        if (!$u) api_error('Usuario no encontrado', 404);

        $allowed = ['nombre', 'email', 'telefono', 'preferencias'];
        $data = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $body)) {
                if ($k === 'preferencias') {
                    $data[$k] = is_array($body[$k]) ? json_encode($body[$k]) : $body[$k];
                } else {
                    $data[$k] = is_string($body[$k]) ? trim($body[$k]) : $body[$k];
                }
            }
        }
        if (empty($data)) api_error('Sin campos para actualizar', 422);

        if (empty($data['nombre'] ?? $u['nombre'])) api_error('El nombre es requerido', 422);

        // Email único si cambia (excluir al propio usuario)
        if (!empty($data['email']) && strtolower($data['email']) !== strtolower($u['email'])) {
            $existing = Usuario::findByEmail($data['email']);
            if ($existing && (int)$existing['id'] !== $target_id) {
                api_error('El email ya está en uso', 409);
            }
        }

        Usuario::update($target_id, $data);

        // Actualizar sesión (ya iniciada por config.php)
        if (isset($data['nombre'])) $_SESSION['user_nombre'] = $data['nombre'];
        if (isset($data['email']))  $_SESSION['user_email']  = $data['email'];

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'usuario_actualizar_perfil',
            'modulo'         => 'personal',
            'detalle'        => "Perfil propio actualizado",
        ]);

        $updated = Usuario::getById($target_id);
        unset($updated['password_hash']);
        api_ok($updated, 'Perfil actualizado');
    }

    // ── Sincronizar residentes vinculados ───────────────────────────────────────
    if ($action === 'sync_residentes') {
        api_auth_roles(['superadmin', 'admin']);

        $target_id     = (int)($body['usuario_id'] ?? 0);
        $residente_ids = $body['residente_ids'] ?? [];
        if (!$target_id) api_error('usuario_id requerido', 400);
        if (!is_array($residente_ids)) api_error('residente_ids debe ser un array', 422);

        $u = Usuario::getById($target_id);
        if (!$u) api_error('Usuario no encontrado', 404);

        $instId = api_inst_id();
        // Validate all residente_ids belong to this institution
        $residente_ids = array_map('intval', $residente_ids);
        if (!empty($residente_ids)) {
            $db = Database::getTenant($instId);
            $placeholders = implode(',', array_fill(0, count($residente_ids), '?'));
            $stmt = $db->prepare("SELECT id FROM residentes WHERE id IN ($placeholders) AND institucion_id = ?");
            $stmt->execute(array_merge($residente_ids, [$instId]));
            $valid = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $residente_ids = array_map('intval', $valid);
        }

        UsuarioResidente::sync($target_id, $residente_ids, $instId);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'usuario_sync_residentes',
            'modulo'         => 'personal',
            'detalle'        => "Usuario ID {$target_id}: " . count($residente_ids) . ' residentes vinculados',
        ]);

        api_ok(['residente_ids' => $residente_ids], 'Residentes actualizados');
    }

    // ── Obtener residentes vinculados ────────────────────────────────────────
    if ($action === 'get_residentes') {
        $target_id = (int)($body['usuario_id'] ?? 0);
        if (!$target_id) api_error('usuario_id requerido', 400);

        $instId = api_inst_id();
        $residentes = UsuarioResidente::getResidentes($target_id, $instId);
        api_ok($residentes);
    }

    // ── ARCO: Acceso — descargar datos personales ──────────────────────────
    if ($action === 'arco_acceso') {
        $userId = api_user_id();
        $instId = api_inst_id();
        $u = Usuario::getById($userId);
        if (!$u) api_error('Usuario no encontrado', 404);

        unset($u['password_hash']);

        // Linked residents summary (no PHI details, just names)
        $residentes = [];
        if ($instId) {
            $residentes = UsuarioResidente::getResidentes($userId, $instId);
        }

        // Log this access request
        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO arco_solicitudes (usuario_id, institucion_id, tipo, estado, descripcion)
             VALUES (?, ?, 'acceso', 'completada', 'Descarga automática de datos personales')"
        )->execute([$userId, $instId]);

        Log::registrar([
            'usuario_id'     => $userId,
            'institucion_id' => $instId,
            'accion'         => 'arco_solicitar_acceso',
            'modulo'         => 'personal',
            'detalle'        => 'Descarga de datos personales (ARCO)',
        ]);

        // ── Enviar correo al usuario + CC a legal_cc_email ────────────────
        try {
            require_once __DIR__ . '/../db/models/Mailer.php';
            require_once __DIR__ . '/../db/models/Configuracion.php';
            $cfg         = $instId ? Configuracion::getOrCreate($instId) : [];
            $ccEmail     = trim($cfg['legal_cc_email'] ?? '');
            $userEmail   = $u['email'] ?? '';
            $userName    = htmlspecialchars($u['nombre'] ?? '', ENT_QUOTES);
            $userEmailHtml = htmlspecialchars($userEmail, ENT_QUOTES);
            $fecha       = date('d/m/Y H:i');

            // Build recipients: user + CC admin if configured and different
            $recipients = array_filter(array_unique([$userEmail,
                ($ccEmail && filter_var($ccEmail, FILTER_VALIDATE_EMAIL) && strtolower($ccEmail) !== strtolower($userEmail)) ? $ccEmail : ''
            ]));

            if (!empty($recipients)) {
                // Resolve which SMTP config to use (inst's own, fallback to any working)
                $smtpInstId = ($cfg['smtp_host'] ?? '') ? $instId : _arco_fallback_smtp_inst();

                $htmlBody = "
<div style='font-family:-apple-system,sans-serif;max-width:520px;margin:0 auto;padding:32px 24px;background:#f8fafc;'>
  <div style='background:#fff;border-radius:12px;padding:28px 24px;border:1px solid #e2e8f0;'>
    <p style='font-size:18px;font-weight:700;color:#033f3f;margin:0 0 8px;'>GeriApp</p>
    <p style='font-size:14px;font-weight:600;color:#1e293b;margin:0 0 16px;'>Confirmación: Acceso a sus datos personales (ARCO)</p>
    <p style='font-size:14px;color:#334155;margin:0 0 12px;'>Hola <strong>{$userName}</strong>, le confirmamos que ha descargado una copia de sus datos personales registrados en GeriApp.</p>
    <table style='width:100%;font-size:13px;border-collapse:collapse;background:#f8fafc;border-radius:8px;padding:12px;'>
      <tr><td style='color:#64748b;padding:6px 8px;'>Tipo de solicitud</td><td style='font-weight:500;padding:6px 8px;'>Acceso (descarga de datos)</td></tr>
      <tr><td style='color:#64748b;padding:6px 8px;'>Correo</td><td style='font-weight:500;padding:6px 8px;'>{$userEmailHtml}</td></tr>
      <tr><td style='color:#64748b;padding:6px 8px;'>Fecha</td><td style='font-weight:500;padding:6px 8px;'>{$fecha}</td></tr>
    </table>
    <p style='font-size:13px;color:#334155;margin:16px 0 0;'>Si usted no realizó esta acción, contáctenos de inmediato a <a href='mailto:info_geriapp@prepenv.com' style='color:#033f3f;'>info_geriapp@prepenv.com</a>.</p>
    <p style='font-size:11px;color:#94a3b8;margin:20px 0 0;'>GeriApp · Gestión geriátrica · LFPDPPP</p>
  </div>
</div>";

                if ($smtpInstId) {
                    $mailer = Mailer::fromConfig($smtpInstId);
                    $mailer->send(array_values($recipients), 'GeriApp — Confirmación: Descarga de datos personales (ARCO)', $htmlBody);
                }
            }
        } catch (\Throwable $e) { /* Email failure must not block the download */ }

        api_ok([
            'usuario'    => $u,
            'residentes_vinculados' => $residentes,
            'fecha_descarga' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── ARCO: Solicitar (rectificación, cancelación, oposición) ────────────
    if ($action === 'arco_solicitud') {
        $userId = api_user_id();
        $instId = api_inst_id();
        $tipo   = $body['tipo'] ?? '';

        if (!in_array($tipo, ['rectificacion', 'cancelacion', 'oposicion'], true)) {
            api_error('Tipo ARCO inválido', 422);
        }

        $descripcion = trim($body['descripcion'] ?? '');
        if (empty($descripcion)) {
            api_error('La descripción es requerida', 422);
        }

        // Prevent duplicate pending requests of same type
        $db = Database::getInstance();
        $existing = $db->prepare(
            "SELECT id FROM arco_solicitudes
             WHERE usuario_id = ? AND tipo = ? AND estado IN ('pendiente','en_proceso')
             LIMIT 1"
        );
        $existing->execute([$userId, $tipo]);
        if ($existing->fetch()) {
            api_error('Ya tiene una solicitud de este tipo en proceso', 409);
        }

        $db->prepare(
            "INSERT INTO arco_solicitudes (usuario_id, institucion_id, tipo, estado, descripcion)
             VALUES (?, ?, ?, 'pendiente', ?)"
        )->execute([$userId, $instId, $tipo, $descripcion]);

        Log::registrar([
            'usuario_id'     => $userId,
            'institucion_id' => $instId,
            'accion'         => 'arco_solicitar_' . $tipo,
            'modulo'         => 'personal',
            'detalle'        => "Solicitud ARCO ({$tipo}): " . mb_substr($descripcion, 0, 100),
        ]);

        // ── Enviar correo al usuario + CC a legal_cc_email ────────────────
        try {
            require_once __DIR__ . '/../db/models/Mailer.php';
            require_once __DIR__ . '/../db/models/Configuracion.php';
            $cfg     = $instId ? Configuracion::getOrCreate($instId) : [];
            $ccEmail = trim($cfg['legal_cc_email'] ?? '');
            $u2      = Usuario::getById($userId);
            $userEmail   = $u2['email'] ?? '';
            $userName    = htmlspecialchars($u2['nombre'] ?? '', ENT_QUOTES);
            $userEmailHtml = htmlspecialchars($userEmail, ENT_QUOTES);
            $fecha   = date('d/m/Y H:i');

            $tipoLabels = [
                'rectificacion' => 'Rectificación de datos',
                'cancelacion'   => 'Cancelación (eliminación de cuenta y datos)',
                'oposicion'     => 'Oposición al tratamiento secundario',
            ];
            $tipoLabel = $tipoLabels[$tipo] ?? $tipo;
            $descHtml  = htmlspecialchars($descripcion, ENT_QUOTES);

            // Recipients: user always, admin CC if configured & different
            $recipients = array_filter(array_unique([$userEmail,
                ($ccEmail && filter_var($ccEmail, FILTER_VALIDATE_EMAIL) && strtolower($ccEmail) !== strtolower($userEmail)) ? $ccEmail : ''
            ]));

            if (!empty($recipients)) {
                $smtpInstId = ($cfg['smtp_host'] ?? '') ? $instId : _arco_fallback_smtp_inst();

                $htmlBody = "
<div style='font-family:-apple-system,sans-serif;max-width:520px;margin:0 auto;padding:32px 24px;background:#f8fafc;'>
  <div style='background:#fff;border-radius:12px;padding:28px 24px;border:1px solid #e2e8f0;'>
    <p style='font-size:18px;font-weight:700;color:#033f3f;margin:0 0 8px;'>GeriApp</p>
    <p style='font-size:14px;font-weight:600;color:#1e293b;margin:0 0 16px;'>Solicitud ARCO recibida: {$tipoLabel}</p>
    <p style='font-size:14px;color:#334155;margin:0 0 12px;'>Hola <strong>{$userName}</strong>, hemos recibido su solicitud de derechos ARCO. La atenderemos en un plazo máximo de <strong>20 días hábiles</strong>.</p>
    <table style='width:100%;font-size:13px;border-collapse:collapse;background:#f8fafc;border-radius:8px;padding:12px;'>
      <tr><td style='color:#64748b;padding:6px 8px;'>Tipo</td><td style='font-weight:500;padding:6px 8px;'>{$tipoLabel}</td></tr>
      <tr><td style='color:#64748b;padding:6px 8px;'>Correo</td><td style='font-weight:500;padding:6px 8px;'>{$userEmailHtml}</td></tr>
      <tr><td style='color:#64748b;padding:6px 8px;'>Fecha</td><td style='font-weight:500;padding:6px 8px;'>{$fecha}</td></tr>
    </table>
    <div style='margin:16px 0 0;padding:12px;background:#f1f5f9;border-radius:8px;font-size:13px;color:#334155;'>
      <strong>Descripción:</strong><br>{$descHtml}
    </div>
    <p style='font-size:13px;color:#334155;margin:16px 0 0;'>Si tiene dudas, contáctenos a <a href='mailto:info_geriapp@prepenv.com' style='color:#033f3f;'>info_geriapp@prepenv.com</a>.</p>
    <p style='font-size:11px;color:#94a3b8;margin:20px 0 0;'>GeriApp · Gestión geriátrica · LFPDPPP</p>
  </div>
</div>";

                if ($smtpInstId) {
                    $mailer = Mailer::fromConfig($smtpInstId);
                    $mailer->send(array_values($recipients), "GeriApp — Solicitud ARCO: {$tipoLabel}", $htmlBody);
                }
            }
        } catch (\Throwable $e) { /* Email failure must not block response */ }

        api_ok(null, 'Solicitud registrada');
    }

    // ── ARCO: Historial de solicitudes ──────────────────────────────────────
    if ($action === 'arco_historial') {
        $userId = api_user_id();
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, tipo, estado, descripcion, respuesta, creado_at, respondido_at
             FROM arco_solicitudes
             WHERE usuario_id = ?
             ORDER BY creado_at DESC
             LIMIT 50"
        );
        $stmt->execute([$userId]);
        api_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    api_error('Acción no reconocida', 400);
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — editar usuario
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    api_auth_roles(['superadmin', 'admin']);

    $id = api_int('id');
    if (!$id) api_error('ID requerido', 400);

    $u = Usuario::getById($id);
    if (!$u) api_error('Usuario no encontrado', 404);
    if (api_rol() !== 'superadmin') {
        $instActiva = api_inst_id();
        $acceso = UsuarioInstitucion::hasAccess($id, $instActiva)
               || (int)($u['institucion_id'] ?? 0) === $instActiva;
        if (!$acceso) api_error('Acceso denegado', 403);
    }

    $body    = api_body();
    $allowed = ['nombre', 'email', 'rol', 'estado', 'avatar_path', 'telefono'];
    $data    = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $body)) {
            $data[$k] = is_string($body[$k]) ? trim($body[$k]) : $body[$k];
        }
    }
    if (empty($data)) api_error('Sin campos para actualizar', 422);

    // Email único si cambia (excluir al propio usuario)
    if (!empty($data['email']) && strtolower($data['email']) !== strtolower($u['email'])) {
        $existing = Usuario::findByEmail($data['email']);
        if ($existing && (int)$existing['id'] !== $id) {
            api_error('El email ya está en uso', 409);
        }
    }

    Usuario::update($id, $data);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'usuario_actualizar',
        'modulo'         => 'personal',
        'detalle'        => "ID {$id}",
    ]);

    $updated = Usuario::getById($id);
    unset($updated['password_hash']);
    api_ok($updated, 'Usuario actualizado');
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — desactivar (soft)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    api_auth_roles(['superadmin', 'admin']);

    $id = api_int('id');
    if (!$id) api_error('ID requerido', 400);
    if ($id === api_user_id()) api_error('No puedes eliminarte a ti mismo', 400);

    $u = Usuario::getById($id);
    if (!$u) api_error('Usuario no encontrado', 404);
    if (api_rol() !== 'superadmin') {
        $instActiva = api_inst_id();
        $acceso = UsuarioInstitucion::hasAccess($id, $instActiva)
               || (int)($u['institucion_id'] ?? 0) === $instActiva;
        if (!$acceso) api_error('Acceso denegado', 403);
    }

    // Hard delete: eliminación permanente
    $ok = Usuario::delete($id);
    if (!$ok) api_error('Error al eliminar usuario', 500);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'usuario_eliminar',
        'modulo'         => 'personal',
        'detalle'        => "ID {$id}: {$u['nombre']}",
    ]);

    api_ok(null, 'Usuario eliminado permanentemente');
}

api_error('Método no soportado', 405);
