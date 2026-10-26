-- ===============================================================
-- DOCUFLOW AI - GRUPO 01 | APLICACIONES DISTRIBUIDAS I
-- MySQL 8.0.16+ (InnoDB, utf8mb4) | Sin Laravel
-- Modelo lógico actualizado: 11 tablas y 12 claves foráneas.
-- Base: DocuFlow_ER_Logico_Actualizado.puml y diccionario v2.
-- ===============================================================
-- DECISIONES AUN PROVISIONALES:
--  1) estado_documental = 'vigente' / 'anulado'.
--  2) nivel_confidencialidad: VARCHAR(20), valores por acordar.
--  3) evaluaciones_ia_log ON DELETE CASCADE (revisar conservación).
-- IMPORTANTE: para anular, usar UPDATE (borrado lógico), no DELETE.
-- Las FK y rol_permiso no sustituyen las verificaciones por ámbito,
-- autor y confidencialidad en PHP/Laravel.
-- ===============================================================

CREATE DATABASE IF NOT EXISTS `docuflow_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `docuflow_db`;

-- ===============================================================
-- 01. SEGURIDAD, PERMISOS Y DEPENDENCIAS
-- ===============================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(10) NOT NULL,
  `nombre` VARCHAR(50) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_codigo` (`codigo`),
  UNIQUE KEY `uq_roles_nombre` (`nombre`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `permisos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(60) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permisos_codigo` (`codigo`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `dependencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dependencias_nombre` (`nombre`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `rol_permiso` (
  `rol_id` BIGINT UNSIGNED NOT NULL,
  `permiso_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rol_id`, `permiso_id`),
  KEY `idx_rol_permiso_permiso` (`permiso_id`),
  CONSTRAINT `fk_rolpermiso_rol`
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_rolpermiso_permiso`
    FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rol_id` BIGINT UNSIGNED NOT NULL,
  `dependencia_id` BIGINT UNSIGNED NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  KEY `idx_usuarios_rol` (`rol_id`),
  KEY `idx_usuarios_dependencia` (`dependencia_id`),
  CONSTRAINT `chk_usuarios_activo` CHECK (`activo` IN (0, 1)),
  CONSTRAINT `fk_usuarios_roles`
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_usuarios_dependencias`
    FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ===============================================================
-- 02. GESTIÓN DOCUMENTAL
-- ===============================================================

CREATE TABLE IF NOT EXISTS `tipos_documento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(60) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipos_documento_nombre` (`nombre`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `documentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `dependencia_id` BIGINT UNSIGNED NOT NULL,
  `tipo_doc_id` BIGINT UNSIGNED NULL,
  `codigo_doc` VARCHAR(80) NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `ruta_almacenada` VARCHAR(255) NOT NULL,
  `formato_mime` VARCHAR(40) NOT NULL,
  `tamanio_bytes` BIGINT NOT NULL,
  `estado_proceso` ENUM('subido','procesado_ia','verificado','error') NOT NULL DEFAULT 'subido',
  `nivel_confidencialidad` VARCHAR(20) NOT NULL,
  `estado_documental` VARCHAR(20) NOT NULL DEFAULT 'vigente',
  `motivo_anulacion` VARCHAR(255) NULL,
  `fecha_anulacion` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documentos_usuario` (`usuario_id`),
  KEY `idx_documentos_dependencia_estado` (`dependencia_id`, `estado_documental`),
  KEY `idx_documentos_tipo` (`tipo_doc_id`),
  KEY `idx_documentos_codigo` (`codigo_doc`),
  CONSTRAINT `chk_documentos_tamanio` CHECK (`tamanio_bytes` >= 0),
  CONSTRAINT `fk_documentos_usuarios`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_documentos_dependencias`
    FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_documentos_tipos`
    FOREIGN KEY (`tipo_doc_id`) REFERENCES `tipos_documento` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `metadatos_documento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` BIGINT UNSIGNED NOT NULL,
  `numero_extraido` VARCHAR(100) NULL,
  `fecha_emision` DATE NULL,
  `remitente` VARCHAR(150) NULL,
  `destinatario` VARCHAR(150) NULL,
  `asunto` TEXT NULL,
  `resumen_contenido` TEXT NULL,
  `validado_humano` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_metadatos_documento_id` (`documento_id`),
  CONSTRAINT `chk_metadatos_validado` CHECK (`validado_humano` IN (0, 1)),
  CONSTRAINT `fk_metadatos_documentos`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===============================================================
-- 03. IA, RAG Y EVALUACIÓN
-- ===============================================================

CREATE TABLE IF NOT EXISTS `chunks_documento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` BIGINT UNSIGNED NOT NULL,
  `numero_chunk` INT NOT NULL,
  `contenido_texto` MEDIUMTEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_chunk` (`documento_id`, `numero_chunk`),
  CONSTRAINT `fk_chunks_documentos`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `evaluaciones_ia_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` BIGINT UNSIGNED NOT NULL,
  `metodo_usado` VARCHAR(60) NOT NULL,
  `modelo_version` VARCHAR(60) NOT NULL,
  `tiempo_respuesta_ms` INT UNSIGNED NOT NULL,
  `campos_evaluados_json` JSON NULL,
  `cer_obtenido` DECIMAL(5,2) NULL,
  `f1_score` DECIMAL(5,2) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evaluaciones_doc_fecha` (`documento_id`, `created_at`),
  -- ATENCIÓN: ON DELETE CASCADE también borra resultados de investigación.
  CONSTRAINT `fk_evaluaciones_documento`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===============================================================
-- 04. AUDITORÍA
-- ===============================================================

CREATE TABLE IF NOT EXISTS `auditoria_acciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NULL,
  `documento_id` BIGINT UNSIGNED NULL,
  `accion` VARCHAR(80) NOT NULL,
  `detalles_json` JSON NULL,
  `direccion_ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_usuario_fecha` (`usuario_id`, `created_at`),
  KEY `idx_auditoria_documento_fecha` (`documento_id`, `created_at`),
  CONSTRAINT `fk_auditoria_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_auditoria_documento`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===============================================================
-- 05. CATÁLOGOS INICIALES (basados en la matriz del Grupo 01)
-- ===============================================================
-- INSERT IGNORE permite volver a ejecutar el archivo sin duplicados
-- en las columnas con UNIQUE. No concede permisos por sí mismo.

INSERT IGNORE INTO `roles` (`codigo`, `nombre`, `descripcion`) VALUES
  ('ADM', 'Administrador del sistema', 'Gestión técnica y evaluaciones, sin acceso a documentos por defecto.'),
  ('GES', 'Gestor documental', 'Gestión de documentos y metadatos dentro de su ámbito.'),
  ('OPE', 'Operador / Registrador', 'Carga y validación de documentos propios.'),
  ('CON', 'Consultor', 'Consulta y descarga autorizada, sin modificación.'),
  ('AUD', 'Auditor', 'Inspección de auditoría, estadísticas y reportes sin modificar documentos.');

INSERT IGNORE INTO `permisos` (`codigo`, `nombre`, `descripcion`) VALUES
  ('USUARIOS_GESTIONAR', 'Gestionar usuarios y roles', 'Crear, editar, activar, desactivar y asignar roles a usuarios.'),
  ('CLAVE_CAMBIAR', 'Cambiar contraseña propia', 'Permiso básico para cambiar o restablecer la propia contraseña.'),
  ('CATALOGOS_GESTIONAR', 'Gestionar catálogos', 'Administrar tipos documentales y catálogos organizacionales.'),
  ('IA_CONFIGURAR', 'Configurar servicios de IA', 'Administrar configuración técnica y parámetros de IA.'),
  ('DOCUMENTOS_REGISTRAR', 'Registrar documentos', 'Cargar y registrar documentos autorizados.'),
  ('DOCUMENTOS_PROCESAR_IA', 'Procesar documentos con IA', 'Procesar documentos según el ámbito de acceso.'),
  ('DOCUMENTOS_VALIDAR', 'Confirmar extracción', 'Revisar y confirmar datos extraídos según el ámbito.'),
  ('DOCUMENTOS_CLASIFICAR', 'Clasificar documentos', 'Asignar y modificar clasificación autorizada.'),
  ('DOCUMENTOS_EDITAR', 'Editar metadatos', 'Modificar metadatos con validación contextual.'),
  ('DOCUMENTOS_ANULAR', 'Anular documentos', 'Registrar anulación lógica y motivo.'),
  ('DOCUMENTOS_BUSCAR', 'Búsqueda convencional', 'Buscar documentos o metadatos conforme a permisos.'),
  ('DOCUMENTOS_BUSCAR_SEM', 'Búsqueda semántica', 'Consultar la búsqueda por significado.'),
  ('RAG_CONSULTAR', 'Consultar asistente RAG', 'Consultar el asistente con fuentes autorizadas.'),
  ('RAG_HISTORIAL', 'Ver historial propio de RAG', 'Consultar interacciones propias, cuando exista este módulo.'),
  ('DOCUMENTOS_DESCARGAR', 'Ver o descargar documento', 'Descargar archivos solamente dentro del ámbito permitido.'),
  ('AUDITORIA_CONSULTAR', 'Consultar auditoría', 'Consultar trazas conforme al alcance del rol.'),
  ('ESTADISTICAS_VER', 'Ver estadísticas', 'Ver estadísticas autorizadas según rol y ámbito.'),
  ('REPORTES_EXPORTAR', 'Exportar reportes', 'Exportar reportes conforme al alcance del rol.'),
  ('IA_EVALUACIONES', 'Ejecutar evaluaciones IA', 'Ejecutar pruebas de investigación autorizadas.');

-- Asignaciones generales por rol. Restricciones de ámbito se aplican en PHP.

-- ADM: 7 permisos generales de la matriz.
INSERT IGNORE INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo = 'ADM'
  AND p.codigo IN ('USUARIOS_GESTIONAR', 'CLAVE_CAMBIAR', 'CATALOGOS_GESTIONAR', 'IA_CONFIGURAR', 'AUDITORIA_CONSULTAR', 'ESTADISTICAS_VER', 'IA_EVALUACIONES');

-- GES: 16 permisos generales de la matriz.
INSERT IGNORE INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo = 'GES'
  AND p.codigo IN ('CLAVE_CAMBIAR', 'CATALOGOS_GESTIONAR', 'DOCUMENTOS_REGISTRAR', 'DOCUMENTOS_PROCESAR_IA', 'DOCUMENTOS_VALIDAR', 'DOCUMENTOS_CLASIFICAR', 'DOCUMENTOS_EDITAR', 'DOCUMENTOS_ANULAR', 'DOCUMENTOS_BUSCAR', 'DOCUMENTOS_BUSCAR_SEM', 'RAG_CONSULTAR', 'RAG_HISTORIAL', 'DOCUMENTOS_DESCARGAR', 'AUDITORIA_CONSULTAR', 'ESTADISTICAS_VER', 'REPORTES_EXPORTAR');

-- OPE: 12 permisos generales de la matriz.
INSERT IGNORE INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo = 'OPE'
  AND p.codigo IN ('CLAVE_CAMBIAR', 'DOCUMENTOS_REGISTRAR', 'DOCUMENTOS_PROCESAR_IA', 'DOCUMENTOS_VALIDAR', 'DOCUMENTOS_CLASIFICAR', 'DOCUMENTOS_EDITAR', 'DOCUMENTOS_BUSCAR', 'DOCUMENTOS_BUSCAR_SEM', 'RAG_CONSULTAR', 'RAG_HISTORIAL', 'DOCUMENTOS_DESCARGAR', 'ESTADISTICAS_VER');

-- CON: 6 permisos generales de la matriz.
INSERT IGNORE INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo = 'CON'
  AND p.codigo IN ('CLAVE_CAMBIAR', 'DOCUMENTOS_BUSCAR', 'DOCUMENTOS_BUSCAR_SEM', 'RAG_CONSULTAR', 'RAG_HISTORIAL', 'DOCUMENTOS_DESCARGAR');

-- AUD: 5 permisos generales de la matriz.
INSERT IGNORE INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo = 'AUD'
  AND p.codigo IN ('CLAVE_CAMBIAR', 'DOCUMENTOS_BUSCAR', 'AUDITORIA_CONSULTAR', 'ESTADISTICAS_VER', 'REPORTES_EXPORTAR');

-- Datos de catálogo institucional exclusivamente demostrativos:
-- Sustituir por dependencias reales definidas por el grupo.
INSERT IGNORE INTO `dependencias` (`nombre`, `descripcion`) VALUES
  ('Archivo Central - DEMO', 'Dependencia de ejemplo para pruebas de primer avance.'),
  ('Secretaria Administrativa - DEMO', 'Otra dependencia sintética de ejemplo.');

INSERT IGNORE INTO `tipos_documento` (`nombre`) VALUES
  ('Oficio'), ('Memorando'), ('Informe Técnico');

-- ===============================================================
-- 06. PRUEBA SQL OPCIONAL - DATOS SINTÉTICOS
-- ===============================================================
-- ATENCIÓN: estos datos NO prueban una carga real desde PHP, ni
-- procesamiento OCR/IA ni subida física de un archivo.
-- Credenciales LOCALES de demostración (NUNCA producción):
--   email: operador.demo@docuflow.test
--   clave: DocuFlowDemo2026!
-- Hash bcrypt generado con password_hash() de PHP.
-- La cadena 'interno' es EJEMPLO, no catálogo definitivo.
-- ===============================================================

INSERT INTO `usuarios`
  (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, dep.id, 'Operador de demostracion',
       'operador.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
JOIN `dependencias` dep ON dep.nombre = 'Archivo Central - DEMO'
WHERE r.codigo = 'OPE'
  AND NOT EXISTS (
    SELECT 1 FROM `usuarios` u
    WHERE u.email = 'operador.demo@docuflow.test'
  );

-- El archivo no existe físicamente hasta que se cargue desde PHP.
INSERT INTO `documentos`
  (`usuario_id`, `dependencia_id`, `tipo_doc_id`, `codigo_doc`,
   `nombre_original`, `ruta_almacenada`, `formato_mime`,
   `tamanio_bytes`, `estado_proceso`, `nivel_confidencialidad`,
   `estado_documental`)
SELECT u.id, dep.id, t.id, 'DEMO-OFI-001',
       'oficio_ejemplo_demo.pdf', 'uploads/demo/oficio_ejemplo_demo.pdf',
       'application/pdf', 245100, 'subido', 'interno', 'vigente'
FROM `usuarios` u
JOIN `dependencias` dep ON dep.id = u.dependencia_id
JOIN `tipos_documento` t ON t.nombre = 'Oficio'
WHERE u.email = 'operador.demo@docuflow.test'
  AND NOT EXISTS (
    SELECT 1 FROM `documentos` d
    WHERE d.codigo_doc = 'DEMO-OFI-001'
  );

-- Metadatos escritos manualmente solo para probar relación 1:0..1.
INSERT INTO `metadatos_documento`
  (`documento_id`, `numero_extraido`, `fecha_emision`,
   `remitente`, `destinatario`, `asunto`, `resumen_contenido`,
   `validado_humano`)
SELECT d.id, 'DEMO-001', '2026-10-20',
       'Area administrativa (demo)', 'Archivo central (demo)',
       'Documento de prueba SQL',
       'Contenido ficticio utilizado unicamente para verificar JOINs.',
       0
FROM `documentos` d
WHERE d.codigo_doc = 'DEMO-OFI-001'
  AND NOT EXISTS (
    SELECT 1 FROM `metadatos_documento` m
    WHERE m.documento_id = d.id
  )
ORDER BY d.id
LIMIT 1;

-- Registrar un evento DE PRUEBA sin fingir una acción desde la web.
INSERT INTO `auditoria_acciones`
  (`usuario_id`, `documento_id`, `accion`, `detalles_json`, `direccion_ip`)
SELECT d.usuario_id, d.id, 'INSERCION_SQL_DEMO',
       JSON_OBJECT('origen','script_sql','datos','sinteticos'), NULL
FROM `documentos` d
WHERE d.codigo_doc = 'DEMO-OFI-001'
  AND NOT EXISTS (
    SELECT 1 FROM `auditoria_acciones` a
    WHERE a.documento_id = d.id AND a.accion = 'INSERCION_SQL_DEMO'
  )
ORDER BY d.id
LIMIT 1;

-- ===============================================================
-- 07. CONSULTAS DE VERIFICACIÓN
-- ===============================================================

-- Comprobar que hay 11 tablas de esta aplicación.
SELECT COUNT(*) AS tablas_de_docuflow
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'roles','permisos','rol_permiso','dependencias','usuarios',
    'tipos_documento','documentos','metadatos_documento',
    'chunks_documento','evaluaciones_ia_log','auditoria_acciones'
  );

-- Ver los roles y sus permisos generales.
SELECT r.codigo AS rol, r.nombre AS perfil, p.codigo AS permiso
FROM `rol_permiso` rp
JOIN `roles` r ON r.id = rp.rol_id
JOIN `permisos` p ON p.id = rp.permiso_id
ORDER BY r.codigo, p.codigo;

-- Verificar documento sintético mediante JOINs reales.
SELECT d.id AS documento_id, d.codigo_doc, d.nombre_original,
       t.nombre AS tipo_documento, u.nombre AS registrado_por,
       r.codigo AS rol, dep.nombre AS dependencia,
       d.nivel_confidencialidad, d.estado_documental,
       m.asunto, m.validado_humano
FROM `documentos` d
JOIN `usuarios` u ON u.id = d.usuario_id
JOIN `roles` r ON r.id = u.rol_id
JOIN `dependencias` dep ON dep.id = d.dependencia_id
LEFT JOIN `tipos_documento` t ON t.id = d.tipo_doc_id
LEFT JOIN `metadatos_documento` m ON m.documento_id = d.id
WHERE d.codigo_doc = 'DEMO-OFI-001';

-- Cuenta de registros por tabla de catálogo y módulo.
SELECT 'roles' AS tabla, COUNT(*) AS filas FROM `roles`
UNION ALL SELECT 'permisos', COUNT(*) FROM `permisos`
UNION ALL SELECT 'rol_permiso', COUNT(*) FROM `rol_permiso`
UNION ALL SELECT 'dependencias', COUNT(*) FROM `dependencias`
UNION ALL SELECT 'usuarios', COUNT(*) FROM `usuarios`
UNION ALL SELECT 'tipos_documento', COUNT(*) FROM `tipos_documento`
UNION ALL SELECT 'documentos', COUNT(*) FROM `documentos`
UNION ALL SELECT 'metadatos_documento', COUNT(*) FROM `metadatos_documento`
UNION ALL SELECT 'chunks_documento', COUNT(*) FROM `chunks_documento`
UNION ALL SELECT 'evaluaciones_ia_log', COUNT(*) FROM `evaluaciones_ia_log`
UNION ALL SELECT 'auditoria_acciones', COUNT(*) FROM `auditoria_acciones`;
