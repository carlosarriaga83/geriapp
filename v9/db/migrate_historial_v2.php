<?php
/**
 * GeriApp — Migración historial v2
 * Agrega columnas medico_nombre, fecha_consulta, hora_consulta
 * a la tabla historial_evoluciones.
 *
 * Ejecutar UNA sola vez en el navegador:
 *   http://localhost/geriapp/v2/db/migrate_historial_v2.php
 */

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/Database.php';

$db   = Database::getInstance();
$msgs = [];

// ── Columnas a agregar ─────────────────────────────────────────────────────
$columns = [
    'medico_nombre'   => "ALTER TABLE `historial_evoluciones`
                              ADD COLUMN `medico_nombre` VARCHAR(200) DEFAULT NULL
                              COMMENT 'Médico que realiza la consulta (texto libre)'
                              AFTER `usuario_id`",
    'fecha_consulta'  => "ALTER TABLE `historial_evoluciones`
                              ADD COLUMN `fecha_consulta` DATE DEFAULT NULL
                              COMMENT 'Fecha real de la consulta'
                              AFTER `medico_nombre`",
    'hora_consulta'   => "ALTER TABLE `historial_evoluciones`
                              ADD COLUMN `hora_consulta` VARCHAR(5) DEFAULT NULL
                              COMMENT 'Hora HH:MM de la consulta'
                              AFTER `fecha_consulta`",
];

foreach ($columns as $col => $sql) {
    // Verificar si la columna ya existe
    $check = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'historial_evoluciones'
            AND COLUMN_NAME  = ?"
    );
    $check->execute([$col]);
    if ($check->fetchColumn() > 0) {
        $msgs[] = "⚠ Columna `{$col}` ya existe — omitida.";
        continue;
    }

    try {
        $db->exec($sql);
        $msgs[] = "✓ Columna `{$col}` agregada correctamente.";
    } catch (PDOException $e) {
        $msgs[] = "✗ Error al agregar `{$col}`: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración historial_evoluciones v2</title>
    <style>
        body { font-family: monospace; background: #f8fafc; padding: 40px; }
        h2   { color: #1e3a6e; }
        p    { padding: 6px 12px; border-radius: 6px; margin: 4px 0; font-size: 14px; }
        .ok  { background: #f0fdf4; color: #166534; }
        .warn{ background: #fffbeb; color: #92400e; }
        .err { background: #fff1f2; color: #991b1b; }
    </style>
</head>
<body>
    <h2>Migración — historial_evoluciones v2</h2>
    <?php foreach ($msgs as $m): ?>
    <p class="<?= str_starts_with($m,'✓') ? 'ok' : (str_starts_with($m,'⚠') ? 'warn' : 'err') ?>">
        <?= htmlspecialchars($m) ?>
    </p>
    <?php endforeach; ?>
    <hr>
    <p style="color:#475569;">Migración completada. Puedes eliminar este archivo.</p>
</body>
</html>
