<?php
// filepath: filemanager/api/upload.php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    jsonResponse(['error' => 'Non autorisé'], 401);
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// Vérifier le token CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

try {
    // Initialiser le gestionnaire de fichiers
    $fileManager = new FileManager($db, $_SESSION['user_id']);

    // Récupérer le dossier de destination
    $folder_id = intval($_POST['folder_id'] ?? 0);

    if (!isset($_FILES['file'])) {
        jsonResponse(['error' => 'Aucun fichier téléchargé'], 400);
    }

    // Vérifier la taille du fichier
    if ($_FILES['file']['size'] > MAX_FILE_SIZE) {
        jsonResponse(['error' => 'Le fichier dépasse la taille maximale autorisée (' . formatFileSize(MAX_FILE_SIZE) . ')'], 400);
    }

    // Télécharger le fichier
    $result = $fileManager->uploadFile($_FILES['file'], $folder_id);

    jsonResponse(['success' => true, 'file' => $result]);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}
