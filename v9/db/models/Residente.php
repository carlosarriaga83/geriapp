<?php
/**
 * GeriApp — Modelo Residente
 * Tabla: residentes
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Residente.php';
 *   $lista = Residente::getAll($institucion_id);
 */

require_once __DIR__ . '/../Database.php';
require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Residente
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Todos los residentes de una institución, con joins a médico y familiar.
     *
     * $filtros: estado ('activo'|'egresado'|'fallecido'), busqueda, habitacion
     */
    public static function getAll(int $institucion_id, array $filtros = []): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT r.*,
                       CONCAT(r.nombre, ' ', r.apellidos) AS nombre_completo,
                       u.nombre  AS medico_nombre,
                       f.nombre  AS familiar_nombre
                FROM residentes r
                LEFT JOIN usuarios u ON u.id = r.medico_id
                LEFT JOIN usuarios f ON f.id = r.familiar_id
                WHERE r.institucion_id = ?";
        $params = [$institucion_id];

        if (!empty($filtros['estado'])) {
            $sql .= " AND r.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (r.nombre LIKE ? OR r.apellidos LIKE ? OR r.folio LIKE ?)";
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }
        if (!empty($filtros['habitacion'])) {
            $sql .= " AND r.habitacion = ?";
            $params[] = $filtros['habitacion'];
        }

        $sql .= " ORDER BY r.apellidos ASC, r.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => EncryptionMap::decryptRow('residentes', $r), $rows);
    }

    /**
     * Un residente por ID, verificando que pertenezca a la institución.
     */
    public static function getById(int $id, int $institucion_id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT r.*,
                    CONCAT(r.nombre, ' ', r.apellidos) AS nombre_completo,
                    u.nombre AS medico_nombre,
                    f.nombre AS familiar_nombre
             FROM residentes r
             LEFT JOIN usuarios u ON u.id = r.medico_id
             LEFT JOIN usuarios f ON f.id = r.familiar_id
             WHERE r.id = ? AND r.institucion_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $institucion_id]);
        $row = $stmt->fetch();
        return $row ? EncryptionMap::decryptRow('residentes', $row) : false;
    }

    /**
     * Solo residentes activos (atajo frecuente).
     */
    public static function getActivos(int $institucion_id): array
    {
        return self::getAll($institucion_id, ['estado' => 'activo']);
    }

    /**
     * Cuenta residentes de una institución por estado.
     */
    public static function countByInstitucion(int $institucion_id, string $estado = 'activo'): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM residentes WHERE institucion_id = ? AND estado = ?"
        );
        $stmt->execute([$institucion_id, $estado]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Genera el siguiente folio correlativo: RES-YYYY-NNN.
     */
    public static function generateFolio(int $institucion_id): string
    {
        $db   = Database::getInstance();
        $year = date('Y');
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM residentes WHERE institucion_id = ? AND folio LIKE ?"
        );
        $stmt->execute([$institucion_id, "RES-$year-%"]);
        $count = (int) $stmt->fetchColumn();
        return sprintf('RES-%s-%03d', $year, $count + 1);
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo residente.
     * Requiere: nombre, apellidos, institucion_id
     *
     * @return int|false  ID del nuevo residente o false en fallo
     */
    public static function create(array $data): int|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO residentes
                 (institucion_id, folio, nombre, apellidos, fecha_nacimiento,
                  sexo, curp, nss, estado_civil, diagnostico, alergias, medico_id, familiar_id,
                  habitacion, fecha_ingreso, estado, foto_path, notas,
                  cuidados_especiales, contacto_nombre, contacto_parentesco,
                  contacto_telefono, contacto_telefono2, contacto_email, contacto_direccion,
                  contactos_json)
             VALUES
                 (:institucion_id, :folio, :nombre, :apellidos, :fecha_nacimiento,
                  :sexo, :curp, :nss, :estado_civil, :diagnostico, :alergias, :medico_id, :familiar_id,
                  :habitacion, :fecha_ingreso, :estado, :foto_path, :notas,
                  :cuidados_especiales, :contacto_nombre, :contacto_parentesco,
                  :contacto_telefono, :contacto_telefono2, :contacto_email, :contacto_direccion,
                  :contactos_json)"
        );
        $ok = $stmt->execute([
            ':institucion_id'      => $data['institucion_id'],
            ':folio'               => $data['folio']               ?? self::generateFolio((int)$data['institucion_id']),
            ':nombre'              => $data['nombre'],
            ':apellidos'           => $data['apellidos'],
            ':fecha_nacimiento'    => $data['fecha_nacimiento']    ?? null,
            ':sexo'                => $data['sexo']                ?? null,
            ':curp'                => $data['curp']                ?? null,
            ':nss'                 => $data['nss']                 ?? null,
            ':estado_civil'        => $data['estado_civil']        ?? null,
            ':diagnostico'         => $data['diagnostico']         ?? null,
            ':alergias'            => $data['alergias']            ?? null,
            ':medico_id'           => $data['medico_id']           ?? null,
            ':familiar_id'         => $data['familiar_id']         ?? null,
            ':habitacion'          => $data['habitacion']          ?? null,
            ':fecha_ingreso'       => $data['fecha_ingreso']       ?? date('Y-m-d'),
            ':estado'              => $data['estado']              ?? 'activo',
            ':foto_path'           => $data['foto_path']           ?? null,
            ':notas'               => $data['notas']               ?? null,
            ':cuidados_especiales' => $data['cuidados_especiales'] ?? null,
            ':contacto_nombre'     => $data['contacto_nombre']     ?? null,
            ':contacto_parentesco' => $data['contacto_parentesco'] ?? null,
            ':contacto_telefono'   => $data['contacto_telefono']   ?? null,
            ':contacto_telefono2'  => $data['contacto_telefono2']  ?? null,
            ':contacto_email'      => $data['contacto_email']      ?? null,
            ':contacto_direccion'  => $data['contacto_direccion']  ?? null,
            ':contactos_json'     => $data['contactos_json']     ?? null,
        ]);
        $id = $ok ? (int) $db->lastInsertId() : false;

        // §5 Dual-write: cifrar hacia columnas _enc
        if ($id) self::_dualWriteEnc($db, $id, $data);

        return $id;
    }

    /**
     * Actualiza datos de un residente (scoped por institución).
     */
    public static function update(int $id, int $institucion_id, array $data): bool
    {
        $allowed = [
            'nombre', 'apellidos', 'fecha_nacimiento', 'sexo', 'curp', 'nss',
            'estado_civil', 'diagnostico', 'alergias', 'medico_id', 'familiar_id',
            'habitacion', 'fecha_ingreso', 'estado', 'foto_path', 'notas',
            'cuidados_especiales', 'contacto_nombre', 'contacto_parentesco',
            'contacto_telefono', 'contacto_telefono2', 'contacto_email', 'contacto_direccion',
            'contactos_json',
        ];
        $sets   = [];
        $params = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;

        $params[] = $id;
        $params[] = $institucion_id;
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE residentes SET " . implode(', ', $sets) .
            " WHERE id = ? AND institucion_id = ?"
        );
        $ok = $stmt->execute($params);

        // §5 Dual-write: cifrar hacia columnas _enc
        if ($ok) self::_dualWriteEnc($db, $id, $data);

        return $ok;
    }

    /**
     * §5 Dual-write: actualiza columnas _enc con datos cifrados.
     */
    private static function _dualWriteEnc(PDO $db, int $id, array $data): void
    {
        try {
            $enc = EncryptionMap::encryptRow('residentes', $data);
            $sets   = [];
            $params = [];
            foreach ($enc as $col => $val) {
                if (str_ends_with($col, '_enc')) {
                    $sets[]   = "`$col` = ?";
                    $params[] = $val;
                }
            }
            if (empty($sets)) return;
            $params[] = $id;
            $db->prepare("UPDATE residentes SET " . implode(', ', $sets) . " WHERE id = ?")
               ->execute($params);
        } catch (Throwable $e) {
            // Non-blocking: _enc columns may not exist yet
        }
    }

    /**
     * Cambia el estado de un residente (egresado / fallecido).
     */
    public static function cambiarEstado(int $id, int $institucion_id, string $estado): bool
    {
        return self::update($id, $institucion_id, ['estado' => $estado]);
    }

    /**
     * Elimina un residente (hard delete, scoped por institución).
     */
    public static function delete(int $id, int $institucion_id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM residentes WHERE id = ? AND institucion_id = ?"
        );
        return $stmt->execute([$id, $institucion_id]);
    }
}
