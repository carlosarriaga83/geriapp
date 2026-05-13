-- ============================================================
-- GeriApp — Schema de base de datos v1.0
-- Motor: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- planes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`             VARCHAR(30)  NOT NULL,
  `nombre`          VARCHAR(80)  NOT NULL,
  `precio`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descripcion`     VARCHAR(255) DEFAULT NULL,
  `max_residentes`  INT          DEFAULT NULL COMMENT 'NULL = ilimitado',
  `max_usuarios`    INT          DEFAULT NULL COMMENT 'NULL = ilimitado',
  `modulos`         JSON         DEFAULT NULL COMMENT 'Array de slugs de módulos habilitados',
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- instituciones  (multi-tenant: cada tenant = 1 fila)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `instituciones` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(150) NOT NULL,
  `email_admin`     VARCHAR(150) NOT NULL,
  `telefono`        VARCHAR(30)  DEFAULT NULL,
  `direccion`       VARCHAR(255) DEFAULT NULL,
  `timezone`        VARCHAR(60)  NOT NULL DEFAULT 'America/Mexico_City',
  `plan_id`         INT UNSIGNED DEFAULT NULL,
  `estado`          ENUM('activa','trial','suspendida','archivada') NOT NULL DEFAULT 'trial',
  `trial_ends_at`   DATE         DEFAULT NULL,
  `plan_vence_at`   DATE         DEFAULT NULL,
  `max_residentes`  INT UNSIGNED DEFAULT NULL,
  `max_usuarios`    INT UNSIGNED DEFAULT NULL,
  `logo_path`       VARCHAR(255) DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inst_plan` (`plan_id`),
  CONSTRAINT `fk_inst_plan` FOREIGN KEY (`plan_id`)
    REFERENCES `planes` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL solo para superadmin',
  `nombre`          VARCHAR(120) NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `rol`             ENUM('superadmin','admin','medico','enfermero','familiar') NOT NULL,
  `estado`          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `avatar_path`     VARCHAR(255) DEFAULT NULL,
  `preferencias`    JSON         DEFAULT NULL,
  `ultimo_acceso`   TIMESTAMP    NULL DEFAULT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usr_email` (`email`),
  KEY `fk_usr_inst` (`institucion_id`),
  CONSTRAINT `fk_usr_inst` FOREIGN KEY (`institucion_id`)
    REFERENCES `instituciones` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- login_attempts  (rate-limiting)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `ip`         VARCHAR(45)  NOT NULL,
  `exitoso`    TINYINT(1)   NOT NULL DEFAULT 0,
  `creado_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_email` (`email`),
  KEY `idx_la_ip`    (`ip`),
  KEY `idx_la_fecha` (`creado_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- password_resets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(100) NOT NULL,
  `expires_at` TIMESTAMP    NOT NULL,
  `usado`      TINYINT(1)   NOT NULL DEFAULT 0,
  `creado_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token`),
  KEY `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- residentes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `residentes` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `folio`            VARCHAR(20)  DEFAULT NULL,
  `nombre`           VARCHAR(120) NOT NULL,
  `apellidos`        VARCHAR(120) NOT NULL,
  `fecha_nacimiento` DATE         DEFAULT NULL,
  `sexo`             ENUM('M','F','Otro') DEFAULT NULL,
  `curp`             VARCHAR(20)  DEFAULT NULL,
  `nss`              VARCHAR(20)  DEFAULT NULL,
  `diagnostico`      VARCHAR(255) DEFAULT NULL,
  `alergias`         TEXT         DEFAULT NULL,
  `medico_id`        INT UNSIGNED DEFAULT NULL,
  `familiar_id`      INT UNSIGNED DEFAULT NULL,
  `habitacion`       VARCHAR(20)  DEFAULT NULL,
  `fecha_ingreso`    DATE         DEFAULT NULL,
  `estado`           ENUM('activo','egresado','fallecido') NOT NULL DEFAULT 'activo',
  `foto_path`        VARCHAR(255) DEFAULT NULL,
  `notas`            TEXT         DEFAULT NULL,
  `creado_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_res_inst`     (`institucion_id`),
  KEY `fk_res_medico`   (`medico_id`),
  KEY `fk_res_familiar` (`familiar_id`),
  KEY `idx_res_folio`   (`folio`),
  CONSTRAINT `fk_res_inst`     FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_medico`   FOREIGN KEY (`medico_id`)      REFERENCES `usuarios`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_familiar` FOREIGN KEY (`familiar_id`)    REFERENCES `usuarios`      (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tablas bitacora_turnos y bitacora_entradas eliminadas en v1.26.0

-- ------------------------------------------------------------
-- prescripciones  (medicación permanente por residente)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescripciones` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`   INT UNSIGNED NOT NULL,
  `institucion_id` INT UNSIGNED NOT NULL,
  `nombre`         VARCHAR(200) NOT NULL,
  `dosis`          VARCHAR(100) DEFAULT NULL,
  `via`            VARCHAR(50)  DEFAULT NULL,
  `frecuencia`     VARCHAR(100) DEFAULT NULL,
  `horarios`       JSON         DEFAULT NULL COMMENT 'Array ["HH:MM",...]',
  `indicacion`     TEXT         DEFAULT NULL,
  `medico_nombre`  VARCHAR(200) DEFAULT NULL,
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `inicio`         DATE         DEFAULT NULL,
  `fin`            DATE         DEFAULT NULL,
  `creado_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_presc_res`  (`residente_id`),
  KEY `fk_presc_inst` (`institucion_id`),
  CONSTRAINT `fk_presc_res`  FOREIGN KEY (`residente_id`)  REFERENCES `residentes`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presc_inst` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tablas bitacora_rx_administraciones, historial_expedientes,
-- historial_evoluciones e historial_documentos eliminadas en v1.26.0

-- ------------------------------------------------------------
-- configuracion  (1 fila por institución)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`      INT UNSIGNED NOT NULL,
  -- SMTP
  `smtp_host`           VARCHAR(150) DEFAULT NULL,
  `smtp_port`           SMALLINT UNSIGNED DEFAULT 587,
  `smtp_usuario`        VARCHAR(150) DEFAULT NULL,
  `smtp_password`       VARCHAR(255) DEFAULT NULL,
  `smtp_encriptacion`   ENUM('TLS','SSL','Ninguna') DEFAULT 'TLS',
  `smtp_timeout`        SMALLINT UNSIGNED DEFAULT 20,
  `smtp_sandbox`        TINYINT(1)   NOT NULL DEFAULT 1,
  `smtp_from_email`     VARCHAR(150) DEFAULT NULL,
  `smtp_from_nombre`    VARCHAR(100) DEFAULT NULL,
  -- WhatsApp
  `wa_proveedor`        ENUM('waapi','meta','twilio') DEFAULT 'waapi',
  `wa_api_key`          VARCHAR(255) DEFAULT NULL,
  `wa_instance_id`      VARCHAR(100) DEFAULT NULL,
  `wa_phone`            VARCHAR(30)  DEFAULT NULL,
  `wa_activo`           TINYINT(1)   NOT NULL DEFAULT 0,
  `wa_sandbox`          TINYINT(1)   NOT NULL DEFAULT 1,
  -- Notificaciones
  `notif_alertas`       TINYINT(1)   NOT NULL DEFAULT 1,
  `notif_bitacora`      TINYINT(1)   NOT NULL DEFAULT 1,
  `notif_familiar`      TINYINT(1)   NOT NULL DEFAULT 0,
  -- IA
  `ia_proveedor`        VARCHAR(30)  DEFAULT 'openai',
  `ia_api_key`          VARCHAR(500) DEFAULT NULL,
  `ia_modelo`           VARCHAR(100) DEFAULT NULL,
  `ia_prompt`           TEXT         DEFAULT NULL,
  `ia_max_palabras`     INT          DEFAULT 400,
  -- Sistema
  `inst_nombre`         VARCHAR(200) DEFAULT NULL,
  `moneda`              VARCHAR(10)  NOT NULL DEFAULT 'MXN',
  `timezone`            VARCHAR(60)  NOT NULL DEFAULT 'America/Mexico_City',
  `idioma`              VARCHAR(10)  NOT NULL DEFAULT 'es',
  `fecha_formato`       VARCHAR(20)  NOT NULL DEFAULT 'd/m/Y',
  `creado_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_inst` (`institucion_id`),
  CONSTRAINT `fk_cfg_inst` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- logs_sistema
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs_sistema` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      INT UNSIGNED DEFAULT NULL,
  `institucion_id`  INT UNSIGNED DEFAULT NULL,
  `accion`          VARCHAR(100) NOT NULL,
  `modulo`          VARCHAR(50)  NOT NULL,
  `detalle`         TEXT         DEFAULT NULL,
  `ip`              VARCHAR(45)  DEFAULT NULL,
  `user_agent`      VARCHAR(255) DEFAULT NULL,
  `estado`          ENUM('ok','warn','error','info') NOT NULL DEFAULT 'ok',
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_usr`    (`usuario_id`),
  KEY `fk_log_inst`   (`institucion_id`),
  KEY `idx_log_fecha` (`creado_at`),
  KEY `idx_log_estado`(`estado`),
  CONSTRAINT `fk_log_usr`  FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_log_inst` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- invitaciones  (invitaciones de registro por correo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitaciones` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `rol`             ENUM('admin','medico','enfermero','familiar') NOT NULL DEFAULT 'enfermero',
  `token`           VARCHAR(64)  NOT NULL,
  `estado`          ENUM('pendiente','aceptada','expirada') NOT NULL DEFAULT 'pendiente',
  `mensaje`         TEXT         DEFAULT NULL,
  `expires_at`      TIMESTAMP    NOT NULL,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`      INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_token` (`token`),
  KEY `fk_inv_inst`         (`institucion_id`),
  KEY `fk_inv_usr`          (`creado_por`),
  KEY `idx_inv_estado`      (`estado`),
  CONSTRAINT `fk_inv_inst` FOREIGN KEY (`institucion_id`)
    REFERENCES `instituciones` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_inv_usr` FOREIGN KEY (`creado_por`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- invitaciones_geriapp  (QR globales para eventos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitaciones_geriapp` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`          VARCHAR(64) NOT NULL,
  `plan_id`        INT UNSIGNED NOT NULL,
  `nombre_evento`  VARCHAR(120) DEFAULT NULL,
  `estado`         ENUM('activa','revocada','expirada') NOT NULL DEFAULT 'activa',
  `expires_at`     TIMESTAMP NOT NULL,
  `usos_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at`   TIMESTAMP NULL DEFAULT NULL,
  `creado_por`     INT UNSIGNED DEFAULT NULL,
  `creado_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_geriapp_token` (`token`),
  KEY `fk_inv_geriapp_plan` (`plan_id`),
  KEY `fk_inv_geriapp_usr` (`creado_por`),
  KEY `idx_inv_geriapp_estado` (`estado`),
  KEY `idx_inv_geriapp_expira` (`expires_at`),
  CONSTRAINT `fk_inv_geriapp_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inv_geriapp_usr` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- invitaciones_geriapp_eventos  (auditoría de escaneos/registros)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitaciones_geriapp_eventos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invitacion_id`  INT UNSIGNED NOT NULL,
  `tipo`           ENUM('scan','registro') NOT NULL,
  `usuario_id`     INT UNSIGNED DEFAULT NULL,
  `institucion_id` INT UNSIGNED DEFAULT NULL,
  `ip`             VARCHAR(45) DEFAULT NULL,
  `user_agent`     VARCHAR(255) DEFAULT NULL,
  `meta`           JSON DEFAULT NULL,
  `creado_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ige_inv_fecha` (`invitacion_id`, `creado_at`),
  KEY `idx_ige_tipo_fecha` (`tipo`, `creado_at`),
  KEY `idx_ige_usuario` (`usuario_id`),
  KEY `idx_ige_institucion` (`institucion_id`),
  CONSTRAINT `fk_ige_inv` FOREIGN KEY (`invitacion_id`) REFERENCES `invitaciones_geriapp` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- sesiones_activas  (tracking de sesiones para gestión)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_activas` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED DEFAULT NULL,
  `session_id`      VARCHAR(128) NOT NULL,
  `ip`              VARCHAR(45)  DEFAULT NULL,
  `user_agent`      VARCHAR(512) DEFAULT NULL,
  `ultimo_acceso`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_id` (`session_id`),
  KEY `fk_sess_usr` (`usuario_id`),
  KEY `idx_sess_inst` (`institucion_id`),
  KEY `idx_sess_ultimo` (`ultimo_acceso`),
  CONSTRAINT `fk_sess_usr`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sess_inst` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
