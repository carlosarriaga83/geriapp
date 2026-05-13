// cd-notes.js — Nursing notes
// Extracted from cuidados.php (lines 6288)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// NOTES
// ═══════════════════════════════════════════════
async function loadNotes(silent = false) {
    if (!_residenteId) return;
    const list = $('#cdNotesList');
    if (!silent && list) list.innerHTML = skeleton(2);
    try {
        const data = await api(`${API_URL}?notas=1&residente_id=${_residenteId}&fecha=${_fecha}`);
        const notas = data.notas || [];
        const count = data.count || 0;
        $('#cdNotesCount').textContent = `(${count})`;
        const notesBadge = $('[data-cat-count="notas"]');
        if (notesBadge) { notesBadge.textContent = count; notesBadge.classList.toggle('visible', count > 0); }
        // Urgent/important note indicator on notas tab button
        const notasBtn = $('.cd-cat-btn[data-cat="notas"]');
        const notasBadge2 = $('[data-cat-count="notas"]');
        if (notasBtn) {
            const hasUrgent = notas.some(n => n.prioridad === 'urgente' || n.prioridad === 'importante');
            notasBtn.classList.toggle('cd-cat-btn--alert', hasUrgent);
            if (notasBadge2) notasBadge2.classList.toggle('cd-cat-badge--alert', hasUrgent);
        }
        _notas = notas;
        renderNotes(notas);
    } catch(e) { if (list) list.innerHTML = ''; }
}

function renderNotes(notas) {
    const list = $('#cdNotesList');
    if (!notas.length) { list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;text-align:center;padding:24px 0">Sin notas para este día</p>'; return; }
    list.innerHTML = notas.map(n => {
        const isOwn = parseInt(n.usuario_id) === CURRENT_USER_ID;
        const imgHtml = n.imagen ? `<img src="${BASE}/${esc(n.imagen)}" class="cd-note-img" alt="Imagen adjunta" onclick="window.open(this.src,'_blank')">` : '';
        const actionsHtml = isOwn ? `<div class="cd-note-actions">
            <button class="cd-note-edit" data-perm-id="form_edit_nota_btn" data-note-id="${n.id}" title="Editar"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <button class="cd-note-delete" data-perm-id="form_delete_nota_btn" data-note-id="${n.id}" title="Eliminar"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        </div>` : '';
        return `<div class="cd-note-card ${isOwn ? 'cd-note-card--own' : 'cd-note-card--other'}" data-note-id="${n.id}">
            <div class="cd-note-head">
                <span class="cd-note-author">${esc(shortName(n.usuario_nombre||'Usuario'))}
                    ${n.prioridad !== 'normal' ? `<span class="cd-note-priority ${n.prioridad}">${n.prioridad}</span>` : ''}
                </span>
                <span class="cd-note-time">${n.creado_at ? (() => { const _d = fmtDateTime(n.creado_at); const _p = n.creado_at.includes('T') ? n.creado_at : n.creado_at.replace(' ','T')+'Z'; const _td = toAppTz(_p); const _dd = String(_td.getDate()).padStart(2,'0')+'-'+String(_td.getMonth()+1).padStart(2,'0')+'-'+_td.getFullYear(); return esc(_dd+' '+_d.time); })() : ''}</span>
            </div>
            <p class="cd-note-text">${esc(n.nota)}</p>
            ${imgHtml}
            ${actionsHtml}
        </div>`;
    }).join('');
    // Auto-scroll to bottom (newest)
    requestAnimationFrame(() => { list.scrollTop = list.scrollHeight; });
    // Bind edit / delete
    $$('.cd-note-edit', list).forEach(btn => {
        btn.addEventListener('click', () => startEditNote(parseInt(btn.dataset.noteId)));
    });
    $$('.cd-note-delete', list).forEach(btn => {
        btn.addEventListener('click', () => deleteNote(parseInt(btn.dataset.noteId)));
    });
}

function startEditNote(noteId) {
    const card = document.querySelector(`.cd-note-card[data-note-id="${noteId}"]`);
    if (!card) return;
    const textEl = card.querySelector('.cd-note-text');
    const currentText = textEl.textContent;
    textEl.style.display = 'none';
    const actions = card.querySelector('.cd-note-actions');
    if (actions) actions.style.display = 'none';
    const editArea = document.createElement('textarea');
    editArea.className = 'cd-note-edit-area';
    editArea.value = currentText;
    const editBtns = document.createElement('div');
    editBtns.className = 'cd-note-edit-btns';
    editBtns.innerHTML = `<button class="cd-note-save-edit" data-perm-id="form_save_nota_edit_btn">Guardar</button><button class="cd-note-cancel-edit"><?= t('btn_cancel') ?></button>`;
    textEl.after(editArea, editBtns);
    editArea.focus();
    editBtns.querySelector('.cd-note-cancel-edit').addEventListener('click', () => {
        editArea.remove(); editBtns.remove();
        textEl.style.display = '';
        if (actions) actions.style.display = '';
    });
    editBtns.querySelector('.cd-note-save-edit').addEventListener('click', async () => {
        const _btn = editBtns.querySelector('.cd-note-save-edit');
        if (_btn.disabled) return;
        const newText = editArea.value.trim();
        if (!newText) { showToast(t('error_note_empty'), 'error'); return; }
        btnLoading(_btn, t('status_saving'));
        try {
            await api(API_URL, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ action:'actualizar_nota', nota_id: noteId, nota: newText })
            });
            showToast(t('toast_note_updated'),'success');
            loadNotes();
        } catch(e) { showToast(t('error_update'),'error'); btnReset(_btn); }
    });
}

async function deleteNote(noteId) {
    if (!await cdConfirm(t('confirm_delete_note'), { title: t('confirm_delete_note_title'), type: 'danger', okText: t('btn_delete') })) return;
    try {
        await api(`${API_URL}?nota_id=${noteId}`, { method: 'DELETE' });
        showToast(t('toast_note_deleted'),'success');
        loadNotes();
    } catch(e) { showToast(t('error_delete'),'error'); }
}

// Priority chips
$$('.cd-note-priority-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        $$('.cd-note-priority-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
    });
});

$('#cdNoteSubmit')?.addEventListener('click', async () => {
    const nota = $('#cdNoteInput').value.trim();
    if (!nota && !_noteImage) return;
    if (!_residenteId) return;
    const todayCheck = nowInTz().date;
    if (!CAN_FUTURE && _fecha > todayCheck) { showToast(t('error_no_future_notes'), 'error'); return; }
    const prioridad = $('.cd-note-priority-chip.active')?.dataset.val || 'normal';
    const _btn = $('#cdNoteSubmit');
    btnLoading(_btn, t('status_saving'));
    try {
        await api(API_URL, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action:'crear_nota', residente_id:_residenteId, nota: nota || '📷 Imagen', prioridad, fecha:_fecha, imagen: _noteImage || null })
        });
        $('#cdNoteInput').value = '';
        _noteImage = null;
        $('#cdNoteImgPreview').innerHTML = '';
        const fileInput = $('#cdNoteFileInput');
        if (fileInput) fileInput.value = '';
        showToast(t('toast_note_saved'),'success');
        loadNotes();
    } catch(e) {}
    btnReset(_btn);
});

// Note image attachment
let _noteImage = null;

$('#cdNoteAttachBtn')?.addEventListener('click', () => {
    $('#cdNoteFileInput')?.click();
});

$('#cdNoteFileInput')?.addEventListener('change', async (e) => {
    let file = e.target.files[0];
    if (!file) return;
    file = await compressImage(file);
    const preview = $('#cdNoteImgPreview');
    const url = URL.createObjectURL(file);
    preview.innerHTML = `<div class="cd-note-img-preview"><img src="${url}"><button class="cd-note-img-remove" type="button">&times;</button></div>`;
    preview.querySelector('.cd-note-img-remove').addEventListener('click', () => {
        _noteImage = null;
        preview.innerHTML = '';
        e.target.value = '';
    });
    const fd = new FormData();
    fd.append('foto', file);
    try {
        const res = await fetch(`${API_URL}?upload_foto=1`, { method:'POST', body:fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        _noteImage = json.data.url;
    } catch(err) {
        showToast(t('error_upload_image'),'error');
        _noteImage = null;
        preview.innerHTML = '';
    }
});

