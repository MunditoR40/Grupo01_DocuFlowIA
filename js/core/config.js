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
    VALID_USER: 'admin@docuflow.ai',
    VALID_PASS: 'DocuFlow2026',
    STORAGE_KEYS: {
        THEME: 'docuflow_theme',
        TOKEN: 'docuflow_session_token',
        LAST_VIEW: 'docuflow_last_view',
        LAST_TAB: 'docuflow_last_tab'
    },
    MOCK_JWT: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.docuflow_simulated_token_2026',
    IDLE_TIMEOUT_MS: 15 * 60 * 1000 // 15 minutos de inactividad
};
