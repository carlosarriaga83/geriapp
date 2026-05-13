<?php
/**
 * GeriApp — Migración de sincronización de esquema v1.9.7
 *
 * Consolida TODAS las migraciones necesarias para que la base de datos
 * sea compatible con el código actual del sistema.
 *
 * Incluye:
 *   • Tablas faltantes: notificaciones_sistema, notificaciones_sistema_log,
 *     bitacora_firmas, residentes_estado_log
 *   • Columnas extras en: configuracion, residentes, usuarios,
 *     instituciones, prescripciones, usuario_instituciones,
 *     notificaciones_sistema, cuidados_notas
 *   • ENUMs ampliados: historial_evoluciones.tipo (+prescripcion)
 *
 * Es IDEMPOTENTE — se puede ejecutar múltiples veces sin problema.
 *
 * Uso CLI:   php v6/db/migrate_sync_schema.php
 * Uso Web:   https://dominio/v6/db/migrate_sync_schema.php?token=geriapp-deploy-2026
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    $token = 'geriapp-deploy-2026';
    if (($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        die('403 — Token requerido: ?token=...');
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== GeriApp — Sync Schema Migration ===\n\n";

require_once dirname(__DIR__) . '/conf/config.db.php';
require_once __DIR__ . '/Database.php';

$db = Database::getMaster();

$ok     = 0;
$skip   = 0;
$errors = [];

// ── Helpers ──────────────────────────────────────────────────────────────────
function addCol(PDO $pdo, string $table, string $col, string $def, int &$ok, int &$skip, array &$errors): void {
    $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col));
    if ($check->rowCount() > 0) {
        echo "  · $table.$col ya existe\n";
        $skip++;
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        echo "  + $table.$col AGREGADA\n";
        $ok++;
    } catch (PDOException $e) {
        $msg = "  ✗ $table.$col ERROR: " . $e->getMessage();
        echo $msg . "\n";
        $errors[] = $msg;
    }
}

function createTbl(PDO $pdo, string $name, string $sql, int &$ok, int &$skip, array &$errors): void {
    try {
        $pdo->exec($sql);
        // Check if it was actually created or already existed
        echo "  + Tabla $name OK\n";
        $ok++;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'already exists')) {
            echo "  · Tabla $name ya existe\n";
            $skip++;
        } else {
            $msg = "  ✗ Tabla $name ERROR: " . $e->getMessage();
            echo $msg . "\n";
            $errors[] = $msg;
        }
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return $check->rowCount() > 0;
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. TABLAS FALTANTES
// ═══════════════════════════════════════════════════════════════════════════
echo "── [1] Tablas faltantes ─────────────────\n\n";

// 1a. notificaciones_sistema
createTbl($db, 'notificaciones_sistema', "
CREATE TABLE IF NOT EXISTS `notificaciones_sistema` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`     INT UNSIGNED NOT NULL,
  `titulo`             VARCHAR(200) NOT NULL,
  `mensaje`            TEXT         NOT NULL,
  `tipo`               ENUM('info','alerta','pregunta') NOT NULL DEFAULT 'info',
  `opciones_respuesta` JSON         DEFAULT NULL,
  `imagen_url`         VARCHAR(500) DEFAULT NULL,
  `activo`             TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_por`         INT UNSIGNED NOT NULL,
  `roles_destino`      JSON         DEFAULT NULL,
  `creado_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ns_inst` (`institucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $ok, $skip, $errors);

// 1b. notificaciones_sistema_log
createTbl($db, 'notificaciones_sistema_log', "
CREATE TABLE IF NOT EXISTS `notificaciones_sistema_log` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notificacion_id` INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `visto_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `respuesta`       TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nsl_notif`   (`notificacion_id`),
  KEY `idx_nsl_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $ok, $skip, $errors);

// FK for notificaciones_sistema_log → notificaciones_sistema (si ambas existen)
if (tableExists($db, 'notificaciones_sistema') && tableExists($db, 'notificaciones_sistema_log')) {
    try {
        $db->exec("ALTER TABLE `notificaciones_sistema_log`
            ADD CONSTRAINT `fk_nsl_notif` FOREIGN KEY (`notificacion_id`)
            REFERENCES `notificaciones_sistema` (`id`) ON DELETE CASCADE");
        echo "  + FK fk_nsl_notif agregada\n";
        $ok++;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'already exists')) {
            echo "  · FK fk_nsl_notif ya existe\n";
            $skip++;
        } else {
            echo "  · FK fk_nsl_notif omitida: " . $e->getMessage() . "\n";
        }
    }
}

// 1c. bitacora_firmas
createTbl($db, 'bitacora_firmas', "
CREATE TABLE IF NOT EXISTS `bitacora_firmas` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `turno_id`     INT UNSIGNED NOT NULL,
  `residente_id` INT UNSIGNED NOT NULL,
  `firmado_por`  INT UNSIGNED DEFAULT NULL,
  `firmado_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_turno_res` (`turno_id`,`residente_id`),
  KEY `fk_firma_turno` (`turno_id`),
  KEY `fk_firma_res`   (`residente_id`),
  KEY `fk_firma_usr`   (`firmado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $ok, $skip, $errors);

// 1d. residentes_estado_log
createTbl($db, 'residentes_estado_log', "
CREATE TABLE IF NOT EXISTS `residentes_estado_log` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `estado_anterior` VARCHAR(30)  NOT NULL,
  `estado_nuevo`    VARCHAR(30)  NOT NULL,
  `usuario_id`      INT UNSIGNED DEFAULT NULL,
  `usuario_nombre`  VARCHAR(150) DEFAULT NULL,
  `nota`            TEXT         DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estado_log_res`   (`residente_id`),
  KEY `idx_estado_log_inst`  (`institucion_id`),
  KEY `idx_estado_log_fecha` (`creado_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $ok, $skip, $errors);

// 1e. notificaciones_log
createTbl($db, 'notificaciones_log', "
CREATE TABLE IF NOT EXISTS `notificaciones_log` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `institucion_id` INT NOT NULL,
  `residente_id`   INT DEFAULT NULL,
  `destinatario`   VARCHAR(255) DEFAULT NULL,
  `canal`          VARCHAR(20)  NOT NULL DEFAULT 'whatsapp',
  `tipo`           VARCHAR(50)  DEFAULT NULL,
  `mensaje`        TEXT         DEFAULT NULL,
  `estado`         VARCHAR(20)  NOT NULL DEFAULT 'enviado',
  `error_detalle`  TEXT         DEFAULT NULL,
  `fecha`          DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $ok, $skip, $errors);

// ═══════════════════════════════════════════════════════════════════════════
// 2. COLUMNAS FALTANTES — RESIDENTES
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [2] Residentes — columnas extra ─────\n\n";

$resCols = [
    ['estado_civil',         "VARCHAR(50)  DEFAULT NULL"],
    ['cuidados_especiales',  "TEXT DEFAULT NULL"],
    ['tipo_sangre',          "VARCHAR(10) DEFAULT NULL"],
    ['contacto_nombre',      "VARCHAR(200) DEFAULT NULL"],
    ['contacto_parentesco',  "VARCHAR(100) DEFAULT NULL"],
    ['contacto_telefono',    "VARCHAR(30)  DEFAULT NULL"],
    ['contacto_telefono2',   "VARCHAR(30)  DEFAULT NULL"],
    ['contacto_email',       "VARCHAR(200) DEFAULT NULL"],
    ['contacto_direccion',   "VARCHAR(300) DEFAULT NULL"],
    ['contactos_json',       "TEXT DEFAULT NULL"],
];
foreach ($resCols as [$col, $def]) {
    addCol($db, 'residentes', $col, $def, $ok, $skip, $errors);
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. COLUMNAS FALTANTES — CONFIGURACION
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [3] Configuración — columnas extra ──\n\n";

$cfgCols = [
    // SMTP extras
    ['smtp_timeout',        "SMALLINT UNSIGNED DEFAULT 20"],
    ['smtp_sandbox',        "TINYINT(1) NOT NULL DEFAULT 1"],
    ['wa_sandbox',          "TINYINT(1) NOT NULL DEFAULT 1"],
    // Turnos
    ['turno_mat_inicio',    "VARCHAR(5) DEFAULT '07:00'"],
    ['turno_mat_fin',       "VARCHAR(5) DEFAULT '15:00'"],
    ['turno_mat_siglas',    "VARCHAR(4) DEFAULT 'TM'"],
    ['turno_ves_inicio',    "VARCHAR(5) DEFAULT '15:00'"],
    ['turno_ves_fin',       "VARCHAR(5) DEFAULT '23:00'"],
    ['turno_ves_siglas',    "VARCHAR(4) DEFAULT 'TV'"],
    ['turno_noc_inicio',    "VARCHAR(5) DEFAULT '23:00'"],
    ['turno_noc_fin',       "VARCHAR(5) DEFAULT '07:00'"],
    ['turno_noc_siglas',    "VARCHAR(4) DEFAULT 'TN'"],
    // Seguridad
    ['seg_pass_min_len',    "TINYINT UNSIGNED DEFAULT 8"],
    ['seg_pass_expira_dias',"SMALLINT UNSIGNED DEFAULT 90"],
    ['seg_2fa',             "TINYINT(1) DEFAULT 0"],
    ['seg_timeout_sesion',  "TINYINT(1) DEFAULT 1"],
    ['seg_una_sesion',      "TINYINT(1) DEFAULT 0"],
    ['seg_log_accesos',     "TINYINT(1) DEFAULT 1"],
    ['seg_max_intentos',    "TINYINT UNSIGNED DEFAULT 5"],
    ['seg_bloqueo_min',     "SMALLINT UNSIGNED DEFAULT 15"],
    // Backup
    ['backup_frecuencia',   "VARCHAR(20) DEFAULT 'diario'"],
    ['backup_hora',         "VARCHAR(5) DEFAULT '02:00'"],
    // Notificaciones extra
    ['notif_vitales',       "TINYINT(1) DEFAULT 1"],
    ['notif_meds',          "TINYINT(1) DEFAULT 1"],
    ['notif_caida',         "TINYINT(1) DEFAULT 1"],
    ['notif_condicion',     "TINYINT(1) DEFAULT 0"],
    ['notif_canal_sistema', "TINYINT(1) DEFAULT 1"],
    ['notif_canal_email',   "TINYINT(1) DEFAULT 0"],
    ['notif_canal_wa',      "TINYINT(1) DEFAULT 0"],
    // Roles / Permisos
    ['roles_permisos',      "JSON DEFAULT NULL"],
    // IA
    ['ia_proveedor',        "VARCHAR(30) DEFAULT 'openai'"],
    ['ia_api_key',          "VARCHAR(500) DEFAULT NULL"],
    ['ia_modelo',           "VARCHAR(100) DEFAULT NULL"],
    ['ia_prompt',           "TEXT DEFAULT NULL"],
    ['ia_max_palabras',     "INT DEFAULT NULL"],
    // Sistema
    ['inst_nombre',         "VARCHAR(200) DEFAULT NULL"],
    ['moneda',              "VARCHAR(10) NOT NULL DEFAULT 'MXN'"],
    ['timezone',            "VARCHAR(60) NOT NULL DEFAULT 'America/Mexico_City'"],
    ['idioma',              "VARCHAR(10) NOT NULL DEFAULT 'es'"],
    ['fecha_formato',       "VARCHAR(20) NOT NULL DEFAULT 'd/m/Y'"],
    ['app_url',             "VARCHAR(255) DEFAULT NULL"],
];
foreach ($cfgCols as [$col, $def]) {
    addCol($db, 'configuracion', $col, $def, $ok, $skip, $errors);
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. COLUMNAS FALTANTES — USUARIOS
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [4] Usuarios — columnas extra ───────\n\n";

addCol($db, 'usuarios', 'telefono', "VARCHAR(30) DEFAULT NULL", $ok, $skip, $errors);
addCol($db, 'usuarios', 'preferencias', "JSON DEFAULT NULL", $ok, $skip, $errors);

// ═══════════════════════════════════════════════════════════════════════════
// 5. COLUMNAS FALTANTES — INSTITUCIONES
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [5] Instituciones — columnas extra ──\n\n";

addCol($db, 'instituciones', 'db_name',     "VARCHAR(80)  DEFAULT NULL", $ok, $skip, $errors);
addCol($db, 'instituciones', 'rfc',         "VARCHAR(20)  DEFAULT NULL", $ok, $skip, $errors);
addCol($db, 'instituciones', 'ciudad',      "VARCHAR(100) DEFAULT NULL", $ok, $skip, $errors);
addCol($db, 'instituciones', 'estado_inst', "VARCHAR(60)  DEFAULT NULL", $ok, $skip, $errors);
addCol($db, 'instituciones', 'num_camas',   "SMALLINT UNSIGNED DEFAULT NULL", $ok, $skip, $errors);

// ═══════════════════════════════════════════════════════════════════════════
// 6. COLUMNAS FALTANTES — PRESCRIPCIONES
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [6] Prescripciones — columnas extra ─\n\n";

addCol($db, 'prescripciones', 'imagen', "VARCHAR(500) DEFAULT NULL", $ok, $skip, $errors);

// ═══════════════════════════════════════════════════════════════════════════
// 7. COLUMNAS FALTANTES — USUARIO_INSTITUCIONES
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [7] Usuario-instituciones — columnas ─\n\n";

addCol($db, 'usuario_instituciones', 'estado',     "ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'", $ok, $skip, $errors);
addCol($db, 'usuario_instituciones', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $ok, $skip, $errors);

// ═══════════════════════════════════════════════════════════════════════════
// 8. COLUMNAS FALTANTES — NOTIFICACIONES_SISTEMA (imagen_url)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [8] Notificaciones_sistema — imagen ─\n\n";

if (tableExists($db, 'notificaciones_sistema')) {
    addCol($db, 'notificaciones_sistema', 'imagen_url', "VARCHAR(500) DEFAULT NULL AFTER `opciones_respuesta`", $ok, $skip, $errors);
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. COLUMNAS FALTANTES — CUIDADOS_NOTAS (imagen)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [9] Cuidados_notas — imagen ─────────\n\n";

if (tableExists($db, 'cuidados_notas')) {
    addCol($db, 'cuidados_notas', 'imagen', "VARCHAR(300) DEFAULT NULL", $ok, $skip, $errors);
}

// ═══════════════════════════════════════════════════════════════════════════
// 10. ENUM AMPLIADO — historial_evoluciones.tipo (+prescripcion)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [10] Enum historial_evoluciones.tipo ─\n\n";

if (tableExists($db, 'historial_evoluciones')) {
    try {
        $col = $db->query("SHOW COLUMNS FROM `historial_evoluciones` LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains($col['Type'], 'prescripcion')) {
            $db->exec("ALTER TABLE `historial_evoluciones`
                MODIFY COLUMN `tipo`
                ENUM('evolucion','nota_enfermeria','valoracion','interconsulta','prescripcion')
                NOT NULL DEFAULT 'evolucion'");
            echo "  + historial_evoluciones.tipo ENUM ampliado con 'prescripcion'\n";
            $ok++;
        } else {
            echo "  · historial_evoluciones.tipo ya contiene 'prescripcion'\n";
            $skip++;
        }
    } catch (PDOException $e) {
        $msg = "  ✗ historial_evoluciones.tipo ERROR: " . $e->getMessage();
        echo $msg . "\n";
        $errors[] = $msg;
    }

    // Índice en tipo
    try {
        $db->exec("ALTER TABLE `historial_evoluciones` ADD INDEX `idx_hev_tipo` (`tipo`)");
        echo "  + Índice idx_hev_tipo creado\n";
        $ok++;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            echo "  · Índice idx_hev_tipo ya existe\n";
            $skip++;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 11. INVENTARIO — residente_id + index
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [11] Inventario — residente_id ──────\n\n";

if (tableExists($db, 'inventario_items')) {
    addCol($db, 'inventario_items', 'residente_id', "INT UNSIGNED DEFAULT NULL AFTER `institucion_id`", $ok, $skip, $errors);
    try {
        $db->exec("ALTER TABLE `inventario_items` ADD INDEX `idx_ii_residente` (`residente_id`)");
        echo "  + Index idx_ii_residente agregado\n";
        $ok++;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            echo "  · Index idx_ii_residente ya existe\n";
            $skip++;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 12. TENANT DB — replicar lo esencial
// ═══════════════════════════════════════════════════════════════════════════
echo "\n── [12] Tenant DBs ────────────────────\n";

$instituciones = [];
try {
    $instituciones = $db->query("SELECT id, db_name FROM instituciones WHERE db_name IS NOT NULL AND db_name != ''")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "  ⚠ No hay instituciones con db_name — omitido\n";
}

foreach ($instituciones as $inst) {
    $iid = (int)$inst['id'];
    echo "\n  [Institución $iid] {$inst['db_name']}\n";
    try {
        $tdb = Database::getTenant($iid);
    } catch (PDOException $e) {
        echo "    ✗ No se pudo conectar: " . $e->getMessage() . "\n";
        continue;
    }

    // Residentes extra columns
    foreach ($resCols as [$col, $def]) {
        addCol($tdb, 'residentes', $col, $def, $ok, $skip, $errors);
    }

    // Configuracion extra columns (si tiene tabla propia)
    if (tableExists($tdb, 'configuracion')) {
        foreach ($cfgCols as [$col, $def]) {
            addCol($tdb, 'configuracion', $col, $def, $ok, $skip, $errors);
        }
    }

    // Tablas de notificaciones
    createTbl($tdb, "notificaciones_sistema (tenant $iid)", "
    CREATE TABLE IF NOT EXISTS `notificaciones_sistema` (
      `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `institucion_id`     INT UNSIGNED NOT NULL,
      `titulo`             VARCHAR(200) NOT NULL,
      `mensaje`            TEXT         NOT NULL,
      `tipo`               ENUM('info','alerta','pregunta') NOT NULL DEFAULT 'info',
      `opciones_respuesta` JSON         DEFAULT NULL,
      `imagen_url`         VARCHAR(500) DEFAULT NULL,
      `activo`             TINYINT(1)   NOT NULL DEFAULT 1,
      `creado_por`         INT UNSIGNED NOT NULL,
      `roles_destino`      JSON         DEFAULT NULL,
      `creado_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_ns_inst` (`institucion_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", $ok, $skip, $errors);

    createTbl($tdb, "notificaciones_sistema_log (tenant $iid)", "
    CREATE TABLE IF NOT EXISTS `notificaciones_sistema_log` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `notificacion_id` INT UNSIGNED NOT NULL,
      `institucion_id`  INT UNSIGNED NOT NULL,
      `usuario_id`      INT UNSIGNED NOT NULL,
      `visto_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `respuesta`       TEXT         DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_nsl_notif`   (`notificacion_id`),
      KEY `idx_nsl_usuario` (`usuario_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", $ok, $skip, $errors);

    // notificaciones_sistema imagen_url
    if (tableExists($tdb, 'notificaciones_sistema')) {
        addCol($tdb, 'notificaciones_sistema', 'imagen_url', "VARCHAR(500) DEFAULT NULL AFTER `opciones_respuesta`", $ok, $skip, $errors);
    }

    // cuidados_notas imagen
    if (tableExists($tdb, 'cuidados_notas')) {
        addCol($tdb, 'cuidados_notas', 'imagen', "VARCHAR(300) DEFAULT NULL", $ok, $skip, $errors);
    }

    // cuidados_registros — columnas desnormalizadas (v1.28.0 cifrado fase 2)
    if (tableExists($tdb, 'cuidados_registros')) {
        addCol($tdb, 'cuidados_registros', 'subtipo',      "VARCHAR(50) DEFAULT NULL AFTER `hora`", $ok, $skip, $errors);
        addCol($tdb, 'cuidados_registros', 'pendiente',    "TINYINT(1) NOT NULL DEFAULT 0 AFTER `subtipo`", $ok, $skip, $errors);
        addCol($tdb, 'cuidados_registros', 'duracion_min', "SMALLINT DEFAULT NULL AFTER `pendiente`", $ok, $skip, $errors);
    }

    // historial_evoluciones.tipo enum
    if (tableExists($tdb, 'historial_evoluciones')) {
        try {
            $col = $tdb->query("SHOW COLUMNS FROM `historial_evoluciones` LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
            if ($col && !str_contains($col['Type'], 'prescripcion')) {
                $tdb->exec("ALTER TABLE `historial_evoluciones`
                    MODIFY COLUMN `tipo`
                    ENUM('evolucion','nota_enfermeria','valoracion','interconsulta','prescripcion')
                    NOT NULL DEFAULT 'evolucion'");
                echo "    + historial_evoluciones.tipo ENUM ampliado\n";
                $ok++;
            }
        } catch (PDOException $e) {
            // Silently skip
        }
    }

    // prescripciones.imagen
    if (tableExists($tdb, 'prescripciones')) {
        addCol($tdb, 'prescripciones', 'imagen', "VARCHAR(500) DEFAULT NULL", $ok, $skip, $errors);
    }

    // usuarios.telefono, preferencias
    if (tableExists($tdb, 'usuarios')) {
        addCol($tdb, 'usuarios', 'telefono', "VARCHAR(30) DEFAULT NULL", $ok, $skip, $errors);
        addCol($tdb, 'usuarios', 'preferencias', "JSON DEFAULT NULL", $ok, $skip, $errors);
    }

    // expediente_docs.tipo — agregar 'nota_medico' al ENUM (v1.40.0)
    if (tableExists($tdb, 'expediente_docs')) {
        try {
            $tdb->exec("ALTER TABLE `expediente_docs`
                MODIFY COLUMN `tipo` ENUM('receta','laboratorio','imagen','interpretacion','hospitalizacion','legal','nota_enfermeria','nota_medico') NOT NULL DEFAULT 'receta'");
            $ok++;
        } catch (PDOException $e) {
            // Ya actualizado o error
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// RESUMEN
// ═══════════════════════════════════════════════════════════════════════════
echo "\n" . str_repeat('═', 50) . "\n";
echo "  Resultado: $ok creados, $skip ya existían, " . count($errors) . " errores\n";
if ($errors) {
    echo "\n  Errores:\n";
    foreach ($errors as $e) echo "    $e\n";
}
echo str_repeat('═', 50) . "\n";
echo "\n✅ Migración sync_schema completada.\n";
