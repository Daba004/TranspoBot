<!-- Trip Details Modal -->
<div id="details-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-3xl overflow-hidden animate-in zoom-in duration-300">
        <div class="px-10 py-8 border-b border-slate-100 flex justify-between items-center bg-[#064e3b] text-white relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="text-2xl font-display font-black tracking-tight">Fiche de Bord Detaillee</h3>
                <p class="text-[10px] text-emerald-100 uppercase font-black tracking-widest mt-1 opacity-80" id="modal-subtitle">Rapport d'activite & Analyse des performances</p>
            </div>
            <button onclick="closeModal()" class="relative z-10 w-10 h-10 flex items-center justify-center rounded-2xl bg-white/10 hover:bg-white/20 transition-all border border-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        
        <div class="p-10 max-h-[80vh] overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <!-- Line Info -->
                <div class="space-y-5">
                    <h4 class="text-[10px] font-black text-emerald-800 uppercase tracking-widest flex items-center gap-2">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                        Itineraire & Secteur
                    </h4>
                    <div class="bg-[#f4f7f6] p-6 rounded-[2rem] shadow-inner relative overflow-hidden group">
                        <div class="flex items-center gap-4 mb-5">
                            <span class="bg-white text-emerald-800 text-[11px] font-black px-3 py-1.5 rounded-xl shadow-sm border border-emerald-50" id="modal-ligne-code"></span>
                            <span class="font-display font-black text-slate-900 text-lg tracking-tight" id="modal-ligne-nom"></span>
                        </div>
                        <div class="space-y-4 text-sm relative z-10">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400 font-bold text-[10px] uppercase tracking-widest">Base de Depart</span>
                                <span class="font-black text-slate-800" id="modal-origine"></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400 font-bold text-[10px] uppercase tracking-widest">Destination Finale</span>
                                <span class="font-black text-slate-800" id="modal-destination"></span>
                            </div>
                            <div class="flex justify-between items-center bg-white/40 p-4 rounded-2xl mt-4 border border-white">
                                <span class="text-slate-500 font-bold text-[10px] uppercase tracking-widest">Logistique</span>
                                <span class="font-black text-emerald-900 group-hover:scale-105 transition-transform"><span id="modal-distance"></span> km <span class="text-slate-300 mx-2">|</span> <span id="modal-duree"></span> min</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle/Driver Info -->
                <div class="space-y-5">
                    <h4 class="text-[10px] font-black text-amber-600 uppercase tracking-widest flex items-center gap-2">
                        <div class="w-2 h-2 bg-amber-500 rounded-full"></div>
                        Moyens & Personnel
                    </h4>
                    <div class="bg-white border-2 border-[#f4f7f6] p-6 rounded-[2rem] shadow-sm space-y-5">
                        <div class="flex items-center gap-5 p-3 hover:bg-slate-50 rounded-2xl transition-all">
                            <div class="w-12 h-12 bg-emerald-50 text-emerald-700 rounded-2xl flex items-center justify-center border border-emerald-100 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Materiel Roulant</p>
                                <p class="font-black text-slate-800 text-base tracking-tight"><span id="modal-vehicule-type" class="capitalize"></span> <span class="text-emerald-600 ml-1" id="modal-vehicule-immatriculation"></span></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-5 p-3 hover:bg-slate-50 rounded-2xl transition-all">
                            <div class="w-12 h-12 bg-amber-50 text-amber-700 rounded-2xl flex items-center justify-center border border-amber-100 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Operateur de Bord</p>
                                <p class="font-black text-slate-800 text-base tracking-tight"><span id="modal-chauffeur-prenom"></span> <span id="modal-chauffeur-nom"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tariffs Section -->
            <div class="mt-10 space-y-5">
                <h4 class="text-[10px] font-black text-emerald-800 uppercase tracking-widest flex items-center gap-2">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                    Analyse des Flux Financiers
                </h4>
                <div id="modal-tarifs-container" class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Dynamic Tariffs go here -->
                </div>
            </div>

            <!-- Performance & Incidents Section -->
            <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
                <!-- Recette Card -->
                <div class="bg-gradient-to-br from-[#064e3b] to-emerald-900 rounded-[2.5rem] p-8 text-white shadow-2xl shadow-emerald-900/40 relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-emerald-100/60 text-[10px] font-black uppercase tracking-widest mb-2">Chiffre d'Affaire Mission</p>
                        <h4 class="text-5xl font-display font-black mb-6 tracking-tight group-hover:scale-105 transition-transform"><span id="modal-recette"></span> <span class="text-lg opacity-60">F</span></h4>
                        <div class="flex justify-between items-center border-t border-white/10 pt-6">
                            <div>
                                <p class="text-emerald-100/40 text-[9px] font-black uppercase tracking-widest">Rendement</p>
                                <p class="text-lg font-black"><span id="modal-recette-pp"></span> <span class="text-[10px] opacity-60 uppercase">F / PAX</span></p>
                            </div>
                            <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Incident List Card -->
                <div class="bg-[#f4f7f6] rounded-[2.5rem] p-8 shadow-inner relative overflow-hidden group">
                    <div class="relative z-10 h-full flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-[10px] font-black text-rose-600 uppercase tracking-widest flex items-center gap-2">
                                <div class="w-2 h-2 bg-rose-500 rounded-full animate-pulse"></div>
                                Journal d'Alertes
                            </h4>
                            <span id="modal-incident-count" class="bg-rose-100 text-rose-700 text-[10px] px-3 py-1 rounded-full font-black shadow-sm group-hover:rotate-12 transition-transform">0</span>
                        </div>
                        <div id="modal-incidents-container" class="space-y-4 max-h-[160px] overflow-y-auto pr-3 custom-scrollbar flex-1">
                            <!-- Dynamic Incidents go here -->
                        </div>
                    </div>
                    <!-- Watermark -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute -bottom-6 -right-6 h-32 w-32 text-slate-200/50 rotate-12 group-hover:rotate-45 transition-transform duration-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function fetchIncidents(trajetId) {
    try {
        const response = await fetch(`actions/get_incidents.php?trajet_id=${trajetId}`);
        return await response.json();
    } catch (e) {
        return [];
    }
}

async function showDetails(trip, tariffs) {
    // Show Modal Loading State (Optional)
    const modal = document.getElementById('details-modal');
    
    // Basic Info
    document.getElementById('modal-ligne-code').innerText = trip.ligne_code || 'N/A';
    document.getElementById('modal-ligne-nom').innerText = trip.ligne_nom || 'N/A';
    document.getElementById('modal-origine').innerText = trip.origine || 'N/A';
    document.getElementById('modal-destination').innerText = trip.destination || 'N/A';
    document.getElementById('modal-distance').innerText = trip.distance_km || '0';
    document.getElementById('modal-duree').innerText = trip.duree_minutes || '0';
    
    // Vehicle/Chauffeur
    document.getElementById('modal-vehicule-type').innerText = trip.vehicule_type || 'N/A';
    document.getElementById('modal-vehicule-immatriculation').innerText = trip.immatriculation || 'N/A';
    document.getElementById('modal-chauffeur-prenom').innerText = trip.chauffeur_prenom || '';
    document.getElementById('modal-chauffeur-nom').innerText = trip.chauffeur_nom || '';
    
    // Recette
    const recette = parseFloat(trip.recette) || 0;
    const passagers = parseInt(trip.nb_passagers) || 0;
    document.getElementById('modal-recette').innerText = recette.toLocaleString('fr-FR');
    document.getElementById('modal-recette-pp').innerText = passagers > 0 ? (recette / passagers).toFixed(0) : '0';
    
    // Tariffs
    const container = document.getElementById('modal-tarifs-container');
    container.innerHTML = '';
    if (tariffs && tariffs.length > 0) {
        tariffs.forEach(t => {
            const div = document.createElement('div');
            div.className = 'bg-white border-2 border-[#f4f7f6] p-6 rounded-[2rem] text-center shadow-sm hover:scale-105 transition-all group';
            div.innerHTML = `
                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest mb-1 group-hover:text-amber-600 transition-colors">${t.type_client}</p>
                <div class="flex items-center justify-center gap-1">
                    <p class="text-xl font-display font-black text-slate-800">${parseFloat(t.prix).toLocaleString('fr-FR')}</p>
                    <span class="text-[10px] font-black text-emerald-600 uppercase">F</span>
                </div>
            `;
            container.appendChild(div);
        });
    } else {
        container.innerHTML = '<p class="text-slate-400 text-xs font-bold uppercase tracking-widest py-8 col-span-3 text-center border-2 border-dashed border-[#f4f7f6] rounded-[2rem]">Données de tarification non disponibles</p>';
    }

    // Incidents
    const incidentContainer = document.getElementById('modal-incidents-container');
    const incidentBadge = document.getElementById('modal-incident-count');
    incidentContainer.innerHTML = '<p class="text-slate-400 text-[10px] italic">Chargement des incidents...</p>';
    
    const incidents = await fetchIncidents(trip.id);
    incidentBadge.innerText = incidents.length;
    incidentContainer.innerHTML = '';
    
    if (incidents.length > 0) {
        incidents.forEach(inc => {
            const div = document.createElement('div');
            div.className = 'p-5 rounded-3xl bg-white border border-rose-100 flex items-start gap-4 shadow-sm group hover:bg-rose-50 transition-all';
            div.innerHTML = `
                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center border border-rose-100 group-hover:rotate-12 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div class="flex-1">
                    <p class="text-[10px] font-black text-rose-700 uppercase tracking-widest mb-1">${inc.type}</p>
                    <p class="text-[11px] text-slate-500 font-medium leading-relaxed">${inc.description}</p>
                </div>
            `;
            incidentContainer.appendChild(div);
        });
    } else {
        incidentContainer.innerHTML = `
            <div class="h-full flex flex-col items-center justify-center py-6">
                <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                </div>
                <p class="text-emerald-800/60 text-[10px] font-black uppercase tracking-widest text-center px-4">Intégrité opérationnelle confirmée</p>
            </div>
        `;
    }

    // Show Modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('details-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById('details-modal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
