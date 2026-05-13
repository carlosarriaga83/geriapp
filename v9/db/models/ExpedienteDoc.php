<?php
/**
 * GeriApp — Modelo ExpedienteDoc
 * Tabla: expediente_docs
 */

require_once __DIR__ . '/../Database.php';

class ExpedienteDoc
{
    private static function db(int $inst_id): PDO
    {
        return Database::getTenant($inst_id);
    }

    private static function authorNames(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn($row) => (int)($row['created_by'] ?? 0),
            $rows
        ))));
        if (!$ids) return [];

        $db = Database::getMaster();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $names = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $names[(int)$row['id']] = $row['nombre'] ?? '';
        }
        return $names;
    }

    /**
     * Listar documentos de un residente con filtros opcionales.
     */
    public static function list(int $inst_id, int $residente_id, array $filters = []): array
    {
        $db   = self::db($inst_id);
        $sql  = "SELECT ed.*
                 FROM expediente_docs ed
                 WHERE ed.institucion_id = ? AND ed.residente_id = ?";
        $params = [$inst_id, $residente_id];

        if (!empty($filters['tipo'])) {
            $sql .= " AND ed.tipo = ?";
            $params[] = $filters['tipo'];
        }
        if (!empty($filters['fuente'])) {
            $sql .= " AND ed.fuente = ?";
            $params[] = $filters['fuente'];
        }
        if (!empty($filters['desde'])) {
            $sql .= " AND ed.fecha_documento >= ?";
            $params[] = $filters['desde'];
        }
        if (!empty($filters['hasta'])) {
            $sql .= " AND ed.fecha_documento <= ?";
            $params[] = $filters['hasta'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (ed.titulo LIKE ? OR ed.descripcion LIKE ? OR ed.nombre_fuente LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $orden = (!empty($filters['orden']) && strtoupper($filters['orden']) === 'ASC') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ed.fecha_documento {$orden}, ed.created_at {$orden}";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . intval($filters['offset']);
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $authors = self::authorNames($rows);
        foreach ($rows as &$row) {
            $row['autor'] = $authors[(int)($row['created_by'] ?? 0)] ?? null;
        }
        unset($row);
        return $rows;
    }

    /**
     * Contar documentos (para paginación).
     */
    public static function count(int $inst_id, int $residente_id, array $filters = []): int
    {
        $db   = self::db($inst_id);
        $sql  = "SELECT COUNT(*) FROM expediente_docs WHERE institucion_id = ? AND residente_id = ?";
        $params = [$inst_id, $residente_id];

        if (!empty($filters['tipo'])) { $sql .= " AND tipo = ?"; $params[] = $filters['tipo']; }
        if (!empty($filters['fuente'])) { $sql .= " AND fuente = ?"; $params[] = $filters['fuente']; }
        if (!empty($filters['desde'])) { $sql .= " AND fecha_documento >= ?"; $params[] = $filters['desde']; }
        if (!empty($filters['hasta'])) { $sql .= " AND fecha_documento <= ?"; $params[] = $filters['hasta']; }
        if (!empty($filters['q'])) {
            $sql .= " AND (titulo LIKE ? OR descripcion LIKE ? OR nombre_fuente LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener un documento por ID (con validación de institución).
     */
    public static function getById(int $id, int $inst_id, ?int $residente_id = null): array|false
    {
        $db   = self::db($inst_id);
        $sql = "SELECT * FROM expediente_docs WHERE id = ? AND institucion_id = ?";
        $params = [$id, $inst_id];
        if ($residente_id !== null) {
            $sql .= " AND residente_id = ?";
            $params[] = $residente_id;
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Crear documento.
     */
    public static function create(array $data): int|false
    {
        $instId = (int)($data['institucion_id'] ?? 0);
        $db = self::db($instId);
        $stmt = $db->prepare(
            "INSERT INTO expediente_docs
               (institucion_id, residente_id, tipo, titulo, descripcion,
                fuente, nombre_fuente, especialidad, fecha_documento,
                archivo_nombre, archivo_tipo, archivo_path, archivos_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ok = $stmt->execute([
            $data['institucion_id'],
            $data['residente_id'],
            $data['tipo'],
            $data['titulo'],
            $data['descripcion'] ?? null,
            $data['fuente'] ?? 'medico',
            $data['nombre_fuente'] ?? null,
            $data['especialidad'] ?? null,
            $data['fecha_documento'] ?? null,
            $data['archivo_nombre'] ?? null,
            $data['archivo_tipo'] ?? null,
            $data['archivo_path'] ?? null,
            $data['archivos_json'] ?? null,
            $data['created_by'],
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    }

    /**
     * Actualizar metadatos de un documento.
     */
    public static function update(int $id, array $data): bool
    {
        $instId = (int)($data['institucion_id'] ?? 0);
        if ($instId <= 0) return false;
        $residentId = isset($data['residente_id']) ? (int)$data['residente_id'] : null;
        $db      = self::db($instId);
        $allowed = ['tipo','titulo','descripcion','fuente','nombre_fuente','especialidad','fecha_documento',
                     'archivo_nombre','archivo_tipo','archivo_path','archivos_json'];
        $sets    = [];
        $vals    = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $sets[] = "`{$field}` = ?";
            $vals[] = $data[$field];
        }
        if (!$sets) return false;
        $vals[] = $id;
        $vals[] = $instId;
        $where = " WHERE id = ? AND institucion_id = ?";
        if ($residentId !== null) {
            $where .= " AND residente_id = ?";
            $vals[] = $residentId;
        }
        return $db->prepare("UPDATE expediente_docs SET " . implode(', ', $sets) . $where)->execute($vals);
    }

    /**
     * Eliminar documento (hard delete) y devolver path del archivo para limpieza.
     */
    public static function delete(int $id, int $inst_id, ?int $residente_id = null): array|false
    {
        $db   = self::db($inst_id);
        $where = "WHERE id = ? AND institucion_id = ?";
        $params = [$id, $inst_id];
        if ($residente_id !== null) {
            $where .= " AND residente_id = ?";
            $params[] = $residente_id;
        }
        $stmt = $db->prepare("SELECT archivo_path, archivos_json FROM expediente_docs {$where}");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        $del = $db->prepare("DELETE FROM expediente_docs {$where}");
        $del->execute($params);

        // Clear linkage in prescripciones
        $db->prepare("UPDATE prescripciones SET expediente_id = NULL WHERE expediente_id = ? AND institucion_id = ?")->execute([$id, $inst_id]);

        // Collect all file paths for cleanup
        $paths = [];
        if (!empty($row['archivos_json'])) {
            $archivos = json_decode($row['archivos_json'], true);
            if (is_array($archivos)) {
                foreach ($archivos as $a) {
                    if (!empty($a['path'])) $paths[] = $a['path'];
                }
            }
        } elseif (!empty($row['archivo_path'])) {
            $paths[] = $row['archivo_path'];
        }
        return $paths;
    }

    /**
     * Documentos vinculados a una prescripción.
     */
    public static function getForPrescripcion(int $rx_id, int $inst_id, ?int $residente_id = null): array|false
    {
        $db   = self::db($inst_id);
        $where = "p.id = ? AND p.institucion_id = ?";
        $params = [$rx_id, $inst_id];
        if ($residente_id !== null) {
            $where .= " AND p.residente_id = ? AND ed.residente_id = ?";
            $params[] = $residente_id;
            $params[] = $residente_id;
        }
        $stmt = $db->prepare(
            "SELECT ed.* FROM expediente_docs ed
             JOIN prescripciones p ON p.expediente_id = ed.id
             WHERE $where LIMIT 1"
        );
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Listado breve (id, titulo, tipo, fecha) para selector de vinculación.
     */
    public static function listBrief(int $inst_id, int $residente_id): array
    {
        $db   = self::db($inst_id);
        $stmt = $db->prepare(
            "SELECT id, titulo, tipo, fecha_documento
             FROM expediente_docs
             WHERE institucion_id = ? AND residente_id = ?
             ORDER BY fecha_documento DESC, created_at DESC"
        );
        $stmt->execute([$inst_id, $residente_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
