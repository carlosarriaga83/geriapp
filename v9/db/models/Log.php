<?php
/**
 * GeriApp — Modelo Log del Sistema
 * Tabla: logs_sistema
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Log.php';
 *   Log::registrar(['accion'=>'sesion_iniciar','modulo'=>'auth','usuario_id'=>1]);
 */

require_once __DIR__ . '/../Database.php';
require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Log
{
    private static function existingId(PDO $db, string $table, mixed $id): ?int
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        static $cache = [];
        $key = $table . ':' . $id;
        if (array_key_exists($key, $cache)) return $cache[$key] ? $id : null;
        try {
            $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $cache[$key] = (bool)$stmt->fetchColumn();
            return $cache[$key] ? $id : null;
        } catch (Throwable $e) {
            $cache[$key] = false;
            return null;
        }
    }

    /**
     * Registra una acción en logs_sistema.
     *
     * $data obligatorios: accion (string), modulo (string)
     * $data opcionales: usuario_id, institucion_id, detalle, ip, user_agent, estado
     *
     * Si ip y user_agent no se pasan se toman automáticamente del entorno.
     */
    public static function registrar(array $data): bool
    {
        $db   = Database::getInstance();
        $accion = trim((string)($data['accion'] ?? 'sistema_evento')) ?: 'sistema_evento';
        $modulo = trim((string)($data['modulo'] ?? 'sistema')) ?: 'sistema';
        $usuarioId = self::existingId($db, 'usuarios', $data['usuario_id'] ?? null);
        $institucionId = self::existingId($db, 'instituciones', $data['institucion_id'] ?? null);

        // Resolve defaults before encryption
        $ip = $data['ip']         ?? ($_SERVER['REMOTE_ADDR']     ?? null);
        $ua = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        // §5 Cifrado: encrypt ip/user_agent
        $enc = EncryptionMap::encryptRow('logs_sistema', ['ip' => $ip, 'user_agent' => $ua]);

        try {
            $stmt = $db->prepare(
                "INSERT INTO logs_sistema
                     (usuario_id, institucion_id, accion, modulo, detalle, ip, user_agent, estado)
                 VALUES
                     (:usuario_id, :institucion_id, :accion, :modulo, :detalle, :ip, :user_agent, :estado)"
            );
            $ok = $stmt->execute([
                ':usuario_id'     => $usuarioId,
                ':institucion_id' => $institucionId,
                ':accion'         => $accion,
                ':modulo'         => $modulo,
                ':detalle'        => $data['detalle']        ?? null,
                ':ip'             => $enc['ip'],
                ':user_agent'     => $enc['user_agent'],
                ':estado'         => $data['estado']         ?? 'ok',
            ]);
        } catch (Throwable $e) {
            error_log('[GeriApp] Log no fatal: ' . $e->getMessage());
            return false;
        }

        // §5 Dual-write _enc (active phase)
        if ($ok) {
            try {
                $sets = []; $params = [];
                foreach ($enc as $col => $val) {
                    if (str_ends_with($col, '_enc')) { $sets[] = "`$col` = ?"; $params[] = $val; }
                }
                if (!empty($sets)) {
                    $id = (int) $db->lastInsertId();
                    $params[] = $id;
                    $db->prepare("UPDATE logs_sistema SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                }
            } catch (Throwable $e) { /* _enc columns may not exist yet */ }
        }

        return $ok;
    }

    /**
     * Lista de logs con filtros y paginación.
     *
     * $filtros: usuario_id, institucion_id, modulo, estado, desde (Y-m-d), hasta (Y-m-d), busqueda
     * $limit / $offset: paginación
     */
    public static function getAll(array $filtros = [], int $limit = 100, int $offset = 0): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT l.*,
                       u.nombre AS usuario_nombre,
                       u.email  AS usuario_email,
                       i.nombre AS institucion_nombre
                FROM logs_sistema l
                LEFT JOIN usuarios      u ON u.id = l.usuario_id
                LEFT JOIN instituciones i ON i.id = l.institucion_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $sql .= " AND l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        if (!empty($filtros['institucion_id'])) {
            $sql .= " AND l.institucion_id = ?";
            $params[] = $filtros['institucion_id'];
        }
        if (!empty($filtros['modulo'])) {
            $sql .= " AND l.modulo = ?";
            $params[] = $filtros['modulo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= " AND l.creado_at >= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= " AND l.creado_at <= ?";
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (l.accion LIKE ? OR l.detalle LIKE ? OR l.modulo LIKE ?)";
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        $sql .= " ORDER BY l.creado_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map(
            fn($r) => EncryptionMap::decryptRow('logs_sistema', $r),
            $stmt->fetchAll()
        );
    }

    /**
     * Logs recientes de una institución (atajo para el dashboard).
     */
    public static function getRecent(int $institucion_id, int $limit = 20): array
    {
        return self::getAll(['institucion_id' => $institucion_id], $limit);
    }

    /**
     * Total de registros con los mismos filtros (para paginación).
     */
    public static function count(array $filtros = []): int
    {
        $db  = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM logs_sistema l WHERE 1=1";
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $sql .= " AND l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        if (!empty($filtros['institucion_id'])) {
            $sql .= " AND l.institucion_id = ?";
            $params[] = $filtros['institucion_id'];
        }
        if (!empty($filtros['modulo'])) {
            $sql .= " AND l.modulo = ?";
            $params[] = $filtros['modulo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= " AND l.creado_at >= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $sql .= " AND l.creado_at <= ?";
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Purga logs anteriores a $dias días.
     * Útil para tareas de mantenimiento automatizadas.
     */
    public static function purgeOld(int $dias = 180): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM logs_sistema WHERE creado_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$dias]);
        return $stmt->rowCount();
    }
}
