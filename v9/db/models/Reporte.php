<?php
/**
 * GeriApp — Modelo Reporte
 *
 * Agrega datos de cuidados_registros, signos vitales y medicamentos
 * para construir reportes por residente en un rango de fechas.
 *
 * Migrado en v1.26.0: las queries antes consultaban bitacora_entradas/bitacora_turnos
 * (tablas legacy eliminadas). Ahora usan cuidados_registros exclusivamente.
 */

require_once __DIR__ . '/../Database.php';
require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Reporte
{
    // ─────────────────────────────────────────────────────────────────────────
    // Conteo de eventos por categoría en un rango (para gráficas de barras)
    // ─────────────────────────────────────────────────────────────────────────
    public static function getEventosPorCategoria(int $instId, int $resId, string $desde, string $hasta): array
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT cr.categoria AS tipo,
                    COUNT(*) AS total
             FROM cuidados_registros cr
             WHERE cr.institucion_id = ?
               AND cr.residente_id   = ?
               AND cr.fecha BETWEEN ? AND ?
             GROUP BY cr.categoria
             ORDER BY total DESC"
        );
        $stmt->execute([$instId, $resId, $desde, $hasta]);
        return $stmt->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Conteo diario de eventos de una categoría (para gráfica de línea)
    // ─────────────────────────────────────────────────────────────────────────
    public static function getEventosPorDia(int $instId, int $resId, string $desde, string $hasta, string $tipo = ''): array
    {
        $db     = Database::getTenant($instId);
        $params = [$instId, $resId, $desde, $hasta];
        $tipoSql = '';
        if ($tipo !== '') {
            $tipoSql  = " AND cr.categoria = ?";
            $params[] = $tipo;
        }
        $stmt = $db->prepare(
            "SELECT cr.fecha,
                    COUNT(*) AS total
             FROM cuidados_registros cr
             WHERE cr.institucion_id = ?
               AND cr.residente_id   = ?
               AND cr.fecha BETWEEN ? AND ?
               $tipoSql
             GROUP BY cr.fecha
             ORDER BY cr.fecha ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Horas de sueño por día (categoria = 'sueno', duracion_min denormalized)
    // ─────────────────────────────────────────────────────────────────────────
    public static function getSuenioPorDia(int $instId, int $resId, string $desde, string $hasta): array
    {
        $db   = Database::getTenant($instId);
        // §5 Use denormalized duracion_min column (replaces JSON_EXTRACT on encrypted datos)
        $stmt = $db->prepare(
            "SELECT cr.fecha,
                    SUM(COALESCE(cr.duracion_min, 0)) AS total_mins
             FROM cuidados_registros cr
             WHERE cr.institucion_id = ?
               AND cr.residente_id   = ?
               AND cr.fecha BETWEEN ? AND ?
               AND cr.categoria = 'sueno'
             GROUP BY cr.fecha
             ORDER BY cr.fecha ASC"
        );
        $stmt->execute([$instId, $resId, $desde, $hasta]);
        return $stmt->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Prescripciones activas del residente con conteo de tomas registradas
    // ─────────────────────────────────────────────────────────────────────────
    public static function getMedicamentos(int $instId, int $resId, string $desde, string $hasta): array
    {
        $db   = Database::getTenant($instId);

        // Total de días en el período
        $totalDias = max(1, (int)round((strtotime($hasta) - strtotime($desde)) / 86400) + 1);

        // §5 Since prescripciones.nombre and cuidados_registros.observaciones are encrypted,
        // we can't do LIKE CONCAT in SQL. Fetch prescriptions + med records separately
        // and match in PHP after decryption.

        // 1. Get active prescriptions (decrypted)
        $stmtP = $db->prepare(
            "SELECT p.id, p.nombre, p.dosis, p.frecuencia
             FROM prescripciones p
             WHERE p.residente_id = ? AND p.activo = 1"
        );
        $stmtP->execute([$resId]);
        $prescripciones = array_map(
            fn($r) => EncryptionMap::decryptRow('prescripciones', $r),
            $stmtP->fetchAll()
        );

        // 2. Get medication records in range (decrypted observaciones)
        $stmtR = $db->prepare(
            "SELECT cr.id, cr.observaciones
             FROM cuidados_registros cr
             WHERE cr.residente_id = ?
               AND cr.categoria = 'medicacion'
               AND cr.fecha BETWEEN ? AND ?"
        );
        $stmtR->execute([$resId, $desde, $hasta]);
        $records = array_map(
            fn($r) => EncryptionMap::decryptRow('cuidados_registros', $r),
            $stmtR->fetchAll()
        );

        // 3. Match: count records whose observaciones contain the prescription name
        return array_map(function($p) use ($records, $totalDias) {
            $nombre = $p['nombre'] ?? '';
            $tomas = 0;
            if ($nombre !== '') {
                foreach ($records as $rec) {
                    if (stripos($rec['observaciones'] ?? '', $nombre) !== false) {
                        $tomas++;
                    }
                }
            }
            $frec = (int)($p['frecuencia'] ?? 1);
            $esperadas = max(1, $totalDias * $frec);
            $p['tomas_registradas'] = $tomas;
            $p['adherencia_pct']    = min(100, round($tomas / $esperadas * 100));
            return $p;
        }, $prescripciones);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Últimas N entradas de texto para el resumen narrativo
    // ─────────────────────────────────────────────────────────────────────────
    public static function getEntradas(int $instId, int $resId, string $desde, string $hasta, int $limit = 60): array
    {
        $db   = Database::getTenant($instId);
        $stmt = $db->prepare(
            "SELECT cr.fecha,
                    cr.categoria  AS entrada_tipo,
                    cr.observaciones AS contenido,
                    u.nombre      AS autor_nombre
             FROM cuidados_registros cr
             LEFT JOIN usuarios u ON u.id = cr.usuario_id
             WHERE cr.institucion_id = ?
               AND cr.residente_id   = ?
               AND cr.fecha BETWEEN ? AND ?
             ORDER BY cr.fecha DESC, cr.id ASC
             LIMIT ?"
        );
        $stmt->execute([$instId, $resId, $desde, $hasta, $limit]);
        return array_map(fn($r) => EncryptionMap::decryptRow('cuidados_registros', $r), $stmt->fetchAll());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resumen completo: agrupa todo para el reporte de un residente
    // ─────────────────────────────────────────────────────────────────────────
    public static function getResumenCompleto(int $instId, int $resId, string $desde, string $hasta): array
    {
        return [
            'categorias'   => self::getEventosPorCategoria($instId, $resId, $desde, $hasta),
            'por_dia'      => self::getEventosPorDia($instId, $resId, $desde, $hasta),
            'suenio'       => self::getSuenioPorDia($instId, $resId, $desde, $hasta),
            'medicamentos' => self::getMedicamentos($instId, $resId, $desde, $hasta),
            'entradas'     => self::getEntradas($instId, $resId, $desde, $hasta),
        ];
    }
}
