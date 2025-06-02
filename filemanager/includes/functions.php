<?php
// filepath: filemanager/includes/functions.php
// Fonctions utilitaires pour le gestionnaire de fichiers

/**
 * Formate la taille d'un fichier en une chaîne lisible
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Nettoie un nom de fichier
 */
function sanitizeFileName($name)
{
    // Supprimer les caractères spéciaux et remplacer les espaces
    $name = preg_replace('/[^\p{L}\p{N}_\-\.]/u', '_', $name);

    // Limiter la longueur du nom
    if (strlen($name) > 255) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = substr(pathinfo($name, PATHINFO_FILENAME), 0, 250 - strlen($ext));
        if ($ext) {
            $name .= '.' . $ext;
        }
    }

    return $name;
}

/**
 * Obtient l'icône correspondant au type de fichier
 */
function getFileIcon($filename)
{
    global $file_icons;

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (isset($file_icons[$extension])) {
        return $file_icons[$extension];
    }

    return $file_icons['default'];
}

/**
 * Vérifie si le type de fichier est une image
 */
function isImage($filename)
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
}

/**
 * Génère une réponse JSON
 */
function jsonResponse($data, $status_code = 200)
{
    http_response_code($status_code);

    // Nettoyer la sortie pour éviter les erreurs
    if (ob_get_level()) ob_end_clean();

    // Définir des en-têtes explicites
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    // S'assurer que les caractères spéciaux sont correctement encodés
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Détermine si un fichier peut être prévisualisé
 */
function isPreviewable($filename)
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Images et PDF
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
        return true;
    }

    // Fichiers texte
    if (in_array($extension, ['txt', 'csv'])) {
        return true;
    }

    return false;
}

/**
 * Vérifie si le jeton CSRF fourni est valide
 * 
 * @param string $token Le jeton à vérifier
 * @return bool True si le jeton est valide, false sinon
 */
function checkCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // Utiliser hash_equals pour une comparaison sécurisée contre les attaques timing
    return hash_equals($_SESSION['csrf_token'], $token);
}
