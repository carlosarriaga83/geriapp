<section id="viewFormSignosVitales" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><img src="assets/icons/pulse.png" alt="" class="cd-form-title-icon" aria-hidden="true"><?= t('form_vitals_title') ?></h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <div id="cdSignosAlert" class="cd-signos-alert" style="display:none"></div>
    <form id="formSignosVitales" data-cat="signos_vitales">
        <div class="cd-form-group cd-event-time-group">
            <label class="cd-form-label"><?= t('form_event_time') ?></label>
            <input type="time" class="cd-time-input cd-event-time" name="hora_evento">
        </div>
        <div class="cd-vitals-grid">
            <p style="font-size:0.75rem;color:var(--cd-text-muted);margin:0 0 8px;grid-column:1/-1"><?= t('form_vitals_hint') ?> <span class="cd-required">*</span> <a href="#cdVitalsRefs" class="cd-vitals-refs-asterisk" title="<?= t('vitals_refs_title') ?>" onclick="var d=document.getElementById('cdVitalsRefs');if(d){d.open=true;d.scrollIntoView({behavior:'smooth',block:'center'});}return false;">*</a></p>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_temp') ?></h4>
                <div class="cd-vital-value"><span id="vTemp">36.5</span><span class="cd-vital-unit">°C</span></div>
                <div><span class="cd-vital-status normal" id="vTempStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="temperatura" min="34" max="40" value="36.5" step="0.1">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="temperatura" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_resp') ?></h4>
                <div class="cd-vital-value"><span id="vFR">18</span><span class="cd-vital-unit">rpm</span></div>
                <div><span class="cd-vital-status normal" id="vFRStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="frecuencia_respiratoria" min="8" max="40" value="18">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="frecuencia_respiratoria" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_spo2') ?></h4>
                <div class="cd-vital-value"><span id="vSpO2">97</span><span class="cd-vital-unit">%</span></div>
                <div><span class="cd-vital-status normal" id="vSpO2Status">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="spo2" min="0" max="100" value="97">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="spo2" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_systolic') ?></h4>
                <div class="cd-vital-value"><span id="vPAS">120</span><span class="cd-vital-unit">mmHg</span></div>
                <div><span class="cd-vital-status normal" id="vPASStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="pa_sistolica" min="70" max="250" value="120">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="pa_sistolica" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_diastolic') ?></h4>
                <div class="cd-vital-value"><span id="vPAD">80</span><span class="cd-vital-unit">mmHg</span></div>
                <div><span class="cd-vital-status normal" id="vPADStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="pa_diastolica" min="40" max="110" value="80">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="pa_diastolica" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_heart') ?></h4>
                <div class="cd-vital-value"><span id="vFC">72</span><span class="cd-vital-unit">bpm</span></div>
                <div><span class="cd-vital-status normal" id="vFCStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="frecuencia_cardiaca" min="40" max="150" value="72">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="frecuencia_cardiaca" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_glucose') ?></h4>
                <div class="cd-vital-value"><span id="vGluc">100</span><span class="cd-vital-unit">mg/dL</span></div>
                <div><span class="cd-vital-status normal" id="vGlucStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="glucosa" min="40" max="500" value="100">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="glucosa" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
            <div class="cd-vital-card cd-vital-off">
                <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                <h4><?= t('vital_weight') ?></h4>
                <div class="cd-vital-value"><span id="vPeso">68</span><span class="cd-vital-unit">kg</span></div>
                <div><span class="cd-vital-status normal" id="vPesoStatus">Normal</span></div>
                <div class="cd-slider-wrap" style="margin-top:8px">
                    <input type="range" class="cd-slider" name="peso" min="25" max="200" value="68" step="0.1">
                </div>
                <div class="cd-vital-photo-wrap">
                    <input type="file" accept="image/*" capture="environment" class="cd-vital-photo-input" data-vital="peso" style="display:none">
                    <button type="button" class="cd-vital-photo-btn" data-perm-id="form_capture_vital_photo_btn" title="<?= t('vital_photo_hint') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
                    <div class="cd-vital-photo-preview"></div>
                </div>
            </div>
        </div>
        <div class="cd-form-group" style="margin-top:16px">
            <label class="cd-form-label"><?= t('form_observations') ?></label>
            <textarea class="cd-textarea" name="observaciones" placeholder="<?= t('form_notes_placeholder') ?>"></textarea>
        </div>
        <details class="cd-vitals-refs" id="cdVitalsRefs">
            <summary><?= t('vitals_refs_title') ?></summary>
            <div class="cd-vitals-refs-body">
                <p class="cd-vitals-refs-intro"><?= t('vitals_refs_intro') ?></p>
                <ul class="cd-vitals-refs-list">
                    <li class="cd-vref-framework">
                        <span class="cd-vref-name"><?= t('vitals_refs_framework') ?></span>
                        <span class="cd-vref-src">
                            <strong>NOM-031-SSA3-2012</strong> &mdash; Asistencia social. Prestaci&oacute;n de servicios de asistencia social a adultos y adultos mayores en situaci&oacute;n de riesgo y vulnerabilidad.<br>
                            <strong>NOM-004-SSA3-2012</strong> &mdash; Del expediente cl&iacute;nico (registro obligatorio de signos vitales en cada nota de evoluci&oacute;n).<br>
                            <a href="https://www.gob.mx/salud/documentos/normas-oficiales-mexicanas-9705" target="_blank" rel="noopener noreferrer">Secretar&iacute;a de Salud &mdash; &Iacute;ndice oficial de NOM</a>
                            &middot;
                            <a href="https://www.dof.gob.mx" target="_blank" rel="noopener noreferrer">DOF (Diario Oficial de la Federaci&oacute;n)</a>
                        </span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_temp') ?></span>
                        <span class="cd-vref-range">36.0&ndash;37.5 &deg;C</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-004-SSA3-2012</strong> (registro de temperatura en expediente cl&iacute;nico) &middot; GPC IMSS-CENETEC <em>"Diagn&oacute;stico y manejo de la fiebre sin signos de focalizaci&oacute;n"</em>. <a href="http://www.cenetec-difusion.com/CMGPC/" target="_blank" rel="noopener noreferrer">cenetec-difusion.com/CMGPC</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_resp') ?></span>
                        <span class="cd-vref-range">12&ndash;20 rpm</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-004-SSA3-2012</strong> &middot; GPC IMSS-CENETEC <em>"Diagn&oacute;stico y tratamiento de la neumon&iacute;a adquirida en la comunidad en el adulto"</em>. <a href="http://www.cenetec-difusion.com/CMGPC/" target="_blank" rel="noopener noreferrer">cenetec-difusion.com/CMGPC</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_heart') ?></span>
                        <span class="cd-vref-range">60&ndash;100 bpm</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-030-SSA2-2009</strong> (Hipertensi&oacute;n arterial &mdash; toma de pulso y FC en consulta) &middot; GPC IMSS-CENETEC. <a href="http://www.cenetec-difusion.com/CMGPC/" target="_blank" rel="noopener noreferrer">cenetec-difusion.com/CMGPC</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_systolic') ?> / <?= t('vital_diastolic') ?></span>
                        <span class="cd-vref-range">&lt;140 / &lt;90 mmHg</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-030-SSA2-2009</strong> &mdash; "Para la prevenci&oacute;n, detecci&oacute;n, diagn&oacute;stico, tratamiento y control de la hipertensi&oacute;n arterial sist&eacute;mica" (clasificaci&oacute;n &oacute;ptima/normal/HTA). <a href="https://www.gob.mx/salud/documentos/normas-oficiales-mexicanas-9705" target="_blank" rel="noopener noreferrer">gob.mx/salud</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_spo2') ?></span>
                        <span class="cd-vref-range">95&ndash;100 %</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: GPC IMSS-CENETEC <em>"Diagn&oacute;stico y tratamiento de EPOC en el adulto"</em> e <em>"Insuficiencia respiratoria aguda"</em> (umbral de hipoxemia &lt;90&nbsp;%, vigilancia &ge;95&nbsp;%). <a href="http://www.cenetec-difusion.com/CMGPC/" target="_blank" rel="noopener noreferrer">cenetec-difusion.com/CMGPC</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_glucose') ?></span>
                        <span class="cd-vref-range">70&ndash;140 mg/dL</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-015-SSA2-2010</strong> &mdash; "Para la prevenci&oacute;n, tratamiento y control de la diabetes mellitus" (glucosa en ayuno 70&ndash;100 mg/dL; postprandial &lt;140 mg/dL). <a href="https://www.gob.mx/salud/documentos/normas-oficiales-mexicanas-9705" target="_blank" rel="noopener noreferrer">gob.mx/salud</a></span>
                    </li>
                    <li>
                        <span class="cd-vref-name"><?= t('vital_weight') ?> / IMC</span>
                        <span class="cd-vref-range">IMC 18.5&ndash;24.9 kg/m&sup2;</span>
                        <span class="cd-vref-src"><?= t('vitals_refs_source') ?>: <strong>NOM-008-SSA3-2017</strong> &mdash; "Para el tratamiento integral del sobrepeso y la obesidad"; <strong>NOM-043-SSA2-2012</strong> &mdash; "Promoci&oacute;n y educaci&oacute;n para la salud en materia alimentaria". <a href="https://www.gob.mx/salud/documentos/normas-oficiales-mexicanas-9705" target="_blank" rel="noopener noreferrer">gob.mx/salud</a></span>
                    </li>
                </ul>
                <p class="cd-vitals-refs-disclaimer"><?= t('vitals_refs_disclaimer') ?></p>
            </div>
        </details>
        <div class="cd-form-group cd-insumo-picker">
            <button type="button" class="cd-btn-add-med cd-insumo-toggle<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_insumo_toggle_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede registrar insumos."' ?>><?= t('form_insumo_toggle') ?></button>
            <div class="cd-insumo-search-wrap" style="display:none">
                <input type="text" class="cd-input cd-insumo-search" placeholder="<?= t('form_insumo_search') ?>" autocomplete="off">
                <div class="cd-insumo-search-results"></div>
            </div>
            <div class="cd-insumo-list"></div>
        </div>
        <button type="submit" class="cd-btn-submit<?= $canEdit ? '' : ' cd-role-locked' ?>" data-perm-id="form_save_signos_vitales_btn"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Sin permiso para guardar" data-lock-msg="Solo el personal autorizado puede guardar registros de cuidado."' ?>><?= t('btn_save_record') ?></button>
    </form>
</div>
</section>
