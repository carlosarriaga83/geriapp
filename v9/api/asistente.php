<?php
/**
 * GeriApp — API /api/asistente.php
 *
 * Asistente de chat IA flotante.
 *   GET  ?action=list                          → lista conversaciones del usuario
 *   GET  ?action=get&conv_id=N                 → mensajes de una conversación
 *   POST action=send                           → manda mensaje + respuesta IA
 *   POST action=delete&conv_id=N               → borra conversación
 *   POST action=rename                         → renombra conversación
 *
 * Roles: todos los autenticados (admin, médico, enfermero, familiar, superadmin).
 *
 * Privacidad: contenido encriptado vía EncryptionMap. Auditado en bitácora.
 */

require_once __DIR__ . '/helpers.php';

api_auth();

$rol = api_rol();
$validRoles = ['superadmin', 'admin', 'medico', 'enfermero', 'familiar'];
if (!in_array($rol, $validRoles, true)) api_error('Rol no autorizado', 403);

$method = api_method();
$action = trim($_REQUEST['action'] ?? ($_GET['action'] ?? ''));
if ($method === 'POST') {
    $body = api_body();
    $action = trim($body['action'] ?? $action);
}

$userId = api_user_id();
$instId = api_inst_id();
if ($instId <= 0) api_error('Institución no resuelta', 422);

$tdb = Database::getTenant($instId);

// ════════════════════════════════════════════════════════════════════════════
// GET — list / get
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'list') {
    $st = $tdb->prepare(
        "SELECT id, titulo, creado_at, actualizado_at,
                (SELECT COUNT(*) FROM chat_asistente_mensajes m WHERE m.conversacion_id = c.id) AS msg_count
         FROM chat_asistente_conv c
         WHERE c.institucion_id = ? AND c.usuario_id = ?
         ORDER BY c.actualizado_at DESC
         LIMIT 50"
    );
    $st->execute([$instId, $userId]);
    api_ok(['conversaciones' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $action === 'get') {
    $convId = api_int('conv_id');
    if ($convId <= 0) api_error('conv_id requerido', 422);
    $own = _conv_owner($tdb, $convId, $instId, $userId);
    if (!$own) api_error('Conversación no encontrada', 404);

    $st = $tdb->prepare(
        "SELECT id, rol, contenido, contenido_enc, creado_at
         FROM chat_asistente_mensajes
         WHERE conversacion_id = ? AND rol IN ('user','assistant')
         ORDER BY creado_at ASC, id ASC"
    );
    $st->execute([$convId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r = EncryptionMap::decryptRow('chat_asistente_mensajes', $r);
        unset($r['contenido_enc']);
    }
    unset($r);
    api_ok(['conversacion' => $own, 'mensajes' => $rows]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST — send / delete / rename
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'delete') {
    $convId = (int)($body['conv_id'] ?? 0);
    if ($convId <= 0) api_error('conv_id requerido', 422);
    $own = _conv_owner($tdb, $convId, $instId, $userId);
    if (!$own) api_error('Conversación no encontrada', 404);

    $tdb->prepare("DELETE FROM chat_asistente_conv WHERE id = ? AND institucion_id = ? AND usuario_id = ?")
        ->execute([$convId, $instId, $userId]);

    Log::registrar([
        'accion'         => 'asistente_borrar_conv',
        'modulo'         => 'asistente',
        'usuario_id'     => $userId,
        'institucion_id' => $instId,
        'detalle'        => json_encode(['conv_id' => $convId]),
        'estado'         => 'ok',
    ]);
    api_ok();
}

if ($method === 'POST' && $action === 'rename') {
    $convId = (int)($body['conv_id'] ?? 0);
    $titulo = mb_substr(trim((string)($body['titulo'] ?? '')), 0, 180);
    if ($convId <= 0) api_error('conv_id requerido', 422);
    if ($titulo === '') api_error('titulo requerido', 422);
    $own = _conv_owner($tdb, $convId, $instId, $userId);
    if (!$own) api_error('Conversación no encontrada', 404);

    $tdb->prepare("UPDATE chat_asistente_conv SET titulo = ? WHERE id = ?")
        ->execute([$titulo, $convId]);
    api_ok();
}

if ($method === 'POST' && $action === 'send') {
    $message = trim((string)($body['message'] ?? ''));
    $convId  = (int)($body['conv_id'] ?? 0);
    $resHint = (int)($body['residente_id'] ?? 0);

    if ($message === '') api_error('Mensaje vacío', 422);
    if (mb_strlen($message) > 4000) api_error('Mensaje demasiado largo (máx. 4000 caracteres)', 422);

    // Rate limit por usuario: máx 30 mensajes/hora
    _rate_limit($tdb, $userId, $instId);

    // Conversación: validar o crear
    if ($convId > 0) {
        $own = _conv_owner($tdb, $convId, $instId, $userId);
        if (!$own) api_error('Conversación no encontrada', 404);
    } else {
        $titulo = mb_substr($message, 0, 60);
        $st = $tdb->prepare("INSERT INTO chat_asistente_conv (institucion_id, usuario_id, titulo) VALUES (?, ?, ?)");
        $st->execute([$instId, $userId, $titulo]);
        $convId = (int)$tdb->lastInsertId();
    }

    // Insertar mensaje del usuario (encriptado)
    _insert_msg($tdb, $convId, 'user', $message);

    // Cargar config IA
    $cfg = Configuracion::getCached($instId);
    $provider = $cfg['ia_proveedor'] ?? 'openai';
    $apiKey   = $cfg['ia_api_key'] ?? '';
    $model    = $cfg['ia_modelo'] ?? '';
    if (!$apiKey) {
        api_error('No se ha configurado la API Key de IA. Pide a un administrador que la configure en Configuración → Inteligencia Artificial.', 422);
    }

    // Construir contexto
    $ctx = _build_context($tdb, $instId, $userId, $resHint, $cfg);

    // Cargar historial reciente (últimos 12 mensajes)
    $stHist = $tdb->prepare(
        "SELECT rol, contenido, contenido_enc FROM chat_asistente_mensajes
         WHERE conversacion_id = ? AND rol IN ('user','assistant')
         ORDER BY creado_at DESC, id DESC LIMIT 12"
    );
    $stHist->execute([$convId]);
    $hist = array_reverse($stHist->fetchAll(PDO::FETCH_ASSOC));
    foreach ($hist as &$h) {
        $h = EncryptionMap::decryptRow('chat_asistente_mensajes', $h);
    }
    unset($h);

    // Llamar a la IA
    [$reply, $err] = _ai_call($provider, $apiKey, $model, $ctx['system'], $hist);
    if ($err) {
        Log::registrar([
            'accion' => 'asistente_error_ia', 'modulo' => 'asistente',
            'usuario_id' => $userId, 'institucion_id' => $instId,
            'detalle' => json_encode(['conv_id' => $convId, 'err' => $err]),
            'estado' => 'error',
        ]);
        api_error('Error de la IA: ' . $err, 502);
    }

    // Persistir respuesta (limpia, sin tags <recordar>)
    [$reply, $memorias] = _extract_memories($reply);
    if (!empty($memorias)) {
        _save_memories($tdb, $instId, $userId, $memorias);
    }

    // Escalamiento a humano: la IA emite <escalar_humano motivo="...">resumen</escalar_humano>
    [$reply, $escalation] = _extract_escalation($reply);
    if ($escalation) {
        try {
            $escRes = _handle_escalation($tdb, $instId, $userId, $resHint, $escalation);
            if (!empty($escRes['ok'])) {
                $msg = "\n\n✅ Listo: abrí un grupo de WhatsApp";
                if (!empty($escRes['admin_name'])) $msg .= " con *{$escRes['admin_name']}*";
                $msg .= " para apoyarte. Revisa tu WhatsApp en unos segundos.";
                if (!empty($escRes['invite_link'])) {
                    $msg .= "\n\nSi no recibes la invitación, usa este enlace: " . $escRes['invite_link'];
                }
                $reply = trim($reply) . $msg;
            } else {
                $reply = trim($reply) . "\n\n⚠️ No pude crear el grupo de WhatsApp: " . ($escRes['error'] ?? 'error desconocido');
            }
        } catch (\Throwable $e) {
            $reply = trim($reply) . "\n\n⚠️ No pude crear el grupo de WhatsApp en este momento. Intenta de nuevo o escribe directamente a tu administrador.";
            Log::registrar([
                'accion' => 'asistente_escalacion_error', 'modulo' => 'asistente',
                'usuario_id' => $userId, 'institucion_id' => $instId,
                'detalle' => json_encode(['err' => $e->getMessage()]),
                'estado' => 'error',
            ]);
        }
    }

    _insert_msg($tdb, $convId, 'assistant', $reply);
    $tdb->prepare("UPDATE chat_asistente_conv SET actualizado_at = NOW() WHERE id = ?")->execute([$convId]);

    Log::registrar([
        'accion' => 'asistente_mensaje', 'modulo' => 'asistente',
        'usuario_id' => $userId, 'institucion_id' => $instId,
        'detalle' => json_encode(['conv_id' => $convId, 'len_in' => mb_strlen($message), 'len_out' => mb_strlen($reply)]),
        'estado' => 'ok',
    ]);

    api_ok([
        'conv_id' => $convId,
        'reply'   => $reply,
    ]);
}

api_error('Acción no reconocida', 400);

// ════════════════════════════════════════════════════════════════════════════
// Helpers internos
// ════════════════════════════════════════════════════════════════════════════

function _conv_owner(PDO $tdb, int $convId, int $instId, int $userId): ?array {
    $st = $tdb->prepare(
        "SELECT id, titulo, creado_at, actualizado_at
         FROM chat_asistente_conv
         WHERE id = ? AND institucion_id = ? AND usuario_id = ?"
    );
    $st->execute([$convId, $instId, $userId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function _insert_msg(PDO $tdb, int $convId, string $rol, string $contenido): int {
    $row = ['contenido' => $contenido];
    $enc = EncryptionMap::encryptRow('chat_asistente_mensajes', $row);
    $st = $tdb->prepare(
        "INSERT INTO chat_asistente_mensajes (conversacion_id, rol, contenido, contenido_enc)
         VALUES (?, ?, ?, ?)"
    );
    $st->execute([
        $convId, $rol,
        $enc['contenido']     ?? $contenido,
        $enc['contenido_enc'] ?? null,
    ]);
    return (int)$tdb->lastInsertId();
}

function _rate_limit(PDO $tdb, int $userId, int $instId): void {
    $st = $tdb->prepare(
        "SELECT COUNT(*) FROM chat_asistente_mensajes m
         JOIN chat_asistente_conv c ON c.id = m.conversacion_id
         WHERE c.usuario_id = ? AND c.institucion_id = ?
           AND m.rol = 'user'
           AND m.creado_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $st->execute([$userId, $instId]);
    $count = (int)$st->fetchColumn();
    if ($count >= 30) {
        api_error('Has alcanzado el límite de 30 mensajes por hora. Inténtalo más tarde.', 429);
    }
}

/**
 * Construye el system prompt con info de rol, instituciones, secciones disponibles
 * y opcionalmente datos del residente activo.
 *
 * Privilegios por rol (alineados con cd-cuidados.php / sidebar / forms):
 *  - superadmin: todo + paneles de administración global.
 *  - admin:     gestión completa de su institución (residentes, personal, expediente,
 *               medicación, inventario, configuración, exportes, notas de turno, signos).
 *               Puede ver notas médicas pero NO crearlas/firmarlas.
 *  - medico:    expediente clínico completo, prescripciones, notas médicas (crear/firmar),
 *               valoraciones, lectura de registros, sin gestión de personal/configuración.
 *  - enfermero: registros operativos del día (sueño, alimentación, eliminación, higiene,
 *               medicación-suministrar, signos vitales, movilidad, terapia, comportamiento,
 *               incidente, notas de turno). Lectura de expediente. NO firma notas médicas.
 *  - familiar:  vista de su residente vinculado: dashboard, registros, expediente (lectura),
 *               ficha (lectura). Sin formularios de registro ni administración.
 */
function _build_context(PDO $tdb, int $instId, int $userId, int $resHint, array $cfg): array {
    $rol = api_rol();
    $rolHumano = [
        'admin'      => 'Administrador',
        'medico'     => 'Médico',
        'enfermero'  => 'Cuidador / Enfermero',
        'familiar'   => 'Familiar',
        'superadmin' => 'Superadministrador',
    ][$rol] ?? $rol;

    $userName = $_SESSION['user_nombre'] ?? '';
    $instName = $cfg['inst_nombre'] ?? ($_SESSION['user_institucion_nombre'] ?? '');

    // ── Capacidades por rol (texto narrativo para la IA) ───────────────────
    $caps = [
        'superadmin' => [
            'PUEDE: administrar todas las instituciones, planes, suscripciones, migraciones BD, auditoría, configuración global y revisar logs/bitácora.',
            'PUEDE: todas las acciones del rol Administrador en cualquier institución.',
        ],
        'admin' => [
            'PUEDE: gestionar residentes (crear/editar/archivar), gestionar personal de su institución, asignar roles, ver bitácora.',
            'PUEDE: configurar la institución (logo, datos, IA, notificaciones, plantillas, idiomas, copias de seguridad, integraciones).',
            'PUEDE: registrar cuidados del día, suministrar medicación, ajustar inventario, ver expediente y prescripciones.',
            'PUEDE: ver notas médicas (lectura).',
            'NO PUEDE: crear ni firmar notas médicas (eso es exclusivo del Médico).',
        ],
        'medico' => [
            'PUEDE: ver y editar expediente clínico, crear/firmar notas médicas vigentes, registrar prescripciones, valoraciones y antecedentes.',
            'PUEDE: ver registros operativos (sueño, alimentación, signos, etc.) y suministrar medicación si lo requiere.',
            'NO PUEDE: gestionar personal, planes, configuración global ni inventario administrativo.',
        ],
        'enfermero' => [
            'PUEDE: registrar todos los cuidados del día (sueño, alimentación, eliminación, higiene, suministrar medicación, signos vitales, movilidad, terapia, comportamiento, incidentes y notas de turno).',
            'PUEDE: ver expediente clínico y prescripciones del residente (solo lectura de notas médicas).',
            'NO PUEDE: crear/firmar notas médicas, modificar prescripciones, gestionar personal ni configuración.',
        ],
        'familiar' => [
            'PUEDE: ver el dashboard, registros, expediente (lectura) y ficha (lectura) del residente al que está vinculado.',
            'NO PUEDE: registrar cuidados, modificar datos clínicos ni acceder a otros residentes ni a configuración.',
        ],
    ][$rol] ?? [];

    // Mapa de secciones (ID JS → descripción + ruta hash interna)
    // Cada entrada lleva 'roles' = lista de roles que pueden acceder.
    $secciones = [
        ['id' => 'viewResidentes',        'rotulo' => 'Inicio (lista de residentes)',  'hash' => '#residentes',        'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Pantalla principal del personal: lista y gestión de residentes; acceso rápido al panel de cuidados de cada uno.'],
        ['id' => 'viewDashboard',         'rotulo' => 'Cuidados (panel del residente)', 'hash' => '#dashboard',         'roles' => ['superadmin','admin','medico','enfermero','familiar'],       'desc' => 'Panel del residente activo: resumen del día, alertas y accesos. Para FAMILIARES esta es su pantalla de Inicio. NUNCA llames a esta sección "Dashboard" ante el usuario; siempre dile "Cuidados" o "Inicio" (según el rol).'],
        ['id' => 'viewRecords',           'rotulo' => 'Registros del día',              'hash' => '#registros',         'roles' => ['superadmin','admin','medico','enfermero','familiar'],       'desc' => 'Bitácora cronológica de cuidados (sueño, alimentación, eliminación, signos, medicación, etc.).'],
        ['id' => 'viewExpediente',        'rotulo' => 'Expediente (archivero de documentos)','hash' => '#expediente',   'roles' => ['superadmin','admin','medico','enfermero','familiar'],       'desc' => 'Archivero electrónico de documentos del residente: PDFs de estudios, recetas escaneadas, identificaciones, consentimientos firmados, expedientes históricos, etc. NO contiene notas clínicas estructuradas (esas están en otros módulos como Nota médica o Registros).'],
        ['id' => 'viewFicha',             'rotulo' => 'Ficha del residente',            'hash' => '#ficha',             'roles' => ['superadmin','admin','medico','enfermero','familiar'],       'desc' => 'Datos personales, contactos, alergias y contactos de emergencia.'],
        ['id' => 'viewInventory',         'rotulo' => 'Medicinas / Inventario',         'hash' => '#inventario',        'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Tracker de medicación: prescripciones activas, suministrar pendientes, ajustar stock.'],
        ['id' => 'viewFormSueno',         'rotulo' => 'Registrar sueño',                'hash' => '#form-sueno',        'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Formulario para registrar siesta o sueño nocturno.'],
        ['id' => 'viewFormAlimentacion',  'rotulo' => 'Registrar alimentación',         'hash' => '#form-alimentacion', 'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Formulario para registrar comidas y consumo (porcentaje de ingesta).'],
        ['id' => 'viewFormMedicacion',    'rotulo' => 'Suministrar medicación',         'hash' => '#form-medicacion',   'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Confirmar suministro de medicamentos pendientes según prescripción.'],
        ['id' => 'viewFormSignosVitales', 'rotulo' => 'Registrar signos vitales',       'hash' => '#form-signos',       'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Presión, temperatura, glucosa, saturación, frecuencia cardiaca, etc.'],
        ['id' => 'viewFormNotas',         'rotulo' => 'Notas de turno',                 'hash' => '#form-notas',        'roles' => ['superadmin','admin','medico','enfermero'],                  'desc' => 'Notas del cuidador del turno (no clínicas).'],
        ['id' => 'viewFormNotasMedico',   'rotulo' => 'Nota médica (crear/firmar)',     'hash' => '#form-nota-medico',  'roles' => ['superadmin','medico'],                                       'desc' => 'Solo para médicos: valoración clínica vigente, archiva la anterior.'],
    ];

    // Filtrar por rol
    $secciones = array_values(array_filter($secciones, fn($s) => in_array($rol, $s['roles'], true)));

    $secLines = array_map(fn($s) => "- **{$s['rotulo']}** ({$s['hash']}): {$s['desc']}", $secciones);

    // Datos del residente activo (opcional, según rol). Familiar siempre tiene contexto;
    // resto de roles pasan resHint cuando hay residente seleccionado.
    $resBlock = '';
    if ($resHint > 0) {
        try {
            $stRes = $tdb->prepare("SELECT id, nombre, fecha_nacimiento, alergias, diagnostico FROM residentes WHERE id = ? AND institucion_id = ?");
            $stRes->execute([$resHint, $instId]);
            $r = $stRes->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $r = EncryptionMap::decryptRow('residentes', $r);
                $edad = '';
                if (!empty($r['fecha_nacimiento'])) {
                    try {
                        $bd = new DateTime($r['fecha_nacimiento']);
                        $edad = (new DateTime())->diff($bd)->y . ' años';
                    } catch (\Throwable $e) {}
                }
                $resBlock = "\nRESIDENTE ACTIVO (contexto del usuario en este momento):\n"
                    . "- Nombre: {$r['nombre']}\n"
                    . ($edad ? "- Edad: {$edad}\n" : '')
                    . (!empty($r['diagnostico']) ? "- Diagnóstico: " . mb_substr(strip_tags($r['diagnostico']), 0, 240) . "\n" : '')
                    . (!empty($r['alergias']) ? "- Alergias: " . mb_substr(strip_tags($r['alergias']), 0, 180) . "\n" : '');
            }
        } catch (\Throwable $e) {}
    }

    $base = $cfg['ia_prompt'] ?? '';
    if (!$base) $base = "Eres el asistente integrado de GeriApp, plataforma de cuidados geriátricos.";

    $capsLines = $caps ? ("\nCAPACIDADES Y RESTRICCIONES DEL ROL ACTUAL:\n- " . implode("\n- ", $caps) . "\n") : '';

    // Memoria persistente del asistente: aprendizajes acumulados
    $memBlock = _load_memories_block($tdb, $instId, $userId);

    // Disponibilidad del escalamiento a humano
    [$escalAvailable, $escalReason] = _escalation_availability($tdb, $instId, $userId, $cfg);
    $escalLine = $escalAvailable
        ? "ESCALAMIENTO A HUMANO: DISPONIBLE — el sistema YA tiene teléfono del usuario y del administrador, y puede crear el grupo de WhatsApp. Si el usuario lo pide, debes emitir la etiqueta <escalar_humano>.\n\n"
        : "ESCALAMIENTO A HUMANO: NO DISPONIBLE en este momento ({$escalReason}). Si el usuario lo pide, explica esto y NO emitas la etiqueta <escalar_humano>.\n\n";

    $system = $base . "\n\n"
        . "ROL DEL ASISTENTE: ayudar al usuario con dudas funcionales (cómo usar la plataforma) y, cuando aplique, con dudas clínicas generales relacionadas a cuidados de adultos mayores.\n"
        . "USUARIO: {$userName} (rol: {$rolHumano}).\n"
        . "INSTITUCIÓN: {$instName}.\n"
        . $capsLines
        . $resBlock . "\n"
        . $escalLine
        . "REGLAS IMPORTANTES SOBRE PRIVILEGIOS (estricto):\n"
        . "- NUNCA sugieras al usuario hacer algo fuera de sus capacidades. Si lo solicitado requiere otro rol, dilo amablemente y sugiere a quién acudir (ej.: \"pídele a tu Médico que firme esa nota\").\n"
        . "- NO menciones ni enlaces secciones que no estén en la lista 'SECCIONES DISPONIBLES PARA ESTE USUARIO'.\n"
        . "- Si la pregunta requiere un dato del residente que no tienes en contexto, indica DÓNDE encontrarlo en la app (ej.: \"está en su Expediente\").\n"
        . "- Para temas clínicos delicados (medicación, diagnósticos, dosis), recuerda al usuario consultar al médico responsable. NO inventes dosis ni indicaciones.\n\n"
        . "NOMBRES OFICIALES DE SECCIONES (estricto, NUNCA uses otros sinónimos ante el usuario):\n"
        . "- 'Inicio' = pantalla principal (lista de residentes para personal; panel del residente activo para familiares).\n"
        . "- 'Cuidados' = panel del residente activo (mismo destino que `#dashboard`). NUNCA digas \"Dashboard\" ni \"Tablero\".\n"
        . "- 'Registros' (no \"bitácora\"), 'Expediente' (archivero de documentos), 'Ficha' (datos personales), 'Medicinas' (no \"Farmacia\" ni \"Inventario\"), 'Configuración'.\n"
        . "- El **Expediente** es ÚNICAMENTE un archivero electrónico de documentos (PDFs, escaneos, recetas digitalizadas, identificaciones, consentimientos). NO contiene notas clínicas estructuradas; esas están en otros módulos (Nota médica, Registros).\n\n"
        . "FORMATO DE RESPUESTAS (importante seguir estilo):\n"
        . "- Responde en español, breve y al grano (idealmente <180 palabras salvo que pidan detalle).\n"
        . "- Usa Markdown estructurado para que sea fácil de leer:\n"
        . "  * **Negritas** para términos clave.\n"
        . "  * Viñetas con `-` o numeradas (1., 2., 3.) para pasos o listas.\n"
        . "  * Encabezados cortos (`### Título`) solo si la respuesta tiene 2+ secciones.\n"
        . "  * Bloques `código` para nombres exactos de campos o valores literales.\n"
        . "- Usa emojis con moderación para hacer la respuesta amigable y escaneables (1 al inicio, 0–2 más en el cuerpo). Ejemplos por contexto:\n"
        . "    👋 saludo · ✅ confirmación / hecho · ⚠️ advertencia · 💊 medicación · 🩺 clínico · 📋 registro / formulario · 📂 expediente · 👤 ficha · 📊 estadística · 🛌 sueño · 🍽️ alimentación · 🚿 higiene · 💧 eliminación · 🏃 movilidad · 🧠 comportamiento · 🆘 incidente · 🔒 privacidad / permiso · 💡 tip.\n"
        . "- Cuando menciones una sección de la app, ENLAZA con el formato exacto: `[Texto](#hash)`. Ej.: \"abre [Registros del día](#registros) y…\".\n"
        . "- Si el usuario te saluda, responde con un saludo breve + 1 sugerencia de qué puede pedirte.\n"
        . "- Si la pregunta NO es sobre GeriApp ni cuidados de adultos mayores, responde brevemente y reconduce al tema.\n\n"
        . "SECCIONES DISPONIBLES PARA ESTE USUARIO:\n"
        . implode("\n", $secLines)
        . $memBlock
        . "\n\nMEMORIA PERSISTENTE (aprendizaje del asistente):\n"
        . "- Cuando el usuario te diga algo que valga la pena RECORDAR para futuras conversaciones (preferencias, hechos institucionales estables, instrucciones recurrentes), incluye al final de tu respuesta uno o más bloques así:\n"
        . "  <recordar tipo=\"preferencia|hecho|instruccion|contexto\" alcance=\"usuario|institucion\" importancia=\"1-10\">texto corto y autocontenido</recordar>\n"
        . "- Reglas: máximo 2 bloques por respuesta, texto <200 caracteres, sin datos sensibles innecesarios (no PII de residentes ni detalles clínicos privados).\n"
        . "- Usa 'usuario' cuando es algo personal del usuario actual; 'institucion' cuando aplica a toda la institución.\n"
        . "- NO escribas etiquetas <recordar> visibles dentro del texto explicativo: el usuario NUNCA debe verlas (el sistema las extrae y oculta).\n"
        . "- Si el usuario pide explícitamente \"olvida X\" o \"ya no recuerdes Y\", responde confirmando y emite <olvidar>texto a olvidar</olvidar> (también se oculta).\n\n"
        . "ESCALAMIENTO A UN HUMANO (grupo de WhatsApp):\n"
        . "- Si arriba dice 'ESCALAMIENTO A HUMANO: DISPONIBLE' y el usuario pide hablar con una persona/administrador/soporte (o está claramente frustrado), DEBES emitir UNA etiqueta al final de tu respuesta:\n"
        . "  <escalar_humano motivo=\"frase corta del motivo\">resumen breve para el administrador (qué pidió el usuario, contexto del residente si aplica, qué intentaste)</escalar_humano>\n"
        . "- NO razones ni asumas que falta información: la disponibilidad ya fue verificada por el sistema. NO digas 'no hay administrador con teléfono' a menos que arriba diga 'NO DISPONIBLE'.\n"
        . "- Antes de la etiqueta, en tu texto visible escribe SOLO una línea breve: \"Voy a abrir un grupo de WhatsApp con el administrador para apoyarte. Te llegará la invitación en unos segundos.\" El sistema añadirá la confirmación final.\n"
        . "- La etiqueta NO se muestra al usuario; el sistema la oculta y se encarga del resto.\n"
        . "- Solo emite la etiqueta UNA VEZ por turno.\n"
        . "- IMPORTANTE: aunque en turnos anteriores el grupo HAYA fallado con error técnico, si el usuario vuelve a pedir hablar con una persona, DEBES INTENTARLO DE NUEVO y emitir la etiqueta. Cada solicitud explícita es un nuevo intento. NO te rindas ni le digas que ya intentaste varias veces; el sistema/admin pueden haber corregido el problema entre turnos.\n"
        . "- NUNCA sugieras que el usuario contacte al \"soporte de GeriApp por teléfono o correo\" como alternativa al escalamiento: el escalamiento ES la vía oficial de contacto humano.\n"
        . "- Si la disponibilidad es 'NO DISPONIBLE', explica brevemente al usuario el motivo indicado y sugiere agregar el dato faltante; NO emitas la etiqueta.\n"
        . "- Si tú mismo puedes resolver la duda con la información disponible y el usuario NO insiste en hablar con persona, primero intenta ayudar.";

    return ['system' => $system];
}

/**
 * Llama al proveedor IA. Devuelve [respuesta, error|null].
 */
function _ai_call(string $provider, string $apiKey, string $model, string $systemPrompt, array $hist): array {
    $endpoints = [
        'openai'   => 'https://api.openai.com/v1/chat/completions',
        'gemini'   => 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model ?: 'gemini-2.0-flash') . ':generateContent?key=' . urlencode($apiKey),
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
    ];
    $url = $endpoints[$provider] ?? $endpoints['openai'];

    if ($provider === 'gemini') {
        $contents = [];
        foreach ($hist as $h) {
            $contents[] = [
                'role'  => $h['rol'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string)$h['contenido']]],
            ];
        }
        $payload = json_encode([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $contents,
            'generationConfig'   => ['maxOutputTokens' => 1024, 'temperature' => 0.6],
        ], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json'];
    } else {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($hist as $h) {
            $messages[] = ['role' => $h['rol'], 'content' => (string)$h['contenido']];
        }
        $payload = json_encode([
            'model'       => $model ?: ($provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini'),
            'messages'    => $messages,
            'max_tokens'  => 1024,
            'temperature' => 0.6,
        ], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cErr = curl_error($ch);
    curl_close($ch);

    if ($cErr) return ['', "Conexión: $cErr"];
    if ($code < 200 || $code >= 300) {
        $body = json_decode($resp, true);
        $msg  = $body['error']['message'] ?? ($body['error']['status'] ?? "HTTP $code");
        return ['', $msg];
    }

    $data = json_decode($resp, true);
    if ($provider === 'gemini') {
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } else {
        $text = $data['choices'][0]['message']['content'] ?? '';
    }
    if ($text === '') return ['', 'La IA no generó respuesta'];
    return [$text, null];
}

// ════════════════════════════════════════════════════════════════════════════
// Memoria persistente del asistente (aprendizaje)
// ════════════════════════════════════════════════════════════════════════════

/**
 * Carga las memorias activas del usuario y de la institución para inyectarlas
 * en el system prompt. Limita a las más relevantes para no saturar tokens.
 * Devuelve un bloque de texto listo para concatenar (vacío si no hay).
 */
function _load_memories_block(PDO $tdb, int $instId, int $userId): string {
    try {
        $st = $tdb->prepare(
            "SELECT id, tipo, contenido, contenido_enc, importancia, usuario_id
             FROM chat_asistente_memoria
             WHERE institucion_id = ? AND (usuario_id = ? OR usuario_id IS NULL)
             ORDER BY importancia DESC, COALESCE(ultimo_uso_at, creado_at) DESC
             LIMIT 25"
        );
        $st->execute([$instId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return ''; }
    if (!$rows) return '';

    $usuario = [];
    $inst = [];
    $ids = [];
    foreach ($rows as $r) {
        $r = EncryptionMap::decryptRow('chat_asistente_memoria', $r);
        $txt = trim((string)($r['contenido'] ?? ''));
        if ($txt === '') continue;
        $line = '- [' . $r['tipo'] . '] ' . $txt;
        if (!empty($r['usuario_id'])) $usuario[] = $line;
        else $inst[] = $line;
        $ids[] = (int)$r['id'];
    }
    if (!$usuario && !$inst) return '';

    // Marcar memorias como usadas (best-effort)
    if ($ids) {
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $upd = $tdb->prepare("UPDATE chat_asistente_memoria SET ultimo_uso_at = NOW(), uso_count = uso_count + 1 WHERE id IN ($in)");
            $upd->execute($ids);
        } catch (\Throwable $e) {}
    }

    $out = "\n\nMEMORIA APRENDIDA (úsala con naturalidad si es relevante; no la cites textualmente):\n";
    if ($usuario) $out .= "Sobre este usuario:\n" . implode("\n", $usuario) . "\n";
    if ($inst)    $out .= "Sobre la institución:\n" . implode("\n", $inst) . "\n";
    return $out;
}

/**
 * Extrae bloques <recordar ...>...</recordar> y <olvidar>...</olvidar> de la
 * respuesta de la IA. Devuelve [respuestaLimpia, listaDeMemorias].
 * Cada memoria es: ['tipo','alcance','importancia','contenido','op'=>'add'|'forget']
 */
function _extract_memories(string $reply): array {
    $memorias = [];
    // <recordar tipo=".." alcance=".." importancia="..">texto</recordar>
    if (preg_match_all('/<recordar([^>]*)>(.*?)<\/recordar>/is', $reply, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $attrs = $hit[1];
            $body  = trim($hit[2]);
            if ($body === '') continue;
            $tipo = 'hecho';
            $alc  = 'usuario';
            $imp  = 5;
            if (preg_match('/tipo\s*=\s*"([^"]+)"/i', $attrs, $a))        $tipo = strtolower($a[1]);
            if (preg_match('/alcance\s*=\s*"([^"]+)"/i', $attrs, $a))     $alc  = strtolower($a[1]);
            if (preg_match('/importancia\s*=\s*"(\d+)"/i', $attrs, $a))   $imp  = max(1, min(10, (int)$a[1]));
            $tipo = in_array($tipo, ['preferencia','hecho','instruccion','contexto'], true) ? $tipo : 'hecho';
            $alc  = $alc === 'institucion' ? 'institucion' : 'usuario';
            $memorias[] = ['op' => 'add', 'tipo' => $tipo, 'alcance' => $alc, 'importancia' => $imp, 'contenido' => mb_substr($body, 0, 500)];
        }
        $reply = preg_replace('/<recordar[^>]*>.*?<\/recordar>/is', '', $reply);
    }
    if (preg_match_all('/<olvidar[^>]*>(.*?)<\/olvidar>/is', $reply, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $body = trim($hit[1]);
            if ($body === '') continue;
            $memorias[] = ['op' => 'forget', 'contenido' => mb_substr($body, 0, 500)];
        }
        $reply = preg_replace('/<olvidar[^>]*>.*?<\/olvidar>/is', '', $reply);
    }
    return [trim($reply), $memorias];
}

/**
 * Guarda (o elimina) las memorias extraídas. Encripta el contenido vía
 * EncryptionMap. Limita a 200 memorias por usuario+institución.
 */
function _save_memories(PDO $tdb, int $instId, int $userId, array $memorias): void {
    foreach ($memorias as $m) {
        try {
            if (($m['op'] ?? '') === 'forget') {
                $needle = '%' . $m['contenido'] . '%';
                $st = $tdb->prepare(
                    "DELETE FROM chat_asistente_memoria
                     WHERE institucion_id = ? AND (usuario_id = ? OR usuario_id IS NULL)
                       AND contenido LIKE ? LIMIT 5"
                );
                $st->execute([$instId, $userId, $needle]);
                Log::registrar([
                    'accion' => 'asistente_memoria_olvidar', 'modulo' => 'asistente',
                    'usuario_id' => $userId, 'institucion_id' => $instId,
                    'detalle' => json_encode(['hint' => mb_substr($m['contenido'], 0, 80)]),
                    'estado' => 'ok',
                ]);
                continue;
            }
            $row = ['contenido' => $m['contenido']];
            $enc = EncryptionMap::encryptRow('chat_asistente_memoria', $row);
            $usuarioId = $m['alcance'] === 'institucion' ? null : $userId;

            $st = $tdb->prepare(
                "INSERT INTO chat_asistente_memoria
                    (institucion_id, usuario_id, tipo, contenido, contenido_enc, importancia)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $instId,
                $usuarioId,
                $m['tipo'],
                $enc['contenido']     ?? $m['contenido'],
                $enc['contenido_enc'] ?? null,
                (int)$m['importancia'],
            ]);
            Log::registrar([
                'accion' => 'asistente_memoria_aprender', 'modulo' => 'asistente',
                'usuario_id' => $userId, 'institucion_id' => $instId,
                'detalle' => json_encode([
                    'tipo' => $m['tipo'],
                    'alcance' => $m['alcance'],
                    'importancia' => $m['importancia'],
                    'len' => mb_strlen($m['contenido']),
                ]),
                'estado' => 'ok',
            ]);
        } catch (\Throwable $e) {}
    }
    // Cap suave: si se pasa de 200 memorias para este (inst, user), borra las menos importantes
    try {
        $st = $tdb->prepare(
            "SELECT COUNT(*) FROM chat_asistente_memoria
             WHERE institucion_id = ? AND (usuario_id = ? OR usuario_id IS NULL)"
        );
        $st->execute([$instId, $userId]);
        if ((int)$st->fetchColumn() > 200) {
            $del = $tdb->prepare(
                "DELETE FROM chat_asistente_memoria
                 WHERE institucion_id = ? AND (usuario_id = ? OR usuario_id IS NULL)
                 ORDER BY importancia ASC, COALESCE(ultimo_uso_at, creado_at) ASC
                 LIMIT 20"
            );
            $del->execute([$instId, $userId]);
        }
    } catch (\Throwable $e) {}
}

/**
 * Verifica si el escalamiento a humano está disponible para este (instituciones,
 * usuario): requiere que el usuario tenga teléfono y que exista un teléfono de
 * administrador (en `usuarios.telefono` con rol admin, o como fallback en
 * `configuracion.wa_phone`). Devuelve [bool disponible, string razón_si_no].
 *
 * NOTA: la tabla `usuarios` vive en la BD MASTER (no en la tenant). Usar
 * Database::getMaster(), no $tdb.
 */
function _escalation_availability(PDO $tdb, int $instId, int $userId, array $cfg): array {
    $mdb = Database::getMaster();

    // Usuario solicitante
    $st = $mdb->prepare("SELECT telefono FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$userId]);
    $userPhone = trim((string)($st->fetchColumn() ?: ''));
    error_log("[ESCAL] avail: instId={$instId} userId={$userId} userPhone='" . ($userPhone ?: '(vacío)') . "'");

    if ($userPhone === '') {
        return [false, "el usuario no tiene teléfono registrado en su perfil"];
    }

    // Teléfono del administrador (única fuente de verdad):
    // Configuración → General → cfgSupportPhone (configuracion.support_phone).
    // No usamos `usuarios.telefono` (rol admin) ni `cfg['wa_phone']`: este
    // último coincide con la propia sesión WaSender (el bot) y rompe el
    // grupo. El número de soporte se administra solo desde Configuración.
    $supportPhone = trim((string)($cfg['support_phone'] ?? ''));
    error_log("[ESCAL] avail: cfg.support_phone='" . ($supportPhone ?: '(vacío)') . "'");
    if ($supportPhone !== '') return [true, ''];

    return [false, "falta configurar el teléfono del administrador en Configuración → General → Teléfono del administrador (WhatsApp)"];
}

/**
 * Extrae <escalar_humano motivo="...">resumen</escalar_humano> de la respuesta.
 * Devuelve [respuestaLimpia, ['motivo'=>..., 'resumen'=>...] | null].
 * Si la IA emite varias, se queda con la PRIMERA (sólo escalamos una vez por turno).
 */
function _extract_escalation(string $reply): array {
    $esc = null;
    if (preg_match('/<escalar_humano([^>]*)>(.*?)<\/escalar_humano>/is', $reply, $m)) {
        $motivo = '';
        if (preg_match('/motivo\s*=\s*"([^"]+)"/i', $m[1], $a)) {
            $motivo = trim($a[1]);
        }
        $resumen = trim($m[2]);
        if ($resumen !== '') {
            $esc = [
                'motivo'  => mb_substr($motivo, 0, 120),
                'resumen' => mb_substr($resumen, 0, 1000),
            ];
        }
        $reply = preg_replace('/<escalar_humano[^>]*>.*?<\/escalar_humano>/is', '', $reply);
    }
    return [trim((string)$reply), $esc];
}

/**
 * Crea un grupo de WhatsApp con: usuario solicitante, un administrador de la
 * institución y el bot (sesión WaSender, queda como superadmin automáticamente).
 * Promueve al administrador a admin del grupo.
 *
 * Requisitos:
 *  - El usuario y el administrador deben tener `usuarios.telefono` con código
 *    de país y estar registrados en WhatsApp.
 *  - La institución debe tener `wa_api_key` configurada.
 *
 * @return array ['ok'=>bool, 'group_jid'=>string|null, 'invite_link'=>string|null,
 *                'error'=>string|null, 'admin_name'=>string|null]
 */
function _handle_escalation(PDO $tdb, int $instId, int $userId, int $resHint, array $esc): array {
    $mdb = Database::getMaster();
    error_log("[ESCAL] handle: start instId={$instId} userId={$userId} resHint={$resHint} motivo='{$esc['motivo']}'");

    // 1) Datos del usuario solicitante (tabla `usuarios` está en MASTER)
    $st = $mdb->prepare("SELECT id, nombre, telefono, rol FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$userId]);
    $usr = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$usr) { error_log("[ESCAL] handle: usuario {$userId} no encontrado"); return ['ok'=>false, 'error'=>'Usuario no encontrado.']; }
    $userPhone = trim((string)($usr['telefono'] ?? ''));
    error_log("[ESCAL] handle: userPhone='{$userPhone}' nombre='{$usr['nombre']}'");
    if ($userPhone === '') {
        return ['ok'=>false, 'error'=>'Tu cuenta no tiene un número de teléfono registrado. Agrégalo en tu perfil (con código de país) y vuelve a pedirlo.'];
    }

    // 2) Teléfono del administrador para el grupo de soporte:
    //    Única fuente → Configuración → General → cfgSupportPhone
    //    (`configuracion.support_phone`). No fallback a `usuarios.telefono`
    //    ni a `cfg.wa_phone` (este último es el número del bot).
    try {
        $cfgTmp  = Configuracion::getCached($instId);
        $waPhone = trim((string)($cfgTmp['support_phone'] ?? ''));
    } catch (\Throwable $e) {
        $waPhone = '';
        error_log("[ESCAL] handle: error leyendo cfg: " . $e->getMessage());
    }
    error_log("[ESCAL] handle: cfg.support_phone='" . ($waPhone ?: '(vacío)') . "'");

    if ($waPhone === '') {
        error_log("[ESCAL] handle: NO support_phone para instId={$instId}");
        return ['ok'=>false, 'error'=>'No hay un teléfono de administrador configurado para esta institución. Pide al equipo que lo configure en *Configuración → General → Teléfono del administrador (WhatsApp)*.'];
    }

    // Nombre del admin (humanizar mensaje): tomar cualquier admin activo si
    // existe, si no usar etiqueta genérica.
    $adminName = 'Administrador';
    try {
        $stn = $mdb->prepare(
            "SELECT id, nombre FROM usuarios
             WHERE institucion_id = ? AND rol = 'admin' AND estado = 'activo'
             ORDER BY id ASC LIMIT 1"
        );
        $stn->execute([$instId]);
        if ($row = $stn->fetch(PDO::FETCH_ASSOC)) {
            $adminName = $row['nombre'] ?: 'Administrador';
            $adminId   = (int)$row['id'];
        } else {
            $adminId = 0;
        }
    } catch (\Throwable $e) { $adminId = 0; }

    $admin      = ['id' => $adminId, 'nombre' => $adminName, 'telefono' => $waPhone];
    $adminPhone = $waPhone;

    // 3) Datos de la institución y del residente activo
    $instName = 'GeriApp';
    try {
        $cfg = Configuracion::getCached($instId);
        // institucion table tiene 'nombre'; si no, fall back al config
        $sti = $tdb->prepare("SELECT nombre FROM instituciones WHERE id = ? LIMIT 1");
        $sti->execute([$instId]);
        $iname = (string)($sti->fetchColumn() ?: '');
        if ($iname !== '') $instName = $iname;
    } catch (\Throwable $e) {}

    $resName = '';
    if ($resHint > 0) {
        try {
            $str = $tdb->prepare("SELECT nombre, apellidos FROM residentes WHERE id = ? AND institucion_id = ? LIMIT 1");
            $str->execute([$resHint, $instId]);
            if ($r = $str->fetch(PDO::FETCH_ASSOC)) {
                $r = EncryptionMap::decryptRow('residentes', $r);
                $resName = trim(($r['nombre'] ?? '') . ' ' . ($r['apellidos'] ?? ''));
            }
        } catch (\Throwable $e) {}
    }

    $title = $resName !== '' ? "{$instName} — {$resName}" : "{$instName} — Soporte";
    $title = mb_substr($title, 0, 80);

    // 4) Cliente WaSender
    try {
        $wa = WaSenderAPI::fromConfig($instId);
    } catch (\Throwable $e) {
        error_log("[ESCAL] handle: WaSender init error: " . $e->getMessage());
        return ['ok'=>false, 'error'=>'WhatsApp no está configurado para esta institución.'];
    }

    // 4.1) Detectar el número de la sesión del bot para evitar autoadiciones
    //      (WaSender devuelve "bad-request" si intentamos añadir al propio bot
    //      como participante de un grupo creado por él).
    //      Usamos la misma normalización (México: 52→521 móvil) para detectar
    //      coincidencias aun cuando el número configurado venga sin el "1".
    $botDigits = $wa->getSessionPhone();
    error_log("[ESCAL] handle: botSessionPhone='" . ($botDigits ?: '(desconocido)') . "'");
    $userDigits  = WaSenderAPI::normalizePhone($userPhone);
    $adminDigits = WaSenderAPI::normalizePhone($adminPhone);
    if ($botDigits !== '' && $adminDigits === $botDigits) {
        error_log("[ESCAL] handle: support_phone coincide con el n\u00famero del bot");
        return ['ok'=>false, 'error'=>'El *Teléfono del administrador (WhatsApp)* configurado coincide con el número del propio bot. Debe ser el WhatsApp personal del administrador, no el de la sesión WaSender. Cámbialo en *Configuración → General*.'];
    }
    if ($botDigits !== '' && $userDigits === $botDigits) {
        return ['ok'=>false, 'error'=>'Tu número personal coincide con el número del bot de WhatsApp. Usa otro número en tu perfil.'];
    }

    // 5) Validar que ambos teléfonos están en WhatsApp (best-effort)
    $userOk  = $wa->isRegistered($userPhone);
    $adminOk = $wa->isRegistered($adminPhone);
    error_log("[ESCAL] handle: isRegistered user=" . var_export($userOk,true) . " admin=" . var_export($adminOk,true));
    if ($userOk === false) {
        return ['ok'=>false, 'error'=>"Tu número {$userPhone} no aparece registrado en WhatsApp. Verifica el número (con código de país) en tu perfil."];
    }
    if ($adminOk === false) {
        return ['ok'=>false, 'error'=>"El número del administrador no aparece registrado en WhatsApp. Pídele que lo verifique."];
    }
    // null = no se pudo verificar; seguimos adelante igualmente (WaSender devolverá error si no es válido)

    // 6) Crear el grupo
    error_log("[ESCAL] handle: createGroup title='{$title}' phones=[{$userPhone},{$adminPhone}]");
    $created = $wa->createGroup($title, [$userPhone, $adminPhone]);
    error_log("[ESCAL] handle: createGroup result ok=" . var_export($created['ok'] ?? null,true) . " jid='" . ($created['group_jid'] ?? '') . "' err='" . ($created['error'] ?? '') . "'");
    if (empty($created['ok'])) {
        return ['ok'=>false, 'error'=>'No se pudo crear el grupo: ' . ($created['error'] ?? 'error desconocido')];
    }
    $jid = (string)$created['group_jid'];

    // 7) Promover al administrador a admin del grupo (el bot ya es superadmin como creador)
    try { $wa->promoteParticipants($jid, [$adminPhone]); } catch (\Throwable $e) {}

    // 8) Mensaje de apertura del grupo
    $motivo = $esc['motivo']  ?: 'Solicitud de soporte';
    $resumen = $esc['resumen'] ?: '(sin resumen)';
    $userName = (string)($usr['nombre'] ?? 'Usuario');
    $rolHum = ['superadmin'=>'Superadmin','admin'=>'Administrador','medico'=>'Médico','enfermero'=>'Enfermero','familiar'=>'Familiar'][$usr['rol']] ?? $usr['rol'];

    $intro  = "👋 Hola, soy el Asistente de GeriApp.\n\n";
    $intro .= "Se abrió este grupo porque *{$userName}* ({$rolHum}) pidió hablar con un humano desde el chat del asistente.\n\n";
    if ($resName !== '') $intro .= "*Residente en contexto:* {$resName}\n";
    $intro .= "*Motivo:* {$motivo}\n\n";
    $intro .= "*Resumen de la conversación:*\n{$resumen}\n\n";
    $intro .= "Por favor, continúen la atención por aquí. 🙏";

    try { $wa->sendGroupText($jid, $intro); } catch (\Throwable $e) {}

    // 9) Invite link (best-effort, por si alguno no recibió la invitación)
    $invite = null;
    try {
        $lk = $wa->getGroupInviteLink($jid);
        if (!empty($lk['ok'])) $invite = $lk['invite_link'];
    } catch (\Throwable $e) {}

    // 10) Auditar
    Log::registrar([
        'accion'         => 'asistente_escalacion_humano',
        'modulo'         => 'asistente',
        'usuario_id'     => $userId,
        'institucion_id' => $instId,
        'detalle'        => json_encode([
            'group_jid' => $jid,
            'admin_id'  => (int)$admin['id'],
            'res_id'    => $resHint,
            'motivo'    => $motivo,
            'title'     => $title,
        ], JSON_UNESCAPED_UNICODE),
        'estado'         => 'ok',
    ]);

    return [
        'ok'          => true,
        'group_jid'   => $jid,
        'invite_link' => $invite,
        'admin_name'  => (string)($admin['nombre'] ?? ''),
        'error'       => null,
    ];
}
