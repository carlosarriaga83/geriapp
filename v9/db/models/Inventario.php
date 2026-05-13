<?php
/**
 * GeriApp — Modelo Inventario
 * Tablas: inventario_items, inventario_movimientos
 */

require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Inventario
{
    public static function getItems(int $instId, ?string $tipo = null, ?int $residenteId = null): array
    {
        $db = Database::getTenant($instId);
        $sql = "SELECT * FROM inventario_items WHERE institucion_id = ? AND activo = 1";
        $params = [$instId];
        if ($residenteId) {
            $sql .= " AND (residente_id IS NULL OR residente_id = ?)";
            $params[] = $residenteId;
        }
        if ($tipo) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(fn($r) => self::_decryptItemRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
        // §5 Sort by nombre in PHP (column may be encrypted)
        usort($rows, fn($a, $b) => strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? ''));
        return $rows;
    }

    public static function getById(int $id, int $instId): ?array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare("SELECT * FROM inventario_items WHERE id = ? AND institucion_id = ?");
        $stmt->execute([$id, $instId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::_decryptItemRow($row) : null;
    }

    public static function create(array $data): int
    {
        $db = Database::getTenant($data['institucion_id']);
        $enc = EncryptionMap::encryptRow('inventario_items', $data);
        $stmt = $db->prepare(
            "INSERT INTO inventario_items (institucion_id, residente_id, nombre, tipo, unidad, stock_actual, stock_minimo, vencimiento, notas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['institucion_id'],
            $data['residente_id'] ?? null,
            $enc['nombre'] ?? $data['nombre'],
            $data['tipo'] ?? 'medicamento',
            $data['unidad'] ?? 'unidades',
            $data['stock_actual'] ?? 0,
            $data['stock_minimo'] ?? 10,
            $data['vencimiento'] ?? null,
            $enc['notas'] ?? ($data['notas'] ?? null),
        ]);
        $id = (int) $db->lastInsertId();
        // §5 Dual-write _enc column if applicable
        self::_dualWriteEnc($db, $id, $enc);
        return $id;
    }

    public static function update(int $id, int $instId, array $data): bool
    {
        $db = Database::getTenant($instId);
        $enc = EncryptionMap::encryptRow('inventario_items', $data);
        $stmt = $db->prepare(
            "UPDATE inventario_items SET nombre=?, tipo=?, unidad=?, stock_minimo=?, vencimiento=?, notas=?
             WHERE id=? AND institucion_id=?"
        );
        $stmt->execute([
            $enc['nombre'] ?? $data['nombre'], $data['tipo'], $data['unidad'],
            $data['stock_minimo'], $data['vencimiento'] ?? null,
            $enc['notas'] ?? ($data['notas'] ?? null), $id, $instId,
        ]);
        self::_dualWriteEnc($db, $id, $enc);
        return $stmt->rowCount() >= 0;
    }

    public static function softDelete(int $id, int $instId): bool
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare("UPDATE inventario_items SET activo = 0 WHERE id = ? AND institucion_id = ?");
        $stmt->execute([$id, $instId]);
        return $stmt->rowCount() > 0;
    }

    public static function registrarMovimiento(array $data): int
    {
        $db = Database::getTenant($data['institucion_id']);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO inventario_movimientos (item_id, institucion_id, tipo, cantidad, residente_id, usuario_id, motivo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['item_id'],
                $data['institucion_id'],
                $data['tipo'],
                $data['cantidad'],
                $data['residente_id'] ?? null,
                $data['usuario_id'],
                $data['motivo'] ?? null,
            ]);
            $movId = (int) $db->lastInsertId();

            $delta = $data['tipo'] === 'entrada' ? (int) $data['cantidad'] : -(int) $data['cantidad'];
            $stmt2 = $db->prepare("UPDATE inventario_items SET stock_actual = GREATEST(0, stock_actual + ?) WHERE id = ? AND institucion_id = ?");
            $stmt2->execute([$delta, $data['item_id'], $data['institucion_id']]);

            $db->commit();
            return $movId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getMovimientos(int $instId, ?int $itemId = null, int $limit = 50, ?int $residenteId = null): array
    {
        $db = Database::getTenant($instId);
        $sql = "SELECT m.*, i.nombre AS item_nombre, u.nombre AS usuario_nombre
                FROM inventario_movimientos m
                LEFT JOIN inventario_items i ON i.id = m.item_id
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                WHERE m.institucion_id = ?";
        $params = [$instId];
        if ($itemId) {
            $sql .= " AND m.item_id = ?";
            $params[] = $itemId;
        }
        if ($residenteId) {
            $sql .= " AND (m.residente_id = ? OR (m.residente_id IS NULL AND i.residente_id = ?))";
            $params[] = $residenteId;
            $params[] = $residenteId;
        }
        $sql .= " ORDER BY m.creado_at DESC LIMIT " . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map(function($r) {
            // §5 Decrypt item_nombre (joined from inventario_items.nombre)
            if (isset($r['item_nombre'])) {
                $r['item_nombre'] = self::_decryptItemValue('nombre', $r['item_nombre']);
            }
            return $r;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function getMovimientoById(int $id, int $instId): ?array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare("SELECT m.*, i.nombre AS item_nombre, i.residente_id AS item_residente_id FROM inventario_movimientos m LEFT JOIN inventario_items i ON i.id = m.item_id WHERE m.id = ? AND m.institucion_id = ?");
        $stmt->execute([$id, $instId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        // §5 Decrypt item_nombre
        if (isset($row['item_nombre'])) {
            $row['item_nombre'] = self::_decryptItemValue('nombre', $row['item_nombre']);
        }
        return $row;
    }

    public static function updateMovimiento(int $id, int $instId, array $data): bool
    {
        $db = Database::getTenant($instId);
        $old = self::getMovimientoById($id, $instId);
        if (!$old) return false;

        $db->beginTransaction();
        try {
            // Reverse old stock change
            $oldDelta = $old['tipo'] === 'entrada' ? -(int)$old['cantidad'] : (int)$old['cantidad'];
            $db->prepare("UPDATE inventario_items SET stock_actual = GREATEST(0, stock_actual + ?) WHERE id = ? AND institucion_id = ?")
               ->execute([$oldDelta, $old['item_id'], $instId]);

            // Update movement record
            $db->prepare("UPDATE inventario_movimientos SET tipo=?, cantidad=?, motivo=? WHERE id=? AND institucion_id=?")
               ->execute([$data['tipo'], (int)$data['cantidad'], $data['motivo'] ?? null, $id, $instId]);

            // Apply new stock change
            $newDelta = $data['tipo'] === 'entrada' ? (int)$data['cantidad'] : -(int)$data['cantidad'];
            $db->prepare("UPDATE inventario_items SET stock_actual = GREATEST(0, stock_actual + ?) WHERE id = ? AND institucion_id = ?")
               ->execute([$newDelta, $old['item_id'], $instId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function deleteMovimiento(int $id, int $instId): bool
    {
        $db = Database::getTenant($instId);
        $old = self::getMovimientoById($id, $instId);
        if (!$old) return false;

        $db->beginTransaction();
        try {
            // Reverse stock change
            $delta = $old['tipo'] === 'entrada' ? -(int)$old['cantidad'] : (int)$old['cantidad'];
            $db->prepare("UPDATE inventario_items SET stock_actual = GREATEST(0, stock_actual + ?) WHERE id = ? AND institucion_id = ?")
               ->execute([$delta, $old['item_id'], $instId]);

            $db->prepare("DELETE FROM inventario_movimientos WHERE id = ? AND institucion_id = ?")
               ->execute([$id, $instId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getLowStock(int $instId, ?int $residenteId = null): array
    {
        $db = Database::getTenant($instId);
        $sql = "SELECT * FROM inventario_items WHERE institucion_id = ? AND activo = 1 AND stock_actual <= stock_minimo";
        $params = [$instId];
        if ($residenteId) {
            $sql .= " AND (residente_id IS NULL OR residente_id = ?)";
            $params[] = $residenteId;
        }
        $sql .= " ORDER BY stock_actual ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($r) => self::_decryptItemRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function _decryptItemRow(array $row): array
    {
        $row = EncryptionMap::decryptRow('inventario_items', $row);
        foreach (['nombre', 'notas'] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = self::_decryptItemValue($col, $row[$col]);
            }
        }
        return $row;
    }

    private static function _decryptItemValue(string $col, mixed $value): mixed
    {
        if ($value === null || $value === '' || !is_string($value)) return $value;
        try {
            return Cipher::decrypt($value);
        } catch (\Throwable $e) {
            error_log('[GeriApp] inventario decrypt fallback ' . $col . ': ' . $e->getMessage());
            return $value;
        }
    }

    // §5 Dual-write _enc columns
    private static function _dualWriteEnc(PDO $db, int $id, array $enc): void
    {
        try {
            $sets = []; $params = [];
            foreach ($enc as $col => $val) {
                if (str_ends_with($col, '_enc')) { $sets[] = "`$col` = ?"; $params[] = $val; }
            }
            if (empty($sets)) return;
            $params[] = $id;
            $db->prepare("UPDATE inventario_items SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        } catch (\Throwable $e) { /* _enc columns may not exist yet */ }
    }
}
