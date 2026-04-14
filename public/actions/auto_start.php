<?php
/**
 * TranspoBot — Auto-Start AI Engine
 * Checks if the FastAPI server is running; if not, starts it.
 */
header('Content-Type: application/json');

$host = '127.0.0.1';
$port = 8000;
$timeout = 1;

// 1. Check if the server is already active
$connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

if (is_resource($connection)) {
    fclose($connection);
    echo json_encode(['success' => true, 'message' => 'L\'IA est déjà connectée.']);
    exit;
}

// 2. If not active, attempt to start it (only on local Windows environment)
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $enginePath = realpath(__DIR__ . '/../../ai_engine/main.py');
    if ($enginePath && file_exists($enginePath)) {
        $cmd = "start /B python \"$enginePath\" > nul 2>&1";
        pclose(popen($cmd, "r"));
        echo json_encode(['success' => true, 'message' => 'Démarrage (Windows)...']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fichier introuvable.']);
    }
} else {
    // On Railway/Linux, the entrypoint.sh handles starting the AI engine.
    echo json_encode(['success' => false, 'message' => 'L\'IA est gérée par le système.']);
}
