/* ==================================================================
   DocuFlow AI — CHATBOT RAG CONTEXTUAL (MySQL 3307)
   ------------------------------------------------------------------
   Consultas contextuales sobre chunks_documento y metadatos_documento
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 10. CHATBOT RAG CONTEXTUAL
// ═══════════════════════════════════════════════════════

window.toggleChatbot = () => {
    const win = document.getElementById('chat-window');
    const btn = document.getElementById('chat-toggle-btn');
    const icon = document.getElementById('chat-toggle-icon');

    if (!win) return;
    const isHidden = win.classList.contains('hidden-view');
    if (isHidden) {
        win.classList.remove('hidden-view');
        if (btn) btn.classList.remove('chat-btn-pulse');
        if (icon) icon.className = 'fa-solid fa-xmark text-lg';
        const inp = document.getElementById('chat-input');
        if (inp) inp.focus();
    } else {
        win.classList.add('hidden-view');
        if (btn) btn.classList.add('chat-btn-pulse');
        if (icon) icon.className = 'fa-solid fa-robot text-xl';
    }
};

window.handleChatSubmit = async (e) => {
    e.preventDefault();
    const inp = document.getElementById('chat-input');
    const query = inp.value.trim();
    if (!query) return;

    const cont = document.getElementById('chat-messages');
    if (!cont) return;

    // Mensaje del usuario
    cont.innerHTML += `
        <div class="flex justify-end animate-fade-in">
            <div class="bg-gradient-to-r from-brand-600 to-purple-600 text-white p-3 rounded-2xl rounded-br-none max-w-[85%] leading-relaxed shadow-sm">
                ${escapeHTML(query)}
            </div>
        </div>
    `;
    inp.value = '';
    cont.scrollTop = cont.scrollHeight;

    // Indicador de búsqueda en MySQL
    const typingId = 'typing-' + Date.now();
    cont.innerHTML += `
        <div id="${typingId}" class="flex gap-2 items-center text-gray-400 text-[11px] animate-pulse">
            <i class="fa-solid fa-circle-notch fa-spin text-brand-500"></i> Consultando base de datos en MySQL (puerto 3307)...
        </div>
    `;
    cont.scrollTop = cont.scrollHeight;

    try {
        const r = await apiFetch('/api/chat/rag', {
            method: 'POST',
            body: { query }
        });

        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        cont.innerHTML += `
            <div class="flex gap-2.5 items-start animate-fade-in">
                <div class="w-7 h-7 rounded-lg bg-brand-100 dark:bg-brand-950 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-robot text-xs"></i>
                </div>
                <div class="bg-gray-100 dark:bg-slate-800 p-3 rounded-2xl rounded-tl-sm text-gray-700 dark:text-gray-200 max-w-[85%] leading-relaxed shadow-sm">
                    ${r.data.answer}
                </div>
            </div>
        `;
    } catch (err) {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        cont.innerHTML += `
            <div class="text-rose-500 text-[11px] p-2.5 bg-rose-50 dark:bg-rose-950/40 rounded-xl border border-rose-200 dark:border-rose-900/40">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>
                ${escapeHTML(err.message || 'No se pudo consultar el servicio RAG.')}
            </div>
        `;
    }
    cont.scrollTop = cont.scrollHeight;
};
