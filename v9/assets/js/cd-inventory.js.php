// cd-inventory.js — Inventory management
// Extracted from cuidados.php (lines 12178)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// INVENTORY
// ═══════════════════════════════════════════════
let _invFilter='todos', _invPageItems=[], _invLow=[], _invSearchText='';
let _medMgmtSubtab = 'rx';

function setMedMgmtSubtab(tab) {
    _medMgmtSubtab = (tab === 'inv') ? 'inv' : 'rx';
    const tabsWrap = $('#cdMedMgmtTabs');
    if (tabsWrap) {
        $$('.cd-period-tab', tabsWrap).forEach(b => {
            b.classList.toggle('active', b.dataset.subtab === _medMgmtSubtab);
        });
    }
    $('#cdMedMgmtPaneRx')?.classList.toggle('active', _medMgmtSubtab === 'rx');
    $('#cdMedMgmtPaneInv')?.classList.toggle('active', _medMgmtSubtab === 'inv');
}

$('#cdMedMgmtTabs')?.addEventListener('click', e => {
    const b = e.target.closest('.cd-period-tab');
    if (!b) return;
    setMedMgmtSubtab(b.dataset.subtab || 'rx');
    if (_medMgmtSubtab === 'rx') {
        renderRxTracker();
    }
});

$('#cdInvTabs')?.addEventListener('click', e => {
    const b=e.target.closest('.cd-period-tab');if(!b)return;
    $$('.cd-period-tab',e.currentTarget).forEach(x=>x.classList.remove('active'));
    b.classList.add('active'); _invFilter=b.dataset.tab; renderInvCards();
});

// Inventory search bar
{
    const invSearch = $('#cdInvSearchBar');
    let invSearchTimer;
    invSearch?.addEventListener('input', () => {
        clearTimeout(invSearchTimer);
        invSearchTimer = setTimeout(() => { _invSearchText = invSearch.value.trim().toLowerCase(); renderInvCards(); }, 200);
    });
}

async function loadInventory() {
    // Default sub-tab when user enters this section: "Medicación del día"
    setMedMgmtSubtab('rx');

    // Ghost skeleton in the Rx tracker while we refresh data for the current _fecha.
    // Without this, navigating dates inside viewInventory leaves the previous day's
    // pills on screen until the new dashboard data arrives.
    const rxTracker = $('#cdRxTracker');
    const rxPills = $('#cdRxTrackerPills');
    if (rxTracker && rxPills) {
        rxTracker.style.display = '';
        rxPills.innerHTML = `<div class="cd-rx-tracker-col">${rxGhostPills(3)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div>`;
    }
    const rxSum = $('#cdRxTrackerSum');
    if (rxSum) rxSum.textContent = '…';
    // Mirror tracker (inside med form) — same ghost treatment
    const rxMirror = $('#cdRxTrackerMirror');
    const rxMirrorPills = $('#cdRxTrackerMirrorPills');
    if (rxMirror && rxMirrorPills) {
        rxMirror.style.display = '';
        rxMirrorPills.innerHTML = `<div class="cd-rx-tracker-col">${rxGhostPills(3)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div><div class="cd-rx-tracker-col">${rxGhostPills(2)}</div>`;
    }

    $('#cdInvList').innerHTML = skeleton(3);
    $('#cdMovList').innerHTML = skeleton(2);

    // Refresh dashboard data (registros + counts + bitacora + inventario) for the
    // current _fecha so the Rx tracker reflects the active day. loadDashboard(true)
    // runs silently (no extra skeletons) and calls renderRxTracker() at the end.
    const dashPromise = loadDashboard(true);

    try{
        const data = await api(`${API_URL}?inventario=1&residente_id=${_residenteId}`);
        _invPageItems=data.items||[]; _invLow=data.low_stock||[];
        renderInvCards();
        loadMovimientos();
    }catch(e){ $('#cdInvList').innerHTML = ''; $('#cdMovList').innerHTML = ''; }

    await dashPromise;
}

function renderInvCards() {
    const alert=$('#cdInvAlert'), list=$('#cdInvList');
    let items = _invFilter==='todos' ? _invPageItems : _invPageItems.filter(i=>i.tipo===_invFilter);
    if (_invSearchText) items = items.filter(i => i.nombre.toLowerCase().includes(_invSearchText) || (i.notas||'').toLowerCase().includes(_invSearchText));

    if(_invLow.length){
        alert.style.display='flex';
        $('#cdInvAlertMsg').textContent=`${_invLow.length} artículo(s) por debajo del umbral mínimo`;
    } else { alert.style.display='none'; }

    if(!items.length){list.innerHTML='<div class="cd-tl-empty"><h3>'+t('inv_empty_title')+'</h3><p>'+t('inv_empty_desc')+'</p></div>';return;}

    list.innerHTML = items.map(i=>`
        <div class="cd-inv-card" data-item-id="${i.id}">
            <div class="cd-inv-card-head">
                <div><div class="cd-inv-name">${esc(i.nombre)}</div><div class="cd-inv-type">${esc(i.tipo)}</div></div>
                ${i.stock_actual<=i.stock_minimo?`<div class="cd-inv-low"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Stock bajo</div>`:''}
            </div>
            <div class="cd-inv-details">
                <div class="cd-inv-detail"><label>Stock actual</label><span>${i.stock_actual} ${esc(i.unidad)}</span></div>
                <div class="cd-inv-detail"><label>Umbral mínimo</label><span>${i.stock_minimo} ${esc(i.unidad)}</span></div>
                <div class="cd-inv-detail"><label>Vencimiento</label><span>${i.vencimiento ? fmtDate(i.vencimiento) : '—'}</span></div>
            </div>
            ${i.notas?`<p class="cd-inv-notes">${esc(i.notas)}</p>`:''}
            <div class="cd-inv-add-actions">
                <button class="${CAN_EDIT ? '' : 'cd-role-locked'}" ${!CAN_EDIT ? `data-cd-locked data-lock-title="Requiere permisos de edición" data-lock-msg="Tu rol actual no permite registrar movimientos de inventario."` : ''} data-perm-id="inv_open_mov_modal_btn" onclick="openMovModal(${i.id})">Movimiento</button>
                <button class="${CAN_EDIT ? '' : 'cd-role-locked'}" ${!CAN_EDIT ? `data-cd-locked data-lock-title="Requiere permisos de edición" data-lock-msg="Tu rol actual no permite editar artículos del inventario."` : ''} data-perm-id="inv_edit_item_btn" onclick="editInvItem(${i.id})">Editar</button>
                <button class="danger${CAN_EDIT ? '' : ' cd-role-locked'}" ${!CAN_EDIT ? `data-cd-locked data-lock-title="Requiere permisos de edición" data-lock-msg="Tu rol actual no permite eliminar artículos del inventario."` : ''} data-perm-id="inv_delete_item_btn" onclick="deleteInvItem(${i.id})">Eliminar</button>
            </div>
        </div>`).join('');
}

async function loadMovimientos() {
    try {
        const data = await api(`${API_URL}?movimientos=1&residente_id=${_residenteId}`);
        const movs = Array.isArray(data) ? data : [];
        const list = $('#cdMovList');
        if(!movs.length){list.innerHTML='<p style="color:var(--cd-text-muted);font-size:0.8125rem;text-align:center">'+t('inv_no_movements_msg')+'</p>';return;}
        list.innerHTML = movs.slice(0,20).map((m,i)=>`
            <div class="cd-mov-card" data-mov-idx="${i}" style="cursor:pointer" ${!IS_ADMIN ? `class="cd-role-locked" data-cd-locked data-lock-title="Solo administradores" data-lock-msg="Solo los administradores pueden editar movimientos de inventario registrados."` : ''}>
                <span class="cd-mov-badge ${m.tipo}">${m.tipo==='entrada'?'▲ Entrada':m.tipo==='salida'?'▼ Salida':'⟳ Ajuste'}</span>
                <div class="cd-mov-info">
                    <strong>${esc(m.item_nombre||'—')}</strong> — ${m.cantidad} uds
                    ${m.motivo?` · ${esc(m.motivo)}`:''}
                    <div class="cd-mov-meta">${esc(m.usuario_nombre||'')} · ${m.creado_at ? fmtDateTime(m.creado_at).date + ' ' + fmtDateTime(m.creado_at).time : ''}</div>
                </div>
            </div>`).join('');
        list._movs = movs;
        $$('.cd-mov-card', list).forEach(card => {
            if (!IS_ADMIN) return; // locked cards block themselves via capture handler
            card.addEventListener('click', () => {
                const idx = parseInt(card.dataset.movIdx);
                const mov = list._movs?.[idx];
                if (mov) openMovEditSidebar(mov);
            });
        });
    }catch(e){}
}

function openMovEditSidebar(mov) {
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Información del movimiento</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>Artículo</th><td>${esc(mov.item_nombre||'—')}</td></tr>
            <tr><th>Registrado por</th><td>${esc(mov.usuario_nombre||'—')}</td></tr>
            <tr><th>Fecha</th><td>${mov.creado_at ? fmtDateTime(mov.creado_at).date + ' ' + fmtDateTime(mov.creado_at).time : '—'}</td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('btn_edit')}</div>
        <div class="cd-form-group"><label class="cd-form-label"><?= t('mov_type_label') ?></label>
            <select class="cd-select" id="cdSbMovEditType">
                <option value="entrada" ${mov.tipo==='entrada'?'selected':''}><?= t('mov_type_entry') ?></option>
                <option value="salida" ${mov.tipo==='salida'?'selected':''}><?= t('mov_type_exit') ?></option>
            </select></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('rx_quantity')}</label>
            <input type="number" class="cd-input" id="cdSbMovEditQty" min="1" value="${mov.cantidad||1}"></div>
        <div class="cd-form-group"><label class="cd-form-label"><?= t('mov_reason_label') ?></label>
            <input type="text" class="cd-input" id="cdSbMovEditReason" value="${esc(mov.motivo||'')}"></div>
    </div>`;
    const actions = `<div class="cd-sidebar-row">
        <button class="cd-btn-submit" id="cdSbMovEditSave" data-perm-id="inv_save_mov_edit_btn"><?= t('btn_save_changes') ?></button>
        <button class="cd-btn-submit cd-btn-danger" id="cdSbMovEditDel" data-perm-id="inv_delete_mov_btn">${t('btn_delete')}</button>
    </div><button class="cd-btn-close-sidebar" id="cdSbMovEditClose">${t('btn_close')}</button>`;
    openSidebar(t('sidebar_movement') + ' #' + (mov.id || ''), body, actions);
    $('#cdSbMovEditClose')?.addEventListener('click', closeSidebar);
    $('#cdSbMovEditSave')?.addEventListener('click', async () => {
        const qty = parseInt($('#cdSbMovEditQty').value);
        if (!qty || qty < 1) { showToast(t('error_invalid_qty'),'error'); return; }
        const _btn = $('#cdSbMovEditSave');
        btnLoading(_btn, t('status_saving'));
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'actualizar_movimiento', id:mov.id,
                    tipo:$('#cdSbMovEditType').value, cantidad:qty,
                    residente_id:_residenteId,
                    motivo:$('#cdSbMovEditReason').value.trim()||null }) });
            closeSidebar();
            showToast(t('toast_mov_updated'),'success');
            loadInventory();
        } catch(e) { btnReset(_btn); }
    });
    $('#cdSbMovEditDel')?.addEventListener('click', async () => {
        if (!await cdConfirm(t('confirm_delete_mov_body'), { title: t('confirm_delete_mov_title'), type: 'danger', okText: t('btn_delete') })) return;
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'eliminar_movimiento', id:mov.id, residente_id:_residenteId }) });
            closeSidebar();
            showToast(t('toast_mov_deleted'),'success');
            loadInventory();
        } catch(e) {}
    });
}

// ── Inventory sidebar (replaces old modal) ───────────────────
$('#cdInvAddBtn')?.addEventListener('click', () => openInvAddSidebar());

function openInvAddSidebar(editItem) {
    const isEdit = !!editItem;
    const body = `<div class="cd-sidebar-section">
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_name')} *</label>
            <input type="text" class="cd-input" id="cdSbInvName" value="${esc(editItem?.nombre||'')}" placeholder="${t('inv_name_placeholder')}"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_type')}</label>
            <select class="cd-select" id="cdSbInvType">
                <option value="medicamento" ${editItem?.tipo==='medicamento'?'selected':''}>${t('inv_type_med')}</option>
                <option value="suplemento" ${editItem?.tipo==='suplemento'?'selected':''}>${t('inv_type_supplement')}</option>
                <option value="insumo" ${editItem?.tipo==='insumo'?'selected':''}>${t('inv_type_supply')}</option>
                <option value="otro" ${editItem?.tipo==='otro'?'selected':''}>${t('inv_type_other')}</option>
            </select></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_unit')}</label>
            <select class="cd-select" id="cdSbInvUnit">
                ${(function(){
                    const opts = [
                        { v: 'unidades',  l: t('unit_units') },
                        { v: 'piezas',    l: t('unit_pieces') },
                        { v: 'tabletas',  l: t('unit_tablets') },
                        { v: 'cápsulas',  l: t('unit_capsules') },
                        { v: 'ml',        l: 'ml' },
                        { v: 'mg',        l: 'mg' },
                        { v: 'g',         l: 'g' },
                        { v: 'frascos',   l: t('unit_bottles') },
                        { v: 'ampolletas',l: t('unit_ampoules') },
                        { v: 'sobres',    l: t('unit_sachets') },
                        { v: 'parches',   l: t('unit_patches') },
                        { v: 'cajas',     l: t('unit_boxes') },
                    ];
                    const cur = (editItem?.unidad || t('inv_units_default')).toString();
                    const exists = opts.some(o => o.v.toLowerCase() === cur.toLowerCase());
                    let html = opts.map(o => `<option value="${esc(o.v)}" ${o.v.toLowerCase()===cur.toLowerCase()?'selected':''}>${esc(o.l)}</option>`).join('');
                    if (!exists && cur) html += `<option value="${esc(cur)}" selected>${esc(cur)}</option>`;
                    html += `<option value="__custom__">${esc(t('unit_other'))}</option>`;
                    return html;
                })()}
            </select>
            <input type="text" class="cd-input" id="cdSbInvUnitCustom" placeholder="${esc(t('unit_other_placeholder'))}" style="display:none;margin-top:6px"></div>
        ${!isEdit ? `<div class="cd-form-group"><label class="cd-form-label">${t('inv_initial_stock')}</label>
            <input type="number" class="cd-input" id="cdSbInvStock" min="0" value="0"></div>` : ''}
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_min_stock')}</label>
            <input type="number" class="cd-input" id="cdSbInvMin" min="0" value="${editItem?.stock_minimo||10}"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_expiry_date')}</label>
            <input type="text" class="cd-input cd-app-date-input" id="cdSbInvExpiry" value="${editItem?.vencimiento ? esc(fmtDate(editItem.vencimiento)) : ''}" data-iso="${esc(editItem?.vencimiento||'')}" placeholder="${appDatePlaceholder()}" inputmode="numeric">
            <div class="cd-quick-chips" style="margin-top:6px">
                <button type="button" class="cd-quick-chip" data-months="3">+3 ${t('months_unit')}</button>
                <button type="button" class="cd-quick-chip" data-months="6">+6 ${t('months_unit')}</button>
                <button type="button" class="cd-quick-chip" data-months="12">+1 ${t('year')}</button>
                <button type="button" class="cd-quick-chip" data-months="24">+2 ${t('years')}</button>
            </div>
        </div>
        <div class="cd-form-group"><label class="cd-form-label">${t('inv_notes')}</label>
            <textarea class="cd-input" id="cdSbInvNotes" rows="3" placeholder="${t('inv_notes_placeholder')}" style="resize:vertical">${esc(editItem?.notas||'')}</textarea></div>
    </div>`;
    const actions = `<button class="cd-btn-submit" id="cdSbInvSave" data-perm-id="inv_save_item_btn">${isEdit?t('btn_update'):t('btn_save')}</button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdSbInvCancel"><?= t('btn_cancel') ?></button>`;
    openSidebar(isEdit ? t('sidebar_edit_inv') : t('sidebar_add_inv'), body, actions);
    $('#cdSbInvCancel').addEventListener('click', closeSidebar);
    initAppDateTextInput($('#cdSbInvExpiry'));
    // Unit-of-measure: toggle custom input when "Otro..." is selected
    {
        const sel = $('#cdSbInvUnit');
        const custom = $('#cdSbInvUnitCustom');
        const syncCustom = () => {
            if (sel.value === '__custom__') {
                custom.style.display = '';
                custom.focus();
            } else {
                custom.style.display = 'none';
                custom.value = '';
            }
        };
        sel?.addEventListener('change', syncCustom);
    }
    // Quick expiry date chips
    $$('.cd-quick-chip[data-months]', sBody).forEach(chip => {
        chip.addEventListener('click', () => {
            const m = parseInt(chip.dataset.months);
            const d = toAppTz(new Date());
            d.setMonth(d.getMonth() + m);
            const y = d.getFullYear(), mo = String(d.getMonth()+1).padStart(2,'0'), dd = String(d.getDate()).padStart(2,'0');
            setAppDateInputValue($('#cdSbInvExpiry'), `${y}-${mo}-${dd}`);
            $$('.cd-quick-chip[data-months]', sBody).forEach(c => c.classList.remove('selected'));
            chip.classList.add('selected');
        });
    });
    $('#cdSbInvSave').addEventListener('click', async () => {
        const name = $('#cdSbInvName').value.trim();
        if (!name) { showToast(t('error_name_required'),'error'); return; }
        // Resolve unit-of-measure: when "Otro..." is selected, use the custom input
        let _unitVal = $('#cdSbInvUnit').value;
        if (_unitVal === '__custom__') {
            _unitVal = ($('#cdSbInvUnitCustom')?.value || '').trim();
            if (!_unitVal) { showToast(t('error_unit_required') || t('error_name_required'),'error'); return; }
        }
        const _btn = $('#cdSbInvSave');
        const expiryIso = appDateInputIso($('#cdSbInvExpiry'), { message:t('inv_expiry_date') + ': ' + appDatePlaceholder() });
        if (expiryIso === null) return;
        btnLoading(_btn, t('status_saving'));
        try {
            if (isEdit) {
                await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ action:'actualizar_inventario', id:editItem.id, nombre:name, tipo:$('#cdSbInvType').value,
                        unidad:_unitVal, stock_minimo:parseInt($('#cdSbInvMin').value)||10, vencimiento:expiryIso||null,
                        notas:$('#cdSbInvNotes').value.trim()||null })
                });
            } else {
                await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ action:'crear_inventario', residente_id:_residenteId, nombre:name, tipo:$('#cdSbInvType').value,
                        unidad:_unitVal, stock_actual:parseInt($('#cdSbInvStock')?.value)||0,
                        stock_minimo:parseInt($('#cdSbInvMin').value)||10, vencimiento:expiryIso||null,
                        notas:$('#cdSbInvNotes').value.trim()||null })
                });
            }
            closeSidebar();
            showToast(isEdit?'Actualizado':'Agregado','success');
            await loadInventoryCache();
            loadInventory();
        } catch(e) { btnReset(_btn); }
    });
}

// ── Movement sidebar (replaces old modal) ────────────────────
window.openMovModal = function(itemId) {
    const item = _invPageItems.find(i => i.id === itemId);
    const body = `<div class="cd-sidebar-section">
        ${item ? `<p style="font-size:0.875rem;font-weight:600;margin:0 0 12px">${esc(item.nombre)} <span style="color:var(--cd-text-muted);font-weight:400">(stock: ${item.stock_actual} ${esc(item.unidad)})</span></p>` : ''}
        <div class="cd-form-group"><label class="cd-form-label"><?= t('mov_type_label') ?></label>
            <select class="cd-select" id="cdSbMovType">
                <option value="entrada"><?= t('mov_type_entry') ?></option>
                <option value="salida"><?= t('mov_type_exit') ?></option>
            </select></div>
        <div class="cd-form-group"><label class="cd-form-label">Cantidad</label>
            <input type="number" class="cd-input" id="cdSbMovQty" min="1" value="1"></div>
        <div class="cd-form-group"><label class="cd-form-label"><?= t('mov_reason_label') ?></label>
            <input type="text" class="cd-input" id="cdSbMovReason" placeholder="<?= t('mov_reason_ph') ?>"></div>
    </div>`;
    const actions = `<button class="cd-btn-submit" id="cdSbMovSave" data-perm-id="inv_save_mov_btn">Registrar</button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdSbMovCancel"><?= t('btn_cancel') ?></button>`;
    openSidebar(t('inv_movement_type'), body, actions);
    $('#cdSbMovCancel').addEventListener('click', closeSidebar);
    $('#cdSbMovSave').addEventListener('click', async () => {
        const qty = parseInt($('#cdSbMovQty').value);
        if (!qty) { showToast(t('error_qty_required'),'error'); return; }
        const movBtn = $('#cdSbMovSave');
        btnLoading(movBtn, t('status_registering'));
        try {
            await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ action:'movimiento_inventario', item_id:itemId, residente_id:_residenteId, tipo:$('#cdSbMovType').value,
                    cantidad:qty, motivo:$('#cdSbMovReason').value.trim()||null })
            });
            closeSidebar();
            showToast(t('toast_mov_recorded'),'success');
            loadInventory();
        } catch(e) { btnReset(movBtn); }
    });
};

window.deleteInvItem = async function(id) {
    if (!await cdConfirm(t('confirm_delete_inv_body'), { title: t('confirm_delete_inv_title'), type: 'danger', okText: t('btn_delete') })) return;
    try {
        await api(API_URL, { method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ action:'eliminar_inventario', id })
        });
        showToast(t('toast_deleted'),'success');
        loadInventory();
    } catch(e) {}
};

window.editInvItem = function(id) {
    const item = _invPageItems.find(i => i.id === id);
    if (item) openInvAddSidebar(item);
};

// Close remaining modals on overlay click
$$('.cd-modal-overlay').forEach(o => o.addEventListener('click', e => {
    if(e.target===o) o.classList.remove('show');
}));

