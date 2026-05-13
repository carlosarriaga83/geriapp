<?php
/**
 * GeriApp — Modelo Configuracion
 * Tabla: configuracion (1 fila por institución)
 *
 * Uso:
 *   require_once BASE_PATH . '/db/models/Configuracion.php';
 *   $cfg = Configuracion::getOrCreate($institucion_id);
 */

require_once __DIR__ . '/../Database.php';
require_once dirname(__DIR__, 2) . '/includes/EncryptionMap.php';

class Configuracion
{
    // ─── READ ────────────────────────────────────────────────────────────────

    /**
     * Obtiene la configuración de una institución.
     */
    public static function getByInstitucion(int $institucion_id): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM configuracion WHERE institucion_id = ? LIMIT 1"
        );
        $stmt->execute([$institucion_id]);
        $row = $stmt->fetch();
        return $row ? EncryptionMap::decryptRow('configuracion', $row) : false;
    }

    /**
     * Obtiene la configuración, creándola vacía si no existe.
     */
    public static function getOrCreate(int $institucion_id): array|false
    {
        $cfg = self::getByInstitucion($institucion_id);
        if (!$cfg) {
            self::create($institucion_id);
            $cfg = self::getByInstitucion($institucion_id);
        }
        return $cfg;
    }

    /**
     * Obtiene configuración cacheada en sesión (TTL 5 min).
     * Reduce queries en endpoints que solo leen config.
     *
     * Invalidación cruzada entre sesiones: cuando un admin guarda la
     * configuración (incluida la matriz `roles_permisos`), `clearCache()`
     * actualiza el mtime del archivo `conf/.cfg_v_<inst>`. Aquí comparamos
     * ese mtime contra el timestamp de nuestra caché: si el flag es más
     * reciente, refrescamos. Esto hace que los cambios de permisos se
     * reflejen INMEDIATAMENTE en las sesiones de otros usuarios sin
     * tener que esperar al TTL de 5 minutos ni cerrar sesión.
     */
    public static function getCached(int $institucion_id): array|false
    {
        $cacheKey = '_cfg_cache_' . $institucion_id;
        $ttlKey   = '_cfg_cache_at_' . $institucion_id;
        $flag     = dirname(__DIR__, 2) . '/conf/.cfg_v_' . $institucion_id;
        $flagAt   = is_file($flag) ? (int)@filemtime($flag) : 0;
        if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$ttlKey])
            && (time() - $_SESSION[$ttlKey]) < 300
            && $_SESSION[$ttlKey] >= $flagAt) {
            return $_SESSION[$cacheKey];
        }
        $cfg = self::getOrCreate($institucion_id);
        if ($cfg) {
            $_SESSION[$cacheKey] = $cfg;
            $_SESSION[$ttlKey]   = time();
        }
        return $cfg;
    }

    /**
     * Invalida la cache de sesión para una institución.
     *
     * Además de limpiar la sesión actual, actualiza el mtime del archivo
     * `conf/.cfg_v_<inst>` para forzar el refresh en TODAS las sesiones
     * activas de cualquier usuario de la institución en su próxima
     * llamada a `getCached()` (ver invalidación cruzada arriba).
     */
    public static function clearCache(int $institucion_id): void
    {
        unset($_SESSION['_cfg_cache_' . $institucion_id], $_SESSION['_cfg_cache_at_' . $institucion_id]);
        $flag = dirname(__DIR__, 2) . '/conf/.cfg_v_' . $institucion_id;
        $dir  = dirname($flag);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        @touch($flag);
    }

    // ─── WRITE ───────────────────────────────────────────────────────────────

    /**
     * Crea una fila de configuración vacía para una institución.
     * Usa INSERT IGNORE para evitar duplicados.
     */
    public static function create(int $institucion_id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT IGNORE INTO configuracion (institucion_id) VALUES (?)"
        );
        return $stmt->execute([$institucion_id]);
    }

    /**
     * Actualiza campos de configuración para una institución.
     *
     * Campos editables — SMTP:
     *   smtp_host, smtp_port, smtp_usuario, smtp_password,
     *   smtp_encriptacion, smtp_from_email, smtp_from_nombre
     *
     * Campos editables — WhatsApp:
     *   wa_proveedor, wa_api_key, wa_instance_id, wa_phone, wa_activo
     *
     * Campos editables — Notificaciones:
     *   notif_alertas, notif_bitacora, notif_familiar
     *
     * Campos editables — Sistema:
     *   timezone, idioma, fecha_formato
     */
    public static function update(int $institucion_id, array $data): bool
    {
        $db = Database::getInstance();
        $existingColumns = self::tableColumns($db, 'configuracion');
        $allowed = [
            // SMTP
            'smtp_host', 'smtp_port', 'smtp_usuario', 'smtp_password',
            'smtp_encriptacion', 'smtp_timeout', 'smtp_sandbox', 'smtp_from_email', 'smtp_from_nombre',
            // WhatsApp
            'wa_proveedor', 'wa_api_key', 'wa_instance_id', 'wa_phone', 'wa_activo', 'wa_sandbox',
            // Notificaciones (alertas clínicas)
            'notif_alertas', 'notif_bitacora', 'notif_familiar',
            'notif_vitales', 'notif_meds', 'notif_caida', 'notif_condicion',
            'notif_canal_sistema', 'notif_canal_email', 'notif_canal_wa',
            // Turnos
            'turno_mat_inicio', 'turno_mat_fin', 'turno_mat_siglas',
            'turno_ves_inicio', 'turno_ves_fin', 'turno_ves_siglas',
            'turno_noc_inicio', 'turno_noc_fin', 'turno_noc_siglas',
            // Seguridad
            'seg_pass_min_len', 'seg_pass_expira_dias',
            'seg_2fa', 'seg_timeout_sesion', 'seg_una_sesion', 'seg_log_accesos',
            'seg_max_intentos', 'seg_bloqueo_min',
            // Sistema / backup
            'app_url', 'inst_nombre', 'moneda',
            'timezone', 'idioma', 'fecha_formato',
            'backup_frecuencia', 'backup_hora',
            // Legal
            'legal_cc_email',
            // Soporte / contacto humano
            'support_phone',
            // Roles y permisos (JSON)
            'roles_permisos',
            // IA
            'ia_proveedor', 'ia_api_key', 'ia_modelo', 'ia_prompt', 'ia_max_palabras',
        ];
        $sets   = [];
        $params = [];

        // §5 Encrypt data for consolidated phase (modifies values in-place)
        $encData = EncryptionMap::encryptRow('configuracion', $data);

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                if (is_array($existingColumns) && !isset($existingColumns[$col])) continue;
                $sets[]   = "$col = ?";
                $params[] = $encData[$col] ?? $data[$col];
            }
        }
        if (empty($sets)) return true;

        $params[] = $institucion_id;
        $stmt = $db->prepare(
            "UPDATE configuracion SET " . implode(', ', $sets) . " WHERE institucion_id = ?"
        );
        $ok = $stmt->execute($params);

        // §5 Dual-write _enc columns (active phase)
        if ($ok) {
            try {
                $encSets = []; $encParams = [];
                foreach ($encData as $col => $val) {
                    if (str_ends_with($col, '_enc')) { $encSets[] = "`$col` = ?"; $encParams[] = $val; }
                }
                if (!empty($encSets)) {
                    $encParams[] = $institucion_id;
                    $db->prepare("UPDATE configuracion SET " . implode(', ', $encSets) . " WHERE institucion_id = ?")
                       ->execute($encParams);
                }
            } catch (Throwable $e) { /* _enc columns may not exist yet */ }
        }

        return $ok;
    }

    /**
     * Upsert: actualiza si existe, crea si no.
     */
    public static function upsert(int $institucion_id, array $data): bool
    {
        self::create($institucion_id);          // INSERT IGNORE — no-op si ya existe
        return self::update($institucion_id, $data);
    }

    private static function tableColumns(PDO $db, string $table): ?array
    {
        static $cache = [];
        $key = $table;
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `{$table}`");
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!empty($row['Field'])) $cols[$row['Field']] = true;
            }
            return $cache[$key] = $cols;
        } catch (Throwable $e) {
            return $cache[$key] = null;
        }
    }
}
