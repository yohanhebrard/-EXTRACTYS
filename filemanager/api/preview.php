<?php
// Fichier: api/preview.php
// Version finale avec la structure BDD correcte

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction d'authentification locale
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Vérifier l'authentification
if (!isAuthenticated()) {
    http_response_code(401);
    exit('Non autorisé - Veuillez vous connecter');
}

// Récupérer les paramètres
$fileId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'file';

if (!$fileId || !is_numeric($fileId)) {
    http_response_code(400);
    exit('ID de fichier manquant ou invalide');
}

try {
    // Essayer de charger la configuration
    $configPaths = [
        '../includes/config.php',
        '../../login_form/init.php',
        '../login_form/init.php',
        '../../includes/config.php'
    ];
    
    $configLoaded = false;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            $configLoaded = true;
            break;
        }
    }
    
    if (!$configLoaded) {
        http_response_code(500);
        exit('Configuration non trouvée');
    }
    
    // Vérifier si $db existe
    if (!isset($db)) {
        // Paramètres de base de données (ajustez selon votre configuration)
        $host = 'localhost';
        $dbname = 'extractys';
        $username = 'root';
        $password = '';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $db = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    
    // Récupérer les informations du fichier avec vérification de propriété
    // Structure correcte basée sur les logs : files table avec user_id direct
    $stmt = $db->prepare("
        SELECT id, name, path, type, size, folder_id, user_id, created_at, updated_at
        FROM files 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$fileId, $_SESSION['user_id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        exit('Fichier non trouvé ou accès non autorisé');
    }
    
    // Construire le chemin du fichier selon votre structure
    $userId = $_SESSION['user_id'];
    $fileName = $file['name']; // Le nom original du fichier
    $filePath = $file['path']; // Le chemin relatif : /Racine/filename.pdf
    
    // Construire les chemins possibles en utilisant le chemin de la BDD
    $possiblePaths = [
        // Chemin basé sur le path de la BDD
        "../storage/users/$userId" . $filePath,
        "../../storage/users/$userId" . $filePath,
        "../../../storage/users/$userId" . $filePath,
        
        // Chemin absolu Windows
        "C:/laragon/www/EXTRACTYS/storage/users/$userId" . $filePath,
        
        // Variations avec le nom du fichier directement
        "../storage/users/$userId/Racine/$fileName",
        "../../storage/users/$userId/Racine/$fileName",
        "../../../storage/users/$userId/Racine/$fileName",
        
        // Chemin absolu avec nom de fichier
        "C:/laragon/www/EXTRACTYS/storage/users/$userId/Racine/$fileName",
        
        // Variations alternatives
        "../storage/users/$userId/$fileName",
        "../../storage/users/$userId/$fileName",
        "../../../storage/users/$userId/$fileName",
        
        // Anciens chemins au cas où
        "../uploads/$fileName",
        "../../uploads/$fileName",
        "../../../uploads/$fileName"
    ];
    
    $actualFilePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $actualFilePath = $path;
            break;
        }
    }
    
    if (!$actualFilePath) {
        // Log détaillé pour débogage
        error_log("Fichier physique non trouvé pour ID: $fileId");
        error_log("User ID: $userId");
        error_log("Nom fichier: $fileName");
        error_log("Path BDD: $filePath");
        error_log("Chemins testés: " . implode(', ', $possiblePaths));
        
        http_response_code(404);
        exit('Fichier physique non trouvé');
    }
    
    // Obtenir l'extension du fichier
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Définir les headers appropriés selon le type de fichier
    switch ($extension) {
        case 'pdf':
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
            // Headers spéciaux pour PDF
            header('X-Content-Type-Options: nosniff');
            header('Accept-Ranges: bytes');
            break;
            
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
            
        case 'png':
            header('Content-Type: image/png');
            break;
            
        case 'gif':
            header('Content-Type: image/gif');
            break;
            
        case 'webp':
            header('Content-Type: image/webp');
            break;
            
        case 'svg':
            header('Content-Type: image/svg+xml');
            break;
            
        case 'txt':
        case 'log':
            header('Content-Type: text/plain; charset=utf-8');
            break;
            
        case 'html':
        case 'htm':
            header('Content-Type: text/html; charset=utf-8');
            break;
            
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            break;
            
        case 'js':
            header('Content-Type: application/javascript; charset=utf-8');
            break;
            
        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            break;
            
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            break;
            
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            break;
            
        default:
            // Utiliser le type MIME de la BDD si disponible
            if (!empty($file['type'])) {
                header('Content-Type: ' . $file['type']);
            } else {
                // Sinon, essayer de détecter automatiquement
                if (function_exists('mime_content_type')) {
                    $mimeType = mime_content_type($actualFilePath);
                    if ($mimeType) {
                        header('Content-Type: ' . $mimeType);
                    } else {
                        header('Content-Type: application/octet-stream');
                    }
                } else {
                    header('Content-Type: application/octet-stream');
                }
            }
            break;
    }
    
    // Headers de sécurité et de cache
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Cache-Control: private, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    
    // Taille du fichier
    $fileSize = filesize($actualFilePath);
    if ($fileSize !== false) {
        header('Content-Length: ' . $fileSize);
    }
    
    // Support des requêtes de plage pour les gros fichiers (utile pour les PDF)
    if (isset($_SERVER['HTTP_RANGE']) && $extension === 'pdf') {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = $matches[2] ? intval($matches[2]) : $fileSize - 1;
            $end = min($end, $fileSize - 1);
            
            if ($start <= $end) {
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$fileSize");
                header('Content-Length: ' . ($end - $start + 1));
                
                $fileHandle = fopen($actualFilePath, 'rb');
                if ($fileHandle) {
                    fseek($fileHandle, $start);
                    echo fread($fileHandle, $end - $start + 1);
                    fclose($fileHandle);
                }
                exit;
            }
        }
    }
    
    // Lire et envoyer le fichier
    if ($fileSize > 10 * 1024 * 1024) { // Si > 10MB, lire par chunks
        $handle = fopen($actualFilePath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            fclose($handle);
        }
    } else {
        readfile($actualFilePath);
    }
    
} catch (PDOException $e) {
    error_log("Erreur base de données dans preview.php: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur de base de données');
} catch (Exception $e) {
    error_log("Erreur générale dans preview.php: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur serveur lors de la prévisualisation');
}
?>