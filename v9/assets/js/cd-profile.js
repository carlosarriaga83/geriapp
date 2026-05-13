// cd-profile.js — Profile edit
// Extracted from cuidados.php (lines 4574)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// PROFILE EDIT
// ═══════════════════════════════════════════════
const PERSONAL_API = BASE + '/api/personal.php';
let _profileEditing = false;

function _profileInitials() {
    return String($('#cdProfName')?.textContent || $('#cdProfEditName')?.value || '')
        .trim().split(/\s+/).slice(0, 2).map(s => s[0] || '').join('').toUpperCase() || '?';
}

function _profileSetAvatar(url) {
    const html = url ? `<img src="${esc(url)}" alt="">` : `<span>${esc(_profileInitials())}</span>`;
    ['cdProfAvatarView', 'cdProfAvatarEdit', 'cdAvatarBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    });
}

$('#cdProfileEditBtn')?.addEventListener('click', () => {
    _profileEditing = true;
    $('#cdProfileView').style.display = 'none';
    $('#cdProfileEdit').style.display = 'block';
});

$('#cdProfCancelBtn')?.addEventListener('click', () => {
    _profileEditing = false;
    $('#cdProfileEdit').style.display = 'none';
    $('#cdProfileView').style.display = '';
});

$('#cdProfAvatarView')?.addEventListener('click', () => $('#cdProfileEditBtn')?.click());
$('#cdProfAvatarEdit')?.addEventListener('click', () => $('#cdProfAvatarInput')?.click());
$('#cdProfAvatarBtn')?.addEventListener('click', () => $('#cdProfAvatarInput')?.click());
$('#cdProfAvatarInput')?.addEventListener('change', async e => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (!/^image\//.test(file.type || '')) { showToast('Selecciona una imagen válida', 'error'); return; }
    const btn = $('#cdProfAvatarBtn');
    btnLoading(btn, 'Subiendo');
    try {
        const fd = new FormData();
        fd.append('archivo', file);
        fd.append('contexto', 'avatar');
        const res = await fetch(BASE + '/api/upload.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'No se pudo subir la foto');
        const url = json.data?.url || json.data?.path || '';
        if (url) _profileSetAvatar(url);
        showToast('Foto de perfil actualizada', 'success');
    } catch(e) { showToast(e.message || 'No se pudo subir la foto', 'error'); }
    btnReset(btn);
});

$('#cdProfSaveBtn')?.addEventListener('click', async () => {
    const name  = $('#cdProfEditName').value.trim();
    const phone = ($('#cdProfEditPhone')?.value || '').trim();
    if (!name) { showToast(t('error_name_required'), 'error'); return; }
    // El email NO se edita desde aquí (campo readonly).
    const _btn = $('#cdProfSaveBtn');
    btnLoading(_btn, t('status_saving'));

    try {
        const res = await fetch(PERSONAL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_profile', nombre: name, telefono: phone })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Error');

        showToast(t('toast_profile_saved'), 'success');
        // Update UI
        $('#cdProfName').textContent = name;
        const ph = $('#cdProfPhone'); if (ph) ph.textContent = phone || '—';
        if (!$('#cdProfAvatarView')?.querySelector('img')) _profileSetAvatar('');

        _profileEditing = false;
        $('#cdProfileEdit').style.display = 'none';
        $('#cdProfileView').style.display = '';
    } catch(e) { showToast(e.message, 'error'); }
    btnReset(_btn);
});

$('#cdProfPassBtn')?.addEventListener('click', async () => {
    const cur  = $('#cdProfCurPass').value;
    const npw  = $('#cdProfNewPass').value;
    const conf = $('#cdProfConfPass').value;
    if (!cur) { showToast(t('error_enter_current_pw'), 'error'); return; }
    if (!npw || npw.length < 8) { showToast(t('error_pw_min_length'), 'error'); return; }
    if (npw !== conf) { showToast(t('error_pw_mismatch'), 'error'); return; }
    const _btn = $('#cdProfPassBtn');
    btnLoading(_btn, t('status_changing'));

    try {
        const res = await fetch(PERSONAL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cambiar_password', current_password: cur, password: npw })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Error');

        showToast(t('toast_pw_updated'), 'success');
        $('#cdProfCurPass').value = '';
        $('#cdProfNewPass').value = '';
        $('#cdProfConfPass').value = '';
    } catch(e) { showToast(e.message, 'error'); }
    btnReset(_btn);
});

