<!-- Modal Logic (Generic) -->
<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}

// Function to populate vehicle edit modal
function editVehicule(data) {
    document.getElementById('v_modal_title').innerText = 'Modifier le Vehicule';
    document.getElementById('v_action').value = 'edit';
    document.getElementById('v_id').value = data.id;
    document.getElementById('v_immatriculation').value = data.immatriculation;
    document.getElementById('v_type').value = data.type;
    document.getElementById('v_capacite').value = data.capacite;
    document.getElementById('v_statut').value = data.statut;
    document.getElementById('v_kilometrage').value = data.kilometrage;
    openModal('modal-vehicule');
}

function addVehicule() {
    document.getElementById('v_modal_title').innerText = 'Ajouter un Vehicule';
    document.getElementById('v_action').value = 'add';
    document.getElementById('v_id').value = '';
    document.getElementById('v_form').reset();
    openModal('modal-vehicule');
}

// Function to populate chauffeur edit modal
function editChauffeur(data) {
    document.getElementById('c_modal_title').innerText = 'Modifier le Chauffeur';
    document.getElementById('c_action').value = 'edit';
    document.getElementById('c_id').value = data.id;
    document.getElementById('c_nom').value = data.nom;
    document.getElementById('c_prenom').value = data.prenom;
    document.getElementById('c_telephone').value = data.telephone;
    document.getElementById('c_numero_permis').value = data.numero_permis;
    document.getElementById('c_categorie_permis').value = data.categorie_permis;
    document.getElementById('c_vehicule_id').value = data.vehicule_id || '';
    document.getElementById('c_disponibilite').checked = data.disponibilite == 1;
    openModal('modal-chauffeur');
}

function addChauffeur() {
    document.getElementById('c_modal_title').innerText = 'Ajouter un Chauffeur';
    document.getElementById('c_action').value = 'add';
    document.getElementById('c_id').value = '';
    document.getElementById('c_form').reset();
    openModal('modal-chauffeur');
}
</script>

<!-- Modal Véhicule -->
<div id="modal-vehicule" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-300">
        <div class="p-6 bg-[#064e3b] text-white flex justify-between items-center relative overflow-hidden">
            <div class="relative z-10">
                <h3 id="v_modal_title" class="font-display font-black text-xl tracking-tight">Ajouter un Vehicule</h3>
                <p class="text-[9px] text-emerald-100 uppercase font-black tracking-widest mt-0.5 opacity-80">Parc Automobile Operationnel</p>
            </div>
            <button onclick="closeModal('modal-vehicule')" class="relative z-10 w-10 h-10 flex items-center justify-center rounded-2xl bg-white/10 hover:bg-white/20 transition-all border border-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <!-- Background Decoration -->
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        <form id="v_form" action="actions/vehicule_action.php" method="POST" class="p-8 space-y-5">
            <input type="hidden" name="action" id="v_action" value="add">
            <input type="hidden" name="id" id="v_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Immatriculation</label>
                    <input type="text" name="immatriculation" id="v_immatriculation" required placeholder="ex: AA-123-BC" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Categorie</label>
                        <select name="type" id="v_type" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner appearance-none">
                            <option value="bus">Bus</option>
                            <option value="minibus">Minibus</option>
                            <option value="taxi">Taxi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Capacite</label>
                        <input type="number" name="capacite" id="v_capacite" required class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Etat Operationnel</label>
                        <select name="statut" id="v_statut" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner appearance-none">
                            <option value="actif">Actif</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="hors_service">Hors Service</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Kilometrage (KM)</label>
                        <input type="number" name="kilometrage" id="v_kilometrage" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal('modal-vehicule')" class="flex-1 px-4 py-3 bg-slate-50 text-slate-500 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all border border-slate-200">Annuler</button>
                <button type="submit" class="flex-[2] px-4 py-3 bg-[#064e3b] text-white rounded-xl text-[9px] font-black uppercase tracking-widest shadow-lg shadow-emerald-900/20 hover:scale-[1.02] active:scale-[0.98] transition-all">Valider l'entree</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Chauffeur -->
<div id="modal-chauffeur" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-300">
        <div class="p-6 bg-[#064e3b] text-white flex justify-between items-center relative overflow-hidden">
            <div class="relative z-10">
                <h3 id="c_modal_title" class="font-display font-black text-xl tracking-tight">Ajouter un Chauffeur</h3>
                <p class="text-[9px] text-emerald-100 uppercase font-black tracking-widest mt-0.5 opacity-80">Ressources Humaines & Personnel</p>
            </div>
            <button onclick="closeModal('modal-chauffeur')" class="relative z-10 w-10 h-10 flex items-center justify-center rounded-2xl bg-white/10 hover:bg-white/20 transition-all border border-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        <form id="c_form" action="actions/chauffeur_action.php" method="POST" class="p-8 space-y-5">
            <input type="hidden" name="action" id="c_action" value="add">
            <input type="hidden" name="id" id="c_id">
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Prenom</label>
                        <input type="text" name="prenom" id="c_prenom" required class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nom</label>
                        <input type="text" name="nom" id="c_nom" required class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Mobile Professionnel</label>
                    <input type="text" name="telephone" id="c_telephone" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">N° Permis</label>
                        <input type="text" name="numero_permis" id="c_numero_permis" required class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Categorie</label>
                        <input type="text" name="categorie_permis" id="c_categorie_permis" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner" placeholder="ex: D">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Unité Assignée</label>
                    <select name="vehicule_id" id="c_vehicule_id" class="w-full bg-[#f4f7f6] border-none rounded-2xl p-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-inner appearance-none">
                        <option value="">-- Sans Affectation --</option>
                        <?php 
                        $v_list = $pdo->query("SELECT id, immatriculation FROM vehicules WHERE id NOT IN (SELECT vehicule_id FROM chauffeurs WHERE vehicule_id IS NOT NULL) OR id IN (SELECT vehicule_id FROM chauffeurs WHERE id = '".($id ?? 0)."')")->fetchAll();
                        foreach($v_list as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo $v['immatriculation']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-3 p-4 bg-emerald-50 rounded-2xl border border-emerald-100/50">
                    <input type="checkbox" name="disponibilite" id="c_disponibilite" checked class="w-5 h-5 rounded-lg text-emerald-600 border-none shadow-inner focus:ring-emerald-500/20">
                    <label class="text-xs text-emerald-800 font-bold uppercase tracking-wider">Statut: Disponible immediatement</label>
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal('modal-chauffeur')" class="flex-1 px-4 py-3 bg-slate-50 text-slate-500 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all border border-slate-200">Annuler</button>
                <button type="submit" class="flex-[2] px-4 py-3 bg-[#064e3b] text-white rounded-xl text-[9px] font-black uppercase tracking-widest shadow-lg shadow-emerald-900/20 hover:scale-[1.02] active:scale-[0.98] transition-all">Enregistrer l'agent</button>
            </div>
        </form>
    </div>
</div>
