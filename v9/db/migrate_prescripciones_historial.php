<?php
/**
 * GeriApp — Migración: agregar tipo 'prescripcion' a historial_evoluciones
 *
 * Ejecutar UNA SOLA VEZ desde la raíz del proyecto:
 *   php v2/db/migrate_prescripciones_historial.php
 *
 * O acceder vía navegador (solo superadmin autenticado).
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../conf/config.php';
    require_once __DIR__ . '/../auth/middleware.php';
    if (($_SESSION['user_rol'] ?? '') !== 'superadmin') {
        http_response_code(403);
        die('Acceso denegado');
    }
}

require_once dirname(__DIR__) . '/conf/config.db.php';
require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

try {
    // 1. Añadir 'prescripcion' al ENUM de historial_evoluciones.tipo
    $db->exec(
        "ALTER TABLE historial_evoluciones
         MODIFY COLUMN tipo
         ENUM('evolucion','nota_enfermeria','valoracion','interconsulta','prescripcion')
         NOT NULL DEFAULT 'evolucion'"
    );
    echo ($isCli ? "" : "<pre>") . "✓ historial_evoluciones.tipo ENUM ampliado con 'prescripcion'.\n";

    // 2. (Opcional) Crear índice en tipo para consultas de filtro
    try {
        $db->exec("ALTER TABLE historial_evoluciones ADD INDEX idx_hev_tipo (tipo)");
        echo "✓ Índice idx_hev_tipo creado.\n";
    } catch (\PDOException $e) {
        echo "  (idx_hev_tipo ya existía — omitido)\n";
    }

    echo "Migración completada correctamente.\n";
    if (!$isCli) echo "</pre>";
} catch (\PDOException $e) {
    $msg = "✗ Error durante la migración: " . $e->getMessage();
    echo ($isCli ? "" : "<pre style='color:red'>") . $msg . "\n";
    if (!$isCli) echo "</pre>";
    exit(1);
}
