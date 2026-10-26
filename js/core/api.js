/* ==================================================================
   DocuFlow AI — CAPA DE COMUNICACIÓN API CON PERSISTENCIA REAL (MySQL 3307)
   ------------------------------------------------------------------
   Peticiones fetch() reales hacia los endpoints nativos PHP en php/api/...
   Soporte robusto de parámetros de búsqueda (?q=...&estado=...) con URLSearchParams.
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

/**
 * Resuelve rutas abstractas (ej: /api/documents?q=oficio) al script real en php/api/...
 * combinando adecuadamente los parámetros de consulta mediante URLSearchParams.
 */
function resolveApiTarget(endpoint) {
    if (!endpoint) return 'php/api/documentos.php';

    // Separar path de query string
    const [pathPart, queryPart] = endpoint.split('?');
    const mapped = API_ENDPOINTS[pathPart] || (pathPart.startsWith('php/') ? pathPart : null);

    if (mapped) {
        const [targetPath, targetQuery] = mapped.split('?');
        const params = new URLSearchParams(targetQuery || '');

        if (queryPart) {
            const extraParams = new URLSearchParams(queryPart);
            for (const [key, value] of extraParams.entries()) {
                params.set(key, value);
            }
        }

        const queryString = params.toString();
        return queryString ? `${targetPath}?${queryString}` : targetPath;
    }

    return endpoint;
}

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

    const phpTarget = resolveApiTarget(endpoint);

    const fetchOptions = {
        method: method,
        credentials: 'same-origin', // Envía y conserva la cookie de sesión PHP (PHPSESSID)
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
            // FormData: el navegador configura automáticamente multipart/form-data con boundary
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
        if (error && error.status !== undefined) {
            throw error;
        }

        console.error('[DocuFlow API Error]', error);
        throw {
            ok: false,
            status: 0,
            message: 'No se pudo conectar con el servidor web o el servicio MySQL (puerto 3307).'
        };
    }
};

// Alias para compatibilidad con código legado
window.fakeFetch = window.apiFetch;
