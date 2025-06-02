<?php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/FileManager.php';

// Fonction de journalisation pour le débogage
function logDebug($message)
{
    file_put_contents(__DIR__ . '/debug_analyze.log', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

logDebug("=================== DÉBUT NOUVELLE ANALYSE ===================");
logDebug("POST data: " . json_encode($_POST));

// Désactiver l'affichage des erreurs mais continuer à les enregistrer
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Authentification
if (!isAuthenticated()) {
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Jeton CSRF invalide']);
    exit;
}

// ID(s) du fichier
$file_ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    // Analyse multiple
    $file_ids = array_map('intval', $_POST['ids']);
    logDebug("Analyse multiple demandée avec " . count($file_ids) . " IDs: " . implode(', ', $file_ids));
} elseif (isset($_POST['id'])) {
    // Analyse simple
    $file_ids[] = intval($_POST['id']);
    logDebug("Analyse simple demandée avec ID: " . $_POST['id']);
} else {
    logDebug("ERREUR: ID de fichier manquant");
    echo json_encode(['error' => 'ID de fichier manquant']);
    exit;
}

$results = [];
$analyse_ids = []; // Stockage des IDs d'analyse pour la redirection

foreach ($file_ids as $file_id) {
    logDebug("Traitement du fichier ID: $file_id");
    try {
        $fileManager = new FileManager($db, $_SESSION['user_id']);
        $file = $fileManager->getFile($file_id);

        if (!$file) {
            logDebug("ERREUR: Fichier introuvable dans la base de données (ID: $file_id)");
            $results[] = [
                'file_id' => $file_id,
                'error' => 'Fichier introuvable dans la base de données',
                'success' => false
            ];
            continue;
        }

        if (!isset($file['path']) || !file_exists($file['path'])) {
            logDebug("ERREUR: Chemin du fichier invalide ou fichier non trouvé (path: " . ($file['path'] ?? 'NULL') . ")");
            $results[] = [
                'file_id' => $file_id,
                'error' => 'Chemin du fichier invalide ou fichier non trouvé',
                'success' => false
            ];
            continue;
        }

        // Générer un fichier temporaire avec la bonne extension
        $ext = pathinfo($file['path'], PATHINFO_EXTENSION);
        $tmp_file = tempnam(sys_get_temp_dir(), 'ana') . '.' . $ext;
        copy($file['path'], $tmp_file);

        // Lancer le script Python
        $python = escapeshellcmd('python');
        $script = escapeshellarg(__DIR__ . '/../python/analyse_factures.py');
        $tmp_file_escaped = escapeshellarg($tmp_file);
        $file_id_escaped = escapeshellarg($file_id); // Nouvel argument : ID du fichier

        $orig_name = isset($file['name']) ? escapeshellarg($file['name']) : escapeshellarg("");
        $rel_path = "";
        if (isset($file['path'])) {
            $rel_path_parts = explode('/storage/users/', $file['path']);
            if (count($rel_path_parts) > 1) {
                $parts = explode('/', $rel_path_parts[1]);
                array_shift($parts);
                $rel_path = '/' . implode('/', $parts);
            } else {
                $rel_path = $file['path'];
            }
        }
        $rel_path_escaped = escapeshellarg($rel_path);

        // Passer l'ID du fichier comme second argument
        $cmd = "$python $script $tmp_file_escaped $file_id_escaped $orig_name $rel_path_escaped 2>&1";
        logDebug("Commande Python: $cmd");

        exec($cmd, $output, $ret);

        logDebug("Code de retour Python: $ret");

        if (file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        if ($ret !== 0) {
            logDebug("ERREUR: Le script Python a échoué");
            $results[] = [
                'file_id' => $file_id,
                'error' => 'Erreur lors de l\'analyse',
                'details' => $output,
                'success' => false
            ];
            continue;
        }

        // Chercher la dernière ligne JSON valide dans la sortie
        $jsonFound = false;
        $result_data = null;
        $analyse_id = null;

        foreach (array_reverse($output) as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) continue;

            $data = json_decode($trimmedLine, true);
            if ($data !== null) {
                // Chercher l'ID de l'analyse dans la base de données
                try {
                    $stmt = $db->prepare("
                        SELECT id FROM pdf_analyses 
                        WHERE pdf_file_id = :file_id
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute(['file_id' => $file_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result && isset($result['id'])) {
                        $data['analyse_id'] = $result['id'];
                        $analyse_id = $result['id'];
                        $analyse_ids[] = $analyse_id; // Ajouter l'ID à la liste pour la redirection
                        logDebug("Analyse ID trouvé: $analyse_id pour le fichier ID: $file_id");
                    } else {
                        logDebug("ATTENTION: Aucun ID d'analyse trouvé pour le fichier ID: $file_id");
                    }
                } catch (Exception $e) {
                    logDebug("ERREUR lors de la requête SQL: " . $e->getMessage());
                }

                $data['file_id'] = $file_id;
                $data['success'] = true;
                $results[] = $data;
                $jsonFound = true;
                break;
            }
        }

        if (!$jsonFound) {
            // Si nous avons un ID d'analyse malgré l'absence de JSON valide
            if ($analyse_id !== null) {
                logDebug("Aucun JSON valide, mais ID d'analyse trouvé: $analyse_id");
                $analyse_ids[] = $analyse_id; // Ajouter l'ID à la liste pour la redirection
                $results[] = [
                    'file_id' => $file_id,
                    'analyse_id' => $analyse_id,
                    'success' => true,
                    'warning' => 'Analyse effectuée mais aucun résultat JSON retourné'
                ];
            } else {
                logDebug("ERREUR: Aucun résultat JSON valide retourné et aucun ID d'analyse trouvé");
                $results[] = [
                    'file_id' => $file_id,
                    'error' => 'Aucun résultat JSON valide retourné',
                    'output' => $output,
                    'success' => false
                ];
            }
        }
    } catch (Exception $e) {
        logDebug("ERREUR: Exception lors du traitement: " . $e->getMessage());
        $results[] = [
            'file_id' => $file_id,
            'error' => 'Erreur lors du traitement: ' . $e->getMessage(),
            'success' => false
        ];
    }
}

logDebug("Nombre de résultats: " . count($results));
logDebug("Nombre d'analyse_ids: " . count($analyse_ids));
logDebug("Liste des analyse_ids: " . implode(', ', $analyse_ids));

// Dédoublonnage des IDs d'analyse (au cas où)
$analyse_ids = array_unique($analyse_ids);

// Préparer la redirection
if (count($file_ids) > 1) {
    // Analyse multiple
    $redirect_url = '/EXTRACTYS/filemanager/facture/factures_telecom.php?analyzed=true';
    if (!empty($analyse_ids)) {
        $redirect_url = '/EXTRACTYS/filemanager/facture/factures_telecom.php?ids=' . implode(',', $analyse_ids) . '&analyzed=true';
    }
    logDebug("Redirection multiple vers: $redirect_url");

    $response = [
        'results' => $results,
        'redirect' => $redirect_url,
        'analyse_ids' => $analyse_ids,
    ];

    echo json_encode($response);
} else {
    // Analyse simple (un seul fichier)
    $result = isset($results[0]) ? $results[0] : [];
    $result['success'] = $result['success'] ?? false;

    $redirect_url = '/EXTRACTYS/filemanager/facture/factures_telecom.php?analyzed=true';
    if (!empty($analyse_ids)) {
        $redirect_url = '/EXTRACTYS/filemanager/facture/factures_telecom.php?id=' . $analyse_ids[0] . '&analyzed=true';
    }
    logDebug("Redirection simple vers: $redirect_url");

    $result['redirect'] = $redirect_url;
    echo json_encode($result);
}

logDebug("=================== FIN ANALYSE ===================");
