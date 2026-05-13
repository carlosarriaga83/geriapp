<section id="viewExpediente" class="cd-view">
<div class="cd-exp-page">
    <div class="cd-exp-header">
        <h1><?= t('exp_title') ?></h1>
    </div>

    <!-- Filters -->
    <div class="cd-exp-filters">
        <input class="cd-input cd-exp-search" id="cdExpSearch" placeholder="<?= t('exp_search_ph') ?>">
        <select class="cd-input cd-select-native cd-exp-filter-tipo" id="cdExpFilterTipo">
            <option value=""><?= t('exp_all_types') ?></option>
            <option value="receta"><?= t('exp_tipo_receta') ?></option>
            <option value="laboratorio"><?= t('exp_tipo_laboratorio') ?></option>
            <option value="imagen"><?= t('exp_tipo_imagen') ?></option>
            <option value="interpretacion"><?= t('exp_tipo_interpretacion') ?></option>
            <option value="hospitalizacion"><?= t('exp_tipo_hospitalizacion') ?></option>
            <option value="legal"><?= t('exp_tipo_legal') ?></option>
            <option value="nota_enfermeria"><?= t('exp_tipo_nota_enfermeria') ?></option>
            <option value="nota_medico"><?= t('exp_tipo_nota_medico') ?></option>
        </select>
        <select class="cd-input cd-select-native cd-exp-filter-fuente" id="cdExpFilterFuente">
            <option value=""><?= t('exp_all_sources') ?></option>
            <option value="medico"><?= t('exp_fuente_medico') ?></option>
            <option value="familiar"><?= t('exp_fuente_familiar') ?></option>
            <option value="residente"><?= t('exp_fuente_residente') ?></option>
            <option value="otro"><?= t('exp_fuente_otro') ?></option>
        </select>
    </div>
    <div class="cd-exp-filters-row2">
        <div class="cd-exp-date-field">
            <div class="cd-exp-date-control">
                <input type="text" inputmode="numeric" class="cd-input cd-exp-date-input" id="cdExpFilterDesde" data-exp-date-filter="1" title="<?= t('exp_date_from') ?>" aria-label="<?= t('exp_date_from') ?>" autocomplete="off">
                <span class="cd-exp-date-picker" title="<?= t('exp_date_from') ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="date" class="cd-exp-date-native" id="cdExpFilterDesdeNative" data-exp-date-native="cdExpFilterDesde" data-no-fmt-hint="1" aria-label="<?= t('exp_date_from') ?>">
                </span>
            </div>
        </div>
        <span class="cd-exp-date-sep">—</span>
        <div class="cd-exp-date-field">
            <div class="cd-exp-date-control">
                <input type="text" inputmode="numeric" class="cd-input cd-exp-date-input" id="cdExpFilterHasta" data-exp-date-filter="1" title="<?= t('exp_date_to') ?>" aria-label="<?= t('exp_date_to') ?>" autocomplete="off">
                <span class="cd-exp-date-picker" title="<?= t('exp_date_to') ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="date" class="cd-exp-date-native" id="cdExpFilterHastaNative" data-exp-date-native="cdExpFilterHasta" data-no-fmt-hint="1" aria-label="<?= t('exp_date_to') ?>">
                </span>
            </div>
        </div>
        <button class="cd-btn-ghost-sm cd-exp-sort-btn" id="cdExpSortBtn" title="<?= t('exp_sort_desc') ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/></svg>
            <span id="cdExpSortLabel"><?= t('exp_sort_desc') ?></span>
        </button>
    </div>
    <div class="cd-exp-header-actions">
        <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdExpRefreshBtn" data-view-refresh="viewExpediente" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
        <button class="cd-exp-nm-btn cd-cat-btn" data-cat="notas_medico" data-perm-id="exp_open_notas_medico_btn">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h6"/><path d="M12 8v6"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <?= t('nm_title') ?>
        </button>
        <button class="cd-btn-add" id="cdExpAddBtn" data-perm-id="exp_add_document_btn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= t('exp_add') ?>
        </button>
    </div>

    <!-- Document timeline -->
    <div class="cd-exp-timeline" id="cdExpGrid"></div>
    <div class="cd-exp-empty" id="cdExpEmpty" style="display:none">
        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.35">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
        </svg>
        <p><?= t('exp_empty') ?></p>
        <span><?= t('exp_empty_hint') ?></span>
    </div>
    <div class="cd-exp-pager" id="cdExpPager" style="display:none">
        <button class="cd-btn-submit cd-btn-secondary" id="cdExpLoadMore"><?= t('exp_load_more') ?></button>
    </div>
</div>
</section>
