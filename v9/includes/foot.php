    <script>window.GERIAPP_BASE = <?= json_encode(BASE_URL) ?>;</script>
    <?php
    // capacitor.js solo existe dentro del bundle nativo (Android/iOS WebView).
    // En web pública el archivo no existe → evita el 404 del navegador.
    // Reusa $_isNative computado en head.php; si no está disponible (otra
    // página que no incluya head.php) recalcula con la misma heuristica.
    if (!isset($_isNative)) {
        $_uaRaw = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_isNative = $_uaRaw !== '' && (
            stripos($_uaRaw, 'GeriAppNative')    !== false ||
            stripos($_uaRaw, 'CapacitorWebView') !== false ||
            stripos($_uaRaw, 'Capacitor')        !== false ||
            (stripos($_uaRaw, 'Android')   !== false && stripos($_uaRaw, '; wv)')    !== false) ||
            (stripos($_uaRaw, 'AppleWebKit') !== false &&
             (stripos($_uaRaw, 'iPhone') !== false || stripos($_uaRaw, 'iPad') !== false) &&
             stripos($_uaRaw, 'Mobile/') !== false && stripos($_uaRaw, 'Safari/') === false)
        );
        if (!$_isNative && !empty($_GET['native'])) $_isNative = true;
    }
    if ($_isNative): ?>
    <script src="capacitor.js"></script>
    <?php endif; ?>
    <script src="<?= ASSETS_URL ?>/js/app.js?v=<?= time() ?>"></script>
    <?= isset($extraScripts) ? $extraScripts : '' ?>

<?php if (empty($bodyClass) || $bodyClass !== 'login-page'): ?>
<!-- ── Global photo lightbox ─────────────────────────────────────── -->
<style>
#gPhotoModal{display:none;position:fixed;inset:0;z-index:10500;background:rgba(0,0,0,.82);align-items:center;justify-content:center}
#gPhotoModal.open{display:flex}
#gPhotoModal figure{margin:0;display:flex;flex-direction:column;align-items:center;gap:10px;max-width:min(94vw,540px)}
#gPhotoModal figure img{max-width:100%;max-height:82vh;border-radius:14px;box-shadow:0 12px 48px rgba(0,0,0,.6);object-fit:contain}
#gPhotoModal figcaption{color:#e2e8f0;font-size:13px;font-weight:500;text-align:center}
#gPhotoModal button{position:absolute;top:16px;right:18px;background:rgba(255,255,255,.15);border:none;color:#fff;width:38px;height:38px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
#gPhotoModal button:hover{background:rgba(255,255,255,.28)}
</style>
<div id="gPhotoModal" role="dialog" aria-modal="true" aria-label="Foto ampliada" onclick="if(event.target===this)closePhoto()">
    <button onclick="closePhoto()" title="Cerrar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <figure>
        <img id="gPhotoModalImg" src="" alt="">
        <figcaption id="gPhotoModalCaption"></figcaption>
    </figure>
</div>
<script>
function openPhoto(src, name) {
    document.getElementById('gPhotoModalImg').src = src;
    document.getElementById('gPhotoModalImg').alt = name || '';
    document.getElementById('gPhotoModalCaption').textContent = name || '';
    document.getElementById('gPhotoModal').classList.add('open');
    document.addEventListener('keydown', _gPhotoKey);
}
function closePhoto() {
    document.getElementById('gPhotoModal').classList.remove('open');
    document.removeEventListener('keydown', _gPhotoKey);
}
function _gPhotoKey(e) { if (e.key === 'Escape') closePhoto(); }
</script>
<?php endif; ?>
</body>
</html>
