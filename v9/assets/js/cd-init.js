// cd-init.js — Init, expose globals, legal verification, forced password change
// Extracted from cuidados.php (lines 16226)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════
updatePatientName();
setDate(_fecha);
if (typeof loadViewData === 'function') loadViewData(_currentView, { force: true });

// Resident card siempre interactivo (en cualquier vista). El usuario puede
// cambiar de residente y reloadCurrentView() (cd-patient.js) actualiza la vista
// activa con ghost loaders y skeletons.
(function syncResCardOnBoot() {
    const card = document.getElementById('cdHeaderResidentCard');
    const sel  = document.getElementById('cdPatientName');
    if (card) card.classList.remove('is-locked');
    if (sel) {
        sel.disabled = false;
        sel.tabIndex = 0;
        sel.setAttribute('aria-hidden', 'false');
    }
})();

// Check for pending system notifications
checkPendingNotifications();

// ── Expose globals ─────────────────────────────────────────────────
Object.assign(window, {
    $, $$, BASE, API_URL, CAN_EDIT, IS_ADMIN, CURRENT_USER_ID,
    _residenteId, showToast, openSidebar, closeSidebar, t, showView, cdGoPreviousView, loadViewData, refreshViewData
});
// Keep _residenteId synced (it's a let, not const)
Object.defineProperty(window, '_residenteId', {
    get() { return _residenteId; },
    set(v) { _residenteId = v; },
    configurable: true
});

// ── Verificar documentos legales pendientes de firma ────────────────────
(async function checkPendingLegalDocs() {
    if (typeof SA_IMPERSONATING !== 'undefined' && SA_IMPERSONATING) return;
    if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'superadmin') return;
    try {
        const pending = await api(`${LEGAL_API}?action=pending`);
        if (!pending?.length) return;
        for (const doc of pending) {
            await showLegalSignatureModal(doc);
        }
    } catch(e) { /* table may not exist yet */ }
})();

async function showLegalSignatureModal(doc) {
    return new Promise((resolve) => {
        const tipoLabel = doc.tipo === 'terminos' ? t('config_tc_title') : t('config_privacy_title');
        const overlay = document.createElement('div');
        overlay.className = 'cd-legal-sign-overlay';
        overlay.innerHTML = `
            <div class="cd-legal-sign-modal">
                <div class="cd-legal-sign-header">
                    <h2>${esc(tipoLabel)} <span class="cd-text-muted cd-text-sm">v${esc(doc.version)}</span></h2>
                    <p class="cd-text-muted cd-text-sm" style="margin:4px 0 0">${esc(doc.titulo)}</p>
                </div>
                <div class="cd-legal-sign-content cd-legal-rich">${renderLegalHtml(doc.contenido)}</div>
                <div class="cd-legal-sign-pad-section">
                    <p style="margin:0 0 8px;font-size:0.8125rem;font-weight:600">${t('legal_sign_instruction')}</p>
                    <div class="cd-legal-sign-canvas-wrap">
                        <canvas id="cdSignCanvas" width="500" height="160"></canvas>
                        <button type="button" class="cd-btn-ghost-sm" id="cdSignClear" style="position:absolute;top:6px;right:6px">${t('legal_sign_clear')}</button>
                    </div>
                    <div style="display:flex;justify-content:center;margin-top:14px">
                        <button class="cd-btn-submit" id="cdSignSubmit" disabled style="min-width:200px">${t('legal_sign_accept')}</button>
                    </div>
                    <p class="cd-text-muted cd-text-xs" style="text-align:center;margin-top:8px">${t('legal_sign_disclaimer')}</p>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const canvas = overlay.querySelector('#cdSignCanvas');
        const ctx = canvas.getContext('2d');
        const submitBtn = overlay.querySelector('#cdSignSubmit');
        const clearBtn = overlay.querySelector('#cdSignClear');
        let drawing = false, hasStroke = false;

        // High-DPI canvas
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.strokeStyle = '#222';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        function getPos(e) {
            const r = canvas.getBoundingClientRect();
            const touch = e.touches?.[0];
            return { x: (touch?.clientX || e.clientX) - r.left, y: (touch?.clientY || e.clientY) - r.top };
        }

        function startDraw(e) { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function draw(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasStroke = true; submitBtn.disabled = false; }
        function endDraw() { drawing = false; }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', endDraw);

        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasStroke = false;
            submitBtn.disabled = true;
        });

        submitBtn.addEventListener('click', async () => {
            if (!hasStroke) return;
            btnLoading(submitBtn, t('status_saving'));
            const firmaData = canvas.toDataURL('image/png');
            try {
                await api(`${LEGAL_API}?action=firmar&id=${doc.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firma_data: firmaData })
                });
                showToast(t('toast_legal_signed'), 'success');
                overlay.remove();
                resolve();
            } catch(e) {
                showToast(e.message || 'Error', 'error');
                btnReset(submitBtn);
            }
        });
    });
}

// 1.11 Modal de cambio de contrasena forzoso
if (_PASSWORD_CHANGE_REQUIRED) {
    document.addEventListener('DOMContentLoaded', () => {
        // Crear overlay + modal
        const overlay = document.createElement('div');
        overlay.id = 'pw-force-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = `
        <style>
            #pw-force-modal input[type="password"],
            #pw-force-modal input[type="text"] {
                width:100%;padding:8px 36px 8px 10px;border:1px solid var(--cd-border,#ddd);
                border-radius:6px;margin-top:4px;font-size:.9rem;font-family:inherit;
                background:var(--cd-bg,#f7f8fa);color:var(--cd-text,#222);
                -webkit-text-security:disc;
            }
            #pw-force-modal input[type="text"] { -webkit-text-security:none; }
            #pw-force-modal input::placeholder { color:var(--cd-text-muted,#999); }
            #pw-force-modal .pw-field-wrap {
                position:relative;display:block;margin-bottom:10px;font-family:inherit;
            }
            #pw-force-modal .pw-field-wrap:last-of-type { margin-bottom:16px; }
            #pw-force-modal .pw-toggle {
                position:absolute;right:8px;bottom:8px;background:none;border:none;
                cursor:pointer;padding:2px;color:var(--cd-text-muted,#888);display:flex;align-items:center;
            }
            #pw-force-modal .pw-toggle:hover { color:var(--cd-text,#ccc); }
            #pw-force-modal .pw-toggle svg { width:18px;height:18px; }
        </style>
        <div id="pw-force-modal" style="background:var(--cd-surface,#fff);border-radius:12px;padding:28px 24px;max-width:420px;width:92%;box-shadow:0 8px 32px rgba(0,0,0,.3);font-family:var(--cd-font,'Open Sans',system-ui,-apple-system,sans-serif);color:var(--cd-text,#222)">
            <h3 style="margin:0 0 6px;font-size:1.1rem;color:var(--cd-accent,#033f3f);font-family:inherit">🔒 Cambio de contraseña requerido</h3>
            <p style="margin:0 0 12px;font-size:.85rem;color:var(--cd-text-muted,#666);font-family:inherit">Tu contraseña no cumple con las políticas de seguridad actuales. Actualízala para continuar.</p>
            <ul id="pw-reqs" style="list-style:none;padding:0;margin:0 0 16px;font-size:.78rem;display:grid;grid-template-columns:1fr 1fr;gap:3px 12px">
                <li data-req="len" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> Mínimo 8 caracteres</li>
                <li data-req="upper" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> 1 mayúscula</li>
                <li data-req="lower" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> 1 minúscula</li>
                <li data-req="digit" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> 1 número</li>
                <li data-req="special" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> 1 carácter especial</li>
                <li data-req="match" style="color:var(--cd-text-muted,#888);transition:color .2s;font-family:inherit"><span class="pw-ico">○</span> Contraseñas coinciden</li>
            </ul>
            <div id="pw-force-error" style="display:none;margin-bottom:12px;padding:8px 12px;border-radius:8px;background:rgba(220,38,38,.12);color:#f87171;font-size:.82rem;font-family:inherit"></div>
            <form id="pw-force-form" autocomplete="off">
                <div class="pw-field-wrap">
                    <span style="font-size:.82rem;font-weight:500">Contraseña actual</span>
                    <input type="password" name="current_password" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <a href="#" id="pw-force-forgot" style="display:block;margin-top:4px;font-size:.76rem;color:var(--cd-accent,#033f3f);text-decoration:none;opacity:.85">¿No recuerdas tu contraseña actual?</a>
                </div>
                <div class="pw-field-wrap">
                    <span style="font-size:.82rem;font-weight:500">Nueva contraseña</span>
                    <input type="password" name="new_password" id="pw-new" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="pw-field-wrap" style="margin-bottom:16px">
                    <span style="font-size:.82rem;font-weight:500">Confirmar nueva contraseña</span>
                    <input type="password" name="new_password2" id="pw-confirm" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <button type="submit" id="pw-submit-btn" disabled style="width:100%;padding:10px;background:var(--cd-accent,#033f3f);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:inherit;opacity:.5;transition:opacity .2s">Actualizar contraseña</button>
            </form>
        </div>`;
        document.body.appendChild(overlay);

        // ── Toggle visibilidad de contraseña ──
        overlay.querySelectorAll('.pw-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const inp = btn.previousElementSibling;
                const showing = inp.type === 'text';
                inp.type = showing ? 'password' : 'text';
                btn.innerHTML = showing
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            });
        });

        // ── Validación en tiempo real de requisitos ──
        const pwNew     = document.getElementById('pw-new');
        const pwConfirm = document.getElementById('pw-confirm');
        const submitBtn = document.getElementById('pw-submit-btn');
        const reqs      = document.querySelectorAll('#pw-reqs li');

        // ── Link "¿No recuerdas tu contraseña?" → logout + forgot ──
        document.getElementById('pw-force-forgot').addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = BASE + '/auth/logout.php?redirect=forgot-password.php';
        });

        function checkReqs() {
            const v  = pwNew.value;
            const v2 = pwConfirm.value;
            const checks = {
                len:     v.length >= 8,
                upper:   /[A-Z]/.test(v),
                lower:   /[a-z]/.test(v),
                digit:   /[0-9]/.test(v),
                special: /[^A-Za-z0-9]/.test(v),
                match:   v.length > 0 && v === v2,
            };
            let allOk = true;
            reqs.forEach(li => {
                const key = li.dataset.req;
                const ok  = checks[key];
                li.querySelector('.pw-ico').textContent = ok ? '●' : '○';
                li.style.color = ok ? '#16a34a' : 'var(--cd-text-muted,#888)';
                li.style.fontWeight = ok ? '600' : '400';
                if (!ok) allOk = false;
            });
            submitBtn.disabled = !allOk;
            submitBtn.style.opacity = allOk ? '1' : '.5';
        }

        pwNew.addEventListener('input', checkReqs);
        pwConfirm.addEventListener('input', checkReqs);

        const form  = document.getElementById('pw-force-form');
        const errEl = document.getElementById('pw-force-error');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errEl.style.display = 'none';
            const fd = new FormData(form);
            try {
                const res = await _origFetch(BASE + '/api/change_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _CSRF_TOKEN },
                    body: JSON.stringify({
                        current_password: fd.get('current_password'),
                        new_password: fd.get('new_password'),
                        new_password2: fd.get('new_password2'),
                        forced: true,
                    }),
                    credentials: 'include',
                });
                const j = await res.json();
                if (!j.success) throw new Error(j.message || 'Error');
                // Flujo post-cambio: recargar la página para sesión limpia
                overlay.innerHTML = '<div style="background:var(--cd-surface,#fff);border-radius:12px;padding:32px 24px;max-width:420px;width:92%;text-align:center;font-family:var(--cd-font,\'Open Sans\',\'Inter\',system-ui,sans-serif);color:var(--cd-text,#222)"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" style="margin-bottom:12px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><h3 style="margin:0 0 8px;font-size:1.1rem;color:#16a34a">¡Contraseña actualizada!</h3><p style="margin:0;font-size:.85rem;color:var(--cd-text-muted,#666)">Iniciando sesión con tu nueva contraseña¦</p></div>';
                setTimeout(() => { window.location.reload(); }, 1800);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
            }
        });
    });
}


// -- Status bar nativo: re-aplicar al final de init (bridge Android puede no estar listo antes) --
(function _restoreStatusBar() {
    if (typeof syncNativeStatusBar !== 'function') return;
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    [0, 300, 800, 2000].forEach(ms => setTimeout(() => syncNativeStatusBar(dark), ms));
})();
