<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$trajet_id = $_GET['trajet_id'] ?? '';

if (!$trajet_id) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM incidents WHERE trajet_id = ? ORDER BY date_incident DESC");
    $stmt->execute([$trajet_id]);
    $incidents = $stmt->fetchAll();
    echo json_encode($incidents);
} catch (PDOException $e) {
    echo json_encode([]);
}
exit();
?>
