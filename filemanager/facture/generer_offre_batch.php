<?php
// generer_offre_batch.php - Traitement par lot de génération d'offres
// Ce fichier gère la génération d'une offre individuelle pour chaque facture

require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Fonction de journalisation pour le débogage
function logDebug($message) {
    $log_file = __DIR__ . '/debug_batch.log';
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Démarrer le log
logDebug("=================== DÉBUT TRAITEMENT PAR LOT ===================");

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: ../../login_form/public/login.php');
    exit;
}

// Récupérer les IDs de factures depuis l'URL
$facture_ids = [];
if (isset($_GET['ids'])) {
    $facture_ids = array_map('intval', explode(',', $_GET['ids']));
    logDebug("IDs récupérés: " . implode(', ', $facture_ids));
}

// Si aucun ID, rediriger avec une erreur
if (empty($facture_ids)) {
    logDebug("Aucun ID de facture spécifié");
    header("Location: factures_telecom.php?error=" . urlencode("Aucune facture sélectionnée"));
    exit;
}

// Récupérer les données des factures
$factures = [];
$user_id = $_SESSION['user_id'];

if (!empty($facture_ids)) {
    $in = str_repeat('?,', count($facture_ids) - 1) . '?';
    $query = "
        SELECT a.*, f.name as filename, f.path as filepath
        FROM pdf_analyses a
        JOIN files f ON a.pdf_file_id = f.id 
        WHERE a.id IN ($in) AND f.user_id = ?
        ORDER BY a.id DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([...$facture_ids, $user_id]);
    $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logDebug("Nombre de factures récupérées: " . count($factures));
}

// Si aucune facture trouvée
if (empty($factures)) {
    logDebug("Aucune facture trouvée pour les IDs spécifiés");
    header("Location: factures_telecom.php?error=" . urlencode("Aucune facture trouvée"));
    exit;
}

// Traitement de la génération par lot si le formulaire est soumis
$message = "";
$error = "";
$generatedPDFs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    logDebug("Demande de génération de PDFs par lot");
    
    // Préparer un dossier temporaire pour les PDFs
    $batch_id = uniqid();
    $temp_dir = sys_get_temp_dir() . '/offres_' . $batch_id;
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    logDebug("Dossier temporaire créé: $temp_dir");
    
    // Traiter chaque facture
    foreach ($factures as $facture) {
        logDebug("Traitement de la facture ID: " . $facture['id']);
        
        // Calculer les économies (exemple: 20% d'économie)
        $montant_ttc = floatval($facture['montant_ttc'] ?? 0);
        $economie_mensuelle = round($montant_ttc * 0.2, 2); // 20% d'économie
        $economie_annuelle = round($economie_mensuelle * 12, 2);
        
        // Préparer les données pour le script Python
        $json_data = [
            'prestataire' => $facture['prestataire'] ?? 'Opérateur',
            'montant_ttc' => $montant_ttc,
            'economie_mensuelle' => $economie_mensuelle,
            'economie_annuelle' => $economie_annuelle,
            'conditions' => $_POST['conditions'] ?? '',
            'facture_id' => $facture['id'],
            'nom' => $facture['nom'] ?? '',
            'prenom' => $facture['prenom'] ?? '',
            'email' => $facture['email'] ?? '',
            'reference' => $facture['reference'] ?? '',
            'batch_mode' => true,
            'output_dir' => $temp_dir,
        ];
        
        // Générer un fichier JSON temporaire
        $tmp_json = tempnam(sys_get_temp_dir(), 'offre_' . $facture['id'] . '_') . '.json';
        file_put_contents($tmp_json, json_encode($json_data, JSON_UNESCAPED_UNICODE));
        logDebug("JSON créé pour la facture {$facture['id']}: $tmp_json");
        
        // Exécuter le script Python
        $python_dir = __DIR__ . '/../python';
        $current_dir = getcwd();
        chdir($python_dir);
        
        // Essayer différentes commandes Python
        $commands = ['python3', 'python', 'py'];
        $output = [];
        $return_var = 1;
        $success = false;
        
        foreach ($commands as $cmd) {
            $command = "$cmd genereateur_offre.py \"$tmp_json\"";
            $output = [];
            $return_var = null;
            exec($command . " 2>&1", $output, $return_var);
            
            if ($return_var === 0) {
                $success = true;
                logDebug("Commande $cmd a réussi pour la facture {$facture['id']}");
                break;
            }
        }
        
        // Retour au répertoire initial
        chdir($current_dir);
        
        // Supprimer le fichier JSON temporaire
        if (file_exists($tmp_json)) {
            unlink($tmp_json);
            logDebug("Fichier JSON temporaire supprimé: $tmp_json");
        }
        
        // Vérifier le résultat
        if ($success) {
            // Chercher le chemin du PDF dans la sortie
            $pdf_path = null;
            foreach (array_reverse($output) as $line) {
                $json_result = json_decode($line, true);
                if ($json_result !== null && isset($json_result['success']) && 
                    $json_result['success'] === true && isset($json_result['pdf_path'])) {
                    
                    $pdf_rel_path = $json_result['pdf_path'];
                    
                    // Résoudre le chemin absolu
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
                    
                    // Si le PDF existe, l'ajouter à la liste
                    if ($pdf_path && file_exists($pdf_path)) {
                        $pdf_info = [
                            'path' => $pdf_path,
                            'facture_id' => $facture['id'],
                            'prestataire' => $facture['prestataire'] ?? 'Opérateur',
                        ];
                        $generatedPDFs[] = $pdf_info;
                        logDebug("PDF généré pour la facture {$facture['id']}: $pdf_path");
                    }
                    
                    break;
                }
            }
            
            if (empty($pdf_path) || !file_exists($pdf_path)) {
                logDebug("ERREUR: PDF non trouvé pour la facture {$facture['id']}");
            }
        }
        else {
            logDebug("ERREUR: Échec de génération pour la facture {$facture['id']}");
            logDebug("Sortie: " . implode("\n", $output));
        }
    }
    
    // Résultat du traitement par lot
    if (count($generatedPDFs) > 0) {
        $message = count($generatedPDFs) . " offres ont été générées avec succès.";
        logDebug($message);
        
        // Préparer les PDFs pour le téléchargement
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Générer un token unique pour ce lot
        $batch_token = md5(uniqid(rand(), true));
        
        // Stocker les informations en session
        $_SESSION['pdf_batch'][$batch_token] = [
            'pdfs' => $generatedPDFs,
            'count' => count($generatedPDFs),
            'expires' => time() + 3600 // Expire dans 1 heure
        ];
        
        logDebug("Token de lot généré: $batch_token avec " . count($generatedPDFs) . " PDFs");
    }
    else {
        $error = "Aucune offre n'a pu être générée.";
        logDebug("ERREUR: " . $error);
    }
}

// Afficher l'interface utilisateur
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Génération d'offres par lot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 1.2em;
            background: #f6f8fa;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2747c2;
            margin-top: 0;
            border-bottom: 2px solid #e0e7ff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            min-height: 80px;
            resize: vertical;
        }
        button {
            background: #4361ee;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.2s;
            margin-top: 5px;
        }
        button:hover {
            background: #2747c2;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background: #d1fae5;
            color: #059669;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
        }
        .info {
            background: #e0e7ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .factures-list {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        .facture-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .facture-item:last-child {
            border-bottom: none;
        }
        .pdf-links {
            margin-top: 15px;
            padding: 10px;
            background: #f6f8fa;
            border-radius: 4px;
        }
        .pdf-link {
            display: block;
            margin-bottom: 5px;
            padding: 8px;
            background: #fff;
            border-radius: 4px;
            text-decoration: none;
            color: #2747c2;
            border: 1px solid #e0e7ff;
        }
        .pdf-link:hover {
            background: #e0e7ff;
        }
        .download-all {
            display: block;
            text-align: center;
            margin-top: 10px;
            background: #059669;
        }
        .download-all:hover {
            background: #047857;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Génération d'offres par lot</h2>
        
        <div class="info">
            <p><strong><?php echo count($factures); ?> factures sélectionnées</strong></p>
            <p>Ce formulaire va générer une offre individuelle pour chaque facture.</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="message success">
            <?php echo htmlspecialchars($message); ?>
            
            <?php if (!empty($generatedPDFs)): ?>
            <div class="pdf-links">
                <?php foreach ($generatedPDFs as $index => $pdf): ?>
                <a href="telecharger_pdf_direct.php?path=<?php echo urlencode($pdf['path']); ?>&name=<?php echo urlencode('offre_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pdf['prestataire']) . '_' . $pdf['facture_id'] . '.pdf'); ?>" 
                   class="pdf-link" target="_blank">
                    Offre <?php echo $index + 1; ?> - <?php echo htmlspecialchars($pdf['prestataire']); ?> 
                    (Facture #<?php echo $pdf['facture_id']; ?>)
                </a>
                <?php endforeach; ?>
                
                <?php if (count($generatedPDFs) > 1): ?>
                <a href="telecharger_zip.php?token=<?php echo $batch_token; ?>" class="pdf-link download-all">
                    Télécharger toutes les offres (ZIP)
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="message error">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="factures-list">
            <?php foreach ($factures as $facture): ?>
            <div class="facture-item">
                <strong>Facture #<?php echo $facture['id']; ?></strong> - 
                <?php echo htmlspecialchars($facture['prestataire'] ?? 'Opérateur'); ?> -
                <?php echo htmlspecialchars($facture['montant_ttc'] ?? '0'); ?> €
            </div>
            <?php endforeach; ?>
        </div>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="conditions">Conditions particulières (appliquées à toutes les offres):</label>
                <textarea id="conditions" name="conditions" placeholder="Conditions communes à toutes les offres générées..."></textarea>
            </div>
            
            <button type="submit" name="generate">Générer toutes les offres</button>
        </form>
    </div>
</body>
</html>
<?php
logDebug("=================== FIN TRAITEMENT PAR LOT ===================");
?>
