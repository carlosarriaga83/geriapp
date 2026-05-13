<section id="viewResidentes" class="cd-view<?= (($initialView ?? 'viewDashboard') === 'viewResidentes') ? ' active' : '' ?>">
<div class="cd-residentes-page">
    <div class="cd-res-mgmt-header">
        <h1><?= t('nav_home') ?></h1>
        <div class="cd-view-head-actions">
        <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdResMgmtRefreshBtn" data-view-refresh="viewResidentes" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
        <button class="cd-btn-add<?= ($canEditResidents ?? false) ? '' : ' cd-role-locked' ?>" id="cdResMgmtAddBtn" data-perm-id="res_new_resident_btn"<?= ($canEditResidents ?? false) ? '' : ' data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden agregar residentes."' ?>>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo Residente
        </button>
        </div>
    </div>

    <!-- Quick shortcuts -->
    <div class="cd-quick-shortcuts">
        <h3><?= t('qs_title') ?></h3>
        <div class="cd-qs-grid">
            <button type="button" class="cd-qs-btn cd-qs-btn--records<?= $canSecRegistros ? '' : ' cd-role-locked' ?>" data-qs-nav="viewRecords" data-perm-id="dash_qs_view_records_btn"<?= $canSecRegistros ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a registros con tu rol actual."' ?>>
                <span class="cd-qs-icon material-symbols-outlined">description</span>
                <span class="cd-qs-text">
                    <span class="cd-qs-label"><?= t('qs_view_records') ?></span>
                    <span class="cd-qs-sub"><?= t('qs_view_records_sub') ?></span>
                </span>
                <span class="cd-qs-arrow material-symbols-outlined">chevron_right</span>
            </button>
            <button type="button" class="cd-qs-btn cd-qs-btn--med<?= $canSecMedicacion ? '' : ' cd-role-locked' ?>" data-qs-nav="viewInventory" data-perm-id="dash_qs_view_inventory_btn"<?= $canSecMedicacion ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a Medicinas con tu rol actual."' ?>>
                <span class="cd-qs-icon material-symbols-outlined">&#xe11f;</span>
                <span class="cd-qs-text">
                    <span class="cd-qs-label"><?= t('qs_med_today') ?></span>
                    <span class="cd-qs-sub"><?= t('qs_med_today_sub') ?></span>
                </span>
                <span class="cd-qs-arrow material-symbols-outlined">chevron_right</span>
            </button>
            <?php if (in_array($userRole, ['admin','superadmin'], true)): ?>
            <button type="button" class="cd-qs-btn cd-qs-btn--invite" data-qs-nav="viewInvitaciones" data-perm-id="dash_qs_invite_staff_btn">
                <span class="cd-qs-icon material-symbols-outlined">mark_email_unread</span>
                <span class="cd-qs-text">
                    <span class="cd-qs-label">Invitaciones</span>
                    <span class="cd-qs-sub">Administrar accesos pendientes</span>
                </span>
                <span class="cd-qs-arrow material-symbols-outlined">chevron_right</span>
            </button>
            <button type="button" class="cd-qs-btn cd-qs-btn--newres" data-qs-action="newResident" data-perm-id="dash_qs_new_resident_btn">
                <span class="cd-qs-icon material-symbols-outlined">elderly</span>
                <span class="cd-qs-text">
                    <span class="cd-qs-label"><?= t('qs_new_resident') ?></span>
                    <span class="cd-qs-sub"><?= t('qs_new_resident_sub') ?></span>
                </span>
                <span class="cd-qs-arrow material-symbols-outlined">chevron_right</span>
            </button>
            <?php endif; ?>
            <?php if ($userRole === 'familiar'): ?>
            <button type="button" class="cd-qs-btn cd-qs-btn--invite" data-qs-action="inviteFamily" data-perm-id="dash_qs_invite_family_btn">
                <span class="cd-qs-icon material-symbols-outlined">group_add</span>
                <span class="cd-qs-text">
                    <span class="cd-qs-label"><?= t('qs_invite_family') ?></span>
                    <span class="cd-qs-sub"><?= t('qs_invite_family_sub') ?></span>
                </span>
                <span class="cd-qs-arrow material-symbols-outlined">chevron_right</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="cd-res-mgmt-toolbar">
        <input class="cd-input" id="cdResMgmtSearch" placeholder="<?= t('residents_search_ph') ?>" style="flex:1">
        <select class="cd-input cd-select-native" id="cdResMgmtFilter" style="width:auto">
            <option value="activo"><?= t('residents_active') ?></option>
            <option value="egresado"><?= t('residents_discharged') ?></option>
            <option value="fallecido"><?= t('residents_deceased') ?></option>
            <option value=""><?= t('residents_all') ?></option>
        </select>
    </div>

    <div id="cdResMgmtList" class="cd-res-mgmt-list"></div>

</div>
</section>
