/* ==================================================================
   DocuFlow AI — SISTEMA DE TOASTS FLOTANTES
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 2. SISTEMA DE TOASTS FLOTANTES
// ═══════════════════════════════════════════════════════
window.showToast = function (message, type = 'info', statusCode = null) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    let bgStyle = 'bg-slate-800 text-white border-slate-700';
    let icon = 'fa-circle-info';

    if (statusCode === 200 || statusCode === 201 || type === 'success') {
        bgStyle = 'bg-emerald-600 text-white border-emerald-500 shadow-emerald-500/20';
        icon = 'fa-circle-check';
    } else if (statusCode >= 400 || type === 'error') {
        bgStyle = 'bg-rose-600 text-white border-rose-500 shadow-rose-500/20';
        icon = 'fa-triangle-exclamation';
    } else if (type === 'warning') {
        bgStyle = 'bg-amber-600 text-white border-amber-500 shadow-amber-500/20';
        icon = 'fa-triangle-exclamation';
    }

    toast.className = `flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-xl text-xs font-semibold animate-toast-in pointer-events-auto min-w-[280px] max-w-sm ${bgStyle}`;
    toast.innerHTML = `
        <i class="fa-solid ${icon} text-base shrink-0"></i>
        <div class="flex-1 leading-snug">${escapeHTML(message)}</div>
        <button onclick="this.parentElement.remove()" class="text-white/60 hover:text-white shrink-0 ml-1">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('animate-toast-in');
        toast.classList.add('animate-toast-out');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};

function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
