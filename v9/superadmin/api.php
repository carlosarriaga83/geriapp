<?php
/**
 * GeriApp — Superadmin API
 *
 * Endpoints para el portal de superadmin:
 *   - sync/test       → Probar conexión a un perfil de BD
 *   - sync/list_dbs   → Listar BDs de tenant en un perfil
 *   - sync/run        → Ejecutar sincronización source→target
 *   - instituciones   → CRUD de instituciones
 *   - planes          → CRUD de planes
 *   - usuarios        → Gestión global de usuarios
 *   - logs            → Visor de logs del sistema
 *   - config          → Parámetros globales
 */

require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once dirname(__DIR__) . '/db/Database.php';
require_once dirname(__DIR__) . '/db/models/GeriappEventInvitation.php';

header('Content-Type: application/json; charset=utf-8');

$action  = $_GET['action'] ?? ($_POST['action'] ?? '');
$method  = $_SERVER['REQUEST_METHOD'];
$input   = ($method === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

function sa_role_storage(?string $role): string
{
    $role = trim((string)$role);
    return $role === 'cuidador' ? 'enfermero' : $role;
}

function sa_roles_storage(array $roles): array
{
    return array_values(array_unique(array_map('sa_role_storage', $roles)));
}

function sa_role_label(?string $role): string
{
    return [
        'superadmin' => 'Superadmin',
        'admin' => 'Administrador',
        'medico' => 'Médico/a',
        'enfermero' => 'Cuidador/a',
        'cuidador' => 'Cuidador/a',
        'familiar' => 'Familiar',
    ][trim((string)$role)] ?? ucfirst((string)$role);
}

// ─────────────────────────────────────────────────────────────────────────────
// Router
// ─────────────────────────────────────────────────────────────────────────────

try {
    switch ($action) {

        // ── Sync: probar conexión ──────────────────────────────────────────
        case 'sync_test':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $profile = $input['profile'] ?? '';

            // Support testing ad-hoc connection data (for new/unsaved profiles)
            if (!empty($input['adhoc'])) {
                $p = $input['adhoc'];
                $p['port'] = (int)($p['port'] ?? 3306);
                $p['charset'] = $p['charset'] ?? 'utf8mb4';
            } else {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$profile])) throw new Exception("Perfil '$profile' no encontrado");
                $p = $profiles[$profile];
            }

            $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['db']};charset={$p['charset']}";
            $start = microtime(true);
            $pdo = new PDO($dsn, $p['user'], $p['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
            $elapsed = round((microtime(true) - $start) * 1000);
            jsonOk(['version' => $ver, 'ms' => $elapsed, 'host' => $p['host']]);
            break;

        // ── Sync: listar BDs tenant en un perfil ───────────────────────────
        case 'sync_list_dbs':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $profile = $input['profile'] ?? '';
            $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
            if (!isset($profiles[$profile])) throw new Exception("Perfil '$profile' no encontrado");
            $p = $profiles[$profile];
            $pdo = connectProfile($p);

            // Master DB
            $dbs = [['name' => $p['db'], 'type' => 'master', 'tables' => countTables($pdo, $p['db'])]];

            // Tenant DBs
            $prefix = $p['prefix'];
            $stmt = $pdo->query("SHOW DATABASES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $dbName = $row[0];
                if (str_starts_with($dbName, $prefix)) {
                    $dbs[] = ['name' => $dbName, 'type' => 'tenant', 'tables' => countTables($pdo, $dbName)];
                }
            }
            jsonOk(['databases' => $dbs]);
            break;

        // ── Sync: ejecutar sincronización ──────────────────────────────────
        case 'sync_run':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $srcKey  = $input['source']   ?? '';
            $tgtKey  = $input['target']   ?? '';
            $dbList  = $input['databases'] ?? [];
            $createIfMissing = !empty($input['create_if_missing']);
            $structureOnly = !empty($input['structure_only']);
            $targetDbName = isset($input['target_db_name']) && $input['target_db_name'] !== '' ? $input['target_db_name'] : null;
            if ($targetDbName && !preg_match('/^[a-zA-Z0-9_]+$/', $targetDbName)) {
                throw new Exception('Nombre de BD destino inválido (solo a-z, 0-9, _)');
            }
            if ($srcKey === $tgtKey) throw new Exception('Source y target deben ser distintos');
            if (empty($dbList)) throw new Exception('Selecciona al menos una BD');

            $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
            if (!isset($profiles[$srcKey])) throw new Exception("Perfil source '$srcKey' no encontrado");
            if (!isset($profiles[$tgtKey])) throw new Exception("Perfil target '$tgtKey' no encontrado");

            $src = $profiles[$srcKey];
            $tgt = $profiles[$tgtKey];
            $results = [];

            foreach ($dbList as $dbName) {
                // Sanitize DB name
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
                    $results[] = ['db' => $dbName, 'ok' => false, 'error' => 'Nombre de BD inválido'];
                    continue;
                }

                try {
                    $destName = $targetDbName ?: $dbName;
                    $result = syncDatabase($src, $tgt, $dbName, $createIfMissing, $destName, $structureOnly);
                    $results[] = ['db' => $dbName, 'ok' => true, 'tables' => $result['tables'], 'rows' => $result['rows'], 'dest_db' => $destName];
                } catch (Throwable $e) {
                    $results[] = ['db' => $dbName, 'ok' => false, 'error' => $e->getMessage()];
                }
            }

            jsonOk(['results' => $results]);
            break;

        // ── Instituciones ──────────────────────────────────────────────────
        case 'instituciones':
            $db = Database::getMaster();
            if ($method === 'GET') {
                $estado = $_GET['estado'] ?? '';
                $where = '';
                $params = [];
                if ($estado && in_array($estado, ['activa','trial','suspendida','archivada'], true)) {
                    $where = 'WHERE i.estado = ?';
                    $params[] = $estado;
                }
                                $stmt = $db->prepare(
                                        "SELECT i.*, p.nombre AS plan_nombre,
                                                        (SELECT COUNT(*) FROM usuario_instituciones ui WHERE ui.institucion_id = i.id AND ui.estado = 'activo') AS num_usuarios,
                                                        (SELECT COUNT(*)
                                                         FROM usuario_instituciones ui
                                                         JOIN usuarios u ON u.id = ui.usuario_id
                                                         WHERE ui.institucion_id = i.id
                                                             AND ui.estado = 'activo'
                                                             AND (ui.rol = 'admin' OR u.rol = 'admin')) AS num_admins,
                                                        (SELECT u.id
                                                         FROM usuario_instituciones ui
                                                         JOIN usuarios u ON u.id = ui.usuario_id
                                                         WHERE ui.institucion_id = i.id
                                                             AND ui.estado = 'activo'
                                                             AND (ui.rol = 'admin' OR u.rol = 'admin')
                                                         ORDER BY CASE WHEN ui.rol = 'admin' THEN 0 ELSE 1 END, u.id
                                                         LIMIT 1) AS admin_id,
                                                        (SELECT u.nombre
                                                         FROM usuario_instituciones ui
                                                         JOIN usuarios u ON u.id = ui.usuario_id
                                                         WHERE ui.institucion_id = i.id
                                                             AND ui.estado = 'activo'
                                                             AND (ui.rol = 'admin' OR u.rol = 'admin')
                                                         ORDER BY CASE WHEN ui.rol = 'admin' THEN 0 ELSE 1 END, u.id
                                                         LIMIT 1) AS admin_nombre,
                                                        (SELECT u.email
                                                         FROM usuario_instituciones ui
                                                         JOIN usuarios u ON u.id = ui.usuario_id
                                                         WHERE ui.institucion_id = i.id
                                                             AND ui.estado = 'activo'
                                                             AND (ui.rol = 'admin' OR u.rol = 'admin')
                                                         ORDER BY CASE WHEN ui.rol = 'admin' THEN 0 ELSE 1 END, u.id
                                                         LIMIT 1) AS admin_email,
                                                        (SELECT u.telefono
                                                         FROM usuario_instituciones ui
                                                         JOIN usuarios u ON u.id = ui.usuario_id
                                                         WHERE ui.institucion_id = i.id
                                                             AND ui.estado = 'activo'
                                                             AND (ui.rol = 'admin' OR u.rol = 'admin')
                                                         ORDER BY CASE WHEN ui.rol = 'admin' THEN 0 ELSE 1 END, u.id
                                                         LIMIT 1) AS admin_telefono
                                         FROM instituciones i
                                         LEFT JOIN planes p ON p.id = i.plan_id
                                         $where
                                         ORDER BY COALESCE(admin_nombre, i.email_admin, 'Sin administrador'), i.nombre"
                                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                jsonOk(['instituciones' => $rows]);
            } elseif ($method === 'POST') {
                $subAction = $input['sub'] ?? 'update';
                $id = (int)($input['id'] ?? 0);
                if ($subAction === 'update' && $id > 0) {
                    $allowed = ['nombre', 'estado', 'plan_id', 'max_residentes', 'max_usuarios'];
                    $sets = []; $vals = [];
                    foreach ($allowed as $f) {
                        if (array_key_exists($f, $input)) {
                            $v = $input[$f];
                            if ($f === 'plan_id' && ($v === '' || $v === '0' || $v === 0 || $v === null)) $v = null;
                            if (in_array($f, ['max_residentes', 'max_usuarios'], true) && ($v === '' || $v === '0' || $v === 0)) $v = null;
                            $sets[] = "`$f` = ?";
                            $vals[] = $v;
                        }
                    }
                    if ($sets) {
                        $vals[] = $id;
                        $db->prepare("UPDATE instituciones SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
                    }
                    jsonOk(['updated' => $id]);
                } elseif ($subAction === 'delete' && $id > 0) {
                    $stmt = $db->prepare("SELECT id, nombre FROM instituciones WHERE id = ?");
                    $stmt->execute([$id]);
                    $inst = $stmt->fetch();
                    if (!$inst) throw new Exception('Institución no encontrada');

                    $db->prepare("DELETE FROM usuario_instituciones WHERE institucion_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM usuarios WHERE institucion_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM instituciones WHERE id = ?")->execute([$id]);
                    jsonOk(['deleted' => $id, 'nombre' => $inst['nombre']]);
                } elseif ($subAction === 'create') {
                    $nombre = trim($input['nombre'] ?? '');
                    if (!$nombre) throw new Exception('Nombre requerido');
                    $planId = (int)($input['plan_id'] ?? 0) ?: null;
                    $maxRes = (int)($input['max_residentes'] ?? 0) ?: null;
                    $maxUsr = (int)($input['max_usuarios'] ?? 0) ?: null;
                    $estado = $input['estado'] ?? 'activo';
                    $db->prepare(
                        "INSERT INTO instituciones (nombre, plan_id, max_residentes, max_usuarios, estado)
                         VALUES (?, ?, ?, ?, ?)"
                    )->execute([$nombre, $planId, $maxRes, $maxUsr, $estado]);
                    jsonOk(['created' => $db->lastInsertId()]);
                } else {
                    throw new Exception('Acción no soportada');
                }
            }
            break;

        // ── Integraciones globales (.env → defaults de instituciones) ──────
        case 'integraciones':
            $defaults = require dirname(__DIR__) . '/conf/institucion_defaults.php';
            $int = $defaults['integraciones'] ?? [];
            $notif = $defaults['notificaciones'] ?? [];
            $mask = static function ($value): string {
                $value = (string)$value;
                $len = strlen($value);
                if ($len === 0) return '';
                if ($len <= 8) return str_repeat('*', $len);
                return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
            };
            $publicInt = $int;
            foreach (['smtp_password','wa_api_key','wa_instance_id','ia_api_key'] as $secretKey) {
                if (array_key_exists($secretKey, $publicInt)) {
                    $publicInt[$secretKey] = $mask($publicInt[$secretKey] ?? '');
                }
            }

            $smtpReady = !empty($int['smtp_host']) && !empty($int['smtp_from_email']);
            $waReady = !empty($int['wa_api_key']);
            $iaReady = !empty($int['ia_api_key']);

            if ($method === 'GET') {
                $db = Database::getMaster();
                $coverage = ['total' => 0, 'smtp_incompleto' => 0, 'wa_incompleto' => 0, 'ia_incompleto' => 0];
                try {
                    $coverage['total'] = (int)$db->query("SELECT COUNT(*) FROM instituciones")->fetchColumn();
                    $coverage['smtp_incompleto'] = (int)$db->query("SELECT COUNT(*) FROM instituciones i LEFT JOIN configuracion c ON c.institucion_id=i.id WHERE c.institucion_id IS NULL OR c.smtp_host IS NULL OR c.smtp_host='' OR c.smtp_from_email IS NULL OR c.smtp_from_email=''")->fetchColumn();
                    $coverage['wa_incompleto'] = (int)$db->query("SELECT COUNT(*) FROM instituciones i LEFT JOIN configuracion c ON c.institucion_id=i.id WHERE c.institucion_id IS NULL OR c.wa_api_key IS NULL OR c.wa_api_key=''")->fetchColumn();
                    $coverage['ia_incompleto'] = (int)$db->query("SELECT COUNT(*) FROM instituciones i LEFT JOIN configuracion c ON c.institucion_id=i.id WHERE c.institucion_id IS NULL OR c.ia_api_key IS NULL OR c.ia_api_key=''")->fetchColumn();
                } catch (Throwable $e) { /* tabla pendiente o config parcial */ }

                jsonOk([
                    'integraciones' => $publicInt,
                    'notificaciones' => [
                        'notif_canal_email' => (int)($notif['notif_canal_email'] ?? 0),
                        'notif_canal_wa' => (int)($notif['notif_canal_wa'] ?? 0),
                    ],
                    'ready' => ['smtp' => $smtpReady, 'whatsapp' => $waReady, 'ia' => $iaReady],
                    'coverage' => $coverage,
                ]);
            }

            if ($method === 'POST') {
                $sub = $input['sub'] ?? '';
                if ($sub === 'apply_defaults') {
                    require_once dirname(__DIR__) . '/db/models/Configuracion.php';
                    $db = Database::getMaster();
                    $ids = $db->query("SELECT id FROM instituciones ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
                    $fields = [
                        'smtp_host','smtp_port','smtp_usuario','smtp_password','smtp_encriptacion','smtp_timeout','smtp_sandbox','smtp_from_email','smtp_from_nombre',
                        'wa_proveedor','wa_api_key','wa_instance_id','wa_phone','wa_activo','wa_sandbox','support_phone',
                        'ia_proveedor','ia_api_key','ia_modelo','ia_prompt','ia_max_palabras','legal_cc_email','notif_canal_email'
                    ];
                    $updated = 0;
                    $patchedFields = 0;
                    foreach ($ids as $id) {
                        $instId = (int)$id;
                        $cfg = Configuracion::getOrCreate($instId) ?: [];
                        $patch = [];
                        foreach ($fields as $field) {
                            $defaultValue = $field === 'notif_canal_email'
                                ? (string)($notif['notif_canal_email'] ?? '')
                                : (string)($int[$field] ?? '');
                            if ($defaultValue === '') continue;
                            $currentValue = (string)($cfg[$field] ?? '');
                            if ($currentValue === '') {
                                $patch[$field] = $defaultValue;
                            }
                        }
                        if ($patch) {
                            Configuracion::upsert($instId, $patch);
                            $updated++;
                            $patchedFields += count($patch);
                        }
                    }
                    jsonOk(['updated' => $updated, 'fields' => $patchedFields]);
                }
                if ($sub === 'test_smtp') {
                    $destEmail = strtolower(trim((string)($input['dest_email'] ?? '')));
                    if (!$destEmail || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) throw new Exception('Correo destino inválido');
                    require_once dirname(__DIR__) . '/db/models/Mailer.php';
                    $mailer = new Mailer($int);
                    $result = $mailer->testConnection($destEmail);
                    if (empty($result['ok'])) throw new Exception($result['error'] ?? 'No se pudo enviar la prueba SMTP');
                    jsonOk(['smtp_log' => $result['log'] ?? []]);
                }
                throw new Exception('sub requerido');
            }
            break;

        // ── Invitaciones GeriApp por QR (eventos masivos) ──────────────────
        case 'event_qr':
            $db = Database::getMaster();
            if ($method === 'GET') {
                try {
                    $db->exec("UPDATE invitaciones_geriapp SET estado='expirada' WHERE estado='activa' AND expires_at < NOW()");
                } catch (\Throwable $e) { /* tabla pendiente de auditoría BD */ }

                $planes = $db->query(
                    "SELECT id, nombre, tier, trial_dias, requiere_cotizacion, solicita_tarjeta_registro, activo
                     FROM planes
                     WHERE activo = 1
                     ORDER BY orden, id"
                )->fetchAll(PDO::FETCH_ASSOC);

                $rows = [];
                try {
                    $rows = $db->query(
                        "SELECT i.id, i.token, i.plan_id, i.nombre_evento, i.estado, i.expires_at,
                                i.usos_count, i.last_used_at, i.creado_at,
                                p.nombre AS plan_nombre, p.tier AS plan_tier, p.trial_dias, p.solicita_tarjeta_registro
                         FROM invitaciones_geriapp i
                         JOIN planes p ON p.id = i.plan_id
                         ORDER BY i.creado_at DESC
                         LIMIT 50"
                    )->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    $rows = [];
                }

                $rows = saEventQrAttachMetrics($db, $rows);

                $baseUrl = rtrim(app_public_url(), '/');
                foreach ($rows as &$row) {
                    $row['id'] = (int)$row['id'];
                    $row['plan_id'] = (int)$row['plan_id'];
                    $row['trial_dias'] = (int)$row['trial_dias'];
                    $row['solicita_tarjeta_registro'] = (int)($row['solicita_tarjeta_registro'] ?? 0);
                    $row['usos_count'] = (int)$row['usos_count'];
                    $row['scan_count'] = (int)($row['scan_count'] ?? 0);
                    $row['registro_count'] = (int)($row['registro_count'] ?? $row['usos_count']);
                    $row['url'] = $baseUrl . '/register.php?event=' . $row['token'];
                }
                unset($row);

                jsonOk(['planes' => $planes, 'invitaciones' => $rows]);
            } elseif ($method === 'POST') {
                $sub = $input['sub'] ?? 'create';
                if ($sub === 'create') {
                    $planId = (int)($input['plan_id'] ?? 0);
                    $dias = max(1, min(365, (int)($input['dias'] ?? 30)));
                    $nombreEvento = trim((string)($input['nombre_evento'] ?? '')) ?: null;
                    if ($planId <= 0) throw new Exception('Selecciona un paquete');
                    $pl = $db->prepare("SELECT id FROM planes WHERE id = ? AND activo = 1 LIMIT 1");
                    $pl->execute([$planId]);
                    if (!$pl->fetchColumn()) throw new Exception('Paquete no disponible');
                    $createdBy = (int)($_SESSION['superadmin_id'] ?? $_SESSION['user_id'] ?? 0);
                    $created = GeriappEventInvitation::create($planId, $createdBy, $dias, $nombreEvento);
                    if (!$created) throw new Exception('No se pudo crear la invitación');
                    $url = rtrim(app_public_url(), '/') . '/register.php?event=' . $created['token'];
                    jsonOk(['created' => (int)$created['id'], 'token' => $created['token'], 'url' => $url]);
                } elseif ($sub === 'revoke') {
                    $id = (int)($input['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    GeriappEventInvitation::revoke($id);
                    jsonOk(['revoked' => $id]);
                } elseif ($sub === 'details') {
                    $id = (int)($input['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');

                    $stmt = $db->prepare(
                        "SELECT i.id, i.token, i.plan_id, i.nombre_evento, i.estado, i.expires_at,
                                i.usos_count, i.last_used_at, i.creado_at,
                                p.nombre AS plan_nombre, p.tier AS plan_tier, p.trial_dias, p.solicita_tarjeta_registro
                         FROM invitaciones_geriapp i
                         JOIN planes p ON p.id = i.plan_id
                         WHERE i.id = ?
                         LIMIT 1"
                    );
                    $stmt->execute([$id]);
                    $detailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!$detailRows) throw new Exception('QR no encontrado');
                    $detailRows = saEventQrAttachMetrics($db, $detailRows);
                    $row = $detailRows[0];
                    $row['id'] = (int)$row['id'];
                    $row['plan_id'] = (int)$row['plan_id'];
                    $row['trial_dias'] = (int)$row['trial_dias'];
                    $row['solicita_tarjeta_registro'] = (int)($row['solicita_tarjeta_registro'] ?? 0);
                    $row['usos_count'] = (int)$row['usos_count'];
                    $row['scan_count'] = (int)($row['scan_count'] ?? 0);
                    $row['registro_count'] = (int)($row['registro_count'] ?? $row['usos_count']);
                    $row['url'] = rtrim(app_public_url(), '/') . '/register.php?event=' . $row['token'];

                    $eventos = [];
                    try {
                        GeriappEventInvitation::ensureEventTable($db);
                        $ev = $db->prepare(
                            "SELECT e.id, e.tipo, e.usuario_id, e.institucion_id, e.ip, e.user_agent, e.meta, e.creado_at,
                                    u.nombre AS usuario_nombre, u.email AS usuario_email,
                                    inst.nombre AS institucion_nombre
                             FROM invitaciones_geriapp_eventos e
                             LEFT JOIN usuarios u ON u.id = e.usuario_id
                             LEFT JOIN instituciones inst ON inst.id = e.institucion_id
                             WHERE e.invitacion_id = ?
                             ORDER BY e.creado_at DESC, e.id DESC"
                        );
                        $ev->execute([$id]);
                        $eventos = $ev->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($eventos as &$eventRow) {
                            $eventRow['id'] = (int)$eventRow['id'];
                            $eventRow['usuario_id'] = $eventRow['usuario_id'] !== null ? (int)$eventRow['usuario_id'] : null;
                            $eventRow['institucion_id'] = $eventRow['institucion_id'] !== null ? (int)$eventRow['institucion_id'] : null;
                            $eventRow['meta'] = $eventRow['meta'] ? (json_decode($eventRow['meta'], true) ?: null) : null;
                        }
                        unset($eventRow);
                    } catch (\Throwable $e) {
                        $eventos = [];
                    }

                    jsonOk(['invitacion' => $row, 'eventos' => $eventos]);
                } else {
                    throw new Exception('sub requerido');
                }
            }
            break;

        // ── Planes ──────────────────────────────────────────────────────────
        case 'planes':
            $db = Database::getMaster();
            if ($method === 'GET') {
                // Plan + sus precios agrupados por moneda/periodo (para evitar N+1 en el front).
                $rows = $db->query("SELECT * FROM planes ORDER BY orden, id")->fetchAll();
                if ($rows) {
                    $ids = array_column($rows, 'id');
                    $place = implode(',', array_fill(0, count($ids), '?'));
                    $stPP = $db->prepare("SELECT * FROM plan_precios WHERE plan_id IN ($place) ORDER BY moneda, periodo");
                    $stPP->execute($ids);
                    $byPlan = [];
                    foreach ($stPP->fetchAll() as $pp) {
                        $byPlan[(int)$pp['plan_id']][] = $pp;
                    }
                    foreach ($rows as &$r) {
                        $r['precios'] = $byPlan[(int)$r['id']] ?? [];
                    }
                    unset($r);

                    $stPAP = $db->prepare("SELECT * FROM plan_addon_precios WHERE plan_id IN ($place) ORDER BY addon_id, moneda, periodo");
                    try {
                        $stPAP->execute($ids);
                        $byPlanAddon = [];
                        foreach ($stPAP->fetchAll() as $pap) {
                            $byPlanAddon[(int)$pap['plan_id']][] = $pap;
                        }
                        foreach ($rows as &$r) {
                            $r['addon_precios'] = $byPlanAddon[(int)$r['id']] ?? [];
                        }
                        unset($r);
                    } catch (\Throwable $e) {
                        foreach ($rows as &$r) { $r['addon_precios'] = []; }
                        unset($r);
                    }
                }
                jsonOk(['planes' => $rows]);
            } elseif ($method === 'POST') {
                $sub = $input['sub'] ?? '';
                if ($sub === 'update') {
                    $id = (int)($input['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    // Whitelist extendido: incluye nuevos campos de billing F1.
                    $allowed = [
                        'nombre', 'key', 'tier', 'precio', 'descripcion',
                        'max_residentes', 'max_usuarios', 'max_instituciones', 'max_familiares',
                        'max_admin', 'max_cuidador', 'max_medico',
                        'admins_ilimitados', 'cuidadores_ilimitados', 'medicos_ilimitados',
                        'requiere_cotizacion', 'solicita_tarjeta_registro', 'trial_dias', 'stripe_product_id', 'orden', 'activo',
                    ];
                    $sets = []; $vals = [];
                    foreach ($allowed as $f) {
                        if (array_key_exists($f, $input)) {
                            $sets[] = "`$f` = ?";
                            $v = $input[$f];
                            // Normaliza vacíos a NULL en columnas nullable numéricas.
                            if (in_array($f, ['max_residentes','max_usuarios','max_instituciones','max_familiares','max_admin','max_cuidador','max_medico'], true)
                                && ($v === '' || $v === null)) {
                                $v = null;
                            }
                            if ($f === 'trial_dias') {
                                $v = max(0, min(365, (int)$v));
                            }
                            $vals[] = $v;
                        }
                    }
                    if ($sets) {
                        $vals[] = $id;
                        $db->prepare("UPDATE planes SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
                    }
                    jsonOk(['updated' => $id]);
                } elseif ($sub === 'create') {
                    $db->prepare(
                        "INSERT INTO planes
                            (`key`, nombre, tier, precio, descripcion,
                             max_residentes, max_usuarios, max_instituciones, max_familiares,
                             max_admin, max_cuidador, max_medico,
                             admins_ilimitados, cuidadores_ilimitados, medicos_ilimitados,
                                     requiere_cotizacion, solicita_tarjeta_registro, trial_dias, stripe_product_id, orden, activo)
                                 VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?, ?,?,?, ?,?,?,?,?,?)"
                    )->execute([
                        $input['key'] ?? '',
                        $input['nombre'] ?? '',
                        $input['tier'] ?? 'basico',
                        $input['precio'] ?? 0,
                        $input['descripcion'] ?? '',
                        $input['max_residentes'] !== '' ? ($input['max_residentes'] ?? null) : null,
                        $input['max_usuarios']   !== '' ? ($input['max_usuarios']   ?? null) : null,
                        $input['max_instituciones'] !== '' ? ($input['max_instituciones'] ?? null) : null,
                        $input['max_familiares'] !== '' ? ($input['max_familiares'] ?? null) : null,
                        ($input['max_admin']     ?? '') !== '' ? $input['max_admin']     : null,
                        ($input['max_cuidador']  ?? '') !== '' ? $input['max_cuidador']  : null,
                        ($input['max_medico']    ?? '') !== '' ? $input['max_medico']    : null,
                        (int)($input['admins_ilimitados']    ?? 1),
                        (int)($input['cuidadores_ilimitados'] ?? 1),
                        (int)($input['medicos_ilimitados']   ?? 1),
                        (int)($input['requiere_cotizacion']  ?? 0),
                        (int)($input['solicita_tarjeta_registro'] ?? 0),
                        max(0, (int)($input['trial_dias'] ?? 30)),
                        $input['stripe_product_id'] ?? null,
                        (int)($input['orden'] ?? 0),
                        (int)($input['activo'] ?? 1),
                    ]);
                    jsonOk(['created' => $db->lastInsertId()]);
                } elseif ($sub === 'delete') {
                    $id = (int)($input['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    // Bloquea borrado si hay suscripciones activas referenciando el plan.
                    $cnt = (int)$db->query("SELECT COUNT(*) FROM suscripciones WHERE plan_id = $id")->fetchColumn();
                    if ($cnt > 0) throw new Exception("No se puede borrar: $cnt suscripcion(es) usan este plan. Desactívalo en su lugar.");
                    $db->prepare("DELETE FROM plan_precios WHERE plan_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM planes WHERE id = ?")->execute([$id]);
                    jsonOk(['deleted' => $id]);
                } else {
                    throw new Exception('sub requerido');
                }
            }
            break;

        // ── Plan add-on precios (precio del asiento extra por paquete) ──────
        case 'plan_addon_precios':
            $db = Database::getMaster();
            if ($method !== 'POST') throw new Exception('POST requerido');
            $sub = $input['sub'] ?? '';
            if ($sub === 'upsert') {
                $planId  = (int)($input['plan_id'] ?? 0);
                $addonId = (int)($input['addon_id'] ?? 0);
                $moneda  = strtoupper(trim($input['moneda'] ?? ''));
                $periodo = $input['periodo'] ?? 'mensual';
                if ($planId <= 0) throw new Exception('plan_id inválido');
                if ($addonId <= 0) throw new Exception('addon_id inválido');
                if (!preg_match('/^[A-Z]{3}$/', $moneda)) throw new Exception('Moneda ISO-4217 (3 letras) requerida');
                if (!in_array($periodo, ['mensual','anual'], true)) throw new Exception('periodo inválido');
                $precio = (float)($input['precio'] ?? 0);
                if ($precio < 0) throw new Exception('Precio negativo no permitido');
                $stripePriceId = $input['stripe_price_id'] ?? null;
                if ($stripePriceId === '') $stripePriceId = null;
                $activo = (int)!empty($input['activo']);
                $db->prepare(
                    "INSERT INTO plan_addon_precios (plan_id, addon_id, moneda, periodo, precio, stripe_price_id, activo)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        precio = VALUES(precio),
                        stripe_price_id = VALUES(stripe_price_id),
                        activo = VALUES(activo)"
                )->execute([$planId, $addonId, $moneda, $periodo, $precio, $stripePriceId, $activo]);
                jsonOk(['upserted' => true]);
            } else {
                throw new Exception('sub requerido (upsert)');
            }
            break;

        // ── Plan precios (multi-divisa × mensual/anual) ─────────────────────
        case 'plan_precios':
            $db = Database::getMaster();
            if ($method !== 'POST') throw new Exception('POST requerido');
            $sub = $input['sub'] ?? '';
            if ($sub === 'upsert') {
                // Crea o actualiza una fila (plan_id, moneda, periodo) UNIQUE.
                $planId  = (int)($input['plan_id'] ?? 0);
                $moneda  = strtoupper(trim($input['moneda'] ?? ''));
                $periodo = $input['periodo'] ?? 'mensual';
                if ($planId <= 0)                  throw new Exception('plan_id inválido');
                if (!preg_match('/^[A-Z]{3}$/', $moneda)) throw new Exception('Moneda ISO-4217 (3 letras) requerida');
                if (!in_array($periodo, ['mensual','anual'], true)) throw new Exception('periodo inválido');
                $precio = (float)($input['precio'] ?? 0);
                if ($precio < 0) throw new Exception('Precio negativo no permitido');
                $stripePriceId = $input['stripe_price_id'] ?? null;
                if ($stripePriceId === '') $stripePriceId = null;
                $activo = (int)!empty($input['activo']);
                $db->prepare(
                    "INSERT INTO plan_precios (plan_id, moneda, periodo, precio, stripe_price_id, activo)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        precio = VALUES(precio),
                        stripe_price_id = VALUES(stripe_price_id),
                        activo = VALUES(activo)"
                )->execute([$planId, $moneda, $periodo, $precio, $stripePriceId, $activo]);
                jsonOk(['upserted' => true]);
            } elseif ($sub === 'delete') {
                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) throw new Exception('ID inválido');
                $db->prepare("DELETE FROM plan_precios WHERE id = ?")->execute([$id]);
                jsonOk(['deleted' => $id]);
            } else {
                throw new Exception('sub requerido (upsert|delete)');
            }
            break;

        // ── Addons (asientos extra) + sus precios ───────────────────────────
        case 'addons':
            $db = Database::getMaster();
            if ($method === 'GET') {
                $rows = $db->query("SELECT * FROM addons ORDER BY id")->fetchAll();
                if ($rows) {
                    $ids = array_column($rows, 'id');
                    $place = implode(',', array_fill(0, count($ids), '?'));
                    $stAP = $db->prepare("SELECT * FROM addon_precios WHERE addon_id IN ($place) ORDER BY moneda, periodo");
                    $stAP->execute($ids);
                    $byAddon = [];
                    foreach ($stAP->fetchAll() as $ap) {
                        $byAddon[(int)$ap['addon_id']][] = $ap;
                    }
                    foreach ($rows as &$r) {
                        $r['precios'] = $byAddon[(int)$r['id']] ?? [];
                    }
                    unset($r);
                }
                jsonOk(['addons' => $rows]);
            } elseif ($method === 'POST') {
                $sub = $input['sub'] ?? '';
                if ($sub === 'update' || $sub === 'create') {
                    $codigo = trim($input['codigo'] ?? '');
                    $nombre = trim($input['nombre'] ?? '');
                    $tipo   = $input['tipo'] ?? 'asiento_familiar';
                    if (!in_array($tipo, ['asiento_familiar','asiento_residente'], true)) throw new Exception('tipo inválido');
                    if ($sub === 'create') {
                        if (!$codigo || !$nombre) throw new Exception('codigo y nombre requeridos');
                        $db->prepare(
                            "INSERT INTO addons (codigo, nombre, tipo, descripcion, stripe_product_id, activo)
                             VALUES (?,?,?,?,?,?)"
                        )->execute([
                            $codigo, $nombre, $tipo,
                            $input['descripcion'] ?? null,
                            $input['stripe_product_id'] ?? null,
                            (int)($input['activo'] ?? 1),
                        ]);
                        jsonOk(['created' => $db->lastInsertId()]);
                    } else {
                        $id = (int)($input['id'] ?? 0);
                        if ($id <= 0) throw new Exception('ID inválido');
                        $allowed = ['codigo','nombre','tipo','descripcion','stripe_product_id','activo'];
                        $sets=[]; $vals=[];
                        foreach ($allowed as $f) {
                            if (array_key_exists($f, $input)) { $sets[]="`$f` = ?"; $vals[]=$input[$f]; }
                        }
                        if ($sets) {
                            $vals[]=$id;
                            $db->prepare("UPDATE addons SET ".implode(', ',$sets)." WHERE id=?")->execute($vals);
                        }
                        jsonOk(['updated'=>$id]);
                    }
                } else {
                    throw new Exception('sub requerido');
                }
            }
            break;

        // ── Addon precios (multi-divisa × mensual/anual) ────────────────────
        case 'addon_precios':
            $db = Database::getMaster();
            if ($method !== 'POST') throw new Exception('POST requerido');
            $sub = $input['sub'] ?? '';
            if ($sub === 'upsert') {
                $addonId = (int)($input['addon_id'] ?? 0);
                $moneda  = strtoupper(trim($input['moneda'] ?? ''));
                $periodo = $input['periodo'] ?? 'mensual';
                if ($addonId <= 0) throw new Exception('addon_id inválido');
                if (!preg_match('/^[A-Z]{3}$/', $moneda)) throw new Exception('Moneda ISO-4217 (3 letras) requerida');
                if (!in_array($periodo, ['mensual','anual'], true)) throw new Exception('periodo inválido');
                $precio = (float)($input['precio'] ?? 0);
                if ($precio < 0) throw new Exception('Precio negativo no permitido');
                $stripePriceId = $input['stripe_price_id'] ?? null;
                if ($stripePriceId === '') $stripePriceId = null;
                $activo = (int)!empty($input['activo']);
                $db->prepare(
                    "INSERT INTO addon_precios (addon_id, moneda, periodo, precio, stripe_price_id, activo)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        precio = VALUES(precio),
                        stripe_price_id = VALUES(stripe_price_id),
                        activo = VALUES(activo)"
                )->execute([$addonId, $moneda, $periodo, $precio, $stripePriceId, $activo]);
                jsonOk(['upserted'=>true]);
            } elseif ($sub === 'delete') {
                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) throw new Exception('ID inválido');
                $db->prepare("DELETE FROM addon_precios WHERE id = ?")->execute([$id]);
                jsonOk(['deleted'=>$id]);
            } else {
                throw new Exception('sub requerido (upsert|delete)');
            }
            break;

        // ── Seed Billing (idempotente) ─────────────────────────────────────
        // Ejecuta el script `db/seed_billing.php` en proceso. Se usa output
        // buffering para capturar el log del seed y devolverlo al cliente.
        case 'seed_billing':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $seedPath = dirname(__DIR__) . '/db/seed_billing.php';
            if (!is_file($seedPath)) throw new Exception('seed_billing.php no encontrado');
            // Cerrar cualquier buffer activo y abrir uno limpio para capturar.
            while (ob_get_level() > 0) { ob_end_clean(); }
            ob_start();
            try {
                if (!defined('GERIAPP_SEED_INLINE')) define('GERIAPP_SEED_INLINE', true);
                include $seedPath;
                $seedLog = ob_get_clean();
            } catch (\Throwable $e) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                throw new Exception('Seed falló: ' . $e->getMessage());
            }
            // Reabrir buffer para que jsonOk envíe limpio.
            ob_start();
            jsonOk(['log' => $seedLog]);
            break;

        // ── Stripe: estado de la integración ───────────────────────────────
        // Devuelve modo (test/live), si las claves están configuradas, y
        // estadísticas rápidas de cuántos planes/precios ya tienen IDs Stripe.
        case 'stripe_status':
            $db = Database::getMaster();
            $cfgOk   = false;
            $mode    = null;
            $hasWh   = false;
            $cfgErr  = null;
            try {
                require_once dirname(__DIR__) . '/db/models/StripeClient.php';
                $cfg = StripeClient::loadConfig();
                $mode = $cfg['mode'] ?? 'test';
                $cfgOk = !empty($cfg[$mode]['secret_key']) && !str_contains($cfg[$mode]['secret_key'], 'REPLACE_ME');
                $hasWh = !empty($cfg[$mode]['webhook_secret']) && !str_contains($cfg[$mode]['webhook_secret'], 'REPLACE_ME');
            } catch (\Throwable $e) { $cfgErr = $e->getMessage(); }

            $stats = $db->query(
                "SELECT
                    (SELECT COUNT(*) FROM planes WHERE activo=1 AND requiere_cotizacion=0)   AS planes_total,
                    (SELECT COUNT(*) FROM planes WHERE activo=1 AND requiere_cotizacion=0 AND stripe_product_id IS NOT NULL) AS planes_synced,
                    (SELECT COUNT(*) FROM plan_precios pp JOIN planes p ON p.id=pp.plan_id WHERE pp.activo=1 AND p.activo=1 AND p.requiere_cotizacion=0) AS precios_total,
                    (SELECT COUNT(*) FROM plan_precios pp JOIN planes p ON p.id=pp.plan_id WHERE pp.activo=1 AND p.activo=1 AND p.requiere_cotizacion=0 AND pp.stripe_price_id IS NOT NULL) AS precios_synced,
                    (SELECT COUNT(*) FROM addons WHERE activo=1) AS addons_total,
                    (SELECT COUNT(*) FROM addons WHERE activo=1 AND stripe_product_id IS NOT NULL) AS addons_synced,
                    (SELECT COUNT(*) FROM addon_precios ap JOIN addons a ON a.id=ap.addon_id WHERE ap.activo=1 AND a.activo=1) AS addon_precios_total,
                    (SELECT COUNT(*) FROM addon_precios ap JOIN addons a ON a.id=ap.addon_id WHERE ap.activo=1 AND a.activo=1 AND ap.stripe_price_id IS NOT NULL) AS addon_precios_synced
                "
            )->fetch(PDO::FETCH_ASSOC);
            $stats['plan_addon_precios_total'] = 0;
            $stats['plan_addon_precios_synced'] = 0;
            try {
                $hasPlanAddonPrices = $db->query("SHOW TABLES LIKE 'plan_addon_precios'")->rowCount() > 0;
                if ($hasPlanAddonPrices) {
                    $extraStats = $db->query(
                        "SELECT
                            (SELECT COUNT(*) FROM plan_addon_precios pap JOIN planes p ON p.id=pap.plan_id JOIN addons a ON a.id=pap.addon_id WHERE pap.activo=1 AND p.activo=1 AND p.requiere_cotizacion=0 AND a.activo=1) AS plan_addon_precios_total,
                            (SELECT COUNT(*) FROM plan_addon_precios pap JOIN planes p ON p.id=pap.plan_id JOIN addons a ON a.id=pap.addon_id WHERE pap.activo=1 AND p.activo=1 AND p.requiere_cotizacion=0 AND a.activo=1 AND pap.stripe_price_id IS NOT NULL) AS plan_addon_precios_synced"
                    )->fetch(PDO::FETCH_ASSOC);
                    $stats['plan_addon_precios_total'] = (int)($extraStats['plan_addon_precios_total'] ?? 0);
                    $stats['plan_addon_precios_synced'] = (int)($extraStats['plan_addon_precios_synced'] ?? 0);
                }
            } catch (\Throwable $e) { /* tabla pendiente de Auditoría BD */ }

            jsonOk([
                'configured'      => $cfgOk,
                'webhook_ready'   => $hasWh,
                'mode'            => $mode,
                'config_error'    => $cfgErr,
                'stats'           => $stats,
            ]);
            break;

        // ── Stripe: sincronizar planes/addons → Products + Prices ──────────
        case 'sync_stripe':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $syncPath = dirname(__DIR__) . '/db/sync_stripe.php';
            if (!is_file($syncPath)) throw new Exception('sync_stripe.php no encontrado');
            while (ob_get_level() > 0) { ob_end_clean(); }
            ob_start();
            try {
                if (!defined('GERIAPP_SYNC_STRIPE_INLINE')) define('GERIAPP_SYNC_STRIPE_INLINE', true);
                include $syncPath;
                $syncLog = ob_get_clean();
            } catch (\Throwable $e) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                throw new Exception('Sync Stripe falló: ' . $e->getMessage());
            }
            ob_start();
            jsonOk(['log' => $syncLog]);
            break;

        // ── Usuarios globales ──────────────────────────────────────────────
        case 'usuarios':
            $db = Database::getMaster();
            if ($method === 'GET') {
                $rows = $db->query(
                    "SELECT u.id, u.nombre, u.email, u.rol, u.estado, u.institucion_id,
                            i.nombre AS institucion_nombre, u.creado_at
                     FROM usuarios u
                     LEFT JOIN instituciones i ON i.id = u.institucion_id
                     ORDER BY u.id DESC
                     LIMIT 500"
                )->fetchAll();
                jsonOk(['usuarios' => $rows]);
            } elseif ($method === 'POST') {
                $sub = $input['sub'] ?? 'update';
                $id = (int)($input['id'] ?? 0);
                if ($sub === 'update' && $id > 0) {
                    $allowed = ['nombre', 'email', 'rol', 'estado'];
                    $sets = []; $vals = [];
                    foreach ($allowed as $f) {
                        if (array_key_exists($f, $input)) {
                            $sets[] = "`$f` = ?";
                            $vals[] = $f === 'rol' ? sa_role_storage($input[$f]) : $input[$f];
                        }
                    }
                    if ($sets) {
                        $vals[] = $id;
                        $db->prepare("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
                    }
                    jsonOk(['updated' => $id]);
                } elseif ($sub === 'delete' && $id > 0) {
                    $stmt = $db->prepare("SELECT id, nombre, email, rol, institucion_id FROM usuarios WHERE id = ? LIMIT 1");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user) throw new Exception('Usuario no encontrado');

                    if (($user['rol'] ?? '') === 'superadmin') {
                        $count = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'superadmin' AND id <> ?");
                        $count->execute([$id]);
                        if ((int)$count->fetchColumn() <= 0) {
                            throw new Exception('No se puede eliminar el último usuario superadmin');
                        }
                    }

                    $linkedInstIds = [];
                    if (saTableExists($db, 'usuario_instituciones')) {
                        $instStmt = $db->prepare("SELECT institucion_id FROM usuario_instituciones WHERE usuario_id = ?");
                        $instStmt->execute([$id]);
                        foreach ($instStmt->fetchAll(PDO::FETCH_COLUMN) as $instId) {
                            $linkedInstIds[(int)$instId] = true;
                        }
                    }
                    if (!empty($user['institucion_id'])) $linkedInstIds[(int)$user['institucion_id']] = true;

                    $cleanup = [];
                    $db->beginTransaction();
                    try {
                        $cleanup['usuario_instituciones'] = saDeleteWhere($db, 'usuario_instituciones', 'usuario_id = ?', [$id]);
                        $cleanup['push_tokens'] = saDeleteWhere($db, 'push_tokens', 'usuario_id = ?', [$id]);
                        $cleanup['sesiones_activas'] = saDeleteWhere($db, 'sesiones_activas', 'usuario_id = ?', [$id]);
                        $cleanup['firmas_documentos'] = saDeleteWhere($db, 'firmas_documentos', 'usuario_id = ?', [$id]);
                        $cleanup['arco_solicitudes'] = saDeleteWhere($db, 'arco_solicitudes', 'usuario_id = ?', [$id]);

                        if (saTableExists($db, 'arco_solicitudes') && saColumnExists($db, 'arco_solicitudes', 'respondido_por')) {
                            $cleanup['arco_solicitudes_respondido_por'] = saUpdateWhere($db, 'arco_solicitudes', 'respondido_por = NULL', 'respondido_por = ?', [$id]);
                        }
                        if (saTableExists($db, 'documentos_legales') && saColumnExists($db, 'documentos_legales', 'creado_por')) {
                            $cleanup['documentos_legales_creado_por'] = saUpdateWhere($db, 'documentos_legales', 'creado_por = NULL', 'creado_por = ?', [$id]);
                        }

                        if (saTableExists($db, 'logs_sistema') && saColumnExists($db, 'logs_sistema', 'usuario_id')) {
                            $cleanup['logs_sistema'] = saUpdateWhere($db, 'logs_sistema', 'usuario_id = NULL', 'usuario_id = ?', [$id]);
                        }
                        if (saTableExists($db, 'invitaciones') && saColumnExists($db, 'invitaciones', 'creado_por')) {
                            $cleanup['invitaciones'] = saUpdateWhere($db, 'invitaciones', 'creado_por = NULL', 'creado_por = ?', [$id]);
                        }
                        if (saTableExists($db, 'invitaciones_geriapp') && saColumnExists($db, 'invitaciones_geriapp', 'creado_por')) {
                            $cleanup['invitaciones_geriapp'] = saUpdateWhere($db, 'invitaciones_geriapp', 'creado_por = NULL', 'creado_por = ?', [$id]);
                        }
                        if (saTableExists($db, 'cotizaciones') && saColumnExists($db, 'cotizaciones', 'usuario_id')) {
                            $cleanup['cotizaciones'] = saUpdateWhere($db, 'cotizaciones', 'usuario_id = NULL', 'usuario_id = ?', [$id]);
                        }
                        if (saTableExists($db, 'residentes')) {
                            if (saColumnExists($db, 'residentes', 'medico_id')) {
                                $cleanup['residentes_medico'] = saUpdateWhere($db, 'residentes', 'medico_id = NULL', 'medico_id = ?', [$id]);
                            }
                            if (saColumnExists($db, 'residentes', 'familiar_id')) {
                                $cleanup['residentes_familiar'] = saUpdateWhere($db, 'residentes', 'familiar_id = NULL', 'familiar_id = ?', [$id]);
                            }
                        }

                        foreach (saClearForeignKeyReferences($db, 'usuarios', 'id', $id) as $key => $value) {
                            $cleanup[$key] = $value;
                        }

                        $del = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                        $del->execute([$id]);
                        if ($del->rowCount() !== 1) throw new Exception('No se pudo eliminar el usuario');
                        $db->commit();
                    } catch (Throwable $deleteError) {
                        if ($db->inTransaction()) $db->rollBack();
                        throw $deleteError;
                    }

                    foreach (array_keys($linkedInstIds) as $instId) {
                        try {
                            $tenant = Database::getTenant((int)$instId);
                            $cleanup['tenant_' . $instId . '_usuario_residentes'] = saDeleteWhere($tenant, 'usuario_residentes', 'usuario_id = ?', [$id]);
                            $cleanup['tenant_' . $instId . '_notificaciones_log'] = saDeleteWhere($tenant, 'notificaciones_sistema_log', 'usuario_id = ?', [$id]);
                        } catch (Throwable $tenantError) {
                            $cleanup['tenant_' . $instId . '_skip'] = $tenantError->getMessage();
                        }
                    }

                    jsonOk([
                        'deleted' => $id,
                        'nombre' => $user['nombre'],
                        'email' => $user['email'],
                        'cleanup' => $cleanup,
                    ]);
                } elseif ($sub === 'create') {
                    $nombre = trim($input['nombre'] ?? '');
                    $email  = trim($input['email'] ?? '');
                    $rol    = sa_role_storage($input['rol'] ?? 'cuidador');
                    $instId = (int)($input['institucion_id'] ?? 0);
                    $pass   = $input['password'] ?? '';
                    if (!$email) throw new Exception('Email es requerido');

                    // Check if email already exists
                    $chk = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
                    $chk->execute([$email]);
                    $existing = $chk->fetch();

                    if ($existing) {
                        // User exists — link to institution if specified
                        if ($instId <= 0) throw new Exception('El usuario ya existe. Selecciona una institución para vincularlo.');
                        // Check if already linked
                        $lnk = $db->prepare("SELECT 1 FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?");
                        $lnk->execute([$existing['id'], $instId]);
                        if ($lnk->fetch()) throw new Exception('El usuario ya está vinculado a esa institución');
                        $db->prepare("INSERT INTO usuario_instituciones (usuario_id, institucion_id, rol) VALUES (?, ?, ?)")
                           ->execute([$existing['id'], $instId, $rol]);
                        jsonOk(['linked' => $existing['id'], 'nombre' => $existing['nombre'],
                                'message' => 'Usuario existente vinculado a la institución']);
                    } else {
                        // New user
                        if (!$nombre) throw new Exception('Nombre es requerido para nuevo usuario');
                        if (!$pass || strlen($pass) < 6) throw new Exception('Contraseña requerida (mín. 6 caracteres)');
                        $hash = password_hash($pass, PASSWORD_DEFAULT);
                        $db->prepare(
                            "INSERT INTO usuarios (nombre, email, password_hash, rol, estado, institucion_id) VALUES (?, ?, ?, ?, 'activo', ?)"
                        )->execute([$nombre, $email, $hash, $rol, $instId ?: null]);
                        $newId = $db->lastInsertId();
                        if ($instId > 0) {
                            $db->prepare("INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol) VALUES (?, ?, ?)")
                               ->execute([$newId, $instId, $rol]);
                        }
                        jsonOk(['created' => $newId]);
                    }
                } elseif ($sub === 'inst_users') {
                    // Get users linked to a specific institution
                    $instId = (int)($input['institucion_id'] ?? 0);
                    if ($instId <= 0) throw new Exception('institucion_id requerido');
                    $stmt = $db->prepare(
                        "SELECT u.id, u.nombre, u.email, u.rol AS global_rol, u.estado, u.creado_at,
                                ui.rol AS inst_rol
                         FROM usuario_instituciones ui
                         JOIN usuarios u ON u.id = ui.usuario_id
                         WHERE ui.institucion_id = ?
                         ORDER BY u.nombre"
                    );
                    $stmt->execute([$instId]);
                    jsonOk(['usuarios' => $stmt->fetchAll()]);
                } elseif ($sub === 'update_inst_role') {
                    $userId = (int)($input['user_id'] ?? 0);
                    $instId = (int)($input['institucion_id'] ?? 0);
                    $rol    = sa_role_storage($input['rol'] ?? '');
                    if ($userId <= 0 || $instId <= 0) throw new Exception('user_id e institucion_id requeridos');
                    if (!in_array($rol, ['admin','enfermero','medico','familiar'])) throw new Exception('Rol inválido');
                    $db->prepare("UPDATE usuario_instituciones SET rol = ? WHERE usuario_id = ? AND institucion_id = ?")
                       ->execute([$rol, $userId, $instId]);
                    jsonOk(['updated' => true]);
                } elseif ($sub === 'user_institutions') {
                    // Get all institutions for a user (with linked status)
                    $userId = (int)($input['user_id'] ?? $id);
                    if ($userId <= 0) throw new Exception('user_id requerido');
                    $stmt = $db->prepare(
                        "SELECT i.id, i.nombre, i.estado,
                                ui.rol AS linked_rol,
                                CASE WHEN ui.usuario_id IS NOT NULL THEN 1 ELSE 0 END AS linked
                         FROM instituciones i
                         LEFT JOIN usuario_instituciones ui ON ui.institucion_id = i.id AND ui.usuario_id = ?
                         ORDER BY i.nombre"
                    );
                    $stmt->execute([$userId]);
                    jsonOk(['instituciones' => $stmt->fetchAll()]);
                } elseif ($sub === 'toggle_institution') {
                    $userId = (int)($input['user_id'] ?? 0);
                    $instId = (int)($input['institucion_id'] ?? 0);
                    $link   = !empty($input['link']); // true to link, false to unlink
                    $rol    = sa_role_storage($input['rol'] ?? 'cuidador');
                    if ($userId <= 0 || $instId <= 0) throw new Exception('user_id e institucion_id requeridos');
                    if ($link) {
                        $db->prepare("INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol) VALUES (?, ?, ?)")
                           ->execute([$userId, $instId, $rol]);
                        // Also update usuarios.institucion_id if null
                        $db->prepare("UPDATE usuarios SET institucion_id = COALESCE(institucion_id, ?) WHERE id = ?")->execute([$instId, $userId]);
                    } else {
                        $db->prepare("DELETE FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?")->execute([$userId, $instId]);
                    }
                    jsonOk(['toggled' => true]);
                } else {
                    throw new Exception('Acción no soportada');
                }
            }
            break;

        // ── Logs del sistema ───────────────────────────────────────────────
        case 'logs':
            $db = Database::getMaster();
            if ($method === 'GET') {
                $limit  = min((int)($_GET['limit']  ?? 100), 500);
                $offset = max((int)($_GET['offset'] ?? 0), 0);
                $nivel  = $_GET['nivel'] ?? '';

                $where = '';
                $params = [];
                if ($nivel && in_array($nivel, ['info', 'warning', 'error', 'debug'], true)) {
                    $where = 'WHERE ls.nivel = ?';
                    $params[] = $nivel;
                }

                $stmt = $db->prepare(
                    "SELECT ls.*, u.nombre AS usuario_nombre
                     FROM logs_sistema ls
                     LEFT JOIN usuarios u ON u.id = ls.usuario_id
                     $where
                     ORDER BY ls.id DESC
                     LIMIT $limit OFFSET $offset"
                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                $countStmt = $db->prepare("SELECT COUNT(*) FROM logs_sistema ls $where");
                $countStmt->execute($params);
                $total = (int)$countStmt->fetchColumn();

                jsonOk(['logs' => $rows, 'total' => $total]);
            }
            break;

        // ── Stats for dashboard ────────────────────────────────────────────
        case 'stats':
            $db = Database::getMaster();
            $inst   = (int)$db->query("SELECT COUNT(*) FROM instituciones")->fetchColumn();
            $users  = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $active = (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE estado='activa'")->fetchColumn();
            $plans  = (int)$db->query("SELECT COUNT(*) FROM planes WHERE activo=1")->fetchColumn();
            jsonOk(['instituciones' => $inst, 'usuarios' => $users, 'activas' => $active, 'planes' => $plans]);
            break;

        // ── Resource monitor ──────────────────────────────────────────────
        case 'resources':
            $db = Database::getMaster();
            $pingStart = microtime(true);
            $db->query('SELECT 1')->fetchColumn();
            $dbPingMs = (int)round((microtime(true) - $pingStart) * 1000);

            $dbLimit = saReadDbHourlyLimit($db);
            $connMetrics = Database::getConnectionMetrics();
            $grantLimit = $dbLimit['max_connections_per_hour'];
            $limitValue = $grantLimit === null ? (int)(getenv('HOSTINGER_DB_CONNECTIONS_PER_HOUR') ?: 500) : (int)$grantLimit;
            $used = (int)$connMetrics['total'];
            $dbLimit['display_limit'] = $limitValue;
            $dbLimit['used'] = $used;
            $dbLimit['remaining'] = $limitValue > 0 ? max(0, $limitValue - $used) : null;
            $dbLimit['percent'] = $limitValue > 0 ? min(999, round(($used / $limitValue) * 100, 1)) : null;
            $dbSizes = saReadDbSizes($db);
            $storage = saReadStorageMetrics();
            $generatedAt = date('c');
            saRecordResourceSnapshot($generatedAt, $connMetrics, $dbSizes, $storage);

            jsonOk([
                'generated_at' => $generatedAt,
                'hostinger_note' => [
                    'title' => 'Hostinger reporta el límite como max_connections_per_hour',
                    'body' => 'El valor 500 corresponde a conexiones MySQL nuevas por hora para el usuario de base de datos. No es una suma exacta de SELECT/INSERT; una misma conexión puede ejecutar varias consultas. GeriApp cuenta las conexiones PDO nuevas observadas desde la app y las compara contra ese umbral.',
                    'source' => 'Hostinger Support: How to Fix the Max_Connections_Per_Hour MySQL Error at Hostinger',
                ],
                'db' => [
                    'host' => DB_HOST,
                    'user' => DB_USER,
                    'name' => DB_MASTER_NAME,
                    'ping_ms' => $dbPingMs,
                    'limit' => $dbLimit,
                    'connections' => $connMetrics,
                    'processes' => saReadDbProcesses($db),
                    'sizes' => $dbSizes,
                ],
                'storage' => $storage,
                'history' => saReadResourceHistory(24),
                'connection_history' => Database::getConnectionMetricHistory(24),
                'php' => [
                    'version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                    'memory_limit' => ini_get('memory_limit'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
            ]);
            break;

        // ── Server info (footer) ───────────────────────────────────────────
        case 'server_info':
            $db = Database::getMaster();
            $ver = $db->query("SELECT VERSION()")->fetchColumn();
            jsonOk([
                'db_host'    => DB_HOST,
                'db_name'    => DB_MASTER_NAME,
                'db_version' => $ver,
                'php_version'=> PHP_VERSION,
                'server'     => PHP_OS_FAMILY,
                'timezone'   => date_default_timezone_get(),
                'uptime'     => @file_get_contents('/proc/uptime') ?: null,
            ]);
            break;

        // ── Perfiles de conexión CRUD ──────────────────────────────────────
        case 'profiles':
            $jsonFile = dirname(__DIR__) . '/secretos/sync_profiles.json';
            $profiles = require dirname(__DIR__) . '/db/sync_profiles.php'; // ensures JSON created
            if ($method === 'GET') {
                // Return profiles WITHOUT passwords
                $safe = [];
                foreach ($profiles as $key => $p) {
                    $safe[$key] = [
                        'label'   => $p['label'],
                        'host'    => $p['host'],
                        'port'    => $p['port'] ?? 3306,
                        'user'    => $p['user'],
                        'pass'    => $p['pass'] ? '••••••' : '',
                        'db'      => $p['db'],
                        'prefix'  => $p['prefix'] ?? 'geriapp_i',
                        'charset' => $p['charset'] ?? 'utf8mb4',
                    ];
                }
                jsonOk(['profiles' => $safe]);
            } elseif ($method === 'POST') {
                $sub = $input['sub'] ?? '';
                $key = $input['key'] ?? '';
                if (!preg_match('/^[a-z0-9_]{2,30}$/', $key)) throw new Exception('Key inválida (a-z0-9_ 2-30 chars)');

                if ($sub === 'save') {
                    $profiles[$key] = [
                        'label'   => $input['label'] ?? $key,
                        'host'    => $input['host'] ?? '127.0.0.1',
                        'port'    => (int)($input['port'] ?? 3306),
                        'user'    => $input['user'] ?? 'root',
                        'pass'    => ($input['pass'] === '••••••' && isset($profiles[$key]))
                                     ? $profiles[$key]['pass']
                                     : ($input['pass'] ?? ''),
                        'db'      => $input['db'] ?? '',
                        'prefix'  => $input['prefix'] ?? 'geriapp_i',
                        'charset' => $input['charset'] ?? 'utf8mb4',
                    ];
                    file_put_contents($jsonFile, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    jsonOk(['saved' => $key]);
                } elseif ($sub === 'delete') {
                    if (!isset($profiles[$key])) throw new Exception("Perfil '$key' no existe");
                    unset($profiles[$key]);
                    file_put_contents($jsonFile, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    jsonOk(['deleted' => $key]);
                } else {
                    throw new Exception('sub requerido: save|delete');
                }
            }
            break;

        // ── Run migration ──────────────────────────────────────────────────
        case 'run_migration':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $file = $input['file'] ?? '';
            // Only allow files in db/ folder starting with migrate_
            if (!preg_match('/^migrate_[a-zA-Z0-9_]+\.php$/', $file)) {
                throw new Exception('Nombre de archivo de migración inválido');
            }
            $fullPath = dirname(__DIR__) . '/db/' . $file;
            if (!file_exists($fullPath)) throw new Exception("Archivo '$file' no encontrado");

            ob_start();
            try {
                require $fullPath;
            } catch (Throwable $e) {
                ob_end_clean();
                throw new Exception("Error en migración: " . $e->getMessage());
            }
            $output = ob_get_clean();
            jsonOk(['output' => $output]);
            break;

        // ── Run FULL migration (master + all tenants) ──────────────────────
        case 'run_full_migration':
            if ($method !== 'POST') throw new Exception('POST requerido');

            require_once dirname(__DIR__) . '/conf/config.db.php';
            require_once dirname(__DIR__) . '/db/Database.php';
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            ob_start();

            $db = Database::getMaster();
            $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

            $expected = require dirname(__DIR__) . '/db/expected_schema.php';

            // ── Helper: build column definition SQL (shared with check_db) ──
            $buildColDef = function(array $cd): string {
                $type = $cd['type'];
                if (preg_match('/^(enum|set)\s*\(/i', $type)) {
                    $type = preg_replace_callback('/^(enum|set)/i', fn($m) => strtoupper($m[1]), $type);
                } else {
                    $type = strtoupper($type);
                }
                $line = $type;
                if (!$cd['nullable']) $line .= ' NOT NULL';
                else $line .= ' DEFAULT NULL';
                if (isset($cd['default']) && !$cd['nullable']) {
                    $dv = $cd['default'];
                    $bare = in_array(strtoupper($dv), ['CURRENT_TIMESTAMP','NULL','TRUE','FALSE'], true);
                    $line .= ' DEFAULT ' . ($bare ? strtoupper($dv) : "'" . str_replace("'", "\\'", $dv) . "'");
                }
                if (!empty($cd['on_update'])) $line .= ' ON UPDATE ' . strtoupper($cd['on_update']);
                if (isset($cd['extra']) && $cd['extra'] === 'auto_increment') $line .= ' AUTO_INCREMENT';
                return $line;
            };

            // ── Helper: build CREATE TABLE SQL from schema spec ──
            $buildCreate = function(string $table, array $spec) use ($buildColDef): string {
                $lines = [];
                foreach ($spec['columns'] as $cn => $cd) {
                    $lines[] = "  `$cn` " . $buildColDef($cd);
                }
                if (!empty($spec['keys'])) {
                    foreach ($spec['keys'] as $kn => $kc) {
                        $cols = '`' . implode('`,`', explode(',', $kc)) . '`';
                        if ($kn === 'PRIMARY') $lines[] = "  PRIMARY KEY ($cols)";
                        elseif (str_starts_with($kn, 'uq_')) $lines[] = "  UNIQUE KEY `$kn` ($cols)";
                        else $lines[] = "  KEY `$kn` ($cols)";
                    }
                }
                return "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $lines)
                     . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            };

            // ── Schema-driven sync: ensure all tables, columns, indexes exist ──
            $syncSchema = function(PDO $pdo, array $expected, array $scopes) use ($buildColDef, $buildCreate) {
                $created = 0; $added = 0; $idxAdded = 0;
                foreach ($expected as $table => $spec) {
                    $ts = $spec['scope'] ?? 'master';
                    if (!in_array($ts, $scopes, true)) continue;

                    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->rowCount() > 0;
                    if (!$exists) {
                        try {
                            $pdo->exec($buildCreate($table, $spec));
                            echo "  + Tabla `$table` CREADA\n";
                            $created++;
                        } catch (PDOException $e) {
                            if (!str_contains($e->getMessage(), 'already exists')) {
                                echo "  ✗ Tabla `$table` ERROR: {$e->getMessage()}\n";
                            }
                        }
                        continue;
                    }

                    // ── Missing columns ──
                    $colRows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($spec['columns'] as $colName => $colDef) {
                        if (in_array($colName, $colRows, true)) continue;
                        try {
                            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$colName` " . $buildColDef($colDef));
                            echo "  + $table.$colName ADDED\n";
                            $added++;
                        } catch (PDOException $e) {
                            if (!str_contains($e->getMessage(), 'Duplicate column')) {
                                echo "  ✗ $table.$colName ERROR: {$e->getMessage()}\n";
                            }
                        }
                    }

                    // ── Missing indexes ──
                    if (!empty($spec['keys'])) {
                        $idxRows = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll();
                        $idxMap = [];
                        foreach ($idxRows as $ix) $idxMap[$ix['Key_name']] = true;
                        foreach ($spec['keys'] as $kn => $kc) {
                            if ($kn === 'PRIMARY' || isset($idxMap[$kn])) continue;
                            $cols = '`' . implode('`,`', explode(',', $kc)) . '`';
                            $uniq = str_starts_with($kn, 'uq_') ? 'UNIQUE ' : '';
                            try {
                                $pdo->exec("ALTER TABLE `$table` ADD {$uniq}KEY `$kn` ($cols)");
                                echo "  + Índice $kn en $table ADDED\n";
                                $idxAdded++;
                            } catch (PDOException $e) {
                                if (!str_contains($e->getMessage(), 'Duplicate')) {
                                    echo "  ✗ Índice $kn ERROR: {$e->getMessage()}\n";
                                }
                            }
                        }
                    }
                }
                return [$created, $added, $idxAdded];
            };

            // Get institutions
            $instituciones = [];
            try {
                $instituciones = $db->query("SELECT id, db_name FROM instituciones")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo "⚠️  No instituciones table yet.\n";
            }

            // ── Helper: fix JSON→TEXT for encrypted columns (drop CHECK constraints) ──
            $fixJsonCheckConstraints = function(PDO $pdo, array $expected) use ($buildColDef) {
                $fixed = 0;
                foreach (EncryptionMap::FIELDS as $table => $fields) {
                    if (!isset($expected[$table])) continue;
                    try {
                        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->rowCount() > 0;
                        if (!$exists) continue;
                        $colInfo = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                        $typeMap = [];
                        foreach ($colInfo as $ci) $typeMap[$ci['Field']] = strtolower($ci['Type']);
                        foreach ($fields as $col => $meta) {
                            if (!isset($expected[$table]['columns'][$col])) continue;
                            $realType   = $typeMap[$col] ?? null;
                            if ($realType === null) continue;
                            $expectType = strtolower($expected[$table]['columns'][$col]['type']);
                            // JSON in MariaDB = LONGTEXT + CHECK(json_valid(col))
                            if (($realType === 'longtext' || $realType === 'json') && $expectType === 'text') {
                                // Drop CHECK constraints referencing this column
                                try {
                                    $cks = $pdo->query(
                                        "SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS
                                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table)
                                    )->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($cks as $ck) {
                                        try {
                                            $pdo->exec("ALTER TABLE `$table` DROP CONSTRAINT `$ck`");
                                            echo "  – Constraint `$ck` en $table DROP\n";
                                        } catch (\Throwable $e) {}
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback: try by column name (MariaDB default naming)
                                    try { $pdo->exec("ALTER TABLE `$table` DROP CONSTRAINT `$col`"); } catch (\Throwable $e2) {}
                                }
                                // Modify column to TEXT
                                $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$col` " . $buildColDef($expected[$table]['columns'][$col]));
                                echo "  ↻ $table.$col LONGTEXT→TEXT (cifrado)\n";
                                $fixed++;
                            }
                        }
                    } catch (\Throwable $e) {
                        echo "  ✗ $table CHECK constraint fix: {$e->getMessage()}\n";
                    }
                }
                return $fixed;
            };

            // ── MASTER DB ──
            echo "── MASTER DB ──────────────────\n";
            [$mc, $ma, $mi] = $syncSchema($db, $expected, ['master', 'both']);
            $mjf = $fixJsonCheckConstraints($db, $expected);
            echo "  Resumen master: $mc tablas creadas, $ma columnas, $mi índices, $mjf JSON→TEXT\n";

            // [Special] Decrypt configuracion (removed encryption from this table)
            echo "\n[Decrypt] Descifrar campos configuracion...\n";
            if (Cipher::hasKey()) {
                $cfgFields = ['smtp_password','smtp_usuario','smtp_from_email','wa_api_key','wa_instance_id','ia_api_key'];
                try {
                    $cfgRows = $db->query("SELECT id, " . implode(',', array_map(fn($f) => "`$f`", $cfgFields)) . " FROM configuracion")->fetchAll(PDO::FETCH_ASSOC);
                    $cfgDecrypted = 0;
                    foreach ($cfgRows as $row) {
                        $updates = [];
                        $params  = [':id' => $row['id']];
                        foreach ($cfgFields as $f) {
                            if ($row[$f] === null || $row[$f] === '') continue;
                            $dec = Cipher::decrypt($row[$f]);
                            if ($dec !== $row[$f]) {
                                $updates[] = "`$f` = :$f";
                                $params[":$f"] = $dec;
                            }
                        }
                        if ($updates) {
                            $db->prepare("UPDATE configuracion SET " . implode(', ', $updates) . " WHERE id = :id")->execute($params);
                            $cfgDecrypted++;
                        }
                    }
                    echo "  → $cfgDecrypted filas descifradas\n";
                } catch (PDOException $e) {
                    echo "  · Tabla configuracion aún no existe, omitiendo\n";
                }
            } else {
                echo "  · Sin DATA_ENCRYPTION_KEY, nada que descifrar\n";
            }

            // ── TENANT DBs ──
            echo "\n── TENANT DBs ─────────────────\n";
            foreach ($instituciones as $inst) {
                $iid = (int)$inst['id'];
                echo "\n[Institución $iid] " . ($inst['db_name'] ?: '(master/shared)') . "\n";
                $tdb = Database::getTenant($iid);
                $tdb->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
                [$tc, $ta, $ti] = $syncSchema($tdb, $expected, ['tenant', 'both']);
                $tjf = $fixJsonCheckConstraints($tdb, $expected);
                if ($tc || $ta || $ti || $tjf) echo "  Resumen: $tc tablas, $ta columnas, $ti índices, $tjf JSON→TEXT\n";

                // [Special] Backfill denorm columns from datos JSON
                try {
                    $bfStmt = $tdb->prepare(
                        "SELECT id, categoria, datos FROM cuidados_registros
                         WHERE datos IS NOT NULL AND (
                             (categoria = 'eliminacion' AND subtipo IS NULL)
                          OR (categoria = 'sueno'       AND pendiente = 0 AND datos LIKE '%pendiente%')
                          OR (categoria IN ('terapia','sueno') AND duracion_min IS NULL AND datos LIKE '%duracion%')
                         )"
                    );
                    $bfStmt->execute();
                    $bfUpd = $tdb->prepare(
                        "UPDATE cuidados_registros SET subtipo=:s, pendiente=:p, duracion_min=:d WHERE id=:id"
                    );
                    $bfCount = 0;
                    while ($bfRow = $bfStmt->fetch(PDO::FETCH_ASSOC)) {
                        try {
                            $dec = EncryptionMap::decryptRow('cuidados_registros', ['datos' => $bfRow['datos']]);
                            $raw = $dec['datos'] ?? $bfRow['datos'];
                            $d = is_string($raw) ? json_decode($raw, true) : $raw;
                            if (!is_array($d)) continue;
                            $s = ($bfRow['categoria']==='eliminacion' && !empty($d['tipo_eliminacion'])) ? $d['tipo_eliminacion'] : null;
                            $p = ($bfRow['categoria']==='sueno' && !empty($d['pendiente'])) ? 1 : 0;
                            $dm = isset($d['duracion_min']) ? (int)$d['duracion_min'] : null;
                            $bfUpd->execute([':s'=>$s, ':p'=>$p, ':d'=>$dm, ':id'=>$bfRow['id']]);
                            if ($bfUpd->rowCount() > 0) $bfCount++;
                        } catch (Throwable $e) { /* skip */ }
                    }
                    if ($bfCount) echo "  + Backfill denorm: $bfCount filas\n";
                } catch (Throwable $e) {
                    // cuidados_registros may not exist yet
                }
            }

            echo "\n✅ Migración completa finalizada.\n";
            $output = ob_get_clean();
            jsonOk(['output' => $output]);
            break;

        // ── List available migrations ──────────────────────────────────────
        case 'migrations':
            $dir = dirname(__DIR__) . '/db/';
            $files = glob($dir . 'migrate_*.php');
            $list = [];
            foreach ($files as $f) {
                $name = basename($f);
                $list[] = [
                    'file'     => $name,
                    'modified' => filemtime($f),
                    'size'     => filesize($f),
                ];
            }
            jsonOk(['files' => $list]);
            break;

        // CIE-10 stats — removido: migrado a MediApp
        case 'cie10_stats':
            throw new Exception('El catálogo CIE-10 fue migrado a MediApp');
            break;

        // CIE-10 import — removido: migrado a MediApp
        case 'cie10_import':
            throw new Exception('El catálogo CIE-10 fue migrado a MediApp');
            break;

        // ── Perfil: obtener datos ──────────────────────────────────────────
        case 'profile':
            require_once __DIR__ . '/auth_config.php';
            $currentUser = $_SESSION['sa_user'] ?? '';
            if ($method === 'GET') {
                $cred = null;
                foreach (SA_CREDENTIALS as $c) {
                    if ($c['user'] === $currentUser) { $cred = $c; break; }
                }
                jsonOk([
                    'user' => $cred['user'] ?? $currentUser,
                    'name' => $cred['name'] ?? ($_SESSION['sa_name'] ?? 'Superadmin'),
                ]);
            }
            // POST — update profile
            if ($method !== 'POST') throw new Exception('Método no soportado');
            $newName = trim($input['name'] ?? '');
            $newUser = trim($input['user'] ?? '');
            $currentPass = $input['current_password'] ?? '';
            $newPass = $input['new_password'] ?? '';
            $confirmPass = $input['confirm_password'] ?? '';

            if (!$newName) throw new Exception('El nombre es requerido');
            if (!$newUser) throw new Exception('El usuario es requerido');
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $newUser)) {
                throw new Exception('Usuario debe tener 3-30 caracteres alfanuméricos');
            }

            // Read current config to find the matching credential
            $configPath = __DIR__ . '/auth_config.php';
            $found = false;
            $passHash = null;
            foreach (SA_CREDENTIALS as $c) {
                if ($c['user'] === $currentUser) {
                    $found = true;
                    $passHash = $c['pass'];
                    break;
                }
            }
            if (!$found) throw new Exception('Credencial actual no encontrada');

            // If changing password, validate
            if ($newPass !== '') {
                if (!$currentPass) throw new Exception('Debes ingresar tu contraseña actual');
                if (!password_verify($currentPass, $passHash)) {
                    throw new Exception('La contraseña actual es incorrecta');
                }
                if (strlen($newPass) < 8) throw new Exception('La nueva contraseña debe tener al menos 8 caracteres');
                if ($newPass !== $confirmPass) throw new Exception('Las contraseñas no coinciden');
                $passHash = password_hash($newPass, PASSWORD_DEFAULT);
            }

            // Rewrite auth_config.php
            $escapedUser = addslashes($newUser);
            $escapedName = addslashes($newName);
            $newConfig = "<?php\n";
            $newConfig .= "/**\n * GeriApp — Superadmin Auth Config\n *\n";
            $newConfig .= " * Credenciales dedicadas para el portal de superadmin.\n";
            $newConfig .= " * Independiente de la tabla usuarios de la BD principal.\n */\n\n";
            $newConfig .= "define('SA_CREDENTIALS', [\n";
            $newConfig .= "    [\n";
            $newConfig .= "        'user'  => '{$escapedUser}',\n";
            $newConfig .= "        'pass'  => '{$passHash}',\n";
            $newConfig .= "        'name'  => '{$escapedName}',\n";
            $newConfig .= "    ],\n";
            $newConfig .= "]);\n\n";
            $newConfig .= "// To generate a new password hash, run:\n";
            $newConfig .= "// php -r \"echo password_hash('YourNewPassword', PASSWORD_DEFAULT);\"\n";

            if (file_put_contents($configPath, $newConfig) === false) {
                throw new Exception('Error al escribir el archivo de configuración');
            }

            // Update session
            $_SESSION['sa_user'] = $newUser;
            $_SESSION['sa_name'] = $newName;

            jsonOk(['message' => 'Perfil actualizado correctamente']);
            break;

        // ── Impersonar institución ─────────────────────────────────────
        case 'impersonate':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $instId = (int)($input['inst_id'] ?? 0);
            if ($instId <= 0) throw new Exception('Institution ID requerido');

            $db = Database::getMaster();
            $stmt = $db->prepare("SELECT id, nombre FROM instituciones WHERE id = ?");
            $stmt->execute([$instId]);
            $inst = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$inst) throw new Exception('Institución no encontrada');

            $backupKeys = ['user_id','user_nombre','user_rol','user_institucion_id','user_institucion_nombre','user_estado','user_multi_inst','_inst_check_at'];
            if (!empty($_SESSION['sa_impersonating']) && isset($_SESSION['sa_imp_backup']) && is_array($_SESSION['sa_imp_backup'])) {
                $backup = $_SESSION['sa_imp_backup'];
            } else {
                $backup = [];
                foreach ($backupKeys as $key) {
                    if (isset($_SESSION[$key])) $backup[$key] = $_SESSION[$key];
                }
            }

            // Set impersonation flags
            $currentImpRole = (string)($_SESSION['sa_imp_role'] ?? $_SESSION['user_rol'] ?? 'superadmin');
            if (!in_array($currentImpRole, ['superadmin', 'admin', 'enfermero', 'medico', 'familiar'], true)) {
                $currentImpRole = 'superadmin';
            }
            $_SESSION['sa_impersonating']    = true;
            $_SESSION['sa_imp_inst_id']      = $instId;
            $_SESSION['sa_imp_inst_nombre']  = $inst['nombre'];
            $_SESSION['sa_imp_role']         = $currentImpRole;
            $_SESSION['sa_imp_backup']       = $backup;

            // Set user_* vars so cuidados.php works normally
            $_SESSION['user_id']                 = -1;
            $_SESSION['user_nombre']             = $_SESSION['sa_name'] ?? 'Superadmin';
            $_SESSION['user_rol']                = $currentImpRole;
            $_SESSION['user_institucion_id']     = $instId;
            $_SESSION['user_institucion_nombre'] = $inst['nombre'];
            $_SESSION['user_estado']             = 'activo';
            $_SESSION['user_multi_inst']         = false;
            unset($_SESSION['_inst_check_at']);

            jsonOk(['url' => BASE_URL . '/cuidados.php']);
            break;

        // ── Terminar impersonación ──────────────────────────────────────
        case 'end_impersonate':
            $backupKeys = ['user_id','user_nombre','user_rol','user_institucion_id','user_institucion_nombre','user_estado','user_multi_inst','_inst_check_at'];
            // Clear impersonation user vars
            foreach ($backupKeys as $key) {
                unset($_SESSION[$key]);
            }
            // Restore previous user session if any
            $backup = $_SESSION['sa_imp_backup'] ?? [];
            foreach ($backup as $k => $v) {
                $_SESSION[$k] = $v;
            }
            // Clean up impersonation flags
            unset($_SESSION['sa_impersonating'], $_SESSION['sa_imp_inst_id'], $_SESSION['sa_imp_inst_nombre'], $_SESSION['sa_imp_backup']);

            jsonOk(['url' => BASE_URL . '/superadmin/']);
            break;

        // ── Auditoría BD (check_db) ─────────────────────────────────────
        case 'check_db':
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';
            // Helper: build column definition SQL from schema spec
            // Handles: ENUM values (no strtoupper on values), ON UPDATE, proper quoting
            $buildColDef = function(array $cd): string {
                $type = $cd['type'];
                // Uppercase type keyword but preserve ENUM/SET values inside quotes
                if (preg_match('/^(enum|set)\s*\(/i', $type)) {
                    $type = preg_replace_callback('/^(enum|set)/i', fn($m) => strtoupper($m[1]), $type);
                } else {
                    $type = strtoupper($type);
                }
                $line = $type;
                if (!$cd['nullable']) $line .= ' NOT NULL';
                else $line .= ' DEFAULT NULL';
                if (isset($cd['default']) && !$cd['nullable']) {
                    $dv = $cd['default'];
                    $bare = in_array(strtoupper($dv), ['CURRENT_TIMESTAMP', 'NULL', 'TRUE', 'FALSE'], true);
                    $line .= ' DEFAULT ' . ($bare ? strtoupper($dv) : "'" . str_replace("'", "\\'", $dv) . "'");
                }
                if (!empty($cd['on_update'])) {
                    $line .= ' ON UPDATE ' . strtoupper($cd['on_update']);
                }
                if (isset($cd['extra']) && $cd['extra'] === 'auto_increment') $line .= ' AUTO_INCREMENT';
                return $line;
            };

            // Connect to selected profile or default local
            $profileKey = $_GET['profile'] ?? '';
            if ($profileKey) {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$profileKey])) throw new Exception("Perfil '$profileKey' no encontrado");
                $p = $profiles[$profileKey];
                $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['db']};charset={$p['charset']}";
                $db = new PDO($dsn, $p['user'], $p['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            } else {
                $db = Database::getMaster();
            }
            $expected = require dirname(__DIR__) . '/db/expected_schema.php';
            $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $results = [];
            $totalIssues = 0;
            $totalOk = 0;

            foreach ($expected as $table => $spec) {
                $issues = [];
                $ok = 0;
                $rowCount = 0;

                if (!in_array($table, $existingTables, true)) {
                    $createSql = "CREATE TABLE `$table` (\n";
                    $lines = [];
                    foreach ($spec['columns'] as $cn => $cd) {
                        $lines[] = "  `$cn` " . $buildColDef($cd);
                    }
                    if (!empty($spec['keys'])) {
                        foreach ($spec['keys'] as $kn => $kc) {
                            $cols = implode('`,`', explode(',', $kc));
                            if ($kn === 'PRIMARY') $lines[] = "  PRIMARY KEY (`$cols`)";
                            elseif (str_starts_with($kn, 'uq_')) $lines[] = "  UNIQUE KEY `$kn` (`$cols`)";
                            else $lines[] = "  KEY `$kn` (`$cols`)";
                        }
                    }
                    $createSql .= implode(",\n", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $issues[] = ['type' => 'error', 'msg' => "Tabla '$table' no existe", 'fix_sql' => $createSql];
                    $results[$table] = ['issues' => $issues, 'ok' => 0, 'rows' => 0];
                    $totalIssues++;
                    continue;
                }

                $cols = $db->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll();
                $colMap = [];
                foreach ($cols as $c) $colMap[$c['Field']] = $c;

                foreach ($spec['columns'] as $colName => $def) {
                    if (!isset($colMap[$colName])) {
                        $colDef = $buildColDef($def);
                        $issues[] = ['type' => 'error', 'msg' => "Columna '$colName' no existe", 'fix_sql' => "ALTER TABLE `$table` ADD COLUMN `$colName` $colDef"];
                        continue;
                    }
                    $real = $colMap[$colName];
                    $realType = strtolower($real['Type']);
                    $expectType = strtolower($def['type']);
                    $typeOk = ($expectType === $realType) || str_contains($realType, $expectType);
                    if (!$typeOk) {
                        $normReal = preg_replace('/\b(int|smallint|tinyint|bigint|mediumint)\(\d+\)/', '$1', $realType);
                        $normExp  = preg_replace('/\b(int|smallint|tinyint|bigint|mediumint)\(\d+\)/', '$1', $expectType);
                        $typeOk = ($normReal === $normExp);
                    }
                    // MariaDB almacena JSON como longtext — son equivalentes funcionales
                    if (!$typeOk && $expectType === 'json' && $realType === 'longtext') {
                        $typeOk = true;
                    }
                    // Columnas cifradas (consolidadas) cambian de json/varchar a text — aceptar
                    if (!$typeOk && $realType === 'text' && isset(EncryptionMap::FIELDS[$table][$colName])) {
                        $encPhase = EncryptionMap::fieldPhase($table, $colName);
                        if (in_array($encPhase, ['active', 'consolidated'], true)) {
                            $typeOk = true;
                        }
                    }
                    // Detect JSON CHECK constraint on encrypted columns (LONGTEXT + CHECK = old JSON type)
                    if ($typeOk && $expectType === 'text' && $realType === 'longtext'
                        && isset(EncryptionMap::FIELDS[$table][$colName])) {
                        try {
                            $ckCount = (int) $db->query(
                                "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($table)
                            )->fetchColumn();
                            if ($ckCount > 0) {
                                $issues[] = [
                                    'type' => 'error',
                                    'msg'  => "'$colName' tipo LONGTEXT con CHECK constraint JSON — cifrado falla al insertar. Requiere conversión a TEXT.",
                                    'fix_action' => 'fix_json_check',
                                    'fix_table'  => $table,
                                ];
                                $typeOk = false;
                            }
                        } catch (\Throwable $e) {}
                    }
                    if (!$typeOk) {
                        $modDef = $buildColDef($def);
                        $issues[] = ['type' => 'warn', 'msg' => "'$colName' tipo: esperado '$expectType', real '$realType'", 'fix_sql' => "ALTER TABLE `$table` MODIFY COLUMN `$colName` $modDef"];
                    } else {
                        $ok++;
                    }

                    // ── Comparar atributo NULL/NOT NULL ──────────────────────
                    // information_schema reporta Null como 'YES' o 'NO'.
                    $expectNullable = !empty($def['nullable']);
                    $realNullable   = isset($real['Null']) && strtoupper((string)$real['Null']) === 'YES';
                    if ($typeOk && $expectNullable !== $realNullable) {
                        $modDef = $buildColDef($def);
                        $expectLabel = $expectNullable ? 'NULL' : 'NOT NULL';
                        $realLabel   = $realNullable   ? 'NULL' : 'NOT NULL';
                        $issues[] = [
                            'type'    => 'warn',
                            'msg'     => "'$colName' nullable: esperado $expectLabel, real $realLabel",
                            'fix_sql' => "ALTER TABLE `$table` MODIFY COLUMN `$colName` $modDef",
                        ];
                    }
                }

                // Extra columns (residual — propose DROP)
                foreach ($colMap as $colName => $c) {
                    if (!isset($spec['columns'][$colName])) {
                        // Don't propose dropping _enc columns (encryption dual-write)
                        if (str_ends_with($colName, '_enc')) {
                            $issues[] = ['type' => 'info', 'msg' => "Columna cifrado dual '$colName' ({$c['Type']})"];
                        } else {
                            $issues[] = ['type' => 'warn', 'msg' => "Columna residual '$colName' ({$c['Type']})", 'fix_sql' => "ALTER TABLE `$table` DROP COLUMN `$colName`"];
                        }
                    }
                }

                // Keys
                if (!empty($spec['keys'])) {
                    $idxRows = $db->query("SHOW INDEX FROM `$table`")->fetchAll();
                    $idxMap = [];
                    foreach ($idxRows as $ix) $idxMap[$ix['Key_name']][] = $ix['Column_name'];
                    foreach ($spec['keys'] as $keyName => $keyCols) {
                        $expectedCols = explode(',', $keyCols);
                        if (!isset($idxMap[$keyName])) {
                            $found = false;
                            foreach ($idxMap as $iCols) { if ($iCols === $expectedCols) { $found = true; break; } }
                            if (!$found && $keyName !== 'PRIMARY') {
                                $idxCols = '`' . implode('`,`', $expectedCols) . '`';
                                $isUnique = str_starts_with($keyName, 'uq_');
                                $issues[] = ['type' => 'warn', 'msg' => "Índice '$keyName' ($keyCols) no encontrado", 'fix_sql' => "ALTER TABLE `$table` ADD " . ($isUnique ? 'UNIQUE ' : '') . "KEY `$keyName` ($idxCols)"];
                            }
                        } else {
                            $ok++;
                        }
                    }
                }

                $rowCount = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $totalIssues += count($issues);
                $totalOk += $ok;
                $results[$table] = ['issues' => $issues, 'ok' => $ok, 'rows' => $rowCount];
            }

            // Detect residual tables (exist in DB but not in expected_schema)
            $knownSystemTables = ['information_schema', 'mysql', 'performance_schema', 'sys'];
            foreach ($existingTables as $realTable) {
                if (isset($expected[$realTable])) continue; // already audited
                $rowCount = 0;
                try { $rowCount = (int)$db->query("SELECT COUNT(*) FROM `$realTable`")->fetchColumn(); } catch (Throwable $e) {}
                $results[$realTable] = [
                    'issues' => [['type' => 'warn', 'msg' => "Tabla residual '$realTable' (no est\u00e1 en el esquema esperado, $rowCount filas)", 'fix_sql' => "DROP TABLE `$realTable`"]],
                    'ok' => 0,
                    'rows' => $rowCount,
                    'residual' => true
                ];
                $totalIssues++;
            }

            // §5 Detect denormalized columns needing backfill (post-encryption)
            if (in_array('cuidados_registros', $existingTables, true)) {
                try {
                    $dnPending = (int) $db->query(
                        "SELECT COUNT(*) FROM cuidados_registros
                         WHERE categoria = 'eliminacion' AND subtipo IS NULL AND datos IS NOT NULL"
                    )->fetchColumn();
                    if ($dnPending > 0) {
                        if (!isset($results['cuidados_registros'])) {
                            $results['cuidados_registros'] = ['issues' => [], 'ok' => 0, 'rows' => 0];
                        }
                        $results['cuidados_registros']['issues'][] = [
                            'type'       => 'warn',
                            'msg'        => "Columna 'subtipo' sin poblar en {$dnPending} registros de eliminación (post-cifrado). Badge heces mostrará '?'. Ejecutar backfill.",
                            'fix_action' => 'backfill_denorm',
                        ];
                        $totalIssues++;
                    }
                } catch (Throwable $e) { /* subtipo column may not exist yet — ignore */ }
            }

            jsonOk(['tables' => $results, 'total_issues' => $totalIssues, 'total_ok' => $totalOk]);
            break;

        // ── Normalizar teléfonos (+52) en BD auditada ───────────────────
        case 'normalize_phones':
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $profileKey = trim((string)($input['profile'] ?? ''));
            $profileCfg = null;
            if ($profileKey !== '') {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$profileKey])) throw new Exception("Perfil '$profileKey' no encontrado");
                $profileCfg = $profiles[$profileKey];
                $dsn = "mysql:host={$profileCfg['host']};port={$profileCfg['port']};dbname={$profileCfg['db']};charset={$profileCfg['charset']}";
                $db = new PDO($dsn, $profileCfg['user'], $profileCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
            } else {
                $db = Database::getMaster();
            }
            $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

            $originalDb = (string)$db->query('SELECT DATABASE()')->fetchColumn();
            $databases = [];
            $totalScanned = 0;
            $totalUpdated = 0;

            $runOne = function(string $dbName) use ($db, &$databases, &$totalScanned, &$totalUpdated): void {
                $result = saNormalizePhonesInCurrentDb($db, $dbName);
                $databases[] = $result;
                $totalScanned += (int)$result['scanned'];
                $totalUpdated += (int)$result['updated'];
            };

            $runOne($originalDb);

            if (saTableExists($db, 'instituciones') && saColumnExists($db, 'instituciones', 'db_name')) {
                $tenantDbs = $db->query("SELECT DISTINCT db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tenantDbs as $tenantDb) {
                    $tenantDb = (string)$tenantDb;
                    if ($tenantDb === $originalDb || !preg_match('/^[A-Za-z0-9_]+$/', $tenantDb)) continue;
                    try {
                        if (!saDatabaseExists($db, $tenantDb)) continue;
                        $db->exec('USE ' . saSqlIdent($tenantDb));
                        $runOne($tenantDb);
                    } catch (Throwable $e) {
                        $databases[] = ['database' => $tenantDb, 'scanned' => 0, 'updated' => 0, 'tables' => [], 'error' => $e->getMessage()];
                    } finally {
                        $db->exec('USE ' . saSqlIdent($originalDb));
                    }
                }
            }

            jsonOk([
                'profile' => $profileKey,
                'databases' => $databases,
                'total_scanned' => $totalScanned,
                'total_updated' => $totalUpdated,
            ]);
            break;

        // ── Normalizar roles de usuarios (pivot vs tabla usuarios) ───────
        case 'normalize_user_roles':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $dryRun = !empty($body['dry_run']);
            $conflicts = [];
            $updated   = 0;
            try {
                $masterDb = Database::getMaster();
                // Find users where usuarios.rol differs from any of their pivot roles
                $rows = $masterDb->query(
                    "SELECT u.id, u.nombre, u.email, u.rol AS rol_usuario,
                            ui.institucion_id, ui.rol AS rol_pivot,
                            i.nombre AS inst_nombre
                     FROM usuarios u
                     JOIN usuario_instituciones ui ON ui.usuario_id = u.id AND ui.estado = 'activo'
                     JOIN instituciones i ON i.id = ui.institucion_id
                     WHERE u.rol != ui.rol
                       AND u.rol NOT IN ('superadmin')
                     ORDER BY u.id, ui.institucion_id"
                )->fetchAll(\PDO::FETCH_ASSOC);

                $grouped = [];
                foreach ($rows as $r) {
                    $grouped[$r['id']][] = $r;
                }

                foreach ($grouped as $uid => $entries) {
                    $pivotRoles = array_unique(array_column($entries, 'rol_pivot'));
                    $usuarioRol = $entries[0]['rol_usuario'];
                    $nombre     = $entries[0]['nombre'];
                    $email      = $entries[0]['email'];
                    foreach ($entries as $e) {
                        $conflicts[] = [
                            'usuario_id'  => (int)$uid,
                            'nombre'      => $nombre,
                            'email'       => $email,
                            'rol_usuario' => $usuarioRol,
                            'rol_pivot'   => $e['rol_pivot'],
                            'inst_id'     => (int)$e['institucion_id'],
                            'inst_nombre' => $e['inst_nombre'],
                        ];
                    }
                    if (!$dryRun && count($pivotRoles) === 1) {
                        // Pivot is source of truth: update usuarios.rol to match pivot
                        // only when all active pivot entries agree on one role.
                        $masterDb->prepare(
                            "UPDATE usuarios SET rol = ? WHERE id = ? AND rol != 'superadmin'"
                        )->execute([$pivotRoles[0], (int)$uid]);
                        $updated++;
                    }
                }
            } catch (\Throwable $e) {
                saJsonError('Error: ' . $e->getMessage());
            }
            saJson([
                'conflicts'  => $conflicts,
                'total'      => count($conflicts),
                'updated'    => $dryRun ? 0 : $updated,
                'dry_run'    => $dryRun,
            ]);
            break;

        // ── Limpieza de invitaciones sin destino ─────────────────────────
        case 'cleanup_invalid_invitations':
            if ($method !== 'POST') throw new Exception('POST requerido');

            $profileKey = trim((string)($input['profile'] ?? ''));
            $dryRun = array_key_exists('dry_run', $input) ? !empty($input['dry_run']) : true;
            $profileCfg = null;
            if ($profileKey !== '') {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$profileKey])) throw new Exception("Perfil '$profileKey' no encontrado");
                $profileCfg = $profiles[$profileKey];
                $dsn = "mysql:host={$profileCfg['host']};port={$profileCfg['port']};dbname={$profileCfg['db']};charset={$profileCfg['charset']}";
                $db = new PDO($dsn, $profileCfg['user'], $profileCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
            } else {
                $db = Database::getMaster();
            }
            $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

            $originalDb = (string)$db->query('SELECT DATABASE()')->fetchColumn();
            $databases = [];
            $totalInvalid = 0;
            $totalDeleted = 0;

            $runOne = function(string $dbName) use ($db, $dryRun, &$databases, &$totalInvalid, &$totalDeleted): void {
                $result = saCleanupInvalidInvitationsInCurrentDb($db, $dbName, !$dryRun);
                $databases[] = $result;
                $totalInvalid += (int)$result['invalid'];
                $totalDeleted += (int)($result['deleted'] ?? 0);
            };

            $runOne($originalDb);

            if (saTableExists($db, 'instituciones') && saColumnExists($db, 'instituciones', 'db_name')) {
                $tenantDbs = $db->query("SELECT DISTINCT db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tenantDbs as $tenantDb) {
                    $tenantDb = (string)$tenantDb;
                    if ($tenantDb === $originalDb || !preg_match('/^[A-Za-z0-9_]+$/', $tenantDb)) continue;
                    try {
                        if (!saDatabaseExists($db, $tenantDb)) continue;
                        $db->exec('USE ' . saSqlIdent($tenantDb));
                        $runOne($tenantDb);
                    } catch (Throwable $e) {
                        $databases[] = ['database' => $tenantDb, 'invalid' => 0, 'deleted' => 0, 'by_estado' => [], 'samples' => [], 'error' => $e->getMessage()];
                    } finally {
                        $db->exec('USE ' . saSqlIdent($originalDb));
                    }
                }
            }

            jsonOk([
                'profile' => $profileKey,
                'dry_run' => $dryRun,
                'databases' => $databases,
                'total_invalid' => $totalInvalid,
                'total_deleted' => $totalDeleted,
            ]);
            break;

        // ── Convertir contactos legacy de residentes a contactos_json ────
        case 'convert_legacy_family_contacts':
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $profileKey = trim((string)($input['profile'] ?? ''));
            $dryRun = array_key_exists('dry_run', $input) ? !empty($input['dry_run']) : true;
            if ($profileKey !== '') {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$profileKey])) throw new Exception("Perfil '$profileKey' no encontrado");
                $profileCfg = $profiles[$profileKey];
                $dsn = "mysql:host={$profileCfg['host']};port={$profileCfg['port']};dbname={$profileCfg['db']};charset={$profileCfg['charset']}";
                $db = new PDO($dsn, $profileCfg['user'], $profileCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
            } else {
                $db = Database::getMaster();
            }
            $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

            $originalDb = (string)$db->query('SELECT DATABASE()')->fetchColumn();
            $databases = [];
            $totalScanned = 0;
            $totalCandidates = 0;
            $totalUpdated = 0;

            $runOne = function(string $dbName) use ($db, $dryRun, &$databases, &$totalScanned, &$totalCandidates, &$totalUpdated): void {
                $result = saConvertLegacyFamilyContactsInCurrentDb($db, $dbName, !$dryRun);
                $databases[] = $result;
                $totalScanned += (int)$result['scanned'];
                $totalCandidates += (int)$result['candidates'];
                $totalUpdated += (int)$result['updated'];
            };

            $runOne($originalDb);

            if (saTableExists($db, 'instituciones') && saColumnExists($db, 'instituciones', 'db_name')) {
                $tenantDbs = $db->query("SELECT DISTINCT db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tenantDbs as $tenantDb) {
                    $tenantDb = (string)$tenantDb;
                    if ($tenantDb === $originalDb || !preg_match('/^[A-Za-z0-9_]+$/', $tenantDb)) continue;
                    try {
                        if (!saDatabaseExists($db, $tenantDb)) continue;
                        $db->exec('USE ' . saSqlIdent($tenantDb));
                        $runOne($tenantDb);
                    } catch (Throwable $e) {
                        $databases[] = ['database' => $tenantDb, 'scanned' => 0, 'candidates' => 0, 'updated' => 0, 'skipped_duplicates' => 0, 'samples' => [], 'error' => $e->getMessage()];
                    } finally {
                        $db->exec('USE ' . saSqlIdent($originalDb));
                    }
                }
            }

            jsonOk([
                'profile' => $profileKey,
                'dry_run' => $dryRun,
                'databases' => $databases,
                'total_scanned' => $totalScanned,
                'total_candidates' => $totalCandidates,
                'total_updated' => $totalUpdated,
            ]);
            break;

        // ── Fix DB (ejecutar DDL correctivo o action PHP) ────────────────
        case 'fix_db':
            if ($method !== 'POST') throw new Exception('POST requerido');

            $fixAction  = $input['fix_action'] ?? '';
            $sql        = trim($input['sql'] ?? '');
            $table      = $input['table'] ?? '';
            $fixProfile = $input['profile'] ?? '';

            // ── PHP-based fix actions ──────────────────────────────────────
            if ($fixAction === 'backfill_denorm') {
                require_once dirname(__DIR__) . '/includes/EncryptionMap.php';
                if ($fixProfile) {
                    $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                    if (!isset($profiles[$fixProfile])) throw new Exception("Perfil '$fixProfile' no encontrado");
                    $p = $profiles[$fixProfile];
                    $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['db']};charset={$p['charset']}";
                    $db = new PDO($dsn, $p['user'], $p['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
                } else {
                    $db = Database::getMaster();
                }
                // Backfill: decrypt datos and populate subtipo, pendiente, duracion_min
                $bfStmt = $db->prepare(
                    "SELECT id, categoria, datos FROM cuidados_registros
                     WHERE datos IS NOT NULL AND (
                         (categoria = 'eliminacion' AND subtipo IS NULL)
                      OR (categoria = 'sueno'       AND pendiente = 0 AND datos LIKE '%pendiente%')
                      OR (categoria IN ('terapia','sueno') AND duracion_min IS NULL AND datos LIKE '%duracion%')
                     )"
                );
                $bfStmt->execute();
                $bfUpd = $db->prepare(
                    "UPDATE cuidados_registros SET subtipo=:s, pendiente=:p, duracion_min=:d WHERE id=:id"
                );
                $updated = 0;
                $errors  = 0;
                while ($row = $bfStmt->fetch(PDO::FETCH_ASSOC)) {
                    try {
                        $dec = EncryptionMap::decryptRow('cuidados_registros', ['datos' => $row['datos']]);
                        $raw = $dec['datos'] ?? $row['datos'];
                        $d = is_string($raw) ? json_decode($raw, true) : $raw;
                        if (!is_array($d)) { $errors++; continue; }
                        $s  = ($row['categoria'] === 'eliminacion' && !empty($d['tipo_eliminacion'])) ? $d['tipo_eliminacion'] : null;
                        $p2 = ($row['categoria'] === 'sueno' && !empty($d['pendiente'])) ? 1 : 0;
                        $dm = isset($d['duracion_min']) ? (int)$d['duracion_min'] : null;
                        $bfUpd->execute([':s' => $s, ':p' => $p2, ':d' => $dm, ':id' => $row['id']]);
                        if ($bfUpd->rowCount() > 0) $updated++;
                    } catch (Throwable $e) { $errors++; }
                }
                jsonOk(['executed' => true, 'backfilled' => $updated, 'errors' => $errors]);
                break;
            }

            // ── Fix JSON CHECK constraints (JSON→TEXT for encrypted columns) ──
            if ($fixAction === 'fix_json_check') {
                require_once dirname(__DIR__) . '/includes/EncryptionMap.php';
                $tblName = $input['fix_table'] ?? $input['table'] ?? '';
                if (!$tblName || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tblName)) {
                    throw new Exception('Nombre de tabla inválido');
                }
                if ($fixProfile) {
                    $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                    if (!isset($profiles[$fixProfile])) throw new Exception("Perfil '$fixProfile' no encontrado");
                    $p = $profiles[$fixProfile];
                    $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['db']};charset={$p['charset']}";
                    $db = new PDO($dsn, $p['user'], $p['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
                } else {
                    $db = Database::getMaster();
                }
                $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
                $dropped = 0;
                try {
                    $cks = $db->query(
                        "SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($tblName)
                    )->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($cks as $ck) {
                        try { $db->exec("ALTER TABLE `$tblName` DROP CONSTRAINT `$ck`"); $dropped++; } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {}
                // Rebuild columns as TEXT using expected_schema
                $expected = require dirname(__DIR__) . '/db/expected_schema.php';
                $modified = 0;
                if (isset($expected[$tblName], EncryptionMap::FIELDS[$tblName])) {
                    $buildColDef2 = function(array $cd): string {
                        $type = strtoupper($cd['type']);
                        $line = $type;
                        if (!$cd['nullable']) $line .= ' NOT NULL'; else $line .= ' DEFAULT NULL';
                        if (isset($cd['default']) && !$cd['nullable']) {
                            $dv = $cd['default'];
                            $bare = in_array(strtoupper($dv), ['CURRENT_TIMESTAMP','NULL','TRUE','FALSE'], true);
                            $line .= ' DEFAULT ' . ($bare ? strtoupper($dv) : "'" . str_replace("'", "\\'", $dv) . "'");
                        }
                        if (!empty($cd['on_update'])) $line .= ' ON UPDATE ' . strtoupper($cd['on_update']);
                        return $line;
                    };
                    foreach (EncryptionMap::FIELDS[$tblName] as $col => $meta) {
                        if (!isset($expected[$tblName]['columns'][$col])) continue;
                        $colDef = $expected[$tblName]['columns'][$col];
                        if (strtolower($colDef['type']) === 'text') {
                            try {
                                $db->exec("ALTER TABLE `$tblName` MODIFY COLUMN `$col` " . $buildColDef2($colDef));
                                $modified++;
                            } catch (\Throwable $e) {}
                        }
                    }
                }
                jsonOk(['executed' => true, 'constraints_dropped' => $dropped, 'columns_modified' => $modified]);
                break;
            }

            // ── SQL DDL fix (original flow) ─────────────────────────────────
            if (!$sql) throw new Exception('SQL requerido');
            if ($table && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                throw new Exception('Nombre de tabla inválido');
            }
            $allowedPrefixes = ['ALTER TABLE', 'CREATE TABLE', 'CREATE INDEX', 'DROP TABLE', 'DROP COLUMN'];
            $sqlUpper = strtoupper(trim($sql));
            $allowed = false;
            foreach ($allowedPrefixes as $pfx) {
                if (str_starts_with($sqlUpper, $pfx)) { $allowed = true; break; }
            }
            if (!$allowed) throw new Exception('Solo se permiten ALTER TABLE, CREATE TABLE, CREATE INDEX y DROP TABLE');

            if ($fixProfile) {
                $profiles = require dirname(__DIR__) . '/db/sync_profiles.php';
                if (!isset($profiles[$fixProfile])) throw new Exception("Perfil '$fixProfile' no encontrado");
                $p = $profiles[$fixProfile];
                $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['db']};charset={$p['charset']}";
                $db = new PDO($dsn, $p['user'], $p['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            } else {
                $db = Database::getMaster();
            }
            // Ensure compatible sql_mode for TIMESTAMP defaults (production MariaDB may differ)
            $db->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
            $db->exec($sql);
            jsonOk(['executed' => true]);
            break;

        // ── Despliegue: crear BD tenant ──────────────────────────────────
        case 'despliegue':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $sub = $input['sub'] ?? '';

            if ($sub === 'create_db') {
                $instIds = $input['institucion_ids'] ?? [];
                if (empty($instIds)) throw new Exception('Selecciona al menos una institución');
                $db = Database::getMaster();
                $created = [];
                foreach ($instIds as $instId) {
                    $instId = (int)$instId;
                    if ($instId <= 0) continue;
                    $dbName = Database::tenantDbName($instId);
                    // Check if already has a DB
                    $stmt = $db->prepare("SELECT db_name, nombre FROM instituciones WHERE id = ?");
                    $stmt->execute([$instId]);
                    $inst = $stmt->fetch();
                    if (!$inst) { $created[] = ['id' => $instId, 'ok' => false, 'error' => 'Institución no encontrada']; continue; }
                    if (!empty($inst['db_name'])) { $created[] = ['id' => $instId, 'ok' => false, 'error' => "Ya tiene BD: {$inst['db_name']}"]; continue; }
                    try {
                        Database::createTenantDB($dbName);
                        $db->prepare("UPDATE instituciones SET db_name = ? WHERE id = ?")->execute([$dbName, $instId]);
                        Database::clearTenantCache($instId);
                        $created[] = ['id' => $instId, 'nombre' => $inst['nombre'], 'db_name' => $dbName, 'ok' => true];
                    } catch (Throwable $e) {
                        $created[] = ['id' => $instId, 'ok' => false, 'error' => $e->getMessage()];
                    }
                }
                jsonOk(['results' => $created]);
            } elseif ($sub === 'list_pending') {
                $db = Database::getMaster();
                $rows = $db->query("SELECT id, nombre, estado, db_name FROM instituciones WHERE db_name IS NULL OR db_name = '' ORDER BY id")->fetchAll();
                jsonOk(['instituciones' => $rows]);
            } else {
                throw new Exception('sub requerido: create_db|list_pending');
            }
            break;

        // ── Push Notifications ────────────────────────────────────────────
        case 'push_tokens':
            $db = Database::getMaster();
            $rows = $db->query("
                SELECT pt.id, pt.usuario_id, pt.token, pt.platform,
                       pt.creado_at, pt.updated_at,
                       u.nombre AS usuario_nombre, u.email AS usuario_email,
                       u.rol AS usuario_rol,
                       GROUP_CONCAT(DISTINCT i.nombre SEPARATOR ', ') AS instituciones,
                       GROUP_CONCAT(DISTINCT i.id SEPARATOR ',') AS institucion_ids
                FROM push_tokens pt
                JOIN usuarios u ON u.id = pt.usuario_id
                LEFT JOIN usuario_instituciones iu ON iu.usuario_id = u.id
                LEFT JOIN instituciones i ON i.id = iu.institucion_id
                GROUP BY pt.id
                ORDER BY pt.updated_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            jsonOk(['tokens' => $rows]);
            break;

        case 'push_stats':
            $db = Database::getMaster();
            $total = (int)$db->query("SELECT COUNT(*) FROM push_tokens")->fetchColumn();
            $byPlatform = $db->query("SELECT platform, COUNT(*) AS cnt FROM push_tokens GROUP BY platform")->fetchAll(PDO::FETCH_ASSOC);
            $byInst = $db->query("
                SELECT i.id, i.nombre, COUNT(DISTINCT pt.id) AS tokens
                FROM push_tokens pt
                JOIN usuario_instituciones iu ON iu.usuario_id = pt.usuario_id
                JOIN instituciones i ON i.id = iu.institucion_id
                GROUP BY i.id
                ORDER BY tokens DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $totalUsers = (int)$db->query("SELECT COUNT(DISTINCT usuario_id) FROM push_tokens")->fetchColumn();
            jsonOk([
                'total'       => $total,
                'total_users' => $totalUsers,
                'by_platform' => $byPlatform,
                'by_inst'     => $byInst,
            ]);
            break;

        case 'push_send':
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/api/push_send.php';
            $sub = $input['sub'] ?? '';

            if ($sub === 'to_user') {
                $userId = (int)($input['user_id'] ?? 0);
                $title  = trim($input['title'] ?? '');
                $body   = trim($input['body'] ?? '');
                if (!$userId || !$title || !$body) throw new Exception('user_id, title y body requeridos');
                $results = pushSendToUser($userId, $title, $body, $input['data'] ?? []);
                jsonOk(['results' => $results, 'sent' => count($results)]);

            } elseif ($sub === 'to_token') {
                $token = trim($input['token'] ?? '');
                $title = trim($input['title'] ?? '');
                $body  = trim($input['body'] ?? '');
                if (!$token || !$title || !$body) throw new Exception('token, title y body requeridos');
                $result = pushSendToToken($token, $title, $body, $input['data'] ?? []);
                jsonOk(['result' => $result]);

            } elseif ($sub === 'to_institution') {
                $instId = (int)($input['institucion_id'] ?? 0);
                $title  = trim($input['title'] ?? '');
                $body   = trim($input['body'] ?? '');
                if (!$instId || !$title || !$body) throw new Exception('institucion_id, title y body requeridos');
                $db = Database::getMaster();
                $tokens = $db->prepare("
                    SELECT DISTINCT pt.token
                    FROM push_tokens pt
                    JOIN usuario_instituciones iu ON iu.usuario_id = pt.usuario_id
                    WHERE iu.institucion_id = ?
                ");
                $tokens->execute([$instId]);
                $tokenList = $tokens->fetchAll(PDO::FETCH_COLUMN);
                $results = [];
                foreach ($tokenList as $t) {
                    $results[] = pushSendToToken($t, $title, $body, $input['data'] ?? []);
                }
                jsonOk(['results' => $results, 'sent' => count($results)]);

            } elseif ($sub === 'to_all') {
                $title = trim($input['title'] ?? '');
                $body  = trim($input['body'] ?? '');
                if (!$title || !$body) throw new Exception('title y body requeridos');
                $db = Database::getMaster();
                $tokenList = $db->query("SELECT token FROM push_tokens")->fetchAll(PDO::FETCH_COLUMN);
                $results = [];
                foreach ($tokenList as $t) {
                    $results[] = pushSendToToken($t, $title, $body, $input['data'] ?? []);
                }
                jsonOk(['results' => $results, 'sent' => count($results)]);

            } else {
                throw new Exception('sub requerido: to_user|to_token|to_institution|to_all');
            }
            break;

        case 'push_delete_token':
            if ($method !== 'POST') throw new Exception('POST requerido');
            $tokenId = (int)($input['token_id'] ?? 0);
            if (!$tokenId) throw new Exception('token_id requerido');
            $db = Database::getMaster();
            $stmt = $db->prepare("DELETE FROM push_tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
            jsonOk(['deleted' => $stmt->rowCount()]);
            break;

        // ══════════════════════════════════════════════════════════════════
        // §5 CIFRADO — Encryption migration endpoints
        // ══════════════════════════════════════════════════════════════════

        case 'encryption_status':
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $keyOk     = Cipher::hasKey();
            $opensslOk = Cipher::isAvailable();
            $state     = EncryptionMap::getState();

            // Build field status by inspecting actual DB columns
            $db     = Database::getMaster();
            $fields = [];
            foreach (EncryptionMap::FIELDS as $table => $cols) {
                // Check which _enc columns already exist
                $existing = [];
                try {
                    $colStmt = $db->prepare(
                        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                    );
                    $colStmt->execute([$table]);
                    $existing = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Throwable $e) { /* table might not exist yet */ }

                // Row count for progress reference
                $rowCount = 0;
                try {
                    $rc = $db->query("SELECT COUNT(*) FROM `$table`");
                    $rowCount = (int)$rc->fetchColumn();
                } catch (Throwable $e) {}

                foreach ($cols as $col => $meta) {
                    $key      = "$table.$col";
                    $encCol   = $col . '_enc';
                    $hasEnc   = in_array($encCol, $existing, true);
                    $phase    = $state['fields'][$key]['phase'] ?? 'none';
                    $migrated = (int)($state['fields'][$key]['migrated'] ?? 0);

                    // NO auto-correct: surface the real DB state so superadmin sees drift.
                    // Drift = JSON state inconsistent with actual DB columns.
                    //   - phase=consolidated but _enc still exists → DDL DROP/RENAME never ran (or only on master)
                    //   - phase=none/prepared/migrated/active but no _enc column → JSON ahead of DB
                    $drift = false;
                    if ($phase === 'consolidated' && $hasEnc) {
                        $drift = 'enc_still_present'; // _enc no fue eliminada/renombrada
                    } elseif (in_array($phase, ['prepared','migrated','active'], true) && !$hasEnc) {
                        $drift = 'enc_missing';      // JSON dice que existe, pero la columna no está
                    }

                    $fields[$key] = [
                        'table'     => $table,
                        'column'    => $col,
                        'label'     => $meta['label'],
                        'cat'       => $meta['cat'],
                        'phase'     => $phase,
                        'has_enc'   => $hasEnc,
                        'rows'      => $rowCount,
                        'migrated'  => $migrated,
                        'drift'     => $drift,
                    ];
                }
            }

            jsonOk([
                'key_ok'         => $keyOk,
                'openssl_ok'     => $opensslOk,
                'read_encrypted' => EncryptionMap::isReadEncrypted(),
                'state'          => $state,
                'fields'         => $fields,
            ]);
            break;

        case 'encryption_prepare':
            // Phase 1: Add _enc columns (ALTER TABLE)
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            if (!Cipher::hasKey())     throw new Exception('DATA_ENCRYPTION_KEY no configurada en conf/.env');
            if (!Cipher::isAvailable()) throw new Exception('OpenSSL no soporta AES-256-GCM');

            $selected = $input['fields'] ?? [];
            if (empty($selected)) throw new Exception('Selecciona al menos un campo');

            $results = [];
            $allDbs  = _enc_getAllDbs();

            foreach ($selected as $fieldKey) {
                [$table, $col] = explode('.', $fieldKey, 2);
                if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => 'Campo no reconocido'];
                    continue;
                }

                $encCol = $col . '_enc';

                foreach ($allDbs as $dbConn) {
                    try {
                        // Skip if table doesn't exist in this database
                        $tblCheck = $dbConn->prepare(
                            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                        );
                        $tblCheck->execute([$table]);
                        if ((int)$tblCheck->fetchColumn() === 0) continue;

                        // Check if column already exists
                        $check = $dbConn->prepare(
                            "SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
                        );
                        $check->execute([$table, $encCol]);
                        if ((int)$check->fetchColumn() > 0) continue; // already exists

                        // All _enc columns are TEXT (encrypted base64 can be long)
                        $dbConn->exec("ALTER TABLE `$table` ADD COLUMN `$encCol` TEXT NULL AFTER `$col`");
                    } catch (Throwable $e) {
                        $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => $e->getMessage()];
                        continue 2;
                    }
                }

                EncryptionMap::setFieldPhase($table, $col, 'prepared');
                $results[] = ['field' => $fieldKey, 'ok' => true];
            }

            jsonOk(['results' => $results]);
            break;

        case 'encryption_migrate':
            // Phase 2: Encrypt existing data into _enc columns (batch)
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            if (!Cipher::hasKey()) throw new Exception('DATA_ENCRYPTION_KEY no configurada');

            $fieldKey  = $input['field']     ?? '';
            $batchSize = (int)($input['batch_size'] ?? 500);
            if ($batchSize < 1) $batchSize = 500;
            if ($batchSize > 2000) $batchSize = 2000;

            if (!$fieldKey) throw new Exception('field requerido (tabla.columna)');

            [$table, $col] = explode('.', $fieldKey, 2);
            if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                throw new Exception("Campo '$fieldKey' no reconocido en EncryptionMap");
            }

            $encCol    = $col . '_enc';
            $totalDone = 0;
            $totalLeft = 0;
            $allDbs    = _enc_getAllDbs();

            foreach ($allDbs as $dbConn) {
                try {
                    // Skip if table doesn't exist in this database
                    $tblCheck = $dbConn->prepare(
                        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                    );
                    $tblCheck->execute([$table]);
                    if ((int)$tblCheck->fetchColumn() === 0) continue;

                    // Count rows needing migration (non-null original, null _enc)
                    $cntStmt = $dbConn->prepare(
                        "SELECT COUNT(*) FROM `$table` WHERE `$col` IS NOT NULL AND `$col` != '' AND `$encCol` IS NULL"
                    );
                    $cntStmt->execute();
                    $pending = (int)$cntStmt->fetchColumn();
                    $totalLeft += $pending;

                    if ($pending === 0) continue;

                    // Fetch batch
                    $selStmt = $dbConn->prepare(
                        "SELECT id, `$col` FROM `$table` WHERE `$col` IS NOT NULL AND `$col` != '' AND `$encCol` IS NULL LIMIT ?"
                    );
                    $selStmt->execute([$batchSize]);
                    $rows = $selStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Encrypt + update in transaction
                    $dbConn->beginTransaction();
                    $upd = $dbConn->prepare("UPDATE `$table` SET `$encCol` = ? WHERE id = ?");
                    foreach ($rows as $r) {
                        $encrypted = Cipher::encrypt($r[$col]);
                        $upd->execute([$encrypted, $r['id']]);
                        $totalDone++;
                    }
                    $dbConn->commit();

                    $totalLeft -= count($rows);
                } catch (Throwable $e) {
                    if ($dbConn->inTransaction()) $dbConn->rollBack();
                    throw new Exception("Error migrando $fieldKey: " . $e->getMessage());
                }
            }

            // Update state
            $state = EncryptionMap::getState();
            $prev  = (int)($state['fields'][$fieldKey]['migrated'] ?? 0);
            EncryptionMap::setFieldPhase($table, $col,
                $totalLeft <= 0 ? 'migrated' : 'prepared',
                ['migrated' => $prev + $totalDone, 'pending' => max(0, $totalLeft)]
            );

            jsonOk([
                'field'     => $fieldKey,
                'processed' => $totalDone,
                'remaining' => max(0, $totalLeft),
                'complete'  => $totalLeft <= 0,
            ]);
            break;

        case 'encryption_activate':
            // Phase 3: Switch reads to _enc columns
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $selected = $input['fields'] ?? [];
            if (empty($selected)) throw new Exception('Selecciona al menos un campo');

            $results = [];
            foreach ($selected as $fieldKey) {
                [$table, $col] = explode('.', $fieldKey, 2);
                if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => 'Campo no reconocido'];
                    continue;
                }

                $phase = EncryptionMap::fieldPhase($table, $col);
                $force = !empty($input['force']);
                if ($phase !== 'migrated' && !$force) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => "Fase actual: $phase (requiere 'migrated', usa force=true para forzar)"];
                    continue;
                }

                EncryptionMap::setFieldPhase($table, $col, 'active');
                $results[] = ['field' => $fieldKey, 'ok' => true];
            }

            jsonOk(['results' => $results]);
            break;

        case 'encryption_rollback':
            // Rollback: deactivate or drop _enc columns
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $selected  = $input['fields'] ?? [];
            $dropCols  = !empty($input['drop_columns']);
            if (empty($selected)) throw new Exception('Selecciona al menos un campo');

            $results = [];
            $allDbs  = $dropCols ? _enc_getAllDbs() : [];

            foreach ($selected as $fieldKey) {
                [$table, $col] = explode('.', $fieldKey, 2);
                if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => 'Campo no reconocido'];
                    continue;
                }

                if ($dropCols) {
                    $encCol = $col . '_enc';
                    foreach ($allDbs as $dbConn) {
                        try {
                            $check = $dbConn->prepare(
                                "SELECT COUNT(*) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
                            );
                            $check->execute([$table, $encCol]);
                            if ((int)$check->fetchColumn() > 0) {
                                $dbConn->exec("ALTER TABLE `$table` DROP COLUMN `$encCol`");
                            }
                        } catch (Throwable $e) {
                            $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => $e->getMessage()];
                            continue 2;
                        }
                    }
                }

                EncryptionMap::setFieldPhase($table, $col, 'none', ['migrated' => 0, 'pending' => 0]);
                $results[] = ['field' => $fieldKey, 'ok' => true];
            }

            jsonOk(['results' => $results]);
            break;

        case 'encryption_toggle':
            // Switch global: leer de columnas cifradas o no
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $enabled = !empty($input['read_encrypted']);
            EncryptionMap::setReadEncrypted($enabled);

            jsonOk(['read_encrypted' => $enabled]);
            break;

        case 'encryption_consolidate':
            // Phase 4: Remove original columns, rename _enc → original
            // This permanently removes plaintext data from the DB.
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/Cipher.php';
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            if (!Cipher::hasKey()) throw new Exception('DATA_ENCRYPTION_KEY no configurada');

            $selected = $input['fields'] ?? [];
            if (empty($selected)) throw new Exception('Selecciona al menos un campo');

            $results = [];
            $allDbs  = _enc_getAllDbs();

            foreach ($selected as $fieldKey) {
                [$table, $col] = explode('.', $fieldKey, 2);
                if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => 'Campo no reconocido'];
                    continue;
                }

                $phase = EncryptionMap::fieldPhase($table, $col);
                $force = !empty($input['force']);
                if ($phase !== 'active' && !$force) {
                    $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => "Requiere fase 'active', actual: $phase (usa force=true para reparar drift)"];
                    continue;
                }

                $encCol = $col . '_enc';

                // Verify all _enc data is populated before consolidating
                foreach ($allDbs as $dbConn) {
                    try {
                        $check = $dbConn->prepare(
                            "SELECT COUNT(*) FROM `$table`
                             WHERE `$col` IS NOT NULL AND `$col` != '' AND (`$encCol` IS NULL OR `$encCol` = '')"
                        );
                        $check->execute();
                        $missing = (int)$check->fetchColumn();
                        if ($missing > 0) {
                            $results[] = [
                                'field' => $fieldKey, 'ok' => false,
                                'error' => "$missing filas sin cifrar en $encCol — ejecutar migración primero"
                            ];
                            continue 2;
                        }
                    } catch (Throwable $e) {
                        // Table might not exist in this tenant
                    }
                }

                // Execute: DROP original, RENAME _enc → original
                // Note: DDL (ALTER TABLE) causes implicit commit in MySQL/MariaDB,
                // so we combine both operations in a single ALTER statement for atomicity.
                foreach ($allDbs as $dbConn) {
                    try {
                        // Check column exists
                        $chk = $dbConn->prepare(
                            "SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
                        );
                        $chk->execute([$table, $encCol]);
                        if ((int)$chk->fetchColumn() === 0) continue;

                        // Single ALTER: drop original + rename _enc → original (atomic DDL)
                        $dbConn->exec(
                            "ALTER TABLE `$table` DROP COLUMN `$col`, CHANGE COLUMN `$encCol` `$col` TEXT NULL"
                        );
                    } catch (Throwable $e) {
                        $results[] = ['field' => $fieldKey, 'ok' => false, 'error' => $e->getMessage()];
                        continue 2;
                    }
                }

                EncryptionMap::setFieldPhase($table, $col, 'consolidated');
                $results[] = ['field' => $fieldKey, 'ok' => true];
            }

            jsonOk(['results' => $results]);
            break;

        case 'encryption_set_phase':
            // Manual override: fija la fase de un campo en encryption_state.json
            // SIN ejecutar DDL. Para reparar drift cuando el JSON quedó
            // desincronizado con la BD real (ej. fase 'consolidated' falsa).
            // §5 PHIPA/NOM — esto NO mueve datos, solo etiqueta. Quien lo use
            // debe verificar manualmente que la BD coincide con la fase fijada.
            if ($method !== 'POST') throw new Exception('POST requerido');
            require_once dirname(__DIR__) . '/includes/EncryptionMap.php';

            $field = (string)($input['field'] ?? '');
            $phase = (string)($input['phase'] ?? '');
            $valid = ['none','prepared','migrated','active','consolidated'];
            if (!in_array($phase, $valid, true)) {
                throw new Exception('Fase inválida. Permitidas: ' . implode(', ', $valid));
            }
            if (!str_contains($field, '.')) throw new Exception('Campo inválido (esperado tabla.columna)');
            [$table, $col] = explode('.', $field, 2);
            if (!isset(EncryptionMap::FIELDS[$table][$col])) {
                throw new Exception('Campo no reconocido en EncryptionMap');
            }

            EncryptionMap::setFieldPhase($table, $col, $phase);
            jsonOk(['field' => $field, 'phase' => $phase]);
            break;

        // ── Demo Seeding ───────────────────────────────────────────────────
        case 'seed_demo':
            $master = Database::getMaster();

            // GET → check status
            if ($method === 'GET') {
                $stmt = $master->query("SELECT id FROM instituciones WHERE nombre LIKE '[DEMO]%' LIMIT 1");
                $row  = $stmt->fetch(PDO::FETCH_ASSOC);
                jsonOk(['active' => (bool)$row, 'inst_id' => $row ? (int)$row['id'] : null]);
            }

            if ($method !== 'POST') throw new Exception('POST requerido');
            $sub = $input['sub'] ?? '';

            // ── DISABLE: Delete all demo data ──
            if ($sub === 'disable') {
                $log = '';
                $stmt = $master->query("SELECT id FROM instituciones WHERE nombre LIKE '[DEMO]%'");
                $demoInsts = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($demoInsts)) {
                    jsonOk(['log' => "No hay datos demo para eliminar.\n"]);
                }

                foreach ($demoInsts as $instId) {
                    $db = Database::getTenant((int)$instId);

                    // Tenant tables (order matters for FK)
                    $tenantTables = ['cuidados_notas','cuidados_registros','notas_medico',
                                     'prescripciones','configuracion','residentes'];
                    foreach ($tenantTables as $t) {
                        try {
                            $del = $db->prepare("DELETE FROM `$t` WHERE institucion_id = ?");
                            $del->execute([$instId]);
                            $c = $del->rowCount();
                            if ($c) $log .= "  ✓ $t: $c filas eliminadas\n";
                        } catch (Throwable $e) {
                            $log .= "  ⚠ $t: " . $e->getMessage() . "\n";
                        }
                    }

                    // Master tables
                    $master->prepare("DELETE FROM usuario_instituciones WHERE institucion_id = ?")->execute([$instId]);
                    $log .= "  ✓ usuario_instituciones limpiada\n";

                    // Delete demo users (by email pattern)
                    $master->exec("DELETE FROM usuarios WHERE email LIKE 'demo_%@geriapp.com'");
                    $log .= "  ✓ usuarios demo eliminados\n";

                    // Delete institution
                    $master->prepare("DELETE FROM instituciones WHERE id = ?")->execute([$instId]);
                    $log .= "  ✓ institución $instId eliminada\n";
                }

                Database::clearTenantCache(0);
                jsonOk(['log' => $log]);
            }

            // ── ENABLE: Create demo data ──
            if ($sub !== 'enable') throw new Exception("Sub-acción '$sub' no reconocida");

            // Check if already exists
            $check = $master->query("SELECT id FROM instituciones WHERE nombre LIKE '[DEMO]%' LIMIT 1");
            if ($check->fetch()) throw new Exception('Ya existen datos demo. Desactívalos primero.');

            $log = '';
            $now = date('Y-m-d');

            // 1) Institution
            $master->prepare(
                "INSERT INTO instituciones (nombre, email_admin, telefono, direccion, timezone, estado, max_residentes, max_usuarios, num_camas)
                 VALUES (?, ?, ?, ?, 'America/Mexico_City', 'activa', 50, 20, 20)"
            )->execute([
                '[DEMO] Casa Geriátrica Bienestar',
                'demo_admin@geriapp.com',
                '+52 55 1234 5678',
                'Av. Reforma 123, Col. Centro, CDMX'
            ]);
            $instId = (int)$master->lastInsertId();
            $log .= "✓ Institución creada (ID: $instId)\n";

            $db = Database::getTenant($instId);

            // 2) Users
            $passHash = password_hash('Demo2026!', PASSWORD_BCRYPT, ['cost' => 12]);
            $demoUsers = [
                ['Demo Admin',    'demo_admin@geriapp.com',     'admin'],
                ['Dra. Laura Méndez', 'demo_medico@geriapp.com', 'medico'],
                ['Cuidador Carlos Ruiz',  'demo_enfermero@geriapp.com', 'enfermero'],
                ['María Torres',  'demo_familiar@geriapp.com',  'familiar'],
            ];
            $userIds = [];
            foreach ($demoUsers as [$nombre, $email, $rol]) {
                $master->prepare(
                    "INSERT INTO usuarios (institucion_id, nombre, email, password_hash, rol, estado, password_change_required)
                     VALUES (?, ?, ?, ?, ?, 'activo', 0)"
                )->execute([$instId, $nombre, $email, $passHash, $rol]);
                $uid = (int)$master->lastInsertId();
                $userIds[$rol] = $uid;

                $master->prepare(
                    "INSERT INTO usuario_instituciones (usuario_id, institucion_id, rol, estado)
                     VALUES (?, ?, ?, 'activo')"
                )->execute([$uid, $instId, $rol]);
            }
            $log .= "✓ 4 usuarios creados (admin, médico, cuidador, familiar)\n";

            // 3) Configuracion
            $db->prepare(
                "INSERT INTO configuracion (institucion_id, inst_nombre, moneda, timezone, idioma, fecha_formato,
                 turno_mat_inicio, turno_mat_fin, turno_mat_siglas,
                 turno_ves_inicio, turno_ves_fin, turno_ves_siglas,
                 turno_noc_inicio, turno_noc_fin, turno_noc_siglas)
                 VALUES (?, ?, 'MXN', 'America/Mexico_City', 'es', 'd/m/Y',
                         '07:00','14:00','TM', '14:00','21:00','TV', '21:00','07:00','TN')"
            )->execute([$instId, '[DEMO] Casa Geriátrica Bienestar']);
            $log .= "✓ Configuración creada\n";

            // 4) Residentes (10)
            $residentes = [
                ['Guadalupe','García López','1938-03-15','F','Alzheimer leve, HTA','A+','101'],
                ['José Luis','Martínez Sánchez','1940-07-22','M','Diabetes tipo 2, Artrosis','O+','102'],
                ['María Elena','Hernández Flores','1935-11-10','F','Parkinson estadio II','B+','103'],
                ['Roberto','Díaz Morales','1942-05-03','M','EPOC, Cardiopatía isquémica','A-','104'],
                ['Carmen','Rodríguez Vega','1937-09-18','F','Demencia vascular','O-','105'],
                ['Fernando','López Castillo','1939-01-25','M','Fractura cadera (rehabilitación)','AB+','106'],
                ['Josefina','Ramírez Torres','1936-12-08','F','Insuficiencia renal crónica','B-','107'],
                ['Antonio','Pérez Gutiérrez','1941-04-14','M','Depresión mayor, Osteoporosis','O+','108'],
                ['Rosa María','Sánchez Navarro','1934-08-30','F','Artritis reumatoide, HTA','A+','109'],
                ['Miguel Ángel','Flores Hernández','1943-06-20','M','Diabetes tipo 2, Neuropatía','AB-','110'],
            ];
            $residenteIds = [];
            foreach ($residentes as $i => [$nom, $ape, $fnac, $sexo, $dx, $sangre, $hab]) {
                $folio = 'DEMO-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                $db->prepare(
                    "INSERT INTO residentes (institucion_id, folio, nombre, apellidos, fecha_nacimiento, sexo,
                     diagnostico, tipo_sangre, habitacion, fecha_ingreso, estado, medico_id, familiar_id,
                     contacto_nombre, contacto_parentesco, contacto_telefono)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?, ?, ?)"
                )->execute([
                    $instId, $folio, $nom, $ape, $fnac, $sexo, $dx, $sangre, $hab,
                    date('Y-m-d', strtotime("-" . ($i * 30 + 10) . " days")),
                    $userIds['medico'], $userIds['familiar'],
                    'Familiar de ' . $nom, 'Hijo/a', '+52 55 ' . rand(1000, 9999) . ' ' . rand(1000, 9999)
                ]);
                $residenteIds[] = (int)$db->lastInsertId();
            }
            $log .= "✓ 10 residentes creados\n";

            // 5) Cuidados registros (al menos 1 por categoría por residente 0-2)
            $categorias = [
                'sueno' => '{"calidad":"buena","horas":7,"interrupciones":0}',
                'alimentacion' => '{"tipo":"completa","porcion":"75%","liquidos":"500ml"}',
                'medicacion' => '{"medicamento":"Metformina 850mg","administrado":true}',
                'higiene' => '{"tipo":"baño completo","asistencia":"parcial"}',
                'terapia' => '{"tipo":"fisioterapia","duracion":30,"tolerancia":"buena"}',
                'movilidad' => '{"tipo":"deambulación","distancia":"50m","asistencia":"andadera"}',
                'eliminacion' => '{"tipo_eliminacion":"urinaria","frecuencia":"normal","incontinencia":false}',
                'comportamiento' => '{"estado_animo":"tranquilo","agitacion":false,"orientacion":"parcial"}',
                'signos_vitales' => '{"ta_sistolica":120,"ta_diastolica":80,"fc":72,"temp":36.5,"spo2":96,"fr":18,"glucosa":110}',
            ];
            $cuidCount = 0;
            foreach ($categorias as $cat => $datos) {
                // Create for first 3 residents
                for ($r = 0; $r < 3 && $r < count($residenteIds); $r++) {
                    $fecha = date('Y-m-d', strtotime("-" . rand(0, 5) . " days"));
                    $hora  = sprintf('%02d:%02d', rand(6, 21), rand(0, 59));
                    $db->prepare(
                        "INSERT INTO cuidados_registros (residente_id, institucion_id, usuario_id, categoria, datos, observaciones, fecha, hora, pendiente, verificacion_biometrica)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"
                    )->execute([
                        $residenteIds[$r], $instId, $userIds['enfermero'],
                        $cat, $datos, 'Registro demo — sin novedad', $fecha, $hora
                    ]);
                    $cuidCount++;
                }
            }
            $log .= "✓ $cuidCount registros de cuidados creados (9 categorías × 3 residentes)\n";

            // 6) Cuidados notas (1 per first 5 residents)
            for ($r = 0; $r < 5 && $r < count($residenteIds); $r++) {
                $db->prepare(
                    "INSERT INTO cuidados_notas (institucion_id, residente_id, usuario_id, nota, prioridad, fecha)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $instId, $residenteIds[$r], $userIds['enfermero'],
                    'Nota de turno demo: Residente estable, sin incidencias relevantes. Se mantiene plan de cuidados.',
                    $r === 0 ? 'importante' : 'normal',
                    date('Y-m-d', strtotime("-" . $r . " days"))
                ]);
            }
            $log .= "✓ 5 notas de turno creadas\n";

            // 7) Notas médico (1 per first 4 residents)
            $soapTemplates = [
                '<p><b>S:</b> Paciente refiere sentirse bien, sin dolor. Sueño reparador.</p><p><b>O:</b> TA 130/85, FC 78, Temp 36.4°C. Consciente, orientada. Cardiopulmonar sin compromiso.</p><p><b>A:</b> Alzheimer leve estable. HTA controlada.</p><p><b>P:</b> Continuar tratamiento actual. Revalorar en 1 semana.</p>',
                '<p><b>S:</b> Refiere dolor leve en rodillas al caminar. Apetito conservado.</p><p><b>O:</b> Glucosa capilar 145 mg/dL. IMC 27. Edema leve MMII.</p><p><b>A:</b> DM2 con control regular. Artrosis degenerativa bilateral.</p><p><b>P:</b> Ajustar dosis de Metformina. Iniciar condroitín.</p>',
                '<p><b>S:</b> Familiar reporta temblor aumentado en mano derecha.</p><p><b>O:</b> Tremor fino en reposo mano derecha. Bradicinesia leve. Marcha conservada.</p><p><b>A:</b> Parkinson estadio II — progresión esperada.</p><p><b>P:</b> Aumentar Levodopa 250mg c/8h. Terapia ocupacional 3x/semana.</p>',
                '<p><b>S:</b> Disnea de medianos esfuerzos. Tos productiva matutina.</p><p><b>O:</b> SpO2 93%, FR 22. Sibilancias bilaterales. Sin cianosis.</p><p><b>A:</b> EPOC exacerbación leve.</p><p><b>P:</b> Nebulización con Salbutamol. Control radiológico. O2 suplementario PRN.</p>',
            ];
            for ($r = 0; $r < 4 && $r < count($residenteIds); $r++) {
                $db->prepare(
                    "INSERT INTO notas_medico (institucion_id, residente_id, usuario_id, contenido, estado)
                     VALUES (?, ?, ?, ?, 'vigente')"
                )->execute([
                    $instId, $residenteIds[$r], $userIds['medico'], $soapTemplates[$r]
                ]);
            }
            $log .= "✓ 4 notas médicas SOAP creadas\n";

            // 8) Prescripciones (2 per first 5 residents)
            $meds = [
                ['Donepezilo 10mg', '10mg', 'Oral', 'Cada 24h', 'Noche, con alimento'],
                ['Losartán 50mg', '50mg', 'Oral', 'Cada 12h', 'Mañana y noche'],
                ['Metformina 850mg', '850mg', 'Oral', 'Cada 8h', 'Con alimentos'],
                ['Atorvastatina 20mg', '20mg', 'Oral', 'Cada 24h', 'Noche'],
                ['Levodopa/Carbidopa 250/25mg', '250/25mg', 'Oral', 'Cada 8h', 'Antes de alimentos'],
                ['Clonazepam 0.5mg', '0.5mg', 'Oral', 'Cada 24h', 'Noche para conciliar sueño'],
                ['Salbutamol inhalador', '2 disparos', 'Inhalada', 'PRN', 'Máximo c/6h si disnea'],
                ['Omeprazol 20mg', '20mg', 'Oral', 'Cada 24h', 'Ayuno, 30 min antes del desayuno'],
                ['Insulina Glargina', '20UI', 'Subcutánea', 'Cada 24h', 'Noche a las 22:00'],
                ['Paracetamol 500mg', '500mg', 'Oral', 'PRN', 'Máximo 3g/día, si dolor o fiebre'],
            ];
            for ($r = 0; $r < 5 && $r < count($residenteIds); $r++) {
                for ($m = 0; $m < 2; $m++) {
                    $med = $meds[$r * 2 + $m];
                    $db->prepare(
                        "INSERT INTO prescripciones (residente_id, institucion_id, nombre, dosis, via, frecuencia, indicacion, medico_nombre, activo, inicio)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
                    )->execute([
                        $residenteIds[$r], $instId,
                        $med[0], $med[1], $med[2], $med[3], $med[4],
                        'Dra. Laura Méndez',
                        date('Y-m-d', strtotime('-30 days'))
                    ]);
                }
            }
            $log .= "✓ 10 prescripciones creadas (2 por residente × 5)\n";

            $log .= "\n🎉 Seeding completado. Institución ID: $instId";
            jsonOk(['log' => $log, 'inst_id' => $instId]);
            break;

        // ── Onboarding: cargar perfil de defaults para preview ─────────────
        case 'onboarding_defaults':
            $defaults = require dirname(__DIR__) . '/conf/institucion_defaults.php';
            // Trim heavy legal HTML to a preview to keep payload small
            $preview = $defaults;
            foreach (['terminos_condiciones', 'aviso_privacidad'] as $k) {
                if (!empty($preview[$k]['contenido'])) {
                    $preview[$k]['contenido_bytes'] = strlen($preview[$k]['contenido']);
                    $preview[$k]['contenido'] = mb_substr(strip_tags($preview[$k]['contenido']), 0, 240) . '…';
                }
            }
            // Enmascarar secretos cargados de .env (visibles en preview, no en logs)
            //   Mantenemos primeros 4 + últimos 4 caracteres y rellenamos el medio con *.
            //   Si el valor es muy corto (<10) se enmascara casi por completo.
            $_maskSecret = static function ($v): string {
                $v = (string)$v;
                $len = strlen($v);
                if ($len === 0) return '';
                if ($len <= 8)  return str_repeat('*', $len);
                $head = substr($v, 0, 4);
                $tail = substr($v, -4);
                return $head . str_repeat('*', max(4, $len - 8)) . $tail;
            };
            $secretKeys = ['smtp_password', 'wa_api_key', 'wa_instance_id', 'ia_api_key'];
            if (isset($preview['integraciones']) && is_array($preview['integraciones'])) {
                foreach ($secretKeys as $sk) {
                    if (array_key_exists($sk, $preview['integraciones'])) {
                        $preview['integraciones'][$sk] = $_maskSecret($preview['integraciones'][$sk]);
                    }
                }
            }
            jsonOk(['defaults' => $preview]);
            break;

        // ── Onboarding: crear institución completa en un solo flujo ─────────
        case 'onboarding_create':
            if ($method !== 'POST') throw new Exception('POST requerido');

            $nombre   = trim($input['nombre']      ?? '');
            $emailAdm = strtolower(trim($input['email_admin'] ?? ''));
            $tz       = $input['timezone']         ?? 'America/Mexico_City';
            $estado   = $input['estado']           ?? 'trial';
            $planId   = isset($input['plan_id']) && $input['plan_id'] !== '' ? (int)$input['plan_id'] : null;
            $maxRes   = isset($input['max_residentes']) && $input['max_residentes'] !== '' ? (int)$input['max_residentes'] : null;
            $maxUsr   = isset($input['max_usuarios'])   && $input['max_usuarios']   !== '' ? (int)$input['max_usuarios']   : null;
            $createDb = !empty($input['create_db']);
            $usuarios     = is_array($input['usuarios'] ?? null)     ? $input['usuarios']     : [];
            $invitaciones = is_array($input['invitaciones'] ?? null) ? $input['invitaciones'] : [];
            $overrides    = is_array($input['overrides'] ?? null)    ? $input['overrides']    : [];

            if (!$nombre)   throw new Exception('Nombre de institución es requerido');
            if (!$emailAdm || !filter_var($emailAdm, FILTER_VALIDATE_EMAIL)) throw new Exception('Email administrador inválido');
            if (!in_array($estado, ['activa','trial','suspendida'], true)) throw new Exception('Estado inválido');

            require_once dirname(__DIR__) . '/db/models/Configuracion.php';

            $defaults = require dirname(__DIR__) . '/conf/institucion_defaults.php';
            $master = Database::getMaster();
            $sa     = $_SESSION['sa_user'] ?? 'superadmin';

            $log = [];
            $created = ['institucion_id' => null, 'db_name' => null, 'usuarios' => [], 'invitaciones' => [], 'errors' => []];

            try {
                $master->beginTransaction();

                // 1) INSERT institución
                $master->prepare(
                    "INSERT INTO instituciones
                        (nombre, email_admin, telefono, direccion, timezone,
                         plan_id, estado, max_residentes, max_usuarios, creado_por)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $nombre,
                    $emailAdm,
                    $input['telefono']  ?? null,
                    $input['direccion'] ?? null,
                    $tz,
                    $planId,
                    $estado,
                    $maxRes,
                    $maxUsr,
                    $sa,
                ]);
                $instId = (int)$master->lastInsertId();
                $created['institucion_id'] = $instId;
                $log[] = "✓ Institución #$instId «{$nombre}» creada (creado_por=$sa)";

                $master->commit();
            } catch (Throwable $e) {
                if ($master->inTransaction()) $master->rollBack();
                throw new Exception('Error al crear institución: ' . $e->getMessage());
            }

            // 2) Crear BD tenant si se solicita
            if ($createDb) {
                try {
                    $dbName = Database::tenantDbName($instId);
                    Database::createTenantDB($dbName);
                    $master->prepare("UPDATE instituciones SET db_name = ? WHERE id = ?")->execute([$dbName, $instId]);
                    Database::clearTenantCache($instId);
                    $created['db_name'] = $dbName;
                    $log[] = "✓ Base de datos tenant «{$dbName}» creada";
                } catch (Throwable $e) {
                    $created['errors'][] = 'BD tenant: ' . $e->getMessage();
                    $log[] = "✗ BD tenant: {$e->getMessage()}";
                }
            }

            // 3) Sembrar configuracion (defaults + overrides)
            try {
                $cfgFlat = array_merge(
                    $defaults['general'],
                    $defaults['integraciones'],
                    $defaults['seguridad'],
                    $defaults['notificaciones'],
                    ['roles_permisos' => json_encode($defaults['roles_permisos'])]
                );
                // Aplicar overrides del wizard
                foreach ($overrides as $k => $v) {
                    if (array_key_exists($k, $cfgFlat) && $v !== null && $v !== '') $cfgFlat[$k] = $v;
                }
                // Forzar valores institucionales
                $cfgFlat['inst_nombre']     = $nombre;
                $cfgFlat['legal_cc_email']  = $cfgFlat['legal_cc_email'] ?: $emailAdm;
                $cfgFlat['timezone']        = $tz;

                Configuracion::upsert($instId, $cfgFlat);
                $log[] = "✓ Configuración sembrada (" . count($cfgFlat) . " campos)";
            } catch (Throwable $e) {
                $created['errors'][] = 'Configuración: ' . $e->getMessage();
                $log[] = "✗ Configuración: {$e->getMessage()}";
            }

            // 4) Sembrar documentos legales
            foreach (['terminos_condiciones' => 'terminos', 'aviso_privacidad' => 'privacidad'] as $key => $tipo) {
                $doc = $defaults[$key] ?? null;
                if (!$doc || empty($doc['contenido'])) continue;
                try {
                    $master->prepare(
                        "INSERT INTO documentos_legales
                            (institucion_id, tipo, version, titulo, contenido, vigente, requiere_firma, creado_por)
                         VALUES (?,?,?,?,?,?,?,NULL)"
                    )->execute([
                        $instId, $tipo, $doc['version'] ?? '1.0', $doc['titulo'] ?? '',
                        $doc['contenido'], !empty($doc['vigente']) ? 1 : 0,
                        !empty($doc['requiere_firma']) ? 1 : 0,
                    ]);
                    $log[] = "✓ Documento legal «{$doc['titulo']}» (v{$doc['version']}) creado";
                } catch (Throwable $e) {
                    // Tabla puede no existir aún; intentar crearla
                    try {
                        require_once dirname(__DIR__) . '/db/migrate_documentos_legales.php';
                        $master->prepare(
                            "INSERT INTO documentos_legales
                                (institucion_id, tipo, version, titulo, contenido, vigente, requiere_firma, creado_por)
                             VALUES (?,?,?,?,?,?,?,NULL)"
                        )->execute([
                            $instId, $tipo, $doc['version'] ?? '1.0', $doc['titulo'] ?? '',
                            $doc['contenido'], !empty($doc['vigente']) ? 1 : 0,
                            !empty($doc['requiere_firma']) ? 1 : 0,
                        ]);
                        $log[] = "✓ Documento legal «{$doc['titulo']}» creado (tras migrate)";
                    } catch (Throwable $e2) {
                        $created['errors'][] = "Doc legal $tipo: " . $e2->getMessage();
                        $log[] = "✗ Doc legal $tipo: {$e2->getMessage()}";
                    }
                }
            }

            // 5) Crear usuarios iniciales (compat: solo si payload trae alguno;
            //    el wizard nuevo NO los envía — el admin se gestiona en 5b)
            foreach ($usuarios as $u) {
                $uNombre = trim($u['nombre'] ?? '');
                $uEmail  = strtolower(trim($u['email'] ?? ''));
                $uRol    = sa_role_storage($u['rol'] ?? 'cuidador');
                $uPass   = (string)($u['password'] ?? '');
                if (!$uNombre || !$uEmail || !filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
                    $log[] = "⚠ Usuario omitido (datos inválidos): " . ($uNombre ?: '(sin nombre)') . " / $uEmail";
                    continue;
                }
                if (!in_array($uRol, ['admin','medico','enfermero','familiar'], true)) $uRol = 'enfermero';
                if (strlen($uPass) < 8) {
                    // generar contraseña temporal
                    $uPass = 'Geri' . bin2hex(random_bytes(4)) . '!';
                    $tempPass = $uPass;
                } else {
                    $tempPass = null;
                }
                try {
                    $chk = $master->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $chk->execute([$uEmail]);
                    $existing = $chk->fetch();
                    if ($existing) {
                        // sólo enlazar
                        $master->prepare(
                            "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
                             VALUES (?,?,?)"
                        )->execute([(int)$existing['id'], $instId, $uRol]);
                        $created['usuarios'][] = ['id' => (int)$existing['id'], 'email' => $uEmail, 'rol' => $uRol, 'linked' => true];
                        $log[] = "✓ Usuario existente $uEmail vinculado como " . sa_role_label($uRol);
                    } else {
                        $hash = password_hash($uPass, PASSWORD_DEFAULT);
                        $master->prepare(
                            "INSERT INTO usuarios
                                (institucion_id, nombre, email, password_hash, rol, estado, password_change_required)
                             VALUES (?,?,?,?,?, 'activo', 1)"
                        )->execute([$instId, $uNombre, $uEmail, $hash, $uRol]);
                        $newId = (int)$master->lastInsertId();
                        $master->prepare(
                            "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
                             VALUES (?,?,?)"
                        )->execute([$newId, $instId, $uRol]);
                        $entry = ['id' => $newId, 'email' => $uEmail, 'nombre' => $uNombre, 'rol' => $uRol];
                        if ($tempPass) $entry['temp_password'] = $tempPass;
                        $created['usuarios'][] = $entry;
                        $log[] = "✓ Usuario $uEmail (" . sa_role_label($uRol) . ") creado" . ($tempPass ? " — pass temporal: $tempPass" : '');
                    }
                } catch (Throwable $e) {
                    $created['errors'][] = "Usuario $uEmail: " . $e->getMessage();
                    $log[] = "✗ Usuario $uEmail: {$e->getMessage()}";
                }
            }

            // 5b) Auto-gestión del administrador a partir de email_admin del paso 1
            //   - Si el email YA tiene cuenta → vincular como 'admin' a esta institución
            //   - Si NO existe → crear invitación con rol='admin' (token para registrarse)
            $adminUserId      = null;
            $adminInviteToken = null;
            $adminInviteId    = null;
            try {
                $chk = $master->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
                $chk->execute([$emailAdm]);
                $existingAdmin = $chk->fetch();
                if ($existingAdmin) {
                    $master->prepare(
                        "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
                         VALUES (?,?,?)"
                    )->execute([(int)$existingAdmin['id'], $instId, 'admin']);
                    $adminUserId = (int)$existingAdmin['id'];
                    $created['admin'] = ['mode' => 'linked', 'user_id' => $adminUserId, 'email' => $emailAdm];
                    $log[] = "✓ Admin existente $emailAdm vinculado a institución #$instId";
                } else {
                    require_once dirname(__DIR__) . '/db/models/Invitacion.php';
                    $r = Invitacion::create([
                        'institucion_id' => $instId,
                        'email'          => $emailAdm,
                        'rol'            => 'admin',
                        'mensaje'        => "Bienvenido a GeriApp. Configura tu cuenta de administrador para «{$nombre}».",
                        'dias'           => 14,
                        'creado_por'     => null,
                    ]);
                    if ($r) {
                        $adminInviteId    = $r['id'];
                        $adminInviteToken = $r['token'];
                        $created['admin'] = [
                            'mode' => 'invited', 'invitation_id' => $r['id'],
                            'email' => $emailAdm, 'token' => $r['token'],
                        ];
                        $log[] = "✓ Invitación admin a $emailAdm generada (token #{$r['id']})";
                    } else {
                        $created['errors'][] = "Admin: no se pudo generar invitación";
                        $log[] = "✗ Admin $emailAdm: invitación falló";
                    }
                }
            } catch (Throwable $e) {
                $created['errors'][] = "Admin auto: " . $e->getMessage();
                $log[] = "✗ Admin auto-gestión: {$e->getMessage()}";
            }

            // 6) Generar invitaciones
            if (!empty($invitaciones)) {
                require_once dirname(__DIR__) . '/db/models/Invitacion.php';
                // Necesitamos un creador (usuario_id) — usar primer admin recién creado o NULL
                $invitedBy = null;
                foreach ($created['usuarios'] as $cu) {
                    if (($cu['rol'] ?? '') === 'admin' && !empty($cu['id'])) { $invitedBy = (int)$cu['id']; break; }
                }
                foreach ($invitaciones as $inv) {
                    $iEmail = strtolower(trim($inv['email'] ?? ''));
                    $iRol   = sa_role_storage($inv['rol'] ?? 'cuidador');
                    $iDias  = (int)($inv['dias'] ?? 7);
                    if (!$iEmail || !filter_var($iEmail, FILTER_VALIDATE_EMAIL)) {
                        $log[] = "⚠ Invitación omitida (email inválido): $iEmail";
                        continue;
                    }
                    if (!in_array($iRol, ['admin','medico','enfermero','familiar'], true)) $iRol = 'enfermero';
                    try {
                        $r = Invitacion::create([
                            'institucion_id' => $instId,
                            'email'          => $iEmail,
                            'rol'            => $iRol,
                            'mensaje'        => $inv['mensaje'] ?? null,
                            'dias'           => $iDias,
                            'creado_por'     => $invitedBy,
                        ]);
                        if ($r) {
                            $created['invitaciones'][] = [
                                'id' => $r['id'], 'email' => $iEmail, 'rol' => $iRol,
                                'token' => $r['token'], 'dias' => $iDias,
                            ];
                            $log[] = "✓ Invitación a $iEmail (" . sa_role_label($iRol) . ", $iDias días) generada";
                        } else {
                            $created['errors'][] = "Invitación $iEmail no se pudo crear";
                        }
                    } catch (Throwable $e) {
                        $created['errors'][] = "Invitación $iEmail: " . $e->getMessage();
                        $log[] = "✗ Invitación $iEmail: {$e->getMessage()}";
                    }
                }
            }

            // 7) Notificaciones: email al admin + email a cada invitado + WhatsApp opcional
            //    (la configuración SMTP/WA fue sembrada en paso 3 con valores reales del .env)
            require_once dirname(__DIR__) . '/db/models/Mailer.php';
            require_once dirname(__DIR__) . '/db/models/WaSenderAPI.php';

            $baseUrl = function_exists('app_public_url')
                ? rtrim(app_public_url(), '/')
                : (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '');

            $created['notificaciones'] = ['email' => [], 'whatsapp' => []];

            // 7a) Email al administrador (link de registro o de acceso)
            try {
                $adminLink = $adminInviteToken
                    ? ($baseUrl . '/register.php?token=' . $adminInviteToken)
                    : ($baseUrl . '/index.php');
                $btnHtml = '<p style="margin:24px 0;text-align:center">'
                    . '<a href="' . htmlspecialchars($adminLink, ENT_QUOTES) . '" '
                    . 'style="background:#635bff;color:#fff;padding:12px 28px;border-radius:8px;'
                    . 'text-decoration:none;font-weight:600;font-family:Arial,sans-serif;display:inline-block">'
                    . ($adminInviteToken ? 'Crear mi cuenta' : 'Acceder a GeriApp')
                    . '</a></p>';
                $intro = $adminInviteToken
                    ? "<p>Tu institución <strong>" . htmlspecialchars($nombre) . "</strong> fue creada en GeriApp."
                      . " Configura tu cuenta de administrador y elige tu contraseña:</p>"
                    : "<p>Tu institución <strong>" . htmlspecialchars($nombre) . "</strong> fue creada en GeriApp."
                      . " Ya puedes ingresar con tu cuenta existente:</p>";
                $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#222">'
                    . '<h2 style="color:#635bff">Bienvenido a GeriApp</h2>'
                    . $intro . $btnHtml
                    . '<p style="font-size:12px;color:#888">Si el botón no funciona, copia este enlace:<br>'
                    . '<a href="' . htmlspecialchars($adminLink, ENT_QUOTES) . '">' . htmlspecialchars($adminLink) . '</a></p>'
                    . '</div>';
                $mailer = Mailer::fromConfig($instId);
                $res = $mailer->send($emailAdm, "GeriApp — institución «{$nombre}» creada", $body, '', $cfgFlat['smtp_from_email'] ?? '');
                $ok = !empty($res['success']) || !empty($res['ok']);
                $created['notificaciones']['email'][] = ['to' => $emailAdm, 'role' => 'admin', 'ok' => $ok];
                $log[] = ($ok ? "✓" : "✗") . " Email admin → $emailAdm" . ($ok ? '' : ': ' . ($res['error'] ?? 'fallo'));
            } catch (Throwable $e) {
                $created['notificaciones']['email'][] = ['to' => $emailAdm, 'role' => 'admin', 'ok' => false, 'error' => $e->getMessage()];
                $log[] = "✗ Email admin: {$e->getMessage()}";
            }

            // 7b) Email a cada invitación adicional
            foreach ($created['invitaciones'] as $invSent) {
                try {
                    $url = $baseUrl . '/register.php?token=' . $invSent['token'];
                    $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#222">'
                        . '<h2 style="color:#635bff">Te invitaron a GeriApp</h2>'
                        . '<p>Has sido invitado a <strong>' . htmlspecialchars($nombre) . '</strong> '
                        . 'con el rol <strong>' . htmlspecialchars(sa_role_label($invSent['rol'])) . '</strong>.</p>'
                        . '<p style="margin:24px 0;text-align:center">'
                        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" '
                        . 'style="background:#635bff;color:#fff;padding:12px 28px;border-radius:8px;'
                        . 'text-decoration:none;font-weight:600;display:inline-block">Aceptar invitación</a></p>'
                        . '<p style="font-size:12px;color:#888">Enlace: <a href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
                        . htmlspecialchars($url) . '</a></p></div>';
                    $res = Mailer::fromConfig($instId)->send($invSent['email'], "Invitación a GeriApp — {$nombre}", $body, '', $cfgFlat['smtp_from_email'] ?? '');
                    $ok = !empty($res['success']) || !empty($res['ok']);
                    $created['notificaciones']['email'][] = ['to' => $invSent['email'], 'role' => $invSent['rol'], 'ok' => $ok];
                    $log[] = ($ok ? "✓" : "✗") . " Email invitación → {$invSent['email']}" . ($ok ? '' : ': ' . ($res['error'] ?? 'fallo'));
                } catch (Throwable $e) {
                    $created['notificaciones']['email'][] = ['to' => $invSent['email'], 'role' => $invSent['rol'], 'ok' => false, 'error' => $e->getMessage()];
                    $log[] = "✗ Email inv {$invSent['email']}: {$e->getMessage()}";
                }
            }

            // 7c) WhatsApp al teléfono institucional si fue capturado
            $instTel = trim((string)($input['telefono'] ?? ''));
            if ($instTel !== '') {
                try {
                    $adminLink = $adminInviteToken
                        ? ($baseUrl . '/register.php?token=' . $adminInviteToken)
                        : ($baseUrl . '/index.php');
                    $msg = "GeriApp: tu institución «{$nombre}» fue creada.\n"
                        . ($adminInviteToken
                            ? "Configura tu cuenta de administrador aquí:\n"
                            : "Accede aquí:\n")
                        . $adminLink;
                    $wa = WaSenderAPI::fromConfig($instId);
                    $r = $wa->sendText($instTel, $msg);
                    $ok = !empty($r['ok']);
                    $created['notificaciones']['whatsapp'][] = ['to' => $instTel, 'ok' => $ok, 'error' => $r['error'] ?? null];
                    $log[] = ($ok ? "✓" : "⚠") . " WhatsApp → $instTel" . ($ok ? '' : ': ' . ($r['error'] ?? 'fallo'));
                } catch (Throwable $e) {
                    $created['notificaciones']['whatsapp'][] = ['to' => $instTel, 'ok' => false, 'error' => $e->getMessage()];
                    $log[] = "✗ WhatsApp: {$e->getMessage()}";
                }
            }

            jsonOk(['result' => $created, 'log' => $log]);
            break;

        // ── Owner Dashboard: vista panorámica para el dueño de GeriApp ──────
        case 'owner_dashboard':
            $db = Database::getMaster();
            $now = date('Y-m-d H:i:s');

            // KPIs
            $kpis = [
                'total_inst'        => (int)$db->query("SELECT COUNT(*) FROM instituciones")->fetchColumn(),
                'inst_activas'      => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE estado='activa'")->fetchColumn(),
                'inst_trial'        => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE estado='trial'")->fetchColumn(),
                'inst_suspendidas'  => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE estado='suspendida'")->fetchColumn(),
                'inst_archivadas'   => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE estado='archivada'")->fetchColumn(),
                'total_usuarios'    => (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
                'usuarios_activos'  => (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE estado='activo'")->fetchColumn(),
                'inst_con_db'       => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE db_name IS NOT NULL AND db_name <> ''")->fetchColumn(),
                'inst_sin_db'       => (int)$db->query("SELECT COUNT(*) FROM instituciones WHERE db_name IS NULL OR db_name = ''")->fetchColumn(),
            ];

            try {
                $kpis['invit_pendientes'] = (int)$db->query(
                    "SELECT COUNT(*) FROM invitaciones WHERE estado='pendiente' AND expires_at > NOW()"
                )->fetchColumn();
                $kpis['invit_expiradas'] = (int)$db->query(
                    "SELECT COUNT(*) FROM invitaciones WHERE estado='expirada' OR (estado='pendiente' AND expires_at <= NOW())"
                )->fetchColumn();
                $kpis['invit_aceptadas'] = (int)$db->query(
                    "SELECT COUNT(*) FROM invitaciones WHERE estado='aceptada'"
                )->fetchColumn();
            } catch (Throwable $e) { $kpis['invit_pendientes'] = $kpis['invit_expiradas'] = $kpis['invit_aceptadas'] = 0; }

            try {
                $kpis['logins_24h'] = (int)$db->query(
                    "SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                )->fetchColumn();
                $kpis['logins_7d']  = (int)$db->query(
                    "SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                )->fetchColumn();
                $kpis['nuevas_inst_30d'] = (int)$db->query(
                    "SELECT COUNT(*) FROM instituciones WHERE creado_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                )->fetchColumn();
                $kpis['nuevos_usr_30d']  = (int)$db->query(
                    "SELECT COUNT(*) FROM usuarios WHERE creado_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                )->fetchColumn();
            } catch (Throwable $e) {}

            // Tabla principal: instituciones con métricas agregadas
            $instRows = $db->query(
                "SELECT i.id, i.nombre, i.estado, i.email_admin, i.timezone,
                        i.db_name, i.creado_at, i.creado_por, i.trial_ends_at, i.plan_vence_at,
                        p.nombre AS plan_nombre,
                        (SELECT COUNT(*) FROM usuario_instituciones ui WHERE ui.institucion_id = i.id)             AS num_usuarios,
                        (SELECT COUNT(*) FROM usuario_instituciones ui JOIN usuarios u ON u.id=ui.usuario_id
                          WHERE ui.institucion_id = i.id AND u.rol='admin')                                       AS num_admins,
                        (SELECT MAX(u.ultimo_acceso) FROM usuario_instituciones ui JOIN usuarios u ON u.id=ui.usuario_id
                          WHERE ui.institucion_id = i.id)                                                         AS ultimo_acceso
                 FROM instituciones i
                 LEFT JOIN planes p ON p.id = i.plan_id
                 ORDER BY i.creado_at DESC, i.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Pendientes por institución (separado por compatibilidad si tabla no existe)
            $pendMap = [];
            try {
                foreach ($db->query(
                    "SELECT institucion_id, COUNT(*) AS n
                     FROM invitaciones WHERE estado='pendiente' AND expires_at > NOW()
                     GROUP BY institucion_id"
                )->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $pendMap[(int)$r['institucion_id']] = (int)$r['n'];
                }
            } catch (Throwable $e) {}
            foreach ($instRows as &$r) {
                $r['invit_pendientes'] = $pendMap[(int)$r['id']] ?? 0;
            }
            unset($r);

            // Invitaciones pendientes recientes
            $invitRows = [];
            try {
                $invitRows = $db->query(
                    "SELECT inv.id, inv.email, inv.rol, inv.estado, inv.creado_at, inv.expires_at,
                            inv.institucion_id, i.nombre AS institucion_nombre,
                            u.nombre AS creado_por_nombre,
                            TIMESTAMPDIFF(DAY, NOW(), inv.expires_at) AS dias_restantes
                     FROM invitaciones inv
                     LEFT JOIN instituciones i ON i.id = inv.institucion_id
                     LEFT JOIN usuarios u      ON u.id = inv.creado_por
                     WHERE inv.estado = 'pendiente' AND inv.expires_at > NOW()
                     ORDER BY inv.creado_at DESC
                     LIMIT 50"
                )->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}

            // Últimos usuarios registrados
            $newUsersRows = $db->query(
                "SELECT u.id, u.nombre, u.email, u.rol, u.estado, u.creado_at, u.ultimo_acceso,
                        u.institucion_id, i.nombre AS institucion_nombre
                 FROM usuarios u
                 LEFT JOIN instituciones i ON i.id = u.institucion_id
                 ORDER BY u.creado_at DESC
                 LIMIT 30"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Distribución por plan
            $planDist = $db->query(
                "SELECT COALESCE(p.nombre, 'Sin plan') AS plan, COUNT(*) AS n
                 FROM instituciones i LEFT JOIN planes p ON p.id = i.plan_id
                 GROUP BY p.nombre ORDER BY n DESC"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Línea temporal: nuevas instituciones por mes (últimos 12)
            $timeline = $db->query(
                "SELECT DATE_FORMAT(creado_at, '%Y-%m') AS mes, COUNT(*) AS n
                 FROM instituciones
                 WHERE creado_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY mes ORDER BY mes"
            )->fetchAll(PDO::FETCH_ASSOC);

            jsonOk([
                'kpis'          => $kpis,
                'instituciones' => $instRows,
                'invitaciones'  => $invitRows,
                'usuarios'      => $newUsersRows,
                'plan_dist'     => $planDist,
                'timeline'      => $timeline,
                'now'           => $now,
            ]);
            break;

        default:
            throw new Exception("Acción '$action' no reconocida");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function jsonOk(array $data): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function saEventQrAttachMetrics(PDO $db, array $rows): array {
    if (!$rows) return $rows;

    foreach ($rows as &$row) {
        $row['scan_count'] = 0;
        $row['registro_count'] = (int)($row['usos_count'] ?? 0);
        $row['first_scan_at'] = null;
        $row['last_scan_at'] = null;
        $row['first_registered_at'] = null;
        $row['last_registered_at'] = $row['last_used_at'] ?? null;
        $row['last_event_at'] = $row['last_used_at'] ?? null;
        $row['conversion_pct'] = null;
    }
    unset($row);

    $ids = array_values(array_unique(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
    $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
    if (!$ids) return $rows;

    try {
        GeriappEventInvitation::ensureEventTable($db);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT invitacion_id,
                    SUM(CASE WHEN tipo = 'scan' THEN 1 ELSE 0 END) AS scan_count,
                    SUM(CASE WHEN tipo = 'registro' THEN 1 ELSE 0 END) AS registro_count,
                    MIN(CASE WHEN tipo = 'scan' THEN creado_at ELSE NULL END) AS first_scan_at,
                    MAX(CASE WHEN tipo = 'scan' THEN creado_at ELSE NULL END) AS last_scan_at,
                    MIN(CASE WHEN tipo = 'registro' THEN creado_at ELSE NULL END) AS first_registered_at,
                    MAX(CASE WHEN tipo = 'registro' THEN creado_at ELSE NULL END) AS last_registered_at,
                    MAX(creado_at) AS last_event_at
             FROM invitaciones_geriapp_eventos
             WHERE invitacion_id IN ($placeholders)
             GROUP BY invitacion_id"
        );
        $stmt->execute($ids);
        $metrics = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $metricRow) {
            $metrics[(int)$metricRow['invitacion_id']] = $metricRow;
        }

        foreach ($rows as &$row) {
            $id = (int)($row['id'] ?? 0);
            if (!isset($metrics[$id])) continue;
            $metric = $metrics[$id];
            $scanCount = (int)($metric['scan_count'] ?? 0);
            $registroCount = max((int)($row['usos_count'] ?? 0), (int)($metric['registro_count'] ?? 0));
            $row['scan_count'] = $scanCount;
            $row['registro_count'] = $registroCount;
            $row['first_scan_at'] = $metric['first_scan_at'] ?? null;
            $row['last_scan_at'] = $metric['last_scan_at'] ?? null;
            $row['first_registered_at'] = $metric['first_registered_at'] ?? null;
            $row['last_registered_at'] = $metric['last_registered_at'] ?? ($row['last_used_at'] ?? null);
            $row['last_event_at'] = $metric['last_event_at'] ?? ($row['last_used_at'] ?? null);
            $row['conversion_pct'] = $scanCount > 0 ? round(($registroCount / $scanCount) * 100, 1) : null;
        }
        unset($row);
    } catch (\Throwable $e) {
        foreach ($rows as &$row) {
            $row['registro_count'] = (int)($row['usos_count'] ?? 0);
        }
        unset($row);
    }

    return $rows;
}

function connectProfile(array $p): PDO {
    $dsn = "mysql:host={$p['host']};port={$p['port']};charset={$p['charset']}";
    return new PDO($dsn, $p['user'], $p['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
}

function countTables(PDO $pdo, string $dbName): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?"
    );
    $stmt->execute([$dbName]);
    return (int)$stmt->fetchColumn();
}

function saTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function saColumnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function saDeleteWhere(PDO $db, string $table, string $where, array $params): int {
    if (!saTableExists($db, $table)) return 0;
    $stmt = $db->prepare('DELETE FROM ' . saSqlIdent($table) . " WHERE $where");
    $stmt->execute($params);
    return $stmt->rowCount();
}

function saUpdateWhere(PDO $db, string $table, string $set, string $where, array $params): int {
    if (!saTableExists($db, $table)) return 0;
    $stmt = $db->prepare('UPDATE ' . saSqlIdent($table) . " SET $set WHERE $where");
    $stmt->execute($params);
    return $stmt->rowCount();
}

function saSqlIdent(string $identifier): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Identificador SQL invalido');
    }
    return '`' . $identifier . '`';
}

function saDatabaseExists(PDO $db, string $dbName): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->execute([$dbName]);
    return (int)$stmt->fetchColumn() > 0;
}

function saNormalizePhoneMx(?string $phone): string {
    $raw = trim((string)$phone);
    if ($raw === '') return '';
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    if (str_starts_with($raw, '+')) return '+' . $digits;
    if (strlen($digits) === 10) return '+52' . $digits;
    if (str_starts_with($digits, '52') && strlen($digits) >= 12) return '+' . $digits;
    return $raw;
}

function saNormalizeContactosJsonPhones(mixed $value): mixed {
    if (!is_string($value) || trim($value) === '') return $value;
    $contacts = json_decode($value, true);
    if (!is_array($contacts)) return $value;
    foreach ($contacts as &$contact) {
        if (!is_array($contact)) continue;
        foreach (['telefono', 'telefono2', 'phone', 'whatsapp'] as $field) {
            if (isset($contact[$field]) && is_string($contact[$field])) {
                $contact[$field] = saNormalizePhoneMx($contact[$field]);
            }
        }
    }
    unset($contact);
    return json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function saNormalizePhonesInCurrentDb(PDO $db, string $dbName): array {
    $tables = [];
    $scanned = 0;
    $updated = 0;

    foreach ([
        ['instituciones', 'telefono'],
        ['usuarios', 'telefono'],
        ['invitaciones', 'telefono'],
        ['cotizaciones', 'telefono'],
    ] as [$table, $column]) {
        $result = saNormalizePlainPhoneColumn($db, $table, $column);
        if (!$result['skipped']) {
            $tables[] = $result;
            $scanned += $result['scanned'];
            $updated += $result['updated'];
        }
    }

    $residentes = saNormalizeResidentPhoneFields($db);
    if (!$residentes['skipped']) {
        $tables[] = $residentes;
        $scanned += $residentes['scanned'];
        $updated += $residentes['updated'];
    }

    return ['database' => $dbName, 'scanned' => $scanned, 'updated' => $updated, 'tables' => $tables];
}

function saInvalidInvitationWhere(PDO $db): ?string {
    if (!saTableExists($db, 'invitaciones') || !saColumnExists($db, 'invitaciones', 'id') || !saColumnExists($db, 'invitaciones', 'email')) {
        return null;
    }
    $emailBlank = "NULLIF(TRIM(COALESCE(" . saSqlIdent('email') . ", '')), '') IS NULL";
    $phoneBlank = saColumnExists($db, 'invitaciones', 'telefono')
        ? "NULLIF(TRIM(COALESCE(" . saSqlIdent('telefono') . ", '')), '') IS NULL"
        : '1=1';
    return "($emailBlank AND $phoneBlank)";
}

function saCleanupInvalidInvitationsInCurrentDb(PDO $db, string $dbName, bool $delete): array {
    $base = [
        'database' => $dbName,
        'invalid' => 0,
        'invalid_before' => 0,
        'deleted' => 0,
        'by_estado' => [],
        'samples' => [],
        'skipped' => false,
    ];

    $where = saInvalidInvitationWhere($db);
    if ($where === null) {
        $base['skipped'] = true;
        return $base;
    }

    $countSql = 'SELECT COUNT(*) FROM ' . saSqlIdent('invitaciones') . ' WHERE ' . $where;
    $invalidBefore = (int)$db->query($countSql)->fetchColumn();
    $base['invalid'] = $invalidBefore;
    $base['invalid_before'] = $invalidBefore;

    if ($invalidBefore > 0) {
        $estadoExpr = saColumnExists($db, 'invitaciones', 'estado') ? saSqlIdent('estado') : "'sin_estado'";
        $base['by_estado'] = $db->query(
            'SELECT COALESCE(' . $estadoExpr . ", 'sin_estado') AS estado, COUNT(*) AS total
             FROM " . saSqlIdent('invitaciones') . "
             WHERE $where
             GROUP BY COALESCE($estadoExpr, 'sin_estado')
             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $select = [saSqlIdent('id') . ' AS id'];
        foreach (['institucion_id', 'rol', 'estado', 'creado_at', 'multi_uso'] as $column) {
            if (saColumnExists($db, 'invitaciones', $column)) {
                $select[] = saSqlIdent($column) . ' AS ' . saSqlIdent($column);
            }
        }
        $base['samples'] = $db->query(
            'SELECT ' . implode(', ', $select) . '
             FROM ' . saSqlIdent('invitaciones') . "
             WHERE $where
             ORDER BY " . (saColumnExists($db, 'invitaciones', 'creado_at') ? saSqlIdent('creado_at') . ' DESC, ' : '') . saSqlIdent('id') . ' DESC
             LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($delete && $invalidBefore > 0) {
        $stmt = $db->prepare('DELETE FROM ' . saSqlIdent('invitaciones') . ' WHERE ' . $where);
        $stmt->execute();
        $base['deleted'] = $stmt->rowCount();
        $base['invalid'] = (int)$db->query($countSql)->fetchColumn();
    }

    return $base;
}

function saDecryptMaybe(mixed $value): mixed {
    if (!is_string($value) || $value === '') return $value;
    try {
        $plain = Cipher::decrypt($value);
        return is_string($plain) ? $plain : $value;
    } catch (Throwable $e) {
        return $value;
    }
}

function saResidentPlainValue(array $row, string $field): string {
    $encKey = $field . '_enc';
    $source = array_key_exists($encKey, $row) && $row[$encKey] !== null && $row[$encKey] !== '' ? $row[$encKey] : ($row[$field] ?? '');
    return trim((string)saDecryptMaybe($source));
}

function saNormalizeContactKey(array $contact): string {
    $email = strtolower(trim((string)($contact['email'] ?? '')));
    if ($email !== '') return 'e:' . $email;
    $phone = preg_replace('/\D+/', '', (string)($contact['telefono'] ?? '')) ?: '';
    if ($phone !== '') return 'p:' . $phone;
    $name = strtolower(trim((string)($contact['nombre'] ?? '')));
    $rel = strtolower(trim((string)($contact['parentesco'] ?? '')));
    if ($name !== '') return 'n:' . $name . '|' . $rel;
    return '';
}

function saConvertLegacyFamilyContactsInCurrentDb(PDO $db, string $dbName, bool $apply): array {
    $base = [
        'database' => $dbName,
        'scanned' => 0,
        'candidates' => 0,
        'updated' => 0,
        'skipped_duplicates' => 0,
        'errors' => 0,
        'samples' => [],
        'skipped' => false,
    ];
    if (!saTableExists($db, 'residentes') || !saColumnExists($db, 'residentes', 'id') || !saColumnExists($db, 'residentes', 'contactos_json')) {
        $base['skipped'] = true;
        return $base;
    }

    $legacyFields = array_values(array_filter([
        'contacto_nombre', 'contacto_parentesco', 'contacto_telefono', 'contacto_telefono2', 'contacto_email', 'contacto_direccion'
    ], fn($field) => saColumnExists($db, 'residentes', $field)));
    if (!$legacyFields) {
        $base['skipped'] = true;
        return $base;
    }

    $selectCols = ['`id`'];
    foreach (['nombre', 'apellidos', 'contactos_json'] as $field) {
        if (saColumnExists($db, 'residentes', $field)) {
            $selectCols[] = saSqlIdent($field);
            if (saColumnExists($db, 'residentes', $field . '_enc')) $selectCols[] = saSqlIdent($field . '_enc');
        }
    }
    $whereParts = [];
    foreach ($legacyFields as $field) {
        $selectCols[] = saSqlIdent($field);
        $whereParts[] = saSqlIdent($field) . " IS NOT NULL AND " . saSqlIdent($field) . " <> ''";
        if (saColumnExists($db, 'residentes', $field . '_enc')) {
            $selectCols[] = saSqlIdent($field . '_enc');
            $whereParts[] = saSqlIdent($field . '_enc') . " IS NOT NULL AND " . saSqlIdent($field . '_enc') . " <> ''";
        }
    }
    $selectCols = array_values(array_unique($selectCols));
    $stmt = $db->query('SELECT ' . implode(', ', $selectCols) . ' FROM ' . saSqlIdent('residentes') . ' WHERE ' . implode(' OR ', $whereParts));

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $base['scanned']++;
        try {
            $legacy = [
                'nombre' => saResidentPlainValue($row, 'contacto_nombre'),
                'parentesco' => saResidentPlainValue($row, 'contacto_parentesco'),
                'telefono' => saNormalizePhoneMx(saResidentPlainValue($row, 'contacto_telefono')),
                'telefono2' => saNormalizePhoneMx(saResidentPlainValue($row, 'contacto_telefono2')),
                'email' => strtolower(saResidentPlainValue($row, 'contacto_email')),
                'direccion' => saResidentPlainValue($row, 'contacto_direccion'),
                'principal' => 1,
                'source' => 'legacy_contacto',
            ];
            $hasAny = trim(implode('', array_intersect_key($legacy, array_flip(['nombre','parentesco','telefono','telefono2','email','direccion'])))) !== '';
            if (!$hasAny) continue;

            $jsonPlain = saResidentPlainValue($row, 'contactos_json');
            $contacts = [];
            if ($jsonPlain !== '') {
                $decoded = json_decode($jsonPlain, true);
                if (is_array($decoded)) $contacts = array_values(array_filter($decoded, 'is_array'));
            }
            $legacyKey = saNormalizeContactKey($legacy);
            $duplicate = $legacyKey !== '' && array_reduce($contacts, fn($found, $contact) => $found || saNormalizeContactKey($contact) === $legacyKey, false);
            if ($duplicate) {
                $base['skipped_duplicates']++;
                continue;
            }

            if (!$apply) $base['candidates']++;
            if (!$apply && count($base['samples']) < 8) {
                $residentName = trim(saResidentPlainValue($row, 'nombre') . ' ' . saResidentPlainValue($row, 'apellidos'));
                $base['samples'][] = [
                    'id' => (int)$row['id'],
                    'residente' => $residentName,
                    'contacto' => $legacy['nombre'] ?: ($legacy['parentesco'] ?: 'Contacto'),
                    'destino' => $legacy['email'] ?: ($legacy['telefono'] ?: ($legacy['telefono2'] ?: 'sin email/teléfono')),
                ];
            }
            if (!$apply) continue;

            if (!$contacts) $legacy['principal'] = 1;
            else $legacy['principal'] = 0;
            $contacts[] = $legacy;
            $json = json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $write = EncryptionMap::encryptRow('residentes', ['contactos_json' => $json]);
            $sets = [];
            $params = [':id' => $row['id']];
            foreach ($write as $column => $value) {
                if (!saColumnExists($db, 'residentes', $column)) continue;
                $param = ':v_' . $column;
                $sets[] = saSqlIdent($column) . " = $param";
                $params[$param] = $value;
            }
            if (!$sets) continue;
            $upd = $db->prepare('UPDATE ' . saSqlIdent('residentes') . ' SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
            $upd->execute($params);
            if ($upd->rowCount() > 0) $base['updated']++;
        } catch (Throwable $e) {
            $base['errors']++;
        }
    }
    return $base;
}

function saNormalizePlainPhoneColumn(PDO $db, string $table, string $column): array {
    $label = "$table.$column";
    if (!saTableExists($db, $table) || !saColumnExists($db, $table, $column) || !saColumnExists($db, $table, 'id')) {
        return ['label' => $label, 'scanned' => 0, 'updated' => 0, 'skipped' => true];
    }

    $stmt = $db->query('SELECT `id`, ' . saSqlIdent($column) . ' AS phone FROM ' . saSqlIdent($table) . ' WHERE ' . saSqlIdent($column) . " IS NOT NULL AND " . saSqlIdent($column) . " <> ''");
    $upd = $db->prepare('UPDATE ' . saSqlIdent($table) . ' SET ' . saSqlIdent($column) . ' = :phone WHERE `id` = :id');
    $scanned = 0;
    $updated = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $scanned++;
        $current = (string)($row['phone'] ?? '');
        $next = saNormalizePhoneMx($current);
        if ($next !== $current) {
            $upd->execute([':phone' => $next, ':id' => $row['id']]);
            $updated += $upd->rowCount() > 0 ? 1 : 0;
        }
    }
    return ['label' => $label, 'scanned' => $scanned, 'updated' => $updated, 'skipped' => false];
}

function saNormalizeResidentPhoneFields(PDO $db): array {
    $label = 'residentes.contactos';
    if (!saTableExists($db, 'residentes') || !saColumnExists($db, 'residentes', 'id')) {
        return ['label' => $label, 'scanned' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => true];
    }

    $fields = array_values(array_filter(['contacto_telefono', 'contacto_telefono2', 'contactos_json'], fn($field) => saColumnExists($db, 'residentes', $field)));
    if (!$fields) return ['label' => $label, 'scanned' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => true];

    $selectCols = ['`id`'];
    $whereParts = [];
    foreach ($fields as $field) {
        $selectCols[] = saSqlIdent($field);
        if (saColumnExists($db, 'residentes', $field . '_enc')) {
            $selectCols[] = saSqlIdent($field . '_enc');
            $whereParts[] = saSqlIdent($field . '_enc') . " IS NOT NULL AND " . saSqlIdent($field . '_enc') . " <> ''";
        }
    }
    foreach ($fields as $field) {
        $whereParts[] = saSqlIdent($field) . " IS NOT NULL AND " . saSqlIdent($field) . " <> ''";
    }
    $stmt = $db->query('SELECT ' . implode(', ', $selectCols) . ' FROM `residentes` WHERE ' . implode(' OR ', $whereParts));

    $scanned = 0;
    $updated = 0;
    $errors = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $scanned++;
        try {
            $next = [];
            $changed = false;
            foreach ($fields as $field) {
                $raw = $row[$field] ?? null;
                $encKey = $field . '_enc';
                $encodedSource = array_key_exists($encKey, $row) && $row[$encKey] !== null && $row[$encKey] !== '' ? $row[$encKey] : $raw;
                $plainSource = is_string($encodedSource) ? Cipher::decrypt($encodedSource) : $encodedSource;
                $plainOriginal = is_string($raw) ? Cipher::decrypt($raw) : $raw;

                if ($field === 'contactos_json') {
                    $normalized = saNormalizeContactosJsonPhones($plainSource);
                    $normalizedOriginal = saNormalizeContactosJsonPhones($plainOriginal);
                } else {
                    $normalized = is_string($plainSource) ? saNormalizePhoneMx($plainSource) : $plainSource;
                    $normalizedOriginal = is_string($plainOriginal) ? saNormalizePhoneMx($plainOriginal) : $plainOriginal;
                }

                if ($normalized !== $plainSource || $normalizedOriginal !== $plainOriginal || $plainOriginal !== $normalized) {
                    $changed = true;
                    $next[$field] = $normalized;
                }
            }
            if (!$changed) continue;

            $write = EncryptionMap::encryptRow('residentes', $next);
            $sets = [];
            $params = [':id' => $row['id']];
            foreach ($write as $column => $value) {
                if (!saColumnExists($db, 'residentes', $column)) continue;
                $param = ':v_' . $column;
                $sets[] = saSqlIdent($column) . " = $param";
                $params[$param] = $value;
            }
            if (!$sets) continue;
            $upd = $db->prepare('UPDATE `residentes` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
            $upd->execute($params);
            $updated += $upd->rowCount() > 0 ? 1 : 0;
        } catch (Throwable $e) {
            $errors++;
        }
    }
    return ['label' => $label, 'scanned' => $scanned, 'updated' => $updated, 'errors' => $errors, 'skipped' => false];
}

function saReadDbHourlyLimit(PDO $db): array {
    $result = [
        'max_connections_per_hour' => null,
        'max_queries_per_hour' => null,
        'source' => 'fallback_hostinger_500',
        'grants_readable' => false,
        'error' => null,
    ];
    try {
        $grants = $db->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);
        $result['grants_readable'] = true;
        foreach ($grants as $grant) {
            if (preg_match('/MAX_CONNECTIONS_PER_HOUR\s+(\d+)/i', $grant, $m)) {
                $result['max_connections_per_hour'] = (int)$m[1];
                $result['source'] = 'mysql_grants';
            }
            if (preg_match('/MAX_QUERIES_PER_HOUR\s+(\d+)/i', $grant, $m)) {
                $result['max_queries_per_hour'] = (int)$m[1];
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }
    return $result;
}

function saReadDbProcesses(PDO $db): array {
    $out = ['total_visible' => null, 'current_user' => null, 'current_user_visible' => null, 'states' => [], 'error' => null];
    try {
        $current = (string)$db->query('SELECT CURRENT_USER()')->fetchColumn();
        $currentName = strtolower(strtok($current, '@') ?: DB_USER);
        $out['current_user'] = $current;
        $rows = $db->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
        $out['total_visible'] = count($rows);
        $mine = 0;
        $states = [];
        foreach ($rows as $row) {
            $user = strtolower((string)($row['User'] ?? ''));
            if ($user === $currentName || $user === strtolower(DB_USER)) $mine++;
            $command = (string)($row['Command'] ?? 'desconocido');
            $states[$command] = ($states[$command] ?? 0) + 1;
        }
        $out['current_user_visible'] = $mine;
        $out['states'] = $states;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function saReadDbSizes(PDO $db): array {
    $dbNames = [DB_MASTER_NAME];
    try {
        $tenantRows = $db->query("SELECT DISTINCT db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tenantRows as $name) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name)) $dbNames[] = $name;
        }
    } catch (Throwable $e) {}
    $dbNames = array_values(array_unique(array_filter($dbNames, fn($name) => is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name))));
    if (!$dbNames) return ['total_bytes' => 0, 'databases' => [], 'top_tables' => [], 'error' => null];

    $result = ['total_bytes' => 0, 'databases' => [], 'top_tables' => [], 'error' => null];
    try {
        $marks = implode(',', array_fill(0, count($dbNames), '?'));
        $stmt = $db->prepare(
            "SELECT TABLE_SCHEMA AS db_name,
                    COUNT(*) AS tables_count,
                    COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS bytes,
                    COALESCE(SUM(TABLE_ROWS), 0) AS approx_rows
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA IN ($marks)
             GROUP BY TABLE_SCHEMA
             ORDER BY bytes DESC"
        );
        $stmt->execute($dbNames);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $row['bytes'] = (int)$row['bytes'];
            $row['tables_count'] = (int)$row['tables_count'];
            $row['approx_rows'] = (int)$row['approx_rows'];
            $result['total_bytes'] += $row['bytes'];
            $result['databases'][] = $row;
        }

        $stmt = $db->prepare(
            "SELECT TABLE_SCHEMA AS db_name,
                    TABLE_NAME AS table_name,
                    COALESCE(DATA_LENGTH + INDEX_LENGTH, 0) AS bytes,
                    COALESCE(TABLE_ROWS, 0) AS approx_rows
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA IN ($marks)
             ORDER BY bytes DESC
             LIMIT 10"
        );
        $stmt->execute($dbNames);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['bytes'] = (int)$row['bytes'];
            $row['approx_rows'] = (int)$row['approx_rows'];
            $result['top_tables'][] = $row;
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }
    return $result;
}

function saReadStorageMetrics(): array {
    $root = dirname(__DIR__);
    $paths = [
        'geriapp_v9' => $root,
        'uploads' => $root . '/uploads',
        'logs' => $root . '/logs',
        'backups' => $root . '/backups',
        'public' => $root . '/public',
    ];
    $items = [];
    foreach ($paths as $key => $path) {
        $items[$key] = saDirStats($path, ['node_modules', '.git'], $key === 'uploads' ? ['_resource_metrics'] : []);
    }

    $uploadTop = [];
    $uploadsPath = $root . '/uploads';
    if (is_dir($uploadsPath)) {
        foreach ((@scandir($uploadsPath) ?: []) as $name) {
            if ($name === '.' || $name === '..' || $name === '_resource_metrics') continue;
            $path = $uploadsPath . '/' . $name;
            if (is_dir($path)) {
                $stat = saDirStats($path, [], []);
                $stat['name'] = $name;
                $uploadTop[] = $stat;
            }
        }
        usort($uploadTop, fn($a, $b) => ($b['bytes'] ?? 0) <=> ($a['bytes'] ?? 0));
        $uploadTop = array_slice($uploadTop, 0, 10);
    }

    return [
        'root' => $root,
        'disk_total' => @disk_total_space($root) ?: null,
        'disk_free' => @disk_free_space($root) ?: null,
        'paths' => $items,
        'uploads_top' => $uploadTop,
    ];
}

function saRecordResourceSnapshot(string $generatedAt, array $connMetrics, array $dbSizes, array $storage): void {
    $dir = saResourceMetricDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return;
    $hour = date('YmdH');
    $uploadsBytes = (int)($storage['paths']['uploads']['bytes'] ?? 0);
    $appBytes = (int)($storage['paths']['geriapp_v9']['bytes'] ?? 0);
    $snapshot = [
        'hour' => $hour,
        'generated_at' => $generatedAt,
        'connections' => (int)($connMetrics['total'] ?? 0),
        'by_user' => is_array($connMetrics['by_user'] ?? null) ? $connMetrics['by_user'] : [],
        'db_bytes' => (int)($dbSizes['total_bytes'] ?? 0),
        'uploads_bytes' => $uploadsBytes,
        'app_bytes' => $appBytes,
        'disk_free' => isset($storage['disk_free']) ? (int)$storage['disk_free'] : null,
        'disk_total' => isset($storage['disk_total']) ? (int)$storage['disk_total'] : null,
    ];
    @file_put_contents($dir . "/resource_snapshot_{$hour}.json", json_encode($snapshot, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function saReadResourceHistory(int $hours = 24): array {
    $hours = max(1, min(168, $hours));
    $dir = saResourceMetricDir();
    $history = [];
    for ($i = $hours - 1; $i >= 0; $i--) {
        $hour = date('YmdH', time() - ($i * 3600));
        $file = $dir . "/resource_snapshot_{$hour}.json";
        $row = is_readable($file) ? json_decode((string)@file_get_contents($file), true) : null;
        if (!is_array($row)) {
            $row = [
                'hour' => $hour,
                'generated_at' => null,
                'connections' => 0,
                'by_user' => [],
                'db_bytes' => null,
                'uploads_bytes' => null,
                'app_bytes' => null,
                'disk_free' => null,
                'disk_total' => null,
            ];
        }
        $history[] = $row;
    }
    return $history;
}

function saResourceMetricDir(): string {
    return dirname(__DIR__) . '/uploads/_resource_metrics';
}

function saDirStats(string $path, array $skipDirs = [], array $skipNames = []): array {
    $stats = ['path' => $path, 'exists' => is_dir($path), 'bytes' => 0, 'files' => 0, 'dirs' => 0, 'error' => null];
    if (!$stats['exists']) return $stats;
    $skipDirs = array_flip($skipDirs);
    $skipNames = array_flip($skipNames);
    $walk = function (string $dir) use (&$walk, &$stats, $skipDirs, $skipNames): void {
        $entries = @scandir($dir);
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || isset($skipNames[$entry])) continue;
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                if (isset($skipDirs[$entry])) continue;
                $stats['dirs']++;
                $walk($full);
            } elseif (is_file($full)) {
                $stats['files']++;
                $stats['bytes'] += (int)@filesize($full);
            }
        }
    };

    try {
        $walk($path);
    } catch (Throwable $e) {
        $stats['error'] = $e->getMessage();
    }
    return $stats;
}

function saClearForeignKeyReferences(PDO $db, string $referencedTable, string $referencedColumn, int $value): array {
    $stmt = $db->prepare(
        "SELECT k.TABLE_NAME, k.COLUMN_NAME, rc.DELETE_RULE, c.IS_NULLABLE
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
           ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         JOIN information_schema.COLUMNS c
           ON c.TABLE_SCHEMA = k.TABLE_SCHEMA
          AND c.TABLE_NAME = k.TABLE_NAME
          AND c.COLUMN_NAME = k.COLUMN_NAME
         WHERE k.CONSTRAINT_SCHEMA = DATABASE()
           AND k.REFERENCED_TABLE_NAME = ?
           AND k.REFERENCED_COLUMN_NAME = ?
         ORDER BY k.TABLE_NAME, k.COLUMN_NAME"
    );
    $stmt->execute([$referencedTable, $referencedColumn]);

    $cleanup = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ref) {
        $table = (string)$ref['TABLE_NAME'];
        $column = (string)$ref['COLUMN_NAME'];
        if ($table === $referencedTable) continue;

        $tableSql = saSqlIdent($table);
        $columnSql = saSqlIdent($column);
        $key = 'fk_' . $table . '_' . $column;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM $tableSql WHERE $columnSql = ?");
        $countStmt->execute([$value]);
        $rows = (int)$countStmt->fetchColumn();
        if ($rows <= 0) continue;

        $deleteRule = strtoupper((string)$ref['DELETE_RULE']);
        if (in_array($deleteRule, ['CASCADE', 'SET NULL'], true)) {
            $cleanup[$key] = ['rule' => strtolower($deleteRule), 'rows' => $rows];
            continue;
        }

        if (($ref['IS_NULLABLE'] ?? '') === 'YES') {
            $upd = $db->prepare("UPDATE $tableSql SET $columnSql = NULL WHERE $columnSql = ?");
            $upd->execute([$value]);
            $cleanup[$key] = ['rule' => 'set_null_before_delete', 'rows' => $upd->rowCount()];
            continue;
        }

        $del = $db->prepare("DELETE FROM $tableSql WHERE $columnSql = ?");
        $del->execute([$value]);
        $cleanup[$key] = ['rule' => 'delete_before_delete', 'rows' => $del->rowCount()];
    }

    return $cleanup;
}

/**
 * §5 Cifrado — Obtiene conexiones a master + todas las BDs de tenant.
 * @return PDO[]
 */
function _enc_getAllDbs(): array {
    $dbs   = [Database::getMaster()];
    $master = Database::getMaster();
    $stmt  = $master->query("SELECT id, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inst) {
        try {
            $dbs[] = Database::getTenant((int)$inst['id']);
        } catch (Throwable $e) {
            // Tenant DB might not exist yet — skip
        }
    }
    return $dbs;
}

/**
 * Sincroniza una BD completa de source a target (DROP + CREATE + INSERT).
 * Usa mysqldump-style: lee estructura y datos de source, recrea en target.
 */
function syncDatabase(array $src, array $tgt, string $dbName, bool $createIfMissing = false, ?string $destDbName = null, bool $structureOnly = false): array {
    // Resolve actual DB names: for master use profile's db name, for tenant use as-is
    $srcDbName = $dbName;
    $tgtDbName = $destDbName ?: $dbName;

    // If no custom dest name, map master DB names between profiles
    if (!$destDbName) {
        if ($dbName === $src['db']) {
            $tgtDbName = $tgt['db'];
        } elseif ($dbName === $tgt['db']) {
            $srcDbName = $src['db'];
        }
    }

    $srcPdo = new PDO(
        "mysql:host={$src['host']};port={$src['port']};dbname={$srcDbName};charset={$src['charset']}",
        $src['user'], $src['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create target DB if option enabled
    $tgtAdmin = new PDO(
        "mysql:host={$tgt['host']};port={$tgt['port']};charset={$tgt['charset']}",
        $tgt['user'], $tgt['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($createIfMissing) {
        $tgtAdmin->exec("CREATE DATABASE IF NOT EXISTS `{$tgtDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } else {
        // Check if target DB exists
        $check = $tgtAdmin->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $check->execute([$tgtDbName]);
        if (!$check->fetchColumn()) {
            throw new Exception("La BD '$tgtDbName' no existe en el destino. Activa 'Crear BD si no existe' para crearla automáticamente.");
        }
    }

    $tgtPdo = new PDO(
        "mysql:host={$tgt['host']};port={$tgt['port']};dbname={$tgtDbName};charset={$tgt['charset']}",
        $tgt['user'], $tgt['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $tgtPdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Get all tables from source
    $tables = $srcPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
    $totalTables = 0;
    $totalRows = 0;

    foreach ($tables as $t) {
        $table = $t[0];
        $totalTables++;

        // Get CREATE TABLE from source
        $create = $srcPdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $createSql = $create[1];

        // Normalize collations unsupported by target (e.g. MariaDB 11 → XAMPP)
        $createSql = preg_replace(
            '/COLLATE[= ]utf8mb4_uca1400_a[is]_c[is]/i',
            'COLLATE=utf8mb4_unicode_ci',
            $createSql
        );
        $createSql = preg_replace(
            '/CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_a[is]_c[is]/i',
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $createSql
        );

        // Drop and recreate in target
        $tgtPdo->exec("DROP TABLE IF EXISTS `{$table}`");
        $tgtPdo->exec($createSql);

        // Copy data in chunks (skip if structure-only mode)
        if ($structureOnly) continue;
        $count = (int)$srcPdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($count === 0) continue;

        $chunkSize = 500;
        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $rows = $srcPdo->query("SELECT * FROM `{$table}` LIMIT {$chunkSize} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) break;

            $cols = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $cols) . '`';
            $placeholders = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholders));

            $sql = "INSERT INTO `{$table}` ({$colList}) VALUES {$allPlaceholders}";
            $vals = [];
            foreach ($rows as $row) {
                foreach ($cols as $col) {
                    $vals[] = $row[$col];
                }
            }
            $tgtPdo->prepare($sql)->execute($vals);
            $totalRows += count($rows);
        }
    }

    $tgtPdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    return ['tables' => $totalTables, 'rows' => $totalRows];
}
