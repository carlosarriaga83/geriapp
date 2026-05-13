// cd-db-config.js — DB config + profiles
// Extracted from cuidados.php (lines 13921)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// DB CONFIG + PROFILES
// ═══════════════════════════════════════════════
const DB_CFG_API = BASE + '/api/db_config.php';
let _dbCfgLoaded = false;
let _dbProfiles = [];

function _dbFormValues() {
    return { host:$('#cfgDbHost').value, port:$('#cfgDbPort').value,
        name:$('#cfgDbName').value, user:$('#cfgDbUser').value, pass:$('#cfgDbPass').value,
        charset:$('#cfgDbCharset').value, tenant_prefix:$('#cfgDbTenantPrefix').value };
}
function _dbFillForm(d) {
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    v('cfgDbHost', d.host); v('cfgDbPort', d.port); v('cfgDbName', d.name);
    v('cfgDbUser', d.user); v('cfgDbPass', d.pass);
    v('cfgDbCharset', d.charset); v('cfgDbTenantPrefix', d.tenant_prefix);
    const result = $('#cfgDbTestResult'); if (result) result.style.display = 'none';
}
function _dbRenderProfiles() {
    const sel = $('#cfgDbProfile'); if (!sel) return;
    sel.innerHTML = '<option value="_active"><?= t('config_db_active_conn') ?></option>' +
        _dbProfiles.map((p,i) => `<option value="${i}">${esc(p.label)}</option>`).join('');
    _dbToggleProfileBtns();
}
function _dbToggleProfileBtns() {
    const isProfile = $('#cfgDbProfile')?.value !== '_active';
    const renBtn = $('#cfgDbProfileRename'), delBtn = $('#cfgDbProfileDelete');
    if (renBtn) renBtn.style.display = isProfile ? '' : 'none';
    if (delBtn) delBtn.style.display = isProfile ? '' : 'none';
}
function _dbSaveProfiles() {
    try { localStorage.setItem('geriapp_db_profiles', JSON.stringify(_dbProfiles)); } catch(e) {}
}
function _dbLoadProfiles() {
    try { _dbProfiles = JSON.parse(localStorage.getItem('geriapp_db_profiles') || '[]'); } catch(e) { _dbProfiles = []; }
}

async function loadDbConfig() {
    _dbLoadProfiles();
    _dbRenderProfiles();
    if (_dbCfgLoaded) return;
    // Show ghost loading on DB fields
    $$('#cfgPanelDb .cd-input').forEach(el => { el.classList.add('cd-skeleton'); el.style.pointerEvents = 'none'; });
    try {
        const d = await api(DB_CFG_API);
        _dbFillForm(d);
        _dbCfgLoaded = true;
        // Update the active profile label with actual DB info
        const sel = $('#cfgDbProfile');
        if (sel && sel.options[0]) sel.options[0].textContent = `Conexión activa (${d.host || 'DB'})`;
    } catch(e) {}
    $$('#cfgPanelDb .cd-input.cd-skeleton').forEach(el => { el.classList.remove('cd-skeleton'); el.style.pointerEvents = ''; });
    // Also fetch DB status
    loadDbStatus();
}

/* ── DB Status Indicator ───────────────────────────────────── */
let _dbStatusData = null;
async function loadDbStatus() {
    const dot  = $('#cfgDbStatusDot');
    const txt  = $('#cfgDbStatusText');
    if (!dot || !txt) return;
    txt.textContent = t('status_verifying');
    dot.style.background = '#ccc';
    try {
        const d = await api(DB_CFG_API + '?action=status');
        _dbStatusData = d;
        if (d.connected) {
            dot.style.background = '#27ae60';
            const uptimeStr = _fmtUptime(d.uptime_seconds);
            txt.innerHTML = `<strong style="color:var(--cd-success,#27ae60)">Conectado</strong> — ${d.host} · MySQL ${d.version} · ${d.tables} tablas · ${d.db_size_mb} MB · ~${d.queries_hour} consultas/h`;
        } else {
            dot.style.background = '#e74c3c';
            txt.innerHTML = `<strong style="color:var(--cd-danger,#e74c3c)">Desconectado</strong> — ${d.error || 'Error desconocido'}`;
        }
    } catch(e) {
        dot.style.background = '#e74c3c';
        txt.innerHTML = `<strong style="color:var(--cd-danger,#e74c3c)">Error</strong> — No se pudo verificar`;
        _dbStatusData = null;
    }
}

function _fmtUptime(sec) {
    if (!sec) return '0s';
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const parts = [];
    if (d) parts.push(d + 'd');
    if (h) parts.push(h + 'h');
    if (m) parts.push(m + 'm');
    return parts.join(' ') || '< 1m';
}

$('#cfgDbStatus')?.addEventListener('click', () => {
    if (!_dbStatusData) return;
    const d = _dbStatusData;
    const connected = d.connected;
    const statusBadge = connected
        ? '<span style="display:inline-block;padding:3px 10px;border-radius:12px;background:#27ae60;color:#fff;font-size:0.75rem;font-weight:600">Conectado</span>'
        : '<span style="display:inline-block;padding:3px 10px;border-radius:12px;background:#e74c3c;color:#fff;font-size:0.75rem;font-weight:600">Desconectado</span>';

    if (!connected) {
        openSidebar(t('config_db_title'), `
            <div style="text-align:center;padding:32px 0">${statusBadge}
            <p style="margin-top:12px;color:var(--cd-text-muted)">${d.error || 'Error de conexión'}</p></div>
        `);
        return;
    }

    const uptimeStr = _fmtUptime(d.uptime_seconds);
    const rows = [
        ['Host', d.host],
        ['Versión', d.version],
        ['Base de datos', d.db_name],
        ['Tablas', d.tables],
        ['Tamaño', d.db_size_mb + ' MB'],
        ['Uptime', uptimeStr],
        ['Consultas totales', d.queries_total?.toLocaleString()],
        ['Consultas/hora', '~' + d.queries_hour?.toLocaleString()],
        ['Conexiones activas', d.threads],
        ['Max conexiones', d.max_connections],
        ['Max conexiones/usuario', d.max_user_connections || 'Sin límite'],
    ];
    const body = `
        <div style="text-align:center;margin-bottom:16px">${statusBadge}</div>
        <table class="cd-sidebar-table" style="width:100%">
        ${rows.map(([k, v]) => `<tr><td style="font-weight:600;padding:6px 8px;white-space:nowrap">${k}</td><td style="padding:6px 8px">${v}</td></tr>`).join('')}
        </table>
    `;
    openSidebar(t('config_db_title'), body,
        '<button class="cd-btn-submit cd-btn-secondary" onclick="loadDbStatus();closeSidebar();">Actualizar</button>');
});

$('#cfgDbProfile')?.addEventListener('change', () => {
    const val = $('#cfgDbProfile').value;
    _dbToggleProfileBtns();
    if (val === '_active') { _dbCfgLoaded = false; loadDbConfig(); return; }
    const p = _dbProfiles[parseInt(val)];
    if (p) _dbFillForm(p);
});

$('#cfgDbProfileSaveAs')?.addEventListener('click', () => {
    const name = prompt('Nombre del perfil:');
    if (!name || !name.trim()) return;
    const vals = _dbFormValues();
    vals.label = name.trim();
    _dbProfiles.push(vals);
    _dbSaveProfiles();
    _dbRenderProfiles();
    $('#cfgDbProfile').value = String(_dbProfiles.length - 1);
    _dbToggleProfileBtns();
    showToast('Perfil "' + vals.label + '" guardado', 'success');
});

$('#cfgDbProfileRename')?.addEventListener('click', () => {
    const idx = parseInt($('#cfgDbProfile').value);
    if (isNaN(idx) || !_dbProfiles[idx]) return;
    const name = prompt('Nuevo nombre:', _dbProfiles[idx].label);
    if (!name || !name.trim()) return;
    _dbProfiles[idx].label = name.trim();
    _dbSaveProfiles();
    _dbRenderProfiles();
    $('#cfgDbProfile').value = String(idx);
    _dbToggleProfileBtns();
    showToast(t('toast_profile_renamed'), 'success');
});

$('#cfgDbProfileDelete')?.addEventListener('click', async () => {
    const idx = parseInt($('#cfgDbProfile').value);
    if (isNaN(idx) || !_dbProfiles[idx]) return;
    if (!await cdConfirm(t('confirm_delete_profile_body', {':name': _dbProfiles[idx].label}), { title: t('confirm_delete_profile_body'), type: 'danger', okText: t('btn_delete') })) return;
    _dbProfiles.splice(idx, 1);
    _dbSaveProfiles();
    _dbRenderProfiles();
    $('#cfgDbProfile').value = '_active';
    _dbToggleProfileBtns();
    _dbCfgLoaded = false; loadDbConfig();
    showToast(t('toast_profile_deleted'), 'success');
});

$('#cfgDbTest')?.addEventListener('click', async () => {
    const _btn = $('#cfgDbTest');
    btnLoading(_btn, t('status_testing'));
    const result = $('#cfgDbTestResult');
    if (result) result.style.display = 'none';
    try {
        const res = await api(DB_CFG_API + '?action=test', { method:'PUT',
            headers:{'Content-Type':'application/json'}, body:JSON.stringify(_dbFormValues()) });
        if (result) {
            result.textContent = '✓ ' + (res?.message || 'Conexión exitosa');
            result.style.display = '';
            result.style.background = 'var(--cd-success-bg, #d4edda)';
            result.style.color = 'var(--cd-success, #155724)';
        }
        showToast(res?.message || 'Conexión exitosa','success');
    } catch(e) {
        if (result) {
            result.textContent = '✗ ' + (e.message || 'Error de conexión');
            result.style.display = '';
            result.style.background = 'var(--cd-danger-bg, #f8d7da)';
            result.style.color = 'var(--cd-danger, #721c24)';
        }
    }
    btnReset(_btn);
});

$('#cfgDbSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgDbSave');
    btnLoading(_btn, t('status_saving'));
    try {
        await api(DB_CFG_API, { method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify(_dbFormValues()) });
        showToast(t('toast_db_applied'),'success');
        _dbCfgLoaded = false;
        // If a profile is selected, update its values too
        const idx = parseInt($('#cfgDbProfile')?.value);
        if (!isNaN(idx) && _dbProfiles[idx]) {
            const v = _dbFormValues(); v.label = _dbProfiles[idx].label;
            _dbProfiles[idx] = v;
            _dbSaveProfiles();
        }
    } catch(e) {}
    btnReset(_btn);
});

// Password eye toggle
document.addEventListener('click', e => {
    const btn = e.target.closest('.cd-pass-toggle');
    if (!btn) return;
    const wrap = btn.closest('.cd-input-password-wrap');
    const input = wrap?.querySelector('input');
    if (!input) return;
    // CSS-masked fields (cd-masked class) vs legacy type=password
    const isMasked = input.classList.contains('cd-masked');
    let showPlain;
    if (isMasked) {
        input.classList.toggle('cd-unmasked');
        showPlain = input.classList.contains('cd-unmasked');
    } else {
        showPlain = input.type === 'password';
        input.type = showPlain ? 'text' : 'password';
    }
    btn.innerHTML = showPlain ? '<svg width=\"18\" height=\"18\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94\"/><path d=\"M1 1l22 22\"/><path d=\"M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19\"/><path d=\"M14.12 14.12a3 3 0 1 1-4.24-4.24\"/></svg>'
        : '<svg width=\"18\" height=\"18\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/></svg>';
});

