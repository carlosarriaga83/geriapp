<section id="viewFormHigiene" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/handwash.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_hygiene_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formHigiene" data-cat="higiene">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_hygiene_type') ?> <span class="cd-required">*</span></label>
            <div class="cd-icon-group" data-field="tipo_higiene">
                <button type="button" class="cd-icon-opt" data-val="Ducha">
                    <img src="assets/icons/shower.png" alt="Ducha">
                    <span><?= t('hygiene_shower') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Baño de esponja">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-label="Esponja">
                        <rect x="8" y="14" width="48" height="36" rx="6"/>
                        <circle cx="20" cy="26" r="2.4" fill="currentColor" stroke="none"/>
                        <circle cx="32" cy="22" r="2.4" fill="currentColor" stroke="none"/>
                        <circle cx="44" cy="28" r="2.4" fill="currentColor" stroke="none"/>
                        <circle cx="22" cy="38" r="2.4" fill="currentColor" stroke="none"/>
                        <circle cx="34" cy="40" r="2.4" fill="currentColor" stroke="none"/>
                        <circle cx="46" cy="40" r="2.4" fill="currentColor" stroke="none"/>
                        <line x1="8"  y1="34" x2="56" y2="34"/>
                    </svg>
                    <span><?= t('hygiene_sponge') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Lavado de manos">
                    <img src="assets/icons/handwash.png" alt="Lavado">
                    <span><?= t('hygiene_handwash') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Higiene bucal">
                    <img src="assets/icons/toothbrush.png" alt="Bucal">
                    <span><?= t('hygiene_oral') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Cambio de ropa">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h4l4 2 4-2h4v6l-3 1v11H7V11L4 10V4z"/></svg>
                    <span><?= t('hygiene_clothing') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Otro">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v.01"/><path d="M12 8a2.5 2.5 0 0 1 1.5 4.5L12 14"/></svg>
                    <span><?= t('hygiene_other') ?></span>
                </button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_assistance_level') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="asistencia" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Independiente"><?= t('assist_independent') ?></option>
                <option value="Supervisión"><?= t('assist_supervision') ?></option>
                <option value="Asistencia parcial"><?= t('assist_partial') ?></option>
                <option value="Asistencia total"><?= t('assist_total') ?></option>
            </select>
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
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_higiene_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
