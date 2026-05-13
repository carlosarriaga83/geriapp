// cd-ai.js — AI interpretation
// Extracted from cuidados.php (lines 11898)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// AI Interpretation
// ═══════════════════════════════════════════════
const REPORT_API = BASE + '/api/reportes.php';

$$('.cd-ia-role-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!_residenteId) return;
        const role = btn.dataset.role;
        const resultDiv = $('#cdIaResult');

        // Deselect others, mark active
        $$('.cd-ia-role-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="cd-ia-loading"><div class="cd-spinner"></div><span>Generando interpretación¦</span></div>';

        // Build report data text from current stats & registros
        const {desde, hasta} = periodRange(_reportPeriod);
        let registros = [], stats = {};
        try {
            const data = await api(`${API_URL}?residente_id=${_residenteId}&desde=${desde}&hasta=${hasta}`);
            registros = data.registros || [];
            stats = data.stats || {};
        } catch (e) {
            resultDiv.innerHTML = '<p class="cd-ia-error">Error al obtener datos del reporte.</p>';
            return;
        }

        const rName = RESIDENTES.find(r => r.id === _residenteId)?.nombre || t('default_resident');

        // Build text summary
        let reportText = `Residente: ${rName}\nPeríodo: ${fmtDate(desde)} al ${fmtDate(hasta)}\n\n`;
        reportText += `Estadísticas por categoría:\n`;
        Object.entries(CAT_LABELS).forEach(([k, l]) => {
            reportText += `- ${l}: ${stats[k] || 0} registros\n`;
        });

        // Group by category for detailed metrics
        const byCategory = {};
        registros.forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
            if (!byCategory[r.categoria]) byCategory[r.categoria] = [];
            byCategory[r.categoria].push({...d, _obs: r.observaciones || '', _por: r.registrado_por || ''});
        });

        // Add metrics per category
        reportText += `\nMétricas detalladas:\n`;
        if (byCategory.sueno?.length) {
            const hrs = byCategory.sueno.map(d => parseFloat(d.horas)).filter(v => !isNaN(v) && v > 0);
            const cals = byCategory.sueno.map(d => parseInt(d.calidad_pct)).filter(v => !isNaN(v));
            if (hrs.length) reportText += `- Sueño: promedio ${(hrs.reduce((a,b)=>a+b,0)/hrs.length).toFixed(1)}h\n`;
            if (cals.length) reportText += `- Calidad de sueño: promedio ${Math.round(cals.reduce((a,b)=>a+b,0)/cals.length)}%\n`;
        }
        if (byCategory.alimentacion?.length) {
            const pcts = byCategory.alimentacion.map(d => parseInt(d.ingesta_pct)).filter(v => !isNaN(v));
            if (pcts.length) reportText += `- Alimentación: ingesta promedio ${Math.round(pcts.reduce((a,b)=>a+b,0)/pcts.length)}%\n`;
        }
        if (byCategory.signos_vitales?.length) {
            const temps = byCategory.signos_vitales.map(d => parseFloat(d.temperatura)).filter(v => v > 0);
            const fcs = byCategory.signos_vitales.map(d => parseInt(d.frecuencia_cardiaca)).filter(v => v > 0);
            if (temps.length) reportText += `- Temperatura promedio: ${(temps.reduce((a,b)=>a+b,0)/temps.length).toFixed(1)}°C\n`;
            if (fcs.length) reportText += `- FC promedio: ${Math.round(fcs.reduce((a,b)=>a+b,0)/fcs.length)} bpm\n`;
        }
        if (byCategory.eliminacion?.length) {
            const panales = byCategory.eliminacion.filter(d => d.cambio_panal === 'Sí').length;
            reportText += `- Eliminación: ${byCategory.eliminacion.length} eventos`;
            if (panales) reportText += `, ${panales} cambios de pañal`;
            reportText += `\n`;
        }

        // Collect all observations by category
        const allObs = [];
        registros.forEach(r => {
            if (r.observaciones && r.observaciones.trim()) {
                allObs.push({ fecha: r.fecha, cat: CAT_LABELS[r.categoria] || r.categoria, por: r.registrado_por || '?', obs: r.observaciones.trim() });
            }
        });
        if (allObs.length) {
            reportText += `\nObservaciones de cuidadores (${allObs.length}):\n`;
            allObs.forEach(o => {
                reportText += `- [${fmtDate(o.fecha)}] ${o.cat} (${o.por}): ${o.obs}\n`;
            });
        }

        // Add event details (last 20) including observations
        reportText += `\nÚltimos eventos (${Math.min(registros.length, 20)} de ${registros.length}):\n`;
        registros.slice(0, 20).forEach(r => {
            const tmp = document.createElement('span');
            tmp.innerHTML = buildSummary(r);
            let line = `- ${fmtDate(r.fecha)} ${r.hora || ''} | ${CAT_LABELS[r.categoria] || r.categoria} | ${tmp.textContent || ''}`;
            if (r.registrado_por) line += ` | Registrado por: ${r.registrado_por}`;
            if (r.observaciones) line += ` | Obs: ${r.observaciones}`;
            reportText += line + `\n`;
        });

        try {
            const res = await api(REPORT_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'interpretar', role, report_data: reportText, residente_id: _residenteId })
            });
            const text = res.interpretation || '';
            // Render markdown to HTML with proper list/heading/paragraph handling
            const renderMd = (md) => {
                // Strip any remaining # headings and convert to bold section titles
                const lines = md.split('\n');
                let html = '', inUl = false, inOl = false;
                for (let i = 0; i < lines.length; i++) {
                    let ln = lines[i];
                    // Horizontal rule
                    if (/^---+$/.test(ln.trim())) {
                        if (inUl) { html += '</ul>'; inUl = false; }
                        if (inOl) { html += '</ol>'; inOl = false; }
                        html += '<hr>'; continue;
                    }
                    // Headings → convert to styled section dividers (bold + underline)
                    let hm = ln.match(/^(#{1,4})\s+(.+)$/);
                    if (hm) {
                        if (inUl) { html += '</ul>'; inUl = false; }
                        if (inOl) { html += '</ol>'; inOl = false; }
                        const depth = hm[1].length;
                        const title = hm[2].replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/^\*\*|\*\*$/g, '');
                        if (depth <= 2) {
                            html += `<div class="cd-ia-section">${title}</div>`;
                        } else {
                            html += `<div class="cd-ia-subsection">${title}</div>`;
                        }
                        continue;
                    }
                    // Detect bold-only lines as section titles (e.g. **Resumen General**)
                    let boldLine = ln.trim().match(/^\*\*(.+?)\*\*\s*$/);
                    if (boldLine && !ln.trim().startsWith('-') && !ln.trim().match(/^\d+[.)]/)) {
                        if (inUl) { html += '</ul>'; inUl = false; }
                        if (inOl) { html += '</ol>'; inOl = false; }
                        html += `<div class="cd-ia-section">${boldLine[1]}</div>`;
                        continue;
                    }
                    // Unordered list
                    let ulm = ln.match(/^\s*[-•*]\s+(.+)$/);
                    if (ulm) {
                        if (inOl) { html += '</ol>'; inOl = false; }
                        if (!inUl) { html += '<ul>'; inUl = true; }
                        html += `<li>${ulm[1].replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</li>`; continue;
                    }
                    // Ordered list
                    let olm = ln.match(/^\s*\d+[.)\-]\s+(.+)$/);
                    if (olm) {
                        if (inUl) { html += '</ul>'; inUl = false; }
                        if (!inOl) { html += '<ol>'; inOl = true; }
                        html += `<li>${olm[1].replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</li>`; continue;
                    }
                    // Close open lists
                    if (inUl) { html += '</ul>'; inUl = false; }
                    if (inOl) { html += '</ol>'; inOl = false; }
                    // Empty line → spacer
                    if (!ln.trim()) { html += '<div class="cd-ia-spacer"></div>'; continue; }
                    // Paragraph text
                    html += `<p>${ln.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</p>`;
                }
                if (inUl) html += '</ul>';
                if (inOl) html += '</ol>';
                return html;
            };
            const html = renderMd(text);
            const roleNames = { medico: 'Médico', enfermero: 'Cuidador', cuidador: 'Cuidador', familiar: 'Familiar' };
            const roleIcons = { medico: '🩺', enfermero: '⚕️', familiar: '👨‍👩‍👧' };
            resultDiv.innerHTML = `<div class="cd-ia-header">${roleIcons[role] || ''} <strong>Interpretación — ${esc(roleNames[role])}</strong></div><div class="cd-ia-text">${html}</div>`;
        } catch (e) {
            resultDiv.innerHTML = `<p class="cd-ia-error">Error al generar la interpretación: ${esc(e.message || 'Error desconocido')}</p>`;
        }
    });
});

