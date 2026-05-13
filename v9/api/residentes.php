<?php
/**
 * GeriApp — API /api/residentes.php
 *
 * GET    /api/residentes.php           → lista de residentes de la institución
 * GET    /api/residentes.php?id=N      → un residente
 * POST   /api/residentes.php           → crear residente
 * PUT    /api/residentes.php?id=N      → actualizar residente
 * DELETE /api/residentes.php?id=N      → eliminar residente
 *
 * Roles permitidos: admin, medico, enfermero, superadmin
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/_badge_helpers.php';

// GET is also allowed for the "familiar" role: a family member can see Inicio
// (the residents list) but the response is filtered to ONLY the residents
// linked to that user via UsuarioResidente. Write methods stay restricted.
if (api_method() === 'GET') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero', 'familiar']);
} else {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
}

$method = api_method();
$id     = api_int('id');

if (($_GET['action'] ?? '') === 'move' && $method !== 'POST') {
    api_error('Método no permitido para mover residente', 405);
}

function _res_can_move_resident_institution(int $instId): bool
{
    $rol = api_rol();
    if (in_array($rol, ['superadmin', 'admin'], true)) return true;
    if (!in_array($rol, ['medico', 'enfermero'], true)) return false;
    $cfg = Configuracion::getCached($instId);
    $perms = [];
    if (!empty($cfg['roles_permisos'])) {
        $perms = is_string($cfg['roles_permisos']) ? (json_decode($cfg['roles_permisos'], true) ?: []) : $cfg['roles_permisos'];
    }
    return !empty($perms[$rol]['mover_residente_institucion']);
}

function _res_norm_email(?string $email): string
{
    return mb_strtolower(trim((string)$email));
}

function _res_norm_phone(?string $phone): string
{
    return preg_replace('/\D+/', '', (string)$phone) ?: '';
}

function _res_registered_user_keys_for_inst(int $instId): array
{
    static $cache = [];
    if (isset($cache[$instId])) return $cache[$instId];
    $emails = [];
    $phones = [];
    try {
        $db = Database::getMaster();
                $stmt = $db->prepare(
                        "SELECT DISTINCT u.email, u.telefono
                         FROM usuario_instituciones ui
                         INNER JOIN usuarios u ON u.id = ui.usuario_id
                         WHERE ui.institucion_id = ?
                             AND ui.estado = 'activo'
                             AND u.estado = 'activo'"
                );
                $stmt->execute([$instId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $email = _res_norm_email($u['email'] ?? '');
            $phone = _res_norm_phone($u['telefono'] ?? '');
            if ($email !== '') $emails[$email] = true;
            if ($phone !== '') $phones[$phone] = true;
        }
    } catch (\Throwable $e) {}
    return $cache[$instId] = ['emails' => $emails, 'phones' => $phones];
}

function _res_invite_matches_registered_user(array $inv, int $instId): bool
{
    $keys = _res_registered_user_keys_for_inst($instId);
    $email = _res_norm_email($inv['email'] ?? '');
    $phone = _res_norm_phone($inv['telefono'] ?? '');
    return ($email !== '' && !empty($keys['emails'][$email])) || ($phone !== '' && !empty($keys['phones'][$phone]));
}

function _res_pending_invites_for_resident(int $instId, int $residenteId, array $roles): array
{
    $rows = [];
    try {
        foreach (Invitacion::getAll($instId) as $inv) {
            if (!in_array((string)($inv['rol'] ?? ''), $roles, true)) continue;
            if (_res_norm_email($inv['email'] ?? '') === '' && _res_norm_phone($inv['telefono'] ?? '') === '') continue;
            $estado = (string)($inv['estado'] ?? 'pendiente');
            if (!in_array($estado, ['pendiente','expirada'], true)) continue;
            if (_res_invite_matches_registered_user($inv, $instId)) continue;
            $resIds = [];
            if (!empty($inv['residente_ids'])) {
                $decoded = json_decode((string)$inv['residente_ids'], true);
                if (is_array($decoded)) $resIds = array_map('intval', $decoded);
            }
            if (empty($resIds) && in_array((string)($inv['rol'] ?? ''), ['admin','medico','enfermero'], true)) {
                $rows[] = $inv;
                continue;
            }
            if (!in_array($residenteId, $resIds, true)) continue;
            $rows[] = $inv;
        }
    } catch (\Throwable $e) {
        return [];
    }
    return $rows;
}

function _res_invitation_status_label(array $inv): string
{
    return (string)($inv['estado'] ?? 'pendiente');
}

function _res_invite_delivery_index(int $instId, int $residenteId): array
{
    $index = ['email' => [], 'whatsapp' => []];
    try {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT destinatario, canal, tipo, estado, error_detalle, fecha
             FROM notificaciones_log
             WHERE institucion_id = ?
               AND (residente_id = ? OR residente_id IS NULL)
               AND tipo IN ('invitacion_creada','invitacion_reenviada')
             ORDER BY fecha DESC, id DESC
             LIMIT 300"
        );
        $stmt->execute([$instId, $residenteId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $canal = strtolower((string)($row['canal'] ?? ''));
            if (!isset($index[$canal])) continue;
            $dest = $canal === 'whatsapp'
                ? _res_norm_phone($row['destinatario'] ?? '')
                : _res_norm_email($row['destinatario'] ?? '');
            if ($dest === '' || isset($index[$canal][$dest])) continue;
            $index[$canal][$dest] = [
                'estado' => (string)($row['estado'] ?? ''),
                'tipo' => (string)($row['tipo'] ?? ''),
                'fecha' => (string)($row['fecha'] ?? ''),
                'error' => (string)($row['error_detalle'] ?? ''),
            ];
        }
    } catch (\Throwable $e) {}
    return $index;
}

function _res_invite_delivery_for(array $inv, array $deliveryIndex): array
{
    $email = _res_norm_email($inv['email'] ?? '');
    $phone = _res_norm_phone($inv['telefono'] ?? '');
    $emailDelivery = $email !== '' ? ($deliveryIndex['email'][$email] ?? null) : null;
    $waDelivery = $phone !== '' ? ($deliveryIndex['whatsapp'][$phone] ?? null) : null;
    return [
        'email' => $emailDelivery,
        'whatsapp' => $waDelivery,
        'email_enviado' => is_array($emailDelivery) && (($emailDelivery['estado'] ?? '') === 'enviado'),
        'whatsapp_enviado' => is_array($waDelivery) && (($waDelivery['estado'] ?? '') === 'enviado'),
        'email_error' => is_array($emailDelivery) && (($emailDelivery['estado'] ?? '') === 'error'),
        'whatsapp_error' => is_array($waDelivery) && (($waDelivery['estado'] ?? '') === 'error'),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// POST multipart — resident photo upload
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && !empty($_FILES['foto']) && $id > 0) {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
    api_assert_residente($id);

    $file = $_FILES['foto'];
    if ($file['error'] !== UPLOAD_ERR_OK) api_error('Error al subir foto', 400);
    if ($file['size'] > 5 * 1024 * 1024)  api_error('Foto muy grande (máx 5MB)', 413);

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        api_error('Solo se permiten imágenes JPG/PNG/WEBP', 415);
    }
    // §6.5 Validación MIME reforzada con finfo
    $mime = api_validate_mime($file['tmp_name'], ['image/jpeg','image/png','image/webp']);

    $instId = api_inst_id();
    $dir = dirname(__DIR__) . "/uploads/residentes/{$instId}";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Delete old photo if exists
    $old = Residente::getById($id, $instId);
    if (!empty($old['foto_path'])) {
        $oldFile = dirname(__DIR__) . '/' . $old['foto_path'];
        if (file_exists($oldFile)) @unlink($oldFile);
    }

    $name = "res_{$id}_" . uniqid() . '.jpg';
    $dest = $dir . '/' . $name;

    // Server-side compression: resize to max 600px & save as JPEG 85%
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
            $max = 600;
            if ($w > $max || $h > $max) {
                if ($w > $h) { $nw = $max; $nh = (int)round($h * $max / $w); }
                else         { $nh = $max; $nw = (int)round($w * $max / $h); }
                $resized = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            if (imagejpeg($img, $dest, 85)) { $compressed = true; }
            imagedestroy($img);
        }
    }
    if (!$compressed) {
        if (!move_uploaded_file($src, $dest)) {
            api_error('Error al guardar foto', 500);
        }
    }

    $url = str_replace('\\', '/', "uploads/residentes/{$instId}/{$name}");
    Residente::update($id, $instId, ['foto_path' => $url]);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => $instId,
        'accion'         => 'residente_subir_foto',
        'modulo'         => 'residentes',
        'detalle'        => "ID {$id}",
    ]);

    api_ok(['url' => $url], 'Foto subida');
}

// ─────────────────────────────────────────────────────────────────────────────
// GET — listar o detalle
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!empty($_GET['cuidados_estado'])) {
        $instId = api_inst_id();
        $estado = $_GET['estado'] ?? '';
        if ($estado === 'todos') $estado = '';
        $busq   = trim($_GET['busqueda'] ?? '');
        $filtros = [];
        if ($estado !== '') $filtros['estado'] = $estado;
        if ($busq !== '') $filtros['busqueda'] = $busq;

        $residentes = Residente::getAll($instId, $filtros);
        // Familiar role: restrict listing to the residents this user is linked to.
        if (api_rol() === 'familiar' && empty($_SESSION['sa_impersonating'])) {
            try {
                $linkedIds = UsuarioResidente::getResidenteIds(api_user_id(), $instId);
                $linkedSet = array_flip(array_map('intval', $linkedIds));
                $residentes = array_values(array_filter($residentes, fn($r) => isset($linkedSet[(int)$r['id']])));
            } catch (\Throwable $e) { $residentes = []; }
        }
        $today = date('Y-m-d');
        $nowDate = date('Y-m-d');
        $nowTime = date('H:i');

        $db = Database::getTenant($instId);
        $out = [];
        foreach ($residentes as $r) {
            $rid = (int)$r['id'];

            $counts = Cuidado::countByCategoria($instId, $rid, $today);
            $suenoPend = Cuidado::getSuenoPendiente($instId, $rid);
            $ultHeces = Cuidado::getUltimaHeces($instId, $rid);
            $ultSignos = Cuidado::getUltimosSignos($instId, $rid);
            $signosOut = _signos_out_count($ultSignos);

            // Alertas médico pendientes
            $alertasPend = 0;
            try {
                $stA = $db->prepare("SELECT COUNT(*) FROM alertas_medico WHERE institucion_id = ? AND residente_id = ? AND visto_por IS NULL");
                $stA->execute([$instId, $rid]);
                $alertasPend = (int)$stA->fetchColumn();
            } catch (\Throwable $e) {}

            // Inventario en bajo stock
            $lowStock = 0;
            try {
                $lowStock = count(Inventario::getLowStock($instId, $rid));
            } catch (\Throwable $e) {}

            // Medicación pendiente (slots vencidos no atendidos)
            $rxPending = 0;
            try {
                $rx = Prescripcion::getForResidente($rid);
                $regsToday = Cuidado::getByFecha($instId, $rid, $today);
                $rxPending = _med_overdue_count($rx, $regsToday, $today, $nowDate, $nowTime);
            } catch (\Throwable $e) {}

            $hecesHrs = _heces_hours_since($ultHeces);

            $out[] = [
                'id' => $rid,
                'nombre' => $r['nombre'] ?? '',
                'apellidos' => $r['apellidos'] ?? '',
                'estado' => $r['estado'] ?? 'activo',
                'habitacion' => $r['habitacion'] ?? '',
                'fecha_nacimiento' => $r['fecha_nacimiento'] ?? null,
                'sexo' => $r['sexo'] ?? null,
                'foto_path' => $r['foto_path'] ?? null,
                'alergias' => $r['alergias'] ?? '',
                'diagnostico' => $r['diagnostico'] ?? '',
                'medico_nombre' => $r['medico_nombre'] ?? '',
                'badges' => [
                    'sueno_pendiente' => $suenoPend ? true : false,
                    'signos_fuera_rango' => $signosOut,
                    'heces_horas' => $hecesHrs,
                    'medicacion_pendiente' => $rxPending,
                    'alertas_medico_pendientes' => $alertasPend,
                    'inventario_low_stock' => $lowStock,
                ],
                'counts_hoy' => $counts,
            ];
        }

        api_ok($out);
    }

    // Estado log for a resident
    if ($id > 0 && !empty($_GET['estado_log'])) {
        api_assert_residente($id);
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM residentes_estado_log WHERE residente_id = ? AND institucion_id = ? ORDER BY creado_at DESC LIMIT 50"
        );
        $stmt->execute([$id, api_inst_id()]);
        api_ok($stmt->fetchAll());
    }

    // ── Cuidadores asignados a un residente (+ pool disponible) ──────────
    // GET /api/residentes.php?id=N&cuidadores=1
    // Retorna { asignados:[{id,nombre,email,telefono,rol}], disponibles:[…], can_edit:bool }
    if ($id > 0 && !empty($_GET['cuidadores'])) {
        api_assert_residente($id);
        $instId  = api_inst_id();
        $rolUser = api_rol();
        $canEdit = in_array($rolUser, ['superadmin','admin'], true);

        // Build assigned list using pivot role (preferred) over usuarios.rol
        // to avoid stale role discrepancies between the two tables.
        $assignedIds = UsuarioResidente::getUsuarioIds($id, $instId);
        $assigned = [];
        if (!empty($assignedIds)) {
            $masterDb = Database::getMaster();
            $place = implode(',', array_fill(0, count($assignedIds), '?'));
                        $asStmt = $masterDb->prepare(
                                "SELECT u.id, u.nombre, u.email, u.telefono, u.estado, ui.rol
                                 FROM usuarios u
                                 INNER JOIN usuario_instituciones ui
                                     ON ui.usuario_id = u.id
                                    AND ui.institucion_id = ?
                                    AND ui.estado = 'activo'
                                 WHERE u.id IN ($place)"
                        );
            $asStmt->execute(array_merge([$instId], array_map('intval', $assignedIds)));
            foreach ($asStmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                if (!in_array($u['rol'] ?? '', ['admin','medico','enfermero'], true)) continue;
                if (($u['estado'] ?? '') !== 'activo') continue;
                $assigned[] = [
                    'id'       => (int)$u['id'],
                    'nombre'   => $u['nombre'] ?? '',
                    'email'    => $u['email'] ?? '',
                    'telefono' => $u['telefono'] ?? '',
                    'rol'      => $u['rol'],
                ];
            }
        }

        $pendingInvites = [];
        $deliveryIndex = _res_invite_delivery_index($instId, $id);
        foreach (_res_pending_invites_for_resident($instId, $id, ['admin','medico','enfermero']) as $inv) {
            $invStatus = _res_invitation_status_label($inv);
            $pendingInvites[] = [
                'id'            => 'inv-' . (int)$inv['id'],
                'invitacion_id' => (int)$inv['id'],
                'nombre'        => trim((string)($inv['nombre_sugerido'] ?? '') . ' ' . (string)($inv['apellido_sugerido'] ?? '')) ?: 'Invitación pendiente',
                'email'         => $inv['email'] ?? '',
                'telefono'      => $inv['telefono'] ?? '',
                'rol'           => $inv['rol'] ?? '',
                'estado'        => $invStatus,
                'enviada'       => $inv['creado_at'] ?? '',
                'expira'        => $inv['expires_at'] ?? '',
                'creado_por_nombre' => $inv['creado_por_nombre'] ?? '',
                'delivery'      => _res_invite_delivery_for($inv, $deliveryIndex),
                'pending_invite'=> true,
            ];
        }

        $disponibles = [];
        if ($canEdit) {
            $db   = Database::getMaster();
                        $stmt = $db->prepare(
                                "SELECT DISTINCT u.id, u.nombre, u.email, u.telefono, ui.rol
                                 FROM usuario_instituciones ui
                                 INNER JOIN usuarios u ON u.id = ui.usuario_id
                                 WHERE ui.institucion_id = ?
                                     AND ui.estado = 'activo'
                                     AND u.estado = 'activo'
                                     AND ui.rol IN ('admin','medico','enfermero')
                                 ORDER BY u.nombre"
                        );
                        $stmt->execute([$instId]);
            $disponibles = array_map(function($r) {
                return [
                    'id'       => (int)$r['id'],
                    'nombre'   => $r['nombre'] ?? '',
                    'email'    => $r['email'] ?? '',
                    'telefono' => $r['telefono'] ?? '',
                    'rol'      => $r['rol'] ?? '',
                ];
            }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        api_ok([
            'asignados'   => $assigned,
            'invitaciones'=> $pendingInvites,
            'disponibles' => $disponibles,
            'can_edit'    => $canEdit,
        ]);
    }

    // ── Destinos posibles para mover residente ───────────────────────────
    // GET /api/residentes.php?id=N&move_targets=1
    // Retorna { targets:[{id,nombre,can_receive:bool}], can_move:bool, current_inst:int }
    // Reglas:
    //   - Admin/superadmin siempre pueden.
    //   - Médico/enfermero solo si la matriz habilita mover_residente_institucion.
    //   - Admin lista destinos donde también es admin; roles delegados listan instituciones vinculadas.
    if ($id > 0 && !empty($_GET['move_targets'])) {
        api_assert_residente($id);
        $rolUser = api_rol();
        $canMove = _res_can_move_resident_institution(api_inst_id());
        if (!$canMove) {
            api_ok(['targets' => [], 'can_move' => false, 'current_inst' => api_inst_id()]);
        }
        $uid    = api_user_id();
        $instId = api_inst_id();
        $db     = Database::getMaster();

        $adminOnly = in_array($rolUser, ['superadmin','admin'], true);
        $accessSql = $adminOnly
            ? "((ui.rol IN ('admin','superadmin')) OR (u.rol IN ('admin','superadmin') AND u.institucion_id = i.id))"
            : "(ui.usuario_id IS NOT NULL OR u.id IS NOT NULL)";

        // Instituciones destino disponibles para el usuario.
        $stmt = $db->prepare(
            "SELECT DISTINCT i.id, i.nombre, i.db_name, i.estado
             FROM instituciones i
             LEFT JOIN usuario_instituciones ui
               ON ui.institucion_id = i.id AND ui.usuario_id = ? AND ui.estado='activo'
             LEFT JOIN usuarios u
               ON u.id = ? AND u.institucion_id = i.id
             WHERE i.id <> ?
               AND i.estado IN ('activa','trial','past_due')
                             AND $accessSql
             ORDER BY i.nombre"
        );
        $stmt->execute([$uid, $uid, $instId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $targets = array_map(function($r) {
            return [
                'id'          => (int)$r['id'],
                'nombre'      => $r['nombre'],
                'estado'      => $r['estado'],
                'can_receive' => true,
            ];
        }, $rows);

        api_ok([
            'targets'      => $targets,
            'can_move'     => true,
            'current_inst' => $instId,
        ]);
    }

    if ($id > 0) {
        $r = Residente::getById($id, api_inst_id());
        if (!$r) api_error('Residente no encontrado', 404);
        // Familiar: must be linked to this resident.
        if (api_rol() === 'familiar' && empty($_SESSION['sa_impersonating'])) {
            try {
                $linked = UsuarioResidente::getResidenteIds(api_user_id(), api_inst_id());
                if (!in_array((int)$id, array_map('intval', $linked), true)) api_error('No autorizado', 403);
            } catch (\Throwable $e) { api_error('No autorizado', 403); }
        }

        // Include linked familiar users for the familia panel
        $instId = api_inst_id();
        $userIds = UsuarioResidente::getUsuarioIds($id, $instId);
        $famUsers = [];
        if (!empty($userIds)) {
            $masterDb = Database::getMaster();
            $place = implode(',', array_fill(0, count($userIds), '?'));
            $stmtFam = $masterDb->prepare(
                "SELECT u.id, u.nombre, u.email, u.telefono
                 FROM usuarios u
                 INNER JOIN usuario_instituciones ui
                   ON ui.usuario_id = u.id
                  AND ui.institucion_id = ?
                  AND ui.estado = 'activo'
                  AND ui.rol = 'familiar'
                 WHERE u.estado = 'activo'
                   AND u.id IN ($place)"
            );
            $stmtFam->execute(array_merge([$instId], array_map('intval', $userIds)));
            foreach ($stmtFam->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $famUsers[] = [
                    'usuario_id' => (int)$u['id'],
                    'nombre'     => $u['nombre'] ?? '',
                    'email'      => $u['email'] ?? '',
                    'telefono'   => $u['telefono'] ?? '',
                ];
            }
        }
        $r['familiares_usuarios'] = $famUsers;

        $famInvites = [];
        $deliveryIndex = _res_invite_delivery_index($instId, $id);
        foreach (_res_pending_invites_for_resident($instId, $id, ['familiar']) as $inv) {
            $invStatus = _res_invitation_status_label($inv);
            $nombreInv = trim((string)($inv['nombre_sugerido'] ?? '') . ' ' . (string)($inv['apellido_sugerido'] ?? ''));
            $delivery = _res_invite_delivery_for($inv, $deliveryIndex);
            $famInvites[] = [
                'source'        => 'invitacion',
                'invitacion_id' => (int)$inv['id'],
                'nombre'        => $nombreInv,
                'email'         => $inv['email'] ?? '',
                'telefono'      => $inv['telefono'] ?? '',
                'parentesco'    => '',
                'estado_cuenta' => 'invitado',
                'invitacion'    => [
                    'id' => (int)$inv['id'],
                    'email' => $inv['email'] ?? '',
                    'telefono' => $inv['telefono'] ?? '',
                    'nombre_sugerido' => $inv['nombre_sugerido'] ?? '',
                    'apellido_sugerido' => $inv['apellido_sugerido'] ?? '',
                    'mensaje' => $inv['mensaje'] ?? '',
                    'status' => $invStatus,
                    'enviada' => $inv['creado_at'] ?? '',
                    'expira' => $inv['expires_at'] ?? '',
                    'token' => $inv['token'] ?? '',
                    'creado_por_nombre' => $inv['creado_por_nombre'] ?? '',
                    'delivery' => $delivery,
                ],
                'delivery'      => $delivery,
            ];
        }
        $r['familiares_invitaciones'] = $famInvites;

        api_ok($r);
    }

    $filtros = [
        'estado'    => $_GET['estado']    ?? '',
        'busqueda'  => $_GET['busqueda']  ?? '',
        'habitacion'=> $_GET['habitacion']?? '',
    ];

    $lista = Residente::getAll(api_inst_id(), array_filter($filtros));
    if (api_rol() === 'familiar' && empty($_SESSION['sa_impersonating'])) {
        try {
            $linkedIds = UsuarioResidente::getResidenteIds(api_user_id(), api_inst_id());
            $linkedSet = array_flip(array_map('intval', $linkedIds));
            $lista = array_values(array_filter($lista, fn($r) => isset($linkedSet[(int)$r['id']])));
        } catch (\Throwable $e) { $lista = []; }
    }
    api_ok($lista);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST action=sync_cuidadores — actualizar cuidadores asignados a un residente
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && ($_GET['action'] ?? '') === 'sync_cuidadores') {
    api_auth_roles(['superadmin', 'admin']);
    if ($id <= 0) api_error('ID requerido', 400);
    api_assert_residente($id);

    $instId = api_inst_id();
    $body   = api_body();
    $ids    = $body['cuidador_ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $clean  = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

    // Validar pool: solo usuarios admin/medico/enfermero activos en la institución
    $valid = [];
    if (!empty($clean)) {
        $db = Database::getMaster();
        $place = implode(',', array_fill(0, count($clean), '?'));
        $stmt = $db->prepare(
            "SELECT DISTINCT u.id
                         FROM usuario_instituciones ui
                         INNER JOIN usuarios u ON u.id = ui.usuario_id
             WHERE u.id IN ($place)
                             AND ui.institucion_id = ?
                             AND ui.estado = 'activo'
               AND u.estado = 'activo'
                             AND ui.rol IN ('admin','medico','enfermero')"
        );
                $params = array_merge($clean, [$instId]);
        $stmt->execute($params);
        $valid = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    // Reemplazar SOLO personal clínico/admin (preservar familiares vinculados).
    $tdb = Database::getTenant($instId);
    try {
        if ($tdb->inTransaction()) $tdb->rollBack();
        $tdb->beginTransaction();

        $curStmt = $tdb->prepare("SELECT usuario_id FROM usuario_residentes WHERE residente_id = ? AND institucion_id = ?");
        $curStmt->execute([$id, $instId]);
        $cur = array_map('intval', $curStmt->fetchAll(\PDO::FETCH_COLUMN));

        $remove = [];
        if (!empty($cur)) {
            $db = Database::getMaster();
            $place = implode(',', array_fill(0, count($cur), '?'));
            $stmt = $db->prepare(
                "SELECT DISTINCT u.id
                 FROM usuarios u
                                 INNER JOIN usuario_instituciones ui
                                     ON ui.usuario_id = u.id AND ui.institucion_id = ?
                 WHERE u.id IN ($place)
                                     AND ui.rol IN ('admin','medico','enfermero')"
            );
            $stmt->execute(array_merge([$instId], $cur));
            $remove = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        }

        if (!empty($remove)) {
            $place = implode(',', array_fill(0, count($remove), '?'));
            $del = $tdb->prepare("DELETE FROM usuario_residentes WHERE residente_id = ? AND institucion_id = ? AND usuario_id IN ($place)");
            $del->execute(array_merge([$id, $instId], $remove));
        }
        $insert = $tdb->prepare(
            "INSERT IGNORE INTO usuario_residentes (usuario_id, residente_id, institucion_id)
             VALUES (?, ?, ?)"
        );
        foreach ($valid as $uid) {
            $insert->execute([(int)$uid, $id, $instId]);
        }
        $tdb->commit();
    } catch (\Throwable $e) {
        if ($tdb->inTransaction()) $tdb->rollBack();
        api_error('Error al actualizar cuidadores: ' . $e->getMessage(), 500);
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => $instId,
        'accion'         => 'residente_cuidadores_sync',
        'modulo'         => 'residentes',
        'detalle'        => "ID {$id}: " . count($valid) . ' cuidadores',
    ]);

    api_ok(['count' => count($valid)], 'Cuidadores actualizados');
}

// ─────────────────────────────────────────────────────────────────────────────
// POST action=move — mover un residente a otra institución
// ─────────────────────────────────────────────────────────────────────────────
// Body: { institucion_destino_id: int }
// Reglas:
//   - Admin/superadmin siempre pueden.
//   - Médico/enfermero solo si la matriz habilita mover_residente_institucion.
//   - Admin debe ser admin en destino; roles delegados deben estar vinculados al destino.
//   - Se actualiza residentes.institucion_id, prescripciones.institucion_id,
//     y tablas clínicas relacionadas en la conexión principal del modelo.
//   - Los pivots usuario_residentes se recrean en destino si el usuario tiene
//     acceso a la institución destino; si no, se eliminan.
//   - Se registra en log de auditoría: accion='residente_move'.
if ($method === 'POST' && ($_GET['action'] ?? '') === 'move') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
    if ($id <= 0) api_error('ID requerido', 400);
    api_assert_residente($id);

    $body   = api_body();
    $destId = (int)($body['institucion_destino_id'] ?? 0);
    if ($destId <= 0) api_error('Institución destino requerida', 400);

    $instId = api_inst_id();
    if ($destId === $instId) api_error('La institución destino debe ser distinta a la actual', 400);

    $uid = api_user_id();
    $db  = Database::getMaster();

        $rolUser = api_rol();
        $adminOnly = in_array($rolUser, ['superadmin','admin'], true);
        if (!_res_can_move_resident_institution($instId)) {
                api_error('No tienes permisos para mover residentes entre instituciones', 403);
        }

        // Verificar permisos/acceso del usuario en destino.
        $destAccessSql = $adminOnly
                ? "SELECT 1 FROM usuario_instituciones
                         WHERE usuario_id = ? AND institucion_id = ? AND rol IN ('admin','superadmin') AND estado='activo'
                     UNION
                     SELECT 1 FROM usuarios
                         WHERE id = ? AND institucion_id = ? AND rol IN ('admin','superadmin')"
                : "SELECT 1 FROM usuario_instituciones
                         WHERE usuario_id = ? AND institucion_id = ? AND estado='activo'
                     UNION
                     SELECT 1 FROM usuarios
                         WHERE id = ? AND institucion_id = ?";
        $perm = $db->prepare($destAccessSql);
    $perm->execute([$uid, $destId, $uid, $destId]);
        if (!$perm->fetchColumn() && $rolUser !== 'superadmin') {
                api_error($adminOnly ? 'No tienes permisos de admin en la institución destino' : 'No tienes acceso a la institución destino', 403);
    }

    // Verificar institución destino
    $dbq = $db->prepare("SELECT id, nombre, db_name, estado FROM instituciones WHERE id IN (?, ?)");
    $dbq->execute([$instId, $destId]);
    $rows = $dbq->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) !== 2) api_error('Institución destino no encontrada', 404);
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;
    $orig = $byId[$instId] ?? null;
    $dest = $byId[$destId] ?? null;
    if (!$orig || !$dest) api_error('Institución no encontrada', 404);
    if (!in_array($dest['estado'], ['activa','trial','past_due'], true)) {
        api_error('La institución destino no está activa', 400);
    }
    // Snapshot del residente para el log
    $r = Residente::getById($id, $instId);
    if (!$r) api_error('Residente no encontrado', 404);

    // Importante: el modelo Residente en v9 usa Database::getInstance() (master).
    // Actualizar getTenant() dejaba el registro visible en la institución original.
    $tdb = Database::getInstance();
    $tdb->beginTransaction();
    try {
        // Mover residente
        $mv = $tdb->prepare("UPDATE residentes SET institucion_id = ? WHERE id = ? AND institucion_id = ?");
        $mv->execute([$destId, $id, $instId]);
        if ($mv->rowCount() < 1) {
            throw new \RuntimeException('No se actualizó el residente; verifica que siga perteneciendo a la institución origen.');
        }

        // Mover datos relacionados que comparten residente_id + institucion_id.
        $relatedTables = [
            'prescripciones',
            'cuidados_registros',
            'cuidados_notas',
            'inventario_items',
            'inventario_movimientos',
            'notificaciones_log',
            'notas_medico',
            'alertas_medico',
            'expediente_docs',
        ];
        foreach ($relatedTables as $table) {
            try {
                $tdb->prepare("UPDATE `$table` SET institucion_id = ? WHERE residente_id = ? AND institucion_id = ?")
                    ->execute([$destId, $id, $instId]);
            } catch (\Throwable $e) { /* tabla opcional según despliegue */ }
        }

        // Mover/limpiar pivots usuario_residentes:
        //   - Si el usuario ligado tiene acceso a destino → reasignar institucion_id
        //   - Si no → eliminar el vínculo (el usuario pierde acceso al residente)
        $linked = UsuarioResidente::getUsuarioIds($id, $instId);
        foreach ($linked as $luid) {
            $luid = (int)$luid;
            // ¿Tiene acceso al destino?
            $acc = $db->prepare(
                "SELECT 1 FROM usuario_instituciones WHERE usuario_id=? AND institucion_id=? AND estado='activo'
                 UNION SELECT 1 FROM usuarios WHERE id=? AND institucion_id=?"
            );
            $acc->execute([$luid, $destId, $luid, $destId]);
            if ($acc->fetchColumn()) {
                UsuarioResidente::add($luid, $id, $destId);
            }
            UsuarioResidente::remove($luid, $id, $instId);
        }

        $tdb->commit();
    } catch (\Throwable $e) {
        if ($tdb->inTransaction()) $tdb->rollBack();
        api_error('Error al mover residente: ' . $e->getMessage(), 500);
    }

    // Recalcular asientos para ambas instituciones (mejor esfuerzo)
    try {
        require_once dirname(__DIR__) . '/db/models/Seats.php';
        $sub = $db->prepare(
            "SELECT s.id FROM suscripcion_instituciones si
              JOIN suscripciones s ON s.id = si.suscripcion_id
              WHERE si.institucion_id IN (?, ?)
              GROUP BY s.id"
        );
        $sub->execute([$instId, $destId]);
        foreach ($sub->fetchAll(\PDO::FETCH_COLUMN) as $sid) {
            Seats::recalcUsage((int)$sid, $instId);
            Seats::recalcUsage((int)$sid, $destId);
        }
    } catch (\Throwable $e) { /* silent */ }

    // Auditoría: log doble (origen y destino) para que sea visible en ambas
    $detalle = sprintf(
        'Residente %s %s (ID %d) movido de "%s" (#%d) a "%s" (#%d)',
        $r['nombre'] ?? '',
        $r['apellidos'] ?? '',
        $id,
        $orig['nombre'],
        $instId,
        $dest['nombre'],
        $destId
    );
    Log::registrar([
        'usuario_id'     => $uid,
        'institucion_id' => $instId,
        'accion'         => 'residente_move',
        'modulo'         => 'residentes',
        'detalle'        => $detalle,
    ]);
    Log::registrar([
        'usuario_id'     => $uid,
        'institucion_id' => $destId,
        'accion'         => 'residente_move',
        'modulo'         => 'residentes',
        'detalle'        => $detalle,
    ]);

    api_ok([
        'id'                => $id,
        'institucion_origen'=> $instId,
        'institucion_destino'=> $destId,
    ], 'Residente movido correctamente');
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — crear
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    api_auth_roles(['superadmin', 'admin']);

    if (api_inst_id() <= 0) api_error('No hay institución seleccionada', 400);    // Estado log creation
    if ($id > 0 && !empty($_GET['estado_log'])) {
        api_assert_residente($id);
        $body = api_body();
        $db   = Database::getInstance();
        $userName = $_SESSION['user_nombre'] ?? 'Sistema';
        $stmt = $db->prepare(
            "INSERT INTO residentes_estado_log (residente_id, institucion_id, estado_anterior, estado_nuevo, usuario_id, usuario_nombre, nota)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $id,
            api_inst_id(),
            $body['estado_anterior'] ?? '',
            $body['estado_nuevo']    ?? '',
            api_user_id(),
            $userName,
            $body['nota'] ?? null,
        ]);
        api_ok(null, 'Estado registrado');
    }

    $body = api_body();

    // Validación
    $errors = [];
    if (empty($body['nombre']))    $errors['nombre']    = 'Requerido';
    if (empty($body['apellidos'])) $errors['apellidos'] = 'Requerido';
    if (!empty($errors)) api_error('Datos inválidos', 422, $errors);

    // ── F6: enforcement de cupo de residentes ────────────────────────────
    require_once dirname(__DIR__) . '/db/models/Seats.php';
    try {
        Seats::assertCanAddResidente(api_inst_id());
    } catch (SeatLimitException $e) {
        api_error($e->getMessage(), 403, [
            'seat_limit' => true,
            'kind'       => $e->tipo,
            'usados'     => $e->usados,
            'limite'     => $e->limite,
            'extra'      => $e->extra,
            'billing_url'=> BASE_URL . '/billing.php#addons',
        ]);
    }

    $data = [
        'institucion_id'      => api_inst_id(),
        'nombre'              => trim($body['nombre']),
        'apellidos'           => trim($body['apellidos']),
        'fecha_nacimiento'    => $body['fecha_nacimiento'] ?? null,
        'sexo'                => $body['sexo']             ?? null,
        'curp'                => strtoupper(trim($body['curp'] ?? '')),
        'nss'                 => trim($body['nss'] ?? ''),
        'estado_civil'        => trim($body['estado_civil'] ?? ''),
        'diagnostico'         => trim($body['diagnostico'] ?? ''),
        'alergias'            => trim($body['alergias']    ?? ''),
        'medico_id'           => !empty($body['medico_id'])   ? (int)$body['medico_id']   : null,
        'familiar_id'         => !empty($body['familiar_id']) ? (int)$body['familiar_id'] : null,
        'habitacion'          => trim($body['habitacion']  ?? ''),
        'fecha_ingreso'       => $body['fecha_ingreso']    ?? date('Y-m-d'),
        'estado'              => 'activo',
        'notas'               => trim($body['notas'] ?? ''),
        'cuidados_especiales' => trim($body['cuidados_especiales'] ?? ''),
        'contacto_nombre'     => trim($body['contacto_nombre'] ?? ''),
        'contacto_parentesco' => trim($body['contacto_parentesco'] ?? ''),
        'contacto_telefono'   => trim($body['contacto_telefono'] ?? ''),
        'contacto_telefono2'  => trim($body['contacto_telefono2'] ?? ''),
        'contacto_email'      => trim($body['contacto_email'] ?? ''),
        'contacto_direccion'  => trim($body['contacto_direccion'] ?? ''),
    ];
    $data = api_normalize_phone_fields($data, ['contacto_telefono', 'contacto_telefono2']);
    $flatContact = [
        'nombre'     => $data['contacto_nombre'] ?? '',
        'parentesco' => $data['contacto_parentesco'] ?? '',
        'telefono'   => $data['contacto_telefono'] ?? '',
        'telefono2'  => $data['contacto_telefono2'] ?? '',
        'email'      => $data['contacto_email'] ?? '',
        'direccion'  => $data['contacto_direccion'] ?? '',
        'principal'  => 1,
        'source'     => 'contacto_inicial',
    ];
    if (trim(implode('', array_intersect_key($flatContact, array_flip(['nombre','parentesco','telefono','telefono2','email','direccion'])))) !== '') {
        $data['contactos_json'] = json_encode([$flatContact], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $newId = Residente::create($data);
    if (!$newId) api_error('Error al crear residente', 500);

    // ── Asignación granular de cuidadores (usuario_residentes) ───────────
    // El payload puede incluir cuidador_ids: array de usuario_id que tendrán
    // acceso explícito a este residente.
    $cuidadorIds = $body['cuidador_ids'] ?? null;
    $assignedCount = 0;
    if (is_array($cuidadorIds) && !empty($cuidadorIds)) {
        $instId = api_inst_id();
        // Validar que cada usuario pertenezca a la institución y sea cuidador
        $db = Database::getInstance();
        $cleanIds = array_values(array_unique(array_map('intval', $cuidadorIds)));
        $cleanIds = array_filter($cleanIds, fn($v) => $v > 0);
        if (!empty($cleanIds)) {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmt = $db->prepare(
                                "SELECT u.id
                                 FROM usuario_instituciones ui
                                 INNER JOIN usuarios u ON u.id = ui.usuario_id
                                 WHERE u.id IN ($placeholders)
                                     AND ui.institucion_id = ?
                                     AND ui.estado = 'activo'
                   AND u.estado = 'activo'
                                     AND ui.rol IN ('enfermero','medico','admin')"
            );
                        $params = array_merge($cleanIds, [$instId]);
            $stmt->execute($params);
            $valid = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($valid as $uid) {
                try { UsuarioResidente::add((int)$uid, (int)$newId, $instId); $assignedCount++; } catch (\Throwable $e) {}
            }
        }
    }

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'residente_crear',
        'modulo'         => 'residentes',
        'detalle'        => "ID {$newId}: {$data['nombre']} {$data['apellidos']}"
                           . ($assignedCount ? " | {$assignedCount} cuidadores asignados" : ''),
    ]);

    // ── F6: refrescar cache de uso de asientos ───────────────────────────
    try { Seats::recalcUsageForInst(api_inst_id()); } catch (\Throwable $e) { /* tolerante */ }

    $residente = Residente::getById($newId, api_inst_id());
    api_ok($residente, 'Residente creado correctamente');
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — actualizar
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

    if ($id <= 0) api_error('ID requerido', 400);
    api_assert_residente($id);

    $body    = api_body();
    $allowed = [
        'nombre', 'apellidos', 'fecha_nacimiento', 'sexo', 'curp', 'nss',
        'estado_civil', 'diagnostico', 'alergias', 'medico_id', 'familiar_id',
        'habitacion', 'fecha_ingreso', 'estado', 'foto_path', 'notas',
        'cuidados_especiales', 'contacto_nombre', 'contacto_parentesco',
        'contacto_telefono', 'contacto_telefono2', 'contacto_email', 'contacto_direccion',
        'contactos_json',
    ];
    $data = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $body)) {
            $data[$k] = is_string($body[$k]) ? trim($body[$k]) : $body[$k];
        }
    }
    $data = api_normalize_phone_fields($data, ['contacto_telefono', 'contacto_telefono2']);
    if (array_key_exists('contactos_json', $data)) {
        $data['contactos_json'] = api_normalize_contactos_json_phones($data['contactos_json']);
    }
    if (empty($data)) api_error('Sin campos para actualizar', 422);

    $ok = Residente::update($id, api_inst_id(), $data);
    if (!$ok) api_error('Error al actualizar', 500);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'residente_actualizar',
        'modulo'         => 'residentes',
        'detalle'        => "ID {$id}",
    ]);

    api_ok(Residente::getById($id, api_inst_id()), 'Residente actualizado');
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — eliminar / borrar foto
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id <= 0) api_error('ID requerido', 400);
    api_assert_residente($id);

    // DELETE photo only
    if (!empty($_GET['foto'])) {
        api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
        $instId = api_inst_id();
        $r = Residente::getById($id, $instId);
        if (!empty($r['foto_path'])) {
            $oldFile = dirname(__DIR__) . '/' . $r['foto_path'];
            if (file_exists($oldFile)) @unlink($oldFile);
            Residente::update($id, $instId, ['foto_path' => null]);
        }
        api_ok(null, 'Foto eliminada');
    }

    api_auth_roles(['superadmin', 'admin']);

    $ok = Residente::delete($id, api_inst_id());
    if (!$ok) api_error('Error al eliminar', 500);

    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'residente_eliminar',
        'modulo'         => 'residentes',
        'detalle'        => "ID {$id}",
    ]);

    // ── F6: refrescar cache de uso de asientos ───────────────────────────
    require_once dirname(__DIR__) . '/db/models/Seats.php';
    try { Seats::recalcUsageForInst(api_inst_id()); } catch (\Throwable $e) { /* tolerante */ }

    api_ok(null, 'Residente eliminado');
}

api_error('Método no soportado', 405);
