/* ==================================================================
   DocuFlow AI — Punto de entrada
   Restaura la vista/pestaña guardada y arranca la aplicación.
   Debe ser el ÚLTIMO script cargado.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 12. ENRUTAMIENTO & PERSISTENCIA AL RECARGAR (F5)
// ═══════════════════════════════════════════════════════
function parseAndApplyRoute() {
    const fullHash = window.location.hash.replace('#', '').trim();
    const token = localStorage.getItem(CONFIG.STORAGE_KEYS.TOKEN);
    const lastSavedView = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_VIEW);
    const lastSavedTab = localStorage.getItem(CONFIG.STORAGE_KEYS.LAST_TAB);

    // Si hay hash en la URL
    if (fullHash) {
        const parts = fullHash.split('/');
        const view = parts[0];
        const tab = parts[1];

        if (view === 'dashboard') {
            if (token) {
                showView('dashboard', false);
                if (tab && TABS.includes(tab)) {
                    switchDashboardTab(tab, false);
                } else if (lastSavedTab && TABS.includes(lastSavedTab)) {
                    switchDashboardTab(lastSavedTab, false);
                }
                resetIdleTimer();
            } else {
                showToast('HTTP 401 Unauthorized: Inicie sesión para acceder al Dashboard.', 'error', 401);
                showView('login', false);
            }
        } else if (view === 'login') {
            showView('login', false);
        } else {
            showView('landing', false);
        }
    }
    // Si se recarga sin hash pero hay estado guardado en LocalStorage
    else if (lastSavedView) {
        if (lastSavedView === 'dashboard' && token) {
            showView('dashboard', false);
            if (lastSavedTab && TABS.includes(lastSavedTab)) {
                switchDashboardTab(lastSavedTab, false);
            }
            resetIdleTimer();
        } else if (lastSavedView === 'login') {
            showView('login', false);
        } else {
            showView('landing', false);
        }
    } else {
        showView('landing', false);
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
