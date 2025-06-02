<?php
require_once '../init.php';
require_once '../includes/session.php';
require_once '../includes/Auth.php';

// Créer une instance de Auth
$auth = new Auth($db);

// Déconnecter l'utilisateur
$auth->logout();

// Rediriger vers la page de connexion
header('Location: login.php');
exit;
