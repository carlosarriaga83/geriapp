// cd-helpers.js — Helpers, confirm dialog, prompt dialog
// Extracted from cuidados.php (lines 12971)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=String(s);return d.innerHTML;}

function cdFlashValidation(el) {
    if (!el) return;
    const els = el.length !== undefined && !el.nodeType ? [...el] : [el];
    els.forEach(target => {
        if (!target?.classList) return;
        target.classList.remove('cd-glow', 'cd-shake');
        void target.offsetWidth;
        target.classList.add('cd-glow', 'cd-shake');
        target.addEventListener('animationend', () => target.classList.remove('cd-glow', 'cd-shake'), { once: true });
    });
    els[0]?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
}

function cdSetFieldInvalid(control, invalid = true, opts = {}) {
    if (!control?.classList) return;
    const isNativeField = control.matches?.('input,select,textarea,.cd-input,.cd-select,.cd-textarea');
    const invalidClass = isNativeField ? 'cd-input-error' : 'cd-required-missing';
    if (invalid) {
        control.classList.add(invalidClass);
        control.setAttribute('aria-invalid', 'true');
        cdFlashValidation(control);
        if (opts.focus !== false) control.focus?.({ preventScroll: true });
    } else {
        control.classList.remove('cd-input-error', 'cd-required-missing', 'cd-glow', 'cd-shake');
        control.removeAttribute('aria-invalid');
    }
}

function cdClearFieldInvalid(control) {
    cdSetFieldInvalid(control, false);
}

function cdRequiredControlHasValue(control) {
    if (!control) return true;
    if (control.matches?.('input[type="checkbox"]')) return !!control.checked;
    if (control.matches?.('input[type="radio"]')) {
        const name = control.getAttribute('name');
        const form = control.form || document;
        if (!name) return !!control.checked;
        try { return !!form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`); }
        catch(e) { return !!control.checked; }
    }
    if (control.matches?.('input,select,textarea')) return String(control.value ?? '').trim() !== '';
    const checked = control.querySelectorAll?.('input[type="checkbox"]:checked,input[type="radio"]:checked').length || 0;
    if (control.querySelector?.('input[type="checkbox"],input[type="radio"]')) return checked > 0;
    return String(control.textContent ?? '').trim() !== '';
}

function cdValidateRequiredFields(root = document, opts = {}) {
    const scope = root && root.querySelectorAll ? root : document;
    const missing = [];
    scope.querySelectorAll('input[required],select[required],textarea[required],[data-required],[aria-required="true"]').forEach(control => {
        if (control.type === 'hidden' || control.disabled || control.offsetParent === null) return;
        if (!cdRequiredControlHasValue(control)) missing.push(control);
        else cdClearFieldInvalid(control);
    });
    if (!missing.length) return true;
    missing.forEach((control, idx) => cdSetFieldInvalid(control, true, { focus: idx === 0 }));
    if (opts.message) showToast?.(opts.message, opts.type || 'error');
    return false;
}

function cdEnsureRequiredMarker(label) {
    if (!label || label.dataset.requiredMarked === '1') return;
    const hasStarElement = Array.from(label.children || []).some(child => child.textContent.trim() === '*');
    if (label.classList.contains('med-required') || hasStarElement || label.querySelector('.cd-required,.sa-req,.reg-required-star,.login-required,.bl-required')) {
        label.dataset.requiredMarked = '1';
        return;
    }
    const textNode = Array.from(label.childNodes).find(node => node.nodeType === Node.TEXT_NODE && node.textContent.includes('*'));
    if (textNode) {
        const parts = textNode.textContent.split('*');
        const frag = document.createDocumentFragment();
        frag.appendChild(document.createTextNode(parts.shift()));
        const star = document.createElement('span');
        star.className = 'cd-required';
        star.setAttribute('aria-hidden', 'true');
        star.textContent = '*';
        frag.appendChild(star);
        frag.appendChild(document.createTextNode(parts.join('*')));
        label.replaceChild(frag, textNode);
    } else {
        const star = document.createElement('span');
        star.className = 'cd-required';
        star.setAttribute('aria-hidden', 'true');
        star.textContent = '*';
        label.appendChild(document.createTextNode(' '));
        label.appendChild(star);
    }
    label.dataset.requiredMarked = '1';
}

function cdFindControlLabel(control, root = document) {
    if (!control) return null;
    if (control.id) {
        try {
            const byFor = root.querySelector(`label[for="${CSS.escape(control.id)}"]`) || document.querySelector(`label[for="${CSS.escape(control.id)}"]`);
            if (byFor) return byFor;
        } catch(e) {}
    }
    const wrapLabel = control.closest('label');
    if (wrapLabel) return wrapLabel;
    const prev = control.previousElementSibling;
    if (prev && prev.tagName === 'LABEL') return prev;
    const group = control.closest('.cd-form-group,.cd-field,.login-field,.bl-form-row,.form-group,.cd-sidebar-section,div');
    return group?.querySelector(':scope > label') || null;
}

function cdMarkRequiredFields(root = document) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('label').forEach(label => {
        if (label.textContent.includes('*')) cdEnsureRequiredMarker(label);
    });
    scope.querySelectorAll('input[required],select[required],textarea[required],[data-required],[aria-required="true"]').forEach(control => {
        if (control.type === 'hidden') return;
        const label = cdFindControlLabel(control, scope);
        cdEnsureRequiredMarker(label);
        if (!control.hasAttribute('aria-required')) control.setAttribute('aria-required', 'true');
        if (!control.dataset.requiredValidationBound) {
            control.dataset.requiredValidationBound = '1';
            control.addEventListener('input', () => cdClearFieldInvalid(control));
            control.addEventListener('change', () => {
                if (cdRequiredControlHasValue(control)) cdClearFieldInvalid(control);
            });
            control.addEventListener('invalid', () => cdSetFieldInvalid(control, true), true);
        }
    });
}

window.cdMarkRequiredFields = cdMarkRequiredFields;
window.cdFlashValidation = cdFlashValidation;
window.cdSetFieldInvalid = cdSetFieldInvalid;
window.cdClearFieldInvalid = cdClearFieldInvalid;
window.cdValidateRequiredFields = cdValidateRequiredFields;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => cdMarkRequiredFields());
} else {
    cdMarkRequiredFields();
}
(() => {
    let requiredTimer = null;
    const schedule = () => {
        clearTimeout(requiredTimer);
        requiredTimer = setTimeout(() => cdMarkRequiredFields(), 60);
    };
    const observe = () => {
        if (!document.body) return;
        new MutationObserver(schedule).observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['required', 'data-required', 'aria-required'],
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observe);
    else observe();
})();

/**
 * Render legal document content safely.
 * - If the raw string contains HTML tags → sanitize (allowlist) and render as rich HTML.
 * - If plain text (pre-v1.34.8 docs from textarea) → escape & convert newlines to <br>.
 */
function renderLegalHtml(raw) {
    if (!raw) return '';
    const hasHtml = /<[a-z][\s\S]*?>/i.test(raw);
    if (!hasHtml) {
        // Plain text: escape and preserve newlines
        return esc(raw).replace(/\n/g, '<br>');
    }
    // Rich HTML: sanitize with allowlist
    const allow = {
        B:1,STRONG:1,I:1,EM:1,U:1,BR:1,P:1,DIV:1,SPAN:1,
        UL:1,OL:1,LI:1,H1:1,H2:1,H3:1,H4:1,H5:1,H6:1,
        BLOCKQUOTE:1,PRE:1,CODE:1,HR:1,SUB:1,SUP:1,A:1
    };
    const safeAttrs = { A: ['href','target','rel'] };
    const tmp = document.createElement('div');
    tmp.innerHTML = raw;
    function clean(parent) {
        [...parent.childNodes].forEach(n => {
            if (n.nodeType === 3) return; // text node OK
            if (n.nodeType === 1) {
                if (!allow[n.tagName]) {
                    // Replace disallowed tag with its children
                    while (n.firstChild) parent.insertBefore(n.firstChild, n);
                    parent.removeChild(n);
                    return;
                }
                // Strip disallowed attributes
                const kept = safeAttrs[n.tagName] || [];
                [...n.attributes].forEach(a => {
                    if (!kept.includes(a.name) || (a.name === 'href' && /^\s*javascript:/i.test(a.value))) {
                        n.removeAttribute(a.name);
                    }
                });
                clean(n);
            } else {
                parent.removeChild(n);
            }
        });
    }
    clean(tmp);
    return tmp.innerHTML;
}

// Short name: "Nombre Primer_Apellido" — strips titles, handles composed names
function shortName(full) {
    if (!full) return '';
    const titles = /^(dr\.?|dra\.?|lic\.?|enf\.?|ing\.?|mtro\.?|mtra\.?|prof\.?|sr\.?|sra\.?|srta\.?)\s+/i;
    let s = full.trim().replace(titles, '');
    const parts = s.split(/\s+/);
    if (parts.length <= 2) return s;
    // "Nombre + Primer Apellido" — first word + last word-like tokens
    // Common pattern: "María Fernanda García López" → "María García"
    // Or "Juan de la Cruz Hernández" → "Juan Hernández"
    return parts[0] + ' ' + parts[parts.length - 2];
}

function skeleton(n = 3) {
    return Array.from({length: n}, (_, i) => `<div class="cd-skeleton-card">
        <div class="cd-skeleton-row"><div class="cd-skeleton cd-skeleton-circle"></div><div style="flex:1"><div class="cd-skeleton cd-skeleton-line ${i%2?'medium':'long'}"></div><div class="cd-skeleton cd-skeleton-line short"></div></div></div>
    </div>`).join('');
}

function rxGhostPills(n = 3) {
    return `<div class="cd-rx-tracker-col-label"><div class="cd-skeleton" style="width:60px;height:14px;border-radius:6px"></div></div>` +
        Array.from({length: n}, () => `<div class="cd-skeleton cd-rx-ghost-pill"></div>`).join('');
}

function btnLoading(btn, text) {
    if (!btn || btn.classList.contains('cd-btn-loading')) return;
    if (btn.closest?.('.cd-sidebar-actions') && typeof window.cdSidebarSetActionBusy === 'function') {
        window.cdSidebarSetActionBusy(btn, text || btn.textContent || 'Procesando');
    }
    btn._origHtml = btn.innerHTML;
    btn.innerHTML = `<span class="cd-btn-text">${text || btn.textContent}</span>`;
    btn.classList.add('cd-btn-loading');
    btn.disabled = true;
}
function btnReset(btn) {
    if (!btn) return;
    btn.classList.remove('cd-btn-loading');
    btn.disabled = false;
    if (btn._origHtml !== undefined) btn.innerHTML = btn._origHtml;
    if (btn.closest?.('.cd-sidebar-actions') && typeof window.cdSidebarClearActionBusy === 'function') {
        window.cdSidebarClearActionBusy(btn);
    }
}

function showToast(msg,type){
    toast.textContent=msg; toast.className='cd-toast'+(type?' '+type:'');
    toast.classList.add('show'); clearTimeout(toast._t);
    toast._t=setTimeout(()=>toast.classList.remove('show'),3000);
}

// ── Styled confirm dialog (replaces native confirm()) ────────
const _cfgOverlay = $('#cdConfirmOverlay');
const _cfgMsg     = $('#cdConfirmMsg');
const _cfgTitle   = $('#cdConfirmTitle');
const _cfgIcon    = $('#cdConfirmIcon');
const _cfgYes     = $('#cdConfirmYes');
const _cfgNo      = $('#cdConfirmNo');

const _CONFIRM_ICONS = {
    warn: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    danger: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
};

/**
 * Show a styled confirm dialog. Returns a Promise<boolean>.
 * @param {string} msg - Message body
 * @param {object} [opts] - { title, okText, cancelText, type: 'warn'|'danger'|'info' }
 */
function cdConfirm(msg, opts = {}) {
    const type = opts.type || 'warn';
    _cfgMsg.textContent = msg;
    _cfgTitle.textContent = opts.title || t('btn_confirm');
    _cfgIcon.className = 'cd-confirm-icon ' + type;
    _cfgIcon.innerHTML = _CONFIRM_ICONS[type] || _CONFIRM_ICONS.warn;
    _cfgYes.textContent = opts.okText || 'Aceptar';
    _cfgYes.className = 'cd-confirm-ok' + (type === 'danger' ? ' danger' : '');
    _cfgNo.textContent = opts.cancelText || 'Cancelar';
    const isRequired = !!opts.required;
    _cfgOverlay.classList.add('show');

    return new Promise(resolve => {
        function cleanup(result) {
            _cfgOverlay.classList.remove('show');
            _cfgYes.removeEventListener('click', onYes);
            _cfgNo.removeEventListener('click', onNo);
            _cfgOverlay.removeEventListener('click', onBg);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        }
        function onYes() { cleanup(true); }
        function onNo()  { cleanup(false); }
        function onBg(e) { if (!isRequired && e.target === _cfgOverlay) cleanup(null); }
        function onKey(e) { if (!isRequired && e.key === 'Escape') cleanup(null); }
        _cfgYes.addEventListener('click', onYes);
        _cfgNo.addEventListener('click', onNo);
        _cfgOverlay.addEventListener('click', onBg);
        document.addEventListener('keydown', onKey);
        _cfgYes.focus();
    });
}

/**
 * Show a prompt dialog that returns the entered string or null if cancelled.
 * @param {string} msg - Message / label
 * @param {object} [opts] - { title, placeholder, type: 'info'|'warn' }
 */
function cdPrompt(msg, opts = {}) {
    const type = opts.type || 'info';
    _cfgMsg.innerHTML = msg + '<br><input id="cdPromptInput" class="cd-input" style="margin-top:10px;width:100%" placeholder="' + esc(opts.placeholder || '') + '">';
    _cfgTitle.textContent = opts.title || '';
    _cfgIcon.className = 'cd-confirm-icon ' + type;
    _cfgIcon.innerHTML = _CONFIRM_ICONS[type] || _CONFIRM_ICONS.info;
    _cfgYes.textContent = opts.okText || 'Aceptar';
    _cfgYes.className = 'cd-confirm-ok';
    _cfgNo.textContent = 'Cancelar';
    _cfgOverlay.classList.add('show');

    return new Promise(resolve => {
        const inp = document.getElementById('cdPromptInput');
        function cleanup(result) {
            _cfgOverlay.classList.remove('show');
            _cfgYes.removeEventListener('click', onYes);
            _cfgNo.removeEventListener('click', onNo);
            _cfgOverlay.removeEventListener('click', onBg);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        }
        function onYes() { cleanup(inp?.value?.trim() || null); }
        function onNo()  { cleanup(null); }
        function onBg(e) { if (e.target === _cfgOverlay) cleanup(null); }
        function onKey(e) { if (e.key === 'Escape') cleanup(null); if (e.key === 'Enter') onYes(); }
        _cfgYes.addEventListener('click', onYes);
        _cfgNo.addEventListener('click', onNo);
        _cfgOverlay.addEventListener('click', onBg);
        document.addEventListener('keydown', onKey);
        setTimeout(() => inp?.focus(), 100);
    });
}

function periodRange(period, customDesde, customHasta) {
    // Parse _fecha parts to avoid timezone issues with new Date()
    const [yy, mm, dd] = _fecha.split('-').map(Number);
    let desde = _fecha, hasta = _fecha;
    if (period === 'semana') {
        const d = new Date(yy, mm - 1, dd); // local date, no TZ shift
        const day = d.getDay(); // 0=Sun
        // Mon-Sun week: if Sunday (0), go back 6 days; else go back (day-1)
        const offStart = day === 0 ? 6 : day - 1;
        const s = new Date(yy, mm - 1, dd - offStart);
        const e = new Date(yy, mm - 1, dd - offStart + 6);
        desde = `${s.getFullYear()}-${String(s.getMonth()+1).padStart(2,'0')}-${String(s.getDate()).padStart(2,'0')}`;
        hasta = `${e.getFullYear()}-${String(e.getMonth()+1).padStart(2,'0')}-${String(e.getDate()).padStart(2,'0')}`;
    } else if (period === 'mes') {
        desde = `${yy}-${String(mm).padStart(2,'0')}-01`;
        const last = new Date(yy, mm, 0); // day 0 of next month = last day of current
        hasta = `${last.getFullYear()}-${String(last.getMonth()+1).padStart(2,'0')}-${String(last.getDate()).padStart(2,'0')}`;
    } else if (period === '30dias') {
        const today = new Date(yy, mm - 1, dd);
        const start = new Date(today);
        start.setDate(start.getDate() - 29); // 30 days including today
        desde = `${start.getFullYear()}-${String(start.getMonth()+1).padStart(2,'0')}-${String(start.getDate()).padStart(2,'0')}`;
        hasta = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
    } else if (period === 'custom') {
        desde = customDesde || _fecha;
        hasta = customHasta || _fecha;
    }
    return { desde, hasta };
}

// Phone fields: default Mexico country code and store values with country prefix.
(function initCdPhoneCountrySelectors() {
    const countries = [
        ['+52', 'MX +52'],
        ['+1', 'US/CA +1'],
        ['+34', 'ES +34'],
        ['+57', 'CO +57'],
        ['+54', 'AR +54'],
        ['+56', 'CL +56'],
        ['+51', 'PE +51'],
        ['+593', 'EC +593'],
        ['+58', 'VE +58'],
        ['+502', 'GT +502'],
    ];
    const countryCodes = countries.map(c => c[0].replace(/\D+/g, '')).sort((a, b) => b.length - a.length);
    const phoneIdRe = /(telefono|tel[eé]fono|phone|whatsapp|wa)$/i;
    const phoneAnyRe = /(telefono|tel[eé]fono|phone|whatsapp)/i;

    function digits(value) { return String(value || '').replace(/\D+/g, ''); }
    function phoneCandidates(root = document) {
        return Array.from(root.querySelectorAll('input')).filter(input => {
            if (!input || input.dataset.phoneEnhanced === '1') return false;
            if ((input.type || '').toLowerCase() === 'hidden') return false;
            if (input.closest('.cd-phone-field')) return false;
            const dataField = input.closest('[data-field]')?.dataset?.field || '';
            const haystack = [input.type, input.id, input.name, input.placeholder, input.getAttribute('aria-label'), dataField].filter(Boolean).join(' ');
            return (input.type || '').toLowerCase() === 'tel' || phoneAnyRe.test(haystack) || phoneIdRe.test(input.id || '') || phoneIdRe.test(input.name || '');
        });
    }
    function splitPhone(value, fallbackCode = '+52') {
        const raw = String(value || '').trim();
        const allDigits = digits(raw);
        let code = fallbackCode || '+52';
        let local = allDigits;
        if (!allDigits) return { code, local: '' };
        const explicitInternational = raw.startsWith('+') || raw.startsWith('00');
        if (explicitInternational || allDigits.length > 10) {
            const matched = countryCodes.find(c => allDigits.startsWith(c));
            if (matched) {
                code = '+' + matched;
                local = allDigits.slice(matched.length);
            }
        }
        return { code, local };
    }
    function composePhone(input) {
        if (!input) return '';
        const wrap = input.closest('.cd-phone-field');
        const select = wrap?.querySelector('.cd-phone-country');
        const code = select?.value || '+52';
        const localDigits = digits(input.value);
        if (!localDigits) { input.value = ''; return ''; }
        const codeDigits = digits(code);
        const finalDigits = localDigits.startsWith(codeDigits) ? localDigits : codeDigits + localDigits;
        input.value = '+' + finalDigits;
        return input.value;
    }
    function showLocalPhone(input) {
        const wrap = input?.closest('.cd-phone-field');
        const select = wrap?.querySelector('.cd-phone-country');
        if (!input || !select) return;
        const parts = splitPhone(input.value, select.value || '+52');
        select.value = countries.some(c => c[0] === parts.code) ? parts.code : '+52';
        input.dataset.phoneCountryDefault = select.value;
        input.value = parts.local;
    }
    function enhancePhoneInput(input) {
        if (!input || input.dataset.phoneEnhanced === '1') return;
        const parts = splitPhone(input.value, '+52');
        const wrapper = document.createElement('div');
        wrapper.className = 'cd-phone-field';
        const select = document.createElement('select');
        select.className = 'cd-phone-country';
        select.setAttribute('aria-label', 'Código de país');
        select.innerHTML = countries.map(([value, label]) => `<option value="${value}"${value === parts.code ? ' selected' : ''}>${label}</option>`).join('');
        input.dataset.phoneEnhanced = '1';
        input.dataset.phoneCountryDefault = parts.code || '+52';
        input.type = 'tel';
        input.inputMode = input.inputMode || 'tel';
        input.autocomplete = input.autocomplete || 'tel-national';
        input.placeholder = input.placeholder || '55 1234 5678';
        input.value = parts.local;
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(select);
        wrapper.appendChild(input);
        const rememberCountry = () => { select.dataset.prevValue = select.value; };
        select.addEventListener('focus', rememberCountry);
        select.addEventListener('pointerdown', rememberCountry);
        select.addEventListener('touchstart', rememberCountry, { passive: true });
        select.addEventListener('change', () => {
            const previousCode = select.dataset.prevValue || input.dataset.phoneCountryDefault || '+52';
            const parts = splitPhone(input.value, previousCode);
            input.value = parts.local;
            input.dataset.phoneCountryDefault = select.value;
            select.dataset.prevValue = select.value;
        });
        input.addEventListener('focus', () => showLocalPhone(input));
        input.addEventListener('blur', () => { if (input.value.trim().startsWith('+')) showLocalPhone(input); });
    }
    function enhanceAll(root = document) { phoneCandidates(root).forEach(enhancePhoneInput); }
    function composeAll(root = document) {
        root.querySelectorAll('.cd-phone-field input[data-phone-enhanced="1"]').forEach(composePhone);
    }
    window.cdNormalizePhoneInput = composePhone;
    window.cdNormalizePhoneInputs = composeAll;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => enhanceAll());
    else enhanceAll();
    document.addEventListener('click', e => {
        if (e.target?.closest?.('.cd-phone-field')) return;
        composeAll();
    }, true);
    document.addEventListener('submit', () => composeAll(), true);
    new MutationObserver(mutations => {
        mutations.forEach(m => m.addedNodes.forEach(node => {
            if (node.nodeType === 1) enhanceAll(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();

