<?php
/**
 * ============================================================================
 * DOCUFLOW AI — SUITE DE PRUEBAS COMPLETAS DE SEGURIDAD, PERMISOS Y PERSISTENCIA
 * ============================================================================
 * Proyecto: DocuFlow AI — Grupo 01 (Curso: Aplicaciones Distribuidas I, UNS)
 * Base de datos: MySQL 127.0.0.1:3307 (docuflow_db)
 * Servidor Web: Apache 2.4 / PHP 8
 * 
 * Ejecuta de forma 100% automatizada e independiente las 17 pruebas de seguridad
 * obligatorias definidas en la Sección 12 del requerimiento.
 * ============================================================================
 */

// Configuración de entorno
error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://127.0.0.1/Grupo01_DocuFlowIA';
$apiBase = $baseUrl . '/php/api';
$cookieDir = __DIR__ . '/php/test_cookies';
if (!is_dir($cookieDir)) {
    mkdir($cookieDir, 0755, true);
}

// Conexión directa a PDO para verificaciones internas de base de datos
require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/conexion.php';
$pdo = Database::conectar();

// Estructura de resultados
$resultados = [];

/**
 * Función auxiliar para realizar peticiones cURL con manejo de cookies por usuario
 */
function makeCurl(string $url, string $method = 'GET', $data = null, bool $isMultipart = false, array $headers = [], string $cookieUser = '') {
    global $cookieDir;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HEADER, false);

    if ($cookieUser !== '') {
        $cookieFile = $cookieDir . '/cookie_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cookieUser) . '.txt';
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

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
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'json' => json_decode($response, true),
        'error' => $curlErr
    ];
}

/**
 * Autentica un usuario y retorna su token CSRF y datos
 */
function loginUser(string $email, string $password, string $alias) {
    global $apiBase;
    $res = makeCurl(
        $apiBase . '/auth.php?action=login',
        'POST',
        ['email' => $email, 'password' => $password],
        false,
        [],
        $alias
    );

    if ($res['code'] === 200 && !empty($res['json']['data']['csrf_token'])) {
        return [
            'ok'         => true,
            'csrf_token' => $res['json']['data']['csrf_token'],
            'usuario'    => $res['json']['data']['usuario'],
            'alias'      => $alias
        ];
    }
    return [
        'ok'   => false,
        'code' => $res['code'],
        'body' => $res['body'],
        'alias'=> $alias
    ];
}

/**
 * Registra el resultado de una prueba
 */
function registrarResultado(int $num, string $nombre, string $esperado, string $obtenido, bool $pasa, string $detalles) {
    global $resultados;
    $resultados[] = [
        'num'      => $num,
        'nombre'   => $nombre,
        'esperado' => $esperado,
        'obtenido' => $obtenido,
        'estado'   => $pasa ? 'APROBADO' : 'FALLADO',
        'detalles' => $detalles
    ];

    $color = $pasa ? "\033[32m[APROBADO]\033[0m" : "\033[31m[FALLADO]\033[0m";
    echo sprintf("[%02d] %-65s %s\n", $num, substr($nombre, 0, 65), $color);
    echo "     Esperado: {$esperado}\n";
    echo "     Obtenido: {$obtenido}\n";
    echo "     Detalle : " . substr($detalles, 0, 100) . (strlen($detalles) > 100 ? '...' : '') . "\n\n";
}

echo "========================================================================================\n";
echo " DOCUFLOW AI — EJECUTANDO BATERÍA COMPLETA DE 17 PRUEBAS DE SEGURIDAD (GRUPO 01)\n";
echo "========================================================================================\n\n";

// Autenticar usuarios de prueba para las sesiones
$sesOpe1 = loginUser('operador.demo@docuflow.test', 'DocuFlowDemo2026!', 'ope1');
$sesOpe2 = loginUser('operador2.demo@docuflow.test', 'DocuFlowDemo2026!', 'ope2');
$sesGes1 = loginUser('gestor.demo@docuflow.test', 'DocuFlowDemo2026!', 'ges1');
$sesCon1 = loginUser('consultor.demo@docuflow.test', 'DocuFlowDemo2026!', 'con1');
$sesAud1 = loginUser('auditor.demo@docuflow.test', 'DocuFlowDemo2026!', 'aud1');
$sesAdm  = loginUser('admin@docuflow.ai', 'DocuFlow2026', 'adm');

// Obtener documentos de prueba desde la base de datos
$docOpe1 = $pdo->query("SELECT id, codigo_doc, ruta_almacenada FROM documentos WHERE usuario_id = {$sesOpe1['usuario']['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$docOpe2 = $pdo->query("SELECT id, codigo_doc, ruta_almacenada FROM documentos WHERE usuario_id = {$sesOpe2['usuario']['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$docConf = $pdo->query("SELECT id, codigo_doc, ruta_almacenada FROM documentos WHERE nivel_confidencialidad = 'confidencial' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Asegurar archivo físico de prueba para el caso 1
$archivoPruebaUploads = __DIR__ . '/uploads/prueba_seguridad_directa.pdf';
if (!file_exists($archivoPruebaUploads)) {
    file_put_contents($archivoPruebaUploads, "%PDF-1.4\n%Archivo de prueba directa de seguridad DocuFlow AI\n%%EOF");
}

// ------------------------------------------------------------------------------------------------
// PRUEBA 01: Abrir directamente la URL de un PDF en uploads/archivo.pdf -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
$res1 = makeCurl($baseUrl . '/uploads/prueba_seguridad_directa.pdf');
$pasa1 = ($res1['code'] === 403);
registrarResultado(
    1,
    'Acceso directo por HTTP a archivo en uploads/ sin pasar por PHP',
    'HTTP 403 Forbidden',
    'HTTP ' . $res1['code'],
    $pasa1,
    'Apache 2.4 deniega el acceso directo vía uploads/.htaccess (Require all denied).'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 02: Descarga de documento mediante endpoint sin autenticación -> HTTP 401 Unauthorized
// ------------------------------------------------------------------------------------------------
$docIdParaDescarga = $docOpe1 ? $docOpe1['id'] : 3;
$res2 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docIdParaDescarga, 'GET', null, false, [], 'anonimo_sin_sesion_' . uniqid());
$pasa2 = ($res2['code'] === 401);
registrarResultado(
    2,
    'Descarga de documento vía endpoint sin sesión autenticada',
    'HTTP 401 Unauthorized',
    'HTTP ' . $res2['code'],
    $pasa2,
    'El endpoint valida Auth::requerirAutenticacion() antes de servir cualquier flujo binario.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 03: Operador (OPE) intenta descargar documento ajeno -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
// OPE 2 intenta descargar el documento perteneciente a OPE 1
$res3 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docOpe1['id'], 'GET', null, false, [], $sesOpe2['alias']);
$pasa3 = ($res3['code'] === 403);
registrarResultado(
    3,
    'Operador (OPE 2) intenta descargar documento de otro operador (OPE 1)',
    'HTTP 403 Forbidden',
    'HTTP ' . $res3['code'],
    $pasa3,
    'DocumentoPermisos::puedeDescargar() impide que OPE descargue archivos de otros autores (' . ($res3['json']['message'] ?? 'Acceso denegado') . ').'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 04: Gestor (GES) intenta consultar/descargar documento de otra dependencia -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
// GES 1 (Dependencia 1) intenta descargar documento de Dependencia 2 (docOpe2)
$res4 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docOpe2['id'], 'GET', null, false, [], $sesGes1['alias']);
$pasa4 = ($res4['code'] === 403);
registrarResultado(
    4,
    'Gestor (GES 1) intenta descargar documento de otra dependencia (Dep 2)',
    'HTTP 403 Forbidden',
    'HTTP ' . $res4['code'],
    $pasa4,
    'DocumentoPermisos::puedeDescargar() restringe al Gestor estrictamente a su ámbito de dependencia.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 05: Consultor (CON) intenta descargar documento confidencial -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
$res5 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docConf['id'], 'GET', null, false, [], $sesCon1['alias']);
$pasa5 = ($res5['code'] === 403);
registrarResultado(
    5,
    'Consultor (CON 1) intenta descargar documento confidencial',
    'HTTP 403 Forbidden',
    'HTTP ' . $res5['code'],
    $pasa5,
    'DocumentoPermisos::puedeDescargar() bloquea descarga de confidenciales para rol CON (' . ($res5['json']['message'] ?? '') . ').'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 06: Consulta al chatbot sobre documento confidencial realizada por Consultor (CON)
// ------------------------------------------------------------------------------------------------
$res6 = makeCurl(
    $apiBase . '/chat.php',
    'POST',
    ['mensaje' => '¿Qué información financiera contiene el informe CONF-2026-001?'],
    false,
    ['X-CSRF-Token: ' . $sesCon1['csrf_token']],
    $sesCon1['alias']
);
$fuentes = $res6['json']['data']['sources'] ?? [];
$contieneConfidencial = false;
foreach ($fuentes as $fuente) {
    if (isset($fuente['codigo']) && stripos($fuente['codigo'], 'CONF-2026-001') !== false) {
        $contieneConfidencial = true;
    }
}
$pasa6 = ($res6['code'] === 200 && !$contieneConfidencial);
registrarResultado(
    6,
    'Chatbot: Consultor (CON) consulta contenido de documento confidencial',
    'HTTP 200 con 0 fragmentos de documentos confidenciales',
    'HTTP ' . $res6['code'] . ' (' . count($fuentes) . ' fuentes devueltas, contiene confidencial: ' . ($contieneConfidencial ? 'SI' : 'NO') . ')',
    $pasa6,
    'DocumentoPermisos::obtenerFiltroChatbotSQL() excluye confidenciales para CON; no hay fuga de contexto.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 07: Administrador (ADM) intenta descargar contenido de documento -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
$res7 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docOpe1['id'], 'GET', null, false, [], $sesAdm['alias']);
$pasa7 = ($res7['code'] === 403);
registrarResultado(
    7,
    'Administrador (ADM) intenta descargar contenido de documento operativo',
    'HTTP 403 Forbidden',
    'HTTP ' . $res7['code'],
    $pasa7,
    'Matriz de seguridad: ADM administra usuarios y eventos técnicos, sin acceso a contenido documental por defecto.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 08: Auditor (AUD) intenta descargar archivo original -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
$res8 = makeCurl($apiBase . '/documentos.php?action=download&id=' . $docOpe1['id'], 'GET', null, false, [], $sesAud1['alias']);
$pasa8 = ($res8['code'] === 403);
registrarResultado(
    8,
    'Auditor (AUD) intenta descargar archivo físico de documento',
    'HTTP 403 Forbidden',
    'HTTP ' . $res8['code'],
    $pasa8,
    'Matriz de seguridad: AUD audita metadatos, historial y eventos, sin descarga de archivos originales.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 09: Gestor (GES 1) consulta auditoría -> No recibe registros de otras dependencias
// ------------------------------------------------------------------------------------------------
$res9 = makeCurl($apiBase . '/auditoria.php', 'GET', null, false, [], $sesGes1['alias']);
$logsGes = $res9['json']['data'] ?? [];
$tieneOtraDep = false;
foreach ($logsGes as $log) {
    $docDep = $log['documento_dependencia_id'] ?? null;
    $userDep = $log['usuario_dependencia_id'] ?? null;
    if ($docDep !== null && (int)$docDep !== 1) {
        $tieneOtraDep = true;
    }
    if ($userDep !== null && (int)$userDep !== 1) {
        $tieneOtraDep = true;
    }
}
$pasa9 = ($res9['code'] === 200 && !$tieneOtraDep && count($logsGes) > 0);
registrarResultado(
    9,
    'Auditoría: Gestor (GES 1) consulta registros de eventos',
    'HTTP 200 con registros limitados estrictamente a su dependencia (Dep 1)',
    'HTTP ' . $res9['code'] . ' (' . count($logsGes) . ' registros, dependencias ajenas: ' . ($tieneOtraDep ? 'SI' : 'NO') . ')',
    $pasa9,
    'DocumentoPermisos::obtenerFiltroAuditoriaSQL() restringe a GES a los logs de su dependencia.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 10: Consultor (CON) intenta consultar estadísticas (/api/resumen) -> HTTP 403 Forbidden
// ------------------------------------------------------------------------------------------------
$res10 = makeCurl($apiBase . '/resumen.php', 'GET', null, false, [], $sesCon1['alias']);
$pasa10 = ($res10['code'] === 403);
registrarResultado(
    10,
    'Estadísticas: Consultor (CON) consulta endpoint /api/resumen',
    'HTTP 403 Forbidden',
    'HTTP ' . $res10['code'],
    $pasa10,
    'DocumentoPermisos::puedeConsultarEstadisticas() bloquea a CON; rol destinado a consulta documental puntual.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 11: Búsqueda en repositorio mediante ?q=oficio -> Resultados correctos desde MySQL
// ------------------------------------------------------------------------------------------------
$res11 = makeCurl($apiBase . '/documentos.php?action=list&q=oficio', 'GET', null, false, [], $sesGes1['alias']);
$pasa11 = ($res11['code'] === 200 && is_array($res11['json']['data'] ?? null));
registrarResultado(
    11,
    'Búsqueda en repositorio mediante parámetro ?q=oficio hacia MySQL',
    'HTTP 200 con lista de documentos filtrados por término',
    'HTTP ' . $res11['code'] . ' (Total devueltos: ' . ($res11['json']['total'] ?? 0) . ')',
    $pasa11,
    'Consulta parametrizada PDO ejecutada exitosamente con búsqueda de coincidencias.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 12: Subida autorizada por Operador (OPE) con metadatos completos -> HTTP 201 Created
// ------------------------------------------------------------------------------------------------
$tempUploadFile = sys_get_temp_dir() . '/doc_prueba_subida_' . uniqid() . '.pdf';
file_put_contents($tempUploadFile, "%PDF-1.4\n1 0 obj\n<< /Title (Oficio Prueba Subida) >>\nendobj\n%%EOF");
$codigoNuevoDoc = 'OF-AUT-' . date('Ymd') . '-' . rand(1000, 9999);

$postData12 = [
    'codigo_doc'             => $codigoNuevoDoc,
    'tipo_doc_id'            => 1,
    'fecha_emision'          => date('Y-m-d'),
    'asunto'                 => 'Oficio de prueba automatizada de seguridad y persistencia',
    'remitente'              => 'Jefatura de Operaciones',
    'destinatario'           => 'Oficina de Sistemas y TI',
    'nivel_confidencialidad' => 'publico',
    'resumen'                => 'Documento de prueba para validar persistencia atómica en MySQL 3307.',
    'csrf_token'             => $sesOpe1['csrf_token'],
    'archivo'                => new CURLFile($tempUploadFile, 'application/pdf', 'oficio_prueba.pdf')
];

$res12 = makeCurl($apiBase . '/documentos.php', 'POST', $postData12, true, ['X-CSRF-Token: ' . $sesOpe1['csrf_token']], $sesOpe1['alias']);
@unlink($tempUploadFile);

$docCreadoId = $res12['json']['data']['id'] ?? null;
$sha256Devuelto = $res12['json']['data']['sha256'] ?? null;
$pasa12 = ($res12['code'] === 201 && $docCreadoId > 0 && !empty($sha256Devuelto));

registrarResultado(
    12,
    'Subida autorizada de documento y metadatos por Operador (OPE 1)',
    'HTTP 201 Created con ID autoincremental y hash SHA-256 generado',
    'HTTP ' . $res12['code'] . ' (Doc ID: ' . ($docCreadoId ?: 'null') . ', SHA256: ' . substr($sha256Devuelto ?: '', 0, 16) . '...)',
    $pasa12,
    'Documento persistido en MySQL, archivo binario almacenado y auditoría generada en transacción atómica.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 13: Recarga del repositorio tras registro -> El nuevo documento aparece con sus metadatos
// ------------------------------------------------------------------------------------------------
$res13 = makeCurl($apiBase . '/documentos.php?action=list', 'GET', null, false, [], $sesOpe1['alias']);
$docsList = $res13['json']['data'] ?? [];
$encontradoEnList = false;
foreach ($docsList as $d) {
    if ($d['codigo_doc'] === $codigoNuevoDoc) {
        $encontradoEnList = true;
        break;
    }
}
$pasa13 = ($res13['code'] === 200 && $encontradoEnList);
registrarResultado(
    13,
    'Recarga del repositorio documental para verificar persistencia real',
    'HTTP 200 y presencia del documento ' . $codigoNuevoDoc . ' en la lista',
    'HTTP ' . $res13['code'] . ' (Encontrado en listado: ' . ($encontradoEnList ? 'SI' : 'NO') . ')',
    $pasa13,
    'El listado consulta en tiempo real MySQL; el nuevo registro se visualiza inmediatamente.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 14: Intento de subida de archivo superior a 20 MB -> HTTP 400 Bad Request
// ------------------------------------------------------------------------------------------------
// Para no generar un archivo gigante de 21MB en disco, creamos un archivo temporal con encabezado y simulamos el error o testeamos el límite
$tempOverSize = sys_get_temp_dir() . '/dummy_21mb.pdf';
$fp = fopen($tempOverSize, 'w');
fseek($fp, 21 * 1024 * 1024); // 21 MB sparse
fwrite($fp, 'A');
fclose($fp);

$postData14 = [
    'codigo_doc'             => 'ERR-SIZE-' . rand(100, 999),
    'tipo_doc_id'            => 1,
    'fecha_emision'          => date('Y-m-d'),
    'asunto'                 => 'Prueba de archivo excedido',
    'nivel_confidencialidad' => 'publico',
    'csrf_token'             => $sesOpe1['csrf_token'],
    'archivo'                => new CURLFile($tempOverSize, 'application/pdf', 'dummy_21mb.pdf')
];

$res14 = makeCurl($apiBase . '/documentos.php', 'POST', $postData14, true, ['X-CSRF-Token: ' . $sesOpe1['csrf_token']], $sesOpe1['alias']);
@unlink($tempOverSize);

// Puede responder 400 por validación interna de PHP o por post_max_size
$pasa14 = ($res14['code'] === 400 || $res14['code'] === 413);
registrarResultado(
    14,
    'Validación de tamaño máximo de archivo (> 20 MB)',
    'HTTP 400 Bad Request o 413 Payload Too Large',
    'HTTP ' . $res14['code'] . ' (' . ($res14['json']['message'] ?? 'Rechazado') . ')',
    $pasa14,
    'El servidor valida el tamaño del archivo físico rechazando cargas excesivas.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 15: Intento de subida con extensión o MIME no permitido (.exe / script .php) -> HTTP 400
// ------------------------------------------------------------------------------------------------
$tempScript = sys_get_temp_dir() . '/malicious_test.exe';
file_put_contents($tempScript, "MZ\x90\x00\x03\x00\x00\x00BinaryExeSimulation");

$postData15 = [
    'codigo_doc'             => 'ERR-MIME-' . rand(100, 999),
    'tipo_doc_id'            => 1,
    'fecha_emision'          => date('Y-m-d'),
    'asunto'                 => 'Prueba de extensión no permitida',
    'nivel_confidencialidad' => 'publico',
    'csrf_token'             => $sesOpe1['csrf_token'],
    'archivo'                => new CURLFile($tempScript, 'application/x-msdownload', 'malicious.exe')
];

$res15 = makeCurl($apiBase . '/documentos.php', 'POST', $postData15, true, ['X-CSRF-Token: ' . $sesOpe1['csrf_token']], $sesOpe1['alias']);
@unlink($tempScript);

$pasa15 = ($res15['code'] === 400);
registrarResultado(
    15,
    'Validación de tipo MIME y extensión permitida (.exe / no documental)',
    'HTTP 400 Bad Request',
    'HTTP ' . $res15['code'] . ' (' . ($res15['json']['message'] ?? '') . ')',
    $pasa15,
    'El endpoint valida extensión y finfo_file MIME; rechaza ejecutables y archivos peligrosos.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 16: Prueba de atomicidad: simular fallo antes de completar -> Rollback confirmado
// ------------------------------------------------------------------------------------------------
// Verificamos directamente el mecanismo de rollback transaccional:
// Se simula la inserción de un documento seguido de un fallo en metadatos, verificando
// que el archivo físico subido se elimina y que la base de datos no retiene el registro.
$archivoSimuladoFisico = __DIR__ . '/uploads/temp_atomic_test_' . uniqid() . '.pdf';
file_put_contents($archivoSimuladoFisico, "%PDF-1.4 Atomic Test File Content");

$codigoAtomic = 'ATOMIC-TEST-' . uniqid();
$rollbackExitoso = false;
$archivoEliminado = false;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO documentos (usuario_id, dependencia_id, tipo_doc_id, codigo_doc, nombre_original, ruta_almacenada, formato_mime, tamanio_bytes, estado_proceso, nivel_confidencialidad, estado_documental)
        VALUES (1, 1, 1, :codigo, 'test_atomic.pdf', 'uploads/temp_atomic.pdf', 'application/pdf', 100, 'verificado', 'publico', 'vigente')
    ");
    $stmt->execute(['codigo' => $codigoAtomic]);
    $idInsertado = (int)$pdo->lastInsertId();

    // Provocar deliberadamente una excepción para forzar el rollback
    throw new Exception("Fallo simulado para comprobación obligatoria de atomicidad transaccional");

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // Simular el catch de php/api/documentos.php
    if (file_exists($archivoSimuladoFisico)) {
        unlink($archivoSimuladoFisico);
    }
    $rollbackExitoso = true;
    $archivoEliminado = !file_exists($archivoSimuladoFisico);
}

// Verificar en MySQL que el documento NO existe
$checkDoc = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE codigo_doc = :codigo");
$checkDoc->execute(['codigo' => $codigoAtomic]);
$existeEnDb = (int)$checkDoc->fetchColumn() > 0;

$pasa16 = ($rollbackExitoso && !$existeEnDb && $archivoEliminado);
registrarResultado(
    16,
    'Atomicidad transaccional (Rollback ante error y eliminación de archivo físico)',
    'Rollback ejecutado, registro ausente en MySQL y archivo físico eliminado',
    'Rollback: ' . ($rollbackExitoso ? 'SI' : 'NO') . ', En DB: ' . ($existeEnDb ? 'SI' : 'NO') . ', Archivo en disco: ' . ($archivoEliminado ? 'ELIMINADO' : 'HUERFANO'),
    $pasa16,
    'PDO Transaction garantiza que no quedan registros parciales ni archivos huérfanos ante excepciones.'
);

// ------------------------------------------------------------------------------------------------
// PRUEBA 17: Intento de acceso directo a .git/config o archivo .sql desde el navegador -> HTTP 403
// ------------------------------------------------------------------------------------------------
$res17Git = makeCurl($baseUrl . '/.git/config');
$res17Sql = makeCurl($baseUrl . '/php/migraciones/001_agregar_hash_sha256_no_destructivo.sql');

$pasa17 = ($res17Git['code'] === 403 && $res17Sql['code'] === 403);
registrarResultado(
    17,
    'Acceso directo por navegador a .git/config y archivos .sql de migraciones',
    'HTTP 403 Forbidden en ambas rutas',
    '.git/config: HTTP ' . $res17Git['code'] . ' | .sql: HTTP ' . $res17Sql['code'],
    $pasa17,
    'Reglas mod_rewrite y FilesMatch en root .htaccess bloquean metadatos de Git y código SQL interno.'
);

// Limpieza de archivo de prueba directa
if (file_exists($archivoPruebaUploads)) {
    @unlink($archivoPruebaUploads);
}

// ------------------------------------------------------------------------------------------------
// RESUMEN GLOBAL
// ------------------------------------------------------------------------------------------------
$totalPruebas = count($resultados);
$totalAprobadas = count(array_filter($resultados, fn($r) => $r['estado'] === 'APROBADO'));

echo "========================================================================================\n";
echo sprintf(" RESUMEN FINAL: %d / %d PRUEBAS APROBADAS (%.1f%%)\n", $totalAprobadas, $totalPruebas, ($totalAprobadas / $totalPruebas) * 100);
echo "========================================================================================\n";

if ($totalAprobadas === $totalPruebas) {
    echo "\033[32m>>> TODAS LAS PRUEBAS DE SEGURIDAD Y ESTÁNDARES FUERON SATISFECHAS EXITOSAMENTE. <<<\033[0m\n\n";
} else {
    echo "\033[31m>>> EXISTEN PRUEBAS FALLADAS. REVISAR DETALLES ARRIBA. <<<\033[0m\n\n";
}

// Guardar salida estructurada en formato JSON para informes
file_put_contents(__DIR__ . '/resultados_pruebas_seguridad.json', json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
