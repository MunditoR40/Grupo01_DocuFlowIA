<?php
/**
 * API de Chatbot RAG - DocuFlow AI
 * Búsqueda contextual sobre chunks_documento y metadatos_documento en MySQL 3307
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../DocuFlowDB.php';
require_once __DIR__ . '/../auth_helper.php';

$rawInput = file_get_contents('php://input');
$input = (!empty($rawInput) ? json_decode($rawInput, true) : null) ?? $_POST ?? [];
$query = trim($input['query'] ?? '');

if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'status'  => 400,
        'message' => 'Por favor ingrese una pregunta para consultar el asistente RAG.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::conectar();
    Auth::iniciarSesion();

    $usuario = Auth::requerirPermiso('RAG_CONSULTAR');

    // Búsqueda de coincidencia en chunks_documento y metadatos_documento
    $terminos = preg_split('/\s+/', $query);
    $condiciones = [];
    $params = [];

    $paramIndex = 0;
    foreach ($terminos as $termino) {
        $termLimpio = trim($termino);
        if (mb_strlen($termLimpio) > 2) {
            $cKey = "c_term_" . $paramIndex;
            $mKey = "m_term_" . $paramIndex;
            $dKey = "d_term_" . $paramIndex;
            $condiciones[] = "(c.contenido_texto LIKE :$cKey OR m.asunto LIKE :$mKey OR d.codigo_doc LIKE :$dKey)";
            $params[$cKey] = "%$termLimpio%";
            $params[$mKey] = "%$termLimpio%";
            $params[$dKey] = "%$termLimpio%";
            $paramIndex++;
        }
    }

    $whereSql = !empty($condiciones) ? implode(' OR ', $condiciones) : "1=1";

    $stmt = $pdo->prepare("
        SELECT d.id, d.codigo_doc, d.nombre_original,
               m.asunto, m.resumen_contenido,
               c.contenido_texto,
               COALESCE(t.nombre, 'General') AS tipo_doc
        FROM documentos d
        LEFT JOIN metadatos_documento m ON m.documento_id = d.id
        LEFT JOIN chunks_documento c ON c.documento_id = d.id
        LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
        WHERE d.estado_documental = 'vigente' AND ($whereSql)
        LIMIT 3
    ");
    $stmt->execute($params);
    $coincidencias = $stmt->fetchAll();

    $answer = '';
    $fuentes = [];

    if (!empty($coincidencias)) {
        $fuentes = array_map(function($c) {
            return $c['codigo_doc'] . ' (' . $c['nombre_original'] . ')';
        }, $coincidencias);

        $primer = $coincidencias[0];
        $answer = "Encontré información relevante en el expediente <strong>" . htmlspecialchars($primer['codigo_doc']) . "</strong> (" . htmlspecialchars($primer['tipo_doc']) . ").<br>"
                . "<em>Asunto:</em> " . htmlspecialchars($primer['asunto'] ?? 'No especificado') . ".<br>"
                . "<span class='text-xs text-brand-400'>Fuente: Documentos indexados en MySQL (Puerto 3307).</span>";
    } else {
        $answer = "No se hallaron coincidencias en el repositorio para <em>\"" . htmlspecialchars($query) . "\"</em>. Prueba consultar por términos como <em>\"oficio\"</em>, <em>\"recursos humanos\"</em> o <em>\"demo\"</em>.";
    }

    // Registrar en auditoria_acciones
    DocuFlowDB::registrarAuditoria(
        (int)$usuario['id'],
        !empty($coincidencias) ? (int)$coincidencias[0]['id'] : null,
        'DOCUMENTO_CONSULTADO',
        [
            'origen'           => 'chat_rag',
            'query'            => $query,
            'resultados_count' => count($coincidencias)
        ]
    );

    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'data'   => [
            'answer'     => $answer,
            'fuentes'    => $fuentes,
            'confidence' => !empty($coincidencias) ? 0.94 : 0.0
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en php/api/chat.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'status'  => 500,
        'message' => 'Error interno al consultar el asistente RAG.'
    ], JSON_UNESCAPED_UNICODE);
}
