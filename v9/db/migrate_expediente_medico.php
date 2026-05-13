<?php
/**
 * GeriApp — Migración: Expediente Médico [DESACTIVADA]
 * 
 * ⚠ Este módulo fue migrado a MediApp (app independiente).
 *   Las tablas fueron eliminadas por migrate_drop_expediente.php.
 *   Este archivo se conserva solo como referencia histórica.
 * 
 * Cumple con NOM-004-SSA3-2012 (Del Expediente Clínico)
 *            NOM-024-SSA3-2012 (Sistemas de Registro Electrónico para la Salud)
 *
 * Tablas que creaba (ya eliminadas de GeriApp):
 *   - expediente_hc, expediente_notas, expediente_enfermeria,
 *     expediente_estudios, expediente_consentimientos, expediente_auditoria,
 *     cie10_catalogo
 */

echo "<pre>\n⚠ Migración DESACTIVADA: el Expediente Médico fue migrado a MediApp.\n";
echo "Ejecute migrate_drop_expediente.php para limpiar las tablas restantes.\n</pre>\n";
return; // No ejecutar nada más

$db = Database::getInstance();

// ─── 1. CIE-10 Catálogo ──────────────────────────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `cie10_catalogo` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`      VARCHAR(10)  NOT NULL,
  `descripcion` VARCHAR(500) NOT NULL,
  `capitulo`    VARCHAR(5)   DEFAULT NULL,
  `grupo`       VARCHAR(10)  DEFAULT NULL,
  `favorito`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Frecuente en geriatría',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cie10_codigo` (`codigo`),
  KEY `idx_cie10_desc` (`descripcion`(100)),
  FULLTEXT KEY `ft_cie10` (`codigo`, `descripcion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla cie10_catalogo creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error cie10_catalogo: " . $e->getMessage() . "\n";
}

// ─── 2. Historia Clínica (NOM-004 §6.1) ──────────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `expediente_hc` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`      INT UNSIGNED NOT NULL,
  `institucion_id`    INT UNSIGNED NOT NULL,

  -- §6.1.1 Interrogatorio
  `ficha_identificacion`        JSON DEFAULT NULL COMMENT 'grupo_etnico, religion, escolaridad, ocupacion, lugar_nacimiento, nacionalidad',
  `antecedentes_heredo`         JSON DEFAULT NULL COMMENT 'diabetes, hipertension, cancer, cardiopatias, etc.',
  `antecedentes_patologicos`    TEXT DEFAULT NULL COMMENT 'Incluye tabaco, alcohol, sustancias (NOM-028)',
  `antecedentes_no_patologicos` TEXT DEFAULT NULL COMMENT 'Vivienda, alimentación, higiene, inmunizaciones',
  `padecimiento_actual`         TEXT DEFAULT NULL,
  `interrogatorio_aparatos`     JSON DEFAULT NULL COMMENT 'cardiovascular, respiratorio, digestivo, etc.',

  -- §6.1.2 Exploración Física
  `exploracion_fisica`       JSON DEFAULT NULL COMMENT 'habitus, cabeza, cuello, torax, abdomen, extremidades, genitales, neurologico',
  `signos_vitales_ingreso`   JSON DEFAULT NULL COMMENT 'ta_s, ta_d, fc, fr, temp, spo2, peso, talla',

  -- §6.1.3 Resultados previos
  `estudios_previos`         TEXT DEFAULT NULL,

  -- §6.1.4 Diagnósticos (vinculados a CIE-10)
  `diagnosticos`             JSON DEFAULT NULL COMMENT '[{codigo, descripcion, tipo: principal|secundario}]',

  -- §6.1.5 Pronóstico
  `pronostico`               TEXT DEFAULT NULL,

  -- §6.1.6 Indicación terapéutica
  `indicacion_terapeutica`   TEXT DEFAULT NULL,

  -- Datos geriátricos adicionales
  `grupo_sanguineo`          VARCHAR(5) DEFAULT NULL,
  `alergias_detalle`         TEXT DEFAULT NULL,
  `plan_cuidados`            TEXT DEFAULT NULL,
  `dieta`                    VARCHAR(200) DEFAULT NULL,
  `movilidad`                VARCHAR(200) DEFAULT NULL,

  -- Valoración geriátrica integral
  `valoracion_geriatrica`     JSON DEFAULT NULL COMMENT 'barthel, lawton, minimental, yesavage, mna, tinetti, notas',

  -- Firma digital (NOM-004 §5.10)
  `firmado_por`    INT UNSIGNED DEFAULT NULL,
  `firmado_at`     TIMESTAMP NULL DEFAULT NULL,
  `firma_path`     VARCHAR(500) DEFAULT NULL,

  `creado_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ehc_residente` (`residente_id`),
  KEY `idx_ehc_inst`  (`institucion_id`),
  CONSTRAINT `fk_ehc_res`  FOREIGN KEY (`residente_id`)   REFERENCES `residentes`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ehc_inst` FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ehc_firm` FOREIGN KEY (`firmado_por`)     REFERENCES `usuarios`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_hc creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_hc: " . $e->getMessage() . "\n";
}

// ─── 2b. Agregar columna valoracion_geriatrica si no existe (para BD existentes) ─
try {
    $cols = $db->query("SHOW COLUMNS FROM expediente_hc LIKE 'valoracion_geriatrica'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE expediente_hc ADD COLUMN `valoracion_geriatrica` JSON DEFAULT NULL COMMENT 'barthel, lawton, minimental, yesavage, mna, tinetti, notas' AFTER `movilidad`");
        echo "✓ Columna valoracion_geriatrica agregada a expediente_hc.\n";
    } else {
        echo "· Columna valoracion_geriatrica ya existe.\n";
    }
} catch (PDOException $e) {
    echo "✗ Error al agregar valoracion_geriatrica: " . $e->getMessage() . "\n";
}

// ─── 3. Notas (Evolución, Interconsulta, Referencia, Ingreso, Egreso) ─────────
// NOM-004 §6.2, §6.3, §6.4, §8.1, §8.9
$sql = "CREATE TABLE IF NOT EXISTS `expediente_notas` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`     INT UNSIGNED NOT NULL,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `usuario_id`       INT UNSIGNED NOT NULL,

  `tipo`  ENUM('evolucion','interconsulta','referencia','ingreso','egreso') NOT NULL DEFAULT 'evolucion',
  `fecha` DATE NOT NULL,
  `hora`  VARCHAR(5) NOT NULL COMMENT 'HH:MM',

  -- SOAP (§6.2)
  `subjetivo`   TEXT DEFAULT NULL COMMENT 'Evolución y actualización cuadro clínico',
  `objetivo`    TEXT DEFAULT NULL COMMENT 'Exploración física, hallazgos',
  `analisis`    TEXT DEFAULT NULL COMMENT 'Diagnósticos, resultados estudios',
  `plan`        TEXT DEFAULT NULL COMMENT 'Tratamiento (medicamento, dosis, vía, periodicidad)',

  `signos_vitales` JSON DEFAULT NULL COMMENT '{ta_s, ta_d, fc, fr, temp, spo2, peso, talla, glucosa}',
  `diagnosticos`   JSON DEFAULT NULL COMMENT '[{codigo, descripcion}]',
  `pronostico`     TEXT DEFAULT NULL,

  -- §6.3 Interconsulta
  `medico_solicitante`  VARCHAR(200) DEFAULT NULL,
  `medico_consultado`   VARCHAR(200) DEFAULT NULL,
  `especialidad`        VARCHAR(100) DEFAULT NULL,

  -- §6.4 Referencia / Traslado
  `establecimiento_envia`  VARCHAR(200) DEFAULT NULL,
  `establecimiento_recibe` VARCHAR(200) DEFAULT NULL,
  `motivo_envio`           TEXT DEFAULT NULL,

  -- §8.9 Egreso
  `motivo_egreso`       ENUM('mejoria','maximo_beneficio','voluntario','defuncion','traslado') DEFAULT NULL,
  `problemas_pendientes` TEXT DEFAULT NULL,
  `recomendaciones`      TEXT DEFAULT NULL,

  -- Inmutabilidad (NOM-024 §6.6.2)
  `firmado`      TINYINT(1) NOT NULL DEFAULT 0,
  `firmado_por`  INT UNSIGNED DEFAULT NULL,
  `firmado_at`   TIMESTAMP NULL DEFAULT NULL,
  `firma_path`   VARCHAR(500) DEFAULT NULL,

  -- Patrón addendum: una vez firmada, correcciones son addendums
  `es_addendum`   TINYINT(1) NOT NULL DEFAULT 0,
  `nota_padre_id` INT UNSIGNED DEFAULT NULL,

  `creado_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_en_res`   (`residente_id`),
  KEY `idx_en_fecha` (`fecha`),
  KEY `idx_en_tipo`  (`tipo`),
  KEY `idx_en_inst`  (`institucion_id`),
  CONSTRAINT `fk_en_res`   FOREIGN KEY (`residente_id`)   REFERENCES `residentes`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_en_inst`  FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_en_usr`   FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_en_firm`  FOREIGN KEY (`firmado_por`)     REFERENCES `usuarios`         (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_en_padre` FOREIGN KEY (`nota_padre_id`)   REFERENCES `expediente_notas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_notas creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_notas: " . $e->getMessage() . "\n";
}

// ─── 4. Hoja de Enfermería (NOM-004 §9.1) ────────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `expediente_enfermeria` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`     INT UNSIGNED NOT NULL,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `usuario_id`       INT UNSIGNED NOT NULL,

  `fecha` DATE NOT NULL,
  `turno` ENUM('matutino','vespertino','nocturno') NOT NULL,

  -- §9.1.1 Habitus exterior
  `habitus_exterior` TEXT DEFAULT NULL,

  -- §9.1.2 Signos vitales
  `signos_vitales` JSON DEFAULT NULL COMMENT '{ta_s, ta_d, fc, fr, temp, spo2, peso, glucosa}',

  -- §9.1.3 Ministración de medicamentos
  `medicamentos_administrados` JSON DEFAULT NULL COMMENT '[{nombre, dosis, via, hora, aplicado_por}]',

  -- §9.1.4 Procedimientos realizados
  `procedimientos` TEXT DEFAULT NULL,

  -- §9.1.5 Observaciones
  `observaciones` TEXT DEFAULT NULL,

  -- Apéndice A D13: valoración del dolor y riesgo de caídas
  `valoracion_dolor`  JSON DEFAULT NULL COMMENT '{localizacion, escala: 0-10}',
  `riesgo_caidas`     ENUM('bajo','medio','alto') DEFAULT NULL,

  -- Firma
  `firmado`    TINYINT(1) NOT NULL DEFAULT 0,
  `firmado_at` TIMESTAMP NULL DEFAULT NULL,
  `firma_path` VARCHAR(500) DEFAULT NULL,

  `creado_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_ee_res`   (`residente_id`),
  KEY `idx_ee_fecha` (`fecha`, `turno`),
  KEY `idx_ee_inst`  (`institucion_id`),
  CONSTRAINT `fk_ee_res`  FOREIGN KEY (`residente_id`)   REFERENCES `residentes`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ee_inst` FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ee_usr`  FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_enfermeria creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_enfermeria: " . $e->getMessage() . "\n";
}

// ─── 5. Estudios Auxiliares (NOM-004 §9.2) ───────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `expediente_estudios` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`     INT UNSIGNED NOT NULL,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `usuario_id`       INT UNSIGNED NOT NULL,

  `fecha` DATE NOT NULL,
  `hora`  VARCHAR(5) DEFAULT NULL,

  `estudio_solicitado` VARCHAR(300) NOT NULL,
  `problema_clinico`   TEXT DEFAULT NULL,
  `resultados`         TEXT DEFAULT NULL,
  `interpretacion`     TEXT DEFAULT NULL COMMENT 'Interpretación del médico',
  `incidentes`         TEXT DEFAULT NULL,
  `realizado_por`      VARCHAR(200) DEFAULT NULL,
  `informado_por`      VARCHAR(200) DEFAULT NULL,

  `archivo_path`  VARCHAR(500) DEFAULT NULL,
  `mime_type`     VARCHAR(80)  DEFAULT NULL,
  `tamano`        INT UNSIGNED DEFAULT NULL COMMENT 'bytes',

  `creado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_est_res`   (`residente_id`),
  KEY `idx_est_fecha` (`fecha`),
  KEY `idx_est_inst`  (`institucion_id`),
  CONSTRAINT `fk_est_res`  FOREIGN KEY (`residente_id`)   REFERENCES `residentes`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_est_inst` FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_est_usr`  FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_estudios creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_estudios: " . $e->getMessage() . "\n";
}

// ─── 6. Consentimientos Informados (NOM-004 §10.1) ──────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `expediente_consentimientos` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`     INT UNSIGNED NOT NULL,
  `institucion_id`   INT UNSIGNED NOT NULL,
  `usuario_id`       INT UNSIGNED NOT NULL,

  `titulo`               VARCHAR(300) NOT NULL COMMENT 'Acto autorizado (§10.1.1.5)',
  `fecha`                DATE NOT NULL,
  `riesgos_beneficios`   TEXT DEFAULT NULL COMMENT '§10.1.1.6',

  `otorgante_nombre`         VARCHAR(200) DEFAULT NULL COMMENT '§10.1.1.8',
  `otorgante_parentesco`     VARCHAR(100) DEFAULT NULL,
  `firma_otorgante_path`     VARCHAR(500) DEFAULT NULL,

  `medico_nombre`       VARCHAR(200) DEFAULT NULL COMMENT '§10.1.1.9',
  `cedula_profesional`  VARCHAR(20) DEFAULT NULL,
  `firma_medico_path`   VARCHAR(500) DEFAULT NULL,

  `testigo1_nombre`     VARCHAR(200) DEFAULT NULL COMMENT '§10.1.1.10',
  `testigo2_nombre`     VARCHAR(200) DEFAULT NULL,

  `documento_path`      VARCHAR(500) DEFAULT NULL COMMENT 'PDF/imagen escaneada',

  `creado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_ec_res`  (`residente_id`),
  KEY `idx_ec_inst` (`institucion_id`),
  CONSTRAINT `fk_ec_res`  FOREIGN KEY (`residente_id`)   REFERENCES `residentes`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ec_inst` FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ec_usr`  FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_consentimientos creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_consentimientos: " . $e->getMessage() . "\n";
}

// ─── 7. Auditoría (NOM-024 §6.6, §3.42, §3.54) ─────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `expediente_auditoria` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `residente_id`    INT UNSIGNED NOT NULL,
  `institucion_id`  INT UNSIGNED NOT NULL,
  `usuario_id`      INT UNSIGNED NOT NULL,

  `accion`    ENUM('ver','crear','editar','firmar','eliminar','exportar') NOT NULL,
  `entidad`   VARCHAR(50) NOT NULL COMMENT 'historia_clinica, nota_evolucion, enfermeria, estudio, consentimiento',
  `entidad_id` INT UNSIGNED DEFAULT NULL,
  `detalles`  JSON DEFAULT NULL COMMENT '{campo, valor_anterior, valor_nuevo}',
  `ip`        VARCHAR(45) DEFAULT NULL,

  `creado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_ea_res`   (`residente_id`),
  KEY `idx_ea_usr`   (`usuario_id`),
  KEY `idx_ea_fecha` (`creado_at`),
  KEY `idx_ea_inst`  (`institucion_id`),
  CONSTRAINT `fk_ea_res`  FOREIGN KEY (`residente_id`)   REFERENCES `residentes`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_inst` FOREIGN KEY (`institucion_id`)  REFERENCES `instituciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_usr`  FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✓ Tabla expediente_auditoria creada/verificada.\n";
} catch (PDOException $e) {
    echo "✗ Error expediente_auditoria: " . $e->getMessage() . "\n";
}

// ─── 8. Crear directorios de uploads ─────────────────────────────────────────
$dirs = [
    __DIR__ . '/../uploads/firmas',
    __DIR__ . '/../uploads/expediente',
];
foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
        echo "✓ Directorio creado: " . basename(dirname($d)) . '/' . basename($d) . "\n";
    }
}

// ─── 9. Seed CIE-10 con códigos geriátricos frecuentes ──────────────────────
$count = $db->query("SELECT COUNT(*) FROM cie10_catalogo")->fetchColumn();
if ((int)$count === 0) {
    echo "\n── Sembrando catálogo CIE-10 (códigos geriátricos frecuentes) ──\n";

    $codes = [
        // Enfermedades infecciosas
        ['A09','Diarrea y gastroenteritis de presunto origen infeccioso','I','A00-A09',1],
        ['A41.9','Septicemia, no especificada','I','A40-A41',1],
        ['A49.9','Infección bacteriana, no especificada','I','A49',0],
        ['B37.0','Estomatitis candidiásica (muguet oral)','I','B35-B49',1],
        ['B86','Escabiosis','I','B85-B89',0],

        // Neoplasias
        ['C34.9','Tumor maligno del bronquio o pulmón, no especificado','II','C34',0],
        ['C50.9','Tumor maligno de la mama, no especificado','II','C50',0],
        ['C61','Tumor maligno de la próstata','II','C61',1],
        ['C67.9','Tumor maligno de la vejiga urinaria, no especificado','II','C67',0],
        ['D64.9','Anemia, no especificada','III','D60-D64',1],

        // Endocrinas / Metabólicas
        ['E03.9','Hipotiroidismo, no especificado','IV','E00-E07',1],
        ['E05.9','Tirotoxicosis, no especificada','IV','E05',0],
        ['E11.9','Diabetes mellitus tipo 2, sin complicaciones','IV','E10-E14',1],
        ['E11.2','Diabetes mellitus tipo 2 con complicaciones renales','IV','E10-E14',1],
        ['E11.4','Diabetes mellitus tipo 2 con complicaciones neurológicas','IV','E10-E14',1],
        ['E11.5','Diabetes mellitus tipo 2 con complicaciones circulatorias periféricas','IV','E10-E14',1],
        ['E11.6','Diabetes mellitus tipo 2 con otras complicaciones especificadas','IV','E10-E14',0],
        ['E13.9','Otros tipos especificados de diabetes mellitus','IV','E10-E14',0],
        ['E14.9','Diabetes mellitus, no especificada','IV','E10-E14',0],
        ['E46','Desnutrición proteicocalórica, no especificada','IV','E40-E46',1],
        ['E55.9','Deficiencia de vitamina D, no especificada','IV','E55',1],
        ['E56.1','Deficiencia de vitamina K','IV','E56',0],
        ['E61.1','Deficiencia de hierro','IV','E61',1],
        ['E66.9','Obesidad, no especificada','IV','E65-E68',1],
        ['E78.0','Hipercolesterolemia pura','IV','E78',1],
        ['E78.5','Hiperlipidemia, no especificada','IV','E78',1],
        ['E83.5','Trastornos del metabolismo del calcio','IV','E83',0],
        ['E86','Deshidratación','IV','E86',1],
        ['E87.1','Hiponatremia','IV','E87',1],
        ['E87.6','Hipopotasemia','IV','E87',1],

        // Trastornos mentales
        ['F00.1','Demencia en la enfermedad de Alzheimer de comienzo tardío','V','F00-F09',1],
        ['F00.9','Demencia en la enfermedad de Alzheimer, no especificada','V','F00-F09',1],
        ['F01.9','Demencia vascular, no especificada','V','F00-F09',1],
        ['F02.0','Demencia en la enfermedad de Pick','V','F00-F09',0],
        ['F02.3','Demencia en la enfermedad de Parkinson','V','F00-F09',1],
        ['F03','Demencia, no especificada','V','F00-F09',1],
        ['F05.9','Delirio, no especificado','V','F05',1],
        ['F10.2','Síndrome de dependencia del alcohol','V','F10-F19',0],
        ['F20.9','Esquizofrenia, no especificada','V','F20-F29',0],
        ['F31.9','Trastorno afectivo bipolar, no especificado','V','F30-F39',0],
        ['F32.0','Episodio depresivo leve','V','F30-F39',1],
        ['F32.1','Episodio depresivo moderado','V','F30-F39',1],
        ['F32.2','Episodio depresivo grave sin síntomas psicóticos','V','F30-F39',1],
        ['F32.9','Episodio depresivo, no especificado','V','F30-F39',1],
        ['F33.9','Trastorno depresivo recurrente, no especificado','V','F30-F39',0],
        ['F41.0','Trastorno de pánico','V','F40-F48',0],
        ['F41.1','Trastorno de ansiedad generalizada','V','F40-F48',1],
        ['F41.9','Trastorno de ansiedad, no especificado','V','F40-F48',1],
        ['F51.0','Insomnio no orgánico','V','F51',1],

        // Sistema nervioso
        ['G20','Enfermedad de Parkinson','VI','G20-G26',1],
        ['G25.0','Temblor esencial','VI','G20-G26',1],
        ['G30.9','Enfermedad de Alzheimer, no especificada','VI','G30',1],
        ['G35','Esclerosis múltiple','VI','G35-G37',0],
        ['G40.9','Epilepsia, no especificada','VI','G40-G47',1],
        ['G43.9','Migraña, no especificada','VI','G43-G44',0],
        ['G45.9','Ataque isquémico transitorio, no especificado','VI','G45-G46',1],
        ['G47.0','Trastornos del inicio y mantenimiento del sueño (insomnio)','VI','G47',1],
        ['G47.3','Apnea del sueño','VI','G47',1],
        ['G62.9','Polineuropatía, no especificada','VI','G60-G64',1],
        ['G81.9','Hemiplejía, no especificada','VI','G80-G83',1],
        ['G82.2','Paraplejía, no especificada','VI','G80-G83',0],

        // Ojo y oído
        ['H25.9','Catarata senil, no especificada','VII','H25-H28',1],
        ['H26.9','Catarata, no especificada','VII','H25-H28',0],
        ['H40.1','Glaucoma primario de ángulo abierto','VII','H40-H42',1],
        ['H54.0','Ceguera binocular','VII','H54',0],
        ['H54.4','Ceguera monocular','VII','H54',0],
        ['H90.5','Hipoacusia neurosensorial bilateral','VIII','H90-H95',1],
        ['H91.9','Hipoacusia, no especificada','VIII','H90-H95',1],

        // Circulatorio
        ['I10','Hipertensión esencial (primaria)','IX','I10-I15',1],
        ['I11.9','Cardiopatía hipertensiva sin insuficiencia cardíaca','IX','I10-I15',1],
        ['I20.9','Angina de pecho, no especificada','IX','I20-I25',1],
        ['I21.9','Infarto agudo del miocardio, no especificado','IX','I20-I25',1],
        ['I25.9','Cardiopatía isquémica crónica, no especificada','IX','I20-I25',1],
        ['I42.0','Miocardiopatía dilatada','IX','I42-I43',0],
        ['I48','Fibrilación y aleteo auricular','IX','I48',1],
        ['I50.0','Insuficiencia cardíaca congestiva','IX','I50',1],
        ['I50.9','Insuficiencia cardíaca, no especificada','IX','I50',1],
        ['I63.9','Infarto cerebral, no especificado','IX','I60-I69',1],
        ['I64','Accidente vascular encefálico no especificado','IX','I60-I69',1],
        ['I67.9','Enfermedad cerebrovascular, no especificada','IX','I60-I69',1],
        ['I69.4','Secuelas de accidente vascular encefálico','IX','I60-I69',1],
        ['I70.0','Aterosclerosis de la aorta','IX','I70-I79',0],
        ['I70.2','Aterosclerosis de arterias de miembros','IX','I70-I79',1],
        ['I73.9','Enfermedad vascular periférica, no especificada','IX','I70-I79',1],
        ['I80.2','Flebitis y tromboflebitis de miembros inferiores','IX','I80-I89',1],
        ['I83.9','Venas varicosas de miembros inferiores','IX','I83',0],
        ['I87.2','Insuficiencia venosa (crónica) periférica','IX','I87',1],

        // Respiratorio
        ['J06.9','Infección aguda de vías respiratorias superiores','X','J00-J06',1],
        ['J15.9','Neumonía bacteriana, no especificada','X','J12-J18',1],
        ['J18.9','Neumonía, no especificada','X','J12-J18',1],
        ['J44.1','EPOC con exacerbación aguda','X','J40-J47',1],
        ['J44.9','EPOC, no especificada','X','J40-J47',1],
        ['J45.9','Asma, no especificada','X','J45-J46',0],
        ['J69.0','Neumonía por aspiración de alimentos y vómito','X','J69',1],
        ['J96.9','Insuficiencia respiratoria, no especificada','X','J96',1],

        // Digestivo
        ['K21.0','Enfermedad por reflujo gastroesofágico con esofagitis','XI','K20-K31',1],
        ['K25.9','Úlcera gástrica, no especificada','XI','K25-K28',0],
        ['K29.7','Gastritis, no especificada','XI','K29',1],
        ['K40.9','Hernia inguinal, no especificada','XI','K40-K46',0],
        ['K52.9','Gastroenteritis y colitis no infecciosas, no especificadas','XI','K50-K52',1],
        ['K56.6','Íleo, no especificado','XI','K56',1],
        ['K57.9','Enfermedad diverticular del intestino, no especificada','XI','K57',1],
        ['K59.0','Estreñimiento','XI','K59',1],
        ['K70.3','Cirrosis hepática alcohólica','XI','K70-K77',0],
        ['K74.6','Cirrosis hepática, no especificada','XI','K70-K77',0],
        ['K76.0','Hígado graso, no clasificado','XI','K70-K77',1],
        ['K80.2','Cálculo de la vesícula biliar sin colecistitis','XI','K80-K87',0],
        ['K92.2','Hemorragia gastrointestinal, no especificada','XI','K92',1],

        // Piel
        ['L02.9','Absceso cutáneo, furúnculo y ántrax, no especificados','XII','L00-L08',0],
        ['L03.9','Celulitis, no especificada','XII','L00-L08',1],
        ['L30.9','Dermatitis, no especificada','XII','L20-L30',1],
        ['L89.0','Úlcera por presión, estadio I','XII','L89',1],
        ['L89.1','Úlcera por presión, estadio II','XII','L89',1],
        ['L89.2','Úlcera por presión, estadio III','XII','L89',1],
        ['L89.3','Úlcera por presión, estadio IV','XII','L89',1],
        ['L89.9','Úlcera por presión, no especificada','XII','L89',1],
        ['L97','Úlcera de miembro inferior, no clasificada','XII','L97',1],

        // Musculoesquelético
        ['M06.9','Artritis reumatoide, no especificada','XIII','M05-M14',1],
        ['M10.9','Gota, no especificada','XIII','M10',1],
        ['M15.9','Poliartrosis, no especificada','XIII','M15-M19',1],
        ['M16.9','Coxartrosis (artrosis de cadera), no especificada','XIII','M15-M19',1],
        ['M17.9','Gonartrosis (artrosis de rodilla), no especificada','XIII','M15-M19',1],
        ['M19.9','Artrosis, no especificada','XIII','M15-M19',1],
        ['M41.9','Escoliosis, no especificada','XIII','M40-M43',0],
        ['M47.9','Espondilosis, no especificada','XIII','M47',1],
        ['M54.5','Lumbago no especificado','XIII','M54',1],
        ['M62.8','Otros trastornos especificados de los músculos (sarcopenia)','XIII','M60-M63',1],
        ['M79.3','Paniculitis, no especificada','XIII','M79',0],
        ['M80.9','Osteoporosis con fractura patológica, no especificada','XIII','M80-M85',1],
        ['M81.9','Osteoporosis, no especificada','XIII','M80-M85',1],

        // Genitourinario
        ['N17.9','Insuficiencia renal aguda, no especificada','XIV','N17-N19',1],
        ['N18.9','Enfermedad renal crónica, no especificada','XIV','N17-N19',1],
        ['N18.3','Enfermedad renal crónica, estadio 3','XIV','N17-N19',1],
        ['N18.4','Enfermedad renal crónica, estadio 4','XIV','N17-N19',1],
        ['N18.5','Enfermedad renal crónica, estadio 5','XIV','N17-N19',1],
        ['N20.0','Cálculo del riñón','XIV','N20-N23',0],
        ['N30.9','Cistitis, no especificada','XIV','N30-N32',0],
        ['N39.0','Infección de vías urinarias, sitio no especificado','XIV','N30-N39',1],
        ['N40','Hiperplasia de la próstata','XIV','N40-N51',1],
        ['N81.9','Prolapso genital femenino, no especificado','XIV','N80-N98',0],

        // Síntomas y signos
        ['R00.0','Taquicardia, no especificada','XVIII','R00',1],
        ['R04.0','Epistaxis (sangrado nasal)','XVIII','R04',0],
        ['R06.0','Disnea','XVIII','R06',1],
        ['R10.4','Dolor abdominal, no especificado','XVIII','R10',1],
        ['R11','Náusea y vómito','XVIII','R11',1],
        ['R13','Disfagia','XVIII','R13',1],
        ['R26.8','Otras anomalías de la marcha y la movilidad','XVIII','R26',1],
        ['R29.6','Tendencia a caer, no clasificada en otra parte','XVIII','R29',1],
        ['R31','Hematuria, no especificada','XVIII','R31',0],
        ['R32','Incontinencia urinaria, no especificada','XVIII','R32',1],
        ['R33','Retención de orina','XVIII','R33',1],
        ['R40.0','Somnolencia','XVIII','R40',1],
        ['R41.0','Desorientación, no especificada','XVIII','R41',1],
        ['R41.3','Amnesia, no especificada','XVIII','R41',1],
        ['R42','Mareo y desvanecimiento','XVIII','R42',1],
        ['R47.0','Disfasia y afasia','XVIII','R47',1],
        ['R50.9','Fiebre, no especificada','XVIII','R50',1],
        ['R52.9','Dolor, no especificado','XVIII','R52',1],
        ['R54','Senilidad / Debilidad senil','XVIII','R54',1],
        ['R55','Síncope y colapso','XVIII','R55',1],
        ['R56.0','Convulsiones febriles','XVIII','R56',0],
        ['R63.0','Anorexia','XVIII','R63',1],
        ['R63.4','Pérdida anormal de peso','XVIII','R63',1],
        ['R64','Caquexia','XVIII','R64',1],

        // Lesiones / Caídas
        ['S00.9','Traumatismo superficial de la cabeza, no especificado','XIX','S00-S09',1],
        ['S01.0','Herida de cuero cabelludo','XIX','S00-S09',0],
        ['S06.0','Concusión cerebral','XIX','S00-S09',1],
        ['S32.0','Fractura de vértebra lumbar','XIX','S30-S39',1],
        ['S42.0','Fractura de clavícula','XIX','S40-S49',0],
        ['S52.5','Fractura de extremo inferior del radio (Colles)','XIX','S50-S59',1],
        ['S72.0','Fractura del cuello del fémur (cadera)','XIX','S70-S79',1],
        ['S72.1','Fractura pertrocantérea del fémur','XIX','S70-S79',1],
        ['S82.0','Fractura de rótula','XIX','S80-S89',0],
        ['T78.4','Alergia, no especificada','XIX','T78',1],
        ['T81.4','Infección consecutiva a procedimiento','XIX','T80-T88',0],
        ['W01','Caída en el mismo nivel por resbalón, tropezón o traspié','XX','W00-W19',1],
        ['W06','Caída desde cama','XX','W00-W19',1],
        ['W10','Caída en o desde escaleras','XX','W00-W19',1],
        ['W18','Otras caídas en el mismo nivel','XX','W00-W19',1],
        ['W19','Caída no especificada','XX','W00-W19',1],

        // Factores de salud (Z)
        ['Z50.1','Otra terapia física (fisioterapia)','XXI','Z50',1],
        ['Z50.5','Terapia del habla','XXI','Z50',0],
        ['Z50.7','Terapia ocupacional y vocacional','XXI','Z50',1],
        ['Z73.6','Limitaciones de actividades debidas a discapacidad','XXI','Z73',1],
        ['Z74.0','Necesidad de asistencia por movilidad reducida','XXI','Z74',1],
        ['Z74.1','Necesidad de asistencia con el cuidado personal','XXI','Z74',1],
        ['Z74.2','Necesidad de asistencia domiciliaria, ningún miembro puede proporcionar cuidado','XXI','Z74',1],
        ['Z87.3','Antecedentes personales de enfermedades del sistema musculoesquelético','XXI','Z87',0],
        ['Z91.1','Antecedentes personales de incumplimiento del tratamiento','XXI','Z91',1],
        ['Z93.2','Ileostomía','XXI','Z93',0],
        ['Z93.3','Colostomía','XXI','Z93',0],
        ['Z96.6','Presencia de implante articular ortopédico','XXI','Z96',1],
        ['Z99.3','Dependencia de silla de ruedas','XXI','Z99',1],
    ];

    $stmt = $db->prepare(
        "INSERT IGNORE INTO cie10_catalogo (codigo, descripcion, capitulo, grupo, favorito) VALUES (?, ?, ?, ?, ?)"
    );
    $inserted = 0;
    foreach ($codes as $c) {
        $stmt->execute($c);
        if ($stmt->rowCount()) $inserted++;
    }
    echo "✓ Sembrados $inserted códigos CIE-10 geriátricos frecuentes.\n";
    echo "  Para importar el catálogo completo, ejecuta: db/cie10_import.php\n";
} else {
    echo "· Catálogo CIE-10 ya contiene $count registros, omitiendo seed.\n";
}

echo "\n=== Migración completada ===\n</pre>";
