<section id="viewFormTerapia" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/terapia.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_therapy_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formTerapia" data-cat="terapia">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_therapy_type') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="tipo_terapia" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Fisioterapia"><?= t('therapy_physio') ?></option>
                <option value="Terapia ocupacional"><?= t('therapy_occupational') ?></option>
                <option value="Estimulación cognitiva"><?= t('therapy_cognitive') ?></option>
                <option value="Terapia del lenguaje"><?= t('therapy_speech') ?></option>
                <option value="Musicoterapia"><?= t('therapy_music') ?></option>
                <option value="Terapia recreativa"><?= t('therapy_recreational') ?></option>
                <option value="Otra"><?= t('therapy_other') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_session_duration') ?> <span class="cd-required">*</span></label>
            <div class="cd-btn-group" data-field="duracion">
                <?php for ($m = 0; $m <= 120; $m += 15): ?>
                <button type="button" class="cd-btn-opt" data-val="<?= $m ?>"><?= $m ?></button>
                <?php endfor; ?>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_participation') ?></label>
            <div class="cd-slider-wrap">
                <input type="range" class="cd-slider" name="participacion_pct" min="0" max="100" value="50">
                <span class="cd-slider-val">50%</span>
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
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_terapia_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
