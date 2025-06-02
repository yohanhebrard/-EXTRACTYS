<?php
require_once '../../login_form/init.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Fonction de journalisation pour le débogage
function logDebug($message)
{
    file_put_contents(__DIR__ . '/debug_factures.log', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Fonction pour calculer le montant HT à partir du TTC et du taux de TVA
function calculateHT($ttc_amount, $tax_rate) {
    if ($tax_rate > 0) {
        $ht_amount = $ttc_amount / (1 + ($tax_rate / 100));
        logDebug("Conversion TTC->HT: {$ttc_amount}€ TTC avec TVA {$tax_rate}% = {$ht_amount}€ HT");
        return $ht_amount;
    }
    logDebug("Pas de TVA: {$ttc_amount}€ TTC = {$ttc_amount}€ HT");
    return $ttc_amount; // Si pas de TVA, TTC = HT
}

// Fonction pour détecter le taux de TVA probable basé sur la description
function detectTaxRate($description) {
    $description_lower = strtolower($description);
    
    // Services numériques et télécoms généralement à 20%
    if (strpos($description_lower, 'netflix') !== false ||
        strpos($description_lower, 'canal') !== false ||
        strpos($description_lower, 'streaming') !== false ||
        strpos($description_lower, 'abonnement') !== false ||
        strpos($description_lower, 'freebox') !== false ||
        strpos($description_lower, 'internet') !== false) {
        return 20.0;
    }
    
    // Presse et magazines généralement à 2.1%
    if (strpos($description_lower, 'cafeyn') !== false ||
        strpos($description_lower, 'presse') !== false ||
        strpos($description_lower, 'magazine') !== false ||
        strpos($description_lower, 'journal') !== false) {
        return 2.1;
    }
    
    // Services TV spéciaux parfois à 10%
    if (strpos($description_lower, 'tv') !== false ||
        strpos($description_lower, 'chaînes') !== false) {
        return 10.0;
    }
    
    // Par défaut 20% pour les services
    return 20.0;
}

// Ajouter cette fonction en haut du fichier, après les autres fonctions
function renderLineItemsTable($items)
{
    if (empty($items)) return '';

    // Vérifier si les données sont valides
    $validItems = array_filter($items, function ($item) {
        return isset($item['product_code']) || isset($item['description']);
    });

    if (empty($validItems)) return '';

    // Regrouper les éléments par description pour éviter les doublons
    $groupedItems = [];
    foreach ($items as $item) {
        $key = trim($item['description'] ?? 'N/A');

        // Ignorer les lignes vides ou avec montant total de 0
        if (empty($key) || (isset($item['total_amount']) && floatval($item['total_amount']) == 0)) {
            continue;
        }

        // Si on a déjà cet élément, fusionner les montants
        if (isset($groupedItems[$key])) {
            $groupedItems[$key]['quantity'] = floatval($groupedItems[$key]['quantity'] ?? 0) + floatval($item['quantity'] ?? 0);
            
            // Priorité aux montants HT s'ils existent, sinon calculer depuis TTC
            $existing_ht = floatval($groupedItems[$key]['unit_price'] ?? 0);
            $existing_total_ht = floatval($groupedItems[$key]['total_amount'] ?? 0);
            
            $new_ht = floatval($item['unit_price'] ?? 0);
            $new_total_ht = floatval($item['total_amount'] ?? 0);
            
            // Si pas de montant HT, essayer de calculer depuis les montants TTC
            if ($new_ht == 0 && isset($item['ttc_unit_price']) && floatval($item['ttc_unit_price']) > 0) {
                $tax_rate = floatval($item['tax_rate'] ?? 0);
                if ($tax_rate == 0) {
                    $tax_rate = detectTaxRate($item['description'] ?? '');
                }
                $new_ht = calculateHT(floatval($item['ttc_unit_price']), $tax_rate);
            }
            
            if ($new_total_ht == 0 && isset($item['ttc_total_amount']) && floatval($item['ttc_total_amount']) > 0) {
                $tax_rate = floatval($item['tax_rate'] ?? 0);
                if ($tax_rate == 0) {
                    $tax_rate = detectTaxRate($item['description'] ?? '');
                }
                $new_total_ht = calculateHT(floatval($item['ttc_total_amount']), $tax_rate);
            }
            
            $groupedItems[$key]['unit_price'] = $existing_ht + $new_ht;
            $groupedItems[$key]['total_amount'] = $existing_total_ht + $new_total_ht;
            
            // Conserver les autres informations
            if (isset($item['tax_rate']) && floatval($item['tax_rate']) > 0) {
                $groupedItems[$key]['tax_rate'] = $item['tax_rate'];
            }
        } else {
            // Nouveau élément - s'assurer qu'on a les montants HT
            $item_copy = $item;
            
            $ht_unit_price = floatval($item_copy['unit_price'] ?? 0);
            $ht_total_amount = floatval($item_copy['total_amount'] ?? 0);
            
            // Si pas de montant HT, calculer depuis TTC
            if ($ht_unit_price == 0 && isset($item_copy['ttc_unit_price']) && floatval($item_copy['ttc_unit_price']) > 0) {
                $tax_rate = floatval($item_copy['tax_rate'] ?? 0);
                if ($tax_rate == 0) {
                    $tax_rate = detectTaxRate($item_copy['description'] ?? '');
                }
                $ht_unit_price = calculateHT(floatval($item_copy['ttc_unit_price']), $tax_rate);
                $item_copy['unit_price'] = $ht_unit_price;
            }
            
            if ($ht_total_amount == 0 && isset($item_copy['ttc_total_amount']) && floatval($item_copy['ttc_total_amount']) > 0) {
                $tax_rate = floatval($item_copy['tax_rate'] ?? 0);
                if ($tax_rate == 0) {
                    $tax_rate = detectTaxRate($item_copy['description'] ?? '');
                }
                $ht_total_amount = calculateHT(floatval($item_copy['ttc_total_amount']), $tax_rate);
                $item_copy['total_amount'] = $ht_total_amount;
            }
            
            $groupedItems[$key] = $item_copy;
        }
    }

    // Filtrer une seconde fois pour exclure les éléments qui pourraient avoir un total de 0 après regroupement
    $groupedItems = array_filter($groupedItems, function ($item) {
        return !isset($item['total_amount']) || floatval($item['total_amount']) != 0;
    });

    // Si aucun élément valide après filtrage, ne rien afficher
    if (empty($groupedItems)) return '';

    // Filtrer les sous-totaux et les déplacer à la fin
    $regularItems = [];
    $subTotalItems = [];
    foreach ($groupedItems as $key => $item) {
        if (stripos($key, 'sous total') !== false) {
            $subTotalItems[$key] = $item;
        } else {
            $regularItems[$key] = $item;
        }
    }

    // Trier les éléments par catégorie puis par montant
    $sortedItems = [];

    // D'abord les abonnements
    foreach ($regularItems as $key => $item) {
        if (stripos($key, 'abonnement') !== false) {
            $sortedItems[$key] = $item;
            unset($regularItems[$key]);
        }
    }

    // Ensuite les consommations
    foreach ($regularItems as $key => $item) {
        if (
            stripos($key, 'consommation') !== false ||
            stripos($key, 'national') !== false ||
            stripos($key, 'mobile') !== false
        ) {
            $sortedItems[$key] = $item;
            unset($regularItems[$key]);
        }
    }

    // Puis le reste
    $sortedItems = array_merge($sortedItems, $regularItems, $subTotalItems);

    $html = '<div class="line-items-container">
        <h4 class="line-items-title"><i class="fas fa-list"></i> Détails des prestations (Montants HT)</h4>
        <div class="download-btn-container">
            <button class="btn btn-primary btn-sm btn-download-table">
                <i class="fas fa-download"></i> Télécharger le tableau
            </button>
        </div>
        <div class="table-responsive">
            <table class="line-items-table" id="invoiceTable">
                <thead>
                    <tr>
                        <th>Code produit</th>
                        <th>Description</th>
                        <th>Quantité</th>
                        <th>Prix unitaire HT</th>
                        <th>Montant total HT</th>
                        <th>TVA (%)</th>
                        <th>Nos Tarifs HT</th>
                        <th>Gain HT</th>
                        <th>Description conseiller</th>
                    </tr>
                </thead>
                <tbody>';

    $total_ht = 0;
    $total_tva = 0;
    $totalGain = 0;
    $categoryTotal = 0;
    $currentCategory = '';

    $rowIndex = 0;

    foreach ($sortedItems as $item) {
        // Détecter les changements de catégorie (Abonnement, Consommation, etc.)
        $description = $item['description'] ?? '';
        $category = '';

        if (preg_match('/^(Abonnement|Consommation|Frais)/i', $description, $matches)) {
            $category = $matches[1];
        }

        // Si on change de catégorie, ajouter une ligne de séparation
        if ($category && $category !== $currentCategory && $currentCategory !== '') {
            $html .= '<tr class="category-separator">
                <td colspan="9"></td>
            </tr>';
        }

        $currentCategory = $category ?: $currentCategory;

        // Calculer les montants HT et TVA
        $itemTotal_ht = floatval($item['total_amount'] ?? 0);
        $unit_price_ht = floatval($item['unit_price'] ?? 0);
        $tax_rate = floatval($item['tax_rate'] ?? 0);
        
        // Si pas de taux de TVA dans la base, essayer de le détecter
        if ($tax_rate == 0) {
            $tax_rate = detectTaxRate($description);
        }
        
        $itemTotal_tva = $itemTotal_ht * ($tax_rate / 100);
        $total_ht += $itemTotal_ht;
        $total_tva += $itemTotal_tva;

        // Déterminer si c'est un sous-total (style différent)
        $isSubtotal = (stripos($description, 'sous total') !== false);
        $rowClass = $isSubtotal ? 'subtotal-row' : '';

        $html .= '<tr class="' . $rowClass . '">
            <td>' . htmlspecialchars($item['product_code'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($description) . '</td>
            <td class="text-right">' . number_format(floatval($item['quantity'] ?? 0), 2, ',', ' ') . '</td>
            <td class="text-right">' . number_format($unit_price_ht, 2, ',', ' ') . ' € HT</td>
            <td class="text-right" data-value="' . $itemTotal_ht . '">' . number_format($itemTotal_ht, 2, ',', ' ') . ' € HT</td>
            <td class="text-right">' . number_format($tax_rate, 1, ',', ' ') . '%</td>
            <td><input type="number" step="0.01" class="notre-tarif" data-row="' . $rowIndex . '" placeholder="0.00"></td>
            <td class="gain text-right" data-row="' . $rowIndex . '">0.00 € HT</td>
            <td><input type="text" class="description-conseiller" placeholder="Commentaire"></td>
        </tr>';

        $rowIndex++;
    }

    // Calculer le total TTC
    $total_ttc = $total_ht + $total_tva;

    // Ajouter une ligne de total HT
    $html .= '<tr class="total-row">
            <td colspan="4" class="text-right"><strong>Total HT</strong></td>
            <td class="text-right"><strong>' . number_format($total_ht, 2, ',', ' ') . ' € HT</strong></td>
            <td class="text-right"></td>
            <td class="total-notre-tarif text-right"><strong>0.00 € HT</strong></td>
            <td class="total-gain text-right"><strong>0.00 € HT</strong></td>
            <td></td>
        </tr>';

    // Ajouter une ligne pour la TVA
    $html .= '<tr class="tax-row">
            <td colspan="4" class="text-right">Total TVA</td>
            <td class="text-right">' . number_format($total_tva, 2, ',', ' ') . ' €</td>
            <td colspan="4"></td>
        </tr>';

    // Ajouter une ligne de total TTC
    $html .= '<tr class="total-ttc-row">
            <td colspan="4" class="text-right"><strong>Total TTC</strong></td>
            <td class="text-right"><strong>' . number_format($total_ttc, 2, ',', ' ') . ' € TTC</strong></td>
            <td colspan="4"></td>
        </tr>';

    $html .= '</tbody>
            </table>
        </div>
    </div>';

    return $html;
}

logDebug("=================== DÉBUT AFFICHAGE FACTURES ===================");
logDebug("URL: " . $_SERVER['REQUEST_URI']);
logDebug("GET Params: " . json_encode($_GET));

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: ../../login_form/public/login.php');
    exit;
}

// Initialiser les variables
$analyses = [];
$analyse = null;
$error = null;

// Récupérer les IDs d'analyse depuis l'URL (plusieurs possibles)
$analyse_ids = [];
if (isset($_GET['ids'])) {
    $analyse_ids = array_map('intval', explode(',', $_GET['ids']));
    logDebug("Mode multiple: IDs récupérés = " . implode(', ', $analyse_ids));
} elseif (isset($_GET['id'])) {
    $analyse_ids[] = intval($_GET['id']);
    logDebug("Mode simple: ID récupéré = " . $_GET['id']);
} else {
    logDebug("Aucun ID spécifié dans l'URL");
}

// Requête pour récupérer les analyses
$user_id = $_SESSION['user_id'];
logDebug("User ID: " . $user_id);

if (!empty($analyse_ids)) {
    // Récupérer plusieurs analyses
    $in = str_repeat('?,', count($analyse_ids) - 1) . '?';
    $query = "
        SELECT a.*, f.name as filename, f.path as filepath
        FROM pdf_analyses a
        JOIN files f ON a.pdf_file_id = f.id 
        WHERE a.id IN ($in) AND f.user_id = ?
        ORDER BY a.id DESC
    ";
    logDebug("Requête SQL (multiple): " . $query);
    logDebug("Params: " . implode(', ', array_merge($analyse_ids, [$user_id])));

    $stmt = $db->prepare($query);
    $stmt->execute([...$analyse_ids, $user_id]);
    $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les lignes d'articles pour chaque analyse avec TOUS les champs HT/TTC
    if (!empty($analyses)) {
        $analyse_ids_for_items = array_column($analyses, 'id');
        $in_items = str_repeat('?,', count($analyse_ids_for_items) - 1) . '?';

        $query_items = "
            SELECT *, 
                   COALESCE(unit_price, 0) as unit_price,
                   COALESCE(total_amount, 0) as total_amount,
                   COALESCE(tax_rate, 0) as tax_rate,
                   COALESCE(tax_amount, 0) as tax_amount,
                   COALESCE(ttc_unit_price, 0) as ttc_unit_price,
                   COALESCE(ttc_total_amount, 0) as ttc_total_amount
            FROM invoice_line_items
            WHERE analyse_id IN ($in_items)
            ORDER BY analyse_id, id
        ";

        logDebug("Requête SQL (lignes d'articles): " . $query_items);

        $stmt_items = $db->prepare($query_items);
        $stmt_items->execute($analyse_ids_for_items);
        $line_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Organiser les lignes d'articles par analyse_id
        $items_by_analyse = [];
        foreach ($line_items as $item) {
            $items_by_analyse[$item['analyse_id']][] = $item;
        }

        logDebug("Nombre de lignes d'articles récupérées: " . count($line_items));
    }

    logDebug("Nombre d'analyses récupérées: " . count($analyses));
    if (count($analyses) > 0) {
        logDebug("IDs des analyses récupérées: " . implode(', ', array_column($analyses, 'id')));
    }
} else {
    // Récupérer la dernière analyse
    $query = "
        SELECT a.*, f.name as filename, f.path as filepath
        FROM pdf_analyses a
        JOIN files f ON a.pdf_file_id = f.id 
        WHERE f.user_id = :user_id
        ORDER BY a.id DESC 
        LIMIT 1
    ";
    logDebug("Requête SQL (dernière analyse): " . $query);

    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $analyse = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($analyse) {
        logDebug("Dernière analyse récupérée: ID = " . $analyse['id']);
    } else {
        logDebug("Aucune dernière analyse trouvée");
    }
}

// Si aucune analyse n'est trouvée
if (empty($analyses) && empty($analyse)) {
    $error = "Aucune analyse de facture trouvée.";
    logDebug("ERREUR: " . $error);
}

// Générer un token CSRF
$csrf_token = generateCSRFToken();

// Début du HTML - reste du code inchangé sauf ajout de logs de débogage
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <!-- En-têtes HTML inchangés -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat d'analyse - EXTRACTYS</title>

    <!-- Fichiers CSS et autres ressources inchangées -->
    <link rel="stylesheet" href="../assets/css/buttons.css">
    <link rel="stylesheet" href="../assets/css/filemanager.css">
    <link rel="stylesheet" href="factures_telecom.css">

    <!-- Pour le débogage uniquement -->
    <style>
        .debug-container {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow: auto;
        }

        .debug-toggle {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        /* Styles améliorés pour le tableau des lignes d'articles */
        .line-items-container {
            margin: 15px 0;
            padding: 0 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            background-color: #fff;
        }

        .line-items-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #444;
            border-bottom: 2px solid #3498db;
            padding: 10px 0;
            font-weight: 600;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 15px;
        }

        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            color: #333;
        }

        .line-items-table th {
            background-color: #3498db;
            color: white;
            text-align: left;
            padding: 10px;
            border: 1px solid #2980b9;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .line-items-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            color: #333;
            background-color: #fff;
        }

        .line-items-table .text-right {
            text-align: right;
            font-weight: 500;
        }

        .line-items-table tr:nth-child(even) td {
            background-color: #f2f7fd;
        }

        .line-items-table tr:hover td {
            background-color: #e8f4fc;
        }

        /* Style pour mettre en évidence les totaux */
        .line-items-table tr td:last-child {
            font-weight: bold;
            color: #2c3e50;
        }

        /* Nouveaux styles pour les catégories et sous-totaux */
        .line-items-table .category-separator td {
            height: 10px;
            background-color: #f0f0f0;
            border-top: 2px solid #ddd;
            border-bottom: 2px solid #ddd;
        }

        .line-items-table .subtotal-row td {
            background-color: #f2f7fd;
            font-style: italic;
            border-top: 1px dashed #aaa;
            color: #555;
        }

        .line-items-table .total-row td {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            border: 1px solid #2980b9;
        }

        .line-items-table .tax-row td {
            background-color: #f39c12;
            color: white;
            font-weight: normal;
            border: 1px solid #e67e22;
        }

        .line-items-table .total-ttc-row td {
            background-color: #e74c3c;
            color: white;
            font-weight: bold;
            border: 1px solid #c0392b;
        }

        .line-items-table .total-row td:last-child,
        .line-items-table .total-ttc-row td:last-child {
            color: white;
            font-size: 1.1em;
        }

        /* Style pour les inputs dans le tableau */
        .line-items-table input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 0.9rem;
            background-color: #f9f9f9;
        }

        .line-items-table input:focus {
            border-color: #3498db;
            box-shadow: 0 0 3px rgba(52, 152, 219, 0.5);
            outline: none;
        }

        .line-items-table .notre-tarif {
            text-align: right;
            color: #16a085;
            font-weight: 500;
        }

        .line-items-table .gain {
            color: #27ae60;
            font-weight: 600;
        }

        .line-items-table .gain.negative {
            color: #e74c3c;
        }

        .download-btn-container {
            text-align: right;
            margin: 10px 0;
        }

        .btn-download-table {
            background-color: #2ecc71;
            border-color: #27ae60;
        }

        .btn-download-table:hover {
            background-color: #27ae60;
            border-color: #219653;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Styles pour le modal d'offre */
        .modal-offer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-offer-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .modal-offer-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-offer-close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #eee;
            background-color: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }

        .offer-summary {
            margin-bottom: 20px;
        }

        .offer-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .offer-header h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .savings-highlight {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .savings-highlight.negative {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .savings-amount {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .savings-label {
            font-weight: 500;
        }

        .savings-value {
            font-size: 1.5em;
            font-weight: bold;
        }

        .savings-percentage {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .offer-comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .offer-comparison-table th,
        .offer-comparison-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        .offer-comparison-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .offer-comparison-table .our-price {
            color: #2ecc71;
            font-weight: bold;
        }

        .offer-comparison-table .savings.positive {
            color: #2ecc71;
            font-weight: bold;
        }

        .offer-comparison-table .savings.negative {
            color: #e74c3c;
            font-weight: bold;
        }

        .offer-comparison-table .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .offer-options {
            margin-top: 20px;
        }

        .offer-options h5 {
            margin-bottom: 15px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        /* Styles pour les notifications toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 300px;
            max-width: 500px;
            animation: slideInRight 0.3s ease-out;
            position: relative;
        }

        .toast-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .toast-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .toast-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .toast-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            margin-left: 10px;
            opacity: 0.7;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <!-- NAVBAR MODERNE -->
    <nav class="navbar">
        <a href="#" class="navbar-logo">EXTRACTYS Télécom</a>
        <div class="navbar-links">
            <a href="../index.php" class="navbar-link active">Accueil</a>

        </div>
        <div class="navbar-user">

        </div>
    </nav>
    <div style="height: 20px;"></div>
    <div class="app-container">
        <main class="app-content">
            <!-- MODE DEBUG UNIQUEMENT -->

            <?php if (isset($error)): ?>
                <div class="error-banner">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="action-buttons">
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Retour au gestionnaire de fichiers
                    </a>
                </div>
            <?php else: ?>
                <?php if (isset($_GET['analyzed']) && $_GET['analyzed'] === 'true'): ?>
                    <div class="info-banner">
                        <i class="fas fa-check-circle"></i> L'analyse de la facture a été effectuée avec succès.
                    </div>
                <?php endif; ?>

                <?php
                logDebug("Avant boucle d'affichage, nombre d'analyses: " . count($analyses));
                if (!empty($analyses)) {
                    foreach ($analyses as $index => $ana) {
                        logDebug("Affichage analyse #" . ($index + 1) . " ID: " . ($ana['id'] ?? 'inconnu'));
                    }
                }
                ?>

                <?php if (!empty($analyses)): ?>
                    <?php foreach ($analyses as $analyse): ?>
                        <div class="result-card">
                            <div class="result-card-header">
                                <div class="result-card-title">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span class="badge badge-op-<?php echo strtolower($analyse['prestataire'] ?? 'autre'); ?>">
                                        <?php echo htmlspecialchars($analyse['prestataire'] ?? 'Opérateur'); ?>
                                    </span>
                                    <span class="facture-num">Facture <strong>#<?php echo $analyse['id']; ?></strong></span>
                                </div>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Analyse réussie
                                </span>
                            </div>

                            <!-- Ajout du tableau des lignes d'articles -->
                            <?php if (isset($items_by_analyse[$analyse['id']])): ?>
                                <?php echo renderLineItemsTable($items_by_analyse[$analyse['id']]); ?>
                            <?php endif; ?>

                            <div class="action-buttons" style="justify-content: flex-end; margin-top: 0;">
                                <button class="btn btn-primary btn-generate-offer" data-facture-id="<?php echo $analyse['id']; ?>">
                                    <i class="fas fa-lightbulb"></i> Générer une offre
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="action-buttons" style="justify-content: flex-end;">
                        <button class="btn btn-primary" id="btnGenerateAllOffers">
                            <i class="fas fa-bolt"></i> Générer toutes les offres
                        </button>
                    </div>
                <?php elseif ($analyse): ?>
                    <div class="result-card">
                        <div class="result-card-header">
                            <div class="result-card-title">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span class="badge badge-op-<?php echo strtolower($analyse['prestataire'] ?? 'autre'); ?>">
                                    <?php echo htmlspecialchars($analyse['prestataire'] ?? 'Opérateur'); ?>
                                </span>
                                <span class="facture-num">Facture <strong>#<?php echo $analyse['id']; ?></strong></span>
                            </div>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i> Analyse réussie
                            </span>
                        </div>

                        <div>
                            <div class="result-label">Nom</div>
                            <div class="result-value">
                                <?php echo htmlspecialchars($analyse['nom'] ?? 'Non renseigné'); ?>
                            </div>
                            <div class="result-label">Prénom</div>
                            <div class="result-value">
                                <?php echo htmlspecialchars($analyse['prenom'] ?? 'Non renseigné'); ?>
                            </div>
                            <div class="result-label">Email</div>
                            <div class="result-value email tooltip">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($analyse['email'] ?? 'Non renseigné'); ?>
                                <span class="tooltiptext">Copier</span>
                            </div>
                            <div class="result-label">Engagement</div>
                            <div class="result-value">
                                <?php echo htmlspecialchars($analyse['date_engagement'] ?? 'Non renseigné'); ?>
                            </div>
                        </div>

                        <!-- Ajout du tableau des lignes d'articles pour une seule analyse -->
                        <?php
                        // Récupérer les lignes d'articles pour cette analyse avec TOUS les champs HT/TTC
                        $stmt_items = $db->prepare("
                            SELECT *, 
                                   COALESCE(unit_price, 0) as unit_price,
                                   COALESCE(total_amount, 0) as total_amount,
                                   COALESCE(tax_rate, 0) as tax_rate,
                                   COALESCE(tax_amount, 0) as tax_amount,
                                   COALESCE(ttc_unit_price, 0) as ttc_unit_price,
                                   COALESCE(ttc_total_amount, 0) as ttc_total_amount
                            FROM invoice_line_items 
                            WHERE analyse_id = ? 
                            ORDER BY id
                        ");
                        $stmt_items->execute([$analyse['id']]);
                        $single_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                        echo renderLineItemsTable($single_items);
                        ?>

                    </div>

                    <div class="action-buttons">
                        <a href="../index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <button class="btn btn-primary" id="btnExportPDF">
                            <i class="fas fa-file-pdf"></i> Exporter en PDF
                        </button>
                        <button class="btn btn-primary" id="btnAnalyseMore">
                            <i class="fas fa-search"></i> Analyser d'autres factures
                        </button>
                    </div>
                <?php endif; ?>
                <?php if (empty($analyses) && empty($analyse)): ?>
                    <div class="error-banner">
                        <i class="fas fa-exclamation-circle"></i> Aucune analyse trouvée pour cet utilisateur.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal pour conditions d'offre -->
    <div id="modalOffer" class="modal-offer" style="display:none;">
        <div class="modal-offer-backdrop"></div>
        <div class="modal-offer-content">
            <button class="modal-offer-close" id="closeModalOffer">&times;</button>
            <iframe id="iframeOffer" src="" frameborder="0" style="width:100%;height:400px;border-radius:8px;"></iframe>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Fonction pour initialiser les tableaux
        function initTables() {
            // Activer les calculs automatiques des gains
            document.querySelectorAll('.notre-tarif').forEach(input => {
                input.addEventListener('input', function() {
                    calculateGain(this);
                    updateTotals();
                });
            });

            // Initialiser les boutons de téléchargement
            document.querySelectorAll('.btn-download-table').forEach(button => {
                button.addEventListener('click', function() {
                    const table = this.closest('.line-items-container').querySelector('.line-items-table');
                    exportTableToCSV(table);
                });
            });
        }

        // Fonction pour calculer le gain pour une ligne (en HT)
        function calculateGain(input) {
            const row = input.closest('tr');
            const rowIndex = input.getAttribute('data-row');
            const originalAmount_ht = parseFloat(row.querySelector('td[data-value]').getAttribute('data-value'));
            const ourPrice_ht = parseFloat(input.value) || 0;

            const gain_ht = originalAmount_ht - ourPrice_ht;
            const gainCell = document.querySelector(`.gain[data-row="${rowIndex}"]`);

            gainCell.textContent = gain_ht.toFixed(2) + ' € HT';
            gainCell.classList.toggle('negative', gain_ht < 0);
        }

        // Fonction pour mettre à jour les totaux (en HT)
        function updateTotals() {
            // Calculer la somme de "Nos Tarifs" HT
            const ourPrices_ht = Array.from(document.querySelectorAll('.notre-tarif')).map(input => parseFloat(input.value) || 0);
            const totalOurPrice_ht = ourPrices_ht.reduce((sum, price) => sum + price, 0);

            // Calculer la somme des gains HT
            const gains_ht = Array.from(document.querySelectorAll('.gain:not(.total-gain)')).map(cell => {
                const value = parseFloat(cell.textContent.replace('€ HT', '').trim()) || 0;
                return value;
            });
            const totalGain_ht = gains_ht.reduce((sum, gain) => sum + gain, 0);

            // Mettre à jour les cellules de total
            const totalOurPriceCell = document.querySelector('.total-notre-tarif');
            if (totalOurPriceCell) {
                totalOurPriceCell.innerHTML = '<strong>' + totalOurPrice_ht.toFixed(2) + ' € HT</strong>';
            }

            const totalGainCell = document.querySelector('.total-gain');
            if (totalGainCell) {
                totalGainCell.innerHTML = '<strong>' + totalGain_ht.toFixed(2) + ' € HT</strong>';
                totalGainCell.classList.toggle('negative', totalGain_ht < 0);
            }
        }

        // Fonction pour exporter le tableau en CSV
        function exportTableToCSV(table) {
            const rows = table.querySelectorAll('tr');
            let csv = [];

            for (let i = 0; i < rows.length; i++) {
                const row = [],
                    cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length; j++) {
                    // Récupérer la valeur (input ou texte)
                    let value = '';
                    const input = cols[j].querySelector('input');

                    if (input) {
                        value = input.value;
                    } else {
                        value = cols[j].innerText;
                    }

                    // Échapper les guillemets et ajouter des guillemets autour du champ
                    value = value.replace(/"/g, '""');
                    row.push('"' + value + '"');
                }

                csv.push(row.join(','));
            }

            // Créer un lien de téléchargement
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.setAttribute('href', url);
            link.setAttribute('download', 'facture_analyse_ht_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Initialiser les tableaux au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            initTables();
        });

        // Pour le modal et autres fonctionnalités existantes
        // Collecter tous les IDs de factures présents sur la page
        function getAllFactureIds() {
            const buttons = document.querySelectorAll('.btn-generate-offer');
            return Array.from(buttons).map(btn => btn.getAttribute('data-facture-id')).filter(id => id);
        }

        // Gestion des boutons de génération d'offre
        document.addEventListener('DOMContentLoaded', function() {
            // Bouton pour générer une offre individuelle
            document.querySelectorAll('.btn-generate-offer').forEach(button => {
                button.addEventListener('click', function() {
                    const factureId = this.getAttribute('data-facture-id');
                    if (factureId) {
                        generateOffer(factureId);
                    }
                });
            });

            // Bouton pour générer toutes les offres
            const btnGenerateAll = document.getElementById('btnGenerateAllOffers');
            if (btnGenerateAll) {
                btnGenerateAll.addEventListener('click', function() {
                    const factureIds = getAllFactureIds();
                    if (factureIds.length > 0) {
                        generateAllOffers(factureIds);
                    }
                });
            }
        });

        function generateOffer(factureId) {
            console.log('Génération d\'offre pour la facture:', factureId);
            
            // Collecter les données de la facture
            const factureData = collectFactureData(factureId);
            
            if (!factureData) {
                showToast('Erreur: Impossible de collecter les données de la facture', 'error');
                return;
            }
            
            // Afficher le modal de génération d'offre
            showOfferModal(factureData);
        }

        function generateAllOffers(factureIds) {
            console.log('Génération d\'offres pour les factures:', factureIds);
            
            if (factureIds.length === 0) {
                showToast('Aucune facture sélectionnée', 'warning');
                return;
            }
            
            // Collecter les données de toutes les factures
            const allFacturesData = [];
            factureIds.forEach(factureId => {
                const factureData = collectFactureData(factureId);
                if (factureData) {
                    allFacturesData.push(factureData);
                }
            });
            
            if (allFacturesData.length === 0) {
                showToast('Erreur: Impossible de collecter les données des factures', 'error');
                return;
            }
            
            // Générer un document d'offre groupée
            generateGroupedOfferDocument(allFacturesData);
        }

        // Fonction pour collecter les données d'une facture spécifique
        function collectFactureData(factureId) {
            const factureContainer = document.querySelector(`[data-facture-id="${factureId}"]`);
            if (!factureContainer) {
                console.error('Container de facture non trouvé pour ID:', factureId);
                return null;
            }
            
            // Collecter les informations de base
            const factureTitle = factureContainer.closest('.result-card').querySelector('.facture-num')?.textContent || `Facture #${factureId}`;
            const operateur = factureContainer.closest('.result-card').querySelector('.badge')?.textContent || 'Opérateur inconnu';
            
            // Collecter les données du tableau
            const table = factureContainer.closest('.result-card').querySelector('.line-items-table');
            if (!table) {
                console.error('Tableau non trouvé pour la facture:', factureId);
                return null;
            }
            
            const items = [];
            const rows = table.querySelectorAll('tbody tr:not(.category-separator):not(.total-row):not(.tax-row):not(.total-ttc-row)');
            
            let totalHT = 0;
            let totalTVA = 0;
            let totalGainPotentiel = 0;
            
            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 8) {
                    const productCode = cells[0].textContent.trim();
                    const description = cells[1].textContent.trim();
                    const quantity = parseFloat(cells[2].textContent.replace(',', '.')) || 1;
                    const unitPriceHT = parseFloat(cells[3].textContent.replace(/[€\s,]/g, '').replace(',', '.')) || 0;
                    const totalAmountHT = parseFloat(cells[4].getAttribute('data-value')) || 0;
                    const taxRate = parseFloat(cells[5].textContent.replace('%', '').replace(',', '.')) || 0;
                    
                    // Récupérer les valeurs saisies par l'utilisateur
                    const ourPriceInput = cells[6].querySelector('input');
                    const ourPriceHT = ourPriceInput ? (parseFloat(ourPriceInput.value) || 0) : 0;
                    const commentInput = cells[8].querySelector('input');
                    const comment = commentInput ? commentInput.value : '';
                    
                    // Calculer le gain
                    const gainHT = totalAmountHT - (ourPriceHT * quantity);
                    
                    items.push({
                        productCode,
                        description,
                        quantity,
                        unitPriceHT,
                        totalAmountHT,
                        taxRate,
                        ourPriceHT,
                        gainHT,
                        comment
                    });
                    
                    totalHT += totalAmountHT;
                    totalTVA += (totalAmountHT * taxRate / 100);
                    totalGainPotentiel += gainHT;
                }
            });
            
            return {
                factureId,
                factureTitle,
                operateur,
                items,
                totals: {
                    totalHT,
                    totalTVA,
                    totalTTC: totalHT + totalTVA,
                    totalGainPotentiel
                }
            };
        }

        // Fonction pour afficher le modal d'offre
        function showOfferModal(factureData) {
            // Créer le modal s'il n'existe pas déjà
            let modal = document.getElementById('modalGenerateOffer');
            if (!modal) {
                modal = createOfferModal();
                document.body.appendChild(modal);
            }
            
            // Remplir le modal avec les données
            populateOfferModal(modal, factureData);
            
            // Afficher le modal
            modal.style.display = 'flex';
        }

        // Fonction pour créer le modal d'offre
        function createOfferModal() {
            const modal = document.createElement('div');
            modal.id = 'modalGenerateOffer';
            modal.className = 'modal-offer';
            modal.innerHTML = `
                <div class="modal-offer-backdrop"></div>
                <div class="modal-offer-content" style="max-width: 90%; max-height: 90%; overflow-y: auto;">
                    <div class="modal-header">
                        <h3><i class="fas fa-lightbulb"></i> Génération d'offre commerciale</h3>
                        <button class="modal-offer-close" id="closeModalGenerateOffer">&times;</button>
                    </div>
                    <div class="modal-body" id="offerModalBody">
                        <!-- Contenu dynamique -->
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" id="btnCancelOffer">Annuler</button>
                        <button class="btn btn-primary" id="btnGenerateOfferDoc">
                            <i class="fas fa-file-pdf"></i> Générer le document
                        </button>
                        <button class="btn btn-success" id="btnSendOfferEmail">
                            <i class="fas fa-envelope"></i> Envoyer par email
                        </button>
                    </div>
                </div>
            `;
            
            // Ajouter les événements
            modal.querySelector('#closeModalGenerateOffer').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            modal.querySelector('#btnCancelOffer').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            modal.querySelector('.modal-offer-backdrop').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            return modal;
        }

        // Fonction pour remplir le modal avec les données de la facture
        function populateOfferModal(modal, factureData) {
            const modalBody = modal.querySelector('#offerModalBody');
            
            let gainTotal = factureData.totals.totalGainPotentiel;
            let pourcentageGain = factureData.totals.totalHT > 0 ? (gainTotal / factureData.totals.totalHT * 100) : 0;
            
            modalBody.innerHTML = `
                <div class="offer-summary">
                    <div class="offer-header">
                        <h4>${factureData.factureTitle} - ${factureData.operateur}</h4>
                        <div class="savings-highlight">
                            <div class="savings-amount">
                                <span class="savings-label">Économies potentielles :</span>
                                <span class="savings-value ${gainTotal > 0 ? 'positive' : 'negative'}">
                                    ${gainTotal.toFixed(2)} € HT / an
                                </span>
                                <span class="savings-percentage">(${pourcentageGain.toFixed(1)}%)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="offer-details">
                        <h5>Comparaison tarifaire</h5>
                        <div class="table-responsive">
                            <table class="offer-comparison-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Tarif actuel (HT)</th>
                                        <th>Notre tarif (HT)</th>
                                        <th>Économie (HT)</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${factureData.items.map(item => `
                                        <tr>
                                            <td>${item.description}</td>
                                            <td>${item.totalAmountHT.toFixed(2)} €</td>
                                            <td class="our-price">${item.ourPriceHT > 0 ? (item.ourPriceHT * item.quantity).toFixed(2) + ' €' : 'À définir'}</td>
                                            <td class="savings ${item.gainHT > 0 ? 'positive' : 'negative'}">
                                                ${item.ourPriceHT > 0 ? item.gainHT.toFixed(2) + ' €' : '-'}
                                            </td>
                                            <td>${item.comment || '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td><strong>Total</strong></td>
                                        <td><strong>${factureData.totals.totalHT.toFixed(2)} €</strong></td>
                                        <td><strong>${factureData.items.reduce((sum, item) => sum + (item.ourPriceHT * item.quantity), 0).toFixed(2)} €</strong></td>
                                        <td class="savings ${gainTotal > 0 ? 'positive' : 'negative'}">
                                            <strong>${gainTotal.toFixed(2)} €</strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="offer-options">
                        <h5>Options de l'offre</h5>
                        <div class="form-group">
                            <label>Validité de l'offre :</label>
                            <select id="offerValidity" class="form-control">
                                <option value="30">30 jours</option>
                                <option value="60">60 jours</option>
                                <option value="90">90 jours</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Conditions particulières :</label>
                            <textarea id="offerConditions" class="form-control" rows="3" placeholder="Conditions spéciales, engagements, frais de mise en service..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            // Ajouter les événements pour les boutons
            modal.querySelector('#btnGenerateOfferDoc').onclick = () => generateOfferDocument(factureData);
            modal.querySelector('#btnSendOfferEmail').onclick = () => sendOfferByEmail(factureData);
        }

        // Fonction pour générer le document d'offre
        function generateOfferDocument(factureData) {
            const validity = document.getElementById('offerValidity').value;
            const conditions = document.getElementById('offerConditions').value;
            
            // Préparer les données pour l'envoi
            const offerData = {
                factureData,
                validity,
                conditions,
                generatedDate: new Date().toISOString()
            };
            
            // Envoyer la requête pour générer le PDF
            fetch('../api/generate_offer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(offerData)
            })
            .then(response => response.blob())
            .then(blob => {
                // Télécharger le fichier PDF
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Offre_${factureData.factureTitle.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                showToast('Document d\'offre généré avec succès', 'success');
            })
            .catch(error => {
                console.error('Erreur lors de la génération:', error);
                showToast('Erreur lors de la génération du document', 'error');
            });
        }

        // Fonction pour envoyer l'offre par email
        function sendOfferByEmail(factureData) {
            const validity = document.getElementById('offerValidity').value;
            const conditions = document.getElementById('offerConditions').value;
            
            // Demander l'email de destination
            const email = prompt('Adresse email de destination :');
            if (!email) return;
            
            const offerData = {
                factureData,
                validity,
                conditions,
                recipientEmail: email,
                generatedDate: new Date().toISOString()
            };
            
            // Envoyer la requête
            fetch('../api/send_offer_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(offerData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Offre envoyée par email avec succès', 'success');
                    document.getElementById('modalGenerateOffer').style.display = 'none';
                } else {
                    showToast('Erreur lors de l\'envoi: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur lors de l\'envoi:', error);
                showToast('Erreur lors de l\'envoi de l\'email', 'error');
            });
        }

        // Fonction pour générer un document d'offre groupée
        function generateGroupedOfferDocument(allFacturesData) {
            const totalSavings = allFacturesData.reduce((sum, facture) => sum + facture.totals.totalGainPotentiel, 0);
            const totalCurrentAmount = allFacturesData.reduce((sum, facture) => sum + facture.totals.totalHT, 0);
            
            showToast(`Génération d'une offre groupée pour ${allFacturesData.length} factures (Économies: ${totalSavings.toFixed(2)} € HT)`, 'info');
            
            // Logique pour générer un document PDF groupé
            const groupedOfferData = {
                factures: allFacturesData,
                totalSavings,
                totalCurrentAmount,
                generatedDate: new Date().toISOString()
            };
            
            // Envoyer la requête pour générer le PDF groupé
            fetch('../api/generate_grouped_offer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(groupedOfferData)
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Offre_Groupee_${new Date().toISOString().split('T')[0]}.pdf`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                showToast('Document d\'offre groupée généré avec succès', 'success');
            })
            .catch(error => {
                console.error('Erreur lors de la génération groupée:', error);
                showToast('Erreur lors de la génération du document groupé', 'error');
            });
        }

        // Fonction pour afficher les notifications toast
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="toast-close">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
            
            // Close button
            toast.querySelector('.toast-close').addEventListener('click', () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            });
        }
    </script>
</body>

</html>
<?php
logDebug("=================== FIN AFFICHAGE FACTURES ===================");
?>