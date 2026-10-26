<?php
/**
 * API de Asistente de Consulta Contextual - DocuFlow AI
 * Búsqueda de coincidencia textual sobre chunks_documento y metadatos_documento en MySQL 3307
 * Filtrado estricto por ámbito de usuario, dependencia y nivel de confidencialidad
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

$rawInput = file_get_contents('php://input');
$input = (!empty($rawInput) ? json_decode($rawInput, true) : null) ?? $_POST ?? [];
$query = trim($input['query'] ?? ($input['mensaje'] ?? ($input['message'] ?? '')));

if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'status'  => 400,
        'message' => 'Por favor ingrese una consulta para buscar en el repositorio.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::conectar();
    Auth::iniciarSesion();

    // 1. Validar sesión y permiso específico
    $usuario = Auth::requerirPermiso('RAG_CONSULTAR');

    // 2. Obtener filtro SQL estricto según rol, dependencia y confidencialidad
    $filtroScope = DocumentoPermisos::obtenerFiltroChatbotSQL($usuario, 'd');
    $whereScope = $filtroScope['sql'];
    $params = $filtroScope['params'];

    // 3. Procesar términos de búsqueda para consulta parametrizada
    $terminos = preg_split('/\s+/', $query);
    $condicionesTerminos = [];

    $paramIndex = 0;
    foreach ($terminos as $termino) {
        $termLimpio = trim($termino);
        if (mb_strlen($termLimpio) > 2) {
            $cKey = "c_term_" . $paramIndex;
            $mKey = "m_term_" . $paramIndex;
            $dKey = "d_term_" . $paramIndex;
            $condicionesTerminos[] = "(c.contenido_texto LIKE :$cKey OR m.asunto LIKE :$mKey OR d.codigo_doc LIKE :$dKey)";
            $params[$cKey] = "%$termLimpio%";
            $params[$mKey] = "%$termLimpio%";
            $params[$dKey] = "%$termLimpio%";
            $paramIndex++;
        }
    }

    $whereTerms = !empty($condicionesTerminos) ? " AND (" . implode(' OR ', $condicionesTerminos) . ") " : "";

    // 4. Consulta contextual acotada exclusivamente a documentos autorizados
    $stmt = $pdo->prepare("
        SELECT d.id, d.codigo_doc, d.nombre_original,
               m.asunto, m.resumen_contenido,
               c.contenido_texto,
               COALESCE(t.nombre, 'General') AS tipo_doc
        FROM documentos d
        LEFT JOIN metadatos_documento m ON m.documento_id = d.id
        LEFT JOIN chunks_documento c ON c.documento_id = d.id
        LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
        WHERE 1=1 {$whereScope} {$whereTerms}
        ORDER BY d.id DESC
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
        $answer = "Coincidencia contextual autorizada en el expediente <strong>" . htmlspecialchars($primer['codigo_doc']) . "</strong> (" . htmlspecialchars($primer['tipo_doc']) . ").<br>"
                . "<em>Asunto:</em> " . htmlspecialchars($primer['asunto'] ?? 'No especificado') . ".<br>"
                . "<span class='text-xs text-brand-400'>Búsqueda contextual en base de datos MySQL (puerto 3307).</span>";
    } else {
        $answer = "No se encontraron expedientes autorizados coincidentes para <em>\"" . htmlspecialchars($query) . "\"</em> dentro de su ámbito (" . htmlspecialchars($usuario['rol_codigo']) . " - " . htmlspecialchars($usuario['dependencia_nombre']) . ").";
    }

    // Registrar en auditoria_acciones sin exponer texto sensible
    DocuFlowDB::registrarAuditoria(
        (int)$usuario['id'],
        !empty($coincidencias) ? (int)$coincidencias[0]['id'] : null,
        'DOCUMENTO_CONSULTADO',
        [
            'canal'            => 'asistente_contextual',
            'coincidencias'    => count($coincidencias)
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
        'message' => 'Error interno al consultar el asistente contextual.'
    ], JSON_UNESCAPED_UNICODE);
}
