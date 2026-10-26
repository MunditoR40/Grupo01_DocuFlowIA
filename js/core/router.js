/* ==================================================================
   DocuFlow AI — ROUTER CON SOPORTE MULTI-PÁGINA & SPA
   ------------------------------------------------------------------
   Manejo de navegación entre vistas y sincronización de pestañas
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 4. ROUTER CON SOPORTE MULTI-PÁGINA & SPA
// ═══════════════════════════════════════════════════════
const VIEWS = ['landing', 'login', 'dashboard'];
const TABS = ['resumen', 'ocr', 'repositorio', 'auditoria'];
const TAB_TITLES = {
    resumen: 'Resumen General',
    ocr: 'Centro OCR & Carga',
    repositorio: 'Repositorio RAG',
    auditoria: 'Auditoría & Logs'
};

/**
 * Cambia la vista activa o redirige a la página correspondiente.
 */
window.showView = function (viewName, pushToHistory = true) {
    if (!VIEWS.includes(viewName)) viewName = 'landing';

    // Protección de ruta: Si intenta ingresar al dashboard sin sesión activa
    if (viewName === 'dashboard') {
        const user = localStorage.getItem(CONFIG.STORAGE_KEYS.USER);
        if (!user && window.location.protocol !== 'file:') {
            showToast('HTTP 401 Unauthorized: Debe iniciar sesión para acceder al Dashboard.', 'error', 401);
            if (!document.getElementById('view-login')) {
                window.location.href = 'login.html';
                return;
            }
            showView('login', pushToHistory);
            return;
        }
    }

    const targetElement = document.getElementById(`view-${viewName}`);

    // Si la vista no está en la página actual y fue una acción intencional (click):
    if (!targetElement) {
        if (pushToHistory) {
            if (viewName === 'login') {
                window.location.href = 'login.html';
                return;
            } else if (viewName === 'dashboard') {
                window.location.href = 'dashboard.html';
                return;
            } else if (viewName === 'landing') {
                window.location.href = 'index.html';
                return;
            }
        }
        return;
    }

    // Alternar contenedores presentes en la página actual
    VIEWS.forEach(v => {
        const el = document.getElementById(`view-${v}`);
        if (el) {
            if (v === viewName) {
                el.classList.remove('hidden-view');
                el.style.animation = 'none';
                el.offsetHeight; // Reflow
                el.style.animation = '';
            } else {
                el.classList.add('hidden-view');
            }
        }
    });

    // Si entramos al dashboard, restaurar la pestaña correspondiente
    if (viewName === 'dashboard') {
        const savedTab = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_TAB) || 'ocr';
        switchDashboardTab(savedTab, false);
        if (typeof cargarDocumentosDesdeBD === 'function') cargarDocumentosDesdeBD();
    }

    // Sincronizar URL hash si se solicita
    if (pushToHistory) {
        let newHash = '';
        if (viewName === 'dashboard') {
            const activeTab = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_TAB) || 'ocr';
            newHash = `#dashboard/${activeTab}`;
        } else if (viewName === 'login') {
            newHash = '#login';
        } else {
            newHash = '#landing';
        }

        const targetUrl = window.location.pathname + window.location.search + newHash;
        history.pushState({ view: viewName }, '', targetUrl);
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/**
 * Conmuta las pestañas dentro del Dashboard sin alterar el resto de la interfaz.
 */
window.switchDashboardTab = function (tabName, syncUrl = true) {
    if (!TABS.includes(tabName)) tabName = 'ocr';

    // Guardar pestaña actual para recargas (F5)
    localStorage.setItem(CONFIG.STORAGE_KEYS.LAST_TAB, tabName);

    TABS.forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        if (el) {
            if (t === tabName) {
                el.classList.remove('hidden-view');
                el.style.animation = 'none';
                el.offsetHeight;
                el.style.animation = '';
            } else {
                el.classList.add('hidden-view');
            }
        }
    });

    // Actualizar título en header
    const titleEl = document.getElementById('dashboard-title');
    if (titleEl) titleEl.textContent = TAB_TITLES[tabName];

    // Actualizar clases activas en sidebar (Desktop)
    document.querySelectorAll('#dashboard-nav .nav-link').forEach(link => {
        if (link.dataset.tab === tabName) {
            link.className = 'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 font-semibold shadow-sm nav-link active';
        } else {
            link.className = 'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors nav-link';
        }
    });

    // Actualizar clases activas en barra de pestañas (Móvil)
    document.querySelectorAll('.mobile-tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.className = 'mobile-tab-btn shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-600 text-white shadow-sm transition-colors';
        } else {
            btn.className = 'mobile-tab-btn shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-slate-800 transition-colors';
        }
    });

    // Carga de datos según la pestaña activa desde MySQL (Puerto 3307)
    if (tabName === 'repositorio' && typeof cargarDocumentosDesdeBD === 'function') {
        cargarDocumentosDesdeBD();
    } else if (tabName === 'auditoria' && typeof cargarAuditoriaDesdeBD === 'function') {
        cargarAuditoriaDesdeBD();
    } else if (tabName === 'resumen' && typeof cargarMetricasResumen === 'function') {
        cargarMetricasResumen();
    }

    // Actualizar URL hash si se solicita
    if (syncUrl) {
        const targetUrl = window.location.pathname + window.location.search + `#dashboard/${tabName}`;
        history.pushState({ view: 'dashboard', tab: tabName }, '', targetUrl);
    }
};

// Escuchar navegación Atrás / Adelante del navegador (popstate)
window.addEventListener('popstate', () => {
    if (typeof parseAndApplyRoute === 'function') parseAndApplyRoute();
});
