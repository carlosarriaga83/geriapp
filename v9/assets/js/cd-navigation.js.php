// cd-navigation.js — Navigation, History API, view switching, popstate, sleep pending
// Extracted from cuidados.php (lines 3009)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// NAVIGATION (with History API for browser back)
// ═══════════════════════════════════════════════
let _currentView = <?= json_encode($initialView ?? 'viewDashboard') ?>;
let _lastSignos = null;
let _suenoPendiente = null;
const _slideClasses = ['cd-slide-in-right','cd-slide-out-left','cd-slide-in-left','cd-slide-out-right'];
const _viewDataLoaded = new Set();
const _viewBackStack = [];
function _clearSlide(el) { el.classList.remove(..._slideClasses); }
function _hideInactiveView(el) {
    if (!el || el.classList.contains('active')) return;
    _clearSlide(el);
    el.classList.remove('cd-fade-up-in');
    el.style.display = '';
}
function _runViewAnimationCleanup(el, cleanup, fallbackMs = 650) {
    if (!el || typeof cleanup !== 'function') return;
    let done = false;
    const finish = () => {
        if (done) return;
        done = true;
        el.removeEventListener('animationend', finish);
        cleanup();
    };
    el.addEventListener('animationend', finish, { once: true });
    setTimeout(finish, fallbackMs);
    requestAnimationFrame(() => {
        const cs = getComputedStyle(el);
        if (cs.animationName === 'none' || cs.animationDuration === '0s') finish();
    });
}

async function loadViewData(viewId, opts = {}) {
    const force = !!opts.force;
    const alwaysFresh = viewId === 'viewExpediente' || viewId === 'viewInvitaciones' || viewId === 'viewResidentes';
    if (!force && viewId === 'viewRecords' && _viewDataLoaded.has(viewId) && typeof loadRecords === 'function') {
        await loadRecords({ silent: true, reason: 'enter' });
        return;
    }
    if (!force && !alwaysFresh && _viewDataLoaded.has(viewId)) return;
    let task = null;
    if (viewId === 'viewDashboard' && typeof loadDashboard === 'function') task = loadDashboard(false);
    else if (viewId === 'viewRecords' && typeof loadRecords === 'function') task = loadRecords({ force });
    else if (viewId === 'viewInventory' && typeof loadInventory === 'function') task = loadInventory();
    else if (viewId === 'viewConfig' && typeof loadConfig === 'function') {
        task = loadConfig(force);
        if (typeof loadPersonal === 'function') loadPersonal();
    }
    else if (viewId === 'viewResidentes' && typeof loadResidentes === 'function') task = loadResidentes();
    else if (viewId === 'viewInvitaciones' && typeof loadInvitaciones === 'function') task = loadInvitaciones();
    else if (viewId === 'viewFicha' && typeof loadResidentInfo === 'function') task = (async () => {
        await loadResidentInfo(force);
        if (document.querySelector('.cd-ficha-tab[data-ficha-tab="notif"]')?.classList.contains('active')) {
            if (typeof _loadNotifLog === 'function') _loadNotifLog();
            if (typeof _checkNotifAlerts === 'function') _checkNotifAlerts();
            if (typeof _populateNotifContactSelect === 'function') _populateNotifContactSelect();
        }
    })();
    else if (viewId === 'viewExpediente' && typeof window._reloadExpediente === 'function') task = window._reloadExpediente({ force: true });

    if (task && typeof task.then === 'function') await task;
    _viewDataLoaded.add(viewId);
}

async function refreshViewData(viewId = _currentView) {
    await loadViewData(viewId, { force: true });
}

function showView(viewId, pushState = true, slideDir = null) {
    const oldEl = $('#' + _currentView);
    const newEl = $('#' + viewId);
    const prevView = _currentView;

    // Determine slide direction: 'right' = form entering, 'left' = going back
    // slideDir auto-detected if not provided
    if (slideDir == null && oldEl && newEl && _currentView !== viewId) {
        const isForm = id => id.startsWith('viewForm');
        if (isForm(viewId) && !isForm(_currentView)) slideDir = 'right';
        else if (!isForm(viewId) && isForm(_currentView)) slideDir = 'left';
    }

    if (slideDir && oldEl && newEl && _currentView !== viewId) {
        // Animate old view out
        _clearSlide(oldEl);
        oldEl.classList.remove('active');
        oldEl.classList.add(slideDir === 'right' ? 'cd-slide-out-left' : 'cd-slide-out-right');
        oldEl.style.display = 'block';
        _runViewAnimationCleanup(oldEl, () => _hideInactiveView(oldEl));

        // Hide other views (not old, not new)
        $$('.cd-view').forEach(v => {
            if (v !== oldEl && v !== newEl) { v.classList.remove('active'); _hideInactiveView(v); }
        });

        // Animate new view in
        _clearSlide(newEl);
        newEl.classList.add('active');
        newEl.classList.add(slideDir === 'right' ? 'cd-slide-in-right' : 'cd-slide-in-left');
        _runViewAnimationCleanup(newEl, () => _clearSlide(newEl));
    } else {
        // Instant switch — add a subtle fade-up so the user perceives the context change
        $$('.cd-view').forEach(v => { v.classList.remove('active'); _hideInactiveView(v); });
        if (newEl) {
            newEl.style.display = '';
            newEl.classList.add('active');
            if (_currentView !== viewId) {
                newEl.classList.remove('cd-fade-up-in');
                // Force reflow so animation re-runs
                void newEl.offsetWidth;
                newEl.classList.add('cd-fade-up-in');
                newEl.addEventListener('animationend', function h() {
                    newEl.removeEventListener('animationend', h);
                    newEl.classList.remove('cd-fade-up-in');
                }, { once: true });
            }
        }
    }

    $$('.cd-nav-item').forEach(b => b.classList.remove('active'));
    const nav = $(`.cd-nav-item[data-nav="${viewId}"]`);
    if (nav) nav.classList.add('active');
    // Header config button (mirrors bottom-nav active state)
    const hCfg = $('#cdHeaderConfigBtn');
    if (hCfg) hCfg.classList.toggle('cd-icon-btn--active', viewId === 'viewConfig');
    // Sync nav sidebar
    $$('.cd-nav-sidebar-item').forEach(b => b.classList.remove('active'));
    const sideNav = $(`.cd-nav-sidebar-item[data-nav="${viewId}"]`);
    if (sideNav) sideNav.classList.add('active');
    loadViewData(viewId);
    if (viewId === 'viewRecords') requestAnimationFrame(() => typeof _positionRecordPeriodPill === 'function' && _positionRecordPeriodPill());
    if (viewId === 'viewConfig') requestAnimationFrame(() => typeof _positionAllVisibleCfgPills === 'function' && _positionAllVisibleCfgPills());
    if (viewId === 'viewFicha') {
        requestAnimationFrame(() => typeof _positionFichaPill === 'function' && _positionFichaPill());
    }
    const _dnViews = ['viewDashboard', 'viewRecords', 'viewInventory'];
    const _dn = $('#cdGlobalDateNav');
    if (_dn) _dn.style.display = _dnViews.includes(viewId) ? '' : 'none';
    if (pushState && prevView && prevView !== viewId) {
        _viewBackStack.push(prevView);
        if (_viewBackStack.length > 30) _viewBackStack.shift();
    }
    // Resident card siempre interactivo: el usuario puede cambiar de residente desde
    // cualquier vista. La actualización de datos se maneja en reloadCurrentView()
    // (cd-patient.js) con ghost loaders y skeletons por vista.
    const _resCard = $('#cdHeaderResidentCard');
    const _resSel  = $('#cdPatientName');
    const _isInstitutionView = viewId === 'viewInvitaciones';
    if (_resCard) {
        _resCard.classList.remove('is-locked');
        _resCard.hidden = _isInstitutionView;
        _resCard.style.display = _isInstitutionView ? 'none' : '';
        _resCard.setAttribute('aria-hidden', _isInstitutionView ? 'true' : 'false');
    }
    if (_resSel) {
        _resSel.disabled = false;
        _resSel.tabIndex = 0;
        _resSel.hidden = _isInstitutionView;
        _resSel.style.display = _isInstitutionView ? 'none' : '';
        _resSel.setAttribute('aria-hidden', _isInstitutionView ? 'true' : 'false');
    }
    _currentView = viewId;
    // Refresh event-time-group date chip whenever a care-form view becomes active
    if (viewId.startsWith('viewForm') && typeof window._updateEventDateChip === 'function') {
        window._updateEventDateChip(viewId);
    }
    if (pushState) history.pushState({ view: viewId }, '', '');
}

function cdGoPreviousView(fallbackView = <?= json_encode($initialView ?? 'viewDashboard') ?>) {
    let target = '';
    while (_viewBackStack.length && !target) {
        const candidate = _viewBackStack.pop();
        if (candidate && candidate !== _currentView && document.getElementById(candidate)) target = candidate;
    }
    if (!target) target = document.getElementById(fallbackView) ? fallbackView : 'viewDashboard';
    showView(target, false, 'left');
    try { history.replaceState({ view: target }, '', ''); } catch(e) {}
}

// Initial history state
history.replaceState({ view: <?= json_encode($initialView ?? 'viewDashboard') ?> }, '', '');

// Warn on browser close/reload if unsaved changes
window.addEventListener('beforeunload', e => {
    if (_medFormDirty || _nmFormDirty || _careFormDirty) { e.preventDefault(); e.returnValue = ''; }
});

// Handle browser back/forward
window.addEventListener('popstate', async e => {
    if (_currentView === 'viewFormMedicacion' && _medFormDirty) {
        if (!await cdConfirm(t('confirm_unsaved_meds'), { title: t('confirm_unsaved_title'), type: 'warn', okText: t('confirm_unsaved_exit'), cancelText: t('confirm_unsaved_stay') })) {
            history.pushState({ view: _currentView }, '', '');
            return;
        }
        _medFormDirty = false;
        renderRxTracker();
    }
    if (_currentView === 'viewFormNotasMedico' && _nmFormDirty) {
        if (!await confirmNmLeave()) {
            history.pushState({ view: _currentView }, '', '');
            return;
        }
        _nmFormDirty = false;
        nmHideForm();
    }
    if (_careFormDirty) {
        if (!await confirmCareLeave()) {
            history.pushState({ view: _currentView }, '', '');
            return;
        }
        resetCareForm();
        _careFormDirty = false;
    }
    const view = e.state?.view || <?= json_encode($initialView ?? 'viewDashboard') ?>;
    showView(view, false);
});

$$('.cd-nav-item').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm(); _careFormDirty = false;
    showView(b.dataset.nav);
}));

// Header config button (moved from bottom bar)
$('#cdHeaderConfigBtn')?.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm(); _careFormDirty = false;
    showView('viewConfig');
});

// Atajos rápidos del dashboard (Ver registros / Medicación del día)
$$('.cd-qs-btn[data-qs-nav]').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm(); _careFormDirty = false;
    showView(b.dataset.qsNav);
}));
$$('.cd-nav-sidebar-item').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm(); _careFormDirty = false;
    if (b.dataset.nav === 'viewFicha') {
        const fv = $('#viewFicha');
        if (fv) fv.dataset.mode = 'all';
    }
    showView(b.dataset.nav);
}));

$$('.cd-cat-btn').forEach(b => {
    b.addEventListener('click', async () => {
        // Prompt if leaving unsaved medicación form
        if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
        if (_currentView === 'viewFormMedicacion') { _medFormDirty = false; renderRxTracker(); }
        // Prompt if leaving unsaved NM form
        if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
        if (_currentView === 'viewFormNotasMedico') { _nmFormDirty = false; nmHideForm(); }
        if (_careFormDirty && !await confirmCareLeave()) return;
        resetCareForm(); _careFormDirty = false;
        const cat = b.dataset.cat;
        // Notas médicas con acceso directo (notas_medico_directo): bypass readonly redirect
        // and navigate straight to the medical notes view regardless of role.
        if (cat === 'notas_medico' && (typeof CAN_NM_DIRECTO === 'undefined' || CAN_NM_DIRECTO)) {
            showView('viewFormNotasMedico', true, false);
            if (typeof nmLoadCurrentForResident === 'function') nmLoadCurrentForResident();
            else renderNotasMedico();
            return;
        }
        // Read-only mode (familiar): the cards act as shortcuts to the records
        // timeline filtered by the selected category. Skip form navigation.
        if (b.closest('.cd-categories--readonly')) {
            const RECORD_CATS = ['sueno','alimentacion','higiene','eliminacion','movilidad','medicacion','signos_vitales','terapia','comportamiento','incidente'];
            const filterCat = RECORD_CATS.includes(cat) ? cat : '';
            try { _recCatFilter = filterCat; } catch(e) {}
            showView('viewRecords', true, false);
            const sel = document.getElementById('cdRecCatFilter');
            if (sel) { sel.value = filterCat; }
            if (typeof loadRecords === 'function') loadRecords();
            return;
        }
        if (cat === 'notas') {
            showView('viewFormNotas', true, false);
            loadNotes();
            return;
        }
        if (cat === 'notificaciones') {
            const rid = parseInt($('#cdPatientName')?.value) || 0;
            if (rid && typeof _resOpenResidentNotif === 'function') _resOpenResidentNotif(rid);
            else showView('viewResidentes', true, false);
            return;
        }
        if (cat === 'ficha') {
            const fichaView = $('#viewFicha');
            if (fichaView) fichaView.dataset.mode = 'info';
            showView('viewFicha', true, false);
            const tab = $('.cd-ficha-tab[data-ficha-tab="info"]');
            if (tab && !tab.classList.contains('active')) tab.click();
            return;
        }
        const view = CAT_VIEWS[cat];
        if (view) {
            // ── Sueño: offer to complete pending record ──────────
            if (cat === 'sueno' && _suenoPendiente && !_editingRecord) {
                const pendDatos = _suenoPendiente.datos || {};
                const sinceTxt = (t('sleep_pending_since') || 'Dormido desde') + ' ' + (pendDatos.hora_inicio || '?');
                const wantComplete = await cdConfirm(
                    sinceTxt + '\n' + (t('sleep_pending_complete') || 'Completar registro de sueño') + '?',
                    { title: t('sleep_pending_badge') || 'Sueño pendiente', type: 'info', okText: t('sleep_pending_complete') || 'Completar', cancelText: t('sleep_pending_new') || 'Nuevo' }
                );
                if (wantComplete === null) return; // dismissed via overlay/Escape → stay on home
                if (wantComplete) {
                    // Pre-fill the form to complete the pending record
                    _editingRecord = { id: _suenoPendiente.id, categoria: 'sueno', datos: pendDatos, hora: _suenoPendiente.hora, fecha: _suenoPendiente.fecha };
                    showView(view, true, false);
                    const form = $('#formSueno');
                    if (form) {
                        // Set hora_inicio from pending
                        const horaIni = form.querySelector('[name="hora_inicio"]');
                        if (horaIni && pendDatos.hora_inicio) horaIni.value = pendDatos.hora_inicio;
                        // Set hora_evento from pending
                        const evtTime = form.querySelector('.cd-event-time');
                        if (evtTime && pendDatos.hora_inicio) evtTime.value = pendDatos.hora_inicio;
                        // Set hora_fin to now
                        const nowTime = nowInTz().time;
                        const horaFin = form.querySelector('[name="hora_fin"]');
                        if (horaFin) { horaFin.value = nowTime; horaFin.disabled = false; horaFin.style.opacity = ''; horaFin.style.display = ''; }
                        const wakeUnknown = $('#cdSleepWakeUnknown');
                        if (wakeUnknown) wakeUnknown.style.display = 'none';
                        // Sync slider
                        if (pendDatos.hora_inicio) {
                            const diffMin = window._sleepDiffMin ? window._sleepDiffMin(pendDatos.hora_inicio, nowTime) : 480;
                            let hrs = Math.round((diffMin / 60) * 2) / 2;
                            if (hrs > 16) hrs = 16;
                            const slider = $('#cdSleepHoursSlider');
                            const slVal = $('#cdSleepHoursVal');
                            if (slider) { slider.value = hrs; slider.disabled = false; }
                            if (slVal) { slVal.textContent = (hrs % 1 === 0 ? hrs + 'h' : hrs.toFixed(1) + 'h'); slVal.style.opacity = ''; }
                        }
                        // Enable calidad
                        const calSlider = form.querySelector('[name="calidad_pct"]');
                        if (calSlider) { calSlider.disabled = false; calSlider.closest('.cd-form-group').style.opacity = ''; }
                        // Uncheck pending checkbox
                        const pendCb = $('#cdSleepPendingCb');
                        if (pendCb) pendCb.checked = false;
                        const pendHint = $('#cdSleepPendingHint');
                        if (pendHint) pendHint.classList.remove('visible');
                        // Set observations
                        const obs = form.querySelector('[name="observaciones"]');
                        if (obs && pendDatos.observaciones) obs.value = pendDatos.observaciones;
                        // Add edit banner
                        const title = $(`#${view} .cd-form-title`);
                        if (title) {
                            title.dataset.origTitle = title.dataset.origTitle || title.textContent;
                            title.textContent = (t('sleep_pending_complete') || 'Completar registro de sueño');
                        }
                    }
                    if (window._updateSleepHint) window._updateSleepHint();
                    return;
                }
                // User chose "Nuevo" — fall through to normal new-record flow
            }

            showView(view, true, false);
            if (cat === 'medicacion') renderMedList();
            if (cat === 'signos_vitales') renderSignosAlert();
            // Set event time to now (timezone-aware)
            const evtInput = $('#' + view + ' .cd-event-time');
            if (evtInput) {
                // For sueño: default hora_inicio and hora_evento to current time
                if (cat === 'sueno') {
                    const nowRaw = nowInTz().time;
                    // Round to nearest 30 minutes
                    const [nh, nm] = nowRaw.split(':').map(Number);
                    const rounded = nm < 15 ? 0 : nm < 45 ? 30 : 60;
                    const rH = rounded === 60 ? (nh + 1) % 24 : nh;
                    const rM = rounded === 60 ? 0 : rounded;
                    const now = String(rH).padStart(2, '0') + ':' + String(rM).padStart(2, '0');
                    const horaIni = $('#' + view + ' [name="hora_inicio"]');
                    if (horaIni) horaIni.value = now;
                    evtInput.value = now;
                    // Recalculate hora_fin = now + slider hours
                    const slider = $('#cdSleepHoursSlider');
                    const slVal = $('#cdSleepHoursVal');
                    const horaFin = $('#' + view + ' [name="hora_fin"]');
                    if (slider && horaFin) {
                        const hrs = parseFloat(slider.value) || 8;
                        const [h, m] = now.split(':').map(Number);
                        const totalMin = (h * 60 + m) + Math.round(hrs * 60);
                        const endH = Math.floor(totalMin / 60) % 24;
                        const endM = totalMin % 60;
                        horaFin.value = String(endH).padStart(2, '0') + ':' + String(endM).padStart(2, '0');
                    }
                    // Update midnight-crossing hint
                    if (window._updateSleepHint) window._updateSleepHint();
                    // Reset pending checkbox for new records
                    const pendCb = $('#cdSleepPendingCb');
                    if (pendCb) { pendCb.checked = false; pendCb.dispatchEvent(new Event('change')); }
                } else {
                    evtInput.value = nowInTz().time;
                }
            }
            // Reset photo uploads when opening alimentación as NEW (not edit)
            if (cat === 'alimentacion' && !_editingRecord) {
                _photoAntes = null; _photoDespues = null;
                ['cdPhotoAntes','cdPhotoDespues'].forEach(pid => {
                    const wrap = $(`#${pid}`);
                    if (wrap) {
                        const target = pid === 'cdPhotoAntes' ? 'antes' : 'despues';
                        wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span><?= t('form_add_photo') ?></span><input type="file" accept="image/*" data-target="${target}">`;
                        bindPhotoBox(wrap);
                    }
                });
            }
        }
    });
});

$$('[data-back]').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm();
    _medFormDirty = false;
    _nmFormDirty = false;
    _careFormDirty = false;
    clearEditState();
    if (_currentView === 'viewNuevoResidente' && typeof _nrResetForm === 'function') _nrResetForm();
    cdGoPreviousView('viewDashboard');
    renderRxTracker(); // Reset tracker pills to saved state
}));
$$('[data-back-home]').forEach(b => b.addEventListener('click', async () => {
    if (_currentView === 'viewFormMedicacion' && !await confirmMedLeave()) return;
    if (_currentView === 'viewFormNotasMedico' && !await confirmNmLeave()) return;
    if (_careFormDirty && !await confirmCareLeave()) return;
    resetCareForm();
    _medFormDirty = false;
    _nmFormDirty = false;
    _careFormDirty = false;
    clearEditState();
    showView(<?= json_encode($initialView ?? 'viewDashboard') ?>, true, 'left');
    renderRxTracker();
}));

document.addEventListener('click', async e => {
    const btn = e.target.closest('[data-view-refresh]');
    if (!btn) return;
    e.preventDefault();
    const viewId = btn.dataset.viewRefresh || _currentView;
    btnLoading(btn, t('status_loading') || 'Actualizando');
    try {
        await refreshViewData(viewId);
        showToast(t('tl_refresh') || 'Actualizado', 'success');
    } catch (err) {
        showToast(err?.message || 'Error al actualizar', 'error');
    } finally {
        btnReset(btn);
    }
});
// Also push form views into history for proper back navigation
function showFormView(viewId) {
    showView(viewId, true, 'right');
}

