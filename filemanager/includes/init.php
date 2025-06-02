// Au début du fichier, après session_start()

// Prolonger la durée de la session à chaque requête
session_regenerate_id(false); // Régénère l'ID de session sans effacer les données
$_SESSION['last_activity'] = time(); // Mettre à jour le timestamp de dernière activité

// Vérifier si la session a expiré (après 30 minutes d'inactivité)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
// La session a expiré, détruire la session
session_unset();
session_destroy();
}