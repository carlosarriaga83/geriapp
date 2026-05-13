// cd-ai-rewrite.js — AI rewrite (mejorar redacción)
// Extracted from cuidados.php (lines 12754)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// AI REWRITE (mejorar redacción)
// ═══════════════════════════════════════════════
const _aiOriginals = {};

async function aiRewrite(targetId, context) {
    const el = document.getElementById(targetId);
    if (!el) return;
    const text = el.value?.trim();
    if (!text) { showToast('Escribe algo primero', 'error'); return; }

    const btn = el.parentElement?.querySelector('.cd-ai-rewrite-btn');
    const undoBtn = el.parentElement?.querySelector('.cd-ai-undo-btn');
    if (btn) { btn.disabled = true; btn.classList.add('cd-ai-loading'); }

    try {
        const res = await api(CFG_API + '?action=ai_rewrite', {
            method: 'PUT', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ text, context })
        });
        _aiOriginals[targetId] = text;
        el.value = res.rewritten;
        el.dispatchEvent(new Event('input', {bubbles: true}));
        el.classList.add('cd-ai-improved');
        if (undoBtn) undoBtn.style.display = '';
        showToast('Texto mejorado con IA', 'success');
    } catch(e) {
        showToast('Error IA: ' + (e.message || 'No disponible'), 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.classList.remove('cd-ai-loading'); }
    }
}

function aiUndo(targetId) {
    const el = document.getElementById(targetId);
    if (!el || !_aiOriginals[targetId]) return;
    el.value = _aiOriginals[targetId];
    el.dispatchEvent(new Event('input', {bubbles: true}));
    el.classList.remove('cd-ai-improved');
    delete _aiOriginals[targetId];
    const undoBtn = el.parentElement?.querySelector('.cd-ai-undo-btn');
    if (undoBtn) undoBtn.style.display = 'none';
}
// Expose to global scope for inline onclick handlers
window.aiRewrite = aiRewrite;
window.aiUndo = aiUndo;

// Init AI buttons for notif form (static)
document.querySelectorAll('.cd-ai-rewrite-btn').forEach(btn => {
    btn.addEventListener('click', () => aiRewrite(btn.dataset.target, btn.dataset.context));
});
document.querySelectorAll('.cd-ai-undo-btn').forEach(btn => {
    btn.addEventListener('click', () => aiUndo(btn.dataset.target));
});

// Inject AI buttons into all observation textareas dynamically
document.querySelectorAll('textarea[name="observaciones"]').forEach((ta, idx) => {
    const id = 'cdObsAi_' + idx;
    ta.id = id;
    const wrap = document.createElement('div');
    wrap.className = 'cd-ai-field-wrap';
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(ta);
    const reBtn = document.createElement('button');
    reBtn.type = 'button';
    reBtn.className = 'cd-ai-rewrite-btn';
    reBtn.title = 'Mejorar redacción con IA';
    reBtn.innerHTML = '<img src="assets/icons/gemini.png" class="cd-ai-icon" alt="AI">';
    reBtn.addEventListener('click', () => aiRewrite(id, 'observacion'));
    wrap.appendChild(reBtn);
    const undoBtn = document.createElement('button');
    undoBtn.type = 'button';
    undoBtn.className = 'cd-ai-undo-btn';
    undoBtn.title = 'Deshacer cambio IA';
    undoBtn.style.display = 'none';
    undoBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>';
    undoBtn.addEventListener('click', () => aiUndo(id));
    wrap.appendChild(undoBtn);
});
