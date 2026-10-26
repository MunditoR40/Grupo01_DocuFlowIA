<?php
/**
 * API de Gestión Documental y OCR - DocuFlow AI
 * Persistencia en docuflow_db (MySQL 3307)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../DocuFlowDB.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Database::conectar();

    // ─────────────────────────────────────────────────────────
    // 1. GET: Listar todos los documentos para el Repositorio RAG
    // ─────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $busqueda = trim($_GET['q'] ?? '');
        $categoria = trim($_GET['categoria'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $sql = "
            SELECT 
                d.id,
                d.codigo_doc,
                d.nombre_original,
                d.ruta_almacenada,
                d.formato_mime,
                d.tamanio_bytes,
                d.estado_proceso,
                d.nivel_confidencialidad,
                d.estado_documental,
                d.created_at,
                COALESCE(t.nombre, 'Sin clasificar') AS tipo_documento,
                u.nombre AS registrado_por,
                u.email AS usuario_email,
                dep.nombre AS dependencia,
                m.numero_extraido,
                m.fecha_emision,
                m.remitente,
                m.destinatario,
                m.asunto,
                m.resumen_contenido,
                m.validado_humano
            FROM documentos d
            JOIN usuarios u ON u.id = d.usuario_id
            JOIN dependencias dep ON dep.id = d.dependencia_id
            LEFT JOIN tipos_documento t ON t.id = d.tipo_doc_id
            LEFT JOIN metadatos_documento m ON m.documento_id = d.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (d.codigo_doc LIKE :q OR d.nombre_original LIKE :q OR m.asunto LIKE :q OR m.resumen_contenido LIKE :q)";
            $params['q'] = "%$busqueda%";
        }

        if (!empty($categoria) && $categoria !== 'todos') {
            $sql .= " AND t.nombre = :cat";
            $params['cat'] = $categoria;
        }

        if (!empty($estado) && $estado !== 'todos') {
            $sql .= " AND d.estado_documental = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'status' => 200,
            'total' => count($documentos),
            'data' => $documentos
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 2. POST (action=process): Simulación/Procesamiento OCR Inicial
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'process') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $filename = $input['filename'] ?? ($_FILES['archivo']['name'] ?? 'documento.pdf');

        // Generar extracción inteligente de metadatos según el archivo
        $tipoDoc = 'Oficio';
        if (stripos($filename, 'memo') !== false) $tipoDoc = 'Memorando';
        if (stripos($filename, 'informe') !== false) $tipoDoc = 'Informe Técnico';
        if (stripos($filename, 'contrato') !== false) $tipoDoc = 'Contrato';

        $codigoAleatorio = 'DOC-' . rand(100, 999) . '-' . date('Y') . '-UNS';

        echo json_encode([
            'ok' => true,
            'status' => 200,
            'data' => [
                'filename' => $filename,
                'metadata' => [
                    'tipo' => $tipoDoc,
                    'codigo' => $codigoAleatorio,
                    'fecha' => date('Y-m-d'),
                    'asunto' => 'Expediente digitalizado mediante DocuFlow AI: ' . htmlspecialchars($filename),
                    'dependencia' => 'Archivo Central - DEMO',
                    'confianza' => 98.4,
                    'tags' => ['Digitalizado', 'OCR', 'RAG', $tipoDoc, 'UNS']
                ]
            ]
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 3. POST (action=store o normal): Confirmar y Guardar en MySQL
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $nombreOriginal = $input['nombre_original'] ?? ($input['filename'] ?? 'documento_ocr.pdf');
        $codigoDoc       = $input['codigo_doc'] ?? ('DOC-' . date('Ymd') . '-' . rand(100, 999));
        $asunto          = $input['asunto'] ?? 'Documento procesado por OCR Inteligente';
        $tipoNombre      = $input['tipo_documento'] ?? 'Oficio';
        $resumen         = $input['resumen'] ?? ('Contenido extraído y normalizado para el expediente ' . $codigoDoc);
        $confidencial    = $input['nivel_confidencialidad'] ?? 'interno';

        // Obtener usuario id (o el primero registrado)
        $usuarioId = $pdo->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
        $depId = $pdo->query("SELECT id FROM dependencias ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;

        // Obtener o crear tipo_documento
        $stmtTipo = $pdo->prepare("SELECT id FROM tipos_documento WHERE nombre = :nombre LIMIT 1");
        $stmtTipo->execute(['nombre' => $tipoNombre]);
        $tipoId = $stmtTipo->fetchColumn();

        if (!$tipoId) {
            $insTipo = $pdo->prepare("INSERT INTO tipos_documento (nombre) VALUES (:nombre)");
            $insTipo->execute(['nombre' => $tipoNombre]);
            $tipoId = $pdo->lastInsertId();
        }

        // Ruta de almacenamiento
        $rutaFisica = 'uploads/' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nombreOriginal);
        $tamanio = $input['tamanio_bytes'] ?? rand(150000, 500000);

        // Iniciar transacción para garantizar consistencia
        $pdo->beginTransaction();

        $stmtDoc = $pdo->prepare("
            INSERT INTO documentos (
                usuario_id, dependencia_id, tipo_doc_id, codigo_doc,
                nombre_original, ruta_almacenada, formato_mime, tamanio_bytes,
                estado_proceso, nivel_confidencialidad, estado_documental
            ) VALUES (
                :uid, :did, :tid, :codigo,
                :nombre, :ruta, 'application/pdf', :tamanio,
                'verificado', :confidencial, 'vigente'
            )
        ");

        $stmtDoc->execute([
            'uid'          => $usuarioId,
            'did'          => $depId,
            'tid'          => $tipoId,
            'codigo'       => $codigoDoc,
            'nombre'       => $nombreOriginal,
            'ruta'         => $rutaFisica,
            'tamanio'      => $tamanio,
            'confidencial' => $confidencial
        ]);

        $documentoId = $pdo->lastInsertId();

        // Insertar metadatos en metadatos_documento
        $stmtMeta = $pdo->prepare("
            INSERT INTO metadatos_documento (
                documento_id, numero_extraido, fecha_emision,
                remitente, destinatario, asunto, resumen_contenido, validado_humano
            ) VALUES (
                :doc_id, :num, CURDATE(),
                'DocuFlow OCR Engine', 'Oficina Receptora', :asunto, :resumen, 1
            )
        ");

        $stmtMeta->execute([
            'doc_id'  => $documentoId,
            'num'     => $codigoDoc,
            'asunto'  => $asunto,
            'resumen' => $resumen
        ]);

        // Insertar chunk de texto en chunks_documento (para consultas RAG)
        $stmtChunk = $pdo->prepare("
            INSERT INTO chunks_documento (documento_id, numero_chunk, contenido_texto)
            VALUES (:doc_id, 1, :texto)
        ");
        $stmtChunk->execute([
            'doc_id' => $documentoId,
            'texto'  => "Expediente {$codigoDoc} ({$tipoNombre}): {$asunto}. {$resumen}."
        ]);

        // Registrar acción en auditoria_acciones
        DocuFlowDB::registrarAuditoria(
            (int)$usuarioId,
            (int)$documentoId,
            'CARGA_DOCUMENTO_OCR',
            [
                'codigo' => $codigoDoc,
                'nombre' => $nombreOriginal,
                'tamano' => $tamanio
            ]
        );

        $pdo->commit();

        http_response_code(201);
        echo json_encode([
            'ok' => true,
            'status' => 201,
            'message' => 'Documento y metadatos persistidos exitosamente en la base de datos (MySQL 3307).',
            'data' => [
                'documento_id' => (int)$documentoId,
                'codigo_doc' => $codigoDoc
            ]
        ]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
