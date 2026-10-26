/* ==================================================================
   DocuFlow AI — Precarga del tema
   Se ejecuta en el <head>, antes de pintar la página, para evitar el
   parpadeo (FOUC) entre modo claro y oscuro al recargar (F5).
   ================================================================== */
(function () {
    try {
        const savedTheme = localStorage.getItem('docuflow_theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
        } else if (savedTheme === 'light') {
            document.documentElement.classList.remove('dark');
        }
    } catch (e) { }
})();
