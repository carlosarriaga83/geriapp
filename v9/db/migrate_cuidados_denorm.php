<?php
/**
 * GeriApp — Migración: columnas desnormalizadas en cuidados_registros
 *
 * Agrega 3 columnas auxiliares en texto claro para permitir cifrar
 * la columna JSON `datos` sin perder capacidad de consulta SQL:
 *   - subtipo       VARCHAR(50)  — e.g. 'Heces','Orina' (de datos.tipo_eliminacion)
 *   - pendiente     TINYINT(1)   — 1 si el registro de sueño está pendiente
 *   - duracion_min  SMALLINT     — duración en minutos (terapias, sueño)
 *
 * v1.28.0 — Cifrado fase 2
 */
require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$instId = $argv[1] ?? $_SESSION['user_institucion_id'] ?? null;
if (!$instId) { echo "Error: sesión sin institución. Uso CLI: php migrate_cuidados_denorm.php <instId>\n"; exit; }

$db = Database::getTenant($instId);

$sqls = [
    // 1. Add columns (IF NOT EXISTS via COLUMN_EXISTS check)
    "ALTER TABLE `cuidados_registros`
        ADD COLUMN IF NOT EXISTS `subtipo`      VARCHAR(50) DEFAULT NULL AFTER `hora`,
        ADD COLUMN IF NOT EXISTS `pendiente`    TINYINT(1)  NOT NULL DEFAULT 0 AFTER `subtipo`,
        ADD COLUMN IF NOT EXISTS `duracion_min` SMALLINT    DEFAULT NULL AFTER `pendiente`",

    // 2. Index for subtipo (used in WHERE subtipo = 'Heces')
    "ALTER TABLE `cuidados_registros`
        ADD INDEX IF NOT EXISTS `idx_cr_subtipo` (`subtipo`)",

    // 3. Index for pendiente (used in WHERE pendiente = 1)
    "ALTER TABLE `cuidados_registros`
        ADD INDEX IF NOT EXISTS `idx_cr_pendiente` (`pendiente`)",
];

$ok = 0;
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        $ok++;
    } catch (PDOException $e) {
        // Column/index already exists — safe to ignore
        if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'Duplicate key')) {
            $ok++;
        } else {
            echo "⚠ SQL Error: " . $e->getMessage() . "\n";
        }
    }
}

// 4. Backfill: populate denormalized columns from datos
//    Usa PHP para descifrar datos (post-cifrado JSON_EXTRACT no funciona)
echo "Backfilling denormalized columns (PHP decrypt)...\n";

require_once __DIR__ . '/../includes/EncryptionMap.php';

$backfill = 0;

// Fetch rows needing backfill — only relevant categories
$stmt = $db->prepare(
    "SELECT id, categoria, datos FROM cuidados_registros
     WHERE datos IS NOT NULL
       AND (
           (categoria = 'eliminacion' AND subtipo IS NULL)
        OR (categoria = 'sueno'       AND pendiente = 0 AND datos LIKE '%pendiente%')
        OR (categoria IN ('terapia','sueno') AND duracion_min IS NULL AND datos LIKE '%duracion%')
       )"
);
$stmt->execute();

$upd = $db->prepare(
    "UPDATE cuidados_registros
     SET subtipo = :subtipo, pendiente = :pendiente, duracion_min = :duracion_min
     WHERE id = :id"
);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    try {
        // Decrypt datos column
        $dec = EncryptionMap::decryptRow('cuidados_registros', ['datos' => $row['datos']]);
        $raw = $dec['datos'] ?? $row['datos'];
        $datos = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($datos)) continue;

        $subtipo     = null;
        $pendiente   = 0;
        $duracionMin = null;

        if ($row['categoria'] === 'eliminacion' && !empty($datos['tipo_eliminacion'])) {
            $subtipo = $datos['tipo_eliminacion'];
        }
        if ($row['categoria'] === 'sueno' && !empty($datos['pendiente'])) {
            $pendiente = 1;
        }
        if (isset($datos['duracion_min'])) {
            $duracionMin = (int) $datos['duracion_min'];
        }

        $upd->execute([
            ':subtipo'      => $subtipo,
            ':pendiente'    => $pendiente,
            ':duracion_min' => $duracionMin,
            ':id'           => $row['id'],
        ]);
        if ($upd->rowCount() > 0) $backfill++;
    } catch (Throwable $e) {
        echo "  ⚠ Row {$row['id']}: " . $e->getMessage() . "\n";
    }
}

echo "  Registros actualizados: {$backfill}\n";
echo "✅ Migración completada: {$ok} DDL ejecutados, {$backfill} registros actualizados.\n";
