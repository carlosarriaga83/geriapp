// cd-patient.js — Patient selector
// Extracted from cuidados.php (lines 3172)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// PATIENT SELECTOR
// ═══════════════════════════════════════════════
resSelect.addEventListener('change', async (evt) => {
    if (_careFormDirty && !await confirmCareLeave()) { resSelect.value = _residenteId; return; }
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) { resSelect.value = _residenteId; return; }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) { resSelect.value = _residenteId; return; }
    resetCareForm(); _careFormDirty = false; _medFormDirty = false; _nmFormDirty = false;
    clearEditState();
    _residenteId = parseInt(resSelect.value) || 0;
    updatePatientName();

    // Anti-bouncing: deshabilitar el select y marcar la tarjeta como ocupada
    // mientras se actualiza la información. Se restaura al terminar.
    const _resCard = $('#cdHeaderResidentCard');
    resSelect.disabled = true;
    if (_resCard) _resCard.classList.add('is-busy');
    const _restoreSel = () => {
        resSelect.disabled = false;
        if (_resCard) _resCard.classList.remove('is-busy');
    };

    // Desde Inicio (viewResidentes) navegar a Cuidados (viewDashboard) del residente seleccionado.
    // skipNav=true: el llamador ya maneja la navegación (p.ej. abrir viewFicha directamente).
    if (_currentView === 'viewResidentes' && !evt?.detail?.skipNav) {
        // Ghost: marcar el contenido del dashboard como cargando antes de cambiar de vista
        const dashRoot = document.querySelector('#viewDashboard .cd-content-wrap');
        if (dashRoot) dashRoot.classList.add('cd-ghost-loading');
        if (typeof showView === 'function') showView('viewDashboard');
        if (typeof loadDashboard === 'function') {
            Promise.resolve(loadDashboard()).finally(() => {
                if (dashRoot) dashRoot.classList.remove('cd-ghost-loading');
                _restoreSel();
            });
        } else {
            if (dashRoot) dashRoot.classList.remove('cd-ghost-loading');
            _restoreSel();
        }
        return;
    }
    try {
        const r = reloadCurrentView();
        if (r && typeof r.finally === 'function') r.finally(_restoreSel);
        else setTimeout(_restoreSel, 600);
    } catch (e) {
        _restoreSel();
    }
});

/** Reload data for the current view after resident change — shows ghost skeletons while loading. Returns Promise. */
function reloadCurrentView() {
    const v = _currentView;

    // If on a form sub-view, go to dashboard
    if (v.startsWith('viewForm')) {
        showView('viewDashboard');
        return Promise.resolve(loadDashboard());
    }

    switch (v) {
        case 'viewDashboard':
            return Promise.resolve(loadDashboard());

        case 'viewFicha':
            // Ghost the resident info fields
            ['cdResName','cdResAge','cdResNombre','cdResApellidos','cdResRoom','cdResSex',
             'cdResDOB','cdResAdmit','cdResDiag','cdResAllergy','cdResDoctor'].forEach(id => {
                const el = $('#' + id);
                if (!el) return;
                el.dataset.prevText = el.textContent;
                el.textContent = '\u00a0';
                el.classList.add('cd-skeleton', 'cd-ghost-field');
            });
            return Promise.resolve(loadResidentInfo());

        case 'viewRecords': {
            // Ghost: limpiar timeline e inyectar skeleton inmediato antes de fetch
            const tl = $('#cdRecTimeline');
            if (tl) {
                $$('.cd-tl-skeleton', tl).forEach(el => el.remove());
                $$('.cd-tl-item', tl).forEach(el => el.remove());
                $$('.cd-tl-date-sep', tl).forEach(el => el.remove());
                const empty = $('#cdRecTimelineEmpty');
                if (empty) empty.style.display = 'none';
                const skEl = document.createElement('div');
                skEl.className = 'cd-tl-skeleton';
                skEl.innerHTML = skeleton(4);
                tl.prepend(skEl);
            }
            // Forzar refetch (puede haber caché de otro residente)
            _recordsDirty = true;
            return Promise.resolve(loadRecords({ force: true }));
        }

        case 'viewInventory':
            return Promise.resolve(loadInventory()); // already has built-in skeletons

        case 'viewExpediente':
            if (typeof _reloadExpediente === 'function') return Promise.resolve(_reloadExpediente());
            return Promise.resolve();

        case 'viewConfig':
        case 'viewResidentes':
            // No dependen del residente seleccionado
            return Promise.resolve();

        default:
            showView('viewDashboard');
            return Promise.resolve(loadDashboard());
    }
}

function updatePatientName() {
    const sel = $('#cdPatientName');
    if (sel && _residenteId) sel.value = _residenteId;
    updateHeaderResidentCard();
}

function updateHeaderResidentCard() {
    const card = $('#cdHeaderResidentCard');
    const avatar = $('#cdHeaderResidentAvatar');
    const nameEl = $('#cdHeaderResidentName');
    if (!card || !avatar || !nameEl) return;

    const residentId = parseInt(_residenteId) || 0;
    const boot = RESIDENTES.find(r => parseInt(r.id) === residentId) || null;
    const fromFull = (_resData && parseInt(_resData.id) === residentId) ? _resData : null;
    const fullName = fromFull
        ? `${fromFull.nombre || ''} ${fromFull.apellidos || ''}`.trim()
        : (boot?.nombre || $('#cdPatientName')?.selectedOptions?.[0]?.textContent || t('header_no_residents'));
    const fotoPath = fromFull?.foto_path || boot?.foto_path || '';

    card.classList.toggle('is-empty', !residentId);
    nameEl.textContent = fullName || t('header_no_residents');
    card.title = `${t('header_change_resident')}: ${fullName || t('header_no_residents')}`;

    if (fotoPath) {
        avatar.innerHTML = `<img src="${BASE}/${esc(fotoPath)}" alt="">`;
    } else {
        avatar.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    }
}

