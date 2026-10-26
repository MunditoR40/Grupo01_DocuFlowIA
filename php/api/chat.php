<?php
/**
 * API de Chatbot RAG - DocuFlow AI
 * Búsqueda contextual sobre chunks_documento y metadatos_documento en MySQL 3307
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../DocuFlowDB.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$query = trim($input['query'] ?? '');

if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'status' => 400,
        'message' => 'Por favor ingrese una pregunta.'
    ]);
    exit;
}

try {
    $pdo = Database::conectar();

    // Búsqueda de coincidencia en chunks_documento y metadatos_documento
    $terminos = preg_split('/\s+/', $query);
    $condiciones = [];
    $params = [];

    foreach ($terminos as $i => $termino) {
        if (strlen($termino) > 2) {
            $paramKey = "term_$i";
            $condiciones[] = "(c.contenido_texto LIKE :$paramKey OR m.asunto LIKE :$paramKey OR d.codigo_doc LIKE :$paramKey)";
            $params[$paramKey] = "%$termino%";
        }
    }

    $whereSql = !empty($condiciones) ? implode(' OR ', $condiciones) : "1=1";

    $stmt = $pdo->prepare("
        SELECT d.id, d.codigo_doc, d.nombre_original,
               m.asunto, m.resumen_contenido,
               c.contenido_texto,
               t.nombre AS tipo_doc
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
        $answer = "Encontré información relevante en el expediente <strong>{$primer['codigo_doc']}</strong> ({$primer['tipo_doc']}).<br>"
                . "<em>Asunto:</em> " . htmlspecialchars($primer['asunto'] ?? 'No especificado') . ".<br>"
                . "<span class='text-xs text-brand-400'>Fuente: Base de Datos Relacional y Vectorial RAG (MySQL 3307).</span>";
    } else {
        $answer = "No se hallaron coincidencias directas en la base vectorial para <em>\"" . htmlspecialchars($query) . "\"</em>. Prueba consultar por <em>\"oficio\"</em>, <em>\"recursos humanos\"</em> o <em>\"demo\"</em>.";
    }

    // Registrar en auditoria_acciones
    DocuFlowDB::registrarAuditoria(
        null,
        !empty($coincidencias) ? (int)$coincidencias[0]['id'] : null,
        'CONSULTA_CHATBOT_RAG',
        [
            'query' => $query,
            'resultados_count' => count($coincidencias)
        ]
    );

    echo json_encode([
        'ok' => true,
        'status' => 200,
        'data' => [
            'answer' => $answer,
            'sources' => $fuentes
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'message' => 'Error en el motor RAG: ' . $e->getMessage()
    ]);
}
