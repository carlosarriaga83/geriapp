// cd-expediente.js — Expediente médico module
// Extracted from cuidados.php (lines 16952)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════════════════════════════════
// EXPEDIENTE MÉDICO — Module
// ═══════════════════════════════════════════════════════════════════════════
(function initExpedienteModule() {
    const EXP_API = BASE + '/api/expediente.php';
    let _expPage = 1;
    let _expTotal = 0;
    let _expPages = 1;
    let _expDocs = [];
    let _expDebounce = null;

    const TIPO_ICONS = {
        receta:           '<svg viewBox="0 0 24 24"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="12" y2="17"/></svg>',
        laboratorio:      '<svg viewBox="0 0 24 24"><path d="M9 3v7.343a4 4 0 0 0 1.172 2.829L14 17a2 2 0 0 1 0 2.828L13.172 20.657A4 4 0 0 1 10.343 21H7.657a4 4 0 0 1-2.829-1.172L4 19a2 2 0 0 1 0-2.828l3.828-3.829A4 4 0 0 0 9 9.514V3"/><line x1="15" y1="3" x2="15" y2="7"/><path d="M15 7a4 4 0 0 1 4 4v0a4 4 0 0 1-4 4"/></svg>',
        imagen:           '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        interpretacion:   '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        hospitalizacion:  '<svg viewBox="0 0 24 24"><path d="M3 12h18"/><path d="M12 3v18"/><rect x="1" y="7" width="22" height="10" rx="2"/></svg>',
        legal:            '<svg viewBox="0 0 24 24"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 8v4l3 3"/></svg>',
        nota_enfermeria:  '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
        nota_medico:      '<svg viewBox="0 0 24 24"><path d="M9 11h6"/><path d="M12 8v6"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
    };

    const TIPO_LABELS = {
        receta: t('exp_tipo_receta'),
        laboratorio: t('exp_tipo_laboratorio'),
        imagen: t('exp_tipo_imagen'),
        interpretacion: t('exp_tipo_interpretacion'),
        hospitalizacion: t('exp_tipo_hospitalizacion'),
        legal: t('exp_tipo_legal'),
        nota_enfermeria: t('exp_tipo_nota_enfermeria'),
        nota_medico: t('exp_tipo_nota_medico'),
    };

    const FUENTE_LABELS = {
        medico: t('exp_fuente_medico'),
        familiar: t('exp_fuente_familiar'),
        residente: t('exp_fuente_residente'),
        otro: t('exp_fuente_otro'),
    };

    function fmtDateShort(d) {
        if (!d) return '';
        try { return (typeof fmtDate === 'function') ? fmtDate(d) : d; }
        catch(e) { return d; }
    }

    function expDatePlaceholder() {
        return (APP_DATE_FMT || 'Y-m-d')
            .replace('d', 'DD')
            .replace('m', 'MM')
            .replace('Y', 'AAAA');
    }

    function expParseDateInput(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const parts = raw.match(/^\d{1,4}([\/\-])\d{1,2}\1\d{1,4}$/) ? raw.split(/[\/\-]/) : null;
        if (!parts || parts.length !== 3) return null;
        let y, m, d;
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
        if (String(y).length === 2) y = '20' + y;
        y = String(y).padStart(4, '0');
        m = String(m).padStart(2, '0');
        d = String(d).padStart(2, '0');
        const iso = `${y}-${m}-${d}`;
        const dt = new Date(iso + 'T00:00:00');
        if (Number.isNaN(dt.getTime()) || dt.getFullYear() !== parseInt(y) || (dt.getMonth() + 1) !== parseInt(m) || dt.getDate() !== parseInt(d)) return null;
        return iso;
    }

    function expFormatDateFilterValue(input) {
        if (!input) return;
        const iso = expParseDateInput(input.value);
        if (iso) input.value = fmtDateShort(iso);
    }

    function expDateField(input) {
        return input?.closest?.('.cd-exp-date-field') || input?.parentNode || null;
    }

    function expSyncNativeDatePicker(input) {
        if (!input) return;
        const native = document.querySelector(`[data-exp-date-native="${input.id}"]`);
        if (!native) return;
        const iso = expParseDateInput(input.value);
        native.value = iso || '';
    }

    function expSetDateFilterValue(inputId, iso, reload = true) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.value = iso ? fmtDateShort(iso) : '';
        input.classList.remove('cd-input-error');
        const err = expDateField(input)?.querySelector('.cd-exp-date-error');
        if (err) err.textContent = '';
        expSyncNativeDatePicker(input);
        if (reload) loadExpediente(1);
    }

    function expDateFilterIso(id) {
        const input = $(id);
        if (!input) return '';
        const iso = expParseDateInput(input.value);
        const err = expDateField(input)?.querySelector('.cd-exp-date-error');
        if (err) err.textContent = input.value && iso === null ? expDatePlaceholder() : '';
        input.classList.toggle('cd-input-error', !!(input.value && iso === null));
        return iso || '';
    }

    function initExpDateFilters() {
        $$('#cdExpFilterDesde, #cdExpFilterHasta').forEach(input => {
            input.placeholder = expDatePlaceholder();
            const field = expDateField(input);
            if (field && !field.querySelector('.cd-exp-date-error')) {
                const err = document.createElement('div');
                err.className = 'cd-exp-date-error';
                field.appendChild(err);
            }
            expSyncNativeDatePicker(input);
            if (!input.dataset.expDateInit) {
                input.dataset.expDateInit = '1';
                input.addEventListener('blur', () => { expFormatDateFilterValue(input); expSyncNativeDatePicker(input); });
                input.addEventListener('input', () => {
                    if (!input.value) expSyncNativeDatePicker(input);
                    input.classList.remove('cd-input-error');
                    const err = field?.querySelector('.cd-exp-date-error');
                    if (err) err.textContent = '';
                });
            }
            const native = document.querySelector(`[data-exp-date-native="${input.id}"]`);
            if (native && !native.dataset.expDateInit) {
                native.dataset.expDateInit = '1';
                native.addEventListener('change', () => expSetDateFilterValue(input.id, native.value, true));
            }
        });
    }

    window._refreshExpDateFilters = initExpDateFilters;

    /** Strip HTML tags & decode entities — used to clean legacy descripciones que contienen <ol><li>… */
    function stripHtml(s) {
        if (!s) return '';
        const tmp = document.createElement('div');
        tmp.innerHTML = String(s)
            .replace(/<\s*li[^>]*>/gi, '• ')
            .replace(/<\s*\/li[^>]*>/gi, ' ')
            .replace(/<\s*br\s*\/?\s*>/gi, ' ');
        return (tmp.textContent || tmp.innerText || '').replace(/\s+/g,' ').trim();
    }

    /** Si la descripción de una nota_medico/receta es el JSON SOAP (v:2),
     *  produce un resumen corto "S: …  A: …  P: …"; si no es JSON válido,
     *  cae a stripHtml(descripcion). */
    function nmExpDescSummary(doc) {
        if (!doc || !doc.descripcion) return '';
        const raw = String(doc.descripcion).trim();
        if ((doc.tipo === 'nota_medico' || doc.tipo === 'receta') && raw.startsWith('{')) {
            try {
                const p = JSON.parse(raw);
                if (p && p.v === 2) {
                    const parts = [];
                    const clean = s => stripHtml(String(s || '')).slice(0, 140);
                    if (p.subjetivo) parts.push('S: ' + clean(p.subjetivo));
                    if (p.analisis)  parts.push('A: ' + clean(p.analisis));
                    if (p.plan)      parts.push('P: ' + clean(p.plan));
                    if (Array.isArray(p.diagnosticos) && p.diagnosticos.length) {
                        parts.push('Dx: ' + p.diagnosticos.map(d => d.codigo).filter(Boolean).join(', '));
                    }
                    return parts.join(' | ') || 'Nota médica registrada';
                }
            } catch(e) {}
        }
        return stripHtml(raw);
    }

    function expDateOnly(value) {
        return String(value || '').slice(0, 10);
    }

    async function findMedicalNoteForExpDoc(doc) {
        const residentId = parseInt(doc.residente_id || _residenteId) || 0;
        if (!residentId || typeof API_URL === 'undefined') return null;
        const res = await api(`${API_URL}?notas_medico=1&residente_id=${residentId}&archivo_limit=200`);
        const notes = [res?.vigente, ...(res?.archivo || [])].filter(Boolean);
        const docBody = String(doc.descripcion || '').trim();
        const exact = docBody ? notes.find(n => String(n.contenido || '').trim() === docBody) : null;
        if (exact) return exact;
        const docDate = expDateOnly(doc.fecha_documento || doc.created_at);
        return notes.find(n =>
            (!doc.created_by || parseInt(n.usuario_id) === parseInt(doc.created_by)) &&
            expDateOnly(n.creado_at) === docDate
        ) || null;
    }

    async function openMedicalNoteEditorFromExp(doc) {
        if (typeof window.nmOpenEditorFromNote !== 'function') {
            showToast('No se pudo abrir el editor de nota médica', 'error');
            return;
        }
        try {
            const note = await findMedicalNoteForExpDoc(doc);
            if (!note) {
                showToast('No se encontró la nota médica original para editar', 'error');
                return;
            }
            closeSidebar();
            await window.nmOpenEditorFromNote(note, { expedienteDocId: doc.id });
        } catch (e) {
            showToast(e.message || 'No se pudo abrir la nota médica', 'error');
        }
    }

    /** Parse archivos from doc (archivos_json or legacy columns) */
    function getArchivos(doc) {
        if (doc.archivos_json) {
            try {
                const arr = typeof doc.archivos_json === 'string' ? JSON.parse(doc.archivos_json) : doc.archivos_json;
                if (Array.isArray(arr) && arr.length) return arr;
            } catch(e) {}
        }
        if (doc.archivo_path) {
            return [{ nombre: doc.archivo_nombre || 'archivo', tipo: doc.archivo_tipo || '', path: doc.archivo_path }];
        }
        return [];
    }

    // ── Load documents ──────────────────────────────────────────
    function expTimelineSkeleton(n = 4) {
        let h = '<div class="cd-exp-tl-date cd-skeleton" style="width:90px;height:14px;border-radius:6px;margin-left:-36px">&nbsp;</div>';
        for (let i = 0; i < n; i++) {
            h += `<div class="cd-exp-tl-ghost">
                <div class="cd-exp-tl-ghost-icon cd-skeleton"></div>
                <div class="cd-exp-tl-ghost-body">
                    <div class="cd-exp-tl-ghost-line cd-skeleton ${i % 2 ? 'med' : 'long'}"></div>
                    <div class="cd-exp-tl-ghost-line cd-skeleton short"></div>
                </div>
            </div>`;
        }
        return h;
    }

    let _expSortOrder = 'DESC';

    async function loadExpediente(page = 1) {
        const requestedResidentId = parseInt(_residenteId) || 0;
        if (!requestedResidentId) {
            _expDocs = [];
            _expTotal = 0;
            _expPages = 1;
            renderExpTimeline();
            return;
        }
        _expPage = page;
        const grid = $('#cdExpGrid');
        if (page === 1 && grid) { grid.innerHTML = expTimelineSkeleton(4); }
        const params = new URLSearchParams({
            residente_id: requestedResidentId,
            page: page,
            tipo: $('#cdExpFilterTipo')?.value || '',
            fuente: $('#cdExpFilterFuente')?.value || '',
            q: $('#cdExpSearch')?.value?.trim() || '',
            desde: expDateFilterIso('#cdExpFilterDesde'),
            hasta: expDateFilterIso('#cdExpFilterHasta'),
            orden: _expSortOrder,
        });
        try {
            const res = await api(EXP_API + '?' + params.toString());
            if ((parseInt(_residenteId) || 0) !== requestedResidentId) return;
            const docs = (res.docs || []).filter(doc => (parseInt(doc.residente_id) || 0) === requestedResidentId);
            const currentDocs = page === 1
                ? []
                : _expDocs.filter(doc => (parseInt(doc.residente_id) || 0) === requestedResidentId);
            _expDocs = page === 1 ? docs : [...currentDocs, ...docs];
            _expTotal = res.total;
            _expPages = res.pages;
            renderExpTimeline();
        } catch (e) {
            showToast(e.message || 'Error', 'error');
        }
    }

    // ── Render timeline ─────────────────────────────────────────
    function renderExpTimeline() {
        const grid = $('#cdExpGrid');
        const empty = $('#cdExpEmpty');
        const pager = $('#cdExpPager');
        if (!grid) return;

        if (!_expDocs.length) {
            grid.innerHTML = '';
            empty.style.display = 'block';
            pager.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        pager.style.display = _expPage < _expPages ? 'block' : 'none';

        let html = '';
        let lastDate = '';

        _expDocs.forEach(doc => {
            const tipoIcon = TIPO_ICONS[doc.tipo] || TIPO_ICONS.receta;
            const tipoLabel = TIPO_LABELS[doc.tipo] || doc.tipo;
            const fuenteLabel = FUENTE_LABELS[doc.fuente] || doc.fuente;
            const archivos = getArchivos(doc);
            const nFiles = archivos.length;
            const dateStr = doc.fecha_documento || '';

            // Date separator
            if (dateStr && dateStr !== lastDate) {
                html += `<div class="cd-exp-tl-date">${fmtDateShort(dateStr)}</div>`;
                lastDate = dateStr;
            }

            // Files badge
            let filesBadge = '';
            if (nFiles > 0) {
                const label = nFiles === 1
                    ? (archivos[0].tipo && archivos[0].tipo.startsWith('image/') ? '1 imagen' : archivos[0].tipo === 'application/pdf' ? '1 PDF' : '1 archivo')
                    : nFiles + ' archivos';
                filesBadge = `<span class="cd-exp-tl-files"><svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" fill="none" stroke="currentColor" stroke-width="2"/></svg> ${label}</span>`;
            }

            html += `<div class="cd-exp-tl-item cd-exp-tipo-${esc(doc.tipo)}" data-id="${doc.id}">
                <div class="cd-exp-card-icon" style="background:${getExpColor(doc.tipo)}">${tipoIcon}</div>
                <div class="cd-exp-tl-body">
                    <div class="cd-exp-tl-row1">
                        <span class="cd-exp-tl-title" title="${esc(doc.titulo)}">${esc(doc.titulo)}</span>
                        ${doc.nombre_fuente ? `<span class="cd-exp-tl-doctor"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ${esc(doc.nombre_fuente)}</span>` : ''}
                        <span class="cd-exp-card-badge">${esc(tipoLabel)}</span>
                    </div>
                    <div class="cd-exp-tl-meta">
                        ${doc.especialidad ? `<span class="cd-exp-tl-especialidad"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2" fill="none" stroke="currentColor" stroke-width="2"/></svg> ${esc(doc.especialidad)}</span>` : ''}
                        ${doc.autor ? `<span><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> ${esc(doc.autor)}</span>` : ''}
                        ${filesBadge}
                    </div>
                </div>
            </div>`;
        });

        grid.innerHTML = html;

        // Click handlers
        $$('.cd-exp-tl-item', grid).forEach(item => {
            item.addEventListener('click', () => {
                const doc = _expDocs.find(d => d.id == item.dataset.id);
                if (doc) openExpDetail(doc);
            });
        });
    }

    // ── Detail sidebar ──────────────────────────────────────────
    function openExpDetail(doc) {
        const archivos = getArchivos(doc);
        const hasFiles = archivos.length > 0;

        // Build file gallery
        let fileHtml = '';
        if (hasFiles) {
            const galleryItems = archivos.map((a, idx) => {
                const url = BASE + '/api/expediente.php?action=serve&id=' + doc.id + '&idx=' + idx + '&residente_id=' + encodeURIComponent(_residenteId || '');
                const isImg = a.tipo && a.tipo.startsWith('image/');
                const isPdf = a.tipo === 'application/pdf';
                if (isImg) {
                    return `<div class="cd-exp-file-gallery-item" data-url="${esc(url)}" data-tipo="${esc(a.tipo)}" data-nombre="${esc(a.nombre)}"><img src="${esc(url)}" alt="" loading="lazy"></div>`;
                } else if (isPdf) {
                    return `<div class="cd-exp-file-gallery-item" data-url="${esc(url)}" data-tipo="${esc(a.tipo)}" data-nombre="${esc(a.nombre)}"><span class="cd-exp-fg-pdf"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--cd-accent)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><br>PDF</span></div>`;
                }
                return '';
            }).join('');
            fileHtml = `<div class="cd-exp-file-gallery">${galleryItems}</div>`;
        } else {
            fileHtml = `<div style="margin:8px 0;font-size:.8125rem;color:var(--cd-text-muted)">${t('exp_no_file')}</div>`;
        }

        const body = `<div class="cd-sidebar-section" style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <div class="cd-exp-card-icon" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:${getExpColor(doc.tipo)}">${TIPO_ICONS[doc.tipo] || TIPO_ICONS.receta}</div>
                <div><div style="font-weight:600;color:var(--cd-text)">${esc(doc.titulo)}</div>
                <span class="cd-exp-card-badge">${esc(TIPO_LABELS[doc.tipo] || doc.tipo)}</span></div>
            </div>
            ${(() => {
                // Nota médica: render SOAP con el mismo layout del módulo de cuidados
                if ((doc.tipo === 'nota_medico' || doc.tipo === 'receta') && doc.descripcion && typeof window.nmParseContenido === 'function') {
                    const parsed = window.nmParseContenido(doc.descripcion);
                    if (parsed && parsed.v === 2) {
                        const sections = window.nmRenderSections(parsed);
                        if (sections) return `<div class="cd-nm-card" style="margin:4px 0;background:transparent;border:1px solid var(--cd-border);border-radius:var(--cd-radius)">${sections}</div>`;
                    }
                }
                const txt = nmExpDescSummary(doc);
                return txt ? `<div style="font-size:.8125rem;color:var(--cd-text-muted);line-height:1.5;white-space:pre-wrap">${esc(txt)}</div>` : '';
            })()}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:.75rem">
                <div><strong>${t('exp_doc_source')}:</strong> ${esc(FUENTE_LABELS[doc.fuente] || doc.fuente)}</div>
                <div><strong>${t('exp_doc_source_name')}:</strong> ${esc(doc.nombre_fuente || '\u2014')}</div>
                <div><strong>${t('exp_doc_date')}:</strong> ${doc.fecha_documento ? fmtDateShort(doc.fecha_documento) : '\u2014'}</div>
                <div><strong>${t('exp_uploaded_by')}:</strong> ${esc(doc.autor || '\u2014')}</div>
                <div><strong>${t('exp_especialidad')}:</strong> ${esc(doc.especialidad || '\u2014')}</div>
            </div>
            ${fileHtml}
        </div>`;

        const isAdmin = <?= json_encode(in_array($userRole, ['admin','superadmin'])) ?>;
        const isSuperadmin = (typeof USER_ROLE !== 'undefined') && USER_ROLE === 'superadmin';
        const isAuthor = parseInt(doc.created_by) === CURRENT_USER_ID;
        const isMedicalNoteDoc = doc.tipo === 'nota_medico' || doc.tipo === 'receta';
        const canEditMedicalNote = isMedicalNoteDoc && (isSuperadmin || (IS_DOCTOR && isAuthor));
        const canEditExp = isMedicalNoteDoc ? canEditMedicalNote : (isAdmin || isAuthor);
        const actions = `
            <button class="cd-btn-submit cd-btn-secondary${canEditExp ? '' : ' cd-role-locked'}" id="cdExpDetailEdit" data-perm-id="exp_edit_document_btn" style="flex:1" ${!canEditExp ? `data-cd-locked data-lock-title="Permiso insuficiente" data-lock-msg="Solo el autor del documento o un administrador puede editarlo."` : ''}>${t('btn_edit')}</button>
            <button class="cd-btn-submit${isAdmin ? '' : ' cd-role-locked'}" id="cdExpDetailDelete" data-perm-id="exp_delete_document_btn" style="background:${isAdmin ? 'var(--cd-danger)' : 'var(--cd-danger)'};color:#fff" ${!isAdmin ? `data-cd-locked data-lock-title="Solo administradores" data-lock-msg="Solo los administradores pueden eliminar documentos del expediente."` : ''}>${t('btn_delete')}</button>
            <button class="cd-btn-submit cd-btn-secondary" id="cdExpDetailClose">${t('btn_close')}</button>`;

        openSidebar(doc.titulo, body, actions, {wide:true});

        // Gallery click handlers
        $$('.cd-exp-file-gallery-item').forEach(item => {
            item.addEventListener('click', async () => {
                if (item.classList.contains('is-loading')) return;
                item.classList.add('is-loading');
                const url = item.dataset.url;
                const tipo = item.dataset.tipo;
                try {
                    if (tipo && tipo.startsWith('image/')) {
                        openExpLightbox(url, item.dataset.nombre || '');
                    } else if (tipo && tipo.includes('pdf')) {
                        await openExpPdfViewer(url);
                    } else {
                        window.open(url, '_blank');
                    }
                } catch(e) { /* ignore */ }
                item.classList.remove('is-loading');
            });
        });

        // Edit
        $('#cdExpDetailEdit')?.addEventListener('click', () => {
            if (doc.tipo === 'nota_medico' || doc.tipo === 'receta') openMedicalNoteEditorFromExp(doc);
            else openExpForm(doc);
        });
        // Delete
        $('#cdExpDetailDelete')?.addEventListener('click', async () => {
            if (!confirm(t('exp_confirm_delete'))) return;
            try {
                await api(EXP_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'eliminar', id: doc.id, residente_id: _residenteId }) });
                showToast(t('exp_deleted'), 'success');
                closeSidebar();
                loadExpediente(1);
            } catch(e) { showToast(e.message, 'error'); }
        });
        $('#cdExpDetailClose')?.addEventListener('click', closeSidebar);
    }

    function getExpColor(tipo) {
        const c = { receta:'#3b82f6', laboratorio:'#8b5cf6', imagen:'#06b6d4', interpretacion:'#f59e0b', hospitalizacion:'#ef4444', legal:'#64748b', nota_enfermeria:'#22c55e', nota_medico:'#0ea5e9' };
        return c[tipo] || '#3b82f6';
    }

    // ── Image lightbox ──────────────────────────────────────────
    function openExpLightbox(url, title) {
        document.getElementById('cdExpLightbox')?.remove();
        const lb = document.createElement('div');
        lb.id = 'cdExpLightbox';
        lb.className = 'cd-exp-lightbox';

        // State
        let scale = 1, tx = 0, ty = 0, dragging = false, sx = 0, sy = 0, rotation = 0;
        const img = new Image();
        img.src = url;  img.alt = title;  img.draggable = false;
        img.className = 'cd-exp-lb-img';
        const apply = () => { img.style.transform = `translate(${tx}px,${ty}px) scale(${scale}) rotate(${rotation}deg)`; };

        // Close
        const closeBtn = document.createElement('button');
        closeBtn.className = 'cd-exp-lightbox-close';
        closeBtn.textContent = '\u2715 ' + t('btn_close');

        // Zoom bar
        const zoomBar = document.createElement('div');
        zoomBar.className = 'cd-exp-lb-zoom';
        zoomBar.innerHTML = '<button data-z="out">\u2212</button><button data-z="reset">1\u00d7</button><button data-z="in">+</button><button data-z="rotate" title="Rotar"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></button>';

        const infoDiv = document.createElement('div');
        infoDiv.className = 'cd-exp-lightbox-info';
        infoDiv.textContent = title;

        lb.append(closeBtn, img, zoomBar, infoDiv);

        const close = () => { lb.remove(); document.removeEventListener('keydown', escH); };
        closeBtn.addEventListener('click', close);
        lb.addEventListener('click', e => { if (e.target === lb) close(); });
        const escH = e => { if (e.key === 'Escape') close(); };
        document.addEventListener('keydown', escH);

        // Wheel zoom
        lb.addEventListener('wheel', e => {
            e.preventDefault();
            scale = Math.max(.5, Math.min(10, scale + (e.deltaY > 0 ? -.2 : .2)));
            if (scale <= 1) { tx = 0; ty = 0; }
            apply();
        }, { passive: false });

        // Mouse drag
        img.addEventListener('mousedown', e => {
            if (scale <= 1) return;
            dragging = true; sx = e.clientX - tx; sy = e.clientY - ty;
            img.style.cursor = 'grabbing'; e.preventDefault();
        });
        document.addEventListener('mousemove', e => {
            if (!dragging) return;
            tx = e.clientX - sx; ty = e.clientY - sy; apply();
        });
        document.addEventListener('mouseup', () => {
            if (dragging) { dragging = false; img.style.cursor = ''; }
        });

        // Touch: pinch + pan
        let lastDist = 0, lastTouch = null;
        img.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                lastDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            } else if (e.touches.length === 1 && scale > 1) {
                lastTouch = { x: e.touches[0].clientX - tx, y: e.touches[0].clientY - ty };
            }
        }, { passive: true });
        img.addEventListener('touchmove', e => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const d = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
                if (lastDist) { scale = Math.max(.5, Math.min(10, scale * (d / lastDist))); apply(); }
                lastDist = d;
            } else if (e.touches.length === 1 && lastTouch && scale > 1) {
                e.preventDefault();
                tx = e.touches[0].clientX - lastTouch.x; ty = e.touches[0].clientY - lastTouch.y; apply();
            }
        }, { passive: false });
        img.addEventListener('touchend', () => { lastDist = 0; lastTouch = null; });

        // Zoom buttons
        zoomBar.addEventListener('click', e => {
            const z = e.target.closest('[data-z]')?.dataset.z;
            if (z === 'in') scale = Math.min(10, scale + .35);
            else if (z === 'out') scale = Math.max(.5, scale - .35);
            else if (z === 'reset') { scale = 1; tx = 0; ty = 0; rotation = 0; }
            else if (z === 'rotate') { rotation = (rotation + 90) % 360; }
            apply();
        });

        // Double-tap / double-click toggle
        img.addEventListener('dblclick', () => {
            if (scale > 1) { scale = 1; tx = 0; ty = 0; } else { scale = 2.5; }
            apply();
        });

        document.body.appendChild(lb);
    }

    // ── PDF viewer ──────────────────────────────────────────────
    async function openExpPdfViewer(url) {
        const isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform());
        if (isNative) {
            try {
                const resp = await fetch(url, { credentials: 'include' });
                const buf = await resp.arrayBuffer();
                if (typeof _showPdfCanvasOverlay === 'function') {
                    _showPdfCanvasOverlay(buf);
                } else {
                    showToast('Visor PDF no disponible', 'error');
                }
            } catch(e) {
                showToast(e.message || 'Error', 'error');
            }
        } else {
            if (typeof _showPdfOverlay === 'function') {
                _showPdfOverlay(url, false);
            } else {
                window.open(url, '_blank');
            }
        }
    }

    // ── Create/Edit form sidebar ────────────────────────────────
    function openExpForm(editDoc = null) {
        const isEdit = !!editDoc;
        const isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform());

        const body = `<div class="cd-exp-form-section">
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_doc_title')} <span style="color:var(--cd-danger)">*</span></label>
                <input type="text" class="cd-input" id="cdExpFrmTitulo" value="${esc(editDoc?.titulo || '')}" placeholder="${t('exp_doc_title')}">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_doc_type')}</label>
                <select class="cd-input cd-select-native" id="cdExpFrmTipo">
                    <option value="receta" ${editDoc?.tipo === 'receta' ? 'selected' : ''}>${t('exp_tipo_receta')}</option>
                    <option value="laboratorio" ${editDoc?.tipo === 'laboratorio' ? 'selected' : ''}>${t('exp_tipo_laboratorio')}</option>
                    <option value="imagen" ${editDoc?.tipo === 'imagen' ? 'selected' : ''}>${t('exp_tipo_imagen')}</option>
                    <option value="interpretacion" ${editDoc?.tipo === 'interpretacion' ? 'selected' : ''}>${t('exp_tipo_interpretacion')}</option>
                    <option value="hospitalizacion" ${editDoc?.tipo === 'hospitalizacion' ? 'selected' : ''}>${t('exp_tipo_hospitalizacion')}</option>
                    <option value="legal" ${editDoc?.tipo === 'legal' ? 'selected' : ''}>${t('exp_tipo_legal')}</option>
                    <option value="nota_enfermeria" ${editDoc?.tipo === 'nota_enfermeria' ? 'selected' : ''}>${t('exp_tipo_nota_enfermeria')}</option>
                    <option value="nota_medico" ${editDoc?.tipo === 'nota_medico' ? 'selected' : ''}>${t('exp_tipo_nota_medico')}</option>
                </select>
            </div>
            <div style="display:flex;gap:8px">
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('exp_doc_source')}</label>
                    <select class="cd-input cd-select-native" id="cdExpFrmFuente">
                        <option value="medico" ${editDoc?.fuente === 'medico' ? 'selected' : ''}>${t('exp_fuente_medico')}</option>
                        <option value="familiar" ${editDoc?.fuente === 'familiar' ? 'selected' : ''}>${t('exp_fuente_familiar')}</option>
                        <option value="residente" ${editDoc?.fuente === 'residente' ? 'selected' : ''}>${t('exp_fuente_residente')}</option>
                        <option value="otro" ${editDoc?.fuente === 'otro' ? 'selected' : ''}>${t('exp_fuente_otro')}</option>
                    </select>
                </div>
                <div class="cd-form-group" style="flex:1">
                    <label class="cd-form-label">${t('exp_doc_source_name')}</label>
                    <input type="text" class="cd-input" id="cdExpFrmFuenteNombre" value="${esc(editDoc?.nombre_fuente || '')}" placeholder="${t('exp_doc_source_ph')}">
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_especialidad')}</label>
                <div class="cd-exp-esp-wrap" style="position:relative">
                    <input type="text" class="cd-input" id="cdExpFrmEspecialidad" value="${esc(editDoc?.especialidad || '')}" placeholder="${t('exp_especialidad_ph')}" autocomplete="off">
                    <div class="cd-exp-esp-dropdown" id="cdExpFrmEspDropdown" style="display:none"></div>
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_doc_date')}</label>
                <input type="text" class="cd-input cd-app-date-input" id="cdExpFrmFecha" value="${esc(fmtDate(editDoc?.fecha_documento || nowInTz().date))}" data-iso="${esc(editDoc?.fecha_documento || nowInTz().date)}" placeholder="${appDatePlaceholder()}" inputmode="numeric">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">${t('exp_doc_desc')}</label>
                <textarea class="cd-textarea" id="cdExpFrmDesc" rows="3" placeholder="${t('exp_doc_desc')}">${esc(editDoc?.descripcion || '')}</textarea>
            </div>
            ${!isEdit ? `<div class="cd-form-group">
                <label class="cd-form-label">${t('exp_doc_file')}</label>
                <div class="cd-exp-file-area" id="cdExpFrmFileArea">
                    <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div style="font-size:.8125rem;color:var(--cd-text-muted)">${t('exp_doc_file_hint')}</div>
                    <div class="cd-exp-file-buttons">
                        ${isNative ? `<button type="button" class="cd-btn-submit cd-btn-secondary" id="cdExpFrmCamera" data-perm-id="exp_camera_capture_btn" style="font-size:.8125rem;padding:6px 12px">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            ${t('exp_btn_camera')}</button>` : ''}
                        <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdExpFrmFilePick" data-perm-id="exp_pick_file_btn" style="font-size:.8125rem;padding:6px 12px">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            ${t('exp_btn_file')}</button>
                    </div>
                    <input type="file" id="cdExpFrmFileInput" accept="image/jpeg,image/png,image/webp,application/pdf" multiple style="display:none">
                </div>
                <div class="cd-exp-files-list" id="cdExpFrmFilesList"></div>
            </div>` : ''}
        </div>`;

        const saveLabel = isEdit ? t('btn_update') : t('btn_save');
        const actions = `<button class="cd-btn-submit" id="cdExpFrmSave" data-perm-id="exp_save_document_btn">${saveLabel}</button>
            <button class="cd-btn-submit cd-btn-secondary" id="cdExpFrmCancel">${t('btn_cancel')}</button>`;

        openSidebar(isEdit ? t('btn_edit') : t('exp_add'), body, actions, {wide:true});
        initAppDateTextInput($('#cdExpFrmFecha'));

        let _expFiles = [];

        function renderFilesList() {
            const list = $('#cdExpFrmFilesList');
            if (!list) return;
            if (!_expFiles.length) { list.innerHTML = ''; return; }
            list.innerHTML = _expFiles.map((f, i) => {
                const isImg = f.type.startsWith('image/');
                const thumbSrc = isImg ? URL.createObjectURL(f) : '';
                return `<div class="cd-exp-files-list-item" data-idx="${i}">
                    ${isImg ? `<img src="${thumbSrc}" alt="">` : `<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="var(--cd-accent)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`}
                    <span class="cd-exp-fl-name">${esc(f.name)}</span>
                    <button class="cd-exp-fl-remove" data-idx="${i}">\u2715</button>
                </div>`;
            }).join('');
            $$('.cd-exp-fl-remove', list).forEach(btn => {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    _expFiles.splice(parseInt(btn.dataset.idx), 1);
                    renderFilesList();
                });
            });
        }

        function addFiles(fileList) {
            for (const f of fileList) {
                if (f.size > 10 * 1024 * 1024) { showToast('Maximo 10 MB por archivo', 'error'); continue; }
                _expFiles.push(f);
            }
            renderFilesList();
        }

        // File pick (multiple)
        $('#cdExpFrmFilePick')?.addEventListener('click', () => $('#cdExpFrmFileInput')?.click());
        $('#cdExpFrmFileInput')?.addEventListener('change', e => {
            if (e.target.files.length) addFiles(e.target.files);
            e.target.value = '';
        });

        // Camera (Capacitor)
        $('#cdExpFrmCamera')?.addEventListener('click', async () => {
            try {
                const Camera = window.Capacitor?.Plugins?.Camera;
                if (!Camera) { showToast('Camara no disponible', 'error'); return; }
                const photo = await Camera.getPhoto({
                    quality: 80,
                    resultType: 'dataUrl',
                    source: 'CAMERA',
                    correctOrientation: true,
                    width: 2048,
                    height: 2048,
                });
                const resp = await fetch(photo.dataUrl);
                const blob = await resp.blob();
                const file = new File([blob], 'foto_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                addFiles([file]);
            } catch(e) {
                if (e.message !== 'User cancelled photos app') showToast(e.message || 'Error', 'error');
            }
        });

        // Save
        $('#cdExpFrmSave')?.addEventListener('click', async () => {
            const titulo = $('#cdExpFrmTitulo').value.trim();
            if (!titulo) { showToast(t('exp_doc_title') + ' obligatorio', 'error'); return; }
            const fechaDocumento = appDateInputIso($('#cdExpFrmFecha'), { message:t('exp_doc_date') + ': ' + appDatePlaceholder() });
            if (fechaDocumento === null) return;

            const btn = $('#cdExpFrmSave');
            btnLoading(btn, t('status_saving'));

            try {
                if (isEdit) {
                    await api(EXP_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                        action: 'actualizar',
                        id: editDoc.id,
                        residente_id: _residenteId,
                        titulo,
                        tipo: $('#cdExpFrmTipo').value,
                        fuente: $('#cdExpFrmFuente').value,
                        nombre_fuente: $('#cdExpFrmFuenteNombre').value.trim() || null,
                        especialidad: $('#cdExpFrmEspecialidad').value.trim() || null,
                        fecha_documento: fechaDocumento || null,
                        descripcion: $('#cdExpFrmDesc').value.trim() || null,
                    })});
                    showToast(t('exp_updated'), 'success');
                } else {
                    const form = new FormData();
                    form.append('action', 'crear');
                    form.append('residente_id', _residenteId);
                    form.append('titulo', titulo);
                    form.append('tipo', $('#cdExpFrmTipo').value);
                    form.append('fuente', $('#cdExpFrmFuente').value);
                    form.append('nombre_fuente', $('#cdExpFrmFuenteNombre').value.trim());
                    form.append('especialidad', $('#cdExpFrmEspecialidad').value.trim());
                    form.append('fecha_documento', fechaDocumento || '');
                    form.append('descripcion', $('#cdExpFrmDesc').value.trim());
                    _expFiles.forEach(f => form.append('archivos[]', f));
                    await api(EXP_API, { method:'POST', body: form });
                    showToast(t('exp_saved'), 'success');
                }
                closeSidebar();
                loadExpediente(1);
            } catch(e) {
                showToast(e.message || 'Error', 'error');
                btnReset(btn);
            }
        });

        $('#cdExpFrmCancel')?.addEventListener('click', closeSidebar);

        // ── Searchable specialty dropdown ─────────────────────────
        const ESP_LIST = [
            'Algología (Medicina del Dolor)','Alergología e Inmunología','Anatomía Patológica',
            'Anestesiología','Angiología y Cirugía Vascular','Audiología','Cardiología',
            'Cirugía Cardiovascular','Cirugía de Tórax','Cirugía General',
            'Cirugía Maxilofacial','Cirugía Oncológica','Cirugía Pediátrica',
            'Cirugía Plástica y Reconstructiva','Dermatología','Endocrinología',
            'Endoscopia','Fisioterapia','Foniatría','Gastroenterología','Genética Médica',
            'Geriatría','Ginecología y Obstetricia','Hematología','Hepatología',
            'Infectología','Medicina Crítica','Medicina de Rehabilitación',
            'Medicina de Urgencias','Medicina del Deporte','Medicina del Trabajo',
            'Medicina Familiar','Medicina General','Medicina Interna','Medicina Nuclear',
            'Medicina Preventiva','Nefrología','Neonatología','Neumología','Neurocirugía',
            'Neurología','Nutrición','Odontología','Oftalmología','Oncología Médica',
            'Optometría','Otorrinolaringología','Pediatría','Podología','Proctología',
            'Psicología','Psiquiatría','Radiología e Imagen','Reumatología',
            'Terapia Ocupacional','Traumatología y Ortopedia','Urología',
        ];
        const espInput = $('#cdExpFrmEspecialidad');
        const espDrop = $('#cdExpFrmEspDropdown');
        if (espInput && espDrop) {
            function renderEspDrop(filter = '') {
                const q = filter.toLowerCase();
                const matches = q ? ESP_LIST.filter(e => e.toLowerCase().includes(q)) : ESP_LIST;
                if (!matches.length) { espDrop.style.display = 'none'; return; }
                espDrop.innerHTML = matches.map(e => `<div class="cd-exp-esp-opt">${esc(e)}</div>`).join('');
                espDrop.style.display = 'block';
                espDrop.querySelectorAll('.cd-exp-esp-opt').forEach(opt => {
                    opt.addEventListener('mousedown', ev => {
                        ev.preventDefault();
                        espInput.value = opt.textContent;
                        espDrop.style.display = 'none';
                    });
                });
            }
            espInput.addEventListener('focus', () => renderEspDrop(espInput.value));
            espInput.addEventListener('input', () => renderEspDrop(espInput.value));
            espInput.addEventListener('blur', () => { setTimeout(() => espDrop.style.display = 'none', 150); });
        }
    }

    // ── Event handlers ──────────────────────────────────────────
    $('#cdExpAddBtn')?.addEventListener('click', () => {
        if (!_residenteId) { showToast('Selecciona un residente', 'error'); return; }
        openExpForm();
    });

    $('#cdExpSearch')?.addEventListener('input', () => {
        clearTimeout(_expDebounce);
        _expDebounce = setTimeout(() => loadExpediente(1), 350);
    });
    $('#cdExpFilterTipo')?.addEventListener('change', () => loadExpediente(1));
    $('#cdExpFilterFuente')?.addEventListener('change', () => loadExpediente(1));
    initExpDateFilters();
    $('#cdExpFilterDesde')?.addEventListener('change', () => { const input = $('#cdExpFilterDesde'); expFormatDateFilterValue(input); expSyncNativeDatePicker(input); loadExpediente(1); });
    $('#cdExpFilterHasta')?.addEventListener('change', () => { const input = $('#cdExpFilterHasta'); expFormatDateFilterValue(input); expSyncNativeDatePicker(input); loadExpediente(1); });
    $('#cdExpSortBtn')?.addEventListener('click', () => {
        _expSortOrder = _expSortOrder === 'DESC' ? 'ASC' : 'DESC';
        const btn = $('#cdExpSortBtn');
        const label = $('#cdExpSortLabel');
        const isDesc = _expSortOrder === 'DESC';
        if (label) label.textContent = t(isDesc ? 'exp_sort_desc' : 'exp_sort_asc');
        if (btn) {
            btn.title = t(isDesc ? 'exp_sort_desc' : 'exp_sort_asc');
            const svg = btn.querySelector('svg');
            if (svg) svg.style.transform = isDesc ? '' : 'rotate(180deg)';
        }
        loadExpediente(1);
    });
    $('#cdExpLoadMore')?.addEventListener('click', () => loadExpediente(_expPage + 1));

    // Observe resident changes
    let _lastExpRid = null;
    setInterval(() => {
        if (_residenteId !== _lastExpRid) {
            _lastExpRid = _residenteId;
            const expView = document.getElementById('viewExpediente');
            if (expView && expView.classList.contains('active')) {
                loadExpediente(1);
            }
        }
    }, 500);

    // ── Public function for linking from prescriptions ─────────
    window._openExpedienteForRx = async function(rxId, rxName) {
        if (!_residenteId) return;
        try {
            const res = await api(EXP_API + '?action=brief&residente_id=' + _residenteId);
            const docs = res || [];
            if (!docs.length) {
                showToast('No hay documentos en el expediente. Agrega uno primero.', 'info');
                return;
            }
            const body = `<div class="cd-sidebar-section" style="display:flex;flex-direction:column;gap:6px">
                <p style="font-size:.8125rem;color:var(--cd-text-muted);margin:0 0 8px">${t('exp_link_rx')}: <strong>${esc(rxName)}</strong></p>
                ${docs.map(d => `<button class="cd-btn-submit cd-btn-secondary cd-exp-link-opt" data-doc-id="${d.id}" data-perm-id="exp_link_doc_to_rx_btn" style="text-align:left;font-size:.8125rem;padding:8px 12px;display:flex;align-items:center;gap:8px">
                    <span class="cd-exp-card-badge" style="flex-shrink:0">${esc(TIPO_LABELS[d.tipo] || d.tipo)}</span>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(d.titulo)}</span>
                    <span style="color:var(--cd-text-muted);font-size:.6875rem">${d.fecha_documento ? fmtDateShort(d.fecha_documento) : ''}</span>
                </button>`).join('')}
            </div>`;
            openSidebar(t('exp_link_rx'), body, `<button class="cd-btn-submit cd-btn-secondary" id="cdExpLinkCancel">${t('btn_cancel')}</button>`);
            $$('.cd-exp-link-opt').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        await api(EXP_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                            action: 'vincular_rx', prescripcion_id: rxId, expediente_id: parseInt(btn.dataset.docId), residente_id: _residenteId
                        })});
                        showToast(t('exp_linked_rx'), 'success');
                        closeSidebar();
                    } catch(e) { showToast(e.message, 'error'); }
                });
            });
            $('#cdExpLinkCancel')?.addEventListener('click', closeSidebar);
        } catch(e) { showToast(e.message || 'Error', 'error'); }
    };

    window._unlinkExpedienteFromRx = async function(rxId) {
        try {
            await api(EXP_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'desvincular_rx', prescripcion_id: rxId, residente_id: _residenteId })});
            showToast(t('exp_unlink_rx'), 'success');
        } catch(e) { showToast(e.message, 'error'); }
    };

    window._reloadExpediente = function(opts = {}) {
        if (opts && opts.force) {
            _expDocs = [];
            _expTotal = 0;
            _expPages = 1;
            _expPage = 1;
        }
        const grid = $('#cdExpGrid');
        if (grid) { grid.innerHTML = expTimelineSkeleton(4); }
        return loadExpediente(1);
    };

    window._viewExpedienteDoc = async function(docId) {
        try {
            const url = EXP_API + '?action=serve&id=' + docId + '&residente_id=' + encodeURIComponent(_residenteId || '');
            const res = await fetch(url, { credentials: 'include', method: 'HEAD' });
            const mime = res.headers.get('content-type') || '';
            if (mime.startsWith('image/')) {
                openExpLightbox(url, '');
            } else if (mime.includes('pdf')) {
                openExpPdfViewer(url);
            } else {
                window.open(url, '_blank');
            }
        } catch(e) { showToast(e.message, 'error'); }
    };

    // Expose lightbox/PDF viewer for other modules (e.g., cd-nm adjuntos)
    window._openImageLightbox = openExpLightbox;
    window._openPdfViewer = openExpPdfViewer;
})();
