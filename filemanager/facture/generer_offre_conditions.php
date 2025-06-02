<?php
// Récupération des IDs des factures
$facture_ids = [];
$factures = [];

require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: ../../login_form/public/login.php');
    exit;
}

// Récupérer les IDs de factures depuis l'URL
if (isset($_GET['ids'])) {
    $facture_ids = array_map('intval', explode(',', $_GET['ids']));
} elseif (isset($_GET['id'])) {
    $facture_ids[] = intval($_GET['id']);
}

// Si on a des IDs, récupérer les données des factures
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
}

// Si on a plusieurs factures, calculer des moyennes et des totaux
$total_montant = 0;
$total_economie_mensuelle = 0;
$total_economie_annuelle = 0;
$facture_prestataires = [];

if (count($factures) > 1) {
    foreach ($factures as $facture) {
        $total_montant += floatval($facture['montant_ttc'] ?? 0);
        $economie_mensuelle = floatval($facture['montant_ttc'] ?? 0) * 0.2; // 20% d'économie par défaut
        $economie_annuelle = $economie_mensuelle * 12;
        
        $total_economie_mensuelle += $economie_mensuelle;
        $total_economie_annuelle += $economie_annuelle;
        
        if (isset($facture['prestataire']) && !empty($facture['prestataire'])) {
            $facture_prestataires[] = $facture['prestataire'];
        }
    }
    
    // Texte de regroupement des prestataires
    $prestataires_uniques = array_unique($facture_prestataires);
    if (count($prestataires_uniques) > 1) {
        $prestataire_text = implode(', ', array_slice($prestataires_uniques, 0, -1)) . ' et ' . end($prestataires_uniques);
    } else {
        $prestataire_text = reset($prestataires_uniques) ?: 'Opérateurs';
    }
} elseif (count($factures) == 1) {
    // Une seule facture
    $facture = $factures[0];
    $total_montant = floatval($facture['montant_ttc'] ?? 42.99);
    $total_economie_mensuelle = $total_montant * 0.2; // 20% d'économie par défaut
    $total_economie_annuelle = $total_economie_mensuelle * 12;
    $prestataire_text = $facture['prestataire'] ?? 'Opérateur';
} else {
    // Valeurs par défaut si pas de facture
    $total_montant = 42.99;
    $total_economie_mensuelle = 8.60;
    $total_economie_annuelle = 103.20;
    $prestataire_text = 'Opérateur';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générer une offre</title>
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
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        textarea {
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
        .info-factures {
            background: #e0e7ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Générer une offre commerciale</h2>
        
        <?php if (count($factures) > 1): ?>
        <div class="info-factures">
            <p><strong>Offre groupée pour <?php echo count($factures); ?> factures</strong></p>
            <p>Prestataires : <?php echo htmlspecialchars($prestataire_text); ?></p>
            <p>Montant total mensuel : <?php echo number_format($total_montant, 2); ?> €</p>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        <div class="message success">
            L'offre a été générée avec succès et le téléchargement devrait démarrer automatiquement.
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="message error">
            Erreur: <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Formulaire direct sans AJAX -->
        <form action="generer_offre_direct.php" method="post">
            <!-- Champs cachés pour les IDs de factures -->
            <?php foreach ($facture_ids as $id): ?>
            <input type="hidden" name="facture_ids[]" value="<?php echo $id; ?>">
            <?php endforeach; ?>
            
            <div class="form-group">
                <label for="prestataire">Nom du prestataire:</label>
                <input type="text" id="prestataire" name="prestataire" value="<?php echo htmlspecialchars($prestataire_text); ?>">
            </div>
            
            <div class="form-group">
                <label for="montant_ttc">Montant TTC actuel (€):</label>
                <input type="number" id="montant_ttc" name="montant_ttc" step="0.01" value="<?php echo number_format($total_montant, 2, '.', ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="economie_mensuelle">Économie mensuelle (€):</label>
                <input type="number" id="economie_mensuelle" name="economie_mensuelle" step="0.01" value="<?php echo number_format($total_economie_mensuelle, 2, '.', ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="economie_annuelle">Économie annuelle (€):</label>
                <input type="number" id="economie_annuelle" name="economie_annuelle" step="0.01" value="<?php echo number_format($total_economie_annuelle, 2, '.', ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="conditions">Conditions particulières (optionnel):</label>
                <textarea id="conditions" name="conditions" placeholder="Saisissez ici des conditions spécifiques..."><?php 
                    if (count($factures) > 1) {
                        echo "Offre groupée incluant ".count($factures)." factures de ".htmlspecialchars($prestataire_text).".";
                    }
                ?></textarea>
            </div>
            
            <button type="submit">Générer et télécharger l'offre</button>
        </form>
    </div>
</body>
</html>