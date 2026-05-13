<?php
/**
 * Migración: crea la tabla de auditoría para QR Eventos GeriApp.
 * Ejecutar: php db/migrate_invitaciones_geriapp_eventos.php
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/models/GeriappEventInvitation.php';

$db = Database::getMaster();

try {
    GeriappEventInvitation::ensureEventTable($db);
    echo "✓ Tabla 'invitaciones_geriapp_eventos' creada o ya existe.\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}