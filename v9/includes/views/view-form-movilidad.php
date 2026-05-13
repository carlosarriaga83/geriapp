<section id="viewFormMovilidad" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/walk.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_mobility_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formMovilidad" data-cat="movilidad">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_assistance_level') ?> <span class="cd-required">*</span></label>
            <div class="cd-icon-group" data-field="nivel_asistencia">
                <button type="button" class="cd-icon-opt" data-val="Independiente">
                    <span class="material-symbols-outlined">directions_walk</span>
                    <span><?= t('assist_independent') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Asistencia mínima">
                    <span class="material-symbols-outlined">blind</span>
                    <span><?= t('assist_min') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Asistencia parcial">
                    <span class="material-symbols-outlined">assist_walker</span>
                    <span><?= t('assist_partial') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Asistencia máxima">
                    <span class="material-symbols-outlined">accessible</span>
                    <span><?= t('assist_max') ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Dependencia total">
                    <span class="material-symbols-outlined">&#xf1ab;</span>
                    <span><?= t('assist_full_dep') ?></span>
                </button>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_activity_type') ?> <span class="cd-required">*</span></label>
            <select class="cd-select" name="tipo_actividad" required>
                <option value=""><?= t('form_select') ?></option>
                <option value="Caminata"><?= t('mobility_walk') ?></option>
                <option value="Ejercicios en cama"><?= t('mobility_bed') ?></option>
                <option value="Ejercicios en silla"><?= t('mobility_chair') ?></option>
                <option value="Transferencia"><?= t('mobility_transfer') ?></option>
                <option value="Subir/bajar escaleras"><?= t('mobility_stairs') ?></option>
                <option value="Otro"><?= t('hygiene_other') ?></option>
            </select>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_duration') ?> <span class="cd-required">*</span></label>
            <div class="cd-btn-group" data-field="duracion">
                <?php for ($m = 0; $m <= 120; $m += 10): ?>
                <button type="button" class="cd-btn-opt" data-val="<?= $m ?>"><?= $m ?></button>
                <?php endfor; ?>
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
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_movilidad_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
