// cd-dashboard.js — Dashboard, timeline, badges, stats
// Extracted from cuidados.php (lines 5150)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════
async function loadDashboard(silent = false) {
    if (!_residenteId) return;
    // Show skeletons only on non-silent (manual) loads
    const timeline = $('#cdTimeline');
    const timelineEmpty = $('#cdTimelineEmpty');
    if (!silent) {
        if (timeline && timelineEmpty) timelineEmpty.style.display = 'none';
        if (timeline) {
            $$('.cd-tl-item', timeline).forEach(el => el.remove());
            const skEl = document.createElement('div');
            skEl.className = 'cd-tl-skeleton';
            skEl.innerHTML = skeleton(3);
            timeline.prepend(skEl);
        }
        // Ghost skeleton for RxTracker
        const rxTracker = $('#cdRxTracker');
        const rxPills = $('#cdRxTrackerPills');
        if (rxTracker && rxPills) {
            rxTracker.style.display = '';
            rxPills.innerHTML = `<div class="cd-rx-tracker-col">${rxGhostPills(3)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div>`;
        }
        // v1.51.53: Clear stale badges from previous residente while the new
        // dashboard data is loading. Otherwise, switching residente from the
        // Residentes card list (or sidebar) shows the previous residente's
        // badges (⚠ N med pending, Nh heces, ⚠ N signos, cat counts) for the
        // duration of the fetch — confusing and looks like the click "didn't
        // work". Reset state + visuals so the dashboard shows a clean slate
        // until the new data arrives.
        _counts = {};
        _registros = [];
        _bitacoraEvents = [];
        window.__cdServerBadges = null;
        $$('[data-cat-count]').forEach(b => { b.textContent = ''; b.classList.remove('visible'); });
        const _medBadge = $('#cdMedPendingBadge');
        if (_medBadge) { _medBadge.classList.remove('visible'); _medBadge.textContent = ''; _medBadge.title = ''; }
        const _hecesBadge = $('#cdHecesBadge');
        if (_hecesBadge) { _hecesBadge.classList.remove('visible'); _hecesBadge.textContent = ''; _hecesBadge.title = ''; }
        const _signosBadge = $('#cdSignosBadge');
        if (_signosBadge) { _signosBadge.classList.remove('visible'); _signosBadge.textContent = ''; _signosBadge.title = ''; }
        const _suenoBadge = $('#cdSuenoBadge');
        if (_suenoBadge) { _suenoBadge.classList.remove('visible'); _suenoBadge.textContent = ''; _suenoBadge.title = ''; }
        const _nmBadge = document.getElementById('cdNmBadge');
        if (_nmBadge) { _nmBadge.classList.remove('show'); _nmBadge.textContent = ''; }
    }
    try {
        // Single batched API call — replaces 5 separate requests
        const data = await api(`${API_URL}?dashboard=1&residente_id=${_residenteId}&fecha=${_fecha}`);

        // Server-authoritative badge counts (single source of truth shared
        // with /api/residentes.php so the dashboard cards and the residentes
        // list always show identical numbers, regardless of client tz vs
        // server tz drift). See api/_badge_helpers.php.
        window.__cdServerBadges = data.badges || null;

        // 1. Registros + counts
        _registros = data.registros || [];
        window.__cdDashboardRecordsKey = `${_residenteId}|${_fecha}`;
        window.__cdDashboardFreshAt = Date.now();
        if (typeof cdSeedRecordsFromDashboard === 'function') cdSeedRecordsFromDashboard();
        _counts = data.counts || {};
        renderCategoryBadges();

        // 2. Bitacora
        _bitacoraEvents = (data.bitacora || []).map(e => ({
            ...e,
            source: 'bitacora',
            categoria: e.tipo
        }));

        // 3. Inventory cache
        const invRaw = data.inventario || {};
        const allItems = Array.isArray(invRaw) ? invRaw : (invRaw.items || []);
        // Incluir medicamentos Y suplementos: ambos se administran como rx en el Registro de Medicación.
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

        // 4. Notas
        const notasData = data.notas || {};
        const notas = notasData.notas || [];
        const notasCount = notasData.count || 0;
        $('#cdNotesCount').textContent = `(${notasCount})`;
        const notesBadge = $('[data-cat-count="notas"]');
        if (notesBadge) { notesBadge.textContent = notasCount; notesBadge.classList.toggle('visible', notasCount > 0); }
        _notas = notas;
        renderNotes(notas);
        // Urgent/important note indicator on notas tab button (also on load)
        const notasBtn = $('.cd-cat-btn[data-cat="notas"]');
        const notasBadge = $('[data-cat-count="notas"]');
        if (notasBtn) {
            const hasUrgent = notas.some(n => n.prioridad === 'urgente' || n.prioridad === 'importante');
            notasBtn.classList.toggle('cd-cat-btn--alert', hasUrgent);
            if (notasBadge) notasBadge.classList.toggle('cd-cat-badge--alert', hasUrgent);
        }

        // 4b. Nota médica vigente
        _notaMedico = data.nota_medico || null;
        const nmBadge = document.getElementById('cdNmBadge');
        if (nmBadge) {
            nmBadge.classList.toggle('show', !!_notaMedico);
            nmBadge.textContent = _notaMedico ? '✓' : '';
        }
        // R86: breathing-blue glow on the medico card while a doctor note is active
        const nmBtn = document.querySelector('.cd-cat-btn--notas-medico');
        if (nmBtn) nmBtn.classList.toggle('cd-cat-btn--breathing-blue', !!_notaMedico);
        // Fetch doctor alerts count for badge
        if (typeof nmLoadAlertas === 'function') {
            nmLoadAlertas();
        } else if (_residenteId) {
            try {
                const alertas = await api(`${API_URL}?alertas_medico=1&residente_id=${_residenteId}`);
                const pendCount = Array.isArray(alertas) ? alertas.filter(a => !a.visto_por).length : 0;
                if (nmBadge && pendCount > 0) {
                    nmBadge.classList.add('show');
                    nmBadge.textContent = _notaMedico ? `✓ +${pendCount}⚠` : `${pendCount}⚠`;
                }
            } catch {}
        }
        if (document.getElementById('viewFormNotasMedico')?.classList.contains('active')) {
            renderNotasMedico();
        }

        // 5b. Heces hours badge
        if (data.ultima_heces) {
            renderHecesBadge(data.ultima_heces);
        } else {
            renderHecesBadge(null);
        }

        // 5c. Signos vitales out-of-range badge
        _lastSignos = data.ultimos_signos || null;
        renderSignosBadge(_lastSignos);

        // 5d. Sueño pendiente badge
        _suenoPendiente = data.sueno_pendiente || null;
        renderSuenoBadge(_suenoPendiente);

        // 5. Resident info (cache + render)
        if (data.residente) {
            _resDataCache[_residenteId] = data.residente;
            _resData = data.residente;
            renderResidentInfo(_resData);
        }

        renderTimeline();
        renderRxTracker();
        checkCareDraft();
    } catch(e) { /* handled by api() */ }
}

function renderCategoryBadges() {
    // Iterar sobre todos los badges presentes en el DOM (en vez de
    // CAT_LABELS, que omite categorías "extra" como incidente, notas y
    // notas_medico). Esto garantiza que cualquier `<span data-cat-count="X">`
    // del dashboard se actualice si el backend reporta `counts[X]`.
    $$('[data-cat-count]').forEach(badge => {
        const cat = badge.getAttribute('data-cat-count');
        if (!cat) return;
        const n = _counts[cat] || 0;
        badge.textContent = n;
        badge.classList.toggle('visible', n > 0);
    });
}

function checkCareDraft() {
    // Remove any previous draft indicator
    $$('.cd-cat-btn .cd-draft-dot').forEach(d => d.remove());
    try {
        const raw = localStorage.getItem('geriapp_care_draft');
        if (!raw) return;
        const draft = JSON.parse(raw);
        // Only show if same resident and less than 12 hours old
        if (draft.residenteId !== _residenteId) return;
        if (Date.now() - draft.ts > 12 * 3600000) { localStorage.removeItem('geriapp_care_draft'); return; }
        const catBtn = $(`.cd-cat-btn[data-cat="${draft.categoria}"]`);
        if (catBtn) {
            const dot = document.createElement('span');
            dot.className = 'cd-draft-dot';
            dot.title = t('draft_pending') || 'Registro sin guardar';
            catBtn.appendChild(dot);
        }
    } catch {}
}

function clearCareDraft() {
    localStorage.removeItem('geriapp_care_draft');
    $$('.cd-cat-btn .cd-draft-dot').forEach(d => d.remove());
}
window.clearCareDraft = clearCareDraft;

function renderHecesBadge(ultimaHeces) {
    const badge = $('#cdHecesBadge');
    if (!badge) return;
    const card = badge.closest('.cd-cat-btn');
    const setBreath = on => card && card.classList.toggle('cd-cat-btn--breathing-red', !!on);
    const poopImg = '<img src="assets/icons/poop.png" alt="">';
    if (!ultimaHeces) {
        badge.innerHTML = poopImg + '<span>?</span>';
        badge.className = 'cd-cat-badge cd-heces-badge cd-heces-badge--danger visible';
        badge.title = t('heces_sin_registro') || 'Sin registro de heces';
        setBreath(true);
        return;
    }
    // Prefer server-computed hours (uses server tz, matches /api/residentes.php).
    // Fall back to client-side calc only if server didn't provide a value.
    let diffHrs;
    const sb = window.__cdServerBadges;
    if (sb && typeof sb.heces_horas === 'number') {
        diffHrs = sb.heces_horas;
    } else {
        const fechaHora = ultimaHeces.fecha + 'T' + (ultimaHeces.hora || '00:00') + ':00';
        const then = new Date(fechaHora);
        const now = new Date();
        diffHrs = Math.floor((now - then) / 3600000);
    }
    badge.innerHTML = poopImg + '<span>' + diffHrs + 'h</span>';
    badge.title = (t('heces_horas') || 'Horas desde última evacuación') + ': ' + diffHrs + 'h';
    const danger = diffHrs >= 24;
    const warn = diffHrs >= 12 && diffHrs < 24;
    badge.className = 'cd-cat-badge cd-heces-badge visible'
        + (danger ? ' cd-heces-badge--danger' : '')
        + (warn   ? ' cd-heces-badge--warn'   : '');
    setBreath(danger);
}

function renderSignosBadge(datos) {
    const badge = $('#cdSignosBadge');
    if (!badge) return;
    if (!datos) { badge.className = 'cd-cat-badge cd-signos-badge'; return; }
    // Normalize old rows format to flat keys
    let d = datos;
    if (!d.pa_sistolica && d.rows && d.rows.length) {
        const row = d.rows[0];
        d = { pa_sistolica: row.sys, pa_diastolica: row.dia, frecuencia_cardiaca: row.fc, frecuencia_respiratoria: row.fr, temperatura: row.temp, spo2: row.spo2, glucosa: row.glucosa };
    }
    const ranges = {
        temperatura:             { normal: [36, 37.5],  warn: [35.5, 38] },
        frecuencia_respiratoria: { normal: [12, 20],    warn: [10, 25] },
        frecuencia_cardiaca:     { normal: [60, 100],   warn: [50, 120] },
        pa_sistolica:            { normal: [90, 140],   warn: [80, 160] },
        pa_diastolica:           { normal: [60, 90],    warn: [50, 100] },
        spo2:                    { normal: [95, 100],   warn: [90, 100] },
        glucosa:                 { normal: [70, 140],   warn: [54, 180] },
    };
    let worst = 'normal';
    let outCount = 0;
    for (const [key, r] of Object.entries(ranges)) {
        const v = parseFloat(d[key]);
        if (!v && v !== 0) continue;
        if (v < r.warn[0] || v > r.warn[1]) { worst = 'danger'; outCount++; }
        else if (v < r.normal[0] || v > r.normal[1]) { if (worst !== 'danger') worst = 'warn'; outCount++; }
    }
    if (outCount === 0) {
        badge.className = 'cd-cat-badge cd-signos-badge';
        return;
    }
    badge.textContent = '⚠ ' + outCount;
    badge.title = t('signos_fuera_rango') || 'Signos vitales fuera de rango';
    badge.className = 'cd-cat-badge cd-signos-badge visible'
        + (worst === 'danger' ? '' : ' cd-signos-badge--warn');
}

function renderSuenoBadge(pending) {
    const badge = $('#cdSuenoBadge');
    if (!badge) return;
    if (!pending) {
        badge.className = 'cd-cat-badge cd-sueno-badge';
        badge.textContent = '';
        return;
    }
    const d = pending.datos || {};
    const since = d.hora_inicio || '?';
    // Calculate elapsed time using full date + time to avoid cross-midnight / timezone bugs
    let elapsedTxt = since;
    if (since !== '?') {
        const [h, m] = since.split(':').map(Number);
        const now = nowInTz();
        const startDate = pending.fecha || now.date;
        const [sy, smo, sd] = startDate.split('-').map(Number);
        const [ny, nmo, nd] = now.date.split('-').map(Number);
        const [nh, nm] = now.time.split(':').map(Number);
        const startMs = new Date(sy, smo - 1, sd, h, m).getTime();
        const nowMs   = new Date(ny, nmo - 1, nd, nh, nm).getTime();
        let diffMin = Math.floor((nowMs - startMs) / 60000);
        if (diffMin < 0) diffMin = 0;
        const hrs = Math.floor(diffMin / 60);
        const mins = diffMin % 60;
        elapsedTxt = hrs > 0 ? hrs + 'h ' + mins + 'm' : mins + 'm';
    }
    badge.innerHTML = '<img src="assets/icons/moon-zzz.png" style="width:14px;height:14px;object-fit:contain;vertical-align:-2px"> ' + elapsedTxt;
    badge.title = (t('sleep_pending_hours') || 'Lleva durmiendo') + ' ' + elapsedTxt + ' (' + (t('sleep_pending_since') || 'Dormido desde') + ' ' + since + ')';
    badge.className = 'cd-cat-badge cd-sueno-badge visible';
}

const _SIGNOS_LABELS = {
    temperatura: 'vital_temp', frecuencia_respiratoria: 'vital_resp',
    frecuencia_cardiaca: 'vital_heart', pa_sistolica: 'vital_systolic',
    pa_diastolica: 'vital_diastolic', spo2: 'vital_spo2', glucosa: 'vital_glucose'
};
const _SIGNOS_UNITS = {
    temperatura: '°C', frecuencia_respiratoria: 'rpm', frecuencia_cardiaca: 'bpm',
    pa_sistolica: 'mmHg', pa_diastolica: 'mmHg', spo2: '%', glucosa: 'mg/dL'
};
function renderSignosAlert() {
    const el = $('#cdSignosAlert');
    if (!el) return;
    if (!_lastSignos) { el.style.display = 'none'; return; }
    // Normalize old rows format to flat keys
    let d = _lastSignos;
    if (!d.pa_sistolica && d.rows && d.rows.length) {
        const row = d.rows[0];
        d = { pa_sistolica: row.sys, pa_diastolica: row.dia, frecuencia_cardiaca: row.fc, frecuencia_respiratoria: row.fr, temperatura: row.temp, spo2: row.spo2, glucosa: row.glucosa, _fecha: _lastSignos._fecha, _hora: _lastSignos._hora };
    }
    const ranges = {
        temperatura:             { normal: [36, 37.5],  warn: [35.5, 38] },
        frecuencia_respiratoria: { normal: [12, 20],    warn: [10, 25] },
        frecuencia_cardiaca:     { normal: [60, 100],   warn: [50, 120] },
        pa_sistolica:            { normal: [90, 140],   warn: [80, 160] },
        pa_diastolica:           { normal: [60, 90],    warn: [50, 100] },
        spo2:                    { normal: [95, 100],   warn: [90, 100] },
        glucosa:                 { normal: [70, 140],   warn: [54, 180] },
    };
    const items = [];
    for (const [key, r] of Object.entries(ranges)) {
        const v = parseFloat(d[key]);
        if (!v && v !== 0) continue;
        let level = null;
        if (v < r.warn[0] || v > r.warn[1]) level = 'danger';
        else if (v < r.normal[0] || v > r.normal[1]) level = 'warn';
        if (level) items.push({ key, v, level, label: t(_SIGNOS_LABELS[key]) || key, unit: _SIGNOS_UNITS[key] });
    }
    if (!items.length) { el.style.display = 'none'; return; }
    const hasDanger = items.some(i => i.level === 'danger');
    el.className = 'cd-signos-alert' + (hasDanger ? ' cd-signos-alert--danger' : ' cd-signos-alert--warn');
    el.style.display = '';
    // Build date/time subtitle
    let fechaStr = '';
    if (_lastSignos._fecha) {
        const f = _lastSignos._fecha;
        const h = _lastSignos._hora || '';
        const d = new Date(f + 'T' + (h || '00:00'));
        const hoy = nowInTz().date;
        const dayLabel = f === hoy ? 'Hoy' : formatDateShort(f);
        fechaStr = ' <span class="cd-signos-alert-date">' + esc(dayLabel + (h ? ' ' + h : '')) + '</span>';
    }
    el.innerHTML = '<div class="cd-signos-alert-title">'
        + '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> '
        + (t('signos_alerta_titulo') || 'Última lectura fuera de rango') + fechaStr + '</div>'
        + '<ul class="cd-signos-alert-list">' + items.map(i =>
            `<li class="cd-signos-alert-item cd-signos-alert-item--${i.level}">`
            + `<span class="cd-signos-alert-name">${esc(i.label)}</span> `
            + `<span class="cd-signos-alert-val">${i.v}${i.unit}</span>`
            + `</li>`
        ).join('') + '</ul>';
}

function renderTimeline() {
    const tl = $('#cdTimeline');
    const empty = $('#cdTimelineEmpty');
    const countEl = $('#cdTimelineCount');
    if (!tl || !empty || !countEl) return;

    $$('.cd-tl-skeleton', tl).forEach(el => el.remove());
    $$('.cd-tl-item', tl).forEach(el => el.remove());

    // Map bitacora tipos to cuidado category keys
    const BIT_CAT_MAP = {
        medicamento: 'medicacion', vitales: 'signos_vitales', sueno: 'sueno',
        cuidados: 'higiene', actividad: 'terapia', incidente: 'comportamiento'
    };

    let allEvents = [..._registros.map(r => ({...r, source:'cuidado'})), ..._bitacoraEvents];

    // Resolve catKey for each event (needed for filtering)
    allEvents.forEach(r => {
        const isCuidado = r.source === 'cuidado';
        let resolvedCatKey = null;
        if (!isCuidado && r.contenido) {
            let bitParsed = null;
            try { bitParsed = JSON.parse(r.contenido); } catch(e) {}
            resolvedCatKey = BIT_CAT_MAP[r.categoria] || null;
            if (bitParsed?.cat) {
                const catLower = bitParsed.cat.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const matchMap = {sueno:'sueno',alimentacion:'alimentacion',medicacion:'medicacion',higiene:'higiene',terapia:'terapia',movilidad:'movilidad',eliminacion:'eliminacion',comportamiento:'comportamiento','signos vitales':'signos_vitales',vitales:'signos_vitales'};
                if (matchMap[catLower]) resolvedCatKey = matchMap[catLower];
            }
            r._bitParsed = bitParsed;
        }
        r._catKey = isCuidado ? r.categoria : (resolvedCatKey || 'bitacora');
        r._catLabel = isCuidado ? (CAT_LABELS[r.categoria]||r.categoria) : (resolvedCatKey ? (CAT_LABELS[resolvedCatKey]||r.categoria) : (r.categoria||'Bitácora'));
    });

    // Populate category dropdown with ALL possible categories
    const catSelect = $('#cdTlCatFilter');
    if (catSelect) {
        const currentVal = catSelect.value;
        const ALL_CATS = ['sueno','alimentacion','medicacion','higiene','terapia','movilidad','eliminacion','comportamiento','signos_vitales','bitacora'];
        const catsPresent = new Set(allEvents.map(e => e._catKey));
        catSelect.innerHTML = '<option value="">' + t('tl_all_categories') + '</option>';
        ALL_CATS.sort((a,b) => (CAT_LABELS[a]||a).localeCompare(CAT_LABELS[b]||b));
        ALL_CATS.forEach(ck => {
            const opt = document.createElement('option');
            opt.value = ck;
            opt.textContent = CAT_LABELS[ck] || ck;
            if (!catsPresent.has(ck)) opt.disabled = true;
            catSelect.appendChild(opt);
        });
        catSelect.value = currentVal || '';
    }

    // Sort
    allEvents.sort((a,b) => _tlSortAsc
        ? (a.hora||'').localeCompare(b.hora||'')
        : (b.hora||'').localeCompare(a.hora||''));

    // Category filter
    if (_tlCatFilter) {
        allEvents = allEvents.filter(r => r._catKey === _tlCatFilter);
    }

    // Text search
    if (_tlSearchText) {
        const q = _tlSearchText.toLowerCase();
        allEvents = allEvents.filter(r => {
            const haystack = [
                r.hora, r._catLabel, r.observaciones,
                r.usuario_nombre, r.contenido,
                JSON.stringify(r.datos || {})
            ].filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(q);
        });
    }

    const totalAll = _registros.length + _bitacoraEvents.length;
    const shown = allEvents.length;
    empty.style.display = shown ? 'none' : 'block';
    countEl.textContent = (_tlCatFilter || _tlSearchText)
        ? `${shown} de ${totalAll} eventos`
        : `${totalAll} eventos`;

    allEvents.forEach(r => {
        const div = document.createElement('div');
        div.className = 'cd-tl-item';
        div.style.cursor = 'pointer';
        if (r.source === 'cuidado') {
            div.dataset.registroId = r.id;
        }
        const isCuidado = r.source === 'cuidado';
        const catKey = r._catKey;
        const catLabel = r._catLabel;

        // Build summary
        let summaryHtml;
        if (isCuidado) {
            summaryHtml = buildSummary(r);
        } else if (r._bitParsed) {
            summaryHtml = buildBitacoraSummary(r._bitParsed, r.categoria, catKey !== 'bitacora' ? catKey : null);
        } else {
            summaryHtml = esc(r.contenido || '');
        }

        // Extract creation time HH:mm in configured timezone
        const creaHora = (() => {
            if (r.creado_at) {
                try {
                    const d = new Date(r.creado_at.replace(' ', 'T') + (r.creado_at.includes('Z') || r.creado_at.includes('+') ? '' : 'Z'));
                    if (!isNaN(d)) return d.toLocaleTimeString('es-MX', {timeZone: APP_TZ, hour:'2-digit', minute:'2-digit', hour12:false});
                } catch(e) {}
                const m = String(r.creado_at).match(/(\d{2}:\d{2})/);
                return m ? m[1] : '';
            }
            return r.hora || '';
        })();

        div.innerHTML = `
            <div class="cd-tl-icon cd-tl-icon--${catKey}">
                ${tlIcon(catKey)}
            </div>
            <div class="cd-tl-card">
                <span class="cd-tl-time">${esc(r.hora)}</span>
                <div class="cd-tl-body">
                    <div class="cd-tl-card-head">
                        <span class="cd-tl-cat">${esc(catLabel)}</span>
                        ${r.source === 'bitacora' ? '<span class="cd-tl-badge-bit">Bitácora</span>' : ''}
                        ${r.usuario_nombre ? `<span class="cd-tl-user">${esc(shortName(r.usuario_nombre))}${creaHora ? ' <span style="color:var(--cd-text-muted);font-weight:400">⚬ '+esc(creaHora)+'</span>' : ''}${r.verificacion_biometrica == 1 ? ' <img src="assets/icons/fingerprint-check-circle.png" alt="Firma biométrica" style="height:16px;width:16px;vertical-align:middle;margin-left:4px">' : ''}</span>` : (creaHora ? `<span class="cd-tl-user" style="color:var(--cd-text-muted);font-weight:400">${esc(creaHora)}${r.verificacion_biometrica == 1 ? ' <img src="assets/icons/fingerprint-check-circle.png" alt="Firma biométrica" style="height:16px;width:16px;vertical-align:middle;margin-left:4px">' : ''}</span>` : (r.verificacion_biometrica == 1 ? `<span class="cd-tl-user"><img src="assets/icons/fingerprint-check-circle.png" alt="Firma biométrica" style="height:16px;width:16px;vertical-align:middle"></span>` : ''))}
                    </div>
                    <p class="cd-tl-summary">${summaryHtml}${(r.source==='cuidado' && !r.datos?.fotos_vitales && (r.datos?.foto_antes || r.datos?.foto_despues)) ? ' <span class="cd-tl-photo-badge" title="Con evidencia fotográfica"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>' : ''}</p>
                    ${r.observaciones ? `<p class="cd-tl-obs">${esc(r.observaciones)}</p>` : (r._bitParsed?.motivo ? `<p class="cd-tl-obs">${esc(r._bitParsed.motivo)}</p>` : '')}
                </div>
            </div>`;
        div.addEventListener('click', () => {
            openDetailSidebar(r);
        });
        tl.insertBefore(div, empty);
    });
}

// Build formatted summary for bitacora JSON content
function buildBitacoraSummary(data, tipo, resolvedCat) {
    // Vitales format: {rows:[{time,sys,dia,fc,fr,temp,spo2,...}]}
    if (data.rows && Array.isArray(data.rows) && data.rows.length) {
        const row = data.rows[0];
        const parts = [];
        if (row.sys || row.dia) parts.push(`PA: ${row.sys||'?'}/${row.dia||'?'}`);
        if (row.fc) parts.push(`FC: ${row.fc}`);
        if (row.fr) parts.push(`FR: ${row.fr}`);
        if (row.temp) parts.push(`Temp: ${row.temp}°C`);
        if (row.spo2) parts.push(`SpO2: ${row.spo2}%`);
        return esc(parts.join(', '));
    }
    // Medicación format: {cat,text,nombre,dosis,via,motivo,...}
    if (data.nombre) {
        const parts = [data.nombre];
        if (data.dosis) parts.push(data.dosis);
        if (data.via) parts.push(data.via);
        if (data.motivo) parts.push(data.motivo);
        return esc(parts.join(' · '));
    }
    // Generic text field
    if (data.text) return esc(data.text);
    if (data.cat && typeof data === 'object') {
        // Try to build from any recognizable fields
        const skip = ['cat','startTime','endTime','durMins','adhoc'];
        const parts = Object.entries(data).filter(([k,v]) => v && !skip.includes(k)).map(([k,v]) => typeof v === 'string' || typeof v === 'number' ? String(v) : null).filter(Boolean);
        if (parts.length) return esc(parts.join(' · '));
    }
    return esc(JSON.stringify(data).substring(0, 100));
}

function buildSummary(r) {
    const d = r.datos || {};
    switch(r.categoria) {
        case 'sueno': {
            // Pending sleep record
            if (d.pendiente) {
                const since = d.hora_inicio || '?';
                return `<span class="cd-tl-pending-tag">${esc(t('sleep_pending_tag') || '⏳ Pendiente')}</span> ${esc((t('sleep_pending_summary') || 'Pendiente — dormido desde') + ' ' + since)}`;
            }
            // Support old format: {calidad:"Buena",horas:"7.5",...} and new {horas,calidad_pct,...}
            const hrsRaw = (d.horas != null && d.horas !== '') ? d.horas : (d.duracion != null && d.duracion !== '' ? d.duracion : null);
            let hrsLabel;
            if (hrsRaw == null) {
                hrsLabel = '?';
            } else {
                const hrsNum = parseFloat(hrsRaw);
                if (!isNaN(hrsNum) && hrsNum < 1) {
                    // Slider rounds to 0.5 steps; compute actual minutes from times for better precision
                    if (hrsNum === 0 && d.hora_inicio && d.hora_fin && window._sleepDiffMin) {
                        const mins = window._sleepDiffMin(d.hora_inicio, d.hora_fin);
                        hrsLabel = mins + ' min';
                    } else {
                        hrsLabel = Math.round(hrsNum * 60) + ' min';
                    }
                } else {
                    hrsLabel = hrsRaw + ' horas';
                }
            }
            const calLabels = {0:'Muy mala',25:'Mala',50:'Regular',75:'Buena',100:'Excelente'};
            let cal = d.calidad_pct != null ? (calLabels[d.calidad_pct] || `${d.calidad_pct}%`) : (d.calidad || '?');
            const time = d.hora_inicio ? ` (${d.hora_inicio}—${d.hora_fin||''})` : (d.startTime ? ` (${d.startTime}—${d.endTime||''})` : '');
            return esc(`${hrsLabel} — ${cal}${time}`);
        }
        case 'alimentacion': return esc(`${d.tipo_comida||d.tipo||'?'} (${d.ingesta_pct!=null?d.ingesta_pct+'%':(d.porcentaje||'?')})`);
        case 'medicacion': {
            // New format: {medicamentos_seleccionados:[...], medicamentos_extra:[...], med_cantidades:{...}}
            const meds = [...(d.medicamentos_seleccionados||[]),...(d.medicamentos_extra||[])];
            const noAdmin = d.medicamentos_no_administrados || [];
            if (meds.length || noAdmin.length) {
                const rxArr = RX_BY_RES[_residenteId] || [];
                const cantidades = d.med_cantidades || {};
                const lines = meds.map(name => {
                    const rx = rxArr.find(r => r.nombre === name);
                    const parts = [name];
                    if (rx?.dosis) parts.push(rx.dosis);
                    const qty = cantidades[name];
                    if (qty && qty !== 1) parts.push(`×${qty}`);
                    if (rx?.via) parts.push(rx.via);
                    return `<span class="cd-tl-med-line">${esc(parts.join(' · '))}</span>`;
                });
                const skipLines = noAdmin.map(m => `<span class="cd-tl-med-line" style="color:var(--cd-danger);text-decoration:line-through">${esc(m.nombre)}${m.razon ? ' — '+esc(m.razon) : ''}</span>`);
                const allLines = [...lines, ...skipLines];
                if (allLines.length === 1 && !noAdmin.length) return esc(meds[0]);
                return allLines.join('');
            }
            // Old format: {nombre:"...", dosis:"...", via:"...", motivo:"..."}
            if (d.nombre) {
                let parts = [d.nombre];
                if (d.dosis) parts.push(d.dosis);
                if (d.via) parts.push(d.via);
                return esc(parts.join(' · '));
            }
            // text field fallback
            if (d.text) return esc(d.text);
            return esc('Sin medicamentos');
        }
        case 'higiene': return esc(`${d.tipo_higiene||d.tipo||'?'} — Asistencia: ${d.asistencia||'?'}`);
        case 'terapia': return esc(`${d.tipo_terapia||d.tipo||'?'} (${d.duracion||'?'} min)`);
        case 'movilidad': return esc(`${d.nivel_asistencia||'?'} · ${d.tipo_actividad||d.tipo||'?'} (${d.duracion||'?'} min)`);
        case 'eliminacion': {
            let s = esc(`${d.tipo_eliminacion||d.tipo||'?'} · ${d.color_aspecto||d.aspecto||'?'} · ${d.cantidad||'?'}`);
            if (d.cambio_panal === 'Sí') s += ' · <svg class="cd-tl-icon-inline" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6 2 10c0 2.5 1.5 5 3 7l1.5 3c.3.6.9 1 1.5 1h8c.6 0 1.2-.4 1.5-1L19 17c1.5-2 3-4.5 3-7 0-4-4.48-8-10-8z"/><path d="M8 13h8"/></svg> Cambio de pañal';
            return s;
        }
        case 'comportamiento': return esc(`${d.estado_animo||d.animo||'?'} · ${d.incidentes||'Sin incidentes'}`);
        case 'signos_vitales': {
            // Range check helper for timeline
            const _vr = {
                temperatura:[36,37.5,35.5,38], frecuencia_respiratoria:[12,20,10,25],
                frecuencia_cardiaca:[60,100,50,120], pa_sistolica:[90,140,80,160],
                pa_diastolica:[60,90,50,100], spo2:[95,100,90,100], glucosa:[70,140,54,180]
            };
            const _photoSvg = '<svg class="cd-tl-photo-inline" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>';
            const _vc = (k,v) => { if(v==null||v==='')return ''; v=parseFloat(v); if(isNaN(v))return ''; const rg=_vr[k]; if(!rg)return ''; if(v<rg[2]||v>rg[3])return 'danger'; if(v<rg[0]||v>rg[1])return 'warn'; return ''; };
            const _vCell = (rangeKey, rawVal, label, dispVal, unit) => {
                if(rawVal==null||rawVal==='')return '';
                const cls = _vc(rangeKey, rawVal);
                const photo = (d.fotos_vitales && d.fotos_vitales[rangeKey]) ? _photoSvg : '';
                const valCls = cls ? ` cd-vital-tl cd-vital-tl--${cls}` : '';
                return `<div class="cd-tl-vital-cell"><span class="cd-tl-vital-lbl">${label}</span><span class="cd-tl-vital-val${valCls}">${esc(String(dispVal))}${unit?'<small>'+esc(unit)+'</small>':''}${photo}</span></div>`;
            };
            // New format
            const vitalKeys = ['pa_sistolica','frecuencia_cardiaca','frecuencia_respiratoria','temperatura','spo2','glucosa','peso'];
            const hasNewFormat = vitalKeys.some(k => d[k] != null);
            if (hasNewFormat) {
                let cells = [];
                if (d.pa_sistolica != null) cells.push(_vCell('pa_sistolica',d.pa_sistolica,'PA',`${d.pa_sistolica}/${d.pa_diastolica||'?'}`,' mmHg'));
                if (d.frecuencia_cardiaca) cells.push(_vCell('frecuencia_cardiaca',d.frecuencia_cardiaca,'FC',d.frecuencia_cardiaca,' lpm'));
                if (d.frecuencia_respiratoria) cells.push(_vCell('frecuencia_respiratoria',d.frecuencia_respiratoria,'FR',d.frecuencia_respiratoria,' rpm'));
                if (d.temperatura) cells.push(_vCell('temperatura',d.temperatura,'Temp',d.temperatura,'°C'));
                if (d.spo2) cells.push(_vCell('spo2',d.spo2,'SpO₂',d.spo2,'%'));
                if (d.glucosa) cells.push(_vCell('glucosa',d.glucosa,'Gluc',d.glucosa,' mg/dL'));
                if (d.peso) cells.push(_vCell(null,d.peso,'Peso',d.peso,' kg'));
                return cells.length ? `<div class="cd-tl-vitals-grid">${cells.join('')}</div>` : esc('Signos vitales');
            }
            // Old format
            if (d.rows && d.rows.length) {
                const row = d.rows[0];
                let cells = [];
                if (row.sys) cells.push(_vCell('pa_sistolica',row.sys,'PA',`${row.sys||'?'}/${row.dia||'?'}`,' mmHg'));
                if (row.fc) cells.push(_vCell('frecuencia_cardiaca',row.fc,'FC',row.fc,' lpm'));
                if (row.fr) cells.push(_vCell('frecuencia_respiratoria',row.fr,'FR',row.fr,' rpm'));
                if (row.temp) cells.push(_vCell('temperatura',row.temp,'Temp',row.temp,'°C'));
                if (row.spo2) cells.push(_vCell('spo2',row.spo2,'SpO₂',row.spo2,'%'));
                return cells.length ? `<div class="cd-tl-vitals-grid">${cells.join('')}</div>` : '';
            }
            return '';
        }
        default: return d.text ? esc(d.text) : '';
    }
}
window.buildSummary = buildSummary;

