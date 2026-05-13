<section id="viewInventory" class="cd-view">
<div class="cd-inventory">
    <div class="cd-inventory-head">
        <h1>Gestión de Medicación</h1>
        <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdInvRefreshBtn" data-view-refresh="viewInventory" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
    </div>

    <div class="cd-period-tabs cd-med-mgmt-tabs" id="cdMedMgmtTabs">
        <span class="cd-med-mgmt-tabs-pill" aria-hidden="true"></span>
        <button class="cd-period-tab active" data-subtab="rx">
            <span class="material-symbols-outlined">prescriptions</span>
            <span><?= t('rx_day_title') ?></span>
        </button>
        <button class="cd-period-tab" data-subtab="inv">
            <span class="material-symbols-outlined">inventory_2</span>
            <span><?= t('inv_title') ?></span>
        </button>
    </div>

    <div class="cd-med-mgmt-pane active" id="cdMedMgmtPaneRx">
        <div class="cd-rx-add-bar">
            <button type="button" class="cd-btn-add cd-btn-add-occ<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdNewOccMedBtn" data-perm-id="inv_new_occ_med_btn" title="<?= t('ficha_new_occ_med') ?>"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar medicamentos ocasionales."' ?>>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span><?= t('ficha_new_occ_med') ?></span>
            </button>
            <button type="button" class="cd-btn-add cd-btn-add-rx-main<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdNewRxBtn" data-perm-id="inv_new_rx_btn" title="<?= t('ficha_new_rx') ?>"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar prescripciones."' ?>>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span><?= t('ficha_new_rx') ?></span>
            </button>
        </div>
        <div class="cd-rx-tracker" id="cdRxTracker" style="display:none">
            <div class="cd-rx-tracker-head">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
                <span><?= t('rx_day_title') ?></span>
                <span class="cd-rx-tracker-summary" id="cdRxTrackerSum"></span>
            </div>
            <div class="cd-rx-tracker-pills" id="cdRxTrackerPills"></div>
        </div>
        <div class="cd-tl-empty" id="cdRxTrackerEmpty" style="display:none;margin-top:8px">
            <h3><?= t('rx_day_title') ?></h3>
            <p><?= t('inv_empty_desc') ?></p>
        </div>
    </div>

    <div class="cd-med-mgmt-pane" id="cdMedMgmtPaneInv">
        <div class="cd-inv-toolbar">
            <div class="cd-period-tabs" id="cdInvTabs" style="flex:1;margin:0">
                <button class="cd-period-tab active" data-tab="todos"><?= t('records_all') ?></button>
                <button class="cd-period-tab" data-tab="medicamento"><?= t('inv_type_med') ?></button>
                <button class="cd-period-tab" data-tab="suplemento"><?= t('inv_type_supplement') ?></button>
                <button class="cd-period-tab" data-tab="insumo"><?= t('inv_type_supply') ?></button>
            </div>
            <button class="cd-btn-add<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdInvAddBtn" data-perm-id="inv_add_item_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar artículos al inventario."' ?>>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                +Agregar
            </button>
        </div>
        <div class="cd-inv-search-bar" style="margin-bottom:12px">
            <input type="text" class="cd-input" id="cdInvSearchBar" placeholder="Buscar en inventario¦" autocomplete="off" style="width:100%">
        </div>
        <div class="cd-inv-alert" id="cdInvAlert" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <h2><?= t('inv_low_stock') ?></h2>
                <p id="cdInvAlertMsg"></p>
            </div>
        </div>
        <div class="cd-inv-grid" id="cdInvList"></div>
        <h2 style="font-size:1rem;font-weight:600;margin:24px 0 8px">Registro de movimientos</h2>
        <div class="cd-mov-list" id="cdMovList"></div>
    </div>
</div>
</section>
