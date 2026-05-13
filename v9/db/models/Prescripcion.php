<?php
/**
 * GeriApp — Modelo Prescripciones
 * Tabla: prescripciones
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Prescripcion.php';
 *   $lista = Prescripcion::getForInstitucion($inst_id);
 */

require_once __DIR__ . '/../Database.php';
require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Prescripcion
{
    /**
     * Prescripciones activas de todos los residentes de una institución.
     * Incluye nombre del residente para mostrar en la bitácora.
     */
    public static function getForInstitucion(int $inst_id): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT p.*, CONCAT(r.nombre, ' ', r.apellidos) AS res_nombre, r.id AS res_id
             FROM prescripciones p
             JOIN residentes r ON r.id = p.residente_id
             WHERE p.institucion_id = ? AND p.activo = 1
               AND (p.fin IS NULL OR p.fin >= CURDATE())
             ORDER BY r.nombre, r.apellidos"
        );
        $stmt->execute([$inst_id]);
        $rows = array_map(fn($r) => EncryptionMap::decryptRow('prescripciones', $r), $stmt->fetchAll());
        // §5 Sort by nombre in PHP (column is encrypted, can't ORDER BY in SQL)
        usort($rows, function($a, $b) {
            $cmp = strcasecmp($a['res_nombre'] ?? '', $b['res_nombre'] ?? '');
            return $cmp !== 0 ? $cmp : strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? '');
        });
        return $rows;
    }

    /**
     * Prescripciones activas de un residente específico.
     */
    public static function getForResidente(int $residente_id): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT p.*, CONCAT(r.nombre, ' ', r.apellidos) AS res_nombre
             FROM prescripciones p
             JOIN residentes r ON r.id = p.residente_id
             WHERE p.residente_id = ? AND p.activo = 1
               AND (p.fin IS NULL OR p.fin >= CURDATE())"
        );
        $stmt->execute([$residente_id]);
        $rows = array_map(fn($r) => EncryptionMap::decryptRow('prescripciones', $r), $stmt->fetchAll());
        // §5 Sort by nombre in PHP (column is encrypted)
        usort($rows, fn($a, $b) => strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? ''));
        return $rows;
    }

    public static function getById(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT p.*, CONCAT(r.nombre, ' ', r.apellidos) AS res_nombre, r.id AS res_id
             FROM prescripciones p
             JOIN residentes r ON r.id = p.residente_id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? EncryptionMap::decryptRow('prescripciones', $row) : false;
    }

    public static function create(array $data): int|false
    {
        $db   = Database::getInstance();
        // §5 Prepare data for encryption (normalize horarios to JSON string)
        $encData = $data;
        if (isset($encData['horarios']) && is_array($encData['horarios'])) {
            $encData['horarios'] = json_encode($encData['horarios']);
        }
        $encData = EncryptionMap::encryptRow('prescripciones', $encData);

        $stmt = $db->prepare(
            "INSERT INTO prescripciones
               (residente_id, institucion_id, nombre, dosis, via, frecuencia,
                horarios, indicacion, imagen, medico_nombre, inicio, fin)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ok = $stmt->execute([
            $data['residente_id'],
            $data['institucion_id'],
            $encData['nombre']        ?? $data['nombre'],
            $encData['dosis']         ?? null,
            $encData['via']           ?? null,
            $encData['frecuencia']    ?? null,
            $encData['horarios']      ?? (isset($data['horarios']) ? json_encode($data['horarios']) : null),
            $encData['indicacion']    ?? null,
            $data['imagen']           ?? null,
            $encData['medico_nombre'] ?? null,
            $data['inicio']           ?? null,
            $data['fin']              ?? null,
        ]);
        $id = $ok ? (int)$db->lastInsertId() : false;
        if ($id) self::_dualWriteEnc($db, $id, $encData);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $db      = Database::getInstance();
        $allowed = ['nombre','dosis','via','frecuencia','horarios','indicacion','imagen','medico_nombre','inicio','fin','activo','expediente_id'];

        // §5 Prepare data for encryption (normalize horarios)
        $encData = $data;
        if (isset($encData['horarios']) && is_array($encData['horarios'])) {
            $encData['horarios'] = json_encode($encData['horarios']);
        }
        $encData = EncryptionMap::encryptRow('prescripciones', $encData);

        $sets    = [];
        $vals    = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $sets[] = "`{$field}` = ?";
            // Use encrypted value (same as plain in non-consolidated, encrypted in consolidated)
            if ($field === 'horarios' && is_array($data[$field])) {
                $vals[] = $encData['horarios'] ?? json_encode($data[$field]);
            } else {
                $vals[] = $encData[$field] ?? $data[$field];
            }
        }
        if (!$sets) return false;
        $vals[] = $id;
        $ok = $db->prepare(
            "UPDATE prescripciones SET " . implode(', ', $sets) . " WHERE id = ?"
        )->execute($vals);
        if ($ok) self::_dualWriteEnc($db, $id, $encData);
        return $ok;
    }

    private static function _dualWriteEnc(PDO $db, int $id, array $encData): void
    {
        try {
            $sets = []; $params = [];
            foreach ($encData as $col => $val) {
                if (str_ends_with($col, '_enc')) { $sets[] = "`$col` = ?"; $params[] = $val; }
            }
            if (empty($sets)) return;
            $params[] = $id;
            $db->prepare("UPDATE prescripciones SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        } catch (Throwable $e) { /* _enc columns may not exist yet */ }
    }

    /**
     * Historial completo de prescripciones de un residente:
     * activas, vencidas y suspendidas, ordenadas por vigencia y fecha.
     */
    public static function getHistorialForResidente(int $residente_id): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT p.*
             FROM prescripciones p
             WHERE p.residente_id = ?
             ORDER BY p.activo DESC,
                      CASE WHEN p.fin IS NULL THEN 1 ELSE 0 END DESC,
                      p.fin DESC,
                      p.creado_at DESC"
        );
        $stmt->execute([$residente_id]);
        return array_map(
            fn($r) => EncryptionMap::decryptRow('prescripciones', $r),
            $stmt->fetchAll()
        );
    }

    /**
     * Soft-delete: marca como inactiva.
     */
    public static function delete(int $id): bool
    {
        return self::update($id, ['activo' => 0]);
    }
}
