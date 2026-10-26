<?php
/**
 * DocuFlow AI — Helper de Autenticación, Sesiones Seguras y Permisos
 * Compatible con PHP 8 nativo y PDO
 */

require_once __DIR__ . '/conexion.php';

class Auth {
    private const TIEMPO_EXPIRACION_SEGUNDOS = 900; // 15 minutos de inactividad

    /**
     * Inicia una sesión PHP segura con flags HttpOnly y SameSite=Lax.
     */
    public static function iniciarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_only_cookies', '1');
            
            // Si el sitio corre bajo HTTPS, asegurar la cookie
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }

            session_start();
        }

        // Control de expiración por inactividad
        if (isset($_SESSION['ultimo_acceso'])) {
            if ((time() - $_SESSION['ultimo_acceso']) > self::TIEMPO_EXPIRACION_SEGUNDOS) {
                self::cerrarSesion();
                return;
            }
        }
        $_SESSION['ultimo_acceso'] = time();
    }

    /**
     * Devuelve el usuario actualmente autenticado o null.
     */
    public static function usuario(): ?array {
        self::iniciarSesion();
        return $_SESSION['usuario'] ?? null;
    }

    /**
     * Verifica si hay una sesión activa.
     */
    public static function estaAutenticado(): bool {
        return self::usuario() !== null;
    }

    /**
     * Obtiene el ID del usuario en sesión o lanza excepción/corta ejecución.
     */
    public static function usuarioId(): ?int {
        $u = self::usuario();
        return $u ? (int)$u['id'] : null;
    }

    /**
     * Establece los datos del usuario en la sesión PHP.
     */
    public static function establecerUsuario(array $datosUsuario, array $permisos = []): void {
        self::iniciarSesion();
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'                 => (int)$datosUsuario['id'],
            'nombre'             => $datosUsuario['nombre'],
            'email'              => $datosUsuario['email'],
            'rol_id'             => (int)$datosUsuario['rol_id'],
            'rol_codigo'         => $datosUsuario['rol_codigo'],
            'rol_nombre'         => $datosUsuario['rol_nombre'],
            'dependencia_id'     => (int)$datosUsuario['dependencia_id'],
            'dependencia_nombre' => $datosUsuario['dependencia_nombre'],
            'permisos'           => $permisos
        ];
        $_SESSION['ultimo_acceso'] = time();
        self::obtenerCsrfToken(); // Generar token CSRF para la sesión
    }

    /**
     * Cierra la sesión activa de forma segura.
     */
    public static function cerrarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Obtiene o genera un token CSRF seguro para la sesión actual.
     */
    public static function obtenerCsrfToken(): string {
        self::iniciarSesion();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida el token CSRF provisto contra la sesión.
     */
    public static function validarCsrfToken(?string $token): bool {
        self::iniciarSesion();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Carga los códigos de permisos asociados a un rol desde la base de datos.
     */
    public static function cargarPermisosRol(int $rolId, PDO $pdo): array {
        $stmt = $pdo->prepare("
            SELECT p.codigo
            FROM rol_permiso rp
            JOIN permisos p ON p.id = rp.permiso_id
            WHERE rp.rol_id = :rol_id
        ");
        $stmt->execute(['rol_id' => $rolId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Verifica si el usuario autenticado cuenta con un permiso específico.
     */
    public static function tienePermiso(string $codigoPermiso): bool {
        $u = self::usuario();
        if (!$u || empty($u['permisos'])) {
            return false;
        }
        return in_array($codigoPermiso, $u['permisos'], true);
    }

    /**
     * Exige que el usuario esté autenticado. Si no, responde con 401 y termina.
     */
    public static function requerirAutenticacion(): array {
        $u = self::usuario();
        if (!$u) {
            http_response_code(401);
            echo json_encode([
                'ok'      => false,
                'status'  => 401,
                'message' => 'No autorizado. Debe iniciar sesión para realizar esta operación.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        return $u;
    }

    /**
     * Exige que el usuario posea un permiso determinado. Si no, responde con 403 y termina.
     */
    public static function requerirPermiso(string $codigoPermiso): array {
        $u = self::requerirAutenticacion();
        if (!self::tienePermiso($codigoPermiso)) {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'status'  => 403,
                'message' => "Acceso denegado. Su rol ({$u['rol_codigo']}) no tiene el permiso requerido: {$codigoPermiso}."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        return $u;
    }
}
