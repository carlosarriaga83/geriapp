// cd-legal.js — Legal documents (T&C / Privacy)
// Extracted from cuidados.php (lines 13681)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// LEGAL DOCUMENTS (T&C / Privacy)
// ═══════════════════════════════════════════════
async function loadLegalDocs(tipo) {
    const isTc = tipo === 'terminos';
    const listEl = $(isTc ? '#cfgTcList' : '#cfgPrivList');
    if (!listEl) return;
    listEl.innerHTML = `<p class="cd-text-muted cd-text-sm">${t('config_legal_loading')}</p>`;
    try {
        const docs = await api(`${LEGAL_API}?action=list&tipo=${tipo}`);
        if (isTc) _legalTcLoaded = true; else _legalPrivLoaded = true;
        if (!docs?.length) {
            listEl.innerHTML = `<p class="cd-text-muted cd-text-sm">${t('config_legal_empty')}</p>`;
            return;
        }
        listEl.innerHTML = docs.map(d => `
            <div class="cd-legal-doc-row${d.vigente == 1 ? ' cd-legal-vigente' : ''}" data-id="${d.id}">
                <div class="cd-legal-doc-info">
                    <span class="cd-legal-doc-version">v${esc(d.version)}</span>
                    <span class="cd-legal-doc-title">${esc(d.titulo)}</span>
                    ${d.vigente == 1 ? `<span class="cd-badge cd-badge-success">${t('config_legal_active')}</span>` : ''}
                    ${d.requiere_firma == 1 ? `<span class="cd-badge cd-badge-info">${t('config_legal_requires_sig')}</span>` : ''}
                </div>
                <div class="cd-legal-doc-meta">
                    <span class="cd-text-muted cd-text-xs">${d.firmas_count || 0} ${t('config_legal_signatures')}</span>
                    <span class="cd-text-muted cd-text-xs">${d.creado_at?.substring(0,10) || ''}</span>
                </div>
                <div class="cd-legal-doc-actions">
                    <button class="cd-btn-ghost-sm" onclick="viewLegalDoc(${d.id})" title="${t('config_legal_view_doc')}">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    ${!isTc && d.vigente == 1 ? `<a class="cd-btn-ghost-sm" href="${BASE}/adp.php?inst=${INST_ID}" target="_blank" rel="noopener" title="Ver página pública">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>` : ''}
                    <button class="cd-btn-ghost-sm" onclick="editLegalDoc(${d.id},'${tipo}')" title="${t('config_legal_edit')}">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    ${d.vigente != 1 ? `<button class="cd-btn-ghost-sm" onclick="setLegalVigente(${d.id},'${tipo}')" title="${t('config_legal_set_active')}">${t('config_legal_set_active')}</button>` : ''}
                    <button class="cd-btn-ghost-sm" onclick="viewLegalSignatures(${d.id})" title="${t('config_legal_view_sigs')}">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                    </button>
                    <button class="cd-btn-ghost-sm cd-text-danger" onclick="deleteLegalDoc(${d.id},'${tipo}')" title="${t('btn_delete')}">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>
            </div>
        `).join('');
    } catch(e) {
        listEl.innerHTML = `<p class="cd-text-muted cd-text-sm">${t('config_legal_error')}</p>`;
    }
}

function openLegalEditor(tipo, doc) {
    const isEdit = !!doc;
    const existingContent = renderLegalHtml(doc?.contenido);
    const html = `
        <div class="cd-cfg-form">
            <div class="cd-form-row">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('config_legal_version_lbl')}</label>
                    <input class="cd-input" id="cdLegalVersion" placeholder="1.0" value="${esc(doc?.version || '1.0')}" maxlength="20">
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('config_legal_title_lbl')}</label>
                <input class="cd-input" id="cdLegalTitulo" placeholder="${tipo === 'terminos' ? t('config_tc_title') : t('config_privacy_title')}" value="${esc(doc?.titulo || '')}" maxlength="200">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('config_legal_content_lbl')}</label>
                <div class="cd-rte-toolbar" id="cdRteToolbar">
                    <button type="button" data-cmd="bold" title="Negrita"><strong>B</strong></button>
                    <button type="button" data-cmd="italic" title="Cursiva"><em>I</em></button>
                    <button type="button" data-cmd="underline" title="Subrayado"><u>U</u></button>
                    <span class="cd-rte-sep"></span>
                    <button type="button" data-cmd="insertUnorderedList" title="Lista">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/></svg>
                    </button>
                    <button type="button" data-cmd="insertOrderedList" title="Lista numerada">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="1" y="8" fill="currentColor" font-size="7" font-weight="600">1</text><text x="1" y="14" fill="currentColor" font-size="7" font-weight="600">2</text><text x="1" y="20" fill="currentColor" font-size="7" font-weight="600">3</text></svg>
                    </button>
                    <span class="cd-rte-sep"></span>
                    <button type="button" id="cdRtePreview" title="Vista previa">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="cd-rte-editor" id="cdLegalContenido" contenteditable="true" style="font-size:0.8125rem;line-height:1.6">${existingContent}</div>
            </div>
            <div class="cd-form-row" style="gap:12px">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('config_legal_vigente_lbl')}</label>
                    <label class="cd-toggle"><input type="checkbox" id="cdLegalVigente" ${doc?.vigente == 1 ? 'checked' : ''}><span class="cd-toggle-track"><span class="cd-toggle-knob"></span></span></label>
                </div>
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('config_legal_requires_sig')}</label>
                    <label class="cd-toggle"><input type="checkbox" id="cdLegalReqFirma" ${(doc?.requiere_firma ?? 1) == 1 ? 'checked' : ''}><span class="cd-toggle-track"><span class="cd-toggle-knob"></span></span></label>
                </div>
            </div>
            <div class="cd-cfg-actions" style="margin-top:16px">
                <button class="cd-btn-submit" id="cdLegalSaveBtn">${t('config_save')}</button>
            </div>
        </div>
    `;
    openSidebar(isEdit ? t('config_legal_edit') : t('config_legal_new'), html, 'wide');
    setTimeout(() => {
        // Rich text toolbar
        const toolbar = $('#cdRteToolbar');
        const editor = $('#cdLegalContenido');
        if (toolbar && editor) {
            toolbar.querySelectorAll('[data-cmd]').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.execCommand(btn.dataset.cmd, false, null);
                    editor.focus();
                });
            });
            // Preview button
            $('#cdRtePreview')?.addEventListener('click', () => {
                const titulo = $('#cdLegalTitulo')?.value?.trim() || 'Sin título';
                const version = $('#cdLegalVersion')?.value?.trim() || '1.0';
                const contenido = editor.innerHTML.trim();
                const previewHtml = `
                    <div style="margin-bottom:12px;display:flex;align-items:center;gap:8px">
                        <span class="cd-badge cd-badge-neutral">v${esc(version)}</span>
                        <span class="cd-badge cd-badge-info">Vista previa</span>
                    </div>
                    <div class="cd-legal-preview-content cd-legal-rich">${renderLegalHtml(contenido)}</div>
                    <div class="cd-cfg-actions" style="margin-top:16px">
                        <button class="cd-btn-submit cd-btn-secondary" id="cdPreviewBack">← Volver al editor</button>
                    </div>
                `;
                openSidebar(titulo, previewHtml, 'wide');
                $('#cdPreviewBack')?.addEventListener('click', () => openLegalEditor(tipo, {
                    ...(doc || {}),
                    version: version,
                    titulo: titulo,
                    contenido: contenido,
                    vigente: doc?.vigente,
                    requiere_firma: doc?.requiere_firma
                }));
            });
        }

        $('#cdLegalSaveBtn')?.addEventListener('click', async () => {
            const _btn = $('#cdLegalSaveBtn');
            btnLoading(_btn, t('status_saving'));
            const contenidoHtml = $('#cdLegalContenido')?.innerHTML?.trim() || '';
            const body = {
                tipo,
                version: $('#cdLegalVersion')?.value?.trim() || '1.0',
                titulo: $('#cdLegalTitulo')?.value?.trim(),
                contenido_b64: btoa(unescape(encodeURIComponent(contenidoHtml))),
                vigente: $('#cdLegalVigente')?.checked ? 1 : 0,
                requiere_firma: $('#cdLegalReqFirma')?.checked ? 1 : 0
            };
            if (!body.titulo || !contenidoHtml) { showToast(t('config_legal_fill_all'), 'error'); btnReset(_btn); return; }
            try {
                await api(LEGAL_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
                showToast(t('toast_legal_saved'), 'success');
                closeSidebar();
                if (tipo === 'terminos') _legalTcLoaded = false;
                else _legalPrivLoaded = false;
                loadLegalDocs(tipo);
            } catch(e) { showToast(e.message || t('config_legal_error'), 'error'); }
            btnReset(_btn);
        });
    }, 50);
}

async function setLegalVigente(id, tipo) {
    if (!await cdConfirm(t('confirm_legal_vigente_body'), { title: t('confirm_legal_vigente_title'), type: 'warn' })) return;
    try {
        await api(`${LEGAL_API}?action=set_vigente&id=${id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({}) });
        showToast(t('toast_legal_vigente'), 'success');
        if (tipo === 'terminos') _legalTcLoaded = false; else _legalPrivLoaded = false;
        loadLegalDocs(tipo);
    } catch(e) { showToast(e.message || 'Error', 'error'); }
}

async function viewLegalSignatures(docId) {
    try {
        const firmas = await api(`${LEGAL_API}?action=firmas&id=${docId}`);
        if (!firmas?.length) { showToast(t('config_legal_no_sigs'), 'info'); return; }
        const cards = firmas.map(f => `
            <div class="cd-sig-card" data-search="${esc(f.usuario_nombre).toLowerCase()} ${esc(f.usuario_email).toLowerCase()}">
                <div class="cd-sig-card-main">
                    <div class="cd-sig-card-avatar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                    </div>
                    <div class="cd-sig-card-info">
                        <strong class="cd-sig-card-name">${esc(f.usuario_nombre)}</strong>
                        <span class="cd-sig-card-email">${esc(f.usuario_email)}</span>
                    </div>
                    <div class="cd-sig-card-meta">
                        <span class="cd-sig-card-date">${f.firmado_at?.substring(0,16)?.replace('T',' ') || ''}</span>
                        <span class="cd-sig-card-ip">IP: ${esc(f.ip || 'N/A')}</span>
                    </div>
                    <button class="cd-btn cd-btn-xs cd-btn-outline" onclick="viewFirmaAutografa(${f.id},${docId})" title="${t('legal_view_signature')}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        ${t('legal_view_signature')}
                    </button>
                </div>
            </div>
        `).join('');
        const html = `
            <div class="cd-sig-search-wrap">
                <input type="text" class="cd-input" id="sigSearchInput" placeholder="${t('legal_sig_search_placeholder')}" style="width:100%">
            </div>
            <div id="sigCardList">${cards}</div>
            <style>
                .cd-sig-search-wrap{margin-bottom:12px}
                .cd-sig-card{padding:10px 12px;border:1px solid var(--cd-border,#e2e8f0);border-radius:8px;margin-bottom:8px;background:var(--cd-bg-card,#fff);transition:box-shadow .15s}
                .cd-sig-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.07)}
                .cd-sig-card-main{display:flex;align-items:center;gap:10px;flex-wrap:nowrap}
                .cd-sig-card-avatar{width:34px;height:34px;border-radius:50%;background:var(--cd-primary-light,#e0e7ff);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--cd-primary,#6366f1)}
                .cd-sig-card-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:1px}
                .cd-sig-card-name{font-size:.8125rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
                .cd-sig-card-email{font-size:.75rem;color:var(--cd-text-muted,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
                .cd-sig-card-meta{display:flex;flex-direction:column;align-items:flex-end;gap:1px;flex-shrink:0;white-space:nowrap}
                .cd-sig-card-date{font-size:.75rem;color:var(--cd-text-secondary,#64748b)}
                .cd-sig-card-ip{font-size:.6875rem;color:var(--cd-text-muted,#94a3b8)}
                .cd-sig-card.hidden{display:none}
                [data-theme="dark"] .cd-sig-card{background:#1e1e1e;border-color:#444}
                [data-theme="dark"] .cd-sig-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.35)}
                [data-theme="dark"] .cd-sig-card-name{color:#f5f5f5}
                [data-theme="dark"] .cd-sig-card-email{color:#a1a1aa}
                [data-theme="dark"] .cd-sig-card-date{color:#a1a1aa}
                [data-theme="dark"] .cd-sig-card-ip{color:#71717a}
                [data-theme="dark"] .cd-sig-card-avatar{background:#2a3a4a;color:#5eaac5}
            </style>
        `;
        openSidebar(t('config_legal_signatures_title') + ` (${firmas.length})`, html, 'wide');
        // Activar búsqueda
        const inp = document.getElementById('sigSearchInput');
        if (inp) inp.addEventListener('input', () => {
            const q = inp.value.trim().toLowerCase();
            document.querySelectorAll('.cd-sig-card').forEach(c => {
                c.classList.toggle('hidden', q && !c.dataset.search.includes(q));
            });
        });
    } catch(e) { showToast('Error', 'error'); }
}

async function viewFirmaAutografa(firmaId, docId) {
    try {
        const res = await api(`${LEGAL_API}?action=get_firma&id=${firmaId}`);
        if (!res?.firma_data) { showToast(t('legal_no_signature_data'), 'info'); return; }
        const html = `
            <div style="text-align:center;padding:16px">
                <div style="background:var(--cd-bg-card,#fff);border:1px solid var(--cd-border,#e2e8f0);border-radius:10px;padding:16px;display:inline-block;max-width:100%">
                    <img src="${res.firma_data}" alt="Firma" style="max-width:100%;border-radius:6px;display:block">
                </div>
            </div>
        `;
        const actions = docId ? `<button class="cd-btn-submit cd-btn-secondary" id="cdFirmaBack" style="gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            ${t('legal_back_to_list')}
        </button>` : '';
        openSidebar(t('legal_view_signature'), html, actions);
        if (docId) {
            document.getElementById('cdFirmaBack')?.addEventListener('click', () => viewLegalSignatures(docId));
        }
    } catch(e) { showToast(e.message || 'Error', 'error'); }
}

async function deleteLegalDoc(id, tipo) {
    if (!await cdConfirm(t('confirm_delete_legal_body'), { title: t('confirm_delete_legal_title'), type: 'danger', okText: t('btn_delete') })) return;
    try {
        await api(`${LEGAL_API}?id=${id}`, { method:'DELETE' });
        showToast(t('toast_legal_deleted'), 'success');
        if (tipo === 'terminos') _legalTcLoaded = false; else _legalPrivLoaded = false;
        loadLegalDocs(tipo);
    } catch(e) { showToast(e.message || 'Error', 'error'); }
}

async function viewLegalDoc(docId) {
    try {
        const doc = await api(`${LEGAL_API}?action=get&id=${docId}`);
        if (!doc) { showToast('Documento no encontrado', 'error'); return; }
        const tipoLabel = doc.tipo === 'terminos' ? t('config_tc_title') : t('config_privacy_title');
        const html = `
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span class="cd-badge cd-badge-neutral">v${esc(doc.version)}</span>
                ${doc.vigente == 1 ? `<span class="cd-badge cd-badge-success">${t('config_legal_active')}</span>` : `<span class="cd-badge cd-badge-neutral">${t('config_legal_inactive')}</span>`}
                ${doc.requiere_firma == 1 ? `<span class="cd-badge cd-badge-info">${t('config_legal_requires_sig')}</span>` : ''}
                <span class="cd-text-muted cd-text-xs">${doc.creado_at?.substring(0,16)?.replace('T',' ') || ''}</span>
            </div>
            <div class="cd-legal-preview-content cd-legal-rich">${renderLegalHtml(doc.contenido)}</div>
        `;
        openSidebar(`${tipoLabel}: ${esc(doc.titulo)}`, html, 'wide');
    } catch(e) { showToast(e.message || 'Error', 'error'); }
}

async function editLegalDoc(docId, tipo) {
    try {
        const doc = await api(`${LEGAL_API}?action=get&id=${docId}`);
        if (!doc) { showToast('Documento no encontrado', 'error'); return; }
        openLegalEditor(tipo, doc);
    } catch(e) { showToast(e.message || 'Error', 'error'); }
}

// Expose legal functions to global scope for inline onclick handlers
window.viewLegalDoc = viewLegalDoc;
window.editLegalDoc = editLegalDoc;
window.viewLegalSignatures = viewLegalSignatures;
window.viewFirmaAutografa = viewFirmaAutografa;
window.setLegalVigente = setLegalVigente;
window.deleteLegalDoc = deleteLegalDoc;

// Bind "new" buttons for legal docs
$('#cfgTcNew')?.addEventListener('click', () => openLegalEditor('terminos'));
$('#cfgPrivNew')?.addEventListener('click', () => openLegalEditor('privacidad'));

// AI config
$$('.cd-ia-provider').forEach(card => {
    card.addEventListener('click', () => {
        $$('.cd-ia-provider').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        const p = card.dataset.provider;
        if ($('#cfgIaProvider')) $('#cfgIaProvider').value = p;
        // Default model hint
        const modelDefaults = { openai:'gpt-4o-mini', gemini:'gemini-2.0-flash', deepseek:'deepseek-chat' };
        const modelInput = $('#cfgIaModel');
        if (modelInput && !modelInput.value) modelInput.value = modelDefaults[p] || '';
        // Update key hint per provider
        const hints = { openai:'platform.openai.com → API Keys', gemini:'aistudio.google.com → API Keys', deepseek:'platform.deepseek.com → API Keys' };
        const hint = $('#cfgIaKeyHint'); if (hint) hint.textContent = t('config_ia_find_at', {':provider': (hints[p] || t('config_ia_provider_panel'))});
    });
});

$('#cfgIaSave')?.addEventListener('click', async () => {
    const _btn = $('#cfgIaSave');
    btnLoading(_btn, t('status_saving'));
    const body = { seccion:'ia', ia_proveedor:$('#cfgIaProvider')?.value||'openai',
        ia_api_key:$('#cfgIaKey')?.value, ia_modelo:$('#cfgIaModel')?.value,
        ia_prompt:$('#cfgIaPrompt')?.value, ia_max_palabras:parseInt($('#cfgIaMaxPalabras')?.value)||400 };
    try {
        await api(CFG_API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
        showToast(t('toast_ai_saved'),'success');
        await _reloadSection('ia');
    } catch(e) {}
    btnReset(_btn);
});

$('#cfgIaTest')?.addEventListener('click', async () => {
    const _btn = $('#cfgIaTest');
    btnLoading(_btn, t('status_testing'));
    try {
        const res = await api(CFG_API + '?action=test_ia', { method:'PUT', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ ia_proveedor:$('#cfgIaProvider')?.value||'openai',
                ia_api_key:$('#cfgIaKey')?.value, ia_modelo:$('#cfgIaModel')?.value }) });
        showToast(res?.message || 'Conexión exitosa','success');
    } catch(e) {}
    btnReset(_btn);
});

