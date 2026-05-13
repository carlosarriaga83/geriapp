<?php
/**
 * GeriApp — Modelo Usuario
 * Tabla: usuarios
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Usuario.php';
 *   $usuarios = Usuario::getAll($institucion_id);
 */

require_once __DIR__ . '/../Database.php';

class Usuario
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Todos los usuarios, opcionalmente filtrados por institución.
     * Si $institucion_id es null devuelve TODOS (solo para superadmin).
     *
     * $filtros: rol, estado, busqueda
     */
    public static function getAll(?int $institucion_id = null, array $filtros = []): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.rol, u.estado,
                       u.institucion_id, u.avatar_path, u.ultimo_acceso,
                       u.creado_at, i.nombre AS institucion_nombre
                FROM usuarios u
                LEFT JOIN instituciones i ON i.id = u.institucion_id
                WHERE 1=1";
        $params = [];

        if ($institucion_id !== null) {
            $sql .= " AND u.institucion_id = ?";
            $params[] = $institucion_id;
        }
        if (!empty($filtros['rol'])) {
            $sql .= " AND u.rol = ?";
            $params[] = $filtros['rol'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND u.estado = ?";
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
     * Un usuario por ID (con nombre de institución).
     */
    public static function getById(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT u.*, i.nombre AS institucion_nombre
             FROM usuarios u
             LEFT JOIN instituciones i ON i.id = u.institucion_id
             WHERE u.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Busca un usuario por email (para autenticación).
     */
    public static function findByEmail(string $email): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM usuarios WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Número de usuarios activos de una institución.
     */
    public static function countByInstitucion(int $institucion_id): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE institucion_id = ? AND estado = 'activo'"
        );
        $stmt->execute([$institucion_id]);
        return (int) $stmt->fetchColumn();
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo usuario.
     * Requiere: nombre, email, password_hash, rol
     * Opcionales: institucion_id, estado, avatar_path
     *
     * @return int|false  ID del nuevo usuario o false en fallo
     */
    public static function create(array $data): int|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO usuarios
                 (institucion_id, nombre, email, password_hash, rol, estado, avatar_path)
             VALUES
                 (:institucion_id, :nombre, :email, :password_hash, :rol, :estado, :avatar_path)"
        );
        $ok = $stmt->execute([
            ':institucion_id' => $data['institucion_id'] ?? null,
            ':nombre'         => $data['nombre'],
            ':email'          => $data['email'],
            ':password_hash'  => $data['password_hash'],
            ':rol'            => $data['rol'],
            ':estado'         => $data['estado']      ?? 'activo',
            ':avatar_path'    => $data['avatar_path'] ?? null,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    }

    /**
     * Actualiza campos de un usuario.
     * Campos editables: nombre, email, rol, estado, avatar_path, institucion_id
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = ['nombre', 'email', 'rol', 'estado', 'avatar_path', 'institucion_id',
                    'password_hash', 'telefono', 'preferencias'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;

        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }

    /**
     * Cambia la contraseña de un usuario (ya hasheada).
     */
    public static function updatePassword(int $id, string $password_hash): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE usuarios SET password_hash = ? WHERE id = ?"
        );
        return $stmt->execute([$password_hash, $id]);
    }

    /**
     * Alterna estado activo ↔ inactivo.
     */
    public static function toggleEstado(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE usuarios
             SET estado = IF(estado = 'activo', 'inactivo', 'activo')
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Actualiza el timestamp de último acceso.
     */
    public static function updateUltimoAcceso(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Elimina un usuario (hard delete).
     * ⚠️  Usar con precaución: cascada eliminará registros relacionados.
     */
    public static function delete(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
