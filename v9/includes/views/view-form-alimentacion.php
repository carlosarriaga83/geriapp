<section id="viewFormAlimentacion" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/food.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_nutrition_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formAlimentacion" data-cat="alimentacion">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">

        </div>
        <div class="cd-photo-row">
            <div>
                <label class="cd-form-label" style="font-size:0.75rem;font-weight:400"><?= t('form_photo_before') ?></label>
                <div class="cd-photo-upload" id="cdPhotoAntes">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span><?= t('form_add_photo') ?></span>
                    <!-- El atributo capture="environment" es la clave -->
                    <input type="file" accept="image/*" capture="environment" data-target="antes">
                </div>
            </div>
            <div>
                <label class="cd-form-label" style="font-size:0.75rem;font-weight:400"><?= t('form_photo_after') ?></label>
                <div class="cd-photo-upload" id="cdPhotoDespues">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span><?= t('form_add_photo') ?></span>
                    <!-- El atributo capture="environment" es la clave -->
                    <input type="file" accept="image/*" capture="environment" data-target="despues">
                </div>
            </div>
        </div>
        <div class="cd-form-group" style="margin-top:16px">
            <label class="cd-form-label"><?= t('form_meal_type') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="tipo_comida" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Desayuno"><?= t('meal_breakfast') ?></option>
                <option value="Almuerzo"><?= t('meal_brunch') ?></option>
                <option value="Comida"><?= t('meal_lunch') ?></option>
                <option value="Merienda"><?= t('meal_snack') ?></option>
                <option value="Cena"><?= t('meal_dinner') ?></option>
                <option value="Colación"><?= t('meal_collation') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_intake_pct') ?> <span class="cd-required">*</span></label>
            <div class="cd-intake-steps" data-field="ingesta_pct">
                <button type="button" class="cd-intake-step" data-val="0">0%<br><small><?= t('intake_nothing') ?></small></button>
                <button type="button" class="cd-intake-step" data-val="25">25%<br><small><?= t('intake_little') ?></small></button>
                <button type="button" class="cd-intake-step" data-val="50">50%<br><small><?= t('intake_half') ?></small></button>
                <button type="button" class="cd-intake-step" data-val="75">75%<br><small><?= t('intake_almost_all') ?></small></button>
                <button type="button" class="cd-intake-step" data-val="100">100%<br><small><?= t('intake_all') ?></small></button>
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
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_alimentacion_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
