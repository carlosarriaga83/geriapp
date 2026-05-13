<?php
/**
 * Migration: Create arco_solicitudes table + update legal documents
 * - Table for ARCO rights requests
 * - Fill placeholders, fix typos, clean data attributes
 * - Add LFPDPPP improvements (plazo ARCO, INAI, retention, deletion)
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../conf/config.php';

try {
    $db = Database::getInstance();

    // ── 1. Create arco_solicitudes table ──────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS arco_solicitudes (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id  INT UNSIGNED NOT NULL,
            institucion_id INT UNSIGNED DEFAULT NULL,
            tipo        ENUM('acceso','rectificacion','cancelacion','oposicion') NOT NULL,
            estado      ENUM('pendiente','en_proceso','completada','rechazada') NOT NULL DEFAULT 'pendiente',
            descripcion TEXT DEFAULT NULL,
            respuesta   TEXT DEFAULT NULL,
            respondido_por INT UNSIGNED DEFAULT NULL,
            creado_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            respondido_at DATETIME DEFAULT NULL,
            INDEX idx_usuario   (usuario_id),
            INDEX idx_estado    (estado),
            INDEX idx_inst      (institucion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla 'arco_solicitudes' creada (o ya existía).\n";

    // ── 2. Clean & update legal documents ─────────────────────────────────
    $DEVELOPER   = 'Alain Jean Paul Raimond Kedilhac Ruiz';
    $ADDRESS     = 'Calle Rocío 197, Col. Jardines del Pedregal, Álvaro Obregón, Ciudad de México, C.P. 01900';
    $EMAIL       = 'info_geriapp@prepenv.com';
    $CITY        = 'Ciudad de México';

    // Get all legal docs
    $docs = $db->query("SELECT id, tipo, contenido FROM documentos_legales")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($docs as $doc) {
        $html = $doc['contenido'];
        $changed = false;

        // ── Strip data-path-to-node and data-index-in-node attributes ──
        $cleaned = preg_replace('/\s+data-path-to-node="[^"]*"/', '', $html);
        $cleaned = preg_replace('/\s+data-index-in-node="[^"]*"/', '', $cleaned);
        if ($cleaned !== $html) { $html = $cleaned; $changed = true; }

        // ── Fix empty h1 (contains only <br>) ──
        $html = preg_replace('/<h1>\s*<br\s*\/?>\s*<\/h1>/', '', $html);
        if ($html !== $doc['contenido']) $changed = true;

        // ── Fill placeholders ──
        $replacements = [
            '[Nombre del Desarrollador o Empresa]' => $DEVELOPER,
            '[Tu Dirección en México o Ciudad/Estado]' => $ADDRESS,
            '[Tu Dirección en México]' => $ADDRESS,
            '[Tu Correo de Soporte]' => $EMAIL,
            '[Ciudad/Estado, México]' => $CITY,
        ];

        foreach ($replacements as $search => $replace) {
            $newHtml = str_replace($search, $replace, $html);
            if ($newHtml !== $html) { $html = $newHtml; $changed = true; }
        }

        // ── Fix typo: "ponerse al uso" → "oponerse al uso" ──
        if ($doc['tipo'] === 'privacidad') {
            $newHtml = str_replace('ponerse al uso', 'oponerse al uso', $html);
            if ($newHtml !== $html) { $html = $newHtml; $changed = true; }
        }

        // ── Add LFPDPPP improvements to Privacy docs ──
        if ($doc['tipo'] === 'privacidad') {

            // Add plazo ARCO (20 business days) + INAI to section 5
            $arcoOld = 'Su solicitud deberá contener:</p>';
            $arcoNew = 'Su solicitud deberá contener:</p>';
            // Check if improvement already exists
            if (strpos($html, '20 días hábiles') === false) {
                // Find the end of the ARCO list and add plazo + INAI after it
                $arcoListEnd = '</ol><h2';
                $arcoImprovement = '</ol>'
                    . '<p>GeriApp responderá su solicitud en un plazo máximo de <b>20 días hábiles</b> contados a partir de la recepción de la solicitud completa, conforme al artículo 32 de la LFPDPPP.</p>'
                    . '<p>Si considera que su derecho no ha sido debidamente atendido, puede presentar una queja ante el <b>Instituto Nacional de Transparencia, Acceso a la Información y Protección de Datos Personales (INAI)</b>: <a href="https://home.inai.org.mx" target="_blank" rel="noopener">www.inai.org.mx</a>.</p>'
                    . '<h2';

                // Only replace the first occurrence that's in section 5 (after Derechos ARCO)
                $pos = strpos($html, 'Derechos ARCO');
                if ($pos !== false) {
                    $posEnd = strpos($html, $arcoListEnd, $pos);
                    if ($posEnd !== false) {
                        $html = substr($html, 0, $posEnd) . $arcoImprovement . substr($html, $posEnd + strlen($arcoListEnd));
                        $changed = true;
                    }
                }
            }

            // Add data retention section before "Cambios al Aviso"
            if (strpos($html, 'Período de conservación') === false && strpos($html, 'conservación de datos') === false) {
                $beforeChanges = '8. Cambios al Aviso de Privacidad';
                $retentionSection = '8. Conservación de datos</h2>'
                    . '<p>Sus datos personales serán conservados mientras mantenga una cuenta activa en GeriApp. '
                    . 'En caso de cancelación de cuenta, los datos serán eliminados en un plazo máximo de <b>30 días naturales</b>, '
                    . 'salvo aquellos que deban conservarse por obligación legal o para fines de auditoría, '
                    . 'los cuales se mantendrán de forma anonimizada por un período máximo de <b>5 años</b>.</p>'
                    . '<h2>9. Eliminación de cuenta</h2>'
                    . '<p>Usted puede solicitar la eliminación total de su cuenta y datos personales en cualquier momento '
                    . 'desde la sección <b>"Mis Datos y Derechos ARCO"</b> dentro de su perfil en la aplicación, '
                    . 'o enviando una solicitud al correo <b>' . $EMAIL . '</b>. '
                    . 'La eliminación se procesará en un plazo máximo de <b>30 días naturales</b>.</p>'
                    . '<h2>10. Cambios al Aviso de Privacidad';

                $newHtml = str_replace($beforeChanges, $retentionSection, $html);
                if ($newHtml !== $html) { $html = $newHtml; $changed = true; }
            }
        }

        // ── Add account deletion + contact sections to T&C ──
        if ($doc['tipo'] === 'terminos') {
            // Add account deletion section before Legislación
            if (strpos($html, 'Eliminación de Cuenta') === false) {
                $beforeLeg = '15. Legislación y Jurisdicción';
                $deletionSection = '15. Eliminación de Cuenta</h2>'
                    . '<p>El Usuario puede solicitar la eliminación de su cuenta y todos los datos asociados en cualquier momento '
                    . 'a través de la sección <b>"Mis Datos y Derechos ARCO"</b> en su perfil, '
                    . 'o contactando al correo <b>' . $EMAIL . '</b>. '
                    . 'GeriApp procesará la solicitud en un plazo máximo de <b>30 días naturales</b>. '
                    . 'La eliminación es irreversible e incluye todos los registros ingresados por el Usuario.</p>'
                    . '<h2>16. Contacto</h2>'
                    . '<p>Para cualquier duda, aclaración o ejercicio de derechos relacionados con estos Términos y Condiciones, '
                    . 'puede comunicarse al correo electrónico: <b>' . $EMAIL . '</b>.</p>'
                    . '<h2>17. Legislación y Jurisdicción';

                $newHtml = str_replace($beforeLeg, $deletionSection, $html);
                if ($newHtml !== $html) { $html = $newHtml; $changed = true; }
            }
        }

        // ── Save if changed ──
        if ($changed) {
            $stmt = $db->prepare("UPDATE documentos_legales SET contenido = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$html, $doc['id']]);
            echo "✅ Documento ID {$doc['id']} ({$doc['tipo']}) actualizado.\n";
        } else {
            echo "ℹ️ Documento ID {$doc['id']} ({$doc['tipo']}) sin cambios.\n";
        }
    }

    echo "\n✅ Migración ARCO/LFPDPPP completada.\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
