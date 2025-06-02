<?php
// filepath: C:\laragon\www\EXTRACTYS\autoload.php

// Définition des constantes de chemins
define('ROOT_DIR', __DIR__);
define('CONFIG_DIR', ROOT_DIR . '/config');
define('LOGIN_DIR', ROOT_DIR . '/login_form');
define('FILEMANAGER_DIR', ROOT_DIR . '/filemanager');
define('STORAGE_DIR', ROOT_DIR . '/storage');

// Démarrer la session si elle n'est pas active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de base de données
function getDatabase()
{
    static $db = null;

    if ($db === null) {
        $db_host = 'localhost';
        $db_name = 'extractys'; // Remplacez par le nom de votre base de données
        $db_user = 'root';      // Remplacez par votre nom d'utilisateur
        $db_pass = '';          // Remplacez par votre mot de passe

        try {
            $db = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            // Définir le mode d'encodage UTF-8
            $db->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    return $db;
}

// Fonction pour vérifier si l'utilisateur est authentifié
function isAuthenticated()
{
    return isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
}

// Fonction pour rediriger vers la page de connexion
function redirectToLogin()
{
    header('Location: ' . LOGIN_DIR . '/login.php');
    exit;
}

// Fonction pour générer un token CSRF
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour valider un token CSRF
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Obtenir l'instance de base de données
$db = getDatabase();

// Charger les fonctions d'authentification
require_once LOGIN_DIR . '/includes/auth_functions.php';
