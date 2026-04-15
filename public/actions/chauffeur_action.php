<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $numero_permis = $_POST['numero_permis'] ?? '';
    $categorie_permis = $_POST['categorie_permis'] ?? '';
    $vehicule_id = $_POST['vehicule_id'] ?: null;
    $disponibilite = isset($_POST['disponibilite']) ? 1 : 0;

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO chauffeurs (nom, prenom, telephone, numero_permis, categorie_permis, vehicule_id, disponibilite, date_embauche) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())");
            $stmt->execute([$nom, $prenom, $telephone, $numero_permis, $categorie_permis, $vehicule_id, $disponibilite]);
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("UPDATE chauffeurs SET nom = ?, prenom = ?, telephone = ?, numero_permis = ?, categorie_permis = ?, vehicule_id = ?, disponibilite = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $telephone, $numero_permis, $categorie_permis, $vehicule_id, $disponibilite, $id]);
        }
        header('Location: ../chauffeurs.php?success=1');
    } catch (PDOException $e) {
        header('Location: ../chauffeurs.php?error=' . urlencode($e->getMessage()));
    }
}
?>
