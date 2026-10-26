/* ==================================================================
   DocuFlow AI — Punto de entrada
   Inicializa temas, valida sesiones reales con PHP y sincroniza la vista.
   Compatible con arquitectura multi-página (.html) y SPA.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 12. ENRUTAMIENTO & PERSISTENCIA AL RECARGAR (F5)
// ═══════════════════════════════════════════════════════
function getCurrentPageType() {
    const path = window.location.pathname.toLowerCase();
    if (path.endsWith('login.html')) return 'login';
    if (path.endsWith('dashboard.html')) return 'dashboard';
    return 'landing'; // index.html, landing.html o raíz /
}

async function parseAndApplyRoute() {
    const pageType = getCurrentPageType();
    const fullHash = window.location.hash.replace('#', '').trim();
    const lastSavedTab = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_TAB) || 'ocr';

    // ─────────────────────────────────────────────────────────
    // CASO 1: Estamos en dashboard.html
    // ─────────────────────────────────────────────────────────
    if (pageType === 'dashboard') {
        try {
            // Verificación real de sesión en el backend PHP
            const meRes = await apiFetch('/api/auth/me');

            if (meRes.ok && meRes.data?.usuario) {
                const u = meRes.data.usuario;
                localStorage.setItem(CONFIG.STORAGE_KEYS.USER, JSON.stringify(u));
                if (meRes.data.csrf_token) {
                    localStorage.setItem(CONFIG.STORAGE_KEYS.CSRF, meRes.data.csrf_token);
                }

                // Actualizar visualización del usuario autenticado en la barra lateral
                const nameEl = document.getElementById('user-display-name');
                const emailEl = document.getElementById('user-display-email');
                if (nameEl) nameEl.textContent = u.nombre;
                if (emailEl) emailEl.textContent = `${u.email} (${u.rol_codigo})`;

                // Mostrar contenedor del dashboard
                const dashEl = document.getElementById('view-dashboard');
                if (dashEl) dashEl.classList.remove('hidden-view');

                // Si el rol es ADM (sin registro por defecto), sugerir pestaña 'resumen'
                const defaultInitialTab = (u.rol_codigo === 'ADM') ? 'resumen' : 'ocr';

                let activeTab = defaultInitialTab;
                if (fullHash) {
                    const parts = fullHash.split('/');
                    const tabFromHash = (parts[0] === 'dashboard' && parts[1]) ? parts[1] : parts[0];
                    if (TABS.includes(tabFromHash)) activeTab = tabFromHash;
                } else if (lastSavedTab && TABS.includes(lastSavedTab)) {
                    activeTab = lastSavedTab;
                }

                switchDashboardTab(activeTab, false);
                if (typeof resetIdleTimer === 'function') resetIdleTimer();

                // Cargar datos reales de MySQL
                if (typeof cargarDocumentosDesdeBD === 'function') cargarDocumentosDesdeBD();
                if (typeof cargarAuditoriaDesdeBD === 'function') cargarAuditoriaDesdeBD();
                if (typeof cargarMetricasResumen === 'function') cargarMetricasResumen();
            }
        } catch (authErr) {
            console.warn('[DocuFlow] Sesión no detectada en servidor:', authErr);
            localStorage.removeItem(CONFIG.STORAGE_KEYS.USER);
            localStorage.removeItem(CONFIG.STORAGE_KEYS.CSRF);

            showToast('Debe iniciar sesión para acceder al Dashboard.', 'error', 401);
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 300);
            return;
        }
        return;
    }

    // ─────────────────────────────────────────────────────────
    // CASO 2: Estamos en login.html
    // ─────────────────────────────────────────────────────────
    if (pageType === 'login') {
        const loginEl = document.getElementById('view-login');
        if (loginEl) loginEl.classList.remove('hidden-view');

        // Si ya hay una sesión activa, redirigir automáticamente al dashboard
        try {
            const checkMe = await apiFetch('/api/auth/me');
            if (checkMe.ok && checkMe.data?.usuario) {
                window.location.href = 'dashboard.html';
            }
        } catch (e) {
            // No está autenticado, permanecer en login
        }
        return;
    }

    // ─────────────────────────────────────────────────────────
    // CASO 3: Estamos en index.html o landing.html (Página Principal)
    // ─────────────────────────────────────────────────────────
    const landingEl = document.getElementById('view-landing');
    if (landingEl) landingEl.classList.remove('hidden-view');

    // Modo SPA legado
    if (fullHash === 'login' && document.getElementById('view-login')) {
        showView('login', false);
    } else if (fullHash.startsWith('dashboard') && document.getElementById('view-dashboard')) {
        showView('dashboard', false);
    }
}

function initApp() {
    initTheme();
    parseAndApplyRoute();
}

// Ejecutar al iniciar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
