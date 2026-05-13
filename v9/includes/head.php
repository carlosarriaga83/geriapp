<?php
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/conf/config.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="description" content="GeriApp — Sistema de gestión de cuidados geriátricos">
    <meta name="theme-color" content="#178391">
    <?= csrf_meta() ?>
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME ?></title>

    <!-- F9 — Apple §3.1.1 / Google Play: estrategia FAIL-CLOSED.
         La card de billing y cualquier elemento [data-hide-on-native] se renderizan
         con display:none POR DEFECTO. Solo se muestran cuando hayamos confirmado
         100 % que NO estamos en una WebView nativa. Si la detección falla por
         cualquier razón, los elementos quedan ocultos (lo opuesto a la lógica
         anterior, que los mostraba al fallar). Esto evita rechazos de tienda. -->
    <?php
    // Detección server-side (UA): añadimos GeriAppNative/1 en capacitor.config.json
    // (appendUserAgent) → si el header User-Agent lo contiene, somos 100 % nativo.
    // Heurísticas adicionales (defensa en profundidad si el APK aún no se rebuilt):
    //   - Android WebView: UA contiene "; wv)" (literal del runtime de WebView).
    //   - iOS WKWebView: UA tiene "AppleWebKit" + "Mobile/" pero NO contiene
    //     "Safari/" (Safari móvil siempre incluye ese token; WKWebView no).
    $_uaRaw    = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_uaLow    = strtolower($_uaRaw);
    $_isNative = $_uaRaw !== '' && (
        stripos($_uaRaw, 'GeriAppNative')    !== false ||
        stripos($_uaRaw, 'Capacitor')        !== false ||
        stripos($_uaRaw, 'CapacitorWebView') !== false ||
        // Android WebView heuristic
        strpos($_uaLow, '; wv)') !== false ||
        // iOS WKWebView heuristic: Mobile/<build> AppleWebKit pero sin Safari/
        (
            strpos($_uaLow, 'applewebkit') !== false &&
            (strpos($_uaLow, 'iphone') !== false || strpos($_uaLow, 'ipad') !== false || strpos($_uaLow, 'ipod') !== false) &&
            strpos($_uaLow, 'mobile/') !== false &&
            strpos($_uaLow, 'safari/') === false &&
            strpos($_uaLow, 'crios/') === false &&    // Chrome iOS (es navegador, no WebView)
            strpos($_uaLow, 'fxios/') === false       // Firefox iOS
        )
    );
    ?>
    <script>(function(){
        // Si el server ya confirmó nativo via UA, lo marcamos de inmediato.
        var serverNative = <?= $_isNative ? 'true' : 'false' ?>;
        if (serverNative) {
            document.documentElement.dataset.platform = 'native';
            document.documentElement.dataset.native   = '1';
            return; // No hace falta sondear más; UA es definitivo.
        }
        // Reproducimos EXACTAMENTE la lógica que sí funciona en cfgPushDiag:
        //   1) capacitor.js se inyecta como <script src="capacitor.js"> al final
        //      del <body>; en web NO existe, así que window.Capacitor sigue
        //      undefined.
        //   2) Esperamos DOMContentLoaded + 100 ms para asegurar que el bridge
        //      esté listo (mismo delay que setupPushNotifications usa).
        //   3) Solo después marcamos web/native. Mientras tanto la CSS deja
        //      todo oculto (fail-closed).
        function _check(){
            try{
                var native = false;
                // 1) Bridge de Capacitor (sólo si la APK lo incluye).
                if (typeof window.Capacitor !== 'undefined') {
                    try {
                        if (typeof window.Capacitor.isNativePlatform === 'function') {
                            native = !!window.Capacitor.isNativePlatform();
                        } else if (window.Capacitor.platform && window.Capacitor.platform !== 'web') {
                            native = true;
                        }
                    } catch(_){}
                }
                // 2) Heurísticas de UA / protocolo (defensa cuando NO hay bridge).
                if (!native) {
                    var ua = (navigator.userAgent || '').toLowerCase();
                    if (ua.indexOf('capacitor')    !== -1) native = true;
                    if (ua.indexOf('geriapp')      !== -1) native = true;
                    if (location.protocol === 'capacitor:' || location.protocol === 'ionic:') native = true;
                    // Android WebView token literal
                    if (ua.indexOf('; wv)') !== -1) native = true;
                    // iOS WKWebView: AppleWebKit + iPhone/iPad + Mobile/ pero sin Safari/
                    if (!native &&
                        ua.indexOf('applewebkit') !== -1 &&
                        (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1) &&
                        ua.indexOf('mobile/') !== -1 &&
                        ua.indexOf('safari/') === -1 &&
                        ua.indexOf('crios/') === -1 &&
                        ua.indexOf('fxios/') === -1) {
                        native = true;
                    }
                }
                if (native) {
                    document.documentElement.dataset.platform = 'native';
                    document.documentElement.dataset.native   = '1';
                } else {
                    document.documentElement.dataset.platform = 'web';
                }
            }catch(_){
                // Cualquier excepción → mantener oculto (fail-closed). No marcar web.
            }
        }
        function _schedule(){ setTimeout(_check, 100); }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', _schedule);
        } else {
            _schedule();
        }
        // Re-check tardío por si el bridge se inyecta más tarde aún.
        window.addEventListener('load', function(){ setTimeout(_check, 250); });
    })();</script>
    <style>
        /* FAIL-CLOSED: oculto por defecto. Solo visible cuando confirmamos web. */
        #cdProfileBillingCard,
        [data-hide-on-native] { display: none !important; }
        html[data-platform="web"] #cdProfileBillingCard { display: block !important; }
        html[data-platform="web"] [data-hide-on-native]  { display: revert !important; }
        /* Refuerzo: si data-native="1" está presente, gana sobre todo. */
        html[data-native="1"] #cdProfileBillingCard,
        html[data-native="1"] [data-hide-on-native] { display: none !important; }
    </style>
    <?php
    // ── Open Graph / Twitter Card (link previews para WhatsApp, Telegram, etc.) ──
    $_ogTitle    = isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME : APP_NAME;
    $_ogDesc     = 'GeriApp — Sistema de gestión de cuidados geriátricos';
    $_ogPublic   = function_exists('app_public_url') ? app_public_url() : (BASE_URL ?: '');
    $_ogImage    = $_ogPublic . '/public/previews/geriapp-og-icon.jpg';
    $_ogUrl      = $_ogPublic . ($_SERVER['REQUEST_URI'] ?? '/');
    ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars(APP_NAME) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($_ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($_ogDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($_ogUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($_ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= htmlspecialchars($_ogImage) ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="GeriApp">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($_ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($_ogDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($_ogImage) ?>">
    <!-- Anti-FOUC: apply saved theme before stylesheet renders -->
    <script>(function(){var forced=<?= !empty($forceLightTheme) ? "'light'" : "''" ?>;if(forced){document.documentElement.setAttribute('data-theme',forced);document.documentElement.dataset.forceTheme=forced;return;}var uid=<?= !empty($_SESSION['user_id']) ? json_encode((int)$_SESSION['user_id']) : 'null' ?>;var key=uid?'geriappTheme:'+uid:'geriappTheme';var t=localStorage.getItem(key);if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSETS_URL ?>/icons/app/icon_32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= ASSETS_URL ?>/icons/app/icon_64.png">
    <link rel="apple-touch-icon" sizes="128x128" href="<?= ASSETS_URL ?>/icons/app/icon_128.png">
    <link rel="apple-touch-icon" sizes="256x256" href="<?= ASSETS_URL ?>/icons/app/icon_256.png">
    <?php
    // Dynamic favicon: yellow tint (superadmin) and/or red dot (testgeriapp)
    $faviconTintColor = isset($faviconTint) ? $faviconTint : '';
    $isTestHost = str_contains($_SERVER['HTTP_HOST'] ?? '', 'test');
    if ($faviconTintColor || $isTestHost): ?>
    <script>(function(){try{
        var tint=<?= json_encode($faviconTintColor) ?>,dot=<?= json_encode($isTestHost) ?>;
        // iPad/iPhone WKWebView: canvas + crossOrigin can taint or crash on file:// or capacitor:// origins.
        // Skip dynamic favicon entirely on Capacitor native to avoid the WebKit canvas tainting bug.
        if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) return;
        var img=new Image();img.crossOrigin='anonymous';
        img.onerror=function(){};
        img.onload=function(){try{
            var c=document.createElement('canvas');c.width=c.height=64;
            var x=c.getContext('2d');
            x.drawImage(img,0,0,64,64);
            if(tint){x.globalCompositeOperation='source-atop';x.fillStyle=tint;x.fillRect(0,0,64,64);}
            if(dot){x.globalCompositeOperation='source-over';x.fillStyle='#ef4444';x.beginPath();x.arc(52,10,11,0,2*Math.PI);x.fill();x.strokeStyle='#fff';x.lineWidth=2;x.stroke();}
            document.querySelectorAll('link[rel="icon"]').forEach(function(l){l.remove();});
            var lk=document.createElement('link');lk.rel='icon';lk.type='image/png';lk.href=c.toDataURL('image/png');document.head.appendChild(lk);
        }catch(_){}};
        img.src=<?= json_encode(ASSETS_URL . '/icons/app/icon_64.png') ?>;
    }catch(_){}})()</script>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(__DIR__.'/../assets/css/style.css') ?>">
    <?= isset($extraStyles) ? $extraStyles : '' ?>
</head>
<body class="<?= isset($bodyClass) ? htmlspecialchars($bodyClass) : '' ?>">
