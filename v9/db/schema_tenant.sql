-- ============================================================
-- GeriApp — Schema Tenant (BD por institución)
-- Motor: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4_unicode_ci
--
-- Este archivo se ejecuta al crear una nueva BD de tenant vía
-- Database::createTenantDB(). Contiene solo las tablas de datos
-- clínicos que son exclusivas de cada institución.
--
-- Las tablas globales (planes, instituciones, usuarios, etc.)
-- permanecen en la BD master.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- residentes
-- NOTA: las FK hacia usuarios (medico_id, familiar_id) son
-- referencias cross-BD y se manejan a nivel de aplicación.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `residentes` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`   INT UNSIGNED NOT NULL COMMENT 'Redundante aquí; facilita reportes y backups',
  `folio`            VARCHAR(20)  DEFAULT NULL,
  `nombre`           VARCHAR(120) NOT NULL,
  `apellidos`        VARCHAR(120) NOT NULL,
  `fecha_nacimiento` DATE         DEFAULT NULL,
  `sexo`             ENUM('M','F','Otro') DEFAULT NULL,
  `curp`             VARCHAR(20)  DEFAULT NULL,
  `nss`              VARCHAR(20)  DEFAULT NULL,
  `diagnostico`      VARCHAR(255) DEFAULT NULL,
  `alergias`         TEXT         DEFAULT NULL,
  `medico_id`        INT UNSIGNED DEFAULT NULL COMMENT 'Ref. a usuarios en master',
  `familiar_id`      INT UNSIGNED DEFAULT NULL COMMENT 'Ref. a usuarios en master',
  `habitacion`       VARCHAR(20)  DEFAULT NULL,
  `fecha_ingreso`    DATE         DEFAULT NULL,
  `estado`           ENUM('activo','egresado','fallecido') NOT NULL DEFAULT 'activo',
  `foto_path`        VARCHAR(255) DEFAULT NULL,
  `notas`            TEXT         DEFAULT NULL,
  `creado_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_res_folio`  (`folio`),
  KEY `idx_res_estado` (`estado`),
  KEY `idx_res_medico` (`medico_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- prescripciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescripciones` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`   INT UNSIGNED NOT NULL,
  `institucion_id` INT UNSIGNED NOT NULL,
  `nombre`         VARCHAR(200) NOT NULL,
  `dosis`          VARCHAR(100) DEFAULT NULL,
  `via`            VARCHAR(50)  DEFAULT NULL,
  `frecuencia`     VARCHAR(100) DEFAULT NULL,
  `horarios`       JSON         DEFAULT NULL,
  `indicacion`     TEXT         DEFAULT NULL,
  `medico_nombre`  VARCHAR(200) DEFAULT NULL,
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `inicio`         DATE         DEFAULT NULL,
  `fin`            DATE         DEFAULT NULL,
  `creado_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_presc_res` (`residente_id`),
  CONSTRAINT `fk_presc_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tablas bitacora_turnos, bitacora_entradas, bitacora_rx_administraciones,
-- historial_expedientes, historial_evoluciones e historial_documentos
-- eliminadas en v1.26.0

-- ------------------------------------------------------------
-- configuracion  (1 fila por institución)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`      INT UNSIGNED NOT NULL,
  `smtp_host`           VARCHAR(150) DEFAULT NULL,
  `smtp_port`           SMALLINT UNSIGNED DEFAULT 587,
  `smtp_usuario`        VARCHAR(150) DEFAULT NULL,
  `smtp_password`       VARCHAR(255) DEFAULT NULL,
  `smtp_encriptacion`   ENUM('TLS','SSL','Ninguna') DEFAULT 'TLS',
  `smtp_timeout`        SMALLINT UNSIGNED DEFAULT 20,
  `smtp_sandbox`        TINYINT(1)   NOT NULL DEFAULT 1,
  `smtp_from_email`     VARCHAR(150) DEFAULT NULL,
  `smtp_from_nombre`    VARCHAR(100) DEFAULT NULL,
  `wa_proveedor`        ENUM('waapi','meta','twilio') DEFAULT 'waapi',
  `wa_api_key`          VARCHAR(255) DEFAULT NULL,
  `wa_instance_id`      VARCHAR(100) DEFAULT NULL,
  `wa_phone`            VARCHAR(30)  DEFAULT NULL,
  `wa_activo`           TINYINT(1)   NOT NULL DEFAULT 0,
  `wa_sandbox`          TINYINT(1)   NOT NULL DEFAULT 1,
  `notif_alertas`       TINYINT(1)   NOT NULL DEFAULT 1,
  `notif_bitacora`      TINYINT(1)   NOT NULL DEFAULT 1,
  `notif_familiar`      TINYINT(1)   NOT NULL DEFAULT 0,
  `timezone`            VARCHAR(60)  NOT NULL DEFAULT 'America/Mexico_City',
  `idioma`              VARCHAR(10)  NOT NULL DEFAULT 'es',
  `fecha_formato`       VARCHAR(20)  NOT NULL DEFAULT 'd/m/Y',
  `app_url`             VARCHAR(255) DEFAULT NULL,
  `backup_frecuencia`   VARCHAR(20)  DEFAULT 'diario',
  `backup_hora`         VARCHAR(5)   DEFAULT '02:00',
  `creado_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_inst` (`institucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- cuidados_registros  (registros de cuidados estructurados)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuidados_registros` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL COMMENT 'Ref. a usuarios en master',
  `categoria`       VARCHAR(30)  NOT NULL COMMENT 'sueno|alimentacion|medicacion|higiene|terapia|movilidad|eliminacion|comportamiento|signos_vitales',
  `datos`           JSON         DEFAULT NULL COMMENT 'Datos estructurados del formulario',
  `observaciones`   TEXT         DEFAULT NULL,
  `fecha`           DATE         NOT NULL,
  `hora`            VARCHAR(5)   NOT NULL COMMENT 'HH:MM',
  `subtipo`         VARCHAR(50)  DEFAULT NULL COMMENT 'Denorm: tipo_eliminacion / etc.',
  `pendiente`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Denorm: sueño pendiente',
  `duracion_min`    SMALLINT     DEFAULT NULL COMMENT 'Denorm: duración en minutos',
  `verificacion_biometrica` TINYINT(1) NOT NULL DEFAULT 0,
  `timestamp_firma` DATETIME     DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_residente` (`residente_id`),
  KEY `idx_cr_fecha`     (`fecha`),
  KEY `idx_cr_categoria` (`categoria`),
  KEY `idx_cr_inst_fecha` (`institucion_id`, `fecha`),
  KEY `idx_cr_subtipo`   (`subtipo`),
  KEY `idx_cr_pendiente` (`pendiente`),
  CONSTRAINT `fk_cr_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuidados: Notas de turno
CREATE TABLE IF NOT EXISTS `cuidados_notas` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `residente_id`    INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `nota`            TEXT         NOT NULL,
  `imagen`          VARCHAR(255) DEFAULT NULL,
  `prioridad`       ENUM('normal','importante','urgente') NOT NULL DEFAULT 'normal',
  `leido_por`       JSON         DEFAULT NULL,
  `fecha`           DATE         NOT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cn_inst_res_fecha` (`institucion_id`, `residente_id`, `fecha`),
  CONSTRAINT `fk_cn_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario de insumos
CREATE TABLE IF NOT EXISTS `inventario_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `residente_id`    INT UNSIGNED DEFAULT NULL,
  `nombre`          VARCHAR(150) NOT NULL,
  `tipo`            ENUM('medicamento','suplemento','insumo','otro') NOT NULL DEFAULT 'insumo',
  `unidad`          VARCHAR(30)  NOT NULL DEFAULT 'unidades',
  `stock_actual`    INT          NOT NULL DEFAULT 0,
  `stock_minimo`    INT          NOT NULL DEFAULT 10,
  `vencimiento`     DATE         DEFAULT NULL,
  `notas`           TEXT         DEFAULT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ii_inst` (`institucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario: movimientos (entradas/salidas)
CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`         INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `tipo`            ENUM('entrada','salida','ajuste') NOT NULL,
  `cantidad`        INT          NOT NULL,
  `residente_id`    INT UNSIGNED DEFAULT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `motivo`          VARCHAR(255) DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_im_item` (`item_id`),
  KEY `idx_im_inst` (`institucion_id`),
  CONSTRAINT `fk_im_item` FOREIGN KEY (`item_id`) REFERENCES `inventario_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificaciones del sistema (admin → usuarios)
CREATE TABLE IF NOT EXISTS `notificaciones_sistema` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `titulo`          VARCHAR(200) NOT NULL,
  `mensaje`         TEXT         NOT NULL,
  `tipo`            ENUM('info','alerta','pregunta') NOT NULL DEFAULT 'info',
  `opciones_respuesta` JSON     DEFAULT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_por`      INT UNSIGNED NOT NULL,
  `roles_destino`   JSON         DEFAULT NULL COMMENT 'Array de roles que ven la notificacion, null=todos',
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ns_inst` (`institucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de notificaciones vistas / respondidas
CREATE TABLE IF NOT EXISTS `notificaciones_sistema_log` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notificacion_id` INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `visto_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `respuesta`       TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nsl_notif` (`notificacion_id`),
  KEY `idx_nsl_usuario` (`usuario_id`),
  CONSTRAINT `fk_nsl_notif` FOREIGN KEY (`notificacion_id`) REFERENCES `notificaciones_sistema` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- usuario_residentes (pivot: usuario ↔ residente access)
-- usuario_id es cross-BD ref a usuarios en master
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_residentes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      INT UNSIGNED NOT NULL COMMENT 'Ref. a usuarios en master',
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usr_res` (`usuario_id`,`residente_id`,`institucion_id`),
  KEY `idx_ur_residente` (`residente_id`),
  KEY `idx_ur_inst` (`institucion_id`),
  CONSTRAINT `fk_ur_res` FOREIGN KEY (`residente_id`) REFERENCES `residentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- documentos_legales (Términos y Condiciones / Aviso de Privacidad)
-- Versionados por institución, con flag vigente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_legales` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id` INT UNSIGNED NOT NULL,
  `tipo`           ENUM('terminos','privacidad') NOT NULL,
  `version`        VARCHAR(20)  NOT NULL DEFAULT '1.0',
  `titulo`         VARCHAR(200) NOT NULL,
  `contenido`      MEDIUMTEXT   NOT NULL,
  `vigente`        TINYINT(1)   NOT NULL DEFAULT 0,
  `requiere_firma` TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_por`     INT UNSIGNED DEFAULT NULL,
  `creado_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dl_inst_tipo` (`institucion_id`,`tipo`),
  KEY `idx_dl_vigente` (`vigente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- firmas_documentos (firma gráfica del usuario sobre un doc legal)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `firmas_documentos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id`   INT UNSIGNED NOT NULL,
  `usuario_id`     INT UNSIGNED NOT NULL,
  `firma_data`     MEDIUMTEXT   NOT NULL COMMENT 'Base64 PNG de firma manuscrita',
  `ip`             VARCHAR(45)  DEFAULT NULL,
  `user_agent`     VARCHAR(512) DEFAULT NULL,
  `firmado_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fd_doc_usr` (`documento_id`,`usuario_id`),
  KEY `idx_fd_doc` (`documento_id`),
  KEY `idx_fd_usr` (`usuario_id`),
  CONSTRAINT `fk_fd_doc` FOREIGN KEY (`documento_id`) REFERENCES `documentos_legales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
