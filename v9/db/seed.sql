-- ============================================================
-- GeriApp — Seed de datos iniciales
-- Ejecutar DESPUÉS de schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- Planes
-- ------------------------------------------------------------
INSERT INTO `planes` (`key`, `nombre`, `precio`, `descripcion`, `max_residentes`, `max_usuarios`, `modulos`, `activo`) VALUES
('basic',      'Básico',       499.00,  'Ideal para centros pequeños que están iniciando.',             20,   5,  '["bitacora","residentes","historial_basico"]',                                         1),
('pro',        'Profesional',  999.00,  'Para residencias de tamaño medio con equipos completos.',      50,   15, '["bitacora","residentes","historial","personal","whatsapp","reportes"]',              1),
('enterprise', 'Enterprise',   1999.00, 'Sin límites. Para grandes grupos geriátricos.',                NULL, NULL,'["bitacora","residentes","historial","personal","whatsapp","smtp","reportes","sla"]', 1);

-- ------------------------------------------------------------
-- Instituciones
-- ------------------------------------------------------------
INSERT INTO `instituciones` (`id`, `nombre`, `email_admin`, `telefono`, `direccion`, `plan_id`, `estado`, `plan_vence_at`, `creado_at`) VALUES
(1, 'Residencia Casilla de María', 'r.rendon@casilla.com',    '+52 55 1234 0001', 'Av. Casilla 123, CDMX',         2, 'activa',     '2026-03-01', '2024-01-15 09:00:00'),
(2, 'Centro Geriátrico San José',  'l.mendez@sanjose.com',    '+52 55 1234 0002', 'Calle San José 456, CDMX',      3, 'activa',     '2026-03-15', '2023-11-02 09:00:00'),
(3, 'Casa de Reposo Los Pinos',    'c.herrera@pinos.com',     '+52 55 1234 0003', 'Blvd. Los Pinos 789, Puebla',   1, 'activa',     '2026-03-10', '2024-03-10 09:00:00'),
(4, 'Residencia Vista Hermosa',    'm.garcia@vhermosa.com',   '+52 55 1234 0004', 'Col. Vista Hermosa 321, CDMX',  2, 'activa',     '2026-03-20', '2024-02-20 09:00:00'),
(5, 'Geriátrico del Valle',        'j.martinez@delvalle.com', '+52 55 1234 0005', 'Carretera del Valle Km 4, GDL', 1, 'trial',      '2026-03-10', '2026-02-10 09:00:00'),
(6, 'Centro Ancianos Buen Vivir',  'a.lopez@bvivir.com',      '+52 55 1234 0006', 'Col. Buen Vivir 654, MTY',      3, 'activa',     '2026-04-18', '2023-09-18 09:00:00'),
(7, 'Casa Hogar San Marcos',       'r.diaz@sanmarcos.com',    '+52 55 1234 0007', 'Calzada San Marcos 12, CDMX',   1, 'activa',     '2026-03-05', '2024-05-05 09:00:00'),
(8, 'Residencia La Paz',           'c.ruiz@lapaz.com',        '+52 55 1234 0008', 'Calle La Paz 890, CDMX',        2, 'suspendida', NULL,         '2023-08-22 09:00:00');

-- ------------------------------------------------------------
-- Usuarios  (passwords: Geri2026! para todos excepto superadmin)
-- Superadmin password: SuperAdmin2026!
-- Hashes generados con PASSWORD_BCRYPT cost=12
-- NOTA: el install.php genera los hashes en tiempo real con PHP,
--       estas son contraseñas de ejemplo para desarrollo.
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `institucion_id`, `nombre`, `email`, `password_hash`, `rol`, `estado`, `ultimo_acceso`) VALUES
-- Superadmin (sin institución)
(1,  NULL, 'Super Admin',         'superadmin@geriapp.mx',    '$2y$12$placeholder_superadmin',   'superadmin', 'activo', NOW()),
-- Admins
(2,  1,    'Rodrigo Rendón',      'r.rendon@casilla.com',     '$2y$12$placeholder_admin_1',      'admin',      'activo', NOW()),
(3,  2,    'Laura Méndez',        'l.mendez@sanjose.com',     '$2y$12$placeholder_admin_2',      'admin',      'activo', NOW()),
(4,  3,    'Carlos Herrera',      'c.herrera@pinos.com',      '$2y$12$placeholder_admin_3',      'admin',      'activo', NOW()),
(5,  4,    'María García',        'm.garcia@vhermosa.com',    '$2y$12$placeholder_admin_4',      'admin',      'activo', NOW()),
(6,  5,    'José Martínez',       'j.martinez@delvalle.com',  '$2y$12$placeholder_admin_5',      'admin',      'activo', NOW()),
(7,  6,    'Ana López',           'a.lopez@bvivir.com',       '$2y$12$placeholder_admin_6',      'admin',      'activo', NOW()),
(8,  7,    'Roberto Díaz',        'r.diaz@sanmarcos.com',     '$2y$12$placeholder_admin_7',      'admin',      'inactivo',NULL),
(9,  8,    'Carmen Ruiz',         'c.ruiz@lapaz.com',         '$2y$12$placeholder_admin_8',      'admin',      'activo', NULL),
-- Médicos
(10, 2,    'Dr. Arturo Ramos',    'a.ramos@sanjose.com',      '$2y$12$placeholder_medico_1',     'medico',     'activo', NOW()),
(11, 6,    'Dra. Elena Fuentes',  'e.fuentes@bvivir.com',     '$2y$12$placeholder_medico_2',     'medico',     'activo', NOW()),
(12, 1,    'Dr. Luis Torres',     'l.torres@casilla.com',     '$2y$12$placeholder_medico_3',     'medico',     'activo', NOW()),
-- Enfermeros
(13, 1,    'Enf. Patricia Soto',  'p.soto@casilla.com',       '$2y$12$placeholder_enf_1',        'enfermero',  'activo', NOW()),
(14, 4,    'Enf. Carla Ríos',     'c.rios@vhermosa.com',      '$2y$12$placeholder_enf_2',        'enfermero',  'activo', NOW()),
-- Familiares
(15, 2,    'Jorge Méndez',        'j.mendez@familia.com',     '$2y$12$placeholder_fam_1',        'familiar',   'activo', NULL);

-- NOTA: Los hashes placeholder serán reemplazados por install.php con hashes reales.

-- ------------------------------------------------------------
-- Residentes (institución 1 — Residencia Casilla de María)
-- ------------------------------------------------------------
INSERT INTO `residentes` (`institucion_id`, `folio`, `nombre`, `apellidos`, `fecha_nacimiento`, `sexo`, `diagnostico`, `alergias`, `medico_id`, `habitacion`, `fecha_ingreso`, `estado`) VALUES
(1, 'RES-001', 'Martha',    'Ramírez López',   '1942-03-15', 'F', 'Diabetes tipo 2, HTA',           'Penicilina',  12, '101', '2023-06-01', 'activo'),
(1, 'RES-002', 'Juan',      'Pérez García',    '1938-07-22', 'M', 'Alzheimer leve, HTA',            NULL,          12, '102', '2023-08-15', 'activo'),
(1, 'RES-003', 'Rosa',      'Mendoza Torres',  '1945-11-30', 'F', 'Artritis reumatoide',            'Aspirina',    12, '103', '2024-01-10', 'activo'),
(1, 'RES-004', 'Ernesto',   'Silva Morales',   '1940-05-18', 'M', 'EPOC, diabetes',                 NULL,          12, '104', '2024-03-20', 'activo'),
-- Institución 2 — Centro Geriátrico San José
(2, 'RES-001', 'Elena',     'Vásquez Díaz',    '1935-09-10', 'F', 'HTA, insuficiencia renal leve',  NULL,          10, '201', '2023-01-05', 'activo'),
(2, 'RES-002', 'Manuel',    'Cruz Herrera',    '1930-12-25', 'M', 'Parkinson estadio II',           'Sulfa',       10, '202', '2023-04-12', 'activo'),
(2, 'RES-003', 'Sofía',     'Guerrero Ríos',   '1943-02-14', 'F', 'Demencia vascular',              NULL,          10, '203', '2023-07-20', 'activo');

-- ------------------------------------------------------------
-- Bitácora — turnos de hoy (institución 1)
-- ------------------------------------------------------------
INSERT INTO `bitacora_turnos` (`institucion_id`, `tipo`, `fecha`, `responsable_id`, `firmado`, `firmado_at`, `firmado_por`) VALUES
(1, 'matutino',    CURDATE(), 13, 1, NOW(),  13),
(1, 'vespertino',  CURDATE(), 13, 0, NULL,   NULL),
(1, 'nocturno',    CURDATE(), 13, 0, NULL,   NULL);

-- ------------------------------------------------------------
-- Bitácora — entradas del turno matutino de hoy
-- ------------------------------------------------------------
INSERT INTO `bitacora_entradas` (`turno_id`, `residente_id`, `usuario_id`, `tipo`, `contenido`, `prioridad`) VALUES
(1, 1, 13, 'medicamento', 'Se administró Metformina 850mg c/desayuno. Sin incidencias.',               'normal'),
(1, 2, 13, 'alerta',      'Paciente con confusión nocturna. Se monitorea. Aviso a médico de guardia.', 'alta'),
(1, 3, 13, 'nota',        'Residente en buen estado. Realizó terapia física sin complicaciones.',      'normal'),
(1, NULL,13, 'actividad',  'Actividad grupal: ejercicios de memoria. Participaron 8 residentes.',      'normal');

-- ------------------------------------------------------------
-- Historial — expedientes
-- ------------------------------------------------------------
INSERT INTO `historial_expedientes` (`residente_id`, `institucion_id`, `grupo_sanguineo`, `antecedentes`, `medicamentos`, `plan_cuidados`, `dieta`, `movilidad`) VALUES
(1, 1, 'O+', 'Diabetes tipo 2 diagnosticada en 2001. HTA desde 2010. Sin cirugías previas.',
   'Metformina 850mg c/12h · Losartán 50mg c/24h · Aspirina 100mg c/24h',
   'Control glucémico diario. Revisión podológica mensual. Ejercicio suave 30 min/día.',
   'Diabética hipocalórica 1800 kcal',
   'Deambulación independiente con bastón'),
(2, 1, 'A+', 'Alzheimer diagnosticado 2022. HTA crónica. Sin alergias conocidas.',
   'Donepezilo 10mg c/24h · Amlodipino 5mg c/24h · Melatonina 5mg noche',
   'Orientación temporo-espacial diaria. Vigilancia constante. Evitar deambulación nocturna.',
   'Normal blanda sin restricciones calóricas',
   'Deambulación asistida'),
(5, 2, 'B+', 'HTA diagnosticada 2015. Insuficiencia renal leve (FG 55%). Sin cirugías.',
   'Enalapril 10mg c/12h · Furosemida 20mg c/24h · Calcitriol 0.25mcg c/24h',
   'Control tensional diario. Restricción de sodio y potasio. Hidratación vigilada.',
   'Hipoproteica, hipopotasémica',
   'Independiente');

-- ------------------------------------------------------------
-- Historial — evoluciones
-- ------------------------------------------------------------
INSERT INTO `historial_evoluciones` (`expediente_id`, `usuario_id`, `tipo`, `subjetivo`, `objetivo`, `analisis`, `plan`, `signos_vitales`) VALUES
(1, 12, 'evolucion',
   'Paciente refiere buen estado general. Niega dolor. Buen apetito.',
   'Consciente, orientada. TA: 130/80. FC: 72x min. Glucosa capilar: 118 mg/dL.',
   'Diabetes tipo 2 compensada. HTA controlada.',
   'Continuar tratamiento actual. Control glucémico en 3 días.',
   '{"ta":"130/80","fc":"72","fr":"16","temp":"36.5","spo2":"97","peso":"68"}'),
(2, 12, 'nota_enfermeria',
   'Paciente agitado en las últimas horas. No reconoce a cuidadores.',
   'Desorientado en tiempo y espacio. Sin fiebre. TA 145/90.',
   'Episodio de agitación en contexto de Alzheimer. HTA elevada.',
   'Ajuste de Amlodipino a 10mg. Valoración por psiquiatría. Notificar a familiar.',
   '{"ta":"145/90","fc":"88","fr":"18","temp":"36.8","spo2":"96","peso":"72"}');

-- ------------------------------------------------------------
-- Configuración por institución
-- ------------------------------------------------------------
INSERT INTO `configuracion` (`institucion_id`, `smtp_host`, `smtp_port`, `smtp_encriptacion`, `notif_alertas`, `notif_bitacora`, `timezone`) VALUES
(1, NULL, 587, 'TLS', 1, 1, 'America/Mexico_City'),
(2, NULL, 587, 'TLS', 1, 1, 'America/Mexico_City'),
(3, NULL, 587, 'TLS', 1, 0, 'America/Mexico_City'),
(4, NULL, 587, 'TLS', 1, 1, 'America/Mexico_City'),
(5, NULL, 587, 'TLS', 0, 0, 'America/Mexico_City'),
(6, NULL, 587, 'TLS', 1, 1, 'America/Monterrey'),
(7, NULL, 587, 'TLS', 1, 0, 'America/Mexico_City'),
(8, NULL, 587, 'TLS', 0, 0, 'America/Mexico_City');

-- ------------------------------------------------------------
-- Logs del sistema
-- ------------------------------------------------------------
INSERT INTO `logs_sistema` (`usuario_id`, `institucion_id`, `accion`, `modulo`, `detalle`, `ip`, `estado`) VALUES
(3,  2, 'create_residente',  'Residentes', 'Nuevo residente: Sofía Guerrero Ríos',            '192.168.1.45', 'ok'),
(2,  1, 'sign_shift',        'Bitácora',   'Turno Matutino firmado — 23/02/2026',             '192.168.1.12', 'ok'),
(1,  NULL,'suspend_tenant',  'SA Panel',   'Institución ID 8 suspendida',                     '190.2.3.4',    'warn'),
(7,  6, 'update_historial',  'Historial',  'Expediente residente ID 5 actualizado',           '10.0.0.5',     'ok'),
(10, 2, 'upload_doc',        'Historial',  'Documento: Laboratorios_Feb2026.pdf',             '192.168.1.46', 'ok'),
(13, 1, 'create_entry',      'Bitácora',   'Nueva entrada turno matutino — Tipo: medicamento','192.168.1.13', 'ok'),
(1,  NULL,'create_tenant',   'SA Panel',   'Nueva institución: Geriátrico del Valle',         '190.2.3.4',    'ok'),
(5,  4, 'update_residente',  'Residentes', 'Residente ID 4 actualizado',                      '10.0.1.8',     'ok');

SET foreign_key_checks = 1;
