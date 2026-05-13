<?php
/**
 * GeriApp — Temporary PDF storage
 *
 * POST  multipart/form-data  { pdf: <blob> }
 *   → Stores PDF in a temp directory, returns { success, url }
 *
 * GET   ?f=<token>.pdf
 *   → Serves the PDF file with inline Content-Disposition
 *
 * Temp files older than 10 minutes are purged automatically on POST.
 */

require_once __DIR__ . '/helpers.php';

// ── Configuration ────────────────────────────────────────────────────────────
define('PDF_TEMP_DIR', dirname(__DIR__) . '/uploads/pdf_temp');
define('PDF_MAX_SIZE', 20 * 1024 * 1024); // 20 MB
define('PDF_TTL_SEC',  600);              // 10 minutes

// Ensure temp directory exists
if (!is_dir(PDF_TEMP_DIR)) {
    mkdir(PDF_TEMP_DIR, 0755, true);
    // Deny direct browsing
    file_put_contents(PDF_TEMP_DIR . '/.htaccess', "Options -Indexes\n");
}

// ── GET: serve a stored PDF ──────────────────────────────────────────────────
if (api_method() === 'GET') {
    // No session auth required for GET: the 32-char hex token in the filename
    // is a cryptographically random secret (128-bit) that acts as a capability
    // URL. Files auto-expire after 10 minutes. This allows Capacitor's native
    // Filesystem.downloadFile() (which runs outside the WebView and has no
    // session cookies) to fetch the PDF.

    $file = $_GET['f'] ?? '';
    // Only allow safe filenames: hex token + .pdf
    if (!preg_match('/\A[a-f0-9]{32}\.pdf\z/', $file)) {
        api_error('Archivo no válido', 400);
    }

    $path = PDF_TEMP_DIR . '/' . $file;
    if (!is_file($path)) {
        api_error('Archivo no encontrado o expirado', 404);
    }

    // Serve PDF
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="reporte.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=300');
    readfile($path);
    exit;
}

// ── POST: store a PDF blob ───────────────────────────────────────────────────
api_require_method('POST');
api_auth();

// Purge old temp files (best-effort, non-blocking)
$cutoff = time() - PDF_TTL_SEC;
foreach (glob(PDF_TEMP_DIR . '/*.pdf') as $old) {
    if (filemtime($old) < $cutoff) {
        @unlink($old);
    }
}

// Validate upload
if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    api_error('No se recibió el archivo PDF', 400);
}

$tmp  = $_FILES['pdf']['tmp_name'];
$size = $_FILES['pdf']['size'];

if ($size > PDF_MAX_SIZE) {
    api_error('El PDF excede el tamaño máximo permitido', 413);
}

// Verify it's actually a PDF (check magic bytes)
$header = file_get_contents($tmp, false, null, 0, 5);
if ($header !== '%PDF-') {
    api_error('El archivo no es un PDF válido', 400);
}

// Save with a random token name
$token = bin2hex(random_bytes(16));
$dest  = PDF_TEMP_DIR . '/' . $token . '.pdf';
if (!move_uploaded_file($tmp, $dest)) {
    api_error('Error al guardar el archivo', 500);
}

// Build the URL the client can use to open the PDF
$url = app_public_url() . '/api/pdf_temp.php?f=' . $token . '.pdf';
api_ok(['url' => $url]);
