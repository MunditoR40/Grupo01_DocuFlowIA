/* ==================================================================
   DocuFlow AI — REPOSITORIO RAG: CONEXIÓN A BASE DE DATOS Y FILTRADO
   ------------------------------------------------------------------
   Carga dinámica de expedientes desde MySQL (docuflow_db, Puerto 3307)
   mediante PHP nativo + PDO.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 9. REPOSITORIO RAG: CONEXIÓN A BD Y FILTRADO REACTIVO
// ═══════════════════════════════════════════════════════

/**
 * Carga los documentos reales desde MySQL 3307 a través del API.
 */
window.cargarDocumentosDesdeBD = async function (filtroBusqueda = '') {
    const tableBody = document.getElementById('repo-table-body');
    if (!tableBody) return;

    try {
        let endpoint = '/api/documents';
        if (filtroBusqueda && filtroBusqueda.trim().length > 0) {
            endpoint += '?q=' + encodeURIComponent(filtroBusqueda.trim());
        }

        const res = await apiFetch(endpoint);
        const docs = res.data || [];

        tableBody.innerHTML = '';

        if (Array.isArray(docs) && docs.length > 0) {
            docs.forEach(doc => {
                const tr = document.createElement('tr');
                tr.className = 'repo-row hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors border-b border-gray-100 dark:border-slate-800/60';
                
                // Atributo de búsqueda local rápida
                const searchTokens = `${doc.id} ${doc.codigo_doc || ''} ${doc.nombre_original || ''} ${doc.tipo_documento || ''} ${doc.dependencia || ''} ${doc.registrado_por || ''} ${doc.asunto || ''}`.toLowerCase();
                tr.dataset.search = searchTokens;

                const fechaFormateada = doc.created_at ? doc.created_at.substring(0, 16).replace('T', ' ') : 'Reciente';

                // Badges de Estado Documental
                const estadoDocClass = doc.estado_documental === 'vigente'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40'
                    : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800/40';

                // Badges de Estado de Proceso
                const estadoProcClass = doc.estado_proceso === 'verificado'
                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';

                tr.innerHTML = `
                    <!-- 1. ID & Código Documental -->
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <span class="font-mono text-[10px] text-gray-400 font-bold">#${escapeHTML(String(doc.id))}</span>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white text-xs block">${escapeHTML(doc.codigo_doc || 'SIN-CODIGO')}</span>
                                <span class="text-[10px] text-gray-400 uppercase font-semibold">${escapeHTML(doc.nivel_confidencialidad || 'interno')}</span>
                            </div>
                        </div>
                    </td>

                    <!-- 2. Archivo & Tipo -->
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0 text-xs">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div class="max-w-[200px]">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate" title="${escapeHTML(doc.nombre_original || '')}">${escapeHTML(doc.nombre_original || 'documento')}</p>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-800 text-gray-500 font-medium">${escapeHTML(doc.tipo_documento || 'General')}</span>
                            </div>
                        </div>
                    </td>

                    <!-- 3. Registrador & Dependencia -->
                    <td class="px-5 py-4 text-xs">
                        <span class="font-semibold text-gray-800 dark:text-gray-200 block">${escapeHTML(doc.registrado_por || 'Operador')}</span>
                        <span class="text-gray-400 text-[10px] flex items-center gap-1">
                            <i class="fa-solid fa-building text-[9px]"></i>
                            ${escapeHTML(doc.dependencia || 'Archivo Central')}
                        </span>
                    </td>

                    <!-- 4. Estados: Documental y Proceso -->
                    <td class="px-5 py-4">
                        <div class="flex flex-col gap-1 items-start">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${estadoDocClass}">
                                <i class="fa-solid ${doc.estado_documental === 'vigente' ? 'fa-circle-check text-[9px]' : 'fa-circle-xmark text-[9px]'}"></i>
                                ${escapeHTML(doc.estado_documental || 'vigente')}
                            </span>
                            <span class="text-[9px] px-2 py-0.5 rounded-md font-semibold ${estadoProcClass}">
                                ${escapeHTML(doc.estado_proceso || 'subido')}
                            </span>
                        </div>
                    </td>

                    <!-- 5. Fecha de Registro -->
                    <td class="px-5 py-4 text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                        ${escapeHTML(fechaFormateada)}
                    </td>

                    <!-- 6. Acciones: Descarga Segura -->
                    <td class="px-5 py-4 text-center">
                        <a href="php/api/documentos.php?action=download&id=${encodeURIComponent(doc.id)}" target="_blank"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-brand-50 hover:bg-brand-100 dark:bg-brand-950/60 dark:hover:bg-brand-900/60 text-brand-600 dark:text-brand-400 font-bold text-xs border border-brand-200 dark:border-brand-800/40 transition-colors shadow-sm"
                            title="Descargar archivo físico protegido">
                            <i class="fa-solid fa-download text-xs"></i>
                            <span class="hidden sm:inline">Descargar</span>
                        </a>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                        <div class="w-12 h-12 mx-auto mb-2 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-inbox"></i>
                        </div>
                        <p class="text-xs font-semibold">No se encontraron expedientes en MySQL.</p>
                        <p class="text-[11px] text-gray-400 mt-1">Utilice la pestaña "Centro OCR & Carga" para registrar nuevos documentos.</p>
                    </td>
                </tr>
            `;
        }
    } catch (err) {
        console.warn('Error al cargar documentos desde la BD:', err);
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-rose-500 text-xs">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    ${escapeHTML(err.message || 'No se pudo conectar con la base de datos MySQL (puerto 3307).')}
                </td>
            </tr>
        `;
    }
};

let searchDebounceTimer = null;
window.handleRepoFilter = function (query) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        // Ejecutar búsqueda remota contra MySQL cuando haya texto
        if (query.trim().length > 1) {
            cargarDocumentosDesdeBD(query);
        } else if (query.trim().length === 0) {
            cargarDocumentosDesdeBD('');
        }
    }, 400);
};

/**
 * Carga de logs de auditoría desde la base de datos (tabla auditoria_acciones)
 */
window.cargarAuditoriaDesdeBD = async function () {
    const container = document.getElementById('auditoria-logs-container');
    if (!container) return;

    try {
        const res = await apiFetch('/api/auditoria');
        const logs = res.data || [];

        if (Array.isArray(logs) && logs.length > 0) {
            container.innerHTML = '';
            logs.forEach(log => {
                const item = document.createElement('div');
                item.className = 'glass-card p-4 rounded-2xl border border-gray-200 dark:border-slate-800 flex items-center justify-between text-xs hover:border-brand-500/50 transition-colors animate-fade-in';
                
                let iconClass = 'fa-solid fa-shield text-indigo-500';
                if (log.accion === 'LOGIN') iconClass = 'fa-solid fa-right-to-bracket text-emerald-500';
                if (log.accion === 'LOGOUT') iconClass = 'fa-solid fa-arrow-right-from-bracket text-gray-400';
                if (log.accion === 'DOCUMENTO_REGISTRADO') iconClass = 'fa-solid fa-file-circle-plus text-brand-500';
                if (log.accion === 'DOCUMENTO_DESCARGADO') iconClass = 'fa-solid fa-file-arrow-down text-purple-500';
                if (log.accion === 'DOCUMENTO_ANULADO') iconClass = 'fa-solid fa-ban text-rose-500';

                const fechaLog = log.created_at ? log.created_at.substring(0, 19).replace('T', ' ') : '';
                const docRef = log.codigo_doc ? ` • Doc: <strong>${escapeHTML(log.codigo_doc)}</strong>` : '';

                item.innerHTML = `
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
                            <i class="${iconClass}"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white">
                                ${escapeHTML(log.accion)} ${docRef}
                            </p>
                            <p class="text-gray-400 text-[11px]">
                                Usuario: <span class="font-semibold text-gray-700 dark:text-gray-300">${escapeHTML(log.usuario_nombre || 'Sistema')}</span>
                                • IP: <span class="font-mono">${escapeHTML(log.direccion_ip || '127.0.0.1')}</span>
                            </p>
                        </div>
                    </div>
                    <span class="text-gray-400 font-mono text-[11px] shrink-0">${escapeHTML(fechaLog)}</span>
                `;
                container.appendChild(item);
            });
        } else {
            container.innerHTML = `
                <div class="p-8 text-center text-gray-400 text-xs">
                    No se registran eventos de auditoría en la base de datos.
                </div>
            `;
        }
    } catch (e) {
        console.warn('Error al cargar auditoría:', e);
        if (e.status === 403) {
            container.innerHTML = `
                <div class="p-6 text-center text-amber-500 bg-amber-50/50 dark:bg-amber-950/20 rounded-2xl border border-amber-200 dark:border-amber-800/40 text-xs">
                    <i class="fa-solid fa-lock mr-1.5"></i> Su rol actual no dispone de permisos para consultar la bitácora de auditoría (AUDITORIA_CONSULTAR).
                </div>
            `;
        }
    }
};

/**
 * Carga de métricas reales del resumen operativo desde MySQL
 */
window.cargarMetricasResumen = async function () {
    try {
        const res = await apiFetch('/api/resumen');
        if (res.ok && res.data?.kpis) {
            const k = res.data.kpis;
            const docEl = document.getElementById('kpi-total-docs');
            const usrEl = document.getElementById('kpi-total-users');
            const tipEl = document.getElementById('kpi-total-types');
            const audEl = document.getElementById('kpi-total-audits');

            if (docEl) docEl.textContent = k.total_documentos;
            if (usrEl) usrEl.textContent = k.total_usuarios;
            if (tipEl) tipEl.textContent = k.total_tipos;
            if (audEl) audEl.textContent = k.total_auditorias;
        }
    } catch (e) {
        console.warn('Error al cargar métricas de resumen:', e);
    }
};
