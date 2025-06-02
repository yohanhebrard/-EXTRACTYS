<?php
// filepath: filemanager/api/rename.php
// Démarrage explicite de la session
session_start();

// Journalisation des informations de session pour débogage
error_log("Session ID: " . session_id());
error_log("Session Data: " . print_r($_SESSION, true));

require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    jsonResponse(['error' => 'Non autorisé'], 401);
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
    exit;
}

// Vérifier le token CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
    exit;
}

// Récupérer les paramètres
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
$type = isset($_POST['type']) ? $_POST['type'] : '';

// Validation
if (!$id || empty($newName) || empty($type)) {
    jsonResponse(['error' => 'Paramètres invalides']);
    exit;
}

try {
    $fileManager = new FileManager($db, $_SESSION['user_id']);

    // Récupérer les informations actuelles de l'élément
    $itemInfo = ($type === 'file')
        ? $fileManager->getFileById($id)
        : $fileManager->getFolderById($id);

    if (!$itemInfo) {
        jsonResponse(['error' => 'Élément non trouvé']);
        exit;
    }

    // Récupérer le chemin physique actuel
    $currentPath = $fileManager->getPhysicalPath($id, $type);

    // Calculer le nouveau chemin
    $directory = dirname($currentPath);
    $newPath = $directory . '/' . $newName;

    // Vérifier si le nouveau nom existe déjà physiquement
    if (file_exists($newPath)) {
        jsonResponse(['error' => 'Un fichier ou dossier avec ce nom existe déjà']);
        exit;
    }

    // Renommer dans la base de données
    $result = $fileManager->renameItem($id, $newName, $type);

    if ($result) {
        // Renommer le fichier/dossier physique
        if (file_exists($currentPath)) {
            if (!rename($currentPath, $newPath)) {
                // Si le renommage physique échoue, annuler le changement en BDD
                $fileManager->renameItem($id, $itemInfo['name'], $type);
                jsonResponse(['error' => 'Impossible de renommer le fichier physique']);
                exit;
            }
        }

        jsonResponse([
            'success' => true,
            'new_name' => $newName,
            'message' => ($type === 'file' ? 'Fichier' : 'Dossier') . ' renommé avec succès'
        ]);
    } else {
        jsonResponse(['error' => 'Échec du renommage dans la base de données']);
    }
} catch (Exception $e) {
    error_log("Erreur lors du renommage: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()]);
}
