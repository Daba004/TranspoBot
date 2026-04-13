<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';
include 'includes/header.php'; 

$search = $_GET['s'] ?? '';
$query = "SELECT * FROM vehicules";
if($search) {
    $query .= " WHERE immatriculation LIKE :s OR type LIKE :s OR statut LIKE :s";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['s' => "%$search%"]);
    $vehicules = $stmt->fetchAll();
} else {
    $vehicules = $pdo->query($query . " ORDER BY id DESC")->fetchAll();
}

include 'includes/modals.php';
?>

<div class="bg-white rounded-2xl border border-slate-200/60 overflow-hidden shadow-lg shadow-slate-200/30 flex-1 flex flex-col min-h-0">
    <div class="p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/50 backdrop-blur-sm shrink-0">
        <div>
            <h2 class="text-xl font-display font-black text-slate-900 tracking-tight">Registre de la Flotte</h2>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Gestion technique et operationnelle</p>
        </div>
        <div class="flex w-full md:auto gap-3">
            <form class="relative flex-1 md:w-64">
                <input type="text" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher..." class="w-full bg-[#f4f7f6] border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:ring-4 focus:ring-emerald-500/10 outline-none border-none shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3.5 top-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </form>
            <button onclick="addVehicule()" class="bg-[#064e3b] text-white px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-800 hover:shadow-lg transition-all flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Ajouter
            </button>
        </div>
    </div>
    <div class="overflow-auto flex-1 custom-scrollbar min-h-0 border-t border-slate-100">
        <table class="w-full text-left border-separate border-spacing-0">
            <thead class="bg-slate-50 text-slate-400 text-[8px] uppercase font-black tracking-widest">
                <tr>
                    <th class="px-6 py-4 border-b border-slate-100">Vehicule</th>
                    <th class="px-6 py-4 border-b border-slate-100">Categorie</th>
                    <th class="px-6 py-4 border-b border-slate-100">Capacite</th>
                    <th class="px-6 py-4 border-b border-slate-100">Etat Rapporte</th>
                    <th class="px-6 py-4 border-b border-slate-100">Performance (Carb/Vit)</th>
                    <th class="px-6 py-4 border-b border-slate-100">Utilisation</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-right">Audit</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-slate-100">
                <?php foreach($vehicules as $v): ?>
                <tr id="vehicle-row-<?php echo $v['id']; ?>" class="group hover:bg-emerald-50/40 transition-all duration-300">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="font-display font-black text-slate-900 text-base tracking-tight leading-tight group-hover:text-emerald-900 transition-colors"><?php echo $v['immatriculation']; ?></span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Unité ID: <?php echo $v['id']; ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-slate-100 text-slate-500 rounded-xl flex items-center justify-center group-hover:bg-emerald-100 group-hover:text-emerald-700 transition-all">
                                <?php if($v['type'] == 'bus'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                                <?php endif; ?>
                            </div>
                            <span class="capitalize font-black text-slate-800 text-[10px] tracking-wide"><?php echo $v['type']; ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-slate-900 font-black bg-stone/50 px-2.5 py-1 rounded-lg border border-slate-100 text-[10px]"><?php echo $v['capacite']; ?> Pas</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-cell px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest shadow-sm <?php 
                            echo $v['statut'] == 'actif' ? 'bg-emerald-100 text-emerald-700' : ($v['statut'] == 'maintenance' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'); 
                        ?>">
                            <?php echo $v['statut']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Carburant</span>
                                <span class="text-[10px] font-black text-slate-800"><?php echo $v['carburant'] ?? 100; ?>%</span>
                            </div>
                            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="fuel-bar-fill h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: <?php echo $v['carburant'] ?? 100; ?>%"></div>
                            </div>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="speed-cell text-[10px] font-black text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md"><?php echo round($v['vitesse'] ?? 0); ?> km/h</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-slate-900 font-black tracking-tight text-xs"><?php echo number_format($v['kilometrage'], 0, ',', ' '); ?> <span class="text-[8px] text-slate-400 font-bold">KM</span></span>
                            <div class="w-20 h-1 bg-slate-100 rounded-full mt-1 overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo min(100, $v['kilometrage']/5000); ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <div class="flex justify-end gap-2">
                            <button onclick='editVehicule(<?php echo json_encode($v); ?>)' 
                                    class="w-9 h-9 flex items-center justify-center bg-white text-emerald-700 rounded-xl border border-emerald-100 hover:bg-emerald-700 hover:text-white transition-all duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            <a href="actions/delete.php?table=vehicules&id=<?php echo $v['id']; ?>" 
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
<script src="assets/js/realtime.js"></script>
<?php include 'includes/footer.php'; ?>
