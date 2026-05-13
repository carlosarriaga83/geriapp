<?php
/**
 * GeriApp — Cuidados  (responsive care tracking SPA)
 * v2 — Desktop+mobile responsive, shift notes, photo uploads,
 *       enhanced forms, PDF export, inventory CRUD, resident info.
 */
require_once __DIR__ . '/conf/config.php';
$requiredRoles = ['admin', 'enfermero', 'medico', 'familiar', 'superadmin'];
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/db/models.php';

// Cache-bust: todo el JS es inline (readfile/include), así que el cache
// ocurre a nivel de la página.  ETag basado en APP_VERSION + mtime más
// reciente de JS/CSS/PHP incluidos; fuerza re-validación en cada navegación.
$_mtimes = array_merge(
    [filemtime(__FILE__)],
    array_map('filemtime', glob(__DIR__ . '/assets/js/cd-*.js')),
    array_map('filemtime', glob(__DIR__ . '/assets/js/cd-*.js.php')),
    array_map('filemtime', glob(__DIR__ . '/includes/views/view-*.php')),
    [filemtime(__DIR__ . '/assets/css/cuidados.css')],
    [filemtime(__DIR__ . '/api/configuracion.php')]
);
$_pageEtag = '"' . md5(APP_VERSION . max($_mtimes)) . '"';
header('ETag: ' . $_pageEtag);
header('Cache-Control: no-cache, must-revalidate');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $_pageEtag) {
    http_response_code(304);
    exit;
}

$instId   = (int) $_SESSION['user_institucion_id'];
$userId   = (int) $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario');
$userRole = $_SESSION['user_rol'] ?? 'enfermero';
$userInitials = implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(explode(' ', trim($_SESSION['user_nombre'] ?? 'U')), 0, 2)));
$saImpersonating = !empty($_SESSION['sa_impersonating']);
$saImpInstNombre = htmlspecialchars($_SESSION['sa_imp_inst_nombre'] ?? '');
$saFamiliarAllResidents = $saImpersonating && $userRole === 'familiar';

// Datos completos del usuario actual (para la vista de Perfil).
// `usuarios` vive en la BD master.
$userProfile = ['nombre' => $_SESSION['user_nombre'] ?? '', 'email' => $_SESSION['user_email'] ?? '', 'telefono' => '', 'avatar_path' => ''];
try {
    $_mdb = Database::getMaster();
    $_st  = $_mdb->prepare("SELECT nombre, email, telefono, avatar_path FROM usuarios WHERE id = ? LIMIT 1");
    $_st->execute([$userId]);
    if ($_row = $_st->fetch(PDO::FETCH_ASSOC)) {
        $userProfile = array_merge($userProfile, $_row);
    }
} catch (\Throwable $e) { /* mantener defaults de sesión */ }

$roleLabels = [
    'admin'      => t('role_admin'),
    'enfermero'  => t('role_enfermero'),
    'medico'     => t('role_medico'),
    'familiar'   => t('role_familiar'),
    'superadmin' => t('role_superadmin'),
];
$roleLabel = $roleLabels[$userRole] ?? ucfirst($userRole);
$saRoleOptions = ['superadmin', 'admin', 'enfermero', 'medico', 'familiar'];
$saRoleSwitchNext = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/cuidados.php', ENT_QUOTES, 'UTF-8');

$residentes     = Residente::getActivos($instId);

// Non-admin users only see their linked residents
if (!in_array($userRole, ['admin', 'superadmin'], true) && !$saFamiliarAllResidents) {
    try {
        $linkedIds = UsuarioResidente::getResidenteIds($userId, $instId);
        if (!empty($linkedIds)) {
            $linkedSet = array_flip($linkedIds);
            $residentes = array_values(array_filter($residentes, fn($r) => isset($linkedSet[(int)$r['id']])));
        }
    } catch (\Throwable $e) { /* table may not exist yet */ }
}

$residentesJson = json_encode(array_map(fn($r) => [
    'id'       => (int)$r['id'],
    'nombre'   => $r['nombre'] . ' ' . $r['apellidos'],
    'foto_path'=> $r['foto_path'] ?? null,
], $residentes), JSON_UNESCAPED_UNICODE);

$rxAll = Prescripcion::getForInstitucion($instId);
$rxByRes = [];
foreach ($rxAll as $rx) {
    $rxByRes[(int)$rx['residente_id']][] = $rx;
}
$rxJson = json_encode($rxByRes, JSON_UNESCAPED_UNICODE);

$canEdit = in_array($userRole, ['admin', 'enfermero', 'medico', 'superadmin'], true);

// Multi-institution: get user's institutions for selector
$userInstituciones = [];
try {
    $userInstituciones = UsuarioInstitucion::getByUsuario($userId, true);
} catch (\Throwable $e) { /* model might not exist yet */ }
$isMultiInst = count($userInstituciones) > 1;
$instJson = json_encode(array_map(fn($i) => [
    'id'     => (int)$i['institucion_id'],
    'nombre' => $i['nombre'] ?? $i['inst_nombre'] ?? 'Institución',
], $userInstituciones), JSON_UNESCAPED_UNICODE);

$saInstituciones = [];
if ($saImpersonating) {
    try {
        $saStmt = Database::getMaster()->query("SELECT id, nombre, estado FROM instituciones ORDER BY nombre ASC");
        $saInstituciones = $saStmt ? $saStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (\Throwable $e) { $saInstituciones = []; }
}

// Load config for timezone / date format
$cfg = Configuracion::getCached($instId);
$cfgTimezone    = $cfg['timezone'] ?? 'America/Mexico_City';
$cfgFechaFmt    = $cfg['fecha_formato'] ?? 'd/m/Y';

// Resolve per-role permission: registrar_futuro
$rolesPermisos = [];
if (!empty($cfg['roles_permisos'])) {
    $rolesPermisos = is_string($cfg['roles_permisos']) ? json_decode($cfg['roles_permisos'], true) : $cfg['roles_permisos'];
}
// Per-element UI permissions (superadmin-managed)
$permElements = [];
if (!empty($cfg['perm_elements'])) {
    $permElements = is_string($cfg['perm_elements']) ? json_decode($cfg['perm_elements'], true) : $cfg['perm_elements'];
}
$canFuture = in_array($userRole, ['admin','superadmin'], true)
    || (!empty($rolesPermisos[$userRole]['registrar_futuro']));
$canMakeRecords = in_array($userRole, ['admin','superadmin'], true)
    || ($userRole !== 'familiar' && (!isset($rolesPermisos[$userRole]['hacer_registros']) || !empty($rolesPermisos[$userRole]['hacer_registros'])))
    || ($userRole === 'familiar' && !empty($rolesPermisos[$userRole]['hacer_registros']));
$canViewCuidadosGrid = $canMakeRecords
    || (!empty($rolesPermisos[$userRole]['ver_cuidados_grid']));
$canViewRxTracker = in_array($userRole, ['admin','superadmin'], true)
    || (!isset($rolesPermisos[$userRole]['ver_rx_tracker']) || !empty($rolesPermisos[$userRole]['ver_rx_tracker']))
    || !empty($rolesPermisos[$userRole]['ver_rx_tracker_readonly']);
// Read-only mode: user can view the daily medication tracker but cannot click pills to register administrations.
// Applies when the user lacks "hacer_registros" but has the readonly perm (typical for familiar role).
$rxTrackerReadonly = !in_array($userRole, ['admin','superadmin'], true)
    && empty($rolesPermisos[$userRole]['hacer_registros'])
    && (!isset($rolesPermisos[$userRole]['ver_rx_tracker_readonly']) || !empty($rolesPermisos[$userRole]['ver_rx_tracker_readonly']));
$canEditResidents = in_array($userRole, ['admin','superadmin'], true)
    || (isset($rolesPermisos[$userRole]['editar_residentes'])
        ? !empty($rolesPermisos[$userRole]['editar_residentes'])
        : in_array($userRole, ['medico'], true));
// Permiso dedicado para agregar/editar/eliminar familiares en la Ficha.
// Por defecto (compat hacia atrás) sigue al permiso editar_residentes.
$canEditFamily = in_array($userRole, ['admin','superadmin'], true)
    || (isset($rolesPermisos[$userRole]['editar_familia'])
        ? !empty($rolesPermisos[$userRole]['editar_familia'])
        : $canEditResidents);
$canMoveResidentInstitution = in_array($userRole, ['admin','superadmin'], true)
    || (in_array($userRole, ['medico','enfermero'], true)
        && !empty($rolesPermisos[$userRole]['mover_residente_institucion']));
$canPreviewReport = in_array($userRole, ['admin','superadmin'], true)
    || (!empty($rolesPermisos[$userRole]['preview_reportes']));

// -- Section visibility (per-role) --------------------------------------------
// Admin/superadmin always see everything. Other roles default to `true` for
// the main user-facing sections (backwards compat) and `false` for admin-only
// sections (residentes, configuración).
$_secCheck = function(string $key, bool $defaultIfMissing) use ($userRole, $rolesPermisos): bool {
    if (in_array($userRole, ['admin','superadmin'], true)) return true;
    if (!isset($rolesPermisos[$userRole][$key])) return $defaultIfMissing;
    return !empty($rolesPermisos[$userRole][$key]);
};
$canSecInicio        = $_secCheck('ver_seccion_inicio',        true);
$canSecRegistros     = $_secCheck('ver_seccion_registros',     true);
$canSecMedicacion    = $_secCheck('ver_seccion_medicacion',    true);
$canSecFicha         = $_secCheck('ver_seccion_ficha',         true);
$canVerNotasMedico   = $_secCheck('ver_notas_medico',          true); // familiar: solo lectura; admin/medico/enfermero: true por defecto
$canNmDirecto        = $_secCheck('notas_medico_directo',       true); // familiar: ir directamente a la vista de notas médicas (no al timeline)
$canSecExpediente    = $_secCheck('ver_seccion_expediente',    true);
$canSecResidentes    = $userRole === 'familiar' ? true : $_secCheck('ver_seccion_residentes',    false);
$canSecConfiguracion = $_secCheck('ver_seccion_configuracion', false);
$initialView = $canSecResidentes ? 'viewResidentes' : ($canSecInicio ? 'viewDashboard' : ($canSecRegistros ? 'viewRecords' : ($canSecMedicacion ? 'viewInventory' : ($canSecFicha ? 'viewFicha' : ($canSecExpediente ? 'viewExpediente' : 'viewDashboard')))));

// Apple §3.1.1 / Google Play: la app nativa está publicada como gratis, por lo
// que no debe mostrar entradas de suscripción/facturación ni links de pago.
// Server-side cuando sea posible; client-side fail-closed en el <head> abajo.
$_nativeUaRaw = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_nativeUaLow = strtolower($_nativeUaRaw);
$isNativeAppShell = !empty($_SERVER['HTTP_X_NATIVE_APP'])
    || (!empty($_SERVER['HTTP_ORIGIN']) && str_starts_with($_SERVER['HTTP_ORIGIN'], 'capacitor://'))
    || ($_nativeUaRaw !== '' && (
        stripos($_nativeUaRaw, 'GeriAppNative') !== false
        || stripos($_nativeUaRaw, 'Capacitor') !== false
        || stripos($_nativeUaRaw, 'CapacitorWebView') !== false
        || strpos($_nativeUaLow, '; wv)') !== false
        || (
            strpos($_nativeUaLow, 'applewebkit') !== false
            && (strpos($_nativeUaLow, 'iphone') !== false || strpos($_nativeUaLow, 'ipad') !== false || strpos($_nativeUaLow, 'ipod') !== false)
            && strpos($_nativeUaLow, 'mobile/') !== false
            && strpos($_nativeUaLow, 'safari/') === false
            && strpos($_nativeUaLow, 'crios/') === false
            && strpos($_nativeUaLow, 'fxios/') === false
        )
    ));
?>
<!DOCTYPE html>
<html lang="<?= t('_lang_code') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" id="cdThemeColorMeta" content="#178391">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?= csrf_meta() ?>
    <title><?= t('title_cuidados') ?> — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSETS_URL ?>/icons/app/icon_32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= ASSETS_URL ?>/icons/app/icon_64.png">
    <link rel="apple-touch-icon" sizes="128x128" href="<?= ASSETS_URL ?>/icons/app/icon_128.png">
    <link rel="apple-touch-icon" sizes="256x256" href="<?= ASSETS_URL ?>/icons/app/icon_256.png">
    <script>(function(){var t=localStorage.getItem('geriappTheme');if(t!=='light')document.documentElement.setAttribute('data-theme','dark');var f=localStorage.getItem('geriappFontSize');if(f)document.documentElement.setAttribute('data-font-size',f);var s=localStorage.getItem('geriappSpacing');if(s)document.documentElement.setAttribute('data-spacing',s);})()</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400..600,0,0&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/cuidados.css?v=<?= filemtime(__DIR__.'/assets/css/cuidados.css') ?>">
    <script>(function(){
        var serverNative = <?= $isNativeAppShell ? 'true' : 'false' ?>;
        function markNative(){
            document.documentElement.dataset.platform = 'native';
            document.documentElement.dataset.native = '1';
            try { localStorage.setItem('geriappNativeApp', '1'); } catch(_) {}
        }
        function looksNative(){
            try {
                if (serverNative) return true;
                if (localStorage.getItem('geriappNativeApp') === '1') return true;
                if (window.Capacitor) {
                    if (typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) return true;
                    if (window.Capacitor.platform && window.Capacitor.platform !== 'web') return true;
                }
                var ua = (navigator.userAgent || '').toLowerCase();
                if (ua.indexOf('geriappnative') !== -1 || ua.indexOf('capacitor') !== -1 || ua.indexOf('geriapp') !== -1) return true;
                if (location.protocol === 'capacitor:' || location.protocol === 'ionic:') return true;
                if (ua.indexOf('; wv)') !== -1) return true;
                if (ua.indexOf('applewebkit') !== -1 &&
                    (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1) &&
                    ua.indexOf('mobile/') !== -1 && ua.indexOf('safari/') === -1 &&
                    ua.indexOf('crios/') === -1 && ua.indexOf('fxios/') === -1) return true;
            } catch(_) {}
            return false;
        }
        function check(allowWeb){
            if (looksNative()) { markNative(); return; }
            if (allowWeb && !document.documentElement.dataset.native) document.documentElement.dataset.platform = 'web';
        }
        check(false);
        [100, 500, 1200, 2500].forEach(function(ms){ setTimeout(function(){ check(ms >= 1200); }, ms); });
        window.addEventListener('load', function(){ setTimeout(function(){ check(true); }, 300); });
    })();</script>
    <style>
        #cdProfileBillingCard { display: none !important; }
        html[data-platform="web"] #cdProfileBillingCard { display: block !important; }
        html[data-native="1"] #cdProfileBillingCard { display: none !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js" defer></script>
</head>
<body>
<div class="cd-app" id="cdApp">

<?php if ($saImpersonating): ?>
<div class="cd-sa-banner">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
    <span class="cd-sa-banner-main">Superadmin · <?= $saImpInstNombre ?></span>
    <?php if (!empty($saInstituciones)): ?>
    <div class="cd-sa-inst-form" aria-label="Cambiar institución">
        <label for="cdSaInstSelect">Institución</label>
        <select id="cdSaInstSelect" title="Cambiar institución">
            <?php foreach ($saInstituciones as $saInst): ?>
            <?php $saInstId = (int)$saInst['id']; $saInstEstado = trim((string)($saInst['estado'] ?? '')); ?>
            <option value="<?= $saInstId ?>" <?= $saInstId === $instId ? 'selected' : '' ?>><?= htmlspecialchars($saInst['nombre'] ?? 'Institución') ?><?= $saInstEstado !== '' ? ' · ' . htmlspecialchars($saInstEstado) : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <form class="cd-sa-role-form" action="<?= BASE_URL ?>/superadmin/impersonate_role.php" method="post" aria-label="Entrar como">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= $saRoleSwitchNext ?>">
        <label for="cdSaRoleSelect">Entrar como</label>
        <select id="cdSaRoleSelect" name="role" onchange="this.form.submit()">
            <?php foreach ($saRoleOptions as $saRoleOption): ?>
            <option value="<?= htmlspecialchars($saRoleOption) ?>" <?= $userRole === $saRoleOption ? 'selected' : '' ?>><?= htmlspecialchars($roleLabels[$saRoleOption] ?? ucfirst($saRoleOption)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <button id="cdSaReturnBtn" title="<?= t('header_sa_back') ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <?= t('header_sa_back') ?>
    </button>
</div>
<?php endif; ?>

<!-- --------------- HEADER --------------- -->
<header class="cd-header">
    <div class="cd-header-top">
        <?php if ($isMultiInst && !$saImpersonating): ?>
        <select class="cd-inst-select" id="cdInstSelect" title="<?= t('header_switch_inst') ?>">
            <?php foreach ($userInstituciones as $ui): ?>
            <option value="<?= (int)$ui['institucion_id'] ?>" <?= (int)$ui['institucion_id'] === $instId ? 'selected' : '' ?>><?= htmlspecialchars($ui['nombre'] ?? $ui['inst_nombre'] ?? 'Institución') ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <div class="cd-header-actions">
            <?php if (!$saImpersonating): ?>
            <div class="cd-avatar-wrap">
                <button class="cd-avatar-btn" id="cdAvatarBtn" data-perm-id="nav_open_profile_btn" title="<?= t('header_my_profile') ?>"><?= htmlspecialchars($userInitials) ?></button>
                <span class="cd-avatar-role-badge"><?= htmlspecialchars($roleLabel) ?></span>
            </div>
            <?php endif; ?>
            <button class="cd-icon-btn cd-notif-btn" id="cdNotifBtn" title="<?= t('header_notifications') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="cd-notif-dot" id="cdNotifDot" style="display:none"></span>
            </button>
            <button class="cd-icon-btn" id="cdThemeToggle" title="<?= t('header_toggle_theme') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <?php if ($canSecConfiguracion): ?>
            <button class="cd-icon-btn" id="cdHeaderConfigBtn" data-nav="viewConfig" title="<?= t('nav_config') ?>">
                <span class="material-symbols-outlined" style="font-size:20px;line-height:1">settings</span>
            </button>
            <?php endif; ?>
            <button class="cd-icon-btn" id="cdLogoutBtn" title="<?= t('header_logout') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </div>
    <div class="cd-header-bottom">
        <?php $headerRes = $residentes[0] ?? null; ?>
        <div class="cd-header-res-card<?= $headerRes ? '' : ' is-empty' ?>" id="cdHeaderResidentCard" aria-live="polite" title="<?= t('header_change_resident') ?>">
            <div class="cd-header-res-avatar" id="cdHeaderResidentAvatar" aria-hidden="true">
                <?php if (!empty($headerRes['foto_path'])): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($headerRes['foto_path'], '/')) ?>" alt="">
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php endif; ?>
            </div>
            <div class="cd-header-res-info">
                <strong id="cdHeaderResidentName"><?= $headerRes ? htmlspecialchars(($headerRes['nombre'] ?? '') . ' ' . ($headerRes['apellidos'] ?? '')) : t('header_no_residents') ?></strong>
            </div>
            <span class="cd-header-res-change" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </span>
        </div>
        <select id="cdPatientName" class="cd-header-res-select" title="<?= t('header_change_resident') ?>">
            <?php foreach ($residentes as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellidos']) ?></option>
            <?php endforeach; ?>
            <?php if (empty($residentes)): ?>
            <option value=""><?= t('header_no_residents') ?></option>
            <?php endif; ?>
        </select>
    </div>
</header>

<!-- --------------- DESKTOP SIDEBAR --------------- -->
<aside class="cd-nav-sidebar" id="cdNavSidebar">
    <nav class="cd-nav-sidebar-items">
        <button class="cd-nav-sidebar-item<?= $initialView === 'viewResidentes' ? ' active' : '' ?><?= $canSecResidentes ? '' : ' cd-role-locked' ?>" data-nav="viewResidentes" title="<?= t('nav_home') ?>"<?= $canSecResidentes ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon material-symbols-outlined">groups</span>
            <span><?= t('nav_home') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $initialView === 'viewDashboard' ? ' active' : '' ?><?= $canSecInicio ? '' : ' cd-role-locked' ?>" data-nav="viewDashboard" title="<?= t('title_cuidados') ?>"<?= $canSecInicio ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon"><img src="<?= ASSETS_URL ?>/icons/cuidados.png" alt="" class="cd-nav-icon-img cd-nav-icon-img--cuidados" aria-hidden="true"></span>
            <span><?= t('title_cuidados') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $canSecRegistros ? '' : ' cd-role-locked' ?>" data-nav="viewRecords" title="<?= t('nav_records') ?>"<?= $canSecRegistros ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon material-symbols-outlined">description</span>
            <span><?= t('nav_records') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $canSecMedicacion ? '' : ' cd-role-locked' ?>" data-nav="viewInventory" title="<?= t('nav_pharmacy') ?>"<?= $canSecMedicacion ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon"><img src="<?= ASSETS_URL ?>/icons/pill.png" alt="" class="cd-nav-icon-img cd-nav-icon-img--pill" aria-hidden="true"></span>
            <span><?= t('nav_pharmacy') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $canSecFicha ? '' : ' cd-role-locked' ?>" data-nav="viewFicha" title="<?= t('nav_ficha') ?>"<?= $canSecFicha ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon"><img src="<?= ASSETS_URL ?>/icons/ficha.png" alt="" class="cd-nav-icon-img cd-nav-icon-img--ficha" aria-hidden="true"></span>
            <span><?= t('nav_ficha') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $canSecExpediente ? '' : ' cd-role-locked' ?>" data-nav="viewExpediente" title="<?= t('nav_expediente') ?>"<?= $canSecExpediente ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon material-symbols-outlined">folder_open</span>
            <span><?= t('nav_expediente') ?></span>
        </button>
        <button class="cd-nav-sidebar-item<?= $canSecConfiguracion ? '' : ' cd-role-locked' ?>" data-nav="viewConfig" title="<?= t('nav_config') ?>"<?= $canSecConfiguracion ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
            <span class="cd-nav-icon material-symbols-outlined">settings</span>
            <span><?= t('nav_config') ?></span>
        </button>
    </nav>
    <div class="cd-nav-sidebar-footer" id="cdNavVersion">
        <span class="cd-nav-sidebar-ver">v<?= APP_VERSION ?></span>
        <div class="cd-changelog-tip">
            <?php
            $changelog = include __DIR__ . '/changelog/changelog.php';
            $latest = array_slice($changelog, 0, 2); // show last 2 versions
            foreach ($latest as $entry): ?>
            <strong>v<?= htmlspecialchars($entry['version']) ?></strong>
            <ul style="margin:4px 0 0 0;padding-left:16px;">
                <?php foreach ($entry['changes'] as $c): ?>
                <li><b>[<?= htmlspecialchars($c['tag']) ?>]</b> <?= htmlspecialchars($c['text']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endforeach; ?>
        </div>
    </div>
</aside>

<!-- --------------- MAIN --------------- -->
<main class="cd-main">

<!-- -- Shared Date Navigator (Cuidados — Registros — Medicación) -- -->
<div class="cd-date-nav" id="cdGlobalDateNav"<?= in_array($initialView, ['viewDashboard','viewRecords','viewInventory'], true) ? '' : ' style="display:none"' ?>>
    <button id="cdDatePrev" title="<?= t('btn_prev_day') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <div class="cd-date-center">
        <button class="today-btn" id="cdTodayBtn" title="<?= t('btn_go_today') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="cdDateLabel"><?= t('btn_today') ?></span>
        </button>
        <button class="cd-today-quick" id="cdTodayQuick" title="<?= t('btn_today') ?>"><?= t('btn_today') ?></button>
        <input type="date" id="cdDateInput" data-no-fmt-hint="1" value="<?= (new DateTime('now', new DateTimeZone($cfgTimezone)))->format('Y-m-d') ?>">
    </div>
    <button id="cdDateNext" title="<?= t('btn_next_day') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
</div>

<!-- -- VIEW: Dashboard ----------------------------------- -->

<!-- --- HTML Views (extracted) --- -->
<?php include __DIR__ . '/includes/views/view-dashboard.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-sueno.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-alimentacion.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-medicacion.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-higiene.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-terapia.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-movilidad.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-eliminacion.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-comportamiento.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-signos-vitales.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-incidente.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-notas.php'; ?>
<?php include __DIR__ . '/includes/views/view-form-notas-medico.php'; ?>
<?php include __DIR__ . '/includes/views/view-records.php'; ?>
<?php include __DIR__ . '/includes/views/view-inventory.php'; ?>
<?php include __DIR__ . '/includes/views/view-profile.php'; ?>
<?php include __DIR__ . '/includes/views/view-ficha.php'; ?>
<?php include __DIR__ . '/includes/views/view-config.php'; ?>
<?php include __DIR__ . '/includes/views/view-residentes.php'; ?>
<?php include __DIR__ . '/includes/views/view-invitaciones.php'; ?>
<?php include __DIR__ . '/includes/views/view-nuevo-residente.php'; ?>
<?php include __DIR__ . '/includes/views/view-expediente.php'; ?>


</main>

<!-- --------------- NOTIFICATION PANEL --------------- -->
<div class="cd-notif-overlay" id="cdNotifOverlay"></div>
<aside class="cd-notif-panel" id="cdNotifPanel">
    <div class="cd-notif-panel-head">
        <div>
            <h3><?= t('notif_title') ?></h3>
            <p class="cd-notif-panel-sub"><?= t('notif_subtitle') ?></p>
        </div>
        <button class="cd-icon-btn" id="cdNotifClose" title="<?= t('form_back') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="cd-notif-panel-tabs">
        <button class="cd-notif-ptab active" data-ntab="all"><?= t('notif_tab_all') ?></button>
        <button class="cd-notif-ptab" data-ntab="push">Push</button>
        <button class="cd-notif-ptab" data-ntab="email">Email</button>
        <button class="cd-notif-ptab" data-ntab="whatsapp">WhatsApp</button>
    </div>
    <div class="cd-notif-panel-body" id="cdNotifList"></div>
</aside>

<!-- --------------- BOTTOM NAV --------------- -->
<nav class="cd-bottom-nav">
    <button class="cd-nav-item<?= $initialView === 'viewDashboard' ? ' active' : '' ?><?= $canSecInicio ? '' : ' cd-role-locked' ?>" data-nav="viewDashboard"<?= $canSecInicio ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
        <span class="cd-nav-icon"><img src="<?= ASSETS_URL ?>/icons/cuidados.png" alt="" class="cd-nav-icon-img cd-nav-icon-img--cuidados" aria-hidden="true"></span>
        <span class="cd-nav-label"><?= t('title_cuidados') ?></span>
    </button>
    <button class="cd-nav-item<?= $initialView === 'viewRecords' ? ' active' : '' ?><?= $canSecRegistros ? '' : ' cd-role-locked' ?>" data-nav="viewRecords" aria-label="<?= t('nav_records') ?>"<?= $canSecRegistros ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
        <span class="cd-nav-icon material-symbols-outlined">description</span>
        <span class="cd-nav-label"><?= t('nav_records') ?></span>
    </button>
    <button class="cd-nav-item<?= $initialView === 'viewResidentes' ? ' active' : '' ?><?= $canSecResidentes ? '' : ' cd-role-locked' ?>" data-nav="viewResidentes"<?= $canSecResidentes ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
        <span class="cd-nav-icon material-symbols-outlined">groups</span>
        <span class="cd-nav-label"><?= t('nav_home') ?></span>
    </button>
    <button class="cd-nav-item<?= $canSecMedicacion ? '' : ' cd-role-locked' ?>" data-nav="viewInventory"<?= $canSecMedicacion ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
        <span class="cd-nav-icon"><img src="<?= ASSETS_URL ?>/icons/pill.png" alt="" class="cd-nav-icon-img cd-nav-icon-img--pill" aria-hidden="true"></span>
        <span class="cd-nav-label"><?= t('nav_pharmacy') ?></span>
    </button>
    <button class="cd-nav-item<?= $canSecExpediente ? '' : ' cd-role-locked' ?>" data-nav="viewExpediente"<?= $canSecExpediente ? '' : ' data-cd-locked data-lock-title="Sección restringida" data-lock-msg="No tienes acceso a esta sección con tu rol actual."' ?>>
        <span class="cd-nav-icon material-symbols-outlined">folder_open</span>
        <span class="cd-nav-label"><?= t('nav_expediente') ?></span>
    </button>
</nav>

</div><!-- .cd-app -->

<!-- -- Asistente IA: botón flotante + panel ------------------------------ -->
<button type="button" class="cd-asis-fab" id="cdAsisFab" aria-label="<?= t('asis_open') ?>" title="<?= t('asis_open') ?>">
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        <circle cx="9"  cy="11" r="1" fill="currentColor"/>
        <circle cx="13" cy="11" r="1" fill="currentColor"/>
        <circle cx="17" cy="11" r="1" fill="currentColor"/>
    </svg>
    <span class="cd-asis-fab-pulse" aria-hidden="true"></span>
</button>

<div class="cd-asis-panel" id="cdAsisPanel" role="dialog" aria-modal="false" aria-labelledby="cdAsisTitle" hidden>
    <header class="cd-asis-header">
        <button type="button" class="cd-asis-iconbtn" id="cdAsisHistBtn" title="<?= t('asis_history') ?>" aria-label="<?= t('asis_history') ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9"/><polyline points="3 4 3 12 11 12"/><polyline points="12 7 12 12 16 14"/></svg>
        </button>
        <div class="cd-asis-title" id="cdAsisTitle"><?= t('asis_title') ?></div>
        <button type="button" class="cd-asis-iconbtn" id="cdAsisNewBtn" title="<?= t('asis_new') ?>" aria-label="<?= t('asis_new') ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <button type="button" class="cd-asis-iconbtn cd-asis-close" id="cdAsisCloseBtn" title="<?= t('asis_close') ?>" aria-label="<?= t('asis_close') ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </header>

    <div class="cd-asis-history" id="cdAsisHistory" hidden>
        <div class="cd-asis-history-list" id="cdAsisHistoryList"></div>
    </div>

    <div class="cd-asis-body" id="cdAsisBody" aria-live="polite">
        <div class="cd-asis-empty" id="cdAsisEmpty">
            <div class="cd-asis-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div class="cd-asis-empty-title"><?= t('asis_welcome_title') ?></div>
            <div class="cd-asis-empty-text"><?= t('asis_welcome_text') ?></div>
            <div class="cd-asis-suggest" id="cdAsisSuggest">
                <button type="button" class="cd-asis-suggest-chip"><?= t('asis_sug_register') ?></button>
                <button type="button" class="cd-asis-suggest-chip"><?= t('asis_sug_expediente') ?></button>
                <button type="button" class="cd-asis-suggest-chip"><?= t('asis_sug_meds') ?></button>
                <button type="button" class="cd-asis-suggest-chip"><?= t('asis_sug_reports') ?></button>
            </div>
        </div>
    </div>

    <form class="cd-asis-input" id="cdAsisForm" autocomplete="off">
        <textarea class="cd-asis-textarea" id="cdAsisTextarea" rows="1" maxlength="4000" placeholder="<?= t('asis_placeholder') ?>" aria-label="<?= t('asis_placeholder') ?>"></textarea>
        <button type="submit" class="cd-asis-send" id="cdAsisSendBtn" disabled aria-label="<?= t('asis_send') ?>" title="<?= t('asis_send') ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </form>
    <div class="cd-asis-foot"><?= t('asis_disclaimer') ?></div>
</div>

<!-- Movement modal -->
<div class="cd-modal-overlay" id="cdMovModal">
<div class="cd-modal">
    <h2><?= t('mov_modal_title') ?></h2>
    <input type="hidden" id="cdMovItemId">
    <div class="cd-form-group">
        <label class="cd-form-label"><?= t('mov_type_label') ?></label>
        <select class="cd-select" id="cdMovType">
            <option value="entrada"><?= t('mov_type_entry') ?></option>
            <option value="salida"><?= t('mov_type_exit') ?></option>
        </select>
    </div>
    <div class="cd-form-group">
        <label class="cd-form-label"><?= t('mov_qty_label') ?></label>
        <input type="number" class="cd-input" id="cdMovQty" min="1" value="1">
    </div>
    <div class="cd-form-group">
        <label class="cd-form-label"><?= t('mov_reason_label') ?></label>
        <input type="text" class="cd-input" id="cdMovReason" placeholder="<?= t('mov_reason_ph') ?>">
    </div>
    <div style="display:flex;gap:8px;margin-top:16px">
        <button class="cd-btn-submit" id="cdMovModalSave" data-perm-id="inv_save_mov_modal_btn" style="flex:1"><?= t('mov_save_btn') ?></button>
        <button class="cd-btn-submit" id="cdMovModalCancel" style="flex:1;background:var(--cd-border);color:var(--cd-text)"><?= t('btn_cancel') ?></button>
    </div>
</div>
</div>

<!-- Confirm dialog (replaces native confirm()) -->
<div class="cd-confirm-overlay" id="cdConfirmOverlay">
    <div class="cd-confirm-dialog">
        <div class="cd-confirm-icon warn" id="cdConfirmIcon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h3 class="cd-confirm-title" id="cdConfirmTitle"><?= t('confirm_default_title') ?></h3>
        <p class="cd-confirm-msg" id="cdConfirmMsg"></p>
        <div class="cd-confirm-btns">
            <button class="cd-confirm-cancel" id="cdConfirmNo"><?= t('btn_cancel') ?></button>
            <button class="cd-confirm-ok" id="cdConfirmYes"><?= t('confirm_btn_ok') ?></button>
        </div>
    </div>
</div>

<!-- Right sidebar (shared for edit & add-med) -->
<div class="cd-sysnotif-overlay" id="cdSysNotifOverlay" style="display:none">
<div class="cd-sysnotif-modal">
    <div class="cd-sysnotif-icon" id="cdSysNotifIcon"></div>
    <h2 class="cd-sysnotif-title" id="cdSysNotifTitle"></h2>
    <div class="cd-sysnotif-body" id="cdSysNotifBody"></div>
    <div class="cd-sysnotif-media" id="cdSysNotifMedia" style="display:none"></div>
    <div class="cd-sysnotif-response" id="cdSysNotifResponse" style="display:none"></div>
    <div class="cd-sysnotif-actions" id="cdSysNotifActions"></div>
    <div class="cd-sysnotif-counter" id="cdSysNotifCounter"></div>
</div>
</div>

<div class="cd-sidebar-overlay" id="cdSidebarOverlay" role="presentation">
    <aside class="cd-sidebar" id="cdSidebarPanel" role="dialog" aria-modal="true" aria-labelledby="cdSidebarTitle">
        <div class="cd-sidebar-handle" aria-hidden="true"><span></span></div>
        <div class="cd-sidebar-head">
            <h3 id="cdSidebarTitle"><?= t('sidebar_detail') ?></h3>
            <button class="cd-sidebar-close" id="cdSidebarClose" aria-label="<?= t('sidebar_close') ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cd-sidebar-body" id="cdSidebarBody">
            <!-- Dynamic content injected by JS -->
        </div>
        <div class="cd-sidebar-actions" id="cdSidebarActions"></div>
    </aside>
</div>

<!-- Toast -->
<div class="cd-toast" id="cdToast"></div>


<script>
<?php include __DIR__ . '/assets/js/cd-core.js.php'; echo "\n"; ?>
window.__cdRxReadonly = <?= !empty($rxTrackerReadonly) ? 'true' : 'false' ?>;
window.__cdCanMoveResident = <?= !empty($canMoveResidentInstitution) ? 'true' : 'false' ?>;
<?php include __DIR__ . '/assets/js/cd-navigation.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-date-nav.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-patient.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-resident.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-family.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-info-edit.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-family-edit.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-profile.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-arco.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-api.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-dashboard.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-sidebar.js.php'; echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-notes.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-nm.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-forms.js.php'; echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-residentes.js.php'; echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-new-resident.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-records.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-day-report.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-ai.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-inventory.js.php'; echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-theme.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-ai-rewrite.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-asistente.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-helpers.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-config.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-legal.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-db-config.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-logs.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-error-log.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-backups.js.php'; echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-notif-admin.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-sessions.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-sys-notif.js'); echo "\n"; ?>
<?php readfile(__DIR__ . '/assets/js/cd-init.js'); echo "\n"; ?>
<?php include __DIR__ . '/assets/js/cd-expediente.js.php'; echo "\n"; ?>

<?php echo "})();\n"; ?>

// -- Offline / Online detection ------------------
(function(){
    var bid = '_gaOfflineBanner';
    function _offShow(){
        if(document.getElementById(bid)) return;
        var d=document.createElement('div');d.id=bid;d.className='cd-offline-banner';
        d.innerHTML='<div class="cd-offline-inner"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg> Sin conexión a internet</div>';
        document.body.insertBefore(d, document.body.firstChild);
        if(typeof showToast==='function') showToast('Sin conexión a internet','error');
    }
    function _offHide(){
        var el=document.getElementById(bid);if(el)el.remove();
        if(typeof showToast==='function') showToast('Conexión restaurada','success');
    }
    window.addEventListener('offline',_offShow);
    window.addEventListener('online',_offHide);
    if(!navigator.onLine) _offShow();
})();
</script>
</body>
</html>
