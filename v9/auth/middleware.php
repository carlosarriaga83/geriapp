<?php
/**
 * GeriApp — Middleware de autenticación
 * Incluir DESPUÉS de config.php en cada página protegida.
 * Para páginas de superadmin: definir $requireSuperadmin = true; antes de incluir.
 */
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/conf/config.php';
}

// ── No autenticado → login ────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    $next    = urlencode($_SERVER['REQUEST_URI'] ?? '');
    $expired = !empty($_SESSION['_just_expired']);
    unset($_SESSION['_just_expired']);
    $qs = [];
    if ($expired) $qs[] = 'expired=1';
    if ($next)    $qs[] = 'next=' . $next;
    header('Location: ' . BASE_URL . '/index.php' . ($qs ? '?' . implode('&', $qs) : ''));
    exit;
}

// ── Cuenta inactiva ────────────────────────────────────────────────────────
if (isset($_SESSION['user_estado']) && $_SESSION['user_estado'] !== 'activo') {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?error=inactivo');
    exit;
}

// ── Solo superadmin ────────────────────────────────────────────────────────
$userRol = $_SESSION['user_rol'] ?? '';
if (!empty($requireSuperadmin) && $userRol !== 'superadmin') {
    header('Location: ' . BASE_URL . '/cuidados.php');
    exit;
}

// ── Roles requeridos (array opcional) ─────────────────────────────────────
if (!empty($requiredRoles) && is_array($requiredRoles)) {
    if (!in_array($userRol, $requiredRoles, true)) {
        header('Location: ' . BASE_URL . '/cuidados.php');
        exit;
    }

}

// ── Validar institución activa para usuarios no-superadmin ────────────────
if ($userRol !== 'superadmin' && empty($_SESSION['sa_impersonating'])) {
    // Si no hay institución seleccionada → selector
    if (empty($_SESSION['user_institucion_id'])) {
        header('Location: ' . BASE_URL . '/select_institucion.php');
        exit;
    }

    // Verificar que el usuario siga teniendo acceso a la institución activa
    // (podría haber sido removido o la institución podría estar suspendida)
    // Solo verificar cada N segundos para no golpear la BD en cada petición
    $instId    = (int)$_SESSION['user_institucion_id'];
    $userId    = (int)$_SESSION['user_id'];
    $lastCheck = $_SESSION['_inst_check_at'] ?? 0;

    if ((time() - $lastCheck) > 120) { // re-verificar cada 2 minutos
        require_once dirname(__DIR__) . '/db/Database.php';
        $db = Database::getMaster();

        // Verificar acceso vía pivot
        $stmt = $db->prepare(
            "SELECT ui.rol, i.estado AS inst_estado
             FROM usuario_instituciones ui
             JOIN instituciones i ON i.id = ui.institucion_id
             WHERE ui.usuario_id = ? AND ui.institucion_id = ? AND ui.estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$userId, $instId]);
        $pivot = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback legacy si no existe en pivot
        if (!$pivot) {
            $stmtL = $db->prepare(
                "SELECT u.rol, i.estado AS inst_estado
                 FROM usuarios u JOIN instituciones i ON i.id = u.institucion_id
                 WHERE u.id = ? AND u.institucion_id = ? LIMIT 1"
            );
            $stmtL->execute([$userId, $instId]);
            $pivot = $stmtL->fetch(PDO::FETCH_ASSOC);
        }

        if (!$pivot || in_array($pivot['inst_estado'], ['suspendida', 'archivada'], true)) {
            // Institución suspendida/archivada o acceso revocado → forzar re-selección
            $_SESSION['user_institucion_id']     = null;
            $_SESSION['user_institucion_nombre'] = '';
            unset($_SESSION['_inst_check_at']);
            header('Location: ' . BASE_URL . '/select_institucion.php?reason=access_revoked');
            exit;
        }

        // Actualizar rol (podría haber cambiado)
        $_SESSION['user_rol']       = $pivot['rol'];
        $_SESSION['_inst_check_at'] = time();

        // Actualizar sesión activa (ultimo_acceso)
        try {
            $db->prepare("UPDATE sesiones_activas SET ultimo_acceso = NOW(), institucion_id = ? WHERE session_id = ?")
               ->execute([$instId, session_id()]);
        } catch (\Throwable $e) { /* table may not exist yet */ }
    }
}

if (!empty($_SESSION['billing_registration_card_required']) && in_array($userRol, ['admin', 'superadmin'], true)) {
    $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $instId = (int)($_SESSION['user_institucion_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($instId > 0 && $userId > 0) {
        require_once dirname(__DIR__) . '/db/Database.php';
        $db = Database::getMaster();
        $stmt = $db->prepare(
            "SELECT s.plan_id, s.stripe_customer_id, s.stripe_subscription_id, p.solicita_tarjeta_registro
             FROM suscripciones s
             JOIN planes p ON p.id = s.plan_id
             JOIN suscripcion_instituciones si ON si.suscripcion_id = s.id AND si.institucion_id = ?
             WHERE s.owner_user_id = ?
             ORDER BY s.id DESC
             LIMIT 1"
        );
        $stmt->execute([$instId, $userId]);
        $pendingBilling = $stmt->fetch(PDO::FETCH_ASSOC);
        $needsCard = $pendingBilling
            && (int)($pendingBilling['solicita_tarjeta_registro'] ?? 0) === 1
            && empty($pendingBilling['stripe_customer_id'])
            && empty($pendingBilling['stripe_subscription_id']);
        if ($needsCard && $scriptName !== 'billing.php') {
            header('Location: ' . BASE_URL . '/billing.php?register_checkout=1&plan_id=' . (int)$pendingBilling['plan_id'] . '&institucion_id=' . $instId);
            exit;
        }
        if (!$needsCard) {
            unset($_SESSION['billing_registration_card_required']);
        }
    }
}
