<?php
/**
 * GeriApp — Modelo Institución
 * Tabla: instituciones
 * Solo accesible para el rol superadmin.
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Institucion.php';
 *   $lista = Institucion::getAll();
 */

require_once __DIR__ . '/../Database.php';

class Institucion
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Todas las instituciones con conteos de usuarios y residentes activos.
     *
     * $filtros: estado ('activa'|'trial'|'suspendida'), busqueda, plan_id
     */
    public static function getAll(array $filtros = []): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT i.*,
                       p.nombre AS plan_nombre,
                       (SELECT COUNT(*) FROM usuarios   u WHERE u.institucion_id = i.id)                         AS total_usuarios,
                       (SELECT COUNT(*) FROM residentes r WHERE r.institucion_id = i.id AND r.estado = 'activo') AS total_residentes
                FROM instituciones i
                LEFT JOIN planes p ON p.id = i.plan_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND i.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (i.nombre LIKE ? OR i.email_admin LIKE ?)";
            $b = '%' . $filtros['busqueda'] . '%';
            $params[] = $b;
            $params[] = $b;
        }
        if (!empty($filtros['plan_id'])) {
            $sql .= " AND i.plan_id = ?";
            $params[] = $filtros['plan_id'];
        }

        $sql .= " ORDER BY i.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Una institución por ID, con nombre del plan.
     */
    public static function getById(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT i.*, p.nombre AS plan_nombre
             FROM instituciones i
             LEFT JOIN planes p ON p.id = i.plan_id
             WHERE i.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Estadísticas detalladas de una institución.
     * Retorna: usuarios, residentes_activos, residentes_total, logs_30d
     */
    public static function getStats(int $id): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
               (SELECT COUNT(*) FROM usuarios    WHERE institucion_id = ?)                                        AS usuarios,
               (SELECT COUNT(*) FROM residentes  WHERE institucion_id = ? AND estado = 'activo')                  AS residentes_activos,
               (SELECT COUNT(*) FROM residentes  WHERE institucion_id = ?)                                        AS residentes_total,
               (SELECT COUNT(*) FROM logs_sistema WHERE institucion_id = ?
                  AND creado_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))                                              AS logs_30d"
        );
        $stmt->execute([$id, $id, $id, $id]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Total de instituciones, opcionalmente filtradas por estado.
     */
    public static function count(?string $estado = null): int
    {
        $db = Database::getInstance();
        if ($estado) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM instituciones WHERE estado = ?");
            $stmt->execute([$estado]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) FROM instituciones");
        }
        return (int) $stmt->fetchColumn();
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea una institución.
     * Requiere: nombre, email_admin
     * Opcionales: telefono, direccion, timezone, plan_id, estado, trial_ends_at, logo_path
     *
     * @return int|false  ID de la institución o false en fallo
     */
    public static function create(array $data): int|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO instituciones
                 (nombre, email_admin, telefono, direccion, timezone,
                  plan_id, estado, trial_ends_at, logo_path)
             VALUES
                 (:nombre, :email_admin, :telefono, :direccion, :timezone,
                  :plan_id, :estado, :trial_ends_at, :logo_path)"
        );
        $ok = $stmt->execute([
            ':nombre'        => $data['nombre'],
            ':email_admin'   => $data['email_admin'],
            ':telefono'      => $data['telefono']      ?? null,
            ':direccion'     => $data['direccion']     ?? null,
            ':timezone'      => $data['timezone']      ?? 'America/Mexico_City',
            ':plan_id'       => $data['plan_id']       ?? null,
            ':estado'        => $data['estado']        ?? 'trial',
            ':trial_ends_at' => $data['trial_ends_at'] ?? null,
            ':logo_path'     => $data['logo_path']     ?? null,
        ]);
        return $ok ? (int) $db->lastInsertId() : false;
    }

    /**
     * Actualiza campos de una institución.
     * Campos editables: nombre, email_admin, telefono, direccion, timezone,
     *                   plan_id, estado, trial_ends_at, plan_vence_at, logo_path
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'nombre', 'email_admin', 'telefono', 'direccion', 'timezone',
            'plan_id', 'estado', 'trial_ends_at', 'plan_vence_at', 'logo_path',
            // Campos adicionales (agregados en migrate_configuracion_v2)
            'rfc', 'ciudad', 'estado_inst', 'num_camas',
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
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE instituciones SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }

    /**
     * Suspende una institución.
     */
    public static function suspend(int $id): bool
    {
        return self::update($id, ['estado' => 'suspendida']);
    }

    /**
     * Activa una institución.
     */
    public static function activate(int $id): bool
    {
        return self::update($id, ['estado' => 'activa']);
    }

    /**
     * Provisiona todos los recursos necesarios para una institución recién creada:
     *   1. Crea los directorios de uploads (fotos_residentes, historial, logos)
     *   2. Si ENABLE_TENANT_DBS está definido como true, crea una BD independiente
     *      y registra su nombre en instituciones.db_name
     *
     * Llamar inmediatamente después de Institucion::create().
     *
     * @param  int    $id         ID de la institución recién creada
     * @param  bool   $tenantDb   true = crear BD separada, false = BD compartida
     * @return array  ['ok' => bool, 'db_created' => bool, 'dirs_created' => int, 'error' => ?string]
     */
    public static function provision(int $id, bool $tenantDb = false): array
    {
        $result = ['ok' => true, 'db_created' => false, 'dirs_created' => 0, 'error' => null];

        // 1. Directorios de uploads
        $base    = dirname(__DIR__, 2) . '/uploads';
        $subdirs = ['fotos_residentes', 'historial', 'logos'];
        foreach ($subdirs as $sub) {
            $dir = "{$base}/{$sub}/{$id}";
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $result['ok']    = false;
                $result['error'] = "No se pudo crear el directorio {$sub}/{$id}";
                return $result;
            }
            $result['dirs_created']++;
        }

        // 2. BD de tenant (opcional / fase futura)
        if ($tenantDb || (defined('ENABLE_TENANT_DBS') && ENABLE_TENANT_DBS)) {
            try {
                $dbName = Database::tenantDbName($id);
                Database::createTenantDB($dbName);

                // Guardar db_name en la fila de la institución
                $db   = Database::getMaster();
                $stmt = $db->prepare("UPDATE instituciones SET db_name = ? WHERE id = ?");
                $stmt->execute([$dbName, $id]);

                Database::clearTenantCache($id);
                $result['db_created'] = true;
            } catch (Exception $e) {
                $result['ok']    = false;
                $result['error'] = 'Error creando BD tenant: ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Elimina una institución (hard delete; las FK en cascada limpiarán todo).
     * ⚠️  Usar con extrema precaución.
     */
    public static function delete(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM instituciones WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
