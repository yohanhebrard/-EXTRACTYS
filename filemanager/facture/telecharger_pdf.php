<?php
// Script de téléchargement direct de PDF
// Ce fichier doit être placé à côté de generer_offre_backend.php

// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_download.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT TÉLÉCHARGEMENT PDF ===================");

// Démarrer la session si ce n'est pas déjà fait
if (!isset($_SESSION)) {
    session_start();
}

// Vérifier la présence du token
if (!isset($_GET['token'])) {
    logDebug("Erreur: Aucun token de téléchargement fourni");
    die("Erreur: Aucun token de téléchargement fourni");
}

$token = $_GET['token'];
logDebug("Token reçu: $token");

// Vérifier que le token existe en session
if (!isset($_SESSION['pdf_download']) || !isset($_SESSION['pdf_download'][$token])) {
    logDebug("Erreur: Token de téléchargement invalide ou expiré");
    die("Erreur: Token de téléchargement invalide ou expiré");
}

// Récupérer les informations du PDF
$pdf_info = $_SESSION['pdf_download'][$token];
logDebug("Informations PDF récupérées: " . json_encode($pdf_info));

// Vérifier que le fichier existe toujours
if (!file_exists($pdf_info['path'])) {
    logDebug("Erreur: Le fichier PDF n'existe plus: {$pdf_info['path']}");
    die("Erreur: Le fichier PDF n'est plus disponible.");
}

// Vérifier que le token n'a pas expiré
if (isset($pdf_info['expires']) && $pdf_info['expires'] < time()) {
    logDebug("Erreur: Token de téléchargement expiré");
    die("Erreur: Le lien de téléchargement a expiré.");
}

// Nettoyer les buffers de sortie
if (ob_get_level()) ob_end_clean();

// Envoyer les en-têtes pour le téléchargement
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $pdf_info['name'] . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($pdf_info['path']));

// Lire et envoyer le fichier
readfile($pdf_info['path']);

// Supprimer le token après utilisation (téléchargement unique)
unset($_SESSION['pdf_download'][$token]);

logDebug("Téléchargement réussi: {$pdf_info['name']}");
logDebug("=================== FIN TÉLÉCHARGEMENT PDF ===================");