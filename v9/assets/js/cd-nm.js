// cd-nm.js — Doctor notes (SOAP NOM-004), vitals, sliders, dx search, save/draft
// Extracted from cuidados.php (lines 7138)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// DOCTOR NOTES (Notas de Médico — SOAP NOM-004)
// ═══════════════════════════════════════════════

let _nmPendingAdjuntos = []; // files pending to attach on save
let _nmPendingRecetas = [];  // prescription files pending to attach on save
let _nmFormMode = null;      // null | 'new' | 'edit'
let _nmFormDirty = false;    // track unsaved changes in NM form
let _nmDiagnosticos = [];    // selected CIE-10 codes [{codigo, descripcion}]
let _nmEditExpedienteDocId = null;

/** Format a Date/string into 'YYYY-MM-DDTHH:MM' (local) for <input type="datetime-local">. */
function _nmFmtDateTimeLocal(input) {
    if (!input) return '';
    const d = (input instanceof Date) ? input : new Date(String(input).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/* ── Rich-text helpers for Plan field ──────────────────────────── */
const _nmAllowedTags = ['B','STRONG','I','EM','U','OL','UL','LI','BR','P','DIV','SPAN'];
function nmSanitizeHtml(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    function walk(node) {
        const out = [];
        for (const ch of node.childNodes) {
            if (ch.nodeType === 3) { out.push(ch.textContent); continue; }
            if (ch.nodeType !== 1) continue;
            const tag = ch.tagName;
            if (_nmAllowedTags.includes(tag)) {
                out.push(`<${tag.toLowerCase()}>${walk(ch)}</${tag.toLowerCase()}>`);
            } else {
                out.push(walk(ch));
            }
        }
        return out.join('');
    }
    return walk(doc.body);
}
function nmGetPlanHtml() {
    const el = document.getElementById('cdNmPlan');
    if (!el) return '';
    return nmSanitizeHtml(el.innerHTML).trim();
}
function nmSetPlanHtml(html) {
    const el = document.getElementById('cdNmPlan');
    if (!el) return;
    el.innerHTML = html ? nmSanitizeHtml(html) : '';
}
function nmInitPlanRte() {
    const toolbar = document.getElementById('cdNmPlanToolbar');
    const editor = document.getElementById('cdNmPlan');
    if (!toolbar || !editor) return;
    toolbar.querySelectorAll('[data-cmd]').forEach(btn => {
        btn.addEventListener('mousedown', e => { e.preventDefault(); });
        btn.addEventListener('click', () => {
            editor.focus();
            document.execCommand(btn.dataset.cmd, false, null);
            _nmUpdateToolbarState();
        });
    });
    editor.addEventListener('input', () => { _nmFormDirty = true; });
    editor.addEventListener('keyup', _nmUpdateToolbarState);
    editor.addEventListener('mouseup', _nmUpdateToolbarState);
    // Default: start with ordered list
    if (!editor.innerHTML.trim() || editor.innerHTML === '<br>') {
        editor.innerHTML = '<ol><li><br></li></ol>';
        setTimeout(() => {
            const li = editor.querySelector('li');
            if (li) { const r = document.createRange(); r.setStart(li, 0); r.collapse(true); const s = window.getSelection(); s.removeAllRanges(); s.addRange(r); }
        }, 0);
    }
}
function _nmUpdateToolbarState() {
    const toolbar = document.getElementById('cdNmPlanToolbar');
    if (!toolbar) return;
    toolbar.querySelectorAll('[data-cmd]').forEach(btn => {
        btn.classList.toggle('cd-nm-rte-active', document.queryCommandState(btn.dataset.cmd));
    });
}

/** CIE-10 catalog — geriátric frequent codes */
const CIE10 = [
{c:'A09',d:'Diarrea y gastroenteritis de presunto origen infeccioso'},{c:'A41',d:'Otras septicemias'},{c:'A419',d:'Septicemia, no especificada'},{c:'B37',d:'Candidiasis'},{c:'B86',d:'Escabiosis'},
{c:'C18',d:'Tumor maligno del colon'},{c:'C34',d:'Tumor maligno de bronquios y pulmón'},{c:'C50',d:'Tumor maligno de la mama'},{c:'C61',d:'Tumor maligno de la próstata'},{c:'C67',d:'Tumor maligno de la vejiga'},{c:'C73',d:'Tumor maligno de la glándula tiroides'},{c:'C90',d:'Mieloma múltiple'},
{c:'D50',d:'Anemia por deficiencia de hierro'},{c:'D509',d:'Anemia por deficiencia de hierro, no especificada'},{c:'D64',d:'Otras anemias'},{c:'D649',d:'Anemia, no especificada'},
{c:'E039',d:'Hipotiroidismo, no especificado'},{c:'E05',d:'Tirotoxicosis (hipertiroidismo)'},{c:'E10',d:'Diabetes mellitus tipo 1'},{c:'E11',d:'Diabetes mellitus tipo 2'},{c:'E110',d:'DM2 con coma'},{c:'E112',d:'DM2 con complicaciones renales'},{c:'E113',d:'DM2 con complicaciones oftálmicas'},{c:'E114',d:'DM2 con complicaciones neurológicas'},{c:'E115',d:'DM2 con complicaciones circulatorias periféricas'},{c:'E119',d:'DM2 sin complicaciones'},{c:'E440',d:'Desnutrición proteicocalórica moderada'},{c:'E441',d:'Desnutrición proteicocalórica leve'},{c:'E46',d:'Desnutrición proteicocalórica, no especificada'},{c:'E55',d:'Deficiencia de vitamina D'},{c:'E559',d:'Deficiencia de vitamina D, no especificada'},{c:'E66',d:'Obesidad'},{c:'E669',d:'Obesidad, no especificada'},{c:'E780',d:'Hipercolesterolemia pura'},{c:'E785',d:'Hiperlipidemia no especificada'},{c:'E86',d:'Depleción del volumen (deshidratación)'},{c:'E87',d:'Trastornos de líquidos, electrolitos y equilibrio ácido-básico'},
{c:'F00',d:'Demencia en enfermedad de Alzheimer'},{c:'F01',d:'Demencia vascular'},{c:'F019',d:'Demencia vascular, no especificada'},{c:'F02',d:'Demencia en otras enfermedades'},{c:'F03',d:'Demencia, no especificada'},{c:'F05',d:'Delirium no inducido por sustancias'},{c:'F051',d:'Delirium superpuesto a demencia'},{c:'F059',d:'Delirium, no especificado'},{c:'F32',d:'Episodio depresivo'},{c:'F329',d:'Episodio depresivo, no especificado'},{c:'F33',d:'Trastorno depresivo recurrente'},{c:'F41',d:'Otros trastornos de ansiedad'},{c:'F410',d:'Trastorno de pánico'},{c:'F411',d:'Trastorno de ansiedad generalizada'},{c:'F419',d:'Trastorno de ansiedad, no especificado'},{c:'F51',d:'Trastornos no orgánicos del sueño'},
{c:'G20',d:'Enfermedad de Parkinson'},{c:'G25',d:'Otros trastornos extrapiramidales y del movimiento'},{c:'G30',d:'Enfermedad de Alzheimer'},{c:'G300',d:'Alzheimer de comienzo temprano'},{c:'G301',d:'Alzheimer de comienzo tardío'},{c:'G309',d:'Alzheimer, no especificada'},{c:'G31',d:'Otras enfermedades degenerativas del SN'},{c:'G35',d:'Esclerosis múltiple'},{c:'G40',d:'Epilepsia'},{c:'G45',d:'Ataques de isquemia cerebral transitoria'},{c:'G47',d:'Trastornos del sueño'},{c:'G470',d:'Insomnio'},{c:'G473',d:'Apnea del sueño'},{c:'G62',d:'Otras polineuropatías'},{c:'G629',d:'Polineuropatía, no especificada'},{c:'G81',d:'Hemiplejía'},{c:'G82',d:'Paraplejía y tetraplejía'},
{c:'H25',d:'Catarata senil'},{c:'H353',d:'Degeneración macular'},{c:'H40',d:'Glaucoma'},{c:'H54',d:'Ceguera y disminución de agudeza visual'},{c:'H810',d:'Enfermedad de Ménière'},{c:'H811',d:'Vértigo paroxístico benigno'},{c:'H90',d:'Hipoacusia conductiva y neurosensorial'},{c:'H919',d:'Hipoacusia, no especificada'},
{c:'I10',d:'Hipertensión esencial (primaria)'},{c:'I11',d:'Enfermedad cardíaca hipertensiva'},{c:'I20',d:'Angina de pecho'},{c:'I21',d:'Infarto agudo de miocardio'},{c:'I25',d:'Enfermedad isquémica crónica del corazón'},{c:'I42',d:'Miocardiopatía'},{c:'I48',d:'Fibrilación y aleteo auricular'},{c:'I489',d:'Fibrilación auricular, no especificada'},{c:'I50',d:'Insuficiencia cardíaca'},{c:'I500',d:'Insuficiencia cardíaca congestiva'},{c:'I509',d:'Insuficiencia cardíaca, no especificada'},{c:'I61',d:'Hemorragia intraencefálica'},{c:'I63',d:'Infarto cerebral'},{c:'I64',d:'Accidente vascular encefálico agudo'},{c:'I67',d:'Otras enfermedades cerebrovasculares'},{c:'I69',d:'Secuelas de enfermedad cerebrovascular'},{c:'I70',d:'Aterosclerosis'},{c:'I80',d:'Flebitis y tromboflebitis'},{c:'I83',d:'Venas varicosas de miembros inferiores'},
{c:'J06',d:'Infecciones agudas de vías respiratorias superiores'},{c:'J15',d:'Neumonía bacteriana'},{c:'J18',d:'Neumonía, organismo no especificado'},{c:'J189',d:'Neumonía, no especificada'},{c:'J44',d:'EPOC'},{c:'J449',d:'EPOC, no especificada'},{c:'J45',d:'Asma'},{c:'J69',d:'Neumonitis por aspiración'},{c:'J690',d:'Neumonía por aspiración de alimento'},{c:'J96',d:'Insuficiencia respiratoria'},
{c:'K21',d:'Enfermedad del reflujo gastroesofágico'},{c:'K25',d:'Úlcera gástrica'},{c:'K29',d:'Gastritis y duodenitis'},{c:'K56',d:'Íleo paralítico y obstrucción intestinal'},{c:'K57',d:'Enfermedad diverticular del intestino'},{c:'K590',d:'Estreñimiento'},{c:'K74',d:'Fibrosis y cirrosis del hígado'},{c:'K80',d:'Colelitiasis'},{c:'K922',d:'Hemorragia gastrointestinal'},
{c:'L03',d:'Celulitis'},{c:'L30',d:'Otras dermatitis'},{c:'L89',d:'Úlcera por presión (decúbito)'},{c:'L890',d:'Úlcera por presión, estadio I'},{c:'L891',d:'Úlcera por presión, estadio II'},{c:'L892',d:'Úlcera por presión, estadio III'},{c:'L893',d:'Úlcera por presión, estadio IV'},{c:'L97',d:'Úlcera de miembro inferior'},
{c:'M05',d:'Artritis reumatoide seropositiva'},{c:'M10',d:'Gota'},{c:'M15',d:'Poliartrosis'},{c:'M16',d:'Coxartrosis (cadera)'},{c:'M17',d:'Gonartrosis (rodilla)'},{c:'M19',d:'Otras artrosis'},{c:'M199',d:'Artrosis, no especificada'},{c:'M47',d:'Espondilosis'},{c:'M545',d:'Lumbago no especificado'},{c:'M625',d:'Desgaste y atrofia muscular'},{c:'M80',d:'Osteoporosis con fractura patológica'},{c:'M81',d:'Osteoporosis sin fractura'},{c:'M819',d:'Osteoporosis, no especificada'},
{c:'N17',d:'Insuficiencia renal aguda'},{c:'N18',d:'Insuficiencia renal crónica'},{c:'N185',d:'ERC, estadio 5'},{c:'N189',d:'Insuficiencia renal crónica, no especificada'},{c:'N30',d:'Cistitis'},{c:'N390',d:'Infección de vías urinarias'},{c:'N40',d:'Hiperplasia de la próstata'},
{c:'R05',d:'Tos'},{c:'R10',d:'Dolor abdominal y pélvico'},{c:'R11',d:'Náusea y vómito'},{c:'R13',d:'Disfagia'},{c:'R15',d:'Incontinencia fecal'},{c:'R260',d:'Marcha atáxica'},{c:'R268',d:'Otras anormalidades de la marcha'},{c:'R296',d:'Tendencia a caer'},{c:'R32',d:'Incontinencia urinaria'},{c:'R42',d:'Mareo y desvanecimiento'},{c:'R52',d:'Dolor, no clasificado en otra parte'},{c:'R54',d:'Senilidad / fragilidad por la edad'},{c:'R55',d:'Síncope y colapso'},{c:'R630',d:'Anorexia'},{c:'R634',d:'Pérdida anormal de peso'},{c:'R64',d:'Caquexia'},
{c:'S720',d:'Fractura de cuello de fémur (cadera)'},{c:'S82',d:'Fractura de pierna/tobillo'},{c:'T81',d:'Complicaciones de procedimientos'},{c:'W19',d:'Caída no especificada'},
{c:'Z74',d:'Necesidad de asistencia para el cuidado personal'},{c:'Z75',d:'Problemas relacionados con instalaciones médicas'},{c:'Z93',d:'Aberturas artificiales (ostomías)'},{c:'Z96',d:'Presencia de implantes funcionales'},{c:'Z99',d:'Dependencia de máquinas y dispositivos'},
];

/** Parse contenido: v2 JSON (SOAP) or legacy plain text */
function nmParseContenido(raw) {
    if (!raw) return { v: 1, plan: '' };
    if (typeof raw === 'object' && raw.v === 2) return raw;
    try {
        const p = JSON.parse(raw);
        if (p && p.v === 2) return p;
    } catch(e) {}
    return { v: 1, plan: raw };
}

/** Check if vital sign is out of normal range */
function nmVitalStatus(key, val) {
    const v = parseFloat(val);
    if (isNaN(v)) return '';
    const ranges = {
        ta_sys: [90, 140], ta_dia: [60, 90],
        fc: [60, 100], fr: [12, 20], temp: [36, 37.5],
        spo2: [92, 100], glucosa: [70, 140],
    };
    const r = ranges[key];
    if (!r) return '';
    if (v < r[0]) return 'low';
    if (v > r[1]) return 'high';
    return '';
}

/** Render vitals as a compact table */
function nmRenderVitals(sv) {
    if (!sv) return '';
    const rows = [];
    if (sv.ta_sys || sv.ta_dia) {
        const sysS = nmVitalStatus('ta_sys', sv.ta_sys);
        const diaS = nmVitalStatus('ta_dia', sv.ta_dia);
        const flag = sysS || diaS;
        rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>', label: 'TA', val: (sv.ta_sys||'?')+'/'+(sv.ta_dia||'?'), unit: 'mmHg', flag});
    }
    if (sv.fc) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>', label: 'FC', val: sv.fc, unit: 'lpm', flag: nmVitalStatus('fc', sv.fc)});
    if (sv.fr) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 16s2-2 4-2 4 4 8 0 4-2 4-2"/><path d="M2 10s2-2 4-2 4 4 8 0 4-2 4-2"/></svg>', label: 'FR', val: sv.fr, unit: 'rpm', flag: nmVitalStatus('fr', sv.fr)});
    if (sv.temp) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>', label: 'Temp', val: sv.temp, unit: '°C', flag: nmVitalStatus('temp', sv.temp)});
    if (sv.spo2) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>', label: 'SpO₂', val: sv.spo2, unit: '%', flag: nmVitalStatus('spo2', sv.spo2)});
    if (sv.peso) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M12 7V4"/></svg>', label: t('nm_sv_peso'), val: sv.peso, unit: 'kg', flag: ''});
    if (sv.glucosa) rows.push({icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>', label: t('nm_sv_glucosa'), val: sv.glucosa, unit: 'mg/dL', flag: nmVitalStatus('glucosa', sv.glucosa)});
    if (!rows.length) return '';
    let html = `<table class="cd-nm-sv-table"><tbody>`;
    for (const r of rows) {
        const cls = r.flag ? ` cd-nm-sv-${r.flag}` : '';
        html += `<tr class="cd-nm-sv-row${cls}"><td class="cd-nm-sv-icon">${r.icon}</td><td class="cd-nm-sv-label">${r.label}</td><td class="cd-nm-sv-val">${esc(String(r.val))}</td><td class="cd-nm-sv-unit">${esc(r.unit)}</td></tr>`;
    }
    html += `</tbody></table>`;
    return html;
}

/** Render SOAP sections in a card (read-only) */
function nmRenderSections(parsed) {
    let html = '';
    const sv = parsed.signos_vitales || {};
    const hasVitals = sv.ta_sys || sv.ta_dia || sv.fc || sv.fr || sv.temp || sv.spo2 || sv.peso || sv.glucosa;
    const vitalsHtml = hasVitals
        ? `<div class="cd-nm-section"><div class="cd-nm-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div class="cd-nm-section-content"><div class="cd-nm-section-label">${t('nm_sv_title')}</div>${nmRenderVitals(sv)}</div></div>`
        : '';
    // SOAP text sections
    const soapIcons = {
        subjetivo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        objetivo:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>',
        analisis:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        plan:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    };
    const sections = [
        ['subjetivo', t('nm_subjetivo')],
        ['objetivo', t('nm_objetivo')],
        ['analisis', t('nm_analisis')],
        ['plan', t('nm_plan')],
    ];
    for (const [key, label] of sections) {
        const val = parsed[key];
        if (val && val.trim()) {
            const body = key === 'plan' ? `<div class="cd-nm-section-body cd-nm-plan-body">${nmSanitizeHtml(val)}</div>` : `<div class="cd-nm-section-body">${esc(val)}</div>`;
            html += `<div class="cd-nm-section"><div class="cd-nm-section-icon">${soapIcons[key] || ''}</div><div class="cd-nm-section-content"><div class="cd-nm-section-label">${label}</div>${body}</div></div>`;
        }
    }
    // CIE-10 diagnosticos
    const dx = parsed.diagnosticos;
    if (Array.isArray(dx) && dx.length) {
        html += `<div class="cd-nm-section"><div class="cd-nm-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div class="cd-nm-section-content"><div class="cd-nm-section-label">${t('nm_diagnosticos')}</div><div class="cd-nm-dx-chips">`;
        html += dx.map(d => `<span class="cd-nm-dx-chip"><strong>${esc(d.codigo)}</strong> ${esc(d.descripcion)}</span>`).join('');
        html += `</div></div></div>`;
    }
    html += vitalsHtml;
    return html;
}

/** Render adjuntos as full-width cards */
function nmRenderAdjuntos(adjuntos) {
    if (!adjuntos || !adjuntos.length) return '';
    return `<div class="cd-nm-adjuntos">${adjuntos.map((a, i) => {
        const isImg = (a.tipo || '').startsWith('image/');
        const isPdf = (a.tipo || '') === 'application/pdf';
        const url = BASE + '/' + (a.url || '');
        const thumb = isImg
            ? `<img class="cd-nm-adj-thumb" src="${esc(url)}" alt="${esc(a.nombre)}" loading="lazy">`
            : isPdf
                ? `<div class="cd-nm-adj-thumb cd-nm-adj-thumb-pdf"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg><span>PDF</span></div>`
                : `<div class="cd-nm-adj-thumb cd-nm-adj-thumb-file"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>`;
        return `<div class="cd-nm-adj-card" data-nm-adj-idx="${i}" data-nm-adj-url="${esc(a.url || '')}" data-nm-adj-tipo="${esc(a.tipo || '')}" data-nm-adj-nombre="${esc(a.nombre || '')}" title="${esc(a.nombre)}">${thumb}<span class="cd-nm-adj-name">${esc(a.nombre)}</span></div>`;
    }).join('')}</div>`;
}

/** Handle click on NM adjunto chip — always inline viewer */
function nmHandleAdjuntoClick(chip) {
    const url = BASE + '/' + (chip.dataset.nmAdjUrl || '');
    const tipo = chip.dataset.nmAdjTipo || '';
    const nombre = chip.dataset.nmAdjNombre || '';

    if (tipo.startsWith('image/')) {
        if (typeof window._openImageLightbox === 'function') {
            window._openImageLightbox(url, nombre);
        } else {
            // Fallback: simple image overlay
            const ov = document.createElement('div');
            ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;cursor:pointer';
            ov.innerHTML = `<img src="${esc(url)}" style="max-width:92vw;max-height:90vh;border-radius:8px" alt="">`;
            ov.addEventListener('click', () => ov.remove());
            document.addEventListener('keydown', function h(e){ if(e.key==='Escape'){ ov.remove(); document.removeEventListener('keydown',h); }});
            document.body.appendChild(ov);
        }
    } else if (tipo.includes('pdf')) {
        if (typeof window._openPdfViewer === 'function') {
            window._openPdfViewer(url);
        } else if (typeof _showPdfOverlay === 'function') {
            _showPdfOverlay(url, false);
        } else {
            // Fallback: embed in overlay
            const ov = document.createElement('div');
            ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;padding:20px';
            ov.innerHTML = `<iframe src="${esc(url)}" style="width:90vw;height:90vh;border:none;border-radius:8px;background:#fff"></iframe>`;
            ov.addEventListener('click', e => { if(e.target===ov) ov.remove(); });
            document.addEventListener('keydown', function h(e){ if(e.key==='Escape'){ ov.remove(); document.removeEventListener('keydown',h); }});
            document.body.appendChild(ov);
        }
    } else {
        // Other file types: download
        const a = document.createElement('a');
        a.href = url; a.download = nombre || 'archivo'; a.click();
    }
}

// Delegate click on .cd-nm-adjuntos
document.addEventListener('click', function(e) {
    const card = e.target.closest('.cd-nm-adj-card, .cd-nm-adj-chip');
    if (card && card.dataset.nmAdjUrl) {
        e.preventDefault();
        nmHandleAdjuntoClick(card);
    }
});

function renderNotasMedico() {
    const currentEl = document.getElementById('cdNmCurrent');
    const formEl    = document.getElementById('cdNmFormWrap');
    const archiveEl = document.getElementById('cdNmArchive');
    if (!currentEl) return;

    const currentResidentId = parseInt(_residenteId) || 0;
    const n = (_notaMedico && (parseInt(_notaMedico.residente_id) || 0) === currentResidentId) ? _notaMedico : null;
    if (_notaMedico && !n) _notaMedico = null;
    _nmFormMode = null;
    _nmPendingAdjuntos = [];
    _nmDiagnosticos = [];

    // Load doctor alerts (async, renders independently)
    nmLoadAlertas();

    // ─── Nota vigente (read-only card) ──────────────────────────────
    if (n) {
        const parsed = nmParseContenido(n.contenido);
        const noteDt = n.creado_at ? fmtDateTime(n.creado_at) : { date:'', time:'' };
        const dateStr = noteDt.date;
        const timeStr = noteDt.time;
        const updStr  = (n.updated_at && n.updated_at !== n.creado_at)
            ? ` <span class="cd-nm-card-edited">· editada</span>` : '';
        const isAuthor = parseInt(n.usuario_id) === CURRENT_USER_ID;
        const isSuperadmin = (typeof USER_ROLE !== 'undefined') && USER_ROLE === 'superadmin';
        const canEditNote = isSuperadmin || (IS_DOCTOR && isAuthor);
        const canDeleteNote = isSuperadmin || (IS_DOCTOR && isAuthor);
        const _nmLock = (cond, msg) => cond ? '' : `data-cd-locked data-lock-title="Permiso insuficiente" data-lock-msg="${msg}"`;
        const deleteBtn = `<button ${_nmLock(canDeleteNote, 'Solo el médico autor o un superadministrador puede eliminar notas médicas.')} class="cd-nm-btn-delete ${!canDeleteNote ? 'cd-role-locked' : ''}" data-perm-id="nm_delete_note_btn" onclick="nmDelete(${n.id})" title="${t('nm_delete') || 'Eliminar'}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                ${t('nm_delete') || 'Eliminar'}
            </button>`;
        const actions = `<div class="cd-nm-card-actions">
            <button ${_nmLock(canEditNote, 'Solo el médico autor o un superadministrador puede editar esta nota.')} class="cd-nm-btn-edit ${!canEditNote ? 'cd-role-locked' : ''}" data-perm-id="nm_edit_note_btn" onclick="nmShowForm('edit')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                ${t('nm_edit')}
            </button>
            <button ${_nmLock(IS_DOCTOR, 'Solo los médicos pueden archivar notas clínicas.')} class="cd-nm-btn-archive ${!IS_DOCTOR ? 'cd-role-locked' : ''}" data-perm-id="nm_archive_note_btn" onclick="nmArchive(${n.id})">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                ${t('nm_archive_btn')}
            </button>
            ${deleteBtn}
        </div>`;

        currentEl.innerHTML = `
            <div class="cd-nm-card">
                <div class="cd-nm-card-header">
                    <div class="cd-nm-card-doctor">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>${esc(n.medico_nombre || 'Médico')}</span>
                    </div>
                    <span class="cd-nm-card-date">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        ${dateStr} · ${timeStr}${updStr}
                    </span>
                </div>
                ${nmRenderSections(parsed)}
                ${nmRenderAdjuntos(n.adjuntos)}
                ${actions}
            </div>`;
    } else {
        currentEl.innerHTML = `
            <div class="cd-nm-empty">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <path d="M9 14h6"/><path d="M9 18h4"/>
                </svg>
                <strong>${t('nm_no_current')}</strong>
                ${t('nm_no_current_desc')}
            </div>`;
    }

    // ─── Action buttons (only doctors) ──────────────────────────────
    if (IS_DOCTOR) {
        formEl.innerHTML = `
            <div class="cd-nm-action-bar" id="cdNmActionBar">
                <button class="cd-nm-action-btn" data-perm-id="nm_new_note_btn" onclick="nmShowForm('new')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    ${t('nm_new')}
                </button>
            </div>
            <div class="cd-nm-form" id="cdNmForm">
                <p class="cd-nm-form-title" id="cdNmFormTitle">${t('nm_new')}</p>
                <!-- Fecha y hora editable de la nota -->
                <div class="cd-nm-field">
                    <label for="cdNmCreadoAt">${t('nm_creado_at') || 'Fecha y hora'}</label>
                    <input type="datetime-local" id="cdNmCreadoAt" class="cd-input" step="60">
                </div>
                <!-- Signos vitales (cd-vitals-grid) -->
                <p class="cd-nm-form-title" style="font-size:.65rem;margin:0 0 6px;color:var(--cd-text-muted)">${t('nm_sv_title')}</p>
                <div class="cd-vitals-grid cd-nm-vitals">
                    <div class="cd-vital-card cd-vital-off" data-nmv="ta">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_ta')}</h4>
                        <div class="cd-vital-value"><span id="vNmTa">120/80</span><span class="cd-vital-unit">mmHg</span></div>
                        <div><span class="cd-vital-status" id="vNmTaStatus"></span></div>
                        <div class="cd-nm-vital-inputs">
                            <div class="cd-nm-ta-group">
                                <input type="number" id="cdNmTaSys" class="cd-nm-vital-input" placeholder="120" min="40" max="300" inputmode="numeric">
                                <span class="cd-nm-ta-sep">/</span>
                                <input type="number" id="cdNmTaDia" class="cd-nm-vital-input" placeholder="80" min="20" max="200" inputmode="numeric">
                            </div>
                        </div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="fc">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_fc')}</h4>
                        <div class="cd-vital-value"><span id="vNmFc">72</span><span class="cd-vital-unit">lpm</span></div>
                        <div><span class="cd-vital-status" id="vNmFcStatus"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmFc" class="cd-nm-vital-input" placeholder="72" min="20" max="300" inputmode="numeric"></div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="fr">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_fr')}</h4>
                        <div class="cd-vital-value"><span id="vNmFr">18</span><span class="cd-vital-unit">rpm</span></div>
                        <div><span class="cd-vital-status" id="vNmFrStatus"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmFr" class="cd-nm-vital-input" placeholder="18" min="4" max="80" inputmode="numeric"></div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="temp">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_temp')}</h4>
                        <div class="cd-vital-value"><span id="vNmTemp">36.5</span><span class="cd-vital-unit">°C</span></div>
                        <div><span class="cd-vital-status" id="vNmTempStatus"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmTemp" class="cd-nm-vital-input" placeholder="36.5" step="0.1" min="30" max="45" inputmode="decimal"></div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="spo2">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_spo2')}</h4>
                        <div class="cd-vital-value"><span id="vNmSpo2">95</span><span class="cd-vital-unit">%</span></div>
                        <div><span class="cd-vital-status" id="vNmSpo2Status"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmSpo2" class="cd-nm-vital-input" placeholder="95" min="50" max="100" inputmode="numeric"></div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="peso">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_peso')}</h4>
                        <div class="cd-vital-value"><span id="vNmPeso">68</span><span class="cd-vital-unit">kg</span></div>
                        <div><span class="cd-vital-status" id="vNmPesoStatus"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmPeso" class="cd-nm-vital-input" placeholder="68" step="0.1" min="10" max="300" inputmode="decimal"></div>
                    </div>
                    <div class="cd-vital-card cd-vital-off" data-nmv="glucosa">
                        <label class="cd-vital-switch"><input type="checkbox" class="cd-vital-toggle"><span class="cd-vital-slider"></span></label>
                        <h4>${t('nm_sv_glucosa')}</h4>
                        <div class="cd-vital-value"><span id="vNmGlucosa">110</span><span class="cd-vital-unit">mg/dL</span></div>
                        <div><span class="cd-vital-status" id="vNmGlucosaStatus"></span></div>
                        <div class="cd-nm-vital-inputs"><input type="number" id="cdNmGlucosa" class="cd-nm-vital-input" placeholder="110" min="10" max="900" inputmode="numeric"></div>
                    </div>
                </div>
                <!-- SOAP -->
                <div class="cd-nm-field"><label>${t('nm_subjetivo')}</label><textarea id="cdNmSubjetivo" placeholder="${t('nm_subjetivo_hint')}"></textarea></div>
                <div class="cd-nm-field"><label>${t('nm_objetivo')}</label><textarea id="cdNmObjetivo" placeholder="${t('nm_objetivo_hint')}"></textarea></div>
                <div class="cd-nm-field">
                    <label>${t('nm_analisis')}</label>
                    <div class="cd-nm-dx-wrap">
                        <div class="cd-nm-dx-input-wrap">
                            <input type="text" id="cdNmDxSearch" class="cd-nm-dx-search" placeholder="${t('nm_dx_search_hint')}" autocomplete="off">
                            <div class="cd-nm-dx-dropdown" id="cdNmDxDropdown"></div>
                        </div>
                        <div class="cd-nm-dx-tags" id="cdNmDxTags"></div>
                    </div>
                    <textarea id="cdNmAnalisis" placeholder="${t('nm_analisis_hint')}" style="margin-top:8px"></textarea>
                </div>
                <div class="cd-nm-field"><label>${t('nm_plan')}</label>
                    <div class="cd-nm-rte-toolbar" id="cdNmPlanToolbar">
                        <button type="button" data-cmd="bold" title="Negrita"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 4h8a4 4 0 0 1 0 8H6z"/><path d="M6 12h9a4 4 0 0 1 0 8H6z"/></svg></button>
                        <button type="button" data-cmd="italic" title="Cursiva"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg></button>
                        <button type="button" data-cmd="underline" title="Subrayado"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg></button>
                        <span class="cd-nm-rte-sep"></span>
                        <button type="button" data-cmd="insertOrderedList" title="Lista numerada" class="cd-nm-rte-active"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="3" y="7" font-size="7" fill="currentColor" stroke="none" font-weight="700">1</text><text x="3" y="13" font-size="7" fill="currentColor" stroke="none" font-weight="700">2</text><text x="3" y="19" font-size="7" fill="currentColor" stroke="none" font-weight="700">3</text></svg></button>
                        <button type="button" data-cmd="insertUnorderedList" title="Lista con viñetas"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg></button>
                    </div>
                    <div id="cdNmPlan" class="cd-nm-plan-rte" contenteditable="true" data-placeholder="${t('nm_plan_hint')}"></div>
                </div>
                <!-- Adjuntos: Receta + Archivos generales en columnas -->
                <div class="cd-nm-attach-cols">
                    <div class="cd-nm-field cd-nm-attach-col">
                        <label>${t('nm_attach_rx')}</label>
                        <div class="cd-nm-file-area cd-nm-file-area-sm cd-nm-rx-area" id="cdNmRxArea">
                            <input type="file" id="cdNmRxInput" multiple accept="image/*,.pdf" style="display:none">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                <line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>
                            </svg>
                            <p class="cd-nm-rx-label">${t('nm_rx_hint')}</p>
                            <button type="button" class="cd-nm-file-pick cd-nm-rx-pick" id="cdNmRxPick" data-perm-id="nm_pick_rx_file_btn">${t('nm_btn_pick_rx')}</button>
                        </div>
                        <div class="cd-nm-attach-list" id="cdNmRxList"></div>
                    </div>
                    <div class="cd-nm-field cd-nm-attach-col">
                        <label>${t('nm_attach')}</label>
                        <div class="cd-nm-file-area cd-nm-file-area-sm" id="cdNmFileArea">
                            <input type="file" id="cdNmFileInput" multiple accept="image/*,.pdf" style="display:none">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p style="font-size:.72rem;color:var(--cd-text-muted);margin:2px 0 0">${t('nm_drop_hint')}</p>
                            <button type="button" class="cd-nm-file-pick" id="cdNmFilePick" data-perm-id="nm_pick_attach_file_btn">${t('nm_btn_pick')}</button>
                        </div>
                        <div class="cd-nm-attach-list" id="cdNmAttachList"></div>
                    </div>
                </div>
                <div class="cd-nm-form-actions">
                    <button type="button" onclick="nmHideForm()">${t('nm_cancel')}</button>
                    <button type="button" class="cd-nm-btn-draft" data-perm-id="nm_save_draft_btn" onclick="nmSaveDraft()">${t('nm_draft')}</button>
                    <button type="button" class="cd-nm-btn-save" id="cdNmSaveBtn" data-perm-id="nm_save_note_btn">${t('nm_save')}</button>
                </div>
            </div>`;

        // CIE-10 autocomplete
        const dxSearch = document.getElementById('cdNmDxSearch');
        const dxDrop = document.getElementById('cdNmDxDropdown');
        if (dxSearch && dxDrop) {
            dxSearch.addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();
                if (q.length < 2) { dxDrop.innerHTML = ''; dxDrop.classList.remove('show'); return; }
                const matches = CIE10.filter(e =>
                    e.c.toLowerCase().includes(q) || e.d.toLowerCase().includes(q)
                ).slice(0, 12);
                if (!matches.length) { dxDrop.innerHTML = '<div class="cd-nm-dx-item cd-nm-dx-empty">Sin resultados</div>'; dxDrop.classList.add('show'); return; }
                dxDrop.innerHTML = matches.map(e =>
                    `<div class="cd-nm-dx-item" data-code="${esc(e.c)}" data-desc="${esc(e.d)}"><strong>${esc(e.c)}</strong> ${esc(e.d)}</div>`
                ).join('');
                dxDrop.classList.add('show');
            });
            dxDrop.addEventListener('click', function(e) {
                const item = e.target.closest('.cd-nm-dx-item[data-code]');
                if (!item) return;
                const code = item.dataset.code, desc = item.dataset.desc;
                if (!_nmDiagnosticos.find(d => d.codigo === code)) {
                    _nmDiagnosticos.push({ codigo: code, descripcion: desc });
                    nmRenderDxTags();
                }
                dxSearch.value = '';
                dxDrop.innerHTML = '';
                dxDrop.classList.remove('show');
            });
            dxSearch.addEventListener('blur', () => setTimeout(() => dxDrop.classList.remove('show'), 200));
            dxSearch.addEventListener('focus', function() { if (this.value.trim().length >= 2) this.dispatchEvent(new Event('input')); });
        }

        // Vital card toggles + display sync (number inputs are user-editable)
        $$('.cd-vital-card[data-nmv]', document.getElementById('cdNmForm')).forEach(card => {
            const toggle = card.querySelector('.cd-vital-toggle');
            const inputs = card.querySelectorAll('.cd-nm-vital-input');
            if (toggle) {
                toggle.addEventListener('change', () => {
                    card.classList.toggle('cd-vital-off', !toggle.checked);
                    _nmSyncVitalCards();
                    _nmFormDirty = true;
                });
            }
            // Each number input → updates display and re-evaluates status
            inputs.forEach(inp => {
                inp.addEventListener('input', () => {
                    // Auto-enable card when a value is entered
                    if (toggle && !toggle.checked && inp.value !== '') {
                        toggle.checked = true;
                        card.classList.remove('cd-vital-off');
                    }
                    _nmSyncVitalCards();
                    _nmFormDirty = true;
                });
            });
        });

        // Track dirty on SOAP textareas
        ['cdNmSubjetivo','cdNmObjetivo','cdNmAnalisis','cdNmPlan'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => { _nmFormDirty = true; });
        });

        // Drag & drop file area (general attachments)
        const fileArea = document.getElementById('cdNmFileArea');
        const fileInput = document.getElementById('cdNmFileInput');
        const filePick = document.getElementById('cdNmFilePick');
        if (fileArea && fileInput) {
            filePick?.addEventListener('click', () => fileInput.click());
            fileArea.addEventListener('dragover', e => { e.preventDefault(); fileArea.classList.add('dragover'); });
            fileArea.addEventListener('dragleave', () => fileArea.classList.remove('dragover'));
            fileArea.addEventListener('drop', e => { e.preventDefault(); fileArea.classList.remove('dragover'); if (e.dataTransfer.files.length) nmUploadFiles(e.dataTransfer.files); });
            fileInput.addEventListener('change', function() { if (this.files.length) nmUploadFiles(this.files); this.value = ''; });
        }

        // Drag & drop file area (prescriptions / recetas)
        const rxArea = document.getElementById('cdNmRxArea');
        const rxInput = document.getElementById('cdNmRxInput');
        const rxPick = document.getElementById('cdNmRxPick');
        if (rxArea && rxInput) {
            rxPick?.addEventListener('click', () => rxInput.click());
            rxArea.addEventListener('dragover', e => { e.preventDefault(); rxArea.classList.add('dragover'); });
            rxArea.addEventListener('dragleave', () => rxArea.classList.remove('dragover'));
            rxArea.addEventListener('drop', e => { e.preventDefault(); rxArea.classList.remove('dragover'); if (e.dataTransfer.files.length) nmUploadRxFiles(e.dataTransfer.files); });
            rxInput.addEventListener('change', function() { if (this.files.length) nmUploadRxFiles(this.files); this.value = ''; });
        }

        document.getElementById('cdNmSaveBtn')?.addEventListener('click', () => nmSave());
    } else {
        formEl.innerHTML = `<p style="text-align:center;font-size:.75rem;color:var(--cd-text-muted);padding:8px 0">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            ${t('nm_only_doctor')}</p>`;
    }

    // ─── Toggle historial ───────────────────────────────────────────
    archiveEl.innerHTML = `
        <button class="cd-nm-archive-toggle" id="cdNmArchiveToggle">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            ${t('nm_view_archived')}
        </button>
        <div class="cd-nm-archive-list" id="cdNmArchiveList">
            <p style="color:var(--cd-text-muted);font-size:.75rem;text-align:center;padding:16px 0">${t('nm_archive_empty')}</p>
        </div>`;
    document.getElementById('cdNmArchiveToggle')?.addEventListener('click', function() {
        this.classList.toggle('open');
        const list = document.getElementById('cdNmArchiveList');
        const showing = list.classList.toggle('show');
        const textNode = this.childNodes[this.childNodes.length - 1];
        if (textNode) textNode.textContent = showing ? ` ${t('nm_hide_archived')}` : ` ${t('nm_view_archived')}`;
        if (showing) nmLoadArchive();
    });
}

async function nmLoadCurrentForResident() {
    const currentResidentId = parseInt(_residenteId) || 0;
    if (!currentResidentId) {
        _notaMedico = null;
        renderNotasMedico();
        return;
    }
    if (_notaMedico && (parseInt(_notaMedico.residente_id) || 0) !== currentResidentId) {
        _notaMedico = null;
        renderNotasMedico();
    }
    try {
        const data = await api(`${API_URL}?notas_medico=1&residente_id=${currentResidentId}`);
        if ((parseInt(_residenteId) || 0) !== currentResidentId) return;
        const vigente = data.vigente || null;
        _notaMedico = (vigente && (parseInt(vigente.residente_id) || 0) === currentResidentId) ? vigente : null;
        nmUpdateAlertBadge();
        renderNotasMedico();
    } catch (e) {
        if ((parseInt(_residenteId) || 0) === currentResidentId) renderNotasMedico();
    }
}

/** Helper: build multipart auth headers (without Content-Type, browser sets boundary) */
function api_headers_multipart() {
    const h = {};
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) h['X-CSRF-Token'] = token;
    return h;
}

/** Show the SOAP form */
function nmShowForm(mode) {
    const form = document.getElementById('cdNmForm');
    const title = document.getElementById('cdNmFormTitle');
    const bar = document.getElementById('cdNmActionBar');
    if (!form) return;

    _nmFormMode = mode;

    if (mode === 'edit' && _notaMedico) {
        _nmEditExpedienteDocId = parseInt(_notaMedico.__expediente_doc_id) || null;
        title.textContent = t('nm_edit');
        const parsed = nmParseContenido(_notaMedico.contenido);
        const sv = parsed.signos_vitales || {};
        // Fill vitals
        const setVal = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
        // Fecha/hora: del registro existente
        const dtEl = document.getElementById('cdNmCreadoAt');
        if (dtEl) dtEl.value = _nmFmtDateTimeLocal(_notaMedico.creado_at);
        setVal('cdNmTaSys', sv.ta_sys);   setVal('cdNmTaDia', sv.ta_dia);
        setVal('cdNmFc', sv.fc);          setVal('cdNmFr', sv.fr);
        setVal('cdNmTemp', sv.temp);      setVal('cdNmSpo2', sv.spo2);
        setVal('cdNmPeso', sv.peso);      setVal('cdNmGlucosa', sv.glucosa);
        // Fill SOAP
        setVal('cdNmSubjetivo', parsed.subjetivo || '');
        setVal('cdNmObjetivo', parsed.objetivo || '');
        setVal('cdNmAnalisis', parsed.analisis || '');
        nmSetPlanHtml(parsed.plan || '');
        // Fill diagnosticos
        _nmDiagnosticos = Array.isArray(parsed.diagnosticos) ? [...parsed.diagnosticos] : [];
        nmRenderDxTags();
        // Fill existing adjuntos + recetas
        _nmPendingAdjuntos = [...(_notaMedico.adjuntos || [])];
        _nmPendingRecetas = [...(_notaMedico.recetas || [])];
        nmRenderAttachList();
        nmRenderRxList();
        _nmSyncVitalCards();
        // Auto-toggle cards that have values (edit mode only)
        _nmAutoToggleCards();
    } else {
        _nmEditExpedienteDocId = null;
        title.textContent = t('nm_new');
        // Default: ahora
        const dtEl = document.getElementById('cdNmCreadoAt');
        if (dtEl) dtEl.value = _nmFmtDateTimeLocal(new Date());
        // Pre-fill with normal values
        const defaults = { cdNmTaSys:'120', cdNmTaDia:'80', cdNmFc:'72', cdNmFr:'18', cdNmTemp:'36.5', cdNmSpo2:'95', cdNmPeso:'68', cdNmGlucosa:'110' };
        Object.entries(defaults).forEach(([id, v]) => {
            const el = document.getElementById(id); if (el) el.value = v;
        });
        ['cdNmSubjetivo','cdNmObjetivo','cdNmAnalisis'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        nmSetPlanHtml('');
        _nmDiagnosticos = [];
        nmRenderDxTags();
        _nmPendingAdjuntos = [];
        _nmPendingRecetas = [];
        nmRenderAttachList();
        nmRenderRxList();

        // Try restore draft
        const draft = _nmLoadDraft();
        if (draft) {
            if (draft.vitals) Object.entries(draft.vitals).forEach(([id, v]) => {
                const el = document.getElementById(id); if (el) el.value = v;
            });
            if (draft.subjetivo) { const el = document.getElementById('cdNmSubjetivo'); if (el) el.value = draft.subjetivo; }
            if (draft.objetivo)  { const el = document.getElementById('cdNmObjetivo');  if (el) el.value = draft.objetivo; }
            if (draft.analisis)  { const el = document.getElementById('cdNmAnalisis');  if (el) el.value = draft.analisis; }
            if (draft.plan)      { nmSetPlanHtml(draft.plan); }
            if (draft.diagnosticos?.length) { _nmDiagnosticos = [...draft.diagnosticos]; nmRenderDxTags(); }
            if (draft.adjuntos?.length) { _nmPendingAdjuntos = [...draft.adjuntos]; nmRenderAttachList(); }
            if (draft.recetas?.length)  { _nmPendingRecetas  = [...draft.recetas];  nmRenderRxList(); }
            _nmSyncVitalCards();
            // Restore toggle states
            if (draft.toggles) {
                document.querySelectorAll('#cdNmForm .cd-vital-card[data-nmv]').forEach(card => {
                    const key = card.dataset.nmv;
                    const on = !!draft.toggles[key];
                    const toggle = card.querySelector('.cd-vital-toggle');
                    if (toggle) toggle.checked = on;
                    card.classList.toggle('cd-vital-off', !on);
                });
            }
            showToast(t('nm_draft_restored'), 'info');
        } else {
            _nmSyncVitalCards();
            // All toggles OFF by default — vitals pre-filled but card inactive
            document.querySelectorAll('#cdNmForm .cd-vital-card[data-nmv]').forEach(card => {
                const toggle = card.querySelector('.cd-vital-toggle');
                if (toggle) toggle.checked = false;
                card.classList.add('cd-vital-off');
            });
        }
    }

    _nmFormDirty = false;
    form.classList.add('show');
    if (bar) bar.style.display = 'none';
    // Hide current note + archive while editing to reduce confusion
    const currentEl = document.getElementById('cdNmCurrent');
    const archiveEl = document.getElementById('cdNmArchive');
    if (currentEl) currentEl.style.display = 'none';
    if (archiveEl) archiveEl.style.display = 'none';
    // Show a "Ver historial" link below the form
    let histLink = document.getElementById('cdNmHistLink');
    if (!histLink) {
        histLink = document.createElement('div');
        histLink.id = 'cdNmHistLink';
        histLink.className = 'cd-nm-hist-link';
        histLink.innerHTML = `<a href="#" onclick="document.getElementById('cdNmCurrent').style.display='';document.getElementById('cdNmArchive').style.display='';this.parentElement.remove();return false"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ${t('nm_view_archived')}</a>`;
        form.parentElement.appendChild(histLink);
    }
    // Auto-grow SOAP textareas (not Plan — it's a contenteditable div)
    ['cdNmSubjetivo','cdNmObjetivo','cdNmAnalisis'].forEach(id => {
        const ta = document.getElementById(id);
        if (!ta) return;
        ta.style.overflow = 'hidden';
        const grow = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
        ta.removeEventListener('input', ta._nmGrow);
        ta._nmGrow = grow;
        ta.addEventListener('input', grow);
        grow();
    });
    nmInitPlanRte();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Hide the form */
function nmHideForm() {
    const form = document.getElementById('cdNmForm');
    const bar = document.getElementById('cdNmActionBar');
    if (form) form.classList.remove('show');
    if (bar) bar.style.display = '';
    // Restore current note + archive visibility
    const currentEl = document.getElementById('cdNmCurrent');
    const archiveEl = document.getElementById('cdNmArchive');
    if (currentEl) currentEl.style.display = '';
    if (archiveEl) archiveEl.style.display = '';
    // Remove hist link if present
    const histLink = document.getElementById('cdNmHistLink');
    if (histLink) histLink.remove();
    _nmFormMode = null;
    _nmFormDirty = false;
    _nmEditExpedienteDocId = null;
    _nmPendingAdjuntos = [];
    _nmPendingRecetas = [];
}

/** Draft key for sessionStorage (per-resident) */
function _nmDraftKey() { return 'geriapp_nm_draft_' + (_residenteId || 0); }

/** Save current NM form state as draft in sessionStorage */
function nmSaveDraft() {
    const vitals = {};
    ['cdNmTaSys','cdNmTaDia','cdNmFc','cdNmFr','cdNmTemp','cdNmSpo2','cdNmPeso','cdNmGlucosa'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) vitals[id] = el.value;
    });
    // Save toggle states
    const toggles = {};
    document.querySelectorAll('#cdNmForm .cd-vital-card[data-nmv]').forEach(card => {
        const t = card.querySelector('.cd-vital-toggle');
        if (t) toggles[card.dataset.nmv] = t.checked;
    });
    const draft = {
        vitals,
        toggles,
        subjetivo: document.getElementById('cdNmSubjetivo')?.value || '',
        objetivo:  document.getElementById('cdNmObjetivo')?.value || '',
        analisis:  document.getElementById('cdNmAnalisis')?.value || '',
        plan:      nmGetPlanHtml(),
        diagnosticos: _nmDiagnosticos.length ? [..._nmDiagnosticos] : [],
        adjuntos: _nmPendingAdjuntos.length ? [..._nmPendingAdjuntos] : [],
        recetas:  _nmPendingRecetas.length  ? [..._nmPendingRecetas]  : [],
        ts: Date.now()
    };
    try { sessionStorage.setItem(_nmDraftKey(), JSON.stringify(draft)); } catch(e) {}
    _nmFormDirty = false;
    showToast(t('nm_draft_saved'), 'success');
}

/** Load draft from sessionStorage, returns object or null */
function _nmLoadDraft() {
    try {
        const raw = sessionStorage.getItem(_nmDraftKey());
        if (!raw) return null;
        return JSON.parse(raw);
    } catch(e) { return null; }
}

/** Clear draft from sessionStorage */
function nmClearDraft() {
    try { sessionStorage.removeItem(_nmDraftKey()); } catch(e) {}
}

/** Sync vital card display (values, sliders, badges) — does NOT auto-toggle */
function _nmSyncVitalCards() {
    const form = document.getElementById('cdNmForm');
    if (!form) return;
    const map = { ta:['cdNmTaSys','cdNmTaDia'], fc:['cdNmFc'], fr:['cdNmFr'], temp:['cdNmTemp'], spo2:['cdNmSpo2'], peso:['cdNmPeso'], glucosa:['cdNmGlucosa'] };
    const ranges = {
        ta:      {normal:[90,140],warn:[80,160]},
        ta_dia:  {normal:[60,90],warn:[50,100]},
        fc:      {normal:[60,100],warn:[50,120]},
        fr:      {normal:[12,20],warn:[10,25]},
        temp:    {normal:[36,37.5],warn:[35.5,38]},
        spo2:    {normal:[95,100],warn:[90,100]},
        peso:    {normal:[40,120],warn:[30,150]},
        glucosa: {normal:[70,140],warn:[54,180]},
    };
    form.querySelectorAll('.cd-vital-card[data-nmv]').forEach(card => {
        const key = card.dataset.nmv;
        const ids = map[key] || [];
        const toggle = card.querySelector('.cd-vital-toggle');
        const isOn = toggle ? toggle.checked : false;
        // Update display
        const displaySpan = card.querySelector('.cd-vital-value span');
        if (displaySpan) {
            if (key === 'ta') {
                const s = document.getElementById('cdNmTaSys')?.value || '—';
                const d = document.getElementById('cdNmTaDia')?.value || '—';
                displaySpan.textContent = (s !== '—' || d !== '—') ? s + '/' + d : '—';
            } else {
                const inp = document.getElementById(ids[0]);
                displaySpan.textContent = inp?.value || '—';
            }
        }
        // Sync ALL sliders from their linked hidden inputs (legacy — sliders removed)
        // card.querySelectorAll('.cd-nm-range').forEach(slider => {
        //     const targetInp = document.getElementById(slider.dataset.nmr);
        //     if (targetInp && targetInp.value) slider.value = targetInp.value;
        // });
        // Update status badge (only visible when toggle is on)
        const st = card.querySelector('.cd-vital-status');
        if (st && ranges[key]) {
            const r = ranges[key];
            let val, cls = '', txt = '';
            if (key === 'ta') {
                const sysVal = parseFloat(document.getElementById('cdNmTaSys')?.value || '');
                const diaVal = parseFloat(document.getElementById('cdNmTaDia')?.value || '');
                const rDia = ranges.ta_dia;
                // Evaluate each, take worst
                const evalRange = (v, rng) => {
                    if (isNaN(v)) return 0; // no data
                    if (v >= rng.normal[0] && v <= rng.normal[1]) return 1;
                    if (v >= rng.warn[0] && v <= rng.warn[1]) return 2;
                    return 3;
                };
                const sysLevel = evalRange(sysVal, r);
                const diaLevel = evalRange(diaVal, rDia);
                const worst = Math.max(sysLevel, diaLevel);
                if (!isOn || worst === 0) { cls = ''; txt = ''; }
                else if (worst === 1) { cls = 'normal'; txt = 'Normal'; }
                else if (worst === 2) { cls = 'warning'; txt = 'Precaución'; }
                else { cls = 'danger'; txt = 'Anormal'; }
            } else {
                val = parseFloat(document.getElementById(ids[0])?.value || '');
                if (!isOn || isNaN(val)) { cls = ''; txt = ''; }
                else if (val >= r.normal[0] && val <= r.normal[1]) { cls = 'normal'; txt = 'Normal'; }
                else if (val >= r.warn[0] && val <= r.warn[1]) { cls = 'warning'; txt = 'Precaución'; }
                else { cls = 'danger'; txt = 'Anormal'; }
            }
            st.className = 'cd-vital-status' + (cls ? ' ' + cls : '');
            st.textContent = txt;
        }
    });
}

/** Auto-toggle cards that have values (used when loading edit mode) */
function _nmAutoToggleCards() {
    const form = document.getElementById('cdNmForm');
    if (!form) return;
    const map = { ta:['cdNmTaSys','cdNmTaDia'], fc:['cdNmFc'], fr:['cdNmFr'], temp:['cdNmTemp'], spo2:['cdNmSpo2'], peso:['cdNmPeso'], glucosa:['cdNmGlucosa'] };
    form.querySelectorAll('.cd-vital-card[data-nmv]').forEach(card => {
        const ids = map[card.dataset.nmv] || [];
        const hasValue = ids.some(id => { const el = document.getElementById(id); return el && el.value; });
        const toggle = card.querySelector('.cd-vital-toggle');
        if (toggle) toggle.checked = hasValue;
        card.classList.toggle('cd-vital-off', !hasValue);
    });
}

/** Upload files via API (used by file input change and drag & drop) */
async function nmUploadFiles(files) {
    const formData = new FormData();
    for (const f of files) formData.append('nm_adjuntos[]', f);
    try {
        const resp = await fetch(API_URL, { method: 'POST', headers: api_headers_multipart(), body: formData });
        const data = await resp.json();
        if (data.success && data.data?.adjuntos) {
            _nmPendingAdjuntos.push(...data.data.adjuntos);
            nmRenderAttachList();
            showToast(t('nm_files_uploaded'), 'success');
        } else { showToast(data.message || t('nm_upload_error'), 'error'); }
    } catch(e) { showToast(t('nm_upload_error'), 'error'); }
}

/** Render CIE-10 diagnostic tags in form */
function nmRenderDxTags() {
    const container = document.getElementById('cdNmDxTags');
    if (!container) return;
    if (!_nmDiagnosticos.length) { container.innerHTML = ''; return; }
    container.innerHTML = _nmDiagnosticos.map((d, i) =>
        `<span class="cd-nm-dx-tag"><strong>${esc(d.codigo)}</strong> ${esc(d.descripcion)} <span class="cd-nm-dx-tag-x" onclick="nmRemoveDx(${i})">&times;</span></span>`
    ).join('');
}

function nmRemoveDx(idx) {
    _nmDiagnosticos.splice(idx, 1);
    nmRenderDxTags();
}

/** Render pending attach list in form */
function nmRenderAttachList() {
    const list = document.getElementById('cdNmAttachList');
    if (!list) return;
    if (!_nmPendingAdjuntos.length) { list.innerHTML = ''; return; }
    list.innerHTML = _nmPendingAdjuntos.map((a, i) => {
        const isImg = (a.tipo || '').startsWith('image/');
        const icon = isImg
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        return `<div class="cd-nm-attach-item">${icon} ${esc(a.nombre)} <span class="nm-remove" onclick="nmRemoveAttach(${i})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></div>`;
    }).join('');
}

function nmRemoveAttach(idx) {
    _nmPendingAdjuntos.splice(idx, 1);
    nmRenderAttachList();
}

/** Upload prescription files */
async function nmUploadRxFiles(files) {
    const formData = new FormData();
    for (const f of files) formData.append('nm_adjuntos[]', f);
    try {
        const resp = await fetch(API_URL, { method: 'POST', headers: api_headers_multipart(), body: formData });
        const data = await resp.json();
        if (data.success && data.data?.adjuntos) {
            _nmPendingRecetas.push(...data.data.adjuntos);
            nmRenderRxList();
            showToast(t('nm_files_uploaded'), 'success');
            _nmFormDirty = true;
        } else { showToast(data.message || t('nm_upload_error'), 'error'); }
    } catch(e) { showToast(t('nm_upload_error'), 'error'); }
}

/** Render prescription attach list */
function nmRenderRxList() {
    const list = document.getElementById('cdNmRxList');
    if (!list) return;
    if (!_nmPendingRecetas.length) { list.innerHTML = ''; return; }
    list.innerHTML = _nmPendingRecetas.map((a, i) => {
        const isImg = (a.tipo || '').startsWith('image/');
        const icon = isImg
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        return `<div class="cd-nm-attach-item">${icon} ${esc(a.nombre)} <span class="nm-remove" onclick="nmRemoveRx(${i})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></div>`;
    }).join('');
}

function nmRemoveRx(idx) {
    _nmPendingRecetas.splice(idx, 1);
    nmRenderRxList();
}

async function nmLoadArchive() {
    const list = document.getElementById('cdNmArchiveList');
    const currentResidentId = parseInt(_residenteId) || 0;
    if (!list || !currentResidentId) return;
    list.innerHTML = skeleton(2);
    try {
        const data = await api(`${API_URL}?notas_medico=1&residente_id=${currentResidentId}`);
        if ((parseInt(_residenteId) || 0) !== currentResidentId) return;
        const archivo = (data.archivo || []).filter(a => (parseInt(a.residente_id) || 0) === currentResidentId);
        if (!archivo.length) {
            list.innerHTML = `<p style="color:var(--cd-text-muted);font-size:.75rem;text-align:center;padding:16px 0">${t('nm_archive_empty')}</p>`;
            return;
        }
        list.innerHTML = archivo.map(a => {
            const dt = fmtDateTime(a.archivada_at || a.creado_at || '');
            const dStr = [dt.date, dt.time].filter(Boolean).join(' ');
            const parsed = nmParseContenido(a.contenido);
            const body = nmRenderSections(parsed);
            const adj = nmRenderAdjuntos(a.adjuntos);
            const isSuperadmin = (typeof USER_ROLE !== 'undefined') && USER_ROLE === 'superadmin';
            const isAuthor = parseInt(a.usuario_id) === CURRENT_USER_ID;
            const canDeleteNote = isSuperadmin || (IS_DOCTOR && isAuthor);
            const canMakeCurrent = isSuperadmin || (IS_DOCTOR && isAuthor);
            const _nmLockA = (cond, msg) => cond ? '' : `data-cd-locked data-lock-title="Permiso insuficiente" data-lock-msg="${msg}"`;
            const expandBtn = body ? `<button type="button" class="cd-nm-archive-expand" onclick="nmToggleArchivedCard(this)" aria-expanded="false">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                <span>Ver completa</span>
            </button>` : '';
            const makeCurrentBtn = `<button class="cd-nm-btn-edit${!canMakeCurrent ? ' cd-role-locked' : ''}" data-perm-id="nm_restore_archived_note_btn" style="margin-top:8px" onclick="nmMakeCurrent(${a.id})" ${_nmLockA(canMakeCurrent, 'Solo el médico autor o un superadministrador puede restaurar notas archivadas.')}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                Hacer vigente
            </button>`;
            const delBtn = `<button class="cd-nm-btn-delete${!canDeleteNote ? ' cd-role-locked' : ''}" data-perm-id="nm_delete_archived_note_btn" style="margin-top:8px" onclick="nmDelete(${a.id})" ${_nmLockA(canDeleteNote, 'Solo el médico autor o un superadministrador puede eliminar notas médicas.')}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                ${t('nm_delete') || 'Eliminar'}
            </button>`;
            const actionBtns = `<div class="cd-nm-card-actions" style="justify-content:flex-start;margin-top:8px">${makeCurrentBtn}${delBtn}</div>`;
            return `<div class="cd-nm-archived-card">
                <div class="cd-nm-archived-card-head">
                    <span>${t('nm_by')} ${esc(a.medico_nombre || 'Médico')}</span>
                    <span>${dStr}</span>
                </div>
                <div class="cd-nm-archived-card-body clamped">${body}</div>
                ${expandBtn}
                ${adj}
                ${actionBtns}
            </div>`;
        }).join('');
    } catch (e) {
        list.innerHTML = `<p style="color:var(--cd-text-muted);font-size:.75rem;text-align:center;padding:16px 0">Error al cargar</p>`;
    }
}

function nmToggleArchivedCard(btn) {
    const card = btn?.closest?.('.cd-nm-archived-card');
    if (!card) return;
    const expanded = card.classList.toggle('is-expanded');
    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const label = btn.querySelector('span');
    if (label) label.textContent = expanded ? 'Contraer' : 'Ver completa';
}

async function nmSave() {
    // Build SOAP JSON
    const getVal = id => (document.getElementById(id)?.value || '').trim();
    const signos_vitales = {};
    const signosVitalesActivos = [];
    const svCards = {
        ta: [['ta_sys', 'cdNmTaSys'], ['ta_dia', 'cdNmTaDia']],
        fc: [['fc', 'cdNmFc']],
        fr: [['fr', 'cdNmFr']],
        temp: [['temp', 'cdNmTemp']],
        spo2: [['spo2', 'cdNmSpo2']],
        peso: [['peso', 'cdNmPeso']],
        glucosa: [['glucosa', 'cdNmGlucosa']],
    };
    for (const [cardKey, fields] of Object.entries(svCards)) {
        const card = document.querySelector(`#cdNmForm .cd-vital-card[data-nmv="${cardKey}"]`);
        const isOn = !!card?.querySelector('.cd-vital-toggle')?.checked;
        if (!isOn) continue;
        let hasValue = false;
        for (const [fieldKey, id] of fields) {
            const v = getVal(id);
            if (v) {
                signos_vitales[fieldKey] = v;
                hasValue = true;
            }
        }
        if (hasValue) signosVitalesActivos.push(cardKey);
    }
    const subjetivo = getVal('cdNmSubjetivo');
    const objetivo  = getVal('cdNmObjetivo');
    const analisis  = getVal('cdNmAnalisis');
    const plan      = nmGetPlanHtml();

    // Require at least plan
    if (!plan && !subjetivo && !objetivo && !analisis && !Object.keys(signos_vitales).length) {
        document.getElementById('cdNmPlan')?.focus();
        return;
    }

    const contenidoObj = { v: 2, subjetivo, objetivo, analisis, plan };
    if (Object.keys(signos_vitales).length) contenidoObj.signos_vitales = signos_vitales;
    if (_nmDiagnosticos.length) contenidoObj.diagnosticos = _nmDiagnosticos;
    const contenido = JSON.stringify(contenidoObj);
    const editId = (_nmFormMode === 'edit' && _notaMedico) ? _notaMedico.id : null;
    if (!_residenteId) return;

    // Fecha/hora editable por el médico
    const dtVal = (document.getElementById('cdNmCreadoAt')?.value || '').trim();
    const creadoAt = dtVal ? dtVal.replace('T', ' ') + ':00' : null;

    const btn = document.getElementById('cdNmSaveBtn');
    btnLoading(btn, t('status_saving'));
    try {
        const body = editId
            ? { action: 'actualizar_nota_medico', nota_id: editId, residente_id: _residenteId, contenido, signos_vitales_activos: signosVitalesActivos, adjuntos: _nmPendingAdjuntos, recetas: _nmPendingRecetas.length ? _nmPendingRecetas : undefined, crear_expediente: true, expediente_doc_id: _nmEditExpedienteDocId || undefined, creado_at: creadoAt }
            : { action: 'crear_nota_medico', residente_id: _residenteId, contenido, signos_vitales_activos: signosVitalesActivos, adjuntos: _nmPendingAdjuntos, recetas: _nmPendingRecetas.length ? _nmPendingRecetas : undefined, crear_expediente: true, creado_at: creadoAt };

        await api(API_URL, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify(body),
        });
        showToast(editId ? t('nm_updated') : t('nm_saved'), 'success');
        _nmFormDirty = false;
        nmClearDraft();

        // Refresh
        const fresh = await api(`${API_URL}?notas_medico=1&residente_id=${_residenteId}`);
        _notaMedico = fresh.vigente || null;
        nmUpdateAlertBadge();
        nmHideForm();
        renderNotasMedico();
    } catch (e) {}
    btnReset(btn);
}

async function nmArchive(id) {
    const ok = await cdConfirm(t('nm_confirm_archive'), { type: 'warning' });
    if (!ok) return;
    try {
        await api(API_URL, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'archivar_nota_medico', nota_id: id })
        });
        showToast(t('nm_archived'), 'success');
        const fresh = await api(`${API_URL}?notas_medico=1&residente_id=${_residenteId}`);
        _notaMedico = fresh.vigente || null;
        nmUpdateAlertBadge();
        renderNotasMedico();
    } catch (e) {}
}

async function nmMakeCurrent(id) {
    const ok = await cdConfirm('¿Marcar esta nota médica como vigente?', { type: 'warning' });
    if (!ok) return;
    try {
        await api(API_URL, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'hacer_vigente_nota_medico', nota_id: id })
        });
        showToast('Nota médica marcada como vigente', 'success');
        const fresh = await api(`${API_URL}?notas_medico=1&residente_id=${_residenteId}`);
        _notaMedico = fresh.vigente || null;
        nmUpdateAlertBadge();
        renderNotasMedico();
        nmLoadArchive();
    } catch (e) { showToast(e.message || 'Error', 'error'); }
}

async function nmOpenEditorFromNote(note, opts = {}) {
    if (!note || !note.id) return false;
    const editableNote = { ...note };
    if (opts.expedienteDocId) editableNote.__expediente_doc_id = parseInt(opts.expedienteDocId) || null;
    const currentResidentId = parseInt(note.residente_id) || parseInt(_residenteId) || 0;
    if (currentResidentId && currentResidentId !== (parseInt(_residenteId) || 0)) {
        const sel = document.getElementById('cdPatientName');
        if (sel) sel.value = currentResidentId;
        _residenteId = currentResidentId;
    }
    _notaMedico = editableNote;
    if (typeof showView === 'function') showView('viewFormNotasMedico', true, false);
    renderNotasMedico();
    requestAnimationFrame(() => nmShowForm('edit'));
    return true;
}

async function nmDelete(id) {
    const ok = await cdConfirm(t('nm_confirm_delete') || '¿Eliminar permanentemente esta nota médica? También se eliminará del expediente. Esta acción no se puede deshacer.', { type: 'danger' });
    if (!ok) return;
    try {
        await api(API_URL, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'eliminar_nota_medico', nota_id: id })
        });
        showToast(t('nm_deleted') || 'Nota médica eliminada', 'success');
        const fresh = await api(`${API_URL}?notas_medico=1&residente_id=${_residenteId}`);
        _notaMedico = fresh.vigente || null;
        nmUpdateAlertBadge();
        renderNotasMedico();
        nmLoadArchive();
    } catch (e) {}
}

// Expose to global scope (used by onclick in dynamic HTML)
window.nmShowForm     = nmShowForm;
window.nmHideForm     = nmHideForm;
window.nmLoadCurrentForResident = nmLoadCurrentForResident;
window.nmSaveDraft    = nmSaveDraft;
window.nmArchive      = nmArchive;
window.nmMakeCurrent  = nmMakeCurrent;
window.nmOpenEditorFromNote = nmOpenEditorFromNote;
window.nmDelete       = nmDelete;
window.nmRemoveAttach = nmRemoveAttach;
window.nmRemoveRx     = nmRemoveRx;
window.nmRemoveDx     = nmRemoveDx;
// Expose SOAP parsers/renderers so other modules (e.g. expediente) can reuse them
window.nmParseContenido = nmParseContenido;
window.nmRenderSections = nmRenderSections;

// ═══════════════════════════════════════════════
// ALERTAS AL MÉDICO
// ═══════════════════════════════════════════════
let _alertasMedico = [];

async function nmLoadAlertas() {
    if (!_residenteId) return;
    try {
        _alertasMedico = await api(`${API_URL}?alertas_medico=1&residente_id=${_residenteId}`);
    } catch { _alertasMedico = []; }
    nmRenderAlertas();
    nmRenderAlertasHist();
    nmUpdateAlertBadge();
}

function nmRenderAlertas() {
    const el = document.getElementById('cdNmAlertas');
    if (!el) return;
    const pending = Array.isArray(_alertasMedico) ? _alertasMedico.filter(a => !a.visto_por) : [];
    if (!pending.length) { el.innerHTML = ''; return; }

    const catLabels = {
        alimentacion: t('cat_alimentacion'), higiene: t('cat_higiene'), medicacion: t('cat_medicacion'),
        sueno: t('cat_sueno'), comportamiento: t('cat_comportamiento'), signos_vitales: t('cat_signos_vitales'),
        movilidad: t('cat_movilidad'), terapia: t('cat_terapia'), eliminacion: t('cat_eliminacion')
    };

    el.innerHTML = `
        <div class="cd-nm-alertas-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            ${t('alert_doc_pending_title')} <span class="cd-nm-alertas-count">${pending.length}</span>
        </div>
        ${pending.map(a => {
            const dt = a.creado_at ? fmtDateTime(a.creado_at) : { date:'', time:'' };
            const dateStr = dt.date;
            const timeStr = dt.time;
            // Build inline detail from registro datos
            let detailHtml = '';
            if (a.reg_datos && typeof window.buildSummary === 'function') {
                try {
                    detailHtml = window.buildSummary({categoria: a.categoria, datos: a.reg_datos});
                } catch(e) { detailHtml = ''; }
            }
            if (!detailHtml && a.reg_obs) detailHtml = esc(a.reg_obs);
            return `<div class="cd-nm-alert-card" data-alert-id="${a.id}">
                <div class="cd-nm-alert-row">
                    <div class="cd-nm-alert-info">
                        <span class="cd-nm-alert-cat">${esc(catLabels[a.categoria] || a.categoria)}</span>
                        <span class="cd-nm-alert-date">${dateStr} · ${timeStr}${a.reg_hora ? ' · ' + esc(a.reg_hora) : ''}</span>
                    </div>
                    <div class="cd-nm-alert-actions">
                        <button class="cd-nm-alert-view" data-perm-id="nm_view_alert_registro_btn" onclick="nmViewRegistro(${a.registro_id})" title="${t('alert_doc_view')}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="cd-nm-alert-ack" data-perm-id="nm_ack_alert_btn" onclick="nmEnterado(${a.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            ${t('alert_doc_ack')}
                        </button>
                    </div>
                </div>
                ${detailHtml ? `<div class="cd-nm-alert-detail">${detailHtml}</div>` : ''}
                ${a.mensaje ? `<div class="cd-nm-alert-msg">${esc(a.mensaje)}</div>` : ''}
            </div>`;
        }).join('')}`;
}

async function nmEnterado(alertaId) {
    const card = document.querySelector(`.cd-nm-alert-card[data-alert-id="${alertaId}"]`);
    const btn = card?.querySelector('.cd-nm-alert-ack');
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    try {
        await api(API_URL, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'enterado_alerta', alerta_id: alertaId })
        });
        if (card) { card.style.opacity = '0.4'; card.style.pointerEvents = 'none'; }
        // Refresh alerts
        await nmLoadAlertas();
    } catch(e) {
        if (btn) { btn.disabled = false; btn.textContent = t('alert_doc_ack'); }
    }
}

async function nmRenderAlertasHist() {
    const el = document.getElementById('cdNmAlertasHist');
    if (!el || !_residenteId) return;
    try {
        const hist = await api(`${API_URL}?alertas_medico=1&residente_id=${_residenteId}&historial=1`);
        const seen = Array.isArray(hist) ? hist.filter(a => a.visto_por) : [];
        if (!seen.length) { el.innerHTML = ''; return; }

        const catLabels = {
            alimentacion: t('cat_alimentacion'), higiene: t('cat_higiene'), medicacion: t('cat_medicacion'),
            sueno: t('cat_sueno'), comportamiento: t('cat_comportamiento'), signos_vitales: t('cat_signos_vitales'),
            movilidad: t('cat_movilidad'), terapia: t('cat_terapia'), eliminacion: t('cat_eliminacion')
        };

        el.innerHTML = `
            <details class="cd-nm-alertas-hist-details">
                <summary class="cd-nm-alertas-hist-summary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ${t('alert_doc_history')} <span class="cd-nm-alertas-hist-count">${seen.length}</span>
                </summary>
                <div class="cd-nm-alertas-hist-list">
                    ${seen.map(a => {
                        const dt = a.creado_at ? fmtDateTime(a.creado_at) : { date:'', time:'' };
                        const dateStr = dt.date;
                        const timeStr = dt.time;
                        const seenDt = a.visto_at ? fmtDateTime(a.visto_at) : { date:'', time:'' };
                        const vDateStr = seenDt.date;
                        const vTimeStr = seenDt.time;
                        return `<div class="cd-nm-alert-hist-item">
                            <div class="cd-nm-alert-hist-top">
                                <span class="cd-nm-alert-cat">${esc(catLabels[a.categoria]||a.categoria)}</span>
                                <span class="cd-nm-alert-date">${dateStr} · ${timeStr}</span>
                            </div>
                            ${a.mensaje ? `<div class="cd-nm-alert-msg">${esc(a.mensaje)}</div>` : ''}
                            <div class="cd-nm-alert-hist-ack">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                ${t('alert_doc_ack_by')} <strong>${esc(a.doctor_nombre||'—')}</strong> · ${vDateStr} ${vTimeStr}
                            </div>
                        </div>`;
                    }).join('')}
                </div>
            </details>`;
    } catch { el.innerHTML = ''; }
}

function nmUpdateAlertBadge() {
    const pending = Array.isArray(_alertasMedico) ? _alertasMedico.filter(a => !a.visto_por) : [];
    const nmBadge = document.getElementById('cdNmBadge');
    if (!nmBadge) return;
    if (pending.length > 0) {
        nmBadge.classList.add('show');
        nmBadge.textContent = _notaMedico ? `✓ +${pending.length}⚠` : `${pending.length}⚠`;
    } else {
        nmBadge.classList.toggle('show', !!_notaMedico);
        nmBadge.textContent = _notaMedico ? '✓' : '';
    }
}

window.nmEnterado = nmEnterado;

async function nmViewRegistro(registroId) {
    // Try to find in already-loaded records
    let rec = _registros.find(r => r.id === registroId);
    if (!rec) {
        // Fetch from API
        try {
            rec = await api(`${API_URL}?registro_id=${registroId}`);
        } catch { return; }
    }
    if (!rec) return;
    rec.source = rec.source || 'cuidado';
    if (typeof openDetailSidebar === 'function') openDetailSidebar(rec);
}
window.nmViewRegistro = nmViewRegistro;

// ── Report button inside NM view (own sidebar with preview toggle) ───
$('#cdNmReportBtn')?.addEventListener('click', () => {
    if (!_residenteId) return;
    const rName = RESIDENTES.find(r => r.id === _residenteId)?.nombre || t('default_resident');
    const body = `
        <p style="margin:0 0 16px;color:var(--cd-text-muted);font-size:0.8125rem;">${t('report_select_period', {':name': esc(rName)})}</p>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <button class="cd-btn-submit" data-report-period="dia" style="justify-content:center;gap:8px;font-size:0.9rem;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                ${t('report_day')}
            </button>
            <button class="cd-btn-submit cd-btn-secondary" data-report-period="semana" style="justify-content:center;gap:8px;font-size:0.9rem;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="18"/></svg>
                ${t('report_weekly')}
            </button>
            <button class="cd-btn-submit cd-btn-secondary" data-report-period="mes" style="justify-content:center;gap:8px;font-size:0.9rem;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
                ${t('report_monthly')}
            </button>
            <button class="cd-btn-submit cd-btn-secondary" data-report-period="30dias" style="justify-content:center;gap:8px;font-size:0.9rem;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M7 14l3 3 7-7"/></svg>
                ${t('report_last_30')}
            </button>
        </div>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--cd-border,#e2e8f0);">
            <p style="margin:0 0 8px;font-size:0.8125rem;font-weight:600;color:var(--cd-text-secondary,#64748b);">${t('report_custom')}</p>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                <label style="font-size:0.75rem;color:var(--cd-text-muted,#94a3b8);min-width:36px;">${t('report_custom_from')}</label>
                <input type="text" id="cdReportCustomDesde" class="cd-app-date-input" style="flex:1;padding:6px 8px;border:1px solid var(--cd-border,#e2e8f0);border-radius:6px;font-size:0.8125rem;background:var(--cd-bg-card,#fff);color:var(--cd-text-primary,#1e293b);" value="${esc(fmtDate(_fecha))}" data-iso="${esc(_fecha)}" placeholder="${appDatePlaceholder()}" inputmode="numeric">
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                <label style="font-size:0.75rem;color:var(--cd-text-muted,#94a3b8);min-width:36px;">${t('report_custom_to')}</label>
                <input type="text" id="cdReportCustomHasta" class="cd-app-date-input" style="flex:1;padding:6px 8px;border:1px solid var(--cd-border,#e2e8f0);border-radius:6px;font-size:0.8125rem;background:var(--cd-bg-card,#fff);color:var(--cd-text-primary,#1e293b);" value="${esc(fmtDate(_fecha))}" data-iso="${esc(_fecha)}" placeholder="${appDatePlaceholder()}" inputmode="numeric">
            </div>
            <button class="cd-btn-submit cd-btn-secondary" data-report-period="custom" style="justify-content:center;gap:8px;font-size:0.9rem;width:100%;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M16 14l-4 4-4-4"/></svg>
                ${t('report_custom_generate')}
            </button>
        </div>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--cd-border,#e2e8f0)">
            <label style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;color:var(--cd-text-secondary,#64748b);cursor:pointer">
                <label class="cd-toggle cd-toggle-sm"><input type="checkbox" id="cdReportPreviewToggle" checked><span class="cd-toggle-track"></span></label>
                ${t('report_preview_mode')}
            </label>
            <p style="margin:4px 0 0;font-size:0.75rem;color:var(--cd-text-muted,#94a3b8)">${t('report_preview_hint')}</p>
        </div>`;
    openSidebar(t('report_generate_pdf'), body, '');
    initAppDateTextInput(document.getElementById('cdReportCustomDesde'));
    initAppDateTextInput(document.getElementById('cdReportCustomHasta'));
    setTimeout(() => {
        sBody.querySelectorAll('[data-report-period]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const period = btn.dataset.reportPeriod;
                if (period === 'custom') {
                    const desdeIso = appDateInputIso(document.getElementById('cdReportCustomDesde'), { required:true, message:t('report_custom_from') + ': ' + appDatePlaceholder() });
                    const hastaIso = appDateInputIso(document.getElementById('cdReportCustomHasta'), { required:true, message:t('report_custom_to') + ': ' + appDatePlaceholder() });
                    if (!desdeIso || !hastaIso) return;
                    _customDesde = desdeIso;
                    _customHasta = hastaIso;
                    if (_customDesde > _customHasta) {
                        showToast(t('report_custom_invalid_range'), 'error');
                        return;
                    }
                }
                const previewMode = !!document.getElementById('cdReportPreviewToggle')?.checked;
                closeSidebar();
                await _generateReport(period, previewMode);
            });
        });
    }, 50);
});

