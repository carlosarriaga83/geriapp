<?php
/**
 * Migración: agrega columnas faltantes a historial_evoluciones
 *   - medico_nombre   VARCHAR(200)
 *   - fecha_consulta  DATE
 *   - hora_consulta   VARCHAR(5)
 */
require dirname(__DIR__) . '/conf/config.db.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Columnas actuales
$existing = $pdo->query("SHOW COLUMNS FROM historial_evoluciones")
                ->fetchAll(PDO::FETCH_COLUMN);

$toAdd = [
    'medico_nombre'  => "ALTER TABLE historial_evoluciones ADD COLUMN medico_nombre  VARCHAR(200) DEFAULT NULL COMMENT 'Médico que realiza la consulta' AFTER tipo",
    'fecha_consulta' => "ALTER TABLE historial_evoluciones ADD COLUMN fecha_consulta  DATE         DEFAULT NULL COMMENT 'Fecha real de la consulta'       AFTER medico_nombre",
    'hora_consulta'  => "ALTER TABLE historial_evoluciones ADD COLUMN hora_consulta   VARCHAR(5)   DEFAULT NULL COMMENT 'Hora HH:MM de la consulta'       AFTER fecha_consulta",
];

foreach ($toAdd as $col => $sql) {
    if (in_array($col, $existing)) {
        echo "– $col ya existe, omitido.\n";
        continue;
    }
    $pdo->exec($sql);
    echo "✓ Columna $col agregada.\n";
}

echo "Migración completada.\n";
