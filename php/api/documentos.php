<?php
/**
 * API de Gestión Documental, Registro Real y Descarga Segura - DocuFlow AI
 * Persistencia en MySQL (docuflow_db, Puerto 3307) mediante PHP PDO nativo
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

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = Database::conectar();
    Auth::iniciarSesion();

    // ─────────────────────────────────────────────────────────
    // 0. GET: Catálogo de Tipos y Dependencias (para selects UI)
    // ─────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'catalogo') {
        $tipos = $pdo->query("SELECT id, nombre FROM tipos_documento ORDER BY nombre ASC")->fetchAll();
        $dependencias = $pdo->query("SELECT id, nombre FROM dependencias ORDER BY nombre ASC")->fetchAll();

        http_response_code(200);
        echo json_encode([
            'ok'     => true,
            'status' => 200,
            'data'   => [
                'tipos'        => $tipos,
                'dependencias' => $dependencias
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 1. GET: Descargar archivo físico con autorización centralizada
    // ─────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'download') {
        $usuario = Auth::requerirPermiso('DOCUMENTOS_DESCARGAR');
        $docId = (int)($_GET['id'] ?? 0);

        if ($docId <= 0) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'Identificador de documento no válido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Consultar el documento en MySQL
        $stmt = $pdo->prepare("
            SELECT d.*, dep.nombre AS dependencia_nombre
            FROM documentos d
            JOIN dependencias dep ON dep.id = d.dependencia_id
            WHERE d.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $docId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            http_response_code(404);
            echo json_encode([
                'ok'      => false,
                'status'  => 404,
                'message' => 'Documento no encontrado en el sistema.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Comprobación de autorización documental mediante servicio centralizado
        if (!DocumentoPermisos::puedeDescargar($usuario, $doc)) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'Acceso denegado. No tiene autorización para descargar este documento según las políticas de rol, dependencia y confidencialidad.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Resolución segura de ruta física y prevención de Path Traversal
        $uploadBaseDir = realpath(__DIR__ . '/../../uploads');
        if ($uploadBaseDir === false) {
            $uploadBaseDir = __DIR__ . '/../../uploads';
        }

        $nombreArchivoInterno = basename($doc['ruta_almacenada']);
        $rutaFisica = realpath($uploadBaseDir . DIRECTORY_SEPARATOR . $nombreArchivoInterno);

        // Validar que el archivo resuelto esté dentro del directorio uploads/ permitido
        if ($rutaFisica === false || !str_starts_with($rutaFisica, $uploadBaseDir) || !is_file($rutaFisica)) {
            http_response_code(404);
            echo json_encode([
                'ok'      => false,
                'status'  => 404,
                'message' => 'El archivo físico no se encuentra en el almacenamiento seguro del servidor.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Registrar auditoría de descarga autorizada
        DocuFlowDB::registrarAuditoria(
            (int)$usuario['id'],
            (int)$doc['id'],
            'DOCUMENTO_DESCARGADO',
            [
                'codigo_doc'      => $doc['codigo_doc'],
                'nombre_original' => $doc['nombre_original'],
                'tamanio_bytes'   => filesize($rutaFisica)
            ]
        );

        // Cabeceras binarias y codificación RFC 5987 / RFC 6266 para nombres especiales
        $filenameOrig = basename($doc['nombre_original']);
        $safeAscii = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filenameOrig);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($doc['formato_mime'] ?: 'application/octet-stream'));
        header("Content-Disposition: attachment; filename=\"{$safeAscii}\"; filename*=UTF-8''" . rawurlencode($filenameOrig));
        header('Expires: 0');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($rutaFisica));
        readfile($rutaFisica);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 2. GET: Listado de documentos para el Repositorio
    // ─────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $usuario = Auth::requerirAutenticacion();

        // ADM no tiene acceso a documentos operativos por defecto
        if ($usuario['rol_codigo'] === 'ADM') {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'El rol Administrador administra usuarios y configuraciones técnicas, sin acceso a documentos operativos por defecto.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar permiso de consulta/búsqueda
        if (!Auth::tienePermiso('DOCUMENTOS_BUSCAR') && !Auth::tienePermiso('AUDITORIA_CONSULTAR')) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'No cuenta con permiso para consultar el repositorio documental.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $busqueda  = trim($_GET['q'] ?? '');
        $categoria = trim($_GET['categoria'] ?? '');
        $estado    = trim($_GET['estado'] ?? '');

        $sql = "
            SELECT 
                d.id,
                d.codigo_doc,
                d.nombre_original,
                d.formato_mime,
                d.tamanio_bytes,
                d.estado_proceso,
                d.nivel_confidencialidad,
                d.estado_documental,
                d.created_at,
                d.dependencia_id,
                d.tipo_doc_id,
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

        // Aplicar filtro de alcance centralizado según el rol
        $filtroScope = DocumentoPermisos::obtenerFiltroDocumentosSQL($usuario, 'd');
        $sql .= $filtroScope['sql'];
        $params = $filtroScope['params'];

        // Filtro de estado documental adicional si se especifica
        if (!empty($estado) && $estado !== 'todos') {
            $sql .= " AND d.estado_documental = :estado_filtro";
            $params['estado_filtro'] = $estado;
        }

        // Filtro de búsqueda textual con parámetros PDO únicos para evitar el error HY093
        if (!empty($busqueda)) {
            $sql .= " AND (d.codigo_doc LIKE :q1 OR d.nombre_original LIKE :q2 OR m.asunto LIKE :q3 OR m.resumen_contenido LIKE :q4)";
            $params['q1'] = "%$busqueda%";
            $params['q2'] = "%$busqueda%";
            $params['q3'] = "%$busqueda%";
            $params['q4'] = "%$busqueda%";
        }

        if (!empty($categoria) && $categoria !== 'todos') {
            $sql .= " AND t.nombre = :cat";
            $params['cat'] = $categoria;
        }

        $sql .= " ORDER BY d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll();

        // Registrar auditoría de consulta cuando hay búsqueda explícita
        if (!empty($busqueda)) {
            DocuFlowDB::registrarAuditoria(
                (int)$usuario['id'],
                null,
                'DOCUMENTO_CONSULTADO',
                ['termino' => $busqueda, 'coincidencias' => count($documentos)]
            );
        }

        http_response_code(200);
        echo json_encode([
            'ok'     => true,
            'status' => 200,
            'total'  => count($documentos),
            'data'   => $documentos
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 3. POST: Procesamiento Asistido OCR (Demostrativo)
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'process') {
        $usuario = Auth::requerirPermiso('DOCUMENTOS_REGISTRAR');

        $filename = '';
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $filename = basename($_FILES['archivo']['name']);
        } else {
            $rawInput = file_get_contents('php://input');
            $input = (!empty($rawInput) ? json_decode($rawInput, true) : null) ?? $_POST;
            $filename = $input['filename'] ?? 'documento.pdf';
        }

        // Inferencia demostrativa de tipo de documento
        $tipoDocNombre = 'Oficio';
        if (stripos($filename, 'memo') !== false)    $tipoDocNombre = 'Memorando';
        if (stripos($filename, 'informe') !== false) $tipoDocNombre = 'Informe Técnico';
        if (stripos($filename, 'contrato') !== false)$tipoDocNombre = 'Contrato';

        $stmtTipo = $pdo->prepare("SELECT id, nombre FROM tipos_documento WHERE nombre = :nombre LIMIT 1");
        $stmtTipo->execute(['nombre' => $tipoDocNombre]);
        $tipoRow = $stmtTipo->fetch();
        $tipoDocId = $tipoRow ? (int)$tipoRow['id'] : 1;

        $codigoSugerido = 'DOC-' . date('Ymd') . '-' . str_pad((string)rand(10, 999), 3, '0', STR_PAD_LEFT);

        http_response_code(200);
        echo json_encode([
            'ok'      => true,
            'status'  => 200,
            'message' => 'Extracción asistida OCR completada (Modo Demostrativo para revisión previa)',
            'data'    => [
                'filename' => $filename,
                'metadata' => [
                    'tipo'                  => $tipoDocNombre,
                    'tipo_doc_id'           => $tipoDocId,
                    'codigo'                => $codigoSugerido,
                    'fecha'                 => date('Y-m-d'),
                    'asunto'                => 'Expediente digitalizado mediante DocuFlow AI: ' . $filename,
                    'dependencia'           => $usuario['dependencia_nombre'] ?? 'Archivo Central',
                    'dependencia_id'        => (int)($usuario['dependencia_id'] ?? 1),
                    'nivel_confidencialidad'=> 'interno',
                    'confianza'             => 98.4,
                    'tags'                  => ['Digitalizado', 'OCR Asistido', 'UNS', $tipoDocNombre]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 4. POST: Registro Real de Documento y Carga Física de Archivo
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        // Validación obligatoria de permiso en backend
        $usuario = Auth::requerirPermiso('DOCUMENTOS_REGISTRAR');

        // Verificación de CSRF token
        $tokenRecibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!Auth::validarCsrfToken($tokenRecibido)) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'Token CSRF inválido o sesión expirada. Por favor recargue la página.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validación de archivo físico subido
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'Debe adjuntar un archivo físico válido para el registro (PDF, JPG, PNG o DOCX).'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $archivoTmp = $_FILES['archivo']['tmp_name'];
        $nombreOrig = basename($_FILES['archivo']['name']);
        $tamanio    = (int)$_FILES['archivo']['size'];

        // Límite de tamaño: 20 MB (20 * 1024 * 1024 bytes)
        $maxBytes = 20 * 1024 * 1024;
        if ($tamanio > $maxBytes || $tamanio <= 0) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'El archivo supera el tamaño máximo permitido de 20 MB o se encuentra vacío.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validación de extensión permitida
        $extension = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
        if (!in_array($extension, $extensionesPermitidas, true)) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'Extensión no permitida. Solo se aceptan archivos PDF, JPG, JPEG, PNG y DOCX.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validación de MIME real mediante Fileinfo (finfo)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeDetectado = $finfo->file($archivoTmp);

        $mimesPermitidos = [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-zip'
            ]
        ];

        if (!isset($mimesPermitidos[$extension]) || !in_array($mimeDetectado, $mimesPermitidos[$extension], true)) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'El contenido binario del archivo no coincide con un formato permitido (' . htmlspecialchars($mimeDetectado) . ').'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Cálculo SHA-256 para verificación de integridad y control de duplicados
        $sha256 = hash_file('sha256', $archivoTmp);

        // Comprobar si el esquema dispone de la columna hash_sha256
        $chkCol = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'hash_sha256'")->fetch();
        $tieneColHash = !empty($chkCol);

        if ($tieneColHash) {
            $chkSha = $pdo->prepare("SELECT id, codigo_doc FROM documentos WHERE hash_sha256 = :sha LIMIT 1");
            $chkSha->execute(['sha' => $sha256]);
            if ($dup = $chkSha->fetch()) {
                http_response_code(409);
                echo json_encode([
                    'ok'      => false,
                    'status'  => 409,
                    'message' => "Conflicto: Este archivo ya fue registrado previamente bajo el código '{$dup['codigo_doc']}'."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Extracción y saneamiento de campos del formulario
        $codigoDoc    = trim($_POST['codigo_doc'] ?? '');
        $asunto       = trim($_POST['asunto'] ?? 'Documento procesado');
        $resumen      = trim($_POST['resumen'] ?? $_POST['resumen_contenido'] ?? "Expediente digitalizado {$codigoDoc}");
        $fechaEmision = trim($_POST['fecha_emision'] ?? date('Y-m-d'));
        $remitente    = trim($_POST['remitente'] ?? 'Área de Trámite Documentario');
        $destinatario = trim($_POST['destinatario'] ?? 'Archivo Central');
        $confidencial = trim($_POST['nivel_confidencialidad'] ?? 'interno');

        // Validar dominio permitido de confidencialidad
        $dominiosConfidencial = ['publico', 'interno', 'confidencial', 'restringido'];
        if (!in_array(strtolower($confidencial), $dominiosConfidencial, true)) {
            $confidencial = 'interno';
        }

        // Validar formato de fecha (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
            $fechaEmision = date('Y-m-d');
        }

        // Si no se proporcionó código, generar uno seguro
        if (empty($codigoDoc)) {
            $codigoDoc = 'DOC-' . date('Ymd') . '-' . str_pad((string)rand(10, 999), 3, '0', STR_PAD_LEFT);
        }

        // Comprobar conflicto de código documental duplicado
        $stmtCheck = $pdo->prepare("SELECT id FROM documentos WHERE codigo_doc = :codigo LIMIT 1");
        $stmtCheck->execute(['codigo' => $codigoDoc]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode([
                'ok'      => false,
                'status'  => 409,
                'message' => "Conflicto: Ya existe un documento registrado con el código '{$codigoDoc}'."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Determinar tipo_doc_id
        $tipoDocId = !empty($_POST['tipo_doc_id']) ? (int)$_POST['tipo_doc_id'] : null;
        if (!$tipoDocId && !empty($_POST['tipo_documento'])) {
            $stmtTipo = $pdo->prepare("SELECT id FROM tipos_documento WHERE nombre = :nombre LIMIT 1");
            $stmtTipo->execute(['nombre' => trim($_POST['tipo_documento'])]);
            $tipoDocId = $stmtTipo->fetchColumn() ?: null;
        }
        if (!$tipoDocId) {
            $tipoDocId = (int)$pdo->query("SELECT id FROM tipos_documento ORDER BY id ASC LIMIT 1")->fetchColumn();
        }

        // Dependencia y usuario_id se obtienen estricta e inalterablemente de la sesión autenticada
        $usuarioId     = (int)$usuario['id'];
        $dependenciaId = (int)$usuario['dependencia_id'];

        // Comprobación de permiso centralizado para registrar
        if (!DocumentoPermisos::puedeRegistrar($usuario, $dependenciaId)) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'Acceso denegado. No está autorizado para registrar documentos en esta dependencia.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Generar nombre de almacenamiento interno seguro y único
        $nombreInterno = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $directorioDestino = __DIR__ . '/../../uploads/';
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0755, true);
        }
        $rutaFisicaCompleta = $directorioDestino . $nombreInterno;
        $rutaAlmacenada     = 'uploads/' . $nombreInterno;

        // Mover el archivo subido a su ubicación protegida
        if (!move_uploaded_file($archivoTmp, $rutaFisicaCompleta)) {
            http_response_code(500);
            echo json_encode([
                'ok'      => false,
                'status'  => 500,
                'message' => 'No se pudo guardar físicamente el archivo en el servidor.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Iniciar transacción PDO para registrar documento, metadatos, chunk y auditoría
        $pdo->beginTransaction();

        try {
            // 1. Insertar en tabla documentos
            $sqlDoc = "
                INSERT INTO documentos (
                    usuario_id, dependencia_id, tipo_doc_id, codigo_doc,
                    nombre_original, ruta_almacenada, formato_mime, tamanio_bytes,
                    " . ($tieneColHash ? "hash_sha256, " : "") . "
                    estado_proceso, nivel_confidencialidad, estado_documental
                ) VALUES (
                    :uid, :did, :tid, :codigo,
                    :nombre_orig, :ruta, :mime, :tamanio,
                    " . ($tieneColHash ? ":hash_sha256, " : "") . "
                    'verificado', :confidencial, 'vigente'
                )
            ";
            $stmtDoc = $pdo->prepare($sqlDoc);

            $paramsDoc = [
                'uid'          => $usuarioId,
                'did'          => $dependenciaId,
                'tid'          => $tipoDocId,
                'codigo'       => $codigoDoc,
                'nombre_orig'  => $nombreOrig,
                'ruta'         => $rutaAlmacenada,
                'mime'         => $mimeDetectado,
                'tamanio'      => $tamanio,
                'confidencial' => $confidencial
            ];
            if ($tieneColHash) {
                $paramsDoc['hash_sha256'] = $sha256;
            }

            $stmtDoc->execute($paramsDoc);
            $documentoId = (int)$pdo->lastInsertId();

            // 2. Insertar en tabla metadatos_documento
            $stmtMeta = $pdo->prepare("
                INSERT INTO metadatos_documento (
                    documento_id, numero_extraido, fecha_emision,
                    remitente, destinatario, asunto, resumen_contenido, validado_humano
                ) VALUES (
                    :doc_id, :num, :fecha,
                    :remitente, :destinatario, :asunto, :resumen, 1
                )
            ");

            $stmtMeta->execute([
                'doc_id'       => $documentoId,
                'num'          => $codigoDoc,
                'fecha'        => $fechaEmision,
                'remitente'    => $remitente,
                'destinatario' => $destinatario,
                'asunto'       => $asunto,
                'resumen'      => $resumen
            ]);

            // 3. Insertar chunk en chunks_documento para consultas contextuales
            $stmtChunk = $pdo->prepare("
                INSERT INTO chunks_documento (documento_id, numero_chunk, contenido_texto)
                VALUES (:doc_id, 1, :texto)
            ");
            $stmtChunk->execute([
                'doc_id' => $documentoId,
                'texto'  => "Expediente {$codigoDoc}: {$asunto}. {$resumen}. Archivo: {$nombreOrig}."
            ]);

            // 4. Registrar evento real en auditoria_acciones
            DocuFlowDB::registrarAuditoria(
                $usuarioId,
                $documentoId,
                'DOCUMENTO_REGISTRADO',
                [
                    'codigo_doc'      => $codigoDoc,
                    'nombre_original' => $nombreOrig,
                    'formato_mime'    => $mimeDetectado,
                    'tamanio_bytes'   => $tamanio,
                    'sha256'          => $sha256,
                    'ruta_almacenada' => $rutaAlmacenada
                ]
            );

            // Confirmar transacción atómica
            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'ok'      => true,
                'status'  => 201,
                'message' => 'Documento y archivo físico guardados exitosamente en MySQL.',
                'data'    => [
                    'id'              => $documentoId,
                    'codigo_doc'      => $codigoDoc,
                    'nombre_original' => $nombreOrig,
                    'tamanio_bytes'   => $tamanio,
                    'sha256'          => $sha256
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $txException) {
            $pdo->rollBack();

            // Eliminar archivo recién subido en caso de falla de transacción (evita archivos huérfanos)
            if (file_exists($rutaFisicaCompleta)) {
                @unlink($rutaFisicaCompleta);
            }

            throw $txException;
        }
    }

    // Ruta no reconocida
    http_response_code(404);
    echo json_encode([
        'ok'      => false,
        'status'  => 404,
        'message' => 'Acción no encontrada en el módulo de documentos.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en php/api/documentos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'status'  => 500,
        'message' => 'Error interno al procesar la solicitud en el repositorio documental.'
    ], JSON_UNESCAPED_UNICODE);
}
