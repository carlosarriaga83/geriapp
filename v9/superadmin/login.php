<?php
/**
 * GeriApp — Superadmin Login
 */
require_once dirname(__DIR__) . '/conf/config.php';
require_once __DIR__ . '/auth_config.php';

$error = '';

// Already authenticated → go to portal
if (!empty($_SESSION['sa_authenticated'])) {
    header('Location: ' . BASE_URL . '/superadmin/');
    exit;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';

    $authenticated = false;
    $saName = '';

    foreach (SA_CREDENTIALS as $cred) {
        if ($cred['user'] === $user && password_verify($pass, $cred['pass'])) {
            $authenticated = true;
            $saName = $cred['name'];
            break;
        }
    }

    if ($authenticated) {
        $_SESSION['sa_authenticated'] = true;
        $_SESSION['sa_user'] = $user;
        $_SESSION['sa_name'] = $saName;
        header('Location: ' . BASE_URL . '/superadmin/');
        exit;
    } else {
        $error = 'Credenciales inválidas';
    }
}

$pageTitle = 'Superadmin Login';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <script>(function(){var t=localStorage.getItem('geriappTheme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
    <link rel="icon" type="image/png" sizes="64x64" href="<?= ASSETS_URL ?>/icons/app/icon_64.png">
    <script>(function(){
        var tint='#f59e0b',dot=<?= json_encode(str_contains($_SERVER['HTTP_HOST'] ?? '', 'test')) ?>;
        var img=new Image();img.crossOrigin='anonymous';
        img.onload=function(){
            var c=document.createElement('canvas');c.width=c.height=64;
            var x=c.getContext('2d');
            x.drawImage(img,0,0,64,64);
            if(tint){x.globalCompositeOperation='source-atop';x.fillStyle=tint;x.fillRect(0,0,64,64);}
            if(dot){x.globalCompositeOperation='source-over';x.fillStyle='#ef4444';x.beginPath();x.arc(52,10,11,0,2*Math.PI);x.fill();x.strokeStyle='#fff';x.lineWidth=2;x.stroke();}
            document.querySelectorAll('link[rel="icon"]').forEach(function(l){l.remove();});
            var lk=document.createElement('link');lk.rel='icon';lk.type='image/png';lk.href=c.toDataURL('image/png');document.head.appendChild(lk);
        };
        img.src=<?= json_encode(ASSETS_URL . '/icons/app/icon_64.png') ?>;
    })()</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cd-bg: #f6fbfd; --cd-surface: #fff; --cd-text: #033f3f;
            --cd-text-muted: #526b75; --cd-border: #b5cddd; --cd-radius: 0.5rem;
            --cd-primary: #033f3f; --cd-primary-fg: #ffffff; --cd-accent: #178391;
            --cd-danger: #ef4444; --cd-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --cd-shadow-lg: 0 4px 12px rgba(0,0,0,.08);
            --cd-font: 'Open Sans', system-ui, -apple-system, sans-serif;
            --cd-title-font: 'Poppins', 'Open Sans', system-ui, -apple-system, sans-serif;
            --cd-page-bg: radial-gradient(circle at 8% 10%, rgba(23, 131, 145, .08) 0 2px, transparent 3px) 0 0 / 34px 34px, linear-gradient(180deg, #e8f6fb 0%, #f6fbfd 54%, #ffffff 100%);
        }
        [data-theme="dark"] {
            --cd-bg: #0b0d10; --cd-surface: #15181c; --cd-text: #f6fbfd;
            --cd-text-muted: #a8b3bc; --cd-border: #2b3036; --cd-primary: #178391;
            --cd-primary-fg: #ffffff; --cd-accent: #64c4cf; --cd-shadow: 0 1px 3px rgba(0,0,0,.3);
            --cd-shadow-lg: 0 4px 12px rgba(0,0,0,.4);
            --cd-page-bg: radial-gradient(circle at 8% 10%, rgba(255, 255, 255, .045) 0 2px, transparent 3px) 0 0 / 34px 34px, linear-gradient(180deg, #07090b 0%, #0b0d10 58%, #111418 100%);
            color-scheme: dark;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            background: var(--cd-page-bg); color: var(--cd-text);
            font-family: var(--cd-font); -webkit-font-smoothing: antialiased;
            display: flex; align-items: center; justify-content: center;
        }
        .sa-login {
            width: 100%; max-width: 380px; padding: 24px;
        }
        .sa-login-card {
            background: var(--cd-surface); border: 1px solid var(--cd-border);
            border-radius: 12px; padding: 32px 28px; box-shadow: var(--cd-shadow-lg);
            text-align: center;
        }
        .sa-login-icon {
            width: 48px; height: 48px; margin: 0 auto 12px;
            background: color-mix(in srgb, var(--cd-primary) 8%, transparent);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
        }
        .sa-login-icon svg { color: var(--cd-text); }
        .sa-login-card h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        .sa-login-card p { font-size: 13px; color: var(--cd-text-muted); margin: 0 0 24px; }
        .sa-login-form { display: flex; flex-direction: column; gap: 14px; text-align: left; }
        .sa-login-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .sa-login-required { color:#ef4444; font-weight:800; margin-left:3px; }
        .sa-login-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--cd-border);
            border-radius: var(--cd-radius); background: var(--cd-bg); color: var(--cd-text);
            font-family: var(--cd-font); font-size: 14px;
        }
        .sa-login-input:focus { outline: none; border-color: var(--cd-accent); box-shadow: 0 0 0 3px rgba(23,131,145,.14); }
        .sa-login-btn {
            width: 100%; padding: 12px; border: none; border-radius: var(--cd-radius);
            background: var(--cd-primary); color: var(--cd-primary-fg);
            font-family: var(--cd-font); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity .15s; margin-top: 4px;
        }
        .sa-login-btn:hover { opacity: .9; }
        .sa-login-error {
            background: color-mix(in srgb, var(--cd-danger) 10%, transparent);
            color: var(--cd-danger); font-size: 13px; font-weight: 500;
            padding: 8px 12px; border-radius: var(--cd-radius); text-align: center;
        }
        .sa-login-footer {
            margin-top: 16px; text-align: center;
        }
        .sa-login-footer a {
            font-size: 13px; color: var(--cd-text-muted); text-decoration: none;
        }
        .sa-login-footer a:hover { color: var(--cd-text); }
    </style>
</head>
<body>
<div class="sa-login">
    <div class="sa-login-card">
        <div class="sa-login-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h1>GeriApp</h1>
        <p>Acceso Superadmin</p>

        <?php if ($error): ?>
            <div class="sa-login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="sa-login-form" method="POST">
            <div>
                <label class="sa-login-label">Usuario <span class="sa-login-required">*</span></label>
                <input class="sa-login-input" name="user" type="text" placeholder="superadmin" required autofocus>
            </div>
            <div>
                <label class="sa-login-label">Contraseña <span class="sa-login-required">*</span></label>
                <input class="sa-login-input" name="pass" type="password" placeholder="••••••••" required>
            </div>
            <button class="sa-login-btn" type="submit">Iniciar Sesión</button>
        </form>

        <div class="sa-login-footer">
            <a href="<?= BASE_URL ?>/index.php">&larr; Volver al login principal</a>
        </div>
    </div>
</div>
<script>
(function(){
    var f = document.querySelector('.sa-login-form');
    if (!f) return;
    var locked = false;
    f.addEventListener('submit', function(){
        if (locked) return;
        locked = true;
        var btn = f.querySelector('.sa-login-btn');
        if (!btn) return;
        btn.disabled = true;
        btn.dataset._orig = btn.textContent;
        btn.style.opacity = '0.75';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:saSpin .7s linear infinite;vertical-align:-2px;margin-right:8px"></span>Ingresando…';
        setTimeout(function(){ if (btn.disabled) { btn.disabled = false; btn.textContent = btn.dataset._orig || 'Iniciar Sesión'; btn.style.opacity = ''; btn.style.cursor = ''; locked = false; } }, 15000);
    });
    var s = document.createElement('style');
    s.textContent = '@keyframes saSpin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
})();
</script>
</body>
</html>
