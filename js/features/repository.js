/* ==================================================================
   DocuFlow AI — REPOSITORIO RAG: CONEXIÓN A BASE DE DATOS Y FILTRADO
   ------------------------------------------------------------------
   Carga dinámica de expedientes desde MySQL (docuflow_db) y filtrado
   reactivo en tiempo real con soporte offline.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 9. REPOSITORIO RAG: CONEXIÓN A BD Y FILTRADO REACTIVO
// ═══════════════════════════════════════════════════════

/**
 * Carga los documentos reales desde MySQL 3307 a través del API.
 */
window.cargarDocumentosDesdeBD = async function () {
    const tableBody = document.getElementById('repo-table-body');
    if (!tableBody) return;

    try {
        const res = await fakeFetch('/api/documents');
        const docs = res.data || [];

        if (Array.isArray(docs) && docs.length > 0) {
            tableBody.innerHTML = '';
            docs.forEach(doc => {
                const tr = document.createElement('tr');
                tr.className = 'repo-row hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors border-b border-gray-100 dark:border-slate-800/60';
                tr.dataset.search = `${doc.codigo_doc} ${doc.nombre_original} ${doc.tipo_documento} ${doc.dependencia} ${doc.asunto || ''}`.toLowerCase();
                
                const fecha = doc.created_at ? doc.created_at.substring(0, 10) : 'Reciente';
                const estadoClass = doc.estado_documental === 'vigente' 
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40'
                    : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800/40';

                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-file-lines text-lg"></i>
                            </div>
                            <div>
                                <div class="font-bold text-gray-900 dark:text-white text-sm">${escapeHTML(doc.codigo_doc || 'SIN-CODIGO')}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1">${escapeHTML(doc.nombre_original || 'Documento')}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <span class="font-semibold text-gray-800 dark:text-gray-200 block">${escapeHTML(doc.tipo_documento || 'General')}</span>
                        <span class="text-gray-400 text-[11px]">${escapeHTML(doc.dependencia || 'Archivo')}</span>
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400 font-mono">
                        ${escapeHTML(fecha)}
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${estadoClass}">
                            <i class="fa-solid ${doc.estado_documental === 'vigente' ? 'fa-circle-check text-[10px]' : 'fa-circle-xmark text-[10px]'}"></i>
                            ${escapeHTML(doc.estado_documental || 'vigente')}
                        </span>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        }
    } catch (err) {
        console.warn('No se pudieron cargar los documentos dinámicos desde la BD:', err);
    }
};

window.handleRepoFilter = function (query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#repo-table-body .repo-row');
    let matches = 0;

    rows.forEach(row => {
        const searchData = (row.dataset.search || row.textContent).toLowerCase();
        if (searchData.includes(q)) {
            row.classList.remove('hidden-view');
            matches++;
        } else {
            row.classList.add('hidden-view');
        }
    });

    let emptyRow = document.getElementById('repo-empty-row');
    if (matches === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'repo-empty-row';
            emptyRow.innerHTML = `<td colspan="4" class="px-6 py-8 text-center text-gray-400">No se encontraron expedientes coincidentes con "${escapeHTML(query)}".</td>`;
            document.getElementById('repo-table-body').appendChild(emptyRow);
        }
    } else if (emptyRow) {
        emptyRow.remove();
    }
};
