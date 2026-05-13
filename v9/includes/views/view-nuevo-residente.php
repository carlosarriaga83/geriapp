<?php
// Nuevo Residente — formulario completo tipo Ficha
// Solo accesible para admin / superadmin (alta de residentes).
if (!in_array($userRole, ['admin','superadmin'], true)) return;
?>
<section id="viewNuevoResidente" class="cd-view">
<div class="cd-residente-page">

    <div class="cd-form-header">
        <h2 class="cd-form-title"><?= t('nr_title') ?></h2>
        <button class="cd-form-back" data-back type="button" id="cdNrBack">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>

    <p class="cd-nr-required-hint" style="font-size:0.8125rem;color:var(--cd-text-muted);margin:0 0 12px">
        <?= t('nr_required_hint') ?>
    </p>

    <!-- ── Sección: Datos personales ─────────────────────── -->
    <div class="cd-res-section-card">
        <div class="cd-res-section-header" style="margin-top:0">
            <h3 style="margin:0">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <?= t('nr_section_personal') ?>
            </h3>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label">Nombre <span class="cd-required">*</span></label>
                <input class="cd-input" id="cdNrNombre" data-required="Nombre">
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label">Apellidos <span class="cd-required">*</span></label>
                <input class="cd-input" id="cdNrApellidos" data-required="Apellidos">
            </div>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('ficha_birthdate') ?> <span class="cd-required">*</span></label>
                <input type="text" class="cd-input cd-app-date-input" id="cdNrFechaNac" data-required="<?= t('ficha_birthdate') ?>" inputmode="numeric">
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('ficha_gender') ?> <span class="cd-required">*</span></label>
                <select class="cd-input cd-select-native" id="cdNrSexo" data-required="<?= t('ficha_gender') ?>">
                    <option value="">—</option>
                    <option value="M"><?= t('gender_male') ?></option>
                    <option value="F"><?= t('gender_female') ?></option>
                    <option value="Otro"><?= t('hygiene_other') ?></option>
                </select>
            </div>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('ficha_admission_date') ?> <span class="cd-required">*</span></label>
                <input type="text" class="cd-input cd-app-date-input" id="cdNrFechaIngreso" data-required="<?= t('ficha_admission_date') ?>" inputmode="numeric">
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('ficha_room') ?> <span class="cd-required">*</span></label>
                <input class="cd-input" id="cdNrHabitacion" data-required="<?= t('ficha_room') ?>">
            </div>
        </div>
    </div>

    <!-- ── Sección: Información médica ───────────────────── -->
    <div class="cd-res-section-card">
        <div class="cd-res-section-header" style="margin-top:0">
            <h3 style="margin:0">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <?= t('nr_section_medical') ?>
            </h3>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('ficha_diagnosis') ?></label>
            <div class="cd-nm-dx-wrap" id="cdNrDxWrap">
                <div class="cd-nm-dx-input-wrap">
                    <input type="text" id="cdNrDxSearch" class="cd-nm-dx-search" placeholder="Buscar código o diagnóstico CIE-10" autocomplete="off">
                    <div class="cd-nm-dx-dropdown" id="cdNrDxDropdown"></div>
                </div>
                <div class="cd-nm-dx-tags" id="cdNrDxTags"></div>
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('ficha_allergies') ?></label>
            <textarea class="cd-textarea" id="cdNrAlergias" rows="2" placeholder="<?= t('res_allergies_placeholder') ?>"></textarea>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('ficha_special_care') ?></label>
            <textarea class="cd-textarea" id="cdNrCuidados" rows="2"></textarea>
        </div>
    </div>

    <!-- ── Sección: Contacto familiar ────────────────────── -->
    <div class="cd-res-section-card">
        <div class="cd-res-section-header" style="margin-top:0">
            <h3 style="margin:0">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?= t('nr_section_contact') ?>
            </h3>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('users_name') ?></label>
                <input class="cd-input" id="cdNrCtNombre">
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('res_relationship') ?></label>
                <input class="cd-input" id="cdNrCtParentesco">
            </div>
        </div>
        <div class="cd-form-row">
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('sidebar_phone') ?></label>
                <input class="cd-input" id="cdNrCtTelefono" type="tel">
            </div>
            <div class="cd-form-group" style="flex:1">
                <label class="cd-form-label"><?= t('sidebar_phone') ?> 2</label>
                <input class="cd-input" id="cdNrCtTelefono2" type="tel">
            </div>
        </div>
        <div class="cd-form-group">
            <label class="cd-form-label"><?= t('users_email') ?></label>
            <input class="cd-input" id="cdNrCtEmail" type="email">
        </div>
    </div>

    <!-- ── Sección: Notas ────────────────────────────────── -->
    <div class="cd-res-section-card">
        <div class="cd-res-section-header" style="margin-top:0">
            <h3 style="margin:0">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?= t('nr_section_notes') ?>
            </h3>
        </div>
        <div class="cd-form-group">
            <textarea class="cd-textarea" id="cdNrNotas" rows="3" placeholder="<?= t('res_notes_placeholder') ?>"></textarea>
        </div>
    </div>

    <!-- ── Sección: Acceso de cuidadores ─────────────────── -->
    <div class="cd-res-section-card">
        <div class="cd-res-section-header" style="margin-top:0">
            <h3 style="margin:0">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?= t('nr_section_access') ?>
            </h3>
        </div>
        <p style="font-size:0.8125rem;color:var(--cd-text-muted);margin:0 0 12px">
            <?= t('nr_access_hint') ?>
        </p>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
            <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdNrToggleAllCare" data-perm-id="nr_toggle_all_caregivers_btn" style="font-size:0.8125rem;padding:6px 12px">
                <?= t('nr_select_all') ?>
            </button>
            <span id="cdNrCareCounter" style="font-size:0.8125rem;color:var(--cd-text-muted)">0 <?= t('nr_caregivers_count') ?></span>
        </div>
        <input class="cd-input" id="cdNrCareSearch" placeholder="Buscar cuidador…" style="margin-bottom:8px">
        <div id="cdNrCareList" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;padding:4px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-bg)">
            <span style="font-size:0.8125rem;color:var(--cd-text-muted);padding:8px"><?= t('nr_loading_caregivers') ?></span>
        </div>
    </div>

    <!-- ── Acciones ──────────────────────────────────────── -->
    <div class="cd-res-edit-actions" style="display:flex;gap:8px;padding:8px 0 24px;flex-wrap:wrap">
        <button class="cd-btn-submit" id="cdNrSaveBtn" data-perm-id="nr_save_new_resident_btn" type="button">
            <?= t('nr_create_btn') ?>
        </button>
        <button class="cd-btn-submit cd-btn-secondary" id="cdNrCancelBtn" type="button">
            <?= t('btn_cancel') ?>
        </button>
    </div>

</div>
</section>
