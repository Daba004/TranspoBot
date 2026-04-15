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
    } elseif ($action === 'add') {
        $trajet_id = $_POST['trajet_id'] ?? null;
        $type = $_POST['type'] ?? 'autre';
        $gravite = $_POST['gravite'] ?? 'faible';
        $description = $_POST['description'] ?? '';
        
        if ($trajet_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO incidents (trajet_id, type, description, gravite, date_incident, resolu) VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt->execute([$trajet_id, $type, $description, $gravite]);
                header("Location: ../incidents.php?success=1");
                exit();
            } catch (PDOException $e) {
                header("Location: ../incidents.php?error=" . urlencode($e->getMessage()));
                exit();
            }
        }
    } elseif ($action === 'delete' && $id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
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
