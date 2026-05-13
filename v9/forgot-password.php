<?php
require_once 'conf/config.php';

// Ya autenticado → redirigir
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

$success = false;
$error   = '';
$fpInfo  = '';

if (isset($_GET['csrf_expired'])) { $fpInfo = 'Tu sesión expiró. Se ha renovado el formulario automáticamente.'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // §1.9 Validar CSRF
    if (!csrf_validate($_POST['_csrf'] ?? '')) {
        header('Location: ' . BASE_URL . '/forgot-password.php?csrf_expired=1');
        exit;
    } else {
    require_once 'db/Database.php';
    require_once 'db/models/Mailer.php';
    require_once 'db/models/Configuracion.php';

    $db    = Database::getInstance();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } else {
        // Buscar usuario activo (sin revelar si existe o no)
        $stmt = $db->prepare(
            "SELECT id, nombre, institucion_id FROM usuarios WHERE email = ? AND estado = 'activo' LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Eliminar tokens anteriores sin usar para este correo
            $db->prepare("DELETE FROM password_resets WHERE email = ? AND usado = 0")->execute([$email]);

            // Generar token seguro y guardarlo
            // gmdate() genera la fecha en UTC para que coincida con NOW() de MySQL.
            $token   = bin2hex(random_bytes(32));
            $expires = gmdate('Y-m-d H:i:s', time() + 3600);

            $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
               ->execute([$email, $token, $expires]);

            // Construir URL de recuperación
            $resetUrl = app_public_url() . '/reset-password.php?token=' . $token;

            // Plantilla del correo
            $instDirecc = '';
            if (!empty($user['institucion_id'])) {
                try {
                    $instRow    = Institucion::getById((int)$user['institucion_id']);
                    $instDirecc = $instRow['direccion'] ?: (defined('APP_ADDRESS') ? APP_ADDRESS : '');
                } catch (\Throwable $e) {}
            }
            if (!$instDirecc && defined('APP_ADDRESS')) {
                $instDirecc = APP_ADDRESS;
            }
            $html = buildResetEmail($user['nombre'], $resetUrl, $instDirecc);
            $text = buildResetEmailText($user['nombre'], $resetUrl, $instDirecc);

            // Intentar enviar con SMTP de la institución
            $sent = false;
            if (!empty($user['institucion_id'])) {
                try {
                    $mailer = Mailer::fromConfig((int)$user['institucion_id']);
                    $result = $mailer->send($email, 'Restablece tu contraseña en GeriApp', $html, $text);
                    $sent   = $result['ok'];
                } catch (\Throwable $e) {
                    $sent = false;
                }
            }

            // Fallback: mail() nativo de PHP
            if (!$sent) {
                $headers  = "From: GeriApp <no-reply@geriapp.mx>\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                @mail($email, 'Restablece tu contraseña en GeriApp', $html, $headers);
            }
        }

        // Siempre mostrar éxito (no revelar si el correo existe)
        $success = true;
    }
    } // end else (CSRF válido)
}

// ── Plantilla HTML del correo ─────────────────────────────────────────────────
function buildResetEmail(string $nombre, string $url, string $direccion = ''): string
{
    $expira = date('d/m/Y H:i', strtotime('+1 hour'));
    $addrLine = $direccion
        ? "<p style='margin:6px 0 0;font-size:10.5px;color:#94a3b8;'>&#128205; " . htmlspecialchars($direccion) . "</p>"
        : '';
    return "
    <!DOCTYPE html>
    <html lang='es'>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
    <body style='margin:0;padding:0;background:#f7f9fb;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;'>
      <div style='max-width:520px;margin:40px auto;background:#fff;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,.07);overflow:hidden;'>
        <!-- Cabecera -->
        <div style='background:#178391;padding:32px 36px;text-align:center;'>
          <div style='display:inline-block;background:rgba(255,255,255,.15);border-radius:50%;width:56px;height:56px;line-height:56px;text-align:center;margin-bottom:12px;'>
            <span style='font-size:26px;'>&#x1F511;</span>
          </div>
          <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Recupera tu contraseña</h1>
          <p style='margin:6px 0 0;color:rgba(255,255,255,.8);font-size:13px;'>GeriApp</p>
        </div>
        <!-- Cuerpo -->
        <div style='padding:36px 36px 28px;'>
          <p style='margin:0 0 16px;font-size:14.5px;color:#1a202c;'>Hola, <strong>" . htmlspecialchars($nombre) . "</strong></p>
          <p style='margin:0 0 24px;font-size:14px;color:#475569;line-height:1.7;'>
            Recibimos una solicitud para restablecer la contraseña de tu cuenta en GeriApp.
            Haz clic en el botón de abajo para crear una nueva contraseña.
          </p>
          <div style='text-align:center;margin:28px 0;'>
            <a href='" . htmlspecialchars($url) . "'
               style='display:inline-block;background:#178391;color:#fff;text-decoration:none;
                      padding:13px 32px;border-radius:6px;font-size:14px;font-weight:600;
                      letter-spacing:.3px;'>
              Restablecer contraseña
            </a>
          </div>
          <p style='margin:0 0 8px;font-size:12.5px;color:#64748b;'>
            Si el botón no funciona, copia y pega este enlace en tu navegador:
          </p>
          <p style='margin:0 0 24px;font-size:11.5px;color:#94a3b8;word-break:break-all;'>
            " . htmlspecialchars($url) . "
          </p>
          <div style='background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:12px 16px;font-size:12.5px;color:#92400e;'>
            &#x23F1; Este enlace expira el <strong>" . $expira . "</strong>. Si no solicitaste este cambio, ignora este correo.
          </div>
        </div>
        <!-- Pie -->
        <div style='background:#f8fafc;padding:16px 36px;border-top:1px solid #e2e8f0;text-align:center;'>
          <p style='margin:0 0 6px;font-size:11px;color:#94a3b8;'>
            GeriApp &middot; Sistema de gestión geriátrica
          </p>
          <p style='margin:0;font-size:11px;color:#94a3b8;line-height:1.7;'>
            Recibes esto porque alguien solicitó restablecer la contraseña asociada
            a esta dirección de correo. Si no fuiste tú, ignora este mensaje; tu contraseña no cambiará.
          </p>
          {$addrLine}
        </div>
      </div>
    </body>
    </html>";
}

function buildResetEmailText(string $nombre, string $url, string $direccion = ''): string
{
    $expira = date('d/m/Y H:i', strtotime('+1 hour'));
    return "Restablece tu contraseña en GeriApp\n"
        . str_repeat('-', 40) . "\n\n"
        . "Hola, {$nombre}\n\n"
        . "Recibimos una solicitud para restablecer la contraseña de tu cuenta en GeriApp.\n"
        . "Usa el siguiente enlace para crear una nueva contraseña:\n\n"
        . "{$url}\n\n"
        . "Este enlace expira el {$expira}.\n"
        . "Si no solicitaste este cambio, ignora este correo.\n\n"
        . str_repeat('-', 40) . "\n"
        . "Recibes esto porque alguien solicitó restablecer la contraseña asociada\n"
        . "a esta dirección de correo en GeriApp.\n"
        . ($direccion ? "Dirección física: {$direccion}\n" : '');
}

$pageTitle = 'Recuperar Contraseña';
$bodyClass = 'login-page';
require_once 'includes/head.php';
?>

<div class="login-outer">
    <div class="login-card">

        <!-- Brand -->
        <div class="login-brand-new">
            <div class="login-heart-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                     stroke="#178391" stroke-width="1.8">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                             l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78
                             l1.06 1.06L12 21.23l7.78-7.78
                             1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            <h1 class="login-app-name">GeriApp</h1>
            <p class="login-app-sub">Recupera tu contraseña</p>
        </div>

        <?php if ($success): ?>
        <!-- Estado: correo enviado -->
        <div class="login-alert login-alert--info" style="flex-direction:column;align-items:flex-start;gap:6px;">
            <div style="display:flex;align-items:center;gap:9px;font-weight:600;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Correo enviado
            </div>
            <p style="margin:0;font-size:12.5px;line-height:1.5;">
                Si ese correo está registrado en GeriApp, recibirás un enlace para restablecer tu contraseña.
                Revisa también tu carpeta de spam.
            </p>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <a href="<?= BASE_URL ?>/index.php" class="login-btn"
               style="display:inline-block;text-decoration:none;line-height:1;padding:11px 28px;font-size:14px;">
                Volver al inicio de sesión
            </a>
        </div>

        <?php else: ?>

        <?php if ($fpInfo): ?>
        <div class="login-alert login-alert--info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= htmlspecialchars($fpInfo) ?>
        </div>
        <?php elseif ($error): ?>
        <div class="login-alert login-alert--error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;line-height:1.6;">
            Ingresa el correo electrónico de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.
        </p>

        <form action="<?= BASE_URL ?>/forgot-password.php" method="post" autocomplete="off" class="login-form">
            <?= csrf_field() ?>
            <div class="login-field">
                <label for="email">Correo Electrónico <span class="login-required">*</span></label>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4
                                     c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email"
                           class="login-input"
                           placeholder="tu@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <button type="submit" class="login-btn">Enviar enlace de recuperación</button>
        </form>

        <?php endif; ?>

        <div class="login-links" style="margin-top:18px;">
            <p><a href="<?= BASE_URL ?>/index.php" class="login-back">← Volver al inicio de sesión</a></p>
        </div>

    </div>
</div>

<script>
(function(){
    document.querySelectorAll('form.login-form').forEach(function(f){
        f.addEventListener('submit', function(){
            var btn = f.querySelector('button[type="submit"], .login-btn');
            if (!btn || btn.dataset._locked) return;
            btn.dataset._locked = '1';
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.style.opacity = '0.75';
            btn.style.cursor = 'not-allowed';
            btn.innerHTML = '<span class="login-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:loginSpin .7s linear infinite;vertical-align:-2px;margin-right:8px"></span>Enviando…';
            setTimeout(function(){ if (btn.disabled) { btn.disabled = false; btn.innerHTML = orig; btn.style.opacity = ''; btn.style.cursor = ''; delete btn.dataset._locked; } }, 15000);
        });
    });
    if (!document.getElementById('_loginSpinKf')) {
        var s = document.createElement('style'); s.id = '_loginSpinKf';
        s.textContent = '@keyframes loginSpin{to{transform:rotate(360deg)}}';
        document.head.appendChild(s);
    }
})();
</script>

<?php require_once 'includes/foot.php'; ?>
