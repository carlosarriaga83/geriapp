<section id="viewFormSueno" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/moon-zzz.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_sleep_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formSueno" data-cat="sueno">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_sleep_range') ?></label>
            <div class="cd-time-row">
                <div class="cd-form-group">
                    <label class="cd-form-label" style="font-size:0.75rem;font-weight:400"><?= t('form_sleep_start') ?></label>
                    <div class="cd-time-with-icon">
                        <img src="assets/icons/sleep.png" class="cd-sleep-icon" alt="">
                        <input type="time" class="cd-time-input" name="hora_inicio" value="22:00">
                    </div>
                    <div class="cd-sleep-day-toggle" id="cdSleepStartDayToggle">
                        <button type="button" class="cd-sleep-day-btn active" data-day-rel="today">Hoy</button>
                        <button type="button" class="cd-sleep-day-btn" data-day-rel="prev">Ayer</button>
                        <input type="hidden" name="inicio_dia_relativo" id="cdSleepStartDayRel" value="today">
                    </div>
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label" style="font-size:0.75rem;font-weight:400"><?= t('form_sleep_end') ?></label>
                    <div class="cd-time-with-icon">
                        <img src="assets/icons/awake.png" class="cd-sleep-icon" alt="">
                        <input type="time" class="cd-time-input" name="hora_fin" value="06:00">
                        <span id="cdSleepWakeUnknown" class="cd-sleep-wake-unknown" style="display:none"><?= t('sleep_wake_unknown') ?></span>
                    </div>
                    <label class="cd-sleep-pending-cb">
                        <input type="checkbox" id="cdSleepPendingCb">
                        <?= t('sleep_pending_cb') ?>
                    </label>
                    <div id="cdSleepPendingHint" class="cd-sleep-pending-hint"><?= t('sleep_pending_hint') ?></div>
                </div>
            </div>
            <div id="cdSleepDateHint" class="cd-sleep-date-hint" style="display:none"></div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_sleep_hours') ?></label>
            <div class="cd-slider-labeled cd-sleep-slider-wrap">
                <div class="cd-sleep-hours-display" id="cdSleepHoursVal">8h</div>
                <div class="cd-slider-wrap">
                    <input type="range" class="cd-slider" id="cdSleepHoursSlider" name="horas" min="0" max="16" value="8" step="0.5" style="display: none">
                </div>
                <div class="cd-slider-labels cd-sleep-ticks" style="display: none">
                    <span>0</span>
                    <span>2</span>
                    <span>4</span>
                    <span>6</span>
                    <span>8</span>
                    <span>10</span>
                    <span>12</span>
                    <span>14</span>
                    <span>16</span>
                </div>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_sleep_quality') ?> <span class="cd-required">*</span></label>
            <div class="cd-slider-labeled">
                <div class="cd-slider-wrap">
                    <input type="range" class="cd-slider" name="calidad_pct" min="0" max="100" value="50" step="25">
                    <span class="cd-slider-val">50%</span>
                </div>
                <div class="cd-slider-labels">
                    <span><?= t('sleep_very_bad') ?></span>
                    <span><?= t('sleep_bad') ?></span>
                    <span><?= t('sleep_regular') ?></span>
                    <span><?= t('sleep_good') ?></span>
                    <span><?= t('sleep_excellent') ?></span>
                </div>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" name="observaciones" placeholder="<?= t('form_sleep_obs') ?>"></textarea>
        </div>
        <div class="cd-form-group cd-insumo-picker">
            <button type="button" class="cd-btn-add-med cd-insumo-toggle<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_insumo_toggle_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede registrar insumos."' ?>><?= t('form_insumo_toggle') ?></button>
            <div class="cd-insumo-search-wrap" style="display:none">
                <input type="text" class="cd-input cd-insumo-search" placeholder="<?= t('form_insumo_search') ?>" autocomplete="off">
                <div class="cd-insumo-search-results"></div>
            </div>
            <div class="cd-insumo-list"></div>
        </div>
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_sueno_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
