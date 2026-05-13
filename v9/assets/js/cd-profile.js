// cd-profile.js — Profile edit
// Extracted from cuidados.php (lines 4574)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// PROFILE EDIT
// ═══════════════════════════════════════════════
const PERSONAL_API = BASE + '/api/personal.php';
let _profileEditing = false;

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

