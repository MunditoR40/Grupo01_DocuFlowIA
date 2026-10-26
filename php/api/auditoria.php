<?php
/**
 * API de Auditoría y Trazabilidad - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../DocuFlowDB.php';
require_once __DIR__ . '/../auth_helper.php';

try {
    $pdo = Database::conectar();
    Auth::iniciarSesion();

    // Verificación de permiso: solo roles con AUDITORIA_CONSULTAR (ej: AUD, GES, ADM)
    $usuario = Auth::requerirPermiso('AUDITORIA_CONSULTAR');

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

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'total'  => count($logs),
        'data'   => $logs
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en php/api/auditoria.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'status'  => 500,
        'message' => 'Error al consultar las trazas de auditoría.'
    ], JSON_UNESCAPED_UNICODE);
}
