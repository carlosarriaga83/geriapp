// cd-records.js — Records timeline view
// Extracted from cuidados.php (lines 9813)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// RECORDS (timeline view)
// ═══════════════════════════════════════════════
let _recordPeriod='dia', _recordsData=[];
let _recSortAsc = false, _recCatFilter = '', _recSearchText = '';
let _recordsRangeKey = '', _recordsFreshAt = 0, _recordsRefreshPromise = null, _recordsDirty = true;
const CD_RECORDS_TTL_MS = 90000;
const CD_RECORDS_STORAGE_KEY = 'geriapp_records_changed';

function _positionRecordPeriodPill() {
    const container = document.getElementById('cdRecordPeriod');
    if (!container) return;
    const pill = container.querySelector(':scope > .cd-tabs-pill');
    if (!pill) return;
    const active = container.querySelector(':scope > .cd-period-tab.active');
    if (!active || !active.offsetWidth) { pill.style.opacity = '0'; return; }
    pill.style.opacity = '1';
    pill.style.width = active.offsetWidth + 'px';
    pill.style.transform = `translateX(${active.offsetLeft}px)`;
}
window.addEventListener('resize', () => requestAnimationFrame(_positionRecordPeriodPill));

$('#cdRecordPeriod').addEventListener('click', e => {
    const b = e.target.closest('.cd-period-tab'); if(!b)return;
    $$('.cd-period-tab',e.currentTarget).forEach(x=>x.classList.remove('active'));
    b.classList.add('active'); _recordPeriod=b.dataset.period;
    _positionRecordPeriodPill();
    loadRecords({ force: true });
});

function _recordsRange() {
    const range = periodRange(_recordPeriod);
    return { desde: range.desde, hasta: range.hasta, key: `${_residenteId}|${_recordPeriod}|${range.desde}|${range.hasta}` };
}

function _recordInRange(record, range = _recordsRange()) {
    if (!record || parseInt(record.residente_id) !== parseInt(_residenteId)) return false;
    const fecha = String(record.fecha || '');
    return fecha >= range.desde && fecha <= range.hasta;
}

function _recordsViewActive() {
    return document.getElementById('viewRecords')?.classList.contains('active');
}

function cdSeedRecordsFromDashboard() {
    if (_recordPeriod !== 'dia') return false;
    if (window.__cdDashboardRecordsKey !== `${_residenteId}|${_fecha}`) return false;
    const wasDirty = _recordsDirty;
    const hadRange = !!_recordsRangeKey;
    const range = _recordsRange();
    _recordsRangeKey = range.key;
    _recordsData = Array.isArray(_registros) ? _registros.slice() : [];
    _recordsFreshAt = window.__cdDashboardFreshAt || Date.now();
    const seedIsFresh = (!wasDirty || !hadRange) && (Date.now() - _recordsFreshAt) < CD_RECORDS_TTL_MS;
    _recordsDirty = !seedIsFresh;
    if (_recordsViewActive()) renderRecordTimeline();
    return seedIsFresh;
}

function cdApplyRecordMutation(record, opts = {}) {
    if (!record || !record.id) return;
    const range = _recordsRange();
    const sameDay = parseInt(record.residente_id) === parseInt(_residenteId) && String(record.fecha || '') === String(_fecha || '');
    if (sameDay && Array.isArray(_registros)) {
        const idx = _registros.findIndex(r => parseInt(r.id) === parseInt(record.id));
        if (idx >= 0) _registros[idx] = { ..._registros[idx], ...record };
        else _registros.push(record);
        renderTimeline();
    }
    if (_recordInRange(record, range)) {
        const idx = _recordsData.findIndex(r => parseInt(r.id) === parseInt(record.id));
        if (idx >= 0) _recordsData[idx] = { ..._recordsData[idx], ...record };
        else _recordsData.push(record);
        _recordsRangeKey = range.key;
        _recordsFreshAt = Date.now();
        _recordsDirty = false;
        if (_recordsViewActive()) renderRecordTimeline();
    } else {
        _recordsDirty = true;
    }
    if (opts.broadcast !== false) cdBroadcastRecordsChanged(record);
}

function cdMarkRecordsDirty(opts = {}) {
    _recordsDirty = true;
    if (opts.broadcast) cdBroadcastRecordsChanged(opts.record || null);
}

function cdBroadcastRecordsChanged(record = null) {
    try {
        localStorage.setItem(CD_RECORDS_STORAGE_KEY, JSON.stringify({
            ts: Date.now(),
            residente_id: record?.residente_id || _residenteId,
            fecha: record?.fecha || _fecha,
            record_id: record?.id || null
        }));
    } catch(e) {}
}

async function loadRecords(opts = {}) {
    if(!_residenteId)return;
    const force = !!opts.force;
    const silent = !!opts.silent;
    const range = _recordsRange();
    const hasCachedRange = _recordsRangeKey === range.key;
    const cacheFresh = hasCachedRange && !_recordsDirty && (Date.now() - _recordsFreshAt) < CD_RECORDS_TTL_MS;
    if (!force && cacheFresh) {
        if (_recordsViewActive()) renderRecordTimeline();
        return;
    }
    if (!force && _recordPeriod === 'dia' && cdSeedRecordsFromDashboard()) return;
    if (_recordsRefreshPromise) return _recordsRefreshPromise;
    const tl=$('#cdRecTimeline');
    const recEmpty=$('#cdRecTimelineEmpty');
    const countEl = $('#cdRecCount');
    const _prevCountText = countEl ? countEl.textContent : '';
    // Indicador siempre visible mientras se busca: contador muestra "Cargando…"
    if (countEl) {
        countEl.dataset._prevText = _prevCountText;
        countEl.textContent = (typeof t === 'function' ? t('status_loading') : 'Cargando…');
        countEl.classList.add('cd-loading-pill');
    }
    if (!silent && tl) {
        $$('.cd-tl-skeleton', tl).forEach(el => el.remove());
        $$('.cd-tl-item', tl).forEach(el => el.remove());
        $$('.cd-tl-date-sep', tl).forEach(el => el.remove());
        if (recEmpty) recEmpty.style.display = 'none';
        const skEl = document.createElement('div');
        skEl.className = 'cd-tl-skeleton';
        skEl.innerHTML = skeleton(4);
        tl.prepend(skEl);
    }
    _recordsRefreshPromise = (async () => {
        try{
            const data = await api(`${API_URL}?residente_id=${_residenteId}&desde=${range.desde}&hasta=${range.hasta}`);
            _recordsData=data.registros||[];
            _recordsRangeKey = range.key;
            _recordsFreshAt = Date.now();
            _recordsDirty = false;
            renderRecordTimeline();
        }catch(e){
            $$('.cd-tl-skeleton', tl).forEach(el => el.remove());
            if (countEl) {
                countEl.textContent = countEl.dataset._prevText || '';
                countEl.classList.remove('cd-loading-pill');
            }
        }
        finally {
            _recordsRefreshPromise = null;
            if (countEl) countEl.classList.remove('cd-loading-pill');
        }
    })();
    return _recordsRefreshPromise;
}

window.addEventListener('storage', e => {
    if (e.key !== CD_RECORDS_STORAGE_KEY || !e.newValue) return;
    try {
        const payload = JSON.parse(e.newValue);
        if (parseInt(payload.residente_id) !== parseInt(_residenteId)) return;
        _recordsDirty = true;
        if (_recordsViewActive()) loadRecords({ silent: true, reason: 'storage' });
    } catch(err) {}
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && _recordsViewActive()) loadRecords({ silent: true, reason: 'visible' });
});
window.addEventListener('focus', () => {
    if (_recordsViewActive()) loadRecords({ silent: true, reason: 'focus' });
});

function renderRecordTimeline() {
    const tl=$('#cdRecTimeline'), empty=$('#cdRecTimelineEmpty'), count=$('#cdRecCount');
    $$('.cd-tl-skeleton', tl).forEach(el => el.remove());
    $$('.cd-tl-item', tl).forEach(el => el.remove());
    $$('.cd-tl-date-sep', tl).forEach(el => el.remove());

    let events = _recordsData.map(r => ({...r, source:'cuidado', _catKey: r.categoria, _catLabel: CAT_LABELS[r.categoria]||r.categoria}));

    // Populate category dropdown
    const catSelect = $('#cdRecCatFilter');
    if (catSelect) {
        const currentVal = catSelect.value;
        const catsPresent = [...new Set(events.map(e => e._catKey))];
        catSelect.innerHTML = '<option value="">' + t('tl_all_categories') + '</option>';
        catsPresent.sort((a,b) => (CAT_LABELS[a]||a).localeCompare(CAT_LABELS[b]||b));
        catsPresent.forEach(ck => {
            const opt = document.createElement('option');
            opt.value = ck; opt.textContent = CAT_LABELS[ck] || ck;
            catSelect.appendChild(opt);
        });
        catSelect.value = currentVal || '';
    }

    // Sort
    events.sort((a,b) => {
        const da = a.fecha||'', db = b.fecha||'';
        if (da !== db) return _recSortAsc ? da.localeCompare(db) : db.localeCompare(da);
        return _recSortAsc ? (a.hora||'').localeCompare(b.hora||'') : (b.hora||'').localeCompare(a.hora||'');
    });

    // Category filter
    if (_recCatFilter) events = events.filter(r => r._catKey === _recCatFilter);

    // Text search
    if (_recSearchText) {
        const q = _recSearchText.toLowerCase();
        events = events.filter(r => {
            const haystack = [r.hora, r.fecha, r._catLabel, r.observaciones, r.usuario_nombre, JSON.stringify(r.datos||{})].filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(q);
        });
    }

    const total = _recordsData.length, shown = events.length;
    empty.style.display = shown ? 'none' : 'block';
    count.textContent = (_recCatFilter || _recSearchText) ? `${shown} de ${total} eventos` : `${total} eventos`;

    const today = new Date().toISOString().split('T')[0];
    let lastDate = null;
    events.forEach(r => {
        // Date separator for multi-day views
        if (_recordPeriod !== 'dia' && r.fecha !== lastDate) {
            lastDate = r.fecha;
            const sep = document.createElement('div');
            sep.className = 'cd-tl-date-sep';
            sep.textContent = r.fecha === today ? 'Hoy' : fmtRecordDate(r.fecha);
            tl.insertBefore(sep, empty);
        }

        const div = document.createElement('div');
        div.className = 'cd-tl-item';
        div.style.cursor = 'pointer';
        div.dataset.registroId = r.id;
        const catKey = r._catKey;
        const catLabel = r._catLabel;

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
            <div class="cd-rec-time-pill">
                ${esc(r.hora || '—')}
            </div>
            <div class="cd-tl-card">
                <div class="cd-tl-body">
                    <div class="cd-tl-card-head">
                        <span class="cd-tl-icon cd-rec-inline-icon cd-tl-icon--${catKey}">${tlIcon(catKey)}</span>
                        <span class="cd-tl-cat">${esc(catLabel)}</span>
                        ${r.usuario_nombre ? `<span class="cd-tl-user">${esc(shortName(r.usuario_nombre))}${creaHora ? ' <span style="color:var(--cd-text-muted);font-weight:400">⚬ '+esc(creaHora)+'</span>' : ''}${r.verificacion_biometrica == 1 ? ' <img src="assets/icons/fingerprint-check-circle.png" alt="Firma biométrica" style="height:16px;width:16px;vertical-align:middle;margin-left:4px">' : ''}</span>` : ''}
                    </div>
                    <p class="cd-tl-summary">${buildSummary(r)}</p>
                    ${r.observaciones ? `<p class="cd-tl-obs">${esc(r.observaciones)}</p>` : ''}
                </div>
            </div>`;
        div.addEventListener('click', () => openDetailSidebar(r));
        tl.insertBefore(div, empty);
    });
}

