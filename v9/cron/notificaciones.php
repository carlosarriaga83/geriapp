<?php
/**
 * GeriApp — Cron: Notificaciones automáticas
 *
 * Designed to run every 10 minutes. Duplicate prevention is handled via
 * notificaciones_log: each notification type is checked against CURDATE()
 * before sending, so repeated executions within the same day are safe.
 *
 * CRON-driven notifications:
 *   - stock_bajo (medicamentos)   → once daily (first run of the day)
 *   - stock_panales               → once daily (first run of the day)
 *   - reporte_dia                 → once daily at the contact's preferred hour (notif_reporte_hora)
 *
 * Event-driven notifications (signos_vitales, incidentes, medicacion,
 * alimentacion, animo) are triggered inline from api/cuidados.php when
 * a care record is created.
 *
 * Recommended crontab (every 10 minutes):
 *   star/10 * * * * /usr/bin/php /path/to/v6/cron/notificaciones.php >> /var/log/geriapp_cron.log 2>&1
 */

$isCli = php_sapi_name() === 'cli';
$isHttp = !$isCli;

if ($isHttp) {
    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

// CLI only (unless CRON_ALLOW_HTTP is defined)
if ($isHttp && !defined('CRON_ALLOW_HTTP')) {
    http_response_code(403);
    exit('CLI only');
}

// Buffered output — collect all output, then format at end
ob_start();

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/models.php';
require_once __DIR__ . '/../includes/EncryptionMap.php';

$logPrefix = date('Y-m-d H:i:s') . ' [notif-cron] ';
$summary = ['inicio' => date('Y-m-d H:i:s'), 'instituciones' => 0, 'inst_sin_canales' => 0, 'residentes' => 0, 'notif_enviadas' => 0, 'notif_error' => 0, 'notif_dedup' => 0, 'reportes_ia' => 0, 'detalle' => []];

// WhatsApp API throttle: minimum 7 seconds between sends to avoid rate-limit errors
$_waLastSendTime = 0;
function _waThrottle(): void {
    global $_waLastSendTime, $logPrefix;
    if ($_waLastSendTime > 0) {
        $elapsed = hrtime(true) - $_waLastSendTime;
        $remaining = 7_000_000_000 - $elapsed; // 7 seconds in nanoseconds
        if ($remaining > 0) {
            $secs = intdiv((int)$remaining, 1_000_000_000);
            $nanos = (int)($remaining % 1_000_000_000);
            $waitLabel = number_format($remaining / 1_000_000_000, 2);
            echo $logPrefix . "  WA throttle: esperando {$waitLabel}s...\n";
            time_nanosleep($secs, $nanos);
        }
    }
}
function _waMarkSent(): void {
    global $_waLastSendTime;
    $_waLastSendTime = hrtime(true);
}

// Get all active institutions
try {
    $central = Database::getMaster();
    $instituciones = $central->query("SELECT id, nombre FROM instituciones WHERE estado = 'activa'")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo $logPrefix . "ERROR: No se pudo conectar a la base central: " . $e->getMessage() . "\n";
    exit(1);
}

foreach ($instituciones as $inst) {
    $instId = (int) $inst['id'];
    $summary['instituciones']++;
    $instSummary = ['nombre' => $inst['nombre'], 'id' => $instId, 'enviados' => 0, 'errores' => 0, 'dedup' => 0, 'residentes' => 0];
    echo $logPrefix . "Procesando institución: {$inst['nombre']} (ID: $instId)\n";

    try {
        $db = Database::getTenant($instId);
    } catch (Throwable $e) {
        echo $logPrefix . "  ERROR conexión tenant: " . $e->getMessage() . "\n";
        continue;
    }

    // Ensure notificaciones_log table exists
    $db->exec("CREATE TABLE IF NOT EXISTS notificaciones_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        institucion_id INT NOT NULL,
        residente_id INT,
        destinatario VARCHAR(255),
        canal VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
        tipo VARCHAR(50),
        mensaje TEXT,
        estado VARCHAR(20) NOT NULL DEFAULT 'enviado',
        error_detalle TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(institucion_id, residente_id, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if this institution has WA/email configured
    $config = Configuracion::getByInstitucion($instId);
    $waActivo = !empty($config['wa_activo']);
    $smtpHost = !empty($config['smtp_host']);
    $instName = $config['inst_nombre'] ?? $inst['nombre'] ?? 'GeriApp';

    // Use institution timezone for hour comparison (server may be UTC)
    $cfgTz = $config['timezone'] ?? '';
    if (empty($cfgTz)) $cfgTz = 'America/Mexico_City';
    $instTz = new DateTimeZone($cfgTz);
    $nowInst = new DateTime('now', $instTz);
    $currentHour = $nowInst->format('H:i');
    // Today's date in institution timezone (for dedup instead of MySQL CURDATE which uses server TZ)
    $todayInst = $nowInst->format('Y-m-d');
    // Convert institution's start-of-day to UTC for dedup queries against MySQL CURRENT_TIMESTAMP (server TZ)
    $todayStartUtc = (new DateTime($todayInst . ' 00:00:00', $instTz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    echo $logPrefix . "  Timezone configurado: {$cfgTz} | Hora local: {$currentHour} | Fecha local: {$todayInst} | Dedup desde (UTC): {$todayStartUtc}\n";

    if (!$waActivo && !$smtpHost) {
        echo $logPrefix . "  Sin canales configurados, saltando.\n";
        $summary['inst_sin_canales']++;
        $instSummary['nota'] = 'Sin canales configurados';
        $summary['detalle'][] = $instSummary;
        continue;
    }

    $wa = null;
    $mailer = null;
    if ($waActivo) {
        try { $wa = WaSenderAPI::fromConfig($instId); } catch (Throwable $e) {
            echo $logPrefix . "  WARN: WA no disponible: " . $e->getMessage() . "\n";
        }
    }
    if ($smtpHost) {
        try { $mailer = Mailer::fromConfig($instId); } catch (Throwable $e) {
            echo $logPrefix . "  WARN: Email no disponible: " . $e->getMessage() . "\n";
        }
    }

    // Get all active residents with contacts
    $residentes = $db->prepare("SELECT id, nombre, apellidos, contacto_nombre, contacto_telefono, contacto_email, contactos_json FROM residentes WHERE institucion_id = ? AND estado = 'activo'");
    $residentes->execute([$instId]);
    $residentes = $residentes->fetchAll(PDO::FETCH_ASSOC);
    // Decrypt PII columns (consolidated phase) so contact phone/email/JSON
    // are usable as destinatario instead of being persisted as ciphertext
    // into notificaciones_log.
    $residentes = array_map(fn($r) => EncryptionMap::decryptRow('residentes', $r), $residentes);

    foreach ($residentes as $res) {
        $resId = (int) $res['id'];
        $resName = trim(($res['nombre'] ?? '') . ' ' . ($res['apellidos'] ?? ''));
        $instSummary['residentes']++;
        $summary['residentes']++;

        // Build contacts array (new format: all contacts in contactos_json)
        $contacts = [];
        if (!empty($res['contactos_json'])) {
            $parsed = json_decode($res['contactos_json'], true);
            if (is_array($parsed) && count($parsed)) {
                // New format: first entry has notif_* keys
                if (isset($parsed[0]['notif_stock']) || isset($parsed[0]['notif_wa'])) {
                    $contacts = $parsed;
                } else {
                    // Old format: JSON has only extra contacts
                    if (!empty($res['contacto_nombre'])) {
                        $contacts[] = [
                            'nombre'   => $res['contacto_nombre'],
                            'telefono' => $res['contacto_telefono'] ?? '',
                            'email'    => $res['contacto_email'] ?? '',
                            'notif_stock' => 1,
                            'notif_wa' => 1,
                        ];
                    }
                    $contacts = array_merge($contacts, $parsed);
                }
            }
        }
        // Fallback: build from flat fields
        if (empty($contacts) && !empty($res['contacto_nombre'])) {
            $contacts[] = [
                'nombre'   => $res['contacto_nombre'],
                'telefono' => $res['contacto_telefono'] ?? '',
                'email'    => $res['contacto_email'] ?? '',
                'notif_stock' => 1,
                'notif_wa' => 1,
            ];
        }

        // Check stock alerts (medicamentos)
        $lowStockItems = [];
        try {
            $stInv = $db->prepare("SELECT nombre, stock_actual, stock_minimo FROM inventario_items WHERE institucion_id = ? AND tipo = 'medicamento' AND activo = 1 AND stock_actual <= COALESCE(stock_minimo, 5) AND stock_actual >= 0");
            $stInv->execute([$instId]);
            $lowStockItems = array_map(fn($r) => EncryptionMap::decryptRow('inventario_items', $r), $stInv->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) { /* inventario table may not exist */ }

        // Check stock alerts (pañales)
        $lowStockPanales = [];
        try {
            $stPan = $db->prepare("SELECT nombre, stock_actual, stock_minimo FROM inventario_items WHERE institucion_id = ? AND tipo = 'panal' AND activo = 1 AND stock_actual <= COALESCE(stock_minimo, 5) AND stock_actual >= 0");
            $stPan->execute([$instId]);
            $lowStockPanales = array_map(fn($r) => EncryptionMap::decryptRow('inventario_items', $r), $stPan->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) { /* inventario table may not exist */ }

        // Get yesterday's care records for daily report (in institution timezone)
        $yesterdayInst = (clone $nowInst)->modify('-1 day');
        $yesterday = $yesterdayInst->format('Y-m-d');
        $careRecords = [];
        try {
            $stCare = $db->prepare(
                "SELECT cr.categoria, cr.hora, cr.datos, cr.observaciones, u.nombre AS usuario_nombre
                 FROM cuidados_registros cr
                 LEFT JOIN usuarios u ON u.id = cr.usuario_id
                 WHERE cr.institucion_id = ? AND cr.residente_id = ? AND cr.fecha = ?
                 ORDER BY cr.hora ASC"
            );
            $stCare->execute([$instId, $resId, $yesterday]);
            $careRecords = array_map(fn($r) => EncryptionMap::decryptRow('cuidados_registros', $r), $stCare->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {}

        // ── Active medical note (notas_medico estado='vigente') ──────────
        // Used by the AI prompt to contextualize the analysis with the
        // most recent doctor's note (PHI = Protected Health Information).
        $activeNote = null;
        try {
            $stNm = $db->prepare(
                "SELECT contenido, creado_at FROM notas_medico
                 WHERE institucion_id = ? AND residente_id = ? AND estado = 'vigente'
                 ORDER BY creado_at DESC LIMIT 1"
            );
            $stNm->execute([$instId, $resId]);
            $rowNm = $stNm->fetch(PDO::FETCH_ASSOC);
            if ($rowNm) {
                $rowNm = EncryptionMap::decryptRow('notas_medico', $rowNm);
                $activeNote = $rowNm;
            }
        } catch (Throwable $e) {}

        // ── Hours since last Heces / Orina (eliminacion alerts) ──────────
        // Thresholds (in hours) for surfacing as alerts to the AI.
        $hecesAlertThr = 12;   // > 12h sin heces → MONITOREO
        $hecesCritThr  = 24;   // > 24h sin heces → ALERTA crítica
        $orinaAlertThr = 8;    // > 8h sin orina → MONITOREO
        $orinaCritThr  = 12;   // > 12h sin orina → ALERTA crítica
        $eliminacionAlerts = ['heces' => null, 'orina' => null];
        try {
            $nowTs = $nowInst->getTimestamp();
            foreach (['Heces' => 'heces', 'Orina' => 'orina'] as $sub => $key) {
                $stEl = $db->prepare(
                    "SELECT fecha, hora FROM cuidados_registros
                     WHERE institucion_id = ? AND residente_id = ?
                       AND categoria = 'eliminacion' AND subtipo = ?
                     ORDER BY fecha DESC, hora DESC LIMIT 1"
                );
                $stEl->execute([$instId, $resId, $sub]);
                $rowEl = $stEl->fetch(PDO::FETCH_ASSOC);
                if ($rowEl) {
                    try {
                        $dtEl = new DateTime($rowEl['fecha'] . ' ' . ($rowEl['hora'] ?: '00:00'), $instTz);
                        $hoursSince = max(0, (int)floor(($nowTs - $dtEl->getTimestamp()) / 3600));
                        $eliminacionAlerts[$key] = [
                            'horas'        => $hoursSince,
                            'ultimo_fecha' => $rowEl['fecha'],
                            'ultimo_hora'  => $rowEl['hora'],
                        ];
                    } catch (Throwable $e) {}
                } else {
                    $eliminacionAlerts[$key] = ['horas' => null, 'ultimo_fecha' => null, 'ultimo_hora' => null];
                }
            }
        } catch (Throwable $e) {}
        $eliminacionAlerts['_thresholds'] = [
            'heces_alert' => $hecesAlertThr, 'heces_crit' => $hecesCritThr,
            'orina_alert' => $orinaAlertThr, 'orina_crit' => $orinaCritThr,
        ];

        // ── Stock & other per-contact notifications ──────────────────────
        foreach ($contacts as $c) {
            if (empty($c['nombre'])) continue;

            // Stock bajo — medicamentos
            if (!empty($c['notif_stock']) && count($lowStockItems) > 0) {
                $agotados = array_filter($lowStockItems, fn($i) => (int)$i['stock_actual'] === 0);
                $criticos = array_filter($lowStockItems, fn($i) => (int)$i['stock_actual'] > 0);

                $stockMsg = "⚠️ *REPORTE DE STOCK BAJO*\n";
                $stockMsg .= "Paciente: *{$resName}*\n";

                if (!empty($agotados)) {
                    $stockMsg .= "\n🔴 *AGOTADOS (0 unidades)*\n";
                    foreach ($agotados as $item) {
                        $stockMsg .= "• {$item['nombre']}\n";
                    }
                }
                if (!empty($criticos)) {
                    $stockMsg .= "\n🟠 *STOCK CRÍTICO (Próximos a agotarse)*\n";
                    foreach ($criticos as $item) {
                        $stockMsg .= "• {$item['nombre']}: {$item['stock_actual']} uds\n";
                    }
                }
                $stockMsg .= "\n> Por favor, coordine la reposición a la brevedad.";

                $dest = $c['telefono'] ?: $c['email'];
                $chk = $db->prepare("SELECT id FROM notificaciones_log WHERE institucion_id = ? AND residente_id = ? AND destinatario = ? AND tipo = 'stock_bajo_auto' AND fecha >= ?");
                $chk->execute([$instId, $resId, $dest, $todayStartUtc]);
                if (!$chk->fetch()) {
                    $sendResult = _cronSendNotif($db, $instId, $resId, $c, 'stock_bajo_auto', 'Alerta: Stock bajo de medicamentos', $stockMsg, $wa, $mailer, $instName);
                    $instSummary['enviados'] += $sendResult['ok']; $instSummary['errores'] += $sendResult['err'];
                    $summary['notif_enviadas'] += $sendResult['ok']; $summary['notif_error'] += $sendResult['err'];
                } else { $summary['notif_dedup']++; $instSummary['dedup']++; }
            }

            // Stock bajo — pañales
            if (!empty($c['notif_stock']) && count($lowStockPanales) > 0) {
                $agotadosP = array_filter($lowStockPanales, fn($i) => (int)$i['stock_actual'] === 0);
                $criticosP = array_filter($lowStockPanales, fn($i) => (int)$i['stock_actual'] > 0);

                $panalMsg = "⚠️ *REPORTE DE STOCK BAJO — PAÑALES*\n";
                $panalMsg .= "Paciente: *{$resName}*\n";

                if (!empty($agotadosP)) {
                    $panalMsg .= "\n🔴 *AGOTADOS (0 unidades)*\n";
                    foreach ($agotadosP as $item) {
                        $panalMsg .= "• {$item['nombre']}\n";
                    }
                }
                if (!empty($criticosP)) {
                    $panalMsg .= "\n🟠 *STOCK CRÍTICO (Próximos a agotarse)*\n";
                    foreach ($criticosP as $item) {
                        $panalMsg .= "• {$item['nombre']}: {$item['stock_actual']} uds\n";
                    }
                }
                $panalMsg .= "\n> Por favor, envíe más a la brevedad.";

                $dest = $c['telefono'] ?: $c['email'];
                $chk = $db->prepare("SELECT id FROM notificaciones_log WHERE institucion_id = ? AND residente_id = ? AND destinatario = ? AND tipo = 'stock_panales_auto' AND fecha >= ?");
                $chk->execute([$instId, $resId, $dest, $todayStartUtc]);
                if (!$chk->fetch()) {
                    $sendResult = _cronSendNotif($db, $instId, $resId, $c, 'stock_panales_auto', 'Alerta: Stock bajo de pañales', $panalMsg, $wa, $mailer, $instName);
                    $instSummary['enviados'] += $sendResult['ok']; $instSummary['errores'] += $sendResult['err'];
                    $summary['notif_enviadas'] += $sendResult['ok']; $summary['notif_error'] += $sendResult['err'];
                } else { $summary['notif_dedup']++; $instSummary['dedup']++; }
            }
        }

        // ── Reporte diario (IA) — generate once, distribute to all ───────
        // Collect eligible contacts for this hour
        $reportRecipients = [];
        // Current time in minutes since midnight (institution timezone)
        $nowMinutes = (int)$nowInst->format('H') * 60 + (int)$nowInst->format('i');
        foreach ($contacts as $c) {
            if (empty($c['nombre']) || empty($c['notif_reporte'])) continue;
            // Parse schedule hours (supports legacy single string or JSON array)
            $rawHora = $c['notif_reporte_hora'] ?? '08:00';
            $horasArr = [];
            if (is_string($rawHora) && str_starts_with($rawHora, '[')) {
                $decoded = json_decode($rawHora, true);
                if (is_array($decoded)) $horasArr = $decoded;
            }
            if (empty($horasArr)) $horasArr = [$rawHora];
            // Check if current time is within ±10 min of any scheduled time
            $hourMatches = false;
            $matchedHH = null;
            foreach ($horasArr as $h) {
                $parts = explode(':', $h);
                $schedMinutes = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                if (abs($nowMinutes - $schedMinutes) <= 10) {
                    $hourMatches = true;
                    $matchedHH = str_pad((int)($parts[0] ?? 0), 2, '0', STR_PAD_LEFT) . str_pad((int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT);
                    break;
                }
            }
            if (!$hourMatches) continue;
            $dest = $c['telefono'] ?: ($c['email'] ?? '');
            if (!$dest) continue;
            // Dedup check: use scheduled HHMM as tipo to allow multiple reports per day at different hours
            $dedupTipo = 'reporte_dia_auto_' . $matchedHH;
            $chk = $db->prepare("SELECT id FROM notificaciones_log WHERE institucion_id = ? AND residente_id = ? AND destinatario = ? AND tipo = ? AND fecha >= ?");
            $chk->execute([$instId, $resId, $dest, $dedupTipo, $todayStartUtc]);
            if (!$chk->fetch()) {
                $reportRecipients[] = ['contact' => $c, 'dedup_tipo' => $dedupTipo];
            } else { $summary['notif_dedup']++; $instSummary['dedup']++; }
        }

        if (!empty($reportRecipients)) {
            // Generate AI report once
            $reportData = _buildReportData($resName, $yesterday, $careRecords, $activeNote, $eliminacionAlerts);
            $reportMsg  = _generateAIReport($config, $reportData, $resName, $yesterday);
            $summary['reportes_ia']++;

            // Generate PDF if any recipient wants it
            $wantsPdf = false;
            foreach ($reportRecipients as $r) {
                if (!empty($r['contact']['notif_reporte_pdf'])) { $wantsPdf = true; break; }
            }
            $pdfData = null;
            if ($wantsPdf) {
                $appUrl = $config['app_url'] ?? '';
                if ($appUrl) {
                    $pdfData = _generateReportPdf($reportMsg, $resName, $yesterday, $instName, $appUrl);
                } else {
                    echo $logPrefix . "  WARN: app_url no configurada, no se puede generar PDF con URL pública.\n";
                }
            }

            // Distribute to each recipient (throttle handled globally inside _cronSendNotif)
            foreach ($reportRecipients as $r) {
                $c = $r['contact'];
                $dedupTipo = $r['dedup_tipo'];
                // Only pass PDF data if this contact has the PDF switch enabled
                $cPdfUrl  = (!empty($c['notif_reporte_pdf']) && $pdfData) ? $pdfData['url'] : '';
                $cPdfPath = (!empty($c['notif_reporte_pdf']) && $pdfData) ? $pdfData['path'] : '';
                try {
                    $sendResult = _cronSendNotif($db, $instId, $resId, $c, $dedupTipo, 'Reporte del día', $reportMsg, $wa, $mailer, $instName, $cPdfUrl, $cPdfPath);
                    $instSummary['enviados'] += $sendResult['ok']; $instSummary['errores'] += $sendResult['err'];
                    $summary['notif_enviadas'] += $sendResult['ok']; $summary['notif_error'] += $sendResult['err'];
                } catch (Throwable $e) {
                    echo $logPrefix . "  ERROR envío reporte a " . ($c['telefono'] ?? $c['email'] ?? '?') . ": " . $e->getMessage() . "\n";
                    $instSummary['errores']++; $summary['notif_error']++;
                }
            }
        }
    }
    $summary['detalle'][] = $instSummary;
}

// ── Resumen de ejecución ─────────────────────────────────────────────────
$summary['fin'] = date('Y-m-d H:i:s');
echo "\n";
echo "════════════════════════════════════════\n";
echo "  RESUMEN DE EJECUCIÓN DEL CRON\n";
echo "════════════════════════════════════════\n";
echo "  Inicio:              {$summary['inicio']}\n";
echo "  Fin:                 {$summary['fin']}\n";
echo "  Instituciones:       {$summary['instituciones']}\n";
echo "    Sin canales:       {$summary['inst_sin_canales']}\n";
echo "  Residentes:          {$summary['residentes']}\n";
echo "  Reportes IA gen.:    {$summary['reportes_ia']}\n";
echo "  Notif. enviadas:     {$summary['notif_enviadas']}\n";
echo "  Notif. con error:    {$summary['notif_error']}\n";
echo "  Notif. duplicadas:   {$summary['notif_dedup']}\n";
echo "────────────────────────────────────────\n";
foreach ($summary['detalle'] as $d) {
    echo "  [{$d['id']}] {$d['nombre']}\n";
    if (!empty($d['nota'])) { echo "       {$d['nota']}\n"; continue; }
    echo "       Residentes: {$d['residentes']} | Enviados: {$d['enviados']} | Errores: {$d['errores']} | Dedup: {$d['dedup']}\n";
}
echo "════════════════════════════════════════\n";

// ── Flush output ─────────────────────────────────────────────────────────
$output = ob_get_clean();
if ($isHttp) {
    $safe = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>GeriApp Cron</title></head>";
    echo "<body style='background:#1a1a2e;color:#e0e0e0;margin:0;padding:20px;font-family:\"Cascadia Code\",\"Fira Code\",\"Consolas\",monospace'>";
    echo "<pre style='white-space:pre-wrap;word-wrap:break-word;font-size:13px;line-height:1.7;max-width:960px;margin:0 auto'>{$safe}</pre>";
    echo "</body></html>";
} else {
    echo $output;
}

// ════════════════════════════════════════════════════════════════════════════
// Helper functions
// ════════════════════════════════════════════════════════════════════════════

/**
 * Build a detailed plain-text report from care records.
 *
 * Includes (when available):
 *   - The active medical note (notas_medico estado='vigente') so the AI can
 *     contextualize today's analysis against the doctor's current plan.
 *   - Hours since the last 'Heces' / 'Orina' eliminación events so the AI can
 *     surface intestinal/urinary alerts when retention exceeds safe thresholds.
 *
 * §5 PHIPA/NOM — All inputs already decrypted by EncryptionMap upstream.
 */
function _buildReportData(
    string $resName,
    string $fecha,
    array $records,
    ?array $activeNote = null,
    ?array $eliminacionAlerts = null
): string {
    $catLabels = [
        'sueno' => 'Sueño', 'alimentacion' => 'Alimentación', 'medicacion' => 'Medicación',
        'higiene' => 'Higiene', 'terapia' => 'Terapia', 'movilidad' => 'Movilidad',
        'eliminacion' => 'Eliminación', 'comportamiento' => 'Comportamiento',
        'signos_vitales' => 'Signos vitales'
    ];

    $text = "Residente: {$resName}\nFecha: {$fecha}\n\n";

    // ── Active medical note (vigente) ───────────────────────────────
    if (is_array($activeNote) && !empty($activeNote['contenido'])) {
        // Strip HTML tags so the prompt stays plain text and within budget.
        $contenido = trim(html_entity_decode(strip_tags((string)$activeNote['contenido']), ENT_QUOTES, 'UTF-8'));
        $contenido = preg_replace("/[ \t]+/u", ' ', $contenido);
        $contenido = preg_replace("/\n{3,}/u", "\n\n", $contenido);
        // Cap to ~2000 chars to keep the prompt efficient
        if (mb_strlen($contenido) > 2000) {
            $contenido = mb_substr($contenido, 0, 2000) . '… [truncado]';
        }
        $created = $activeNote['creado_at'] ?? '';
        $text .= "NOTA MÉDICA VIGENTE";
        if ($created) $text .= " (registrada {$created})";
        $text .= ":\n{$contenido}\n\n";
    }

    // ── Eliminación alerts (heces / orina) ──────────────────────────
    if (is_array($eliminacionAlerts)) {
        $thr = $eliminacionAlerts['_thresholds'] ?? [
            'heces_alert' => 12, 'heces_crit' => 24,
            'orina_alert' => 8,  'orina_crit' => 12,
        ];
        $alertLines = [];
        foreach (['heces' => 'Heces', 'orina' => 'Orina'] as $k => $label) {
            $info = $eliminacionAlerts[$k] ?? null;
            if (!is_array($info)) continue;
            $hrs = $info['horas'] ?? null;
            $aThr = $thr[$k . '_alert'] ?? null;
            $cThr = $thr[$k . '_crit'] ?? null;
            if ($hrs === null) {
                $alertLines[] = "- {$label}: SIN registros previos en la base de datos.";
                continue;
            }
            $when = trim(($info['ultimo_fecha'] ?? '') . ' ' . ($info['ultimo_hora'] ?? ''));
            $level = '';
            if ($cThr !== null && $hrs >= $cThr) $level = 'CRÍTICA';
            elseif ($aThr !== null && $hrs >= $aThr) $level = 'monitoreo';
            if ($level !== '') {
                $alertLines[] = "- {$label}: {$hrs}h sin registro (último: {$when}) — alerta " . $level . ".";
            } else {
                $alertLines[] = "- {$label}: {$hrs}h desde último registro (último: {$when}) — dentro de rango.";
            }
        }
        if (!empty($alertLines)) {
            $text .= "ALERTAS DE ELIMINACIÓN (umbrales heces ≥{$thr['heces_alert']}h/≥{$thr['heces_crit']}h, orina ≥{$thr['orina_alert']}h/≥{$thr['orina_crit']}h):\n"
                . implode("\n", $alertLines) . "\n\n";
        }
    }

    if (empty($records)) {
        $text .= "No se registraron cuidados en este día.\n";
        return $text;
    }

    // Stats summary
    $counts = [];
    foreach ($records as $r) {
        $cat = $r['categoria'];
        $counts[$cat] = ($counts[$cat] ?? 0) + 1;
    }
    $text .= "Estadísticas:\n";
    foreach ($counts as $cat => $total) {
        $label = $catLabels[$cat] ?? $cat;
        $text .= "- {$label}: {$total} registro(s)\n";
    }
    $text .= "\n";

    // Detailed records by category
    $byCategory = [];
    foreach ($records as $r) {
        $byCategory[$r['categoria']][] = $r;
    }

    foreach ($byCategory as $cat => $recs) {
        $label = $catLabels[$cat] ?? $cat;
        $text .= "{$label}:\n";
        foreach ($recs as $r) {
            $hora = $r['hora'] ?? '';
            $datos = is_string($r['datos']) ? json_decode($r['datos'], true) : ($r['datos'] ?: []);
            $obs = $r['observaciones'] ?? '';
            $parts = [];
            if ($hora) $parts[] = $hora;
            // Extract key data fields
            if (is_array($datos)) {
                foreach ($datos as $k => $v) {
                    if ($v === null || $v === '' || $k === 'foto_path') continue;
                    if (is_bool($v)) $v = $v ? 'Sí' : 'No';
                    if (is_array($v)) {
                        // Sub-objetos (ej. signos vitales: presion=>['sis'=>120,'dia'=>80])
                        // se aplanan como "k1=v1, k2=v2"
                        $flat = [];
                        foreach ($v as $sk => $sv) {
                            if ($sv === null || $sv === '') continue;
                            if (is_array($sv)) $sv = json_encode($sv, JSON_UNESCAPED_UNICODE);
                            elseif (is_bool($sv)) $sv = $sv ? 'Sí' : 'No';
                            $flat[] = "{$sk}={$sv}";
                        }
                        $v = implode(', ', $flat);
                    }
                    $parts[] = str_replace('_', ' ', $k) . ": {$v}";
                }
            }
            if ($obs) $parts[] = "Obs: {$obs}";
            if ($r['usuario_nombre'] ?? false) $parts[] = "Por: {$r['usuario_nombre']}";
            $text .= "  - " . implode(' | ', $parts) . "\n";
        }
        $text .= "\n";
    }

    return $text;
}

/**
 * Call the AI provider to interpret report data. Falls back to plain text.
 */
function _generateAIReport(array $config, string $reportData, string $resName, string $fecha): string
{
    global $logPrefix;

    $provider = $config['ia_proveedor'] ?? 'openai';
    $apiKey   = $config['ia_api_key'] ?? '';
    $model    = $config['ia_modelo'] ?? '';
    $maxPalabras = max(50, min(2000, (int)($config['ia_max_palabras'] ?? 400)));

    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    $fechaDisplay = $fechaObj ? strtoupper($fechaObj->format('d')) . ' ' . strtoupper([
        1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
        7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'
    ][(int)$fechaObj->format('n')]) . ' ' . $fechaObj->format('Y') : $fecha;

    // If no AI key configured, send the raw detailed report
    if (empty($apiKey)) {
        echo $logPrefix . "  IA no configurada, enviando reporte de datos en texto plano.\n";
        return "📋 *REPORTE DIARIO — {$fechaDisplay}*\nPaciente: *{$resName}*\n\n" . $reportData;
    }

    $systemPrompt = $config['ia_prompt'] ?? '';
    if (empty($systemPrompt)) {
        $systemPrompt = "Eres un asistente de salud geriátrica profesional. Analizas datos de cuidados de adultos mayores en una residencia.";
    }

    $userPrompt = "Analiza este reporte de cuidados de un adulto mayor en una residencia geriátrica.\n\n"
        . "Instrucciones ESTRICTAS de formato (este mensaje se envía por WhatsApp):\n"
        . "- Máximo {$maxPalabras} palabras.\n"
        . "- NO uses formato de carta (sin saludo, sin despedida, sin firma).\n"
        . "- NO uses encabezados markdown (# ni ##). Usa emojis + *negritas* para secciones.\n"
        . "- Formato WhatsApp: *negritas*, _itálicas_, listas con • (viñeta).\n"
        . "- Responde en español.\n\n"
        . "Estructura OBLIGATORIA del reporte (usa EXACTAMENTE estas secciones, omite las que no apliquen):\n\n"
        . "🔴 *ALERTAS PRIORITARIAS* _(Requieren atención)_\n"
        . "Solo si hay situaciones que necesiten intervención médica o familiar inmediata.\n"
        . "Cada alerta en un párrafo breve indicando QUÉ pasó y POR QUÉ es relevante.\n\n"
        . "⚠️ *MONITOREO* _(Observaciones)_\n"
        . "Situaciones que no son urgentes pero merecen seguimiento.\n\n"
        . "✅ *ESTADO GENERAL* _(Estable)_\n"
        . "Resumen breve de lo que estuvo bien: medicación, alimentación, higiene, etc.\n\n"
        . "💡 *RECOMENDACIONES*\n"
        . "Acciones concretas sugeridas para el familiar o el equipo médico.\n\n"
        . "IMPORTANTE:\n"
        . "- Si el reporte incluye 'NOTA MÉDICA VIGENTE', úsala como contexto clínico de base: contrasta lo registrado hoy contra el plan/diagnóstico/indicaciones del médico, y resalta cualquier desviación o cumplimiento.\n"
        . "- Si el reporte incluye 'ALERTAS DE ELIMINACIÓN' con nivel 'CRÍTICA' (heces ≥24h u orina ≥12h), inclúyelas SIEMPRE en 🔴 ALERTAS PRIORITARIAS. Si el nivel es 'monitoreo' (heces ≥12h u orina ≥8h), inclúyelas en ⚠️ MONITOREO.\n"
        . "- Presta especial atención a las observaciones de los cuidadores (campo 'Obs:') ya que contienen información clínica valiosa.\n"
        . "- Identifica patrones, tendencias o anomalías.\n"
        . "- Sé directo, empático y usa lenguaje sencillo (es para familiares, no médicos).\n"
        . "- No repitas la misma información en varias secciones.\n\n"
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
        CURLOPT_TIMEOUT        => 120,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode < 200 || $httpCode >= 300) {
        echo $logPrefix . "  ERROR IA ({$provider}): " . ($curlErr ?: "HTTP {$httpCode}") . "\n";
        return "📋 *REPORTE DIARIO — {$fechaDisplay}*\nPaciente: *{$resName}*\n\n" . $reportData;
    }

    $result = json_decode($response, true);
    if ($provider === 'gemini') {
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } else {
        $text = $result['choices'][0]['message']['content'] ?? '';
    }

    if (empty($text)) {
        echo $logPrefix . "  WARN: IA no generó respuesta, enviando datos planos.\n";
        return "📋 *REPORTE DIARIO — {$fechaDisplay}*\nPaciente: *{$resName}*\n\n" . $reportData;
    }

    echo $logPrefix . "  IA ({$provider}): reporte generado OK.\n";
    return "📋 *REPORTE DIARIO — {$fechaDisplay}*\nPaciente: *{$resName}*\n\n" . $text;
}

/**
 * Send a notification to a contact via their preferred channels.
 * Returns ['ok' => int, 'err' => int] with counts of successful/failed sends.
 * $pdfUrl: optional public URL to a PDF document to attach via WaSender.
 * $pdfPath: optional local file path to attach to email.
 */
function _cronSendNotif(PDO $db, int $instId, int $resId, array $c, string $tipo, string $subject, string $msg, ?WaSenderAPI $wa, ?Mailer $mailer, string $instName = 'GeriApp', string $pdfUrl = '', string $pdfPath = ''): array
{
    global $logPrefix;
    $ok = 0; $err = 0;
    // WhatsApp
    if (!empty($c['notif_wa']) && !empty($c['telefono']) && $wa) {
        $estado = 'enviado';
        $error = null;
        // Convert markdown bold (**text**) to WhatsApp bold (*text*)
        $waMsg = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $msg);
        _waThrottle();
        $result = $wa->sendText($c['telefono'], $waMsg);
        _waMarkSent();
        if (empty($result['ok'])) {
            $estado = 'error';
            $error = $result['error'] ?? 'Error desconocido de WA API';
            $err++;
        } else { $ok++; }
        $encMsg = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $msg])['mensaje'] ?? $msg;
        $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'whatsapp', ?, ?, ?, ?)");
        $st->execute([$instId, $resId, $c['telefono'], $tipo, $encMsg, $estado, $error]);
        EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $msg]);
        echo $logPrefix . "  WA → {$c['telefono']}: {$estado}" . ($error ? " ({$error})" : '') . "\n";

        // Send PDF/HTML document if available
        if ($pdfUrl && empty($error)) {
            _waThrottle(); // Enforce 7s gap before sending document
            $ext = pathinfo(parse_url($pdfUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
            $docFilename = "reporte_diario.{$ext}";
            $pdfResult = $wa->sendDocument($c['telefono'], $pdfUrl, '📋 Reporte diario', $docFilename);
            _waMarkSent();
            if (empty($pdfResult['ok'])) {
                echo $logPrefix . "  WA DOC → {$c['telefono']}: error (" . ($pdfResult['error'] ?? '?') . ")\n";
            } else {
                echo $logPrefix . "  WA DOC → {$c['telefono']}: enviado\n";
            }
        }
    }
    // Email
    if (!empty($c['notif_email']) && !empty($c['email']) && $mailer) {
        $estado = 'enviado';
        $error = null;
        $htmlBody = _buildEmailHtml($subject, $msg, $tipo, $instName);
        try {
            if ($pdfPath && file_exists($pdfPath)) {
                $mailer->addAttachment($pdfPath, 'reporte_diario.pdf');
            }
            $mailer->send($c['email'], $subject, $htmlBody, $msg);
            $ok++;
        } catch (Throwable $e) {
            $estado = 'error';
            $error = $e->getMessage();
            $err++;
        }
        $encMsgEmail = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $msg])['mensaje'] ?? $msg;
        $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'email', ?, ?, ?, ?)");
        $st->execute([$instId, $resId, $c['email'], $tipo, $encMsgEmail, $estado, $error]);
        EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $msg]);
        echo $logPrefix . "  Email → {$c['email']}: {$estado}\n";
    }
    return ['ok' => $ok, 'err' => $err];
}

/**
 * Generate a styled HTML/PDF report file.
 * Tries dompdf if the library is available; otherwise saves as .pdf-compatible HTML.
 * Returns ['path' => local file path, 'url' => public URL] or null on failure.
 */
function _generateReportPdf(string $reportMsg, string $resName, string $fecha, string $instName, string $appUrl): ?array
{
    global $logPrefix;

    $uploadsDir = __DIR__ . '/../uploads/reportes';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Clean old reports (older than 7 days)
    foreach (glob($uploadsDir . '/*.{pdf,html}', GLOB_BRACE) as $old) {
        if (filemtime($old) < time() - 7 * 86400) @unlink($old);
    }

    $safeRes  = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($resName, 0, 30));
    $filename = "reporte_{$safeRes}_{$fecha}_" . substr(md5(uniqid('', true)), 0, 8);

    // Build styled HTML content
    $msgSafe = htmlspecialchars($reportMsg, ENT_QUOTES, 'UTF-8');
    // Convert WhatsApp formatting to HTML
    $msgHtml = preg_replace('/\*(.+?)\*/', '<strong>$1</strong>', $msgSafe);
    $msgHtml = preg_replace('/_(.+?)_/', '<em>$1</em>', $msgHtml);
    $msgHtml = nl2br($msgHtml);
    // Convert bullet points
    $msgHtml = preg_replace('/^• /m', '<span style="margin-left:12px">• </span>', $msgHtml);

    $instSafe = htmlspecialchars($instName, ENT_QUOTES, 'UTF-8');
    $resSafe  = htmlspecialchars($resName, ENT_QUOTES, 'UTF-8');
    $year     = date('Y');

    $html = <<<EPDF
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Diario - {$resSafe}</title>
<style>
  @page { margin: 20mm 15mm; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #1e293b; font-size: 13px; line-height: 1.7; margin: 0; padding: 20px; background: #fff; }
  .header { border-bottom: 3px solid #1e3a6e; padding-bottom: 12px; margin-bottom: 20px; }
  .header h1 { color: #1e3a6e; font-size: 20px; margin: 0 0 4px 0; }
  .header .meta { color: #64748b; font-size: 12px; }
  .content { margin-bottom: 30px; }
  .content strong { color: #1e293b; }
  .footer { border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 11px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="header">
  <h1>📋 Reporte Diario</h1>
  <div class="meta">{$instSafe} &middot; Paciente: {$resSafe} &middot; Fecha: {$fecha}</div>
</div>
<div class="content">{$msgHtml}</div>
<div class="footer">{$instSafe} &middot; GeriApp &middot; &copy; {$year}</div>
</body>
</html>
EPDF;

    // Try dompdf if available
    $dompdfAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($dompdfAutoload)) {
        try {
            require_once $dompdfAutoload;
            if (class_exists('Dompdf\\Dompdf')) {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfPath = $uploadsDir . '/' . $filename . '.pdf';
                file_put_contents($pdfPath, $dompdf->output());
                $publicUrl = rtrim($appUrl, '/') . '/uploads/reportes/' . $filename . '.pdf';
                echo $logPrefix . "  PDF generado (dompdf): {$filename}.pdf\n";
                return ['path' => $pdfPath, 'url' => $publicUrl];
            }
        } catch (Throwable $e) {
            echo $logPrefix . "  WARN dompdf: " . $e->getMessage() . "\n";
        }
    }

    // Fallback: save as HTML document (opens in any browser/device)
    $htmlPath = $uploadsDir . '/' . $filename . '.html';
    file_put_contents($htmlPath, $html);
    $publicUrl = rtrim($appUrl, '/') . '/uploads/reportes/' . $filename . '.html';
    echo $logPrefix . "  Reporte HTML generado (sin dompdf): {$filename}.html\n";
    return ['path' => $htmlPath, 'url' => $publicUrl];
}

/**
 * Build a styled HTML email body.
 */
function _buildEmailHtml(string $subject, string $message, string $tipo, string $instName = 'GeriApp'): string
{
    $accentColors = [
        'stock_bajo_auto'    => '#e67e22',
        'stock_panales_auto' => '#e67e22',
        'signos_vitales'     => '#e74c3c',
        'incidente'          => '#e74c3c',
        'reporte_dia_auto'   => '#1e3a6e',
        'medicacion'         => '#27ae60',
        'alimentacion'       => '#27ae60',
        'animo'              => '#8e44ad',
    ];
    $accent      = $accentColors[$tipo] ?? '#1e3a6e';
    $instNameSafe = htmlspecialchars($instName, ENT_QUOTES, 'UTF-8');
    $subjectSafe  = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $messageHtml  = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $year         = date('Y');

    return <<<EHTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
  <tr><td style="height:5px;background:{$accent};"></td></tr>
  <tr><td style="padding:28px 32px 0 32px;">
    <div style="font-size:13px;font-weight:600;color:{$accent};text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{$instNameSafe}</div>
    <div style="font-size:20px;font-weight:700;color:#1e293b;line-height:1.3;">{$subjectSafe}</div>
  </td></tr>
  <tr><td style="padding:16px 32px 0 32px;"><div style="border-top:1px solid #e2e8f0;"></div></td></tr>
  <tr><td style="padding:20px 32px 28px 32px;">
    <div style="font-size:14px;line-height:1.7;color:#334155;">{$messageHtml}</div>
  </td></tr>
  <tr><td style="padding:16px 32px 24px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;">
    <div style="font-size:11px;color:#94a3b8;line-height:1.5;">{$instNameSafe} &middot; GeriApp &middot; &copy; {$year}</div>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
EHTML;
}
