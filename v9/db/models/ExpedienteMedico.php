<?php
/**
 * GeriApp — Modelo Expediente Médico (NOM-004-SSA3-2012 / NOM-024-SSA3-2012)
 *
 * Tablas: expediente_hc, expediente_notas, expediente_enfermeria,
 *         expediente_estudios, expediente_consentimientos,
 *         expediente_auditoria, cie10_catalogo
 */

require_once __DIR__ . '/../Database.php';

class ExpedienteMedico
{
    // ─── HISTORIA CLÍNICA (1 por residente) ──────────────────────────────────

    public static function getHC(int $residente_id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM expediente_hc WHERE residente_id = ? LIMIT 1");
        $stmt->execute([$residente_id]);
        $row = $stmt->fetch();
        if ($row) {
            foreach (['ficha_identificacion','antecedentes_heredo','interrogatorio_aparatos',
                       'exploracion_fisica','signos_vitales_ingreso','diagnosticos',
                       'valoracion_geriatrica'] as $jsonCol) {
                if (!empty($row[$jsonCol]) && is_string($row[$jsonCol])) {
                    $row[$jsonCol] = json_decode($row[$jsonCol], true) ?: [];
                }
            }
        }
        return $row;
    }

    public static function createHC(array $data): int|false
    {
        $db = Database::getInstance();
        $jsonCols = ['ficha_identificacion','antecedentes_heredo','interrogatorio_aparatos',
                     'exploracion_fisica','signos_vitales_ingreso','diagnosticos','valoracion_geriatrica'];
        foreach ($jsonCols as $c) {
            if (isset($data[$c]) && is_array($data[$c])) {
                $data[$c] = json_encode($data[$c], JSON_UNESCAPED_UNICODE);
            }
        }

        $cols = ['residente_id','institucion_id','ficha_identificacion','antecedentes_heredo',
                 'antecedentes_patologicos','antecedentes_no_patologicos','padecimiento_actual',
                 'interrogatorio_aparatos','exploracion_fisica','signos_vitales_ingreso',
                 'estudios_previos','diagnosticos','pronostico','indicacion_terapeutica',
                 'grupo_sanguineo','alergias_detalle','plan_cuidados','dieta','movilidad',
                 'valoracion_geriatrica'];
        $sets = []; $params = [];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = $col;
                $params[":$col"] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $colStr = implode(',', $sets);
        $phStr  = implode(',', array_map(fn($c) => ":$c", $sets));

        $stmt = $db->prepare("INSERT INTO expediente_hc ($colStr) VALUES ($phStr)");
        return $stmt->execute($params) ? (int) $db->lastInsertId() : false;
    }

    public static function updateHC(int $id, array $data): bool
    {
        $jsonCols = ['ficha_identificacion','antecedentes_heredo','interrogatorio_aparatos',
                     'exploracion_fisica','signos_vitales_ingreso','diagnosticos','valoracion_geriatrica'];
        foreach ($jsonCols as $c) {
            if (isset($data[$c]) && is_array($data[$c])) {
                $data[$c] = json_encode($data[$c], JSON_UNESCAPED_UNICODE);
            }
        }

        $allowed = ['ficha_identificacion','antecedentes_heredo','antecedentes_patologicos',
                     'antecedentes_no_patologicos','padecimiento_actual','interrogatorio_aparatos',
                     'exploracion_fisica','signos_vitales_ingreso','estudios_previos','diagnosticos',
                     'pronostico','indicacion_terapeutica','grupo_sanguineo','alergias_detalle',
                     'plan_cuidados','dieta','movilidad','valoracion_geriatrica',
                     'firmado_por','firmado_at','firma_path'];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE expediente_hc SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public static function getOrCreateHC(int $residente_id, int $institucion_id): array|false
    {
        $hc = self::getHC($residente_id);
        if (!$hc) {
            $id = self::createHC([
                'residente_id'   => $residente_id,
                'institucion_id' => $institucion_id,
            ]);
            if (!$id) return false;
            $hc = self::getHC($residente_id);
        }
        return $hc;
    }

    // ─── NOTAS (Evolución / Interconsulta / Referencia / Ingreso / Egreso) ───

    public static function getNotas(int $residente_id, ?string $tipo = null, int $limit = 50, int $offset = 0): array
    {
        $db  = Database::getInstance();
        $sql = "SELECT n.*, u.nombre AS autor_nombre
                FROM expediente_notas n
                JOIN usuarios u ON u.id = n.usuario_id
                WHERE n.residente_id = ?";
        $params = [$residente_id];
        if ($tipo) {
            $sql .= " AND n.tipo = ?";
            $params[] = $tipo;
        }
        $sql .= " ORDER BY n.fecha DESC, n.hora DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            foreach (['signos_vitales','diagnosticos'] as $jc) {
                if (!empty($r[$jc]) && is_string($r[$jc])) {
                    $r[$jc] = json_decode($r[$jc], true) ?: [];
                }
            }
        }
        return $rows;
    }

    public static function getNota(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT n.*, u.nombre AS autor_nombre
             FROM expediente_notas n
             JOIN usuarios u ON u.id = n.usuario_id
             WHERE n.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            foreach (['signos_vitales','diagnosticos'] as $jc) {
                if (!empty($row[$jc]) && is_string($row[$jc])) {
                    $row[$jc] = json_decode($row[$jc], true) ?: [];
                }
            }
        }
        return $row;
    }

    public static function createNota(array $data): int|false
    {
        $db = Database::getInstance();
        foreach (['signos_vitales','diagnosticos'] as $jc) {
            if (isset($data[$jc]) && is_array($data[$jc])) {
                $data[$jc] = json_encode($data[$jc], JSON_UNESCAPED_UNICODE);
            }
        }
        $cols = ['residente_id','institucion_id','usuario_id','tipo','fecha','hora',
                 'subjetivo','objetivo','analisis','plan','signos_vitales','diagnosticos',
                 'pronostico','medico_solicitante','medico_consultado','especialidad',
                 'establecimiento_envia','establecimiento_recibe','motivo_envio',
                 'motivo_egreso','problemas_pendientes','recomendaciones',
                 'es_addendum','nota_padre_id'];
        $sets = []; $params = [];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = $col;
                $params[":$col"] = $data[$col];
            }
        }
        $colStr = implode(',', $sets);
        $phStr  = implode(',', array_map(fn($c) => ":$c", $sets));
        $stmt   = $db->prepare("INSERT INTO expediente_notas ($colStr) VALUES ($phStr)");
        return $stmt->execute($params) ? (int) $db->lastInsertId() : false;
    }

    public static function updateNota(int $id, array $data): bool
    {
        // Solo se puede editar si no está firmada
        $existing = self::getNota($id);
        if (!$existing || $existing['firmado']) return false;

        foreach (['signos_vitales','diagnosticos'] as $jc) {
            if (isset($data[$jc]) && is_array($data[$jc])) {
                $data[$jc] = json_encode($data[$jc], JSON_UNESCAPED_UNICODE);
            }
        }
        $allowed = ['tipo','fecha','hora','subjetivo','objetivo','analisis','plan',
                     'signos_vitales','diagnosticos','pronostico',
                     'medico_solicitante','medico_consultado','especialidad',
                     'establecimiento_envia','establecimiento_recibe','motivo_envio',
                     'motivo_egreso','problemas_pendientes','recomendaciones'];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE expediente_notas SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public static function firmarNota(int $id, int $usuario_id, ?string $firma_path = null): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE expediente_notas
             SET firmado = 1, firmado_por = ?, firmado_at = NOW(), firma_path = ?
             WHERE id = ? AND firmado = 0"
        );
        return $stmt->execute([$usuario_id, $firma_path, $id]);
    }

    public static function deleteNota(int $id): bool
    {
        // Solo se puede eliminar si no está firmada
        $existing = self::getNota($id);
        if (!$existing || $existing['firmado']) return false;
        $db   = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM expediente_notas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ─── ENFERMERÍA ──────────────────────────────────────────────────────────

    public static function getEnfermeria(int $residente_id, int $limit = 50, int $offset = 0): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT e.*, u.nombre AS autor_nombre
             FROM expediente_enfermeria e
             JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.residente_id = ?
             ORDER BY e.fecha DESC, FIELD(e.turno,'nocturno','vespertino','matutino')
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$residente_id, $limit, $offset]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            foreach (['signos_vitales','medicamentos_administrados','valoracion_dolor'] as $jc) {
                if (!empty($r[$jc]) && is_string($r[$jc])) {
                    $r[$jc] = json_decode($r[$jc], true) ?: [];
                }
            }
        }
        return $rows;
    }

    public static function getRegistroEnfermeria(int $id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT e.*, u.nombre AS autor_nombre
             FROM expediente_enfermeria e
             JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            foreach (['signos_vitales','medicamentos_administrados','valoracion_dolor'] as $jc) {
                if (!empty($row[$jc]) && is_string($row[$jc])) {
                    $row[$jc] = json_decode($row[$jc], true) ?: [];
                }
            }
        }
        return $row;
    }

    public static function createEnfermeria(array $data): int|false
    {
        $db = Database::getInstance();
        foreach (['signos_vitales','medicamentos_administrados','valoracion_dolor'] as $jc) {
            if (isset($data[$jc]) && is_array($data[$jc])) {
                $data[$jc] = json_encode($data[$jc], JSON_UNESCAPED_UNICODE);
            }
        }
        $cols = ['residente_id','institucion_id','usuario_id','fecha','turno',
                 'habitus_exterior','signos_vitales','medicamentos_administrados',
                 'procedimientos','observaciones','valoracion_dolor','riesgo_caidas'];
        $sets = []; $params = [];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = $col;
                $params[":$col"] = $data[$col];
            }
        }
        $colStr = implode(',', $sets);
        $phStr  = implode(',', array_map(fn($c) => ":$c", $sets));
        $stmt   = $db->prepare("INSERT INTO expediente_enfermeria ($colStr) VALUES ($phStr)");
        return $stmt->execute($params) ? (int) $db->lastInsertId() : false;
    }

    public static function updateEnfermeria(int $id, array $data): bool
    {
        $existing = self::getRegistroEnfermeria($id);
        if (!$existing || $existing['firmado']) return false;

        foreach (['signos_vitales','medicamentos_administrados','valoracion_dolor'] as $jc) {
            if (isset($data[$jc]) && is_array($data[$jc])) {
                $data[$jc] = json_encode($data[$jc], JSON_UNESCAPED_UNICODE);
            }
        }
        $allowed = ['fecha','turno','habitus_exterior','signos_vitales',
                     'medicamentos_administrados','procedimientos','observaciones',
                     'valoracion_dolor','riesgo_caidas'];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE expediente_enfermeria SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public static function firmarEnfermeria(int $id, ?string $firma_path = null): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE expediente_enfermeria SET firmado = 1, firmado_at = NOW(), firma_path = ? WHERE id = ? AND firmado = 0"
        );
        return $stmt->execute([$firma_path, $id]);
    }

    // ─── ESTUDIOS ────────────────────────────────────────────────────────────

    public static function getEstudios(int $residente_id, int $limit = 50, int $offset = 0): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT e.*, u.nombre AS autor_nombre
             FROM expediente_estudios e
             JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.residente_id = ?
             ORDER BY e.fecha DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$residente_id, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function createEstudio(array $data): int|false
    {
        $db = Database::getInstance();
        $cols = ['residente_id','institucion_id','usuario_id','fecha','hora',
                 'estudio_solicitado','problema_clinico','resultados','interpretacion',
                 'incidentes','realizado_por','informado_por','archivo_path','mime_type'];
        $sets = []; $params = [];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = $col;
                $params[":$col"] = $data[$col];
            }
        }
        $colStr = implode(',', $sets);
        $phStr  = implode(',', array_map(fn($c) => ":$c", $sets));
        $stmt   = $db->prepare("INSERT INTO expediente_estudios ($colStr) VALUES ($phStr)");
        return $stmt->execute($params) ? (int) $db->lastInsertId() : false;
    }

    public static function updateEstudio(int $id, array $data): bool
    {
        $allowed = ['fecha','hora','estudio_solicitado','problema_clinico','resultados',
                     'interpretacion','incidentes','realizado_por','informado_por',
                     'archivo_path','mime_type'];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]   = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE expediente_estudios SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public static function deleteEstudio(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM expediente_estudios WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ─── CONSENTIMIENTOS ─────────────────────────────────────────────────────

    public static function getConsentimientos(int $residente_id): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT c.*, u.nombre AS autor_nombre
             FROM expediente_consentimientos c
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.residente_id = ?
             ORDER BY c.fecha DESC"
        );
        $stmt->execute([$residente_id]);
        return $stmt->fetchAll();
    }

    public static function createConsentimiento(array $data): int|false
    {
        $db = Database::getInstance();
        $cols = ['residente_id','institucion_id','usuario_id','titulo','fecha',
                 'riesgos_beneficios','otorgante_nombre','otorgante_parentesco',
                 'firma_otorgante_path','medico_nombre','firma_medico_path',
                 'testigo1_nombre','testigo2_nombre','documento_path'];
        $sets = []; $params = [];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = $col;
                $params[":$col"] = $data[$col];
            }
        }
        $colStr = implode(',', $sets);
        $phStr  = implode(',', array_map(fn($c) => ":$c", $sets));
        $stmt   = $db->prepare("INSERT INTO expediente_consentimientos ($colStr) VALUES ($phStr)");
        return $stmt->execute($params) ? (int) $db->lastInsertId() : false;
    }

    // ─── AUDITORÍA (NOM-024 §6.6) ───────────────────────────────────────────

    public static function logAudit(int $residente_id, int $institucion_id, int $usuario_id,
                                    string $accion, string $entidad, ?int $entidad_id = null,
                                    ?array $detalles = null): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO expediente_auditoria
                 (residente_id, institucion_id, usuario_id, accion, entidad, entidad_id, detalles, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $residente_id, $institucion_id, $usuario_id, $accion, $entidad, $entidad_id,
            $detalles ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function getAuditLog(int $residente_id, int $limit = 100, int $offset = 0): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT a.*, u.nombre AS usuario_nombre
             FROM expediente_auditoria a
             JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.residente_id = ?
             ORDER BY a.creado_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$residente_id, $limit, $offset]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            if (!empty($r['detalles']) && is_string($r['detalles'])) {
                $r['detalles'] = json_decode($r['detalles'], true) ?: [];
            }
        }
        return $rows;
    }

    // ─── CIE-10 BÚSQUEDA ────────────────────────────────────────────────────

    public static function searchCIE10(string $query, int $limit = 20): array
    {
        $db = Database::getInstance();
        $query = trim($query);
        if (strlen($query) < 2) return [];

        // Try FULLTEXT first, fall back to LIKE
        if (strlen($query) >= 3) {
            $ftQuery = '+' . implode('* +', preg_split('/\s+/', $query)) . '*';
            $stmt = $db->prepare(
                "SELECT codigo, descripcion, favorito,
                        MATCH(codigo, descripcion) AGAINST(? IN BOOLEAN MODE) AS score
                 FROM cie10_catalogo
                 WHERE MATCH(codigo, descripcion) AGAINST(? IN BOOLEAN MODE)
                 ORDER BY favorito DESC, score DESC
                 LIMIT ?"
            );
            $stmt->execute([$ftQuery, $ftQuery, $limit]);
            $results = $stmt->fetchAll();
            if (!empty($results)) return $results;
        }

        // LIKE fallback (short queries or no fulltext match)
        $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $query) . '%';
        $stmt = $db->prepare(
            "SELECT codigo, descripcion, favorito
             FROM cie10_catalogo
             WHERE codigo LIKE ? OR descripcion LIKE ?
             ORDER BY favorito DESC, codigo ASC
             LIMIT ?"
        );
        $stmt->execute([$like, $like, $limit]);
        return $stmt->fetchAll();
    }
}
