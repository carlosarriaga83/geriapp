// cd-info-edit.js — Info tab edit (admin)
// Extracted from cuidados.php (lines 4361)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// INFO TAB EDIT (admin only)
// ═══════════════════════════════════════════════
let _infoEditing = false;
let _infoOriginalValues = {};

function _getInfoFormValues() {
    const vals = {};
    ['#cdResGrid', '#cdResExtraGrid', '#cdResStatusPanel'].forEach(gridSel => {
        $$(gridSel + ' .cd-res-field[data-field]').forEach(f => {
            const input = f.querySelector('.cd-inline-edit');
            if (!input) return;
            vals[f.dataset.field] = input.value;
        });
    });
    return vals;
}

function _infoHasChanges() {
    const current = _getInfoFormValues();
    return Object.keys(_infoOriginalValues).some(k => _infoOriginalValues[k] !== (current[k] ?? ''));
}

function _updateInfoEditButton() {
    const btn = $('#cdResInfoEditBtn');
    if (!btn) return;
    if (_infoEditing) {
        const hasChanges = _infoHasChanges();
        btn.textContent = hasChanges ? t('btn_save') : t('btn_cancel');
        btn.classList.toggle('is-save', hasChanges);
        const actions = $('#cdResInfoEditActions');
        if (actions) actions.style.display = 'flex';
    } else {
        btn.textContent = t('btn_edit');
        btn.classList.remove('is-save');
    }
}

function toggleInfoEdit(on) {
    _infoEditing = on;
    $('#viewFicha')?.classList.toggle('is-info-editing', on);
    ['#cdResGrid', '#cdResExtraGrid', '#cdResStatusPanel'].forEach(gridSel => {
        $$(gridSel + ' .cd-res-field[data-field]').forEach(f => {
            if (!f.querySelector('.cd-inline-edit')) return;
            f.classList.toggle('editing', on);
        });
    });
    const actions = $('#cdResInfoEditActions');
    if (actions) actions.style.display = on ? 'flex' : 'none';
    _updateInfoEditButton();
    if (on) _autoResizeAllTextareas();
    // Populate doctor datalist when entering edit mode (medico role only)
    if (on) {
        const dl = $('#cdDoctorList');
        if (dl && !dl.childElementCount) {
            api(PERSONAL_API + '?rol=medico&limit=200').then(personal => {
                const arr = Array.isArray(personal) ? personal : [];
                dl.innerHTML = arr.map(p => `<option value="${esc(p.nombre)}">`).join('');
            }).catch(() => {});
        }
    }
}

// Invite doctor link removed from Ficha (no invitations/notifications in this view).

$('#cdResInfoEditBtn')?.addEventListener('click', () => {
    if (!_infoEditing) {
        if (_resData) {
            const fm = {
                nombre: _resData.nombre||'', apellidos: _resData.apellidos||'',
                habitacion: _resData.habitacion||'', sexo: _resData.sexo||'',
                fecha_nacimiento: _resData.fecha_nacimiento||'',
                fecha_ingreso: _resData.fecha_ingreso||'',
                diagnostico: _resData.diagnostico||'', alergias: _resData.alergias||'',
                medico_nombre: _resData.medico_nombre||''
            };
            for (const [k,v] of Object.entries(fm)) {
                const el = document.querySelector(`#cdResGrid [data-field="${k}"] .cd-inline-edit`);
                if (!el) continue;
                if (k === 'fecha_nacimiento' || k === 'fecha_ingreso') {
                    initAppDateTextInput(el);
                    setAppDateInputValue(el, v);
                } else {
                    el.value = v;
                }
            }
            const extraMap = {
                estado_civil: _resData.estado_civil || '',
                curp: _resData.curp || '',
                nss: _resData.nss || '',
                cuidados_especiales: _resData.cuidados_especiales || '',
                notas: _resData.notas || '',
                estado: _resData.estado || 'activo'
            };
            for (const [k,v] of Object.entries(extraMap)) {
                document.querySelectorAll(`#cdResExtraGrid [data-field="${k}"] .cd-inline-edit, #cdResStatusPanel [data-field="${k}"] .cd-inline-edit`).forEach(el => { el.value = v; });
            }
        }
        toggleInfoEdit(true);
        _infoOriginalValues = _getInfoFormValues();
        ['#cdResGrid', '#cdResExtraGrid', '#cdResStatusPanel'].forEach(gridSel => {
            $$(gridSel + ' .cd-inline-edit').forEach(inp => {
                inp.addEventListener('input', _updateInfoEditButton);
                inp.addEventListener('change', _updateInfoEditButton);
            });
        });
    } else {
        if (_infoHasChanges()) {
            $('#cdResInfoSaveBtn')?.click();
        } else {
            toggleInfoEdit(false);
        }
    }
});

$('#cdResInfoCancelBtn')?.addEventListener('click', () => {
    if (_resData) renderResidentInfo(_resData);
    toggleInfoEdit(false);
});

$('#cdResInfoSaveBtn')?.addEventListener('click', async () => {
    if (!_resData) return;
    const _btn = $('#cdResInfoSaveBtn');
    const data = {};
    let invalidDate = false;
    $$('#cdResGrid .cd-res-field[data-field]').forEach(f => {
        const input = f.querySelector('.cd-inline-edit');
        if (!input) return;
        const field = f.dataset.field;
        if (field === 'medico_nombre') return;
        if (field === 'fecha_nacimiento' || field === 'fecha_ingreso') {
            const iso = appDateInputIso(input, { required: field === 'fecha_ingreso', message: (field === 'fecha_ingreso' ? t('ficha_admission_date') : t('ficha_birthdate')) + ': ' + appDatePlaceholder() });
            if (iso === null) { invalidDate = true; return; }
            data[field] = iso || '';
        } else {
            data[field] = input.value.trim();
        }
    });
    if (invalidDate) return;
    if (!data.nombre) { showToast(t('error_name_required'), 'error'); return; }
    if (!data.apellidos) { showToast(t('error_surname_required'), 'error'); return; }
    btnLoading(_btn, t('status_saving'));

    $$('#cdResExtraGrid .cd-res-field[data-field], #cdResStatusPanel .cd-res-field[data-field]').forEach(f => {
        const input = f.querySelector('.cd-inline-edit');
        if (!input) return;
        data[f.dataset.field] = input.value.trim();
    });
    const estadoInput = document.querySelector('#cdResStatusPanel [data-field="estado"] .cd-inline-edit');
    if (estadoInput) data.estado = estadoInput.value || 'activo';

    const medicoInput = document.querySelector('#cdResGrid [data-field="medico_nombre"] .cd-inline-edit');
    const medicoText = medicoInput ? medicoInput.value.trim() : '';
    if (medicoText !== (_resData.medico_nombre || '')) {
        if (!medicoText) {
            data.medico_id = null;
        } else {
            try {
                const personal = await api(PERSONAL_API + '?rol=medico&limit=200');
                const arr = Array.isArray(personal) ? personal : [];
                const lower = medicoText.toLowerCase();
                const match = arr.find(p => {
                    const fullName = ((p.nombre || '') + ' ' + (p.apellidos || '')).trim().toLowerCase();
                    return fullName === lower || (p.nombre || '').toLowerCase() === lower;
                });
                if (match) {
                    data.medico_id = match.usuario_id || match.user_id || match.id;
                } else {
                    showToast('Médico "' + medicoText + '" no encontrado en personal', 'error');
                    btnReset(_btn);
                    return;
                }
            } catch(e) {
                showToast(t('error_save') || 'Error al buscar médico', 'error');
                btnReset(_btn);
                return;
            }
        }
    }

    try {
        const prevEstado = _resData.estado || 'activo';
        const saved = await fetch(`${RES_API}?id=${_resData.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json()).then(j => { if (!j.success) throw new Error(j.message||'Error'); return j.data || null; });
        if (data.estado && data.estado !== prevEstado) {
            try { await api(`${RES_API}?id=${_resData.id}&estado_log=1`, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ estado_anterior: prevEstado, estado_nuevo: data.estado }) }); } catch(e) {}
        }
        showToast(t('toast_info_updated'), 'success');
        toggleInfoEdit(false);
        if (saved && typeof saved === 'object') _resData = saved;
        delete _resDataCache[_residenteId];
        await loadResidentInfo(true);
        if (typeof loadResidentes === 'function') {
            const filter = $('#cdResMgmtFilter');
            if (filter && data.estado) filter.value = data.estado;
            await loadResidentes();
        }
        const opt = document.querySelector(`#cdPatientName option[value="${_residenteId}"]`);
        if (opt && data.nombre) opt.textContent = (data.nombre + ' ' + (data.apellidos||'')).trim();
    } catch(e) { showToast(e.message, 'error'); }
    btnReset(_btn);
});

