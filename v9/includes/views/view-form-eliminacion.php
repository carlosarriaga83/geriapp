<section id="viewFormEliminacion" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/toilet.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_elim_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formEliminacion" data-cat="eliminacion">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_elim_type') ?> <span class="cd-required">*</span></label>
            <div class="cd-icon-group" data-field="tipo_eliminacion">
                <button type="button" class="cd-icon-opt" data-val="Orina">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3c-1.5 4-5 6-5 11a5 5 0 0 0 10 0c0-5-3.5-7-5-11z"/></svg>
                    <span><?= t('elim_urine') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Heces">
                    <img src="assets/icons/poop.png" alt="Heces">
                    <span><?= t('elim_stool') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Vómito">
                    <img src="assets/icons/vomit.png" alt="Vómito">
                    <span><?= t('elim_vomit') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Sangre">
                    <img src="assets/icons/blood.png" alt="Sangre">
                    <span><?= t('elim_blood') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Otros">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><?= t('elim_other') ?></span>
                </button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_color_aspect') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="color_aspecto" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Normal"><?= t('aspect_normal') ?></option>
                <option value="Claro"><?= t('aspect_light') ?></option>
                <option value="Oscuro"><?= t('aspect_dark') ?></option>
                <option value="Con sangre"><?= t('aspect_blood') ?></option>
                <option value="Con moco"><?= t('aspect_mucus') ?></option>
                <option value="Otro"><?= t('hygiene_other') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_quantity') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="cantidad" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Escasa"><?= t('qty_scarce') ?></option>
                <option value="Normal"><?= t('aspect_normal') ?></option>
                <option value="Abundante"><?= t('qty_abundant') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_diaper_change') ?> <span class="cd-required">*</span></label>
            <div class="cd-btn-group" data-field="cambio_panal">
                <button type="button" class="cd-btn-opt" data-val="Sí"><?= t('opt_yes') ?></button>
                <button type="button" class="cd-btn-opt" data-val="No"><?= t('opt_no') ?></button>
                <span class="cd-panal-stock-badge" id="cdPanalStockBadge"></span>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" name="observaciones" placeholder="<?= t('form_notes_placeholder') ?>"></textarea>
        </div>
        <div class="cd-form-group cd-insumo-picker">
            <button type="button" class="cd-btn-add-med cd-insumo-toggle<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_insumo_toggle_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede registrar insumos."' ?>><?= t('form_insumo_toggle') ?></button>
            <div class="cd-insumo-search-wrap" style="display:none">
                <input type="text" class="cd-input cd-insumo-search" placeholder="<?= t('form_insumo_search') ?>" autocomplete="off">
                <div class="cd-insumo-search-results"></div>
            </div>
            <div class="cd-insumo-list"></div>
        </div>
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_eliminacion_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
