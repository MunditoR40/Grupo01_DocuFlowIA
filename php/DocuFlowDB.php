<?php
/**
 * Repositorio de consultas para DocuFlow AI
 * Mapeo directo a las 11 tablas del modelo de base de datos
 */

require_once __DIR__ . '/conexion.php';

class DocuFlowDB {
    /**
     * Obtiene la lista de usuarios con su rol y dependencia.
     */
    public static function getUsuarios(): array {
        $sql = "SELECT u.id, u.nombre, u.email, u.activo,
                       r.codigo AS rol_codigo, r.nombre AS rol_nombre,
                       d.nombre AS dependencia_nombre,
                       u.created_at
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                JOIN dependencias d ON d.id = u.dependencia_id
                ORDER BY u.id ASC";
        return Database::conectar()->query($sql)->fetchAll();
    }

    /**
     * Obtiene el listado de documentos con tipo, usuario y metadatos.
     */
    public static function getDocumentos(): array {
        $sql = "SELECT doc.id, doc.codigo_doc, doc.nombre_original, doc.estado_documental,
                       doc.nivel_confidencialidad, doc.created_at,
                       td.nombre AS tipo_documento,
                       u.nombre AS registrado_por,
                       dep.nombre AS dependencia,
                       meta.asunto, meta.validado_humano
                FROM documentos doc
                JOIN usuarios u ON u.id = doc.usuario_id
                JOIN dependencias dep ON dep.id = doc.dependencia_id
                LEFT JOIN tipos_documento td ON td.id = doc.tipo_doc_id
                LEFT JOIN metadatos_documento meta ON meta.documento_id = doc.id
                ORDER BY doc.id DESC";
        return Database::conectar()->query($sql)->fetchAll();
    }

    /**
     * Obtiene todos los roles y la cantidad de permisos asignados a cada uno.
     */
    public static function getRolesConPermisos(): array {
        $sql = "SELECT r.id, r.codigo, r.nombre, r.descripcion,
                       COUNT(rp.permiso_id) AS total_permisos
                FROM roles r
                LEFT JOIN rol_permiso rp ON rp.rol_id = r.id
                GROUP BY r.id, r.codigo, r.nombre, r.descripcion
                ORDER BY r.id ASC";
        return Database::conectar()->query($sql)->fetchAll();
    }

    /**
     * Registra un evento en la tabla de auditoría_acciones.
     */
    public static function registrarAuditoria(?int $usuarioId, ?int $documentoId, string $accion, array $detalles = [], ?string $ip = null): bool {
        $sql = "INSERT INTO auditoria_acciones (usuario_id, documento_id, accion, detalles_json, direccion_ip)
                VALUES (:usuario_id, :documento_id, :accion, :detalles, :ip)";
        $stmt = Database::conectar()->prepare($sql);
        return $stmt->execute([
            'usuario_id'   => $usuarioId,
            'documento_id' => $documentoId,
            'accion'       => $accion,
            'detalles'     => !empty($detalles) ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null,
            'ip'           => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
        ]);
    }
}
