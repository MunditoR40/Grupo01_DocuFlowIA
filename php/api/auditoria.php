<?php
/**
 * API de Auditoría y Trazabilidad - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
 * Filtrado estricto por rol según matriz de permisos
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

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

    $usuario = Auth::requerirAutenticacion();

    // Verificación de permiso centralizado
    if (!DocumentoPermisos::puedeConsultarAuditoria($usuario)) {
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'status'  => 403,
            'message' => 'Acceso denegado. Su rol no tiene autorización para consultar la bitácora de auditoría.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filtroAudit = DocumentoPermisos::obtenerFiltroAuditoriaSQL($usuario, 'a');
    $whereSql = $filtroAudit['sql'];
    $params = $filtroAudit['params'];

    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.accion,
            a.detalles_json,
            a.direccion_ip,
            a.created_at,
            COALESCE(u.nombre, 'Sistema Automático') AS usuario_nombre,
            COALESCE(u.email, 'sistema@docuflow.ai') AS usuario_email,
            u.dependencia_id AS usuario_dependencia_id,
            d.codigo_doc,
            d.nombre_original,
            d.dependencia_id AS documento_dependencia_id
        FROM auditoria_acciones a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN documentos d ON d.id = a.documento_id
        WHERE {$whereSql}
        ORDER BY a.id DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Saneamiento de datos sensibles en detalles_json según rol
    $logsSaneados = array_map(function ($log) use ($usuario) {
        $detalles = json_decode($log['detalles_json'] ?? '{}', true) ?: [];
        
        // ADM no debe ver nombres de archivo ni códigos documentales en auditoría
        if ($usuario['rol_codigo'] === 'ADM') {
            unset($detalles['nombre_original'], $detalles['ruta_almacenada']);
            $log['nombre_original'] = null;
            $log['codigo_doc'] = null;
        }

        // Eliminar posibles tokens o contraseñas
        unset($detalles['password'], $detalles['token'], $detalles['csrf_token'], $detalles['contenido_texto']);

        $log['detalles_json'] = json_encode($detalles, JSON_UNESCAPED_UNICODE);
        return $log;
    }, $logs);

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'total'  => count($logsSaneados),
        'data'   => $logsSaneados
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
