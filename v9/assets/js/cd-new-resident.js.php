<?php /* GeriApp — cd-new-resident.js.php
   Lógica del formulario "Nuevo residente" (vista completa tipo Ficha).
   Incluye carga de cuidadores activos de la institución y asignación granular.
*/ ?>
// ── Nuevo Residente: estado y helpers ─────────────────────────────────────
let _nrCaregivers = [];     // [{id, nombre, rol}]
let _nrCaregiversLoaded = false;
let _nrDiagnosticos = [];

function _nrDxCatalog() {
    return (typeof CIE10 !== 'undefined' && Array.isArray(CIE10)) ? CIE10 : [];
}

function _nrRenderDxTags() {
    const tags = $('#cdNrDxTags');
    if (!tags) return;
    if (!_nrDiagnosticos.length) { tags.innerHTML = ''; return; }
    tags.innerHTML = _nrDiagnosticos.map((d, i) =>
        `<span class="cd-nm-dx-tag"><strong>${esc(d.codigo)}</strong> ${esc(d.descripcion)} <button type="button" class="cd-nm-dx-tag-x" data-nr-dx-remove="${i}" aria-label="Quitar ${esc(d.codigo)}">&times;</button></span>`
    ).join('');
    tags.querySelectorAll('[data-nr-dx-remove]').forEach(btn => {
        btn.addEventListener('click', () => {
            _nrDiagnosticos.splice(parseInt(btn.dataset.nrDxRemove, 10), 1);
            _nrRenderDxTags();
        });
    });
}

function _nrDiagnosisValue() {
    return _nrDiagnosticos.map(d => `${d.codigo} - ${d.descripcion}`).join('; ');
}

function _nrInitDxSelector() {
    const search = $('#cdNrDxSearch');
    const drop = $('#cdNrDxDropdown');
    if (!search || !drop || search.dataset.nrDxInit === '1') return;
    search.dataset.nrDxInit = '1';
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        if (q.length < 2) { drop.innerHTML = ''; drop.classList.remove('show'); return; }
        const matches = _nrDxCatalog().filter(e =>
            String(e.c || '').toLowerCase().includes(q) || String(e.d || '').toLowerCase().includes(q)
        ).slice(0, 12);
        if (!matches.length) {
            drop.innerHTML = '<div class="cd-nm-dx-item cd-nm-dx-empty">Sin resultados</div>';
            drop.classList.add('show');
            return;
        }
        drop.innerHTML = matches.map(e => `<div class="cd-nm-dx-item" data-code="${esc(e.c)}" data-desc="${esc(e.d)}"><strong>${esc(e.c)}</strong> ${esc(e.d)}</div>`).join('');
        drop.classList.add('show');
    });
    drop.addEventListener('click', e => {
        const item = e.target.closest('.cd-nm-dx-item[data-code]');
        if (!item) return;
        const code = item.dataset.code;
        const desc = item.dataset.desc;
        if (!_nrDiagnosticos.some(d => d.codigo === code)) {
            _nrDiagnosticos.push({ codigo: code, descripcion: desc });
            _nrRenderDxTags();
        }
        search.value = '';
        drop.innerHTML = '';
        drop.classList.remove('show');
    });
    search.addEventListener('blur', () => setTimeout(() => drop.classList.remove('show'), 200));
    search.addEventListener('focus', () => { if (search.value.trim().length >= 2) search.dispatchEvent(new Event('input')); });
}

function _nrResetForm() {
    const ids = [
        'cdNrNombre','cdNrApellidos','cdNrFechaNac','cdNrSexo','cdNrFechaIngreso',
        'cdNrHabitacion','cdNrDxSearch','cdNrAlergias','cdNrCuidados',
        'cdNrCtNombre','cdNrCtParentesco','cdNrCtTelefono','cdNrCtTelefono2','cdNrCtEmail',
        'cdNrNotas','cdNrCareSearch'
    ];
    ids.forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    _nrDiagnosticos = [];
    _nrRenderDxTags();
    const dxDrop = $('#cdNrDxDropdown');
    if (dxDrop) { dxDrop.innerHTML = ''; dxDrop.classList.remove('show'); }
    // Default fecha_ingreso = hoy
    const fi = $('#cdNrFechaIngreso');
    if (fi) setAppDateInputValue(fi, nowInTz().date);
    // Limpia errores visuales
    $$('#viewNuevoResidente .cd-input.cd-input-error, #viewNuevoResidente .cd-textarea.cd-input-error')
        .forEach(el => el.classList.remove('cd-input-error'));
    // Reset cuidadores (todos sin marcar)
    $$('#cdNrCareList input.cdNrCareCheck').forEach(c => c.checked = false);
    _nrUpdateCareCounter();
}

function _nrRenderCareList(filterText) {
    const list = $('#cdNrCareList');
    if (!list) return;
    const q = (filterText || '').trim().toLowerCase();
    if (!_nrCaregivers.length) {
        list.innerHTML = '<span style="font-size:0.8125rem;color:var(--cd-text-muted);padding:8px">' + t('nr_no_caregivers') + '</span>';
        return;
    }
    // Preserve checked state across re-renders
    const checkedIds = new Set(
        $$('#cdNrCareList input.cdNrCareCheck:checked').map(c => parseInt(c.value))
    );
    const filtered = q
        ? _nrCaregivers.filter(u => (u.nombre || '').toLowerCase().includes(q) || (u.rol || '').toLowerCase().includes(q))
        : _nrCaregivers;
    if (!filtered.length) {
        list.innerHTML = '<span style="font-size:0.8125rem;color:var(--cd-text-muted);padding:8px">Sin coincidencias</span>';
        return;
    }
    list.innerHTML = filtered.map(u => `
        <label style="display:flex;align-items:center;gap:8px;padding:6px 8px;font-size:0.8125rem;cursor:pointer;border-radius:6px" class="cd-nr-care-row">
            <input type="checkbox" class="cdNrCareCheck" value="${u.id}" ${checkedIds.has(u.id) ? 'checked' : ''} style="accent-color:var(--cd-primary)">
            <span style="flex:1">${esc(u.nombre || '')}</span>
            <span style="font-size:0.6875rem;color:var(--cd-text-muted);text-transform:uppercase">${esc(u.rol || '')}</span>
        </label>
    `).join('');
    // Wire change events
    $$('#cdNrCareList input.cdNrCareCheck').forEach(c => {
        c.addEventListener('change', _nrUpdateCareCounter);
    });
}

function _nrUpdateCareCounter() {
    const n = $$('#cdNrCareList input.cdNrCareCheck:checked').length;
    const total = _nrCaregivers.length;
    const ctr = $('#cdNrCareCounter');
    if (ctr) ctr.textContent = `${n}/${total} ` + t('nr_caregivers_count');
    // Toggle button text (select all <-> unselect all)
    const btn = $('#cdNrToggleAllCare');
    if (btn) btn.textContent = (n > 0 && n === total) ? t('nr_unselect_all') : t('nr_select_all');
}

async function _nrLoadCaregivers(force) {
    if (_nrCaregiversLoaded && !force) { _nrRenderCareList(''); return; }
    const list = $('#cdNrCareList');
    if (list) list.innerHTML = '<span style="font-size:0.8125rem;color:var(--cd-text-muted);padding:8px">' + t('nr_loading_caregivers') + '</span>';
    try {
        // Roles cuidadores: enfermero, medico, admin (familiar y superadmin se excluyen).
        const all = await api(`${PERSONAL_API}?estado=activo`);
        const arr = Array.isArray(all) ? all : [];
        _nrCaregivers = arr
            .map(u => ({
                id: parseInt(u.usuario_id || u.id || 0),
                nombre: ((u.nombre || '') + ' ' + (u.apellido || u.apellidos || '')).trim() || u.email || '—',
                rol: u.rol || ''
            }))
            .filter(u => u.id && ['enfermero','medico','admin'].includes(u.rol));
        _nrCaregiversLoaded = true;
        _nrRenderCareList('');
        _nrUpdateCareCounter();
    } catch (e) {
        if (list) list.innerHTML = '<span style="font-size:0.8125rem;color:var(--cd-danger);padding:8px">Error cargando cuidadores</span>';
    }
}

function _nrValidate() {
    const required = [
        ['cdNrNombre',        'Nombre'],
        ['cdNrApellidos',     'Apellidos'],
        ['cdNrFechaNac',      '<?= addslashes(t('ficha_birthdate')) ?>'],
        ['cdNrSexo',          '<?= addslashes(t('ficha_gender')) ?>'],
        ['cdNrFechaIngreso',  '<?= addslashes(t('ficha_admission_date')) ?>'],
        ['cdNrHabitacion',    '<?= addslashes(t('ficha_room')) ?>'],
    ];
    const missing = [];
    let firstBad = null;
    required.forEach(([id, label]) => {
        const el = $('#' + id);
        if (!el) return;
        const v = (el.value || '').trim();
        if (!v) {
            el.classList.add('cd-input-error');
            missing.push(label);
            if (!firstBad) firstBad = el;
        } else {
            el.classList.remove('cd-input-error');
        }
    });
    if (missing.length) {
        showToast(t('nr_validation_missing') + missing.join(', '), 'error');
        if (firstBad) { try { firstBad.focus(); firstBad.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e) {} }
        return false;
    }
    return true;
}

async function _nrSubmit() {
    if (!_nrValidate()) return;
    const fechaNac = appDateInputIso($('#cdNrFechaNac'), { required:true, message:t('ficha_birthdate') + ': ' + appDatePlaceholder() });
    const fechaIngreso = appDateInputIso($('#cdNrFechaIngreso'), { required:true, message:t('ficha_admission_date') + ': ' + appDatePlaceholder() });
    if (!fechaNac || !fechaIngreso) return;
    const saveBtn = $('#cdNrSaveBtn');
    btnLoading(saveBtn, t('status_saving'));

    const cuidadorIds = $$('#cdNrCareList input.cdNrCareCheck:checked').map(c => parseInt(c.value));

    const payload = {
        nombre:              $('#cdNrNombre').value.trim(),
        apellidos:           $('#cdNrApellidos').value.trim(),
        fecha_nacimiento:    fechaNac || null,
        sexo:                $('#cdNrSexo').value || null,
        fecha_ingreso:       fechaIngreso || null,
        habitacion:          $('#cdNrHabitacion').value.trim(),
        diagnostico:         _nrDiagnosisValue(),
        alergias:            $('#cdNrAlergias').value.trim(),
        cuidados_especiales: $('#cdNrCuidados').value.trim(),
        contacto_nombre:     $('#cdNrCtNombre').value.trim(),
        contacto_parentesco: $('#cdNrCtParentesco').value.trim(),
        contacto_telefono:   $('#cdNrCtTelefono').value.trim(),
        contacto_telefono2:  $('#cdNrCtTelefono2').value.trim(),
        contacto_email:      $('#cdNrCtEmail').value.trim(),
        notas:               $('#cdNrNotas').value.trim(),
        cuidador_ids:        cuidadorIds, // backend asignará usuario_residentes
    };

    try {
        const created = await api(RES_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        showToast(t('toast_resident_created') || 'Residente creado', 'success');
        try { if (typeof _refreshResidenteSelect === 'function') await _refreshResidenteSelect(); } catch(e) {}
        try { if (typeof loadResidentes === 'function') await loadResidentes(); } catch(e) {}
        _nrResetForm();
        // Navegar a la ficha del nuevo residente si la API la devolvió
        const newId = created && (created.id || (created.data && created.data.id));
        if (newId) {
            const sel = $('#cdPatientName');
            if (sel) {
                // refresh select first then set
                if (typeof _refreshResidenteSelect === 'function') await _refreshResidenteSelect();
                sel.value = newId;
                sel.dispatchEvent(new Event('change'));
            }
            showView('viewFicha');
        } else {
            showView('viewDashboard');
        }
    } catch (e) {
        showToast(t('error_save') || 'Error al guardar', 'error');
        btnReset(saveBtn);
    }
}

// ── Wire-up del formulario "Nuevo residente" ──────────────────────────────
$('#cdNrSaveBtn')?.addEventListener('click', _nrSubmit);
$('#cdNrCancelBtn')?.addEventListener('click', () => { _nrResetForm(); typeof cdGoPreviousView === 'function' ? cdGoPreviousView('viewDashboard') : showView('viewDashboard'); });
$('#cdNrCareSearch')?.addEventListener('input', e => _nrRenderCareList(e.target.value));
$('#cdNrToggleAllCare')?.addEventListener('click', () => {
    const checks = $$('#cdNrCareList input.cdNrCareCheck');
    if (!checks.length) return;
    const allChecked = checks.every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
    _nrUpdateCareCounter();
});
// Limpia errores visuales al editar
['cdNrNombre','cdNrApellidos','cdNrFechaNac','cdNrSexo','cdNrFechaIngreso','cdNrHabitacion'].forEach(id => {
    const el = $('#' + id);
    el?.addEventListener('input',  () => el.classList.remove('cd-input-error'));
    el?.addEventListener('change', () => el.classList.remove('cd-input-error'));
});
['cdNrFechaNac','cdNrFechaIngreso'].forEach(id => initAppDateTextInput($('#' + id)));
setAppDateInputValue($('#cdNrFechaIngreso'), nowInTz().date);
_nrInitDxSelector();

// ── Atajos rápidos del dashboard (Invitar / Nuevo residente) ─────────────
$$('.cd-qs-btn[data-qs-action]').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm(); _careFormDirty = false;
    const action = b.dataset.qsAction;
    if (action === 'inviteStaff') {
        if (typeof _resOpenFamiliarInvite === 'function') _resOpenFamiliarInvite(null, {}, {});
        else showToast('Módulo de invitaciones no disponible', 'error');
    } else if (action === 'inviteFamily') {
        if (typeof _resOpenFamiliarInvite === 'function') {
            _resOpenFamiliarInvite(_residenteId || null, {}, {});
            // Lock role to familiar after sidebar renders
            setTimeout(() => {
                const famCard = document.querySelector('[data-invite-role-card="familiar"] input[name="cdInvRole"]');
                if (!famCard) return;
                famCard.checked = true;
                famCard.dispatchEvent(new Event('change', { bubbles: true }));
                document.querySelectorAll('[data-invite-role-card]').forEach(c => {
                    c.classList.toggle('is-active', c.dataset.inviteRoleCard === 'familiar');
                    const inp = c.querySelector('input[name="cdInvRole"]');
                    if (inp && inp.value !== 'familiar') { inp.disabled = true; c.classList.add('is-disabled'); }
                });
            }, 0);
        } else showToast('Módulo de invitaciones no disponible', 'error');
    } else if (action === 'newResident') {
        _nrResetForm();
        showView('viewNuevoResidente');
        _nrLoadCaregivers(false);
    }
}));
