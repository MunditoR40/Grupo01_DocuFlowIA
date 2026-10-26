/* ==================================================================
   DocuFlow AI — CAPA DE COMUNICACIÓN API CON PERSISTENCIA REAL (MySQL 3307)
   ------------------------------------------------------------------
   Peticiones fetch() reales hacia los endpoints nativos PHP en php/api/...
   Sin respuestas ficticias ni simulaciones de guardado.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 3. CAPA DE COMUNICACIÓN CLIENTE-SERVIDOR (API FETCH REAL)
// ═══════════════════════════════════════════════════════

const API_ENDPOINTS = {
    '/api/auth/login': 'php/api/auth.php?action=login',
    '/api/auth/me': 'php/api/auth.php?action=me',
    '/api/auth/logout': 'php/api/auth.php?action=logout',
    '/api/documents/process': 'php/api/documentos.php?action=process',
    '/api/documents/store': 'php/api/documentos.php',
    '/api/documents': 'php/api/documentos.php',
    '/api/documents/catalogo': 'php/api/documentos.php?action=catalogo',
    '/api/resumen': 'php/api/resumen.php',
    '/api/auditoria': 'php/api/auditoria.php',
    '/api/chat/rag': 'php/api/chat.php'
};

window.apiFetch = async function (endpoint, options = {}) {
    const method = options.method || 'GET';
    const body = options.body || null;

    // Caso de prueba explícito para verificar control de errores
    if (options.forceError500) {
        const errObj = {
            ok: false,
            status: 500,
            message: 'Error de prueba forzado (HTTP 500): Fallo de servicio simulado para verificación.'
        };
        throw errObj;
    }

    const phpTarget = API_ENDPOINTS[endpoint] || (endpoint.startsWith('php/') ? endpoint : endpoint);

    const fetchOptions = {
        method: method,
        credentials: 'same-origin', // Fundamental para enviar y mantener la sesión PHP (PHPSESSID)
        headers: {
            'Accept': 'application/json'
        }
    };

    // Agregar token CSRF si existe para métodos que alteran estado
    const csrfToken = localStorage.getItem(CONFIG.STORAGE_KEYS.CSRF) || sessionStorage.getItem(CONFIG.STORAGE_KEYS.CSRF);
    if (csrfToken && method !== 'GET') {
        fetchOptions.headers['X-CSRF-Token'] = csrfToken;
    }

    if (body) {
        if (body instanceof FormData) {
            // FormData: el navegador configura el Content-Type y boundary automáticamente
            fetchOptions.body = body;
        } else {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(body);
        }
    }

    try {
        const response = await fetch(phpTarget, fetchOptions);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errorPayload = {
                ok: false,
                status: response.status,
                message: data.message || `Error del servidor (HTTP ${response.status})`,
                data: data.data || null
            };
            throw errorPayload;
        }

        return {
            ok: true,
            status: response.status,
            message: data.message || 'Operación exitosa',
            data: data.data !== undefined ? data.data : data
        };

    } catch (error) {
        // Si el error ya fue estructurado por el bloque de respuesta HTTP, relanzarlo
        if (error && error.status !== undefined) {
            throw error;
        }

        // Si es un error de red o conexión caída
        console.error('[DocuFlow API Error]', error);
        throw {
            ok: false,
            status: 0,
            message: 'No se pudo conectar con el servidor web o el servicio MySQL (puerto 3307). Verifique que Apache y MySQL estén activos en XAMPP.'
        };
    }
};

// Mantener fakeFetch como alias de apiFetch para compatibilidad con llamadas existentes
window.fakeFetch = window.apiFetch;
