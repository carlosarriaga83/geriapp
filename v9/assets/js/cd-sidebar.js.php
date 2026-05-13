// cd-sidebar.js — Unified right sidebar, med detail, med add, photo upload, expediente linking
// Extracted from cuidados.php (lines 6113)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// UNIFIED RIGHT SIDEBAR
// ═══════════════════════════════════════════════
const CURRENT_USER_ID = <?= (int) $userId ?>;
const IS_ADMIN = <?= in_array($userRole, ['admin', 'superadmin']) ? 'true' : 'false' ?>;
let _editRecord = null;

const sOverlay = $('#cdSidebarOverlay');
const sPanel   = $('#cdSidebarPanel');
const sTitle   = $('#cdSidebarTitle');
const sBody    = $('#cdSidebarBody');
const sActions = $('#cdSidebarActions');

function cdSidebarMarkExitActions() {
    sActions?.querySelectorAll?.('button').forEach(btn => {
        const label = String(btn.textContent || '').trim();
        const isExit = /^(cerrar|cancelar)(\s|$)/i.test(label);
        btn.classList.toggle('cd-btn-sidebar-exit', isExit);
    });
}

function openSidebar(title, bodyHTML, actionsHTML, opts) {
    // 'wide' as actionsHTML is a shorthand flag
    let wide = !!opts?.wide;
    if (actionsHTML === 'wide') { wide = true; actionsHTML = ''; }
    sTitle.textContent = title;
    sBody.innerHTML = bodyHTML;
    sActions.dataset.busy = '';
    sActions.innerHTML = actionsHTML ? `${actionsHTML}<div class="cd-sidebar-action-status" data-sidebar-action-status aria-live="polite"></div>` : '';
    cdSidebarMarkExitActions();
    sOverlay.classList.toggle('cd-sb-wide', wide);
    sOverlay.classList.add('show');
    document.body.style.overflow = 'hidden';
    sPanel.focus();
}

function cdSidebarActionStatus(message = '', state = '') {
    const status = sActions?.querySelector?.('[data-sidebar-action-status]');
    if (!status) return;
    status.textContent = message || '';
    status.dataset.state = state || '';
    status.hidden = !message;
}

function cdSidebarSetActionBusy(btn, label = 'Procesando') {
    if (!btn || !sActions?.contains(btn)) return false;
    if (sActions.dataset.busy === '1') return false;
    sActions.dataset.busy = '1';
    sActions.querySelectorAll('button').forEach(action => {
        action.dataset.sidebarWasDisabled = action.disabled ? '1' : '0';
        action.disabled = true;
        action.setAttribute('aria-disabled', 'true');
    });
    btn.disabled = false;
    btn.setAttribute('aria-busy', 'true');
    cdSidebarActionStatus(label, 'busy');
    return true;
}

function cdSidebarClearActionBusy(btn, message = '') {
    if (!sActions) return;
    sActions.dataset.busy = '';
    sActions.querySelectorAll('button').forEach(action => {
        const wasDisabled = action.dataset.sidebarWasDisabled === '1';
        action.disabled = wasDisabled;
        if (wasDisabled) action.setAttribute('aria-disabled', 'true');
        else action.removeAttribute('aria-disabled');
        delete action.dataset.sidebarWasDisabled;
        action.removeAttribute('aria-busy');
    });
    cdSidebarActionStatus(message, message ? 'done' : '');
    if (message) setTimeout(() => cdSidebarActionStatus('', ''), 1600);
}

window.cdSidebarActionStatus = cdSidebarActionStatus;
window.cdSidebarSetActionBusy = cdSidebarSetActionBusy;
window.cdSidebarClearActionBusy = cdSidebarClearActionBusy;

sActions?.addEventListener('click', e => {
    const btn = e.target.closest('button');
    if (!btn) return;
    if (sActions.dataset.busy === '1' && btn.getAttribute('aria-busy') !== 'true') {
        e.preventDefault();
        e.stopPropagation();
        cdSidebarActionStatus('Espera a que termine la acción en curso', 'busy');
        return;
    }
    const label = String(btn.textContent || '').trim();
    if (label) {
        cdSidebarActionStatus(label.match(/cerrar|cancelar/i) ? label : `${label}...`, 'busy');
        clearTimeout(sActions._statusClickTimer);
        sActions._statusClickTimer = setTimeout(() => {
            if (sActions.dataset.busy !== '1') cdSidebarActionStatus('', '');
        }, 900);
    }
}, true);
function closeSidebar() {
    sOverlay.classList.remove('show', 'cd-sb-wide');
    document.body.style.overflow = '';
    sActions.dataset.busy = '';
    _editRecord = null;
}
$('#cdSidebarClose').addEventListener('click', closeSidebar);
// Close sidebar only on full click (mousedown+mouseup) outside the panel
(function() {
    let downOnOverlay = false;
    sOverlay.addEventListener('mousedown', e => { downOnOverlay = (e.target === sOverlay); });
    sOverlay.addEventListener('mouseup', e => {
        if (downOnOverlay && e.target === sOverlay) closeSidebar();
        downOnOverlay = false;
    });
})();
document.addEventListener('keydown', e => { if (e.key === 'Escape' && sOverlay.classList.contains('show')) closeSidebar(); });

// ── Image lightbox delegation: clicking any image in sidebar opens the viewer ──
sBody.addEventListener('click', e => {
    const link = e.target.closest('a.cd-sb-vital-photo-link, .cd-sidebar-img-wrap');
    const img = e.target.closest('img.cd-sb-vital-photo-img, img.cd-sidebar-img');
    if (img && typeof window._openImageLightbox === 'function') {
        e.preventDefault();
        e.stopPropagation();
        window._openImageLightbox(img.src, img.alt || '');
        return;
    }
    if (link) { e.preventDefault(); }
});

/* ── Swipe-to-dismiss (mobile bottom sheet) ── */
(function initSwipeDismiss() {
    const handle = sPanel.querySelector('.cd-sidebar-handle');
    if (!handle) return;
    let startY = 0, currentY = 0, dragging = false;
    const onStart = e => {
        dragging = true;
        startY = (e.touches ? e.touches[0] : e).clientY;
        sPanel.style.transition = 'none';
    };
    const onMove = e => {
        if (!dragging) return;
        currentY = (e.touches ? e.touches[0] : e).clientY;
        const dy = Math.max(0, currentY - startY);
        sPanel.style.transform = `translateY(${dy}px)`;
    };
    const onEnd = () => {
        if (!dragging) return;
        dragging = false;
        sPanel.style.transition = '';
        const dy = currentY - startY;
        if (dy > 80) { closeSidebar(); }
        else { sPanel.style.transform = ''; }
    };
    handle.addEventListener('touchstart', onStart, { passive: true });
    handle.addEventListener('touchmove', onMove, { passive: true });
    handle.addEventListener('touchend', onEnd);
    handle.addEventListener('mousedown', onStart);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
})();

// ── Edit via registration form (timeline click) ──────────────
function clearEditState() {
    _revertedEntries = [];
    if (!_editingRecord) return;
    const cat = _editingRecord.categoria;
    const viewId = CAT_VIEWS[cat];
    if (viewId) {
        const form = $(`#${viewId} form`);
        if (form) {
            form.reset();
            _careFormDirty = false;
            $$('.selected', form).forEach(b => b.classList.remove('selected'));
            $$('.cd-med-item.checked').forEach(b => b.classList.remove('checked'));
            // Reset vital card toggles to off
            $$('.cd-vital-card', form).forEach(c => {
                c.classList.add('cd-vital-off');
                const t = c.querySelector('.cd-vital-toggle');
                if (t) t.checked = false;
            });
            // Reset sleep pending checkbox state
            const pendCb = form.querySelector('#cdSleepPendingCb');
            if (pendCb) { pendCb.checked = false; pendCb.dispatchEvent(new Event('change')); }
            if (form.id === 'formSueno' && window._setSleepStartDayRel) {
                window._setSleepStartDayRel('today');
                if (window._updateSleepHint) window._updateSleepHint();
            }
        }
        // Reset photo uploads if alimentación
        if (cat === 'alimentacion') {
            _photoAntes = null;
            _photoDespues = null;
            ['cdPhotoAntes','cdPhotoDespues'].forEach(id => {
                const wrap = $(`#${id}`);
                if (wrap) {
                    const target = id === 'cdPhotoAntes' ? 'antes' : 'despues';
                    wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span><?= t('form_add_photo') ?></span><input type="file" accept="image/*" data-target="${target}">`;
                    bindPhotoBox(wrap);
                }
            });
        }
        // Remove edit banner and restore submit button
        const banner = $(`#${viewId} .cd-edit-banner`);
        if (banner) banner.remove();
        const actions = $(`#${viewId} .cd-form-actions`);
        if (actions) {
            const submit = actions.querySelector('.cd-btn-submit:not(.cd-btn-danger)');
            if (submit) { submit.textContent = t('btn_save_record'); actions.replaceWith(submit); }
        }
        // Restore title
        const title = $(`#${viewId} .cd-form-title`);
        if (title) title.textContent = title.dataset.origTitle || title.textContent;
    }
    _editingRecord = null;
}

async function openFormForEdit(r) {
    clearEditState();
    _editingRecord = r;
    const cat = r.categoria;
    const viewId = CAT_VIEWS[cat];
    if (!viewId) return;
    const d = r.datos || {};

    // Switch to the form view
    $$('.cd-nav-item').forEach(n => n.classList.remove('active'));
    $$('.cd-view').forEach(v => v.classList.remove('active'));
    $(`#${viewId}`).classList.add('active');

    const form = $(`#${viewId} form`);
    if (!form) return;

    // Reset form before prefilling
    form.reset();
    _careFormDirty = false;
    $$('.selected', form).forEach(b => b.classList.remove('selected'));

    // Set event time
    const evtInput = form.querySelector('.cd-event-time');
    if (evtInput && r.hora) evtInput.value = r.hora;

    // Prefill fields based on category
    prefillFormFields(form, cat, d);

    // Set observations
    const obsField = form.querySelector('[name="observaciones"]');
    if (obsField) obsField.value = r.observaciones || '';

    // Add edit banner
    const title = $(`#${viewId} .cd-form-title`);
    if (title) {
        title.dataset.origTitle = title.dataset.origTitle || title.textContent;
        title.textContent = t('btn_edit') + ' ' + (CAT_LABELS[cat] || cat);
    }
    const existingBanner = $(`#${viewId} .cd-edit-banner`);
    if (!existingBanner) {
        const banner = document.createElement('div');
        banner.className = 'cd-edit-banner';
        banner.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Editando registro de ${esc(r.usuario_nombre||'')} — ${esc(r.hora||'')}`;
        form.insertBefore(banner, form.firstChild);
    }

    // Swap submit button to update + delete actions
    const submitBtn = form.querySelector('.cd-btn-submit:not(.cd-btn-danger)');
    if (submitBtn && !form.querySelector('.cd-form-actions')) {
        submitBtn.textContent = t('btn_update_record');
        const wrapper = document.createElement('div');
        wrapper.className = 'cd-form-actions';
        submitBtn.parentNode.insertBefore(wrapper, submitBtn);
        wrapper.appendChild(submitBtn);
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'cd-btn-submit cd-btn-danger';
        delBtn.textContent = t('btn_delete');
        delBtn.addEventListener('click', async () => {
            if (!_editingRecord || !await cdConfirm(t('confirm_delete_record'), { title: t('confirm_delete_record_title'), type: 'danger', okText: t('btn_delete') })) return;
            try {
                await api(`${API_URL}?id=${_editingRecord.id}`, { method: 'DELETE' });
                showToast(t('toast_record_deleted'), 'success');
                clearEditState();
                showView('viewDashboard');
                loadDashboard();
                if (typeof loadRecords === 'function') loadRecords();
            } catch(e) {}
        });
        wrapper.appendChild(delBtn);
    }

    // Scroll to top
    const main = $('.cd-main');
    if (main) main.scrollTop = 0;

    // Pre-fill alert toggle if an alert exists for this record
    const alertToggle = form.querySelector('.cd-alert-doc-cb');
    if (alertToggle && r.id) {
        try {
            const alertas = await api(`${API_URL}?alertas_medico=1&residente_id=${r.residente_id}`);
            const myAlert = Array.isArray(alertas) ? alertas.find(a => parseInt(a.registro_id) === r.id) : null;
            if (myAlert) {
                alertToggle.checked = true;
                const msgField = form.querySelector('.cd-alert-doc-msg');
                if (msgField) {
                    msgField.style.display = '';
                    msgField.value = myAlert.mensaje || '';
                }
            }
        } catch {}
    }
}

function prefillFormFields(form, cat, d) {
    // Selects
    form.querySelectorAll('.cd-select').forEach(s => {
        if (s.name && d[s.name] != null) s.value = d[s.name];
    });

    // Time inputs (not event time)
    form.querySelectorAll('.cd-time-input:not(.cd-event-time)').forEach(s => {
        if (s.name && d[s.name] != null) s.value = d[s.name];
    });

    // Sliders
    form.querySelectorAll('.cd-slider').forEach(s => {
        if (s.name && d[s.name] != null) {
            s.value = d[s.name];
            s.dataset.touched = '1';
            // Don't dispatch input for sleep hours slider (handled separately after prefill)
            if (s.id !== 'cdSleepHoursSlider') s.dispatchEvent(new Event('input'));
        }
    });

    // Button groups, mood bars, icon groups, intake steps
    form.querySelectorAll('.cd-btn-group, .cd-mood-bar, .cd-icon-group, .cd-intake-steps').forEach(g => {
        const f = g.dataset.field;
        if (!f || d[f] == null) return;
        const val = String(d[f]);
        const btn = g.querySelector(`[data-val="${val}"]`);
        if (btn) btn.classList.add('selected');
    });

    // Category-specific
    if (cat === 'medicacion') {
        // Check prescribed meds that were in the record
        const selMeds = d.medicamentos_seleccionados || [];
        setTimeout(() => {
            $$('.cd-med-item').forEach(item => {
                const name = item.querySelector('.cd-med-name')?.textContent || '';
                if (selMeds.includes(name)) item.classList.add('checked');
            });
        }, 100);
        // Add extra meds as chips
        const extras = d.medicamentos_extra || [];
        if (extras.length) {
            const extraList = $('#cdMedExtraList');
            if (extraList) {
                extraList.innerHTML = extras.map(m => `<span class="cd-chip">${esc(m)}<button type="button" class="cd-chip-remove">&times;</button></span>`).join('');
                $$('.cd-chip-remove', extraList).forEach(btn => btn.addEventListener('click', () => btn.parentElement.remove()));
            }
        }
    }

    if (cat === 'sueno') {
        if (window._setSleepStartDayRel) {
            window._setSleepStartDayRel((d.inicio_dia_relativo === 'prev') ? 'prev' : 'today');
        }
        // Sync sleep hours slider from hora_inicio/hora_fin
        if (d.hora_inicio && d.hora_fin) {
            const [sh,sm] = d.hora_inicio.split(':').map(Number);
            const [eh,em] = d.hora_fin.split(':').map(Number);
            let mins = (eh * 60 + em) - (sh * 60 + sm);
            if (mins < 0) mins += 1440;
            let hrs = Math.round(mins / 30) / 2;
            const slider = $('#cdSleepHoursSlider');
            const slVal = $('#cdSleepHoursVal');
            if (slider) { slider.value = hrs; if (slVal) slVal.textContent = (hrs % 1 === 0 ? hrs + 'h' : hrs.toFixed(1) + 'h'); }
        }
    }

    if (cat === 'alimentacion') {
        // Pre-fill existing photos
        if (d.foto_antes) {
            _photoAntes = d.foto_antes;
            const wrap = $('#cdPhotoAntes');
            if (wrap) {
                wrap.innerHTML = `<img src="${BASE}/${esc(d.foto_antes)}"><button type="button" class="cd-photo-remove">&times;</button>
                    <input type="file" accept="image/*" data-target="antes">`;
                wrap.querySelector('.cd-photo-remove')?.addEventListener('click', ev => {
                    ev.stopPropagation();
                    _photoAntes = null;
                    wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span><?= t('form_add_photo') ?></span><input type="file" accept="image/*" data-target="antes">`;
                    bindPhotoBox(wrap);
                });
                bindPhotoBox(wrap);
            }
        }
        if (d.foto_despues) {
            _photoDespues = d.foto_despues;
            const wrap = $('#cdPhotoDespues');
            if (wrap) {
                wrap.innerHTML = `<img src="${BASE}/${esc(d.foto_despues)}"><button type="button" class="cd-photo-remove">&times;</button>
                    <input type="file" accept="image/*" data-target="despues">`;
                wrap.querySelector('.cd-photo-remove')?.addEventListener('click', ev => {
                    ev.stopPropagation();
                    _photoDespues = null;
                    wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span><?= t('form_add_photo') ?></span><input type="file" accept="image/*" data-target="despues">`;
                    bindPhotoBox(wrap);
                });
                bindPhotoBox(wrap);
            }
        }
    }

    // Pre-fill vital sign photo thumbnails when editing
    if (cat === 'signos_vitales' && d.fotos_vitales) {
        for (const [vk, url] of Object.entries(d.fotos_vitales)) {
            const inp = form.querySelector(`.cd-vital-photo-input[data-vital="${vk}"]`);
            if (!inp) continue;
            const preview = inp.closest('.cd-vital-photo-wrap')?.querySelector('.cd-vital-photo-preview');
            if (!preview) continue;
            const thumb = document.createElement('div');
            thumb.className = 'cd-vital-photo-thumb';
            thumb.innerHTML = `<img src="${BASE}/${esc(url)}" alt=""><button type="button" class="cd-vital-photo-remove">&times;</button>`;
            thumb.querySelector('.cd-vital-photo-remove').addEventListener('click', () => {
                thumb.remove();
                inp.value = '';
            });
            preview.appendChild(thumb);
        }
    }
}

// ── Detail sidebar (timeline click) ──────────
function buildDetailBody(r) {
    const isCuidado = r.source === 'cuidado';
    const d = r.datos || {};
    let html = '';

    if (isCuidado) {
        switch(r.categoria) {
            case 'signos_vitales': {
                // New format — check for ANY known vital sign (not just PA)
                const vitalKeys = ['pa_sistolica','frecuencia_cardiaca','frecuencia_respiratoria','temperatura','spo2','glucosa','peso'];
                if (vitalKeys.some(k => d[k] != null)) {
                    const _vr = {
                        temperatura:[36,37.5,35.5,38], frecuencia_respiratoria:[12,20,10,25],
                        frecuencia_cardiaca:[60,100,50,120], pa_sistolica:[90,140,80,160],
                        pa_diastolica:[60,90,50,100], spo2:[95,100,90,100], glucosa:[70,140,54,180]
                    };
                    const _vc = (k,v) => { if(v==null||v==='')return ''; v=parseFloat(v); if(isNaN(v))return ''; const r=_vr[k]; if(!r)return ''; if(v<r[2]||v>r[3])return ' class="cd-vital-danger"'; if(v<r[0]||v>r[1])return ' class="cd-vital-warn"'; return ''; };
                    const tsFull = (r.fecha ? fmtDate(r.fecha) : '') + (r.hora ? ' · ' + esc(r.hora) : '');
                    const tsCreado = r.creado_at ? (() => { try { const dt = new Date(r.creado_at.replace(' ','T')+(r.creado_at.includes('Z')||r.creado_at.includes('+')?'':'Z')); return !isNaN(dt) ? dt.toLocaleString('es-MX',{timeZone:APP_TZ,day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:false}) : r.creado_at; } catch(e){ return r.creado_at; } })() : '';
                    html = `<table class="cd-sb-vitals-table">
                        ${tsFull ? `<tr><th>Evento</th><td>${tsFull}</td></tr>` : ''}
                        ${tsCreado ? `<tr><th>Registro</th><td>${tsCreado}</td></tr>` : ''}
                        <tr><th>Temp</th><td${_vc('temperatura',d.temperatura)}>${d.temperatura ? d.temperatura+'°C' : '—'}</td></tr>
                        <tr><th>FR</th><td${_vc('frecuencia_respiratoria',d.frecuencia_respiratoria)}>${d.frecuencia_respiratoria ? d.frecuencia_respiratoria+' rpm' : '—'}</td></tr>
                        <tr><th>SpO2</th><td${_vc('spo2',d.spo2)}>${d.spo2 ? d.spo2+'%' : '—'}</td></tr>
                        <tr><th>PA</th><td${_vc('pa_sistolica',d.pa_sistolica)}>${esc(d.pa_sistolica)}/${esc(d.pa_diastolica)} mmHg</td></tr>
                        <tr><th>FC</th><td${_vc('frecuencia_cardiaca',d.frecuencia_cardiaca)}>${esc(d.frecuencia_cardiaca)} bpm</td></tr>
                        ${d.glucosa ? `<tr><th>Glucosa</th><td${_vc('glucosa',d.glucosa)}>${d.glucosa} mg/dL</td></tr>` : ''}
                    </table>`;
                    // Vital sign photos
                    if (d.fotos_vitales && Object.keys(d.fotos_vitales).length) {
                        const vitalLabels = {temperatura:'Temp',frecuencia_respiratoria:'FR',frecuencia_cardiaca:'FC',pa_sistolica:'PA',spo2:'SpO₂',glucosa:'Glucosa',peso:'Peso'};
                        html += `<div class="cd-sb-vital-photos"><div class="cd-sidebar-section-title" style="margin-top:12px">Evidencia fotográfica</div><div class="cd-sb-vital-photos-grid">`;
                        for (const [vk, url] of Object.entries(d.fotos_vitales)) {
                            html += `<div class="cd-sb-vital-photo-item">
                                <div class="cd-sb-vital-photo-label">${esc(vitalLabels[vk]||vk)}</div>
                                <a href="${BASE}/${esc(url)}" target="_blank" class="cd-sb-vital-photo-link">
                                    <img src="${BASE}/${esc(url)}" alt="${esc(vitalLabels[vk]||vk)}" class="cd-sb-vital-photo-img" loading="lazy">
                                </a>
                            </div>`;
                        }
                        html += `</div></div>`;
                    }
                }
                // Old format: rows array
                else if (d.rows && d.rows.length) {
                    html = `<table class="cd-sb-vitals-table"><thead><tr><th>Hora</th><th>PA</th><th>FC</th><th>FR</th><th>Temp</th><th>SpO2</th></tr></thead><tbody>`;
                    d.rows.forEach(row => {
                        html += `<tr><td>${esc(row.time||'')}</td><td>${esc(row.sys||'')}/${esc(row.dia||'')}</td><td>${esc(row.fc||'')}</td><td>${esc(row.fr||'')}</td><td>${row.temp?row.temp+'°C':'—'}</td><td>${row.spo2?row.spo2+'%':'—'}</td></tr>`;
                    });
                    html += '</tbody></table>';
                }
                break;
            }
            case 'medicacion': {
                // New format
                const meds = [...(d.medicamentos_seleccionados||[]),...(d.medicamentos_extra||[])];
                if (meds.length) {
                    html = meds.map(m => `<div style="padding:4px 0;font-size:0.8125rem">💊 ${esc(m)}</div>`).join('');
                }
                // Old format: {nombre, dosis, via, motivo}
                else if (d.nombre) {
                    html = `<div style="font-size:0.8125rem">
                        <strong>${esc(d.nombre)}</strong>
                        ${d.dosis ? `<br>Dosis: ${esc(d.dosis)}` : ''}
                        ${d.via ? `<br>Vía: ${esc(d.via)}` : ''}
                        ${d.motivo ? `<br>Indicación: ${esc(d.motivo)}` : ''}
                        ${d.adhoc ? '<br><span style="color:var(--cd-warning)">Ad hoc</span>' : ''}
                    </div>`;
                }
                // Inventory items administered
                if (d.inventario_admin?.length) {
                    html += `<div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--cd-border)">
                        <div style="font-size:0.6875rem;text-transform:uppercase;font-weight:600;color:var(--cd-text-muted);margin-bottom:4px">Del inventario</div>
                        ${d.inventario_admin.map(i => `<div style="font-size:0.8125rem">💊 ${esc(i.nombre)} (${i.qty} ${esc(i.unidad||'uds')})</div>`).join('')}
                    </div>`;
                }
                // Show skipped meds
                if (d.medicamentos_no_administrados?.length) {
                    html += `<div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--cd-border)">
                        <div style="font-size:0.6875rem;text-transform:uppercase;font-weight:600;color:var(--cd-danger);margin-bottom:4px">No administrados</div>
                        ${d.medicamentos_no_administrados.map(m => `<div style="font-size:0.8125rem;color:var(--cd-danger)">✕ ${esc(m.nombre)}${m.razon ? ` — <em>${esc(m.razon)}</em>` : ''}</div>`).join('')}
                    </div>`;
                }
                break;
            }
            case 'alimentacion': {
                html = `<div style="font-size:0.8125rem">
                    ${d.tipo_comida ? `<strong>${esc(d.tipo_comida)}</strong>` : ''}
                    ${d.ingesta_pct != null ? ` — ${d.ingesta_pct}% ingerido` : ''}
                </div>`;
                if (d.suplementos_admin?.length) {
                    html += `<div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--cd-border)">
                        <div style="font-size:0.6875rem;text-transform:uppercase;font-weight:600;color:var(--cd-text-muted);margin-bottom:4px">Suplementos administrados</div>
                        ${d.suplementos_admin.map(s => `<div style="font-size:0.8125rem">💊 ${esc(s.nombre)} (${s.qty} ${esc(s.unidad||'uds')})</div>`).join('')}
                    </div>`;
                }
                break;
            }
            default:
                html = `<p class="cd-sidebar-detail-text">${buildSummary(r)}</p>`;
        }
    } else {
        html = `<p class="cd-sidebar-detail-text">${esc(r.contenido||'')}</p>`;
    }

    if (!html) html = `<p class="cd-sidebar-detail-text">${buildSummary(r)}</p>`;
    return html;
}

function openDetailSidebar(r) {
    _editRecord = r;
    const isCuidado = r.source === 'cuidado';
    const isOwner = isCuidado && ((parseInt(r.usuario_id) === CURRENT_USER_ID) || IS_ADMIN);
    const isAuthor = isCuidado && (parseInt(r.usuario_id) === CURRENT_USER_ID);
    const catLabel = isCuidado ? (CAT_LABELS[r.categoria]||r.categoria) : (r.categoria||'Bitácora');

    let body = `<div class="cd-sidebar-meta">
        <div class="cd-sidebar-meta-row"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> ${fmtDate(r.fecha)} <input type="time" class="cd-time-input" id="cdSbHora" value="${esc(r.hora||'')}" style="padding:4px 8px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-bg);font-size:0.8125rem;width:auto;" ${isOwner?'':'disabled'}></div>
        <div class="cd-sidebar-meta-row"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ${esc(r.usuario_nombre||'—')}</div>
        <div class="cd-sidebar-meta-row"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg> ${esc(catLabel)}${r.source === 'bitacora' ? ' <span class="cd-tl-badge-bit" style="margin-left:6px">Bitácora</span>' : ''}</div>
    </div>`;

    const d = r.datos || {};
    body += `<div class="cd-sidebar-section"><div class="cd-sidebar-section-title">Detalle</div>
        ${buildDetailBody(r)}`;
    if (d.foto_antes || d.foto_despues) {
        const hasBoth = d.foto_antes && d.foto_despues;
        body += `<div class="cd-sidebar-img-row${hasBoth ? ' cd-sidebar-img-pair' : ''}">`;
        if (d.foto_antes) body += `<div class="cd-sidebar-img-wrap"><div class="cd-sidebar-img-label">Antes</div><div class="cd-skeleton" style="width:100%;height:${hasBoth?'120':'180'}px;border-radius:var(--cd-radius)"></div><img class="cd-sidebar-img" src="${BASE}/${esc(d.foto_antes)}" alt="Antes" onload="this.previousElementSibling.style.display='none';this.style.display=''" onerror="this.previousElementSibling.style.display='none'" style="display:none"></div>`;
        if (d.foto_despues) body += `<div class="cd-sidebar-img-wrap"><div class="cd-sidebar-img-label">Después</div><div class="cd-skeleton" style="width:100%;height:${hasBoth?'120':'180'}px;border-radius:var(--cd-radius)"></div><img class="cd-sidebar-img" src="${BASE}/${esc(d.foto_despues)}" alt="Después" onload="this.previousElementSibling.style.display='none';this.style.display=''" onerror="this.previousElementSibling.style.display='none'" style="display:none"></div>`;
        body += `</div>`;
    }
    body += `</div>`;

    // For bitacora events, extract motivo from JSON for observaciones
    let obsText = r.observaciones || '';
    if (!obsText && !isCuidado && r.contenido) {
        try { const bp = JSON.parse(r.contenido); if (bp?.motivo) obsText = bp.motivo; } catch(e) {}
    }

    body += `<div class="cd-sidebar-section"><div class="cd-sidebar-section-title">Observaciones</div>
        <div class="cd-ai-field-wrap">
            <textarea class="cd-textarea" id="cdSbObs" placeholder="Sin observaciones..." ${isOwner ? '' : 'disabled'}>${esc(obsText)}</textarea>
            ${isOwner ? '<button type="button" class="cd-ai-rewrite-btn" data-perm-id="sb_ai_rewrite_obs_btn" onclick="aiRewrite(\'cdSbObs\',\'observacion\')" title="Mejorar redacción con IA"><img src="assets/icons/gemini.png" class="cd-ai-icon" alt="AI"></button><button type="button" class="cd-ai-undo-btn" onclick="aiUndo(\'cdSbObs\')" title="Deshacer cambio IA" style="display:none"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></button>' : ''}
        </div></div>`;

    let actions = '';
    const _sbLock = (cond, title, msg) => cond ? '' : `class="cd-role-locked" data-cd-locked data-lock-title="${title}" data-lock-msg="${msg}"`;
    if (isOwner) {
        const canEditForm = CAT_VIEWS[r.categoria] ? true : false;
        actions = (canEditForm ? `<button class="cd-btn-submit cd-btn-outline cd-btn-edit-form" id="cdSbEditForm" data-perm-id="sb_edit_form_btn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> ${t('btn_edit_cat', {':cat': ''})}</button>` : '') +
            `<div class="cd-sidebar-row">
                <button class="cd-btn-submit cd-btn-danger" id="cdSbDelete" data-perm-id="sb_delete_record_btn">${t('btn_delete')}</button>
                <button class="cd-btn-submit" id="cdSbSave" data-perm-id="sb_save_record_btn"><?= t('btn_save_changes') ?></button>
            </div>` +
            `<button class="cd-btn-close-sidebar" id="cdSbClose">${t('btn_close')}</button>`;
    } else {
        const canEditForm = isCuidado && CAT_VIEWS[r.categoria] ? true : false;
        const _lockMsg = isAuthor ? '' : (IS_ADMIN ? '' : 'Solo el autor del registro puede editarlo.');
        actions = (canEditForm ? `<button ${_sbLock(false, 'Solo el autor puede editar', 'Solo el autor del registro puede editar el formulario de cuidados.')} class="cd-btn-submit cd-btn-outline cd-btn-edit-form cd-role-locked" id="cdSbEditForm" data-perm-id="sb_edit_form_btn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> ${t('btn_edit_cat', {':cat': ''})}</button>` : '') +
            `<div class="cd-sidebar-row">
                <button class="cd-btn-submit cd-btn-danger cd-role-locked" id="cdSbDelete" data-perm-id="sb_delete_record_btn" data-cd-locked data-lock-title="Solo el autor puede eliminar" data-lock-msg="Solo el autor del registro o un administrador puede eliminarlo.">${t('btn_delete')}</button>
                <button class="cd-btn-submit cd-role-locked" id="cdSbSave" data-perm-id="sb_save_record_btn" data-cd-locked data-lock-title="Solo el autor puede editar" data-lock-msg="Solo el autor del registro puede modificar la hora y las observaciones."><?= t('btn_save_changes') ?></button>
            </div>` +
            `<button class="cd-btn-close-sidebar" id="cdSbClose">${t('btn_close')}</button>`;
    }
    openSidebar(catLabel, body, actions);

    // Auto-resize cdSbObs to show all text without scroll
    const sbObs = $('#cdSbObs');
    if (sbObs) {
        sbObs.style.resize = 'none';
        sbObs.style.overflow = 'hidden';
        const autoSize = () => { sbObs.style.height = 'auto'; sbObs.style.height = sbObs.scrollHeight + 'px'; };
        sbObs.addEventListener('input', autoSize);
        // Delay to allow sidebar open animation to finish
        requestAnimationFrame(() => { requestAnimationFrame(autoSize); });
    }

    $('#cdSbEditForm')?.addEventListener('click', () => {
        const rec = _editRecord;
        closeSidebar();
        openFormForEdit(rec);
    });

    $('#cdSbSave')?.addEventListener('click', async () => {
        if (!_editRecord) return;
        const btn = $('#cdSbSave');
        btnLoading(btn, t('status_saving'));
        sBody.style.opacity = '.45';
        sBody.style.pointerEvents = 'none';
        try {
            const updHora = $('#cdSbHora')?.value || _editRecord.hora;
            await api(API_URL, {
                method: 'PUT', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ id: _editRecord.id, datos: _editRecord.datos, observaciones: $('#cdSbObs').value.trim(), hora: updHora })
            });
            showToast(t('toast_record_updated'), 'success');
            closeSidebar();
            loadDashboard();
            if (typeof loadRecords === 'function') loadRecords();
        } catch(e) {
            sBody.style.opacity = '';
            sBody.style.pointerEvents = '';
            btnReset(btn);
        }
    });
    $('#cdSbDelete')?.addEventListener('click', async () => {
        if (!_editRecord || !await cdConfirm(t('confirm_delete_record'), { title: t('confirm_delete_record_title'), type: 'danger', okText: t('btn_delete') })) return;
        const btn = $('#cdSbDelete');
        btnLoading(btn, t('status_deleting'));
        sBody.style.opacity = '.45';
        sBody.style.pointerEvents = 'none';
        try {
            await api(`${API_URL}?id=${_editRecord.id}`, { method: 'DELETE' });
            showToast(t('toast_record_deleted'), 'success');
            closeSidebar();
            loadDashboard();
            if (typeof loadRecords === 'function') loadRecords();
        } catch(e) {
            sBody.style.opacity = '';
            sBody.style.pointerEvents = '';
            btnReset(btn);
        }
    });
    $('#cdSbClose')?.addEventListener('click', closeSidebar);
}

// ── Add medication sidebar (enhanced) ────────────────────────
function openAddMedSidebar(editRx = null) {
    const isEdit = !!editRx;
    const doctorName = isEdit ? (editRx.medico_nombre || '') : (_resData?.medico_nombre || '');
    const invOpts = _invItems.map(it => {
        const s = parseInt(it.stock_actual) || 0;
        return `<option value="${esc(it.nombre)}" data-stock="${s}" data-unit="${esc(it.unidad||'uds')}">${esc(it.nombre)} — Stock: ${s} ${esc(it.unidad||'uds')}</option>`;
    }).join('');
    const body = `
        <div class="cd-sidebar-section">
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_med_name')} <span style="color:var(--cd-danger)">*</span></label>
                <div class="cd-sb-med-search-wrap">
                    <input type="text" class="cd-input" id="cdSbMedSearch" placeholder="${t('rx_med_name')}" autocomplete="off">
                    <div class="cd-sb-med-dropdown" id="cdSbMedDropdown" style="display:none"></div>
                    <input type="hidden" id="cdSbMedNombre">
                </div>
                <div id="cdSbMedStockInfo" class="cd-sb-med-stock-info" style="display:none"></div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_total_dose')}<span style="color:var(--cd-danger)">*</span></label>
                <input type="text" class="cd-input" id="cdSbMedDosis" placeholder="${t('rx_dose_placeholder')}">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_admin_route')} <span style="color:var(--cd-danger)">*</span></label>
                <div class="cd-quick-chips" id="cdSbMedViaChips">
                    <button type="button" class="cd-quick-chip" data-val="Oral">Oral</button>
                    <button type="button" class="cd-quick-chip" data-val="Sublingual">Sublingual</button>
                    <button type="button" class="cd-quick-chip" data-val="Tópica">Tópica</button>
                    <button type="button" class="cd-quick-chip" data-val="Intravenosa">IV</button>
                    <button type="button" class="cd-quick-chip" data-val="Intramuscular">IM</button>
                    <button type="button" class="cd-quick-chip" data-val="Subcutánea">SC</button>
                    <button type="button" class="cd-quick-chip" data-val="Inhalatoria">Inhalatoria</button>
                    <button type="button" class="cd-quick-chip" data-val="Rectal">Rectal</button>
                    <button type="button" class="cd-quick-chip" data-val="Oftálmica">Oftálmica</button>
                    <button type="button" class="cd-quick-chip" data-val="Ótica">Ótica</button>
                </div>
                <input type="hidden" id="cdSbMedVia">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_frequency')}</label>
                <div class="cd-quick-chips" id="cdSbMedFrecChips">
                    <button type="button" class="cd-quick-chip" data-val="Cada 4 horas">c/4h</button>
                    <button type="button" class="cd-quick-chip" data-val="Cada 6 horas">c/6h</button>
                    <button type="button" class="cd-quick-chip" data-val="Cada 8 horas">c/8h</button>
                    <button type="button" class="cd-quick-chip" data-val="Cada 12 horas">c/12h</button>
                    <button type="button" class="cd-quick-chip" data-val="Cada 24 horas">c/24h</button>
                    <button type="button" class="cd-quick-chip" data-val="1 vez al día">1x/día</button>
                    <button type="button" class="cd-quick-chip" data-val="2 veces al día">2x/día</button>
                    <button type="button" class="cd-quick-chip" data-val="3 veces al día">3x/día</button>
                    <button type="button" class="cd-quick-chip" data-val="SOS / PRN">SOS</button>
                </div>
                <input type="hidden" id="cdSbMedFrec">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_schedules')} <span style="color:var(--cd-danger)">*</span></label>
                <div class="cd-quick-chips cd-hor-chips-24" id="cdSbMedHorChips">
                    ${Array.from({length:24},(_,i)=>{const hh=String(i).padStart(2,'0')+':00';return `<button type=\"button\" class=\"cd-quick-chip cd-hor-chip\" data-val=\"${hh}\">${hh}</button>`;}).join('')}
                </div>
                <input type="hidden" id="cdSbMedHorarios">
            </div>
            <div style="display:flex;gap:8px">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('rx_start_date')}</label>
                    <div class="cd-sb-date-control">
                        <input type="text" class="cd-input cd-app-date-input" id="cdSbMedInicio" value="${esc(fmtDate(nowInTz().date))}" data-iso="${nowInTz().date}" placeholder="${appDatePlaceholder()}" inputmode="numeric">
                        <button type="button" class="cd-sb-date-picker" id="cdSbMedInicioPicker" title="Seleccionar fecha" aria-label="Seleccionar fecha de inicio">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <input type="date" class="cd-sb-date-native" id="cdSbMedInicioNative" data-med-date-native="cdSbMedInicio" data-no-fmt-hint="1" value="${nowInTz().date}">
                        </button>
                    </div>
                </div>
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('rx_end_date')}</label>
                    <div class="cd-sb-date-control">
                        <input type="text" class="cd-input cd-app-date-input" id="cdSbMedFin" placeholder="${appDatePlaceholder()}" inputmode="numeric">
                        <button type="button" class="cd-sb-date-picker" id="cdSbMedFinPicker" title="Seleccionar fecha" aria-label="Seleccionar fecha de fin">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <input type="date" class="cd-sb-date-native" id="cdSbMedFinNative" data-med-date-native="cdSbMedFin" data-no-fmt-hint="1">
                        </button>
                    </div>
                    <label class="cd-checkbox-inline" style="display:flex;align-items:center;gap:6px;font-size:0.75rem;color:var(--cd-text-muted);cursor:pointer;margin-top:6px">
                        <input type="checkbox" id="cdSbMedPermanent"> ${t('rx_permanent_no_end')}
                    </label>
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_indication_notes')}</label>
                <textarea class="cd-textarea" id="cdSbMedIndicacion" placeholder="${t('rx_indication_placeholder')}"></textarea>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_prescribing_doctor')}</label>
                <input type="text" class="cd-input" id="cdSbMedMedico" placeholder="${t('rx_doctor_placeholder')}" value="${esc(doctorName)}">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('rx_photo')}</label>
                <div class="cd-rx-img-upload" id="cdSbMedImgWrap">
                    <input type="file" id="cdSbMedImgFile" accept="image/jpeg,image/png,image/webp" style="display:none">
                    <div class="cd-rx-img-preview" id="cdSbMedImgPreview" style="display:none">
                        <img id="cdSbMedImgThumb" src="" alt="Preview">
                        <button type="button" class="cd-rx-img-remove" id="cdSbMedImgRemove" title="${t('rx_remove_image')}">&times;</button>
                    </div>
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdSbMedImgBtn" style="font-size:0.8125rem;padding:6px 12px">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        ${t('rx_attach_photo')}
                    </button>
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_link_rx')}</label>
                <div class="cd-exp-link-row">
                    <select class="cd-input cd-select-native cd-exp-link-select" id="cdSbMedExpDoc">
                        <option value="">${t('exp_no_link')}</option>
                    </select>
                    <a href="javascript:void(0)" id="cdSbMedExpView" class="cd-exp-link-view" style="display:none">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        ${t('exp_view_doc') || 'Ver documento'}
                    </a>
                </div>
                <div id="cdSbMedExpStatus" style="font-size:.7rem;color:var(--cd-text-muted);margin-top:4px;display:none"></div>
            </div>
        </div>`;

    const isActive = isEdit ? parseInt(editRx.activo) !== 0 : true;
        const statusBtn = isEdit
            ? (isActive
                ? `<button class="cd-btn-submit cd-btn-danger" id="cdSbMedDelete">${t('btn_delete')}</button>`
                : `<button class="cd-btn-submit" id="cdSbMedArchive" style="background:#22c55e;color:#fff;border-color:#22c55e">${t('btn_reactivate')}</button>`)
            : '';
    const actions = `<button class="cd-btn-submit" id="cdSbMedSave">${isEdit ? t('btn_update_rx') : t('btn_save_rx')}</button>
            ${statusBtn}
        <button class="cd-btn-submit cd-btn-secondary" id="cdSbMedCancel"><?= t('btn_cancel') ?></button>`;

    openSidebar(isEdit ? t('sidebar_edit_med') : t('sidebar_add_med'), body, actions);

    // Pre-fill when editing
    if (isEdit) {
        $('#cdSbMedSearch').value = editRx.nombre || '';
        $('#cdSbMedNombre').value = editRx.nombre || '';
        $('#cdSbMedDosis').value = editRx.dosis || '';
        $('#cdSbMedIndicacion').value = editRx.indicacion || '';
        $('#cdSbMedMedico').value = editRx.medico_nombre || '';
        if (editRx.inicio) setAppDateInputValue($('#cdSbMedInicio'), editRx.inicio);
        if (editRx.fin) setAppDateInputValue($('#cdSbMedFin'), editRx.fin);
        if (!editRx.fin) { $('#cdSbMedPermanent').checked = true; $('#cdSbMedFin').disabled = true; }
        // Pre-select via chip
        if (editRx.via) {
            $('#cdSbMedVia').value = editRx.via;
            $$('.cd-quick-chip', $('#cdSbMedViaChips')).forEach(c => {
                c.classList.toggle('selected', c.dataset.val === editRx.via);
            });
        }
        // Pre-select frecuencia chip
        if (editRx.frecuencia) {
            $('#cdSbMedFrec').value = editRx.frecuencia;
            $$('.cd-quick-chip', $('#cdSbMedFrecChips')).forEach(c => {
                c.classList.toggle('selected', c.dataset.val === editRx.frecuencia);
            });
        }
        // Pre-select hour chips
        const rxHorarios = editRx.horarios ? (typeof editRx.horarios === 'string' ? JSON.parse(editRx.horarios) : editRx.horarios) : [];
        if (rxHorarios.length) {
            $('#cdSbMedHorarios').value = rxHorarios.join(', ');
            $$('.cd-hor-chip', $('#cdSbMedHorChips')).forEach(c => {
                c.classList.toggle('selected', rxHorarios.includes(c.dataset.val));
            });
        }
        // Pre-fill image preview
        if (editRx.imagen) {
            const prev = $('#cdSbMedImgPreview');
            const thumb = $('#cdSbMedImgThumb');
            thumb.src = BASE + '/' + editRx.imagen;
            prev.style.display = 'block';
            $('#cdSbMedImgBtn').textContent = t('btn_change_photo');
        }
    }

    // ── Photo upload UI ───────────────────────────────────────────────────
    let _rxImgFile = null;
    let _rxImgDeleted = false;
    $('#cdSbMedImgBtn').addEventListener('click', () => $('#cdSbMedImgFile').click());
    $('#cdSbMedImgFile').addEventListener('change', async e => {
        let file = e.target.files[0];
        if (!file) return;
        file = await compressImage(file);
        if (file.size > 5 * 1024 * 1024) { showToast(t('error_image_max_5mb'), 'error'); return; }
        _rxImgFile = file;
        _rxImgDeleted = false;
        const reader = new FileReader();
        reader.onload = ev => {
            $('#cdSbMedImgThumb').src = ev.target.result;
            $('#cdSbMedImgPreview').style.display = 'block';
            $('#cdSbMedImgBtn').textContent = t('btn_change_photo');
        };
        reader.readAsDataURL(file);
    });
    $('#cdSbMedImgRemove').addEventListener('click', () => {
        _rxImgFile = null;
        _rxImgDeleted = true;
        $('#cdSbMedImgFile').value = '';
        $('#cdSbMedImgThumb').src = '';
        $('#cdSbMedImgPreview').style.display = 'none';
        $('#cdSbMedImgBtn').innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Adjuntar foto';
    });

    // ── Expediente linking in prescription form ───────────────────────
    (async () => {
        if (!_residenteId) return;
        try {
            const expDocs = await api(BASE + '/api/expediente.php?action=brief&residente_id=' + _residenteId);
            const sel = $('#cdSbMedExpDoc');
            if (sel && expDocs && expDocs.length) {
                const TIPO_SHORT = {receta:'Rx',laboratorio:'Lab',imagen:'Img',interpretacion:'Int',hospitalizacion:'Hosp',legal:'Legal',nota_enfermeria:'Enf',nota_medico:'N.Méd'};
                expDocs.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = `[${TIPO_SHORT[d.tipo]||d.tipo}] ${d.titulo}${d.fecha_documento ? ' - '+fmtDate(d.fecha_documento) : ''}`;
                    sel.appendChild(opt);
                });
                // Pre-select if editing
                if (isEdit && editRx.expediente_id) {
                    sel.value = editRx.expediente_id;
                    const viewBtn = $('#cdSbMedExpView');
                    if (viewBtn) viewBtn.style.display = 'block';
                    const status = $('#cdSbMedExpStatus');
                    if (status) { status.textContent = t('exp_linked_doc'); status.style.display = 'block'; }
                }
            }
        } catch(e) { /* expediente not available yet — ignore */ }
    })();
    $('#cdSbMedExpDoc')?.addEventListener('change', function() {
        const viewBtn = $('#cdSbMedExpView');
        if (viewBtn) viewBtn.style.display = this.value ? 'block' : 'none';
    });
    $('#cdSbMedExpView')?.addEventListener('click', () => {
        const docId = $('#cdSbMedExpDoc')?.value;
        if (docId && typeof _viewExpedienteDoc === 'function') _viewExpedienteDoc(parseInt(docId));
    });

    // Medication inventory search-dropdown
    const medSearch = $('#cdSbMedSearch');
    const medDropdown = $('#cdSbMedDropdown');
    const medHidden = $('#cdSbMedNombre');
    const medStockInfo = $('#cdSbMedStockInfo');
    function renderMedDropdown(q) {
        const query = q.toLowerCase().trim();
        // Search across the full active inventory (any tipo) so users can find
        // any item — meds, insumos, etc. Match across nombre + presentacion +
        // categoria + tipo + marca/principio_activo when those fields exist.
        const source = (Array.isArray(_invAllItems) && _invAllItems.length) ? _invAllItems : _invItems;
        const matches = source.filter(it => {
            if (!query) return true;
            const hay = [
                it.nombre, it.presentacion, it.categoria, it.tipo,
                it.marca, it.principio_activo, it.codigo, it.unidad
            ].filter(Boolean).join(' ').toLowerCase();
            return hay.includes(query);
        }).slice(0, 15);
        if (!matches.length) {
            medDropdown.innerHTML = `<div class="cd-sb-med-dd-empty">${t('med_inv_no_matches')} ${t('med_inv_create_hint')}</div>`;
            medDropdown.style.display = 'block';
            return;
        }
        medDropdown.innerHTML = matches.map(it => {
            const s = parseInt(it.stock_actual) || 0;
            const cls = s === 0 ? 'cd-med-stock-out' : s <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
            const venc = it.vencimiento || '';
            let warnHtml = '';
            if (venc) {
                const daysLeft = Math.ceil((new Date(venc) - new Date()) / 86400000);
                if (daysLeft <= 30 && daysLeft > 0) warnHtml = `<span class="cd-sb-med-dd-warn"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Vence: ${esc(fmtDate(venc))}</span>`;
                else if (daysLeft <= 0) warnHtml = `<span class="cd-sb-med-dd-warn cd-sb-med-dd-expired"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Vencido</span>`;
            }
            return `<button type="button" class="cd-sb-med-dd-item" data-name="${esc(it.nombre)}" data-stock="${s}" data-unit="${esc(it.unidad||'uds')}" data-venc="${esc(venc)}">
                <span class="cd-sb-med-dd-name">${esc(it.nombre)}${it.tipo && it.tipo !== 'medicamento' ? ` <span class="cd-sb-med-dd-tipo">${esc(it.tipo)}</span>` : ''}</span>
                <span class="cd-sb-med-dd-meta"><span class="${cls}">Stock: ${s} ${esc(it.unidad||'uds')}</span>${warnHtml}</span>
            </button>`;
        }).join('');
        medDropdown.style.display = 'block';
        $$('.cd-sb-med-dd-item:not(.cd-sb-med-dd-add)', medDropdown).forEach(btn => {
            btn.addEventListener('click', () => {
                medHidden.value = btn.dataset.name;
                medSearch.value = btn.dataset.name;
                medDropdown.style.display = 'none';
                const s = parseInt(btn.dataset.stock) || 0;
                const cls = s === 0 ? 'cd-med-stock-out' : s <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
                let infoHtml = `<span class="${cls}">Stock: ${s} ${esc(btn.dataset.unit)}</span>`;
                // Check for existing active Rx with same name
                const existingRx = (RX_BY_RES[_residenteId] || []).find(rx =>
                    parseInt(rx.activo) !== 0 && rx.nombre?.toLowerCase() === btn.dataset.name.toLowerCase()
                    && (!isEdit || rx.id != editRx?.id)
                );
                if (existingRx) {
                    const rxDosis = existingRx.dosis ? ` — ${existingRx.dosis}` : '';
                    const rxFrec = existingRx.frecuencia ? `, ${existingRx.frecuencia}` : '';
                    infoHtml += `<div class="cd-sb-med-dup-warn"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Este medicamento ya tiene un esquema activo${rxDosis}${rxFrec}</div>`;
                }
                medStockInfo.innerHTML = infoHtml;
                medStockInfo.style.display = 'block';
            });
        });
    }
    medSearch?.addEventListener('input', () => {
        medHidden.value = medSearch.value.trim();
        if (medStockInfo) medStockInfo.style.display = 'none';
        renderMedDropdown(medSearch.value);
    });
    medSearch?.addEventListener('focus', () => renderMedDropdown(medSearch.value));
    document.addEventListener('click', e => {
        if (!e.target.closest('.cd-sb-med-search-wrap')) medDropdown.style.display = 'none';
    }, { once: false });

    // Quick-chip single-select for vía and frecuencia
    ['cdSbMedViaChips','cdSbMedFrecChips'].forEach(containerId => {
        const container = $('#'+containerId);
        container?.addEventListener('click', e => {
            const chip = e.target.closest('.cd-quick-chip');
            if (!chip) return;
            const wasSelected = chip.classList.contains('selected');
            $$('.cd-quick-chip', container).forEach(c => c.classList.remove('selected'));
            const hiddenId = containerId === 'cdSbMedViaChips' ? 'cdSbMedVia' : 'cdSbMedFrec';
            if (wasSelected) {
                // Deselect: clear value
                $('#'+hiddenId).value = '';
                // Also clear hours if frequency was deselected
                if (containerId === 'cdSbMedFrecChips') {
                    $$('.cd-hor-chip', $('#cdSbMedHorChips')).forEach(c => c.classList.remove('selected'));
                    $('#cdSbMedHorarios').value = '';
                }
            } else {
                chip.classList.add('selected');
                $('#'+hiddenId).value = chip.dataset.val;
                // Auto-select hours when frequency is picked
                if (containerId === 'cdSbMedFrecChips') {
                    const FREQ_HOURS = {
                        'Cada 4 horas':    ['06:00','12:00','18:00','22:00'],
                        'Cada 6 horas':    ['06:00','12:00','18:00'],
                        'Cada 8 horas':    ['08:00','14:00','22:00'],
                        'Cada 12 horas':   ['08:00','20:00'],
                        'Cada 24 horas':   ['08:00'],
                        '1 vez al día':    ['08:00'],
                        '2 veces al día':  ['08:00','20:00'],
                        '3 veces al día':  ['08:00','14:00','20:00'],
                    };
                    const autoHours = FREQ_HOURS[chip.dataset.val] || [];
                    const horChips = $$('.cd-hor-chip', $('#cdSbMedHorChips'));
                    horChips.forEach(c => c.classList.remove('selected'));
                    autoHours.forEach(h => {
                        const match = horChips.find(c => c.dataset.val === h);
                        if (match) match.classList.add('selected');
                    });
                    $('#cdSbMedHorarios').value = autoHours.join(', ');
                }
            }
        });
    });
    // Horarios multi-select
    $('#cdSbMedHorChips')?.addEventListener('click', e => {
        const chip = e.target.closest('.cd-hor-chip');
        if (!chip) return;
        chip.classList.toggle('selected');
        const sel = $$('.cd-hor-chip.selected', $('#cdSbMedHorChips')).map(c => c.dataset.val);
        $('#cdSbMedHorarios').value = sel.join(', ');
    });
    // Permanent checkbox disables fin date
    $('#cdSbMedPermanent')?.addEventListener('change', e => {
        const fin = $('#cdSbMedFin');
        if (fin) {
            fin.disabled = e.target.checked;
            if (e.target.checked) setAppDateInputValue(fin, '');
            syncMedDateNative(fin);
            setMedDatePickerDisabled('cdSbMedFin', e.target.checked);
        }
    });

    function syncMedDateNative(input) {
        if (!input) return;
        const native = document.querySelector(`[data-med-date-native="${input.id}"]`);
        if (!native) return;
        const iso = parseAppDateInput(input.value || input.dataset.iso || '') || '';
        native.value = iso;
    }

    function setMedDatePickerDisabled(inputId, disabled) {
        const native = document.querySelector(`[data-med-date-native="${inputId}"]`);
        const btn = native?.closest('.cd-sb-date-picker');
        if (native) native.disabled = !!disabled;
        if (btn) btn.classList.toggle('disabled', !!disabled);
    }

    function initMedDatePicker(inputId) {
        const input = $('#'+inputId);
        const native = document.querySelector(`[data-med-date-native="${inputId}"]`);
        initAppDateTextInput(input);
        syncMedDateNative(input);
        setMedDatePickerDisabled(inputId, input?.disabled);
        if (!input || !native || native.dataset.medDateInit === '1') return;
        native.dataset.medDateInit = '1';
        native.addEventListener('pointerdown', () => syncMedDateNative(input));
        native.addEventListener('change', () => {
            if (native.value) setAppDateInputValue(input, native.value);
        });
        input.addEventListener('blur', () => syncMedDateNative(input));
        input.addEventListener('input', () => { if (!input.value) native.value = ''; });
    }

    ['cdSbMedInicio','cdSbMedFin'].forEach(initMedDatePicker);

    $('#cdSbMedSave').addEventListener('click', async () => {
        const nombre = ($('#cdSbMedSearch').value || $('#cdSbMedNombre').value || '').trim();
        if (!nombre) { showToast(t('error_select_med'), 'error'); return; }
        const dosis = $('#cdSbMedDosis').value.trim();
        if (!dosis) { showToast(t('error_dose_required'), 'error'); return; }
        const via = $('#cdSbMedVia').value;
        if (!via) { showToast(t('error_select_route'), 'error'); return; }
        const horariosRaw = $('#cdSbMedHorarios').value.trim();
        const horarios = horariosRaw ? horariosRaw.split(',').map(h => h.trim()).filter(Boolean) : [];
        if (!horarios.length) { showToast(t('error_select_schedule'), 'error'); return; }
        const hhmmRe = /^([01]\d|2[0-3]):[0-5]\d$/;
        const badH = horarios.find(h => !hhmmRe.test(h));
        if (badH) { showToast(t('error_invalid_schedule', {time: badH}), 'error'); return; }
        const isPermanent = $('#cdSbMedPermanent')?.checked;
        const inicio = appDateInputIso($('#cdSbMedInicio'), { required:true, message:t('rx_start_date') + ': ' + appDatePlaceholder() });
        if (!inicio) return;
        const fin = isPermanent ? '' : appDateInputIso($('#cdSbMedFin'), { required:true, message:t('rx_end_date') + ': ' + appDatePlaceholder() });
        if (!isPermanent && !fin) { showToast(t('error_select_end_date'), 'error'); return; }
        const invItem = await ensureMedicationInventoryForAdministration(nombre, 1, { notas: 'Creado desde prescripción recurrente' });
        if (!invItem) return;
        const saveBtn = $('#cdSbMedSave');
        btnLoading(saveBtn, t('status_saving'));
        try {
            let savedId = null;
            if (isEdit) {
                await api(BASE + '/api/prescripciones.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({
                        id: editRx.id,
                        nombre, dosis: $('#cdSbMedDosis').value.trim(), via: $('#cdSbMedVia').value, frecuencia: $('#cdSbMedFrec').value, horarios, indicacion: $('#cdSbMedIndicacion').value.trim(), medico_nombre: $('#cdSbMedMedico').value.trim(), inicio: inicio || null, fin: fin || null
                    })
                });
                savedId = editRx.id;
            } else {
                const res = await api(BASE + '/api/prescripciones.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({
                        action: 'batch_create',
                        residente_id: _residenteId,
                        medico_nombre: $('#cdSbMedMedico').value.trim(),
                        medicamentos: [{ nombre, dosis: $('#cdSbMedDosis').value.trim(), via: $('#cdSbMedVia').value, frecuencia: $('#cdSbMedFrec').value, horarios, indicacion: $('#cdSbMedIndicacion').value.trim(), inicio: inicio || null, fin: fin || null }]
                    })
                });
                savedId = res.ids?.[0] || null;
            }
            // Upload or delete prescription image — non-fatal: la prescripción ya
            // quedó guardada, así que un error de imagen no debe marcar todo el
            // flujo como fallido. api() ya muestra el toast con el mensaje real
            // del servidor (p.ej. "La imagen supera 5 MB", "Tipo no permitido").
            let _imgFailed = false;
            if (savedId && _rxImgFile) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'upload_imagen');
                    fd.append('prescripcion_id', savedId);
                    fd.append('imagen', _rxImgFile);
                    await api(BASE + '/api/prescripciones.php', { method: 'POST', body: fd });
                } catch(e) { _imgFailed = true; }
            } else if (savedId && _rxImgDeleted && isEdit && editRx.imagen) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'delete_imagen');
                    fd.append('prescripcion_id', savedId);
                    await api(BASE + '/api/prescripciones.php', { method: 'POST', body: fd });
                } catch(e) { _imgFailed = true; }
            }
            // Link/unlink expediente document
            const expDocId = $('#cdSbMedExpDoc')?.value;
            if (savedId && expDocId) {
                await api(BASE + '/api/expediente.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'vincular_rx', prescripcion_id: savedId, expediente_id: parseInt(expDocId), residente_id: _residenteId }) });
            } else if (savedId && !expDocId && isEdit && editRx.expediente_id) {
                await api(BASE + '/api/expediente.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'desvincular_rx', prescripcion_id: savedId, residente_id: _residenteId }) });
            }
            if (!_imgFailed) {
                showToast(isEdit ? t('toast_rx_updated') : t('toast_rx_added'), 'success');
            }
            const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
            RX_BY_RES[_residenteId] = fresh;
            renderMedList();
            renderRxTracker();
            closeSidebar();
        } catch(e) {
            // api() ya mostró el toast con el mensaje real del servidor;
            // no sobreescribir con un genérico "Error al guardar".
            btnReset(saveBtn);
        }
    });
    $('#cdSbMedCancel').addEventListener('click', closeSidebar);

    // Delete / Reactivate button (edit mode only)
    if (isEdit) {
        $('#cdSbMedDelete')?.addEventListener('click', async () => {
            const delBtn = $('#cdSbMedDelete');
            if (!await cdConfirm(t('confirm_delete_rx_body'), { title: t('confirm_delete_rx_title'), type: 'danger', okText: t('btn_delete') })) return;
            btnLoading(delBtn, t('status_deleting'));
            try {
                await api(`${BASE}/api/prescripciones.php?id=${editRx.id}`, { method:'DELETE' });
                showToast(t('toast_rx_deleted'), 'success');
                const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
                RX_BY_RES[_residenteId] = fresh;
                renderMedList();
                renderRxTracker();
                closeSidebar();
            } catch(e) { showToast(t('error_generic'), 'error'); btnReset(delBtn); }
        });
        $('#cdSbMedArchive')?.addEventListener('click', async () => {
            const archBtn = $('#cdSbMedArchive');
            const newActivo = 1;
            const confirmMsg = t('confirm_reactivate_rx', {name: editRx.nombre});
            if (!await cdConfirm(confirmMsg, { title: t('confirm_reactivate_title'), type: 'warn', okText: t('btn_reactivate') })) return;
            btnLoading(archBtn, t('status_reactivating'));
            try {
                await api(BASE + '/api/prescripciones.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ id: editRx.id, activo: newActivo }) });
                showToast(t('toast_rx_reactivated'), 'success');
                const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
                RX_BY_RES[_residenteId] = fresh;
                renderMedList();
                renderRxTracker();
                closeSidebar();
            } catch(e) { showToast(t('error_generic'), 'error'); btnReset(archBtn); }
        });
    }
}

$('#cdNewRxBtn')?.addEventListener('click', () => openAddMedSidebar());
$('#cdOpenRxFromMedForm')?.addEventListener('click', () => openAddMedSidebar());
$('#cdNewOccMedBtn')?.addEventListener('click', () => openOccasionalMedSidebar());
$('#cdOpenOccMedFromForm')?.addEventListener('click', () => openOccasionalMedSidebar());

function _medInvNormName(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function _medInventorySource() {
    const source = (Array.isArray(_invAllItems) && _invAllItems.length) ? _invAllItems : (_invItems || []);
    const seen = new Set();
    return source.filter(item => {
        const id = String(item?.id || '');
        if (!id || seen.has(id)) return false;
        seen.add(id);
        const tipo = String(item.tipo || '').toLowerCase();
        return !tipo || tipo === 'medicamento' || tipo === 'suplemento';
    });
}

function _findMedicationInventoryMatches(nombre) {
    const needle = _medInvNormName(nombre);
    if (!needle) return [];
    return _medInventorySource()
        .map(item => {
            const hay = _medInvNormName(item.nombre);
            let score = 0;
            if (hay === needle) score = 100;
            else if (hay.startsWith(needle) || needle.startsWith(hay)) score = 80;
            else if (hay.includes(needle) || needle.includes(hay)) score = 60;
            return {...item, _matchScore: score};
        })
        .filter(item => item._matchScore > 0)
        .sort((a, b) => (b._matchScore - a._matchScore) || ((parseFloat(b.stock_actual) || 0) - (parseFloat(a.stock_actual) || 0)))
        .slice(0, 6);
}

function _inventoryExactMedication(nombre) {
    const needle = _medInvNormName(nombre);
    return _medInventorySource().find(item => _medInvNormName(item.nombre) === needle) || null;
}

function ensureMedicationInventoryForAdministration(nombre, cantidad = 1, opts = {}) {
    const exact = _inventoryExactMedication(nombre);
    if (exact) return Promise.resolve(exact);

    const matches = _findMedicationInventoryMatches(nombre);
    const qty = Math.max(0.25, parseFloat(cantidad) || 1);
    const safeName = String(nombre || '').trim();

    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'cd-modal-overlay show cd-med-inv-modal-overlay';
        overlay.innerHTML = `
            <div class="cd-modal cd-med-inv-modal" role="dialog" aria-modal="true" aria-labelledby="cdMedInvTitle">
                <h2 id="cdMedInvTitle">${t('med_inv_match_title')}</h2>
                <p class="cd-med-inv-desc">${t('med_inv_match_desc')}</p>
                <div class="cd-med-inv-name">${esc(safeName)}</div>
                <div class="cd-med-inv-options">
                    ${matches.length ? matches.map((item, idx) => {
                        const stock = parseFloat(item.stock_actual) || 0;
                        const unit = item.unidad || 'uds';
                        const checked = idx === 0 ? 'checked' : '';
                        return `<label class="cd-med-inv-option">
                            <input type="radio" name="cdMedInvChoice" value="match:${item.id}" ${checked}>
                            <span>
                                <strong>${esc(item.nombre)}</strong>
                                <small>${esc(item.tipo || 'medicamento')} · Stock: ${fmtQty(stock)} ${esc(unit)}</small>
                            </span>
                        </label>`;
                    }).join('') : `<div class="cd-med-inv-empty">${t('med_inv_no_matches')}</div>`}
                    <label class="cd-med-inv-option cd-med-inv-create">
                        <input type="radio" name="cdMedInvChoice" value="create" ${matches.length ? '' : 'checked'}>
                        <span>
                            <strong>${t('med_inv_create_new')}</strong>
                            <small>${t('med_inv_create_hint')}</small>
                        </span>
                    </label>
                </div>
                <div class="cd-med-inv-create-fields" id="cdMedInvCreateFields">
                    <div class="cd-form-group">
                        <label class="cd-form-label">${t('inv_unit')}</label>
                        <select class="cd-select" id="cdMedInvUnit">
                            <option value="unidades">${t('unit_units') || 'Unidades'}</option>
                            <option value="tabletas">${t('unit_tablets') || 'Tabletas'}</option>
                            <option value="cápsulas">${t('unit_capsules') || 'Cápsulas'}</option>
                            <option value="ml">ml</option>
                            <option value="mg">mg</option>
                            <option value="frascos">${t('unit_bottles') || 'Frascos'}</option>
                            <option value="ampolletas">${t('unit_ampoules') || 'Ampolletas'}</option>
                            <option value="sobres">${t('unit_sachets') || 'Sobres'}</option>
                        </select>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">${t('inv_initial_stock')} <span style="color:var(--cd-danger)">*</span></label>
                        <input type="number" class="cd-input" id="cdMedInvInitial" min="${qty}" step="0.25" value="${qty}">
                        <p class="cd-med-inv-help">${t('med_inv_initial_hint')}</p>
                    </div>
                </div>
                <div class="cd-med-inv-actions">
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdMedInvCancel">${t('btn_cancel')}</button>
                    <button type="button" class="cd-btn-submit" id="cdMedInvContinue">${t('btn_continue') || 'Continuar'}</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);

        const fields = overlay.querySelector('#cdMedInvCreateFields');
        const syncFields = () => {
            const choice = overlay.querySelector('input[name="cdMedInvChoice"]:checked')?.value || 'create';
            fields.style.display = choice === 'create' ? 'grid' : 'none';
        };
        overlay.querySelectorAll('input[name="cdMedInvChoice"]').forEach(input => input.addEventListener('change', syncFields));
        syncFields();

        const cleanup = result => {
            overlay.remove();
            resolve(result || null);
        };
        overlay.querySelector('#cdMedInvCancel')?.addEventListener('click', () => cleanup(null));
        overlay.addEventListener('click', e => { if (e.target === overlay) cleanup(null); });
        overlay.querySelector('#cdMedInvContinue')?.addEventListener('click', async () => {
            const choice = overlay.querySelector('input[name="cdMedInvChoice"]:checked')?.value || 'create';
            if (choice.startsWith('match:')) {
                const id = parseInt(choice.split(':')[1], 10);
                cleanup(_medInventorySource().find(item => parseInt(item.id, 10) === id) || null);
                return;
            }
            const initial = parseFloat(overlay.querySelector('#cdMedInvInitial')?.value || '0');
            if (!initial || initial < qty) {
                showToast(t('med_inv_initial_required'), 'warning');
                overlay.querySelector('#cdMedInvInitial')?.focus();
                return;
            }
            const btn = overlay.querySelector('#cdMedInvContinue');
            btnLoading(btn, t('status_saving'));
            try {
                const created = await api(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        action: 'crear_inventario',
                        residente_id: _residenteId,
                        nombre: safeName,
                        tipo: 'medicamento',
                        unidad: overlay.querySelector('#cdMedInvUnit')?.value || 'unidades',
                        stock_actual: initial,
                        stock_minimo: 0,
                        notas: opts.notas || t('occ_obs_prefix')
                    })
                });
                if (typeof loadInventoryCache === 'function') await loadInventoryCache();
                cleanup(created || _inventoryExactMedication(safeName));
            } catch (e) {
                btnReset(btn);
                showToast(t('error_generic') || 'Error', 'error');
            }
        });
    });
}

// ── Occasional / SOS medication sidebar ─────────────────────
// One-off administration of a med that may not be in the inventory.
// Saves a `medicacion` registro flagged with `meds_sos:[name]` so the day
// tracker shows it as a blue SOS pill at the administered time.
function openOccasionalMedSidebar() {
    const now = nowInTz();
    const doctorName = _resData?.medico_nombre || '';
    const body = `
        <div class="cd-sidebar-section">
            <div class="cd-sb-occ-info">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>${t('occ_sidebar_title')}. ${t('occ_obs_prefix')}.</span>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('occ_lbl_name')} <span style="color:var(--cd-danger)">*</span></label>
                <input type="text" class="cd-input" id="cdSbOccNombre" placeholder="${t('rx_med_name')}" autocomplete="off">
            </div>
            <div style="display:flex;gap:8px">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('occ_lbl_dose')}</label>
                    <input type="text" class="cd-input" id="cdSbOccDosis" placeholder="500 mg, 1 tab, 10 ml…">
                </div>
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('occ_lbl_via')}</label>
                    <div class="cd-quick-chips" id="cdSbOccViaChips">
                        <button type="button" class="cd-quick-chip" data-val="Oral">Oral</button>
                        <button type="button" class="cd-quick-chip" data-val="Sublingual">SL</button>
                        <button type="button" class="cd-quick-chip" data-val="IV">IV</button>
                        <button type="button" class="cd-quick-chip" data-val="IM">IM</button>
                        <button type="button" class="cd-quick-chip" data-val="SC">SC</button>
                        <button type="button" class="cd-quick-chip" data-val="Tópica">Tópica</button>
                    </div>
                    <input type="hidden" id="cdSbOccVia">
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('occ_lbl_when')}</label>
                    <div style="display:flex;gap:6px">
                        <input type="text" class="cd-input cd-app-date-input" id="cdSbOccFecha" value="${esc(fmtDate(_fecha || now.date))}" data-iso="${esc(_fecha || now.date)}" placeholder="${appDatePlaceholder()}" inputmode="numeric" style="flex:1.2">
                        <input type="time" class="cd-input" id="cdSbOccHora" value="${esc(now.time)}" style="flex:1">
                    </div>
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('occ_lbl_doctor')}</label>
                <input type="text" class="cd-input" id="cdSbOccMedico" placeholder="${t('rx_doctor_placeholder')}" value="${esc(doctorName)}">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('occ_lbl_reason')}</label>
                <textarea class="cd-textarea" id="cdSbOccObs" placeholder="${t('occ_reason_ph')}" rows="3"></textarea>
            </div>
        </div>`;

    const actions = `<button class="cd-btn-submit" id="cdSbOccSave">${t('occ_save_btn')}</button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdSbOccCancel">${t('btn_cancel')}</button>`;

    openSidebar(t('occ_sidebar_title'), body, actions);
    initAppDateTextInput($('#cdSbOccFecha'));

    // Vía chips single-select (reuse pattern)
    const viaChips = $('#cdSbOccViaChips');
    viaChips?.addEventListener('click', e => {
        const chip = e.target.closest('.cd-quick-chip');
        if (!chip) return;
        const wasSelected = chip.classList.contains('selected');
        $$('.cd-quick-chip', viaChips).forEach(c => c.classList.remove('selected'));
        if (wasSelected) {
            $('#cdSbOccVia').value = '';
        } else {
            chip.classList.add('selected');
            $('#cdSbOccVia').value = chip.dataset.val;
        }
    });

    $('#cdSbOccCancel')?.addEventListener('click', closeSidebar);
    $('#cdSbOccSave')?.addEventListener('click', async () => {
        const nombre = $('#cdSbOccNombre').value.trim();
        const dosis  = $('#cdSbOccDosis').value.trim();
        const via    = $('#cdSbOccVia').value;
        const fecha  = appDateInputIso($('#cdSbOccFecha'), { required:true, message:t('occ_lbl_when') + ': ' + appDatePlaceholder() });
        const hora   = $('#cdSbOccHora').value;
        const medico = $('#cdSbOccMedico').value.trim();
        const obsRaw = $('#cdSbOccObs').value.trim();

        if (!nombre) { showToast(t('occ_lbl_name'), 'warning'); $('#cdSbOccNombre').focus(); return; }
        if (!fecha || !hora) { showToast(t('occ_lbl_when'), 'warning'); return; }

        const invItem = await ensureMedicationInventoryForAdministration(nombre, 1, { notas: t('occ_obs_prefix') });
        if (!invItem) return;

        const btn = $('#cdSbOccSave');
        btnLoading(btn, t('status_saving'));
        try {
            // 1) Build human-readable observations summary
            const obsParts = [t('occ_obs_prefix') + ': ' + nombre];
            if (dosis) obsParts.push(dosis);
            if (via)   obsParts.push(via);
            if (medico) obsParts.push('Indicó: ' + medico);
            const obsHeader = obsParts.join(' · ');
            const observaciones = obsRaw ? (obsHeader + ' — ' + obsRaw) : obsHeader;

            // 2) Save the medicación registro flagged as SOS so the day
            //    tracker, timeline, day-report and audit log all pick it up.
            const datos = {
                medicamentos_extra: [nombre],
                med_cantidades: { [nombre]: 1 },
                inventario_admin: [{ id: invItem.id, nombre: invItem.nombre, unidad: invItem.unidad || 'uds', qty: 1 }],
                meds_sos: [nombre],
                med_dosis_extra: dosis ? { [nombre]: dosis } : undefined,
                horarios_cubiertos: { [nombre]: [hora.substring(0,5)] }
            };
            if (via) datos.via_extra = { [nombre]: via };
            if (medico) datos.medico_indica = medico;

            await api(API_URL, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    action: 'crear',
                    residente_id: _residenteId,
                    categoria: 'medicacion',
                    datos: datos,
                    observaciones: observaciones,
                    fecha: fecha,
                    hora: hora.length === 5 ? hora + ':00' : hora
                })
            });

            try {
                await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'movimiento_inventario', item_id: invItem.id, tipo:'salida', cantidad:1, residente_id:_residenteId, motivo:`Administrado — medicacion (${nombre})` })
                });
                if (typeof loadInventoryCache === 'function') await loadInventoryCache();
            } catch (_e) { showToast(t('error_stock_deduct') || 'Error al descontar inventario', 'warning'); }

            showToast(t('occ_saved'), 'success');
            closeSidebar();
            // Refresh dashboard so tracker pill + timeline appear immediately
            if (typeof loadDashboard === 'function') loadDashboard(true);
        } catch (e) {
            btnReset(btn);
            showToast(t('error_generic') || 'Error', 'error');
        }
    });
}

