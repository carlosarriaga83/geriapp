<?php
/**
 * GeriApp — Selector de institución
 * Se muestra cuando un usuario pertenece a más de una institución.
 * Requiere que la sesión tenga pending_instituciones o user_id activo.
 */
require_once 'conf/config.php';

// Debe existir sesión de usuario
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Superadmin no necesita seleccionar institución
if (($_SESSION['user_rol'] ?? '') === 'superadmin') {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

// Obtener lista: puede venir de la sesión (login) o del pivot en tiempo real
$instituciones = $_SESSION['pending_instituciones'] ?? [];
if (empty($instituciones)) {
    // Reconstruir desde BD
    require_once __DIR__ . '/db/Database.php';
    $db = Database::getMaster();
    $stmt = $db->prepare(
        "SELECT ui.institucion_id, ui.rol, i.nombre AS inst_nombre,
                i.estado AS inst_estado, i.logo_path
         FROM usuario_instituciones ui
         JOIN instituciones i ON i.id = ui.institucion_id
         WHERE ui.usuario_id = ?
           AND ui.estado = 'activo'
           AND i.estado NOT IN ('suspendida','archivada')
         ORDER BY i.nombre ASC"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($instituciones)) {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?reason=sin_institucion');
    exit;
}

// Stats extra para la UI (número de residentes por institución)
require_once __DIR__ . '/db/Database.php';
$db = Database::getMaster();
foreach ($instituciones as &$inst) {
    $s = $db->prepare("SELECT COUNT(*) FROM residentes WHERE institucion_id = ? AND estado = 'activo'");
    $s->execute([$inst['institucion_id']]);
    $inst['residentes_activos'] = (int) $s->fetchColumn();
}
unset($inst);

$pageTitle = 'Seleccionar Institución';
$bodyClass = 'login-page';

// Mensajes según reason
$alertMsg = '';
$reason   = $_GET['reason'] ?? '';
if ($reason === 'access_revoked')    $alertMsg = 'Tu acceso a esa institución fue revocado o está suspendida.';
if ($reason === 'sin_institucion')   $alertMsg = 'Tu usuario no pertenece a ninguna institución activa.';

require_once 'includes/head.php';
?>

<div class="login-outer">
    <div class="login-card" style="max-width:480px;">

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
            <h1 class="login-app-name" style="margin-bottom:4px;">Seleccionar institución</h1>
            <p class="login-app-sub">
                Hola, <strong><?= htmlspecialchars($_SESSION['user_nombre'] ?? '') ?></strong>.
                Elige con qué institución quieres trabajar.
            </p>
        </div>

        <!-- Alerta de razón de redirección -->
        <?php if ($alertMsg): ?>
        <div class="login-alert login-alert--error" style="margin-bottom:12px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($alertMsg) ?>
        </div>
        <?php endif; ?>

        <!-- Lista de instituciones -->
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px;">
            <?php foreach ($instituciones as $inst): ?>
            <?php
                $rolLabels = ['admin'=>'Administrador','medico'=>'Médico/a','enfermero'=>'Cuidador/a','cuidador'=>'Cuidador/a','familiar'=>'Familiar'];
                $rolLabel  = $rolLabels[$inst['rol']] ?? ucfirst($inst['rol']);
            ?>
            <button
                class="inst-btn"
                onclick="seleccionar(<?= (int)$inst['institucion_id'] ?>)"
                style="display:flex;align-items:center;gap:14px;width:100%;padding:14px 16px;
                       background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;
                       cursor:pointer;text-align:left;transition:border-color .2s,box-shadow .2s;"
                onmouseover="this.style.borderColor='#178391';this.style.boxShadow='0 0 0 3px rgba(23,131,145,.1)'"
                onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">

                <!-- Ícono / Logo -->
                <div style="flex-shrink:0;width:44px;height:44px;border-radius:10px;
                             background:#e0f2fe;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <?php if (!empty($inst['logo_path'])): ?>
                    <img src="<?= htmlspecialchars($inst['logo_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#178391" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:14px;color:#1e293b;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($inst['inst_nombre']) ?>
                    </div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">
                        <?= $rolLabel ?> &bull; <?= (int)$inst['residentes_activos'] ?> residente(s) activo(s)
                    </div>
                </div>

                <!-- Flecha -->
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="#94a3b8" stroke-width="2.5" style="flex-shrink:0;">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Spinner -->
        <div id="spinner" style="display:none;text-align:center;padding:20px;color:#64748b;font-size:13px;">
            <div style="width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#178391;
                        border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 8px;"></div>
            Conectando...
        </div>

        <!-- Error -->
        <div id="selErr" style="display:none;margin-top:12px;" class="login-alert login-alert--error"></div>

        <!-- Logout -->
        <div style="text-align:center;margin-top:20px;border-top:1px solid #f1f5f9;padding-top:16px;">
            <a href="<?= BASE_URL ?>/auth/logout.php"
               style="font-size:12px;color:#94a3b8;text-decoration:none;">
                ← Cerrar sesión y volver al inicio
            </a>
        </div>

    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
let _selecting = false;
async function seleccionar(instId) {
    if (_selecting) return;
    _selecting = true;
    document.querySelectorAll('.inst-btn').forEach(b => b.disabled = true);
    document.getElementById('spinner').style.display = 'block';
    document.getElementById('selErr').style.display  = 'none';

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/switch_institucion.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ inst_id: instId }),
        });
        const json = await res.json();
        if (json.success) {
            window.location.href = '<?= BASE_URL ?>/cuidados.php';
        } else {
            throw new Error(json.message || 'Error al seleccionar institución');
        }
    } catch (err) {
        document.getElementById('spinner').style.display = 'none';
        document.querySelectorAll('.inst-btn').forEach(b => b.disabled = false);
        const errEl = document.getElementById('selErr');
        errEl.textContent = err.message;
        errEl.style.display = 'block';
        _selecting = false;
    }
}
</script>

<?php require_once 'includes/foot.php'; ?>
