/* ==================================================================
   DocuFlow AI — CHATBOT RAG CONTEXTUAL (Respuestas Oficiales del PDF)
   ------------------------------------------------------------------
   Script clásico: las funciones y constantes de nivel superior quedan
   disponibles de forma global para el resto de archivos y para los
   atributos onclick/oninput del HTML.
   ================================================================== */
'use strict';

// ═══════════════════════════════════════════════════════
// 10. CHATBOT RAG CONTEXTUAL (Respuestas Oficiales del PDF)
// ═══════════════════════════════════════════════════════
window.toggleChatbot = () => {
    const win = document.getElementById('chat-window');
    const btn = document.getElementById('chat-toggle-btn');
    const icon = document.getElementById('chat-toggle-icon');

    const isHidden = win.classList.contains('hidden-view');
    if (isHidden) {
        win.classList.remove('hidden-view');
        btn.classList.remove('chat-btn-pulse');
        icon.className = 'fa-solid fa-xmark text-lg';
        document.getElementById('chat-input').focus();
    } else {
        win.classList.add('hidden-view');
        btn.classList.add('chat-btn-pulse');
        icon.className = 'fa-solid fa-robot text-xl';
    }
};

window.handleChatSubmit = async (e) => {
    e.preventDefault();
    const inp = document.getElementById('chat-input');
    const query = inp.value.trim();
    if (!query) return;

    const cont = document.getElementById('chat-messages');

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

    // Indicador de "buscando en base vectorial RAG"
    const typingId = 'typing-' + Date.now();
    cont.innerHTML += `
        <div id="${typingId}" class="flex gap-2 items-center text-gray-400 text-[11px] animate-pulse">
            <i class="fa-solid fa-circle-notch fa-spin text-brand-500"></i> Consultando base vectorial RAG (fakeFetch)...
        </div>
    `;
    cont.scrollTop = cont.scrollHeight;

    try {
        const r = await fakeFetch('/api/chat/rag', {
            method: 'POST',
            body: { query }
        });

        document.getElementById(typingId)?.remove();

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
        document.getElementById(typingId)?.remove();
        cont.innerHTML += `
            <div class="text-rose-500 text-[11px] p-2 bg-rose-50 dark:bg-rose-950/40 rounded-xl">
                Error al consultar el servicio RAG (HTTP 500).
            </div>
        `;
    }
    cont.scrollTop = cont.scrollHeight;
};
