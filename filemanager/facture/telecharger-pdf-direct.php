<?php
// telecharger_pdf_direct.php - Téléchargement direct d'un PDF par chemin

// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_download_direct.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT TÉLÉCHARGEMENT PDF DIRECT ===================");

// Vérifier la présence du chemin
if (!isset($_GET['path'])) {
    logDebug("Erreur: Aucun chemin de fichier fourni");
    die("Erreur: Aucun chemin de fichier fourni");
}

$file_path = $_GET['path'];
logDebug("Chemin reçu: $file_path");

// Vérifier que le fichier existe
if (!file_exists($file_path)) {
    logDebug("Erreur: Le fichier PDF n'existe pas: $file_path");
    die("Erreur: Le fichier PDF n'existe pas.");
}

// Nom du fichier pour le téléchargement
$download_name = isset($_GET['name']) ? $_GET['name'] : 'offre.pdf';
logDebug("Nom de téléchargement: $download_name");

// Nettoyer les buffers de sortie
while (ob_get_level()) {
    ob_end_clean();
}

// Vérifier la taille du fichier
$filesize = filesize($file_path);
logDebug("Taille du fichier: $filesize octets");

if ($filesize <= 0) {
    logDebug("Erreur: Fichier vide ou inaccessible");
    die("Erreur: Fichier vide ou inaccessible.");
}

// Envoyer les en-têtes pour le téléchargement
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Lire et envoyer le fichier
if ($f = fopen($file_path, 'rb')) {
    while (!feof($f) && connection_status() == 0) {
        echo fread($f, 1024 * 8);
        flush();
    }
    fclose($f);
    logDebug("PDF téléchargé avec succès");
} else {
    logDebug("Erreur: Impossible d'ouvrir le fichier PDF pour lecture");
    die("Erreur: Impossible d'ouvrir le fichier PDF.");
}

logDebug("=================== FIN TÉLÉCHARGEMENT PDF DIRECT ===================");