<?php
/**
 * GeriApp — API /api/instituciones.php
 *
 * Gestión multi-institución para el rol admin (owner). Superadmin ve y gestiona
 * todas las instituciones; un admin solo las que están vinculadas a su
 * suscripción y/o donde tiene rol 'admin' en usuario_instituciones.
 *
 * Endpoints:
 *   GET    ?action=list              → instituciones del owner + límite del plan
 *   POST   ?action=create            → crea institución y la vincula al owner + suscripción
 *   PUT    ?action=update            → actualiza datos básicos
 *   POST   ?action=archive           → marca estado='archivada' (soft)
 *   POST   ?action=restore           → restaura desde 'archivada' a 'activa'
 *
 * Cuotas: respeta plan.max_instituciones del plan vigente del owner.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

api_auth();
api_auth_roles(['admin', 'superadmin']);

$db     = Database::getMaster();
$action = $_GET['action'] ?? '';
$method = api_method();
$userId = api_user_id();
$rol    = api_rol();

/* ─────────────────────────────────────────────────────────────────────────── */
/* Helpers internos                                                            */
/* ─────────────────────────────────────────────────────────────────────────── */

/** Devuelve la suscripción activa del owner actual (admin) o null. */
function _owner_subscription(PDO $db, int $userId): ?array {
    $st = $db->prepare(
        "SELECT s.id, s.plan_id, s.estado, s.seats_residente_extra,
                p.max_instituciones, p.nombre AS plan_nombre, p.tier
         FROM suscripciones s
         LEFT JOIN planes p ON p.id = s.plan_id
         WHERE s.owner_user_id = ?
         ORDER BY FIELD(s.estado,'activa','trial','past_due'), s.id DESC
         LIMIT 1"
    );
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** IDs de instituciones administradas por el usuario. */
function _owner_inst_ids(PDO $db, int $userId, string $rol, ?int $subId): array {
    if ($rol === 'superadmin') {
        $st = $db->query("SELECT id FROM instituciones");
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    // Superadmin impersonating as another role: scope to the impersonated institution
    if (!empty($_SESSION['sa_impersonating'])) {
        $instId = (int)($_SESSION['user_institucion_id'] ?? $_SESSION['sa_imp_inst_id'] ?? 0);
        return $instId > 0 ? [$instId] : [];
    }
    $ids = [];
    if ($subId) {
        $st = $db->prepare("SELECT institucion_id FROM suscripcion_instituciones WHERE suscripcion_id = ?");
        $st->execute([$subId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    // Sumar también las instituciones donde el usuario tiene rol admin via pivot.
    try {
        $st = $db->prepare(
            "SELECT DISTINCT institucion_id FROM usuario_instituciones
             WHERE usuario_id = ? AND rol IN ('admin') AND estado = 'activo'"
        );
        $st->execute([$userId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $iid) {
            $iid = (int)$iid;
            if ($iid && !in_array($iid, $ids, true)) $ids[] = $iid;
        }
    } catch (\Throwable $e) {}
    return $ids;
}

/** Verifica acceso del owner a una institución específica. */
function _owner_can_access(PDO $db, int $userId, string $rol, int $instId, ?int $subId): bool {
    if ($rol === 'superadmin') return true;
    if ($instId <= 0) return false;
    return in_array($instId, _owner_inst_ids($db, $userId, $rol, $subId), true);
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* GET ?action=list                                                            */
/* ─────────────────────────────────────────────────────────────────────────── */
if ($action === 'list' && $method === 'GET') {
    $sub    = _owner_subscription($db, $userId);
    $subId  = $sub ? (int)$sub['id'] : null;
    $instIds = _owner_inst_ids($db, $userId, $rol, $subId);

    $list = [];
    if (!empty($instIds)) {
        $place = implode(',', array_fill(0, count($instIds), '?'));
        $st = $db->prepare(
            "SELECT i.id, i.nombre, i.email_admin, i.telefono, i.direccion, i.timezone,
                    i.estado, i.trial_ends_at, i.plan_vence_at,
                    i.max_residentes, i.max_usuarios, i.logo_path,
                    i.rfc, i.ciudad, i.estado_inst AS estado_geo, i.num_camas,
                    i.creado_at, i.updated_at,
                    (SELECT COUNT(*) FROM residentes r WHERE r.institucion_id = i.id AND r.estado = 'activo') AS residentes_activos,
                    (SELECT COUNT(*) FROM usuario_instituciones ui WHERE ui.institucion_id = i.id AND ui.estado='activo') AS usuarios_count
             FROM instituciones i
             WHERE i.id IN ($place)
             ORDER BY (i.estado='archivada'), i.nombre"
        );
        $st->execute($instIds);
        $list = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $maxInst = $sub && $sub['max_instituciones'] !== null ? (int)$sub['max_instituciones'] : null;
    // Usadas = no archivadas
    $usadas = 0;
    foreach ($list as $i) { if (($i['estado'] ?? '') !== 'archivada') $usadas++; }

    api_ok([
        'instituciones' => $list,
        'plan'          => $sub ? [
            'plan_nombre'      => $sub['plan_nombre'],
            'plan_tier'        => $sub['tier'],
            'max_instituciones'=> $maxInst,
        ] : null,
        'usadas'        => $usadas,
        'puede_crear'   => $rol === 'superadmin' || $maxInst === null || $usadas < $maxInst,
    ]);
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* POST ?action=create                                                         */
/* ─────────────────────────────────────────────────────────────────────────── */
if ($action === 'create' && $method === 'POST') {
    $body = api_body();
    $errors = [];
    $nombre   = trim((string)($body['nombre'] ?? ''));
    $email    = trim((string)($body['email_admin'] ?? ''));
    $telefono = trim((string)($body['telefono'] ?? ''));
    $direccion= trim((string)($body['direccion'] ?? ''));
    $timezone = trim((string)($body['timezone'] ?? 'America/Mexico_City'));
    $rfc      = trim((string)($body['rfc'] ?? ''));
    $ciudad   = trim((string)($body['ciudad'] ?? ''));
    $estadoGeo= trim((string)($body['estado_geo'] ?? ''));
    $numCamas = isset($body['num_camas']) ? (int)$body['num_camas'] : null;

    if ($nombre === '')                       $errors['nombre'] = 'Requerido';
    if ($email === '')                        $errors['email_admin'] = 'Requerido';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email_admin'] = 'Email inválido';
    if ($timezone === '')                     $errors['timezone'] = 'Requerido';
    if ($errors) api_error('Datos inválidos', 422, $errors);

    // Cuotas
    $sub   = _owner_subscription($db, $userId);
    $subId = $sub ? (int)$sub['id'] : null;
    if ($rol !== 'superadmin') {
        $maxInst = $sub && $sub['max_instituciones'] !== null ? (int)$sub['max_instituciones'] : null;
        if ($maxInst !== null) {
            $usadas = count(array_filter(
                _owner_inst_ids($db, $userId, $rol, $subId),
                function($iid) use ($db) {
                    $st = $db->prepare("SELECT estado FROM instituciones WHERE id = ?");
                    $st->execute([$iid]);
                    return ($st->fetchColumn() ?: '') !== 'archivada';
                }
            ));
            if ($usadas >= $maxInst) {
                api_error("Tu plan permite hasta $maxInst instituciones. Archiva alguna o amplía tu plan para crear más.", 403);
            }
        }
    }

    $db->beginTransaction();
    try {
        $st = $db->prepare(
            "INSERT INTO instituciones
                (nombre, email_admin, telefono, direccion, timezone, estado,
                 rfc, ciudad, estado_inst, num_camas, creado_por)
             VALUES (?, ?, ?, ?, ?, 'activa', ?, ?, ?, ?, ?)"
        );
        $st->execute([
            $nombre, $email,
            $telefono ?: null, $direccion ?: null, $timezone,
            $rfc ?: null, $ciudad ?: null, $estadoGeo ?: null,
            $numCamas ?: null,
            ($_SESSION['user_email'] ?? $_SESSION['user_name'] ?? 'admin'),
        ]);
        $newId = (int)$db->lastInsertId();

        // Vincular al owner como admin (pivot)
        try {
            $st = $db->prepare(
                "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol, estado)
                 VALUES (?, ?, 'admin', 'activo')"
            );
            $st->execute([$userId, $newId]);
        } catch (\Throwable $e) {}

        // Vincular a la suscripción del owner
        if ($subId) {
            try {
                $st = $db->prepare(
                    "INSERT IGNORE INTO suscripcion_instituciones (suscripcion_id, institucion_id)
                     VALUES (?, ?)"
                );
                $st->execute([$subId, $newId]);
            } catch (\Throwable $e) {}
        }

        // Configuración por defecto (si la tabla existe)
        try {
            $st = $db->prepare("INSERT IGNORE INTO configuracion (id) VALUES (?)");
            $st->execute([$newId]);
        } catch (\Throwable $e) {}

        $db->commit();

        // Log
        try {
            $db->prepare(
                "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
                 VALUES (?, ?, 'institucion_create', 'Instituciones', ?, 'ok')"
            )->execute([$userId, $newId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable $e) {}

        api_ok(['id' => $newId], 'Institución creada');
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        api_error('Error al crear institución: ' . $e->getMessage(), 500);
    }
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* PUT ?action=update                                                          */
/* ─────────────────────────────────────────────────────────────────────────── */
if ($action === 'update' && in_array($method, ['PUT','POST'], true)) {
    $body = api_body();
    $id   = (int)($body['id'] ?? api_int('id'));
    if ($id <= 0) api_error('ID requerido', 422);

    $sub   = _owner_subscription($db, $userId);
    $subId = $sub ? (int)$sub['id'] : null;
    if (!_owner_can_access($db, $userId, $rol, $id, $subId)) api_error('Acceso denegado', 403);

    $allowed = ['nombre','email_admin','telefono','direccion','timezone',
                'rfc','ciudad','estado_geo','num_camas'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $body)) continue;
        $v = $body[$k];
        $col = $k === 'estado_geo' ? 'estado_inst' : $k;
        if ($k === 'num_camas') $v = $v === '' || $v === null ? null : (int)$v;
        elseif (is_string($v))   $v = trim($v);
        if ($k === 'email_admin' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            api_error('Email inválido', 422, ['email_admin' => 'Inválido']);
        }
        $sets[] = "$col = ?";
        $vals[] = $v === '' ? null : $v;
    }
    if (!$sets) api_error('Sin cambios', 422);
    $vals[] = $id;
    $st = $db->prepare("UPDATE instituciones SET " . implode(', ', $sets) . " WHERE id = ?");
    $st->execute($vals);

    try {
        $db->prepare(
            "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
             VALUES (?, ?, 'institucion_update', 'Instituciones', ?, 'ok')"
        )->execute([$userId, $id, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (\Throwable $e) {}

    api_ok(['id' => $id], 'Institución actualizada');
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* POST ?action=archive                                                        */
/* ─────────────────────────────────────────────────────────────────────────── */
if ($action === 'archive' && $method === 'POST') {
    $body = api_body();
    $id   = (int)($body['id'] ?? api_int('id'));
    if ($id <= 0) api_error('ID requerido', 422);

    $sub   = _owner_subscription($db, $userId);
    $subId = $sub ? (int)$sub['id'] : null;
    if (!_owner_can_access($db, $userId, $rol, $id, $subId)) api_error('Acceso denegado', 403);

    $db->prepare("UPDATE instituciones SET estado = 'archivada' WHERE id = ?")->execute([$id]);

    try {
        $db->prepare(
            "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
             VALUES (?, ?, 'institucion_archive', 'Instituciones', ?, 'ok')"
        )->execute([$userId, $id, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (\Throwable $e) {}

    api_ok(['id' => $id], 'Institución archivada');
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* POST ?action=restore                                                        */
/* ─────────────────────────────────────────────────────────────────────────── */
if ($action === 'restore' && $method === 'POST') {
    $body = api_body();
    $id   = (int)($body['id'] ?? api_int('id'));
    if ($id <= 0) api_error('ID requerido', 422);

    $sub   = _owner_subscription($db, $userId);
    $subId = $sub ? (int)$sub['id'] : null;
    if (!_owner_can_access($db, $userId, $rol, $id, $subId)) api_error('Acceso denegado', 403);

    // Cuota al restaurar
    if ($rol !== 'superadmin') {
        $maxInst = $sub && $sub['max_instituciones'] !== null ? (int)$sub['max_instituciones'] : null;
        if ($maxInst !== null) {
            $usadas = 0;
            foreach (_owner_inst_ids($db, $userId, $rol, $subId) as $iid) {
                $st = $db->prepare("SELECT estado FROM instituciones WHERE id = ?");
                $st->execute([$iid]);
                if (($st->fetchColumn() ?: '') !== 'archivada') $usadas++;
            }
            if ($usadas >= $maxInst) {
                api_error("Tu plan permite hasta $maxInst instituciones activas. Archiva otra primero o amplía tu plan.", 403);
            }
        }
    }

    $db->prepare("UPDATE instituciones SET estado = 'activa' WHERE id = ?")->execute([$id]);
    api_ok(['id' => $id], 'Institución restaurada');
}

api_error('Acción no soportada', 404);
