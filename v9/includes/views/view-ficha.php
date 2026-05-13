<section id="viewFicha" class="cd-view">
<div class="cd-residente-page">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><?= t('nav_ficha') ?></h2>
        <div class="cd-form-header-actions">
        <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdFichaRefreshBtn" data-view-refresh="viewFicha" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
        <button class="cd-form-back" data-back type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
        </div>
    </div>

    <!-- Avatar + Name (always visible) -->
    <div class="cd-res-full-card cd-res-avatar-card">
        <div class="cd-res-info-header">
            <div class="cd-res-avatar cd-res-avatar-lg" id="cdResAvatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <div class="cd-avatar-overlay" id="cdAvatarOverlay">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
                <span class="cd-avatar-edit-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
            </div>
            <div>
                <h2 class="cd-res-name" id="cdResName" style="font-size:1.25rem">—</h2>
                <p class="cd-res-age" id="cdResAge"></p>
            </div>
        </div>
        <?php if ($canEditResidents): ?>
        <div class="cd-res-status-panel" id="cdResStatusPanel">
            <div class="cd-res-field cd-res-status-field" data-field="estado">
                <label><?= t('ficha_status') ?? 'Estado' ?></label>
                <span id="cdResEstado">—</span>
                <select class="cd-inline-edit">
                    <option value="activo"><?= t('status_active') ?></option>
                    <option value="egresado"><?= t('status_discharged') ?></option>
                    <option value="fallecido"><?= t('status_deceased') ?></option>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (false): ?>
    <!-- Ficha Tab Navigation -->
    <div class="cd-ficha-tabs">
        <span class="cd-tabs-pill" aria-hidden="true"></span>
        <button class="cd-ficha-tab active" data-ficha-tab="info">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <?= t('ficha_tab_info') ?>
        </button>
        <button class="cd-ficha-tab" data-ficha-tab="familia">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <?= t('ficha_tab_family') ?>
        </button>
        <button class="cd-ficha-tab" data-ficha-tab="notif">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?= t('config_notifications') ?>
            <span class="cd-ficha-tab-badge" id="cdNotifBadge" style="display:none">0</span>
        </button>
    </div>

    <?php endif; ?>

    <!-- ── Información ─────────────────────────── -->
    <div class="cd-ficha-tab-panel active" data-ficha-panel="info">
        <div class="cd-res-section-card">
            <div class="cd-res-section-header" style="margin-top:0">
                <h3 style="margin:0">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <?= t('ficha_section_general') ?>
                </h3>
                <button class="cd-res-edit-btn<?= $canEditResidents ? '' : ' cd-role-locked' ?>" id="cdResInfoEditBtn" data-perm-id="ficha_edit_info_btn"<?= $canEditResidents ? '' : ' data-cd-locked data-lock-title="Requiere permisos de administrador" data-lock-msg="Solo los administradores pueden editar la ficha del residente."' ?>><?= t('btn_edit') ?></button>
            </div>
            <div class="cd-res-grid" id="cdResGrid">
                <div class="cd-res-field" data-field="nombre"><label><?= t('ficha_name') ?></label><span id="cdResNombre">—</span><input class="cd-inline-edit" type="text"></div>
                <div class="cd-res-field" data-field="apellidos"><label><?= t('ficha_surname') ?></label><span id="cdResApellidos">—</span><input class="cd-inline-edit" type="text"></div>
                <div class="cd-res-field" data-field="habitacion"><label><?= t('ficha_room') ?></label><span id="cdResRoom">—</span><input class="cd-inline-edit" type="text"></div>
                <div class="cd-res-field" data-field="sexo"><label><?= t('ficha_gender') ?></label><span id="cdResSex">—</span>
                    <select class="cd-inline-edit"><option value="">—</option><option value="M"><?= t('gender_male') ?></option><option value="F"><?= t('gender_female') ?></option><option value="Otro"><?= t('hygiene_other') ?></option></select>
                </div>
                <div class="cd-res-field" data-field="fecha_nacimiento"><label><?= t('ficha_birthdate') ?></label><span id="cdResDOB">—</span><input class="cd-inline-edit cd-app-date-input" type="text" inputmode="numeric"></div>
                <div class="cd-res-field" data-field="fecha_ingreso"><label><?= t('ficha_admission_date') ?></label><span id="cdResAdmit">—</span><input class="cd-inline-edit cd-app-date-input" type="text" inputmode="numeric"></div>
                <div class="cd-res-field full" data-field="diagnostico"><label><?= t('ficha_diagnosis') ?></label><span id="cdResDiag">—</span><textarea class="cd-inline-edit"></textarea></div>
                <div class="cd-res-field full" data-field="alergias"><label><?= t('ficha_allergies') ?></label><span id="cdResAllergy">—</span><textarea class="cd-inline-edit"></textarea></div>
                <div class="cd-res-field" data-field="medico_nombre"><label><?= t('sidebar_doctor') ?></label><span id="cdResDoctor">—</span><input class="cd-inline-edit" type="text" placeholder="<?= t('ficha_doctor_ph') ?>" list="cdDoctorList" autocomplete="off"><datalist id="cdDoctorList"></datalist></div>
            </div>
            <div class="cd-res-extra-divider">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <?= t('ficha_additional_info') ?>
            </div>
            <div class="cd-res-grid" id="cdResExtraGrid">
                <div class="cd-res-field" data-field="estado_civil"><label><?= t('ficha_civil_status') ?></label><span id="cdResEstadoCivil">—</span>
                    <select class="cd-inline-edit"><option value="">—</option><option value="Soltero/a"><?= t('civil_single') ?></option><option value="Casado/a"><?= t('civil_married') ?></option><option value="Viudo/a"><?= t('civil_widowed') ?></option><option value="Divorciado/a"><?= t('civil_divorced') ?></option><option value="Unión libre"><?= t('civil_common_law') ?></option></select>
                </div>
                <div class="cd-res-field" data-field="curp"><label><?= t('ficha_curp') ?></label><span id="cdResCurp">—</span><input class="cd-inline-edit" type="text" maxlength="18"></div>
                <div class="cd-res-field" data-field="nss"><label><?= t('ficha_nss') ?></label><span id="cdResNss">—</span><input class="cd-inline-edit" type="text"></div>
                <div class="cd-res-field full" data-field="cuidados_especiales"><label><?= t('ficha_special_care') ?></label><span id="cdResCuidados">—</span><textarea class="cd-inline-edit"></textarea></div>
                <div class="cd-res-field full" data-field="notas"><label><?= t('ficha_notes') ?? 'Notas' ?></label><span id="cdResNotas">—</span><textarea class="cd-inline-edit"></textarea></div>
            </div>
            <?php if ($canEditResidents): ?>
            <div class="cd-res-edit-actions" id="cdResInfoEditActions" style="display:none;padding:16px 0 0;gap:8px">
                <button class="cd-btn-submit cd-btn-save-edit" id="cdResInfoSaveBtn" data-perm-id="ficha_save_info_btn"><?= t('btn_save_changes') ?></button>
                <button class="cd-btn-submit cd-btn-cancel-edit" id="cdResInfoCancelBtn"><?= t('btn_cancel') ?></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (false): ?>
    <!-- ── Tab: Familiares ─────────────────────── -->
    <div class="cd-ficha-tab-panel" data-ficha-panel="familia">
        <div class="cd-res-section-card">
            <?php if ($canEditFamily): ?>
            <div class="cd-res-section-header" style="margin-top:0">
                <h3 style="margin:0">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Contactos Familiares
                </h3>
                <div style="display:flex;gap:6px;align-items:center">
                    <button class="cd-res-edit-btn" id="cdResFamEditBtn"><?= t('btn_edit') ?></button>
                    <button type="button" class="cd-btn-add-contact" id="cdAddContactBtn" title="Agregar contacto">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Agregar
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <div id="cdResFamilyContainer"></div>
            <?php if ($canEditFamily): ?>
            <div class="cd-res-edit-actions" id="cdResFamEditActions" style="display:none;padding:16px 0 0;gap:8px">
                <button class="cd-btn-submit cd-btn-save-edit" id="cdResFamSaveBtn"><?= t('btn_save_changes') ?></button>
                <button class="cd-btn-submit cd-btn-cancel-edit" id="cdResFamCancelBtn"><?= t('btn_cancel') ?></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Tab: Notificaciones ─────────────────── -->
    <div class="cd-ficha-tab-panel" data-ficha-panel="notif">
        <div class="cd-res-section-card">
            <div class="cd-res-section-header cd-notif-section-header" style="margin-top:0">
                <h3 style="margin:0">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?= t('config_notifications') ?>
                </h3>
            </div>
            <!-- Sub-tabs inside notification card -->
            <div class="cd-notif-subtabs-row">
            <div class="cd-res-subtabs cd-notif-tabs" id="cdNotifSubTabs" role="tablist" aria-label="<?= t('config_notifications') ?>">
                <span class="cd-res-subtabs-pill" aria-hidden="true"></span>
                <button class="cd-res-subtab cd-notif-tab active is-active" data-ntab="prefs">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?= t('notif_tab_prefs') ?>
                </button>
                <button class="cd-res-subtab cd-notif-tab" data-ntab="test">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <?= t('notif_tab_test') ?>
                </button>
                <button class="cd-res-subtab cd-notif-tab" data-ntab="log">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?= t('notif_tab_log') ?>
                </button>
            </div>
            </div>

            <!-- Tab: Preferencias -->
            <div class="cd-notif-tab-panel active" data-ntab-panel="prefs">

            <!-- Family member list (un card por contacto, expandible).
                 Reemplaza al dropdown anterior: cada miembro tiene su renglón
                 propio que se expande para mostrar el panel de switches.
                 Se conserva el <select id="cdNotifContactSelect"> oculto
                 como estado interno para no romper el JS que lo lee. -->
            <div class="cd-notif-members" id="cdNotifMembersList">
                <p class="cd-notif-empty" id="cdNotifMembersEmpty" style="display:none">
                    <?= t('notif_select_contact_ph') ?>
                </p>
            </div>
            <select id="cdNotifContactSelect" hidden aria-hidden="true" tabindex="-1">
                <option value=""></option>
            </select>

            <!-- Per-contact notification switches -->
            <div class="cd-notif-switches" id="cdNotifSwitchesPanel" style="display:none">
                <!-- ── Group 1: Critical alerts ───────────────────── -->
                <div class="cd-notif-switch-group cd-notif-group--critical">
                    <h4 class="cd-notif-switch-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <?= t('notif_group_critical') ?>
                    </h4>
                    <p class="cd-notif-group-hint"><?= t('notif_group_critical_hint') ?></p>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_emergency') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwEmergencia"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_vitals') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwSignos"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_incidents') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwIncidentes"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_falls') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwCaida"><span class="cd-switch-slider"></span></span>
                    </label>
                </div>

                <!-- ── Group 2: Daily care events ─────────────────── -->
                <div class="cd-notif-switch-group">
                    <h4 class="cd-notif-switch-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        <?= t('notif_group_care') ?>
                    </h4>
                    <p class="cd-notif-group-hint"><?= t('notif_group_care_hint') ?></p>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_medication') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwMedicacion"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_med_omitted') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwMedOmitida"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_nutrition') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwAlimentacion"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_hygiene') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwHigiene"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_elimination') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwEliminacion"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_sleep') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwSueno"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_mood') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwAnimo"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_mobility') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwMovilidad"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_therapy') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwTerapia"><span class="cd-switch-slider"></span></span>
                    </label>
                </div>

                <!-- ── Group 3: Reports & system ──────────────────── -->
                <div class="cd-notif-switch-group">
                    <h4 class="cd-notif-switch-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <?= t('notif_group_reports') ?>
                    </h4>
                    <p class="cd-notif-group-hint"><?= t('notif_group_reports_hint') ?></p>
                    <div class="cd-switch-row" style="flex-wrap:wrap">
                        <span class="cd-switch-label"><?= t('notif_sw_report') ?></span>
                        <label class="cd-switch" style="margin:0"><input type="checkbox" id="cdNotifSwReporte"><span class="cd-switch-slider"></span></label>
                    </div>
                    <div id="cdNotifReporteHorasWrap" style="display:none;padding:0 0 8px">
                        <div id="cdNotifReporteHorasList" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px"></div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <input type="time" class="cd-input" id="cdNotifReporteHoraInput" value="08:00" style="width:110px;padding:4px 8px;font-size:12px">
                            <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdNotifAddHora" style="font-size:0.75rem;padding:4px 10px">+ Agregar</button>
                        </div>
                        <label class="cd-switch-row" style="margin-top:8px">
                            <span class="cd-switch-label" style="font-size:0.8rem;color:var(--cd-text-secondary)">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <?= t('notif_sw_report_pdf') ?>
                            </span>
                            <span class="cd-switch"><input type="checkbox" id="cdNotifSwReportePdf"><span class="cd-switch-slider"></span></span>
                        </label>
                    </div>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_weekly') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwSemanal"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_doctor_notes') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwNotasMedico"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_visits') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwVisitas"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_stock') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwStock"><span class="cd-switch-slider"></span></span>
                    </label>
                </div>

                <!-- ── Group 4: Advanced preferences ──────────────── -->
                <div class="cd-notif-switch-group">
                    <h4 class="cd-notif-switch-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <?= t('notif_group_advanced') ?>
                    </h4>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_critical_only') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwCriticasOnly"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label"><?= t('notif_sw_quiet_hours') ?></span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwQuiet"><span class="cd-switch-slider"></span></span>
                    </label>
                    <div id="cdNotifQuietWrap" style="display:none;padding:4px 0 0">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:0.8rem">
                            <span style="color:var(--cd-text-secondary)"><?= t('notif_quiet_from') ?></span>
                            <input type="time" class="cd-input" id="cdNotifQuietStart" value="22:00" style="width:110px;padding:4px 8px;font-size:12px">
                            <span style="color:var(--cd-text-secondary)"><?= t('notif_quiet_to') ?></span>
                            <input type="time" class="cd-input" id="cdNotifQuietEnd" value="07:00" style="width:110px;padding:4px 8px;font-size:12px">
                        </div>
                        <p class="cd-notif-group-hint" style="margin-top:6px"><?= t('notif_quiet_hint') ?></p>
                    </div>
                </div>

                <!-- ── Channels ───────────────────────────────────── -->
                <div class="cd-notif-switch-group">
                    <h4 class="cd-notif-switch-title"><?= t('notif_channels_title') ?></h4>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="#25D366" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.654-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg>
                            WhatsApp
                        </span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwWa"><span class="cd-switch-slider"></span></span>
                    </label>
                    <label class="cd-switch-row">
                        <span class="cd-switch-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            Email
                        </span>
                        <span class="cd-switch"><input type="checkbox" id="cdNotifSwEmail"><span class="cd-switch-slider"></span></span>
                    </label>
                </div>
                <button class="cd-btn-submit" id="cdNotifSavePrefs" style="margin-top:8px;font-size:0.8125rem;padding:8px 16px"><?= t('notif_save_prefs') ?></button>
            </div>

            <!-- Alerts -->
            <div class="cd-notif-alerts-compact" id="cdNotifAlerts" style="margin-top:16px">
                <p class="cd-notif-empty">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?= t('notif_no_alerts') ?>
                </p>
            </div>
            </div><!-- /tab prefs -->

            <!-- Tab: Notificación de Prueba -->
            <div class="cd-notif-tab-panel" data-ntab-panel="test">
            <div class="cd-notif-send-section" id="cdNotifSendSection">
                <h4 class="cd-notif-send-title"><?= t('notif_test_title') ?></h4>
                <div class="cd-notif-type-select">
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="reporte_dia" checked>
                        <span><?= t('notif_test_report') ?></span>
                    </label>
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="stock_bajo">
                        <span><?= t('notif_sw_stock') ?></span>
                    </label>
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="stock_panales">
                        <span><?= t('notif_test_diapers') ?></span>
                    </label>
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="signos_vitales">
                        <span><?= t('notif_sw_vitals') ?></span>
                    </label>
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="incidente">
                        <span><?= t('notif_test_incident') ?></span>
                    </label>
                    <label class="cd-notif-type-option">
                        <input type="radio" name="cdNotifType" value="personalizado">
                        <span><?= t('notif_test_custom') ?></span>
                    </label>
                </div>
                <textarea class="cd-input cd-notif-custom-msg" id="cdNotifCustomMsg" placeholder="<?= t('notif_custom_msg_ph') ?>" rows="2" style="display:none"></textarea>
                <div class="cd-notif-recipients" id="cdNotifRecipients">
                    <!-- Populated dynamically from contacts -->
                </div>
                <div class="cd-notif-actions">
                    <button class="cd-btn-submit cd-notif-send-btn" id="cdNotifSendWa" title="<?= t('notif_test_wa') ?>">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.654-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg>
                        <?= t('notif_test_wa') ?>
                    </button>
                    <button class="cd-btn-submit cd-notif-send-btn" id="cdNotifSendEmail" title="<?= t('notif_test_email') ?>" style="background:var(--cd-accent)">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <?= t('notif_test_email') ?>
                    </button>
                </div>
            </div>
            </div><!-- /tab test -->

            <!-- Tab: Historial -->
            <div class="cd-notif-tab-panel" data-ntab-panel="log">
            <!-- Notification log -->
            <div class="cd-notif-log-section" id="cdNotifLogSection">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                    <h4 class="cd-notif-send-title" style="margin:0"><?= t('notif_log_title') ?></h4>
                    <button type="button" class="cd-btn-icon" id="cdNotifLogRefresh" title="Actualizar" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--cd-text-secondary)">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
                <div id="cdNotifLogList" class="cd-notif-log-list">
                    <p class="cd-notif-empty"><?= t('notif_log_empty') ?></p>
                </div>
            </div>
            </div><!-- /tab log -->
        </div>
    </div>
    <?php endif; ?>
</div>
</section>
