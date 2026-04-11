<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $immatriculation = $_POST['immatriculation'] ?? '';
    $type = $_POST['type'] ?? '';
    $capacite = $_POST['capacite'] ?? '';
    $statut = $_POST['statut'] ?? 'actif';
    $kilometrage = $_POST['kilometrage'] ?? 0;

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO vehicules (immatriculation, type, capacite, statut, kilometrage) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$immatriculation, $type, $capacite, $statut, $kilometrage]);
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("UPDATE vehicules SET immatriculation = ?, type = ?, capacite = ?, statut = ?, kilometrage = ? WHERE id = ?");
            $stmt->execute([$immatriculation, $type, $capacite, $statut, $kilometrage, $id]);
        }
        header('Location: ../flotte.php?success=1');
    } catch (PDOException $e) {
        header('Location: ../flotte.php?error=' . urlencode($e->getMessage()));
    }
}
?>
