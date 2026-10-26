/* ==================================================================
   DocuFlow AI — BÚSQUEDA RÁPIDA EN LANDING (Reactiva)
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 11. BÚSQUEDA RÁPIDA EN LANDING (Reactiva)
// ═══════════════════════════════════════════════════════
const MOCK_HERO_DOCS = [
    { code: 'OFICIO-045-2024-UNS', title: 'Requerimiento Docente Semestral 2024-II', type: 'Oficio' },
    { code: 'CONTRATO-012-MANT', title: 'Mantenimiento Preventivo de Datacenter', type: 'Contrato' },
    { code: 'RESOLUCION-102-CU', title: 'Modernización de Sistemas de Archivo', type: 'Resolución' },
    { code: 'DIRECTIVA-004-SGD', title: 'Lineamientos de Firma Digital Certificada', type: 'Directiva' }
];

window.handleHeroQuickSearch = (query) => {
    const list = document.getElementById('quick-search-list');
    const res = document.getElementById('quick-search-results');
    const q = query.toLowerCase().trim();

    if (!q) {
        res.classList.add('hidden-view');
        return;
    }

    const matches = MOCK_HERO_DOCS.filter(d =>
        d.code.toLowerCase().includes(q) ||
        d.title.toLowerCase().includes(q) ||
        d.type.toLowerCase().includes(q)
    );

    if (matches.length === 0) {
        list.innerHTML = `<p class="text-gray-400 py-1">No se encontraron expedientes con "${escapeHTML(query)}".</p>`;
    } else {
        list.innerHTML = matches.map(d => `
            <div class="p-2 rounded-lg bg-gray-50 dark:bg-slate-800 flex items-center justify-between hover:bg-brand-50 dark:hover:bg-slate-700 cursor-pointer transition-colors" onclick="showToast('Expediente: ${d.code}', 'info')">
                <div>
                    <span class="font-bold text-gray-800 dark:text-gray-200 block">${d.code}</span>
                    <span class="text-[10px] text-gray-400">${d.title}</span>
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded bg-brand-100 dark:bg-brand-950 text-brand-600 dark:text-brand-300 font-bold">${d.type}</span>
            </div>
        `).join('');
    }

    res.classList.remove('hidden-view');
};

window.triggerQuickSearchAlert = () => {
    const input = document.getElementById('hero-quick-search');
    if (input && input.value.trim()) {
        showToast(`Búsqueda ejecutada para: "${input.value}"`, 'info');
    } else {
        showToast('Escribe un término para buscar en los documentos.', 'warning');
    }
};

window.toggleMobileMenu = () => {
    const m = document.getElementById('mobile-menu');
    const icon = document.getElementById('mobile-menu-icon');
    const isHidden = m.classList.toggle('hidden-view');
    if (icon) {
        icon.className = `fa-solid ${isHidden ? 'fa-bars' : 'fa-xmark'} text-xl`;
    }
};
