<?php
// Script direct pour générer le PDF et forcer le téléchargement
// Version simplifiée sans AJAX ni JSON

// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_python_exec.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT GÉNÉRATION OFFRE DIRECTE ===================");
logDebug("POST data: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));

// Récupérer les données POST
$conditions = $_POST['conditions'] ?? '';
$prestataire = $_POST['prestataire'] ?? 'Client';
$montant_ttc = $_POST['montant_ttc'] ?? 100;
$economie_mensuelle = $_POST['economie_mensuelle'] ?? 10;
$economie_annuelle = $_POST['economie_annuelle'] ?? 120;

logDebug("Données récupérées:");
logDebug("- Prestataire: $prestataire");
logDebug("- Montant TTC: $montant_ttc");
logDebug("- Économie mensuelle: $economie_mensuelle");
logDebug("- Économie annuelle: $economie_annuelle");
logDebug("- Conditions: $conditions");

// Vérifier que le script Python existe
$python_dir = __DIR__ . '/../python';
$python_script = $python_dir . '/genereateur_offre.py';

logDebug("Répertoire Python: $python_dir");
logDebug("Script Python: $python_script");

if (!file_exists($python_script)) {
    logDebug("ERREUR: Script Python introuvable: $python_script");
    header("Location: generer_offre_conditions.php?error=" . urlencode("Script Python introuvable"));
    exit;
}

// Générer un fichier JSON temporaire pour les données
$tmp_json = tempnam(sys_get_temp_dir(), 'offre_') . '.json';
logDebug("Fichier JSON temporaire: $tmp_json");

$json_data = [
    'prestataire' => $prestataire,
    'montant_ttc' => $montant_ttc,
    'economie_mensuelle' => $economie_mensuelle,
    'economie_annuelle' => $economie_annuelle,
    'conditions' => $conditions,
];

file_put_contents($tmp_json, json_encode($json_data, JSON_UNESCAPED_UNICODE));
logDebug("Données JSON écrites dans le fichier temporaire");

// Changer de répertoire vers le dossier Python
$current_dir = getcwd();
chdir($python_dir);
logDebug("Changement de répertoire de travail: de $current_dir vers $python_dir");

// Exécuter Python - essayer différentes commandes
$commands = ['python3', 'python', 'py'];
$output = [];
$return_var = 1; // Échec par défaut
$used_command = '';

foreach ($commands as $cmd) {
    logDebug("Tentative avec la commande: $cmd");
    $command = "$cmd genereateur_offre.py \"$tmp_json\"";
    $output = [];
    $return_var = null;
    exec($command . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        $used_command = $cmd;
        logDebug("Commande $cmd a réussi");
        break;
    }
    
    logDebug("Code de retour pour $cmd: $return_var");
}

// Revenir au répertoire de travail initial
chdir($current_dir);
logDebug("Retour au répertoire de travail: $current_dir");

// Nettoyer le fichier temporaire
if (file_exists($tmp_json)) {
    unlink($tmp_json);
    logDebug("Fichier temporaire supprimé: $tmp_json");
}

// Vérifier si la commande a réussi
if ($return_var !== 0) {
    logDebug("ERREUR: Toutes les commandes Python ont échoué");
    logDebug("Dernière sortie: " . implode("\n", $output));
    header("Location: generer_offre_conditions.php?error=" . urlencode("Échec d'exécution de Python"));
    exit;
}

logDebug("Sortie de la commande (" . count($output) . " lignes):");
for ($i = 0; $i < min(count($output), 20); $i++) {
    logDebug("Ligne " . ($i+1) . ": " . $output[$i]);
}

// Chercher le chemin du PDF dans la sortie
$pdf_path = null;
foreach (array_reverse($output) as $line) {
    $json = json_decode($line, true);
    if ($json !== null && isset($json['success']) && $json['success'] === true && isset($json['pdf_path'])) {
        $pdf_rel_path = $json['pdf_path'];
        logDebug("Chemin PDF relatif trouvé: $pdf_rel_path");
        
        // Traiter différents formats de chemins
        if (strpos($pdf_rel_path, '../generated_offers/') === 0) {
            $pdf_path = $python_dir . '/' . str_replace('../', '', $pdf_rel_path);
        }
        elseif (strpos($pdf_rel_path, 'output/') === 0) {
            $pdf_path = $python_dir . '/' . $pdf_rel_path;
        }
        else {
            $pdf_path = $pdf_rel_path;
            if (!preg_match('~^(/|[a-z]:)~i', $pdf_path)) {
                $alt_path = $python_dir . '/' . $pdf_path;
                if (file_exists($alt_path)) {
                    $pdf_path = $alt_path;
                }
            }
        }
        
        logDebug("Chemin PDF absolu résolu: $pdf_path");
        break;
    }
}

// Vérifier si le PDF a été trouvé et existe
if ($pdf_path && file_exists($pdf_path)) {
    logDebug("PDF trouvé, préparation du téléchargement: $pdf_path");
    
    // Forcer le téléchargement direct du PDF
    $download_filename = 'offre_' . preg_replace('/[^a-zA-Z0-9]/', '_', $prestataire) . '_' . date('Ymd') . '.pdf';
    
    // Nettoyer tous les buffers de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Vérifier la taille du fichier
    $filesize = filesize($pdf_path);
    logDebug("Taille du fichier PDF: $filesize octets");
    
    if ($filesize <= 0) {
        logDebug("ERREUR: Fichier PDF vide ou inaccessible");
        header("Location: generer_offre_conditions.php?error=" . urlencode("Fichier PDF généré invalide"));
        exit;
    }
    
    // Envoyer les en-têtes pour le téléchargement
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $filesize);
    
    // Lire et envoyer le fichier
    if ($f = fopen($pdf_path, 'rb')) {
        while (!feof($f) && connection_status() == 0) {
            echo fread($f, 1024 * 8);
            flush();
        }
        fclose($f);
        logDebug("PDF téléchargé avec succès");
    } else {
        logDebug("ERREUR: Impossible d'ouvrir le fichier PDF pour lecture");
        header("Location: generer_offre_conditions.php?error=" . urlencode("Impossible d'ouvrir le fichier PDF"));
    }
} else {
    logDebug("ERREUR: PDF non trouvé ou n'existe pas: " . ($pdf_path ?: "chemin non trouvé"));
    header("Location: generer_offre_conditions.php?error=" . urlencode("PDF non trouvé ou introuvable"));
}

logDebug("=================== FIN GÉNÉRATION OFFRE DIRECTE ===================");
