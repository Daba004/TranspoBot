<?php
require_once '../includes/db.php';

// Filter parameters (same as historique.php)
$ligne_id = $_GET['ligne_id'] ?? '';
$statut = $_GET['statut'] ?? '';
$date_depart = $_GET['date_depart'] ?? '';

// Build Query
$query = "SELECT t.date_heure_depart, l.nom as ligne, c.nom as chauffeur, v.immatriculation as vehicule, t.nb_passagers, t.recette, t.statut
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
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Send CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=historique_trajets_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');

// Add CSV header row
fputcsv($output, ['Date / Heure', 'Ligne', 'Chauffeur', 'Vehicule', 'Passagers', 'Recette (F)', 'Statut']);

// Add data rows
foreach ($data as $row) {
    fputcsv($output, [
        $row['date_heure_depart'],
        $row['ligne'],
        $row['chauffeur'],
        $row['vehicule'],
        $row['nb_passagers'],
        $row['recette'],
        $row['statut']
    ]);
}

fclose($output);
exit();
?>
