<?php
/**
 * API de Autenticación - DocuFlow AI
 * Conexión a docuflow_db (MySQL 3307)
 * Sesiones PHP nativas seguras, password_verify() y control de roles
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
$rawInput = file_get_contents('php://input');
$input = (!empty($rawInput) ? json_decode($rawInput, true) : null) ?? $_POST ?? [];

try {
    $pdo = Database::conectar();
    Auth::iniciarSesion();

    // ─────────────────────────────────────────────────────────
    // 1. POST: Inicio de Sesión (Login)
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST' && ($action === 'login' || empty($action))) {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'status'  => 400,
                'message' => 'Por favor ingrese correo y contraseña.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Buscar usuario en docuflow_db respetando el esquema
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.email, u.password, u.activo,
                   u.rol_id, u.dependencia_id,
                   r.codigo AS rol_codigo, r.nombre AS rol_nombre,
                   d.nombre AS dependencia_nombre
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            JOIN dependencias d ON d.id = u.dependencia_id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            http_response_code(401);
            echo json_encode([
                'ok'      => false,
                'status'  => 401,
                'message' => 'Credenciales inválidas. Correo no registrado.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificación de estado activo (0 = inactivo)
        if ((int)$usuario['activo'] !== 1) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => 'Usuario inactivo. Contacte al administrador del sistema.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificación estricta de contraseña con password_verify()
        // (Sin contraseñas fijas ni puertas traseras)
        if (!password_verify($password, $usuario['password'])) {
            http_response_code(401);
            echo json_encode([
                'ok'      => false,
                'status'  => 401,
                'message' => 'Contraseña incorrecta.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Cargar permisos reales asignados al rol en rol_permiso
        $permisos = Auth::cargarPermisosRol((int)$usuario['rol_id'], $pdo);

        // Guardar usuario en sesión PHP segura y regenerar ID de sesión
        Auth::establecerUsuario($usuario, $permisos);
        $csrfToken = Auth::obtenerCsrfToken();

        // Registrar evento real en auditoria_acciones
        DocuFlowDB::registrarAuditoria(
            (int)$usuario['id'],
            null,
            'LOGIN',
            [
                'email' => $usuario['email'],
                'rol'   => $usuario['rol_codigo'],
                'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]
        );

        http_response_code(200);
        echo json_encode([
            'ok'      => true,
            'status'  => 200,
            'message' => 'Autenticación exitosa',
            'data'    => [
                'usuario' => [
                    'id'                 => (int)$usuario['id'],
                    'nombre'             => $usuario['nombre'],
                    'email'              => $usuario['email'],
                    'rol_id'             => (int)$usuario['rol_id'],
                    'rol_codigo'         => $usuario['rol_codigo'],
                    'rol_nombre'         => $usuario['rol_nombre'],
                    'dependencia_id'     => (int)$usuario['dependencia_id'],
                    'dependencia_nombre' => $usuario['dependencia_nombre'],
                    'permisos'           => $permisos
                ],
                'csrf_token' => $csrfToken
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 2. GET: Obtener usuario en sesión actual (/api/auth/me)
    // ─────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'me') {
        $usuarioActual = Auth::usuario();

        if (!$usuarioActual) {
            http_response_code(401);
            echo json_encode([
                'ok'      => false,
                'status'  => 401,
                'message' => 'No hay una sesión activa o ha expirado por inactividad.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            'ok'      => true,
            'status'  => 200,
            'data'    => [
                'usuario'    => $usuarioActual,
                'csrf_token' => Auth::obtenerCsrfToken()
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // 3. POST: Cierre de Sesión (Logout)
    // ─────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'logout') {
        $usuarioActual = Auth::usuario();

        if ($usuarioActual) {
            DocuFlowDB::registrarAuditoria(
                (int)$usuarioActual['id'],
                null,
                'LOGOUT',
                ['email' => $usuarioActual['email']]
            );
        }

        Auth::cerrarSesion();

        http_response_code(200);
        echo json_encode([
            'ok'      => true,
            'status'  => 200,
            'message' => 'Sesión cerrada correctamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Acción no reconocida
    http_response_code(404);
    echo json_encode([
        'ok'      => false,
        'status'  => 404,
        'message' => 'Ruta de autenticación no encontrada.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en php/api/auth.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'status'  => 500,
        'message' => 'Error interno del servidor al procesar la autenticación.'
    ], JSON_UNESCAPED_UNICODE);
}
