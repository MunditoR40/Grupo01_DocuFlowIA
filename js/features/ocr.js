/* ==================================================================
   DocuFlow AI — DASHBOARD: OCR, REVISIÓN/EDICIÓN Y ALMACENAMIENTO
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 8. DASHBOARD: OCR, REVISIÓN/EDICIÓN Y ALMACENAMIENTO
// ═══════════════════════════════════════════════════════
let currentFile = { name: 'OFICIO-045-2024-UNS.pdf', size: '2.4 MB' };

window.handleFileInput = (e) => {
    if (e.target.files && e.target.files[0]) updateSelectedFile(e.target.files[0]);
};

window.handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('drop-zone').classList.remove('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) updateSelectedFile(e.dataTransfer.files[0]);
};

window.handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('drop-zone').classList.add('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
};

window.handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('drop-zone').classList.remove('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
};

function updateSelectedFile(file) {
    const sizeMb = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
    currentFile = { name: file.name, size: sizeMb };

    document.getElementById('loaded-filename').textContent = file.name;
    document.getElementById('loaded-filesize').textContent = `${sizeMb} • Listo para procesar`;
    document.getElementById('file-preview-card').classList.remove('hidden-view');
    document.getElementById('drop-title').innerHTML = `Archivo seleccionado: <span class="text-brand-600 dark:text-brand-400 font-bold">${file.name}</span>`;
    showToast(`Archivo cargado: ${file.name}`, 'info');
}

/**
 * Ejecuta el escáner láser y muestra los metadatos dentro de inputs editables
 * para que el usuario pueda revisarlos y corregirlos (Pág. 2 y 3 del PDF).
 */
window.triggerProcessDocument = async function (forceError = false) {
    const btnProcess = document.getElementById('btn-process');
    const laser = document.getElementById('laser-line');
    const empty = document.getElementById('metadata-empty');
    const skel = document.getElementById('metadata-skeletons');
    const res = document.getElementById('metadata-results');
    const badge = document.getElementById('ocr-status-badge');

    // Validación de archivo
    if (!currentFile || !currentFile.name) {
        showToast('HTTP 400 Bad Request: Selecciona o arrastra un archivo primero.', 'error', 400);
        return;
    }

    // Bloqueo de concurrencia
    btnProcess.disabled = true;
    laser.classList.remove('hidden-view');
    empty.classList.add('hidden-view');
    res.classList.add('hidden-view');
    skel.classList.remove('hidden-view');

    badge.textContent = 'Escaneando OCR...';
    badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400 animate-pulse';

    showToast('Iniciando escaneo láser y extracción OCR (fakeFetch 2s)...', 'info');

    try {
        const r = await fakeFetch('/api/documents/process', {
            method: 'POST',
            body: { filename: currentFile.name },
            forceError500: forceError
        });

        if (r.ok && r.status === 200) {
            const m = r.data.metadata;
            badge.textContent = `200 OK (${m.confianza}%)`;
            badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400';

            // Inyectar campos editables con botón de confirmación de almacenamiento
            res.innerHTML = `
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/40 text-[11px] text-amber-700 dark:text-amber-400">
                        <span class="flex items-center gap-1.5 font-semibold"><i class="fa-solid fa-pen-to-square"></i> Puedes revisar y corregir los datos antes de guardar:</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Tipo de Documento</label>
                            <input id="edit-meta-tipo" type="text" value="${escapeHTML(m.tipo)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Código Oficial</label>
                            <input id="edit-meta-codigo" type="text" value="${escapeHTML(m.codigo)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-bold text-brand-600 dark:text-brand-400 outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>
                    <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Fecha de Emisión</label>
                        <input id="edit-meta-fecha" type="text" value="${escapeHTML(m.fecha)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-semibold text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                    </div>
                    <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Asunto Sintetizado</label>
                        <textarea id="edit-meta-asunto" rows="2" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs text-gray-700 dark:text-gray-300 outline-none focus:ring-1 focus:ring-brand-500 leading-relaxed">${escapeHTML(m.asunto)}</textarea>
                    </div>
                    <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Dependencia Responsable</label>
                        <input id="edit-meta-dependencia" type="text" value="${escapeHTML(m.dependencia)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-semibold text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-gray-400 block mb-1.5">Etiquetas Semánticas IA</span>
                        <div class="flex flex-wrap gap-1.5">
                            ${m.tags.map(t => `<span class="px-2.5 py-1 rounded-lg bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 text-[10px] font-bold border border-brand-200 dark:border-brand-800/60">${t}</span>`).join('')}
                        </div>
                    </div>

                    <!-- Botón Requerido por el PDF: Confirmar y Guardar en Repositorio -->
                    <button id="btn-save-doc" onclick="confirmAndSaveDocument()" 
                        class="w-full mt-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all flex items-center justify-center gap-2 text-xs">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Confirmar y Guardar en Repositorio</span>
                    </button>
                </div>
            `;
            res.classList.remove('hidden-view');
            showToast('Extracción completada. Revisa los datos y confirma el guardado (HTTP 200 OK)', 'success', 200);
        }
    } catch (err) {
        empty.classList.remove('hidden-view');
        badge.textContent = 'Error 500';
        badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400';
        showToast(err.data?.message || 'HTTP 500 Internal Server Error en OCR.', 'error', 500);
    } finally {
        laser.classList.add('hidden-view');
        skel.classList.add('hidden-view');
        btnProcess.disabled = false;
    }
};

/**
 * Almacena el documento en el repositorio vectorial y actualiza auditoría y KPI (Pág. 3 del PDF).
 */
window.confirmAndSaveDocument = async function () {
    const tipo = document.getElementById('edit-meta-tipo')?.value || 'Oficio Administrativo';
    const codigo = document.getElementById('edit-meta-codigo')?.value || 'OFICIO-045-2024-UNS';
    const fecha = document.getElementById('edit-meta-fecha')?.value || '15 de Octubre de 2024';
    const asunto = document.getElementById('edit-meta-asunto')?.value || '';
    const dependencia = document.getElementById('edit-meta-dependencia')?.value || 'RRHH — UNS';

    const btn = document.getElementById('btn-save-doc');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Almacenando en Repositorio...';
    }

    try {
        const response = await fakeFetch('/api/documents/store', {
            method: 'POST',
            body: { tipo, codigo, fecha, asunto, dependencia, filename: currentFile.name }
        });

        if (response.ok) {
            // 1. Inyectar nueva fila en la tabla del Repositorio RAG
            const tableBody = document.getElementById('repo-table-body');
            if (tableBody) {
                const newRow = document.createElement('tr');
                newRow.className = 'repo-row bg-emerald-50/50 dark:bg-emerald-950/20 hover:bg-gray-50 dark:hover:bg-slate-800/30 transition-colors animate-fade-in';
                newRow.dataset.search = `${codigo} ${tipo} ${dependencia}`;
                newRow.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-[10px]">
                                PDF
                            </div>
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    ${escapeHTML(codigo)}
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-500 text-white font-bold">Nuevo</span>
                                </p>
                                <p class="text-[10px] text-gray-400">Recién guardado • ${escapeHTML(dependencia)}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-full bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 font-bold">${escapeHTML(tipo)}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-16 h-1.5 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 w-[100%]"></div>
                            </div>
                            <span class="font-bold text-emerald-500">100% OK</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button onclick="showToast('Visualizando ${escapeHTML(codigo)}', 'info')" class="p-2 text-gray-400 hover:text-brand-500 transition-colors"><i class="fa-solid fa-eye"></i></button>
                        <button onclick="showToast('Descargando copia cifrada', 'info')" class="p-2 text-gray-400 hover:text-brand-500 transition-colors"><i class="fa-solid fa-download"></i></button>
                    </td>
                `;
                tableBody.prepend(newRow);
            }

            // 2. Inyectar log en Auditoría & Logs
            const auditContainer = document.querySelector('#tab-auditoria .space-y-3');
            if (auditContainer) {
                const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
                const auditCard = document.createElement('div');
                auditCard.className = 'glass-card p-4 rounded-2xl border border-emerald-500/40 bg-emerald-50/20 dark:bg-emerald-950/20 flex items-center justify-between text-xs hover:border-brand-500/50 transition-colors animate-fade-in';
                auditCard.innerHTML = `
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white">Documento Confirmado y Guardado</p>
                            <p class="text-gray-400">Expediente: ${escapeHTML(codigo)} • Usuario: admin@docuflow.ai • Status: 201 Created</p>
                        </div>
                    </div>
                    <span class="text-gray-400 font-mono">${now}</span>
                `;
                auditContainer.prepend(auditCard);
            }

            // 3. Incrementar KPI de Total Procesados
            const kpiTotal = document.getElementById('kpi-total-docs');
            if (kpiTotal) {
                const count = parseInt(kpiTotal.textContent.replace(/,/g, '')) || 1284;
                kpiTotal.textContent = (count + 1).toLocaleString();
            }

            // 4. Cambiar estado del botón
            if (btn) {
                btn.className = 'w-full mt-3 bg-gray-100 dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 font-bold py-3.5 rounded-2xl border border-emerald-500/30 flex items-center justify-center gap-2 text-xs cursor-default';
                btn.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i> Almacenado con Éxito en Repositorio';
            }

            showToast(`HTTP 201 Created: Expediente ${codigo} guardado en el repositorio.`, 'success', 201);
        }
    } catch (err) {
        showToast('Error al almacenar documento.', 'error', 500);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Confirmar y Guardar en Repositorio';
        }
    }
};

window.triggerForcedError500 = () => triggerProcessDocument(true);
