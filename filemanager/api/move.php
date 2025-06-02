<?php
// filepath: filemanager/api/move.php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';  // Ce fichier contient déjà jsonResponse()
require_once '../includes/FileManager.php';

// Activer le reporting d'erreurs pour le débogage
ini_set('display_errors', 0);  // Désactiver l'affichage des erreurs (pour produire du JSON uniquement)
error_reporting(E_ALL);        // Mais continuer à les enregistrer

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
$type = isset($_POST['type']) ? $_POST['type'] : '';
$destination_id = isset($_POST['destination_id']) ? intval($_POST['destination_id']) : 0;

// Validation des paramètres
if (!$id || !$type || !$destination_id) {
    jsonResponse(['error' => 'Paramètres manquants ou invalides'], 400);
    exit;
}

// Vérifier que le type est valide
if ($type !== 'file' && $type !== 'folder') {
    jsonResponse(['error' => 'Type invalide. Utilisez "file" ou "folder"'], 400);
    exit;
}

try {
    $fileManager = new FileManager($db, $_SESSION['user_id']);

    if ($type === 'file') {
        // Déplacer un fichier
        $result = $fileManager->moveFile($id, $destination_id);
    } else {
        // Déplacer un dossier
        $result = $fileManager->moveFolder($id, $destination_id);
    }

    if ($result) {
        jsonResponse([
            'success' => true,
            'message' => ($type === 'file' ? 'Fichier' : 'Dossier') . ' déplacé avec succès'
        ]);
    } else {
        jsonResponse(['error' => 'Échec du déplacement'], 400);
    }
} catch (Exception $e) {
    // Journaliser l'erreur pour le débogage
    error_log("Erreur dans move.php: " . $e->getMessage());

    // Renvoyer une réponse JSON avec le message d'erreur
    jsonResponse(['error' => $e->getMessage()], 400);
}
