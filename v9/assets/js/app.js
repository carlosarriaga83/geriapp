/* =====================================================
   GeriApp — JavaScript Principal
   ===================================================== */

/* ── Theme ─────────────────────────────────────────── */
async function _gSyncStatusBar(isDark) {
    try {
        const SB = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.StatusBar;
        if (!SB) return;
        // @capacitor/status-bar Style se refiere al FONDO, no al texto:
        //   'DARK'  → fondo oscuro → texto/íconos claros
        //   'LIGHT' → fondo claro  → texto/íconos oscuros
        if (isDark) {
            await SB.setStyle({ style: 'DARK' });
            if (SB.setBackgroundColor) await SB.setBackgroundColor({ color: '#111111' });
        } else {
            await SB.setStyle({ style: 'LIGHT' });
            if (SB.setBackgroundColor) await SB.setBackgroundColor({ color: '#FFFFFF' });
        }
        if (SB.setOverlaysWebView) await SB.setOverlaysWebView({ overlay: false });
    } catch (e) { /* silent: no nativo o plugin no disponible */ }
}

function _gApplyTheme(mode, save) {
    const isDark = mode === 'dark';
    document.documentElement.setAttribute('data-theme', mode);
    if (save) localStorage.setItem('geriappTheme', mode);
    const moon  = document.getElementById('themeIconMoon');
    const sun   = document.getElementById('themeIconSun');
    const label = document.getElementById('themeToggleLabel');
    if (moon)  moon.style.display  = isDark ? 'none' : '';
    if (sun)   sun.style.display   = isDark ? '' : 'none';
    if (label) label.textContent   = isDark ? 'Tema claro' : 'Tema oscuro';
    _gSyncStatusBar(isDark);
}
function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    _gApplyTheme(cur === 'dark' ? 'light' : 'dark', true);
}

document.addEventListener('DOMContentLoaded', function () {
    // Init theme (restore persisted preference + update icons)
    _gApplyTheme(document.documentElement.dataset.forceTheme || localStorage.getItem('geriappTheme') || 'light', false);
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

    // Apple §3.1.1 / Google Play: ocultar enlaces a /billing.php cuando
    // estamos dentro de una WebView nativa (Capacitor). El estado de la
    // suscripción se sigue mostrando en la página /billing si entran por URL,
    // pero los botones de compra/portal no se renderizan (el endpoint también
    // bloquea con 403 vía cabecera X-Capacitor-Native).
    try {
        if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) {
            document.querySelectorAll('#cdProfileBillingCard, [data-hide-on-native]').forEach(function (n) {
                n.style.display = 'none';
            });
        }
    } catch (e) { /* no-op */ }
});

// -----------------------------------------------
// PUSH NOTIFICATIONS (Capacitor native)
// Estrategia:
//   1. Al recibir el token, guardarlo en localStorage.
//   2. Exponer geriappFlushPushToken() globalmente.
//   3. Las páginas autenticadas llaman a esta función
//      para enviarlo al backend cuando la sesión ya
//      está activa (evita 401 en página de login).
// -----------------------------------------------

// Diagnostic log stored in localStorage for debugging without console
window._pushDiag = JSON.parse(localStorage.getItem('_ga_push_diag') || '{}');
function _pushLog(key, val) {
    window._pushDiag[key] = val;
    window._pushDiag._ts = new Date().toISOString();
    try { localStorage.setItem('_ga_push_diag', JSON.stringify(window._pushDiag)); } catch(e) {}
}

(function setupPushNotifications() {

    function _initPush() {
        _pushLog('bridge_exists', typeof window.Capacitor !== 'undefined');
        if (typeof window.Capacitor === 'undefined') {
            _pushLog('status', 'no_capacitor_bridge');
            return;
        }
        _pushLog('is_native', window.Capacitor.isNativePlatform());
        if (!window.Capacitor.isNativePlatform()) {
            _pushLog('status', 'not_native_platform');
            return;
        }

        var platform = window.Capacitor.getPlatform();
        _pushLog('platform', platform);

        var PushNotifications = window.Capacitor.Plugins.PushNotifications;
        _pushLog('plugin_exists', !!PushNotifications);
        if (!PushNotifications) {
            _pushLog('status', 'no_push_plugin');
            return;
        }

        console.log('[GeriApp] Push: init on platform', platform);
        _pushLog('status', 'requesting_permissions');

        PushNotifications.requestPermissions().then(function (perm) {
            console.log('[GeriApp] Push permission result:', perm.receive);
            _pushLog('permission', perm.receive);
            if (perm.receive === 'granted') {
                _pushLog('status', 'registering');
                PushNotifications.register().then(function() {
                    console.log('[GeriApp] Push register() resolved');
                    _pushLog('register_call', 'resolved');
                }).catch(function(err) {
                    console.error('[GeriApp] Push register() error:', err);
                    _pushLog('status', 'register_call_error');
                    _pushLog('register_call_error', String(err.message || err));
                });
                // Timeout: if no token after 30s, log it
                setTimeout(function() {
                    if (window._pushDiag.status === 'registering') {
                        _pushLog('status', 'registration_timeout');
                        _pushLog('timeout_hint', 'APNs no respondio. Verificar: 1) AppDelegate tiene didRegisterForRemoteNotifications, 2) Provisioning Profile incluye Push, 3) Entitlements aps-environment correcto');
                    }
                }, 30000);
            } else {
                _pushLog('status', 'permission_denied_' + perm.receive);
            }
        }).catch(function(err) {
            console.error('[GeriApp] Push requestPermissions error:', err);
            _pushLog('status', 'permission_error');
            _pushLog('permission_error', String(err.message || err));
        });

        // Token recibido: guardar en localStorage y enviar al servidor.
        // iOS devuelve token APNs (hex) — el servidor lo convierte a FCM via batchImport.
        // Android devuelve el token FCM directamente.
        PushNotifications.addListener('registration', function (token) {
            console.log('[GeriApp] Push token recibido:', token.value);
            _pushLog('status', 'token_received');
            _pushLog('token_preview', token.value ? (token.value.substring(0, 20) + '...') : 'empty');
            _pushLog('token_type', platform === 'ios' ? 'apns' : 'fcm');
            localStorage.setItem('_ga_push_token', token.value);
            localStorage.setItem('_ga_push_platform', platform || 'android');
            window.geriappFlushPushToken();
        });

        PushNotifications.addListener('registrationError', function (error) {
            console.error('[GeriApp] Push registration error:', JSON.stringify(error));
            _pushLog('status', 'registration_error');
            _pushLog('reg_error', JSON.stringify(error));
        });

        PushNotifications.addListener('pushNotificationReceived', function (notification) {
            console.log('[GeriApp] Push recibido (foreground):', notification);
        });

        PushNotifications.addListener('pushNotificationActionPerformed', function (notification) {
            console.log('[GeriApp] Push tapped:', notification);
        });
    }

    // Capacitor bridge loads synchronously before app.js, so we can init right away.
    // Use a small delay to ensure any lazy plugin registration is complete.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(_initPush, 100); });
    } else {
        setTimeout(_initPush, 100);
    }

})();

/**
 * Envia el token FCM pendiente en localStorage al backend.
 * Se llama automaticamente al obtener el token, y tambien
 * desde cuidados.php al cargar (cuando la sesion ya esta activa).
 * El token solo se elimina de localStorage tras un 200 exitoso.
 */
window.geriappFlushPushToken = function () {
    var token    = localStorage.getItem('_ga_push_token');
    var platform = localStorage.getItem('_ga_push_platform') || 'android';
    if (!token) {
        _pushLog('flush', 'no_token_in_storage');
        return;
    }

    // Skip if no active session (login page, register, etc.) to avoid 401
    var page = location.pathname.split('/').pop() || 'index.php';
    if (['index.php', 'register.php', 'forgot-password.php', 'reset-password.php', ''].indexOf(page) !== -1) {
        console.log('[GeriApp] Push token guardado localmente, se enviara tras login');
        _pushLog('flush', 'skipped_auth_page_' + page);
        return;
    }

    _pushLog('flush', 'sending_to_server');
    var base = window.GERIAPP_BASE || '';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    fetch(base + '/api/push_token.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ token: token, platform: platform })
    }).then(function (r) {
        if (r.ok) {
            console.log('[GeriApp] Push token registrado en servidor OK');
            _pushLog('flush', 'success');
            _pushLog('server_saved', true);
            if (window._pushDiag) { delete window._pushDiag.flush_error; try { localStorage.setItem('_ga_push_diag', JSON.stringify(window._pushDiag)); } catch(e) {} }
            localStorage.removeItem('_ga_push_token');
            localStorage.removeItem('_ga_push_platform');
        } else {
            console.warn('[GeriApp] Push token no guardado aun, HTTP ' + r.status + ' (se reintentara al iniciar sesion)');
            _pushLog('flush', 'http_error_' + r.status);
        }
    }).catch(function (err) {
        console.warn('[GeriApp] Error enviando push token:', err);
        _pushLog('flush', 'network_error');
        _pushLog('flush_error', String(err.message || err));
    });
};

// -----------------------------------------------
// OFFLINE / ONLINE DETECTION
// Shows a sticky banner when the device loses connectivity.
// -----------------------------------------------
(function setupOfflineDetection() {
    var bannerId = '_gaOfflineBanner';

    function showBanner() {
        if (document.getElementById(bannerId)) return;
        var div = document.createElement('div');
        div.id = bannerId;
        div.className = 'offline-banner';
        div.innerHTML = '<div class="offline-banner-inner">' +
            '<svg class="offline-banner-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>' +
            '<path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>' +
            '<path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>' +
            '<div class="offline-banner-body">' +
            '<span class="offline-banner-title">Sin conexión a internet</span>' +
            '<span class="offline-banner-sub">Los cambios no se guardarán hasta restaurar la conexión.</span>' +
            '</div></div>';
        var target = document.body.firstChild;
        document.body.insertBefore(div, target);
        // Also fire event for SPA modules (cuidados)
        window.dispatchEvent(new Event('geriapp:offline'));
    }

    function hideBanner() {
        var el = document.getElementById(bannerId);
        if (el) el.remove();
        window.dispatchEvent(new Event('geriapp:online'));
    }

    window.addEventListener('offline', showBanner);
    window.addEventListener('online', hideBanner);
    // Check on load
    if (!navigator.onLine) showBanner();
})();
