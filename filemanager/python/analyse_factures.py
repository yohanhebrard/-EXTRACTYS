import sys
import json
import os
import mysql.connector
from decimal import Decimal
from datetime import datetime
import traceback
import tempfile
from PyPDF2 import PdfReader, PdfWriter


# Classe pour gérer la sérialisation des objets Decimal en JSON
class DecimalEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, Decimal):
            return float(obj)
        return super(DecimalEncoder, self).default(obj)


# Fonction pour journaliser les erreurs ET les logs détaillés
def log_error(message):
    with open(os.path.join(os.path.dirname(__file__), "python_errors.log"), "a", encoding='utf-8') as f:
        f.write(f"[{datetime.now()}] {message}\n")


def log_mindee_response(message):
    """Log spécialement pour les réponses de l'API Mindee"""
    with open(os.path.join(os.path.dirname(__file__), "mindee_api_responses.log"), "a", encoding='utf-8') as f:
        f.write(f"[{datetime.now()}] {message}\n")


# Fonction pour diviser un PDF en plusieurs parties
def split_pdf(input_path, max_pages_per_chunk=10):
    """Divise un PDF en plusieurs fichiers plus petits."""
    temp_files = []

    try:
        # Ouvrir le PDF source
        pdf = PdfReader(input_path)
        total_pages = len(pdf.pages)

        # Si le PDF est petit, pas besoin de le diviser
        if total_pages <= max_pages_per_chunk:
            return [input_path], False

        # Diviser le PDF en plusieurs parties
        log_error(
            f"Divisant un PDF de {total_pages} pages en morceaux de {max_pages_per_chunk} pages"
        )

        chunks = []
        for i in range(0, total_pages, max_pages_per_chunk):
            writer = PdfWriter()

            # Ajouter les pages au nouvel objet PDF
            end_page = min(i + max_pages_per_chunk, total_pages)
            for page_num in range(i, end_page):
                writer.add_page(pdf.pages[page_num])

            # Créer un fichier temporaire pour cette partie
            temp_file = tempfile.NamedTemporaryFile(delete=False, suffix=".pdf")
            temp_files.append(temp_file.name)

            # Écrire le contenu dans le fichier temporaire
            with open(temp_file.name, "wb") as output_file:
                writer.write(output_file)

            chunks.append(temp_file.name)

        return chunks, True

    except Exception as e:
        log_error(
            f"Erreur lors de la division du PDF: {str(e)}\n{traceback.format_exc()}"
        )
        # En cas d'erreur, supprimer tous les fichiers temporaires créés
        for temp_file in temp_files:
            if os.path.exists(temp_file):
                os.remove(temp_file)
        return [input_path], False


def clean_amount(amount_str):
    """Nettoie une chaîne de montant et la convertit en float"""
    if not amount_str:
        return 0.0
    
    # Convertir en string si ce n'est pas déjà le cas
    if not isinstance(amount_str, str):
        amount_str = str(amount_str)
    
    # Supprimer les espaces, €, et autres caractères
    cleaned = amount_str.replace('€', '').replace(' ', '').replace(',', '.')
    
    try:
        return float(cleaned)
    except ValueError:
        return 0.0


def is_valid_service_or_product(description, unit_price, total_amount):
    """
    Détermine si un élément est un vrai service/produit à conserver
    """
    if not description:
        return False
    
    description_lower = description.lower().strip()
    
    # Mots-clés qui indiquent des vrais services/produits facturables
    valid_keywords = [
        # Télécoms/Internet
        'abonnement', 'forfait', 'appels', 'sms', 'mms', 'internet', 'data',
        'freebox', 'ligne', 'mobile', 'fixe', 'illimité', 'go', 'mo', 'gb', 'mb',
        '4g', '5g', '3g', 'wifi', 'adsl', 'fibre', 'débit',
        
        # Services numériques
        'netflix', 'canal', 'tv', 'chaînes', 'streaming', 'vidéo', 'musique',
        'spotify', 'deezer', 'amazon', 'prime', 'disney', 'ocs', 'bein',
        'cafeyn', 'presse', 'magazine', 'journal',
        
        # Matériel/Équipement
        'équipement', 'matériel', 'boîtier', 'décodeur', 'modem', 'routeur',
        'téléphone', 'smartphone', 'tablette', 'accessoire', 'câble',
        'installation', 'mise en service', 'configuration',
        
        # Services techniques
        'maintenance', 'support', 'assistance', 'dépannage', 'intervention',
        'réparation', 'remplacement', 'upgrade', 'migration',
        
        # Produits physiques
        'vente', 'achat', 'location', 'leasing', 'garantie', 'assurance',
        
        # ========== NOUVEAUX: Services Cloud & IT ==========
        # Services Microsoft Azure
        'azure', 'microsoft', 'office 365', 'o365', 'teams', 'sharepoint',
        'exchange', 'onedrive', 'power bi', 'dynamics', 'sql server',
        'windows server', 'active directory', 'ad', 'backup', 'sauvegarde',
        
        # Services Cloud généraux
        'cloud', 'saas', 'paas', 'iaas', 'serveur', 'server', 'instance',
        'stockage', 'storage', 'compute', 'calcul', 'vm', 'machine virtuelle',
        'container', 'kubernetes', 'docker', 'database', 'base de données',
        'cdn', 'load balancer', 'firewall', 'vpn', 'ssl', 'certificat',
        
        # Services Amazon AWS
        'aws', 'amazon web services', 'ec2', 's3', 'rds', 'lambda',
        'cloudfront', 'route 53', 'elastic', 'auto scaling',
        
        # Services Google Cloud
        'google cloud', 'gcp', 'compute engine', 'app engine', 'bigquery',
        'cloud storage', 'kubernetes engine', 'firebase',
        
        # Services IT généraux
        'hébergement', 'hosting', 'domaine', 'domain', 'email', 'messagerie',
        'antivirus', 'sécurité', 'security', 'monitoring', 'surveillance',
        'supervision', 'siem', 'endpoint', 'patch', 'mise à jour',
        'licence', 'license', 'subscription', 'souscription',
        
        # Services de développement
        'développement', 'development', 'api', 'sdk', 'devops', 'ci/cd',
        'git', 'github', 'gitlab', 'jenkins', 'terraform', 'ansible',
        
        # Services de données
        'analytics', 'analytique', 'data warehouse', 'etl', 'bi',
        'business intelligence', 'machine learning', 'ai', 'ia',
        'artificial intelligence', 'deep learning'
    ]
    
    # Mots-clés à ignorer (lignes administratives, totaux, etc.)
    ignore_keywords = [
        'total', 'sous-total', 'subtotal', 'somme', 'montant à payer',
        'tva', 'taxe', 'dont tva', 'hors taxe', 'toutes taxes',
        'report', 'solde', 'crédit', 'avoir', 'remboursement',
        'facture', 'période', 'du au', 'détail', 'récapitulatif',
        'ligne de facture', 'poste', 'rubrique', 'compte de service',
        'frais de dossier', 'frais administratif', 'commission'
    ]
    
    # Vérifier si c'est une ligne à ignorer
    if any(keyword in description_lower for keyword in ignore_keywords):
        log_mindee_response(f"  [IGNORE] Ligne administrative: {description}")
        return False
    
    # Vérifier si c'est un vrai service/produit
    has_valid_keyword = any(keyword in description_lower for keyword in valid_keywords)
    
    # Doit avoir un prix > 0
    price_value = clean_amount(unit_price) if unit_price else clean_amount(total_amount)
    has_price = price_value > 0
    
    # Description doit faire au moins 5 caractères
    has_description = len(description_lower) >= 5
    
    log_mindee_response(f"  Analyse: '{description}' - Prix: {price_value}, Mot-clé valide: {has_valid_keyword}")
    
    return has_valid_keyword and has_price and has_description


# Fonction pour traiter un fichier PDF avec Mindee
def detect_price_type_and_convert(item, all_items_data=None):
    """
    Détecte intelligemment si les prix sont en HT ou TTC et fait les conversions appropriées
    """
    description = getattr(item, 'description', None) or ""
    product_code = getattr(item, 'product_code', None) or ""
    quantity = getattr(item, 'quantity', None)
    unit_price = getattr(item, 'unit_price', None)
    total_amount = getattr(item, 'total_amount', None)
    tax_rate = getattr(item, 'tax_rate', None)
    tax_amount = getattr(item, 'tax_amount', None)
    
    # Nettoyer et convertir les valeurs
    quantity_value = clean_amount(quantity) if quantity else 1.0
    unit_price_value = clean_amount(unit_price) if unit_price else 0.0
    total_amount_value = clean_amount(total_amount) if total_amount else 0.0
    tax_rate_value = clean_amount(tax_rate) if tax_rate else 0.0
    tax_amount_value = clean_amount(tax_amount) if tax_amount else 0.0
    
    log_mindee_response(f"  Valeurs nettoyées - Prix unit: {unit_price_value}, Total: {total_amount_value}, TVA: {tax_rate_value}%, Montant TVA: {tax_amount_value}")
    
    # === MÉTHODE 1: Détection par mot-clés dans la description ===
    description_lower = description.lower()
    ht_keywords = ['ht', 'hors taxe', 'hors taxes', 'excluding tax', 'ex tax', 'net']
    ttc_keywords = ['ttc', 'toutes taxes comprises', 'including tax', 'inc tax', 'gross', 'tva comprise']
    
    is_ht_by_keyword = any(keyword in description_lower for keyword in ht_keywords)
    is_ttc_by_keyword = any(keyword in description_lower for keyword in ttc_keywords)
    
    if is_ht_by_keyword:
        log_mindee_response(f"  [DÉTECTION] Mots-clés HT détectés dans: '{description}'")
        price_type = "HT"
    elif is_ttc_by_keyword:
        log_mindee_response(f"  [DÉTECTION] Mots-clés TTC détectés dans: '{description}'")
        price_type = "TTC"
    else:
        # === MÉTHODE 2: Détection par cohérence mathématique ===
        price_type = detect_by_calculation(unit_price_value, total_amount_value, quantity_value, tax_rate_value, tax_amount_value)
    
    log_mindee_response(f"  [RÉSULTAT DÉTECTION] Type de prix: {price_type}")
    
    # === CALCULS SELON LE TYPE DÉTECTÉ ===
    if price_type == "HT":
        # Les prix sont déjà HT
        ht_unit_price = unit_price_value
        ht_total_amount = total_amount_value
        
        # Calculer les prix TTC
        if tax_rate_value > 0:
            ttc_unit_price = unit_price_value * (1 + tax_rate_value / 100)
            ttc_total_amount = total_amount_value * (1 + tax_rate_value / 100)
            
            # Calculer le montant de TVA si pas fourni
            if tax_amount_value == 0:
                tax_amount_value = total_amount_value * (tax_rate_value / 100)
        else:
            ttc_unit_price = unit_price_value
            ttc_total_amount = total_amount_value
            
        log_mindee_response(f"  [CALCUL HT] Conservé HT: {ht_unit_price}€ -> Calculé TTC: {ttc_unit_price:.2f}€")
        
    else:  # TTC
        # Les prix sont TTC, convertir en HT
        ttc_unit_price = unit_price_value
        ttc_total_amount = total_amount_value
        
        if tax_rate_value > 0:
            ht_unit_price = unit_price_value / (1 + tax_rate_value / 100)
            ht_total_amount = total_amount_value / (1 + tax_rate_value / 100)
            
            # Calculer le montant de TVA si pas fourni
            if tax_amount_value == 0:
                tax_amount_value = ht_total_amount * (tax_rate_value / 100)
        else:
            ht_unit_price = unit_price_value
            ht_total_amount = total_amount_value
            
        log_mindee_response(f"  [CALCUL TTC] Converti TTC: {ttc_unit_price}€ -> HT: {ht_unit_price:.2f}€")
    
    # === VÉRIFICATIONS DE COHÉRENCE ===
    # Si pas de prix unitaire mais montant total, calculer le prix unitaire
    if ht_unit_price == 0 and ht_total_amount > 0 and quantity_value > 0:
        ht_unit_price = ht_total_amount / quantity_value
        ttc_unit_price = ttc_total_amount / quantity_value if ttc_total_amount > 0 else ht_unit_price
        log_mindee_response(f"  Prix unitaire calculé: {ht_total_amount} / {quantity_value} = {ht_unit_price:.2f}€ HT")
    
    # Si pas de montant total mais prix unitaire, calculer le montant total
    if ht_total_amount == 0 and ht_unit_price > 0 and quantity_value > 0:
        ht_total_amount = ht_unit_price * quantity_value
        ttc_total_amount = ttc_unit_price * quantity_value if ttc_unit_price > 0 else ht_total_amount
        log_mindee_response(f"  Montant total calculé: {ht_unit_price} * {quantity_value} = {ht_total_amount:.2f}€ HT")
    
    return {
        "product_code": product_code.strip() if product_code else "N/A",
        "description": description.strip(),
        "quantity": max(quantity_value, 1.0),
        "unit_price": round(ht_unit_price, 2),  # Prix HT
        "total_amount": round(ht_total_amount, 2),  # Montant HT
        "tax_rate": tax_rate_value,
        "tax_amount": round(tax_amount_value, 2),
        "ttc_unit_price": round(ttc_unit_price, 2),  # Prix TTC
        "ttc_total_amount": round(ttc_total_amount, 2),  # Montant TTC
        "detected_price_type": price_type
    }


def detect_by_calculation(unit_price, total_amount, quantity, tax_rate, tax_amount):
    """
    Détecte le type de prix par analyse mathématique
    """
    if tax_rate <= 0:
        log_mindee_response(f"  [DÉTECTION CALC] Pas de TVA -> considéré comme HT")
        return "HT"
    
    # Test 1: Si on a un montant de TVA explicite
    if tax_amount > 0:
        # Calculer ce que devrait être la TVA si le prix était HT
        expected_tax_ht = total_amount * (tax_rate / 100)
        # Calculer ce que devrait être la TVA si le prix était TTC
        expected_tax_ttc = (total_amount / (1 + tax_rate / 100)) * (tax_rate / 100)
        
        diff_ht = abs(tax_amount - expected_tax_ht)
        diff_ttc = abs(tax_amount - expected_tax_ttc)
        
        log_mindee_response(f"  [TEST TVA] Montant TVA fourni: {tax_amount}")
        log_mindee_response(f"  [TEST TVA] TVA attendue si HT: {expected_tax_ht:.2f} (diff: {diff_ht:.2f})")
        log_mindee_response(f"  [TEST TVA] TVA attendue si TTC: {expected_tax_ttc:.2f} (diff: {diff_ttc:.2f})")
        
        # Tolérance de 0.05€ pour les arrondis
        if diff_ht <= 0.05:
            return "HT"
        elif diff_ttc <= 0.05:
            return "TTC"
    
    # Test 2: Cohérence quantité × prix unitaire = total
    if unit_price > 0 and quantity > 0:
        calculated_total = unit_price * quantity
        diff = abs(calculated_total - total_amount)
        
        log_mindee_response(f"  [TEST COHÉRENCE] {unit_price} × {quantity} = {calculated_total} vs {total_amount} (diff: {diff:.2f})")
        
        # Si la cohérence est bonne (différence < 1€), on teste avec les taux de TVA
        if diff < 1.0:
            # Test si c'est cohérent avec HT
            ttc_calculated = calculated_total * (1 + tax_rate / 100)
            # Test si c'est cohérent avec TTC
            ht_calculated = calculated_total / (1 + tax_rate / 100)
            
            log_mindee_response(f"  [TEST HT] Si HT, TTC devrait être: {ttc_calculated:.2f}")
            log_mindee_response(f"  [TEST TTC] Si TTC, HT devrait être: {ht_calculated:.2f}")
            
            # Pour l'instant, utiliser une heuristique simple
            # Si le taux de TVA est standard (20%, 10%, 5.5%, 2.1%) et qu'on a des montants "ronds"
            if tax_rate in [20.0, 10.0, 5.5, 2.1]:
                # Vérifier si le prix semble "rond" en HT ou TTC
                if is_round_number(unit_price) or is_round_number(total_amount):
                    log_mindee_response(f"  [HEURISTIQUE] Prix rond détecté -> probablement HT")
                    return "HT"
    
    # Test 3: Analyse des centimes (heuristique)
    # Les prix HT ont souvent des centimes "bizarres" après conversion
    # Les prix TTC sont souvent plus "ronds"
    if has_weird_cents(unit_price) or has_weird_cents(total_amount):
        log_mindee_response(f"  [HEURISTIQUE] Centimes 'bizarres' détectés -> probablement TTC avec conversion")
        return "TTC"
    
    # Par défaut, considérer comme TTC (plus courant sur les factures clients)
    log_mindee_response(f"  [DÉFAUT] Aucune détection claire -> considéré comme TTC")
    return "TTC"


def is_round_number(value):
    """Vérifie si un nombre est 'rond' (terminé par .00, .50, etc.)"""
    if value == int(value):  # Nombre entier
        return True
    cents = round((value - int(value)) * 100)
    return cents in [0, 25, 50, 75]  # Quarts d'euro


def has_weird_cents(value):
    """Vérifie si un nombre a des centimes 'bizarres' (souvent résultat de calculs)"""
    if value == int(value):  # Nombre entier
        return False
    cents = round((value - int(value)) * 100)
    # Centimes "bizarres" souvent dus aux conversions TVA
    weird_cents = [1, 2, 3, 4, 6, 7, 8, 9, 11, 12, 13, 14, 16, 17, 18, 19, 
                   21, 22, 23, 24, 26, 27, 28, 29, 31, 32, 33, 34, 36, 37, 38, 39,
                   41, 42, 43, 44, 46, 47, 48, 49, 51, 52, 53, 54, 56, 57, 58, 59,
                   61, 62, 63, 64, 66, 67, 68, 69, 71, 72, 73, 74, 76, 77, 78, 79,
                   81, 82, 83, 84, 86, 87, 88, 89, 91, 92, 93, 94, 96, 97, 98, 99]
    return cents in weird_cents


# Fonction modifiée pour traiter un fichier PDF avec Mindee
def detect_price_type_from_totals_and_items(prediction):
    """
    Détermine si les prix sont HT ou TTC en analysant les totaux ET en vérifiant 
    la cohérence avec la somme des articles individuels
    """
    try:
        total_net = getattr(prediction, 'total_net', None)  # Total HT
        total_amount = getattr(prediction, 'total_amount', None)  # Total général
        total_tax = getattr(prediction, 'total_tax', None)  # Total TVA
        
        # Nettoyer les valeurs
        total_net_value = clean_amount(total_net) if total_net else 0.0
        total_amount_value = clean_amount(total_amount) if total_amount else 0.0
        total_tax_value = clean_amount(total_tax) if total_tax else 0.0
        
        log_mindee_response(f"=== ANALYSE DES TOTAUX FACTURE ===")
        log_mindee_response(f"Total Net (HT): {total_net_value}")
        log_mindee_response(f"Total Amount: {total_amount_value}")
        log_mindee_response(f"Total Tax (TVA): {total_tax_value}")
        
        # Calculer la somme des articles individuels
        sum_individual_amounts = 0.0
        if hasattr(prediction, 'line_items') and prediction.line_items:
            for item in prediction.line_items:
                item_total = getattr(item, 'total_amount', None)
                if item_total:
                    sum_individual_amounts += clean_amount(item_total)
        
        log_mindee_response(f"Somme des montants individuels des articles: {sum_individual_amounts}")
        
        # LOGIQUE PRINCIPALE: Comparer la somme des articles avec les totaux
        
        # CAS 1: total_net existe et somme articles ≈ total_net
        if total_net_value > 0:
            diff_with_net = abs(sum_individual_amounts - total_net_value)
            log_mindee_response(f"Comparaison articles vs total_net: {sum_individual_amounts} vs {total_net_value} (diff: {diff_with_net:.2f})")
            
            if diff_with_net <= 1.0:  # Tolérance de 1€
                log_mindee_response(f"[DÉTECTION] Somme articles = total_net -> Les prix ligne sont HT")
                log_mindee_response(f"[LOGIQUE] Si articles HT = {sum_individual_amounts}, alors total_net = somme HT")
                return "HT", total_net_value, total_amount_value, total_tax_value, sum_individual_amounts
        
        # CAS 2: total_net existe et somme articles ≈ total_amount  
        if total_net_value > 0:
            diff_with_amount = abs(sum_individual_amounts - total_amount_value)
            log_mindee_response(f"Comparaison articles vs total_amount: {sum_individual_amounts} vs {total_amount_value} (diff: {diff_with_amount:.2f})")
            
            if diff_with_amount <= 1.0:  # Tolérance de 1€
                log_mindee_response(f"[DÉTECTION] Somme articles = total_amount -> Les prix ligne sont TTC")
                log_mindee_response(f"[LOGIQUE] Si articles TTC = {sum_individual_amounts}, alors total_net = {total_net_value} (calculé par Mindee)")
                return "TTC", total_net_value, total_amount_value, total_tax_value, sum_individual_amounts
        
        # CAS 3: total_net = 0, comparer avec total_amount
        if total_net_value == 0:
            diff_with_amount = abs(sum_individual_amounts - total_amount_value)
            log_mindee_response(f"total_net=0, comparaison articles vs total_amount: {sum_individual_amounts} vs {total_amount_value} (diff: {diff_with_amount:.2f})")
            
            if diff_with_amount <= 1.0:
                log_mindee_response(f"[DÉTECTION] total_net=0 et somme articles = total_amount -> Les prix ligne sont HT")
                return "HT", sum_individual_amounts, total_amount_value, total_tax_value, sum_individual_amounts
        
        # CAS PAR DÉFAUT: Utiliser la logique simple
        if total_net_value > 0:
            log_mindee_response(f"[DÉFAUT] total_net > 0 -> Probablement TTC (logique Mindee)")
            return "TTC", total_net_value, total_amount_value, total_tax_value, sum_individual_amounts
        else:
            log_mindee_response(f"[DÉFAUT] total_net = 0 -> Probablement HT")
            return "HT", total_amount_value, total_amount_value, total_tax_value, sum_individual_amounts
        
    except Exception as e:
        log_mindee_response(f"Erreur dans detect_price_type_from_totals_and_items: {e}")
        return "HT", 0.0, 0.0, 0.0, 0.0


def process_item_with_global_context(item, price_type, global_net_total, global_gross_total, global_tax_total, sum_individual):
    """
    Traite un item en tenant compte du contexte global de la facture
    """
    description = getattr(item, 'description', None) or ""
    product_code = getattr(item, 'product_code', None) or ""
    quantity = getattr(item, 'quantity', None)
    unit_price = getattr(item, 'unit_price', None)
    total_amount = getattr(item, 'total_amount', None)
    tax_rate = getattr(item, 'tax_rate', None)
    tax_amount = getattr(item, 'tax_amount', None)
    
    # Nettoyer et convertir les valeurs
    quantity_value = clean_amount(quantity) if quantity else 1.0
    unit_price_value = clean_amount(unit_price) if unit_price else 0.0
    total_amount_value = clean_amount(total_amount) if total_amount else 0.0
    tax_rate_value = clean_amount(tax_rate) if tax_rate else 0.0
    tax_amount_value = clean_amount(tax_amount) if tax_amount else 0.0
    
    log_mindee_response(f"  Traitement item avec contexte global - Type détecté: {price_type}")
    log_mindee_response(f"  Valeurs brutes: Prix={unit_price_value}, Total={total_amount_value}, TVA%={tax_rate_value}")
    
    # Calculer selon le type détecté globalement
    if price_type == "TTC":
        # Les prix fournis sont TTC, calculer les HT
        ttc_unit_price = unit_price_value
        ttc_total_amount = total_amount_value
        
        if tax_rate_value > 0:
            ht_unit_price = unit_price_value / (1 + tax_rate_value / 100)
            ht_total_amount = total_amount_value / (1 + tax_rate_value / 100)
            
            # Calculer le montant de TVA si pas fourni
            if tax_amount_value == 0:
                tax_amount_value = ht_total_amount * (tax_rate_value / 100)
        else:
            # Pas de TVA, HT = TTC
            ht_unit_price = unit_price_value
            ht_total_amount = total_amount_value
            
        log_mindee_response(f"  [TTC->HT] {ttc_unit_price}€ TTC -> {ht_unit_price:.2f}€ HT")
        
    else:  # HT
        # Les prix fournis sont HT
        ht_unit_price = unit_price_value
        ht_total_amount = total_amount_value
        
        if tax_rate_value > 0:
            ttc_unit_price = unit_price_value * (1 + tax_rate_value / 100)
            ttc_total_amount = total_amount_value * (1 + tax_rate_value / 100)
            
            # Calculer le montant de TVA si pas fourni
            if tax_amount_value == 0:
                tax_amount_value = ht_total_amount * (tax_rate_value / 100)
        else:
            # Pas de TVA, TTC = HT
            ttc_unit_price = unit_price_value
            ttc_total_amount = total_amount_value
            
        log_mindee_response(f"  [HT->TTC] {ht_unit_price}€ HT -> {ttc_unit_price:.2f}€ TTC")
    
    # Vérifications de cohérence et calculs manquants
    if ht_unit_price == 0 and ht_total_amount > 0 and quantity_value > 0:
        ht_unit_price = ht_total_amount / quantity_value
        ttc_unit_price = ttc_total_amount / quantity_value if ttc_total_amount > 0 else ht_unit_price
        log_mindee_response(f"  Prix unitaire calculé: {ht_unit_price:.2f}€ HT")
    
    if ht_total_amount == 0 and ht_unit_price > 0 and quantity_value > 0:
        ht_total_amount = ht_unit_price * quantity_value
        ttc_total_amount = ttc_unit_price * quantity_value if ttc_unit_price > 0 else ht_total_amount
        log_mindee_response(f"  Montant total calculé: {ht_total_amount:.2f}€ HT")
    
    return {
        "product_code": product_code.strip() if product_code else "N/A",
        "description": description.strip(),
        "quantity": max(quantity_value, 1.0),
        "unit_price": round(ht_unit_price, 2),  # Prix HT
        "total_amount": round(ht_total_amount, 2),  # Montant HT
        "tax_rate": tax_rate_value,
        "tax_amount": round(tax_amount_value, 2),
        "ttc_unit_price": round(ttc_unit_price, 2),  # Prix TTC
        "ttc_total_amount": round(ttc_total_amount, 2),  # Montant TTC
        "detected_price_type": price_type,
        "global_context": {
            "net_total": global_net_total,
            "gross_total": global_gross_total,
            "tax_total": global_tax_total
        }
    }


def process_with_mindee(file_path):
    """
    Version améliorée utilisant les totaux Mindee pour détecter HT/TTC
    """
    try:
        from mindee import Client, product

        log_mindee_response(f"=== DÉBUT TRAITEMENT MINDEE V2 POUR: {file_path} ===")
        
        # Configuration Mindee
        mindee_client = Client(api_key="cc22c9fef6b64ce64328253a485f8315")
        log_mindee_response("Client Mindee initialisé avec succès")

        # Chargement du fichier
        input_doc = mindee_client.source_from_path(file_path)
        log_mindee_response(f"Document chargé depuis: {file_path}")

        # Analyse du document avec le modèle de facture
        log_mindee_response("Envoi de la requête à l'API Mindee...")
        result = mindee_client.parse(product.InvoiceV4, input_doc)
        log_mindee_response("Réponse reçue de l'API Mindee")

        # Log complet des totaux disponibles
        prediction = result.document.inference.prediction
        log_mindee_response("=== TOTAUX DISPONIBLES ===")
        for attr_name in ['total_net', 'total_amount', 'total_tax', 'total_excl', 'total_incl']:
            if hasattr(prediction, attr_name):
                value = getattr(prediction, attr_name)
                log_mindee_response(f"{attr_name}: {value}")

        # ÉTAPE 1: Déterminer le type de prix global
        price_type, net_total, gross_total, tax_total, sum_articles = detect_price_type_from_totals_and_items(prediction)
        
        log_mindee_response(f"=== RÉSULTAT DÉTECTION GLOBALE ===")
        log_mindee_response(f"Type de prix détecté: {price_type}")
        log_mindee_response(f"Total net (HT): {net_total}")
        log_mindee_response(f"Total brut (TTC): {gross_total}")
        log_mindee_response(f"Total TVA: {tax_total}")
        log_mindee_response(f"Somme articles individuels: {sum_articles}")

        # ÉTAPE 2: Traiter chaque ligne avec le contexte global
        line_items = []
        try:            
            if hasattr(prediction, 'line_items') and prediction.line_items:
                total_items = len(prediction.line_items)
                items_kept = 0
                
                log_mindee_response(f"Nombre total d'items trouvés: {total_items}")
                
                for i, item in enumerate(prediction.line_items):
                    log_mindee_response(f"--- ANALYSE ITEM {i+1}/{total_items} ---")
                    
                    # Traiter avec le contexte global
                    processed_item = process_item_with_global_context(
                        item, price_type, net_total, gross_total, tax_total, sum_articles
                    )
                    
                    # Vérifier si c'est un service/produit valide
                    if not is_valid_service_or_product(
                        processed_item["description"], 
                        processed_item["unit_price"], 
                        processed_item["total_amount"]
                    ):
                        log_mindee_response(f"  [IGNORE] - Ne correspond pas aux critères de service/produit")
                        continue
                    
                    log_mindee_response(f"  [CONSERVÉ] Article final: {processed_item}")
                    line_items.append(processed_item)
                    items_kept += 1
                
                log_mindee_response(f"RÉSUMÉ FINAL: {items_kept}/{total_items} items conservés")
                
            else:
                log_mindee_response("Aucun line_items trouvé dans la réponse Mindee")
                
        except Exception as e:
            log_error(f"Erreur lors du traitement des items: {str(e)}")
            log_mindee_response(f"ERREUR items: {str(e)}\n{traceback.format_exc()}")

        log_mindee_response(f"=== FIN TRAITEMENT MINDEE V2 - {len(line_items)} items extraits ===\n")
        return line_items

    except Exception as e:
        log_error(f"Erreur lors du traitement avec Mindee V2: {str(e)}")
        log_mindee_response(f"ERREUR GLOBALE MINDEE V2: {str(e)}\n{traceback.format_exc()}")
        return []


# Fonction clean_amount à conserver
def clean_amount(amount_str):
    """Nettoie une chaîne de montant et la convertit en float"""
    if not amount_str:
        return 0.0
    
    # Convertir en string si ce n'est pas déjà le cas
    if not isinstance(amount_str, str):
        amount_str = str(amount_str)
    
    # Supprimer les espaces, €, et autres caractères
    cleaned = amount_str.replace('€', '').replace(' ', '').replace(',', '.')
    
    try:
        return float(cleaned)
    except ValueError:
        return 0.0
try:
    # Vérification des arguments
    if len(sys.argv) < 3:
        print(
            json.dumps(
                {"error": "Arguments manquants: chemin du fichier et ID du fichier"}
            )
        )
        sys.exit(1)

    # Récupération des arguments
    file_path = sys.argv[1]
    file_id = int(sys.argv[2])
    original_name = sys.argv[3] if len(sys.argv) > 3 else ""
    relative_path = sys.argv[4] if len(sys.argv) > 4 else ""

    # Nombre maximum de pages par partie
    max_pages_per_chunk = 10

    # Vérification que le fichier existe
    if not os.path.exists(file_path):
        log_error(f"Le fichier n'existe pas: {file_path}")
        print(json.dumps({"error": f"Le fichier n'existe pas: {file_path}"}))
        sys.exit(1)

    # Connexion à la base de données
    conn = None
    cursor = None
    temp_files_created = []

    try:
        db_config = {
            "host": "localhost",
            "user": "root",
            "password": "",
            "database": "extractys",
        }

        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)

        # Vérifier si une analyse existe déjà pour ce fichier
        cursor.execute(
            "SELECT id FROM pdf_analyses WHERE pdf_file_id = %s LIMIT 1", (file_id,)
        )
        existing_analysis = cursor.fetchone()

        if existing_analysis:
            # L'analyse existe déjà, récupérer les données existantes
            analyse_id = existing_analysis["id"]

            # Récupérer les lignes d'articles
            cursor.execute(
                "SELECT * FROM invoice_line_items WHERE analyse_id = %s", (analyse_id,)
            )
            line_items = cursor.fetchall()

            # Préparer la réponse
            invoice_data = {
                "analyse_id": analyse_id,
                "file_id": file_id,
                "line_items": line_items,
                "from_database": True,
            }

            # Retourner les données existantes
            print(json.dumps(invoice_data, cls=DecimalEncoder))
            sys.exit(0)

        # Diviser le PDF si nécessaire
        pdf_chunks, was_split = split_pdf(file_path, max_pages_per_chunk)
        temp_files_created = pdf_chunks if was_split else []

        # Traiter chaque partie du PDF
        all_line_items = []
        for chunk_path in pdf_chunks:
            chunk_items = process_with_mindee(chunk_path)
            all_line_items.extend(chunk_items)

        # Vérifier si la table existe
        cursor.execute("SHOW TABLES LIKE 'pdf_analyses'")
        if not cursor.fetchone():
            # Créer la table si elle n'existe pas
            cursor.execute(
                """
            CREATE TABLE pdf_analyses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pdf_file_id INT NOT NULL,
                created_at DATETIME
            )
            """
            )
            conn.commit()

        # Insérer une entrée dans pdf_analyses
        query = """
        INSERT INTO pdf_analyses 
        (pdf_file_id, created_at) 
        VALUES (%s, %s)
        """

        values = (file_id, datetime.now())

        cursor.execute(query, values)
        conn.commit()

        # Récupérer l'ID de l'analyse insérée
        analyse_id = cursor.lastrowid

        # Vérifier si la table ligne_items existe et la modifier si nécessaire
        cursor.execute("SHOW TABLES LIKE 'invoice_line_items'")
        if not cursor.fetchone():
            # Créer la table avec les nouveaux champs
            cursor.execute(
                """
            CREATE TABLE invoice_line_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                analyse_id INT NOT NULL,
                product_code VARCHAR(100),
                description TEXT,
                quantity DECIMAL(10,2),
                unit_price DECIMAL(10,2) COMMENT 'Prix unitaire HT',
                total_amount DECIMAL(10,2) COMMENT 'Montant total HT',
                tax_rate DECIMAL(5,2) COMMENT 'Taux de TVA en %',
                tax_amount DECIMAL(10,2) COMMENT 'Montant de la TVA',
                ttc_unit_price DECIMAL(10,2) COMMENT 'Prix unitaire TTC',
                ttc_total_amount DECIMAL(10,2) COMMENT 'Montant total TTC',
                FOREIGN KEY (analyse_id) REFERENCES pdf_analyses(id) ON DELETE CASCADE
            )
            """
            )
            conn.commit()
        else:
            # Vérifier si les nouvelles colonnes existent
            cursor.execute("DESCRIBE invoice_line_items")
            columns = [row['Field'] for row in cursor.fetchall()]
            
            if 'tax_rate' not in columns:
                cursor.execute("ALTER TABLE invoice_line_items ADD COLUMN tax_rate DECIMAL(5,2) COMMENT 'Taux de TVA en %'")
            if 'tax_amount' not in columns:
                cursor.execute("ALTER TABLE invoice_line_items ADD COLUMN tax_amount DECIMAL(10,2) COMMENT 'Montant de la TVA'")
            if 'ttc_unit_price' not in columns:
                cursor.execute("ALTER TABLE invoice_line_items ADD COLUMN ttc_unit_price DECIMAL(10,2) COMMENT 'Prix unitaire TTC'")
            if 'ttc_total_amount' not in columns:
                cursor.execute("ALTER TABLE invoice_line_items ADD COLUMN ttc_total_amount DECIMAL(10,2) COMMENT 'Montant total TTC'")
            
            conn.commit()

        # Insérer les lignes d'articles avec les nouveaux champs
        if all_line_items:
            line_item_query = """
            INSERT INTO invoice_line_items 
            (analyse_id, product_code, description, quantity, unit_price, total_amount, 
             tax_rate, tax_amount, ttc_unit_price, ttc_total_amount) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """

            for item in all_line_items:
                item_values = (
                    analyse_id,
                    item["product_code"],
                    item["description"],
                    item["quantity"],
                    item["unit_price"],  # HT
                    item["total_amount"],  # HT
                    item.get("tax_rate", 0),
                    item.get("tax_amount", 0),
                    item.get("ttc_unit_price", item["unit_price"]),  # TTC
                    item.get("ttc_total_amount", item["total_amount"])  # TTC
                )
                cursor.execute(line_item_query, item_values)

            conn.commit()

        # Ajout de l'ID de l'analyse aux données
        invoice_data = {
            "analyse_id": analyse_id,
            "file_id": file_id,
            "line_items": all_line_items,
            "from_database": False,
            "was_split": was_split,
            "chunks_processed": len(pdf_chunks),
        }

        # Fermeture des connexions
        cursor.close()
        conn.close()

        # Afficher les résultats en JSON pour que PHP puisse les récupérer
        print(json.dumps(invoice_data, cls=DecimalEncoder))

        # Sortie avec succès
        sys.exit(0)

    except Exception as e:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()
        log_error(
            f"Erreur lors du traitement de la base de données: {str(e)}\n{traceback.format_exc()}"
        )
        print(
            json.dumps(
                {"error": f"Erreur lors du traitement de la base de données: {str(e)}"}
            )
        )
        sys.exit(1)
    finally:
        # Nettoyer les fichiers temporaires
        for temp_file in temp_files_created:
            if temp_file != file_path and os.path.exists(temp_file):
                try:
                    os.remove(temp_file)
                except:
                    pass

except Exception as e:
    log_error(f"Erreur générale: {str(e)}\n{traceback.format_exc()}")
    print(json.dumps({"error": f"Erreur générale: {str(e)}"}))
    sys.exit(1)