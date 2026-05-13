<?php
/**
 * §5 PHIPA/NOM — Mapa de Cifrado (Encryption Map)
 *
 * Define qué campos de qué tablas son candidatos a cifrado,
 * y controla el estado actual de la migración de cifrado
 * mediante el archivo conf/encryption_state.json.
 *
 * Fases por campo:
 *   none         → sin columna _enc
 *   prepared     → columna _enc creada (ALTER TABLE)
 *   migrated     → datos existentes cifrados en _enc
 *   active       → la app lee de _enc y escribe dual (plain + enc)
 *   consolidated → columnas originales eliminadas, _enc renombrada a original
 *                  la app lee/escribe cifrado directamente en la columna original
 *
 * Switch global (encryption_state.json → "read_encrypted"):
 *   true  = usar columnas cifradas (normal en active/consolidated)
 *   false = forzar lectura de columnas originales (bypass para emergencias)
 */

require_once __DIR__ . '/Cipher.php';

class EncryptionMap
{
    /**
     * Campos candidatos a cifrado, organizados por tabla.
     * 'phi'  = PHI (Protected Health Information) per PHIPA/HIPAA.
     * 'pii'  = PII (Personally Identifiable Information).
     * 'sens' = Datos sensibles (no salud, pero requieren protección).
     */
    /**
     * NOTA DE SEGURIDAD (auditoría v1.24.0, ampliada v1.28.0):
     * Los siguientes campos fueron EXCLUIDOS porque cifrarlos rompe funcionalidad:
     *
     *   residentes.nombre / apellidos          → WHERE LIKE (búsqueda), ORDER BY, CONCAT
     *   residentes.notas                        → LIKE '%import_uuid:%' (detección de duplicados)
     *   logs_sistema.detalle                    → LIKE en búsqueda de logs
     *
     *   (bitacora_entradas, historial_evoluciones — tablas eliminadas en v1.26.0)
     *
     * Campos incorporados en v1.28.0 con refactorización:
     *   prescripciones.nombre                   → ORDER BY movido a PHP usort()
     *   inventario_items.nombre                 → ORDER BY movido a PHP usort()
     *   cuidados_registros.datos                → JSON_EXTRACT reemplazado por columnas desnormalizadas
     *   cuidados_registros.observaciones        → LIKE CONCAT movido a matching en PHP
     *   notificaciones_log.mensaje              → sin restricciones SQL
     */
    public const FIELDS = [
        'residentes' => [
            'curp'                => ['label' => 'CURP',                'cat' => 'pii'],
            'nss'                 => ['label' => 'NSS',                 'cat' => 'pii'],
            'diagnostico'         => ['label' => 'Diagnóstico',         'cat' => 'phi'],
            'alergias'            => ['label' => 'Alergias',            'cat' => 'phi'],
            'cuidados_especiales' => ['label' => 'Cuidados especiales', 'cat' => 'phi'],
            'contacto_nombre'     => ['label' => 'Nombre contacto',     'cat' => 'pii'],
            'contacto_telefono'   => ['label' => 'Teléfono contacto',   'cat' => 'pii'],
            'contacto_telefono2'  => ['label' => 'Teléfono 2 contacto', 'cat' => 'pii'],
            'contacto_email'      => ['label' => 'Email contacto',      'cat' => 'pii'],
            'contacto_direccion'  => ['label' => 'Dirección contacto',  'cat' => 'pii'],
            'contactos_json'      => ['label' => 'Contactos (JSON)',    'cat' => 'pii'],
        ],
        'prescripciones' => [
            'nombre'        => ['label' => 'Nombre medicamento','cat' => 'phi'],
            'indicacion'    => ['label' => 'Indicación',        'cat' => 'phi'],
            'medico_nombre' => ['label' => 'Nombre del médico', 'cat' => 'phi'],
            'dosis'         => ['label' => 'Dosis',             'cat' => 'phi'],
            'via'           => ['label' => 'Vía',               'cat' => 'phi'],
            'frecuencia'    => ['label' => 'Frecuencia',        'cat' => 'phi'],
            'horarios'      => ['label' => 'Horarios (JSON)',   'cat' => 'phi'],
        ],
        'cuidados_registros' => [
            'datos'         => ['label' => 'Datos (JSON)',      'cat' => 'phi'],
            'observaciones' => ['label' => 'Observaciones',     'cat' => 'phi'],
        ],
        'notificaciones_log' => [
            'mensaje'       => ['label' => 'Mensaje',           'cat' => 'phi'],
        ],
        'inventario_items' => [
            'nombre'        => ['label' => 'Nombre artículo',   'cat' => 'phi'],
            'notas'         => ['label' => 'Notas artículo',    'cat' => 'phi'],
        ],
        'cuidados_notas' => [
            'nota' => ['label' => 'Nota de turno', 'cat' => 'phi'],
        ],
        'notas_medico' => [
            'contenido' => ['label' => 'Nota médica (valoración)', 'cat' => 'phi'],
        ],
        'chat_asistente_mensajes' => [
            'contenido' => ['label' => 'Contenido mensaje asistente', 'cat' => 'phi'],
        ],
        'logs_sistema' => [
            'ip'         => ['label' => 'Dirección IP',  'cat' => 'pii'],
            'user_agent' => ['label' => 'User-Agent',    'cat' => 'pii'],
        ],
        // historial_expedientes + historial_evoluciones removed in v1.26.0 (tables dropped)
        // configuracion: cifrado REMOVIDO en v1.49.8 — causaba corrupción de
        // contraseñas/keys al guardar valores enmascarados. Estos campos se
        // almacenan ahora en texto plano. La migración descifra los valores
        // existentes (ver Auditoría BD → migraciones).
    ];

    private const STATE_FILE = '/conf/encryption_state.json';

    // ── State persistence ────────────────────────────────────────────────

    private static function statePath(): string
    {
        return dirname(__DIR__) . self::STATE_FILE;
    }

    /** Lee el estado actual de cifrado. */
    public static function getState(): array
    {
        $path = self::statePath();
        if (!is_readable($path)) {
            return ['fields' => [], 'updated' => null];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : ['fields' => [], 'updated' => null];
    }

    /** Guarda el estado de cifrado. Lanza excepción si falla la escritura. */
    public static function saveState(array $state): void
    {
        $state['updated'] = date('c');
        $path = self::statePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $bytes = @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException("No se pudo escribir encryption_state.json en $path — verificar permisos del directorio conf/");
        }
    }

    /**
     * Obtiene la fase de un campo: none | prepared | migrated | active.
     */
    public static function fieldPhase(string $table, string $column): string
    {
        $state = self::getState();
        $key   = "$table.$column";
        return $state['fields'][$key]['phase'] ?? 'none';
    }

    /**
     * Establece la fase de un campo.
     */
    public static function setFieldPhase(string $table, string $column, string $phase, array $extra = []): void
    {
        $state = self::getState();
        $key   = "$table.$column";
        $state['fields'][$key] = array_merge(
            $state['fields'][$key] ?? [],
            ['phase' => $phase],
            $extra
        );
        self::saveState($state);
    }

    // ── Query helpers ────────────────────────────────────────────────────

    /**
     * Switch global: ¿leer de columnas cifradas?
     * En modo `false`, decryptRow() siempre devuelve la columna original sin descifrar.
     * Útil para emergencias o verificación manual.
     */
    public static function isReadEncrypted(): bool
    {
        $state = self::getState();
        return $state['read_encrypted'] ?? true; // default: leer cifrado si hay campos activos
    }

    /** Cambia el switch global de lectura. */
    public static function setReadEncrypted(bool $enabled): void
    {
        $state = self::getState();
        $state['read_encrypted'] = $enabled;
        self::saveState($state);
    }

    /**
     * ¿Debe la app leer de la columna _enc para este campo?
     * Respeta el switch global y la fase del campo.
     */
    public static function isActive(string $table, string $column): bool
    {
        if (!self::isReadEncrypted()) return false;
        $phase = self::fieldPhase($table, $column);
        return in_array($phase, ['active', 'consolidated'], true);
    }

    /**
     * Cifra un array asociativo según los campos activos de una tabla.
     * - active:       escribe dual (original plain + _enc cifrado)
     * - consolidated: escribe cifrado directamente en la columna original
     */
    public static function encryptRow(string $table, array $row): array
    {
        if (!isset(self::FIELDS[$table])) return $row;

        $state = self::getState();
        foreach (self::FIELDS[$table] as $col => $meta) {
            $key   = "$table.$col";
            $phase = $state['fields'][$key]['phase'] ?? 'none';

            if ($phase === 'consolidated') {
                // Columna _enc ya no existe; cifrar directamente en la original
                if (array_key_exists($col, $row) && $row[$col] !== null && $row[$col] !== '') {
                    $row[$col] = Cipher::encrypt($row[$col]);
                }
            } elseif (in_array($phase, ['prepared', 'migrated', 'active'], true)) {
                // Dual-write: mantener original plain + escribir _enc cifrado
                if (array_key_exists($col, $row) && $row[$col] !== null && $row[$col] !== '') {
                    $row[$col . '_enc'] = Cipher::encrypt($row[$col]);
                } else {
                    $row[$col . '_enc'] = $row[$col] ?? null;
                }
            }
        }
        return $row;
    }

    /**
     * Descifra un array asociativo según los campos activos de una tabla.
     * - active:       lee de _enc si existe
     * - consolidated: la columna original YA contiene cifrado, descifrar directamente
     * - switch off:   no descifra nada (devuelve tal cual)
     */
    public static function decryptRow(string $table, array $row): array
    {
        if (!isset(self::FIELDS[$table])) return $row;

        $readEnc = self::isReadEncrypted();
        $state   = self::getState();
        foreach (self::FIELDS[$table] as $col => $meta) {
            $key   = "$table.$col";
            $phase = $state['fields'][$key]['phase'] ?? 'none';

            if (!$readEnc) continue; // switch off: no descifrar

            try {
                if ($phase === 'consolidated') {
                    // Columna original contiene cifrado directo
                    if (array_key_exists($col, $row) && $row[$col] !== null) {
                        $row[$col] = Cipher::decrypt($row[$col]);
                    }
                } elseif ($phase === 'active' && array_key_exists($col . '_enc', $row)) {
                    $row[$col] = Cipher::decrypt($row[$col . '_enc']);
                }
            } catch (\Throwable $e) {
                // Un campo corrupto no debe tumbar toda la fila.
                // Dejar el valor tal cual y loguear.
                error_log("[EncryptionMap] decryptRow $table.$col failed: " . $e->getMessage());
            }
        }
        return $row;
    }

    /**
     * Dual-write: UPDATE _enc columns after INSERT (phases prepared/migrated/active).
     * Generic helper so callers outside models can do dual-write.
     */
    public static function dualWriteEnc(PDO $db, string $table, int $id, array $originalData): void
    {
        try {
            $enc = self::encryptRow($table, $originalData);
            $sets = []; $params = [];
            foreach ($enc as $col => $val) {
                if (str_ends_with($col, '_enc')) { $sets[] = "`$col` = ?"; $params[] = $val; }
            }
            if (empty($sets)) return;
            $params[] = $id;
            $db->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        } catch (\Throwable $e) { /* _enc columns may not exist yet */ }
    }
}
