// cd-theme.js — Theme, logout, push notifications, biometric, notification panel, role check
// Extracted from cuidados.php (lines 12674)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// THEME / LOGOUT
// ═══════════════════════════════════════════════
function syncNativeStatusBar(dark) {
    const meta = document.getElementById('cdThemeColorMeta');
    if (meta) meta.setAttribute('content', dark ? '#111111' : '#f5f5f7');

    // 'DARK'  = íconos BLANCOS → tema oscuro (fondo #111)
    // 'LIGHT' = íconos NEGROS  → tema claro  (fondo #f5f5f7)
    const style = dark ? 'DARK' : 'LIGHT';

    function _apply() {
        let ok = false;
        try {
            // ── Vía 1: JavascriptInterface directo (bypasea plugin, más fiable en Android 16+)
            if (window.AndroidStatusBar && typeof window.AndroidStatusBar.setLightIcons === 'function') {
                window.AndroidStatusBar.setLightIcons(!dark);
                ok = true;
            }
        } catch(e) {}
        try {
            // ── Vía 2: Plugin Capacitor StatusBar (funciona en iOS y versiones antiguas)
            const cap = window.Capacitor;
            if (cap && (typeof cap.isNativePlatform !== 'function' || cap.isNativePlatform())) {
                const SB = cap.Plugins?.StatusBar;
                if (SB && typeof SB.setStyle === 'function') {
                    SB.setStyle({ style });
                    ok = true;
                }
            }
        } catch(e) {}
        return ok;
    }

    _apply();
    // Backoff para cubrir init del bridge
    [100, 400, 1000, 2500].forEach(ms => setTimeout(_apply, ms));
}

// Re-aplicar cuando el bridge esté listo y al recuperar foco
(function _setupStatusBarListeners() {
    // Capacitor 8 no garantiza que el bridge esté listo cuando este script corre.
    // Hacemos polling hasta que isNativePlatform() confirme que el bridge existe,
    // o hasta 6s de espera máxima.
    let _bridgePoller = null;
    let _pollCount = 0;
    function _pollBridge() {
        _pollCount++;
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        const done = syncNativeStatusBar(dark);
        if (done || _pollCount >= 24) {
            clearInterval(_bridgePoller);
        }
    }
    // Primer intento inmediato; si el bridge no está listo, pollear cada 250ms
    if (!syncNativeStatusBar(document.documentElement.getAttribute('data-theme') === 'dark')) {
        _bridgePoller = setInterval(_pollBridge, 250);
    }

    // Re-aplicar al volver al frente (switch entre apps, bloqueo de pantalla)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const dark = document.documentElement.getAttribute('data-theme') === 'dark';
            syncNativeStatusBar(dark);
        }
    });

    // Observar cambios en data-theme (cambio de tema manual)
    new MutationObserver(() => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        syncNativeStatusBar(dark);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();

function applyTheme(dark) {
    const themeUserId = (typeof CURRENT_USER_ID !== 'undefined' && CURRENT_USER_ID) ? CURRENT_USER_ID : 'anon';
    const themeKey = 'geriappTheme:' + themeUserId;
    if(dark){document.documentElement.setAttribute('data-theme','dark');localStorage.setItem(themeKey,'dark');localStorage.setItem('geriappTheme','dark');}
    else{document.documentElement.removeAttribute('data-theme');localStorage.setItem(themeKey,'light');localStorage.setItem('geriappTheme','light');}
    const t=$('#cdDarkToggle'); if(t)t.checked=dark;
    syncNativeStatusBar(dark);
}
$('#cdThemeToggle').addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme')!=='dark'));
$('#cdDarkToggle')?.addEventListener('change', e => applyTheme(e.target.checked));
const _themeUserId = (typeof CURRENT_USER_ID !== 'undefined' && CURRENT_USER_ID) ? CURRENT_USER_ID : 'anon';
const sv=localStorage.getItem('geriappTheme:' + _themeUserId);
applyTheme(sv === 'dark');

// Font size preference
function applyFontSize(size) {
    document.documentElement.setAttribute('data-font-size', size);
    localStorage.setItem('geriappFontSize', size);
    const sel=$('#cdFontSizeSelect'); if(sel) sel.value=size;
}
$('#cdFontSizeSelect')?.addEventListener('change', e => applyFontSize(e.target.value));

// Spacing preference
function applySpacing(spacing) {
    document.documentElement.setAttribute('data-spacing', spacing);
    localStorage.setItem('geriappSpacing', spacing);
    const sel=$('#cdSpacingSelect'); if(sel) sel.value=spacing;
}
$('#cdSpacingSelect')?.addEventListener('change', e => applySpacing(e.target.value));

// Load preferences from DB (bootstrapped), fallback to localStorage
(function(){
    const prefs = <?= json_encode(json_decode(Usuario::getById($userId)['preferencias'] ?? '{}', true) ?: new stdClass) ?>;
    applyFontSize(prefs.font_size || localStorage.getItem('geriappFontSize') || 'large');
    applySpacing(prefs.spacing || localStorage.getItem('geriappSpacing') || 'normal');
})();

// ── Push Notifications (Capacitor native) ────────────────────
// app.js no se carga en cuidados.php (SPA), así que definimos
// el flush y el setup de push directamente aquí.
(function(){
    // Diagnostic log helper
    window._pushDiag = JSON.parse(localStorage.getItem('_ga_push_diag') || '{}');
    function _pushLog(key, val) {
        window._pushDiag[key] = val;
        window._pushDiag._ts = new Date().toISOString();
        try { localStorage.setItem('_ga_push_diag', JSON.stringify(window._pushDiag)); } catch(e) {}
    }

    // Flush: envía token pendiente en localStorage al backend
    window.geriappFlushPushToken = function() {
        var token    = localStorage.getItem('_ga_push_token');
        var platform = localStorage.getItem('_ga_push_platform') || 'android';
        if (!token) { _pushLog('flush', 'no_token_in_storage'); return; }
        _pushLog('flush', 'sending_to_server');
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch(BASE + '/api/push_token.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ token: token, platform: platform })
        }).then(function(r) {
            if (r.ok) {
                console.log('[GeriApp] Push token registrado en servidor OK');
                _pushLog('flush', 'success');
                _pushLog('server_saved', true);
                delete window._pushDiag.flush_error;
                try { localStorage.setItem('_ga_push_diag', JSON.stringify(window._pushDiag)); } catch(e) {}
                localStorage.removeItem('_ga_push_token');
                localStorage.removeItem('_ga_push_platform');
            } else {
                console.warn('[GeriApp] Push token HTTP ' + r.status);
                _pushLog('flush', 'http_error_' + r.status);
            }
        }).catch(function(err) {
            console.warn('[GeriApp] Error enviando push token:', err);
            _pushLog('flush', 'network_error');
            _pushLog('flush_error', String(err.message || err));
        });
    };

    // Setup: registrar push y obtener token si aún no existe
    function _initPush() {
        if (typeof window.Capacitor === 'undefined' || !window.Capacitor.isNativePlatform()) return;
        var platform = window.Capacitor.getPlatform();
        var PN = window.Capacitor.Plugins.PushNotifications;
        if (!PN) return;
        _pushLog('platform', platform);
        _pushLog('status', 'requesting_permissions');
        PN.requestPermissions().then(function(perm) {
            _pushLog('permission', perm.receive);
            if (perm.receive === 'granted') {
                _pushLog('status', 'registering');
                PN.register();
            }
        }).catch(function(){});
        PN.addListener('registration', function(token) {
            _pushLog('status', 'token_received');
            _pushLog('token_preview', token.value ? token.value.substring(0,20)+'...' : 'empty');
            localStorage.setItem('_ga_push_token', token.value);
            localStorage.setItem('_ga_push_platform', platform || 'android');
            window.geriappFlushPushToken();
        });
        PN.addListener('registrationError', function(err) {
            _pushLog('status', 'registration_error');
            _pushLog('reg_error', JSON.stringify(err));
        });
    }

    // Flush token pendiente de inmediato (ya tenemos sesión)
    window.geriappFlushPushToken();

    // Si no hay token aún, iniciar el setup de push para obtener uno
    if (!localStorage.getItem('_ga_push_token') && typeof window.Capacitor !== 'undefined') {
        setTimeout(_initPush, 200);
    }
})();

// Save preferences button
$('#cdApplyPrefsBtn')?.addEventListener('click', async function() {
    const btn = this;
    const fs = $('#cdFontSizeSelect')?.value || 'normal';
    const sp = $('#cdSpacingSelect')?.value || 'normal';
    applyFontSize(fs);
    applySpacing(sp);
    btn.textContent = t('status_saving');
    btn.disabled = true;
    try {
        const res = await fetch(PERSONAL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_profile', preferencias: { font_size: fs, spacing: sp } })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Error');
        showToast(t('toast_prefs_saved'), 'success');
    } catch(e) { showToast(e.message, 'error'); }
    btn.textContent = t('btn_apply_save_prefs');
    btn.disabled = false;
});

// Language selector — set cookie & reload
$('#cdLangSelect')?.addEventListener('change', function() {
    document.cookie = 'geriapp_lang=' + this.value + ';path=/;max-age=' + (365*86400) + ';SameSite=Lax';
    location.reload();
});

// ── Biometric toggle in profile settings ────────────────────────
(async function _initBiometricToggle() {
    const row    = $('#cdBiometricRow');
    const toggle = $('#cdBiometricToggle');
    if (!row || !toggle) { return; }

    const cap = window.Capacitor || null;
    const isNative = !!(
        (cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform()) ||
        (cap && cap.platform && cap.platform !== 'web') ||
        document.documentElement.dataset.native === '1' ||
        localStorage.getItem('geriappNativeApp') === '1'
    );
    if (!isNative) { return; }

    // Try multiple ways the plugin might be registered
    const plugins = (cap && cap.Plugins) || {};
    const bio = plugins.NativeBiometric
             || plugins['NativeBiometric']
             || window.NativeBiometric;

    if (!bio) {
        // Plugin not available — still show the row but disabled with explanation
        row.style.display = '';
        toggle.disabled = true;
        row.querySelector('.desc').textContent = 'Plugin de biometría no disponible';
        return;
    }

    let bioAvailable = false;
    try {
        if (typeof bio.isAvailable === 'function') {
            const result = await bio.isAvailable();
            bioAvailable = result.isAvailable;
        }
    } catch (e) {
        console.warn('[Bio] isAvailable error:', e);
    }

    // Show the toggle regardless — if not available, show a message
    row.style.display = '';
    if (!bioAvailable) {
        toggle.disabled = true;
        row.querySelector('.desc').textContent = 'Biometría no configurada en este dispositivo';
        return;
    }

    const CRED_SRV = 'com.geriapp.login';
    const BIO_KEY  = 'geriapp_bio_enabled';

    toggle.checked = localStorage.getItem(BIO_KEY) === '1';

    toggle.addEventListener('change', async function() {
        if (this.checked) {
            // ── Activate: ask for password, verify, store credentials ──
            this.checked = false; // revert until confirmed
            const password = await _bioPromptPassword();
            if (!password) return;

            try {
                // Verify password against server
                const res = await fetch(PERSONAL_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'verificar_password', password: password })
                });
                const json = await res.json();
                if (!json.success) {
                    showToast(json.message || 'Contraseña incorrecta', 'error');
                    return;
                }

                // Verify biometric identity
                await bio.verifyIdentity({
                    reason:            'Confirma tu identidad para activar biometría',
                    title:             'Activar Biometría',
                    subtitle:          'Verifica tu identidad',
                    description:       'Usa tu huella o rostro',
                    negativeButtonText: 'Cancelar',
                    maxAttempts:       3,
                });

                // Get email from profile
                const email = ($('#cdProfEmail')?.textContent || '').trim();
                if (!email || email === '—') {
                    showToast('No se pudo obtener el email', 'error');
                    return;
                }

                // Save credentials in Keychain/Keystore
                await bio.setCredentials({
                    server:   CRED_SRV,
                    username: email,
                    password: password,
                });
                localStorage.setItem(BIO_KEY, '1');
                toggle.checked = true;
                showToast('¡Biometría activada! La próxima vez podrás ingresar con tu huella o rostro.', 'success');

            } catch (e) {
                console.warn('[GeriApp Bio] Activation error:', e);
                if (/cancel/i.test(e.message || '')) {
                    // User cancelled biometric prompt
                    return;
                }
                showToast('Error al activar biometría: ' + (e.message || 'Intenta de nuevo'), 'error');
            }
        } else {
            // ── Disable biometric ──
            try {
                await bio.deleteCredentials({ server: CRED_SRV });
            } catch (e) {
                console.warn('[GeriApp Bio] deleteCredentials:', e);
            }
            localStorage.removeItem(BIO_KEY);
            showToast('Biometría desactivada', 'success');
        }
    });

    // Password prompt dialog
    function _bioPromptPassword() {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:20px;';
            overlay.innerHTML =
                '<div style="background:var(--cd-surface,#fff);border-radius:16px;padding:28px 24px;max-width:340px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.3);font-family:var(--cd-font,\'Open Sans\',\'Inter\',system-ui,sans-serif);color:var(--cd-text,#1e293b);">'
              + '<div style="text-align:center;margin-bottom:16px;">'
              +   '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--cd-primary,#178391)" stroke-width="1.5">'
              +     '<path d="M12 11c0-1.1-.9-2-2-2s-2 .9-2 2 2 4 2 4"/>'
              +     '<path d="M6.13 7.92A6.98 6.98 0 0 1 12 5c3.87 0 7 3.13 7 7 0 1.68-.47 3.25-1.29 4.58"/>'
              +     '<path d="M3.51 11.72A10 10 0 0 1 12 2c5.52 0 10 4.48 10 10 0 2.4-.85 4.6-2.26 6.33"/>'
              +   '</svg>'
              + '</div>'
              + '<h3 style="margin:0 0 6px;font-size:1.05rem;color:var(--cd-text,#1e293b);text-align:center;">Activar Biometría</h3>'
              + '<p style="margin:0 0 16px;font-size:.8125rem;color:var(--cd-text-muted,#64748b);text-align:center;">Ingresa tu contraseña actual para guardar tus credenciales de forma segura.</p>'
              + '<div style="position:relative;margin-bottom:16px;">'
              +   '<input type="password" id="_bioPwInput" placeholder="Contraseña actual" autocomplete="current-password"'
              +     ' style="width:100%;padding:10px 38px 10px 12px;border:1px solid var(--cd-border,#e2e8f0);border-radius:10px;font-size:.9rem;box-sizing:border-box;background:var(--cd-bg,#f7f8fa);color:var(--cd-text,#1e293b);font-family:inherit;">'
              +   '<button type="button" id="_bioPwEye" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:2px;color:var(--cd-text-muted,#888);display:flex;align-items:center;" tabindex="-1" aria-label="Mostrar contraseña">'
              +     '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
              +   '</button>'
              + '</div>'
              + '<div style="display:flex;gap:10px;">'
              +   '<button id="_bioPwCancel" style="flex:1;padding:10px;border:1px solid var(--cd-border,#e2e8f0);background:var(--cd-surface,#fff);border-radius:10px;font-size:.9rem;cursor:pointer;color:var(--cd-text-muted,#64748b);font-family:inherit;">Cancelar</button>'
              +   '<button id="_bioPwOk" style="flex:1;padding:10px;border:none;background:linear-gradient(135deg,#178391,#9db9d0);color:#fff;border-radius:10px;font-size:.9rem;cursor:pointer;font-weight:600;font-family:inherit;">Confirmar</button>'
              + '</div></div>';
            document.body.appendChild(overlay);

            const inp = overlay.querySelector('#_bioPwInput');
            inp.focus();

            // Toggle eye
            const eyeBtn = overlay.querySelector('#_bioPwEye');
            eyeBtn.addEventListener('click', () => {
                const showing = inp.type === 'text';
                inp.type = showing ? 'password' : 'text';
                eyeBtn.innerHTML = showing
                    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            });

            function done(val) { overlay.remove(); resolve(val || null); }
            overlay.querySelector('#_bioPwOk').addEventListener('click', () => done(inp.value));
            overlay.querySelector('#_bioPwCancel').addEventListener('click', () => done(null));
            inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') done(inp.value); });
            overlay.addEventListener('click', (e) => { if (e.target === overlay) done(null); });
        });
    }
})();

const _saImpersonating = <?= $saImpersonating ? 'true' : 'false' ?>;
function doLogout(){
    if (_saImpersonating) {
        endSaImpersonation();
        return;
    }
    // Note: biometric credentials are NOT deleted on logout.
    // The user keeps their stored creds so they can use biometric
    // login on the next visit. To disable, use the toggle in settings.
    window.location.href=BASE+'/auth/logout.php';
}
async function endSaImpersonation() {
    try {
        const r = await fetch(BASE + '/superadmin/api.php?action=end_impersonate', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}'
        }).then(r => r.json());
        if (r.ok) window.location.href = r.url;
        else window.location.href = BASE + '/superadmin/';
    } catch(e) { window.location.href = BASE + '/superadmin/'; }
}
$('#cdSaReturnBtn')?.addEventListener('click', endSaImpersonation);
$('#cdLogoutBtn').addEventListener('click', doLogout);
$('#cdLogoutBtn2')?.addEventListener('click', doLogout);
$('#cdAvatarBtn')?.addEventListener('click', async () => {
    if (_careFormDirty && !await confirmCareLeave()) return;
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    resetCareForm(); _careFormDirty = false; _medFormDirty = false; _nmFormDirty = false;
    showView('viewProfile');
});

// ── Notification panel ──
(function initNotifPanel() {
    const btn = $('#cdNotifBtn');
    const panel = $('#cdNotifPanel');
    const overlay = $('#cdNotifOverlay');
    const closeBtn = $('#cdNotifClose');
    const listEl = $('#cdNotifList');
    const dot = $('#cdNotifDot');
    if (!btn || !panel) return;
    let _notifLoaded = false;
    let _allLogs = [];
    let _activeTab = 'all';

    function togglePanel(open) {
        const show = typeof open === 'boolean' ? open : !panel.classList.contains('open');
        panel.classList.toggle('open', show);
        overlay.classList.toggle('open', show);
        if (show) loadNotifs();
    }
    btn.addEventListener('click', () => togglePanel());
    closeBtn?.addEventListener('click', () => togglePanel(false));
    overlay?.addEventListener('click', () => togglePanel(false));

    // Tab switching
    $$('.cd-notif-ptab', panel).forEach(tab => {
        tab.addEventListener('click', () => {
            $$('.cd-notif-ptab', panel).forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            _activeTab = tab.dataset.ntab;
            renderList();
        });
    });

    function filterLogs() {
        if (_activeTab === 'all') return _allLogs;
        return _allLogs.filter(l => (l.canal || '').toLowerCase() === _activeTab);
    }

    function renderList() {
        const logs = filterLogs();
        if (!logs.length) {
            listEl.innerHTML = `<div class="cd-notif-push-empty">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--cd-text-muted)" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <p style="font-size:.85rem;font-weight:500">${t('notif_empty')}</p>
                <p style="font-size:.75rem;margin-top:-4px">${t('notif_empty_desc')}</p>
            </div>`;
            return;
        }
        listEl.innerHTML = logs.map((l, i) => {
            const icon = _getIcon(l.canal);
            const statusCls = l.estado === 'enviado' ? 'sent' : (l.estado === 'error' ? 'error' : 'pending');
            const statusLabel = l.estado === 'enviado' ? t('notif_sent') : (l.estado === 'error' ? t('notif_error') : t('notif_pending'));
            const fecha = l.fecha ? _timeAgo(new Date(l.fecha)) : '';
            const fechaFull = l.fecha ? (function(){ const f = fmtDateTime(l.fecha); return f.date ? (f.date + ' ' + f.time) : new Date(l.fecha).toLocaleString('es-MX', { timeZone: APP_TZ }); })() : '';
            const tipo = l.tipo ? esc(l.tipo).replace(/_/g, ' ') : '';
            return `<div class="cd-notif-card cd-notif-card--${statusCls}" data-nidx="${i}">
                <div class="cd-notif-card-main">
                    <div class="cd-notif-card-icon">${icon}</div>
                    <div class="cd-notif-card-content">
                        <div class="cd-notif-card-msg">${esc(l.mensaje || l.tipo || '—')}</div>
                        <div class="cd-notif-card-meta">
                            <span class="cd-notif-badge cd-notif-badge--${statusCls}">${statusLabel}</span>
                            <span>${esc(l.canal || '').toUpperCase()}</span>
                            <span title="${fechaFull}">${fecha}</span>
                        </div>
                    </div>
                    <button class="cd-notif-card-expand" title="Ver detalle">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="cd-notif-card-detail">
                    <div class="cd-notif-detail-row"><span class="cd-notif-detail-label">${t('notif_dest')}</span><span>${esc(l.destinatario || '—')}</span></div>
                    ${tipo ? `<div class="cd-notif-detail-row"><span class="cd-notif-detail-label">${t('notif_type')}</span><span>${tipo}</span></div>` : ''}
                    <div class="cd-notif-detail-row"><span class="cd-notif-detail-label">${t('notif_date')}</span><span>${fechaFull}</span></div>
                    ${(l.error || l.error_detalle) ? `<div class="cd-notif-detail-row cd-notif-detail-error"><span class="cd-notif-detail-label">Error</span><span>${esc(l.error || l.error_detalle)}</span></div>` : ''}
                </div>
            </div>`;
        }).join('');

        // Expand/collapse
        $$('.cd-notif-card-expand', listEl).forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const card = btn.closest('.cd-notif-card');
                card.classList.toggle('expanded');
            });
        });
    }

    function _getIcon(canal) {
        const c = (canal || '').toLowerCase();
        if (c === 'email') return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
        if (c === 'whatsapp') return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
        if (c === 'push') return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    }

    function _timeAgo(date) {
        const diff = (Date.now() - date.getTime()) / 1000;
        if (diff < 60) return 'ahora';
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + ' h';
        if (diff < 604800) return Math.floor(diff / 86400) + ' d';
        return date.toLocaleDateString('es-MX', { timeZone: APP_TZ });
    }

    async function loadNotifs() {
        listEl.innerHTML = skeleton(4);
        try {
            const data = await api(`${API_URL}?notif_log=1`);
            _allLogs = data.logs || [];
            _notifLoaded = true;
            renderList();
            if (dot) dot.style.display = 'none';
        } catch(e) {
            listEl.innerHTML = `<p style="padding:16px;color:var(--cd-text-muted)">Error al cargar notificaciones</p>`;
        }
    }
    // Check for new notifs on dashboard load
    window._checkNotifDot = async function() {
        try {
            const data = await api(`${API_URL}?notif_log=1`);
            const logs = data.logs || [];
            if (dot && logs.length > 0) dot.style.display = '';
            _notifLoaded = false; // force reload on next open
        } catch(e) {}
    };
})();

// ── Periodic role check (detect changes without refresh) ──
(function(){
    if (_saImpersonating) return;
    const ROLE_CHECK_INTERVAL = 60000; // 60s
    const currentRole = '<?= addslashes($userRole) ?>';
    setInterval(async () => {
        if (document.hidden) return;
        try {
            const r = await fetch(BASE + '/api/session_check.php', {credentials:'same-origin'}).then(r=>r.json());
            if (r.revoked) { location.href = BASE + '/select_institucion.php?reason=access_revoked'; return; }
            if (r.changed) location.reload();
        } catch(e) {}
    }, ROLE_CHECK_INTERVAL);
})();

// Multi-institution switch
$('#cdInstSelect')?.addEventListener('change', async function() {
    const instId = parseInt(this.value);
    if (!instId) return;
    try {
        const res = await fetch(BASE + '/api/switch_institucion.php', {
            method: 'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ inst_id: instId })
        });
        const json = await res.json();
        if (json.success) window.location.reload();
        else showToast(json.message || t('error_change_institution'), 'error');
    } catch(e) { showToast(t('error_connection'), 'error'); }
});

$('#cdSaInstSelect')?.addEventListener('change', async function() {
    const instId = parseInt(this.value, 10);
    if (!instId) return;
    this.disabled = true;
    try {
        const res = await fetch(BASE + '/superadmin/api.php?action=impersonate', {
            method: 'POST',
            headers: { 'Content-Type':'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ inst_id: instId })
        });
        const json = await res.json();
        if (json.success || json.ok) window.location.href = json.url || json.data?.url || (BASE + '/cuidados.php');
        else {
            this.disabled = false;
            showToast(json.message || 'No se pudo cambiar de institución', 'error');
        }
    } catch(e) {
        this.disabled = false;
        showToast(t('error_connection'), 'error');
    }
});

