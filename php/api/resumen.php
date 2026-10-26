<?php
/**
 * API de Resumen y Métricas Diferenciadas por Rol - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
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

    // Verificación de permiso para consultar estadísticas
    if (!DocumentoPermisos::puedeConsultarEstadisticas($usuario)) {
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'status'  => 403,
            'message' => 'Acceso denegado. Su rol no tiene autorización para consultar estadísticas operativas.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rol = $usuario['rol_codigo'] ?? '';
    $kpis = [];
    $ultimosDocs = [];
    $recienteAuditoria = [];

    // ─────────────────────────────────────────────────────────
    // 1. Tratamiento Diferenciado según Rol
    // ─────────────────────────────────────────────────────────
    if ($rol === 'OPE') {
        // Operador: solo métricas y documentos propios
        $stmtDocs = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE usuario_id = :uid AND estado_documental = 'vigente'");
        $stmtDocs->execute(['uid' => (int)$usuario['id']]);
        $totalDocs = (int)$stmtDocs->fetchColumn();

        $stmtAnul = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE usuario_id = :uid AND estado_documental = 'anulado'");
        $stmtAnul->execute(['uid' => (int)$usuario['id']]);
        $totalAnulados = (int)$stmtAnul->fetchColumn();

        $stmtAud = $pdo->prepare("SELECT COUNT(*) FROM auditoria_acciones WHERE usuario_id = :uid");
        $stmtAud->execute(['uid' => (int)$usuario['id']]);
        $totalAudits = (int)$stmtAud->fetchColumn();

        $kpis = [
            'total_documentos' => $totalDocs,
            'total_anulados'   => $totalAnulados,
            'total_usuarios'   => 1,
            'total_tipos'      => (int)$pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn(),
            'total_auditorias' => $totalAudits
        ];

        $stmtLastDocs = $pdo->prepare("
            SELECT d.id, d.codigo_doc, d.nombre_original, d.created_at, d.estado_proceso,
                   COALESCE(t.nombre, 'General') AS tipo, u.nombre AS autor
            FROM documentos d
            JOIN usuarios u ON u.id = d.usuario_id
            LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
            WHERE d.usuario_id = :uid AND d.estado_documental = 'vigente'
            ORDER BY d.id DESC
            LIMIT 5
        ");
        $stmtLastDocs->execute(['uid' => (int)$usuario['id']]);
        $ultimosDocs = $stmtLastDocs->fetchAll();

    } elseif ($rol === 'GES') {
        // Gestor: métricas y documentos de su dependencia
        $depId = (int)$usuario['dependencia_id'];

        $stmtDocs = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE dependencia_id = :did AND estado_documental = 'vigente'");
        $stmtDocs->execute(['did' => $depId]);
        $totalDocs = (int)$stmtDocs->fetchColumn();

        $stmtAnul = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE dependencia_id = :did AND estado_documental = 'anulado'");
        $stmtAnul->execute(['did' => $depId]);
        $totalAnulados = (int)$stmtAnul->fetchColumn();

        $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE dependencia_id = :did AND activo = 1");
        $stmtUsers->execute(['did' => $depId]);
        $totalUsers = (int)$stmtUsers->fetchColumn();

        $kpis = [
            'total_documentos' => $totalDocs,
            'total_anulados'   => $totalAnulados,
            'total_usuarios'   => $totalUsers,
            'total_tipos'      => (int)$pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn(),
            'total_auditorias' => (int)$pdo->query("SELECT COUNT(*) FROM auditoria_acciones WHERE documento_id IN (SELECT id FROM documentos WHERE dependencia_id = {$depId})")->fetchColumn()
        ];

        $stmtLastDocs = $pdo->prepare("
            SELECT d.id, d.codigo_doc, d.nombre_original, d.created_at, d.estado_proceso,
                   COALESCE(t.nombre, 'General') AS tipo, u.nombre AS autor
            FROM documentos d
            JOIN usuarios u ON u.id = d.usuario_id
            LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
            WHERE d.dependencia_id = :did AND d.estado_documental = 'vigente'
            ORDER BY d.id DESC
            LIMIT 5
        ");
        $stmtLastDocs->execute(['did' => $depId]);
        $ultimosDocs = $stmtLastDocs->fetchAll();

    } elseif ($rol === 'ADM') {
        // Administrador: métricas técnicas y administrativas, sin contenido documental sensible
        $kpis = [
            'total_documentos' => (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'vigente'")->fetchColumn(),
            'total_anulados'   => (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'anulado'")->fetchColumn(),
            'total_usuarios'   => (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn(),
            'total_tipos'      => (int)$pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn(),
            'total_auditorias' => (int)$pdo->query("SELECT COUNT(*) FROM auditoria_acciones")->fetchColumn()
        ];

        // ADM no visualiza el contenido ni listado de documentos operativos recientes
        $ultimosDocs = [];

        // Auditoría administrativa técnica
        $recienteAuditoria = $pdo->query("
            SELECT a.id, a.accion, a.created_at, a.direccion_ip,
                   COALESCE(u.nombre, 'Sistema') AS usuario,
                   NULL AS codigo_doc
            FROM auditoria_acciones a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.documento_id IS NULL OR a.accion IN ('LOGIN', 'LOGOUT', 'USUARIO_CREADO', 'USUARIO_MODIFICADO')
            ORDER BY a.id DESC
            LIMIT 5
        ")->fetchAll();

    } elseif ($rol === 'AUD') {
        // Auditor: estadísticas globales y actividad para control
        $kpis = [
            'total_documentos' => (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'vigente'")->fetchColumn(),
            'total_anulados'   => (int)$pdo->query("SELECT COUNT(*) FROM documentos WHERE estado_documental = 'anulado'")->fetchColumn(),
            'total_usuarios'   => (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn(),
            'total_tipos'      => (int)$pdo->query("SELECT COUNT(*) FROM tipos_documento")->fetchColumn(),
            'total_auditorias' => (int)$pdo->query("SELECT COUNT(*) FROM auditoria_acciones")->fetchColumn()
        ];

        $ultimosDocs = $pdo->query("
            SELECT d.id, d.codigo_doc, d.nombre_original, d.created_at, d.estado_proceso,
                   COALESCE(t.nombre, 'General') AS tipo, u.nombre AS autor
            FROM documentos d
            JOIN usuarios u ON u.id = d.usuario_id
            LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
            WHERE d.estado_documental = 'vigente'
            ORDER BY d.id DESC
            LIMIT 5
        ")->fetchAll();

        $recienteAuditoria = $pdo->query("
            SELECT a.id, a.accion, a.created_at, a.direccion_ip,
                   COALESCE(u.nombre, 'Sistema') AS usuario,
                   d.codigo_doc
            FROM auditoria_acciones a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            LEFT JOIN documentos d ON d.id = a.documento_id
            ORDER BY a.id DESC
            LIMIT 5
        ")->fetchAll();
    }

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'data'   => [
            'kpis'               => $kpis,
            'ultimos_documentos' => $ultimosDocs,
            'auditoria_reciente' => $recienteAuditoria
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en php/api/resumen.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'status'  => 500,
        'message' => 'Error al calcular el resumen operativo.'
    ], JSON_UNESCAPED_UNICODE);
}
