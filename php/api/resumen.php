<?php
/**
 * API de Resumen y Métricas en Tiempo Real - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conexion.php';

try {
    $pdo = Database::conectar();

    // 1. Métricas clave
    $totalDocs = (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'vigente'")->fetchColumn();
    $totalAnulados = (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'anulado'")->fetchColumn();
    $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
    $totalTipos = (int)$pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn();
    $totalAuditorias = (int)$pdo->query("SELECT COUNT(*) FROM auditoria_acciones")->fetchColumn();
    $totalChunks = (int)$pdo->query("SELECT COUNT(*) FROM chunks_documento")->fetchColumn();

    // 2. Últimos documentos registrados
    $ultimosDocs = $pdo->query("
        SELECT d.id, d.codigo_doc, d.nombre_original, d.created_at, d.estado_proceso,
               COALESCE(t.nombre, 'General') AS tipo, u.nombre AS autor
        FROM documentos d
        JOIN usuarios u ON u.id = d.usuario_id
        LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
        ORDER BY d.id DESC
        LIMIT 5
    ")->fetchAll();

    // 3. Actividad reciente de auditoría
    $recienteAuditoria = $pdo->query("
        SELECT a.id, a.accion, a.created_at, a.direccion_ip,
               COALESCE(u.nombre, 'Sistema') AS usuario,
               d.codigo_doc
        FROM auditoria_acciones a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN documentos d ON d.id = a.documento_id
        ORDER BY a.id DESC
        LIMIT 6
    ")->fetchAll();

    echo json_encode([
        'ok' => true,
        'status' => 200,
        'data' => [
            'kpis' => [
                'total_documentos' => $totalDocs,
                'total_anulados'   => $totalAnulados,
                'total_usuarios'   => $totalUsuarios,
                'total_tipos'      => $totalTipos,
                'total_auditorias' => $totalAuditorias,
                'total_chunks_rag' => $totalChunks
            ],
            'ultimos_documentos' => $ultimosDocs,
            'auditoria_reciente' => $recienteAuditoria
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'message' => 'Error al calcular resumen: ' . $e->getMessage()
    ]);
}
