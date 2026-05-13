<?php
/**
 * GeriApp — Modelo UsuarioInstitucion
 * Tabla: usuario_instituciones  (pivot user ↔ institution)
 *
 * Un mismo usuario puede pertenecer a varias instituciones con diferentes roles.
 * El superadmin no usa esta tabla (no tiene institución).
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/UsuarioInstitucion.php';
 *   $insts = UsuarioInstitucion::getByUsuario($userId);
 */

require_once __DIR__ . '/../Database.php';

class UsuarioInstitucion
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Todas las instituciones a las que pertenece un usuario, con datos de la institución.
     *
     * @return array[]  [{institucion_id, rol, estado, inst_nombre, inst_estado, logo_path, ...}]
     */
    public static function getByUsuario(int $userId, bool $soloActivos = true): array
    {
        $db  = Database::getMaster();
        $sql = "SELECT ui.id, ui.usuario_id, ui.institucion_id, ui.rol, ui.estado,
                       ui.creado_at, ui.updated_at,
                       i.nombre      AS inst_nombre,
                       i.estado      AS inst_estado,
                       i.logo_path   AS inst_logo,
                       i.telefono    AS inst_telefono,
                       i.db_name     AS inst_db_name,
                       p.nombre      AS plan_nombre,
                       (SELECT COUNT(*) FROM residentes r
                        WHERE r.institucion_id = ui.institucion_id AND r.estado = 'activo') AS residentes_activos
                FROM usuario_instituciones ui
                JOIN instituciones i ON i.id = ui.institucion_id
                LEFT JOIN planes p ON p.id = i.plan_id
                WHERE ui.usuario_id = ?";
        $params = [$userId];

        if ($soloActivos) {
            $sql .= " AND ui.estado = 'activo' AND i.estado NOT IN ('suspendida','archivada')";
        }

        $sql .= " ORDER BY i.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Todos los usuarios de una institución, con datos del usuario.
     *
     * $filtros: rol, estado, busqueda
     */
    public static function getByInstitucion(int $instId, array $filtros = []): array
    {
        $db  = Database::getMaster();
        $sql = "SELECT ui.id, ui.usuario_id, ui.institucion_id, ui.rol, ui.estado,
                       u.id AS user_id, u.nombre, u.email, u.telefono, u.avatar_path, u.ultimo_acceso, u.creado_at AS usuario_creado
                FROM usuario_instituciones ui
                JOIN usuarios u ON u.id = ui.usuario_id
                WHERE ui.institucion_id = ?";
        $params = [$instId];

        if (!empty($filtros['rol'])) {
            $sql .= " AND ui.rol = ?";
            $params[] = $filtros['rol'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND ui.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ?)";
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b;
            $params[] = $b;
        }

        $sql .= " ORDER BY u.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene la relación pivot concreta entre usuario e institución.
     * Retorna false si no existe.
     */
    public static function get(int $userId, int $instId): array|false
    {
        $db   = Database::getMaster();
        $stmt = $db->prepare(
            "SELECT * FROM usuario_instituciones
             WHERE usuario_id = ? AND institucion_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $instId]);
        return $stmt->fetch();
    }

    /**
     * Comprueba si el usuario tiene acceso a la institución (estado activo).
     */
    public static function hasAccess(int $userId, int $instId): bool
    {
        $row = self::get($userId, $instId);
        return $row !== false && $row['estado'] === 'activo';
    }

    /**
     * Obtiene el rol del usuario en la institución.
     * Retorna null si no pertenece.
     */
    public static function getRol(int $userId, int $instId): ?string
    {
        $row = self::get($userId, $instId);
        return $row ? $row['rol'] : null;
    }

    /**
     * Cantidad de usuarios activos en una institución.
     */
    public static function count(int $instId): int
    {
        $db   = Database::getMaster();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM usuario_instituciones WHERE institucion_id = ? AND estado = 'activo'"
        );
        $stmt->execute([$instId]);
        return (int) $stmt->fetchColumn();
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Agrega un usuario a una institución con el rol indicado.
     * Si ya existe la relación, la reactiva.
     *
     * @return bool
     */
    public static function add(int $userId, int $instId, string $rol = 'enfermero'): bool
    {
        $db   = Database::getMaster();
        $stmt = $db->prepare(
            "INSERT INTO usuario_instituciones (usuario_id, institucion_id, rol, estado)
             VALUES (?, ?, ?, 'activo')
             ON DUPLICATE KEY UPDATE rol = VALUES(rol), estado = 'activo', updated_at = NOW()"
        );
        return $stmt->execute([$userId, $instId, $rol]);
    }

    /**
     * Actualiza el rol o estado de la relación.
     * $data puede contener: rol, estado
     */
    public static function update(int $userId, int $instId, array $data): bool
    {
        $allowed = ['rol', 'estado'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;

        $params[] = $userId;
        $params[] = $instId;

        $db   = Database::getMaster();
        $stmt = $db->prepare(
            "UPDATE usuario_instituciones SET " . implode(', ', $sets) . ", updated_at = NOW()
             WHERE usuario_id = ? AND institucion_id = ?"
        );
        return $stmt->execute($params);
    }

    /**
     * Desactiva la relación (no elimina físicamente).
     */
    public static function deactivate(int $userId, int $instId): bool
    {
        return self::update($userId, $instId, ['estado' => 'inactivo']);
    }

    /**
     * Elimina la relación físicamente.
     */
    public static function remove(int $userId, int $instId): bool
    {
        $db   = Database::getMaster();
        $stmt = $db->prepare(
            "DELETE FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?"
        );
        return $stmt->execute([$userId, $instId]);
    }

    /**
     * Sincroniza el pivot con los datos actuales de usuarios.institucion_id.
     * Útil después de migraciones o al crear usuarios por el flujo legacy.
     *
     * @param int $userId  ID del usuario a sincronizar (o 0 para todos)
     */
    public static function syncFromLegacy(int $userId = 0): int
    {
        $db     = Database::getMaster();
        $rolesValidos = ['admin', 'medico', 'enfermero', 'familiar'];

        $sql = "SELECT id, institucion_id, rol FROM usuarios
                WHERE institucion_id IS NOT NULL AND rol != 'superadmin'";
        $params = [];
        if ($userId > 0) {
            $sql .= " AND id = ?";
            $params[] = $userId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $usuarios = $stmt->fetchAll();

        $ins = $db->prepare(
            "INSERT IGNORE INTO usuario_instituciones (usuario_id, institucion_id, rol)
             VALUES (?, ?, ?)"
        );

        $count = 0;
        foreach ($usuarios as $u) {
            $rol = in_array($u['rol'], $rolesValidos, true) ? $u['rol'] : 'enfermero';
            $ins->execute([$u['id'], $u['institucion_id'], $rol]);
            $count++;
        }
        return $count;
    }
}
