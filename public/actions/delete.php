<?php
require_once '../includes/db.php';

if (isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = $_GET['id'];
    $allowed_tables = ['vehicules', 'chauffeurs', 'trajets', 'tarifs', 'lignes', 'incidents'];
    
    if (in_array($table, $allowed_tables)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            
            $redirect = '../' . ($table == 'vehicules' ? 'flotte.php' : ($table == 'chauffeurs' ? 'chauffeurs.php' : 'index.php'));
            header("Location: $redirect?success=deleted");
        } catch (PDOException $e) {
            $redirect = '../' . ($table == 'vehicules' ? 'flotte.php' : ($table == 'chauffeurs' ? 'chauffeurs.php' : 'index.php'));
            header("Location: $redirect?error=" . urlencode("Impossible de supprimer car cet élément est utilisé ailleurs."));
        }
    }
}
?>
