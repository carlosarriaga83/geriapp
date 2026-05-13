<?php
/**
 * GeriApp — Modelo Plan
 * Tabla: planes
 * Solo accesible para el rol superadmin.
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Plan.php';
 *   $planes = Plan::getAll(soloActivos: true);
 */

require_once __DIR__ . '/../Database.php';

class Plan
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Todos los planes, opcionalmente solo los activos.
     */
    public static function getAll(bool $soloActivos = false): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM instituciones WHERE plan_id = p.id) AS total_clientes
                FROM planes p";
        if ($soloActivos) {
            $sql .= " WHERE p.activo = 1";
        }
        $sql .= " ORDER BY p.precio ASC";
        return $db->query($sql)->fetchAll();
    }

    /**
     * Un plan por ID.
     */
    public static function getById(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM planes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Un plan por su clave (key), útil para el registro.
     */
    public static function getByKey(string $key): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM planes WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        return $stmt->fetch();
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea un plan.
     * Requiere: key, nombre
    * Opcionales: precio, descripcion, max_residentes, max_usuarios, modulos (array), activo
     *
     * @return int|false  ID del plan o false en fallo
     */
    public static function create(array $data): int|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO planes
                 (`key`, nombre, precio, descripcion, max_residentes, max_usuarios, modulos, solicita_tarjeta_registro, activo)
             VALUES
                 (:key, :nombre, :precio, :descripcion, :max_residentes, :max_usuarios, :modulos, :solicita_tarjeta_registro, :activo)"
        );

        $modulos = null;
        if (!empty($data['modulos'])) {
            $modulos = is_array($data['modulos'])
                ? json_encode($data['modulos'], JSON_UNESCAPED_UNICODE)
                : $data['modulos'];
        }

        $ok = $stmt->execute([
            ':key'            => $data['key'],
            ':nombre'         => $data['nombre'],
            ':precio'         => $data['precio']         ?? 0,
            ':descripcion'    => $data['descripcion']    ?? null,
            ':max_residentes' => $data['max_residentes'] ?? null,
            ':max_usuarios'   => $data['max_usuarios']   ?? null,
            ':modulos'        => $modulos,
            ':solicita_tarjeta_registro' => !empty($data['solicita_tarjeta_registro']) ? 1 : 0,
            ':activo'         => $data['activo']          ?? 1,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    }

    /**
     * Actualiza campos de un plan.
      * Campos editables: nombre, precio, descripcion, max_residentes, max_usuarios, modulos, activo
     */
    public static function update(int $id, array $data): bool
    {
          $allowed = ['nombre', 'precio', 'descripcion', 'max_residentes', 'max_usuarios', 'solicita_tarjeta_registro', 'activo'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        // modulos requiere json_encode si viene como array
        if (array_key_exists('modulos', $data)) {
            $sets[]   = "modulos = ?";
            $params[] = is_array($data['modulos'])
                ? json_encode($data['modulos'], JSON_UNESCAPED_UNICODE)
                : $data['modulos'];
        }

        if (empty($sets)) return false;

        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE planes SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }

    /**
     * Activa o desactiva un plan.
     */
    public static function toggleActivo(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE planes SET activo = IF(activo = 1, 0, 1) WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Elimina un plan (solo si no tiene instituciones asignadas).
     *
     * @throws RuntimeException si hay instituciones en uso
     */
    public static function delete(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM instituciones WHERE plan_id = ?"
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException(
                "No se puede eliminar: el plan tiene instituciones asignadas."
            );
        }
        $stmt = $db->prepare("DELETE FROM planes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
