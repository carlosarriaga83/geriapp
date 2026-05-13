// cd-config.js — Configuration (admin)
// Extracted from cuidados.php (lines 13327)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// CONFIGURATION (admin only)
// ═══════════════════════════════════════════════
const CFG_API = BASE + '/api/configuracion.php';
const INVITE_API = BASE + '/api/invitaciones.php';
const NOTIF_API = BASE + '/api/notificaciones.php';
const LEGAL_API = BASE + '/api/documentos_legales.php';
const BILLING_API = BASE + '/api/billing.php';
let _cfgLoaded = false;
let _cfgData = {};
let _legalTcLoaded = false;
let _legalPrivLoaded = false;
let _cfgBillingLoaded = false;

// Tab switching
function _positionCfgPill(container) {
    if (!container) return;
    const pill = container.querySelector(':scope > .cd-cfg-tabs-pill, :scope > .cd-cfg-subtabs-pill');
    if (!pill) return;
    const active = container.querySelector(':scope > .cd-cfg-tab.active, :scope > .cd-cfg-subtab.active');
    if (!active || !active.offsetWidth) { pill.style.opacity = '0'; return; }
    pill.style.opacity = '1';
    pill.style.width = active.offsetWidth + 'px';
    pill.style.transform = `translateX(${active.offsetLeft}px)`;
}
function _positionAllVisibleCfgPills() {
    document.querySelectorAll('#cdCfgTabs, .cd-cfg-panel.active .cd-cfg-subtabs').forEach(_positionCfgPill);
}
window.addEventListener('resize', () => requestAnimationFrame(_positionAllVisibleCfgPills));

$('#cdCfgTabs')?.addEventListener('click', e => {
    const tab = e.target.closest('.cd-cfg-tab');
    if (!tab) return;
    const cfgKey = tab.dataset.cfgNav || tab.dataset.cfg;
    $$('.cd-cfg-tab', e.currentTarget).forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
    });
    tab.classList.add('active');
    tab.setAttribute('aria-selected', 'true');
    $$('.cd-cfg-panel').forEach(p => p.classList.remove('active'));
    const panel = $(`.cd-cfg-panel[data-cfg-panel="${cfgKey}"]`);
    if (panel) panel.classList.add('active');
    _positionCfgPill(e.currentTarget);
    requestAnimationFrame(_positionAllVisibleCfgPills);
    if (cfgKey === 'equipo' && !$('#cfgUsersList')?.children.length) loadPersonal();
    if (cfgKey === 'logs' && !$('#cfgLogsList')?.children.length) loadLogs();
    if (cfgKey === 'db') loadDbConfig();
    if (cfgKey === 'notificaciones_admin') loadNotificacionesAdmin();
    if (cfgKey === 'instituciones') loadInstituciones();
    if (cfgKey === 'facturacion') loadConfigBilling();
});

// Sub-tab switching (generic for Logs , DB, etc.)
document.querySelectorAll('.cd-cfg-subtabs').forEach(container => {
    container.addEventListener('click', e => {
        const st = e.target.closest('.cd-cfg-subtab');
        if (!st) return;
        container.querySelectorAll(':scope > .cd-cfg-subtab').forEach(s => s.classList.remove('active'));
        st.classList.add('active');
        const parent = container.closest('.cd-cfg-panel');
        if (!parent) return;
        parent.querySelectorAll(':scope > .cd-cfg-subpanel').forEach(p => p.classList.remove('active'));
        const sub = parent.querySelector(`.cd-cfg-subpanel[data-subpanel="${st.dataset.subtab}"]`);
        if (sub) sub.classList.add('active');
        _positionCfgPill(container);
        // Lazy load on sub-tab activate
        if (st.dataset.subtab === 'errores' && !_errorLogLoaded) loadErrorLog();
        if (st.dataset.subtab === 'backups' && !_backupsLoaded) loadBackups();
        if (st.dataset.subtab === 'sesiones') { loadSessions(); startSessCountdown(); renderPushDiag(); }
        if (st.dataset.subtab === 'personal' && !$('#cfgUsersList')?.children.length) loadPersonal();
        if (st.dataset.subtab === 'terminos' && !_legalTcLoaded) loadLegalDocs('terminos');
        if (st.dataset.subtab === 'privacidad' && !_legalPrivLoaded) loadLegalDocs('privacidad');
        if (st.dataset.subtab === 'catalogo' && typeof renderTagsCatalog === 'function') renderTagsCatalog();
    });
});

async function loadConfig(force = false) {
    if (_cfgLoaded && !force) return;
    // Position pills now that view is visible
    requestAnimationFrame(_positionAllVisibleCfgPills);
    // Show ghost loading on all config input fields
    $$('.cd-cfg-panel .cd-input, .cd-cfg-panel .cd-textarea, .cd-cfg-panel select').forEach(el => {
        el.classList.add('cd-skeleton');
        el.style.pointerEvents = 'none';
    });
    try {
        _cfgData = await api(CFG_API);
        _cfgLoaded = true;
        populateConfigForms(_cfgData);
        if ($('#cfgPanelFacturacion')?.classList.contains('active')) loadConfigBilling(true);
        // Re-populate after short delay to defeat Chrome autofill overwrites
        setTimeout(() => populateConfigForms(_cfgData), 350);
        // Warn about corrupted fields (mask saved to DB by old bug)
        _warnCorruptedFields(_cfgData._corrupted_fields);
    } catch(e) {}
    // Remove ghost loading
    $$('.cd-cfg-panel .cd-input.cd-skeleton, .cd-cfg-panel .cd-textarea.cd-skeleton, .cd-cfg-panel select.cd-skeleton').forEach(el => {
        el.classList.remove('cd-skeleton');
        el.style.pointerEvents = '';
    });
}

function populateConfigForms(c) {
    // General
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    v('cfgInstNombre', c.inst_nombre);
    v('cfgAppUrl', c.app_url);
    if (c.timezone) $('#cfgTimezone').value = c.timezone;
    if (c.idioma) $('#cfgIdioma').value = c.idioma;
    if (c.fecha_formato) $('#cfgFechaFormato').value = c.fecha_formato;
    if (c.moneda) $('#cfgMoneda').value = c.moneda;
    if (c.inst_logo) {
        const logo = $('#cfgLogoPreview');
        if (logo) { logo.src = c.inst_logo; logo.style.display = ''; }
    }
    v('cfgLegalCcEmail', c.legal_cc_email);
    v('cfgSupportPhone', c.support_phone);
    // Hint con el nombre del administrador de la institución
    const _adminHint = $('#cfgSupportPhoneAdminHint');
    if (_adminHint) {
        const nm = (c._admin_name || '').trim();
        if (nm) {
            const lbl = (typeof t === 'function' ? t('config_support_phone_admin_hint') : 'Administrador de la institución:');
            _adminHint.textContent = lbl + ' ' + nm;
            _adminHint.style.display = '';
        } else {
            _adminHint.textContent = '';
            _adminHint.style.display = 'none';
        }
    }
    // SMTP
    _populateSmtp(c);
    // WhatsApp
    _populateWa(c);
    // Security
    _populateSeg(c);
    // Roles
    populateRolesTable(c.roles_permisos);
    // Permisos UI elements
    _populatePermElementsGrid(c.perm_elements);
    // AI
    _populateIa(c);
}

function _populateSmtp(c) {
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    v('cfgSmtpHost', c.smtp_host); v('cfgSmtpPort', c.smtp_port);
    v('cfgSmtpUser', c.smtp_usuario); v('cfgSmtpPass', c.smtp_password);
    v('cfgSmtpFromEmail', c.smtp_from_email); v('cfgSmtpFromName', c.smtp_from_nombre);
    if (c.smtp_encriptacion) $('#cfgSmtpEnc').value = c.smtp_encriptacion.toLowerCase();
}

function _populateWa(c) {
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    if (c.wa_proveedor) $('#cfgWaProvider').value = c.wa_proveedor.toLowerCase();
    v('cfgWaKey', c.wa_api_key); v('cfgWaInstId', c.wa_instance_id); v('cfgWaPhone', c.wa_phone);
    if ($('#cfgWaActive')) $('#cfgWaActive').checked = c.wa_activo == 1;
    toggleWaInstId();
}

function _populateSeg(c) {
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    v('cfgSegPassLen', c.seg_pass_min_len || 8);
    v('cfgSegPassExpire', c.seg_pass_expira_dias || 0);
    v('cfgSegTimeout', c.seg_timeout_sesion || 60);
    v('cfgSegMaxAttempts', c.seg_max_intentos || 5);
    v('cfgSegBlockMin', c.seg_bloqueo_min || 15);
    if ($('#cfgSegLog')) $('#cfgSegLog').checked = c.seg_log_accesos != 0;
}

function _populateIa(c) {
    const v = (id, val) => { const el = $('#'+id); if (el) el.value = val || ''; };
    if (c.ia_proveedor) {
        $$('.cd-ia-provider').forEach(p => p.classList.toggle('active', p.dataset.provider === c.ia_proveedor));
        if ($('#cfgIaProvider')) $('#cfgIaProvider').value = c.ia_proveedor;
        const _iaHints = { openai:'platform.openai.com → API Keys', gemini:'aistudio.google.com → API Keys', deepseek:'platform.deepseek.com → API Keys' };
        const _iaHint = $('#cfgIaKeyHint'); if (_iaHint) _iaHint.textContent = t('config_ia_find_at', {':provider': (_iaHints[c.ia_proveedor] || t('config_ia_provider_panel'))});
    }
    v('cfgIaKey', c.ia_api_key); v('cfgIaModel', c.ia_modelo); v('cfgIaPrompt', c.ia_prompt);
    v('cfgIaMaxPalabras', c.ia_max_palabras || 400);
}

/**
 * Recarga la configuración desde la BD y repopula SOLO la sección indicada.
/**
 * Muestra alerta si hay campos cuyo valor real se perdió por un bug anterior
 * (el valor enmascarado fue guardado en la BD).
 */
function _warnCorruptedFields(fields) {
    if (!fields || !fields.length) return;
    const labels = { smtp_password: 'Contraseña SMTP', wa_api_key: 'API Key WhatsApp', ia_api_key: 'API Key IA' };
    const names = fields.map(f => labels[f] || f).join(', ');
    showToast(`⚠️ ${names}: el valor almacenado estaba corrupto y fue limpiado. Re-ingresa el valor real y guarda.`, 'warning', 12000);
    // Highlight the affected input fields
    const inputMap = { smtp_password: 'cfgSmtpPass', wa_api_key: 'cfgWaKey', ia_api_key: 'cfgIaKey' };
    fields.forEach(f => {
        const el = $('#' + (inputMap[f] || ''));
        if (el) {
            el.style.borderColor = 'var(--cd-warning, #f0883e)';
            el.placeholder = '⚠ Re-ingresa el valor real';
        }
    });
}

/**
 * Recarga la configuración desde la BD y repopula SOLO la sección indicada.
 * Evita que guardar una sección sobreescriba cambios no guardados de otras.
 */
async function _reloadSection(seccion) {
    try {
        _cfgData = await api(CFG_API);
        _cfgLoaded = true;
        const fn = {
            smtp: _populateSmtp, whatsapp: _populateWa,
            seguridad: _populateSeg, ia: _populateIa,
            roles: (c) => populateRolesTable(c.roles_permisos),
            permisos_ui: (c) => _populatePermElementsGrid(c.perm_elements),
        };
        if (fn[seccion]) fn[seccion](_cfgData);
        _warnCorruptedFields(_cfgData._corrupted_fields);
    } catch(e) {}
}

function toggleWaInstId() {
    const grp = $('#cfgWaInstIdGroup');
    if (grp) grp.style.display = $('#cfgWaProvider')?.value === 'waapi' ? '' : 'none';
}
$('#cfgWaProvider')?.addEventListener('change', toggleWaInstId);

// General save
$('#cfgGeneralSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgGeneralSave');
    btnLoading(_btn, t('status_saving'));
    const body = { seccion:'sistema',
        app_url:$('#cfgAppUrl')?.value,
        timezone:$('#cfgTimezone')?.value,
        idioma:$('#cfgIdioma')?.value,
        fecha_formato:$('#cfgFechaFormato')?.value,
        moneda:$('#cfgMoneda')?.value,
        inst_nombre:$('#cfgInstNombre')?.value,
        legal_cc_email:$('#cfgLegalCcEmail')?.value?.trim(),
        support_phone:$('#cfgSupportPhone')?.value?.trim() };
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        // Apply changes immediately without page refresh
        if (body.timezone)       APP_TZ = body.timezone;
        if (body.fecha_formato)  APP_DATE_FMT = body.fecha_formato;
        setDate(_fecha); // re-render date display with new tz/format
        if (typeof window._refreshDateFmtHints === 'function') window._refreshDateFmtHints();
        if (typeof window._refreshExpDateFilters === 'function') window._refreshExpDateFilters();
        showToast(t('toast_general_saved'),'success');
    } catch(e) {}
    // Logo upload
    const fileInput = $('#cfgLogoFile');
    if (fileInput?.files?.length) {
        const fd = new FormData();
        fd.append('logo', fileInput.files[0]);
        try {
            await fetch(CFG_API + '?action=upload_logo', { method:'POST', body:fd, credentials:'same-origin' });
        } catch(e) {}
    }
    await _reloadSection('sistema');
    btnReset(_btn);
});

// SMTP save/test
$('#cfgSmtpSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgSmtpSave');
    btnLoading(_btn, t('status_saving'));
    const body = { seccion:'smtp', smtp_host:$('#cfgSmtpHost').value, smtp_port:$('#cfgSmtpPort').value,
        smtp_encriptacion:$('#cfgSmtpEnc').value, smtp_usuario:$('#cfgSmtpUser').value,
        smtp_password:$('#cfgSmtpPass').value, smtp_from_email:$('#cfgSmtpFromEmail').value,
        smtp_from_nombre:$('#cfgSmtpFromName').value };
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        showToast(t('toast_smtp_saved'),'success');
        await _reloadSection('smtp');
    } catch(e) {}
    btnReset(_btn);
});

$('#cfgSmtpTest')?.addEventListener('click', () => {
    openSidebar(t('config_smtp_title'), `<div class="cd-sidebar-section">
        <label class="cd-label">Email de destino</label>
        <input class="cd-input" id="cdSmtpTestEmail" placeholder="ejemplo@correo.com">
    </div>`, `<button class="cd-btn-submit" id="cdSmtpTestSend" data-perm-id="cfg_smtp_test_send_btn">Enviar prueba</button>`);
    $('#cdSmtpTestSend')?.addEventListener('click', async () => {
        const email = $('#cdSmtpTestEmail')?.value?.trim();
        if (!email) { showToast(t('error_enter_email'),'error'); return; }
        const _btn = $('#cdSmtpTestSend');
        btnLoading(_btn, t('status_sending'));
        try {
            await api(CFG_API + '?action=test_smtp', { method:'PUT', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ dest_email:email, smtp_host:$('#cfgSmtpHost').value, smtp_port:$('#cfgSmtpPort').value,
                    smtp_encriptacion:$('#cfgSmtpEnc').value, smtp_usuario:$('#cfgSmtpUser').value,
                    smtp_password:$('#cfgSmtpPass').value }) });
            showToast(t('toast_test_email_sent'),'success');
            closeSidebar();
        } catch(e) { btnReset(_btn); }
    });
});

// WhatsApp save/test
$('#cfgWaSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgWaSave');
    btnLoading(_btn, t('status_saving'));
    const body = { seccion:'whatsapp', wa_proveedor:$('#cfgWaProvider').value, wa_api_key:$('#cfgWaKey').value,
        wa_instance_id:$('#cfgWaInstId').value, wa_phone:$('#cfgWaPhone').value,
        wa_activo:$('#cfgWaActive')?.checked ? 1 : 0 };
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        showToast(t('toast_wa_saved'),'success');
        await _reloadSection('whatsapp');
    } catch(e) {}
    btnReset(_btn);
});

$('#cfgWaTest')?.addEventListener('click', () => {
    openSidebar(t('config_wa_title'), `<div class="cd-sidebar-section">
        <label class="cd-label">Número de destino (con código de país)</label>
        <input class="cd-input" id="cdWaTestPhone" placeholder="+56912345678">
    </div>`, `<button class="cd-btn-submit" id="cdWaTestSend" data-perm-id="cfg_wa_test_send_btn">Enviar prueba</button>`);
    $('#cdWaTestSend')?.addEventListener('click', async () => {
        const phone = $('#cdWaTestPhone')?.value?.trim();
        if (!phone) { showToast(t('error_enter_phone'),'error'); return; }
        const _btn = $('#cdWaTestSend');
        btnLoading(_btn, t('status_sending'));
        try {
            await api(CFG_API + '?action=test_wa', { method:'PUT', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ dest_phone:phone, wa_proveedor:$('#cfgWaProvider').value,
                    wa_api_key:$('#cfgWaKey').value, wa_instance_id:$('#cfgWaInstId').value }) });
            showToast(t('toast_test_msg_sent'),'success');
            closeSidebar();
        } catch(e) { btnReset(_btn); }
    });
});

// Roles & Permissions
const PERM_LIST = [
    // ── Visibilidad de secciones (orden de navegación) ──
    {section: t('perm_section_visibility'), sectionKey: 'section_visibility'},
    {key:'ver_seccion_residentes',    label:t('perm_ver_seccion_residentes'),    desc:t('perm_desc_ver_seccion_residentes')},
    {key:'ver_seccion_inicio',        label:t('perm_ver_seccion_inicio'),        desc:t('perm_desc_ver_seccion_inicio')},
    {key:'ver_seccion_registros',     label:t('perm_ver_seccion_registros'),     desc:t('perm_desc_ver_seccion_registros')},
    {key:'ver_seccion_medicacion',    label:t('perm_ver_seccion_medicacion'),    desc:t('perm_desc_ver_seccion_medicacion')},
    {key:'ver_seccion_ficha',         label:t('perm_ver_seccion_ficha'),         desc:t('perm_desc_ver_seccion_ficha')},
    {key:'ver_seccion_expediente',    label:t('perm_ver_seccion_expediente'),    desc:t('perm_desc_ver_seccion_expediente')},
    {key:'ver_seccion_configuracion', label:t('perm_ver_seccion_configuracion'), desc:t('perm_desc_ver_seccion_configuracion')},
    // ── Cuidados (Registrar) ──
    {section: t('perm_section_inicio'), sectionKey: 'section_inicio'},
    {key:'ver_cuidados_grid',       label:t('perm_ver_cuidados_grid'),       desc:t('perm_desc_ver_cuidados_grid')},
    {key:'hacer_registros',         label:t('perm_hacer_registros'),         desc:t('perm_desc_hacer_registros')},
    {key:'registrar_futuro',        label:t('perm_register_future'),         desc:t('perm_desc_register_future')},
    {key:'ver_rx_tracker',          label:t('perm_ver_rx_tracker'),          desc:t('perm_desc_ver_rx_tracker')},
    {key:'ver_rx_tracker_readonly', label:t('perm_ver_rx_tracker_readonly'), desc:t('perm_desc_ver_rx_tracker_readonly')},
    // ── Ficha del residente ──
    {section: t('perm_section_ficha'), sectionKey: 'section_ficha'},
    {key:'editar_residentes',       label:t('perm_edit_residents'),          desc:t('perm_desc_edit_residents')},
    {key:'editar_familia',          label:t('perm_edit_family'),             desc:t('perm_desc_edit_family')},
    {key:'mover_residente_institucion', label:t('perm_move_resident_institution'), desc:t('perm_desc_move_resident_institution'), roles:['admin','enfermero','medico']},
    // ── Expediente ──
    {section: t('perm_section_expediente'), sectionKey: 'section_expediente'},
    {key:'editar_expediente',       label:t('perm_edit_expediente'),         desc:t('perm_desc_edit_expediente')},
    {key:'ver_notas_medico',        label:t('perm_ver_notas_medico'),        desc:t('perm_desc_ver_notas_medico')},
    {key:'notas_medico_directo',    label:t('perm_notas_medico_directo'),    desc:t('perm_desc_notas_medico_directo'), roles:['familiar']},
    // ── Reportes ──
    {section: t('perm_section_reportes'), sectionKey: 'section_reportes'},
    {key:'preview_reportes',        label:t('perm_preview_reports'),         desc:t('perm_desc_preview_reports')},
];
const ROLE_COLS = ['admin','enfermero','medico','familiar'];

// Default permissions matching actual system behavior
const DEFAULT_PERMS = {
    admin: {
        ver_seccion_inicio:true, ver_seccion_registros:true, ver_seccion_medicacion:true, ver_seccion_ficha:true, ver_seccion_expediente:true, ver_seccion_residentes:true, ver_seccion_configuracion:true,
        ver_cuidados_grid:true, hacer_registros:true, registrar_futuro:true, ver_rx_tracker:true, ver_rx_tracker_readonly:true,
        editar_residentes:true, editar_familia:true, mover_residente_institucion:true,
        editar_expediente:true, ver_notas_medico:true, notas_medico_directo:false,
        preview_reportes:true,
    },
    enfermero: {
        ver_seccion_inicio:true, ver_seccion_registros:true, ver_seccion_medicacion:true, ver_seccion_ficha:true, ver_seccion_expediente:true, ver_seccion_residentes:false, ver_seccion_configuracion:false,
        ver_cuidados_grid:true, hacer_registros:true, registrar_futuro:false, ver_rx_tracker:true, ver_rx_tracker_readonly:true,
        editar_residentes:false, editar_familia:false, mover_residente_institucion:false,
        editar_expediente:true, ver_notas_medico:true, notas_medico_directo:false,
        preview_reportes:true,
    },
    medico: {
        ver_seccion_inicio:true, ver_seccion_registros:true, ver_seccion_medicacion:true, ver_seccion_ficha:true, ver_seccion_expediente:true, ver_seccion_residentes:false, ver_seccion_configuracion:false,
        ver_cuidados_grid:true, hacer_registros:true, registrar_futuro:false, ver_rx_tracker:true, ver_rx_tracker_readonly:true,
        editar_residentes:true, editar_familia:false, mover_residente_institucion:false,
        editar_expediente:true, ver_notas_medico:true, notas_medico_directo:false,
        preview_reportes:true,
    },
    familiar: {
        ver_seccion_inicio:true, ver_seccion_registros:true, ver_seccion_medicacion:true, ver_seccion_ficha:true, ver_seccion_expediente:true, ver_seccion_residentes:true, ver_seccion_configuracion:false,
        ver_cuidados_grid:true, hacer_registros:false, registrar_futuro:false, ver_rx_tracker:false, ver_rx_tracker_readonly:true,
        editar_residentes:false, editar_familia:false, mover_residente_institucion:false,
        editar_expediente:false, ver_notas_medico:true, notas_medico_directo:true,
        preview_reportes:false,
    },
};

function populateRolesTable(permsJson) {
    let perms = {};
    if (typeof permsJson === 'string') { try { perms = JSON.parse(permsJson); } catch(e) {} }
    else if (permsJson) perms = permsJson;
    // If perms is empty, use defaults
    if (!Object.keys(perms).length) perms = JSON.parse(JSON.stringify(DEFAULT_PERMS));
    const tbody = $('#cfgPermsTable tbody');
    if (!tbody) return;
    const infoSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    tbody.innerHTML = PERM_LIST.map(p => {
        if (p.section) return `<tr class="cd-perm-section-row" data-section-key="${p.sectionKey}">
        <td colspan="5" style="font-weight:700;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--cd-text-muted);padding:14px 0 6px;border-bottom:2px solid var(--cd-border)">${esc(p.section)}</td>
    </tr>`;
        return `<tr>
        <td><span style="display:inline-flex;align-items:center;gap:6px">${esc(p.label)}<button type="button" class="cd-perm-info-btn" data-perm-key="${p.key}" title="${t('perm_info_tooltip')}">${infoSvg}</button></span></td>
        ${ROLE_COLS.map(r => {
            const allowed = !p.roles || p.roles.includes(r);
            const checked = r === 'admin' || (allowed && !!perms[r]?.[p.key]);
            const disabled = r === 'admin' || !allowed;
            return `<td><label class="cd-toggle cd-toggle-sm"><input type="checkbox" data-perm="${p.key}" data-role="${r}" ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}><span class="cd-toggle-track"></span></label></td>`;
        }).join('')}
    </tr>`;
    }).join('');
    // Info button click
    tbody.querySelectorAll('.cd-perm-info-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const key = btn.dataset.permKey;
            const perm = PERM_LIST.find(p => p.key === key);
            if (!perm) return;
            const defaults = ROLE_COLS.map(r => `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--cd-border)"><span>${t('role_' + r)}</span><span style="font-weight:600">${DEFAULT_PERMS[r]?.[key] ? '✓ Activo' : '✗ Inactivo'}</span></div>`).join('');
            const body = `<div style="padding:4px 0">
                <p style="margin:0 0 16px;color:var(--cd-text-secondary);font-size:0.875rem;line-height:1.5">${esc(perm.desc)}</p>
                <div style="margin-top:12px"><h4 style="margin:0 0 8px;font-size:0.8125rem;color:var(--cd-text-muted)">${t('perm_info_defaults')}</h4>${defaults}</div>
            </div>`;
            openSidebar(perm.label, body);
        });
    });
}

$('#cfgRolesDefaults')?.addEventListener('click', async () => {
    if (!await cdConfirm(t('confirm_reset_perms_body'), { title: t('confirm_reset_perms_title'), type: 'warn', okText: t('confirm_reset_btn') })) return;
    populateRolesTable(DEFAULT_PERMS);
    showToast(t('toast_defaults_applied'),'info');
});

$('#cfgRolesSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgRolesSave');
    btnLoading(_btn, t('status_saving'));
    const perms = {};
    ROLE_COLS.forEach(r => { perms[r] = {}; });
    $$('#cfgPermsTable input[type="checkbox"]').forEach(cb => {
        const role = cb.dataset.role, perm = cb.dataset.perm;
        if (role && perm) perms[role][perm] = cb.checked;
    });
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ seccion:'roles', roles_permisos:perms }) });
        showToast(t('toast_perms_saved'),'success');
        await _reloadSection('roles');
    } catch(e) {}
    btnReset(_btn);
});

// Security save
$('#cfgSegSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgSegSave');
    btnLoading(_btn, t('status_saving'));
    const body = { seccion:'seguridad', seg_pass_min_len:$('#cfgSegPassLen').value,
        seg_pass_expira_dias:$('#cfgSegPassExpire').value,
        seg_timeout_sesion:$('#cfgSegTimeout').value,
        seg_max_intentos:$('#cfgSegMaxAttempts').value,
        seg_bloqueo_min:$('#cfgSegBlockMin').value,
        seg_log_accesos:$('#cfgSegLog')?.checked ? 1 : 0 };
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        showToast(t('toast_security_saved'),'success');
        await _reloadSection('seguridad');
    } catch(e) {}
    btnReset(_btn);
});

function _cfgBillingNative() {
    return document.documentElement.dataset.native === '1' || document.documentElement.dataset.platform === 'native' || !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());
}

function _cfgBillingMoney(amount, currency) {
    const n = Number(amount || 0);
    try { return new Intl.NumberFormat(currency === 'MXN' ? 'es-MX' : (currency === 'COP' ? 'es-CO' : 'en-US'), { style:'currency', currency: currency || 'MXN', maximumFractionDigits: 0 }).format(n); }
    catch(_) { return `${n.toFixed(2)} ${currency || ''}`.trim(); }
}

function _cfgBillingDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    return [String(d.getDate()).padStart(2, '0'), String(d.getMonth() + 1).padStart(2, '0'), d.getFullYear()].join('-');
}

function _cfgBillingPaymentLabel(tipo, status) {
    if (tipo === 'invoice.payment_failed') return 'Pago fallido';
    if (tipo === 'checkout.session.completed') return 'Checkout completado';
    if (tipo === 'invoice.paid' || tipo === 'invoice.payment_succeeded') return 'Pago recibido';
    return status || tipo || 'Evento';
}

function _cfgBillingStatusBadge(status) {
    const map = {
        active:{ label:'Activa', bg:'rgba(34,197,94,.14)', fg:'#15803d' }, trialing:{ label:'Prueba', bg:'rgba(23,131,145,.14)', fg:'#0f7682' },
        trial:{ label:'Prueba', bg:'rgba(23,131,145,.14)', fg:'#0f7682' }, past_due:{ label:'Vencida', bg:'rgba(245,158,11,.16)', fg:'#b45309' },
        canceled:{ label:'Cancelada', bg:'rgba(107,114,128,.16)', fg:'#4b5563' }, cancelada:{ label:'Cancelada', bg:'rgba(107,114,128,.16)', fg:'#4b5563' },
        incomplete:{ label:'Incompleta', bg:'rgba(245,158,11,.16)', fg:'#b45309' }, unpaid:{ label:'Sin pago', bg:'rgba(239,68,68,.14)', fg:'#b91c1c' },
    };
    const v = map[String(status || '').toLowerCase()] || { label: status || 'Sin suscripción', bg:'rgba(107,114,128,.14)', fg:'#4b5563' };
    return `<span class="cd-cfg-billing-badge" style="background:${v.bg};color:${v.fg}">${_instEsc(v.label)}</span>`;
}

function _cfgBillingLimit(used, max) {
    if (max === null || max === undefined || max === '') return `${used || 0} / sin límite`;
    return `${used || 0} / ${max}`;
}

async function loadConfigBilling(force = false) {
    const panel = $('#cfgBillingPanel');
    if (!panel || _cfgBillingNative()) return;
    if (_cfgBillingLoaded && !force) return;
    panel.innerHTML = '<div class="cd-cfg-billing-loading">Cargando facturación...</div>';
    try {
        const [status, history, plans] = await Promise.all([
            api(BILLING_API + '?action=status'),
            api(BILLING_API + '?action=payment_history'),
            api(BILLING_API + '?action=plans'),
        ]);
        _cfgBillingLoaded = true;
        _renderConfigBilling(status || {}, history?.payments || [], plans?.planes || []);
    } catch(err) {
        panel.innerHTML = `<div class="cd-cfg-billing-empty">${_instEsc(err?.message || 'No se pudo cargar facturación')}</div>`;
    }
}

function _renderConfigBilling(status, payments, plans) {
    const panel = $('#cfgBillingPanel'); if (!panel) return;
    const sub = status.subscription || null;
    const usage = status.usage || {};
    const limits = sub?.plan_limits || {};
    const planName = sub?.plan_nombre || status.plan?.nombre || 'Sin plan activo';
    const period = sub?.periodo ? String(sub.periodo).replace('monthly', 'mensual').replace('yearly', 'anual') : '—';
    const lastPay = sub?.ultimo_cobro || null;
    const planCards = plans.slice(0, 6).map(p => {
        const prices = (p.precios || []).map(pr => `${_cfgBillingMoney(pr.precio, pr.moneda)} ${pr.periodo === 'yearly' || pr.periodo === 'anual' ? 'anual' : 'mensual'}`).join(' · ') || 'Precio no configurado';
        return `<div class="cd-cfg-billing-plan${sub && +sub.plan_id === +p.id ? ' is-current' : ''}"><strong>${_instEsc(p.nombre || 'Plan')}</strong><span>${_instEsc(prices)}</span><small>Res ${_cfgBillingLimit(usage.residentes, p.max_residentes)} · Fam ${_cfgBillingLimit(usage.familiares, p.max_familiares)}</small></div>`;
    }).join('');
    const paymentRows = payments.length ? payments.map(row => {
        const links = [row.recibo_url ? `<a href="${_instEsc(row.recibo_url)}" target="_blank" rel="noopener">Recibo</a>` : '', row.recibo_pdf_url ? `<a href="${_instEsc(row.recibo_pdf_url)}" target="_blank" rel="noopener">PDF</a>` : ''].filter(Boolean).join(' · ');
        return `<tr><td>${_cfgBillingDate(row.recibido_at)}</td><td>${_instEsc(_cfgBillingPaymentLabel(row.tipo, row.status))}</td><td>${_instEsc(row.status || '—')}</td><td>${row.monto != null ? _cfgBillingMoney(row.monto, row.moneda) : '—'}</td><td>${links || '—'}</td></tr>`;
    }).join('') : '<tr><td colspan="5">Sin cobros registrados todavía.</td></tr>';
    const instList = (status.instituciones || []).length ? (status.instituciones || []).map(i => `<span>${_instEsc(i.nombre || 'Institución')}</span>`).join('') : '<span>Sin instituciones vinculadas</span>';
    panel.innerHTML = `
        <div class="cd-cfg-billing-grid">
            <section class="cd-cfg-billing-card cd-cfg-billing-card--primary">
                <div class="cd-cfg-billing-kicker">Plan actual</div>
                <div class="cd-cfg-billing-plan-name">${_instEsc(planName)}</div>
                <div class="cd-cfg-billing-meta">${_cfgBillingStatusBadge(sub?.estado || status.institution_trial?.estado || '')}<span>${_instEsc((sub?.moneda || status.currency_default || 'MXN') + ' · ' + period)}</span></div>
                <div class="cd-cfg-billing-actions"><button type="button" class="cd-btn-submit" id="cfgBillingOpenAppBtn">Ver planes y asientos</button><button type="button" class="cd-btn-submit cd-btn-secondary" id="cfgBillingPortalBtn" ${sub?.has_customer ? '' : 'disabled'}>Método de pago</button></div>
            </section>
            <section class="cd-cfg-billing-card"><div class="cd-cfg-billing-kicker">Periodo</div><strong>${_cfgBillingDate(sub?.periodo_inicio)} - ${_cfgBillingDate(sub?.periodo_fin || sub?.trial_ends_at || status.institution_trial?.trial_ends_at)}</strong><span>${sub?.cancel_at_period_end ? 'Cancelación programada al final del periodo' : 'Renovación activa'}</span></section>
            <section class="cd-cfg-billing-card"><div class="cd-cfg-billing-kicker">Último cobro</div><strong>${lastPay ? _cfgBillingMoney(lastPay.monto, lastPay.moneda) : '—'}</strong><span>${lastPay ? _cfgBillingDate(lastPay.recibido_at) : 'Sin cobros todavía'}</span></section>
        </div>
        <div class="cd-cfg-billing-usage">
            <div><span>Residentes</span><strong>${_cfgBillingLimit(usage.residentes, limits.max_residentes)}</strong></div>
            <div><span>Familiares</span><strong>${_cfgBillingLimit(usage.familiares, limits.max_familiares)}</strong></div>
            <div><span>Personal</span><strong>${_cfgBillingLimit(usage.personal, limits.max_usuarios)}</strong></div>
            <div><span>Instituciones</span><strong>${_cfgBillingLimit((status.instituciones || []).length, limits.max_instituciones)}</strong></div>
        </div>
        <section class="cd-cfg-billing-section"><h3>Instituciones cubiertas</h3><div class="cd-cfg-billing-chips">${instList}</div></section>
        <section class="cd-cfg-billing-section"><h3>Planes disponibles</h3><div class="cd-cfg-billing-plans">${planCards || '<div class="cd-cfg-billing-empty">No hay catálogo de planes disponible.</div>'}</div></section>
        <section class="cd-cfg-billing-section"><h3>Historial</h3><div class="cd-cfg-billing-table-wrap"><table class="cd-cfg-billing-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Estado</th><th>Monto</th><th>Documento</th></tr></thead><tbody>${paymentRows}</tbody></table></div></section>`;
}

$('#cfgBillingRefreshBtn')?.addEventListener('click', () => loadConfigBilling(true));
document.addEventListener('click', async e => {
    if (e.target.closest('#cfgBillingOpenAppBtn')) {
        window.open(`${BASE}/billing.php`, '_blank', 'noopener');
        return;
    }
    if (e.target.closest('#cfgBillingPortalBtn')) {
        try {
            const r = await api(BILLING_API + '?action=portal', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ return_url: location.href }) });
            if (r?.url) window.open(r.url, '_blank', 'noopener');
        } catch(err) { showToast(err?.message || 'No se pudo abrir el portal de pago', 'error'); }
    }
});

// ═══════════════════════════════════════════════
// MIS INSTITUCIONES (admin/superadmin)
// ═══════════════════════════════════════════════
const INST_API = BASE + '/api/instituciones.php';
let _instLoaded = false;
let _instData = { instituciones: [], plan: null, usadas: 0, puede_crear: true };

function _instEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

function _instStateBadge(estado) {
    const map = {
        activa:      { label: 'Activa',      bg: 'rgba(34,197,94,.15)',  fg: '#16a34a' },
        trial:       { label: 'Prueba',      bg: 'rgba(23,131,145,.15)', fg: '#178391' },
        past_due:    { label: 'Vencida',     bg: 'rgba(245,158,11,.15)', fg: '#d97706' },
        suspendida:  { label: 'Suspendida',  bg: 'rgba(239,68,68,.15)',  fg: '#dc2626' },
        archivada:   { label: 'Archivada',   bg: 'rgba(107,114,128,.15)', fg: '#6b7280' },
    };
    const v = map[estado] || { label: estado || '—', bg: 'rgba(107,114,128,.15)', fg: '#6b7280' };
    return `<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:0.6875rem;font-weight:600;background:${v.bg};color:${v.fg}">${_instEsc(v.label)}</span>`;
}

async function loadInstituciones(force = false) {
    if (_instLoaded && !force) return;
    const list = $('#instList'); if (!list) return;
    list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--cd-text-muted);font-size:0.875rem">Cargando…</div>';
    try {
        const r = await api(INST_API + '?action=list');
        _instData = r || _instData;
        _instLoaded = true;
        _renderInstituciones();
    } catch(e) {
        list.innerHTML = `<div style="padding:24px;text-align:center;color:var(--cd-danger,#e74c3c);font-size:0.875rem">Error al cargar instituciones</div>`;
    }
}

function _renderInstituciones() {
    const list = $('#instList'); if (!list) return;
    const items = _instData.instituciones || [];
    const plan = _instData.plan;
    const usadas = _instData.usadas || 0;
    const max = plan && plan.max_instituciones != null ? plan.max_instituciones : null;

    // Cuota
    const badge = $('#instQuotaBadge');
    if (badge) {
        if (max == null) badge.textContent = `${usadas} activas · sin límite`;
        else             badge.textContent = `${usadas} / ${max} activas`;
    }

    // Botón "Nueva" + hint contextual
    const btnNew = $('#btnInstNew');
    const puede = !!_instData.puede_crear;
    if (btnNew) {
        btnNew.disabled = !puede;
        btnNew.style.opacity = puede ? '1' : '.55';
        btnNew.style.cursor = puede ? '' : 'not-allowed';
        btnNew.title = puede ? '' : `Tu plan permite hasta ${max} instituciones activas.`;
    }
    // Hint banner
    let hint = $('#instHint');
    if (!hint) {
        hint = document.createElement('div');
        hint.id = 'instHint';
        hint.style.cssText = 'display:none;margin-top:10px;padding:10px 12px;border-radius:var(--cd-radius);font-size:0.8125rem;border:1px solid;gap:8px;align-items:flex-start';
        const list = $('#instList');
        if (list && list.parentNode) list.parentNode.insertBefore(hint, list);
    }
    if (!puede && max != null) {
        const nativeApp = !!(
            (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) ||
            document.documentElement.dataset.native === '1' ||
            localStorage.getItem('geriappNativeApp') === '1'
        );
        hint.style.display = 'flex';
        hint.style.background = 'rgba(245,158,11,.10)';
        hint.style.borderColor = 'rgba(245,158,11,.35)';
        hint.style.color = '#92400e';
        const actionText = nativeApp
            ? 'Puedes archivar una institución activa para liberar un espacio o contactar al administrador de tu institución.'
            : `Puedes archivar una institución activa para liberar un espacio, o ampliar tu plan desde <a href="javascript:void(0)" onclick="document.querySelector('[data-cfg=\'general\']')?.click?.();" style="color:inherit;text-decoration:underline">Facturación</a> para crear más.`;
        hint.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            <div><strong>Has alcanzado el límite de tu plan.</strong> ${actionText}</div>`;
    } else if (max != null && usadas >= max - 1 && max > 0) {
        // Aviso suave cuando queda 1 espacio
        hint.style.display = 'flex';
        hint.style.background = 'rgba(23,131,145,.08)';
        hint.style.borderColor = 'rgba(23,131,145,.30)';
        hint.style.color = '#033f3f';
        hint.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <div>Te queda <strong>${max - usadas}</strong> institución disponible en tu plan actual.</div>`;
    } else {
        hint.style.display = 'none';
    }

    if (!items.length) {
        list.innerHTML = `<div style="padding:32px;text-align:center;color:var(--cd-text-muted);font-size:0.875rem;background:var(--cd-surface);border:1px dashed var(--cd-border);border-radius:var(--cd-radius)">No tienes instituciones todavía. Crea la primera con el botón "Nueva institución".</div>`;
        return;
    }

    list.innerHTML = items.map(i => {
        const archived = (i.estado === 'archivada');
        const tz = i.timezone || '—';
        const lugar = [i.ciudad, i.estado_geo].filter(Boolean).join(', ');
        // Restricciones por tarjeta
        const restoreBlocked = archived && max != null && usadas >= max;
        const archiveBlocked = !archived && (i.residentes_activos ?? 0) > 0;
        let actBtn;
        if (archived) {
            actBtn = `<button type="button" class="cd-btn-submit cd-btn-secondary" data-action="inst-restore" data-perm-id="cfg_restore_institution_btn" data-id="${i.id}" style="font-size:0.75rem;padding:6px 12px${restoreBlocked?';opacity:.55;cursor:not-allowed':''}" ${restoreBlocked?'disabled':''} title="${restoreBlocked?`Tu plan permite hasta ${max} instituciones activas. Archiva otra primero.`:'Restaurar'}">Restaurar</button>`;
        } else {
            actBtn = `<button type="button" class="cd-btn-submit cd-btn-secondary" data-action="inst-archive" data-perm-id="cfg_archive_institution_btn" data-id="${i.id}" style="font-size:0.75rem;padding:6px 12px;color:var(--cd-danger,#e74c3c)" title="${archiveBlocked?'Esta institución tiene residentes activos.':'Archivar'}">Archivar</button>`;
        }
        // Hint de tarjeta (cuando hay restricciones)
        let cardHint = '';
        if (restoreBlocked) {
            cardHint = `<div style="margin-top:8px;padding:6px 10px;border-radius:8px;background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.30);color:#92400e;font-size:0.72rem;display:flex;align-items:center;gap:6px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                No se puede restaurar: alcanzaste el límite del plan.
            </div>`;
        } else if (archiveBlocked) {
            cardHint = `<div style="margin-top:8px;padding:6px 10px;border-radius:8px;background:rgba(23,131,145,.08);border:1px solid rgba(23,131,145,.30);color:#033f3f;font-size:0.72rem;display:flex;align-items:center;gap:6px">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                Antes de archivar, mueve o egresa los <strong>${i.residentes_activos}</strong> residente(s) activos.
            </div>`;
        }
        return `
        <div class="inst-card" data-id="${i.id}" style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);${archived?'opacity:.65':''}">
            <div style="flex:0 0 44px;height:44px;border-radius:10px;background:rgba(99,102,241,.12);color:#6366f1;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem">
                ${_instEsc((i.nombre||'?').trim().charAt(0).toUpperCase())}
            </div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <strong style="font-size:0.9375rem">${_instEsc(i.nombre)}</strong>
                    ${_instStateBadge(i.estado)}
                </div>
                <div style="font-size:0.75rem;color:var(--cd-text-muted);margin-top:4px;display:flex;gap:12px;flex-wrap:wrap">
                    <span>${_instEsc(i.email_admin || '—')}</span>
                    ${i.telefono ? `<span>· ${_instEsc(i.telefono)}</span>` : ''}
                    ${lugar ? `<span>· ${_instEsc(lugar)}</span>` : ''}
                    <span>· TZ: ${_instEsc(tz)}</span>
                </div>
                <div style="font-size:0.75rem;color:var(--cd-text-muted);margin-top:4px">
                    <span>Residentes activos: <strong style="color:var(--cd-text)">${i.residentes_activos ?? 0}</strong></span>
                    <span style="margin-left:12px">Usuarios: <strong style="color:var(--cd-text)">${i.usuarios_count ?? 0}</strong></span>
                    ${i.num_camas ? `<span style="margin-left:12px">Camas: <strong style="color:var(--cd-text)">${i.num_camas}</strong></span>` : ''}
                </div>
                ${cardHint}
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
                <button type="button" class="cd-btn-submit cd-btn-secondary" data-action="inst-edit" data-perm-id="cfg_edit_institution_btn" data-id="${i.id}" style="font-size:0.75rem;padding:6px 12px">Editar</button>
                ${actBtn}
            </div>
        </div>`;
    }).join('');
}

// Delegated handlers
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action^="inst-"]');
    if (!btn) return;
    const id = parseInt(btn.dataset.id, 10);
    const action = btn.dataset.action;
    if (action === 'inst-edit') return _instOpenModal(id);
    if (action === 'inst-archive') {
        if (!await cdConfirm('¿Archivar esta institución? Sus datos se conservarán y podrás restaurarla después.', { type: 'warn', okText: 'Archivar' })) return;
        try {
            await api(INST_API + '?action=archive', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
            showToast('Institución archivada', 'success');
            await loadInstituciones(true);
        } catch(err) { showToast(err?.message || 'Error', 'error'); }
        return;
    }
    if (action === 'inst-restore') {
        try {
            await api(INST_API + '?action=restore', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
            showToast('Institución restaurada', 'success');
            await loadInstituciones(true);
        } catch(err) { showToast(err?.message || 'Error', 'error'); }
        return;
    }
});

// Modal open/close
function _instOpenModal(id) {
    const ov = $('#instModalOverlay'); if (!ov) return;
    const form = $('#instForm');
    form.reset();
    $('#instId').value = id || '';
    $('#instFormErr').style.display = 'none';
    $('#instModalTitle').textContent = id ? 'Editar institución' : 'Nueva institución';

    if (id) {
        const i = (_instData.instituciones || []).find(x => +x.id === +id);
        if (i) {
            $('#instNombre').value     = i.nombre || '';
            $('#instEmail').value      = i.email_admin || '';
            $('#instTel').value        = i.telefono || '';
            $('#instDir').value        = i.direccion || '';
            $('#instCiudad').value     = i.ciudad || '';
            $('#instEstadoGeo').value  = i.estado_geo || '';
            $('#instTz').value         = i.timezone || 'America/Mexico_City';
            $('#instCamas').value      = i.num_camas || '';
            $('#instRfc').value        = i.rfc || '';
        }
    }
    ov.classList.add('show');
    setTimeout(() => $('#instNombre')?.focus(), 50);
}
function _instCloseModal() { $('#instModalOverlay')?.classList.remove('show'); }

$('#btnInstNew')?.addEventListener('click', () => _instOpenModal(null));
$('#instCancelBtn')?.addEventListener('click', _instCloseModal);
$('#instModalClose')?.addEventListener('click', _instCloseModal);

// ═══════════════════════════════════════════════
// PERMISOS DE ELEMENTOS UI  (solo superadmin)
// ═══════════════════════════════════════════════

/** Working copy of perm_elements during the session — keyed by perm-id */
let _peState = {};
/** perm-ids discovered by scanning the codebase (via API) */
let _peDiscovered = [];

/** All available roles */
const PE_ROLES = ['familiar', 'enfermero', 'medico', 'admin', 'superadmin'];

/** Role display labels */
const PE_ROLE_LABELS = {
    familiar:    'Familiar',
    enfermero:   'Enfermero/a',
    medico:      'Médico/a',
    admin:       'Admin',
    superadmin:  'Superadmin',
};

/**
 * Renders the saved perm_elements as editable rows.
 * Called by populateConfigForms + _reloadSection.
 * @param {Object|string|null} raw - value of perm_elements from config API
 */
function _populatePermElementsGrid(raw) {
    const grid = $('#cdPermElementsGrid');
    if (!grid) return;

    if (raw && typeof raw === 'string') {
        try { _peState = JSON.parse(raw); } catch(e) { _peState = {}; }
    } else if (raw && typeof raw === 'object' && raw !== null) {
        _peState = { ...raw };
    } else {
        _peState = {};
    }

    _renderPeGrid();
}

/** Re-renders the grid from _peState (called after edits) */
function _renderPeGrid() {
    const grid = $('#cdPermElementsGrid');
    if (!grid) return;

    const entries = Object.keys(_peState);

    if (!entries.length) {
        grid.innerHTML = `<div class="cd-pe-empty">
            <span class="material-symbols-outlined">tune</span>
            <p>No hay ningún perm-id registrado aún.</p>
            <p class="cd-pe-empty-hint">Haz clic en <strong>+ Agregar elemento</strong> para registrar el primero.</p>
        </div>`;
        return;
    }

    let html = '<div class="cd-pe-cards">';
    entries.forEach(permId => {
        const roles = _peState[permId] || [];
        const chips = PE_ROLES.map(r =>
            `<span class="cd-pe-role-chip ${roles.includes(r) ? 'cd-pe-role-chip--on' : 'cd-pe-role-chip--off'}">${PE_ROLE_LABELS[r]}</span>`
        ).join('');
        html += `<div class="cd-pe-card">
            <div class="cd-pe-card-head">
                <code class="cd-pe-card-id-code">${_escHtml(permId)}</code>
                <div class="cd-pe-card-actions">
                    <button class="cd-pe-card-btn" data-perm-edit="${_escHtml(permId)}" type="button" title="Editar roles">
                        <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                    </button>
                    <button class="cd-pe-card-btn cd-pe-card-btn--del" data-perm-del="${_escHtml(permId)}" type="button" title="Eliminar">
                        <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                    </button>
                </div>
            </div>
            <div class="cd-pe-card-chips">${chips}</div>
        </div>`;
    });
    html += '</div>';
    grid.innerHTML = html;
}

function _escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/** Scans the codebase for data-perm-id values via API. Cached. */
async function _peLoadDiscovered() {
    if (_peDiscovered.length) return _peDiscovered;
    try {
        const r = await api(CFG_API + '?action=scan_perm_ids');
        _peDiscovered = (r.perm_ids || []).map(i => i.id);
    } catch(e) {
        _peDiscovered = [];
    }
    return _peDiscovered;
}

/** Opens the "Agregar elemento" drawer */
async function _openAddPermElementDrawer() {
    const scanBtn = $('#cdPeAddBtn');
    if (scanBtn) { scanBtn.disabled = true; scanBtn.textContent = 'Escaneando…'; }

    const discovered = await _peLoadDiscovered();

    if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">add</span> Agregar elemento'; }

    // Build datalist options (exclude already-saved ids)
    const available = discovered.filter(id => !_peState.hasOwnProperty(id));
    const options   = available.map(id => `<option value="${_escHtml(id)}">`).join('');

    const body = `
        <div class="cd-pe-drawer">
            <p class="cd-pe-drawer-hint">
                Ingresa o selecciona el <code>data-perm-id</code> del elemento HTML que quieres controlar.
                Luego elige qué roles pueden verlo y usarlo.
            </p>
            <div class="cd-form-group">
                <label class="cd-form-label cd-required">perm-id</label>
                <input class="cd-input" id="cdPeNewId" list="cdPeIdList" placeholder="ej. res_subcard_nm_btn"
                    autocomplete="off" spellcheck="false">
                <datalist id="cdPeIdList">${options}</datalist>
                <span class="cd-pe-drawer-field-hint">
                    ${available.length} elemento${available.length !== 1 ? 's' : ''} encontrado${available.length !== 1 ? 's' : ''} en el código.
                    ${!available.length && !discovered.length ? 'No se encontraron <code>data-perm-id</code> en el código.' : ''}
                </span>
            </div>
            <div class="cd-pe-drawer-roles" id="cdPeDrawerRoles">
                ${PE_ROLES.map(r => {
                    const isSuper = r === 'superadmin';
                    return `<label class="cd-pe-drawer-role ${isSuper ? 'cd-pe-drawer-role--disabled' : ''}">
                        <input type="checkbox" name="pe_role" value="${r}" ${isSuper ? 'disabled checked' : ''}>
                        <span class="cd-pe-drawer-role-label">${PE_ROLE_LABELS[r]}</span>
                        ${isSuper ? '<span class="cd-pe-drawer-role-note">Siempre habilitado</span>' : ''}
                    </label>`;
                }).join('')}
            </div>
        </div>`;

    const actions = `
        <button class="cd-btn cd-btn-secondary" id="cdPeDrawerCancel" type="button">Cancelar</button>
        <button class="cd-btn cd-btn-primary" id="cdPeDrawerApply" type="button">
            <span class="material-symbols-outlined" aria-hidden="true">add</span>Agregar
        </button>`;

    openSidebar('Agregar elemento con perm-id', body, actions);

    $('#cdPeDrawerCancel')?.addEventListener('click', closeSidebar);
    $('#cdPeDrawerApply')?.addEventListener('click', () => {
        const pid = $('#cdPeNewId')?.value?.trim();
        if (!pid) { showToast('Ingresa un perm-id.', 'warning'); return; }
        if (_peState.hasOwnProperty(pid)) { showToast('Este perm-id ya está registrado.', 'warning'); return; }
        const checked = [...$$('#cdPeDrawerRoles input[type="checkbox"]')]
            .filter(cb => cb.checked).map(cb => cb.value);
        if (!checked.includes('superadmin')) checked.push('superadmin');
        _peState[pid] = checked;
        _renderPeGrid();
        closeSidebar();
        showToast(`"${pid}" agregado. Guarda para aplicar.`, 'success');
    });
}

/** Opens the edit roles drawer for an existing perm-id */
function _openEditPermElementDrawer(permId) {
    const current = _peState[permId] || ['superadmin'];

    const body = `
        <div class="cd-pe-drawer">
            <div class="cd-pe-drawer-item-head">
                <span class="material-symbols-outlined cd-pe-drawer-icon" aria-hidden="true">tune</span>
                <code class="cd-pe-drawer-id">${_escHtml(permId)}</code>
            </div>
            <p class="cd-pe-drawer-hint">
                Selecciona los roles que pueden <strong>ver y usar</strong> este elemento.
                Los roles no seleccionados verán el elemento bloqueado con un candado.
            </p>
            <div class="cd-pe-drawer-roles" id="cdPeDrawerRoles">
                ${PE_ROLES.map(r => {
                    const checked  = current.includes(r) ? 'checked' : '';
                    const isSuper  = r === 'superadmin';
                    return `<label class="cd-pe-drawer-role ${isSuper ? 'cd-pe-drawer-role--disabled' : ''}">
                        <input type="checkbox" name="pe_role" value="${r}" ${checked} ${isSuper ? 'disabled checked' : ''}>
                        <span class="cd-pe-drawer-role-label">${PE_ROLE_LABELS[r]}</span>
                        ${isSuper ? '<span class="cd-pe-drawer-role-note">Siempre habilitado</span>' : ''}
                    </label>`;
                }).join('')}
            </div>
        </div>`;

    const actions = `
        <button class="cd-btn cd-btn-secondary" id="cdPeDrawerCancel" type="button">Cancelar</button>
        <button class="cd-btn cd-btn-primary" id="cdPeDrawerApply" type="button" data-perm-id="${_escHtml(permId)}">
            <span class="material-symbols-outlined" aria-hidden="true">check</span>Aplicar
        </button>`;

    openSidebar(`Editar: ${permId}`, body, actions);

    $('#cdPeDrawerCancel')?.addEventListener('click', closeSidebar);
    $('#cdPeDrawerApply')?.addEventListener('click', () => {
        const pid = $('#cdPeDrawerApply').dataset.permId;
        const checked = [...$$('#cdPeDrawerRoles input[type="checkbox"]')]
            .filter(cb => cb.checked).map(cb => cb.value);
        if (!checked.includes('superadmin')) checked.push('superadmin');
        _peState[pid] = checked;
        _renderPeGrid();
        closeSidebar();
    });
}

// Event delegation for grid buttons
$('#cdPermElementsGrid')?.addEventListener('click', e => {
    const editBtn = e.target.closest('[data-perm-edit]');
    if (editBtn) { e.stopPropagation(); _openEditPermElementDrawer(editBtn.dataset.permEdit); return; }
    const delBtn = e.target.closest('[data-perm-del]');
    if (delBtn) {
        e.stopPropagation();
        const pid = delBtn.dataset.permDel;
        if (!confirm(`¿Eliminar perm-id "${pid}"?\nEl elemento volverá a ser accesible por todos.`)) return;
        delete _peState[pid];
        _renderPeGrid();
    }
});

// "Agregar elemento" button
$('#cdPeAddBtn')?.addEventListener('click', () => _openAddPermElementDrawer());

// Save button
$('#cfgPeSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgPeSave');
    btnLoading(_btn, t('status_saving'));
    try {
        await api(CFG_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'roles', perm_elements: _peState }),
        });
        showToast('Permisos de elementos guardados.', 'success');
        await _reloadSection('permisos_ui');
    } catch(e) {}
    btnReset(_btn);
});
$('#instModalOverlay')?.addEventListener('click', (e) => {
    if (e.target.id === 'instModalOverlay') _instCloseModal();
});

$('#instForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = $('#instId').value;
    const errBox = $('#instFormErr'); errBox.style.display = 'none';
    const payload = {
        nombre:      $('#instNombre').value.trim(),
        email_admin: $('#instEmail').value.trim(),
        telefono:    $('#instTel').value.trim(),
        direccion:   $('#instDir').value.trim(),
        ciudad:      $('#instCiudad').value.trim(),
        estado_geo:  $('#instEstadoGeo').value.trim(),
        timezone:    $('#instTz').value,
        num_camas:   $('#instCamas').value ? parseInt($('#instCamas').value,10) : null,
        rfc:         $('#instRfc').value.trim(),
    };
    if (id) payload.id = parseInt(id, 10);

    const _btn = $('#instSaveBtn');
    btnLoading?.(_btn);
    try {
        const url = id
            ? (INST_API + '?action=update')
            : (INST_API + '?action=create');
        await api(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        showToast(id ? 'Institución actualizada' : 'Institución creada', 'success');
        _instCloseModal();
        await loadInstituciones(true);
    } catch(err) {
        const msg = err?.message || 'Error al guardar';
        errBox.textContent = msg;
        errBox.style.display = 'block';
    } finally {
        btnReset?.(_btn);
    }
});



