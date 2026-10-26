<?php
/**
 * API de Autenticación - DocuFlow AI
 * Conexión a docuflow_db (MySQL 3307)
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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = Database::conectar();

    // Asegurar que el usuario admin demo exista si aún no está insertado
    $checkAdmin = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
    $checkAdmin->execute(['email' => 'admin@docuflow.ai']);
    if (!$checkAdmin->fetch()) {
        // Obtener rol ADM o el primero disponible
        $rolId = $pdo->query("SELECT id FROM roles ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
        $depId = $pdo->query("SELECT id FROM dependencias ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
        $hashPass = password_hash('DocuFlow2026', PASSWORD_BCRYPT);
        
        $insertAdmin = $pdo->prepare("INSERT INTO usuarios (rol_id, dependencia_id, nombre, email, password, activo) 
                                      VALUES (:rol, :dep, 'Administrador DocuFlow', 'admin@docuflow.ai', :pass, 1)");
        $insertAdmin->execute([
            'rol'  => $rolId,
            'dep'  => $depId,
            'pass' => $hashPass
        ]);
    }

    // Ruta: POST Login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'login' || empty($action))) {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'status' => 400,
                'message' => 'Por favor ingrese correo y contraseña.'
            ]);
            exit;
        }

        // Buscar usuario en docuflow_db
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.email, u.password, u.activo,
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
                'ok' => false,
                'status' => 401,
                'message' => 'Credenciales inválidas. Correo no registrado.'
            ]);
            exit;
        }

        if ((int)$usuario['activo'] !== 1) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'status' => 403,
                'message' => 'Usuario inactivo. Contacte al administrador.'
            ]);
            exit;
        }

        // Validar contraseña (compatible con password_verify o credenciales demo)
        $passwordValida = password_verify($password, $usuario['password']) 
                          || ($email === 'admin@docuflow.ai' && $password === 'DocuFlow2026')
                          || ($email === 'operador.demo@docuflow.test' && $password === 'DocuFlowDemo2026!');

        if (!$passwordValida) {
            http_response_code(401);
            echo json_encode([
                'ok' => false,
                'status' => 401,
                'message' => 'Contraseña incorrecta.'
            ]);
            exit;
        }

        // Generar Token de sesión simulado firmado
        $token = 'df_' . bin2hex(random_bytes(16)) . '_' . base64_encode(json_encode([
            'uid' => $usuario['id'],
            'email' => $usuario['email'],
            'time' => time()
        ]));

        // Registrar en auditoria_acciones
        DocuFlowDB::registrarAuditoria(
            (int)$usuario['id'],
            null,
            'INICIO_SESION',
            ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'agente' => $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        echo json_encode([
            'ok' => true,
            'status' => 200,
            'message' => 'Autenticación exitosa',
            'data' => [
                'token' => $token,
                'usuario' => [
                    'id' => (int)$usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'email' => $usuario['email'],
                    'rol' => $usuario['rol_nombre'],
                    'rol_codigo' => $usuario['rol_codigo'],
                    'dependencia' => $usuario['dependencia_nombre']
                ]
            ]
        ]);
        exit;
    }

    // Ruta: GET /me (Verificar sesión)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'me') {
        // En un entorno de demostración devolvemos datos del usuario activo
        $usuario = $pdo->query("
            SELECT u.id, u.nombre, u.email, r.codigo AS rol_codigo, r.nombre AS rol_nombre, d.nombre AS dependencia_nombre
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            JOIN dependencias d ON d.id = u.dependencia_id
            ORDER BY u.id ASC LIMIT 1
        ")->fetch();

        echo json_encode([
            'ok' => true,
            'status' => 200,
            'data' => $usuario
        ]);
        exit;
    }

    // Ruta: POST Logout
    if ($action === 'logout') {
        echo json_encode([
            'ok' => true,
            'status' => 200,
            'message' => 'Sesión finalizada correctamente'
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'message' => 'Error interno del servidor en MySQL (3307): ' . $e->getMessage()
    ]);
}
