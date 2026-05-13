<?php
/**
 * GeriApp — API /api/upload.php
 *
 * POST multipart/form-data
 *   $_FILES['archivo']  → archivo a subir
 *   $_POST['contexto']  → 'historial' | 'avatar' | 'logo'
 *   $_POST['expediente_id'] → (requerido si contexto=historial)
 *   $_POST['nombre']    → nombre descriptivo del documento
 *   $_POST['tipo']      → laboratorio|imagen|receta|consentimiento|otro
 *
 * Responde: { success, data: { url, path, documento_id? } }
 *
 * Roles: admin, medico, enfermero, superadmin
 */

require_once __DIR__ . '/helpers.php';

api_auth_roles(['superadmin', 'admin', 'medico', 'enfermero']);
api_require_method('POST');

// ── Configuración de tipos permitidos ─────────────────────────────────────────
const UPLOAD_MAX_SIZE = 10 * 1024 * 1024; // 10 MB

const UPLOAD_MIME_ALLOWED = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
];

const UPLOAD_EXT_ALLOWED = [
    'jpg', 'jpeg', 'png', 'webp', 'gif',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt',
];

// ── Validar que llegó un archivo ──────────────────────────────────────────────
if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $err_map = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera MAX_FILE_SIZE del formulario',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
        UPLOAD_ERR_CANT_WRITE => 'No se puede escribir en disco',
    ];
    $code = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
    api_error($err_map[$code] ?? 'Error desconocido al subir archivo', 400);
}

$archivo  = $_FILES['archivo'];
$contexto = $_POST['contexto'] ?? 'historial';

// ── Validaciones de tamaño y tipo ─────────────────────────────────────────────
if ($archivo['size'] > UPLOAD_MAX_SIZE) {
    api_error('El archivo supera el límite de 10 MB', 413);
}

$ext  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if (!in_array($ext, UPLOAD_EXT_ALLOWED, true)) {
    api_error("Extensión de archivo no permitida ({$ext})", 415);
}
// §6.5 Validación MIME reforzada con finfo
$mime = api_validate_mime($archivo['tmp_name'], UPLOAD_MIME_ALLOWED);

// ── Construir ruta destino ────────────────────────────────────────────────────
$base_upload = dirname(__DIR__) . '/uploads';
$inst_id     = api_inst_id();

// Todas las rutas con datos clínicos o institucionales se organizan por inst_id.
// avatars es plano (una foto de perfil es del usuario, no de la institución).
$dirs = [
    'historial'      => "historial/{$inst_id}",
    'avatar'         => "avatars",
    'logo'           => "logos/{$inst_id}",
    'residente_foto' => "fotos_residentes/{$inst_id}",
];
$subdir = $dirs[$contexto] ?? 'otros';

$destDir = $base_upload . '/' . $subdir;
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

// Nombre único: timestamp + random + extensión
$filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $destDir . '/' . $filename;
$urlPath  = BASE_URL . '/uploads/' . $subdir . '/' . $filename;

// ── Mover archivo ─────────────────────────────────────────────────────────────
if (!move_uploaded_file($archivo['tmp_name'], $destPath)) {
    api_error('Error al guardar el archivo en el servidor', 500);
}

// ── (historial document upload removed in v1.26.0 — tables dropped) ──────────

// ── Actualizar avatar de usuario ──────────────────────────────────────────────
if ($contexto === 'avatar') {
    Usuario::update(api_user_id(), ['avatar_path' => $urlPath]);
    $_SESSION['user_avatar'] = $urlPath;
}

api_ok([
    'url'          => $urlPath,
    'path'         => 'uploads/' . $subdir . '/' . $filename,
    'documento_id' => $documento_id,
    'mime_type'    => $mime,
    'tamano'       => $archivo['size'],
], 'Archivo subido correctamente');
