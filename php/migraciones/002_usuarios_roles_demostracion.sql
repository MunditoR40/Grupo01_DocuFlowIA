-- ===============================================================
-- MIGRACIÓN NO DESTRUCTIVA: USUARIOS SINTÉTICOS PARA LOS 5 ROLES
-- DocuFlow AI - Grupo 01 | MySQL 8.0+ / MariaDB (Puerto 3307)
-- ===============================================================
-- Crea cuentas de prueba seguras para validar los 5 roles y la matriz
-- de permisos en pruebas de integración y sustentación.
-- Clave para todos los usuarios demo: DocuFlowDemo2026!
-- ===============================================================

USE `docuflow_db`;

-- 1. Gestor Documental (GES) - Dependencia 1 (Archivo Central)
INSERT INTO `usuarios` (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, 1, 'Gestor Documental Demo', 'gestor.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
WHERE r.codigo = 'GES'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE email = 'gestor.demo@docuflow.test');

-- 2. Consultor (CON) - Dependencia 1 (Archivo Central)
INSERT INTO `usuarios` (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, 1, 'Consultor Demo', 'consultor.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
WHERE r.codigo = 'CON'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE email = 'consultor.demo@docuflow.test');

-- 3. Auditor (AUD) - Dependencia 1 (Archivo Central)
INSERT INTO `usuarios` (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, 1, 'Auditor Demo', 'auditor.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
WHERE r.codigo = 'AUD'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE email = 'auditor.demo@docuflow.test');

-- 4. Segundo Operador (OPE) - Dependencia 2 (Secretaria Administrativa)
INSERT INTO `usuarios` (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, 2, 'Segundo Operador Demo', 'operador2.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
WHERE r.codigo = 'OPE'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE email = 'operador2.demo@docuflow.test');

-- 5. Segundo Gestor (GES) - Dependencia 2 (Secretaria Administrativa)
INSERT INTO `usuarios` (`rol_id`, `dependencia_id`, `nombre`, `email`, `password`, `activo`)
SELECT r.id, 2, 'Segundo Gestor Demo', 'gestor2.demo@docuflow.test',
       '$2y$12$0GkJ/vkX.uzdLqnMJR7B4uhU6.w2D1u2D9t.v24USgmbGcEyYXF4K', 1
FROM `roles` r
WHERE r.codigo = 'GES'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE email = 'gestor2.demo@docuflow.test');
