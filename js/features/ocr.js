/* ==================================================================
   DocuFlow AI — DASHBOARD: OCR, REVISIÓN/EDICIÓN Y ALMACENAMIENTO
   ------------------------------------------------------------------
   Procesamiento asistido demostrativo y guardado real en MySQL con FormData
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 8. DASHBOARD: OCR, REVISIÓN/EDICIÓN Y ALMACENAMIENTO REAL
// ═══════════════════════════════════════════════════════

let selectedFileObj = null;

window.handleFileInput = (e) => {
    if (e.target.files && e.target.files[0]) {
        updateSelectedFile(e.target.files[0]);
    }
};

window.handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('drop-zone');
    if (zone) zone.classList.remove('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        updateSelectedFile(e.dataTransfer.files[0]);
    }
};

window.handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('drop-zone');
    if (zone) zone.classList.add('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
};

window.handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    const zone = document.getElementById('drop-zone');
    if (zone) zone.classList.remove('border-brand-500', 'bg-brand-50/50', 'dark:bg-slate-800/80');
};

function updateSelectedFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
    if (!permitidas.includes(ext)) {
        showToast('HTTP 400: Extensión no permitida. Solo se aceptan PDF, JPG, PNG y DOCX.', 'error', 400);
        return;
    }

    const maxBytes = 20 * 1024 * 1024; // 20 MB
    if (file.size > maxBytes) {
        showToast('HTTP 400: El archivo supera los 20 MB máximos permitidos.', 'error', 400);
        return;
    }

    selectedFileObj = file;

    const sizeMb = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
    const loadedFilename = document.getElementById('loaded-filename');
    const loadedFilesize = document.getElementById('loaded-filesize');
    const previewCard = document.getElementById('file-preview-card');
    const dropTitle = document.getElementById('drop-title');
    const badge = document.getElementById('ocr-status-badge');

    if (loadedFilename) loadedFilename.textContent = file.name;
    if (loadedFilesize) loadedFilesize.textContent = `${sizeMb} • Listo para procesar`;
    if (previewCard) previewCard.classList.remove('hidden-view');
    if (dropTitle) {
        dropTitle.innerHTML = `Archivo seleccionado: <span class="text-brand-600 dark:text-brand-400 font-bold">${escapeHTML(file.name)}</span>`;
    }
    if (badge) {
        badge.textContent = 'Archivo preparado';
        badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400';
    }

    showToast(`Archivo cargado: ${file.name}`, 'info');
}

/**
 * Ejecuta el escáner demostrativo y presenta los metadatos dentro de inputs editables
 * para que el usuario los revise y confirme antes de persistirlos en MySQL.
 */
window.triggerProcessDocument = async function (forceError = false) {
    const btnProcess = document.getElementById('btn-process');
    const laser = document.getElementById('laser-line');
    const empty = document.getElementById('metadata-empty');
    const skel = document.getElementById('metadata-skeletons');
    const res = document.getElementById('metadata-results');
    const badge = document.getElementById('ocr-status-badge');

    if (!selectedFileObj) {
        showToast('HTTP 400 Bad Request: Selecciona o arrastra un archivo primero.', 'error', 400);
        return;
    }

    btnProcess.disabled = true;
    if (laser) laser.classList.remove('hidden-view');
    if (empty) empty.classList.add('hidden-view');
    if (res) res.classList.add('hidden-view');
    if (skel) skel.classList.remove('hidden-view');

    if (badge) {
        badge.textContent = 'Analizando OCR...';
        badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400 animate-pulse';
    }

    showToast('Iniciando extracción asistida OCR...', 'info');

    try {
        const r = await apiFetch('/api/documents/process', {
            method: 'POST',
            body: { filename: selectedFileObj.name },
            forceError500: forceError
        });

        if (r.ok && r.status === 200) {
            const m = r.data.metadata;
            if (badge) {
                badge.textContent = `200 OK (${m.confianza}% confianza)`;
                badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400';
            }

            // Inyectar campos editables respetando exactamente los nombres del modelo relacional
            res.innerHTML = `
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/40 text-[11px] text-amber-700 dark:text-amber-400">
                        <span class="flex items-center gap-1.5 font-semibold">
                            <i class="fa-solid fa-pen-to-square"></i> Extracción asistida (Demostrativa) • Revisa y corrige antes de guardar en MySQL:
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Tipo de Documento</label>
                            <select id="edit-meta-tipo" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-1 focus:ring-brand-500">
                                <option value="1" ${m.tipo === 'Oficio' ? 'selected' : ''}>Oficio</option>
                                <option value="2" ${m.tipo === 'Memorando' ? 'selected' : ''}>Memorando</option>
                                <option value="3" ${m.tipo === 'Informe Técnico' ? 'selected' : ''}>Informe Técnico</option>
                            </select>
                        </div>
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Código Documental (codigo_doc)</label>
                            <input id="edit-meta-codigo" type="text" value="${escapeHTML(m.codigo)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-bold text-brand-600 dark:text-brand-400 outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Fecha Emisión (fecha_emision)</label>
                            <input id="edit-meta-fecha" type="date" value="${escapeHTML(m.fecha)}" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-semibold text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Confidencialidad</label>
                            <select id="edit-meta-confidencialidad" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs font-semibold text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                                <option value="interno" selected>Interno</option>
                                <option value="publico">Público</option>
                                <option value="confidencial">Confidencial</option>
                                <option value="restringido">Restringido</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Remitente</label>
                            <input id="edit-meta-remitente" type="text" value="Área Administrativa" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Destinatario</label>
                            <input id="edit-meta-destinatario" type="text" value="Archivo Central" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs text-gray-800 dark:text-gray-200 outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>

                    <div class="p-2.5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Asunto (metadatos_documento.asunto)</label>
                        <textarea id="edit-meta-asunto" rows="2" class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-1.5 text-xs text-gray-700 dark:text-gray-300 outline-none focus:ring-1 focus:ring-brand-500 leading-relaxed">${escapeHTML(m.asunto)}</textarea>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase text-gray-400 block mb-1.5">Etiquetas Asistidas</span>
                        <div class="flex flex-wrap gap-1.5">
                            ${m.tags.map(t => `<span class="px-2.5 py-1 rounded-lg bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 text-[10px] font-bold border border-brand-200 dark:border-brand-800/60">${escapeHTML(t)}</span>`).join('')}
                        </div>
                    </div>

                    <!-- Botón para Guardar Realmente con FormData -->
                    <button id="btn-save-doc" onclick="confirmAndSaveDocument()" 
                        class="w-full mt-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all flex items-center justify-center gap-2 text-xs">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Confirmar y Guardar en MySQL</span>
                    </button>
                </div>
            `;
            res.classList.remove('hidden-view');
            showToast('Extracción completada. Revisa los datos y confirma el guardado en MySQL (HTTP 200 OK)', 'success', 200);
        }
    } catch (err) {
        if (empty) empty.classList.remove('hidden-view');
        if (badge) {
            badge.textContent = `Error ${err.status || 500}`;
            badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400';
        }
        showToast(err.message || 'Error en el procesamiento OCR.', 'error', err.status || 500);
    } finally {
        if (laser) laser.classList.add('hidden-view');
        if (skel) skel.classList.add('hidden-view');
        btnProcess.disabled = false;
    }
};

/**
 * Envía el archivo físico y los metadatos editados mediante FormData hacia PHP PDO + MySQL.
 */
window.confirmAndSaveDocument = async function () {
    if (!selectedFileObj) {
        showToast('HTTP 400 Bad Request: No hay ningún archivo seleccionado.', 'error', 400);
        return;
    }

    const tipoDocId        = document.getElementById('edit-meta-tipo')?.value || '1';
    const codigoDoc        = document.getElementById('edit-meta-codigo')?.value.trim();
    const fechaEmision     = document.getElementById('edit-meta-fecha')?.value;
    const asunto           = document.getElementById('edit-meta-asunto')?.value.trim();
    const remitente        = document.getElementById('edit-meta-remitente')?.value.trim();
    const destinatario     = document.getElementById('edit-meta-destinatario')?.value.trim();
    const confidencialidad = document.getElementById('edit-meta-confidencialidad')?.value || 'interno';

    if (!codigoDoc) {
        showToast('El código documental es obligatorio.', 'error', 400);
        return;
    }

    const btn = document.getElementById('btn-save-doc');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Subiendo archivo y guardando en MySQL...';
    }

    // Construcción de FormData para enviar archivo físico y metadatos
    const formData = new FormData();
    formData.append('archivo', selectedFileObj);
    formData.append('codigo_doc', codigoDoc);
    formData.append('tipo_doc_id', tipoDocId);
    formData.append('fecha_emision', fechaEmision);
    formData.append('asunto', asunto);
    formData.append('remitente', remitente);
    formData.append('destinatario', destinatario);
    formData.append('nivel_confidencialidad', confidencialidad);
    formData.append('nombre_original', selectedFileObj.name);

    // CSRF Token
    const csrfToken = localStorage.getItem(CONFIG.STORAGE_KEYS.CSRF) || '';
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }

    try {
        const response = await apiFetch('/api/documents/store', {
            method: 'POST',
            body: formData
        });

        if (response.ok && response.status === 201) {
            showToast(`¡Documento ${escapeHTML(codigoDoc)} guardado exitosamente en MySQL! (HTTP 201)`, 'success', 201);

            // Refrescar Repositorio, Métricas y Auditoría desde MySQL
            if (typeof cargarDocumentosDesdeBD === 'function') cargarDocumentosDesdeBD();
            if (typeof cargarMetricasResumen === 'function') cargarMetricasResumen();
            if (typeof cargarAuditoriaDesdeBD === 'function') cargarAuditoriaDesdeBD();

            // Limpiar área de trabajo y archivo
            selectedFileObj = null;
            const fileInput = document.getElementById('file-input');
            if (fileInput) fileInput.value = '';

            const previewCard = document.getElementById('file-preview-card');
            if (previewCard) previewCard.classList.add('hidden-view');

            const res = document.getElementById('metadata-results');
            if (res) {
                res.innerHTML = `
                    <div class="p-6 text-center space-y-3 bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-2xl animate-fade-in">
                        <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h5 class="text-sm font-bold text-gray-900 dark:text-white">Expediente Almacenado Físicamente</h5>
                        <p class="text-xs text-gray-500 dark:text-gray-400">El registro y archivo fueron persistidos en MySQL (puerto 3307) y en la carpeta protegida uploads/.</p>
                        <button onclick="switchDashboardTab('repositorio')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white font-semibold text-xs hover:bg-brand-500 transition shadow">
                            <i class="fa-solid fa-folder-tree"></i>
                            <span>Ver en el Repositorio</span>
                        </button>
                    </div>
                `;
            }

            const dropTitle = document.getElementById('drop-title');
            if (dropTitle) {
                dropTitle.innerHTML = `Arrastra tu archivo aquí o <span class="text-brand-600 dark:text-brand-400 underline">haz clic para explorar</span>`;
            }

            const badge = document.getElementById('ocr-status-badge');
            if (badge) {
                badge.textContent = 'Guardado en MySQL 3307';
                badge.className = 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400';
            }
        }
    } catch (err) {
        console.error('[Error al guardar documento]', err);
        showToast(err.message || 'Error al persistir el documento en la base de datos.', 'error', err.status || 500);

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span>Reintentar Guardado en MySQL</span>';
        }
    }
};

window.triggerForcedError500 = function () {
    triggerProcessDocument(true);
};
