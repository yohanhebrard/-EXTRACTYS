<?php
// filepath: filemanager/api/delete.php
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

    // Récupérer les paramètres
    $id = intval($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'folder' ou 'file'

    if ($id <= 0) {
        jsonResponse(['error' => 'ID invalide'], 400);
    }

    if ($type === 'folder') {
        // Supprimer un dossier
        $result = $fileManager->deleteFolder($id);
        jsonResponse(['success' => true]);
    } elseif ($type === 'file') {
        // Supprimer un fichier
        $result = $fileManager->deleteFile($id);
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Type non valide'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}
