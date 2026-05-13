<?php
/**
 * GeriApp — API /api/import.php
 *
 * POST  action=detect   → recibe archivos, detecta su tipo, devuelve preview
 * POST  action=run      → recibe archivos, ejecuta la importación completa
 *
 * Solo accesible por admin / superadmin.
 * Los registros se importan en la institución del usuario en sesión.
 */

// Capture any stray output (PHP warnings etc.) before JSON is sent
ob_start();

// Convert PHP warnings/notices to exceptions so they are caught below
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/helpers.php';
api_auth_roles(['superadmin', 'admin']);

if (api_method() !== 'POST') api_error('Método no permitido', 405);

$action = $_POST['action'] ?? '';
if (!in_array($action, ['detect', 'run'], true)) api_error('Acción inválida', 400);

$instId  = (int)($_SESSION['user_institucion_id'] ?? 0);
$userId  = (int)($_SESSION['user_id'] ?? 0);
if (!$instId) api_error('Institución no encontrada en la sesión', 403);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers CSV
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Detecta el tipo de CSV por sus columnas de cabecera.
 * Devuelve: 'residents'|'medications'|'vital_signs'|'nursing_notes'|
 *           'care_logs'|'nutrition_logs'|'sleep_logs'|'elimination_logs'|
 *           'medication_orders'|'unknown'
 */
function detectCsvType(array $headers): string
{
    $h = array_map('strtolower', $headers);
    $h = array_map('trim', $h);

    if (in_array('first_name', $h) && in_array('last_name', $h) && in_array('date_of_birth', $h))
        return 'residents';
    if (in_array('medication_name', $h) && in_array('dosage', $h) && in_array('frequency', $h))
        return 'medication_orders';
    if (in_array('medicamento', $h) && in_array('dosis', $h) && in_array('dose1_time', $h))
        return 'medications_log';
    if (in_array('ta', $h) && in_array('fc', $h) && in_array('fr', $h))
        return 'vital_signs';
    if (in_array('category', $h) && in_array('content', $h) && in_array('severity', $h))
        return 'nursing_notes';
    if (in_array('category', $h) && in_array('performed_at', $h) && (in_array('tm_nurse', $h) || in_array('performed_by', $h)))
        return 'care_logs';
    if (in_array('meal_type', $h) && in_array('percentage_consumed', $h))
        return 'nutrition_logs';
    if (in_array('start_time', $h) && in_array('end_time', $h) && in_array('quality', $h))
        return 'sleep_logs';
    if (in_array('type', $h) && in_array('characteristics', $h) && in_array('logged_at', $h))
        return 'elimination_logs';
    return 'unknown';
}

/** Abre un archivo CSV y devuelve [headers, rows[]] */
function parseCsv(string $path): array
{
    $rows    = [];
    $headers = null;
    if (($fh = fopen($path, 'r')) === false) return [[], []];
    while (($line = fgetcsv($fh, 0, ',')) !== false) {
        // Fix encoding: latin1 -> utf8 if needed
        $line = array_map(fn($v) => mb_detect_encoding($v, ['UTF-8'], true)
            ? $v
            : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'), $line);

        if ($headers === null) {
            // Normalize to lowercase + trim so row key access is always consistent,
            // regardless of whether the CSV uses 'Date', 'DATE', 'date', etc.
            $headers = array_map(fn($v) => strtolower(trim($v)), $line);
            continue;
        }
        if (count($line) === count($headers)) {
            $rows[] = array_combine($headers, $line);
        }
    }
    fclose($fh);
    return [$headers ?? [], $rows];
}

/** Fixer de encoding inline para un string */
function fixEnc(string $s): string
{
    $s = trim($s);
    if ($s === '' || mb_detect_encoding($s, ['UTF-8'], true)) return $s;
    return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
}

/** Convierte fecha de origen (ISO, timestamp con TZ) a DATE Y-m-d o null */
function toDate(?string $s): ?string
{
    if (!$s || trim($s) === '') return null;
    $s = preg_replace('/\s?\+\d{2}:\d{2}$/', '', trim($s));
    try {
        $dt = new DateTime($s);
        return $dt->format('Y-m-d');
    } catch (Exception) {
        return null;
    }
}

/** Convierte timestamp ISO a DATETIME Y-m-d H:i:s o null */
function toDatetime(?string $s): ?string
{
    if (!$s || trim($s) === '') return null;
    $s = preg_replace('/\.\d+/', '', trim($s)); // strip microseconds
    $s = preg_replace('/\s?\+\d{2}:\d{2}$/', '', $s);
    try {
        $dt = new DateTime($s);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception) {
        return null;
    }
}

/** Mapeo de vía de administración del sistema origen al enum de GeriApp */
function mapVia(?string $v): string
{
    $map = [
        'oral'         => 'oral',
        'iv'           => 'IV',
        'im'           => 'IM',
        'topical'      => 'tópica',
        'topica'       => 'tópica',
        'tópica'       => 'tópica',
        'inhaled'      => 'inhalada',
        'inhalada'     => 'inhalada',
        'sublingual'   => 'sublingual',
        'rectal'       => 'rectal',
        'ophthalmic'   => 'oftálmica',
        'oftalmologica'=> 'oftálmica',
        'oftálmica'    => 'oftálmica',
        'otic'         => 'ótica',
        'nasal'        => 'nasal',
        'subcutaneous' => 'subcutánea',
        'subcutánea'   => 'subcutánea',
    ];
    return $map[strtolower(trim($v ?? ''))] ?? (trim($v) ?: 'oral');
}

/** Shift de origen a enum turno de GeriApp */
function mapShift(?string $s): string
{
    $map = [
        'morning'   => 'matutino',
        'afternoon' => 'vespertino',
        'evening'   => 'vespertino',
        'night'     => 'nocturno',
        'tm'        => 'matutino',
        'tv'        => 'vespertino',
        'tn'        => 'nocturno',
    ];
    return $map[strtolower(trim($s ?? ''))] ?? 'matutino';
}

// ─────────────────────────────────────────────────────────────────────────────
// Recibir archivos subidos
// ─────────────────────────────────────────────────────────────────────────────
$files = [];
if (!empty($_FILES['csvfiles']['name'][0])) {
    $count = count($_FILES['csvfiles']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['csvfiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $files[] = [
            'name' => $_FILES['csvfiles']['name'][$i],
            'tmp'  => $_FILES['csvfiles']['tmp_name'][$i],
        ];
    }
}
if (empty($files)) api_error('No se recibió ningún archivo', 400);

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: detect — solo devuelve preview, no importa nada
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'detect') {
    $preview = [];
    foreach ($files as $f) {
        [$headers, $rows] = parseCsv($f['tmp']);
        $type  = detectCsvType($headers);
        $label = [
            'residents'        => 'Residentes',
            'medication_orders'=> 'Prescripciones (órdenes médicas)',
            'medications_log'  => 'Registros de administración de medicamentos',
            'vital_signs'      => 'Signos vitales',
            'nursing_notes'    => 'Notas de enfermería',
            'care_logs'        => 'Registros de cuidados',
            'nutrition_logs'   => 'Registros de nutrición',
            'sleep_logs'       => 'Registros de sueño',
            'elimination_logs' => 'Registros de eliminación',
            'unknown'          => 'No reconocido',
        ][$type] ?? 'Desconocido';

        $preview[] = [
            'filename' => $f['name'],
            'type'     => $type,
            'label'    => $label,
            'rows'     => count($rows),
            'headers'  => $headers,
            'sample'   => array_slice($rows, 0, 2),
        ];
    }
    api_ok($preview, 'Detección completada');
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: run — importación real
// ─────────────────────────────────────────────────────────────────────────────
try {
$db  = Database::getInstance();
$log = [];   // log de pasos para devolver al cliente
$stats = [
    'residentes'    => 0,
    'prescripciones'=> 0,
    'bitacora'      => 0,
    'signos_vitales'=> 0,
    'omitidos'      => 0,
];

/**
 * Mapa UUID_origen => residente_id (INT) de GeriApp.
 * Se construye al importar residentes y se reutiliza en todo lo demás.
 */
$residenteMap = [];  // uuid => new_id

/** Obtener o crear un turno de bitácora para la fecha y tipo dados */
$turnoCache = [];
function getOrCreateTurno(int $instId, string $tipo, string $fecha, int $userId, $db): int
{
    global $turnoCache;
    $key = "$instId|$tipo|$fecha";
    if (isset($turnoCache[$key])) return $turnoCache[$key];

    $stmt = $db->prepare(
        "SELECT id FROM bitacora_turnos WHERE institucion_id=? AND tipo=? AND fecha=? LIMIT 1"
    );
    $stmt->execute([$instId, $tipo, $fecha]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $turnoCache[$key] = (int)$row['id'];
        return (int)$row['id'];
    }
    $ins = $db->prepare(
        "INSERT INTO bitacora_turnos (institucion_id, tipo, fecha, responsable_id) VALUES (?,?,?,?)"
    );
    $ins->execute([$instId, $tipo, $fecha, $userId]);
    $turnoCache[$key] = (int)$db->lastInsertId();
    return $turnoCache[$key];
}

/** Obtener o crear expediente para un residente */
$expCache = [];
function getOrCreateExpediente(int $resId, int $instId, $db): int
{
    global $expCache;
    if (isset($expCache[$resId])) return $expCache[$resId];
    $stmt = $db->prepare("SELECT id FROM historial_expedientes WHERE residente_id=? LIMIT 1");
    $stmt->execute([$resId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $expCache[$resId] = (int)$row['id'];
        return (int)$row['id'];
    }
    $ins = $db->prepare(
        "INSERT INTO historial_expedientes (residente_id, institucion_id) VALUES (?,?)"
    );
    $ins->execute([$resId, $instId]);
    $expCache[$resId] = (int)$db->lastInsertId();
    return $expCache[$resId];
}

// Ordenar archivos: residents primero
usort($files, function($a, $b) {
    [$ha] = parseCsv($a['tmp']);
    [$hb] = parseCsv($b['tmp']);
    $ta = detectCsvType($ha);
    $tb = detectCsvType($hb);
    return ($ta === 'residents' ? 0 : 1) - ($tb === 'residents' ? 0 : 1);
});

// ── Pre-cargar mapa UUID→ID de residentes ya existentes en DB ─────────────
$existingRes = $db->query(
    "SELECT id, notas FROM residentes WHERE institucion_id = $instId"
)->fetchAll(PDO::FETCH_ASSOC);

// Guardaremos el UUID original en el campo `notas` con prefijo "import_uuid:"
foreach ($existingRes as $er) {
    if ($er['notas'] && preg_match('/import_uuid:([a-f0-9\-]{36})/i', $er['notas'], $m)) {
        $residenteMap[$m[1]] = (int)$er['id'];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada archivo
// ─────────────────────────────────────────────────────────────────────────────
foreach ($files as $f) {
    [$headers, $rows] = parseCsv($f['tmp']);
    $type = detectCsvType($headers);
    if ($type === 'unknown') {
        $log[] = ['level'=>'warn', 'msg'=>"⚠ {$f['name']}: tipo no reconocido, omitido"];
        $stats['omitidos'] += count($rows);
        continue;
    }

    $log[] = ['level'=>'info', 'msg'=>"▶ Procesando {$f['name']} ({$type}, " . count($rows) . " filas)"];
    $n = 0;

    // ── RESIDENTS ────────────────────────────────────────────────────────────
    if ($type === 'residents') {
        // F6: pre-validar cupo (rechaza el archivo entero si excede).
        // Solo contamos UUIDs nuevos (los repetidos serán omitidos).
        require_once dirname(__DIR__) . '/db/models/Seats.php';
        $stmtPre = $db->prepare("SELECT 1 FROM residentes WHERE notas LIKE ? AND institucion_id=? LIMIT 1");
        $nuevosCount = 0;
        foreach ($rows as $row) {
            $u = trim($row['id'] ?? '');
            if (!$u) continue;
            if (isset($residenteMap[$u])) continue;
            $stmtPre->execute(["%import_uuid:$u%", $instId]);
            if (!$stmtPre->fetchColumn()) $nuevosCount++;
        }
        if ($nuevosCount > 0) {
            try {
                Seats::assertCanAddResidentes($instId, $nuevosCount);
            } catch (SeatLimitException $e) {
                api_error($e->getMessage(), 403, [
                    'seat_limit'   => true,
                    'kind'         => 'residente',
                    'usados'       => $e->usados,
                    'limite'       => $e->limite,
                    'extra'        => $e->extra,
                    'intentando'   => $nuevosCount,
                    'billing_url'  => BASE_URL . '/billing.php#addons',
                ]);
            }
        }

        $stmtChk = $db->prepare(
            "SELECT id FROM residentes WHERE notas LIKE ? AND institucion_id=? LIMIT 1"
        );
        $stmtIns = $db->prepare(
            "INSERT INTO residentes
               (institucion_id, nombre, apellidos, fecha_nacimiento, habitacion,
                estado, alergias, diagnostico, notas, fecha_ingreso, creado_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid = trim($row['id'] ?? '');
            if (!$uuid) { $stats['omitidos']++; continue; }

            // Skip if already imported
            $stmtChk->execute(["%import_uuid:$uuid%", $instId]);
            if ($existing = $stmtChk->fetch(PDO::FETCH_ASSOC)) {
                $residenteMap[$uuid] = (int)$existing['id'];
                $stats['omitidos']++;
                continue;
            }

            $nombre   = fixEnc($row['first_name'] ?? '');
            $apellido = fixEnc($row['last_name'] ?? '');
            $dob      = toDate($row['date_of_birth'] ?? null);
            $hab      = trim($row['room_number'] ?? '');
            $estado   = strtolower($row['status'] ?? 'active') === 'active' ? 'activo' : 'egresado';
            $alergias = fixEnc($row['allergies'] ?? '');
            $diag     = fixEnc($row['conditions']    ?? '');
            $createdAt= toDatetime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s');

            // Notas: plan de cuidados + notas relevantes + UUID para tracking
            $notasParts = array_filter([
                fixEnc($row['care_plan_summary'] ?? ''),
                fixEnc($row['relevant_notes']    ?? ''),
            ]);
            $notas = implode("\n", $notasParts);
            $notas .= "\nimport_uuid:$uuid";

            $stmtIns->execute([
                $instId, $nombre, $apellido, $dob, $hab ?: null,
                $estado, $alergias ?: null, $diag ?: null, $notas, $dob, $createdAt,
            ]);
            $newId = (int)$db->lastInsertId();
            $residenteMap[$uuid] = $newId;

            // Crear expediente historial automáticamente
            getOrCreateExpediente($newId, $instId, $db);

            $n++;
            $stats['residentes']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Residentes importados: $n"];
        // F6: refrescar cache de uso tras import masivo
        try { Seats::recalcUsageForInst($instId); } catch (\Throwable $e) { /* tolerante */ }
        continue;
    }

    // ── MEDICATION ORDERS (prescripciones) ───────────────────────────────────
    if ($type === 'medication_orders') {
        $stmtIns = $db->prepare(
            "INSERT INTO prescripciones
               (residente_id, institucion_id, nombre, dosis, via, frecuencia,
                indicacion, inicio, activo, creado_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid   = trim($row['resident_id'] ?? '');
            $resId  = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $nombre  = fixEnc($row['medication_name'] ?? '');
            if (!$nombre) { $stats['omitidos']++; continue; }

            $activo  = strtolower($row['is_active'] ?? 'true') === 'true' ? 1 : 0;
            $stmtIns->execute([
                $resId,
                $instId,
                $nombre,
                fixEnc($row['dosage']       ?? ''),
                mapVia($row['route']         ?? ''),
                fixEnc($row['frequency']    ?? ''),
                fixEnc($row['instructions'] ?? ''),
                toDate($row['start_date']   ?? null),
                $activo,
                toDatetime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
            $n++;
            $stats['prescripciones']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Prescripciones importadas: $n"];
        continue;
    }

    // ── MEDICATIONS LOG (registros de administración → bitacora) ─────────────
    if ($type === 'medications_log') {
        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $fecha     = toDate($row['date'] ?? null) ?? date('Y-m-d');
            $shift     = mapShift($row['shift'] ?? 'morning');
            $turnoId   = getOrCreateTurno($instId, $shift, $fecha, $userId, $db);
            $med       = fixEnc($row['medicamento'] ?? '');
            $dosis     = fixEnc($row['dosis']        ?? '');
            $via       = fixEnc($row['via']           ?? '');
            $obs       = fixEnc($row['observacion']   ?? '');
            $contenido = "Medicamento: $med" .
                ($dosis ? " — $dosis" : '') .
                ($via   ? " — vía $via" : '') .
                ($obs   ? ". $obs" : '');
            $createdAt = toDatetime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s');

            $stmtIns->execute([$turnoId, $resId, $userId, 'medicamento', $contenido, 'normal', $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Administraciones de medicamentos: $n"];
        continue;
    }

    // ── VITAL SIGNS ──────────────────────────────────────────────────────────
    if ($type === 'vital_signs') {
        // Group individual readings by (residente_id, fecha, shift) so that each
        // group produces exactly ONE bitacora_entradas record in the format the
        // bitácora UI expects: {"rows":[{"time","sys","dia","fc","fr","temp","spo2"},...]}
        $vsGroups   = [];   // "resId|fecha|shift" => groupData
        $totalRows  = 0;

        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            // ── Fecha y hora (zona horaria CDMX = America/Mexico_City) ──────
            // date column is MM/DD/YYYY in CDMX local time.
            $cdmxTz   = new DateTimeZone('America/Mexico_City');
            $rawDate  = trim($row['date'] ?? '');
            $dtParsed = $rawDate !== ''
                ? (DateTime::createFromFormat('m/d/Y', $rawDate, $cdmxTz)
                   ?: DateTime::createFromFormat('m/d/y', $rawDate, $cdmxTz)
                   ?: null)
                : null;
            $fecha = $dtParsed ? $dtParsed->format('Y-m-d') : date('Y-m-d');
            // time column: support both 24-hour (HH:MM[:SS]) and 12-hour (h:MM:SS AM/PM) formats
            $rawTime = trim($row['time'] ?? '');
            $hora    = '08:00';
            if ($rawTime !== '') {
                // Try 12-hour AM/PM first (e.g. '8:00:00 AM', '1:30:00 PM', '12:00:00 PM')
                $dtTime = DateTime::createFromFormat('g:i:s A', strtoupper($rawTime), $cdmxTz)
                       ?: DateTime::createFromFormat('g:i A',   strtoupper($rawTime), $cdmxTz)
                       ?: null;
                if ($dtTime) {
                    $hora = $dtTime->format('H:i');
                } elseif (preg_match('/^(\d{1,2}):(\d{2})/', $rawTime, $tm)) {
                    // 24-hour fallback: extract HH:MM
                    $hora = str_pad($tm[1], 2, '0', STR_PAD_LEFT) . ':' . $tm[2];
                }
            }

            // Determine bitácora shift from hour
            $hh = (int)substr($hora, 0, 2);
            if      ($hh >= 7  && $hh < 15) $shift = 'matutino';
            elseif  ($hh >= 15 && $hh < 23) $shift = 'vespertino';
            else                              $shift = 'nocturno';

            // ── Presión arterial: "120/80" → sys / dia ───────────────────
            $ta  = trim($row['ta'] ?? '');
            $sys = ''; $dia = '';
            if ($ta !== '') {
                if (str_contains($ta, '/')) {
                    [$rawSys, $rawDia] = array_pad(explode('/', $ta, 2), 2, '');
                    $sys = trim($rawSys);
                    $dia = trim($rawDia);
                    // Discard non-numeric values
                    if (!is_numeric($sys)) $sys = '';
                    if (!is_numeric($dia)) $dia = '';
                } elseif (is_numeric(trim($ta))) {
                    $sys = trim($ta);   // solo sistólica
                }
            }

            // ── SpO2: columna 'sato2'; fallback a 'spo2' ─────────────────
            $spo2 = trim($row['sato2'] ?? $row['spo2'] ?? '');
            if (!is_numeric($spo2)) $spo2 = '';

            // ── Demás campos numéricos ────────────────────────────────────
            $fc   = is_numeric(trim($row['fc']   ?? '')) ? trim($row['fc'])   : '';
            $fr   = is_numeric(trim($row['fr']   ?? '')) ? trim($row['fr'])   : '';
            $temp = trim($row['temp'] ?? '');
            // temp allows decimals with . or ,
            $temp = str_replace(',', '.', $temp);
            if (!is_numeric($temp)) $temp = '';

            // Build one row entry; 'time' is always kept; omit empty numeric fields
            // No 'mood' field in this CSV source
            $vsRow = array_filter([
                'time' => $hora,
                'sys'  => $sys,
                'dia'  => $dia,
                'fc'   => $fc,
                'fr'   => $fr,
                'temp' => $temp,
                'spo2' => $spo2,
            ], fn($v) => $v !== '');
            $vsRow['time'] = $hora;   // always keep time even if all vitals empty

            $key = "$resId|$fecha|$shift";
            if (!isset($vsGroups[$key])) {
                $vsGroups[$key] = [
                    'resId'     => $resId,
                    'fecha'     => $fecha,
                    'shift'     => $shift,
                    // created_at in CSV may carry UTC offset; convert to CDMX local
                    'createdAt' => (function() use ($row, $cdmxTz): string {
                        $raw = trim($row['created_at'] ?? '');
                        if ($raw === '') return date('Y-m-d H:i:s');
                        $raw = preg_replace('/\.\d+/', '', $raw); // strip microseconds
                        try {
                            $dt = new DateTime($raw);             // parses offset if present
                            $dt->setTimezone($cdmxTz);            // convert to CDMX
                            return $dt->format('Y-m-d H:i:s');
                        } catch (Exception) {
                            return date('Y-m-d H:i:s');
                        }
                    })(),
                    'vsRows'    => [],
                ];
            }
            $vsGroups[$key]['vsRows'][] = $vsRow;
            $totalRows++;
        }

        // Insert one bitacora_entradas record per group
        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($vsGroups as $g) {
            $turnoId   = getOrCreateTurno($instId, $g['shift'], $g['fecha'], $userId, $db);
            $contenido = json_encode(['rows' => $g['vsRows']], JSON_UNESCAPED_UNICODE);
            $stmtIns->execute([
                $turnoId, $g['resId'], $userId, 'vitales', $contenido, 'normal', $g['createdAt'],
            ]);
            $n++;
            $stats['signos_vitales']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Signos vitales: $totalRows lecturas → $n entradas de bitácora"];
        continue;
    }

    // ── NURSING NOTES ────────────────────────────────────────────────────────
    if ($type === 'nursing_notes') {
        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $fecha     = toDate($row['created_at'] ?? null) ?? date('Y-m-d');
            $shift     = mapShift($row['shift'] ?? 'morning');
            $turnoId   = getOrCreateTurno($instId, $shift, $fecha, $userId, $db);
            $categoria = fixEnc($row['category'] ?? 'General');
            $contenido = fixEnc($row['content']  ?? '');
            if (!$contenido) { $stats['omitidos']++; continue; }
            $contenido = "[$categoria] $contenido";
            $sev       = strtolower($row['severity'] ?? 'low');
            $prio      = match($sev) { 'high','critical' => 'alta', 'medium','moderate' => 'alta', default => 'normal' };
            $createdAt = toDatetime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s');

            $stmtIns->execute([$turnoId, $resId, $userId, 'nota', $contenido, $prio, $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Notas de enfermería: $n"];
        continue;
    }

    // ── CARE LOGS ────────────────────────────────────────────────────────────
    if ($type === 'care_logs') {
        // English category → Spanish label
        $catMap = [
            'hygiene'           => 'Higiene',
            'bathing'           => 'Baño',
            'grooming'          => 'Aseo personal',
            'oral care'         => 'Higiene oral',
            'oral_care'         => 'Higiene oral',
            'dressing'          => 'Vestido',
            'skin care'         => 'Cuidado de piel',
            'skin_care'         => 'Cuidado de piel',
            'mobility'          => 'Movilidad',
            'ambulation'        => 'Deambulación',
            'transfer'          => 'Transferencia',
            'positioning'       => 'Posicionamiento',
            'exercise'          => 'Ejercicio',
            'rehabilitation'    => 'Rehabilitación',
            'feeding'           => 'Alimentación',
            'nutrition'         => 'Alimentación',
            'medication'        => 'Medicación',
            'wound care'        => 'Cura de heridas',
            'wound_care'        => 'Cura de heridas',
            'toileting'         => 'Eliminación',
            'elimination'       => 'Eliminación',
            'social'            => 'Social',
            'vitals'            => 'Signos vitales',
            'assessment'        => 'Valoración',
            'therapy'           => 'Terapia',
            'fall prevention'   => 'Prevención de caídas',
            'fall_prevention'   => 'Prevención de caídas',
        ];
        // Shift key → nurse column suffix
        $shiftNurseKey = ['matutino' => 'tm', 'vespertino' => 'tv', 'nocturno' => 'tn'];

        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        $cdmxTz = new DateTimeZone('America/Mexico_City');

        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            // ── Skip non-completed records ────────────────────────────────
            $status = strtolower(trim($row['status'] ?? 'completed'));
            if (in_array($status, ['false', '0', 'pending', 'cancelled', 'canceled', 'no'])) {
                $stats['omitidos']++;
                continue;
            }

            // ── performed_at: ISO timestamp with offset → CDMX ───────────
            $rawPa = preg_replace('/\.\d+/', '', trim($row['performed_at'] ?? ''));
            try {
                $dtPa = new DateTime($rawPa !== '' ? $rawPa : 'now');
                $dtPa->setTimezone($cdmxTz);
            } catch (Exception) {
                $dtPa = new DateTime('now', $cdmxTz);
            }
            $fecha     = $dtPa->format('Y-m-d');
            $createdAt = $dtPa->format('Y-m-d H:i:s');

            // ── Infer shift from CDMX hour ────────────────────────────────
            $hh = (int)$dtPa->format('H');
            if      ($hh >= 7  && $hh < 15) $shift = 'matutino';
            elseif  ($hh >= 15 && $hh < 23) $shift = 'vespertino';
            else                              $shift = 'nocturno';

            // ── Nurse: use column matching the inferred shift ─────────────
            $sk    = $shiftNurseKey[$shift];
            $nurse = fixEnc($row["${sk}_nurse"] ?? '');

            // ── Category: map English → Spanish ──────────────────────────
            $rawCat = strtolower(trim($row['category'] ?? ''));
            $cat    = $catMap[$rawCat] ?? fixEnc($row['category'] ?? 'Cuidado');

            // ── Notes: combine notes + relevant_notes ─────────────────────
            $notes   = fixEnc($row['notes']          ?? '');
            $relNotes= fixEnc($row['relevant_notes'] ?? '');
            $contenido = "[$cat]";
            if ($notes)    $contenido .= " $notes";
            if ($relNotes) $contenido .= ($notes ? ' — ' : ' ') . $relNotes;
            if ($nurse)    $contenido .= " | Cuidador/a: $nurse";

            $turnoId = getOrCreateTurno($instId, $shift, $fecha, $userId, $db);
            $stmtIns->execute([$turnoId, $resId, $userId, 'cuidados', $contenido, 'normal', $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Registros de cuidados: $n"];
        continue;
    }

    // ── NUTRITION LOGS ───────────────────────────────────────────────────────
    if ($type === 'nutrition_logs') {
        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $fecha     = toDate($row['date'] ?? $row['logged_at'] ?? null) ?? date('Y-m-d');
            $turnoId   = getOrCreateTurno($instId, 'matutino', $fecha, $userId, $db);
            $meal      = fixEnc($row['meal_type'] ?? 'Comida');
            $pct       = trim($row['percentage_consumed'] ?? '');
            $desc      = fixEnc($row['description'] ?? '');
            $notes     = fixEnc($row['notes']       ?? '');
            $contenido = "Nutrición — $meal" .
                ($pct  ? " — consumido: $pct%" : '') .
                ($desc ? ": $desc" : '') .
                ($notes? " ($notes)" : '');
            $createdAt = toDatetime($row['created_at'] ?? $row['logged_at'] ?? null) ?? date('Y-m-d H:i:s');

            $stmtIns->execute([$turnoId, $resId, $userId, 'actividad', $contenido, 'normal', $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Registros de nutrición: $n"];
        continue;
    }

    // ── SLEEP LOGS ───────────────────────────────────────────────────────────
    if ($type === 'sleep_logs') {
        $cdmxTz = new DateTimeZone('America/Mexico_City');

        // English CSV quality → Spanish label (matches data-q button values in UI)
        $qualityMap = [
            'good'        => 'Bueno',
            'fair'        => 'Regular',
            'poor'        => 'Malo',
            'interrupted' => 'Interrumpido',
        ];

        // Shift key abbreviations
        $skMap = ['matutino' => 'tm', 'vespertino' => 'tv', 'nocturno' => 'tn'];

        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );

        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $fecha = trim($row['date'] ?? '');
            if (!$fecha) { $stats['omitidos']++; continue; }

            $startRaw = trim($row['start_time'] ?? '');
            $endRaw   = trim($row['end_time']   ?? '');
            $startHH  = (int)substr($startRaw, 0, 2);
            $startMM  = (int)substr($startRaw, 3, 2);
            $endHH    = (int)substr($endRaw, 0, 2);
            $endMM    = (int)substr($endRaw, 3, 2);

            // Rows with 00:00/00:00 are nursing notes, not sleep records
            $isAnomaly = (($startRaw === '00:00:00' || $startRaw === '00:00') &&
                          ($endRaw   === '00:00:00' || $endRaw   === '00:00'));

            $obs = fixEnc($row['observations'] ?? '');

            // Convert created_at from UTC to CDMX
            $rawCa = preg_replace('/\.\d+/', '', trim($row['created_at'] ?? ''));
            try {
                $dtCa = new DateTime($rawCa !== '' ? $rawCa : 'now');
                $dtCa->setTimezone($cdmxTz);
            } catch (Exception) {
                $dtCa = new DateTime('now', $cdmxTz);
            }
            $createdAt = $dtCa->format('Y-m-d H:i:s');

            if ($isAnomaly) {
                // Import as plain note in nocturno shift
                $turnoId   = getOrCreateTurno($instId, 'nocturno', $fecha, $userId, $db);
                $contenido = $obs ?: 'Nota de turno nocturno';
                $stmtIns->execute([$turnoId, $resId, $userId, 'nota', $contenido, 'normal', $createdAt]);
                $n++;
                $stats['bitacora']++;
                continue;
            }

            // Infer shift from start_time hour
            if ($startHH >= 7 && $startHH < 15) {
                $shift = 'matutino';
            } elseif ($startHH >= 15 && $startHH < 23) {
                $shift = 'vespertino';
            } else {
                $shift = 'nocturno';
            }
            $sk = $skMap[$shift];

            // Calculate hours: account for midnight crossing
            $startMins = $startHH * 60 + $startMM;
            $endMins   = $endHH   * 60 + $endMM;
            if ($endMins <= $startMins) $endMins += 24 * 60;
            $hrs = round(($endMins - $startMins) / 60, 2);

            // Map English quality → Spanish
            $qualityKey = strtolower(trim($row['quality'] ?? ''));
            $quality    = $qualityMap[$qualityKey] ?? '';

            $contenido = json_encode([
                'sk'        => $sk,
                'quality'   => $quality,
                'hrs'       => $hrs,
                'times'     => [],
                'incidents' => [],
                'obs'       => $obs,
            ], JSON_UNESCAPED_UNICODE);

            $turnoId = getOrCreateTurno($instId, $shift, $fecha, $userId, $db);
            $stmtIns->execute([$turnoId, $resId, $userId, 'sueno', $contenido, 'normal', $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Registros de sueño: $n"];
        continue;
    }

    // ── ELIMINATION LOGS ─────────────────────────────────────────────────────
    if ($type === 'elimination_logs') {
        $stmtIns = $db->prepare(
            "INSERT INTO bitacora_entradas
               (turno_id, residente_id, usuario_id, tipo, contenido, prioridad, creado_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($rows as $row) {
            $uuid  = trim($row['resident_id'] ?? '');
            $resId = $residenteMap[$uuid] ?? null;
            if (!$resId) { $stats['omitidos']++; continue; }

            $fecha     = toDate($row['date'] ?? $row['logged_at'] ?? null) ?? date('Y-m-d');
            $turnoId   = getOrCreateTurno($instId, 'matutino', $fecha, $userId, $db);
            $tipo      = fixEnc($row['type']            ?? 'Eliminación');
            $caract    = fixEnc($row['characteristics'] ?? '');
            $notas     = fixEnc($row['notes']           ?? '');
            $contenido = "Eliminación — $tipo" .
                ($caract ? ": $caract" : '') .
                ($notas  ? ". $notas"  : '');
            $createdAt = toDatetime($row['created_at'] ?? $row['logged_at'] ?? null) ?? date('Y-m-d H:i:s');

            $stmtIns->execute([$turnoId, $resId, $userId, 'cuidados', $contenido, 'normal', $createdAt]);
            $n++;
            $stats['bitacora']++;
        }
        $log[] = ['level'=>'ok', 'msg'=>"  ✓ Registros de eliminación: $n"];
        continue;
    }
}

// ── Log en DB ────────────────────────────────────────────────────────────────
Log::registrar([
    'usuario_id'     => $userId,
    'institucion_id' => $instId,
    'accion'         => 'datos_importar',
    'modulo'         => 'sistema',
    'detalle'        => sprintf(
        'Importación completada: %d residentes, %d prescripciones, %d entradas bitácora, %d signos vitales',
        $stats['residentes'], $stats['prescripciones'], $stats['bitacora'], $stats['signos_vitales']
    ),
]);

ob_end_clean();
api_ok([
    'stats' => $stats,
    'log'   => $log,
], 'Importación completada');

} catch (\Throwable $e) {
    ob_end_clean();
    api_error('Error en la importación: ' . $e->getMessage(), 500);
}
