/* ==================================================================
   DocuFlow AI — GESTOR DE INACTIVIDAD (Client-Side Idle Timeout)
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 5. GESTOR DE INACTIVIDAD (Client-Side Idle Timeout)
// ═══════════════════════════════════════════════════════
let idleTimeoutTimer = null;

function resetIdleTimer() {
    if (idleTimeoutTimer) clearTimeout(idleTimeoutTimer);

    const token = localStorage.getItem(CONFIG.STORAGE_KEYS.TOKEN);
    // Solo vigilar inactividad si hay sesión activa iniciada
    if (token) {
        idleTimeoutTimer = setTimeout(() => {
            triggerIdleLogout();
        }, CONFIG.IDLE_TIMEOUT_MS);
    }
}

function triggerIdleLogout() {
    const token = localStorage.getItem(CONFIG.STORAGE_KEYS.TOKEN);
    if (!token) return;

    localStorage.removeItem(CONFIG.STORAGE_KEYS.TOKEN);
    showToast('HTTP 401 Unauthorized: Sesión cerrada por inactividad (15 min). Por seguridad, inicie sesión nuevamente.', 'error', 401);
    showView('login', true);
}

// Exponer función de prueba rápida de inactividad para demostración
window.testIdleTimeout = function () {
    triggerIdleLogout();
};

// Eventos del usuario para reiniciar el reloj de inactividad
['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
    window.addEventListener(evt, resetIdleTimer, { passive: true });
});
