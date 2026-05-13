<section id="viewRecords" class="cd-view">
<div class="cd-content-wrap">
    <div class="cd-form-header">
        <h2 class="cd-form-title"><?= t('nav_records') ?></h2>
        <button class="cd-form-back" data-back type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <div class="cd-dash-toolbar">
        <button class="cd-dash-toolbar-btn cd-print-day-report" id="cdPrintRecordReport" data-perm-id="rec_print_report_btn" title="<?= t('btn_print_report') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span><?= t('btn_print_report') ?></span>
        </button>
        <span class="cd-version-label">v<?= APP_VERSION ?></span>
        <button class="cd-dash-toolbar-btn cd-dash-refresh-btn" id="cdRecRefreshBtn" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
    </div>
</div>
<div class="cd-records">
    <div class="cd-records-card">
    <div class="cd-timeline-header">
        <div style="display:flex;align-items:center;gap:8px">
            <h3><?= t('tl_title') ?></h3>
            <button type="button" class="cd-tl-sort-btn" id="cdRecSortBtn" title="<?= t('tl_sort_asc') ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </button>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <span class="cd-timeline-count" id="cdRecCount">0 eventos</span>
        </div>
    </div>
    <div class="cd-tl-filters">
        <select class="cd-tl-cat-filter" id="cdRecCatFilter">
            <option value=""><?= t('tl_all_categories') ?></option>
        </select>
        <div class="cd-tl-search-wrap">
            <svg class="cd-tl-search-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="cd-tl-search" id="cdRecSearch" placeholder="<?= t('tl_search') ?>" autocomplete="off">
            <button type="button" class="cd-tl-search-clear" id="cdRecSearchClear" title="<?= t('tl_clear') ?>">&times;</button>
        </div>
    </div>
    <div class="cd-period-tabs" id="cdRecordPeriod">
        <span class="cd-tabs-pill" aria-hidden="true"></span>
        <button class="cd-period-tab active" data-period="dia"><?= t('period_day') ?></button>
        <button class="cd-period-tab" data-period="semana"><?= t('period_week') ?></button>
        <button class="cd-period-tab" data-period="mes"><?= t('period_month') ?></button>
    </div>
    <div class="cd-timeline" id="cdRecTimeline">
        <div class="cd-tl-empty" id="cdRecTimelineEmpty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3><?= t('tl_empty_title') ?></h3>
            <p><?= t('tl_empty_desc') ?></p>
        </div>
    </div>
    </div>
</div>
</section>
