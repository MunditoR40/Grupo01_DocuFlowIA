<?php
/**
 * DocuFlow AI — Helper de Autenticación, Sesiones Seguras y Permisos Centralizados
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
     * Obtiene el ID del usuario en sesión o null.
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

/**
 * Servicio Centralizado de Autorización y Permisos Documentales
 * Aplica rigurosamente la matriz de los 5 roles y restricciones de confidencialidad y ámbito.
 */
class DocumentoPermisos {

    /**
     * Determina si el usuario puede consultar un documento específico.
     */
    public static function puedeConsultar(array $usuario, array $doc): bool {
        if (($doc['estado_documental'] ?? 'vigente') === 'anulado') {
            return false;
        }

        $rol = $usuario['rol_codigo'] ?? '';
        switch ($rol) {
            case 'ADM':
                // ADM: sin contenido documental por defecto
                return false;
            case 'OPE':
                // OPE: exclusivamente sobre documentos propios
                return (int)$doc['usuario_id'] === (int)$usuario['id'];
            case 'GES':
                // GES: exclusivamente sobre documentos de su dependencia
                return (int)$doc['dependencia_id'] === (int)$usuario['dependencia_id'];
            case 'CON':
                // CON: debe cumplir tanto el filtro de dependencia como el de confidencialidad (publico/interno)
                $conf = strtolower($doc['nivel_confidencialidad'] ?? 'interno');
                $permitida = in_array($conf, ['publico', 'interno'], true);
                return ((int)$doc['dependencia_id'] === (int)$usuario['dependencia_id']) && $permitida;
            case 'AUD':
                // AUD: consulta de metadatos permitida para fines de auditoría
                return true;
            default:
                return false;
        }
    }

    /**
     * Determina si el usuario puede descargar el archivo físico de un documento.
     */
    public static function puedeDescargar(array $usuario, array $doc): bool {
        if (!Auth::tienePermiso('DOCUMENTOS_DESCARGAR')) {
            return false;
        }

        // Documentos anulados se excluyen de descarga operativa
        if (($doc['estado_documental'] ?? 'vigente') === 'anulado') {
            return false;
        }

        $rol = $usuario['rol_codigo'] ?? '';
        switch ($rol) {
            case 'ADM':
                // ADM: no accede a descargas de contenido documental por defecto
                return false;
            case 'AUD':
                // AUD: inspecciona metadatos y auditoría; sin descargar contenido documental
                return false;
            case 'OPE':
                // OPE: no debe descargar documentos ajenos aunque pertenezcan a su dependencia
                return (int)$doc['usuario_id'] === (int)$usuario['id'];
            case 'GES':
                // GES: no debe administrar ni descargar documentos de otras dependencias
                return (int)$doc['dependencia_id'] === (int)$usuario['dependencia_id'];
            case 'CON':
                // CON: debe pertenecer a su dependencia y no ser confidencial
                $conf = strtolower($doc['nivel_confidencialidad'] ?? 'interno');
                $permitida = in_array($conf, ['publico', 'interno'], true);
                return ((int)$doc['dependencia_id'] === (int)$usuario['dependencia_id']) && $permitida;
            default:
                return false;
        }
    }

    /**
     * Determina si el usuario puede registrar un documento en una dependencia dada.
     */
    public static function puedeRegistrar(array $usuario, ?int $dependenciaId = null): bool {
        if (!Auth::tienePermiso('DOCUMENTOS_REGISTRAR')) {
            return false;
        }
        $rol = $usuario['rol_codigo'] ?? '';
        if ($rol === 'OPE') {
            if ($dependenciaId !== null && (int)$dependenciaId !== (int)$usuario['dependencia_id']) {
                return false;
            }
            return true;
        }
        if ($rol === 'GES') {
            if ($dependenciaId !== null && (int)$dependenciaId !== (int)$usuario['dependencia_id']) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Determina si el usuario puede anular un documento.
     */
    public static function puedeAnular(array $usuario, array $doc): bool {
        if (!Auth::tienePermiso('DOCUMENTOS_ANULAR')) {
            return false;
        }
        $rol = $usuario['rol_codigo'] ?? '';
        if ($rol === 'GES') {
            return (int)$doc['dependencia_id'] === (int)$usuario['dependencia_id'];
        }
        return false;
    }

    /**
     * Determina si el usuario puede consultar la bitácora de auditoría.
     */
    public static function puedeConsultarAuditoria(array $usuario): bool {
        if (!Auth::tienePermiso('AUDITORIA_CONSULTAR')) {
            return false;
        }
        $rol = $usuario['rol_codigo'] ?? '';
        // Solo AUD, GES y ADM están autorizados a consultar auditoría según su ámbito
        return in_array($rol, ['AUD', 'GES', 'ADM'], true);
    }

    /**
     * Determina si el usuario puede consultar el resumen de estadísticas.
     */
    public static function puedeConsultarEstadisticas(array $usuario): bool {
        if (!Auth::tienePermiso('ESTADISTICAS_VER')) {
            return false;
        }
        $rol = $usuario['rol_codigo'] ?? '';
        // CON no tiene autorización para métricas estadísticas operativas
        if ($rol === 'CON') {
            return false;
        }
        return in_array($rol, ['OPE', 'GES', 'ADM', 'AUD'], true);
    }

    /**
     * Genera la cláusula SQL WHERE y parámetros PDO para filtrar listados según el rol y ámbito.
     */
    public static function obtenerFiltroDocumentosSQL(array $usuario, string $alias = 'd'): array {
        $rol = $usuario['rol_codigo'] ?? '';
        $sql = " AND {$alias}.estado_documental = 'vigente' ";
        $params = [];

        switch ($rol) {
            case 'OPE':
                // OPE ve únicamente sus propios documentos
                $sql .= " AND {$alias}.usuario_id = :scope_uid ";
                $params['scope_uid'] = (int)$usuario['id'];
                break;
            case 'GES':
                // GES ve los documentos de su dependencia
                $sql .= " AND {$alias}.dependencia_id = :scope_dep ";
                $params['scope_dep'] = (int)$usuario['dependencia_id'];
                break;
            case 'CON':
                // CON ve documentos de su dependencia y con confidencialidad permitida
                $sql .= " AND {$alias}.dependencia_id = :scope_dep AND {$alias}.nivel_confidencialidad IN ('publico', 'interno') ";
                $params['scope_dep'] = (int)$usuario['dependencia_id'];
                break;
            case 'AUD':
                // AUD ve metadatos de los documentos vigentes para auditoría
                break;
            case 'ADM':
            default:
                // ADM no tiene acceso a contenido documental por defecto
                $sql .= " AND 1=0 ";
                break;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Genera la cláusula SQL WHERE y parámetros PDO para el Chatbot contextual.
     */
    public static function obtenerFiltroChatbotSQL(array $usuario, string $alias = 'd'): array {
        if (!Auth::tienePermiso('RAG_CONSULTAR')) {
            return ['sql' => " AND 1=0 ", 'params' => []];
        }

        $rol = $usuario['rol_codigo'] ?? '';
        $sql = " AND {$alias}.estado_documental = 'vigente' ";
        $params = [];

        switch ($rol) {
            case 'OPE':
                // OPE solo consulta sobre sus propios documentos
                $sql .= " AND {$alias}.usuario_id = :chat_scope_uid ";
                $params['chat_scope_uid'] = (int)$usuario['id'];
                break;
            case 'GES':
                // GES solo consulta sobre documentos de su dependencia
                $sql .= " AND {$alias}.dependencia_id = :chat_scope_dep ";
                $params['chat_scope_dep'] = (int)$usuario['dependencia_id'];
                break;
            case 'CON':
                // CON consulta sobre su dependencia y documentos no confidenciales
                $sql .= " AND {$alias}.dependencia_id = :chat_scope_dep AND {$alias}.nivel_confidencialidad IN ('publico', 'interno') ";
                $params['chat_scope_dep'] = (int)$usuario['dependencia_id'];
                break;
            case 'ADM':
            case 'AUD':
            default:
                // ADM y AUD no tienen búsqueda de contenido documental
                $sql .= " AND 1=0 ";
                break;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Genera la cláusula SQL WHERE y parámetros PDO para Auditoría según el rol.
     */
    public static function obtenerFiltroAuditoriaSQL(array $usuario, string $alias = 'a'): array {
        if (!Auth::tienePermiso('AUDITORIA_CONSULTAR')) {
            return ['sql' => " 1=0 ", 'params' => []];
        }

        $rol = $usuario['rol_codigo'] ?? '';
        $params = [];

        switch ($rol) {
            case 'AUD':
                // AUD consulta todas las trazas del sistema
                $sql = " 1=1 ";
                break;
            case 'ADM':
                // ADM: solo eventos técnicos/administrativos o sin documento asociado
                $sql = " ({$alias}.documento_id IS NULL OR {$alias}.accion IN ('LOGIN', 'LOGOUT', 'USUARIO_CREADO', 'USUARIO_MODIFICADO', 'INSERCION_SQL_DEMO')) ";
                break;
            case 'GES':
                // GES: operaciones sobre documentos de su dependencia o usuarios de su dependencia
                $sql = " ({$alias}.documento_id IN (SELECT id FROM documentos WHERE dependencia_id = :aud_dep) OR {$alias}.usuario_id IN (SELECT id FROM usuarios WHERE dependencia_id = :aud_dep_u)) ";
                $params['aud_dep'] = (int)$usuario['dependencia_id'];
                $params['aud_dep_u'] = (int)$usuario['dependencia_id'];
                break;
            case 'OPE':
            case 'CON':
            default:
                $sql = " 1=0 ";
                break;
        }

        return ['sql' => $sql, 'params' => $params];
    }
}
