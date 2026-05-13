<?php
/**
 * GeriApp — API /medico/api.php
 *
 * Endpoints para Expediente Médico (NOM-004 / NOM-024).
 *
 * GET  ?action=hc&residente_id=N             → Historia Clínica
 * GET  ?action=notas&residente_id=N          → Notas de evolución
 * GET  ?action=nota&id=N                     → Nota individual
 * GET  ?action=enfermeria&residente_id=N     → Hojas de enfermería
 * GET  ?action=estudios&residente_id=N       → Estudios auxiliares
 * GET  ?action=consentimientos&residente_id=N→ Consentimientos
 * GET  ?action=auditoria&residente_id=N      → Log de auditoría
 * GET  ?action=cie10&q=texto                 → Búsqueda CIE-10
 *
 * POST action=save_hc                        → Crear/actualizar HC
 * POST action=create_nota                    → Nueva nota
 * POST action=update_nota                    → Editar nota (no firmada)
 * POST action=firmar_nota                    → Firmar nota
 * POST action=create_enfermeria              → Nueva hoja enfermería
 * POST action=update_enfermeria              → Editar hoja (no firmada)
 * POST action=firmar_enfermeria              → Firmar hoja
 * POST action=create_estudio                 → Nuevo estudio
 * POST action=update_estudio                 → Editar estudio
 * POST action=create_consentimiento          → Nuevo consentimiento
 * POST action=save_firma                     → Guardar imagen de firma
 *
 * DELETE ?action=delete_nota&id=N            → Eliminar nota (no firmada)
 * DELETE ?action=delete_estudio&id=N         → Eliminar estudio
 */

require_once __DIR__ . '/../api/helpers.php';

$method = api_method();
$action = $_REQUEST['action'] ?? '';

// ═══════════════════════════════════════════════════════════════════════════════
// GET
// ═══════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {
    api_auth_roles(['superadmin','admin','medico','enfermero','familiar']);
    $instId = api_inst_id();

    switch ($action) {

        case 'hc':
            $rid = api_int('residente_id');
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $hc = ExpedienteMedico::getOrCreateHC($rid, $instId);
            ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'ver', 'historia_clinica', $hc['id'] ?? null);
            api_json(['success' => true, 'data' => $hc ?: null]);
            break;

        case 'notas':
            $rid  = api_int('residente_id');
            $tipo = $_GET['tipo'] ?? null;
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $rows = ExpedienteMedico::getNotas($rid, $tipo);
            api_json(['success' => true, 'data' => $rows]);
            break;

        case 'nota':
            $id = api_int('id');
            if (!$id) api_error('id requerido', 400);
            $nota = ExpedienteMedico::getNota($id);
            if (!$nota) api_error('Nota no encontrada', 404);
            api_assert_residente($nota['residente_id']);
            api_json(['success' => true, 'data' => $nota]);
            break;

        case 'enfermeria':
            $rid = api_int('residente_id');
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $rows = ExpedienteMedico::getEnfermeria($rid);
            api_json(['success' => true, 'data' => $rows]);
            break;

        case 'estudios':
            $rid = api_int('residente_id');
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $rows = ExpedienteMedico::getEstudios($rid);
            api_json(['success' => true, 'data' => $rows]);
            break;

        case 'consentimientos':
            $rid = api_int('residente_id');
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $rows = ExpedienteMedico::getConsentimientos($rid);
            api_json(['success' => true, 'data' => $rows]);
            break;

        case 'auditoria':
            api_auth_roles(['superadmin','admin']);
            $rid = api_int('residente_id');
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);
            $rows = ExpedienteMedico::getAuditLog($rid);
            api_json(['success' => true, 'data' => $rows]);
            break;

        case 'cie10':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) api_json(['success' => true, 'data' => []]);
            $results = ExpedienteMedico::searchCIE10($q);
            api_json(['success' => true, 'data' => $results]);
            break;

        default:
            api_error('Acción GET no válida', 400);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST
// ═══════════════════════════════════════════════════════════════════════════════
elseif ($method === 'POST') {
    $body = api_body();
    $action = $body['action'] ?? $action;

    switch ($action) {

        case 'save_hc':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $rid = (int)($body['residente_id'] ?? 0);
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);

            $hc = ExpedienteMedico::getHC($rid);
            if ($hc) {
                $ok = ExpedienteMedico::updateHC($hc['id'], $body);
                ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'editar', 'historia_clinica', $hc['id']);
            } else {
                $body['residente_id']   = $rid;
                $body['institucion_id'] = $instId;
                $newId = ExpedienteMedico::createHC($body);
                $ok = (bool) $newId;
                ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'crear', 'historia_clinica', $newId ?: null);
            }
            $updated = ExpedienteMedico::getHC($rid);
            api_json(['success' => $ok, 'data' => $updated]);
            break;

        case 'create_nota':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $rid = (int)($body['residente_id'] ?? 0);
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);

            $body['institucion_id'] = $instId;
            $body['usuario_id']     = $_SESSION['user_id'];
            if (empty($body['fecha'])) $body['fecha'] = date('Y-m-d');
            if (empty($body['hora']))  $body['hora']  = date('H:i');

            $id = ExpedienteMedico::createNota($body);
            if (!$id) api_error('Error al crear nota', 500);
            ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'crear', 'nota_' . ($body['tipo'] ?? 'evolucion'), $id);
            $nota = ExpedienteMedico::getNota($id);
            api_json(['success' => true, 'data' => $nota]);
            break;

        case 'update_nota':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $id = (int)($body['id'] ?? 0);
            if (!$id) api_error('id requerido', 400);
            $nota = ExpedienteMedico::getNota($id);
            if (!$nota) api_error('Nota no encontrada', 404);
            api_assert_residente($nota['residente_id']);
            if ($nota['firmado']) api_error('No se puede editar una nota firmada', 403);

            $ok = ExpedienteMedico::updateNota($id, $body);
            ExpedienteMedico::logAudit($nota['residente_id'], $instId, $_SESSION['user_id'], 'editar', 'nota_' . $nota['tipo'], $id);
            $updated = ExpedienteMedico::getNota($id);
            api_json(['success' => $ok, 'data' => $updated]);
            break;

        case 'firmar_nota':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $id = (int)($body['id'] ?? 0);
            if (!$id) api_error('id requerido', 400);
            $nota = ExpedienteMedico::getNota($id);
            if (!$nota) api_error('Nota no encontrada', 404);
            api_assert_residente($nota['residente_id']);

            $firmaPath = $body['firma_path'] ?? null;
            $ok = ExpedienteMedico::firmarNota($id, $_SESSION['user_id'], $firmaPath);
            ExpedienteMedico::logAudit($nota['residente_id'], $instId, $_SESSION['user_id'], 'firmar', 'nota_' . $nota['tipo'], $id);
            api_json(['success' => $ok]);
            break;

        case 'create_enfermeria':
            api_auth_roles(['superadmin','admin','medico','enfermero']);
            $instId = api_inst_id();
            $rid = (int)($body['residente_id'] ?? 0);
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);

            $body['institucion_id'] = $instId;
            $body['usuario_id']     = $_SESSION['user_id'];
            if (empty($body['fecha'])) $body['fecha'] = date('Y-m-d');

            $id = ExpedienteMedico::createEnfermeria($body);
            if (!$id) api_error('Error al crear registro', 500);
            ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'crear', 'enfermeria', $id);
            $reg = ExpedienteMedico::getRegistroEnfermeria($id);
            api_json(['success' => true, 'data' => $reg]);
            break;

        case 'update_enfermeria':
            api_auth_roles(['superadmin','admin','medico','enfermero']);
            $instId = api_inst_id();
            $id = (int)($body['id'] ?? 0);
            if (!$id) api_error('id requerido', 400);
            $reg = ExpedienteMedico::getRegistroEnfermeria($id);
            if (!$reg) api_error('Registro no encontrado', 404);
            api_assert_residente($reg['residente_id']);
            if ($reg['firmado']) api_error('No se puede editar un registro firmado', 403);

            $ok = ExpedienteMedico::updateEnfermeria($id, $body);
            ExpedienteMedico::logAudit($reg['residente_id'], $instId, $_SESSION['user_id'], 'editar', 'enfermeria', $id);
            $updated = ExpedienteMedico::getRegistroEnfermeria($id);
            api_json(['success' => $ok, 'data' => $updated]);
            break;

        case 'firmar_enfermeria':
            api_auth_roles(['superadmin','admin','medico','enfermero']);
            $instId = api_inst_id();
            $id = (int)($body['id'] ?? 0);
            if (!$id) api_error('id requerido', 400);
            $reg = ExpedienteMedico::getRegistroEnfermeria($id);
            if (!$reg) api_error('Registro no encontrado', 404);
            api_assert_residente($reg['residente_id']);

            $firmaPath = $body['firma_path'] ?? null;
            $ok = ExpedienteMedico::firmarEnfermeria($id, $firmaPath);
            ExpedienteMedico::logAudit($reg['residente_id'], $instId, $_SESSION['user_id'], 'firmar', 'enfermeria', $id);
            api_json(['success' => $ok]);
            break;

        case 'create_estudio':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $rid = (int)($body['residente_id'] ?? 0);
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);

            $body['institucion_id'] = $instId;
            $body['usuario_id']     = $_SESSION['user_id'];
            if (empty($body['fecha'])) $body['fecha'] = date('Y-m-d');

            // Handle file upload
            if (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/expediente/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext  = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
                $safe = preg_replace('/[^a-z0-9._-]/', '', strtolower($_FILES['archivo']['name']));
                $name = time() . '_' . $safe;
                $dest = $uploadDir . $name;
                if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) {
                    $body['archivo_path'] = 'uploads/expediente/' . $name;
                    $body['mime_type']    = $_FILES['archivo']['type'];
                }
            }

            $id = ExpedienteMedico::createEstudio($body);
            if (!$id) api_error('Error al crear estudio', 500);
            ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'crear', 'estudio', $id);
            api_json(['success' => true, 'data' => ['id' => $id]]);
            break;

        case 'update_estudio':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $id = (int)($body['id'] ?? 0);
            if (!$id) api_error('id requerido', 400);
            $ok = ExpedienteMedico::updateEstudio($id, $body);
            api_json(['success' => $ok]);
            break;

        case 'create_consentimiento':
            api_auth_roles(['superadmin','admin','medico']);
            $instId = api_inst_id();
            $rid = (int)($body['residente_id'] ?? 0);
            if (!$rid) api_error('residente_id requerido', 400);
            api_assert_residente($rid);

            $body['institucion_id'] = $instId;
            $body['usuario_id']     = $_SESSION['user_id'];
            if (empty($body['fecha'])) $body['fecha'] = date('Y-m-d');

            // Handle document upload
            if (!empty($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/expediente/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $safe = preg_replace('/[^a-z0-9._-]/', '', strtolower($_FILES['documento']['name']));
                $name = time() . '_consent_' . $safe;
                $dest = $uploadDir . $name;
                if (move_uploaded_file($_FILES['documento']['tmp_name'], $dest)) {
                    $body['documento_path'] = 'uploads/expediente/' . $name;
                }
            }

            $id = ExpedienteMedico::createConsentimiento($body);
            if (!$id) api_error('Error al crear consentimiento', 500);
            ExpedienteMedico::logAudit($rid, $instId, $_SESSION['user_id'], 'crear', 'consentimiento', $id);
            api_json(['success' => true, 'data' => ['id' => $id]]);
            break;

        case 'save_firma':
            api_auth_roles(['superadmin','admin','medico','enfermero']);
            $instId = api_inst_id();
            $dataUri = $body['firma_data'] ?? '';
            if (!$dataUri || !str_starts_with($dataUri, 'data:image/png;base64,')) {
                api_error('Datos de firma inválidos', 400);
            }
            $dir = dirname(__DIR__) . '/uploads/firmas/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $decoded = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
            if (!$decoded) api_error('Error decodificando firma', 400);
            $filename = 'firma_' . $_SESSION['user_id'] . '_' . time() . '.png';
            $path = $dir . $filename;
            file_put_contents($path, $decoded);
            api_json(['success' => true, 'path' => 'uploads/firmas/' . $filename]);
            break;

        default:
            api_error('Acción POST no válida', 400);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// DELETE
// ═══════════════════════════════════════════════════════════════════════════════
elseif ($method === 'DELETE') {
    api_auth_roles(['superadmin','admin','medico']);
    $instId = api_inst_id();

    switch ($action) {
        case 'delete_nota':
            $id = api_int('id');
            if (!$id) api_error('id requerido', 400);
            $nota = ExpedienteMedico::getNota($id);
            if (!$nota) api_error('Nota no encontrada', 404);
            api_assert_residente($nota['residente_id']);
            if ($nota['firmado']) api_error('No se puede eliminar una nota firmada', 403);

            $ok = ExpedienteMedico::deleteNota($id);
            ExpedienteMedico::logAudit($nota['residente_id'], $instId, $_SESSION['user_id'], 'eliminar', 'nota_' . $nota['tipo'], $id);
            api_json(['success' => $ok]);
            break;

        case 'delete_estudio':
            $id = api_int('id');
            if (!$id) api_error('id requerido', 400);
            $ok = ExpedienteMedico::deleteEstudio($id);
            api_json(['success' => $ok]);
            break;

        default:
            api_error('Acción DELETE no válida', 400);
    }
}

else {
    api_error('Método no permitido', 405);
}
