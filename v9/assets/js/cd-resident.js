// cd-resident.js — Resident info, avatar photos
// Extracted from cuidados.php (lines 3412)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// RESIDENT INFO
// ═══════════════════════════════════════════════
const _resDataCache = {}; // client-side cache keyed by residente_id

async function loadResidentInfo(force = false) {
    if (!_residenteId) return;
    // Use cache if available (dashboard batch populates it)
    if (!force && _resDataCache[_residenteId]) {
        _resData = _resDataCache[_residenteId];
        renderResidentInfo(_resData);
        return;
    }
    try {
        const res = await fetch(`${RES_API}?id=${_residenteId}`);
        const json = await res.json();
        if (!json.success) return;
        _resData = json.data;
        _resDataCache[_residenteId] = _resData;
        renderResidentInfo(_resData);
    } catch(e) { /* silent */ }
}

function renderResidentInfo(r) {
    // Clear ghost/skeleton classes from resident change
    $$('.cd-ghost-field').forEach(el => { el.classList.remove('cd-skeleton', 'cd-ghost-field'); });

    const fullName = (r.nombre||'') + ' ' + (r.apellidos||'');
    const age = r.fecha_nacimiento ? calcAge(r.fecha_nacimiento) : '—';
    const _sexoLabel = {M:'Masculino',F:'Femenino',Otro:'Otro'};
    const ageStr = age !== '—' ? `${age} años · ${_sexoLabel[r.sexo]||r.sexo||''}` : '';

    // Full card (Residente view)
    $('#cdResName').textContent = fullName;
    $('#cdResAge').textContent = ageStr;
    const nEl = $('#cdResNombre');
    const aEl = $('#cdResApellidos');
    if (nEl) nEl.textContent = r.nombre || '—';
    if (aEl) aEl.textContent = r.apellidos || '—';
    $('#cdResRoom').textContent = r.habitacion || '—';
    $('#cdResSex').textContent = ({M:'Masculino',F:'Femenino',Otro:'Otro'})[r.sexo] || r.sexo || '—';
    const dobEl = $('#cdResDOB');
    if (dobEl) dobEl.textContent = r.fecha_nacimiento ? fmtDate(r.fecha_nacimiento) : '—';
    $('#cdResAdmit').textContent = r.fecha_ingreso ? fmtDate(r.fecha_ingreso) : '—';
    $('#cdResDiag').textContent = r.diagnostico || 'Sin diagnóstico registrado';
    $('#cdResAllergy').textContent = r.alergias || 'Sin alergias registradas';
    $('#cdResDoctor').textContent = r.medico_nombre || '—';

    // Populate inline edit inputs with current data
    const fieldMap = {
        nombre: r.nombre || '',
        apellidos: r.apellidos || '',
        habitacion: r.habitacion || '',
        sexo: r.sexo || '',
        fecha_nacimiento: r.fecha_nacimiento || '',
        fecha_ingreso: r.fecha_ingreso || '',
        diagnostico: r.diagnostico || '',
        alergias: r.alergias || '',
        medico_nombre: r.medico_nombre || ''
    };
    for (const [field, val] of Object.entries(fieldMap)) {
        const el = document.querySelector(`#cdResGrid [data-field="${field}"] .cd-inline-edit`);
        if (!el) continue;
        if (field === 'fecha_nacimiento' || field === 'fecha_ingreso') {
            initAppDateTextInput(el);
            setAppDateInputValue(el, val);
        } else {
            el.value = val;
        }
    }

    const avatar = $('#cdResAvatar');
    const overlayHtml = '<div class="cd-avatar-overlay" id="cdAvatarOverlay"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>';
    const badgeHtml = '<span class="cd-avatar-edit-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>';
    if (r.foto_path) {
        avatar.innerHTML = `<img src="${BASE}/${esc(r.foto_path)}" alt="Foto">${overlayHtml}${badgeHtml}`;
    } else {
        avatar.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>${overlayHtml}${badgeHtml}`;
    }

    // Family info section — render dynamic contact blocks
    renderFamilyContacts(r);
    if (typeof updateHeaderResidentCard === 'function') updateHeaderResidentCard();
    // Check notifications/alerts for this resident
    _checkNotifAlerts();

    // Extra info section — populate static editable grid
    const estadoValue = r.estado || 'activo';
    const estadoClass = estadoValue === 'activo' ? 'cd-res-estado-activo' : (estadoValue === 'egresado' ? 'cd-res-estado-egresado' : 'cd-res-estado-fallecido');
    const estadoEl = document.getElementById('cdResEstado');
    if (estadoEl) {
        estadoEl.textContent = estadoValue;
        estadoEl.className = `cd-res-estado-badge ${estadoClass}`;
    }

    const extraFields = {
        estado_civil: r.estado_civil || '',
        curp: r.curp || '',
        nss: r.nss || '',
        cuidados_especiales: r.cuidados_especiales || '',
        notas: r.notas || '',
        estado: estadoValue
    };
    const extraIdMap = {
        estado_civil: 'cdResEstadoCivil',
        curp: 'cdResCurp',
        nss: 'cdResNss',
        cuidados_especiales: 'cdResCuidados',
        notas: 'cdResNotas',
        estado: 'cdResEstado'
    };
    for (const [field, val] of Object.entries(extraFields)) {
        const el = document.getElementById(extraIdMap[field]);
        if (el && field !== 'estado') el.textContent = val || '—';
        document.querySelectorAll(`#cdResExtraGrid [data-field="${field}"] .cd-inline-edit, #cdResStatusPanel [data-field="${field}"] .cd-inline-edit`).forEach(editEl => { editEl.value = val; });
    }
    // Auto-resize all textareas after populating
    _autoResizeAllTextareas();
}

function _autoResizeTextarea(ta) {
    if (!ta || ta.tagName !== 'TEXTAREA') return;
    ta.style.height = 'auto';
    ta.style.height = ta.scrollHeight + 'px';
}
function _autoResizeAllTextareas() {
    $$('#cdResGrid textarea.cd-inline-edit, #cdResExtraGrid textarea.cd-inline-edit').forEach(_autoResizeTextarea);
}
// Bind input event for live auto-resize during editing
$$('#cdResGrid textarea.cd-inline-edit, #cdResExtraGrid textarea.cd-inline-edit').forEach(ta => {
    ta.addEventListener('input', () => _autoResizeTextarea(ta));
});

// ── Avatar photo management ─────────────────────────────────
(function() {
    const avatar = $('#cdResAvatar');
    if (!avatar) return;
    let _avatarMenu = null;

    function _closeAvatarMenu() {
        if (_avatarMenu) { _avatarMenu.remove(); _avatarMenu = null; }
    }

    document.addEventListener('click', e => {
        if (_avatarMenu && !_avatarMenu.contains(e.target) && !avatar.contains(e.target)) _closeAvatarMenu();
    });

    async function _pickAvatarPhoto() {
        let file = null;
        if (_isCapacitorNative()) {
            file = await _capacitorPickPhoto();
        } else if (_isMobileWeb()) {
            file = await _showPhotoActionSheet('avatar', avatar);
        } else {
            // Desktop: create temp file input
            file = await new Promise(resolve => {
                const tmp = document.createElement('input');
                tmp.type = 'file'; tmp.accept = 'image/*'; tmp.style.display = 'none';
                document.body.appendChild(tmp);
                tmp.addEventListener('change', () => { resolve(tmp.files[0] || null); tmp.remove(); });
                const onFocus = () => { setTimeout(() => { if (!tmp.files.length) { tmp.remove(); resolve(null); } }, 500); window.removeEventListener('focus', onFocus); };
                window.addEventListener('focus', onFocus);
                tmp.click();
            });
        }
        return file;
    }

    async function _uploadAvatarPhoto(file) {
        if (!file || !_resData?.id) return;
        showToast(t('status_saving'), 'info');
        try {
            const compressed = await compressImage(file, {maxWidth: 600, maxHeight: 600, quality: 0.85});
            const fd = new FormData();
            fd.append('foto', compressed);
            const res = await fetch(`${RES_API}?id=${_resData.id}`, { method:'POST', body: fd, credentials:'include' });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            _resData.foto_path = json.data.url;
            delete _resDataCache[_residenteId];
            avatar.querySelector('img')?.remove();
            const img = document.createElement('img');
            img.src = `${BASE}/${json.data.url}`;
            img.alt = 'Foto';
            avatar.insertBefore(img, avatar.firstChild);
            const boot = RESIDENTES.find(r => parseInt(r.id) === parseInt(_residenteId));
            if (boot) boot.foto_path = json.data.url;
            if (typeof updateHeaderResidentCard === 'function') updateHeaderResidentCard();
            showToast(t('toast_photo_saved') || 'Foto actualizada', 'success');
        } catch(e) { showToast(e.message || t('error_upload_photo'), 'error'); }
    }

    async function _deleteAvatarPhoto() {
        if (!_resData?.id || !_resData.foto_path) return;
        try {
            const res = await fetch(`${RES_API}?id=${_resData.id}&foto=1`, { method:'DELETE', credentials:'include' });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            _resData.foto_path = null;
            delete _resDataCache[_residenteId];
            avatar.querySelector('img')?.remove();
            // Re-add default SVG if not present
            if (!avatar.querySelector(':scope > svg')) {
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '1.5');
                svg.innerHTML = '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
                avatar.insertBefore(svg, avatar.firstChild);
            }
            const boot = RESIDENTES.find(r => parseInt(r.id) === parseInt(_residenteId));
            if (boot) boot.foto_path = null;
            if (typeof updateHeaderResidentCard === 'function') updateHeaderResidentCard();
            showToast(t('toast_photo_deleted') || 'Foto eliminada', 'success');
        } catch(e) { showToast(e.message || t('error_save'), 'error'); }
    }

    avatar.addEventListener('click', async (e) => {
        e.stopPropagation();
        _closeAvatarMenu();
        const hasPhoto = !!_resData?.foto_path;
        if (!hasPhoto) {
            // No photo: directly trigger pick
            const file = await _pickAvatarPhoto();
            if (file) await _uploadAvatarPhoto(file);
            return;
        }
        // Has photo: show menu with change/delete options
        _avatarMenu = document.createElement('div');
        _avatarMenu.className = 'cd-avatar-menu';
        _avatarMenu.innerHTML = `
            <button type="button" class="cd-avatar-menu-btn" data-action="change">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                ${t('photo_change') || 'Cambiar foto'}
            </button>
            <button type="button" class="cd-avatar-menu-btn danger" data-action="delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                ${t('photo_delete') || 'Eliminar foto'}
            </button>`;
        avatar.appendChild(_avatarMenu);
        _avatarMenu.querySelector('[data-action="change"]').addEventListener('click', async (ev) => {
            ev.stopPropagation();
            _closeAvatarMenu();
            const file = await _pickAvatarPhoto();
            if (file) await _uploadAvatarPhoto(file);
        });
        _avatarMenu.querySelector('[data-action="delete"]').addEventListener('click', async (ev) => {
            ev.stopPropagation();
            _closeAvatarMenu();
            await _deleteAvatarPhoto();
        });
    });
})();

function calcAge(dob) {
    const b = new Date(dob), t = new Date();
    let a = t.getFullYear() - b.getFullYear();
    if (t.getMonth() < b.getMonth() || (t.getMonth()===b.getMonth() && t.getDate()<b.getDate())) a--;
    return a;
}

