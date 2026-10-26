/* ==================================================================
   DocuFlow AI — GESTOR DE TEMA OSCURO (Sincronización Total)
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 6. GESTOR DE TEMA OSCURO (Sincronización Total)
// ═══════════════════════════════════════════════════════
window.toggleDarkMode = function () {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem(CONFIG.STORAGE_KEYS.THEME, isDark ? 'dark' : 'light');
    updateThemeIcons(isDark);
};

function updateThemeIcons(isDark) {
    const iconLanding = document.getElementById('theme-icon');
    const iconDash = document.getElementById('theme-icon-dash');

    if (iconLanding) {
        iconLanding.className = isDark ? 'fa-solid fa-sun text-yellow-400 text-base' : 'fa-solid fa-moon text-gray-600 text-base';
    }
    if (iconDash) {
        iconDash.className = isDark ? 'fa-solid fa-sun text-yellow-400 text-base' : 'fa-solid fa-moon text-gray-600 text-base';
    }
}

function initTheme() {
    const savedTheme = localStorage.getItem(CONFIG.STORAGE_KEYS.THEME);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const activeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);

    if (activeDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    updateThemeIcons(activeDark);
}
