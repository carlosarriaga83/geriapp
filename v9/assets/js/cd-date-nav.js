// cd-date-nav.js — Date navigation
// Extracted from cuidados.php (lines 3101)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// DATE NAV
// ═══════════════════════════════════════════════
function setDate(dateStr) {
    const today = nowInTz().date;
    if (!CAN_FUTURE && dateStr > today) dateStr = today;
    _fecha = dateStr;
    dateInput.value = dateStr;
    dateLabel.textContent = dateStr === today ? 'Hoy' : formatDateShort(dateStr);
    todayQuick.style.display = dateStr === today ? 'none' : '';
    todayQuick.textContent = dateStr === today ? 'Hoy' : 'Ir a hoy';
    const nextBtn = $('#cdDateNext');
    if (nextBtn) nextBtn.disabled = (!CAN_FUTURE && _fecha >= today);
    if (window._updateSleepHint && _currentView === 'viewFormSueno') window._updateSleepHint();
    if (typeof window._updateAllEventDateChips === 'function') window._updateAllEventDateChips();
    if (_currentView === 'viewRecords') loadRecords();
    else if (_currentView === 'viewInventory') loadInventory();
    else if (_currentView === 'viewDashboard') loadDashboard();
}

function formatDateShort(d) {
    return fmtDate(d);
}

/**
 * Format a YYYY-MM-DD date string according to APP_DATE_FMT config.
 * Supports: d/m/Y, d-m-Y, m/d/Y, Y-m-d
 */
function fmtDate(d) {
    if (!d || d.length < 10) return d || '';
    const [y, m, day] = d.split('-');
    switch (APP_DATE_FMT) {
        case 'd-m-Y': return `${day}-${m}-${y}`;
        case 'd/m/Y': return `${day}/${m}/${y}`;
        case 'm/d/Y': return `${m}/${day}/${y}`;
        case 'Y-m-d': default: return d;
    }
}

function appDatePlaceholder() {
    return (APP_DATE_FMT || 'Y-m-d')
        .replace('d', 'DD')
        .replace('m', 'MM')
        .replace('Y', 'AAAA');
}

function parseAppDateInput(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const isoMatch = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    let y, m, d;
    if (isoMatch) {
        [, y, m, d] = isoMatch;
    } else {
        const parts = raw.match(/^\d{1,4}([\/\-])\d{1,2}\1\d{1,4}$/) ? raw.split(/[\/\-]/) : null;
        if (!parts || parts.length !== 3) return null;
        switch (APP_DATE_FMT) {
            case 'd/m/Y':
            case 'd-m-Y':
                [d, m, y] = parts;
                break;
            case 'm/d/Y':
                [m, d, y] = parts;
                break;
            case 'Y-m-d':
            default:
                [y, m, d] = parts;
                break;
        }
    }
    if (String(y).length === 2) y = '20' + y;
    y = String(y).padStart(4, '0');
    m = String(m).padStart(2, '0');
    d = String(d).padStart(2, '0');
    const iso = `${y}-${m}-${d}`;
    const dt = new Date(iso + 'T00:00:00');
    if (Number.isNaN(dt.getTime()) || dt.getFullYear() !== parseInt(y) || (dt.getMonth() + 1) !== parseInt(m) || dt.getDate() !== parseInt(d)) return null;
    return iso;
}

function setAppDateInputValue(input, iso) {
    if (!input) return;
    input.placeholder = appDatePlaceholder();
    input.dataset.iso = iso || '';
    input.value = iso ? fmtDate(iso) : '';
    input.classList.remove('cd-input-error');
    syncAppDateNative(input);
}

function syncAppDateNative(input) {
    if (!input) return;
    const native = document.querySelector(`[data-app-date-native="${input.id}"]`);
    if (!native) return;
    const iso = parseAppDateInput(input.value || input.dataset.iso || '') || '';
    native.value = iso;
    native.disabled = !!input.disabled;
    native.closest('.cd-sb-date-picker')?.classList.toggle('disabled', !!input.disabled);
}

function attachAppDatePicker(input) {
    if (!input || input.dataset.noDatePicker === '1' || input.dataset.appDatePickerAttached === '1') return;
    if (!input.id) input.id = 'cdAppDate' + Math.random().toString(36).slice(2, 9);
    const currentWrap = input.closest('.cd-sb-date-control');
    if (currentWrap?.querySelector('input[type="date"]')) {
        input.dataset.appDatePickerAttached = '1';
        return;
    }
    const parent = input.parentNode;
    if (!parent) return;
    const wrap = document.createElement('div');
    wrap.className = 'cd-sb-date-control cd-app-date-control';
    parent.insertBefore(wrap, input);
    wrap.appendChild(input);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cd-sb-date-picker cd-app-date-picker';
    btn.title = 'Seleccionar fecha';
    btn.setAttribute('aria-label', 'Seleccionar fecha');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
    const native = document.createElement('input');
    native.type = 'date';
    native.className = 'cd-sb-date-native cd-app-date-native';
    native.dataset.appDateNative = input.id;
    native.dataset.noFmtHint = '1';
    btn.appendChild(native);
    wrap.appendChild(btn);
    input.dataset.appDatePickerAttached = '1';
    native.addEventListener('pointerdown', () => syncAppDateNative(input));
    native.addEventListener('change', () => { if (native.value) setAppDateInputValue(input, native.value); });
    input.addEventListener('blur', () => syncAppDateNative(input));
    input.addEventListener('input', () => { if (!input.value) native.value = ''; });
    syncAppDateNative(input);
}

function initAppDateTextInput(input) {
    if (!input || input.dataset.appDateInit === '1') return;
    input.dataset.appDateInit = '1';
    input.placeholder = appDatePlaceholder();
    input.inputMode = 'numeric';
    attachAppDatePicker(input);
    input.addEventListener('blur', () => {
        const iso = parseAppDateInput(input.value);
        if (iso) setAppDateInputValue(input, iso);
        else if (input.value) input.classList.add('cd-input-error');
    });
    input.addEventListener('input', () => (window.cdClearFieldInvalid ? window.cdClearFieldInvalid(input) : input.classList.remove('cd-input-error')));
}

function appDateInputIso(input, opts = {}) {
    if (!input) return '';
    const iso = parseAppDateInput(input.value || input.dataset.iso || '');
    const required = !!opts.required;
    const invalid = (required && !iso) || iso === null;
    if (invalid && window.cdSetFieldInvalid) window.cdSetFieldInvalid(input, true);
    else input.classList.toggle('cd-input-error', invalid);
    if (invalid) {
        showToast?.(opts.message || appDatePlaceholder(), 'warning');
        input.focus?.();
        return null;
    }
    if (iso) setAppDateInputValue(input, iso);
    return iso || '';
}

/**
 * Convert a UTC Date object or ISO string to the configured timezone.
 * Returns a Date-like object via Intl formatting.
 */
function toAppTz(dateInput) {
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(d)) return d;
    return new Date(d.toLocaleString('en-US', { timeZone: APP_TZ }));
}

/**
 * Get current date (YYYY-MM-DD) and time (HH:MM) in the configured timezone.
 */
function nowInTz() {
    const now = toAppTz(new Date());
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    return { date: `${y}-${m}-${dd}`, time: `${hh}:${mm}` };
}

/**
 * Format a UTC datetime string (e.g. "2026-03-23 14:30:00") to local TZ display.
 * Returns { date: 'formatted date', time: 'HH:MM' }
 */
function fmtDateTime(dtStr) {
    if (!dtStr) return { date: '', time: '' };
    const d = toAppTz(dtStr.includes('T') ? dtStr : dtStr.replace(' ', 'T') + 'Z');
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const iso = `${y}-${m}-${dd}`;
    return { date: fmtDate(iso), time: String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') };
}

function fmtRecordDate(dateStr) {
    if (!dateStr) return '';
    return fmtDate(dateStr);
}

/**
 * Auto-attach a `.cd-date-fmt-hint` after every `<input type="date">` so the
 * value is also displayed in the user's preferred date format (APP_DATE_FMT,
 * configured via cfgFechaFormato). The native date input always uses ISO
 * (YYYY-MM-DD) for the value and the OS locale for its visual representation;
 * this hint gives a consistent in-app preview.
 *
 * Inputs marked `data-no-fmt-hint` are skipped (e.g. compact filters that
 * don't have room for the hint).
 */
function _attachDateFmtHint(input) {
    if (!input || input.dataset.fmtHintAttached === '1' || input.dataset.noFmtHint === '1') return;
    input.dataset.fmtHintAttached = '1';
    let hint = input.nextElementSibling;
    if (!hint || !hint.classList || !hint.classList.contains('cd-date-fmt-hint')) {
        hint = document.createElement('div');
        hint.className = 'cd-date-fmt-hint';
        input.parentNode?.insertBefore(hint, input.nextSibling);
    }
    const sync = () => {
        if (input.disabled) { hint.textContent = ''; return; }
        if (input.value) {
            try { hint.textContent = fmtDate(input.value); }
            catch(_) { hint.textContent = input.value; }
        } else { hint.textContent = ''; }
    };
    input.addEventListener('change', sync);
    input.addEventListener('input',  sync);
    sync();
}

function _scanDateInputs(root = document) {
    (root.querySelectorAll ? root.querySelectorAll('input[type="date"]') : []).forEach(_attachDateFmtHint);
}

function _scanAppDateTextInputs(root = document) {
    (root.querySelectorAll ? root.querySelectorAll('input.cd-app-date-input') : []).forEach(initAppDateTextInput);
}

function _refreshDateFmtHints(root = document) {
    (root.querySelectorAll ? root.querySelectorAll('input[type="date"]') : []).forEach(input => {
        if (input.dataset.noFmtHint === '1') return;
        _attachDateFmtHint(input);
        input.dispatchEvent(new Event('input', { bubbles: false }));
    });
}
window._refreshDateFmtHints = _refreshDateFmtHints;

document.addEventListener('DOMContentLoaded', () => {
    _scanDateInputs();
    _scanAppDateTextInputs();
    // Watch for dynamically inserted date inputs (sidebar forms, modals, etc.)
    new MutationObserver(muts => {
        for (const m of muts) {
            for (const node of m.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches?.('input[type="date"]')) _attachDateFmtHint(node);
                if (node.matches?.('input.cd-app-date-input')) initAppDateTextInput(node);
                if (node.querySelectorAll) _scanDateInputs(node);
                if (node.querySelectorAll) _scanAppDateTextInputs(node);
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
});

$('#cdDatePrev').addEventListener('click', () => {
    const d = new Date(_fecha); d.setDate(d.getDate()-1);
    setDate(d.toISOString().split('T')[0]);
});
$('#cdDateNext').addEventListener('click', () => {
    const d = new Date(_fecha); d.setDate(d.getDate()+1);
    setDate(d.toISOString().split('T')[0]);
});
$('#cdTodayBtn').addEventListener('click', () => dateInput.showPicker?.());
$('#cdTodayQuick').addEventListener('click', () => setDate(nowInTz().date));
dateInput.addEventListener('change', () => setDate(dateInput.value));

