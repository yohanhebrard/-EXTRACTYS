<?php

/**
 * Vérifie le code 2FA pour un utilisateur donné
 * 
 * @param int $userId L'identifiant de l'utilisateur
 * @param string $code Le code 2FA saisi
 * @return bool True si le code est valide, False sinon
 */
function verifyTfaCode($userId, $code)
{
    global $db;

    try {
        // Récupérer le secret 2FA de l'utilisateur
        $stmt = $db->prepare('SELECT tfa_secret FROM users WHERE id = :userId');
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['tfa_secret'])) {
            error_log("Secret 2FA non trouvé pour l'utilisateur ID: $userId");
            return false;
        }

        // Option 1: Si vous avez un code à usage unique envoyé par email ou SMS
        // Vérifiez simplement que le code correspond et qu'il n'est pas expiré
        $isValid = hash_equals($user['tfa_secret'], $code);

        // Journalisation
        if ($isValid) {
            error_log("Code 2FA validé pour l'utilisateur ID: $userId");
        } else {
            error_log("Échec de validation du code 2FA pour l'utilisateur ID: $userId");
        }

        return $isValid;
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification 2FA: " . $e->getMessage());
        return false;
    }
}

/**
 * Implémentation personnalisée de TOTP si vous avez besoin de TOTP (Time-based One-Time Password)
 * 
 * @param int $userId L'identifiant de l'utilisateur
 * @param string $code Le code TOTP saisi
 * @return bool True si le code est valide, False sinon
 */
function verifyTotpCode($userId, $code)
{
    global $db;

    try {
        // Récupérer le secret TOTP de l'utilisateur
        $stmt = $db->prepare('SELECT tfa_secret FROM users WHERE id = :userId');
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['tfa_secret'])) {
            return false;
        }

        $secret = $user['tfa_secret'];

        // Algorithme TOTP simplifié
        // Valider pour l'heure actuelle et +/- 30 secondes (pour tenir compte des décalages d'horloge)
        $currentTime = floor(time() / 30);

        for ($t = $currentTime - 1; $t <= $currentTime + 1; $t++) {
            $calculatedCode = calculateTOTP($secret, $t);
            if (hash_equals($calculatedCode, $code)) {
                error_log("Code TOTP validé pour l'utilisateur ID: $userId");
                return true;
            }
        }

        error_log("Échec de validation du code TOTP pour l'utilisateur ID: $userId");
        return false;
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification TOTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule un code TOTP basé sur un secret et un compteur de temps
 * Cette implémentation est simplifiée, dans un environnement de production 
 * vous devriez utiliser une bibliothèque éprouvée
 * 
 * @param string $secret Le secret en base32
 * @param int $timeCounter Le compteur de temps
 * @return string Le code TOTP
 */
function calculateTOTP($secret, $timeCounter)
{
    // Convertir le secret base32 en binaire
    $secretBinary = base32_decode($secret);

    // Préparer le compteur (8 octets, big-endian)
    $timeCounter = pack('N*', 0, $timeCounter);

    // Calculer le HMAC-SHA1
    $hash = hash_hmac('sha1', $timeCounter, $secretBinary, true);

    // Extraction dynamique du code (RFC 6238)
    $offset = ord($hash[19]) & 0x0F;
    $truncatedHash = (
        ((ord($hash[$offset + 0]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;

    // Formater avec des zéros au début si nécessaire
    return str_pad($truncatedHash, 6, '0', STR_PAD_LEFT);
}

/**
 * Décode une chaîne base32
 * 
 * @param string $base32 La chaîne encodée en base32
 * @return string La chaîne décodée
 */
function base32_decode($base32)
{
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper($base32);
    $buffer = 0;
    $bufferBits = 0;
    $result = '';

    for ($i = 0; $i < strlen($base32); $i++) {
        $char = $base32[$i];
        if ($char === '=') break; // Padding

        $val = strpos($base32chars, $char);
        if ($val === false) continue; // Ignorer les caractères invalides

        $buffer = ($buffer << 5) | $val;
        $bufferBits += 5;

        if ($bufferBits >= 8) {
            $bufferBits -= 8;
            $result .= chr(($buffer >> $bufferBits) & 0xFF);
        }
    }

    return $result;
}

/**
 * Valide un jeton CSRF
 * 
 * @param string $token Le jeton CSRF à valider
 * @return bool True si le jeton est valide, False sinon
 */
function validateCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

