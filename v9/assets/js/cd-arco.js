// cd-arco.js — ARCO Rights management in user profile
// ────────────────────────────────────────────────────────────

(function () {
    const API = BASE + '/api/personal.php';

    // ── Acceso: download personal data ─────────────────────────
    $('#cdArcoAccess')?.addEventListener('click', async () => {
        const btn = $('#cdArcoAccess');
        btnLoading(btn, t('arco_downloading'));
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_acceso' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Error');

            // Generate downloadable JSON
            const blob = new Blob([JSON.stringify(json.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `geriapp-datos-personales-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showToast(t('arco_request_sent'), 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
        btnReset(btn);
    });

    // ── Rectificación: go to edit mode ─────────────────────────
    $('#cdArcoRectify')?.addEventListener('click', () => {
        const editBtn = $('#cdProfileEditBtn');
        if (editBtn) {
            editBtn.click();
            $('#cdProfileEdit')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // ── Cancelación: request account deletion ─────────────────
    $('#cdArcoCancel')?.addEventListener('click', async () => {
        const ok = await cdConfirm(t('arco_confirm_cancel_body'), {
            title:   t('arco_confirm_cancel_title'),
            type:    'danger',
            okText:  t('btn_confirm'),
        });
        if (!ok) return;
        await _sendArcoRequest('cancelacion', 'Solicitud de cancelación de cuenta y eliminación de datos personales.');
    });

    // ── Oposición: opt out of secondary processing ────────────
    $('#cdArcoOppose')?.addEventListener('click', async () => {
        const ok = await cdConfirm(t('arco_confirm_oppose_body'), {
            title:  t('arco_confirm_oppose_title'),
            type:   'warn',
            okText: t('btn_confirm'),
        });
        if (!ok) return;
        await _sendArcoRequest('oposicion', 'Solicitud de oposición al tratamiento secundario de datos personales.');
    });

    // ── Show history toggle ───────────────────────────────────
    $('#cdArcoShowHistory')?.addEventListener('click', async () => {
        const panel = $('#cdArcoHistory');
        if (!panel) return;

        if (panel.style.display !== 'none') {
            panel.style.display = 'none';
            return;
        }

        panel.style.display = '';
        const list = $('#cdArcoHistoryList');
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.85rem">Cargando…</p>';

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_historial' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            const items = json.data || [];
            if (!items.length) {
                list.innerHTML = `<p style="color:var(--cd-text-muted);font-size:0.85rem">${t('arco_no_requests')}</p>`;
                return;
            }

            const statusLabels = {
                pendiente:  t('arco_status_pendiente'),
                en_proceso: t('arco_status_en_proceso'),
                completada: t('arco_status_completada'),
                rechazada:  t('arco_status_rechazada'),
            };
            const statusColors = {
                pendiente:  '#f59e0b',
                en_proceso: '#3b82f6',
                completada: '#10b981',
                rechazada:  '#ef4444',
            };
            const tipoLabels = {
                acceso:        t('arco_access'),
                rectificacion: t('arco_rectify'),
                cancelacion:   t('arco_cancel'),
                oposicion:     t('arco_oppose'),
            };

            let html = '';
            for (const r of items) {
                const date = (function(){ const f = fmtDateTime(r.creado_at); return f.date || new Date(r.creado_at).toLocaleDateString('es-MX', { timeZone: APP_TZ }); })();
                const color = statusColors[r.estado] || '#64748b';
                html += `<div class="cd-arco-item">
                    <div class="cd-arco-item-header">
                        <span class="cd-arco-tipo">${tipoLabels[r.tipo] || r.tipo}</span>
                        <span class="cd-arco-status" style="color:${color}">${statusLabels[r.estado] || r.estado}</span>
                    </div>
                    <p class="cd-arco-item-date">${date}</p>
                    ${r.respuesta ? `<p class="cd-arco-item-resp">${_escapeHtml(r.respuesta)}</p>` : ''}
                </div>`;
            }
            list.innerHTML = html;

        } catch (e) {
            list.innerHTML = `<p style="color:var(--cd-error);font-size:0.85rem">${e.message}</p>`;
        }
    });

    // ── Helper: send ARCO request ─────────────────────────────
    async function _sendArcoRequest(tipo, descripcion) {
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_solicitud', tipo, descripcion })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            showToast(t('arco_request_sent'), 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
    }

    // ── Helper: escape HTML ───────────────────────────────────
    function _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();


(function () {
    const API = BASE + '/api/personal.php';

    // ── Acceso: download personal data ─────────────────────────
    $('#cdArcoAccess')?.addEventListener('click', async () => {
        const btn = $('#cdArcoAccess');
        btnLoading(btn, t('arco_downloading'));
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_acceso' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Error');

            // Generate downloadable JSON
            const blob = new Blob([JSON.stringify(json.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `geriapp-datos-personales-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showToast(t('arco_request_sent'), 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
        btnReset(btn);
    });

    // ── Rectificación: go to edit mode ─────────────────────────
    $('#cdArcoRectify')?.addEventListener('click', () => {
        // Trigger the existing edit button
        const editBtn = $('#cdProfileEditBtn');
        if (editBtn) {
            editBtn.click();
            // Scroll to edit section
            $('#cdProfileEdit')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // ── Cancelación: request account deletion ─────────────────
    $('#cdArcoCancel')?.addEventListener('click', () => {
        if (typeof showConfirm === 'function') {
            showConfirm(
                t('arco_confirm_cancel_title'),
                t('arco_confirm_cancel_body'),
                async () => { await _sendArcoRequest('cancelacion', t('arco_confirm_cancel_body')); }
            );
        } else {
            if (!confirm(t('arco_confirm_cancel_body'))) return;
            _sendArcoRequest('cancelacion', t('arco_confirm_cancel_body'));
        }
    });

    // ── Oposición: opt out of secondary processing ────────────
    $('#cdArcoOppose')?.addEventListener('click', () => {
        if (typeof showConfirm === 'function') {
            showConfirm(
                t('arco_confirm_oppose_title'),
                t('arco_confirm_oppose_body'),
                async () => { await _sendArcoRequest('oposicion', t('arco_confirm_oppose_body')); }
            );
        } else {
            if (!confirm(t('arco_confirm_oppose_body'))) return;
            _sendArcoRequest('oposicion', t('arco_confirm_oppose_body'));
        }
    });

    // ── Show history toggle ───────────────────────────────────
    $('#cdArcoShowHistory')?.addEventListener('click', async () => {
        const panel = $('#cdArcoHistory');
        if (!panel) return;

        if (panel.style.display !== 'none') {
            panel.style.display = 'none';
            return;
        }

        panel.style.display = '';
        const list = $('#cdArcoHistoryList');
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.85rem">Cargando…</p>';

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_historial' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            const items = json.data || [];
            if (!items.length) {
                list.innerHTML = `<p style="color:var(--cd-text-muted);font-size:0.85rem">${t('arco_no_requests')}</p>`;
                return;
            }

            const statusLabels = {
                pendiente:  t('arco_status_pendiente'),
                en_proceso: t('arco_status_en_proceso'),
                completada: t('arco_status_completada'),
                rechazada:  t('arco_status_rechazada'),
            };
            const statusColors = {
                pendiente:  '#f59e0b',
                en_proceso: '#3b82f6',
                completada: '#10b981',
                rechazada:  '#ef4444',
            };
            const tipoLabels = {
                acceso:        t('arco_access'),
                rectificacion: t('arco_rectify'),
                cancelacion:   t('arco_cancel'),
                oposicion:     t('arco_oppose'),
            };

            let html = '';
            for (const r of items) {
                const date = (function(){ const f = fmtDateTime(r.creado_at); return f.date || new Date(r.creado_at).toLocaleDateString('es-MX', { timeZone: APP_TZ }); })();
                const color = statusColors[r.estado] || '#64748b';
                html += `<div class="cd-arco-item">
                    <div class="cd-arco-item-header">
                        <span class="cd-arco-tipo">${tipoLabels[r.tipo] || r.tipo}</span>
                        <span class="cd-arco-status" style="color:${color}">${statusLabels[r.estado] || r.estado}</span>
                    </div>
                    <p class="cd-arco-item-date">${date}</p>
                    ${r.respuesta ? `<p class="cd-arco-item-resp">${_escapeHtml(r.respuesta)}</p>` : ''}
                </div>`;
            }
            list.innerHTML = html;

        } catch (e) {
            list.innerHTML = `<p style="color:var(--cd-error);font-size:0.85rem">${e.message}</p>`;
        }
    });

    // ── Helper: send ARCO request ─────────────────────────────
    async function _sendArcoRequest(tipo, descripcion) {
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'arco_solicitud', tipo, descripcion })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            showToast(t('arco_request_sent'), 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
    }

    // ── Helper: escape HTML ───────────────────────────────────
    function _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
