<?php
/**
 * Migration Script: Adds simulation columns to the database.
 * Safe to run multiple times (idempotent).
 */
header('Content-Type: text/html; charset=UTF-8');
require_once '../includes/db.php';

$results = [];

function runMigration($pdo, $sql, $description) {
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = "✅ $description";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "⏭️ $description (déjà fait)";
        } else {
            $results[] = "❌ $description : " . $e->getMessage();
        }
    }
}

// 1. Add GPS & simulation columns to vehicules
runMigration($pdo, "ALTER TABLE vehicules ADD COLUMN latitude DECIMAL(10,7) DEFAULT 14.6937", "Ajout colonne latitude à vehicules");
runMigration($pdo, "ALTER TABLE vehicules ADD COLUMN longitude DECIMAL(10,7) DEFAULT -17.4441", "Ajout colonne longitude à vehicules");
runMigration($pdo, "ALTER TABLE vehicules ADD COLUMN carburant INT DEFAULT 100", "Ajout colonne carburant à vehicules");
runMigration($pdo, "ALTER TABLE vehicules ADD COLUMN vitesse DECIMAL(5,1) DEFAULT 0.0", "Ajout colonne vitesse à vehicules");

// 2. Add GPS coordinates to lignes
runMigration($pdo, "ALTER TABLE lignes ADD COLUMN origine_lat DECIMAL(10,7)", "Ajout colonne origine_lat à lignes");
runMigration($pdo, "ALTER TABLE lignes ADD COLUMN origine_lng DECIMAL(10,7)", "Ajout colonne origine_lng à lignes");
runMigration($pdo, "ALTER TABLE lignes ADD COLUMN destination_lat DECIMAL(10,7)", "Ajout colonne destination_lat à lignes");
runMigration($pdo, "ALTER TABLE lignes ADD COLUMN destination_lng DECIMAL(10,7)", "Ajout colonne destination_lng à lignes");

// 3. Update existing lines with real Dakar coordinates
$updates = [
    // L1: Gare Routière Dakar → Thiès Centre
    ["UPDATE lignes SET origine_lat=14.6937, origine_lng=-17.4441, destination_lat=14.7886, destination_lng=-16.9260 WHERE code='L1'", "Coordonnées GPS Ligne L1 (Dakar-Thiès)"],
    // L4: Dakar Plateau → Aéroport AIBD
    ["UPDATE lignes SET origine_lat=14.6693, origine_lng=-17.4380, destination_lat=14.7397, destination_lng=-17.4902 WHERE code='L4'", "Coordonnées GPS Ligne L4 (Aéroport)"],
];

foreach ($updates as $u) {
    try {
        $pdo->exec($u[0]);
        $results[] = "✅ " . $u[1];
    } catch (PDOException $e) {
        $results[] = "❌ " . $u[1] . " : " . $e->getMessage();
    }
}

// 4. Initialize vehicle positions at their assigned line's origin
try {
    $pdo->exec("
        UPDATE vehicules v
        JOIN chauffeurs c ON c.vehicule_id = v.id
        JOIN trajets t ON t.vehicule_id = v.id AND t.statut IN ('en_cours', 'planifie')
        JOIN lignes l ON t.ligne_id = l.id
        SET v.latitude = l.origine_lat, v.longitude = l.origine_lng
        WHERE l.origine_lat IS NOT NULL
    ");
    $results[] = "✅ Positions initiales des véhicules mises à jour";
} catch (PDOException $e) {
    $results[] = "⚠️ Positions initiales : " . $e->getMessage();
}

// 5. Set default fuel levels
try {
    $pdo->exec("UPDATE vehicules SET carburant = FLOOR(60 + RAND() * 40) WHERE carburant = 100 OR carburant IS NULL");
    $results[] = "✅ Niveaux de carburant initialisés (60-100%)";
} catch (PDOException $e) {
    $results[] = "⚠️ Carburant : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration Simulation - TranspoBot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-8 font-sans">
    <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-2xl font-black text-emerald-900 mb-6">🚀 Migration Simulation</h1>
        <div class="space-y-2">
            <?php foreach($results as $r): ?>
                <p class="text-sm font-medium text-slate-700"><?php echo $r; ?></p>
            <?php endforeach; ?>
        </div>
        <div class="mt-8 p-4 bg-emerald-50 rounded-xl border border-emerald-200">
            <p class="text-xs font-bold text-emerald-700">Migration terminée. Vous pouvez maintenant lancer le simulateur.</p>
        </div>
        <a href="../tracking.php" class="mt-4 inline-block bg-emerald-700 text-white px-6 py-3 rounded-xl text-sm font-bold hover:bg-emerald-800 transition-all">→ Aller au Tracking GPS</a>
    </div>
</body>
</html>
