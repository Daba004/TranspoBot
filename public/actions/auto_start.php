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

// 2. If not active, attempt to start it in the background
// We use realpath to be sure of the path on Windows
$enginePath = realpath(__DIR__ . '/../../ai_engine/main.py');

if ($enginePath && file_exists($enginePath)) {
    // Command for Windows: start /B runs it in background without opening a CMD window
    // We redirect output to avoid blocking
    $cmd = "start /B python \"$enginePath\" > nul 2>&1";
    
    // pclose/popen is the most reliable way to fire and forget on Windows PHP
    pclose(popen($cmd, "r"));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Démarrage du moteur AI en cours...',
        'path' => $enginePath
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Le fichier ai_engine/main.py est introuvable.',
        'path' => $enginePath
    ]);
}
