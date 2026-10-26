/* ==================================================================
   DocuFlow AI — CONFIGURACIÓN Y CONSTANTES DEL CLIENTE
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 1. CONFIGURACIÓN Y CONSTANTES DEL CLIENTE
// ═══════════════════════════════════════════════════════
const CONFIG = {
    STORAGE_KEYS: {
        THEME: 'docuflow_theme',
        USER: 'docuflow_user_profile',
        CSRF: 'docuflow_csrf_token',
        LAST_VIEW: 'docuflow_last_view',
        LAST_TAB: 'docuflow_last_tab'
    },
    IDLE_TIMEOUT_MS: 15 * 60 * 1000 // 15 minutos de inactividad
};
