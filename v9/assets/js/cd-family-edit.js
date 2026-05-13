// cd-family-edit.js — Family tab edit (admin)
// Extracted from cuidados.php (lines 4500)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// FAMILY TAB EDIT (admin only)
// ═══════════════════════════════════════════════
let _famEditing = false;
let _famOriginalValues = '';

function _getFamFormValues() {
    return JSON.stringify(_collectFamilyContacts());
}

function _famHasChanges() {
    return _famOriginalValues !== _getFamFormValues();
}

function _updateFamEditButton() {
    const btn = $('#cdResFamEditBtn');
    if (!btn) return;
    if (_famEditing) {
        const hasChanges = _famHasChanges();
        btn.textContent = hasChanges ? t('btn_save') : t('btn_cancel');
        btn.classList.toggle('is-save', hasChanges);
        const actions = $('#cdResFamEditActions');
        if (actions) actions.style.display = hasChanges ? 'flex' : 'none';
    } else {
        btn.textContent = t('btn_edit');
        btn.classList.remove('is-save');
    }
}

function toggleFamEdit(on) {
    _famEditing = on;
    $$('#cdResFamilyContainer .cd-res-field').forEach(f => {
        if (f.querySelector('.cd-inline-edit')) f.classList.toggle('editing', on);
    });
    const actions = $('#cdResFamEditActions');
    if (actions) actions.style.display = on ? 'flex' : 'none';
    _updateFamEditButton();
}

$('#cdResFamEditBtn')?.addEventListener('click', () => {
    if (!_famEditing) {
        if (_resData) renderFamilyContacts(_resData);
        toggleFamEdit(true);
        _famOriginalValues = _getFamFormValues();
        $$('#cdResFamilyContainer .cd-inline-edit').forEach(inp => {
            inp.addEventListener('input', _updateFamEditButton);
            inp.addEventListener('change', _updateFamEditButton);
        });
    } else {
        if (_famHasChanges()) {
            $('#cdResFamSaveBtn')?.click();
        } else {
            toggleFamEdit(false);
        }
    }
});

$('#cdResFamCancelBtn')?.addEventListener('click', () => {
    if (_resData) renderFamilyContacts(_resData);
    toggleFamEdit(false);
});

$('#cdResFamSaveBtn')?.addEventListener('click', async () => {
    if (!_resData) return;
    const _btn = $('#cdResFamSaveBtn');
    btnLoading(_btn, t('status_saving'));
    const contacts = _collectFamilyContacts();
    // Merge notif prefs from existing _notifContacts by identity (name+phone), not index
    contacts.forEach(c => {
        const key = ((c.nombre || '') + '|' + (c.telefono || '')).toLowerCase();
        const existing = _notifContacts.find(nc => ((nc.nombre || '') + '|' + (nc.telefono || '')).toLowerCase() === key);
        if (existing) {
            c.notif_stock = existing.notif_stock ?? 0;
            c.notif_reporte = existing.notif_reporte ?? 0;
            c.notif_reporte_hora = existing.notif_reporte_hora ?? '08:00';
            c.notif_reporte_pdf = existing.notif_reporte_pdf ?? 0;
            c.notif_signos = existing.notif_signos ?? 0;
            c.notif_incidentes = existing.notif_incidentes ?? 0;
            c.notif_medicacion = existing.notif_medicacion ?? 0;
            c.notif_alimentacion = existing.notif_alimentacion ?? 0;
            c.notif_animo = existing.notif_animo ?? 0;
            c.notif_wa = existing.notif_wa ?? 0;
            c.notif_email = existing.notif_email ?? 0;
        }
    });
    const data = {};
    if (contacts.length > 0) {
        const first = contacts[0];
        data.contacto_nombre = first.nombre || '';
        data.contacto_parentesco = first.parentesco || '';
        data.contacto_telefono = first.telefono || '';
        data.contacto_telefono2 = first.telefono2 || '';
        data.contacto_email = first.email || '';
        data.contacto_direccion = first.direccion || '';
    } else {
        data.contacto_nombre = '';
        data.contacto_parentesco = '';
        data.contacto_telefono = '';
        data.contacto_telefono2 = '';
        data.contacto_email = '';
        data.contacto_direccion = '';
    }
    data.contactos_json = JSON.stringify(contacts);

    try {
        await fetch(`${RES_API}?id=${_resData.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json()).then(j => { if (!j.success) throw new Error(j.message||'Error'); });

        // Sync back changes to linked user records
        for (const c of contacts) {
            if (!c._usuario_id) continue;
            try {
                await fetch(`${PERSONAL_API}?id=${c._usuario_id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre: c.nombre || '', email: c.email || '', telefono: c.telefono || '' })
                });
            } catch(e) { /* best-effort sync */ }
        }

        showToast(t('toast_contacts_updated'), 'success');
        toggleFamEdit(false);
        delete _resDataCache[_residenteId];
        await loadResidentInfo();
        const phone = data.contacto_telefono;
        if (phone && phone !== (_resData.contacto_telefono || '')) {
            try {
                const waRes = await api(`${API_URL}?check_whatsapp=${encodeURIComponent(phone)}`);
                if (waRes?.registered === true) showToast(t('toast_wa_registered'), 'success');
                else if (waRes?.registered === false) showToast(t('toast_wa_not_registered'), 'error');
            } catch(e) {}
        }
    } catch(e) { showToast(e.message, 'error'); }
    btnReset(_btn);
});
