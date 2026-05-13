<?php
/**
 * GeriApp — Modelo Seats (lógica de cupos)
 *
 * Centraliza el cálculo y enforcement de límites de plan + asientos extra:
 *   max_residentes  + seats_residente_extra  (de la suscripción)
 *   max_familiares  + seats_familiar_extra
 *
 * Reglas:
 *   - Solo aplica enforcement cuando la suscripción está en estado 'trial'
 *     o 'activa'. En 'past_due' el cron de F8 decidirá; en 'cancelada' /
 *     'incompleta' / 'pausada' el módulo de instituciones se encarga
 *     (típicamente bloquea acceso completo, no solo cupo).
 *   - Si la institución NO tiene suscripción asociada (modo legacy o trial
 *     pre-billing), NO bloqueamos — devolvemos true.
 *   - Familiares se cuentan en master.usuarios (rol='familiar') por
 *     institucion_id. Residentes se cuentan en master.residentes (este
 *     proyecto opera todo en master por ahora).
 *
 * Excepción:
 *   SeatLimitException con código que distingue 'residente' o 'familiar'
 *   y mensaje listo para mostrar al usuario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

class SeatLimitException extends \RuntimeException
{
    public string $tipo;       // 'residente' | 'familiar'
    public int    $usados;
    public int    $limite;
    public int    $extra;

    public function __construct(string $tipo, int $usados, int $limite, int $extra)
    {
        $this->tipo   = $tipo;
        $this->usados = $usados;
        $this->limite = $limite;
        $this->extra  = $extra;
        $tipoLabel = $tipo === 'residente' ? 'residentes' : 'familiares';
        parent::__construct(
            "Has alcanzado el límite de {$tipoLabel} de tu plan ({$usados}/" .
            ($limite + $extra) . "). Compra un asiento extra para continuar.",
            403
        );
    }
}

class Seats
{
    /**
     * Obtiene la suscripción ACTIVA (trial|activa|past_due) que cubre la
     * institución dada. Retorna null si no hay suscripción vinculada.
     */
    public static function getActiveSubForInst(int $instId): ?array
    {
        if ($instId <= 0) return null;
        $db = Database::getMaster();
        $st = $db->prepare(
            "SELECT s.id, s.plan_id, s.estado, s.seats_residente_extra, s.seats_familiar_extra,
                    p.max_residentes, p.max_familiares, p.max_instituciones, p.nombre AS plan_nombre
             FROM suscripcion_instituciones si
             JOIN suscripciones s ON s.id = si.suscripcion_id
             LEFT JOIN planes p ON p.id = s.plan_id
             WHERE si.institucion_id = ?
               AND s.estado IN ('trial','activa','past_due')
             ORDER BY FIELD(s.estado,'activa','trial','past_due'), s.id DESC
             LIMIT 1"
        );
        $st->execute([$instId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Cuenta residentes activos en master.residentes para la institución. */
    public static function countResidentes(int $instId): int
    {
        $db = Database::getMaster();
        $st = $db->prepare("SELECT COUNT(*) FROM residentes WHERE institucion_id = ? AND estado = 'activo'");
        $st->execute([$instId]);
        return (int)$st->fetchColumn();
    }

    /** Cuenta usuarios familiares activos en master.usuarios para la institución. */
    public static function countFamiliares(int $instId): int
    {
        $db = Database::getMaster();
        // Modelo multi-tenant: vinculación vía pivot usuario_instituciones.
        // Fallback legacy: usuarios.institucion_id directo si el pivot no aporta filas.
        $st = $db->prepare(
            "SELECT COUNT(DISTINCT ui.usuario_id)
             FROM usuario_instituciones ui
             JOIN usuarios u ON u.id = ui.usuario_id
             WHERE ui.institucion_id = ?
               AND ui.rol = 'familiar'
               AND ui.estado = 'activo'
               AND u.estado = 'activo'"
        );
        $st->execute([$instId]);
        $n = (int)$st->fetchColumn();
        if ($n > 0) return $n;
        $st2 = $db->prepare(
            "SELECT COUNT(*) FROM usuarios
             WHERE institucion_id = ? AND rol = 'familiar' AND estado = 'activo'"
        );
        $st2->execute([$instId]);
        return (int)$st2->fetchColumn();
    }

    /**
     * Lanza SeatLimitException si crear un nuevo residente excedería el cupo.
     * Si no hay suscripción o la suscripción no está en trial/activa, no hace nada.
     */
    public static function assertCanAddResidente(int $instId): void
    {
        self::assertCanAddResidentes($instId, 1);
    }

    /**
     * Variante batch: valida ANTES de un import masivo.
     * Si la cantidad pedida no cabe, lanza con el detalle de cuántos faltan.
     */
    public static function assertCanAddResidentes(int $instId, int $cantidad): void
    {
        if ($cantidad <= 0) return;
        $sub = self::getActiveSubForInst($instId);
        if (!$sub) return;
        if (!in_array($sub['estado'], ['trial','activa'], true)) return;
        if ($sub['max_residentes'] === null) return;

        $usados = self::countResidentes($instId);
        $limite = (int)$sub['max_residentes'];
        $extra  = (int)$sub['seats_residente_extra'];
        if (($usados + $cantidad) > ($limite + $extra)) {
            throw new SeatLimitException('residente', $usados, $limite, $extra);
        }
    }

    /**
     * Lanza SeatLimitException si aceptar un nuevo familiar excedería el cupo.
     */
    public static function assertCanAddFamiliar(int $instId): void
    {
        $sub = self::getActiveSubForInst($instId);
        if (!$sub) return;
        if (!in_array($sub['estado'], ['trial','activa'], true)) return;
        if ($sub['max_familiares'] === null) return;

        $usados = self::countFamiliares($instId);
        $limite = (int)$sub['max_familiares'];
        $extra  = (int)$sub['seats_familiar_extra'];
        if ($usados >= ($limite + $extra)) {
            throw new SeatLimitException('familiar', $usados, $limite, $extra);
        }
    }

    /**
     * Refresca la fila de seats_uso para (suscripcion_id, institucion_id).
     * Idempotente: usa INSERT … ON DUPLICATE KEY UPDATE.
     * Llamado por el cron de F8 (recalc_seats.php) y por cualquier acción
     * que sume/reste residentes o familiares.
     */
    public static function recalcUsage(int $suscripcionId, int $instId): void
    {
        if ($suscripcionId <= 0 || $instId <= 0) return;
        $r = self::countResidentes($instId);
        $f = self::countFamiliares($instId);
        $db = Database::getMaster();
        $db->prepare(
            "INSERT INTO seats_uso (suscripcion_id, institucion_id, residentes_count, familiares_count)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                residentes_count = VALUES(residentes_count),
                familiares_count = VALUES(familiares_count)"
        )->execute([$suscripcionId, $instId, $r, $f]);
    }

    /**
     * Atajo: refresca cache para todas las parejas (suscripcion, institucion)
     * de las instituciones del owner. Útil tras crear/eliminar masivos.
     */
    public static function recalcUsageForInst(int $instId): void
    {
        $sub = self::getActiveSubForInst($instId);
        if (!$sub) return;
        self::recalcUsage((int)$sub['id'], $instId);
    }
}
