<?php
/**
 * GeriApp — Model: Cuidado
 * CRUD para registros de cuidados estructurados.
 */

require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Cuidado
{
    // ── Categorías válidas ──────────────────────────────────────────────────
    const CATEGORIAS = [
        'sueno', 'alimentacion', 'medicacion', 'higiene',
        'terapia', 'movilidad', 'eliminacion', 'comportamiento',
        'signos_vitales', 'incidente'
    ];

    // ── Helper: decrypt + decode datos/observaciones ───────────────────────
    private static function _decryptRecord(array $r): array
    {
        $r = EncryptionMap::decryptRow('cuidados_registros', $r);
        if (!empty($r['datos']) && is_string($r['datos'])) {
            $r['datos'] = json_decode($r['datos'], true);
        }
        return $r;
    }

    // ── Helper: encrypt datos/observaciones for write ───────────────────────
    private static function _encryptForWrite(array $data): array
    {
        $row = [];
        if (isset($data['datos'])) {
            $row['datos'] = is_string($data['datos']) ? $data['datos'] : json_encode($data['datos'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('observaciones', $data)) {
            $row['observaciones'] = $data['observaciones'];
        }
        return EncryptionMap::encryptRow('cuidados_registros', $row);
    }

    // ── Helper: extract denormalized columns from datos ─────────────────────
    private static function _denormCols(string $cat, ?array $datos): array
    {
        $cols = ['subtipo' => null, 'pendiente' => 0, 'duracion_min' => null];
        if (!$datos) return $cols;
        if ($cat === 'eliminacion' && !empty($datos['tipo_eliminacion'])) {
            $cols['subtipo'] = $datos['tipo_eliminacion'];
        }
        if ($cat === 'sueno' && !empty($datos['pendiente'])) {
            $cols['pendiente'] = 1;
        }
        if ($cat === 'terapia' && isset($datos['duracion_min'])) {
            $cols['duracion_min'] = (int) $datos['duracion_min'];
        }
        return $cols;
    }

    // ── Obtener registros de un residente por fecha ─────────────────────────
    public static function getByFecha(int $instId, int $residenteId, string $fecha): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT cr.*, u.nombre AS usuario_nombre
             FROM cuidados_registros cr
             LEFT JOIN usuarios u ON u.id = cr.usuario_id
             WHERE cr.institucion_id = ? AND cr.residente_id = ? AND cr.fecha = ?
             ORDER BY cr.hora ASC, cr.creado_at ASC"
        );
        $stmt->execute([$instId, $residenteId, $fecha]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, '_decryptRecord'], $rows);
    }

    // ── Contar registros por categoría para un día ──────────────────────────
    public static function countByCategoria(int $instId, int $residenteId, string $fecha): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT categoria, COUNT(*) AS total
             FROM cuidados_registros
             WHERE institucion_id = ? AND residente_id = ? AND fecha = ?
             GROUP BY categoria"
        );
        $stmt->execute([$instId, $residenteId, $fecha]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = array_fill_keys(self::CATEGORIAS, 0);
        foreach ($rows as $r) {
            $counts[$r['categoria']] = (int) $r['total'];
        }
        return $counts;
    }

    // ── Obtener por ID ──────────────────────────────────────────────────────
    public static function getById(int $id, int $instId): ?array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT cr.*, u.nombre AS usuario_nombre
             FROM cuidados_registros cr
             LEFT JOIN usuarios u ON u.id = cr.usuario_id
             WHERE cr.id = ? AND cr.institucion_id = ?"
        );
        $stmt->execute([$id, $instId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::_decryptRecord($row) : null;
    }

    // ── Crear registro ──────────────────────────────────────────────────────
    public static function create(array $data): int
    {
        $db = Database::getTenant($data['institucion_id']);
        $bio = !empty($data['verificacion_biometrica']) ? 1 : 0;
        $tsf = $bio ? date('Y-m-d H:i:s') : null;

        // §5 Encrypt datos + observaciones
        $enc = self::_encryptForWrite($data);
        $datosStr = $enc['datos'] ?? (isset($data['datos']) ? json_encode($data['datos'], JSON_UNESCAPED_UNICODE) : null);
        $obsStr   = $enc['observaciones'] ?? ($data['observaciones'] ?? null);

        // §5 Denormalized columns for SQL queries on encrypted datos
        $dnorm = self::_denormCols($data['categoria'], $data['datos'] ?? null);

        $baseParams = [
            $data['residente_id'],
            $data['institucion_id'],
            $data['usuario_id'],
            $data['categoria'],
            $datosStr,
            $obsStr,
            $data['fecha'],
            $data['hora'],
            $dnorm['subtipo'],
            $dnorm['pendiente'],
            $dnorm['duracion_min'],
        ];
        try {
            $stmt = $db->prepare(
                "INSERT INTO cuidados_registros
                 (residente_id, institucion_id, usuario_id, categoria, datos, observaciones, fecha, hora,
                  subtipo, pendiente, duracion_min, verificacion_biometrica, timestamp_firma)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array_merge($baseParams, [$bio, $tsf]));
        } catch (\PDOException $e) {
            // Fallback if denorm/biometric columns don't exist yet
            $stmt = $db->prepare(
                "INSERT INTO cuidados_registros
                 (residente_id, institucion_id, usuario_id, categoria, datos, observaciones, fecha, hora)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['residente_id'], $data['institucion_id'], $data['usuario_id'],
                $data['categoria'], $datosStr, $obsStr, $data['fecha'], $data['hora'],
            ]);
        }
        $id = (int) $db->lastInsertId();
        // §5 Dual-write _enc columns if applicable
        self::_dualWriteEnc($db, $id, $enc);
        return $id;
    }

    // ── Última evacuación de heces ──────────────────────────────────────────
    public static function getUltimaHeces(int $instId, int $residenteId): ?array
    {
        $db = Database::getTenant($instId);
        // §5 Use denormalized subtipo column (replaces JSON_EXTRACT on encrypted datos)
        $stmt = $db->prepare(
            "SELECT fecha, hora FROM cuidados_registros
             WHERE institucion_id = ? AND residente_id = ? AND categoria = 'eliminacion'
               AND subtipo = 'Heces'
             ORDER BY fecha DESC, hora DESC
             LIMIT 1"
        );
        $stmt->execute([$instId, $residenteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Último registro de signos vitales (para badge de alerta) ────────────
    public static function getUltimosSignos(int $instId, int $residenteId): ?array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT datos, fecha, hora FROM cuidados_registros
             WHERE institucion_id = ? AND residente_id = ? AND categoria = 'signos_vitales'
             ORDER BY fecha DESC, hora DESC
             LIMIT 1"
        );
        $stmt->execute([$instId, $residenteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        // §5 Decrypt datos column
        $row = EncryptionMap::decryptRow('cuidados_registros', $row);
        $datos = is_string($row['datos']) ? json_decode($row['datos'], true) : $row['datos'];
        if (!is_array($datos)) return null;
        $datos['_fecha'] = $row['fecha'];
        $datos['_hora']  = $row['hora'];
        return $datos;
    }

    // ── Sueño pendiente (hora_fin sin completar) ────────────────────────────
    public static function getSuenoPendiente(int $instId, int $residenteId): ?array
    {
        $db = Database::getTenant($instId);
        // §5 Use denormalized pendiente column (replaces JSON_EXTRACT on encrypted datos)
        $stmt = $db->prepare(
            "SELECT id, datos, fecha, hora, creado_at FROM cuidados_registros
             WHERE institucion_id = ? AND residente_id = ? AND categoria = 'sueno'
               AND pendiente = 1
             ORDER BY fecha DESC, hora DESC
             LIMIT 1"
        );
        $stmt->execute([$instId, $residenteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row = EncryptionMap::decryptRow('cuidados_registros', $row);
        $row['datos'] = is_string($row['datos']) ? json_decode($row['datos'], true) : $row['datos'];
        return $row;
    }

    // ── Eliminar registro ───────────────────────────────────────────────────
    public static function delete(int $id, int $instId): bool
    {
        $db = Database::getTenant($instId);
        $db->beginTransaction();
        try {
            // Remove medico alerts tied to this record first to avoid orphaned alerts
            // (table may not exist in older installs).
            try {
                $delAlert = $db->prepare("DELETE FROM alertas_medico WHERE registro_id = ? AND institucion_id = ?");
                $delAlert->execute([$id, $instId]);
            } catch (\Throwable $e) {}

            $stmt = $db->prepare(
                "DELETE FROM cuidados_registros WHERE id = ? AND institucion_id = ?"
            );
            $stmt->execute([$id, $instId]);
            $ok = $stmt->rowCount() > 0;

            $db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ── Actualizar registro (datos + observaciones) ─────────────────────────
    public static function update(int $id, int $instId, array $data): bool
    {
        $db = Database::getTenant($instId);
        // §5 Encrypt datos + observaciones
        $enc = self::_encryptForWrite($data);
        $datosStr = $enc['datos'] ?? (isset($data['datos']) ? json_encode($data['datos'], JSON_UNESCAPED_UNICODE) : null);
        $obsStr   = $enc['observaciones'] ?? ($data['observaciones'] ?? null);

        // §5 Denormalized columns — need category to determine which to set
        $cat = null;
        try {
            $st = $db->prepare("SELECT categoria FROM cuidados_registros WHERE id = ? AND institucion_id = ? LIMIT 1");
            $st->execute([$id, $instId]);
            $cat = $st->fetchColumn() ?: null;
        } catch (\Throwable $e) {}

        $dnorm = self::_denormCols($cat ?? '', $data['datos'] ?? null);

        try {
            $stmt = $db->prepare(
                "UPDATE cuidados_registros SET datos = ?, observaciones = ?, hora = ?,
                 subtipo = ?, pendiente = ?, duracion_min = ? WHERE id = ? AND institucion_id = ?"
            );
            $stmt->execute([
                $datosStr, $obsStr, $data['hora'] ?? null,
                $dnorm['subtipo'], $dnorm['pendiente'], $dnorm['duracion_min'],
                $id, $instId,
            ]);
        } catch (\PDOException $e) {
            // Fallback if denorm columns don't exist yet
            $stmt = $db->prepare(
                "UPDATE cuidados_registros SET datos = ?, observaciones = ?, hora = ? WHERE id = ? AND institucion_id = ?"
            );
            $stmt->execute([$datosStr, $obsStr, $data['hora'] ?? null, $id, $instId]);
        }
        // §5 Dual-write _enc columns
        self::_dualWriteEnc($db, $id, $enc);
        return true;
    }

    // ── Obtener registros por rango de fechas (para reportes) ───────────────
    public static function getByRango(int $instId, int $residenteId, string $desde, string $hasta): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT cr.*, u.nombre AS usuario_nombre
             FROM cuidados_registros cr
             LEFT JOIN usuarios u ON u.id = cr.usuario_id
             WHERE cr.institucion_id = ? AND cr.residente_id = ? AND cr.fecha BETWEEN ? AND ?
             ORDER BY cr.fecha DESC, cr.hora DESC"
        );
        $stmt->execute([$instId, $residenteId, $desde, $hasta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, '_decryptRecord'], $rows);
    }

    // ── Estadísticas por rango (para reportes) ──────────────────────────────
    public static function statsByRango(int $instId, int $residenteId, string $desde, string $hasta): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT categoria, COUNT(*) AS total
             FROM cuidados_registros
             WHERE institucion_id = ? AND residente_id = ? AND fecha BETWEEN ? AND ?
             GROUP BY categoria"
        );
        $stmt->execute([$instId, $residenteId, $desde, $hasta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats = array_fill_keys(self::CATEGORIAS, 0);
        foreach ($rows as $r) {
            $stats[$r['categoria']] = (int) $r['total'];
        }
        return $stats;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NOTAS DE TURNO
    // ═══════════════════════════════════════════════════════════════════════

    public static function getNotas(int $instId, int $residenteId, string $fecha): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT n.*, u.nombre AS usuario_nombre
             FROM cuidados_notas n
             LEFT JOIN usuarios u ON u.id = n.usuario_id
             WHERE n.institucion_id = ? AND n.residente_id = ? AND n.fecha = ?
             ORDER BY n.creado_at ASC"
        );
        $stmt->execute([$instId, $residenteId, $fecha]);
        return array_map(
            fn($r) => EncryptionMap::decryptRow('cuidados_notas', $r),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public static function createNota(array $data): int
    {
        $db = Database::getTenant($data['institucion_id']);
        // §5 Cifrado: encrypt nota for consolidated phase
        $enc = EncryptionMap::encryptRow('cuidados_notas', ['nota' => $data['nota']]);
        $stmt = $db->prepare(
            "INSERT INTO cuidados_notas (institucion_id, residente_id, usuario_id, nota, prioridad, fecha, imagen)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['institucion_id'],
            $data['residente_id'],
            $data['usuario_id'],
            $enc['nota'],
            $data['prioridad'] ?? 'normal',
            $data['fecha'],
            $data['imagen'] ?? null,
        ]);
        $id = (int) $db->lastInsertId();
        // §5 Dual-write _enc (active phase)
        try {
            if (isset($enc['nota_enc'])) {
                $db->prepare("UPDATE cuidados_notas SET nota_enc = ? WHERE id = ?")
                   ->execute([$enc['nota_enc'], $id]);
            }
        } catch (Throwable $e) { /* _enc column may not exist yet */ }
        return $id;
    }

    public static function updateNota(int $id, int $instId, int $userId, string $nota): bool
    {
        $db = Database::getTenant($instId);
        // §5 Cifrado: encrypt nota
        $enc = EncryptionMap::encryptRow('cuidados_notas', ['nota' => $nota]);
        $stmt = $db->prepare(
            "UPDATE cuidados_notas SET nota = ? WHERE id = ? AND institucion_id = ? AND usuario_id = ?"
        );
        $stmt->execute([$enc['nota'], $id, $instId, $userId]);
        $ok = $stmt->rowCount() > 0;
        // §5 Dual-write _enc (active phase)
        if ($ok) {
            try {
                if (isset($enc['nota_enc'])) {
                    $db->prepare("UPDATE cuidados_notas SET nota_enc = ? WHERE id = ? AND institucion_id = ?")
                       ->execute([$enc['nota_enc'], $id, $instId]);
                }
            } catch (Throwable $e) { /* _enc column may not exist yet */ }
        }
        return $ok;
    }

    public static function deleteNota(int $id, int $instId): bool
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare("DELETE FROM cuidados_notas WHERE id = ? AND institucion_id = ?");
        $stmt->execute([$id, $instId]);
        return $stmt->rowCount() > 0;
    }

    public static function countNotas(int $instId, int $residenteId, string $fecha): int
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM cuidados_notas WHERE institucion_id = ? AND residente_id = ? AND fecha = ?"
        );
        $stmt->execute([$instId, $residenteId, $fecha]);
        return (int) $stmt->fetchColumn();
    }

    // §5 Dual-write _enc columns for cuidados_registros
    private static function _dualWriteEnc(PDO $db, int $id, array $enc): void
    {
        try {
            $sets = []; $params = [];
            foreach ($enc as $col => $val) {
                if (str_ends_with($col, '_enc')) { $sets[] = "`$col` = ?"; $params[] = $val; }
            }
            if (empty($sets)) return;
            $params[] = $id;
            $db->prepare("UPDATE cuidados_registros SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        } catch (\Throwable $e) { /* _enc columns may not exist yet */ }
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Notas de Médico — valoraciones/indicaciones persistentes (SOAP NOM-004)
    // ══════════════════════════════════════════════════════════════════════

    /** Decode adjuntos JSON safely. */
    private static function _decodeAdjuntos(?string $raw): array
    {
        if (!$raw) return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    /** Enrich row with decoded adjuntos. */
    private static function _enrichNotaMedico(array $row): array
    {
        $row['adjuntos'] = self::_decodeAdjuntos($row['adjuntos'] ?? null);
        $row['recetas']  = self::_decodeAdjuntos($row['recetas']  ?? null);
        return $row;
    }

    /** Obtener la nota vigente de un residente (o null). */
    public static function getNotaMedicoVigente(int $instId, int $residenteId): ?array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT n.*, u.nombre AS medico_nombre
             FROM notas_medico n
             LEFT JOIN usuarios u ON u.id = n.usuario_id
             WHERE n.institucion_id = ? AND n.residente_id = ? AND n.estado = 'vigente'
             ORDER BY n.creado_at DESC LIMIT 1"
        );
        $stmt->execute([$instId, $residenteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::_enrichNotaMedico(EncryptionMap::decryptRow('notas_medico', $row)) : null;
    }

    /** Historial archivado de notas médicas. */
    public static function getNotasMedicoArchivo(int $instId, int $residenteId, int $limit = 20): array
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT n.*, u.nombre AS medico_nombre
             FROM notas_medico n
             LEFT JOIN usuarios u ON u.id = n.usuario_id
             WHERE n.institucion_id = ? AND n.residente_id = ? AND n.estado = 'archivada'
             ORDER BY n.archivada_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$instId, $residenteId]);
        return array_map(
            fn($r) => self::_enrichNotaMedico(EncryptionMap::decryptRow('notas_medico', $r)),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /** Crear nota médica — archiva automáticamente la anterior vigente. */
    public static function createNotaMedico(array $data): int
    {
        $db = Database::getTenant($data['institucion_id']);
        // Archivar nota vigente anterior
        $db->prepare(
            "UPDATE notas_medico SET estado = 'archivada', archivada_at = NOW(), archivada_por = ?
             WHERE institucion_id = ? AND residente_id = ? AND estado = 'vigente'"
        )->execute([$data['usuario_id'], $data['institucion_id'], $data['residente_id']]);

        // §5 Cifrado
        $enc = EncryptionMap::encryptRow('notas_medico', ['contenido' => $data['contenido']]);
        $adjuntos = !empty($data['adjuntos']) ? json_encode($data['adjuntos'], JSON_UNESCAPED_UNICODE) : null;
        $recetas  = !empty($data['recetas'])  ? json_encode($data['recetas'],  JSON_UNESCAPED_UNICODE) : null;
        $hasCustomTs = !empty($data['creado_at']);
        if ($hasCustomTs) {
            $stmt = $db->prepare(
                "INSERT INTO notas_medico (institucion_id, residente_id, usuario_id, contenido, adjuntos, recetas, estado, creado_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'vigente', ?)"
            );
            $stmt->execute([
                $data['institucion_id'],
                $data['residente_id'],
                $data['usuario_id'],
                $enc['contenido'],
                $adjuntos,
                $recetas,
                $data['creado_at'],
            ]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO notas_medico (institucion_id, residente_id, usuario_id, contenido, adjuntos, recetas, estado)
                 VALUES (?, ?, ?, ?, ?, ?, 'vigente')"
            );
            $stmt->execute([
                $data['institucion_id'],
                $data['residente_id'],
                $data['usuario_id'],
                $enc['contenido'],
                $adjuntos,
                $recetas,
            ]);
        }
        $id = (int) $db->lastInsertId();
        // §5 Dual-write _enc
        try {
            if (isset($enc['contenido_enc'])) {
                $db->prepare("UPDATE notas_medico SET contenido_enc = ? WHERE id = ?")
                   ->execute([$enc['contenido_enc'], $id]);
            }
        } catch (\Throwable $e) { /* _enc column may not exist yet */ }
        return $id;
    }

    /** Actualizar nota médica (autor médico o superadmin, solo vigente). */
    public static function updateNotaMedico(int $id, int $instId, ?int $authorId, string $contenido, ?array $adjuntos = null, ?array $recetas = null, ?string $creadoAt = null): bool
    {
        $db = Database::getTenant($instId);
        $enc = EncryptionMap::encryptRow('notas_medico', ['contenido' => $contenido]);
        $adjJson = $adjuntos !== null ? json_encode($adjuntos, JSON_UNESCAPED_UNICODE) : null;
        $rxJson  = $recetas  !== null ? json_encode($recetas,  JSON_UNESCAPED_UNICODE) : null;
        $tsCol   = $creadoAt !== null ? ', creado_at = ?' : '';
           $authorWhere = $authorId !== null ? ' AND usuario_id = ?' : '';
        if ($adjuntos !== null) {
              $sql = "UPDATE notas_medico SET contenido = ?, adjuntos = ?, recetas = ?, updated_at = NOW()$tsCol
                  WHERE id = ? AND institucion_id = ?$authorWhere AND estado IN ('vigente','archivada')";
            $params = [$enc['contenido'], $adjJson, $rxJson];
            if ($creadoAt !== null) $params[] = $creadoAt;
              $params[] = $id; $params[] = $instId;
              if ($authorId !== null) $params[] = $authorId;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
              $sql = "UPDATE notas_medico SET contenido = ?, recetas = ?, updated_at = NOW()$tsCol
                  WHERE id = ? AND institucion_id = ?$authorWhere AND estado IN ('vigente','archivada')";
            $params = [$enc['contenido'], $rxJson];
            if ($creadoAt !== null) $params[] = $creadoAt;
              $params[] = $id; $params[] = $instId;
              if ($authorId !== null) $params[] = $authorId;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            try {
                if (isset($enc['contenido_enc'])) {
                    $db->prepare("UPDATE notas_medico SET contenido_enc = ? WHERE id = ?")
                       ->execute([$enc['contenido_enc'], $id]);
                }
            } catch (\Throwable $e) {}
        }
        return $ok;
    }

    /** Archivar una nota médica vigente. */
    public static function archivarNotaMedico(int $id, int $instId, int $userId): bool
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "UPDATE notas_medico SET estado = 'archivada', archivada_at = NOW(), archivada_por = ?
             WHERE id = ? AND institucion_id = ? AND estado = 'vigente'"
        );
        $stmt->execute([$userId, $id, $instId]);
        return $stmt->rowCount() > 0;
    }

    /** Convertir una nota archivada en vigente; archiva cualquier vigente previa del residente. */
    public static function hacerVigenteNotaMedico(int $id, int $instId, ?int $authorId, int $actorId): bool
    {
        $db = Database::getTenant($instId);
        $authorWhere = $authorId !== null ? " AND usuario_id = ?" : "";
        $params = [$id, $instId];
        if ($authorId !== null) $params[] = $authorId;

        $info = $db->prepare("SELECT id, residente_id, estado FROM notas_medico WHERE id = ? AND institucion_id = ?$authorWhere LIMIT 1");
        $info->execute($params);
        $meta = $info->fetch(PDO::FETCH_ASSOC);
        if (!$meta) return false;

        if (($meta['estado'] ?? '') === 'vigente') return true;

        try {
            $db->beginTransaction();
            $db->prepare(
                "UPDATE notas_medico SET estado = 'archivada', archivada_at = NOW(), archivada_por = ?
                 WHERE institucion_id = ? AND residente_id = ? AND estado = 'vigente' AND id <> ?"
            )->execute([$actorId, $instId, (int)$meta['residente_id'], $id]);

            $stmt = $db->prepare(
                "UPDATE notas_medico
                 SET estado = 'vigente', archivada_at = NULL, archivada_por = NULL, updated_at = NOW()
                 WHERE id = ? AND institucion_id = ?$authorWhere AND estado = 'archivada'"
            );
            $stmt->execute($params);
            $ok = $stmt->rowCount() > 0;
            $db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Eliminar nota médica. También borra los
     *  documentos del expediente generados al crear/actualizar la nota
     *  (heurística: mismo residente + mismo autor + tipo nota_medico/receta
     *  + fecha_documento = DATE(creado_at de la nota)). */
    public static function deleteNotaMedico(int $id, int $instId, ?int $authorId = null): bool
    {
        $db = Database::getTenant($instId);
        // 1) Recuperar metadata para borrar docs del expediente asociados
        $authorWhere = $authorId !== null ? " AND usuario_id = ?" : "";
        $info = $db->prepare("SELECT residente_id, usuario_id, creado_at FROM notas_medico WHERE id = ? AND institucion_id = ?" . $authorWhere);
        $params = [$id, $instId];
        if ($authorId !== null) $params[] = $authorId;
        $info->execute($params);
        $meta = $info->fetch(PDO::FETCH_ASSOC);
        if (!$meta) return false;
        // 2) Borrar nota
        $stmt = $db->prepare("DELETE FROM notas_medico WHERE id = ? AND institucion_id = ?" . $authorWhere);
        $stmt->execute($params);
        $ok = $stmt->rowCount() > 0;
        // 3) Borrar docs del expediente vinculados (best-effort)
        if ($ok && $meta) {
            try {
                $fecha = substr((string)($meta['creado_at'] ?? ''), 0, 10);
                if ($fecha) {
                    $db->prepare(
                        "DELETE FROM expediente_docs
                         WHERE institucion_id = ? AND residente_id = ? AND created_by = ?
                           AND tipo IN ('nota_medico','receta') AND fecha_documento = ?"
                    )->execute([$instId, (int) $meta['residente_id'], (int) $meta['usuario_id'], $fecha]);
                }
            } catch (\Throwable $e) { /* tabla puede no existir o columnas distintas */ }
        }
        return $ok;
    }

    /** Contar notas vigentes (para badge). */
    public static function countNotasMedicoVigentes(int $instId, int $residenteId): int
    {
        $db = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM notas_medico WHERE institucion_id = ? AND residente_id = ? AND estado = 'vigente'"
        );
        $stmt->execute([$instId, $residenteId]);
        return (int) $stmt->fetchColumn();
    }
}
