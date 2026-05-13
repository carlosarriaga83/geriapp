// cd-day-report.js — Day report PDF generation
// Extracted from cuidados.php (lines 11722)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// DAY REPORT PDF (Dashboard / Records toolbar)
// ═══════════════════════════════════════════════
const _onPrintDayReportClick = () => {
    if (!_residenteId) return;
    const rName = RESIDENTES.find(r => r.id === _residenteId)?.nombre || t('default_resident');
    const previewAllowed = CAN_PREVIEW_REPORT;
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
        ${previewAllowed ? `<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--cd-border,#e2e8f0)">
            <label style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;color:var(--cd-text-secondary,#64748b);cursor:pointer">
                <label class="cd-toggle cd-toggle-sm"><input type="checkbox" id="cdReportPreviewToggle" checked><span class="cd-toggle-track"></span></label>
                ${t('report_preview_mode')}
            </label>
            <p style="margin:4px 0 0;font-size:0.75rem;color:var(--cd-text-muted,#94a3b8)">${t('report_preview_hint')}</p>
        </div>` : ''}`;
    openSidebar(t('report_generate_pdf'), body, '');
    initAppDateTextInput(document.getElementById('cdReportCustomDesde'));
    initAppDateTextInput(document.getElementById('cdReportCustomHasta'));
    // Attach period button handlers
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
                const previewMode = previewAllowed && document.getElementById('cdReportPreviewToggle')?.checked;
                closeSidebar();
                await _generateReport(period, previewMode);
            });
        });
    }, 50);
};
document.querySelectorAll('.cd-print-day-report').forEach(btn => {
    btn.addEventListener('click', _onPrintDayReportClick);
});

let _customDesde = '', _customHasta = '';
async function _generateReport(period, previewMode) {
    if (!_residenteId) return;
    const _btn = document.querySelector('.cd-print-day-report.is-loading') || $('#cdPrintDayReport') || document.querySelector('.cd-print-day-report');
    btnLoading(_btn, t('status_generating'));
    showToast(t('toast_generating_pdf'), '');

    const rName = RESIDENTES.find(r => r.id === _residenteId)?.nombre || t('default_resident');

    // Determine date range
    const { desde, hasta } = periodRange(period, _customDesde, _customHasta);
    const periodLabels = { dia: 'Día', semana: 'Semana', mes: 'Mes', '30dias': 'Últimos 30 días', custom: 'Personalizado' };
    const periodLabel = periodLabels[period] || 'Día';
    const dateLabel = period === 'dia' ? fmtDate(_fecha) : `${fmtDate(desde)} — ${fmtDate(hasta)}`;
    const fileName = `reporte_${period}_${rName.replace(/\s/g, '_')}_${desde}.pdf`;

    // Fetch data for the selected period
    let registros, counts, bitacoraEvents, notas;
    if (period === 'dia') {
        // Use already-loaded data
        registros = _registros || [];
        counts = _counts || {};
        bitacoraEvents = _bitacoraEvents || [];
        notas = _notas || [];
    } else {
        try {
            const data = await api(`${API_URL}?residente_id=${_residenteId}&desde=${desde}&hasta=${hasta}`);
            registros = data.registros || [];
            counts = {};
            registros.forEach(r => { counts[r.categoria] = (counts[r.categoria] || 0) + 1; });
            bitacoraEvents = [];
            notas = [];
        } catch (e) {
            showToast(t('error_loading'), 'error');
            btnReset(_btn);
            return;
        }
    }

    // Colors
    const C = {
        primary: [52, 120, 220],
        accent:  [34, 139, 96],
        muted:   [130, 130, 130],
        light:   [245, 245, 245],
        border:  [220, 220, 220],
        admin:   [34, 139, 96],   // green for administered
        pending: [200, 80, 60],   // red-ish for pending
        catColors: {
            sueno:          [99, 102, 241],
            alimentacion:   [245, 158, 11],
            medicacion:     [16, 185, 129],
            higiene:        [59, 130, 246],
            terapia:        [244, 63, 94],
            movilidad:      [139, 92, 246],
            eliminacion:    [107, 114, 128],
            comportamiento: [251, 146, 60],
            signos_vitales: [239, 68, 68],
        }
    };

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        const mg = 12;
        const contentW = pageW - mg * 2;
        let y = 12;

        // ── Top accent bar ──
        doc.setFillColor(...C.primary);
        doc.rect(0, 0, pageW, 1.2, 'F');

        // ── Compact header ──
        doc.setFontSize(15);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(40, 40, 40);
        const titleMap = { dia: t('report_day'), semana: t('report_weekly'), mes: t('report_monthly'), '30dias': t('report_last_30'), custom: t('report_custom') };
        doc.text(titleMap[period] || t('report_day'), mg, y);
        doc.setFontSize(8);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(...C.muted);
        doc.text(dateLabel, pageW - mg, y, { align: 'right' });
        y += 5;
        doc.setFontSize(10);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(50, 50, 50);
        doc.text(rName, mg, y);
        doc.setFontSize(7);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(...C.muted);
        doc.text(`Generado: ${toAppTz(new Date()).toLocaleString(t('locale_code'))}`, pageW - mg, y, { align: 'right' });
        y += 3;
        doc.setDrawColor(...C.primary);
        doc.setLineWidth(0.3);
        doc.line(mg, y, pageW - mg, y);
        y += 4;

        // ══ TWO-COLUMN SECTION ══
        const colGap = 4;
        const colW = (contentW - colGap) / 2;
        const colL = mg;
        const colR = mg + colW + colGap;
        const yStart = y;

        // ── LEFT COLUMN: Resumen de Cuidados (two-sub-column layout) ──
        doc.setFontSize(8);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(40, 40, 40);
        doc.text('Resumen de Cuidados', colL, y);
        y += 1;

        const byCategory = {};
        registros.forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { d = {}; } }
            if (!byCategory[r.categoria]) byCategory[r.categoria] = [];
            byCategory[r.categoria].push(d);
        });

        // Split categories into left and right sub-columns
        const catEntries = Object.entries(CAT_LABELS);
        const leftCats = catEntries.slice(0, 5);  // Sueño, Alimentación, Medicación, Higiene, Terapia
        const rightCats = catEntries.slice(5);     // Movilidad, Eliminación, Comportamiento, Signos Vitales

        const leftRows = leftCats.map(([k, l]) => [l, String(counts[k] || 0)]);
        const rightRows = rightCats.map(([k, l]) => [l, String(counts[k] || 0)]);

        const totalRegs = registros.length;

        // Sub-table widths
        const subColW = (colW - 2) / 2;

        // Left sub-table
        doc.autoTable({
            startY: y,
            head: [['Categoría', '#']],
            body: leftRows,
            theme: 'plain',
            headStyles: { fillColor: false, textColor: [...C.primary], fontStyle: 'bold', fontSize: 6.5, cellPadding: { top: 1, bottom: 1, left: 1.5, right: 1 }, lineWidth: { bottom: 0.2 }, lineColor: C.border },
            bodyStyles: { fontSize: 6.5, cellPadding: { top: 0.8, bottom: 0.8, left: 1.5, right: 1 }, textColor: [50, 50, 50], lineWidth: { bottom: 0.1 }, lineColor: [235, 235, 235] },
            columnStyles: { 0: { cellWidth: 'auto' }, 1: { halign: 'center', cellWidth: 7 } },
            margin: { left: colL, right: pageW - colL - subColW },
            tableWidth: subColW,
        });
        const yLeftSub = doc.lastAutoTable.finalY;

        // Right sub-table
        doc.autoTable({
            startY: y,
            head: [['Categoría', '#']],
            body: rightRows,
            theme: 'plain',
            headStyles: { fillColor: false, textColor: [...C.primary], fontStyle: 'bold', fontSize: 6.5, cellPadding: { top: 1, bottom: 1, left: 1.5, right: 1 }, lineWidth: { bottom: 0.2 }, lineColor: C.border },
            bodyStyles: { fontSize: 6.5, cellPadding: { top: 0.8, bottom: 0.8, left: 1.5, right: 1 }, textColor: [50, 50, 50], lineWidth: { bottom: 0.1 }, lineColor: [235, 235, 235] },
            columnStyles: { 0: { cellWidth: 'auto' }, 1: { halign: 'center', cellWidth: 7 } },
            margin: { left: colL + subColW + 2, right: pageW - colL - colW },
            tableWidth: subColW,
        });
        const yRightSub = doc.lastAutoTable.finalY;

        // Total row spanning full width
        let yAfterStats = Math.max(yLeftSub, yRightSub) + 2;

        // ── Charts: Vital Signs + Sleep (individual chart per sign, below stats, within left column) ──
        const vitalsData = (byCategory['signos_vitales'] || []).map((d, idx) => {
            const reg = registros.filter(r => r.categoria === 'signos_vitales')[idx];
            return {
                hora: reg?.hora || '--:--',
                fecha: reg?.fecha || '',
                temp: parseFloat(d.temperatura) || 0,
                fc: parseInt(d.frecuencia_cardiaca) || 0,
                fr: parseInt(d.frecuencia_respiratoria) || 0,
                spo2: parseInt(d.spo2) || 0,
                pas: parseInt(d.pa_sistolica) || 0,
                pad: parseInt(d.pa_diastolica) || 0,
                gluc: parseInt(d.glucosa) || 0,
            };
        }).filter(v => v.temp > 0 || v.fc > 0 || v.spo2 > 0 || v.pas > 0 || v.gluc > 0)
          .sort((a, b) => (a.fecha + ' ' + a.hora).localeCompare(b.fecha + ' ' + b.hora));

        // Sleep data for chart
        const sleepData = (byCategory['sueno'] || []).map((d, idx) => {
            const reg = registros.filter(r => r.categoria === 'sueno')[idx];
            return {
                hora: reg?.hora || '--:--',
                fecha: reg?.fecha || '',
                horas: parseFloat(d.horas) || 0,
                calidad: parseInt(d.calidad_pct) || 0,
            };
        }).filter(v => v.horas > 0)
          .sort((a, b) => (a.fecha + ' ' + a.hora).localeCompare(b.fecha + ' ' + b.hora));

        const hasChartData = vitalsData.length > 0 || sleepData.length > 0;

        if (hasChartData) {
            doc.setFontSize(7);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(40, 40, 40);
            doc.text('Gráficas', colL, yAfterStats);
            yAfterStats += 3;

            // Define each chart series (vital signs + sleep)
            const allSeries = [
                { key: 'temp', label: 'Temperatura (°C)', color: [239, 68, 68], min: 35, max: 40, normalLo: 36.0, normalHi: 37.4, decimals: 1, src: 'vitals' },
                { key: 'fc', label: 'Frec. Cardíaca (bpm)', color: [52, 120, 220], min: 40, max: 130, normalLo: 60, normalHi: 100, decimals: 0, src: 'vitals' },
                { key: 'fr', label: 'Frec. Respiratoria (rpm)', color: [245, 158, 11], min: 8, max: 30, normalLo: 12, normalHi: 20, decimals: 0, src: 'vitals' },
                { key: 'spo2', label: 'SpO₂ (%)', color: [16, 185, 129], min: 85, max: 100, normalLo: 95, normalHi: 100, decimals: 0, src: 'vitals' },
                { key: 'pas', label: 'P. Arterial (mmHg)', color: [139, 92, 246], min: 60, max: 180, normalLo: 90, normalHi: 130, decimals: 0, extra: 'pad', src: 'vitals' },
                { key: 'gluc', label: 'Glucosa (mg/dL)', color: [234, 88, 12], min: 40, max: 400, normalLo: 70, normalHi: 140, decimals: 0, src: 'vitals' },
                { key: 'horas', label: 'Horas de Sueño', color: [99, 102, 241], min: 0, max: 14, normalLo: 6, normalHi: 9, decimals: 1, src: 'sleep' },
            ];
            const activeSeries = allSeries.filter(s =>
                s.src === 'sleep' ? sleepData.some(v => v[s.key] > 0) : vitalsData.some(v => v[s.key] > 0)
            );

            // Layout: 1 chart per row spanning full column width
            const chartGap = 3;
            const chartsPerRow = 1;
            const singleChartW = colW;
            const singleChartH = 24;

            let _chartBaseY = yAfterStats;
            activeSeries.forEach((s, si) => {
                const row = Math.floor(si / chartsPerRow);
                const colIdx = si % chartsPerRow;
                let cX = colL + colIdx * (singleChartW + chartGap);
                let cY = _chartBaseY + row * (singleChartH + 8);

                // Check page overflow — ensure chart + labels fit above footer
                if (cY + singleChartH + 6 > pageH - 14) {
                    doc.addPage();
                    _chartBaseY = 14 - row * (singleChartH + 8);
                    cY = 14;
                    cX = colL + colIdx * (singleChartW + chartGap);
                }

                const chartData = s.src === 'sleep' ? sleepData : vitalsData;

                // Chart title
                doc.setFontSize(5.5);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(...s.color);
                doc.text(s.label, cX, cY);

                const bY = cY + 2;

                // Chart background
                doc.setFillColor(250, 250, 252);
                doc.setDrawColor(230, 230, 230);
                doc.roundedRect(cX, bY, singleChartW, singleChartH, 1, 1, 'FD');

                const pad = { l: 7, r: 2, t: 3, b: 8 };
                const pX = cX + pad.l;
                const pW = singleChartW - pad.l - pad.r;
                const pY = bY + pad.t;
                const pH = singleChartH - pad.t - pad.b;

                // Normal range band
                const normalLoPct = Math.max(0, Math.min(1, (s.normalLo - s.min) / (s.max - s.min)));
                const normalHiPct = Math.max(0, Math.min(1, (s.normalHi - s.min) / (s.max - s.min)));
                const bandTop = pY + pH - (normalHiPct * pH);
                const bandBot = pY + pH - (normalLoPct * pH);
                doc.setFillColor(220, 240, 220);
                doc.rect(pX, bandTop, pW, bandBot - bandTop, 'F');

                // Y-axis grid
                doc.setDrawColor(235, 235, 240);
                doc.setLineWidth(0.06);
                const gridN = 3;
                for (let g = 0; g <= gridN; g++) {
                    const gy = pY + (pH * g / gridN);
                    doc.line(pX, gy, pX + pW, gy);
                    const val = s.max - ((s.max - s.min) * g / gridN);
                    doc.setFontSize(4);
                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(160, 160, 160);
                    doc.text(String(Math.round(val)), pX - 1, gy + 0.7, { align: 'right' });
                }

                // Points
                const nPts = chartData.length;
                const xStep = nPts > 1 ? pW / (nPts - 1) : 0;
                const points = [];
                chartData.forEach((v, i) => {
                    const val = v[s.key];
                    if (val <= 0) return;
                    const x = nPts > 1 ? pX + i * xStep : pX + pW / 2;
                    const pct = Math.max(0, Math.min(1, (val - s.min) / (s.max - s.min)));
                    const py = pY + pH - (pct * pH);
                    const isAbnormal = val < s.normalLo || val > s.normalHi;
                    points.push({ x, y: py, val, hora: v.hora, fecha: v.fecha, abnormal: isAbnormal });

                    // For PA, also store diastolic
                    if (s.extra === 'pad' && v.pad > 0) {
                        const pctD = Math.max(0, Math.min(1, (v.pad - s.min) / (s.max - s.min)));
                        const pyD = pY + pH - (pctD * pH);
                        points.push({ x, y: pyD, val: v.pad, hora: v.hora, fecha: v.fecha, abnormal: v.pad < 60 || v.pad > 85, isDiastolic: true });
                    }
                });

                // Draw lines (only between main points, not diastolic)
                const mainPts = points.filter(p => !p.isDiastolic);
                doc.setDrawColor(...s.color);
                doc.setLineWidth(0.3);
                for (let p = 1; p < mainPts.length; p++) {
                    doc.line(mainPts[p - 1].x, mainPts[p - 1].y, mainPts[p].x, mainPts[p].y);
                }

                // Diastolic lines for PA
                if (s.extra === 'pad') {
                    const diaPts = points.filter(p => p.isDiastolic);
                    doc.setDrawColor(139, 92, 246);
                    doc.setLineWidth(0.15);
                    for (let p = 1; p < diaPts.length; p++) {
                        doc.line(diaPts[p - 1].x, diaPts[p - 1].y, diaPts[p].x, diaPts[p].y);
                    }
                }

                // Dots + value labels
                points.forEach(pt => {
                    const dotColor = pt.abnormal ? [220, 50, 50] : s.color;
                    doc.setFillColor(...dotColor);
                    doc.circle(pt.x, pt.y, pt.isDiastolic ? 0.4 : 0.55, 'F');
                    doc.setFontSize(3.8);
                    doc.setFont(undefined, pt.abnormal ? 'bold' : 'normal');
                    doc.setTextColor(...dotColor);
                    const valStr = s.decimals > 0 ? pt.val.toFixed(s.decimals) : String(Math.round(pt.val));
                    doc.text(valStr, pt.x, pt.y - 1.2, { align: 'center' });
                });

                // X-axis time labels (rotated -45°)
                const _mAbr = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                // Smart label skipping: show max ~6 labels per chart to avoid overlap
                const visibleIdxs = [];
                chartData.forEach((v, i) => { if (v[s.key] > 0) visibleIdxs.push(i); });
                const maxLabels = 6;
                const skipEvery = visibleIdxs.length > maxLabels ? Math.ceil(visibleIdxs.length / maxLabels) : 1;

                chartData.forEach((v, i) => {
                    if (v[s.key] <= 0) return;
                    const visIdx = visibleIdxs.indexOf(i);
                    // Always show first and last; skip intermediate labels for dense data
                    if (visIdx > 0 && visIdx < visibleIdxs.length - 1 && visIdx % skipEvery !== 0) return;

                    const x = nPts > 1 ? pX + i * xStep : pX + pW / 2;
                    let lbl;
                    if (period !== 'dia' && v.fecha) {
                        const _fp = v.fecha.split('-');
                        const _dd = parseInt(_fp[2]);
                        const _mm = parseInt(_fp[1]) - 1;
                        const _yy = _fp[0].substring(2);
                        lbl = _dd + '-' + _mAbr[_mm] + '-' + _yy + ' ' + v.hora.substring(0, 5);
                    } else {
                        lbl = v.hora.substring(0, 5);
                    }
                    doc.setFontSize(3.2);
                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(140, 140, 140);
                    doc.text(lbl, x + 0.5, bY + singleChartH - pad.b + 1, { angle: -90 });
                });
            });

            // Advance Y past all chart rows
            const totalRows = Math.ceil(activeSeries.length / chartsPerRow);
            yAfterStats = _chartBaseY + totalRows * (singleChartH + 8) + 2;
        }

        const yLeftEnd = yAfterStats;

        // ── RIGHT COLUMN: Medicación Prescrita ──
        let yR = yStart;
        doc.setFontSize(8);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(40, 40, 40);
        doc.text('Medicación Prescrita', colR, yR);
        yR += 1;

        // Gather prescriptions for this resident today
        const rxArr = (RX_BY_RES[_residenteId] || []).filter(rx => {
            if (parseInt(rx.activo) === 0) return false;
            if (rx.inicio && rx.inicio > _fecha) return false;
            if (rx.fin && rx.fin < _fecha) return false;
            return true;
        });
        const administered = getAdministeredMedTimes();

        // Agrupar por medicamento (nombre)
        const medsByName = {};
        rxArr.forEach(rx => {
            const horarios = rx.horarios ? (typeof rx.horarios === 'string' ? JSON.parse(rx.horarios) : rx.horarios) : [];
            if (!medsByName[rx.nombre]) {
                medsByName[rx.nombre] = {
                    name: rx.nombre,
                    dosisSet: new Set(),
                    horariosSet: new Set(),
                    adminTimes: administered[rx.nombre] || [],
                    isInv: false
                };
            }
            if (rx.dosis) medsByName[rx.nombre].dosisSet.add(rx.dosis);
            horarios.forEach(h => medsByName[rx.nombre].horariosSet.add(h));
        });

        // Convert to array and format horarios (only rx meds, no inventory)
        const rxTableBody = [];
        const rowMeta = [];
        const rxNames = new Set(Object.keys(medsByName).map(n => n.toLowerCase()));
        Object.values(medsByName).forEach(med => {
            const dosis = Array.from(med.dosisSet).join(', ');
            const horariosArr = Array.from(med.horariosSet).sort();
            const adminFlagsArr = horariosArr.map(h => {
                const hN = String(h).substring(0, 5);
                return med.adminTimes.some(at => String(at).startsWith(hN));
            });
            rxTableBody.push([med.name, dosis, '']); // horarios drawn with colors in didDrawCell
            rowMeta.push({ type: 'rx', rx: med, horariosArr, adminFlagsArr });
        });

        // Medicamentos ocasionales / SOS / no prescritos
        const sosNames = new Set();
        registros.filter(r => r.categoria === 'medicacion').forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
            (d.medicamentos_extra || []).forEach(name => {
                const key = name.toLowerCase();
                if (!sosNames.has(key)) {
                    sosNames.add(key);
                    const qty = d.med_cantidades?.[name] || 1;
                    const hora = r.hora ? r.hora.substring(0,5) : '';
                    rxTableBody.push([name, `×${qty}`, hora || '—']);
                    rowMeta.push({ type: 'sos', rx: { name, isInv: false, isSos: true } });
                }
            });
        });

        if (rxTableBody.length) {
            doc.autoTable({
                startY: yR,
                head: [['Medicamento', 'Dosis', 'Horarios']],
                body: rxTableBody,
                theme: 'plain',
                headStyles: { fillColor: false, textColor: [...C.primary], fontStyle: 'bold', fontSize: 6.5, cellPadding: { top: 1, bottom: 1, left: 1.5, right: 1 }, lineWidth: { bottom: 0.2 }, lineColor: C.border },
                bodyStyles: { fontSize: 6, cellPadding: { top: 0.8, bottom: 0.8, left: 1.5, right: 1 }, textColor: [50, 50, 50], lineWidth: { bottom: 0.1 }, lineColor: [235, 235, 235] },
                columnStyles: {
                    0: { cellWidth: 'auto' },
                    1: { cellWidth: 22, fontSize: 5.5, textColor: [...C.muted] },
                    2: { cellWidth: 26, fontSize: 5.5, halign: 'right' }
                },
                margin: { left: colR, right: mg },
                tableWidth: colW,
                didParseCell: function (data) {
                    if (data.section !== 'body') return;
                    const meta = rowMeta[data.row.index];
                    if (!meta || meta.type === 'header') return;
                    if (data.column.index === 0 && meta.type === 'sos') {
                        data.cell.styles.textColor = [180, 80, 20];
                        data.cell.styles.fontStyle = 'italic';
                    }
                    if (meta.type === 'sos' && data.column.index === 1) {
                        data.cell.styles.textColor = [180, 80, 20];
                    }
                    // Prevent autotable from rendering horarios text (drawn manually in didDrawCell)
                    if (data.column.index === 2 && meta.type !== 'sos') {
                        data.cell.text = [''];
                        // Ensure enough height for stacked horarios
                        const nH = (meta.horariosArr && meta.horariosArr.length) ? meta.horariosArr.length : 1;
                        if (nH > 1) {
                            data.cell.styles.minCellHeight = nH * 2.8 + 2;
                        }
                    }
                },
                didDrawCell: function (data) {
                    if (data.section !== 'body' || data.column.index !== 2) return;
                    const meta = rowMeta[data.row.index];
                    if (!meta || meta.type === 'header' || meta.type === 'sos') return;
                    const items = (meta.horariosArr && meta.horariosArr.length) ? meta.horariosArr : ['S/H'];
                    const flags = (meta.horariosArr && meta.horariosArr.length) ? meta.adminFlagsArr : [false];
                    const cell = data.cell;
                    const pT = cell.padding('top');
                    const pR = cell.padding('right');
                    const lh = 2.8;
                    doc.setFontSize(5.5);
                    items.forEach(function (h, i) {
                        const textY = cell.y + pT + 1.2 + i * lh;
                        // Green = administered, orange = pending
                        doc.setTextColor(...(flags[i] ? [20, 130, 60] : [200, 110, 10]));
                        doc.text(h, cell.x + cell.width - pR, textY, { align: 'right' });
                    });
                    doc.setTextColor(0);
                }
            });
            yR = doc.lastAutoTable.finalY;
        } else {
            yR += 3;
            doc.setFontSize(7);
            doc.setTextColor(...C.muted);
            doc.text('Sin medicación prescrita para este día.', colR, yR);
        }

        // ── Insumos table (right column, below medications) ──
        yR += 4;
        const insumosMap = {};
        // 1) Insumos from generic insumo picker (any category)
        registros.forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
            (d.insumos_consumidos || []).forEach(ins => {
                const k = (ins.nombre || ins.id || '').toLowerCase();
                if (!insumosMap[k]) insumosMap[k] = { nombre: ins.nombre || ins.id || k, unidad: ins.unidad || 'uds', qty: 0 };
                insumosMap[k].qty += (parseFloat(ins.qty) || 1);
            });
        });
        // 2) ALL medications administered (rx + SOS + extras) — always count
        const invNameMap = {};
        (_invItems || []).forEach(it => { invNameMap[it.nombre.toLowerCase()] = it; });
        const invNameKeys = Object.keys(invNameMap);
        function _fuzzyInvMatch(name) {
            const k = name.toLowerCase().trim();
            if (invNameMap[k]) return invNameMap[k];
            if (k.length < 3) return null;
            let found = invNameKeys.find(ik => ik.startsWith(k) || k.startsWith(ik));
            if (found) return invNameMap[found];
            found = invNameKeys.find(ik => ik.includes(k) || k.includes(ik));
            return found ? invNameMap[found] : null;
        }
        registros.filter(r => r.categoria === 'medicacion').forEach(r => {
            let d = r.datos || {};
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
            const meds = [...(d.medicamentos_seleccionados || []), ...(d.medicamentos_extra || [])];
            const cant = d.med_cantidades || {};
            // Track which med names we've already counted from inventario_admin
            const countedFromInv = new Set();
            // (a) If inventario_admin present, use it for reliable inventory linkage
            if (d.inventario_admin?.length) {
                d.inventario_admin.forEach(inv => {
                    const k = inv.nombre.toLowerCase();
                    if (!insumosMap[k]) insumosMap[k] = { nombre: inv.nombre, unidad: inv.unidad || 'uds', qty: 0 };
                    insumosMap[k].qty += (parseFloat(inv.qty) || 1);
                    countedFromInv.add(k);
                });
            }
            // (b) Count any remaining meds not already covered by inventario_admin
            meds.forEach(name => {
                const invItem = _fuzzyInvMatch(name);
                const resolvedKey = invItem ? invItem.nombre.toLowerCase() : name.toLowerCase().trim();
                if (countedFromInv.has(resolvedKey)) return; // already counted
                const qty = parseFloat(cant[name]) || 1;
                if (!insumosMap[resolvedKey]) insumosMap[resolvedKey] = { nombre: invItem ? invItem.nombre : name, unidad: invItem?.unidad || 'uds', qty: 0 };
                insumosMap[resolvedKey].qty += qty;
            });
        });
        // 3) Merge with inventory to include all items and their stock
        (_invItems || []).forEach(it => {
            const k = it.nombre.toLowerCase();
            if (!insumosMap[k]) {
                insumosMap[k] = { nombre: it.nombre, unidad: it.unidad || 'uds', qty: 0 };
            }
            insumosMap[k].stock = parseInt(it.stock_actual) || 0;
            if (!insumosMap[k].unidad || insumosMap[k].unidad === 'uds') insumosMap[k].unidad = it.unidad || 'uds';
        });
        const insumosArr = Object.values(insumosMap);
        doc.setFontSize(8);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(40, 40, 40);
        doc.text('Insumos', colR, yR);
        if (insumosArr.length) {
            yR += 1;
            doc.autoTable({
                startY: yR,
                head: [['Insumo', 'Cant.', 'Stock']],
                body: insumosArr.map(ins => [
                    ins.nombre,
                    ins.qty > 0 ? String(ins.qty) : (ins.qty < 0 ? String(ins.qty) : '—'),
                    typeof ins.stock === 'number' ? String(ins.stock - ins.qty) + ' ' + ins.unidad : '—'
                ]),
                theme: 'plain',
                headStyles: { fillColor: false, textColor: [...C.primary], fontStyle: 'bold', fontSize: 6.5, cellPadding: { top: 1, bottom: 1, left: 1.5, right: 1 }, lineWidth: { bottom: 0.2 }, lineColor: C.border },
                bodyStyles: { fontSize: 6, cellPadding: { top: 0.8, bottom: 0.8, left: 1.5, right: 1 }, textColor: [50, 50, 50], lineWidth: { bottom: 0.1 }, lineColor: [235, 235, 235] },
                columnStyles: { 0: { cellWidth: 'auto' }, 1: { halign: 'center', cellWidth: 14 }, 2: { halign: 'center', cellWidth: 22 } },
                margin: { left: colR, right: mg },
                tableWidth: colW,
            });
            yR = doc.lastAutoTable.finalY;
        } else {
            yR += 3;
            doc.setFontSize(7);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(...C.muted);
            doc.text('Sin insumos registrados.', colR, yR);
        }

        const yRightEnd = yR + 2;
        y = Math.max(yLeftEnd, yRightEnd) + 5;

        // ── Thin separator ──
        doc.setDrawColor(...C.border);
        doc.setLineWidth(0.2);
        doc.line(mg, y, pageW - mg, y);
        y += 4;

        // ══ COMPACT TIMELINE ══
        const allEvents = [...registros.map(r => ({...r, source:'cuidado'})), ...bitacoraEvents]
            .sort((a, b) => {
                const dA = (a.fecha || '') + ' ' + (a.hora || '');
                const dB = (b.fecha || '') + ' ' + (b.hora || '');
                return dA.localeCompare(dB);
            });

        if (allEvents.length) {
            doc.setFontSize(8);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(40, 40, 40);
            doc.text('Línea de Tiempo', mg, y);
            doc.setFontSize(6.5);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(...C.muted);
            doc.text(`${allEvents.length} eventos`, pageW - mg, y, { align: 'right' });
            y += 4;

            // Layout: |time| gap |icon| gap |content|
            const timeX = mg;        // time column left-aligned
            const timeW = 12;        // width reserved for time
            const iconX = mg + timeW + 1; // icon position
            const iconSize = 2.2;
            const bodyX = iconX + iconSize + 2.5; // content starts here
            const bodyW = contentW - (bodyX - mg);

            // Line icon drawers (small 2.2mm line-art icons)
            function _drawTlIcon(doc, cx, cy, cat, sz) {
                const r = sz / 2;
                doc.setDrawColor(80, 80, 80);
                doc.setLineWidth(0.2);
                doc.setFillColor(255, 255, 255);
                switch (cat) {
                    case 'sueno': // crescent moon
                        doc.circle(cx, cy, r, 'S');
                        doc.setFillColor(80, 80, 80);
                        doc.circle(cx + r * 0.35, cy - r * 0.2, r * 0.6, 'F');
                        doc.setFillColor(255, 255, 255);
                        doc.circle(cx + r * 0.55, cy - r * 0.4, r * 0.55, 'F');
                        break;
                    case 'alimentacion': // fork-like lines
                        doc.line(cx - r * 0.5, cy - r, cx - r * 0.5, cy + r);
                        doc.line(cx, cy - r, cx, cy + r * 0.3);
                        doc.line(cx + r * 0.5, cy - r, cx + r * 0.5, cy + r * 0.3);
                        doc.line(cx - r * 0.5, cy + r * 0.3, cx + r * 0.5, cy + r * 0.3);
                        break;
                    case 'medicacion': // pill cross
                        doc.line(cx, cy - r, cx, cy + r);
                        doc.line(cx - r, cy, cx + r, cy);
                        break;
                    case 'higiene': // droplet
                        doc.line(cx, cy - r, cx - r * 0.6, cy + r * 0.3);
                        doc.line(cx, cy - r, cx + r * 0.6, cy + r * 0.3);
                        doc.line(cx - r * 0.6, cy + r * 0.3, cx, cy + r);
                        doc.line(cx + r * 0.6, cy + r * 0.3, cx, cy + r);
                        break;
                    case 'terapia': // heart outline
                        doc.circle(cx - r * 0.35, cy - r * 0.2, r * 0.35, 'S');
                        doc.circle(cx + r * 0.35, cy - r * 0.2, r * 0.35, 'S');
                        doc.line(cx - r * 0.7, cy, cx, cy + r);
                        doc.line(cx + r * 0.7, cy, cx, cy + r);
                        break;
                    case 'movilidad': // stick figure
                        doc.circle(cx, cy - r * 0.6, r * 0.3, 'S');
                        doc.line(cx, cy - r * 0.3, cx, cy + r * 0.4);
                        doc.line(cx - r * 0.5, cy + r, cx, cy + r * 0.4);
                        doc.line(cx + r * 0.5, cy + r, cx, cy + r * 0.4);
                        break;
                    case 'eliminacion': // bin outline
                        doc.line(cx - r * 0.6, cy - r * 0.5, cx + r * 0.6, cy - r * 0.5);
                        doc.line(cx - r * 0.5, cy - r * 0.5, cx - r * 0.4, cy + r);
                        doc.line(cx + r * 0.5, cy - r * 0.5, cx + r * 0.4, cy + r);
                        doc.line(cx - r * 0.4, cy + r, cx + r * 0.4, cy + r);
                        break;
                    case 'comportamiento': // smiley
                        doc.circle(cx, cy, r, 'S');
                        doc.setFillColor(80, 80, 80);
                        doc.circle(cx - r * 0.3, cy - r * 0.2, 0.15, 'F');
                        doc.circle(cx + r * 0.3, cy - r * 0.2, 0.15, 'F');
                        doc.setFillColor(255, 255, 255);
                        break;
                    case 'signos_vitales': // heartbeat line
                        doc.line(cx - r, cy, cx - r * 0.3, cy);
                        doc.line(cx - r * 0.3, cy, cx, cy - r);
                        doc.line(cx, cy - r, cx + r * 0.3, cy + r * 0.5);
                        doc.line(cx + r * 0.3, cy + r * 0.5, cx + r * 0.5, cy);
                        doc.line(cx + r * 0.5, cy, cx + r, cy);
                        break;
                    default: // generic dot
                        doc.circle(cx, cy, r * 0.5, 'S');
                        break;
                }
            }

            // Two-column timeline rendering
            const colCount = 2;
            const colWidth = (contentW - 6) / colCount; // 6mm gap between columns
            const colXs = [mg, mg + colWidth + 6];
            let col = 0;
            let yCols = [y, y];
            let _lastTlDate = '';
            allEvents.forEach((evt, idx) => {
                let yCurr = yCols[col];

                // Day separator for multi-day reports (full-width, resets columns)
                const evtDate = evt.fecha || '';
                if (evtDate && evtDate !== _lastTlDate) {
                    _lastTlDate = evtDate;
                    if (idx > 0) {
                        // Flush both columns — start separator below the tallest
                        let yMax = Math.max(yCols[0] || 0, yCols[1] || 0) + 3;
                        if (yMax > pageH - 18) { doc.addPage(); yMax = 14; }
                        // Full-width separator line
                        doc.setDrawColor(...C.primary);
                        doc.setLineWidth(0.4);
                        doc.line(mg, yMax, pageW - mg, yMax);
                        // Date label centered, larger than timeline titles
                        doc.setFontSize(9);
                        doc.setFont(undefined, 'bold');
                        doc.setTextColor(...C.primary);
                        doc.text(fmtDate(evtDate), pageW / 2, yMax + 4.5, { align: 'center' });
                        // Second line below date
                        const yLine2 = yMax + 7;
                        doc.setDrawColor(...C.primary);
                        doc.setLineWidth(0.15);
                        doc.line(mg, yLine2, pageW - mg, yLine2);
                        // Reset columns to start fresh below separator
                        const yNewStart = yLine2 + 3;
                        col = 0;
                        yCols = [yNewStart, yNewStart];
                        yCurr = yNewStart;
                    }
                }

                // If current column is full, move to next column
                if (yCurr > pageH - 18) {
                    col++;
                    if (col >= colCount) {
                        doc.addPage();
                        col = 0;
                        yCols = [12, 12];
                    }
                    yCurr = yCols[col];
                }

                // Column-specific X positions
                const timeX = colXs[col];
                const timeW = 12;
                const iconX = timeX + timeW + 1;
                const iconSize = 2.2;
                const bodyX = iconX + iconSize + 2.5;
                const bodyW = colWidth - (bodyX - timeX);

                const isCuidado = evt.source === 'cuidado';
                const catKey = isCuidado ? evt.categoria : 'bitacora';
                const catLabel = isCuidado ? (CAT_LABELS[evt.categoria] || evt.categoria) : (evt.categoria || 'Bitácora');

                // Detect abnormal vital signs
                let isAbnormalVitals = false;
                if (isCuidado && evt.categoria === 'signos_vitales') {
                    let vd = evt.datos || {};
                    if (typeof vd === 'string') { try { vd = JSON.parse(vd); } catch(e) { vd = {}; } }
                    const temp = parseFloat(vd.temperatura);
                    const fc   = parseInt(vd.frecuencia_cardiaca);
                    const fr   = parseInt(vd.frecuencia_respiratoria);
                    const spo2 = parseInt(vd.spo2);
                    const pas  = parseInt(vd.pa_sistolica);
                    const pad  = parseInt(vd.pa_diastolica);
                    if (!isNaN(temp) && temp > 0 && (temp < 36.0 || temp > 37.4)) isAbnormalVitals = true;
                    if (!isNaN(fc)   && fc   > 0 && (fc   < 60  || fc   > 100))   isAbnormalVitals = true;
                    if (!isNaN(fr)   && fr   > 0 && (fr   < 12  || fr   > 20))    isAbnormalVitals = true;
                    if (!isNaN(spo2) && spo2 > 0 &&  spo2 < 95)                   isAbnormalVitals = true;
                    if (!isNaN(pas)  && pas  > 0 && (pas  < 90  || pas  > 130))   isAbnormalVitals = true;
                    if (!isNaN(pad)  && pad  > 0 && (pad  < 60  || pad  > 85))    isAbnormalVitals = true;
                }

                // ── Time (isolated left column) ──
                const timeStr = evt.hora || '--:--';
                doc.setFontSize(10);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(50, 50, 50);
                doc.text(timeStr, timeX + timeW, yCurr, { align: 'right' });

                // ── Line icon ──
                _drawTlIcon(doc, iconX + iconSize / 2, yCurr - 0.6, catKey, iconSize);

                // ── Category label ──
                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(40, 40, 40);
                doc.text(catLabel, bodyX, yCurr);

                // ── Author + registration time (right-aligned) ──
                const authorName = evt.usuario_nombre || '';
                const creadoAt = evt.creado_at || '';
                if (authorName || creadoAt) {
                    let metaStr = '';
                    if (authorName) metaStr += authorName;
                    if (creadoAt) {
                        const _ts = creadoAt.includes('T') ? creadoAt : creadoAt.replace(' ','T')+'Z';
                        try { const _d = toAppTz(_ts); metaStr += (metaStr ? ' · ' : '') + String(_d.getHours()).padStart(2,'0')+':'+String(_d.getMinutes()).padStart(2,'0'); } catch(e){}
                    }
                    if (metaStr) {
                        doc.setFontSize(5.5);
                        doc.setFont(undefined, 'normal');
                        doc.setTextColor(...C.muted);
                        doc.text(metaStr, colXs[col] + colWidth, yCurr, { align: 'right' });
                    }
                }

                // ── Summary text (indented under category) ──
                let yNext = yCurr + 4;

                // Footer overflow guard for timeline entry (check if header alone is too close)
                const _footerGuard = pageH - 18;
                if (yNext > _footerGuard) {
                    col++;
                    if (col >= colCount) { doc.addPage(); col = 0; yCols = [12, 12]; }
                    yCols[col] = yCols[col] || 12;
                    yCurr = yCols[col];
                    yNext = yCurr + 4;
                    // Re-draw header on new column/page
                    const _reTimeX = colXs[col];
                    const _reBodyX = _reTimeX + 12 + 1 + iconSize + 2.5;
                    doc.setFontSize(10); doc.setFont(undefined, 'bold'); doc.setTextColor(50, 50, 50);
                    doc.text(timeStr, _reTimeX + 12, yCurr, { align: 'right' });
                    _drawTlIcon(doc, _reTimeX + 12 + 1 + iconSize / 2, yCurr - 0.6, catKey, iconSize);
                    doc.setFontSize(9); doc.setFont(undefined, 'bold'); doc.setTextColor(40, 40, 40);
                    doc.text(catLabel, _reBodyX, yCurr);
                }

                let summaryText = '';
                if (isCuidado) {
                    // For medication with multiple meds, build one line per med
                    if (evt.categoria === 'medicacion') {
                        let d = evt.datos || {};
                        if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e) { d = {}; } }
                        const meds = [...(d.medicamentos_seleccionados||[]),...(d.medicamentos_extra||[])];
                        if (meds.length > 1) {
                            const rxA = RX_BY_RES[_residenteId] || [];
                            const cant = d.med_cantidades || {};
                            const medLines = meds.map(name => {
                                const rxM = rxA.find(r => r.nombre === name);
                                const p = [name];
                                if (rxM?.dosis) p.push(rxM.dosis);
                                const q = cant[name]; if (q && q !== 1) p.push('\u00d7' + q);
                                if (rxM?.via) p.push(rxM.via);
                                return p.join(' \u00b7 ');
                            });
                            summaryText = medLines.join('\n');
                        } else {
                            const tmp = document.createElement('span');
                            tmp.innerHTML = buildSummary(evt);
                            summaryText = tmp.textContent || '';
                        }
                    } else {
                        const tmp = document.createElement('span');
                        tmp.innerHTML = buildSummary(evt);
                        summaryText = tmp.textContent || '';
                    }
                } else {
                    let bitParsed = null;
                    try { bitParsed = JSON.parse(evt.contenido); } catch (e) {}
                    if (bitParsed) {
                        const tmp2 = document.createElement('span');
                        tmp2.innerHTML = buildBitacoraSummary(bitParsed, evt.categoria, catKey !== 'bitacora' ? catKey : null);
                        summaryText = tmp2.textContent || '';
                    } else {
                        summaryText = evt.contenido || '';
                    }
                }
                if (summaryText) {
                    // Strip emojis (not renderable in default PDF font)
                    summaryText = summaryText.replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{FE00}-\u{FE0F}\u{1F900}-\u{1F9FF}\u{200D}\u{20E3}\u{E0020}-\u{E007F}]/gu, '').replace(/  +/g, ' ').trim();

                    // For signos vitales with abnormal readings, render each reading individually
                    if (isAbnormalVitals) {
                        let vd = evt.datos || {};
                        if (typeof vd === 'string') { try { vd = JSON.parse(vd); } catch(e) { vd = {}; } }
                        const readings = [];
                        const temp = parseFloat(vd.temperatura);
                        const fc   = parseInt(vd.frecuencia_cardiaca);
                        const fr   = parseInt(vd.frecuencia_respiratoria);
                        const spo2 = parseInt(vd.spo2);
                        const pas  = parseInt(vd.pa_sistolica);
                        const pad  = parseInt(vd.pa_diastolica);
                        if (!isNaN(temp) && temp > 0) readings.push({ text: `Temp: ${temp}°C`, bad: temp < 36.0 || temp > 37.4 });
                        if (!isNaN(fc) && fc > 0)   readings.push({ text: `FC: ${fc} bpm`, bad: fc < 60 || fc > 100 });
                        if (!isNaN(fr) && fr > 0)   readings.push({ text: `FR: ${fr} rpm`, bad: fr < 12 || fr > 20 });
                        if (!isNaN(spo2) && spo2 > 0) readings.push({ text: `SpO2: ${spo2}%`, bad: spo2 < 95 });
                        if (!isNaN(pas) && pas > 0) readings.push({ text: `PAS: ${pas}`, bad: pas < 90 || pas > 130 });
                        if (!isNaN(pad) && pad > 0) readings.push({ text: `PAD: ${pad}`, bad: pad < 60 || pad > 85 });
                        const gluc = parseInt(vd.glucosa);
                        if (!isNaN(gluc) && gluc > 0) readings.push({ text: `Gluc: ${gluc} mg/dL`, bad: gluc < 70 || gluc > 140 });
                        doc.setFontSize(8.5);
                        doc.setFont(undefined, 'normal');
                        const maxX = bodyX + bodyW;
                        let xOff = bodyX;
                        const sepStr = ' · ';
                        readings.forEach((rd, ri) => {
                            const hasSep = ri < readings.length - 1;
                            const tw = doc.getTextWidth(rd.text);
                            // Wrap to next line if it won't fit
                            if (xOff > bodyX && xOff + tw > maxX) {
                                yNext += 3.5;
                                xOff = bodyX;
                            }
                            doc.setTextColor(...(rd.bad ? [185, 30, 30] : [80, 80, 80]));
                            doc.setFont(undefined, rd.bad ? 'bold' : 'normal');
                            doc.text(rd.text, xOff, yNext);
                            xOff += tw;
                            if (hasSep) {
                                doc.setTextColor(80, 80, 80);
                                doc.setFont(undefined, 'normal');
                                doc.text(sepStr, xOff, yNext);
                                xOff += doc.getTextWidth(sepStr);
                            }
                        });
                        yNext += 3.5;
                    } else {
                        doc.setFontSize(8.5);
                        doc.setFont(undefined, 'normal');
                        doc.setTextColor(80, 80, 80);
                        const lines = doc.splitTextToSize(summaryText, bodyW);
                        const showLines = lines.slice(0, 6);
                        showLines.forEach(line => {
                            if (yNext > pageH - 18) {
                                col++; if (col >= colCount) { doc.addPage(); col = 0; yCols = [12, 12]; }
                                yNext = yCols[col] || 12; yCols[col] = yNext;
                            }
                            doc.setFontSize(8.5); doc.setFont(undefined, 'normal'); doc.setTextColor(80, 80, 80);
                            doc.text(line, bodyX, yNext);
                            yNext += 3.5;
                        });
                    }
                }

                // Observaciones
                const obs = evt.observaciones || '';
                if (obs) {
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'italic');
                    doc.setTextColor(...C.muted);
                    const obsLines = doc.splitTextToSize(obs, bodyW);
                    const showObs = obsLines.slice(0, 3);
                    showObs.forEach(line => {
                        if (yNext > pageH - 18) {
                            col++; if (col >= colCount) { doc.addPage(); col = 0; yCols = [12, 12]; }
                            yNext = yCols[col] || 12; yCols[col] = yNext;
                        }
                        doc.setFontSize(8); doc.setFont(undefined, 'italic'); doc.setTextColor(...C.muted);
                        doc.text(line, bodyX, yNext);
                        yNext += 3.2;
                    });
                }

                // Thin connector (vertical line between icon positions)
                yNext += 0.3;
                if (idx < allEvents.length - 1) {
                    doc.setDrawColor(210, 210, 210);
                    doc.setLineWidth(0.1);
                    doc.line(iconX + iconSize / 2, yNext - 2.5, iconX + iconSize / 2, yNext + 0.5);
                }
                yNext += 1.8;
                yCols[col] = yNext;
            });

            // ── Notas de turno (full-width, single column) ──
            if (notas && notas.length) {
                // Get Y from whichever column is last
                y = Math.max(yCols[0] || y, yCols[1] || y) + 4;
                if (y > pageH - 20) { doc.addPage(); y = 14; }

                // Thin separator before notes
                doc.setDrawColor(...C.border);
                doc.setLineWidth(0.2);
                doc.line(mg, y, pageW - mg, y);
                y += 4;

                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(40, 40, 40);
                doc.text('<?= t('form_notes_title') ?>', mg, y);
                y += 5;

                notas.forEach(nota => {
                    if (y > pageH - 18) { doc.addPage(); y = 14; }

                    const noteText = (nota.nota || '').replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{FE00}-\u{FE0F}\u{1F900}-\u{1F9FF}\u{200D}\u{20E3}\u{E0020}-\u{E007F}]/gu, '').replace(/  +/g, ' ').trim();

                    // Timestamp (left) + Name (right) on the same line
                    let ts = '';
                    if (nota.creado_at) {
                        const _p = nota.creado_at.includes('T') ? nota.creado_at : nota.creado_at.replace(' ','T')+'Z';
                        const _td = toAppTz(_p);
                        ts = String(_td.getDate()).padStart(2,'0')+'-'+String(_td.getMonth()+1).padStart(2,'0')+'-'+_td.getFullYear()+' '+(fmtDateTime(nota.creado_at).time || '');
                    }
                    if (ts) {
                        doc.setFontSize(6.5);
                        doc.setFont(undefined, 'normal');
                        doc.setTextColor(...C.muted);
                        doc.text(ts, mg, y);
                    }
                    if (nota.usuario_nombre) {
                        doc.setFontSize(7);
                        doc.setFont(undefined, 'bold');
                        doc.setTextColor(60, 60, 60);
                        doc.text(nota.usuario_nombre, pageW - mg, y, { align: 'right' });
                    }
                    if (ts || nota.usuario_nombre) y += 3.2;

                    if (nota.prioridad && nota.prioridad !== 'normal') {
                        doc.setFontSize(6.5);
                        doc.setFont(undefined, 'italic');
                        doc.setTextColor(180, 80, 20);
                        doc.text('[' + nota.prioridad + ']', mg, y);
                        y += 2.8;
                    }

                    // Set font BEFORE splitTextToSize so width calc matches render
                    doc.setFontSize(7);
                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(80, 80, 80);
                    const noteLines = doc.splitTextToSize(noteText, contentW - 2);
                    noteLines.forEach(line => {
                        if (y > pageH - 14) { doc.addPage(); y = 14; }
                        doc.text(line, mg, y);
                        y += 3;
                    });
                    y += 2;
                });
            }
        } else {
            doc.setFontSize(7);
            doc.setTextColor(...C.muted);
            doc.text('Sin eventos registrados para este período.', mg, y);
            y += 5;
        }

        // ── Page footers ──
        const totalPages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            doc.setPage(i);
            // Top accent bar on every page
            doc.setFillColor(...C.primary);
            doc.rect(0, 0, pageW, 1.2, 'F');
            // Footer separator + text
            doc.setDrawColor(210, 210, 210);
            doc.setLineWidth(0.15);
            doc.line(mg, pageH - 10, pageW - mg, pageH - 10);
            doc.setFontSize(6);
            doc.setTextColor(160, 160, 160);
            doc.text(`${i} / ${totalPages}`, pageW - mg, pageH - 6, { align: 'right' });
            doc.text(`GeriApp · ${rName} · ${dateLabel}`, mg, pageH - 6);
        }

        if (previewMode) {
            try {
                const previewUrl = await _uploadPdfTemp(doc.output('blob'), fileName);
                _openPdfViewer(previewUrl, false);
            } catch (previewErr) {
                console.warn('[GeriApp] PDF preview temp upload failed, falling back to blob URL:', previewErr);
                const blobUrl = doc.output('bloburl');
                _openPdfViewer(blobUrl, true);
            }
            showToast(t('toast_pdf_preview'), 'success');
        } else {
            const native = await _savePdfCompat(doc, fileName);
            showToast(t(native ? 'toast_pdf_opened' : 'toast_pdf_downloaded'), 'success');
        }
        _logReporte(`reporte_${period}`, rName, dateLabel);
    } catch (e) {
        console.error('Day report PDF error:', e);
        showToast(t('error_pdf'), 'error');
    }
    btnReset(_btn);
}


// ── In-page PDF viewer (preview without downloading) ──
function _openPdfViewer(pdfUrl, revokeOnClose) {
    const isNative = !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());
    // iOS Safari (web) tiene un bug conocido: al hacer pinch-zoom dentro de un
    // <iframe> con PDF, el visor nativo de iOS reescala el contenido sin respetar
    // los límites del iframe — el PDF "se desprende" y deja ver el overlay/otros
    // contenidos debajo. Por eso en iOS usamos la misma ruta canvas (pdf.js) que
    // Capacitor, que maneja el zoom redimensionando físicamente el wrapper.
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent || '')
        || (navigator.platform === 'MacIntel' && (navigator.maxTouchPoints || 0) > 1); // iPadOS 13+
    const isCompactWeb = !isNative && !isIOS && (
        window.matchMedia?.('(max-width: 820px)')?.matches
        || (window.matchMedia?.('(pointer: coarse)')?.matches && window.innerWidth <= 1024)
    );

    if (isNative || isIOS || isCompactWeb) {
        // Render via pdf.js a <canvas> (sin iframe).
        (async () => {
            try {
                const resp = await fetch(pdfUrl);
                const buf = await resp.arrayBuffer();
                if (revokeOnClose) URL.revokeObjectURL(pdfUrl);
                _showPdfCanvasOverlay(buf, revokeOnClose ? '' : pdfUrl);
            } catch (e) {
                console.error('PDF preview error:', e);
                showToast(t('error_pdf'), 'error');
                // Fallback: si pdf.js falla en web, intentar el iframe como último recurso
                if (!isNative) _showPdfOverlay(pdfUrl, !!revokeOnClose);
            }
        })();
        return;
    }

    // Web (no iOS): blob URL directo en iframe — visor nativo del navegador
    _showPdfOverlay(pdfUrl, !!revokeOnClose);
}

// Renders PDF arrayBuffer into scrollable canvas pages using pdf.js (Capacitor)
let _pdfjsLib = null;
async function _loadPdfJs() {
    if (_pdfjsLib) return _pdfjsLib;
    if (window.pdfjsLib) { _pdfjsLib = window.pdfjsLib; return _pdfjsLib; }
    const mod = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.4.168/build/pdf.min.mjs');
    _pdfjsLib = mod;
    _pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.4.168/build/pdf.worker.min.mjs';
    return _pdfjsLib;
}

async function _showPdfCanvasOverlay(arrayBuf, sourceUrl) {
    document.getElementById('cdPdfViewerOverlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'cdPdfViewerOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;flex-direction:column;align-items:center;padding:calc(env(safe-area-inset-top,0px) + 8px) calc(env(safe-area-inset-right,0px) + 4px) calc(env(safe-area-inset-bottom,0px) + 8px) calc(env(safe-area-inset-left,0px) + 4px);';

    let pdfZoom = 1;
    let baseWidth = 0;

    const _close = () => {
        overlay.remove();
        document.removeEventListener('keydown', _escHandler);
    };

    // Top bar
    const bar = document.createElement('div');
    bar.style.cssText = 'display:flex;justify-content:space-between;align-items:center;width:100%;max-width:900px;margin-bottom:6px;flex-shrink:0;gap:8px;';

    const zoomBar = document.createElement('div');
    zoomBar.style.cssText = 'display:flex;gap:4px;';
    zoomBar.innerHTML = `<button class="cd-pdf-zoom-btn" data-z="out">\u2212</button><button class="cd-pdf-zoom-btn cd-pdf-zoom-label" data-z="reset">100%</button><button class="cd-pdf-zoom-btn" data-z="in">+</button>`;
    const zoomLabel = zoomBar.querySelector('[data-z="reset"]');
    const btnStyle = 'padding:5px 12px;border:none;border-radius:8px;background:var(--cd-bg-card,#fff);color:var(--cd-text-primary,#1e293b);font-size:0.8125rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15);min-width:32px;text-align:center;';
    zoomBar.querySelectorAll('.cd-pdf-zoom-btn').forEach(b => b.style.cssText = btnStyle);

    const actionBar = document.createElement('div');
    actionBar.style.cssText = 'display:flex;gap:6px;align-items:center;flex-shrink:0;';
    if (sourceUrl) {
        const openBtn = document.createElement('button');
        openBtn.textContent = 'Abrir PDF';
        openBtn.style.cssText = 'padding:6px 12px;border:none;border-radius:8px;background:var(--cd-primary,#3478dc);color:#fff;font-size:0.8125rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15);white-space:nowrap;';
        openBtn.addEventListener('click', () => window.open(sourceUrl, '_blank', 'noopener'));
        actionBar.appendChild(openBtn);
    }

    const closeBtn = document.createElement('button');
    closeBtn.textContent = '\u2715 Cerrar';
    closeBtn.style.cssText = 'padding:6px 12px;border:none;border-radius:8px;background:var(--cd-bg-card,#fff);color:var(--cd-text-primary,#1e293b);font-size:0.8125rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15);white-space:nowrap;';
    closeBtn.addEventListener('click', _close);
    actionBar.appendChild(closeBtn);
    bar.appendChild(zoomBar);
    bar.appendChild(actionBar);

    // Scroll container — native overflow handles all panning/scrolling
    const scroll = document.createElement('div');
    scroll.style.cssText = 'flex:1;overflow:auto;width:100%;max-width:900px;-webkit-overflow-scrolling:touch;border-radius:6px;touch-action:pan-x pan-y;';

    // Wrapper — physically resized for zoom (NO CSS transform)
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'margin:0 auto;';
    scroll.appendChild(wrapper);

    // Zoom helper: resizes wrapper and adjusts scroll to keep anchor point stable
    const applyZoom = (cx, cy, prevZoom) => {
        cx = cx ?? (scroll.clientWidth / 2);
        cy = cy ?? (scroll.clientHeight / 2);
        const ratio = pdfZoom / (prevZoom || 1);
        const newSL = (scroll.scrollLeft + cx) * ratio - cx;
        const newST = (scroll.scrollTop + cy) * ratio - cy;
        wrapper.style.width = (baseWidth * pdfZoom) + 'px';
        scroll.scrollLeft = Math.max(0, newSL);
        scroll.scrollTop  = Math.max(0, newST);
        zoomLabel.textContent = Math.round(pdfZoom * 100) + '%';
    };

    zoomBar.addEventListener('click', e => {
        const z = e.target.closest('[data-z]')?.dataset.z;
        const prev = pdfZoom;
        if (z === 'in')    pdfZoom = Math.min(3, pdfZoom + .25);
        else if (z === 'out')   pdfZoom = Math.max(.5, pdfZoom - .25);
        else if (z === 'reset') pdfZoom = 1;
        if (pdfZoom !== prev) applyZoom(null, null, prev);
    });

    // Pinch-to-zoom — anchored to midpoint between fingers
    let pzInitDist = 0, pzBaseZoom = 1;
    scroll.addEventListener('touchstart', e => {
        if (e.touches.length === 2) {
            pzInitDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            pzBaseZoom = pdfZoom;
        }
    }, { passive: true });
    scroll.addEventListener('touchmove', e => {
        if (e.touches.length === 2 && pzInitDist > 0) {
            e.preventDefault();
            const d = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            const prev = pdfZoom;
            pdfZoom = Math.max(.5, Math.min(3, pzBaseZoom * (d / pzInitDist)));
            if (pdfZoom !== prev) {
                const rect = scroll.getBoundingClientRect();
                const cx = ((e.touches[0].clientX + e.touches[1].clientX) / 2) - rect.left;
                const cy = ((e.touches[0].clientY + e.touches[1].clientY) / 2) - rect.top;
                applyZoom(cx, cy, prev);
            }
        }
    }, { passive: false });
    scroll.addEventListener('touchend', () => { pzInitDist = 0; });

    // Double-tap to toggle zoom (1x ↔ 2x)
    let lastTap = 0;
    scroll.addEventListener('touchend', e => {
        if (e.touches.length > 0) return;
        const now = Date.now();
        if (now - lastTap < 300) {
            const prev = pdfZoom;
            pdfZoom = prev < 1.5 ? 2 : 1;
            const rect = scroll.getBoundingClientRect();
            const cx = e.changedTouches[0].clientX - rect.left;
            const cy = e.changedTouches[0].clientY - rect.top;
            applyZoom(cx, cy, prev);
            lastTap = 0;
        } else {
            lastTap = now;
        }
    });

    // Loading indicator
    const loading = document.createElement('div');
    loading.style.cssText = 'text-align:center;padding:40px 0;color:#fff;font-size:0.875rem;';
    loading.textContent = 'Cargando reporte\u2026';
    wrapper.appendChild(loading);

    overlay.appendChild(bar);
    overlay.appendChild(scroll);

    overlay.addEventListener('click', e => { if (e.target === overlay) _close(); });
    const _escHandler = e => { if (e.key === 'Escape') _close(); };
    document.addEventListener('keydown', _escHandler);

    document.body.appendChild(overlay);

    // Measure base width after DOM insertion
    baseWidth = scroll.clientWidth;
    wrapper.style.width = baseWidth + 'px';

    // Render PDF pages to canvases
    try {
        const pdfjsLib = await _loadPdfJs();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuf }).promise;
        wrapper.removeChild(loading);
        const dpr = Math.min(window.devicePixelRatio || 1, 2); // cap at 2x for memory

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const vp = page.getViewport({ scale: 1 });
            const scale = (baseWidth / vp.width) * dpr;
            const scaledVp = page.getViewport({ scale });

            const canvas = document.createElement('canvas');
            canvas.width = scaledVp.width;
            canvas.height = scaledVp.height;
            canvas.style.cssText = `width:100%;height:auto;display:block;margin-bottom:4px;background:#fff;`;

            wrapper.appendChild(canvas);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledVp }).promise;
        }
    } catch (e) {
        console.error('pdf.js render error:', e);
        wrapper.innerHTML = '<div style="text-align:center;padding:40px 0;color:#ff6b6b;font-size:0.875rem;">Error al renderizar el PDF</div>';
    }
}

// Web PDF viewer (iframe-based, desktop/laptop browsers)
function _showPdfOverlay(pdfUrl, revokeOnClose) {
    document.getElementById('cdPdfViewerOverlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'cdPdfViewerOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;flex-direction:column;align-items:center;padding:calc(env(safe-area-inset-top,0px) + 10px) calc(env(safe-area-inset-right,0px) + 10px) calc(env(safe-area-inset-bottom,0px) + 10px) calc(env(safe-area-inset-left,0px) + 10px);';

    const _close = () => {
        if (revokeOnClose) URL.revokeObjectURL(pdfUrl);
        overlay.remove();
        document.removeEventListener('keydown', _escHandler);
    };

    const bar = document.createElement('div');
    bar.style.cssText = 'display:flex;justify-content:flex-end;width:100%;max-width:900px;margin-bottom:6px;';
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '✕ Cerrar';
    closeBtn.style.cssText = 'padding:6px 18px;border:none;border-radius:8px;background:var(--cd-bg-card,#fff);color:var(--cd-text-primary,#1e293b);font-size:0.875rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15);';
    closeBtn.addEventListener('click', _close);
    bar.appendChild(closeBtn);

    const iframe = document.createElement('iframe');
    iframe.src = pdfUrl + '#zoom=page-width';
    iframe.style.cssText = 'width:100%;max-width:900px;flex:1;border:none;border-radius:6px;box-shadow:0 4px 24px rgba(0,0,0,.3);background:#fff;';

    overlay.appendChild(bar);
    overlay.appendChild(iframe);

    overlay.addEventListener('click', e => { if (e.target === overlay) _close(); });
    const _escHandler = e => { if (e.key === 'Escape') _close(); };
    document.addEventListener('keydown', _escHandler);

    document.body.appendChild(overlay);
}

// Save PDF: upload blob to server, then download natively (or doc.save on web).

window._descargarReporteNativo = async function(url, fn) {
    //1. Usamos Filesystem para la descarga, no Http
    const Filesystem = window.Capacitor.Plugins.Filesystem;
    const FileOpener = window.Capacitor.Plugins.FileOpener;

    console.log('[GeriApp PDF] Iniciando descarga con Filesystem para:', fn);

    if (!Filesystem || typeof Filesystem.downloadFile !== 'function') {
        console.error('[GeriApp] Error: El plugin Filesystem no está disponible o no soporta downloadFile.');
        alert("Error: El sistema de archivos no está listo.");
        return;
    }

    // Limpieza de nombre de archivo
    const cleanFileName = fn
        .replace(/[Áá]/g, 'A').replace(/[Éé]/g, 'E')
        .replace(/[Íí]/g, 'I').replace(/[Óó]/g, 'O')
        .replace(/[Úú]/g, 'U').replace(/[Ññ]/g, 'N')
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, '_')
        .replace(/[^a-zA-Z0-9.\-_]/g, "")
        .trim();

    try {
        // 2. Usamos Filesystem.downloadFile
        // Nota: 'path' es el nombre del archivo dentro del directorio especificado
        const options = {
            url: url,
            path: cleanFileName, 
            directory: 'CACHE', // 'CACHE' es ideal para archivos temporales como PDFs
        };

        const response = await Filesystem.downloadFile(options);
        
        // En versiones recientes, la ruta viene en response.path o response.uri
        const finalPath = response.path || response.uri;
        console.log('[GeriApp PDF] Guardado en: ' + finalPath);

        // 3. Abrir el archivo
        await FileOpener.open({
            filePath: finalPath,
            contentType: 'application/pdf',
            openWithDefault: true
        });

    } catch (err) {
        console.error('[GeriApp PDF] Error Crítico:', err);
        alert('Error al procesar el PDF: ' + err.message);
    }
};


/**
 * Log report generation activity (fire-and-forget)
 */
function _logReporte(tipo, residenteNombre, periodo) {
    api(API_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'log_reporte', tipo, residente: residenteNombre, periodo })
    }).catch(() => {});
}

async function _uploadPdfTemp(blob, fileName) {
    const form = new FormData();
    form.append('pdf', blob, fileName);
    try {
        const res = await fetch(BASE + '/api/pdf_temp.php', {
            method: 'POST',
            body: form,
            credentials: 'include',
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success || !json.data?.url) {
            throw new Error(json.message || 'Error al subir PDF al servidor');
        }
        return json.data.url;
    } finally {
        form.delete('pdf');
    }
}

/**
 * 2. FUNCIÓN DE COMPATIBILIDAD (jsPDF -> Server -> Native)
 * Decide si descargar directo (Web) o subir al server para luego bajar (Nativo).
 */
async function _savePdfCompat(doc, fileName) {
    const isNative = !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());

    // --- ESCENARIO WEB ---
    if (!isNative) {
        doc.save(fileName);
        return false;
    }

    // --- ESCENARIO NATIVO (Capacitor) ---
    try {
        console.log('[GeriApp] Generando PDF y subiendo a servidor temporal...');
        
        const tempUrl = await _uploadPdfTemp(doc.output('blob'), fileName);

        // Llamamos a la función GLOBAL que definimos arriba
        await window._descargarReporteNativo(tempUrl, fileName);
        return true;

    } catch (e) {
        if (e.message === 'SESSION_EXPIRED') {
            alert('Sesión expirada. Por favor, inicia sesión de nuevo.');
            setTimeout(() => { location.href = BASE + '/index.php'; }, 1500);
        } else {
            console.error('[GeriApp] Error en savePdfCompat:', e);
            throw e;
        }
    }
}

// Shared metric helper for PDF exports
function _getMetricForPdf(cat, byCategory) {
    const items = byCategory[cat] || [];
    if (!items.length) return '';
    switch (cat) {
        case 'alimentacion': {
            const pcts = items.map(d => parseInt(d.ingesta_pct)).filter(v => !isNaN(v));
            return pcts.length ? `Ingesta prom: ${Math.round(pcts.reduce((a, b) => a + b, 0) / pcts.length)}%` : '';
        }
        case 'sueno': {
            const hrs = items.map(d => parseFloat(d.horas)).filter(v => !isNaN(v) && v > 0);
            const cals = items.map(d => parseInt(d.calidad_pct)).filter(v => !isNaN(v));
            let p = [];
            if (hrs.length) p.push(`${(hrs.reduce((a, b) => a + b, 0) / hrs.length).toFixed(1)}h prom`);
            if (cals.length) {
                const avg = Math.round(cals.reduce((a, b) => a + b, 0) / cals.length);
                const cLbl = { 0: 'Muy mala', 25: 'Mala', 50: 'Regular', 75: 'Buena', 100: 'Excelente' };
                const nearest = [0, 25, 50, 75, 100].reduce((a, c) => Math.abs(c - avg) < Math.abs(a - avg) ? c : a);
                p.push(`calidad: ${cLbl[nearest]}`);
            }
            return p.join(' · ');
        }
        case 'signos_vitales': {
            const temps = items.map(d => parseFloat(d.temperatura)).filter(v => v > 0);
            const fcs = items.map(d => parseInt(d.frecuencia_cardiaca)).filter(v => v > 0);
            const glucs = items.map(d => parseInt(d.glucosa)).filter(v => v > 0);
            let p = [];
            if (temps.length) p.push(`Temp: ${(temps.reduce((a, b) => a + b, 0) / temps.length).toFixed(1)}°C`);
            if (fcs.length) p.push(`FC: ${Math.round(fcs.reduce((a, b) => a + b, 0) / fcs.length)} bpm`);
            if (glucs.length) p.push(`Gluc: ${Math.round(glucs.reduce((a, b) => a + b, 0) / glucs.length)} mg/dL`);
            return p.join(' · ');
        }
        case 'comportamiento': {
            const moods = items.map(d => d.estado_animo).filter(Boolean);
            if (moods.length) {
                const freq = {};
                moods.forEach(m => { freq[m] = (freq[m] || 0) + 1; });
                const top = Object.entries(freq).sort((a, b) => b[1] - a[1])[0];
                return `Predominante: ${top[0]}`;
            }
            return '';
        }
        case 'medicacion': {
            const total = items.reduce((a, d) => a + (d.medicamentos_seleccionados?.length || 0) + (d.medicamentos_extra?.length || 0), 0);
            return total ? `${total} meds administrados` : '';
        }
        case 'higiene': {
            const tipos = items.map(d => d.tipo_higiene || d.tipo).filter(Boolean);
            if (tipos.length) {
                const freq = {};
                tipos.forEach(t => { freq[t] = (freq[t] || 0) + 1; });
                const top = Object.entries(freq).sort((a, b) => b[1] - a[1])[0];
                return `Más frecuente: ${top[0]}`;
            }
            return '';
        }
        case 'movilidad': {
            const durs = items.map(d => parseInt(d.duracion)).filter(v => !isNaN(v) && v > 0);
            return durs.length ? `Duración prom: ${Math.round(durs.reduce((a, b) => a + b, 0) / durs.length)} min` : '';
        }
        case 'eliminacion': {
            const panales = items.filter(d => d.cambio_panal === 'Sí').length;
            let txt = `${items.length} eventos`;
            if (panales) txt += ` · ${panales} cambio${panales > 1 ? 's' : ''} de pañal`;
            return txt;
        }
        case 'terapia': {
            const durs = items.map(d => parseInt(d.duracion)).filter(v => !isNaN(v) && v > 0);
            return durs.length ? `Total: ${durs.reduce((a, b) => a + b, 0)} min` : '';
        }
        default: return '';
    }
}

