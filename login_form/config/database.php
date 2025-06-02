<?php
// Paramètres de connexion à la base de données
$host = 'localhost';
$dbname = 'extractys'; // Assurez-vous que ce nom correspond à votre base de données
$username = 'root'; // Remplacez par votre nom d'utilisateur MySQL
$password = ''; // Remplacez par votre mot de passe MySQL

try {
    // Création de l'instance PDO avec options recommandées
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // En cas d'erreur de connexion
    die('Erreur de connexion : ' . $e->getMessage());
}

// Facultatif : Définir une fonction pour obtenir la connexion à la base de données
function getDb()
{
    global $db;
    return $db;
}
