<?php if (in_array($userRole, ['admin','superadmin'], true)): ?>
<section id="viewInvitaciones" class="cd-view">
<div class="cd-inv-page">
    <div class="cd-inv-header">
        <div>
            <h1>Invitaciones</h1>
            <p>Gestiona accesos pendientes, reenvios y bajas de la institucion.</p>
        </div>
        <div class="cd-view-head-actions">
            <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdInvRefreshBtn" data-view-refresh="viewInvitaciones" title="<?= t('tl_refresh') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span><?= t('tl_refresh') ?></span>
            </button>
            <button type="button" class="cd-btn-add" id="cdInvNewBtn" data-perm-id="inv_new_invitation_btn">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nueva invitacion
            </button>
        </div>
    </div>
    <div class="cd-inv-summary" id="cdInvSummary" aria-live="polite"></div>
    <div class="cd-inv-list-panel" id="cdInvListPanel">
        <div class="cd-res-subpanel-loading">Cargando invitaciones...</div>
    </div>
</div>
</section>
<?php endif; ?>