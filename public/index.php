<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';
include 'includes/header.php'; 

// Initialize variables to avoid warnings
$recent_trajets = [];
$tarifs_by_ligne = [];

// Fetch Stats
$stats = [
    'vehicules' => $pdo->query("SELECT COUNT(*) FROM vehicules")->fetchColumn(),
    'chauffeurs' => $pdo->query("SELECT COUNT(*) FROM chauffeurs WHERE disponibilite = 1")->fetchColumn(),
    'trajets' => $pdo->query("SELECT COUNT(*) FROM trajets WHERE statut = 'termine'")->fetchColumn(),
    'recette' => $pdo->query("SELECT SUM(recette) FROM trajets")->fetchColumn() ?? 0,
    'incidents_non_resolus' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE resolu = 0")->fetchColumn()
];

// Fetch all tariffs for details breakdown
$all_tarifs = $pdo->query("SELECT * FROM tarifs")->fetchAll();
$tarifs_by_ligne = [];
foreach($all_tarifs as $tr) {
    $tarifs_by_ligne[$tr['ligne_id']][] = $tr;
}

// Fetch Recent Trajets (The missing query)
$recent_trajets = $pdo->query("SELECT t.*, l.nom as ligne_nom, l.code as ligne_code, v.immatriculation, c.nom as chauffeur_nom, c.prenom as chauffeur_prenom 
                               FROM trajets t 
                               JOIN lignes l ON t.ligne_id = l.id 
                               JOIN vehicules v ON t.vehicule_id = v.id 
                               JOIN chauffeurs c ON t.chauffeur_id = c.id 
                               ORDER BY t.date_heure_depart DESC LIMIT 6")->fetchAll();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Stat Card 1 -->
    <div class="bg-white p-5 rounded-[1.5rem] border border-slate-200/60 shadow-lg shadow-slate-200/30 transition-all duration-300 hover:-translate-y-1">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
            </div>
            <span class="text-[10px] font-black text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full uppercase tracking-widest">+12%</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-tight mb-0.5">Vehicules de ligne</p>
        <h3 class="text-3xl font-display font-black text-slate-900"><?php echo $stats['vehicules']; ?></h3>
    </div>

    <!-- Stat Card 2 -->
    <div class="bg-white p-5 rounded-[1.5rem] border border-slate-200/60 shadow-lg shadow-slate-200/30 transition-all duration-300 hover:-translate-y-1">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2.5 bg-amber-50 text-amber-600 rounded-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <span class="text-[10px] font-black text-amber-500 bg-amber-50 px-2 py-1 rounded-full uppercase tracking-widest">Optimal</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-tight mb-0.5">Chauffeurs Libres</p>
        <h3 class="text-3xl font-display font-black text-slate-900"><?php echo $stats['chauffeurs']; ?></h3>
    </div>

    <!-- Stat Card 3 -->
    <div class="bg-white p-5 rounded-[1.5rem] border border-slate-200/60 shadow-lg shadow-slate-200/30 transition-all duration-300 hover:-translate-y-1">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <span class="text-[10px] font-black text-slate-300 bg-slate-50 px-2 py-1 rounded-full uppercase tracking-widest">Total</span>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-tight mb-0.5">Trajets Termines</p>
        <h3 class="text-3xl font-display font-black text-slate-900"><?php echo $stats['trajets']; ?></h3>
    </div>

    <!-- Stat Card 4 (Forest Green Highlight) -->
    <div class="bg-[#064e3b] p-5 rounded-[1.5rem] shadow-lg shadow-emerald-900/20 transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
        <!-- Interactive Watermark -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 absolute -bottom-6 -right-6 text-white/5 rotate-12 group-hover:rotate-[30deg] transition-all duration-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex items-center justify-between mb-4 relative z-10">
            <div class="p-2.5 bg-amber-400 text-emerald-900 rounded-2xl shadow-lg shadow-amber-400/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <a href="incidents.php" class="text-[10px] font-black text-emerald-900 bg-amber-400 px-3 py-1.5 rounded-full uppercase tracking-widest hover:scale-105 transition-all">Incidents: <?php echo $stats['incidents_non_resolus']; ?></a>
        </div>
        <p class="text-[11px] font-bold text-emerald-100/60 uppercase tracking-tight mb-0.5 relative z-10">Recette Globale</p>
        <h3 class="text-3xl font-display font-black text-white relative z-10 tracking-tight"><?php echo number_format($stats['recette'], 0, ',', ' '); ?> <span class="text-sm text-emerald-300">F</span></h3>
    </div>
</div>

<div class="flex flex-col lg:flex-row gap-6 mb-8 items-stretch">
    <!-- Recent Events - Left Column -->
    <div class="flex-1 min-w-0 flex flex-col">
        <div class="bg-white rounded-[1.5rem] border border-slate-200/60 overflow-hidden shadow-xl shadow-slate-200/30 flex-1">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50 backdrop-blur-sm">
                <div>
                    <h2 class="text-lg font-display font-black text-slate-900 tracking-tight">Flux de Transport Recents</h2>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1 italic">Surveillance en temps reel</p>
                </div>
                <a href="historique.php" class="bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl text-[9px] uppercase font-black hover:bg-emerald-700 hover:text-white transition-all tracking-widest border border-emerald-100 shadow-sm">Exporter Historique</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-0">
                    <thead class="bg-slate-50 text-slate-400 text-[8px] uppercase font-black tracking-widest">
                        <tr>
                            <th class="px-6 py-4 border-b border-slate-100">Ligne / Service</th>
                            <th class="px-6 py-4 border-b border-slate-100">Chauffeur</th>
                            <th class="px-6 py-4 border-b border-slate-100">Rapport Etat</th>
                            <th class="px-6 py-4 border-b border-slate-100">Recette</th>
                            <th class="px-6 py-4 border-b border-slate-100 text-right">Audit</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php foreach($recent_trajets as $rt): ?>
                        <tr class="group hover:bg-emerald-50/40 transition-all duration-300">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-900 text-sm tracking-tight leading-tight"><?php echo $rt['ligne_nom']; ?></span>
                                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $rt['ligne_code']; ?> • <?php echo $rt['immatriculation']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-slate-700 font-bold text-xs"><?php echo $rt['chauffeur_prenom'] . ' ' . $rt['chauffeur_nom']; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $statusClass = $rt['statut'] == 'termine' ? 'bg-emerald-100 text-emerald-700' : ($rt['statut'] == 'en_cours' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest shadow-sm <?php echo $statusClass; ?>">
                                    <?php echo $rt['statut']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-display font-black text-slate-900 text-sm tracking-tight whitespace-nowrap">
                                <?php echo number_format($rt['recette'], 0, ',', ' '); ?> <span class="text-[9px] text-emerald-600 ml-0.5">F</span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button onclick='showDetails(<?php echo json_encode($rt); ?>, <?php echo json_encode($tarifs_by_ligne[$rt['ligne_id']] ?? []); ?>)' 
                                        class="bg-white text-slate-800 px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:text-white hover:shadow-lg transition-all border border-slate-200 group-hover:border-emerald-700">
                                    Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- AI Assistant Widget - Integrated Right Side -->
    <div class="w-full lg:w-[320px] shrink-0 flex flex-col min-h-[450px]">
        <div class="bg-[#064e3b] rounded-[1.5rem] shadow-2xl shadow-emerald-900/40 flex flex-col h-full overflow-hidden border border-emerald-800">
            <div class="p-6 border-b border-emerald-800 flex items-center gap-3 bg-emerald-950/20 backdrop-blur-md">
                <div class="w-10 h-10 bg-amber-400 text-emerald-950 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-400/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-display font-black text-white text-base tracking-tight">Assistant TranspoBot</h2>
                    <p class="text-[9px] text-emerald-400 font-black uppercase tracking-widest flex items-center gap-1 mt-0.5">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> IA Connectée
                    </p>
                </div>
            </div>

            <!-- Chat Container -->
            <div id="chat-container" class="flex-1 overflow-y-auto p-6 space-y-6 scroll-smooth custom-scrollbar">
                <div class="flex gap-3 max-w-[90%]">
                    <div class="w-8 h-8 rounded-xl bg-emerald-800 flex-shrink-0 flex items-center justify-center text-emerald-400 border border-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="bg-emerald-800/50 backdrop-blur-sm p-4 rounded-3xl rounded-tl-none text-sm text-emerald-100 border border-emerald-700 shadow-sm leading-relaxed">
                        Bonjour ! Je suis votre assistant TranspoBot pilote par IA. Comment puis-je vous aider aujourd'hui ? 
                        <div class="mt-3 p-2 bg-emerald-950/30 rounded-xl border border-emerald-900/50 text-[10px] text-emerald-400 font-bold uppercase tracking-widest">
                            Questions suggerees:
                            <ul class="mt-1 space-y-1 normal-case tracking-normal text-emerald-200">
                                <li>• "Recette totale d'aujourd'hui"</li>
                                <li>• "Liste des incidents critiques"</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-6 bg-emerald-950/20 backdrop-blur-md border-t border-emerald-800">
                <form id="chat-form" class="relative">
                    <input type="text" id="chat-input" 
                        class="w-full bg-emerald-900/50 border border-emerald-700 rounded-2xl py-4 pl-5 pr-14 text-sm text-white placeholder-emerald-500 outline-none focus:ring-2 focus:ring-amber-400/50 transition-all shadow-inner shadow-black/20"
                        placeholder="Tapez votre requete IA...">
                    <button type="submit" class="absolute right-2 top-2 w-10 h-10 bg-amber-400 text-emerald-900 rounded-xl flex items-center justify-center hover:scale-105 active:scale-95 transition-all shadow-lg shadow-amber-400/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/details_modal.php';
include 'includes/footer.php'; 
?>
