<?php
// cd-asistente.js.php — Asistente IA flotante (chat).
// Endpoint: api/asistente.php
//
// Eventos clave:
//   #cdAsisFab      → abrir/cerrar panel
//   #cdAsisForm     → enviar mensaje
//   #cdAsisHistBtn  → mostrar/ocultar lista de conversaciones previas
//   #cdAsisNewBtn   → iniciar conversación nueva
//
// Persistencia: el conv_id activo se guarda en localStorage por usuario.
?>
// ── Asistente IA flotante ──────────────────────────────────────────────────
(function(){
    const fab        = document.getElementById('cdAsisFab');
    const panel      = document.getElementById('cdAsisPanel');
    const closeBtn   = document.getElementById('cdAsisCloseBtn');
    const histBtn    = document.getElementById('cdAsisHistBtn');
    const newBtn     = document.getElementById('cdAsisNewBtn');
    const histPanel  = document.getElementById('cdAsisHistory');
    const histList   = document.getElementById('cdAsisHistoryList');
    const body       = document.getElementById('cdAsisBody');
    const empty      = document.getElementById('cdAsisEmpty');
    const form       = document.getElementById('cdAsisForm');
    const textarea   = document.getElementById('cdAsisTextarea');
    const sendBtn    = document.getElementById('cdAsisSendBtn');
    const titleEl    = document.getElementById('cdAsisTitle');
    if (!fab || !panel) return;

    const _userKey   = (typeof CURRENT_USER_ID !== 'undefined' && CURRENT_USER_ID) ? CURRENT_USER_ID : 'anon';
    const STORE_KEY  = 'geriappAsisConv:' + _userKey;
    let convId = parseInt(localStorage.getItem(STORE_KEY) || '0', 10) || 0;
    let sending = false;

    // ── Helpers ───────────────────────────────────────────────────────────
    function _saveConv(id) {
        convId = id || 0;
        if (convId) localStorage.setItem(STORE_KEY, String(convId));
        else        localStorage.removeItem(STORE_KEY);
    }

    function _scrollBottom() {
        body.scrollTop = body.scrollHeight;
    }

    function _autosize() {
        textarea.style.height = 'auto';
        const h = Math.min(140, textarea.scrollHeight);
        textarea.style.height = h + 'px';
        sendBtn.disabled = !textarea.value.trim() || sending;
    }

    // Markdown muy ligero: **bold**, `code`, listas con -, números, y links [txt](url|#hash)
    function _renderMd(md) {
        const esc = (s) => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        let s = esc(md);
        // Code spans
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Bold
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Links [txt](url o #hash) — solo permitimos http(s) y hashes internos
        s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function(_, txt, href) {
            const safe = /^(#|https?:)/.test(href) ? href : '#';
            const isHash = href.startsWith('#');
            const attrs = isHash ? `data-asis-link="${safe}"` : 'target="_blank" rel="noopener"';
            return `<a href="${safe}" ${attrs}>${txt}</a>`;
        });
        // Listas (- item)  + numeradas (1. item)
        const lines = s.split('\n');
        const out = [];
        let inUl = false, inOl = false;
        for (const ln of lines) {
            const liU = /^\s*-\s+(.*)$/.exec(ln);
            const liO = /^\s*\d+\.\s+(.*)$/.exec(ln);
            if (liU) {
                if (inOl) { out.push('</ol>'); inOl = false; }
                if (!inUl) { out.push('<ul>'); inUl = true; }
                out.push(`<li>${liU[1]}</li>`);
            } else if (liO) {
                if (inUl) { out.push('</ul>'); inUl = false; }
                if (!inOl) { out.push('<ol>'); inOl = true; }
                out.push(`<li>${liO[1]}</li>`);
            } else {
                if (inUl) { out.push('</ul>'); inUl = false; }
                if (inOl) { out.push('</ol>'); inOl = false; }
                if (ln.trim() === '') out.push('');
                else out.push(`<p>${ln}</p>`);
            }
        }
        if (inUl) out.push('</ul>');
        if (inOl) out.push('</ol>');
        return out.join('\n');
    }

    function _bubble(rol, contenido) {
        const wrap = document.createElement('div');
        wrap.className = 'cd-asis-msg cd-asis-msg-' + rol;
        wrap.innerHTML = '<div class="cd-asis-bubble">' + (rol === 'user' ? esc(contenido) : _renderMd(contenido)) + '</div>';
        body.appendChild(wrap);
        return wrap;
    }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function _typingBubble() {
        const wrap = document.createElement('div');
        wrap.className = 'cd-asis-msg cd-asis-msg-assistant cd-asis-typing';
        wrap.innerHTML = '<div class="cd-asis-bubble"><span></span><span></span><span></span></div>';
        body.appendChild(wrap);
        _scrollBottom();
        return wrap;
    }

    function _hideEmpty() {
        if (empty && !empty.hidden) empty.hidden = true;
    }
    function _showEmpty() {
        // Limpiar todas las burbujas y mostrar pantalla inicial
        body.querySelectorAll('.cd-asis-msg').forEach(n => n.remove());
        if (empty) empty.hidden = false;
    }

    // ── API calls ─────────────────────────────────────────────────────────
    async function _api(method, params) {
        const url = BASE + '/api/asistente.php' + (method === 'GET' && params ? ('?' + new URLSearchParams(params).toString()) : '');
        const opts = {
            method,
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
        };
        if (method !== 'GET') opts.body = JSON.stringify(params || {});
        const r = await fetch(url, opts);
        let json;
        try { json = await r.json(); } catch(e) { json = { success: false, message: 'Respuesta inválida' }; }
        if (!r.ok || !json.success) throw new Error(json.message || ('HTTP ' + r.status));
        return json.data || {};
    }

    async function _loadConv(id) {
        if (!id) { _showEmpty(); titleEl.textContent = '<?= addslashes(t('asis_title')) ?>'; return; }
        try {
            const data = await _api('GET', { action: 'get', conv_id: id });
            _hideEmpty();
            body.querySelectorAll('.cd-asis-msg').forEach(n => n.remove());
            (data.mensajes || []).forEach(m => _bubble(m.rol, m.contenido || ''));
            if (data.conversacion?.titulo) titleEl.textContent = data.conversacion.titulo;
            _scrollBottom();
        } catch(e) {
            _saveConv(0);
            _showEmpty();
        }
    }

    async function _sendMessage(text) {
        if (sending || !text.trim()) return;
        sending = true;
        sendBtn.disabled = true;
        _hideEmpty();
        _bubble('user', text);
        _scrollBottom();
        const typing = _typingBubble();
        try {
            const resHint = (typeof _residenteId !== 'undefined') ? (_residenteId || 0) : 0;
            const data = await _api('POST', {
                action: 'send',
                message: text,
                conv_id: convId,
                residente_id: resHint,
            });
            typing.remove();
            if (!convId && data.conv_id) _saveConv(data.conv_id);
            _bubble('assistant', data.reply || '');
            _scrollBottom();
        } catch(e) {
            typing.remove();
            _bubble('assistant', '⚠️ ' + (e.message || 'Error'));
            _scrollBottom();
        } finally {
            sending = false;
            _autosize();
        }
    }

    async function _renderHistory() {
        try {
            const data = await _api('GET', { action: 'list' });
            const list = data.conversaciones || [];
            if (!list.length) {
                histList.innerHTML = '<div class="cd-asis-history-empty"><?= addslashes(t('asis_history_empty')) ?></div>';
                return;
            }
            histList.innerHTML = list.map(c => `
                <div class="cd-asis-history-item${c.id == convId ? ' is-active' : ''}" data-id="${c.id}">
                    <div class="cd-asis-history-titulo">${esc(c.titulo || 'Sin título')}</div>
                    <div class="cd-asis-history-meta">${esc(c.actualizado_at || '')} · ${c.msg_count || 0} msj</div>
                    <button type="button" class="cd-asis-history-del" data-id="${c.id}" title="<?= addslashes(t('asis_delete')) ?>" aria-label="<?= addslashes(t('asis_delete')) ?>">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                    </button>
                </div>
            `).join('');
        } catch(e) {
            histList.innerHTML = '<div class="cd-asis-history-empty">' + esc(e.message) + '</div>';
        }
    }

    async function _deleteConv(id) {
        if (!confirm('<?= addslashes(t('asis_delete_confirm')) ?>')) return;
        try {
            await _api('POST', { action: 'delete', conv_id: id });
            if (id == convId) { _saveConv(0); _showEmpty(); titleEl.textContent = '<?= addslashes(t('asis_title')) ?>'; }
            _renderHistory();
        } catch(e) {
            if (typeof showToast === 'function') showToast(e.message, 'error');
        }
    }

    // ── Apertura / cierre ─────────────────────────────────────────────────
    function _open() {
        panel.hidden = false;
        requestAnimationFrame(() => panel.classList.add('is-open'));
        if (convId && body.querySelectorAll('.cd-asis-msg').length === 0) {
            _loadConv(convId);
        }
        setTimeout(() => textarea.focus(), 200);
    }
    function _close() {
        panel.classList.remove('is-open');
        setTimeout(() => { panel.hidden = true; histPanel.hidden = true; }, 220);
    }

    fab.addEventListener('click', () => panel.hidden ? _open() : _close());
    closeBtn.addEventListener('click', _close);

    histBtn.addEventListener('click', () => {
        const showing = !histPanel.hidden;
        histPanel.hidden = showing;
        if (!showing) _renderHistory();
    });

    newBtn.addEventListener('click', () => {
        _saveConv(0);
        _showEmpty();
        titleEl.textContent = '<?= addslashes(t('asis_title')) ?>';
        histPanel.hidden = true;
        textarea.focus();
    });

    histList.addEventListener('click', (ev) => {
        const del = ev.target.closest('.cd-asis-history-del');
        if (del) { ev.stopPropagation(); _deleteConv(parseInt(del.dataset.id, 10)); return; }
        const item = ev.target.closest('.cd-asis-history-item');
        if (!item) return;
        const id = parseInt(item.dataset.id, 10);
        if (!id) return;
        _saveConv(id);
        histPanel.hidden = true;
        _loadConv(id);
    });

    // Enviar
    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const text = textarea.value.trim();
        if (!text) return;
        textarea.value = '';
        _autosize();
        _sendMessage(text);
    });
    textarea.addEventListener('input', _autosize);
    textarea.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey) {
            ev.preventDefault();
            form.requestSubmit();
        }
    });

    // Sugerencias iniciales
    document.querySelectorAll('.cd-asis-suggest-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            textarea.value = chip.textContent.trim();
            _autosize();
            form.requestSubmit();
        });
    });

    // Click en links internos del asistente → navegar usando showView()
    body.addEventListener('click', (ev) => {
        const a = ev.target.closest('a[data-asis-link]');
        if (!a) return;
        const hash = a.getAttribute('data-asis-link') || '';
        const map = {
            '#residentes': 'viewResidentes',
            '#dashboard':  'viewDashboard',
            '#registros':  'viewRecords',
            '#expediente': 'viewExpediente',
            '#ficha':      'viewFicha',
            '#inventario': 'viewInventory',
            '#form-sueno': 'viewFormSueno',
            '#form-alimentacion': 'viewFormAlimentacion',
            '#form-medicacion':   'viewFormMedicacion',
            '#form-signos':       'viewFormSignosVitales',
            '#form-notas':        'viewFormNotas',
            '#form-nota-medico':  'viewFormNotasMedico',
        };
        const view = map[hash];
        if (view && typeof showView === 'function') {
            ev.preventDefault();
            _close();
            showView(view);
        }
    });

    // Mostrar/ocultar FAB cuando hay un overlay/diálogo grande activo.
    // Selectores reales del SPA v9 (cd-modal-overlay usa style.display, no clase).
    const _hideOnOverlay = () => {
        const overlays = document.querySelectorAll('.cd-modal-overlay, .cd-photo-sheet-backdrop, .cd-rx-img-overlay, .cd-legal-sign-overlay, .cd-sysnotif-overlay');
        let blocking = false;
        overlays.forEach(el => {
            const cs = el && el.ownerDocument ? el.ownerDocument.defaultView.getComputedStyle(el) : null;
            if (cs && cs.display !== 'none' && cs.visibility !== 'hidden') blocking = true;
        });
        fab.style.display = blocking ? 'none' : '';
    };
    new MutationObserver(_hideOnOverlay).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class','style'] });
    _hideOnOverlay();

    _autosize();
})();
