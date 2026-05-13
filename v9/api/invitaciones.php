<?php
/**
 * GeriApp — API /api/invitaciones.php
 *
 * GET  /api/invitaciones.php              → lista invitaciones de la institución
 * POST /api/invitaciones.php              → crear invitación  (body: email, rol, dias, mensaje)
 * POST action=reenviar                    → reenviar         (body: id)
 * POST action=revocar                     → eliminar (compat) (body: id)
 * POST action=eliminar                    → borrar           (body: id)
 * POST action=actualizar                  → editar datos     (body: id, email, telefono, rol, nombre_sugerido, apellido_sugerido, mensaje)
 *
 * Roles permitidos: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

api_auth();
api_auth_roles(['admin', 'superadmin']);

$method = api_method();

function invitaciones_role_rank(?string $rol): int
{
  return match (api_role_storage($rol ?? '')) {
    'admin' => 4,
    'medico' => 3,
    'enfermero' => 2,
    'familiar' => 1,
    default => 0,
  };
}

function invitaciones_role_label(?string $rol): string
{
  return match (api_role_storage($rol ?? '')) {
    'admin' => 'Admin',
    'medico' => 'Médico',
    'enfermero' => 'Cuidador',
    'familiar' => 'Familiar',
    default => 'Usuario',
  };
}

function invitaciones_lower_role_conflict(int $instId, string $rol, string $email = '', ?string $telefono = null, ?int $excludeInviteId = null): ?array
{
  $email = strtolower(trim($email));
  $telefono = trim((string)$telefono);
  if ($email === '' && $telefono === '') return null;

  $requestedRank = invitaciones_role_rank($rol);
  $db = Database::getInstance();
  $or = [];
  $params = [$instId];
  if ($email !== '') { $or[] = 'LOWER(u.email) = ?'; $params[] = $email; }
  if ($telefono !== '') { $or[] = 'u.telefono = ?'; $params[] = $telefono; }
  if ($or) {
    $stmt = $db->prepare(
      "SELECT u.id, u.nombre, u.email, u.telefono, COALESCE(ui.rol, u.rol) AS rol
       FROM usuarios u
       INNER JOIN usuario_instituciones ui ON ui.usuario_id = u.id
       WHERE ui.institucion_id = ? AND ui.estado = 'activo' AND u.estado = 'activo'
         AND (" . implode(' OR ', $or) . ")
       LIMIT 1"
    );
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && invitaciones_role_rank($user['rol'] ?? '') > $requestedRank) {
      return ['kind' => 'user', 'rol' => api_role_storage($user['rol'] ?? ''), 'label' => invitaciones_role_label($user['rol'] ?? '')];
    }
  }

  $or = [];
  $params = [$instId];
  if ($excludeInviteId) $params[] = $excludeInviteId;
  if ($email !== '') { $or[] = 'LOWER(email) = ?'; $params[] = $email; }
  if ($telefono !== '') { $or[] = 'telefono = ?'; $params[] = $telefono; }
  if ($or) {
    $excludeSql = $excludeInviteId ? 'AND id <> ?' : '';
    $stmt = $db->prepare(
      "SELECT id, rol, estado
       FROM invitaciones
       WHERE institucion_id = ? {$excludeSql}
         AND estado IN ('pendiente','enviada')
         AND (" . implode(' OR ', $or) . ")
       ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute($params);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($inv && invitaciones_role_rank($inv['rol'] ?? '') > $requestedRank) {
      return ['kind' => 'invite', 'rol' => api_role_storage($inv['rol'] ?? ''), 'label' => invitaciones_role_label($inv['rol'] ?? '')];
    }
  }

  return null;
}

function invitaciones_wa_text(string $instNombre, string $rolLabel, string $registerLink, ?string $mensaje = null, bool $resent = false): string
{
    $prefix = $resent ? 'Te reenviamos la invitación' : 'Te invito';
    $text = "{$prefix} a unirte a *{$instNombre}* en GeriApp como *{$rolLabel}*.";
    $mensaje = trim((string)$mensaje);
    if ($mensaje !== '') {
        $text .= "\n\n{$mensaje}";
    }
    return $text . "\n\nRegístrate aquí:\n\n" . trim($registerLink) . "\n\nSi WhatsApp no lo abre, copia y pega el enlace en tu navegador.";
}

function invitaciones_send_whatsapp(int $instId, ?string $phone, string $rol, string $registerLink, ?string $mensaje = null, bool $resent = false): array
{
    $phone = trim((string)$phone);
    if ($phone === '') return ['ok' => false, 'skipped' => true, 'error' => 'Sin teléfono'];

    try {
        $inst = Institucion::getById($instId);
        $instNombre = $inst['nombre'] ?? 'GeriApp';
        $rolLabels = [
            'admin'     => 'Administrador',
            'medico'    => 'Médico/a',
          'enfermero' => 'Cuidador/a',
          'cuidador'  => 'Cuidador/a',
            'familiar'  => 'Familiar',
        ];
        $rolLabel = $rolLabels[$rol] ?? ucfirst($rol);
        $wa = WaSenderAPI::fromConfig($instId);
        return $wa->sendText($phone, invitaciones_wa_text($instNombre, $rolLabel, $registerLink, $mensaje, $resent));
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

  function invitaciones_resident_ids_for_log(int $instId, string $rol, array $residenteIds): array
  {
    $ids = array_values(array_filter(array_unique(array_map('intval', $residenteIds)), fn($v) => $v > 0));
    if (empty($ids) && in_array($rol, ['medico','enfermero'], true)) {
      try {
        $ids = array_map(fn($r) => (int)$r['id'], Residente::getAll($instId, ['estado' => 'activo']));
      } catch (Throwable $e) {
        $ids = [];
      }
    }
    return $ids ?: [null];
  }

  function invitaciones_log_notification(int $instId, array $residentIds, string $destinatario, string $canal, string $tipo, string $mensaje, string $estado = 'enviado', ?string $error = null): void
  {
    if ($destinatario === '') return;
    try {
      $db = Database::getTenant($instId);
      $db->exec("CREATE TABLE IF NOT EXISTS notificaciones_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        institucion_id INT NOT NULL,
        residente_id INT,
        destinatario VARCHAR(255),
        canal VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
        tipo VARCHAR(50),
        mensaje TEXT,
        estado VARCHAR(20) NOT NULL DEFAULT 'enviado',
        error_detalle TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(institucion_id, residente_id, fecha)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $encMessage = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $mensaje])['mensaje'] ?? $mensaje;
      $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      foreach ($residentIds as $resId) {
        $st->execute([$instId, $resId ? (int)$resId : null, $destinatario, $canal, $tipo, $encMessage, $estado, $error]);
        EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $mensaje]);
      }
    } catch (Throwable $e) {
      // Logging must never block invitation delivery.
    }
  }

// ─────────────────────────────────────────────────────────────────────────────
// GET — listar invitaciones
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $inst_id = api_inst_id();
    if (!$inst_id) api_error('Institución no disponible', 403);

    $registeredEmails = [];
    $registeredPhones = [];
    $registeredUsers = [];
    try {
        require_once __DIR__ . '/../db/models/UsuarioResidente.php';
        $db = Database::getMaster();
        $stmt = $db->prepare(
            "SELECT DISTINCT u.id, u.nombre, u.email, u.telefono, u.creado_at, COALESCE(ui.rol, u.rol) AS rol
             FROM usuarios u
             LEFT JOIN usuario_instituciones ui
               ON ui.usuario_id = u.id AND ui.institucion_id = ? AND ui.estado = 'activo'
             WHERE u.estado = 'activo'
               AND (u.institucion_id = ? OR ui.institucion_id = ?)"
        );
        $stmt->execute([$inst_id, $inst_id, $inst_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $email = mb_strtolower(trim((string)($u['email'] ?? '')));
            $phone = preg_replace('/\D+/', '', (string)($u['telefono'] ?? '')) ?: '';
            if ($email !== '') $registeredEmails[$email] = true;
            if ($phone !== '') $registeredPhones[$phone] = true;
            $resList = UsuarioResidente::getResidentes((int)$u['id'], $inst_id);
            $registeredUsers[] = [
              'id'      => 'user-' . (int)$u['id'],
              'usuario_id' => (int)$u['id'],
              'registered' => true,
              'email'   => $u['email'] ?? '',
              'telefono' => $u['telefono'] ?? null,
              'rol'     => api_role_storage($u['rol'] ?? 'familiar'),
              'status'  => 'registrado',
              'enviada' => $u['creado_at'] ?? '',
              'expira'  => null,
              'mensaje' => null,
              'token'   => null,
              'creado_por_nombre' => null,
              'residente_ids' => array_values(array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $resList))),
              'nombre_sugerido' => $u['nombre'] ?? null,
              'apellido_sugerido' => null,
            ];
        }
    } catch (\Throwable $e) {}

    $rows = Invitacion::getAll($inst_id);
    $rows = array_values(array_filter($rows, function($r) use ($registeredEmails, $registeredPhones) {
        $email = mb_strtolower(trim((string)($r['email'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string)($r['telefono'] ?? '')) ?: '';
      if ($email === '' && $phone === '') return false;
        return !(($email !== '' && !empty($registeredEmails[$email])) || ($phone !== '' && !empty($registeredPhones[$phone])));
    }));
    // Normalizar para el frontend
    $data = array_map(function($r) {
      $expira = null;
      if (($r['estado'] ?? '') === 'expirada' && !empty($r['expires_at']) && substr((string)$r['expires_at'], 0, 4) !== '9999') {
        $expira = $r['expires_at'];
      }
      return [
        'id'      => (int)$r['id'],
        'email'   => $r['email'],
        'rol'     => $r['rol'],
        'status'  => $r['estado'],
        'enviada' => $r['creado_at'],
      'expira'  => $expira,
        'mensaje' => $r['mensaje'],
        'token'   => $r['token'],
        'telefono' => $r['telefono'] ?? null,
        'creado_por_nombre' => $r['creado_por_nombre'] ?? null,
        'residente_ids' => !empty($r['residente_ids']) ? (json_decode((string)$r['residente_ids'], true) ?: []) : [],
        'nombre_sugerido'  => $r['nombre_sugerido'] ?? null,
        'apellido_sugerido' => $r['apellido_sugerido'] ?? null,
        'multi_uso'  => isset($r['multi_uso']) ? (int)$r['multi_uso'] : 0,
        'usos_count' => isset($r['usos_count']) ? (int)$r['usos_count'] : 0,
        'permite_nueva_institucion' => isset($r['permite_nueva_institucion']) ? (int)$r['permite_nueva_institucion'] : 0,
        'dias_prueba' => isset($r['dias_prueba']) ? (int)$r['dias_prueba'] : null,
        ];
      }, $rows);
    api_ok(array_merge($registeredUsers, $data));
}

// ─────────────────────────────────────────────────────────────────────────────
// POST
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $inst_id = api_inst_id();
    if (!$inst_id) api_error('Institución no disponible', 403);

    // Resolve base URL: prefer per-institution override, fall back to server-detected full URL
    $_cfg    = Configuracion::getCached($inst_id);
    $_appUrl = rtrim($_cfg['app_url'] ?? '', '/') ?: app_public_url();

    $body   = api_body();
    $action = $body['action'] ?? '';

    // ── Crear invitación ─────────────────────────────────────────────────────
    if (empty($action)) {
        $email = strtolower(trim($body['email'] ?? ''));
        $rol   = api_role_storage($body['rol'] ?? 'cuidador');
        $dias  = max(1, (int)($body['dias'] ?? 7));
        $msg   = trim($body['mensaje'] ?? '') ?: null;
        $nombreSug   = trim($body['nombre_sugerido'] ?? '') ?: null;
        $apellidoSug = trim($body['apellido_sugerido'] ?? '') ?: null;
        $telefono    = api_normalize_phone_mx(trim($body['telefono'] ?? '')) ?: null;
        $qrOnly      = !empty($body['qr_only']);
        $whatsappOnly = !empty($body['whatsapp_only']);
        $multiUso    = !empty($body['multi_uso']);
        $permiteNuevaInst = !empty($body['permite_nueva_institucion']);
        $diasPrueba  = isset($body['dias_prueba']) ? (int)$body['dias_prueba'] : 0;

        // Para invitaciones QR o solo WhatsApp el email es opcional (lo capturará el invitado al registrarse).
        if ($qrOnly || $whatsappOnly) {
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                api_error('Correo electrónico inválido', 422);
            }
          if ($whatsappOnly && !$telefono) {
            api_error('Teléfono WhatsApp requerido', 422);
          }
        } else {
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                api_error('Correo electrónico inválido', 422);
            }
        }
        $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
        if (!in_array($rol, $rolesValidos, true)) {
            api_error('Rol inválido', 422);
        }

        // ── Restricciones HIPAA/LFPDPPP para modo multi-uso ────────────────
        // El modo multi-uso permite que cualquier persona que escanee el QR
        // se registre en la institución. Por minimización de PHI:
        //   1. Solo se permite con qr_only (no para invites por email).
        //   2. Se proh\u00edbe el rol 'familiar' (vincular\u00eda a residentes).
        //   3. Se vacía residente_ids: ningún registro nuevo queda
        //      preasociado a residentes; un admin debe asignarlos despu\u00e9s.
        //   4. Se ignora email/telefono/nombre/apellido sugeridos: el QR es
        //      gen\u00e9rico, no personalizado.
        $residenteIdsForCreate = $body['residente_ids'] ?? [];
        if (is_array($residenteIdsForCreate)) {
          $residenteIdsForCreate = array_values(array_filter(array_unique(array_map('intval', $residenteIdsForCreate)), fn($v) => $v > 0));
        } else {
          $residenteIdsForCreate = [];
        }
        if ($multiUso) {
            if (!$qrOnly) {
                api_error('El modo multi-uso solo está disponible para invitaciones por QR', 422);
            }
            if ($rol === 'familiar') {
                api_error('El modo multi-uso no está permitido para el rol familiar', 422);
            }
            $residenteIdsForCreate = [];
            $email       = '';
            $telefono    = null;
            $nombreSug   = null;
            $apellidoSug = null;
        }

        if (!$multiUso && !$qrOnly && $email === '' && !$telefono) {
            api_error('Agrega un correo o teléfono WhatsApp para crear la invitación', 422);
        }

          if (!$multiUso && $rol !== 'admin' && empty($residenteIdsForCreate)) {
            api_error('Selecciona al menos un residente para este rol', 422);
          }

        // ── Validaciones de "crear nueva institución" + días de prueba ──
        // Solo permitido en combinación con multi_uso (modo evento). Además,
        // si el invitado podrá crear su propia institución, lo natural es que
        // ingrese como administrador de esa nueva institución; forzamos rol=admin.
        if ($permiteNuevaInst) {
            if (!$multiUso) {
                api_error('La opción "crear nueva institución" requiere modo evento (multi-uso).', 422);
            }
            $rol = 'admin';
        }
        // Acotar días de prueba a 0..365.
        if ($diasPrueba < 0) $diasPrueba = 0;
        if ($diasPrueba > 365) $diasPrueba = 365;

        $roleConflict = invitaciones_lower_role_conflict($inst_id, $rol, $email, $telefono);
        if ($roleConflict) {
          api_error('Esta persona ya tiene ' . ($roleConflict['kind'] === 'invite' ? 'una invitación' : 'un usuario') . ' con rol superior (' . $roleConflict['label'] . ') en esta institución.', 409);
        }

        $result = Invitacion::create([
            'institucion_id' => $inst_id,
            'email'          => ($qrOnly || $whatsappOnly) && !$email ? null : $email,
            'rol'            => $rol,
            'mensaje'        => $msg,
            'dias'           => $dias,
            'creado_por'     => (int)$_SESSION['user_id'],
            'residente_ids'  => $residenteIdsForCreate,
            'nombre_sugerido'  => $nombreSug,
            'apellido_sugerido' => $apellidoSug,
            'telefono'          => $telefono,
            'multi_uso'         => $multiUso,
            'permite_nueva_institucion' => $permiteNuevaInst,
            'dias_prueba'       => $diasPrueba ?: null,
        ]);
        if (!$result) api_error('Error al crear invitación', 500);

        $registerLink = $_appUrl . '/register.php?inv=' . $result['token'];

        // Para invitaciones QR no se envía correo: se devuelve el enlace para mostrar el QR.
        if ($qrOnly) {
            try {
                if (class_exists('Log')) {
                    Log::registrar([
                        'usuario_id'     => (int)$_SESSION['user_id'],
                        'institucion_id' => $inst_id,
                        'accion'         => 'invitacion_qr_creada',
                        'modulo'         => 'Invitaciones',
                        'estado'         => 'ok',
                    ]);
                }
            } catch (\Throwable $e) { /* no fatal */ }
            api_ok([
                'id'    => (int)$result['id'],
                'token' => $result['token'],
                'url'   => $registerLink,
                'rol'   => $rol,
                'qr'    => true,
            ]);
        }

        // ── Enviar correo de invitación (no fatal) ───────────────────────────
        $emailSent = false;
        $emailError = null;
        $textBody = '';
        if ($email && !$whatsappOnly) try {
            $inst       = Institucion::getById($inst_id);
            $instNombre = $inst['nombre']    ?? 'GeriApp';
            $instDirecc = $inst['direccion'] ?: (defined('APP_ADDRESS') ? APP_ADDRESS : '');
            $rolLabels  = [
                'admin'     => 'Administrador',
                'medico'    => 'Médico/a',
                'enfermero' => 'Cuidador/a',
                'familiar'  => 'Familiar',
            ];
            $rolLabel   = $rolLabels[$rol] ?? ucfirst($rol);
            $mailer    = Mailer::fromConfig($inst_id);

            $mensajeBloque = $msg
                ? "<tr><td style='padding:0 0 20px'>"
                  . "<div style='border-left:3px solid #178391;padding:10px 16px;background:#e8f6fb;border-radius:0 6px 6px 0;font-size:14px;color:#334155;line-height:1.6;'>"
                  . htmlspecialchars($msg)
                  . "</div></td></tr>"
                : '';
            $footerDireccion = $instDirecc
                ? "<p style='margin:6px 0 0;font-size:10.5px;color:#94a3b8;text-align:center;'>&#128205; "
                  . htmlspecialchars($instDirecc) . "</p>"
                : '';

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invitación a {$instNombre}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr><td align="center">
      <table width="100%" style="max-width:560px;" cellpadding="0" cellspacing="0" border="0">

        <!-- Cabecera -->
        <tr><td style="background:#178391;border-radius:10px 10px 0 0;padding:32px 40px;text-align:center;">
          <p style="margin:0 0 4px;font-size:11px;font-weight:600;letter-spacing:1.5px;color:rgba(255,255,255,.7);text-transform:uppercase;">Sistema de gestión geriátrica</p>
          <h1 style="margin:0;font-size:24px;font-weight:700;color:#ffffff;">GeriApp</h1>
        </td></tr>

        <!-- Cuerpo -->
        <tr><td style="background:#ffffff;padding:36px 40px;">
          <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="padding:0 0 16px;">
              <p style="margin:0;font-size:15px;font-weight:600;color:#1a202c;">Hola,</p>
            </td></tr>
            <tr><td style="padding:0 0 16px;">
              <p style="margin:0;font-size:14px;color:#475569;line-height:1.7;">
                Fuiste invitado/a a unirte a <strong style="color:#1a202c;">{$instNombre}</strong> en GeriApp
                con el rol de <strong style="color:#178391;">{$rolLabel}</strong>.
              </p>
            </td></tr>
            {$mensajeBloque}
            <tr><td style="padding:0 0 28px;">
              <p style="margin:0;font-size:14px;color:#475569;line-height:1.7;">
                Haz clic en el botón de abajo para crear tu contraseña y activar tu cuenta.
                El enlace permanecerá activo hasta que sea utilizado o eliminado por un administrador.
              </p>
            </td></tr>
            <!-- Botón CTA -->
            <tr><td align="center" style="padding:0 0 28px;">
              <a href="{$registerLink}"
                 style="display:inline-block;background:#178391;color:#ffffff;text-decoration:none;
                        padding:14px 36px;border-radius:6px;font-size:15px;font-weight:600;
                        letter-spacing:.3px;">
                Activar mi cuenta
              </a>
            </td></tr>
            <!-- Enlace alternativo -->
            <tr><td style="padding:0 0 8px;">
              <p style="margin:0;font-size:12px;color:#94a3b8;">Si el botón no funciona, copia y pega este enlace:</p>
            </td></tr>
            <tr><td style="padding:0 0 24px;">
              <p style="margin:0;font-size:11.5px;color:#94a3b8;word-break:break-all;">{$registerLink}</p>
            </td></tr>
            <!-- Aviso de seguridad -->
            <tr><td style="border-top:1px solid #e2e8f0;padding:20px 0 0;">
              <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
                Si no esperabas esta invitación, puedes ignorar este correo con seguridad.
                Nadie puede acceder a tu cuenta sin completar el registro.
              </p>
            </td></tr>
          </table>
        </td></tr>

        <!-- Pie -->
        <tr><td style="background:#f8fafc;border-radius:0 0 10px 10px;padding:20px 40px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:11px;color:#94a3b8;text-align:center;line-height:1.8;">
            Recibes este correo porque el administrador de <strong>{$instNombre}</strong>
            ingresó tu dirección al enviar una invitación en GeriApp.<br>
            &copy; GeriApp &mdash; Sistema de gestión geriátrica
          </p>
          {$footerDireccion}
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

            $textBody = "Invitación a GeriApp\n"
                . str_repeat('-', 40) . "\n\n"
                . "Fuiste invitado/a a unirte a {$instNombre} en GeriApp\n"
                . "con el rol de {$rolLabel}.\n\n"
                . ($msg ? "Mensaje del administrador:\n{$msg}\n\n" : '')
                . "Activa tu cuenta visitando el siguiente enlace.\n"
                . "Permanecerá activo hasta que sea utilizado o eliminado por un administrador:\n\n"
                . "{$registerLink}\n\n"
                . str_repeat('-', 40) . "\n"
                . "Recibes esto porque el administrador de {$instNombre} ingresó\n"
                . "tu correo al crear una invitación en GeriApp.\n"
                . ($instDirecc ? "{$instNombre} — {$instDirecc}\n" : '')
                . "Si no lo esperabas, simplemente ignora este mensaje.\n";

            $sendResult = $mailer->send(
                $email,
                "{$instNombre} te invita a GeriApp",
                $htmlBody,
              $textBody,
              $_cfg['smtp_from_email'] ?? ''
            );
            $emailSent  = is_array($sendResult) ? !empty($sendResult['ok']) : (bool)$sendResult;
            if (!$emailSent && is_array($sendResult)) $emailError = $sendResult['error'] ?? 'No enviado';
        } catch (Throwable $e) {
            $emailError = $e->getMessage();
            // Non-fatal: invitación creada aunque falle el correo
        }

        $waResult = ['ok' => false, 'skipped' => true, 'error' => 'Sin teléfono'];
        if (!empty($telefono)) {
          try {
            $waResult = invitaciones_send_whatsapp($inst_id, $telefono, $rol, $registerLink, $msg, false);
          } catch (Throwable $e) {
            $waResult = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
            error_log('[GeriApp] Invitacion WhatsApp no fatal: ' . $e->getMessage());
          }
        }
        try {
          $logResIds = invitaciones_resident_ids_for_log($inst_id, $rol, $residenteIdsForCreate);
          if ($email) {
            invitaciones_log_notification(
              $inst_id,
              $logResIds,
              $email,
              'email',
              'invitacion_creada',
              $textBody ?: "Invitación a GeriApp: {$registerLink}",
              $emailSent ? 'enviado' : 'error',
              $emailError
            );
          }
          if (!empty($telefono) && empty($waResult['skipped'])) {
            $instLog = Institucion::getById($inst_id);
            $instNombreLog = is_array($instLog) ? ($instLog['nombre'] ?? 'GeriApp') : 'GeriApp';
            $rolLabelsLog = ['admin'=>'Administrador','medico'=>'Médico/a','enfermero'=>'Cuidador/a','familiar'=>'Familiar'];
            invitaciones_log_notification(
              $inst_id,
              $logResIds,
              $telefono,
              'whatsapp',
              'invitacion_creada',
              invitaciones_wa_text($instNombreLog, $rolLabelsLog[$rol] ?? ucfirst($rol), $registerLink, $msg, false),
              !empty($waResult['ok']) ? 'enviado' : 'error',
              empty($waResult['ok']) ? ($waResult['error'] ?? 'No enviado') : null
            );
          }
        } catch (Throwable $e) {
          error_log('[GeriApp] Invitacion auditoria no fatal: ' . $e->getMessage());
        }

        api_ok([
            'id'            => $result['id'],
            'token'         => $result['token'],
            'link'          => $registerLink,
            'email_enviado' => $emailSent,
            'whatsapp_enviado' => !empty($waResult['ok']),
            'whatsapp_error'   => empty($waResult['ok']) && empty($waResult['skipped']) ? ($waResult['error'] ?? 'No enviado') : null,
        ], 201);
    }

    // ── Buscar usuario / invitación existente por correo o teléfono ─────────
    if ($action === 'lookup_existing') {
        $email = strtolower(trim($body['email'] ?? ''));
        $telefono = api_normalize_phone_mx(trim($body['telefono'] ?? '')) ?: null;
        $excludeResId = (int)($body['residente_id'] ?? 0);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
        if (!$email && !$telefono) api_ok(['user' => null, 'invitacion' => null]);

        require_once __DIR__ . '/../db/models/UsuarioResidente.php';
        $db = Database::getInstance();
        $clauses = [];
        $params = [$inst_id];
        if ($email)    { $clauses[] = 'LOWER(u.email) = ?'; $params[] = $email; }
        if ($telefono) { $clauses[] = 'u.telefono = ?';     $params[] = $telefono; }
        $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.estado, ui.rol
                FROM usuarios u
                INNER JOIN usuario_instituciones ui ON ui.usuario_id = u.id
                WHERE ui.institucion_id = ? AND ui.estado = 'activo' AND ("
              . implode(' OR ', $clauses) . ') LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $u = $stmt->fetch();

        $userPayload = null;
        if ($u) {
            $resList = UsuarioResidente::getResidentes((int)$u['id'], $inst_id);
            $resIds  = array_map(fn($r) => (int)($r['id'] ?? 0), $resList);
            $userPayload = [
                'id'          => (int)$u['id'],
                'nombre'      => $u['nombre'],
                'email'       => $u['email'],
                'telefono'    => $u['telefono'],
                'rol'         => api_role_public($u['rol']),
                'rol_storage' => api_role_storage($u['rol']),
                'residentes'  => array_map(fn($r) => [
                    'id'     => (int)($r['id'] ?? 0),
                    'nombre' => trim(($r['nombre'] ?? '') . ' ' . ($r['apellidos'] ?? '')),
                ], $resList),
                'linked_to_current' => $excludeResId > 0 && in_array($excludeResId, $resIds, true),
            ];
        }

        $invPayload = null;
        $or = [];
        $invParams = [$inst_id];
        if ($email)    { $or[] = 'LOWER(email) = ?'; $invParams[] = $email; }
        if ($telefono) { $or[] = 'telefono = ?';     $invParams[] = $telefono; }
        if ($or) {
            $sqlInv = "SELECT id, email, telefono, rol, estado, expires_at AS expira_at FROM invitaciones
                       WHERE institucion_id = ? AND estado IN ('pendiente','enviada') AND ("
                       . implode(' OR ', $or) . ') ORDER BY id DESC LIMIT 1';
            $stmtI = $db->prepare($sqlInv);
            $stmtI->execute($invParams);
            $inv = $stmtI->fetch();
            if ($inv) $invPayload = [
                'id'        => (int)$inv['id'],
                'email'     => $inv['email'],
                'telefono'  => $inv['telefono'],
                'rol'       => api_role_public($inv['rol']),
              'rol_storage' => api_role_storage($inv['rol']),
                'estado'    => $inv['estado'],
                'expira_at' => null,
            ];
        }

        api_ok(['user' => $userPayload, 'invitacion' => $invPayload]);
    }

    // ── Vincular usuario existente a un residente (sin reemplazar otros) ────
    if ($action === 'link_residente') {
        $userId = (int)($body['usuario_id'] ?? 0);
        $resId  = (int)($body['residente_id'] ?? 0);
        if (!$userId || !$resId) api_error('usuario_id y residente_id requeridos', 422);

        require_once __DIR__ . '/../db/models/UsuarioResidente.php';
        require_once __DIR__ . '/../db/models/UsuarioInstitucion.php';
        if (!UsuarioInstitucion::hasAccess($userId, $inst_id)) api_error('Usuario no pertenece a esta institución', 403);

        $db = Database::getTenant($inst_id);
        $stmt = $db->prepare('SELECT id FROM residentes WHERE id = ? AND institucion_id = ? LIMIT 1');
        $stmt->execute([$resId, $inst_id]);
        if (!$stmt->fetchColumn()) api_error('Residente no encontrado', 404);

        UsuarioResidente::add($userId, $resId, $inst_id);
        try {
            if (class_exists('Log')) {
                Log::registrar([
                    'usuario_id'     => (int)$_SESSION['user_id'],
                    'institucion_id' => $inst_id,
                    'accion'         => 'usuario_residente_vinculado',
                    'modulo'         => 'Invitaciones',
                    'detalle'        => "Usuario {$userId} → residente {$resId}",
                    'estado'         => 'ok',
                ]);
            }
        } catch (\Throwable $e) { /* no fatal */ }
        api_ok(['usuario_id' => $userId, 'residente_id' => $resId, 'linked' => true]);
    }

    // ── Reenviar invitación ──────────────────────────────────────────────────
    if ($action === 'reenviar') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) api_error('ID requerido', 422);

        $inv = Invitacion::getById($id, $inst_id);
        if (!$inv) api_error('Invitación no encontrada', 404);
        if ($inv['estado'] === 'aceptada') api_error('La invitación ya fue aceptada', 409);

        $dias  = max(1, (int)($body['dias'] ?? 7));
        $canal = (string)($body['canal'] ?? 'all');
        if (!in_array($canal, ['all','email','whatsapp'], true)) $canal = 'all';

        $emailDestino = trim((string)($inv['email'] ?? ''));
        $telefonoDestino = trim((string)($inv['telefono'] ?? ''));
        if ($canal === 'email' && $emailDestino === '') {
          api_error('La invitación no tiene email para reenviar', 422);
        }
        if ($canal === 'whatsapp' && $telefonoDestino === '') {
          api_error('La invitación no tiene teléfono WhatsApp para reenviar', 422);
        }
        if ($canal === 'all' && $emailDestino === '' && $telefonoDestino === '') {
          api_error('Agrega un email o teléfono WhatsApp antes de reenviar la invitación', 422);
        }

        $token = Invitacion::resend($id, $inst_id, $dias);
        if (!$token) api_error('Error al reenviar invitación', 500);

        $registerLink = $_appUrl . '/register.php?inv=' . $token;
        $invData = Invitacion::getByToken($token) ?: $inv;

        // ── Re-enviar correo (no fatal) ──────────────────────────────────────
        $emailSent = false;
        $emailError = null;
        $textBody = '';
        if (in_array($canal, ['all','email'], true) && !empty($inv['email'])) try {
            $inst         = Institucion::getById($inst_id);
            $instNombre   = $inst['nombre']    ?? 'GeriApp';
            $instDirecc   = $inst['direccion'] ?: (defined('APP_ADDRESS') ? APP_ADDRESS : '');
            $rolLabels  = [
                'admin'     => 'Administrador',
                'medico'    => 'Médico/a',
                'enfermero' => 'Cuidador/a',
                'familiar'  => 'Familiar',
            ];
            $rolLabel   = $rolLabels[$invData['rol'] ?? ''] ?? ucfirst($invData['rol'] ?? '');
            $mailer     = Mailer::fromConfig($inst_id);

            $footerDireccion = $instDirecc
                ? "<p style='margin:6px 0 0;font-size:10.5px;color:#94a3b8;text-align:center;'>&#128205; "
                  . htmlspecialchars($instDirecc) . "</p>"
                : '';

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invitación a {$instNombre}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr><td align="center">
      <table width="100%" style="max-width:560px;" cellpadding="0" cellspacing="0" border="0">

        <!-- Cabecera -->
        <tr><td style="background:#178391;border-radius:10px 10px 0 0;padding:32px 40px;text-align:center;">
          <p style="margin:0 0 4px;font-size:11px;font-weight:600;letter-spacing:1.5px;color:rgba(255,255,255,.7);text-transform:uppercase;">Sistema de gestión geriátrica</p>
          <h1 style="margin:0;font-size:24px;font-weight:700;color:#ffffff;">GeriApp</h1>
        </td></tr>

        <!-- Cuerpo -->
        <tr><td style="background:#ffffff;padding:36px 40px;">
          <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="padding:0 0 16px;">
              <p style="margin:0;font-size:15px;font-weight:600;color:#1a202c;">Hola,</p>
            </td></tr>
            <tr><td style="padding:0 0 28px;">
              <p style="margin:0;font-size:14px;color:#475569;line-height:1.7;">
                El administrador de <strong style="color:#1a202c;">{$instNombre}</strong> reenvió tu invitación
                para unirte a GeriApp como <strong style="color:#178391;">{$rolLabel}</strong>.
                El enlace anterior ya no es válido; usa el de abajo.
              </p>
            </td></tr>
            <!-- Botón CTA -->
            <tr><td align="center" style="padding:0 0 28px;">
              <a href="{$registerLink}"
                 style="display:inline-block;background:#178391;color:#ffffff;text-decoration:none;
                        padding:14px 36px;border-radius:6px;font-size:15px;font-weight:600;
                        letter-spacing:.3px;">
                Activar mi cuenta
              </a>
            </td></tr>
            <!-- Enlace alternativo -->
            <tr><td style="padding:0 0 8px;">
              <p style="margin:0;font-size:12px;color:#94a3b8;">Si el botón no funciona, copia y pega este enlace:</p>
            </td></tr>
            <tr><td style="padding:0 0 24px;">
              <p style="margin:0;font-size:11.5px;color:#94a3b8;word-break:break-all;">{$registerLink}</p>
            </td></tr>
            <tr><td style="padding:0 0 4px;">
              <p style="margin:0;font-size:12.5px;color:#64748b;">El enlace permanecerá activo hasta que sea utilizado o eliminado por un administrador.</p>
            </td></tr>
            <!-- Aviso de seguridad -->
            <tr><td style="border-top:1px solid #e2e8f0;padding:20px 0 0;">
              <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
                Si no esperabas esta invitación, puedes ignorar este correo con seguridad.
              </p>
            </td></tr>
          </table>
        </td></tr>

        <!-- Pie -->
        <tr><td style="background:#f8fafc;border-radius:0 0 10px 10px;padding:20px 40px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:11px;color:#94a3b8;text-align:center;line-height:1.8;">
            Recibes este correo porque el administrador de <strong>{$instNombre}</strong>
            reenvió una invitación a tu dirección en GeriApp.<br>
            &copy; GeriApp &mdash; Sistema de gestión geriátrica
          </p>
          {$footerDireccion}
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

            $textBody = "Invitación a GeriApp (reenviada)\n"
                . str_repeat('-', 40) . "\n\n"
                . "El administrador de {$instNombre} reenvió tu invitación\n"
                . "para unirte a GeriApp como {$rolLabel}.\n\n"
                . "Activa tu cuenta con este enlace, vigente hasta uso o eliminación manual:\n\n"
                . "{$registerLink}\n\n"
                . str_repeat('-', 40) . "\n"
                . ($instDirecc ? "{$instNombre} — {$instDirecc}\n" : '')
                . "Si no lo esperabas, simplemente ignora este mensaje.\n";

            $sendResult = $mailer->send(
                $inv['email'],
                "{$instNombre} te invita a GeriApp",
                $htmlBody,
              $textBody,
              $_cfg['smtp_from_email'] ?? ''
            );
            $emailSent  = is_array($sendResult) ? !empty($sendResult['ok']) : (bool)$sendResult;
            if (!$emailSent && is_array($sendResult)) $emailError = $sendResult['error'] ?? 'No enviado';
        } catch (Throwable $e) {
            $emailError = $e->getMessage();
            // Non-fatal
        }

        $waResult = ['ok' => false, 'skipped' => true, 'error' => 'Canal no solicitado'];
        if (in_array($canal, ['all','whatsapp'], true)) {
          try {
            $waResult = invitaciones_send_whatsapp($inst_id, $invData['telefono'] ?? ($inv['telefono'] ?? ''), $invData['rol'] ?? ($inv['rol'] ?? ''), $registerLink, $invData['mensaje'] ?? ($inv['mensaje'] ?? null), true);
          } catch (Throwable $e) {
            $waResult = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
            error_log('[GeriApp] Reenvio invitacion WhatsApp no fatal: ' . $e->getMessage());
          }
        }
        try {
          $invResIds = [];
          if (!empty($invData['residente_ids'])) {
            $decoded = json_decode((string)$invData['residente_ids'], true);
            if (is_array($decoded)) $invResIds = $decoded;
          }
          $invRol = (string)($invData['rol'] ?? ($inv['rol'] ?? ''));
          $logResIds = invitaciones_resident_ids_for_log($inst_id, $invRol, $invResIds);
          if (in_array($canal, ['all','email'], true) && !empty($inv['email'])) {
            invitaciones_log_notification(
              $inst_id,
              $logResIds,
              (string)$inv['email'],
              'email',
              'invitacion_reenviada',
              $textBody ?: "Invitación reenviada a GeriApp: {$registerLink}",
              $emailSent ? 'enviado' : 'error',
              $emailError
            );
          }
          $waPhone = (string)($invData['telefono'] ?? ($inv['telefono'] ?? ''));
          if (in_array($canal, ['all','whatsapp'], true) && $waPhone !== '' && empty($waResult['skipped'])) {
            $instLog = Institucion::getById($inst_id);
            $instNombreLog = is_array($instLog) ? ($instLog['nombre'] ?? 'GeriApp') : 'GeriApp';
            $rolLabelsLog = ['admin'=>'Administrador','medico'=>'Médico/a','enfermero'=>'Cuidador/a','familiar'=>'Familiar'];
            invitaciones_log_notification(
              $inst_id,
              $logResIds,
              $waPhone,
              'whatsapp',
              'invitacion_reenviada',
              invitaciones_wa_text($instNombreLog, $rolLabelsLog[$invRol] ?? ucfirst($invRol), $registerLink, $invData['mensaje'] ?? ($inv['mensaje'] ?? null), true),
              !empty($waResult['ok']) ? 'enviado' : 'error',
              empty($waResult['ok']) ? ($waResult['error'] ?? 'No enviado') : null
            );
          }
        } catch (Throwable $e) {
          error_log('[GeriApp] Reenvio invitacion auditoria no fatal: ' . $e->getMessage());
        }

        api_ok([
            'link' => $registerLink,
            'email_enviado' => $emailSent,
            'whatsapp_enviado' => !empty($waResult['ok']),
            'whatsapp_error'   => empty($waResult['ok']) && empty($waResult['skipped']) ? ($waResult['error'] ?? 'No enviado') : null,
        ]);
    }

    // ── Revocar invitación (compatibilidad): eliminar en vez de expirar ─────
    if ($action === 'revocar') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) api_error('ID requerido', 422);

      $ok = Invitacion::delete($id, $inst_id);
      if (!$ok) api_error('No se pudo eliminar (ya aceptada o no encontrada)', 409);

      api_ok(['revocada' => true, 'eliminada' => true]);
    }

    // ── Eliminar invitación ─────────────────────────────────────────────────
    if ($action === 'eliminar') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) api_error('ID requerido', 422);

        $ok = Invitacion::delete($id, $inst_id);
        if (!$ok) api_error('No se pudo eliminar (ya aceptada o no encontrada)', 409);

        api_ok(['eliminada' => true]);
    }

    // ── Actualizar datos de invitación pendiente ─────────────────────────────
    if ($action === 'actualizar') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) api_error('ID requerido', 422);
        $email = trim((string)($body['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_error('Email inválido', 422);
        }
        $tel = api_normalize_phone_mx(trim((string)($body['telefono'] ?? '')));
        if ($tel !== '' && !preg_match('/^[+]?[0-9 \-()]{6,30}$/', $tel)) {
            api_error('Teléfono inválido', 422);
        }
        $current = Invitacion::getById($id, $inst_id);
        if (!$current) api_error('No se pudo actualizar (aceptada o no encontrada)', 409);
        $rol = api_role_storage($body['rol'] ?? ($current['rol'] ?? 'cuidador'));
        $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
        if (!in_array($rol, $rolesValidos, true)) {
          api_error('Rol inválido', 422);
        }
        $roleConflict = invitaciones_lower_role_conflict($inst_id, $rol, $email, $tel, $id);
        if ($roleConflict) {
          api_error('Esta persona ya tiene ' . ($roleConflict['kind'] === 'invite' ? 'una invitación' : 'un usuario') . ' con rol superior (' . $roleConflict['label'] . ') en esta institución.', 409);
        }
        $ok = Invitacion::updateData($id, $inst_id, [
            'email'             => $email,
            'telefono'          => $tel,
          'rol'               => $rol,
            'nombre_sugerido'   => trim((string)($body['nombre_sugerido']   ?? '')),
            'apellido_sugerido' => trim((string)($body['apellido_sugerido'] ?? '')),
            'mensaje'           => trim((string)($body['mensaje']           ?? '')),
        ]);
        if (!$ok) api_error('No se pudo actualizar (aceptada o no encontrada)', 409);
        api_ok(['actualizada' => true]);
    }

    api_error('Acción desconocida', 400);
}

api_error('Método no permitido', 405);
