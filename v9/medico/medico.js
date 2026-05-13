/* ═══════════════════════════════════════════════════════════════════
 * GeriApp — Expediente Médico JS Module
 * NOM-004-SSA3-2012 / NOM-024-SSA3-2012
 *
 * Depends on globals from cuidados.php:
 *   $, $$, BASE, _residenteId, CAN_EDIT, IS_ADMIN, CURRENT_USER_ID,
 *   showToast, openSidebar, closeSidebar, t
 * ═══════════════════════════════════════════════════════════════════ */
(function () {
'use strict';

const MED_API = BASE + '/medico/api.php';

// ── State ────────────────────────────────────────────────────────
let _hc = null;          // current HC object
let _hcDiags = [];       // diagnostics array [{codigo,descripcion,tipo}]
let _notasCache = [];
let _enfCache = [];
let _activeTab = 'hc';
let _cieTimeout = null;
let _autoSaveTimeout = null;
let _autoSaving = false;

// ── Quick-suggest definitions ────────────────────────────────────
const QUICK_SUGGESTS = {
    heredofam:    ['DM en padre','HTA en madre','Cáncer colon','Cardiopatía','Alzheimer','No relevantes'],
    ant_pat:      ['DM2 dx hace 10 años','HTA en tratamiento','EPOC','Hipotiroidismo','IRC','Sin antecedentes patológicos'],
    ant_nopat:    ['Tabaquismo negado','Alcoholismo social','Sedentario','Vivienda con servicios básicos','Vacunación completa','Alimentación regular'],
    alergias:     ['Penicilina','Sulfas','AINEs','Ninguna conocida','Alergia a mariscos','Látex'],
    padecimiento: ['Deterioro cognitivo progresivo','Caídas frecuentes','Inmovilidad parcial','Incontinencia urinaria','Pérdida de peso involuntaria','Dolor crónico'],
    ia_cardio:    ['Sin disnea','Palpitaciones ocasionales','Dolor precordial negado','Edema de MsIs','Sin síncope','Ortopnea negada'],
    ia_resp:      ['Sin tos','Disnea de medianos esfuerzos','Sin expectoración','Sin hemoptisis','Sibilancias negadas','Sin datos'],
    ia_dig:       ['Apetito conservado','Estreñimiento crónico','Náusea negada','Reflujo ocasional','Sin disfagia','Sin datos'],
    ia_uri:       ['Incontinencia de urgencia','Polaquiuria','Sin disuria','Sin hematuria','Nicturia 2x','Sin datos'],
    ia_musc:      ['Gonartrosis bilateral','Lumbalgia crónica','Sin fracturas recientes','Rigidez matutina','Artralgias','Sin datos'],
    ia_neuro:     ['Cefalea negada','Mareo ocasional','Temblor fino','Parestesias en MsIs','Sin convulsiones','Sin datos'],
    ia_endo:      ['Polidipsia negada','Poliuria negada','Intolerancia al frío','Sin bocio','Sin datos'],
    ia_piel:      ['Piel seca','Prurito generalizado','Sin lesiones','Equimosis fáciles','Sin datos'],
    ia_psiq:      ['Insomnio','Ansiedad leve','Ánimo conservado','Irritabilidad','Sin ideación suicida','Sin datos'],
    ef_habitus:   ['Consciente, orientado en 3 esferas','Cooperador','Edad aparente acorde','Pálido','Deshidratado','Íntegro, bien conformado'],
    ef_cabeza:    ['Normocéfalo','Pupilas isocóricas, reactivas','Sin exoftalmos','Mucosas orales hidratadas','Dentadura incompleta','Sin datos'],
    ef_cuello:    ['Sin adenopatías','Sin ingurgitación yugular','Tiroides normal','Pulsos carotídeos presentes','Sin rigidez','Sin datos'],
    ef_torax:     ['Simétrico','MV bilateral','Sin estertores','Ruidos cardíacos rítmicos','Sin soplos','Sin datos'],
    ef_abdomen:   ['Blando, depresible','Sin hepatomegalia','Peristalsis presente','Sin masas','Sin dolor a palpación','Sin datos'],
    ef_extrem:    ['Sin edema','Pulsos periféricos presentes','Movilidad conservada','Fuerza 4/5','Llenado capilar <2s','Sin datos'],
    ef_neuro:     ['Glasgow 15','Pares craneales íntegros','ROT normales','Babinski negativo','Marcha con apoyo','Sin datos'],
    ef_piel:      ['Turgencia disminuida','Sin úlceras por presión','Sin lesiones dérmicas','Hidratada','Palidez de tegumentos','Sin datos'],
    pronostico:   ['Reservado','Bueno para función','Malo para vida','Crónico controlado','Estable','En deterioro'],
    indicacion:   ['Continuar tratamiento actual','Ajustar dosis de antihipertensivo','Referencia a especialista','Rehabilitación física','Soporte nutricional','Cuidados paliativos'],
    plan_cuidados:['Vigilancia de signos vitales c/8h','Prevención de caídas','Programa de movilidad','Cuidado de piel','Estimulación cognitiva','Soporte emocional'],
};

// ── Refs ─────────────────────────────────────────────────────────
const tabs     = () => $$('.med-tab');
const panels   = () => $$('.med-panel');

// ═════════════════════════════════════════════════════════════════
// PUBLIC: Called by showView when viewExpediente becomes active
// ═════════════════════════════════════════════════════════════════
window._medLoadExpediente = function () {
    if (!_residenteId) return;
    initQuickSuggests();
    switchTab(_activeTab);
};

// Called when resident changes — invalidate caches
window._medOnResidentChange = function () {
    _hc = null; _hcDiags = []; _notasCache = []; _enfCache = [];
    const hcView = $('#medHcContent');
    if (hcView) hcView.style.display = 'none';
    const hcEmpty = $('#medHcEmpty');
    if (hcEmpty) hcEmpty.style.display = '';
    updateProgress();
};

// ═════════════════════════════════════════════════════════════════
// TABS
// ═════════════════════════════════════════════════════════════════
function switchTab(tabId) {
    _activeTab = tabId;
    tabs().forEach(b => b.classList.toggle('active', b.dataset.medTab === tabId));
    panels().forEach(p => p.classList.toggle('active', p.dataset.medPanel === tabId));

    if (tabId === 'hc')              loadHC();
    else if (tabId === 'notas')      loadNotas();
    else if (tabId === 'enfermeria') loadEnfermeria();
    else if (tabId === 'estudios')   loadEstudios();
    else if (tabId === 'consentimientos') loadConsentimientos();
    else if (tabId === 'auditoria')  loadAuditoria();
}

document.addEventListener('click', e => {
    const tab = e.target.closest('.med-tab');
    if (tab && tab.dataset.medTab) switchTab(tab.dataset.medTab);
});

// ═════════════════════════════════════════════════════════════════
// PROGRESS BAR
// ═════════════════════════════════════════════════════════════════
function updateProgress() {
    const wrap  = $('#medProgressWrap');
    const fill  = $('#medProgressFill');
    const label = $('#medProgressLabel');
    if (!wrap || !fill || !label) return;

    // Only show in HC tab when editing or viewing
    if (_activeTab !== 'hc') { wrap.style.display = 'none'; return; }

    if (!_hc) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';

    // NOM-required sections for HC
    const checks = [
        !!(_hc.antecedentes_heredo && Object.values(_hc.antecedentes_heredo).some(v => v)),
        !!_hc.antecedentes_patologicos,
        !!_hc.antecedentes_no_patologicos,
        !!_hc.padecimiento_actual,
        !!(_hc.interrogatorio_aparatos && Object.values(_hc.interrogatorio_aparatos).some(v => v)),
        !!(_hc.exploracion_fisica && Object.values(_hc.exploracion_fisica).some(v => v)),
        !!(_hc.signos_vitales_ingreso && Object.values(_hc.signos_vitales_ingreso).some(v => v)),
        !!(_hc.diagnosticos && _hc.diagnosticos.length),
        !!_hc.pronostico,
        !!_hc.indicacion_terapeutica,
    ];
    const filled = checks.filter(Boolean).length;
    const total  = checks.length;
    const pct    = Math.round((filled / total) * 100);

    fill.style.width = pct + '%';
    label.textContent = `${pct}% — ${filled}/${total} secciones NOM`;
}

// ═════════════════════════════════════════════════════════════════
// AUTO-SAVE INDICATOR
// ═════════════════════════════════════════════════════════════════
function showAutoSaveStatus(status) {
    const badge = $('#medAutosaveBadge');
    const text  = $('#medAutosaveText');
    if (!badge || !text) return;
    badge.style.display = '';
    badge.className = 'med-autosave-badge ' + status;
    text.textContent = status === 'saving' ? t('med_autosaving') || 'Guardando…' : t('med_autosaved') || 'Guardado';
    if (status === 'saved') {
        setTimeout(() => { badge.style.display = 'none'; }, 2500);
    }
}

// ═════════════════════════════════════════════════════════════════
// QUICK SUGGESTS
// ═════════════════════════════════════════════════════════════════
function initQuickSuggests() {
    const form = $('#medHcFormEl');
    if (!form) return;
    // Remove previously injected chips
    form.querySelectorAll('.med-quick-chips').forEach(el => el.remove());

    form.querySelectorAll('textarea[data-suggest]').forEach(ta => {
        const key = ta.dataset.suggest;
        const chips = QUICK_SUGGESTS[key];
        if (!chips || !chips.length) return;
        const wrap = document.createElement('div');
        wrap.className = 'med-quick-chips';
        chips.forEach(text => {
            const chip = document.createElement('span');
            chip.className = 'med-quick-chip';
            chip.textContent = text;
            chip.addEventListener('click', () => {
                const cur = ta.value.trim();
                ta.value = cur ? cur + '. ' + text : text;
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            });
            wrap.appendChild(chip);
        });
        ta.parentElement.appendChild(wrap);
    });
}

// ═════════════════════════════════════════════════════════════════
// HISTORIA CLÍNICA
// ═════════════════════════════════════════════════════════════════
async function loadHC() {
    if (!_residenteId) return;
    try {
        const r = await medFetch(`action=hc&residente_id=${_residenteId}`);
        _hc = r.data;
        _hcDiags = (_hc && _hc.diagnosticos) ? (Array.isArray(_hc.diagnosticos) ? _hc.diagnosticos : []) : [];
        renderHCView();
        updateProgress();
    } catch (e) { showToast(t('med_error_load'), 'error'); }
}

function renderHCView() {
    const empty   = $('#medHcEmpty');
    const content = $('#medHcContent');
    const form    = $('#medHcForm');
    if (form) form.style.display = 'none';

    if (!_hc || !hasHCData(_hc)) {
        if (empty)   empty.style.display = '';
        if (content) content.style.display = 'none';
        return;
    }
    if (empty)   empty.style.display = 'none';
    if (content) content.style.display = '';

    const fi = _hc.ficha_identificacion || {};
    const ah = _hc.antecedentes_heredo || {};
    const ia = _hc.interrogatorio_aparatos || {};
    const ef = _hc.exploracion_fisica || {};
    const sv = _hc.signos_vitales_ingreso || {};
    const vg = _hc.valoracion_geriatrica || {};

    let html = '';

    // Ficha
    html += section(t('med_hc_ficha'), `
        ${row(t('med_hc_grupo_etnico'), fi.grupo_etnico)}
        ${row(t('med_hc_religion'), fi.religion)}
        ${row(t('med_hc_escolaridad'), fi.escolaridad)}
        ${row(t('med_hc_ocupacion'), fi.ocupacion_previa)}
        ${row(t('med_hc_lugar_nacimiento'), fi.lugar_nacimiento)}
    `);

    // Antecedentes Heredo-Familiares
    const ahChecks = ['diabetes','hipertension','cancer','cardiopatias','enf_mentales','enf_renales','enf_hepaticas','alergias_fam'];
    let ahHtml = '<div class="med-hc-checks">';
    ahChecks.forEach(k => {
        const on = !!ah[k];
        ahHtml += `<span class="med-hc-check">
            <svg class="med-hc-check-icon ${on?'yes':'no'}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            ${on ? '<polyline points="20 6 9 17 4 12"/>' : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'}
            </svg> ${t('med_hc_'+k)}</span>`;
    });
    ahHtml += '</div>';
    if (ah.otros) ahHtml += `<div class="med-hc-row" style="margin-top:8px">${esc(ah.otros)}</div>`;
    html += section(t('med_hc_ant_heredo'), ahHtml);

    // Antecedentes Personales
    html += section(t('med_hc_ant_personales'), `
        ${row(t('med_hc_ant_patologicos'), _hc.antecedentes_patologicos)}
        ${row(t('med_hc_ant_no_patologicos'), _hc.antecedentes_no_patologicos)}
        ${row(t('med_hc_alergias'), _hc.alergias_detalle)}
        ${row(t('med_hc_grupo_sang'), _hc.grupo_sanguineo)}
    `);

    // Padecimiento Actual
    if (_hc.padecimiento_actual) html += section(t('med_hc_padecimiento'), `<div class="med-hc-value">${esc(_hc.padecimiento_actual)}</div>`);

    // Interrogatorio
    const iaKeys = ['cardiovascular','respiratorio','digestivo','urinario','musculoesqueletico','neurologico','endocrino','piel_tegumentos','psiquiatrico'];
    let iaHtml = '';
    iaKeys.forEach(k => { if (ia[k]) iaHtml += row(t('med_hc_' + k.replace('piel_tegumentos','piel').replace('musculoesqueletico','musculo')), ia[k]); });
    if (iaHtml) html += section(t('med_hc_interrogatorio'), iaHtml);

    // Exploración Física
    const efKeys = ['habitus','cabeza','cuello','torax','abdomen','extremidades','neurologico','piel'];
    let efHtml = '';
    efKeys.forEach(k => { if (ef[k]) efHtml += row(t('med_hc_' + k + (k==='neurologico'||k==='piel'?'_exp':'')), ef[k]); });
    if (efHtml) html += section(t('med_hc_exploracion'), efHtml);

    // Signos Vitales
    if (Object.keys(sv).some(k => sv[k])) {
        html += section(t('med_hc_signos'), `
            <div class="med-hc-row" style="flex-wrap:wrap;gap:12px">
            ${sv.ta?`<span><b>T/A:</b> ${esc(sv.ta)}</span>`:''}
            ${sv.fc?`<span><b>FC:</b> ${esc(sv.fc)} lpm</span>`:''}
            ${sv.fr?`<span><b>FR:</b> ${esc(sv.fr)} rpm</span>`:''}
            ${sv.temp?`<span><b>Temp:</b> ${esc(sv.temp)}°C</span>`:''}
            ${sv.spo2?`<span><b>SpO₂:</b> ${esc(sv.spo2)}%</span>`:''}
            ${sv.peso?`<span><b>${t('med_hc_peso')}:</b> ${esc(sv.peso)} kg</span>`:''}
            ${sv.talla?`<span><b>${t('med_hc_talla')}:</b> ${esc(sv.talla)} cm</span>`:''}
            </div>
        `);
    }

    // Diagnósticos
    if (_hcDiags.length) {
        let dHtml = '<div class="med-diag-list">';
        _hcDiags.forEach(d => {
            const tipo = d.tipo || 'secundario';
            dHtml += `<span class="med-diag-tag ${tipo}"><span class="med-diag-tipo">${tipo === 'principal' ? '★ Principal' : 'Secundario'}</span> ${esc(d.codigo)} — ${esc(d.descripcion)}</span>`;
        });
        dHtml += '</div>';
        html += section(t('med_hc_diagnosticos'), dHtml);
    }

    // Pronóstico / Indicación
    if (_hc.pronostico) html += section(t('med_hc_pronostico'), `<div class="med-hc-value">${esc(_hc.pronostico)}</div>`);
    if (_hc.indicacion_terapeutica) html += section(t('med_hc_indicacion'), `<div class="med-hc-value">${esc(_hc.indicacion_terapeutica)}</div>`);

    // Geriatric extras
    let geHtml = '';
    if (_hc.plan_cuidados) geHtml += row(t('med_hc_plan_cuidados'), _hc.plan_cuidados);
    if (_hc.dieta) geHtml += row(t('med_hc_dieta'), _hc.dieta);
    if (_hc.movilidad) geHtml += row(t('med_hc_movilidad'), _hc.movilidad);
    if (geHtml) html += section(t('med_hc_extras_geri'), geHtml);

    // Valoración Geriátrica
    if (Object.keys(vg).some(k => vg[k])) {
        let vgHtml = '<div class="med-hc-row" style="flex-wrap:wrap;gap:12px">';
        if (vg.barthel !== undefined && vg.barthel !== null) vgHtml += `<span><b>Barthel:</b> ${esc(vg.barthel)}</span>`;
        if (vg.lawton  !== undefined && vg.lawton  !== null) vgHtml += `<span><b>Lawton:</b> ${esc(vg.lawton)}</span>`;
        if (vg.minimental !== undefined && vg.minimental !== null) vgHtml += `<span><b>MMSE:</b> ${esc(vg.minimental)}</span>`;
        if (vg.yesavage !== undefined && vg.yesavage !== null) vgHtml += `<span><b>GDS:</b> ${esc(vg.yesavage)}</span>`;
        if (vg.mna    !== undefined && vg.mna    !== null) vgHtml += `<span><b>MNA:</b> ${esc(vg.mna)}</span>`;
        if (vg.tinetti !== undefined && vg.tinetti !== null) vgHtml += `<span><b>Tinetti:</b> ${esc(vg.tinetti)}</span>`;
        vgHtml += '</div>';
        if (vg.notas) vgHtml += `<div class="med-hc-row" style="margin-top:8px">${esc(vg.notas)}</div>`;
        html += section(t('med_hc_valoracion_geri'), vgHtml);
    }

    // Firma
    if (_hc.firmado_at) {
        html += section(t('med_firma'), `
            <div class="med-hc-row">${t('med_firmado_por')}: ${esc(_hc.firmado_por || '—')} — ${_hc.firmado_at}</div>
            ${_hc.firma_path ? `<img src="${BASE}/${esc(_hc.firma_path)}" style="max-height:80px;margin-top:6px" alt="Firma">` : ''}
        `);
    }

    content.innerHTML = html;
}

function hasHCData(hc) {
    if (!hc) return false;
    const fi = hc.ficha_identificacion || {};
    return Object.values(fi).some(v => v) ||
           hc.antecedentes_patologicos || hc.padecimiento_actual ||
           (hc.diagnosticos && hc.diagnosticos.length) || hc.pronostico ||
           hc.plan_cuidados || hc.grupo_sanguineo;
}

// ── HC Form Edit ─────────────────────────────────────────────────
const hcEditBtn   = $('#medHcEditBtn');
const hcCancelBtn = $('#medHcCancelBtn');
const hcFormEl    = $('#medHcFormEl');

if (hcEditBtn) hcEditBtn.addEventListener('click', showHCForm);
if (hcCancelBtn) hcCancelBtn.addEventListener('click', hideHCForm);
if (hcFormEl) {
    hcFormEl.addEventListener('submit', saveHC);
    // Auto-save on every input/change
    hcFormEl.addEventListener('input', debounceAutoSave);
    hcFormEl.addEventListener('change', debounceAutoSave);
}

function debounceAutoSave() {
    if (_autoSaving) return;
    clearTimeout(_autoSaveTimeout);
    _autoSaveTimeout = setTimeout(() => autoSaveHC(), 1500);
}

async function autoSaveHC() {
    if (!_residenteId || !hcFormEl) return;
    _autoSaving = true;
    showAutoSaveStatus('saving');
    try {
        const payload = buildHCPayload();
        const r = await medPost(payload);
        if (r.success) {
            _hc = r.data;
            _hcDiags = (_hc && _hc.diagnosticos) ? (Array.isArray(_hc.diagnosticos) ? _hc.diagnosticos : []) : [];
            updateProgress();
            showAutoSaveStatus('saved');
        }
    } catch (_) { /* silent fail for autosave */ }
    _autoSaving = false;
}

function showHCForm() {
    const view = $('#medHcView'); const form = $('#medHcForm');
    if (view) view.style.display = 'none';
    if (form) form.style.display = '';
    populateHCForm();
    initQuickSuggests();
    updateProgress();
}

function hideHCForm() {
    const view = $('#medHcView'); const form = $('#medHcForm');
    if (form) form.style.display = 'none';
    if (view) view.style.display = '';
    clearTimeout(_autoSaveTimeout);
}

function populateHCForm() {
    if (!_hc) return;
    const f = hcFormEl;
    const fi = _hc.ficha_identificacion || {};
    const ah = _hc.antecedentes_heredo || {};
    const ia = _hc.interrogatorio_aparatos || {};
    const ef = _hc.exploracion_fisica || {};
    const sv = _hc.signos_vitales_ingreso || {};
    const vg = _hc.valoracion_geriatrica || {};

    setVal(f, 'ficha_grupo_etnico', fi.grupo_etnico);
    setVal(f, 'ficha_religion', fi.religion);
    setVal(f, 'ficha_escolaridad', fi.escolaridad);
    setVal(f, 'ficha_ocupacion_previa', fi.ocupacion_previa);
    setVal(f, 'ficha_lugar_nacimiento', fi.lugar_nacimiento);

    ['diabetes','hipertension','cancer','cardiopatias','enf_mentales','enf_renales','enf_hepaticas','alergias_fam']
        .forEach(k => { const el = f.elements['ah_' + k]; if (el) el.checked = !!ah[k]; });
    setVal(f, 'ah_otros', ah.otros);

    setVal(f, 'antecedentes_patologicos', _hc.antecedentes_patologicos);
    setVal(f, 'antecedentes_no_patologicos', _hc.antecedentes_no_patologicos);
    setVal(f, 'alergias_detalle', _hc.alergias_detalle);
    setVal(f, 'grupo_sanguineo', _hc.grupo_sanguineo);
    setVal(f, 'padecimiento_actual', _hc.padecimiento_actual);

    ['cardiovascular','respiratorio','digestivo','urinario','musculoesqueletico',
     'neurologico','endocrino','piel_tegumentos','psiquiatrico']
        .forEach(k => setVal(f, 'ia_' + k, ia[k]));

    ['habitus','cabeza','cuello','torax','abdomen','extremidades','neurologico','piel']
        .forEach(k => setVal(f, 'ef_' + k, ef[k]));

    setVal(f, 'sv_ta', sv.ta); setVal(f, 'sv_fc', sv.fc); setVal(f, 'sv_fr', sv.fr);
    setVal(f, 'sv_temp', sv.temp); setVal(f, 'sv_spo2', sv.spo2);
    setVal(f, 'sv_peso', sv.peso); setVal(f, 'sv_talla', sv.talla);

    setVal(f, 'pronostico', _hc.pronostico);
    setVal(f, 'indicacion_terapeutica', _hc.indicacion_terapeutica);
    setVal(f, 'plan_cuidados', _hc.plan_cuidados);
    setVal(f, 'dieta', _hc.dieta);
    setVal(f, 'movilidad', _hc.movilidad);

    setVal(f, 'vg_barthel', vg.barthel); setVal(f, 'vg_lawton', vg.lawton);
    setVal(f, 'vg_minimental', vg.minimental); setVal(f, 'vg_yesavage', vg.yesavage);
    setVal(f, 'vg_mna', vg.mna); setVal(f, 'vg_tinetti', vg.tinetti);
    setVal(f, 'vg_notas', vg.notas);

    // Diagnosticos
    _hcDiags = (_hc.diagnosticos && Array.isArray(_hc.diagnosticos)) ? [..._hc.diagnosticos] : [];
    renderDiagList();
}

function buildHCPayload() {
    const f = hcFormEl;
    return {
        action: 'save_hc',
        residente_id: _residenteId,
        ficha_identificacion: {
            grupo_etnico: val(f, 'ficha_grupo_etnico'),
            religion: val(f, 'ficha_religion'),
            escolaridad: val(f, 'ficha_escolaridad'),
            ocupacion_previa: val(f, 'ficha_ocupacion_previa'),
            lugar_nacimiento: val(f, 'ficha_lugar_nacimiento'),
        },
        antecedentes_heredo: {
            diabetes: chk(f, 'ah_diabetes'), hipertension: chk(f, 'ah_hipertension'),
            cancer: chk(f, 'ah_cancer'), cardiopatias: chk(f, 'ah_cardiopatias'),
            enf_mentales: chk(f, 'ah_enf_mentales'), enf_renales: chk(f, 'ah_enf_renales'),
            enf_hepaticas: chk(f, 'ah_enf_hepaticas'), alergias_fam: chk(f, 'ah_alergias_fam'),
            otros: val(f, 'ah_otros'),
        },
        antecedentes_patologicos: val(f, 'antecedentes_patologicos'),
        antecedentes_no_patologicos: val(f, 'antecedentes_no_patologicos'),
        alergias_detalle: val(f, 'alergias_detalle'),
        grupo_sanguineo: val(f, 'grupo_sanguineo'),
        padecimiento_actual: val(f, 'padecimiento_actual'),
        interrogatorio_aparatos: {
            cardiovascular: val(f, 'ia_cardiovascular'), respiratorio: val(f, 'ia_respiratorio'),
            digestivo: val(f, 'ia_digestivo'), urinario: val(f, 'ia_urinario'),
            musculoesqueletico: val(f, 'ia_musculoesqueletico'), neurologico: val(f, 'ia_neurologico'),
            endocrino: val(f, 'ia_endocrino'), piel_tegumentos: val(f, 'ia_piel_tegumentos'),
            psiquiatrico: val(f, 'ia_psiquiatrico'),
        },
        exploracion_fisica: {
            habitus: val(f, 'ef_habitus'), cabeza: val(f, 'ef_cabeza'),
            cuello: val(f, 'ef_cuello'), torax: val(f, 'ef_torax'),
            abdomen: val(f, 'ef_abdomen'), extremidades: val(f, 'ef_extremidades'),
            neurologico: val(f, 'ef_neurologico'), piel: val(f, 'ef_piel'),
        },
        signos_vitales_ingreso: {
            ta: val(f, 'sv_ta'), fc: val(f, 'sv_fc'), fr: val(f, 'sv_fr'),
            temp: val(f, 'sv_temp'), spo2: val(f, 'sv_spo2'),
            peso: val(f, 'sv_peso'), talla: val(f, 'sv_talla'),
        },
        diagnosticos: _hcDiags,
        pronostico: val(f, 'pronostico'),
        indicacion_terapeutica: val(f, 'indicacion_terapeutica'),
        plan_cuidados: val(f, 'plan_cuidados'),
        dieta: val(f, 'dieta'),
        movilidad: val(f, 'movilidad'),
        valoracion_geriatrica: {
            barthel: numVal(f, 'vg_barthel'), lawton: numVal(f, 'vg_lawton'),
            minimental: numVal(f, 'vg_minimental'), yesavage: numVal(f, 'vg_yesavage'),
            mna: numVal(f, 'vg_mna'), tinetti: numVal(f, 'vg_tinetti'),
            notas: val(f, 'vg_notas'),
        },
    };
}

async function saveHC(e) {
    e.preventDefault();
    clearTimeout(_autoSaveTimeout);
    const payload = buildHCPayload();

    try {
        const r = await medPost(payload);
        if (r.success) {
            _hc = r.data;
            _hcDiags = (_hc && _hc.diagnosticos) ? (Array.isArray(_hc.diagnosticos) ? _hc.diagnosticos : []) : [];
            hideHCForm();
            renderHCView();
            updateProgress();
            showToast(t('med_hc_saved'), 'ok');
        } else { showToast(r.message || t('med_error_save'), 'error'); }
    } catch (e) { showToast(t('med_error_save'), 'error'); }
}

// ── CIE-10 Autocomplete ─────────────────────────────────────────
const cieInput   = $('#medCieSearch');
const cieResults = $('#medCieResults');

if (cieInput) cieInput.addEventListener('input', () => {
    clearTimeout(_cieTimeout);
    const q = cieInput.value.trim();
    if (q.length < 2) { cieResults.classList.remove('show'); toggleSpinner('medCieSpinner', false); return; }
    toggleSpinner('medCieSpinner', true);
    _cieTimeout = setTimeout(async () => {
        try {
            const r = await medFetch(`action=cie10&q=${encodeURIComponent(q)}`);
            toggleSpinner('medCieSpinner', false);
            if (!r.data || !r.data.length) { cieResults.classList.remove('show'); return; }
            cieResults.innerHTML = r.data.map(c =>
                `<div class="med-cie-item" data-code="${esc(c.codigo)}" data-desc="${esc(c.descripcion)}">
                    <span class="med-cie-code">${highlightMatch(esc(c.codigo), q)}</span>
                    <span>${highlightMatch(esc(c.descripcion), q)}</span>
                </div>`
            ).join('');
            cieResults.classList.add('show');
        } catch (_) { toggleSpinner('medCieSpinner', false); cieResults.classList.remove('show'); }
    }, 300);
});

if (cieResults) cieResults.addEventListener('click', e => {
    const item = e.target.closest('.med-cie-item');
    if (!item) return;
    const code = item.dataset.code;
    const desc = item.dataset.desc;
    if (!_hcDiags.find(d => d.codigo === code)) {
        _hcDiags.push({ codigo: code, descripcion: desc, tipo: 'secundario' });
        if (_hcDiags.length === 1) _hcDiags[0].tipo = 'principal';
        renderDiagList();
    }
    cieResults.classList.remove('show');
    cieInput.value = '';
});

document.addEventListener('click', e => {
    if (cieResults && !e.target.closest('.med-cie-search-wrap')) cieResults.classList.remove('show');
});

function renderDiagList() {
    const el = $('#medDiagList');
    if (!el) return;
    if (!_hcDiags.length) { el.innerHTML = ''; return; }
    el.innerHTML = _hcDiags.map((d, i) => {
        const tipo = d.tipo || 'secundario';
        return `<span class="med-diag-tag ${tipo}" data-idx="${i}">
            <span class="med-diag-tipo">${tipo === 'principal' ? '★ Ppal' : 'Sec'}</span>
            ${esc(d.codigo)} — ${esc(d.descripcion)}
            <button type="button" class="med-diag-toggle" data-action="toggle" data-idx="${i}" title="Cambiar tipo">${tipo === 'principal' ? '→Sec' : '→Ppal'}</button>
            <button type="button" class="med-diag-remove" data-action="remove" data-idx="${i}" title="Quitar">&times;</button>
         </span>`;
    }).join('');
    el.querySelectorAll('button[data-action="remove"]').forEach(b => b.addEventListener('click', e => {
        e.stopPropagation();
        _hcDiags.splice(parseInt(b.dataset.idx), 1);
        renderDiagList();
    }));
    el.querySelectorAll('button[data-action="toggle"]').forEach(b => b.addEventListener('click', e => {
        e.stopPropagation();
        const idx = parseInt(b.dataset.idx);
        if (_hcDiags[idx].tipo === 'principal') {
            _hcDiags[idx].tipo = 'secundario';
        } else {
            // Set this as principal, demote current principal
            _hcDiags.forEach(d => { if (d.tipo === 'principal') d.tipo = 'secundario'; });
            _hcDiags[idx].tipo = 'principal';
        }
        renderDiagList();
    }));
}

// ═════════════════════════════════════════════════════════════════
// NOTAS DE EVOLUCIÓN
// ═════════════════════════════════════════════════════════════════
async function loadNotas() {
    if (!_residenteId) return;
    const filterEl = $('#medNotasTipoFilter');
    const tipo = filterEl ? filterEl.value : '';
    try {
        const r = await medFetch(`action=notas&residente_id=${_residenteId}${tipo ? '&tipo=' + tipo : ''}`);
        _notasCache = r.data || [];
        renderNotas();
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

function renderNotas() {
    const list = $('#medNotasList');
    if (!list) return;
    if (!_notasCache.length) {
        list.innerHTML = `<div class="med-empty-state"><p>${t('med_notas_empty')}</p></div>`;
        return;
    }
    list.innerHTML = _notasCache.map(n => `
        <div class="med-tl-card ${n.firmado ? 'firmado' : ''}" data-id="${n.id}">
            <div class="med-tl-head">
                <span class="med-tl-tipo ${esc(n.tipo)}">${t('med_tipo_' + n.tipo)}</span>
                <span class="med-tl-datetime">${esc(n.fecha)} ${esc(n.hora || '')}</span>
                <span class="med-tl-author">${esc(n.autor_nombre)}</span>
            </div>
            <div class="med-tl-excerpt">${esc((n.subjetivo || n.objetivo || n.analisis || '').substring(0, 150))}</div>
            <div class="med-tl-footer">
                ${n.firmado ? `<span class="med-tl-signed"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> ${t('med_signed')}</span>` : ''}
                ${n.diagnosticos && n.diagnosticos.length ? `<span style="color:var(--cd-text-muted)">${n.diagnosticos.map(d=>esc(d.codigo)).join(', ')}</span>` : ''}
            </div>
        </div>
    `).join('');

    list.querySelectorAll('.med-tl-card').forEach(card => {
        card.addEventListener('click', () => openNotaDetail(parseInt(card.dataset.id)));
    });
}

const notaFilterEl = $('#medNotasTipoFilter');
if (notaFilterEl) notaFilterEl.addEventListener('change', loadNotas);

const notaNewBtn = $('#medNotaNewBtn');
if (notaNewBtn) notaNewBtn.addEventListener('click', () => openNotaForm());

// ── Nota detail sidebar ──────────────────────────────────────────
async function openNotaDetail(id) {
    try {
        const r = await medFetch(`action=nota&id=${id}`);
        const n = r.data;
        if (!n) return;

        const sv = n.signos_vitales || {};
        const diags = n.diagnosticos || [];

        let html = `<div class="med-sb-form">`;
        html += `<div style="margin-bottom:12px"><span class="med-tl-tipo ${esc(n.tipo)}">${t('med_tipo_' + n.tipo)}</span>
                  <span style="color:var(--cd-text-muted);font-size:.8rem;margin-left:8px">${esc(n.fecha)} ${esc(n.hora||'')}</span></div>`;

        if (n.subjetivo) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_soap_s')}</label><div>${esc(n.subjetivo)}</div></div>`;
        if (n.objetivo)  html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_soap_o')}</label><div>${esc(n.objetivo)}</div></div>`;
        if (n.analisis)  html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_soap_a')}</label><div>${esc(n.analisis)}</div></div>`;
        if (n.plan)      html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_soap_p')}</label><div>${esc(n.plan)}</div></div>`;

        if (Object.values(sv).some(v => v)) {
            html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_hc_signos')}</label>
                <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.82rem">
                ${sv.ta_s&&sv.ta_d?`<span>T/A: ${esc(sv.ta_s)}/${esc(sv.ta_d)}</span>`:''}
                ${sv.fc?`<span>FC: ${esc(sv.fc)}</span>`:''}${sv.fr?`<span>FR: ${esc(sv.fr)}</span>`:''}
                ${sv.temp?`<span>T: ${esc(sv.temp)}°C</span>`:''}${sv.spo2?`<span>SpO₂: ${esc(sv.spo2)}%</span>`:''}
                </div></div>`;
        }

        if (diags.length) {
            html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_hc_diagnosticos')}</label>
                <div class="med-diag-list">${diags.map(d=>`<span class="med-diag-tag">${esc(d.codigo)} — ${esc(d.descripcion)}</span>`).join('')}</div></div>`;
        }

        if (n.tipo === 'interconsulta') {
            if (n.medico_consultado) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_consultado')}</label><div>${esc(n.medico_consultado)} ${n.especialidad ? '('+esc(n.especialidad)+')' : ''}</div></div>`;
        }
        if (n.tipo === 'referencia') {
            if (n.establecimiento_recibe) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_ref_recibe')}</label><div>${esc(n.establecimiento_recibe)}</div></div>`;
            if (n.motivo_envio) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_ref_motivo')}</label><div>${esc(n.motivo_envio)}</div></div>`;
        }
        if (n.tipo === 'egreso') {
            if (n.motivo_egreso) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_egreso_motivo')}</label><div>${esc(n.motivo_egreso)}</div></div>`;
            if (n.recomendaciones) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_egreso_rec')}</label><div>${esc(n.recomendaciones)}</div></div>`;
        }

        if (n.firmado) {
            html += `<div class="med-tl-signed" style="margin-top:12px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> ${t('med_signed')} — ${esc(n.firmado_at||'')}</div>`;
            if (n.firma_path) html += `<img src="${BASE}/${esc(n.firma_path)}" style="max-height:60px;margin-top:6px" alt="Firma">`;
        }
        html += '</div>';

        let actions = '';
        if (!n.firmado && CAN_EDIT) {
            actions += `<button class="med-btn med-btn-sm med-btn-secondary" onclick="window._medEditNota(${n.id})">${t('med_btn_edit')}</button>`;
            actions += `<button class="med-btn med-btn-sm med-btn-primary" onclick="window._medFirmarNota(${n.id})">${t('med_btn_firmar')}</button>`;
            actions += `<button class="med-btn med-btn-sm med-btn-danger" onclick="window._medDeleteNota(${n.id})">${t('btn_delete')}</button>`;
        }
        if (n.firmado && CAN_EDIT) {
            actions += `<button class="med-btn med-btn-sm med-btn-secondary" onclick="window._medAddendum(${n.id})">${t('med_btn_addendum')}</button>`;
        }

        openSidebar(t('med_nota_detail'), html, actions);
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

// ── Nota create/edit form (sidebar) ──────────────────────────────
function openNotaForm(editData) {
    const isEdit = !!(editData && editData.id);
    const n = editData || {};

    let html = `<form id="medSbNotaForm" class="med-sb-form" autocomplete="off">
        <div class="cd-form-group"><label class="cd-form-label">${t('med_nota_tipo')}</label>
            <select class="cd-select" name="tipo">
                <option value="evolucion" ${n.tipo==='evolucion'?'selected':''}>${t('med_tipo_evolucion')}</option>
                <option value="interconsulta" ${n.tipo==='interconsulta'?'selected':''}>${t('med_tipo_interconsulta')}</option>
                <option value="referencia" ${n.tipo==='referencia'?'selected':''}>${t('med_tipo_referencia')}</option>
                <option value="ingreso" ${n.tipo==='ingreso'?'selected':''}>${t('med_tipo_ingreso')}</option>
                <option value="egreso" ${n.tipo==='egreso'?'selected':''}>${t('med_tipo_egreso')}</option>
            </select></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_soap_s')}</label><textarea class="cd-textarea" name="subjetivo" rows="3">${esc(n.subjetivo||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_soap_o')}</label><textarea class="cd-textarea" name="objetivo" rows="3">${esc(n.objetivo||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_soap_a')}</label><textarea class="cd-textarea" name="analisis" rows="3">${esc(n.analisis||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_soap_p')}</label><textarea class="cd-textarea" name="plan" rows="3">${esc(n.plan||'')}</textarea></div>

        <details class="med-fieldset" style="border:1px solid var(--cd-border);border-radius:8px;padding:12px;margin-bottom:14px">
            <summary style="cursor:pointer;font-weight:600;font-size:.82rem;color:var(--cd-accent)">${t('med_hc_signos')}</summary>
            <div class="med-sv-grid" style="margin-top:10px">
                <div class="cd-form-group"><label class="cd-form-label">T/A Sist.</label><input type="number" class="cd-input" name="sv_ta_s" value="${esc(n.signos_vitales?.ta_s||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">T/A Diast.</label><input type="number" class="cd-input" name="sv_ta_d" value="${esc(n.signos_vitales?.ta_d||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">FC</label><input type="number" class="cd-input" name="sv_fc" value="${esc(n.signos_vitales?.fc||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">FR</label><input type="number" class="cd-input" name="sv_fr" value="${esc(n.signos_vitales?.fr||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Temp</label><input type="number" class="cd-input" name="sv_temp" step="0.1" value="${esc(n.signos_vitales?.temp||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">SpO₂</label><input type="number" class="cd-input" name="sv_spo2" value="${esc(n.signos_vitales?.spo2||'')}"></div>
            </div>
        </details>

        <div class="cd-form-group" id="medSbNotaCieWrap">
            <label class="cd-form-label">${t('med_hc_diagnosticos')}</label>
            <input type="text" class="cd-input" id="medSbCieSearch" placeholder="${t('med_cie_search_ph')}" autocomplete="off">
            <div class="med-cie-results" id="medSbCieResults"></div>
            <div id="medSbDiagList" class="med-diag-list" style="margin-top:6px"></div>
        </div>

        <div class="cd-form-group"><label class="cd-form-label">${t('med_hc_pronostico')}</label><textarea class="cd-textarea" name="pronostico" rows="2">${esc(n.pronostico||'')}</textarea></div>

        <!-- Interconsulta fields -->
        <div id="medSbInterFields" style="display:none">
            <div class="cd-form-group"><label class="cd-form-label">${t('med_consultado')}</label><input type="text" class="cd-input" name="medico_consultado" value="${esc(n.medico_consultado||'')}"></div>
            <div class="cd-form-group"><label class="cd-form-label">${t('med_especialidad')}</label><input type="text" class="cd-input" name="especialidad" value="${esc(n.especialidad||'')}"></div>
        </div>

        <!-- Referencia fields -->
        <div id="medSbRefFields" style="display:none">
            <div class="cd-form-group"><label class="cd-form-label">${t('med_ref_recibe')}</label><input type="text" class="cd-input" name="establecimiento_recibe" value="${esc(n.establecimiento_recibe||'')}"></div>
            <div class="cd-form-group"><label class="cd-form-label">${t('med_ref_motivo')}</label><textarea class="cd-textarea" name="motivo_envio" rows="2">${esc(n.motivo_envio||'')}</textarea></div>
        </div>

        <!-- Egreso fields -->
        <div id="medSbEgresoFields" style="display:none">
            <div class="cd-form-group"><label class="cd-form-label">${t('med_egreso_motivo')}</label>
                <select class="cd-select" name="motivo_egreso">
                    <option value="">—</option>
                    <option value="mejoria" ${n.motivo_egreso==='mejoria'?'selected':''}>${t('med_egreso_mejoria')}</option>
                    <option value="maximo_beneficio" ${n.motivo_egreso==='maximo_beneficio'?'selected':''}>${t('med_egreso_max')}</option>
                    <option value="voluntario" ${n.motivo_egreso==='voluntario'?'selected':''}>${t('med_egreso_vol')}</option>
                    <option value="defuncion" ${n.motivo_egreso==='defuncion'?'selected':''}>${t('med_egreso_def')}</option>
                    <option value="traslado" ${n.motivo_egreso==='traslado'?'selected':''}>${t('med_egreso_traslado')}</option>
                </select></div>
            <div class="cd-form-group"><label class="cd-form-label">${t('med_egreso_rec')}</label><textarea class="cd-textarea" name="recomendaciones" rows="2">${esc(n.recomendaciones||'')}</textarea></div>
        </div>
    </form>`;

    const actions = `
        <button class="med-btn med-btn-secondary" onclick="closeSidebar()">${t('btn_cancel')}</button>
        <button class="med-btn med-btn-primary" id="medSbNotaSaveBtn">${t('btn_save')}</button>
    `;

    openSidebar(isEdit ? t('med_nota_edit') : t('med_nota_new'), html, actions);

    // Init sidebar CIE search
    let sbDiags = (n.diagnosticos && Array.isArray(n.diagnosticos)) ? [...n.diagnosticos] : [];
    const sbDiagList = $('#medSbDiagList');
    const sbCieInput = $('#medSbCieSearch');
    const sbCieResults = $('#medSbCieResults');

    function renderSbDiags() {
        if (!sbDiagList) return;
        if (!sbDiags.length) { sbDiagList.innerHTML = ''; return; }
        sbDiagList.innerHTML = sbDiags.map((d, i) => {
            const tipo = d.tipo || 'secundario';
            return `<span class="med-diag-tag ${tipo}" data-idx="${i}">
                <span class="med-diag-tipo">${tipo === 'principal' ? '★ Ppal' : 'Sec'}</span>
                ${esc(d.codigo)} — ${esc(d.descripcion)}
                <button type="button" class="med-diag-toggle" data-action="toggle" data-idx="${i}" title="Cambiar tipo">${tipo === 'principal' ? '→Sec' : '→Ppal'}</button>
                <button type="button" class="med-diag-remove" data-action="remove" data-idx="${i}">&times;</button>
            </span>`;
        }).join('');
        sbDiagList.querySelectorAll('button[data-action="remove"]').forEach(b => b.addEventListener('click', e => {
            e.stopPropagation();
            sbDiags.splice(parseInt(b.dataset.idx), 1); renderSbDiags();
        }));
        sbDiagList.querySelectorAll('button[data-action="toggle"]').forEach(b => b.addEventListener('click', e => {
            e.stopPropagation();
            const idx = parseInt(b.dataset.idx);
            if (sbDiags[idx].tipo === 'principal') {
                sbDiags[idx].tipo = 'secundario';
            } else {
                sbDiags.forEach(d => { if (d.tipo === 'principal') d.tipo = 'secundario'; });
                sbDiags[idx].tipo = 'principal';
            }
            renderSbDiags();
        }));
    }
    renderSbDiags();

    let sbCieTimeout;
    if (sbCieInput) sbCieInput.addEventListener('input', () => {
        clearTimeout(sbCieTimeout);
        const q = sbCieInput.value.trim();
        if (q.length < 2) { if (sbCieResults) sbCieResults.classList.remove('show'); return; }
        sbCieTimeout = setTimeout(async () => {
            try {
                const r = await medFetch(`action=cie10&q=${encodeURIComponent(q)}`);
                if (!r.data.length) { sbCieResults.classList.remove('show'); return; }
                sbCieResults.innerHTML = r.data.map(c =>
                    `<div class="med-cie-item" data-code="${esc(c.codigo)}" data-desc="${esc(c.descripcion)}"><span class="med-cie-code">${highlightMatch(esc(c.codigo), q)}</span><span>${highlightMatch(esc(c.descripcion), q)}</span></div>`
                ).join('');
                sbCieResults.classList.add('show');
            } catch (_) {}
        }, 300);
    });

    if (sbCieResults) sbCieResults.addEventListener('click', e => {
        const item = e.target.closest('.med-cie-item');
        if (!item) return;
        if (!sbDiags.find(d => d.codigo === item.dataset.code)) {
            const tipo = sbDiags.length === 0 ? 'principal' : 'secundario';
            sbDiags.push({ codigo: item.dataset.code, descripcion: item.dataset.desc, tipo });
            renderSbDiags();
        }
        sbCieResults.classList.remove('show');
        sbCieInput.value = '';
    });

    // Toggle type-specific fields
    const tipoSel = document.querySelector('#medSbNotaForm [name="tipo"]');
    function toggleTipoFields() {
        const v = tipoSel.value;
        const inter = $('#medSbInterFields');
        const ref   = $('#medSbRefFields');
        const egr   = $('#medSbEgresoFields');
        if (inter) inter.style.display = v === 'interconsulta' ? '' : 'none';
        if (ref)   ref.style.display   = v === 'referencia'    ? '' : 'none';
        if (egr)   egr.style.display   = v === 'egreso'       ? '' : 'none';
    }
    if (tipoSel) { tipoSel.addEventListener('change', toggleTipoFields); toggleTipoFields(); }

    // Save handler
    const saveBtn = $('#medSbNotaSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', async () => {
        const form = $('#medSbNotaForm');
        const fd = new FormData(form);
        const payload = {
            action: isEdit ? 'update_nota' : 'create_nota',
            residente_id: _residenteId,
            tipo: fd.get('tipo'),
            subjetivo: fd.get('subjetivo'),
            objetivo: fd.get('objetivo'),
            analisis: fd.get('analisis'),
            plan: fd.get('plan'),
            pronostico: fd.get('pronostico'),
            signos_vitales: {
                ta_s: fd.get('sv_ta_s'), ta_d: fd.get('sv_ta_d'),
                fc: fd.get('sv_fc'), fr: fd.get('sv_fr'),
                temp: fd.get('sv_temp'), spo2: fd.get('sv_spo2'),
            },
            diagnosticos: sbDiags,
            medico_consultado: fd.get('medico_consultado'),
            especialidad: fd.get('especialidad'),
            establecimiento_recibe: fd.get('establecimiento_recibe'),
            motivo_envio: fd.get('motivo_envio'),
            motivo_egreso: fd.get('motivo_egreso'),
            recomendaciones: fd.get('recomendaciones'),
        };
        if (isEdit) payload.id = editData.id;
        if (n.es_addendum)   payload.es_addendum   = 1;
        if (n.nota_padre_id) payload.nota_padre_id = n.nota_padre_id;

        try {
            const r = await medPost(payload);
            if (r.success) {
                closeSidebar();
                loadNotas();
                showToast(t('med_nota_saved'), 'ok');
            } else { showToast(r.message || t('med_error_save'), 'error'); }
        } catch (_) { showToast(t('med_error_save'), 'error'); }
    });
}

// ── Nota actions (global for sidebar buttons) ────────────────────
window._medEditNota = async function (id) {
    try {
        const r = await medFetch(`action=nota&id=${id}`);
        if (r.data) openNotaForm(r.data);
    } catch (_) {}
};

window._medFirmarNota = function (id) { openFirmaModal('nota', id); };

window._medDeleteNota = async function (id) {
    if (!confirm(t('med_confirm_delete'))) return;
    try {
        const r = await medDelete(`action=delete_nota&id=${id}`);
        if (r.success) { closeSidebar(); loadNotas(); showToast(t('med_deleted'), 'ok'); }
        else showToast(r.message || t('med_error_delete'), 'error');
    } catch (_) { showToast(t('med_error_delete'), 'error'); }
};

window._medAddendum = function (parentId) {
    openNotaForm({ tipo: 'evolucion', es_addendum: 1, nota_padre_id: parentId });
};

// ═════════════════════════════════════════════════════════════════
// ENFERMERÍA
// ═════════════════════════════════════════════════════════════════
async function loadEnfermeria() {
    if (!_residenteId) return;
    try {
        const r = await medFetch(`action=enfermeria&residente_id=${_residenteId}`);
        _enfCache = r.data || [];
        renderEnfermeria();
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

function renderEnfermeria() {
    const list = $('#medEnfList');
    if (!list) return;
    if (!_enfCache.length) {
        list.innerHTML = `<div class="med-empty-state"><p>${t('med_enf_empty')}</p></div>`;
        return;
    }
    list.innerHTML = _enfCache.map(e => {
        const sv = e.signos_vitales || {};
        return `
        <div class="med-tl-card ${e.firmado?'firmado':''}" data-id="${e.id}">
            <div class="med-tl-head">
                <span class="med-tl-tipo ${esc(e.turno)}">${t('med_turno_' + e.turno)}</span>
                <span class="med-tl-datetime">${esc(e.fecha)}</span>
                <span class="med-tl-author">${esc(e.autor_nombre)}</span>
            </div>
            <div class="med-tl-excerpt">
                ${sv.ta_s && sv.ta_d ? `T/A: ${sv.ta_s}/${sv.ta_d} ` : ''}
                ${sv.fc ? `FC: ${sv.fc} ` : ''}
                ${sv.temp ? `T: ${sv.temp}°C ` : ''}
                ${e.observaciones ? '— ' + esc(e.observaciones.substring(0, 100)) : ''}
            </div>
            ${e.firmado ? `<div class="med-tl-footer"><span class="med-tl-signed"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> ${t('med_signed')}</span></div>` : ''}
        </div>`;
    }).join('');

    list.querySelectorAll('.med-tl-card').forEach(card => {
        card.addEventListener('click', () => openEnfDetail(parseInt(card.dataset.id)));
    });
}

const enfNewBtn = $('#medEnfNewBtn');
if (enfNewBtn) enfNewBtn.addEventListener('click', () => openEnfForm());

async function openEnfDetail(id) {
    const e = _enfCache.find(x => x.id === id);
    if (!e) return;
    const sv = e.signos_vitales || {};
    const meds = e.medicamentos_administrados || [];
    const dolor = e.valoracion_dolor || {};

    let html = '<div class="med-sb-form">';
    html += `<div style="margin-bottom:12px"><span class="med-tl-tipo ${esc(e.turno)}">${t('med_turno_' + e.turno)}</span>
              <span style="color:var(--cd-text-muted);font-size:.8rem;margin-left:8px">${esc(e.fecha)}</span></div>`;
    if (e.habitus_exterior) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_habitus')}</label><div>${esc(e.habitus_exterior)}</div></div>`;

    if (Object.values(sv).some(v => v)) {
        html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_hc_signos')}</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.82rem">
            ${sv.ta_s&&sv.ta_d?`<span>T/A: ${sv.ta_s}/${sv.ta_d}</span>`:''}${sv.fc?`<span>FC: ${sv.fc}</span>`:''}
            ${sv.fr?`<span>FR: ${sv.fr}</span>`:''}${sv.temp?`<span>T: ${sv.temp}°C</span>`:''}
            ${sv.spo2?`<span>SpO₂: ${sv.spo2}%</span>`:''}${sv.peso?`<span>Peso: ${sv.peso}</span>`:''}
            ${sv.glucosa?`<span>Gluc: ${sv.glucosa}</span>`:''}
            </div></div>`;
    }

    if (meds.length) {
        html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_meds')}</label><ul style="margin:0;padding-left:16px;font-size:.82rem">`;
        meds.forEach(m => { html += `<li>${esc(m.nombre)} ${esc(m.dosis||'')} ${esc(m.via||'')} — ${esc(m.hora||'')}</li>`; });
        html += '</ul></div>';
    }
    if (e.procedimientos) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_proc')}</label><div>${esc(e.procedimientos)}</div></div>`;
    if (e.observaciones) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_obs')}</label><div>${esc(e.observaciones)}</div></div>`;
    if (dolor.escala) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_dolor')}</label><div>${t('med_enf_escala')}: ${dolor.escala}/10 ${dolor.localizacion ? '— ' + esc(dolor.localizacion) : ''}</div></div>`;
    if (e.riesgo_caidas) html += `<div class="cd-form-group"><label class="cd-form-label">${t('med_enf_caidas')}</label><div>${esc(e.riesgo_caidas)}</div></div>`;
    html += '</div>';

    let actions = '';
    if (!e.firmado && CAN_EDIT) {
        actions += `<button class="med-btn med-btn-sm med-btn-secondary" onclick="window._medEditEnf(${e.id})">${t('med_btn_edit')}</button>`;
        actions += `<button class="med-btn med-btn-sm med-btn-primary" onclick="window._medFirmarEnf(${e.id})">${t('med_btn_firmar')}</button>`;
    }

    openSidebar(t('med_enf_detail'), html, actions);
}

function openEnfForm(editData) {
    const isEdit = !!editData;
    const e = editData || {};
    const sv = e.signos_vitales || {};

    let html = `<form id="medSbEnfForm" class="med-sb-form" autocomplete="off">
        <div class="cd-form-group"><label class="cd-form-label">${t('med_enf_turno')}</label>
            <select class="cd-select" name="turno">
                <option value="matutino" ${e.turno==='matutino'?'selected':''}>${t('med_turno_matutino')}</option>
                <option value="vespertino" ${e.turno==='vespertino'?'selected':''}>${t('med_turno_vespertino')}</option>
                <option value="nocturno" ${e.turno==='nocturno'?'selected':''}>${t('med_turno_nocturno')}</option>
            </select></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_enf_habitus')}</label>
            <textarea class="cd-textarea" name="habitus_exterior" rows="2">${esc(e.habitus_exterior||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_hc_signos')}</label>
            <div class="med-sv-grid">
                <div class="cd-form-group"><label class="cd-form-label">T/A S</label><input type="number" class="cd-input" name="sv_ta_s" value="${esc(sv.ta_s||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">T/A D</label><input type="number" class="cd-input" name="sv_ta_d" value="${esc(sv.ta_d||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">FC</label><input type="number" class="cd-input" name="sv_fc" value="${esc(sv.fc||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">FR</label><input type="number" class="cd-input" name="sv_fr" value="${esc(sv.fr||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Temp</label><input type="number" class="cd-input" name="sv_temp" step="0.1" value="${esc(sv.temp||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">SpO₂</label><input type="number" class="cd-input" name="sv_spo2" value="${esc(sv.spo2||'')}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Glucosa</label><input type="number" class="cd-input" name="sv_glucosa" value="${esc(sv.glucosa||'')}"></div>
            </div></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_enf_proc')}</label>
            <textarea class="cd-textarea" name="procedimientos" rows="2">${esc(e.procedimientos||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_enf_obs')}</label>
            <textarea class="cd-textarea" name="observaciones" rows="3">${esc(e.observaciones||'')}</textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_enf_caidas')}</label>
            <select class="cd-select" name="riesgo_caidas">
                <option value="">—</option>
                <option value="bajo" ${e.riesgo_caidas==='bajo'?'selected':''}>Bajo</option>
                <option value="medio" ${e.riesgo_caidas==='medio'?'selected':''}>Medio</option>
                <option value="alto" ${e.riesgo_caidas==='alto'?'selected':''}>Alto</option>
            </select></div>
    </form>`;

    const actions = `
        <button class="med-btn med-btn-secondary" onclick="closeSidebar()">${t('btn_cancel')}</button>
        <button class="med-btn med-btn-primary" id="medSbEnfSaveBtn">${t('btn_save')}</button>`;

    openSidebar(isEdit ? t('med_enf_edit') : t('med_enf_new'), html, actions);

    const saveBtn = $('#medSbEnfSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', async () => {
        const form = $('#medSbEnfForm');
        const fd = new FormData(form);
        const payload = {
            action: isEdit ? 'update_enfermeria' : 'create_enfermeria',
            residente_id: _residenteId,
            turno: fd.get('turno'),
            habitus_exterior: fd.get('habitus_exterior'),
            signos_vitales: {
                ta_s: fd.get('sv_ta_s'), ta_d: fd.get('sv_ta_d'),
                fc: fd.get('sv_fc'), fr: fd.get('sv_fr'),
                temp: fd.get('sv_temp'), spo2: fd.get('sv_spo2'),
                glucosa: fd.get('sv_glucosa'),
            },
            procedimientos: fd.get('procedimientos'),
            observaciones: fd.get('observaciones'),
            riesgo_caidas: fd.get('riesgo_caidas'),
        };
        if (isEdit) payload.id = editData.id;

        try {
            const r = await medPost(payload);
            if (r.success) { closeSidebar(); loadEnfermeria(); showToast(t('med_enf_saved'), 'ok'); }
            else showToast(r.message || t('med_error_save'), 'error');
        } catch (_) { showToast(t('med_error_save'), 'error'); }
    });
}

window._medEditEnf = async function (id) {
    try {
        const r = await medFetch(`action=enfermeria&residente_id=${_residenteId}`);
        const e = (r.data || []).find(x => x.id === id);
        if (e) openEnfForm(e);
    } catch (_) {}
};

window._medFirmarEnf = function (id) { openFirmaModal('enfermeria', id); };

// ═════════════════════════════════════════════════════════════════
// ESTUDIOS
// ═════════════════════════════════════════════════════════════════
async function loadEstudios() {
    if (!_residenteId) return;
    try {
        const r = await medFetch(`action=estudios&residente_id=${_residenteId}`);
        renderEstudios(r.data || []);
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

function renderEstudios(data) {
    const list = $('#medEstList');
    if (!list) return;
    if (!data.length) {
        list.innerHTML = `<div class="med-empty-state"><p>${t('med_est_empty')}</p></div>`;
        return;
    }
    list.innerHTML = data.map(e => `
        <div class="med-card">
            <div class="med-card-title">${esc(e.estudio_solicitado)}</div>
            <div class="med-card-meta">${esc(e.fecha)} — ${esc(e.autor_nombre)}</div>
            <div class="med-card-body">${esc((e.resultados || e.interpretacion || '').substring(0, 120))}</div>
            <div class="med-card-actions">
                ${e.archivo_path ? `<a class="med-card-link" href="${BASE}/${esc(e.archivo_path)}" target="_blank">${t('med_ver_archivo')}</a>` : ''}
            </div>
        </div>
    `).join('');
}

const estNewBtn = $('#medEstNewBtn');
if (estNewBtn) estNewBtn.addEventListener('click', () => {
    let html = `<form id="medSbEstForm" class="med-sb-form" enctype="multipart/form-data" autocomplete="off">
        <div class="cd-form-group"><label class="cd-form-label">${t('med_est_nombre')}</label>
            <input type="text" class="cd-input" name="estudio_solicitado" required></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_est_problema')}</label>
            <textarea class="cd-textarea" name="problema_clinico" rows="2"></textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_est_resultados')}</label>
            <textarea class="cd-textarea" name="resultados" rows="3"></textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_est_interpretacion')}</label>
            <textarea class="cd-textarea" name="interpretacion" rows="2"></textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_est_archivo')}</label>
            <input type="file" class="cd-input" name="archivo" accept="image/*,.pdf,.doc,.docx"></div>
    </form>`;

    const actions = `
        <button class="med-btn med-btn-secondary" onclick="closeSidebar()">${t('btn_cancel')}</button>
        <button class="med-btn med-btn-primary" id="medSbEstSaveBtn">${t('btn_save')}</button>`;
    openSidebar(t('med_est_new'), html, actions);

    $('#medSbEstSaveBtn').addEventListener('click', async () => {
        const form = $('#medSbEstForm');
        const fd = new FormData(form);
        fd.append('action', 'create_estudio');
        fd.append('residente_id', _residenteId);

        try {
            const resp = await fetch(MED_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const r = await resp.json();
            if (r.success) { closeSidebar(); loadEstudios(); showToast(t('med_est_saved'), 'ok'); }
            else showToast(r.message || t('med_error_save'), 'error');
        } catch (_) { showToast(t('med_error_save'), 'error'); }
    });
});

// ═════════════════════════════════════════════════════════════════
// CONSENTIMIENTOS
// ═════════════════════════════════════════════════════════════════
async function loadConsentimientos() {
    if (!_residenteId) return;
    try {
        const r = await medFetch(`action=consentimientos&residente_id=${_residenteId}`);
        renderConsentimientos(r.data || []);
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

function renderConsentimientos(data) {
    const list = $('#medConsList');
    if (!list) return;
    if (!data.length) {
        list.innerHTML = `<div class="med-empty-state"><p>${t('med_cons_empty')}</p></div>`;
        return;
    }
    list.innerHTML = data.map(c => `
        <div class="med-card">
            <div class="med-card-title">${esc(c.titulo)}</div>
            <div class="med-card-meta">${esc(c.fecha)} — ${esc(c.autor_nombre)}</div>
            <div class="med-card-body">
                ${c.otorgante_nombre ? `<div>${t('med_cons_otorgante')}: ${esc(c.otorgante_nombre)} (${esc(c.otorgante_parentesco||'')})</div>` : ''}
                ${c.medico_nombre ? `<div>${t('med_cons_medico')}: ${esc(c.medico_nombre)}</div>` : ''}
            </div>
            <div class="med-card-actions">
                ${c.documento_path ? `<a class="med-card-link" href="${BASE}/${esc(c.documento_path)}" target="_blank">${t('med_ver_archivo')}</a>` : ''}
            </div>
        </div>
    `).join('');
}

const consNewBtn = $('#medConsNewBtn');
if (consNewBtn) consNewBtn.addEventListener('click', () => {
    let html = `<form id="medSbConsForm" class="med-sb-form" enctype="multipart/form-data" autocomplete="off">
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_titulo')}</label>
            <input type="text" class="cd-input" name="titulo" required></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_riesgos')}</label>
            <textarea class="cd-textarea" name="riesgos_beneficios" rows="3"></textarea></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_otorgante')}</label>
            <input type="text" class="cd-input" name="otorgante_nombre"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_parentesco')}</label>
            <input type="text" class="cd-input" name="otorgante_parentesco"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_medico')}</label>
            <input type="text" class="cd-input" name="medico_nombre"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_testigo1')}</label>
            <input type="text" class="cd-input" name="testigo1_nombre"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_testigo2')}</label>
            <input type="text" class="cd-input" name="testigo2_nombre"></div>
        <div class="cd-form-group"><label class="cd-form-label">${t('med_cons_doc')}</label>
            <input type="file" class="cd-input" name="documento" accept="image/*,.pdf"></div>
    </form>`;

    const actions = `
        <button class="med-btn med-btn-secondary" onclick="closeSidebar()">${t('btn_cancel')}</button>
        <button class="med-btn med-btn-primary" id="medSbConsSaveBtn">${t('btn_save')}</button>`;
    openSidebar(t('med_cons_new'), html, actions);

    $('#medSbConsSaveBtn').addEventListener('click', async () => {
        const form = $('#medSbConsForm');
        const fd = new FormData(form);
        fd.append('action', 'create_consentimiento');
        fd.append('residente_id', _residenteId);

        try {
            const resp = await fetch(MED_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            const r = await resp.json();
            if (r.success) { closeSidebar(); loadConsentimientos(); showToast(t('med_cons_saved'), 'ok'); }
            else showToast(r.message || t('med_error_save'), 'error');
        } catch (_) { showToast(t('med_error_save'), 'error'); }
    });
});

// ═════════════════════════════════════════════════════════════════
// AUDITORÍA
// ═════════════════════════════════════════════════════════════════
async function loadAuditoria() {
    if (!_residenteId) return;
    try {
        const r = await medFetch(`action=auditoria&residente_id=${_residenteId}`);
        renderAuditoria(r.data || []);
    } catch (_) { showToast(t('med_error_load'), 'error'); }
}

function renderAuditoria(data) {
    const list = $('#medAuditList');
    if (!list) return;
    if (!data.length) {
        list.innerHTML = `<div class="med-empty-state"><p>${t('med_audit_empty')}</p></div>`;
        return;
    }
    list.innerHTML = data.map(a => `
        <div class="med-audit-row">
            <span class="med-audit-date">${esc(a.creado_at)}</span>
            <span class="med-audit-action ${esc(a.accion)}">${esc(a.accion)}</span>
            <span class="med-audit-entity">${esc(a.entidad)}${a.entidad_id ? ' #'+a.entidad_id : ''}</span>
            <span class="med-audit-user">${esc(a.usuario_nombre)}</span>
        </div>
    `).join('');
}

// ═════════════════════════════════════════════════════════════════
// FIRMA (Canvas Signature)
// ═════════════════════════════════════════════════════════════════
function openFirmaModal(entity, entityId) {
    let html = `<div class="med-sb-form">
        <p style="font-size:.85rem;margin-bottom:12px">${t('med_firma_instruccion')}</p>
        <div class="med-firma-wrap">
            <canvas id="medFirmaCanvas" class="med-firma-canvas" width="600" height="200"></canvas>
            <div class="med-firma-actions">
                <button class="med-btn med-btn-sm med-btn-secondary" id="medFirmaClear">${t('med_firma_clear')}</button>
            </div>
        </div>
    </div>`;

    const actions = `
        <button class="med-btn med-btn-secondary" onclick="closeSidebar()">${t('btn_cancel')}</button>
        <button class="med-btn med-btn-primary" id="medFirmaSaveBtn">${t('med_btn_firmar')}</button>`;

    openSidebar(t('med_firma_title'), html, actions);

    const canvas = $('#medFirmaCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let drawing = false;

    // Scale canvas
    const rect = canvas.getBoundingClientRect();
    canvas.width  = rect.width * (window.devicePixelRatio || 1);
    canvas.height = rect.height * (window.devicePixelRatio || 1);
    ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
    ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#000';

    function pos(e) {
        const r = canvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        return { x: touch.clientX - r.left, y: touch.clientY - r.top };
    }

    canvas.addEventListener('pointerdown', e => { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('pointermove', e => { if (!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('pointerup', () => { drawing = false; });
    canvas.addEventListener('pointerleave', () => { drawing = false; });

    $('#medFirmaClear').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); });

    $('#medFirmaSaveBtn').addEventListener('click', async () => {
        const dataUri = canvas.toDataURL('image/png');
        // Check if canvas has any strokes
        const blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        if (canvas.toDataURL() === blank.toDataURL()) {
            showToast(t('med_firma_empty'), 'warn');
            return;
        }

        try {
            // 1. Save firma image
            const fRes = await medPost({ action: 'save_firma', firma_data: dataUri });
            if (!fRes.success) { showToast(t('med_error_save'), 'error'); return; }
            const firmaPath = fRes.path;

            // 2. Sign the entity
            let signRes;
            if (entity === 'nota') {
                signRes = await medPost({ action: 'firmar_nota', id: entityId, firma_path: firmaPath });
            } else if (entity === 'enfermeria') {
                signRes = await medPost({ action: 'firmar_enfermeria', id: entityId, firma_path: firmaPath });
            }

            if (signRes && signRes.success) {
                closeSidebar();
                if (entity === 'nota') loadNotas();
                else if (entity === 'enfermeria') loadEnfermeria();
                showToast(t('med_firmado_ok'), 'ok');
            } else {
                showToast(signRes?.message || t('med_error_save'), 'error');
            }
        } catch (_) { showToast(t('med_error_save'), 'error'); }
    });
}

// ═════════════════════════════════════════════════════════════════
// HELPERS
// ═════════════════════════════════════════════════════════════════
async function medFetch(qs) {
    const r = await fetch(`${MED_API}?${qs}`, { credentials: 'same-origin' });
    if (!r.ok) throw new Error(r.statusText);
    return r.json();
}

async function medPost(body) {
    const r = await fetch(MED_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error(r.statusText);
    return r.json();
}

async function medDelete(qs) {
    const r = await fetch(`${MED_API}?${qs}`, { method: 'DELETE', credentials: 'same-origin' });
    if (!r.ok) throw new Error(r.statusText);
    return r.json();
}

function esc(s) { if (s == null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function section(title, inner) { return `<div class="med-hc-section"><div class="med-hc-section-title">${title}</div>${inner}</div>`; }
function row(label, value) { return `<div class="med-hc-row"><span class="med-hc-label">${label}</span><span class="med-hc-value">${esc(value||'')}</span></div>`; }
function setVal(form, name, val) { const el = form.elements[name]; if (el) el.value = val || ''; }
function val(form, name) { return (form.elements[name]?.value || '').trim(); }
function numVal(form, name) { const v = val(form, name); return v === '' ? null : Number(v); }
function chk(form, name) { return form.elements[name]?.checked || false; }

function toggleSpinner(id, show) {
    const el = $('#' + id);
    if (el) el.classList.toggle('active', show);
}

function highlightMatch(text, query) {
    if (!query || query.length < 2) return text;
    const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp('(' + safe + ')', 'gi'), '<mark>$1</mark>');
}

})();
