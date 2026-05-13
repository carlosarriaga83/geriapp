// cd-family.js — Family contacts, ficha tabs, notifications sub-tabs
// Extracted from cuidados.php (lines 4142)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// FAMILY CONTACTS (multiple)
// ═══════════════════════════════════════════════
function renderFamilyContacts(r) {
    const container = $('#cdResFamilyContainer');
    if (!container) return;
    let contacts = [];
    // Try reading all contacts from contactos_json first (new format stores all)
    if (r.contactos_json) {
        try {
            const parsed = JSON.parse(r.contactos_json);
            if (Array.isArray(parsed) && parsed.length) {
                parsed = parsed.filter(c => c && c.source !== 'staff_notif' && !c.__notifStaff);
                // Check if first entry has notif_* keys (new format) or is extra-only (old format)
                if (parsed[0].notif_stock !== undefined || parsed[0].notif_wa !== undefined) {
                    contacts = parsed;
                } else {
                    // Old format: JSON has only extra contacts, primary is in flat fields
                    const primary = {
                        nombre: r.contacto_nombre || r.familiar_nombre || '',
                        parentesco: r.contacto_parentesco || '',
                        telefono: r.contacto_telefono || r.familiar_telefono || '',
                        telefono2: r.contacto_telefono2 || '',
                        email: r.contacto_email || r.familiar_email || '',
                        direccion: r.contacto_direccion || '',
                        notif_stock: 0, notif_reporte: 0, notif_reporte_hora: '08:00', notif_reporte_pdf: 0,
                        notif_signos: 0, notif_incidentes: 0, notif_medicacion: 0, notif_alimentacion: 0,
                        notif_animo: 0, notif_wa: 0, notif_email: 0
                    };
                    if (Object.values(primary).some(v => v && v !== 0)) contacts.push(primary);
                    contacts.push(...parsed);
                }
            }
        } catch(e) {}
    }
    // Fallback: build primary from flat fields (no JSON or empty)
    if (!contacts.length) {
        const primary = {
            nombre: r.contacto_nombre || r.familiar_nombre || '',
            parentesco: r.contacto_parentesco || '',
            telefono: r.contacto_telefono || r.familiar_telefono || '',
            telefono2: r.contacto_telefono2 || '',
            email: r.contacto_email || r.familiar_email || '',
            direccion: r.contacto_direccion || '',
            notif_stock: 0, notif_reporte: 0, notif_reporte_hora: '08:00', notif_reporte_pdf: 0,
            notif_signos: 0, notif_incidentes: 0, notif_medicacion: 0, notif_alimentacion: 0,
            notif_animo: 0, notif_wa: 0, notif_email: 0
        };
        if (Object.values(primary).some(v => v && v !== 0)) contacts.push(primary);
    }
    if (!contacts.length) contacts.push({nombre:'',parentesco:'',telefono:'',telefono2:'',email:'',direccion:''});

    // ── Dedupe contactos_json itself (defensive: previous saves may have created duplicates) ──
    {
        const seen = new Set();
        contacts = contacts.filter(c => {
            const sig = c._usuario_id
                ? 'u:' + c._usuario_id
                : ((c.email||'').trim().toLowerCase()
                    || (((c.nombre||'').trim().toLowerCase()) + '|' + ((c.telefono||'').trim())));
            if (!sig) return true; // truly empty placeholder, keep
            if (seen.has(sig)) return false;
            seen.add(sig);
            return true;
        });
    }

    // ── Merge linked familiar users (from usuario_residentes) ──
    const famUsers = r.familiares_usuarios || [];
    if (famUsers.length) {
        const existingEmails = new Set(contacts.map(c => (c.email||'').toLowerCase()).filter(Boolean));
        const existingUsuarioIds = new Set(contacts.map(c => c._usuario_id).filter(Boolean));
        famUsers.forEach(fu => {
            const fuEmail = (fu.email || '').toLowerCase();
            // 1) Match by usuario_id (most reliable)
            if (existingUsuarioIds.has(fu.usuario_id)) return;
            // 2) Match by email
            if (fuEmail && existingEmails.has(fuEmail)) {
                const match = contacts.find(c => (c.email||'').toLowerCase() === fuEmail);
                if (match) {
                    match._usuario_id = fu.usuario_id;
                    existingUsuarioIds.add(fu.usuario_id);
                }
                return;
            }
            // 3) Match by name (fallback when no email/usuario_id link exists)
            const fuName = (fu.nombre || '').trim().toLowerCase();
            if (fuName) {
                const nameMatch = contacts.find(c => !c._usuario_id && (c.nombre||'').trim().toLowerCase() === fuName);
                if (nameMatch) {
                    nameMatch._usuario_id = fu.usuario_id;
                    if (fu.email && !nameMatch.email) nameMatch.email = fu.email;
                    if (fu.telefono && !nameMatch.telefono) nameMatch.telefono = fu.telefono;
                    existingUsuarioIds.add(fu.usuario_id);
                    if (fuEmail) existingEmails.add(fuEmail);
                    return;
                }
            }
            // 4) No match anywhere — auto-inject as a new contact
            contacts.push({
                nombre: fu.nombre || '',
                parentesco: 'Familiar',
                telefono: fu.telefono || '',
                telefono2: '',
                email: fu.email || '',
                direccion: '',
                _usuario_id: fu.usuario_id,
                notif_stock: 0, notif_reporte: 0, notif_reporte_hora: '08:00', notif_reporte_pdf: 0,
                notif_signos: 0, notif_incidentes: 0, notif_medicacion: 0, notif_alimentacion: 0,
                notif_animo: 0, notif_wa: 0, notif_email: 0
            });
            existingUsuarioIds.add(fu.usuario_id);
            if (fuEmail) existingEmails.add(fuEmail);
        });
        // Remove the blank placeholder if we injected real contacts
        if (contacts.length > 1 && !contacts[0].nombre && !contacts[0].email && !contacts[0].telefono) {
            contacts.shift();
        }
    }

    const famInvites = Array.isArray(r.familiares_invitaciones)
        ? r.familiares_invitaciones
        : (Array.isArray(r.familiares_panel) ? r.familiares_panel.filter(f => f?.source === 'invitacion' || f?.invitacion_id) : []);
    if (famInvites.length) {
        const existingKeys = new Set(contacts.map(c => {
            if (c._invitacion_id) return 'i:' + c._invitacion_id;
            const email = (c.email || '').trim().toLowerCase();
            if (email) return 'e:' + email;
            const tel = (c.telefono || '').replace(/\D+/g, '');
            if (tel) return 't:' + tel;
            return '';
        }).filter(Boolean));
        famInvites.forEach(invRow => {
            if (invRow.usuario_id) return;
            const inv = invRow.invitacion || invRow;
            const email = (invRow.email || inv.email || '').trim().toLowerCase();
            const tel = (invRow.telefono || inv.telefono || '').replace(/\D+/g, '');
            const key = invRow.invitacion_id ? 'i:' + invRow.invitacion_id : (email ? 'e:' + email : (tel ? 't:' + tel : ''));
            if (key && existingKeys.has(key)) return;
            contacts.push({
                nombre: invRow.nombre || [inv.nombre_sugerido || '', inv.apellido_sugerido || ''].join(' ').trim() || '',
                parentesco: invRow.parentesco || 'Familiar',
                telefono: invRow.telefono || inv.telefono || '',
                telefono2: '',
                email: invRow.email || inv.email || '',
                direccion: '',
                _invitacion_id: invRow.invitacion_id || inv.id || '',
                _pending_invite: true,
                notif_stock: 0, notif_reporte: 0, notif_reporte_hora: '08:00', notif_reporte_pdf: 0,
                notif_signos: 0, notif_incidentes: 0, notif_medicacion: 0, notif_alimentacion: 0,
                notif_animo: 0, notif_wa: 0, notif_email: 0
            });
            if (key) existingKeys.add(key);
        });
        if (contacts.length > 1 && !contacts[0].nombre && !contacts[0].email && !contacts[0].telefono) {
            contacts.shift();
        }
    }

    // Sort: principal contact first
    contacts.sort((a, b) => (b.principal ? 1 : 0) - (a.principal ? 1 : 0));

    container.innerHTML = '';
    contacts.forEach((c, idx) => {
        container.appendChild(_createContactBlock(c, idx, contacts.length));
    });
    _checkWaBadges();
    // Store contacts for notification tab
    _notifContacts = contacts.map(c => ({...c}));
    _updateNotifRecipients(contacts);
    _populateNotifContactSelect();
}

// ── Ficha tab switching ─────────────────────────────────
function _positionFichaPill() {
    const container = document.querySelector('.cd-ficha-tabs');
    if (!container) return;
    const pill = container.querySelector(':scope > .cd-tabs-pill');
    if (!pill) return;
    const active = container.querySelector(':scope > .cd-ficha-tab.active:not([style*="display: none"])');
    if (!active || !active.offsetWidth) { pill.style.opacity = '0'; return; }
    pill.style.opacity = '1';
    pill.style.width = active.offsetWidth + 'px';
    pill.style.transform = `translateX(${active.offsetLeft}px)`;
}
function _positionNotifSubtabs() {
    const tabsEl = document.getElementById('cdNotifSubTabs');
    if (!tabsEl) return;
    const pill = tabsEl.querySelector(':scope > .cd-res-subtabs-pill');
    const active = tabsEl.querySelector(':scope > .cd-notif-tab.is-active, :scope > .cd-notif-tab.active');
    if (!pill || !active || !active.offsetWidth) { if (pill) pill.style.opacity = '0'; return; }
    pill.style.opacity = '1';
    pill.style.width = active.offsetWidth + 'px';
    pill.style.transform = `translateX(${active.offsetLeft}px)`;
}
window.addEventListener('resize', () => requestAnimationFrame(_positionFichaPill));
window.addEventListener('resize', () => requestAnimationFrame(_positionNotifSubtabs));
$$('.cd-ficha-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        $$('.cd-ficha-tab').forEach(t => t.classList.remove('active'));
        $$('.cd-ficha-tab-panel').forEach(p => { p.classList.remove('active'); p.classList.remove('cd-fade-up-in'); });
        tab.classList.add('active');
        const panel = $(`.cd-ficha-tab-panel[data-ficha-panel="${tab.dataset.fichaTab}"]`);
        if (panel) {
            panel.classList.add('active');
            void panel.offsetWidth;
            panel.classList.add('cd-fade-up-in');
            panel.addEventListener('animationend', function h() { panel.removeEventListener('animationend', h); panel.classList.remove('cd-fade-up-in'); }, { once: true });
        }
        _positionFichaPill();
        if (tab.dataset.fichaTab === 'notif') {
            requestAnimationFrame(_positionNotifSubtabs);
            _loadNotifLog();
            _checkNotifAlerts();
            _populateNotifContactSelect();
        }
    });
});

// ── Notification sub-tab switching ─────────────────────────
$('#cdNotifSubTabs')?.addEventListener('click', e => {
    const tab = e.target.closest('.cd-notif-tab');
    if (!tab) return;
    $$('.cd-notif-tab', e.currentTarget).forEach(t => t.classList.remove('active', 'is-active'));
    tab.classList.add('active', 'is-active');
    $$('.cd-notif-tab-panel').forEach(p => p.classList.remove('active'));
    const panel = $(`.cd-notif-tab-panel[data-ntab-panel="${tab.dataset.ntab}"]`);
    if (panel) panel.classList.add('active');
    _positionNotifSubtabs();
    if (tab.dataset.ntab === 'log') _loadNotifLog();
});

function _updateNotifRecipients(contacts) {
    const container = $('#cdNotifRecipients');
    if (!container) return;
    const filtered = contacts.filter(c => c.nombre && (c.telefono || c.email));
    if (!filtered.length) {
        container.innerHTML = '<p style="font-size:0.8125rem;color:var(--cd-text-muted)">' + t('empty_no_contacts') + '</p>';
        return;
    }
    container.innerHTML = filtered.map((c, i) => {
        const hasPhone = !!c.telefono;
        const hasEmail = !!c.email;
        return `<label class="cd-notif-recipient">
            <input type="checkbox" value="${i}" checked data-phone="${esc(c.telefono||'')}" data-email="${esc(c.email||'')}">
            <span class="cd-notif-recipient-name">${esc(c.nombre)}${c.parentesco ? ' <small>('+esc(c.parentesco)+')</small>' : ''}</span>
            <span class="cd-notif-recipient-channels">
                ${hasPhone ? '<span class="cd-notif-ch cd-notif-ch-wa" title="WhatsApp disponible">WA</span>' : ''}
                ${hasEmail ? '<span class="cd-notif-ch cd-notif-ch-em" title="Email disponible">✉</span>' : ''}
            </span>
        </label>`;
    }).join('');
}

// Show/hide custom message textarea
$$('input[name="cdNotifType"]').forEach(r => {
    r.addEventListener('change', () => {
        const custom = $('#cdNotifCustomMsg');
        if (custom) custom.style.display = r.value === 'personalizado' && r.checked ? '' : 'none';
    });
});

let _notifAlertItems = []; // store alerts for sidebar

function _checkNotifAlerts() {
    const alertsEl = $('#cdNotifAlerts');
    if (!alertsEl) return;
    const alerts = [];
    if (_invItems && _invItems.length) {
        const lowStock = _invItems.filter(it => it.stock_actual !== null && it.stock_actual <= (it.stock_minimo || 5));
        lowStock.forEach(it => {
            alerts.push({type: 'stock_bajo', icon: '⚠️', text: `Stock bajo: ${it.nombre} (${it.stock_actual} unidades)`, severity: 'warning'});
        });
    }
    _notifAlertItems = alerts;
    if (!alerts.length) {
        alertsEl.innerHTML = '<p class="cd-notif-empty"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <?= t('notif_no_alerts') ?></p>';
        const badge = $('#cdNotifBadge');
        if (badge) badge.style.display = 'none';
        return;
    }
    alertsEl.innerHTML = `<a href="#" class="cd-notif-alert-link" id="cdNotifAlertLink"><span class="cd-notif-alert-link-icon">⚠️</span> <span>${alerts.length} ${alerts.length === 1 ? t('notif_alert_singular') : t('notif_alert_plural')}</span></a>`;
    $('#cdNotifAlertLink')?.addEventListener('click', e => {
        e.preventDefault();
        _openNotifAlertsSidebar();
    });
    const badge = $('#cdNotifBadge');
    if (badge) { badge.textContent = alerts.length; badge.style.display = 'inline-flex'; }
}

function _openNotifAlertsSidebar() {
    if (!_notifAlertItems.length) return;
    const body = _notifAlertItems.map(a =>
        `<div class="cd-notif-alert cd-notif-alert-${a.severity}"><span>${a.icon}</span><span>${esc(a.text)}</span></div>`
    ).join('');
    openSidebar(t('notif_alerts_title'), body, `<button class="cd-btn-submit cd-btn-secondary" onclick="closeSidebar()">${t('btn_close')}</button>`);
}

// ── Notification contact list + switches ─────────────────
// El UI es una lista de cards (un renglón por contacto, expandible). Por
// compatibilidad con el resto del JS se conserva un <select> oculto
// (#cdNotifContactSelect) que sigue siendo la "fuente de verdad" del
// contacto activo; los cards solo lo manipulan.
let _notifContacts = []; // populated from renderFamilyContacts

function _contactInitial(c) {
    const n = (c?.nombre || '').trim();
    if (!n) return '?';
    return n.charAt(0).toUpperCase();
}

function _populateNotifContactSelect() {
    const sel = $('#cdNotifContactSelect');
    if (!sel) return;
    sel.innerHTML = '<option value=""></option>';
    _notifContacts.forEach((c, i) => {
        if (!c.nombre) return;
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = c.nombre + (c.parentesco ? ` (${c.parentesco})` : '');
        sel.appendChild(opt);
    });
    const addOpt = document.createElement('option');
    addOpt.value = '__add__';
    addOpt.textContent = t('btn_add_family');
    sel.appendChild(addOpt);
    _renderNotifMembersList();
    _showNotifSwitches();
}

function _renderNotifMembersList() {
    const list = $('#cdNotifMembersList');
    if (!list) return;
    const empty = $('#cdNotifMembersEmpty');
    const sel = $('#cdNotifContactSelect');
    const activeIdx = sel?.value;

    // Preservar el panel de switches (es singleton) si ya está dentro de la lista
    const switchesPanel = $('#cdNotifSwitchesPanel');
    if (switchesPanel && switchesPanel.parentElement && switchesPanel.parentElement.classList.contains('cd-notif-member-body')) {
        // Sacarlo temporalmente al .cd-res-section-card para no perderlo al re-renderizar
        const card = list.parentElement;
        if (card) card.appendChild(switchesPanel);
    }

    list.innerHTML = '';
    const validContacts = _notifContacts.filter(c => c.nombre);
    if (empty) empty.style.display = validContacts.length ? 'none' : '';

    _notifContacts.forEach((c, i) => {
        if (!c.nombre) return;
        const row = document.createElement('div');
        row.className = 'cd-notif-member';
        row.dataset.idx = String(i);
        const channels = [
            c.notif_wa    ? 'WhatsApp' : null,
            c.notif_email ? 'Email'    : null,
        ].filter(Boolean).join(' · ') || (t('notif_no_channels') || 'Sin canales');
        row.innerHTML = `
            <button type="button" class="cd-notif-member-head" aria-expanded="false">
                <span class="cd-notif-member-avatar">${esc(_contactInitial(c))}</span>
                <span class="cd-notif-member-info">
                    <span class="cd-notif-member-name">${esc(c.nombre)}${c.parentesco ? ` <span class="cd-notif-member-rel">· ${esc(c.parentesco)}</span>` : ''}</span>
                    <span class="cd-notif-member-sub">${esc(channels)}</span>
                </span>
                <svg class="cd-notif-member-chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="cd-notif-member-body" hidden></div>
        `;
        list.appendChild(row);
    });

    // Add-contact row
    const addRow = document.createElement('button');
    addRow.type = 'button';
    addRow.className = 'cd-notif-member cd-notif-member--add';
    addRow.innerHTML = `
        <span class="cd-notif-member-head">
            <span class="cd-notif-member-avatar cd-notif-member-avatar--add">+</span>
            <span class="cd-notif-member-info">
                <span class="cd-notif-member-name">${esc(t('btn_add_family'))}</span>
            </span>
        </span>
    `;
    addRow.addEventListener('click', () => {
        const famTab = document.querySelector('.cd-ficha-tab[data-ficha-tab="familia"]');
        if (famTab) famTab.click();
        setTimeout(() => {
            if (!_famEditing) $('#cdResFamEditBtn')?.click();
            setTimeout(() => $('#cdAddContactBtn')?.click(), 150);
        }, 100);
    });
    list.appendChild(addRow);

    // Click en cabecera = abrir/cerrar
    list.querySelectorAll('.cd-notif-member:not(.cd-notif-member--add) .cd-notif-member-head').forEach(head => {
        head.addEventListener('click', () => {
            const row = head.closest('.cd-notif-member');
            const idx = row.dataset.idx;
            const sel = $('#cdNotifContactSelect');
            if (!sel) return;
            const wasOpen = row.classList.contains('is-open');
            // Cerrar todos
            list.querySelectorAll('.cd-notif-member.is-open').forEach(r => {
                r.classList.remove('is-open');
                const h = r.querySelector('.cd-notif-member-head');
                if (h) h.setAttribute('aria-expanded', 'false');
                const b = r.querySelector('.cd-notif-member-body');
                if (b) b.hidden = true;
            });
            if (wasOpen) {
                sel.value = '';
                _showNotifSwitches();
                return;
            }
            sel.value = idx;
            row.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            const body = row.querySelector('.cd-notif-member-body');
            const panel = $('#cdNotifSwitchesPanel');
            if (body && panel) {
                body.appendChild(panel);
                body.hidden = false;
            }
            _showNotifSwitches();
            // Trigger 'change' for cualquier listener legado
            sel.dispatchEvent(new Event('change'));
        });
    });
}

// ── Multi-schedule hours for daily report ──────────────────────────────
let _reporteHoras = ['08:00'];
function _parseReporteHoras(val) {
    if (!val) return ['08:00'];
    if (typeof val === 'string') {
        try { const arr = JSON.parse(val); if (Array.isArray(arr)) return arr.length ? arr : ['08:00']; } catch(e) {}
        return [val]; // legacy single value
    }
    if (Array.isArray(val)) return val.length ? val : ['08:00'];
    return ['08:00'];
}
function _getReporteHoras() { return _reporteHoras.length ? _reporteHoras : ['08:00']; }
function _renderReporteHoras(horas) {
    _reporteHoras = horas;
    const list = $('#cdNotifReporteHorasList');
    if (!list) return;
    list.innerHTML = horas.map((h, i) => `<span class="cd-notif-hora-chip">${esc(h)}<button type="button" data-idx="${i}" class="cd-notif-hora-remove">&times;</button></span>`).join('');
    list.querySelectorAll('.cd-notif-hora-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = parseInt(btn.dataset.idx);
            const arr = [..._reporteHoras];
            arr.splice(idx, 1);
            _renderReporteHoras(arr.length ? arr : ['08:00']);
        });
    });
}
$('#cdNotifAddHora')?.addEventListener('click', () => {
    const inp = $('#cdNotifReporteHoraInput');
    const val = inp?.value;
    if (!val) return;
    if (_reporteHoras.includes(val)) { showToast('Esa hora ya está agregada', 'warning'); return; }
    _renderReporteHoras([..._reporteHoras, val].sort());
});
$('#cdNotifSwReporte')?.addEventListener('change', () => {
    const wrap = $('#cdNotifReporteHorasWrap');
    if (wrap) wrap.style.display = $('#cdNotifSwReporte').checked ? '' : 'none';
});

// Toggle visibility of quiet-hours time inputs
$('#cdNotifSwQuiet')?.addEventListener('change', () => {
    const wrap = $('#cdNotifQuietWrap');
    if (wrap) wrap.style.display = $('#cdNotifSwQuiet').checked ? '' : 'none';
});

function _showNotifSwitches() {
    const sel = $('#cdNotifContactSelect');
    const panel = $('#cdNotifSwitchesPanel');
    if (!sel || !panel) return;
    const idx = sel.value;
    if (idx === '' || !_notifContacts[idx]) {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = '';
    const c = _notifContacts[idx];
    $('#cdNotifSwStock').checked = !!c.notif_stock;
    $('#cdNotifSwReporte').checked = !!c.notif_reporte;
    // Multi-schedule hours
    const horasWrap = $('#cdNotifReporteHorasWrap');
    horasWrap.style.display = c.notif_reporte ? '' : 'none';
    _renderReporteHoras(_parseReporteHoras(c.notif_reporte_hora));
    $('#cdNotifSwReportePdf').checked = !!c.notif_reporte_pdf;
    $('#cdNotifSwSignos').checked = !!c.notif_signos;
    $('#cdNotifSwIncidentes').checked = !!c.notif_incidentes;
    $('#cdNotifSwMedicacion').checked = !!c.notif_medicacion;
    $('#cdNotifSwAlimentacion').checked = !!c.notif_alimentacion;
    $('#cdNotifSwAnimo').checked = !!c.notif_animo;
    // ─ Enriched preferences ─
    if ($('#cdNotifSwEmergencia'))    $('#cdNotifSwEmergencia').checked    = c.notif_emergencia    !== undefined ? !!c.notif_emergencia    : true;
    if ($('#cdNotifSwCaida'))         $('#cdNotifSwCaida').checked         = c.notif_caida         !== undefined ? !!c.notif_caida         : true;
    if ($('#cdNotifSwMedOmitida'))    $('#cdNotifSwMedOmitida').checked    = !!c.notif_med_omitida;
    if ($('#cdNotifSwHigiene'))       $('#cdNotifSwHigiene').checked       = !!c.notif_higiene;
    if ($('#cdNotifSwEliminacion'))   $('#cdNotifSwEliminacion').checked   = !!c.notif_eliminacion;
    if ($('#cdNotifSwSueno'))         $('#cdNotifSwSueno').checked         = !!c.notif_sueno;
    if ($('#cdNotifSwMovilidad'))     $('#cdNotifSwMovilidad').checked     = !!c.notif_movilidad;
    if ($('#cdNotifSwTerapia'))       $('#cdNotifSwTerapia').checked       = !!c.notif_terapia;
    if ($('#cdNotifSwSemanal'))       $('#cdNotifSwSemanal').checked       = !!c.notif_semanal;
    if ($('#cdNotifSwNotasMedico'))   $('#cdNotifSwNotasMedico').checked   = !!c.notif_notas_medico;
    if ($('#cdNotifSwVisitas'))       $('#cdNotifSwVisitas').checked       = !!c.notif_visitas;
    if ($('#cdNotifSwCriticasOnly'))  $('#cdNotifSwCriticasOnly').checked  = !!c.notif_solo_criticas;
    if ($('#cdNotifSwQuiet'))         $('#cdNotifSwQuiet').checked         = !!c.notif_quiet_enabled;
    if ($('#cdNotifQuietStart'))      $('#cdNotifQuietStart').value        = c.notif_quiet_start || '22:00';
    if ($('#cdNotifQuietEnd'))        $('#cdNotifQuietEnd').value          = c.notif_quiet_end   || '07:00';
    const quietWrap = $('#cdNotifQuietWrap');
    if (quietWrap) quietWrap.style.display = c.notif_quiet_enabled ? '' : 'none';
    $('#cdNotifSwWa').checked = !!c.notif_wa;
    $('#cdNotifSwEmail').checked = !!c.notif_email;
}

$('#cdNotifContactSelect')?.addEventListener('change', () => {
    const sel = $('#cdNotifContactSelect');
    if (sel?.value === '__add__') {
        sel.value = '';
        // Navigate to family tab and start editing
        const famTab = document.querySelector('.cd-ficha-tab[data-ficha-tab="familia"]');
        if (famTab) famTab.click();
        setTimeout(() => {
            if (!_famEditing) $('#cdResFamEditBtn')?.click();
            setTimeout(() => $('#cdAddContactBtn')?.click(), 150);
        }, 100);
        return;
    }
    _showNotifSwitches();
});

// Save notif preferences for selected contact
$('#cdNotifSavePrefs')?.addEventListener('click', async () => {
    const _btn = $('#cdNotifSavePrefs');
    if (_btn?.disabled) return; // anti-bouncing
    const sel = $('#cdNotifContactSelect');
    const idx = sel?.value;
    if (idx === '' || !_notifContacts[idx] || !_resData) return;
    btnLoading(_btn, t('status_saving'));
    const c = _notifContacts[idx];
    c.notif_stock = $('#cdNotifSwStock').checked ? 1 : 0;
    c.notif_reporte = $('#cdNotifSwReporte').checked ? 1 : 0;
    c.notif_reporte_hora = JSON.stringify(_getReporteHoras());
    c.notif_reporte_pdf = $('#cdNotifSwReportePdf').checked ? 1 : 0;
    c.notif_signos = $('#cdNotifSwSignos').checked ? 1 : 0;
    c.notif_incidentes = $('#cdNotifSwIncidentes').checked ? 1 : 0;
    c.notif_medicacion = $('#cdNotifSwMedicacion').checked ? 1 : 0;
    c.notif_alimentacion = $('#cdNotifSwAlimentacion').checked ? 1 : 0;
    c.notif_animo = $('#cdNotifSwAnimo').checked ? 1 : 0;
    // ─ Enriched preferences ─
    c.notif_emergencia    = $('#cdNotifSwEmergencia')?.checked    ? 1 : 0;
    c.notif_caida         = $('#cdNotifSwCaida')?.checked         ? 1 : 0;
    c.notif_med_omitida   = $('#cdNotifSwMedOmitida')?.checked    ? 1 : 0;
    c.notif_higiene       = $('#cdNotifSwHigiene')?.checked       ? 1 : 0;
    c.notif_eliminacion   = $('#cdNotifSwEliminacion')?.checked   ? 1 : 0;
    c.notif_sueno         = $('#cdNotifSwSueno')?.checked         ? 1 : 0;
    c.notif_movilidad     = $('#cdNotifSwMovilidad')?.checked     ? 1 : 0;
    c.notif_terapia       = $('#cdNotifSwTerapia')?.checked       ? 1 : 0;
    c.notif_semanal       = $('#cdNotifSwSemanal')?.checked       ? 1 : 0;
    c.notif_notas_medico  = $('#cdNotifSwNotasMedico')?.checked   ? 1 : 0;
    c.notif_visitas       = $('#cdNotifSwVisitas')?.checked       ? 1 : 0;
    c.notif_solo_criticas = $('#cdNotifSwCriticasOnly')?.checked  ? 1 : 0;
    c.notif_quiet_enabled = $('#cdNotifSwQuiet')?.checked         ? 1 : 0;
    c.notif_quiet_start   = $('#cdNotifQuietStart')?.value || '22:00';
    c.notif_quiet_end     = $('#cdNotifQuietEnd')?.value   || '07:00';
    c.notif_wa = $('#cdNotifSwWa').checked ? 1 : 0;
    c.notif_email = $('#cdNotifSwEmail').checked ? 1 : 0;
    // Save all contacts back
    const data = {};
    if (_notifContacts.length > 0) {
        const first = _notifContacts[0];
        data.contacto_nombre = first.nombre || '';
        data.contacto_parentesco = first.parentesco || '';
        data.contacto_telefono = first.telefono || '';
        data.contacto_telefono2 = first.telefono2 || '';
        data.contacto_email = first.email || '';
        data.contacto_direccion = first.direccion || '';
    }
    data.contactos_json = JSON.stringify(_notifContacts);
    try {
        await fetch(`${RES_API}?id=${_resData.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json()).then(j => { if (!j.success) throw new Error(j.message||'Error'); });
        showToast(t('toast_prefs_saved'), 'success');
        delete _resDataCache[_residenteId];
        await loadResidentInfo();
    } catch(e) { showToast(e.message || t('error_save'), 'error'); }
    finally { btnReset(_btn); }
});

// Send notification handlers
$('#cdNotifSendWa')?.addEventListener('click', async () => {
    const type = document.querySelector('input[name="cdNotifType"]:checked')?.value;
    const customMsg = $('#cdNotifCustomMsg')?.value || '';
    const checked = $$('#cdNotifRecipients input[type="checkbox"]:checked');
    const recipients = Array.from(checked).filter(cb => cb.dataset.phone).map(cb => cb.dataset.phone);
    if (!recipients.length) { showToast(t('toast_select_recipient_phone'), 'warning'); return; }
    if (type === 'reporte_dia') showToast(t('toast_generating_ia'), 'info');
    const msg = type === 'personalizado' ? customMsg : await _buildNotifMessage(type);
    if (!msg) { showToast(t('toast_write_message'), 'warning'); return; }
    showToast(t('toast_sending_wa'), 'info');
    const waMsg = msg.replace(/\*\*(.+?)\*\*/g, '*$1*');
    let waFails = 0;
    for (const phone of recipients) {
        try {
            await api(API_URL, {method:'POST', body: JSON.stringify({action:'send_notif_wa', phone, message: waMsg, residente_id: _resData?.id, tipo: type})});
        } catch(e) { waFails++; }
    }
    if (waFails === recipients.length) {
        showToast(t('error_send_wa') || 'Error al enviar WhatsApp', 'error');
    } else if (waFails > 0) {
        showToast(t('toast_notif_partial') || `Enviado con ${waFails} error(es)`, 'warning');
    } else {
        showToast(t('toast_notif_sent'), 'success');
    }
    _loadNotifLog();
});

$('#cdNotifSendEmail')?.addEventListener('click', async () => {
    const type = document.querySelector('input[name="cdNotifType"]:checked')?.value;
    const customMsg = $('#cdNotifCustomMsg')?.value || '';
    const checked = $$('#cdNotifRecipients input[type="checkbox"]:checked');
    const recipients = Array.from(checked).filter(cb => cb.dataset.email).map(cb => ({email: cb.dataset.email, name: cb.closest('.cd-notif-recipient')?.querySelector('.cd-notif-recipient-name')?.textContent || ''}));
    if (!recipients.length) { showToast(t('toast_select_recipient_email'), 'warning'); return; }
    if (type === 'reporte_dia') showToast(t('toast_generating_ia'), 'info');
    const msg = type === 'personalizado' ? customMsg : await _buildNotifMessage(type);
    if (!msg) { showToast(t('toast_write_message'), 'warning'); return; }
    showToast(t('toast_sending_email'), 'info');
    try {
        await api(API_URL, {method:'POST', body: JSON.stringify({action:'send_notif_email', recipients, subject: _notifSubject(type), message: msg, residente_id: _resData?.id, tipo: type})});
        showToast(t('toast_emails_sent'), 'success');
        _loadNotifLog();
    } catch(e) { showToast(t('error_send_email'), 'error'); }
});

function _notifSubject(type) {
    const labels = {
        stock_bajo: t('notif_subj_stock'),
        stock_panales: t('notif_subj_diapers'),
        reporte_dia: t('notif_subj_report'),
        signos_vitales: t('notif_subj_vitals'),
        incidente: t('notif_subj_incident'),
        medicacion: t('notif_subj_medication'),
        alimentacion: t('notif_subj_nutrition'),
        animo: t('notif_subj_mood'),
        personalizado: t('notif_subj_custom')
    };
    return labels[type] || t('notif_subj_custom');
}

async function _buildNotifMessage(type) {
    const resName = _resData ? (_resData.nombre + ' ' + (_resData.apellidos || '')) : t('default_resident');
    const msgs = {
        stock_bajo: `⚠️ *Stock bajo de medicamentos*\n\nEstimado familiar, el stock de medicamentos de *${resName}* está bajo.\n\n> Por favor, coordine la reposición a la brevedad.`,
        stock_panales: `⚠️ *Stock bajo de pañales*\n\nEstimado familiar, el stock de pañales de *${resName}* está bajo.\n\n> Por favor, envíe más a la brevedad.`,
        signos_vitales: `🚨 *Alerta: Signos vitales fuera de rango*\n\nSe han detectado valores fuera del rango normal en los signos vitales de *${resName}*.\n\n> El equipo de cuidados está atento a la situación.`,
        incidente: `🔴 *Incidente reportado*\n\nSe ha reportado un incidente relacionado con *${resName}*.\n\n> El equipo ya tomó las medidas necesarias. Contacte al centro para más detalles.`,
        medicacion: `💊 *Medicación administrada*\n\nLa medicación de *${resName}* ha sido administrada según la prescripción.\n\n> Puede consultar los detalles en la plataforma.`,
        alimentacion: `🍽️ *Registro de alimentación*\n\nSe ha registrado información sobre la alimentación de *${resName}*.\n\n> Consulte la plataforma para más información.`,
        animo: `💜 *Cambio de ánimo / conducta*\n\nSe observaron cambios en el estado de ánimo o conducta de *${resName}*.\n\n> El equipo de cuidados está al tanto.`,
    };
    if (msgs[type]) return msgs[type];
    if (type === 'reporte_dia') {
        // Generate AI report like data-role="familiar"
        const {desde, hasta} = periodRange('dia');
        let registros = [], stats = {};
        try {
            const data = await api(`${API_URL}?residente_id=${_residenteId}&desde=${desde}&hasta=${hasta}`);
            registros = data.registros || [];
            stats = data.stats || {};
        } catch(e) { return `Reporte del día para ${resName}: Los cuidados del día se han registrado correctamente.`; }
        if (!registros.length) return `Reporte del día para ${resName}: No se registraron cuidados en el día de hoy.`;
        let reportText = `Residente: ${resName}\nFecha: ${fmtDate(desde)}\n\n`;
        reportText += `Estadísticas:\n`;
        Object.entries(CAT_LABELS).forEach(([k, l]) => { if (stats[k]) reportText += `- ${l}: ${stats[k]} registros\n`; });
        const byCategory = {};
        registros.forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
            if (!byCategory[r.categoria]) byCategory[r.categoria] = [];
            byCategory[r.categoria].push({...d, _obs: r.observaciones || ''});
        });
        if (byCategory.sueno?.length) {
            const hrs = byCategory.sueno.map(d => parseFloat(d.horas)).filter(v => !isNaN(v) && v > 0);
            if (hrs.length) reportText += `- Sueño: promedio ${(hrs.reduce((a,b)=>a+b,0)/hrs.length).toFixed(1)}h\n`;
        }
        if (byCategory.alimentacion?.length) {
            const pcts = byCategory.alimentacion.map(d => parseInt(d.ingesta_pct)).filter(v => !isNaN(v));
            if (pcts.length) reportText += `- Alimentación: ingesta promedio ${Math.round(pcts.reduce((a,b)=>a+b,0)/pcts.length)}%\n`;
        }
        if (byCategory.signos_vitales?.length) {
            const temps = byCategory.signos_vitales.map(d => parseFloat(d.temperatura)).filter(v => v > 0);
            if (temps.length) reportText += `- Temperatura promedio: ${(temps.reduce((a,b)=>a+b,0)/temps.length).toFixed(1)}°C\n`;
        }
        registros.slice(0, 15).forEach(r => {
            const tmp = document.createElement('span');
            tmp.innerHTML = buildSummary(r);
            reportText += `- ${r.hora || ''} | ${CAT_LABELS[r.categoria] || r.categoria} | ${tmp.textContent || ''}\n`;
        });
        try {
            const res = await api(REPORT_API, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'interpretar', role: 'familiar', report_data: reportText })
            });
            return res.interpretation || `Reporte del día para ${resName}: Los cuidados del día se han registrado correctamente.`;
        } catch(e) { return `Reporte del día para ${resName}: Los cuidados del día se han registrado correctamente.`; }
    }
    return '';
}

// Notification log
let _notifLogData = [];

// Render WhatsApp-style markdown safely as HTML for the log preview.
// 1) HTML-escape first to prevent XSS.
// 2) If the content arrives HTML-escaped from the DB (e.g. "&lt;br&gt;"),
//    decode common entities once so we don't show "&amp;" or "&quot;" literally.
// 3) Convert WA tokens: *bold*, _italic_, ~strike~, ```mono```, > quote,
//    plus tel: links and bare URLs. Newlines preserved via <br>.
function _formatWaMessage(s) {
    if (s == null) return '';
    let txt = String(s);
    // Decode entities that may have been double-escaped at write time.
    if (/&(amp|lt|gt|quot|#0?39|nbsp);/i.test(txt)) {
        const ta = document.createElement('textarea');
        ta.innerHTML = txt;
        txt = ta.value;
    }
    // Now HTML-escape for safe rendering.
    let out = txt
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    // Quote lines starting with "&gt; " → blockquote-like span
    out = out.replace(/(^|\n)&gt;\s?(.*?)(?=\n|$)/g,
        '$1<span style="display:block;border-left:3px solid var(--cd-border);padding:2px 8px;color:var(--cd-text-muted);font-style:italic">$2</span>');
    // ```code``` (multiline) before single-char tokens
    out = out.replace(/```([\s\S]+?)```/g,
        '<code style="display:inline-block;background:var(--cd-surface);padding:2px 6px;border-radius:4px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85em">$1</code>');
    // *bold*  _italic_  ~strike~  (avoid matching across line breaks and require word boundary)
    out = out.replace(/(^|[\s(\[])\*([^\s*][^*\n]*?[^\s*]|\S)\*(?=[\s).,;:!?\]]|$)/g, '$1<strong>$2</strong>');
    out = out.replace(/(^|[\s(\[])_([^\s_][^_\n]*?[^\s_]|\S)_(?=[\s).,;:!?\]]|$)/g, '$1<em>$2</em>');
    out = out.replace(/(^|[\s(\[])~([^\s~][^~\n]*?[^\s~]|\S)~(?=[\s).,;:!?\]]|$)/g, '$1<s>$2</s>');
    // Newlines → <br>
    out = out.replace(/\n/g, '<br>');
    return out;
}

async function _loadNotifLog() {
    const list = $('#cdNotifLogList');
    if (!list || !_resData) return;
    try {
        const res = await api(`${API_URL}?notif_log=1&residente_id=${_resData.id}`);
        const logs = Array.isArray(res) ? res : (res?.logs || []);
        _notifLogData = logs;
        if (!logs.length) {
            list.innerHTML = '<p class="cd-notif-empty"><?= t('notif_log_empty') ?></p>';
            return;
        }
        const _tipoLabelsLog = {stock_bajo:'Stock bajo',stock_panales:'Stock bajo pañales',reporte_dia:'Reporte del día',reporte_dia_auto:'Reporte del día (IA)',personalizado:'Personalizado',manual:'Manual'};
        const _tipoLabelLog = t => _tipoLabelsLog[t] || (t?.startsWith('reporte_dia_auto') ? 'Reporte del día (IA)' : t);
        // Build phone/email → name lookup from contacts
        const _destNameMap = {};
        _notifContacts.forEach(c => {
            if (c.telefono) _destNameMap[c.telefono] = c.nombre;
            if (c.email) _destNameMap[c.email] = c.nombre;
        });
        list.innerHTML = logs.slice(0, 50).map((l, i) => {
            const chIcon = l.canal === 'whatsapp' ? '💬' : '✉️';
            const statusClass = l.estado === 'enviado' ? 'cd-notif-log-ok' : 'cd-notif-log-err';
            // Convert UTC date to institution timezone
            const fechaLocal = l.fecha ? new Date(l.fecha + 'Z').toLocaleString('es-MX', {timeZone: APP_TZ, day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '';
            const contactName = _destNameMap[l.destinatario] || '';
            const destLabel = contactName ? `${contactName} (${l.destinatario})` : l.destinatario;
            return `<div class="cd-notif-log-entry ${statusClass}" data-log-idx="${i}" style="cursor:pointer">
                <span class="cd-notif-log-ch">${chIcon}</span>
                <span class="cd-notif-log-dest">${esc(destLabel)}</span>
                <span class="cd-notif-log-type">${esc(_tipoLabelLog(l.tipo))}</span>
                <span class="cd-notif-log-date">${fechaLocal}</span>
                <span class="cd-notif-log-status">${l.estado === 'enviado' ? '✓' : '✗'}</span>
            </div>`;
        }).join('');
        list.querySelectorAll('.cd-notif-log-entry').forEach(entry => {
            entry.addEventListener('click', () => {
                const idx = parseInt(entry.dataset.logIdx);
                const l = _notifLogData[idx];
                if (!l) return;
                const chLabel = l.canal === 'whatsapp' ? 'WhatsApp' : 'Email';
                const chIcon = l.canal === 'whatsapp' ? '💬' : '✉️';
                const statusLabel = l.estado === 'enviado' ? '<span style="color:var(--cd-success);font-weight:600">✓ Enviado</span>' : '<span style="color:var(--cd-danger);font-weight:600">✗ Error</span>';
                const fechaDetalle = l.fecha ? new Date(l.fecha + 'Z').toLocaleString('es-MX', {timeZone: APP_TZ, day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '';
                const detailName = _destNameMap?.[l.destinatario];
                const destDetail = detailName ? `${esc(detailName)} (${esc(l.destinatario)})` : esc(l.destinatario);
                let body = `<div class="cd-sidebar-detail-grid">
                    <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Canal</span><span>${chIcon} ${chLabel}</span></div>
                    <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Destinatario</span><span>${destDetail}</span></div>
                    <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Tipo</span><span>${esc(_tipoLabelLog(l.tipo))}</span></div>
                    <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Fecha</span><span>${fechaDetalle}</span></div>
                    <div class="cd-sidebar-detail-row"><span class="cd-sidebar-detail-label">Estado</span><span>${statusLabel}</span></div>
                </div>`;
                if (l.mensaje) {
                    body += `<div style="margin-top:12px"><label class="cd-sidebar-detail-label" style="display:block;margin-bottom:4px">Mensaje</label><div class="cd-notif-log-msg-preview">${_formatWaMessage(l.mensaje)}</div></div>`;
                }
                if (l.error_detalle) {
                    body += `<div style="margin-top:8px"><label class="cd-sidebar-detail-label" style="display:block;margin-bottom:4px;color:var(--cd-danger)">Detalle del error</label><div class="cd-notif-log-msg-preview" style="border-color:var(--cd-danger)">${esc(l.error_detalle)}</div></div>`;
                }
                body += `<div style="margin-top:16px;text-align:right"><button type="button" class="cd-btn-submit" id="cdNotifLogDeleteBtn" data-perm-id="fam_delete_notif_log_btn" style="background:var(--cd-danger);font-size:0.8rem;padding:6px 14px">Eliminar registro</button></div>`;
                openSidebar(t('sidebar_detail'), body);
                $('#cdNotifLogDeleteBtn')?.addEventListener('click', async () => {
                    if (!confirm('¿Eliminar este registro de notificación?')) return;
                    try {
                        await api(`${API_URL}?notif_log_id=${l.id}`, {method:'DELETE'});
                        showToast('Registro eliminado', 'success');
                        closeSidebar();
                        _loadNotifLog();
                    } catch(e) { showToast('Error al eliminar', 'error'); }
                });
            });
        });
    } catch(e) { list.innerHTML = '<p class="cd-notif-empty">' + t('error_load_log_inline') + '</p>'; }
}

$('#cdNotifLogRefresh')?.addEventListener('click', () => _loadNotifLog());

async function _checkWaBadges() {
    const badges = $$('.cd-wa-badge[data-wa-phone]');
    for (const badge of badges) {
        const phone = badge.dataset.waPhone;
        if (!phone) continue;
        try {
            const res = await api(`${API_URL}?check_whatsapp=${encodeURIComponent(phone)}`);
            if (res?.registered === true) badge.style.display = 'inline';
        } catch(e) {}
    }
}

function _createContactBlock(c, idx, total) {
    const block = document.createElement('div');
    block.className = 'cd-family-contact-block';
    if (c.principal) block.classList.add('cd-contact-principal');
    block.dataset.contactIdx = idx;
    if (c._usuario_id) block.dataset.usuarioId = c._usuario_id;
    if (c._pending_invite) {
        block.classList.add('cd-family-contact-block--invite');
        block.dataset.pendingInvite = '1';
    }
    const userBadge = c._usuario_id
        ? `<span class="cd-fam-user-badge" title="Usuario registrado en el sistema"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Usuario</span>`
        : '';
    const inviteBadge = c._pending_invite
        ? `<span class="cd-fam-user-badge cd-fam-user-badge--invite" title="Invitación enviada">Invitación</span>`
        : '';
    const principalActive = c.principal ? ' active' : '';
    block.innerHTML = `
        <div class="cd-family-contact-header">
            <button type="button" class="cd-family-contact-star${principalActive}" data-perm-id="fam_set_principal_contact_btn" title="${t('contact_set_principal') || 'Marcar como principal'}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="${c.principal ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            <span class="cd-family-contact-label">${c.principal ? (t('contact_principal') || 'Principal') : 'Contacto ' + (idx + 1)}</span>
            ${userBadge}
            ${inviteBadge}
            <button type="button" class="cd-family-contact-remove" data-perm-id="fam_remove_contact_btn" title="Eliminar contacto">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
        </div>
        <div class="cd-res-grid">
            <div class="cd-res-field" data-field="nombre"><label><?= t('ficha_name') ?></label><span>${esc(c.nombre)||'—'}</span><input class="cd-inline-edit" type="text" value="${esc(c.nombre)}"></div>
            <div class="cd-res-field" data-field="parentesco"><label>Parentesco</label><span>${esc(c.parentesco)||'—'}</span><input class="cd-inline-edit" type="text" value="${esc(c.parentesco)}"></div>
            <div class="cd-res-field" data-field="telefono"><label>Teléfono <span class="cd-wa-badge" data-wa-phone="${esc(c.telefono)}" style="display:none" title="Registrado en WhatsApp"><img src="assets/icons/whatsapp.png" alt="WhatsApp" width="14" height="14" style="vertical-align:-2px;filter:brightness(0) saturate(100%) invert(56%) sepia(74%) saturate(420%) hue-rotate(94deg) brightness(95%) contrast(85%)"></span></label><span>${esc(c.telefono)||'—'}</span><input class="cd-inline-edit" type="text" value="${esc(c.telefono)}"></div>
            <div class="cd-res-field" data-field="telefono2"><label>Teléfono 2</label><span>${esc(c.telefono2)||'—'}</span><input class="cd-inline-edit" type="text" value="${esc(c.telefono2)}"></div>
            <div class="cd-res-field full" data-field="email"><label>Email</label><span>${esc(c.email)||'—'}</span><input class="cd-inline-edit" type="email" value="${esc(c.email)}"></div>
            <div class="cd-res-field full" data-field="direccion"><label>Dirección</label><span>${esc(c.direccion)||'—'}</span><textarea class="cd-inline-edit">${esc(c.direccion)}</textarea></div>
        </div>`;
    if (_famEditing) {
        block.querySelectorAll('.cd-res-field').forEach(f => f.classList.add('editing'));
        block.querySelectorAll('.cd-inline-edit').forEach(inp => {
            if (c._pending_invite) {
                inp.readOnly = true;
                inp.style.pointerEvents = 'none';
            }
            inp.addEventListener('input', _updateFamEditButton);
            inp.addEventListener('change', _updateFamEditButton);
        });
    }
    // Star / principal toggle
    const starBtn = block.querySelector('.cd-family-contact-star');
    if (starBtn) {
        if (c._pending_invite) {
            starBtn.disabled = true;
            starBtn.style.opacity = '.45';
            starBtn.style.cursor = 'not-allowed';
        }
        starBtn.addEventListener('click', async () => {
            if (c._pending_invite) return;
            const container = $('#cdResFamilyContainer');
            const wasPrincipal = block.classList.contains('cd-contact-principal');
            // Unstar all
            $$('#cdResFamilyContainer .cd-family-contact-block').forEach(b => {
                b.classList.remove('cd-contact-principal');
                const s = b.querySelector('.cd-family-contact-star');
                if (s) { s.classList.remove('active'); s.querySelector('svg').setAttribute('fill', 'none'); }
                const lbl = b.querySelector('.cd-family-contact-label');
                if (lbl) lbl.textContent = 'Contacto ' + (parseInt(b.dataset.contactIdx, 10) + 1);
            });
            if (!wasPrincipal) {
                block.classList.add('cd-contact-principal');
                starBtn.classList.add('active');
                starBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                block.querySelector('.cd-family-contact-label').textContent = t('contact_principal') || 'Principal';
                // Move to top
                container.prepend(block);
                // Re-index
                $$('#cdResFamilyContainer .cd-family-contact-block').forEach((b, i) => {
                    b.dataset.contactIdx = i;
                    if (!b.classList.contains('cd-contact-principal')) {
                        b.querySelector('.cd-family-contact-label').textContent = 'Contacto ' + (i + 1);
                    }
                });
            }
            // Auto-save principal change
            if (_resData) {
                if (starBtn.disabled) return; // anti-bouncing
                starBtn.disabled = true;
                const contacts = _collectFamilyContacts();
                contacts.forEach(ct => {
                    // Merge notif prefs by identity (usuario_id || email || name+phone), NOT by index
                    const key = ct._usuario_id
                        ? 'u:' + ct._usuario_id
                        : ((ct.email||'').trim().toLowerCase()
                            || (((ct.nombre||'').trim().toLowerCase()) + '|' + ((ct.telefono||'').trim())));
                    const ex = _notifContacts.find(nc => {
                        const nk = nc._usuario_id
                            ? 'u:' + nc._usuario_id
                            : ((nc.email||'').trim().toLowerCase()
                                || (((nc.nombre||'').trim().toLowerCase()) + '|' + ((nc.telefono||'').trim())));
                        return nk === key;
                    });
                    if (ex) {
                        ct.notif_stock = ex.notif_stock ?? 0; ct.notif_reporte = ex.notif_reporte ?? 0;
                        ct.notif_reporte_hora = ex.notif_reporte_hora ?? '08:00'; ct.notif_reporte_pdf = ex.notif_reporte_pdf ?? 0;
                        ct.notif_signos = ex.notif_signos ?? 0; ct.notif_incidentes = ex.notif_incidentes ?? 0;
                        ct.notif_medicacion = ex.notif_medicacion ?? 0; ct.notif_alimentacion = ex.notif_alimentacion ?? 0;
                        ct.notif_animo = ex.notif_animo ?? 0; ct.notif_wa = ex.notif_wa ?? 0; ct.notif_email = ex.notif_email ?? 0;
                    }
                });
                const saveData = {};
                if (contacts.length > 0) {
                    const first = contacts[0];
                    saveData.contacto_nombre = first.nombre || '';
                    saveData.contacto_parentesco = first.parentesco || '';
                    saveData.contacto_telefono = first.telefono || '';
                    saveData.contacto_telefono2 = first.telefono2 || '';
                    saveData.contacto_email = first.email || '';
                    saveData.contacto_direccion = first.direccion || '';
                }
                saveData.contactos_json = JSON.stringify(contacts);
                try {
                    await fetch(`${RES_API}?id=${_resData.id}`, {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(saveData)}).then(r=>r.json()).then(j=>{if(!j.success) throw new Error(j.message||'Error');});
                    showToast(t('toast_prefs_saved'), 'success');
                    delete _resDataCache[_residenteId];
                    _notifContacts = contacts.map(ct => ({...ct}));
                } catch(e) { showToast(e.message || t('error_save'), 'error'); }
                finally { starBtn.disabled = false; }
            }
            _updateFamEditButton();
        });
    }
    const removeBtn = block.querySelector('.cd-family-contact-remove');
    if (removeBtn) {
        if (c._pending_invite) removeBtn.style.display = 'none';
        removeBtn.addEventListener('click', async () => {
            if (c._pending_invite) return;
            const contactName = c.nombre || `Contacto ${idx + 1}`;
            if (!await cdConfirm(t('confirm_delete_contact', {':name': contactName}), { title: t('confirm_delete_contact_title'), type: 'danger', okText: t('btn_delete') })) return;
            block.remove();
            const remaining = $$('#cdResFamilyContainer .cd-family-contact-block');
            remaining.forEach((b, i) => {
                b.dataset.contactIdx = i;
                if (!b.classList.contains('cd-contact-principal')) {
                    b.querySelector('.cd-family-contact-label').textContent = `Contacto ${i + 1}`;
                }
            });
            // Auto-save deletion
            if (_resData) {
                const contacts = _collectFamilyContacts();
                // Preserve notif prefs (all 11 fields)
                contacts.forEach((ct, i) => {
                    const ex = _notifContacts[i];
                    if (ex) {
                        ct.notif_stock = ex.notif_stock ?? 0;
                        ct.notif_reporte = ex.notif_reporte ?? 0;
                        ct.notif_reporte_hora = ex.notif_reporte_hora ?? '08:00';
                        ct.notif_reporte_pdf = ex.notif_reporte_pdf ?? 0;
                        ct.notif_signos = ex.notif_signos ?? 0;
                        ct.notif_incidentes = ex.notif_incidentes ?? 0;
                        ct.notif_medicacion = ex.notif_medicacion ?? 0;
                        ct.notif_alimentacion = ex.notif_alimentacion ?? 0;
                        ct.notif_animo = ex.notif_animo ?? 0;
                        ct.notif_wa = ex.notif_wa ?? 0;
                        ct.notif_email = ex.notif_email ?? 0;
                    }
                });
                const data = {};
                if (contacts.length > 0) {
                    const first = contacts[0];
                    data.contacto_nombre = first.nombre || '';
                    data.contacto_parentesco = first.parentesco || '';
                    data.contacto_telefono = first.telefono || '';
                    data.contacto_telefono2 = first.telefono2 || '';
                    data.contacto_email = first.email || '';
                    data.contacto_direccion = first.direccion || '';
                } else {
                    data.contacto_nombre = ''; data.contacto_parentesco = ''; data.contacto_telefono = '';
                    data.contacto_telefono2 = ''; data.contacto_email = ''; data.contacto_direccion = '';
                }
                data.contactos_json = JSON.stringify(contacts);
                try {
                    await fetch(`${RES_API}?id=${_resData.id}`, {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)}).then(r=>r.json()).then(j=>{if(!j.success) throw new Error(j.message||'Error');});
                    showToast(t('toast_contact_deleted'), 'success');
                    delete _resDataCache[_residenteId];
                    await loadResidentInfo();
                } catch(e) { showToast(e.message || t('error_delete'), 'error'); }
            }
            _updateFamEditButton();
        });
    }
    return block;
}

function _collectFamilyContacts() {
    const contacts = [];
    $$('#cdResFamilyContainer .cd-family-contact-block').forEach(block => {
        if (block.dataset.pendingInvite === '1') return;
        const c = {};
        block.querySelectorAll('.cd-res-field[data-field]').forEach(f => {
            const inp = f.querySelector('.cd-inline-edit');
            if (inp) c[f.dataset.field] = inp.value.trim();
        });
        if (block.dataset.usuarioId) c._usuario_id = parseInt(block.dataset.usuarioId, 10);
        if (block.classList.contains('cd-contact-principal')) c.principal = 1;
        contacts.push(c);
    });
    return contacts;
}

$('#cdAddContactBtn')?.addEventListener('click', () => {
    // Enter edit mode if not already
    if (!_famEditing) {
        if (_resData) renderFamilyContacts(_resData);
        toggleFamEdit(true);
        _famOriginalValues = _getFamFormValues();
        $$('#cdResFamilyContainer .cd-inline-edit').forEach(inp => {
            inp.addEventListener('input', _updateFamEditButton);
            inp.addEventListener('change', _updateFamEditButton);
        });
    }
    const container = $('#cdResFamilyContainer');
    const count = container.querySelectorAll('.cd-family-contact-block').length;
    const block = _createContactBlock({nombre:'',parentesco:'',telefono:'',telefono2:'',email:'',direccion:''}, count, count + 1);
    container.appendChild(block);
    $$('#cdResFamilyContainer .cd-family-contact-remove').forEach(btn => btn.style.display = '');
    block.querySelector('.cd-inline-edit')?.focus();
    _updateFamEditButton();
});


// -----------------------------------------------
// NOTIFICATION PREFERENCE INFO BUTTONS (R8-9)
// Inject info (i) buttons next to each notification switch label
// describing what trigger fires that notification.
// -----------------------------------------------
const NOTIF_HELP_MAP = {
    cdNotifSwEmergencia:   'emergency',
    cdNotifSwSignos:       'vitals',
    cdNotifSwIncidentes:   'incidents',
    cdNotifSwCaida:        'falls',
    cdNotifSwMedicacion:   'medication',
    cdNotifSwMedOmitida:   'med_omitted',
    cdNotifSwAlimentacion: 'nutrition',
    cdNotifSwHigiene:      'hygiene',
    cdNotifSwEliminacion:  'elimination',
    cdNotifSwSueno:        'sleep',
    cdNotifSwAnimo:        'mood',
    cdNotifSwMovilidad:    'mobility',
    cdNotifSwTerapia:      'therapy',
    cdNotifSwReporte:      'report',
    cdNotifSwReportePdf:   'report_pdf',
    cdNotifSwSemanal:      'weekly',
    cdNotifSwNotasMedico:  'doctor_notes',
    cdNotifSwVisitas:      'visits',
    cdNotifSwStock:        'stock',
    cdNotifSwCriticasOnly: 'critical_only',
    cdNotifSwQuiet:        'quiet_hours',
    cdNotifSwWa:           'wa',
    cdNotifSwEmail:        'email',
};

function _injectNotifHelpButtons() {
    const panel = $('#cdNotifSwitchesPanel');
    if (!panel || panel.dataset.helpReady) return;
    Object.entries(NOTIF_HELP_MAP).forEach(([swId, key]) => {
        const sw = panel.querySelector('#' + swId);
        if (!sw) return;
        const row = sw.closest('.cd-switch-row');
        if (!row) return;
        const label = row.querySelector('.cd-switch-label');
        if (!label || label.querySelector('.cd-notif-help-btn')) return;
        // ── FIX: <label> sin for= activa el primer descendiente labelable.
        // <button> ES labelable, asi que al inyectar el help-btn ANTES del <input>
        // un click en el slider disparaba el boton (abriendo el sidebar) en vez
        // del checkbox. Forzar for=swId hace que el label apunte explicitamente
        // al checkbox y el help-btn ya no intercepta clicks ajenos.
        if (row.tagName === 'LABEL' && !row.hasAttribute('for')) {
            row.setAttribute('for', swId);
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cd-notif-help-btn';
        btn.dataset.helpKey = key;
        btn.title = t('notif_help_btn_title') || 'Ver descripci�n';
        btn.setAttribute('aria-label', btn.title);
        // Material Icons "info" (codepoint e88e) reproducido como SVG filled.
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
        label.appendChild(btn);
    });
    panel.dataset.helpReady = '1';
    panel.addEventListener('click', e => {
        const btn = e.target.closest('.cd-notif-help-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const key = btn.dataset.helpKey;
        const titleKey = 'notif_help_' + key + '_title';
        const bodyKey  = 'notif_help_' + key + '_body';
        const trigKey  = 'notif_help_' + key + '_trigger';
        const title = t(titleKey) || (btn.closest('.cd-switch-row')?.querySelector('.cd-switch-label')?.textContent.trim() || 'Notificaci�n');
        const body  = t(bodyKey)  || '';
        const trig  = t(trigKey)  || '';
        if (typeof openSidebar !== 'function') return;
        openSidebar(title, `
            <div class="cd-sidebar-section">
                <p style="margin:0 0 12px;line-height:1.5;color:var(--cd-text)">${body}</p>
                ${trig ? `<div class="cd-notif-help-trigger">
                    <strong style="display:block;margin-bottom:4px;color:var(--cd-text-secondary);font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px">${t('notif_help_trigger_label') || 'Disparador'}</strong>
                    <p style="margin:0;line-height:1.5">${trig}</p>
                </div>` : ''}
            </div>`, '');
    });
}

// Re-inject after the switches panel becomes visible
const _origShowNotifSwitches = typeof _showNotifSwitches === 'function' ? _showNotifSwitches : null;
document.addEventListener('DOMContentLoaded', _injectNotifHelpButtons);
// Also inject on contact change (panel toggles display)
$('#cdNotifContactSelect')?.addEventListener('change', () => setTimeout(_injectNotifHelpButtons, 50));
// And when ficha loads notif tab
document.addEventListener('click', e => {
    if (e.target.closest('[data-ficha-tab="notificaciones"], [data-ntab="prefs"]')) {
        setTimeout(_injectNotifHelpButtons, 100);
    }
});
