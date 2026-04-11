<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';
include 'includes/header.php'; 

$search = $_GET['s'] ?? '';
$query = "SELECT c.*, v.immatriculation as vehicule_nom 
          FROM chauffeurs c 
          LEFT JOIN vehicules v ON c.vehicule_id = v.id";

if($search) {
    $query .= " WHERE c.nom LIKE :s OR c.prenom LIKE :s OR c.telephone LIKE :s OR v.immatriculation LIKE :s";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['s' => "%$search%"]);
    $chauffeurs = $stmt->fetchAll();
} else {
    $chauffeurs = $pdo->query($query . " ORDER BY c.nom ASC")->fetchAll();
}

include 'includes/modals.php';
?>

<div class="bg-white rounded-2xl border border-slate-200/60 overflow-hidden shadow-lg shadow-slate-200/30">
    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/50 backdrop-blur-sm">
        <div>
            <h2 class="text-xl font-display font-black text-slate-900 tracking-tight">Registre du Personnel</h2>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Gestion administrative et operationnelle</p>
        </div>
        <div class="flex w-full md:w-auto gap-3">
            <form class="relative flex-1 md:w-64">
                <input type="text" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher..." class="w-full bg-[#f4f7f6] border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none border-none shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3.5 top-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </form>
            <button onclick="addChauffeur()" class="bg-[#064e3b] text-white px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-800 hover:shadow-lg transition-all flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Ajouter
            </button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-separate border-spacing-0">
            <thead class="bg-slate-50 text-slate-400 text-[8px] uppercase font-black tracking-widest">
                <tr>
                    <th class="px-6 py-4 border-b border-slate-100">Conducteur Affete</th>
                    <th class="px-6 py-4 border-b border-slate-100">Contact & Liaison</th>
                    <th class="px-6 py-4 border-b border-slate-100">Permis & Capacite</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-right">Rapport d'État</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-right">Audit</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-slate-100">
                <?php foreach($chauffeurs as $c): ?>
                <tr class="group hover:bg-emerald-50/40 transition-all duration-300">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-100 to-slate-200 border-2 border-white shadow-sm overflow-hidden flex-shrink-0 group-hover:rotate-3 transition-transform">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['prenom'] . ' ' . $c['nom']); ?>&background=064e3b&color=fff" alt="Avatar">
                            </div>
                            <div>
                                <p class="text-slate-900 font-display font-black text-base tracking-tight group-hover:text-emerald-900 transition-colors"><?php echo $c['prenom'] . ' ' . $c['nom']; ?></p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">ID Agent: #CH-0<?php echo $c['id']; ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <span class="text-slate-700 font-bold tracking-tight text-xs"><?php echo $c['telephone']; ?></span>
                            <span class="text-[8px] text-slate-400 font-black uppercase tracking-widest border-l-2 border-emerald-500 pl-1.5 leading-none">Liaison Mobile</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <span class="bg-stone/50 text-slate-800 font-black px-2.5 py-1 rounded-lg border border-slate-100 text-[9px]"><?php echo $c['numero_permis']; ?></span>
                            <span class="bg-amber-400 text-emerald-900 px-2 py-0.5 rounded-md text-[8px] font-black uppercase shadow-sm"><?php echo $c['categorie_permis']; ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <?php if($c['disponibilite']): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-[8px] font-black uppercase tracking-widest shadow-sm">
                                <span class="w-1 h-1 bg-emerald-500 rounded-full animate-pulse"></span> Disponible
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-rose-100 text-rose-700 text-[8px] font-black uppercase tracking-widest shadow-sm">
                                <span class="w-1 h-1 bg-rose-500 rounded-full"></span> En Mission
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <div class="flex justify-end gap-2">
                            <button onclick='editChauffeur(<?php echo json_encode($c); ?>)' 
                                    class="w-9 h-9 flex items-center justify-center bg-white text-emerald-700 rounded-xl border border-emerald-100 hover:bg-emerald-700 hover:text-white transition-all duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <a href="actions/delete.php?table=chauffeurs&id=<?php echo $c['id']; ?>" 
                               class="w-9 h-9 flex items-center justify-center bg-white text-rose-600 rounded-xl border border-rose-100 hover:bg-rose-600 hover:text-white transition-all duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
