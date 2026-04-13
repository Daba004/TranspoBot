<?php 
header('Content-Type: text/html; charset=UTF-8');
require_once 'includes/db.php';
include 'includes/header.php'; 

// Filter parameters
$ligne_id = $_GET['ligne_id'] ?? '';
$statut = $_GET['statut'] ?? '';
$date_depart = $_GET['date_depart'] ?? '';

// Build Query
$query = "SELECT t.*, 
                 l.nom as ligne_nom, l.code as ligne_code, l.origine, l.destination, l.distance_km, l.duree_minutes,
                 c.nom as chauffeur_nom, c.prenom as chauffeur_prenom, c.telephone as chauffeur_tel,
                 v.immatriculation, v.type as vehicule_type, v.capacite as vehicule_capacite
          FROM trajets t 
          JOIN lignes l ON t.ligne_id = l.id 
          JOIN chauffeurs c ON t.chauffeur_id = c.id 
          JOIN vehicules v ON t.vehicule_id = v.id
          WHERE 1=1";

$params = [];
if ($ligne_id) {
    $query .= " AND t.ligne_id = ?";
    $params[] = $ligne_id;
}
if ($statut) {
    $query .= " AND t.statut = ?";
    $params[] = $statut;
}
if ($date_depart) {
    $query .= " AND DATE(t.date_heure_depart) = ?";
    $params[] = $date_depart;
}

$query .= " ORDER BY t.date_heure_depart DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Fetch all lines for the filter dropdown
$lines = $pdo->query("SELECT id, nom FROM lignes")->fetchAll();

// Fetch all tariffs for details breakdown
$all_tarifs = $pdo->query("SELECT * FROM tarifs")->fetchAll();
$tarifs_by_ligne = [];
foreach($all_tarifs as $tr) {
    $tarifs_by_ligne[$tr['ligne_id']][] = $tr;
}
?>

<div class="bg-white rounded-2xl border border-slate-200/60 overflow-hidden shadow-lg shadow-slate-200/30 flex-1 flex flex-col min-h-0">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50 backdrop-blur-sm shrink-0">
        <div>
            <h2 class="text-xl font-display font-black text-slate-900 tracking-tight">Journal des Activités</h2>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Audit complet des opérations</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="actions/export_csv.php" method="GET" class="inline">
                <input type="hidden" name="ligne_id" value="<?php echo $ligne_id; ?>">
                <input type="hidden" name="statut" value="<?php echo $statut; ?>">
                <input type="hidden" name="date_depart" value="<?php echo $date_depart; ?>">
                <button type="submit" class="bg-slate-50 text-slate-500 px-4 py-2.5 rounded-xl text-[9px] uppercase font-black hover:bg-emerald-50 hover:text-emerald-700 transition-all tracking-widest border border-slate-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Exporter
                </button>
            </form>
            <button onclick="toggleFilter()" class="bg-[#064e3b] text-white px-6 py-2.5 rounded-xl text-[9px] uppercase font-black tracking-widest hover:bg-emerald-800 hover:shadow-lg transition-all flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                Filtres
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div id="filter-bar" class="<?php echo ($ligne_id || $statut || $date_depart) ? '' : 'hidden'; ?> p-6 bg-stone/50 border-b border-slate-100 shrink-0">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Affiner par Ligne</label>
                <select name="ligne_id" class="w-full bg-white border-none shadow-inner rounded-lg py-2.5 px-3 text-xs outline-none focus:ring-4 focus:ring-emerald-500/10">
                    <option value="">Tous les réseaux</option>
                    <?php foreach($lines as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $ligne_id == $l['id'] ? 'selected' : ''; ?>><?php echo $l['nom']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">État du Trajet</label>
                <select name="statut" class="w-full bg-white border-none shadow-inner rounded-lg py-2.5 px-3 text-xs outline-none focus:ring-4 focus:ring-emerald-500/10">
                    <option value="">Tous les statuts</option>
                    <option value="planifie" <?php echo $statut == 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                    <option value="en_cours" <?php echo $statut == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="termine" <?php echo $statut == 'termine' ? 'selected' : ''; ?>>Terminé</option>
                    <option value="annule" <?php echo $statut == 'annule' ? 'selected' : ''; ?>>Annulé</option>
                </select>
            </div>
            <div>
                <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Calendrier</label>
                <input type="date" name="date_depart" value="<?php echo $date_depart; ?>" class="w-full bg-white border-none shadow-inner rounded-lg py-2.5 px-3 text-xs outline-none focus:ring-4 focus:ring-emerald-500/10">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 text-white py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-md hover:bg-emerald-800 transition-all">Appliquer</button>
                <a href="historique.php" class="w-10 h-10 flex items-center justify-center bg-slate-200 text-slate-500 rounded-lg hover:bg-rose-100 hover:text-rose-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </a>
            </div>
        </form>
    </div>
    <div class="overflow-auto flex-1 custom-scrollbar min-h-0 border-t border-slate-100">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-400 text-[8px] uppercase font-black tracking-widest">
                <tr>
                    <th class="px-6 py-4 border-b border-slate-100">Date / Heure</th>
                    <th class="px-6 py-4 border-b border-slate-100">Ligne</th>
                    <th class="px-6 py-4 border-b border-slate-100">Chauffeur / Véhicule</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-center">Pas</th>
                    <th class="px-6 py-4 border-b border-slate-100">Recette</th>
                    <th class="px-6 py-4 border-b border-slate-100">Status</th>
                    <th class="px-6 py-4 border-b border-slate-100 text-right">Audit</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-slate-100">
                <?php foreach($trips as $t): ?>
                <tr class="group hover:bg-emerald-50/40 transition-all duration-300">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <p class="font-display font-black text-slate-900 text-sm tracking-tight"><?php echo date('d M Y', strtotime($t['date_heure_depart'])); ?></p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?php echo date('H:i', strtotime($t['date_heure_depart'])); ?></p>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-800 text-xs tracking-tight"><?php echo $t['ligne_nom']; ?></span>
                            <span class="text-[8px] text-emerald-600 font-black uppercase tracking-widest">Code <?php echo $t['ligne_code']; ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <p class="text-slate-900 font-bold text-xs tracking-tight group-hover:text-emerald-900"><?php echo $t['chauffeur_prenom'] . ' ' . $t['chauffeur_nom']; ?></p>
                        <p class="text-[9px] text-slate-400 font-black uppercase tracking-tighter">Mat: <?php echo $t['immatriculation']; ?></p>
                    </td>
                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        <span class="text-slate-900 font-black text-xs"><?php echo $t['nb_passagers']; ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-slate-900 font-display font-black text-sm tracking-tight"><?php echo number_format($t['recette'], 0, ',', ' '); ?></span> <span class="text-[9px] text-emerald-600 font-black">F</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $statusClass = $t['statut'] == 'termine' ? 'bg-emerald-100 text-emerald-700' : ($t['statut'] == 'en_cours' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                        ?>
                        <span class="px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest shadow-sm <?php echo $statusClass; ?>">
                            <?php echo $t['statut']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <button onclick='showDetails(<?php echo json_encode($t); ?>, <?php echo json_encode($tarifs_by_ligne[$t['ligne_id']] ?? []); ?>)' 
                                class="bg-white text-slate-800 px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:text-white hover:shadow-lg transition-all border border-slate-200 group-hover:border-emerald-700">
                            Détails
                        </button>
                    </td>
                </tr>    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleFilter() {
    const bar = document.getElementById('filter-bar');
    bar.classList.toggle('hidden');
}
</script>

<?php 
include 'includes/details_modal.php';
include 'includes/footer.php'; 
?>
