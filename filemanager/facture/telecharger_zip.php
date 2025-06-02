<?php
// telecharger_zip.php - Création et téléchargement d'un ZIP contenant toutes les offres générées

// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_download_zip.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT TÉLÉCHARGEMENT ZIP ===================");

// Démarrer la session si ce n'est pas déjà fait
if (!isset($_SESSION)) {
    session_start();
}

// Vérifier la présence du token
if (!isset($_GET['token'])) {
    logDebug("Erreur: Aucun token de lot fourni");
    die("Erreur: Aucun token de lot fourni");
}

$token = $_GET['token'];
logDebug("Token reçu: $token");

// Vérifier que le token existe en session
if (!isset($_SESSION['pdf_batch']) || !isset($_SESSION['pdf_batch'][$token])) {
    logDebug("Erreur: Token de lot invalide ou expiré");
    die("Erreur: Token de lot invalide ou expiré");
}

// Récupérer les informations du lot
$batch_info = $_SESSION['pdf_batch'][$token];
logDebug("Informations du lot récupérées: " . json_encode($batch_info));

// Vérifier que le lot n'a pas expiré
if (isset($batch_info['expires']) && $batch_info['expires'] < time()) {
    logDebug("Erreur: Token de lot expiré");
    die("Erreur: Le lien de téléchargement a expiré.");
}

// Vérifier qu'il y a des PDFs dans le lot
if (empty($batch_info['pdfs'])) {
    logDebug("Erreur: Aucun PDF dans le lot");
    die("Erreur: Aucun PDF disponible dans ce lot.");
}

// Créer un fichier ZIP temporaire
$zip_filename = tempnam(sys_get_temp_dir(), 'offres_zip_') . '.zip';
logDebug("Fichier ZIP temporaire: $zip_filename");

// Créer l'archive ZIP
$zip = new ZipArchive();
if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
    logDebug("Erreur: Impossible de créer le fichier ZIP");
    die("Erreur: Impossible de créer le fichier ZIP.");
}

// Ajouter chaque PDF à l'archive
$missing_files = [];
foreach ($batch_info['pdfs'] as $index => $pdf_info) {
    if (file_exists($pdf_info['path'])) {
        // Nommer le fichier dans le ZIP
        $pdf_name = 'offre_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pdf_info['prestataire']) . 
                    '_facture_' . $pdf_info['facture_id'] . '.pdf';
        
        // Ajouter le fichier au ZIP
        $zip->addFile($pdf_info['path'], $pdf_name);
        logDebug("Ajout du fichier au ZIP: {$pdf_info['path']} -> $pdf_name");
    } else {
        $missing_files[] = $pdf_info['path'];
        logDebug("Erreur: Fichier manquant: {$pdf_info['path']}");
    }
}

// Fermer l'archive ZIP
$zip->close();
logDebug("Archive ZIP fermée");

// Vérifier si tous les fichiers existent
if (count($missing_files) > 0) {
    logDebug("Avertissement: " . count($missing_files) . " fichiers manquants sur " . count($batch_info['pdfs']));
}

// Nettoyer les buffers de sortie
while (ob_get_level()) {
    ob_end_clean();
}

// Nom du fichier ZIP pour le téléchargement
$download_name = 'offres_' . date('Ymd_His') . '.zip';

// Vérifier la taille du fichier
$filesize = filesize($zip_filename);
logDebug("Taille du fichier ZIP: $filesize octets");

if ($filesize <= 0) {
    logDebug("Erreur: Fichier ZIP vide ou inaccessible");
    die("Erreur: Fichier ZIP vide ou inaccessible.");
}

// Envoyer les en-têtes pour le téléchargement
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Lire et envoyer le fichier
readfile($zip_filename);

// Supprimer le fichier ZIP temporaire
if (file_exists($zip_filename)) {
    unlink($zip_filename);
    logDebug("Fichier ZIP temporaire supprimé");
}

// Conserver le token pour permettre plusieurs téléchargements
// Si vous préférez un téléchargement unique, décommentez la ligne suivante:
// unset($_SESSION['pdf_batch'][$token]);

logDebug("ZIP téléchargé avec succès");
logDebug("=================== FIN TÉLÉCHARGEMENT ZIP ===================");