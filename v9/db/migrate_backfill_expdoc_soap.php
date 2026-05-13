<?php
/**
 * GeriApp — Migración backfill: copiar contenido SOAP (JSON v:2) desde
 * `notas_medico.contenido` (descifrado) hacia el `expediente_docs.descripcion`
 * correspondiente, para que el sidebar del expediente pueda renderizar
 * las secciones S/O/A/P de notas antiguas creadas antes de v1.51.18.
 *
 * Estrategia de matching (sin FK directa):
 *   - mismo institucion_id, residente_id
 *   - expediente_docs.created_by  == notas_medico.usuario_id
 *   - expediente_docs.tipo IN ('nota_medico','receta')
 *   - fecha_documento = DATE(notas_medico.creado_at)
 *   - expediente_docs.created_at dentro de ±10 min de notas_medico.creado_at
 *   - descripcion NO empiece ya con '{"v":2' (idempotente)
 *
 * Si hay múltiples candidatos para una misma nota, se elige el más
 * cercano en created_at. Si ninguno matchea, se loggea y se omite.
 *
 * Ejecutar:  php db/migrate_backfill_expdoc_soap.php
 *            (opcional) ?dry=1 vía CLI env DRY=1 para simular sin escribir.
 */

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../includes/EncryptionMap.php';

$DRY = !empty(getenv('DRY')) || in_array('--dry', $argv ?? [], true);

echo "=== Backfill expediente_docs.descripcion ← notas_medico.contenido (SOAP v:2) ===\n";
if ($DRY) echo "[MODO DRY-RUN: no se escribirá nada]\n";

$master = Database::getInstance();

try {
    $instituciones = $master->query(
        "SELECT id, COALESCE(db_name,'') AS db_name FROM instituciones"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "ERROR listando instituciones: " . $e->getMessage() . "\n";
    exit(1);
}

$totalNotas    = 0;
$totalUpdated  = 0;
$totalSkipped  = 0;  // ya migradas (v:2)
$totalNoMatch  = 0;  // sin expediente_docs
$totalNoJson   = 0;  // contenido no era JSON v:2 (nota legacy)

foreach ($instituciones as $inst) {
    $instId  = (int) $inst['id'];
    $dbName  = $inst['db_name'] !== '' ? $inst['db_name'] : '(master compartido)';
    $label   = "inst={$instId} ({$dbName})";

    try {
        $db = Database::getTenant($instId);
    } catch (Exception $e) {
        echo "[{$label}] No se pudo conectar: " . $e->getMessage() . "\n";
        continue;
    }

    // Validar que existan ambas tablas
    $hasNotas = (bool) $db->query("SHOW TABLES LIKE 'notas_medico'")->fetch();
    $hasExp   = (bool) $db->query("SHOW TABLES LIKE 'expediente_docs'")->fetch();
    if (!$hasNotas || !$hasExp) {
        echo "[{$label}] Tablas faltantes (notas_medico=" . ($hasNotas?'✓':'✗')
           . ", expediente_docs=" . ($hasExp?'✓':'✗') . ") — omitido.\n";
        continue;
    }

    $stmtNotas = $db->prepare(
        "SELECT id, residente_id, usuario_id, contenido, creado_at
           FROM notas_medico
          WHERE institucion_id = ?
          ORDER BY creado_at ASC"
    );
    $stmtNotas->execute([$instId]);
    $notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

    $countInst = count($notas);
    if ($countInst === 0) {
        echo "[{$label}] Sin notas médicas.\n";
        continue;
    }

    echo "[{$label}] Procesando {$countInst} notas…\n";
    $totalNotas += $countInst;

    // Prepared statements reutilizables
    // NB: no filtramos por fecha_documento — puede haber desfase de TZ entre
    // `notas_medico.creado_at` (UTC) y `expediente_docs.fecha_documento`
    // (local). La cercanía temporal de `created_at` (±10 min) es suficiente.
    $stmtFind = $db->prepare(
        "SELECT id, descripcion, created_at,
                ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) AS delta
           FROM expediente_docs
          WHERE institucion_id = ?
            AND residente_id   = ?
            AND created_by     = ?
            AND tipo IN ('nota_medico','receta')
            AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 600
          ORDER BY delta ASC
          LIMIT 1"
    );
    $stmtUpdate = $db->prepare(
        "UPDATE expediente_docs SET descripcion = ? WHERE id = ?"
    );

    foreach ($notas as $nota) {
        // Descifrar contenido
        try {
            $dec = EncryptionMap::decryptRow('notas_medico', ['contenido' => $nota['contenido']]);
            $contenido = $dec['contenido'] ?? '';
        } catch (Exception $e) {
            echo "  · nota#{$nota['id']}: error descifrando — {$e->getMessage()}\n";
            $totalNoJson++;
            continue;
        }

        // Debe ser JSON v:2 con secciones SOAP
        $trim = ltrim($contenido);
        if ($trim === '' || $trim[0] !== '{') {
            $totalNoJson++;
            continue;
        }
        $parsed = json_decode($contenido, true);
        if (!is_array($parsed) || (($parsed['v'] ?? null) !== 2 && empty($parsed['subjetivo']) && empty($parsed['objetivo']) && empty($parsed['analisis']) && empty($parsed['plan']) && empty($parsed['diagnosticos']))) {
            $totalNoJson++;
            continue;
        }

        // Buscar expediente_docs candidato
        $ok = $stmtFind->execute([
            $nota['creado_at'],
            $instId,
            (int) $nota['residente_id'],
            (int) $nota['usuario_id'],
            $nota['creado_at'],
        ]);
        if (!$ok) {
            echo "  · nota#{$nota['id']}: fallo SELECT expediente_docs\n";
            continue;
        }
        $doc = $stmtFind->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            $totalNoMatch++;
            continue;
        }

        // Idempotencia: si descripcion ya es JSON v:2, skip
        $curDesc = (string) ($doc['descripcion'] ?? '');
        if ($curDesc !== '' && $curDesc[0] === '{') {
            $peek = json_decode($curDesc, true);
            if (is_array($peek) && ($peek['v'] ?? null) === 2) {
                $totalSkipped++;
                continue;
            }
        }

        if ($DRY) {
            $totalUpdated++;
            echo "  · nota#{$nota['id']} → expdoc#{$doc['id']} (Δ={$doc['delta']}s) [DRY]\n";
            continue;
        }

        try {
            $stmtUpdate->execute([$contenido, (int) $doc['id']]);
            $totalUpdated++;
        } catch (Exception $e) {
            echo "  · nota#{$nota['id']} → expdoc#{$doc['id']}: UPDATE falló — {$e->getMessage()}\n";
        }
    }

    echo "[{$label}] done.\n";
}

echo "\n=== RESUMEN ===\n";
echo "Notas totales procesadas : {$totalNotas}\n";
echo "Expediente_docs actualizados: {$totalUpdated}\n";
echo "Ya migrados (skip v:2)   : {$totalSkipped}\n";
echo "Sin match en expediente_docs: {$totalNoMatch}\n";
echo "Contenido no-JSON / legacy: {$totalNoJson}\n";
if ($DRY) echo "\n(DRY-RUN: no se modificó ningún registro)\n";
echo "Migración completada.\n";
