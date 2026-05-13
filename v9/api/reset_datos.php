<?php
/**
 * GeriApp — Reset de datos de la institución
 *
 * POST /api/reset_datos.php
 * Body: { "action": "reset_datos", "confirmacion": "<nombre de la institución>" }
 *
 * Elimina TODOS los datos clínicos/operativos de la institución:
 *   residentes, prescripciones, invitaciones, logs.
 * Conserva: usuarios, institución, planes, configuración.
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['admin', 'superadmin']);
api_require_method('POST');

$body   = api_body();
$instId = api_inst_id();

if (($body['action'] ?? '') !== 'reset_datos') {
    api_error('Acción no reconocida', 400);
}

// ── Verificar confirmación: el usuario debe escribir exactamente el nombre de la institución ──
$db       = Database::getInstance();
$instRow  = $db->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
$instRow->execute([$instId]);
$inst = $instRow->fetch(PDO::FETCH_ASSOC);

if (!$inst) {
    api_error('Institución no encontrada', 404);
}

$nombreEsperado = trim($inst['nombre']);
$confirmacion   = trim($body['confirmacion'] ?? '');

if ($confirmacion === '' || $confirmacion !== $nombreEsperado) {
    api_error('La confirmación no coincide con el nombre de la institución.', 422);
}

// ── Eliminar en orden seguro (respetar FKs) ────────────────────────────────
$counts = [];

try {
    $db->exec('SET foreign_key_checks = 0');

    // (bitacora_* and historial_* tables dropped in v1.26.0)

    // 1. Prescripciones
    $stmt = $db->prepare("DELETE FROM prescripciones WHERE institucion_id = ?");
    $stmt->execute([$instId]);
    $counts['prescripciones'] = $stmt->rowCount();

    // 2. Residentes
    $stmt = $db->prepare("DELETE FROM residentes WHERE institucion_id = ?");
    $stmt->execute([$instId]);
    $counts['residentes'] = $stmt->rowCount();

    // 9. Invitaciones pendientes
    $stmt = $db->prepare("DELETE FROM invitaciones WHERE institucion_id = ?");
    $stmt->execute([$instId]);
    $counts['invitaciones'] = $stmt->rowCount();

    // 10. Logs del sistema de esta institución
    $stmt = $db->prepare("DELETE FROM logs_sistema WHERE institucion_id = ?");
    $stmt->execute([$instId]);
    $counts['logs_sistema'] = $stmt->rowCount();

    $db->exec('SET foreign_key_checks = 1');

} catch (Exception $e) {
    $db->exec('SET foreign_key_checks = 1');
    api_error('Error durante el reset: ' . $e->getMessage(), 500);
}

// ── Registrar la acción en el log (después del reset, log está vacío así que insertamos directo) ──
try {
    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => $instId,
        'accion'         => 'datos_reset',
        'modulo'         => 'configuracion',
        'detalle'        => 'Reset completo de datos clínicos. Residentes eliminados: ' . $counts['residentes'],
    ]);
} catch (Exception $_) { /* Si falla el log, no es crítico */ }

$total = array_sum($counts);
api_ok(
    ['counts' => $counts, 'total' => $total],
    "Reset completado. Se eliminaron {$total} registros en total."
);
