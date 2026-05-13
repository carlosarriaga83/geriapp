// cd-residentes.js — Residentes management (admin)
// Extracted from cuidados.php (lines 9685)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
let _resMgmtData = [];
let _resKebabBound = false;
const _resWaRegistrationCache = new Map();

function _resNormalizeWaLookupPhone(value) {
    if (typeof cdNormalizePhone === 'function') return cdNormalizePhone(value || '');
    return String(value || '').replace(/\D+/g, '');
}

async function _resCheckWhatsAppRegistration(phone, box) {
    const normalized = _resNormalizeWaLookupPhone(phone);
    if (!box || !normalized || normalized.length < 8) {
        if (box) { box.hidden = true; box.textContent = ''; box.dataset.kind = ''; }
        return;
    }
    box.hidden = false;
    box.textContent = 'Verificando WhatsApp...';
    box.dataset.kind = 'pending';
    try {
        let result = _resWaRegistrationCache.get(normalized);
        if (!result) {
            const waUrl = `${(typeof API_URL !== 'undefined' ? API_URL : `${BASE}/api/cuidados.php`)}?check_whatsapp=${encodeURIComponent(normalized)}`;
            result = await Promise.race([
                api(waUrl),
                new Promise(resolve => setTimeout(() => resolve({ registered: null, timeout: true }), 8000))
            ]);
            if (result?.success && result?.data) result = result.data;
            _resWaRegistrationCache.set(normalized, result || {});
        }
        if (result?.registered === true) {
            box.textContent = 'Número registrado en WhatsApp';
            box.dataset.kind = 'ok';
        } else if (result?.registered === false) {
            box.textContent = 'Número no registrado en WhatsApp';
            box.dataset.kind = 'warn';
        } else {
            box.textContent = 'No se pudo confirmar WhatsApp';
            box.dataset.kind = 'warn';
        }
    } catch (e) {
        box.textContent = 'No se pudo confirmar WhatsApp';
        box.dataset.kind = 'warn';
    }
}

function _resBindInviteWhatsAppStatus(scope = document) {
    scope.querySelectorAll('[data-inv-wa-reg-status]').forEach(box => {
        const phone = box.dataset.phone || box.closest('.cd-inv-detail-channel')?.querySelector('input[type="tel"]')?.value || '';
        _resCheckWhatsAppRegistration(phone, box);
    });
    scope.querySelectorAll('[data-inv-wa-reg-input]').forEach(input => {
        const box = scope.querySelector(`[data-inv-wa-reg-status-for="${input.id}"]`);
        if (!box) return;
        let timer = null;
        const schedule = () => {
            clearTimeout(timer);
            box.dataset.phone = input.value || '';
            box.hidden = true;
            timer = setTimeout(() => _resCheckWhatsAppRegistration(input.value || '', box), 550);
        };
        input.addEventListener('input', schedule);
        input.addEventListener('blur', () => {
            clearTimeout(timer);
            _resCheckWhatsAppRegistration(input.value || '', box);
        });
    });
}

function _resScrollCareTop() {
    requestAnimationFrame(() => {
        const main = document.querySelector('.cd-main');
        if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

async function loadResidentes() {
    const list = $('#cdResMgmtList');
    if (!list) return;
    Object.keys(_resExpandCache || {}).forEach(k => delete _resExpandCache[k]);
    list.innerHTML = skeleton(4);
    const estado = $('#cdResMgmtFilter')?.value ?? 'activo';
    const busqueda = $('#cdResMgmtSearch')?.value.trim() || '';
    try {
        const params = new URLSearchParams();
        params.set('cuidados_estado', '1');
        if (estado) params.set('estado', estado);
        if (busqueda) params.set('busqueda', busqueda);
        _resMgmtData = await api(`${RES_API}?${params}`);
        renderResidentesList();
    } catch(e) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);text-align:center;padding:24px 0">' + t('error_load_residents') + '</p>';
    }
}

function renderResidentesList() {
    const list = $('#cdResMgmtList');
    if (!list) return;
    if (!_resMgmtData.length) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);text-align:center;padding:24px 0">' + t('residents_not_found') + '</p>';
        return;
    }
    let kpiAlertas = 0, kpiMeds = 0, kpiSueno = 0;
    const sortedData = [..._resMgmtData].sort((a, b) => {
        const sa = _resCritScore(a);
        const sb = _resCritScore(b);
        if (sb !== sa) return sb - sa;
        const an = `${a.nombre || ''} ${a.apellidos || ''}`.toLowerCase();
        const bn = `${b.nombre || ''} ${b.apellidos || ''}`.toLowerCase();
        return an.localeCompare(bn, 'es');
    });
    const canMoveResident = !!window.__cdCanMoveResident || (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) || (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'superadmin');
    list.innerHTML = sortedData.map(r => {
        const fullName = (r.nombre||'') + ' ' + (r.apellidos||'');
        const age = r.fecha_nacimiento ? calcAge(r.fecha_nacimiento) : null;
        const sub = [age ? `${age} años` : null, ({M:'Masculino',F:'Femenino',Otro:'Otro'})[r.sexo]||r.sexo||null, r.habitacion ? `Hab. ${r.habitacion}` : null].filter(Boolean).join(' · ');
        const estadoClass = r.estado === 'activo' ? 'cd-res-estado-activo' : r.estado === 'egresado' ? 'cd-res-estado-egresado' : 'cd-res-estado-fallecido';
        const avatarHtml = r.foto_path
            ? `<img src="${BASE}/${esc(r.foto_path)}" alt="" class="cd-res-mgmt-avatar">`
            : `<div class="cd-res-mgmt-avatar cd-res-mgmt-avatar-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;

        const b = r.badges || {};
        const counts = r.counts_hoy || {};
        const _svg = (path) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
        // Use a span with CSS mask so the icon adopts the badge text color (currentColor).
        // (An <img> element renders the original pixels on top of the mask, so currentColor doesn't apply.)
        const _img = (cls, alt) => `<span class="cd-res-stat-img cd-res-stat-img--${cls}" role="img" aria-label="${esc(alt)}"></span>`;
        const _stat = (tone, icon, value, title) => `<span class="cd-res-stat${tone ? ' cd-res-stat--' + tone : ''}" title="${esc(title)}">${icon}<span>${value}</span></span>`;
        const statsLeft = [];
        const statsRight = [];

        // ── LEFT: cuidados hoy + inventario stock ──
        const totalHoy = (counts.sueno||0)+(counts.alimentacion||0)+(counts.medicacion||0)+(counts.higiene||0)+(counts.terapia||0)+(counts.movilidad||0)+(counts.eliminacion||0)+(counts.comportamiento||0)+(counts.signos_vitales||0);
        if (totalHoy > 0) {
            statsLeft.push(_stat('success', _svg('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'), totalHoy, 'Cuidados registrados hoy'));
        } else {
            statsLeft.push(_stat('muted', _svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'), '0', 'Sin cuidados registrados hoy'));
        }
        if (b.inventario_low_stock > 0) {
            statsLeft.push(_stat('alert', '<span class="material-symbols-outlined">&#xe1a1;</span>', b.inventario_low_stock, 'Insumos con bajo stock'));
        }

        // ── RIGHT: medicación, alertas médicas, signos vitales, sueño, horas evacuación ──
        if (b.medicacion_pendiente > 0) statsRight.push(_stat('alert', _svg('<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/>'), b.medicacion_pendiente, 'Medicaciones pendientes'));
        if (b.alertas_medico_pendientes > 0) statsRight.push(_stat('danger', _svg('<path d="M9 11h6"/><path d="M12 8v6"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>'), b.alertas_medico_pendientes, 'Alertas al médico pendientes'));
        if (b.signos_fuera_rango > 0) statsRight.push(_stat('danger', '<span class="cd-res-stat-img cd-res-stat-img--pulse"></span>', b.signos_fuera_rango, 'Signos vitales fuera de rango'));
        if (b.sueno_pendiente) statsRight.push(_stat('alert', _svg('<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'), 'Pdte', 'Sueño pendiente'));
        if (typeof b.heces_horas === 'number' && b.heces_horas >= 12) statsRight.push(_stat(b.heces_horas >= 24 ? 'danger' : 'alert', _img('poop', 'Heces'), `${b.heces_horas}h`, 'Horas desde última evacuación'));

        // Spacer goes between groups only when both have content
        const statsHtml = statsLeft.join('') + (statsRight.length ? '<span class="cd-res-mgmt-stats-spacer"></span>' + statsRight.join('') : '');

        if (b.alertas_medico_pendientes > 0 || b.signos_fuera_rango > 0) kpiAlertas++;
        if (b.medicacion_pendiente > 0) kpiMeds++;
        if (b.sueno_pendiente) kpiSueno++;

        const isInactive = r.estado !== 'activo';

        const navGridHtml = _resMgmtTabGridHtml(r.id);

        const avatarBlock = `<div class="cd-res-mgmt-avatar-wrap">
            ${avatarHtml}
        </div>`;

        return `<div class="cd-res-mgmt-card${isInactive ? ' cd-res-mgmt-card--inactive' : ''}" data-res-id="${r.id}" data-expanded="0">
            ${avatarBlock}
            <div class="cd-res-mgmt-head">
                <span class="cd-res-mgmt-name">${esc(fullName)}</span>
            </div>
            <div class="cd-res-mgmt-meta">
                <span class="cd-res-mgmt-sub">${esc(sub) || '—'}</span>
            </div>
            ${navGridHtml}
            <span class="cd-res-estado-badge ${estadoClass} cd-res-estado-badge--under-photo">${esc(r.estado||'activo')}</span>
            <div class="cd-res-mgmt-stats">${statsHtml}</div>
            <div class="cd-res-mgmt-subcard" data-subcard="${r.id}"></div>
            <div class="cd-res-mgmt-menu-wrap">
                <button type="button" class="cd-res-mgmt-kebab" data-res-id="${r.id}" data-perm-id="res_kebab_menu_btn" title="Acciones" aria-haspopup="true" aria-expanded="false">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                </button>
                <div class="cd-res-mgmt-menu" role="menu">
                    <button type="button" data-action="state" data-perm-id="res_kebab_care_state_btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Estado de cuidados</button>
                    <button type="button" data-action="log" data-perm-id="res_kebab_care_log_btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Historial de estado</button>
                    <button type="button" data-action="edit" data-perm-id="res_kebab_edit_btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Editar</button>
                    <button type="button" data-action="move" data-perm-id="res_kebab_move_inst_btn" ${!canMoveResident ? `class="cd-role-locked" data-cd-locked data-lock-title="Solo superadministradores" data-lock-msg="Mover un residente a otra institución requiere privilegios de superadministrador."`  : ''}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h13"/><polyline points="12 5 19 12 12 19"/><path d="M21 5v14"/></svg>Mover institución</button>
                </div>
            </div>
        </div>`;
    }).join('');

    // KPI elements removed — variables kept for possible future use

    // Close any open kebab menu when clicking elsewhere
    if (!_resKebabBound) {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.cd-res-mgmt-menu-wrap')) return;
            $$('.cd-res-mgmt-menu.open').forEach(m => {
                m.classList.remove('open');
                const btn = m.parentElement?.querySelector('.cd-res-mgmt-kebab');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        });
        _resKebabBound = true;
    }

    // Kebab toggle
    $$('.cd-res-mgmt-kebab', list).forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const wrap = btn.closest('.cd-res-mgmt-menu-wrap');
            const menu = wrap?.querySelector('.cd-res-mgmt-menu');
            if (!menu) return;
            const wasOpen = menu.classList.contains('open');
            $$('.cd-res-mgmt-menu.open').forEach(m => {
                m.classList.remove('open');
                m.parentElement?.querySelector('.cd-res-mgmt-kebab')?.setAttribute('aria-expanded', 'false');
            });
            if (!wasOpen) {
                menu.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Kebab menu actions
    $$('.cd-res-mgmt-menu button', list).forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            const action = btn.dataset.action;
            const card = btn.closest('.cd-res-mgmt-card');
            const id = parseInt(card?.dataset.resId);
            const menu = btn.closest('.cd-res-mgmt-menu');
            if (menu) {
                menu.classList.remove('open');
                menu.parentElement?.querySelector('.cd-res-mgmt-kebab')?.setAttribute('aria-expanded', 'false');
            }
            const r = _resMgmtData.find(x => x.id == id);
            if (!r) return;
            if (action === 'edit') {
                // UX-12 task 11: Editar ahora abre la Ficha del residente (gestión unificada)
                // skipNav:true evita que el change handler navegue a viewDashboard,
                // para que showView('viewFicha') apile viewResidentes correctamente.
                const sel = $('#cdPatientName');
                if (sel) { sel.value = id; sel.dispatchEvent(new CustomEvent('change', { detail: { skipNav: true } })); }
                showView('viewFicha');
            } else if (action === 'log') {
                openEstadoLogSidebar(r);
            } else if (action === 'state') {
                openResCareSidebar(r);
            } else if (action === 'move') {
                _resOpenMoveModal(id);
            }
        });
    });

    if (!list._resSubtabsBound) {
        list.addEventListener('click', e => {
            const closeBtn = e.target.closest('[data-res-sub-close]');
            if (closeBtn) {
                e.stopPropagation();
                const card = closeBtn.closest('.cd-res-mgmt-card');
                if (card) _resCollapseCard(card);
                return;
            }
            const nmBtn = e.target.closest('[data-res-sub-nm]');
            if (nmBtn) {
                e.stopPropagation();
                // Select the resident corresponding to this card, then navigate to NM view
                const card = nmBtn.closest('.cd-res-mgmt-card');
                const resId = card ? parseInt(card.dataset.resId) : 0;
                if (resId) {
                    const sel = $('#cdPatientName');
                    if (sel) { sel.value = resId; sel.dispatchEvent(new Event('change')); }
                }
                showView('viewFormNotasMedico', true, false);
                if (typeof nmLoadCurrentForResident === 'function') nmLoadCurrentForResident();
                else if (typeof renderNotasMedico === 'function') renderNotasMedico();
                return;
            }
            const tabBtn = e.target.closest('.cd-res-subtab[data-subtab], .cd-res-mgmt-grid-btn[data-subtab]');
            if (!tabBtn) return;
            e.stopPropagation();
            const card = tabBtn.closest('.cd-res-mgmt-card');
            if (card) _resOpenCardTab(card, tabBtn.dataset.subtab);
        });
        list._resSubtabsBound = true;
    }

    // Click card → select resident and go to dashboard
    $$('.cd-res-mgmt-card', list).forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('.cd-res-mgmt-menu-wrap')) return;
            if (e.target.closest('.cd-res-mgmt-nav-grid')) return;
            if (e.target.closest('.cd-res-mgmt-subcard')) return;
            const id = parseInt(card.dataset.resId);
            const r = _resMgmtData.find(x => x.id == id);
            if (!r || r.estado !== 'activo') return;
            // Select this resident in the dropdown and navigate to dashboard.
            // We must switch to viewDashboard BEFORE dispatching `change` so
            // that reloadCurrentView() runs `loadDashboard()` (otherwise
            // _currentView is still 'viewResidentes' and the change handler
            // breaks out without refreshing the data).
            const sel = $('#cdPatientName');
            const prevId = parseInt(sel?.value) || 0;
            showView('viewDashboard');
            _resScrollCareTop();
            if (sel) {
                sel.value = id;
                if (id !== prevId) {
                    // Different resident → change handler triggers loadDashboard
                    sel.dispatchEvent(new Event('change'));
                } else {
                    // Same resident → change event won't fire; refresh manually
                    if (typeof loadDashboard === 'function') loadDashboard();
                }
            }
        });
    });
}

// Cache: { [residenteId]: { familiares, prescripciones, nota_medico } }
const _resExpandCache = {};

function _resSubtabDefs() {
    return [
        { key: 'notificaciones', label: 'Notificaciones', icon: 'notifications' },
        { key: 'nota', label: 'Nota médica', icon: 'clinical_notes' },
    ];
}

function _resMgmtTabGridHtml(resId, activeTab = '', counts = {}) {
    return `<div class="cd-res-mgmt-nav-grid" data-res-grid-for="${resId}" aria-label="Accesos rápidos del residente">
        ${_resSubtabDefs().map(def => `<button type="button" class="cd-res-mgmt-grid-btn${activeTab === def.key ? ' is-active' : ''}" data-subtab="${def.key}" data-perm-id="res_subcard_${def.key}_btn" aria-expanded="${activeTab === def.key ? 'true' : 'false'}">
            <span class="material-symbols-outlined cd-res-mgmt-grid-icon" aria-hidden="true">${def.icon}</span>
            <span class="cd-res-mgmt-grid-label">${esc(def.label)}</span>
            ${counts[def.key] != null && counts[def.key] > 0 ? `<span class="cd-res-subtab-count">${counts[def.key]}</span>` : ''}
        </button>`).join('')}
    </div>`;
}

function _resSubcardCloseHtml(activeTab = '') {
    const nmAccessible = IS_DOCTOR || (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'admin') || (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'superadmin');
    const nmBtn = activeTab === 'nota'
        ? (nmAccessible
            ? `<button type="button" class="cd-res-subcard-action cd-res-subcard-nm" data-perm-id="res_subcard_nm_btn" data-res-sub-nm title="Ir a Notas médicas" aria-label="Notas médicas">
                <span class="material-symbols-outlined" aria-hidden="true">clinical_notes</span>
                <span>Notas médicas</span>
            </button>`
            : `<button type="button" class="cd-res-subcard-action cd-res-subcard-nm cd-role-locked" data-perm-id="res_subcard_nm_btn" data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal médico puede acceder a las notas médicas desde aquí." aria-label="Notas médicas">
                <span class="material-symbols-outlined" aria-hidden="true">clinical_notes</span>
                <span>Notas médicas</span>
            </button>`)
        : '';
    return `<div class="cd-res-subcard-toolbar">${nmBtn}<button type="button" class="cd-res-subcard-close" data-res-sub-close title="Colapsar información" aria-label="Colapsar información">
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
    </button></div>`;
}

function _resSetMgmtTabGrid(card, activeTab = '', counts = {}) {
    const grid = card?.querySelector(':scope > .cd-res-mgmt-nav-grid');
    if (!grid) return;
    const resId = parseInt(card.dataset.resId, 10) || 0;
    grid.outerHTML = _resMgmtTabGridHtml(resId, activeTab, counts || {});
}

function _resSetMgmtTabState(card, activeTab = '', counts = null, opts = null) {
    if (!card) return;
    if (counts) card._resSubtabCounts = counts;
    if (opts && Object.prototype.hasOwnProperty.call(opts, 'canMove')) card._resCanMove = !!opts.canMove;
    _resSetMgmtTabGrid(card, activeTab, card._resSubtabCounts || {});
}

function _resCollapseCard(card) {
    if (!card) return;
    card.dataset.expanded = '0';
    card.dataset.activeTab = '';
    const obs = _resStickyObservers.get(card);
    if (obs) { obs.disconnect(); _resStickyObservers.delete(card); }
    const cardId = card.dataset.resId || '';
    document.querySelectorAll(`.cd-res-mgmt-stickybar[data-card-id="${cardId}"]`).forEach(b => b.remove());
    card.dataset.stickyReady = '';
    _resSetMgmtTabState(card, '', card._resSubtabCounts || {}, { canMove: !!card._resCanMove });
}

async function _resOpenCardTab(card, tabKey) {
    if (!card || !tabKey) return;
    const id = parseInt(card.dataset.resId, 10);
    if (!id) return;
    const isOpen = card.dataset.expanded === '1';
    if (isOpen && card.dataset.activeTab === tabKey) {
        _resCollapseCard(card);
        return;
    }
    card.dataset.expanded = '1';
    card.dataset.activeTab = tabKey;
    _resSetMgmtTabState(card, tabKey, card._resSubtabCounts || {}, { canMove: !!card._resCanMove });
    const sub = card.querySelector('.cd-res-mgmt-subcard');
    if (!sub) return;
    if (!sub.dataset.loaded || !_resExpandCache[id]) {
        await _resLoadExpand(id, sub, tabKey);
    } else {
        _resRenderSubcard(sub, id, _resExpandCache[id], tabKey);
    }
    _resInstallStickybar(card);
    setTimeout(() => {
        const headerH = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--cd-header-h')) || 88;
        const cardTop = card.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: Math.max(0, cardTop - headerH - 8), behavior: 'smooth' });
    }, 120);
}

// Inyecta una stickybar (mini-avatar + nombre + botón cerrar) dentro de la subcard.
// Usa position:sticky puro: siempre visible mientras la card está expandida; cuando
// el usuario scrollea, la barra queda fijada al tope (top = header + 6px).
// Mapa para guardar el IntersectionObserver de cada card y poder desconectarlo al colapsar
const _resStickyObservers = new WeakMap();

function _resInstallStickybar(card) {
    if (!card || card.dataset.stickyReady === '1') return;
    const sub  = card.querySelector('.cd-res-mgmt-subcard');
    if (!sub) return;
    card.dataset.stickyReady = '1';
    const name = card.querySelector('.cd-res-mgmt-name')?.textContent?.trim() || '';
    const avatarEl  = card.querySelector('.cd-res-mgmt-avatar');
    const avatarSrc = avatarEl?.getAttribute('src') || '';
    const escName = name.replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
    const avatarHtml = avatarSrc
        ? `<img src="${avatarSrc}" alt="" class="cd-res-mgmt-stickybar-avatar">`
        : `<div class="cd-res-mgmt-stickybar-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;
    const bar = document.createElement('div');
    bar.className = 'cd-res-mgmt-stickybar';
    bar.innerHTML = `${avatarHtml}
        <span class="cd-res-mgmt-stickybar-name">${escName}</span>
        <button type="button" class="cd-res-mgmt-stickybar-close" title="Cerrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg><span>Cerrar</span></button>`;
    // IMPORTANTE: appendear al <body> en vez de la subcard.
    // La .cd-res-mgmt-card tiene transform en hover/active, lo que crea un containing
    // block para position:fixed (CSS spec) y desplaza la barra. Al estar en body, fixed
    // se posiciona relativo al viewport real.
    bar.dataset.cardId = card.dataset.resId || '';
    document.body.appendChild(bar);
    bar.querySelector('.cd-res-mgmt-stickybar-close').addEventListener('click', e => {
        e.stopPropagation();
        const headerH = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--cd-header-h')) || 88;
        const cardTop = card.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: Math.max(0, cardTop - headerH - 8), behavior: 'smooth' });
        if (card.dataset.expanded === '1') setTimeout(() => _resCollapseCard(card), 350);
    });

    // La stickybar se "desprende" cuando .cd-res-mgmt-head (avatar + nombre + datos
    // principales) sale del viewport por arriba. Mientras el head a\u00fan se vea bajo
    // el header, la stickybar permanece oculta.
    // La stickybar usa position:fixed controlada por JS. Aparece cuando .cd-res-mgmt-head
    // sale del viewport por arriba. Sin .is-visible queda oculta y NO ocupa flujo.
    const target = card.querySelector('.cd-res-mgmt-head') || avatarEl || card.querySelector('.cd-res-mgmt-avatar-wrap');
    // Buscamos el ancestro real con scroll vertical (puede ser .cd-main, body, o cualquier overflow:auto/scroll)
    function _findScroller(el) {
        let p = el?.parentElement;
        while (p && p !== document.body) {
            const s = getComputedStyle(p);
            if (/(auto|scroll|overlay)/.test(s.overflowY) && p.scrollHeight > p.clientHeight) return p;
            p = p.parentElement;
        }
        return document.querySelector('.cd-main') || window;
    }
    const scroller = _findScroller(card);
    if (target) {
        const updateBar = () => {
            if (card.dataset.expanded !== '1') return;
            const scrollerRect = (scroller === window)
                ? { top: 0, left: 0, width: document.documentElement.clientWidth }
                : scroller.getBoundingClientRect();
            const headRect = target.getBoundingClientRect();
            const cardRect = card.getBoundingClientRect();
            const hidden = headRect.bottom <= scrollerRect.top + 4;
            const cardStillVisible = cardRect.bottom > scrollerRect.top + 40;
            const show = hidden && cardStillVisible;
            if (show) {
                bar.style.top   = (scrollerRect.top + 6) + 'px';
                bar.style.left  = cardRect.left + 'px';
                bar.style.width = cardRect.width + 'px';
                bar.classList.add('is-visible');
            } else {
                bar.classList.remove('is-visible');
            }
        };
        let ticking = false;
        const onScroll = () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => { updateBar(); ticking = false; });
        };
        // Capture-phase scroll listener en document => atrapa eventos de cualquier scroller anidado
        document.addEventListener('scroll', onScroll, { passive: true, capture: true });
        window.addEventListener('resize', onScroll, { passive: true });
        // Estado inicial: forzar varios checks por si la card aun se esta animando al expandir
        updateBar();
        setTimeout(updateBar, 200);
        setTimeout(updateBar, 600);
        setTimeout(updateBar, 1100);
        _resStickyObservers.set(card, { disconnect() {
            document.removeEventListener('scroll', onScroll, { capture: true });
            window.removeEventListener('resize', onScroll);
        }});
    }
}

async function _resLoadExpand(resId, sub, activeTab = 'notificaciones') {
    sub.dataset.loaded = '1';
    sub.innerHTML = `${_resSubcardCloseHtml(activeTab)}<div class="cd-res-subpanel"><div class="cd-res-subpanel-loading">Cargando información del residente…</div></div>`;
    try {
        let data = _resExpandCache[resId];
        if (!data) {
            const today = (new Date()).toISOString().slice(0, 10);
            const [careResp, rxResp, cuidResp] = await Promise.all([
                api(`${API_URL}?dashboard=1&residente_id=${resId}&fecha=${today}`),
                api(`${BASE}/api/prescripciones.php?res_id=${resId}`).catch(() => []),
                api(`${BASE}/api/residentes.php?id=${resId}&cuidadores=1`).catch(() => null),
            ]);
            // Extract today's medication registros (administered + skipped doses)
            const regs = Array.isArray(careResp?.registros) ? careResp.registros : [];
            const medRegs = regs.filter(r => r.categoria === 'medicacion');
            data = {
                residente: careResp?.residente || null,
                familiares: (careResp?.residente?.familiares_panel) || (careResp?.residente?.familiares_usuarios) || [],
                familiaresInvitaciones: careResp?.residente?.familiares_invitaciones || [],
                familySeats: careResp?.residente?.familiares_seats || null,
                nota_medico: careResp?.nota_medico || careResp?.notaMedico || null,
                prescripciones: Array.isArray(rxResp) ? rxResp : (rxResp?.data || []),
                med_regs: medRegs,
                cuidadores: cuidResp || { asignados: [], disponibles: [], can_edit: false },
                today,
            };
            _resExpandCache[resId] = data;
        }
        _resRenderSubcard(sub, resId, data, activeTab || 'notificaciones');
    } catch (e) {
        sub.innerHTML = `${_resSubcardCloseHtml(activeTab)}<div class="cd-res-subpanel"><div class="cd-res-subpanel-empty">No se pudo cargar la información. Intenta de nuevo.</div></div>`;
        sub.dataset.loaded = '';
    }
}

function _resRenderSubcard(sub, resId, data, activeTab) {
    const notifRows = _resNotifContactRows(data);
    const inviteRows = _resInvitationRows(data);
    const notifData = _resBuildNotifData(data, notifRows);
    const counts = {
        invitaciones: inviteRows.length,
        notificaciones: _resNotifEnabledCount(notifData.familiares),
        medicaciones: data.prescripciones.length,
        nota: data.nota_medico ? 1 : 0,
    };
    const effectiveTab = (activeTab === 'familiares' || activeTab === 'cuidadores' || activeTab === 'invitaciones') ? 'notificaciones' : (activeTab || 'notificaciones');
    let panelHtml = '';
    if (effectiveTab === 'invitaciones')       panelHtml = _resRenderInvitaciones(inviteRows, resId, data);
    else if (effectiveTab === 'notificaciones') panelHtml = _resRenderNotificaciones(notifData.familiares, resId, notifData);
    else if (effectiveTab === 'nota')           panelHtml = _resRenderNotaMedica(data.nota_medico);
    const canMove = !!window.__cdCanMoveResident || (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) || (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'superadmin');
    const card = sub.closest('.cd-res-mgmt-card');
    if (card) _resSetMgmtTabState(card, effectiveTab, counts, { canMove });
    sub.innerHTML = `${_resSubcardCloseHtml(effectiveTab)}<div class="cd-res-subpanel cd-fade-up-in">${panelHtml}</div>`;
    _resBindSubcardSearch(sub);
    if (effectiveTab === 'notificaciones') {
        _resBindNotifManager(sub, resId, notifData);
        sub.querySelector('[data-notif-open]')?.addEventListener('click', e => {
            e.stopPropagation();
            _resOpenResidentNotif(resId);
        });
        sub.querySelector('[data-notif-add-contact]')?.addEventListener('click', e => {
            e.stopPropagation();
            _resOpenFamiliarInvite(resId, {}, data, 'notificaciones');
        });
    } else if (effectiveTab === 'invitaciones') {
        _resBindInvitacionesFilters(sub, inviteRows, { resId, refreshTab: 'invitaciones' });
        sub.querySelectorAll('[data-inv-new], [data-fam-new]').forEach(btn => btn.addEventListener('click', e => {
            e.stopPropagation();
            _resOpenFamiliarInvite(resId, {}, data, 'invitaciones');
        }));
        sub.querySelectorAll('.cd-res-fam-item[data-inv-idx]').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('.cd-res-fam-actions,a,button,input,select,textarea,label')) return;
                e.stopPropagation();
                const item = inviteRows[parseInt(row.dataset.invIdx, 10)] || null;
                if (!item) return;
                if (item.kind === 'familiar') _resOpenFamiliarManage(resId, item.raw, data, 'invitaciones');
                else _resOpenCuidadorDetails(resId, item.raw, { ...(data.cuidadores || {}), _refreshTab: 'invitaciones' });
            });
            row.addEventListener('keydown', e => {
                if (!['Enter', ' '].includes(e.key)) return;
                if (e.target.closest('.cd-res-fam-actions,a,button,input,select,textarea,label')) return;
                e.preventDefault();
                const item = inviteRows[parseInt(row.dataset.invIdx, 10)] || null;
                if (!item) return;
                if (item.kind === 'familiar') _resOpenFamiliarManage(resId, item.raw, data, 'invitaciones');
                else _resOpenCuidadorDetails(resId, item.raw, { ...(data.cuidadores || {}), _refreshTab: 'invitaciones' });
            });
        });
        // Resend invite (familiar + cuidador pending)
        sub.querySelectorAll('[data-inv-resend-email], [data-inv-resend-wa], [data-inv-resend]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Reenviando')) return;
                const idx = parseInt(btn.dataset.invResendEmail || btn.dataset.invResendWa || btn.dataset.invResend, 10);
                const item = inviteRows[idx];
                const canal = btn.dataset.invResendEmail ? 'email' : (btn.dataset.invResendWa ? 'whatsapp' : 'all');
                try {
                    if (item?.kind === 'familiar') await _resResendFamiliarInvite(resId, item.id, canal, 'invitaciones');
                    else if (item?.kind === 'cuidador') await _resCuidResendInvite(resId, item.id, canal, 'invitaciones');
                } finally { _resFamActionEnd(btn); }
            });
        });
        // Create invite from legacy contact
        sub.querySelectorAll('[data-inv-create-email], [data-inv-create-wa]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                const item = inviteRows[parseInt(btn.dataset.invCreateEmail || btn.dataset.invCreateWa, 10)];
                if (!item?.legacyContact) return;
                if (btn.dataset.invCreateEmail) {
                    if (!_resFamActionStart(btn, 'Abriendo')) return;
                    _resOpenFamiliarInvite(resId, item.raw, data, 'invitaciones');
                    setTimeout(() => _resFamActionEnd(btn), 300);
                    return;
                }
                if (!_resFamActionStart(btn, 'Invitando')) return;
                try {
                    await _resInviteFamiliarWhatsApp(resId, item.raw, data, 'invitaciones');
                } finally { _resFamActionEnd(btn); }
            });
        });
        // Delete / unlink
        sub.querySelectorAll('[data-inv-delete]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Eliminando')) return;
                const item = inviteRows[parseInt(btn.dataset.invDelete, 10)];
                try {
                    if (item?.legacyContact) await _resDeleteFamiliar(resId, item.raw, data, 'invitaciones');
                    else if (item?.completed && item?.kind === 'familiar') await _resDeleteFamiliar(resId, item.raw, data, 'invitaciones');
                    else if (item?.kind === 'familiar') await _resDeleteFamiliarInvite(resId, item.id, 'invitaciones');
                    else if (item?.kind === 'cuidador') await _resCuidDeleteInvite(resId, item.id, 'invitaciones');
                } finally { _resFamActionEnd(btn); }
            });
        });
        // WhatsApp for registered familiar
        sub.querySelectorAll('[data-inv-wa]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Enviando')) return;
                const item = inviteRows[parseInt(btn.dataset.invWa, 10)];
                try {
                    if (item?.kind === 'familiar') await _resSendFamiliarWhatsApp(resId, item.raw, data);
                } finally { _resFamActionEnd(btn); }
            });
        });
        // Notifications for registered familiar
        sub.querySelectorAll('[data-inv-notif]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Abriendo')) return;
                const item = inviteRows[parseInt(btn.dataset.invNotif, 10)];
                if (item?.kind === 'familiar') _resOpenFamiliarNotif(resId, item.raw);
                setTimeout(() => _resFamActionEnd(btn), 500);
            });
        });
        // Manage for registered familiar
        sub.querySelectorAll('[data-inv-manage]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Abriendo')) return;
                const item = inviteRows[parseInt(btn.dataset.invManage, 10)];
                if (item?.kind === 'familiar') _resOpenFamiliarManage(resId, item.raw, data, 'invitaciones');
                setTimeout(() => _resFamActionEnd(btn), 300);
            });
        });
        // Edit staff assignments
        sub.querySelectorAll('[data-inv-cuid-edit]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                _resCloseFamMenus();
                _resOpenCuidadoresEdit(resId);
            });
        });
    }
}

function _resFamActionStart(btn, label = 'Procesando') {
    if (!btn || btn.dataset.busy === '1') return false;
    btn.dataset.busy = '1';
    btn.dataset.prevTitle = btn.getAttribute('title') || '';
    btn.classList.add('is-busy');
    btn.setAttribute('aria-busy', 'true');
    btn.setAttribute('title', label);
    if ('disabled' in btn) btn.disabled = true;
    const menuEl = btn.closest('.cd-res-fam-menu');
    const menu = btn.closest('.cd-res-fam-actions') || menuEl?._famMenuWrap;
    if (menu) {
        _resCloseFamMenu(menu);
    }
    const item = btn.closest('.cd-res-fam-item');
    item?.classList.add('is-action-busy');
    item?.querySelectorAll('.cd-res-fam-action').forEach(action => {
        if (action !== btn && 'disabled' in action) action.disabled = true;
    });
    return true;
}

function _resFamActionEnd(btn) {
    if (!btn) return;
    const item = btn.closest('.cd-res-fam-item');
    delete btn.dataset.busy;
    btn.classList.remove('is-busy');
    btn.removeAttribute('aria-busy');
    btn.setAttribute('title', btn.dataset.prevTitle || '');
    delete btn.dataset.prevTitle;
    if ('disabled' in btn) btn.disabled = false;
    item?.classList.remove('is-action-busy');
    item?.querySelectorAll('.cd-res-fam-action').forEach(action => {
        if ('disabled' in action) action.disabled = false;
    });
}

function _resFamIcon(name) {
    return `<span class="material-symbols-outlined cd-res-fam-menu-ic" aria-hidden="true">${esc(name)}</span>`;
}

function _resFamMenuItem({ href = '', attrs = '', cls = '', icon = 'circle', label = '', title = '', locked = false, lockTitle = '', lockMsg = '' }) {
    const titleAttr = title ? ` title="${esc(title)}"` : '';
    const lockAttrs = locked ? ` data-cd-locked data-lock-title="${esc(lockTitle)}" data-lock-msg="${esc(lockMsg)}"` : '';
    const lockedCls = locked ? ' cd-role-locked' : '';
    const content = `${_resFamIcon(icon)}<span>${esc(label)}</span>`;
    if (href) {
        return `<a class="cd-res-fam-menu-item ${cls}${lockedCls}" href="${href}"${titleAttr}${lockAttrs} onclick="event.stopPropagation()">${content}</a>`;
    }
    return `<button type="button" class="cd-res-fam-menu-item cd-res-fam-action ${cls}${lockedCls}" ${attrs}${lockAttrs}${titleAttr}>${content}</button>`;
}

function _resFamMenu(actions) {
    if (!actions.length) return '';
    return `<div class="cd-res-fam-actions">
        <button type="button" class="cd-res-fam-menu-btn" data-fam-menu-toggle aria-haspopup="menu" aria-expanded="false" title="Acciones">
            ${_resFamIcon('more_vert')}
        </button>
        <div class="cd-res-fam-menu" role="menu">${actions.join('')}</div>
    </div>`;
}

function _resCloseFamMenu(wrap) {
    if (!wrap) return;
    const menu = wrap._famPortalMenu || wrap.querySelector(':scope > .cd-res-fam-menu');
    wrap.classList.remove('is-open');
    wrap.querySelector('[data-fam-menu-toggle]')?.setAttribute('aria-expanded', 'false');
    if (menu) {
        menu.classList.remove('is-portal-open');
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.maxWidth = '';
        menu.style.width = '';
        if (wrap._famMenuPlaceholder?.parentNode) {
            wrap._famMenuPlaceholder.parentNode.replaceChild(menu, wrap._famMenuPlaceholder);
        } else if (!wrap.contains(menu)) {
            wrap.appendChild(menu);
        }
        menu._famMenuWrap = null;
    }
    wrap._famPortalMenu = null;
    wrap._famMenuPlaceholder = null;
}

function _resCloseFamMenus() {
    document.querySelectorAll('.cd-res-fam-actions.is-open').forEach(_resCloseFamMenu);
}

function _resRegisteredFamiliares(data = {}) {
    return (Array.isArray(data.familiares) ? data.familiares : [])
    .filter(f => !(!f?.usuario_id && f?.invitacion_id))
    .filter(f => !!f?.usuario_id);
}

function _resFamiliaresPanelRows(data = {}) {
    return (Array.isArray(data.familiares) ? data.familiares : [])
        .filter(item => !(item?.__notifStaff || item?.source === 'staff_notif'))
        .filter(item => String(item?.nombre || item?.email || item?.telefono || '').trim() || item?.usuario_id || item?.invitacion_id);
}

function _resNotifContactRows(data = {}) {
    return (Array.isArray(data.familiares) ? data.familiares : [])
        .filter(f => !(!f?.usuario_id && f?.invitacion_id));
}

function _resNotifGroupForRole(rol) {
    rol = String(rol || '').toLowerCase();
    if (rol === 'admin') return 'admins';
    if (rol === 'medico') return 'medicos';
    if (rol === 'enfermero' || rol === 'cuidador') return 'cuidadores';
    return 'familiares';
}

function _resNotifInviteName(inv, fallback = 'Invitación pendiente') {
    const raw = inv?.invitacion || inv || {};
    return inv?.nombre
        || [raw.nombre_sugerido, raw.apellido_sugerido].filter(Boolean).join(' ')
        || inv?.email
        || raw.email
        || fallback;
}

function _resNotifAudience(list = [], data = {}) {
    const audience = [];
    const seen = new Set();
    const add = (f, sourceIdx, group, opts = {}) => {
        const name = String(f?.nombre || opts.name || '').trim();
        const email = String(f?.email || '').trim();
        const phone = String(f?.telefono || '').trim();
        if (!name && !email && !phone) return;
        const userId = opts.userId || f?.usuario_id || f?.user_id || f?.id || '';
        const idKeys = [userId ? `u:${userId}` : '', email ? `e:${email.toLowerCase()}` : '', phone ? `p:${phone.replace(/\D+/g, '')}` : ''].filter(Boolean);
        const key = idKeys.length ? idKeys.join('|') : [group, name.toLowerCase()].filter(Boolean).join('|');
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        audience.push({
            f: { ...f, __notifGroup: group, __notifReadonly: !!opts.readonly, __notifRoleLabel: opts.roleLabel || '' },
            sourceIdx,
            group,
        });
    };
    const registeredKeys = _resRegisteredContactKeys(data);
    const hasStaffRows = (Array.isArray(list) ? list : []).some(f => f?.__notifStaff || f?.source === 'staff_notif');
    (Array.isArray(list) ? list : []).forEach((f, idx) => {
        const isStaff = !!(f?.__notifStaff || f?.source === 'staff_notif');
        add(f, idx, isStaff ? (f.__notifGroup || _resNotifGroupForRole(f.rol)) : 'familiares', {
            readonly: !!f.__notifReadonly,
            roleLabel: f.__notifRoleLabel || (isStaff ? _resRoleLabel(f.rol) : ''),
            userId: f.usuario_id || f.user_id || f._usuario_id || f.id || '',
        });
    });
    const familyInviteSource = [
        ...(Array.isArray(data.familiares) ? data.familiares : []),
        ...(Array.isArray(data.familiaresInvitaciones) ? data.familiaresInvitaciones : []),
    ];
    familyInviteSource.forEach((f, idx) => {
        if (f?.usuario_id || !f?.invitacion_id) return;
        const status = String(f?.invitacion?.status || f?.estado_cuenta || f?.estado || 'pendiente').toLowerCase();
        if (status === 'aceptada' || _resInviteBelongsToRegisteredUser(f, registeredKeys)) return;
        const inv = f.invitacion || {};
        add({
            ...f,
            nombre: _resNotifInviteName(f, 'Invitación familiar'),
            email: f.email || inv.email || '',
            telefono: f.telefono || inv.telefono || '',
            parentesco: 'Familiar invitado',
        }, `invite-f-${f.invitacion_id || idx}`, 'familiares', {
            readonly: true,
            roleLabel: 'Familiar invitado',
        });
    });
    if (!hasStaffRows) {
        _resNotifStaffRows(data).forEach((c, idx) => {
            add(c, `staff-${idx}`, c.__notifGroup || _resNotifGroupForRole(c.rol), {
                roleLabel: c.__notifRoleLabel || _resRoleLabel(c.rol),
                userId: c.usuario_id || c.user_id || c.id || '',
            });
        });
    }
    return audience;
}

function _resNotifGroupLabel(group) {
    return ({ familiares: 'Familiares', admins: 'Administradores', cuidadores: 'Cuidadores', medicos: 'Médicos' })[group] || 'Destinatarios';
}

function _resNotifFilterChips(audience = []) {
    const counts = audience.reduce((acc, item) => {
        acc[item.group] = (acc[item.group] || 0) + 1;
        return acc;
    }, {});
    const defs = [
        ['all', 'Todos', audience.length],
        ['familiares', 'Familiares', counts.familiares || 0],
        ['admins', 'Admin', counts.admins || 0],
        ['cuidadores', 'Cuidadores', counts.cuidadores || 0],
        ['medicos', 'Médicos', counts.medicos || 0],
    ];
    return `<div class="cd-res-notif-filter-chips" role="toolbar" aria-label="Filtrar destinatarios de notificaciones">
        ${defs.map(([key, label, count], idx) => `<button type="button" class="cd-res-notif-filter-chip${idx === 0 ? ' is-active' : ''}" data-res-notif-filter="${key}" ${count ? '' : 'disabled aria-disabled="true"'}><span>${esc(label)}</span><b>${count}</b></button>`).join('')}
    </div>`;
}

function _resNotifApplyAudienceFilter(manager, group = 'all') {
    if (!manager) return;
    manager.querySelectorAll('[data-res-notif-filter]').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.resNotifFilter === group);
    });
    manager.querySelectorAll('[data-notif-group]').forEach(el => {
        const visible = group === 'all' || el.dataset.notifGroup === group;
        el.style.display = visible ? '' : 'none';
        const cb = el.querySelector?.('input[type="checkbox"]');
        if (cb) cb.checked = visible;
    });
}

function _resFamFilterKey(value) {
    return String(value || 'familiar').trim().toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'familiar';
}

function _resFamRoleLabel(item = {}) {
    const role = String(item.rol || '').trim();
    if (role) return _resRoleLabel(role);
    return String(item.parentesco || item.relacion || item.role || 'Familiar').trim() || 'Familiar';
}

function _resFamStatusKey(item = {}) {
    const inv = item.invitacion || {};
    const status = String(inv.status || item.estado || item.estado_cuenta || '').toLowerCase();
    if (status === 'revocada') return 'expirada';
    if (status === 'expirada') return 'expirada';
    if (item.usuario_id || item.invitacion_id || status === 'registrado' || status === 'invitado' || status === 'aceptada') return 'enviada';
    return 'creada';
}

function _resInvStatusKey(item = {}) {
    const status = String(item.estado || item.raw?.invitacion?.status || '').toLowerCase();
    if (status === 'revocada') return 'revocada';
    if (status === 'expirada') return 'expirada';
    if (item.completed || status === 'completada' || status === 'registrado' || status === 'aceptada') return 'completada';
    if (_resInvitationSentChannels(item).length || _resInvitationErrorChannels(item).length) return 'enviada';
    if (item.noEnviada || item.legacyContact || !String(item.enviada || '').trim()) return 'creada';
    return 'enviada';
}

function _resInvitationDelivery(item = {}) {
    return item.delivery || item.raw?.delivery || item.raw?.invitacion?.delivery || {};
}

function _resInvitationChannelState(item = {}, channel = 'email') {
    const delivery = _resInvitationDelivery(item);
    const details = delivery?.[channel] || null;
    if (details && typeof details === 'object') return String(details.estado || '').toLowerCase();
    if (channel === 'email' && delivery?.email_enviado) return 'enviado';
    if (channel === 'whatsapp' && delivery?.whatsapp_enviado) return 'enviado';
    if (channel === 'email' && delivery?.email_error) return 'error';
    if (channel === 'whatsapp' && delivery?.whatsapp_error) return 'error';
    return '';
}

function _resInvitationSentChannels(item = {}) {
    const channels = [];
    if (_resInvitationChannelState(item, 'email') === 'enviado') channels.push('email');
    if (_resInvitationChannelState(item, 'whatsapp') === 'enviado') channels.push('whatsapp');
    return channels;
}

function _resInvitationErrorChannels(item = {}) {
    const channels = [];
    if (_resInvitationChannelState(item, 'email') === 'error') channels.push('email');
    if (_resInvitationChannelState(item, 'whatsapp') === 'error') channels.push('whatsapp');
    return channels;
}

function _resInvitationHasDelivery(item = {}) {
    const delivery = _resInvitationDelivery(item);
    return !!(delivery && (delivery.email || delivery.whatsapp || delivery.email_enviado || delivery.whatsapp_enviado || delivery.email_error || delivery.whatsapp_error));
}

function _resInvitationStatusLabel(item = {}, stateKey = '') {
    const status = String(item.estado || item.raw?.invitacion?.status || '').toLowerCase();
    if (item.completed || status === 'completada' || status === 'registrado' || status === 'aceptada') return item.estado === 'registrado' ? 'Registrado' : 'Completada';
    if (status === 'revocada') return 'Revocada';
    if (status === 'expirada') return 'Expirada';
    const errors = _resInvitationErrorChannels(item);
    if (errors.length) return errors.includes('whatsapp') ? 'Error WhatsApp' : 'Error correo';
    const sent = _resInvitationSentChannels(item);
    if (sent.includes('whatsapp') && sent.includes('email')) return 'Correo + WhatsApp';
    if (sent.includes('whatsapp')) return 'WhatsApp enviado';
    if (sent.includes('email')) return 'Correo enviado';
    if (stateKey === 'enviada') return 'Enviada';
    if (item.noEnviada || item.legacyContact) return 'Creada';
    return 'Pendiente';
}

function _resFamFilterChips(kind, defs, ariaLabel) {
    const attr = kind === 'role' ? 'data-res-fam-filter-role' : 'data-res-fam-filter-status';
    return `<div class="cd-res-notif-filter-chips cd-res-fam-filter-chips" role="toolbar" aria-label="${esc(ariaLabel)}">
        ${defs.map((def, idx) => `<button type="button" class="cd-res-notif-filter-chip${idx === 0 ? ' is-active' : ''}" ${attr}="${esc(def.key)}" ${def.count ? '' : 'disabled aria-disabled="true"'}><span>${esc(def.label)}</span><b>${def.count}</b></button>`).join('')}
    </div>`;
}

function _resInvFilterChips(kind, defs, ariaLabel) {
    const attr = kind === 'role' ? 'data-res-inv-filter-role' : 'data-res-inv-filter-status';
    return `<div class="cd-res-notif-filter-chips cd-res-inv-filter-chips" role="toolbar" aria-label="${esc(ariaLabel)}">
        ${defs.map((def, idx) => `<button type="button" class="cd-res-notif-filter-chip${idx === 0 ? ' is-active' : ''}" ${attr}="${esc(def.key)}" ${def.count ? '' : 'disabled aria-disabled="true"'}><span>${esc(def.label)}</span><b>${def.count}</b></button>`).join('')}
    </div>`;
}

function _resFamRoleFilterDefs(list = []) {
    const counts = new Map();
    (Array.isArray(list) ? list : []).forEach(item => {
        const label = _resFamRoleLabel(item);
        const key = _resFamFilterKey(label);
        const current = counts.get(key) || { key, label, count: 0 };
        current.count += 1;
        counts.set(key, current);
    });
    return [{ key: 'all', label: 'Todos', count: list.length }, ...Array.from(counts.values()).sort((a, b) => b.count - a.count || a.label.localeCompare(b.label))];
}

function _resFamStatusFilterDefs(list = []) {
    const counts = (Array.isArray(list) ? list : []).reduce((acc, item) => {
        const key = _resFamStatusKey(item);
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});
    return [
        { key: 'all', label: 'Todas', count: list.length },
        { key: 'creada', label: 'Creada', count: counts.creada || 0 },
        { key: 'enviada', label: 'Enviada', count: counts.enviada || 0 },
        { key: 'expirada', label: 'Expiradas', count: counts.expirada || 0 },
        { key: 'completada', label: 'Completadas', count: counts.completada || 0 },
    ];
}

function _resInvStatusFilterDefs(list = []) {
    const counts = (Array.isArray(list) ? list : []).reduce((acc, item) => {
        const key = _resInvStatusKey(item);
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});
    return [
        { key: 'all', label: 'Todas', count: list.length },
        { key: 'creada', label: 'Creada', count: counts.creada || 0 },
        { key: 'enviada', label: 'Enviada', count: counts.enviada || 0 },
        { key: 'expirada', label: 'Expirada', count: counts.expirada || 0 },
        { key: 'completada', label: 'Completadas', count: counts.completada || 0 },
    ];
}

function _resBindInvitacionesFilters(sub, list = [], context = {}) {
    list = Array.isArray(list) ? list : [];
    const refreshAfterBulk = async () => {
        if (context.refreshTab === 'viewInvitaciones' || !context.resId) {
            if (typeof loadInvitaciones === 'function') await loadInvitaciones();
            return;
        }
        if (typeof _resReloadExpandedSubcard === 'function') await _resReloadExpandedSubcard(context.resId, context.refreshTab || 'invitaciones');
    };
    const apply = () => {
        const role = sub.querySelector('[data-res-inv-filter-role].is-active')?.dataset.resInvFilterRole || 'all';
        const status = sub.querySelector('[data-res-inv-filter-status].is-active')?.dataset.resInvFilterStatus || 'all';
        const query = String(sub.querySelector('[data-res-sub-search="invitaciones"]')?.value || '').trim().toLowerCase();
        const resFilter = sub.querySelector('[data-inv-res-dropdown]')?.dataset.invResValue || '';
        let shown = 0;
        sub.querySelectorAll('.cd-res-inv-list .cd-res-inv-item[data-inv-role][data-inv-state]').forEach(row => {
            const okRole = role === 'all' || row.dataset.invRole === role;
            const okStatus = status === 'all' || row.dataset.invState === status;
            const okSearch = !query || String(row.dataset.resSearchText || '').toLowerCase().includes(query);
            let okRes = true;
            if (resFilter) { try { okRes = JSON.parse(row.dataset.invResIds || '[]').map(String).includes(resFilter); } catch(e) { okRes = false; } }
            const visible = okRole && okStatus && okSearch && okRes;
            const group = row.closest('.cd-inv-row-group');
            if (!visible) row.querySelectorAll('[data-inv-select]').forEach(ch => { ch.checked = false; });
            if (group) { group.hidden = !visible; group.style.display = visible ? '' : 'none'; } else { row.hidden = !visible; row.style.display = visible ? '' : 'none'; }
            row.classList.toggle('is-search-hidden', !visible);
            if (visible) shown++;
        });
        const empty = sub.querySelector('[data-res-search-empty]');
        if (empty) empty.hidden = shown > 0;
        updateBulkState();
    };
    const selectedChecks = () => Array.from(sub.querySelectorAll('[data-inv-select]:checked'));
    const selectedItems = () => selectedChecks().map(ch => list[parseInt(ch.dataset.invSelect, 10)]).filter(Boolean);
    const updateBulkState = () => {
        const selected = selectedChecks();
        const bar = sub.querySelector('[data-inv-bulkbar]');
        const count = sub.querySelector('[data-inv-bulk-count]');
        const selectAll = sub.querySelector('[data-inv-select-all]');
        if (bar) bar.hidden = selected.length === 0;
        if (count) count.textContent = String(selected.length);
        if (selectAll) {
            const visible = Array.from(sub.querySelectorAll('.cd-inv-row-group:not([hidden]) [data-inv-select]'));
            selectAll.checked = visible.length > 0 && visible.every(ch => ch.checked);
            selectAll.indeterminate = visible.some(ch => ch.checked) && !selectAll.checked;
        }
    };
    sub.querySelectorAll('[data-res-inv-filter-role], [data-res-inv-filter-status]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            if (btn.disabled) return;
            const selector = btn.dataset.resInvFilterRole !== undefined ? '[data-res-inv-filter-role]' : '[data-res-inv-filter-status]';
            sub.querySelectorAll(selector).forEach(item => item.classList.toggle('is-active', item === btn));
            apply();
        });
    });
    sub.querySelector('[data-res-sub-search="invitaciones"]')?.addEventListener('input', apply);
    sub.querySelector('[data-res-sub-search-clear="invitaciones"]')?.addEventListener('click', apply);
    sub.querySelector('[data-inv-select-all]')?.addEventListener('change', e => {
        const checked = !!e.target.checked;
        sub.querySelectorAll('.cd-inv-row-group:not([hidden]) [data-inv-select]').forEach(ch => { ch.checked = checked; });
        updateBulkState();
    });
    sub.querySelectorAll('[data-inv-select]').forEach(ch => ch.addEventListener('change', e => { e.stopPropagation(); updateBulkState(); }));
    sub.querySelectorAll('[data-inv-bulk-action]').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            const items = selectedItems();
            if (!items.length) return;
            const action = btn.dataset.invBulkAction;
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">hourglass_top</span><span>Procesando</span>';
            try {
                if (action === 'delete') {
                    const ok = await cdConfirm(`¿Eliminar ${items.length} invitación(es)?`, { title:'Eliminar invitaciones', type:'danger', okText:'Eliminar' });
                    if (!ok) return;
                    for (const item of items) {
                        if (!item?.id) continue;
                        await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'eliminar', id:item.id }) });
                    }
                    showToast?.(`${items.length} invitación(es) eliminada(s)`, 'success');
                } else if (action === 'email' || action === 'whatsapp') {
                    for (const item of items) {
                        if (!item?.id) continue;
                        await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'reenviar', id:item.id, canal:action }) });
                    }
                    showToast?.(`${items.length} invitación(es) reenviada(s)`, 'success');
                } else if (action === 'role') {
                    const role = sub.querySelector('[data-inv-bulk-role]')?.value || 'familiar';
                    for (const item of items) {
                        const inv = item.raw?.invitacion || item.raw || {};
                        await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                            action:'actualizar', id:item.id, rol:role,
                            email:item.email || inv.email || '', telefono:item.telefono || inv.telefono || '',
                            nombre_sugerido:inv.nombre_sugerido || '', apellido_sugerido:inv.apellido_sugerido || '', mensaje:inv.mensaje || ''
                        }) });
                    }
                    showToast?.(`Rol actualizado en ${items.length} invitación(es)`, 'success');
                }
                await refreshAfterBulk();
            } catch(err) {
                showToast?.(err?.message || 'No se pudo completar la acción masiva', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        });
    });
    // Expand row toggle
    sub.querySelectorAll('[data-inv-exp]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const ep = sub.querySelector(`[data-inv-exp-panel="${btn.dataset.invExp}"]`);
            if (!ep) return;
            const open = !ep.hidden;
            ep.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            btn.classList.toggle('is-open', !open);
        });
    });
    // Resident dropdown filter
    const resDD = sub.querySelector('[data-inv-res-dropdown]');
    if (resDD) {
        const ddToggle = resDD.querySelector('[data-inv-res-toggle]');
        const ddPanel = resDD.querySelector('.cd-inv-res-dropdown-panel');
        const ddLabel = resDD.querySelector('[data-inv-res-label]');
        const ddSearch = resDD.querySelector('[data-inv-res-search]');
        const ddOpts = resDD.querySelector('[data-inv-res-options]');
        const filterOpts = q => { const lo = q.toLowerCase(); ddOpts?.querySelectorAll('.cd-inv-res-option').forEach(o => { o.hidden = !!lo && !o.textContent.toLowerCase().includes(lo); }); };
        const positionDD = () => {
            if (!ddPanel || !ddToggle || ddPanel.hidden) return;
            const r = ddToggle.getBoundingClientRect();
            ddPanel.style.position = 'fixed';
            ddPanel.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - 292))}px`;
            ddPanel.style.top = `${Math.min(r.bottom + 4, window.innerHeight - 260)}px`;
            ddPanel.style.width = `${Math.max(220, r.width)}px`;
            ddPanel.style.zIndex = '10020';
        };
        const closeDD = () => { if (ddPanel) { ddPanel.hidden = true; ddToggle?.setAttribute('aria-expanded', 'false'); if (ddSearch) ddSearch.value = ''; filterOpts(''); } };
        const openDD = () => { if (ddPanel) { ddPanel.hidden = false; ddToggle?.setAttribute('aria-expanded', 'true'); positionDD(); ddSearch?.focus(); } };
        const selectOpt = opt => {
            ddOpts?.querySelectorAll('.cd-inv-res-option').forEach(o => o.classList.toggle('is-active', o === opt));
            resDD.dataset.invResValue = opt.dataset.invResId || '';
            if (ddLabel) ddLabel.textContent = String(opt.textContent || '').trim();
            closeDD(); apply();
        };
        ddToggle?.addEventListener('click', e => { e.stopPropagation(); ddPanel?.hidden ? openDD() : closeDD(); });
        ddSearch?.addEventListener('input', e => filterOpts(e.target.value));
        ddOpts?.addEventListener('click', e => { const o = e.target.closest('.cd-inv-res-option'); if (o) { e.stopPropagation(); selectOpt(o); } });
        window.addEventListener('resize', positionDD);
        window.addEventListener('scroll', positionDD, true);
        document.addEventListener('click', e => { if (resDD.isConnected && !resDD.contains(e.target)) closeDD(); });
    }
    apply();
}

function _resCuidadoresInviteList(payload) {
    payload = payload || { invitaciones: [] };
    return Array.isArray(payload.invitaciones) ? payload.invitaciones.map(i => ({ ...i, pending_invite: true })) : [];
}

const RES_NOTIF_PREF_FIELDS = [
    'notif_emergencia','notif_signos','notif_incidentes','notif_caida','notif_medicacion','notif_med_omitida','notif_alimentacion','notif_higiene','notif_eliminacion','notif_sueno','notif_animo','notif_movilidad','notif_terapia','notif_reporte','notif_reporte_hora','notif_reporte_pdf','notif_semanal','notif_notas_medico','notif_visitas','notif_stock','notif_solo_criticas','notif_quiet_enabled','notif_quiet_start','notif_quiet_end','notif_wa','notif_email'
];

function _resNotifKey(item = {}) {
    const uid = item.usuario_id || item.user_id || item._usuario_id || item.id || '';
    const email = _resCleanKey(item.email || '');
    const phone = _resPhoneKey(item.telefono || '');
    return [uid ? `u:${uid}` : '', email ? `e:${email}` : '', phone ? `p:${phone}` : ''].filter(Boolean).join('|');
}

function _resStoredNotifRows(data = {}) {
    const raw = data?.residente?.contactos_json || data?.contactos_json || '';
    if (!raw) return [];
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter(c => c && (c.source === 'staff_notif' || c.__notifStaff)) : [];
    } catch(e) { return []; }
}

function _resMergeNotifPrefs(row, storedRows = []) {
    const key = _resNotifKey(row);
    const stored = storedRows.find(s => _resNotifKey(s) === key) || null;
    if (!stored) return row;
    RES_NOTIF_PREF_FIELDS.forEach(field => {
        if (stored[field] !== undefined) row[field] = stored[field];
    });
    return row;
}

function _resNotifStaffRows(data = {}) {
    const payload = data.cuidadores || {};
    const storedRows = _resStoredNotifRows(data);
    const rows = [];
    const seen = new Set();
    const add = (c, status) => {
        const rol = String(c?.rol || '').toLowerCase();
        if (!['admin', 'medico', 'enfermero', 'cuidador'].includes(rol)) return;
        if (c?.pending_invite || c?.invitacion_id) return;
        const key = _resNotifKey(c) || [rol, c?.nombre || c?.email || c?.telefono || ''].join('|').toLowerCase();
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        const roleLabel = _resRoleLabel(rol);
        rows.push(_resMergeNotifPrefs({
            ...c,
            source: 'staff_notif',
            __notifStaff: true,
            __notifGroup: _resNotifGroupForRole(rol),
            __notifRoleLabel: roleLabel,
            __notifStatus: status,
            usuario_id: c.usuario_id || c.user_id || c.id || '',
            parentesco: roleLabel,
        }, storedRows));
    };
    (Array.isArray(payload.asignados) ? payload.asignados : []).forEach(c => add(c, 'asignado'));
    (Array.isArray(payload.disponibles) ? payload.disponibles : []).forEach(c => add(c, 'disponible'));
    return rows;
}

function _resBuildNotifData(data = {}, familyRows = []) {
    const families = (Array.isArray(familyRows) ? familyRows : []).filter(f => f && f.source !== 'staff_notif' && !f.__notifStaff);
    return { ...data, familiares: [...families, ..._resNotifStaffRows(data)] };
}

function _resNotifPersistContact(contact = {}) {
    const isStaff = contact.__notifStaff || contact.source === 'staff_notif';
    const out = {
        nombre: contact.nombre || '',
        parentesco: contact.parentesco || '',
        telefono: contact.telefono || '',
        telefono2: contact.telefono2 || '',
        email: contact.email || '',
        direccion: contact.direccion || '',
    };
    if (contact.principal) out.principal = 1;
    if (contact._usuario_id || contact.usuario_id) out._usuario_id = contact._usuario_id || contact.usuario_id;
    if (isStaff) {
        out.source = 'staff_notif';
        out.__notifStaff = true;
        out.rol = contact.rol || '';
        out.usuario_id = contact.usuario_id || contact._usuario_id || contact.id || '';
    } else if (contact.source) {
        out.source = contact.source;
    }
    RES_NOTIF_PREF_FIELDS.forEach(field => {
        if (contact[field] !== undefined) out[field] = contact[field];
    });
    return out;
}

function _resCleanKey(value) {
    return String(value || '').trim().toLowerCase();
}

function _resPhoneKey(value) {
    return String(value || '').replace(/\D+/g, '');
}

function _resRegisteredContactKeys(data = {}) {
    const emails = new Set();
    const phones = new Set();
    const add = item => {
        const email = _resCleanKey(item?.email || item?.invitacion?.email || '');
        const phone = _resPhoneKey(item?.telefono || item?.invitacion?.telefono || '');
        if (email) emails.add(email);
        if (phone) phones.add(phone);
    };
    (Array.isArray(data.familiares) ? data.familiares : []).filter(f => !!f?.usuario_id).forEach(add);
    _resCuidadoresList(data.cuidadores).forEach(c => { if (!c?.pending_invite && c?.id) add(c); });
    return { emails, phones };
}

function _resInviteBelongsToRegisteredUser(item, keys) {
    const raw = item?.raw || item || {};
    const email = _resCleanKey(item?.email || raw.email || raw.invitacion?.email || '');
    const phone = _resPhoneKey(item?.telefono || raw.telefono || raw.invitacion?.telefono || '');
    return (email && keys.emails.has(email)) || (phone && keys.phones.has(phone));
}

function _resSubcardSearchHtml(scope, placeholder = 'Buscar') {
    return `<div class="cd-res-sub-search" data-res-search-scope="${esc(scope)}">
        <span class="material-symbols-outlined" aria-hidden="true">search</span>
        <input type="search" class="cd-res-sub-search-input" data-res-sub-search="${esc(scope)}" placeholder="${esc(placeholder)}" autocomplete="off">
        <button type="button" class="cd-res-sub-search-clear" data-res-sub-search-clear="${esc(scope)}" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">&times;</button>
    </div>`;
}

function _resBindSubcardSearch(sub) {
    const input = sub.querySelector('[data-res-sub-search]');
    if (!input) return;
    const clear = sub.querySelector(`[data-res-sub-search-clear="${input.dataset.resSubSearch}"]`);
    const empty = sub.querySelector('[data-res-search-empty]');
    const apply = () => {
        const q = input.value.trim().toLowerCase();
        let shown = 0;
        sub.querySelectorAll('[data-res-search-text]').forEach(row => {
            const ok = !q || String(row.dataset.resSearchText || '').toLowerCase().includes(q);
            row.hidden = !ok;
            row.classList.toggle('is-search-hidden', !ok);
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (empty) empty.hidden = !q || shown > 0;
        if (clear) clear.hidden = !q;
    };
    input.addEventListener('input', apply);
    clear?.addEventListener('click', e => {
        e.stopPropagation();
        input.value = '';
        input.focus();
        apply();
    });
    apply();
}

function _resInvitationRows(data = {}) {
    const rows = [];
    const seen = new Set();
    const registeredKeys = _resRegisteredContactKeys(data);
    const familyInviteSource = [
        ...(Array.isArray(data.familiares) ? data.familiares : []),
        ...(Array.isArray(data.familiaresInvitaciones) ? data.familiaresInvitaciones : []),
    ];
    familyInviteSource.forEach(f => {
        if (f?.usuario_id) {
            const key = `u:${f.usuario_id}`;
            if (seen.has(key)) return;
            seen.add(key);
            const inv = f.invitacion || {};
            rows.push({
                kind: 'familiar', id: f.invitacion_id || `user-${f.usuario_id}`, role: 'Familiar', raw: f,
                completed: true,
                name: f.nombre || [inv.nombre_sugerido, inv.apellido_sugerido].filter(Boolean).join(' ') || 'Familiar registrado',
                email: inv.email || f.email || '', telefono: inv.telefono || f.telefono || '',
                estado: 'registrado', enviada: inv.enviada || '', expira: inv.expira || '',
                creadoPor: inv.creado_por_nombre || f.creado_por_nombre || '', revocadoPor: inv.revocado_por_nombre || f.revocado_por_nombre || '',
            });
            return;
        }
        if (!f?.invitacion_id) {
            const isLegacyContact = f?.source === 'contacto' || (f?.estado_cuenta || '') === 'contacto';
            const hasIdentity = String(f?.nombre || '').trim() || String(f?.email || '').trim() || String(f?.telefono || '').trim();
            if (!isLegacyContact || !hasIdentity) return;
            if (_resInviteBelongsToRegisteredUser(f, registeredKeys)) return;
            const rawIdx = Number.isFinite(Number(f.contact_idx)) ? Number(f.contact_idx) : rows.length;
            const keyParts = [
                _resCleanKey(f.email || ''),
                _resPhoneKey(f.telefono || ''),
                _resCleanKey(f.nombre || ''),
                String(rawIdx),
            ].filter(Boolean).join('|');
            const key = `legacy:${keyParts}`;
            if (seen.has(key)) return;
            seen.add(key);
            rows.push({
                kind: 'familiar', id: `legacy-${rawIdx}`, role: 'Familiar', raw: f,
                legacyContact: true, noEnviada: true,
                name: f.nombre || 'Contacto familiar', email: f.email || '', telefono: f.telefono || '',
                estado: 'pendiente', enviada: '', expira: '',
                creadoPor: 'Contacto legacy', revocadoPor: '',
            });
            return;
        }
        if (_resInviteBelongsToRegisteredUser(f, registeredKeys)) return;
        const key = `f:${f.invitacion_id}`;
        if (seen.has(key)) return;
        seen.add(key);
        const inv = f.invitacion || {};
        rows.push({
            kind: 'familiar', id: f.invitacion_id, role: 'Familiar', raw: f,
            name: f.nombre || [inv.nombre_sugerido, inv.apellido_sugerido].filter(Boolean).join(' ') || 'Invitación familiar',
            email: inv.email || f.email || '', telefono: inv.telefono || f.telefono || '',
            estado: inv.status || f.estado_cuenta || 'pendiente', enviada: inv.enviada || '', expira: inv.expira || '',
            creadoPor: inv.creado_por_nombre || f.creado_por_nombre || '', revocadoPor: inv.revocado_por_nombre || f.revocado_por_nombre || '',
        });
    });
    _resCuidadoresInviteList(data.cuidadores).forEach(c => {
        if (!c?.invitacion_id) return;
        if (_resInviteBelongsToRegisteredUser(c, registeredKeys)) return;
        const key = `c:${c.invitacion_id}`;
        if (seen.has(key)) return;
        seen.add(key);
        rows.push({
            kind: 'cuidador', id: c.invitacion_id, role: _resRoleLabel(c.rol), raw: c,
            name: c.nombre || c.email || 'Invitación pendiente', email: c.email || '', telefono: c.telefono || '',
            estado: c.estado || 'pendiente', enviada: c.enviada || '', expira: c.expira || '',
            creadoPor: c.creado_por_nombre || '', revocadoPor: c.revocado_por_nombre || '',
        });
    });
    // Add registered staff (asignados with user accounts, all roles)
    const staffList = Array.isArray(data.cuidadores?.asignados) ? data.cuidadores.asignados : [];
    staffList.forEach(c => {
        if (c?.pending_invite || c?.invitacion_id) return;
        const uid = c?.id || c?.usuario_id || c?.user_id || '';
        const email = _resCleanKey(c?.email || '');
        const phone = _resPhoneKey(c?.telefono || '');
        const key = uid ? `reg:${uid}` : (email ? `reg-e:${email}` : (phone ? `reg-p:${phone}` : ''));
        if (!key) return;
        if (seen.has(key)) return;
        seen.add(key);
        rows.push({
            kind: 'cuidador', id: `staff-${uid || email || phone}`, role: _resRoleLabel(c.rol), raw: c,
            completed: true, registered: true,
            name: c.nombre || c.email || 'Sin nombre', email: c.email || '', telefono: c.telefono || '',
            estado: 'registrado', enviada: '', expira: '',
            creadoPor: '', revocadoPor: '',
        });
    });
    return rows.sort((a, b) => String(b.enviada || '').localeCompare(String(a.enviada || '')));
}

function _resRoleLabel(rol) {
    return ({ admin:'Admin', medico:'Médico', enfermero:'Cuidador', cuidador:'Cuidador', familiar:'Familiar' })[rol] || rol || 'Usuario';
}

function _resRoleRank(rol) {
    return ({ admin:4, medico:3, enfermero:2, cuidador:2, familiar:1 })[String(rol || '').toLowerCase()] || 0;
}

function _resToTitleCase(str) {
    return String(str || '').toLowerCase().replace(/(?:^|\s)\S/g, c => c.toUpperCase());
}

function _resInvitationChannelHtml(item) {
    const channels = [];
    const hasDelivery = _resInvitationHasDelivery(item);
    const emailSent = _resInvitationChannelState(item, 'email') === 'enviado';
    const waSent = _resInvitationChannelState(item, 'whatsapp') === 'enviado';
    if (hasDelivery ? emailSent : String(item?.email || '').trim()) {
        channels.push(`<span class="cd-inv-channel" title="Enviada por correo"><span class="material-symbols-outlined" aria-hidden="true">mail</span></span>`);
    }
    if (hasDelivery ? waSent : String(item?.telefono || '').trim()) {
        channels.push(`<span class="cd-inv-channel" title="Enviada por WhatsApp"><span class="material-symbols-outlined" aria-hidden="true">send_to_mobile</span></span>`);
    }
    return channels.length ? `<span class="cd-inv-channels" aria-label="Canales de envío">${channels.join('')}</span>` : '';
}

function _resInvitationStepHtml(item) {
    const status = String(item.estado || 'pendiente').toLowerCase();
    const noEnviada = !!item.noEnviada || !!item.legacyContact;
    const isExpired = status === 'revocada' || status === 'expirada';
    const isAccepted = !!item.completed || status === 'aceptada' || status === 'registrado' || status === 'completada';
    const statusLabel = isAccepted ? 'Completada' : (isExpired ? (status === 'revocada' ? 'Revocada' : 'Expirada') : 'Pendiente');
    const steps = [
        { label:'Creada', cls:'is-done' },
        { label:noEnviada ? 'No enviada' : 'Enviada', cls:item.enviada ? 'is-done' : 'is-current', extra:noEnviada ? '' : _resInvitationChannelHtml(item) },
        { label:statusLabel, cls:isAccepted ? 'is-done' : (isExpired ? 'is-danger' : 'is-current') },
    ];
    return `<div class="cd-inv-steps" aria-label="Estado de invitación">${steps.map(s => `<span class="cd-inv-step ${s.cls}"><i></i><b>${esc(s.label)}</b>${s.extra || ''}</span>`).join('')}</div>`;
}

function _resInvitationActor(item = {}, key = 'creadoPor') {
    const raw = item.raw || {};
    const inv = raw.invitacion || {};
    if (item[key]) return item[key];
    if (key === 'creadoPor') return inv.creado_por_nombre || raw.creado_por_nombre || '';
    if (key === 'revocadoPor') return inv.revocado_por_nombre || raw.revocado_por_nombre || '';
    return '';
}

function _resInvitationRoleOptions(selected = 'familiar') {
    const role = String(selected || 'familiar').toLowerCase() === 'cuidador' ? 'enfermero' : String(selected || 'familiar').toLowerCase();
    const roles = [
        ['familiar', 'Familiar'],
        ['medico', 'Médico'],
        ['enfermero', 'Cuidador'],
        ['admin', 'Admin'],
    ];
    return roles.map(([value, label]) => `<option value="${value}"${role === value ? ' selected' : ''}>${label}</option>`).join('');
}

function _resInvEditChannel({ icon = 'edit', label = '', status = '', control = '' } = {}) {
    return `<div class="cd-inv-detail-channel cd-inv-detail-channel--edit">
        <span class="material-symbols-outlined" aria-hidden="true">${esc(icon)}</span>
        <div>
            <div class="cd-inv-detail-channel-head"><strong>${esc(label)}</strong><em>${esc(status)}</em></div>
            ${control}
        </div>
    </div>`;
}

function _resInvInfoChannel({ icon = 'info', label = '', status = '', value = '' } = {}) {
    return `<div class="cd-inv-detail-channel cd-inv-detail-channel--info">
        <span class="material-symbols-outlined" aria-hidden="true">${esc(icon)}</span>
        <div>
            <div class="cd-inv-detail-channel-head"><strong>${esc(label)}</strong><em>${esc(status)}</em></div>
            <small>${esc(value || '—')}</small>
        </div>
    </div>`;
}

function _resInvitationDetailsHtml(item = {}) {
    const status = String(item.estado || 'pendiente').toLowerCase();
    const creator = _resInvitationActor(item, 'creadoPor') || 'No registrado';
    const revoker = _resInvitationActor(item, 'revocadoPor') || (status === 'revocada' ? 'Sistema / no registrado' : '—');
    const sentBy = creator;
    const vigenciaLabel = status === 'expirada' ? (item.expira ? `Expirada: ${item.expira}` : 'Expirada') : 'Sin expiración';
    const channel = ({ icon, label, value, inputId, inputType = 'text', channelKey = 'email' }) => {
        const delivery = _resInvitationDelivery(item);
        const state = _resInvitationChannelState(item, channelKey);
        const detail = delivery?.[channelKey] || null;
        const statusTxt = !value ? 'Sin destino' : (state === 'enviado' ? 'Enviado' : (state === 'error' ? 'Error' : (item.enviada ? 'Pendiente' : 'Sin envío')));
        const detailText = detail?.fecha ? `<small class="cd-inv-channel-meta">${esc(detail.fecha)}${detail.error ? ' · ' + esc(detail.error) : ''}</small>` : '';
        const waStatusHtml = channelKey === 'whatsapp' && (value || inputId)
            ? `<small class="cd-inv-wa-status cd-inv-wa-status--inline" data-inv-wa-reg-status data-inv-wa-reg-status-for="${esc(inputId || '')}" data-phone="${esc(value || '')}" ${value ? '' : 'hidden'}>Verificando WhatsApp...</small>`
            : '';
        const valueHtml = inputId
            ? (inputType === 'tel'
                ? `<input class="cd-input cd-inv-channel-input" id="${inputId}" type="tel" value="${esc(value || '')}" placeholder="+52 1 555 123 4567" autocomplete="tel" data-inv-wa-reg-input>`
                : `<input class="cd-input cd-inv-channel-input" id="${inputId}" type="email" value="${esc(value || '')}" placeholder="correo@dominio.com" autocomplete="email">`)
            : `<small>${value ? esc(value) : 'Sin destino configurado'}</small>`;
        return `<div class="cd-inv-detail-channel${inputId ? ' cd-inv-detail-channel--edit' : ''}">
            <span class="material-symbols-outlined" aria-hidden="true">${esc(icon)}</span>
            <div>
                <div class="cd-inv-detail-channel-head"><strong>${esc(label)}</strong><em>${statusTxt}</em></div>
                ${valueHtml}
                ${detailText}
                ${waStatusHtml}
            </div>
        </div>`;
    };
    return `<div class="cd-inv-detail-card">
        ${_resInvitationStepHtml(item)}
        <div class="cd-inv-detail-grid">
            <div><span>Creada por</span><strong>${esc(creator)}</strong></div>
            <div><span>Creada / enviada</span><strong>${item.enviada ? esc(item.enviada) : 'Sin fecha'}</strong></div>
            <div><span>Vigencia</span><strong>${esc(vigenciaLabel)}</strong></div>
            <div><span>Revocada por</span><strong>${esc(revoker)}</strong></div>
        </div>
        <div class="cd-inv-detail-channels">
            ${channel({ icon:'mail', label:'Correo', value:item.email || '', inputId: item.editEmailId || '', inputType:'email', channelKey:'email' })}
            ${channel({ icon:'send_to_mobile', label:'WhatsApp', value:item.telefono || '', inputId: item.editTelId || '', inputType:'tel', channelKey:'whatsapp' })}
        </div>
        ${item.editHtml || ''}
        <div class="cd-inv-detail-foot">Envío/creación registrado por: ${esc(sentBy)}. Los reenvíos por canal no guardan usuario separado en la auditoría actual.</div>
    </div>`;
}

function _resRenderInvitaciones(list, resId, data = {}) {
    list = Array.isArray(list) ? list : [];
    const isGlobal = !!data._global;
    const canEdit = !!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN);
    const search = _resSubcardSearchHtml('invitaciones', 'Buscar invitaciones');
    const inviteButton = `<button type="button" class="cd-res-inv-add-btn${canEdit ? '' : ' cd-role-locked'}" data-inv-new="${resId}" title="Crear invitación" ${!canEdit ? `data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden crear invitaciones."` : ''}><span class="material-symbols-outlined" aria-hidden="true">add</span><span>Invitar</span></button>`;
    const residentOptions = isGlobal ? (() => {
        const map = { ...(data.residentMap || {}) };
        list.forEach(item => { (item.residenteIds || []).forEach((id, i) => { if (!map[id]) { const names = String(item.residenteNames || '').split(','); map[id] = (names[i] || '').trim() || `Residente #${id}`; } }); });
        return Object.entries(map).sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => `<div class="cd-inv-res-option" data-inv-res-id="${esc(id)}" role="option" tabindex="-1">${esc(name)}</div>`).join('');
    })() : '';
    const resDropdown = isGlobal ? `<div class="cd-inv-res-dropdown" data-inv-res-dropdown><button type="button" class="cd-inv-res-dropdown-toggle" data-inv-res-toggle aria-haspopup="listbox" aria-expanded="false"><span class="material-symbols-outlined" aria-hidden="true">person_search</span><span class="cd-inv-res-dd-label" data-inv-res-label>Todos los residentes</span><span class="material-symbols-outlined cd-inv-res-dd-chevron" aria-hidden="true">expand_more</span></button><div class="cd-inv-res-dropdown-panel" role="listbox" aria-label="Filtrar por residente" hidden><div class="cd-inv-res-search-wrap"><span class="material-symbols-outlined" aria-hidden="true">search</span><input type="text" class="cd-inv-res-search-input" data-inv-res-search placeholder="Buscar residente..." autocomplete="off"></div><div class="cd-inv-res-options" data-inv-res-options><div class="cd-inv-res-option is-active" data-inv-res-id="" role="option" tabindex="-1">Todos los residentes</div>${residentOptions}</div></div></div>` : '';
    const header = `<div class="cd-res-fam-panel-head cd-res-inv-panel-head"><div class="cd-res-inv-toolbar">${search}${resDropdown}${inviteButton}</div></div>`;
    if (!list.length) return `${header}<div class="cd-res-subpanel-empty">${isGlobal ? 'No hay invitaciones pendientes.' : 'No hay invitaciones para este residente.'}</div>`;
    const bulkBar = canEdit ? `<div class="cd-inv-bulkbar" data-inv-bulkbar hidden><div class="cd-inv-bulk-left"><strong data-inv-bulk-count>0</strong><span>seleccionadas</span></div><div class="cd-inv-bulk-actions"><select class="cd-inv-bulk-role" data-inv-bulk-role aria-label="Cambiar rol de invitaciones seleccionadas">${_resInvitationRoleOptions('familiar')}</select><button type="button" class="cd-inv-bulk-btn" data-inv-bulk-action="role"><span class="material-symbols-outlined" aria-hidden="true">manage_accounts</span><span>Aplicar rol</span></button><button type="button" class="cd-inv-bulk-btn" data-inv-bulk-action="email"><span class="material-symbols-outlined" aria-hidden="true">outgoing_mail</span><span>Correo</span></button><button type="button" class="cd-inv-bulk-btn" data-inv-bulk-action="whatsapp"><span class="material-symbols-outlined" aria-hidden="true">send_to_mobile</span><span>WhatsApp</span></button><button type="button" class="cd-inv-bulk-btn cd-inv-bulk-btn--danger" data-inv-bulk-action="delete"><span class="material-symbols-outlined" aria-hidden="true">delete</span><span>Eliminar</span></button></div></div>` : '';
    const filters = `<div class="cd-res-inv-filter-stack">
        ${_resInvFilterChips('role', _resFamRoleFilterDefs(list), 'Filtrar invitaciones por rol')}
        ${_resInvFilterChips('status', _resInvStatusFilterDefs(list), 'Filtrar invitaciones por estado')}
    </div>`;
    const _invStateLabel = { completada:'Completada', pendiente:'Pendiente', revocada:'Revocada', expirada:'Expirada', enviada:'Enviada', creada:'Creada' };
    const _invStateCls   = { completada:'is-registered', pendiente:'is-invited', revocada:'is-expired', expirada:'is-expired', enviada:'is-invited', creada:'is-contact' };
    const _hdr = `<div class="cd-rri-hd cd-inv-row-head"><div class="cd-rri-col cd-inv-col--select">${canEdit ? '<label class="cd-inv-check-wrap"><input type="checkbox" data-inv-select-all aria-label="Seleccionar invitaciones visibles"><span></span></label>' : ''}</div><div class="cd-rri-col cd-rri-col--name">Contacto</div><div class="cd-rri-col cd-inv-col--target">Destino</div><div class="cd-rri-col cd-rri-col--status">Estado</div><div class="cd-rri-col cd-rri-col--actions"></div></div>`;
    return `${header}${bulkBar}${filters}<div class="cd-res-fam-list cd-res-inv-list cd-res-row-list">${_hdr}${list.map((item, idx) => {
        const hasEmail = !!String(item.email || '').trim();
        const hasPhone = !!String(item.telefono || '').trim();
        const digits = String(item.telefono || '').replace(/\D+/g, '');
        const hasWa = digits.length >= 10;
        const isLegacyContact = !!item.legacyContact;
        const roleKey = _resFamFilterKey(item.role || 'Familiar');
        const stateKey = _resInvStatusKey(item);
        const isCompleted = stateKey === 'completada';
        const isRegisteredFam = isCompleted && item.kind === 'familiar' && !isLegacyContact;
        const isRegisteredStaff = isCompleted && item.kind === 'cuidador' && !!item.registered;
        const isRegistered = isRegisteredFam || isRegisteredStaff;
        const _invNamePlaceholders = new Set(['invitación pendiente','invitación familiar','contacto familiar','familiar registrado','sin nombre','invitacion pendiente','invitacion familiar','contacto legacy']);
        const _cleanNombre = (v) => { const s = String(v || '').trim(); return _invNamePlaceholders.has(s.toLowerCase()) ? '' : s; };
        const rawNombre = _cleanNombre(item.raw?.nombre) || _cleanNombre(item.raw?.invitacion?.nombre_sugerido) || item.email || item.telefono || '';
        const hasRealName = !!rawNombre;
        const nameLabel = hasRealName ? _resToTitleCase(rawNombre) : '';
        const initials = isRegistered && hasRealName ? rawNombre.split(/\s+/).slice(0, 2).map(s => s[0] || '').join('').toUpperCase() : '';
        const actions = [];
        const _lockEdit = { locked: !canEdit, lockTitle: 'Requiere permisos de administrador', lockMsg: 'Solo los administradores pueden gestionar y eliminar invitaciones.' };
        if (isRegisteredFam) {
            if (hasPhone) actions.push(_resFamMenuItem({ href:`tel:${esc(item.telefono)}`, icon:'call', label:'Llamar', title:'Llamar' }));
            if (hasEmail) actions.push(_resFamMenuItem({ href:`mailto:${esc(item.email)}`, icon:'mail', label:'Correo', title:'Enviar correo' }));
            if (hasWa) actions.push(_resFamMenuItem({ attrs:`data-inv-wa="${idx}"`, cls:'cd-res-fam-action--wa', icon:'chat', label:'WhatsApp', title:'Enviar WhatsApp' }));
            actions.push(_resFamMenuItem({ attrs:`data-inv-notif="${idx}"`, cls:'cd-res-fam-action--notif', icon:'notifications', label:'Notificaciones', title:'Configurar notificaciones' }));
            actions.push(_resFamMenuItem({ attrs:`data-inv-manage="${idx}"`, cls:'cd-res-fam-action--manage', icon:'edit', label:'Administrar', title:'Editar y administrar' }));
            actions.push(_resFamMenuItem({ attrs:`data-inv-delete="${idx}"`, cls:'cd-res-fam-action--delete', icon:'delete', label:'Desvincular familiar', title:'Desvincular familiar', ..._lockEdit }));
        } else if (isRegisteredStaff) {
            if (hasPhone) actions.push(_resFamMenuItem({ href:`tel:${esc(item.telefono)}`, icon:'call', label:'Llamar', title:'Llamar' }));
            if (hasEmail) actions.push(_resFamMenuItem({ href:`mailto:${esc(item.email)}`, icon:'mail', label:'Correo', title:'Enviar correo' }));
            actions.push(_resFamMenuItem({ attrs:`data-inv-cuid-edit="${resId}"`, cls:'cd-res-fam-action--manage', icon:'manage_accounts', label:'Residentes asociados', title:'Editar asignaciones', ..._lockEdit }));
        } else if (!isCompleted) {
            if (isLegacyContact && hasEmail) actions.push(_resFamMenuItem({ attrs:`data-inv-create-email="${idx}"`, cls:'cd-res-fam-action--invite', icon:'outgoing_mail', label:'Invitar por correo', title:'Crear invitación por correo', ..._lockEdit }));
            if (isLegacyContact && hasPhone) actions.push(_resFamMenuItem({ attrs:`data-inv-create-wa="${idx}"`, cls:'cd-res-fam-action--invite-wa', icon:'send_to_mobile', label:'Invitar por WhatsApp', title:'Crear invitación y enviarla por WhatsApp', ..._lockEdit }));
            if (!isLegacyContact && hasEmail) actions.push(_resFamMenuItem({ attrs:`data-inv-resend-email="${idx}"`, cls:'cd-res-fam-action--resend', icon:'outgoing_mail', label:'Reenviar correo', title:'Reenviar por correo', ..._lockEdit }));
            if (!isLegacyContact && hasPhone) actions.push(_resFamMenuItem({ attrs:`data-inv-resend-wa="${idx}"`, cls:'cd-res-fam-action--invite-wa', icon:'send_to_mobile', label:'Reenviar WhatsApp', title:'Reenviar por WhatsApp', ..._lockEdit }));
            actions.push(_resFamMenuItem({ attrs:`data-inv-delete="${idx}"`, cls:'cd-res-fam-action--delete', icon:isLegacyContact ? 'delete' : 'delete_forever', label:isLegacyContact ? 'Borrar contacto' : 'Eliminar invitación', title:isLegacyContact ? 'Borrar contacto legacy' : 'Eliminar invitación', ..._lockEdit }));
        }
        const residentNames = String(item.residenteNames || '').trim();
        const searchText = [nameLabel, item.role, residentNames, item.email, item.telefono, item.estado, stateKey, item.enviada].filter(Boolean).join(' ');
        const statusDisplayLabel = _resInvitationStatusLabel(item, stateKey) || _invStateLabel[stateKey] || esc(stateKey || 'Pendiente');
        const statusClass = _resInvitationErrorChannels(item).length ? 'is-expired' : (_invStateCls[stateKey] || 'is-contact');
        const statusChip = `<span class="cd-res-fam-badge ${statusClass}">${statusDisplayLabel}</span>`;
        const targetHtml = `<span class="cd-inv-target-stack">${hasEmail ? `<a href="mailto:${esc(item.email)}" onclick="event.stopPropagation()" class="cd-inv-target-line"><span class="material-symbols-outlined" aria-hidden="true">mail</span>${esc(item.email)}</a>` : ''}${hasPhone ? `<a href="tel:${esc(item.telefono)}" onclick="event.stopPropagation()" class="cd-inv-target-line"><span class="material-symbols-outlined" aria-hidden="true">call</span>${esc(item.telefono)}</a>` : ''}${!hasEmail && !hasPhone ? '<span class="cd-rri-empty">Sin destino</span>' : ''}</span>`;
        const _invResIds = item.residenteIds || [];
        const _invResNamesArr = String(item.residenteNames || '').split(',').map(s => s.trim());
        const _hasExpand = _invResIds.length > 0;
        const _expandBtn = _hasExpand ? `<button type="button" class="cd-inv-expand-btn" data-inv-exp="${idx}" title="Ver residentes asociados" aria-expanded="false"><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button>` : '';
        const _vigenciaIcon = stateKey === 'expirada' ? 'event_busy' : 'all_inclusive';
        const _vigenciaText = stateKey === 'expirada' ? (item.expira ? `Expirada: ${item.expira}` : 'Expirada') : 'Sin expiración';
        if (isGlobal) actions.unshift(_resFamMenuItem({ attrs:`data-inv-detail="${idx}"`, cls:'cd-res-fam-action--details', icon:'info', label:'Ver detalles', title:'Ver detalles' }));
        const _expandPanel = _hasExpand ? `<div class="cd-inv-row-expand" data-inv-exp-panel="${idx}" hidden><div class="cd-inv-expand-inner"><div class="cd-inv-expand-main"><span class="cd-inv-expand-role-badge">${esc(item.role)}</span><div class="cd-inv-expand-chips">${_invResIds.map((rid, ri) => { const rn = _invResNamesArr[ri] || `Residente #${rid}`; const ri2 = rn.split(/\s+/).slice(0,2).map(s=>s[0]||'').join('').toUpperCase(); return `<button type="button" class="cd-inv-res-chip" data-res-nav="${esc(String(rid))}" title="${esc(rn)}"><span class="cd-inv-res-chip-init" aria-hidden="true">${esc(ri2)}</span><span>${esc(rn)}</span></button>`; }).join('')}</div></div>${item.enviada?`<div class="cd-inv-expand-dates"><span><span class="material-symbols-outlined" aria-hidden="true">outgoing_mail</span>${esc(item.enviada)}</span><span><span class="material-symbols-outlined" aria-hidden="true">${_vigenciaIcon}</span>${esc(_vigenciaText)}</span></div>`:''}</div></div>` : '';
        return `<div class="cd-inv-row-group"><div class="cd-res-fam-item cd-res-fam-item--actionable cd-res-inv-item cd-res-row-item" data-inv-idx="${idx}" data-inv-role="${esc(roleKey)}" data-inv-state="${esc(stateKey)}" data-res-search-text="${esc(searchText)}" data-inv-res-ids="${esc(JSON.stringify(_invResIds))}" role="button" tabindex="0" title="Ver detalles de ${esc(nameLabel)}">
            <div class="cd-rri-col cd-inv-col--select">${canEdit ? `<label class="cd-inv-check-wrap" title="Seleccionar invitación"><input type="checkbox" data-inv-select="${idx}" onclick="event.stopPropagation()"><span></span></label>` : ''}</div>
            <div class="cd-rri-col cd-rri-col--name">
                ${initials ? `<span class="cd-rri-initials">${esc(initials)}</span>` : ''}
                <span class="cd-rri-name-stack">
                    <span class="cd-rri-label">${esc(nameLabel || 'Invitación sin nombre')}</span>
                    <span class="cd-res-fam-rel">${esc(item.role)}</span>
                    <span class="cd-rri-mobile-status">${statusChip}</span>
                </span>
            </div>
            <div class="cd-rri-col cd-inv-col--target">${targetHtml}</div>
            <div class="cd-rri-col cd-rri-col--status">${statusChip}</div>
            <div class="cd-rri-col cd-rri-col--actions">${_expandBtn}${_resFamMenu(actions)}</div>
        </div>${_expandPanel}</div>`;
    }).join('')}</div><div class="cd-res-subpanel-empty cd-res-search-empty" data-res-search-empty hidden>No hay invitaciones que coincidan con los filtros.</div>`;
}

function _resInvitationRowsFromApi(rows = [], residentMap = {}) {
    rows = Array.isArray(rows) ? rows : (Array.isArray(rows?.data) ? rows.data : []);
    return (Array.isArray(rows) ? rows : []).map(inv => {
        const role = _resRoleLabel(inv.rol || 'familiar');
        const resIds = Array.isArray(inv.residente_ids) ? inv.residente_ids.map(id => parseInt(id, 10)).filter(Boolean) : [];
        const residentNames = resIds.map(id => residentMap[id]).filter(Boolean).join(', ');
        const firstResId = resIds[0] || 0;
        const isRegistered = !!inv.registered || !!inv.usuario_id;
        const fullName = [inv.nombre_sugerido || '', inv.apellido_sugerido || ''].join(' ').trim() || (isRegistered ? 'Usuario registrado' : 'Invitación pendiente');
        const invId = isRegistered ? String(inv.id || `user-${inv.usuario_id}`) : (parseInt(inv.id, 10) || 0);
        return {
            id: invId,
            kind: (inv.rol || '') === 'familiar' ? 'familiar' : ((inv.rol || '') === 'admin' ? 'admin' : 'cuidador'),
            role,
            email: inv.email || '',
            telefono: inv.telefono || '',
            estado: inv.status || (isRegistered ? 'registrado' : 'pendiente'),
            enviada: inv.enviada || '',
            expira: inv.expira || '',
            token: inv.token || '',
            resId: firstResId,
            residenteIds: resIds,
            residenteNames: residentNames,
            raw: {
                ...inv,
                invitacion_id: invId,
                usuario_id: inv.usuario_id || null,
                nombre: fullName,
                rol: inv.rol || 'familiar',
                email: inv.email || '',
                telefono: inv.telefono || '',
                invitacion: {
                    ...inv,
                    id: invId,
                    status: inv.status || (isRegistered ? 'registrado' : 'pendiente'),
                    nombre_sugerido: inv.nombre_sugerido || '',
                    apellido_sugerido: inv.apellido_sugerido || '',
                },
            },
            creadoPor: inv.creado_por_nombre || '',
            registered: isRegistered,
            completed: isRegistered || String(inv.status || '').toLowerCase() === 'registrado',
            noEnviada: !isRegistered && !String(inv.enviada || '').trim(),
        };
    });
}

async function _resInvitationResidentMap() {
    const map = {};
    const residentGlobal = (typeof RESIDENTES !== 'undefined' && Array.isArray(RESIDENTES)) ? RESIDENTES : [];
    const source = Array.isArray(_resMgmtData) && _resMgmtData.length ? _resMgmtData : residentGlobal;
    source.forEach(r => {
        const id = parseInt(r.id, 10);
        if (!id) return;
        map[id] = (r.nombre_completo || [r.nombre || '', r.apellidos || ''].join(' ')).trim() || `Residente #${id}`;
    });
    if (Object.keys(map).length) return map;
    try {
        const residents = await api(`${RES_API}?cuidados_estado=1&estado=`);
        const residentRows = Array.isArray(residents) ? residents : (Array.isArray(residents?.data) ? residents.data : []);
        if (!Array.isArray(_resMgmtData) || !_resMgmtData.length) _resMgmtData = residentRows;
        residentRows.forEach(r => {
            const id = parseInt(r.id, 10);
            if (!id) return;
            map[id] = [r.nombre || '', r.apellidos || ''].join(' ').trim() || `Residente #${id}`;
        });
    } catch(e) {}
    return map;
}

function _resRenderInvitationSummary(rows = []) {
    const statusCounts = rows.reduce((acc, item) => {
        const key = _resInvStatusKey(item);
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});
    return [
        ['Total', rows.length, 'mark_email_unread'],
        ['Registrados', statusCounts.completada || 0, 'person_check'],
        ['Enviadas', statusCounts.enviada || 0, 'outgoing_mail'],
        ['Expiradas', statusCounts.expirada || 0, 'event_busy'],
    ].map(([label, count, icon]) => `<div class="cd-inv-summary-item"><span class="material-symbols-outlined" aria-hidden="true">${icon}</span><strong>${count}</strong><small>${label}</small></div>`).join('');
}

async function loadInvitaciones() {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) return;
    const panel = $('#cdInvListPanel');
    const summary = $('#cdInvSummary');
    if (!panel) return;
    panel.innerHTML = '<div class="cd-res-subpanel-loading">Cargando invitaciones...</div>';
    try {
        const [rowsPayload, residentMap] = await Promise.all([
            api(`${BASE}/api/invitaciones.php`),
            _resInvitationResidentMap(),
        ]);
        const rows = Array.isArray(rowsPayload) ? rowsPayload : (Array.isArray(rowsPayload?.data) ? rowsPayload.data : []);
        const list = _resInvitationRowsFromApi(rows, residentMap);
        if (summary) summary.innerHTML = _resRenderInvitationSummary(list);
        panel.innerHTML = _resRenderInvitaciones(list, 0, { _global: true, residentMap });
        _resBindInvitacionesFilters(panel, list, { resId: 0, refreshTab: 'viewInvitaciones' });
        panel.querySelectorAll('[data-inv-new], [data-fam-new]').forEach(btn => btn.addEventListener('click', e => {
            e.stopPropagation();
            _resOpenFamiliarInvite(null, {}, {}, 'viewInvitaciones');
        }));
        const openInvitationDetail = item => {
            if (!item) return;
            openSidebar('Detalle de invitación', `<div class="cd-sidebar-section">${_resInvitationDetailsHtml(item)}</div>`, `<button class="cd-btn-submit cd-btn-secondary" id="cdInvDetailClose">Cerrar</button>`);
            _resBindInviteWhatsAppStatus(document);
            $('#cdInvDetailClose')?.addEventListener('click', closeSidebar);
        };
        panel.querySelectorAll('.cd-res-fam-item[data-inv-idx]').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('.cd-res-fam-actions,a,button,input,select,textarea,label')) return;
                const item = list[parseInt(row.dataset.invIdx, 10)] || null;
                openInvitationDetail(item);
            });
        });
        panel.querySelectorAll('[data-inv-detail]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                openInvitationDetail(list[parseInt(btn.dataset.invDetail, 10)] || null);
            });
        });
        panel.querySelectorAll('[data-inv-resend-email], [data-inv-resend-wa], [data-inv-resend]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Reenviando')) return;
                const idx = parseInt(btn.dataset.invResendEmail || btn.dataset.invResendWa || btn.dataset.invResend, 10);
                const item = list[idx];
                const canal = btn.dataset.invResendEmail ? 'email' : (btn.dataset.invResendWa ? 'whatsapp' : 'all');
                try { await _resResendFamiliarInvite(item?.resId || 0, item?.id || 0, canal, 'viewInvitaciones'); }
                finally { _resFamActionEnd(btn); }
            });
        });
        panel.querySelectorAll('[data-inv-delete]').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.stopPropagation();
                const item = list[parseInt(btn.dataset.invDelete, 10)] || null;
                if (!item) return;
                await _resDeleteFamiliarInvite(item.resId || 0, item.id, 'viewInvitaciones');
            });
        });
        panel.querySelectorAll('.cd-inv-res-chip[data-res-nav]').forEach(chip => {
            chip.addEventListener('click', e => {
                e.stopPropagation();
                const id = parseInt(chip.dataset.resNav, 10);
                if (!id) return;
                if (typeof showView === 'function') showView('viewFicha');
                if (typeof loadResidente === 'function') loadResidente(id);
            });
        });
    } catch(e) {
        console.error('[GeriApp] loadInvitaciones failed', e);
        panel.innerHTML = `<div class="cd-res-subpanel-empty">No se pudieron cargar las invitaciones.${e?.message ? `<br><small>${esc(e.message)}</small>` : ''}</div>`;
    }
}

$('#cdInvNewBtn')?.addEventListener('click', () => _resOpenFamiliarInvite(null, {}, {}, 'viewInvitaciones'));

function _resOpenFamMenu(wrap, toggle) {
    const menu = wrap?.querySelector(':scope > .cd-res-fam-menu');
    if (!wrap || !toggle || !menu) return;
    _resCloseFamMenus();
    const ph = document.createComment('cd-res-fam-menu');
    wrap.insertBefore(ph, menu);
    document.body.appendChild(menu);
    menu.classList.add('is-portal-open');
    const rect = toggle.getBoundingClientRect();
    const width = Math.min(Math.max(menu.offsetWidth || 224, 224), window.innerWidth - 16);
    const left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.right - width));
    const top = Math.min(window.innerHeight - 8, rect.bottom + 6);
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
    menu.style.maxWidth = `${width}px`;
    menu.style.width = `${width}px`;
    menu._famMenuWrap = wrap;
    wrap._famPortalMenu = menu;
    wrap._famMenuPlaceholder = ph;
    wrap.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
}

document.addEventListener('click', e => {
    const toggle = e.target.closest('[data-fam-menu-toggle]');
    if (toggle) {
        e.preventDefault();
        e.stopPropagation();
        const wrap = toggle.closest('.cd-res-fam-actions');
        const opening = !wrap?.classList.contains('is-open');
        _resCloseFamMenus();
        if (opening) _resOpenFamMenu(wrap, toggle);
        return;
    }
    if (e.target.closest('.cd-res-fam-menu')) return;
    if (!e.target.closest('.cd-res-fam-actions')) {
        _resCloseFamMenus();
    }
});
document.addEventListener('focusin', e => {
    document.querySelectorAll('.cd-res-fam-actions.is-open').forEach(wrap => {
        const menu = wrap._famPortalMenu || wrap.querySelector(':scope > .cd-res-fam-menu');
        if (!wrap.contains(e.target) && !(menu && menu.contains(e.target))) _resCloseFamMenu(wrap);
    });
    if (!e.target.closest('.cd-res-mgmt-menu-wrap')) {
        $$('.cd-res-mgmt-menu.open').forEach(m => {
            m.classList.remove('open');
            const btn = m.parentElement?.querySelector('.cd-res-mgmt-kebab');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') _resCloseFamMenus(); });
window.addEventListener('resize', _resCloseFamMenus);
window.addEventListener('scroll', _resCloseFamMenus, true);

function _resRenderFamiliares(list, resId, data = {}) {
    list = Array.isArray(list) ? list : [];
    const _famIc = (path) => `<svg class="cd-res-fam-meta-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
    const icMail = _famIc('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>');
    const icTel  = _famIc('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>');
    const seats = data.familySeats || {};
    const hasSeatInfo = seats && seats.included !== undefined && seats.included !== null;
    const usedTxt = hasSeatInfo ? `${Number(seats.used || 0)}/${Number(seats.total || seats.included || 0)}` : 'Sin límite';
    const seatHint = hasSeatInfo
        ? `Incluidos: ${Number(seats.included || 0)} · Extras comprados: ${Number(seats.extra || 0)}${Number(seats.pending_invites || 0) ? ` · Invitaciones pendientes: ${Number(seats.pending_invites || 0)}` : ''}`
        : 'El paquete actual no limita familiares.';
    const canInvite = !!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN);
    const header = `<div class="cd-res-fam-panel-head">
        <button type="button" class="cd-res-sub-action cd-res-fam-new${canInvite ? '' : ' cd-role-locked'}" data-fam-new="${resId}" title="Invitar familiar" ${!canInvite ? `data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden enviar invitaciones a familiares."` : ''}><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg><span>Invitar familiar</span></button>
    </div>`;
    const search = _resSubcardSearchHtml('familiares', 'Buscar familiares');
    if (!list.length) {
        return `${header}${search}<div class="cd-res-subpanel-empty">No hay familiares registrados para este residente.</div>`;
    }
    const inviteState = (f) => {
        const inv = f.invitacion || {};
        const st = inv.status || f.estado_cuenta || '';
        const channels = [
            (inv.email || f.email || '').trim() ? 'Correo' : '',
            (inv.telefono || f.telefono || '').trim() ? 'WhatsApp' : '',
        ].filter(Boolean);
        let label = 'Pendiente';
        let cls = 'is-invited';
        if (st === 'expirada') { label = 'Expirada'; cls = 'is-expired'; }
        else if (st === 'revocada') { label = 'Revocada'; cls = 'is-expired'; }
        else if (st === 'aceptada') { label = 'Aceptada'; cls = 'is-registered'; }
        return { status: st || 'pendiente', label, cls, channels };
    };
    const statusBadge = (f) => {
        const invState = inviteState(f);
        if (f.usuario_id) return `<span class="cd-res-fam-badge is-registered">Registrado</span>`;
        if (f.invitacion_id) {
            return `<span class="cd-res-fam-badge ${invState.cls}">Invitación ${esc(invState.label.toLowerCase())}</span>`;
        }
        return `<span class="cd-res-fam-badge is-contact">Sin invitación</span>`;
    };
    const seatBadge = (f) => {
        if (f.seat_scope === 'extra') return `<span class="cd-res-fam-badge is-extra" title="Este familiar queda fuera de los asientos incluidos y pagará su asiento extra al registrarse">Extra / pago propio</span>`;
        if (f.seat_scope === 'incluido') return `<span class="cd-res-fam-badge is-included">Incluido</span>`;
        return '';
    };
    const html = `${header}${search}<div class="cd-res-fam-list cd-res-row-list">${list.map((f, idx) => {
        const initials = (f.nombre || '?').trim().split(/\s+/).slice(0,2).map(s => s[0]||'').join('').toUpperCase();
        const tel = (f.telefono || '').trim();
        const mail = (f.email || '').trim();
        const rel = (f.parentesco || '').trim();
        const roleLabel = _resFamRoleLabel(f);
        const roleKey = _resFamFilterKey(roleLabel);
        const stateKey = _resFamStatusKey(f);
        const inv = f.invitacion || {};
        const invState = inviteState(f);
        const inviteMeta = f.invitacion_id ? `<div class="cd-res-fam-status-line">Estado: ${esc(invState.label)}${invState.channels.length ? ` · Vía: ${esc(invState.channels.join(' + '))}` : ''}${inv.enviada ? ` · Enviada: ${esc(inv.enviada)}` : ''} · Sin expiración</div>` : '';
        // Filas uniformes (siempre 2 filas: email + tel) para alineación consistente entre residentes
        const metaRows = [
            `<div class="cd-res-fam-meta-row">${icMail}<span class="cd-res-fam-meta-val">${mail ? `<a href="mailto:${esc(mail)}" onclick="event.stopPropagation()">${esc(mail)}</a>` : '<span class="cd-res-fam-meta-empty">Sin correo</span>'}</span></div>`,
            `<div class="cd-res-fam-meta-row">${icTel}<span class="cd-res-fam-meta-val">${tel  ? `<a href="tel:${esc(tel)}" onclick="event.stopPropagation()">${esc(tel)}</a>`     : '<span class="cd-res-fam-meta-empty">Sin teléfono</span>'}</span></div>`,
        ];
        const actions = [];
        const digits = tel.replace(/\D+/g, '');
        const hasWa = digits.length >= 10;
        const inviteOpen = f.invitacion_id && (inv.status || 'pendiente') !== 'aceptada';
        if (tel) actions.push(_resFamMenuItem({ href:`tel:${esc(tel)}`, icon:'call', label:'Llamar', title:'Llamar' }));
        if (!inviteOpen && mail) actions.push(_resFamMenuItem({ href:`mailto:${esc(mail)}`, icon:'mail', label:'Correo', title:'Enviar correo' }));
        if (!inviteOpen && hasWa) actions.push(_resFamMenuItem({ attrs:`data-fam-wa="${idx}"`, cls:'cd-res-fam-action--wa', icon:'chat', label:'WhatsApp', title:'Enviar WhatsApp por API' }));
        const _lockInv = { locked: !canInvite, lockTitle: 'Requiere permisos de administrador', lockMsg: 'Solo los administradores pueden gestionar invitaciones.' };
        if (inviteOpen && mail)  actions.push(_resFamMenuItem({ attrs:`data-fam-resend-email="${idx}"`, cls:'cd-res-fam-action--resend', icon:'outgoing_mail', label:'Reenviar correo', title:'Reenviar invitación por correo', ..._lockInv }));
        if (inviteOpen && hasWa) actions.push(_resFamMenuItem({ attrs:`data-fam-resend-wa="${idx}"`, cls:'cd-res-fam-action--invite-wa', icon:'send_to_mobile', label:'Reenviar WhatsApp', title:'Reenviar invitación por WhatsApp', ..._lockInv }));
        if (inviteOpen && !mail && !hasWa) actions.push(_resFamMenuItem({ attrs:`data-fam-resend="${idx}"`, cls:'cd-res-fam-action--resend', icon:'refresh', label:'Reenviar', title:'Reenviar invitación', ..._lockInv }));
        if (!f.usuario_id && !f.invitacion_id && mail) actions.push(_resFamMenuItem({ attrs:`data-fam-invite="${idx}"`, cls:'cd-res-fam-action--invite', icon:'outgoing_mail', label:'Invitar por correo', title:'Crear invitación por correo', ..._lockInv }));
        if (hasWa && !f.usuario_id && !f.invitacion_id) actions.push(_resFamMenuItem({ attrs:`data-fam-invite-wa="${idx}"`, cls:'cd-res-fam-action--invite-wa', icon:'send_to_mobile', label:'Invitar por WhatsApp', title:'Crear invitación y enviarla por WhatsApp', ..._lockInv }));
        actions.push(_resFamMenuItem({ attrs:`data-fam-notif="${idx}"`, cls:'cd-res-fam-action--notif', icon:'notifications', label:'Notificaciones', title:'Configurar notificaciones' }));
        actions.push(_resFamMenuItem({ attrs:`data-fam-manage="${idx}"`, cls:'cd-res-fam-action--manage', icon:'edit', label:'Administrar', title:'Editar y administrar' }));
        const deleteLabel = f.invitacion_id ? 'Eliminar invitación' : (f.usuario_id ? 'Desvincular familiar' : 'Borrar contacto');
        const deleteIcon  = f.invitacion_id ? 'delete_forever' : 'delete';
        actions.push(_resFamMenuItem({ attrs:`data-fam-delete="${idx}"`, cls:'cd-res-fam-action--delete', icon:deleteIcon, label:deleteLabel, title:deleteLabel, ..._lockInv }));
        const searchText = [f.nombre, roleLabel, f.parentesco, f.email, f.telefono, f.estado_cuenta, stateKey, f.seat_scope].filter(Boolean).join(' ');
        const _famHdr = idx === 0 ? `<div class="cd-rri-hd" aria-hidden="true"><div class="cd-rri-col cd-rri-col--name">Nombre</div><div class="cd-rri-col cd-rri-col--email">Correo</div><div class="cd-rri-col cd-rri-col--phone">Teléfono</div><div class="cd-rri-col cd-rri-col--status">Estado</div><div class="cd-rri-col cd-rri-col--actions"></div></div>` : '';
        return `${_famHdr}<div class="cd-res-fam-item cd-res-fam-item--actionable cd-res-row-item" data-fam-idx="${idx}" data-fam-role="${esc(roleKey)}" data-fam-state="${esc(stateKey)}" data-res-search-text="${esc(searchText)}" role="button" tabindex="0" title="Ver detalles de ${esc(f.nombre || '')}">
            <div class="cd-rri-col cd-rri-col--name">
                <span class="cd-rri-initials">${esc(initials || '?')}</span>
                <span class="cd-rri-name-stack">
                    <span class="cd-rri-label">${esc(f.nombre || 'Sin nombre')}</span>
                    <span class="cd-res-fam-rel">${esc(rel || roleLabel)}</span>
                </span>
            </div>
            <div class="cd-rri-col cd-rri-col--email">${mail ? `<a href="mailto:${esc(mail)}" onclick="event.stopPropagation()" class="cd-rri-val">${esc(mail)}</a>` : '<span class="cd-rri-empty">—</span>'}</div>
            <div class="cd-rri-col cd-rri-col--phone">${tel ? `<a href="tel:${esc(tel)}" onclick="event.stopPropagation()" class="cd-rri-val">${esc(tel)}</a>` : '<span class="cd-rri-empty">—</span>'}</div>
            <div class="cd-rri-col cd-rri-col--status">${statusBadge(f)}${seatBadge(f)}</div>
            <div class="cd-rri-col cd-rri-col--actions">${_resFamMenu(actions)}</div>
        </div>`;
    }).join('')}</div><div class="cd-res-subpanel-empty cd-res-search-empty" data-res-search-empty hidden>No hay familiares que coincidan con los filtros.</div>`;
    // Wire notif buttons after innerHTML is set — done via delegated handler in _resRenderSubcard
    setTimeout(() => {
        document.querySelectorAll(`[data-subcard="${resId}"] .cd-res-fam-action--notif`).forEach(btn => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (!_resFamActionStart(btn, 'Abriendo')) return;
                const i = parseInt(btn.dataset.famNotif);
                const fam = list[i];
                if (fam) _resOpenFamiliarNotif(resId, fam);
                setTimeout(() => _resFamActionEnd(btn), 500);
            });
        });
    }, 0);
    return html;
}

function _resFamiliarNameParts(fam) {
    const raw = (fam.nombre || '').trim();
    const parts = raw.split(/\s+/).filter(Boolean);
    if (!parts.length) return { nombre: '', apellido: '' };
    if (parts.length === 1) return { nombre: parts[0], apellido: '' };
    return { nombre: parts.slice(0, -1).join(' '), apellido: parts.slice(-1).join('') };
}

async function _resRunSidebarAction(btn, label, work, doneMessage = '') {
    if (!btn || btn.dataset.busy === '1' || btn.disabled) return false;
    btn.dataset.busy = '1';
    btnLoading?.(btn, label);
    try {
        await work();
        if (document.body.contains(btn)) {
            delete btn.dataset.busy;
            btnReset?.(btn);
        }
        if (doneMessage && typeof window.cdSidebarActionStatus === 'function') window.cdSidebarActionStatus(doneMessage, 'done');
        return true;
    } catch(e) {
        showToast?.(e.message || 'No se pudo completar la acción', 'error');
        delete btn.dataset.busy;
        btnReset?.(btn);
        return false;
    }
}

async function _resUpdateInvitation(id, values = {}) {
    if (!id) throw new Error('Invitación requerida');
    return api(`${BASE}/api/invitaciones.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'actualizar', id, ...values }),
    });
}

async function _resRefreshFamiliaresPanel(resId, activeTab = 'invitaciones') {
    if (activeTab === 'viewInvitaciones' || !resId) {
        if (typeof loadInvitaciones === 'function') await loadInvitaciones();
        return;
    }
    delete _resExpandCache[resId];
    const sub = document.querySelector(`[data-subcard="${resId}"]`);
    if (!sub) return;
    sub.dataset.loaded = '';
    await _resLoadExpand(resId, sub, activeTab);
}

function _resCollectContactsFromData(data) {
    const residente = data?.residente || {};
    let contacts = [];
    if (residente.contactos_json) {
        try { const parsed = JSON.parse(residente.contactos_json); if (Array.isArray(parsed)) contacts = parsed; } catch(e) { contacts = []; }
    }
    if (!contacts.length) {
        const flat = {
            nombre: residente.contacto_nombre || '', parentesco: residente.contacto_parentesco || '',
            telefono: residente.contacto_telefono || '', telefono2: residente.contacto_telefono2 || '',
            email: residente.contacto_email || '', direccion: residente.contacto_direccion || '', principal: 1
        };
        if (Object.values(flat).some(v => v && v !== 1)) contacts.push(flat);
    }
    return contacts;
}

async function _resSaveFamiliarContact(resId, fam, values, data) {
    const contacts = _resCollectContactsFromData(data);
    const normMail = (v) => (v || '').trim().toLowerCase();
    const normTel = (v) => (v || '').replace(/\D+/g, '');
    const targetMail = normMail(fam.email || values.email);
    const targetTel = normTel(fam.telefono || values.telefono);
    let idx = contacts.findIndex(c => (targetMail && normMail(c.email) === targetMail) || (targetTel && normTel(c.telefono) === targetTel));
    const patch = {
        nombre: values.nombre || '', parentesco: values.parentesco || '', telefono: values.telefono || '',
        telefono2: values.telefono2 || fam.telefono2 || '', email: values.email || '', direccion: values.direccion || fam.direccion || '',
        principal: fam.principal || (contacts.length ? 0 : 1), _usuario_id: fam.usuario_id || undefined
    };
    if (idx >= 0) contacts[idx] = { ...contacts[idx], ...patch };
    else contacts.push(patch);
    const first = contacts[0] || {};
    await api(`${RES_API}?id=${resId}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            contacto_nombre: first.nombre || '', contacto_parentesco: first.parentesco || '',
            contacto_telefono: first.telefono || '', contacto_telefono2: first.telefono2 || '',
            contacto_email: first.email || '', contacto_direccion: first.direccion || '',
            contactos_json: JSON.stringify(contacts),
        }),
    });
}

async function _resRemoveFamiliarContact(resId, fam, data) {
    const contacts = _resCollectContactsFromData(data);
    const contactIdx = Number.isInteger(parseInt(fam.contact_idx, 10)) ? parseInt(fam.contact_idx, 10) : -1;
    if (contactIdx >= 0 && contactIdx < contacts.length) {
        contacts.splice(contactIdx, 1);
        const first = contacts[0] || {};
        await api(`${RES_API}?id=${resId}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contacto_nombre: first.nombre || '', contacto_parentesco: first.parentesco || '',
                contacto_telefono: first.telefono || '', contacto_telefono2: first.telefono2 || '',
                contacto_email: first.email || '', contacto_direccion: first.direccion || '',
                contactos_json: JSON.stringify(contacts),
            }),
        });
        return true;
    }
    const normMail = (v) => (v || '').trim().toLowerCase();
    const normTel = (v) => (v || '').replace(/\D+/g, '');
    const normName = (v) => (v || '').trim().toLowerCase();
    const targetMail = normMail(fam.email);
    const targetTel = normTel(fam.telefono);
    const targetName = normName(fam.nombre);
    const next = contacts.filter(c => {
        if (targetMail && normMail(c.email) === targetMail) return false;
        if (targetTel && normTel(c.telefono) === targetTel) return false;
        if (targetName && normName(c.nombre) === targetName) return false;
        return true;
    });
    if (next.length === contacts.length) return false;
    const first = next[0] || {};
    await api(`${RES_API}?id=${resId}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            contacto_nombre: first.nombre || '', contacto_parentesco: first.parentesco || '',
            contacto_telefono: first.telefono || '', contacto_telefono2: first.telefono2 || '',
            contacto_email: first.email || '', contacto_direccion: first.direccion || '',
            contactos_json: JSON.stringify(next),
        }),
    });
    return true;
}

function _resFamiliarInviteMessage(fam, data = {}) {
    const inv = fam.invitacion || {};
    const token = inv.token || fam.token || '';
    const inst = (typeof INST_NAME !== 'undefined' && INST_NAME) ? INST_NAME : 'GeriApp';
    const name = (fam.nombre || '').trim();
    const residentName = (data?.residente?.nombre || '').trim();
    if (token) {
        const link = `${(typeof APP_URL !== 'undefined' && APP_URL) ? APP_URL : BASE}/register.php?inv=${encodeURIComponent(token)}`;
        return `Te invito a unirte a *${inst}* en GeriApp como *Familiar*.\n\nRegístrate aquí:\n${link}`;
    }
    return `Hola${name ? ' ' + name : ''}, te contactamos desde *${inst}*${residentName ? ' sobre ' + residentName : ''}.`;
}

function _resInviteWizardQrHtml(url) {
    if (!url) return '';
    const src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&format=svg&data=' + encodeURIComponent(url);
    return `<div class="cd-invite-qr-result" data-invite-qr-result>
        <div class="cd-invite-qr-img"><img alt="QR de invitación" src="${esc(src)}"></div>
        <div class="cd-invite-qr-link">${esc(url)}</div>
        <div class="cd-invite-qr-actions">
            <button type="button" class="cd-btn-submit cd-btn-secondary" data-invite-copy-link>Copiar enlace</button>
            <a class="cd-btn-submit cd-btn-secondary" href="${esc(url)}" target="_blank" rel="noopener">Abrir enlace</a>
            <a class="cd-btn-submit cd-btn-secondary cd-invite-wa-share" href="https://wa.me/?text=${encodeURIComponent('Te invito a registrarte en GeriApp: ' + url)}" target="_blank" rel="noopener">Compartir WhatsApp</a>
        </div>
    </div>`;
}

async function _resSendFamiliarWhatsApp(resId, fam, data = {}) {
    const phone = cdNormalizePhone(fam.telefono || fam.invitacion?.telefono || '');
    if (!phone) { showToast?.('Este familiar no tiene teléfono WhatsApp válido', 'error'); return; }
    await api(API_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'send_notif_wa',
            phone,
            message:_resFamiliarInviteMessage(fam, data),
            residente_id:resId,
            tipo:fam.invitacion_id ? 'invitacion' : 'familiar',
        })
    });
    showToast?.('WhatsApp enviado', 'success');
}

async function _resInviteFamiliarWhatsApp(resId, fam, data = {}, refreshTab = 'invitaciones') {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) { showToast?.('Solo un administrador puede invitar familiares', 'error'); return; }
    const phone = cdNormalizePhone(fam.telefono || fam.invitacion?.telefono || '');
    if (!phone) { showToast?.('Este familiar no tiene teléfono WhatsApp válido', 'error'); return; }
    const parts = _resFamiliarNameParts(fam);
    const resp = await api(`${BASE}/api/invitaciones.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            rol:'familiar', whatsapp_only:true, telefono:phone,
            email:(fam.email || '').trim(), nombre_sugerido:parts.nombre,
            apellido_sugerido:parts.apellido, mensaje:'', residente_ids:[resId]
        })
    });
    await _resSaveFamiliarContact(resId, fam, {
        nombre:[parts.nombre, parts.apellido].filter(Boolean).join(' ') || fam.nombre || '',
        parentesco:fam.parentesco || '', telefono:phone, email:fam.email || ''
    }, data).catch(() => null);
    if (resp?.whatsapp_error) showToast?.('Invitación creada; WhatsApp no se pudo enviar', 'error');
    else showToast?.('Invitación enviada por WhatsApp', 'success');
    await _resRefreshFamiliaresPanel(resId, refreshTab);
}

async function _resUnlinkFamiliarUser(resId, userId) {
    const linked = await api(`${BASE}/api/personal.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'get_residentes', usuario_id:userId })
    });
    const ids = (Array.isArray(linked) ? linked : [])
        .map(r => parseInt(r.id, 10))
        .filter(id => id && id !== parseInt(resId, 10));
    await api(`${BASE}/api/personal.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'sync_residentes', usuario_id:userId, residente_ids:ids })
    });
}

async function _resDeleteFamiliar(resId, fam, data = {}, refreshTab = 'invitaciones') {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) { showToast?.('Solo un administrador puede borrar familiares', 'error'); return; }
    const label = fam.invitacion_id ? '¿Borrar esta invitación?' : (fam.usuario_id ? '¿Desvincular este familiar del residente?' : '¿Borrar este contacto?');
    const ok = await cdConfirm(label, { title:'Borrar familiar', type:'danger', okText:'Borrar' });
    if (!ok) return;

    if (fam.invitacion_id) {
        await api(`${BASE}/api/invitaciones.php`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'eliminar', id:fam.invitacion_id })
        });
    }
    if (fam.usuario_id) {
        await _resUnlinkFamiliarUser(resId, fam.usuario_id);
    }
    await _resRemoveFamiliarContact(resId, fam, data).catch(() => null);

    showToast?.('Familiar eliminado', 'success');
    await _resRefreshFamiliaresPanel(resId, refreshTab);
}

async function _resDeleteFamiliarInvite(resId, invitationId, refreshTab = 'invitaciones') {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) { showToast?.('Solo un administrador puede eliminar invitaciones', 'error'); return; }
    const ok = await cdConfirm('¿Eliminar esta invitación?', { title:'Eliminar invitación', type:'danger', okText:'Eliminar' });
    if (!ok) return;
    await api(`${BASE}/api/invitaciones.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'eliminar', id: invitationId })
    });
    showToast?.('Invitación eliminada', 'success');
    await _resRefreshFamiliaresPanel(resId, refreshTab);
}

async function _resResendFamiliarInvite(resId, invitationId, canal = 'all', refreshTab = 'invitaciones') {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) { showToast?.('Solo un administrador puede reenviar invitaciones', 'error'); return; }
    try {
        const resp = await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'reenviar', id: invitationId, dias: 7, canal }) });
        if (canal === 'email') showToast?.(resp?.email_enviado ? 'Invitación reenviada por correo' : 'No se pudo reenviar el correo', resp?.email_enviado ? 'success' : 'error');
        else if (canal === 'whatsapp') showToast?.(resp?.whatsapp_enviado ? 'Invitación reenviada por WhatsApp' : 'No se pudo reenviar el WhatsApp', resp?.whatsapp_enviado ? 'success' : 'error');
        else if (resp?.whatsapp_error) showToast?.('Invitación reenviada por correo; WhatsApp no se pudo enviar', 'error');
        else if (resp?.whatsapp_enviado) showToast?.('Invitación reenviada por correo y WhatsApp', 'success');
        else showToast?.('Invitación reenviada', 'success');
        await _resRefreshFamiliaresPanel(resId, refreshTab);
    } catch(e) {}
}

async function _resReloadExpandedSubcard(resId, activeTab = 'invitaciones') {
    if (activeTab === 'viewInvitaciones' || !resId) {
        if (typeof loadInvitaciones === 'function') await loadInvitaciones();
        return;
    }
    delete _resExpandCache[resId];
    const sub = document.querySelector(`[data-subcard="${resId}"]`);
    if (sub) await _resLoadExpand(resId, sub, activeTab);
}

async function _resCuidResendInvite(resId, invitationId, canal = 'all', refreshTab = 'invitaciones') {
    if (!invitationId) return;
    const resp = await api(`${BASE}/api/invitaciones.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'reenviar', id: parseInt(invitationId, 10), dias: 7, canal })
    });
    if (canal === 'email') showToast?.(resp?.email_enviado ? 'Invitación reenviada por correo' : 'No se pudo reenviar el correo', resp?.email_enviado ? 'success' : 'error');
    else if (canal === 'whatsapp') showToast?.(resp?.whatsapp_enviado ? 'Invitación reenviada por WhatsApp' : 'No se pudo reenviar el WhatsApp', resp?.whatsapp_enviado ? 'success' : 'error');
    else if (resp?.whatsapp_error) showToast?.('Invitación reenviada por correo; WhatsApp no se pudo enviar', 'error');
    else if (resp?.whatsapp_enviado) showToast?.('Invitación reenviada por correo y WhatsApp', 'success');
    else showToast?.('Invitación reenviada', 'success');
    await _resReloadExpandedSubcard(resId, refreshTab);
}

async function _resCuidDeleteInvite(resId, invitationId, refreshTab = 'invitaciones') {
    if (!invitationId) return;
    const ok = await cdConfirm('¿Eliminar esta invitación?', { title:'Eliminar invitación', type:'danger', okText:'Eliminar' });
    if (!ok) return;
    await api(`${BASE}/api/invitaciones.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'eliminar', id: parseInt(invitationId, 10) })
    });
    showToast?.('Invitación eliminada', 'success');
    await _resReloadExpandedSubcard(resId, refreshTab);
}

function _resCuidadoresList(payload) {
    payload = payload || { asignados: [], invitaciones: [] };
    return Array.isArray(payload.asignados) ? payload.asignados : [];
}

function _resCuidadoresTabList(payload) {
    payload = payload || { asignados: [], invitaciones: [], disponibles: [] };
    const rows = [];
    const seen = new Set();
    const keyFor = item => {
        const id = item?.id || item?.usuario_id || item?.user_id || '';
        const email = _resCleanKey(item?.email || '');
        const phone = _resPhoneKey(item?.telefono || '');
        return [id ? `u:${id}` : '', email ? `e:${email}` : '', phone ? `p:${phone}` : ''].filter(Boolean).join('|');
    };
    const add = item => {
        const key = keyFor(item) || [item?.rol || '', item?.nombre || item?.email || item?.telefono || ''].join('|').toLowerCase();
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        rows.push(item);
    };
    _resCuidadoresList(payload)
        .filter(c => ['enfermero', 'cuidador'].includes(String(c?.rol || '').toLowerCase()))
        .forEach(c => add({ ...c, __cuidTabStatus: 'asignado' }));
    (Array.isArray(payload.disponibles) ? payload.disponibles : [])
        .filter(c => ['enfermero', 'cuidador'].includes(String(c?.rol || '').toLowerCase()))
        .forEach(c => add({ ...c, __cuidAvailable: true, __cuidTabStatus: 'disponible' }));
    return rows;
}

function _resCuidInviteHasDestination(cuidador) {
    return !!(String(cuidador?.email || '').trim() || String(cuidador?.telefono || '').trim());
}

function _resOpenCuidadorDetails(resId, cuidador, payload = {}) {
    const canEdit = !!payload.can_edit;
    const refreshTab = payload._refreshTab || 'invitaciones';
    // Registered (non-pending) user → unified drawer
    if (!cuidador.pending_invite && !cuidador.__cuidAvailable) {
        const uid = cuidador.id || cuidador.usuario_id || cuidador.user_id || '';
        return _resOpenRegisteredUserDrawer(resId, {
            nombre: cuidador.nombre || '', email: cuidador.email || '',
            telefono: cuidador.telefono || '', rol: cuidador.rol || 'enfermero',
            usuario_id: uid,
        }, { refreshTab, kind: 'staff' });
    }
    const status = cuidador.pending_invite ? (cuidador.estado || 'pendiente') : (cuidador.__cuidAvailable ? 'disponible' : 'asignado');
    const title = cuidador.pending_invite ? 'Invitación de cuidador' : (cuidador.__cuidAvailable ? 'Cuidador disponible' : 'Cuidador asignado');
    const canResendInvite = _resCuidInviteHasDestination(cuidador);
    const canEditInvite = !!(canEdit && cuidador.pending_invite && cuidador.invitacion_id && status !== 'aceptada');
    const inviteParts = _resFamiliarNameParts({ nombre: cuidador.nombre || '' });
    const inviteEdit = canEditInvite ? `<div class="cd-inv-detail-channels cd-inv-detail-edit-channels">
        ${_resInvEditChannel({ icon:'badge', label:'Rol', status:'Editable', control:`<select class="cd-input cd-inv-channel-input" id="cdCuidInvEditRole">${_resInvitationRoleOptions(cuidador.rol || 'enfermero')}</select>` })}
        ${_resInvEditChannel({ icon:'person', label:'Nombre', status:'Editable', control:`<input class="cd-input cd-inv-channel-input" id="cdCuidInvEditNombre" value="${esc(cuidador.nombre_sugerido || inviteParts.nombre)}">` })}
        ${_resInvEditChannel({ icon:'person', label:'Apellido', status:'Editable', control:`<input class="cd-input cd-inv-channel-input" id="cdCuidInvEditApellido" value="${esc(cuidador.apellido_sugerido || inviteParts.apellido)}">` })}
        ${_resInvEditChannel({ icon:'chat', label:'Mensaje', status:'Opcional', control:`<textarea class="cd-input cd-inv-channel-input" id="cdCuidInvEditMsg" rows="3" placeholder="Mensaje opcional para la invitación">${esc(cuidador.mensaje || '')}</textarea>` })}
    </div>` : '';
    const inviteDetails = cuidador.pending_invite ? _resInvitationDetailsHtml({
        kind: 'cuidador', role: _resRoleLabel(cuidador.rol), raw: cuidador,
        name: cuidador.nombre || cuidador.email || 'Invitación pendiente',
        email: cuidador.email || '', telefono: cuidador.telefono || '', estado: status,
        enviada: cuidador.enviada || '', expira: cuidador.expira || '',
        creadoPor: cuidador.creado_por_nombre || '', revocadoPor: cuidador.revocado_por_nombre || '',
        editEmailId: canEditInvite ? 'cdCuidInvEditEmail' : '',
        editTelId:   canEditInvite ? 'cdCuidInvEditTel' : '',
        editHtml: inviteEdit,
    }) : '';
    const registeredChannels = !cuidador.pending_invite ? `<div class="cd-inv-detail-channels cd-inv-detail-meta-channels">
        ${_resInvInfoChannel({ icon:'person', label:'Nombre', status:'Registrado', value: cuidador.nombre || 'Sin nombre' })}
        ${_resInvInfoChannel({ icon:'badge', label:'Rol', status:'', value: _resRoleLabel(cuidador.rol) || '—' })}
        ${cuidador.email ? _resInvInfoChannel({ icon:'mail', label:'Correo', status:'', value: cuidador.email }) : ''}
        ${cuidador.telefono ? _resInvInfoChannel({ icon:'call', label:'Teléfono', status:'', value: cuidador.telefono }) : ''}
        ${_resInvInfoChannel({ icon:'badge_check', label:'Estado', status:'', value: status })}
    </div>` : '';
    const pendingTable = cuidador.pending_invite ? `<table class="cd-sb-vitals-table"><tbody>
            <tr><th>Nombre</th><td>${esc(cuidador.nombre || 'Sin nombre')}</td></tr>
            <tr><th>Rol</th><td>${esc(_resRoleLabel(cuidador.rol) || '—')}</td></tr>
            <tr><th>Estado</th><td>${esc(status)}</td></tr>
            <tr><th>Email</th><td>${cuidador.email ? `<a href="mailto:${esc(cuidador.email)}">${esc(cuidador.email)}</a>` : 'Sin correo'}</td></tr>
            <tr><th>Teléfono</th><td>${cuidador.telefono ? `<a href="tel:${esc(cuidador.telefono)}">${esc(cuidador.telefono)}</a>` : 'Sin teléfono'}</td></tr>
            ${cuidador.enviada ? `<tr><th>Enviada</th><td>${esc(cuidador.enviada)}</td></tr>` : ''}
            ${cuidador.expira ? `<tr><th>Expira</th><td>${esc(cuidador.expira)}</td></tr>` : ''}
        </tbody></table>` : '';
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Detalle</div>
        ${inviteDetails}
        ${registeredChannels}
        ${pendingTable}
    </div>`;
    const actions = [`<button class="cd-btn-submit cd-btn-secondary" id="cdCuidDetailClose">Cerrar</button>`];
    if (canEdit && cuidador.pending_invite && cuidador.invitacion_id) {
        if (canEditInvite) actions.push(`<button class="cd-btn-submit" id="cdCuidDetailSave">Guardar cambios</button>`);
        actions.push(canResendInvite
            ? `<button class="cd-btn-submit cd-btn-secondary" id="cdCuidDetailResend">Reenviar invitación</button>`
            : `<button class="cd-btn-submit cd-btn-secondary" id="cdCuidDetailResend" disabled aria-disabled="true" title="Agrega un email o teléfono WhatsApp antes de reenviar">Reenviar invitación</button>`);
        actions.push(`<button class="cd-btn-submit cd-btn-danger" id="cdCuidDetailDelete">Eliminar invitación</button>`);
    } else if (canEdit) {
        actions.push(`<button class="cd-btn-submit" id="cdCuidDetailEdit">Editar asignaciones</button>`);
    }
    openSidebar(title, body, actions.join(''));
    _resBindInviteWhatsAppStatus(document);
    $('#cdCuidDetailClose')?.addEventListener('click', closeSidebar);
    $('#cdCuidDetailEdit')?.addEventListener('click', () => _resOpenCuidadoresEdit(resId));
    $('#cdCuidDetailSave')?.addEventListener('click', async () => {
        const btn = $('#cdCuidDetailSave');
        await _resRunSidebarAction(btn, 'Guardando', async () => {
            const telInput = $('#cdCuidInvEditTel');
            await _resUpdateInvitation(cuidador.invitacion_id, {
                email: $('#cdCuidInvEditEmail')?.value?.trim() || '',
                telefono: (window.cdNormalizePhoneInput ? window.cdNormalizePhoneInput(telInput) : telInput?.value?.trim()) || '',
                rol: $('#cdCuidInvEditRole')?.value || cuidador.rol || 'enfermero',
                nombre_sugerido: $('#cdCuidInvEditNombre')?.value?.trim() || '',
                apellido_sugerido: $('#cdCuidInvEditApellido')?.value?.trim() || '',
                mensaje: $('#cdCuidInvEditMsg')?.value?.trim() || '',
            });
            showToast?.('Invitación actualizada', 'success');
            closeSidebar();
            await _resRefreshFamiliaresPanel(resId, refreshTab);
        }, 'Cambios guardados');
    });
    $('#cdCuidDetailResend')?.addEventListener('click', async () => {
        if (!_resCuidInviteHasDestination(cuidador)) { showToast?.('Agrega un email o teléfono WhatsApp antes de reenviar', 'warning'); return; }
        const btn = $('#cdCuidDetailResend');
        await _resRunSidebarAction(btn, 'Reenviando', async () => {
            await _resCuidResendInvite(resId, cuidador.invitacion_id, 'all', refreshTab);
            closeSidebar();
        }, 'Invitación reenviada');
    });
    $('#cdCuidDetailDelete')?.addEventListener('click', async () => {
        const btn = $('#cdCuidDetailDelete');
        await _resRunSidebarAction(btn, 'Eliminando', async () => {
            await _resCuidDeleteInvite(resId, cuidador.invitacion_id, refreshTab);
            closeSidebar();
        }, 'Invitación eliminada');
    });
}

function _resRolePermissionNote(role) {
    const map = {
        familiar: {
            label: 'Familiar',
            desc: 'Diseñado para un familiar directo o persona cercana al residente. Tiene visibilidad limitada para mantener al círculo afectivo informado sin interferir en la operación clínica.',
            can: [
                'Ver el perfil y datos generales del residente',
                'Recibir notificaciones de salud y emergencias',
                'Consultar notas y actualizaciones compartidas por el equipo',
            ],
            cannot: [
                'Registrar o modificar datos clínicos ni prescripciones',
                'Ver notas médicas privadas o expediente completo',
                'Gestionar otros usuarios o configurar la institución',
            ],
        },
        medico: {
            label: 'Médico',
            desc: 'Diseñado para un profesional de la salud que lleva la atención médica del residente. Tiene acceso clínico completo dentro del contexto del residente asignado.',
            can: [
                'Registrar y consultar notas médicas y prescripciones',
                'Ver y actualizar el historial clínico completo',
                'Consultar signos vitales, evolución y cuidados registrados',
            ],
            cannot: [
                'Gestionar usuarios, invitaciones ni configuración general',
                'Ver información financiera ni módulos de facturación',
                'Acceder a residentes que no tenga asignados',
            ],
        },
        enfermero: {
            label: 'Cuidador',
            desc: 'Diseñado para el personal de cuidado directo: enfermeros, auxiliares o cuidadores. Cubre el registro operativo diario del residente.',
            can: [
                'Registrar cuidados: alimentación, higiene, sueño, ánimo',
                'Registrar signos vitales y administración de medicación',
                'Consultar la bitácora diaria y notas del equipo',
            ],
            cannot: [
                'Escribir notas médicas ni modificar prescripciones',
                'Gestionar usuarios, invitaciones ni configuración',
                'Ver módulos de facturación ni reportes administrativos',
            ],
        },
        admin: {
            label: 'Administrador',
            desc: 'Diseñado para el responsable operativo de la institución. No se vincula a un residente específico; tiene acceso transversal a toda la plataforma.',
            can: [
                'Acceder a todos los residentes de la institución',
                'Gestionar usuarios, invitaciones y roles',
                'Ver reportes, configuración y módulos de facturación',
            ],
            cannot: [
                'Modificar configuración global del sistema (reservado a superadmin)',
                'Acceder a instituciones distintas a la suya',
            ],
        },
    };
    const def = map[role] || map.familiar;
    const canHtml  = `<ul class="cd-role-note-list">${def.can.map(i => `<li>${i}</li>`).join('')}</ul>`;
    const cantHtml = `<ul class="cd-role-note-list cd-role-note-list--cannot">${def.cannot.map(i => `<li>${i}</li>`).join('')}</ul>`;
    return `<strong>${def.label}</strong><p class="cd-role-note-desc">${def.desc}</p>`
         + `<span class="cd-role-note-section-label">Puede</span>${canHtml}`
         + `<span class="cd-role-note-section-label cd-role-note-section-label--cannot">No puede</span>${cantHtml}`;
}

function _resOpenFamiliarInvite(resId, fam = {}, data = {}, refreshTab = 'invitaciones') {
    if (!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN)) { showToast?.('Solo un administrador puede invitar', 'error'); return; }
    const roleCard = (val, ic, label) => `<label class="cd-invite-mode" data-invite-role-card="${val}"><input type="radio" name="cdInvRole" value="${val}"><span class="material-symbols-outlined" aria-hidden="true">${ic}</span><strong>${label}</strong></label>`;
    const residentGlobal = (typeof RESIDENTES !== 'undefined' && Array.isArray(RESIDENTES)) ? RESIDENTES : [];
    const residentOptionsSource = Array.isArray(_resMgmtData) && _resMgmtData.length ? _resMgmtData : residentGlobal;
    const residentChecks = residentOptionsSource.map(r => {
        const id = parseInt(r.id, 10);
        if (!id) return '';
        const label = (r.nombre_completo || [r.nombre || '', r.apellidos || ''].join(' ')).trim() || `Residente #${id}`;
        return `<label class="cd-invite-res-check"><input type="checkbox" value="${id}" data-invite-res-check><span>${esc(label)}</span></label>`;
    }).filter(Boolean).join('');
    const targetResidentPicker = !resId ? `<div class="cd-sidebar-section cd-invite-resident-section" data-invite-resident-wrap>
        <div class="cd-sidebar-section-title"><span data-invite-res-title>Residente visible</span><span class="cd-required" aria-hidden="true">*</span></div>
        <div class="cd-invite-res-tools" data-invite-res-tools hidden>
            <button type="button" class="cd-invite-res-tool" data-invite-res-all>Seleccionar todos</button>
            <button type="button" class="cd-invite-res-tool" data-invite-res-none>Deseleccionar todos</button>
            <span data-invite-res-count>0 seleccionados</span>
        </div>
        <div class="cd-invite-res-list" data-invite-res-list>
            ${residentChecks || '<div class="cd-res-subpanel-empty">Sin residentes disponibles</div>'}
        </div>
    </div>` : '';
    const body = `<div class="cd-sidebar-section cd-invite-wizard">
        <div class="cd-sidebar-section-title">Rol del invitado<span class="cd-required" aria-hidden="true">*</span></div>
        <div class="cd-invite-mode-grid cd-invite-role-grid" role="radiogroup" aria-label="Rol del invitado">
            <label class="cd-invite-mode is-active" data-invite-role-card="familiar"><input type="radio" name="cdInvRole" value="familiar" checked><span class="material-symbols-outlined" aria-hidden="true">family_restroom</span><strong>Familiar</strong></label>
            ${roleCard('medico', 'medical_services', 'Médico')}
            ${roleCard('enfermero', 'health_and_safety', 'Cuidador')}
            ${roleCard('admin', 'admin_panel_settings', 'Admin')}
        </div>
        <div class="cd-res-fam-drawer-note" data-invite-role-note>${_resRolePermissionNote('familiar')}</div>
    </div>
    ${targetResidentPicker}
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Datos de contacto</div>
        <label class="cd-label">Email</label><input class="cd-input" id="cdInvEmail" type="email" value="${esc(fam.email || '')}" placeholder="invitado@correo.com" autocomplete="off">
        <label class="cd-label" style="margin-top:12px">Teléfono WhatsApp</label>
        <input class="cd-input" id="cdInvTel" type="tel" value="${esc(fam.telefono || '')}" placeholder="+52 1 555 123 4567" autocomplete="off">
        <div class="cd-inv-wa-status" data-wa-status hidden></div>
        <div class="cd-inv-existing" data-existing-info hidden></div>
    </div>
    <div class="cd-sidebar-section cd-invite-qr-section" data-invite-qr-section hidden>
        <div class="cd-sidebar-section-title">QR generado</div>
        <div class="cd-invite-qr-placeholder" data-invite-qr-placeholder>Presiona “QR” para generar el código y compartirlo.</div>
    </div>`;
    const actions = `<button class="cd-btn-submit cd-btn-secondary" id="cdInvCancel">Cancelar</button>
        <button class="cd-btn-submit cd-invite-channel-btn" id="cdInvSendEmail" data-channel="email" title="Enviar por correo"><span class="material-symbols-outlined" aria-hidden="true">mail</span><span>Correo</span></button>
        <button class="cd-btn-submit cd-invite-channel-btn" id="cdInvSendWa" data-channel="whatsapp" title="Enviar por WhatsApp"><span class="material-symbols-outlined" aria-hidden="true">send_to_mobile</span><span>WhatsApp</span></button>
        <button class="cd-btn-submit cd-invite-channel-btn" id="cdInvGenQr" data-channel="qr" title="Generar QR"><span class="material-symbols-outlined" aria-hidden="true">qr_code_2</span><span>QR</span></button>`;
    openSidebar('Crear invitación', body, actions);
    $('#cdInvCancel')?.addEventListener('click', closeSidebar);

    const getRole   = () => document.querySelector('input[name="cdInvRole"]:checked')?.value || 'familiar';
    const getResidentChecks = () => Array.from(document.querySelectorAll('[data-invite-res-check]'));
    const getTargetResIds = () => {
        if (resId) return [parseInt(resId, 10)].filter(Boolean);
        if (getRole() === 'admin') return [];
        return getResidentChecks().filter(ch => ch.checked).map(ch => parseInt(ch.value, 10)).filter(Boolean);
    };
    const getTargetResId = () => getTargetResIds()[0] || 0;
    const syncResidentPicker = () => {
        const role = document.querySelector('input[name="cdInvRole"]:checked')?.value || 'familiar';
        document.querySelectorAll('[data-invite-role-card]').forEach(c => c.classList.toggle('is-active', c.dataset.inviteRoleCard === role));
        const note = $('[data-invite-role-note]');
        if (note) note.innerHTML = _resRolePermissionNote(role);
        const residentWrap = $('[data-invite-resident-wrap]');
        if (residentWrap) residentWrap.hidden = role === 'admin';
        const multi = role === 'medico' || role === 'enfermero';
        const title = $('[data-invite-res-title]');
        if (title) title.textContent = multi ? 'Residentes visibles' : 'Residente visible';
        const tools = $('[data-invite-res-tools]');
        if (tools) tools.hidden = !multi;
        const checks = getResidentChecks();
        if (!multi) {
            const firstChecked = checks.find(ch => ch.checked);
            checks.forEach(ch => { ch.checked = firstChecked ? ch === firstChecked : false; });
        }
        const count = $('[data-invite-res-count]');
        if (count) count.textContent = `${getTargetResIds().length} seleccionado${getTargetResIds().length === 1 ? '' : 's'}`;
    };
    document.querySelectorAll('input[name="cdInvRole"]').forEach(input => input.addEventListener('change', syncResidentPicker));
    getResidentChecks().forEach(ch => ch.addEventListener('change', () => {
        if (getRole() !== 'medico' && getRole() !== 'enfermero' && ch.checked) {
            getResidentChecks().forEach(other => { if (other !== ch) other.checked = false; });
        }
        syncResidentPicker();
        forceLookup();
    }));
    $('[data-invite-res-all]')?.addEventListener('click', () => { getResidentChecks().forEach(ch => { ch.checked = true; }); syncResidentPicker(); forceLookup(); });
    $('[data-invite-res-none]')?.addEventListener('click', () => { getResidentChecks().forEach(ch => { ch.checked = false; }); syncResidentPicker(); forceLookup(); });
    syncResidentPicker();

    const getEmail  = () => $('#cdInvEmail')?.value?.trim() || '';
    const getPhone  = () => {
        const i = $('#cdInvTel');
        return (window.cdNormalizePhoneInput ? window.cdNormalizePhoneInput(i) : i?.value?.trim()) || '';
    };

    // ── Validación WhatsApp en vivo ─────────────────────────────────────────
    const waBox = $('[data-wa-status]');
    const setWaState = (text, kind) => {
        if (!waBox) return;
        if (!text) { waBox.hidden = true; waBox.textContent = ''; waBox.dataset.kind = ''; return; }
        waBox.hidden = false; waBox.textContent = text; waBox.dataset.kind = kind || '';
    };
    let waTimer = null; let waLastChecked = '';
    const checkWa = async () => {
        const phone = getPhone();
        if (!phone || phone.length < 8) { setWaState('', ''); waLastChecked = ''; return; }
        if (phone === waLastChecked) return;
        waLastChecked = phone;
        setWaState('Verificando si está en WhatsApp…', 'pending');
        try {
            const res = await api(`${API_URL}?check_whatsapp=${encodeURIComponent(phone)}`);
            if (waLastChecked !== phone) return;
            if (res?.registered === true)  setWaState('Número registrado en WhatsApp', 'ok');
            else if (res?.registered === false) setWaState('Este número no está en WhatsApp', 'warn');
            else setWaState('No se pudo verificar el número', 'warn');
        } catch (e) { setWaState('No se pudo verificar el número', 'warn'); }
    };
    $('#cdInvTel')?.addEventListener('input', () => { clearTimeout(waTimer); setWaState('', ''); waTimer = setTimeout(checkWa, 600); });
    $('#cdInvTel')?.addEventListener('blur', () => { clearTimeout(waTimer); checkWa(); });
    if (getPhone()) setTimeout(checkWa, 80);

    // ── Detección de existente (email / teléfono) ──────────────────────────
    const existingBox = $('[data-existing-info]');
    let existingState = { user: null, invitacion: null, key: '' };
    const getExistingMissingResIds = (user) => {
        const selected = getTargetResIds();
        const linked = new Set((user?.residentes || []).map(r => parseInt(r.id, 10)).filter(Boolean));
        return selected.filter(id => !linked.has(id));
    };
    const renderExisting = () => {
        if (!existingBox) return;
        const { user, invitacion } = existingState;
        if (!user && !invitacion) { existingBox.hidden = true; existingBox.innerHTML = ''; return; }
        const blocks = [];
        if (user) {
            const missingIds = getExistingMissingResIds(user);
            const linkedTxt = !missingIds.length && getTargetResIds().length
                ? 'Ya está vinculado a los residentes seleccionados.'
                : (user.residentes?.length
                    ? `Ya está vinculado a: ${user.residentes.map(r => esc(r.nombre || `#${r.id}`)).join(', ')}.`
                    : 'No está vinculado a ningún residente.');
            const action = !missingIds.length
                ? ''
                : `<button type="button" class="cd-btn-submit cd-btn-secondary cd-inv-existing-link" data-link-existing>Vincular residentes seleccionados</button>`;
            blocks.push(`<div class="cd-inv-existing-card" data-existing-kind="user">
                <div class="cd-inv-existing-icon"><span class="material-symbols-outlined" aria-hidden="true">person_check</span></div>
                <div class="cd-inv-existing-body">
                    <strong>${esc(user.nombre || user.email || user.telefono || 'Usuario existente')}</strong>
                    <span>Rol actual: ${esc(user.rol || '—')}</span>
                    <span>${linkedTxt}</span>
                </div>
                ${action}
            </div>`);
        }
        if (invitacion && (!user || invitacion.email !== user.email)) {
            blocks.push(`<div class="cd-inv-existing-card" data-existing-kind="invite">
                <div class="cd-inv-existing-icon"><span class="material-symbols-outlined" aria-hidden="true">mark_email_unread</span></div>
                <div class="cd-inv-existing-body">
                    <strong>Invitación pendiente</strong>
                    <span>${esc([invitacion.email, invitacion.telefono].filter(Boolean).join(' · '))}</span>
                    <span>Rol: ${esc(invitacion.rol || '—')} · Estado: ${esc(invitacion.estado || '—')}</span>
                </div>
            </div>`);
        }
        existingBox.innerHTML = blocks.join('');
        existingBox.hidden = !blocks.length;
        existingBox.querySelector('[data-link-existing]')?.addEventListener('click', async (ev) => {
            const btn = ev.currentTarget;
            await _resRunSidebarAction(btn, 'Vinculando', async () => {
                const targetResId = getTargetResId();
                const missingIds = getExistingMissingResIds(existingState.user);
                if (!missingIds.length) { showToast?.('Selecciona al menos un residente nuevo para vincular', 'info'); return; }
                for (const rid of missingIds) {
                    await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'link_residente', usuario_id: existingState.user.id, residente_id: rid }) });
                }
                showToast?.(`Usuario vinculado a ${missingIds.length} residente${missingIds.length === 1 ? '' : 's'}`, 'success');
                closeSidebar();
                await _resRefreshFamiliaresPanel(targetResId, refreshTab);
            }, 'Vinculado');
        });
    };
    let lookupTimer = null; let lookupLastKey = '';
    const lookupExisting = async () => {
        const email = getEmail(); const telefono = getPhone();
        const key = `${email}|${telefono}`;
        if (key === lookupLastKey) return;
        lookupLastKey = key;
        if (!email && !telefono) { existingState = { user:null, invitacion:null, key }; renderExisting(); return; }
        try {
            const resp = await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'lookup_existing', email, telefono, residente_id: getTargetResId() || resId || null }) });
            if (lookupLastKey !== key) return;
            existingState = { user: resp?.user || null, invitacion: resp?.invitacion || null, key };
            renderExisting();
        } catch (e) { /* silencioso */ }
    };
    const scheduleLookup = () => { clearTimeout(lookupTimer); lookupTimer = setTimeout(lookupExisting, 500); };
    const forceLookup = () => { clearTimeout(lookupTimer); lookupLastKey = ''; lookupExisting(); };
    $('#cdInvEmail')?.addEventListener('input', scheduleLookup);
    $('#cdInvEmail')?.addEventListener('change', forceLookup);
    $('#cdInvEmail')?.addEventListener('blur', forceLookup);
    $('#cdInvTel')?.addEventListener('input', scheduleLookup);
    $('#cdInvTel')?.addEventListener('change', forceLookup);
    // Re-validar al cambiar el código de país (el input se recompone solo al hacer composePhone)
    document.addEventListener('change', (ev) => {
        const sel = ev.target;
        if (!sel?.classList?.contains?.('cd-phone-country')) return;
        const wrap = sel.closest('.cd-phone-field');
        if (!wrap || !wrap.contains($('#cdInvTel'))) return;
        clearTimeout(waTimer); waLastChecked = ''; setWaState('', '');
        waTimer = setTimeout(checkWa, 200);
        clearTimeout(lookupTimer); lookupLastKey = '';
        lookupTimer = setTimeout(lookupExisting, 250);
    });

    // ── QR result helpers ──────────────────────────────────────────────────
    $('[data-invite-qr-section]')?.addEventListener('click', async e => {
        const copyBtn = e.target.closest('[data-invite-copy-link]');
        if (!copyBtn) return;
        const url = $('[data-invite-qr-result] .cd-invite-qr-link')?.textContent?.trim() || '';
        if (!url) return;
        try { await navigator.clipboard?.writeText(url); showToast?.('Enlace copiado', 'success'); }
        catch(err) { showToast?.('No se pudo copiar el enlace', 'error'); }
    });

    // ── Acción de envío por canal ──────────────────────────────────────────
    const sendInvite = async (channel, btn) => {
        if (!btn || btn.dataset.busy === '1' || btn.dataset.lookupBusy === '1' || btn.disabled) return;
        btn.dataset.lookupBusy = '1';
        try {
        const role = getRole();
        const email = getEmail();
        const telefono = getPhone();
        const targetResId = getTargetResId();
        const targetResIds = getTargetResIds();
        if ((channel === 'email' || channel === 'qr') && email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast?.('Correo inválido', 'error'); return; }
        if (channel === 'email' && !email) { showToast?.('Ingresa un correo', 'error'); return; }
        if (channel === 'whatsapp' && !telefono) { showToast?.('Ingresa un teléfono WhatsApp', 'error'); return; }
        if (channel !== 'qr' && role !== 'admin' && !email && !telefono) { showToast?.('Captura un correo o teléfono', 'error'); return; }
        if (role !== 'admin' && !targetResIds.length) { showToast?.('Selecciona al menos un residente', 'error'); return; }

        lookupLastKey = '';
        await lookupExisting();

        const requestedRank = _resRoleRank(role);
        if (existingState.user && _resRoleRank(existingState.user.rol_storage || existingState.user.rol) > requestedRank) {
            showToast?.(`Esta persona ya tiene un rol superior (${_resRoleLabel(existingState.user.rol_storage || existingState.user.rol)}) en esta institución.`, 'warning');
            renderExisting();
            return;
        }
        if (existingState.invitacion && _resRoleRank(existingState.invitacion.rol_storage || existingState.invitacion.rol) > requestedRank) {
            showToast?.(`Ya existe una invitación pendiente con rol superior (${_resRoleLabel(existingState.invitacion.rol_storage || existingState.invitacion.rol)}).`, 'warning');
            renderExisting();
            return;
        }

        // Si existe usuario y este canal lo vincularía, ofrecer link directo
        const existingMissingIds = existingState.user ? getExistingMissingResIds(existingState.user) : [];
        if (existingState.user && existingMissingIds.length && role !== 'admin') {
            const ok = window.confirm(`Esta persona ya está registrada como ${existingState.user.rol}. ¿Vincularla a los residentes seleccionados en lugar de crear una invitación nueva?`);
            if (ok) {
                await _resRunSidebarAction(btn, 'Vinculando', async () => {
                    for (const rid of existingMissingIds) {
                        await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'link_residente', usuario_id: existingState.user.id, residente_id: rid }) });
                    }
                    showToast?.(`Usuario vinculado a ${existingMissingIds.length} residente${existingMissingIds.length === 1 ? '' : 's'}`, 'success');
                    closeSidebar();
                    await _resRefreshFamiliaresPanel(targetResId, refreshTab);
                }, 'Vinculado');
                return;
            }
        } else if (existingState.user && role !== 'admin') {
            showToast?.('Esta persona ya está vinculada a los residentes seleccionados', 'info');
            return;
        } else if (existingState.user && role === 'admin') {
            showToast?.('Esta persona ya está registrada en la institución', 'info');
            renderExisting();
            return;
        }

        if (existingState.invitacion) {
            showToast?.('Ya existe una invitación pendiente para este correo o teléfono. Revísala en Invitaciones para reenviarla.', 'info');
            renderExisting();
            return;
        }

        const payload = {
            email: channel === 'whatsapp' ? '' : email,
            rol: role,
            telefono: (channel === 'whatsapp' || channel === 'qr') ? telefono : '',
            residente_ids: role === 'admin' ? [] : targetResIds,
            whatsapp_only: channel === 'whatsapp',
            qr_only: channel === 'qr',
            dias: 7,
        };
        const label = channel === 'qr' ? 'Generando' : 'Enviando';
        const doneMsg = channel === 'qr' ? 'QR generado' : 'Invitación enviada';
        const completed = await _resRunSidebarAction(btn, label, async () => {
            const resp = await api(`${BASE}/api/invitaciones.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
            if (channel === 'qr') {
                const section = $('[data-invite-qr-section]');
                const target = $('[data-invite-qr-result]') || $('[data-invite-qr-placeholder]');
                if (section) section.hidden = false;
                if (target) target.outerHTML = _resInviteWizardQrHtml(resp?.url || '');
                showToast?.('QR generado', 'success');
                await _resRefreshFamiliaresPanel(targetResId, refreshTab);
                return;
            }
            if (channel === 'whatsapp' && resp?.whatsapp_error) showToast?.('No se pudo enviar el WhatsApp: ' + (resp.whatsapp_error || ''), 'error');
            else if (channel === 'email') showToast?.('Invitación enviada por correo', 'success');
            else if (channel === 'whatsapp') showToast?.('Invitación enviada por WhatsApp', 'success');
            closeSidebar();
            await _resRefreshFamiliaresPanel(targetResId, refreshTab);
        }, doneMsg);
        if (completed && channel === 'qr') {
            const nextBtn = $('#cdInvGenQr');
            if (nextBtn) { const lbl = nextBtn.querySelector('span:not(.material-symbols-outlined)'); if (lbl) lbl.textContent = 'Generar otro QR'; }
        }
        } finally {
            delete btn.dataset.lookupBusy;
        }
    };
    $('#cdInvSendEmail')?.addEventListener('click', e => sendInvite('email',    e.currentTarget));
    $('#cdInvSendWa')   ?.addEventListener('click', e => sendInvite('whatsapp', e.currentTarget));
    $('#cdInvGenQr')    ?.addEventListener('click', e => sendInvite('qr',       e.currentTarget));
}

// ─── Drawer unificado para usuario registrado (familiar o cuidador) ──────────
function _resOpenRegisteredUserDrawer(resId, user, opts = {}) {
    // user fields: nombre, email, telefono, rol, usuario_id
    // opts: { refreshTab, kind, relLabel }
    const refreshTab = opts.refreshTab || 'invitaciones';
    const kind       = opts.kind || 'familiar';   // 'familiar' | 'staff'
    const relLabel   = opts.relLabel || '';
    const canAdmin   = !!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN);
    const uid        = user.usuario_id || user.id || user.user_id || '';
    const rol        = String(user.rol || (kind === 'familiar' ? 'familiar' : 'enfermero')).toLowerCase();

    const roleSection = canAdmin ? `
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Gestión de cuenta</div>
        <div class="cd-inv-detail-channels cd-inv-detail-edit-channels">
            ${_resInvEditChannel({ icon:'badge', label:'Rol', status:'Editable',
                control:`<select class="cd-input cd-inv-channel-input" id="cdRegUserRol">${_resInvitationRoleOptions(rol)}</select>` })}
        </div>
        <div class="cd-res-fam-drawer-note" id="cdRegUserRolNote" style="margin-top:6px">${_resRolePermissionNote(rol)}</div>
    </div>` : '';

    const body = `
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Datos del usuario</div>
        <div class="cd-inv-detail-channels cd-inv-detail-meta-channels">
            ${_resInvInfoChannel({ icon:'person',        label:'Nombre',   status:'Registrado', value: user.nombre    || 'Sin nombre' })}
            ${_resInvInfoChannel({ icon:'badge',         label:'Rol',      status:'',           value: _resRoleLabel(rol) })}
            ${user.email    ? _resInvInfoChannel({ icon:'mail',  label:'Correo',   status:'', value: user.email    }) : ''}
            ${user.telefono ? _resInvInfoChannel({ icon:'call',  label:'Teléfono', status:'', value: user.telefono }) : ''}
            ${relLabel      ? _resInvInfoChannel({ icon:'group', label:'Relación', status:'', value: relLabel      }) : ''}
            ${_resInvInfoChannel({ icon:'verified_user', label:'Cuenta',   status:'Activa',     value: 'Usuario registrado' })}
        </div>
    </div>
    ${roleSection}`;

    const actions = [
        `<button class="cd-btn-submit cd-btn-secondary" id="cdRegUserClose">Cerrar</button>`,
        ...(canAdmin ? [`<button class="cd-btn-submit" id="cdRegUserSave">Guardar cambios</button>`] : []),
    ].join('');

    openSidebar(user.nombre || 'Usuario registrado', body, actions);

    document.getElementById('cdRegUserClose')?.addEventListener('click', closeSidebar);

    // Live role-note update
    document.getElementById('cdRegUserRol')?.addEventListener('change', e => {
        const note = document.getElementById('cdRegUserRolNote');
        if (note) note.innerHTML = _resRolePermissionNote(e.target.value);
    });

    document.getElementById('cdRegUserSave')?.addEventListener('click', async () => {
        if (!canAdmin) return;
        const btn = document.getElementById('cdRegUserSave');
        const newRol = document.getElementById('cdRegUserRol')?.value;
        if (!uid) { showToast?.('No se encontró el ID de usuario', 'error'); return; }
        await _resRunSidebarAction(btn, 'Guardando', async () => {
            if (newRol && newRol !== rol) {
                await api(`${BASE}/api/personal.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cambiar_rol', usuario_id: uid, rol: newRol }),
                });
            }
            showToast?.('Usuario actualizado', 'success');
            closeSidebar();
            await _resRefreshFamiliaresPanel(resId, refreshTab);
        }, 'Cambios guardados');
    });
}

function _resOpenFamiliarManage(resId, fam, data = {}, refreshTab = 'invitaciones') {
    // Registered user → unified drawer
    if (fam.usuario_id) {
        return _resOpenRegisteredUserDrawer(resId, {
            nombre: fam.nombre || '', email: fam.email || '',
            telefono: fam.telefono || '', rol: fam.rol || 'familiar',
            usuario_id: fam.usuario_id,
        }, { refreshTab, kind: 'familiar', relLabel: fam.parentesco || '' });
    }
    const inv = fam.invitacion || {};
    const canAdmin = !!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN);
    const payNote = fam.seat_scope === 'extra'
        ? `<div class="cd-res-fam-drawer-note">Asiento extra: si aún no está registrado, al aceptar la invitación podrá pagar su propio asiento desde Facturación.</div>` : '';
    const inviteParts = {
        nombre: inv.nombre_sugerido || _resFamiliarNameParts(fam).nombre,
        apellido: inv.apellido_sugerido || _resFamiliarNameParts(fam).apellido,
    };
    const canEditInvite = !!(canAdmin && fam.invitacion_id && inv.status !== 'aceptada');
    const contactEdit = !canEditInvite ? `<div class="cd-inv-detail-channels cd-inv-detail-edit-channels">
        ${_resInvEditChannel({ icon:'person', label:'Nombre', status:'Editable', control:`<input class="cd-input cd-inv-channel-input" id="cdFamEditNombre" value="${esc(fam.nombre || '')}">` })}
    </div>` : '';
    const inviteEdit = canEditInvite ? `<div class="cd-inv-detail-channels cd-inv-detail-edit-channels">
        ${_resInvEditChannel({ icon:'badge', label:'Rol', status:'Editable', control:`<select class="cd-input cd-inv-channel-input" id="cdFamInvEditRole">${_resInvitationRoleOptions(inv.rol || fam.rol || 'familiar')}</select>` })}
        ${_resInvEditChannel({ icon:'person', label:'Nombre', status:'Editable', control:`<input class="cd-input cd-inv-channel-input" id="cdFamInvEditNombre" value="${esc(inviteParts.nombre)}">` })}
        ${_resInvEditChannel({ icon:'person', label:'Apellido', status:'Editable', control:`<input class="cd-input cd-inv-channel-input" id="cdFamInvEditApellido" value="${esc(inviteParts.apellido)}">` })}
        ${_resInvEditChannel({ icon:'chat', label:'Mensaje', status:'Opcional', control:`<textarea class="cd-input cd-inv-channel-input" id="cdFamInvEditMsg" rows="3" placeholder="Mensaje opcional para la invitación">${esc(inv.mensaje || '')}</textarea>` })}
    </div>` : '';
    const detailEditHtml = `${contactEdit}${inviteEdit}`;
    const accountLabel = fam.usuario_id ? 'Usuario registrado' : (fam.invitacion_id ? 'Invitación enviada' : 'Contacto sin invitación');
    const seatLabel = fam.seat_scope === 'extra' ? 'Extra / pago propio' : (fam.seat_scope === 'incluido' ? 'Incluido en paquete' : 'Sin límite');
    const metaHtml = `<div class="cd-inv-detail-channels cd-inv-detail-meta-channels">
        ${_resInvInfoChannel({ icon:'account_circle', label:'Cuenta', status:fam.usuario_id ? 'Registrada' : 'Pendiente', value:accountLabel })}
        ${_resInvInfoChannel({ icon:'event_seat', label:'Cupo', status:fam.seat_scope === 'extra' ? 'Extra' : 'Base', value:seatLabel })}
        ${inv.status ? _resInvInfoChannel({ icon:'mark_email_read', label:'Invitación', status:'Estado', value:inv.status }) : ''}
        ${inv.expira ? _resInvInfoChannel({ icon:'event_busy', label:'Expira', status:'Vigencia', value:inv.expira }) : ''}
    </div>`;
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Estado</div>
        ${_resInvitationDetailsHtml({
            kind: 'familiar', role: 'Familiar', raw: fam,
            name: fam.nombre || [inv.nombre_sugerido, inv.apellido_sugerido].filter(Boolean).join(' ') || 'Familiar',
            email: fam.email || inv.email || '', telefono: fam.telefono || inv.telefono || '',
            legacyContact: !fam.invitacion_id && (fam.source === 'contacto' || (fam.estado_cuenta || '') === 'contacto'),
            noEnviada: !fam.invitacion_id,
            completed: !!fam.usuario_id,
            estado: inv.status || fam.estado_cuenta || (fam.usuario_id ? 'completada' : 'pendiente'), enviada: inv.enviada || '', expira: inv.expira || '',
            creadoPor: inv.creado_por_nombre || fam.creado_por_nombre || (!fam.invitacion_id ? 'Contacto legacy' : ''), revocadoPor: inv.revocado_por_nombre || fam.revocado_por_nombre || '',
            editEmailId: canEditInvite ? 'cdFamInvEditEmail' : 'cdFamEditEmail',
            editTelId:   canEditInvite ? 'cdFamInvEditTel'   : 'cdFamEditTel',
            editHtml: detailEditHtml,
        })}
        ${metaHtml}
        ${payNote}
    </div>`;
    const _famLockAttrs = `data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden gestionar invitaciones."`;
    const actions = `<div style="display:flex;gap:8px"><button class="cd-btn-submit cd-btn-secondary" style="flex:1" id="cdFamEditClose">Cerrar</button>${!fam.usuario_id && !fam.invitacion_id ? `<button class="cd-btn-submit cd-btn-secondary${canAdmin ? '' : ' cd-role-locked'}" style="flex:1" id="cdFamEditInvite" ${!canAdmin ? _famLockAttrs : ''}>Invitar</button>` : ''}${fam.invitacion_id && inv.status !== 'aceptada' ? `<button class="cd-btn-submit cd-btn-secondary${canAdmin ? '' : ' cd-role-locked'}" style="flex:1" id="cdFamEditResend" ${!canAdmin ? _famLockAttrs : ''}>Reenviar</button>` : ''}</div><button class="cd-btn-submit" id="cdFamEditSave">Guardar cambios</button>`;
    openSidebar('Administrar familiar', body, actions);
    _resBindInviteWhatsAppStatus(document);
    $('#cdFamEditClose')?.addEventListener('click', closeSidebar);
    $('#cdFamEditInvite')?.addEventListener('click', () => _resOpenFamiliarInvite(resId, fam, data, refreshTab));
    $('#cdFamEditResend')?.addEventListener('click', async () => {
        const btn = $('#cdFamEditResend');
        await _resRunSidebarAction(btn, 'Reenviando', async () => {
            if (fam.invitacion_id) await _resResendFamiliarInvite(resId, fam.invitacion_id, 'all', refreshTab);
            closeSidebar();
        }, 'Invitación reenviada');
    });
    $('#cdFamEditSave')?.addEventListener('click', async () => {
        const btn = $('#cdFamEditSave');
        const editTelInput = canEditInvite ? $('#cdFamInvEditTel') : $('#cdFamEditTel');
        const values = {
            nombre: canEditInvite
                ? [($('#cdFamInvEditNombre')?.value?.trim() || ''), ($('#cdFamInvEditApellido')?.value?.trim() || '')].filter(Boolean).join(' ')
                : ($('#cdFamEditNombre')?.value?.trim() || ''),
            telefono: (window.cdNormalizePhoneInput ? window.cdNormalizePhoneInput(editTelInput) : editTelInput?.value?.trim()) || '',
            email: canEditInvite ? ($('#cdFamInvEditEmail')?.value?.trim() || '') : ($('#cdFamEditEmail')?.value?.trim() || ''),
        };
        await _resRunSidebarAction(btn, 'Guardando', async () => {
            await _resSaveFamiliarContact(resId, fam, values, data);
            if (canEditInvite) {
                const invTelInput = $('#cdFamInvEditTel');
                const invValues = {
                    email: $('#cdFamInvEditEmail')?.value?.trim() || '',
                    telefono: (window.cdNormalizePhoneInput ? window.cdNormalizePhoneInput(invTelInput) : invTelInput?.value?.trim()) || '',
                    rol: $('#cdFamInvEditRole')?.value || inv.rol || fam.rol || 'familiar',
                    nombre_sugerido: $('#cdFamInvEditNombre')?.value?.trim() || '',
                    apellido_sugerido: $('#cdFamInvEditApellido')?.value?.trim() || '',
                    mensaje: $('#cdFamInvEditMsg')?.value?.trim() || '',
                };
                await _resUpdateInvitation(fam.invitacion_id, invValues);
            }
            showToast?.('Familiar actualizado', 'success');
            closeSidebar();
            await _resRefreshFamiliaresPanel(resId, refreshTab);
        }, 'Cambios guardados');
    });
}

function _resNotifEnabledCount(list) {
    list = Array.isArray(list) ? list : [];
    return list.filter(f => _resNotifEnabledChannels(f).length || _resNotifEnabledTypes(f).length).length;
}

function _resNotifEnabledChannels(fam) {
    const channels = [];
    if (fam?.notif_wa) channels.push('WhatsApp');
    if (fam?.notif_email) channels.push('Email');
    return channels;
}

function _resNotifEnabledTypes(fam) {
    const defs = [
        ['notif_emergencia', 'Emergencias'],
        ['notif_caida', 'Caídas'],
        ['notif_signos', 'Signos'],
        ['notif_incidentes', 'Incidentes'],
        ['notif_medicacion', 'Medicación'],
        ['notif_med_omitida', 'Omisiones'],
        ['notif_alimentacion', 'Alimentación'],
        ['notif_higiene', 'Higiene'],
        ['notif_eliminacion', 'Eliminación'],
        ['notif_sueno', 'Sueño'],
        ['notif_animo', 'Ánimo'],
        ['notif_movilidad', 'Movilidad'],
        ['notif_terapia', 'Terapia'],
        ['notif_reporte', 'Reporte diario'],
        ['notif_semanal', 'Reporte semanal'],
        ['notif_notas_medico', 'Notas médicas'],
        ['notif_visitas', 'Visitas'],
        ['notif_stock', 'Stock'],
    ];
    return defs.filter(([key]) => !!fam?.[key]).map(([, label]) => label);
}

function _resRenderNotificaciones(list, resId, data = {}) {
    list = Array.isArray(list) ? list : [];
    list = list.filter(f => !(!f?.usuario_id && f?.invitacion_id && ((f.invitacion?.status || f.estado_cuenta || '') === 'aceptada')));
    const audience = _resNotifAudience(list, data);
    const valid = audience
        .filter(({ f }) => (f.nombre || '').trim() || (f.email || '').trim() || (f.telefono || '').trim());
    const enabled = _resNotifEnabledCount(valid.map(item => item.f));
    const totalChannels = valid.reduce((sum, item) => {
        if (item.f?.__notifReadonly) {
            return sum + [item.f.telefono, item.f.email].filter(v => String(v || '').trim()).length;
        }
        return sum + _resNotifEnabledChannels(item.f).length;
    }, 0);
    const reportEnabled = valid.filter(item => !!item.f.notif_reporte).length;
    const canInvite = !!(typeof IS_ADMIN !== 'undefined' && IS_ADMIN);
    const firstIdx = valid.length ? valid[0].sourceIdx : '';
    const summary = `<div class="cd-res-notif-summary">
        <div><span>Destinatarios</span><strong>${valid.length}</strong></div>
        <div><span>Con reglas activas</span><strong>${enabled}</strong></div>
        <div><span>Canales activos</span><strong>${totalChannels}</strong></div>
        <div><span>Reporte diario</span><strong>${reportEnabled}</strong></div>
    </div>`;
    const header = `<div class="cd-res-fam-panel-head cd-res-notif-panel-head">
        <div class="cd-res-notif-head-actions">
            <button type="button" class="cd-res-sub-action${canInvite ? '' : ' cd-role-locked'}" data-notif-add-contact="${resId}" title="Agregar destinatario" ${!canInvite ? `data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden agregar destinatarios de notificaciones."` : ''}><span class="material-symbols-outlined" aria-hidden="true">person_add</span><span>Agregar</span></button>
        </div>
    </div>`;
    if (!valid.length) {
        return `${header}<div class="cd-res-subpanel-empty" style="display:flex;flex-direction:column;align-items:center;gap:10px">
            <span>No hay familiares, administradores, cuidadores o médicos con datos de envío para este residente.</span>
            <button type="button" class="cd-btn-submit${canInvite ? '' : ' cd-role-locked'}" data-notif-add-contact="${resId}" ${!canInvite ? `data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden agregar destinatarios de notificaciones."` : ''}>Agregar destinatario</button>
        </div>`;
    }
    const memberRows = valid.map(({ f, sourceIdx, group }) => _resNotifMemberRow(f, sourceIdx, false, group)).join('');
    const recipientChecks = valid.map(({ f, sourceIdx, group }) => _resNotifRecipientCheck(f, sourceIdx, group)).join('');
    return `${header}<div class="cd-res-notif-manager" data-res-notif-manager="${resId}" data-active-contact="${firstIdx}">
        ${_resNotifFilterChips(valid)}
        <div class="cd-res-notif-mini-tabs-row">
            <div class="cd-res-subtabs cd-res-notif-mini-tabs" role="tablist" aria-label="Notificaciones del residente">
                <span class="cd-res-subtabs-pill" aria-hidden="true"></span>
                <button type="button" class="cd-res-subtab is-active" data-res-notif-tab="prefs">Preferencias</button>
                <button type="button" class="cd-res-subtab" data-res-notif-tab="test">Prueba</button>
                <button type="button" class="cd-res-subtab" data-res-notif-tab="log">Historial</button>
            </div>
        </div>
        <div class="cd-res-notif-panel is-active" data-res-notif-panel="prefs">
            <div class="cd-notif-members cd-res-notif-members" data-res-notif-members>${memberRows}</div>
        </div>
        <div class="cd-res-notif-panel" data-res-notif-panel="test">
            <div class="cd-res-notif-send-section">
                <h4 class="cd-notif-send-title">Enviar notificación de prueba</h4>
                <div class="cd-notif-type-select cd-res-notif-type-select">
                    ${_resNotifTypeOption(resId, 'reporte_dia', 'Reporte del día', true)}
                    ${_resNotifTypeOption(resId, 'signos_vitales', 'Signos vitales')}
                    ${_resNotifTypeOption(resId, 'incidente', 'Incidente')}
                    ${_resNotifTypeOption(resId, 'medicacion', 'Medicación')}
                    ${_resNotifTypeOption(resId, 'personalizado', 'Personalizado')}
                </div>
                <textarea class="cd-input cd-res-notif-custom" data-res-notif-custom rows="3" placeholder="Escribe un mensaje personalizado" style="display:none"></textarea>
                <div class="cd-notif-recipients cd-res-notif-recipients">${recipientChecks}</div>
                <div class="cd-res-notif-actions">
                    <button type="button" class="cd-btn-submit" data-res-notif-send="whatsapp">Enviar WhatsApp</button>
                    <button type="button" class="cd-btn-submit cd-btn-secondary" data-res-notif-send="email">Enviar Email</button>
                </div>
            </div>
        </div>
        <div class="cd-res-notif-panel" data-res-notif-panel="log">
            <div class="cd-res-notif-log-head">
                <h4 class="cd-notif-send-title">Historial de notificaciones</h4>
                <button type="button" class="cd-btn-submit cd-btn-secondary" data-res-notif-log-refresh>Actualizar</button>
            </div>
            ${summary}
            <div class="cd-res-notif-log-list" data-res-notif-log-list><p class="cd-notif-empty">Abre el historial para cargar registros.</p></div>
        </div>
    </div>`;
}

function _resNotifMemberRow(f, sourceIdx, active = false, group = 'familiares') {
    const rel = (f.parentesco || '').trim();
    const readonly = !!f.__notifReadonly;
    const availableChannels = [String(f.telefono || '').trim() ? 'WhatsApp' : '', String(f.email || '').trim() ? 'Email' : ''].filter(Boolean);
    const channels = readonly
        ? (availableChannels.join(' · ') || 'Sin canal disponible')
        : (_resNotifEnabledChannels(f).join(' · ') || 'Sin canales');
    const groupLabel = _resNotifGroupLabel(group);
    const bodyHtml = readonly
        ? `<div class="cd-res-notif-readonly"><strong>${esc(groupLabel)}</strong><span>Disponible como destinatario para pruebas y avisos manuales. Sus reglas permanentes se gestionan desde su asignación de usuario.</span></div>`
        : `<div class="cd-res-notif-prefs">${_resNotifPrefsHtml()}</div>`;
    return `<div class="cd-notif-member cd-res-notif-member${active ? ' is-open' : ''}" data-res-notif-member="${sourceIdx}" data-active-contact="${sourceIdx}" data-notif-group="${esc(group)}">
        <button type="button" class="cd-notif-member-head" data-res-notif-member-head aria-expanded="${active ? 'true' : 'false'}">
            <span class="cd-notif-member-avatar">${esc(_resNotifInitials(f))}</span>
            <span class="cd-notif-member-info">
                <span class="cd-notif-member-name">${esc(f.nombre || 'Sin nombre')}</span>
                <span class="cd-notif-member-sub">${esc(channels)}</span>
            </span>
            <span class="cd-res-notif-role-chip">${esc(groupLabel)}</span>
            <svg class="cd-notif-member-chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="cd-notif-member-body" ${active ? '' : 'hidden'}>
            ${bodyHtml}
        </div>
    </div>`;
}

function _resNotifInitials(f) {
    return (f.nombre || '?').trim().split(/\s+/).slice(0,2).map(s => s[0] || '').join('').toUpperCase() || '?';
}

function _resNotifAddMemberRow(resId) {
    return `<button type="button" class="cd-notif-member cd-notif-member--add" data-notif-add-contact="${resId}">
        <span class="cd-notif-member-head">
            <span class="cd-notif-member-avatar cd-notif-member-avatar--add">+</span>
            <span class="cd-notif-member-info">
                <span class="cd-notif-member-name">Agregar familiar/contacto</span>
            </span>
        </span>
    </button>`;
}

function _resNotifContactButton(f, sourceIdx, active = false) {
    const initials = (f.nombre || '?').trim().split(/\s+/).slice(0,2).map(s => s[0] || '').join('').toUpperCase();
    const channels = _resNotifEnabledChannels(f).join(' · ') || 'Sin canal activo';
    const types = _resNotifEnabledTypes(f);
    return `<button type="button" class="cd-res-notif-contact${active ? ' is-active' : ''}" data-res-notif-contact="${sourceIdx}">
        <span class="cd-res-fam-avatar">${esc(initials || '?')}</span>
        <span class="cd-res-notif-contact-copy">
            <strong>${esc(f.nombre || 'Sin nombre')}</strong>
            <small>${esc(channels)}${types.length ? ` · ${types.length} reglas` : ''}</small>
        </span>
    </button>`;
}

function _resNotifRecipientCheck(f, sourceIdx, group = 'familiares') {
    const hasPhone = !!(f.telefono || '').trim();
    const hasEmail = !!(f.email || '').trim();
    return `<label class="cd-notif-recipient cd-res-notif-recipient" data-notif-group="${esc(group)}">
        <input type="checkbox" value="${esc(sourceIdx)}" checked data-name="${esc(f.nombre || '')}" data-phone="${esc(f.telefono || '')}" data-email="${esc(f.email || '')}">
        <span class="cd-notif-recipient-name">${esc(f.nombre || 'Sin nombre')} ${f.parentesco ? `<small>(${esc(f.parentesco)})</small>` : ''} <small class="cd-res-notif-recipient-role">${esc(_resNotifGroupLabel(group))}</small></span>
        <span class="cd-notif-recipient-channels">${hasPhone ? '<span class="cd-notif-ch cd-notif-ch-wa">WA</span>' : ''}${hasEmail ? '<span class="cd-notif-ch cd-notif-ch-em">Email</span>' : ''}</span>
    </label>`;
}

function _resNotifTypeOption(resId, value, label, checked = false) {
    return `<label class="cd-notif-type-option"><input type="radio" name="cdResNotifType${resId}" value="${value}" ${checked ? 'checked' : ''}><span>${esc(label)}</span></label>`;
}

function _resNotifPrefsHtml() {
    const group = (title, items, cls = '') => `<div class="cd-notif-switch-group ${cls}"><h4 class="cd-notif-switch-title">${esc(title)}</h4>${items.map(([field, label]) => _resNotifSwitch(field, label)).join('')}</div>`;
    return `${group('Alertas críticas', [
        ['notif_emergencia', 'Emergencias'], ['notif_signos', 'Signos fuera de rango'], ['notif_incidentes', 'Incidentes'], ['notif_caida', 'Caídas']
    ], 'cd-notif-group--critical')}
    ${group('Cuidados diarios', [
        ['notif_medicacion', 'Medicación'], ['notif_med_omitida', 'Medicación omitida'], ['notif_alimentacion', 'Alimentación'], ['notif_higiene', 'Higiene'], ['notif_eliminacion', 'Eliminación'], ['notif_sueno', 'Sueño'], ['notif_animo', 'Ánimo'], ['notif_movilidad', 'Movilidad'], ['notif_terapia', 'Terapia']
    ])}
    <div class="cd-notif-switch-group">
        <h4 class="cd-notif-switch-title">Reportes y sistema</h4>
        ${_resNotifSwitch('notif_reporte', 'Reporte diario')}
        <div class="cd-res-notif-report-options" data-report-options>
            <label class="cd-label">Hora de reporte</label>
            <input class="cd-input" type="time" data-res-notif-field="notif_reporte_hora" value="08:00">
            ${_resNotifSwitch('notif_reporte_pdf', 'Adjuntar PDF')}
        </div>
        ${_resNotifSwitch('notif_semanal', 'Reporte semanal')}
        ${_resNotifSwitch('notif_notas_medico', 'Notas médicas')}
        ${_resNotifSwitch('notif_visitas', 'Visitas')}
        ${_resNotifSwitch('notif_stock', 'Stock bajo')}
    </div>
    <div class="cd-notif-switch-group">
        <h4 class="cd-notif-switch-title">Avanzadas</h4>
        ${_resNotifSwitch('notif_solo_criticas', 'Solo críticas')}
        ${_resNotifSwitch('notif_quiet_enabled', 'Horario silencioso')}
        <div class="cd-res-notif-quiet" data-quiet-options>
            <input class="cd-input" type="time" data-res-notif-field="notif_quiet_start" value="22:00">
            <span>a</span>
            <input class="cd-input" type="time" data-res-notif-field="notif_quiet_end" value="07:00">
        </div>
    </div>
    <div class="cd-notif-switch-group cd-res-notif-group-channels">
        <h4 class="cd-notif-switch-title">Canales</h4>
        ${_resNotifSwitch('notif_wa', 'WhatsApp')}
        ${_resNotifSwitch('notif_email', 'Email')}
    </div>
    <div class="cd-res-notif-autosave" data-res-notif-autosave aria-live="polite">Los cambios se guardan automáticamente</div>`;
}

const _resNotifInfoMap = {
    notif_emergencia:   { title:'Emergencias',              desc:'Se activa cuando el personal registra un evento de emergencia: caída grave, pérdida de conciencia, paro respiratorio, crisis convulsiva u otro evento crítico que requiera atención inmediata.' },
    notif_signos:       { title:'Signos fuera de rango',    desc:'Se activa cuando se registran signos vitales fuera de los umbrales normales: presión arterial, frecuencia cardíaca, temperatura, saturación de oxígeno o glucemia.' },
    notif_incidentes:   { title:'Incidentes',               desc:'Se activa al registrar cualquier incidente relevante: lesión, accidente, error de medicación u otro evento que quede documentado en la bitácora del residente.' },
    notif_caida:        { title:'Caídas',                   desc:'Se activa específicamente al registrar una caída del residente, ya sea con lesión o sin ella. Incluye caídas de cama, silla o durante traslados.' },
    notif_medicacion:   { title:'Medicación',               desc:'Se activa cuando se registra la administración de un medicamento programado. Confirma al familiar que la medicación del horario fue aplicada correctamente.' },
    notif_med_omitida:  { title:'Medicación omitida',       desc:'Se activa cuando una dosis programada no fue administrada en el horario indicado, ya sea por rechazo del residente, falta de insumo u otro motivo registrado.' },
    notif_alimentacion: { title:'Alimentación',             desc:'Se activa al registrar cada ingesta del residente (desayuno, almuerzo, cena y colaciones), incluyendo el porcentaje consumido y la consistencia de la dieta.' },
    notif_higiene:      { title:'Higiene',                  desc:'Se activa al registrar actividades de higiene personal: baño, aseo bucal, cambio de ropa o cuidado de piel.' },
    notif_eliminacion:  { title:'Eliminación',              desc:'Se activa al registrar eventos de eliminación (orina, heces). Útil para control de estreñimiento, incontinencia o seguimiento de sonda vesical.' },
    notif_sueno:        { title:'Sueño',                    desc:'Se activa al registrar el inicio y fin del descanso nocturno o siesta, permitiendo monitorear la calidad y duración del sueño del residente.' },
    notif_animo:        { title:'Ánimo',                    desc:'Se activa al registrar el estado emocional del residente. Incluye evaluaciones de ánimo (muy triste a muy feliz) y observaciones del equipo sobre el comportamiento del día.' },
    notif_movilidad:    { title:'Movilidad',                desc:'Se activa al registrar actividades de movilización: traslado a silla de ruedas, fisioterapia, paseo o ejercicio.' },
    notif_terapia:      { title:'Terapia',                  desc:'Se activa al registrar sesiones de terapia ocupacional, cognitiva, recreativa o de lenguaje.' },
    notif_reporte:      { title:'Reporte diario',           desc:'Envía automáticamente un resumen del día al horario configurado. Incluye cuidados registrados, medicación, signos vitales y observaciones del equipo.' },
    notif_reporte_pdf:  { title:'Adjuntar PDF',             desc:'Cuando está activado, el reporte diario se envía con un documento PDF adjunto con el detalle completo del día del residente.' },
    notif_semanal:      { title:'Reporte semanal',          desc:'Envía cada semana un resumen consolidado: tendencias de signos vitales, cumplimiento de medicación, cuidados y eventos relevantes de los últimos 7 días.' },
    notif_notas_medico: { title:'Notas médicas',            desc:'Se activa cuando el médico registra o actualiza una nota en el expediente del residente.' },
    notif_visitas:      { title:'Visitas',                  desc:'Se activa al registrar una visita al residente: entrada, duración y salida del visitante.' },
    notif_stock:        { title:'Stock bajo',               desc:'Se activa cuando algún insumo médico o de cuidado del residente baja del umbral mínimo definido en inventario.' },
    notif_solo_criticas:{ title:'Solo críticas',            desc:'Cuando está activado, solo se envían las notificaciones de alertas críticas (emergencias, caídas, incidentes, signos fuera de rango), silenciando el resto.' },
    notif_quiet_enabled:{ title:'Horario silencioso',       desc:'Durante el rango de horas configurado no se enviarán notificaciones. Las alertas críticas pueden ignorar este silencio según la configuración de la institución.' },
    notif_wa:           { title:'WhatsApp',                 desc:'Habilita el envío de notificaciones por mensaje de WhatsApp al número telefónico registrado del contacto. Requiere que el número tenga WhatsApp activo.' },
    notif_email:        { title:'Email',                    desc:'Habilita el envío de notificaciones por correo electrónico a la dirección registrada del contacto.' },
};

function _resNotifSwitch(field, label) {
    return `<label class="cd-switch-row"><span class="cd-switch-label">${esc(label)}<button type="button" class="cd-notif-info-btn" data-notif-info="${field}" aria-label="Información sobre ${esc(label)}"><span class="material-symbols-outlined">info</span></button></span><span class="cd-switch"><input type="checkbox" data-res-notif-field="${field}"><span class="cd-switch-slider"></span></span></label>`;
}

function _resBindNotifManager(scope, resId, data = {}) {
    const manager = scope.querySelector(`[data-res-notif-manager="${resId}"]`);
    if (!manager) return;
    const positionPill = () => {
        const tabsEl = manager.querySelector('.cd-res-notif-mini-tabs');
        const pill = tabsEl?.querySelector(':scope > .cd-res-subtabs-pill');
        const active = tabsEl?.querySelector(':scope > .cd-res-subtab.is-active');
        if (!pill || !active || !active.offsetWidth) { if (pill) pill.style.opacity = '0'; return; }
        pill.style.opacity = '1';
        pill.style.width = active.offsetWidth + 'px';
        pill.style.transform = `translateX(${active.offsetLeft}px)`;
    };
    manager.querySelectorAll('[data-res-notif-tab]').forEach(tab => {
        tab.addEventListener('click', e => {
            e.stopPropagation();
            const key = tab.dataset.resNotifTab;
            manager.querySelectorAll('[data-res-notif-tab]').forEach(t => t.classList.toggle('is-active', t === tab));
            manager.querySelectorAll('[data-res-notif-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.resNotifPanel === key));
            positionPill();
            if (key === 'log') _resNotifLoadLog(manager, resId, false, data);
        });
    });
    manager.querySelectorAll('[data-res-notif-filter]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            if (btn.disabled) return;
            _resNotifApplyAudienceFilter(manager, btn.dataset.resNotifFilter || 'all');
        });
    });
    manager.querySelectorAll('[data-res-notif-member]').forEach(row => {
        const idx = parseInt(row.dataset.resNotifMember, 10);
        if (!Number.isNaN(idx)) _resNotifHydratePrefs(row, data, idx);
    });
    manager.querySelectorAll('[data-res-notif-member-head]').forEach(head => {
        head.addEventListener('click', e => {
            e.stopPropagation();
            const row = head.closest('[data-res-notif-member]');
            if (!row) return;
            const sourceKey = row.dataset.resNotifMember || '';
            const idx = parseInt(sourceKey, 10);
            const wasOpen = row.classList.contains('is-open');
            manager.querySelectorAll('[data-res-notif-member].is-open').forEach(openRow => {
                openRow.classList.remove('is-open');
                openRow.querySelector('[data-res-notif-member-head]')?.setAttribute('aria-expanded', 'false');
                const body = openRow.querySelector('.cd-notif-member-body');
                if (body) body.hidden = true;
            });
            if (wasOpen) {
                manager.dataset.activeContact = '';
                return;
            }
            manager.dataset.activeContact = sourceKey;
            row.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            const body = row.querySelector('.cd-notif-member-body');
            if (body) body.hidden = false;
            if (!Number.isNaN(idx)) _resNotifHydratePrefs(row, data, idx);
        });
    });
    manager.querySelectorAll('[data-notif-info]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const info = _resNotifInfoMap[btn.dataset.notifInfo];
            if (!info) return;
            openSidebar(info.title,
                `<div class="cd-sidebar-section"><p style="font-size:0.875rem;line-height:1.6;color:var(--cd-text)">${esc(info.desc)}</p></div>`,
                `<button class="cd-btn-submit cd-btn-secondary" id="cdNotifInfoClose">Cerrar</button>`
            );
            document.getElementById('cdNotifInfoClose')?.addEventListener('click', closeSidebar);
        });
    });
    manager.querySelectorAll('[data-res-notif-field]').forEach(input => {
        input.addEventListener('change', e => {
            e.stopPropagation();
            const row = input.closest('[data-res-notif-member]') || manager;
            if (input.dataset.resNotifField === 'notif_reporte' || input.dataset.resNotifField === 'notif_quiet_enabled') {
                _resNotifToggleOptions(row);
            }
            _resNotifQueueSavePrefs(row, resId, data);
        });
    });
    manager.querySelectorAll(`input[name="cdResNotifType${resId}"]`).forEach(radio => {
        radio.addEventListener('change', () => {
            const custom = manager.querySelector('[data-res-notif-custom]');
            if (custom) custom.style.display = radio.checked && radio.value === 'personalizado' ? '' : 'none';
        });
    });
    manager.querySelectorAll('[data-res-notif-send]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            _resNotifSendTest(manager, resId, data, btn.dataset.resNotifSend, btn);
        });
    });
    manager.querySelector('[data-res-notif-log-refresh]')?.addEventListener('click', e => {
        e.stopPropagation();
        _resNotifLoadLog(manager, resId, true, data);
    });
    requestAnimationFrame(positionPill);
}

function _resNotifParseReportHour(value) {
    if (!value) return '08:00';
    if (Array.isArray(value)) return value[0] || '08:00';
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (Array.isArray(parsed)) return parsed[0] || '08:00';
        } catch(e) {}
        const match = value.match(/\d{1,2}:\d{2}/);
        return match ? match[0].padStart(5, '0') : '08:00';
    }
    return '08:00';
}

function _resNotifHydratePrefs(manager, data, idx) {
    const c = data.familiares?.[idx] || {};
    manager.querySelectorAll('[data-res-notif-field]').forEach(input => {
        const field = input.dataset.resNotifField;
        if (input.type === 'checkbox') {
            const channelUnavailable = (field === 'notif_wa' && !String(c.telefono || '').trim())
                || (field === 'notif_email' && !String(c.email || '').trim());
            input.disabled = channelUnavailable;
            input.closest('.cd-switch-row')?.classList.toggle('is-disabled', channelUnavailable);
            input.checked = channelUnavailable ? false : (['notif_emergencia', 'notif_caida'].includes(field) && c[field] === undefined ? true : !!c[field]);
        } else if (field === 'notif_reporte_hora') {
            input.value = _resNotifParseReportHour(c[field]);
        } else if (field === 'notif_quiet_start') {
            input.value = c[field] || '22:00';
        } else if (field === 'notif_quiet_end') {
            input.value = c[field] || '07:00';
        } else {
            input.value = c[field] || '';
        }
    });
    _resNotifToggleOptions(manager);
}

function _resNotifToggleOptions(manager) {
    const reportOn = manager.querySelector('[data-res-notif-field="notif_reporte"]')?.checked;
    const quietOn = manager.querySelector('[data-res-notif-field="notif_quiet_enabled"]')?.checked;
    const report = manager.querySelector('[data-report-options]');
    const quiet = manager.querySelector('[data-quiet-options]');
    if (report) report.style.display = reportOn ? '' : 'none';
    if (quiet) quiet.style.display = quietOn ? '' : 'none';
}

async function _resNotifSavePrefs(manager, resId, data = {}) {
    const idx = parseInt(manager.dataset.activeContact || manager.dataset.resNotifMember, 10);
    if (Number.isNaN(idx) || !data.familiares?.[idx]) return;
    _resNotifSetAutosaveState(manager, 'saving');
    const contact = data.familiares[idx];
    manager.querySelectorAll('[data-res-notif-field]').forEach(input => {
        const field = input.dataset.resNotifField;
        if (input.type === 'checkbox') contact[field] = input.checked ? 1 : 0;
        else if (field === 'notif_reporte_hora') contact[field] = JSON.stringify([input.value || '08:00']);
        else contact[field] = input.value || '';
    });
    const contacts = (data.familiares || []).filter(f => (f.nombre || '').trim() || (f.email || '').trim() || (f.telefono || '').trim());
    const familyContacts = contacts.filter(f => !(f.__notifStaff || f.source === 'staff_notif'));
    const first = familyContacts[0] || {};
    const contactosJson = JSON.stringify(contacts.map(_resNotifPersistContact));
    try {
        await fetch(`${RES_API}?id=${resId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contacto_nombre: first.nombre || '',
                contacto_parentesco: first.parentesco || '',
                contacto_telefono: first.telefono || '',
                contacto_telefono2: first.telefono2 || '',
                contacto_email: first.email || '',
                contacto_direccion: first.direccion || '',
                contactos_json: contactosJson,
            })
        }).then(r => r.json()).then(j => { if (!j.success) throw new Error(j.message || 'Error'); });
        data.contactos_json = contactosJson;
        if (data.residente) data.residente.contactos_json = contactosJson;
        _resNotifRefreshMemberSummary(manager, contact);
        _resNotifRefreshSummaryCounts(resId, data);
        delete _resDataCache[resId];
        _resNotifSetAutosaveState(manager, 'saved');
        showToast?.('Preferencias de notificación guardadas', 'success');
    } catch(e) {
        showToast?.(e.message || 'Error al guardar preferencias', 'error');
        _resNotifSetAutosaveState(manager, 'error');
    }
}

function _resNotifQueueSavePrefs(manager, resId, data = {}) {
    if (!manager) return;
    clearTimeout(manager._resNotifSaveTimer);
    _resNotifSetAutosaveState(manager, 'pending');
    manager._resNotifSaveTimer = setTimeout(() => {
        _resNotifSavePrefs(manager, resId, data);
    }, 350);
}

function _resNotifSetAutosaveState(manager, state) {
    const status = manager?.querySelector?.('[data-res-notif-autosave]');
    if (!status) return;
    status.dataset.state = state;
    status.textContent = ({
        pending: 'Cambios pendientes...',
        saving: 'Guardando...',
        saved: 'Guardado',
        error: 'No se pudo guardar',
    })[state] || 'Los cambios se guardan automáticamente';
    if (state === 'saved') {
        clearTimeout(status._resNotifSavedTimer);
        status._resNotifSavedTimer = setTimeout(() => {
            if (status.dataset.state === 'saved') {
                status.dataset.state = '';
                status.textContent = 'Los cambios se guardan automáticamente';
            }
        }, 1600);
    }
}

function _resNotifRefreshMemberSummary(manager, contact) {
    const head = manager?.querySelector?.('[data-res-notif-member-head]');
    const sub = head?.querySelector?.('.cd-notif-member-sub');
    if (!sub) return;
    sub.textContent = _resNotifEnabledChannels(contact).join(' · ') || 'Sin canales';
}

function _resNotifRefreshSummaryCounts(resId, data = {}) {
    const sub = document.querySelector(`[data-subcard="${resId}"]`);
    const card = sub?.closest?.('.cd-res-mgmt-card');
    if (!card) return;
    const familyRows = _resRegisteredFamiliares(data);
    const notifRows = _resNotifContactRows(data);
    const notifData = _resBuildNotifData(data, notifRows);
    const audience = _resNotifAudience(notifData.familiares || [], notifData).filter(({ f }) => (f.nombre || '').trim() || (f.email || '').trim() || (f.telefono || '').trim());
    const counts = {
        familiares: familyRows.length,
        invitaciones: _resInvitationRows(data).length,
        notificaciones: _resNotifEnabledCount(audience.map(item => item.f)),
        cuidadores: data.cuidadores && Array.isArray(data.cuidadores.asignados) ? _resCuidadoresTabList(data.cuidadores).length : null,
        medicaciones: Array.isArray(data.prescripciones) ? data.prescripciones.length : 0,
        nota: data.nota_medico ? 1 : 0,
    };
    _resSetMgmtTabState(card, card.dataset.activeTab || 'notificaciones', counts, { canMove: !!card._resCanMove });
}

function _resNotifSubject(type) {
    const labels = {
        stock_bajo: 'Stock bajo', reporte_dia: 'Reporte del día', signos_vitales: 'Signos vitales',
        incidente: 'Incidente reportado', medicacion: 'Medicación administrada', personalizado: 'Notificación'
    };
    return labels[type] || 'Notificación';
}

function _resNotifBuildMessage(type, custom, data = {}) {
    const r = data.residente || {};
    const resName = [r.nombre, r.apellidos].filter(Boolean).join(' ') || 'el residente';
    if (type === 'personalizado') return custom || '';
    const msgs = {
        reporte_dia: `Reporte del día para ${resName}: los cuidados registrados están disponibles para consulta en GeriApp.`,
        signos_vitales: `Alerta de signos vitales para ${resName}. El equipo de cuidados está revisando la situación.`,
        incidente: `Se registró un incidente relacionado con ${resName}. El equipo ya está dando seguimiento.`,
        medicacion: `La medicación de ${resName} fue registrada en GeriApp. Puedes consultar el detalle en la plataforma.`,
    };
    return msgs[type] || `Actualización de GeriApp sobre ${resName}.`;
}

async function _resNotifSendTest(manager, resId, data = {}, channel, btn) {
    const type = manager.querySelector(`input[name="cdResNotifType${resId}"]:checked`)?.value || 'personalizado';
    const custom = manager.querySelector('[data-res-notif-custom]')?.value || '';
    const message = _resNotifBuildMessage(type, custom, data);
    if (!message) { showToast?.('Escribe un mensaje para enviar', 'warning'); return; }
    const checks = Array.from(manager.querySelectorAll('.cd-res-notif-recipients input[type="checkbox"]:checked'));
    const recipients = channel === 'email'
        ? checks.filter(c => c.dataset.email).map(c => ({ email: c.dataset.email, name: c.dataset.name || data.familiares?.[parseInt(c.value, 10)]?.nombre || '' }))
        : checks.filter(c => c.dataset.phone).map(c => c.dataset.phone);
    if (!recipients.length) { showToast?.(channel === 'email' ? 'Selecciona destinatarios con email' : 'Selecciona destinatarios con WhatsApp', 'warning'); return; }
    btnLoading?.(btn, 'Enviando');
    try {
        if (channel === 'email') {
            await api(API_URL, { method:'POST', body: JSON.stringify({ action:'send_notif_email', recipients, subject:_resNotifSubject(type), message, residente_id:resId, tipo:type }) });
        } else {
            for (const phone of recipients) {
                await api(API_URL, { method:'POST', body: JSON.stringify({ action:'send_notif_wa', phone, message:message.replace(/\*\*(.+?)\*\*/g, '*$1*'), residente_id:resId, tipo:type }) });
            }
        }
        showToast?.('Notificación enviada', 'success');
        _resNotifLoadLog(manager, resId, true, data);
    } catch(e) {
        showToast?.('Error al enviar notificación', 'error');
    } finally {
        btnReset?.(btn);
    }
}

async function _resNotifLoadLog(manager, resId, force = false, data = {}) {
    const list = manager.querySelector('[data-res-notif-log-list]');
    if (!list) return;
    if (list.dataset.loaded === '1' && !force) return;
    list.innerHTML = '<p class="cd-notif-empty">Cargando historial...</p>';
    try {
        const res = await api(`${API_URL}?notif_log=1&residente_id=${resId}`);
        const logs = Array.isArray(res) ? res : (res?.logs || []);
        list.dataset.loaded = '1';
        if (!logs.length) {
            list.innerHTML = '<p class="cd-notif-empty">Sin notificaciones enviadas</p>';
            return;
        }
        const visibleLogs = logs.slice(0, 30);
        const destNameMap = {};
        _resNotifAudience(data?.familiares || [], data).forEach(({ f }) => {
            const c = f || {};
            if (c.telefono) destNameMap[c.telefono] = c.nombre || '';
            if (c.email) destNameMap[c.email] = c.nombre || '';
        });
        list.innerHTML = visibleLogs.map((l, i) => {
            const fechaLocal = l.fecha ? new Date(l.fecha + 'Z').toLocaleString('es-MX', {timeZone: APP_TZ, day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '';
            const ok = l.estado === 'enviado';
            const destName = destNameMap[l.destinatario] || '';
            const destLabel = destName ? `${destName} (${l.destinatario || ''})` : (l.destinatario || 'Sin destinatario');
            return `<button type="button" class="cd-res-notif-log-entry ${ok ? 'is-ok' : 'is-error'}" data-res-notif-log-idx="${i}">
                <div><strong>${esc(l.canal || 'canal')}</strong><span>${esc(destLabel)}</span></div>
                <div><span>${esc(l.tipo || 'manual')}</span><span>${esc(fechaLocal)}</span><b>${ok ? 'Enviado' : 'Error'}</b></div>
            </button>`;
        }).join('');
        list.querySelectorAll('[data-res-notif-log-idx]').forEach(entry => {
            entry.addEventListener('click', () => {
                const idx = parseInt(entry.dataset.resNotifLogIdx, 10);
                const log = visibleLogs[idx];
                if (log) _resNotifOpenLogDrawer(log, destNameMap, () => _resNotifLoadLog(manager, resId, true, data));
            });
        });
    } catch(e) {
        list.innerHTML = '<p class="cd-notif-empty">Error al cargar historial</p>';
    }
}

function _resNotifTypeLabel(type) {
    const labels = { stock_bajo:'Stock bajo', stock_panales:'Stock bajo pañales', reporte_dia:'Reporte del día', reporte_dia_auto:'Reporte del día (IA)', personalizado:'Personalizado', manual:'Manual', signos_vitales:'Signos vitales', incidente:'Incidente', medicacion:'Medicación' };
    return labels[type] || (type?.startsWith?.('reporte_dia_auto') ? 'Reporte del día (IA)' : (type || 'Manual'));
}

function _resNotifOpenLogDrawer(log, destNameMap = {}, onDelete = null) {
    const chLabel = log.canal === 'whatsapp' ? 'WhatsApp' : (log.canal === 'email' ? 'Email' : (log.canal || 'Canal'));
    const fechaDetalle = log.fecha ? new Date(log.fecha + 'Z').toLocaleString('es-MX', {timeZone: APP_TZ, day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '';
    const detailName = destNameMap[log.destinatario] || '';
    const destDetail = detailName ? `${esc(detailName)} (${esc(log.destinatario || '')})` : esc(log.destinatario || 'Sin destinatario');
    const statusLabel = log.estado === 'enviado'
        ? '<span style="color:var(--cd-success);font-weight:600">Enviado</span>'
        : '<span style="color:var(--cd-danger);font-weight:600">Error</span>';
    let body = `<div class="cd-sidebar-detail-grid">
        <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Canal</span><span>${esc(chLabel)}</span></div>
        <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Destinatario</span><span>${destDetail}</span></div>
        <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Tipo</span><span>${esc(_resNotifTypeLabel(log.tipo))}</span></div>
        <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Fecha</span><span>${esc(fechaDetalle)}</span></div>
        <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Estado</span><span>${statusLabel}</span></div>
    </div>`;
    if (log.mensaje) {
        const msg = typeof _formatWaMessage === 'function' ? _formatWaMessage(log.mensaje) : esc(log.mensaje).replace(/\n/g, '<br>');
        body += `<div style="margin-top:12px"><label class="cd-sidebar-detail-label" style="display:block;margin-bottom:4px">Mensaje</label><div class="cd-notif-log-msg-preview">${msg}</div></div>`;
    }
    if (log.error_detalle) {
        body += `<div style="margin-top:8px"><label class="cd-sidebar-detail-label" style="display:block;margin-bottom:4px;color:var(--cd-danger)">Detalle del error</label><div class="cd-notif-log-msg-preview" style="border-color:var(--cd-danger)">${esc(log.error_detalle)}</div></div>`;
    }
    if (log.id) {
        body += `<div style="margin-top:16px;text-align:right"><button type="button" class="cd-btn-submit" id="cdResNotifLogDeleteBtn" style="background:var(--cd-danger);font-size:0.8rem;padding:6px 14px">Eliminar registro</button></div>`;
    }
    openSidebar?.('Detalle de notificación', body);
    $('#cdResNotifLogDeleteBtn')?.addEventListener('click', async () => {
        if (!confirm('¿Eliminar este registro de notificación?')) return;
        try {
            await api(`${API_URL}?notif_log_id=${log.id}`, {method:'DELETE'});
            showToast?.('Registro eliminado', 'success');
            closeSidebar?.();
            if (typeof onDelete === 'function') onDelete();
        } catch(e) { showToast?.('Error al eliminar', 'error'); }
    });
}

function _resOpenResidentNotif(resId, options = {}) {
    resId = parseInt(resId, 10) || 0;
    if (!resId) return;
    if (typeof showView === 'function') showView('viewResidentes', true, 'right');
    const openTab = async (attempts = 20) => {
        let card = document.querySelector(`.cd-res-mgmt-card[data-res-id="${resId}"]`);
        if (!card && typeof loadResidentes === 'function') {
            try { await loadResidentes(); } catch(e) {}
            card = document.querySelector(`.cd-res-mgmt-card[data-res-id="${resId}"]`);
        }
        if (!card) {
            if (attempts > 0) setTimeout(() => openTab(attempts - 1), 120);
            return;
        }
        if (card.dataset.expanded === '1' && card.dataset.activeTab === 'notificaciones') {
            const sub = card.querySelector('.cd-res-mgmt-subcard');
            if (sub && (!sub.dataset.loaded || !_resExpandCache[resId])) await _resLoadExpand(resId, sub, 'notificaciones');
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        await _resOpenCardTab(card, 'notificaciones');
    };
    setTimeout(() => openTab(), 80);
    if (options.addContact) {
        setTimeout(() => _resOpenFamiliarInvite(resId, {}, {}, 'notificaciones'), 500);
    }
}

// Open Ficha → Notificaciones for a given resident and try to expand the matching family contact
function _resOpenFamiliarNotif(resId, fam) {
    _resOpenResidentNotif(resId);
    // Poll for the contact list and click the matching head
    const targetMail = (fam.email || '').trim().toLowerCase();
    const targetName = (fam.nombre || '').trim().toLowerCase();
    const tryOpen = (attempts) => {
        const list = document.querySelector(`[data-res-notif-manager="${resId}"] [data-res-notif-members]`);
        if (!list) {
            if (attempts > 0) setTimeout(() => tryOpen(attempts - 1), 250);
            return;
        }
        const rows = list.querySelectorAll('.cd-notif-member:not(.cd-notif-member--add)');
        if (!rows.length) {
            if (attempts > 0) setTimeout(() => tryOpen(attempts - 1), 250);
            return;
        }
        let match = null;
        rows.forEach(r => {
            if (match) return;
            const nm = (r.querySelector('.cd-notif-member-name')?.textContent || '').trim().toLowerCase();
            const sub = (r.querySelector('.cd-notif-member-sub')?.textContent || '').toLowerCase();
            if (targetMail && (nm.includes(targetMail) || sub.includes(targetMail))) match = r;
            else if (targetName && nm.startsWith(targetName.split(' ')[0])) match = r;
        });
        if (!match) match = rows[0];
        const head = match.querySelector('.cd-notif-member-head');
        if (head && !match.classList.contains('is-open')) head.click();
        match.scrollIntoView({behavior: 'smooth', block: 'center'});
    };
    setTimeout(() => tryOpen(8), 350);
}

function _resRenderMedicaciones(list, medRegs, todayStr, resId) {
    if (!list || !list.length) {
        return `<div class="cd-res-subpanel-empty">No hay medicaciones registradas.</div>`;
    }
    // Build per-medication maps from today's registros
    const adminMap = {};
    const skipMap  = {};
    (medRegs || []).forEach(reg => {
        const d = reg?.datos || {};
        const hc = d.horarios_cubiertos || {};
        Object.keys(hc).forEach(name => {
            const k = name.trim().toLowerCase();
            if (!adminMap[k]) adminMap[k] = new Set();
            (Array.isArray(hc[name]) ? hc[name] : []).forEach(h => adminMap[k].add(_resNormHour(h)));
        });
        const noAdm = Array.isArray(d.medicamentos_no_administrados) ? d.medicamentos_no_administrados : [];
        noAdm.forEach(it => {
            const k = (it.nombre || '').trim().toLowerCase();
            if (!k) return;
            if (!skipMap[k]) skipMap[k] = new Set();
            (Array.isArray(it.horarios) ? it.horarios : []).forEach(h => skipMap[k].add(_resNormHour(h)));
        });
    });
    const today = todayStr ? new Date(todayStr + 'T00:00:00') : new Date();
    today.setHours(0,0,0,0);
    const now = new Date();
    const nowMin = now.getHours() * 60 + now.getMinutes();
    // Icons (admin / overdue / pending / done / off / expired)
    const ICONS = {
        done:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        partial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6 v6 l4 2"/></svg>',
        overdue: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        soon:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        ok:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
        off:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
        expired: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    };
    // Compute per-med state
    const items = list.map(m => {
        const inicio = m.inicio ? new Date(m.inicio + 'T00:00:00') : null;
        const fin    = m.fin ? new Date(m.fin + 'T00:00:00') : null;
        const isInactive = (m.activo === 0 || m.activo === '0' || m.activo === false);
        const isExpired = !isInactive && fin && fin < today;
        const isFuture  = !isInactive && !isExpired && inicio && inicio > today;
        const isVigente = !isInactive && !isExpired && !isFuture;
        // Parse horarios array
        let horarios = [];
        if (Array.isArray(m.horarios)) horarios = m.horarios.map(_resNormHour).filter(Boolean);
        else if (typeof m.horarios === 'string' && m.horarios.trim()) {
            try { const arr = JSON.parse(m.horarios); if (Array.isArray(arr)) horarios = arr.map(_resNormHour).filter(Boolean); } catch(e){}
            if (!horarios.length) {
                const matches = m.horarios.match(/\d{1,2}:\d{2}/g);
                if (matches) horarios = matches.map(_resNormHour);
            }
        }
        // Sort horarios chronologically
        horarios.sort();
        const k = (m.nombre || '').trim().toLowerCase();
        const adminSet = adminMap[k] || new Set();
        const skipSet  = skipMap[k]  || new Set();
        let admin = 0, overdue = 0, pending = 0, skipped = 0;
        // Per-horario chip state map
        const chipMap = {};
        horarios.forEach(h => {
            let st;
            if (adminSet.has(h)) { st = 'admin'; admin++; }
            else if (skipSet.has(h)) { st = 'skip'; skipped++; }
            else if (!isVigente) { st = 'off'; }
            else {
                const [hh, mm] = h.split(':').map(n => parseInt(n));
                if (hh*60+mm <= nowMin) { st = 'overdue'; overdue++; }
                else                    { st = 'pending'; pending++; }
            }
            chipMap[h] = st;
        });
        const total = horarios.length;
        // Decide row state and status text (still used for icon color and a11y)
        let state, statusMain;
        if (isInactive)      { state = 'off';     statusMain = 'Inactiva';   }
        else if (isExpired)  { state = 'expired'; statusMain = 'Vencida';    }
        else if (isFuture)   { state = 'soon';    statusMain = 'Programada'; }
        else if (overdue > 0) { state = 'overdue'; statusMain = `${overdue} vencida${overdue>1?'s':''}`; }
        else if (total === 0) { state = 'ok';      statusMain = 'Vigente';   }
        else if (admin === total) { state = 'done';    statusMain = `${admin}/${total}`; }
        else if (admin > 0)   { state = 'partial'; statusMain = `${admin}/${total}`; }
        else                  { state = 'soon';    statusMain = `0/${total}`; }
        return { m, state, statusMain, total, admin, overdue, pending, skipped, horarios, chipMap, isVigente };
    });
    // Sort: vigentes con vencidas primero, luego pendientes, luego completadas, luego inactivas/vencidas al final
    const stateOrder = { overdue: 0, soon: 1, partial: 2, done: 3, ok: 4, expired: 5, off: 6 };
    items.sort((a, b) => {
        const sa = stateOrder[a.state] ?? 9;
        const sb = stateOrder[b.state] ?? 9;
        if (sa !== sb) return sa - sb;
        return (b.overdue - a.overdue) || (b.total - a.total);
    });
    const renderRow = (it) => {
        const m = it.m;
        const dosis = [m.dosis, m.via].filter(Boolean).join(' · ');
        // Tooltip with full breakdown of horarios
        const tipParts = [];
        if (it.total) tipParts.push(`Horarios: ${it.horarios.join(', ')}`);
        if (it.admin)   tipParts.push(`Administradas: ${it.admin}`);
        if (it.overdue) tipParts.push(`Vencidas: ${it.overdue}`);
        if (it.pending) tipParts.push(`Pendientes: ${it.pending}`);
        if (it.skipped) tipParts.push(`Omitidas: ${it.skipped}`);
        if (m.indicaciones || m.indicacion) tipParts.push(`Indicaciones: ${m.indicaciones || m.indicacion}`);
        const tip = tipParts.join(' · ');
        // Chips: un chip por horario coloreado por estado; si no hay horarios, mostrar estado general
        const chipsHtml = it.horarios.length
            ? it.horarios.map(h => `<span class="cd-res-med-chip cd-res-med-chip--${it.chipMap[h]}" title="${esc(h)} · ${esc(it.chipMap[h])}">${esc(h)}</span>`).join('')
            : `<span class="cd-res-med-chip cd-res-med-chip--${it.state==='off'||it.state==='expired'?'off':it.state==='done'?'admin':it.state==='overdue'?'overdue':'pending'}">${esc(it.statusMain)}</span>`;
        return `<tr class="cd-res-med-row cd-res-med-row--${it.state}" title="${esc(tip)}">
            <td class="cd-res-med-tbl-icon">${ICONS[it.state] || ICONS.ok}</td>
            <td class="cd-res-med-tbl-name">
                <span class="cd-res-med-name">${esc(m.nombre || 'Medicamento')}</span>
                ${dosis ? `<span class="cd-res-med-dosis">${esc(dosis)}</span>` : ''}
            </td>
            <td class="cd-res-med-tbl-status"><div class="cd-res-med-chips">${chipsHtml}</div></td>
        </tr>`;
    };
    const safeResId = parseInt(resId) || 0;
    return `<div class="cd-res-med-list">
        <div class="cd-res-med-header">
            <h4 class="cd-res-med-header-title">Medicaciones</h4>
            <button type="button" class="cd-res-med-goto cd-btn-add" onclick="event.stopPropagation(); _resGotoMedicacion(${safeResId})" title="Ir a la sección de Medicación">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
                <span>Ir a Medicación</span>
            </button>
        </div>
        <table class="cd-res-med-tbl"><tbody>${items.map(renderRow).join('')}</tbody></table>
    </div>`;
}

// Navega a la vista Dashboard, selecciona el residente y abre la categoría Medicación
// IMPORTANTE: showView debe ir ANTES del change event; reloadCurrentView() hace `break`
// si _currentView === 'viewResidentes', así que el change no recarga el dashboard.
window._resGotoMedicacion = function(resId) {
    if (!resId) return;
    const sel = document.getElementById('cdPatientName');
    const prevId = parseInt(sel?.value) || 0;
    // 1. Cambiar vista primero para que reloadCurrentView dispare loadDashboard
    if (typeof showView === 'function') showView('viewDashboard');
    // 2. Seleccionar residente; si cambia, change event recarga; si es el mismo, refrescar manual
    if (sel) {
        sel.value = resId;
        if (resId !== prevId) {
            sel.dispatchEvent(new Event('change'));
        } else if (typeof loadDashboard === 'function') {
            loadDashboard();
        }
    }
    // 3. Esperar a que el dashboard renderice los cd-cat-btn y disparar Medicación
    let intentos = 0;
    const tryClick = () => {
        const btn = document.querySelector('.cd-cat-btn[data-cat="medicacion"]');
        if (btn) { btn.click(); return; }
        if (++intentos < 20) setTimeout(tryClick, 80);
    };
    setTimeout(tryClick, 200);
}

// Normalize hour to HH:MM (zero-pad)
function _resNormHour(h) {
    if (!h) return '';
    const m = String(h).match(/(\d{1,2}):(\d{2})/);
    if (!m) return '';
    return String(parseInt(m[1])).padStart(2,'0') + ':' + m[2];
}

function _resRenderCuidadores(payload, resId) {
    payload = payload || { asignados: [], disponibles: [], can_edit: false };
    const list   = _resCuidadoresTabList(payload);
    const pool   = Array.isArray(payload.disponibles) ? payload.disponibles : [];
    const canEdit = !!payload.can_edit;

    const _ic = (path) => `<svg class="cd-res-fam-meta-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
    const icMail = _ic('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>');
    const icTel  = _ic('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>');

    const editBtn = canEdit
        ? `<button type="button" class="cd-btn-submit cd-btn-secondary" data-cuid-edit="${resId}" style="font-size:0.75rem;padding:6px 12px">Editar asignaciones</button>`
        : '';
    const search = _resSubcardSearchHtml('cuidadores', 'Buscar cuidadores');

    if (!list.length) {
        return `${search}<div class="cd-res-subpanel-empty" style="display:flex;flex-direction:column;align-items:center;gap:10px">
            <span>No hay cuidadores disponibles para este residente.</span>
            ${editBtn}
        </div>`;
    }

    const rolBadge = (rol) => {
        const map = {
            admin:     { l:'Admin',     bg:'rgba(99,102,241,.14)',  fg:'#6366f1' },
            medico:    { l:'Médico',    bg:'rgba(34,197,94,.14)',   fg:'#16a34a' },
            enfermero: { l:'Cuidador', bg:'rgba(23,131,145,.14)',  fg:'#178391' },
            cuidador:  { l:'Cuidador', bg:'rgba(23,131,145,.14)',  fg:'#178391' },
        };
        const v = map[rol] || { l: rol || '—', bg:'rgba(107,114,128,.14)', fg:'#6b7280' };
        return `<span class="cd-res-fam-rel" style="color:${v.fg};background:${v.bg};border-color:${v.fg}40">${esc(v.l)}</span>`;
    };
    const inviteBadge = (c) => c.pending_invite
        ? `<span class="cd-res-fam-status cd-res-fam-status--invite" title="Invitación ${esc(c.estado || 'pendiente')}">${esc((c.estado || 'pendiente') === 'expirada' ? 'Expirada' : 'Invitado')}</span>`
        : (c.__cuidAvailable ? '<span class="cd-res-fam-status cd-res-fam-status--invite" title="Disponible para asignar">Disponible</span>' : '');

    const html = `<div style="display:flex;justify-content:flex-end;margin-bottom:8px">${editBtn}</div>
    ${search}
    <div class="cd-res-fam-list">${list.map((c, idx) => {
        const displayName = c.pending_invite ? (c.nombre && c.nombre !== 'Invitación pendiente' ? c.nombre : (c.email || 'Invitación pendiente')) : (c.nombre || 'Sin nombre');
        const initials = (displayName||'?').trim().split(/\s+/).slice(0,2).map(s=>s[0]||'').join('').toUpperCase();
        const tel = (c.telefono||'').trim();
        const mail = (c.email||'').trim();
        const actions = [];
        if (tel)  actions.push(_resFamMenuItem({ href:`tel:${esc(tel)}`, icon:'call', label:'Llamar', title:'Llamar' }));
        if (mail) actions.push(_resFamMenuItem({ href:`mailto:${esc(mail)}`, icon:'mail', label:'Correo', title:'Enviar correo' }));
        if (canEdit && c.pending_invite && c.invitacion_id) {
            if (_resCuidInviteHasDestination(c)) actions.push(_resFamMenuItem({ attrs:`data-cuid-invite-resend="${c.invitacion_id}"`, cls:'cd-res-fam-action--resend', icon:'refresh', label:'Reenviar invitación', title:'Reenviar invitación' }));
            actions.push(_resFamMenuItem({ attrs:`data-cuid-invite-delete="${c.invitacion_id}"`, cls:'cd-res-fam-action--delete', icon:'delete_forever', label:'Eliminar invitación', title:'Eliminar invitación' }));
        } else if (canEdit) {
            actions.push(_resFamMenuItem({ attrs:`data-cuid-edit="${resId}"`, cls:'cd-res-fam-action--manage', icon:'manage_accounts', label:'Residentes asociados', title:'Editar asignaciones' }));
        }
        const searchText = [displayName, c.rol, c.email, c.telefono].filter(Boolean).join(' ');
        return `<div class="cd-res-fam-item cd-res-fam-item--actionable" data-cuid-idx="${idx}" data-res-search-text="${esc(searchText)}" role="button" tabindex="0" title="Ver detalles y acciones">
            <div class="cd-res-fam-avatar">${esc(initials || '?')}</div>
            <div class="cd-res-fam-info">
                <div class="cd-res-fam-head">
                    <span class="cd-res-fam-name">${esc(displayName)}</span>
                    ${inviteBadge(c)}
                </div>
                <div class="cd-res-fam-meta">
                    <div class="cd-res-fam-meta-row">${icMail}<span class="cd-res-fam-meta-val">${mail ? `<a href="mailto:${esc(mail)}" onclick="event.stopPropagation()">${esc(mail)}</a>` : '<span class="cd-res-fam-meta-empty">Sin correo</span>'}</span></div>
                    <div class="cd-res-fam-meta-row">${icTel}<span class="cd-res-fam-meta-val">${tel ? `<a href="tel:${esc(tel)}" onclick="event.stopPropagation()">${esc(tel)}</a>` : '<span class="cd-res-fam-meta-empty">Sin teléfono</span>'}</span></div>
                </div>
            </div>
            <div class="cd-res-fam-side">
                ${_resFamMenu(actions)}
                ${rolBadge(c.rol)}
            </div>
        </div>`;
    }).join('')}</div><div class="cd-res-subpanel-empty cd-res-search-empty" data-res-search-empty hidden>No hay cuidadores que coincidan con la búsqueda.</div>`;
    return html;
}

async function _resOpenCuidadoresEdit(resId) {
    let payload;
    try {
        payload = await api(`${BASE}/api/residentes.php?id=${resId}&cuidadores=1`);
    } catch(e) { showToast?.('Error al cargar cuidadores', 'error'); return; }
    const pool = payload?.disponibles || [];
    const assigned = new Set((payload?.asignados || []).map(a => +a.id));
    if (!pool.length) { showToast?.('No hay usuarios disponibles para asignar.', 'warning'); return; }

    const rows = pool.map(u => {
        const checked = assigned.has(+u.id) ? 'checked' : '';
        const subtitle = [_resRoleLabel(u.rol), u.email].filter(Boolean).join(' · ');
        return `<label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);cursor:pointer">
            <input type="checkbox" value="${u.id}" ${checked} style="margin:0">
            <div style="flex:1;min-width:0">
                <div style="font-size:0.8125rem;font-weight:600;color:var(--cd-text)">${esc(u.nombre || '—')}</div>
                <div style="font-size:0.7rem;color:var(--cd-text-muted)">${esc(subtitle)}</div>
            </div>
        </label>`;
    }).join('');

    const ovId = 'cuidEditOverlay';
    let ov = document.getElementById(ovId);
    if (ov) ov.remove();
    ov = document.createElement('div');
    ov.id = ovId;
    ov.className = 'cd-modal-overlay show';
    ov.innerHTML = `<div class="cd-modal" role="dialog" aria-modal="true" style="max-width:520px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <h2 style="margin:0;font-size:1rem">Cuidadores asignados</h2>
            <button type="button" data-cuid-close style="background:none;border:0;font-size:1.5rem;line-height:1;color:var(--cd-text-muted);cursor:pointer">&times;</button>
        </div>
        <p style="font-size:0.75rem;color:var(--cd-text-muted);margin:0 0 12px">Marca los usuarios que tendrán acceso a este residente.</p>
        <div id="cuidEditList" style="display:flex;flex-direction:column;gap:6px;max-height:50vh;overflow-y:auto">${rows}</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
            <button type="button" class="cd-btn-submit cd-btn-secondary" data-cuid-close>Cancelar</button>
            <button type="button" class="cd-btn-submit" data-cuid-save="${resId}">Guardar</button>
        </div>
    </div>`;
    document.body.appendChild(ov);
    ov.addEventListener('click', e => {
        if (e.target === ov || e.target.closest('[data-cuid-close]')) ov.remove();
    });
    ov.querySelector('[data-cuid-save]')?.addEventListener('click', async () => {
        const ids = Array.from(ov.querySelectorAll('input[type="checkbox"]:checked')).map(cb => parseInt(cb.value, 10));
        const _btn = ov.querySelector('[data-cuid-save]');
        if (typeof btnLoading === 'function') btnLoading(_btn);
        try {
            await api(`${BASE}/api/residentes.php?action=sync_cuidadores&id=${resId}`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cuidador_ids: ids }),
            });
            showToast?.('Cuidadores actualizados', 'success');
            ov.remove();
            // Refresh subcard
            delete _resExpandCache[resId];
            const sub = document.querySelector(`[data-subcard="${resId}"]`);
            if (sub) { sub.dataset.loaded = ''; await _resLoadExpand(resId, sub, 'notificaciones'); }
        } catch(err) {
            showToast?.(err?.message || 'Error', 'error');
            if (typeof btnReset === 'function') btnReset(_btn);
        }
    });
}

// Delegated click for cuidadores edit button
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-cuid-edit]');
    if (!btn) return;
    e.stopPropagation();
    const rid = parseInt(btn.dataset.cuidEdit, 10);
    if (rid) _resOpenCuidadoresEdit(rid);
});

// Delegated click for "mover institución"
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-res-move]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const rid = parseInt(btn.dataset.resMove, 10);
    if (rid) _resOpenMoveModal(rid);
});

async function _resOpenMoveModal(resId) {
    let payload;
    try {
        payload = await api(`${BASE}/api/residentes.php?id=${resId}&move_targets=1`);
    } catch (e) {
        showToast?.('Error al cargar instituciones destino', 'error');
        return;
    }
    if (!payload?.can_move) { showToast?.('No tienes permisos para mover residentes.', 'warning'); return; }
    const targets = payload.targets || [];
    if (!targets.length) {
        showToast?.('No tienes otras instituciones donde puedas recibir este residente.', 'warning');
        return;
    }

    const opts = targets.map(t => {
        const disabled = t.can_receive === false;
        return `<label style="display:flex;align-items:flex-start;gap:8px;padding:10px;border:1px solid var(--cd-border);border-radius:8px;background:var(--cd-bg);cursor:${disabled?'not-allowed':'pointer'};${disabled?'opacity:.55':''}">
            <input type="radio" name="resMoveDest" value="${t.id}" ${disabled?'disabled':''} style="margin-top:3px">
            <div style="flex:1;min-width:0">
                <div style="font-weight:600">${esc(t.nombre)} <span style="font-size:11px;color:var(--cd-text-muted);text-transform:uppercase">${esc(t.estado)}</span></div>
                ${disabled ? `<div style="font-size:11px;color:#b45309;margin-top:2px">Esta institución no está disponible para recibir este residente.</div>` : ''}
            </div>
        </label>`;
    }).join('');

    if (typeof openSidebar !== 'function') {
        showToast?.('Sidebar no disponible para mover residente.', 'error');
        return;
    }

    openSidebar(
        'Mover residente',
        `<div style="display:flex;flex-direction:column;gap:12px">
            <p style="margin:0;font-size:13px;color:var(--cd-text-muted)">Selecciona la institución destino. Los cuidadores y familiares vinculados se conservan solo si también tienen acceso a la institución destino.</p>
            <div style="display:flex;flex-direction:column;gap:8px">${opts}</div>
        </div>`,
        `<button type="button" class="cd-btn-submit cd-btn-secondary" id="resMoveCancel">Cancelar</button>
         <button type="button" class="cd-btn-submit" id="resMoveSave">Mover</button>`
    );

    document.getElementById('resMoveCancel')?.addEventListener('click', closeSidebar);
    document.getElementById('resMoveSave').addEventListener('click', async () => {
        const sel = document.querySelector('#cdSidebarBody input[name="resMoveDest"]:checked');
        if (!sel) { showToast?.('Selecciona una institución destino.', 'warning'); return; }
        const destId = parseInt(sel.value, 10);
        const tName  = (targets.find(t => t.id === destId) || {}).nombre || '';
        const ok = await (window.cdConfirm
            ? cdConfirm({
                title: 'Confirmar traslado',
                message: `¿Mover este residente a "${tName}"? Esta acción se registrará en el log de auditoría.`,
                okText: 'Sí, mover',
                cancelText: 'Cancelar'
            })
            : Promise.resolve(confirm(`¿Mover este residente a "${tName}"?`)));
        if (!ok) return;
        const btn = document.getElementById('resMoveSave');
        if (typeof btnLoading === 'function') btnLoading(btn, 'Moviendo…');
        try {
            await api(`${BASE}/api/residentes.php?id=${resId}&action=move`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ institucion_destino_id: destId })
            });
            closeSidebar();
            showToast?.('Residente movido. Cambia a la institución destino para verlo.', 'success');
            // Refrescar la lista actual: ya no debería aparecer aquí.
            delete _resExpandCache[resId];
            if (typeof _resReload === 'function') _resReload();
            else if (typeof loadResidentes === 'function') loadResidentes();
        } catch (e) {
            if (typeof btnReset === 'function') btnReset(btn);
            showToast?.('Error al mover: ' + (e.message || e), 'error');
        }
    });
}

function _resRenderNotaMedica(nota) {
    if (!nota) {
        return `<div class="cd-res-subpanel-empty">No hay nota médica vigente.</div>`;
    }
    // Reuse cd-nm.js renderer for visual parity with the Expediente sidebar SOAP card.
    const parsed = (typeof window.nmParseContenido === 'function')
        ? window.nmParseContenido(nota.contenido || nota.descripcion || '')
        : null;
    const dateObj = nota.creado_at ? new Date(String(nota.creado_at).replace(' ', 'T')) : null;
    const dateStr = dateObj ? dateObj.toLocaleDateString('es-MX', { day:'numeric', month:'short', year:'numeric' }) : '';
    const timeStr = dateObj ? dateObj.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' }) : '';
    const updStr  = (nota.updated_at && nota.updated_at !== nota.creado_at) ? ` <span class="cd-nm-card-edited">· editada</span>` : '';
    const autor = nota.medico_nombre || nota.autor_nombre || nota.usuario_nombre || 'Médico';
    const sectionsHtml = (parsed && typeof window.nmRenderSections === 'function')
        ? window.nmRenderSections(parsed)
        : `<div class="cd-nm-section"><div class="cd-nm-section-content"><div class="cd-nm-section-body">${esc(nota.contenido || nota.descripcion || '—')}</div></div></div>`;
    // Mover sección de Signos Vitales al final (UX: primero SOAP / Dx, luego vitals)
    let finalSectionsHtml = sectionsHtml;
    try {
        const tmp = document.createElement('div');
        tmp.innerHTML = sectionsHtml;
        const svLabel = (typeof t === 'function') ? t('nm_sv_title') : 'Signos vitales';
        const target = svLabel.trim().toLowerCase();
        let svNode = null;
        tmp.querySelectorAll('.cd-nm-section').forEach(s => {
            if (svNode) return;
            const lbl = s.querySelector('.cd-nm-section-label');
            if (lbl && lbl.textContent.trim().toLowerCase() === target) svNode = s;
        });
        if (svNode) {
            tmp.appendChild(svNode); // re-añadir al final
            finalSectionsHtml = tmp.innerHTML;
        }
    } catch (e) { /* fallback al orden original */ }
    return `<div class="cd-nm-card cd-res-nm-card">
        <div class="cd-nm-card-header">
            <div class="cd-nm-card-doctor">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>${esc(autor)}</span>
            </div>
            <span class="cd-nm-card-date">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                ${dateStr}${timeStr ? ' · ' + timeStr : ''}${updStr}
            </span>
        </div>
        ${finalSectionsHtml}
    </div>`;
}

function _resCritScore(r) {
    const b = r?.badges || {};
    let s = 0;
    if (b.alertas_medico_pendientes > 0) s += 100 + (b.alertas_medico_pendientes * 5);
    if (b.medicacion_pendiente > 0) s += 80 + (b.medicacion_pendiente * 3);
    if (b.signos_fuera_rango > 0) s += 70 + (b.signos_fuera_rango * 4);
    if (b.sueno_pendiente) s += 35;
    if (typeof b.heces_horas === 'number' && b.heces_horas >= 12) s += Math.min(24, b.heces_horas - 11);
    if (b.inventario_low_stock > 0) s += Math.min(20, b.inventario_low_stock * 2);
    return s;
}

function openResCareSidebar(r) {
    const fullName = (r.nombre || '') + ' ' + (r.apellidos || '');
    const b = r.badges || {};
    const counts = r.counts_hoy || {};
    const _sbIc = (path) => `<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.7">${path}</svg>`;
    const rows = [
        ['<img src="assets/icons/moon-zzz.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">', 'Sueño pendiente', b.sueno_pendiente ? 'Sí' : 'No'],
        [_sbIc('<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'), 'Signos fuera de rango', String(b.signos_fuera_rango || 0)],
        ['<img src="assets/icons/pill.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">', 'Medicaciones pendientes', String(b.medicacion_pendiente || 0)],
        [_sbIc('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'), 'Alertas médicas pendientes', String(b.alertas_medico_pendientes || 0)],
        [_sbIc('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'), 'Horas desde evacuación', (typeof b.heces_horas === 'number') ? `${b.heces_horas}h` : '—'],
        [_sbIc('<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'), 'Items con stock bajo', String(b.inventario_low_stock || 0)],
    ];
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Resumen de estado</div>
        <table class="cd-sb-vitals-table"><tbody>
            ${rows.map(([ic,k,v]) => `<tr><th style="display:flex;align-items:center;gap:5px">${ic}${esc(k)}</th><td>${esc(v)}</td></tr>`).join('')}
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Actividad de cuidados (hoy)</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/moon-zzz.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Sueño</th><td>${counts.sueno || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/food.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Alimentación</th><td>${counts.alimentacion || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/pill.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Medicación</th><td>${counts.medicacion || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/handwash.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Higiene</th><td>${counts.higiene || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/terapia.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Terapia</th><td>${counts.terapia || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/walk.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Movilidad</th><td>${counts.movilidad || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/toilet.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Eliminación</th><td>${counts.eliminacion || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px"><img src="assets/icons/head-ia.png" width="13" height="13" style="flex-shrink:0;opacity:.7;object-fit:contain" alt="">Comportamiento</th><td>${counts.comportamiento || 0}</td></tr>
            <tr><th style="display:flex;align-items:center;gap:5px">${_sbIc('<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>')}Signos vitales</th><td>${counts.signos_vitales || 0}</td></tr>
        </tbody></table>
    </div>`;
    const actions = `<div class="cd-sidebar-row">
        <button class="cd-btn-submit" id="cdResCareGoDash">Ver en Inicio</button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdResCareClose">Cerrar</button>
    </div>`;
    openSidebar(`Estado de cuidados — ${esc(fullName)}`, body, actions);
    $('#cdResCareClose')?.addEventListener('click', closeSidebar);
    $('#cdResCareGoDash')?.addEventListener('click', () => {
        const sel = $('#cdPatientName');
        if (sel) {
            sel.value = r.id;
            sel.dispatchEvent(new Event('change'));
        }
        closeSidebar();
        showView('viewDashboard');
        _resScrollCareTop();
    });
}

let _resMgDiagnosticos = [];

function _resMgDxCatalog() {
    return (typeof CIE10 !== 'undefined' && Array.isArray(CIE10)) ? CIE10 : [];
}

function _resMgRenderDxTags() {
    const tags = $('#cdResMgDxTags');
    if (!tags) return;
    if (!_resMgDiagnosticos.length) { tags.innerHTML = ''; return; }
    tags.innerHTML = _resMgDiagnosticos.map((d, i) =>
        `<span class="cd-nm-dx-tag"><strong>${esc(d.codigo)}</strong> ${esc(d.descripcion)} <button type="button" class="cd-nm-dx-tag-x" data-res-mg-dx-remove="${i}" aria-label="Quitar ${esc(d.codigo)}">&times;</button></span>`
    ).join('');
    tags.querySelectorAll('[data-res-mg-dx-remove]').forEach(btn => {
        btn.addEventListener('click', () => {
            _resMgDiagnosticos.splice(parseInt(btn.dataset.resMgDxRemove, 10), 1);
            _resMgRenderDxTags();
        });
    });
}

function _resMgDiagnosisValue() {
    return _resMgDiagnosticos.map(d => `${d.codigo} - ${d.descripcion}`).join('; ');
}

function _resMgInitDxSelector() {
    const search = $('#cdResMgDxSearch');
    const drop = $('#cdResMgDxDropdown');
    if (!search || !drop || search.dataset.resMgDxInit === '1') return;
    search.dataset.resMgDxInit = '1';
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        if (q.length < 2) { drop.innerHTML = ''; drop.classList.remove('show'); return; }
        const matches = _resMgDxCatalog().filter(e =>
            String(e.c || '').toLowerCase().includes(q) || String(e.d || '').toLowerCase().includes(q)
        ).slice(0, 12);
        if (!matches.length) {
            drop.innerHTML = '<div class="cd-nm-dx-item cd-nm-dx-empty">Sin resultados</div>';
            drop.classList.add('show');
            return;
        }
        drop.innerHTML = matches.map(e => `<div class="cd-nm-dx-item" data-code="${esc(e.c)}" data-desc="${esc(e.d)}"><strong>${esc(e.c)}</strong> ${esc(e.d)}</div>`).join('');
        drop.classList.add('show');
    });
    drop.addEventListener('click', e => {
        const item = e.target.closest('.cd-nm-dx-item[data-code]');
        if (!item) return;
        const code = item.dataset.code;
        const desc = item.dataset.desc;
        if (!_resMgDiagnosticos.some(d => d.codigo === code)) {
            _resMgDiagnosticos.push({ codigo: code, descripcion: desc });
            _resMgRenderDxTags();
        }
        search.value = '';
        drop.innerHTML = '';
        drop.classList.remove('show');
    });
    search.addEventListener('blur', () => setTimeout(() => drop.classList.remove('show'), 200));
    search.addEventListener('focus', () => { if (search.value.trim().length >= 2) search.dispatchEvent(new Event('input')); });
}

function openResidenteMgmtSidebar(r) {
    const isNew = !r;
    const data = r || {};
    const hasAllergies = !!(data.alergias && data.alergias.trim());
    if (isNew) _resMgDiagnosticos = [];
    const roomAdmissionFields = isNew
        ? `<div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">${t('res_room')}</label><input class="cd-input" id="cdResMgHabitacion" value="${esc(data.habitacion||'')}"></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">${t('res_admission_date')}</label><input type="text" class="cd-input cd-app-date-input" id="cdResMgFechaIngreso" value="${esc(fmtDate(data.fecha_ingreso||nowInTz().date))}" data-iso="${esc(data.fecha_ingreso||nowInTz().date)}" placeholder="${appDatePlaceholder()}" inputmode="numeric"></div></div>`
        : `<div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">Estado civil</label>
            <select class="cd-input cd-select-native" id="cdResMgEstadoCivil"><option value="">—</option><option value="Soltero/a" ${data.estado_civil==='Soltero/a'?'selected':''}><?= t('civil_single') ?></option><option value="Casado/a" ${data.estado_civil==='Casado/a'?'selected':''}><?= t('civil_married') ?></option><option value="Viudo/a" ${data.estado_civil==='Viudo/a'?'selected':''}><?= t('civil_widowed') ?></option><option value="Divorciado/a" ${data.estado_civil==='Divorciado/a'?'selected':''}><?= t('civil_divorced') ?></option><option value="Unión libre" ${data.estado_civil==='Unión libre'?'selected':''}><?= t('civil_common_law') ?></option></select></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">${t('res_room')}</label><input class="cd-input" id="cdResMgHabitacion" value="${esc(data.habitacion||'')}"></div></div>
        <div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">CURP</label><input class="cd-input" id="cdResMgCurp" value="${esc(data.curp||'')}" maxlength="18"></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">NSS</label><input class="cd-input" id="cdResMgNss" value="${esc(data.nss||'')}"></div></div>
        <div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">${t('res_admission_date')}</label><input type="text" class="cd-input cd-app-date-input" id="cdResMgFechaIngreso" value="${esc(fmtDate(data.fecha_ingreso||nowInTz().date))}" data-iso="${esc(data.fecha_ingreso||nowInTz().date)}" placeholder="${appDatePlaceholder()}" inputmode="numeric"></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">${t('sidebar_status')}</label>
            <select class="cd-input cd-select-native" id="cdResMgEstado"><option value="activo" ${data.estado==='activo'?'selected':''}>${t('status_active')}</option><option value="egresado" ${data.estado==='egresado'?'selected':''}>${t('status_discharged')}</option><option value="fallecido" ${data.estado==='fallecido'?'selected':''}>${t('status_deceased')}</option></select></div></div>`;
    const diagnosisField = isNew
        ? `<div class="cd-nm-dx-wrap" id="cdResMgDxWrap"><div class="cd-nm-dx-input-wrap"><input type="text" id="cdResMgDxSearch" class="cd-nm-dx-search" placeholder="Buscar código o diagnóstico CIE-10" autocomplete="off"><div class="cd-nm-dx-dropdown" id="cdResMgDxDropdown"></div></div><div class="cd-nm-dx-tags" id="cdResMgDxTags"></div></div>`
        : `<textarea class="cd-textarea" id="cdResMgDiagnostico" rows="2">${esc(data.diagnostico||'')}</textarea>`;
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Datos personales</div>
        <div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">Nombre *</label><input class="cd-input" id="cdResMgNombre" value="${esc(data.nombre||'')}"></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">Apellidos *</label><input class="cd-input" id="cdResMgApellidos" value="${esc(data.apellidos||'')}"></div></div>
        <div class="cd-form-row"><div class="cd-form-group" style="flex:1"><label class="cd-form-label">F. Nacimiento</label><input type="text" class="cd-input cd-app-date-input" id="cdResMgFechaNac" value="${data.fecha_nacimiento ? esc(fmtDate(data.fecha_nacimiento)) : ''}" data-iso="${esc(data.fecha_nacimiento||'')}" placeholder="${appDatePlaceholder()}" inputmode="numeric"></div>
        <div class="cd-form-group" style="flex:1"><label class="cd-form-label">Sexo</label>
            <select class="cd-input cd-select-native" id="cdResMgSexo"><option value="">—</option><option value="M" ${data.sexo==='M'?'selected':''}><?= t('gender_male') ?></option><option value="F" ${data.sexo==='F'?'selected':''}><?= t('gender_female') ?></option><option value="Otro" ${data.sexo==='Otro'?'selected':''}>Otro</option></select></div></div>
        ${roomAdmissionFields}
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('res_medical_info')}</div>
        <div class="cd-form-group"><label class="cd-form-label">${t('res_diagnosis')}</label>${diagnosisField}</div>
        <div class="cd-form-group">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <label class="cd-form-label" style="margin:0">${t('res_allergies')}</label>
                <label class="cd-vital-switch"><input type="checkbox" id="cdResMgAlergiaSwitch" ${hasAllergies?'checked':''}><span class="cd-vital-slider"></span></label>
            </div>
            <textarea class="cd-textarea" id="cdResMgAlergias" rows="2" style="margin-top:6px;${hasAllergies?'':'display:none'}" placeholder="${t('res_allergies_placeholder')}">${esc(data.alergias||'')}</textarea>
        </div>
        <div class="cd-form-group"><label class="cd-form-label">${t('res_special_care')}</label><textarea class="cd-textarea" id="cdResMgCuidados" rows="2">${esc(data.cuidados_especiales||'')}</textarea></div>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('res_notes')}</div>
        <div class="cd-form-group"><textarea class="cd-textarea" id="cdResMgNotas" rows="2" placeholder="${t('res_notes_placeholder')}">${esc(data.notas||'')}</textarea></div>
    </div>`;
    const actions = `<button class="cd-btn-submit" id="cdResMgSave">${isNew ? t('btn_create_resident') : t('btn_save_changes')}</button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdResMgCancel"><?= t('btn_cancel') ?></button>`;
    openSidebar(isNew ? t('sidebar_new_resident') : t('sidebar_edit_resident'), body, actions);
    initAppDateTextInput($('#cdResMgFechaNac'));
    initAppDateTextInput($('#cdResMgFechaIngreso'));
    if (isNew) {
        _resMgRenderDxTags();
        _resMgInitDxSelector();
    }

    // Allergy switch toggles textarea
    $('#cdResMgAlergiaSwitch')?.addEventListener('change', e => {
        const ta = $('#cdResMgAlergias');
        ta.style.display = e.target.checked ? '' : 'none';
        if (!e.target.checked) ta.value = '';
    });

    $('#cdResMgSave').addEventListener('click', async () => {
        const nombre = $('#cdResMgNombre').value.trim();
        const apellidos = $('#cdResMgApellidos').value.trim();
        if (!nombre || !apellidos) { showToast(t('error_name_surname_req'), 'error'); return; }
        const saveBtn = $('#cdResMgSave');
        const fechaNac = appDateInputIso($('#cdResMgFechaNac'), { message:'F. Nacimiento: ' + appDatePlaceholder() });
        const fechaIngreso = appDateInputIso($('#cdResMgFechaIngreso'), { required:true, message:t('res_admission_date') + ': ' + appDatePlaceholder() });
        if (fechaNac === null || !fechaIngreso) return;
        btnLoading(saveBtn, t('status_saving'));
        const payload = {
            nombre, apellidos,
            fecha_nacimiento: fechaNac || null,
            sexo: $('#cdResMgSexo').value || null,
            habitacion: $('#cdResMgHabitacion').value.trim(),
            estado_civil: $('#cdResMgEstadoCivil')?.value || null,
            curp: $('#cdResMgCurp')?.value.trim() || '',
            nss: $('#cdResMgNss')?.value.trim() || '',
            diagnostico: isNew ? _resMgDiagnosisValue() : ($('#cdResMgDiagnostico')?.value.trim() || ''),
            alergias: $('#cdResMgAlergiaSwitch').checked ? $('#cdResMgAlergias').value.trim() : '',
            fecha_ingreso: fechaIngreso || null,
            notas: $('#cdResMgNotas').value.trim(),
            cuidados_especiales: $('#cdResMgCuidados').value.trim(),
        };
        if (!isNew) payload.estado = $('#cdResMgEstado')?.value || 'activo';
        try {
            const prevEstado = data.estado || 'activo';
            if (isNew) {
                await api(RES_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
                showToast(t('toast_resident_created'), 'success');
            } else {
                await api(`${RES_API}?id=${data.id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
                showToast(t('toast_resident_updated'), 'success');
                // Log state change if estado changed
                if (payload.estado && payload.estado !== prevEstado) {
                    try { await api(`${RES_API}?id=${data.id}&estado_log=1`, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ estado_anterior: prevEstado, estado_nuevo: payload.estado }) }); } catch(e) {}
                }
            }
            await _refreshResidenteSelect();
            await loadResidentes();
            closeSidebar();
        } catch(e) { showToast(t('error_save'), 'error'); btnReset(saveBtn); }
    });
    $('#cdResMgCancel').addEventListener('click', closeSidebar);
}

async function _refreshResidenteSelect() {
    try {
        const all = await api(`${RES_API}?estado=activo`);
        const sel = $('#cdPatientName');
        if (!sel) return;
        const curVal = sel.value;
        sel.innerHTML = all.map(r => `<option value="${r.id}">${esc((r.nombre||'')+' '+(r.apellidos||''))}</option>`).join('');
        if (!all.length) sel.innerHTML = '<option value="">Sin residentes activos</option>';
        // Restore selection if still exists
        if (curVal && [...sel.options].some(o => o.value == curVal)) sel.value = curVal;
        // Update RESIDENTES global
        RESIDENTES.length = 0;
        all.forEach(r => RESIDENTES.push({ id: r.id, nombre: (r.nombre||'')+' '+(r.apellidos||'') }));
    } catch(e) {}
}

$('#cdResMgmtAddBtn')?.addEventListener('click', () => openResidenteMgmtSidebar(null));

async function openEstadoLogSidebar(r) {
    const fullName = (r.nombre||'') + ' ' + (r.apellidos||'');
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('sidebar_current_status')}</div>
        <p style="margin:0 0 12px"><span class="cd-res-estado-badge ${r.estado==='activo'?'cd-res-estado-activo':r.estado==='egresado'?'cd-res-estado-egresado':'cd-res-estado-fallecido'}">${esc(r.estado||'activo')}</span></p>
        <div class="cd-sidebar-section-title">${t('sidebar_change_history')}</div>
        <div id="cdEstadoLogList" style="min-height:40px"><div class="cd-skeleton" style="height:60px;border-radius:8px"></div></div>
    </div>`;
    openSidebar(`${t('sidebar_history')} — ${esc(fullName)}`, body, `<button class="cd-btn-submit cd-btn-secondary" id="cdEstadoLogClose">${t('btn_close')}</button>`);
    $('#cdEstadoLogClose')?.addEventListener('click', closeSidebar);
    try {
        const logs = await api(`${RES_API}?id=${r.id}&estado_log=1`);
        const div = $('#cdEstadoLogList');
        if (!div) return;
        if (!logs.length) {
            div.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;text-align:center;padding:16px 0">' + t('empty_estado_log') + '</p>';
            return;
        }
        div.innerHTML = logs.map(l => {
            const prevClass = l.estado_anterior==='activo'?'cd-res-estado-activo':l.estado_anterior==='egresado'?'cd-res-estado-egresado':'cd-res-estado-fallecido';
            const newClass = l.estado_nuevo==='activo'?'cd-res-estado-activo':l.estado_nuevo==='egresado'?'cd-res-estado-egresado':'cd-res-estado-fallecido';
            return `<div class="cd-estado-log-entry">
                <div class="cd-estado-log-badges">
                    <span class="cd-res-estado-badge ${prevClass}">${esc(l.estado_anterior)}</span>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--cd-text-muted)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span class="cd-res-estado-badge ${newClass}">${esc(l.estado_nuevo)}</span>
                </div>
                <div class="cd-estado-log-meta">
                    <span>${esc(l.usuario_nombre||'Sistema')}</span>
                    <span>${fmtDateTime(l.creado_at)}</span>
                </div>
                ${l.nota ? `<p class="cd-estado-log-nota">${esc(l.nota)}</p>` : ''}
            </div>`;
        }).join('');
    } catch(e) {
        const div = $('#cdEstadoLogList');
        if (div) div.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem">' + t('error_load_history') + '</p>';
    }
}

let _resMgmtSearchTimeout;
$('#cdResMgmtSearch')?.addEventListener('input', () => {
    clearTimeout(_resMgmtSearchTimeout);
    _resMgmtSearchTimeout = setTimeout(loadResidentes, 300);
});
$('#cdResMgmtFilter')?.addEventListener('change', loadResidentes);

