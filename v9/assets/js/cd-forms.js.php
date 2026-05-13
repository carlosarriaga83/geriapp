// cd-forms.js — Care forms, form submit, collectFormData, med tracker, insumo picker
// Extracted from cuidados.php (lines 9431)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// CARE FORMS
// ═══════════════════════════════════════════════

// Button groups (single select)
$$('.cd-btn-group, .cd-mood-bar, .cd-icon-group, .cd-intake-steps').forEach(g => {
    g.addEventListener('click', e => {
        const btn = e.target.closest('.cd-btn-opt, .cd-mood-btn, .cd-icon-opt, .cd-intake-step');
        if (!btn) return;
        $$('.cd-btn-opt.selected, .cd-mood-btn.selected, .cd-icon-opt.selected, .cd-intake-step.selected', g).forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
    });
});

// Sliders
$$('.cd-slider').forEach(sl => {
    if (sl.id === 'cdSleepHoursSlider') return; // handled separately
    const val = sl.closest('.cd-slider-wrap')?.querySelector('.cd-slider-val');
    sl.addEventListener('input', () => {
        if (val) val.textContent = sl.value + (sl.step==='0.1'?'°C':'%');
        sl.dataset.touched = '1';
    });
});

// Sleep hours slider → update hora_fin and display value
{
    const sleepSlider = $('#cdSleepHoursSlider');
    const sleepVal = $('#cdSleepHoursVal');
    const sleepDayRelInput = $('#cdSleepStartDayRel');
    const sleepDayBtns = $$('#cdSleepStartDayToggle .cd-sleep-day-btn');

    /**
     * Calculate sleep duration in minutes handling midnight crossing.
     * If end < start, assumes overnight sleep (+24h).
     */
    function _sleepDiffMin(startStr, endStr) {
        const [sh, sm] = startStr.split(':').map(Number);
        const [eh, em] = endStr.split(':').map(Number);
        let diff = (eh * 60 + em) - (sh * 60 + sm);
        if (diff <= 0) diff += 1440; // midnight crossing
        return diff;
    }

    /**
     * Determine the correct record date for a sleep event.
     * Rule: sleep belongs to the day it started.
     * If hora_inicio is in the PM (>= 12:00) and _fecha is today,
     * but current real time is AM (next morning), the record date
     * should be yesterday. Also handles manual _fecha selections.
     */
    function _shiftDate(baseDateStr, deltaDays) {
        const [y, m, d] = String(baseDateStr || '').split('-').map(Number);
        if (!y || !m || !d) return baseDateStr;
        const dt = new Date(y, m - 1, d);
        dt.setDate(dt.getDate() + deltaDays);
        const yy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        return `${yy}-${mm}-${dd}`;
    }

    function _getSleepStartDayRel() {
        const v = sleepDayRelInput?.value;
        return v === 'prev' ? 'prev' : 'today';
    }

    // Tracks whether the user has manually picked the day toggle.
    // While false, we auto-sync the toggle from the start/end times so that
    // overnight sleep (hora_fin <= hora_inicio) is anchored to the start day.
    let _userToggledDayRel = false;

    function _setSleepStartDayRel(rel) {
        const norm = rel === 'prev' ? 'prev' : 'today';
        if (sleepDayRelInput) sleepDayRelInput.value = norm;
        sleepDayBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.dayRel === norm));
        if (typeof window._updateEventDateChip === 'function') {
            window._updateEventDateChip('viewFormSueno');
        }
    }

    function _sleepFecha() {
        const rel = _getSleepStartDayRel();
        return rel === 'prev' ? _shiftDate(_fecha, -1) : _fecha;
    }

    /**
     * Auto-sync day toggle from times unless the user has overridden it.
     * Rule: a sleep record belongs to the day it STARTED. If the end time
     * is less than or equal to the start time, the sleep crossed midnight
     * and the start was the previous day relative to _fecha (today).
     */
    function _autoSyncDayRelFromTimes(startStr, endStr) {
        if (_userToggledDayRel) return;
        if (!startStr || !endStr) return;
        const sMin = (parseInt(startStr.split(':')[0])||0) * 60 + (parseInt(startStr.split(':')[1])||0);
        const eMin = (parseInt(endStr.split(':')[0])||0) * 60 + (parseInt(endStr.split(':')[1])||0);
        const crosses = eMin <= sMin;
        _setSleepStartDayRel(crosses ? 'prev' : 'today');
    }

    /**
     * Update the midnight-crossing info hint shown below the time row.
     */
    function _updateSleepHint() {
        const form = $('#formSueno');
        const hint = $('#cdSleepDateHint');
        if (!form || !hint) return;
        // Don't show midnight hint when hora_fin is unknown (pending)
        const isPending = $('#cdSleepPendingCb')?.checked;
        if (isPending) { hint.style.display = 'none'; return; }
        const startStr = form.querySelector('[name="hora_inicio"]')?.value;
        const endStr = form.querySelector('[name="hora_fin"]')?.value;
        if (!startStr || !endStr) { hint.style.display = 'none'; return; }
        // Auto-anchor record date to the day sleep started before computing hint
        _autoSyncDayRelFromTimes(startStr, endStr);
        const [sh] = startStr.split(':').map(Number);
        const [eh] = endStr.split(':').map(Number);
        const crossesMidnight = (eh * 60 + (parseInt(endStr.split(':')[1])||0)) <= (sh * 60 + (parseInt(startStr.split(':')[1])||0));
        const correctedDate = _sleepFecha();
        const dateChanged = correctedDate !== _fecha;
        const rel = _getSleepStartDayRel();
        if (crossesMidnight || dateChanged || rel === 'prev') {
            const parts = [];
            if (crossesMidnight) {
                parts.push(`<span class="cd-sleep-hint-row"><img src="assets/icons/moon-zzz.png" class="cd-sleep-hint-icon" alt=""><span><?= t('sleep_crosses_midnight') ?></span></span>`);
            }
            if (rel === 'prev') {
                parts.push(`<span class="cd-sleep-hint-row">Inicio marcado como <strong>ayer</strong></span>`);
            }
            if (dateChanged) {
                parts.push(`<span class="cd-sleep-hint-row"><?= t('sleep_date_adjusted') ?> <strong>${fmtDate(correctedDate)}</strong></span>`);
            }
            hint.innerHTML = parts.join('');
            hint.style.display = 'flex';
        } else {
            hint.style.display = 'none';
        }
    }

    function updateSleepHoraFin() {
        if (!sleepSlider) return;
        const hrs = parseFloat(sleepSlider.value);
        sleepVal.textContent = (hrs % 1 === 0 ? hrs + 'h' : hrs.toFixed(1) + 'h');
        const form = $('#formSueno');
        const horaInicio = form.querySelector('[name="hora_inicio"]').value;
        if (horaInicio) {
            const [h, m] = horaInicio.split(':').map(Number);
            const totalMin = (h * 60 + m) + Math.round(hrs * 60);
            const endH = Math.floor(totalMin / 60) % 24;
            const endM = totalMin % 60;
            form.querySelector('[name="hora_fin"]').value =
                String(endH).padStart(2, '0') + ':' + String(endM).padStart(2, '0');
        }
        _updateSleepHint();
    }
    sleepSlider?.addEventListener('input', updateSleepHoraFin);
    sleepDayBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            _userToggledDayRel = true;
            _setSleepStartDayRel(btn.dataset.dayRel);
            _updateSleepHint();
        });
    });

    // When hora_fin changes manually → sync slider
    const suenoForm = $('#formSueno');
    suenoForm?.querySelector('[name="hora_fin"]')?.addEventListener('change', () => {
        const horaInicio = suenoForm.querySelector('[name="hora_inicio"]').value;
        const horaFin = suenoForm.querySelector('[name="hora_fin"]').value;
        if (horaInicio && horaFin) {
            let hrs = _sleepDiffMin(horaInicio, horaFin) / 60;
            // Snap to nearest 0.5
            hrs = Math.round(hrs * 2) / 2;
            if (hrs > 16) hrs = 16;
            if (hrs < 0) hrs = 0;
            if (sleepSlider) { sleepSlider.value = hrs; sleepVal.textContent = (hrs % 1 === 0 ? hrs + 'h' : hrs.toFixed(1) + 'h'); }
        }
        _updateSleepHint();
    });

    // When hora_inicio changes → recalculate hora_fin from slider + update hint
    suenoForm?.querySelector('[name="hora_inicio"]')?.addEventListener('change', () => {
        const evt = suenoForm.querySelector('[name="hora_evento"]');
        if (evt) evt.value = suenoForm.querySelector('[name="hora_inicio"]').value;
        updateSleepHoraFin();
    });

    // Expose for form submit and form-open callback
    window._sleepFecha = _sleepFecha;
    window._sleepDiffMin = _sleepDiffMin;
    window._updateSleepHint = _updateSleepHint;
    window._setSleepStartDayRel = _setSleepStartDayRel;
    window._resetSleepDayRelTouch = function() { _userToggledDayRel = false; };

    // Default state on first load
    _setSleepStartDayRel('today');
}

// ── Sleep pending checkbox toggle ────────────────────────────
{
    const pendCb = $('#cdSleepPendingCb');
    const pendHint = $('#cdSleepPendingHint');
    const suenoForm = $('#formSueno');
    if (pendCb && suenoForm) {
        pendCb.addEventListener('change', () => {
            const on = pendCb.checked;
            // toggle hint visibility
            pendHint?.classList.toggle('visible', on);
            // disable/enable hora_fin, slider and calidad
            const horaFin = suenoForm.querySelector('[name="hora_fin"]');
            const wakeUnknown = $('#cdSleepWakeUnknown');
            const slider = $('#cdSleepHoursSlider');
            const sliderVal = $('#cdSleepHoursVal');
            const calSlider = suenoForm.querySelector('[name="calidad_pct"]');
            if (horaFin) {
                horaFin.disabled = on;
                horaFin.style.display = on ? 'none' : '';
            }
            if (wakeUnknown) wakeUnknown.style.display = on ? '' : 'none';
            if (slider) { slider.disabled = on; }
            if (sliderVal) { sliderVal.style.opacity = on ? '.4' : ''; }
            if (calSlider) {
                calSlider.disabled = on;
                calSlider.closest('.cd-form-group').style.opacity = on ? '.4' : '';
            }
            // hide midnight hint when pending
            if (on) {
                const dateHint = $('#cdSleepDateHint');
                if (dateHint) dateHint.style.display = 'none';
            } else {
                if (window._updateSleepHint) window._updateSleepHint();
            }
        });
    }
}

// Photo uploads (alimentación)
let _photoAntes = null, _photoDespues = null;

function _isCapacitorNative() {
    return !!(window.Capacitor && window.Capacitor.isNativePlatform());
}

function _isMobileWeb() {
    return !_isCapacitorNative() && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

// Returns: File on success, 'cancelled' if user dismissed, null on error
async function _capacitorPickPhoto() {
    const Camera = window.Capacitor?.Plugins?.Camera;
    if (!Camera) return null;
    try {
        // Check and request camera permissions before using Camera API
        let perms = await Camera.checkPermissions();
        if (perms.camera !== 'granted') {
            perms = await Camera.requestPermissions({ permissions: ['camera'] });
            if (perms.camera !== 'granted') {
                showToast(t('error_camera_permission') || 'Camera permission denied', 'error');
                return null;
            }
        }
        const photo = await Camera.getPhoto({
            resultType: 'uri',        // more memory-efficient than dataUrl
            source: 'prompt',         // shows actionsheet: Camera / Gallery
            quality: 80,
            width: 1200,
            height: 1200,
            correctOrientation: true
        });
        // Convert webPath (local URI) to File for upload
        const resp = await fetch(photo.webPath);
        const blob = await resp.blob();
        return new File([blob], `photo_${Date.now()}.${photo.format || 'jpeg'}`, {type: blob.type || 'image/jpeg'});
    } catch (e) {
        if (e.message && /cancel|User cancelled/i.test(e.message)) return 'cancelled';
        console.warn('[GeriApp] Camera plugin error:', e);
        return null;
    }
}

/* ── Mobile web actionsheet: Camera / Gallery ── */
function _showPhotoActionSheet(target, wrap) {
    return new Promise(resolve => {
        // Backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'cd-photo-sheet-backdrop';

        const sheet = document.createElement('div');
        sheet.className = 'cd-photo-sheet';
        sheet.innerHTML = `
            <button type="button" class="cd-photo-sheet-btn" data-mode="camera">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                ${t('photo_use_camera') || 'Cámara'}
            </button>
            <button type="button" class="cd-photo-sheet-btn" data-mode="gallery">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                ${t('photo_use_gallery') || 'Galería'}
            </button>
            <button type="button" class="cd-photo-sheet-cancel">${t('btn_cancel') || 'Cancelar'}</button>`;

        const cleanup = () => { backdrop.remove(); };

        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) { cleanup(); resolve(null); }
        });
        sheet.querySelector('.cd-photo-sheet-cancel').addEventListener('click', () => {
            cleanup(); resolve(null);
        });
        sheet.querySelectorAll('.cd-photo-sheet-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.mode;
                cleanup();
                // Create a temporary input with appropriate attributes
                const tmp = document.createElement('input');
                tmp.type = 'file';
                tmp.accept = 'image/*';
                if (mode === 'camera') tmp.setAttribute('capture', 'environment');
                tmp.style.display = 'none';
                document.body.appendChild(tmp);
                tmp.addEventListener('change', () => {
                    const file = tmp.files[0];
                    tmp.remove();
                    resolve(file || null);
                });
                // If user cancels the file picker, clean up
                // Use focus event as a heuristic (fires when picker closes)
                const onFocus = () => {
                    setTimeout(() => { if (!tmp.files.length) { tmp.remove(); resolve(null); } }, 500);
                    window.removeEventListener('focus', onFocus);
                };
                window.addEventListener('focus', onFocus);
                tmp.click();
            });
        });

        backdrop.appendChild(sheet);
        document.body.appendChild(backdrop);
    });
}

let _photoUploadBusy = false;
async function triggerPhotoUpload(wrap) {
    if (_photoUploadBusy) return;          // prevent re-entry from bubbled input.click()
    _photoUploadBusy = true;
    try {
        const target = wrap.querySelector('input[type="file"]')?.dataset.target || 'antes';

        let file = null;

        // 1) Capacitor native → try Camera plugin
        if (_isCapacitorNative()) {
            const result = await _capacitorPickPhoto();
            if (result === 'cancelled') return; // user dismissed — do nothing
            if (result instanceof File) {
                processPhotoFile(result, target, wrap);
                return;
            }
            // Camera failed or unavailable → fall through to file input
        }
        // 2) Mobile web → our own actionsheet (Camera / Gallery)
        else if (_isMobileWeb()) {
            file = await _showPhotoActionSheet(target, wrap);
            if (file) { processPhotoFile(file, target, wrap); }
            return;
        }

        // 3) Desktop or Capacitor fallback → trigger file input
        const input = wrap.querySelector('input[type="file"]');
        if (input) {
            input.style.pointerEvents = ''; // re-enable in case it was disabled
            input.click();
        }
    } finally {
        _photoUploadBusy = false;
    }
}

function handlePhotoChange(e) {
    const input = e.target;
    const origFile = input.files[0];
    if (!origFile) return;
    const target = input.dataset.target;
    const wrap = input.closest('.cd-photo-upload');
    processPhotoFile(origFile, target, wrap);
}

function processPhotoFile(origFile, target, wrap) {
    const url = URL.createObjectURL(origFile);
    wrap.innerHTML = `<img src="${url}"><button type="button" class="cd-photo-remove">&times;</button>
        <input type="file" accept="image/*" data-target="${target}" style="display:none">`;

    (async () => {
        const file = await compressImage(origFile);
        const fd = new FormData();
        fd.append('foto', file);
        try {
            const res = await fetch(`${API_URL}?upload_foto=1`, { method:'POST', body:fd, credentials:'include' });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            if (target === 'antes') _photoAntes = json.data.url;
            else _photoDespues = json.data.url;
        } catch(err) { showToast(t('error_upload_photo'),'error'); }
    })();

    wrap.querySelector('.cd-photo-remove')?.addEventListener('click', ev => {
        ev.stopPropagation();
        if (target === 'antes') _photoAntes = null; else _photoDespues = null;
        wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span><?= t('form_add_photo') ?></span><input type="file" accept="image/*" data-target="${target}">`;
        bindPhotoBox(wrap);
    });
    bindPhotoBox(wrap);
}

function rebindPhotoInput(input) {
    if (!input) return;
    input.addEventListener('change', handlePhotoChange);
}

function bindPhotoBox(wrap) {
    const input = wrap.querySelector('input[type="file"]');
    if (input) {
        input.removeEventListener('change', handlePhotoChange);
        input.addEventListener('change', handlePhotoChange);
        // On mobile, disable pointer-events so our actionsheet handles it
        if (_isMobileWeb()) {
            input.style.pointerEvents = 'none';
        }
    }
    // Remove old click handler to avoid duplicates, then re-add
    wrap.removeEventListener('click', wrap._photoClickHandler);
    wrap._photoClickHandler = (e) => {
        if (e.target.closest('.cd-photo-remove')) return;
        if (!_isMobileWeb() && !_isCapacitorNative() && e.target.tagName === 'INPUT') return; // desktop: let native input handle it
        e.preventDefault();
        triggerPhotoUpload(wrap);
    };
    wrap.addEventListener('click', wrap._photoClickHandler);
}

$$('.cd-photo-upload').forEach(wrap => bindPhotoBox(wrap));

// Prime camera permission on Capacitor so the OS prompt appears early
if (_isCapacitorNative()) {
    (async () => {
        try {
            const Cam = window.Capacitor.Plugins.Camera;
            if (!Cam) return;
            const s = await Cam.checkPermissions();
            if (s.camera !== 'granted') await Cam.requestPermissions({ permissions: ['camera'] });
        } catch (_) { /* ignore — will retry when user taps photo */ }
    })();
}

// Vital signs
function setupVitals() {
    const cfg = {
        temperatura:        {el:'vTemp',status:'vTempStatus',unit:'°C',normal:[36,37.5],warn:[35.5,38]},
        frecuencia_respiratoria:{el:'vFR',status:'vFRStatus',unit:'rpm',normal:[12,20],warn:[10,25]},
        frecuencia_cardiaca:{el:'vFC',  status:'vFCStatus',  unit:'bpm',normal:[60,100],warn:[50,120]},
        pa_sistolica:       {el:'vPAS', status:'vPASStatus', unit:'mmHg',normal:[90,140],warn:[80,160]},
        pa_diastolica:      {el:'vPAD', status:'vPADStatus', unit:'mmHg',normal:[60,90],warn:[50,100]},
        spo2:               {el:'vSpO2',status:'vSpO2Status',unit:'%',   normal:[95,100],warn:[90,100]},
        glucosa:            {el:'vGluc',status:'vGlucStatus',unit:'mg/dL',normal:[70,140],warn:[54,180]},
        peso:               {el:'vPeso',status:'vPesoStatus',unit:'kg',normal:[40,120],warn:[30,150]},
    };
    Object.entries(cfg).forEach(([name,c]) => {
        const sl = $(`input[name="${name}"]`);
        if (!sl) return;
        sl.addEventListener('input', () => {
            const v = parseFloat(sl.value);
            const el = $('#'+c.el), st = $('#'+c.status);
            if (el) el.textContent = c.unit==='°C' ? v.toFixed(1) : v;
            if (st) {
                let cls='danger', lbl='Anormal';
                if (v>=c.normal[0]&&v<=c.normal[1]){cls='normal';lbl='Normal';}
                else if(v>=c.warn[0]&&v<=c.warn[1]){cls='warning';lbl='Precaución';}
                st.className='cd-vital-status '+cls; st.textContent=lbl;
            }
        });
        // ── Tap-to-edit: el display numérico abre un input para teclear el valor.
        // Esto evita la sensibilidad excesiva del scroll en mobile cuando el rango es amplio.
        const display = $('#'+c.el);
        if (display && !display.dataset.tapEdit) {
            display.dataset.tapEdit = '1';
            display.classList.add('cd-vital-value-editable');
            display.setAttribute('role', 'button');
            display.setAttribute('tabindex', '0');
            display.setAttribute('title', (typeof t==='function' ? t('vital_tap_to_edit') : '') || 'Tocar para escribir el valor');
            const openEditor = () => {
                if (display.querySelector('input.cd-vital-inline-input')) return; // ya abierto
                const cur = parseFloat(sl.value);
                const isFloat = (sl.step && parseFloat(sl.step) < 1);
                const inp = document.createElement('input');
                inp.type = 'number';
                inp.className = 'cd-vital-inline-input';
                inp.value = isFloat ? cur.toFixed(1) : String(cur|0);
                inp.min = sl.min || '';
                inp.max = sl.max || '';
                inp.step = sl.step || '1';
                inp.inputMode = isFloat ? 'decimal' : 'numeric';
                inp.setAttribute('aria-label', display.id);
                const prev = display.textContent;
                display.textContent = '';
                display.appendChild(inp);
                inp.focus();
                inp.select();
                let committed = false;
                const commit = (apply) => {
                    if (committed) return;
                    committed = true;
                    if (apply) {
                        let v = parseFloat(inp.value);
                        if (!Number.isFinite(v)) v = cur;
                        const min = parseFloat(sl.min); const max = parseFloat(sl.max);
                        if (Number.isFinite(min) && v < min) v = min;
                        if (Number.isFinite(max) && v > max) v = max;
                        // snap to step (avoid float drift)
                        const step = parseFloat(sl.step) || 1;
                        if (step) v = Math.round(v / step) * step;
                        sl.value = isFloat ? v.toFixed(1) : String(Math.round(v));
                        sl.dataset.touched = '1';
                        // Auto-enable la cd-vital-card si estaba apagada.
                        const card = sl.closest('.cd-vital-card');
                        const toggle = card?.querySelector('.cd-vital-toggle');
                        if (toggle && !toggle.checked) { toggle.checked = true; card.classList.remove('cd-vital-off'); }
                        sl.dispatchEvent(new Event('input', {bubbles:true}));
                        sl.dispatchEvent(new Event('change', {bubbles:true}));
                    } else {
                        display.textContent = prev;
                    }
                };
                inp.addEventListener('keydown', e => {
                    if (e.key === 'Enter') { e.preventDefault(); commit(true); inp.blur(); }
                    else if (e.key === 'Escape') { e.preventDefault(); commit(false); inp.blur(); }
                });
                inp.addEventListener('blur', () => commit(true));
            };
            display.addEventListener('click', e => { e.stopPropagation(); openEditor(); });
            display.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openEditor(); }
            });
        }
    });
}
setupVitals();

// Vital card toggle switches
// Mobile scroll guard — prevent accidental toggle activation while scrolling
// Tracks both vertical and horizontal movement; any drag >6px cancels toggle
let _vitalScrolling = false, _vitalTouchStartX = 0, _vitalTouchStartY = 0;
document.addEventListener('touchstart', e => {
    _vitalScrolling = false;
    _vitalTouchStartX = e.touches[0].clientX;
    _vitalTouchStartY = e.touches[0].clientY;
}, {passive: true});
document.addEventListener('touchmove', e => {
    const dx = Math.abs(e.touches[0].clientX - _vitalTouchStartX);
    const dy = Math.abs(e.touches[0].clientY - _vitalTouchStartY);
    if (dx > 6 || dy > 6) _vitalScrolling = true;
}, {passive: true});

$$('.cd-vital-card').forEach(card => {
    const toggle = card.querySelector('.cd-vital-toggle');
    const slider = card.querySelector('.cd-slider');
    if (!toggle) return;
    // Toggle on/off state
    toggle.addEventListener('change', () => {
        if (_vitalScrolling) { toggle.checked = !toggle.checked; return; }
        card.classList.toggle('cd-vital-off', !toggle.checked);
    });
    // Auto-enable when slider is interacted with
    if (slider) {
        slider.addEventListener('input', () => {
            if (!toggle.checked) {
                toggle.checked = true;
                card.classList.remove('cd-vital-off');
            }
        });
    }
});

// Vital photo evidence buttons
$$('.cd-vital-photo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const wrap = btn.closest('.cd-vital-photo-wrap');
        const inp = wrap.querySelector('.cd-vital-photo-input');
        if (inp) inp.click();
    });
});
$$('.cd-vital-photo-input').forEach(inp => {
    inp.addEventListener('change', () => {
        const preview = inp.closest('.cd-vital-photo-wrap').querySelector('.cd-vital-photo-preview');
        preview.innerHTML = '';
        Array.from(inp.files).forEach(f => {
            if (!f.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => {
                const thumb = document.createElement('div');
                thumb.className = 'cd-vital-photo-thumb';
                thumb.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" class="cd-vital-photo-remove">&times;</button>`;
                thumb.querySelector('.cd-vital-photo-remove').addEventListener('click', () => {
                    thumb.remove();
                    inp.value = '';
                });
                preview.appendChild(thumb);
            };
            reader.readAsDataURL(f);
        });
        // Auto-enable the card
        const card = inp.closest('.cd-vital-card');
        const toggle = card?.querySelector('.cd-vital-toggle');
        if (toggle && !toggle.checked) { toggle.checked = true; card.classList.remove('cd-vital-off'); }
    });
});

// Medication list
function getSkippedMedNames() {
    // Scan today's registros for medicacion entries with medicamentos_no_administrados
    // Returns Map<name, Set<time>> — empty set means all times skipped
    const skipped = new Map();
    _registros.filter(r => r.categoria === 'medicacion').forEach(r => {
        const noAdmin = (r.datos || {}).medicamentos_no_administrados || [];
        noAdmin.forEach(m => {
            const name = (m.nombre || '').trim().toLowerCase();
            if (!name) return;
            if (!skipped.has(name)) skipped.set(name, new Set());
            const times = m.horarios || (m.horario && m.horario !== 'todos' ? [m.horario] : []);
            if (times.length) {
                times.forEach(t => skipped.get(name).add(t));
            }
            // If no times specified (legacy 'todos'), leave the set empty to mean all
        });
    });
    return skipped;
}

function getAdministeredMedTimes() {
    // Scan today's registros for medicacion entries to find already-administered med+time combos
    // Names are normalized (lowercase + trim) to avoid mismatches between rx.nombre and saved
    // medicamentos_seleccionados (which may differ in case/whitespace if med came from inventory).
    const administered = {};
    _registros.filter(r => r.categoria === 'medicacion').forEach(r => {
        const d = r.datos || {};
        const hora = r.hora || '';
        const meds = [...(d.medicamentos_seleccionados||[]),...(d.medicamentos_extra||[])];
        meds.forEach(rawName => {
            const name = String(rawName || '').trim().toLowerCase();
            if (!name) return;
            if (!administered[name]) administered[name] = [];
            if (hora) administered[name].push(hora);
            // Also include explicitly covered schedule times
            const cubiertos = (d.horarios_cubiertos || {})[rawName];
            if (Array.isArray(cubiertos)) {
                cubiertos.forEach(t => {
                    if (t && !administered[name].includes(t)) administered[name].push(t);
                });
            }
        });
    });
    return administered;
}

/* ── Helper: recompute pending state (overdue/upcoming) for a time span ── */
function _recomputeTimePending(ts) {
    const h = ts.dataset.time;
    const _n = nowInTz();
    const isToday = _fecha === _n.date;
    const isFuture = _fecha > _n.date;
    const hNorm = h ? String(h).padStart(5,'0') : '';
    const isPast = !isFuture && (!isToday || !h || hNorm <= _n.time);
    ts.classList.remove('active', 'skip-selected', 'no-administrado');
    ts.classList.toggle('overdue', isPast);
    ts.classList.toggle('upcoming', !isPast);
    ts.textContent = h;
    ts.title = '';
}

/* ── Derive med-item state from its time spans ── */
function _deriveMedItemState(item) {
    const activeCount = $$('.cd-med-time.active', item).length;
    const skipCount = $$('.cd-med-time.skip-selected', item).length;
    const hasTimes = !!item.querySelector('.cd-med-times');

    // Checked if any time is active
    if (hasTimes) item.classList.toggle('checked', activeCount > 0);

    // Qty matches active count
    const qv = item.querySelector('.cd-med-qty-val');
    if (qv) {
        const q = Math.max(activeCount, 1);
        qv.dataset.qty = q;
        qv.textContent = fmtQty(q);
    }

    // Show/hide skip reason UI
    if (skipCount > 0) {
        if (!item.querySelector('.cd-med-skip-reasons')) {
            const infoDiv = item.querySelector('.cd-med-info');
            if (infoDiv) {
                const wrap = document.createElement('div');
                wrap.className = 'cd-med-skip-reasons';
                ['Sin stock','Paciente rechazó','No disponible','Otro'].forEach(r => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'cd-skip-reason-btn';
                    b.textContent = r;
                    b.dataset.reason = r;
                    b.addEventListener('click', ev => {
                        ev.stopPropagation();
                        $$('.cd-skip-reason-btn', wrap).forEach(x => x.classList.remove('selected'));
                        b.classList.add('selected');
                        const oldInput = item.querySelector('.cd-med-skip-reason');
                        if (r === 'Otro') {
                            if (!oldInput) {
                                const inp = document.createElement('input');
                                inp.type = 'text';
                                inp.className = 'cd-med-skip-reason';
                                inp.placeholder = t('ph_write_reason');
                                wrap.after(inp);
                                inp.focus();
                            }
                        } else { if (oldInput) oldInput.remove(); }
                    });
                    wrap.appendChild(b);
                });
                infoDiv.appendChild(wrap);
            }
        }
    } else {
        item.querySelectorAll('.cd-med-skip-reason, .cd-med-skip-reasons').forEach(el => el.remove());
    }

    item.querySelector('.cd-med-times')?.classList.remove('cd-med-times-hint');
    updateMedCount();
    updateStockBadge(item);
    syncFormToTracker();
}

function renderMedList() {
    const list = $('#cdMedList');
    const rxArr = RX_BY_RES[_residenteId] || [];
    const administered = getAdministeredMedTimes();
    const savedSkipped = getSkippedMedNames();
    const _now = nowInTz();
    const _nowTime = _now.time;
    const _isToday = _fecha === _now.date;
    const _isFuture = _fecha > _now.date;
    const invStock = _invCache || {};
    // Preserve archived-list expanded state across re-renders so that
    // changes made via the sidebar are reflected without losing the user's
    // current view.
    const _wasArchivedOpen = !!(list && list.querySelector('#cdMedArchivedList') && list.querySelector('#cdMedArchivedList').style.display !== 'none');
    let html = '';
    rxArr.forEach(rx => {
        if (parseInt(rx.activo) === 0) return; // skip archived
        // Skip meds outside their date range
        if (rx.inicio && rx.inicio > _fecha) return;
        if (rx.fin && rx.fin < _fecha) return;
        const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
        const rxNameKey = String(rx.nombre || '').trim().toLowerCase();
        const adminTimes = administered[rxNameKey] || [];
        const rxSkipTimes = savedSkipped.get(rxNameKey);
        let timesHtml = '';
        if (Array.isArray(horarios) && horarios.length) {
            timesHtml = `<div class="cd-med-times">` + horarios.map(h => {
                const hNorm = String(h).padStart(5,'0');
                const isAdmin = adminTimes.some(at => at.startsWith(hNorm.substring(0,5)));
                const isSkip = !isAdmin && rxSkipTimes && (rxSkipTimes.size === 0 || rxSkipTimes.has(h));
                if (isAdmin) return `<span class="cd-med-time administered" data-time="${esc(h)}" title="Ya administrado">${esc(h)} ✓</span>`;
                if (isSkip) return `<span class="cd-med-time no-administrado" data-time="${esc(h)}" title="No administrado">${esc(h)} ✕</span>`;
                const isPast = !_isFuture && (!_isToday || hNorm <= _nowTime);
                return `<span class="cd-med-time${isPast ? ' overdue' : ' upcoming'}" data-time="${esc(h)}">${esc(h)}</span>`;
            }).join('') + `</div>`;
        }
        const dosisDetail = [rx.dosis, rx.via, rx.frecuencia].filter(Boolean).join(' · ');
        const isSosRx = /\b(sos|prn)\b/i.test(String(rx.frecuencia || ''));
        const sosBadge = isSosRx ? `<span class="cd-med-sos-badge" title="Medicamento SOS / PRN — administración por necesidad">SOS</span>` : '';
        const indicacionTxt = String(rx.indicacion || '').trim();
        const notesHtml = indicacionTxt ? `<div class="cd-med-notes" title="Notas especiales / indicación"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span>${esc(indicacionTxt)}</span></div>` : '';
        // Prescription image icon
        const imgIcon = rx.imagen ? `<button type="button" class="cd-med-img-btn" data-img="${esc(rx.imagen)}" title="Ver foto de prescripción"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>` : '';
        // Match to inventory by name (case-insensitive)
        const _rxNameLc = String(rx.nombre || '').toLowerCase();
        const invMatch = invStock[_rxNameLc];
        const invStockVal = invMatch ? (parseInt(invMatch.stock_actual) || 0) : -1;
        let stockHtml = '';
        if (invMatch) {
            if (invStockVal === 0) stockHtml = `<span class="cd-med-stock cd-med-stock-out" title="Sin stock en inventario"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Sin stock</span>`;
            else if (invStockVal <= 5) stockHtml = `<span class="cd-med-stock cd-med-stock-low" title="${t('rx_low_stock')}"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Stock: ${invStockVal}</span>`;
            else stockHtml = `<span class="cd-med-stock cd-med-stock-ok">Stock: ${invStockVal}</span>`;
        } else {
            stockHtml = `<span class="cd-med-stock cd-med-stock-none" title="No encontrado en inventario">Sin inventario</span>`;
        }
        html += `<div class="cd-med-item${isSosRx ? ' cd-med-item-sos' : ''}" data-rx-id="${rx.id}" data-inv-stock="${invStockVal}" data-med-name="${esc(_rxNameLc)}">
            <div class="cd-med-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="cd-med-info"><div class="cd-med-name">${esc(rx.nombre)}${sosBadge}</div><div class="cd-med-detail">${esc(dosisDetail)}</div>${notesHtml}${timesHtml}</div>
            ${imgIcon}
            <button type="button" class="cd-med-info-btn" data-rx-id="${rx.id}" title="Detalles / Gestionar"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <div class="cd-med-qty-wrap">
                <div class="cd-med-qty">
                    <button type="button" class="cd-med-qty-btn cd-med-qty-minus" title="Menos">−</button>
                    <span class="cd-med-qty-val" data-qty="1">1</span>
                    <button type="button" class="cd-med-qty-btn cd-med-qty-plus" title="Más">+</button>
                </div>
                <span class="cd-med-stock-badge">${stockHtml}</span>
            </div>
        </div>`;
    });
    // Show archived toggle (always, so user knows about archived meds)
    const archivedCount = (RX_BY_RES[_residenteId] || []).filter(rx => parseInt(rx.activo) === 0).length;
    if (archivedCount) {
        html += `<button type="button" class="cd-med-archived-toggle" id="cdMedArchivedToggle">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
            Archivados (${archivedCount})</button>`;
        html += `<div id="cdMedArchivedList" class="cd-med-archived-list" style="display:none"></div>`;
    }
    if (!rxArr.length || !rxArr.some(rx => parseInt(rx.activo) !== 0)) html = '<p style="color:var(--cd-text-muted);font-size:0.8125rem">' + t('empty_no_active_rx') + '</p>' + (archivedCount ? `<button type="button" class="cd-med-archived-toggle" id="cdMedArchivedToggle"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg> Archivados (${archivedCount})</button><div id="cdMedArchivedList" class="cd-med-archived-list" style="display:none"></div>` : '');
    list.innerHTML = html;
    // Restore archived-list expanded state across re-renders
    if (_wasArchivedOpen && archivedCount) {
        const archivedDiv = $('#cdMedArchivedList');
        const togBtn = $('#cdMedArchivedToggle');
        if (archivedDiv) {
            archivedDiv.style.display = 'block';
            renderArchivedMeds();
        }
        if (togBtn) {
            togBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg> Ocultar archivados`;
        }
    }
    // ── Unified time click: cycle pending → active → skip-selected → pending ──
    $$('.cd-med-time', list).forEach(ts => {
        ts.addEventListener('click', e => {
            e.stopPropagation();
            const item = ts.closest('.cd-med-item');
            if (!item) return;
            if (ts.classList.contains('administered')) {
                // Saved administered → revert to pending (with confirm)
                _revertMedTime(item, ts);
                return;
            }
            if (ts.classList.contains('no-administrado')) {
                // Saved skip → revert to pending (with confirm), then set active
                _revertMedTime(item, ts);
                // After revert, make it active (re-administer)
                if (!ts.classList.contains('no-administrado')) {
                    ts.classList.remove('overdue', 'upcoming');
                    ts.classList.add('active');
                    _deriveMedItemState(item);
                }
                return;
            }
            if (ts.classList.contains('active')) {
                // Active → skip-selected
                ts.classList.remove('active');
                ts.classList.add('skip-selected');
            } else if (ts.classList.contains('skip-selected')) {
                // Skip-selected → pending
                ts.classList.remove('skip-selected');
                _recomputeTimePending(ts);
            } else {
                // Pending (overdue/upcoming) → active
                ts.classList.remove('overdue', 'upcoming');
                ts.classList.add('active');
            }
            _deriveMedItemState(item);
        });
    });
    // Item body click: meds WITHOUT horarios toggle checked; with horarios, hint
    $$('.cd-med-item', list).forEach(item => {
        item.addEventListener('click', e => {
            if (e.target.closest('.cd-med-time') || e.target.closest('.cd-med-qty-btn') || e.target.closest('.cd-med-info-btn') || e.target.closest('.cd-med-img-btn') || e.target.closest('.cd-med-skip-reason')) return;
            const hasTimes = !!item.querySelector('.cd-med-times');
            if (hasTimes) {
                if (item.classList.contains('checked')) {
                    // Uncheck: clear active times only (keep skip-selected)
                    $$('.cd-med-time.active', item).forEach(t => { t.classList.remove('active'); _recomputeTimePending(t); });
                    _deriveMedItemState(item);
                } else {
                    const pickable = $$('.cd-med-time:not(.administered):not(.no-administrado):not(.active):not(.skip-selected)', item);
                    if (!pickable.length) return;
                    const timesDiv = item.querySelector('.cd-med-times');
                    if (timesDiv) { timesDiv.classList.add('cd-med-times-hint'); setTimeout(() => timesDiv.classList.remove('cd-med-times-hint'), 1200); }
                }
            } else {
                item.classList.toggle('checked');
                updateMedCount();
                updateStockBadge(item);
                syncFormToTracker();
            }
        });
    });
    // Auto-select med+time if navigating from RxTracker pill click
    if (_rxPendingSelect) {
        const sel = _rxPendingSelect;
        _rxPendingSelect = null;
        const selName = String(sel.nombre || '').toLowerCase();
        const medItem = $$('.cd-med-item', list).find(it => {
            const name = it.querySelector('.cd-med-name')?.textContent || '';
            return name.toLowerCase() === selName;
        });
        if (medItem) {
            if (sel.hora) {
                const timeSpan = $$('.cd-med-time', medItem).find(t => t.dataset.time === sel.hora);
                if (timeSpan && !timeSpan.classList.contains('administered') && !timeSpan.classList.contains('active')) {
                    timeSpan.classList.remove('overdue', 'upcoming', 'no-administrado', 'skip-selected');
                    timeSpan.textContent = timeSpan.dataset.time;
                    timeSpan.title = '';
                    timeSpan.classList.add('active');
                }
            }
            _deriveMedItemState(medItem);
            setTimeout(() => {
                document.querySelector('.cd-main')?.scrollTo(0, 0);
                requestAnimationFrame(() => {
                    medItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    medItem.classList.add('cd-med-highlight');
                    setTimeout(() => medItem.classList.remove('cd-med-highlight'), 1500);
                });
            }, 450);
        }
    }
    // Med info detail button → edit sidebar
    $$('.cd-med-info-btn', list).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const rxId = parseInt(btn.dataset.rxId);
            const rx = (RX_BY_RES[_residenteId]||[]).find(r => r.id == rxId);
            if (rx) openAddMedSidebar(rx);
        });
    });
    // Prescription image viewer
    $$('.cd-med-img-btn', list).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const src = BASE + '/' + btn.dataset.img;
            const overlay = document.createElement('div');
            overlay.className = 'cd-rx-img-overlay';
            overlay.innerHTML = `
                <img src="${src}" class="cd-rx-img-full" data-rot="0">
                <div class="cd-rx-img-toolbar">
                    <button type="button" class="cd-rx-img-tool" data-act="rot-l" title="Rotar 90° a la izquierda" aria-label="Rotar a la izquierda">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                    </button>
                    <button type="button" class="cd-rx-img-tool" data-act="rot-r" title="Rotar 90° a la derecha" aria-label="Rotar a la derecha">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                    <button type="button" class="cd-rx-img-tool" data-act="reset" title="Restablecer rotación" aria-label="Restablecer rotación">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9"/><polyline points="3 4 3 9 8 9"/></svg>
                    </button>
                </div>
                <button type="button" class="cd-rx-img-overlay-close" aria-label="Cerrar">&times;</button>`;
            document.body.appendChild(overlay);
            const imgEl = overlay.querySelector('.cd-rx-img-full');
            const setRot = deg => {
                const norm = ((deg % 360) + 360) % 360;
                imgEl.dataset.rot = String(norm);
                imgEl.style.transform = `rotate(${norm}deg)`;
                // Swap max-w/max-h when sideways so the image still fits the viewport
                if (norm === 90 || norm === 270) {
                    imgEl.style.maxWidth = '90vh';
                    imgEl.style.maxHeight = '90vw';
                } else {
                    imgEl.style.maxWidth = '';
                    imgEl.style.maxHeight = '';
                }
            };
            overlay.addEventListener('click', ev => {
                const tool = ev.target.closest('.cd-rx-img-tool');
                if (tool) {
                    ev.stopPropagation();
                    const cur = parseInt(imgEl.dataset.rot || '0', 10) || 0;
                    const act = tool.dataset.act;
                    if (act === 'rot-l') setRot(cur - 90);
                    else if (act === 'rot-r') setRot(cur + 90);
                    else if (act === 'reset') setRot(0);
                    return;
                }
                if (ev.target === overlay || ev.target.closest('.cd-rx-img-overlay-close')) overlay.remove();
            });
        });
    });
    // Archived toggle
    $('#cdMedArchivedToggle')?.addEventListener('click', () => {
        const archivedDiv = $('#cdMedArchivedList');
        if (!archivedDiv) return;
        const visible = archivedDiv.style.display !== 'none';
        archivedDiv.style.display = visible ? 'none' : 'block';
        const cnt = (RX_BY_RES[_residenteId]||[]).filter(rx => parseInt(rx.activo)===0).length;
        const btn = $('#cdMedArchivedToggle');
        btn.innerHTML = visible
            ? `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg> Archivados (${cnt})`
            : `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg> Ocultar archivados`;
        if (!visible) renderArchivedMeds();
    });
    // Pill qty buttons — fractional steps (require time selection first)
    const QTY_STEPS = [0.25, 0.5, 0.75, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $$('.cd-med-qty-btn', list).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const item = btn.closest('.cd-med-item');
            // If med has horarios, require at least one time selected
            const timesDiv = item.querySelector('.cd-med-times');
            if (timesDiv && !$$('.cd-med-time.active', item).length) {
                timesDiv.classList.add('cd-med-times-hint');
                setTimeout(() => timesDiv.classList.remove('cd-med-times-hint'), 1200);
                return;
            }
            const qtyVal = item.querySelector('.cd-med-qty-val');
            let qty = parseFloat(qtyVal.dataset.qty) || 1;
            if (btn.classList.contains('cd-med-qty-plus')) {
                const next = QTY_STEPS.find(s => s > qty);
                qty = next !== undefined ? next : qty + 1;
            } else {
                const prev = [...QTY_STEPS].reverse().find(s => s < qty);
                qty = prev !== undefined ? prev : 0.25;
            }
            qtyVal.dataset.qty = qty;
            qtyVal.textContent = fmtQty(qty);
            if (!item.classList.contains('checked')) {
                item.classList.add('checked');
                updateMedCount();
            }
            updateStockBadge(item);
        });
    });
}

// Format quantity for display (fractions)
function fmtQty(q) {
    if (q === 0.25) return 'Â¼';
    if (q === 0.5) return 'Â½';
    if (q === 0.75) return 'Â¾';
    if (Number.isInteger(q)) return String(q);
    return String(q);
}

function updateMedCount() {
    const n = $$('.cd-med-item.checked', $('#cdMedList')).length + $$('#cdMedExtraList .cd-extra-med-item.checked').length;
    const s = $$('.cd-med-time.skip-selected', $('#cdMedList')).length;
    let txt = n + ' seleccionados';
    if (s) txt += `, ${s} omitidos`;
    const rv = _revertedEntries.length;
    if (rv) txt += `, ${rv} revertidos`;
    $('#cdMedCount').textContent = txt;
    // Track dirty state
    _medFormDirty = n > 0 || s > 0 || rv > 0 || $$('#cdMedExtraList .cd-extra-med-item').length > 0;
}

/** Glow + shake one or more elements to draw attention  */
function _flashValidation(el) {
    if (!el) return;
    const els = el.length !== undefined ? [...el] : [el];
    els.forEach(e => {
        e.classList.remove('cd-glow', 'cd-shake');
        void e.offsetWidth; // force reflow
        e.classList.add('cd-glow', 'cd-shake');
        e.addEventListener('animationend', () => { e.classList.remove('cd-glow', 'cd-shake'); }, { once: true });
    });
    els[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/** Reverted entries: track administered/skipped times the user wants to revert to pending */
let _revertedEntries = [];
// Each entry: { registroId, nombre, horario, prevState:'administered'|'skipped' }

/** Revert an administered or skipped time to pending — updates _registros in memory + timeline */
async function _revertMedTime(item, ts) {
    const medName = item.querySelector('.cd-med-name')?.textContent || '';
    const hora = ts.dataset.time;
    const wasAdmin = ts.classList.contains('administered');
    const wasSkip = ts.classList.contains('no-administrado');
    if (!wasAdmin && !wasSkip) return;

    const label = wasAdmin ? 'administrado' : 'omitido';
    if (!await cdConfirm(`¿Revertir "${medName}" ${hora} de ${label} a pendiente?`, { title: 'Revertir medicamento', type: 'warn', okText: 'Revertir' })) return;

    // Find the registro that contains this med+time
    const matchReg = _registros.find(r => {
        if (r.categoria !== 'medicacion') return false;
        const d = r.datos || {};
        if (wasAdmin) {
            const meds = [...(d.medicamentos_seleccionados||[]), ...(d.medicamentos_extra||[])];
            if (!meds.includes(medName)) return false;
            const cubiertos = (d.horarios_cubiertos || {})[medName] || [];
            return !hora || cubiertos.includes(hora) || r.hora?.startsWith(hora.substring(0,5));
        }
        if (wasSkip) {
            return (d.medicamentos_no_administrados || []).some(m => m.nombre === medName && (m.horarios||[]).includes(hora));
        }
        return false;
    });

    if (matchReg) {
        _revertedEntries.push({
            registroId: matchReg.id,
            nombre: medName,
            horario: hora,
            prevState: wasAdmin ? 'administered' : 'skipped'
        });

        // Update _registros in memory
        const d = matchReg.datos;
        if (wasAdmin) {
            // Remove from seleccionados if this was the only time
            const cubiertos = (d.horarios_cubiertos || {})[medName] || [];
            const newCub = cubiertos.filter(t => t !== hora);
            if (d.horarios_cubiertos) {
                if (newCub.length) d.horarios_cubiertos[medName] = newCub;
                else delete d.horarios_cubiertos[medName];
                if (!Object.keys(d.horarios_cubiertos).length) delete d.horarios_cubiertos;
            }
            // If no remaining covered times for this med, remove from seleccionados
            if (!newCub.length) {
                d.medicamentos_seleccionados = (d.medicamentos_seleccionados || []).filter(n => n !== medName);
                d.medicamentos_extra = (d.medicamentos_extra || []).filter(n => n !== medName);
                if (d.med_cantidades) delete d.med_cantidades[medName];
            }
        }
        if (wasSkip) {
            const noAdmin = d.medicamentos_no_administrados || [];
            const entry = noAdmin.find(m => m.nombre === medName);
            if (entry) {
                entry.horarios = (entry.horarios || []).filter(t => t !== hora);
                if (!entry.horarios.length) {
                    d.medicamentos_no_administrados = noAdmin.filter(m => m !== entry);
                }
            }
        }
    }

    // Update the time span visually to pending
    _recomputeTimePending(ts);
    _deriveMedItemState(item);

    // Re-render timeline with modified _registros
    renderTimeline();
    renderRxTracker();
}

function updateStockBadge(item) {
    const badge = item.querySelector('.cd-med-stock-badge');
    if (!badge) return;
    const totalStock = parseInt(item.dataset.invStock);
    if (totalStock < 0) return; // not in inventory
    const qty = item.classList.contains('checked') ? (parseFloat(item.querySelector('.cd-med-qty-val')?.dataset.qty) || 1) : 0;
    const remaining = totalStock - qty;
    const warnIcon = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    if (qty === 0) {
        // Show original stock
        if (totalStock === 0) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-out" title="${t('rx_no_stock')}">${warnIcon} Sin stock</span>`;
        else if (totalStock <= 5) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-low" title="${t('rx_low_stock')}">${warnIcon} Stock: ${totalStock}</span>`;
        else badge.innerHTML = `<span class="cd-med-stock cd-med-stock-ok">Stock: ${totalStock}</span>`;
    } else {
        if (remaining <= 0) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-out" title="${t('rx_insufficient_stock')}">${warnIcon} Stock: ${totalStock} → ${remaining}</span>`;
        else if (remaining <= 5) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-low" title="${t('rx_low_stock_after')}">${warnIcon} Stock: ${totalStock} → ${remaining}</span>`;
        else badge.innerHTML = `<span class="cd-med-stock cd-med-stock-ok">Stock: ${totalStock} → ${remaining}</span>`;
    }
}

// Inventory cache — loaded once per dashboard load, keyed by lowercase name
let _invCache = {};   // { "paracetamol 500mg": { id, nombre, stock_actual, unidad, ... } }
let _invItems = [];   // raw array of inventory med items
let _invInsumoCache = {}; // { "pañal": { id, nombre, stock_actual, ... } }
let _invAllItems = []; // all inventory items (for generic insumo picker)
let _notas    = [];   // shift notes for current day (for PDF)
let _notaMedico = null; // current vigente doctor note

// Find pañal item in insumo cache (matches names containing "pañal" or "panal")
function findPanalItem() {
    for (const [key, item] of Object.entries(_invInsumoCache)) {
        if (/pa[ñn]al/i.test(key)) return item;
    }
    return null;
}

// Update the pañal stock badge in the Eliminación form
function updatePanalStockBadge(preview = false) {
    const badge = $('#cdPanalStockBadge');
    if (!badge) return;
    const item = findPanalItem();
    const warnIcon = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    if (!item) { badge.innerHTML = ''; return; }
    const stock = parseInt(item.stock_actual) || 0;
    if (preview) {
        const remaining = stock - 1;
        if (remaining <= 0) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-out" title="${t('rx_insufficient_stock')}">${warnIcon} ${t('inv_diapers')}: ${stock} → ${remaining}</span>`;
        else if (remaining <= 5) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-low" title="${t('rx_low_stock')}">${warnIcon} ${t('inv_diapers')}: ${stock} → ${remaining}</span>`;
        else badge.innerHTML = `<span class="cd-med-stock cd-med-stock-ok">${t('inv_diapers')}: ${stock} → ${remaining}</span>`;
    } else {
        if (stock === 0) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-out" title="${t('rx_no_stock')}">${warnIcon} ${t('inv_diapers')}: 0</span>`;
        else if (stock <= 5) badge.innerHTML = `<span class="cd-med-stock cd-med-stock-low" title="${t('rx_low_stock')}">${warnIcon} ${t('inv_diapers')}: ${stock}</span>`;
        else badge.innerHTML = `<span class="cd-med-stock cd-med-stock-ok">${t('inv_diapers')}: ${stock}</span>`;
    }
}

// Hook into cambio_panal button group to show preview on "Sí" and auto-add pañal to insumo picker
document.querySelector('[data-field="cambio_panal"]')?.addEventListener('click', async e => {
    const btn = e.target.closest('.cd-btn-opt');
    if (!btn) return;
    const isSi = btn.dataset.val === 'Sí';
    // Auto-add/remove pañal in the Eliminación form's insumo picker
    const elimForm = document.querySelector('#viewFormEliminacion .cd-insumo-picker');
    const listDiv = elimForm?.querySelector('.cd-insumo-list');
    if (isSi) {
        let panalItem = findPanalItem();
        // If no pañal exists in inventory, create one automatically
        if (!panalItem) {
            try {
                const res = await api(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'crear_inventario',
                        nombre: 'Pañal',
                        tipo: 'insumo',
                        unidad: 'unidades',
                        stock_actual: 0,
                        stock_minimo: 10,
                        residente_id: _residenteId
                    })
                });
                if (res && res.id) {
                    const newItem = { id: res.id, nombre: res.nombre || 'Pañal', stock_actual: res.stock_actual ?? 0, unidad: res.unidad || 'unidades', tipo: 'insumo', activo: 1 };
                    _invInsumoCache[newItem.nombre.toLowerCase()] = newItem;
                    _invAllItems.push(newItem);
                    panalItem = newItem;
                }
            } catch (err) { /* silent — badge will show empty */ }
        }
        updatePanalStockBadge(true);
        if (panalItem && listDiv && !listDiv.querySelector('.cd-insumo-item[data-auto-panal]')) {
            const stock = parseInt(panalItem.stock_actual) || 0;
            addInsumoItem(listDiv, { id: panalItem.id, nombre: panalItem.nombre, unidad: panalItem.unidad || 'uds', stock, tipo: 'insumo' });
            const added = listDiv.lastElementChild;
            if (added) added.dataset.autoPanal = '1';
        }
    } else {
        updatePanalStockBadge(false);
        if (listDiv) {
            const autoPanal = listDiv.querySelector('.cd-insumo-item[data-auto-panal]');
            if (autoPanal) autoPanal.remove();
        }
    }
});

async function loadInventoryCache() {
    try {
        const raw = await api(`${API_URL}?inventario=1&residente_id=${_residenteId}`);
        const allItems = Array.isArray(raw) ? raw : (raw?.items || []);
        // Incluir medicamentos Y suplementos: ambos pueden estar prescritos y se muestran en el Registro de Medicación.
        _invItems = allItems.filter(i => (i.tipo === 'medicamento' || i.tipo === 'suplemento') && parseInt(i.activo) === 1);
        _invCache = {};
        _invItems.forEach(it => { _invCache[it.nombre.toLowerCase()] = it; });
        // Cache insumo items (pañales, etc.)
        _invInsumoCache = {};
        allItems.filter(i => i.tipo === 'insumo' && parseInt(i.activo) === 1)
            .forEach(it => { _invInsumoCache[it.nombre.toLowerCase()] = it; });
        // All active items for generic insumo picker
        _invAllItems = allItems.filter(i => parseInt(i.activo) === 1);
        updatePanalStockBadge();
    } catch(e) { _invItems = []; _invCache = {}; _invInsumoCache = {}; _invAllItems = []; }
}

// ── Generic Insumo Picker for all forms ──────────────────────────
function initInsumoPickers() {
    $$('.cd-insumo-picker').forEach(picker => {
        const toggle = picker.querySelector('.cd-insumo-toggle');
        const searchWrap = picker.querySelector('.cd-insumo-search-wrap');
        const searchInput = picker.querySelector('.cd-insumo-search');
        const resultsDiv = picker.querySelector('.cd-insumo-search-results');
        const listDiv = picker.querySelector('.cd-insumo-list');
        if (!toggle || !searchWrap || !searchInput || !resultsDiv || !listDiv) return;

        toggle.addEventListener('click', () => {
            const showing = searchWrap.style.display !== 'none';
            searchWrap.style.display = showing ? 'none' : 'block';
            if (!showing) { searchInput.value = ''; searchInput.focus(); renderInsumoResults(resultsDiv, listDiv, ''); }
        });

        searchInput.addEventListener('input', () => renderInsumoResults(resultsDiv, listDiv, searchInput.value));
    });
}

function renderInsumoResults(container, listDiv, query) {
    const q = query.toLowerCase().trim();
    const addedIds = new Set($$('.cd-insumo-item', listDiv).map(el => el.dataset.itemId));
    const items = _invAllItems.filter(it => {
        if (it.tipo === 'medicamento') return false;
        if (parseInt(it.stock_actual) <= 0) return false;
        if (addedIds.has(String(it.id))) return false;
        return !q || it.nombre.toLowerCase().includes(q);
    });
    if (!items.length) {
        container.innerHTML = q ? '<div class="cd-inv-search-empty">' + t('empty_no_results') + '</div>' : '<div class="cd-inv-search-empty">' + t('empty_no_stock_items') + '</div>';
        return;
    }
    const tipoLabels = { medicamento: '\ud83d\udc8a', suplemento: '\ud83e\uddf4', insumo: '\ud83e\uddfb', otro: '\ud83d\udce6' };
    container.innerHTML = items.slice(0, 15).map(it => {
        const s = parseInt(it.stock_actual) || 0;
        const cls = s <= (parseInt(it.stock_minimo) || 5) ? 'cd-med-stock-low' : 'cd-med-stock-ok';
        const emoji = tipoLabels[it.tipo] || '\ud83d\udce6';
        return `<button type="button" class="cd-inv-search-item" data-item='${esc(JSON.stringify({id:it.id,nombre:it.nombre,unidad:it.unidad||'uds',stock:s,tipo:it.tipo}))}'>
            <span class="cd-inv-search-name">${emoji} ${esc(it.nombre)}</span>
            <span class="cd-inv-search-stock ${cls}">Stock: ${s} ${esc(it.unidad||'uds')}</span>
        </button>`;
    }).join('');
    $$('.cd-inv-search-item', container).forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.item);
            addInsumoItem(listDiv, data);
            btn.remove();
            if (!container.querySelector('.cd-inv-search-item')) {
                container.innerHTML = '<div class="cd-inv-search-empty">No hay m\u00e1s items</div>';
            }
        });
    });
}

function addInsumoItem(listDiv, data) {
    const stock = parseInt(data.stock) || 0;
    const div = document.createElement('div');
    div.className = 'cd-insumo-item checked';
    div.dataset.itemId = data.id;
    div.dataset.itemName = data.nombre;
    div.dataset.itemUnit = data.unidad;
    div.dataset.itemStock = stock;
    const stockCls = (stock - 1) <= 0 ? 'cd-med-stock-out' : (stock - 1) <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
    div.innerHTML = `<div class="cd-insumo-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="cd-insumo-info">
            <div class="cd-insumo-name">${esc(data.nombre)}</div>
            <div class="cd-insumo-stock"><span class="cd-insumo-stock-lbl ${stockCls}">Stock: ${stock} \u2192 ${stock - 1} ${esc(data.unidad)}</span></div>
        </div>
        <div class="cd-insumo-qty">
            <button type="button" class="cd-insumo-qty-minus" title="Menos">\u2212</button>
            <span class="cd-insumo-qty-val" data-qty="1">1</span>
            <button type="button" class="cd-insumo-qty-plus" title="M\u00e1s">+</button>
        </div>
        <button type="button" class="cd-insumo-remove" title="Quitar"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>`;

    function updateStockLabel() {
        const qty = parseFloat(div.querySelector('.cd-insumo-qty-val').dataset.qty) || 1;
        const remaining = stock - qty;
        const lbl = div.querySelector('.cd-insumo-stock-lbl');
        const cls = remaining <= 0 ? 'cd-med-stock-out' : remaining <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
        lbl.className = 'cd-insumo-stock-lbl ' + cls;
        lbl.textContent = `Stock: ${stock} \u2192 ${remaining} ${data.unidad}`;
    }

    div.querySelector('.cd-insumo-remove').addEventListener('click', () => div.remove());
    div.querySelector('.cd-insumo-qty-minus').addEventListener('click', e => {
        e.stopPropagation();
        const qv = div.querySelector('.cd-insumo-qty-val');
        let q = parseFloat(qv.dataset.qty) || 1;
        if (q > 1) { q--; qv.dataset.qty = q; qv.textContent = q; updateStockLabel(); }
    });
    div.querySelector('.cd-insumo-qty-plus').addEventListener('click', e => {
        e.stopPropagation();
        const qv = div.querySelector('.cd-insumo-qty-val');
        let q = parseFloat(qv.dataset.qty) || 1;
        if (q < Math.max(stock, 99)) { q++; qv.dataset.qty = q; qv.textContent = q; updateStockLabel(); }
    });

    listDiv.appendChild(div);
}

function collectInsumos(form) {
    const picker = form.closest('.cd-form-view')?.querySelector('.cd-insumo-picker');
    if (!picker) return [];
    return $$('.cd-insumo-item.checked', picker).map(el => ({
        id: parseInt(el.dataset.itemId),
        nombre: el.dataset.itemName,
        unidad: el.dataset.itemUnit,
        qty: parseFloat(el.querySelector('.cd-insumo-qty-val')?.dataset.qty) || 1
    }));
}

function clearInsumoPicker(form) {
    const picker = form.closest('.cd-form-view')?.querySelector('.cd-insumo-picker');
    if (!picker) return;
    const list = picker.querySelector('.cd-insumo-list');
    const searchWrap = picker.querySelector('.cd-insumo-search-wrap');
    if (list) list.innerHTML = '';
    if (searchWrap) searchWrap.style.display = 'none';
}

initInsumoPickers();

// Render archived meds
function renderArchivedMeds() {
    const div = $('#cdMedArchivedList');
    if (!div) return;
    const archived = (RX_BY_RES[_residenteId]||[]).filter(rx => parseInt(rx.activo)===0);
    div.innerHTML = archived.map(rx => {
        const detail = [rx.dosis, rx.via, rx.frecuencia].filter(Boolean).join(' · ');
        return `<div class="cd-med-item cd-med-archived" data-rx-id="${rx.id}">
        <div class="cd-med-info"><div class="cd-med-name">${esc(rx.nombre)}</div><div class="cd-med-detail">${esc(detail) || '—'}</div></div>
        <div style="display:flex;gap:4px;align-items:center">
            <button type="button" class="cd-med-info-btn cd-archived-info-btn" data-rx-id="${rx.id}" title="Detalles"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <button type="button" class="cd-btn-submit cd-btn-secondary cd-med-unarchive" data-rx-id="${rx.id}" style="padding:4px 10px;font-size:0.75rem">Reactivar</button>
            <button type="button" class="cd-btn-submit cd-btn-danger cd-med-del-archived" data-rx-id="${rx.id}" style="padding:4px 10px;font-size:0.75rem">Eliminar</button>
        </div>
    </div>`;
    }).join('');
    // Info button → open edit sidebar
    $$('.cd-archived-info-btn', div).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const rxId = parseInt(btn.dataset.rxId);
            const rx = (RX_BY_RES[_residenteId]||[]).find(r => r.id == rxId);
            if (rx) openAddMedSidebar(rx);
        });
    });
    $$('.cd-med-unarchive', div).forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            const rxId = parseInt(btn.dataset.rxId);
            try {
                await api(BASE + '/api/prescripciones.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ id: rxId, activo: 1 }) });
                showToast(t('toast_rx_reactivated'),'success');
                const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
                RX_BY_RES[_residenteId] = fresh;
                renderMedList();
                if (typeof renderRxTracker === 'function') renderRxTracker();
            } catch(e) {}
        });
    });
    $$('.cd-med-del-archived', div).forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            if (!await cdConfirm(t('confirm_delete_rx_body'), { title: t('confirm_delete_rx_title'), type: 'danger', okText: t('btn_delete') })) return;
            const rxId = parseInt(btn.dataset.rxId);
            try {
                await api(`${BASE}/api/prescripciones.php?id=${rxId}`, { method:'DELETE' });
                showToast(t('toast_rx_deleted'),'success');
                const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
                RX_BY_RES[_residenteId] = fresh;
                renderMedList();
                if (typeof renderRxTracker === 'function') renderRxTracker();
            } catch(e) {}
        });
    });
}

// Prescription detail sidebar (for info/edit/archive)
function openRxDetailSidebar(rx) {
    const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
    const isActive = parseInt(rx.activo) !== 0;
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_info')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('rx_name')}</th><td>${esc(rx.nombre)}</td></tr>
            <tr><th>${t('rx_dose')}</th><td>${esc(rx.dosis||'—')}</td></tr>
            <tr><th>${t('rx_route')}</th><td>${esc(rx.via||'—')}</td></tr>
            <tr><th>${t('rx_frequency')}</th><td>${esc(rx.frecuencia||'—')}</td></tr>
            <tr><th>${t('rx_schedules')}</th><td>${horarios.length ? esc(horarios.join(', ')) : '—'}</td></tr>
            <tr><th>${t('rx_indication')}</th><td>${esc(rx.indicacion||'—')}</td></tr>
            <tr><th>${t('rx_doctor')}</th><td>${esc(rx.medico_nombre||'—')}</td></tr>
            <tr><th>${t('rx_start')}</th><td>${rx.inicio ? esc(fmtDate(rx.inicio)) : '—'}</td></tr>
            <tr><th>${t('rx_end')}</th><td>${rx.fin ? esc(fmtDate(rx.fin)) : `<em>${t('rx_permanent')}</em>`}</td></tr>
            <tr><th>${t('sidebar_status')}</th><td>${isActive ? `<span style="color:var(--cd-success)">${t('status_active')}</span>` : `<span style="color:var(--cd-text-muted)">${t('status_archived')}</span>`}</td></tr>
        </tbody></table>
    </div>`;
    const actions = `<div class="cd-sidebar-row">
        <button class="cd-btn-submit ${isActive ? 'cd-btn-secondary' : ''}" id="cdRxToggle">${isActive ? t('btn_archive') : t('btn_reactivate')}</button>
        <button class="cd-btn-submit cd-btn-danger" id="cdRxDelete">${t('btn_delete')}</button>
    </div><button class="cd-btn-close-sidebar" id="cdRxClose">${t('btn_close')}</button>`;
    openSidebar(rx.nombre, body, actions);
    $('#cdRxToggle')?.addEventListener('click', async () => {
        try {
            await api(BASE + '/api/prescripciones.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ id: rx.id, activo: isActive ? 0 : 1 }) });
            showToast(isActive ? t('toast_rx_archived') : t('toast_rx_reactivated'),'success');
            const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
            RX_BY_RES[_residenteId] = fresh;
            renderMedList();
            if (typeof renderRxTracker === 'function') renderRxTracker();
            closeSidebar();
        } catch(e) {}
    });
    $('#cdRxDelete')?.addEventListener('click', async () => {
        if (!await cdConfirm(t('confirm_delete_rx_body'), { title: t('confirm_delete_rx_title'), type: 'danger', okText: t('btn_delete') })) return;
        try {
            await api(`${BASE}/api/prescripciones.php?id=${rx.id}`, { method:'DELETE' });
            showToast(t('toast_rx_deleted'),'success');
            const fresh = await api(`${BASE}/api/prescripciones.php?res_id=${_residenteId}`);
            RX_BY_RES[_residenteId] = fresh;
            renderMedList();
            if (typeof renderRxTracker === 'function') renderRxTracker();
            closeSidebar();
        } catch(e) {}
    });
    $('#cdRxClose')?.addEventListener('click', closeSidebar);
}

// Prescription tracker (task 15) — renders above timeline
function openRxAdminSidebar(p) {
    const {rx, hora} = p;
    const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
    const nowTime = hora || new Date().toTimeString().substring(0,5);
    // Inventory match for this prescription
    const invMatch = _invCache[String(rx.nombre || '').toLowerCase()];
    const invStockVal = invMatch ? (parseInt(invMatch.stock_actual) || 0) : -1;
    let stockHtml = '';
    if (invMatch) {
        if (invStockVal === 0) stockHtml = `<span style="color:var(--cd-danger)">${t('rx_no_stock')}</span>`;
        else if (invStockVal <= 5) stockHtml = `<span style="color:var(--cd-warning)">Stock: ${invStockVal} ${esc(invMatch.unidad||'uds')}</span>`;
        else stockHtml = `<span style="color:var(--cd-success)">Stock: ${invStockVal} ${esc(invMatch.unidad||'uds')}</span>`;
    } else {
        stockHtml = `<span style="color:var(--cd-text-muted)">${t('rx_no_inventory')}</span>`;
    }
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_prescription')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('rx_name')}</th><td>${esc(rx.nombre)}</td></tr>
            <tr><th>${t('rx_dose')}</th><td>${esc(rx.dosis||'—')}</td></tr>
            <tr><th>${t('rx_route')}</th><td>${esc(rx.via||'—')}</td></tr>
            <tr><th>${t('rx_frequency')}</th><td>${esc(rx.frecuencia||'—')}</td></tr>
            <tr><th>${t('rx_schedules')}</th><td>${horarios.length ? esc(horarios.join(', ')) : '—'}</td></tr>
            <tr><th>${t('rx_indication')}</th><td>${esc(rx.indicacion||'—')}</td></tr>
            <tr><th>${t('rx_doctor')}</th><td>${esc(rx.medico_nombre||'—')}</td></tr>
            <tr><th>${t('rx_start')}</th><td>${rx.inicio ? esc(fmtDate(rx.inicio)) : '—'}</td></tr>
            <tr><th>${t('rx_end')}</th><td>${rx.fin ? esc(fmtDate(rx.fin)) : `<em>${t('rx_permanent')}</em>`}</td></tr>
            <tr><th>${t('rx_inventory')}</th><td>${stockHtml}</td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_register_admin')}</div>
        <div class="cd-form-group">
            <label class="cd-form-label">${t('rx_quantity')}</label>
            <div class="cd-med-qty" style="display:inline-flex;align-items:center;gap:6px">
                <button type="button" class="cd-med-qty-btn cd-med-qty-minus" id="cdRxAdminQtyMinus" title="-">−</button>
                <span class="cd-med-qty-val" id="cdRxAdminQty" data-qty="1" style="min-width:28px;text-align:center;font-weight:600;font-size:0.9375rem">1</span>
                <button type="button" class="cd-med-qty-btn cd-med-qty-plus" id="cdRxAdminQtyPlus" title="+">+</button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label">${t('rx_time')}</label>
            <input type="time" class="cd-input" id="cdRxAdminHora" value="${nowTime}">
        </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" id="cdRxAdminObs" rows="3" placeholder="Opcional¦"></textarea>
        </div>
    </div>`;
    const actions = `<button class="cd-btn-submit" id="cdRxAdminConfirm">${t('rx_confirm_admin')}</button>
        <button class="cd-btn-close-sidebar" id="cdRxAdminClose"><?= t('btn_cancel') ?></button>`;
    openSidebar(rx.nombre, body, actions);
    // Qty buttons in sidebar
    const rxQtySteps = [0.25, 0.5, 0.75, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    const qtyEl = $('#cdRxAdminQty');
    $('#cdRxAdminQtyPlus')?.addEventListener('click', () => {
        let q = parseFloat(qtyEl.dataset.qty) || 1;
        const next = rxQtySteps.find(s => s > q);
        q = next !== undefined ? next : q + 1;
        qtyEl.dataset.qty = q; qtyEl.textContent = fmtQty(q);
    });
    $('#cdRxAdminQtyMinus')?.addEventListener('click', () => {
        let q = parseFloat(qtyEl.dataset.qty) || 1;
        const prev = [...rxQtySteps].reverse().find(s => s < q);
        q = prev !== undefined ? prev : 0.25;
        qtyEl.dataset.qty = q; qtyEl.textContent = fmtQty(q);
    });
    $('#cdRxAdminClose')?.addEventListener('click', closeSidebar);
    $('#cdRxAdminConfirm')?.addEventListener('click', async () => {
        const horaVal = $('#cdRxAdminHora').value || nowTime;
        const obs = ($('#cdRxAdminObs').value || '').trim();
        const qty = parseFloat($('#cdRxAdminQty').dataset.qty) || 1;
        const invItem = invMatch || await ensureMedicationInventoryForAdministration(rx.nombre, qty, { notas: 'Creado desde administración de medicación' });
        if (!invItem) return;
        const btn = $('#cdRxAdminConfirm');
        btnLoading(btn, t('status_saving'));
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                action: 'crear',
                residente_id: _residenteId,
                categoria: 'medicacion',
                datos: { medicamentos_seleccionados: [rx.nombre], med_cantidades: { [rx.nombre]: qty }, ...(hora ? { horarios_cubiertos: { [rx.nombre]: [hora] } } : {}) },
                observaciones: obs,
                fecha: _fecha,
                hora: horaVal
            })});
            try {
                await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'movimiento_inventario', item_id: invItem.id, tipo:'salida', cantidad: qty, residente_id: _residenteId, motivo: `Administrado — medicacion (${rx.nombre})` })
                });
            } catch(e) { showToast(t('error_stock_deduct') || 'Error al descontar inventario', 'warning'); }
            await loadInventoryCache();
            showToast(t('toast_admin_recorded'), 'success');
            closeSidebar();
            await loadDashboard();
        } catch(e) {
            showToast(t('error_register'), 'error');
            btnReset(btn);
        }
    });
}

function openRxPendingChoiceSidebar(p) {
    const {rx} = p;
    const medName = esc(rx.nombre || '');
    const doseStr = rx.dosis ? `<span class="cd-rx-choice-dose">${esc(rx.dosis)}</span>` : '';
    const horaStr = p.hora ? `<span class="cd-rx-choice-time">${esc(p.hora)}</span>` : '';

    // Find matching inventory item by name
    const invItem = _invItems.find(it => it.nombre.toLowerCase() === (rx.nombre || '').toLowerCase());
    const stock = invItem ? parseInt(invItem.stock_actual) || 0 : null;
    const stockHtml = invItem
        ? `<span class="cd-rx-choice-stock ${stock < 1 ? 'low' : ''}">${stock} ${esc(invItem.unidad || 'uds')} en stock</span>`
        : '';

    const body = `
        <div class="cd-rx-choice-header">
            <div class="cd-rx-choice-name">${medName}</div>
            <div class="cd-rx-choice-meta">${doseStr}${horaStr}${stockHtml}</div>
        </div>
        <div class="cd-rx-choice-options">
            <button type="button" class="cd-rx-choice-btn" id="cdRxChoiceAdminister">
                <span class="cd-rx-choice-btn-icon material-symbols-outlined">medication</span>
                <span class="cd-rx-choice-btn-body">
                    <span class="cd-rx-choice-btn-label">Suministrar</span>
                    <span class="cd-rx-choice-btn-desc">Registrar la administración de este medicamento</span>
                </span>
                <span class="material-symbols-outlined cd-rx-choice-btn-arrow">chevron_right</span>
            </button>
            <button type="button" class="cd-rx-choice-btn" id="cdRxChoiceEditRx">
                <span class="cd-rx-choice-btn-icon material-symbols-outlined">edit_note</span>
                <span class="cd-rx-choice-btn-body">
                    <span class="cd-rx-choice-btn-label">Editar prescripción</span>
                    <span class="cd-rx-choice-btn-desc">Modificar dosis, horario o vía de administración</span>
                </span>
                <span class="material-symbols-outlined cd-rx-choice-btn-arrow">chevron_right</span>
            </button>
            ${invItem ? `<button type="button" class="cd-rx-choice-btn${stock < 1 ? ' cd-rx-choice-btn--warn' : ''}" id="cdRxChoiceStock">
                <span class="cd-rx-choice-btn-icon material-symbols-outlined">inventory_2</span>
                <span class="cd-rx-choice-btn-body">
                    <span class="cd-rx-choice-btn-label">Ajustar stock</span>
                    <span class="cd-rx-choice-btn-desc">${stock < 1 ? 'Sin stock disponible — registra una entrada' : 'Registrar entrada o salida de inventario'}</span>
                </span>
                <span class="material-symbols-outlined cd-rx-choice-btn-arrow">chevron_right</span>
            </button>` : ''}
        </div>`;

    openSidebar(medName, body, '');

    $('#cdRxChoiceAdminister')?.addEventListener('click', () => {
        closeSidebar();
        _rxPendingSelect = { nombre: rx.nombre, hora: p.hora };
        const catBtn = $$('.cd-cat-btn').find(b => b.dataset.cat === 'medicacion');
        if (catBtn) catBtn.click();
    });

    $('#cdRxChoiceEditRx')?.addEventListener('click', () => {
        closeSidebar();
        if (typeof openAddMedSidebar === 'function') openAddMedSidebar(rx);
    });

    if (invItem) {
        $('#cdRxChoiceStock')?.addEventListener('click', () => {
            closeSidebar();
            if (typeof openMovModal === 'function') openMovModal(invItem.id);
        });
    }
}

function openRxAdminDetailSidebar(p) {
    const {rx, hora} = p;
    const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
    const match = _registros.find(r => {
        if (r.categoria !== 'medicacion') return false;
        const meds = [...((r.datos||{}).medicamentos_seleccionados||[]),...((r.datos||{}).medicamentos_extra||[])];
        if (!meds.includes(rx.nombre)) return false;
        if (hora) return (r.hora||'').startsWith(String(hora).padStart(5,'0').substring(0,5));
        return true;
    });
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_prescription')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('rx_name')}</th><td>${esc(rx.nombre)}</td></tr>
            <tr><th>${t('rx_dose')}</th><td>${esc(rx.dosis||'—')}</td></tr>
            <tr><th>${t('rx_route')}</th><td>${esc(rx.via||'—')}</td></tr>
            <tr><th>${t('rx_frequency')}</th><td>${esc(rx.frecuencia||'—')}</td></tr>
            <tr><th>${t('rx_schedules')}</th><td>${horarios.length ? esc(horarios.join(', ')) : '—'}</td></tr>
            <tr><th>${t('rx_indication')}</th><td>${esc(rx.indicacion||'—')}</td></tr>
            <tr><th>${t('rx_doctor')}</th><td>${esc(rx.medico_nombre||'—')}</td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_admin_record')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('sidebar_status')}</th><td><span style="color:var(--cd-success)">✓ ${t('rx_administered')}</span></td></tr>
            ${match ? `<tr><th>${t('rx_time')}</th><td>${esc(match.hora||'—')}</td></tr>
            <tr><th>${t('rx_by')}</th><td>${esc(match.usuario_nombre||'—')}</td></tr>
            <tr><th>${t('rx_observations')}</th><td>${esc(match.observaciones||'—')}</td></tr>` : ''}
        </tbody></table>
    </div>`;
    openSidebar(rx.nombre, body, `<button class="cd-btn-close-sidebar" id="cdRxDetailClose">${t('btn_close')}</button>`);
    $('#cdRxDetailClose')?.addEventListener('click', closeSidebar);
}

function openRxSkippedDetailSidebar(p) {
    const {rx} = p;
    const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
    // Find the skip record
    let skipReason = '';
    let skipRecord = null;
    _registros.filter(r => r.categoria === 'medicacion').forEach(r => {
        const noAdmin = (r.datos || {}).medicamentos_no_administrados || [];
        const found = noAdmin.find(m => (m.nombre || '').toLowerCase() === String(rx.nombre || '').toLowerCase());
        if (found) { skipReason = found.razon || ''; skipRecord = r; }
    });
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('rx_prescription')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('rx_name')}</th><td>${esc(rx.nombre)}</td></tr>
            <tr><th>${t('rx_dose')}</th><td>${esc(rx.dosis||'—')}</td></tr>
            <tr><th>${t('rx_route')}</th><td>${esc(rx.via||'—')}</td></tr>
            <tr><th>${t('rx_schedules')}</th><td>${horarios.length ? esc(horarios.join(', ')) : '—'}</td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('sidebar_status')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('sidebar_status')}</th><td><span style="color:var(--cd-danger)">✕ ${t('rx_not_administered')}</span></td></tr>
            <tr><th>${t('rx_reason')}</th><td>${esc(skipReason || '—')}</td></tr>
            ${skipRecord ? `<tr><th>${t('rx_recorded_by')}</th><td>${esc(skipRecord.usuario_nombre||'—')}</td></tr>` : ''}
        </tbody></table>
    </div>`;
    openSidebar(rx.nombre, body, `<button class="cd-btn-close-sidebar" id="cdRxDetailClose">${t('btn_close')}</button>`);
    $('#cdRxDetailClose')?.addEventListener('click', closeSidebar);
}

let _rxPillData = [];
function renderMedicacionPendingBadge(count) {
    const badge = $('#cdMedPendingBadge');
    if (!badge) return;
    if (!count) {
        badge.classList.remove('visible');
        badge.textContent = '';
        badge.title = '';
        return;
    }
    badge.classList.add('visible');
    badge.classList.add('cd-cat-badge--alert');
    badge.textContent = '⚠ ' + count;
    badge.title = (t('rx_pending_count') || 'Medicación pendiente por administrar') + ': ' + count;
}

function renderRxTracker() {
    const tracker = $('#cdRxTracker');
    const pillsDiv = $('#cdRxTrackerPills');
    const sumSpan = $('#cdRxTrackerSum');
    const rxEmpty = $('#cdRxTrackerEmpty');
    if (!tracker || !pillsDiv) return;
    const rxArr = (RX_BY_RES[_residenteId] || []).filter(rx => parseInt(rx.activo) !== 0);
    if (!rxArr.length) {
        tracker.style.display = 'none';
        if (rxEmpty) rxEmpty.style.display = '';
        _rxPillData = [];
        renderMedicacionPendingBadge(0);
        return;
    }
    const today = _fecha;
    const todayRx = rxArr.filter(rx => {
        if (rx.inicio && rx.inicio > today) return false;
        if (rx.fin && rx.fin < today) return false;
        return true;
    });
    if (!todayRx.length) {
        tracker.style.display = 'none';
        if (rxEmpty) rxEmpty.style.display = '';
        _rxPillData = [];
        renderMedicacionPendingBadge(0);
        return;
    }
    tracker.style.display = '';
    if (rxEmpty) rxEmpty.style.display = 'none';
    const administered = getAdministeredMedTimes();
    const savedSkipped = getSkippedMedNames();
    let totalSlots = 0, doneSlots = 0;
    let pendingWarnSlots = 0;
    _rxPillData = [];

    // Build pill entries, each with a sortable time key
    const entries = [];
    todayRx.forEach(rx => {
        const horarios = rx.horarios ? (typeof rx.horarios==='string'?JSON.parse(rx.horarios):rx.horarios) : [];
        const rxNameKey = String(rx.nombre || '').trim().toLowerCase();
        const adminTimes = administered[rxNameKey] || [];
        if (!horarios.length) {
            totalSlots++;
            const done = adminTimes.length > 0;
            if (done) doneSlots++;
            const idx = _rxPillData.length;
            _rxPillData.push({rx, hora: null, done, adminTimes});
            entries.push({ idx, hora: '99:99', h: '', rx, done });
        } else {
            horarios.forEach(h => {
                totalSlots++;
                const hNorm = String(h).padStart(5,'0');
                const done = adminTimes.some(at => at.startsWith(hNorm.substring(0,5)));
                if (done) doneSlots++;
                const idx = _rxPillData.length;
                _rxPillData.push({rx, hora: h, done, adminTimes});
                entries.push({ idx, hora: hNorm, h, rx, done });
            });
        }
    });

    // ── Inject SOS / occasional administrations ─────────────────────
    // Walk today's medicación registros and surface any name flagged as
    // `meds_sos[]` (or any extra med not represented by an active Rx) as a
    // "done" blue SOS pill. These do NOT count toward totalSlots/doneSlots
    // because they are not part of the scheduled regimen.
    const rxNameSet = new Set(todayRx.map(r => String(r.nombre || '').trim().toLowerCase()));
    _registros.filter(r => r.categoria === 'medicacion').forEach(r => {
        const d = r.datos || {};
        const sosList = Array.isArray(d.meds_sos) ? d.meds_sos : [];
        if (!sosList.length) return;
        const hora = (r.hora || '').substring(0,5);
        sosList.forEach(rawName => {
            const name = String(rawName || '').trim();
            if (!name) return;
            if (rxNameSet.has(name.toLowerCase())) return; // already covered by an Rx pill
            const dosis = (d.med_dosis_extra || {})[rawName] || '';
            const via   = (d.via_extra || {})[rawName] || '';
            const fakeRx = {
                nombre: name, dosis: dosis, via: via,
                frecuencia: 'SOS / PRN',
                medico_nombre: d.medico_indica || '',
                horarios: hora ? [hora] : []
            };
            const idx = _rxPillData.length;
            _rxPillData.push({rx: fakeRx, hora: hora || null, done: true, adminTimes: hora ? [hora] : []});
            entries.push({ idx, hora: hora ? hora.padStart(5,'0') : '99:99', h: hora, rx: fakeRx, done: true });
        });
    });

    // Sort by time asc
    entries.sort((a, b) => a.hora.localeCompare(b.hora));

    // Group: mañana (<12), tarde (12-19), noche (>=19 or no-time)
    const groups = { am: [], pm: [], noc: [] };
    entries.forEach(e => {
        const hh = parseInt(e.hora.substring(0,2));
        if (e.hora === '99:99' || hh >= 19) groups.noc.push(e);
        else if (hh >= 12) groups.pm.push(e);
        else groups.am.push(e);
    });

    const _now = nowInTz();
    const _nowTime = _now.time;
    const _isToday = _fecha === _now.date;
    const _isFuture = _fecha > _now.date;

    function pillHtml(e) {
        let isSkipped = false;
        const eNameKey = String(e.rx.nombre || '').trim().toLowerCase();
        if (savedSkipped.has(eNameKey)) {
            const skipTimes = savedSkipped.get(eNameKey);
            isSkipped = skipTimes.size === 0 || (e.h && skipTimes.has(e.h));
        }
        let cls;
        if (isSkipped) { cls = 'skipped'; }
        else if (e.done) { cls = 'done'; }
        else if (_isFuture) { cls = 'pending upcoming'; }
        else if (!_isToday || !e.h) { cls = 'pending overdue'; }
        else { cls = String(e.h).padStart(5,'0') <= _nowTime ? 'pending overdue' : 'pending upcoming'; }

        if (!isSkipped && !e.done && cls === 'pending overdue') {
            pendingWarnSlots++;
        }

        const timeStr = e.h ? `<span class="cd-rx-pill-time">${esc(e.h)}</span>` : '';
        const nameStr = `<span class="cd-rx-pill-name">${esc(e.rx.nombre)}</span>`;
        const doseStr = e.rx.dosis ? `<span class="cd-rx-pill-dose">${esc(e.rx.dosis)}</span>` : '';
        const isSos = /\b(sos|prn)\b/i.test(String(e.rx.frecuencia || ''));
        const sosCls = isSos ? ' cd-rx-pill-sos' : '';
        const sosTitle = isSos ? ' · SOS / PRN (opcional / soporte)' : '';
        const _eRxName = String(e.rx.nombre || '');
        return `<button type="button" class="cd-rx-pill ${cls}${sosCls}" data-pill-idx="${e.idx}" data-med-name="${esc(_eRxName.toLowerCase())}" data-med-hora="${esc(e.h)}" title="${esc(_eRxName)}${e.h ? ' '+e.h : ''}${e.rx.dosis ? ' — '+e.rx.dosis : ''}${sosTitle}">${timeStr}${nameStr}${doseStr}</button>`;
    }

    function colHtml(label, items) {
        const pills = items.length ? items.map(pillHtml).join('') : '<span style="font-size:0.75rem;color:var(--cd-text-muted);text-align:center;display:block">—</span>';
        return `<div class="cd-rx-tracker-col"><div class="cd-rx-tracker-col-label">${label}</div>${pills}</div>`;
    }

    const html = colHtml('<svg class="cd-rx-tod-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg> Mañana', groups.am) + colHtml('<svg class="cd-rx-tod-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a5 5 0 0 0-10 0"/><line x1="12" y1="9" x2="12" y2="2"/><line x1="4.22" y1="10.22" x2="5.64" y2="11.64"/><line x1="1" y1="18" x2="3" y2="18"/><line x1="21" y1="18" x2="23" y2="18"/><line x1="18.36" y1="10.22" x2="19.78" y2="11.64"/><line x1="23" y1="22" x2="1" y2="22"/><polyline points="8 6 12 2 16 6"/></svg> Tarde', groups.pm) + colHtml('<img src="assets/icons/night.png" class="cd-rx-tod-icon"> Noche', groups.noc);
    pillsDiv.innerHTML = html;

    sumSpan.textContent = `${doneSlots}/${totalSlots}`;
    sumSpan.className = 'cd-rx-tracker-summary' + (doneSlots === totalSlots && totalSlots > 0 ? ' complete' : '');
    // Prefer server-computed pending count (single source of truth, matches
    // /api/residentes.php). Fall back to client-computed value only when the
    // server didn't supply one (e.g. tracker rendered before dashboard load).
    const _sb = window.__cdServerBadges;
    const _serverPending = _sb && typeof _sb.medicacion_pendiente === 'number' ? _sb.medicacion_pendiente : null;
    renderMedicacionPendingBadge(_serverPending !== null ? _serverPending : pendingWarnSlots);
    // Read-only mode (e.g. familiar role): show pills but disable interaction
    const _rxReadonly = !!window.__cdRxReadonly;
    const _rxTrackerEl = pillsDiv.closest('.cd-rx-tracker');
    if (_rxTrackerEl) _rxTrackerEl.classList.toggle('readonly', _rxReadonly);
    if (!_rxReadonly) {
    $$('.cd-rx-pill', pillsDiv).forEach(btn => {
        btn.addEventListener('click', () => {
            const p = _rxPillData[parseInt(btn.dataset.pillIdx)];
            if (!p) return;
            if (btn.classList.contains('skipped')) { openRxSkippedDetailSidebar(p); return; }
            if (p.done) { openRxAdminDetailSidebar(p); return; }
            // Pending pill (overdue or upcoming): show action choice drawer
            openRxPendingChoiceSidebar(p);
        });
    });
    }
    // Render mirror tracker inside med form
    _renderRxMirror(pillsDiv.innerHTML, doneSlots, totalSlots);
}

function _renderRxMirror(pillsHtml, doneSlots, totalSlots) {
    const mirror = $('#cdRxTrackerMirror');
    const mPills = $('#cdRxTrackerMirrorPills');
    const mSum = $('#cdRxTrackerMirrorSum');
    if (!mirror || !mPills) return;
    if (!totalSlots) { mirror.style.display = 'none'; return; }
    mirror.style.display = '';
    mPills.innerHTML = pillsHtml;
    if (mSum) {
        mSum.textContent = `${doneSlots}/${totalSlots}`;
        mSum.className = 'cd-rx-tracker-summary' + (doneSlots === totalSlots && totalSlots > 0 ? ' complete' : '');
    }
    // Mirror pill clicks: cycle time state in the form (including revert)
    $$('.cd-rx-pill', mPills).forEach(btn => {
        btn.addEventListener('click', () => {
            const p = _rxPillData[parseInt(btn.dataset.pillIdx)];
            if (!p) return;
            // Find the time span in the form and simulate a click
            const medName = String(p.rx.nombre || '').toLowerCase();
            const list = $('#cdMedList');
            if (!list) return;
            const medItem = $$('.cd-med-item', list).find(it => (it.querySelector('.cd-med-name')?.textContent || '').toLowerCase() === medName);
            if (!medItem) return;
            if (p.hora) {
                const timeSpan = $$('.cd-med-time', medItem).find(t => t.dataset.time === p.hora);
                if (timeSpan) {
                    timeSpan.click(); // triggers the cycle handler (including revert for administered/no-administrado)
                }
            } else {
                medItem.classList.toggle('checked');
                if (!medItem.classList.contains('checked')) {
                    $$('.cd-med-time.active', medItem).forEach(t => t.classList.remove('active'));
                    const qv = medItem.querySelector('.cd-med-qty-val');
                    if (qv) { qv.dataset.qty = '1'; qv.textContent = '1'; }
                }
                updateMedCount();
                updateStockBadge(medItem);
                syncFormToTracker();
            }
            medItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
}

/* ── Live sync: form selections ↔ tracker pills ── */
let _rxPendingSelect = null;
let _medFormDirty = false;
let _careFormDirty = false;

/** Reset the currently visible care form (vital signs, alimentacion, etc.) to a clean state */
function resetCareForm() {
    const activeView = document.querySelector('.cd-view.active');
    if (!activeView) return;
    const form = activeView.querySelector('form[data-cat]');
    if (!form) return;
    form.reset();
    $$('.selected', form).forEach(b => b.classList.remove('selected'));
    $$('.cd-slider', form).forEach(s => delete s.dataset.touched);
    $$('.cd-vital-card', form).forEach(c => { c.classList.add('cd-vital-off'); const t = c.querySelector('.cd-vital-toggle'); if (t) t.checked = false; });
    $$('.cd-vital-photo-preview', form).forEach(p => p.innerHTML = '');
    $$('.cd-vital-photo-input', form).forEach(i => i.value = '');
    // Reset sleep pending state (form.reset unchecks the cb but does NOT fire change)
    const pendCb = form.querySelector('#cdSleepPendingCb');
    if (pendCb) pendCb.dispatchEvent(new Event('change'));
    if (form.id === 'formSueno' && window._setSleepStartDayRel) {
        if (window._resetSleepDayRelTouch) window._resetSleepDayRelTouch();
        window._setSleepStartDayRel('today');
        if (window._updateSleepHint) window._updateSleepHint();
    }
    _photoAntes = null; _photoDespues = null;
    clearInsumoPicker(form);
    if (typeof clearCareDraft === 'function') clearCareDraft();
}

/** Check if medicación form has unsaved changes and prompt user */
async function confirmMedLeave() {
    if (!_medFormDirty) return true;
    return cdConfirm(t('confirm_unsaved_meds'), { title: t('confirm_unsaved_title'), type: 'warn', okText: t('confirm_unsaved_exit'), cancelText: t('confirm_unsaved_stay') });
}

async function confirmNmLeave() {
    if (!_nmFormDirty) return true;
    // 3-button dialog: Exit / Save Draft / Keep Editing
    return new Promise(resolve => {
        _cfgMsg.textContent = t('confirm_unsaved_nm');
        _cfgTitle.textContent = t('confirm_unsaved_title');
        _cfgIcon.className = 'cd-confirm-icon warn';
        _cfgIcon.innerHTML = _CONFIRM_ICONS.warn;
        _cfgYes.textContent = t('confirm_unsaved_exit');
        _cfgYes.className = 'cd-confirm-ok';
        _cfgNo.textContent = t('confirm_unsaved_stay');

        // Insert draft button
        const draftBtn = document.createElement('button');
        draftBtn.className = 'cd-confirm-ok';
        draftBtn.style.cssText = 'background:var(--cd-surface,#f8fafc);color:var(--cd-text);border:1px dashed var(--cd-border);';
        draftBtn.textContent = t('nm_draft') || 'Guardar borrador';
        _cfgNo.parentNode.insertBefore(draftBtn, _cfgNo);

        _cfgOverlay.classList.add('show');

        function cleanup(result) {
            _cfgOverlay.classList.remove('show');
            draftBtn.remove();
            _cfgYes.removeEventListener('click', onYes);
            _cfgNo.removeEventListener('click', onNo);
            draftBtn.removeEventListener('click', onDraft);
            _cfgOverlay.removeEventListener('click', onBg);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        }
        function onYes() { cleanup(true); }
        function onNo()  { cleanup(false); }
        function onDraft() { nmSaveDraft(); cleanup(true); }
        function onBg(e) { if (e.target === _cfgOverlay) cleanup(false); }
        function onKey(e) { if (e.key === 'Escape') cleanup(false); }
        _cfgYes.addEventListener('click', onYes);
        _cfgNo.addEventListener('click', onNo);
        draftBtn.addEventListener('click', onDraft);
        _cfgOverlay.addEventListener('click', onBg);
        document.addEventListener('keydown', onKey);
        _cfgYes.focus();
    });
}

async function confirmCareLeave() {
    if (!_careFormDirty) return true;
    const ok = await cdConfirm(t('confirm_unsaved_care') || 'Tienes cambios sin guardar en el formulario. ¿Deseas salir?', { title: t('confirm_unsaved_title'), type: 'warn', okText: t('confirm_unsaved_exit'), cancelText: t('confirm_unsaved_stay') });
    if (ok) {
        // Save draft indicator so user can see which category had unsaved changes
        const viewToCat = {};
        for (const [cat, view] of Object.entries(CAT_VIEWS)) viewToCat[view] = cat;
        const draftCat = viewToCat[_currentView] || '';
        if (draftCat && _residenteId) {
            try {
                localStorage.setItem('geriapp_care_draft', JSON.stringify({
                    residenteId: _residenteId, categoria: draftCat, fecha: _fecha, ts: Date.now()
                }));
            } catch {}
        }
    }
    return ok;
}

function syncFormToTracker() {
    // Build map of currently-selected meds+times from the form
    const formSel = {};
    $$('.cd-med-item', $('#cdMedList')).forEach(item => {
        const name = (item.querySelector('.cd-med-name')?.textContent || '').toLowerCase();
        const activeTimes = $$('.cd-med-time.active', item).map(t => t.dataset.time).filter(Boolean);
        if (activeTimes.length || item.classList.contains('checked')) formSel[name] = activeTimes;
    });
    // Build map of skip-selected times from ALL items (no longer depends on .skipped class)
    const formSkipped = new Map();
    $$('.cd-med-item', $('#cdMedList')).forEach(item => {
        const name = (item.querySelector('.cd-med-name')?.textContent || '').toLowerCase();
        const skipTimes = $$('.cd-med-time.skip-selected', item).map(t => t.dataset.time).filter(Boolean);
        if (skipTimes.length || item.classList.contains('skipped')) formSkipped.set(name, new Set(skipTimes));
    });
    // Merge saved no-administrado (only times NOT overridden by active selection)
    getSkippedMedNames().forEach((times, name) => {
        if (!formSkipped.has(name)) formSkipped.set(name, new Set());
        const activeSet = new Set(formSel[name] || []);
        times.forEach(t => { if (!activeSet.has(t)) formSkipped.get(name).add(t); });
    });
    const administered = getAdministeredMedTimes();
    // Update both main tracker and mirror
    _syncPillsDom($('#cdRxTrackerPills'), $('#cdRxTrackerSum'), formSel, administered, formSkipped);
    _syncPillsDom($('#cdRxTrackerMirrorPills'), $('#cdRxTrackerMirrorSum'), formSel, administered, formSkipped);
}

function _syncPillsDom(pillsDiv, sumSpan, formSel, administered, formSkipped) {
    if (!pillsDiv) return;
    const skipped = formSkipped || new Map();
    let total = 0, done = 0;
    $$('.cd-rx-pill', pillsDiv).forEach(btn => {
        const medName = btn.dataset.medName || '';
        const medHora = btn.dataset.medHora || '';
        const idx = parseInt(btn.dataset.pillIdx);
        const p = _rxPillData[idx];
        total++;
        const savedDone = p ? p.done : false;
        // Check if this specific pill time is skipped
        let isSkipped = false;
        if (skipped.has(medName)) {
            const skipTimes = skipped.get(medName);
            if (skipTimes.size === 0) {
                // No specific times selected → all are skipped
                isSkipped = true;
            } else {
                // Only skipped if this pill's time matches a skip-selected time
                isSkipped = medHora && skipTimes.has(medHora);
            }
        }
        let formDone = false;
        if (medName in formSel) {
            if (!medHora) formDone = true;
            else formDone = formSel[medName].some(t => String(t).padStart(5,'0').substring(0,5) === String(medHora).padStart(5,'0').substring(0,5));
        }
        const isDone = savedDone || formDone;
        if (isDone) done++;
        btn.classList.toggle('done', isDone && !isSkipped);
        btn.classList.toggle('skipped', isSkipped && !savedDone);
        btn.classList.toggle('pending', !isDone && !isSkipped);
        btn.classList.toggle('form-selected', formDone && !savedDone && !isSkipped);
        // overdue vs upcoming (only for pending)
        if (!isDone && !isSkipped) {
            const h = btn.dataset.medHora || '';
            const _now2 = nowInTz();
            const isToday = _fecha === _now2.date;
            const isFuture = _fecha > _now2.date;
            const overdue = !isFuture && (!isToday || !h || String(h).padStart(5,'0') <= _now2.time);
            btn.classList.toggle('overdue', overdue);
            btn.classList.toggle('upcoming', !overdue);
        } else {
            btn.classList.remove('overdue', 'upcoming');
        }
    });
    if (sumSpan) {
        sumSpan.textContent = `${done}/${total}`;
        sumSpan.className = 'cd-rx-tracker-summary' + (done === total && total > 0 ? ' complete' : '');
    }
}

// ── "+ Del inventario" button: toggle search dropdown ──
$('#cdAddInvBtn')?.addEventListener('click', () => {
    const wrap = $('#cdInvSearchWrap');
    const sosWrap = $('#cdSosInputWrap');
    if (!wrap) return;
    sosWrap && (sosWrap.style.display = 'none');
    const showing = wrap.style.display !== 'none';
    wrap.style.display = showing ? 'none' : 'block';
    if (!showing) { $('#cdInvSearchInput')?.focus(); $('#cdInvSearchInput').value = ''; renderInvSearchResults(''); }
});

// Inventory search input
$('#cdInvSearchInput')?.addEventListener('input', e => renderInvSearchResults(e.target.value));

function renderInvSearchResults(query) {
    const container = $('#cdInvSearchResults');
    if (!container) return;
    const q = query.toLowerCase().trim();
    const items = _invItems.filter(it => {
        if (parseInt(it.stock_actual) <= 0) return false;
        return !q || it.nombre.toLowerCase().includes(q);
    });
    if (!items.length) {
        container.innerHTML = q ? '<div class="cd-inv-search-empty">' + t('empty_no_results') + '</div>' : '<div class="cd-inv-search-empty">' + t('empty_no_med_stock') + '</div>';
        return;
    }
    container.innerHTML = items.slice(0, 15).map(it => {
        const s = parseInt(it.stock_actual) || 0;
        const cls = s <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
        const venc = it.vencimiento || '';
        let vencHtml = '';
        if (venc) {
            const daysLeft = Math.ceil((new Date(venc) - new Date()) / 86400000);
            if (daysLeft <= 0) vencHtml = ' <span class="cd-extra-warn-danger" style="font-size:0.6875rem">Vencido</span>';
            else if (daysLeft <= 30) vencHtml = ` <span class="cd-extra-warn-warning" style="font-size:0.6875rem">Vence: ${esc(fmtDate(venc))}</span>`;
        }
        return `<button type="button" class="cd-inv-search-item" data-inv-id="${it.id}" data-inv-name="${esc(it.nombre)}" data-inv-unit="${esc(it.unidad||'uds')}" data-inv-stock="${s}" data-inv-venc="${esc(venc)}">
            <span class="cd-inv-search-name">${esc(it.nombre)}</span>
            <span class="cd-inv-search-stock ${cls}">Stock: ${s} ${esc(it.unidad||'uds')}${vencHtml}</span>
        </button>`;
    }).join('');
    $$('.cd-inv-search-item', container).forEach(btn => {
        btn.addEventListener('click', () => addExtraMedFromInventory(btn.dataset));
    });
}

function addExtraMedFromInventory(data) {
    const wrap = $('#cdMedExtraList');
    const stock = parseInt(data.invStock) || 0;
    const venc = data.invVenc || '';
    const item = document.createElement('div');
    item.className = 'cd-extra-med-item cd-inv-med-item checked';
    item.dataset.invId = data.invId;
    item.dataset.invName = data.invName;
    item.dataset.invUnit = data.invUnit;
    item.dataset.invStock = data.invStock;
    item.dataset.source = 'inventario';
    // Expiry warning
    let expiryWarn = '';
    if (venc) {
        const daysLeft = Math.ceil((new Date(venc) - new Date()) / 86400000);
        if (daysLeft <= 0) expiryWarn = `<span class="cd-extra-warn cd-extra-warn-danger"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Vencido</span>`;
        else if (daysLeft <= 30) expiryWarn = `<span class="cd-extra-warn cd-extra-warn-warning"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Vence: ${esc(venc)}</span>`;
    }
    const stockCls = stock <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok';
    item.innerHTML = `<div class="cd-inv-med-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="cd-inv-med-info">
            <div class="cd-inv-med-name">${esc(data.invName)}</div>
            <div class="cd-inv-med-stock"><span class="cd-extra-stock-lbl ${stockCls}">Stock: ${stock} → ${stock - 1} ${esc(data.invUnit)}</span>${expiryWarn}</div>
        </div>
        <button type="button" class="cd-sos-remove" title="Quitar"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        <div class="cd-med-qty">
            <button type="button" class="cd-med-qty-btn cd-med-qty-minus" title="Menos">−</button>
            <span class="cd-med-qty-val" data-qty="1">1</span>
            <button type="button" class="cd-med-qty-btn cd-med-qty-plus" title="Más">+</button>
        </div>`;
    item.querySelector('.cd-sos-remove').addEventListener('click', () => { item.remove(); updateMedCount(); });
    function updateExtraStock() {
        const isChecked = item.classList.contains('checked');
        const q = isChecked ? (parseFloat(item.querySelector('.cd-med-qty-val').dataset.qty) || 1) : 0;
        const remaining = stock - q;
        const lbl = item.querySelector('.cd-extra-stock-lbl');
        if (!lbl) return;
        if (!isChecked) {
            lbl.className = 'cd-extra-stock-lbl ' + (stock <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok');
            lbl.textContent = `Stock: ${stock} ${data.invUnit}`;
        } else {
            lbl.className = 'cd-extra-stock-lbl ' + (remaining <= 0 ? 'cd-med-stock-out' : remaining <= 5 ? 'cd-med-stock-low' : 'cd-med-stock-ok');
            lbl.textContent = `Stock: ${stock} → ${remaining} ${data.invUnit}`;
        }
    }
    item.querySelectorAll('.cd-med-qty-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const qv = item.querySelector('.cd-med-qty-val');
            let q = parseFloat(qv.dataset.qty) || 1;
            if (btn.classList.contains('cd-med-qty-plus')) {
                const next = QTY_STEPS.find(s => s > q);
                q = next !== undefined ? Math.min(stock, next) : q;
            } else {
                const prev = [...QTY_STEPS].reverse().find(s => s < q);
                q = prev !== undefined ? prev : QTY_STEPS[0];
            }
            qv.dataset.qty = q; qv.textContent = fmtQty(q);
            updateExtraStock();
        });
    });
    wrap.appendChild(item);
    // Click to toggle selection
    item.addEventListener('click', e => {
        if (e.target.closest('.cd-med-qty-btn') || e.target.closest('.cd-sos-remove')) return;
        item.classList.toggle('checked');
        updateMedCount();
        updateExtraStock();
    });
    updateMedCount();
    $('#cdInvSearchWrap').style.display = 'none';
}

// ── "+ SOS" button: toggle free text input ──
$('#cdAddSosBtn')?.addEventListener('click', () => {
    const wrap = $('#cdSosInputWrap');
    const invWrap = $('#cdInvSearchWrap');
    if (!wrap) return;
    invWrap && (invWrap.style.display = 'none');
    const showing = wrap.style.display !== 'none';
    wrap.style.display = showing ? 'none' : 'flex';
    if (!showing) $('#cdMedExtra')?.focus();
});

// SOS add button
$('#cdMedAddBtn')?.addEventListener('click', () => {
    const input = $('#cdMedExtra');
    const val = input.value.trim();
    if (!val) return;
    const wrap = $('#cdMedExtraList');
    const item = document.createElement('div');
    item.className = 'cd-extra-med-item cd-inv-med-item checked cd-sos-item';
    item.dataset.source = 'sos';
    item.innerHTML = `<div class="cd-inv-med-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="cd-inv-med-info"><div class="cd-inv-med-name">${esc(val)}</div><div class="cd-inv-med-stock" style="color:var(--cd-accent)">SOS</div></div>
        <button type="button" class="cd-sos-remove" title="Quitar"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        <div class="cd-med-qty">
            <button type="button" class="cd-med-qty-btn cd-med-qty-minus" title="Menos">−</button>
            <span class="cd-med-qty-val" data-qty="1">1</span>
            <button type="button" class="cd-med-qty-btn cd-med-qty-plus" title="Más">+</button>
        </div>`;
    item.querySelector('.cd-sos-remove').addEventListener('click', () => { item.remove(); updateMedCount(); });
    item.querySelectorAll('.cd-med-qty-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const qv = item.querySelector('.cd-med-qty-val');
            let q = parseFloat(qv.dataset.qty) || 1;
            if (btn.classList.contains('cd-med-qty-plus')) {
                const next = QTY_STEPS.find(s => s > q);
                q = next !== undefined ? next : q;
            } else {
                const prev = [...QTY_STEPS].reverse().find(s => s < q);
                q = prev !== undefined ? prev : QTY_STEPS[0];
            }
            qv.dataset.qty = q; qv.textContent = fmtQty(q);
        });
    });
    wrap.appendChild(item);
    // Click to toggle selection
    item.addEventListener('click', e => {
        if (e.target.closest('.cd-med-qty-btn') || e.target.closest('.cd-sos-remove')) return;
        item.classList.toggle('checked');
        updateMedCount();
    });
    input.value = '';
    updateMedCount();
});

// Form submission
function collectFormData(form) {
    const cat = form.dataset.cat;
    const datos = {};
    const obs = form.querySelector('[name="observaciones"]')?.value?.trim() || '';

    form.querySelectorAll('.cd-select').forEach(s => { if(s.name&&s.value) datos[s.name]=s.value; });
    form.querySelectorAll('.cd-slider').forEach(s => {
        if (!s.name) return;
        // Skip sliders inside disabled vital cards
        const vCard = s.closest('.cd-vital-card');
        if (vCard && vCard.classList.contains('cd-vital-off')) return;
        datos[s.name]=parseFloat(s.value);
    });
    // Collect vital photo evidence files
    const vitalPhotos = {};
    form.querySelectorAll('.cd-vital-photo-input').forEach(inp => {
        if (inp.files.length && !inp.closest('.cd-vital-card')?.classList.contains('cd-vital-off')) {
            vitalPhotos[inp.dataset.vital] = inp.files[0];
        }
    });
    form.querySelectorAll('.cd-time-input').forEach(s => { if(s.name) datos[s.name]=s.value; });
    form.querySelectorAll('.cd-btn-group,.cd-mood-bar,.cd-icon-group,.cd-intake-steps').forEach(g => {
        const f=g.dataset.field, sel=g.querySelector('.selected');
        if(f&&sel) datos[f]=sel.dataset.val;
    });

    if (cat === 'medicacion') {
        datos.medicamentos_seleccionados = $$('.cd-med-item.checked').map(i => i.querySelector('.cd-med-name')?.textContent||'');
        datos.medicamentos_extra = $$('#cdMedExtraList .cd-extra-med-item.checked[data-source="sos"]').map(el => el.querySelector('.cd-inv-med-name')?.textContent?.trim()||'').filter(Boolean);
        // Quantities alongside names
        datos.med_cantidades = {};
        datos.inventario_admin = datos.inventario_admin || [];
        $$('.cd-med-item.checked').forEach(i => {
            const name = i.querySelector('.cd-med-name')?.textContent||'';
            datos.med_cantidades[name] = parseFloat(i.querySelector('.cd-med-qty-val')?.dataset.qty) || 1;
            // If prescription matches inventory, add to inventario_admin for stock deduction
            const invItem = _invCache[name.toLowerCase()];
            if (invItem) {
                datos.inventario_admin.push({ id: invItem.id, nombre: invItem.nombre, unidad: invItem.unidad || 'uds', qty: datos.med_cantidades[name] });
            }
        });
        // Extra meds from inventory (added via "+ Del inventario") — only checked ones
        const invExtras = $$('#cdMedExtraList .cd-extra-med-item.checked[data-source="inventario"]');
        if (invExtras.length) {
            invExtras.forEach(el => {
                datos.inventario_admin.push({
                    id: parseInt(el.dataset.invId),
                    nombre: el.dataset.invName,
                    unidad: el.dataset.invUnit,
                    qty: parseFloat(el.querySelector('.cd-med-qty-val')?.dataset.qty) || 1
                });
            });
            // Also include names in medicamentos_extra for display in timeline
            invExtras.forEach(el => {
                const name = el.dataset.invName;
                if (name) {
                    datos.medicamentos_extra.push(name);
                    datos.med_cantidades[name] = parseFloat(el.querySelector('.cd-med-qty-val')?.dataset.qty) || 1;
                }
            });
        }
        // SOS extra quantities — only checked ones
        $$('#cdMedExtraList .cd-extra-med-item.checked[data-source="sos"]').forEach(el => {
            const name = el.querySelector('.cd-inv-med-name')?.textContent?.trim()||'';
            if (name) datos.med_cantidades[name] = parseFloat(el.querySelector('.cd-med-qty-val')?.dataset.qty) || 1;
        });
        // Collect active schedule times so RxTracker pills match
        datos.horarios_cubiertos = {};
        $$('.cd-med-item.checked').forEach(i => {
            const name = i.querySelector('.cd-med-name')?.textContent||'';
            const times = $$('.cd-med-time.active', i).map(t => t.dataset.time).filter(Boolean);
            if (times.length) datos.horarios_cubiertos[name] = times;
        });
        if (!Object.keys(datos.horarios_cubiertos).length) delete datos.horarios_cubiertos;
        // Remove empty inventario_admin
        if (!datos.inventario_admin.length) delete datos.inventario_admin;
        // Collect skipped (no administrado) meds with reasons and selected times
        const skippedMeds = [];
        $$('.cd-med-item', $('#cdMedList')).forEach(item => {
            const skipTimes = $$('.cd-med-time.skip-selected', item).map(t => t.dataset.time).filter(Boolean);
            if (!skipTimes.length && !item.classList.contains('skipped')) return;
            const name = item.querySelector('.cd-med-name')?.textContent || '';
            const reasonBtn = item.querySelector('.cd-skip-reason-btn.selected');
            let reason = '';
            if (reasonBtn) {
                reason = reasonBtn.dataset.reason === 'Otro'
                    ? (item.querySelector('.cd-med-skip-reason')?.value?.trim() || 'Otro')
                    : reasonBtn.dataset.reason;
            }
            const horarios = skipTimes.length ? skipTimes : ['todos'];
            if (name) skippedMeds.push({ nombre: name, razon: reason, horarios });
        });
        if (skippedMeds.length) datos.medicamentos_no_administrados = skippedMeds;
    }
    if (cat === 'alimentacion') {
        if (_photoAntes) datos.foto_antes = _photoAntes;
        if (_photoDespues) datos.foto_despues = _photoDespues;
    }
    if (cat === 'incidente') {
        const lugar = form.querySelector('[name="lugar"]')?.value?.trim();
        if (lugar) datos.lugar = lugar;
        const accion = form.querySelector('[name="accion_tomada"]')?.value?.trim();
        if (accion) datos.accion_tomada = accion;
        const traslado = form.querySelector('[name="requiere_traslado"]');
        datos.requiere_traslado = traslado?.checked ? 1 : 0;
        // Foto opcional del incidente (reusa slot 'antes')
        if (_photoAntes) datos.foto = _photoAntes;
    }

    // Sueño pendiente: mark pending and strip inapplicable fields
    if (cat === 'sueno' && $('#cdSleepPendingCb')?.checked) {
        datos.pendiente = true;
        delete datos.hora_fin;
        delete datos.horas;
        delete datos.calidad_pct;
    }
    if (cat === 'sueno') {
        const rel = form.querySelector('[name="inicio_dia_relativo"]')?.value;
        datos.inicio_dia_relativo = (rel === 'prev') ? 'prev' : 'today';
    }

    // Generic insumo picker — collect selected insumos for any form
    const insumos = collectInsumos(form);
    if (insumos.length) {
        datos.insumos_consumidos = insumos;
    }

    return { datos, observaciones: obs, vitalPhotos };
}

$$('form[data-cat]').forEach(form => {
    // Inject "Notificar al médico" toggle before submit button
    const submitBtn = form.querySelector('.cd-btn-submit[type="submit"]');
    if (submitBtn && !IS_DOCTOR) {
        const wrap = document.createElement('div');
        wrap.className = 'cd-alert-doc-toggle';
        wrap.innerHTML = `
            <label class="cd-alert-doc-label">
                <input type="checkbox" class="cd-alert-doc-cb">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                ${t('alert_doc_toggle')}
                <span class="cd-alert-doc-info" title="${t('alert_doc_info_tooltip')}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </span>
            </label>
            <textarea class="cd-textarea cd-alert-doc-msg" placeholder="${t('alert_doc_msg_ph')}" style="display:none" maxlength="500"></textarea>`;
        submitBtn.parentNode.insertBefore(wrap, submitBtn);
        const cb = wrap.querySelector('.cd-alert-doc-cb');
        const msg = wrap.querySelector('.cd-alert-doc-msg');
        cb.addEventListener('change', () => {
            msg.style.display = cb.checked ? '' : 'none';
            if (cb.checked) {
                setTimeout(() => msg.scrollIntoView({ behavior: 'smooth', block: 'center' }), 120);
            }
        });
    }
    // Dirty tracking for care forms (exclude alert-doc toggle/msg from dirtying)
    form.addEventListener('input', e => { if (!e.target.closest('.cd-alert-doc-toggle')) _careFormDirty = true; });
    form.addEventListener('change', e => { if (!e.target.closest('.cd-alert-doc-toggle')) _careFormDirty = true; });
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!CAN_EDIT || !_residenteId) return;
        const todayCheck = nowInTz().date;
        if (!CAN_FUTURE && _fecha > todayCheck) { showToast(t('error_no_future_records'), 'error'); return; }
        const cat = form.dataset.cat;
        const {datos, observaciones, vitalPhotos} = collectFormData(form);
        // At least one medication must be selected or skipped
        if (cat === 'medicacion') {
            const totalMeds = (datos.medicamentos_seleccionados||[]).length + (datos.medicamentos_extra||[]).length + (datos.medicamentos_no_administrados||[]).length;
            // Allow save with 0 meds only when reverting existing records
            const onlyRevert = totalMeds === 0 && _revertedEntries.length > 0;
            if (totalMeds === 0 && !onlyRevert) {
                _flashValidation($('#cdMedList'));
                showToast(t('error_select_med'), 'error'); return;
            }
            // Validate skipped meds: must have a reason
            const invalidSkip = $$('.cd-med-item', form).filter(item => $$('.cd-med-time.skip-selected', item).length > 0 || item.classList.contains('skipped')).find(item => {
                const hasTime = $$('.cd-med-time.skip-selected', item).length > 0;
                const hasReason = !!item.querySelector('.cd-skip-reason-btn.selected');
                if (!hasTime && item.querySelector('.cd-med-times')) return true;
                if (!hasReason) return true;
                if (item.querySelector('.cd-skip-reason-btn.selected')?.dataset.reason === 'Otro') {
                    const v = item.querySelector('.cd-med-skip-reason')?.value?.trim();
                    if (!v) return true;
                }
                return false;
            });
            if (invalidSkip) {
                const hasTime = $$('.cd-med-time.skip-selected', invalidSkip).length > 0;
                if (!hasTime) {
                    const timesW = invalidSkip.querySelector('.cd-med-times');
                    if (timesW) _flashValidation(timesW);
                    showToast(t('error_select_skip_time'), 'error');
                } else {
                    const reasonW = invalidSkip.querySelector('.cd-med-skip-reasons');
                    if (reasonW) _flashValidation(reasonW);
                    else _flashValidation(invalidSkip);
                    showToast(t('error_select_skip_reason'), 'error');
                }
                return;
            }
        }
        // Alimentación: tipo_comida and ingesta_pct required
        if (cat === 'alimentacion') {
            if (!datos.tipo_comida) { _flashValidation(form.querySelector('[name="tipo_comida"]')); showToast(t('error_select_meal'), 'error'); return; }
            if (datos.ingesta_pct == null || datos.ingesta_pct === '') { _flashValidation(form.querySelector('.cd-intake-steps')); showToast(t('error_select_intake'), 'error'); return; }
        }
        // Higiene: tipo_higiene and asistencia required
        if (cat === 'higiene') {
            if (!datos.tipo_higiene) { _flashValidation(form.querySelector('[name="tipo_higiene"]')?.closest('.cd-form-group') || form.querySelector('[data-field="tipo_higiene"]')); showToast(t('error_select_hygiene'), 'error'); return; }
            if (!datos.asistencia) { _flashValidation(form.querySelector('[data-field="asistencia"]')); showToast(t('error_select_assist'), 'error'); return; }
        }
        // Sueño: calidad_pct is required (unless pending)
        if (cat === 'sueno') {
            const isPending = $('#cdSleepPendingCb')?.checked;
            if (!isPending) {
                const calSlider = form.querySelector('[name="calidad_pct"]');
                if (calSlider && (calSlider.value === '' || calSlider.value == null)) { _flashValidation(calSlider.closest('.cd-form-group')); showToast(t('error_select_quality') || 'Selecciona la calidad de sueño', 'error'); return; }
            }
        }
        // Terapia: tipo_terapia and duracion required
        if (cat === 'terapia') {
            if (!datos.tipo_terapia) { _flashValidation(form.querySelector('[name="tipo_terapia"]')?.closest('.cd-form-group') || form.querySelector('[data-field="tipo_terapia"]')); showToast(t('error_select_therapy'), 'error'); return; }
            if (datos.duracion == null || datos.duracion === '') { _flashValidation(form.querySelector('[name="duracion"]')?.closest('.cd-form-group') || form.querySelector('[data-field="duracion"]')); showToast(t('error_select_session'), 'error'); return; }
        }
        // Movilidad: nivel_asistencia, tipo_actividad and duracion required
        if (cat === 'movilidad') {
            if (!datos.nivel_asistencia) { _flashValidation(form.querySelector('[data-field="nivel_asistencia"]')); showToast(t('error_select_assist'), 'error'); return; }
            if (!datos.tipo_actividad) { _flashValidation(form.querySelector('[data-field="tipo_actividad"]') || form.querySelector('[name="tipo_actividad"]')?.closest('.cd-form-group')); showToast(t('error_select_activity'), 'error'); return; }
            if (datos.duracion == null || datos.duracion === '') { _flashValidation(form.querySelector('[name="duracion"]')?.closest('.cd-form-group')); showToast(t('error_select_duration'), 'error'); return; }
        }
        // Eliminación: all fields except observaciones are required
        if (cat === 'eliminacion') {
            if (!datos.tipo_eliminacion) { _flashValidation(form.querySelector('[data-field="tipo_eliminacion"]')); showToast(t('error_select_elim'), 'error'); return; }
            if (!datos.color_aspecto) { _flashValidation(form.querySelector('[data-field="color_aspecto"]')); showToast(t('error_select_color'), 'error'); return; }
            if (!datos.cantidad) { _flashValidation(form.querySelector('[data-field="cantidad"]')); showToast(t('error_select_qty'), 'error'); return; }
            if (!datos.cambio_panal) { _flashValidation(form.querySelector('[data-field="cambio_panal"]')); showToast(t('error_select_diaper'), 'error'); return; }
        }
        // Comportamiento: estado_animo and incidentes required
        if (cat === 'comportamiento') {
            if (!datos.estado_animo) { _flashValidation(form.querySelector('[data-field="estado_animo"]')); showToast(t('error_select_mood'), 'error'); return; }
            if (!datos.incidentes) { _flashValidation(form.querySelector('[data-field="incidentes"]')); showToast(t('error_select_incident'), 'error'); return; }
        }
        // Signos Vitales: at least one vital sign must be toggled on
        if (cat === 'signos_vitales') {
            const hasVital = form.querySelectorAll('.cd-vital-toggle:checked').length > 0;
            if (!hasVital) { _flashValidation(form.querySelector('.cd-vitals-grid')); showToast(t('error_select_vital'), 'error'); return; }
        }
        // Incidente: tipo and severidad required
        if (cat === 'incidente') {
            if (!datos.tipo_incidente) { _flashValidation(form.querySelector('[data-field="tipo_incidente"]')); showToast(t('error_select_incidente_type') || 'Selecciona el tipo de incidente', 'error'); return; }
            if (!datos.severidad) { _flashValidation(form.querySelector('[data-field="severidad"]')); showToast(t('error_select_severity') || 'Selecciona la severidad', 'error'); return; }
        }
        const evtTime = form.querySelector('.cd-event-time');
        const hora = (evtTime && evtTime.value) ? evtTime.value : (String(new Date().getHours()).padStart(2,'0')+':'+String(new Date().getMinutes()).padStart(2,'0'));
        const onlyRevertBeforeSave = cat === 'medicacion' && (datos.medicamentos_seleccionados||[]).length + (datos.medicamentos_extra||[]).length + (datos.medicamentos_no_administrados||[]).length === 0 && _revertedEntries.length > 0;
        if (cat === 'medicacion' && !onlyRevertBeforeSave && !(_editingRecord && _editingRecord.categoria === cat)) {
            datos.inventario_admin = datos.inventario_admin || [];
            const linkedNames = new Set(datos.inventario_admin.map(inv => _medInvNormName(inv.nombre)));
            const allAdminNames = [...(datos.medicamentos_seleccionados || []), ...(datos.medicamentos_extra || [])]
                .map(name => String(name || '').trim())
                .filter(Boolean);
            for (const name of allAdminNames) {
                const key = _medInvNormName(name);
                if (!key || linkedNames.has(key)) continue;
                const qty = parseFloat(datos.med_cantidades?.[name]) || 1;
                const invItem = await ensureMedicationInventoryForAdministration(name, qty, { notas: 'Creado desde registro de medicación' });
                if (!invItem) return;
                datos.inventario_admin.push({ id: invItem.id, nombre: invItem.nombre, unidad: invItem.unidad || 'uds', qty });
                linkedNames.add(key);
            }
            if (!datos.inventario_admin.length) delete datos.inventario_admin;
        }
        const btn = form.querySelector('.cd-btn-submit:not(.cd-btn-danger)');
        btnLoading(btn, t('status_saving'));
        try {
            // Handle only-revert save: skip new record creation when no new meds but reverts exist
            const onlyRevert = onlyRevertBeforeSave;

            if (_editingRecord && _editingRecord.categoria === cat) {
                // UPDATE existing record
                const alertDocCbEdit = form.querySelector('.cd-alert-doc-cb');
                const notificar_medico_edit = alertDocCbEdit?.checked ? 1 : 0;
                const mensaje_medico_edit = notificar_medico_edit ? (form.querySelector('.cd-alert-doc-msg')?.value?.trim() || '') : '';
                const updatedRecord = await api(API_URL, {
                    method:'PUT', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ id: _editingRecord.id, datos, observaciones, hora, notificar_medico: notificar_medico_edit, mensaje_medico: mensaje_medico_edit })
                });
                if (typeof cdApplyRecordMutation === 'function') cdApplyRecordMutation(updatedRecord, { broadcast: true });
                // Upload vital sign photo evidence on edit (if new photos added)
                if (cat === 'signos_vitales' && Object.keys(vitalPhotos).length) {
                    const fd = new FormData();
                    fd.append('action', 'upload_vital_photos');
                    fd.append('residente_id', _residenteId);
                    fd.append('registro_id', _editingRecord.id);
                    fd.append('fecha', _editingRecord.fecha || _fecha);
                    fd.append('hora', hora);
                    for (const [vName, file] of Object.entries(vitalPhotos)) {
                        fd.append(`foto_${vName}`, file);
                    }
                    try {
                        await fetch(API_URL, { method: 'POST', headers: api_headers_multipart(), body: fd });
                    } catch(e) { console.warn('Error subiendo fotos de signos vitales:', e); }
                }
                showToast(t('toast_record_updated'),'success');
            } else if (!onlyRevert) {
                // Biometric verification for medication on native devices
                let verificacion_biometrica = 0;
                if (cat === 'medicacion' && window.Capacitor && window.Capacitor.isNativePlatform()) {
                    try {
                        const bio = window.Capacitor?.Plugins?.NativeBiometric;
                        if (bio) {
                            const avail = await bio.isAvailable();
                            if (avail?.isAvailable) {
                                await bio.verifyIdentity({ reason: 'Firma biométrica para registro de medicamento', title: 'Verificación biométrica' });
                                verificacion_biometrica = 1;
                            }
                        }
                    } catch(bioErr) {
                        console.warn('[Bio] Verificación cancelada o fallida:', bioErr);
                        // Continue without biometric — not blocking
                    }
                }
                // CREATE new record
                const recordFecha = (cat === 'sueno' && window._sleepFecha) ? window._sleepFecha() : _fecha;
                // Check alert-to-doctor toggle
                const alertDocCb = form.querySelector('.cd-alert-doc-cb');
                const notificar_medico = alertDocCb?.checked ? 1 : 0;
                const mensaje_medico = notificar_medico ? (form.querySelector('.cd-alert-doc-msg')?.value?.trim() || '') : '';
                const createdRecord = await api(API_URL, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'crear', residente_id:_residenteId, categoria:cat, datos, observaciones, fecha:recordFecha, hora, verificacion_biometrica, notificar_medico, mensaje_medico })
                });
                if (typeof cdApplyRecordMutation === 'function') cdApplyRecordMutation(createdRecord, { broadcast: true });
                showToast(t('toast_record_saved'),'success');
                _careFormDirty = false;
                // Upload vital sign photo evidence (if any)
                if (cat === 'signos_vitales' && Object.keys(vitalPhotos).length && createdRecord?.id) {
                    const fd = new FormData();
                    fd.append('action', 'upload_vital_photos');
                    fd.append('residente_id', _residenteId);
                    fd.append('registro_id', createdRecord.id);
                    fd.append('fecha', recordFecha);
                    fd.append('hora', hora);
                    for (const [vName, file] of Object.entries(vitalPhotos)) {
                        fd.append(`foto_${vName}`, file);
                    }
                    try {
                        await fetch(API_URL, { method: 'POST', headers: api_headers_multipart(), body: fd });
                    } catch(e) { console.warn('Error subiendo fotos de signos vitales:', e); }
                }
                // Deduct stock for inventory meds used
                if (datos.inventario_admin?.length) {
                    for (const inv of datos.inventario_admin) {
                        try {
                            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                                body: JSON.stringify({ action:'movimiento_inventario', item_id: inv.id, tipo:'salida', cantidad: inv.qty, residente_id: _residenteId, motivo: `Administrado — ${cat}` })
                            });
                        } catch(e) { showToast(t('error_stock_deduct') || 'Error al descontar inventario', 'warning'); }
                    }
                    await loadInventoryCache();
                }
                // Deduct pañal stock when cambio_panal = Sí
                if (cat === 'eliminacion' && datos.cambio_panal === 'Sí') {
                    const panalItem = findPanalItem();
                    if (panalItem && parseInt(panalItem.stock_actual) > 0) {
                        try {
                            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                                body: JSON.stringify({ action:'movimiento_inventario', item_id: panalItem.id, tipo:'salida', cantidad: 1, residente_id: _residenteId, motivo: 'Cambio de pañal' })
                            });
                        } catch(e) { showToast(t('error_stock_deduct') || 'Error al descontar pañal', 'warning'); }
                        await loadInventoryCache();
                    }
                }
                // Deduct stock for generic insumos selected in any form
                if (datos.insumos_consumidos?.length) {
                    for (const ins of datos.insumos_consumidos) {
                        try {
                            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                                body: JSON.stringify({ action:'movimiento_inventario', item_id: ins.id, tipo:'salida', cantidad: ins.qty, residente_id: _residenteId, motivo: `Consumo — ${cat}` })
                            });
                        } catch(e) { showToast(t('error_stock_deduct') || 'Error al descontar insumo', 'warning'); }
                    }
                    await loadInventoryCache();
                }
            }
            // Persist reverted entries: update or delete original registros
            if (_revertedEntries.length) {
                const regUpdates = new Map();
                _revertedEntries.forEach(rv => {
                    if (!regUpdates.has(rv.registroId)) {
                        const orig = _registros.find(r => r.id === rv.registroId);
                        if (orig) regUpdates.set(rv.registroId, JSON.parse(JSON.stringify(orig.datos)));
                    }
                });
                for (const [regId, regDatos] of regUpdates) {
                    try {
                        // Check if registro has any medications left after reverts
                        const hasMeds = (regDatos.medicamentos_seleccionados||[]).length
                                      + (regDatos.medicamentos_extra||[]).length
                                      + (regDatos.medicamentos_no_administrados||[]).length;
                        if (hasMeds > 0) {
                            const updatedRecord = await api(API_URL, {
                                method: 'PUT', headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({ id: regId, datos: regDatos })
                            });
                            if (typeof cdApplyRecordMutation === 'function') cdApplyRecordMutation(updatedRecord, { broadcast: true });
                        } else {
                            // Delete empty medication records
                            await api(`${API_URL}?id=${regId}`, { method: 'DELETE' });
                            if (typeof cdMarkRecordsDirty === 'function') cdMarkRecordsDirty({ broadcast: true });
                        }
                    } catch(e) {}
                }
                if (onlyRevert) showToast(t('meds_reverted_pending'), 'success');
                _revertedEntries = [];
            }
            clearEditState();
            _medFormDirty = false;
            form.reset();
            $$('.selected', form).forEach(b => b.classList.remove('selected'));
            $$('.cd-slider', form).forEach(s => delete s.dataset.touched);
            $$('.cd-med-item.checked').forEach(b => { b.classList.remove('checked'); const qv = b.querySelector('.cd-med-qty-val'); if (qv) { qv.dataset.qty = '1'; qv.textContent = '1'; } });
            $$('.cd-med-item.skipped').forEach(b => { b.classList.remove('skipped'); const r = b.querySelector('.cd-med-skip-reason'); if (r) r.remove(); });
            $$('.cd-vital-card', form).forEach(c => { c.classList.add('cd-vital-off'); const t = c.querySelector('.cd-vital-toggle'); if (t) t.checked = false; });
            $$('.cd-vital-photo-preview', form).forEach(p => p.innerHTML = '');
            $$('.cd-vital-photo-input', form).forEach(i => i.value = '');
            const extraList = $('#cdMedExtraList'); if (extraList) extraList.innerHTML = '';
            const isw = $('#cdInvSearchWrap'); if (isw) isw.style.display = 'none';
            const ssw = $('#cdSosInputWrap'); if (ssw) ssw.style.display = 'none';
            clearInsumoPicker(form);
            _photoAntes = null; _photoDespues = null;
            if (typeof clearCareDraft === 'function') clearCareDraft();
            showView('viewDashboard', true, false);
            loadDashboard();
            if (typeof loadRecords === 'function' && document.getElementById('viewRecords')?.classList.contains('active')) loadRecords({ silent: true });
        } catch(e) {}
        finally { btnReset(btn); }
    });
});
// Reset dirty flag after all form setup (browser autofill or DOM injection may have triggered change events)
_careFormDirty = false;

// ═══════════════════════════════════════════════
// EVENT-TIME-GROUP DATE CHIP
// ───────────────────────────────────────────────
// Inserts a small badge inside every `.cd-form-group.cd-event-time-group`
// indicating which day this record will be saved under, formatted with
// APP_DATE_FMT. For most forms this is the date currently selected in
// the global date nav (`_fecha`). For viewFormSueno the chip follows
// `cdSleepStartDayToggle` (today / día anterior) since sleep is anchored
// to its start day.
// ═══════════════════════════════════════════════
(function () {
    const ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';

    function _ensureChip(group) {
        let chip = group.querySelector(':scope > .cd-event-date-chip');
        if (!chip) {
            chip = document.createElement('span');
            chip.className = 'cd-event-date-chip';
            group.appendChild(chip);
        }
        return chip;
    }

    function _updateChipForView(viewId) {
        const view = document.getElementById(viewId);
        if (!view) return;
        const groups = view.querySelectorAll('.cd-form-group.cd-event-time-group');
        if (!groups.length) return;

        const today = (typeof nowInTz === 'function') ? nowInTz().date : null;
        let dateStr = (typeof _fecha !== 'undefined' && _fecha) ? _fecha : today;

        // Sueño: respect cdSleepStartDayToggle (the record is anchored to start day)
        if (viewId === 'viewFormSueno' && typeof window._sleepFecha === 'function') {
            try { dateStr = window._sleepFecha() || dateStr; } catch (_) {}
        }

        let when = 'other';
        let prefix = '';
        if (today && dateStr === today) {
            when = 'today';
            // En el dia en curso mostramos el nombre del dia (Lunes, Martes, ...) en vez de "Hoy"
            try {
                const [y, m, d] = dateStr.split('-').map(Number);
                const dt = new Date(y, m - 1, d);
                const dayName = dt.toLocaleDateString('es-MX', { weekday: 'long' });
                prefix = dayName.charAt(0).toUpperCase() + dayName.slice(1);
            } catch (_) { prefix = 'Hoy'; }
        } else if (today) {
            // Compute yesterday in TZ for "Ayer / Día anterior" label
            const [y, m, d] = today.split('-').map(Number);
            const yest = new Date(y, m - 1, d);
            yest.setDate(yest.getDate() - 1);
            const yyStr = `${yest.getFullYear()}-${String(yest.getMonth()+1).padStart(2,'0')}-${String(yest.getDate()).padStart(2,'0')}`;
            if (dateStr === yyStr) {
                when = 'prev';
                prefix = 'Ayer';
            }
        }

        const formatted = (typeof fmtDate === 'function') ? fmtDate(dateStr) : dateStr;
        const label = prefix ? `${prefix} · ${formatted}` : formatted;

        groups.forEach(g => {
            const chip = _ensureChip(g);
            chip.dataset.when = when;
            chip.innerHTML = ICON + '<span>' + label + '</span>';
        });
    }

    function _updateAll() {
        document.querySelectorAll('.cd-view').forEach(v => {
            if (v.id && v.id.startsWith('viewForm')) _updateChipForView(v.id);
        });
    }

    // Public hooks
    window._updateEventDateChip = _updateChipForView;
    window._updateAllEventDateChips = _updateAll;

    // Initial pass once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _updateAll, { once: true });
    } else {
        _updateAll();
    }
})();

