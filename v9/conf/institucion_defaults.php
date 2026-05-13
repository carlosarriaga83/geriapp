<?php
/**
 * GeriApp — Perfil de configuración inicial por defecto
 * ------------------------------------------------------
 * Este archivo define los parámetros con los que se debería sembrar la
 * configuración de TODA institución nueva (tabla `configuracion` + documentos
 * legales). Los valores tomados como referencia provienen de la institución
 * "Casita de María" (institucion_id = 9), que es la configuración productiva
 * estable más usada.
 *
 * SECCIONES:
 *   - general         → zona horaria, idioma, formato de fecha, moneda, turnos
 *   - integraciones   → SMTP, WhatsApp, IA, backups
 *   - seguridad       → políticas de contraseña, sesiones, intentos
 *   - notificaciones  → eventos clínicos + canales habilitados
 *   - roles_permisos  → matriz de permisos por rol (admin/enfermero/medico/familiar)
 *   - terminos_condiciones → versión + HTML del documento legal
 *   - aviso_privacidad     → versión + HTML del documento legal
 *
 * Las claves coinciden 1:1 con las columnas de la tabla `configuracion`,
 * salvo `roles_permisos` (que se serializa a JSON al persistir) y los bloques
 * legales (que van a la tabla `documentos_legales`).
 *
 * USO TÍPICO (al crear institución nueva):
 *
 *   $defaults = require BASE_PATH . '/conf/institucion_defaults.php';
 *
 *   // 1) Crear fila de configuración con flatten de las secciones
 *   $cfgFlat = array_merge(
 *       $defaults['general'],
 *       $defaults['integraciones'],
 *       $defaults['seguridad'],
 *       $defaults['notificaciones'],
 *       ['roles_permisos' => json_encode($defaults['roles_permisos'])]
 *   );
 *   Configuracion::upsert($newInstId, $cfgFlat);
 *
 *   // 2) Sembrar documentos legales
 *   foreach (['terminos_condiciones', 'aviso_privacidad'] as $key) {
 *       $doc = $defaults[$key];
 *       $db->prepare("INSERT INTO documentos_legales
 *           (institucion_id, tipo, version, titulo, contenido, vigente, requiere_firma, creado_por)
 *           VALUES (?,?,?,?,?,?,?,?)")->execute([
 *           $newInstId, $doc['tipo'], $doc['version'], $doc['titulo'],
 *           $doc['contenido'], 1, $doc['requiere_firma'] ? 1 : 0, null
 *       ]);
 *   }
 *
 * IMPORTANTE: las credenciales sensibles y defaults globales se toman primero
 * desde `conf/.env` y, como compatibilidad, desde
 * `secretos/integraciones_defaults.json` (gitignored, Deny from all).
 * Si no existen o traen placeholders, los campos quedan vacíos y el admin de
 * la institución debe configurarlos manualmente tras el alta.
 */

$_legalDir = __DIR__ . '/institucion_defaults';

// Asegurar que .env esté cargado (SMTP_HOST, SMTP_PASS, WHATSAPP_API_KEY, DEEPSEEK_API_KEY).
// config.db.php ya lo hace al arrancar la app, pero si este archivo se incluye
// desde un contexto raro (cron sin bootstrap) lo cargamos explícitamente.
if (getenv('SMTP_PASS') === false && getenv('DB_HOST') === false) {
    $_envFile = __DIR__ . '/.env';
    if (is_readable($_envFile)) {
        foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
            $_line = trim($_line);
            if ($_line === '' || $_line[0] === '#' || strpos($_line, '=') === false) continue;
            [$_k, $_v] = array_map('trim', explode('=', $_line, 2));
            if (!array_key_exists($_k, $_ENV)) { $_ENV[$_k] = $_v; putenv("$_k=$_v"); }
        }
    }
}

// Helper: prefiere .env (real) → JSON secretos → ''.
$_pickSecret = function (string $envKey, string $jsonKey, array $jsonBag): string {
    $envVal = getenv($envKey);
    if (is_string($envVal) && $envVal !== '' && !str_starts_with($envVal, 'REEMPLAZAR_')) {
        return $envVal;
    }
    return (string)($jsonBag[$jsonKey] ?? '');
};

$_pickSecretAny = function (array $envKeys, string $jsonKey, array $jsonBag): string {
    foreach ($envKeys as $envKey) {
        $envVal = getenv($envKey);
        if (is_string($envVal)) {
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if ($envVal !== '' && !str_starts_with($envVal, 'REEMPLAZAR_')) {
                return $envVal;
            }
        }
    }
    return (string)($jsonBag[$jsonKey] ?? '');
};

$_pickEnv = function (array $envKeys, string $default = ''): string {
    foreach ($envKeys as $envKey) {
        $envVal = getenv($envKey);
        if (is_string($envVal)) {
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if ($envVal !== '' && !str_starts_with($envVal, 'REEMPLAZAR_')) {
                return $envVal;
            }
        }
    }
    return $default;
};

$_pickInt = function (array $envKeys, int $default) use ($_pickEnv): int {
    $value = $_pickEnv($envKeys, '');
    return $value !== '' ? (int)$value : $default;
};

$_pickBool = function (array $envKeys, int $default) use ($_pickEnv): int {
    $value = strtolower($_pickEnv($envKeys, ''));
    if ($value === '') return $default;
    return in_array($value, ['1', 'true', 'yes', 'on', 'si'], true) ? 1 : 0;
};

// Cargar secretos globales de integraciones (opcional, gitignored). Solo se usa
// como fallback si .env no tiene la variable correspondiente.
$_intSecrets = [];
$_intSecretsPath = dirname(__DIR__) . '/secretos/integraciones_defaults.json';
if (is_file($_intSecretsPath)) {
    $_raw = @file_get_contents($_intSecretsPath);
    $_dec = $_raw ? json_decode($_raw, true) : null;
    if (is_array($_dec)) {
        // Filtra placeholders ("REEMPLAZAR_*") y comentarios (claves que empiezan con _)
        foreach ($_dec as $k => $v) {
            if (str_starts_with((string)$k, '_')) continue;
            if (is_string($v) && str_starts_with($v, 'REEMPLAZAR_')) continue;
            $_intSecrets[$k] = $v;
        }
    }
}

$_smtpHostDefault = $_pickEnv(['SMTP_HOST', 'MAIL_HOST'], '');
$_smtpFromDefault = $_pickEnv(['SMTP_FROM_EMAIL', 'MAIL_FROM_ADDRESS'], 'info_geriapp@prepenv.com');
$_smtpReadyDefault = ($_smtpHostDefault !== '' && $_smtpFromDefault !== '') ? 1 : 0;

return [

    // ──────────────────────────────────────────────────────────────────
    // GENERAL
    // ──────────────────────────────────────────────────────────────────
    'general' => [
        'inst_nombre'      => '',                       // Se sobrescribe con el nombre real al crear
        'app_url'          => '',                       // Se autocompleta desde app_url() del helper
        'timezone'         => 'America/Mexico_City',    // Default seguro (Casita usa Etc/GMT+5)
        'idioma'           => 'es',
        'fecha_formato'    => 'DD-MM-YYYY',
        'moneda'           => 'MXN',

        // Turnos (mismos rangos que Casita de María)
        'turno_mat_inicio' => '07:00', 'turno_mat_fin' => '15:00', 'turno_mat_siglas' => 'TM',
        'turno_ves_inicio' => '15:00', 'turno_ves_fin' => '23:00', 'turno_ves_siglas' => 'TV',
        'turno_noc_inicio' => '23:00', 'turno_noc_fin' => '07:00', 'turno_noc_siglas' => 'TN',
    ],

    // ──────────────────────────────────────────────────────────────────
    // INTEGRACIONES (SMTP / WhatsApp / IA / Backups)
    // ──────────────────────────────────────────────────────────────────
    'integraciones' => [
        // SMTP — defaults desde .env; password desde .env → JSON → ''
        'smtp_host'         => $_smtpHostDefault,
        'smtp_port'         => $_pickInt(['SMTP_PORT', 'MAIL_PORT'], 465),
        'smtp_usuario'      => $_pickEnv(['SMTP_USER', 'SMTP_USUARIO', 'MAIL_USERNAME'], $_smtpFromDefault),
        'smtp_password'     => $_pickSecretAny(['SMTP_PASS', 'SMTP_PASSWORD', 'MAIL_PASSWORD'], 'smtp_password', $_intSecrets),
        'smtp_encriptacion' => strtoupper($_pickEnv(['SMTP_ENCRYPTION', 'MAIL_ENCRYPTION'], 'SSL')),
        'smtp_timeout'      => $_pickInt(['SMTP_TIMEOUT', 'MAIL_TIMEOUT'], 30),
        'smtp_sandbox'      => $_pickBool(['SMTP_SANDBOX'], 0),
        'smtp_from_email'   => $_smtpFromDefault,
        'smtp_from_nombre'  => $_pickEnv(['SMTP_FROM_NAME', 'MAIL_FROM_NAME'], 'GeriApp'),

        // WhatsApp — api_key/instance_id desde .env → JSON → ''
        'wa_proveedor'      => $_pickEnv(['WHATSAPP_PROVIDER', 'WA_PROVIDER'], 'wasender'),
        'wa_api_key'        => $_pickSecret('WHATSAPP_API_KEY', 'wa_api_key', $_intSecrets),
        'wa_instance_id'    => $_pickSecret('WHATSAPP_INSTANCE_ID', 'wa_instance_id', $_intSecrets),
        'wa_phone'          => $_pickEnv(['WHATSAPP_PHONE', 'WA_PHONE'], '+525574606871'),
        'wa_activo'         => $_pickBool(['WHATSAPP_ACTIVE', 'WA_ACTIVE'], 1),
        'wa_sandbox'        => $_pickBool(['WHATSAPP_SANDBOX', 'WA_SANDBOX'], 0),

        // IA — api_key desde .env → JSON → ''
        'ia_proveedor'      => $_pickEnv(['IA_PROVIDER', 'AI_PROVIDER'], 'deepseek'),
        'ia_api_key'        => $_pickSecret('DEEPSEEK_API_KEY', 'ia_api_key', $_intSecrets),
        'ia_modelo'         => $_pickEnv(['DEEPSEEK_MODEL', 'IA_MODEL'], 'deepseek-reasoner'),
        'ia_prompt'         => '',
        'ia_max_palabras'   => 100,

        // Backups
        'backup_frecuencia' => 'diario',
        'backup_hora'       => '02:00',

        // Email CC para correspondencia legal (ARCO, T&C, etc.)
        'legal_cc_email'    => $_pickEnv(['LEGAL_CC_EMAIL'], $_smtpFromDefault),

        // Teléfono de contacto del administrador para escalación humana
        // (usado por el Asistente IA cuando crea grupos de WhatsApp).
        'support_phone'     => $_pickEnv(['SUPPORT_PHONE', 'ADMIN_PHONE'], ''),
    ],

    // ──────────────────────────────────────────────────────────────────
    // SEGURIDAD
    // ──────────────────────────────────────────────────────────────────
    'seguridad' => [
        'seg_pass_min_len'     => 8,
        'seg_pass_expira_dias' => 0,        // 0 = sin expiración (Casita usa 900; conservador para alta nueva)
        'seg_2fa'              => 0,        // OFF por defecto; admin lo activa manualmente
        'seg_timeout_sesion'   => 60,       // minutos
        'seg_una_sesion'       => 60,
        'seg_log_accesos'      => 1,        // ON: trazabilidad por defecto
        'seg_max_intentos'     => 5,
        'seg_bloqueo_min'      => 20,
    ],

    // ──────────────────────────────────────────────────────────────────
    // NOTIFICACIONES (eventos clínicos + canales)
    // ──────────────────────────────────────────────────────────────────
    'notificaciones' => [
        // Switches por evento
        'notif_alertas'   => 0,
        'notif_bitacora'  => 0,
        'notif_familiar'  => 0,
        'notif_vitales'   => 0,
        'notif_meds'      => 0,
        'notif_caida'     => 0,
        'notif_condicion' => 0,

        // Canales habilitados (sólo "sistema" por defecto; email/wa requieren config)
        'notif_canal_sistema' => 1,
        'notif_canal_email'   => $_smtpReadyDefault,
        'notif_canal_wa'      => 0,
    ],

    // ──────────────────────────────────────────────────────────────────
    // ROLES Y PERMISOS (matriz por rol)
    // Se serializa a JSON al persistir en `configuracion.roles_permisos`.
    // perm_elements: control granular por perm-id (vacío = defaults del catálogo)
    // ──────────────────────────────────────────────────────────────────
    'perm_elements' => '{}', // JSON: { "perm_id": ["role1","role2"], ... }
    // ──────────────────────────────────────────────────────────────────
    'roles_permisos' => [
        'admin' => [
            // Visibilidad de secciones
            'ver_seccion_inicio'        => true,
            'ver_seccion_registros'     => true,
            'ver_seccion_medicacion'    => true,
            'ver_seccion_ficha'         => true,
            'ver_seccion_expediente'    => true,
            'ver_seccion_residentes'    => true,
            'ver_seccion_configuracion' => true,
            // Inicio
            'ver_cuidados_grid'         => true,
            'hacer_registros'           => true,
            'registrar_futuro'          => true,
            'ver_rx_tracker'            => true,
            'ver_rx_tracker_readonly'   => true,
            // Notas médicas
            'ver_notas_medico'          => true,
            'notas_medico_directo'      => false, // N/A: admin nunca está en modo solo lectura
            // Ficha del residente
            'mover_residente_institucion'=> true,
            // Expediente
            'editar_expediente'         => true,
            // Reportes
            'preview_reportes'          => true,
        ],
        'enfermero' => [
            'ver_seccion_inicio'        => true,
            'ver_seccion_registros'     => true,
            'ver_seccion_medicacion'    => true,
            'ver_seccion_ficha'         => true,
            'ver_seccion_expediente'    => true,
            'ver_seccion_residentes'    => false,
            'ver_seccion_configuracion' => false,
            'ver_cuidados_grid'         => true,
            'hacer_registros'           => true,
            'registrar_futuro'          => false,
            'ver_rx_tracker'            => true,
            'ver_rx_tracker_readonly'   => true,
            'ver_notas_medico'          => true,
            'notas_medico_directo'      => false, // N/A: enfermero siempre puede hacer registros
            'editar_residentes'         => false,
            'editar_familia'            => false,
        ],
        'medico' => [
            'ver_seccion_inicio'        => true,
            'ver_seccion_registros'     => true,
            'ver_seccion_medicacion'    => true,
            'ver_seccion_ficha'         => true,
            'ver_seccion_expediente'    => true,
            'ver_seccion_residentes'    => false,
            'ver_seccion_configuracion' => false,
            'ver_cuidados_grid'         => true,
            'hacer_registros'           => true,
            'registrar_futuro'          => false,
            'ver_rx_tracker'            => true,
            'ver_rx_tracker_readonly'   => true,
            'ver_notas_medico'          => true,
            'notas_medico_directo'      => false, // N/A: médico siempre puede hacer registros
            'editar_residentes'         => true,
            'editar_familia'            => false,
        ],
        'familiar' => [
            'ver_seccion_inicio'        => true,
            'ver_seccion_registros'     => true,
            'ver_seccion_medicacion'    => true,
            'ver_seccion_ficha'         => true,
            'ver_seccion_expediente'    => true,
            'ver_seccion_residentes'    => true,
            'ver_seccion_configuracion' => false,
            'ver_cuidados_grid'         => true,
            'hacer_registros'           => false,
            'registrar_futuro'          => false,
            'ver_rx_tracker'            => false,
            'ver_rx_tracker_readonly'   => true,
            'ver_notas_medico'          => true,
            'notas_medico_directo'      => true,  // familiar: ir directo a notas médicas (no al timeline)
            'editar_residentes'         => false,
        ],
    ],

    // ──────────────────────────────────────────────────────────────────
    // TÉRMINOS Y CONDICIONES (tabla documentos_legales, tipo='terminos')
    // El HTML vive en /conf/institucion_defaults/terminos_condiciones.html
    // ──────────────────────────────────────────────────────────────────
    'terminos_condiciones' => [
        'tipo'           => 'terminos',
        'version'        => '1.0',
        'titulo'         => 'Términos y Condiciones de Uso - GeriApp',
        'requiere_firma' => true,
        'vigente'        => true,
        'contenido'      => is_file($_legalDir . '/terminos_condiciones.html')
            ? file_get_contents($_legalDir . '/terminos_condiciones.html')
            : '',
    ],

    // ──────────────────────────────────────────────────────────────────
    // AVISO DE PRIVACIDAD (tabla documentos_legales, tipo='privacidad')
    // El HTML vive en /conf/institucion_defaults/aviso_privacidad.html
    // ──────────────────────────────────────────────────────────────────
    'aviso_privacidad' => [
        'tipo'           => 'privacidad',
        'version'        => '1.0',
        'titulo'         => 'Aviso de Privacidad Integral - GeriApp',
        'requiere_firma' => true,
        'vigente'        => true,
        'contenido'      => is_file($_legalDir . '/aviso_privacidad.html')
            ? file_get_contents($_legalDir . '/aviso_privacidad.html')
            : '',
    ],
];
