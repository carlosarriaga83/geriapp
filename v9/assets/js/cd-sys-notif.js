// cd-sys-notif.js — System notifications (user-facing)
// Extracted from cuidados.php (lines 15951)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// SYSTEM NOTIFICATIONS (user-facing)
// ═══════════════════════════════════════════════
let _pendingNotifs = [];
let _currentNotifIdx = 0;

async function checkPendingNotifications() {
    try {
        const res = await api(`${NOTIF_API}?action=pendientes`);
        _pendingNotifs = res || [];
        if (_pendingNotifs.length) {
            _currentNotifIdx = 0;
            showSystemNotification(_currentNotifIdx);
        }
    } catch(e) {}
}

function showSystemNotification(idx) {
    const n = _pendingNotifs[idx];
    if (!n) { $('#cdSysNotifOverlay').style.display = 'none'; return; }

    const overlay = $('#cdSysNotifOverlay');
    const iconEl = $('#cdSysNotifIcon');
    const titleEl = $('#cdSysNotifTitle');
    const bodyEl = $('#cdSysNotifBody');
    const respEl = $('#cdSysNotifResponse');
    const actionsEl = $('#cdSysNotifActions');
    const counterEl = $('#cdSysNotifCounter');

    // Type icon & color
    const typeConfig = {
        info: { icon: '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--cd-accent)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>', cls: 'cd-sysnotif-info' },
        alerta: { icon: '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--cd-warning, #f59e0b)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', cls: 'cd-sysnotif-alerta' },
        pregunta: { icon: '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--cd-success)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', cls: 'cd-sysnotif-pregunta' },
    };
    const tc = typeConfig[n.tipo] || typeConfig.info;
    iconEl.innerHTML = tc.icon;

    const modal = overlay.querySelector('.cd-sysnotif-modal');
    modal.className = 'cd-sysnotif-modal ' + tc.cls;

    titleEl.textContent = n.titulo;
    bodyEl.textContent = n.mensaje;

    // GIF / Video media
    const mediaEl = $('#cdSysNotifMedia');
    if (n.imagen_url) {
        mediaEl.style.display = '';
        const mediaSrc = n.imagen_url.startsWith('http') ? esc(n.imagen_url) : esc(BASE + '/' + n.imagen_url);
        const isVid = /\.(mp4|webm)$/i.test(n.imagen_url);
        mediaEl.innerHTML = isVid
            ? `<video src="${mediaSrc}" autoplay loop muted playsinline class="cd-sysnotif-img"></video>`
            : `<img src="${mediaSrc}" alt="" class="cd-sysnotif-img" onerror="this.style.display='none'">`;
    } else {
        mediaEl.style.display = 'none';
        mediaEl.innerHTML = '';
    }

    // Response section
    const opciones = n.opciones_respuesta ? (typeof n.opciones_respuesta === 'string' ? JSON.parse(n.opciones_respuesta) : n.opciones_respuesta) : [];

    if (n.tipo === 'pregunta') {
        respEl.style.display = '';
        if (opciones.length) {
            // Show option buttons (select, then confirm with send button)
            respEl.innerHTML = '<p class="cd-sysnotif-resp-label">Selecciona tu respuesta:</p>' +
                opciones.map(op => `<button type="button" class="cd-sysnotif-opt-btn" data-resp="${esc(op)}">${esc(op)}</button>`).join('');
        } else {
            // Free text
            respEl.innerHTML = '<p class="cd-sysnotif-resp-label">Tu respuesta:</p>' +
                '<textarea class="cd-textarea cd-sysnotif-textarea" id="cdSysNotifFreeText" rows="2" placeholder="Escribe tu respuesta..."></textarea>';
        }
    } else {
        respEl.style.display = 'none';
        respEl.innerHTML = '';
    }

    // Actions — always show send button for pregunta (both options and free text)
    if (n.tipo === 'pregunta') {
        actionsEl.innerHTML = '<button type="button" class="cd-btn-submit cd-sysnotif-send-btn" id="cdSysNotifSendBtn">Enviar respuesta</button>';
    } else {
        actionsEl.innerHTML = '<button type="button" class="cd-btn-submit cd-sysnotif-ok-btn" id="cdSysNotifOkBtn">Entendido</button>';
    }

    // Counter
    counterEl.textContent = _pendingNotifs.length > 1 ? `${idx + 1} de ${_pendingNotifs.length}` : '';

    overlay.style.display = '';

    // Event handlers
    const respondAndNext = async (respuesta) => {
        try {
            await api(NOTIF_API, { method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ action:'responder', notificacion_id: n.id, respuesta }) });
        } catch(e) {}
        _currentNotifIdx++;
        if (_currentNotifIdx < _pendingNotifs.length) {
            showSystemNotification(_currentNotifIdx);
        } else {
            overlay.style.display = 'none';
        }
    };

    // OK button (info/alerta)
    $('#cdSysNotifOkBtn')?.addEventListener('click', () => respondAndNext(null));

    // Option buttons (pregunta with options) — select, don't send yet
    let _selectedOptResp = null;
    $$('.cd-sysnotif-opt-btn', respEl).forEach(btn => {
        btn.addEventListener('click', () => {
            $$('.cd-sysnotif-opt-btn', respEl).forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            _selectedOptResp = btn.dataset.resp;
        });
    });

    // Send button handles both options and free text
    $('#cdSysNotifSendBtn')?.addEventListener('click', () => {
        if (_selectedOptResp) {
            respondAndNext(_selectedOptResp);
        } else {
            const text = $('#cdSysNotifFreeText')?.value?.trim();
            if (!text) { showToast(t('error_select_response'), 'error'); return; }
            respondAndNext(text);
        }
    });
}
