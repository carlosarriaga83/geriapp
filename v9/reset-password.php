<?php
require_once 'conf/config.php';

// Ya autenticado → redirigir
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

require_once 'db/Database.php';
$db = Database::getInstance();

$token    = trim($_GET['token'] ?? '');
$error    = '';
$rpInfo   = '';
$tokenRow = null;

if (isset($_GET['csrf_expired'])) { $rpInfo = 'Tu sesión expiró. Se ha renovado el formulario automáticamente.'; }

// Validar token
if ($token) {
    $stmt = $db->prepare(
        "SELECT * FROM password_resets
         WHERE token = ? AND usado = 0 AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch();
}

if (!$token || !$tokenRow) {
    $error = 'El enlace de recuperación no es válido o ha expirado.';
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow) {
    // §1.9 Validar CSRF
    if (!csrf_validate($_POST['_csrf'] ?? '')) {
        header('Location: ' . $_SERVER['REQUEST_URI'] . (str_contains($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'csrf_expired=1');
        exit;
    } else {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    // Load security config from the user's institution
    $_rpInstId = 0;
    try {
        $uStmt = $db->prepare("SELECT institucion_id FROM usuarios WHERE email = ? LIMIT 1");
        $uStmt->execute([$tokenRow['email']]);
        $_rpInstId = (int)($uStmt->fetchColumn() ?: 0);
    } catch (\Throwable $e) {}
    $_rpPassLen = 8;
    if ($_rpInstId) {
        require_once 'db/models/Configuracion.php';
        $_rpCfg = Configuracion::getByInstitucion($_rpInstId) ?: [];
        $_rpPassLen = max(4, (int)($_rpCfg['seg_pass_min_len'] ?? 8));
    }

    if ($pwErr = validate_password_strength($pass1, $_rpPassLen)) {
        $error = $pwErr;
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($pass1, PASSWORD_BCRYPT, ['cost' => 12]);

        // Actualizar contraseña
        $db->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?")
           ->execute([$hash, $tokenRow['email']]);

        // Marcar token como usado
        $db->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?")
           ->execute([$token]);

        // Registrar en logs si existe la tabla
        try {
            $u = $db->prepare("SELECT id, institucion_id FROM usuarios WHERE email = ? LIMIT 1");
            $u->execute([$tokenRow['email']]);
            $usr = $u->fetch();
            if ($usr) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $db->prepare("INSERT INTO logs_sistema (usuario_id, institucion_id, accion, modulo, ip, estado)
                              VALUES (?,?,'password_restablecer','Auth',?,'ok')")
                   ->execute([$usr['id'], $usr['institucion_id'], $ip]);
            }
        } catch (\Throwable $e) { /* no crítico */ }

        header('Location: ' . BASE_URL . '/index.php?reset=1');
        exit;
    }
    } // end else (CSRF válido)
}

$pageTitle = 'Nueva Contraseña';
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
            <p class="login-app-sub">Crea tu nueva contraseña</p>
        </div>

        <?php if ($error && !$tokenRow): ?>
        <!-- Token inválido / expirado -->
        <div class="login-alert login-alert--error" style="flex-direction:column;align-items:flex-start;gap:6px;">
            <div style="display:flex;align-items:center;gap:9px;font-weight:600;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Enlace no válido
            </div>
            <p style="margin:0;font-size:12.5px;line-height:1.5;">
                <?= htmlspecialchars($error) ?><br>
                Solicita un nuevo enlace de recuperación.
            </p>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <a href="<?= BASE_URL ?>/forgot-password.php" class="login-btn"
               style="display:inline-block;text-decoration:none;line-height:1;padding:11px 28px;font-size:14px;">
                Solicitar nuevo enlace
            </a>
        </div>

        <?php else: ?>

        <?php if ($rpInfo): ?>
        <div class="login-alert login-alert--info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= htmlspecialchars($rpInfo) ?>
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
            Ingresa y confirma tu nueva contraseña. Debe tener al menos 8 caracteres.
        </p>

        <form action="<?= BASE_URL ?>/reset-password.php?token=<?= urlencode($token) ?>"
              method="post" autocomplete="off" class="login-form">
            <?= csrf_field() ?>

            <!-- Nueva contraseña -->
            <div class="login-field">
                <label for="password">Nueva Contraseña <span class="login-required">*</span></label>
                <div class="login-input-wrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password"
                           class="login-input" placeholder="Mínimo 8 caracteres" required autofocus>
                    <button type="button" class="login-eye-btn" id="togglePass1" tabindex="-1">
                        <svg id="eyeIcon1" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <!-- Barra de fuerza -->
                <div class="reg-strength" style="margin-top:7px;">
                    <div class="reg-strength-track">
                        <div class="reg-strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="reg-strength-label" id="strengthLabel"></span>
                </div>
                <!-- Checklist de requisitos -->
                <ul class="reg-pw-reqs" id="rpPwReqs">
                    <li data-req="len"><span class="reg-pw-ico">○</span> Mínimo 8 caracteres</li>
                    <li data-req="upper"><span class="reg-pw-ico">○</span> 1 mayúscula</li>
                    <li data-req="lower"><span class="reg-pw-ico">○</span> 1 minúscula</li>
                    <li data-req="digit"><span class="reg-pw-ico">○</span> 1 número</li>
                    <li data-req="special"><span class="reg-pw-ico">○</span> 1 carácter especial</li>
                </ul>
            </div>

            <!-- Confirmar contraseña -->
            <div class="login-field">
                <label for="password2">Confirmar Contraseña <span class="login-required">*</span></label>
                <div class="login-input-wrap" id="confirmWrap">
                    <span class="login-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password2" name="password2"
                           class="login-input" placeholder="Repite la contraseña" required>
                    <button type="button" class="login-eye-btn" id="togglePass2" tabindex="-1">
                        <svg id="eyeIcon2" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="reg-match-msg" id="matchMsg"></div>
                <div class="reg-pw-reqs reg-pw-reqs-match" id="rpPwMatch">
                    <li data-req="match"><span class="reg-pw-ico">○</span> Contraseñas coinciden</li>
                </div>
            </div>

            <button type="submit" class="login-btn" id="submitBtn">Guardar nueva contraseña</button>

        </form>

        <?php endif; ?>

        <div class="login-links" style="margin-top:18px;">
            <p><a href="<?= BASE_URL ?>/index.php" class="login-back">← Volver al inicio de sesión</a></p>
        </div>

    </div>
</div>

<script>
// Eye toggles
function makeEyeToggle(btnId, inputId, iconId) {
    document.getElementById(btnId)?.addEventListener('click', function () {
        const inp  = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const show = inp.type === 'password';
        inp.type   = show ? 'text' : 'password';
        icon.innerHTML = show
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    });
}
makeEyeToggle('togglePass1', 'password',  'eyeIcon1');
makeEyeToggle('togglePass2', 'password2', 'eyeIcon2');

// Checklist de requisitos + barra de fuerza
function rpCheckPwReqs() {
    const val  = document.getElementById('password').value;
    const val2 = document.getElementById('password2').value;
    const fill = document.getElementById('strengthFill');
    const lbl  = document.getElementById('strengthLabel');
    const checks = {
        len:     val.length >= 8,
        upper:   /[A-Z]/.test(val),
        lower:   /[a-z]/.test(val),
        digit:   /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val),
        match:   val.length > 0 && val === val2,
    };
    document.querySelectorAll('#rpPwReqs li, #rpPwMatch li').forEach(li => {
        const key = li.dataset.req;
        const ok  = checks[key];
        const ico = li.querySelector('.reg-pw-ico');
        const wasOk = li.classList.contains('req-pass');
        if (ok && !wasOk) {
            li.classList.add('req-pass');
            li.classList.remove('req-fail');
            ico.textContent = '●';
        } else if (!ok && wasOk) {
            li.classList.remove('req-pass');
            li.classList.add('req-fail');
            ico.textContent = '○';
        } else if (!ok && !wasOk) {
            li.classList.remove('req-pass', 'req-fail');
            ico.textContent = '○';
        }
    });
    let score = 0;
    if (checks.len)     score++;
    if (checks.upper)   score++;
    if (checks.lower)   score++;
    if (checks.digit)   score++;
    if (checks.special) score++;
    const levels = [
        { pct: '0%',   color: '',        text: '' },
        { pct: '20%',  color: '#dc2626', text: 'Muy débil' },
        { pct: '40%',  color: '#dc2626', text: 'Débil' },
        { pct: '60%',  color: '#d97706', text: 'Regular' },
        { pct: '80%',  color: '#178391', text: 'Buena' },
        { pct: '100%', color: '#16a34a', text: 'Fuerte' },
    ];
    const lvl = levels[score];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    lbl.textContent       = lvl.text;
    lbl.style.color       = lvl.color;
}
document.getElementById('password')?.addEventListener('input', rpCheckPwReqs);

// Coincidencia de contraseñas
document.getElementById('password2')?.addEventListener('input', function () {
    rpCheckPwReqs();
    const p1   = document.getElementById('password').value;
    const msg  = document.getElementById('matchMsg');
    const wrap = document.getElementById('confirmWrap');
    if (!this.value) { msg.textContent = ''; wrap.style.borderColor = ''; return; }
    if (this.value === p1) {
        msg.textContent    = '✓ Las contraseñas coinciden';
        msg.style.color    = '#16a34a';
        wrap.style.borderColor = '#16a34a';
    } else {
        msg.textContent    = '✗ Las contraseñas no coinciden';
        msg.style.color    = '#dc2626';
        wrap.style.borderColor = '#dc2626';
    }
});

// ── Anti doble-envío (bouncing) ──────────────────────────────
(function(){
    var form = document.querySelector('form.login-form');
    if (!form) return;
    var locked = false;
    form.addEventListener('submit', function(){
        if (locked) return;
        locked = true;
        var btn = document.getElementById('submitBtn');
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.style.opacity = '0.75';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:loginSpin .7s linear infinite;vertical-align:-2px;margin-right:8px"></span>Guardando…';
        setTimeout(function(){ if (btn.disabled) { btn.disabled = false; btn.innerHTML = orig; btn.style.opacity = ''; btn.style.cursor = ''; locked = false; } }, 15000);
    });
    if (!document.getElementById('_loginSpinKf')) {
        var s = document.createElement('style'); s.id = '_loginSpinKf';
        s.textContent = '@keyframes loginSpin{to{transform:rotate(360deg)}}';
        document.head.appendChild(s);
    }
})();
</script>

<?php require_once 'includes/foot.php'; ?>
