// cd-core.js — i18n, CSRF, image compress, globals
// Extracted from cuidados.php (lines 2731)
// ────────────────────────────────────────────────────────────

const _T = <?= t_json() ?>;
(function() {
'use strict';

// ── i18n helper (JS side) ──
function t(key, placeholders) {
    let s = (typeof _T !== 'undefined' && _T[key]) ? _T[key] : key;
    if (placeholders) Object.entries(placeholders).forEach(([k,v]) => { s = s.replaceAll(k, v); });
    return s;
}

// ── Force HTTP on non-production hosts (tunnel, localhost) ──
// Chrome HTTPS-First / HSTS can upgrade the page to HTTPS even when
// the server has no SSL certificate, causing ERR_SSL_PROTOCOL_ERROR
// on all subsequent fetch requests.  Redirect back to HTTP early.
const _prodHosts = ['geriapp.prepenv.com', 'testgeriapp.prepenv.com'];
if (location.protocol === 'https:' && !_prodHosts.includes(location.hostname)) {
    location.replace('http://' + location.host + location.pathname + location.search + location.hash);
}

const BASE       = <?= json_encode(BASE_URL) ?>;
const APP_URL    = <?= json_encode(rtrim($cfg['app_url'] ?? '', '/') ?: app_public_url()) ?>;
const API_URL    = BASE + '/api/cuidados.php';
const RES_API    = BASE + '/api/residentes.php';
const CAN_EDIT   = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_EDIT_RESIDENTS = <?= $canEditResidents ? 'true' : 'false' ?>;
const IS_DOCTOR  = <?= in_array($userRole, ['medico','superadmin'], true) ? 'true' : 'false' ?>;
const USER_ROLE  = <?= json_encode($userRole) ?>;
const SA_IMPERSONATING = <?= !empty($_SESSION['sa_impersonating']) ? 'true' : 'false' ?>;
const CAN_FUTURE = <?= $canFuture ? 'true' : 'false' ?>;
const CAN_PREVIEW_REPORT = <?= $canPreviewReport ? 'true' : 'false' ?>;
const CAN_NM_DIRECTO = <?= $canNmDirecto ? 'true' : 'false' ?>;
// Per-element UI permissions (superadmin-managed) — keyed by perm-id
const PERM_ELEMENTS = <?= json_encode($permElements ?? [], JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?>;
const RESIDENTES = <?= $residentesJson ?>;
const RX_BY_RES  = <?= $rxJson ?>;
const INST_NAME  = <?= json_encode($_SESSION['user_institucion_nombre'] ?? 'Institución') ?>;
const INST_ID    = <?= (int)$instId ?>;

// ── Â§1.9 CSRF: interceptar fetch para inyectar token automáticamente ──────
const _CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const _origFetch = window.fetch;
window.fetch = function(url, opts = {}) {
    opts = opts || {};
    const method = (opts.method || 'GET').toUpperCase();
    if (['POST','PUT','DELETE','PATCH'].includes(method)) {
        // Para FormData, agregar como campo oculto
        if (opts.body instanceof FormData) {
            if (!opts.body.has('_csrf')) opts.body.append('_csrf', _CSRF_TOKEN);
        } else {
            // Para JSON, agregar header
            opts.headers = opts.headers || {};
            if (typeof opts.headers === 'object' && !(opts.headers instanceof Headers)) {
                opts.headers['X-CSRF-Token'] = _CSRF_TOKEN;
            }
        }
    }
    return _origFetch.call(this, url, opts);
};

// ── Â§1.11 Cambio de contraseña forzoso ────────────────────────────────────
const _PASSWORD_CHANGE_REQUIRED = <?= !empty($_SESSION['password_change_required']) ? 'true' : 'false' ?>;

// ── Phone normalization helpers (resilientes al signo + inicial) ──────────
// Devuelve solo dígitos. Si la cadena trae '+' al inicio se asume E.164 y
// se respeta el código de país; en cualquier otro caso se aplica el default
// MX (52) cuando el número parece local de 10 dígitos.
function cdNormalizePhone(s, opts) {
    const trimmed = String(s == null ? '' : s).trim();
    if (!trimmed) return '';
    const hasPlus = trimmed.startsWith('+');
    const digits  = trimmed.replace(/\D+/g, '');
    if (!digits) return '';
    if (hasPlus) return digits; // ya viene en E.164
    const defaultCountry = (opts && opts.defaultCountry) || '52';
    if (digits.length === 10) return defaultCountry + digits;
    return digits;
}
// Construye una URL wa.me/<digits> resiliente.
function cdWaUrl(s) {
    const d = cdNormalizePhone(s);
    return d ? 'https://wa.me/' + d : '';
}

// ── Image compression utility ─────────────────────────────────────────────
function compressImage(file, {maxWidth = 1200, maxHeight = 1200, quality = 0.80} = {}) {
    return new Promise((resolve, reject) => {
        if (!file.type.startsWith('image/')) { resolve(file); return; }
        const img = new Image();
        img.onload = () => {
            let w = img.width, h = img.height;
            if (w <= maxWidth && h <= maxHeight && file.size < 200_000) { resolve(file); return; }
            if (w > maxWidth)  { h = Math.round(h * maxWidth / w);  w = maxWidth;  }
            if (h > maxHeight) { w = Math.round(w * maxHeight / h); h = maxHeight; }
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            canvas.toBlob(blob => {
                if (!blob) { resolve(file); return; }
                const baseName = (file.name || 'image').replace(/\.[^.]+$/, '');
                resolve(new File([blob], (baseName || 'image') + '.jpg', {type:'image/jpeg'}));
            }, 'image/jpeg', quality);
        };
        img.onerror = () => resolve(file);
        img.src = URL.createObjectURL(file);
    });
}

// Config: timezone & date format (bootstrapped from DB)
let APP_TZ     = <?= json_encode($cfgTimezone) ?>;
let APP_DATE_FMT = <?= json_encode($cfgFechaFmt) ?>;

let _residenteId = RESIDENTES.length ? RESIDENTES[0].id : 0;
let _fecha       = nowInTz().date;
let _registros   = [];
let _counts      = {};
let _resData     = null; // full resident info
let _bitacoraEvents = []; // bitacora entries for timeline
let _editingRecord = null; // record being edited (null = create mode)

const CAT_LABELS = {
    sueno: t('cat_sueno'), alimentacion: t('cat_alimentacion'), medicacion: t('cat_medicacion'),
    higiene: t('cat_higiene'), terapia: t('cat_terapia'), movilidad: t('cat_movilidad'),
    eliminacion: t('cat_eliminacion'), comportamiento: t('cat_comportamiento'),
    signos_vitales: t('cat_signos_vitales'), bitacora: t('cat_bitacora')
};

// Category-specific timeline icons
function tlIcon(cat) {
    const s = {
        sueno:          '<img src="assets/icons/moon-zzz.png">',
        alimentacion:   '<img src="assets/icons/food.png">',
        medicacion:     '<img src="assets/icons/pill.png">',
        higiene:        '<img src="assets/icons/handwash.png">',
        terapia:        '<img src="assets/icons/terapia.png">',
        movilidad:      '<img src="assets/icons/walk.png">',
        eliminacion:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
        comportamiento: '<img src="assets/icons/head-ia.png">',
        signos_vitales: '<img src="assets/icons/pulse.png">',
        incidente:      '<img src="assets/icons/warning.png">',
        bitacora:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'
    };
    return s[cat] || '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/></svg>';
}

const CAT_VIEWS = {
    sueno:'viewFormSueno', alimentacion:'viewFormAlimentacion',
    medicacion:'viewFormMedicacion', higiene:'viewFormHigiene',
    terapia:'viewFormTerapia', movilidad:'viewFormMovilidad',
    eliminacion:'viewFormEliminacion', comportamiento:'viewFormComportamiento',
    signos_vitales:'viewFormSignosVitales',
    incidente:'viewFormIncidente'
};

const $  = (s, c) => (c||document).querySelector(s);
const $$ = (s, c) => [...(c||document).querySelectorAll(s)];
const resSelect=  $('#cdPatientName'), dateInput = $('#cdDateInput'),
      dateLabel = $('#cdDateLabel'), todayQuick = $('#cdTodayQuick'),
      timeline  = $('#cdTimeline'), timelineEmpty = $('#cdTimelineEmpty'),
      timelineCount = $('#cdTimelineCount'), toast = $('#cdToast');

// ── Timeline filter/sort state ──
let _tlSortAsc = false;   // false = desc (newest first, default)
let _tlCatFilter = '';     // '' = all
let _tlSearchText = '';    // free-text filter

// ════════════════════════════════════════════════════════════════
// ROLE-LOCK SYSTEM — restricted elements shown as locked/inactive
// ════════════════════════════════════════════════════════════════
(function () {
'use strict';

const _LOCK_BADGE = '<span class="material-symbols-outlined cd-lock-badge" aria-hidden="true">admin_panel_settings</span>';

function _initLockEl(el) {
    if (el._cdLockInit) return;
    el._cdLockInit = true;
    if (!el.querySelector('.cd-lock-badge')) el.insertAdjacentHTML('beforeend', _LOCK_BADGE);
}

// Auto-inject badge on DOM insertion
const _lockMO = new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.matches('[data-cd-locked]')) _initLockEl(n);
        n.querySelectorAll('[data-cd-locked]').forEach(_initLockEl);
    }));
});

/**
 * Enforce perm-id based element-level access control.
 * Reads PERM_ELEMENTS (from server) + USER_ROLE.
 * Any element with data-perm-id whose id has a role list that does NOT include
 * the current user's role gets locked via the same data-cd-locked mechanism.
 */
function _applyPermIdLocks() {
    if (typeof PERM_ELEMENTS === 'undefined' || typeof USER_ROLE === 'undefined') return;
    document.querySelectorAll('[data-perm-id]').forEach(el => {
        const permId = el.dataset.permId;
        if (!permId) return;
        // superadmin always bypasses all perm-id locks
        if (USER_ROLE === 'superadmin') return;
        const allowed = PERM_ELEMENTS[permId];
        // If no entry in config, leave element as-is (allow by default for unlisted items)
        if (!Array.isArray(allowed)) return;
        if (!allowed.includes(USER_ROLE)) {
            // Apply lock unless already locked by role-matrix logic
            if (!el.hasAttribute('data-cd-locked')) {
                el.setAttribute('data-cd-locked', '');
                el.dataset.lockTitle = 'Acceso restringido';
                el.dataset.lockMsg   = 'No tienes permiso para usar este elemento.';
                _initLockEl(el);
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    _lockMO.observe(document.body, { childList: true, subtree: true });
    document.querySelectorAll('[data-cd-locked]').forEach(_initLockEl);
    // Apply perm-id element access control after DOM is ready
    _applyPermIdLocks();
});

// Capture-phase click: intercept before any button handler fires
document.addEventListener('click', e => {
    const el = e.target.closest('[data-cd-locked]');
    if (!el) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    cdShowRoleDialog(
        el.dataset.lockTitle || 'Función restringida',
        el.dataset.lockMsg   || 'No tienes permisos para realizar esta acción.'
    );
}, true);

function cdShowRoleDialog(title, msg) {
    document.getElementById('cdRoleLockDialog')?.remove();
    const d = document.createElement('div');
    d.id = 'cdRoleLockDialog';
    d.setAttribute('role', 'dialog');
    d.setAttribute('aria-modal', 'true');
    d.setAttribute('aria-labelledby', 'cdRoleLockDialogTitle');
    // esc() may not be available yet when this runs — use a local escaper
    const _e = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    d.innerHTML = `
        <div class="cd-rld-backdrop"></div>
        <div class="cd-rld-panel" role="document">
            <div class="cd-rld-icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
            <div class="cd-rld-title" id="cdRoleLockDialogTitle">${_e(title)}</div>
            <div class="cd-rld-msg">${_e(msg)}</div>
            <button class="cd-rld-close" id="_cdRldClose">Entendido</button>
        </div>`;
    document.body.appendChild(d);
    const close = () => { d.classList.remove('is-visible'); setTimeout(() => d.remove(), 220); };
    d.querySelector('#_cdRldClose').addEventListener('click', close);
    d.querySelector('.cd-rld-backdrop').addEventListener('click', close);
    const onKey = ev => { if (ev.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); } };
    document.addEventListener('keydown', onKey);
    requestAnimationFrame(() => d.classList.add('is-visible'));
    setTimeout(() => d.querySelector('#_cdRldClose')?.focus(), 60);
}

window.cdShowRoleDialog = cdShowRoleDialog;

}());
