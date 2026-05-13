// cd-notif-admin.js — Notification admin (config tab)
// Extracted from cuidados.php (lines 15495)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// NOTIFICACIONES ADMIN (config tab)
// ═══════════════════════════════════════════════
let _notifAdminData = [];
let _notifEditId = null;
let _notifOpciones = [];

$('#cdNotifNewBtn')?.addEventListener('click', () => openNotifSidebar());

async function loadNotificacionesAdmin() {
    const list = $('#cfgNotifList');
    if (!list) return;
    list.innerHTML = skeleton(2);
    try {
        const res = await api(`${NOTIF_API}?action=list`);
        _notifAdminData = res || [];
        renderNotifAdminList();
    } catch(e) { list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem">' + t('error_load_notif') + '</p>'; }
}

function renderNotifAdminList() {
    const list = $('#cfgNotifList');
    if (!list) return;
    if (!_notifAdminData.length) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">' + t('notif_empty') + '</p>';
        return;
    }
    const tipoIcons = { info: 'ℹ️', alerta: '⚠️', pregunta: '❓' };
    list.innerHTML = _notifAdminData.map(n => {
        const fecha = n.creado_at ? new Date(n.creado_at).toLocaleString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '';
        return `<div class="cd-notif-card ${n.activo == 1 ? '' : 'cd-notif-inactive'}" data-notif-id="${n.id}">
            <div class="cd-notif-card-header">
                <span class="cd-notif-tipo-badge cd-notif-tipo-${esc(n.tipo)}">${tipoIcons[n.tipo] || ''} ${esc(n.tipo)}</span>
                <label class="cd-vital-switch cd-notif-card-switch" style="position:relative;top:0;right:0;flex-shrink:0"><input type="checkbox" class="cd-notif-toggle-switch" data-notif-id="${n.id}" ${n.activo == 1 ? 'checked' : ''}><span class="cd-vital-slider"></span></label>
            </div>
            <h4 class="cd-notif-card-title">${esc(n.titulo)}</h4>
            ${n.imagen_url ? '<span class="cd-notif-media-badge">🎬 Media adjunta</span>' : ''}
            <p class="cd-notif-card-msg">${esc(n.mensaje)}</p>
            <div class="cd-notif-card-meta">
                <span>${fecha}</span>
                <span>${n.creador_nombre ? 'por ' + esc(n.creador_nombre) : ''}</span>
                <span class="cd-notif-card-responses" data-notif-id="${n.id}" title="Ver log">${n.respuestas_count || 0} respuesta${n.respuestas_count !== 1 ? 's' : ''}</span>
                ${(Array.isArray(n.roles_destino) && n.roles_destino.length) ? '<span>👥 ' + n.roles_destino.map(r => esc(r.charAt(0).toUpperCase()+r.slice(1))).join(', ') + '</span>' : '<span>👥 Todos</span>'}
            </div>
            <div class="cd-notif-card-actions">
                <button type="button" class="cd-notif-btn-edit" data-notif-id="${n.id}" title="Editar">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button type="button" class="cd-notif-btn-log" data-notif-id="${n.id}" title="Ver log">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </button>
                <button type="button" class="cd-notif-btn-delete" data-notif-id="${n.id}" title="Eliminar">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </div>
        </div>`;
    }).join('');

    // Event delegation for the list
    list.onclick = async e => {
        const editBtn = e.target.closest('.cd-notif-btn-edit');
        const logBtn = e.target.closest('.cd-notif-btn-log');
        const toggleSwitch = e.target.closest('.cd-notif-toggle-switch');
        const deleteBtn = e.target.closest('.cd-notif-btn-delete');
        const responsesLink = e.target.closest('.cd-notif-card-responses');

        if (editBtn) {
            const id = parseInt(editBtn.dataset.notifId);
            editNotificacion(id);
        }
        if (logBtn || responsesLink) {
            const id = parseInt((logBtn || responsesLink).dataset.notifId);
            showNotifLog(id);
        }
        if (toggleSwitch) {
            const id = parseInt(toggleSwitch.dataset.notifId);
            const nuevoActivo = toggleSwitch.checked ? 1 : 0;
            const card = toggleSwitch.closest('.cd-notif-card');
            try {
                await api(NOTIF_API, { method:'PUT', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ id, activo: nuevoActivo }) });
                showToast(nuevoActivo ? 'Notificación activada' : 'Notificación desactivada', 'success');
                if (card) card.classList.toggle('cd-notif-inactive', !nuevoActivo);
                const n = _notifAdminData.find(x => x.id == id);
                if (n) n.activo = nuevoActivo;
            } catch(e) { toggleSwitch.checked = !toggleSwitch.checked; }
            return;
        }
        if (deleteBtn) {
            const id = parseInt(deleteBtn.dataset.notifId);
            if (!await cdConfirm(t('confirm_delete_notif_body'), { title: t('confirm_delete_notif_title'), type: 'danger', okText: t('btn_delete') })) return;
            try {
                await api(`${NOTIF_API}?id=${id}`, { method:'DELETE' });
                showToast(t('toast_notif_deleted'), 'success');
                loadNotificacionesAdmin();
            } catch(e) {}
        }
    };
}

function _notifFormHtml(n) {
    const isEdit = !!n;
    const titulo = n?.titulo || '';
    const mensaje = n?.mensaje || '';
    const tipo = n?.tipo || 'info';
    const activo = n?.activo ?? 1;
    const roles = Array.isArray(n?.roles_destino) ? n.roles_destino : [];
    const roleLabels = { admin: 'Admin', enfermero: 'Cuidador', medico: 'Médico', familiar: 'Familiar' };
    const roleChecks = ['admin','enfermero','medico','familiar'].map(r =>
        `<label style="display:flex;align-items:center;gap:4px;font-size:0.8125rem;cursor:pointer">
            <input type="checkbox" class="cdNotifRoleCheck" value="${r}" ${roles.includes(r)?'checked':''} style="accent-color:var(--cd-primary)"> ${roleLabels[r] || r}
        </label>`).join('');
    return `
        <div class="cd-form-group">
            <label class="cd-form-label">${t('config_notif_titulo_lbl')} <span class="cd-required">*</span></label>
            <div class="cd-ai-field-wrap">
                <input class="cd-input" id="cfgNotifTitulo" placeholder="${t('config_notif_titulo_ph')}" maxlength="200" value="${esc(titulo)}">
                <button type="button" class="cd-ai-rewrite-btn" onclick="aiRewrite('cfgNotifTitulo','titulo_notificacion')" title="Mejorar redacción con IA"><img src="assets/icons/gemini.png" class="cd-ai-icon" alt="AI"></button>
                <button type="button" class="cd-ai-undo-btn" onclick="aiUndo('cfgNotifTitulo')" title="Deshacer cambio IA" style="display:none"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label">${t('config_notif_msg_lbl')} <span class="cd-required">*</span></label>
            <div class="cd-ai-field-wrap">
                <textarea class="cd-textarea" id="cfgNotifMensaje" rows="3" placeholder="${t('config_notif_msg_ph')}">${esc(mensaje)}</textarea>
                <button type="button" class="cd-ai-rewrite-btn" onclick="aiRewrite('cfgNotifMensaje','notificacion')" title="Mejorar redacción con IA"><img src="assets/icons/gemini.png" class="cd-ai-icon" alt="AI"></button>
                <button type="button" class="cd-ai-undo-btn" onclick="aiUndo('cfgNotifMensaje')" title="Deshacer cambio IA" style="display:none"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></button>
            </div>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label">${t('config_notif_type')}</label>
                <select class="cd-input cd-select-native" id="cfgNotifTipo">
                    <option value="info" ${tipo==='info'?'selected':''}>${t('config_notif_type_info')}</option>
                    <option value="alerta" ${tipo==='alerta'?'selected':''}>${t('config_notif_type_alert')}</option>
                    <option value="pregunta" ${tipo==='pregunta'?'selected':''}>${t('config_notif_type_question')}</option>
                </select>
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label">${t('config_notif_status')}</label>
                <select class="cd-input cd-select-native" id="cfgNotifActivo">
                    <option value="1" ${activo==1?'selected':''}>${t('config_notif_active')}</option>
                    <option value="0" ${activo==0?'selected':''}>${t('config_notif_inactive')}</option>
                </select>
            </div>
        </div>
        <div class="cd-form-group" style="margin-top:2px">
            <label class="cd-form-label">${t('notif_roles_label')}</label>
            <p style="font-size:0.7rem;color:var(--cd-text-muted);margin:0 0 6px">${t('notif_roles_hint')}</p>
            <div id="cdNotifRoles" style="display:flex;flex-wrap:wrap;gap:8px">${roleChecks}</div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label">${t('config_notif_media')}</label>
            <label class="cd-notif-media-drop" id="cdNotifMediaDrop">
                <input type="file" id="cfgNotifMediaFile" accept="image/*,video/mp4,video/webm" hidden>
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.45"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span class="cd-notif-media-drop-text">${t('config_notif_media_drop')}</span>
                <span class="cd-notif-media-drop-hint">${t('config_notif_media_hint')}</span>
            </label>
            <div id="cdNotifMediaPreview" class="cd-notif-media-preview" style="display:none"></div>
        </div>
        <div class="cd-form-group cd-notif-opciones-wrap" id="cdNotifOpcionesWrap" style="display:${tipo==='pregunta'?'':'none'}">
            <label class="cd-form-label">${t('config_notif_options')}</label>
            <div id="cdNotifOpcionesList" class="cd-notif-opciones-list"></div>
            <div class="cd-notif-opcion-add-row">
                <input class="cd-input" id="cdNotifOpcionInput" placeholder="${t('config_notif_option_ph')}">
                <button type="button" class="cd-btn-submit cd-btn-sm" id="cdNotifAddOpcionBtn">${t('config_notif_option_add')}</button>
            </div>
        </div>
    `;
}

function _notifBindSidebarEvents() {
    // Tipo → toggle opciones
    $('#cfgNotifTipo')?.addEventListener('change', e => toggleNotifOpciones(e.target.value));
    // Media file input
    $('#cfgNotifMediaFile')?.addEventListener('change', async e => {
        let f = e.target.files[0]; if (!f) return;
        if (f.size > 10*1024*1024) { showToast(t('error_file_max_10mb'),'error'); e.target.value=''; return; }
        if (f.type.startsWith('image/') && !f.type.includes('gif')) f = await compressImage(f);
        _notifMediaFile = f; _notifExistingMedia = null; _renderNotifMediaPreview();
    });
    // Drag-and-drop
    const drop = $('#cdNotifMediaDrop');
    if (drop) {
        ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('cd-dragover'); }));
        ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('cd-dragover'); }));
        drop.addEventListener('drop', async e => {
            let f = e.dataTransfer?.files?.[0]; if (!f) return;
            if (!f.type.match(/^(image\/(gif|jpeg|png|webp)|video\/(mp4|webm))$/)) { showToast(t('error_only_img_gif_mp4'),'error'); return; }
            if (f.size > 10*1024*1024) { showToast(t('error_file_max_10mb'),'error'); return; }
            if (f.type.startsWith('image/') && !f.type.includes('gif')) f = await compressImage(f);
            _notifMediaFile = f; _notifExistingMedia = null; _renderNotifMediaPreview();
        });
    }
    // Add option
    $('#cdNotifAddOpcionBtn')?.addEventListener('click', () => {
        const input = $('#cdNotifOpcionInput');
        const val = input?.value?.trim(); if (!val) return;
        _notifOpciones.push(val); input.value = ''; renderNotifOpciones();
    });
    _renderNotifMediaPreview();
    renderNotifOpciones();
}

function openNotifSidebar(editId) {
    let n = null;
    if (editId) {
        n = _notifAdminData.find(x => x.id == editId);
        if (!n) return;
    }
    _notifEditId = editId || null;
    _notifMediaFile = null;
    _notifExistingMedia = n?.imagen_url || null;
    _notifOpciones = Array.isArray(n?.opciones_respuesta) ? [...n.opciones_respuesta] : [];

    const title = editId ? t('notif_edit_title') : t('config_notif_new');
    const saveLabel = editId ? t('btn_save') : t('config_notif_new');
    openSidebar(title, _notifFormHtml(n),
        `<button type="button" class="cd-btn-outline" id="cdNotifPreviewBtn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> ${t('notif_preview') || 'Vista previa'}</button>`
        + `<button type="button" class="cd-btn-submit" id="cdNotifSaveBtn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> ${esc(saveLabel)}</button>`
    );
    _notifBindSidebarEvents();

    // Preview handler
    $('#cdNotifPreviewBtn')?.addEventListener('click', () => {
        const titulo  = $('#cfgNotifTitulo')?.value?.trim() || '(Sin título)';
        const mensaje = $('#cfgNotifMensaje')?.value?.trim() || '(Sin mensaje)';
        const tipo    = $('#cfgNotifTipo')?.value || 'info';
        let imagen_url = null;
        if (_notifMediaFile) imagen_url = URL.createObjectURL(_notifMediaFile);
        else if (_notifExistingMedia) imagen_url = _notifExistingMedia;
        const opciones = tipo === 'pregunta' ? _notifOpciones : [];
        // Read media size from slider
        const mediaSz = parseInt($('#cdNotifMediaSize')?.value || '180');
        // Use existing showSystemNotification infrastructure
        const fakeNotif = { id: 0, titulo, mensaje, tipo, imagen_url, opciones_respuesta: opciones };
        _pendingNotifs = [fakeNotif];
        _currentNotifIdx = 0;
        showSystemNotification(0);
        // Apply custom media size
        setTimeout(() => {
            const img = $('#cdSysNotifMedia .cd-sysnotif-img');
            if (img) img.style.maxHeight = mediaSz + 'px';
            // Override OK/send button to just close (it's a preview)
            const okBtn = $('#cdSysNotifOkBtn');
            if (okBtn) okBtn.onclick = () => { $('#cdSysNotifOverlay').style.display = 'none'; _pendingNotifs = []; };
            const sendBtn = $('#cdSysNotifSendBtn');
            if (sendBtn) { sendBtn.textContent = 'Cerrar vista previa'; sendBtn.onclick = () => { $('#cdSysNotifOverlay').style.display = 'none'; _pendingNotifs = []; }; }
        }, 50);
    });

    // Save handler
    $('#cdNotifSaveBtn')?.addEventListener('click', async () => {
        const titulo  = $('#cfgNotifTitulo')?.value?.trim();
        const mensaje = $('#cfgNotifMensaje')?.value?.trim();
        const tipo    = $('#cfgNotifTipo')?.value || 'info';
        const activo  = parseInt($('#cfgNotifActivo')?.value ?? '1');
        const roles_destino = [...document.querySelectorAll('.cdNotifRoleCheck:checked')].map(cb => cb.value);
        if (!titulo) { showToast(t('error_title_required'),'error'); return; }
        if (!mensaje) { showToast(t('error_message_required'),'error'); return; }
        const btn = $('#cdNotifSaveBtn');
        btnLoading(btn, t('status_saving'));
        try {
            let imagen_url = _notifExistingMedia || null;
            if (_notifMediaFile) {
                const fd = new FormData(); fd.append('action','upload_media'); fd.append('media',_notifMediaFile);
                const upRes = await api(NOTIF_API, {method:'POST', body:fd}); imagen_url = upRes.url;
            }
            if (!_notifMediaFile && !_notifExistingMedia) imagen_url = null;
            const payload = { titulo, mensaje, tipo, activo, imagen_url, roles_destino };
            if (tipo === 'pregunta' && _notifOpciones.length) payload.opciones_respuesta = _notifOpciones;
            if (_notifEditId) {
                payload.id = _notifEditId;
                await api(NOTIF_API, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
                showToast(t('toast_notif_updated'),'success');
            } else {
                payload.action = 'crear';
                await api(NOTIF_API, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
                showToast(t('toast_notif_created'),'success');
            }
            closeSidebar();
            loadNotificacionesAdmin();
        } catch(e) {} finally { btnReset(btn); }
    });
}

function editNotificacion(id) {
    openNotifSidebar(id);
}

function resetNotifForm() {
    _notifEditId = null;
    _notifOpciones = [];
    _notifMediaFile = null;
    _notifExistingMedia = null;
}

function toggleNotifOpciones(tipo) {
    const wrap = $('#cdNotifOpcionesWrap');
    if (wrap) wrap.style.display = tipo === 'pregunta' ? '' : 'none';
}

function renderNotifOpciones() {
    const list = $('#cdNotifOpcionesList');
    if (!list) return;
    if (!_notifOpciones.length) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.75rem">' + t('notif_no_options') + '</p>';
        return;
    }
    list.innerHTML = _notifOpciones.map((op, i) => `<div class="cd-notif-opcion-chip">
        <span>${esc(op)}</span>
        <button type="button" class="cd-notif-opcion-remove" data-idx="${i}">&times;</button>
    </div>`).join('');
    list.onclick = e => {
        const rm = e.target.closest('.cd-notif-opcion-remove');
        if (!rm) return;
        _notifOpciones.splice(parseInt(rm.dataset.idx), 1);
        renderNotifOpciones();
    };
}

// Media upload state
let _notifMediaFile = null;
let _notifExistingMedia = null;

function _renderNotifMediaPreview() {
    const wrap = $('#cdNotifMediaPreview');
    const dropEl = $('#cdNotifMediaDrop');
    if (!wrap) return;
    const sizeSlider = `<div class="cd-notif-media-size">
        <label>${t('notif_media_size') || 'Tamaño'}</label>
        <input type="range" id="cdNotifMediaSize" min="60" max="400" value="180" step="10">
        <span id="cdNotifMediaSizeVal">180px</span>
    </div>`;
    const bindSize = () => {
        const sl = $('#cdNotifMediaSize'), lbl = $('#cdNotifMediaSizeVal'), media = wrap.querySelector('.cd-notif-prev-media');
        if (sl && media) sl.addEventListener('input', () => { media.style.maxHeight = sl.value + 'px'; if (lbl) lbl.textContent = sl.value + 'px'; });
    };
    if (_notifMediaFile) {
        wrap.style.display = '';
        if (dropEl) dropEl.style.display = 'none';
        const objUrl = URL.createObjectURL(_notifMediaFile);
        const isVideo = _notifMediaFile.type.startsWith('video/');
        wrap.innerHTML = (isVideo
            ? `<video src="${objUrl}" autoplay loop muted playsinline class="cd-notif-prev-media"></video>`
            : `<img src="${objUrl}" alt="Vista previa" class="cd-notif-prev-media">`)
            + '<button type="button" class="cd-notif-media-remove" title="Quitar">&times;</button>'
            + sizeSlider;
        wrap.querySelector('.cd-notif-media-remove').onclick = () => {
            _notifMediaFile = null; _notifExistingMedia = null;
            const fi = $('#cfgNotifMediaFile'); if (fi) fi.value = '';
            _renderNotifMediaPreview();
        };
        bindSize();
    } else if (_notifExistingMedia) {
        wrap.style.display = '';
        if (dropEl) dropEl.style.display = 'none';
        const isVid = /\.(mp4|webm)$/i.test(_notifExistingMedia);
        const src = _notifExistingMedia.startsWith('http') ? _notifExistingMedia : BASE + '/' + _notifExistingMedia;
        wrap.innerHTML = (isVid
            ? `<video src="${esc(src)}" autoplay loop muted playsinline class="cd-notif-prev-media"></video>`
            : `<img src="${esc(src)}" alt="Vista previa" class="cd-notif-prev-media">`)
            + '<button type="button" class="cd-notif-media-remove" title="Quitar">&times;</button>'
            + sizeSlider;
        wrap.querySelector('.cd-notif-media-remove').onclick = () => {
            _notifExistingMedia = null;
            _renderNotifMediaPreview();
        };
        bindSize();
    } else {
        wrap.style.display = 'none'; wrap.innerHTML = '';
        if (dropEl) dropEl.style.display = '';
    }
}

// Show notification log in sidebar
async function showNotifLog(notifId) {
    const notif = _notifAdminData.find(n => n.id == notifId);
    const title = notif?.titulo || 'Notificación #' + notifId;

    openSidebar('Log: ' + title, '<div style="padding:12px 0">' + skeleton(3) + '</div>');

    try {
        const logs = await api(`${NOTIF_API}?action=log&id=${notifId}`);
        let html = '';

        // ── Stats summary ──
        const total = logs?.length || 0;
        const conRespuesta = logs ? logs.filter(l => l.respuesta).length : 0;
        const sinRespuesta = total - conRespuesta;

        html += `<div class="cd-nlog-stats">
            <div class="cd-nlog-stat"><span class="cd-nlog-stat-val">${total}</span><span class="cd-nlog-stat-lbl">Total vistas</span></div>
            <div class="cd-nlog-stat"><span class="cd-nlog-stat-val cd-nlog-stat-ok">${conRespuesta}</span><span class="cd-nlog-stat-lbl">Con respuesta</span></div>
            <div class="cd-nlog-stat"><span class="cd-nlog-stat-val cd-nlog-stat-pending">${sinRespuesta}</span><span class="cd-nlog-stat-lbl">Sin respuesta</span></div>
        </div>`;

        // ── Respuestas breakdown (if opciones) ──
        if (notif && notif.tipo === 'pregunta' && conRespuesta > 0) {
            const respCounts = {};
            logs.forEach(l => { if (l.respuesta) respCounts[l.respuesta] = (respCounts[l.respuesta] || 0) + 1; });
            const sorted = Object.entries(respCounts).sort((a, b) => b[1] - a[1]);
            html += '<div class="cd-nlog-breakdown"><h4 class="cd-nlog-section-title">Desglose de respuestas</h4>';
            sorted.forEach(([resp, count]) => {
                const pct = total > 0 ? Math.round(count / total * 100) : 0;
                html += `<div class="cd-nlog-bar-row">
                    <span class="cd-nlog-bar-label">${esc(resp)}</span>
                    <div class="cd-nlog-bar-track"><div class="cd-nlog-bar-fill" style="width:${pct}%"></div></div>
                    <span class="cd-nlog-bar-count">${count} (${pct}%)</span>
                </div>`;
            });
            html += '</div>';
        }

        // ── Log entries ──
        html += '<h4 class="cd-nlog-section-title" style="margin-top:16px">Respuestas individuales</h4>';
        if (!total) {
            html += '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:8px 0">Nadie ha visto esta notificación aún</p>';
        } else {
            html += '<div class="cd-nlog-entries">';
            logs.forEach(l => {
                let fecha = '—';
                if (l.visto_at) {
                    const d = new Date(l.visto_at.replace(' ', 'T'));
                    fecha = isNaN(d.getTime()) ? l.visto_at : d.toLocaleString('es-MX', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
                }
                const userName = l.usuario_nombre || l.usuario_email || 'ID: ' + l.usuario_id;
                html += `<div class="cd-nlog-entry" data-log-id="${l.id}">
                    <div class="cd-nlog-entry-main">
                        <div class="cd-nlog-entry-user">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span>${esc(userName)}</span>
                        </div>
                        <span class="cd-nlog-entry-date">${fecha}</span>
                    </div>
                    <div class="cd-nlog-entry-resp">${l.respuesta ? esc(l.respuesta) : '<span class="cd-nlog-no-resp">Sin respuesta</span>'}</div>
                    <div class="cd-nlog-entry-actions">
                        <button type="button" class="cd-nlog-btn cd-nlog-btn-edit" data-log-id="${l.id}" data-resp="${esc(l.respuesta || '')}" title="Editar respuesta">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="cd-nlog-btn cd-nlog-btn-del" data-log-id="${l.id}" title="Eliminar">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>`;
            });
            html += '</div>';
        }

        sBody.innerHTML = html;

        // Event delegation for edit/delete within sidebar
        sBody.onclick = async (e) => {
            const editBtn = e.target.closest('.cd-nlog-btn-edit');
            const delBtn  = e.target.closest('.cd-nlog-btn-del');

            if (editBtn) {
                const logId = editBtn.dataset.logId;
                const entry = editBtn.closest('.cd-nlog-entry');
                const respDiv = entry.querySelector('.cd-nlog-entry-resp');
                const currentResp = editBtn.dataset.resp || '';

                // Toggle inline edit
                if (entry.querySelector('.cd-nlog-edit-input')) return;
                respDiv.innerHTML = `<div class="cd-nlog-edit-row">
                    <input type="text" class="cd-nlog-edit-input" value="${esc(currentResp)}" placeholder="Respuesta¦">
                    <button type="button" class="cd-nlog-btn cd-nlog-btn-save" data-log-id="${logId}">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <button type="button" class="cd-nlog-btn cd-nlog-btn-cancel">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>`;
                respDiv.querySelector('.cd-nlog-edit-input').focus();
            }

            if (e.target.closest('.cd-nlog-btn-save')) {
                const saveBtn = e.target.closest('.cd-nlog-btn-save');
                const logId = saveBtn.dataset.logId;
                const input = saveBtn.closest('.cd-nlog-edit-row').querySelector('.cd-nlog-edit-input');
                const newResp = input.value.trim();
                try {
                    await api(NOTIF_API, { method:'PUT', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({ action:'update_log_entry', log_id: parseInt(logId), respuesta: newResp || null }) });
                    showToast(t('toast_response_updated'), 'success');
                    showNotifLog(notifId);
                } catch(err) { showToast(t('error_update'), 'error'); }
            }

            if (e.target.closest('.cd-nlog-btn-cancel')) {
                showNotifLog(notifId);
            }

            if (delBtn) {
                const logId = delBtn.dataset.logId;
                if (!await cdConfirm(t('confirm_delete_log_body'), { title: t('confirm_delete_log_title'), type: 'danger', okText: t('btn_delete') })) return;
                try {
                    await api(`${NOTIF_API}?action=delete_log_entry&log_id=${logId}`, { method:'DELETE' });
                    showToast(t('toast_log_deleted'), 'success');
                    showNotifLog(notifId);
                    loadNotificacionesAdmin();
                } catch(err) { showToast(t('error_delete'), 'error'); }
            }
        };

    } catch(e) { sBody.innerHTML = '<p style="color:var(--cd-danger);padding:12px">Error al cargar log</p>'; }
}

