<?php
// Gestion des erreurs (à adapter selon l'environnement)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers de configuration et fonctions
require_once 'config/database.php';
require_once 'includes/session.php'; // Session démarrée ici
require_once 'includes/functions.php';
require_once 'includes/auth.php'; // Pas de démarrage de session ici

// Inclure le fichier d'autoload à la racine
require_once dirname(__DIR__) . '../autoload.php';

// Vérifier si une session est déjà active avant d'en démarrer une nouvelle
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prolonger la durée de la session à chaque requête
session_regenerate_id(false); // Régénère l'ID de session sans effacer les données
$_SESSION['last_activity'] = time(); // Mettre à jour le timestamp de dernière activité

// Vérifier si la session a expiré (après 30 minutes d'inactivité)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // La session a expiré, détruire la session
    session_unset();
    session_destroy();

    // Rediriger vers la page de connexion avec un message d'erreur
    if (!headers_sent()) {
        header('Location: /EXTRACTYS/login_form/login.php?error=session_expired');
        exit;
    }
}

// Code spécifique à l'initialisation du module de connexion
// (si nécessaire)
