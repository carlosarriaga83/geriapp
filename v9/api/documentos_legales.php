<?php
/**
 * GeriApp — API /api/documentos_legales.php
 *
 * GET    ?action=list&tipo=terminos|privacidad   → listar documentos
 * GET    ?action=vigente&tipo=terminos|privacidad → obtener doc vigente
 * GET    ?action=firmas&id=<doc_id>              → listar firmas de un doc
 * GET    ?action=pending                         → docs pendientes de firma (usuario actual)
 * POST                                           → crear documento
 * PUT    ?action=set_vigente&id=<doc_id>         → marcar como vigente
 * PUT    ?action=firmar&id=<doc_id>              → firmar documento (usuario actual)
 * DELETE ?id=<doc_id>                            → eliminar documento
 *
 * Roles: admin, superadmin (excepto pending/firmar que es cualquier usuario autenticado)
 */

require_once __DIR__ . '/helpers.php';

$method = api_method();
$action = $_GET['action'] ?? '';

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // public_vigente: NO requiere sesión — T&C y AdP son documentos públicos
    if ($action === 'public_vigente') {
        $tipo   = $_GET['tipo'] ?? '';
        $instId = (int)($_GET['inst'] ?? 0);
        if (!in_array($tipo, ['terminos', 'privacidad'], true)) api_error('Tipo inválido');
        if (!$instId) api_error('Institución requerida');

        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT id, tipo, version, titulo, contenido, requiere_firma, creado_at
             FROM documentos_legales
             WHERE institucion_id = ? AND tipo = ? AND vigente = 1
             LIMIT 1"
        );
        $stmt->execute([$instId, $tipo]);
        api_ok($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    // pending: cualquier usuario autenticado puede consultar
    if ($action === 'pending') {
        api_auth();
        if (!empty($_SESSION['sa_impersonating']) || (($_SESSION['user_rol'] ?? '') === 'superadmin')) {
            api_ok([]);
        }
        $instId  = api_inst_id();
        $userId  = api_user_id();
        if (!$instId) api_ok([]);

        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT dl.id, dl.tipo, dl.version, dl.titulo, dl.contenido
             FROM documentos_legales dl
             WHERE dl.institucion_id = ?
               AND dl.vigente = 1
               AND dl.requiere_firma = 1
               AND dl.id NOT IN (
                   SELECT fd.documento_id FROM firmas_documentos fd WHERE fd.usuario_id = ?
               )
             ORDER BY FIELD(dl.tipo,'terminos','privacidad')"
        );
        $stmt->execute([$instId, $userId]);
        api_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // firmar lookup: cualquier usuario autenticado
    if ($action === 'vigente') {
        api_auth();
        $tipo = $_GET['tipo'] ?? '';
        if (!in_array($tipo, ['terminos', 'privacidad'], true)) api_error('Tipo inválido');
        $instId = api_inst_id();
        if (!$instId) api_ok(null);

        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT id, tipo, version, titulo, contenido, requiere_firma, creado_at
             FROM documentos_legales
             WHERE institucion_id = ? AND tipo = ? AND vigente = 1
             LIMIT 1"
        );
        $stmt->execute([$instId, $tipo]);
        api_ok($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    // Admin-only endpoints
    api_auth_roles(['superadmin', 'admin']);
    $instId = api_inst_id();

    // get single doc by id (with contenido)
    if ($action === 'get') {
        $docId = api_int('id');
        if (!$docId) api_error('ID requerido');
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT id, tipo, version, titulo, contenido, vigente, requiere_firma, creado_por, creado_at, updated_at
             FROM documentos_legales
             WHERE id = ? AND institucion_id = ?
             LIMIT 1"
        );
        $stmt->execute([$docId, $instId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) api_error('Documento no encontrado', 404);
        api_ok($doc);
    }

    if ($action === 'firmas') {
        $docId = api_int('id');
        if (!$docId) api_error('ID requerido');
        $db = Database::getTenant($instId);
        $dbM = Database::getMaster();

        $stmt = $db->prepare(
            "SELECT fd.id, fd.usuario_id, fd.ip, fd.user_agent, fd.firmado_at
             FROM firmas_documentos fd
             WHERE fd.documento_id = ?
             ORDER BY fd.firmado_at DESC"
        );
        $stmt->execute([$docId]);
        $firmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enriquecer con nombre de usuario desde master
        foreach ($firmas as &$f) {
            $u = $dbM->prepare("SELECT nombre, email FROM usuarios WHERE id = ? LIMIT 1");
            $u->execute([$f['usuario_id']]);
            $usr = $u->fetch(PDO::FETCH_ASSOC);
            $f['usuario_nombre'] = $usr['nombre'] ?? '(desconocido)';
            $f['usuario_email']  = $usr['email'] ?? '';
        }
        unset($f);
        api_ok($firmas);
    }

    // Obtener firma_data (imagen autógrafa) de una firma individual
    if ($action === 'get_firma') {
        $firmaId = api_int('id');
        if (!$firmaId) api_error('ID requerido');
        $db = Database::getTenant($instId);
        $stmt = $db->prepare("SELECT firma_data FROM firmas_documentos WHERE id = ? LIMIT 1");
        $stmt->execute([$firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) api_error('Firma no encontrada', 404);
        api_ok(['firma_data' => $row['firma_data']]);
    }

    // list
    $tipo = $_GET['tipo'] ?? '';
    if (!in_array($tipo, ['terminos', 'privacidad'], true)) api_error('Tipo inválido');

    $db = Database::getTenant($instId);
    $stmt = $db->prepare(
        "SELECT id, tipo, version, titulo, vigente, requiere_firma, creado_por, creado_at, updated_at
         FROM documentos_legales
         WHERE institucion_id = ? AND tipo = ?
         ORDER BY creado_at DESC"
    );
    $stmt->execute([$instId, $tipo]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar firmas por documento
    foreach ($docs as &$d) {
        $s = $db->prepare("SELECT COUNT(*) FROM firmas_documentos WHERE documento_id = ?");
        $s->execute([$d['id']]);
        $d['firmas_count'] = (int)$s->fetchColumn();
    }
    unset($d);

    api_ok($docs);
}

// ── POST — crear documento ───────────────────────────────────────────────────
if ($method === 'POST') {
    api_auth_roles(['superadmin', 'admin']);
    $instId = api_inst_id();
    $body   = api_body();

    $tipo     = $body['tipo'] ?? '';
    $version  = trim($body['version'] ?? '1.0');
    $titulo   = trim($body['titulo'] ?? '');
    // Accept base64-encoded HTML (prevents WAF/ModSecurity stripping), fallback to raw
    $contenido = '';
    if (!empty($body['contenido_b64'])) {
        $decoded = base64_decode($body['contenido_b64'], true);
        if ($decoded !== false) $contenido = trim($decoded);
    }
    if (!$contenido) {
        $contenido = trim($body['contenido'] ?? '');
    }
    $vigente  = (int)($body['vigente'] ?? 0);
    $requiere = (int)($body['requiere_firma'] ?? 1);

    if (!in_array($tipo, ['terminos', 'privacidad'], true)) api_error('Tipo inválido');
    if (!$titulo)    api_error('Título requerido');
    if (!$contenido) api_error('Contenido requerido');

    // Sanitise HTML: allow only safe tags, strip all attributes except href on <a>
    $contenido = strip_tags($contenido,
        '<b><strong><i><em><u><br><p><div><span><ul><ol><li>'
        . '<h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><sub><sup><a>'
    );
    // Remove dangerous attributes (onerror, onclick, etc.) and javascript: hrefs
    $contenido = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $contenido);
    $contenido = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $contenido);

    $db = Database::getTenant($instId);

    // Si vigente = 1, desactivar anteriores del mismo tipo
    if ($vigente) {
        $db->prepare("UPDATE documentos_legales SET vigente = 0 WHERE institucion_id = ? AND tipo = ?")
           ->execute([$instId, $tipo]);
    }

    $stmt = $db->prepare(
        "INSERT INTO documentos_legales (institucion_id, tipo, version, titulo, contenido, vigente, requiere_firma, creado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$instId, $tipo, $version, $titulo, $contenido, $vigente, $requiere, api_user_id()]);
    $newId = (int)$db->lastInsertId();

    // Log
    try {
        Database::getMaster()->prepare(
            "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, detalle, ip, estado) VALUES (?,?,?,?,?,?,?)"
        )->execute([api_user_id(), $instId, 'crear_documento_legal', 'Configuracion',
            json_encode(['tipo' => $tipo, 'version' => $version, 'vigente' => $vigente]),
            $_SERVER['REMOTE_ADDR'] ?? '', 'ok']);
    } catch (\Throwable $e) {}

    api_ok(['id' => $newId]);
}

// ── PUT ──────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {

    // Firmar: cualquier usuario autenticado
    if ($action === 'firmar') {
        api_auth();
        $docId  = api_int('id');
        $body   = api_body();
        $firma  = $body['firma_data'] ?? '';

        if (!$docId) api_error('ID de documento requerido');
        if (!$firma) api_error('Firma requerida');

        // Validar que sea base64 PNG razonable (máx ~500KB encoded)
        if (strlen($firma) > 700000) api_error('Firma demasiado grande');
        if (!preg_match('/^data:image\/png;base64,/', $firma)) api_error('Formato de firma inválido');

        $instId = api_inst_id();
        $userId = api_user_id();
        $db     = Database::getTenant($instId);

        // Verificar que el documento existe y es vigente
        $doc = $db->prepare("SELECT id, vigente, requiere_firma FROM documentos_legales WHERE id = ? AND institucion_id = ?");
        $doc->execute([$docId, $instId]);
        $doc = $doc->fetch(PDO::FETCH_ASSOC);
        if (!$doc) api_error('Documento no encontrado', 404);
        if (!$doc['vigente']) api_error('Este documento ya no está vigente');

        // Insertar firma (ON DUPLICATE = actualizar)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
        $stmt = $db->prepare(
            "INSERT INTO firmas_documentos (documento_id, usuario_id, firma_data, ip, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE firma_data = VALUES(firma_data), ip = VALUES(ip), user_agent = VALUES(user_agent), firmado_at = NOW()"
        );
        $stmt->execute([$docId, $userId, $firma, $ip, $ua]);

        // Log
        try {
            Database::getMaster()->prepare(
                "INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, detalle, ip, estado) VALUES (?,?,?,?,?,?,?)"
            )->execute([$userId, $instId, 'firmar_documento', 'Legal',
                json_encode(['documento_id' => $docId]),
                $ip, 'ok']);
        } catch (\Throwable $e) {}

        // ── Enviar copia por email al usuario (y CC al admin si configurado) ──
        try {
            require_once __DIR__ . '/../db/models/Mailer.php';
            require_once __DIR__ . '/../db/models/Configuracion.php';

            $cfg = Configuracion::getOrCreate($instId);
            if (!empty($cfg['smtp_host'])) {
                // Obtener datos del usuario firmante
                $usrStmt = Database::getMaster()->prepare("SELECT nombre, email FROM usuarios WHERE id = ? LIMIT 1");
                $usrStmt->execute([$userId]);
                $usr = $usrStmt->fetch(PDO::FETCH_ASSOC);

                // Obtener título y contenido del documento firmado
                $docStmt = $db->prepare("SELECT tipo, version, titulo, contenido FROM documentos_legales WHERE id = ? LIMIT 1");
                $docStmt->execute([$docId]);
                $docData = $docStmt->fetch(PDO::FETCH_ASSOC);

                if ($usr && $usr['email'] && $docData) {
                    $tipoLabel = $docData['tipo'] === 'terminos' ? 'Términos y Condiciones' : 'Aviso de Privacidad';
                    $fechaFirma = date('d/m/Y H:i');
                    // Render legal content as HTML (sanitized) — it is rich text from the editor.
                    $rawDoc = (string)($docData['contenido'] ?? '');
                    if (preg_match('/<[a-z][\\s\\S]*?>/i', $rawDoc)) {
                        $contenidoHtml = strip_tags($rawDoc,
                            '<b><strong><i><em><u><br><p><div><span><ul><ol><li>'
                            . '<h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><sub><sup><a>'
                        );
                        $contenidoHtml = preg_replace('/\\s+on\\w+\\s*=\\s*["\'][^"\']*["\']/i', '', $contenidoHtml);
                        $contenidoHtml = preg_replace('/href\\s*=\\s*["\']?\\s*javascript:[^"\'>\\s]*/i', 'href="#"', $contenidoHtml);
                    } else {
                        $contenidoHtml = nl2br(htmlspecialchars($rawDoc, ENT_QUOTES, 'UTF-8'));
                    }

                    $htmlBody = <<<HTML
<div style="font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;color:#1e293b">
  <div style="text-align:center;padding:16px 0 12px">
    <h2 style="margin:0;font-size:20px;color:#178391">GeriApp</h2>
    <p style="margin:6px 0 0;color:#64748b;font-size:13px">Copia de documento firmado</p>
  </div>
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin:12px 0 16px">
    <table style="width:100%;font-size:13px;border-collapse:collapse">
      <tr><td style="padding:4px 8px;color:#64748b;width:120px">Documento:</td><td style="padding:4px 8px;font-weight:600">{$tipoLabel}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Título:</td><td style="padding:4px 8px">{$docData['titulo']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Versión:</td><td style="padding:4px 8px">{$docData['version']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Firmante:</td><td style="padding:4px 8px">{$usr['nombre']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Email:</td><td style="padding:4px 8px">{$usr['email']}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">Fecha firma:</td><td style="padding:4px 8px">{$fechaFirma}</td></tr>
      <tr><td style="padding:4px 8px;color:#64748b">IP:</td><td style="padding:4px 8px">{$ip}</td></tr>
    </table>
  </div>
  <div style="border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px">
    <h3 style="margin:0 0 12px;font-size:14px;color:#334155">Contenido del documento</h3>
    <div style="font-size:12px;line-height:1.7;color:#475569;word-wrap:break-word">{$contenidoHtml}</div>
  </div>
  <p style="text-align:center;font-size:11px;color:#94a3b8;margin:16px 0 0">
    Este correo es una constancia automática de aceptación. No es necesario responder.<br>
    &copy; GeriApp — Sistema de gestión de cuidados geriátricos
  </p>
</div>
HTML;

                    $subject = "Copia de {$tipoLabel} firmado — {$docData['titulo']}";
                    $recipients = [$usr['email']];

                    // CC al correo del admin si está configurado
                    $ccEmail = trim($cfg['legal_cc_email'] ?? '');
                    if ($ccEmail && filter_var($ccEmail, FILTER_VALIDATE_EMAIL) && strtolower($ccEmail) !== strtolower($usr['email'])) {
                        $recipients[] = $ccEmail;
                    }

                    $mailer = Mailer::fromConfig($instId);
                    $mailer->send($recipients, $subject, $htmlBody);
                }
            }
        } catch (\Throwable $e) {
            // Email failure should not block the firma response
        }

        api_ok();
    }

    // set_vigente: admin only
    api_auth_roles(['superadmin', 'admin']);
    if ($action !== 'set_vigente') api_error('Acción no válida');

    $docId  = api_int('id');
    $instId = api_inst_id();
    if (!$docId) api_error('ID requerido');

    $db  = Database::getTenant($instId);
    $doc = $db->prepare("SELECT id, tipo FROM documentos_legales WHERE id = ? AND institucion_id = ?");
    $doc->execute([$docId, $instId]);
    $doc = $doc->fetch(PDO::FETCH_ASSOC);
    if (!$doc) api_error('Documento no encontrado', 404);

    // Desactivar todos del mismo tipo, luego activar este
    $db->prepare("UPDATE documentos_legales SET vigente = 0 WHERE institucion_id = ? AND tipo = ?")->execute([$instId, $doc['tipo']]);
    $db->prepare("UPDATE documentos_legales SET vigente = 1 WHERE id = ?")->execute([$docId]);

    api_ok();
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    api_auth_roles(['superadmin', 'admin']);
    $docId  = api_int('id');
    $instId = api_inst_id();
    if (!$docId) api_error('ID requerido');

    $db = Database::getTenant($instId);
    $doc = $db->prepare("SELECT id FROM documentos_legales WHERE id = ? AND institucion_id = ?");
    $doc->execute([$docId, $instId]);
    if (!$doc->fetch()) api_error('Documento no encontrado', 404);

    $db->prepare("DELETE FROM documentos_legales WHERE id = ?")->execute([$docId]);

    api_ok();
}

api_error('Método no permitido', 405);
