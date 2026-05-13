<?php
/**
 * GeriApp — API /api/reportes.php
 *
 * GET  ?residente_id=N&desde=YYYY-MM-DD&hasta=YYYY-MM-DD   → datos agregados para gráficas
 * POST  action=interpretar                                   → interpretación IA del reporte
 *
 * Roles: admin, enfermero, medico, familiar, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero', 'familiar']);

$method = api_method();

// ════════════════════════════════════════════════════════════════════════════
// POST — Interpretar reporte con IA
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $body = api_body();
    $action = trim($body['action'] ?? '');

    if ($action !== 'interpretar') api_error('Acción no reconocida', 400);

    $role       = trim($body['role'] ?? '');
    $reportData = $body['report_data'] ?? '';
    $residenteId = (int)($body['residente_id'] ?? 0);

    $validRoles = ['medico', 'enfermero', 'familiar'];
    if (!in_array($role, $validRoles, true)) api_error('Rol no válido', 422);
    if (empty($reportData)) api_error('Datos del reporte requeridos', 422);

    // Get AI config
    $instId = api_inst_id();
    $cfg    = Configuracion::getCached($instId);
    $provider = $cfg['ia_proveedor'] ?? 'openai';
    $apiKey   = $cfg['ia_api_key'] ?? '';
    $model    = $cfg['ia_modelo'] ?? '';

    if (!$apiKey) api_error('No se ha configurado la API Key de IA. Ve a Configuración > Inteligencia Artificial.', 422);

    $maxPalabras = (int)($cfg['ia_max_palabras'] ?? 400);
    if ($maxPalabras < 50) $maxPalabras = 50;
    if ($maxPalabras > 2000) $maxPalabras = 2000;

    // ── Server-side enrichment: prepend active medical note + heces/orina alerts ─
    // The client only sends raw care records. To improve clinical analysis we add
    // (a) the latest 'vigente' notas_medico and (b) hours-since-last Heces/Orina,
    // both fetched here from the tenant DB so they cannot be tampered with.
    if ($residenteId > 0) {
        try { api_assert_residente($residenteId); } catch (\Throwable $e) { $residenteId = 0; }
    }
    if ($residenteId > 0) {
        $extraText = '';
        try {
            $tdb = Database::getTenant($instId);

            // Active medical note
            $stNm = $tdb->prepare(
                "SELECT contenido, creado_at FROM notas_medico
                 WHERE institucion_id = ? AND residente_id = ? AND estado = 'vigente'
                 ORDER BY creado_at DESC LIMIT 1"
            );
            $stNm->execute([$instId, $residenteId]);
            $rowNm = $stNm->fetch(PDO::FETCH_ASSOC);
            if ($rowNm) {
                $rowNm = EncryptionMap::decryptRow('notas_medico', $rowNm);
                $contenido = trim(html_entity_decode(strip_tags((string)($rowNm['contenido'] ?? '')), ENT_QUOTES, 'UTF-8'));
                $contenido = preg_replace("/[ \t]+/u", ' ', $contenido);
                $contenido = preg_replace("/\n{3,}/u", "\n\n", $contenido);
                if (mb_strlen($contenido) > 2000) $contenido = mb_substr($contenido, 0, 2000) . '… [truncado]';
                if ($contenido !== '') {
                    $extraText .= "NOTA MÉDICA VIGENTE";
                    if (!empty($rowNm['creado_at'])) $extraText .= " (registrada {$rowNm['creado_at']})";
                    $extraText .= ":\n{$contenido}\n\n";
                }
            }

            // Hours since last Heces / Orina
            $tzCfg = $cfg['general_timezone'] ?? ($cfg['timezone'] ?? date_default_timezone_get());
            try { $tz = new DateTimeZone($tzCfg); } catch (\Throwable $e) { $tz = new DateTimeZone(date_default_timezone_get()); }
            $nowTs = (new DateTime('now', $tz))->getTimestamp();
            $thr = ['heces_alert'=>12,'heces_crit'=>24,'orina_alert'=>8,'orina_crit'=>12];
            $alertLines = [];
            foreach (['Heces' => 'Heces', 'Orina' => 'Orina'] as $sub => $label) {
                $stEl = $tdb->prepare(
                    "SELECT fecha, hora FROM cuidados_registros
                     WHERE institucion_id = ? AND residente_id = ?
                       AND categoria = 'eliminacion' AND subtipo = ?
                     ORDER BY fecha DESC, hora DESC LIMIT 1"
                );
                $stEl->execute([$instId, $residenteId, $sub]);
                $rowEl = $stEl->fetch(PDO::FETCH_ASSOC);
                $key = strtolower($sub);
                if (!$rowEl) {
                    $alertLines[] = "- {$label}: SIN registros previos en la base de datos.";
                    continue;
                }
                try {
                    $dtEl = new DateTime($rowEl['fecha'] . ' ' . ($rowEl['hora'] ?: '00:00'), $tz);
                    $hrs = max(0, (int)floor(($nowTs - $dtEl->getTimestamp()) / 3600));
                    $when = $rowEl['fecha'] . ' ' . $rowEl['hora'];
                    $level = '';
                    if ($hrs >= $thr[$key . '_crit']) $level = 'CRÍTICA';
                    elseif ($hrs >= $thr[$key . '_alert']) $level = 'monitoreo';
                    if ($level !== '') {
                        $alertLines[] = "- {$label}: {$hrs}h sin registro (último: {$when}) — alerta {$level}.";
                    } else {
                        $alertLines[] = "- {$label}: {$hrs}h desde último registro (último: {$when}) — dentro de rango.";
                    }
                } catch (\Throwable $e) {}
            }
            if (!empty($alertLines)) {
                $extraText .= "ALERTAS DE ELIMINACIÓN (umbrales heces ≥{$thr['heces_alert']}h/≥{$thr['heces_crit']}h, orina ≥{$thr['orina_alert']}h/≥{$thr['orina_crit']}h):\n"
                    . implode("\n", $alertLines) . "\n\n";
            }
        } catch (\Throwable $e) { /* enrichment is best-effort */ }

        if ($extraText !== '') {
            $reportData = $extraText . $reportData;
        }
    }

    // Build prompt per role
    $roleLabels = [
        'medico'    => 'médico geriatra',
        'enfermero' => 'cuidador/a especialista en geriatría',
        'familiar'  => 'familiar del residente (lenguaje sencillo y empático)',
    ];
    $roleLabel = $roleLabels[$role];

    $systemPrompt = $cfg['ia_prompt'] ?? '';
    if (empty($systemPrompt)) {
        $systemPrompt = "Eres un asistente de salud geriátrica profesional. Analizas datos de cuidados de adultos mayores en una residencia.";
    }

    $userPrompt = "Analiza este reporte de cuidados como {$roleLabel}.\n\n"
        . "Instrucciones de formato y contenido:\n"
        . "- NO uses formato de carta (sin saludo, sin despedida, sin firma).\n"
        . "- Sé directo y conciso: máximo {$maxPalabras} palabras.\n"
        . "- NO uses encabezados markdown (# ni ## ni ###). En su lugar usa texto en negritas (**título**) para los nombres de sección.\n"
        . "- Usa viñetas (-) y numerales (1.) para organizar la información.\n"
        . "- Incluye estas secciones: Resumen General, Hallazgos Clave, Alertas (si las hay), Recomendaciones.\n"
        . "- Si el reporte incluye 'NOTA MÉDICA VIGENTE', úsala como contexto clínico de base: contrasta lo observado contra el plan/diagnóstico/indicaciones del médico, y resalta desviaciones o cumplimiento.\n"
        . "- Si el reporte incluye 'ALERTAS DE ELIMINACIÓN' con nivel 'CRÍTICA' (heces ≥24h u orina ≥12h), repórtalas SIEMPRE en la sección de Alertas. Si el nivel es 'monitoreo' (heces ≥12h u orina ≥8h), repórtalas como observación a vigilar.\n"
        . "- Presta especial atención a las observaciones de los cuidadores (campo 'Obs:') ya que contienen información clínica relevante.\n"
        . "- Identifica patrones, tendencias o anomalías en los datos.\n"
        . "- Usa un tono profesional adecuado para un/a {$roleLabel}.\n"
        . "- Responde en español.\n\n"
        . "Datos del reporte:\n{$reportData}";

    // Call AI provider
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
            'generationConfig' => ['maxOutputTokens' => 2048, 'temperature' => 0.7],
        ]);
        $headers = ['Content-Type: application/json'];
    } else {
        $payload = json_encode([
            'model'    => $model ?: ($provider === 'deepseek' ? 'deepseek-chat' : 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 2048,
            'temperature' => 0.7,
        ]);
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
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) api_error('Error de conexión: ' . $curlErr, 502);
    if ($httpCode < 200 || $httpCode >= 300) {
        $errBody = json_decode($response, true);
        $errMsg  = $errBody['error']['message'] ?? ($errBody['error']['status'] ?? "HTTP $httpCode");
        api_error('Error del proveedor IA: ' . $errMsg, 502);
    }

    $result = json_decode($response, true);

    // Extract text based on provider
    if ($provider === 'gemini') {
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } else {
        $text = $result['choices'][0]['message']['content'] ?? '';
    }

    if (empty($text)) api_error('La IA no generó una respuesta', 502);

    api_ok(['interpretation' => $text]);
}

// ════════════════════════════════════════════════════════════════════════════
// GET — Datos agregados para reporte
// ════════════════════════════════════════════════════════════════════════════
if ($method !== 'GET') api_error('Método no permitido', 405);

$instId = api_inst_id();
$resId  = api_int('residente_id');
$desde  = trim($_GET['desde'] ?? '');
$hasta  = trim($_GET['hasta'] ?? '');

if ($resId <= 0)   api_error('residente_id requerido', 422);
if (!$desde)       api_error('desde requerido', 422);
if (!$hasta)       api_error('hasta requerido', 422);

// Verificar que el residente pertenece a la institución
$residente = Residente::getById($resId, $instId);
if (!$residente) api_error('Residente no encontrado', 404);

// Familiares solo pueden ver su residente vinculado
if (($_SESSION['user_rol'] ?? '') === 'familiar' && empty($_SESSION['sa_impersonating'])) {
    $famResId = (int)($_SESSION['user_residente_id'] ?? 0);
    if ($famResId > 0 && $famResId !== $resId) api_error('Acceso denegado', 403);
}

$datos = Reporte::getResumenCompleto($instId, $resId, $desde, $hasta);

api_ok(array_merge(['residente' => $residente], $datos));
