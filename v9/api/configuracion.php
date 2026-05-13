<?php
/**
 * GeriApp — API /api/configuracion.php
 *
 * GET  /api/configuracion.php          → leer config de la institución
 * POST /api/configuracion.php          → guardar config (upsert por sección)
 *   body.seccion: 'smtp' | 'whatsapp' | 'notificaciones' | 'sistema'
 *
 * Roles: admin, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin', 'admin']);

$method = api_method();

// ─────────────────────────────────────────────────────────────────────────────
// GET ?action=scan_perm_ids — superadmin only, scan codebase for data-perm-id
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && ($_GET['action'] ?? '') === 'scan_perm_ids') {
    api_auth_roles(['superadmin']); // tighter gate

    $root     = dirname(__DIR__); // v9/
    $exts     = ['php', 'js', 'html'];
    $pattern  = '/data-perm-id=["\']([^"\']+)["\']/i';
    $found    = [];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iter as $file) {
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $exts, true)) continue;
        // Skip ignored dirs
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $rel = str_replace('\\', '/', $rel);
        if (str_starts_with($rel, 'uploads/') || str_starts_with($rel, 'vendor/') ||
            str_starts_with($rel, 'node_modules/') || str_starts_with($rel, '.')) continue;

        $content = file_get_contents($file->getPathname());
        if (!$content || mb_strpos($content, 'data-perm-id') === false) continue;

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $permId) {
                $permId = trim($permId);
                if (!$permId) continue;
                if (!isset($found[$permId])) {
                    $found[$permId] = ['id' => $permId, 'files' => []];
                }
                if (!in_array($rel, $found[$permId]['files'], true)) {
                    $found[$permId]['files'][] = $rel;
                }
            }
        }
    }

    ksort($found);
    api_ok(['perm_ids' => array_values($found)]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET — leer configuración
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $cfg = Configuracion::getCached(api_inst_id());
    if (!$cfg) api_error('Error al obtener configuración', 500);

    // §9.4 Enmascarar API keys y passwords sensibles en respuesta
    $sensitiveKeys = ['wa_api_key', 'ia_api_key', 'smtp_password'];
    $corrupted     = [];                       // campos cuyo valor real fue sobrescrito por la máscara
    foreach ($sensitiveKeys as $sk) {
        if (!empty($cfg[$sk]) && is_string($cfg[$sk])) {
            // Detectar corrupción: si el valor descifrado contiene '•' (U+2022),
            // significa que un save anterior guardó el valor enmascarado en la BD.
            if (mb_strpos($cfg[$sk], '•') !== false) {
                $corrupted[] = $sk;
                $cfg[$sk] = '';                // devolver vacío para forzar re-ingreso
                continue;
            }
            $len = mb_strlen($cfg[$sk]);
            if ($len > 8) {
                $cfg[$sk] = mb_substr($cfg[$sk], 0, 4) . str_repeat('•', $len - 8) . mb_substr($cfg[$sk], -4);
            } else {
                $cfg[$sk] = str_repeat('•', $len);
            }
        }
    }

    if (!empty($corrupted)) {
        $cfg['_corrupted_fields'] = $corrupted;
    }

    // Nombre del administrador principal de la institución (para hint UI).
    // `usuarios` vive en la BD master; mostrar el primer admin activo.
    try {
        $mdb = Database::getMaster();
        $stA = $mdb->prepare(
            "SELECT nombre, telefono, email FROM usuarios
             WHERE institucion_id = ? AND rol = 'admin' AND estado = 'activo'
             ORDER BY id ASC LIMIT 1"
        );
        $stA->execute([api_inst_id()]);
        if ($admin = $stA->fetch(PDO::FETCH_ASSOC)) {
            $cfg['_admin_name']  = (string)($admin['nombre'] ?? '');
            $cfg['_admin_phone'] = (string)($admin['telefono'] ?? '');
            $cfg['_admin_email'] = (string)($admin['email'] ?? '');
        }
    } catch (\Throwable $e) { /* no romper el GET por esto */ }

    api_ok($cfg);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — guardar sección
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body    = api_body();
    $seccion = $body['seccion'] ?? 'sistema';

    $secciones = [
        'smtp' => [
            'smtp_host', 'smtp_port', 'smtp_usuario', 'smtp_password',
            'smtp_encriptacion', 'smtp_timeout', 'smtp_sandbox',
            'smtp_from_email', 'smtp_from_nombre',
        ],
        'whatsapp' => [
            'wa_proveedor', 'wa_api_key', 'wa_instance_id', 'wa_phone', 'wa_activo', 'wa_sandbox', 'support_phone',
        ],
        'notificaciones' => [
            'notif_alertas', 'notif_bitacora', 'notif_familiar',
            'notif_vitales', 'notif_meds', 'notif_caida', 'notif_condicion',
            'notif_canal_sistema', 'notif_canal_email', 'notif_canal_wa',
        ],
        'turnos' => [
            'turno_mat_inicio', 'turno_mat_fin', 'turno_mat_siglas',
            'turno_ves_inicio', 'turno_ves_fin', 'turno_ves_siglas',
            'turno_noc_inicio', 'turno_noc_fin', 'turno_noc_siglas',
        ],
        'seguridad' => [
            'seg_pass_min_len', 'seg_pass_expira_dias',
            'seg_2fa', 'seg_timeout_sesion', 'seg_una_sesion', 'seg_log_accesos',
            'seg_max_intentos', 'seg_bloqueo_min',
        ],
        'sistema' => [
            'app_url', 'inst_nombre', 'moneda',
            'timezone', 'idioma', 'fecha_formato',
            'backup_frecuencia', 'backup_hora',
            'legal_cc_email',
            'support_phone',
        ],
        'roles' => [
            'roles_permisos',  // JSON
            'perm_elements',   // JSON — per-element UI access control (superadmin)
        ],
        'ia' => [
            'ia_proveedor', 'ia_api_key', 'ia_modelo', 'ia_prompt', 'ia_max_palabras',
        ],
    ];

    if (!isset($secciones[$seccion])) {
        api_error("Sección no válida. Usa: " . implode(', ', array_keys($secciones)), 400);
    }

    $campos = $secciones[$seccion];
    $data   = [];

    foreach ($campos as $campo) {
        if (array_key_exists($campo, $body)) {
            $val = $body[$campo];

            // No sobreescribir contraseñas/keys si llegan enmascaradas o vacías
            // Detectar CUALQUIER • (U+2022) en el valor — la máscara de contraseñas
            // cortas puede tener solo 1-3 bullets y no matchear '••••'.
            if (in_array($campo, ['smtp_password', 'wa_api_key', 'ia_api_key'], true)
                && ($val === '' || mb_strpos((string)$val, '•') !== false)) {
                continue;
            }

            // JSON fields: encode to string for DB storage
            if ($campo === 'roles_permisos') {
                $data[$campo] = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
                continue;
            }
            if ($campo === 'perm_elements') {
                $data[$campo] = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
                continue;
            }

            $data[$campo] = is_string($val) ? trim($val) : $val;
        }
    }

    if (empty($data)) api_error('Sin campos para guardar', 422);

    $ok = Configuracion::upsert(api_inst_id(), $data);
    if (!$ok) api_error('Error al guardar configuración', 500);

    Configuracion::clearCache(api_inst_id());

    // Si se actualizó el timeout de sesión, refrescar el valor en la sesión
    // actual para que el cambio tenga efecto inmediato (sin necesidad de
    // re-login). El admin que cambia 30→60 min vería la sesión expirar a
    // los 30 min sin esto.
    if ($seccion === 'seguridad' && array_key_exists('seg_timeout_sesion', $data)) {
        $newMin = max(5, (int)$data['seg_timeout_sesion']);
        $_SESSION['_cfg_session_timeout'] = $newMin * 60;
        $_SESSION['_last_activity'] = time();
    }

    try {
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'configuracion_actualizar_general',
            'modulo'         => 'configuracion',
            'detalle'        => "Sección: {$seccion}",
        ]);
    } catch (Throwable $e) {
        error_log('[GeriApp] Configuracion log no fatal: ' . $e->getMessage());
    }

    api_ok(null, "Configuración de '{$seccion}' guardada correctamente");
}

// ───────────────────────────────────────────────────────────────────────────────
// PUT /api/configuracion.php?action=test_wa  → probar conexión WaAPI
// ───────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $action = $_GET['action'] ?? '';

    if ($action === 'test_smtp') {
        $body      = api_body();
        $destEmail = trim($body['dest_email'] ?? '');

        if (!$destEmail || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
            api_error('Correo destino inválido o faltante', 422);
        }

        // Usar credenciales del body si se proveen (antes de guardar)
        $cfg = Configuracion::getOrCreate(api_inst_id());

        // Sobreescribir con valores del body si son diferentes a los enmascarados
        $overrides = ['smtp_host','smtp_port','smtp_encriptacion','smtp_timeout','smtp_usuario',
                      'smtp_from_email','smtp_from_nombre'];
        foreach ($overrides as $k) {
            if (!empty($body[$k])) $cfg[$k] = $body[$k];
        }
        // Contraseña: solo actualizar si llega texto real (no enmascarado)
        if (!empty($body['smtp_password']) && mb_strpos($body['smtp_password'], '•') === false) {
            $cfg['smtp_password'] = $body['smtp_password'];
        }

        $mailer = new Mailer($cfg);
        $result = $mailer->testConnection($destEmail);

        if (!$result['ok']) {
            api_error($result['error'] ?? 'Error de conexión SMTP', 502, ['smtp_log' => $result['log']]);
        }

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'smtp_probar',
            'modulo'         => 'configuracion',
            'detalle'        => "Prueba SMTP → {$destEmail}",
        ]);

        api_ok(['smtp_log' => $result['log']], 'Correo de prueba enviado exitosamente');
    }

    if ($action === 'test_wa') {
        $body      = api_body();
        $provider  = trim($body['wa_proveedor']   ?? '');
        $token     = trim($body['wa_api_key']     ?? '');
        $instId    = trim($body['wa_instance_id'] ?? '');
        $destPhone = trim($body['dest_phone']     ?? '');

        // Si el token viene enmascarado, usar el almacenado
        if (mb_strpos($token, '•') !== false || !$token) {
            $cfg = Configuracion::getOrCreate(api_inst_id());
            $token = $cfg['wa_api_key'] ?? '';
            if (!$instId || mb_strpos($instId, '•') !== false) $instId = $cfg['wa_instance_id'] ?? '';
        }

        if (!$token)     api_error('Falta el Token / API Key', 422);
        if (!$destPhone) api_error('Falta número de destino para la prueba', 422);

        // Seleccionar driver según proveedor
        if ($provider === 'wasender') {
            $wa = new WaSenderAPI($token);
            $logDetalle = "Prueba WaSenderAPI → {$destPhone}";
        } else {
            // waapi.app (y legacy sin proveedor)
            if (!$instId) api_error('Falta el Instance ID', 422);
            $wa = new WaAPI($token, $instId);
            $logDetalle = "Prueba WaAPI instancia {$instId} → {$destPhone}";
        }

        $result = $wa->testConnection($destPhone);

        if (!$result['ok']) {
            api_error($result['error'] ?? 'Error de conexión con la API de WhatsApp', 502);
        }

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'whatsapp_probar',
            'modulo'         => 'configuracion',
            'detalle'        => $logDetalle,
        ]);

        api_ok(['message_id' => $result['message_id']], 'Mensaje de prueba enviado exitosamente');
    }

    // PUT /api/configuracion.php?action=ai_rewrite  → re-redactar texto con IA
    if ($action === 'ai_rewrite') {
        $body = api_body();
        $text = trim($body['text'] ?? '');
        $context = trim($body['context'] ?? 'general');
        if (!$text) api_error('Texto requerido', 422);
        if (mb_strlen($text) > 2000) api_error('Texto demasiado largo (máx 2000 caracteres)', 422);

        $instId = api_inst_id();
        $cfg = Configuracion::getCached($instId);
        $provider = $cfg['ia_proveedor'] ?? 'openai';
        $apiKey   = $cfg['ia_api_key'] ?? '';
        $model    = $cfg['ia_modelo'] ?? '';
        if (!$apiKey) api_error('No se ha configurado la API Key de IA. Ve a Configuración > Inteligencia Artificial.', 422);

        $contextHints = [
            'notif_titulo' => 'Es el título de una notificación para el personal de una residencia geriátrica. Debe ser corto (máx ~60 caracteres), claro y directo.',
            'notif_mensaje' => 'Es el mensaje de una notificación para el personal de una residencia geriátrica. Debe ser claro, profesional y conciso.',
            'observacion' => 'Es una observación clínica sobre un residente de una residencia geriátrica. Debe ser clara, profesional y usar terminología adecuada de enfermería/cuidados.',
        ];
        $ctxHint = $contextHints[$context] ?? 'Es un texto en el contexto de una residencia geriátrica.';

        $systemPrompt = "Eres un asistente de redacción para una residencia geriátrica. Tu tarea es mejorar la claridad y profesionalismo del texto del usuario sin cambiar su significado. Responde SOLAMENTE con el texto mejorado, sin explicaciones ni comillas.";
        $userPrompt = "Mejora la redacción del siguiente texto. {$ctxHint}\n\nTexto original:\n{$text}";

        $endpoints = [
            'openai'   => 'https://api.openai.com/v1/chat/completions',
            'gemini'   => 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model ?: 'gemini-2.0-flash') . ':generateContent?key=' . urlencode($apiKey),
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
        ];
        $url = $endpoints[$provider] ?? $endpoints['openai'];

        if ($provider === 'gemini') {
            $payload = json_encode([
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['parts' => [['text' => $userPrompt]]]],
                'generationConfig' => ['maxOutputTokens' => 512, 'temperature' => 0.5],
            ]);
            $headers = ['Content-Type: application/json'];
        } else {
            $payload = json_encode([
                'model'    => $model ?: ($provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => 512,
                'temperature' => 0.5,
            ]);
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) api_error('Error de conexión: ' . $curlErr, 502);
        if ($httpCode < 200 || $httpCode >= 300) {
            $errBody = json_decode($response, true);
            $errMsg  = $errBody['error']['message'] ?? "HTTP $httpCode";
            api_error('Error IA: ' . $errMsg, 502);
        }

        $result = json_decode($response, true);
        if ($provider === 'gemini') {
            $rewritten = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $rewritten = $result['choices'][0]['message']['content'] ?? '';
        }
        $rewritten = trim($rewritten, " \n\r\t\"'");
        if (empty($rewritten)) api_error('La IA no generó respuesta', 502);

        api_ok(['rewritten' => $rewritten]);
    }

    if ($action === 'test_ia') {
        $body     = api_body();
        $provider = trim($body['ia_proveedor'] ?? 'openai');
        $apiKey   = trim($body['ia_api_key'] ?? '');
        $model    = trim($body['ia_modelo'] ?? '');

        if (!$apiKey || mb_strpos($apiKey, '•') !== false) {
            // Usar key almacenada si llega enmascarada
            $cfg = Configuracion::getOrCreate(api_inst_id());
            $apiKey = $cfg['ia_api_key'] ?? '';
        }
        if (!$apiKey) api_error('Falta API Key', 422);

        // Simple connectivity test using a minimal request
        $endpoints = [
            'openai'   => 'https://api.openai.com/v1/models',
            'gemini'   => 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey),
            'deepseek' => 'https://api.deepseek.com/v1/models',
        ];
        $url = $endpoints[$provider] ?? $endpoints['openai'];

        $headers = ['Content-Type: application/json'];
        if ($provider !== 'gemini') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            api_ok(null, 'Conexión exitosa con ' . $provider);
        } else {
            api_error('Error de conexión: HTTP ' . $httpCode, 502);
        }
    }

    api_error('Acción no reconocida', 400);
}

// ─────────────────────────────────────────────────────────────────────────────
// PATCH — acciones especiales: perfil, institución, backup
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PATCH') {
    $action = $_GET['action'] ?? '';

    // ── Guardar Mi Perfil ──────────────────────────────────────────────────
    if ($action === 'perfil') {
        $body   = api_body();
        $userId = api_user_id();

        $updateData = [];
        if (!empty($body['nombre']))   $updateData['nombre']   = trim($body['nombre']);
        if (isset($body['telefono']))  $updateData['telefono'] = trim($body['telefono']);

        // Cambio de correo
        if (!empty($body['email'])) {
            $newEmail = trim(strtolower($body['email']));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                api_error('Correo electrónico inválido', 422);
            }
            // Verificar que no esté en uso por otro usuario
            $existing = Usuario::findByEmail($newEmail);
            if ($existing && (int)$existing['id'] !== $userId) {
                api_error('Ese correo ya está registrado por otro usuario', 409);
            }
            $updateData['email'] = $newEmail;
        }

        // Cambio de contraseña
        if (!empty($body['password_nueva'])) {
            $passActual = $body['password_actual'] ?? '';
            $passNueva  = $body['password_nueva'];
            $passConfirm = $body['password_confirmar'] ?? '';

            if ($passNueva !== $passConfirm) {
                api_error('Las contraseñas nuevas no coinciden', 422);
            }
            if (strlen($passNueva) < 6) {
                api_error('La contraseña debe tener al menos 6 caracteres', 422);
            }

            // Verificar contraseña actual
            $user = Usuario::getById($userId);
            if (!$user || !password_verify($passActual, $user['password_hash'])) {
                api_error('La contraseña actual es incorrecta', 403);
            }

            $updateData['password_hash'] = password_hash($passNueva, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($updateData)) api_error('Sin cambios que guardar', 422);

        $ok = Usuario::update($userId, $updateData);
        if (!$ok) api_error('Error al actualizar perfil', 500);

        // Actualizar sesión si cambió nombre o email
        if (!empty($updateData['nombre'])) $_SESSION['user_nombre'] = $updateData['nombre'];
        if (!empty($updateData['email']))  $_SESSION['user_email']  = $updateData['email'];

        Log::registrar([
            'usuario_id'     => $userId,
            'institucion_id' => api_inst_id(),
            'accion'         => 'perfil_actualizar',
            'modulo'         => 'configuracion',
            'detalle'        => 'Perfil de usuario actualizado',
        ]);

        api_ok(null, 'Perfil actualizado correctamente');
    }

    // ── Guardar datos de Institución ───────────────────────────────────────
    if ($action === 'institucion') {
        $body   = api_body();
        $instId = api_inst_id();

        $allowed = ['nombre', 'telefono', 'direccion', 'ciudad', 'estado_inst',
                    'rfc', 'num_camas', 'timezone', 'email_admin'];
        $data = [];
        foreach ($allowed as $campo) {
            if (array_key_exists($campo, $body)) {
                $data[$campo] = is_string($body[$campo]) ? trim($body[$campo]) : $body[$campo];
            }
        }

        if (empty($data)) api_error('Sin campos para guardar', 422);

        $ok = Institucion::update($instId, $data);
        if (!$ok) api_error('Error al guardar datos de la institución', 500);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'institucion_actualizar',
            'modulo'         => 'configuracion',
            'detalle'        => 'Datos de institución actualizados',
        ]);

        api_ok(null, 'Datos de la institución guardados correctamente');
    }

    // ── Descargar Backup ────────────────────────────────────────────────────
    if ($action === 'backup') {
        /**
         * Genera un respaldo SQL autocontenido que puede restaurarse en una
         * base de datos que NO existe aún:
         *   1. CREATE DATABASE IF NOT EXISTS + USE
         *   2. CREATE TABLE IF NOT EXISTS (DDL real via SHOW CREATE TABLE)
         *   3. INSERT IGNORE de los datos filtrados por institución
         *
         * Orden de tablas respeta dependencias FK:
         *   planes → instituciones → usuarios → invitaciones
         *   → residentes → prescripciones
         *   → configuracion → logs_sistema
         */
        $instId = api_inst_id();

        require_once dirname(__DIR__) . '/db/Database.php';
        require_once dirname(__DIR__) . '/conf/config.db.php';
        $db    = Database::getInstance();
        $dbName = DB_NAME;

        // Obtener nombre de la institución para el encabezado
        $stmtInst = $db->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
        $stmtInst->execute([$instId]);
        $instNombre = $stmtInst->fetchColumn() ?: "Institución #{$instId}";

        // ── Definición de tablas: [nombre => query para obtener filas] ──────
        // El orden importa para respetar FKs al restaurar.
        $tableDefs = [
            // Tablas de referencia global (solo filas relevantes a la institución)
            'planes' => [
                'query'  => "SELECT p.* FROM planes p
                             JOIN instituciones i ON i.plan_id = p.id
                             WHERE i.id = ?",
                'params' => [$instId],
            ],
            'instituciones' => [
                'query'  => "SELECT * FROM instituciones WHERE id = ?",
                'params' => [$instId],
            ],
            'usuarios' => [
                'query'  => "SELECT * FROM usuarios WHERE institucion_id = ? OR (rol = 'superadmin' AND id IN (
                                 SELECT DISTINCT usuario_id FROM logs_sistema WHERE institucion_id = ? LIMIT 100
                             ))",
                'params' => [$instId, $instId],
            ],
            'invitaciones' => [
                'query'  => "SELECT * FROM invitaciones WHERE institucion_id = ?",
                'params' => [$instId],
            ],
            // Datos clínicos de la institución
            'residentes' => [
                'query'  => "SELECT * FROM residentes WHERE institucion_id = ?",
                'params' => [$instId],
            ],
            'prescripciones' => [
                'query'  => "SELECT * FROM prescripciones WHERE institucion_id = ?",
                'params' => [$instId],
            ],
            'bitacora_turnos' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'bitacora_entradas' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'bitacora_rx_administraciones' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'historial_expedientes' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'historial_evoluciones' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'historial_documentos' => [
                'query'  => "SELECT 1 WHERE 0", 'params' => [], // dropped in v1.26.0
            ],
            'configuracion' => [
                'query'  => "SELECT * FROM configuracion WHERE institucion_id = ?",
                'params' => [$instId],
            ],
            'logs_sistema' => [
                'query'  => "SELECT * FROM logs_sistema WHERE institucion_id = ? ORDER BY id DESC LIMIT 5000",
                'params' => [$instId],
            ],
        ];

        // ── Cabecera del archivo ─────────────────────────────────────────────
        $sql  = "-- ============================================================\n";
        $sql .= "-- GeriApp — Respaldo completo\n";
        $sql .= "-- Institución : {$instNombre} (ID: {$instId})\n";
        $sql .= "-- Base de datos: {$dbName}\n";
        $sql .= "-- Generado el  : " . date('Y-m-d H:i:s') . " (UTC" . date('P') . ")\n";
        $sql .= "-- ============================================================\n";
        $sql .= "-- Este archivo crea la base de datos si no existe,\n";
        $sql .= "-- crea las tablas si no existen y luego inserta los datos.\n";
        $sql .= "-- Para restaurar:  mysql -u usuario -p < archivo.sql\n";
        $sql .= "-- ============================================================\n\n";

        // ── Crear / seleccionar base de datos ────────────────────────────────
        $sql .= "CREATE DATABASE IF NOT EXISTS `{$dbName}`\n";
        $sql .= "  DEFAULT CHARACTER SET utf8mb4\n";
        $sql .= "  DEFAULT COLLATE utf8mb4_unicode_ci;\n\n";
        $sql .= "USE `{$dbName}`;\n\n";

        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET time_zone = '+00:00';\n";
        $sql .= "SET foreign_key_checks = 0;\n";
        $sql .= "SET sql_mode = 'NO_ENGINE_SUBSTITUTION';\n\n";

        // ── DDL + datos por tabla ────────────────────────────────────────────
        foreach ($tableDefs as $table => $def) {
            $sql .= "-- ------------------------------------------------------------\n";
            $sql .= "-- Tabla: `{$table}`\n";
            $sql .= "-- ------------------------------------------------------------\n";

            // 1) Obtener DDL real con SHOW CREATE TABLE
            try {
                $ddlStmt = $db->query("SHOW CREATE TABLE `{$table}`");
                $ddlRow  = $ddlStmt->fetch(PDO::FETCH_NUM);
                if ($ddlRow) {
                    // Convertir CREATE TABLE → CREATE TABLE IF NOT EXISTS
                    $ddl = preg_replace(
                        '/^CREATE TABLE\s+`?' . preg_quote($table, '/') . '`?/i',
                        "CREATE TABLE IF NOT EXISTS `{$table}`",
                        trim($ddlRow[1])
                    );
                    $sql .= $ddl . ";\n\n";
                }
            } catch (Exception $e) {
                $sql .= "-- ADVERTENCIA: no se pudo obtener DDL de {$table}: " . $e->getMessage() . "\n\n";
            }

            // 2) Obtener y volcar datos
            try {
                $stmt = $db->prepare($def['query']);
                $stmt->execute($def['params']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    $sql .= "-- (sin datos)\n\n";
                    continue;
                }

                $sql .= "-- " . count($rows) . " fila(s)\n";
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';

                foreach ($rows as $row) {
                    $vals = array_map(function ($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote((string)$v);
                    }, array_values($row));
                    $sql .= "INSERT IGNORE INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sql .= "\n";

            } catch (Exception $e) {
                $sql .= "-- ERROR al exportar datos de {$table}: " . $e->getMessage() . "\n\n";
            }
        }

        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "SET foreign_key_checks = 1;\n";
        $sql .= "-- ============================================================\n";
        $sql .= "-- Fin del respaldo\n";
        $sql .= "-- ============================================================\n";

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => $instId,
            'accion'         => 'backup_descargar_sql',
            'modulo'         => 'configuracion',
            'detalle'        => 'Backup SQL completo (con DDL) generado y descargado',
        ]);

        $filename = 'geriapp_backup_inst' . $instId . '_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $sql;
        exit;
    }

    api_error('Acción no reconocida', 400);
}
