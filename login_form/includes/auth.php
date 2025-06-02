<?php
require_once 'session.php';
require_once __DIR__ . '/../config/database.php';
require_once 'TwoFactorAuth.php';

class Auth
{
    private $db;
    private $twoFactor;

    public function __construct($pdo)
    {
        // Vérifier que $pdo est bien une instance de PDO
        if (!($pdo instanceof PDO)) {
            throw new Exception("La connexion à la base de données n'est pas valide");
        }
        $this->db = $pdo;
        $this->twoFactor = new TwoFactorAuth($pdo);
    }

    public function login($email_or_username, $password, $remember = false)
    {
        // Ajoutons des logs pour déboguer
        error_log("Tentative de connexion avec : " . $email_or_username);

        // Vérifier si l'entrée est un email ou un nom d'utilisateur
        $isEmail = filter_var($email_or_username, FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'username';

        // Préparer la requête SQL
        $sql = "SELECT * FROM users WHERE {$field} = :input LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':input', $email_or_username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: Vérifier si l'utilisateur est trouvé
        if (!$user) {
            error_log("Utilisateur non trouvé pour : " . $email_or_username);
            return false;
        }

        // Debug: Vérifier la valeur du mot de passe haché stocké
        error_log("Mot de passe haché stocké : " . $user['password']);

        // Vérifier si le mot de passe est correct
        if (password_verify($password, $user['password'])) {
            error_log("Mot de passe vérifié avec succès");

            // Stocker les informations utilisateur dans la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'];

            // Vérifier si la 2FA est activée pour cet utilisateur
            if ($this->twoFactor->isEnabled($user['id'])) {
                // Marquer que l'utilisateur a besoin de vérifier la 2FA
                $_SESSION['awaiting_2fa'] = true;
                // Ne pas définir 'logged_in' à true avant que la 2FA soit vérifiée
                return 'require_2fa';
            } else {
                // La 2FA n'est pas activée, mais elle est obligatoire
                // Stocker les informations nécessaires pour la configuration
                $_SESSION['setup_2fa'] = true;
                return 'require_setup_2fa';
            }

            // Le code pour "se souvenir de moi" est ici mais sera exécuté après la 2FA
        } else {
            error_log("Échec de vérification du mot de passe pour : " . $email_or_username);
            return false;
        }
    }

    public function completeLogin($remember = false)
    {
        // Cette méthode est appelée une fois que la 2FA a été vérifiée avec succès
        $_SESSION['logged_in'] = true;
        unset($_SESSION['awaiting_2fa']);

        // Gérer l'option "se souvenir de moi"
        if ($remember) {
            $token = bin2hex(random_bytes(50));
            $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30 jours

            // Sauvegarder le token dans la base de données
            $sql = "UPDATE users SET remember_token = :token WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();

            // Créer un cookie sécurisé
            setcookie(
                'remember_token',
                $token,
                time() + 60 * 60 * 24 * 30, // 30 jours
                '/',
                '',
                true, // Seulement sur HTTPS
                true  // HttpOnly
            );
        }

        return true;
    }

    public function logout()
    {
        // Supprimer le token de la DB si existant
        if (isset($_SESSION['user_id'])) {
            $sql = "UPDATE users SET remember_token = NULL WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
        }

        // Supprimer le cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        // Détruire la session
        $_SESSION = [];
        session_destroy();
    }

    public function checkRememberToken()
    {
        if (isset($_COOKIE['remember_token']) && !isset($_SESSION['logged_in'])) {
            $token = $_COOKIE['remember_token'];

            $sql = "SELECT * FROM users WHERE remember_token = :token LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Ne pas connecter automatiquement l'utilisateur si la 2FA est activée
                if ($this->twoFactor->isEnabled($user['id'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['awaiting_2fa'] = true;
                    return 'require_2fa';
                } else {
                    // La 2FA n'est pas activée, mais elle est obligatoire
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['setup_2fa'] = true;
                    return 'require_setup_2fa';
                }
            }
        }

        return false;
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function requiresTwoFactor()
    {
        return isset($_SESSION['awaiting_2fa']) && $_SESSION['awaiting_2fa'] === true;
    }

    public function requiresSetupTwoFactor()
    {
        return isset($_SESSION['setup_2fa']) && $_SESSION['setup_2fa'] === true;
    }

    public function getTwoFactorAuth()
    {
        return $this->twoFactor;
    }
}

// Autres fonctions d'authentification...

// Ajouter cette fonction
function isLoggedIn()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
