<?php
/**
 * GeriApp — Modelo UsuarioResidente
 * Tabla: usuario_residentes (tenant DB)
 *
 * Pivot table linking users to specific residents they can access.
 * Used primarily for "familiar" role to restrict which residents they see,
 * but can also be used for any role scoping.
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/UsuarioResidente.php';
 *   $ids = UsuarioResidente::getResidenteIds($userId, $instId);
 */

require_once __DIR__ . '/../Database.php';

class UsuarioResidente
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * IDs de residentes vinculados a un usuario en una institución.
     */
    public static function getResidenteIds(int $userId, int $instId): array
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT residente_id FROM usuario_residentes WHERE usuario_id = ? AND institucion_id = ?"
        );
        $stmt->execute([$userId, $instId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Residentes vinculados a un usuario, con datos del residente.
     */
    public static function getResidentes(int $userId, int $instId): array
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT r.id, r.nombre, r.apellidos, r.estado, r.foto_path,
                    CONCAT(r.nombre, ' ', r.apellidos) AS nombre_completo
             FROM usuario_residentes ur
             JOIN residentes r ON r.id = ur.residente_id
             WHERE ur.usuario_id = ? AND ur.institucion_id = ?
             ORDER BY r.apellidos, r.nombre"
        );
        $stmt->execute([$userId, $instId]);
        return $stmt->fetchAll();
    }

    /**
     * Usuarios vinculados a un residente.
     */
    public static function getUsuarioIds(int $residenteId, int $instId): array
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT usuario_id FROM usuario_residentes WHERE residente_id = ? AND institucion_id = ?"
        );
        $stmt->execute([$residenteId, $instId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Vincula un usuario a un residente (idempotente).
     */
    public static function add(int $userId, int $residenteId, int $instId): bool
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "INSERT IGNORE INTO usuario_residentes (usuario_id, residente_id, institucion_id)
             VALUES (?, ?, ?)"
        );
        return $stmt->execute([$userId, $residenteId, $instId]);
    }

    /**
     * Desvincula un usuario de un residente.
     */
    public static function remove(int $userId, int $residenteId, int $instId): bool
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "DELETE FROM usuario_residentes WHERE usuario_id = ? AND residente_id = ? AND institucion_id = ?"
        );
        return $stmt->execute([$userId, $residenteId, $instId]);
    }

    /**
     * Sincroniza la lista completa de residentes para un usuario.
     * Reemplaza todos los vínculos existentes con los nuevos IDs.
     */
    public static function sync(int $userId, array $residenteIds, int $instId): void
    {
        $db = Database::getTenant($instId);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "DELETE FROM usuario_residentes WHERE usuario_id = ? AND institucion_id = ?"
            );
            $stmt->execute([$userId, $instId]);

            if (!empty($residenteIds)) {
                $insert = $db->prepare(
                    "INSERT INTO usuario_residentes (usuario_id, residente_id, institucion_id) VALUES (?, ?, ?)"
                );
                foreach ($residenteIds as $rid) {
                    $insert->execute([$userId, (int)$rid, $instId]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
