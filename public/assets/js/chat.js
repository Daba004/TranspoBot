document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatContainer = document.getElementById('chat-container');
    const fabButton = document.getElementById('chatbot-fab');
    const chatPanel = document.getElementById('chatbot-panel');
    const closeButton = document.getElementById('chatbot-close');
    
    // Status indicators
    const statusDots = document.querySelectorAll('.ai-status-dot');
    const statusLabels = document.querySelectorAll('.ai-status-label');

    // API URL de FastAPI
    // API URL de FastAPI (vide en production car proxifié par Apache)
    const API_URL = window.location.origin;
    const ASK_URL = `${API_URL}/ask`;

    function addMessage(text, isUser = false, data = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex gap-3 ${isUser ? 'flex-row-reverse ml-auto' : ''} max-w-[90%] opacity-0 translate-y-2 transition-all duration-300`;
        
        let contentHtml = '';
        if (isUser) {
            contentHtml = `
                <div class="bg-gradient-to-br from-amber-300 to-amber-500 text-emerald-950 font-medium px-4 py-2.5 rounded-[1.25rem] rounded-tr-sm shadow-md shadow-amber-500/20 break-words whitespace-pre-wrap max-w-full border border-amber-200/50">
                    ${text}
                </div>
            `;
        } else {
            contentHtml = `
                <div class="w-8 h-8 rounded-[0.8rem] bg-gradient-to-br from-emerald-700 to-emerald-900 flex-shrink-0 flex items-center justify-center text-emerald-300 border border-emerald-600/50 shadow-md shadow-emerald-900/30 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8V4H8" />
                        <rect width="16" height="12" x="4" y="8" rx="2" />
                        <path d="M2 14h2" />
                        <path d="M20 14h2" />
                        <path d="M15 13v2" />
                        <path d="M9 13v2" />
                    </svg>
                </div>
                <div class="space-y-3 flex-1 min-w-0">
                    <div class="bg-emerald-900/40 backdrop-blur-md px-4 py-3 rounded-[1.25rem] rounded-tl-sm text-sm text-emerald-50 border border-emerald-700/50 shadow-md leading-relaxed break-words whitespace-pre-wrap max-w-full ring-1 ring-inset ring-white/5">
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
            tableDiv.className = "mt-3 bg-emerald-950/60 border border-emerald-800/60 rounded-xl overflow-hidden shadow-lg scale-95 opacity-0 transition-all duration-500 delay-150 backdrop-blur-sm";
            
            let tableHtml = `<div class="overflow-x-auto"><table class="w-full text-xs text-left">
                <thead class="bg-emerald-900/50 text-emerald-200 uppercase tracking-wider font-bold">
                    <tr>`;
            
            // En-têtes
            Object.keys(data[0]).forEach(key => {
                tableHtml += `<th class="px-3 py-2.5 border-b border-emerald-800/50">${key}</th>`;
            });
            
            tableHtml += `</tr></thead><tbody class="divide-y divide-emerald-800/30">`;
            
            // Lignes
            data.forEach(row => {
                tableHtml += `<tr class="hover:bg-emerald-800/30 transition-colors">`;
                Object.values(row).forEach(val => {
                    tableHtml += `<td class="px-3 py-2 text-emerald-100/90">${val !== null ? val : '-'}</td>`;
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

    window.askSuggestedQuestion = function(msg) {
        sendMessage(msg);
    };

    async function sendMessage(message) {
        if (!message) return;

        // Ajouter message utilisateur
        addMessage(message, true);
        chatInput.value = '';

        // Indicateur de chargement
        const loadingDiv = document.createElement('div');
        loadingDiv.className = "flex gap-3 items-center text-emerald-400/80 text-xs animate-pulse px-4 py-2 ml-10";
        loadingDiv.innerHTML = `<div class="w-4 h-4 border-2 border-emerald-900/50 border-t-emerald-400 rounded-full animate-spin"></div> Analyse en cours...`;
        chatContainer.appendChild(loadingDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;

        try {
            const response = await fetch(ASK_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });

            const result = await response.json();
            chatContainer.removeChild(loadingDiv);

            if (result.success) {
                if (result.reponse) {
                    addMessage(result.reponse, false, null);
                } else if (result.data && result.data.length > 0) {
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
    }

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const message = chatInput.value.trim();
        sendMessage(message);
    });

    // Check API Status and Auto-start if unreachable
    async function checkApiStatus() {
        try {
            const response = await fetch(API_URL);
            if (response.ok) {
                // Connecté
                window._isStartingAi = false;
                statusDots.forEach(dot => {
                    dot.classList.remove('bg-slate-400', 'bg-rose-500');
                    dot.classList.add('bg-green-500', 'animate-pulse');
                });
                statusLabels.forEach(label => {
                    label.textContent = "IA Connectée";
                    label.classList.remove('text-slate-400', 'text-rose-500');
                    label.classList.add('text-emerald-400');
                });
            } else {
                throw new Error("API not ok");
            }
        } catch (error) {
            // Déconnecté
            statusDots.forEach(dot => {
                dot.classList.remove('bg-green-500', 'bg-slate-400', 'animate-pulse');
                dot.classList.add('bg-rose-500');
            });
            statusLabels.forEach(label => {
                label.textContent = (window._isStartingAi) ? "Démarrage IA..." : "IA Déconnectée";
                label.classList.remove('text-emerald-400', 'text-slate-400');
                label.classList.add('text-rose-500');
            });

            // Auto-start mechanism
            if (!window._isStartingAi) {
                window._isStartingAi = true;
                console.log("[TranspoBot] IA par défaut déconnectée. Tentative de démarrage...");
                fetch('actions/auto_start.php')
                    .then(res => res.json())
                    .then(data => {
                        console.log("[TranspoBot] Statut auto-start:", data.message);
                    })
                    .catch(err => console.error("[TranspoBot] Erreur auto-start:", err));
            }
        }
    }

    // Toggle Chatbot Panel
    function toggleChatbot() {
        if (chatPanel.classList.contains('scale-0')) {
            // Open
            chatPanel.classList.remove('scale-0', 'opacity-0', 'pointer-events-none');
            chatPanel.classList.add('scale-100', 'opacity-100', 'pointer-events-auto');
            fabButton.classList.add('scale-0'); // Hide FAB
            setTimeout(() => chatInput.focus(), 300);
            
            // Check status when opening
            checkApiStatus();
        } else {
            // Close
            chatPanel.classList.remove('scale-100', 'opacity-100', 'pointer-events-auto');
            chatPanel.classList.add('scale-0', 'opacity-0', 'pointer-events-none');
            fabButton.classList.remove('scale-0'); // Show FAB
        }
    }

    if(fabButton) fabButton.addEventListener('click', toggleChatbot);
    if(closeButton) closeButton.addEventListener('click', toggleChatbot);

    // Initial status check
    checkApiStatus();
    // Check status periodically (every 15s)
    setInterval(checkApiStatus, 15000);
});
