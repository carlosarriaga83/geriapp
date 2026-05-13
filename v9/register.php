<?php
require_once 'conf/config.php';

require_once 'db/Database.php';
require_once 'db/models/Invitacion.php';
require_once 'db/models/GeriappEventInvitation.php';

// ── Resolver invitación (GET o POST) ─────────────────────────────────────────
$invToken = trim($_GET['inv'] ?? $_POST['inv_token'] ?? '');
$eventToken = trim($_GET['event'] ?? $_POST['event_token'] ?? '');
$inv      = null;
$eventInv = null;
$invError = '';

// Prefill desde la landing (web/start.php) — solo lectura, no se persiste.
// Se usa únicamente para precargar campos del formulario en GET inicial.
$prefill = [
    'nombre'      => trim((string)($_GET['nombre']      ?? '')),
    'apellido'    => trim((string)($_GET['apellido']    ?? '')),
    'email'       => trim((string)($_GET['email']       ?? '')),
    'telefono'    => trim((string)($_GET['telefono']    ?? '')),
    'institucion' => trim((string)($_GET['institucion'] ?? '')),
];

if ($invToken) {
    $inv = Invitacion::getByToken($invToken);
    if (!$inv) {
        $invError = 'El enlace de invitación no es válido.';
        $inv      = null;
    } elseif ($inv['estado'] !== 'pendiente') {
        $invError = $inv['estado'] === 'aceptada'
            ? 'Esta invitación ya fue utilizada.'
            : 'Esta invitación ya no está activa.';
        $inv = null;
    }
} elseif ($eventToken) {
    $eventInv = GeriappEventInvitation::getByToken($eventToken);
    if ($eventInv && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        GeriappEventInvitation::recordScan($eventToken, [
            'estado' => $eventInv['estado'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
        ]);
    }
    if (!$eventInv) {
        $invError = 'El QR de registro no es válido.';
        $eventInv = null;
    } elseif (($eventInv['estado'] ?? '') !== 'activa') {
        $invError = 'Este QR de registro ya no está activo.';
        $eventInv = null;
    } elseif (strtotime($eventInv['expires_at']) < time()) {
        $invError = 'Este QR de registro ha expirado.';
        $eventInv = null;
    } elseif ((int)($eventInv['plan_activo'] ?? 0) !== 1) {
        $invError = 'El paquete asociado a este QR ya no está disponible.';
        $eventInv = null;
    }
} else {
    // §3.6 Registro cerrado: invitación obligatoria
    $invError = 'Se requiere un enlace de invitación para registrarse. Contacta al administrador de tu institución.';
}

// Ya autenticado sin invitación → redirigir; con invitación, dejamos pasar
// para mostrar el flujo de "aceptar invitación como usuario existente".
if (!empty($_SESSION['user_id']) && !$inv && !$eventInv) {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

$rolLabels = [
    'admin'     => 'Administrador',
    'medico'    => 'Médico/a',
    'enfermero' => 'Cuidador/a',
    'cuidador'  => 'Cuidador/a',
    'familiar'  => 'Familiar',
];

// ── Detectar usuario existente con el mismo correo de la invitación ──────────
// Esto permite vincular un usuario ya registrado a una nueva institución sin
// pedirle que vuelva a crear contraseña ni que rellene el formulario completo.
$existingInvUser = null;
$mode            = 'register'; // 'register' | 'accept_logged_in' | 'accept_login_required'
                               // 'accept_email_mismatch' | 'accept_already_member'
$invInstNombre   = '';
if ($inv) {
    $dbBoot = Database::getInstance();
    $stInst = $dbBoot->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
    $stInst->execute([(int)$inv['institucion_id']]);
    $invInstNombre = (string)($stInst->fetchColumn() ?: '');

    $stExist = $dbBoot->prepare("SELECT id, nombre, email, password_hash, estado FROM usuarios WHERE email = ? LIMIT 1");
    $stExist->execute([$inv['email']]);
    $existingInvUser = $stExist->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existingInvUser) {
        // ¿Ya está vinculado a esta institución?
        $stPivot = $dbBoot->prepare(
            "SELECT 1 FROM usuario_instituciones
             WHERE usuario_id = ? AND institucion_id = ? AND estado = 'activo' LIMIT 1"
        );
        $stPivot->execute([(int)$existingInvUser['id'], (int)$inv['institucion_id']]);
        $alreadyMember = (bool)$stPivot->fetchColumn();

        if ($alreadyMember) {
            $mode = 'accept_already_member';
        } elseif (!empty($_SESSION['user_id'])) {
            // Hay sesión: comparar email
            $sessEmail = strtolower(trim($_SESSION['user_email'] ?? ''));
            if ($sessEmail === strtolower($existingInvUser['email'])) {
                $mode = 'accept_logged_in';
            } else {
                $mode = 'accept_email_mismatch';
            }
        } else {
            $mode = 'accept_login_required';
        }
    } elseif (!empty($_SESSION['user_id'])) {
        // Sesión activa pero la invitación es para un correo no registrado.
        // Se trata como mismatch: no pueden aceptarla con su sesión actual.
        $mode = 'accept_email_mismatch';
    }
}

$regError  = '';
$regInfo   = '';
$errorField = '';

if (isset($_GET['csrf_expired'])) { $regInfo = 'Tu sesión expiró. Se ha renovado el formulario automáticamente.'; }

function register_compose_phone(string $countryCode, string $phoneInput): string
{
    $countryDigits = preg_replace('/\D+/', '', $countryCode) ?: '52';
    $raw = trim($phoneInput);
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    if (str_starts_with($raw, '+') || str_starts_with($digits, $countryDigits)) return '+' . $digits;
    return '+' . $countryDigits . $digits;
}

function register_phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function register_phone_local_from_full(string $phone, string $countryCode): string
{
    $phoneDigits = register_phone_digits($phone);
    $countryDigits = register_phone_digits($countryCode) ?: '52';
    if ($phoneDigits !== '' && str_starts_with($phoneDigits, $countryDigits)) {
        return substr($phoneDigits, strlen($countryDigits));
    }
    return $phoneDigits;
}

function register_phone_exists(PDO $db, string $phone): bool
{
    $targetDigits = register_phone_digits($phone);
    if (strlen($targetDigits) < 8) return false;
    $stmt = $db->query("SELECT telefono FROM usuarios WHERE telefono IS NOT NULL AND telefono <> ''");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storedDigits = register_phone_digits((string)($row['telefono'] ?? ''));
        if ($storedDigits === '') continue;
        if ($storedDigits === $targetDigits) return true;
        if (strlen($storedDigits) >= 8 && str_ends_with($targetDigits, $storedDigits)) return true;
        if (strlen($targetDigits) >= 8 && str_ends_with($storedDigits, $targetDigits)) return true;
    }
    return false;
}

function register_whatsapp_config(?int $institucionId): array
{
    require_once __DIR__ . '/db/models/Configuracion.php';
    if ($institucionId && $institucionId > 0) {
        return Configuracion::getOrCreate($institucionId);
    }
    $defaultsPath = __DIR__ . '/conf/institucion_defaults.php';
    if (is_file($defaultsPath)) {
        $defaults = require $defaultsPath;
        return $defaults['integraciones'] ?? [];
    }
    return [];
}

function register_check_whatsapp(string $phone, ?int $institucionId): array
{
    $digits = register_phone_digits($phone);
    if (strlen($digits) < 8) {
        return ['checked' => false, 'registered' => null, 'error' => null, 'cached' => false];
    }

    $cacheKey = '_reg_wa_check_' . md5((string)($institucionId ?: 0) . '|' . $digits);
    $entry = $_SESSION[$cacheKey] ?? null;
    if (is_array($entry) && (time() - (int)($entry['at'] ?? 0)) < 86400) {
        return [
            'checked' => true,
            'registered' => $entry['registered'],
            'error' => null,
            'cached' => true,
        ];
    }

    try {
        require_once __DIR__ . '/db/models/WaSenderAPI.php';
        $cfg = register_whatsapp_config($institucionId);
        if (empty($cfg['wa_api_key']) || (int)($cfg['wa_activo'] ?? 1) !== 1) {
            return ['checked' => false, 'registered' => null, 'error' => 'WhatsApp no configurado', 'cached' => false];
        }
        $wa = new WaSenderAPI((string)$cfg['wa_api_key']);
        $registered = $wa->isRegistered($phone);
        if ($registered !== null) {
            $_SESSION[$cacheKey] = ['registered' => $registered, 'at' => time()];
        }
        return ['checked' => true, 'registered' => $registered, 'error' => null, 'cached' => false];
    } catch (\Throwable $e) {
        return ['checked' => true, 'registered' => null, 'error' => 'No se pudo verificar WhatsApp', 'cached' => false];
    }
}

if (($_GET['ajax'] ?? '') === 'check_contact') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$inv && !$eventInv) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invitación requerida']);
        exit;
    }
    $dbCheck = Database::getInstance();
    $emailCheck = strtolower(trim((string)($_GET['email'] ?? '')));
    $countryCheck = trim((string)($_GET['country_code'] ?? '+52')) ?: '+52';
    $phoneCheck = register_compose_phone($countryCheck, (string)($_GET['telefono'] ?? ''));
    $waInstId = $inv ? (int)$inv['institucion_id'] : null;

    $emailExists = false;
    if ($emailCheck !== '' && filter_var($emailCheck, FILTER_VALIDATE_EMAIL)) {
        $stmtEmail = $dbCheck->prepare("SELECT 1 FROM usuarios WHERE email = ? LIMIT 1");
        $stmtEmail->execute([$emailCheck]);
        $emailExists = (bool)$stmtEmail->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'email' => ['checked' => $emailCheck !== '', 'exists' => $emailExists],
        'telefono' => [
            'checked' => register_phone_digits($phoneCheck) !== '',
            'exists' => register_phone_exists($dbCheck, $phoneCheck),
            'normalized' => $phoneCheck,
        ],
        'whatsapp' => register_check_whatsapp($phoneCheck, $waInstId),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Helper: vincula un usuario existente a la institución de la invitación,
 * marca la invitación como aceptada, sincroniza residentes y redirige.
 * Usado por las acciones `accept_existing` y `login_and_accept`.
 */
$linkExistingUserAndRedirect = function(int $userId, array $invRow, string $token) {
    $db        = Database::getInstance();
    $instId    = (int)$invRow['institucion_id'];
    $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
    $rolPivot  = in_array($invRow['rol'], $rolesValidos, true) ? $invRow['rol'] : 'enfermero';

    $requiresFamiliarSelfPay = false;
    // ── F6: cupo de familiares ────────────────────────────────────────────
    if ($rolPivot === 'familiar' && $instId > 0) {
        require_once __DIR__ . '/db/models/Seats.php';
        try {
            Seats::assertCanAddFamiliar($instId);
        } catch (SeatLimitException $e) {
            $requiresFamiliarSelfPay = true;
        }
    }

    $db->prepare(
        "INSERT INTO usuario_instituciones (usuario_id, institucion_id, rol, estado)
         VALUES (?, ?, ?, 'activo')
         ON DUPLICATE KEY UPDATE rol = VALUES(rol), estado = 'activo'"
    )->execute([$userId, $instId, $rolPivot]);

    Invitacion::markAccepted($token);

    if (!empty($invRow['residente_ids'])) {
        $resIds = json_decode($invRow['residente_ids'], true);
        if (is_array($resIds) && count($resIds)) {
            require_once __DIR__ . '/db/models/UsuarioResidente.php';
            UsuarioResidente::sync($userId, $resIds, $instId);
        }
    }

    $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
                  VALUES (?,?,'inv_accept_existing','Auth',?,'ok')")
       ->execute([$userId, $instId, $_SERVER['REMOTE_ADDR'] ?? null]);

    // Limpia lista cacheada de instituciones para forzar recálculo en select_institucion
    unset($_SESSION['pending_instituciones']);
    $_SESSION['user_multi_inst'] = true;

    if ($requiresFamiliarSelfPay) {
        $_SESSION['familiar_extra_seat_required'] = true;
        $_SESSION['user_institucion_id'] = $instId;
        $inst = Institucion::getById($instId) ?: [];
        $_SESSION['user_institucion_nombre'] = $inst['nombre'] ?? ($_SESSION['user_institucion_nombre'] ?? '');
        header('Location: ' . BASE_URL . '/billing.php?extra_familiar=1');
        exit;
    }

    // Redirige al selector de institución para que el usuario elija con cuál entrar.
    header('Location: ' . BASE_URL . '/select_institucion.php?linked=1');
    exit;
};

/**
 * Helper: provisiona una nueva institución para el invitado (modo evento).
 * - Crea fila en `instituciones` con estado='trial' si dias_prueba > 0, y trial_ends_at.
 * - Crea la BD tenant.
 * - Siembra `configuracion` con defaults.
 * - Vincula al usuario como administrador (rol=admin) en `usuario_instituciones`.
 *
 * Retorna el institucion_id nuevo o lanza Exception en error.
 */
$provisionNewInstitutionForInvitee = function(string $instNombre, int $userId, ?int $diasPrueba, string $emailAdmin, ?int $planId = null, string $source = 'self_signup_qr', ?string $telefonoAdmin = null): int {
    require_once __DIR__ . '/db/models/Configuracion.php';
    $db = Database::getInstance();

    $estadoInicial = ($diasPrueba !== null && $diasPrueba > 0) ? 'trial' : 'activa';
    $trialEnd      = ($diasPrueba !== null && $diasPrueba > 0)
        ? date('Y-m-d', strtotime("+{$diasPrueba} days"))
        : null;
    $tz = date_default_timezone_get() ?: 'America/Mexico_City';

    $db->prepare(
        "INSERT INTO instituciones
            (nombre, email_admin, telefono, timezone, plan_id, estado, trial_ends_at, creado_por)
         VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$instNombre, $emailAdmin, $telefonoAdmin ?: null, $tz, $planId ?: null, $estadoInicial, $trialEnd, $source]);
    $newInstId = (int)$db->lastInsertId();

    // Crear BD tenant (best-effort: si falla, dejamos la fila pero registramos el error).
    try {
        $dbName = Database::tenantDbName($newInstId);
        Database::createTenantDB($dbName);
        $db->prepare("UPDATE instituciones SET db_name = ? WHERE id = ?")->execute([$dbName, $newInstId]);
        Database::clearTenantCache($newInstId);
    } catch (\Throwable $e) {
        error_log("[GeriApp] provisionNewInstitution: createTenantDB falló para inst $newInstId: " . $e->getMessage());
    }

    // Sembrar configuración con defaults.
    try {
        $defaultsPath = __DIR__ . '/conf/institucion_defaults.php';
        if (is_file($defaultsPath)) {
            $defaults = require $defaultsPath;
            $cfgFlat = array_merge(
                $defaults['general']        ?? [],
                $defaults['integraciones']  ?? [],
                $defaults['seguridad']      ?? [],
                $defaults['notificaciones'] ?? [],
                isset($defaults['roles_permisos'])
                    ? ['roles_permisos' => json_encode($defaults['roles_permisos'])]
                    : []
            );
            $cfgFlat['inst_nombre']    = $instNombre;
            $cfgFlat['legal_cc_email'] = $cfgFlat['legal_cc_email'] ?? $emailAdmin;
            $cfgFlat['timezone']       = $tz;
            Configuracion::upsert($newInstId, $cfgFlat);
        } else {
            Configuracion::create($newInstId);
        }
    } catch (\Throwable $e) {
        error_log("[GeriApp] provisionNewInstitution: seed config falló para inst $newInstId: " . $e->getMessage());
    }

    // Vincular usuario como administrador.
    $db->prepare(
        "INSERT INTO usuario_instituciones (usuario_id, institucion_id, rol, estado)
         VALUES (?, ?, 'admin', 'activo')
         ON DUPLICATE KEY UPDATE rol = 'admin', estado = 'activo'"
    )->execute([$userId, $newInstId]);

    // Log de auditoría.
    $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
                  VALUES (?,?,'inst_self_provision','Auth',?,'ok')")
       ->execute([$userId, $newInstId, $_SERVER['REMOTE_ADDR'] ?? null]);

    return $newInstId;
};

$ensureRegistrationSubscription = function(int $userId, int $institucionId, ?int $planId, ?int $diasPrueba, bool $solicitaTarjeta): void {
    if ($userId <= 0 || $institucionId <= 0 || !$planId) return;
    $db = Database::getInstance();
    $trialDays = max(0, (int)($diasPrueba ?? 0));
    $trialEnd = $trialDays > 0 ? date('Y-m-d 23:59:59', strtotime("+{$trialDays} days")) : null;
    $estado = $trialDays > 0 ? 'trial' : 'incompleta';
    $metadata = json_encode([
        'source' => 'event_qr_registration',
        'solicita_tarjeta_registro' => $solicitaTarjeta ? 1 : 0,
    ], JSON_UNESCAPED_SLASHES);

    $stmt = $db->prepare("SELECT id FROM suscripciones WHERE owner_user_id = ? AND stripe_subscription_id IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $suscId = (int)($stmt->fetchColumn() ?: 0);
    if ($suscId > 0) {
        $db->prepare(
            "UPDATE suscripciones
             SET plan_id=?, moneda='MXN', periodo='mensual', estado=?, trial_ends_at=?, metadata=?
             WHERE id=?"
        )->execute([$planId, $estado, $trialEnd, $metadata, $suscId]);
    } else {
        $db->prepare(
            "INSERT INTO suscripciones (owner_user_id, plan_id, moneda, periodo, estado, trial_ends_at, metadata)
             VALUES (?, ?, 'MXN', 'mensual', ?, ?, ?)"
        )->execute([$userId, $planId, $estado, $trialEnd, $metadata]);
        $suscId = (int)$db->lastInsertId();
    }
    if ($suscId > 0) {
        $db->prepare(
            "INSERT IGNORE INTO suscripcion_instituciones (suscripcion_id, institucion_id)
             VALUES (?, ?)"
        )->execute([$suscId, $institucionId]);
    }
};

$loginNewAdminAndRedirectToBilling = function(int $userId, string $nombre, string $email, int $institucionId, string $institucionNombre, int $planId): void {
    $db = Database::getInstance();
    session_regenerate_id(true);
    $_SESSION['user_id']          = $userId;
    $_SESSION['user_nombre']      = $nombre;
    $_SESSION['user_email']       = $email;
    $_SESSION['user_estado']      = 'activo';
    $_SESSION['user_avatar_path'] = '';
    $_SESSION['user_rol']         = 'admin';
    $_SESSION['user_institucion_id'] = $institucionId;
    $_SESSION['user_institucion_nombre'] = $institucionNombre;
    $_SESSION['user_multi_inst']  = false;
    $_SESSION['billing_registration_card_required'] = true;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    try {
        $db->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,1)")->execute([$email, $ip]);
        $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$userId]);
        $db->prepare("INSERT INTO sesiones_activas (usuario_id, session_id, ip, user_agent) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), ultimo_acceso = NOW()")
           ->execute([$userId, session_id(), $ip, $ua]);
    } catch (\Throwable $e) { /* best effort */ }

    header('Location: ' . BASE_URL . '/billing.php?register_checkout=1&plan_id=' . $planId . '&institucion_id=' . $institucionId);
    exit;
};

$loginFamiliarAndRedirectToBilling = function(int $userId, string $nombre, string $email, int $institucionId): void {
    $db = Database::getInstance();
    $inst = Institucion::getById($institucionId) ?: [];
    session_regenerate_id(true);
    $_SESSION['user_id']          = $userId;
    $_SESSION['user_nombre']      = $nombre;
    $_SESSION['user_email']       = $email;
    $_SESSION['user_estado']      = 'activo';
    $_SESSION['user_avatar_path'] = '';
    $_SESSION['user_rol']         = 'familiar';
    $_SESSION['user_institucion_id'] = $institucionId;
    $_SESSION['user_institucion_nombre'] = $inst['nombre'] ?? '';
    $_SESSION['user_multi_inst']  = false;
    $_SESSION['familiar_extra_seat_required'] = true;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    try {
        $db->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,1)")->execute([$email, $ip]);
        $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$userId]);
        $db->prepare("INSERT INTO sesiones_activas (usuario_id, session_id, ip, user_agent) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), ultimo_acceso = NOW()")
           ->execute([$userId, session_id(), $ip, $ua]);
    } catch (\Throwable $e) { /* best effort */ }

    header('Location: ' . BASE_URL . '/billing.php?extra_familiar=1');
    exit;
};

$sendEventWelcomeEmail = function(int $institucionId, string $instNombre, string $emailAdmin, string $nombreAdmin, ?int $trialDays, ?string $telefonoAdmin = null): void {
        try {
                require_once __DIR__ . '/db/models/Mailer.php';
        require_once __DIR__ . '/db/models/WaSenderAPI.php';
                require_once __DIR__ . '/db/models/Configuracion.php';
                $cfg = Configuracion::getOrCreate($institucionId);

                $baseUrl = function_exists('app_public_url') ? rtrim(app_public_url(), '/') : rtrim(BASE_URL, '/');
                $loginUrl = $baseUrl . '/index.php';
                $instEsc = htmlspecialchars($instNombre, ENT_QUOTES, 'UTF-8');
                $adminEsc = htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8');
                $trialText = $trialDays && $trialDays > 0
                        ? "Tu prueba de {$trialDays} dias inicia desde este registro."
                        : 'Tu institucion ya esta lista para configurarse.';
                $phoneRow = $telefonoAdmin
                        ? '<tr><td style="padding:6px 10px;color:#64748b">Telefono</td><td style="padding:6px 10px;font-weight:600">' . htmlspecialchars($telefonoAdmin, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                        : '';

                $htmlBody = <<<MAIL
<div style="font-family:'Segoe UI',Arial,sans-serif;max-width:620px;margin:0 auto;padding:24px;color:#1e293b">
    <div style="border:1px solid #dbe7ec;border-radius:14px;overflow:hidden;background:#ffffff">
        <div style="padding:24px 24px 18px;border-bottom:1px solid #e2e8f0;background:#f5fbfd">
            <div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#178391;margin-bottom:8px">GeriApp</div>
            <h1 style="margin:0;font-size:24px;line-height:1.25;color:#12384a">Bienvenido, {$adminEsc}</h1>
            <p style="margin:10px 0 0;font-size:14px;line-height:1.6;color:#475569">La institucion <strong>{$instEsc}</strong> fue creada correctamente desde el QR de evento.</p>
        </div>
        <div style="padding:22px 24px">
            <table style="width:100%;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;font-size:14px;margin-bottom:18px">
                <tr><td style="padding:6px 10px;color:#64748b;width:130px">Institucion</td><td style="padding:6px 10px;font-weight:600">{$instEsc}</td></tr>
                <tr><td style="padding:6px 10px;color:#64748b">Email admin</td><td style="padding:6px 10px;font-weight:600">{$emailAdmin}</td></tr>
                {$phoneRow}
            </table>
            <p style="font-size:14px;line-height:1.7;color:#334155;margin:0 0 16px">{$trialText} Ingresa con el correo y la contrasena que acabas de crear para revisar tu panel.</p>
            <p style="text-align:center;margin:24px 0">
                <a href="{$loginUrl}" style="display:inline-block;background:#178391;color:#fff;text-decoration:none;font-weight:700;padding:13px 28px;border-radius:10px">Entrar a GeriApp</a>
            </p>
            <p style="font-size:12px;color:#64748b;line-height:1.6;margin:0">Si el boton no funciona, copia este enlace:<br><a href="{$loginUrl}" style="color:#178391">{$loginUrl}</a></p>
        </div>
    </div>
</div>
MAIL;

        if (!empty($cfg['smtp_host']) && !empty($cfg['smtp_from_email'])) {
            Mailer::fromConfig($institucionId)->send($emailAdmin, "GeriApp - institucion {$instNombre} creada", $htmlBody);
        } else {
            error_log("[GeriApp] QR Evento bienvenida: SMTP incompleto para inst {$institucionId}");
        }

        if ($telefonoAdmin && !empty($cfg['wa_api_key']) && (int)($cfg['wa_activo'] ?? 1) === 1) {
            $waMsg = "Hola {$nombreAdmin}, bienvenido a GeriApp.\n\n"
                . "Tu institucion {$instNombre} ya esta lista.\n"
                . "Entra aqui:\n{$loginUrl}";
            $waResult = WaSenderAPI::fromConfig($institucionId)->sendText($telefonoAdmin, $waMsg);
            if (empty($waResult['ok'])) {
                error_log('[GeriApp] QR Evento bienvenida WhatsApp error: ' . ($waResult['error'] ?? 'fallo desconocido'));
            }
        } elseif ($telefonoAdmin) {
            error_log("[GeriApp] QR Evento bienvenida: WhatsApp no configurado para inst {$institucionId}");
        }
        } catch (\Throwable $e) {
                error_log('[GeriApp] QR Evento bienvenida error: ' . $e->getMessage());
        }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // §1.9 Validar CSRF
    if (!csrf_validate($_POST['_csrf'] ?? '')) {
        header('Location: ' . $_SERVER['REQUEST_URI'] . (str_contains($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'csrf_expired=1');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ── Acción: aceptar invitación con sesión activa (1 click) ───────────────
    if ($action === 'accept_existing' && $inv && $mode === 'accept_logged_in' && $existingInvUser) {
        $linkExistingUserAndRedirect((int)$existingInvUser['id'], $inv, $invToken);
        exit;
    }

    // ── Acción: iniciar sesión y aceptar invitación ──────────────────────────
    if ($action === 'login_and_accept' && $inv && $mode === 'accept_login_required' && $existingInvUser) {
        $passLogin = $_POST['password'] ?? '';
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
        $dbL       = Database::getInstance();

        // Rate limit por correo+IP usando login_attempts (mismo patrón que index.php)
        require_once 'db/models/Configuracion.php';
        $secCfg      = Configuracion::getByInstitucion((int)$inv['institucion_id']) ?: [];
        $maxAttempts = max(1, (int)($secCfg['seg_max_intentos'] ?? 5));
        $blockMin    = max(1, (int)($secCfg['seg_bloqueo_min']  ?? 15));
        $window      = date('Y-m-d H:i:s', strtotime("-{$blockMin} minutes"));
        $stmtRL = $dbL->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND exitoso = 0 AND creado_at > ?");
        $stmtRL->execute([$existingInvUser['email'], $ip, $window]);
        if ((int)$stmtRL->fetchColumn() >= $maxAttempts) {
            $regError = "Demasiados intentos fallidos. Espera {$blockMin} minutos e intenta de nuevo.";
        } elseif ($existingInvUser['estado'] !== 'activo') {
            $regError = 'Tu cuenta está desactivada. Contacta al administrador.';
        } elseif (!password_verify($passLogin, $existingInvUser['password_hash'])) {
            $dbL->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,0)")
                ->execute([$existingInvUser['email'], $ip]);
            try { security_alert('login_fallido', ['email' => $existingInvUser['email']]); } catch (\Throwable $e) {}
            $regError = 'Contraseña incorrecta.';
        } else {
            // Login OK → establecer sesión base y vincular institución
            session_regenerate_id(true);
            $_SESSION['user_id']          = (int)$existingInvUser['id'];
            $_SESSION['user_nombre']      = $existingInvUser['nombre'];
            $_SESSION['user_email']       = $existingInvUser['email'];
            $_SESSION['user_estado']      = $existingInvUser['estado'];

            $dbL->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,1)")
                ->execute([$existingInvUser['email'], $ip]);
            $dbL->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
                ->execute([(int)$existingInvUser['id']]);

            $linkExistingUserAndRedirect((int)$existingInvUser['id'], $inv, $invToken);
            exit;
        }
    }

    // ── Flujo de registro nuevo (sólo cuando no estamos en modo aceptar) ─────
    if ($mode === 'register') {
    if (!$inv && !$eventInv) {
        $regError = $invError ?: 'Se requiere un enlace de invitación para registrarse.';
    } else {
    $db       = Database::getInstance();
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $pass     = $_POST['password']  ?? '';
    $pass2    = $_POST['password2'] ?? '';
    $telefonoCountryCode = trim($_POST['telefono_country_code'] ?? '+52') ?: '+52';
    $telefonoLocal = trim($_POST['telefono_local'] ?? '');
    $telefonoAdmin = trim($_POST['telefono'] ?? '');
    if ($telefonoLocal !== '') {
        $telefonoAdmin = register_compose_phone($telefonoCountryCode, $telefonoLocal);
    } elseif ($telefonoAdmin !== '') {
        $telefonoAdmin = register_compose_phone($telefonoCountryCode, $telefonoAdmin);
    }
    $telefonoDigits = preg_replace('/\D+/', '', $telefonoAdmin);

    // Si hay invitación válida: rol e institución vienen de la BD; el email solo si la invitación lo trae (QR sin email permite capturarlo).
    if ($eventInv) {
        $rol            = 'admin';
        $institucion_id = null;
        $email          = strtolower(trim($_POST['email'] ?? ''));
    } elseif ($inv) {
        $rol           = $inv['rol'];
        $institucion_id = (int)$inv['institucion_id'];
        $invEmail      = isset($inv['email']) ? trim((string)$inv['email']) : '';
        $email         = $invEmail !== '' ? $invEmail : strtolower(trim($_POST['email'] ?? ''));
    } else {
        $email          = strtolower(trim($_POST['email'] ?? ''));
        $rol            = ($_POST['rol'] ?? 'cuidador') === 'cuidador' ? 'enfermero' : ($_POST['rol'] ?? 'enfermero');
        $institucion_id = null;
    }

    // ── Modo evento: invitado opta por crear su propia institución ──────────
    // Si la invitación lo permite Y el usuario marcó la casilla con un nombre,
    // entramos en modo "self-provisioning": el flujo creará una nueva
    // institución (estado='trial' si dias_prueba > 0), tenant DB y dejará al
    // usuario como administrador de ESA nueva institución, no de la del emisor.
    $crearInstNombre = trim($_POST['crear_inst_nombre'] ?? '');
    $crearNuevaInst  = (bool)$eventInv || ($inv && !empty($inv['permite_nueva_institucion']) && $crearInstNombre !== '');
    if ($crearNuevaInst) {
        // En este modo el rol siempre será admin (el invitado administra su propia institución).
        $rol = 'admin';
        // Marcamos como "sin institución todavía"; se creará más abajo.
        $institucion_id = null;
    }

    $validRoles = ['admin', 'enfermero', 'medico', 'familiar'];

    // Load security config for password length
    $_regInstId = $institucion_id ?: 0;
    $_regCfg = [];
    if ($_regInstId) {
        require_once 'db/models/Configuracion.php';
        $_regCfg = Configuracion::getByInstitucion($_regInstId) ?: [];
    }
    $_regPassLen = max(4, (int)($_regCfg['seg_pass_min_len'] ?? 8));

    if (!$nombre || !$apellido || !$email || !$pass) {
        $regError  = 'Todos los campos son obligatorios.';
        $errorField = !$nombre ? 'nombre' : (!$apellido ? 'apellido' : (!$email ? 'email' : 'password'));
    } elseif ($eventInv && $telefonoAdmin === '') {
        $regError  = 'Indica un número telefónico de contacto.';
        $errorField = 'telefono';
    } elseif ($telefonoAdmin !== '' && (strlen($telefonoDigits) < 8 || strlen($telefonoDigits) > 20)) {
        $regError  = 'El teléfono debe incluir entre 8 y 20 dígitos.';
        $errorField = 'telefono';
    } elseif (empty($_POST['accept_consent'])) {
        $regError  = 'Debe aceptar las condiciones legales para continuar.';
        $errorField = 'accept_consent';
    } elseif ($crearNuevaInst && $crearInstNombre === '') {
        $regError  = 'Indica el nombre de tu institución para iniciar tu prueba.';
        $errorField = 'crear_inst_nombre';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $regError  = 'El correo electrónico no es válido.';
        $errorField = 'email';
    } elseif (!in_array($rol, $validRoles, true)) {
        $regError  = 'Rol no válido.';
        $errorField = 'rol';
    } elseif ($pwErr = validate_password_strength($pass, $_regPassLen)) {
        $regError  = $pwErr;
        $errorField = 'password';
    } elseif ($pass !== $pass2) {
        $regError  = 'Las contraseñas no coinciden.';
        $errorField = 'password2';
    } else {
        $stmt = $db->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser && $inv && $institucion_id) {
            // User already exists but has a valid invitation → link to institution
            $existingId   = (int)$existingUser['id'];
            $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
            $rolPivot     = in_array($rol, $rolesValidos, true) ? $rol : 'enfermero';

            // ── F6: cupo de familiares ────────────────────────────────────
            $requiresFamiliarSelfPay = false;
            if ($rolPivot === 'familiar') {
                require_once __DIR__ . '/db/models/Seats.php';
                try {
                    Seats::assertCanAddFamiliar((int)$institucion_id);
                } catch (SeatLimitException $e) {
                    $requiresFamiliarSelfPay = true;
                }
            }

            $db->prepare(
                "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
                 VALUES (?, ?, ?)"
            )->execute([$existingId, $institucion_id, $rolPivot]);

            Invitacion::markAccepted($invToken);

            // Link to residents if specified in invitation
            if (!empty($inv['residente_ids'])) {
                $resIds = json_decode($inv['residente_ids'], true);
                if (is_array($resIds) && count($resIds)) {
                    require_once __DIR__ . '/db/models/UsuarioResidente.php';
                    UsuarioResidente::sync($existingId, $resIds, $institucion_id);
                }
            }

            $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,?,'inv_link_existing','Auth',?,'ok')")
               ->execute([$existingId, $institucion_id, $_SERVER['REMOTE_ADDR'] ?? null]);

            if ($requiresFamiliarSelfPay) {
                $loginFamiliarAndRedirectToBilling($existingId, $existingUser['nombre'] ?? $email, $email, (int)$institucion_id);
            }

            header('Location: ' . BASE_URL . '/index.php?registered=1&linked=1');
            exit;
        } elseif ($existingUser && $eventInv) {
            $regError = 'Este correo ya está registrado. Inicia sesión primero o usa otro correo para crear una nueva institución.';
            $errorField = 'email';
        } elseif ($existingUser && $crearNuevaInst) {
            // Usuario ya registrado y eligió crear su propia institución (modo evento).
            $existingId = (int)$existingUser['id'];
            try {
                $trialDays = $eventInv ? (int)($eventInv['trial_dias'] ?? 0) : (isset($inv['dias_prueba']) ? (int)$inv['dias_prueba'] : null);
                $planId = $eventInv ? (int)$eventInv['plan_id'] : null;
                $newInstId = $provisionNewInstitutionForInvitee(
                    $crearInstNombre,
                    $existingId,
                    $trialDays,
                    $email,
                    $planId,
                        $eventInv ? 'event_qr' : 'self_signup_qr',
                        $eventInv ? $telefonoAdmin : null
                );
                    if ($eventInv) {
                        try { $db->prepare("UPDATE usuarios SET telefono = COALESCE(NULLIF(telefono,''), ?) WHERE id = ?")->execute([$telefonoAdmin ?: null, $existingId]); } catch (\Throwable $e) {}
                        $sendEventWelcomeEmail($newInstId, $crearInstNombre, $email, (string)($existingInvUser['nombre'] ?? $email), $trialDays, $telefonoAdmin ?: null);
                    }
                if ($eventInv) GeriappEventInvitation::markUsed($eventToken, $existingId, $newInstId, ['flow' => 'existing_user_new_institution']);
                else Invitacion::markAccepted($invToken);
                header('Location: ' . BASE_URL . '/index.php?registered=1&new_inst=' . $newInstId);
                exit;
            } catch (\Throwable $e) {
                error_log('[GeriApp] register self-provision (existing) error: ' . $e->getMessage());
                $regError = 'No se pudo crear la institución. Intenta de nuevo o contacta soporte.';
            }
        } elseif ($existingUser) {
            $regError  = 'Este correo electrónico ya está registrado.';
            $errorField = 'email';
        } else {
            // ── F6: cupo de familiares (registro nuevo) ─────────────────
            // Solo aplica si el invitado se vincula a una institución existente
            // como familiar. Si crea su propia institución (self-provision), no
            // hay cupo previo que respetar.
            $requiresFamiliarSelfPay = false;
            if ($rol === 'familiar' && $institucion_id && !$crearNuevaInst) {
                require_once __DIR__ . '/db/models/Seats.php';
                try {
                    Seats::assertCanAddFamiliar((int)$institucion_id);
                } catch (SeatLimitException $e) {
                    $requiresFamiliarSelfPay = true;
                }
            }

            $hash            = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $nombre_completo = $nombre . ' ' . $apellido;

                $db->prepare("INSERT INTO usuarios (institucion_id, nombre, email, password_hash, rol, telefono, terms_accepted_at, privacy_version) VALUES (?,?,?,?,?,?,NOW(),?)")
                    ->execute([$institucion_id, $nombre_completo, $email, $hash, $rol, $telefonoAdmin ?: null, APP_VERSION]);

            $nuevo_id = (int)$db->lastInsertId();

            // ── Self-provision: el invitado eligió crear su propia institución ──
            if ($crearNuevaInst && ($inv || $eventInv)) {
                try {
                    $trialDays = $eventInv ? (int)($eventInv['trial_dias'] ?? 0) : (isset($inv['dias_prueba']) ? (int)$inv['dias_prueba'] : null);
                    $planId = $eventInv ? (int)$eventInv['plan_id'] : null;
                    $newInstId = $provisionNewInstitutionForInvitee(
                        $crearInstNombre,
                        $nuevo_id,
                        $trialDays,
                        $email,
                        $planId,
                        $eventInv ? 'event_qr' : 'self_signup_qr',
                        $eventInv ? $telefonoAdmin : null
                    );
                    // Refleja la institución principal del usuario en `usuarios.institucion_id`.
                    $db->prepare("UPDATE usuarios SET institucion_id = ? WHERE id = ?")
                       ->execute([$newInstId, $nuevo_id]);
                    $institucion_id = $newInstId; // para el resto del flujo (firmas legales, etc.)
                } catch (\Throwable $e) {
                    error_log('[GeriApp] register self-provision error: ' . $e->getMessage());
                    // Limpieza: borrar usuario huérfano para que pueda reintentar.
                    try { $db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$nuevo_id]); } catch (\Throwable $_) {}
                    $regError = 'No se pudo crear la institución. Intenta de nuevo o contacta soporte.';
                    // Renderizar el formulario con el error en lugar de continuar el flujo.
                    $skipPostRegister = true;
                }
            }

            if (empty($skipPostRegister)) {
            if ($eventInv && $crearNuevaInst && $institucion_id) {
                $sendEventWelcomeEmail($institucion_id, $crearInstNombre, $email, $nombre_completo, $trialDays ?? null, $telefonoAdmin ?: null);
            }
            // Agregar al pivot usuario_instituciones
            if ($institucion_id) {
                $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];
                $rolPivot     = in_array($rol, $rolesValidos, true) ? $rol : 'enfermero';
                $db->prepare(
                    "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
                     VALUES (?, ?, ?)"
                )->execute([$nuevo_id, $institucion_id, $rolPivot]);
            }

            // Marcar invitación como aceptada
            if ($inv) {
                Invitacion::markAccepted($invToken);

                // Link to residents if specified in invitation
                if (!empty($inv['residente_ids'])) {
                    $resIds = json_decode($inv['residente_ids'], true);
                    if (is_array($resIds) && count($resIds) && $institucion_id) {
                        require_once __DIR__ . '/db/models/UsuarioResidente.php';
                        UsuarioResidente::sync($nuevo_id, $resIds, $institucion_id);
                    }
                }
            }
            if ($eventInv) {
                GeriappEventInvitation::markUsed($eventToken, $nuevo_id, $institucion_id ? (int)$institucion_id : null, ['flow' => 'new_user_new_institution']);
            }

            $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,?,'register','Auth',?,'ok')")
               ->execute([$nuevo_id, $institucion_id, $_SERVER['REMOTE_ADDR'] ?? null]);

            // ── Registrar firmas legales (T&C y AdP) si el usuario firmó ──
            if ($institucion_id) {
                $regIp = $_SERVER['REMOTE_ADDR'] ?? '';
                $regUa = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
                $dbT   = Database::getTenant($institucion_id);

                $legalPairs = [
                    ['doc_id' => (int)($_POST['tc_doc_id'] ?? 0),  'firma' => $_POST['tc_firma_data'] ?? ''],
                    ['doc_id' => (int)($_POST['adp_doc_id'] ?? 0), 'firma' => $_POST['adp_firma_data'] ?? ''],
                ];
                foreach ($legalPairs as $lp) {
                    if ($lp['doc_id'] > 0 && $lp['firma'] && preg_match('/^data:image\/png;base64,/', $lp['firma']) && strlen($lp['firma']) < 700000) {
                        try {
                            $dbT->prepare(
                                "INSERT INTO firmas_documentos (documento_id, usuario_id, firma_data, ip, user_agent)
                                 VALUES (?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE firma_data = VALUES(firma_data), ip = VALUES(ip), user_agent = VALUES(user_agent), firmado_at = NOW()"
                            )->execute([$lp['doc_id'], $nuevo_id, $lp['firma'], $regIp, $regUa]);
                        } catch (\Throwable $e) { /* tabla puede no existir aún */ }
                    }
                }

                // Enviar copia por correo de los documentos firmados
                try {
                    require_once __DIR__ . '/db/models/Mailer.php';
                    require_once __DIR__ . '/db/models/Configuracion.php';
                    $cfg = Configuracion::getOrCreate($institucion_id);
                    if (!empty($cfg['smtp_host'])) {
                        foreach ($legalPairs as $lp) {
                            if ($lp['doc_id'] <= 0 || !$lp['firma']) continue;
                            $docRow = $dbT->prepare("SELECT tipo, version, titulo, contenido FROM documentos_legales WHERE id = ? LIMIT 1");
                            $docRow->execute([$lp['doc_id']]);
                            $docData = $docRow->fetch(PDO::FETCH_ASSOC);
                            if (!$docData) continue;

                            $tipoLabel  = $docData['tipo'] === 'terminos' ? 'Términos y Condiciones' : 'Aviso de Privacidad';
                            $fechaFirma = date('d/m/Y H:i');
                            // Render legal content as HTML (sanitized) — it is rich text from the editor.
                            $rawDoc = (string)($docData['contenido'] ?? '');
                            if (preg_match('/<[a-z][\\s\\S]*?>/i', $rawDoc)) {
                                $contenidoHtml = strip_tags($rawDoc,
                                    '<b><strong><i><em><u><br><p><div><span><ul><ol><li>'
                                    . '<h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><sub><sup><a>'
                                );
                                $contenidoHtml = preg_replace('/\\s+on\\w+\\s*=\\s*["\'][^"\']*["\']/i', '', $contenidoHtml);
                                $contenidoHtml = preg_replace('/href\\s*=\\s*["\']?\\s*javascript:[^"\'>\\s]*/i', 'href="#"', $contenidoHtml);
                            } else {
                                $contenidoHtml = nl2br(htmlspecialchars($rawDoc, ENT_QUOTES, 'UTF-8'));
                            }
                            $nombreEsc = htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8');
                            $emailEsc  = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

                            $htmlBody = <<<MAIL
<div style="font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;color:#1e293b">
  <div style="text-align:center;padding:16px 0 12px">
    <h2 style="margin:0;font-size:20px;color:#178391">GeriApp</h2>
    <p style="margin:6px 0 0;color:#64748b;font-size:13px">Copia de documento firmado al registrarse</p>
  </div>
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin:12px 0 16px">
    <table style="width:100%;font-size:13px;border-collapse:collapse">
      <tr><td style="padding:4px 8px;color:#64748b;width:120px">Documento:</td><td style="padding:4px 8px;font-weight:600">{$tipoLabel}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Título:</td><td style="padding:4px 8px">{$docData['titulo']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Versión:</td><td style="padding:4px 8px">{$docData['version']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Firmante:</td><td style="padding:4px 8px">{$nombreEsc}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Email:</td><td style="padding:4px 8px">{$emailEsc}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Fecha firma:</td><td style="padding:4px 8px">{$fechaFirma}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">IP:</td><td style="padding:4px 8px">{$regIp}</td></tr>
    </table>
  </div>
  <div style="border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px">
    <h3 style="margin:0 0 12px;font-size:14px;color:#334155">Contenido del documento</h3>
    <div style="font-size:12px;line-height:1.7;color:#475569;word-wrap:break-word">{$contenidoHtml}</div>
  </div>
  <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0 0">
    Este correo es una constancia automática de aceptación al momento del registro.<br>
    &copy; GeriApp — Sistema de gestión de cuidados geriátricos
  </p>
</div>
MAIL;
                            $subject = "Copia de {$tipoLabel} firmado — {$docData['titulo']}";
                            $recipients = [$email];
                            $ccEmail = trim($cfg['legal_cc_email'] ?? '');
                            if ($ccEmail && filter_var($ccEmail, FILTER_VALIDATE_EMAIL) && strtolower($ccEmail) !== strtolower($email)) {
                                $recipients[] = $ccEmail;
                            }
                            $mailer = Mailer::fromConfig($institucion_id);
                            $mailer->send($recipients, $subject, $htmlBody);
                        }
                    }
                } catch (\Throwable $e) { /* email failure should not block registration */ }
            }
            $registroPlanId = (int)($planId ?? 0);
            $solicitaTarjetaRegistro = $eventInv && $crearNuevaInst && !empty($eventInv['solicita_tarjeta_registro']) && $registroPlanId > 0;
            if ($solicitaTarjetaRegistro && $institucion_id) {
                $ensureRegistrationSubscription($nuevo_id, (int)$institucion_id, $registroPlanId, $trialDays ?? 0, true);
                $loginNewAdminAndRedirectToBilling($nuevo_id, $nombre_completo, $email, (int)$institucion_id, $crearInstNombre, $registroPlanId);
            }

            if (!empty($requiresFamiliarSelfPay) && $rol === 'familiar' && $institucion_id) {
                $loginFamiliarAndRedirectToBilling($nuevo_id, $nombre_completo, $email, (int)$institucion_id);
            }

            header('Location: ' . BASE_URL . '/index.php?registered=1');
            exit;
            } // end if (empty($skipPostRegister))
            endRegisterFlow:; // F6: salida limpia cuando el cupo de familiares bloqueó el alta
        }
    }
    }
    } // end if ($mode === 'register')
}

$pageTitle = 'Crear Cuenta';
$bodyClass = 'login-page register-page';
$forceLightTheme = true;
require_once 'includes/head.php';
?>

<div class="login-outer">
    <div class="login-card login-card--register">

        <!-- Brand -->
        <div class="login-brand-new">
            <div class="login-heart-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                     stroke="#178391" stroke-width="1.8">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                             l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78
                             l1.06 1.06L12 21.23l7.78-7.78
                             1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            <h1 class="login-app-name">GeriApp</h1>
            <p class="login-app-sub">
                <?php
                if ($mode === 'accept_logged_in')        echo 'Aceptar nueva invitación';
                elseif ($mode === 'accept_login_required') echo 'Vincular tu cuenta existente';
                elseif ($mode === 'accept_email_mismatch') echo 'Sesión activa con otro correo';
                elseif ($mode === 'accept_already_member') echo 'Ya perteneces a esta institución';
                elseif ($eventInv) echo 'Crea tu institución';
                elseif ($inv) echo 'Completa tu registro';
                else echo 'Crea tu cuenta';
                ?>
            </p>
        </div>

        <!-- Banner de invitación -->
        <?php if ($eventInv && $mode === 'register'): ?>
        <?php
            $eventPlan = htmlspecialchars($eventInv['plan_nombre'] ?? 'Paquete GeriApp', ENT_QUOTES, 'UTF-8');
            $eventTrial = (int)($eventInv['trial_dias'] ?? 0);
            $eventCardRequired = !empty($eventInv['solicita_tarjeta_registro']);
            $eventName = trim((string)($eventInv['nombre_evento'] ?? ''));
            $eventNameEsc = htmlspecialchars($eventName ?: 'Invitación GeriApp', ENT_QUOTES, 'UTF-8');
            $crearInstNombrePrev = htmlspecialchars($_POST['crear_inst_nombre'] ?? ($prefill['institucion'] ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="reg-event-hero">
            <div class="reg-event-kicker">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM19 19h2v2h-2zM19 14h2v2h-2zM14 19h2v2h-2z"/></svg>
                <?= $eventNameEsc ?>
            </div>
            <div class="reg-event-title">Crea una institución nueva en GeriApp</div>
            <div class="reg-event-pills">
                <span class="reg-event-pill">Paquete: <?= $eventPlan ?></span>
                <span class="reg-event-pill">Prueba: <?= $eventTrial > 0 ? $eventTrial . ' días' : 'sin trial' ?></span>
                <?php if ($eventCardRequired): ?><span class="reg-event-pill">Requiere tarjeta</span><?php endif; ?>
            </div>
            <div class="reg-event-hint">
                Al completar el registro quedarás como Administrador. La prueba inicia desde este registro y se aplicará la configuración inicial de GeriApp.<?php if ($eventCardRequired): ?> Después te llevaremos a un checkout seguro para registrar tu tarjeta.<?php endif; ?>
            </div>
        </div>
        <div class="reg-event-inst-card">
            <label for="crearInstNombre" class="reg-event-inst-label">Nombre de tu institución <span class="reg-required-star">*</span></label>
            <input type="text" id="crearInstNombre" name="crear_inst_nombre" form="regForm"
                   maxlength="120" value="<?= $crearInstNombrePrev ?>"
                   placeholder="Ej. Casa de retiro San José"
                   class="reg-event-inst-input"
                   required>
        </div>
        <?php elseif ($inv && $mode === 'register'): ?>
        <div style="margin-bottom:18px;padding:14px 16px;background:linear-gradient(135deg,#178391 0%, #033f3f 100%);color:#fff;border-radius:10px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;opacity:.85">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                Invitación a la institución
            </div>
            <div style="font-size:18px;font-weight:700;line-height:1.25"><?= htmlspecialchars($invInstNombre !== '' ? $invInstNombre : ('Institución #' . (int)$inv['institucion_id'])) ?></div>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;opacity:.95">
                <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:999px;font-weight:600;font-size:11px;letter-spacing:.4px;text-transform:uppercase">
                    Rol: <?= htmlspecialchars($rolLabels[$inv['rol']] ?? ucfirst($inv['rol'])) ?>
                </span>
            </div>
            <div style="font-size:12px;opacity:.85;line-height:1.5;margin-top:2px">
                El rol y la institución han sido asignados por el administrador y no pueden modificarse.
            </div>
        </div>
        <?php
        // ── Modo evento: opción para que el invitado cree su propia institución ──
        if ($inv && !empty($inv['permite_nueva_institucion'])):
            $diasPruebaInv = isset($inv['dias_prueba']) ? (int)$inv['dias_prueba'] : 0;
            $crearInstNombrePrev = htmlspecialchars($_POST['crear_inst_nombre'] ?? '', ENT_QUOTES, 'UTF-8');
            $crearChecked = !empty($_POST['crear_inst_toggle']) ? 'checked' : '';
        ?>
        <div style="margin:0 0 18px;padding:14px 16px;background:#fef9c3;border:1px dashed #ca8a04;border-radius:10px;color:#713f12">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:14px">
                <input type="checkbox" id="crearInstToggle" name="crear_inst_toggle" value="1" <?= $crearChecked ?>
                       form="regForm" style="accent-color:#ca8a04;transform:scale(1.1)"
                       onchange="document.getElementById('crearInstWrap').style.display=this.checked?'block':'none';document.getElementById('crearInstNombre').required=this.checked;">
                <span>Crear mi propia institución en lugar de unirme a la de arriba</span>
            </label>
            <p style="margin:6px 0 0;padding-left:26px;font-size:12px;line-height:1.5;color:#854d0e">
                Quedarás como <strong>Administrador</strong> de tu nueva institución
                <?php if ($diasPruebaInv > 0): ?>
                    con <strong><?= (int)$diasPruebaInv ?> días de prueba</strong>. La facturación se gestionará más adelante.
                <?php else: ?>
                    .
                <?php endif; ?>
            </p>
            <div id="crearInstWrap" style="display:<?= $crearChecked ? 'block' : 'none' ?>;padding-left:26px;margin-top:10px">
                <label for="crearInstNombre" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Nombre de tu institución <span class="reg-required-star">*</span></label>
                <input type="text" id="crearInstNombre" name="crear_inst_nombre" form="regForm"
                       maxlength="120" value="<?= $crearInstNombrePrev ?>"
                       placeholder="Ej. Casa de retiro San José"
                       style="width:100%;padding:8px 10px;border:1px solid #ca8a04;border-radius:6px;font-size:14px;background:#fff;color:#1e293b"
                       <?= $crearChecked ? 'required' : '' ?>>
            </div>
        </div>
        <?php endif; ?>
        <?php elseif ($invError): ?>
        <div class="login-alert login-alert--error" style="margin-bottom:18px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>
                <?= htmlspecialchars($invError) ?>
                <?php if ($invToken || $eventToken): ?>
                <br><small style="opacity:.75">Código recibido: <code style="background:rgba(0,0,0,.06);padding:1px 6px;border-radius:3px;font-size:11px"><?= htmlspecialchars(substr($invToken ?: $eventToken,0,12)) ?>…</code></small>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Alerta de error -->
        <?php if ($regInfo): ?>
        <div class="login-alert login-alert--info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= htmlspecialchars($regInfo) ?>
        </div>
        <?php elseif ($regError): ?>
        <div class="login-alert login-alert--error" id="regErrorAlert">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($regError) ?>
        </div>
        <?php endif; ?>

        <?php if ($mode !== 'register' && $inv): ?>
        <?php
            $invInstLabel = $invInstNombre !== '' ? $invInstNombre : ('institución #' . (int)$inv['institucion_id']);
            $invRolLabel  = $rolLabels[$inv['rol']] ?? ucfirst($inv['rol']);
            $invEmailEsc  = htmlspecialchars($inv['email']);
        ?>

        <?php if ($mode === 'accept_logged_in'): ?>
        <!-- ── Modo: aceptar invitación con sesión activa (1 click) ──────── -->
        <div class="login-alert login-alert--info" style="margin-bottom:18px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
            <span>
                Tu cuenta <strong><?= $invEmailEsc ?></strong> ya existe en GeriApp.
                Vamos a vincularla a <strong><?= htmlspecialchars($invInstLabel) ?></strong>
                como <strong><?= htmlspecialchars($invRolLabel) ?></strong>.
            </span>
        </div>
        <form action="<?= BASE_URL ?>/register.php?inv=<?= urlencode($invToken) ?>" method="post" class="login-form">
            <?= csrf_field() ?>
            <input type="hidden" name="inv_token" value="<?= htmlspecialchars($invToken) ?>">
            <input type="hidden" name="action" value="accept_existing">
            <button type="submit" class="login-btn">Aceptar invitación</button>
        </form>
        <div class="login-links" style="margin-top:14px;">
            <p><a href="<?= BASE_URL ?>/cuidados.php" class="login-back">Cancelar y volver a la app</a></p>
        </div>

        <?php elseif ($mode === 'accept_login_required'): ?>
        <!-- ── Modo: ya tienes cuenta, inicia sesión y vincula ─────────── -->
        <div class="login-alert login-alert--info" style="margin-bottom:18px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>
                Ya tienes una cuenta GeriApp con el correo <strong><?= $invEmailEsc ?></strong>.
                Inicia sesión para vincularla a <strong><?= htmlspecialchars($invInstLabel) ?></strong>
                como <strong><?= htmlspecialchars($invRolLabel) ?></strong>.
            </span>
        </div>
        <form action="<?= BASE_URL ?>/register.php?inv=<?= urlencode($invToken) ?>" method="post" class="login-form" autocomplete="off" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="inv_token" value="<?= htmlspecialchars($invToken) ?>">
            <input type="hidden" name="action" value="login_and_accept">

            <div class="login-field">
                <label>Correo</label>
                <div class="login-input-wrap" style="background:#f8fafc;border-color:#cbd5e1;">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <span class="login-input" style="display:flex;align-items:center;color:#64748b;cursor:not-allowed;padding-left:0;"><?= $invEmailEsc ?></span>
                </div>
            </div>

            <div class="login-field">
                <label for="password">Contraseña</label>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="password" name="password" class="login-input" placeholder="Tu contraseña actual" required autofocus>
                </div>
            </div>

            <button type="submit" class="login-btn">Iniciar sesión y aceptar invitación</button>
        </form>
        <div class="login-links" style="margin-top:14px;">
            <p><a href="<?= BASE_URL ?>/forgot-password.php?email=<?= urlencode($inv['email']) ?>">¿Olvidaste tu contraseña?</a></p>
        </div>

        <?php elseif ($mode === 'accept_email_mismatch'): ?>
        <!-- ── Modo: sesión activa con correo distinto al de la invitación ─ -->
        <div class="login-alert login-alert--error" style="margin-bottom:18px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>
                Estás autenticado/a como <strong><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></strong>,
                pero esta invitación está dirigida a <strong><?= $invEmailEsc ?></strong>.
                Cierra sesión y vuelve a abrir el enlace para aceptarla.
            </span>
        </div>
        <form action="<?= BASE_URL ?>/auth/logout.php" method="get" class="login-form">
            <input type="hidden" name="redirect" value="register.php">
            <input type="hidden" name="inv" value="<?= htmlspecialchars($invToken) ?>">
            <button type="submit" class="login-btn">Cerrar sesión y continuar</button>
        </form>

        <?php elseif ($mode === 'accept_already_member'): ?>
        <!-- ── Modo: el usuario ya pertenece a esta institución ──────────── -->
        <div class="login-alert login-alert--info" style="margin-bottom:18px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <span>
                Tu cuenta <strong><?= $invEmailEsc ?></strong> ya pertenece a
                <strong><?= htmlspecialchars($invInstLabel) ?></strong>.
                No es necesario aceptar esta invitación de nuevo.
            </span>
        </div>
        <div class="login-links">
            <?php if (!empty($_SESSION['user_id'])): ?>
            <p><a href="<?= BASE_URL ?>/select_institucion.php" class="login-btn" style="display:inline-block;text-decoration:none;">Ir al selector de institución</a></p>
            <?php else: ?>
            <p><a href="<?= BASE_URL ?>/index.php" class="login-btn" style="display:inline-block;text-decoration:none;">Iniciar sesión</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: /* mode === 'register' */ ?>

        <?php if (!$inv && !$eventInv): /* Registro cerrado o token inválido/expirado: no mostrar formulario */ ?>
        <div style="display:flex;flex-direction:column;gap:12px;align-items:stretch">
            <a href="<?= BASE_URL ?>/index.php" class="login-btn" style="text-align:center;text-decoration:none;display:inline-block">Volver al inicio de sesión</a>
            <p style="font-size:12px;color:#94a3b8;text-align:center;margin:0;line-height:1.6">
                Si crees que el enlace debería ser válido, contacta al administrador de la institución para solicitar una nueva invitación.
            </p>
        </div>
        <?php else: ?>

        <!-- Form -->
        <form action="<?= BASE_URL ?>/register.php<?= $invToken ? '?inv=' . urlencode($invToken) : ($eventToken ? '?event=' . urlencode($eventToken) : '') ?>" method="post" autocomplete="off" class="login-form" id="regForm" novalidate>
            <?= csrf_field() ?>
            <?php if ($invToken): ?>
            <input type="hidden" name="inv_token" value="<?= htmlspecialchars($invToken) ?>">
            <?php endif; ?>
            <?php if ($eventToken): ?>
            <input type="hidden" name="event_token" value="<?= htmlspecialchars($eventToken) ?>">
            <?php endif; ?>

            <!-- Name row -->
            <div class="reg-name-row">
                <div class="login-field">
                    <label for="nombre">Nombre(s) <span class="reg-required-star">*</span></label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <input type="text" id="nombre" name="nombre"
                               class="login-input" placeholder="Juan"
                               value="<?= htmlspecialchars($_POST['nombre'] ?? ($inv['nombre_sugerido'] ?? ($prefill['nombre'] ?? ''))) ?>" required>
                    </div>
                </div>
                <div class="login-field">
                    <label for="apellido">Apellido(s) <span class="reg-required-star">*</span></label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <input type="text" id="apellido" name="apellido"
                               class="login-input" placeholder="García"
                               value="<?= htmlspecialchars($_POST['apellido'] ?? ($inv['apellido_sugerido'] ?? ($prefill['apellido'] ?? ''))) ?>" required>
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div class="login-field">
                <label for="email">Correo Electrónico <span class="reg-required-star">*</span></label>
                <div class="login-input-wrap" <?= $inv ? 'style="background:#f8fafc;border-color:#cbd5e1;"' : '' ?>>
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12
                                     c0 1.1-.9 2-2 2H4
                                     c-1.1 0-2-.9-2-2V6
                                     c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email"
                           class="login-input" placeholder="tu@email.com"
                           value="<?= htmlspecialchars($inv && !empty($inv['email']) ? $inv['email'] : ($_POST['email'] ?? ($prefill['email'] ?? ''))) ?>"
                           <?= ($inv && !empty($inv['email'])) ? 'readonly style="color:#64748b;cursor:not-allowed;"' : '' ?>
                           required <?= (!$inv || empty($inv['email'])) ? 'autofocus' : '' ?>>
                    <?php if ($inv && !empty($inv['email'])): ?>
                    <span class="login-input-icon" title="Asignado por invitación">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="#94a3b8" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="reg-contact-check" id="emailCheckMsg" aria-live="polite"></div>
            </div>

            <?php if ($eventInv): ?>
            <?php
                $postedCountryCode = trim($_POST['telefono_country_code'] ?? '+52') ?: '+52';
                $postedPhoneLocal = trim($_POST['telefono_local'] ?? '');
                if ($postedPhoneLocal === '' && !empty($_POST['telefono'])) {
                    $postedPhoneLocal = register_phone_local_from_full((string)$_POST['telefono'], $postedCountryCode);
                }
                if ($postedPhoneLocal === '' && !empty($prefill['telefono'])) {
                    $postedPhoneLocal = register_phone_local_from_full((string)$prefill['telefono'], $postedCountryCode);
                }
                $phoneCountryOptions = [
                    '+52' => 'México',
                    '+1'  => 'EE. UU. / Canadá',
                    '+57' => 'Colombia',
                    '+34' => 'España',
                    '+54' => 'Argentina',
                    '+56' => 'Chile',
                    '+51' => 'Perú',
                    '+55' => 'Brasil',
                    '+502' => 'Guatemala',
                    '+503' => 'El Salvador',
                    '+504' => 'Honduras',
                    '+506' => 'Costa Rica',
                    '+507' => 'Panamá',
                ];
            ?>
            <div class="login-field">
                <label for="telefono">Teléfono de contacto <span class="reg-required-star">*</span></label>
                <div class="login-input-wrap reg-phone-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.59 2.61a2 2 0 0 1-.45 2.11L8 9.69a16 16 0 0 0 6.31 6.31l1.25-1.25a2 2 0 0 1 2.11-.45c.84.27 1.71.47 2.61.59A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </span>
                    <select id="telefonoCountryCode" name="telefono_country_code" class="reg-phone-country" aria-label="Código de país">
                        <?php foreach ($phoneCountryOptions as $countryCode => $countryLabel): ?>
                        <option value="<?= htmlspecialchars($countryCode) ?>" <?= $postedCountryCode === $countryCode ? 'selected' : '' ?>><?= htmlspecialchars($countryCode . ' ' . $countryLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="tel" id="telefono" name="telefono_local"
                           class="login-input reg-phone-local" placeholder="55 1234 5678"
                           value="<?= htmlspecialchars($postedPhoneLocal) ?>"
                           inputmode="tel" autocomplete="tel-national" required>
                    <input type="hidden" id="telefonoFull" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                <div class="reg-contact-check" id="phoneCheckMsg" aria-live="polite"></div>
            </div>
            <?php endif; ?>

            <!-- Role -->
            <div class="login-field">
                <label for="rol">Rol <span class="reg-required-star">*</span></label>
                <?php if ($inv || $eventInv): ?>
                <?php
                    $fixedRol = $eventInv ? 'admin' : $inv['rol'];
                    $fixedRolLabel = $rolLabels[$fixedRol] ?? ucfirst($fixedRol);
                ?>
                <div class="login-input-wrap" style="background:#f8fafc;border-color:#cbd5e1;">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87
                                     1.18 6.88L12 17.77l-6.18 3.25
                                     L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </span>
                    <span class="login-input" style="display:flex;align-items:center;color:#64748b;cursor:not-allowed;padding-left:0;">
                        <?= htmlspecialchars($fixedRolLabel) ?>
                    </span>
                    <input type="hidden" id="rol" name="rol" value="<?= htmlspecialchars($fixedRol) ?>" required>
                    <span class="login-input-icon" title="Asignado por invitación">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="#94a3b8" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                </div>
                <?php else: ?>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87
                                     1.18 6.88L12 17.77l-6.18 3.25
                                     L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </span>
                        <select id="rol" name="rol" class="login-input" required
                            style="padding-left:0;cursor:pointer;">
                        <?php $selRol = ($_POST['rol'] ?? 'cuidador') === 'enfermero' ? 'cuidador' : ($_POST['rol'] ?? 'cuidador'); ?>
                        <option value="cuidador" <?= $selRol === 'cuidador' ? 'selected' : '' ?>>Cuidador/a</option>
                        <option value="medico"    <?= $selRol === 'medico'    ? 'selected' : '' ?>>Médico/a</option>
                        <option value="familiar"  <?= $selRol === 'familiar'  ? 'selected' : '' ?>>Familiar</option>
                        <option value="admin"     <?= $selRol === 'admin'     ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="login-field">
                <label for="password">Contraseña <span class="reg-required-star">*</span></label>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password"
                           class="login-input" placeholder="Mínimo 8 caracteres" required>
                    <button type="button" class="login-eye-btn" id="togglePass1"
                            title="Mostrar contraseña" tabindex="-1">
                        <svg id="eyeIcon1" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <!-- Strength bar -->
                <div class="reg-strength" id="strengthBar">
                    <div class="reg-strength-track">
                        <div class="reg-strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="reg-strength-label" id="strengthLabel"></span>
                </div>
                <!-- Password requirements checklist -->
                <ul class="reg-pw-reqs" id="regPwReqs">
                    <li data-req="len"><span class="reg-pw-ico">○</span> Mínimo 8 caracteres</li>
                    <li data-req="upper"><span class="reg-pw-ico">○</span> 1 mayúscula</li>
                    <li data-req="lower"><span class="reg-pw-ico">○</span> 1 minúscula</li>
                    <li data-req="digit"><span class="reg-pw-ico">○</span> 1 número</li>
                    <li data-req="special"><span class="reg-pw-ico">○</span> 1 carácter especial</li>
                </ul>
            </div>

            <!-- Confirm Password -->
            <div class="login-field">
                <label for="password2">Confirmar Contraseña <span class="reg-required-star">*</span></label>
                <div class="login-input-wrap" id="confirmWrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password2" name="password2"
                           class="login-input" placeholder="Repite la contraseña" required>
                    <button type="button" class="login-eye-btn" id="togglePass2"
                            title="Mostrar contraseña" tabindex="-1">
                        <svg id="eyeIcon2" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="reg-match-msg" id="matchMsg"></div>
                <!-- Match requirement -->
                <div class="reg-pw-reqs reg-pw-reqs-match" id="regPwMatch">
                    <li data-req="match"><span class="reg-pw-ico">○</span> Contraseñas coinciden</li>
                </div>
            </div>

            <!-- Consentimiento legal unificado -->
            <div class="login-field" style="margin-top:8px;">
                <label class="reg-accept-label" style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:0.8125rem;line-height:1.6;color:var(--cd-text,#334155);">
                    <input type="checkbox" id="accept_consent" name="accept_consent" value="1"
                           style="margin-top:4px;flex-shrink:0;width:18px;height:18px;accent-color:#178391;cursor:pointer;"
                           <?= !empty($_POST['accept_consent']) ? 'checked' : '' ?> required>
                    <span>
                        <span class="reg-required-star">*</span> He leído y acepto los <a href="#" id="regViewTerms" style="color:#178391;text-decoration:underline;font-weight:500;">Términos y Condiciones</a>
                        y el <a href="#" id="regViewPrivacy" style="color:#178391;text-decoration:underline;font-weight:500;">Aviso de Privacidad</a>.
                        Declaro bajo protesta de decir verdad que cuento con la facultad legal o autorización familiar
                        para gestionar los datos personales y de salud de la persona bajo mi cuidado.
                        Comprendo que GeriApp no es un servicio médico de urgencias.
                        <span id="regConsentTip" style="display:inline-block;width:15px;height:15px;border-radius:50%;background:#e2e8f0;color:#475569;font-size:10px;text-align:center;line-height:15px;cursor:help;margin-left:2px;vertical-align:middle;position:relative;" title="Si usted es familiar o cuidador, su aceptación actúa como mandato de gestión de cuidados conforme a la LFPDPPP.">?</span>
                    </span>
                </label>
                <div id="consentError" style="display:none;color:#dc2626;font-size:0.75rem;margin-top:4px;padding-left:28px;">Debe aceptar las condiciones para continuar.</div>
            </div>

            <!-- Hidden fields para firmas legales -->
            <input type="hidden" id="tc_firma_data" name="tc_firma_data" value="">
            <input type="hidden" id="tc_doc_id" name="tc_doc_id" value="">
            <input type="hidden" id="adp_firma_data" name="adp_firma_data" value="">
            <input type="hidden" id="adp_doc_id" name="adp_doc_id" value="">

            <button type="submit" class="login-btn" id="submitBtn" disabled style="display:flex;align-items:center;justify-content:center;gap:8px;">
                <span id="btnText">Crear Cuenta</span>
                <span id="btnSpinner" style="display:none;align-items:center;gap:8px;">
                    <svg style="animation:reg-spin .75s linear infinite;flex-shrink:0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10" stroke-opacity=".25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                    </svg>
                    Creando cuenta…
                </span>
            </button>
            <div id="regSubmitHint" style="display:none;margin-top:10px;padding:10px 12px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:0.8125rem;line-height:1.45;text-align:center;">
                Creando tu cuenta y preparando el siguiente paso seguro. Mantén esta ventana abierta.
            </div>

        </form>
        <?php endif; /* invToken && !$inv */ ?>
        <?php endif; /* mode === 'register' branch */ ?>

        <!-- Footer links -->
        <div class="login-links">
            <p>¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/index.php">Inicia sesión aquí</a></p>
            <?php if (!$eventInv): ?>
            <p><a href="<?= BASE_URL ?>/index.php" class="login-back">← Volver al login</a></p>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
@keyframes reg-spin { to { transform: rotate(360deg); } }
.reg-phone-wrap { gap: 8px; align-items: stretch; }
.reg-phone-country {
    border: 0;
    border-right: 1px solid #e2e8f0;
    background: transparent;
    color: #334155;
    font: inherit;
    font-size: 12.5px;
    font-weight: 700;
    max-width: 150px;
    min-width: 128px;
    padding: 0 8px 0 0;
    outline: none;
}
.reg-phone-local { min-width: 0; }
.reg-contact-check {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-height: 18px;
    margin-top: 5px;
    font-size: 12px;
    line-height: 1.45;
    color: #64748b;
}
.reg-contact-statement {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    padding: 3px 8px;
    border-radius: 7px;
    border: 1px solid transparent;
    font-weight: 700;
}
.reg-contact-statement--positive {
    background: #dcfce7;
    border-color: #86efac;
    color: #15803d;
}
.reg-contact-statement--attention {
    background: #fef9c3;
    border-color: #fde68a;
    color: #a16207;
}
.reg-contact-statement--negative {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
}
.reg-contact-statement--neutral {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #64748b;
}
.reg-contact-check.is-ok { color: #15803d; }
.reg-contact-check.is-warn { color: #a16207; }
.reg-contact-check.is-error { color: #dc2626; }
.reg-contact-check.is-loading { color: #64748b; }
@media (max-width: 430px) {
    .reg-phone-wrap { flex-wrap: wrap; padding-top: 8px; padding-bottom: 8px; }
    .reg-phone-country { width: 100%; max-width: none; border-right: 0; border-bottom: 1px solid #e2e8f0; padding: 0 0 8px; }
    .reg-phone-local { flex-basis: calc(100% - 32px); }
}
</style>
<script>
// ── Mobile viewport rescue ───────────────────────────────────────────────────
// Algunos navegadores móviles pueden cargar con viewport ancho tipo desktop;
// en ese caso el formulario se ve diminuto. Detectamos ese estado y usamos una
// clase que agranda y apila el registro para aprovechar el ancho físico.
(function () {
    const coarse = window.matchMedia?.('(hover: none) and (pointer: coarse)')?.matches;
    const mobileUa = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    const touch = (navigator.maxTouchPoints || 0) > 0;
    const screenW = window.screen?.width || 0;
    const wideMobileViewport = window.innerWidth >= 700 && (mobileUa || coarse || touch);
    const scaledMobileViewport = screenW > 0 && screenW <= 600 && window.innerWidth > screenW * 1.15;
    if (wideMobileViewport || scaledMobileViewport) {
        document.body.classList.add('register-viewport-wide');
    }
})();

// ── Eye toggles ───────────────────────────────────────────────────────────────
function makeEyeToggle(btnId, inputId, iconId) {
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!btn || !inp || !icon) return;
    btn.addEventListener('click', function () {
        const show = inp.type === 'password';
        inp.type   = show ? 'text' : 'password';
        icon.innerHTML = show
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    });
}
makeEyeToggle('togglePass1', 'password',  'eyeIcon1');
makeEyeToggle('togglePass2', 'password2', 'eyeIcon2');

// ── Password strength + requirements checklist ───────────────────────────────
function regCheckPwReqs() {
    const passEl = document.getElementById('password');
    const pass2El = document.getElementById('password2');
    if (!passEl || !pass2El) return;
    const val  = passEl.value;
    const val2 = pass2El.value;
    const fill = document.getElementById('strengthFill');
    const lbl  = document.getElementById('strengthLabel');
    const checks = {
        len:     val.length >= 8,
        upper:   /[A-Z]/.test(val),
        lower:   /[a-z]/.test(val),
        digit:   /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val),
        match:   val.length > 0 && val === val2,
    };
    // Animate each requirement
    document.querySelectorAll('#regPwReqs li, #regPwMatch li').forEach(li => {
        const key = li.dataset.req;
        const ok  = checks[key];
        const ico = li.querySelector('.reg-pw-ico');
        const wasOk = li.classList.contains('req-pass');
        if (ok && !wasOk) {
            li.classList.add('req-pass');
            li.classList.remove('req-fail');
            ico.textContent = '●';
        } else if (!ok && wasOk) {
            li.classList.remove('req-pass');
            li.classList.add('req-fail');
            ico.textContent = '○';
        } else if (!ok && !wasOk) {
            li.classList.remove('req-pass', 'req-fail');
            ico.textContent = '○';
        }
    });
    // Strength bar (5 rules excl. match)
    let score = 0;
    if (checks.len)     score++;
    if (checks.upper)   score++;
    if (checks.lower)   score++;
    if (checks.digit)   score++;
    if (checks.special) score++;
    const levels = [
        { pct: '0%',   color: '',        text: '' },
        { pct: '20%',  color: '#dc2626', text: 'Muy débil' },
        { pct: '40%',  color: '#dc2626', text: 'Débil' },
        { pct: '60%',  color: '#d97706', text: 'Regular' },
        { pct: '80%',  color: '#178391', text: 'Buena' },
        { pct: '100%', color: '#16a34a', text: 'Fuerte' },
    ];
    const lvl = levels[score];
    if (fill) {
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
    }
    if (lbl) {
        lbl.textContent       = lvl.text;
        lbl.style.color       = lvl.color;
    }
}
document.getElementById('password')?.addEventListener('input', regCheckPwReqs);

// ── Confirm match ─────────────────────────────────────────────────────────────
document.getElementById('password2')?.addEventListener('input', function () {
    regCheckPwReqs();
    const p1   = document.getElementById('password')?.value || '';
    const msg  = document.getElementById('matchMsg');
    const wrap = document.getElementById('confirmWrap');
    if (!msg || !wrap) return;
    if (!this.value) { msg.textContent = ''; wrap.style.borderColor = ''; return; }
    if (this.value === p1) {
        msg.textContent        = '✓ Las contraseñas coinciden';
        msg.style.color        = '#16a34a';
        wrap.style.borderColor = '#16a34a';
    } else {
        msg.textContent        = '✗ Las contraseñas no coinciden';
        msg.style.color        = '#dc2626';
        wrap.style.borderColor = '#dc2626';
    }
});

// ── Email/teléfono ya registrados ───────────────────────────────────────────
const regContactConflict = { email: false, phone: false };

function regDigits(value) {
    return String(value || '').replace(/\D+/g, '');
}

function regComposePhone() {
    const country = document.getElementById('telefonoCountryCode')?.value || '+52';
    const localEl = document.getElementById('telefono');
    const hidden = document.getElementById('telefonoFull');
    const localRaw = localEl?.value?.trim() || '';
    const countryDigits = regDigits(country) || '52';
    const localDigits = regDigits(localRaw);
    let full = '';
    if (localDigits) {
        full = localRaw.startsWith('+') || localDigits.startsWith(countryDigits)
            ? '+' + localDigits
            : '+' + countryDigits + localDigits;
    }
    if (hidden) hidden.value = full;
    return full;
}

function regSetContactMessage(id, text, state) {
    const box = document.getElementById(id);
    if (!box) return;
    box.innerHTML = '';
    box.className = 'reg-contact-check' + (state ? ' is-' + state : '');
    if (!text) return;
    const chip = document.createElement('span');
    const chipState = state === 'ok' ? 'positive' : (state === 'warn' || state === 'error' ? 'attention' : 'neutral');
    chip.className = 'reg-contact-statement reg-contact-statement--' + chipState;
    chip.textContent = text;
    box.appendChild(chip);
}

function regSetContactStatements(id, statements) {
    const box = document.getElementById(id);
    if (!box) return;
    box.innerHTML = '';
    box.className = 'reg-contact-check';
    statements.filter(Boolean).forEach(item => {
        const chip = document.createElement('span');
        chip.className = 'reg-contact-statement reg-contact-statement--' + (item.state || 'neutral');
        chip.textContent = item.text || '';
        box.appendChild(chip);
    });
}

function regBuildContactCheckUrl() {
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', 'check_contact');
    return url;
}

let regContactTimer = null;
let regContactSeq = 0;
async function regCheckContacts() {
    const emailEl = document.getElementById('email');
    const phoneEl = document.getElementById('telefono');
    const countryEl = document.getElementById('telefonoCountryCode');
    const email = emailEl?.value?.trim() || '';
    const phoneLocal = phoneEl?.value?.trim() || '';
    regComposePhone();

    if (!email && !phoneLocal) {
        regContactConflict.email = false;
        regContactConflict.phone = false;
        regSetContactMessage('emailCheckMsg', '', '');
        regSetContactMessage('phoneCheckMsg', '', '');
        updateRegisterSubmitState();
        return;
    }

    const seq = ++regContactSeq;
    if (email && email.includes('@')) regSetContactMessage('emailCheckMsg', 'Verificando correo...', 'loading');
    if (phoneLocal && regDigits(phoneLocal).length >= 8) regSetContactMessage('phoneCheckMsg', 'Verificando teléfono...', 'loading');

    try {
        const url = regBuildContactCheckUrl();
        url.searchParams.set('email', email);
        url.searchParams.set('country_code', countryEl?.value || '+52');
        url.searchParams.set('telefono', phoneLocal);
        const response = await fetch(url.toString(), { credentials: 'same-origin' });
        const data = await response.json().catch(() => null);
        if (seq !== regContactSeq || !data?.success) return;

        const emailReady = email !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        regContactConflict.email = !!(emailReady && data.email?.exists);
        if (emailReady) {
            regSetContactStatements('emailCheckMsg', [{
                text: regContactConflict.email
                    ? 'Este correo ya está registrado. Inicia sesión o usa otro correo.'
                    : 'Correo disponible para registro.',
                state: regContactConflict.email ? 'attention' : 'positive'
            }]);
        } else {
            regContactConflict.email = false;
            regSetContactMessage('emailCheckMsg', '', '');
        }

        const phoneReady = phoneLocal !== '' && regDigits(phoneLocal).length >= 8;
        regContactConflict.phone = false;
        if (phoneReady) {
            const phoneStatements = [{
                text: data.telefono?.exists
                    ? 'Este teléfono ya existe en GeriApp, pero puedes continuar si es correcto.'
                    : 'Teléfono disponible para registro.',
                state: data.telefono?.exists ? 'attention' : 'positive'
            }];
            if (data.whatsapp?.checked) {
                if (data.whatsapp.registered === true) {
                    phoneStatements.push({ text: 'WhatsApp activo detectado.', state: 'positive' });
                } else if (data.whatsapp.registered === false) {
                    phoneStatements.push({ text: 'No parece estar registrado en WhatsApp.', state: 'attention' });
                } else {
                    phoneStatements.push({ text: 'No se pudo confirmar WhatsApp en este momento.', state: 'attention' });
                }
            } else if (data.whatsapp?.error) {
                phoneStatements.push({ text: 'La verificación de WhatsApp no está disponible.', state: 'attention' });
            }
            regSetContactStatements('phoneCheckMsg', phoneStatements);
        } else {
            regContactConflict.phone = false;
            regSetContactMessage('phoneCheckMsg', '', '');
        }
    } catch (_) {
        regContactConflict.email = false;
        regContactConflict.phone = false;
        if (email) regSetContactMessage('emailCheckMsg', 'No se pudo verificar ahora; se validará al enviar.', 'error');
        if (phoneLocal) regSetContactMessage('phoneCheckMsg', 'No se pudo verificar ahora; se validará al enviar.', 'error');
    } finally {
        updateRegisterSubmitState();
    }
}

function regScheduleContactCheck() {
    clearTimeout(regContactTimer);
    regContactTimer = setTimeout(regCheckContacts, 380);
}

document.getElementById('email')?.addEventListener('input', regScheduleContactCheck);
document.getElementById('telefono')?.addEventListener('input', regScheduleContactCheck);
document.getElementById('telefonoCountryCode')?.addEventListener('change', regScheduleContactCheck);

// ── Loading state al enviar ───────────────────────────────────────────────────
const regForm    = document.getElementById('regForm');
const submitBtn  = document.getElementById('submitBtn');
const btnText    = document.getElementById('btnText');
const btnSpinner = document.getElementById('btnSpinner');
let   submitted  = false;

function regAllControls() {
    if (!regForm) return [];
    const controls = [...regForm.elements, ...document.querySelectorAll('[form="regForm"]')];
    return [...new Set(controls)].filter(el => el && !el.disabled);
}

function regRequiredControls() {
    return regAllControls().filter(el => {
        if (!el || !el.required || el.disabled) return false;
        if ((el.type || '').toLowerCase() === 'hidden') return false;
        return true;
    });
}

function regIsFormReady() {
    if (!regForm || submitted) return false;
    if (regContactConflict.email) return false;
    const requiredOk = regRequiredControls().every(el => {
        if ((el.type || '').toLowerCase() === 'checkbox') return el.checked;
        return String(el.value || '').trim() !== '' && el.checkValidity();
    });
    const p1 = document.getElementById('password')?.value || '';
    const p2 = document.getElementById('password2')?.value || '';
    return requiredOk && p1.length >= 8 && p1 === p2;
}

function updateRegisterSubmitState() {
    if (!submitBtn) return;
    submitBtn.disabled = !regIsFormReady();
}

regAllControls().forEach(el => {
    el.addEventListener('input', updateRegisterSubmitState);
    el.addEventListener('change', updateRegisterSubmitState);
});

regForm?.addEventListener('submit', function (e) {
    regComposePhone();
    updateRegisterSubmitState();
    if (!regIsFormReady()) {
        e.preventDefault();
        const firstMissing = regRequiredControls().find(el => {
            if ((el.type || '').toLowerCase() === 'checkbox') return !el.checked;
            return String(el.value || '').trim() === '' || !el.checkValidity();
        });
        if (firstMissing) firstMissing.focus();
        return;
    }
    // Validación client-side: consentimiento unificado
    const consentBox = document.getElementById('accept_consent');
    const consentErr = document.getElementById('consentError');
    if (!consentBox.checked) {
        e.preventDefault();
        consentErr.style.display = 'block';
        consentBox.focus();
        return;
    }
    consentErr.style.display = 'none';

    // Validación client-side: contraseñas coinciden
    const p1 = document.getElementById('password').value;
    const p2 = document.getElementById('password2').value;
    if (p1 && p2 && p1 !== p2) {
        e.preventDefault();
        const wrap = document.getElementById('confirmWrap');
        const msg  = document.getElementById('matchMsg');
        wrap.style.borderColor = '#dc2626';
        msg.textContent = '✗ Las contraseñas no coinciden';
        msg.style.color = '#dc2626';
        document.getElementById('password2').focus();
        return;
    }
    // Prevenir doble envío
    if (submitted) { e.preventDefault(); return; }
    submitted = true;

    // Mostrar spinner y deshabilitar form
    btnText.style.display    = 'none';
    btnSpinner.style.display = 'flex';
    submitBtn.disabled       = true;
    submitBtn.style.opacity  = '0.82';
    submitBtn.style.cursor   = 'not-allowed';
    const submitHint = document.getElementById('regSubmitHint');
    if (submitHint) submitHint.style.display = 'block';
    // Note: use readOnly instead of disabled — disabled fields are stripped from POST data
    regForm.querySelectorAll('input:not([type=hidden]), select').forEach(el => {
        el.readOnly = true;
        el.style.pointerEvents = 'none';
    });
});

// ── Al cargar: scroll a error y enfocar campo problemático ────────────────────
(function () {
    const alert     = document.getElementById('regErrorAlert');
    const errorField = '<?= addslashes($errorField) ?>';
    if (!alert) return;

    // Scroll suave hacia la alerta
    setTimeout(() => alert.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);

    // Resaltar campo con error
    if (errorField) {
        const el = document.getElementById(errorField);
        if (el) {
            el.focus();
            const wrap = el.closest('.login-input-wrap');
            if (wrap) wrap.style.borderColor = '#dc2626';
            // Quitar highlight al empezar a escribir
            el.addEventListener('input', () => {
                if (wrap) wrap.style.borderColor = '';
            }, { once: true });
        }
    }
})();

// ── Ver Términos y Condiciones (modal con firma) ──────────────────────────────
const REG_BASE = '<?= BASE_URL ?>';
const REG_INST = <?= json_encode($inv ? (int)$inv['institucion_id'] : 0) ?>;

function regSanitizeHtml(raw) {
    if (!raw) return '';
    if (!/<[a-z][\s\S]*?>/i.test(raw)) {
        const d = document.createElement('div'); d.textContent = raw;
        return d.innerHTML.replace(/\n/g, '<br>');
    }
    const allow = {B:1,STRONG:1,I:1,EM:1,U:1,BR:1,P:1,DIV:1,SPAN:1,UL:1,OL:1,LI:1,H1:1,H2:1,H3:1,H4:1,H5:1,H6:1,BLOCKQUOTE:1,PRE:1,CODE:1,HR:1,SUB:1,SUP:1,A:1};
    const safeAttrs = { A: ['href','target','rel'] };
    const tmp = document.createElement('div');
    tmp.innerHTML = raw;
    (function clean(parent) {
        [...parent.childNodes].forEach(n => {
            if (n.nodeType === 3) return;
            if (n.nodeType === 1) {
                if (!allow[n.tagName]) { while (n.firstChild) parent.insertBefore(n.firstChild, n); parent.removeChild(n); return; }
                const kept = safeAttrs[n.tagName] || [];
                [...n.attributes].forEach(a => { if (!kept.includes(a.name) || (a.name === 'href' && /^\s*javascript:/i.test(a.value))) n.removeAttribute(a.name); });
                clean(n);
            } else parent.removeChild(n);
        });
    })(tmp);
    return tmp.innerHTML;
}

/**
 * Abre modal de documento legal con firma manuscrita.
 * @param {'terminos'|'privacidad'} tipo
 * @param {string} checkboxId — ID del checkbox a marcar
 * @param {string} firmaFieldId — ID del hidden input para firma_data
 * @param {string} docIdFieldId — ID del hidden input para doc_id
 */
function regShowLegalModal(tipo, checkboxId, firmaFieldId, docIdFieldId) {
    const label = tipo === 'terminos' ? 'Términos y Condiciones' : 'Aviso de Privacidad';

    if (!REG_INST) {
        alert(label + ' estarán disponibles una vez vincule su cuenta a una institución.');
        return;
    }

    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px;';
    overlay.innerHTML = `
    <style>
        .reg-legal-modal { background:#fff;border-radius:14px;max-width:640px;width:100%;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);font-family:'Open Sans',system-ui,sans-serif }
        .reg-legal-header { padding:18px 24px 12px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center }
        .reg-legal-header h2 { margin:0;font-size:1.05rem;color:#1e293b }
        .reg-legal-body { flex:1;overflow-y:auto;padding:20px 24px;font-size:0.8125rem;line-height:1.7;color:#334155 }
        .reg-legal-body h1,.reg-legal-body h2,.reg-legal-body h3,.reg-legal-body h4 { color:#1e293b;margin:16px 0 8px }
        .reg-legal-body h1 { font-size:1.25rem } .reg-legal-body h2 { font-size:1.1rem } .reg-legal-body h3 { font-size:1rem }
        .reg-legal-body p { margin:0 0 10px } .reg-legal-body ul,.reg-legal-body ol { padding-left:20px;margin:0 0 12px }
        .reg-legal-body blockquote { border-left:3px solid #cbd5e1;padding:8px 12px;margin:12px 0;color:#475569;background:#f8fafc;border-radius:0 6px 6px 0 }
        .reg-legal-body a { color:#178391;text-decoration:underline }
        .reg-legal-pad { padding:16px 24px 20px;border-top:1px solid #e2e8f0;background:#f8fafc }
        .reg-legal-canvas-wrap { position:relative;border:2px dashed #cbd5e1;border-radius:10px;background:#fff;overflow:hidden }
        .reg-legal-canvas-wrap canvas { display:block;width:100%;height:120px;cursor:crosshair;touch-action:none }
        .reg-legal-clear { position:absolute;top:6px;right:6px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:2px 8px;font-size:.7rem;cursor:pointer;color:#64748b }
        .reg-legal-clear:hover { background:#e2e8f0 }
        .reg-legal-submit { width:100%;padding:10px;background:#178391;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:inherit;margin-top:12px;opacity:.5;transition:opacity .2s }
        .reg-legal-submit:not(:disabled) { opacity:1 }
        .reg-legal-submit:not(:disabled):hover { background:#15687f }
        @media(max-width:500px){ .reg-legal-modal { max-height:95vh } .reg-legal-body { padding:14px 16px } .reg-legal-pad { padding:12px 16px 16px } }
    </style>
    <div class="reg-legal-modal">
        <div class="reg-legal-header">
            <h2>${label} <span style="font-size:.78rem;color:#94a3b8" id="regLegalVer"></span></h2>
            <button id="regLegalClose" style="background:none;border:none;cursor:pointer;padding:4px;color:#94a3b8;font-size:1.3rem" title="Cerrar">&times;</button>
        </div>
        <div class="reg-legal-body" id="regLegalBody">
            <p style="color:#94a3b8;text-align:center">Cargando…</p>
        </div>
        <div class="reg-legal-pad">
            <p style="margin:0 0 8px;font-size:.8rem;font-weight:600;color:#334155">Firma de aceptación</p>
            <div class="reg-legal-canvas-wrap">
                <canvas id="regSignCanvas" width="500" height="120"></canvas>
                <button type="button" class="reg-legal-clear" id="regSignClear">Limpiar</button>
            </div>
            <button type="button" class="reg-legal-submit" id="regSignAccept" disabled>Acepto y firmo</button>
            <p style="text-align:center;margin:8px 0 0;font-size:.68rem;color:#94a3b8">Al firmar, acepto los términos del documento. Se enviará una copia a mi correo al completar el registro.</p>
        </div>
    </div>`;
    document.body.appendChild(overlay);

    // Close
    overlay.querySelector('#regLegalClose').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (ev) => { if (ev.target === overlay) overlay.remove(); });

    // Fetch document
    fetch(`${REG_BASE}/api/documentos_legales.php?action=public_vigente&tipo=${tipo}&inst=${REG_INST}`)
        .then(r => r.json()).then(res => {
            const doc = res?.data || res;
            const body = overlay.querySelector('#regLegalBody');
            if (doc?.contenido) {
                body.innerHTML = regSanitizeHtml(doc.contenido);
                overlay.querySelector('#regLegalVer').textContent = doc.version ? 'v' + doc.version : '';
                // Store doc id
                document.getElementById(docIdFieldId).value = doc.id || '';
            } else {
                body.innerHTML = '<p style="color:#94a3b8;text-align:center">No hay ' + label + ' configurados para esta institución.</p>';
            }
        }).catch(() => {
            overlay.querySelector('#regLegalBody').innerHTML = '<p style="color:#94a3b8;text-align:center">No se pudo cargar el documento.</p>';
        });

    // Signature canvas
    const canvas = overlay.querySelector('#regSignCanvas');
    const ctx = canvas.getContext('2d');
    const acceptBtn = overlay.querySelector('#regSignAccept');
    let drawing = false, hasStroke = false;

    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    canvas.style.width = rect.width + 'px';
    canvas.style.height = rect.height + 'px';
    ctx.strokeStyle = '#222';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const t = e.touches?.[0];
        return { x: (t?.clientX || e.clientX) - r.left, y: (t?.clientY || e.clientY) - r.top };
    }
    function startDraw(e) { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
    function doDraw(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasStroke = true; acceptBtn.disabled = false; }
    function endDraw() { drawing = false; }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', doDraw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', doDraw, { passive: false });
    canvas.addEventListener('touchend', endDraw);

    overlay.querySelector('#regSignClear').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasStroke = false;
        acceptBtn.disabled = true;
    });

    acceptBtn.addEventListener('click', () => {
        if (!hasStroke) return;
        const firmaData = canvas.toDataURL('image/png');
        document.getElementById(firmaFieldId).value = firmaData;
        overlay.remove();

        // Check if both T&C and AdP are now signed
        const tcSigned  = document.getElementById('tc_firma_data').value !== '';
        const adpSigned = document.getElementById('adp_firma_data').value !== '';

        if (tcSigned && adpSigned) {
            document.getElementById(checkboxId).checked = true;
            document.getElementById(checkboxId).dispatchEvent(new Event('change'));
        } else if (tcSigned && !adpSigned) {
            regShowLegalModal('privacidad', checkboxId, 'adp_firma_data', 'adp_doc_id');
        } else if (!tcSigned && adpSigned) {
            regShowLegalModal('terminos', checkboxId, 'tc_firma_data', 'tc_doc_id');
        }
    });
}

document.getElementById('regViewTerms')?.addEventListener('click', function(e) {
    e.preventDefault();
    regShowLegalModal('terminos', 'accept_consent', 'tc_firma_data', 'tc_doc_id');
});

document.getElementById('regViewPrivacy')?.addEventListener('click', function(e) {
    e.preventDefault();
    regShowLegalModal('privacidad', 'accept_consent', 'adp_firma_data', 'adp_doc_id');
});

// ── Consentimiento legal ─────────────────────────────────────────────────────
document.getElementById('accept_consent')?.addEventListener('click', function() {
    if (!this.checked) {
        document.getElementById('tc_firma_data').value = '';
        document.getElementById('tc_doc_id').value = '';
        document.getElementById('adp_firma_data').value = '';
        document.getElementById('adp_doc_id').value = '';
    }
});
// ── Limpiar error + habilitar botón al marcar checkbox ───────────────────────────────
document.getElementById('accept_consent')?.addEventListener('change', function() {
    if (this.checked) document.getElementById('consentError').style.display = 'none';
    updateRegisterSubmitState();
});
regComposePhone();
regScheduleContactCheck();
updateRegisterSubmitState();
</script>

<?php require_once 'includes/foot.php'; ?>
