<?php
/**
 * Classe simple pour générer un QR code sans dépendances externes
 */
class QRCode {
    /**
     * Génère une URL vers un QR code en utilisant l'API Goqr.me
     *
     * @param string $data Les données à encoder dans le QR code
     * @param int $size Taille du QR code en pixels
     * @return string L'URL du QR code
     */
    public static function getQrCodeUrl($data, $size = 200) {
        // Utiliser l'API Goqr.me qui ne nécessite pas de clé API
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);
    }
    
    /**
     * Génère un QR code et retourne une balise img
     *
     * @param string $data Les données à encoder dans le QR code
     * @param int $size Taille du QR code en pixels
     * @return string Balise HTML img
     */
    public static function getQrCodeImage($data, $size = 200) {
        $url = self::getQrCodeUrl($data, $size);
        return '<img src="' . htmlspecialchars($url) . '" alt="QR Code" width="' . $size . '" height="' . $size . '">';
    }
}