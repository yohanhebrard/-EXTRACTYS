<?php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Vérification de l'authentification
if (!isAuthenticated()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}

// Vérification du jeton CSRF
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Jeton CSRF invalide']);
    exit;
}

// Récupérer l'ID du fichier
$file_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$file_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de fichier manquant']);
    exit;
}

try {
    // Instancier le gestionnaire de fichiers
    $fileManager = new FileManager($db, $_SESSION['user_id']);

    // Récupérer les informations du fichier
    $file = $fileManager->getFile($file_id);

    if (!$file) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Fichier non trouvé']);
        exit;
    }

    // Vérification que le fichier existe physiquement
    if (!file_exists($file['path'])) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Le fichier n\'existe pas physiquement sur le serveur']);
        exit;
    }

    // Déterminer le type MIME du fichier si non disponible
    $mime_type = isset($file['mime_type']) ? $file['mime_type'] : mime_content_type($file['path']);
    $filename = isset($file['name']) ? $file['name'] : basename($file['path']);

    // Définir les en-têtes pour le streaming
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file['path']));
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Envoyer le contenu du fichier
    readfile($file['path']);
    exit;
} catch (Exception $e) {
    error_log('Erreur lors du streaming du fichier: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Erreur serveur lors du streaming du fichier']);
    exit;
}
// Déboguer les problèmes de session (à retirer en production)
error_log("Session ID: " . session_id());
error_log("Session state: " . print_r($_SESSION, true));
