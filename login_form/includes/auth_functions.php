<?php
// filepath: C:\laragon\www\EXTRACTYS\login_form\includes\auth_functions.php

// Fonctions d'authentification

/**
 * Connecte un utilisateur
 */
function loginUser($user_id, $username)
{
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
}

/**
 * Déconnecte l'utilisateur actuel
 */
function logoutUser()
{
    // Détruire toutes les variables de session
    $_SESSION = array();

    // Si un cookie de session est utilisé, le détruire aussi
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Détruire la session
    session_destroy();
}

/**
 * Vérifie si une session est expirée
 */
function isSessionExpired()
{
    $max_lifetime = 3600; // 1 heure

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $max_lifetime)) {
        logoutUser();
        return true;
    }

    // Mettre à jour le timestamp de dernière activité
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Vérifie les identifiants de connexion
 */
function checkLogin($username, $password)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return $user['id'];
        }

        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de connexion: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre un nouvel utilisateur
 */
function registerUser($username, $password, $email)
{
    global $db;

    try {
        // Vérifier si l'utilisateur existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $stmt->execute([
            'username' => $username,
            'email' => $email
        ]);

        if ($stmt->fetchColumn() > 0) {
            return false; // Utilisateur ou email déjà existant
        }

        // Hasher le mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insérer le nouvel utilisateur
        $stmt = $db->prepare("
            INSERT INTO users (username, password, email, created_at) 
            VALUES (:username, :password, :email, NOW())
        ");

        $stmt->execute([
            'username' => $username,
            'password' => $hashed_password,
            'email' => $email
        ]);

        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'enregistrement de l'utilisateur: " . $e->getMessage());
        return false;
    }
}
