<?php
require_once '../includes/init.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    jsonResponse(['error' => 'Authentification requise'], 401);
    exit;
}

// Récupérer l'ID du fichier
$file_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$file_id) {
    jsonResponse(['error' => 'ID de fichier manquant'], 400);
    exit;
}

// Instancier le gestionnaire de fichiers
$fileManager = new FileManager($db, $_SESSION['user_id']);

try {
    // Récupérer le fichier
    $file = $fileManager->getFile($file_id);

    // Vérifier que le fichier existe
    if (!file_exists($file['path'])) {
        jsonResponse([
            'error' => 'Le fichier n\'existe pas',
            'path_checked' => $file['path'],
            'file_id' => $file_id
        ], 404);
        exit;
    }

    // Vérifier le type MIME
    $mime_type = mime_content_type($file['path']);

    // Renvoyer des informations de diagnostic
    jsonResponse([
        'success' => true,
        'file_info' => [
            'id' => $file_id,
            'name' => basename($file['path']),
            'path' => $file['path'],
            'relative_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file['path']),
            'mime_type' => $mime_type,
            'size' => filesize($file['path']),
            'exists' => file_exists($file['path']),
            'is_readable' => is_readable($file['path'])
        ]
    ]);
} catch (Exception $e) {
    jsonResponse([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 500);
}
