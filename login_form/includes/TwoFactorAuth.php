<?php
require_once 'session.php';
require_once __DIR__ . '/TOTP.php'; // Utiliser notre implémentation locale

class TwoFactorAuth
{
    private $db;

    public function __construct($pdo)
    {
        if (!($pdo instanceof PDO)) {
            throw new Exception("La connexion à la base de données n'est pas valide");
        }
        $this->db = $pdo;
    }

    /**
     * Vérifie si l'utilisateur a activé la 2FA
     */
    public function isEnabled($userId)
    {
        $stmt = $this->db->prepare("SELECT two_factor_enabled FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['two_factor_enabled'] == 1;
    }

    /**
     * Génère un nouveau secret TOTP pour l'utilisateur
     */
    public function generateSecret($userId, $username)
    {
        // Créer un nouvel objet TOTP
        $totp = TOTP::create();
        $totp->setLabel($username);
        $totp->setIssuer('EXTRACTYS'); // Remplacez par le nom de votre application

        // Récupérer le secret
        $secret = $totp->getSecret();

        // Sauvegarder temporairement le secret dans la session
        $_SESSION['temp_2fa_secret'] = $secret;

        return $secret;
    }

    /**
     * Obtient l'URL provisionnement pour le QR code
     */
    public function getProvisioningUrl($username, $secret)
    {
        $totp = TOTP::create($secret);
        $totp->setLabel($username);
        $totp->setIssuer('EXTRACTYS'); // Remplacez par le nom de votre application

        return $totp->getProvisioningUri();
    }

    /**
     * Vérifie le code TOTP fourni par l'utilisateur
     */
    public function verifyCode($secret, $code)
    {
        $totp = TOTP::create($secret);
        return $totp->verify($code);
    }

    /**
     * Active la 2FA pour l'utilisateur
     */
    public function enable($userId, $secret)
    {
        $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = :secret, two_factor_enabled = 1 WHERE id = :id");
        $stmt->bindParam(':secret', $secret);
        $stmt->bindParam(':id', $userId);

        return $stmt->execute();
    }

    /**
     * Obtient le secret 2FA de l'utilisateur
     */
    public function getSecret($userId)
    {
        $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['two_factor_secret'] : null;
    }
}
