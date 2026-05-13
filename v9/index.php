<?php
require_once 'conf/config.php';

// ── Ya autenticado → redirigir ────────────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

// ── Mensajes de estado ────────────────────────────────────────────────────
$loginError = '';
if (isset($_GET['bye']))        { $loginError = ''; $loginInfo = 'Sesión cerrada correctamente.'; }
if (isset($_GET['registered']))  {
    $loginInfo = isset($_GET['linked'])
        ? '¡Invitación aceptada! Tu cuenta ya fue vinculada a la nueva institución. Inicia sesión.'
        : '¡Cuenta creada! Ya puedes iniciar sesión.';
}
if (isset($_GET['reset']))       { $loginInfo = '✓ Contraseña actualizada correctamente. Ya puedes iniciar sesión.'; }
if (isset($_GET['error']) && $_GET['error'] === 'inactivo') { $loginError = 'Tu cuenta está desactivada. Contacta al administrador.'; }
if (isset($_GET['expired']))    { $loginInfo = 'Tu sesión expiró por inactividad. Por favor, inicia sesión de nuevo.'; }
$loginInfo = $loginInfo ?? '';

// ── Procesar POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db/Database.php';
    $db    = Database::getInstance();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Detect native/AJAX callers that want JSON instead of redirect
    $_wantsJson = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    );

    // §1.9 Validar CSRF en login
    // Apps nativas (Capacitor iOS/Android) envían X-Native-App: 1.
    // WKWebView iOS no sincroniza cookies de sesión en el primer page load,
    // lo que rompe la validación CSRF (sesión de csrf.php ≠ sesión del POST).
    // X-Native-App solo se puede enviar via JS fetch (no form submit), y
    // CORS bloquea custom headers cross-origin → no es vector CSRF.
    $_isNativeApp = !empty($_SERVER['HTTP_X_NATIVE_APP']);
    // Legacy: también aceptar Origin: capacitor:// (Capacitor sin server.url)
    $_isCapacitor = !empty($_SERVER['HTTP_ORIGIN'])
                 && str_starts_with($_SERVER['HTTP_ORIGIN'], 'capacitor://');
    if (!$_isNativeApp && !$_isCapacitor && !csrf_validate($_POST['_csrf'] ?? '')) {
        // CSRF falló — lo más común tras inactividad: la sesión expiró y el
        // token del form ya no coincide con el de la sesión recién iniciada.
        // No redireccionamos (perderíamos el email tecleado): re-renderizamos
        // en sitio con un mensaje claro y un token fresco. El usuario solo
        // tendrá que volver a escribir la contraseña.
        $loginError = 'Tu sesión de seguridad expiró por inactividad. Vuelve a intentar (tu correo se conservó).';
        if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$loginError,'csrf'=>csrf_token()]); exit; }
        // Fall-through al render del formulario (fuera del if POST).
    } else {

    // Rate limiting — load security config from user's primary institution
    require_once 'db/models/Configuracion.php';
    // Pre-fetch user to get institution for security config
    $stmtUser = $db->prepare("SELECT institucion_id FROM usuarios WHERE email = ? LIMIT 1");
    $stmtUser->execute([$email]);
    $_loginInstId = (int)($stmtUser->fetchColumn() ?: 0);
    $_secCfg = $_loginInstId ? (Configuracion::getByInstitucion($_loginInstId) ?: []) : [];
    $_maxAttempts = max(1, (int)($_secCfg['seg_max_intentos'] ?? 5));
    $_blockMin    = max(1, (int)($_secCfg['seg_bloqueo_min'] ?? 15));
    $_passMinLen  = max(4, (int)($_secCfg['seg_pass_min_len'] ?? 8));
    $_sessionMin  = max(5, (int)($_secCfg['seg_timeout_sesion'] ?? 60));

    $window = date('Y-m-d H:i:s', strtotime("-{$_blockMin} minutes"));
    $stmt   = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND exitoso = 0 AND creado_at > ?");
    $stmt->execute([$email, $ip, $window]);
    if ((int)$stmt->fetchColumn() >= $_maxAttempts) {
        $loginError = "Demasiados intentos fallidos. Espera {$_blockMin} minutos e intenta de nuevo.";
        if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$loginError]); exit; }
    } else {
        $stmt = $db->prepare("
            SELECT u.*, i.nombre AS inst_nombre
            FROM usuarios u
            LEFT JOIN instituciones i ON i.id = u.institucion_id
            WHERE u.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            if ($user['estado'] !== 'activo') {
                $loginError = 'Tu cuenta está desactivada. Contacta al administrador.';
                if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$loginError]); exit; }
            } else {
                // Éxito: regenerar sesión y guardar datos base
                session_regenerate_id(true);
                $_SESSION['user_id']          = (int)$user['id'];
                $_SESSION['user_nombre']      = $user['nombre'];
                $_SESSION['user_email']       = $user['email'];
                $_SESSION['user_estado']      = $user['estado'];
                $_SESSION['user_avatar_path'] = $user['avatar_path'] ?? '';

                // §1.11 Verificar complejidad de contraseña (gradual enforcement)
                if (validate_password_strength($pass, $_passMinLen) !== null) {
                    $_SESSION['password_change_required'] = true;
                }

                // Store configured session timeout (seconds)
                $_SESSION['_cfg_session_timeout'] = $_sessionMin * 60;

                // Registrar acceso
                $db->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,1)")->execute([$email, $ip]);
                $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$user['id']]);

                // Registrar sesión activa
                $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
                try {
                    $db->prepare("INSERT INTO sesiones_activas (usuario_id, session_id, ip, user_agent) VALUES (?,?,?,?)
                                  ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), ultimo_acceso = NOW()")
                       ->execute([(int)$user['id'], session_id(), $ip, $ua]);
                } catch (\Throwable $e) { /* table may not exist yet */ }

                if ($user['rol'] === 'superadmin') {
                    // ── Superadmin: sin institución activa ───────────────────
                    $_SESSION['user_rol']                = 'superadmin';
                    $_SESSION['user_institucion_id']     = null;
                    $_SESSION['user_institucion_nombre'] = '';                    $_SESSION['user_multi_inst']         = false;
                    $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,NULL,'sesion_iniciar','Auth',?,'ok')")
                       ->execute([$user['id'], $ip]);

                    if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'redirect'=> BASE_URL . '/cuidados.php']); exit; }
                    header('Location: ' . BASE_URL . '/cuidados.php');
                    exit;
                }

                // ── Obtener instituciones del usuario (pivot) ────────────────
                $stmtPivot = $db->prepare(
                    "SELECT ui.institucion_id, ui.rol, ui.estado AS ui_estado,
                            i.nombre AS inst_nombre, i.estado AS inst_estado
                     FROM usuario_instituciones ui
                     JOIN instituciones i ON i.id = ui.institucion_id
                     WHERE ui.usuario_id = ?
                       AND ui.estado = 'activo'
                       AND i.estado NOT IN ('suspendida','archivada')
                     ORDER BY i.nombre ASC"
                );
                $stmtPivot->execute([$user['id']]);
                $instituciones = $stmtPivot->fetchAll(PDO::FETCH_ASSOC);

                // Fallback: si la tabla pivot no tiene datos, usar usuarios.institucion_id
                if (empty($instituciones) && $user['institucion_id']) {
                    $instituciones = [[
                        'institucion_id' => $user['institucion_id'],
                        'rol'            => $user['rol'],
                        'inst_nombre'    => $user['inst_nombre'] ?? '',
                        'inst_estado'    => 'activa',
                    ]];
                }

                if (empty($instituciones)) {
                    $loginError = 'Tu usuario no pertenece a ninguna institución activa.';
                    if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$loginError]); exit; }
                } elseif (count($instituciones) === 1) {
                    // ── Una sola institución: entrar directo ─────────────────
                    $inst = $instituciones[0];
                    $_SESSION['user_rol']                = $inst['rol'];
                    $_SESSION['user_institucion_id']     = (int)$inst['institucion_id'];
                    $_SESSION['user_institucion_nombre'] = $inst['inst_nombre'];                    $_SESSION['user_multi_inst']         = false;
                    $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,?,'sesion_iniciar','Auth',?,'ok')")
                       ->execute([$user['id'], $inst['institucion_id'], $ip]);

                    if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'redirect'=> BASE_URL . '/cuidados.php']); exit; }
                    header('Location: ' . BASE_URL . '/cuidados.php');
                    exit;
                } else {
                    // ── Múltiples instituciones: mostrar selector ─────────────
                    // Guardar lista en sesión para que select_institucion.php la use
                    $_SESSION['pending_instituciones']   = $instituciones;
                    // rol temporal: el del primer resultado (se sobreescribirá al seleccionar)
                    $_SESSION['user_rol']                = $instituciones[0]['rol'];
                    $_SESSION['user_institucion_id']     = null;
                    $_SESSION['user_institucion_nombre'] = '';
                    $_SESSION['user_multi_inst']         = true;

                    $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado) VALUES (?,NULL,'sesion_iniciar','Auth',?,'ok')")
                       ->execute([$user['id'], $ip]);

                    if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'redirect'=> BASE_URL . '/select_institucion.php']); exit; }
                    header('Location: ' . BASE_URL . '/select_institucion.php');
                    exit;
                }
            } // end else (estado activo)
        } else {
            $db->prepare("INSERT INTO login_attempts (email, ip, exitoso) VALUES (?,?,0)")->execute([$email, $ip]);
            // §4.6 Alerta de seguridad por login fallido
            try { security_alert('login_fallido', ['email' => $email]); } catch (\Throwable $e) {}
            $loginError = 'Correo electrónico o contraseña incorrectos.';
            if ($_wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$loginError]); exit; }
        }
    } // end else (rate limit OK)
    } // end else (CSRF válido)
}

$pageTitle = 'Iniciar Sesión';
$bodyClass = 'login-page';
require_once 'includes/head.php';
?>

<div class="login-outer">
    <div class="login-card">

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
            <p class="login-app-sub">Ingresa a tu cuenta</p>
        </div>

        <!-- Alertas -->
        <?php if ($loginError): ?>
        <div class="login-alert login-alert--error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php elseif ($loginInfo): ?>
        <div class="login-alert login-alert--info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= htmlspecialchars($loginInfo) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="<?= BASE_URL ?>/index.php" method="post" autocomplete="off" class="login-form">
            <?= csrf_field() ?>

            <!-- Email -->
            <div class="login-field">
                <label for="email">Correo Electrónico <span class="login-required">*</span></label>
                <div class="login-input-wrap">
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
                           class="login-input"
                           placeholder="tu@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <!-- Password -->
            <div class="login-field">
                <label for="password">Contraseña <span class="login-required">*</span></label>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password"
                           class="login-input"
                           placeholder="••••••••"
                           required>
                    <button type="button" class="login-eye-btn" id="togglePass"
                            title="Mostrar contraseña" tabindex="-1">
                        <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8
                                     -4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="login-forgot">
                    <a href="<?= BASE_URL ?>/forgot-password.php">¿Olvidaste tu contraseña?</a>
                </div>
            </div>

            <button type="submit" class="login-btn">Iniciar Sesión</button>

        </form>

        <!-- Footer links -->
        <div class="login-links">
            <!-- Registration disabled — invite-only access -->
            <!-- <p>¿No tienes cuenta? <a href="<?= BASE_URL ?>/register.php">Regístrate aquí</a></p> -->
            <p><a href="#" class="login-back">← Volver al inicio</a></p>
        </div>

    </div>
</div>

<script>
document.getElementById('togglePass').addEventListener('click', function () {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.innerHTML = show
        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
});

// ═══════════════════════════════════════════════════════════════════
// GeriApp — Autenticación Biométrica (NativeBiometric)
// ═══════════════════════════════════════════════════════════════════
(function() {
    'use strict';

    const BASE      = window.GERIAPP_BASE || '';
    const BIO_KEY   = 'geriapp_bio_enabled';   // localStorage flag
    const CRED_SRV  = 'com.geriapp.login';     // Keychain/Keystore server id

    // ── Helpers ──────────────────────────────────────────────────────
    function _isNative() {
        const native = !!(window.Capacitor && window.Capacitor.isNativePlatform());
        if (native) {
            try { localStorage.setItem('geriappNativeApp', '1'); } catch (_) {}
        }
        return native;
    }

    function _getPlugin() {
        if (!_isNative()) return null;
        const p = window.Capacitor.Plugins.NativeBiometric;
        return (p && typeof p.isAvailable === 'function') ? p : null;
    }

    // ── 1. Verificar disponibilidad biométrica ──────────────────────
    window._biometricIsAvailable = async function() {
        const bio = _getPlugin();
        if (!bio) return { available: false, reason: 'not_native' };
        try {
            const result = await bio.isAvailable();
            // result: { isAvailable: bool, biometryType: 1|2|3|4 }
            return {
                available:     result.isAvailable,
                biometryType:  result.biometryType,  // 1=Touch, 2=Face, 3=Iris, 4=Multiple
                reason:        result.isAvailable ? 'ok' : 'not_configured'
            };
        } catch (e) {
            console.warn('[GeriApp Bio] isAvailable error:', e);
            return { available: false, reason: 'error', error: e.message };
        }
    };

    // ── 2. Autenticar con biometría (diálogo huella/Face) ───────────
    window._biometricVerify = async function() {
        const bio = _getPlugin();
        if (!bio) throw new Error('Plugin no disponible');
        try {
            await bio.verifyIdentity({
                reason:           'Inicia sesión en GeriApp',
                title:            'Autenticación Biométrica',
                subtitle:         'Verifica tu identidad',
                description:      'Usa tu huella o rostro para acceder',
                negativeButtonText: 'Cancelar',
                maxAttempts:      3,
            });
            return true;
        } catch (e) {
            // Códigos comunes del plugin
            const code = e.code || e.message || '';
            if (code === '10' || code === 'userCancel' || /cancel/i.test(e.message)) {
                throw Object.assign(new Error('Autenticación cancelada por el usuario'), { code: 'USER_CANCEL' });
            }
            if (code === '14' || /not.*(enroll|available|configured)/i.test(e.message)) {
                throw Object.assign(new Error('Biometría no configurada en el dispositivo'), { code: 'NOT_CONFIGURED' });
            }
            if (code === '11' || /fail|attempt/i.test(e.message)) {
                throw Object.assign(new Error('Fallo de autenticación biométrica'), { code: 'AUTH_FAILED' });
            }
            throw Object.assign(new Error('Error biométrico: ' + e.message), { code: 'UNKNOWN' });
        }
    };

    // ── 3. Guardar credenciales en Keychain/Keystore ────────────────
    window._biometricSetCredentials = async function(username, password) {
        const bio = _getPlugin();
        if (!bio) throw new Error('Plugin no disponible');
        await bio.setCredentials({
            server:   CRED_SRV,
            username: username,
            password: password,
        });
        localStorage.setItem(BIO_KEY, '1');
        console.log('[GeriApp Bio] Credenciales guardadas');
    };

    // ── 4. Obtener credenciales ─────────────────────────────────────
    window._biometricGetCredentials = async function() {
        const bio = _getPlugin();
        if (!bio) throw new Error('Plugin no disponible');
        const creds = await bio.getCredentials({ server: CRED_SRV });
        return { username: creds.username, password: creds.password };
    };

    // ── 5. Eliminar credenciales (logout) ───────────────────────────
    window._biometricDeleteCredentials = async function() {
        const bio = _getPlugin();
        if (!bio) return;
        try {
            await bio.deleteCredentials({ server: CRED_SRV });
        } catch (e) {
            console.warn('[GeriApp Bio] deleteCredentials:', e);
        }
        localStorage.removeItem(BIO_KEY);
        console.log('[GeriApp Bio] Credenciales eliminadas');
    };

    // ── 6. ¿Está la biometría activada por el usuario? ──────────────
    window._biometricIsEnrolled = function() {
        return localStorage.getItem(BIO_KEY) === '1';
    };

    // ═════════════════════════════════════════════════════════════════
    // Login page integration
    // ═════════════════════════════════════════════════════════════════

    const form     = document.querySelector('.login-form');
    const emailInp = document.getElementById('email');
    const passInp  = document.getElementById('password');
    const loginBtn = form ? form.querySelector('.login-btn') : null;
    if (!form || !emailInp || !passInp) return;

    // ── Intercept form → fetch para capturar credenciales ───────────
    form.addEventListener('submit', async function(e) {
        // Loader for all paths (web + native)
        loginBtn.disabled = true;
        loginBtn.dataset.orig = loginBtn.textContent;
        loginBtn.innerHTML = '<span class="login-spinner"></span> Ingresando…';

        if (!_isNative()) return; // web: let the normal form POST happen

        e.preventDefault();

        const email    = emailInp.value.trim();
        const password = passInp.value;
        const csrf     = form.querySelector('input[name="_csrf"]');

        try {
            // §1.9 Refresh CSRF token before login (session may have expired)
            try {
                const tokenRes = await fetch(BASE + '/api/csrf.php', { credentials: 'include' });
                const tokenData = await tokenRes.json();
                if (tokenData.token && csrf) csrf.value = tokenData.token;
            } catch (_) { /* proceed with existing token */ }

            const params = { email, password };
            if (csrf) params._csrf = csrf.value;

            const _nativeHeaders = _isNative()
                ? { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Native-App': '1' }
                : { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

            const _doLogin = async () => {
                const loginParams = { email, password };
                if (csrf) loginParams._csrf = csrf.value;
                return fetch(form.action || (BASE + '/index.php'), {
                    method:      'POST',
                    body:        new URLSearchParams(loginParams),
                    credentials: 'include',
                    headers:     _nativeHeaders,
                });
            };

            let res = await _doLogin();

            let data;
            try { data = await res.json(); } catch (_) { data = null; }

            // §1.9 Auto-retry on CSRF failure: refresh token + retry once
            if (data && !data.ok && (data.error || '').toLowerCase().includes('token')) {
                try {
                    const retryToken = await fetch(BASE + '/api/csrf.php', { credentials: 'include' });
                    const retryData = await retryToken.json();
                    if (retryData.token && csrf) csrf.value = retryData.token;
                    res = await _doLogin();
                    try { data = await res.json(); } catch (_) { data = null; }
                } catch (_) { /* use first response */ }
            }

            if (!data) {
                // Fallback: couldn't parse JSON (shouldn't happen now)
                // If we got a redirect or 200, session might still be set — try navigating
                window.location.href = BASE + '/cuidados.php';
                return;
            }

            if (!data.ok) {
                _showBioAlert(data.error || 'Correo o contraseña incorrectos.', 'error');
                loginBtn.disabled = false;
                loginBtn.innerHTML = loginBtn.dataset.orig || 'Iniciar Sesión';
                return;
            }

            // Login succeeded — offer biometric enrollment if not yet enrolled
            if (!window._biometricIsEnrolled()) {
                const check = await window._biometricIsAvailable();
                if (check.available) {
                    const accepted = await _showBioConfirm(
                        '¿Activar inicio de sesión con huella/rostro?',
                        'En tu próximo ingreso podrás entrar sin escribir tu contraseña.'
                    );
                    if (accepted) {
                        try {
                            await window._biometricSetCredentials(email, password);
                            _showBioAlert('¡Biometría activada! La próxima vez podrás ingresar con tu huella o rostro.', 'success');
                            await _sleep(1800);
                        } catch (bioErr) {
                            console.error('[GeriApp Bio] Error al guardar credenciales:', bioErr);
                        }
                    }
                }
            }

            // Navigate to the redirect destination
            window.location.href = data.redirect || (BASE + '/cuidados.php');

        } catch (netErr) {
            console.error('[GeriApp Bio] Network error:', netErr);
            _showBioAlert('Error de conexión. Verifica tu internet.', 'error');
            loginBtn.disabled = false;
            loginBtn.innerHTML = loginBtn.dataset.orig || 'Iniciar Sesión';
        }
    });

    // ── Auto-login with biometrics on page load ─────────────────────
    async function _tryBiometricLogin() {
        if (!_isNative() || !window._biometricIsEnrolled()) return;

        const check = await window._biometricIsAvailable();
        if (!check.available) return;

        // Show biometric button
        _showBiometricButton();

        // Small delay to ensure the page is fully rendered
        await _sleep(600);
        _doBiometricLogin();
    }

    async function _doBiometricLogin() {
        try {
            await window._biometricVerify();
            const creds = await window._biometricGetCredentials();

            loginBtn.disabled = true;
            loginBtn.textContent = 'Ingresando…';
            emailInp.value = creds.username;
            passInp.value  = creds.password;

            // Submit via fetch to follow the same intercepted path
            form.requestSubmit();
        } catch (bioErr) {
            if (bioErr.code === 'USER_CANCEL') {
                // User cancelled — do nothing, they can type manually
                return;
            }
            if (bioErr.code === 'NOT_CONFIGURED') {
                _showBioAlert('La biometría ya no está configurada en tu dispositivo. Inicia sesión con tu contraseña.', 'error');
                window._biometricDeleteCredentials();
                return;
            }
            if (bioErr.code === 'AUTH_FAILED') {
                _showBioAlert('Autenticación biométrica fallida. Intenta de nuevo o usa tu contraseña.', 'error');
                return;
            }
            console.error('[GeriApp Bio] Login error:', bioErr);
            _showBioAlert('Error biométrico. Usa tu contraseña.', 'error');
        }
    }

    // ── Biometric login button in the UI ────────────────────────────
    function _showBiometricButton() {
        if (document.getElementById('bioLoginBtn')) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id   = 'bioLoginBtn';
        btn.className = 'login-btn';
        btn.style.cssText = 'margin-top:10px;background:linear-gradient(135deg,#178391,#9db9d0);display:flex;align-items:center;justify-content:center;gap:8px;';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            + '<path d="M12 11c0-1.1-.9-2-2-2s-2 .9-2 2 2 4 2 4"/>'
            + '<path d="M6.13 7.92A6.98 6.98 0 0 1 12 5c3.87 0 7 3.13 7 7 0 1.68-.47 3.25-1.29 4.58"/>'
            + '<path d="M3.51 11.72A10 10 0 0 1 12 2c5.52 0 10 4.48 10 10 0 2.4-.85 4.6-2.26 6.33"/>'
            + '<path d="M2 12a10 10 0 0 0 2.76 6.9"/>'
            + '</svg> Ingresar con biometría';
        btn.addEventListener('click', _doBiometricLogin);
        loginBtn.parentNode.insertBefore(btn, loginBtn.nextSibling);
    }

    // ── UI helpers ──────────────────────────────────────────────────
    function _showBioAlert(msg, type) {
        let el = document.getElementById('bioAlert');
        if (!el) {
            el = document.createElement('div');
            el.id = 'bioAlert';
            const card = document.querySelector('.login-card');
            const formEl = card.querySelector('.login-form');
            card.insertBefore(el, formEl);
        }
        el.className = 'login-alert login-alert--' + (type === 'success' ? 'info' : 'error');
        el.textContent = msg;
        el.style.display = '';
        if (type === 'success') setTimeout(() => { el.style.display = 'none'; }, 4000);
    }

    function _showBioConfirm(title, msg) {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:20px;';
            overlay.innerHTML = '<div style="background:#fff;border-radius:16px;padding:28px 24px;max-width:340px;width:100%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.3);">'
                + '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#178391" stroke-width="1.5" style="margin-bottom:12px;">'
                + '<path d="M12 11c0-1.1-.9-2-2-2s-2 .9-2 2 2 4 2 4"/>'
                + '<path d="M6.13 7.92A6.98 6.98 0 0 1 12 5c3.87 0 7 3.13 7 7 0 1.68-.47 3.25-1.29 4.58"/>'
                + '<path d="M3.51 11.72A10 10 0 0 1 12 2c5.52 0 10 4.48 10 10 0 2.4-.85 4.6-2.26 6.33"/>'
                + '</svg>'
                + '<h3 style="margin:0 0 8px;font-size:1.05rem;color:#1e293b;">' + title + '</h3>'
                + '<p style="margin:0 0 20px;font-size:.85rem;color:#64748b;">' + msg + '</p>'
                + '<div style="display:flex;gap:10px;justify-content:center;">'
                + '<button id="_bioNo" style="flex:1;padding:10px;border:1px solid #e2e8f0;background:#fff;border-radius:10px;font-size:.9rem;cursor:pointer;color:#64748b;">No, gracias</button>'
                + '<button id="_bioYes" style="flex:1;padding:10px;border:none;background:linear-gradient(135deg,#178391,#9db9d0);color:#fff;border-radius:10px;font-size:.9rem;cursor:pointer;font-weight:600;">Activar</button>'
                + '</div></div>';
            document.body.appendChild(overlay);
            overlay.querySelector('#_bioYes').addEventListener('click', () => { overlay.remove(); resolve(true); });
            overlay.querySelector('#_bioNo').addEventListener('click',  () => { overlay.remove(); resolve(false); });
        });
    }

    function _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    // ── Init ────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(_tryBiometricLogin, 300));
    } else {
        setTimeout(_tryBiometricLogin, 300);
    }

})();
</script>

<?php require_once 'includes/foot.php'; ?>
