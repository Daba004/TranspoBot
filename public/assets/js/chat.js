document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatContainer = document.getElementById('chat-container');

    // API URL de FastAPI
    const API_URL = 'http://localhost:8000/ask';

    function addMessage(text, isUser = false, data = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex gap-3 ${isUser ? 'flex-row-reverse ml-auto' : ''} max-w-[90%] opacity-0 translate-y-2 transition-all duration-300`;
        
        let contentHtml = '';
        if (isUser) {
            contentHtml = `
                <div class="bg-blue-600 text-white p-3 rounded-2xl rounded-tr-none text-sm shadow-sm">
                    ${text}
                </div>
            `;
        } else {
            contentHtml = `
                <div class="w-8 h-8 rounded-full bg-slate-100 flex-shrink-0 flex items-center justify-center text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="space-y-3 flex-1">
                    <div class="bg-slate-100 p-3 rounded-2xl rounded-tl-none text-sm text-slate-700 shadow-sm">
                        ${text}
                    </div>
                </div>
            `;
        }

        messageDiv.innerHTML = contentHtml;
        chatContainer.appendChild(messageDiv);

        // Affichage des données sous forme de tableau si présentes
        if (data && data.length > 0) {
            const tableDiv = document.createElement('div');
            tableDiv.className = "mt-3 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm scale-95 opacity-0 transition-all duration-500 delay-150";
            
            let tableHtml = `<div class="overflow-x-auto"><table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase font-bold">
                    <tr>`;
            
            // En-têtes
            Object.keys(data[0]).forEach(key => {
                tableHtml += `<th class="px-4 py-2 border-b border-slate-100">${key}</th>`;
            });
            
            tableHtml += `</tr></thead><tbody class="divide-y divide-slate-50">`;
            
            // Lignes
            data.forEach(row => {
                tableHtml += `<tr>`;
                Object.values(row).forEach(val => {
                    tableHtml += `<td class="px-4 py-2 text-slate-600">${val !== null ? val : '-'}</td>`;
                });
                tableHtml += `</tr>`;
            });
            
            tableHtml += `</tbody></table></div>`;
            tableDiv.innerHTML = tableHtml;
            messageDiv.querySelector('.space-y-3').appendChild(tableDiv);
            
            setTimeout(() => {
                tableDiv.classList.remove('scale-95', 'opacity-0');
            }, 50);
        }

        // Animation d'entrée
        setTimeout(() => {
            messageDiv.classList.remove('opacity-0', 'translate-y-2');
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }, 10);
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        // Ajouter message utilisateur
        addMessage(message, true);
        chatInput.value = '';

        // Indicateur de chargement
        const loadingDiv = document.createElement('div');
        loadingDiv.className = "flex gap-3 items-center text-slate-400 text-xs animate-pulse p-2";
        loadingDiv.innerHTML = `<div class="w-4 h-4 border-2 border-slate-200 border-t-blue-500 rounded-full animate-spin"></div> Reflexion en cours...`;
        chatContainer.appendChild(loadingDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });

            const result = await response.json();
            chatContainer.removeChild(loadingDiv);

            if (result.success) {
                if (result.data && result.data.length > 0) {
                    addMessage(`J'ai trouvé ${result.data.length} résultat(s) pour votre demande :`, false, result.data);
                } else if (result.data && result.data.length === 0) {
                    addMessage("Aucun résultat ne correspond à votre recherche dans la base de données.", false);
                } else {
                    addMessage("C'est fait ! La requête SQL a été exécutée avec succès.", false);
                }
            } else {
                addMessage("Désolé, j'ai rencontré une erreur : " + (result.error || "Problème de communication avec le serveur."), false);
            }
        } catch (error) {
            chatContainer.removeChild(loadingDiv);
            addMessage("Erreur : Impossible de contacter le serveur AI. Assurez-vous que le micro-service Python est lancé sur le port 8000.", false);
            console.error("Fetch error:", error);
        }
    });
});
