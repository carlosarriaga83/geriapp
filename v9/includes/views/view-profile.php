<?php $cdShowBillingProfile = empty($isNativeAppShell); ?>
<?php $userName = $userName ?? htmlspecialchars($userProfile['nombre'] ?? ($_SESSION['user_nombre'] ?? 'Usuario')); ?>
<?php $roleLabel = $roleLabel ?? ucfirst((string)($_SESSION['user_rol'] ?? 'usuario')); ?>
<?php $userInitials = $userInitials ?? implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(explode(' ', trim($userProfile['nombre'] ?? ($_SESSION['user_nombre'] ?? 'U'))), 0, 2))); ?>
<?php $cdProfileAvatar = trim((string)($userProfile['avatar_path'] ?? '')); ?>
<section id="viewProfile" class="cd-view">
<div class="cd-profile">
    <div class="cd-profile-header-row">
        <h1><?= t('profile_title') ?></h1>
        <button class="cd-profile-edit-btn" id="cdProfileEditBtn" data-perm-id="prof_edit_profile_btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar
        </button>
    </div>

    <!-- View mode -->
    <div class="cd-profile-card" id="cdProfileView">
        <div class="cd-profile-avatar-block">
            <button type="button" class="cd-profile-avatar" id="cdProfAvatarView" title="Editar foto de perfil">
                <?php if ($cdProfileAvatar): ?>
                <img src="<?= htmlspecialchars($cdProfileAvatar) ?>" alt="">
                <?php else: ?>
                <span><?= htmlspecialchars($userInitials ?? '') ?></span>
                <?php endif; ?>
            </button>
        </div>
        <h2 class="cd-profile-name" id="cdProfName"><?= $userName ?></h2>
        <p class="cd-profile-role"><?= $roleLabel ?></p>
        <dl>
            <div class="cd-profile-row"><dt><?= t('profile_role') ?></dt><dd><?= $roleLabel ?></dd></div>
            <div class="cd-profile-row"><dt><?= t('profile_institution') ?></dt><dd><?= htmlspecialchars($_SESSION['user_institucion_nombre'] ?? '—') ?></dd></div>
            <div class="cd-profile-row"><dt>Email</dt><dd id="cdProfEmail"><?= htmlspecialchars($userProfile['email'] ?? ($_SESSION['user_email'] ?? '—')) ?></dd></div>
            <div class="cd-profile-row"><dt>Teléfono</dt><dd id="cdProfPhone"><?= htmlspecialchars(trim((string)($userProfile['telefono'] ?? '')) ?: '—') ?></dd></div>
        </dl>
    </div>

    <!-- Edit mode (hidden by default) -->
    <div class="cd-profile-card" id="cdProfileEdit" style="display:none">
        <h2 style="font-size:1.125rem;font-weight:600;margin:0 0 16px"><?= t('btn_edit') ?> <?= t('profile_title') ?></h2>
        <div class="cd-profile-edit-group">
            <label>Foto de perfil</label>
            <div class="cd-profile-avatar-edit-row">
                <button type="button" class="cd-profile-avatar cd-profile-avatar--edit" id="cdProfAvatarEdit" title="Cambiar foto de perfil">
                    <?php if ($cdProfileAvatar): ?>
                    <img src="<?= htmlspecialchars($cdProfileAvatar) ?>" alt="">
                    <?php else: ?>
                    <span><?= htmlspecialchars($userInitials ?? '') ?></span>
                    <?php endif; ?>
                </button>
                <div class="cd-profile-avatar-actions">
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="cdProfAvatarBtn">Cambiar foto</button>
                    <small>JPG, PNG o WebP. Máx. 10 MB.</small>
                </div>
                <input type="file" id="cdProfAvatarInput" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
            </div>
        </div>
        <div class="cd-profile-edit-group">
            <label>Nombre <span style="color:#dc2626">*</span></label>
            <input class="cd-input" type="text" id="cdProfEditName" value="<?= htmlspecialchars($userProfile['nombre'] ?? $_SESSION['user_nombre'] ?? '') ?>" required>
        </div>
        <div class="cd-profile-edit-group">
            <label>Email</label>
            <input class="cd-input" type="email" id="cdProfEditEmail"
                   value="<?= htmlspecialchars($userProfile['email'] ?? ($_SESSION['user_email'] ?? '')) ?>"
                   readonly disabled
                   style="background:rgba(0,0,0,.04);cursor:not-allowed">
            <p class="cd-form-hint" style="margin:4px 0 0;font-size:.78rem;color:var(--cd-text-muted)">El correo electrónico no puede modificarse desde aquí. Contacta a un administrador si necesitas cambiarlo.</p>
        </div>
        <div class="cd-profile-edit-group">
            <label>Teléfono <span style="color:#dc2626">*</span></label>
            <input class="cd-input" type="tel" id="cdProfEditPhone"
                   value="<?= htmlspecialchars($userProfile['telefono'] ?? '') ?>"
                   placeholder="+52 5512345678" inputmode="tel" autocomplete="tel">
            <p class="cd-form-hint" style="margin:4px 0 0;font-size:.78rem;color:var(--cd-text-muted)">Incluye el código de país (ej. +52). Necesario para que el asistente pueda crear grupos de WhatsApp contigo.</p>
        </div>
        <div class="cd-profile-edit-actions">
            <button class="cd-btn-submit cd-btn-save-edit" id="cdProfSaveBtn" data-perm-id="prof_save_profile_btn"><?= t('btn_save') ?></button>
            <button class="cd-btn-submit cd-btn-cancel-edit" id="cdProfCancelBtn"><?= t('btn_cancel') ?></button>
        </div>

        <form class="cd-password-section" onsubmit="return false">
            <h3><?= t('password_change_title') ?></h3>
            <!-- Hidden username field for password manager / a11y compliance
                 (DOM warning: "Password forms should have a username field"). -->
            <input type="text" id="cdProfPassUser" name="username"
                   autocomplete="username" tabindex="-1" aria-hidden="true"
                   value="<?= htmlspecialchars($_SESSION['user_email'] ?? ($_SESSION['user_name'] ?? ''), ENT_QUOTES) ?>"
                   style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0">
            <div class="cd-profile-edit-group">
                <label><?= t('password_current') ?></label>
                <input class="cd-input" type="password" id="cdProfCurPass" autocomplete="current-password">
            </div>
            <div class="cd-profile-edit-group">
                <label><?= t('password_new') ?></label>
                <input class="cd-input" type="password" id="cdProfNewPass" autocomplete="new-password">
            </div>
            <div class="cd-profile-edit-group">
                <label><?= t('password_confirm') ?></label>
                <input class="cd-input" type="password" id="cdProfConfPass" autocomplete="new-password">
            </div>
            <button class="cd-btn-submit cd-btn-save-edit" id="cdProfPassBtn" data-perm-id="prof_change_password_btn" style="width:100%"><?= t('password_change_btn') ?></button>
        </form>
    </div>

        <?php if ($cdShowBillingProfile): ?>
        <!-- Suscripción y facturación: abre billing.php dentro de un drawer lateral
         amplio (iframe) para no perder el contexto del perfil. Oculto en
         Capacitor por la regla CSS html[data-native="1"] #cdProfileBillingCard. -->
    <div class="cd-settings-card" id="cdProfileBillingCard">
        <h2>Suscripción y facturación</h2>
        <div class="cd-setting-row">
            <div>
                <span class="label">Gestionar tu plan, asientos y método de pago</span>
                <p class="desc">Cambia de plan, compra asientos extra y descarga facturas (Stripe).</p>
            </div>
            <button type="button" id="cdOpenBillingBtn" data-perm-id="prof_open_billing_btn" class="cd-btn-submit" style="display:inline-flex;align-items:center;gap:6px;background:#635bff;color:#fff;border:none;cursor:pointer">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3"/><path d="M2 11h20v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-7z"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
                Abrir
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="cd-settings-card">
        <h2><?= t('config_title') ?></h2>
        <div class="cd-setting-row">
            <div>
                <span class="label"><?= t('header_toggle_theme') ?></span>
                <p class="desc"><?= t('profile_theme_desc') ?></p>
            </div>
            <label class="cd-toggle">
                <input type="checkbox" id="cdDarkToggle">
                <span class="cd-toggle-track"></span>
            </label>
        </div>
        <div class="cd-setting-row">
            <div>
                <span class="label"><?= t('profile_font_size') ?></span>
                <p class="desc"><?= t('profile_font_desc') ?></p>
            </div>
            <select class="cd-input" id="cdFontSizeSelect" style="width:auto;min-width:120px">
                <option value="small"><?= t('profile_font_sm') ?></option>
                <option value="normal" selected><?= t('profile_font_md') ?></option>
                <option value="large"><?= t('profile_font_lg') ?></option>
                <option value="xlarge"><?= t('profile_font_xl') ?></option>
            </select>
        </div>
        <div class="cd-setting-row">
            <div>
                <span class="label"><?= t('profile_spacing') ?></span>
                <p class="desc"><?= t('profile_spacing_desc') ?></p>
            </div>
            <select class="cd-input" id="cdSpacingSelect" style="width:auto;min-width:120px">
                <option value="compact"><?= t('profile_spacing_compact') ?></option>
                <option value="normal" selected><?= t('profile_spacing_normal') ?></option>
                <option value="comfortable"><?= t('profile_spacing_relaxed') ?></option>
            </select>
        </div>
        <div class="cd-setting-row">
            <div>
                <span class="label"><?= t('profile_language') ?></span>
            </div>
            <select class="cd-input" id="cdLangSelect" style="width:auto;min-width:140px">
                <?php foreach (t_available() as $lang): ?>
                <option value="<?= $lang['code'] ?>" <?= $lang['code'] === APP_LANG ? 'selected' : '' ?>><?= $lang['flag'] ?> <?= $lang['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cd-setting-row" id="cdBiometricRow" style="display:none">
            <div>
                <span class="label">Biometría</span>
                <p class="desc">Ingresar con huella o reconocimiento facial</p>
            </div>
            <label class="cd-toggle">
                <input type="checkbox" id="cdBiometricToggle">
                <span class="cd-toggle-track"></span>
            </label>
        </div>
        <div style="padding-top:12px">
            <button class="cd-btn-submit" id="cdApplyPrefsBtn" data-perm-id="prof_save_prefs_btn" style="width:100%"><?= t('profile_save_prefs') ?></button>
        </div>
    </div>
    <!-- ── Mis Datos y Derechos ARCO ── -->
    <div class="cd-settings-card cd-arco-card">
        <h2><?= t('arco_title') ?></h2>
        <p class="cd-arco-desc"><?= t('arco_desc') ?></p>

        <div class="cd-arco-grid">
            <!-- Acceso -->
            <button class="cd-arco-btn" id="cdArcoAccess" data-perm-id="prof_arco_access_btn" title="<?= t('arco_access_desc') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <span><?= t('arco_access') ?></span>
            </button>
            <!-- Rectificación -->
            <button class="cd-arco-btn" id="cdArcoRectify" data-perm-id="prof_arco_rectify_btn" title="<?= t('arco_rectify_desc') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span><?= t('arco_rectify') ?></span>
            </button>
            <!-- Cancelación -->
            <button class="cd-arco-btn cd-arco-danger" id="cdArcoCancel" data-perm-id="prof_arco_cancel_btn" title="<?= t('arco_cancel_desc') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span><?= t('arco_cancel') ?></span>
            </button>
            <!-- Oposición -->
            <button class="cd-arco-btn" id="cdArcoOppose" data-perm-id="prof_arco_oppose_btn" title="<?= t('arco_oppose_desc') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                <span><?= t('arco_oppose') ?></span>
            </button>
        </div>

        <!-- Solicitudes previas -->
        <div id="cdArcoHistory" class="cd-arco-history" style="display:none">
            <h3><?= t('arco_history_title') ?></h3>
            <div id="cdArcoHistoryList"></div>
        </div>

        <button class="cd-btn-link" id="cdArcoShowHistory" data-perm-id="prof_arco_view_history_btn" style="margin-top:12px;font-size:0.82rem">
            <?= t('arco_view_history') ?>
        </button>

        <p class="cd-arco-legal-link">
            <a href="<?= BASE_URL ?>/adp.php<?= isset($_SESSION['user_institucion_id']) ? '?inst=' . (int)$_SESSION['user_institucion_id'] : '' ?>" target="_blank" rel="noopener">
                <?= t('arco_view_privacy') ?>
            </a>
        </p>
    </div>

    <button class="cd-btn-logout" id="cdLogoutBtn2"><?= t('header_logout') ?></button>
</div>
</section>

<?php if ($cdShowBillingProfile): ?>
<!-- ── Billing drawer (iframe) ──────────────────────────────────────
     Drawer lateral amplio que carga billing.php sin perder el contexto
     de la vista de Perfil. Oculto en Capacitor (la card se oculta vía CSS
     html[data-native="1"] #cdProfileBillingCard, por lo que el botón nunca
     dispara este drawer). El iframe se carga lazy: solo en el primer open.
-->
<style>
.cd-bill-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 500;
    visibility: hidden; opacity: 0;
    pointer-events: none;
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
    transition: opacity .28s ease, visibility 0s linear .28s;
}
.cd-bill-overlay.show {
    visibility: visible; opacity: 1;
    pointer-events: auto;
    transition: opacity .28s ease, visibility 0s linear 0s;
}
.cd-bill-panel {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: min(880px, 96vw);
    background: var(--cd-surface, #fff);
    box-shadow: -8px 0 32px rgba(0,0,0,.18);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .32s cubic-bezier(.4,0,.2,1);
    z-index: 501;
    /* Safe-area: respeta notch (top), home indicator (bottom) y borde derecho
       (orientación landscape con notch). El left no aplica porque el panel
       está anclado a la derecha. */
    padding-top:    env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
    padding-right:  env(safe-area-inset-right, 0px);
}
.cd-bill-overlay.show .cd-bill-panel { transform: translateX(0); }
.cd-bill-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid var(--cd-border, #e5e7eb);
    background: var(--cd-surface, #fff);
    flex-shrink: 0;
}
.cd-bill-head h3 {
    margin: 0; font-size: 1.0625rem; font-weight: 700;
    color: var(--cd-text, #1c1c1e);
    display: flex; align-items: center; gap: 8px;
}
.cd-bill-head .cd-bill-pop {
    color: var(--cd-text-muted, #6b7280);
    text-decoration: none;
    font-size: 0.8125rem;
    font-weight: 500;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid var(--cd-border, #e5e7eb);
    margin-right: 8px;
    transition: background .15s;
}
.cd-bill-head .cd-bill-pop:hover { background: var(--cd-bg, #f5f5f7); }
.cd-bill-head .cd-bill-actions { display: flex; align-items: center; }
.cd-bill-back {
    display: inline-flex; align-items: center; gap: 6px;
    background: transparent;
    border: 1px solid var(--cd-border, #e5e7eb);
    color: var(--cd-text, #1c1c1e);
    font-family: var(--cd-font, inherit);
    font-size: 0.875rem;
    font-weight: 600;
    padding: 7px 12px 7px 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    flex-shrink: 0;
    margin-right: 12px;
}
.cd-bill-back:hover { background: var(--cd-bg, #f5f5f7); border-color: var(--cd-text-muted, #6b7280); }
.cd-bill-close {
    width: 36px; height: 36px;
    border: none; background: transparent;
    color: var(--cd-text-muted, #6b7280);
    cursor: pointer; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.cd-bill-close:hover { background: var(--cd-bg, #f5f5f7); color: var(--cd-text, #1c1c1e); }
.cd-bill-body { flex: 1; position: relative; background: var(--cd-bg, #f5f5f7); }
.cd-bill-frame { width: 100%; height: 100%; border: 0; display: block; background: var(--cd-bg, #f5f5f7); }
.cd-bill-loading {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    color: var(--cd-text-muted, #6b7280); font-size: 0.875rem;
    background: var(--cd-bg, #f5f5f7);
}
@media (max-width: 720px) {
    .cd-bill-panel { width: 100vw; }
    .cd-bill-head .cd-bill-pop { display: none; }
}
</style>

<div class="cd-bill-overlay" id="cdBillingOverlay" role="presentation">
    <aside class="cd-bill-panel" role="dialog" aria-modal="true" aria-labelledby="cdBillingTitle">
        <div class="cd-bill-head">
            <a href="<?= BASE_URL ?>/billing.php" target="_blank" rel="noopener" class="cd-bill-pop" title="Abrir en pestaña nueva">↗ Pestaña nueva</a>
            <h3 id="cdBillingTitle">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#635bff" stroke-width="2"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3"/><path d="M2 11h20v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-7z"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
                Suscripción y facturación
            </h3>
            <div class="cd-bill-actions">
                <button type="button" class="cd-bill-back" id="cdBillingBack" aria-label="Volver" title="Volver al perfil">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>Volver</span>
                </button>
                <button type="button" class="cd-bill-close" id="cdBillingClose" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="cd-bill-body">
            <div class="cd-bill-loading" id="cdBillingLoading">Cargando…</div>
            <iframe class="cd-bill-frame" id="cdBillingFrame" title="Facturación" loading="lazy"></iframe>
        </div>
    </aside>
</div>

<script>
(function(){
    var btn     = document.getElementById('cdOpenBillingBtn');
    var overlay = document.getElementById('cdBillingOverlay');
    var closeBt = document.getElementById('cdBillingClose');
    var backBt  = document.getElementById('cdBillingBack');
    var frame   = document.getElementById('cdBillingFrame');
    var loading = document.getElementById('cdBillingLoading');
    if (!btn || !overlay || !frame) return;

    var loaded = false;
    var url    = <?= json_encode(BASE_URL . '/billing.php') ?>;

    // Defensa anti-stores: si el padre sospecha que estamos en una WebView nativa,
    // pasamos ?native=1 al iframe para que billing.php no muestre precios/planes
    // (Apple §3.1.1 / Google Play). Funciona aunque el APK aún no tenga
    // appendUserAgent inyectado.
    function _suspectNative(){
        try {
            if (document.documentElement.dataset.platform === 'native') return true;
            if (document.documentElement.dataset.native === '1')        return true;
            if (window.Capacitor) {
                if (typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) return true;
                if (window.Capacitor.platform && window.Capacitor.platform !== 'web') return true;
            }
            var ua = (navigator.userAgent || '').toLowerCase();
            if (ua.indexOf('capacitor') !== -1 || ua.indexOf('geriapp') !== -1) return true;
            if (location.protocol === 'capacitor:' || location.protocol === 'ionic:') return true;
            // Android WebView
            if (ua.indexOf('; wv)') !== -1) return true;
            // iOS WKWebView (AppleWebKit + iPhone/iPad + Mobile/ sin Safari/)
            if (ua.indexOf('applewebkit') !== -1 &&
                (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1) &&
                ua.indexOf('mobile/') !== -1 &&
                ua.indexOf('safari/') === -1 &&
                ua.indexOf('crios/') === -1 &&
                ua.indexOf('fxios/') === -1) return true;
        } catch (_) {}
        return false;
    }

    function open(){
        if (!loaded) {
            frame.src = url + (_suspectNative() ? (url.indexOf('?')>=0 ? '&' : '?') + 'native=1' : '');
            loaded = true;
            frame.addEventListener('load', function(){
                if (loading) loading.style.display = 'none';
            }, { once: true });
        } else {
            if (loading) loading.style.display = 'none';
        }
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', _esc);
    }
    function close(){
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', _esc);
    }
    function _esc(e){ if (e.key === 'Escape') close(); }

    btn.addEventListener('click', open);
    closeBt.addEventListener('click', close);
    if (backBt) backBt.addEventListener('click', close);
    overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
})();
</script>
<?php endif; ?>
