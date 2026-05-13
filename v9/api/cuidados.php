<?php
/**
 * GeriApp — API /api/cuidados.php
 *
 * GET  ?residente_id=N&fecha=YYYY-MM-DD         → registros del día
 * GET  ?residente_id=N&desde=X&hasta=Y          → registros por rango
 * GET  ?residente_id=N&fecha=YYYY-MM-DD&counts=1 → conteos por categoría
 * POST  action=crear                             → crear registro
 * DELETE ?id=N                                    → eliminar registro
 *
 * Roles: admin, medico, enfermero, superadmin, familiar (solo GET)
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/includes/EncryptionMap.php';
require_once dirname(__DIR__) . '/db/models/ExpedienteDoc.php';

/**
 * Build enriched detail JSON for audit logs.
 * Stores structured data so the UI sidebar can render rich detail.
 * @param array $extra  Optional: fecha, hora, observaciones, cambios
 */
function _buildLogDetalle(string $accion, int $residenteId, string $categoria, array $datos = [], int $registroId = 0, array $extra = []): string {
    $res = Residente::getById($residenteId, api_inst_id());
    $resName = $res ? strtoupper(trim(($res['nombre'] ?? '') . ' ' . ($res['apellidos'] ?? ''))) : '';

    $obj = [
        'accion'            => $accion,
        'categoria'         => $categoria,
        'residente_id'      => $residenteId,
        'residente_nombre'  => $resName ?: "ID {$residenteId}",
        'registro_id'       => $registroId,
    ];
    if (!empty($extra['fecha']))         $obj['fecha']         = $extra['fecha'];
    if (!empty($extra['hora']))          $obj['hora']          = $extra['hora'];
    if (!empty($extra['observaciones'])) $obj['observaciones'] = $extra['observaciones'];
    if (!empty($extra['cambios']))       $obj['cambios']       = $extra['cambios'];

    // Flatten datos (skip arrays/objects, empties)
    $flat = [];
    foreach ($datos as $k => $v) {
        if (is_array($v) || is_object($v)) continue;
        if ($v === '' || $v === null) continue;
        $flat[$k] = $v;
    }
    if ($flat) $obj['datos'] = $flat;

    return json_encode($obj, JSON_UNESCAPED_UNICODE);
}

/**
 * Helper: crear un registro en expediente_docs a partir de una nota médica.
 */
function _nmCrearExpedienteDoc(int $instId, int $residenteId, int $userId, string $contenidoJson, ?array $adjuntos, ?array $recetas = null): void {
    $parsed = json_decode($contenidoJson, true);
    if (!$parsed) return;

    // Título: Nota médica + fecha
    $titulo = 'Nota médica – ' . date('d/m/Y');

    // Descripción: para nota_medico/receta guardamos el JSON SOAP completo
    // (v:2) en `descripcion` para que el front-end pueda renderizar las
    // secciones con el mismo layout que en el módulo de cuidados. Se
    // antepone un resumen de texto plano separado por "\n\n---\n\n" para
    // que el fallback (stripHtml) muestre algo legible si el front no
    // sabe parsear v:2.
    $cleanText = function(string $s): string {
        $s = preg_replace('/<\s*li[^>]*>/i', '• ', $s);
        $s = preg_replace('/<\s*\/\s*li[^>]*>/i', ' ', $s);
        $s = preg_replace('/<\s*br\s*\/?\s*>/i', ' ', $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    };
    $summary = [];
    if (!empty($parsed['subjetivo']))  $summary[] = 'S: ' . mb_substr($cleanText($parsed['subjetivo']), 0, 120);
    if (!empty($parsed['analisis']))   $summary[] = 'A: ' . mb_substr($cleanText($parsed['analisis']),  0, 120);
    if (!empty($parsed['plan']))       $summary[] = 'P: ' . mb_substr($cleanText($parsed['plan']),      0, 120);
    if (!empty($parsed['diagnosticos'])) {
        $codes = array_map(fn($d) => $d['codigo'] ?? '', $parsed['diagnosticos']);
        $summary[] = 'Dx: ' . implode(', ', array_filter($codes));
    }
    // Guardamos el JSON original íntegro como descripción para poder
    // renderizar SOAP en el sidebar (front-end detecta {"v":2,...}).
    $descripcion = $contenidoJson;

    // Merge adjuntos + recetas for archivos JSON
    $allFiles = [];
    if ($adjuntos && count($adjuntos)) $allFiles = array_merge($allFiles, $adjuntos);
    if ($recetas && count($recetas))   $allFiles = array_merge($allFiles, $recetas);
    $archivosJson = count($allFiles) ? json_encode($allFiles, JSON_UNESCAPED_UNICODE) : null;

    // Obtener nombre del médico
    $user = Usuario::getById($userId);
    $nombreFuente = $user ? ($user['nombre'] ?? 'Médico') : 'Médico';

    // Tipo de expediente: si hay recetas adjuntas → 'receta', sino → 'nota_medico'
    $tipoExp = ($recetas && count($recetas)) ? 'receta' : 'nota_medico';

    ExpedienteDoc::create([
        'institucion_id'  => $instId,
        'residente_id'    => $residenteId,
        'tipo'            => $tipoExp,
        'titulo'          => $titulo,
        'descripcion'     => $descripcion,
        'fuente'          => 'medico',
        'nombre_fuente'   => $nombreFuente,
        'fecha_documento' => date('Y-m-d'),
        'archivos_json'   => $archivosJson,
        'created_by'      => $userId,
    ]);
}

function _nmFiltrarSignosVitalesActivos(string $contenidoJson, $activos): string {
    if (!is_array($activos)) return $contenidoJson;

    $parsed = json_decode($contenidoJson, true);
    if (!is_array($parsed) || (int)($parsed['v'] ?? 0) !== 2) return $contenidoJson;

    $activeSet = [];
    foreach ($activos as $key) {
        $key = trim((string)$key);
        if ($key !== '') $activeSet[$key] = true;
    }

    $sv = isset($parsed['signos_vitales']) && is_array($parsed['signos_vitales']) ? $parsed['signos_vitales'] : [];
    $map = [
        'ta'      => ['ta_sys', 'ta_dia'],
        'fc'      => ['fc'],
        'fr'      => ['fr'],
        'temp'    => ['temp'],
        'spo2'    => ['spo2'],
        'peso'    => ['peso'],
        'glucosa' => ['glucosa'],
    ];

    $clean = [];
    foreach ($map as $toggleKey => $fields) {
        if (empty($activeSet[$toggleKey])) continue;
        foreach ($fields as $field) {
            if (array_key_exists($field, $sv) && $sv[$field] !== '' && $sv[$field] !== null) {
                $clean[$field] = $sv[$field];
            }
        }
    }

    if ($clean) $parsed['signos_vitales'] = $clean;
    else unset($parsed['signos_vitales']);

    $encoded = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : $contenidoJson;
}

$method = api_method();

// ─────────────────────────────────────────────────────────────────────────────
// GET
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero', 'familiar']);

    $instId = api_inst_id();

    // ── Dashboard batch: all data in a single request ───────────────────
    if (isset($_GET['dashboard'])) {
        $residenteId = api_int('residente_id');
        if (!$residenteId) api_error('residente_id requerido', 400);
        api_assert_residente($residenteId);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        // 1. Registros + counts
        $registros = Cuidado::getByFecha($instId, $residenteId, $fecha);
        $counts    = Cuidado::countByCategoria($instId, $residenteId, $fecha);

        // 2. (bitacora legacy removed in v1.26.0 — tables dropped)

        // 3. Inventory
        $invItems   = Inventario::getItems($instId, null, $residenteId);
        $invLow     = Inventario::getLowStock($instId, $residenteId);

        // 4. Notas
        $notas      = Cuidado::getNotas($instId, $residenteId, $fecha);
        $notasCount = Cuidado::countNotas($instId, $residenteId, $fecha);

        // 4b. Notas de médico (vigente)
        try {
            $notaMedico = Cuidado::getNotaMedicoVigente($instId, $residenteId);
        } catch (\Throwable $e) {
            $notaMedico = null; // tabla puede no existir aún
        }

        // 5. Resident info
        $residente = Residente::getById($residenteId, $instId);

        // 5b. Linked familiar users for familia panel
        $userIds = UsuarioResidente::getUsuarioIds($residenteId, $instId);
        $famUsers = [];
        // Parsear contactos_json del residente para enriquecer con parentesco
        $contactosArr = [];
        if ($residente && !empty($residente['contactos_json'])) {
            $tmpC = json_decode($residente['contactos_json'], true);
            if (is_array($tmpC)) $contactosArr = $tmpC;
        }
        $matchParentesco = function($u) use ($contactosArr) {
            $email = strtolower(trim($u['email'] ?? ''));
            $tel   = preg_replace('/\D+/', '', $u['telefono'] ?? '');
            $nom   = strtolower(trim($u['nombre'] ?? ''));
            foreach ($contactosArr as $c) {
                $ce = strtolower(trim($c['email'] ?? ''));
                $ct = preg_replace('/\D+/', '', $c['telefono'] ?? '');
                $cn = strtolower(trim($c['nombre'] ?? ''));
                if ($email && $ce && $email === $ce) return $c['parentesco'] ?? '';
                if ($tel   && $ct && $tel === $ct)   return $c['parentesco'] ?? '';
                if ($nom   && $cn && $nom === $cn)   return $c['parentesco'] ?? '';
            }
            return '';
        };
        $inactiveLinkedKeys = ['emails' => [], 'phones' => []];
        if (!empty($userIds)) {
            try {
                $masterDb = Database::getMaster();
                $place = implode(',', array_fill(0, count($userIds), '?'));
                $stmtInactive = $masterDb->prepare(
                    "SELECT u.email, u.telefono
                     FROM usuarios u
                     LEFT JOIN usuario_instituciones ui
                       ON ui.usuario_id = u.id
                      AND ui.institucion_id = ?
                      AND ui.estado = 'activo'
                     WHERE u.id IN ($place)
                       AND (u.estado <> 'activo' OR ui.usuario_id IS NULL)"
                );
                $stmtInactive->execute(array_merge([$instId], array_map('intval', $userIds)));
                foreach ($stmtInactive->fetchAll(\PDO::FETCH_ASSOC) as $inactiveUser) {
                    $emailKey = strtolower(trim((string)($inactiveUser['email'] ?? '')));
                    $phoneKey = preg_replace('/\D+/', '', (string)($inactiveUser['telefono'] ?? '')) ?: '';
                    if ($emailKey !== '') $inactiveLinkedKeys['emails'][$emailKey] = true;
                    if ($phoneKey !== '') $inactiveLinkedKeys['phones'][$phoneKey] = true;
                }
            } catch (\Throwable $e) { $inactiveLinkedKeys = ['emails' => [], 'phones' => []]; }
        }
        $matchesInactiveLinkedUser = function(array $contact) use ($inactiveLinkedKeys): bool {
            $emailKey = strtolower(trim((string)($contact['email'] ?? '')));
            $phoneKey = preg_replace('/\D+/', '', (string)($contact['telefono'] ?? '')) ?: '';
            return ($emailKey !== '' && !empty($inactiveLinkedKeys['emails'][$emailKey]))
                || ($phoneKey !== '' && !empty($inactiveLinkedKeys['phones'][$phoneKey]));
        };
        if (!empty($userIds)) {
            $masterDb = Database::getMaster();
            $place = implode(',', array_fill(0, count($userIds), '?'));
            $stmtFam = $masterDb->prepare(
                "SELECT u.id, u.nombre, u.email, u.telefono
                 FROM usuarios u
                 INNER JOIN usuario_instituciones ui
                   ON ui.usuario_id = u.id
                  AND ui.institucion_id = ?
                  AND ui.estado = 'activo'
                  AND ui.rol = 'familiar'
                 WHERE u.estado = 'activo'
                   AND u.id IN ($place)"
            );
            $stmtFam->execute(array_merge([$instId], array_map('intval', $userIds)));
            foreach ($stmtFam->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $famUsers[] = [
                    'usuario_id' => (int)$u['id'],
                    'nombre'     => $u['nombre'] ?? '',
                    'email'      => $u['email'] ?? '',
                    'telefono'   => $u['telefono'] ?? '',
                    'parentesco' => $matchParentesco($u),
                ];
            }
        }
        if ($residente) {
            $residente['familiares_usuarios'] = $famUsers;

            $seatSummary = null;
            try {
                require_once dirname(__DIR__) . '/db/models/Seats.php';
                $sub = Seats::getActiveSubForInst($instId);
                if ($sub) {
                    $included = isset($sub['max_familiares']) ? (int)$sub['max_familiares'] : null;
                    $extra = (int)($sub['seats_familiar_extra'] ?? 0);
                    $used = Seats::countFamiliares($instId);
                    $seatSummary = [
                        'plan_nombre' => $sub['plan_nombre'] ?? null,
                        'included'    => $included,
                        'extra'       => $extra,
                        'total'       => $included === null ? null : ($included + $extra),
                        'used'        => $used,
                        'available'   => $included === null ? null : max(0, ($included + $extra) - $used),
                    ];
                }
            } catch (\Throwable $e) { $seatSummary = null; }

            $contactosBase = [];
            $notifPrefKeys = [
                'notif_emergencia','notif_signos','notif_incidentes','notif_caida','notif_medicacion','notif_med_omitida',
                'notif_alimentacion','notif_higiene','notif_eliminacion','notif_sueno','notif_animo','notif_movilidad',
                'notif_terapia','notif_reporte','notif_reporte_hora','notif_reporte_pdf','notif_semanal','notif_notas_medico',
                'notif_visitas','notif_stock','notif_solo_criticas','notif_quiet_enabled','notif_quiet_start','notif_quiet_end',
                'notif_wa','notif_email'
            ];
            foreach ($contactosArr as $idx => $c) {
                if (!is_array($c)) continue;
                if (($c['source'] ?? '') === 'staff_notif' || !empty($c['__notifStaff'])) continue;
                if ($matchesInactiveLinkedUser($c)) continue;
                $hasAny = trim((string)($c['nombre'] ?? '')) !== ''
                    || trim((string)($c['email'] ?? '')) !== ''
                    || trim((string)($c['telefono'] ?? '')) !== '';
                if (!$hasAny) continue;
                $contactRow = [
                    'source'     => 'contacto',
                    'contact_idx'=> $idx,
                    'nombre'     => $c['nombre'] ?? '',
                    'email'      => $c['email'] ?? '',
                    'telefono'   => $c['telefono'] ?? '',
                    'telefono2'  => $c['telefono2'] ?? '',
                    'parentesco' => $c['parentesco'] ?? '',
                    'direccion'  => $c['direccion'] ?? '',
                    'principal'  => !empty($c['principal']) ? 1 : 0,
                    'estado_cuenta' => 'contacto',
                ];
                foreach ($notifPrefKeys as $prefKey) {
                    if (array_key_exists($prefKey, $c)) $contactRow[$prefKey] = $c[$prefKey];
                }
                $contactosBase[] = $contactRow;
            }
            if (empty($contactosBase)) {
                $hasFlat = trim((string)($residente['contacto_nombre'] ?? '')) !== ''
                    || trim((string)($residente['contacto_email'] ?? '')) !== ''
                    || trim((string)($residente['contacto_telefono'] ?? '')) !== '';
                if ($hasFlat) {
                    $flatRow = [
                        'source'     => 'contacto',
                        'contact_idx'=> 0,
                        'nombre'     => $residente['contacto_nombre'] ?? '',
                        'email'      => $residente['contacto_email'] ?? '',
                        'telefono'   => $residente['contacto_telefono'] ?? '',
                        'telefono2'  => $residente['contacto_telefono2'] ?? '',
                        'parentesco' => $residente['contacto_parentesco'] ?? '',
                        'direccion'  => $residente['contacto_direccion'] ?? '',
                        'principal'  => 1,
                        'estado_cuenta' => 'contacto',
                    ];
                    if (!$matchesInactiveLinkedUser($flatRow)) $contactosBase[] = $flatRow;
                }
            }

            $panelRows = [];
            $rowKey = function(array $row): string {
                if (!empty($row['usuario_id'])) return 'u:' . (int)$row['usuario_id'];
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if ($email !== '') return 'e:' . $email;
                $phone = preg_replace('/\D+/', '', (string)($row['telefono'] ?? ''));
                if ($phone !== '') return 'p:' . $phone;
                return 'n:' . strtolower(trim((string)($row['nombre'] ?? '')));
            };
            $findRow = function(array $needle) use (&$panelRows, $rowKey): int {
                $key = $rowKey($needle);
                if ($key === 'n:') return -1;
                foreach ($panelRows as $idx => $row) {
                    if ($rowKey($row) === $key) return $idx;
                }
                return -1;
            };
            foreach ($contactosBase as $c) $panelRows[] = $c;

            foreach ($famUsers as $u) {
                $row = [
                    'source'     => 'usuario',
                    'usuario_id' => (int)$u['usuario_id'],
                    'nombre'     => $u['nombre'] ?? '',
                    'email'      => $u['email'] ?? '',
                    'telefono'   => $u['telefono'] ?? '',
                    'parentesco' => $u['parentesco'] ?? '',
                    'estado_cuenta' => 'registrado',
                ];
                $idx = $findRow($row);
                if ($idx >= 0) {
                    $panelRows[$idx] = array_merge($panelRows[$idx], array_filter($row, fn($v) => $v !== '' && $v !== null));
                    $panelRows[$idx]['estado_cuenta'] = 'registrado';
                    $panelRows[$idx]['usuario_id'] = (int)$u['usuario_id'];
                } else {
                    $panelRows[] = $row;
                }
            }

            try {
                $invRows = Invitacion::getAll($instId);
                foreach ($invRows as $inv) {
                    if (($inv['rol'] ?? '') !== 'familiar') continue;
                    if (($inv['estado'] ?? '') === 'aceptada') continue;
                    $resIds = [];
                    if (!empty($inv['residente_ids'])) {
                        $decoded = json_decode((string)$inv['residente_ids'], true);
                        if (is_array($decoded)) $resIds = array_map('intval', $decoded);
                    }
                    if (!in_array($residenteId, $resIds, true)) continue;
                    $nombreInv = trim((string)($inv['nombre_sugerido'] ?? '') . ' ' . (string)($inv['apellido_sugerido'] ?? ''));
                    $row = [
                        'source'        => 'invitacion',
                        'invitacion_id' => (int)$inv['id'],
                        'nombre'        => $nombreInv,
                        'email'         => $inv['email'] ?? '',
                        'telefono'      => $inv['telefono'] ?? '',
                        'parentesco'    => '',
                        'estado_cuenta' => 'invitado',
                        'invitacion'    => [
                            'id' => (int)$inv['id'],
                            'email' => $inv['email'] ?? '',
                            'telefono' => $inv['telefono'] ?? '',
                            'nombre_sugerido' => $inv['nombre_sugerido'] ?? '',
                            'apellido_sugerido' => $inv['apellido_sugerido'] ?? '',
                            'mensaje' => $inv['mensaje'] ?? '',
                            'status' => $inv['estado'] ?? '',
                            'enviada' => $inv['creado_at'] ?? '',
                            'expira' => '',
                            'token' => $inv['token'] ?? '',
                            'creado_por_nombre' => $inv['creado_por_nombre'] ?? '',
                        ],
                    ];
                    $idx = $findRow($row);
                    if ($idx >= 0) {
                        $panelRows[$idx]['invitacion_id'] = (int)$inv['id'];
                        $panelRows[$idx]['invitacion'] = $row['invitacion'];
                        if (($panelRows[$idx]['estado_cuenta'] ?? '') !== 'registrado') {
                            $panelRows[$idx]['estado_cuenta'] = 'invitado';
                        }
                        foreach (['nombre','email','telefono'] as $k) {
                            if (empty($panelRows[$idx][$k]) && !empty($row[$k])) $panelRows[$idx][$k] = $row[$k];
                        }
                    } else {
                        $panelRows[] = $row;
                    }
                }
            } catch (\Throwable $e) { /* invitaciones no deben romper dashboard */ }

            $includedLimit = $seatSummary['included'] ?? null;
            $seatOrdinal = 0;
            foreach ($panelRows as &$row) {
                $countsAsSeat = !empty($row['usuario_id']) || !empty($row['invitacion_id']) || (($row['email'] ?? '') !== '');
                if ($includedLimit !== null && $countsAsSeat) {
                    $row['seat_number'] = $seatOrdinal + 1;
                    $row['seat_scope'] = $seatOrdinal < (int)$includedLimit ? 'incluido' : 'extra';
                    $row['requiere_pago_extra'] = $row['seat_scope'] === 'extra' ? 1 : 0;
                    $seatOrdinal++;
                } else {
                    $row['seat_scope'] = $includedLimit === null ? 'sin_limite' : 'contacto';
                    $row['requiere_pago_extra'] = 0;
                }
            }
            unset($row);

            if ($seatSummary) {
                $seatSummary['visible_panel_count'] = $seatOrdinal;
                $seatSummary['pending_invites'] = count(array_filter($panelRows, fn($r) => !empty($r['invitacion_id']) && empty($r['usuario_id']) && (($r['invitacion']['status'] ?? '') === 'pendiente')));
            }
            $residente['familiares_panel'] = array_values($panelRows);
            $residente['familiares_seats'] = $seatSummary;
        }

        // 6. Last heces record (for hours-since badge)
        $ultimaHeces = Cuidado::getUltimaHeces($instId, $residenteId);

        // 7. Last vital signs (for out-of-range alert badge)
        $ultimosSignos = Cuidado::getUltimosSignos($instId, $residenteId);

        // 8. Pending sleep record (partial — missing wake-up time)
        $suenoPendiente = Cuidado::getSuenoPendiente($instId, $residenteId);

        // 9. Server-authoritative badge counts (single source of truth shared
        //     with /api/residentes.php so both views always agree). Computed
        //     in server timezone; client must NOT re-derive these from local
        //     Date math (would drift when institution tz != server default).
        require_once __DIR__ . '/_badge_helpers.php';
        $today    = date('Y-m-d');
        $nowDate  = date('Y-m-d');
        $nowTime  = date('H:i');
        $rxPend   = 0;
        try {
            $rxList     = Prescripcion::getForResidente($residenteId);
            $rxPend     = _med_overdue_count($rxList, $registros, $fecha, $nowDate, $nowTime);
        } catch (\Throwable $e) { /* badge value 0 on error */ }
        $signosOut  = _signos_out_count($ultimosSignos);
        $hecesHoras = _heces_hours_since($ultimaHeces);
        $badges = [
            'medicacion_pendiente' => $rxPend,
            'signos_fuera_rango'   => $signosOut,
            'heces_horas'          => $hecesHoras,
        ];

        // §3.7 Filtrado para rol familiar: solo ve registros de cuidado,
        // info básica del residente y notas. NO ve bitácora interna,
        // inv. completo, ni datos de otros familiares.
        $isFamiliar = (api_rol() === 'familiar');

        // Si es familiar, verificar que está vinculado a este residente
        if ($isFamiliar && empty($_SESSION['sa_impersonating'])) {
            $linkedRes = UsuarioResidente::getResidenteIds(api_user_id(), $instId);
            if (!in_array($residenteId, array_map('intval', $linkedRes), true)) {
                api_error('No tienes acceso a este residente.', 403);
            }
        }

        $response = [
            'registros'        => $registros,
            'counts'           => $counts,
            'residente'        => $residente,
            'ultima_heces'     => $ultimaHeces,
            'ultimos_signos'   => $ultimosSignos,
            'sueno_pendiente'  => $suenoPendiente,
            'badges'           => $badges,
        ];

        if (!$isFamiliar) {
            // Staff completo: incluir inventario, notas
            $response['bitacora']     = []; // legacy — removed in v1.26.0
            $response['inventario']   = ['items' => $invItems, 'low_stock' => $invLow];
            $response['notas']        = ['notas' => $notas, 'count' => $notasCount];
            $response['nota_medico']  = $notaMedico;
        } else {
            // Familiar: vaciar datos internos, ocultar campos sensibles del residente
            $response['bitacora']     = [];
            $response['inventario']   = ['items' => [], 'low_stock' => []];
            $response['notas']        = ['notas' => [], 'count' => 0];
            $response['nota_medico']  = $notaMedico; // Familiares pueden ver indicaciones médicas
            // Ocultar datos sensibles del residente
            if ($response['residente']) {
                unset(
                    $response['residente']['curp'],
                    $response['residente']['nss'],
                    $response['residente']['contacto_emergencia_telefono'],
                    $response['residente']['familiares_usuarios']
                );
            }
        }

        api_ok($response);
    }

    // ── WhatsApp check ──────────────────────────────────────────────────
    // Cacheado en sesión por 24h por número para no consumir la cuota diaria
    // del proveedor (WaSender API: 1000 req/día por endpoint /on-whatsapp).
    if (isset($_GET['check_whatsapp'])) {
        $phone = trim($_GET['check_whatsapp']);
        if (!$phone) api_error('Número requerido', 400);
        $cacheKey = '_wa_check_' . md5($instId . '|' . $phone);
        $entry = $_SESSION[$cacheKey] ?? null;
        if (is_array($entry) && (time() - ($entry['at'] ?? 0)) < 86400) {
            api_ok(['registered' => $entry['registered'], 'phone' => $phone, 'cached' => true]);
        }
        try {
            $wa = WaSenderAPI::fromConfig($instId);
            $registered = $wa->isRegistered($phone);
            // Solo cacheamos respuestas concretas (true/false). Si fue null
            // (cuota agotada / error), reintentamos en la próxima.
            if ($registered !== null) {
                $_SESSION[$cacheKey] = ['registered' => $registered, 'at' => time()];
            }
            api_ok(['registered' => $registered, 'phone' => $phone]);
        } catch (\Throwable $e) {
            api_ok(['registered' => null, 'phone' => $phone, 'error' => 'No se pudo verificar']);
        }
    }

    // ── Inventario queries ──────────────────────────────────────────────
    if (isset($_GET['inventario'])) {
        $tipo = $_GET['tipo'] ?? null;
        $residenteId = !empty($_GET['residente_id']) ? (int) $_GET['residente_id'] : null;
        $items = Inventario::getItems($instId, $tipo ?: null, $residenteId);
        $lowStock = Inventario::getLowStock($instId, $residenteId);
        api_ok(['items' => $items, 'low_stock' => $lowStock]);
    }

    if (isset($_GET['movimientos'])) {
        $itemId = !empty($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $residenteId = !empty($_GET['residente_id']) ? (int) $_GET['residente_id'] : null;
        $movs = Inventario::getMovimientos($instId, $itemId, 50, $residenteId);
        api_ok($movs);
    }

    // ── Notification log query ──────────────────────────────────────────
    if (isset($_GET['notif_log'])) {
        $residenteId = !empty($_GET['residente_id']) ? (int) $_GET['residente_id'] : null;
        $db = Database::getTenant($instId);
        // Auto-create table if not exists
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
        $sql = "SELECT id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle, fecha FROM notificaciones_log WHERE institucion_id = ?";
        $params = [$instId];
        if ($residenteId) { $sql .= " AND residente_id = ?"; $params[] = $residenteId; }

        // Scope to the current user. Admin/superadmin see every notification
        // emitted by the institution. Other roles (medico, enfermero, familiar)
        // only see notifications belonging to residentes they are linked to via
        // UsuarioResidente. If the user has no linked residentes, return an
        // empty list rather than leaking the institution-wide log.
        $callerRole = api_rol();
        if (!in_array($callerRole, ['admin', 'superadmin'], true) && empty($_SESSION['sa_impersonating'])) {
            try {
                $linkedIds = UsuarioResidente::getResidenteIds(api_user_id(), $instId);
            } catch (\Throwable $e) {
                $linkedIds = [];
            }
            $linkedIds = array_values(array_unique(array_map('intval', $linkedIds)));
            if (empty($linkedIds)) {
                api_ok(['logs' => []]);
            }
            // If a residente_id was passed and is not linked to this user, deny.
            if ($residenteId && !in_array((int)$residenteId, $linkedIds, true)) {
                api_ok(['logs' => []]);
            }
            $ph = implode(',', array_fill(0, count($linkedIds), '?'));
            $sql .= " AND residente_id IN ($ph)";
            $params = array_merge($params, $linkedIds);
        }

        $sql .= " ORDER BY fecha DESC LIMIT 50";
        $st = $db->prepare($sql);
        $st->execute($params);
        // §5 Decrypt mensaje column. Older rows also stored `destinatario`
        // as ciphertext when contactos_json/contacto_telefono were already in
        // 'consolidated' phase at write time and the source values were not
        // decrypted before being copied into notificaciones_log. Apply a safe
        // best-effort Cipher::decrypt() on `destinatario` so the historial
        // shows phone/email in plain text. Cipher::decrypt() returns the
        // input unchanged if it is not actually ciphertext.
        $logs = array_map(function($r) {
            $r = EncryptionMap::decryptRow('notificaciones_log', $r);
            if (!empty($r['destinatario'])) {
                try { $r['destinatario'] = Cipher::decrypt($r['destinatario']); } catch (\Throwable $e) {}
            }
            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC));
        api_ok(['logs' => $logs]);
    }

    // ── Notas queries ───────────────────────────────────────────────────
    if (isset($_GET['notas'])) {
        $residenteId = api_int('residente_id');
        if (!$residenteId) api_error('residente_id requerido', 400);
        api_assert_residente($residenteId);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $notas = Cuidado::getNotas($instId, $residenteId, $fecha);
        $count = Cuidado::countNotas($instId, $residenteId, $fecha);
        api_ok(['notas' => $notas, 'count' => $count]);
    }

    // ── Registro individual por ID ──────────────────────────────────────
    if (isset($_GET['registro_id'])) {
        $id = (int) $_GET['registro_id'];
        if (!$id) api_error('registro_id requerido', 400);
        $registro = Cuidado::getById($id, $instId);
        if (!$registro) api_error('Registro no encontrado', 404);
        api_ok($registro);
    }

    // ── Notas de Médico queries ──────────────────────────────────────────
    if (isset($_GET['notas_medico'])) {
        $residenteId = api_int('residente_id');
        if (!$residenteId) api_error('residente_id requerido', 400);
        api_assert_residente($residenteId);
        try {
            $archivoLimit = max(20, min(200, api_int('archivo_limit') ?: 20));
            $vigente  = Cuidado::getNotaMedicoVigente($instId, $residenteId);
            $archivo  = Cuidado::getNotasMedicoArchivo($instId, $residenteId, $archivoLimit);
        } catch (\Throwable $e) {
            $vigente = null; $archivo = []; // tabla puede no existir aún
        }
        api_ok(['vigente' => $vigente, 'archivo' => $archivo]);
    }

    // ── Alertas al Médico queries ────────────────────────────────────────
    if (isset($_GET['alertas_medico'])) {
        $residenteId = api_int('residente_id');
        if (!$residenteId) api_error('residente_id requerido', 400);
        api_assert_residente($residenteId);
        try {
            $db = Database::getTenant($instId);
            // Check table exists
            $db->query("SELECT 1 FROM alertas_medico LIMIT 0");

            // Cleanup orphan alerts (care record was deleted)
            try {
                $cleanup = $db->prepare("\n                    DELETE a FROM alertas_medico a\n                    LEFT JOIN cuidados_registros cr\n                      ON cr.id = a.registro_id AND cr.institucion_id = a.institucion_id\n                    WHERE a.institucion_id = ? AND a.residente_id = ? AND cr.id IS NULL\n                ");
                $cleanup->execute([$instId, $residenteId]);
            } catch (\Throwable $e) {}

            if (!empty($_GET['historial'])) {
                // All alerts (pending + acknowledged) with doctor/caregiver names
                $st = $db->prepare("
                    SELECT a.*, uc.nombre AS creador_nombre,
                           ud.nombre AS doctor_nombre
                    FROM alertas_medico a
                    LEFT JOIN " . DB_MASTER_NAME . ".usuarios uc ON uc.id = a.creado_por
                    LEFT JOIN " . DB_MASTER_NAME . ".usuarios ud ON ud.id = a.visto_por
                    WHERE a.residente_id = ? AND a.institucion_id = ?
                    ORDER BY a.creado_at DESC LIMIT 50
                ");
                $st->execute([$residenteId, $instId]);
                api_ok($st->fetchAll(PDO::FETCH_ASSOC));
            } else {
                // Only pending alerts (visto_por IS NULL) — include registro details
                $st = $db->prepare("
                    SELECT a.*, uc.nombre AS creador_nombre,
                           cr.datos AS reg_datos, cr.hora AS reg_hora, cr.observaciones AS reg_obs
                    FROM alertas_medico a
                    LEFT JOIN " . DB_MASTER_NAME . ".usuarios uc ON uc.id = a.creado_por
                    LEFT JOIN cuidados_registros cr ON cr.id = a.registro_id AND cr.institucion_id = a.institucion_id
                    WHERE a.residente_id = ? AND a.institucion_id = ?
                                            AND a.visto_por IS NULL
                                            AND cr.id IS NOT NULL
                    ORDER BY a.creado_at DESC
                ");
                $st->execute([$residenteId, $instId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                // Decrypt registro datos + parse JSON
                foreach ($rows as &$row) {
                    if ($row['reg_datos'] !== null) {
                        $dec = EncryptionMap::decryptRow('cuidados_registros', ['datos' => $row['reg_datos'], 'observaciones' => $row['reg_obs'] ?? '']);
                        $row['reg_datos'] = is_string($dec['datos']) ? json_decode($dec['datos'], true) : $dec['datos'];
                        $row['reg_obs']   = $dec['observaciones'] ?? $row['reg_obs'];
                    }
                }
                unset($row);
                api_ok($rows);
            }
        } catch (\Throwable $e) {
            api_ok([]); // table may not exist yet
        }
    }

    // ── Bitacora endpoint (legacy — removed in v1.26.0) ─────────────────
    if (isset($_GET['bitacora'])) {
        api_ok([]);
    }

    // ── Cuidados queries ────────────────────────────────────────────────
    $residenteId = api_int('residente_id');
    if (!$residenteId) api_error('residente_id requerido', 400);
    api_assert_residente($residenteId);

    // Conteos por categoría
    if (!empty($_GET['counts'])) {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $counts = Cuidado::countByCategoria($instId, $residenteId, $fecha);
        api_ok($counts);
    }

    // Rango de fechas (para reportes)
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $registros = Cuidado::getByRango($instId, $residenteId, $_GET['desde'], $_GET['hasta']);
        $stats = Cuidado::statsByRango($instId, $residenteId, $_GET['desde'], $_GET['hasta']);
        api_ok(['registros' => $registros, 'stats' => $stats]);
    }

    // Default: registros del día
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $registros = Cuidado::getByFecha($instId, $residenteId, $fecha);
    $counts = Cuidado::countByCategoria($instId, $residenteId, $fecha);
    api_ok(['registros' => $registros, 'counts' => $counts]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST multipart — file upload for Notas de Médico (adjuntos)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && !empty($_FILES['nm_adjuntos'])) {
    api_auth_roles(['medico', 'superadmin']);
    $instId = api_inst_id();
    $dir = dirname(__DIR__) . "/uploads/notas_medico/{$instId}";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $files = $_FILES['nm_adjuntos'];
    $isMulti = is_array($files['name']);
    $count = $isMulti ? count($files['name']) : 1;
    if ($count > 10) api_error('Máximo 10 archivos por nota', 400);

    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $allowedMime = ['image/jpeg','image/png','image/webp','application/pdf'];
    $maxSize = 10 * 1024 * 1024; // 10 MB
    $uploaded = [];

    for ($i = 0; $i < $count; $i++) {
        $name  = $isMulti ? $files['name'][$i]     : $files['name'];
        $tmp   = $isMulti ? $files['tmp_name'][$i]  : $files['tmp_name'];
        $error = $isMulti ? $files['error'][$i]     : $files['error'];
        $size  = $isMulti ? $files['size'][$i]      : $files['size'];

        if ($error !== UPLOAD_ERR_OK) continue;
        if ($size > $maxSize) api_error("Archivo \"{$name}\" excede 10 MB", 413);

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            api_error("Tipo de archivo no permitido: .{$ext}", 415);
        }
        $mime = api_validate_mime($tmp, $allowedMime);

        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $dir . '/' . $safeName;

        // Images: compress server-side (max 2048px, JPEG 82%)
        $saved = false;
        if (str_starts_with($mime, 'image/') && function_exists('imagecreatefromjpeg')) {
            $img = match(true) {
                str_contains($mime, 'png')  => @imagecreatefrompng($tmp),
                str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($tmp),
                default => @imagecreatefromjpeg($tmp),
            };
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                $max = 2048;
                if ($w > $max || $h > $max) {
                    if ($w > $h) { $nw = $max; $nh = (int)round($h * $max / $w); }
                    else         { $nh = $max; $nw = (int)round($w * $max / $h); }
                    $resized = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagedestroy($img);
                    $img = $resized;
                }
                $destJpg = preg_replace('/\.\w+$/', '.jpg', $dest);
                if (imagejpeg($img, $destJpg, 82)) {
                    $saved = true;
                    $safeName = basename($destJpg);
                }
                imagedestroy($img);
            }
        }
        if (!$saved) {
            if (!move_uploaded_file($tmp, $dest)) {
                api_error("Error al guardar archivo \"{$name}\"", 500);
            }
        }

        $url = str_replace('\\', '/', "uploads/notas_medico/{$instId}/{$safeName}");
        $uploaded[] = [
            'nombre' => basename($name),
            'url'    => $url,
            'tipo'   => $mime,
            'size'   => $size,
        ];
    }

    api_ok(['adjuntos' => $uploaded], count($uploaded) . ' archivo(s) subido(s)');
}

// ─────────────────────────────────────────────────────────────────────────────
// POST multipart — photo upload for alimentación (must be checked BEFORE JSON body)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && !empty($_FILES['foto'])) {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

    $file = $_FILES['foto'];
    if ($file['error'] !== UPLOAD_ERR_OK) api_error('Error al subir foto', 400);
    if ($file['size'] > 5 * 1024 * 1024)  api_error('Foto muy grande (máx 5MB)', 413);

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        api_error('Solo se permiten imágenes JPG/PNG/WEBP', 415);
    }
    // §6.5 Validación MIME reforzada con finfo
    $mime = api_validate_mime($file['tmp_name'], ['image/jpeg','image/png','image/webp']);

    $instId = api_inst_id();
    $dir = dirname(__DIR__) . "/uploads/cuidados/{$instId}";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = uniqid('food_') . '.jpg';
    $dest = $dir . '/' . $name;

    // Server-side compression: resize to max 1200px & save as JPEG 80%
    $src = $file['tmp_name'];
    $compressed = false;
    if (function_exists('imagecreatefromjpeg')) {
        $img = match(true) {
            str_contains($mime, 'png')  => @imagecreatefrompng($src),
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($src),
            default => @imagecreatefromjpeg($src),
        };
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            $max = 1200;
            if ($w > $max || $h > $max) {
                if ($w > $h) { $nw = $max; $nh = (int)round($h * $max / $w); }
                else         { $nh = $max; $nw = (int)round($w * $max / $h); }
                $resized = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            if (imagejpeg($img, $dest, 80)) { $compressed = true; }
            imagedestroy($img);
        }
    }
    if (!$compressed) {
        if (!move_uploaded_file($src, $dest)) {
            api_error('Error al guardar foto', 500);
        }
    }

    $url = str_replace('\\', '/', "uploads/cuidados/{$instId}/{$name}");
    api_ok(['url' => $url], 'Foto subida');
}

// ─ Upload vital sign photos ──────────────────────────────────────────────────
if ($method === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'upload_vital_photos') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
    $instId = api_inst_id();
    $residenteId = (int) ($_POST['residente_id'] ?? 0);
    $registroId  = (int) ($_POST['registro_id'] ?? 0);
    if (!$residenteId || !$registroId) api_error('residente_id y registro_id requeridos', 422);
    api_assert_residente($residenteId);

    $dir = dirname(__DIR__) . "/uploads/cuidados/{$instId}/vitales";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fotos = [];
    foreach ($_FILES as $key => $file) {
        if (!str_starts_with($key, 'foto_')) continue;
        if ($file['error'] !== UPLOAD_ERR_OK) continue;
        if ($file['size'] > 5 * 1024 * 1024) continue;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
        api_validate_mime($file['tmp_name'], ['image/jpeg','image/png','image/webp']);

        $vitalName = substr($key, 5); // e.g. "temperatura", "pa_sistolica"
        $fname = uniqid("sv_{$vitalName}_") . '.jpg';
        $dest = $dir . '/' . $fname;

        // Compress
        $src = $file['tmp_name'];
        $compressed = false;
        $info = @getimagesize($src);
        if ($info) {
            $img = match($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($src), IMAGETYPE_PNG => @imagecreatefrompng($src), IMAGETYPE_WEBP => @imagecreatefromwebp($src), default => false };
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                if ($w > 1200) { $nh = intval($h * 1200 / $w); $resized = imagecreatetruecolor(1200, $nh); imagecopyresampled($resized, $img, 0,0,0,0, 1200, $nh, $w, $h); imagedestroy($img); $img = $resized; }
                if (imagejpeg($img, $dest, 80)) $compressed = true;
                imagedestroy($img);
            }
        }
        if (!$compressed) move_uploaded_file($src, $dest);

        $fotos[$vitalName] = str_replace('\\', '/', "uploads/cuidados/{$instId}/vitales/{$fname}");
    }

    if ($fotos) {
        // Update the record's datos to include photo URLs
        $registro = Cuidado::getById($registroId, $instId);
        if ($registro) {
            $datos = is_string($registro['datos']) ? json_decode($registro['datos'], true) : ($registro['datos'] ?? []);
            if (!is_array($datos)) $datos = [];
            $datos['fotos_vitales'] = array_merge($datos['fotos_vitales'] ?? [], $fotos);
            Cuidado::update($registroId, $instId, ['datos' => $datos, 'hora' => $registro['hora'], 'observaciones' => $registro['observaciones'] ?? '']);
        }
    }

    api_ok(['fotos' => $fotos], 'Fotos subidas');
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — crear registro (JSON body)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

    $instId = api_inst_id();
    $body = api_body();
    $action = $body['action'] ?? 'crear';

    if ($action === 'crear') {
        $errors = [];
        if (empty($body['residente_id']))  $errors['residente_id']  = 'Requerido';
        if (empty($body['categoria']))     $errors['categoria']     = 'Requerido';
        if (empty($body['fecha']))         $errors['fecha']         = 'Requerido';
        if (empty($body['hora']))          $errors['hora']          = 'Requerido';
        if (!empty($errors)) api_error('Datos inválidos', 422, $errors);

        $categoria = $body['categoria'];
        if (!in_array($categoria, Cuidado::CATEGORIAS, true)) {
            api_error('Categoría inválida', 422);
        }

        $residenteId = (int) $body['residente_id'];
        api_assert_residente($residenteId);

        $id = Cuidado::create([
            'residente_id'  => $residenteId,
            'institucion_id'=> api_inst_id(),
            'usuario_id'    => api_user_id(),
            'categoria'     => $categoria,
            'datos'         => $body['datos'] ?? null,
            'observaciones' => trim($body['observaciones'] ?? ''),
            'fecha'         => $body['fecha'],
            'hora'          => $body['hora'],
            'verificacion_biometrica' => !empty($body['verificacion_biometrica']) ? 1 : 0,
        ]);

        if (!$id) api_error('Error al crear registro', 500);

        $registro = Cuidado::getById($id, api_inst_id());

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'cuidado_crear',
            'modulo'         => 'cuidados',
            'detalle'        => _buildLogDetalle('crear', $residenteId, $categoria, $body['datos'] ?? [], $id, [
                'fecha'         => $body['fecha'] ?? '',
                'hora'          => $body['hora'] ?? '',
                'observaciones' => trim($body['observaciones'] ?? ''),
            ]),
        ]);

        // Event-driven notifications (signos, incidentes, medicación, alimentación, ánimo)
        _triggerCareNotification(api_inst_id(), $residenteId, $categoria, $body['datos'] ?? [], trim($body['observaciones'] ?? ''));

        // Alerta al médico (toggle del cuidador)
        if (!empty($body['notificar_medico'])) {
            try {
                $db = Database::getTenant(api_inst_id());
                $st = $db->prepare("INSERT INTO alertas_medico (institucion_id, residente_id, registro_id, categoria, mensaje, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
                $st->execute([api_inst_id(), $residenteId, $id, $categoria, trim($body['mensaje_medico'] ?? '') ?: null, api_user_id()]);
            } catch (\Throwable $e) { /* table may not exist yet */ }
        }

        api_ok($registro, 'Registro creado');
    }

    // ── Notas de turno ───────────────────────────────────────────────────
    if ($action === 'crear_nota') {
        if (empty($body['residente_id'])) api_error('residente_id requerido', 422);
        if (empty($body['nota']))         api_error('nota requerida', 422);

        $residenteId = (int) $body['residente_id'];
        api_assert_residente($residenteId);

        $id = Cuidado::createNota([
            'institucion_id' => api_inst_id(),
            'residente_id'   => $residenteId,
            'usuario_id'     => api_user_id(),
            'nota'           => trim($body['nota']),
            'prioridad'      => $body['prioridad'] ?? 'normal',
            'fecha'          => $body['fecha'] ?? date('Y-m-d'),
            'imagen'         => $body['imagen'] ?? null,
        ]);

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_turno_crear',
            'modulo'         => 'cuidados',
            'detalle'        => json_encode([
                'nota_id'      => $id,
                'residente_id' => $residenteId,
                'prioridad'    => $body['prioridad'] ?? 'normal',
            ]),
        ]);

        api_ok(['id' => $id], 'Nota creada');
    }

    // ── Actualizar nota (solo el autor) ──────────────────────────────────
    if ($action === 'actualizar_nota') {
        if (empty($body['nota_id'])) api_error('nota_id requerido', 422);
        if (empty($body['nota']))    api_error('nota requerida', 422);

        $notaId = (int) $body['nota_id'];
        $ok = Cuidado::updateNota($notaId, api_inst_id(), api_user_id(), trim($body['nota']));
        if (!$ok) api_error('No se pudo actualizar (solo el autor puede editar)', 403);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_turno_actualizar',
            'modulo'         => 'cuidados',
            'estado'         => 'warn',
            'detalle'        => json_encode(['nota_id' => $notaId]),
        ]);
        api_ok(null, 'Nota actualizada');
    }

    // ── Notas de Médico: crear ──────────────────────────────────────────
    if ($action === 'crear_nota_medico') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['residente_id'])) api_error('residente_id requerido', 422);
        if (empty($body['contenido']))    api_error('contenido requerido', 422);

        $residenteId = (int) $body['residente_id'];
        api_assert_residente($residenteId);

        $contenido = _nmFiltrarSignosVitalesActivos(trim((string)$body['contenido']), $body['signos_vitales_activos'] ?? null);
        $adjuntos = isset($body['adjuntos']) && is_array($body['adjuntos']) ? $body['adjuntos'] : null;
        $recetas  = isset($body['recetas'])  && is_array($body['recetas'])  ? $body['recetas']  : null;

        // Hora opcional del médico (datetime-local YYYY-MM-DDTHH:MM o MySQL YYYY-MM-DD HH:MM:SS)
        $creadoAt = null;
        if (!empty($body['creado_at'])) {
            $raw = str_replace('T', ' ', trim((string) $body['creado_at']));
            $ts  = strtotime($raw);
            if ($ts === false) api_error('creado_at inválido', 422);
            $creadoAt = date('Y-m-d H:i:s', $ts);
        }

        $id = Cuidado::createNotaMedico([
            'institucion_id' => api_inst_id(),
            'residente_id'   => $residenteId,
            'usuario_id'     => api_user_id(),
            'contenido'      => $contenido,
            'adjuntos'       => $adjuntos,
            'recetas'        => $recetas,
            'creado_at'      => $creadoAt,
        ]);

        // Crear registro en expediente si se solicita
        if (!empty($body['crear_expediente'])) {
            _nmCrearExpedienteDoc(api_inst_id(), $residenteId, api_user_id(), $contenido, $adjuntos, $recetas);
        }

        $nota = Cuidado::getNotaMedicoVigente(api_inst_id(), $residenteId);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_medica_crear',
            'modulo'         => 'cuidados',
            'detalle'        => json_encode([
                'nota_id'           => $id,
                'residente_id'      => $residenteId,
                'recetas'           => is_array($recetas) ? count($recetas) : 0,
                'adjuntos'          => is_array($adjuntos) ? count($adjuntos) : 0,
                'crear_expediente'  => !empty($body['crear_expediente']),
            ]),
        ]);
        api_ok($nota, 'Nota médica creada');
    }

    // ── Notas de Médico: actualizar ─────────────────────────────────────
    if ($action === 'actualizar_nota_medico') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['nota_id']))   api_error('nota_id requerido', 422);
        if (empty($body['contenido'])) api_error('contenido requerido', 422);

        $contenido = _nmFiltrarSignosVitalesActivos(trim((string)$body['contenido']), $body['signos_vitales_activos'] ?? null);
        $adjuntos = isset($body['adjuntos']) && is_array($body['adjuntos']) ? $body['adjuntos'] : null;
        $recetas  = isset($body['recetas'])  && is_array($body['recetas'])  ? $body['recetas']  : null;
        $creadoAt = null;
        if (!empty($body['creado_at'])) {
            $raw = str_replace('T', ' ', trim((string) $body['creado_at']));
            $ts  = strtotime($raw);
            if ($ts === false) api_error('creado_at inválido', 422);
            $creadoAt = date('Y-m-d H:i:s', $ts);
        }
        $isSuperadmin = api_role_storage($_SESSION['user_rol'] ?? '') === 'superadmin';
        $ok = Cuidado::updateNotaMedico((int) $body['nota_id'], api_inst_id(), $isSuperadmin ? null : api_user_id(), $contenido, $adjuntos, $recetas, $creadoAt);
        if (!$ok) api_error('No se pudo actualizar (solo el médico autor o superadmin puede editar la nota)', 403);

        // Actualizar documento de expediente si la edición viene desde el drawer.
        if (!empty($body['expediente_doc_id']) && !empty($body['residente_id'])) {
            $allFiles = [];
            if (is_array($adjuntos) && count($adjuntos)) $allFiles = array_merge($allFiles, $adjuntos);
            if (is_array($recetas) && count($recetas)) $allFiles = array_merge($allFiles, $recetas);
            ExpedienteDoc::update((int)$body['expediente_doc_id'], [
                'institucion_id'  => api_inst_id(),
                'residente_id'    => (int)$body['residente_id'],
                'tipo'            => (is_array($recetas) && count($recetas)) ? 'receta' : 'nota_medico',
                'descripcion'     => $contenido,
                'fecha_documento' => $creadoAt ? substr($creadoAt, 0, 10) : null,
                'archivos_json'   => count($allFiles) ? json_encode($allFiles, JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
        // Actualizar/recrear registro en expediente si se solicita
        elseif (!empty($body['crear_expediente']) && !empty($body['residente_id'])) {
            _nmCrearExpedienteDoc(api_inst_id(), (int) $body['residente_id'], api_user_id(), $contenido, $adjuntos, $recetas);
        }

        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_medica_actualizar',
            'modulo'         => 'cuidados',
            'estado'         => 'warn',
            'detalle'        => json_encode([
                'nota_id'      => (int) $body['nota_id'],
                'residente_id' => isset($body['residente_id']) ? (int) $body['residente_id'] : null,
            ]),
        ]);

        api_ok(null, 'Nota médica actualizada');
    }

    // ── Notas de Médico: archivar ───────────────────────────────────────
    if ($action === 'archivar_nota_medico') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['nota_id'])) api_error('nota_id requerido', 422);

        $ok = Cuidado::archivarNotaMedico((int) $body['nota_id'], api_inst_id(), api_user_id());
        if (!$ok) api_error('No se pudo archivar', 400);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_medica_archivar',
            'modulo'         => 'cuidados',
            'estado'         => 'warn',
            'detalle'        => json_encode(['nota_id' => (int) $body['nota_id']]),
        ]);
        api_ok(null, 'Nota médica archivada');
    }

    // ── Notas de Médico: hacer vigente ─────────────────────────────────
    if ($action === 'hacer_vigente_nota_medico') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['nota_id'])) api_error('nota_id requerido', 422);
        $isSuperadmin = api_role_storage($_SESSION['user_rol'] ?? '') === 'superadmin';
        $ok = Cuidado::hacerVigenteNotaMedico((int) $body['nota_id'], api_inst_id(), $isSuperadmin ? null : api_user_id(), api_user_id());
        if (!$ok) api_error('No se pudo marcar como vigente', 403);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_medica_hacer_vigente',
            'modulo'         => 'cuidados',
            'estado'         => 'warn',
            'detalle'        => json_encode(['nota_id' => (int) $body['nota_id']]),
        ]);
        api_ok(null, 'Nota médica marcada como vigente');
    }

    // ── Notas de Médico: eliminar ───────────────────────────────────────
    if ($action === 'eliminar_nota_medico') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['nota_id'])) api_error('nota_id requerido', 422);
        $isSuperadmin = api_role_storage($_SESSION['user_rol'] ?? '') === 'superadmin';
        $ok = Cuidado::deleteNotaMedico((int) $body['nota_id'], api_inst_id(), $isSuperadmin ? null : api_user_id());
        if (!$ok) api_error('No se pudo eliminar', 400);
        Log::registrar([
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'accion'         => 'nota_medica_eliminar',
            'modulo'         => 'cuidados',
            'estado'         => 'error',
            'detalle'        => json_encode(['nota_id' => (int) $body['nota_id']]),
        ]);
        api_ok(null, 'Nota médica eliminada (incluyendo expediente)');
    }

    // ── Alertas al Médico: crear ─────────────────────────────────────────
    if ($action === 'crear_alerta_medico') {
        if (empty($body['residente_id'])) api_error('residente_id requerido', 422);
        if (empty($body['registro_id']))  api_error('registro_id requerido', 422);
        if (empty($body['categoria']))    api_error('categoria requerida', 422);

        $residenteId = (int) $body['residente_id'];
        api_assert_residente($residenteId);

        $db = Database::getTenant(api_inst_id());
        $st = $db->prepare("INSERT INTO alertas_medico (institucion_id, residente_id, registro_id, categoria, mensaje, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([
            api_inst_id(),
            $residenteId,
            (int) $body['registro_id'],
            $body['categoria'],
            trim($body['mensaje'] ?? '') ?: null,
            api_user_id(),
        ]);
        api_ok(['id' => (int) $db->lastInsertId()], 'Alerta creada');
    }

    // ── Alertas al Médico: enterado ──────────────────────────────────────
    if ($action === 'enterado_alerta') {
        api_auth_roles(['medico', 'superadmin']);
        if (empty($body['alerta_id'])) api_error('alerta_id requerido', 422);

        $db = Database::getTenant(api_inst_id());
        $st = $db->prepare("UPDATE alertas_medico SET visto_por = ?, visto_at = NOW() WHERE id = ? AND institucion_id = ? AND visto_por IS NULL");
        $st->execute([api_user_id(), (int) $body['alerta_id'], api_inst_id()]);
        if ($st->rowCount() === 0) api_error('Alerta no encontrada o ya vista', 404);
        api_ok(null, 'Enterado registrado');
    }

    // ── Inventario: crear item ───────────────────────────────────────────
    if ($action === 'crear_inventario') {
        if (empty($body['nombre'])) api_error('nombre requerido', 422);

        $id = Inventario::create([
            'institucion_id' => api_inst_id(),
            'residente_id'   => !empty($body['residente_id']) ? (int) $body['residente_id'] : null,
            'nombre'         => trim($body['nombre']),
            'tipo'           => $body['tipo'] ?? 'medicamento',
            'unidad'         => $body['unidad'] ?? 'unidades',
            'stock_actual'   => (int) ($body['stock_actual'] ?? 0),
            'stock_minimo'   => (int) ($body['stock_minimo'] ?? 10),
            'vencimiento'    => $body['vencimiento'] ?? null,
            'notas'          => $body['notas'] ?? null,
        ]);

        api_ok(Inventario::getById($id, api_inst_id()), 'Item creado');
    }

    // ── Inventario: registrar movimiento ─────────────────────────────────
    if ($action === 'movimiento_inventario') {
        if (empty($body['item_id']))  api_error('item_id requerido', 422);
        if (empty($body['tipo']))     api_error('tipo requerido', 422);
        if (!isset($body['cantidad'])) api_error('cantidad requerida', 422);

        $item = Inventario::getById((int) $body['item_id'], api_inst_id());
        if (!$item) api_error('Item no encontrado', 404);

        $movId = Inventario::registrarMovimiento([
            'item_id'        => (int) $body['item_id'],
            'institucion_id' => api_inst_id(),
            'tipo'           => $body['tipo'],
            'cantidad'       => (int) $body['cantidad'],
            'residente_id'   => $body['residente_id'] ?? null,
            'usuario_id'     => api_user_id(),
            'motivo'         => $body['motivo'] ?? null,
        ]);

        api_ok(['movimiento_id' => $movId, 'item' => Inventario::getById((int) $body['item_id'], api_inst_id())], 'Movimiento registrado');
    }

    // ── Inventario: actualizar movimiento ────────────────────────────────
    if ($action === 'actualizar_movimiento') {
        if (empty($body['id']))       api_error('id requerido', 422);
        if (empty($body['tipo']))     api_error('tipo requerido', 422);
        if (!isset($body['cantidad'])) api_error('cantidad requerida', 422);

        $mov = Inventario::getMovimientoById((int) $body['id'], api_inst_id());
        if (!$mov) api_error('Movimiento no encontrado', 404);
        if (!empty($body['residente_id'])) {
            $requestedResidentId = (int)$body['residente_id'];
            $movResidentId = !empty($mov['residente_id']) ? (int)$mov['residente_id'] : null;
            $itemResidentId = !empty($mov['item_residente_id']) ? (int)$mov['item_residente_id'] : null;
            if ($movResidentId !== $requestedResidentId && !($movResidentId === null && $itemResidentId === $requestedResidentId)) {
                api_error('Movimiento no pertenece al residente seleccionado', 403);
            }
        }

        Inventario::updateMovimiento((int) $body['id'], api_inst_id(), [
            'tipo'     => $body['tipo'],
            'cantidad' => (int) $body['cantidad'],
            'motivo'   => $body['motivo'] ?? null,
        ]);

        api_ok(null, 'Movimiento actualizado');
    }

    // ── Inventario: eliminar movimiento ──────────────────────────────────
    if ($action === 'eliminar_movimiento') {
        if (empty($body['id'])) api_error('id requerido', 422);

        $mov = Inventario::getMovimientoById((int) $body['id'], api_inst_id());
        if (!$mov) api_error('Movimiento no encontrado', 404);
        if (!empty($body['residente_id'])) {
            $requestedResidentId = (int)$body['residente_id'];
            $movResidentId = !empty($mov['residente_id']) ? (int)$mov['residente_id'] : null;
            $itemResidentId = !empty($mov['item_residente_id']) ? (int)$mov['item_residente_id'] : null;
            if ($movResidentId !== $requestedResidentId && !($movResidentId === null && $itemResidentId === $requestedResidentId)) {
                api_error('Movimiento no pertenece al residente seleccionado', 403);
            }
        }

        Inventario::deleteMovimiento((int) $body['id'], api_inst_id());

        api_ok(null, 'Movimiento eliminado');
    }

    // ── Inventario: actualizar item ──────────────────────────────────────
    if ($action === 'actualizar_inventario') {
        if (empty($body['id']))     api_error('id requerido', 422);
        if (empty($body['nombre'])) api_error('nombre requerido', 422);

        $item = Inventario::getById((int) $body['id'], api_inst_id());
        if (!$item) api_error('Item no encontrado', 404);

        Inventario::update((int) $body['id'], api_inst_id(), [
            'nombre'       => trim($body['nombre']),
            'tipo'         => $body['tipo'] ?? $item['tipo'],
            'unidad'       => $body['unidad'] ?? $item['unidad'],
            'stock_minimo' => (int) ($body['stock_minimo'] ?? $item['stock_minimo']),
            'vencimiento'  => $body['vencimiento'] ?? $item['vencimiento'],
            'notas'        => $body['notas'] ?? $item['notas'],
        ]);

        api_ok(Inventario::getById((int) $body['id'], api_inst_id()), 'Item actualizado');
    }

    // ── Inventario: eliminar item ────────────────────────────────────────
    if ($action === 'eliminar_inventario') {
        if (empty($body['id'])) api_error('id requerido', 422);
        Inventario::softDelete((int) $body['id'], api_inst_id());
        api_ok(null, 'Item eliminado');
    }

    // ── Send notification via WhatsApp ───────────────────────────────────
    if ($action === 'send_notif_wa') {
        $phone   = trim($body['phone'] ?? '');
        $message = trim($body['message'] ?? '');
        $residenteId = !empty($body['residente_id']) ? (int) $body['residente_id'] : null;
        $tipo    = trim($body['tipo'] ?? 'manual');
        if (!$phone || !$message) api_error('phone y message requeridos', 422);

        $db = Database::getTenant(api_inst_id());
        // Ensure table exists
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

        $estado = 'enviado';
        $errorDetalle = null;
        try {
            $wa = WaSenderAPI::fromConfig(api_inst_id());
            // sendText() NO lanza excepción ante fallo de API; retorna
            // ['ok'=>bool, 'message_id'=>?string, 'error'=>?string].
            // Antes asumíamos throw → siempre se registraba como 'enviado'.
            $result = $wa->sendText($phone, $message);
            if (empty($result['ok'])) {
                $estado = 'error';
                $rawErr = (string)($result['error'] ?? 'Error desconocido de WhatsApp API');
                // Traducir errores comunes a mensajes amigables.
                if (stripos($rawErr, 'JID does not exist') !== false) {
                    $errorDetalle = "El número {$phone} no tiene cuenta de WhatsApp activa.";
                } elseif (stripos($rawErr, 'unauthorized') !== false || stripos($rawErr, '401') !== false) {
                    $errorDetalle = 'Token de WhatsApp inválido o sesión desconectada.';
                } elseif (stripos($rawErr, 'Daily request limit') !== false || stripos($rawErr, '429') !== false) {
                    $errorDetalle = 'Cuota diaria de WhatsApp agotada. Intenta más tarde.';
                } else {
                    $errorDetalle = $rawErr;
                }
            }
        } catch (\Throwable $e) {
            $estado = 'error';
            $errorDetalle = $e->getMessage();
        }

        $encMessage = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $message])['mensaje'] ?? $message;
        $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'whatsapp', ?, ?, ?, ?)");
        $st->execute([api_inst_id(), $residenteId, $phone, $tipo, $encMessage, $estado, $errorDetalle]);
        EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $message]);

        if ($estado === 'error') api_error('Error: ' . $errorDetalle, 500);
        api_ok(null, 'WhatsApp enviado');
    }

    // ── Send notification via Email ──────────────────────────────────────
    if ($action === 'send_notif_email') {
        $recipients = $body['recipients'] ?? [];
        $subject    = trim($body['subject'] ?? 'Notificación');
        $message    = trim($body['message'] ?? '');
        $residenteId = !empty($body['residente_id']) ? (int) $body['residente_id'] : null;
        $tipo       = trim($body['tipo'] ?? 'manual');
        if (!$recipients || !$message) api_error('recipients y message requeridos', 422);

        // Build styled HTML email
        $cfg = Configuracion::getOrCreate(api_inst_id());
        $instName = htmlspecialchars($cfg['inst_nombre'] ?? 'GeriApp', ENT_QUOTES, 'UTF-8');
        $appUrl   = htmlspecialchars($cfg['app_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $year     = date('Y');
        $messageHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        // Determine accent color based on notification type
        $accentColors = [
            'stock_bajo'     => '#e67e22',
            'stock_panales'  => '#e67e22',
            'signos_vitales' => '#e74c3c',
            'incidente'      => '#e74c3c',
            'reporte_dia'    => '#1e3a6e',
            'medicacion'     => '#27ae60',
            'alimentacion'   => '#27ae60',
            'animo'          => '#8e44ad',
            'personalizado'  => '#1e3a6e',
        ];
        $accent = $accentColors[$tipo] ?? '#1e3a6e';

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
  <!-- Header accent bar -->
  <tr><td style="height:5px;background:{$accent};"></td></tr>
  <!-- Logo / Institution -->
  <tr><td style="padding:28px 32px 0 32px;">
    <div style="font-size:13px;font-weight:600;color:{$accent};text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{$instName}</div>
    <div style="font-size:20px;font-weight:700;color:#1e293b;line-height:1.3;">{$subject}</div>
  </td></tr>
  <!-- Divider -->
  <tr><td style="padding:16px 32px 0 32px;"><div style="border-top:1px solid #e2e8f0;"></div></td></tr>
  <!-- Body -->
  <tr><td style="padding:20px 32px 28px 32px;">
    <div style="font-size:14px;line-height:1.7;color:#334155;">{$messageHtml}</div>
  </td></tr>
  <!-- Footer -->
  <tr><td style="padding:16px 32px 24px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;">
    <div style="font-size:11px;color:#94a3b8;line-height:1.5;">
      {$instName} &middot; GeriApp<br>
      &copy; {$year}
    </div>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

        $db = Database::getTenant(api_inst_id());
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

        $mailer = Mailer::fromConfig(api_inst_id());
        foreach ($recipients as $r) {
            $email = is_array($r) ? ($r['email'] ?? '') : $r;
            $name  = is_array($r) ? ($r['name'] ?? '') : '';
            if (!$email) continue;
            $estado = 'enviado';
            $errorDetalle = null;
            try {
                $mailer->send($email, $subject, $htmlBody, $message);
            } catch (\Throwable $e) {
                $estado = 'error';
                $errorDetalle = $e->getMessage();
            }
            $encMessage = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $message])['mensaje'] ?? $message;
            $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'email', ?, ?, ?, ?)");
            $st->execute([api_inst_id(), $residenteId, $email, $tipo, $encMessage, $estado, $errorDetalle]);
            EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $message]);
        }
        api_ok(null, 'Emails procesados');
    }

    // ── Log report generation ───────────────────────────────────────────
    if ($action === 'log_reporte') {
        $tipo      = trim($body['tipo'] ?? '');
        $residente = trim($body['residente'] ?? '');
        $periodo   = trim($body['periodo'] ?? '');
        Log::registrar([
            'accion'         => 'reporte_generar',
            'modulo'         => 'cuidados',
            'usuario_id'     => api_user_id(),
            'institucion_id' => api_inst_id(),
            'detalle'        => "Tipo: {$tipo} | Residente: {$residente} | Periodo: {$periodo}",
        ]);
        api_ok(null, 'Actividad registrada');
    }

    api_error('Acción no reconocida', 400);
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — update registro (author-only edit)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

    $body = api_body();
    $id = (int) ($body['id'] ?? 0);
    if (!$id) api_error('id requerido', 400);

    $registro = Cuidado::getById($id, api_inst_id());
    if (!$registro) api_error('Registro no encontrado', 404);

    $rol = api_rol();
    if (!in_array($rol, ['superadmin', 'admin'], true) && (int) $registro['usuario_id'] !== api_user_id()) {
        api_error('Solo el autor puede editar este registro', 403);
    }

    // Preserve photo keys when editing (they are NOT sent from the form)
    if (isset($body['datos']) && is_array($body['datos'])) {
        $oldDatos = is_string($registro['datos']) ? json_decode($registro['datos'], true) : ($registro['datos'] ?? []);
        if (is_array($oldDatos)) {
            foreach (['fotos_vitales', 'foto_antes', 'foto_despues'] as $_pk) {
                if (!empty($oldDatos[$_pk]) && !isset($body['datos'][$_pk])) {
                    $body['datos'][$_pk] = $oldDatos[$_pk];
                }
            }
        }
    }

    Cuidado::update($id, api_inst_id(), [
        'datos'         => $body['datos'] ?? $registro['datos'],
        'observaciones' => trim($body['observaciones'] ?? $registro['observaciones'] ?? ''),
        'hora'          => !empty($body['hora']) ? $body['hora'] : $registro['hora'],
    ]);

    $updated = Cuidado::getById($id, api_inst_id());

    // Handle alert toggle on edit
    if (isset($body['notificar_medico'])) {
        try {
            $db = Database::getTenant(api_inst_id());
            $residenteId = (int) $registro['residente_id'];
            if (!empty($body['notificar_medico'])) {
                // Upsert: check if already exists for this registro
                $existing = $db->prepare("SELECT id FROM alertas_medico WHERE registro_id = ? AND institucion_id = ?");
                $existing->execute([$id, api_inst_id()]);
                if ($existing->fetch()) {
                    $db->prepare("UPDATE alertas_medico SET mensaje = ?, visto_por = NULL, visto_at = NULL WHERE registro_id = ? AND institucion_id = ?")->execute([trim($body['mensaje_medico'] ?? '') ?: null, $id, api_inst_id()]);
                } else {
                    $db->prepare("INSERT INTO alertas_medico (institucion_id, residente_id, registro_id, categoria, mensaje, creado_por) VALUES (?, ?, ?, ?, ?, ?)")->execute([api_inst_id(), $residenteId, $id, $registro['categoria'], trim($body['mensaje_medico'] ?? '') ?: null, api_user_id()]);
                }
            } else {
                // Remove pending alert if toggle turned off
                $db->prepare("DELETE FROM alertas_medico WHERE registro_id = ? AND institucion_id = ? AND visto_por IS NULL")->execute([$id, api_inst_id()]);
            }
        } catch (\Throwable $e) { /* table may not exist */ }
    }

    $updatedDatos = is_string($updated['datos'] ?? null) ? json_decode($updated['datos'], true) : ($updated['datos'] ?? []);
    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'cuidado_editar',
        'modulo'         => 'cuidados',
        'detalle'        => _buildLogDetalle('editar', (int)$registro['residente_id'], $registro['categoria'] ?? '', $updatedDatos ?: [], $id, [
            'fecha'         => $updated['fecha'] ?? '',
            'hora'          => $updated['hora'] ?? '',
            'observaciones' => trim($updated['observaciones'] ?? ''),
        ]),
    ]);

    api_ok($updated, 'Registro actualizado');
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);

    // Delete notification log entry
    if (!empty($_GET['notif_log_id'])) {
        $logId = (int) $_GET['notif_log_id'];
        $db = Database::getTenant(api_inst_id());
        $del = $db->prepare("DELETE FROM notificaciones_log WHERE id = ? AND institucion_id = ?");
        $del->execute([$logId, api_inst_id()]);
        api_ok(null, 'Registro eliminado');
    }

    // Delete nota (owner or admin only)
    if (!empty($_GET['nota_id'])) {
        $notaId = (int) $_GET['nota_id'];
        $instId = api_inst_id();
        $db = Database::getTenant($instId);
        $chk = $db->prepare("SELECT usuario_id FROM cuidados_notas WHERE id = ? AND institucion_id = ?");
        $chk->execute([$notaId, $instId]);
        $nota = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$nota) api_error('Nota no encontrada', 404);
        $rol = api_rol();
        if (!in_array($rol, ['superadmin', 'admin'], true) && (int) $nota['usuario_id'] !== api_user_id()) {
            api_error('Solo el autor o un administrador puede eliminar esta nota', 403);
        }
        $ok = Cuidado::deleteNota($notaId, $instId);
        if (!$ok) api_error('Error al eliminar nota', 500);
        api_ok(null, 'Nota eliminada');
    }

    $id = api_int('id');
    if (!$id) api_error('id requerido', 400);

    $registro = Cuidado::getById($id, api_inst_id());
    if (!$registro) api_error('Registro no encontrado', 404);

    // Solo el creador, admin o superadmin pueden eliminar
    $rol = api_rol();
    if (!in_array($rol, ['superadmin', 'admin'], true) && (int) $registro['usuario_id'] !== api_user_id()) {
        api_error('No autorizado para eliminar este registro', 403);
    }

    // Restore inventory stock if the record had inventory deductions
    $datos = $registro['datos'] ?? null;
    if (is_string($datos)) {
        $datos = json_decode($datos, true);
    }
    if (is_array($datos)) {
        $instId = api_inst_id();
        $invItems = $datos['inventario_admin'] ?? [];
        foreach ($invItems as $inv) {
            if (!empty($inv['id']) && !empty($inv['qty'])) {
                try {
                    Inventario::registrarMovimiento([
                        'item_id'        => (int) $inv['id'],
                        'institucion_id' => $instId,
                        'tipo'           => 'entrada',
                        'cantidad'       => (int) $inv['qty'],
                        'residente_id'   => $registro['residente_id'] ?? null,
                        'usuario_id'     => api_user_id(),
                        'motivo'         => 'Reposición – registro eliminado',
                    ]);
                } catch (\Throwable $e) { /* skip if item was already deleted */ }
            }
        }
    }

    $ok = Cuidado::delete($id, api_inst_id());
    if (!$ok) api_error('Error al eliminar', 500);

    $delDatos = is_string($registro['datos'] ?? null) ? json_decode($registro['datos'], true) : ($registro['datos'] ?? []);
    Log::registrar([
        'usuario_id'     => api_user_id(),
        'institucion_id' => api_inst_id(),
        'accion'         => 'cuidado_eliminar',
        'modulo'         => 'cuidados',
        'detalle'        => _buildLogDetalle('eliminar', (int)$registro['residente_id'], $registro['categoria'] ?? '', $delDatos ?: [], $id, [
            'fecha'         => $registro['fecha'] ?? '',
            'hora'          => $registro['hora'] ?? '',
            'observaciones' => trim($registro['observaciones'] ?? ''),
        ]),
    ]);

    api_ok(null, 'Registro eliminado');
}

api_error('Método no permitido', 405);

// ─────────────────────────────────────────────────────────────────────────────
// Event-driven care notifications
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Trigger event-driven notifications to family contacts when a care record is created.
 *
 * Category → preference mapping:
 *   signos_vitales  → notif_signos     (only when values are out of range)
 *   comportamiento  → notif_incidentes (when incidentes !== 'Ninguno')
 *                   → notif_animo      (always)
 *   medicacion      → notif_medicacion
 *   alimentacion    → notif_alimentacion
 *
 * Deduplication: max 1 notification per type per contact per 4 hours.
 */
function _triggerCareNotification(int $instId, int $residenteId, string $categoria, mixed $datos, string $observaciones): void
{
    // Map category → [notif preference key, notification tipo, subject]
    $catMap = [
        'signos_vitales' => ['notif_signos',        'signos_vitales', 'Alerta: Signos vitales fuera de rango'],
        'medicacion'     => ['notif_medicacion',     'medicacion',     'Notificación: Administración de medicamentos'],
        'alimentacion'   => ['notif_alimentacion',   'alimentacion',   'Notificación: Cambio en alimentación'],
        'comportamiento' => ['notif_animo',          'animo',          'Notificación: Cambio de ánimo / conducta'],
        'higiene'        => ['notif_higiene',        'higiene',        'Notificación: Aseo personal'],
        'eliminacion'    => ['notif_eliminacion',    'eliminacion',    'Notificación: Registro de eliminación'],
        'sueno'          => ['notif_sueno',          'sueno',          'Notificación: Calidad del sueño'],
        'movilidad'      => ['notif_movilidad',      'movilidad',      'Notificación: Actividad / movilidad'],
        'terapia'        => ['notif_terapia',        'terapia',        'Notificación: Sesión de terapia'],
        'incidente'      => ['notif_incidentes',     'incidente',      'ALERTA CRÍTICA: Incidente / Caída'],
    ];

    if (!isset($catMap[$categoria])) return;

    // Parse datos if it's a JSON string
    if (is_string($datos)) {
        $datos = json_decode($datos, true) ?: [];
    }
    if (!is_array($datos)) $datos = [];

    // For signos_vitales, only notify if values are abnormal
    if ($categoria === 'signos_vitales') {
        if (!_vitalsAreAbnormal($datos)) return;
    }

    // Check if this is an incident (comportamiento with incidentes !== 'Ninguno')
    $isIncident = false;
    if ($categoria === 'comportamiento') {
        $incidenteVal = $datos['incidentes'] ?? '';
        $isIncident = !empty($incidenteVal) && $incidenteVal !== 'Ninguno';
    }

    try {
        $db = Database::getTenant($instId);

        // Get resident info
        $stRes = $db->prepare("SELECT nombre, apellidos, contactos_json, contacto_nombre, contacto_telefono, contacto_email FROM residentes WHERE id = ? AND institucion_id = ?");
        $stRes->execute([$residenteId, $instId]);
        $res = $stRes->fetch(PDO::FETCH_ASSOC);
        if (!$res) return;
        // Decrypt residente PII before parsing contacts. Without this, when
        // residentes.contactos_json / contacto_telefono / contacto_email are
        // in 'consolidated' phase, the values stay as ciphertext and end up
        // being persisted into notificaciones_log.destinatario as ciphertext.
        $res = EncryptionMap::decryptRow('residentes', $res);

        $resName = trim(($res['nombre'] ?? '') . ' ' . ($res['apellidos'] ?? ''));

        // Parse contacts (same logic as cron)
        $contacts = [];
        if (!empty($res['contactos_json'])) {
            $parsed = json_decode($res['contactos_json'], true);
            if (is_array($parsed) && count($parsed)) {
                if (isset($parsed[0]['notif_stock']) || isset($parsed[0]['notif_wa'])) {
                    $contacts = $parsed;
                } else {
                    if (!empty($res['contacto_nombre'])) {
                        $contacts[] = [
                            'nombre'   => $res['contacto_nombre'],
                            'telefono' => $res['contacto_telefono'] ?? '',
                            'email'    => $res['contacto_email'] ?? '',
                            'notif_wa' => 1, 'notif_email' => 0,
                        ];
                    }
                    $contacts = array_merge($contacts, $parsed);
                }
            }
        }
        if (empty($contacts) && !empty($res['contacto_nombre'])) {
            $contacts[] = [
                'nombre'   => $res['contacto_nombre'],
                'telefono' => $res['contacto_telefono'] ?? '',
                'email'    => $res['contacto_email'] ?? '',
                'notif_wa' => 1, 'notif_email' => 0,
            ];
        }

        if (empty($contacts)) return;

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

        // Get channels config
        $config = Configuracion::getOrCreate($instId);
        $instName = $config['inst_nombre'] ?? 'GeriApp';
        $waActivo = !empty($config['wa_activo']);
        $smtpHost = !empty($config['smtp_host']);

        $wa = null;
        $mailer = null;
        if ($waActivo) {
            try { $wa = WaSenderAPI::fromConfig($instId); } catch (Throwable $e) {}
        }
        if ($smtpHost) {
            try { $mailer = Mailer::fromConfig($instId); } catch (Throwable $e) {}
        }

        if (!$wa && !$mailer) return;

        [$prefKey, $tipo, $subject] = $catMap[$categoria];

        // Build notification targets: [prefKey => [tipo, subject, message]]
        $targets = [];

        // Primary notification
        $msg = _buildCareNotifMessage($categoria, $tipo, $resName, $datos, $observaciones);
        $targets[] = [$prefKey, $tipo, $subject, $msg];

        // If comportamiento is also an incident, add incident notification
        if ($isIncident) {
            $incidentMsg = _buildCareNotifMessage($categoria, 'incidente', $resName, $datos, $observaciones);
            $targets[] = ['notif_incidentes', 'incidente', 'Alerta: Incidente reportado', $incidentMsg];
        }

        foreach ($contacts as $c) {
            if (empty($c['nombre'])) continue;

            foreach ($targets as [$pKey, $nTipo, $nSubject, $nMsg]) {
                if (empty($c[$pKey])) continue;

                // Deduplication: max 1 per tipo per contact per 4 hours
                $dest = $c['telefono'] ?: ($c['email'] ?? '');
                if (!$dest) continue;

                $chk = $db->prepare("SELECT id FROM notificaciones_log WHERE institucion_id = ? AND residente_id = ? AND destinatario = ? AND tipo = ? AND fecha > DATE_SUB(NOW(), INTERVAL 4 HOUR) LIMIT 1");
                $chk->execute([$instId, $residenteId, $dest, $nTipo]);
                if ($chk->fetch()) continue;

                // Send via WA
                if (!empty($c['notif_wa']) && !empty($c['telefono']) && $wa) {
                    $estado = 'enviado';
                    $error = null;
                    try {
                        // sendText() retorna ['ok'=>bool, 'error'=>?string]; no lanza ante fallo de API.
                        $r = $wa->sendText($c['telefono'], $nMsg);
                        if (empty($r['ok'])) {
                            $estado = 'error';
                            $error = $r['error'] ?? 'Error desconocido de WhatsApp API';
                        }
                    } catch (Throwable $e) { $estado = 'error'; $error = $e->getMessage(); }
                    $encNMsg = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $nMsg])['mensaje'] ?? $nMsg;
                    $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'whatsapp', ?, ?, ?, ?)");
                    $st->execute([$instId, $residenteId, $c['telefono'], $nTipo, $encNMsg, $estado, $error]);
                    EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $nMsg]);
                }

                // Send via Email
                if (!empty($c['notif_email']) && !empty($c['email']) && $mailer) {
                    $estado = 'enviado';
                    $error = null;
                    $htmlBody = _buildCareEmailHtml($nSubject, $nMsg, $nTipo, $instName);
                    try {
                        $mailer->send($c['email'], $nSubject, $htmlBody, $nMsg);
                    } catch (Throwable $e) { $estado = 'error'; $error = $e->getMessage(); }
                    $encNMsg2 = EncryptionMap::encryptRow('notificaciones_log', ['mensaje' => $nMsg])['mensaje'] ?? $nMsg;
                    $st = $db->prepare("INSERT INTO notificaciones_log (institucion_id, residente_id, destinatario, canal, tipo, mensaje, estado, error_detalle) VALUES (?, ?, ?, 'email', ?, ?, ?, ?)");
                    $st->execute([$instId, $residenteId, $c['email'], $nTipo, $encNMsg2, $estado, $error]);
                    EncryptionMap::dualWriteEnc($db, 'notificaciones_log', (int)$db->lastInsertId(), ['mensaje' => $nMsg]);
                }
            }
        }
    } catch (Throwable $e) {
        // Don't let notification errors affect the main API response
        error_log("GeriApp event notif error: " . $e->getMessage());
    }
}

/**
 * Check if vital sign values are outside normal geriatric ranges.
 */
function _vitalsAreAbnormal(array $d): bool
{
    $t = isset($d['temperatura']) ? (float) $d['temperatura'] : 0;
    if ($t > 0 && ($t >= 37.8 || $t <= 35.5)) return true;

    $fc = isset($d['frecuencia_cardiaca']) ? (int) $d['frecuencia_cardiaca'] : 0;
    if ($fc > 0 && ($fc > 100 || $fc < 50)) return true;

    $fr = isset($d['frecuencia_respiratoria']) ? (int) $d['frecuencia_respiratoria'] : 0;
    if ($fr > 0 && ($fr > 25 || $fr < 12)) return true;

    $spo2 = isset($d['spo2']) ? (int) $d['spo2'] : 0;
    if ($spo2 > 0 && $spo2 < 92) return true;

    $pas = isset($d['pa_sistolica']) ? (int) $d['pa_sistolica'] : 0;
    if ($pas > 0 && ($pas > 160 || $pas < 90)) return true;

    $pad = isset($d['pa_diastolica']) ? (int) $d['pa_diastolica'] : 0;
    if ($pad > 0 && ($pad > 100 || $pad < 50)) return true;

    return false;
}

/**
 * Build a family-friendly notification message.
 *
 * Format conventions (WhatsApp-friendly):
 *   - Single emoji + *bold title* on first line
 *   - Resident name in bold
 *   - Bullet points for data, label in italic with _underscores_
 *   - Closing footer using > (quote block) with reassurance / call to action
 */
function _buildCareNotifMessage(string $categoria, string $tipo, string $resName, array $datos, string $obs): string
{
    // Helper: friendly time stamp
    $ts = date('d/M H:i');

    switch ($tipo) {
        case 'signos_vitales':
            $msg  = "🚨 *Alerta de signos vitales*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            $msg .= "Se detectaron valores fuera de rango:\n\n";
            $parts = [];
            if (!empty($datos['temperatura']))             $parts[] = "🌡️ *Temperatura:* {$datos['temperatura']} °C";
            if (!empty($datos['frecuencia_cardiaca']))      $parts[] = "❤️ *Frec. cardíaca:* {$datos['frecuencia_cardiaca']} bpm";
            if (!empty($datos['frecuencia_respiratoria']))  $parts[] = "💨 *Frec. respiratoria:* {$datos['frecuencia_respiratoria']} rpm";
            if (!empty($datos['spo2']))                     $parts[] = "🫁 *SpO₂:* {$datos['spo2']} %";
            if (!empty($datos['glucosa']))                  $parts[] = "🩸 *Glucosa:* {$datos['glucosa']} mg/dL";
            if (!empty($datos['peso']))                     $parts[] = "⚖️ *Peso:* {$datos['peso']} kg";
            if (!empty($datos['pa_sistolica']) || !empty($datos['pa_diastolica'])) {
                $parts[] = "🩺 *Presión arterial:* " . ($datos['pa_sistolica'] ?? '?') . '/' . ($datos['pa_diastolica'] ?? '?') . " mmHg";
            }
            if ($parts) $msg .= implode("\n", $parts) . "\n";
            if ($obs)   $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> El equipo de cuidados ya está atendiendo la situación.";
            break;

        case 'incidente':
            $incVal = $datos['incidentes'] ?? 'Incidente';
            $msg  = "🔴 *Incidente reportado*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            $msg .= "⚠️ *Tipo:* {$incVal}\n";
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> El equipo ya tomó las medidas necesarias.\n> Contacte al centro para más detalles.";
            break;

        case 'animo':
            $animo = $datos['estado_animo'] ?? '';
            $msg  = "💜 *Cambio de ánimo / conducta*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if ($animo) $msg .= "🙂 *Estado de ánimo:* {$animo}\n";
            if ($obs)   $msg .= "📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> El equipo de cuidados está al tanto.";
            break;

        case 'medicacion':
            $msg  = "💊 *Medicación administrada*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            $msg .= "Se administró la medicación según la prescripción.\n";
            if (!empty($datos['medicamento'])) $msg .= "\n• *Medicamento:* {$datos['medicamento']}";
            if (!empty($datos['dosis']))       $msg .= "\n• *Dosis:* {$datos['dosis']}";
            if ($obs) $msg .= "\n\n📝 _Observaciones:_ {$obs}";
            $msg .= "\n\n> Detalles completos disponibles en la plataforma.";
            break;

        case 'alimentacion':
            $msg  = "🍽️ *Registro de alimentación*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            $tipo_comida = $datos['tipo_comida'] ?? '';
            $ingesta     = $datos['ingesta_pct'] ?? '';
            if ($tipo_comida) $msg .= "🥣 *Comida:* {$tipo_comida}\n";
            if ($ingesta !== '') {
                $emo = $ingesta >= 75 ? '✅' : ($ingesta >= 40 ? '⚠️' : '🔻');
                $msg .= "{$emo} *Ingesta:* {$ingesta}%\n";
            }
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> Más información disponible en la plataforma.";
            break;

        case 'higiene':
            $msg  = "🧼 *Aseo personal completado*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if (!empty($datos['tipo'])) $msg .= "🛁 *Tipo:* {$datos['tipo']}\n";
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> Su familiar fue atendido con el cuidado habitual.";
            break;

        case 'eliminacion':
            $msg  = "🚽 *Registro de eliminación*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if (!empty($datos['tipo']))    $msg .= "• *Tipo:* {$datos['tipo']}\n";
            if (!empty($datos['cantidad']))$msg .= "• *Cantidad:* {$datos['cantidad']}\n";
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> Información disponible en la plataforma.";
            break;

        case 'sueno':
            $msg  = "🌙 *Registro de sueño*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if (!empty($datos['hora_inicio'])) $msg .= "🛏️ *Inicio:* {$datos['hora_inicio']}\n";
            if (!empty($datos['hora_fin']))    $msg .= "☀️ *Fin:* {$datos['hora_fin']}\n";
            if (!empty($datos['calidad_pct'])) {
                $cal = (int)$datos['calidad_pct'];
                $emo = $cal >= 75 ? '😴' : ($cal >= 40 ? '😐' : '😟');
                $msg .= "{$emo} *Calidad:* {$cal}%\n";
            }
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> Más detalles en la plataforma.";
            break;

        case 'movilidad':
            $msg  = "🚶 *Actividad / movilidad*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if (!empty($datos['tipo']))     $msg .= "• *Actividad:* {$datos['tipo']}\n";
            if (!empty($datos['duracion'])) $msg .= "• *Duración:* {$datos['duracion']} min\n";
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> El equipo continúa motivando la actividad diaria.";
            break;

        case 'terapia':
            $msg  = "🧠 *Sesión de terapia*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            if (!empty($datos['tipo']))     $msg .= "• *Tipo:* {$datos['tipo']}\n";
            if (!empty($datos['duracion'])) $msg .= "• *Duración:* {$datos['duracion']} min\n";
            if ($obs) $msg .= "\n📝 _Observaciones:_ {$obs}\n";
            $msg .= "\n> Detalles disponibles en la plataforma.";
            break;

        default:
            $msg  = "📝 *Registro de cuidados*\n";
            $msg .= "_Paciente:_ *{$resName}*  ·  _{$ts}_\n";
            $msg .= "─────────────\n";
            $msg .= "Se ha registrado un evento de cuidados.";
            if ($obs) $msg .= "\n\n📝 _Observaciones:_ {$obs}";
            $msg .= "\n\n> Información completa disponible en la plataforma.";
    }

    return $msg;
}

/**
 * Build styled HTML email body for event-driven care notifications.
 */
function _buildCareEmailHtml(string $subject, string $message, string $tipo, string $instName = 'GeriApp'): string
{
    $accentColors = [
        'signos_vitales' => '#e74c3c',
        'incidente'      => '#e74c3c',
        'medicacion'     => '#27ae60',
        'alimentacion'   => '#27ae60',
        'animo'          => '#8e44ad',
    ];
    $accent       = $accentColors[$tipo] ?? '#1e3a6e';
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
