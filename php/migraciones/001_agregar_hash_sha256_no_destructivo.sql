-- ===============================================================
-- MIGRACIÓN NO DESTRUCTIVA: AGREGAR HASH SHA-256 A DOCUMENTOS
-- DocuFlow AI - Grupo 01 | MySQL 8.0+
-- ===============================================================
-- Permite indexar y validar la integridad criptográfica y unicidad
-- física de los documentos almacenados mediante SHA-256 (64 caracteres hex).
-- ===============================================================

USE `docuflow_db`;

-- Agregar columna hash_sha256 solo si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'documentos' 
      AND COLUMN_NAME = 'hash_sha256'
);

SET @sql_add_col = IF(
    @col_exists = 0,
    'ALTER TABLE `documentos` ADD COLUMN `hash_sha256` CHAR(64) NULL AFTER `tamanio_bytes`, ADD KEY `idx_documentos_hash` (`hash_sha256`);',
    'SELECT "La columna hash_sha256 ya existe en la tabla documentos." AS resultado;'
);

PREPARE stmt FROM @sql_add_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
