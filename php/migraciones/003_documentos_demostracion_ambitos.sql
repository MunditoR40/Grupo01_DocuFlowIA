-- ===============================================================
-- MIGRACIÓN NO DESTRUCTIVA: DOCUMENTOS SINTÉTICOS DE PRUEBA DE ÁMBITOS
-- DocuFlow AI - Grupo 01 | MySQL 8.0+ / MariaDB (Puerto 3307)
-- ===============================================================
-- Inserta documentos sintéticos para comprobar las restricciones
-- de confidencialidad y dependencias cruzadas entre los 5 roles.
-- ===============================================================

USE `docuflow_db`;

-- 1. Documento de Dependencia 2 (Secretaria Administrativa)
INSERT INTO `documentos` (
    `usuario_id`, `dependencia_id`, `tipo_doc_id`, `codigo_doc`,
    `nombre_original`, `ruta_almacenada`, `formato_mime`, `tamanio_bytes`,
    `estado_proceso`, `nivel_confidencialidad`, `estado_documental`
)
SELECT u.id, 2, 1, 'SEC-2026-001',
       'oficio_secretaria_dep2.pdf', 'uploads/doc_sec_demo_dep2.pdf',
       'application/pdf', 312, 'verificado', 'interno', 'vigente'
FROM `usuarios` u
WHERE u.email = 'operador2.demo@docuflow.test'
  AND NOT EXISTS (SELECT 1 FROM `documentos` WHERE codigo_doc = 'SEC-2026-001');

INSERT INTO `metadatos_documento` (`documento_id`, `numero_extraido`, `fecha_emision`, `remitente`, `destinatario`, `asunto`, `resumen_contenido`, `validado_humano`)
SELECT d.id, 'SEC-2026-001', '2026-10-25', 'Secretaría Administrativa', 'Dirección General', 'Expediente interno propio de la Secretaría Administrativa', 'Contenido sintético exclusivo de Dependencia 2', 1
FROM `documentos` d
WHERE d.codigo_doc = 'SEC-2026-001'
  AND NOT EXISTS (SELECT 1 FROM `metadatos_documento` WHERE documento_id = d.id);

INSERT INTO `chunks_documento` (`documento_id`, `numero_chunk`, `contenido_texto`)
SELECT d.id, 1, 'Expediente SEC-2026-001 de Secretaria Administrativa. Información exclusiva de dependencia 2.'
FROM `documentos` d
WHERE d.codigo_doc = 'SEC-2026-001'
  AND NOT EXISTS (SELECT 1 FROM `chunks_documento` WHERE documento_id = d.id);

-- 2. Documento Confidencial en Dependencia 1 (Archivo Central)
INSERT INTO `documentos` (
    `usuario_id`, `dependencia_id`, `tipo_doc_id`, `codigo_doc`,
    `nombre_original`, `ruta_almacenada`, `formato_mime`, `tamanio_bytes`,
    `estado_proceso`, `nivel_confidencialidad`, `estado_documental`
)
SELECT u.id, 1, 3, 'CONF-2026-001',
       'informe_financiero_reservado.pdf', 'uploads/doc_confidencial_demo.pdf',
       'application/pdf', 312, 'verificado', 'confidencial', 'vigente'
FROM `usuarios` u
WHERE u.email = 'operador.demo@docuflow.test'
  AND NOT EXISTS (SELECT 1 FROM `documentos` WHERE codigo_doc = 'CONF-2026-001');

INSERT INTO `metadatos_documento` (`documento_id`, `numero_extraido`, `fecha_emision`, `remitente`, `destinatario`, `asunto`, `resumen_contenido`, `validado_humano`)
SELECT d.id, 'CONF-2026-001', '2026-10-25', 'Comisión Especial de Presupuesto', 'Rectorado', 'Auditoría financiera reservada confidencial 2026', 'Documento confidencial restringido para personal no autorizado', 1
FROM `documentos` d
WHERE d.codigo_doc = 'CONF-2026-001'
  AND NOT EXISTS (SELECT 1 FROM `metadatos_documento` WHERE documento_id = d.id);

INSERT INTO `chunks_documento` (`documento_id`, `numero_chunk`, `contenido_texto`)
SELECT d.id, 1, 'Expediente CONF-2026-001: Informe financiero confidencial clasificado de la Universidad Nacional del Santa.'
FROM `documentos` d
WHERE d.codigo_doc = 'CONF-2026-001'
  AND NOT EXISTS (SELECT 1 FROM `chunks_documento` WHERE documento_id = d.id);

-- 3. Documento Anulado
INSERT INTO `documentos` (
    `usuario_id`, `dependencia_id`, `tipo_doc_id`, `codigo_doc`,
    `nombre_original`, `ruta_almacenada`, `formato_mime`, `tamanio_bytes`,
    `estado_proceso`, `nivel_confidencialidad`, `estado_documental`,
    `motivo_anulacion`, `fecha_anulacion`
)
SELECT u.id, 1, 1, 'ANUL-2026-001',
       'oficio_anulado.pdf', 'uploads/doc_confidencial_demo.pdf',
       'application/pdf', 312, 'verificado', 'interno', 'anulado',
       'Trámite duplicado por error administrativo', NOW()
FROM `usuarios` u
WHERE u.email = 'operador.demo@docuflow.test'
  AND NOT EXISTS (SELECT 1 FROM `documentos` WHERE codigo_doc = 'ANUL-2026-001');
