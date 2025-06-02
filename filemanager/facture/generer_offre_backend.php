<?php
// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_python_exec.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT GÉNÉRATION OFFRE PYTHON REDIRECTION ===================");
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

// Vérifier que le script Python existe au bon endroit - SANS utiliser realpath
$python_dir = __DIR__ . '/../python';
$python_script = $python_dir . '/genereateur_offre.py';

logDebug("Répertoire Python: $python_dir");
logDebug("Script Python: $python_script");

if (!file_exists($python_script)) {
    logDebug("ERREUR: Script Python introuvable: $python_script");
    echo "Erreur: Script Python introuvable.";
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

// MÉTHODE SIMPLIFIÉE : N'utilisez pas escapeshellarg pour éviter les problèmes de caractères
// Changer de répertoire vers le dossier Python
$current_dir = getcwd();
chdir($python_dir);
logDebug("Changement de répertoire de travail: de $current_dir vers $python_dir");

// N'utiliser que le nom du fichier Python (puisque nous sommes déjà dans le bon répertoire)
// et utiliser des guillemets doubles pour le chemin du fichier JSON sous Windows
$command = 'python3 genereateur_offre.py "' . $tmp_json . '"';
logDebug("Exécution de la commande: $command");

$output = [];
$return_var = null;
exec($command . " 2>&1", $output, $return_var);

// Revenir au répertoire de travail initial
chdir($current_dir);
logDebug("Retour au répertoire de travail: $current_dir");

// Nettoyer le fichier temporaire
if (file_exists($tmp_json)) {
    unlink($tmp_json);
    logDebug("Fichier temporaire supprimé: $tmp_json");
}

// Analyser la sortie
logDebug("Code de retour: $return_var");
logDebug("Sortie (" . count($output) . " lignes):");

// Afficher les premières lignes pour un meilleur débogage
$max_lines = min(count($output), 20);
for ($i = 0; $i < $max_lines; $i++) {
    logDebug("Ligne " . ($i+1) . ": " . $output[$i]);
}

// Si python3 n'est pas trouvé, essayer avec python
if ($return_var !== 0 && (strpos(implode(" ", $output), "not recognized") !== false || 
                          strpos(implode(" ", $output), "command not found") !== false)) {
    
    logDebug("Commande python3 a échoué, essai avec python");
    
    // Retourner dans le répertoire Python
    chdir($python_dir);
    
    // Essayer avec python
    $command = 'python genereateur_offre.py "' . $tmp_json . '"';
    logDebug("Exécution de la commande alternative: $command");
    
    $output = [];
    $return_var = null;
    exec($command . " 2>&1", $output, $return_var);
    
    // Revenir au répertoire initial
    chdir($current_dir);
    
    logDebug("Code de retour (python): $return_var");
    logDebug("Sortie (python) (" . count($output) . " lignes):");
    
    for ($i = 0; $i < min(count($output), 10); $i++) {
        logDebug("Ligne " . ($i+1) . ": " . $output[$i]);
    }
}

// Chercher un JSON valide dans la sortie et un chemin PDF
$found_json = false;
$pdf_path = null;

foreach (array_reverse($output) as $line) {
    $json = json_decode($line, true);
    if ($json !== null) {
        // Si c'est une réponse de succès avec un chemin de PDF
        if (isset($json['success']) && $json['success'] === true && isset($json['pdf_path'])) {
            logDebug("JSON de succès trouvé: " . json_encode($json));
            
            // Récupérer le chemin du PDF indiqué par Python
            $pdf_rel_path = $json['pdf_path'];
            logDebug("Chemin PDF relatif trouvé: $pdf_rel_path");
            
            // CORRECTION: Traiter différents formats de chemins
            if (strpos($pdf_rel_path, '../generated_offers/') === 0) {
                // Format avec ../generated_offers/ - utiliser le dossier python
                $pdf_path = $python_dir . '/' . str_replace('../', '', $pdf_rel_path);
            }
            elseif (strpos($pdf_rel_path, 'output/') === 0) {
                // Format avec output/ - utiliser le sous-dossier output du dossier python
                $pdf_path = $python_dir . '/' . $pdf_rel_path;
            }
            else {
                // Autres formats - tenter le chemin direct
                $pdf_path = $pdf_rel_path;
                
                // Si le chemin ne commence pas par un slash ou une lettre de lecteur (comme C:)
                if (!preg_match('~^(/|[a-z]:)~i', $pdf_path)) {
                    // Essayer avec le dossier python comme base
                    $alt_path = $python_dir . '/' . $pdf_path;
                    if (file_exists($alt_path)) {
                        $pdf_path = $alt_path;
                    }
                }
            }
            
            logDebug("Chemin PDF absolu résolu: $pdf_path");
            
            $found_json = true;
            break;
        }
        // Si c'est une réponse d'erreur
        elseif (isset($json['error'])) {
            logDebug("JSON d'erreur trouvé: " . json_encode($json));
            echo "Erreur: " . $json['error'];
            exit;
        }
    }
}

// Si un PDF a été mentionné, vérifier son existence
if ($found_json && $pdf_path) {
    logDebug("Vérification de l'existence du PDF: $pdf_path");
    
    if (file_exists($pdf_path)) {
        logDebug("PDF trouvé, préparation du téléchargement: $pdf_path");
        
        // Générer un token unique pour ce téléchargement
        $token = md5(uniqid(rand(), true));
        
        // Stocker le chemin du PDF en session pour le téléchargement ultérieur
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['pdf_download'][$token] = [
            'path' => $pdf_path,
            'name' => 'offre_' . preg_replace('/[^a-zA-Z0-9]/', '_', $prestataire) . '_' . date('Ymd') . '.pdf',
            'expires' => time() + 3600 // Expire dans 1 heure
        ];
        
        logDebug("Token de téléchargement généré: $token");
        
        // SOLUTION: REDIRECTION au lieu de téléchargement direct
        // Rediriger vers une page de téléchargement avec le token
        logDebug("Redirection vers telecharger_pdf.php?token=$token");
        header("Location: telecharger_pdf.php?token=$token");
        exit;
    } else {
        logDebug("ERREUR: PDF mentionné mais introuvable: $pdf_path");
        echo "Erreur: Le PDF a été généré mais n'a pas pu être trouvé.";
    }
}
// Si aucun PDF n'a été mentionné ou trouvé
else {
    logDebug("ERREUR: Aucun PDF mentionné dans la sortie");
    echo "Erreur: Le script Python n'a pas retourné de chemin de PDF valide.";
}

logDebug("=================== FIN GÉNÉRATION OFFRE PYTHON REDIRECTION ===================");