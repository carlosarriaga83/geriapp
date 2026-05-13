// cd-error-log.js — Error log §7
// Extracted from cuidados.php (lines 14173)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// ERROR LOG (Â§7 — Errores PHP)
// ═══════════════════════════════════════════════
let _errorLogLoaded = false;

async function loadErrorLog() {
    const output = $('#cfgErrorLogOutput');
    const info   = $('#cfgErrorLogInfo');
    const lines  = $('#cfgErrorLogLines')?.value || 200;
    if (output) output.textContent = 'Cargando¦';
    try {
        const d = await api(LOGS_API + '?action=errors&lines=' + lines);
        _errorLogLoaded = true;
        // Update display_errors toggle
        const toggle = $('#cfgDisplayErrors');
        if (toggle) toggle.checked = !!d.display_errors;
        // Update info bar
        if (info) {
            info.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`
                + `<span style="font-size:0.8125rem;color:var(--cd-text-muted)">Tamaño del log: <strong>${d.total_size_fmt || '0 KB'}</strong> · ${(d.lines||[]).length} líneas mostradas</span>`;
        }
        // Render log lines
        if (output) {
            if (!d.lines || d.lines.length === 0) {
                output.innerHTML = '<span style="color:var(--cd-text-muted)">El log de errores está vacío.</span>';
            } else {
                output.textContent = d.lines.join('\n');
                output.scrollTop = output.scrollHeight;
            }
        }
    } catch(e) {
        if (output) output.textContent = 'Error al cargar el log de errores.';
    }
}

// Toggle display_errors
$('#cfgDisplayErrors')?.addEventListener('change', async function() {
    const enabled = this.checked;
    try {
        await api(LOGS_API + '?action=display_errors', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ enabled })
        });
        showToast('display_errors ' + (enabled ? 'activado' : 'desactivado'), enabled ? 'warning' : 'success');
    } catch(e) {
        this.checked = !enabled; // revert
    }
});

$('#cfgErrorLogRefresh')?.addEventListener('click', () => loadErrorLog());
$('#cfgErrorLogLines')?.addEventListener('change', () => loadErrorLog());

$('#cfgErrorLogClear')?.addEventListener('click', async () => {
    if (!await cdConfirm('¿Limpiar todo el log de errores PHP? Esta acción no se puede deshacer.', { type: 'danger', okText: 'Limpiar' })) return;
    try {
        await api(LOGS_API + '?action=clear_errors', { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
        showToast('Log limpiado', 'success');
        loadErrorLog();
    } catch(e) {}
});
