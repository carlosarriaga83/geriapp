<?php
/**
 * GeriApp — Aviso de Privacidad (público)
 * URL: /v8/adp.php  o  /v8/adp.php?inst=<id>
 *
 * Muestra el Aviso de Privacidad vigente sin requerir sesión.
 * Si se indica ?inst=ID se carga el de esa institución específica;
 * sin parámetro se carga el primero vigente disponible.
 *
 * Accesible públicamente — no contiene datos personales de residentes.
 */

require_once __DIR__ . '/conf/config.php';
require_once __DIR__ . '/db/Database.php';

// ── Resolver institución ────────────────────────────────────────────────────
$instId  = max(0, (int)($_GET['inst'] ?? 0));
$doc     = null;
$instNom = null;

try {
    if ($instId > 0) {
        // Tenant específico
        $tenantDb = Database::getTenant($instId);
        $stmt = $tenantDb->prepare(
            "SELECT id, tipo, version, titulo, contenido, creado_at, updated_at
             FROM documentos_legales
             WHERE institucion_id = ? AND tipo = 'privacidad' AND vigente = 1
             LIMIT 1"
        );
        $stmt->execute([$instId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Nombre de la institución
        $masterDb = Database::getMaster();
        $si = $masterDb->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
        $si->execute([$instId]);
        $instNom = $si->fetchColumn() ?: null;

    } else {
        // Sin filtro: buscar en master el primero vigente
        $masterDb = Database::getMaster();
        $stmt = $masterDb->prepare(
            "SELECT dl.id, dl.tipo, dl.version, dl.titulo, dl.contenido,
                    dl.creado_at, dl.updated_at, i.nombre AS inst_nombre
             FROM documentos_legales dl
             LEFT JOIN instituciones i ON i.id = dl.institucion_id
             WHERE dl.tipo = 'privacidad' AND dl.vigente = 1
             ORDER BY dl.updated_at DESC
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $doc     = $row;
            $instNom = $row['inst_nombre'] ?? null;
        }
    }
} catch (\Throwable $e) {
    // Silencioso — mostramos "no disponible" al usuario
    $doc = null;
}

// ── Preparar contenido para renderizado seguro ──────────────────────────────
$contenidoHtml = '';
if ($doc && !empty($doc['contenido'])) {
    $raw = $doc['contenido'];
    if (preg_match('/<[a-z][\s\S]*?>/i', $raw)) {
        // Rich HTML (v1.34.8+): sanitize with allowlist
        $contenidoHtml = strip_tags($raw,
            '<b><strong><i><em><u><br><p><div><span><ul><ol><li>'
            . '<h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><sub><sup><a>'
        );
        $contenidoHtml = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $contenidoHtml);
        $contenidoHtml = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $contenidoHtml);
    } else {
        // Plain text (pre-v1.34.8): escape & convert newlines
        $contenidoHtml = nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
    }
}

$pageTitle   = 'Aviso de Privacidad';
$bodyClass   = 'adp-page';

$extraStyles = <<<HTML
<style>
/* ── Aviso de Privacidad público — v3 ─────────────────────────────── */
*,*::before,*::after{box-sizing:border-box}
body.adp-page{overflow:auto;position:static;min-height:100vh;background:var(--bg,#f1f5f9)}
.adp-wrap{width:100%;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:0 16px}
.adp-container{width:100%;max-width:780px;margin:0 auto;padding:0 0 48px}

/* ── Header ── */
.adp-header{text-align:center;padding:40px 0 28px}
.adp-logo{width:64px;height:64px;background:linear-gradient(135deg,#e0f2f7 0%,#b3e0ee 100%);
  border-radius:18px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:0 4px 12px rgba(23,131,145,.12)}
.adp-app-name{font-size:1.5rem;font-weight:800;background:linear-gradient(135deg,#178391,#033f3f);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 2px;letter-spacing:-0.3px}
.adp-inst{font-size:0.82rem;color:var(--text-muted,#64748b);margin:0;font-weight:500}

/* ── Card ── */
.adp-card{background:var(--white,#fff);border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06),0 8px 24px rgba(0,0,0,.06)}
.adp-card-hero{background:linear-gradient(135deg,#f0f9ff 0%,#e0f2f7 100%);padding:28px 36px 22px;border-bottom:1px solid var(--border,#e2e8f0)}
[data-theme="dark"] .adp-card-hero{background:linear-gradient(135deg,#0f2937 0%,#162d3d 100%)}
.adp-doc-type{display:inline-flex;align-items:center;gap:6px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#0e7490;margin:0 0 10px}
.adp-doc-type svg{flex-shrink:0}
.adp-title{font-size:1.35rem;font-weight:700;color:var(--text,#1e293b);margin:0 0 12px;line-height:1.35}
.adp-pills{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.adp-pill{display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;font-weight:600;padding:3px 10px;border-radius:9999px;white-space:nowrap}
.adp-pill--green{background:#dcfce7;color:#166534}
.adp-pill--blue{background:#dbeafe;color:#1e40af}
.adp-pill--gray{background:#f1f5f9;color:#475569}
[data-theme="dark"] .adp-pill--green{background:#14532d;color:#86efac}
[data-theme="dark"] .adp-pill--blue{background:#1e3a5f;color:#93c5fd}
[data-theme="dark"] .adp-pill--gray{background:#1e293b;color:#94a3b8}

/* ── Contenido ── */
.adp-body{padding:32px 36px}
.adp-content{font-size:0.875rem;line-height:1.85;color:var(--text,#334155);word-wrap:break-word}
.adp-content p{margin:0 0 .7em}
.adp-content ul,.adp-content ol{margin:0 0 .7em 1.4em;padding:0}
.adp-content li{margin-bottom:.25em}
.adp-content b,.adp-content strong{font-weight:700}
.adp-content i,.adp-content em{font-style:italic}
.adp-content u{text-decoration:underline}

/* ── Tabla de contenido rápida ── */
.adp-toc{background:var(--bg,#f8fafc);border:1px solid var(--border,#e2e8f0);border-radius:10px;padding:16px 20px;margin-bottom:24px}
.adp-toc-title{font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-muted,#64748b);margin:0 0 8px}
.adp-toc-info{font-size:0.78rem;color:var(--text-muted,#64748b);line-height:1.6}
.adp-toc-info span{display:inline-flex;align-items:center;gap:4px;margin-right:16px}

/* ── Empty ── */
.adp-empty{text-align:center;padding:64px 24px;color:var(--text-muted,#64748b)}
.adp-empty-icon{width:72px;height:72px;background:#f1f5f9;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px}
[data-theme="dark"] .adp-empty-icon{background:#1e293b}
.adp-empty h3{margin:0 0 8px;font-size:1rem;color:var(--text,#334155)}
.adp-empty p{margin:0 0 6px;font-size:0.85rem}

/* ── Footer ── */
.adp-footer{text-align:center;padding:20px 36px 28px;border-top:1px solid var(--border,#e2e8f0);font-size:0.75rem;color:var(--text-muted,#94a3b8);line-height:1.7}
.adp-footer a{color:#0e7490;text-decoration:none;font-weight:500}
.adp-footer a:hover{text-decoration:underline}
.adp-back-btn{display:inline-flex;align-items:center;gap:5px;margin-top:8px;padding:6px 14px;border:1px solid var(--border,#d1d5db);background:var(--white,#fff);border-radius:8px;font-size:0.78rem;color:var(--text,#334155);text-decoration:none;transition:all .15s ease}
.adp-back-btn:hover{background:#f1f5f9;border-color:#94a3b8;text-decoration:none}

/* ── Print ── */
@media print{.adp-header,.adp-footer,.adp-back-btn{display:none}.adp-card{box-shadow:none;border:none}.adp-body{padding:16px 0}}
@media (max-width:520px){.adp-card-hero,.adp-body,.adp-footer{padding-left:20px;padding-right:20px}.adp-title{font-size:1.15rem}}
[data-theme="dark"] .adp-card{background:var(--surface,#1e293b)}
</style>
HTML;

require_once __DIR__ . '/includes/head.php';
?>

<div class="adp-wrap">
<div class="adp-container">

    <!-- Header -->
    <div class="adp-header">
        <div class="adp-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#178391" stroke-width="1.8">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
        </div>
        <h1 class="adp-app-name"><?= APP_NAME ?></h1>
        <?php if ($instNom): ?>
        <p class="adp-inst"><?= htmlspecialchars($instNom) ?></p>
        <?php endif; ?>
    </div>

    <!-- Card -->
    <div class="adp-card">

        <?php if ($doc): ?>

        <div class="adp-card-hero">
            <div class="adp-doc-type">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Aviso de Privacidad
            </div>
            <h2 class="adp-title"><?= htmlspecialchars($doc['titulo']) ?></h2>
            <div class="adp-pills">
                <span class="adp-pill adp-pill--green">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Vigente
                </span>
                <span class="adp-pill adp-pill--blue">v<?= htmlspecialchars($doc['version']) ?></span>
                <?php
                    $fechaMod = $doc['updated_at'] ?: $doc['creado_at'];
                    if ($fechaMod):
                        $ts = strtotime($fechaMod);
                ?>
                <span class="adp-pill adp-pill--gray">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= date('d/m/Y', $ts) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="adp-body">
            <div class="adp-toc">
                <div class="adp-toc-title">Información del documento</div>
                <div class="adp-toc-info">
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Tipo: Aviso de Privacidad
                    </span>
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php if ($instNom): ?>Institución: <?= htmlspecialchars($instNom) ?><?php else: ?>Documento público<?php endif; ?>
                    </span>
                    <?php if ($fechaMod): ?>
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Última actualización: <?= date('d \d\e F \d\e Y', $ts) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="adp-content"><?= $contenidoHtml ?></div>
        </div>

        <?php else: ?>

        <div class="adp-empty">
            <div class="adp-empty-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="9" y1="13" x2="15" y2="13"/>
                    <line x1="9" y1="17" x2="11" y2="17"/>
                </svg>
            </div>
            <h3>Aviso de Privacidad no disponible</h3>
            <p>No hay un Aviso de Privacidad vigente disponible en este momento.</p>
            <?php if ($instId > 0): ?>
            <p style="font-size:0.75rem;color:var(--text-muted,#94a3b8);">Si eres el administrador, configúralo en<br>
                <strong>Configuración → Aviso de Privacidad</strong>.</p>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <div class="adp-footer">
            <?= date('Y') ?> &copy; <?= htmlspecialchars(APP_NAME) ?>.
            <?php if ($instNom): ?>
                <?= htmlspecialchars($instNom) ?> &mdash;
            <?php endif; ?>
            Todos los derechos reservados.
            <br>
            <a href="<?= BASE_URL ?>/index.php" class="adp-back-btn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Iniciar sesión
            </a>
        </div>

    </div><!-- /adp-card -->

</div><!-- /adp-container -->
</div><!-- /adp-wrap -->

<?php
// No cargar foot.php completo (trae lightbox y scripts innecesarios para página pública).
?>
</body>
</html>
