<section id="viewFormNotasMedico" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title">
            <svg class="cd-form-title-icon cd-form-title-icon--svg cd-form-title-icon--notas-medico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11h6"/><path d="M12 8v6"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <?= t('nm_title') ?>
        </h2>
        <div class="cd-form-header-actions">
            <button class="cd-nm-report-btn" id="cdNmReportBtn" data-perm-id="nm_print_report_btn" title="<?= t('btn_print_report') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <?= t('btn_print_report') ?>
            </button>
            <button class="cd-form-back" data-back>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                <?= t('form_back') ?>
            </button>
        </div>
    </div>

    <!-- Formulario (solo médicos) → encima de la nota vigente -->
    <div id="cdNmFormWrap"></div>

    <!-- Alertas al médico (pendientes) -->
    <div id="cdNmAlertas" class="cd-nm-alertas"></div>

    <!-- Nota vigente -->
    <div id="cdNmCurrent" class="cd-nm-current"></div>

    <!-- Historial de alertas -->
    <div id="cdNmAlertasHist" class="cd-nm-alertas-hist"></div>

    <!-- Historial archivado -->
    <div id="cdNmArchive" class="cd-nm-archive"></div>
</div>
</section>
