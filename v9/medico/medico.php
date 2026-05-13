<!-- ══════════════════════════════════════════════════════════════════════════
     VIEW: Expediente Médico (NOM-004-SSA3-2012 / NOM-024-SSA3-2012)
     Included from cuidados.php — all session vars ($userRole, etc.) are available.
     ══════════════════════════════════════════════════════════════════════════ -->
<section id="viewExpediente" class="cd-view">
<div class="cd-content-wrap med-wrap">

    <!-- ── Header ────────────────────────────────────────────────────── -->
    <div class="med-header">
        <h2 class="med-title"><?= t('med_title') ?></h2>
        <span class="med-badge-nom">NOM-004</span>
        <span class="med-autosave-badge" id="medAutosaveBadge" style="display:none">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="medAutosaveText"></span>
        </span>
    </div>

    <!-- ── Tabs (sticky) ─────────────────────────────────────────────── -->
    <div class="med-tabs-sticky">
        <div class="med-tabs" id="medTabs">
            <button class="med-tab active" data-med-tab="hc">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?= t('med_tab_hc') ?>
            </button>
            <button class="med-tab" data-med-tab="notas">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <?= t('med_tab_notas') ?>
            </button>
            <?php if (in_array($userRole, ['admin','superadmin','medico','enfermero'])): ?>
            <button class="med-tab" data-med-tab="enfermeria">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <?= t('med_tab_enfermeria') ?>
            </button>
            <?php endif; ?>
            <button class="med-tab" data-med-tab="estudios">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <?= t('med_tab_estudios') ?>
            </button>
            <button class="med-tab" data-med-tab="consentimientos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= t('med_tab_consentimientos') ?>
            </button>
            <?php if (in_array($userRole, ['admin','superadmin'])): ?>
            <button class="med-tab" data-med-tab="auditoria">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?= t('med_tab_auditoria') ?>
            </button>
            <?php endif; ?>
        </div>
        <!-- Progress bar -->
        <div class="med-progress-wrap" id="medProgressWrap" style="display:none">
            <div class="med-progress-bar"><div class="med-progress-fill" id="medProgressFill"></div></div>
            <div class="med-progress-label" id="medProgressLabel"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Historia Clínica
         ═══════════════════════════════════════════════════════════════ -->
    <div class="med-panel active" id="medPanelHC" data-med-panel="hc">
        <div class="med-panel-toolbar">
            <?php if (in_array($userRole, ['admin','superadmin','medico'])): ?>
            <button class="med-btn med-btn-sm med-btn-primary" id="medHcEditBtn">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <?= t('med_btn_edit') ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- HC View mode -->
        <div id="medHcView" class="med-hc-view">
            <div class="med-empty-state" id="medHcEmpty">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <p><?= t('med_hc_empty') ?></p>
            </div>
            <div id="medHcContent" class="med-hc-content" style="display:none;"></div>
        </div>

        <!-- HC Edit form (hidden by default) -->
        <div id="medHcForm" class="med-hc-form" style="display:none;">
            <form id="medHcFormEl" autocomplete="off">

                <!-- Ficha de Identificación -->
                <fieldset class="med-fieldset">
                    <legend><?= t('med_hc_ficha') ?></legend>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_grupo_etnico') ?></label><input type="text" class="cd-input" name="ficha_grupo_etnico"></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_religion') ?></label><input type="text" class="cd-input" name="ficha_religion"></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_escolaridad') ?></label><input type="text" class="cd-input" name="ficha_escolaridad"></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_ocupacion') ?></label><input type="text" class="cd-input" name="ficha_ocupacion_previa"></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_lugar_nacimiento') ?></label><input type="text" class="cd-input" name="ficha_lugar_nacimiento"></div>
                    </div>
                </fieldset>

                <!-- Antecedentes Heredo-Familiares (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_ant_heredo') ?></legend>
                    <div class="med-check-grid">
                        <label class="med-check"><input type="checkbox" name="ah_diabetes"> <?= t('med_hc_diabetes') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_hipertension"> <?= t('med_hc_hipertension') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_cancer"> <?= t('med_hc_cancer') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_cardiopatias"> <?= t('med_hc_cardiopatias') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_enf_mentales"> <?= t('med_hc_enf_mentales') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_enf_renales"> <?= t('med_hc_enf_renales') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_enf_hepaticas"> <?= t('med_hc_enf_hepaticas') ?></label>
                        <label class="med-check"><input type="checkbox" name="ah_alergias_fam"> <?= t('med_hc_alergias_fam') ?></label>
                    </div>
                    <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_otros') ?></label><textarea class="cd-textarea" name="ah_otros" rows="2" data-suggest="heredofam"></textarea></div>
                </fieldset>

                <!-- Antecedentes Personales (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_ant_personales') ?></legend>
                    <div class="cd-form-group"><label class="cd-form-label med-required"><?= t('med_hc_ant_patologicos') ?></label><textarea class="cd-textarea" name="antecedentes_patologicos" rows="3" data-suggest="ant_pat"></textarea></div>
                    <div class="cd-form-group"><label class="cd-form-label med-required"><?= t('med_hc_ant_no_patologicos') ?></label><textarea class="cd-textarea" name="antecedentes_no_patologicos" rows="3" data-suggest="ant_nopat"></textarea></div>
                    <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_alergias') ?></label><textarea class="cd-textarea" name="alergias_detalle" rows="2" data-suggest="alergias"></textarea></div>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_grupo_sang') ?></label>
                            <select class="cd-select" name="grupo_sanguineo">
                                <option value="">—</option>
                                <option value="A+">A+</option><option value="A-">A-</option>
                                <option value="B+">B+</option><option value="B-">B-</option>
                                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                                <option value="O+">O+</option><option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- Padecimiento Actual (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_padecimiento') ?></legend>
                    <div class="cd-form-group"><textarea class="cd-textarea" name="padecimiento_actual" rows="4" data-suggest="padecimiento"></textarea></div>
                </fieldset>

                <!-- Interrogatorio por Aparatos y Sistemas (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_interrogatorio') ?></legend>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_cardio') ?></label><textarea class="cd-textarea" name="ia_cardiovascular" rows="2" data-suggest="ia_cardio"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_resp') ?></label><textarea class="cd-textarea" name="ia_respiratorio" rows="2" data-suggest="ia_resp"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_digestivo') ?></label><textarea class="cd-textarea" name="ia_digestivo" rows="2" data-suggest="ia_dig"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_urinario') ?></label><textarea class="cd-textarea" name="ia_urinario" rows="2" data-suggest="ia_uri"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_musculo') ?></label><textarea class="cd-textarea" name="ia_musculoesqueletico" rows="2" data-suggest="ia_musc"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_neuro') ?></label><textarea class="cd-textarea" name="ia_neurologico" rows="2" data-suggest="ia_neuro"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_endocrino') ?></label><textarea class="cd-textarea" name="ia_endocrino" rows="2" data-suggest="ia_endo"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_piel') ?></label><textarea class="cd-textarea" name="ia_piel_tegumentos" rows="2" data-suggest="ia_piel"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_psiquiatrico') ?></label><textarea class="cd-textarea" name="ia_psiquiatrico" rows="2" data-suggest="ia_psiq"></textarea></div>
                    </div>
                </fieldset>

                <!-- Exploración Física (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_exploracion') ?></legend>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_habitus') ?></label><textarea class="cd-textarea" name="ef_habitus" rows="2" data-suggest="ef_habitus"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_cabeza') ?></label><textarea class="cd-textarea" name="ef_cabeza" rows="2" data-suggest="ef_cabeza"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_cuello') ?></label><textarea class="cd-textarea" name="ef_cuello" rows="2" data-suggest="ef_cuello"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_torax') ?></label><textarea class="cd-textarea" name="ef_torax" rows="2" data-suggest="ef_torax"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_abdomen') ?></label><textarea class="cd-textarea" name="ef_abdomen" rows="2" data-suggest="ef_abdomen"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_extremidades') ?></label><textarea class="cd-textarea" name="ef_extremidades" rows="2" data-suggest="ef_extrem"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_neuro_exp') ?></label><textarea class="cd-textarea" name="ef_neurologico" rows="2" data-suggest="ef_neuro"></textarea></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_piel_exp') ?></label><textarea class="cd-textarea" name="ef_piel" rows="2" data-suggest="ef_piel"></textarea></div>
                    </div>
                </fieldset>

                <!-- Signos Vitales de Ingreso (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_signos') ?></legend>
                    <div class="med-vitals-grid">
                        <div class="med-vital-card">
                            <label>T/A</label>
                            <input type="text" name="sv_ta" placeholder="120/80">
                            <div class="med-vital-unit">mmHg</div>
                        </div>
                        <div class="med-vital-card">
                            <label>FC</label>
                            <input type="number" name="sv_fc" min="0" max="300" placeholder="72">
                            <div class="med-vital-unit">lpm</div>
                        </div>
                        <div class="med-vital-card">
                            <label>FR</label>
                            <input type="number" name="sv_fr" min="0" max="100" placeholder="18">
                            <div class="med-vital-unit">rpm</div>
                        </div>
                        <div class="med-vital-card">
                            <label>Temp</label>
                            <input type="number" name="sv_temp" step="0.1" min="30" max="45" placeholder="36.5">
                            <div class="med-vital-unit">°C</div>
                        </div>
                        <div class="med-vital-card">
                            <label>SpO₂</label>
                            <input type="number" name="sv_spo2" min="0" max="100" placeholder="97">
                            <div class="med-vital-unit">%</div>
                        </div>
                        <div class="med-vital-card">
                            <label><?= t('med_hc_peso') ?></label>
                            <input type="number" name="sv_peso" step="0.1" placeholder="70">
                            <div class="med-vital-unit">kg</div>
                        </div>
                        <div class="med-vital-card">
                            <label><?= t('med_hc_talla') ?></label>
                            <input type="number" name="sv_talla" step="0.1" placeholder="165">
                            <div class="med-vital-unit">cm</div>
                        </div>
                    </div>
                </fieldset>

                <!-- Diagnósticos CIE-10 (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_diagnosticos') ?></legend>
                    <div class="med-cie-search-wrap">
                        <input type="text" class="cd-input" id="medCieSearch" placeholder="<?= t('med_cie_search_ph') ?>" autocomplete="off">
                        <div class="med-cie-spinner" id="medCieSpinner"></div>
                        <div class="med-cie-results" id="medCieResults"></div>
                    </div>
                    <div id="medDiagList" class="med-diag-list"></div>
                </fieldset>

                <!-- Pronóstico (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_pronostico') ?></legend>
                    <div class="cd-form-group"><textarea class="cd-textarea" name="pronostico" rows="2" data-suggest="pronostico"></textarea></div>
                </fieldset>
                <!-- Indicación Terapéutica (NOM §6.1) -->
                <fieldset class="med-fieldset">
                    <legend class="med-required"><?= t('med_hc_indicacion') ?></legend>
                    <div class="cd-form-group"><textarea class="cd-textarea" name="indicacion_terapeutica" rows="3" data-suggest="indicacion"></textarea></div>
                </fieldset>

                <!-- Geriatric extras -->
                <fieldset class="med-fieldset">
                    <legend><?= t('med_hc_extras_geri') ?></legend>
                    <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_plan_cuidados') ?></label><textarea class="cd-textarea" name="plan_cuidados" rows="2" data-suggest="plan_cuidados"></textarea></div>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_dieta') ?></label><input type="text" class="cd-input" name="dieta"></div>
                        <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_movilidad') ?></label><input type="text" class="cd-input" name="movilidad"></div>
                    </div>
                </fieldset>

                <!-- Valoración Geriátrica Integral -->
                <fieldset class="med-fieldset">
                    <legend><?= t('med_hc_valoracion_geri') ?></legend>
                    <div class="med-form-grid">
                        <div class="cd-form-group"><label class="cd-form-label">Barthel</label><input type="number" class="cd-input" name="vg_barthel" min="0" max="100"></div>
                        <div class="cd-form-group"><label class="cd-form-label">Lawton-Brody</label><input type="number" class="cd-input" name="vg_lawton" min="0" max="8"></div>
                        <div class="cd-form-group"><label class="cd-form-label">Minimental (MMSE)</label><input type="number" class="cd-input" name="vg_minimental" min="0" max="30"></div>
                        <div class="cd-form-group"><label class="cd-form-label">GDS (Yesavage)</label><input type="number" class="cd-input" name="vg_yesavage" min="0" max="15"></div>
                        <div class="cd-form-group"><label class="cd-form-label">MNA</label><input type="number" class="cd-input" name="vg_mna" step="0.5"></div>
                        <div class="cd-form-group"><label class="cd-form-label">Tinetti</label><input type="number" class="cd-input" name="vg_tinetti" min="0" max="28"></div>
                    </div>
                    <div class="cd-form-group"><label class="cd-form-label"><?= t('med_hc_notas_vg') ?></label><textarea class="cd-textarea" name="vg_notas" rows="2"></textarea></div>
                </fieldset>

                <div class="med-form-actions">
                    <button type="button" class="med-btn med-btn-secondary" id="medHcCancelBtn"><?= t('btn_cancel') ?></button>
                    <button type="submit" class="med-btn med-btn-primary" id="medHcSaveBtn"><?= t('btn_save') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Notas de Evolución
         ═══════════════════════════════════════════════════════════════ -->
    <div class="med-panel" id="medPanelNotas" data-med-panel="notas">
        <div class="med-panel-toolbar">
            <select class="cd-select cd-select-sm" id="medNotasTipoFilter">
                <option value=""><?= t('med_notas_all') ?></option>
                <option value="evolucion"><?= t('med_tipo_evolucion') ?></option>
                <option value="interconsulta"><?= t('med_tipo_interconsulta') ?></option>
                <option value="referencia"><?= t('med_tipo_referencia') ?></option>
                <option value="ingreso"><?= t('med_tipo_ingreso') ?></option>
                <option value="egreso"><?= t('med_tipo_egreso') ?></option>
            </select>
            <?php if (in_array($userRole, ['admin','superadmin','medico'])): ?>
            <button class="med-btn med-btn-sm med-btn-primary" id="medNotaNewBtn">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('med_btn_new_nota') ?>
            </button>
            <?php endif; ?>
        </div>
        <div id="medNotasList" class="med-timeline"></div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Enfermería
         ═══════════════════════════════════════════════════════════════ -->
    <?php if (in_array($userRole, ['admin','superadmin','medico','enfermero'])): ?>
    <div class="med-panel" id="medPanelEnfermeria" data-med-panel="enfermeria">
        <div class="med-panel-toolbar">
            <?php if (in_array($userRole, ['admin','superadmin','medico','enfermero'])): ?>
            <button class="med-btn med-btn-sm med-btn-primary" id="medEnfNewBtn">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('med_btn_new_enfermeria') ?>
            </button>
            <?php endif; ?>
        </div>
        <div id="medEnfList" class="med-timeline"></div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Estudios
         ═══════════════════════════════════════════════════════════════ -->
    <div class="med-panel" id="medPanelEstudios" data-med-panel="estudios">
        <div class="med-panel-toolbar">
            <?php if (in_array($userRole, ['admin','superadmin','medico'])): ?>
            <button class="med-btn med-btn-sm med-btn-primary" id="medEstNewBtn">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('med_btn_new_estudio') ?>
            </button>
            <?php endif; ?>
        </div>
        <div id="medEstList" class="med-card-grid"></div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Consentimientos
         ═══════════════════════════════════════════════════════════════ -->
    <div class="med-panel" id="medPanelConsentimientos" data-med-panel="consentimientos">
        <div class="med-panel-toolbar">
            <?php if (in_array($userRole, ['admin','superadmin','medico'])): ?>
            <button class="med-btn med-btn-sm med-btn-primary" id="medConsNewBtn">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= t('med_btn_new_consent') ?>
            </button>
            <?php endif; ?>
        </div>
        <div id="medConsList" class="med-card-grid"></div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TAB: Auditoría
         ═══════════════════════════════════════════════════════════════ -->
    <?php if (in_array($userRole, ['admin','superadmin'])): ?>
    <div class="med-panel" id="medPanelAuditoria" data-med-panel="auditoria">
        <div id="medAuditList" class="med-audit-list"></div>
    </div>
    <?php endif; ?>

</div>
</section>
