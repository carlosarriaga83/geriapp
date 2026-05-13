<section id="viewFormMedicacion" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/pill.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_med_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formMedicacion" data-cat="medicacion">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <div class="cd-med-prescribed-head">
                <div class="cd-med-prescribed-title">
                    <label class="cd-form-label" style="margin:0"><?= t('rx_prescribed') ?> <span class="cd-required">*</span></label>
                    <span class="cd-med-count" id="cdMedCount">0 seleccionados</span>
                </div>
                <button type="button" class="cd-btn-add-med cd-btn-add-rx<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdOpenRxFromMedForm" data-perm-id="form_open_rx_from_med_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar medicamentos programados."' ?>>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <?= t('btn_register_scheduled_med') ?>
                </button>
            </div>
            <div class="cd-med-list" id="cdMedList">
                <!-- Populated by JS from prescripciones -->
            </div>
        </div>
        <div class="cd-form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label class="cd-form-label" style="margin:0"><?= t('label_occasional_med') ?></label>
                <button type="button" class="cd-btn-add-med<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdOpenOccMedFromForm" data-perm-id="form_open_occ_med_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar medicamentos ocasionales."' ?>><?= t('btn_register_occ_med') ?></button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" name="observaciones" placeholder="<?= t('form_notes_placeholder') ?>"></textarea>
        </div>
        <div class="cd-med-submit-sticky">
            <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de medicación."' ?>><?= t('btn_save_record') ?></button>
        </div>
    </form>
</div>
</section>
