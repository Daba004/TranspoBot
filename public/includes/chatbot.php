<!-- Floating Action Button -->
<button id="chatbot-fab" class="fixed bottom-6 right-6 w-16 h-16 bg-amber-400 rounded-full shadow-2xl shadow-amber-400/40 flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50 group">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-950 group-hover:rotate-12 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 8V4H8" />
        <rect width="16" height="12" x="4" y="8" rx="2" />
        <path d="M2 14h2" />
        <path d="M20 14h2" />
        <path d="M15 13v2" />
        <path d="M9 13v2" />
    </svg>
</button>

<!-- Chatbot Panel -->
<div id="chatbot-panel" class="fixed bottom-24 right-6 w-[400px] h-[600px] max-w-[calc(100vw-3rem)] max-h-[calc(100vh-8rem)] bg-[#064e3b] rounded-[1.5rem] shadow-2xl shadow-emerald-900/40 flex flex-col overflow-hidden border border-emerald-800 z-50 transition-all duration-300 origin-bottom-right scale-0 opacity-0 pointer-events-none">
    <div class="p-6 border-b border-emerald-800 flex items-center justify-between bg-emerald-950/20 backdrop-blur-md shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-400 text-emerald-950 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-400/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8V4H8" />
                    <rect width="16" height="12" x="4" y="8" rx="2" />
                    <path d="M2 14h2" />
                    <path d="M20 14h2" />
                    <path d="M15 13v2" />
                    <path d="M9 13v2" />
                </svg>
            </div>
            <div>
                <h2 class="font-display font-black text-white text-base tracking-tight">Assistant TranspoBot</h2>
                <p class="text-[9px] font-black uppercase tracking-widest flex items-center gap-1 mt-0.5 ai-status-text text-slate-400">
                    <span class="w-1.5 h-1.5 rounded-full ai-status-dot bg-slate-400"></span> <span class="ai-status-label">Vérification...</span>
                </p>
            </div>
        </div>
        <button id="chatbot-close" class="text-emerald-500 hover:text-white transition-colors bg-emerald-900/50 hover:bg-emerald-800 p-2 rounded-xl">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Chat Container -->
    <div id="chat-container" class="flex-1 overflow-y-auto p-6 space-y-6 scroll-smooth custom-scrollbar">
        <div class="flex gap-3 max-w-[90%]">
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
            <div class="bg-emerald-900/40 backdrop-blur-md px-4 py-3 rounded-[1.25rem] rounded-tl-sm text-sm text-emerald-50 border border-emerald-700/50 shadow-md leading-relaxed ring-1 ring-inset ring-white/5">
                Bonjour ! Je suis votre assistant TranspoBot piloté par IA. Comment puis-je vous aider aujourd'hui ? 
                <div class="mt-3 p-2 bg-emerald-950/30 rounded-xl border border-emerald-900/50 text-[10px] text-emerald-400 font-bold uppercase tracking-widest">
                    Questions suggérées:
                    <div class="mt-2 flex flex-col gap-1.5 normal-case tracking-normal">
                        <button type="button" onclick="window.askSuggestedQuestion('Recette totale')" class="text-left bg-emerald-900/30 hover:bg-emerald-700/50 text-emerald-100 hover:text-white px-3 py-1.5 rounded-lg text-[11px] border border-emerald-800/50 hover:border-emerald-500/50 transition-all flex items-center gap-2 group cursor-pointer">
                            <span class="text-emerald-500 group-hover:text-amber-400 transition-colors">→</span> "Recette totale"
                        </button>
                        <button type="button" onclick="window.askSuggestedQuestion('Liste des incidents critiques')" class="text-left bg-emerald-900/30 hover:bg-emerald-700/50 text-emerald-100 hover:text-white px-3 py-1.5 rounded-lg text-[11px] border border-emerald-800/50 hover:border-emerald-500/50 transition-all flex items-center gap-2 group cursor-pointer">
                            <span class="text-emerald-500 group-hover:text-amber-400 transition-colors">→</span> "Liste des incidents critiques"
                        </button>
                        <button type="button" onclick="window.askSuggestedQuestion('Quels sont les chauffeurs disponibles ?')" class="text-left bg-emerald-900/30 hover:bg-emerald-700/50 text-emerald-100 hover:text-white px-3 py-1.5 rounded-lg text-[11px] border border-emerald-800/50 hover:border-emerald-500/50 transition-all flex items-center gap-2 group cursor-pointer">
                            <span class="text-emerald-500 group-hover:text-amber-400 transition-colors">→</span> "Quels sont les chauffeurs disponibles ?"
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Input Area -->
    <div class="p-4 bg-emerald-950/20 backdrop-blur-md border-t border-emerald-800 shrink-0">
        <form id="chat-form" class="relative m-0">
            <input type="text" id="chat-input" 
                class="w-full bg-emerald-900/50 border border-emerald-700 rounded-2xl py-3 pl-4 pr-12 text-sm text-white placeholder-emerald-500 outline-none focus:ring-2 focus:ring-amber-400/50 transition-all shadow-inner shadow-black/20"
                placeholder="Tapez votre requête IA..." autocomplete="off">
            <button type="submit" class="absolute right-1.5 top-1.5 w-9 h-9 bg-amber-400 text-emerald-900 rounded-xl flex items-center justify-center hover:scale-105 active:scale-95 transition-all shadow-lg shadow-amber-400/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </button>
        </form>
    </div>
</div>
