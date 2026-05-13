<?php
/**
 * GeriApp — Portal Superadmin
 * UI de gestión global: sincronización BD, instituciones, planes, usuarios, logs.
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/auth_middleware.php';

$pageTitle   = 'Superadmin';
$userName    = htmlspecialchars($_SESSION['sa_name'] ?? 'Superadmin');
$faviconTint = '#f59e0b';
$extraStyles = '<link rel="stylesheet" href="' . BASE_URL . '/superadmin/assets/css/superadmin.css?v=' . time() . '">';
?>
<?php require_once dirname(__DIR__) . '/includes/head.php'; ?>

<div class="sa-app">

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<header class="sa-header">
    <div class="sa-header-left">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <h1>GeriApp <span>Superadmin</span></h1>
    </div>
    <div class="sa-header-right">
        <span class="sa-user"><?= $userName ?></span>
        <button class="sa-icon-btn" id="btnThemeToggle" title="Tema">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="<?= BASE_URL ?>/cuidados.php" class="sa-icon-btn" title="Ir a Cuidados">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </a>
        <a href="<?= BASE_URL ?>/superadmin/logout.php" class="sa-icon-btn" title="Cerrar sesión">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</header>

<!-- Floating hamburger (mobile only) -->
<button class="sa-fab-hamburger" id="saHamburger" title="Menú">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<!-- ── Layout: Sidebar + Content ───────────────────────────────────────── -->
<div class="sa-layout">

<!-- ── Sidebar Nav ─────────────────────────────────────────────────────── -->
<div class="sa-sidenav-overlay" id="saSidenavOverlay"></div>
<aside class="sa-sidenav" id="saSidenav">
    <nav class="sa-sidenav-items">
        <button class="sa-sidenav-item active" data-tab="sync">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10"/><path d="M20.49 15a9 9 0 01-14.85 3.36L1 14"/></svg>
            <span>Sincronización BD</span>
        </button>
        <button class="sa-sidenav-item" data-tab="owner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></svg>
            <span>Panel Dueño</span>
        </button>
        <button class="sa-sidenav-item" data-tab="recursos">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.29 7L12 12l8.71-5"/><path d="M12 22V12"/></svg>
            <span>Recursos</span>
        </button>
        <button class="sa-sidenav-item" data-tab="onboarding">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4.55 4.55a1 1 0 001.41 0L19 8.5"/><circle cx="12" cy="12" r="10"/></svg>
            <span>Onboarding 1-click</span>
        </button>
        <button class="sa-sidenav-item" data-tab="eventqr">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM19 19h2v2h-2zM19 14h2v2h-2zM14 19h2v2h-2z"/></svg>
            <span>QR Eventos</span>
        </button>
        <button class="sa-sidenav-item" data-tab="integraciones">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4"/><path d="M7 9V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4"/><path d="M12 12v9"/><path d="M8 12h8"/><rect x="5" y="9" width="14" height="6" rx="2"/></svg>
            <span>Integraciones</span>
        </button>
        <button class="sa-sidenav-item" data-tab="instituciones">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Instituciones</span>
        </button>
        <button class="sa-sidenav-item" data-tab="planes">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span>Planes</span>
        </button>
        <button class="sa-sidenav-item" data-tab="usuarios">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <span>Usuarios</span>
        </button>
        <button class="sa-sidenav-item" data-tab="despliegue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>Despliegue</span>
        </button>
        <button class="sa-sidenav-item" data-tab="checkdb">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Auditoría BD</span>
        </button>
        <button class="sa-sidenav-item" data-tab="mantenimiento">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            <span>Mantenimiento</span>
        </button>
        <button class="sa-sidenav-item" data-tab="logs">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>Logs del Sistema</span>
        </button>
        <button class="sa-sidenav-item" data-tab="migraciones">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></svg>
            <span>Migraciones</span>
        </button>
        <button class="sa-sidenav-item" data-tab="push">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <span>Push Notifications</span>
        </button>
        <button class="sa-sidenav-item" data-tab="cifrado">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <span>Cifrado §5</span>
        </button>
        <button class="sa-sidenav-item" data-tab="perfil">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Perfil</span>
        </button>
        <button class="sa-sidenav-item" data-tab="seeding">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            <span>Demo Seeding</span>
        </button>
    </nav>
</aside>

<!-- ── Main Content ────────────────────────────────────────────────────── -->
<div class="sa-main">
<div class="sa-content">
<div class="sa-stats" id="saStats">
    <div class="sa-stat-card">
        <div class="sa-stat-value" id="statInst">—</div>
        <div class="sa-stat-label">Instituciones</div>
    </div>
    <div class="sa-stat-card">
        <div class="sa-stat-value" id="statUsers">—</div>
        <div class="sa-stat-label">Usuarios</div>
    </div>
    <div class="sa-stat-card">
        <div class="sa-stat-value" id="statActive">—</div>
        <div class="sa-stat-label">Activas</div>
    </div>
    <div class="sa-stat-card">
        <div class="sa-stat-value" id="statPlans">—</div>
        <div class="sa-stat-label">Planes</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Recursos
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelRecursos">
    <div class="sa-section-header">
        <div>
            <h2>Recursos</h2>
            <p class="cd-cfg-desc">Monitoreo de consumo para hosting compartido: conexiones MySQL, almacenamiento, bases de datos y límites PHP.</p>
        </div>
        <button class="cd-btn-submit cd-btn-secondary" id="btnRefreshResources" style="padding:6px 12px;font-size:12px" title="Actualizar ahora">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Actualizar
        </button>
    </div>
    <div class="sa-resource-meta" id="resourceUpdatedAt">Sin actualizar</div>
    <div class="sa-stats sa-stats-wide" id="resourceKpis"></div>
    <section class="sa-resource-card sa-resource-chart-card">
        <h3>Histórico de consumo</h3>
        <div id="resourceHistoryChart" class="sa-resource-chart-empty">Sin datos históricos todavía.</div>
    </section>
    <div class="sa-resource-grid">
        <section class="sa-resource-card" id="resourceDbLimit"></section>
        <section class="sa-resource-card" id="resourceStorage"></section>
    </div>
    <div class="sa-resource-grid sa-maintenance-grid">
        <section class="sa-resource-card sa-maint-card sa-maint-card-wide">
            <h3>Almacenamiento GeriApp</h3>
            <div id="resourcePathBreakdown"></div>
        </section>
        <section class="sa-resource-card sa-maint-card">
            <h3>Bases de datos</h3>
            <div id="resourceDbBreakdown"></div>
        </section>
    </div>
    <section class="sa-resource-card">
        <h3>Notas del límite Hostinger</h3>
        <div id="resourceHostingerNote"></div>
    </section>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Sincronización BD
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel active" id="panelSync">
    <h2>Sincronización de Base de Datos</h2>
    <p class="cd-cfg-desc">Sincroniza datos entre perfiles de conexión (producción ↔ local). Selecciona origen, destino y las bases de datos a copiar.</p>

    <!-- ── Perfiles de conexión ──────────────────────────────────────── -->
    <div class="sa-profiles-section">
        <div class="sa-section-header">
            <h3>Perfiles de Conexión</h3>
            <button class="cd-btn-submit cd-btn-secondary" id="btnNewProfile" style="padding:6px 12px;font-size:12px">+ Nuevo Perfil</button>
        </div>
        <div class="cd-cfg-users-list" id="profileList"></div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cd-border);margin:20px 0">

    <div class="cd-cfg-form">
        <!-- ── Two-column grid: Source | Target ─────────────────── -->
        <div class="sa-sync-grid">
            <!-- ── LEFT: Origen (Source) ─────────────────────────── -->
            <div class="sa-sync-col">
                <div class="cd-form-group">
                    <label class="cd-form-label">Origen (Source)</label>
                    <select class="cd-input cd-select-native" id="syncSource">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="cd-form-group">
                    <button class="cd-btn-submit cd-btn-secondary" id="btnTestSource" disabled>Probar conexión origen</button>
                    <div class="sa-conn-result" id="resultSource"></div>
                </div>
                <div class="cd-form-group" id="dbListWrap" style="display:none">
                    <label class="cd-form-label">Bases de datos disponibles</label>
                    <div class="sa-db-list" id="dbList"></div>
                    <div class="sa-db-actions">
                        <button class="sa-link-btn" id="btnSelectAll">Seleccionar todas</button>
                        <button class="sa-link-btn" id="btnSelectNone">Deseleccionar todas</button>
                    </div>
                </div>
                <div class="cd-form-group">
                    <button class="cd-btn-submit cd-btn-secondary" id="btnListDbs" disabled>Listar bases de datos del origen</button>
                </div>
            </div>

            <!-- ── RIGHT: Destino (Target) ───────────────────────── -->
            <div class="sa-sync-col">
                <div class="cd-form-group">
                    <label class="cd-form-label">Destino (Target)</label>
                    <select class="cd-input cd-select-native" id="syncTarget">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="cd-form-group">
                    <button class="cd-btn-submit cd-btn-secondary" id="btnTestTarget" disabled>Probar conexión destino</button>
                    <div class="sa-conn-result" id="resultTarget"></div>
                </div>
                <div class="cd-form-group">
                    <div class="sa-sync-options">
                        <label class="sa-switch-label">
                            <input type="checkbox" id="syncCreateDb">
                            <span class="sa-switch-track"></span>
                            <span>Crear BD en destino si no existe</span>
                        </label>
                        <label class="sa-switch-label" style="margin-top:8px">
                            <input type="checkbox" id="syncStructureOnly">
                            <span class="sa-switch-track"></span>
                            <span>Solo estructura (sin datos)</span>
                        </label>
                    </div>
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label">Nombre alternativo BD destino <small style="font-weight:400;color:var(--cd-text-muted)">(opcional)</small></label>
                    <input class="cd-input" id="syncTargetDbName" placeholder="Dejar vacío para usar el mismo nombre">
                </div>
            </div>
        </div>

        <!-- ── Full-width below: Progress, Results, Action ──────── -->

        <!-- Progress -->
        <div id="syncProgress" style="display:none">
            <div class="sa-progress-bar"><div class="sa-progress-fill" id="syncProgressFill"></div></div>
            <div class="sa-progress-log" id="syncLog"></div>
        </div>

        <!-- Results -->
        <div id="syncResults" style="display:none"></div>

        <!-- Sync action -->
        <div class="cd-cfg-actions">
            <button class="cd-btn-submit sa-btn-sync" id="btnSync" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                Ejecutar sincronización
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Instituciones
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelInstituciones">
    <h2>Instituciones</h2>
    <p class="cd-cfg-desc">Gestiona las instituciones registradas en la plataforma.</p>
    <div class="sa-filter-bar">
        <select class="cd-input cd-select-native" id="instFilterEstado" style="max-width:180px">
            <option value="">Todos los estados</option>
            <option value="activa">Activa</option>
            <option value="trial">Trial</option>
            <option value="suspendida">Suspendida</option>
            <option value="archivada">Archivada</option>
        </select>
        <div class="sa-search-wrap">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="cd-input" id="instSearch" placeholder="Buscar institución, admin, BD o plan">
        </div>
        <button class="cd-btn-submit cd-btn-secondary" onclick="loadInstituciones()" style="padding:6px 12px;font-size:12px" title="Actualizar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Actualizar
        </button>
        <button class="cd-btn-submit" id="btnNewInst" style="padding:6px 12px;font-size:12px;margin-left:auto">+ Nueva Institución</button>
    </div>
    <div class="cd-cfg-users-list" id="instList"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Planes
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelPlanes">
    <h2>Planes y Licencias</h2>
    <p class="cd-cfg-desc">Administra los paquetes (Básico/Intermedio/Empresarial), sus precios multi-divisa y los add-ons de asientos extra.</p>

    <!-- Status de la integración Stripe -->
    <div id="stripeStatusPanel" style="margin:0 0 16px;padding:14px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-bg);font-size:13px">
        <div style="color:var(--cd-text-muted)">Cargando estado de Stripe…</div>
    </div>

    <div class="cd-cfg-actions" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px">
        <button class="cd-btn-submit cd-btn-secondary" id="btnNewPlan">+ Nuevo Plan</button>
        <button class="cd-btn-submit cd-btn-secondary" id="btnNewAddon">+ Nuevo Add-on</button>
        <button class="cd-btn-submit" id="btnSeedBilling" style="background:#ca8a04;color:#fff" title="Inserta los 7 paquetes y los add-ons estándar (idempotente)">⚡ Sembrar paquetes</button>
        <button class="cd-btn-submit" id="btnSyncStripe" style="background:#635bff;color:#fff" title="Crea Products + Prices en Stripe para todo lo que tenga precio activo y aún no esté sincronizado">⇅ Sincronizar con Stripe</button>
    </div>

    <h3 style="margin:16px 0 8px;font-size:13px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Planes</h3>
    <div class="cd-cfg-users-list" id="planList">
        <div class="sa-loading">Cargando planes…</div>
    </div>

    <h3 style="margin:24px 0 8px;font-size:13px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Add-ons (asientos extra)</h3>
    <div class="cd-cfg-users-list" id="addonList">
        <div class="sa-loading">Cargando add-ons…</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: QR Eventos
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelEventqr">
    <h2>Invitaciones GeriApp por QR</h2>
    <p class="cd-cfg-desc">Genera QR masivos para eventos. Cada registro crea una institución nueva desde cero, aplica los defaults de institución y deja al usuario como administrador.</p>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,360px);gap:16px;align-items:start">
        <div style="padding:14px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-bg)">
            <div style="display:grid;grid-template-columns:1fr 120px;gap:10px;margin-bottom:10px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--cd-text-muted);margin-bottom:5px">Paquete</label>
                    <select class="cd-input cd-select-native" id="eventQrPlan"></select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--cd-text-muted);margin-bottom:5px">Vigencia</label>
                    <input class="cd-input" id="eventQrDays" type="number" min="1" max="365" value="30">
                </div>
            </div>
            <div style="margin-bottom:10px">
                <label style="display:block;font-size:12px;font-weight:700;color:var(--cd-text-muted);margin-bottom:5px">Nombre interno del evento</label>
                <input class="cd-input" id="eventQrName" maxlength="120" placeholder="Ej. Expo salud octubre">
            </div>
            <p id="eventQrHint" style="font-size:12px;color:var(--cd-text-muted);line-height:1.5;margin:0 0 12px"></p>
            <button class="cd-btn-submit" onclick="createEventQr()">Generar QR</button>
        </div>

        <div id="eventQrResult" style="min-height:220px;padding:14px;border:1px dashed var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);display:flex;align-items:center;justify-content:center;color:var(--cd-text-muted);font-size:13px;text-align:center">
            El QR generado aparecerá aquí.
        </div>
    </div>

    <h3 style="margin:20px 0 8px;font-size:13px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">QR generados</h3>
    <div class="cd-cfg-users-list" id="eventQrList">
        <div class="sa-loading">Cargando invitaciones…</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Integraciones
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelIntegraciones">
    <h2>Integraciones globales</h2>
    <p class="cd-cfg-desc">Audita los defaults cargados desde <code>conf/.env</code>. Las llaves y contraseñas se muestran enmascaradas y se aplican a instituciones nuevas creadas por QR Evento u Onboarding.</p>

    <div class="sa-int-actions">
        <button class="cd-btn-submit cd-btn-secondary" onclick="loadIntegraciones()">Actualizar</button>
        <button class="cd-btn-submit" onclick="applyIntegrationDefaults()">Aplicar defaults a campos vacíos</button>
    </div>

    <div class="sa-int-grid" id="integrationsCards">
        <div class="sa-loading">Cargando integraciones…</div>
    </div>

    <div class="sa-int-test">
        <div>
            <h3>Prueba SMTP global</h3>
            <p>Envía una prueba usando los valores actuales de <code>conf/.env</code>, sin guardar secretos en el navegador.</p>
        </div>
        <div class="sa-int-test-form">
            <input class="cd-input" id="integrationTestEmail" type="email" placeholder="correo@dominio.com">
            <button class="cd-btn-submit cd-btn-secondary" onclick="testGlobalSmtp()">Enviar prueba</button>
        </div>
    </div>

    <div class="sa-int-coverage" id="integrationsCoverage"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Usuarios
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelUsuarios">
    <h2>Usuarios Globales</h2>
    <p class="cd-cfg-desc">Vista global de todos los usuarios registrados en la plataforma.</p>
    <div class="sa-filter-bar">
        <input class="cd-input" id="userSearch" placeholder="Buscar por nombre o email…" style="max-width:300px">
        <select class="cd-input cd-select-native" id="userFilterRol" style="max-width:160px">
            <option value="">Todos los roles</option>
            <option value="superadmin">Superadmin</option>
            <option value="admin">Admin</option>
            <option value="cuidador">Cuidador</option>
            <option value="medico">Médico</option>
            <option value="familiar">Familiar</option>
        </select>
        <button class="cd-btn-submit cd-btn-secondary" onclick="loadUsuarios()" style="padding:6px 12px;font-size:12px" title="Actualizar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Actualizar
        </button>
        <button class="cd-btn-submit" id="btnNewUser" style="padding:6px 12px;font-size:12px;margin-left:auto">+ Nuevo Usuario</button>
    </div>
    <div class="cd-cfg-users-list" id="userList">
        <div class="sa-loading">Cargando usuarios…</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Despliegue
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelDespliegue">
    <div class="sa-split-layout">
        <div class="sa-split-main">
            <h2>Despliegue de Base de Datos</h2>
            <p class="cd-cfg-desc">Crea bases de datos tenant para instituciones que aún usan la BD compartida. Cada institución seleccionada recibirá su propia BD con el schema completo.</p>

            <div class="sa-section-header" style="margin-bottom:12px">
                <h3>Instituciones sin BD propia</h3>
                <button class="cd-btn-submit cd-btn-secondary" onclick="loadPendingDeploy()" style="padding:6px 12px;font-size:12px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                    Actualizar
                </button>
            </div>
            <div class="cd-cfg-users-list" id="deployList"><div class="sa-loading">Cargando…</div></div>
            <div class="sa-db-actions" id="deployActions" style="display:none;margin-top:12px">
                <button class="sa-link-btn" onclick="toggleAllDeploy(true)">Seleccionar todas</button>
                <button class="sa-link-btn" onclick="toggleAllDeploy(false)">Deseleccionar</button>
            </div>
            <div class="cd-cfg-actions" style="margin-top:16px">
                <button class="cd-btn-submit sa-btn-sync" id="btnRunDeploy" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>
                    Crear bases de datos seleccionadas
                </button>
            </div>
            <div id="deployResults" style="display:none;margin-top:16px"></div>
        </div>
        <aside class="sa-split-sidebar" id="deploySidebar">
            <h4>Información</h4>
            <div class="sa-sidebar-info">
                <p>El despliegue creará una base de datos independiente para cada institución seleccionada usando el schema <code>schema_tenant.sql</code>.</p>
                <p>Las instituciones que ya tienen BD propia no aparecen en esta lista.</p>
                <p><strong>Formato BD:</strong> <code>geriapp_i{ID}</code></p>
            </div>
        </aside>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Auditoría BD
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelCheckdb">
    <div class="sa-split-layout">
        <div class="sa-split-main">
            <h2>Auditoría de Base de Datos</h2>
            <p class="cd-cfg-desc">Compara la estructura esperada contra la real: tablas, columnas, tipos, índices.</p>
            <div class="cd-form-group" style="margin-bottom:12px;max-width:320px">
                <label class="cd-form-label">Base de datos</label>
                <select class="cd-input cd-select-native" id="checkdbProfile">
                    <option value="">Local (por defecto)</option>
                </select>
            </div>
            <div class="sa-section-header" style="margin-bottom:12px">
                <h3 id="checkdbSummary">Sin verificar</h3>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <button class="cd-btn-submit" id="btnRunCheckDb" style="padding:6px 14px;font-size:12px">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Ejecutar auditoría
                    </button>
                    <button class="cd-btn-submit" id="btnFixAllDb" style="padding:6px 14px;font-size:12px;background:var(--cd-warning,#f59e0b);color:#000;display:none">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        Corregir todo
                    </button>
                </div>
            </div>
            <div class="cd-cfg-users-list" id="checkdbList"></div>
        </div>
        <aside class="sa-split-sidebar" id="checkdbSidebar">
            <h4>Detalle</h4>
            <div class="sa-sidebar-info" id="checkdbDetail">
                <p>Selecciona una tabla para ver sus problemas y ejecutar correcciones.</p>
            </div>
        </aside>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Mantenimiento
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelMantenimiento">
    <div class="sa-section-header">
        <div>
            <h2>Mantenimiento</h2>
            <p class="cd-cfg-desc">Herramientas de saneamiento controlado para datos operativos y normalizaciones puntuales.</p>
        </div>
        <button class="cd-btn-submit cd-btn-secondary" id="btnScanInvalidInvites" style="padding:6px 12px;font-size:12px" title="Analizar registros inválidos">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
            Analizar
        </button>
    </div>
    <div class="cd-form-group" style="margin-bottom:14px;max-width:420px">
        <label class="cd-form-label">Base de datos</label>
        <select class="cd-input cd-select-native" id="maintenanceProfile">
            <option value="">Local (por defecto)</option>
        </select>
    </div>
    <div class="sa-resource-grid sa-maintenance-grid">
        <section class="sa-resource-card sa-maint-card sa-maint-card-wide">
            <h3>Invitaciones sin destino</h3>
            <p class="cd-cfg-desc" style="margin-top:-4px">Elimina registros de <code>invitaciones</code> sin correo ni teléfono. Primero se analiza; la eliminación requiere confirmación.</p>
            <div id="maintenanceInvalidInvites"><div class="sa-loading">Sin analizar.</div></div>
            <div class="cd-cfg-actions" style="margin-top:12px">
                <button class="cd-btn-submit sa-btn-danger" id="btnCleanupInvalidInvites" style="padding:6px 14px;font-size:12px" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Eliminar inválidas
                </button>
            </div>
        </section>
        <section class="sa-resource-card sa-maint-card">
            <h3>Normalización de teléfonos</h3>
            <p class="cd-cfg-desc" style="margin-top:-4px">Actualiza teléfonos nacionales de 10 dígitos a formato +52 y respeta campos cifrados de residentes.</p>
            <div class="cd-cfg-actions" style="margin-top:12px">
                <button class="cd-btn-submit" id="btnNormalizePhones" style="padding:6px 14px;font-size:12px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.91.32 1.8.59 2.65a2 2 0 0 1-.45 2.11L8.09 9.64a16 16 0 0 0 6.27 6.27l1.16-1.16a2 2 0 0 1 2.11-.45c.85.27 1.74.47 2.65.59A2 2 0 0 1 22 16.92z"/></svg>
                    Normalizar teléfonos
                </button>
            </div>
        </section>
        <section class="sa-resource-card sa-maint-card">
            <h3>Normalizar roles de usuarios</h3>
            <p class="cd-cfg-desc" style="margin-top:-4px">Detecta y corrige inconsistencias entre <code>usuarios.rol</code> y <code>usuario_instituciones.rol</code>. El pivote por institución tiene prioridad.</p>
            <div id="maintenanceNormalizeRoles"><div class="sa-loading">Sin analizar.</div></div>
            <div class="cd-cfg-actions" style="margin-top:12px">
                <button class="cd-btn-submit cd-btn-secondary" id="btnScanNormalizeRoles" style="padding:6px 14px;font-size:12px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
                    Analizar
                </button>
                <button class="cd-btn-submit" id="btnRunNormalizeRoles" style="padding:6px 14px;font-size:12px" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                    Normalizar roles
                </button>
            </div>
        </section>
        <section class="sa-resource-card sa-maint-card sa-maint-card-wide">
            <h3>Contactos legacy a familiares</h3>
            <p class="cd-cfg-desc" style="margin-top:-4px">Consolida los campos antiguos <code>contacto_*</code> de residentes dentro de <code>contactos_json</code>, para que se reflejen en Familiares aunque no tengan invitación ni cuenta registrada.</p>
            <div id="maintenanceLegacyFamilyContacts"><div class="sa-loading">Sin analizar.</div></div>
            <div class="cd-cfg-actions" style="margin-top:12px">
                <button class="cd-btn-submit cd-btn-secondary" id="btnScanLegacyFamilyContacts" style="padding:6px 14px;font-size:12px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
                    Analizar contactos
                </button>
                <button class="cd-btn-submit" id="btnConvertLegacyFamilyContacts" style="padding:6px 14px;font-size:12px" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                    Convertir contactos
                </button>
            </div>
        </section>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Logs
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelLogs">
    <h2>Logs del Sistema</h2>
    <p class="cd-cfg-desc">Registro de actividad del sistema.</p>
    <div class="sa-filter-bar">
        <select class="cd-input cd-select-native" id="logFilterNivel" style="max-width:160px">
            <option value="">Todos los niveles</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
            <option value="debug">Debug</option>
        </select>
        <button class="cd-btn-submit cd-btn-secondary" id="btnRefreshLogs" style="max-width:160px">Actualizar</button>
    </div>
    <div class="sa-logs-list" id="logList">
        <div class="sa-loading">Cargando logs…</div>
    </div>
    <div class="sa-pagination" id="logPagination"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Migraciones
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelMigraciones">
    <h2>Migraciones de Base de Datos</h2>
    <p class="cd-cfg-desc">Ejecuta archivos de migración (db/migrate_*.php) directamente desde el portal sin necesidad de acceder al servidor.</p>

    <!-- Full migration runner -->
    <div style="margin-bottom:20px;padding:16px;border:1px solid var(--cd-border);border-radius:var(--cd-radius-lg);background:var(--cd-surface)">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
                <strong style="font-size:0.95rem">Migración Completa</strong>
                <p style="margin:4px 0 0;font-size:0.8125rem;color:var(--cd-text-muted)">Ejecuta TODAS las migraciones (master + tenant) de forma idempotente. Seguro de ejecutar múltiples veces.</p>
            </div>
            <button class="cd-btn-submit" style="padding:8px 18px;font-size:13px;white-space:nowrap" onclick="runFullMigration()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Ejecutar Todo
            </button>
        </div>
        <div id="fullMigrationOutput" style="display:none;margin-top:12px"></div>
    </div>

    <h3 style="font-size:0.95rem;margin-bottom:8px">Archivos individuales</h3>
    <div class="cd-cfg-users-list" id="migrationList"></div>

    <!-- CIE-10 removido: migrado a MediApp -->
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Push Notifications
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelPush">
    <div class="sa-section-header" style="margin-bottom:16px">
        <h2 style="margin:0">Push Notifications</h2>
        <button class="cd-btn-submit cd-btn-secondary" onclick="loadPushData()" style="padding:6px 12px;font-size:12px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10"/><path d="M20.49 15a9 9 0 01-14.85 3.36L1 14"/></svg>
            Actualizar
        </button>
    </div>
    <p class="cd-cfg-desc">Gestiona tokens de push, envía notificaciones individuales o masivas por institución.</p>

    <!-- Stats cards -->
    <div class="sa-stats" id="pushStats" style="margin-bottom:20px">
        <div class="sa-stat-card">
            <div class="sa-stat-value" id="pushStatTotal">—</div>
            <div class="sa-stat-label">Tokens registrados</div>
        </div>
        <div class="sa-stat-card">
            <div class="sa-stat-value" id="pushStatUsers">—</div>
            <div class="sa-stat-label">Usuarios con push</div>
        </div>
        <div class="sa-stat-card">
            <div class="sa-stat-value" id="pushStatAndroid">—</div>
            <div class="sa-stat-label">Android</div>
        </div>
        <div class="sa-stat-card">
            <div class="sa-stat-value" id="pushStatIos">—</div>
            <div class="sa-stat-label">iOS</div>
        </div>
    </div>

    <!-- Enviar push -->
    <div class="sa-profiles-section" style="margin-bottom:20px">
        <div class="sa-section-header">
            <h3>Enviar Push Notification</h3>
        </div>
        <div class="cd-cfg-form" style="max-width:560px">
            <div class="cd-form-group">
                <label class="cd-form-label">Destino</label>
                <select class="cd-input cd-select-native" id="pushTarget" onchange="onPushTargetChange()">
                    <option value="user">Usuario específico</option>
                    <option value="institution">Institución completa</option>
                    <option value="all">Todos los usuarios</option>
                </select>
            </div>
            <div class="cd-form-group" id="pushUserGroup">
                <label class="cd-form-label">Usuario</label>
                <select class="cd-input cd-select-native" id="pushUserId"></select>
            </div>
            <div class="cd-form-group" id="pushInstGroup" style="display:none">
                <label class="cd-form-label">Institución</label>
                <select class="cd-input cd-select-native" id="pushInstId"></select>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Título</label>
                <input type="text" class="cd-input" id="pushTitle" placeholder="Título de la notificación">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Mensaje</label>
                <textarea class="cd-input" id="pushBody" rows="3" placeholder="Cuerpo de la notificación" style="resize:vertical"></textarea>
            </div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" id="btnSendPush" onclick="sendPush()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Enviar
                </button>
            </div>
            <div id="pushSendResult" style="margin-top:12px;font-size:13px;display:none"></div>
        </div>
    </div>

    <!-- Tokens por institución -->
    <div class="sa-profiles-section" style="margin-bottom:20px">
        <div class="sa-section-header">
            <h3>Tokens por Institución</h3>
        </div>
        <div class="cd-cfg-users-list" id="pushInstList"></div>
    </div>

    <!-- Lista de tokens -->
    <div class="sa-profiles-section">
        <div class="sa-section-header">
            <h3>Todos los Tokens</h3>
        </div>
        <div style="margin-bottom:12px">
            <input class="cd-input" id="pushTokenSearch" placeholder="Buscar por usuario, email o institución…" style="max-width:400px">
        </div>
        <div class="cd-cfg-users-list" id="pushTokenList"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Cifrado §5 PHIPA
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelCifrado">
    <h2>Cifrado de Datos §5</h2>
    <p class="cd-cfg-desc">
        Gestión del cifrado AES-256-GCM para campos con
        <strong>PHI</strong> (Protected Health Information — Información de Salud Protegida)
        y <strong>PII</strong> (Personally Identifiable Information — Información de Identificación Personal)
        según <strong>PHIPA</strong> (Personal Health Information Protection Act)
        y <strong>NOM-024-SSA3</strong>.
    </p>

    <!-- Pre-flight checks -->
    <div class="sa-enc-preflight" id="encPreflight">
        <h3 style="margin:0 0 12px;font-size:15px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Verificación previa
        </h3>
        <div class="sa-enc-check" id="encCheckKey">
            <span class="sa-enc-check-icon">⏳</span>
            <span><strong>DATA_ENCRYPTION_KEY</strong> en conf/.env (64 hex = 256 bits)</span>
        </div>
        <div class="sa-enc-check" id="encCheckOpenssl">
            <span class="sa-enc-check-icon">⏳</span>
            <span><strong>OpenSSL AES-256-GCM</strong> disponible en el servidor</span>
        </div>
        <div class="sa-enc-check" id="encCheckBackup">
            <span class="sa-enc-check-icon">⚠️</span>
            <span>Se recomienda crear un <strong>respaldo completo</strong> antes de cifrar datos</span>
        </div>
    </div>

    <!-- Encryption mode switch -->
    <div class="sa-enc-switch-wrap" id="encSwitchWrap" style="margin-top:16px;padding:16px;border-radius:10px;border:1px solid var(--cd-border);background:var(--cd-surface)">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h3 style="margin:0 0 4px;font-size:15px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Modo de lectura
                </h3>
                <p style="margin:0;font-size:13px;color:var(--cd-text-muted)">
                    Controla si la aplicación lee datos desde las columnas cifradas o las originales (texto plano).
                    Útil para verificar que el cifrado funciona correctamente antes de consolidar.
                </p>
            </div>
            <label class="sa-enc-toggle" style="flex-shrink:0">
                <input type="checkbox" id="encReadSwitch" onchange="encToggleReadMode(this.checked)">
                <span class="sa-enc-toggle-slider"></span>
                <span class="sa-enc-toggle-label" id="encReadLabel">Cifrado</span>
            </label>
        </div>
    </div>

    <!-- Info box -->
    <div class="sa-enc-info">
        <h3 style="margin:0 0 8px;font-size:14px">ℹ️ ¿Cómo funciona el cifrado?</h3>
        <p style="margin:0 0 6px;font-size:13px;line-height:1.5">
            El proceso de cifrado utiliza la estrategia <strong>dual-write</strong> (escritura dual) para garantizar
            cero tiempo de inactividad. Se ejecuta en 4 fases:
        </p>
        <ol style="margin:0;padding-left:20px;font-size:13px;line-height:1.7">
            <li><strong>Preparar:</strong> se agrega una columna <code>_enc</code> junto a cada campo seleccionado (ALTER TABLE).</li>
            <li><strong>Migrar:</strong> se cifran los datos existentes en lotes y se escriben en las columnas <code>_enc</code>.</li>
            <li><strong>Activar:</strong> la aplicación empieza a leer de las columnas cifradas (dual-write: escribe en ambas).</li>
            <li><strong>Consolidar:</strong> elimina columnas originales y renombra <code>_enc</code> → original. <strong style="color:var(--cd-danger)">Irreversible.</strong></li>
            <li><strong>Rollback:</strong> se puede revertir en fases 1-3 eliminando las columnas <code>_enc</code>.</li>
        </ol>
    </div>

    <!-- Field selection table -->
    <div style="margin-top:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
            <h3 style="margin:0;font-size:15px">Campos a cifrar</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="cd-btn-submit sa-btn-sm" id="btnEncSelAll" onclick="encToggleAll(true)">Seleccionar todos</button>
                <button class="cd-btn-submit sa-btn-sm sa-btn-outline" id="btnEncSelNone" onclick="encToggleAll(false)">Deseleccionar</button>
                <button class="cd-btn-submit sa-btn-sm" id="btnEncRefresh" onclick="loadEncryptionStatus()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10"/><path d="M20.49 15a9 9 0 01-14.85 3.36L1 14"/></svg>
                    Actualizar
                </button>
            </div>
        </div>
        <div class="sa-enc-table-wrap">
            <table class="sa-enc-table" id="encFieldsTable">
                <thead>
                    <tr>
                        <th style="width:32px"><input type="checkbox" id="encCheckAll" onchange="encToggleAll(this.checked)"></th>
                        <th>Tabla</th>
                        <th>Campo</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Fase</th>
                        <th>Progreso</th>
                    </tr>
                </thead>
                <tbody id="encFieldsBody">
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--cd-text-muted)">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="sa-enc-actions" id="encActions" style="margin-top:20px">
        <button class="cd-btn-submit" id="btnEncPrepare" onclick="encPrepare()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Fase 1: Preparar columnas
        </button>
        <button class="cd-btn-submit" id="btnEncMigrate" onclick="encMigrate()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10"/><path d="M20.49 15a9 9 0 01-14.85 3.36L1 14"/></svg>
            Fase 2: Migrar datos
        </button>
        <button class="cd-btn-submit" id="btnEncActivate" onclick="encActivate()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><polyline points="20 6 9 17 4 12"/></svg>
            Fase 3: Activar lectura cifrada
        </button>
        <button class="cd-btn-submit sa-btn-warning" id="btnEncConsolidate" onclick="encConsolidate()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Fase 4: Consolidar (limpiar BD)
        </button>
        <button class="cd-btn-submit sa-btn-danger" id="btnEncRollback" onclick="encRollback()" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            Rollback
        </button>

        <label class="sa-enc-force-toggle" title="Permite ejecutar cualquier fase ignorando el estado guardado en encryption_state.json. Útil para reparar drift entre el JSON y la BD real (ej. fase 'consolidated' falsa con columnas _enc todavía presentes)." style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;padding:6px 10px;border:1px dashed var(--cd-warning, #f59e0b);border-radius:6px;font-size:12px;color:var(--cd-warning, #f59e0b);cursor:pointer">
            <input type="checkbox" id="encForceMode" onchange="_updateEncButtons()">
            <span>Modo forzar</span>
        </label>
    </div>

    <!-- Progress panel (hidden by default) -->
    <div class="sa-enc-progress" id="encProgress" style="display:none;margin-top:20px">
        <h3 style="margin:0 0 12px;font-size:15px" id="encProgressTitle">Progreso</h3>
        <div class="sa-enc-progress-bar-wrap">
            <div class="sa-enc-progress-bar" id="encProgressBar" style="width:0%"></div>
        </div>
        <div class="sa-enc-progress-text" id="encProgressText">0%</div>
        <div class="sa-enc-progress-log" id="encProgressLog"></div>
    </div>

    <!-- Confirmation modal overlay -->
    <div class="sa-enc-confirm-overlay" id="encConfirmOverlay" style="display:none">
        <div class="sa-enc-confirm-dialog">
            <h3 id="encConfirmTitle">Confirmar operación</h3>
            <div id="encConfirmBody"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                <button class="cd-btn-submit sa-btn-outline" onclick="encConfirmCancel()">Cancelar</button>
                <button class="cd-btn-submit" id="encConfirmOk" onclick="encConfirmOk()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Perfil
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelPerfil">
    <h2>Perfil de Superadmin</h2>
    <p class="cd-cfg-desc">Gestiona tus credenciales de acceso al portal de superadmin.</p>

    <div class="sa-profile-section">
        <div class="sa-profile-card">
            <div class="sa-profile-avatar">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="sa-profile-info">
                <div class="sa-profile-name" id="profileName">—</div>
                <div class="sa-profile-user" id="profileUser">—</div>
            </div>
        </div>

        <div class="cd-cfg-form" style="max-width:480px;margin-top:20px">
            <h3 style="margin:0 0 16px;font-size:15px">Cambiar credenciales</h3>
            <div class="cd-form-group">
                <label class="cd-form-label">Nombre para mostrar</label>
                <input type="text" class="cd-input" id="profName" placeholder="Nombre">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Usuario</label>
                <input type="text" class="cd-input" id="profUser" placeholder="Nombre de usuario" autocomplete="off">
            </div>
            <hr style="border:none;border-top:1px solid var(--cd-border);margin:16px 0">
            <h3 style="margin:0 0 16px;font-size:15px">Cambiar contraseña</h3>
            <div class="cd-form-group">
                <label class="cd-form-label">Contraseña actual</label>
                <input type="password" class="cd-input" id="profCurrentPass" placeholder="Contraseña actual" autocomplete="off">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Nueva contraseña</label>
                <input type="password" class="cd-input" id="profNewPass" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Confirmar nueva contraseña</label>
                <input type="password" class="cd-input" id="profConfirmPass" placeholder="Repetir contraseña" autocomplete="new-password">
            </div>
            <button class="cd-btn-submit" id="btnSaveProfile" style="margin-top:8px">Guardar cambios</button>
            <div id="profileMsg" style="margin-top:12px;font-size:13px;display:none"></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Onboarding 1-click (deploy de nueva institución)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelOnboarding">
    <div class="sa-onb-hero">
        <div class="sa-onb-hero-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
        </div>
        <div class="sa-onb-hero-text">
            <h2>Despliegue 1-click</h2>
            <p>Crea una nueva institución con configuración predeterminada, usuarios, invitaciones y base de datos tenant — todo en un solo flujo guiado.</p>
        </div>
    </div>

    <div class="sa-onb-stepper sa-onb-stepper-v2" id="onbStepper">
        <div class="sa-onb-progress-line"><div class="sa-onb-progress-fill" id="onbProgressFill" style="width:0%"></div></div>
        <div class="sa-onb-step active" data-step="1">
            <span class="sa-onb-bullet">1</span>
            <span class="sa-onb-step-text">Institución</span>
        </div>
        <div class="sa-onb-step" data-step="2">
            <span class="sa-onb-bullet">2</span>
            <span class="sa-onb-step-text">Invitaciones</span>
        </div>
        <div class="sa-onb-step" data-step="3">
            <span class="sa-onb-bullet">3</span>
            <span class="sa-onb-step-text">Confirmar</span>
        </div>
        <div class="sa-onb-step" data-step="4">
            <span class="sa-onb-bullet">4</span>
            <span class="sa-onb-step-text">Resultado</span>
        </div>
    </div>

    <!-- ── Paso 1: datos de la institución ───────────────────────────── -->
    <div class="sa-onb-pane" id="onbPane1">
        <div class="sa-onb-section">
            <div class="sa-onb-section-head">
                <span class="sa-onb-section-icon">🏥</span>
                <div><h4>Datos básicos</h4><p>Nombre y contacto principal de la institución.</p></div>
            </div>
            <div class="cd-cfg-form">
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label">Nombre de la institución <span class="sa-req">*</span></label>
                        <input class="cd-input" id="onbInstNombre" placeholder="Casa de reposo San José" required>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">Email administrador <span class="sa-req">*</span></label>
                        <input class="cd-input" id="onbInstEmail" type="email" placeholder="admin@institucion.com" required>
                    </div>
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label">Teléfono</label>
                        <input class="cd-input" id="onbInstTelefono" placeholder="+52...">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">Dirección</label>
                        <input class="cd-input" id="onbInstDireccion" placeholder="Calle, número, ciudad">
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-onb-section">
            <div class="sa-onb-section-head">
                <span class="sa-onb-section-icon">🌎</span>
                <div><h4>Localización</h4><p>Zona horaria y estado de la cuenta al activarse.</p></div>
            </div>
            <div class="cd-cfg-form">
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label">Zona horaria</label>
                        <select class="cd-input cd-select-native" id="onbInstTimezone">
                            <option value="America/Mexico_City" selected>America/Mexico_City</option>
                            <option value="America/Tijuana">America/Tijuana</option>
                            <option value="America/Cancun">America/Cancun</option>
                            <option value="America/Bogota">America/Bogota</option>
                            <option value="America/Lima">America/Lima</option>
                            <option value="America/Argentina/Buenos_Aires">America/Buenos_Aires</option>
                            <option value="Etc/GMT+5">Etc/GMT+5</option>
                            <option value="Etc/GMT+6">Etc/GMT+6</option>
                        </select>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">Estado inicial</label>
                        <select class="cd-input cd-select-native" id="onbInstEstado">
                            <option value="trial" selected>Trial</option>
                            <option value="activa">Activa</option>
                            <option value="suspendida">Suspendida</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-onb-section">
            <div class="sa-onb-section-head">
                <span class="sa-onb-section-icon">📦</span>
                <div><h4>Plan y límites</h4><p>Asigna un plan y define cuotas máximas (vacío = ilimitado).</p></div>
            </div>
            <div class="cd-cfg-form">
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label">Plan</label>
                        <select class="cd-input cd-select-native" id="onbInstPlan"><option value="">Sin plan</option></select>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">Máx. residentes</label>
                        <input class="cd-input" id="onbInstMaxRes" type="number" placeholder="Ilimitado">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label">Máx. usuarios</label>
                        <input class="cd-input" id="onbInstMaxUsr" type="number" placeholder="Ilimitado">
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-onb-section">
            <div class="sa-onb-section-head">
                <span class="sa-onb-section-icon">💾</span>
                <div><h4>Base de datos</h4><p>Cada institución puede tener su propia BD tenant aislada.</p></div>
            </div>
            <label class="sa-onb-toggle-card">
                <input type="checkbox" id="onbCreateDb">
                <span class="sa-onb-toggle-card-body">
                    <strong>Crear base de datos tenant dedicada</strong>
                    <small>Se creará <code>geriapp_i{ID}</code> automáticamente. Recomendado para aislamiento total de datos.</small>
                </span>
            </label>
        </div>

        <div class="cd-cfg-actions sa-onb-actions"><button class="cd-btn-submit" onclick="onbGoStep(2)">Continuar →</button></div>
    </div>

    <!-- ── Paso 2: invitaciones (opcional) ─────────────────────────── -->
    <div class="sa-onb-pane" id="onbPane2" style="display:none">
        <div class="sa-onb-info-banner">
            <span class="sa-onb-info-icon">✉️</span>
            <span><strong>Opcional.</strong> El email administrador del paso 1 recibirá automáticamente un correo (y WhatsApp si hay teléfono) con el link para ingresar o registrarse. Aquí puedes invitar personal adicional con sus propios links únicos. Puedes saltar este paso y agregar invitaciones después.</span>
        </div>
        <div class="sa-onb-invits" id="onbInvitsList"></div>
        <div class="sa-onb-add-row">
            <span class="sa-onb-add-row-label">Invitar a:</span>
            <button class="sa-onb-add-chip sa-role-chip-admin" onclick="onbAddInvit('admin')"><span>👤</span> Admin</button>
            <button class="sa-onb-add-chip sa-role-chip-medico" onclick="onbAddInvit('medico')"><span>🩺</span> Médico</button>
            <button class="sa-onb-add-chip sa-role-chip-cuidador" onclick="onbAddInvit('cuidador')"><span>💉</span> Cuidador</button>
            <button class="sa-onb-add-chip sa-role-chip-familiar" onclick="onbAddInvit('familiar')"><span>👨‍👩‍👧</span> Familiar</button>
        </div>
        <div class="cd-cfg-actions sa-onb-actions">
            <button class="cd-btn-submit cd-btn-secondary" onclick="onbGoStep(1)">← Atrás</button>
            <button class="cd-btn-submit" onclick="onbGoStep(3)">Continuar →</button>
        </div>
    </div>

    <!-- ── Paso 3: confirmar ─────────────────────────────────────────── -->
    <div class="sa-onb-pane" id="onbPane3" style="display:none">
        <div class="sa-onb-tiles" id="onbTiles"></div>
        <h3 class="sa-onb-sum-title">Resumen del despliegue</h3>
        <div id="onbSummary" class="sa-onb-summary"></div>
        <details style="margin-top:14px">
            <summary style="cursor:pointer;font-weight:600;font-size:13px;color:var(--cd-text-muted)">▸ Ver perfil de configuración por defecto que se aplicará</summary>
            <div id="onbDefaultsPreview" class="sa-onb-defaults"></div>
        </details>
        <div class="cd-cfg-actions sa-onb-actions" style="margin-top:18px">
            <button class="cd-btn-submit cd-btn-secondary" onclick="onbGoStep(2)">← Atrás</button>
            <button class="cd-btn-submit sa-btn-deploy" id="btnOnbExecute" onclick="onbExecute()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                Confirmar y desplegar
            </button>
        </div>
    </div>

    <!-- ── Paso 4: resultado ────────────────────────────────────────── -->
    <div class="sa-onb-pane" id="onbPane4" style="display:none">
        <div id="onbResult"></div>
        <div class="cd-cfg-actions sa-onb-actions" style="margin-top:16px">
            <button class="cd-btn-submit cd-btn-secondary" onclick="onbReset()">↺ Crear otra institución</button>
            <button class="cd-btn-submit" onclick="document.querySelector('[data-tab=owner]').click()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></svg>
                Ir al Panel Dueño
            </button>
        </div>
    </div>

    <!-- Progress overlay (mostrado durante onbExecute) -->
    <div class="sa-onb-progress-overlay" id="onbProgressOverlay" hidden>
        <div class="sa-onb-progress-card">
            <div class="sa-onb-progress-spinner">
                <svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
            </div>
            <h3 id="onbProgressTitle">Desplegando institución…</h3>
            <p class="sa-onb-progress-sub">Esto puede tardar unos segundos. No cierres esta ventana.</p>
            <ul class="sa-onb-progress-list" id="onbProgressList"></ul>
            <div class="sa-onb-progress-bar"><div class="sa-onb-progress-bar-fill" id="onbProgressBarFill"></div></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: Owner Dashboard (Panel del dueño de GeriApp)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="cd-cfg-panel" id="panelOwner">
    <div class="sa-section-header" style="margin-bottom:16px">
        <h2 style="margin:0">Panel Dueño · GeriApp</h2>
        <button class="cd-btn-submit cd-btn-secondary" onclick="loadOwnerDashboard()" style="padding:6px 12px;font-size:12px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Actualizar
        </button>
    </div>
    <p class="cd-cfg-desc">Vista panorámica del negocio: instituciones, planes, usuarios, invitaciones pendientes y altas recientes.</p>

    <!-- KPIs grid -->
    <div class="sa-stats sa-stats-wide" id="ownerKpis"></div>

    <!-- Distribución por plan -->
    <div class="sa-profiles-section" style="margin-top:20px">
        <div class="sa-section-header"><h3>Distribución por plan</h3></div>
        <div id="ownerPlanDist" class="sa-onb-bars"></div>
    </div>

    <!-- Timeline -->
    <div class="sa-profiles-section" style="margin-top:20px">
        <div class="sa-section-header"><h3>Nuevas instituciones (últimos 12 meses)</h3></div>
        <div id="ownerTimeline" class="sa-onb-bars"></div>
    </div>

    <!-- Tabla principal: instituciones -->
    <div class="sa-profiles-section" style="margin-top:20px">
        <div class="sa-section-header"><h3>Instituciones</h3></div>
        <div style="overflow-x:auto">
            <table class="sa-owner-table" id="ownerInstTable">
                <thead><tr>
                    <th>#</th><th>Nombre</th><th>Estado</th><th>Plan</th>
                    <th>Usuarios</th><th>Invits pend.</th><th>BD</th>
                    <th>Creado por</th><th>Creada</th><th>Última actividad</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Invitaciones pendientes -->
    <div class="sa-profiles-section" style="margin-top:20px">
        <div class="sa-section-header"><h3>Invitaciones pendientes (50 más recientes)</h3></div>
        <div style="overflow-x:auto">
            <table class="sa-owner-table" id="ownerInvitTable">
                <thead><tr>
                    <th>Email</th><th>Rol</th><th>Institución</th>
                    <th>Días restantes</th><th>Creada</th><th>Por</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Últimos usuarios -->
    <div class="sa-profiles-section" style="margin-top:20px">
        <div class="sa-section-header"><h3>Últimos usuarios registrados (30)</h3></div>
        <div style="overflow-x:auto">
            <table class="sa-owner-table" id="ownerUsersTable">
                <thead><tr>
                    <th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th>
                    <th>Institución</th><th>Registrado</th><th>Último acceso</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════ Panel: Demo Seeding ═══════════════ -->
<div class="cd-cfg-panel" id="panelSeeding">
    <h2 class="sa-panel-title">Demo Seeding</h2>
    <p style="color:var(--cd-muted);font-size:13px;margin-bottom:18px">
        Inyecta datos de demostración para presentar la app. Al desactivar, se eliminan todos los datos demo.
    </p>

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px">
        <label class="cd-switch" style="flex-shrink:0">
            <input type="checkbox" id="seedToggle" onchange="toggleSeedDemo(this.checked)">
            <span class="cd-switch-slider"></span>
        </label>
        <span id="seedStatusLabel" style="font-weight:600;font-size:15px">Cargando…</span>
    </div>

    <div id="seedCredentials" style="display:none;background:var(--cd-surface,#f8fafc);border:1px solid var(--cd-border,#e2e8f0);border-radius:10px;padding:18px;margin-bottom:18px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:10px;color:var(--cd-accent,#178391)">Credenciales Demo</h3>
        <table style="width:100%;font-size:13px;border-collapse:collapse">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--cd-border,#e2e8f0)">
                <th style="padding:4px 8px">Rol</th><th style="padding:4px 8px">Email</th><th style="padding:4px 8px">Contraseña</th>
            </tr></thead>
            <tbody>
                <tr><td style="padding:4px 8px">Admin</td><td style="padding:4px 8px"><code>demo_admin@geriapp.com</code></td><td style="padding:4px 8px"><code>Demo2026!</code></td></tr>
                <tr><td style="padding:4px 8px">Médico</td><td style="padding:4px 8px"><code>demo_medico@geriapp.com</code></td><td style="padding:4px 8px"><code>Demo2026!</code></td></tr>
                <tr><td style="padding:4px 8px">Cuidador</td><td style="padding:4px 8px"><code>demo_enfermero@geriapp.com</code></td><td style="padding:4px 8px"><code>Demo2026!</code></td></tr>
                <tr><td style="padding:4px 8px">Familiar</td><td style="padding:4px 8px"><code>demo_familiar@geriapp.com</code></td><td style="padding:4px 8px"><code>Demo2026!</code></td></tr>
            </tbody>
        </table>
        <p style="margin-top:10px;font-size:12px;color:var(--cd-muted)">Institución: <strong>[DEMO] Casa Geriátrica Bienestar</strong></p>
    </div>

    <div id="seedLog" style="display:none;background:var(--cd-bg,#fff);border:1px solid var(--cd-border,#e2e8f0);border-radius:8px;padding:14px;font-size:12px;font-family:monospace;max-height:260px;overflow-y:auto;white-space:pre-wrap"></div>
</div>

</div><!-- .sa-content -->
</div><!-- .sa-main -->
</div><!-- .sa-layout -->

<!-- ═══════════════════════════════════════════════════════════════════════
     Modal genérico
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="sa-modal-overlay" id="saModal">
    <div class="sa-modal">
        <div class="sa-modal-header">
            <h3 id="saModalTitle">Modal</h3>
            <button class="sa-icon-btn" onclick="closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sa-modal-body" id="saModalBody"></div>
    </div>
</div>

<!-- ── Right Drawer (Users) ─────────────────────────────────────────── -->
<div class="sa-drawer-overlay" id="saDrawerOverlay" onclick="closeDrawer()"></div>
<div class="sa-drawer" id="saDrawer">
    <div class="sa-drawer-header">
        <h3 id="saDrawerTitle">Usuario</h3>
        <button class="sa-icon-btn" onclick="closeDrawer()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="sa-drawer-body" id="saDrawerBody"></div>
</div>

<!-- ── Footer ─────────────────────────────────────────────────────────── -->
<footer class="sa-footer" id="saFooter">
    <div class="sa-footer-inner">
        <div class="sa-footer-item">
            <span class="sa-footer-label">Base de datos:</span>
            <span class="sa-footer-value" id="footerDb">—</span>
        </div>
        <div class="sa-footer-item">
            <span class="sa-footer-label">Host:</span>
            <span class="sa-footer-value" id="footerHost">—</span>
        </div>
        <div class="sa-footer-item">
            <span class="sa-footer-dot" id="footerDot"></span>
            <span class="sa-footer-value" id="footerStatus">Verificando…</span>
        </div>
        <div class="sa-footer-item">
            <span class="sa-footer-label">MySQL:</span>
            <span class="sa-footer-value" id="footerMysql">—</span>
        </div>
        <div class="sa-footer-item">
            <span class="sa-footer-label">PHP:</span>
            <span class="sa-footer-value" id="footerPhp">—</span>
        </div>
        <div class="sa-footer-item">
            <span class="sa-footer-label">Entorno:</span>
            <span class="sa-footer-value" id="footerEnv">—</span>
        </div>
    </div>
</footer>

</div><!-- .sa-app -->

<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
// ═══════════════════════════════════════════════════════════════════════════
// GeriApp Superadmin — Client-side logic
// ═══════════════════════════════════════════════════════════════════════════

const API = '<?= BASE_URL ?>/superadmin/api.php';
const BASE = '<?= BASE_URL ?>';
const SA_USER_TIMEZONE = (() => {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; }
    catch (e) { return 'UTC'; }
})();
const SA_ROLE_LABELS = { superadmin: 'Superadmin', admin: 'Admin', cuidador: 'Cuidador', enfermero: 'Cuidador', medico: 'Médico', familiar: 'Familiar' };
const SA_ROLE_ICONS = { admin: '👤', cuidador: '💉', enfermero: '💉', medico: '🩺', familiar: '👨‍👩‍👧', superadmin: '★' };
function saRoleStorage(role) { return role === 'cuidador' ? 'enfermero' : (role || ''); }
function saRolePublic(role) { return role === 'enfermero' ? 'cuidador' : (role || ''); }
function saRoleLabel(role) { return SA_ROLE_LABELS[role] || SA_ROLE_LABELS[saRolePublic(role)] || String(role || 'Usuario'); }
function saRoleIcon(role) { return SA_ROLE_ICONS[role] || SA_ROLE_ICONS[saRolePublic(role)] || ''; }
function saRoleOptions(roles, selectedRole) {
    const selected = saRolePublic(selectedRole);
    return roles.map(r => `<option value="${r}" ${selected === r ? 'selected' : ''}>${saRoleLabel(r)}</option>`).join('');
}
function saApplyVisitorTimezoneDefault() {
    const select = document.getElementById('onbInstTimezone');
    if (!select || !SA_USER_TIMEZONE) return;
    if (![...select.options].some(opt => opt.value === SA_USER_TIMEZONE)) {
        select.insertAdjacentHTML('afterbegin', `<option value="${esc(SA_USER_TIMEZONE)}">${esc(SA_USER_TIMEZONE)}</option>`);
    }
    select.value = SA_USER_TIMEZONE;
}

// ── State ────────────────────────────────────────────────────────────────
let _instituciones = [];
let _planes = [];
let _usuarios = [];
let _logs = [];
let _logTotal = 0;
let _logOffset = 0;
let _profileLoaded = false;
let _deployLoaded = false;
let _checkdbLoaded = false;
let _checkdbResults = {};
let _pushLoaded = false;
let _pushTokens = [];
let _pushStats = {};
let _encLoaded = false;
let _encFields = {};
let _encConfirmCallback = null;
let _seedLoaded = false;
let _onbInited = false;
let _onbDefaults = null;
let _onbUsers = [];
let _onbInvits = [];
let _onbStep = 1;
let _ownerLoaded = false;
let _eventQrLoaded = false;
let _eventQrPlanes = [];
let _eventQrRows = [];
let _integracionesLoaded = false;
let _resourcesTimer = null;
let _maintenanceInvalidInvites = null;
let _maintenanceLegacyFamilyContacts = null;

// ── Init ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initTheme();
    initSync();
    saApplyVisitorTimezoneDefault();
    // Close drawer on Escape
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
    loadStats();
    loadInstituciones();
    loadServerInfo();
    document.getElementById('instFilterEstado')?.addEventListener('change', () => loadInstituciones());
    document.getElementById('instSearch')?.addEventListener('input', () => renderInstituciones());
    document.getElementById('btnRefreshResources')?.addEventListener('click', () => loadResources());
    document.getElementById('maintenanceProfile')?.addEventListener('change', () => { loadMaintenance(); loadLegacyFamilyContacts(true); });
    document.getElementById('btnScanInvalidInvites')?.addEventListener('click', () => loadMaintenance());
    document.getElementById('btnCleanupInvalidInvites')?.addEventListener('click', () => cleanupInvalidInvitations());
    document.getElementById('btnScanLegacyFamilyContacts')?.addEventListener('click', () => loadLegacyFamilyContacts());
    document.getElementById('btnConvertLegacyFamilyContacts')?.addEventListener('click', () => convertLegacyFamilyContacts());
});

// ── Sidenav ──────────────────────────────────────────────────────────────
function initTabs() {
    const sidenav = document.getElementById('saSidenav');
    const overlay = document.getElementById('saSidenavOverlay');
    const hamburger = document.getElementById('saHamburger');

    // Hamburger toggle (mobile)
    hamburger?.addEventListener('click', () => {
        sidenav.classList.toggle('open');
        overlay.classList.toggle('open');
    });
    overlay?.addEventListener('click', () => {
        sidenav.classList.remove('open');
        overlay.classList.remove('open');
    });

    document.querySelectorAll('.sa-sidenav-item').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sa-sidenav-item').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.cd-cfg-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const panel = document.getElementById('panel' + capitalize(btn.dataset.tab));
            if (panel) panel.classList.add('active');
            document.querySelector('.sa-content')?.classList.toggle('sa-content-wide', btn.dataset.tab === 'recursos');

            // Close mobile nav
            sidenav.classList.remove('open');
            overlay.classList.remove('open');

            // Lazy-load tab data
            const tab = btn.dataset.tab;
            if (tab === 'recursos') { loadResources(); startResourcesAutoRefresh(); }
            else stopResourcesAutoRefresh();
            if (tab === 'planes' && !_planes.length) loadPlanes();
            if (tab === 'usuarios' && !_usuarios.length) loadUsuarios();
            if (tab === 'logs' && !_logs.length) loadLogs();
            if (tab === 'migraciones' && !_migrationsLoaded) loadMigrations();
            if (tab === 'perfil' && !_profileLoaded) loadProfile();
            if (tab === 'despliegue' && !_deployLoaded) loadPendingDeploy();
            if (tab === 'checkdb' && !_checkdbLoaded) runCheckDb();
            if (tab === 'mantenimiento' && !_maintenanceInvalidInvites) loadMaintenance();
            if (tab === 'push' && !_pushLoaded) loadPushData();
            if (tab === 'cifrado' && !_encLoaded) loadEncryptionStatus();
            if (tab === 'seeding' && !_seedLoaded) loadSeedStatus();
            if (tab === 'onboarding' && !_onbInited) onbInit();
            if (tab === 'owner' && !_ownerLoaded) loadOwnerDashboard();
            if (tab === 'eventqr' && !_eventQrLoaded) loadEventQr();
            if (tab === 'integraciones' && !_integracionesLoaded) loadIntegraciones();
        });
    });
}

function capitalize(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// ── Theme ────────────────────────────────────────────────────────────────
function initTheme() {
    document.getElementById('btnThemeToggle').addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.removeItem('geriappTheme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('geriappTheme', 'dark');
        }
    });
}

// ── Stats ────────────────────────────────────────────────────────────────
async function loadStats() {
    document.querySelectorAll('#saStats .sa-stat-value').forEach(el => el.innerHTML = '<span class="sa-ghost sa-ghost-text" style="width:40px;height:24px;display:inline-block"></span>');
    try {
        const r = await api('stats');
        document.getElementById('statInst').textContent   = r.instituciones;
        document.getElementById('statUsers').textContent   = r.usuarios;
        document.getElementById('statActive').textContent  = r.activas;
        document.getElementById('statPlans').textContent   = r.planes;
    } catch (e) { console.error('Stats error:', e); }
}

// ── Recursos ─────────────────────────────────────────────────────────────
function startResourcesAutoRefresh() {
    stopResourcesAutoRefresh();
    _resourcesTimer = setInterval(() => {
        if (document.getElementById('panelRecursos')?.classList.contains('active')) loadResources(true);
    }, 15000);
}

function stopResourcesAutoRefresh() {
    if (_resourcesTimer) clearInterval(_resourcesTimer);
    _resourcesTimer = null;
}

async function loadResources(silent = false) {
    const kpis = document.getElementById('resourceKpis');
    const limit = document.getElementById('resourceDbLimit');
    const storage = document.getElementById('resourceStorage');
    if (!silent) {
        kpis.innerHTML = ghostResourceCards(4);
        limit.innerHTML = '<div class="sa-loading">Calculando recursos…</div>';
        storage.innerHTML = '<div class="sa-loading">Calculando almacenamiento…</div>';
        document.getElementById('resourceHistoryChart').innerHTML = '<div class="sa-loading">Cargando histórico…</div>';
        document.getElementById('resourcePathBreakdown').innerHTML = ghostRows(3);
        document.getElementById('resourceDbBreakdown').innerHTML = ghostRows(3);
    }
    try {
        const r = await api('resources');
        renderResources(r);
    } catch (e) {
        kpis.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderResources(r) {
    const dbLimit = r.db?.limit || {};
    const storage = r.storage || {};
    const dbSizes = r.db?.sizes || {};
    const uploads = storage.paths?.uploads || {bytes:0, files:0, exists:false};
    const app = storage.paths?.geriapp_v9 || {bytes:0, files:0, exists:false};
    const diskUsed = storage.disk_total && storage.disk_free ? storage.disk_total - storage.disk_free : null;
    const diskPct = storage.disk_total && diskUsed !== null ? (diskUsed / storage.disk_total) * 100 : null;
    const connPct = dbLimit.percent ?? 0;

    document.getElementById('resourceUpdatedAt').textContent = `Actualizado ${fmtDt(r.generated_at)} · refresco automático cada 15 s`;
    document.getElementById('resourceKpis').innerHTML = [
        resourceKpi(`${dbLimit.used ?? 0}/${dbLimit.display_limit ?? 500}`, 'Conexiones MySQL/h', connPct >= 80 ? 'warn' : 'ok'),
        resourceKpi(fmtBytes(uploads.bytes || 0), 'Uploads', uploads.exists ? 'info' : 'warn'),
        resourceKpi(fmtBytes(dbSizes.total_bytes || 0), 'Datos BD', dbSizes.error ? 'warn' : 'info'),
        resourceKpi(storage.disk_free ? fmtBytes(storage.disk_free) : '—', 'Disco libre', diskPct !== null && diskPct > 85 ? 'warn' : 'ok'),
    ].join('');

    renderResourceDbLimit(r);
    renderResourceStorage(r);
    renderResourcePaths(storage, app);
    renderResourceDbs(dbSizes);
    renderHostingerNote(r.hostinger_note, dbLimit, r.db?.host || '—');
    renderResourceHistory(r.history || [], dbLimit);
}

function resourceKpi(value, label, type) {
    return `<div class="sa-stat-card sa-kpi-${type || 'info'}">
        <div class="sa-stat-value">${esc(value)}</div>
        <div class="sa-stat-label">${esc(label)}</div>
    </div>`;
}

function renderResourceDbLimit(r) {
    const limit = r.db?.limit || {};
    const processes = r.db?.processes || {};
    const byUser = r.db?.connections?.by_user || {};
    const pct = limit.percent ?? 0;
    const status = pct >= 80 ? 'warn' : 'ok';
    document.getElementById('resourceDbLimit').innerHTML = `
        <h3>Conexiones MySQL Hostinger</h3>
        <div class="sa-resource-bigline">
            <strong>${limit.used ?? 0}</strong>
            <span>de ${limit.display_limit ?? 500} conexiones observadas esta hora</span>
        </div>
        <div class="sa-resource-meter"><div class="sa-resource-meter-fill ${status}" style="width:${Math.min(100, pct)}%"></div></div>
        <div class="sa-resource-facts">
            <span>Disponible: ${limit.remaining ?? '—'}</span>
            <span>Uso: ${pct}%</span>
            <span>Ping BD: ${r.db?.ping_ms ?? '—'} ms</span>
            <span>Procesos visibles: ${processes.current_user_visible ?? processes.total_visible ?? '—'}</span>
        </div>
        <p class="sa-resource-note">Fuente del límite: ${limit.source === 'mysql_grants' ? 'SHOW GRANTS del usuario MySQL' : 'valor Hostinger/fallback 500'}.</p>
        <details class="sa-resource-details">
            <summary>Desglose por usuario MySQL</summary>
            ${resourceTable(Object.entries(byUser).map(([user, count]) => ({user, count})), ['Usuario MySQL', 'Conexiones/h'], row => [
                esc(row.user),
                Number(row.count || 0).toLocaleString()
            ], 'Aún no hay conexiones registradas por usuario.')}
        </details>
    `;
}

let _resourceHistoryChartReady = null;
let _resourceHistoryResizeBound = false;
let _lastResourceHistory = { rows: [], limit: {}, bytesMax: 1 };

function parseResourceHistoryDate(hour, idx, total) {
    const raw = String(hour || '').trim();
    const compact = raw.match(/^(\d{4})(\d{2})(\d{2})(\d{2})$/);
    const candidate = compact
        ? `${compact[1]}-${compact[2]}-${compact[3]}T${compact[4]}:00:00Z`
        : (raw.replace(' ', 'T').replace(/^(\d{4}-\d{2}-\d{2}T\d{2})$/, '$1:00:00') + (/[zZ]|[+-]\d{2}:?\d{2}$/.test(raw) ? '' : 'Z'));
    const parsed = new Date(candidate);
    if (!Number.isNaN(parsed.getTime())) return parsed;
    return new Date(Date.now() - Math.max(0, total - idx - 1) * 3600000);
}

function ensureResourceHistoryChartReady() {
    if (!window.google || !google.charts) return Promise.reject(new Error('Google Charts no disponible'));
    if (!_resourceHistoryChartReady) {
        _resourceHistoryChartReady = new Promise(resolve => {
            google.charts.load('current', { packages: ['corechart'] });
            google.charts.setOnLoadCallback(resolve);
        });
    }
    return _resourceHistoryChartReady;
}

function resourceHistoryColors() {
    const root = getComputedStyle(document.documentElement);
    return {
        text: root.getPropertyValue('--cd-text').trim() || '#1f2937',
        muted: root.getPropertyValue('--cd-text-muted').trim() || '#6b7280',
        border: root.getPropertyValue('--cd-border').trim() || '#e5e7eb',
        accent: root.getPropertyValue('--cd-accent').trim() || '#178391',
        success: root.getPropertyValue('--cd-success').trim() || '#16a34a',
        warning: root.getPropertyValue('--cd-warning').trim() || '#f59e0b',
        surface: root.getPropertyValue('--cd-surface').trim() || '#ffffff',
    };
}

function resourceHistoryTooltip(row) {
    return `<div style="padding:8px 10px;min-width:170px">
        <strong>${esc(formatResourceHour(row.hour))}</strong><br>
        Conexiones: ${Number(row.connections || 0).toLocaleString()}<br>
        BD: ${fmtBytes(row.db_bytes || 0)}<br>
        Uploads: ${fmtBytes(row.uploads_bytes || 0)}
    </div>`;
}

async function drawResourceHistoryGoogle(rows, limit, bytesMax) {
    await ensureResourceHistoryChartReady();
    const chartEl = document.getElementById('resourceHistoryGoogleChart');
    if (!chartEl || !window.google?.visualization) return;
    const colors = resourceHistoryColors();
    const data = new google.visualization.DataTable();
    data.addColumn('datetime', 'Hora');
    data.addColumn('number', 'Conexiones/h');
    data.addColumn({ type: 'string', role: 'tooltip', p: { html: true } });
    data.addColumn('number', 'Tamaño BD');
    data.addColumn({ type: 'string', role: 'tooltip', p: { html: true } });
    data.addColumn('number', 'Uploads');
    data.addColumn({ type: 'string', role: 'tooltip', p: { html: true } });
    data.addRows(rows.map((row, idx) => {
        const tip = resourceHistoryTooltip(row);
        return [
            parseResourceHistoryDate(row.hour, idx, rows.length),
            Number(row.connections || 0), tip,
            Number(row.db_bytes || 0), tip,
            Number(row.uploads_bytes || 0), tip,
        ];
    }));
    const chart = new google.visualization.ComboChart(chartEl);
    chart.draw(data, {
        backgroundColor: 'transparent',
        chartArea: { left: 54, top: 24, right: 74, bottom: 46, width: '82%', height: '72%' },
        colors: [colors.accent, colors.success, colors.warning],
        fontName: 'Open Sans, system-ui, sans-serif',
        legend: { position: 'top', alignment: 'end', textStyle: { color: colors.muted, fontSize: 12 } },
        tooltip: { isHtml: true },
        seriesType: 'bars',
        series: {
            0: { type: 'bars', targetAxisIndex: 0 },
            1: { type: 'line', targetAxisIndex: 1, lineWidth: 3, pointSize: 4 },
            2: { type: 'line', targetAxisIndex: 1, lineWidth: 3, pointSize: 4 },
        },
        hAxis: { textStyle: { color: colors.muted, fontSize: 11 }, gridlines: { color: 'transparent' } },
        vAxes: {
            0: { title: 'Conexiones', minValue: 0, textStyle: { color: colors.muted }, titleTextStyle: { color: colors.muted }, gridlines: { color: colors.border } },
            1: { title: 'Bytes', minValue: 0, viewWindow: { min: 0, max: bytesMax }, textStyle: { color: colors.muted }, titleTextStyle: { color: colors.muted }, gridlines: { color: 'transparent' }, format: 'short' },
        },
        bar: { groupWidth: '55%' },
    });
    if (!_resourceHistoryResizeBound) {
        _resourceHistoryResizeBound = true;
        window.addEventListener('resize', () => {
            if (_lastResourceHistory.rows.length) {
                drawResourceHistoryGoogle(_lastResourceHistory.rows, _lastResourceHistory.limit, _lastResourceHistory.bytesMax).catch(() => {});
            }
        });
    }
}

function renderResourceHistorySvg(rows, limit, bytesMax) {
    const el = document.getElementById('resourceHistoryChart');
    const connMax = Math.max(limit.display_limit || 500, ...rows.map(r => Number(r.connections || 0)), 1);
    const w = 720, h = 240, pad = 34;
    const plotW = w - (pad * 2), plotH = h - (pad * 2);
    const x = idx => pad + (rows.length === 1 ? plotW / 2 : (idx / (rows.length - 1)) * plotW);
    const yConn = val => pad + plotH - (Number(val || 0) / connMax) * plotH;
    const yBytes = val => pad + plotH - (Number(val || 0) / bytesMax) * plotH;
    const path = (getter, yFn) => rows.map((row, idx) => `${idx === 0 ? 'M' : 'L'} ${x(idx).toFixed(1)} ${yFn(getter(row)).toFixed(1)}`).join(' ');
    const labels = rows.filter((_, idx) => idx === 0 || idx === rows.length - 1 || idx % 6 === 0);
    el.innerHTML = `
        <div class="sa-resource-chart-wrap">
            <svg class="sa-resource-chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Histórico de conexiones, base de datos y uploads">
                <line x1="${pad}" y1="${pad}" x2="${pad}" y2="${h - pad}" class="sa-chart-axis"/>
                <line x1="${pad}" y1="${h - pad}" x2="${w - pad}" y2="${h - pad}" class="sa-chart-axis"/>
                <line x1="${pad}" y1="${yConn(limit.display_limit || 500).toFixed(1)}" x2="${w - pad}" y2="${yConn(limit.display_limit || 500).toFixed(1)}" class="sa-chart-limit"/>
                ${rows.map((row, idx) => {
                    const barW = Math.max(4, plotW / rows.length * .55);
                    const barH = (Number(row.connections || 0) / connMax) * plotH;
                    return `<rect class="sa-chart-bar" x="${(x(idx) - barW / 2).toFixed(1)}" y="${(h - pad - barH).toFixed(1)}" width="${barW.toFixed(1)}" height="${barH.toFixed(1)}"><title>${formatResourceHour(row.hour)} · ${row.connections || 0} conexiones</title></rect>`;
                }).join('')}
                <path class="sa-chart-line sa-chart-db" d="${path(row => row.db_bytes, yBytes)}"/>
                <path class="sa-chart-line sa-chart-uploads" d="${path(row => row.uploads_bytes, yBytes)}"/>
                ${labels.map((row, idx) => `<text class="sa-chart-label" x="${x(rows.indexOf(row)).toFixed(1)}" y="${h - 8}" text-anchor="${idx === 0 ? 'start' : 'middle'}">${formatResourceHour(row.hour)}</text>`).join('')}
            </svg>
        </div>
        <div class="sa-resource-legend">
            <span><i class="conn"></i> Conexiones/h</span>
            <span><i class="db"></i> Tamaño BD</span>
            <span><i class="uploads"></i> Uploads</span>
            <span class="sa-resource-legend-note">Últimas ${rows.length} horas · BD máx ${fmtBytes(bytesMax)}</span>
        </div>
    `;
}

function renderResourceHistory(history, limit) {
    const el = document.getElementById('resourceHistoryChart');
    const rows = (history || []).filter(row => row && row.hour);
    if (!rows.length) {
        el.innerHTML = '<p class="sa-empty">Sin datos históricos todavía.</p>';
        return;
    }
    const bytesMax = Math.max(...rows.map(r => Number(r.db_bytes || 0)), ...rows.map(r => Number(r.uploads_bytes || 0)), 1);
    _lastResourceHistory = { rows, limit, bytesMax };
    el.innerHTML = `
        <div class="sa-resource-chart-wrap">
            <div id="resourceHistoryGoogleChart" class="sa-resource-google-chart"></div>
        </div>
        <div class="sa-resource-legend">
            <span><i class="conn"></i> Conexiones/h</span>
            <span><i class="db"></i> Tamaño BD</span>
            <span><i class="uploads"></i> Uploads</span>
            <span class="sa-resource-legend-note">Últimas ${rows.length} horas · BD máx ${fmtBytes(bytesMax)}</span>
        </div>
    `;
    drawResourceHistoryGoogle(rows, limit, bytesMax).catch(() => renderResourceHistorySvg(rows, limit, bytesMax));
}

function renderResourceStorage(r) {
    const storage = r.storage || {};
    const uploads = storage.paths?.uploads || {bytes:0, files:0, dirs:0, exists:false};
    const app = storage.paths?.geriapp_v9 || {bytes:0, files:0, dirs:0, exists:false};
    const diskUsed = storage.disk_total && storage.disk_free ? storage.disk_total - storage.disk_free : null;
    const diskPct = storage.disk_total && diskUsed !== null ? Math.round((diskUsed / storage.disk_total) * 1000) / 10 : null;
    document.getElementById('resourceStorage').innerHTML = `
        <h3>Almacenamiento</h3>
        <div class="sa-resource-bigline"><strong>${fmtBytes(app.bytes || 0)}</strong><span>carpeta v9 sin node_modules</span></div>
        <div class="sa-resource-meter"><div class="sa-resource-meter-fill ${diskPct > 85 ? 'warn' : 'ok'}" style="width:${diskPct ?? 0}%"></div></div>
        <div class="sa-resource-facts">
            <span>Uploads: ${uploads.exists ? fmtBytes(uploads.bytes || 0) : 'no existe aún'}</span>
            <span>Archivos uploads: ${(uploads.files || 0).toLocaleString()}</span>
            <span>Disco usado: ${diskPct ?? '—'}%</span>
            <span>Disco total: ${storage.disk_total ? fmtBytes(storage.disk_total) : '—'}</span>
        </div>
    `;
}

function renderResourcePaths(storage, app) {
    const paths = storage.paths || {};
    const rows = Object.entries(paths).map(([key, stat]) => ({key, ...stat}));
    document.getElementById('resourcePathBreakdown').innerHTML = `
        ${resourceTable(rows, ['Ruta', 'Tamaño', 'Archivos'], row => [
            esc(row.key),
            fmtBytes(row.bytes || 0),
            `${(row.files || 0).toLocaleString()} ${row.exists ? '' : '(no existe)'}`
        ])}
        <h4 class="sa-resource-subtitle">Uploads por carpeta</h4>
        ${resourceTable(storage.uploads_top || [], ['Carpeta', 'Tamaño', 'Archivos'], row => [
            esc(row.name),
            fmtBytes(row.bytes || 0),
            (row.files || 0).toLocaleString()
        ], 'Sin archivos en uploads todavía.')}
    `;
}

function renderResourceDbs(dbSizes) {
    document.getElementById('resourceDbBreakdown').innerHTML = `
        ${dbSizes.error ? `<div class="sa-conn-err">${esc(dbSizes.error)}</div>` : ''}
        ${resourceTable(dbSizes.databases || [], ['BD', 'Tamaño', 'Tablas'], row => [
            esc(row.db_name),
            fmtBytes(row.bytes || 0),
            `${(row.tables_count || 0).toLocaleString()} · ${(row.approx_rows || 0).toLocaleString()} filas aprox.`
        ], 'No se pudo leer information_schema.')}
        <h4 class="sa-resource-subtitle">Tablas más pesadas</h4>
        ${resourceTable(dbSizes.top_tables || [], ['Tabla', 'Tamaño', 'Filas'], row => [
            `${esc(row.db_name)}.${esc(row.table_name)}`,
            fmtBytes(row.bytes || 0),
            (row.approx_rows || 0).toLocaleString()
        ], 'Sin datos de tablas.')}
    `;
}

function renderHostingerNote(note, limit, dbHost) {
    document.getElementById('resourceHostingerNote').innerHTML = `
        <p class="sa-resource-note"><strong>${esc(note?.title || 'Límite MySQL')}</strong></p>
        <p class="sa-resource-note">${esc(note?.body || '')}</p>
        <div class="sa-resource-facts">
            <span>Conexiones/h: ${limit.max_connections_per_hour ?? 'no leído en grants'}</span>
            <span>Consultas/h: ${limit.max_queries_per_hour ?? 'no leído en grants'}</span>
            <span>DB_HOST: ${esc(dbHost)}</span>
        </div>
    `;
}

function resourceTable(rows, headers, mapper, emptyText) {
    if (!rows.length) return `<p class="sa-empty">${esc(emptyText || 'Sin datos.')}</p>`;
    return `<table class="sa-results-table sa-resource-table"><thead><tr>${headers.map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>
        ${rows.map(row => `<tr>${mapper(row).map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}
    </tbody></table>`;
}

function ghostResourceCards(n) {
    return Array.from({length:n}).map(() => `<div class="sa-stat-card"><div class="sa-ghost sa-ghost-text" style="width:60px;height:28px;margin:0 auto 8px"></div><div class="sa-ghost sa-ghost-text" style="width:90px;margin:0 auto"></div></div>`).join('');
}

// ═══════════════════════════════════════════════════════════════════════════
// SYNC
// ═══════════════════════════════════════════════════════════════════════════
let _profiles = {};

async function initSync() {
    await loadProfiles();

    // Test buttons
    document.getElementById('btnTestSource').addEventListener('click', () => testConn('source'));
    document.getElementById('btnTestTarget').addEventListener('click', () => testConn('target'));
    document.getElementById('btnListDbs').addEventListener('click', listDbs);
    document.getElementById('btnSync').addEventListener('click', runSync);
    document.getElementById('btnSelectAll').addEventListener('click', () => toggleAllDbs(true));
    document.getElementById('btnSelectNone').addEventListener('click', () => toggleAllDbs(false));
    document.getElementById('btnNewProfile').addEventListener('click', () => editProfile(null));
    document.getElementById('syncSource').addEventListener('change', updateSyncState);
    document.getElementById('syncTarget').addEventListener('change', updateSyncState);
}

async function loadProfiles() {
    const listEl = document.getElementById('profileList');
    listEl.innerHTML = ghostRows(2);
    try {
        const r = await api('profiles', null, 'GET');
        _profiles = r.profiles;
        renderProfiles();
        populateProfileSelects();
    } catch (e) {
        listEl.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderProfiles() {
    const el = document.getElementById('profileList');
    const keys = Object.keys(_profiles);
    if (!keys.length) { el.innerHTML = '<p class="sa-empty">No hay perfiles.</p>'; return; }
    el.innerHTML = keys.map(key => {
        const p = _profiles[key];
        return `<div class="cd-cfg-user-card" onclick="editProfile('${esc(key)}')">
            <div class="cd-cfg-user-info">
                <strong>${esc(p.label)}</strong>
                <span>${esc(key)} · ${esc(p.host)}:${p.port} · BD: ${esc(p.db)}</span>
            </div>
            <span class="cd-cfg-user-role">${esc(p.user)}</span>
            <button class="sa-house-btn" onclick="event.stopPropagation();testProfileInline('${esc(key)}')" title="Probar conexión">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </button>
        </div>`;
    }).join('');
}

function populateProfileSelects() {
    const srcSel = document.getElementById('syncSource');
    const tgtSel = document.getElementById('syncTarget');
    const prevSrc = srcSel.value;
    const prevTgt = tgtSel.value;

    srcSel.innerHTML = '<option value="">— Seleccionar —</option>';
    tgtSel.innerHTML = '<option value="">— Seleccionar —</option>';

    for (const [key, p] of Object.entries(_profiles)) {
        srcSel.add(new Option(`${p.label} (${p.host})`, key));
        tgtSel.add(new Option(`${p.label} (${p.host})`, key));
    }

    srcSel.value = prevSrc || 'produccion';
    tgtSel.value = prevTgt || 'local';
    updateSyncState();

    // Also populate checkdb profile selector
    const chkSel = document.getElementById('checkdbProfile');
    if (chkSel) {
        const prevChk = chkSel.value;
        chkSel.innerHTML = '<option value="">Local (por defecto)</option>';
        for (const [key, p] of Object.entries(_profiles)) {
            chkSel.add(new Option(`${p.label} (${p.host}:${p.port} → ${p.db})`, key));
        }
        if (prevChk) chkSel.value = prevChk;
    }

    const maintSel = document.getElementById('maintenanceProfile');
    if (maintSel) {
        const prevMaint = maintSel.value;
        maintSel.innerHTML = '<option value="">Local (por defecto)</option>';
        for (const [key, p] of Object.entries(_profiles)) {
            maintSel.add(new Option(`${p.label} (${p.host}:${p.port} → ${p.db})`, key));
        }
        if (prevMaint) maintSel.value = prevMaint;
    }
}

function editProfile(key) {
    const p = key ? _profiles[key] : {label:'', host:'', port:3306, user:'root', pass:'', db:'', prefix:'geriapp_i', charset:'utf8mb4'};
    const isNew = !key;

    openModal(isNew ? 'Nuevo Perfil' : 'Editar Perfil', `
        <div class="cd-cfg-form">
            <div class="cd-form-group">
                <label class="cd-form-label">Key (identificador único)</label>
                <input class="cd-input" id="mProfKey" value="${esc(key || '')}" ${isNew ? '' : 'readonly style="opacity:.6"'} placeholder="mi_servidor">
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label">Nombre / etiqueta</label>
                <input class="cd-input" id="mProfLabel" value="${esc(p.label)}" placeholder="Mi Servidor">
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group">
                    <label class="cd-form-label">Host</label>
                    <input class="cd-input" id="mProfHost" value="${esc(p.host)}" placeholder="127.0.0.1">
                </div>
                <div class="cd-form-group" style="max-width:100px">
                    <label class="cd-form-label">Puerto</label>
                    <input class="cd-input" id="mProfPort" type="number" value="${p.port || 3306}">
                </div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group">
                    <label class="cd-form-label">Usuario</label>
                    <input class="cd-input" id="mProfUser" value="${esc(p.user)}" placeholder="root">
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label">Contraseña</label>
                    <input class="cd-input" id="mProfPass" type="password" value="${esc(p.pass)}">
                </div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group">
                    <label class="cd-form-label">Base de datos principal</label>
                    <input class="cd-input" id="mProfDb" value="${esc(p.db)}" placeholder="geriapp">
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label">Prefijo tenants</label>
                    <input class="cd-input" id="mProfPrefix" value="${esc(p.prefix || 'geriapp_i')}" placeholder="geriapp_i">
                </div>
            </div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" onclick="saveProfile()">Guardar</button>
                <button class="cd-btn-submit cd-btn-secondary" id="mProfTestBtn" onclick="testProfileModal()">Probar</button>
                ${!isNew ? '<button class="cd-btn-submit" style="background:var(--cd-danger);margin-left:auto" onclick="deleteProfile(\''+esc(key)+'\')">Eliminar</button>' : ''}
                <button class="cd-btn-submit cd-btn-secondary" onclick="closeModal()">Cancelar</button>
            </div>
            <div class="sa-conn-result" id="mProfTestResult"></div>
        </div>
    `);
}

async function saveProfile() {
    const key = document.getElementById('mProfKey').value.trim();
    if (!key) return saAlert('Key es requerida', 'error');
    try {
        await api('profiles', {
            sub: 'save',
            key,
            label:  document.getElementById('mProfLabel').value,
            host:   document.getElementById('mProfHost').value,
            port:   document.getElementById('mProfPort').value,
            user:   document.getElementById('mProfUser').value,
            pass:   document.getElementById('mProfPass').value,
            db:     document.getElementById('mProfDb').value,
            prefix: document.getElementById('mProfPrefix').value,
        });
        closeModal();
        await loadProfiles();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function deleteProfile(key) {
    if (!confirm(`¿Eliminar perfil "${key}"?`)) return;
    try {
        await api('profiles', {sub: 'delete', key});
        closeModal();
        await loadProfiles();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function testProfileModal() {
    const el = document.getElementById('mProfTestResult');
    el.innerHTML = '<span class="sa-conn-testing">Conectando…</span>';
    try {
        const r = await api('sync_test', {
            adhoc: {
                host:   document.getElementById('mProfHost').value,
                port:   document.getElementById('mProfPort').value,
                user:   document.getElementById('mProfUser').value,
                pass:   document.getElementById('mProfPass').value,
                db:     document.getElementById('mProfDb').value,
            }
        });
        el.innerHTML = `<span class="sa-conn-ok">✓ Conectado — MySQL ${esc(r.version)} (${r.ms}ms)</span>`;
    } catch (e) {
        el.innerHTML = `<span class="sa-conn-err">✗ Error: ${esc(e.message)}</span>`;
    }
}

async function testProfileInline(key) {
    try {
        const r = await api('sync_test', {profile: key});
        saAlert(`✓ ${_profiles[key]?.label}: MySQL ${r.version} (${r.ms}ms)`, 'success');
    } catch (e) {
        saAlert(`✗ ${e.message}`, 'error');
    }
}

function updateSyncState() {
    const src = document.getElementById('syncSource').value;
    const tgt = document.getElementById('syncTarget').value;
    document.getElementById('btnTestSource').disabled = !src;
    document.getElementById('btnTestTarget').disabled = !tgt;
    document.getElementById('btnListDbs').disabled = !src;
    document.getElementById('btnSync').disabled = !src || !tgt || src === tgt;
    // Clear old results
    document.getElementById('resultSource').innerHTML = '';
    document.getElementById('resultTarget').innerHTML = '';
    document.getElementById('dbListWrap').style.display = 'none';
    document.getElementById('syncResults').style.display = 'none';
}

async function testConn(side) {
    const selId = side === 'source' ? 'syncSource' : 'syncTarget';
    const resId = side === 'source' ? 'resultSource' : 'resultTarget';
    const profile = document.getElementById(selId).value;
    const el = document.getElementById(resId);
    el.innerHTML = '<span class="sa-conn-testing">Conectando…</span>';

    try {
        const r = await api('sync_test', {profile});
        el.innerHTML = `<span class="sa-conn-ok">✓ Conectado — MySQL ${esc(r.version)} (${r.ms}ms)</span>`;
    } catch (e) {
        el.innerHTML = `<span class="sa-conn-err">✗ Error: ${esc(e.message)}</span>`;
    }
}

async function listDbs() {
    const profile = document.getElementById('syncSource').value;
    const wrap = document.getElementById('dbListWrap');
    const list = document.getElementById('dbList');
    list.innerHTML = '<div class="sa-loading">Consultando bases de datos…</div>';
    wrap.style.display = 'block';

    try {
        const r = await api('sync_list_dbs', {profile});
        list.innerHTML = r.databases.map(db => `
            <label class="sa-db-item">
                <input type="checkbox" value="${esc(db.name)}" checked>
                <span class="sa-db-name">${esc(db.name)}</span>
                <span class="sa-db-badge sa-db-badge-${db.type}">${db.type}</span>
                <span class="sa-db-tables">${db.tables} tablas</span>
            </label>
        `).join('');
    } catch (e) {
        list.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function toggleAllDbs(state) {
    document.querySelectorAll('#dbList input[type="checkbox"]').forEach(cb => cb.checked = state);
}

async function runSync() {
    const source = document.getElementById('syncSource').value;
    const target = document.getElementById('syncTarget').value;
    const checks = document.querySelectorAll('#dbList input[type="checkbox"]:checked');
    const databases = Array.from(checks).map(cb => cb.value);

    if (!databases.length) return saAlert('Selecciona al menos una base de datos', 'error');
    const structureOnly = document.getElementById('syncStructureOnly').checked;
    const confirmMsg = structureOnly
        ? `¿Sincronizar ESTRUCTURA de ${databases.length} BD(s) de ${source} → ${target}?\n\nSolo se copiarán tablas vacías (sin datos).`
        : `¿Sincronizar ${databases.length} BD(s) de ${source} → ${target}?\n\nEsto REEMPLAZARÁ los datos en el destino.`;
    if (!confirm(confirmMsg)) return;

    const btn = document.getElementById('btnSync');
    const progress = document.getElementById('syncProgress');
    const results = document.getElementById('syncResults');
    btn.disabled = true;
    btn.textContent = 'Sincronizando…';
    progress.style.display = 'block';
    results.style.display = 'none';

    // Scroll down to show progress area
    progress.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const log = document.getElementById('syncLog');
    const fill = document.getElementById('syncProgressFill');
    log.innerHTML = '';
    fill.style.width = '0%';

    const createIfMissing = document.getElementById('syncCreateDb').checked;
    const targetDbName = document.getElementById('syncTargetDbName').value.trim();
    appendLog('info', `Iniciando sincronización: ${source} → ${target}`);
    appendLog('info', `Bases de datos: ${databases.join(', ')}`);
    if (createIfMissing) appendLog('info', 'Crear BD si no existe: Sí');
    if (structureOnly) appendLog('info', 'Modo: Solo estructura (sin datos)');
    if (targetDbName) appendLog('info', `Nombre BD destino: ${targetDbName}`);

    try {
        const r = await api('sync_run', {source, target, databases, create_if_missing: createIfMissing, structure_only: structureOnly, target_db_name: targetDbName || null});
        fill.style.width = '100%';

        let html = '<div class="sa-sync-results"><h3>Resultados</h3><table class="sa-results-table"><thead><tr><th>Base de datos</th><th>Estado</th><th>Tablas</th><th>Filas</th></tr></thead><tbody>';
        for (const res of r.results) {
            if (res.ok) {
                html += `<tr><td>${esc(res.db)}</td><td class="sa-conn-ok">✓ OK</td><td>${res.tables}</td><td>${res.rows.toLocaleString()}</td></tr>`;
                appendLog('ok', `${res.db}: ${res.tables} tablas, ${res.rows.toLocaleString()} filas`);
            } else {
                html += `<tr><td>${esc(res.db)}</td><td class="sa-conn-err">✗ Error</td><td colspan="2">${esc(res.error)}</td></tr>`;
                appendLog('error', `${res.db}: ${res.error}`);
            }
        }
        html += '</tbody></table></div>';
        results.innerHTML = html;
        results.style.display = 'block';
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        appendLog('info', 'Sincronización completada.');
    } catch (e) {
        appendLog('error', `Error fatal: ${e.message}`);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg> Ejecutar sincronización';
    }
}

function appendLog(type, msg) {
    const log = document.getElementById('syncLog');
    const ts = fmtTime();
    log.innerHTML += `<div class="sa-log-line sa-log-${type}">[${ts}] ${esc(msg)}</div>`;
    log.scrollTop = log.scrollHeight;
}

// ═══════════════════════════════════════════════════════════════════════════
// MIGRACIONES
// ═══════════════════════════════════════════════════════════════════════════
let _migrationsLoaded = false;

async function loadMigrations() {
    const el = document.getElementById('migrationList');
    el.innerHTML = ghostRows(3);
    try {
        const r = await api('migrations', null, 'GET');
        _migrationsLoaded = true;
        renderMigrations(r.files);
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderMigrations(files) {
    const el = document.getElementById('migrationList');
    if (!files.length) { el.innerHTML = '<p class="sa-empty">No hay archivos de migración.</p>'; return; }
    el.innerHTML = files.map(f => `
        <div class="cd-cfg-user-card">
            <div class="cd-cfg-user-info">
                <strong>${esc(f.file)}</strong>
                <span>${(f.size / 1024).toFixed(1)} KB · ${fmtDate(f.modified)}</span>
            </div>
            <button class="cd-btn-submit cd-btn-secondary" style="padding:6px 12px;font-size:12px" onclick="runMigrationFile('${esc(f.file)}')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Ejecutar
            </button>
        </div>
    `).join('');
}

async function runMigrationFile(file) {
    if (!confirm(`¿Ejecutar migración "${file}"?\n\nEsto puede modificar la estructura de la base de datos.`)) return;
    const el = document.getElementById('migrationList');
    const prev = el.innerHTML;
    el.insertAdjacentHTML('afterbegin', `<div class="sa-migration-running" id="migRunning"><span class="sa-conn-testing">Ejecutando ${esc(file)}…</span></div>`);
    try {
        const r = await api('run_migration', {file});
        const runEl = document.getElementById('migRunning');
        if (runEl) runEl.innerHTML = `
            <div class="sa-conn-ok" style="margin-bottom:8px">✓ Migración completada</div>
            <pre class="sa-migration-output">${esc(r.output || '(sin salida)')}</pre>
        `;
    } catch (e) {
        const runEl = document.getElementById('migRunning');
        if (runEl) runEl.innerHTML = `<div class="sa-conn-err">✗ Error: ${esc(e.message)}</div>`;
    }
}

async function runFullMigration() {
    if (!confirm('¿Ejecutar TODAS las migraciones (master + tenant)?\n\nEsto es idempotente y seguro de ejecutar múltiples veces.')) return;
    const el = document.getElementById('fullMigrationOutput');
    el.style.display = 'block';
    el.innerHTML = '<span class="sa-conn-testing">Ejecutando migración completa…</span>';
    try {
        const r = await api('run_full_migration', {});
        el.innerHTML = `
            <div class="sa-conn-ok" style="margin-bottom:8px">✓ Migración completa ejecutada</div>
            <pre class="sa-migration-output" style="max-height:400px;overflow:auto;font-size:12px;padding:12px;background:var(--cd-bg);border-radius:var(--cd-radius);border:1px solid var(--cd-border)">${esc(r.output || '(sin salida)')}</pre>
        `;
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">✗ Error: ${esc(e.message)}</div>`;
    }
}

// CIE-10 Import Tools — removido: migrado a MediApp

// ═══════════════════════════════════════════════════════════════════════════
// INSTITUCIONES
// ═══════════════════════════════════════════════════════════════════════════
async function loadInstituciones() {
    const el = document.getElementById('instList');
    el.innerHTML = ghostRows(4);
    try {
        const estado = document.getElementById('instFilterEstado')?.value || '';
        const qs = estado ? 'instituciones&estado=' + encodeURIComponent(estado) : 'instituciones';
        const r = await api(qs, null, 'GET');
        _instituciones = r.instituciones;
        renderInstituciones();
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderInstituciones() {
    const el = document.getElementById('instList');
    if (!_instituciones.length) { el.innerHTML = '<p class="sa-empty">No hay instituciones.</p>'; return; }
    const term = (document.getElementById('instSearch')?.value || '').trim().toLowerCase();
    const instituciones = term ? _instituciones.filter(i => [
        i.id,
        i.nombre,
        i.db_name,
        i.estado,
        i.plan_nombre,
        i.admin_nombre,
        i.admin_email,
        i.admin_telefono,
        i.email_admin,
        i.telefono,
    ].some(value => String(value || '').toLowerCase().includes(term))) : _instituciones;
    if (!instituciones.length) { el.innerHTML = '<p class="sa-empty">No se encontraron instituciones con ese criterio.</p>'; return; }
    const groups = new Map();
    instituciones.forEach(i => {
        const email = (i.admin_email || i.email_admin || '').trim();
        const name = (i.admin_nombre || '').trim();
        const key = email || name || '__sin_admin__';
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                nombre: name || (email ? email : 'Sin administrador asignado'),
                email,
                telefono: i.admin_telefono || '',
                instituciones: [],
            });
        }
        groups.get(key).instituciones.push(i);
    });

    el.innerHTML = [...groups.values()].map(group => `
        <section class="sa-inst-admin-group">
            <div class="sa-inst-admin-head">
                <div>
                    <strong>${esc(group.nombre)}</strong>
                    <span>${group.email ? esc(group.email) : 'Instituciones sin usuario administrador vinculado'}</span>
                </div>
                <em>${group.instituciones.length} ${group.instituciones.length === 1 ? 'institución' : 'instituciones'}</em>
            </div>
            <div class="sa-inst-admin-list">
                ${group.instituciones.map(i => {
                    const isArchived = i.estado === 'archivada';
                    return `
                    <div class="sa-inst-wrap" id="instWrap_${i.id}">
                        <div class="cd-cfg-user-card sa-inst-card" onclick="openInstitutionDrawer(${i.id})" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openInstitutionDrawer(${i.id})}">
                            <button class="sa-house-btn" onclick="event.stopPropagation();enterInstitucion(${i.id})" title="Entrar a cuidados">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </button>
                            <div class="cd-cfg-user-info">
                                <strong>${esc(i.nombre)}</strong>
                                <span>ID: ${i.id} · BD: ${esc(i.db_name || 'master')} · Usuarios: ${i.num_usuarios || 0} · Admins: ${i.num_admins || 0}</span>
                            </div>
                            <span class="cd-cfg-user-role">${esc(i.plan_nombre || 'Sin plan')}</span>
                            <span class="cd-cfg-user-status ${i.estado === 'activa' ? 'active' : ''} ${isArchived ? 'archived' : ''}">${esc(i.estado)}</span>
                            <button class="sa-house-btn" onclick="event.stopPropagation();openInstitutionDrawer(${i.id}, true)" title="Ver usuarios">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                            </button>
                            <button class="sa-house-btn" onclick="event.stopPropagation();toggleArchivarInstitucion(${i.id},'${isArchived ? 'activa' : 'archivada'}')" title="${isArchived ? 'Desarchivar' : 'Archivar'}">
                                ${isArchived
                                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1014.85 3.36L1 10"/></svg>'
                                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>'}
                            </button>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </section>
    `).join('');
}

// ── Institution users expand/collapse ────────────────────────────────────
const _instUsersCache = {};

async function toggleInstUsers(instId) {
    const panel = document.getElementById('instUsersPanel_' + instId);
    if (!panel) return;
    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        return;
    }
    panel.innerHTML = '<div class="sa-loading" style="padding:12px">Cargando usuarios…</div>';
    panel.classList.add('open');
    try {
        const r = await api('usuarios', { sub: 'inst_users', institucion_id: instId });
        _instUsersCache[instId] = r.usuarios;
        renderInstUsersPanel(instId, r.usuarios);
    } catch (e) {
        panel.innerHTML = `<div class="sa-conn-err" style="padding:12px">Error: ${esc(e.message)}</div>`;
    }
}

function renderInstUsersPanel(instId, usuarios, searchTerm, roleFilter) {
    const panel = document.getElementById('instUsersPanel_' + instId);
    if (!panel) return;
    const term = (searchTerm ?? '').toLowerCase();
    const rolF = saRoleStorage(roleFilter ?? '');
    let list = usuarios;
    if (term) list = list.filter(u => (u.nombre||'').toLowerCase().includes(term) || (u.email||'').toLowerCase().includes(term));
    if (rolF) list = list.filter(u => (u.inst_rol || u.global_rol) === rolF);

    // Build toolbar (search + role filter) only once, then update results container
    let toolbar = panel.querySelector('.sa-inst-toolbar');
    let results = panel.querySelector('.sa-inst-results');
    if (!toolbar) {
        const rolOpts = saRoleOptions(['admin','cuidador','medico','familiar'], rolF);
        panel.innerHTML = `<div class="sa-inst-toolbar" style="display:flex;gap:6px;margin-bottom:8px">
            <input class="cd-input sa-inst-users-search" placeholder="Buscar usuario…" value="${esc(searchTerm||'')}" style="flex:1">
            <select class="cd-input cd-select-native sa-inst-role-filter" style="width:auto;min-width:100px">
                <option value="">Todos los roles</option>${rolOpts}
            </select>
        </div><div class="sa-inst-results"></div>`;
        toolbar = panel.querySelector('.sa-inst-toolbar');
        const searchInput = toolbar.querySelector('.sa-inst-users-search');
        const roleSelect = toolbar.querySelector('.sa-inst-role-filter');
        searchInput.addEventListener('input', () => renderInstUsersPanel(instId, _instUsersCache[instId], searchInput.value, roleSelect.value));
        roleSelect.addEventListener('change', () => renderInstUsersPanel(instId, _instUsersCache[instId], searchInput.value, roleSelect.value));
        results = panel.querySelector('.sa-inst-results');
    }

    if (!list.length) {
        results.innerHTML = '<p class="sa-empty" style="padding:8px 0">No se encontraron usuarios.</p>';
        return;
    }
    results.innerHTML = list.map(u => `
        <div class="sa-inst-user-row" onclick="openInstUserDrawer(${u.id}, ${instId})">
            <div class="sa-inst-user-info">
                <strong>${esc(u.nombre)}</strong>
                <span>${esc(u.email)}</span>
            </div>
            <span class="cd-cfg-user-role">${esc(saRoleLabel(u.inst_rol || u.global_rol))}</span>
            <span class="cd-cfg-user-status ${u.estado === 'activo' ? 'active' : ''}">${esc(u.estado)}</span>
        </div>
    `).join('');
}

async function openInstUserDrawer(userId, instId) {
    // Find user from cache or fetch
    let u = (_instUsersCache[instId] || []).find(x => x.id === userId);
    if (!u) { const r = await api('usuarios', null, 'GET'); u = r.usuarios.find(x => x.id === userId); }
    if (!u) return;

    const inst = _instituciones.find(x => x.id === instId);
    const instName = inst ? inst.nombre : 'Institución #' + instId;
    const instRol = u.inst_rol || u.global_rol || 'enfermero';
    const rolOpts = saRoleOptions(['admin','cuidador','medico','familiar'], instRol);

    openDrawer(u.nombre, `
        <div class="sa-drawer-section">
            <h4>Datos del usuario</h4>
            <div class="sa-user-detail">
                <div class="sa-user-detail-row"><span class="sa-label">Email</span><span class="sa-value">${esc(u.email)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Estado</span><span class="sa-value">${esc(u.estado)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Rol global</span><span class="sa-value">${esc(saRoleLabel(u.global_rol || u.rol))}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Registrado</span><span class="sa-value">${u.creado_at ? fmtDt(u.creado_at) : '—'}</span></div>
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Rol en ${esc(instName)}</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group">
                    <label class="cd-form-label">Rol</label>
                    <select class="cd-input cd-select-native" id="dwInstUserRol">${rolOpts}</select>
                </div>
                <div class="cd-cfg-actions">
                    <button class="cd-btn-submit" onclick="saveInstUserRole(${userId}, ${instId})">Guardar rol</button>
                    <button class="cd-btn-submit sa-btn-danger" onclick="deleteUsuario(${userId}, ${instId})">Eliminar usuario</button>
                </div>
            </div>
        </div>
    `);
}

async function saveInstUserRole(userId, instId) {
    const rol = saRoleStorage(document.getElementById('dwInstUserRol').value);
    try {
        await api('usuarios', { sub: 'update_inst_role', user_id: userId, institucion_id: instId, rol });
        closeDrawer();
        saAlert('Rol actualizado', 'success');
        // Refresh expanded panel
        const r = await api('usuarios', { sub: 'inst_users', institucion_id: instId });
        _instUsersCache[instId] = r.usuarios;
        renderInstUsersPanel(instId, r.usuarios);
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

function enterInstitucion(id) {
    window.open(BASE + '/superadmin/impersonate.php?inst_id=' + id, '_blank');
}

function editInstitucion(id) { openInstitutionDrawer(id); }

function openInstitutionDrawer(id, loadUsers = false) {
    const inst = _instituciones.find(i => i.id === id);
    if (!inst) return;
    const isArchived = inst.estado === 'archivada';
    const planOpts = '<option value=""' + (!inst.plan_id ? ' selected' : '') + '>N/A</option>' +
        (_planes.length
        ? _planes.map(p => `<option value="${p.id}" ${p.id == inst.plan_id ? 'selected' : ''}>${esc(p.nombre)}</option>`).join('')
        : '');

    openDrawer(inst.nombre, `
        <div class="sa-drawer-section sa-inst-detail-hero">
            <div class="sa-inst-detail-title">
                <strong>${esc(inst.nombre)}</strong>
                <span class="cd-cfg-user-status ${inst.estado === 'activa' ? 'active' : ''} ${isArchived ? 'archived' : ''}">${esc(inst.estado)}</span>
            </div>
            <div class="sa-user-detail">
                <div class="sa-user-detail-row"><span class="sa-label">ID</span><span class="sa-value">${inst.id}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Administrador</span><span class="sa-value">${esc(inst.admin_nombre || inst.admin_email || inst.email_admin || 'Sin administrador vinculado')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Correo admin</span><span class="sa-value">${esc(inst.admin_email || inst.email_admin || '—')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Teléfono admin</span><span class="sa-value">${esc(inst.admin_telefono || inst.telefono || '—')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Plan</span><span class="sa-value">${esc(inst.plan_nombre || 'Sin plan')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Base de datos</span><span class="sa-value">${esc(inst.db_name || 'master')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Usuarios</span><span class="sa-value">${inst.num_usuarios || 0} usuarios · ${inst.num_admins || 0} admins</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Creada</span><span class="sa-value">${inst.creado_at ? fmtDt(inst.creado_at) : '—'}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Fin trial</span><span class="sa-value">${inst.trial_ends_at ? fmtDate(inst.trial_ends_at) : '—'}</span></div>
            </div>
            <div class="sa-drawer-actions sa-inst-detail-actions">
                <button class="cd-btn-submit" onclick="enterInstitucion(${id})">Entrar</button>
                <button class="cd-btn-submit cd-btn-secondary" onclick="loadDrawerInstUsers(${id})">Ver usuarios</button>
                ${isArchived
                    ? '<button class="cd-btn-submit cd-btn-secondary" onclick="toggleArchivarInstitucion('+id+',\'activa\');closeDrawer()">Desarchivar</button>'
                    : '<button class="cd-btn-submit sa-btn-warn" onclick="toggleArchivarInstitucion('+id+',\'archivada\');closeDrawer()">Archivar</button>'}
                <button class="cd-btn-submit sa-btn-danger" onclick="deleteInstitucion(${id})">Eliminar</button>
            </div>
        </div>
        <div class="sa-drawer-section" id="dwInstUsersSection" style="display:none">
            <h4>Usuarios vinculados</h4>
            <div id="dwInstUsersPanel" class="sa-inst-drawer-users"></div>
        </div>
        <div class="sa-drawer-section">
            <h4>Editar institución</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="mInstNombre" value="${esc(inst.nombre)}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Estado</label>
                    <select class="cd-input cd-select-native" id="mInstEstado">
                        <option value="activa" ${inst.estado === 'activa' ? 'selected' : ''}>Activa</option>
                        <option value="trial" ${inst.estado === 'trial' ? 'selected' : ''}>Trial</option>
                        <option value="suspendida" ${inst.estado === 'suspendida' ? 'selected' : ''}>Suspendida</option>
                        <option value="archivada" ${inst.estado === 'archivada' ? 'selected' : ''}>Archivada</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Plan y límites</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Plan</label>
                    <select class="cd-input cd-select-native" id="mInstPlan">${planOpts}</select>
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group"><label class="cd-form-label">Máx. Residentes</label><input class="cd-input" id="mInstMaxRes" type="number" value="${inst.max_residentes ?? ''}"></div>
                    <div class="cd-form-group"><label class="cd-form-label">Máx. Usuarios</label><input class="cd-input" id="mInstMaxUsr" type="number" value="${inst.max_usuarios ?? ''}"></div>
                </div>
            </div>
        </div>
        <div class="sa-drawer-actions">
            <button class="cd-btn-submit" onclick="saveInstitucion(${id})">Guardar</button>
            <button class="cd-btn-submit sa-btn-cancel" onclick="closeDrawer()">Cancelar</button>
        </div>
    `);
    if (loadUsers) loadDrawerInstUsers(id);
}

async function loadDrawerInstUsers(instId) {
    const section = document.getElementById('dwInstUsersSection');
    const panel = document.getElementById('dwInstUsersPanel');
    if (!section || !panel) return;
    section.style.display = '';
    panel.innerHTML = '<div class="sa-loading" style="padding:10px 0">Cargando usuarios…</div>';
    try {
        const r = await api('usuarios', { sub: 'inst_users', institucion_id: instId });
        _instUsersCache[instId] = r.usuarios;
        if (!r.usuarios.length) {
            panel.innerHTML = '<p class="sa-empty">No hay usuarios vinculados.</p>';
            return;
        }
        panel.innerHTML = r.usuarios.map(u => `
            <div class="sa-inst-user-row" onclick="openInstUserDrawer(${u.id}, ${instId})">
                <div class="sa-inst-user-info">
                    <strong>${esc(u.nombre)}</strong>
                    <span>${esc(u.email)}</span>
                </div>
                <span class="cd-cfg-user-role">${esc(u.inst_rol || u.global_rol)}</span>
                <span class="cd-cfg-user-status ${u.estado === 'activo' ? 'active' : ''}">${esc(u.estado)}</span>
            </div>
        `).join('');
    } catch (e) {
        panel.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

async function saveInstitucion(id) {
    try {
        await api('instituciones', {
            sub: 'update', id,
            nombre: document.getElementById('mInstNombre').value,
            estado: document.getElementById('mInstEstado').value,
            plan_id: document.getElementById('mInstPlan').value || null,
            max_residentes: document.getElementById('mInstMaxRes').value || null,
            max_usuarios: document.getElementById('mInstMaxUsr').value || null
        });
        closeDrawer();
        loadInstituciones();
        loadStats();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function toggleArchivarInstitucion(id, nuevoEstado) {
    const label = nuevoEstado === 'archivada' ? 'archivar' : 'desarchivar';
    if (!confirm(`¿Seguro que deseas ${label} esta institución?`)) return;
    try {
        await api('instituciones', { sub: 'update', id, estado: nuevoEstado });
        loadInstituciones();
        loadStats();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function deleteInstitucion(id) {
    if (!confirm('¿Eliminar esta institución PERMANENTEMENTE?\n\nEsta acción no se puede deshacer.')) return;
    if (!confirm('⚠️ CONFIRMAR: Se eliminarán todos los datos asociados. ¿Continuar?')) return;
    try {
        await api('instituciones', { sub: 'delete', id });
        closeDrawer();
        loadInstituciones();
        loadStats();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

function createInstitucion() {
    const planOpts = _planes.length
        ? _planes.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('')
        : '';
    openDrawer('Nueva Institución', `
        <div class="sa-drawer-section">
            <h4>Datos generales</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="mNewInstNombre" placeholder="Nombre de la institución"></div>
                <div class="cd-form-group"><label class="cd-form-label">Estado</label>
                    <select class="cd-input cd-select-native" id="mNewInstEstado">
                        <option value="activa">Activa</option>
                        <option value="trial" selected>Trial</option>
                        <option value="suspendida">Suspendida</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Plan y límites</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Plan</label>
                    <select class="cd-input cd-select-native" id="mNewInstPlan"><option value="">Sin plan</option>${planOpts}</select>
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group"><label class="cd-form-label">Máx. Residentes</label><input class="cd-input" id="mNewInstMaxRes" type="number" placeholder="Ilimitado"></div>
                    <div class="cd-form-group"><label class="cd-form-label">Máx. Usuarios</label><input class="cd-input" id="mNewInstMaxUsr" type="number" placeholder="Ilimitado"></div>
                </div>
            </div>
        </div>
        <div class="sa-drawer-actions">
            <button class="cd-btn-submit" onclick="saveNewInstitucion()">Crear</button>
            <button class="cd-btn-submit sa-btn-cancel" onclick="closeDrawer()">Cancelar</button>
        </div>
    `);
}

async function saveNewInstitucion() {
    try {
        await api('instituciones', {
            sub: 'create',
            nombre: document.getElementById('mNewInstNombre').value,
            estado: document.getElementById('mNewInstEstado').value,
            plan_id: document.getElementById('mNewInstPlan').value || null,
            max_residentes: document.getElementById('mNewInstMaxRes').value || null,
            max_usuarios: document.getElementById('mNewInstMaxUsr').value || null
        });
        closeDrawer();
        loadInstituciones();
        loadStats();
        saAlert('Institución creada correctamente', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════
// QR EVENTOS GERIAPP
// ═══════════════════════════════════════════════════════════════════════════

async function loadEventQr() {
    const list = document.getElementById('eventQrList');
    if (list) list.innerHTML = ghostRows(3);
    try {
        const r = await api('event_qr', null, 'GET');
        _eventQrPlanes = r.planes || [];
        _eventQrRows = r.invitaciones || [];
        _eventQrLoaded = true;
        renderEventQrForm();
        renderEventQrRows();
    } catch (e) {
        if (list) list.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderEventQrForm() {
    const sel = document.getElementById('eventQrPlan');
    if (!sel) return;
    sel.innerHTML = _eventQrPlanes.map(p => {
        const trial = parseInt(p.trial_dias || 0, 10);
        const cardReq = parseInt(p.solicita_tarjeta_registro || 0, 10) === 1;
        const label = `${p.nombre} · ${trial > 0 ? trial + ' días trial' : 'sin trial'}${cardReq ? ' · pide tarjeta' : ''}`;
        return `<option value="${p.id}" data-trial="${trial}" data-card="${cardReq ? 1 : 0}">${esc(label)}</option>`;
    }).join('') || '<option value="">Sin paquetes activos</option>';
    sel.onchange = updateEventQrHint;
    document.getElementById('eventQrDays')?.addEventListener('input', updateEventQrHint, {once:false});
    updateEventQrHint();
}

function updateEventQrHint() {
    const sel = document.getElementById('eventQrPlan');
    const opt = sel?.selectedOptions?.[0];
    const trial = parseInt(opt?.dataset.trial || '0', 10);
    const cardReq = parseInt(opt?.dataset.card || '0', 10) === 1;
    const dias = parseInt(document.getElementById('eventQrDays')?.value || '30', 10) || 30;
    const hint = document.getElementById('eventQrHint');
    if (!hint) return;
    hint.textContent = `El QR estará activo ${dias} día(s). El cliente creará su institución desde cero y el trial de ${trial} día(s) iniciará al completar el registro.${cardReq ? ' Este paquete solicitará tarjeta antes de entrar a la app.' : ''}`;
}

async function createEventQr() {
    const planId = parseInt(document.getElementById('eventQrPlan')?.value || '0', 10);
    const dias = parseInt(document.getElementById('eventQrDays')?.value || '30', 10) || 30;
    const nombre = document.getElementById('eventQrName')?.value.trim() || '';
    if (!planId) return saAlert('Selecciona un paquete', 'error');
    try {
        const r = await api('event_qr', {sub:'create', plan_id:planId, dias, nombre_evento:nombre});
        renderEventQrResult(r.url);
        document.getElementById('eventQrName').value = '';
        await loadEventQr();
        saAlert('QR generado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

function renderEventQrResult(url) {
    const el = document.getElementById('eventQrResult');
    if (!el) return;
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url);
    el.innerHTML = `
        <div style="width:100%;display:flex;flex-direction:column;align-items:center;gap:10px">
            <img src="${qrUrl}" alt="QR GeriApp" style="width:220px;height:220px;border-radius:8px;border:1px solid var(--cd-border);background:#fff;padding:8px">
            <input class="cd-input" value="${esc(url)}" readonly style="font-size:12px;text-align:center">
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
                <button class="cd-btn-submit cd-btn-secondary" onclick="copyEventQrLink('${esc(url)}')">Copiar link</button>
                <a class="cd-btn-submit" href="${esc(url)}" target="_blank" rel="noopener" style="text-decoration:none">Abrir</a>
            </div>
        </div>`;
}

async function copyEventQrLink(url) {
    try {
        await navigator.clipboard.writeText(url);
        saAlert('Link copiado', 'success');
    } catch (e) { saAlert('No se pudo copiar el link', 'error'); }
}

function eventQrNum(value) {
    const n = parseInt(value || 0, 10);
    return Number.isFinite(n) ? n : 0;
}

function eventQrRegistrationCount(row) {
    return Math.max(eventQrNum(row.registro_count), eventQrNum(row.usos_count));
}

function eventQrConversionLabel(row) {
    const scans = eventQrNum(row.scan_count);
    const regs = eventQrRegistrationCount(row);
    if (!scans) return '—';
    return `${Math.round((regs / scans) * 1000) / 10}%`;
}

function renderEventQrMetricGrid(row) {
    const scans = eventQrNum(row.scan_count);
    const regs = eventQrRegistrationCount(row);
    const cards = [
        ['Escaneos', scans, row.last_scan_at ? `Último: ${fmtDt(row.last_scan_at)}` : 'Sin escaneos registrados'],
        ['Registros', regs, row.last_registered_at ? `Último: ${fmtDt(row.last_registered_at)}` : 'Sin registros completados'],
        ['Conversión', eventQrConversionLabel(row), scans ? `${regs} de ${scans} escaneo(s)` : 'Pendiente de escaneos'],
    ];
    return `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin:0 0 12px">
        ${cards.map(([label, value, hint]) => `<div style="border:1px solid var(--cd-border);border-radius:8px;padding:10px;background:var(--cd-surface)">
            <div style="font-size:11px;color:var(--cd-text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.3px">${esc(label)}</div>
            <div style="font-size:22px;font-weight:800;color:var(--cd-text-primary);line-height:1.2;margin-top:4px">${esc(value)}</div>
            <div style="font-size:11px;color:var(--cd-text-muted);line-height:1.35;margin-top:4px">${esc(hint)}</div>
        </div>`).join('')}
    </div>`;
}

function renderEventQrTimeline(events) {
    if (!events) {
        return '<div class="sa-empty-state">Cargando actividad del QR...</div>';
    }
    if (!events.length) {
        return '<div class="sa-empty-state">Aún no hay escaneos ni registros auditados para este QR.</div>';
    }
    return `<div style="display:flex;flex-direction:column;gap:8px">
        ${events.map(ev => {
            const isReg = ev.tipo === 'registro';
            const label = isReg ? 'Registro completado' : 'Escaneo / apertura';
            const user = ev.usuario_nombre || ev.usuario_email
                ? `${ev.usuario_nombre || 'Usuario'}${ev.usuario_email ? ' · ' + ev.usuario_email : ''}`
                : '';
            const inst = ev.institucion_nombre ? `Institución: ${ev.institucion_nombre}` : '';
            const tech = [ev.ip ? `IP ${ev.ip}` : '', ev.user_agent || ''].filter(Boolean).join(' · ');
            return `<div style="border:1px solid var(--cd-border);border-radius:8px;padding:10px;background:var(--cd-bg-card)">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <div style="font-weight:800;color:${isReg ? 'var(--cd-success,#16a34a)' : 'var(--cd-primary,#178391)'};font-size:13px">${label}</div>
                    <div style="font-size:12px;color:var(--cd-text-muted);font-weight:700">${fmtDt(ev.creado_at)}</div>
                </div>
                ${user ? `<div style="font-size:12px;color:var(--cd-text-primary);margin-top:5px">${esc(user)}</div>` : ''}
                ${inst ? `<div style="font-size:12px;color:var(--cd-text-secondary);margin-top:3px">${esc(inst)}</div>` : ''}
                ${tech ? `<div style="font-size:11px;color:var(--cd-text-muted);line-height:1.35;margin-top:5px;word-break:break-word">${esc(tech)}</div>` : ''}
            </div>`;
        }).join('')}
    </div>`;
}

function renderEventQrRows() {
    const el = document.getElementById('eventQrList');
    if (!el) return;
    if (!_eventQrRows.length) {
        el.innerHTML = '<div class="sa-empty-state">Aún no hay QR generados.</div>';
        return;
    }
    el.innerHTML = _eventQrRows.map(row => {
        const active = row.estado === 'activa';
        const title = row.nombre_evento || 'QR GeriApp';
        const trial = parseInt(row.trial_dias || 0, 10);
        const cardReq = parseInt(row.solicita_tarjeta_registro || 0, 10) === 1;
        const scans = eventQrNum(row.scan_count);
        const regs = eventQrRegistrationCount(row);
        const lastActivity = row.last_event_at || row.last_registered_at || row.last_scan_at || row.last_used_at;
        return `<div class="cd-cfg-user-card" style="align-items:flex-start;cursor:pointer" onclick="openEventQrDrawer(${row.id})" title="Ver QR">
            <div class="cd-cfg-user-info" style="min-width:0;flex:1">
                <div class="cd-cfg-user-name">${esc(title)}</div>
            <div class="cd-cfg-user-email">${esc(row.plan_nombre)} · ${trial > 0 ? trial + ' días trial' : 'sin trial'}${cardReq ? ' · pide tarjeta' : ''} · expira ${fmtDt(row.expires_at)}</div>
                <div class="cd-cfg-user-email" style="font-size:11px">${scans} escaneo(s) · ${regs} registro(s) · conversión ${eventQrConversionLabel(row)}${lastActivity ? ' · última actividad ' + fmtDt(lastActivity) : ''}</div>
            </div>
            <span class="cd-cfg-user-status ${active ? 'active' : ''}" style="font-size:11px">${esc(row.estado)} · ${regs} registro(s)</span>
            ${active ? `<button class="cd-btn-submit sa-btn-danger" style="padding:6px 10px;font-size:12px" onclick="event.stopPropagation();revokeEventQr(${row.id})">Revocar</button>` : ''}
        </div>`;
    }).join('');
}

function eventQrById(id) {
    return _eventQrRows.find(row => parseInt(row.id, 10) === parseInt(id, 10)) || null;
}

function copyEventQrById(id) {
    const row = eventQrById(id);
    if (row?.url) copyEventQrLink(row.url);
}

function renderEventQrDrawer(row, events, loading, error) {
    if (!row) return;
    const active = row.estado === 'activa';
    const trial = parseInt(row.trial_dias || 0, 10);
    const cardReq = parseInt(row.solicita_tarjeta_registro || 0, 10) === 1;
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + encodeURIComponent(row.url);
    return `
        <div class="sa-drawer-section" style="text-align:center">
            <img src="${qrUrl}" alt="QR GeriApp" style="width:260px;max-width:100%;height:auto;border-radius:8px;border:1px solid var(--cd-border);background:#fff;padding:10px;margin:0 auto 12px;display:block">
            <input class="cd-input" value="${esc(row.url)}" readonly style="font-size:12px;text-align:center;margin-bottom:10px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
                <button class="cd-btn-submit cd-btn-secondary" onclick="copyEventQrById(${row.id})">Copiar link</button>
                <a class="cd-btn-submit" href="${esc(row.url)}" target="_blank" rel="noopener" style="text-decoration:none">Abrir registro</a>
                ${active ? `<button class="cd-btn-submit sa-btn-danger" onclick="revokeEventQr(${row.id});closeDrawer()">Revocar</button>` : ''}
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Resumen</h4>
            ${renderEventQrMetricGrid(row)}
        </div>
        <div class="sa-drawer-section">
            <h4>Detalles</h4>
            <table class="sa-mini-table"><tbody>
                <tr><th>Estado</th><td>${esc(row.estado)}</td></tr>
                <tr><th>Paquete</th><td>${esc(row.plan_nombre)} (${trial > 0 ? trial + ' días trial' : 'sin trial'}${cardReq ? ', pide tarjeta' : ''})</td></tr>
                <tr><th>Expira</th><td>${fmtDt(row.expires_at)}</td></tr>
                <tr><th>Creado</th><td>${fmtDt(row.creado_at)}</td></tr>
                <tr><th>Primer escaneo</th><td>${row.first_scan_at ? fmtDt(row.first_scan_at) : '—'}</td></tr>
                <tr><th>Último escaneo</th><td>${row.last_scan_at ? fmtDt(row.last_scan_at) : '—'}</td></tr>
                <tr><th>Primer registro</th><td>${row.first_registered_at ? fmtDt(row.first_registered_at) : '—'}</td></tr>
                <tr><th>Último registro</th><td>${row.last_registered_at ? fmtDt(row.last_registered_at) : (row.last_used_at ? fmtDt(row.last_used_at) : '—')}</td></tr>
            </tbody></table>
        </div>
        <div class="sa-drawer-section">
            <h4>Actividad</h4>
            ${error ? `<div class="sa-conn-err">Error: ${esc(error)}</div>` : renderEventQrTimeline(loading ? null : events)}
        </div>`;
}

function openEventQrDrawer(id) {
    const row = eventQrById(id);
    if (!row) return;
    const title = row.nombre_evento || 'QR GeriApp';
    openDrawer(title, renderEventQrDrawer(row, null, true, ''));
    api('event_qr', {sub:'details', id})
        .then(r => {
            const detail = r.invitacion || row;
            const idx = _eventQrRows.findIndex(item => parseInt(item.id, 10) === parseInt(id, 10));
            if (idx >= 0) _eventQrRows[idx] = {..._eventQrRows[idx], ...detail};
            openDrawer(detail.nombre_evento || 'QR GeriApp', renderEventQrDrawer(detail, r.eventos || [], false, ''));
        })
        .catch(e => openDrawer(title, renderEventQrDrawer(row, [], false, e.message)));
}

async function revokeEventQr(id) {
    if (!confirm('¿Revocar este QR?')) return;
    try {
        await api('event_qr', {sub:'revoke', id});
        await loadEventQr();
        saAlert('QR revocado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════
// INTEGRACIONES GLOBALES (.env)
// ═══════════════════════════════════════════════════════════════════════════

async function loadIntegraciones() {
    const cards = document.getElementById('integrationsCards');
    if (cards) cards.innerHTML = ghostRows(3);
    try {
        const r = await api('integraciones', null, 'GET');
        _integracionesLoaded = true;
        renderIntegraciones(r);
    } catch (e) {
        if (cards) cards.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderIntegraciones(data) {
    const cfg = data.integraciones || {};
    const ready = data.ready || {};
    const cards = [
        {
            title: 'SMTP / Correo',
            ok: !!ready.smtp,
            rows: [
                ['Host', cfg.smtp_host || 'No configurado'],
                ['Puerto', cfg.smtp_port || '—'],
                ['Cifrado', cfg.smtp_encriptacion || '—'],
                ['Usuario', cfg.smtp_usuario || '—'],
                ['Password', cfg.smtp_password || 'No configurado'],
                ['From', `${cfg.smtp_from_nombre || 'GeriApp'} <${cfg.smtp_from_email || 'sin correo'}>`],
            ]
        },
        {
            title: 'WhatsApp',
            ok: !!ready.whatsapp,
            rows: [
                ['Proveedor', cfg.wa_proveedor || '—'],
                ['API key', cfg.wa_api_key || 'No configurada'],
                ['Instance ID', cfg.wa_instance_id || '—'],
                ['Teléfono', cfg.wa_phone || '—'],
                ['Activo', String(cfg.wa_activo ?? '—')],
            ]
        },
        {
            title: 'IA',
            ok: !!ready.ia,
            rows: [
                ['Proveedor', cfg.ia_proveedor || '—'],
                ['Modelo', cfg.ia_modelo || '—'],
                ['API key', cfg.ia_api_key || 'No configurada'],
                ['Max palabras', cfg.ia_max_palabras || '—'],
            ]
        }
    ];

    const el = document.getElementById('integrationsCards');
    if (el) {
        el.innerHTML = cards.map(card => `
            <div class="sa-int-card ${card.ok ? 'is-ok' : 'is-warn'}">
                <div class="sa-int-card-head">
                    <h3>${esc(card.title)}</h3>
                    <span>${card.ok ? 'Listo' : 'Incompleto'}</span>
                </div>
                <table class="sa-mini-table"><tbody>
                    ${card.rows.map(([k,v]) => `<tr><th>${esc(k)}</th><td>${esc(v)}</td></tr>`).join('')}
                </tbody></table>
            </div>
        `).join('');
    }

    const cov = data.coverage || {};
    const coverageEl = document.getElementById('integrationsCoverage');
    if (coverageEl) {
        coverageEl.innerHTML = `
            <div class="sa-int-coverage-item"><strong>${esc(cov.total ?? 0)}</strong><span>Instituciones</span></div>
            <div class="sa-int-coverage-item"><strong>${esc(cov.smtp_incompleto ?? 0)}</strong><span>SMTP incompleto</span></div>
            <div class="sa-int-coverage-item"><strong>${esc(cov.wa_incompleto ?? 0)}</strong><span>WhatsApp incompleto</span></div>
            <div class="sa-int-coverage-item"><strong>${esc(cov.ia_incompleto ?? 0)}</strong><span>IA incompleta</span></div>
        `;
    }
}

async function applyIntegrationDefaults() {
    if (!confirm('¿Aplicar los defaults de .env solo a campos vacíos de las instituciones existentes?')) return;
    try {
        const r = await api('integraciones', {sub:'apply_defaults'});
        saAlert(`Defaults aplicados a ${r.updated || 0} institución(es)`, 'success', `${r.fields || 0} campo(s) completados`);
        await loadIntegraciones();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function testGlobalSmtp() {
    const email = document.getElementById('integrationTestEmail')?.value.trim() || '';
    if (!email) return saAlert('Indica un correo destino', 'error');
    try {
        await api('integraciones', {sub:'test_smtp', dest_email:email});
        saAlert('Correo de prueba enviado', 'success');
    } catch (e) { saAlert('Error SMTP: ' + e.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════
// PLANES + ADDONS (Billing F2)
// ═══════════════════════════════════════════════════════════════════════════
let _addons = [];
const SUPPORTED_CURRENCIES = ['MXN','USD','COP','CAD'];
const SUPPORTED_PERIODS    = ['mensual','anual'];

async function loadPlanes() {
    const el = document.getElementById('planList');
    el.innerHTML = ghostRows(3);
    try {
        const r = await api('planes', null, 'GET');
        _planes = r.planes;
        await loadAddons();
        renderPlanes();
        loadStripeStatus();
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

// ── Status Stripe ────────────────────────────────────────────────────────
async function loadStripeStatus() {
    const el = document.getElementById('stripeStatusPanel');
    if (!el) return;
    try {
        const r = await api('stripe_status', null, 'GET');
        const s = r.stats || {};
        const planesPct = s.planes_total > 0   ? Math.round((s.planes_synced   / s.planes_total)   * 100) : 0;
        const preciosPct= s.precios_total > 0  ? Math.round((s.precios_synced  / s.precios_total)  * 100) : 0;
        const addonsPct = s.addons_total > 0   ? Math.round((s.addons_synced   / s.addons_total)   * 100) : 0;
        const apPct     = s.addon_precios_total > 0 ? Math.round((s.addon_precios_synced / s.addon_precios_total) * 100) : 0;
        const papPct    = s.plan_addon_precios_total > 0 ? Math.round((s.plan_addon_precios_synced / s.plan_addon_precios_total) * 100) : 0;
        const fullySynced = (s.planes_synced == s.planes_total && s.precios_synced == s.precios_total && s.addons_synced == s.addons_total && s.addon_precios_synced == s.addon_precios_total && s.plan_addon_precios_synced == s.plan_addon_precios_total);
        const modeColor = r.mode === 'live' ? '#dc2626' : '#178391';
        const modeBg    = r.mode === 'live' ? '#fee2e2' : '#e0f2fe';

        if (!r.configured) {
            el.innerHTML = `
                <div style="display:flex;align-items:center;gap:10px;color:#dc2626;font-weight:600;margin-bottom:6px">
                    <span style="width:10px;height:10px;border-radius:50%;background:#dc2626;display:inline-block"></span>
                    Stripe NO configurado
                </div>
                <p style="margin:0;color:var(--cd-text-muted);font-size:12px">
                    Crea el archivo <code>v9/secretos/stripe.json</code> con tus claves.
                    Hay un ejemplo en <code>stripe.example.json</code>. Mientras no esté configurado, los botones de pago no funcionarán.
                </p>
                ${r.config_error ? `<p style="margin:6px 0 0;color:#dc2626;font-size:11px">${esc(r.config_error)}</p>` : ''}`;
            return;
        }
        el.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <span style="width:10px;height:10px;border-radius:50%;background:${fullySynced ? '#16a34a' : '#ca8a04'};display:inline-block"></span>
                <strong>Stripe configurado</strong>
                <span style="background:${modeBg};color:${modeColor};padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase">${esc(r.mode)}</span>
                ${r.webhook_ready
                    ? '<span style="color:#16a34a;font-size:11px">✓ Webhook secret OK</span>'
                    : '<span style="color:#ca8a04;font-size:11px">⚠ Webhook secret no configurado</span>'}
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:12px">
                ${_syncStat('Planes',          s.planes_synced,         s.planes_total,         planesPct)}
                ${_syncStat('Precios de plan', s.precios_synced,        s.precios_total,        preciosPct)}
                ${_syncStat('Add-ons',         s.addons_synced,         s.addons_total,         addonsPct)}
                ${_syncStat('Precios de addon',s.addon_precios_synced,  s.addon_precios_total,  apPct)}
                ${_syncStat('Add-ons por paquete', s.plan_addon_precios_synced, s.plan_addon_precios_total, papPct)}
            </div>
            ${!fullySynced ? '<p style="margin:8px 0 0;font-size:11px;color:var(--cd-text-muted)">Pulsa <strong>⇅ Sincronizar con Stripe</strong> para crear los IDs faltantes.</p>' : ''}`;
    } catch (e) {
        el.innerHTML = `<div style="color:#dc2626;font-size:12px">Error cargando estado Stripe: ${esc(e.message)}</div>`;
    }
}

function _syncStat(label, synced, total, pct) {
    const color = pct === 100 ? '#16a34a' : (pct > 0 ? '#ca8a04' : '#94a3b8');
    return `
        <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:3px">
                <span style="color:var(--cd-text-muted)">${esc(label)}</span>
                <span style="font-weight:600;color:${color}">${synced}/${total}</span>
            </div>
            <div style="height:4px;background:var(--cd-border);border-radius:2px;overflow:hidden">
                <div style="height:100%;width:${pct}%;background:${color};transition:width .3s"></div>
            </div>
        </div>`;
}

async function runSyncStripe() {
    if (!confirm('Esto creará Products y Prices en Stripe (modo configurado en stripe.json) para todos los planes/addons activos sin IDs.\n\nEs idempotente — si ya existen, los omite. ¿Continuar?')) return;
    const btn = document.getElementById('btnSyncStripe');
    if (btn) { btn.disabled = true; btn.textContent = 'Sincronizando…'; }
    try {
        const r = await api('sync_stripe', {});
        loadPlanes();   // recarga + status
        openModal('Resultado del sync Stripe', `
            <pre style="background:var(--cd-bg);padding:12px;border-radius:6px;font-size:11px;font-family:monospace;max-height:500px;overflow:auto;white-space:pre-wrap">${esc(r.log || '(sin salida)')}</pre>
            <div class="cd-cfg-actions"><button class="cd-btn-submit" onclick="closeModal()">Cerrar</button></div>
        `);
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '⇅ Sincronizar con Stripe'; }
    }
}

function _planSummaryRow(p) {
    const fmtSeats = v => v == null || v === '' || Number(v) <= 0 ? '∞' : v;
    const tierColor = { basico:'#178391', intermedio:'#9db9d0', empresarial:'#f59e0b', custom:'#64748b' }[p.tier] || '#64748b';
    const cot = +p.requiere_cotizacion ? '<span style="background:#fef3c7;color:#854d0e;padding:1px 6px;border-radius:4px;font-size:10px;margin-left:6px">Cotización</span>' : '';
    const cardReq = +p.solicita_tarjeta_registro ? '<span style="background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:4px;font-size:10px;margin-left:6px">Tarjeta registro</span>' : '';
    // Precio MXN mensual de referencia (si existe).
    const mxnMonthly = (p.precios || []).find(x => x.moneda === 'MXN' && x.periodo === 'mensual');
    const priceLabel = +p.requiere_cotizacion
        ? 'A cotizar'
        : (mxnMonthly ? `$${parseFloat(mxnMonthly.precio).toFixed(0)} MXN/mes` : `$${parseFloat(p.precio).toFixed(0)}`);
    return `
        <div class="cd-cfg-user-card" onclick="editPlan(${p.id})">
            <div class="cd-cfg-user-info">
                <strong>${esc(p.nombre)} <span style="color:${tierColor};font-size:11px;font-weight:500;text-transform:uppercase">· ${esc(p.tier || '')}</span> ${cot}${cardReq}</strong>
                <span>${esc(p.key)} · Trial: ${Math.max(0, parseInt(p.trial_dias || 0, 10))} días · Inst: ${fmtSeats(p.max_instituciones)} · Res: ${fmtSeats(p.max_residentes)} · Fam: ${fmtSeats(p.max_familiares)} · Admin: ${fmtSeats(p.max_admin)} · Cuidador: ${fmtSeats(p.max_cuidador)} · Médico: ${fmtSeats(p.max_medico)} · ${esc(priceLabel)}</span>
            </div>
            <span class="cd-cfg-user-status ${p.activo == 1 ? 'active' : ''}">${p.activo == 1 ? 'Activo' : 'Inactivo'}</span>
        </div>`;
}

function renderPlanes() {
    const el = document.getElementById('planList');
    if (!_planes.length) { el.innerHTML = '<p class="sa-empty">No hay planes. Pulsa “Sembrar paquetes” para crear los 7 estándar.</p>'; return; }
    el.innerHTML = _planes.map(_planSummaryRow).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnNewPlan')?.addEventListener('click', () => editPlan(0));
    document.getElementById('btnNewAddon')?.addEventListener('click', () => editAddon(0));
    document.getElementById('btnSeedBilling')?.addEventListener('click', () => runSeedBilling());
    document.getElementById('btnSyncStripe')?.addEventListener('click', () => runSyncStripe());
    document.getElementById('btnNewInst').addEventListener('click', () => createInstitucion());
    document.getElementById('btnNewUser').addEventListener('click', () => createUsuario());
    document.getElementById('btnRunCheckDb').addEventListener('click', () => runCheckDb());
    document.getElementById('btnNormalizePhones').addEventListener('click', () => normalizePhonesFromAudit());
    document.getElementById('btnFixAllDb').addEventListener('click', () => fixAllDbGlobal());
    document.getElementById('btnRunDeploy').addEventListener('click', () => runDeploy());
});

function _pricesEditor(precios, ownerKey, ownerId) {
    // ownerKey: 'plan' | 'addon'  → determina qué endpoint usar.
    // Tabla con una fila por moneda × periodo. Edición inline (precio,
    // stripe_price_id, activo) que llama a `*_precios upsert` al cambiar.
    const byKey = {};
    (precios || []).forEach(p => byKey[`${p.moneda}|${p.periodo}`] = p);
    let html = `
        <table class="sa-pricing-table" style="width:100%;border-collapse:collapse;font-size:12px;margin-top:8px">
            <thead>
                <tr style="background:var(--cd-bg);text-align:left">
                    <th style="padding:6px 8px">Moneda</th>
                    <th style="padding:6px 8px">Periodo</th>
                    <th style="padding:6px 8px">Precio</th>
                    <th style="padding:6px 8px">Stripe price_id</th>
                    <th style="padding:6px 8px;text-align:center">Activo</th>
                </tr>
            </thead>
            <tbody>`;
    SUPPORTED_CURRENCIES.forEach(mon => {
        SUPPORTED_PERIODS.forEach(per => {
            const row = byKey[`${mon}|${per}`] || { precio: 0, stripe_price_id: '', activo: 0 };
            const inputId = `pp_${ownerKey}_${ownerId}_${mon}_${per}`;
            html += `
                <tr>
                    <td style="padding:6px 8px;font-weight:600">${mon}</td>
                    <td style="padding:6px 8px">${per}</td>
                    <td style="padding:4px 8px"><input type="number" step="0.01" min="0" id="${inputId}_precio" value="${row.precio || 0}" style="width:90px;padding:4px 6px;border:1px solid var(--cd-border);border-radius:4px"></td>
                    <td style="padding:4px 8px"><input type="text" id="${inputId}_sid" value="${esc(row.stripe_price_id || '')}" placeholder="price_xxx" style="width:170px;padding:4px 6px;border:1px solid var(--cd-border);border-radius:4px;font-family:monospace;font-size:11px"></td>
                    <td style="padding:4px 8px;text-align:center"><input type="checkbox" id="${inputId}_act" ${+row.activo ? 'checked' : ''}></td>
                </tr>`;
        });
    });
    html += `</tbody></table>
        <p style="font-size:11px;color:var(--cd-text-muted);margin:6px 0 0">
            Cambios se guardan al pulsar “Guardar precios”. Cada combinación moneda+periodo necesita su propio <code>stripe_price_id</code> (creado en el portal Stripe).
        </p>
        <button class="cd-btn-submit cd-btn-secondary" style="margin-top:8px" onclick="savePrices('${ownerKey}', ${ownerId})">Guardar precios</button>`;
    return html;
}

async function savePrices(ownerKey, ownerId) {
    // Upsert en lote — una llamada por celda modificada. No bloqueamos UI:
    // recolectamos promesas y reportamos al final.
    const endpoint = ownerKey === 'plan' ? 'plan_precios' : 'addon_precios';
    const idKey    = ownerKey === 'plan' ? 'plan_id' : 'addon_id';
    const tasks = [];
    SUPPORTED_CURRENCIES.forEach(mon => {
        SUPPORTED_PERIODS.forEach(per => {
            const inputId = `pp_${ownerKey}_${ownerId}_${mon}_${per}`;
            const precio = document.getElementById(`${inputId}_precio`)?.value;
            const sid    = document.getElementById(`${inputId}_sid`)?.value || null;
            const act    = document.getElementById(`${inputId}_act`)?.checked ? 1 : 0;
            if (precio == null) return;
            const body = { sub: 'upsert', moneda: mon, periodo: per, precio, stripe_price_id: sid, activo: act };
            body[idKey] = ownerId;
            tasks.push(api(endpoint, body));
        });
    });
    try {
        await Promise.all(tasks);
        saAlert('Precios guardados', 'success');
        if (ownerKey === 'plan') loadPlanes(); else loadAddons();
    } catch (e) {
        saAlert('Error guardando precios: ' + e.message, 'error');
    }
}

function _planAddonPricesEditor(plan) {
    if (!plan?.id) return '<p style="font-size:12px;color:var(--cd-text-muted);margin-top:8px">Guarda primero el plan para configurar costos de add-ons por paquete.</p>';
    if (!_addons.length) return '<p style="font-size:12px;color:var(--cd-text-muted);margin-top:8px">Carga o crea add-ons para configurar costos por paquete.</p>';
    const byKey = {};
    (plan.addon_precios || []).forEach(p => byKey[`${p.addon_id}|${p.moneda}|${p.periodo}`] = p);
    let html = `
        <table class="sa-pricing-table" style="width:100%;border-collapse:collapse;font-size:12px;margin-top:8px">
            <thead>
                <tr style="background:var(--cd-bg);text-align:left">
                    <th style="padding:6px 8px">Add-on</th>
                    <th style="padding:6px 8px">Moneda</th>
                    <th style="padding:6px 8px">Periodo</th>
                    <th style="padding:6px 8px">Precio</th>
                    <th style="padding:6px 8px">Stripe price_id</th>
                    <th style="padding:6px 8px;text-align:center">Activo</th>
                </tr>
            </thead>
            <tbody>`;
    _addons.forEach(addon => {
        SUPPORTED_CURRENCIES.forEach(mon => {
            SUPPORTED_PERIODS.forEach(per => {
                const row = byKey[`${addon.id}|${mon}|${per}`] || { precio: 0, stripe_price_id: '', activo: 0 };
                const inputId = `pap_${plan.id}_${addon.id}_${mon}_${per}`;
                html += `
                    <tr>
                        <td style="padding:6px 8px;font-weight:600">${esc(addon.nombre)}<br><span style="font-size:10px;color:var(--cd-text-muted)">${esc(addon.tipo)}</span></td>
                        <td style="padding:6px 8px;font-weight:600">${mon}</td>
                        <td style="padding:6px 8px">${per}</td>
                        <td style="padding:4px 8px"><input type="number" step="0.01" min="0" id="${inputId}_precio" value="${row.precio || 0}" style="width:90px;padding:4px 6px;border:1px solid var(--cd-border);border-radius:4px"></td>
                        <td style="padding:4px 8px"><input type="text" id="${inputId}_sid" value="${esc(row.stripe_price_id || '')}" placeholder="price_xxx" style="width:170px;padding:4px 6px;border:1px solid var(--cd-border);border-radius:4px;font-family:monospace;font-size:11px"></td>
                        <td style="padding:4px 8px;text-align:center"><input type="checkbox" id="${inputId}_act" ${+row.activo ? 'checked' : ''}></td>
                    </tr>`;
            });
        });
    });
    html += `</tbody></table>
        <p style="font-size:11px;color:var(--cd-text-muted);margin:6px 0 0">
            Estos precios tienen prioridad sobre el precio global del add-on cuando el cliente está en este paquete. Si no hay fila activa, se usa el precio global del add-on.
        </p>
        <button class="cd-btn-submit cd-btn-secondary" style="margin-top:8px" onclick="savePlanAddonPrices(${plan.id})">Guardar costos de add-ons</button>`;
    return html;
}

async function savePlanAddonPrices(planId) {
    const tasks = [];
    _addons.forEach(addon => {
        SUPPORTED_CURRENCIES.forEach(mon => {
            SUPPORTED_PERIODS.forEach(per => {
                const inputId = `pap_${planId}_${addon.id}_${mon}_${per}`;
                const precio = document.getElementById(`${inputId}_precio`)?.value;
                const sid    = document.getElementById(`${inputId}_sid`)?.value || null;
                const act    = document.getElementById(`${inputId}_act`)?.checked ? 1 : 0;
                if (precio == null) return;
                tasks.push(api('plan_addon_precios', {
                    sub: 'upsert', plan_id: planId, addon_id: addon.id,
                    moneda: mon, periodo: per, precio, stripe_price_id: sid, activo: act
                }));
            });
        });
    });
    try {
        await Promise.all(tasks);
        saAlert('Costos de add-ons guardados', 'success');
        loadPlanes();
        loadStripeStatus();
    } catch (e) {
        saAlert('Error guardando costos de add-ons: ' + e.message, 'error');
    }
}

function _seatLimitsTable(plan) {
    // Tabla de límites por tipo de seat con el mismo estilo que sa-pricing-table.
    // Convención de UI: 0 = ilimitado. En BD se persiste NULL para ilimitado.
    const rows = [
        { id: 'mPlanMaxInst', label: 'Instituciones',  val: plan.max_instituciones,  hint: 'Máximo de instituciones cubiertas por la suscripción.' },
        { id: 'mPlanMaxRes',  label: 'Residentes',     val: plan.max_residentes,     hint: 'Máximo de residentes activos sumados entre todas las instituciones.' },
        { id: 'mPlanMaxFam',  label: 'Familiares',     val: plan.max_familiares,     hint: 'Familiares con cuenta activa vinculados a residentes.' },
        { id: 'mPlanMaxAdm',  label: 'Admin',          val: plan.max_admin,          hint: 'Usuarios con rol admin.' },
        { id: 'mPlanMaxCui',  label: 'Cuidador',       val: plan.max_cuidador,       hint: 'Usuarios con rol cuidador.' },
        { id: 'mPlanMaxMed',  label: 'Médico',         val: plan.max_medico,         hint: 'Usuarios con rol médico.' },
    ];
    // val null/'' → 0 (ilimitado en UI)
    const display = v => (v === null || v === undefined || v === '') ? 0 : (parseInt(v, 10) || 0);
    let html = `
        <table class="sa-pricing-table" style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px">
            <thead>
                <tr style="background:var(--cd-bg);text-align:left">
                    <th style="padding:6px 8px">Tipo de seat</th>
                    <th style="padding:6px 8px;width:120px">Máximo</th>
                    <th style="padding:6px 8px">Detalle</th>
                </tr>
            </thead>
            <tbody>`;
    rows.forEach(r => {
        html += `
            <tr>
                <td style="padding:6px 8px;font-weight:600">${esc(r.label)}</td>
                <td style="padding:4px 8px">
                    <input type="number" min="0" id="${r.id}" value="${display(r.val)}" style="width:90px;padding:4px 6px;border:1px solid var(--cd-border);border-radius:4px">
                </td>
                <td style="padding:6px 8px;color:var(--cd-text-muted);font-size:11px">${esc(r.hint)}</td>
            </tr>`;
    });
    html += `</tbody></table>
        <p style="font-size:11px;color:var(--cd-text-muted);margin:6px 0 8px">
            <strong>0 = ilimitado.</strong> Cualquier otro valor es el tope del plan; al alcanzarlo se bloquean nuevas altas y se requieren asientos extra.
        </p>`;
    return html;
}

function editPlan(id) {
    const blank = {
        id: 0, key: '', nombre: '', tier: 'basico', precio: 0, descripcion: '',
        max_residentes: '', max_usuarios: '', max_instituciones: '', max_familiares: '',
        max_admin: '', max_cuidador: '', max_medico: '',
        admins_ilimitados: 1, cuidadores_ilimitados: 1, medicos_ilimitados: 1,
        requiere_cotizacion: 0, solicita_tarjeta_registro: 0, trial_dias: 30, stripe_product_id: '', orden: (_planes.length + 1), activo: 1,
        precios: [],
    };
    const plan = id ? _planes.find(p => p.id === id) : blank;
    if (!plan) return;

    const pricesHtml = id ? _pricesEditor(plan.precios, 'plan', id) :
        '<p style="font-size:12px;color:var(--cd-text-muted);margin-top:8px">Guarda primero el plan para configurar sus precios por moneda.</p>';

    openModal(id ? `Editar Plan: ${esc(plan.nombre)}` : 'Nuevo Plan', `
        <div class="cd-cfg-form">
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label">Key (slug)</label><input class="cd-input" id="mPlanKey" value="${esc(plan.key)}" placeholder="basico_1"></div>
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="mPlanNombre" value="${esc(plan.nombre)}"></div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label">Tier</label>
                    <select class="cd-input cd-select-native" id="mPlanTier">
                        ${['basico','intermedio','empresarial','custom'].map(t => `<option value="${t}" ${plan.tier === t ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                </div>
                <div class="cd-form-group"><label class="cd-form-label">Orden</label><input class="cd-input" id="mPlanOrden" type="number" value="${plan.orden ?? 0}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Activo</label>
                    <select class="cd-input cd-select-native" id="mPlanActivo">
                        <option value="1" ${plan.activo == 1 ? 'selected' : ''}>Sí</option>
                        <option value="0" ${plan.activo == 0 ? 'selected' : ''}>No</option>
                    </select>
                </div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label">Descripción</label><input class="cd-input" id="mPlanDesc" value="${esc(plan.descripcion || '')}"></div>

            <h4 style="margin:14px 0 6px;font-size:12px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Límites de seats (compartidos entre instituciones del owner)</h4>
            ${_seatLimitsTable(plan)}

            <h4 style="margin:14px 0 6px;font-size:12px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Stripe</h4>
            <div class="cd-form-row">
                <div class="cd-form-group" style="flex:2"><label class="cd-form-label">Stripe product_id</label><input class="cd-input" id="mPlanStripeProd" value="${esc(plan.stripe_product_id || '')}" placeholder="prod_xxx"></div>
                <div class="cd-form-group"><label class="cd-form-label">Requiere cotización</label>
                    <select class="cd-input cd-select-native" id="mPlanReqCot">
                        <option value="0" ${plan.requiere_cotizacion == 0 ? 'selected' : ''}>No</option>
                        <option value="1" ${plan.requiere_cotizacion == 1 ? 'selected' : ''}>Sí (no se vende por checkout)</option>
                    </select>
                </div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label">Solicitar tarjeta al registro</label>
                <select class="cd-input cd-select-native" id="mPlanTarjetaRegistro">
                    <option value="0" ${plan.solicita_tarjeta_registro == 0 ? 'selected' : ''}>No, permitir trial sin tarjeta</option>
                    <option value="1" ${plan.solicita_tarjeta_registro == 1 ? 'selected' : ''}>Sí, pedir tarjeta antes de iniciar uso</option>
                </select>
                <p style="font-size:11px;color:var(--cd-text-muted);margin:4px 0 0">Cuando está activo, el registro por QR envía al admin recién creado a Checkout y Stripe solicita una tarjeta aun si el paquete tiene días de prueba.</p>
            </div>
            <div class="cd-form-group"><label class="cd-form-label">Días de prueba para checkout</label><input class="cd-input" id="mPlanTrialDias" type="number" min="0" max="365" value="${parseInt(plan.trial_dias ?? 30, 10) || 0}"><p style="font-size:11px;color:var(--cd-text-muted);margin:4px 0 0">0 desactiva trial para este paquete. Se aplica sólo al primer checkout de una suscripción nueva.</p></div>
            <div class="cd-form-group"><label class="cd-form-label">Precio referencial (legado, sólo para vista rápida)</label><input class="cd-input" id="mPlanPrecio" type="number" step="0.01" value="${plan.precio}"></div>

            <h4 style="margin:14px 0 6px;font-size:12px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Precios por moneda y periodo</h4>
            ${pricesHtml}

            <h4 style="margin:14px 0 6px;font-size:12px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Costo de add-ons para este paquete</h4>
            ${_planAddonPricesEditor(plan)}

            <div class="cd-cfg-actions" style="margin-top:14px">
                <button class="cd-btn-submit" onclick="savePlan(${id})">${id ? 'Guardar plan' : 'Crear plan'}</button>
                ${id ? `<button class="cd-btn-submit" style="background:#dc2626;color:#fff" onclick="deletePlan(${id})">Eliminar</button>` : ''}
                <button class="cd-btn-submit cd-btn-secondary" onclick="closeModal()">Cancelar</button>
            </div>
        </div>
    `);
}

async function savePlan(id) {
    // UI usa 0 = ilimitado; persistimos NULL (string vacío) para que la API lo normalice.
    const limFromUi = (elId) => {
        const raw = document.getElementById(elId)?.value;
        if (raw === '' || raw === null || raw === undefined) return '';
        const n = parseInt(raw, 10);
        if (isNaN(n) || n <= 0) return ''; // 0 ó negativo → ilimitado → NULL
        return String(n);
    };
    const data = {
        sub: id ? 'update' : 'create',
        key: document.getElementById('mPlanKey').value.trim(),
        nombre: document.getElementById('mPlanNombre').value.trim(),
        tier: document.getElementById('mPlanTier').value,
        precio: document.getElementById('mPlanPrecio').value || 0,
        descripcion: document.getElementById('mPlanDesc').value,
        max_residentes:    limFromUi('mPlanMaxRes'),
        max_instituciones: limFromUi('mPlanMaxInst'),
        max_familiares:    limFromUi('mPlanMaxFam'),
        max_admin:         limFromUi('mPlanMaxAdm'),
        max_cuidador:      limFromUi('mPlanMaxCui'),
        max_medico:        limFromUi('mPlanMaxMed'),
        stripe_product_id: document.getElementById('mPlanStripeProd').value.trim() || null,
        requiere_cotizacion: parseInt(document.getElementById('mPlanReqCot').value, 10),
        solicita_tarjeta_registro: parseInt(document.getElementById('mPlanTarjetaRegistro').value, 10),
        trial_dias: Math.max(0, Math.min(365, parseInt(document.getElementById('mPlanTrialDias').value, 10) || 0)),
        orden:  parseInt(document.getElementById('mPlanOrden').value, 10) || 0,
        activo: parseInt(document.getElementById('mPlanActivo').value, 10),
    };
    if (id) data.id = id;
    if (!data.key || !data.nombre) { saAlert('Key y nombre son obligatorios', 'error'); return; }
    try {
        await api('planes', data);
        closeModal();
        loadPlanes();
        loadStats();
        saAlert(id ? 'Plan actualizado' : 'Plan creado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function deletePlan(id) {
    if (!confirm('¿Eliminar este plan? Sus precios también se borrarán. Sólo se permite si NO hay suscripciones activas.')) return;
    try {
        await api('planes', { sub: 'delete', id });
        closeModal();
        loadPlanes();
        loadStats();
        saAlert('Plan eliminado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

// ── Add-ons ──────────────────────────────────────────────────────────────
async function loadAddons() {
    const el = document.getElementById('addonList');
    if (!el) return;
    el.innerHTML = ghostRows(2);
    try {
        const r = await api('addons', null, 'GET');
        _addons = r.addons || [];
        renderAddons();
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderAddons() {
    const el = document.getElementById('addonList');
    if (!_addons.length) { el.innerHTML = '<p class="sa-empty">No hay add-ons.</p>'; return; }
    el.innerHTML = _addons.map(a => {
        const mxn = (a.precios || []).find(p => p.moneda === 'MXN' && p.periodo === 'mensual');
        const priceLabel = mxn ? `$${parseFloat(mxn.precio).toFixed(0)} MXN/mes` : '—';
        const tipoLabel = a.tipo === 'asiento_familiar' ? 'Familiar' : 'Residente';
        return `
        <div class="cd-cfg-user-card" onclick="editAddon(${a.id})">
            <div class="cd-cfg-user-info">
                <strong>${esc(a.nombre)} <span style="color:var(--cd-text-muted);font-size:11px;font-weight:500">· ${tipoLabel}</span></strong>
                <span>${esc(a.codigo)} · ${esc(priceLabel)}</span>
            </div>
            <span class="cd-cfg-user-status ${a.activo == 1 ? 'active' : ''}">${a.activo == 1 ? 'Activo' : 'Inactivo'}</span>
        </div>`;
    }).join('');
}

function editAddon(id) {
    const blank = { id:0, codigo:'', nombre:'', tipo:'asiento_familiar', descripcion:'', stripe_product_id:'', activo:1, precios:[] };
    const a = id ? _addons.find(x => x.id === id) : blank;
    if (!a) return;

    const pricesHtml = id ? _pricesEditor(a.precios, 'addon', id) :
        '<p style="font-size:12px;color:var(--cd-text-muted);margin-top:8px">Guarda primero el add-on para configurar sus precios.</p>';

    openModal(id ? `Editar Add-on: ${esc(a.nombre)}` : 'Nuevo Add-on', `
        <div class="cd-cfg-form">
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label">Código</label><input class="cd-input" id="mAddonCodigo" value="${esc(a.codigo)}" placeholder="familiar_extra"></div>
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="mAddonNombre" value="${esc(a.nombre)}"></div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label">Tipo</label>
                    <select class="cd-input cd-select-native" id="mAddonTipo">
                        <option value="asiento_familiar"  ${a.tipo === 'asiento_familiar'  ? 'selected' : ''}>Asiento Familiar</option>
                        <option value="asiento_residente" ${a.tipo === 'asiento_residente' ? 'selected' : ''}>Asiento Residente</option>
                    </select>
                </div>
                <div class="cd-form-group"><label class="cd-form-label">Activo</label>
                    <select class="cd-input cd-select-native" id="mAddonActivo">
                        <option value="1" ${a.activo == 1 ? 'selected' : ''}>Sí</option>
                        <option value="0" ${a.activo == 0 ? 'selected' : ''}>No</option>
                    </select>
                </div>
                <div class="cd-form-group"><label class="cd-form-label">Stripe product_id</label><input class="cd-input" id="mAddonStripeProd" value="${esc(a.stripe_product_id || '')}" placeholder="prod_xxx"></div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label">Descripción</label><input class="cd-input" id="mAddonDesc" value="${esc(a.descripcion || '')}"></div>

            <h4 style="margin:14px 0 6px;font-size:12px;color:var(--cd-text-muted);text-transform:uppercase;letter-spacing:.5px">Precios por moneda y periodo</h4>
            ${pricesHtml}

            <div class="cd-cfg-actions" style="margin-top:14px">
                <button class="cd-btn-submit" onclick="saveAddon(${id})">${id ? 'Guardar add-on' : 'Crear add-on'}</button>
                <button class="cd-btn-submit cd-btn-secondary" onclick="closeModal()">Cancelar</button>
            </div>
        </div>
    `);
}

async function saveAddon(id) {
    const data = {
        sub: id ? 'update' : 'create',
        codigo: document.getElementById('mAddonCodigo').value.trim(),
        nombre: document.getElementById('mAddonNombre').value.trim(),
        tipo:   document.getElementById('mAddonTipo').value,
        descripcion: document.getElementById('mAddonDesc').value,
        stripe_product_id: document.getElementById('mAddonStripeProd').value.trim() || null,
        activo: parseInt(document.getElementById('mAddonActivo').value, 10),
    };
    if (id) data.id = id;
    if (!data.codigo || !data.nombre) { saAlert('Código y nombre son obligatorios', 'error'); return; }
    try {
        await api('addons', data);
        closeModal();
        loadAddons();
        saAlert(id ? 'Add-on actualizado' : 'Add-on creado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function runSeedBilling() {
    if (!confirm('Insertar/actualizar los 7 paquetes y los 2 add-ons estándar.\n\nEs idempotente (no duplica). ¿Continuar?')) return;
    try {
        const r = await api('seed_billing', {});
        loadPlanes();
        // Muestra log capturado del seed.
        openModal('Resultado del seed', `
            <pre style="background:var(--cd-bg);padding:12px;border-radius:6px;font-size:11px;font-family:monospace;max-height:400px;overflow:auto;white-space:pre-wrap">${esc(r.log || '(sin salida)')}</pre>
            <div class="cd-cfg-actions"><button class="cd-btn-submit" onclick="closeModal()">Cerrar</button></div>
        `);
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════
// USUARIOS
// ═══════════════════════════════════════════════════════════════════════════
async function loadUsuarios() {
    const el = document.getElementById('userList');
    el.innerHTML = ghostRows(5);
    try {
        const r = await api('usuarios', null, 'GET');
        _usuarios = r.usuarios;
        renderUsuarios();
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderUsuarios(filter) {
    const el = document.getElementById('userList');
    let list = _usuarios;

    const search = (filter?.search || document.getElementById('userSearch')?.value || '').toLowerCase();
    const rol    = saRoleStorage(filter?.rol || document.getElementById('userFilterRol')?.value || '');

    if (search) list = list.filter(u => (u.nombre || '').toLowerCase().includes(search) || (u.email || '').toLowerCase().includes(search));
    if (rol)    list = list.filter(u => u.rol === rol);

    if (!list.length) { el.innerHTML = '<p class="sa-empty">No se encontraron usuarios.</p>'; return; }

    el.innerHTML = list.map(u => `
        <div class="cd-cfg-user-card" onclick="openUserDrawer(${u.id})">
            <div class="cd-cfg-user-info">
                <strong>${esc(u.nombre)}</strong>
                <span>${esc(u.email)} · ${esc(u.institucion_nombre || 'Sin institución')}</span>
            </div>
            <span class="cd-cfg-user-role">${esc(saRoleLabel(u.rol))}</span>
            <span class="cd-cfg-user-status ${u.estado === 'activo' ? 'active' : ''}">${esc(u.estado)}</span>
            <button class="sa-house-btn sa-danger-icon" onclick="event.stopPropagation();deleteUsuario(${u.id})" title="Eliminar usuario">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
            </button>
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('userSearch').addEventListener('input', () => renderUsuarios());
    document.getElementById('userFilterRol').addEventListener('change', () => renderUsuarios());
});

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('userSearch').addEventListener('input', () => renderUsuarios());
    document.getElementById('userFilterRol').addEventListener('change', () => renderUsuarios());
    document.getElementById('pushTokenSearch')?.addEventListener('input', () => renderPushTokens());
});

// ═══════════════════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════

async function loadPushData() {
    try {
        const [statsR, tokensR] = await Promise.all([
            api('push_stats'),
            api('push_tokens'),
        ]);
        _pushStats  = statsR;
        _pushTokens = tokensR.tokens || [];
        _pushLoaded = true;
        renderPushStats();
        renderPushTokens();
        renderPushInstList();
        populatePushSelectors();
    } catch (e) {
        saAlert('Error cargando push: ' + e.message, 'error');
    }
}

function renderPushStats() {
    const s = _pushStats;
    document.getElementById('pushStatTotal').textContent   = s.total ?? 0;
    document.getElementById('pushStatUsers').textContent   = s.total_users ?? 0;
    const android = (s.by_platform || []).find(p => p.platform === 'android');
    const ios     = (s.by_platform || []).find(p => p.platform === 'ios');
    document.getElementById('pushStatAndroid').textContent = android ? android.cnt : 0;
    document.getElementById('pushStatIos').textContent     = ios ? ios.cnt : 0;
}

function renderPushTokens() {
    const search = (document.getElementById('pushTokenSearch')?.value || '').toLowerCase();
    const list   = _pushTokens.filter(t => {
        if (!search) return true;
        return (t.usuario_nombre || '').toLowerCase().includes(search)
            || (t.usuario_email || '').toLowerCase().includes(search)
            || (t.instituciones || '').toLowerCase().includes(search)
            || (t.platform || '').toLowerCase().includes(search);
    });
    const container = document.getElementById('pushTokenList');
    if (!list.length) {
        container.innerHTML = '<p class="sa-empty" style="padding:12px 0">No se encontraron tokens.</p>';
        return;
    }
    container.innerHTML = list.map(t => `
        <div class="cd-cfg-user-card" onclick="openPushTokenDrawer(${t.id})" style="cursor:pointer">
            <div class="cd-cfg-user-info">
                <div class="cd-cfg-user-name">${esc(t.usuario_nombre)}</div>
                <div class="cd-cfg-user-email">${esc(t.usuario_email)}</div>
            </div>
            <span class="cd-cfg-user-role">${esc(t.platform)}</span>
            <span class="cd-cfg-user-status active" style="font-size:11px">${esc(t.instituciones || 'Sin institución')}</span>
        </div>
    `).join('');
}

function renderPushInstList() {
    const list = _pushStats.by_inst || [];
    const container = document.getElementById('pushInstList');
    if (!list.length) {
        container.innerHTML = '<p class="sa-empty" style="padding:12px 0">No hay tokens registrados por institución.</p>';
        return;
    }
    container.innerHTML = list.map(i => `
        <div class="cd-cfg-user-card" style="cursor:pointer" onclick="filterTokensByInst(${i.id}, '${esc(i.nombre)}')">
            <div class="cd-cfg-user-info">
                <div class="cd-cfg-user-name">${esc(i.nombre)}</div>
                <div class="cd-cfg-user-email">${i.tokens} token${i.tokens != 1 ? 's' : ''} registrado${i.tokens != 1 ? 's' : ''}</div>
            </div>
            <span class="cd-cfg-user-role" style="min-width:auto">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </span>
        </div>
    `).join('');
}

function filterTokensByInst(instId, instName) {
    const search = document.getElementById('pushTokenSearch');
    if (search) { search.value = instName; renderPushTokens(); }
    // Scroll to tokens list
    document.getElementById('pushTokenList')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function populatePushSelectors() {
    // Users with tokens
    const usersMap = {};
    _pushTokens.forEach(t => { usersMap[t.usuario_id] = t.usuario_nombre; });
    const userSel = document.getElementById('pushUserId');
    userSel.innerHTML = Object.entries(usersMap)
        .map(([id, name]) => `<option value="${id}">${esc(name)}</option>`)
        .join('');
    if (!Object.keys(usersMap).length) {
        userSel.innerHTML = '<option value="">Sin usuarios con tokens</option>';
    }

    // Institutions
    const instSel = document.getElementById('pushInstId');
    const instList = _pushStats.by_inst || [];
    instSel.innerHTML = instList
        .map(i => `<option value="${i.id}">${esc(i.nombre)} (${i.tokens})</option>`)
        .join('');
    if (!instList.length) {
        instSel.innerHTML = '<option value="">Sin instituciones</option>';
    }
}

function onPushTargetChange() {
    const v = document.getElementById('pushTarget').value;
    document.getElementById('pushUserGroup').style.display = v === 'user' ? '' : 'none';
    document.getElementById('pushInstGroup').style.display = v === 'institution' ? '' : 'none';
}

async function sendPush() {
    const target = document.getElementById('pushTarget').value;
    const title  = document.getElementById('pushTitle').value.trim();
    const body   = document.getElementById('pushBody').value.trim();
    const resDiv = document.getElementById('pushSendResult');

    if (!title || !body) return saAlert('Título y mensaje son requeridos', 'error');

    const btn = document.getElementById('btnSendPush');
    btn.disabled = true;
    btn.textContent = 'Enviando…';

    // Mostrar progreso paso a paso
    resDiv.style.display = 'block';
    resDiv.style.color = 'var(--cd-text-muted)';
    resDiv.innerHTML = '⏳ Conectando con Firebase Cloud Messaging…';

    try {
        let payload = { title, body };
        let targetLabel = '';
        if (target === 'user') {
            payload.sub = 'to_user';
            payload.user_id = parseInt(document.getElementById('pushUserId').value);
            if (!payload.user_id) throw new Error('Selecciona un usuario');
            const opt = document.getElementById('pushUserId').selectedOptions[0];
            targetLabel = opt ? opt.textContent : 'Usuario #' + payload.user_id;
        } else if (target === 'institution') {
            payload.sub = 'to_institution';
            payload.institucion_id = parseInt(document.getElementById('pushInstId').value);
            if (!payload.institucion_id) throw new Error('Selecciona una institución');
            const opt = document.getElementById('pushInstId').selectedOptions[0];
            targetLabel = opt ? opt.textContent : 'Institución #' + payload.institucion_id;
        } else {
            payload.sub = 'to_all';
            targetLabel = 'Todos los usuarios';
        }

        resDiv.innerHTML = `⏳ Enviando a <strong>${targetLabel}</strong>…`;

        const r = await api('push_send', payload);
        const allResults = r.results || (r.result ? [r.result] : []);
        const sent = r.sent || allResults.length;
        const successes = allResults.filter(x => x && x.success).length;
        const failures = allResults.filter(x => x && !x.success);

        let html = '';
        if (successes === sent && sent > 0) {
            html = `✅ <strong>Enviado exitosamente</strong> a ${successes} dispositivo${sent > 1 ? 's' : ''}`;
            resDiv.style.color = 'var(--cd-success)';
        } else if (successes > 0) {
            html = `⚠️ <strong>${successes}/${sent}</strong> dispositivos recibieron la notificación`;
            resDiv.style.color = 'var(--cd-warning, #f59e0b)';
        } else if (sent === 0) {
            html = '⚠️ El destino no tiene tokens registrados. El usuario debe abrir la app desde un dispositivo móvil.';
            resDiv.style.color = 'var(--cd-warning, #f59e0b)';
        } else {
            html = `❌ <strong>Falló el envío</strong> a ${sent} dispositivo${sent > 1 ? 's' : ''}`;
            resDiv.style.color = 'var(--cd-danger)';
        }

        // Detallar errores individuales
        if (failures.length) {
            html += '<div style="margin-top:6px;font-size:12px;opacity:.85">';
            failures.forEach((f, i) => {
                const err = f.error || (f.response?.error?.message) || `HTTP ${f.http_code || '?'}`;
                html += `<div>• Token #${i + 1}: ${err}</div>`;
            });
            html += '</div>';
        }

        resDiv.innerHTML = html;
        saAlert(`Push: ${successes}/${sent} exitosos`, successes > 0 ? 'success' : 'error');
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
        resDiv.style.display = 'block';
        resDiv.style.color = 'var(--cd-danger)';
        resDiv.innerHTML = '❌ <strong>Error:</strong> ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Enviar';
    }
}

function openPushTokenDrawer(tokenId) {
    const t = _pushTokens.find(x => x.id == tokenId);
    if (!t) return;

    const shortToken = t.token.length > 40 ? t.token.substring(0, 20) + '…' + t.token.substring(t.token.length - 20) : t.token;

    openDrawer(t.usuario_nombre, `
        <div class="sa-drawer-section">
            <h4>Datos del token</h4>
            <div class="sa-user-detail">
                <div class="sa-user-detail-row"><span class="sa-label">Usuario</span><span class="sa-value">${esc(t.usuario_nombre)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Email</span><span class="sa-value">${esc(t.usuario_email)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Rol</span><span class="sa-value">${esc(t.usuario_rol)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Instituciones</span><span class="sa-value">${esc(t.instituciones || '—')}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Plataforma</span><span class="sa-value">${esc(t.platform)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Token</span><span class="sa-value" style="font-size:11px;word-break:break-all">${esc(shortToken)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Registrado</span><span class="sa-value">${fmtDt(t.created_at)}</span></div>
                <div class="sa-user-detail-row"><span class="sa-label">Último uso</span><span class="sa-value">${fmtDt(t.updated_at)}</span></div>
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Enviar push de prueba</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group">
                    <label class="cd-form-label">Título</label>
                    <input class="cd-input" id="dwPushTitle" value="Test Push" placeholder="Título">
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label">Mensaje</label>
                    <input class="cd-input" id="dwPushBody" value="Prueba desde superadmin" placeholder="Mensaje">
                </div>
                <div class="sa-drawer-actions">
                    <button class="cd-btn-submit" id="dwPushSendBtn" onclick="sendPushToToken(${t.id})">Enviar prueba</button>
                    <button class="cd-btn-submit sa-btn-danger" onclick="deletePushToken(${t.id})">Eliminar token</button>
                </div>
                <div id="dwPushResult" style="display:none;margin-top:8px;padding:6px 10px;border-radius:6px;font-size:12px;border:1px solid var(--cd-border)"></div>
            </div>
        </div>
    `);
}

async function sendPushToToken(tokenId) {
    const t = _pushTokens.find(x => x.id == tokenId);
    if (!t) return;
    const title = document.getElementById('dwPushTitle')?.value.trim() || 'Test';
    const body  = document.getElementById('dwPushBody')?.value.trim() || 'Prueba';
    const btn   = document.getElementById('dwPushSendBtn');
    const res   = document.getElementById('dwPushResult');

    if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
    if (res) { res.style.display = 'block'; res.style.color = 'var(--cd-text-muted)'; res.innerHTML = '⏳ Enviando a FCM…'; }

    try {
        const r = await api('push_send', { sub: 'to_token', token: t.token, title, body });
        if (r.result?.success) {
            saAlert('✅ Push enviado al dispositivo', 'success');
            if (res) { res.style.color = 'var(--cd-success)'; res.innerHTML = `✅ Enviado OK — Plataforma: ${t.platform || '?'}, HTTP ${r.result.http_code || 200}`; }
        } else {
            const err = r.result?.error || r.result?.response?.error?.message || 'Error desconocido';
            saAlert('❌ Error FCM: ' + err, 'error');
            if (res) { res.style.color = 'var(--cd-danger)'; res.innerHTML = `❌ Falló — ${err} (HTTP ${r.result?.http_code || '?'})`; }
        }
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
        if (res) { res.style.color = 'var(--cd-danger)'; res.innerHTML = '❌ ' + e.message; }
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Enviar prueba'; }
    }
}

async function deletePushToken(tokenId) {
    if (!confirm('¿Eliminar este token de push? El dispositivo dejará de recibir notificaciones.')) return;
    try {
        await api('push_delete_token', { token_id: tokenId });
        saAlert('Token eliminado', 'success');
        closeDrawer();
        _pushLoaded = false;
        loadPushData();
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    }
}

// ── Drawer helpers ───────────────────────────────────────────────────────
function openDrawer(title, bodyHtml) {
    document.getElementById('saDrawerTitle').textContent = title;
    document.getElementById('saDrawerBody').innerHTML = bodyHtml;
    document.getElementById('saDrawerOverlay').classList.add('open');
    document.getElementById('saDrawer').classList.add('open');
}
function closeDrawer() {
    document.getElementById('saDrawerOverlay').classList.remove('open');
    document.getElementById('saDrawer').classList.remove('open');
}

// ── View / Edit user (drawer) ────────────────────────────────────────────
async function openUserDrawer(id) {
    const u = _usuarios.find(x => x.id === id);
    if (!u) return;

    const rolOpts = saRoleOptions(['superadmin','admin','cuidador','medico','familiar'], u.rol);
    const estadoOpts = ['activo','inactivo']
        .map(e => `<option value="${e}" ${u.estado===e?'selected':''}>${e.charAt(0).toUpperCase()+e.slice(1)}</option>`).join('');

    openDrawer(u.nombre, `
        <div class="sa-drawer-section">
            <h4>Datos del usuario</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="dwUserNombre" value="${esc(u.nombre)}"></div>
                <div class="cd-form-group"><label class="cd-form-label">Email</label><input class="cd-input" id="dwUserEmail" value="${esc(u.email)}"></div>
                <div class="cd-form-row">
                    <div class="cd-form-group"><label class="cd-form-label">Rol global</label>
                        <select class="cd-input cd-select-native" id="dwUserRol">${rolOpts}</select>
                        <p style="font-size:0.7rem;color:var(--cd-text-muted);margin:2px 0 0">Solo aplica si no tiene rol específico en una institución</p>
                    </div>
                    <div class="cd-form-group"><label class="cd-form-label">Estado</label><select class="cd-input cd-select-native" id="dwUserEstado">${estadoOpts}</select></div>
                </div>
                <div class="sa-drawer-actions">
                    <button class="cd-btn-submit" onclick="saveUsuario(${id})">Guardar cambios</button>
                    <button class="cd-btn-submit sa-btn-danger" onclick="deleteUsuario(${id})">Eliminar usuario</button>
                </div>
            </div>
        </div>
        <div class="sa-drawer-section">
            <h4>Instituciones vinculadas</h4>
            <div id="dwInstList"><div class="sa-loading">Cargando…</div></div>
        </div>
    `);

    // Load institutions for this user
    try {
        const r = await api('usuarios', { sub: 'user_institutions', user_id: id });
        renderUserInstitutions(id, r.instituciones);
    } catch (e) {
        document.getElementById('dwInstList').innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderUserInstitutions(userId, instituciones) {
    const el = document.getElementById('dwInstList');
    if (!instituciones.length) { el.innerHTML = '<p class="sa-empty">No hay instituciones registradas.</p>'; return; }

    el.innerHTML = instituciones.map(inst => {
        const linked = inst.linked == 1;
        const instRol = inst.linked_rol || 'enfermero';
        const rolOpts = saRoleOptions(['admin','cuidador','medico','familiar'], instRol);
        return `<div class="sa-inst-toggle ${linked ? 'linked' : ''}" id="instToggle_${inst.id}">
            <div class="sa-inst-toggle-info">
                <span class="sa-inst-toggle-name">${esc(inst.nombre)}</span>
                <span class="sa-inst-toggle-status">${esc(inst.estado)}</span>
            </div>
            ${linked ? `<select class="cd-input cd-select-native sa-inst-role-select" onchange="saveInstUserRole_inline(${userId}, ${inst.id}, this.value)" style="width:auto;min-width:90px;font-size:0.75rem;padding:4px 6px">${rolOpts}</select>` : ''}
            <label class="sa-switch-label">
                <input type="checkbox" ${linked ? 'checked' : ''} onchange="toggleUserInst(${userId}, ${inst.id}, this.checked)">
                <span class="sa-switch-track"></span>
            </label>
        </div>`;
    }).join('');
}

async function toggleUserInst(userId, instId, link) {
    const card = document.getElementById('instToggle_' + instId);
    try {
        await api('usuarios', { sub: 'toggle_institution', user_id: userId, institucion_id: instId, link });
        if (card) card.classList.toggle('linked', link);
        // Re-render to show/hide role select
        const r2 = await api('usuarios', { sub: 'user_institutions', user_id: userId });
        renderUserInstitutions(userId, r2.instituciones);
        loadUsuarios();
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
        const cb = card?.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = !link;
        if (card) card.classList.toggle('linked', !link);
    }
}

async function saveInstUserRole_inline(userId, instId, rol) {
    try {
        await api('usuarios', { sub: 'update_inst_role', user_id: userId, institucion_id: instId, rol });
        saAlert('Rol actualizado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function saveUsuario(id) {
    try {
        await api('usuarios', {
            sub: 'update', id,
            nombre: document.getElementById('dwUserNombre').value,
            email: document.getElementById('dwUserEmail').value,
            rol: saRoleStorage(document.getElementById('dwUserRol').value),
            estado: document.getElementById('dwUserEstado').value
        });
        closeDrawer();
        loadUsuarios();
        loadStats();
        saAlert('Usuario actualizado', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function deleteUsuario(id, instId) {
    const u = _usuarios.find(x => x.id == id)
        || Object.values(_instUsersCache).flat().find(x => x.id == id)
        || { nombre: 'este usuario', email: '' };
    const label = `${u.nombre || 'este usuario'}${u.email ? ' (' + u.email + ')' : ''}`;
    if (!confirm(`¿Eliminar permanentemente a ${label}?\n\nSe revocarán sus accesos, instituciones vinculadas, sesiones y tokens de notificación.`)) return;
    if (!confirm('CONFIRMAR ELIMINACIÓN: esta acción no se puede deshacer. ¿Continuar?')) return;
    try {
        const r = await api('usuarios', { sub: 'delete', id });
        closeDrawer();
        _usuarios = _usuarios.filter(x => x.id != id);
        renderUsuarios();
        await Promise.all([loadStats(), loadInstituciones()]);
        if (instId) {
            const instUsers = await api('usuarios', { sub: 'inst_users', institucion_id: instId });
            _instUsersCache[instId] = instUsers.usuarios;
            renderInstUsersPanel(instId, instUsers.usuarios);
        }
        saAlert(`Usuario eliminado: ${r.nombre || label}`, 'success');
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    }
}

// ── Create user (drawer) ─────────────────────────────────────────────────
function createUsuario() {
    const instOpts = _instituciones.length
        ? _instituciones.map(i => `<option value="${i.id}">${esc(i.nombre)}</option>`).join('')
        : '';
    openDrawer('Nuevo Usuario', `
        <div class="sa-drawer-section">
            <h4>Datos del usuario</h4>
            <div class="cd-cfg-form">
                <div class="cd-form-group"><label class="cd-form-label">Nombre</label><input class="cd-input" id="dwNewUserNombre" placeholder="Nombre completo"></div>
                <div class="cd-form-group"><label class="cd-form-label">Email</label><input class="cd-input" id="dwNewUserEmail" type="email" placeholder="correo@ejemplo.com"></div>
                <div class="cd-form-group"><label class="cd-form-label">Contraseña</label><input class="cd-input" id="dwNewUserPass" type="password" placeholder="Mínimo 6 caracteres (solo si es nuevo)" autocomplete="new-password"></div>
                <div class="cd-form-row">
                    <div class="cd-form-group"><label class="cd-form-label">Rol</label>
                        <select class="cd-input cd-select-native" id="dwNewUserRol">
                            <option value="admin">Admin</option>
                            <option value="cuidador" selected>Cuidador</option>
                            <option value="medico">Médico</option>
                            <option value="familiar">Familiar</option>
                        </select>
                    </div>
                    <div class="cd-form-group"><label class="cd-form-label">Institución</label>
                        <select class="cd-input cd-select-native" id="dwNewUserInst"><option value="">Sin institución</option>${instOpts}</select>
                    </div>
                </div>
                <p style="font-size:12px;color:var(--cd-text-muted);margin:4px 0 0">Si el email ya existe, el usuario será vinculado a la institución seleccionada.</p>
            </div>
        </div>
        <div class="sa-drawer-actions">
            <button class="cd-btn-submit" onclick="saveNewUsuario()">Crear / Vincular</button>
            <button class="cd-btn-submit sa-btn-cancel" onclick="closeDrawer()">Cancelar</button>
        </div>
    `);
}

async function saveNewUsuario() {
    try {
        const r = await api('usuarios', {
            sub: 'create',
            nombre: document.getElementById('dwNewUserNombre').value,
            email: document.getElementById('dwNewUserEmail').value,
            password: document.getElementById('dwNewUserPass').value,
            rol: saRoleStorage(document.getElementById('dwNewUserRol').value),
            institucion_id: document.getElementById('dwNewUserInst').value || 0
        });
        closeDrawer();
        loadUsuarios();
        loadStats();
        saAlert(r.message || 'Usuario creado correctamente', 'success');
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════
// LOGS
// ═══════════════════════════════════════════════════════════════════════════
async function loadLogs() {
    const nivel = document.getElementById('logFilterNivel')?.value || '';
    document.getElementById('logList').innerHTML = ghostRows(4);
    try {
        const params = new URLSearchParams({action: 'logs', limit: 100, offset: _logOffset});
        if (nivel) params.set('nivel', nivel);
        const resp = await fetch(API + '?' + params.toString(), {credentials: 'same-origin'});
        if (resp.status === 401) { window.location.href = 'login.php'; return; }
        const r = await resp.json();
        if (!r.ok) throw new Error(r.error);
        _logs = r.logs;
        _logTotal = r.total;
        renderLogs();
    } catch (e) {
        document.getElementById('logList').innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function renderLogs() {
    const el = document.getElementById('logList');
    if (!_logs.length) { el.innerHTML = '<p class="sa-empty">No hay logs.</p>'; return; }

    el.innerHTML = _logs.map(l => `
        <div class="sa-log-entry sa-log-entry-${l.nivel || 'info'}">
            <div class="sa-log-meta">
                <span class="sa-log-nivel">${esc(l.nivel || 'info')}</span>
                <span class="sa-log-time">${fmtDt(l.creado_at)}</span>
                <span class="sa-log-user">${esc(l.usuario_nombre || 'Sistema')}</span>
            </div>
            <div class="sa-log-msg">${esc(l.mensaje || l.accion || '')}</div>
            ${l.detalles ? `<div class="sa-log-details">${esc(l.detalles)}</div>` : ''}
        </div>
    `).join('');

    // Pagination
    const pag = document.getElementById('logPagination');
    const pages = Math.ceil(_logTotal / 100);
    const current = Math.floor(_logOffset / 100);
    if (pages <= 1) { pag.innerHTML = ''; return; }
    let html = '';
    for (let i = 0; i < pages && i < 10; i++) {
        html += `<button class="sa-page-btn ${i === current ? 'active' : ''}" onclick="goLogPage(${i})">${i + 1}</button>`;
    }
    pag.innerHTML = html;
}

function goLogPage(page) {
    _logOffset = page * 100;
    loadLogs();
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('logFilterNivel').addEventListener('change', () => { _logOffset = 0; loadLogs(); });
    document.getElementById('btnRefreshLogs').addEventListener('click', () => loadLogs());
});

// ═══════════════════════════════════════════════════════════════════════════
// DESPLIEGUE
// ═══════════════════════════════════════════════════════════════════════════
async function loadPendingDeploy() {
    _deployLoaded = true;
    const el = document.getElementById('deployList');
    el.innerHTML = ghostRows(3);
    document.getElementById('deployActions').style.display = 'none';
    document.getElementById('deployResults').style.display = 'none';
    try {
        const r = await api('despliegue', {sub: 'list_pending'});
        const list = r.instituciones;
        if (!list.length) {
            el.innerHTML = '<p class="sa-empty">Todas las instituciones ya tienen BD propia.</p>';
            document.getElementById('btnRunDeploy').disabled = true;
            return;
        }
        el.innerHTML = list.map(i => `
            <label class="sa-db-item">
                <input type="checkbox" class="deploy-check" value="${i.id}" checked>
                <span class="sa-db-name">${esc(i.nombre)}</span>
                <span class="sa-db-badge sa-db-badge-${i.estado}">${esc(i.estado)}</span>
                <span class="sa-db-tables">→ geriapp_i${i.id}</span>
            </label>
        `).join('');
        document.getElementById('deployActions').style.display = '';
        document.getElementById('btnRunDeploy').disabled = false;
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    }
}

function toggleAllDeploy(state) {
    document.querySelectorAll('.deploy-check').forEach(cb => cb.checked = state);
}

async function runDeploy() {
    const checks = document.querySelectorAll('.deploy-check:checked');
    const ids = Array.from(checks).map(cb => parseInt(cb.value));
    if (!ids.length) return saAlert('Selecciona al menos una institución', 'error');
    if (!confirm(`¿Crear BD para ${ids.length} institución(es)?\n\nSe creará una BD independiente con el schema tenant para cada una.`)) return;

    const btn = document.getElementById('btnRunDeploy');
    btn.disabled = true;
    btn.textContent = 'Creando…';
    const resEl = document.getElementById('deployResults');

    try {
        const r = await api('despliegue', {sub: 'create_db', institucion_ids: ids});
        let html = '<div class="sa-sync-results"><h3>Resultados</h3><table class="sa-results-table"><thead><tr><th>Institución</th><th>BD</th><th>Estado</th></tr></thead><tbody>';
        for (const res of r.results) {
            if (res.ok) {
                html += `<tr><td>${esc(res.nombre)}</td><td>${esc(res.db_name)}</td><td class="sa-conn-ok">✓ Creada</td></tr>`;
            } else {
                html += `<tr><td>ID: ${res.id}</td><td>—</td><td class="sa-conn-err">✗ ${esc(res.error)}</td></tr>`;
            }
        }
        html += '</tbody></table></div>';
        resEl.innerHTML = html;
        resEl.style.display = 'block';
        loadPendingDeploy();
        loadInstituciones();
    } catch (e) {
        resEl.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
        resEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg> Crear bases de datos seleccionadas';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// AUDITORÍA BD
// ═══════════════════════════════════════════════════════════════════════════
async function runCheckDb() {
    _checkdbLoaded = true;
    const el = document.getElementById('checkdbList');
    el.innerHTML = ghostRows(5);
    document.getElementById('checkdbSummary').textContent = 'Verificando…';
    document.getElementById('checkdbDetail').innerHTML = '<p>Ejecutando auditoría…</p>';

    const profile = document.getElementById('checkdbProfile')?.value || '';

    try {
        const r = await api('check_db' + (profile ? '&profile=' + encodeURIComponent(profile) : ''), null, 'GET');
        _checkdbResults = r.tables;
        const tables = Object.entries(r.tables);
        // Count only real problems (error + warn), not info items
        let realIssues = 0;
        tables.forEach(([, d]) => { realIssues += d.issues.filter(i => i.type !== 'info').length; });
        document.getElementById('checkdbSummary').textContent =
            `${r.total_ok} OK · ${realIssues} problema(s) en ${tables.length} tablas`;

        el.innerHTML = tables.map(([name, data]) => {
            const hasErrors = data.issues.some(i => i.type === 'error');
            const hasWarns  = data.issues.some(i => i.type === 'warn');
            const isResidual = !!data.residual;
            const realCount = data.issues.filter(i => i.type !== 'info').length;
            const statusTxt = isResidual ? '🗑 Residual' : (hasErrors ? '⚠ Errores' : (hasWarns ? '⚡ Warnings' : '✓ OK'));
            const badge = isResidual ? 'sa-conn-err' : (hasErrors ? 'sa-conn-err' : (hasWarns ? 'sa-conn-testing' : 'sa-conn-ok'));
            return `
            <div class="cd-cfg-user-card" onclick="showTableDetail('${esc(name)}')" style="cursor:pointer">
                <div class="cd-cfg-user-info">
                    <strong>${esc(name)}</strong>
                    <span>${isResidual ? 'No está en esquema esperado · ' : ''}${data.ok} cols OK · ${realCount} problema(s) · ${data.rows} filas</span>
                </div>
                <span class="${badge}">${statusTxt}</span>
            </div>`;
        }).join('');

        document.getElementById('checkdbDetail').innerHTML = '<p>Selecciona una tabla para ver detalles.</p>';

        // Show/hide global fix button
        let totalFixable = 0;
        tables.forEach(([, d]) => { totalFixable += d.issues.filter(i => i.fix_sql || i.fix_action).length; });
        const btnFix = document.getElementById('btnFixAllDb');
        if (totalFixable > 0) {
            btnFix.style.display = '';
            btnFix.querySelector('span')?.remove();
            const cnt = document.createElement('span');
            cnt.textContent = ` (${totalFixable})`;
            btnFix.appendChild(cnt);
        } else {
            btnFix.style.display = 'none';
        }
    } catch (e) {
        el.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
        document.getElementById('btnFixAllDb').style.display = 'none';
    }
}

function showTableDetail(table) {
    const data = _checkdbResults[table];
    if (!data) return;
    const sidebar = document.getElementById('checkdbDetail');

    if (!data.issues.length) {
        sidebar.innerHTML = `<h5>${esc(table)}</h5><p class="sa-conn-ok">Todo correcto (${data.ok} cols OK, ${data.rows} filas)</p>`;
        return;
    }

    let html = `<h5>${esc(table)}</h5><p>${data.ok} OK · ${data.issues.filter(i=>i.type!=='info').length} problema(s) · ${data.rows} filas</p>`;
    const fixableCount = data.issues.filter(i => i.fix_sql || i.fix_action).length;
    if (fixableCount > 1) {
        html += `<button class="cd-btn-submit" style="margin-bottom:10px;font-size:12px;padding:5px 12px" onclick="fixAllDbIssues('${esc(table)}')">Corregir todos (${fixableCount})</button>`;
    }
    html += '<div class="sa-checkdb-issues">';
    data.issues.forEach((issue, idx) => {
        const color = issue.type === 'error' ? 'var(--cd-danger)' : (issue.type === 'warn' ? 'var(--cd-warning)' : 'var(--cd-text-muted)');
        html += `<div class="sa-checkdb-issue" style="border-left:3px solid ${color};padding:8px 10px;margin-bottom:8px;background:var(--cd-bg-secondary);border-radius:4px">
            <div style="font-size:13px;margin-bottom:4px">${issue.msg}</div>`;
        if (issue.fix_sql) {
            html += `<code style="font-size:11px;display:block;margin:4px 0;word-break:break-all;color:var(--cd-text-muted)">${esc(issue.fix_sql.substring(0, 200))}${issue.fix_sql.length > 200 ? '…' : ''}</code>
                <button class="cd-btn-submit cd-btn-secondary" style="padding:3px 8px;font-size:11px" onclick="fixDbIssue('${esc(table)}',${idx})">Corregir</button>`;
        } else if (issue.fix_action) {
            html += `<code style="font-size:11px;display:block;margin:4px 0;color:var(--cd-text-muted)">acción: ${esc(issue.fix_action)}</code>
                <button class="cd-btn-submit cd-btn-secondary" style="padding:3px 8px;font-size:11px" onclick="fixDbIssue('${esc(table)}',${idx})">Corregir</button>`;
        }
        html += '</div>';
    });
    html += '</div>';
    sidebar.innerHTML = html;
}

async function fixDbIssue(table, idx) {
    const issue = _checkdbResults[table]?.issues?.[idx];
    if (!issue?.fix_sql && !issue?.fix_action) return;
    const desc = issue.fix_sql ? issue.fix_sql.substring(0, 200) : `acción: ${issue.fix_action}`;
    if (!confirm(`¿Ejecutar corrección en '${table}'?\n\n${desc}`)) return;
    const profile = document.getElementById('checkdbProfile')?.value || '';
    try {
        if (issue.fix_action) {
            const r = await api('fix_db', {table, fix_action: issue.fix_action, profile});
            const n = r?.backfilled ?? 0;
            const e = r?.errors ?? 0;
            const msg = `Backfill completado: ${n} registros actualizados` + (e ? `, ${e} errores` : '');
            saAlert(msg, e ? 'warning' : 'success');
        } else {
            await api('fix_db', {table, sql: issue.fix_sql, profile});
            saAlert('Corrección aplicada', 'success');
        }
        runCheckDb();
    } catch (e) { saAlert('Error: ' + e.message, 'error'); }
}

async function fixAllDbIssues(table) {
    const data = _checkdbResults[table];
    if (!data) return;
    const fixable = data.issues.filter(i => i.fix_sql || i.fix_action);
    if (!fixable.length) return;
    const preview = fixable.map(i => i.fix_sql ? i.fix_sql.substring(0, 120) : `acción: ${i.fix_action}`).join('\n');
    if (!confirm(`¿Ejecutar ${fixable.length} correcciones en '${table}'?\n\n${preview}`)) return;
    const profile = document.getElementById('checkdbProfile')?.value || '';
    let ok = 0, errs = [];
    for (const issue of fixable) {
        try {
            if (issue.fix_action) {
                await api('fix_db', {table, fix_action: issue.fix_action, profile});
            } else {
                await api('fix_db', {table, sql: issue.fix_sql, profile});
            }
            ok++;
        } catch (e) { errs.push(e.message); }
    }
    if (errs.length) saAlert(`${ok} aplicadas, ${errs.length} errores: ${errs[0]}`, 'error');
    else saAlert(`${ok} correcciones aplicadas`, 'success');
    runCheckDb();
}

async function fixAllDbGlobal() {
    if (!_checkdbResults) return;
    const allFixes = [];
    Object.entries(_checkdbResults).forEach(([table, data]) => {
        data.issues.filter(i => i.fix_sql || i.fix_action).forEach(i => {
            if (i.fix_action) allFixes.push({ table, fix_action: i.fix_action });
            else allFixes.push({ table, sql: i.fix_sql });
        });
    });
    if (!allFixes.length) { saAlert('No hay correcciones pendientes', 'info'); return; }
    const preview = allFixes.slice(0, 8).map(f => `[${f.table}] ${f.sql ? f.sql.substring(0, 80) : 'acción: ' + f.fix_action}`).join('\n');
    const extra = allFixes.length > 8 ? `\n… y ${allFixes.length - 8} más` : '';
    if (!confirm(`¿Ejecutar ${allFixes.length} correcciones en TODA la base de datos?\n\n${preview}${extra}`)) return;

    const profile = document.getElementById('checkdbProfile')?.value || '';
    const btn = document.getElementById('btnFixAllDb');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .6s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Ejecutando…';

    let ok = 0, errs = [];
    for (const fix of allFixes) {
        try {
            if (fix.fix_action) {
                await api('fix_db', { table: fix.table, fix_action: fix.fix_action, profile });
            } else {
                await api('fix_db', { table: fix.table, sql: fix.sql, profile });
            }
            ok++;
        } catch (e) { errs.push(`[${fix.table}] ${e.message}`); }
    }

    btn.disabled = false;
    btn.innerHTML = origText;

    if (errs.length) saAlert(`${ok} aplicadas, ${errs.length} error(es):\n${errs.slice(0, 3).join('\n')}`, 'error');
    else saAlert(`${ok} correcciones aplicadas exitosamente`, 'success');
    runCheckDb();
}

async function normalizePhonesFromAudit() {
    const profile = document.getElementById('maintenanceProfile')?.value || document.getElementById('checkdbProfile')?.value || '';
    const target = profile ? `perfil "${profile}"` : 'base local';
    if (!confirm(`Normalizar teléfonos en ${target}?\n\nSe actualizarán teléfonos nacionales de 10 dígitos a formato +52 y se respetarán campos cifrados de residentes.`)) return;

    const btn = document.getElementById('btnNormalizePhones');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .6s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Normalizando…';

    try {
        const r = await api('normalize_phones', { profile });
        const dbRows = (r.databases || []).map(db => {
            const detail = (db.tables || []).map(t => `${esc(t.label)}: ${t.updated}/${t.scanned}`).join('<br>');
            const status = db.error ? `<span class="sa-conn-err">${esc(db.error)}</span>` : `<span class="sa-conn-ok">${db.updated} actualizado(s)</span>`;
            return `<tr><td>${esc(db.database)}</td><td>${status}</td><td>${detail || 'Sin columnas aplicables'}</td></tr>`;
        }).join('');
        openModal('Normalización de teléfonos', `
            <div class="sa-sync-results">
                <h3>Resumen</h3>
                <p style="font-size:13px;color:var(--cd-text-muted)">${r.total_updated || 0} valor(es) actualizado(s), ${r.total_scanned || 0} revisado(s).</p>
                <table class="sa-results-table"><thead><tr><th>BD</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>${dbRows}</tbody></table>
                <div class="cd-cfg-actions"><button class="cd-btn-submit" onclick="closeModal()">Cerrar</button></div>
            </div>
        `);
        saAlert('Normalización completada', 'success');
        if (document.getElementById('panelCheckdb')?.classList.contains('active')) runCheckDb();
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

async function scanNormalizeRoles() {
    const box = document.getElementById('maintenanceNormalizeRoles');
    const runBtn = document.getElementById('btnRunNormalizeRoles');
    box.innerHTML = '<div class="sa-loading">Analizando roles…</div>';
    if (runBtn) runBtn.disabled = true;
    try {
        const r = await api('normalize_user_roles', { dry_run: true });
        if (!r.total) {
            box.innerHTML = '<p style="font-size:12px;color:var(--cd-success)">✓ No se encontraron inconsistencias.</p>';
            return;
        }
        const rows = (r.conflicts || []).map(c =>
            `<tr><td>${esc(c.nombre)}</td><td>${esc(c.email)}</td><td>${esc(c.inst_nombre)}</td><td><code>${esc(c.rol_usuario)}</code> → <code>${esc(c.rol_pivot)}</code></td></tr>`
        ).join('');
        box.innerHTML = `<p style="font-size:12px;color:var(--cd-warning)">${r.total} conflicto(s) encontrado(s). El pivote tiene prioridad al normalizar.</p>
            <table class="sa-results-table" style="font-size:11px"><thead><tr><th>Usuario</th><th>Correo</th><th>Institución</th><th>Cambio</th></tr></thead><tbody>${rows}</tbody></table>`;
        if (runBtn) runBtn.disabled = false;
    } catch (e) {
        box.innerHTML = `<p style="font-size:12px;color:var(--cd-danger)">Error: ${esc(e.message)}</p>`;
    }
}

async function runNormalizeRoles() {
    if (!confirm('¿Normalizar roles ahora?\n\nSe actualizará usuarios.rol para que coincida con el rol del pivote por institución.\nSolo afecta usuarios con un único rol en el pivote.')) return;
    const btn = document.getElementById('btnRunNormalizeRoles');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Normalizando…';
    try {
        const r = await api('normalize_user_roles', { dry_run: false });
        saAlert(`${r.updated} rol(es) normalizado(s).`, 'success');
        await scanNormalizeRoles();
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

document.getElementById('btnScanNormalizeRoles')?.addEventListener('click', scanNormalizeRoles);
document.getElementById('btnRunNormalizeRoles')?.addEventListener('click', runNormalizeRoles);

async function loadMaintenance(silent = false) {
    const box = document.getElementById('maintenanceInvalidInvites');
    const btn = document.getElementById('btnCleanupInvalidInvites');
    const profile = document.getElementById('maintenanceProfile')?.value || '';
    if (!silent) {
        box.innerHTML = '<div class="sa-loading">Analizando invitaciones…</div>';
        if (btn) btn.disabled = true;
    }
    try {
        const r = await api('cleanup_invalid_invitations', { profile, dry_run: true });
        _maintenanceInvalidInvites = r;
        renderMaintenanceInvalidInvites(r);
    } catch (e) {
        _maintenanceInvalidInvites = null;
        box.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
        if (btn) btn.disabled = true;
    }
}

function renderMaintenanceInvalidInvites(r) {
    const box = document.getElementById('maintenanceInvalidInvites');
    const btn = document.getElementById('btnCleanupInvalidInvites');
    const rows = r.databases || [];
    const total = Number(r.total_invalid || 0);
    if (btn) btn.disabled = total <= 0;
    const detailRows = rows.map(db => {
        const status = db.error
            ? `<span class="sa-conn-err">${esc(db.error)}</span>`
            : `<span class="${Number(db.invalid || 0) > 0 ? 'sa-conn-err' : 'sa-conn-ok'}">${Number(db.invalid || 0)} inválida(s)</span>`;
        const removed = db.deleted != null ? `${Number(db.deleted || 0)} eliminada(s)` : '—';
        const byState = (db.by_estado || []).map(x => `${esc(x.estado || 'sin estado')}: ${Number(x.total || 0)}`).join('<br>') || '—';
        return `<tr><td>${esc(db.database)}</td><td>${status}</td><td>${removed}</td><td>${byState}</td></tr>`;
    }).join('');
    const sampleRows = rows.flatMap(db => (db.samples || []).map(s => ({...s, database: db.database}))).slice(0, 8);
    const samples = sampleRows.length ? `
        <h4 style="margin:12px 0 6px;font-size:12px">Muestra</h4>
        <div class="sa-maint-table-wrap"><table class="sa-results-table"><thead><tr><th>BD</th><th>ID</th><th>Institución</th><th>Rol</th><th>Estado</th><th>Creada</th></tr></thead><tbody>
            ${sampleRows.map(s => `<tr><td>${esc(s.database)}</td><td>${Number(s.id || 0)}</td><td>${Number(s.institucion_id || 0) || '—'}</td><td>${esc(saRoleLabel(s.rol || '—'))}</td><td>${esc(s.estado || '—')}</td><td>${s.creado_at ? fmtDt(s.creado_at) : '—'}</td></tr>`).join('')}
        </tbody></table></div>` : '';
    box.innerHTML = `
        <div class="sa-resource-bigline" style="margin-bottom:10px">
            <strong>${total.toLocaleString()}</strong>
            <span>invitación(es) sin correo ni teléfono</span>
        </div>
        <div class="sa-maint-table-wrap"><table class="sa-results-table"><thead><tr><th>BD</th><th>Hallazgo</th><th>Eliminadas</th><th>Por estado</th></tr></thead><tbody>${detailRows || '<tr><td colspan="4">Sin bases revisadas.</td></tr>'}</tbody></table></div>
        ${samples}
    `;
}

async function cleanupInvalidInvitations() {
    const profile = document.getElementById('maintenanceProfile')?.value || '';
    if (!_maintenanceInvalidInvites) await loadMaintenance(true);
    const total = Number(_maintenanceInvalidInvites?.total_invalid || 0);
    if (total <= 0) return saAlert('No hay invitaciones inválidas para eliminar', 'success');
    const target = profile ? `perfil "${profile}"` : 'base local';
    if (!confirm(`Eliminar ${total} invitación(es) sin correo ni teléfono en ${target}?\n\nEsta acción borra registros de la tabla invitaciones y no modifica usuarios ni residentes.`)) return;

    const btn = document.getElementById('btnCleanupInvalidInvites');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .6s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Eliminando…';
    try {
        const r = await api('cleanup_invalid_invitations', { profile, dry_run: false });
        _maintenanceInvalidInvites = r;
        renderMaintenanceInvalidInvites(r);
        saAlert(`Limpieza completada: ${Number(r.total_deleted || 0)} invitación(es) eliminada(s)`, 'success');
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = original;
        btn.disabled = Number(_maintenanceInvalidInvites?.total_invalid || 0) <= 0;
    }
}

async function loadLegacyFamilyContacts(silent = false) {
    const box = document.getElementById('maintenanceLegacyFamilyContacts');
    const btn = document.getElementById('btnConvertLegacyFamilyContacts');
    const profile = document.getElementById('maintenanceProfile')?.value || '';
    if (!box) return;
    if (!silent) {
        box.innerHTML = '<div class="sa-loading">Analizando contactos legacy…</div>';
        if (btn) btn.disabled = true;
    }
    try {
        const r = await api('convert_legacy_family_contacts', { profile, dry_run: true });
        _maintenanceLegacyFamilyContacts = r;
        renderLegacyFamilyContacts(r);
    } catch (e) {
        _maintenanceLegacyFamilyContacts = null;
        box.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
        if (btn) btn.disabled = true;
    }
}

function renderLegacyFamilyContacts(r) {
    const box = document.getElementById('maintenanceLegacyFamilyContacts');
    const btn = document.getElementById('btnConvertLegacyFamilyContacts');
    if (!box) return;
    const rows = r.databases || [];
    const total = Number(r.total_candidates || 0);
    if (btn) btn.disabled = total <= 0;
    const detailRows = rows.map(db => {
        const status = db.error
            ? `<span class="sa-conn-err">${esc(db.error)}</span>`
            : `<span class="${Number(db.candidates || 0) > 0 ? 'sa-conn-err' : 'sa-conn-ok'}">${Number(db.candidates || 0)} por convertir</span>`;
        return `<tr><td>${esc(db.database)}</td><td>${status}</td><td>${Number(db.scanned || 0)}</td><td>${Number(db.updated || 0)}</td><td>${Number(db.skipped_duplicates || 0)}</td></tr>`;
    }).join('');
    const sampleRows = rows.flatMap(db => (db.samples || []).map(s => ({...s, database: db.database}))).slice(0, 8);
    const samples = sampleRows.length ? `
        <h4 style="margin:12px 0 6px;font-size:12px">Muestra</h4>
        <div class="sa-maint-table-wrap"><table class="sa-results-table"><thead><tr><th>BD</th><th>ID</th><th>Residente</th><th>Contacto</th><th>Dato</th></tr></thead><tbody>
            ${sampleRows.map(s => `<tr><td>${esc(s.database)}</td><td>${Number(s.id || 0)}</td><td>${esc(s.residente || '—')}</td><td>${esc(s.contacto || '—')}</td><td>${esc(s.destino || '—')}</td></tr>`).join('')}
        </tbody></table></div>` : '';
    box.innerHTML = `
        <div class="sa-resource-bigline" style="margin-bottom:10px">
            <strong>${total.toLocaleString()}</strong>
            <span>residente(s) con contacto legacy pendiente de reflejar</span>
        </div>
        <div class="sa-maint-table-wrap"><table class="sa-results-table"><thead><tr><th>BD</th><th>Estado</th><th>Revisados</th><th>Convertidos</th><th>Duplicados</th></tr></thead><tbody>${detailRows || '<tr><td colspan="5">Sin bases revisadas.</td></tr>'}</tbody></table></div>
        ${samples}
    `;
}

async function convertLegacyFamilyContacts() {
    const profile = document.getElementById('maintenanceProfile')?.value || '';
    if (!_maintenanceLegacyFamilyContacts) await loadLegacyFamilyContacts(true);
    const total = Number(_maintenanceLegacyFamilyContacts?.total_candidates || 0);
    if (total <= 0) return saAlert('No hay contactos legacy pendientes', 'success');
    const target = profile ? `perfil "${profile}"` : 'base local';
    if (!confirm(`Convertir ${total} contacto(s) legacy en ${target}?\n\nSe copiarán a contactos_json para mostrarlos como familiares. Los campos contacto_* se conservan como respaldo.`)) return;

    const btn = document.getElementById('btnConvertLegacyFamilyContacts');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .6s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Convirtiendo…';
    try {
        const r = await api('convert_legacy_family_contacts', { profile, dry_run: false });
        _maintenanceLegacyFamilyContacts = r;
        renderLegacyFamilyContacts(r);
        saAlert(`Conversión completada: ${Number(r.total_updated || 0)} residente(s) actualizado(s)`, 'success');
    } catch (e) {
        saAlert('Error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = original;
        btn.disabled = Number(_maintenanceLegacyFamilyContacts?.total_candidates || 0) <= 0;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// MODAL
// ═══════════════════════════════════════════════════════════════════════════
function openModal(title, bodyHtml) {
    document.getElementById('saModalTitle').textContent = title;
    document.getElementById('saModalBody').innerHTML = bodyHtml;
    document.getElementById('saModal').classList.add('open');
}

function closeModal() {
    document.getElementById('saModal').classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('saModal').addEventListener('click', e => {
        if (e.target.classList.contains('sa-modal-overlay')) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════

async function api(action, body, method) {
    method = method || (body ? 'POST' : 'GET');
    const opts = {method, credentials: 'same-origin', headers: {}};
    if (method === 'GET') {
        const parts = String(action).split('&');
        const actionName = parts.shift() || '';
        const params = new URLSearchParams({ action: actionName });
        if (parts.length) {
            new URLSearchParams(parts.join('&')).forEach((value, key) => params.set(key, value));
        }
        const resp = await fetch(API + '?' + params.toString(), opts);
        if (resp.status === 401) { window.location.href = 'login.php'; return; }
        const data = await resp.json();
        if (!data.ok) throw new Error(data.error || 'Error desconocido');
        return data;
    } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body || {});
        const resp = await fetch(API + '?action=' + encodeURIComponent(action), opts);
        if (resp.status === 401) { window.location.href = 'login.php'; return; }
        const data = await resp.json();
        if (!data.ok) throw new Error(data.error || 'Error desconocido');
        return data;
    }
}

function esc(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

/* ── saAlert: toast notifications ── */
function _getToastContainer() {
    let c = document.getElementById('saToastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'saToastContainer';
        c.className = 'sa-toast-container';
        // Force inline styles for reliable fixed positioning (CSS cache/CDN independence)
        Object.assign(c.style, {
            position: 'fixed', top: '16px', right: '16px', zIndex: '99999',
            display: 'flex', flexDirection: 'column', gap: '10px',
            pointerEvents: 'none', maxWidth: '440px', width: 'calc(100vw - 32px)'
        });
        document.body.appendChild(c);
    }
    return c;
}
function saAlert(msg, type) {
    type = type || 'info';
    const icons = {
        success: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--cd-success)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>',
        error:   '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--cd-danger)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        info:    '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--cd-accent)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    const titles = { success: 'Éxito', error: 'Error', info: 'Aviso' };
    const durations = { success: 4000, error: 10000, info: 4000 };
    const dur = durations[type] || 4000;
    return new Promise(resolve => {
        const toast = document.createElement('div');
        toast.className = `sa-toast sa-toast-${esc(type)}`;
        toast.style.position = 'relative'; toast.style.overflow = 'hidden'; toast.style.pointerEvents = 'auto';
        toast.innerHTML = `<div class="sa-toast-icon">${icons[type] || icons.info}</div>
            <div class="sa-toast-body">
                <div class="sa-toast-title">${esc(titles[type] || 'Aviso')}</div>
                <div class="sa-toast-msg">${esc(String(msg))}</div>
            </div>
            <div class="sa-toast-progress"><div class="sa-toast-progress-bar" style="animation-duration:${dur}ms"></div></div>`;
        const dismiss = () => {
            toast.classList.add('sa-toast-out');
            setTimeout(() => { toast.remove(); resolve(); }, 250);
        };
        toast.addEventListener('click', dismiss);
        _getToastContainer().appendChild(toast);
        setTimeout(dismiss, dur);
    });
}

// Format date/datetime using the visitor browser timezone — DD-MM-YYYY HH:MM:SS
function parseAppDate(val) {
    if (!val) return null;
    if (typeof val === 'number') return new Date(val < 1000000000000 ? val * 1000 : val);
    const raw = String(val).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const parts = raw.split('-').map(Number);
        return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12, 0, 0));
    }
    const normalized = raw.replace(' ', 'T');
    const hasZone = /[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized);
    return new Date(hasZone ? normalized : `${normalized}Z`);
}
function saDateParts(d, withTime = false) {
    const opts = {
        timeZone: SA_USER_TIMEZONE,
        year: 'numeric', month: '2-digit', day: '2-digit',
        ...(withTime ? { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false } : {})
    };
    const parts = Object.fromEntries(new Intl.DateTimeFormat('es-MX', opts).formatToParts(d).map(p => [p.type, p.value]));
    return withTime
        ? `${parts.day}-${parts.month}-${parts.year} ${parts.hour}:${parts.minute}:${parts.second}`
        : `${parts.day}-${parts.month}-${parts.year}`;
}
function fmtDt(val) {
    if (!val) return '';
    const d = parseAppDate(val);
    if (!d || isNaN(d)) return String(val);
    return saDateParts(d, true);
}
function fmtDate(val) {
    if (!val) return '';
    const raw = String(val).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const [Y, M, D] = raw.split('-');
        return `${D}-${M}-${Y}`;
    }
    const d = parseAppDate(val);
    if (!d || isNaN(d)) return String(val);
    return saDateParts(d, false);
}
function fmtTime() {
    const d = new Date();
    const parts = Object.fromEntries(new Intl.DateTimeFormat('es-MX', { timeZone: SA_USER_TIMEZONE, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).formatToParts(d).map(p => [p.type, p.value]));
    return `${parts.hour}:${parts.minute}:${parts.second}`;
}
function fmtBytes(bytes) {
    const n = Number(bytes || 0);
    if (!Number.isFinite(n) || n <= 0) return '0 B';
    const units = ['B','KB','MB','GB','TB'];
    const idx = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
    const value = n / Math.pow(1024, idx);
    return `${value >= 10 || idx === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[idx]}`;
}
function formatResourceHour(hour) {
    const str = String(hour || '');
    if (str.length !== 10) return str;
    return saDateParts(parseResourceHistoryDate(str, 0, 1), true).slice(0, 13) + 'h';
}

// ── Ghost / skeleton loading ─────────────────────────────────────────────
function ghostRows(n) {
    let html = '';
    for (let i = 0; i < n; i++) {
        html += `<div class="sa-ghost-row">
            <div class="sa-ghost sa-ghost-text" style="width:35%"></div>
            <div class="sa-ghost sa-ghost-text" style="width:20%"></div>
            <div class="sa-ghost sa-ghost-badge"></div>
        </div>`;
    }
    return html;
}

// ═══════════════════════════════════════════════════════════════════════════
// PROFILE
// ═══════════════════════════════════════════════════════════════════════════
async function loadProfile() {
    _profileLoaded = true;
    try {
        const r = await api('profile');
        document.getElementById('profileName').textContent = r.name;
        document.getElementById('profileUser').textContent = r.user;
        document.getElementById('profName').value = r.name;
        document.getElementById('profUser').value = r.user;
    } catch (e) { console.error('Profile error:', e); }

    document.getElementById('btnSaveProfile').addEventListener('click', async () => {
        const msgEl = document.getElementById('profileMsg');
        msgEl.style.display = 'none';
        const btn = document.getElementById('btnSaveProfile');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Guardando…';
        try {
            const payload = {
                name: document.getElementById('profName').value.trim(),
                user: document.getElementById('profUser').value.trim(),
                current_password: document.getElementById('profCurrentPass').value,
                new_password: document.getElementById('profNewPass').value,
                confirm_password: document.getElementById('profConfirmPass').value,
            };
            const r = await api('profile', payload);
            msgEl.style.display = 'block';
            msgEl.style.color = 'var(--cd-success, #22c55e)';
            msgEl.textContent = r.message || 'Perfil actualizado';
            document.querySelector('.sa-user').textContent = payload.name;
            document.getElementById('profileName').textContent = payload.name;
            document.getElementById('profileUser').textContent = payload.user;
            // Clear password fields
            document.getElementById('profCurrentPass').value = '';
            document.getElementById('profNewPass').value = '';
            document.getElementById('profConfirmPass').value = '';
        } catch (e) {
            msgEl.style.display = 'block';
            msgEl.style.color = 'var(--cd-danger, #ef4444)';
            msgEl.textContent = e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = origText;
        }
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// §5 CIFRADO — Encryption tab
// ═══════════════════════════════════════════════════════════════════════════

const ENC_CAT_LABELS = {
    phi: 'PHI',    // Protected Health Information — Información de Salud Protegida
    pii: 'PII',    // Personally Identifiable Information — Información de Identificación Personal
    sens: 'Sensible'
};
const ENC_PHASE_LABELS = {
    none:         'Sin preparar',
    prepared:     'Preparado',
    migrated:     'Migrado',
    active:       'Activo',
    consolidated: 'Consolidado'
};
const ENC_PHASE_CLASS = {
    none:         '',
    prepared:     'sa-enc-phase-prepared',
    migrated:     'sa-enc-phase-migrated',
    active:       'sa-enc-phase-active',
    consolidated: 'sa-enc-phase-consolidated'
};

async function loadEncryptionStatus() {
    _encLoaded = true;
    const tbody = document.getElementById('encFieldsBody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px"><span class="sa-ghost sa-ghost-text" style="width:200px;height:18px;display:inline-block"></span></td></tr>';

    try {
        const r = await api('encryption_status');

        // Pre-flight checks
        _setCheck('encCheckKey',     r.key_ok);
        _setCheck('encCheckOpenssl', r.openssl_ok);

        // Read mode switch
        const sw = document.getElementById('encReadSwitch');
        if (sw) {
            sw.checked = r.read_encrypted;
            document.getElementById('encReadLabel').textContent = r.read_encrypted ? 'Cifrado' : 'Texto plano';
        }

        // Build fields table
        _encFields = r.fields;
        let html = '';
        let currentTable = '';
        for (const [key, f] of Object.entries(r.fields)) {
            const showTable = f.table !== currentTable;
            currentTable = f.table;
            const catBadge = `<span class="sa-enc-cat sa-enc-cat-${f.cat}">${ENC_CAT_LABELS[f.cat] || f.cat}</span>`;
            const phaseBadge = `<span class="sa-enc-phase ${ENC_PHASE_CLASS[f.phase]}">${ENC_PHASE_LABELS[f.phase]}</span>`;
            const pct = f.rows > 0 && f.phase !== 'none' ? Math.round((f.migrated / f.rows) * 100) : 0;
            const progressBar = f.phase === 'none' ? '—'
                : `<div class="sa-enc-mini-bar"><div class="sa-enc-mini-fill" style="width:${pct}%"></div></div><span style="font-size:11px;margin-left:4px">${f.migrated}/${f.rows}</span>`;

            html += `<tr data-field="${key}" data-phase="${f.phase}" ${f.drift ? 'data-drift="' + f.drift + '"' : ''}>
                <td><input type="checkbox" class="enc-field-cb" value="${key}"></td>
                <td>${showTable ? `<code>${f.table}</code>` : ''}</td>
                <td><code>${f.column}</code></td>
                <td>${f.label}</td>
                <td>${catBadge}</td>
                <td>
                    ${phaseBadge}
                    ${f.drift === 'enc_still_present' ? '<span title="Drift: el JSON dice consolidated pero la columna _enc sigue existiendo en la BD. Activa Modo forzar y presiona Consolidar para reparar." style="color:var(--cd-warning,#f59e0b);margin-left:4px;font-size:11px">⚠️ drift</span>' : ''}
                    ${f.drift === 'enc_missing' ? '<span title="Drift: el JSON dice que existe la columna _enc pero la BD no la tiene. Probablemente faltan tenants por preparar, o ya se consolidó sin actualizar el JSON." style="color:var(--cd-warning,#f59e0b);margin-left:4px;font-size:11px">⚠️ drift</span>' : ''}
                    <select class="enc-phase-set" data-field="${key}" onchange="encSetPhase(this)" title="Fijar manualmente la fase en el JSON (no ejecuta DDL). Usar solo para reparar drift." style="margin-left:6px;font-size:11px;padding:1px 4px;background:transparent;border:1px solid var(--cd-border);border-radius:4px;color:var(--cd-text-muted)">
                        <option value="">Fijar fase…</option>
                        <option value="none"${f.phase==='none'?' disabled':''}>none</option>
                        <option value="prepared"${f.phase==='prepared'?' disabled':''}>prepared</option>
                        <option value="migrated"${f.phase==='migrated'?' disabled':''}>migrated</option>
                        <option value="active"${f.phase==='active'?' disabled':''}>active</option>
                        <option value="consolidated"${f.phase==='consolidated'?' disabled':''}>consolidated</option>
                    </select>
                </td>
                <td style="white-space:nowrap">${progressBar}</td>
            </tr>`;
        }
        tbody.innerHTML = html || '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--cd-text-muted)">No se encontraron campos encriptables</td></tr>';

        // Enable/disable action buttons based on state
        _updateEncButtons();

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--cd-danger)">${e.message}</td></tr>`;
    }
}

function _setCheck(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    const icon = el.querySelector('.sa-enc-check-icon');
    if (icon) icon.textContent = ok ? '✅' : '❌';
    el.classList.toggle('sa-enc-check-ok', ok);
    el.classList.toggle('sa-enc-check-fail', !ok);
}

function encToggleAll(checked) {
    document.querySelectorAll('.enc-field-cb:not(:disabled)').forEach(cb => cb.checked = checked);
    _updateEncButtons();
}

function _getSelectedFields(filterPhase) {
    const force = document.getElementById('encForceMode')?.checked;
    const checked = [];
    document.querySelectorAll('.enc-field-cb:checked').forEach(cb => {
        const phase = _encFields[cb.value]?.phase;
        // En Modo forzar ignoramos el filtro de fase — superadmin sabe lo que hace.
        if (force || !filterPhase || filterPhase.includes(phase)) {
            checked.push(cb.value);
        }
    });
    return checked;
}

function _updateEncButtons() {
    const selected = [];
    document.querySelectorAll('.enc-field-cb:checked').forEach(cb => selected.push(cb.value));

    const phases = selected.map(k => _encFields[k]?.phase || 'none');
    const hasNone     = phases.some(p => p === 'none');
    const hasPrepared = phases.some(p => p === 'prepared');
    const hasMigrated = phases.some(p => p === 'migrated');
    const hasActive   = phases.some(p => p === 'active');
    const hasAny      = selected.length > 0;
    const force       = document.getElementById('encForceMode')?.checked;

    // En Modo forzar: cualquier fase habilita todos los botones (incluido Consolidar para reparar drift).
    document.getElementById('btnEncPrepare').disabled     = !(hasNone     || (force && hasAny));
    document.getElementById('btnEncMigrate').disabled      = !(hasPrepared || (force && hasAny));
    document.getElementById('btnEncActivate').disabled     = !(hasMigrated || (force && hasAny));
    document.getElementById('btnEncConsolidate').disabled  = !(hasActive   || (force && hasAny));
    document.getElementById('btnEncRollback').disabled     = !hasAny;
}

// Add checkbox listener for enabling/disabling buttons
document.addEventListener('change', e => {
    if (e.target.classList.contains('enc-field-cb') || e.target.id === 'encCheckAll') {
        _updateEncButtons();
    }
});

// ── Phase 1: Prepare ─────────────────────────────────────────────────────
async function encPrepare() {
    const fields = _getSelectedFields(['none']);
    if (!fields.length) return alert('Selecciona campos en fase "Sin preparar"');

    const fieldLabels = fields.map(k => {
        const f = _encFields[k];
        return `• ${f.table}.${f.column} — ${f.label}`;
    }).join('\n');

    _showEncConfirm(
        'Fase 1: Preparar columnas',
        `<p style="font-size:13px;margin:0 0 12px">Se agregarán columnas <code>_enc</code> (tipo TEXT) en las siguientes tablas, en <strong>master + todos los tenants</strong>:</p>
         <pre style="font-size:12px;background:var(--cd-surface);padding:10px;border-radius:6px;max-height:200px;overflow:auto;margin:0">${_esc(fieldLabels)}</pre>
         <p style="font-size:13px;margin:12px 0 0;color:var(--cd-text-muted)">Esta operación es segura y no afecta los datos existentes.</p>`,
        async () => {
            _showEncProgress('Preparando columnas…');
            try {
                const r = await api('encryption_prepare', { fields });
                let ok = 0, fail = 0;
                for (const res of r.results) {
                    if (res.ok) {
                        ok++;
                        _logEnc(`✅ ${res.field} — columna _enc creada`);
                    } else {
                        fail++;
                        _logEnc(`❌ ${res.field} — ${res.error}`, true);
                    }
                }
                _logEnc(`\nCompletado: ${ok} exitosos, ${fail} errores`);
                _setEncProgress(100);
                loadEncryptionStatus();
            } catch (e) {
                _logEnc(`❌ Error: ${e.message}`, true);
            }
        }
    );
}

// ── Phase 2: Migrate (batch) ─────────────────────────────────────────────
async function encMigrate() {
    const fields = _getSelectedFields(['prepared']);
    if (!fields.length) return alert('Selecciona campos en fase "Preparado"');

    const fieldLabels = fields.map(k => {
        const f = _encFields[k];
        return `• ${f.table}.${f.column} — ${f.rows} filas`;
    }).join('\n');

    _showEncConfirm(
        'Fase 2: Migrar datos',
        `<p style="font-size:13px;margin:0 0 12px">Se cifrarán los datos existentes de los siguientes campos usando <strong>AES-256-GCM</strong> (Galois/Counter Mode — modo Galois/Contador) con la clave de <code>conf/.env</code>:</p>
         <pre style="font-size:12px;background:var(--cd-surface);padding:10px;border-radius:6px;max-height:200px;overflow:auto;margin:0">${_esc(fieldLabels)}</pre>
         <p style="font-size:13px;margin:12px 0 0;color:var(--cd-text-muted)">Los datos originales NO se modifican. Se usa procesamiento por lotes de 500 filas.</p>`,
        async () => {
            _showEncProgress('Migrando datos…');
            const totalFields = fields.length;
            let fieldIdx = 0;

            for (const fieldKey of fields) {
                fieldIdx++;
                const f = _encFields[fieldKey];
                _logEnc(`\n📋 [${fieldIdx}/${totalFields}] ${fieldKey} (${f.rows} filas)…`);

                let complete = false;
                let batchNum = 0;
                while (!complete) {
                    batchNum++;
                    try {
                        const r = await api('encryption_migrate', {
                            field: fieldKey,
                            batch_size: 500
                        });
                        _logEnc(`  Lote ${batchNum}: ${r.processed} cifrados, ${r.remaining} restantes`);
                        complete = r.complete;

                        // Update overall progress
                        const fieldProgress = complete ? 1 : (r.remaining > 0 ? 1 - (r.remaining / f.rows) : 1);
                        const overallPct = Math.round(((fieldIdx - 1 + fieldProgress) / totalFields) * 100);
                        _setEncProgress(overallPct);
                    } catch (e) {
                        _logEnc(`  ❌ Error en lote ${batchNum}: ${e.message}`, true);
                        complete = true; // Stop on error
                    }
                }
                _logEnc(`  ✅ ${fieldKey} — migración completa`);
            }

            _logEnc(`\n✅ Migración finalizada`);
            _setEncProgress(100);
            loadEncryptionStatus();
        }
    );
}

// ── Phase 3: Activate ────────────────────────────────────────────────────
async function encActivate() {
    const fields = _getSelectedFields(['migrated']);
    if (!fields.length) return alert('Selecciona campos en fase "Migrado"');

    const fieldLabels = fields.map(k => {
        const f = _encFields[k];
        return `• ${f.table}.${f.column} — ${f.label}`;
    }).join('\n');

    _showEncConfirm(
        'Fase 3: Activar lectura cifrada',
        `<p style="font-size:13px;margin:0 0 12px"><strong>⚠️ Acción importante:</strong> la aplicación empezará a <strong>leer de las columnas cifradas</strong> para estos campos:</p>
         <pre style="font-size:12px;background:var(--cd-surface);padding:10px;border-radius:6px;max-height:200px;overflow:auto;margin:0">${_esc(fieldLabels)}</pre>
         <p style="font-size:13px;margin:12px 0 0"><strong>Dual-write:</strong> se seguirá escribiendo en ambas columnas (original + cifrada) para permitir rollback.</p>
         <p style="font-size:12px;margin:8px 0 0;color:var(--cd-warning)">Asegúrate de que la migración esté al 100% antes de activar.</p>`,
        async () => {
            _showEncProgress('Activando…');
            try {
                const force = document.getElementById('encForceMode')?.checked;
                const r = await api('encryption_activate', { fields, force });
                for (const res of r.results) {
                    if (res.ok) {
                        _logEnc(`✅ ${res.field} — lectura cifrada activa`);
                    } else {
                        _logEnc(`❌ ${res.field} — ${res.error}`, true);
                    }
                }
                _setEncProgress(100);
                loadEncryptionStatus();
            } catch (e) {
                _logEnc(`❌ Error: ${e.message}`, true);
            }
        }
    );
}

// ── Rollback ─────────────────────────────────────────────────────────────
async function encRollback() {
    const fields = [];
    document.querySelectorAll('.enc-field-cb:checked').forEach(cb => fields.push(cb.value));
    if (!fields.length) return alert('Selecciona campos para revertir');

    const fieldLabels = fields.map(k => {
        const f = _encFields[k];
        return `• ${f.table}.${f.column} — fase: ${ENC_PHASE_LABELS[f.phase]}`;
    }).join('\n');

    _showEncConfirm(
        'Rollback — Revertir cifrado',
        `<p style="font-size:13px;margin:0 0 12px;color:var(--cd-danger)"><strong>⚠️ Esta acción eliminará las columnas <code>_enc</code></strong> y toda la data cifrada en ellas:</p>
         <pre style="font-size:12px;background:var(--cd-surface);padding:10px;border-radius:6px;max-height:200px;overflow:auto;margin:0">${_esc(fieldLabels)}</pre>
         <p style="font-size:13px;margin:12px 0 0">Los datos originales (en texto plano) permanecen intactos.</p>`,
        async () => {
            _showEncProgress('Revirtiendo…');
            try {
                const r = await api('encryption_rollback', { fields, drop_columns: true });
                for (const res of r.results) {
                    if (res.ok) {
                        _logEnc(`✅ ${res.field} — revertido a texto plano`);
                    } else {
                        _logEnc(`❌ ${res.field} — ${res.error}`, true);
                    }
                }
                _setEncProgress(100);
                loadEncryptionStatus();
            } catch (e) {
                _logEnc(`❌ Error: ${e.message}`, true);
            }
        }
    );
}

// ── Set phase manualmente (reparar drift JSON ↔ BD) ───────────────────────
async function encSetPhase(selectEl) {
    const phase = selectEl.value;
    const field = selectEl.dataset.field;
    if (!phase) return;
    const f = _encFields[field];
    if (!f) return;
    const ok = confirm(
        `¿Fijar la fase del campo ${field} como "${phase}" en encryption_state.json?\n\n` +
        `Fase actual: ${f.phase}\n` +
        `Esta acción NO ejecuta DDL — solo actualiza la etiqueta en el JSON.\n` +
        `Úsala para reparar drift cuando el JSON está desincronizado con la BD real.`
    );
    if (!ok) { selectEl.value = ''; return; }
    try {
        await api('encryption_set_phase', { field, phase });
        loadEncryptionStatus();
    } catch (e) {
        alert('Error: ' + e.message);
        selectEl.value = '';
    }
}

// ── Encryption read mode toggle ──────────────────────────────────────────
async function encToggleReadMode(checked) {
    const label = document.getElementById('encReadLabel');
    const sw    = document.getElementById('encReadSwitch');
    try {
        label.textContent = checked ? 'Cambiando…' : 'Cambiando…';
        sw.disabled = true;
        await api('encryption_toggle', { read_encrypted: checked });
        label.textContent = checked ? 'Cifrado' : 'Texto plano';
    } catch (e) {
        alert('Error: ' + e.message);
        sw.checked = !checked;
        label.textContent = !checked ? 'Cifrado' : 'Texto plano';
    } finally {
        sw.disabled = false;
    }
}

// ── Phase 4: Consolidate (cleanup) ──────────────────────────────────────
async function encConsolidate() {
    const fields = _getSelectedFields(['active']);
    if (!fields.length) return alert('Selecciona campos en fase "Activo"');

    const fieldLabels = fields.map(k => {
        const f = _encFields[k];
        return `• ${f.table}.${f.column} — ${f.label}`;
    }).join('\n');

    _showEncConfirm(
        'Fase 4: Consolidar — Limpiar BD',
        `<p style="font-size:13px;margin:0 0 12px;color:var(--cd-danger)"><strong>⚠️ ACCIÓN IRREVERSIBLE.</strong> Esta operación:</p>
         <ol style="font-size:13px;margin:0 0 12px;padding-left:20px;line-height:1.7">
             <li><strong>Elimina</strong> las columnas originales (texto plano)</li>
             <li><strong>Renombra</strong> las columnas <code>_enc</code> al nombre original</li>
         </ol>
         <p style="font-size:13px;margin:0 0 12px">Campos a consolidar:</p>
         <pre style="font-size:12px;background:var(--cd-surface);padding:10px;border-radius:6px;max-height:200px;overflow:auto;margin:0">${_esc(fieldLabels)}</pre>
         <p style="font-size:13px;margin:12px 0 0;padding:10px;background:rgba(239,68,68,.1);border-radius:6px;border-left:3px solid var(--cd-danger)">
             <strong>No se puede revertir.</strong> Asegúrate de tener un respaldo de la base de datos
             y de haber verificado que todo funciona correctamente con el cifrado activo.
         </p>`,
        async () => {
            _showEncProgress('Consolidando…');
            try {
                const force = document.getElementById('encForceMode')?.checked;
                const r = await api('encryption_consolidate', { fields, force });
                for (const res of r.results) {
                    if (res.ok) {
                        _logEnc(`✅ ${res.field} — consolidado (columna original eliminada, _enc renombrada)`);
                    } else {
                        _logEnc(`❌ ${res.field} — ${res.error}`, true);
                    }
                }
                _setEncProgress(100);
                _logEnc(`\n✅ Consolidación finalizada. Las columnas ahora almacenan directamente datos cifrados.`);
                loadEncryptionStatus();
            } catch (e) {
                _logEnc(`❌ Error: ${e.message}`, true);
            }
        }
    );
    // Make confirm button red for this dangerous operation
    document.getElementById('encConfirmOk').classList.add('sa-btn-danger');
}

// ── Confirmation dialog ──────────────────────────────────────────────────
function _showEncConfirm(title, bodyHtml, onConfirm) {
    document.getElementById('encConfirmTitle').textContent = title;
    document.getElementById('encConfirmBody').innerHTML = bodyHtml;
    document.getElementById('encConfirmOverlay').style.display = 'flex';
    _encConfirmCallback = onConfirm;

    // If rollback, make confirm button red
    const btn = document.getElementById('encConfirmOk');
    btn.classList.toggle('sa-btn-danger', title.includes('Rollback'));
}
function encConfirmCancel() {
    document.getElementById('encConfirmOverlay').style.display = 'none';
    _encConfirmCallback = null;
}
function encConfirmOk() {
    document.getElementById('encConfirmOverlay').style.display = 'none';
    if (_encConfirmCallback) _encConfirmCallback();
    _encConfirmCallback = null;
}

// ── Progress helpers ─────────────────────────────────────────────────────
function _showEncProgress(title) {
    const el = document.getElementById('encProgress');
    el.style.display = 'block';
    document.getElementById('encProgressTitle').textContent = title;
    document.getElementById('encProgressLog').innerHTML = '';
    _setEncProgress(0);
}
function _setEncProgress(pct) {
    document.getElementById('encProgressBar').style.width = pct + '%';
    document.getElementById('encProgressText').textContent = pct + '%';
}
function _logEnc(msg, isError) {
    const log = document.getElementById('encProgressLog');
    const line = document.createElement('div');
    line.textContent = msg;
    if (isError) line.style.color = 'var(--cd-danger, #ef4444)';
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
}
function _esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Server info (footer) ─────────────────────────────────────────────────
async function loadServerInfo() {
    try {
        const r = await api('server_info');
        document.getElementById('footerDb').textContent   = r.db_name;
        document.getElementById('footerHost').textContent  = r.db_host;
        document.getElementById('footerMysql').textContent = r.db_version;
        document.getElementById('footerPhp').textContent   = r.php_version;
        document.getElementById('footerEnv').textContent   = r.server;
        document.getElementById('footerDot').className     = 'sa-footer-dot online';
        document.getElementById('footerStatus').textContent = 'Conectado';
    } catch (e) {
        document.getElementById('footerDot').className     = 'sa-footer-dot offline';
        document.getElementById('footerStatus').textContent = 'Error';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Demo Seeding
// ══════════════════════════════════════════════════════════════════════════
async function loadSeedStatus() {
    _seedLoaded = true;
    const toggle = document.getElementById('seedToggle');
    const label  = document.getElementById('seedStatusLabel');
    const creds  = document.getElementById('seedCredentials');
    try {
        const r = await api('seed_demo');
        if (r.active) {
            toggle.checked = true;
            label.textContent = 'Demo activa';
            label.style.color = 'var(--cd-success,#22c55e)';
            creds.style.display = '';
        } else {
            toggle.checked = false;
            label.textContent = 'Demo inactiva';
            label.style.color = 'var(--cd-muted)';
            creds.style.display = 'none';
        }
    } catch (e) {
        label.textContent = 'Error al verificar';
        label.style.color = 'var(--cd-danger,#ef4444)';
    }
}

async function toggleSeedDemo(enable) {
    const toggle = document.getElementById('seedToggle');
    const label  = document.getElementById('seedStatusLabel');
    const creds  = document.getElementById('seedCredentials');
    const log    = document.getElementById('seedLog');
    const action = enable ? 'Activar' : 'Desactivar';

    if (!confirm(`¿${action} datos de demostración? ${enable ? 'Se crearán institución, usuarios, residentes y registros demo.' : 'Se eliminarán TODOS los datos demo.'}`)) {
        toggle.checked = !enable;
        return;
    }

    label.textContent = enable ? 'Creando datos demo…' : 'Eliminando datos demo…';
    label.style.color = 'var(--cd-accent,#178391)';
    log.style.display = '';
    log.textContent = `${action} seeding…\n`;
    toggle.disabled = true;

    try {
        const r = await api('seed_demo', { sub: enable ? 'enable' : 'disable' });

        if (r.log) log.textContent += r.log;
        if (enable) {
            label.textContent = 'Demo activa';
            label.style.color = 'var(--cd-success,#22c55e)';
            creds.style.display = '';
        } else {
            label.textContent = 'Demo inactiva';
            label.style.color = 'var(--cd-muted)';
            creds.style.display = 'none';
        }
        log.textContent += `\n✅ ${action} completado.`;
    } catch (e) {
        label.textContent = 'Error';
        label.style.color = 'var(--cd-danger,#ef4444)';
        log.textContent += `\n❌ Error: ${e.message}`;
        toggle.checked = !enable;
    } finally {
        toggle.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// ONBOARDING 1-CLICK
// ═══════════════════════════════════════════════════════════════════════════
async function onbInit() {
    _onbInited = true;
    // Cargar defaults preview + planes
    try {
        const r = await api('onboarding_defaults');
        _onbDefaults = r.defaults;
    } catch (e) { saAlert('Error cargando defaults: ' + e.message, 'error'); }
    try {
        if (!_planes.length) await loadPlanes();
    } catch (e) {}
    // Poblar select de planes
    const sel = document.getElementById('onbInstPlan');
    sel.innerHTML = '<option value="">Sin plan</option>' +
        _planes.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
    // El paso "Usuarios iniciales" se eliminó: el admin se gestiona por email
    // (auto-link si existe, invitación automática si no). _onbUsers queda vacío.
    _onbUsers = [];
    onbRenderInvits();
}

function onbGoStep(step) {
    if (step > 1 && !onbValidateStep(_onbStep)) return;
    _onbStep = step;
    document.querySelectorAll('.sa-onb-step').forEach(el => {
        el.classList.toggle('active', +el.dataset.step === step);
        el.classList.toggle('done', +el.dataset.step < step);
    });
    // Animated progress line (4 steps → 0/33/66/100%)
    const pct = ((step - 1) / 3) * 100;
    const fill = document.getElementById('onbProgressFill');
    if (fill) fill.style.width = pct + '%';
    for (let i = 1; i <= 4; i++) {
        const p = document.getElementById('onbPane' + i);
        if (p) p.style.display = (i === step) ? '' : 'none';
    }
    if (step === 3) onbRenderSummary();
    // Smooth scroll to top of pane
    const stepper = document.getElementById('onbStepper');
    if (stepper) stepper.scrollIntoView({behavior:'smooth', block:'start'});
}

function onbValidateStep(step) {
    if (step === 1) {
        const nombre = document.getElementById('onbInstNombre').value.trim();
        const email  = document.getElementById('onbInstEmail').value.trim();
        if (!nombre) { saAlert('Nombre de institución requerido', 'error'); return false; }
        if (!email || !/^\S+@\S+\.\S+$/.test(email)) { saAlert('Email administrador inválido', 'error'); return false; }
    }
    if (step === 2) {
        // Paso 2 = invitaciones (opcional). Solo validar formato si hay alguna.
        onbSyncInvitsFromDOM();
        for (const i of _onbInvits) {
            if (!/^\S+@\S+\.\S+$/.test(i.email)) { saAlert(`Email inválido en invitación: ${i.email}`, 'error'); return false; }
        }
    }
    return true;
}

function onbAddUser(rol) {
    // BUG FIX: sincronizar inputs actuales del DOM al estado ANTES de re-render,
    // para no perder lo que el usuario ya había tecleado.
    onbSyncUsersFromDOM();
    _onbUsers.push({ nombre: '', email: '', rol: saRolePublic(rol || 'cuidador'), password: '' });
    onbRenderUsers();
    // Foco automático en el nuevo nombre
    requestAnimationFrame(() => {
        const rows = document.querySelectorAll('#onbUsersList .sa-onb-user-card');
        const last = rows[rows.length - 1];
        last?.querySelector('.onb-u-nombre')?.focus();
    });
}
function onbRemoveUser(idx) {
    onbSyncUsersFromDOM();
    _onbUsers.splice(idx, 1);
    onbRenderUsers();
}
function onbSyncUsersFromDOM() {
    document.querySelectorAll('#onbUsersList .sa-onb-user-card').forEach((row, i) => {
        if (!_onbUsers[i]) return;
        _onbUsers[i].nombre   = row.querySelector('.onb-u-nombre')?.value.trim() || '';
        _onbUsers[i].email    = row.querySelector('.onb-u-email')?.value.trim().toLowerCase() || '';
        _onbUsers[i].rol      = saRolePublic(row.querySelector('.onb-u-rol')?.value || _onbUsers[i].rol);
        _onbUsers[i].password = row.querySelector('.onb-u-pass')?.value || '';
    });
}
function onbRenderUsers() {
    const el = document.getElementById('onbUsersList');
    if (!el) return;
    if (!_onbUsers.length) {
        el.innerHTML = `<div class="sa-onb-empty-card">
            <div class="sa-onb-empty-icon">👥</div>
            <p><strong>Sin usuarios todavía.</strong></p>
            <p class="sa-onb-empty-sub">Agrega al menos un admin para que alguien pueda acceder.</p>
        </div>`;
        return;
    }
    const roleMeta = {
        admin:     { icon:'👤', label:'Admin' },
        medico:    { icon:'🩺', label:'Médico' },
        cuidador:  { icon:'💉', label:'Cuidador' },
        familiar:  { icon:'👨‍👩‍👧', label:'Familiar' },
    };
    el.innerHTML = _onbUsers.map((u, i) => {
        const role = saRolePublic(u.rol || 'cuidador');
        const m = roleMeta[role] || roleMeta.cuidador;
        const initial = (u.nombre || u.email || '?').trim().charAt(0).toUpperCase();
        return `
        <div class="sa-onb-user-card sa-role-card-${esc(role)}">
            <div class="sa-onb-card-head">
                <div class="sa-onb-avatar">${esc(initial)}</div>
                <div class="sa-onb-card-title">
                    <span class="sa-role-badge sa-role-badge-${esc(role)}">${m.icon} ${m.label}</span>
                    <small>Usuario #${i + 1}</small>
                </div>
                <button class="sa-onb-card-remove" onclick="onbRemoveUser(${i})" title="Quitar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-onb-card-body">
                <div class="sa-onb-field">
                    <label>Nombre completo</label>
                    <input class="cd-input onb-u-nombre" placeholder="Ej. María González" value="${esc(u.nombre)}">
                </div>
                <div class="sa-onb-field">
                    <label>Email</label>
                    <input class="cd-input onb-u-email" type="email" placeholder="email@dominio.com" value="${esc(u.email)}">
                </div>
                <div class="sa-onb-field">
                    <label>Rol</label>
                    <select class="cd-input cd-select-native onb-u-rol" onchange="onbUpdateUserRole(${i}, this.value)">
                        ${['admin','medico','cuidador','familiar'].map(r =>
                            `<option value="${r}" ${role===r?'selected':''}>${roleMeta[r].label}</option>`).join('')}
                    </select>
                </div>
                <div class="sa-onb-field">
                    <label>Contraseña <small>(vacío = autogenerada)</small></label>
                    <input class="cd-input onb-u-pass" type="text" placeholder="Mín. 8 caracteres" value="${esc(u.password)}">
                </div>
            </div>
        </div>`;
    }).join('');
}
function onbUpdateUserRole(idx, rol) {
    // Sincroniza para no perder cambios + actualiza el badge sin reemplazar inputs
    onbSyncUsersFromDOM();
    if (_onbUsers[idx]) _onbUsers[idx].rol = saRolePublic(rol);
    onbRenderUsers();
}

function onbAddInvit(rol) {
    onbSyncInvitsFromDOM();  // BUG FIX
    _onbInvits.push({ email: '', rol: saRolePublic(rol || 'cuidador'), dias: 7, mensaje: '' });
    onbRenderInvits();
    requestAnimationFrame(() => {
        const rows = document.querySelectorAll('#onbInvitsList .sa-onb-invit-card');
        const last = rows[rows.length - 1];
        last?.querySelector('.onb-i-email')?.focus();
    });
}
function onbRemoveInvit(idx) {
    onbSyncInvitsFromDOM();
    _onbInvits.splice(idx, 1);
    onbRenderInvits();
}
function onbSyncInvitsFromDOM() {
    document.querySelectorAll('#onbInvitsList .sa-onb-invit-card').forEach((row, i) => {
        if (!_onbInvits[i]) return;
        _onbInvits[i].email = row.querySelector('.onb-i-email')?.value.trim().toLowerCase() || '';
        _onbInvits[i].rol   = saRolePublic(row.querySelector('.onb-i-rol')?.value || _onbInvits[i].rol);
        _onbInvits[i].dias  = +(row.querySelector('.onb-i-dias')?.value) || 7;
    });
}
function onbRenderInvits() {
    const el = document.getElementById('onbInvitsList');
    if (!el) return;
    if (!_onbInvits.length) {
        el.innerHTML = `<div class="sa-onb-empty-card">
            <div class="sa-onb-empty-icon">✉️</div>
            <p><strong>Sin invitaciones (opcional).</strong></p>
            <p class="sa-onb-empty-sub">Útil para que el personal se registre por sí mismo con una URL única.</p>
        </div>`;
        return;
    }
    const roleMeta = {
        admin:     { icon:'👤', label:'Admin' },
        medico:    { icon:'🩺', label:'Médico' },
        cuidador:  { icon:'💉', label:'Cuidador' },
        familiar:  { icon:'👨‍👩‍👧', label:'Familiar' },
    };
    el.innerHTML = _onbInvits.map((inv, i) => {
        const role = saRolePublic(inv.rol || 'cuidador');
        const m = roleMeta[role] || roleMeta.cuidador;
        return `
        <div class="sa-onb-invit-card sa-role-card-${esc(role)}">
            <div class="sa-onb-card-head">
                <div class="sa-onb-avatar sa-onb-avatar-mail">✉</div>
                <div class="sa-onb-card-title">
                    <span class="sa-role-badge sa-role-badge-${esc(role)}">${m.icon} ${m.label}</span>
                    <small>Invitación #${i + 1}</small>
                </div>
                <button class="sa-onb-card-remove" onclick="onbRemoveInvit(${i})" title="Quitar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-onb-card-body">
                <div class="sa-onb-field" style="grid-column:1/-1">
                    <label>Email a invitar</label>
                    <input class="cd-input onb-i-email" type="email" placeholder="email@dominio.com" value="${esc(inv.email)}">
                </div>
                <div class="sa-onb-field">
                    <label>Rol</label>
                    <select class="cd-input cd-select-native onb-i-rol" onchange="onbUpdateInvitRole(${i}, this.value)">
                        ${['admin','medico','cuidador','familiar'].map(r =>
                            `<option value="${r}" ${role===r?'selected':''}>${roleMeta[r].label}</option>`).join('')}
                    </select>
                </div>
                <div class="sa-onb-field">
                    <label>Validez (días)</label>
                    <input class="cd-input onb-i-dias" type="number" min="1" max="60" value="${inv.dias||7}">
                </div>
            </div>
        </div>`;
    }).join('');
}
function onbUpdateInvitRole(idx, rol) {
    onbSyncInvitsFromDOM();
    if (_onbInvits[idx]) _onbInvits[idx].rol = saRolePublic(rol);
    onbRenderInvits();
}

function onbRenderSummary() {
    onbSyncInvitsFromDOM();
    const inst = {
        nombre:    document.getElementById('onbInstNombre').value.trim(),
        email:     document.getElementById('onbInstEmail').value.trim(),
        telefono:  document.getElementById('onbInstTelefono').value.trim(),
        direccion: document.getElementById('onbInstDireccion').value.trim(),
        timezone:  document.getElementById('onbInstTimezone').value,
        estado:    document.getElementById('onbInstEstado').value,
        plan:      document.getElementById('onbInstPlan').selectedOptions[0]?.text || '—',
        maxRes:    document.getElementById('onbInstMaxRes').value || 'Ilimitado',
        maxUsr:    document.getElementById('onbInstMaxUsr').value || 'Ilimitado',
        createDb:  document.getElementById('onbCreateDb').checked,
    };

    // KPI tiles arriba (visión rápida)
    const tiles = [
        ['🏥', '1', 'Institución', 'sa-kpi-info'],
        ['👤', '1', 'Admin (auto)', 'sa-kpi-ok'],
        ['✉️', String(_onbInvits.length), 'Invitación(es)', 'sa-kpi-info'],
        ['💾', inst.createDb ? 'Tenant' : 'Master', 'Base de datos', inst.createDb ? 'sa-kpi-ok' : 'sa-kpi-warn'],
    ];
    const tilesEl = document.getElementById('onbTiles');
    if (tilesEl) {
        tilesEl.innerHTML = tiles.map(([icon, val, lbl, cls]) => `
            <div class="sa-onb-kpi-tile ${cls}">
                <div class="sa-onb-kpi-icon">${icon}</div>
                <div class="sa-onb-kpi-val">${esc(val)}</div>
                <div class="sa-onb-kpi-lbl">${esc(lbl)}</div>
            </div>`).join('');
    }

    const sumEl = document.getElementById('onbSummary');
    sumEl.innerHTML = `
        <div class="sa-onb-sum-card">
            <h4>🏥 Institución</h4>
            <table class="sa-onb-sum-tbl">
                <tr><td>Nombre</td><td><strong>${esc(inst.nombre)}</strong></td></tr>
                <tr><td>Email admin</td><td>${esc(inst.email)}</td></tr>
                <tr><td>Teléfono</td><td>${esc(inst.telefono || '—')}</td></tr>
                <tr><td>Dirección</td><td>${esc(inst.direccion || '—')}</td></tr>
                <tr><td>Zona horaria</td><td>${esc(inst.timezone)}</td></tr>
                <tr><td>Estado</td><td><span class="cd-cfg-user-status ${inst.estado==='activa'?'active':''}">${esc(inst.estado)}</span></td></tr>
                <tr><td>Plan</td><td>${esc(inst.plan)}</td></tr>
                <tr><td>Máx. residentes</td><td>${esc(inst.maxRes)}</td></tr>
                <tr><td>Máx. usuarios</td><td>${esc(inst.maxUsr)}</td></tr>
                <tr><td>BD tenant</td><td>${inst.createDb ? '✓ Crear nueva' : '— Compartida (master)'}</td></tr>
            </table>
        </div>
        <div class="sa-onb-sum-card">
            <h4>👤 Administrador inicial</h4>
            <p style="margin:0;color:var(--cd-text-muted);font-size:13px">
                Se enviará automáticamente un correo${inst.telefono ? ' y WhatsApp' : ''} a
                <strong>${esc(inst.email)}</strong> con el link para
                ingresar (si ya tiene cuenta) o registrarse y elegir contraseña (si es su primera institución).
            </p>
        </div>
        <div class="sa-onb-sum-card">
            <h4>✉️ Invitaciones (${_onbInvits.length})</h4>
            ${_onbInvits.length ? `<table class="sa-onb-sum-tbl">
                <thead><tr><th>Email</th><th>Rol</th><th>Días</th></tr></thead>
                <tbody>${_onbInvits.map(i => `
                    <tr><td>${esc(i.email)}</td>
                        <td><span class="sa-role-badge sa-role-badge-${esc(saRolePublic(i.rol))}">${esc(saRoleLabel(i.rol))}</span></td>
                        <td>${i.dias}</td></tr>
                `).join('')}</tbody></table>` : '<p class="sa-empty">— Ninguna (opcional) —</p>'}
        </div>`;

    // Defaults preview
    const dpEl = document.getElementById('onbDefaultsPreview');
    if (_onbDefaults && dpEl) {
        const sections = [];
        for (const [secKey, secVal] of Object.entries(_onbDefaults)) {
            if (secKey === 'roles_permisos') {
                sections.push(`<details><summary><strong>${esc(secKey)}</strong> — ${Object.keys(secVal).length} rol(es)</summary>
                    <pre>${esc(JSON.stringify(secVal, null, 2))}</pre></details>`);
            } else if (typeof secVal === 'object' && secVal !== null) {
                const rows = Object.entries(secVal).map(([k, v]) => {
                    let display = v;
                    if (typeof v === 'object') display = JSON.stringify(v);
                    if (k === 'contenido_bytes') display = (v / 1024).toFixed(1) + ' KB';
                    return `<tr><td>${esc(k)}</td><td>${esc(String(display ?? '—'))}</td></tr>`;
                }).join('');
                sections.push(`<details open><summary><strong>${esc(secKey)}</strong></summary>
                    <table class="sa-onb-sum-tbl">${rows}</table></details>`);
            }
        }
        dpEl.innerHTML = sections.join('');
    }
}

// ── Progress overlay helpers ─────────────────────────────────────────
function _onbProgressShow(steps) {
    const ov = document.getElementById('onbProgressOverlay');
    const list = document.getElementById('onbProgressList');
    const bar = document.getElementById('onbProgressBarFill');
    if (!ov) return;
    list.innerHTML = steps.map((s, i) => `
        <li class="sa-onb-progress-step" data-i="${i}">
            <span class="sa-onb-progress-tick">
                <svg class="sa-onb-tick-pending" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="9"/></svg>
                <svg class="sa-onb-tick-active" width="14" height="14" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/></svg>
                <svg class="sa-onb-tick-done" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <span>${esc(s)}</span>
        </li>`).join('');
    bar.style.width = '0%';
    bar.style.transition = 'none';
    requestAnimationFrame(() => { bar.style.transition = ''; });
    ov.hidden = false;
    requestAnimationFrame(() => ov.classList.add('open'));
}
function _onbProgressAdvance(idx, total) {
    const list = document.getElementById('onbProgressList');
    const bar = document.getElementById('onbProgressBarFill');
    if (!list) return;
    list.querySelectorAll('.sa-onb-progress-step').forEach(li => {
        const i = +li.dataset.i;
        li.classList.toggle('done', i < idx);
        li.classList.toggle('active', i === idx);
    });
    if (bar) bar.style.width = Math.min(100, Math.round((idx / total) * 100)) + '%';
}
function _onbProgressFinish(success, msg) {
    const list = document.getElementById('onbProgressList');
    const bar = document.getElementById('onbProgressBarFill');
    const title = document.getElementById('onbProgressTitle');
    if (list) list.querySelectorAll('.sa-onb-progress-step').forEach(li => {
        li.classList.remove('active');
        li.classList.add('done');
    });
    if (bar) bar.style.width = '100%';
    if (title) title.textContent = msg || (success ? '¡Listo!' : 'Algo falló');
}
function _onbProgressHide() {
    const ov = document.getElementById('onbProgressOverlay');
    if (!ov) return;
    ov.classList.remove('open');
    setTimeout(() => { ov.hidden = true; }, 300);
}

async function onbExecute() {
    const btn = document.getElementById('btnOnbExecute');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Desplegando…';

    const steps = [
        'Validando datos…',
        'Creando institución…',
        'Sembrando configuración predeterminada…',
        'Generando documentos legales (T&C, Privacidad)…',
        document.getElementById('onbCreateDb').checked ? 'Creando base de datos tenant…' : 'Vinculando con BD master…',
        'Vinculando o invitando administrador…',
        'Generando invitaciones…',
        'Enviando notificaciones (email / WhatsApp)…',
        'Finalizando…',
    ];
    _onbProgressShow(steps);

    // Animación de progreso simulada (avanza paso a paso mientras la API trabaja).
    // El backend es una sola llamada; esto da feedback visual constante.
    let curStep = 0;
    _onbProgressAdvance(curStep, steps.length);
    const tick = setInterval(() => {
        if (curStep < steps.length - 1) {
            curStep++;
            _onbProgressAdvance(curStep, steps.length);
        }
    }, 700);

    const payload = {
        nombre:         document.getElementById('onbInstNombre').value.trim(),
        email_admin:    document.getElementById('onbInstEmail').value.trim(),
        telefono:       document.getElementById('onbInstTelefono').value.trim(),
        direccion:      document.getElementById('onbInstDireccion').value.trim(),
        timezone:       document.getElementById('onbInstTimezone').value,
        estado:         document.getElementById('onbInstEstado').value,
        plan_id:        document.getElementById('onbInstPlan').value || null,
        max_residentes: document.getElementById('onbInstMaxRes').value || null,
        max_usuarios:   document.getElementById('onbInstMaxUsr').value || null,
        create_db:      document.getElementById('onbCreateDb').checked,
        usuarios:       [],
        invitaciones:   _onbInvits,
        overrides:      {},
    };

    try {
        const r = await api('onboarding_create', payload);
        clearInterval(tick);
        _onbProgressFinish(true, '¡Despliegue completado!');
        await new Promise(res => setTimeout(res, 600));
        _onbProgressHide();
        onbRenderResult(r);
        onbGoStep(4);
        loadStats();
        loadInstituciones();
        _ownerLoaded = false;  // forzar refresh al abrir owner panel
        saAlert('Institución desplegada', 'success');
    } catch (e) {
        clearInterval(tick);
        _onbProgressFinish(false, 'Error en el despliegue');
        await new Promise(res => setTimeout(res, 400));
        _onbProgressHide();
        saAlert('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function onbRenderResult(r) {
    const el = document.getElementById('onbResult');
    const res = r.result || {};
    const errs = res.errors || [];
    const ok = !errs.length;
    const inviteUrl = (token) => `${BASE}/register.php?token=${encodeURIComponent(token)}`;
    const allCreds = (res.usuarios || []).filter(u => u.temp_password)
        .map(u => `${u.email}\t${u.temp_password}`).join('\n');
    const allInviteUrls = (res.invitaciones || []).map(i => `${i.email}\t${inviteUrl(i.token)}`).join('\n');
    const adm = res.admin || null;
    const notif = res.notificaciones || {email:[], whatsapp:[]};
    const adminLink = adm && adm.token ? inviteUrl(adm.token) : null;

    el.innerHTML = `
        <div class="sa-onb-result-card ${ok ? 'success' : 'has-errors'}">
            <div class="sa-onb-result-hero">
                <div class="sa-onb-success-badge ${ok ? 'ok' : 'warn'}">
                    ${ok ? `
                    <svg class="sa-onb-success-svg" viewBox="0 0 52 52">
                        <circle class="sa-onb-success-circle" cx="26" cy="26" r="24" fill="none"/>
                        <path class="sa-onb-success-check" fill="none" d="M14 27 L23 36 L40 18"/>
                    </svg>` : '⚠️'}
                </div>
                <h3>${ok ? '¡Despliegue exitoso!' : 'Despliegue completado con avisos'}</h3>
                <p class="sa-onb-result-sub">
                    ID institución: <strong>${res.institucion_id ?? '—'}</strong>
                    ${res.db_name ? ` · BD: <code>${esc(res.db_name)}</code>` : ' · BD compartida (master)'}
                </p>
            </div>

            ${adm ? `
                <div class="sa-onb-result-section">
                    <h4>👤 Administrador (${adm.mode === 'invited' ? 'invitación enviada' : 'cuenta vinculada'})</h4>
                    <table class="sa-onb-sum-tbl">
                        <tr><td>Email</td><td><strong>${esc(adm.email || '')}</strong></td></tr>
                        ${adm.mode === 'invited' && adminLink ? `
                            <tr><td>Link registro</td><td><code class="sa-onb-url">${esc(adminLink)}</code>
                                <button class="sa-link-btn" onclick="navigator.clipboard.writeText('${esc(adminLink)}').then(()=>saAlert('URL copiada','success'))">Copiar</button>
                            </td></tr>` : `
                            <tr><td>Estado</td><td><em>Usuario existente vinculado como admin</em></td></tr>`}
                    </table>
                </div>
            ` : ''}

            ${(notif.email && notif.email.length) || (notif.whatsapp && notif.whatsapp.length) ? `
                <div class="sa-onb-result-section">
                    <h4>📨 Notificaciones enviadas</h4>
                    <table class="sa-onb-sum-tbl">
                        <thead><tr><th>Canal</th><th>Destino</th><th>Estado</th></tr></thead>
                        <tbody>
                            ${(notif.email || []).map(n => `<tr>
                                <td>✉️ Email${n.role ? ` (${esc(saRoleLabel(n.role))})` : ''}</td>
                                <td>${esc(n.to)}</td>
                                <td>${n.ok ? '<span style="color:#0a0">✓ enviado</span>' : '<span style="color:#a00">✗ ' + esc(n.error || 'fallo') + '</span>'}</td>
                            </tr>`).join('')}
                            ${(notif.whatsapp || []).map(n => `<tr>
                                <td>📱 WhatsApp</td>
                                <td>${esc(n.to)}</td>
                                <td>${n.ok ? '<span style="color:#0a0">✓ enviado</span>' : '<span style="color:#a00">✗ ' + esc(n.error || 'fallo') + '</span>'}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            ` : ''}

            ${(res.usuarios && res.usuarios.length) ? `
                <div class="sa-onb-result-section">
                    <div class="sa-onb-result-sec-head">
                        <h4>Usuarios creados (${res.usuarios.length})</h4>
                        ${allCreds ? `<button class="sa-link-btn" onclick="navigator.clipboard.writeText(\`${esc(allCreds)}\`).then(()=>saAlert('Credenciales copiadas','success'))">📋 Copiar todas las credenciales</button>` : ''}
                    </div>
                    <table class="sa-onb-sum-tbl">
                        <thead><tr><th>ID</th><th>Email</th><th>Rol</th><th>Contraseña temporal</th></tr></thead>
                        <tbody>${res.usuarios.map(u => `
                            <tr>
                                <td>${u.id}</td>
                                <td>${esc(u.email)}</td>
                                <td><span class="sa-role-badge sa-role-badge-${esc(saRolePublic(u.rol))}">${esc(saRoleLabel(u.rol))}</span></td>
                                <td>${u.temp_password ? `<code class="sa-onb-cred">${esc(u.temp_password)}</code>` : (u.linked ? '<em>(usuario existente vinculado)</em>' : '— definida —')}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                    <p class="sa-onb-result-warn">⚠️ Copia las contraseñas temporales <strong>ahora</strong>; no se volverán a mostrar. Los usuarios deberán cambiarlas en el primer acceso.</p>
                </div>
            ` : ''}

            ${(res.invitaciones && res.invitaciones.length) ? `
                <div class="sa-onb-result-section">
                    <div class="sa-onb-result-sec-head">
                        <h4>Invitaciones generadas (${res.invitaciones.length})</h4>
                        ${allInviteUrls ? `<button class="sa-link-btn" onclick="navigator.clipboard.writeText(\`${esc(allInviteUrls)}\`).then(()=>saAlert('URLs copiadas','success'))">📋 Copiar todas las URLs</button>` : ''}
                    </div>
                    <table class="sa-onb-sum-tbl">
                        <thead><tr><th>Email</th><th>Rol</th><th>URL de registro</th></tr></thead>
                        <tbody>${res.invitaciones.map(i => `
                            <tr>
                                <td>${esc(i.email)}</td>
                                <td><span class="sa-role-badge sa-role-badge-${esc(saRolePublic(i.rol))}">${esc(saRoleLabel(i.rol))}</span></td>
                                <td><code class="sa-onb-url">${esc(inviteUrl(i.token))}</code>
                                    <button class="sa-link-btn" onclick="navigator.clipboard.writeText('${esc(inviteUrl(i.token))}').then(() => saAlert('URL copiada','success'))">Copiar</button>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            ` : ''}

            ${errs.length ? `
                <div class="sa-onb-result-section">
                    <h4>⚠️ Avisos</h4>
                    <ul class="sa-onb-result-warns">${errs.map(e => `<li>${esc(e)}</li>`).join('')}</ul>
                </div>
            ` : ''}

            ${(r.log && r.log.length) ? `
                <details class="sa-onb-result-log"><summary>▸ Log detallado (${r.log.length} líneas)</summary>
                    <pre>${esc(r.log.join('\n'))}</pre>
                </details>` : ''}
        </div>`;
}

function onbReset() {
    _onbUsers = [];
    _onbInvits = [];
    document.getElementById('onbInstNombre').value = '';
    document.getElementById('onbInstEmail').value = '';
    document.getElementById('onbInstTelefono').value = '';
    document.getElementById('onbInstDireccion').value = '';
    document.getElementById('onbInstMaxRes').value = '';
    document.getElementById('onbInstMaxUsr').value = '';
    onbRenderInvits();
    onbGoStep(1);
}

// ═══════════════════════════════════════════════════════════════════════════
// OWNER DASHBOARD
// ═══════════════════════════════════════════════════════════════════════════
async function loadOwnerDashboard() {
    _ownerLoaded = true;
    const kpiEl = document.getElementById('ownerKpis');
    // Skeleton con shimmer
    kpiEl.innerHTML = Array.from({length:12}, () =>
        `<div class="sa-stat-card sa-stat-loading">
            <div class="sa-stat-icon-skel sa-ghost"></div>
            <div class="sa-stat-value"><span class="sa-ghost sa-ghost-text" style="width:50px;height:24px;display:inline-block"></span></div>
            <div class="sa-stat-label"><span class="sa-ghost sa-ghost-text" style="width:80px;height:11px;display:inline-block"></span></div>
        </div>`).join('');
    // Marcar el botón "Actualizar" como spinning
    const refreshBtn = document.querySelector('#panelOwner .sa-section-header .cd-btn-secondary');
    refreshBtn?.classList.add('sa-refreshing');

    try {
        const r = await api('owner_dashboard');
        renderOwnerKpis(r.kpis);
        renderOwnerInst(r.instituciones);
        renderOwnerInvits(r.invitaciones);
        renderOwnerUsers(r.usuarios);
        renderOwnerPlanDist(r.plan_dist);
        renderOwnerTimeline(r.timeline);
    } catch (e) {
        kpiEl.innerHTML = `<div class="sa-conn-err">Error: ${esc(e.message)}</div>`;
    } finally {
        refreshBtn?.classList.remove('sa-refreshing');
    }
}

function renderOwnerKpis(k) {
    // [icon SVG, label, value, css class]
    const ico = (path) => `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
    const cards = [
        [ico('<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h6"/>'),  'Instituciones',     k.total_inst,        ''],
        [ico('<polyline points="20 6 9 17 4 12"/>'),                                                                              'Activas',           k.inst_activas,      'sa-kpi-ok'],
        [ico('<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'),                                                          'Trial',             k.inst_trial,        'sa-kpi-info'],
        [ico('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'), 'Suspendidas',       k.inst_suspendidas,  'sa-kpi-warn'],
        [ico('<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>'),     'Con BD propia',     k.inst_con_db,       ''],
        [ico('<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/>'),                                      'Sin BD (master)',   k.inst_sin_db,       'sa-kpi-warn'],
        [ico('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'), 'Usuarios totales', k.total_usuarios, ''],
        [ico('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),                          'Activos',           k.usuarios_activos,  'sa-kpi-ok'],
        [ico('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'), 'Invits pend.', k.invit_pendientes, 'sa-kpi-info'],
        [ico('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),                                            'Invits expiradas',  k.invit_expiradas,   'sa-kpi-warn'],
        [ico('<path d="M3 3v18h18"/><polyline points="7 14 11 10 15 14 21 8"/>'),                                                 'Logins 24h',        k.logins_24h ?? '—', ''],
        [ico('<path d="M3 3v18h18"/><polyline points="7 14 11 10 15 14 21 8"/>'),                                                 'Logins 7d',         k.logins_7d  ?? '—', ''],
        [ico('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'),                                     'Nuevas inst. 30d',  k.nuevas_inst_30d ?? '—', 'sa-kpi-ok'],
        [ico('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'),                                     'Nuevos usr. 30d',   k.nuevos_usr_30d  ?? '—', 'sa-kpi-ok'],
    ];
    document.getElementById('ownerKpis').innerHTML = cards.map(([icon, label, val, cls]) => `
        <div class="sa-stat-card sa-stat-card-icon ${cls}">
            <div class="sa-stat-icon">${icon}</div>
            <div class="sa-stat-value">${val ?? '—'}</div>
            <div class="sa-stat-label">${esc(label)}</div>
        </div>`).join('');
}

function renderOwnerInst(rows) {
    const tb = document.querySelector('#ownerInstTable tbody');
    if (!rows.length) { tb.innerHTML = '<tr><td colspan="10" class="sa-empty">Sin instituciones.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `
        <tr>
            <td>${r.id}</td>
            <td><strong>${esc(r.nombre)}</strong><br><small>${esc(r.email_admin || '—')}</small></td>
            <td><span class="cd-cfg-user-status ${r.estado==='activa'?'active':''} ${r.estado==='archivada'?'archived':''}">${esc(r.estado)}</span></td>
            <td>${esc(r.plan_nombre || '—')}</td>
            <td>${r.num_usuarios} ${r.num_admins ? `<small>(${r.num_admins} adm)</small>` : ''}</td>
            <td>${r.invit_pendientes > 0 ? `<strong style="color:var(--cd-warning,#f59e0b)">${r.invit_pendientes}</strong>` : '0'}</td>
            <td>${r.db_name ? `<code>${esc(r.db_name)}</code>` : '<em>master</em>'}</td>
            <td>${esc(r.creado_por || '—')}</td>
            <td>${r.creado_at ? fmtDt(r.creado_at) : '—'}</td>
            <td>${r.ultimo_acceso ? fmtDt(r.ultimo_acceso) : '<em style="color:var(--cd-text-muted)">sin actividad</em>'}</td>
        </tr>`).join('');
}

function renderOwnerInvits(rows) {
    const tb = document.querySelector('#ownerInvitTable tbody');
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="6" class="sa-empty">Sin invitaciones pendientes.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `
        <tr>
            <td>${esc(r.email)}</td>
            <td>${esc(saRoleLabel(r.rol))}</td>
            <td>${esc(r.institucion_nombre || '—')}</td>
            <td>${r.dias_restantes != null ? `${r.dias_restantes}d` : '—'}</td>
            <td>${r.creado_at ? fmtDt(r.creado_at) : '—'}</td>
            <td>${esc(r.creado_por_nombre || '—')}</td>
        </tr>`).join('');
}

function renderOwnerUsers(rows) {
    const tb = document.querySelector('#ownerUsersTable tbody');
    if (!rows.length) { tb.innerHTML = '<tr><td colspan="7" class="sa-empty">Sin usuarios.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `
        <tr>
            <td>${esc(r.nombre)}</td>
            <td>${esc(r.email)}</td>
            <td>${esc(saRoleLabel(r.rol))}</td>
            <td><span class="cd-cfg-user-status ${r.estado==='activo'?'active':''}">${esc(r.estado)}</span></td>
            <td>${esc(r.institucion_nombre || '—')}</td>
            <td>${r.creado_at ? fmtDt(r.creado_at) : '—'}</td>
            <td>${r.ultimo_acceso ? fmtDt(r.ultimo_acceso) : '<em>nunca</em>'}</td>
        </tr>`).join('');
}

function renderOwnerPlanDist(rows) {
    const el = document.getElementById('ownerPlanDist');
    if (!rows || !rows.length) { el.innerHTML = '<p class="sa-empty">—</p>'; return; }
    const max = Math.max(...rows.map(r => +r.n));
    el.innerHTML = rows.map(r => `
        <div class="sa-onb-bar-row">
            <span class="sa-onb-bar-label">${esc(r.plan)}</span>
            <div class="sa-onb-bar-track"><div class="sa-onb-bar-fill" style="width:${max?(r.n/max*100):0}%"></div></div>
            <span class="sa-onb-bar-val">${r.n}</span>
        </div>`).join('');
}

function renderOwnerTimeline(rows) {
    const el = document.getElementById('ownerTimeline');
    if (!rows || !rows.length) { el.innerHTML = '<p class="sa-empty">Sin altas en los últimos 12 meses.</p>'; return; }
    const max = Math.max(...rows.map(r => +r.n));
    el.innerHTML = rows.map(r => `
        <div class="sa-onb-bar-row">
            <span class="sa-onb-bar-label">${esc(r.mes)}</span>
            <div class="sa-onb-bar-track"><div class="sa-onb-bar-fill" style="width:${max?(r.n/max*100):0}%"></div></div>
            <span class="sa-onb-bar-val">${r.n}</span>
        </div>`).join('');
}

</script>

</body>
</html>
