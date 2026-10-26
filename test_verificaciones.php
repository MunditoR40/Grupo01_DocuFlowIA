<?php
/**
 * Suite de Verificación Automatizada de Estándares - DocuFlow AI Grupo 01
 * Ejecuta pruebas reales end-to-end contra Apache (puerto 80) y MySQL (puerto 3307)
 */

$baseUrl = 'http://127.0.0.1/Grupo01_DocuFlowIA/php/api';
$cookieJar = __DIR__ . '/test_cookies.txt';
if (file_exists($cookieJar)) @unlink($cookieJar);

function curlRequest($url, $method = 'GET', $data = null, $isMultipart = false, $headers = []) {
    global $cookieJar;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
            $headers[] = 'Content-Type: application/json';
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'json' => json_decode($response, true),
        'error' => $curlErr
    ];
}

echo "===============================================================\n";
echo " EJECUCIÓN DE PRUEBAS OBLIGATORIAS DOCUFLOW AI (GRUPO 01)\n";
echo "===============================================================\n\n";

$pruebasPasadas = 0;
$pruebasTotales = 14;

// 1. Conexión PDO a MySQL 3307
echo "[PRUEBA 01] Verificando conexión PDO a MySQL en puerto 3307... ";
try {
    require_once __DIR__ . '/php/config.php';
    require_once __DIR__ . '/php/conexion.php';
    $pdo = Database::conectar();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "PASÓ (Versión: $version)\n";
    $pruebasPasadas++;
} catch (Exception $e) {
    echo "FALLÓ: " . $e->getMessage() . "\n";
}

// 2. Login con usuario OPE registrado
echo "[PRUEBA 02] Inicio de sesión con 'operador.demo@docuflow.test'... ";
$resLogin = curlRequest("$baseUrl/auth.php?action=login", 'POST', [
    'email' => 'operador.demo@docuflow.test',
    'password' => 'DocuFlowDemo2026!'
]);
if ($resLogin['code'] === 200 && ($resLogin['json']['ok'] ?? false) === true) {
    $opeUser = $resLogin['json']['data']['usuario'];
    $csrfToken = $resLogin['json']['data']['csrf_token'] ?? '';
    echo "PASÓ (HTTP 200, Rol: {$opeUser['rol_codigo']}, Usuario: {$opeUser['nombre']})\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ (HTTP {$resLogin['code']}): " . $resLogin['body'] . "\n";
}

// 3. Rechazo de contraseña incorrecta
echo "[PRUEBA 03] Verificando rechazo de contraseña incorrecta... ";
$resBadPass = curlRequest("$baseUrl/auth.php?action=login", 'POST', [
    'email' => 'operador.demo@docuflow.test',
    'password' => 'PasswordTotalmenteErronea!'
]);
if ($resBadPass['code'] === 401 && ($resBadPass['json']['ok'] ?? true) === false) {
    echo "PASÓ (HTTP 401: {$resBadPass['json']['message']})\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Esperaba HTTP 401, recibido {$resBadPass['code']}\n";
}

// 4. Rechazo de usuario inactivo
echo "[PRUEBA 04] Verificando bloqueo de usuario inactivo (activo = 0)... ";
try {
    $pdo->exec("INSERT INTO usuarios (rol_id, dependencia_id, nombre, email, password, activo) 
                VALUES (3, 1, 'Usuario Inactivo Test', 'inactivo.test@docuflow.test', '" . password_hash('TestPass123!', PASSWORD_BCRYPT) . "', 0)");
    
    $resInactivo = curlRequest("$baseUrl/auth.php?action=login", 'POST', [
        'email' => 'inactivo.test@docuflow.test',
        'password' => 'TestPass123!'
    ]);

    // Limpiar usuario temporal de prueba
    $pdo->exec("DELETE FROM usuarios WHERE email = 'inactivo.test@docuflow.test'");

    if ($resInactivo['code'] === 403 && ($resInactivo['json']['ok'] ?? true) === false) {
        echo "PASÓ (HTTP 403: {$resInactivo['json']['message']})\n";
        $pruebasPasadas++;
    } else {
        echo "FALLÓ: Esperaba HTTP 403, recibido {$resInactivo['code']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 5. Usuario OPE autorizado registra un documento real con subida de archivo
echo "[PRUEBA 05] Registro de documento real con FormData y subida de archivo... ";
// Crear archivo PDF temporal para la prueba
$tempPdf = __DIR__ . '/test_sample_doc.pdf';
$pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 300 144]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000018 00000 n\n0000000077 00000 n\n0000000178 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n268\n%%EOF";
file_put_contents($tempPdf, $pdfContent);

$codigoPrueba = 'OFI-' . date('Ymd-His') . '-UNS';
$postData = [
    'archivo' => new CURLFile($tempPdf, 'application/pdf', 'oficio_inspeccion_rectorado.pdf'),
    'codigo_doc' => $codigoPrueba,
    'tipo_doc_id' => '1',
    'fecha_emision' => date('Y-m-d'),
    'asunto' => 'Inspección técnica de infraestructura distribuida en laboratorios',
    'remitente' => 'Área de Infraestructura TI',
    'destinatario' => 'Decanato de Ingeniería',
    'nivel_confidencialidad' => 'interno',
    'csrf_token' => $csrfToken
];

$resUpload = curlRequest("$baseUrl/documentos.php", 'POST', $postData, true, [
    'X-CSRF-Token: ' . $csrfToken
]);

$nuevoDocId = null;
$rutaGuardada = null;
if ($resUpload['code'] === 201 && ($resUpload['json']['ok'] ?? false) === true) {
    $nuevoDocId = $resUpload['json']['data']['id'];
    $rutaGuardada = $resUpload['json']['data']['ruta_almacenada'];
    echo "PASÓ (HTTP 201, Doc ID: $nuevoDocId, Código: $codigoPrueba)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ (HTTP {$resUpload['code']}): " . $resUpload['body'] . "\n";
}

// 6. Comprobar que el archivo se almacena físicamente
echo "[PRUEBA 06] Verificando existencia física del archivo subido en uploads/... ";
$archivoFisico = __DIR__ . '/' . $rutaGuardada;
if (!empty($rutaGuardada) && file_exists($archivoFisico) && filesize($archivoFisico) > 0) {
    echo "PASÓ (Existe en: $rutaGuardada, Tamaño: " . filesize($archivoFisico) . " bytes)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: El archivo no existe físicamente en $archivoFisico\n";
}

// 7. Comprobar que el documento aparece en MySQL
echo "[PRUEBA 07] Verificando persistencia del documento en tabla 'documentos'... ";
$stmtCheckDoc = $pdo->prepare("SELECT * FROM documentos WHERE id = :id");
$stmtCheckDoc->execute(['id' => $nuevoDocId]);
$docRow = $stmtCheckDoc->fetch(PDO::FETCH_ASSOC);
if ($docRow && $docRow['codigo_doc'] === $codigoPrueba) {
    echo "PASÓ (ID: {$docRow['id']}, Código: {$docRow['codigo_doc']}, MIME: {$docRow['formato_mime']})\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Documento no encontrado en tabla documentos\n";
}

// 8. Comprobar que sus metadatos y la auditoría se guardaron
echo "[PRUEBA 08] Verificando persistencia de metadatos y auditoría... ";
$stmtMeta = $pdo->prepare("SELECT * FROM metadatos_documento WHERE documento_id = :id");
$stmtMeta->execute(['id' => $nuevoDocId]);
$metaRow = $stmtMeta->fetch(PDO::FETCH_ASSOC);

$stmtAudit = $pdo->prepare("SELECT * FROM auditoria_acciones WHERE documento_id = :id AND accion = 'DOCUMENTO_REGISTRADO'");
$stmtAudit->execute(['id' => $nuevoDocId]);
$auditRow = $stmtAudit->fetch(PDO::FETCH_ASSOC);

if ($metaRow && $auditRow) {
    echo "PASÓ (Asunto: '{$metaRow['asunto']}', Acción Audit: {$auditRow['accion']})\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Metadatos o auditoría no encontrados\n";
}

// 9. Comprobar que el documento aparece en el repositorio (GET /api/documents)
echo "[PRUEBA 09] Consulta del documento en el Repositorio (GET api/documentos.php)... ";
$resRepo = curlRequest("$baseUrl/documentos.php", 'GET');
$encontradoEnRepo = false;
if ($resRepo['code'] === 200 && is_array($resRepo['json']['data'] ?? null)) {
    foreach ($resRepo['json']['data'] as $d) {
        if ($d['id'] == $nuevoDocId) {
            $encontradoEnRepo = true;
            break;
        }
    }
}
if ($encontradoEnRepo) {
    echo "PASÓ (Documento listado correctamente con JOINs)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Documento no apareció en el listado del repositorio\n";
}

// 10. Comprobar persistencia tras recargar (segunda consulta independiente)
echo "[PRUEBA 10] Verificando persistencia tras recarga (consulta subsecuente)... ";
$resReload = curlRequest("$baseUrl/documentos.php", 'GET');
$persiste = false;
if ($resReload['code'] === 200 && is_array($resReload['json']['data'] ?? null)) {
    foreach ($resReload['json']['data'] as $d) {
        if ($d['id'] == $nuevoDocId) {
            $persiste = true;
            break;
        }
    }
}
if ($persiste) {
    echo "PASÓ (Persistencia confirmada en MySQL tras recargar)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Documento no persistió en la recarga\n";
}

// 11. Usuario sin permiso recibe HTTP 403 (Login como ADM e intentar registrar doc)
echo "[PRUEBA 11] Verificando que usuario sin permiso (ADM) recibe HTTP 403 al registrar... ";
// Iniciar sesión como ADM
$resLoginAdm = curlRequest("$baseUrl/auth.php?action=login", 'POST', [
    'email' => 'admin@docuflow.ai',
    'password' => 'DocuFlow2026'
]);
$csrfAdm = $resLoginAdm['json']['data']['csrf_token'] ?? '';

$resAdmUpload = curlRequest("$baseUrl/documentos.php", 'POST', [
    'archivo' => new CURLFile($tempPdf, 'application/pdf', 'intento_admin.pdf'),
    'codigo_doc' => 'INTENTO-ADM-001',
    'tipo_doc_id' => '1',
    'csrf_token' => $csrfAdm
], true, [
    'X-CSRF-Token: ' . $csrfAdm
]);

if ($resAdmUpload['code'] === 403 && ($resAdmUpload['json']['ok'] ?? true) === false) {
    echo "PASÓ (HTTP 403: {$resAdmUpload['json']['message']})\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Esperaba HTTP 403, recibido HTTP {$resAdmUpload['code']}\n";
}

// 12. Error de conexión o sin sesión no produce éxito simulado
echo "[PRUEBA 12] Petición sin autenticación no produce éxito simulado... ";
if (file_exists($cookieJar)) @unlink($cookieJar); // Borrar cookies para simular sin sesión
$resNoAuth = curlRequest("$baseUrl/documentos.php", 'POST', [
    'codigo_doc' => 'DOC-FAKE'
]);
if ($resNoAuth['code'] === 401 && ($resNoAuth['json']['ok'] ?? true) === false) {
    echo "PASÓ (HTTP 401 No autorizado, sin simulación de éxito ficticio)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Esperaba HTTP 401, recibido HTTP {$resNoAuth['code']}\n";
}

// 13. Las consultas SQL no provocan errores HY093 (Búsqueda con :q1, :q2, :q3, :q4)
echo "[PRUEBA 13] Búsqueda documental con parámetro LIKE (prevención error HY093)... ";
// Loguear de nuevo como operador para tener sesión
curlRequest("$baseUrl/auth.php?action=login", 'POST', [
    'email' => 'operador.demo@docuflow.test',
    'password' => 'DocuFlowDemo2026!'
]);
$resSearch = curlRequest("$baseUrl/documentos.php?q=infraestructura", 'GET');
if ($resSearch['code'] === 200 && ($resSearch['json']['ok'] ?? false) === true) {
    echo "PASÓ (HTTP 200, Consulta ejecutada sin error HY093, encontrados: " . count($resSearch['json']['data']) . ")\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ (HTTP {$resSearch['code']}): " . $resSearch['body'] . "\n";
}

// 14. Comprobar que los registros originales siguen intactos
echo "[PRUEBA 14] Comprobando conservación de los registros preexistentes... ";
$stmtOrigUser = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id = 1 AND email = 'operador.demo@docuflow.test'");
$origUserOk = ($stmtOrigUser->fetchColumn() == 1);

$stmtOrigDoc = $pdo->query("SELECT COUNT(*) FROM documentos WHERE id = 1 AND codigo_doc = 'DEMO-OFI-001'");
$origDocOk = ($stmtOrigDoc->fetchColumn() == 1);

$stmtRoles = $pdo->query("SELECT COUNT(*) FROM roles");
$rolesCount = $stmtRoles->fetchColumn();

if ($origUserOk && $origDocOk && $rolesCount == 5) {
    echo "PASÓ (Usuario 1, Documento 1 y los 5 roles se mantienen intactos)\n";
    $pruebasPasadas++;
} else {
    echo "FALLÓ: Se alteraron o eliminaron registros preexistentes\n";
}

// Limpiar archivo temporal de muestra
if (file_exists($tempPdf)) @unlink($tempPdf);
if (file_exists($cookieJar)) @unlink($cookieJar);

echo "\n===============================================================\n";
echo " RESULTADO FINAL: $pruebasPasadas DE $pruebasTotales PRUEBAS SUPERADAS CON ÉXITO\n";
echo "===============================================================\n";
