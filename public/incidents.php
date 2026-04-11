<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';
include 'includes/header.php'; 

// Fetch Stats for Incidents
$incident_stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
    'non_resolus' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE resolu = 0")->fetchColumn(),
    'graves' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE gravite = 'grave' AND resolu = 0")->fetchColumn()
];

// Fetch Incidents with related trajet info
$incidents = $pdo->query("SELECT i.*, t.date_heure_depart, l.nom as ligne_nom, v.immatriculation
                          FROM incidents i
                          JOIN trajets t ON i.trajet_id = t.id
                          JOIN lignes l ON t.ligne_id = l.id
                          JOIN vehicules v ON t.vehicule_id = v.id
                          ORDER BY i.date_incident DESC")->fetchAll();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-lg shadow-slate-200/30 group hover:translate-y-1 transition-all">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500 group-hover:bg-[#064e3b] group-hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Cumul des Incidents</p>
        </div>
        <h3 class="text-3xl font-display font-black text-slate-800"><?php echo $incident_stats['total']; ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-lg shadow-slate-200/30 group hover:translate-y-1 transition-all">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500 group-hover:bg-[#f59e0b] group-hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">En cours</p>
        </div>
        <h3 class="text-3xl font-display font-black text-amber-600"><?php echo $incident_stats['non_resolus']; ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-lg shadow-slate-200/30 group hover:translate-y-1 transition-all">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500 group-hover:bg-[#e11d48] group-hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Urgence</p>
        </div>
        <h3 class="text-3xl font-display font-black text-rose-600"><?php echo $incident_stats['graves']; ?></h3>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 overflow-hidden shadow-lg shadow-slate-200/30">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50 backdrop-blur-sm">
        <div>
            <h2 class="text-xl font-display font-black text-slate-900 tracking-tight">Journal des Incidents</h2>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Audit technique des operations</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-400 text-[8px] uppercase font-black tracking-widest">
                <tr>
                    <th class="px-6 py-4 border-b border-slate-100">Horodatage</th>
                    <th class="px-6 py-4 border-b border-slate-100">Unité</th>
                    <th class="px-6 py-4 border-b border-slate-100">Classification</th>
                    <th class="px-6 py-4 border-b border-slate-100">Gravite</th>
                    <th class="px-6 py-4 border-b border-slate-100">Description</th>
                    <th class="px-6 py-4 border-b border-slate-100">État</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-right">Intervention</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-slate-100">
                <?php foreach($incidents as $i): ?>
                <tr class="group hover:bg-emerald-50/40 transition-all duration-300">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <p class="font-display font-black text-slate-900 text-sm tracking-tight"><?php echo date('d M Y', strtotime($i['date_incident'])); ?></p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?php echo date('H:i', strtotime($i['date_incident'])); ?></p>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <p class="font-bold text-slate-800 text-xs tracking-tight"><?php echo $i['ligne_nom']; ?></p>
                        <p class="text-[9px] text-emerald-600 font-black uppercase tracking-widest"><?php echo $i['immatriculation']; ?></p>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="capitalize text-slate-900 font-black text-[9px] tracking-widest bg-stone px-2 py-1 rounded-md"><?php echo $i['type']; ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $gravClass = $i['gravite'] == 'grave' ? 'bg-rose-100 text-rose-700' : ($i['gravite'] == 'moyen' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                        ?>
                        <span class="px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest shadow-sm <?php echo $gravClass; ?>">
                            <?php echo $i['gravite']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-500 italic max-w-[200px] truncate text-[10px] leading-relaxed">
                        <?php echo $i['description']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if($i['resolu']): ?>
                            <span class="flex items-center gap-1.5 text-emerald-600 font-black text-[8px] uppercase tracking-widest">
                                <div class="w-1 h-1 bg-emerald-500 rounded-full"></div>
                                Résolu
                            </span>
                        <?php else: ?>
                            <span class="flex items-center gap-1.5 text-amber-600 font-black text-[8px] uppercase tracking-widest animate-pulse">
                                <div class="w-1 h-1 bg-amber-500 rounded-full"></div>
                                En attente
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <?php if(!$i['resolu']): ?>
                            <form action="actions/incident_action.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="resolve">
                                <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-800 hover:shadow-lg transition-all">
                                    Résoudre
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
