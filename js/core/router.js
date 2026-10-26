/* ==================================================================
   DocuFlow AI — ROUTER SPA CON HISTORY API & PERSISTENCIA TOTAL
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 4. ROUTER SPA CON HISTORY API & PERSISTENCIA TOTAL
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
 * Cambia la vista y sincroniza el estado en LocalStorage y URL hash.
 */
window.showView = function (viewName, pushToHistory = true) {
    if (!VIEWS.includes(viewName)) viewName = 'landing';

    // Protección de ruta: Si intenta ingresar al dashboard sin sesión activa
    if (viewName === 'dashboard') {
        const token = localStorage.getItem(CONFIG.STORAGE_KEYS.TOKEN);
        if (!token) {
            showToast('HTTP 401 Unauthorized: Debe iniciar sesión para acceder al Dashboard.', 'error', 401);
            if (!document.getElementById('view-login')) {
                window.location.href = 'login.html';
                return;
            }
            showView('login', pushToHistory);
            return;
        }
    }

    // Si la vista solicitada no está en el documento actual, redirigir a su archivo .html
    const targetElement = document.getElementById(`view-${viewName}`);
    if (!targetElement) {
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

    // Guardar última vista visitada para recargas (F5)
    localStorage.setItem(CONFIG.STORAGE_KEYS.LAST_VIEW, viewName);

    // Alternar contenedores
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

    // Si entramos al dashboard, restaurar la pestaña correspondiente y refrescar datos
    if (viewName === 'dashboard') {
        const savedTab = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_TAB) || 'ocr';
        switchDashboardTab(savedTab, false);
        if (typeof cargarDocumentosDesdeBD === 'function') cargarDocumentosDesdeBD();
    }

    // Sincronizar URL hash de forma compatible con entornos locales (file:// y http://)
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
    parseAndApplyRoute();
});
