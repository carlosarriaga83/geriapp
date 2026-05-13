<section id="viewFormIncidente" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/warning.png" alt="" class="cd-form-title-icon cd-form-title-icon--incidente" aria-hidden="true"><?= t('form_incidente_title') ?? 'Registrar incidente' ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <form id="formIncidente" data-cat="incidente">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_incidente_type') ?? 'Tipo de incidente' ?> <span class="cd-required">*</span></label>
            <div class="cd-icon-group" data-field="tipo_incidente">
                <button type="button" class="cd-icon-opt" data-val="Caída">
                    <span class="material-symbols-outlined">falling</span>
                    <span><?= t('incidente_fall') ?? 'Caída' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Golpe">
                    <span class="material-symbols-outlined">bolt</span>
                    <span><?= t('incidente_hit') ?? 'Golpe' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Herida">
                    <span class="material-symbols-outlined">healing</span>
                    <span><?= t('incidente_wound') ?? 'Herida' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Quemadura">
                    <span class="material-symbols-outlined">local_fire_department</span>
                    <span><?= t('incidente_burn') ?? 'Quemadura' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Atragantamiento">
                    <span class="material-symbols-outlined">sick</span>
                    <span><?= t('incidente_choke') ?? 'Atragantamiento' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Crisis / convulsión">
                    <span class="material-symbols-outlined">monitor_heart</span>
                    <span><?= t('incidente_seizure') ?? 'Convulsión' ?></span>
                </button>
                <button type="button" class="cd-icon-opt" data-val="Otro">
                    <span class="material-symbols-outlined">help</span>
                    <span><?= t('hygiene_other') ?></span>
                </button>
            </div>
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_incidente_severity') ?? 'Severidad' ?> <span class="cd-required">*</span></label>
            <div class="cd-icon-group cd-icon-group--3" data-field="severidad">
                <button type="button" class="cd-icon-opt cd-icon-opt--success" data-val="Leve">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?= t('severity_low') ?? 'Leve' ?></span>
                </button>
                <button type="button" class="cd-icon-opt cd-icon-opt--warning" data-val="Moderado">
                    <span class="material-symbols-outlined">warning</span>
                    <span><?= t('severity_med') ?? 'Moderado' ?></span>
                </button>
                <button type="button" class="cd-icon-opt cd-icon-opt--danger" data-val="Grave">
                    <span class="material-symbols-outlined">error</span>
                    <span><?= t('severity_high') ?? 'Grave' ?></span>
                </button>
            </div>
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label" style="font-size:0.75rem;font-weight:400"><?= t('form_add_photo') ?></label>
            <div class="cd-photo-upload" id="cdPhotoIncidente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span><?= t('form_add_photo') ?></span>
                <input type="file" accept="image/*" capture="environment" data-target="antes">
            </div>
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_incidente_location') ?? 'Lugar' ?></label>
            <input type="text" class="cd-input" name="lugar" placeholder="<?= t('form_incidente_location_ph') ?? 'Ej. Habitación, baño, comedor…' ?>">
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_incidente_action') ?? 'Acción tomada' ?></label>
            <textarea class="cd-textarea" name="accion_tomada" placeholder="<?= t('form_incidente_action_ph') ?? 'Primeros auxilios, traslado, aviso al médico…' ?>"></textarea>
        </div>

        <div class="cd-form-group">
            <label class="cd-checkbox-row">
                <input type="checkbox" name="requiere_traslado" value="1">
                <span><?= t('form_incidente_transfer') ?? 'Requirió traslado a hospital' ?></span>
            </label>
        </div>

        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" name="observaciones" placeholder="<?= t('form_notes_placeholder') ?>"></textarea>
        </div>

        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_incidente_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de incidentes."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
