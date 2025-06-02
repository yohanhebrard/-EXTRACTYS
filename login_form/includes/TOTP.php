<?php

/**
 * Classe TOTP simplifiée pour l'authentification à deux facteurs
 * Basée sur RFC 6238
 */
class TOTP
{
    private $secret;
    private $issuer;
    private $label;
    private $digits = 6;
    private $period = 30;
    private $algorithm = 'sha1';

    /**
     * Crée une nouvelle instance TOTP
     * 
     * @param string $secret Secret en base32 (optionnel)
     * @return TOTP
     */
    public static function create($secret = null)
    {
        return new self($secret);
    }

    /**
     * Constructeur
     * 
     * @param string $secret Secret en base32
     */
    public function __construct($secret = null)
    {
        $this->secret = $secret ?: self::generateSecret();
    }

    /**
     * Définit le label (nom d'utilisateur)
     * 
     * @param string $label Label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Définit l'émetteur (nom de l'application)
     * 
     * @param string $issuer Émetteur
     * @return $this
     */
    public function setIssuer($issuer)
    {
        $this->issuer = $issuer;
        return $this;
    }

    /**
     * Récupère le secret
     * 
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Vérifie un code TOTP
     * 
     * @param string $code Code à vérifier
     * @param int $window Fenêtre de tolérance (en périodes)
     * @return bool
     */
    public function verify($code, $window = 1)
    {
        if (strlen($code) !== $this->digits) {
            return false;
        }

        $timestamp = time();

        // Vérifier dans la fenêtre de tolérance
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = $this->generateCode($timestamp + ($i * $this->period));
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Génère l'URI de provisioning pour QR code
     * 
     * @return string
     */
    public function getProvisioningUri()
    {
        $label = $this->label ?: 'unknown';
        $issuer = $this->issuer ?: 'EXTRACTYS';

        $params = [
            'secret' => $this->secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'period' => $this->period,
            'digits' => $this->digits
        ];

        $query = http_build_query($params);

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($label),
            $query
        );
    }

    /**
     * Génère un code TOTP pour un timestamp donné
     * 
     * @param int $timestamp Timestamp
     * @return string Code
     */
    private function generateCode($timestamp)
    {
        $counter = floor($timestamp / $this->period);
        $counterBin = pack('N*', 0, $counter); // 64-bit counter value

        $key = $this->base32Decode($this->secret);
        $hash = hash_hmac($this->algorithm, $counterBin, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $binary % pow(10, $this->digits);
        return str_pad($code, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Génère un secret aléatoire en base32
     * 
     * @param int $length Longueur du secret en octets
     * @return string Secret en base32
     */
    private static function generateSecret($length = 16)
    {
        $secret = random_bytes($length);
        return self::base32Encode($secret);
    }

    /**
     * Décode une chaîne base32
     * 
     * @param string $base32 Chaîne base32
     * @return string Données décodées
     */
    private function base32Decode($base32)
    {
        $base32 = strtoupper($base32);
        $base32 = str_replace('=', '', $base32);

        $lookup = [
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            'E' => 4,
            'F' => 5,
            'G' => 6,
            'H' => 7,
            'I' => 8,
            'J' => 9,
            'K' => 10,
            'L' => 11,
            'M' => 12,
            'N' => 13,
            'O' => 14,
            'P' => 15,
            'Q' => 16,
            'R' => 17,
            'S' => 18,
            'T' => 19,
            'U' => 20,
            'V' => 21,
            'W' => 22,
            'X' => 23,
            'Y' => 24,
            'Z' => 25,
            '2' => 26,
            '3' => 27,
            '4' => 28,
            '5' => 29,
            '6' => 30,
            '7' => 31
        ];

        $length = strlen($base32);
        $n = 0;
        $bitLen = 0;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $n = ($n << 5) | $lookup[$base32[$i]];
            $bitLen += 5;

            while ($bitLen >= 8) {
                $bitLen -= 8;
                $result .= chr(($n >> $bitLen) & 0xFF);
            }
        }

        return $result;
    }

    /**
     * Encode des données en base32
     * 
     * @param string $data Données à encoder
     * @return string Chaîne base32
     */
    private static function base32Encode($data)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        $segments = str_split($binary, 5);

        foreach ($segments as $segment) {
            if (strlen($segment) < 5) {
                $segment = str_pad($segment, 5, '0', STR_PAD_RIGHT);
            }
            $result .= $alphabet[bindec($segment)];
        }

        return $result;
    }
}
