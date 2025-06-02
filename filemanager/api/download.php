<?php
// filepath: filemanager/api/download.php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Non autorisé');
}

// Vérifier le token CSRF
if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Token CSRF invalide');
}

try {
    // Initialiser le gestionnaire de fichiers
    $fileManager = new FileManager($db, $_SESSION['user_id']);
    
    // Récupérer l'ID du fichier
    $file_id = intval($_GET['id'] ?? 0);
    
    if ($file_id <= 0) {
        header('HTTP/1.1 400 Bad Request');
        exit('ID de fichier invalide');
    }
    
    // Récupérer les informations du fichier
    $file = $fileManager->getFile($file_id);
    
    // Vérifier si le fichier existe
    if (!file_exists($file['path'])) {
        header('HTTP/1.1 404 Not Found');
        exit('Fichier non trouvé');
    }
    
    // Déterminer le type MIME
    $mime_type = mime_content_type($file['path']);
    
    // Pour l'aperçu ou le téléchargement
    $disposition = isset($_GET['preview']) ? 'inline' : 'attachment';
    
    // Envoyer les en-têtes HTTP
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: ' . $disposition . '; filename="' . $file['info']['name'] . '"');
    header('Content-Length: ' . filesize($file['path']));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Envoyer le fichier
    readfile($file['path']);
    exit;
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    exit($e->getMessage());
}