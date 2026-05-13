<?php
/**
 * GeriApp — Modelo Invitacion
 * Tabla: invitaciones
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Invitacion.php';
 *   $invs = Invitacion::getAll($institucion_id);
 */

require_once __DIR__ . '/../Database.php';

class Invitacion
{
    private static function validUserId(PDO $db, mixed $userId): ?int
    {
        $userId = (int)$userId;
        if ($userId <= 0) return null;
        static $cache = [];
        if (array_key_exists($userId, $cache)) return $cache[$userId] ? $userId : null;
        try {
            $stmt = $db->prepare('SELECT 1 FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $cache[$userId] = (bool)$stmt->fetchColumn();
            return $cache[$userId] ? $userId : null;
        } catch (Throwable $e) {
            $cache[$userId] = false;
            return null;
        }
    }

    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Lista todas las invitaciones de una institución.
     * Las invitaciones normales no expiran automáticamente; se eliminan manualmente.
     */
    public static function getAll(int $institucion_id): array
    {
        $db = Database::getInstance();
        $columns = self::tableColumns($db, 'invitaciones') ?? self::defaultColumns();

        $stmt = $db->prepare(
            "SELECT i.*, u.nombre AS creado_por_nombre
             FROM invitaciones i
             LEFT JOIN usuarios u ON u.id = i.creado_por
             WHERE i.institucion_id = ?
             ORDER BY i.creado_at DESC"
        );
        $stmt->execute([$institucion_id]);
        return array_map([self::class, 'normalizeRow'], $stmt->fetchAll());
    }

    /**
     * Obtiene una invitación por ID, verificando que pertenezca a la institución.
     */
    public static function getById(int $id, int $institucion_id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM invitaciones WHERE id = ? AND institucion_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $institucion_id]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : false;
    }

    /**
     * Obtiene una invitación por token (para el flujo de registro).
     */
    public static function getByToken(string $token): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM invitaciones WHERE token = ? LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : false;
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea una nueva invitación.
     *
     * @param array{
     *   institucion_id: int,
     *   email:          string,
     *   rol:            string,
     *   mensaje:        string|null,
     *   dias:           int,
     *   creado_por:     int
     * } $data
     * @return array{id:int, token:string}|false
     */
    public static function create(array $data): array|false
    {
        $db    = Database::getInstance();
        $token = bin2hex(random_bytes(32));   // 64 hex chars
        $dias  = max(1, (int)($data['dias'] ?? 7));
        $multiUso = !empty($data['multi_uso']) ? 1 : 0;
        $permiteNuevaInst = !empty($data['permite_nueva_institucion']) ? 1 : 0;
        $diasPrueba = isset($data['dias_prueba']) ? max(0, min(365, (int)$data['dias_prueba'])) : null;
        if ($diasPrueba === 0) $diasPrueba = null; // 0 = sin trial explícito
        $emailValue = self::emailValue($data['email'] ?? null, $token);

        $columns = self::tableColumns($db, 'invitaciones') ?? self::defaultColumns();
        $creatorId = self::validUserId($db, $data['creado_por'] ?? null);
        $values = [
            'institucion_id' => (int)$data['institucion_id'],
            'email' => $emailValue,
            'rol' => $data['rol'],
            'token' => $token,
            'estado' => 'pendiente',
            'mensaje' => $data['mensaje'] ?? null,
            'residente_ids' => !empty($data['residente_ids']) ? json_encode(array_map('intval', $data['residente_ids'])) : null,
            'nombre_sugerido' => $data['nombre_sugerido'] ?? null,
            'apellido_sugerido' => $data['apellido_sugerido'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'creado_por' => $creatorId,
            'multi_uso' => $multiUso,
            'usos_count' => 0,
            'permite_nueva_institucion' => $permiteNuevaInst,
            'dias_prueba' => $diasPrueba,
        ];
        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach ($values as $col => $value) {
            if (!isset($columns[$col])) continue;
            $insertCols[] = "`{$col}`";
            $placeholders[] = ':' . $col;
            $params[':' . $col] = $value;
        }
        if (isset($columns['expires_at'])) {
            $insertCols[] = '`expires_at`';
            $placeholders[] = ':expires_at';
            $params[':expires_at'] = '9999-12-31 23:59:59';
        }
        if (!isset($columns['institucion_id'], $columns['email'], $columns['rol'], $columns['token'])) {
            return false;
        }
        $stmt = $db->prepare(
            "INSERT INTO invitaciones (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")"
        );
        try {
            $ok = $stmt->execute($params);
        } catch (Throwable $e) {
            error_log('[GeriApp] Invitacion create dynamic insert fallback: ' . $e->getMessage());
            $ok = self::createMinimal($db, $data, $token, $dias, true);
            if (!$ok) $ok = self::createMinimal($db, $data, $token, $dias, false);
        }
        if (!$ok) return false;
        return ['id' => (int)$db->lastInsertId(), 'token' => $token];
    }

    private static function createMinimal(PDO $db, array $data, string $token, int $dias, bool $withCreator): bool
    {
        $columns = self::tableColumns($db, 'invitaciones') ?? self::defaultColumns();
        $email = self::emailValue($data['email'] ?? null, $token);
        $creatorId = self::validUserId($db, $data['creado_por'] ?? null);
        try {
            $cols = [];
            $vals = [];
            $params = [
                ':inst' => (int)$data['institucion_id'],
                ':email' => $email,
                ':rol' => $data['rol'],
                ':token' => $token,
            ];
            foreach (['institucion_id' => ':inst', 'email' => ':email', 'rol' => ':rol', 'token' => ':token'] as $col => $param) {
                if (!isset($columns[$col])) return false;
                $cols[] = $col;
                $vals[] = $param;
            }
            if (isset($columns['estado'])) {
                $cols[] = 'estado';
                $vals[] = "'pendiente'";
            }
            if (isset($columns['mensaje'])) {
                $cols[] = 'mensaje';
                $vals[] = ':msg';
                $params[':msg'] = $data['mensaje'] ?? null;
            }
            if (isset($columns['expires_at'])) {
                $cols[] = 'expires_at';
                $vals[] = ':expires_at';
                $params[':expires_at'] = '9999-12-31 23:59:59';
            }
            if (isset($columns['creado_por'])) {
                $cols[] = 'creado_por';
                $vals[] = ':usr';
                $params[':usr'] = $withCreator ? $creatorId : null;
            }
            $stmt = $db->prepare(
                "INSERT INTO invitaciones (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")"
            );
            return $stmt->execute($params);
        } catch (Throwable $e) {
            error_log('[GeriApp] Invitacion create minimal failed' . ($withCreator ? ' with creator' : ' without creator') . ': ' . $e->getMessage());
            return false;
        }
    }

    private static function emailValue(mixed $email, string $token): string
    {
        $email = strtolower(trim((string)$email));
        if ($email !== '') return $email;
        return 'sin-correo-' . substr($token, 0, 16) . '@geriapp.local';
    }

    private static function normalizeRow(array $row): array
    {
        if (isset($row['email']) && preg_match('/^sin-correo-[a-f0-9]{16}@geriapp\.local$/i', (string)$row['email'])) {
            $row['email'] = '';
        }
        return $row;
    }

    private static function tableColumns(PDO $db, string $table): ?array
    {
        static $cache = [];
        $key = $table;
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `{$table}`");
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!empty($row['Field'])) $cols[$row['Field']] = true;
            }
            return $cache[$key] = $cols;
        } catch (Throwable $e) {
            return $cache[$key] = null;
        }
    }

    /**
    * Reenvía una invitación: renueva token y restablece estado a pendiente.
     * Retorna el nuevo token o false en fallo.
     */
    public static function resend(int $id, int $institucion_id, int $dias = 7): string|false
    {
        $db    = Database::getInstance();
        $token = bin2hex(random_bytes(32));
        $columns = self::tableColumns($db, 'invitaciones') ?? self::defaultColumns();
        $sets = ['token = :token'];
        $params = [':token' => $token, ':id' => $id, ':inst' => $institucion_id];
        if (isset($columns['estado'])) $sets[] = "estado = 'pendiente'";
        if (isset($columns['creado_at'])) $sets[] = 'creado_at = NOW()';
        if (isset($columns['expires_at'])) {
            $sets[] = 'expires_at = :expires_at';
            $params[':expires_at'] = '9999-12-31 23:59:59';
        }
        $where = 'id = :id AND institucion_id = :inst';
        if (isset($columns['estado'])) $where .= " AND estado != 'aceptada'";
        $stmt  = $db->prepare(
            "UPDATE invitaciones SET " . implode(', ', $sets) . " WHERE {$where}"
        );
        $ok = $stmt->execute($params);
        return ($ok && $stmt->rowCount() > 0) ? $token : false;
    }

    /**
     * Revoca (marca como expirada) una invitación pendiente.
     */
    public static function revoke(int $id, int $institucion_id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE invitaciones
             SET estado = 'expirada'
             WHERE id = ? AND institucion_id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$id, $institucion_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina físicamente una invitación no aceptada.
     */
    public static function delete(int $id, int $institucion_id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM invitaciones
             WHERE id = ? AND institucion_id = ? AND estado != 'aceptada'"
        );
        $stmt->execute([$id, $institucion_id]);
        return $stmt->rowCount() > 0;
    }

    /**
    * Actualiza datos editables (email, telefono, rol, nombre/apellido sugerido, mensaje)
    * de una invitación no aceptada.
     */
    public static function updateData(int $id, int $institucion_id, array $data): bool
    {
        $db   = Database::getInstance();
        $columns = self::tableColumns($db, 'invitaciones') ?? self::defaultColumns();
        $allowed = [
            'email' => ($data['email'] ?? '') !== '' ? $data['email'] : null,
            'telefono' => ($data['telefono'] ?? '') !== '' ? $data['telefono'] : null,
            'rol' => ($data['rol'] ?? '') !== '' ? $data['rol'] : null,
            'nombre_sugerido' => ($data['nombre_sugerido'] ?? '') !== '' ? $data['nombre_sugerido'] : null,
            'apellido_sugerido' => ($data['apellido_sugerido'] ?? '') !== '' ? $data['apellido_sugerido'] : null,
            'mensaje' => ($data['mensaje'] ?? '') !== '' ? $data['mensaje'] : null,
        ];
        $sets = [];
        $params = [':id' => $id, ':inst' => $institucion_id];
        foreach ($allowed as $col => $value) {
            if (!isset($columns[$col])) continue;
            $param = ':' . $col;
            $sets[] = "`{$col}` = {$param}";
            $params[$param] = $value;
        }
        if (empty($sets)) return true;
        $where = 'id = :id AND institucion_id = :inst';
        if (isset($columns['estado'])) $where .= " AND estado != 'aceptada'";
        $stmt = $db->prepare(
            "UPDATE invitaciones SET " . implode(', ', $sets) . " WHERE {$where}"
        );
        $ok = $stmt->execute($params);
        return $ok;
    }

    private static function defaultColumns(): array
    {
        return [
            'id' => true,
            'institucion_id' => true,
            'email' => true,
            'rol' => true,
            'token' => true,
            'estado' => true,
            'mensaje' => true,
            'expires_at' => true,
            'creado_at' => true,
            'creado_por' => true,
        ];
    }

    /**
     * Marca una invitación como aceptada (llamado desde register.php al completar el registro).
     *
     * Comportamiento según el modo:
     *  - Single-use (multi_uso = 0, default): cambia estado a 'aceptada' y
     *    el token queda inutilizable. Modo recomendado para HIPAA/LFPDPPP.
     *  - Multi-uso (multi_uso = 1): NO cambia el estado; solo incrementa
    *    usos_count para auditoría. El token continúa válido hasta que
    *    un admin lo elimine/revoque manualmente. Pensado para
     *    convenciones / eventos promocionales.
     */
    public static function markAccepted(string $token): bool
    {
        $db = Database::getInstance();

        // Detectar el modo de la invitación para no romper instalaciones
        // antiguas que todavía no tengan la columna multi_uso (la query
        // try/catch fall-back a single-use clasico).
        $multi = 0;
        try {
            $st = $db->prepare("SELECT multi_uso FROM invitaciones WHERE token = ? LIMIT 1");
            $st->execute([$token]);
            $multi = (int)($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) { $multi = 0; }

        if ($multi === 1) {
            $stmt = $db->prepare(
                "UPDATE invitaciones
                    SET usos_count = usos_count + 1
                                    WHERE token = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$token]);
            return $stmt->rowCount() > 0;
        }

        $stmt = $db->prepare(
            "UPDATE invitaciones SET estado = 'aceptada'
               WHERE token = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }
}
