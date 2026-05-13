<section id="viewDashboard" class="cd-view<?= (($initialView ?? 'viewDashboard') === 'viewDashboard') ? ' active' : '' ?>">
<div class="cd-content-wrap">

    <div class="cd-dash-toolbar">
        <button class="cd-dash-toolbar-btn cd-print-day-report" id="cdPrintDayReport" data-perm-id="dash_print_day_report_btn" title="<?= t('btn_print_report') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span><?= t('btn_print_report') ?></span>
        </button>
        <span class="cd-care-toolbar-title"><?= t('cat_title') ?></span>
        <button class="cd-dash-toolbar-btn cd-dash-refresh-btn" id="cdDashRefreshBtn" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
        <span class="cd-version-label">v<?= APP_VERSION ?></span>
    </div>

    <div class="cd-dash-row">
        <div class="cd-dash-left">

            <!-- Category grid -->
            <?php if ($canViewCuidadosGrid): ?>
            <div class="cd-categories<?= !$canMakeRecords ? ' cd-categories--readonly' : '' ?>">

                <!-- Grupo: Cuidado básico -->
                <div class="cd-cat-group">
                    <h4 class="cd-cat-group-title"><?= t('cat_group_basico') ?></h4>
                    <div class="cd-cat-grid">
                        <button class="cd-cat-btn" data-cat="sueno" data-perm-id="dash_cat_sueno_btn">
                            <img src="assets/icons/moon-zzz.png" alt="Sueño" class="cd-cat-icon-img">
                            <span><?= t('cat_sueno') ?></span>
                            <span class="cd-cat-badge" data-cat-count="sueno"></span>
                            <span class="cd-cat-badge cd-sueno-badge" id="cdSuenoBadge"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="alimentacion" data-perm-id="dash_cat_alimentacion_btn">
                            <img src="assets/icons/food.png" alt="Alimentación" class="cd-cat-icon-img">
                            <span><?= t('cat_alimentacion') ?></span>
                            <span class="cd-cat-badge" data-cat-count="alimentacion"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="higiene" data-perm-id="dash_cat_higiene_btn">
                            <img src="assets/icons/handwash.png" alt="Higiene" class="cd-cat-icon-img">
                            <span><?= t('cat_higiene') ?></span>
                            <span class="cd-cat-badge" data-cat-count="higiene"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="eliminacion" data-perm-id="dash_cat_eliminacion_btn">
                            <img src="assets/icons/wc.png" alt="Eliminación" class="cd-cat-icon-img">
                            <span><?= t('cat_eliminacion') ?></span>
                            <span class="cd-cat-badge" data-cat-count="eliminacion"></span>
                            <span class="cd-cat-badge cd-heces-badge" id="cdHecesBadge"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="movilidad" data-perm-id="dash_cat_movilidad_btn">
                            <img src="assets/icons/walk.png" alt="Movilidad" class="cd-cat-icon-img">
                            <span><?= t('cat_movilidad') ?></span>
                            <span class="cd-cat-badge" data-cat-count="movilidad"></span>
                        </button>
                    </div>
                </div>

                <!-- Grupo: Salud y tratamiento -->
                <div class="cd-cat-group">
                    <h4 class="cd-cat-group-title"><?= t('cat_group_salud') ?></h4>
                    <div class="cd-cat-grid">
                        <button class="cd-cat-btn" data-cat="medicacion" data-perm-id="dash_cat_medicacion_btn">
                            <img src="assets/icons/pill.png" alt="Medicación" class="cd-cat-icon-img">
                            <span><?= t('cat_medicacion') ?></span>
                            <span class="cd-cat-badge" data-cat-count="medicacion"></span>
                            <span class="cd-cat-badge cd-med-pending-badge" id="cdMedPendingBadge"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="signos_vitales" data-perm-id="dash_cat_signos_vitales_btn">
                            <img src="assets/icons/pulse.png" alt="Signos vitales" class="cd-cat-icon-img">
                            <span><?= t('cat_signos_vitales') ?></span>
                            <span class="cd-cat-badge" data-cat-count="signos_vitales"></span>
                            <span class="cd-cat-badge cd-signos-badge" id="cdSignosBadge"></span>
                        </button>
                        <button class="cd-cat-btn" data-cat="terapia" data-perm-id="dash_cat_terapia_btn">
                            <img src="assets/icons/terapia.png" alt="Terapia" class="cd-cat-icon-img">
                            <span><?= t('cat_terapia') ?></span>
                            <span class="cd-cat-badge" data-cat-count="terapia"></span>
                        </button>
                    </div>
                </div>

                <!-- Grupo: Observaciones y notas -->
                <div class="cd-cat-group">
                    <h4 class="cd-cat-group-title"><?= t('cat_group_observa') ?></h4>
                    <div class="cd-cat-grid">
                        <button class="cd-cat-btn" data-cat="comportamiento" data-perm-id="dash_cat_comportamiento_btn">
                            <img src="assets/icons/head.png" alt="Comportamiento" class="cd-cat-icon-img">
                            <span><?= t('cat_comportamiento') ?></span>
                            <span class="cd-cat-badge" data-cat-count="comportamiento"></span>
                        </button>
                        <!-- INCIDENTES button (critical) -->
                        <button class="cd-cat-btn cd-cat-btn--incidente" data-cat="incidente" data-perm-id="dash_cat_incidente_btn">
                            <span class="cd-cat-mask cd-cat-mask--warning" aria-hidden="true"></span>
                            <span><?= t('cat_incidente') ?></span>
                            <span class="cd-cat-badge" data-cat-count="incidente"></span>
                        </button>
                        <!-- NOTAS button -->
                        <button class="cd-cat-btn cd-cat-btn--notas" data-cat="notas" data-perm-id="dash_cat_notas_btn">
                            <img src="assets/icons/notes.png" alt="" class="cd-cat-icon-img" aria-hidden="true">
                            <span><?= t('cat_notas') ?></span>
                            <span class="cd-cat-badge" data-cat-count="notas"></span>
                        </button>
                        <!-- NOTAS MÉDICAS button -->
                        <button class="cd-cat-btn cd-cat-btn--notas-medico<?= $canVerNotasMedico ? '' : ' cd-role-locked' ?>" data-cat="notas_medico" data-perm-id="dash_cat_notas_medico_btn" data-perm-id="dash_cat_notas_medico_btn" data-perm-id="dash_cat_notas_medico_btn"<?= $canVerNotasMedico ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a las notas médicas con tu rol actual."' ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11h6"/><path d="M12 8v6"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                            <span><?= t('cat_notas_medico') ?></span>
                            <span class="cd-cat-badge cd-nm-badge" id="cdNmBadge"></span>
                        </button>
                    </div>
                </div>

                <!-- Grupo: Información y avisos -->
                <div class="cd-cat-group">
                    <h4 class="cd-cat-group-title"><?= t('cat_group_info') ?></h4>
                    <div class="cd-cat-grid">
                        <!-- FICHA button -->
                        <button class="cd-cat-btn cd-cat-btn--ficha<?= $canSecFicha ? '' : ' cd-role-locked' ?>" data-cat="ficha" data-perm-id="dash_cat_ficha_btn" data-perm-id="dash_cat_ficha_btn" data-perm-id="dash_cat_ficha_btn"<?= $canSecFicha ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a la ficha del residente con tu rol actual."' ?>>
                            <img src="assets/icons/ficha.png" alt="Ficha" class="cd-cat-icon-img">
                            <span><?= t('cat_ficha') ?></span>
                        </button>
                        <!-- NOTIFICACIONES button -->
                        <button class="cd-cat-btn cd-cat-btn--notificaciones<?= $canSecFicha ? '' : ' cd-role-locked' ?>" data-cat="notificaciones" data-perm-id="dash_cat_notificaciones_btn" data-perm-id="dash_cat_notificaciones_btn" data-perm-id="dash_cat_notificaciones_btn"<?= $canSecFicha ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a notificaciones con tu rol actual."' ?>>
                            <img src="assets/icons/whatsapp.png" alt="Notificaciones" class="cd-cat-icon-img">
                            <span><?= t('cat_notificaciones') ?></span>
                            <span class="cd-cat-badge cd-cat-badge--alert" id="cdCatNotifBadge" style="display:none">0</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</section>
