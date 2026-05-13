// cd-backups.js — Backups §8
// Extracted from cuidados.php (lines 14987)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// BACKUPS (Â§8 — Respaldos)
// ═══════════════════════════════════════════════
const BACKUP_API = BASE + '/api/backup.php';
let _backupsLoaded = false;

function cfgRolePublic(rol) {
    return rol === 'enfermero' ? 'cuidador' : (rol || '');
}

function cfgRoleLabel(rol) {
    const labels = { admin: t('role_admin') || 'Admin', medico: t('role_medico') || 'Médico', enfermero: t('role_enfermero') || 'Cuidador', cuidador: t('role_enfermero') || 'Cuidador', familiar: t('role_familiar') || 'Familiar' };
    return labels[rol] || labels[cfgRolePublic(rol)] || rol || '—';
}

async function loadBackups() {
    const list = $('#cfgBackupsList');
    if (list) list.innerHTML = skeleton(2);
    try {
        const d = await api(BACKUP_API);
        _backupsLoaded = true;
        // Populate schedule
        const frec = $('#cfgBackupFrec');
        const hora = $('#cfgBackupHora');
        if (frec && d.frecuencia) frec.value = d.frecuencia;
        if (hora && d.hora) hora.value = d.hora;
        // Render list
        renderBackupsList(d.backups || []);
    } catch(e) {
        if (list) list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">Error al cargar respaldos.</p>';
    }
}

function renderBackupsList(backups) {
    const list = $('#cfgBackupsList');
    if (!list) return;
    if (!backups.length) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">Sin respaldos almacenados en el servidor.</p>';
        return;
    }
    list.innerHTML = backups.map(b => {
        const badges = [];
        if (b.encrypted) badges.push('<span style="background:var(--cd-warning-bg,#fff3cd);color:var(--cd-warning,#856404);padding:1px 6px;border-radius:8px;font-size:.65rem;font-weight:600">Cifrado</span>');
        if (b.compressed) badges.push('<span style="background:var(--cd-info-bg,#d1ecf1);color:var(--cd-info,#0c5460);padding:1px 6px;border-radius:8px;font-size:.65rem;font-weight:600">Comprimido</span>');
        return `<div class="cd-cfg-user-card" style="cursor:default">
            <div class="cd-cfg-user-info" style="min-width:0">
                <strong style="font-size:0.8125rem;word-break:break-all">${esc(b.name)}</strong>
                <span style="font-size:0.75rem">${esc(b.date)} · ${esc(b.size_fmt)} ${badges.join(' ')}</span>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0">
                <button class="cd-btn-submit cd-btn-secondary cd-bkp-dl" data-file="${esc(b.name)}" style="padding:4px 10px;font-size:0.75rem" title="Descargar">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </button>
                <button class="cd-btn-submit cd-btn-secondary cd-bkp-del" data-file="${esc(b.name)}" style="padding:4px 10px;font-size:0.75rem;color:var(--cd-danger,#e74c3c)" title="Eliminar">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
        </div>`;
    }).join('');
}

// Backup list actions (download / delete)
$('#cfgBackupsList')?.addEventListener('click', async e => {
    const dlBtn = e.target.closest('.cd-bkp-dl');
    if (dlBtn) {
        window.location.href = BACKUP_API + '?action=download&file=' + encodeURIComponent(dlBtn.dataset.file);
        return;
    }
    const delBtn = e.target.closest('.cd-bkp-del');
    if (delBtn) {
        if (!await cdConfirm('¿Eliminar este respaldo del servidor?', { type: 'danger', okText: 'Eliminar' })) return;
        try {
            await api(BACKUP_API + '?action=delete', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ file: delBtn.dataset.file })
            });
            showToast('Respaldo eliminado', 'success');
            loadBackups();
        } catch(e) {}
    }
});

// Generate backup now
$('#cfgBackupNow')?.addEventListener('click', async () => {
    const btn = $('#cfgBackupNow');
    btnLoading(btn, 'Generando¦');
    try {
        const d = await api(BACKUP_API + '?action=now', { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
        showToast('Respaldo generado: ' + (d.file || ''), 'success');
        loadBackups();
    } catch(e) {}
    btnReset(btn);
});

// Direct download (existing PATCH endpoint)
$('#cfgBackupDownloadDirect')?.addEventListener('click', async () => {
    const btn = $('#cfgBackupDownloadDirect');
    btnLoading(btn, 'Generando¦');
    try {
        const resp = await fetch(CFG_API + '?action=backup', {
            method: 'PATCH',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
        });
        if (!resp.ok) throw new Error('Error');
        const blob = await resp.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const disp = resp.headers.get('Content-Disposition') || '';
        const match = disp.match(/filename="?([^"]+)"?/);
        a.download = match ? match[1] : 'geriapp_backup.sql';
        a.href = url;
        a.click();
        URL.revokeObjectURL(url);
    } catch(e) { showToast('Error al descargar', 'error'); }
    btnReset(btn);
});

// Save backup schedule
$('#cfgBackupSaveSchedule')?.addEventListener('click', async () => {
    const btn = $('#cfgBackupSaveSchedule');
    const frec = $('#cfgBackupFrec')?.value || 'desactivado';
    const hora = $('#cfgBackupHora')?.value || '03:00';
    btnLoading(btn, 'Guardando¦');
    try {
        await api(CFG_API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ seccion: 'sistema', backup_frecuencia: frec, backup_hora: hora })
        });
        showToast('Programación guardada', 'success');
    } catch(e) {}
    btnReset(btn);
});

// Refresh backups
$('#cfgBackupRefresh')?.addEventListener('click', () => loadBackups());

// Personal / Users
async function loadPersonal() {
    const uList = $('#cfgUsersList');
    const iList = $('#cfgInvitesList');
    const rBtns = [$('#cfgUsersRefresh'), $('#cfgInvitesRefresh')];
    if (uList) uList.innerHTML = skeleton(3);
    if (iList) iList.innerHTML = skeleton(2);
    rBtns.forEach(b => b?.classList.add('cd-btn-spinning'));
    try {
        const data = await api(PERSONAL_API);
        const users = data.usuarios || data || [];
        renderUsersList(Array.isArray(users) ? users : []);
    } catch(e) { if (uList) uList.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem">Error al cargar usuarios.</p>'; }
    try {
        const data = await api(INVITE_API);
        const invites = data.invitaciones || data || [];
        renderInvitesList(Array.isArray(invites) ? invites : []);
    } catch(e) {}
    rBtns.forEach(b => b?.classList.remove('cd-btn-spinning'));
}

function renderUsersList(users) {
    const list = $('#cfgUsersList');
    if (!list) return;
    const q = ($('#cfgUserSearch')?.value || '').toLowerCase();
    const statusFilter = $('#cfgUsersFilter')?.querySelector('.cd-chip-filter.active')?.dataset?.filter || 'todos';
    // Update filter counts
    const uFilterEl = $('#cfgUsersFilter');
    if (uFilterEl) {
        const activos = users.filter(u => (u.estado || 'activo') === 'activo').length;
        const inactivos = users.filter(u => (u.estado || 'activo') === 'inactivo').length;
        uFilterEl.querySelector('[data-filter="todos"]').textContent = `Todos (${users.length})`;
        uFilterEl.querySelector('[data-filter="activo"]').textContent = `Activos (${activos})`;
        uFilterEl.querySelector('[data-filter="inactivo"]').textContent = `Inactivos (${inactivos})`;
        const pBtn = uFilterEl.querySelector('[data-filter="pendientes"]');
        if (pBtn && pBtn.textContent.indexOf('Pendientes') !== 0) pBtn.textContent = 'Pendientes';
    }
    // Pendientes chip routes to invites section (hides users list)
    const invitesSec = $('#cfgInvitesSection');
    if (statusFilter === 'pendientes') {
        list.style.display = 'none';
        if (invitesSec) invitesSec.style.display = '';
        return;
    }
    list.style.display = '';
    if (invitesSec) invitesSec.style.display = 'none';
    let filtered = users;
    if (statusFilter !== 'todos') filtered = filtered.filter(u => (u.estado || 'activo') === statusFilter);
    if (q) filtered = filtered.filter(u => (u.nombre||'').toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q));
    if (!filtered.length) { list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">' + t('users_empty') + '</p>'; return; }
    const resMap = {};
    RESIDENTES.forEach(r => resMap[r.id] = r.nombre);
    list.innerHTML = filtered.map((u, i) => {
        const rIds = u.residentes_ids || [];
        const isAdmin = (u.rol === 'admin');
        // Admin always sees all residents; others use their explicit list
        const effectiveIds = isAdmin ? RESIDENTES.map(r => r.id) : rIds;
        const effectiveSet = new Set(effectiveIds.map(Number));
        const MAX_CHIPS = 3;
        const previewChips = effectiveIds.slice(0, MAX_CHIPS).map(rid => `<span class="cd-cfg-user-res-chip">${esc(resMap[rid] || '#'+rid)}</span>`).join('');
        const moreCount = effectiveIds.length - MAX_CHIPS;
        const chipsHtml = effectiveIds.length === 0
            ? '<span style="font-size:0.75rem;color:var(--cd-text-muted)">' + t('users_no_residents') + '</span>'
            : previewChips + (moreCount > 0 ? `<span class="cd-cfg-user-res-chip" style="background:color-mix(in srgb, var(--cd-primary) 15%, transparent);color:var(--cd-primary)">+${moreCount} más</span>` : '');
        const inlineChecks = RESIDENTES.map(r => `<label class="cd-cfg-user-res-toggle" style="display:flex;align-items:center;gap:6px;padding:3px 0;font-size:0.8rem;cursor:pointer" data-rname="${esc(r.nombre).toLowerCase()}">
            <input type="checkbox" class="cdExpandResCheck" value="${r.id}" ${effectiveSet.has(r.id)?'checked':''} style="accent-color:var(--cd-primary)">
            ${esc(r.nombre)}
        </label>`).join('');
        return `<div class="cd-cfg-user-card" data-uidx="${i}">
        <div class="cd-cfg-user-card-header">
            <svg class="cd-chevron-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            <div class="cd-cfg-user-info">
                <strong>${esc(u.nombre || '—')}</strong>
                <span>${esc(u.email || '')}${u.ultimo_acceso ? ' · ' + t('sidebar_last_access') + ': ' + esc((function(){ const f = fmtDateTime(u.ultimo_acceso); return f.date ? (f.date + ' ' + f.time) : u.ultimo_acceso; })()) : ''}</span>
            </div>
            <button class="cd-cfg-user-edit-btn" data-action="edit">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <span class="cd-cfg-user-role">${esc(cfgRoleLabel(u.rol))}</span>
            <span class="cd-cfg-user-res-count" title="${t('sidebar_linked_residents')}">${effectiveIds.length}/${RESIDENTES.length}</span>
            <span class="cd-cfg-user-status ${u.estado === 'activo' ? 'active' : ''}">${esc(u.estado || '—')}</span>
        </div>
        <div class="cd-cfg-user-expand" style="display:none">
            <div class="cd-cfg-user-expand-chips" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px">${chipsHtml}</div>
            <details style="margin-top:4px">
                <summary style="font-size:0.75rem;color:var(--cd-primary);cursor:pointer;user-select:none">${t('sidebar_linked_residents')} (${effectiveIds.length}/${RESIDENTES.length})</summary>
                <input class="cd-input cdExpandResSearch" placeholder="${t('config_personal_search')}" style="margin:6px 0 4px;font-size:0.8rem;padding:4px 8px">
                <div style="display:flex;gap:6px;margin:4px 0">
                    <button type="button" class="cdExpandSelAll" style="font-size:0.7rem;padding:2px 8px;cursor:pointer;border:1px solid var(--cd-border);border-radius:4px;background:var(--cd-surface)">${t('btn_select_all')}</button>
                    <button type="button" class="cdExpandSelNone" style="font-size:0.7rem;padding:2px 8px;cursor:pointer;border:1px solid var(--cd-border);border-radius:4px;background:var(--cd-surface)">${t('btn_deselect_all')}</button>
                </div>
                <div style="max-height:160px;overflow-y:auto;display:flex;flex-direction:column;gap:1px;padding:2px 0">
                    ${inlineChecks}
                </div>
                <div style="display:flex;gap:6px;margin-top:6px">
                    <button type="button" class="cd-btn-submit cdExpandSave" style="flex:1;font-size:0.75rem;padding:4px 0">${t('btn_save_changes')}</button>
                    <button type="button" class="cd-btn-submit cd-btn-secondary cdExpandCancel" style="flex:1;font-size:0.75rem;padding:4px 0">${t('btn_cancel')}</button>
                </div>
            </details>
        </div>
    </div>`;
    }).join('');
    list._users = users;
}

$('#cfgUsersList')?.addEventListener('click', e => {
    // Ignore clicks inside the expand area that aren't the edit button
    if (e.target.closest('.cdExpandResSearch')) return;
    if (e.target.closest('summary')) return;
    if (e.target.closest('.cd-cfg-user-res-toggle')) return;

    const card = e.target.closest('.cd-cfg-user-card');
    if (!card) return;

    // Handle inline resident checkbox toggle (no auto-sync, wait for save)
    const resCheck = e.target.closest('.cdExpandResCheck');
    if (resCheck) return;

    // Select all / Deselect all
    const selAll = e.target.closest('.cdExpandSelAll');
    const selNone = e.target.closest('.cdExpandSelNone');
    if (selAll || selNone) {
        const visible = [...card.querySelectorAll('.cd-cfg-user-res-toggle')].filter(l => l.style.display !== 'none');
        visible.forEach(l => { const cb = l.querySelector('.cdExpandResCheck'); if (cb) cb.checked = !!selAll; });
        return;
    }

    // Save linked residents
    const saveBtn = e.target.closest('.cdExpandSave');
    if (saveBtn) {
        const list = $('#cfgUsersList');
        const idx = parseInt(card.dataset.uidx, 10);
        const q = ($('#cfgUserSearch')?.value || '').toLowerCase();
        const users = list._users || [];
        const filtered = q ? users.filter(u => (u.nombre||'').toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q)) : users;
        const user = filtered[idx];
        if (!user) return;
        const resMap = {}; RESIDENTES.forEach(r => resMap[r.id] = r.nombre);
        const checked = [...card.querySelectorAll('.cdExpandResCheck:checked')].map(cb => Number(cb.value));
        // Update chips area
        const chipsDiv = card.querySelector('.cd-cfg-user-expand-chips');
        if (chipsDiv) {
            chipsDiv.innerHTML = checked.length
                ? checked.map(rid => `<span class="cd-cfg-user-res-chip">${esc(resMap[rid] || '#'+rid)}</span>`).join('')
                : '<span style="font-size:0.75rem;color:var(--cd-text-muted)">' + t('users_no_residents') + '</span>';
        }
        // Update summary count
        const summary = card.querySelector('details > summary');
        if (summary) summary.textContent = t('sidebar_linked_residents') + ` (${checked.length}/${RESIDENTES.length})`;
        // Update header counter
        const hdrCount = card.querySelector('.cd-cfg-user-res-count');
        if (hdrCount) hdrCount.textContent = `${checked.length}/${RESIDENTES.length}`;
        // Sync to server
        user.residentes_ids = checked;
        const _uid = user.usuario_id || user.user_id || user.id;
        api(PERSONAL_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'sync_residentes', usuario_id: _uid, residente_ids: checked}) }).then(() => showToast(t('toast_user_updated'),'success')).catch(()=> showToast('Error','error'));
        return;
    }

    // Cancel: restore original checkboxes
    const cancelBtn = e.target.closest('.cdExpandCancel');
    if (cancelBtn) {
        const list = $('#cfgUsersList');
        const idx = parseInt(card.dataset.uidx, 10);
        const q = ($('#cfgUserSearch')?.value || '').toLowerCase();
        const users = list._users || [];
        const filtered = q ? users.filter(u => (u.nombre||'').toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q)) : users;
        const user = filtered[idx];
        if (!user) return;
        const origIds = new Set((user.residentes_ids || []).map(Number));
        card.querySelectorAll('.cdExpandResCheck').forEach(cb => { cb.checked = origIds.has(Number(cb.value)); });
        return;
    }

    // Edit button opens sidebar
    const editBtn = e.target.closest('.cd-cfg-user-edit-btn');

    if (editBtn) {
        const list = $('#cfgUsersList');
        const idx = parseInt(card.dataset.uidx, 10);
        const q = ($('#cfgUserSearch')?.value || '').toLowerCase();
        const users = list._users || [];
        const filtered = q ? users.filter(u => (u.nombre||'').toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q)) : users;
        const user = filtered[idx];
        if (user) openUserDetailSidebar(user);
        return;
    }

    // Toggle expand — only from header clicks
    if (!e.target.closest('.cd-cfg-user-expand')) {
        const expand = card.querySelector('.cd-cfg-user-expand');
        if (expand) {
            const isOpen = expand.style.display !== 'none';
            expand.style.display = isOpen ? 'none' : '';
            card.classList.toggle('expanded', !isOpen);
        }
    }
});

// Delegated search filter inside expand cards
$('#cfgUsersList')?.addEventListener('input', e => {
    if (!e.target.classList.contains('cdExpandResSearch')) return;
    const q = e.target.value.toLowerCase();
    const card = e.target.closest('.cd-cfg-user-card');
    card?.querySelectorAll('.cd-cfg-user-res-toggle').forEach(lbl => {
        lbl.style.display = (lbl.dataset.rname || '').includes(q) ? '' : 'none';
    });
});

function openUserDetailSidebar(u) {
    const isSelf = u.id === CURRENT_USER_ID;
    const roles = ['admin','enfermero','medico','familiar'];
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('sidebar_user_data')}</div>
        <div class="cd-form-group" style="margin-bottom:10px">
            <label class="cd-form-label">${t('users_name')}</label>
            <input class="cd-input" id="cdUserEditNombre" value="${esc(u.nombre||'')}">
        </div>
        <div class="cd-form-group" style="margin-bottom:10px">
            <label class="cd-form-label">${t('users_email')}</label>
            <input class="cd-input" id="cdUserEditEmail" value="${esc(u.email||'')}" type="email">
        </div>
        <div class="cd-form-group" style="margin-bottom:10px">
            <label class="cd-form-label">${t('sidebar_phone')}</label>
            <input class="cd-input" id="cdUserEditTel" value="${esc(u.telefono||'')}">
        </div>
        <table class="cd-sb-vitals-table" style="margin-top:4px"><tbody>
            <tr><th>${t('sidebar_last_access')}</th><td>${esc(u.ultimo_acceso||'—')}</td></tr>
            <tr><th>${t('sidebar_created')}</th><td>${esc(u.creado_at||u.usuario_creado||'—')}</td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('sidebar_manage')}</div>
        <div class="cd-form-group" style="margin-bottom:12px">
            <label class="cd-form-label">${t('sidebar_role')}</label>
            <select class="cd-input cd-select-native" id="cdUserRolSelect">
                ${roles.map(r => `<option value="${r}" ${u.rol===r?'selected':''}>${esc(cfgRoleLabel(r))}</option>`).join('')}
            </select>
        </div>
        <div class="cd-form-group" style="margin-bottom:12px">
            <label class="cd-form-label">${t('sidebar_status')}</label>
            <div style="display:flex;align-items:center;gap:10px">
                <span id="cdUserEstadoLabel" style="font-size:0.875rem;font-weight:500;color:${u.estado==='activo'?'var(--cd-success)':'var(--cd-danger)'}">${esc(u.estado||'—')}</span>
                ${!isSelf ? `<label class="cd-vital-switch" style="position:relative;top:0;right:0"><input type="checkbox" class="cd-vital-toggle" id="cdUserToggleEstado" ${u.estado==='activo'?'checked':''}><span class="cd-vital-slider"></span></label>` : ''}
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label">${t('sidebar_reset_pw')}</label>
            <form onsubmit="return false" style="margin:0"><div class="cd-input-password-wrap">
                <input class="cd-input" id="cdUserNewPw" type="password" placeholder="${t('sidebar_reset_pw_ph')}">
                <button type="button" class="cd-pass-toggle" tabindex="-1"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div></form>
        </div>
    </div>`;
    const actions = `<div style="display:flex;gap:8px;width:100%">
        ${!isSelf ? `<button class="cd-btn-submit cd-btn-danger" id="cdUserDelete" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            ${t('btn_delete')}
        </button>` : ''}
        <button class="cd-btn-submit" id="cdUserSave" style="flex:1"><?= t('btn_save_changes') ?></button>
    </div>
    <button class="cd-btn-close-sidebar" id="cdUserClose">${t('btn_close')}</button>`;
    openSidebar(u.nombre || 'Usuario', body, actions);
    const _userId = u.usuario_id || u.user_id || u.id;
    // Save all changes (name, email, tel, role, password)
    $('#cdUserSave')?.addEventListener('click', async () => {
        const btn = $('#cdUserSave');
        const newNombre = ($('#cdUserEditNombre')?.value || '').trim();
        const newEmail = ($('#cdUserEditEmail')?.value || '').trim();
        const newTel = ($('#cdUserEditTel')?.value || '').trim();
        const newRol = $('#cdUserRolSelect')?.value;
        const newPw = ($('#cdUserNewPw')?.value || '').trim();
        if (!newNombre) { showToast(t('error_name_required'),'error'); return; }
        if (!newEmail) { showToast(t('error_enter_email'),'error'); return; }
        if (newPw && newPw.length < 8) { showToast(t('error_min_8_chars'),'error'); return; }
        btnLoading(btn, t('status_saving'));
        let dataOk = true, rolOk = true, pwOk = true;
        // Only send PUT if data actually changed
        const dataChanged = newNombre !== (u.nombre || '') || newEmail !== (u.email || '') || newTel !== (u.telefono || u.tel || '');
        if (dataChanged) {
            try {
                await api(PERSONAL_API + '?id=' + _userId, { method:'PUT', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ nombre:newNombre, email:newEmail, telefono:newTel }) });
                u.nombre = newNombre; u.email = newEmail; u.telefono = newTel;
            } catch(e) { dataOk = false; }
        }
        // Update role if changed
        if (newRol !== u.rol) {
            try {
                await api(PERSONAL_API, { method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ action:'cambiar_rol', usuario_id:_userId, rol:newRol }) });
                u.rol = newRol;
            } catch(e) { rolOk = false; }
        }
        // Change password if entered
        if (newPw) {
            try {
                await api(PERSONAL_API, { method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ action:'cambiar_password', usuario_id:_userId, password:newPw }) });
            } catch(e) { pwOk = false; }
        }
        if (dataOk && rolOk && pwOk) {
            showToast(t('toast_user_updated'),'success');
            closeSidebar();
            loadPersonal();
        }
        btnReset(btn);
    });
    // Delete user
    $('#cdUserDelete')?.addEventListener('click', async () => {
        if (!await cdConfirm(t('confirm_delete_user', {name: u.nombre || t('this_user')}), { title: t('confirm_delete_user_title'), type: 'danger', okText: t('btn_delete') })) return;
        const btn = $('#cdUserDelete');
        btnLoading(btn, '');
        try {
            await api(PERSONAL_API + '?id=' + _userId, { method:'DELETE' });
            showToast(t('toast_user_deleted'),'success');
            closeSidebar();
            loadPersonal();
        } catch(e) {}
        btnReset(btn);
    });
    // Toggle estado
    $('#cdUserToggleEstado')?.addEventListener('change', async () => {
        const toggle = $('#cdUserToggleEstado');
        try {
            const res = await api(PERSONAL_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'toggle_estado', usuario_id:_userId }) });
            const nuevoEstado = res?.estado || (u.estado==='activo'?'inactivo':'activo');
            showToast(`Usuario ${nuevoEstado}`,'success');
            u.estado = nuevoEstado;
            const lbl = $('#cdUserEstadoLabel');
            if (lbl) { lbl.textContent = nuevoEstado; lbl.style.color = nuevoEstado==='activo'?'var(--cd-success)':'var(--cd-danger)'; }
            loadPersonal();
        } catch(e) { if (toggle) toggle.checked = !toggle.checked; }
    });

    $('#cdUserClose')?.addEventListener('click', closeSidebar);
}

$('#cfgUserSearch')?.addEventListener('input', () => {
    const list = $('#cfgUsersList');
    if (list?._users) renderUsersList(list._users);
});

// Users status filter chips
$('#cfgUsersFilter')?.addEventListener('click', e => {
    const btn = e.target.closest('.cd-chip-filter');
    if (!btn) return;
    $('#cfgUsersFilter').querySelectorAll('.cd-chip-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const list = $('#cfgUsersList');
    if (list?._users) renderUsersList(list._users);
});

$('#cfgUsersRefresh')?.addEventListener('click', () => loadPersonal());
$('#cfgInvitesRefresh')?.addEventListener('click', () => loadPersonal());

// Invites status filter chips
$('#cfgInvitesFilter')?.addEventListener('click', e => {
    const btn = e.target.closest('.cd-chip-filter');
    if (!btn) return;
    $('#cfgInvitesFilter').querySelectorAll('.cd-chip-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const list = $('#cfgInvitesList');
    if (list?._invites) renderInvitesList(list._invites);
});

function renderInvitesList(invites) {
    const list = $('#cfgInvitesList');
    if (!list) return;
    list._invites = invites;
    const statusFilter = $('#cfgInvitesFilter')?.querySelector('.cd-chip-filter.active')?.dataset?.filter || 'pendiente';
    // Update filter counts
    const iFilterEl = $('#cfgInvitesFilter');
    const counts = {};
    invites.forEach(inv => { const st = inv.status || inv.estado || 'pendiente'; counts[st] = (counts[st] || 0) + 1; });
    if (iFilterEl) {
        iFilterEl.querySelector('[data-filter="pendiente"]').textContent = `Pendientes (${counts.pendiente || 0})`;
        iFilterEl.querySelector('[data-filter="todos"]').textContent = `Todas (${invites.length})`;
        iFilterEl.querySelector('[data-filter="aceptada"]').textContent = `Aceptadas (${counts.aceptada || 0})`;
        iFilterEl.querySelector('[data-filter="revocada"]').textContent = `Revocadas (${counts.revocada || 0})`;
    }
    // Sync Pendientes chip in users filter (uses pending invites count)
    const uPend = $('#cfgUsersFilter')?.querySelector('[data-filter="pendientes"]');
    if (uPend) uPend.textContent = `Pendientes (${counts.pendiente || 0})`;
    const filtered = statusFilter === 'todos' ? invites : invites.filter(inv => {
        const st = inv.status || inv.estado || 'pendiente';
        return st === statusFilter;
    });
    if (!filtered.length) { list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">' + t('invites_empty') + '</p>'; return; }
    list.innerHTML = filtered.map(inv => {
        const st = inv.status || inv.estado || 'pendiente';
        const isExpired = inv.expira && inv.expira < new Date().toISOString().slice(0,10);
        const badge = isExpired ? 'expirada' : st;
        const expFmt = inv.expira ? (inv.expira.length > 10 ? (function(){ const f = fmtDateTime(inv.expira); return f.date ? (f.date + ' ' + f.time) : inv.expira; })() : fmtDate(inv.expira)) : '—';
        const phoneRaw = (inv.telefono || '').toString().trim();
        const waSvg = '<svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" style="vertical-align:-1px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.296-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12.057 21.785h-.005a9.87 9.87 0 0 1-5.03-1.378l-.36-.214-3.741.982 1-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.002-5.45 4.436-9.884 9.888-9.884 2.64.001 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.892 6.994c-.003 5.45-4.437 9.884-9.887 9.884zm8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';
        let phoneChip = '';
        if (phoneRaw) {
            const phoneClean = phoneRaw.replace(/[^0-9+]/g, '');
            phoneChip = `<span class="cd-cfg-inv-phone" data-cd-phone="${esc(phoneClean)}" style="display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:9px;background:color-mix(in srgb, #25d366 14%, transparent);color:#0f7a3a;font-size:0.7rem;font-weight:600;margin-left:6px;vertical-align:middle">${waSvg}<span class="cd-cfg-inv-phone-num">${esc(phoneRaw)}</span><span class="cd-cfg-inv-phone-status" style="opacity:.7;font-weight:500">…</span></span>`;
        } else {
            phoneChip = `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:9px;background:color-mix(in srgb, var(--cd-text-muted) 14%, transparent);color:var(--cd-text-muted);font-size:0.7rem;font-weight:600;margin-left:6px;vertical-align:middle">Sin teléfono</span>`;
        }
        const resIds = Array.isArray(inv.residente_ids) ? inv.residente_ids.map(Number).filter(Boolean) : [];
        const resNames = resIds.map(id => RESIDENTES.find(r => Number(r.id) === id)?.nombre).filter(Boolean);
        const roleScope = inv.rol === 'familiar'
            ? 'Familiares'
            : (inv.rol === 'admin'
                ? 'Administradores'
                : (inv.rol === 'medico' ? 'Médicos' : (['enfermero','cuidador'].includes(inv.rol) ? 'Cuidadores' : 'Personal')));
        const resScope = inv.rol === 'admin'
            ? 'sin residente asignado'
            : (resNames.length ? resNames.slice(0, 3).join(', ') + (resNames.length > 3 ? ` +${resNames.length - 3}` : '') : (['medico','enfermero'].includes(inv.rol) ? 'Todos los residentes' : `${resIds.length} residente${resIds.length === 1 ? '' : 's'}`));
        return `<div class="cd-cfg-user-card" style="cursor:pointer">
        <div class="cd-cfg-user-info">
            <strong>${esc(inv.email || '—')}</strong>
            <span>${esc(roleScope)} · Rol: ${esc(cfgRoleLabel(inv.rol))} · ${esc(resScope)} · Expira: ${esc(expFmt)}${phoneChip}</span>
        </div>
        <span class="cd-cfg-user-status ${badge === 'aceptada' ? 'active' : ''}">${esc(badge)}</span>
    </div>`;
    }).join('');
    list._filteredInvites = filtered;
    // Async WhatsApp registration check for each phone chip
    list.querySelectorAll('.cd-cfg-inv-phone[data-cd-phone]').forEach(chip => {
        const phone = chip.dataset.cdPhone;
        const statusEl = chip.querySelector('.cd-cfg-inv-phone-status');
        if (!phone || !statusEl) return;
        api(API_URL + '?check_whatsapp=' + encodeURIComponent(phone)).then(res => {
            if (res && res.registered === true) {
                statusEl.textContent = '✓';
                statusEl.style.color = '#15803d';
                statusEl.title = 'WhatsApp activo';
            } else if (res && res.registered === false) {
                statusEl.textContent = '✗';
                statusEl.style.color = 'var(--cd-danger, #dc2626)';
                statusEl.title = 'No registrado en WhatsApp';
            } else {
                statusEl.textContent = '?';
            }
        }).catch(() => { statusEl.textContent = '?'; });
    });
}

$('#cfgInvitesList')?.addEventListener('click', e => {
    const card = e.target.closest('.cd-cfg-user-card');
    if (!card) return;
    const list = $('#cfgInvitesList');
    const idx = [...list.querySelectorAll('.cd-cfg-user-card')].indexOf(card);
    const inv = (list._filteredInvites || [])[idx];
    if (inv) openInviteDetailSidebar(inv);
});

function openInviteDetailSidebar(inv) {
    const st = inv.status || inv.estado || 'pendiente';
    const isExpired = st === 'pendiente' && inv.expira && inv.expira < new Date().toISOString().slice(0,10);
    const badge = isExpired ? 'expirada' : st;
    const badgeColor = badge === 'aceptada' ? 'var(--cd-success)' : badge === 'revocada' ? 'var(--cd-danger)' : badge === 'expirada' ? 'var(--cd-warning, #f59e0b)' : 'var(--cd-primary)';
    const canResend = (st === 'pendiente');
    const canRevoke = (st === 'pendiente');

    // Format timestamps nicely (respects institution cfgTimezone via APP_TZ)
    const fmtTs = (ts) => {
        if (!ts) return '—';
        // Treat naive server timestamps as UTC so APP_TZ conversion is correct
        const iso = ts.includes('T') ? ts : ts.replace(' ', 'T') + (ts.includes('Z') || ts.includes('+') ? '' : 'Z');
        const d = new Date(iso);
        if (isNaN(d)) return esc(ts);
        return d.toLocaleDateString('es-MX', {timeZone: APP_TZ, year:'numeric', month:'short', day:'numeric'})
            + ' ' + d.toLocaleTimeString('es-MX', {timeZone: APP_TZ, hour:'2-digit', minute:'2-digit'});
    };

    // Build invite link from token (full URL for sharing)
    const invLink = inv.token ? (APP_URL + '/register.php?inv=' + inv.token) : '';
    const hasPhone = !!(inv.telefono && inv.telefono.toString().trim());
    const waBtnExtra = hasPhone ? '' : ' data-cd-no-phone="1" title="Agrega un teléfono para enviar por WhatsApp"';
    const waBtnStyle = hasPhone
        ? 'background:#25d366'
        : 'background:#25d366;opacity:.55;filter:grayscale(.4);cursor:help';
    const linkSection = invLink ? `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Enlace de invitación</div>
        <div style="background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius);padding:10px 12px;font-size:0.75rem;word-break:break-all;color:var(--cd-text-muted);margin-bottom:10px;user-select:all" id="cdInvLinkText">${esc(invLink)}</div>
        <div style="display:flex;gap:8px">
            <button class="cd-btn-submit cd-btn-secondary" id="cdInvCopyLink" style="flex:1;padding:8px;font-size:0.8125rem">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copiar
            </button>
            <button class="cd-btn-submit" id="cdInvWhatsApp" style="flex:1;padding:8px;font-size:0.8125rem;color:#fff;${waBtnStyle}"${waBtnExtra}>
                <img src="assets/icons/whatsapp.png" alt="" width="14" height="14" style="vertical-align:-2px;margin-right:4px;filter:brightness(0) invert(1)">
                WhatsApp
            </button>
        </div>
        ${!hasPhone ? '<div style="margin-top:6px;font-size:0.7rem;color:var(--cd-text-muted)">Agrega un teléfono abajo para habilitar el envío por WhatsApp.</div>' : ''}
    </div>` : '';

    // Editable section (only for pendiente invites)
    const editSection = (st === 'pendiente') ? `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Editar datos de la invitación</div>
        <div class="cd-form-group" style="margin-bottom:10px">
            <label class="cd-form-label">Teléfono WhatsApp</label>
            <input class="cd-input" id="cdInvEditTel" value="${esc(inv.telefono||'')}" type="tel" placeholder="+52 1 555 123 4567">
        </div>
        <div style="display:flex;gap:8px;margin-bottom:10px">
            <div style="flex:1">
                <label class="cd-form-label">Nombre</label>
                <input class="cd-input" id="cdInvEditNombre" value="${esc(inv.nombre_sugerido||'')}">
            </div>
            <div style="flex:1">
                <label class="cd-form-label">Apellido</label>
                <input class="cd-input" id="cdInvEditApellido" value="${esc(inv.apellido_sugerido||'')}">
            </div>
        </div>
        <div class="cd-form-group" style="margin-bottom:10px">
            <label class="cd-form-label">Mensaje</label>
            <textarea class="cd-input" id="cdInvEditMensaje" rows="2" style="resize:vertical">${esc(inv.mensaje||'')}</textarea>
        </div>
        <button class="cd-btn-submit" id="cdInvEditSave" style="width:100%">Guardar cambios</button>
    </div>` : '';

    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('invite_details')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>Email</th><td>${esc(inv.email||'—')}</td></tr>
            <tr><th>${t('sidebar_role')}</th><td>${esc((inv.rol||'').charAt(0).toUpperCase()+(inv.rol||'').slice(1))}</td></tr>
            <tr><th>${t('log_status')}</th><td><span style="color:${badgeColor};font-weight:600">${esc(badge)}</span></td></tr>
            <tr><th>${t('invite_sent_date')}</th><td>${fmtTs(inv.enviada)}</td></tr>
            <tr><th>${t('invite_expiry')}</th><td>${fmtTs(inv.expira)}</td></tr>
            ${inv.telefono ? `<tr><th>WhatsApp</th><td>${esc(inv.telefono)}</td></tr>` : ''}
            ${inv.nombre_sugerido || inv.apellido_sugerido ? `<tr><th>Nombre sugerido</th><td>${esc((inv.nombre_sugerido||'')+' '+(inv.apellido_sugerido||''))}</td></tr>` : ''}
            ${inv.mensaje ? `<tr><th>${t('label_message')}</th><td>${esc(inv.mensaje)}</td></tr>` : ''}
        </tbody></table>
    </div>
    ${linkSection}
    ${editSection}
    ${canResend || canRevoke ? `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('invite_actions')}</div>
        ${canResend ? `<div class="cd-form-group" style="margin-bottom:12px">
            <label class="cd-form-label">${t('invite_renew_days')}</label>
            <select class="cd-input cd-select-native" id="cdInvResendDias">
                <option value="3">${t('invite_days_3')}</option>
                <option value="7" selected>${t('invite_days_7')}</option>
                <option value="14">${t('invite_days_14')}</option>
                <option value="30">${t('invite_days_30')}</option>
            </select>
        </div>
        <button class="cd-btn-submit" id="cdInvResendBtn" style="width:100%;margin-bottom:10px">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            ${t('invite_resend_label')}
        </button>` : ''}
        ${canRevoke ? `<button class="cd-btn-submit cd-btn-danger" id="cdInvRevokeBtn" style="width:100%">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            ${t('invite_revoke_label')}
        </button>` : ''}
    </div>` : ''}`;
    const actions = `<button class="cd-btn-close-sidebar" id="cdInvDetailClose">${t('btn_close')}</button>`;
    openSidebar(t('invite_send'), body, actions);
    const _invId = inv.id;

    // Reenviar
    $('#cdInvResendBtn')?.addEventListener('click', async () => {
        const dias = parseInt($('#cdInvResendDias')?.value || '7');
        const btn = $('#cdInvResendBtn');
        btnLoading(btn, t('status_resending'));
        try {
            await api(INVITE_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'reenviar', id:_invId, dias }) });
            showToast(t('toast_invite_resent'),'success');
            closeSidebar();
            loadPersonal();
        } catch(e) { showToast(t('error_resend'),'error'); btnReset(btn); }
    });

    // Revocar
    $('#cdInvRevokeBtn')?.addEventListener('click', async () => {
        if (!await cdConfirm(t('confirm_revoke_invite_body'), { title: t('confirm_revoke_invite_title'), type: 'danger', okText: t('confirm_revoke_btn') })) return;
        const btn = $('#cdInvRevokeBtn');
        btnLoading(btn, t('status_revoking'));
        try {
            await api(INVITE_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'revocar', id:_invId }) });
            showToast(t('toast_invite_revoked'),'success');
            closeSidebar();
            loadPersonal();
        } catch(e) { showToast(t('error_revoke'),'error'); btnReset(btn); }
    });

    $('#cdInvDetailClose')?.addEventListener('click', closeSidebar);

    // Copy link
    $('#cdInvCopyLink')?.addEventListener('click', () => {
        const link = $('#cdInvLinkText')?.textContent?.trim();
        if (!link) return;
        navigator.clipboard.writeText(link).then(() => {
            const btn = $('#cdInvCopyLink');
            if (btn) { btn.innerHTML = '✓ Copiado'; setTimeout(() => { btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copiar'; }, 2000); }
        }).catch(() => showToast('No se pudo copiar','error'));
    });

    // WhatsApp share via configured API
    $('#cdInvWhatsApp')?.addEventListener('click', async (ev) => {
        const btn = $('#cdInvWhatsApp');
        // No-phone: focus tel field instead of sending
        if (btn?.dataset?.cdNoPhone === '1') {
            const tel = $('#cdInvEditTel');
            if (tel) { tel.focus(); tel.select?.(); }
            showToast('Agrega un teléfono para enviar por WhatsApp', 'info');
            return;
        }
        const link = $('#cdInvLinkText')?.textContent?.trim();
        if (!link) return;
        let phone = cdNormalizePhone(inv.telefono || '');
        if (!phone) {
            const raw = await cdPrompt('Ingresa el número de WhatsApp del destinatario', {
                title: 'Enviar invitación por WhatsApp',
                placeholder: '+52 1 555 123 4567'
            });
            if (!raw) return;
            phone = cdNormalizePhone(raw);
        }
        if (!phone) return;
        const text = `Te invito a unirte a *${INST_NAME}* en GeriApp como *${(inv.rol||'').charAt(0).toUpperCase()+(inv.rol||'').slice(1)}*.\n\nRegístrate aquí:\n${link}`;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="cd-spinner-inline"></span> Enviando…';
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ action:'send_notif_wa', phone, message: text, tipo:'invitacion' }) });
            showToast('Invitación enviada por WhatsApp','success');
            btn.innerHTML = '✓ Enviado';
            setTimeout(() => { btn.innerHTML = origHtml; btn.disabled = false; }, 3000);
        } catch(e) {
            showToast(e.message || 'Error al enviar WhatsApp','error');
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    });

    // Save edited invite data (telefono, nombre, apellido, mensaje)
    $('#cdInvEditSave')?.addEventListener('click', async () => {
        const btn = $('#cdInvEditSave');
        const telefono = ($('#cdInvEditTel')?.value || '').trim();
        const nombre   = ($('#cdInvEditNombre')?.value || '').trim();
        const apellido = ($('#cdInvEditApellido')?.value || '').trim();
        const mensaje  = ($('#cdInvEditMensaje')?.value || '').trim();
        btnLoading(btn, t('status_saving'));
        try {
            await api(INVITE_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ action:'actualizar', id:_invId,
                    telefono, nombre_sugerido:nombre, apellido_sugerido:apellido, mensaje }) });
            inv.telefono = telefono;
            inv.nombre_sugerido = nombre;
            inv.apellido_sugerido = apellido;
            inv.mensaje = mensaje;
            showToast('Invitación actualizada','success');
            closeSidebar();
            loadPersonal();
        } catch(e) {
            showToast(e.message || 'Error al actualizar','error');
        }
        btnReset(btn);
    });
}

$('#cfgInviteBtn')?.addEventListener('click', () => {
    const resCheckboxes = RESIDENTES.map(r => `<label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:0.8125rem;cursor:pointer">
        <input type="checkbox" class="cdInvResCheck" value="${r.id}" ${r.id === _residenteId?'checked':''} style="accent-color:var(--cd-primary)">
        ${esc(r.nombre)}
    </label>`).join('');
    openSidebar(t('users_invite'), `<div class="cd-sidebar-section">
        <div style="background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius);padding:12px;margin-bottom:16px">
            <div style="font-size:0.75rem;color:var(--cd-text-muted);margin-bottom:4px">${t('profile_institution')}</div>
            <div style="font-weight:600;font-size:0.875rem">${esc(INST_NAME)}</div>
        </div>
        <label class="cd-label">Email <span class="cd-required">*</span></label>
        <input class="cd-input" id="cdInvEmail" type="email" placeholder="usuario@correo.com" required>
        <label class="cd-label" style="margin-top:12px">Teléfono WhatsApp <span style="color:var(--cd-text-muted);font-weight:400">(opcional)</span></label>
        <input class="cd-input" id="cdInvTelefono" type="tel" placeholder="+52 1 555 123 4567">
        <div style="display:flex;gap:8px;margin-top:12px">
            <div style="flex:1">
                <label class="cd-label">Nombre <span style="color:var(--cd-text-muted);font-weight:400">(opcional)</span></label>
                <input class="cd-input" id="cdInvNombre" placeholder="Juan">
            </div>
            <div style="flex:1">
                <label class="cd-label">Apellido <span style="color:var(--cd-text-muted);font-weight:400">(opcional)</span></label>
                <input class="cd-input" id="cdInvApellido" placeholder="García">
            </div>
        </div>
        <label class="cd-label" style="margin-top:12px">${t('sidebar_role')} <span class="cd-required">*</span></label>
        <select class="cd-input cd-select-native" id="cdInvRol" required>
            <option value="enfermero">${t('role_enfermero')}</option>
            <option value="medico">${t('role_medico')}</option>
            <option value="admin">${t('role_admin')}</option>
            <option value="familiar">${t('role_familiar')}</option>
        </select>
        <div id="cdInvResidenteWrap" style="display:none;margin-top:12px">
            <label class="cd-label">${t('sidebar_linked_residents')} <span class="cd-required">*</span> <span id="cdInvResCount" style="font-weight:400;color:var(--cd-text-muted)">(0/${RESIDENTES.length})</span></label>
            <input class="cd-input" id="cdInvResSearch" placeholder="Buscar residente..." style="width:100%;font-size:0.8125rem;margin-bottom:6px">
            <div id="cdInvAlphaNav" style="display:flex;flex-wrap:wrap;gap:2px;margin-bottom:6px"></div>
            <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdInvResToggleAll" style="font-size:0.75rem;padding:4px 10px;width:100%;margin-bottom:8px">Seleccionar todos</button>
            <div id="cdInvResList" style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;padding:4px 0" aria-required="true">
                ${resCheckboxes || '<span style="font-size:0.8125rem;color:var(--cd-text-muted)">' + t('users_no_residents') + '</span>'}
            </div>
            <p style="font-size:0.75rem;color:var(--cd-text-muted);margin-top:4px">${t('invite_resident_hint') || t('invite_familiar_hint')}</p>
        </div>
    </div>`, `<button class="cd-btn-submit" id="cdInvSend">${t('btn_send_invite')}</button>`);
    // Update counter helper
    const updateInvResCount = () => {
        const cnt = document.querySelectorAll('.cdInvResCheck:checked').length;
        const el = $('#cdInvResCount');
        if (el) el.textContent = `(${cnt}/${RESIDENTES.length})`;
        const btn = $('#cdInvResToggleAll');
        if (btn) btn.textContent = cnt === RESIDENTES.length ? 'Deseleccionar todos' : 'Seleccionar todos';
        if (cnt > 0) window.cdClearFieldInvalid?.($('#cdInvResList'));
    };
    // Show/hide resident selector based on role (admin sees all, no need to pick)
    const rolSel = $('#cdInvRol');
    const resWrap = $('#cdInvResidenteWrap');
    const toggleResWrap = () => {
        const requiresResidents = rolSel.value !== 'admin';
        const resList = $('#cdInvResList');
        if (resWrap) resWrap.style.display = requiresResidents ? '' : 'none';
        if (resList) {
            resList.setAttribute('aria-required', requiresResidents ? 'true' : 'false');
            if (!requiresResidents) window.cdClearFieldInvalid?.(resList);
        }
        // Auto-select all residents for cuidador
        if (['enfermero', 'cuidador'].includes(rolSel.value)) {
            document.querySelectorAll('.cdInvResCheck').forEach(cb => cb.checked = true);
            updateInvResCount();
        }
    };
    rolSel?.addEventListener('change', toggleResWrap);
    toggleResWrap();
    // ── Alphabet navigation ──────────────────────────────────────────────
    const alphaNav = $('#cdInvAlphaNav');
    if (alphaNav && RESIDENTES.length > 15) {
        const letters = new Set(RESIDENTES.map(r => (r.nombre||'').charAt(0).toUpperCase()).filter(Boolean));
        const sorted = [...letters].sort();
        alphaNav.innerHTML = `<button type="button" class="cdInvAlphaBtn cdInvAlphaActive" data-letter="" style="padding:2px 6px;font-size:0.6875rem;border:1px solid var(--cd-border);background:var(--cd-primary);color:#fff;border-radius:4px;cursor:pointer;min-width:22px">Todos</button>`
            + sorted.map(l => `<button type="button" class="cdInvAlphaBtn" data-letter="${l}" style="padding:2px 6px;font-size:0.6875rem;border:1px solid var(--cd-border);background:var(--cd-bg);color:var(--cd-text);border-radius:4px;cursor:pointer;min-width:22px">${l}</button>`).join('');
        alphaNav.addEventListener('click', e => {
            const btn = e.target.closest('.cdInvAlphaBtn');
            if (!btn) return;
            const letter = btn.dataset.letter;
            alphaNav.querySelectorAll('.cdInvAlphaBtn').forEach(b => {
                b.style.background = b === btn ? 'var(--cd-primary)' : 'var(--cd-bg)';
                b.style.color = b === btn ? '#fff' : 'var(--cd-text)';
                b.classList.toggle('cdInvAlphaActive', b === btn);
            });
            const searchQ = ($('#cdInvResSearch')?.value || '').toLowerCase();
            document.querySelectorAll('#cdInvResList label').forEach(lbl => {
                const name = lbl.textContent.trim();
                const matchLetter = !letter || name.charAt(0).toUpperCase() === letter;
                const matchSearch = !searchQ || name.toLowerCase().includes(searchQ);
                lbl.style.display = (matchLetter && matchSearch) ? '' : 'none';
            });
        });
    }
    updateInvResCount();
    // Toggle all / deselect all
    $('#cdInvResToggleAll')?.addEventListener('click', () => {
        const cbs = [...document.querySelectorAll('.cdInvResCheck')];
        const allChecked = cbs.every(cb => cb.checked);
        cbs.forEach(cb => cb.checked = !allChecked);
        updateInvResCount();
    });
    // Update counter on individual checkbox change
    $('#cdInvResList')?.addEventListener('change', updateInvResCount);
    // Search filter for resident checkboxes (respects active alphabet filter)
    $('#cdInvResSearch')?.addEventListener('input', () => {
        const q = ($('#cdInvResSearch')?.value || '').toLowerCase();
        const activeLetter = alphaNav?.querySelector('.cdInvAlphaActive')?.dataset?.letter || '';
        document.querySelectorAll('#cdInvResList label').forEach(lbl => {
            const name = lbl.textContent.trim();
            const matchLetter = !activeLetter || name.charAt(0).toUpperCase() === activeLetter;
            const matchSearch = !q || name.toLowerCase().includes(q);
            lbl.style.display = (matchLetter && matchSearch) ? '' : 'none';
        });
    });
    $('#cdInvSend')?.addEventListener('click', async () => {
        const _btn = $('#cdInvSend');
        if (_btn.disabled) return;
        const emailInput = $('#cdInvEmail');
        const email = emailInput?.value?.trim();
        const rol = $('#cdInvRol')?.value;
        if (!email || (emailInput && !emailInput.checkValidity())) { window.cdSetFieldInvalid?.(emailInput, true); showToast(t('error_enter_email'),'error'); return; }
        const payload = { email, rol };
        // Optional name fields
        const nombreSug = $('#cdInvNombre')?.value?.trim();
        const apellidoSug = $('#cdInvApellido')?.value?.trim();
        if (nombreSug) payload.nombre_sugerido = nombreSug;
        if (apellidoSug) payload.apellido_sugerido = apellidoSug;
        // Optional phone for WhatsApp
        const telefono = $('#cdInvTelefono')?.value?.trim();
        if (telefono) payload.telefono = telefono;
        // For non-admin roles, send selected residents
        if (rol !== 'admin') {
            payload.residente_ids = [...document.querySelectorAll('.cdInvResCheck:checked')].map(cb => parseInt(cb.value, 10));
            if (!payload.residente_ids.length) {
                window.cdSetFieldInvalid?.($('#cdInvResList'), true);
                showToast('Selecciona al menos un residente para este rol', 'error');
                return;
            }
        }
        btnLoading(_btn, t('status_saving'));
        try {
            await api(INVITE_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify(payload) });
            showToast(t('toast_invite_sent'),'success');
            closeSidebar();
            loadPersonal();
        } catch(e) { showToast(e.message || t('error_save'), 'error'); }
        btnReset(_btn);
    });
});

// ═══════════════════════════════════════════════
// Invitar por QR — genera invitación sin email y muestra QR para compartir
// ═══════════════════════════════════════════════
$('#cfgInviteQRBtn')?.addEventListener('click', () => {
    const roles = [
        { key:'enfermero', label: t('role_enfermero') || 'Cuidador/a' },
        { key:'medico',    label: t('role_medico')    || 'Médico/a' },
        { key:'admin',     label: t('role_admin')     || 'Administrador' },
        { key:'familiar',  label: t('role_familiar')  || 'Familiar' },
    ];
    const roleTabsHtml = roles.map((r,i) => `
        <button type="button" class="cd-qrinv-roletab${i===0?' is-active':''}" data-role="${r.key}"
                style="flex:1;min-width:90px;padding:10px 12px;border:1px solid var(--cd-border);background:var(--cd-bg);color:var(--cd-text);border-radius:var(--cd-radius);cursor:pointer;font-size:0.8125rem;font-weight:600;transition:background .15s,color .15s,border-color .15s">
            ${esc(r.label)}
        </button>`).join('');
    // Residentes picker (solo aplica para roles distintos de admin). Reusa el patrón del invite por email.
    const _resCheckboxes = (RESIDENTES || []).map(r => `<label style="display:flex;align-items:center;gap:8px;padding:4px 6px;font-size:0.8125rem;cursor:pointer;color:var(--cd-text);border-radius:6px">
        <input type="checkbox" class="cdQRInvResCheck" value="${r.id}" style="accent-color:var(--cd-primary)">
        ${esc(r.nombre)}
    </label>`).join('');
    const _resTotal = (RESIDENTES || []).length;
    openSidebar(t('invite_qr_title') || 'Invitación por código QR', `
        <div class="cd-sidebar-section">
            <div style="background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius);padding:12px;margin-bottom:14px">
                <div style="font-size:0.75rem;color:var(--cd-text-muted);margin-bottom:4px">${t('profile_institution') || 'Institución'}</div>
                <div style="font-weight:600;font-size:0.875rem;color:var(--cd-text)">${esc(INST_NAME)}</div>
            </div>
            <label class="cd-label">${t('invite_qr_select_role') || 'Selecciona el rol del invitado'}</label>
            <div id="cdQRInvRoleTabs" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
                ${roleTabsHtml}
            </div>
            <label class="cd-label">${t('invite_qr_validity') || 'Vigencia del QR'}</label>
            <select class="cd-input cd-select-native" id="cdQRInvDias" style="margin-bottom:6px">
                <option value="1">${t('invite_qr_validity_1d') || '1 día'}</option>
                <option value="3">${t('invite_qr_validity_3d') || '3 días'}</option>
                <option value="7" selected>${t('invite_qr_validity_7d') || '7 días'}</option>
                <option value="14">${t('invite_qr_validity_14d') || '14 días'}</option>
                <option value="30">${t('invite_qr_validity_30d') || '30 días'}</option>
            </select>
            <p style="font-size:0.7rem;color:var(--cd-text-muted);margin:0 0 14px">
                ${t('invite_qr_validity_hint') || 'Tras este periodo el enlace expira y no se podrán crear nuevas cuentas con este QR. Una vez utilizado por una persona, el QR se invalida automáticamente.'}
            </p>
            <div id="cdQRInvResWrap" style="display:none;margin-bottom:14px;padding:12px;background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius)">
                <label class="cd-label" style="margin-top:0">${t('sidebar_linked_residents') || 'Residentes vinculados'} <span class="cd-required">*</span>
                    <span id="cdQRInvResCount" style="font-weight:400;color:var(--cd-text-muted)">(0/${_resTotal})</span>
                </label>
                <input class="cd-input" id="cdQRInvResSearch" placeholder="${t('search_resident_ph') || 'Buscar residente…'}" style="width:100%;font-size:0.8125rem;margin-bottom:6px">
                <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdQRInvResToggleAll" style="font-size:0.75rem;padding:4px 10px;width:100%;margin-bottom:8px">${t('btn_select_all') || 'Seleccionar todos'}</button>
                <div id="cdQRInvResList" style="max-height:140px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;padding:4px 0" aria-required="true">
                    ${_resCheckboxes || '<span style="font-size:0.8125rem;color:var(--cd-text-muted)">' + (t('users_no_residents') || 'No hay residentes') + '</span>'}
                </div>
                <p id="cdQRInvResHint" style="font-size:0.75rem;color:var(--cd-text-muted);margin:6px 0 0">${t('invite_resident_hint') || t('invite_familiar_hint') || ''}</p>
            </div>
            <p style="font-size:0.75rem;color:var(--cd-text-muted);margin:0 0 12px">
                ${t('invite_qr_hint') || 'Comparte este QR con la persona que deseas invitar. El registro tendrá prellenados el rol y la institución.'}
            </p>
            <div id="cdQRInvBox" style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:16px;background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius-lg);min-height:280px;justify-content:center">
                <div id="cdQRInvCanvasWrap" style="background:#fff;padding:12px;border-radius:8px;display:none">
                    <canvas id="cdQRInvCanvas"></canvas>
                </div>
                <div id="cdQRInvLoading" style="color:var(--cd-text-muted);font-size:0.8125rem;text-align:center">${t('invite_qr_press_generate') || 'Configura las opciones y presiona “Generar QR”.'}</div>
                <div id="cdQRInvLink" style="display:none;width:100%;font-size:0.7rem;color:var(--cd-text);word-break:break-all;text-align:center;padding:6px 8px;background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:6px"></div>
                <div id="cdQRInvActions" style="display:none;gap:8px;flex-wrap:wrap;justify-content:center">
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdQRInvCopy" style="font-size:0.8125rem;padding:8px 14px">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        ${t('invite_qr_copy') || 'Copiar enlace'}
                    </button>
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdQRInvWa" style="font-size:0.8125rem;padding:8px 14px;background:#25d366;color:#fff;border-color:#25d366">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>
                        ${t('invite_qr_share_wa') || 'Compartir por WhatsApp'}
                    </button>
                </div>
            </div>
        </div>`,
        `<button class="cd-btn-submit" id="cdQRInvNew">${t('invite_qr_generate') || 'Generar QR'}</button>`);

    const tabsWrap = $('#cdQRInvRoleTabs');
    const paintTabs = () => {
        tabsWrap?.querySelectorAll('.cd-qrinv-roletab').forEach(b => {
            const active = b.classList.contains('is-active');
            b.style.background = active ? 'var(--cd-primary)' : 'var(--cd-bg)';
            b.style.color = active ? 'var(--cd-primary-fg)' : 'var(--cd-text)';
            b.style.borderColor = active ? 'var(--cd-primary)' : 'var(--cd-border)';
        });
    };
    paintTabs();

    let _currentRole  = 'enfermero';
    let _currentUrl   = '';
    let _currentToken = '';

    // ── Residente picker logic ──────────────────────────────────────────
    const resWrap   = $('#cdQRInvResWrap');
    const resListEl = $('#cdQRInvResList');
    const resCountEl= $('#cdQRInvResCount');
    const resTglBtn = $('#cdQRInvResToggleAll');
    const resSearch = $('#cdQRInvResSearch');
    const updateResCount = () => {
        const cnt = document.querySelectorAll('.cdQRInvResCheck:checked').length;
        if (resCountEl) resCountEl.textContent = `(${cnt}/${_resTotal})`;
        if (cnt > 0) window.cdClearFieldInvalid?.(resListEl);
        if (resTglBtn) {
            const allChecked = (cnt === _resTotal && _resTotal > 0);
            resTglBtn.textContent = allChecked
                ? (t('btn_deselect_all') || 'Deseleccionar todos')
                : (t('btn_select_all')   || 'Seleccionar todos');
        }
    };
    const toggleResWrapVisibility = (rol) => {
        if (!resWrap) return;
        // admin no requiere selección (verá todo). Otros roles sí.
        resWrap.style.display = (rol === 'admin') ? 'none' : '';
        if (resListEl) {
            resListEl.setAttribute('aria-required', rol === 'admin' ? 'false' : 'true');
            if (rol === 'admin') window.cdClearFieldInvalid?.(resListEl);
        }
        // Auto-marcar todos para cuidador/médico (cobertura clínica completa por defecto).
        // Para familiar dejar vacío: el admin debe elegir explícitamente a qué residentes ata.
        if (rol === 'enfermero' || rol === 'medico') {
            document.querySelectorAll('.cdQRInvResCheck').forEach(cb => cb.checked = true);
        } else if (rol === 'familiar') {
            document.querySelectorAll('.cdQRInvResCheck').forEach(cb => cb.checked = false);
        }
        updateResCount();
    };
    resTglBtn?.addEventListener('click', () => {
        const cbs = [...document.querySelectorAll('.cdQRInvResCheck')];
        const allChecked = cbs.length > 0 && cbs.every(cb => cb.checked);
        cbs.forEach(cb => cb.checked = !allChecked);
        updateResCount();
    });
    resListEl?.addEventListener('change', updateResCount);
    resSearch?.addEventListener('input', () => {
        const q = (resSearch.value || '').toLowerCase();
        document.querySelectorAll('#cdQRInvResList label').forEach(lbl => {
            const name = lbl.textContent.trim().toLowerCase();
            lbl.style.display = (!q || name.includes(q)) ? '' : 'none';
        });
    });
    const getSelectedResIds = () => [...document.querySelectorAll('.cdQRInvResCheck:checked')].map(cb => parseInt(cb.value, 10)).filter(Number.isFinite);

    const renderQR = (text) => {
        const wrap   = $('#cdQRInvCanvasWrap');
        const linkEl = $('#cdQRInvLink');
        const acts   = $('#cdQRInvActions');
        const load   = $('#cdQRInvLoading');
        if (!wrap) return;
        // Render por <img> usando un servicio público de generación de QR
        const src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&format=svg&data=' + encodeURIComponent(text);
        wrap.innerHTML = `<img alt="QR" id="cdQRInvImg" style="width:240px;height:240px;display:block" src="${src}">`;
        const img = wrap.querySelector('img');
        // Mostrar el QR sólo cuando la imagen cargue; manejar error con fallback alterno
        img.onload = () => {
            wrap.style.display = '';
            if (load) load.style.display = 'none';
            if (linkEl) { linkEl.textContent = text; linkEl.style.display = ''; }
            if (acts)   acts.style.display = 'flex';
        };
        img.onerror = () => {
            // Fallback PNG si el SVG falla por algún proxy
            const pngSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' + encodeURIComponent(text);
            if (img.src !== pngSrc) {
                img.src = pngSrc;
                return;
            }
            if (load) load.textContent = 'No se pudo generar el QR. Verifica tu conexión.';
        };
    };

    const generateForRole = async (rol) => {
        _currentRole = rol;
        const wrap = $('#cdQRInvCanvasWrap'); if (wrap) { wrap.style.display = 'none'; wrap.innerHTML = ''; }
        const acts = $('#cdQRInvActions');    if (acts) acts.style.display = 'none';
        const linkEl = $('#cdQRInvLink');     if (linkEl) linkEl.style.display = 'none';
        const load = $('#cdQRInvLoading');
        if (load) { load.style.display = ''; load.textContent = t('invite_qr_loading') || 'Generando QR…'; }
        // Validar selección de residentes para roles no-admin.
        const resIds = getSelectedResIds();
        if (rol !== 'admin' && resIds.length === 0) {
            window.cdSetFieldInvalid?.(resListEl, true);
            const msg = t('invite_qr_need_residents') || 'Selecciona al menos un residente para este rol.';
            showToast(msg, 'error');
            if (load) load.textContent = msg;
            _currentUrl = ''; _currentToken = '';
            return;
        }
        // Vigencia (días) configurable desde el sidebar. Acotar a 1..90 por sanidad.
        const diasSel = $('#cdQRInvDias');
        let dias = parseInt(diasSel?.value || '7', 10);
        if (!Number.isFinite(dias) || dias < 1) dias = 1;
        if (dias > 90) dias = 90;
        const payload = { qr_only: true, rol, dias };
        if (rol !== 'admin') payload.residente_ids = resIds;
        try {
            const res = await api(INVITE_API, {
                method: 'POST',
                headers: { 'Content-Type':'application/json' },
                body: JSON.stringify(payload)
            });
            _currentUrl   = res?.url   || '';
            _currentToken = res?.token || '';
            if (!_currentUrl) throw new Error('URL vacía');
            renderQR(_currentUrl);
        } catch (e) {
            if (load) load.textContent = e.message || 'Error al generar invitación';
        }
    };

    // Regenerar el QR cuando cambia la selección de residentes (si el rol lo requiere).
    // NOTA: Solo invalida el QR ya generado; el usuario debe presionar “Generar QR” para emitir uno nuevo.
    const _invalidateQR = () => {
        if (!_currentUrl) return;
        _currentUrl = ''; _currentToken = '';
        const wrap = $('#cdQRInvCanvasWrap'); if (wrap) { wrap.style.display = 'none'; wrap.innerHTML = ''; }
        const acts = $('#cdQRInvActions');    if (acts) acts.style.display = 'none';
        const linkEl = $('#cdQRInvLink');     if (linkEl) linkEl.style.display = 'none';
        const load = $('#cdQRInvLoading');
        if (load) { load.style.display = ''; load.textContent = t('invite_qr_press_generate') || 'Configura las opciones y presiona “Generar QR”.'; }
        const newBtn = $('#cdQRInvNew');
        if (newBtn) newBtn.textContent = t('invite_qr_generate') || 'Generar QR';
    };
    resListEl?.addEventListener('change', _invalidateQR);
    resTglBtn?.addEventListener('click', () => setTimeout(_invalidateQR, 0));

    // Invalidar el QR cuando cambia la vigencia.
    $('#cdQRInvDias')?.addEventListener('change', _invalidateQR);

    tabsWrap?.addEventListener('click', e => {
        const btn = e.target.closest('.cd-qrinv-roletab');
        if (!btn || btn.disabled) return;
        tabsWrap.querySelectorAll('.cd-qrinv-roletab').forEach(b => b.classList.toggle('is-active', b === btn));
        paintTabs();
        _currentRole = btn.dataset.role;
        toggleResWrapVisibility(btn.dataset.role);
        _invalidateQR();
    });

    $('#cdQRInvCopy')?.addEventListener('click', async () => {
        if (!_currentUrl) return;
        try {
            await navigator.clipboard.writeText(_currentUrl);
            showToast(t('invite_qr_copied') || 'Enlace copiado', 'success');
        } catch { showToast('No se pudo copiar', 'error'); }
    });

    $('#cdQRInvWa')?.addEventListener('click', async () => {
        if (!_currentUrl) return;
        const phone = await cdPrompt('Ingresa el número de WhatsApp del destinatario', {
            title: 'Compartir invitación por WhatsApp',
            placeholder: '+52 1 555 123 4567'
        });
        if (!phone) return;
        const digits = cdNormalizePhone(phone);
        if (!digits) { showToast('Teléfono inválido', 'error'); return; }
        const rolLabel = (t('role_' + _currentRole) || _currentRole).toString();
        const text = `Te invito a unirte a *${INST_NAME}* en GeriApp como *${rolLabel}*.\n\nRegístrate aquí:\n${_currentUrl}`;
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ action:'send_notif_wa', phone: digits, message: text, tipo:'invitacion' }) });
            showToast('Invitación enviada por WhatsApp','success');
        } catch(e) {
            // Fallback: abrir wa.me en nueva pestaña
            window.open(`https://wa.me/${digits}?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
        }
    });

    document.addEventListener('click', function _qrNewHandler(ev) {
        const b = ev.target.closest && ev.target.closest('#cdQRInvNew');
        if (!b) return;
        if (!document.body.contains(b)) { document.removeEventListener('click', _qrNewHandler); return; }
        generateForRole(_currentRole);
        // Tras el primer QR, el botón cambia a “Generar otro QR”.
        b.textContent = t('invite_qr_new') || 'Generar otro QR';
    });

    // Inicializar visibilidad del picker pero NO generar QR automáticamente.
    // El usuario debe presionar “Generar QR” explícitamente.
    toggleResWrapVisibility('enfermero');
});

