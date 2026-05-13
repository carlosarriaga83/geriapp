<?php
/**
 * GeriApp — Página /billing
 *
 * Una sola página que sirve a los tres roles relevantes:
 *   - admin / superadmin → suscripción de institución, cambio de plan,
 *     compra de asientos extra (familiares y residentes), portal Stripe.
 *   - familiar          → suscripción individual de familiar (asiento extra),
 *     portal Stripe.
 *
 * Política de plataformas:
 *   - Si el navegador es Capacitor (window.Capacitor.isNativePlatform()),
 *     escondemos todos los botones de pago. El usuario verá su estado actual
 *     pero no podrá comprar ni gestionar pagos (cumple Apple §3.1.1 / Google).
 *
 * UI: usa las variables CSS de cuidados.css (--cd-bg, --cd-border, etc.) para
 *     respetar tema claro/oscuro automáticamente.
 */

require_once __DIR__ . '/conf/config.php';
$requiredRoles = ['admin', 'superadmin', 'familiar'];
require_once __DIR__ . '/auth/middleware.php';

$pageTitle = 'Suscripción y Facturación';
$bodyClass = 'billing-page';

$userRole = $_SESSION['user_rol'] ?? '';
$userName = htmlspecialchars($_SESSION['user_nombre'] ?? '');

// ── Detección server-side de Capacitor (fail-closed) ────────────────────
// Apple §3.1.1 / Google Play Payments: en la app móvil NO podemos mostrar
// precios, planes ni botones que enlacen a checkout fuera del IAP/Play Billing.
// Por eso renderizamos un modo "B2B" mínimo cuando el UA es nativo:
//   - Solo plan actual + estado.
//   - Sin precios, sin "Próx. cobro", sin contadores con cuotas, sin
//     "Planes disponibles", sin "Asientos adicionales", sin link a versión web.
// Heurísticas (mismas que head.php):
//   1) Token GeriAppNative / Capacitor en UA (cuando appendUserAgent esté inyectado).
//   2) Query string ?native=1 (lo pasa el drawer del perfil cuando sospecha nativo).
//   3) Android WebView: "; wv)" en UA.
//   4) iOS WKWebView: AppleWebKit + iPhone/iPad + Mobile/ sin Safari/ ni Chrome/Firefox iOS.
$_uaRaw  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_uaLow  = strtolower($_uaRaw);
$_isWv   = strpos($_uaLow, '; wv)') !== false;
$_isWkWv = (
    strpos($_uaLow, 'applewebkit') !== false &&
    (strpos($_uaLow, 'iphone') !== false || strpos($_uaLow, 'ipad') !== false || strpos($_uaLow, 'ipod') !== false) &&
    strpos($_uaLow, 'mobile/') !== false &&
    strpos($_uaLow, 'safari/') === false &&
    strpos($_uaLow, 'crios/') === false &&
    strpos($_uaLow, 'fxios/') === false
);
$IS_NATIVE = (
    !empty($_GET['native']) ||
    ($_uaRaw !== '' && (
        stripos($_uaRaw, 'GeriAppNative')    !== false ||
        stripos($_uaRaw, 'Capacitor')        !== false ||
        stripos($_uaRaw, 'CapacitorWebView') !== false ||
        $_isWv || $_isWkWv
    ))
);
if ($IS_NATIVE) {
    $pageTitle = 'Plan institucional';
}

require_once 'includes/head.php';
?>
<style>
/* Variables locales (no incluimos cuidados.css porque bloquea overflow del body). */
:root {
    --cd-bg: #f5f5f7;
    --cd-surface: #ffffff;
    --cd-border: #d1d5db;
    --cd-text: #1c1c1e;
    --cd-text-muted: #636366;
}
html[data-theme="dark"] {
    --cd-bg: #1c1c1e;
    --cd-surface: #2c2c2e;
    --cd-border: #3a3a3c;
    --cd-text: #f5f5f7;
    --cd-text-muted: #a1a1a6;
}
body.billing-page { background: var(--cd-bg); color: var(--cd-text); min-height: 100vh; }
.bl-wrap     { max-width: 1080px; margin: 0 auto; padding: 24px 16px 80px; color: var(--cd-text, #1c1c1e); }
.bl-h1       { font-size: 28px; font-weight: 700; margin: 0 0 4px; }
.bl-sub      { color: var(--cd-text-muted); margin: 0 0 24px; font-size: 14px; }
.bl-card     { background: var(--cd-surface); border: 1px solid var(--cd-border); border-radius: 12px; padding: 20px; margin-bottom: 18px; color: var(--cd-text); }
.bl-card h2  { font-size: 18px; margin: 0 0 12px; font-weight: 600; }
.bl-card h3  { font-size: 14px; margin: 0 0 8px; font-weight: 600; color: var(--cd-text-muted); text-transform: uppercase; letter-spacing: .04em; }
.bl-extra-family-note { margin: 0 0 18px; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(244,63,94,.26); background: rgba(244,63,94,.08); color: var(--cd-text); }
.bl-extra-family-note strong { display: block; margin-bottom: 4px; font-size: 15px; }
.bl-extra-family-note span { color: var(--cd-text-muted); font-size: 13px; line-height: 1.45; }
.bl-grid     { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.bl-stat     { padding: 12px; border-radius: 8px; background: rgba(127,127,127,.06); }
.bl-stat-label { color: var(--cd-text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
.bl-stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.bl-stat-sub   { font-size: 12px; color: var(--cd-text-muted); margin-top: 2px; }
/* Uso del plan: barras horizontales */
.bl-usage          { display: flex; flex-direction: column; gap: 12px; margin: 4px 0 14px; }
.bl-usage-row      { display: flex; flex-direction: column; gap: 4px; }
.bl-usage-head     { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; font-size: 13px; }
.bl-usage-label    { font-weight: 600; color: var(--cd-text); }
.bl-usage-count    { color: var(--cd-text-muted); font-variant-numeric: tabular-nums; }
.bl-usage-count strong { color: var(--cd-text); font-weight: 700; }
.bl-usage-bar      { position: relative; height: 8px; border-radius: 999px; background: rgba(127,127,127,.18); overflow: hidden; }
.bl-usage-fill     { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: linear-gradient(90deg,#22c55e,#10b981); transition: width .35s ease, background .25s ease; }
.bl-usage-fill[data-state="warn"]   { background: linear-gradient(90deg,#f59e0b,#f97316); }
.bl-usage-fill[data-state="danger"] { background: linear-gradient(90deg,#ef4444,#dc2626); }
.bl-usage-fill[data-state="unlimited"] { background: linear-gradient(90deg,#94a3b8,#64748b); width: 100% !important; opacity:.35; }
.bl-usage-extra    { font-size: 11px; color: var(--cd-text-muted); }
/* Desglose de uso (acordeón) */
.bl-usage-detail-btn { background:none; border:none; color: var(--cd-text-muted); font-size:12px; cursor:pointer; padding:2px 0; display:inline-flex; align-items:center; gap:4px; align-self:flex-start; transition: color .15s; }
.bl-usage-detail-btn:hover { color: var(--cd-text); }
.bl-usage-detail-btn svg { width:12px; height:12px; transition: transform .2s; }
.bl-usage-detail-btn[aria-expanded="true"] svg { transform: rotate(180deg); }
.bl-usage-detail { display:none; margin-top:8px; padding:10px 12px; border-radius:8px; background: rgba(127,127,127,.05); border:1px solid var(--cd-border); font-size:13px; max-height:340px; overflow:auto; }
.bl-usage-detail.is-open { display:block; }
.bl-usage-detail-empty { color: var(--cd-text-muted); font-style: italic; padding:6px 0; }
.bl-usage-detail-loading { color: var(--cd-text-muted); padding:6px 0; }
.bl-usage-inst { margin-bottom:10px; padding-bottom:8px; border-bottom:1px dashed var(--cd-border); }
.bl-usage-inst:last-child { margin-bottom:0; padding-bottom:0; border-bottom:none; }
.bl-usage-inst-title { font-weight:600; color: var(--cd-text); display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
.bl-usage-inst-count { color: var(--cd-text-muted); font-size:11px; font-weight:500; }
.bl-usage-res { padding: 4px 8px; margin: 2px 0; border-radius:6px; background: var(--cd-surface); border:1px solid var(--cd-border); }
.bl-usage-res-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.bl-usage-res-name { font-weight:500; }
.bl-usage-res-meta { color: var(--cd-text-muted); font-size:11px; }
.bl-usage-res-fams { margin-top:4px; padding-left:14px; font-size:12px; color: var(--cd-text-muted); }
.bl-usage-res-fam { display:flex; align-items:center; gap:6px; padding:1px 0; }
.bl-usage-res-fam::before { content:"·"; opacity:.6; }
.bl-usage-fam-loose { padding:3px 8px; margin:2px 0; border-radius:6px; background: rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25); display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
.bl-usage-fam-loose-tag { font-size:10px; color:#b45309; background:rgba(245,158,11,.15); padding:1px 6px; border-radius:4px; }
.bl-badge      { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.bl-b-trial    { background: #e0f2fe; color: #0369a1; }
.bl-b-activa   { background: #dcfce7; color: #15803d; }
.bl-b-pastdue  { background: #fef3c7; color: #b45309; }
.bl-b-cancel,.bl-b-incompleta { background: #fee2e2; color: #b91c1c; }
.bl-btn        { display: inline-block; padding: 10px 18px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: opacity .15s; text-decoration: none; }
.bl-btn-primary{ background: #635bff; color: #fff; }
.bl-btn-secondary{ background: transparent; color: var(--cd-text); border: 1px solid var(--cd-border); }
.bl-btn:hover  { opacity: .9; }
.bl-btn:disabled{ opacity: .5; cursor: not-allowed; }
.bl-btn-row    { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.bl-plans      { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.bl-plan-card  { border: 1px solid var(--cd-border); border-radius: 10px; padding: 16px; background: rgba(127,127,127,.03); position: relative; display: flex; flex-direction: column; }
.bl-plan-card.is-current { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,.15); }
.bl-plan-card.is-quote   { background: linear-gradient(135deg, rgba(99,91,255,.08), rgba(127,127,127,.03)); }
.bl-plan-name  { font-weight: 700; font-size: 16px; margin: 0 0 4px; }
.bl-plan-tier  { font-size: 11px; text-transform: uppercase; color: var(--cd-text-muted); letter-spacing: .05em; }
.bl-plan-price { font-size: 22px; font-weight: 700; margin: 8px 0 4px; }
.bl-plan-price small { font-size: 12px; color: var(--cd-text-muted); font-weight: 400; }
.bl-plan-feats { font-size: 12px; color: var(--cd-text-muted); list-style: none; padding: 0; margin: 8px 0 12px; }
.bl-plan-feats li { padding: 2px 0; }
.bl-plan-feats li::before { content: '✓ '; color: #16a34a; font-weight: 700; }
.bl-plan-card .bl-btn { margin-top: auto; }
.bl-period-toggle { display: inline-flex; border: 1px solid var(--cd-border); border-radius: 8px; overflow: hidden; }
.bl-period-toggle button { background: transparent; color: var(--cd-text); border: none; padding: 6px 14px; cursor: pointer; font-weight: 600; font-size: 12px; }
.bl-period-toggle button.is-active { background: #635bff; color: #fff; }
.bl-currency   { padding: 6px 10px; border-radius: 6px; border: 1px solid var(--cd-border); background: var(--cd-bg); color: var(--cd-text); font-size: 13px; }
.bl-toolbar    { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.bl-warn       { padding: 12px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 13px; margin-bottom: 12px; }
.bl-err        { padding: 12px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-size: 13px; margin-bottom: 12px; }
.bl-ok         { padding: 12px; border-radius: 8px; background: #dcfce7; color: #166534; font-size: 13px; margin-bottom: 12px; }
.bl-native-notice { padding: 14px; border-radius: 8px; background: rgba(99,91,255,.08); color: var(--cd-text); font-size: 13px; border: 1px solid rgba(99,91,255,.3); margin-bottom: 18px; }
.bl-table      { width: 100%; border-collapse: collapse; font-size: 13px; }
.bl-table th, .bl-table td { padding: 8px; text-align: left; border-bottom: 1px solid var(--cd-border); }
.bl-table th   { color: var(--cd-text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; }
.bl-receipt-links { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.bl-mini-link { color:#635bff; font-size:12px; font-weight:600; text-decoration:none; }
.bl-mini-link:hover { text-decoration:underline; }
.bl-spinner    { display: inline-block; width: 14px; height: 14px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: bl-spin .8s linear infinite; vertical-align: middle; }
@keyframes bl-spin { to { transform: rotate(360deg); } }
.bl-input      { width: 100%; padding: 8px 10px; border: 1px solid var(--cd-border); border-radius: 6px; background: var(--cd-bg); color: var(--cd-text); font-size: 14px; box-sizing: border-box; }
.bl-modal-bg   { position: fixed; inset: 0; background: rgba(0,0,0,.55); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
.bl-modal-bg.is-open { display: flex; }
.bl-modal      { background: var(--cd-bg); border-radius: 12px; padding: 22px; max-width: 520px; width: 100%; max-height: 90vh; overflow: auto; border: 1px solid var(--cd-border); }
.bl-form-row   { margin-bottom: 12px; }
.bl-form-row label { display: block; font-size: 12px; color: var(--cd-text-muted); margin-bottom: 4px; font-weight: 600; }
.bl-required { color:#dc2626; font-weight:800; margin-left:3px; }
.bl-back-link  { display: inline-block; color: var(--cd-text-muted); text-decoration: none; font-size: 13px; margin-bottom: 12px; }
.bl-back-link:hover { color: var(--cd-text); }
/* Modal Embedded Checkout (iframe Stripe vive aquí dentro). */
.bl-embed-bg   { position: fixed; inset: 0; background: rgba(0,0,0,.6); display: none; align-items: flex-start; justify-content: center; z-index: 9998; padding: 24px 12px; overflow-y: auto; }
.bl-embed-bg.is-open { display: flex; }
.bl-embed-box  { background: var(--cd-bg); border-radius: 14px; padding: 18px; width: 100%; max-width: 640px; border: 1px solid var(--cd-border); box-shadow: 0 20px 60px rgba(0,0,0,.35); }
.bl-embed-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.bl-embed-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--cd-text); }
.bl-embed-close{ background: transparent; border: none; color: var(--cd-text-muted); font-size: 22px; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 6px; }
.bl-embed-close:hover { background: rgba(127,127,127,.12); color: var(--cd-text); }
.bl-embed-mount{ min-height: 480px; }
.bl-embed-mount .bl-embed-loading { padding: 40px 12px; text-align: center; color: var(--cd-text-muted); font-size: 14px; }
.bl-checkout-wait { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; margin:0 0 18px; border:1px solid rgba(99,91,255,.32); border-radius:10px; background:rgba(99,91,255,.08); color:var(--cd-text); box-shadow:0 8px 24px rgba(15,23,42,.08); }
.bl-checkout-wait strong { display:block; font-size:15px; margin-bottom:3px; }
.bl-checkout-wait span:not(.bl-spinner) { color:var(--cd-text-muted); font-size:13px; line-height:1.45; }
.bl-checkout-wait.is-error { border-color:rgba(239,68,68,.32); background:rgba(239,68,68,.08); }
.bl-checkout-wait.is-error .bl-spinner { display:none; }
</style>

<div class="bl-wrap">
    <a href="<?= BASE_URL ?>/cuidados.php" class="bl-back-link">← Volver al panel</a>
    <h1 class="bl-h1"><?= $IS_NATIVE ? 'Plan institucional' : 'Suscripción y facturación' ?></h1>
    <?php if ($IS_NATIVE): ?>
        <p class="bl-sub">Hola <strong><?= $userName ?></strong>. Aquí puedes ver el estado institucional actual.</p>
    <?php else: ?>
        <p class="bl-sub">Hola <strong><?= $userName ?></strong>. Aquí gestionas tu plan, asientos y método de pago.</p>
    <?php endif; ?>

    <!-- Mensajes de querystring -->
    <?php if (isset($_GET['paid']) && !$IS_NATIVE): ?>
        <div class="bl-ok">✓ Pago recibido. Si tu suscripción no aparece como activa en unos segundos, recarga la página (Stripe nos avisa por webhook).</div>
    <?php endif; ?>
    <?php if (!$IS_NATIVE && isset($_GET['register_checkout'])): ?>
        <div class="bl-checkout-wait" id="blRegisterCheckoutWait" aria-live="polite">
            <span class="bl-spinner" aria-hidden="true"></span>
            <div>
                <strong id="blRegisterCheckoutWaitTitle">Preparando checkout seguro</strong>
                <span id="blRegisterCheckoutWaitText">Estamos verificando tu cuenta y cargando el plan seleccionado. Esto puede tardar unos segundos.</span>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['cancel']) && !$IS_NATIVE): ?>
        <div class="bl-warn">El proceso de pago fue cancelado. Puedes intentarlo de nuevo cuando quieras.</div>
    <?php endif; ?>

    <?php if ($IS_NATIVE): ?>
        <div class="bl-native-notice" style="display:block">
            La gestión administrativa se realiza fuera de la app móvil. Aquí solo puedes ver el estado actual de tu plan institucional.
        </div>
    <?php endif; ?>

    <?php if (!$IS_NATIVE && $userRole === 'familiar' && (!empty($_GET['extra_familiar']) || !empty($_SESSION['familiar_extra_seat_required']))): ?>
        <div class="bl-extra-family-note">
            <strong>Tu invitación requiere un asiento familiar extra</strong>
            <span>El residente ya está vinculado a los familiares incluidos en el paquete. Puedes activar tu acceso con el botón de pago del plan Familiar.</span>
        </div>
    <?php endif; ?>

    <!-- Sección 1: estado actual -->
    <div class="bl-card" id="blCurrent">
        <h2><?= $IS_NATIVE ? 'Estado del plan' : 'Suscripción actual' ?></h2>
        <div id="blCurrentBody" style="color:var(--cd-text-muted)"><span class="bl-spinner"></span> Cargando…</div>
    </div>

    <?php if (!$IS_NATIVE): ?>
    <div class="bl-card" id="blPaymentHistory" style="display:none">
        <h2>Historial de pagos</h2>
        <div id="blPaymentHistoryBody"><span class="bl-spinner"></span> Cargando…</div>
    </div>
    <?php endif; ?>

    <?php if (!$IS_NATIVE): ?>
    <!-- Sección 2: Planes disponibles -->
    <div class="bl-card" id="blPlans">
        <div class="bl-toolbar">
            <h2 style="margin:0">Planes disponibles</h2>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <select id="blCurrency" class="bl-currency">
                    <option value="MXN">MXN</option>
                    <option value="USD">USD</option>
                    <option value="COP">COP</option>
                    <option value="CAD">CAD</option>
                </select>
                <div class="bl-period-toggle" role="tablist">
                    <button id="blPeriodMen" class="is-active" data-p="mensual">Mensual</button>
                    <button id="blPeriodAnu" data-p="anual">Anual <small style="opacity:.7">−2 meses</small></button>
                </div>
            </div>
        </div>
        <div id="blPlansBody"><span class="bl-spinner"></span> Cargando…</div>
    </div>

    <!-- Sección 3: Asientos extra (solo admin) -->
    <div class="bl-card" id="blAddons" style="display:none">
        <h2>Asientos adicionales</h2>
        <p class="bl-sub" style="margin-bottom:12px">Si tu plan se queda corto, compra asientos extra a la carta. Se prorratean al ciclo actual.</p>
        <div id="blAddonsBody"></div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal cotización (solo web; Apple/Google no permiten captar leads de pago en la app) -->
<?php if (!$IS_NATIVE): ?>
<div class="bl-modal-bg" id="blQuoteBg" role="dialog" aria-modal="true">
    <div class="bl-modal">
        <h2 style="margin:0 0 12px">Solicitar cotización</h2>
        <p class="bl-sub">El plan Empresarial 7 se cotiza a la medida según tu institución. Llena tus datos y te contactamos.</p>
        <form id="blQuoteForm">
            <input type="hidden" name="plan_id" id="qPlanId">
            <div class="bl-form-row"><label>Nombre completo <span class="bl-required">*</span></label><input class="bl-input" name="nombre_contacto" id="qNombre" required value="<?= htmlspecialchars($_SESSION['user_nombre'] ?? '') ?>"></div>
            <div class="bl-form-row"><label>Email <span class="bl-required">*</span></label><input class="bl-input" type="email" name="email" id="qEmail" required value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>"></div>
            <div class="bl-form-row"><label>Teléfono</label><input class="bl-input" name="telefono" id="qTel"></div>
            <div class="bl-form-row"><label>Nombre de la institución</label><input class="bl-input" name="institucion_nombre" id="qInst"></div>
            <div class="bl-form-row"><label># residentes aprox.</label><input class="bl-input" type="number" min="0" name="num_residentes" id="qRes"></div>
            <div class="bl-form-row"><label>Mensaje (opcional)</label><textarea class="bl-input" rows="3" name="mensaje" id="qMsg"></textarea></div>
            <div class="bl-btn-row" style="justify-content:flex-end">
                <button type="button" class="bl-btn bl-btn-secondary" onclick="closeQuote()">Cancelar</button>
                <button type="submit" class="bl-btn bl-btn-primary" id="qSubmit">Enviar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Embedded Checkout (Stripe inline) — sólo web -->
<?php if (!$IS_NATIVE): ?>
<div class="bl-embed-bg" id="blEmbedBg" role="dialog" aria-modal="true" aria-labelledby="blEmbedTitle">
    <div class="bl-embed-box">
        <div class="bl-embed-head">
            <h2 id="blEmbedTitle">Completa tu suscripción</h2>
            <button type="button" class="bl-embed-close" id="blEmbedClose" aria-label="Cerrar">×</button>
        </div>
        <div id="blEmbedMount" class="bl-embed-mount">
            <div class="bl-embed-loading"><span class="bl-spinner"></span> Preparando pago seguro…</div>
        </div>
    </div>
</div>

<!-- Stripe.js (necesario para Embedded Checkout) -->
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
(function() {
    'use strict';

    // ── Detección Capacitor (server-side UA + JS bridge) ───────────────
    // El servidor ya ocultó las secciones de planes/addons si es nativo.
    // Aquí marcamos IS_NATIVE para que la JS de renderCurrent omita "Próx.
    // cobro" y los contadores con cuotas (Apple §3.1.1 / Google Play).
    const IS_NATIVE = <?= $IS_NATIVE ? 'true' : 'false' ?> ||
        !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());

    // ── Estado ──────────────────────────────────────────────────────────
    const ROLE = <?= json_encode($userRole) ?>;
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const URL_PARAMS = new URLSearchParams(window.location.search);
    const AUTO_CHECKOUT_PLAN_ID = URL_PARAMS.has('register_checkout') ? parseInt(URL_PARAMS.get('plan_id') || '0', 10) : 0;
    const AUTO_CHECKOUT_INST_ID = URL_PARAMS.has('register_checkout') ? parseInt(URL_PARAMS.get('institucion_id') || '0', 10) : 0;
    const APP_HOME_AFTER_PAYMENT = '<?= BASE_URL ?>/cuidados.php?billing=paid';
    const REG_CHECKOUT_WAIT = document.getElementById('blRegisterCheckoutWait');

    function setRegistrationCheckoutWait(message, type = 'loading', title = 'Preparando checkout seguro') {
        if (!REG_CHECKOUT_WAIT) return;
        REG_CHECKOUT_WAIT.style.display = 'flex';
        REG_CHECKOUT_WAIT.classList.toggle('is-error', type === 'error');
        const titleEl = document.getElementById('blRegisterCheckoutWaitTitle');
        const textEl = document.getElementById('blRegisterCheckoutWaitText');
        if (titleEl) titleEl.textContent = title;
        if (textEl) textEl.textContent = message;
    }

    function hideRegistrationCheckoutWait() {
        if (REG_CHECKOUT_WAIT) REG_CHECKOUT_WAIT.style.display = 'none';
    }
    let _state = { sub: null, plans: null, addons: null, payments: [], institutionTrial: null, currency: 'MXN', period: 'mensual' };
    let _autoCheckoutStarted = false;

    function goToAppHomeAfterPayment() {
        if (window.top && window.top !== window.self) {
            window.top.location.href = APP_HOME_AFTER_PAYMENT;
            return;
        }
        window.location.replace(APP_HOME_AFTER_PAYMENT);
    }

    const fmt = (n, currency) => new Intl.NumberFormat(currency==='MXN'?'es-MX':(currency==='COP'?'es-CO':'en-US'), { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function parseAppDate(val) {
        if (!val) return null;
        if (typeof val === 'number') return new Date(val * 1000);
        const raw = String(val);
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            const parts = raw.split('-').map(Number);
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }
        return new Date(raw.replace(' ', 'T'));
    }
    function fmtDate(val) {
        const d = parseAppDate(val);
        if (!d || isNaN(d)) return val ? String(val) : '';
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}-${month}-${year}`;
    }
    function fmtDateTime(val) {
        const d = parseAppDate(val);
        if (!d || isNaN(d)) return val ? String(val) : '';
        const time = [d.getHours(), d.getMinutes()].map(x => String(x).padStart(2, '0')).join(':');
        return `${fmtDate(val)} ${time}`;
    }

    async function api(action, body, method) {
        method = method || (body ? 'POST' : 'GET');
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            credentials: 'same-origin',
        };
        if (IS_NATIVE) opts.headers['X-Capacitor-Native'] = '1';
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        const url = '<?= BASE_URL ?>/api/billing.php?action=' + encodeURIComponent(action);
        const r = await fetch(url, opts);
        const j = await r.json().catch(() => ({ success: false, message: 'Respuesta no JSON' }));
        if (!j.success) throw new Error(j.message || 'Error');
        return j.data;
    }

    // ── Status ──────────────────────────────────────────────────────────
    function badge(estado) {
        const map = {
            trial:    ['bl-b-trial',    'En prueba'],
            activa:   ['bl-b-activa',   'Activa'],
            past_due: ['bl-b-pastdue',  'Pago pendiente'],
            cancelada:['bl-b-cancel',   'Cancelada'],
            pausada:  ['bl-b-cancel',   'Pausada'],
            incompleta:['bl-b-incompleta','Incompleta'],
        };
        const [cls, label] = map[estado] || ['', estado];
        return `<span class="bl-badge ${cls}">${esc(label)}</span>`;
    }

    // ── Usage bar helper ────────────────────────────────────────────────
    // Renderiza una barra horizontal de uso. `extra` se suma al `max` para
    // reflejar asientos extra comprados. Si `max` es null → "Ilimitado".
    function usageBar(label, used, max, extra, breakdownKey) {
        const u = Math.max(0, Number(used) || 0);
        const x = Math.max(0, Number(extra) || 0);
        const isUnlimited = max === null || max === undefined || Number(max) <= 0;
        const total = isUnlimited ? null : (Math.max(0, Number(max) || 0) + x);
        let pct = 0, state = 'ok';
        if (!isUnlimited && total > 0) {
            pct = Math.min(100, Math.round((u / total) * 100));
            if (pct >= 90) state = 'danger';
            else if (pct >= 70) state = 'warn';
        }
        const right = isUnlimited
            ? `<span class="bl-usage-count"><strong>${u}</strong> / Ilimitado</span>`
            : `<span class="bl-usage-count"><strong>${u}</strong> / ${total} <span style="opacity:.7">(${pct}%)</span></span>`;
        const extraNote = (!isUnlimited && x > 0)
            ? `<div class="bl-usage-extra">Incluye +${x} ${x===1?'asiento extra':'asientos extra'}</div>` : '';
        const fillStyle = isUnlimited ? '' : `style="width:${pct}%"`;
        const fillState = isUnlimited ? 'unlimited' : state;
        const detailBtn = breakdownKey ? `
                <button type="button" class="bl-usage-detail-btn" data-breakdown="${breakdownKey}" aria-expanded="false">
                    Ver desglose
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="bl-usage-detail" data-breakdown-panel="${breakdownKey}"></div>` : '';
        return `
            <div class="bl-usage-row">
                <div class="bl-usage-head">
                    <span class="bl-usage-label">${esc(label)}</span>
                    ${right}
                </div>
                <div class="bl-usage-bar">
                    <div class="bl-usage-fill" data-state="${fillState}" ${fillStyle}></div>
                </div>
                ${extraNote}
                ${detailBtn}
            </div>`;
    }

    // Cache del breakdown (una sola request)
    let _breakdownPromise = null;
    function loadBreakdown() {
        if (!_breakdownPromise) _breakdownPromise = api('usage_breakdown').catch(() => ({ instituciones: [] }));
        return _breakdownPromise;
    }

    function renderBreakdown(kind, data) {
        const insts = (data && data.instituciones) || [];
        if (!insts.length) return `<div class="bl-usage-detail-empty">Sin datos para mostrar.</div>`;
        if (kind === 'instituciones') {
            return insts.map(i => `
                <div class="bl-usage-inst" style="margin:0;padding:6px 0;border-bottom:1px solid var(--cd-border)">
                    <div class="bl-usage-inst-title">
                        <span>${esc(i.nombre)}</span>
                        <span class="bl-usage-inst-count">${i.residentes_count} res · ${i.familiares_count} fam</span>
                    </div>
                </div>`).join('');
        }
        if (kind === 'residentes') {
            return insts.map(i => {
                const rs = (i.residentes || []);
                if (!rs.length) return `
                    <div class="bl-usage-inst">
                        <div class="bl-usage-inst-title"><span>${esc(i.nombre)}</span><span class="bl-usage-inst-count">0</span></div>
                        <div class="bl-usage-detail-empty">Sin residentes activos.</div>
                    </div>`;
                return `
                <div class="bl-usage-inst">
                    <div class="bl-usage-inst-title">
                        <span>${esc(i.nombre)}</span>
                        <span class="bl-usage-inst-count">${rs.length} ${rs.length===1?'residente':'residentes'}</span>
                    </div>
                    ${rs.map(r => `
                        <div class="bl-usage-res">
                            <div class="bl-usage-res-head">
                                <span class="bl-usage-res-name">${esc((r.nombre||'') + ' ' + (r.apellidos||''))}</span>
                                <span class="bl-usage-res-meta">${r.habitacion ? 'Hab. ' + esc(r.habitacion) : ''}${r.familiares_count ? ` · ${r.familiares_count} fam` : ''}</span>
                            </div>
                        </div>`).join('')}
                </div>`;
            }).join('');
        }
        if (kind === 'admin' || kind === 'cuidador' || kind === 'medico' || kind === 'personal') {
            const role = kind;
            const labelSing = role === 'admin' ? 'admin' : (role === 'cuidador' ? 'cuidador' : (role === 'medico' ? 'médico' : 'persona'));
            const labelPlur = role === 'admin' ? 'admins' : (role === 'cuidador' ? 'cuidadores' : (role === 'medico' ? 'médicos' : 'personas'));
            return insts.map(i => {
                const ps = (role === 'personal') ? (i.personal || []) : (i[role] || []);
                if (!ps.length) return `
                    <div class="bl-usage-inst">
                        <div class="bl-usage-inst-title"><span>${esc(i.nombre)}</span><span class="bl-usage-inst-count">0</span></div>
                        <div class="bl-usage-detail-empty">Sin ${labelPlur} en esta institución.</div>
                    </div>`;
                const rolBadge = (rol) => {
                    const map = { admin:'#6366f1', medico:'#16a34a', enfermero:'#178391', cuidador:'#178391' };
                    const fg = map[rol] || '#6b7280';
                    return `<span style="display:inline-block;padding:1px 6px;border-radius:999px;font-size:.65rem;font-weight:600;color:${fg};background:${fg}1f;border:1px solid ${fg}55;margin-left:6px;text-transform:capitalize">${esc(rol||'—')}</span>`;
                };
                return `
                <div class="bl-usage-inst">
                    <div class="bl-usage-inst-title">
                        <span>${esc(i.nombre)}</span>
                        <span class="bl-usage-inst-count">${ps.length} ${ps.length===1?labelSing:labelPlur}</span>
                    </div>
                    ${ps.map(p => `
                        <div class="bl-usage-res">
                            <div class="bl-usage-res-head">
                                <span class="bl-usage-res-name">${esc(p.nombre || '?')}${rolBadge(p.rol_ui || p.rol)}</span>
                                <span class="bl-usage-res-meta">${p.email ? esc(p.email) : ''}</span>
                            </div>
                        </div>`).join('')}
                </div>`;
            }).join('');
        }
        if (kind === 'familiares') {
            return insts.map(i => {
                const rs = (i.residentes || []).filter(r => (r.familiares||[]).length);
                const sueltos = i.familiares_sueltos || [];
                if (!rs.length && !sueltos.length) return `
                    <div class="bl-usage-inst">
                        <div class="bl-usage-inst-title"><span>${esc(i.nombre)}</span><span class="bl-usage-inst-count">0</span></div>
                        <div class="bl-usage-detail-empty">Sin familiares vinculados.</div>
                    </div>`;
                return `
                <div class="bl-usage-inst">
                    <div class="bl-usage-inst-title">
                        <span>${esc(i.nombre)}</span>
                        <span class="bl-usage-inst-count">${i.familiares_count} ${i.familiares_count===1?'familiar':'familiares'}</span>
                    </div>
                    ${rs.map(r => `
                        <div class="bl-usage-res">
                            <div class="bl-usage-res-head">
                                <span class="bl-usage-res-name">${esc((r.nombre||'') + ' ' + (r.apellidos||''))}</span>
                                <span class="bl-usage-res-meta">${r.habitacion ? 'Hab. ' + esc(r.habitacion) : ''}</span>
                            </div>
                            <div class="bl-usage-res-fams">
                                ${r.familiares.map(f => `<div class="bl-usage-res-fam">${esc(f.nombre || '?')}${f.email ? ` <span style="opacity:.7">— ${esc(f.email)}</span>` : ''}</div>`).join('')}
                            </div>
                        </div>`).join('')}
                    ${sueltos.map(f => `
                        <div class="bl-usage-fam-loose">
                            <span>${esc(f.nombre||'?')}${f.email ? ` <span style="opacity:.7">— ${esc(f.email)}</span>` : ''}</span>
                            <span class="bl-usage-fam-loose-tag">Sin residente</span>
                        </div>`).join('')}
                </div>`;
            }).join('');
        }
        return '';
    }

    function bindBreakdownToggles(rootEl) {
        rootEl.querySelectorAll('.bl-usage-detail-btn').forEach(btn => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', async () => {
                const key = btn.dataset.breakdown;
                const panel = rootEl.querySelector(`[data-breakdown-panel="${key}"]`);
                if (!panel) return;
                const isOpen = panel.classList.contains('is-open');
                if (isOpen) {
                    panel.classList.remove('is-open');
                    btn.setAttribute('aria-expanded', 'false');
                    return;
                }
                btn.setAttribute('aria-expanded', 'true');
                panel.classList.add('is-open');
                if (!panel.dataset.loaded) {
                    panel.innerHTML = `<div class="bl-usage-detail-loading">Cargando…</div>`;
                    try {
                        const data = await loadBreakdown();
                        panel.innerHTML = renderBreakdown(key, data);
                        panel.dataset.loaded = '1';
                    } catch (e) {
                        panel.innerHTML = `<div class="bl-usage-detail-empty">Error al cargar el desglose.</div>`;
                    }
                }
            });
        });
    }

    function renderCurrent() {
        const el = document.getElementById('blCurrentBody');
        const sub = _state.sub;
        if (!sub) {
            const instTrial = _state.institutionTrial;
            const trialLine = instTrial && instTrial.trial_ends_at && !IS_NATIVE
                ? `<p class="bl-sub" style="margin:0">La institución <strong>${esc(instTrial.nombre || '')}</strong> está en prueba hasta el <strong>${fmtDate(instTrial.trial_ends_at)}</strong>.</p>`
                : `<p class="bl-sub" style="margin:0">Elige un plan abajo para iniciar la prueba configurada para ese paquete.</p>`;
            el.innerHTML = IS_NATIVE ? `
                <p style="margin:0 0 8px">No hay un plan institucional activo en este momento.</p>
                <p class="bl-sub" style="margin:0">Contacta al administrador de tu institución para que active tu plan.</p>` : `
                <p style="margin:0 0 8px">No tienes suscripción activa.</p>
                ${trialLine}`;
            return;
        }
        const lim = sub.plan_limits || {};
        const showSeats = (ROLE === 'admin' || ROLE === 'superadmin') && !IS_NATIVE;
        const trialMsg = (sub.estado === 'trial' && sub.trial_ends_at && !IS_NATIVE)
            ? `<p class="bl-sub" style="margin:6px 0 0">Tu prueba termina el <strong>${fmtDate(sub.trial_ends_at)}</strong>. Agrega un método de pago para no perder acceso.</p>` : '';
        const cancelMsg = (sub.cancel_at_period_end && !IS_NATIVE)
            ? `<div class="bl-warn">Tu suscripción se cancelará al final del periodo (${sub.periodo_fin ? fmtDate(sub.periodo_fin) : '—'}).</div>` : '';
        const isFamiliar = sub.kind === 'familiar';
        const planLine = isFamiliar
            ? `<strong>${esc(sub.addon_nombre || 'Asiento familiar')}</strong>`
            : `<strong>${esc(sub.plan_nombre || '—')}</strong> <span class="bl-plan-tier">(${esc(sub.plan_tier || '')})</span>`;
        // En modo nativo: ocultamos "Próx. cobro", moneda/periodo (insinúa precio)
        // y contadores con cuotas (insinúan compra de asientos extra).
        const planSubLine = IS_NATIVE ? '' : `<div class="bl-stat-sub">${esc(sub.moneda)} · ${esc(sub.periodo)}</div>`;
        const lastChargeLine = (sub.ultimo_cobro && !IS_NATIVE)
            ? `<div class="bl-stat-sub">Último cobro: ${fmt(Number(sub.ultimo_cobro.monto || 0), sub.ultimo_cobro.moneda || sub.moneda)} · ${fmtDate(sub.ultimo_cobro.recibido_at)}</div>`
            : '';
        const periodoFinLine = (sub.periodo_fin && !IS_NATIVE)
            ? `<div class="bl-stat-sub">Próx. cobro: ${fmtDate(sub.periodo_fin)}</div>` : '';
        el.innerHTML = `
            ${cancelMsg}
            <div class="bl-grid" style="margin-bottom:14px">
                <div class="bl-stat">
                    <div class="bl-stat-label">Plan</div>
                    <div class="bl-stat-value" style="font-size:18px">${planLine}</div>
                    ${planSubLine}
                </div>
                <div class="bl-stat">
                    <div class="bl-stat-label">Estado</div>
                    <div class="bl-stat-value" style="font-size:18px">${badge(sub.estado)}</div>
                    ${lastChargeLine}
                    ${periodoFinLine}
                </div>
            </div>
            ${showSeats && _state.usage ? `
            <div class="bl-stat-label" style="margin:14px 0 8px">Uso del plan</div>
            <div class="bl-usage">
                ${usageBar('Residentes', _state.usage.residentes, lim.max_residentes, sub.seats_residente_extra, 'residentes')}
                ${usageBar('Familiares', _state.usage.familiares, lim.max_familiares, sub.seats_familiar_extra, 'familiares')}
                ${usageBar('Personal admin', _state.usage.admin || 0, lim.max_admin, 0, 'admin')}
                ${usageBar('Personal médico', _state.usage.medico || 0, lim.max_medico, 0, 'medico')}
                ${usageBar('Cuidadores', _state.usage.cuidador || 0, lim.max_cuidador, 0, 'cuidador')}
                ${lim.max_instituciones != null ? usageBar('Instituciones', (_state.instituciones||[]).length, lim.max_instituciones, 0, 'instituciones') : ''}
            </div>` : ''}
            ${trialMsg}
            ${!IS_NATIVE && sub.has_customer ? `
                <div class="bl-btn-row">
                    <button class="bl-btn bl-btn-primary" id="btnPortal">Gestionar pago / facturas</button>
                </div>` : ''}
        `;
        document.getElementById('btnPortal')?.addEventListener('click', openPortal);
        bindBreakdownToggles(el);
    }

    function paymentLabel(tipo, status) {
        if (tipo === 'invoice.payment_failed') return 'Pago fallido';
        if (tipo === 'checkout.session.completed') return 'Checkout completado';
        if (tipo === 'invoice.paid' || tipo === 'invoice.payment_succeeded') return 'Pago recibido';
        return status || tipo || 'Evento';
    }

    function receiptLinks(payment) {
        const links = [];
        if (payment.recibo_url) links.push(`<a class="bl-mini-link" href="${esc(payment.recibo_url)}" target="_blank" rel="noopener noreferrer">Ver</a>`);
        if (payment.recibo_pdf_url) links.push(`<a class="bl-mini-link" href="${esc(payment.recibo_pdf_url)}" target="_blank" rel="noopener noreferrer" download>Descargar</a>`);
        return links.length ? `<div class="bl-receipt-links">${links.join('')}</div>` : '<span class="bl-sub">—</span>';
    }

    function renderPaymentHistory() {
        const wrap = document.getElementById('blPaymentHistory');
        const body = document.getElementById('blPaymentHistoryBody');
        if (!wrap || !body || IS_NATIVE) return;
        const rows = _state.payments || [];
        wrap.style.display = '';
        if (!rows.length) {
            body.innerHTML = '<p class="bl-sub" style="margin:0">Aún no hay pagos registrados para esta suscripción.</p>';
            return;
        }
        body.innerHTML = `
            <table class="bl-table">
                <thead><tr><th>Fecha</th><th>Evento</th><th>Monto</th><th>Estado</th><th>Recibo</th></tr></thead>
                <tbody>
                    ${rows.map(p => {
                        const amount = p.monto != null ? fmt(Number(p.monto), p.moneda || _state.currency) : '—';
                        return `<tr>
                            <td>${p.recibido_at ? fmtDateTime(p.recibido_at) : '—'}</td>
                            <td>${esc(paymentLabel(p.tipo, p.status))}${p.stripe_invoice_id ? `<br><span style="font-size:11px;color:var(--cd-text-muted)">${esc(p.stripe_invoice_id)}</span>` : ''}</td>
                            <td>${amount}</td>
                            <td>${esc(p.status || '—')}</td>
                            <td>${receiptLinks(p)}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>`;
    }

    function renderInstituciones() {
        const list = _state.instituciones || [];
        if (!list.length) return '';
        return `
            <div class="bl-card" style="margin-top:0">
                <h3>Instituciones cubiertas</h3>
                <table class="bl-table">
                    <tbody>
                        ${list.map(i => `<tr><td>${esc(i.nombre)}</td><td>${badge(i.estado === 'activa' ? 'activa' : i.estado)}</td></tr>`).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    // ── Planes ──────────────────────────────────────────────────────────
    function planFeats(p) {
        const f = [];
        if (p.max_instituciones != null) f.push(`Hasta ${p.max_instituciones} institución${p.max_instituciones>1?'es':''}`);
        if (p.max_residentes   != null) f.push(`Hasta ${p.max_residentes} residentes`);
        if (p.max_familiares   != null) f.push(`Hasta ${p.max_familiares} familiares`);
        if (Number(p.trial_dias || 0) > 0 && Number(p.requiere_cotizacion) !== 1) f.push(`${Number(p.trial_dias)} días de prueba`);
        if (Number(p.solicita_tarjeta_registro || 0) === 1 && Number(p.requiere_cotizacion) !== 1) f.push('Solicita tarjeta al registrarse');
        f.push('Admins, cuidadores y médicos sin límite');
        (_state.addons || []).forEach(addon => {
            const scoped = (p.addon_precios || []).filter(x => Number(x.addon_id) === Number(addon.id));
            const pr = priceFor(scoped, _state.currency, _state.period);
            if (!pr) return;
            const tipoLbl = addon.tipo === 'asiento_familiar' ? 'Familiar extra' : (addon.tipo === 'asiento_residente' ? 'Residente extra' : addon.nombre);
            f.push(`${tipoLbl}: ${fmt(pr.precio, _state.currency)} / ${_state.period === 'anual' ? 'año' : 'mes'}`);
        });
        return f.map(x => `<li>${esc(x)}</li>`).join('');
    }

    function priceFor(precios, currency, period) {
        return (precios || []).find(x => x.moneda === currency && x.periodo === period && Number(x.activo) === 1);
    }

    function renderPlans() {
        const el = document.getElementById('blPlansBody');
        const planes = (_state.plans || []).filter(p => {
            // Familiar no contrata planes de admin
            if (ROLE === 'familiar') return false;
            return true;
        });
        if (ROLE === 'familiar') {
            // Para familiar mostramos el addon_familiar como "plan"
            const addon = (_state.addons || []).find(a => a.tipo === 'asiento_familiar');
            if (!addon) { el.innerHTML = '<p class="bl-sub">No hay planes disponibles.</p>'; return; }
            const pr = priceFor(addon.precios, _state.currency, _state.period);
            el.innerHTML = `
                <div class="bl-plans">
                    <div class="bl-plan-card ${_state.sub ? 'is-current':''}">
                        <div>
                            <div class="bl-plan-tier">Familiar</div>
                            <h3 class="bl-plan-name" style="margin:4px 0">${esc(addon.nombre)}</h3>
                            <p class="bl-sub" style="margin:0 0 8px">${esc(addon.descripcion || 'Acceso completo como familiar.')}</p>
                            <div class="bl-plan-price">${pr ? fmt(pr.precio, _state.currency) : '—'}<small> / ${_state.period === 'anual' ? 'año' : 'mes'}</small></div>
                        </div>
                        ${IS_NATIVE ? '' : `<button class="bl-btn bl-btn-primary" ${pr ? '' : 'disabled'} onclick="checkout('familiar',${addon.id})">${_state.sub ? 'Renovar' : 'Suscribirme'}</button>`}
                    </div>
                </div>`;
            return;
        }
        if (!planes.length) { el.innerHTML = '<p class="bl-sub">No hay planes disponibles. Contacta al administrador.</p>'; return; }

        el.innerHTML = `<div class="bl-plans">` + planes.map(p => {
            const pr = priceFor(p.precios, _state.currency, _state.period);
            const isCurrent = _state.sub && _state.sub.plan_id === p.id;
            const isQuote   = Number(p.requiere_cotizacion) === 1;
            const cardCls   = `bl-plan-card${isCurrent?' is-current':''}${isQuote?' is-quote':''}`;
            let priceHtml;
            if (isQuote) {
                priceHtml = `<div class="bl-plan-price">A medida<small> · contáctanos</small></div>`;
            } else if (pr) {
                priceHtml = `<div class="bl-plan-price">${fmt(pr.precio, _state.currency)}<small> / ${_state.period==='anual'?'año':'mes'}</small></div>`;
            } else {
                priceHtml = `<div class="bl-plan-price">—<small> sin precio en ${_state.currency}</small></div>`;
            }
            let btnHtml;
            if (IS_NATIVE) btnHtml = '';
            else if (isCurrent) btnHtml = `<button class="bl-btn bl-btn-secondary" disabled>Plan actual</button>`;
            else if (isQuote) btnHtml = `<button class="bl-btn bl-btn-primary" onclick="openQuote(${p.id})">Solicitar cotización</button>`;
            else {
                const trialDays = Number(p.trial_dias || 0);
                const label = _state.sub ? 'Cambiar a este plan' : (trialDays > 0 ? 'Iniciar prueba' : 'Contratar');
                btnHtml = `<button class="bl-btn bl-btn-primary" ${pr ? '' : 'disabled'} onclick="checkout('admin',${p.id})">${label}</button>`;
            }

            return `
                <div class="${cardCls}">
                    <div class="bl-plan-tier">${esc(p.tier)}</div>
                    <h3 class="bl-plan-name">${esc(p.nombre)}</h3>
                    <p class="bl-sub" style="margin:0 0 4px;font-size:12px">${esc(p.descripcion || '')}</p>
                    ${priceHtml}
                    <ul class="bl-plan-feats">${planFeats(p)}</ul>
                    ${btnHtml}
                </div>`;
        }).join('') + `</div>`;
    }

    // ── Add-ons ─────────────────────────────────────────────────────────
    function renderAddons() {
        const wrap = document.getElementById('blAddons');
        if (ROLE === 'familiar' || !(_state.addons || []).length) { wrap.style.display = 'none'; return; }
        if (!_state.sub) { wrap.style.display = 'none'; return; }   // sin sub no aplica seats
        wrap.style.display = '';
        const body = document.getElementById('blAddonsBody');
        body.innerHTML = `
            <table class="bl-table">
                <thead><tr><th>Tipo</th><th>Descripción</th><th>Precio</th><th></th></tr></thead>
                <tbody>
                    ${_state.addons.map(a => {
                        const pr = priceFor(a.precios, _state.currency, _state.period);
                        const tipoLbl = a.tipo === 'asiento_familiar' ? 'Familiar extra' : (a.tipo === 'asiento_residente' ? 'Residente extra' : a.tipo);
                        const priceSource = pr?.source === 'plan' ? 'Para tu paquete' : (pr ? 'Precio global' : 'Sin precio');
                        return `<tr>
                            <td><strong>${esc(tipoLbl)}</strong></td>
                            <td style="color:var(--cd-text-muted)">${esc(a.descripcion || a.nombre)}</td>
                            <td>${pr ? `${fmt(pr.precio, _state.currency)} / ${_state.period==='anual'?'año':'mes'}<br><span style="font-size:11px;color:var(--cd-text-muted)">${priceSource}</span>` : '—'}</td>
                            <td>${IS_NATIVE ? '' : `<button class="bl-btn bl-btn-secondary" ${pr ? '' : 'disabled'} onclick="checkout('seat',${a.id})">Comprar</button>`}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>`;
    }

    // ── Acciones ────────────────────────────────────────────────────────
    let _stripe = null;            // instancia Stripe.js (lazy)
    let _embeddedCheckout = null;  // instancia activa para poder destruirla

    function getStripe() {
        if (_stripe) return _stripe;
        const pk = _state.publishable_key;
        if (!pk) throw new Error('Falta publishable_key (revisa secretos/stripe.json).');
        if (!window.Stripe) throw new Error('Stripe.js no cargó. Verifica tu conexión.');
        _stripe = window.Stripe(pk);
        return _stripe;
    }

    function closeEmbed() {
        const bg = document.getElementById('blEmbedBg');
        bg.classList.remove('is-open');
        if (_embeddedCheckout) {
            try { _embeddedCheckout.destroy(); } catch(_) {}
            _embeddedCheckout = null;
        }
        document.getElementById('blEmbedMount').innerHTML =
            '<div class="bl-embed-loading"><span class="bl-spinner"></span> Preparando pago seguro…</div>';
    }
    document.getElementById('blEmbedClose').addEventListener('click', closeEmbed);
    document.getElementById('blEmbedBg').addEventListener('click', e => {
        if (e.target.id === 'blEmbedBg') closeEmbed();
    });

    window.checkout = async function(kind, id, opts = {}) {
        try {
            const body = { kind, moneda: _state.currency, periodo: _state.period };
            if (kind === 'admin')   body.plan_id  = id;
            else if (kind === 'familiar' || kind === 'seat') body.addon_id = id;
            if (opts.institucionId) body.institucion_id = opts.institucionId;
            // Abrir modal con loader antes del round-trip
            hideRegistrationCheckoutWait();
            document.getElementById('blEmbedBg').classList.add('is-open');
            const r = await api('checkout', body);
            if (!r || !r.client_secret) throw new Error('Stripe no devolvió client_secret');
            // Override de publishable_key por si el server cambió de modo test/live
            if (r.publishable_key) { _state.publishable_key = r.publishable_key; _stripe = null; }
            const stripe = getStripe();
            const mount = document.getElementById('blEmbedMount');
            mount.innerHTML = '';   // limpiar loader
            _embeddedCheckout = await stripe.initEmbeddedCheckout({
                clientSecret: r.client_secret,
                onComplete: async () => {
                    // Stripe ya cobró. No esperamos al webhook: pedimos al server
                    // que consulte Stripe directamente y actualice la BD para que
                    // el usuario vea "activa" inmediatamente al recargar.
                    try { await api('sync', { checkout_session_id: r.id }); } catch (_) { /* fallback al webhook */ }
                    goToAppHomeAfterPayment();
                }
            });
            _embeddedCheckout.mount('#blEmbedMount');
        } catch (e) {
            closeEmbed();
            alert(e.message);
        }
    };

    function maybeStartRegistrationCheckout() {
        if (_autoCheckoutStarted || !AUTO_CHECKOUT_PLAN_ID || IS_NATIVE || ROLE === 'familiar') return;
        const plan = (_state.plans || []).find(p => Number(p.id) === AUTO_CHECKOUT_PLAN_ID);
        if (!plan) {
            setRegistrationCheckoutWait('No encontramos el plan seleccionado. Puedes elegir un plan disponible abajo.', 'error', 'No se pudo abrir Stripe');
            return;
        }
        if (Number(plan.requiere_cotizacion) === 1) {
            setRegistrationCheckoutWait('Este plan requiere cotización. Solicita una cotización para continuar.', 'error', 'No se pudo abrir Stripe');
            return;
        }
        const price = priceFor(plan.precios, _state.currency, _state.period);
        if (!price) {
            setRegistrationCheckoutWait('No encontramos precio para la moneda y periodo actuales. Cambia la moneda o periodo para continuar.', 'error', 'No se pudo abrir Stripe');
            return;
        }
        _autoCheckoutStarted = true;
        setRegistrationCheckoutWait('Listo. Abriendo Stripe para completar tu suscripción...', 'loading');
        window.checkout('admin', AUTO_CHECKOUT_PLAN_ID, { institucionId: AUTO_CHECKOUT_INST_ID });
    }

    async function openPortal() {
        const btn = document.getElementById('btnPortal');
        if (btn) btn.disabled = true;
        try {
            const r = await api('portal', { return_url: window.location.href });
            if (r && r.url) window.location.href = r.url;
        } catch (e) { alert(e.message); if (btn) btn.disabled = false; }
    }

    // ── Cotización modal (solo web) ─────────────────────────────────────
    if (!IS_NATIVE) {
        window.openQuote = function(planId) {
            document.getElementById('qPlanId').value = planId;
            document.getElementById('blQuoteBg').classList.add('is-open');
        };
        window.closeQuote = function() {
            document.getElementById('blQuoteBg').classList.remove('is-open');
        };
        document.getElementById('blQuoteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('qSubmit');
            btn.disabled = true; btn.textContent = 'Enviando…';
            try {
                const fd = new FormData(e.target);
                const body = Object.fromEntries(fd.entries());
                await api('cotizacion', body);
                alert('Cotización enviada. Te contactaremos pronto.');
                closeQuote();
            } catch (err) { alert(err.message); }
            finally { btn.disabled = false; btn.textContent = 'Enviar'; }
        });
    }

    // ── Toggle moneda/periodo (solo web; en nativo el bloque de planes no existe) ──
    if (!IS_NATIVE) {
        document.getElementById('blCurrency').addEventListener('change', e => { _state.currency = e.target.value; renderPlans(); renderAddons(); });
        document.querySelectorAll('.bl-period-toggle button').forEach(b => {
            b.addEventListener('click', () => {
                document.querySelectorAll('.bl-period-toggle button').forEach(x => x.classList.remove('is-active'));
                b.classList.add('is-active');
                _state.period = b.dataset.p;
                renderPlans(); renderAddons();
            });
        });
    }

    // ── Bootstrap ───────────────────────────────────────────────────────
    (async function init() {        // Si venimos de Stripe (?paid=1), pedimos sync ANTES del primer status
        // por si webhook aún no llegó. Tolerante a fallos: si rompe, status
        // mostrará lo que haya y el webhook eventualmente lo arreglará.
        const params = new URLSearchParams(window.location.search);
        if (params.has('paid')) {
            try { await api('sync', { checkout_session_id: params.get('session_id') || '' }); } catch (_) {}
            goToAppHomeAfterPayment();
            return;
        }        try {
            const status = await api('status');
            _state.sub = status.subscription;
            _state.instituciones = status.instituciones || [];
            _state.institutionTrial = status.institution_trial || null;
            _state.usage = status.usage || { residentes:0, familiares:0 };
            _state.publishable_key = status.publishable_key || '';
            if (status.currency_default) {
                _state.currency = status.currency_default;
                const ccSel = document.getElementById('blCurrency');
                if (ccSel) ccSel.value = status.currency_default;
            }
            renderCurrent();
        } catch (e) {
            document.getElementById('blCurrentBody').innerHTML = `<div class="bl-err">Error: ${esc(e.message)}</div>`;
        }
        if (IS_NATIVE) return; // En nativo no cargamos catálogo de planes ni addons
        try {
            const history = await api('payment_history');
            _state.payments = history.payments || [];
            renderPaymentHistory();
        } catch (e) {
            const body = document.getElementById('blPaymentHistoryBody');
            if (body) body.innerHTML = `<div class="bl-err">Error cargando historial: ${esc(e.message)}</div>`;
        }
        try {
            const cat = await api('plans');
            _state.plans  = cat.planes || [];
            _state.addons = cat.addons || [];
            renderPlans();
            renderAddons();
            maybeStartRegistrationCheckout();
        } catch (e) {
            const pb = document.getElementById('blPlansBody');
            if (pb) pb.innerHTML = `<div class="bl-err">Error: ${esc(e.message)}</div>`;
        }
    })();
})();
</script>

<?php require_once 'includes/foot.php'; ?>
