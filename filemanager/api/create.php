<?php
// filepath: filemanager/api/create.php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Activer un mode debug explicite
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Journalisation de débogage
$debugLog = [];
$debugLog[] = "Requête démarrée";
$debugLog[] = "Méthode: " . $_SERVER['REQUEST_METHOD'];
$debugLog[] = "POST: " . print_r($_POST, true);

// Vérifier l'authentification
if (!isAuthenticated()) {
    $debugLog[] = "Non authentifié";
    jsonResponse(['error' => 'Non autorisé', 'debug' => $debugLog], 401);
}

$debugLog[] = "Authentifié: " . $_SESSION['user_id'];

// Capture des données brutes POST pour débogage
$rawPostData = file_get_contents("php://input");
$debugLog[] = "Données POST brutes: " . $rawPostData;

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $debugLog[] = "Méthode non autorisée: " . $_SERVER['REQUEST_METHOD'];
    jsonResponse(['error' => 'Méthode non autorisée', 'debug' => $debugLog], 405);
}

// Récupérer les paramètres
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

$debugLog[] = "Nom reçu: '{$name}'";
$debugLog[] = "Parent ID: {$parent_id}";
$debugLog[] = "Token CSRF: " . substr($csrf_token, 0, 5) . "...";

// Validation explicite
if (empty($name)) {
    $debugLog[] = "ERREUR: Nom vide";
    jsonResponse(['error' => 'Le nom du dossier est vide', 'debug' => $debugLog], 400);
}

// Vérifier token CSRF seulement si non vide
if (!empty($csrf_token) && !validateCSRFToken($csrf_token)) {
    $debugLog[] = "ERREUR: Token CSRF invalide";
    jsonResponse(['error' => 'Token CSRF invalide', 'debug' => $debugLog], 403);
}

try {
    // Initialiser le gestionnaire de fichiers
    $fileManager = new FileManager($db, $_SESSION['user_id']);
    $debugLog[] = "FileManager initialisé";

    // Créer un dossier
    $result = $fileManager->createFolder($name, $parent_id);
    $debugLog[] = "Dossier créé avec succès: " . json_encode($result);

    jsonResponse(['success' => true, 'folder' => $result, 'debug' => $debugLog]);
} catch (Exception $e) {
    $debugLog[] = "EXCEPTION: " . $e->getMessage();
    error_log("Erreur création dossier: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage(), 'debug' => $debugLog], 400);
}
