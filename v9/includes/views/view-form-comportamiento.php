<section id="viewFormComportamiento" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/head-ia.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_behavior_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formComportamiento" data-cat="comportamiento">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_mood') ?> <span class="cd-required">*</span></label>
            <div class="cd-mood-bar" data-field="estado_animo">
                <!-- Material Symbols: sentiment_very_dissatisfied -->
                <button type="button" class="cd-mood-btn cd-mood-btn--very-sad" data-val="Muy triste">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.53 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.88-9.54L17.29 9.05l-1.41-1.41-1.41 1.41-1.41-1.41-1.42 1.41 1.42 1.41-1.42 1.42 1.42 1.41 1.41-1.41 1.41 1.41 1.41-1.41-1.42-1.42zm-7.76 0L9.54 9.05 8.12 7.64 6.71 9.05 5.29 7.64 3.88 9.05l1.41 1.41-1.41 1.42 1.41 1.41 1.42-1.41 1.41 1.41 1.42-1.41-1.42-1.42zM12 14c-2.33 0-4.31 1.46-5.11 3.5h10.22c-.8-2.04-2.78-3.5-5.11-3.5z"/></svg>
                    <span><?= t('mood_very_sad') ?></span>
                </button>
                <!-- Material Symbols: sentiment_dissatisfied -->
                <button type="button" class="cd-mood-btn cd-mood-btn--sad" data-val="Triste">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.53 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 3c-2.33 0-4.31 1.46-5.11 3.5h10.22c-.8-2.04-2.78-3.5-5.11-3.5z"/></svg>
                    <span><?= t('mood_sad') ?></span>
                </button>
                <!-- Material Symbols: sentiment_neutral -->
                <button type="button" class="cd-mood-btn cd-mood-btn--neutral" data-val="Neutral">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.53 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zM9 14h6v1.5H9zm6.5-4c.83 0 1.5-.67 1.5-1.5S16.33 7 15.5 7 14 7.67 14 8.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 7 8.5 7 7 7.67 7 8.5 7.67 10 8.5 10z"/></svg>
                    <span><?= t('mood_neutral') ?></span>
                </button>
                <!-- Material Symbols: sentiment_satisfied -->
                <button type="button" class="cd-mood-btn cd-mood-btn--happy" data-val="Feliz">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.53 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                    <span><?= t('mood_happy') ?></span>
                </button>
                <!-- Material Symbols: sentiment_very_satisfied -->
                <button type="button" class="cd-mood-btn cd-mood-btn--very-happy" data-val="Muy feliz">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.47 2 12s4.47 10 9.99 10C17.52 22 22 17.53 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm4.18-12.24-.69.85c-.41.5-1.16.65-1.69.32C12.78 8.55 12 8 12 8s-.78.55-1.8 1.93c-.53.33-1.28.18-1.69-.32l-.69-.85C7.34 8.31 7.5 7.6 8.05 7.32 8.85 6.93 10.04 6.5 12 6.5s3.15.43 3.95.82c.55.28.71.99.23 1.44zM12 17.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                    <span><?= t('mood_very_happy') ?></span>
                </button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_incidents') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="incidentes" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Ninguno"><?= t('incident_none') ?></option>
                <option value="Agitación"><?= t('incident_agitation') ?></option>
                <option value="Confusión"><?= t('incident_confusion') ?></option>
                <option value="Agresividad verbal"><?= t('incident_verbal_aggr') ?></option>
                <option value="Agresividad física"><?= t('incident_physical_aggr') ?></option>
                <option value="Deambulación"><?= t('incident_wandering') ?></option>
                <option value="Alucinaciones"><?= t('incident_hallucination') ?></option>
                <option value="Otro"><?= t('hygiene_other') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_stability') ?></label>
            <div class="cd-slider-wrap">
                <input type="range" class="cd-slider" name="estabilidad_pct" min="0" max="100" value="50">
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
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_comportamiento_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
