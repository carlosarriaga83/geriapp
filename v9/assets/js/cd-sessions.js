// cd-sessions.js — Sessions management
// Extracted from cuidados.php (lines 15823)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// SESSIONS MANAGEMENT (v2)
// ═══════════════════════════════════════════════
const SESS_API = BASE + '/api/sesiones.php';
const SESS_TTL_H = 24; // sesiones expiran 24h después del último acceso

function parseUA(ua) {
    if (!ua) return { browser:'Desconocido', os:'Desconocido', icon:'🖥' };
    let browser = 'Otro', os = 'Otro', icon = '🖥';
    if (/Edg\//i.test(ua)) browser = 'Edge';
    else if (/Chrome/i.test(ua) && !/Chromium/i.test(ua)) browser = 'Chrome';
    else if (/Firefox/i.test(ua)) browser = 'Firefox';
    else if (/Safari/i.test(ua) && !/Chrome/i.test(ua)) browser = 'Safari';
    else if (/MSIE|Trident/i.test(ua)) browser = 'IE';
    if (/Windows/i.test(ua)) { os = 'Windows'; icon = '💻'; }
    else if (/Macintosh|Mac OS/i.test(ua)) { os = 'macOS'; icon = '💻'; }
    else if (/Android/i.test(ua)) { os = 'Android'; icon = '📱'; }
    else if (/iPhone|iPad|iPod/i.test(ua)) { os = 'iOS'; icon = '📱'; }
    else if (/Linux/i.test(ua)) { os = 'Linux'; icon = '🐧'; }
    return { browser, os, icon };
}

function sessCountdown(ultimoAcceso) {
    const last = new Date(ultimoAcceso.replace(' ', 'T') + (ultimoAcceso.includes('Z') ? '' : 'Z'));
    if (isNaN(last.getTime())) return { text:'—', cls:'' };
    const expiresAt = last.getTime() + SESS_TTL_H * 3600000;
    const remaining = expiresAt - Date.now();
    if (remaining <= 0) return { text:'Expirada', cls:'danger' };
    const h = Math.floor(remaining / 3600000);
    const m = Math.floor((remaining % 3600000) / 60000);
    let cls = '';
    if (h < 2) cls = 'danger';
    else if (h < 6) cls = 'warn';
    const text = h > 0 ? `${h}h ${m}m` : `${m}m`;
    return { text, cls };
}

let _sessCache = [];
let _sessFilter = 'all';

function renderSessionsList() {
    const list = $('#cfgSessionsList');
    const summary = $('#cfgSessSummary');
    if (!list) return;

    let filtered = _sessCache;
    if (_sessFilter === 'current') filtered = _sessCache.filter(s => s.es_actual);
    else if (_sessFilter === 'others') filtered = _sessCache.filter(s => !s.es_actual);

    // Summary
    if (summary) {
        const total = _sessCache.length;
        summary.innerHTML = `<strong>${total}</strong> sesión${total !== 1 ? 'es' : ''} activa${total !== 1 ? 's' : ''}
            <button type="button" class="cd-btn-ghost-sm" id="cfgSessionsRefresh" style="margin-left:auto">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Actualizar
            </button>`;
        $('#cfgSessionsRefresh')?.addEventListener('click', loadSessions);
    }

    if (!filtered.length) {
        list.innerHTML = `<p style="color:var(--cd-text-muted);text-align:center;padding:16px 0;font-size:0.8125rem">Sin sesiones en este filtro</p>`;
        return;
    }

    list.innerHTML = filtered.map(s => {
        const { browser, os, icon } = parseUA(s.user_agent);
        const isCurrent = s.es_actual;
        const cd = sessCountdown(s.ultimo_acceso);
        const lastFmt = fmtDateTime(s.ultimo_acceso);
        const lastAct = lastFmt.date ? `${lastFmt.date} ${lastFmt.time}` : '—';

        return `<div class="cd-cfg-user-card cd-session-card ${isCurrent ? 'cd-session-current' : ''}" data-sess-id="${s.id}" data-user-id="${s.usuario_id}">
            <div class="cd-session-info">
                <div class="cd-session-row1">
                    <span>${icon}</span>
                    <strong title="${esc(s.usuario_nombre || '')}">${esc(s.usuario_nombre || '—')}</strong>
                    <span class="cd-session-badge-device">${esc(browser)} · ${esc(os)}</span>
                    ${isCurrent ? `<span class="cd-session-badge-current">Actual</span>` : ''}
                </div>
                <div class="cd-session-row2">
                    <span title="IP">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        ${esc(s.ip || '—')}
                    </span>
                    <span title="Última actividad">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        ${lastAct}
                    </span>
                    <span class="cd-session-countdown ${cd.cls}" title="Expira en">⏱ ${cd.text}</span>
                </div>
            </div>
            <div class="cd-session-actions">
                ${!isCurrent ? `<button type="button" class="cd-btn-danger-sm cd-sess-end" data-sess-id="${s.id}" title="Cerrar sesión">Cerrar</button>` : ''}
            </div>
        </div>`;
    }).join('');

    // End session buttons
    $$('.cd-sess-end', list).forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!await cdConfirm('¿Cerrar esta sesión?', { type:'warn' })) return;
            btnLoading(btn, '¦');
            try {
                await api(`${SESS_API}?id=${btn.dataset.sessId}`, { method:'DELETE' });
                showToast('Sesión cerrada', 'success');
                loadSessions();
            } catch(e) { showToast('Error al cerrar sesión', 'error'); btnReset(btn); }
        });
    });
}

async function loadSessions() {
    const list = $('#cfgSessionsList');
    if (!list) return;
    list.innerHTML = skeleton(3);
    try {
        _sessCache = await api(SESS_API) || [];
        renderSessionsList();
    } catch(e) {
        list.innerHTML = `<p style="color:var(--cd-danger);text-align:center;padding:12px;font-size:0.8125rem">Error al cargar sesiones</p>`;
    }
}

// Filter chips
$$('#cfgSessFilters .cd-chip-filter').forEach(btn => {
    btn.addEventListener('click', () => {
        $$('#cfgSessFilters .cd-chip-filter').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _sessFilter = btn.dataset.sf;
        renderSessionsList();
    });
});

// Countdown auto-refresh every 60s
let _sessCountdownTimer = null;
function startSessCountdown() {
    clearInterval(_sessCountdownTimer);
    _sessCountdownTimer = setInterval(() => {
        if (!_sessCache.length) return;
        $$('.cd-session-countdown').forEach(el => {
            const card = el.closest('.cd-session-card');
            if (!card) return;
            const sessId = card.dataset.sessId;
            const s = _sessCache.find(x => String(x.id) === sessId);
            if (!s) return;
            const cd = sessCountdown(s.ultimo_acceso);
            el.textContent = '⏱ ' + cd.text;
            el.className = 'cd-session-countdown ' + cd.cls;
        });
    }, 60000);
}

// ── Push Notification Diagnostics ──
function renderPushDiag() {
    const el = $('#cfgPushDiag');
    if (!el) return;
    const diag = window._pushDiag || {};
    const token = localStorage.getItem('_ga_push_token');
    const serverSaved = diag.server_saved;
    const statusColors = {
        'token_received': 'var(--cd-success, #15803d)',
        'registration_error': 'var(--cd-danger)',
        'register_call_error': 'var(--cd-danger)',
        'registration_timeout': 'var(--cd-warning, #b45309)',
        'permission_error': 'var(--cd-danger)',
        'permission_denied_denied': 'var(--cd-danger)',
        'no_capacitor_bridge': 'var(--cd-warning, #b45309)',
        'not_native_platform': 'var(--cd-text-muted)',
        'no_push_plugin': 'var(--cd-danger)',
    };
    const statusLabels = {
        'token_received': '✅ Token recibido',
        'registration_error': '❌ Error al registrar',
        'register_call_error': '❌ Error en register()',
        'registration_timeout': '⏰ Timeout — APNs no respondió',
        'permission_error': '❌ Error al pedir permiso',
        'permission_denied_denied': '❌ Permiso denegado',
        'permission_denied_prompt': '⏳ Pendiente de permiso',
        'no_capacitor_bridge': '⚠️ Bridge Capacitor no detectado',
        'not_native_platform': 'ℹ️ No es plataforma nativa (web)',
        'no_push_plugin': '❌ Plugin PushNotifications no encontrado',
        'requesting_permissions': '⏳ Pidiendo permisos...',
        'registering': '⏳ Registrando...',
    };
    const status = diag.status || 'no_data';
    const rows = [
        ['Estado', `<span style="color:${statusColors[status] || 'var(--cd-text)'}">${statusLabels[status] || status}</span>`],
        ['Plataforma', diag.platform || '—'],
        ['Bridge Capacitor', diag.bridge_exists ? '✅ Sí' : '❌ No'],
        ['Es nativo', diag.is_native ? '✅ Sí' : (diag.is_native === false ? '❌ No' : '—')],
        ['Plugin existe', diag.plugin_exists ? '✅ Sí' : (diag.plugin_exists === false ? '❌ No' : '—')],
        ['Permiso', diag.permission || '—'],
        ['Token (local)', token ? ('✅ ' + token.substring(0,20) + '...') : (serverSaved ? '✅ Enviado al servidor' : '❌ No hay token')],
        ['Enviado al servidor', serverSaved ? '✅ Sí' : '❌ No'],
        ['Último diagnóstico', diag._ts ? new Date(diag._ts).toLocaleString('es-MX') : '—'],
    ];
    if (diag.reg_error) rows.push(['Error registro', `<span style="color:var(--cd-danger)">${esc(diag.reg_error)}</span>`]);
    if (diag.register_call_error) rows.push(['Error en register()', `<span style="color:var(--cd-danger)">${esc(diag.register_call_error)}</span>`]);
    if (diag.permission_error) rows.push(['Error permiso', `<span style="color:var(--cd-danger)">${esc(diag.permission_error)}</span>`]);
    if (diag.timeout_hint) rows.push(['Diagnóstico timeout', `<span style="color:var(--cd-warning,#b45309);font-size:0.75rem">${esc(diag.timeout_hint)}</span>`]);
    if (diag.flush) rows.push(['Último flush', esc(diag.flush)]);
    if (diag.flush_error) rows.push(['Error flush', `<span style="color:var(--cd-danger)">${esc(diag.flush_error)}</span>`]);
    el.innerHTML = '<table class="cd-sb-vitals-table"><tbody>' + rows.map(([k,v]) => `<tr><th style="white-space:nowrap">${k}</th><td>${v}</td></tr>`).join('') + '</tbody></table>';
}
$('#cfgPushRefresh')?.addEventListener('click', () => {
    // Re-read from localStorage in case it updated
    window._pushDiag = JSON.parse(localStorage.getItem('_ga_push_diag') || '{}');
    renderPushDiag();
});

// ── Send test push notification ──
$('#cfgPushTest')?.addEventListener('click', async () => {
    const btn = $('#cfgPushTest');
    const res = $('#cfgPushTestResult');
    if (!btn || !res) return;

    res.style.display = 'block';
    res.style.borderColor = 'var(--cd-border)';
    res.style.color = 'var(--cd-text-muted)';
    res.innerHTML = '⏳ Enviando notificación de prueba¦';
    btnLoading(btn, 'Enviando¦');

    try {
        const r = await api(BASE + '/api/push_test.php', { method: 'POST' });
        const total = r.total || 0;
        const ok = r.success || 0;
        const errors = r.errors || [];

        if (total === 0) {
            res.style.borderColor = 'var(--cd-warning, #b45309)';
            res.style.color = 'var(--cd-warning, #b45309)';
            res.innerHTML = '⚠️ No tienes tokens registrados. Abre la app desde el dispositivo para registrar uno.';
        } else if (ok === total) {
            res.style.borderColor = 'var(--cd-success, #15803d)';
            res.style.color = 'var(--cd-success, #15803d)';
            res.innerHTML = `✅ Push enviado exitosamente a <strong>${ok}</strong> dispositivo${ok > 1 ? 's' : ''}. Revisa la notificación en tu teléfono.`;
        } else {
            res.style.borderColor = 'var(--cd-danger)';
            res.style.color = 'var(--cd-danger)';
            let msg = `❌ ${ok}/${total} exitosos.`;
            if (errors.length) msg += '<br>' + errors.map(e => `• ${esc(e)}`).join('<br>');
            const cleaned = r.cleaned || 0;
            if (cleaned) msg += `<br><em style="color:var(--cd-text-muted)">🧹 ${cleaned} token${cleaned > 1 ? 's' : ''} inválido${cleaned > 1 ? 's' : ''} eliminado${cleaned > 1 ? 's' : ''} automáticamente.</em>`;
            res.innerHTML = msg;
        }
    } catch(e) {
        res.style.borderColor = 'var(--cd-danger)';
        res.style.color = 'var(--cd-danger)';
        res.innerHTML = `❌ Error: ${esc(e.message)}`;
    } finally {
        btnReset(btn);
    }
});

// ── Clear push diagnostic cache ──
$('#cfgPushClear')?.addEventListener('click', () => {
    localStorage.removeItem('_ga_push_diag');
    localStorage.removeItem('_ga_push_token');
    localStorage.removeItem('_ga_push_platform');
    window._pushDiag = {};
    renderPushDiag();
    const res = $('#cfgPushTestResult');
    if (res) res.style.display = 'none';
    showToast('Caché de push limpiado', 'success');
});

// ═══════════════════════════════════════════════
// AUTO-REFRESH disabled — Hostinger shared hosting has 500 max_connections_per_hour.
// Dashboard only refreshes when user clicks "Inicio" in bottom nav.

// Manual refresh button
$('#cdTlRefreshBtn')?.addEventListener('click', () => {
    loadDashboard(false);
});
$('#cdDashRefreshBtn')?.addEventListener('click', () => {
    loadDashboard(false);
});

// ── Timeline sort button ──
$('#cdTlSortBtn')?.addEventListener('click', function() {
    _tlSortAsc = !_tlSortAsc;
    this.classList.toggle('asc', _tlSortAsc);
    this.title = _tlSortAsc ? 'Orden descendente' : 'Orden ascendente';
    renderTimeline();
});

// ── Timeline category filter ──
$('#cdTlCatFilter')?.addEventListener('change', function() {
    _tlCatFilter = this.value;
    renderTimeline();
});

// ── Timeline search ──
let _tlSearchTimer = null;
$('#cdTlSearch')?.addEventListener('input', function() {
    clearTimeout(_tlSearchTimer);
    const val = this.value.trim();
    _tlSearchTimer = setTimeout(() => { _tlSearchText = val; renderTimeline(); }, 250);
});
$('#cdTlSearchClear')?.addEventListener('click', () => {
    const inp = $('#cdTlSearch');
    if (inp) { inp.value = ''; _tlSearchText = ''; renderTimeline(); inp.focus(); }
});

// ── Records timeline controls ──
$('#cdRecSortBtn')?.addEventListener('click', function() {
    _recSortAsc = !_recSortAsc;
    this.classList.toggle('asc', _recSortAsc);
    this.title = _recSortAsc ? 'Orden descendente' : 'Orden ascendente';
    renderRecordTimeline();
});
$('#cdRecCatFilter')?.addEventListener('change', function() {
    _recCatFilter = this.value;
    renderRecordTimeline();
});
let _recSearchTimer = null;
$('#cdRecSearch')?.addEventListener('input', function() {
    clearTimeout(_recSearchTimer);
    const val = this.value.trim();
    _recSearchTimer = setTimeout(() => { _recSearchText = val; renderRecordTimeline(); }, 250);
});
$('#cdRecSearchClear')?.addEventListener('click', () => {
    const inp = $('#cdRecSearch');
    if (inp) { inp.value = ''; _recSearchText = ''; renderRecordTimeline(); inp.focus(); }
});
$('#cdRecRefreshBtn')?.addEventListener('click', () => loadRecords({ force: true }));

