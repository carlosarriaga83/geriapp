<?php
/**
 * GeriApp — Autoloader de modelos
 *
 * Incluye todos los modelos de la capa de datos de una sola vez.
 *
 * Uso desde cualquier página:
 *   require_once __DIR__ . '/db/models.php';   // desde la raíz v2/
 *   require_once __DIR__ . '/../db/models.php'; // desde subcarpetas (superadmin/, api/)
 *
 * Modelos disponibles:
 *   Usuario       → tabla usuarios
 *   Residente     → tabla residentes
 *   Prescripcion  → tabla prescripciones
 *   Institucion   → tabla instituciones          (solo superadmin)
 *   Plan          → tabla planes                 (solo superadmin)
 *   Configuracion → tabla configuracion
 *   Invitacion    → tabla invitaciones
 *   WaAPI         → cliente HTTP para waapi.app
 *   Mailer        → cliente SMTP nativo para envío de correos
 *   Log           → tabla logs_sistema
 */

$_models_dir = __DIR__ . '/models/';

require_once $_models_dir . 'Usuario.php';
require_once $_models_dir . 'UsuarioInstitucion.php';
require_once $_models_dir . 'Residente.php';
require_once $_models_dir . 'Prescripcion.php';
require_once $_models_dir . 'Institucion.php';
require_once $_models_dir . 'Plan.php';
require_once $_models_dir . 'Configuracion.php';
require_once $_models_dir . 'Invitacion.php';
require_once $_models_dir . 'WaAPI.php';
require_once $_models_dir . 'WaSenderAPI.php';
require_once $_models_dir . 'Mailer.php';
require_once $_models_dir . 'Log.php';
require_once $_models_dir . 'Reporte.php';
require_once $_models_dir . 'Cuidado.php';
require_once $_models_dir . 'Inventario.php';
require_once $_models_dir . 'UsuarioResidente.php';
require_once $_models_dir . 'ExpedienteDoc.php';

unset($_models_dir);
