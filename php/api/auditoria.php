<?php
/**
 * API de Auditoría y Trazabilidad - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conexion.php';

try {
    $pdo = Database::conectar();

    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.accion,
            a.detalles_json,
            a.direccion_ip,
            a.created_at,
            COALESCE(u.nombre, 'Sistema Automático') AS usuario_nombre,
            COALESCE(u.email, 'sistema@docuflow.ai') AS usuario_email,
            d.codigo_doc,
            d.nombre_original
        FROM auditoria_acciones a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN documentos d ON d.id = a.documento_id
        ORDER BY a.id DESC
        LIMIT 50
    ");

    $logs = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'status' => 200,
        'total' => count($logs),
        'data' => $logs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'message' => 'Error al obtener auditoría: ' . $e->getMessage()
    ]);
}
