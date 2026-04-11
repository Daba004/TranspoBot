<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($action === 'resolve' && $id) {
        try {
            $stmt = $pdo->prepare("UPDATE incidents SET resolu = 1 WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: ../incidents.php?success=1");
            exit();
        } catch (PDOException $e) {
            header("Location: ../incidents.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

header("Location: ../incidents.php");
exit();
?>
