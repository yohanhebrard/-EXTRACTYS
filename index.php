<?php
require_once '../login_form/init.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/FileManager.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: ../login_form/public/login.php');
    exit;
}

// Initialiser le gestionnaire de fichiers
$fileManager = new FileManager($db, $_SESSION['user_id']);

// Récupérer le dossier courant
$current_folder = isset($_GET['folder']) ? intval($_GET['folder']) : null;

// Initialiser $content avec une structure par défaut
$content = [
    'current_folder' => ['id' => 0, 'name' => 'Racine'],
    'breadcrumb' => [['id' => 0, 'name' => 'Racine', 'parent_id' => null]],
    'folders' => [],
    'files' => []
];

try {
    // Récupérer le contenu du dossier
    $content = $fileManager->getFolderContents($current_folder);
} catch (Exception $e) {
    $error = $e->getMessage();

    // Créer un dossier racine si nécessaire
    if ($e->getMessage() === "Aucun dossier racine trouvé pour cet utilisateur") {
        try {
            // Tenter de créer un dossier racine à la volée
            $stmt = $db->prepare("
                INSERT INTO folders (name, parent_id, user_id)
                VALUES ('Racine', NULL, :user_id)
            ");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);

            // Récupérer le contenu à nouveau
            $content = $fileManager->getFolderContents();
            $error = null; // Effacer l'erreur si ça a fonctionné
        } catch (Exception $newE) {
            $error .= " - Impossible de créer un dossier racine: " . $newE->getMessage();
        }
    }
}

// Générer un token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de fichiers - EXTRACTYS</title>

    <!-- Fichiers CSS de base -->
    <link rel="stylesheet" href="../login_form/assets/css/reset.css">
    <link rel="stylesheet" href="../login_form/assets/css/variables.css">
    <link rel="stylesheet" href="../login_form/assets/css/layout.css">
    <link rel="stylesheet" href="../login_form/assets/css/components.css">

    <link rel="stylesheet" href="../login_form/assets/css/buttons.css">
    <link rel="stylesheet" href="../login_form/assets/css/animations.css">

    <!-- CSS pour le gestionnaire de fichiers -->
    <link rel="stylesheet" href="assets/css/filemanager.css">
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/contextmenu.css">
    <link rel="stylesheet" href="assets/css/breadcrumb.css">

    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Responsive toujours en dernier -->
    <link rel="stylesheet" href="../login_form/assets/css/responsive.css">
    <!-- Dans la section head -->
    <link rel="stylesheet" href="assets/css/contextual-dialogs.css">

    <style>
        .hidden {
            display: none !important;
        }
        .contextual-dialog.delete-confirm,
.contextual-dialog.delete-confirm .contextual-dialog-body,
.contextual-dialog.delete-confirm .contextual-dialog-header,
.contextual-dialog.delete-confirm .contextual-dialog-footer {
    color: #222 !important; /* ou la couleur de texte souhaitée */
}
.hidden {
        display: none !important;
    }
    .contextual-dialog,
    .contextual-dialog .contextual-dialog-body,
    .contextual-dialog .contextual-dialog-header,
    .contextual-dialog .contextual-dialog-footer {
        color: #222 !important; /* couleur de texte lisible */
    }
    .breadcrumb,
.breadcrumb-item,
.breadcrumb-item a,
.breadcrumb-item.active,
.breadcrumb-item span {
    color: #fff !important;           /* texte blanc */
    background: rgba(30,30,30,0.4);   /* fond sombre et translucide */
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 500;
}

    </style>

    <!-- Juste avant la fermeture de la balise body -->
    <input type="hidden" id="csrfToken" value="<?php echo $csrf_token; ?>">
    <input type="hidden" id="currentFolderId" value="<?php echo $current_folder ?? $content['current_folder']['id']; ?>">

    <script src="assets/js/filemanager-core.js"></script>
    <script src="assets/js/filemanager-context-menu.js"></script>
    <script src="assets/js/filemanager-dialogs.js"></script>
    <script src="assets/js/filemanager-operations.js"></script>
    <script src="assets/js/filemanager-drag-drop.js"></script>
    <script src="assets/js/filemanager-item-events.js"></script>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="app-container">
        <header class="app-header">
            <?php if (isAuthenticated()): ?>
        <?php
        // Vérifier si l'utilisateur est admin
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] === 'admin'):
        ?>
            <a href="../login_form/public/admin/users.php" class="btn btn-primary btn-sm">
                <i class="fas fa-users-cog"></i> Gestion utilisateurs
            </a>
        <?php endif; ?>
        
        <a href="logout.php" class="btn btn-light btn-sm">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    <?php else: ?>
        <a href="login.php" class="btn btn-primary btn-sm">
            <i class="fas fa-sign-in-alt"></i> Connexion
        </a>
    <?php endif; ?>
        </header>

        <main class="app-content">
            <div class="filemanager-container">
                <div class="filemanager-actions">
                    <div class="filemanager-breadcrumb">
                        <nav aria-label="Fil d'Ariane">
                            <ol class="breadcrumb">
                                <?php foreach ($content['breadcrumb'] as $index => $folder): ?>
                                    <li class="breadcrumb-item<?php echo ($index === count($content['breadcrumb']) - 1) ? ' active' : ''; ?>">
                                        <?php if ($index === count($content['breadcrumb']) - 1): ?>
                                            <span><?php echo htmlspecialchars($folder['name']); ?></span>
                                        <?php else: ?>
                                            <a href="index.php?folder=<?php echo $folder['id']; ?>">
                                                <?php echo htmlspecialchars($folder['name']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                    <div class="filemanager-buttons">
                        <button class="btn btn-primary" id="btnNewFolder">
                            <i class="fas fa-folder-plus"></i> Nouveau dossier
                        </button>

                        <label for="uploadFile" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Importer un fichier
                        </label>
                        <input type="file" id="uploadFile" class="hidden" />
                        <label for="uploadFiles" class="btn btn-primary">
    <i class="fas fa-upload"></i> Importer des fichiers
</label>
<input type="file" id="uploadFiles" class="hidden" multiple />

                        <!-- Bouton pour analyser tous les fichiers -->
                        <button class="btn btn-info" id="btnAnalyzeAll">
                            <i class="fas fa-search"></i> Analyser tous les fichiers
                        </button>
                    </div>
                </div>

                <div class="filemanager-content">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="filemanager-grid" id="filemanagerGrid">
                        <!-- Dossiers -->
                        <?php if (empty($content['folders']) && empty($content['files'])): ?>
                            <div class="filemanager-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>Ce dossier est vide</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($content['folders'] as $folder): ?>
                                <div class="filemanager-item folder" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                                    <div class="filemanager-item-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div class="filemanager-item-name">
                                        <span><?php echo htmlspecialchars($folder['name']); ?></span>
                                    </div>
                                    <div class="filemanager-item-actions">
                                        <button class="btn btn-icon btn-sm btn-rename" title="Renommer">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="btn btn-icon btn-sm btn-delete" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Fichiers -->
                            <?php foreach ($content['files'] as $file): ?>
                                <div class="filemanager-item file" data-id="<?php echo $file['id']; ?>" data-type="file">
                                    <div class="filemanager-item-icon">
                                        <i class="fas <?php echo getFileIcon($file['name']); ?>"></i>
                                    </div>
                                    <div class="filemanager-item-name">
                                        <span><?php echo htmlspecialchars($file['name']); ?></span>
                                    </div>
                                    <div class="filemanager-item-info">
                                        <span><?php echo formatFileSize($file['size']); ?></span>
                                    </div>
                                    <div class="filemanager-item-actions">
                                        <button class="btn btn-icon btn-sm btn-rename" title="Renommer">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="btn btn-icon btn-sm btn-delete" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <!-- Bouton pour analyser ce fichier -->
                                        <button class="btn btn-icon btn-sm btn-analyze" title="Analyser" data-id="<?php echo $file['id']; ?>">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Menu contextuel -->

    <!-- Modal pour prévisualisation -->
    <div class="modal modal-large" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="previewModalTitle">Prévisualisation</h2>

            </div>
            <div class="modal-body" id="previewModalBody">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="closePreview">Fermer</button>
                <a href="#" class="btn btn-primary" id="downloadFile" download>Télécharger</a>
            </div>
        </div>
    </div>

    <!-- Structure HTML recommandée pour la prévisualisation -->

    <!-- Toast notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- JavaScript pour le gestionnaire de fichiers -->
    <script>
        // Place ce bloc tout en haut, avant tout autre script JS
        function showLoadingMessage(message) {
            showToast(message, 'info', false);
        }

        function hideLoadingMessage() {
            const toasts = document.querySelectorAll('.toast.info');
            toasts.forEach(toast => toast.remove());
        }

        function showSuccessMessage(message) {
            showToast(message, 'success');
        }

        function showErrorMessage(message) {
            showToast(message, 'error');
        }

        function showToast(message, type = 'info', autoClose = true) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                ${autoClose ? '<button class="toast-close"><i class="fas fa-times"></i></button>' : ''}
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            if (autoClose) {
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 5000);

                const closeBtn = toast.querySelector('.toast-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            toast.remove();
                        }, 300);
                    });
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Définir les variables globales
            window.fileManagerConfig = {
                currentFolderId: <?php echo json_encode($current_folder ?? $content['current_folder']['id']); ?>,
                csrfToken: "<?php echo $csrf_token; ?>",
                apiPath: "" // Laissez vide si les API sont dans le même dossier, sinon ajustez
            };

            console.log("Configuration:", window.fileManagerConfig);

            // Surchargez les URLs d'API si nécessaire
            if (document.querySelector('#filemanagerGrid')) {
                console.log("Initialisation du gestionnaire de fichiers");
                try {
                    window.fileManager = new FileManagerCore();
                } catch (error) {
                    console.error('Erreur d\'initialisation:', error);
                }
            } else {
                console.warn("Élément #filemanagerGrid non trouvé, initialisation annulée");
            }
        });

        // Configuration des boutons de fermeture de prévisualisation
        document.querySelectorAll('#close-preview-btn, #closePreview, .modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                // Fermer modal
                const modal = document.getElementById('previewModal');
                if (modal) modal.classList.remove('show');

                // Fermer conteneur fixe
                const container = document.getElementById('file-preview-container');
                if (container) container.style.display = 'none';

                // Nettoyer le contenu
                setTimeout(() => {
                    const modalBody = document.getElementById('previewModalBody');
                    if (modalBody) modalBody.innerHTML = '';

                    const previewContent = document.getElementById('file-preview-content');
                    if (previewContent) previewContent.innerHTML = '';

                    const previewFrame = document.getElementById('file-preview-frame');
                    if (previewFrame) previewFrame.src = 'about:blank';
                }, 300);
            });
        });

        // Gestion du bouton "Analyser tous les fichiers"
        // Gestion du bouton "Analyser tous les fichiers"
// Gestion du bouton "Analyser tous les fichiers"
// Gestion du bouton "Analyser tous les fichiers"
const btnAnalyzeAll = document.getElementById('btnAnalyzeAll');
if (btnAnalyzeAll) {
    btnAnalyzeAll.addEventListener('click', async function() {
        const fileButtons = document.querySelectorAll('.btn-analyze');
        if (fileButtons.length === 0) {
            showErrorMessage("Aucun fichier à analyser.");
            return;
        }
        
        console.log("Boutons d'analyse trouvés:", fileButtons.length);
        
        showLoadingMessage("Analyse de tous les fichiers en cours...");
        
        const fileIds = Array.from(fileButtons).map(btn => btn.dataset.id);
        console.log("IDs de fichiers à analyser:", fileIds);
        
        const csrfToken = document.getElementById('csrfToken').value;

        // Préparer les paramètres de la requête manuellement
        let formData = new FormData();
        formData.append('csrf_token', csrfToken);
        
        // Ajouter chaque ID de fichier individuellement avec le même nom de paramètre
        fileIds.forEach(id => {
            formData.append('ids[]', id);
        });
        
        console.log("Envoi de la requête avec", fileIds.length, "IDs de fichiers");

        try {
            const response = await fetch('api/analyze.php', {
                method: 'POST',
                body: formData
            });
            
            console.log("Réponse reçue du serveur, statut:", response.status);
            
            const responseText = await response.text();
            console.log("Texte de réponse:", responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
                console.log("Données JSON:", data);
            } catch (e) {
                console.error("Erreur de parsing JSON:", e);
                console.error("Texte reçu:", responseText);
                throw new Error("Réponse non-JSON reçue du serveur");
            }
            
            if (data.results && Array.isArray(data.results)) {
                // Afficher les résultats de chaque analyse
                data.results.forEach((result, index) => {
                    console.log(`Résultat d'analyse #${index + 1}:`, result);
                });
                
                // Récupère tous les analyse_id créés
                const analyseIds = data.results
                    .filter(r => r.success !== false && r.analyse_id) // Filtrer les analyses réussies
                    .map(r => r.analyse_id)
                    .filter(id => id !== undefined && id !== null); // Enlever les valeurs undefined/null
                    
                console.log("Analyses IDs extraits:", analyseIds);
                
                if (analyseIds.length > 0) {
                    const redirectUrl = '/EXTRACTYS/filemanager/facture/factures_telecom.php?ids=' + analyseIds.join(',') + '&analyzed=true';
                    console.log("Redirection vers:", redirectUrl);
                    window.location.href = redirectUrl;
                } else if (data.redirect) {
                    console.log("Redirection via data.redirect:", data.redirect);
                    window.location.href = data.redirect;
                } else {
                    hideLoadingMessage();
                    showErrorMessage("Aucune analyse n'a pu être effectuée correctement.");
                }
            } else if (data.redirect) {
                console.log("Redirection simple via data.redirect:", data.redirect);
                window.location.href = data.redirect;
            } else {
                hideLoadingMessage();
                showErrorMessage("Erreur lors de l'analyse: format de réponse incorrect");
            }
        } catch (error) {
            hideLoadingMessage();
            showErrorMessage("Erreur lors de l'analyse: " + error.message);
            console.error("Erreur détaillée:", error);
            
            // En cas d'erreur, rediriger vers la page de factures après un délai
            setTimeout(() => {
                window.location.href = '/EXTRACTYS/filemanager/facture/factures_telecom.php';
            }, 3000);
        }
    });
}
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Supprimer les gestionnaires d'événements existants sur le bouton Nouveau dossier
            const btnNewFolder = document.getElementById('btnNewFolder');
            if (btnNewFolder) {
                // Cloner le bouton pour supprimer tous les écouteurs d'événements
                const newButton = btnNewFolder.cloneNode(true);
                btnNewFolder.parentNode.replaceChild(newButton, btnNewFolder);

                // Réassigner un seul gestionnaire d'événement
                newButton.addEventListener('click', function() {
                    if (window.fileManager && window.fileManager.dialogs) {
                        window.fileManager.dialogs.showNewFolderDialog();
                    }
                });
            }

            console.log("Configuration du gestionnaire d'événements de création de dossier terminée");
        });

        function checkElementExists(selector, message) {
            const element = document.querySelector(selector);
            if (element) {
                console.log(`✅ Élément trouvé: ${selector}`);
            } else {
                console.error(`❌ Élément NON trouvé: ${selector}`);
            }
            return !!element;
        }

        // Fonction pour déboguer le formulaire de renommage
        function debugRenameForm() {
            console.log('🔍 Déboguer le formulaire de renommage:');

            // Vérifier les éléments du formulaire
            checkElementExists('#renameContextual', 'Boîte de dialogue de renommage');
            checkElementExists('#renameContextualInput', 'Champ de saisie du nouveau nom');
            checkElementExists('#renameContextualItemId', 'ID de l\'élément à renommer');
            checkElementExists('#renameContextualItemType', 'Type de l\'élément à renommer');
            checkElementExists('#renameContextualExtension', 'Extension du fichier');
            checkElementExists('#submitRenameContextual', 'Bouton de soumission');

            // Afficher les valeurs s'ils existent
            const id = document.getElementById('renameContextualItemId');
            const type = document.getElementById('renameContextualItemType');
            const name = document.getElementById('renameContextualInput');

            if (id && type && name) {
                console.log('📝 Valeurs du formulaire:', {
                    id: id.value,
                    type: type.value,
                    newName: name.value
                });
            }
        }

        // Ajouter un écouteur d'événements sur la boîte de dialogue de renommage
        document.addEventListener('DOMContentLoaded', function() {
            // S'exécute une fois au chargement de la page
            setTimeout(function() {
                const renameDialog = document.getElementById('renameContextual');
                if (renameDialog) {
                    console.log('🔄 Boîte de dialogue de renommage trouvée et configurée pour le débogage');

                    // Observer quand la boîte de dialogue devient visible
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.attributeName === 'class' &&
                                renameDialog.classList.contains('show')) {
                                debugRenameForm();
                            }
                        });
                    });

                    observer.observe(renameDialog, {
                        attributes: true
                    });
                } else {
                    console.warn('⚠️ La boîte de dialogue de renommage n\'existe pas encore');
                }
            }, 1000); // Attendre 1 seconde pour s'assurer que tout est initialisé
        });
    </script>
    <script>
        // Script pour la page index.php
        document.addEventListener('DOMContentLoaded', function() {
            // Analyse d'un fichier individuel
            document.querySelectorAll('.btn-analyze').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const fileId = this.dataset.id;
                    const csrfToken = document.getElementById('csrfToken').value;

                    // Afficher un loader ou un message d'attente
                    showLoadingMessage('Analyse en cours...');

                    fetch('api/analyze.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                id: fileId,
                                csrf_token: csrfToken
                            })
                        })
                        .then(response => {
                            // Traitement de la réponse
                            return response.text().then(text => {
                                // Filtrer le contenu pour extraire uniquement le JSON valide
                                // Nous cherchons du JSON valide qui commence par { et se termine par }
                                const jsonMatch = text.match(/(\{.*\})/s);
                                if (jsonMatch && jsonMatch[0]) {
                                    try {
                                        return JSON.parse(jsonMatch[0]);
                                    } catch (e) {
                                        console.error('Erreur lors du parsing JSON:', e);
                                        console.error('Texte extrait:', jsonMatch[0]);
                                        console.error('Réponse complète:', text);
                                        throw new Error('Format de réponse invalide');
                                    }
                                } else {
                                    console.error('Aucun JSON valide trouvé dans la réponse:', text);
                                    throw new Error('Aucun JSON valide trouvé dans la réponse');
                                }
                            });
                        })
                        .then(data => {
                            hideLoadingMessage();

                            if (data.success) {
                                showSuccessMessage('Analyse réussie!');
                                // Redirection vers la page des factures avec un petit délai
                                if (data.redirect) {
                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 1000);
                                }
                            } else if (data.error) {
                                showErrorMessage('Erreur: ' + data.error);
                                // Essayer quand même de rediriger si un chemin est spécifié
                                if (data.redirect) {
                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 2000);
                                }
                            } else {
                                showErrorMessage('Analyse impossible.');
                            }
                        })
                        .catch(err => {
                            hideLoadingMessage();
                            showErrorMessage('Erreur: ' + err.message);
                            console.error('Erreur complète:', err);

                            // Redirection de secours en cas d'échec complet
                            setTimeout(() => {
                                window.location.href = '../facture/factures_telecom.php';
                            }, 3000);
                        });
                });
            });
        });
        /**
 * Solution pour éviter les uploads en double
 * Placez ce code dans une balise script à la fin de votre fichier index.php
 */
document.addEventListener('DOMContentLoaded', function() {
    // Attendre que le DOM soit complètement chargé
    setTimeout(function() {
        // Trouver l'input de téléchargement
        const uploadFileInput = document.getElementById('uploadFile');
        
        if (uploadFileInput) {
            console.log('🔄 Réinitialisation du gestionnaire d\'upload...');
            
            // Supprimer tous les gestionnaires d'événements existants
            // en clonant l'élément et en remplaçant l'original
            const newUploadInput = uploadFileInput.cloneNode(true);
            uploadFileInput.parentNode.replaceChild(newUploadInput, uploadFileInput);
            
            // Ajouter un seul gestionnaire d'événement
            newUploadInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                console.log('📤 Téléchargement du fichier:', file.name);
                
                // Afficher un toast pour indiquer que le téléchargement est en cours
                showToast(`Téléchargement de "${file.name}" en cours...`, 'info', false);
                
                // Préparer les données
                const formData = new FormData();
                formData.append('file', file);
                formData.append('folder_id', document.getElementById('currentFolderId').value);
                formData.append('csrf_token', document.getElementById('csrfToken').value);
                
                // Effectuer la requête AJAX
                fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`Le fichier "${file.name}" a été téléchargé avec succès`, 'success');
                        
                        // Recharger la page après un court délai
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(data.error || 'Une erreur est survenue lors du téléchargement', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showToast('Une erreur est survenue lors du téléchargement', 'error');
                })
                .finally(() => {
                    // Réinitialiser l'input pour permettre de télécharger à nouveau le même fichier
                    e.target.value = '';
                });
            });
            
            console.log('✅ Gestionnaire d\'upload réinitialisé avec succès');
        }
    }, 500); // Attendre 500ms pour s'assurer que tous les scripts sont chargés
});

/**
 * Fonction utilitaire pour afficher des toasts
 */
function showToast(message, type = 'info', autoClose = true) {
    // Récupérer ou créer le conteneur de toasts
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Créer le toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Déterminer l'icône
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        </div>
        ${autoClose ? '<button class="toast-close"><i class="fas fa-times"></i></button>' : ''}
    `;
    
    // Ajouter le toast au conteneur
    toastContainer.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Fermeture automatique
    if (autoClose) {
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
        
        // Fermeture manuelle
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        }
    }
    
    return toast;
}
    </script>
    <script>
/**
 * Gestionnaire d'upload multiple de fichiers
 */
document.addEventListener('DOMContentLoaded', function() {
    // Attendre que le DOM soit complètement chargé
    setTimeout(function() {
        // Trouver l'input de téléchargement
        const uploadFilesInput = document.getElementById('uploadFiles');
        
        if (uploadFilesInput) {
            console.log('🔄 Initialisation du gestionnaire d\'upload multiple...');
            
            // Supprimer tous les gestionnaires d'événements existants
            const newUploadInput = uploadFilesInput.cloneNode(true);
            uploadFilesInput.parentNode.replaceChild(newUploadInput, uploadFilesInput);
            
            // Ajouter le gestionnaire pour l'upload multiple
            newUploadInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                if (files.length === 0) return;
                
                console.log(`📤 Téléchargement de ${files.length} fichier(s):`, files.map(f => f.name));
                
                // Afficher un toast global pour tous les fichiers
                const loadingToast = showToast(
                    `Téléchargement de ${files.length} fichier(s) en cours...`, 
                    'info', 
                    false
                );
                
                // Compteurs pour suivre les uploads
                let completed = 0;
                let successful = 0;
                let failed = 0;
                const results = [];
                
                // Fonction pour traiter chaque fichier
                const uploadFile = (file, index) => {
                    return new Promise((resolve) => {
                        const formData = new FormData();
                        formData.append('file', file);
                        formData.append('folder_id', document.getElementById('currentFolderId').value);
                        formData.append('csrf_token', document.getElementById('csrfToken').value);
                        
                        fetch('api/upload.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                successful++;
                                results.push({ file: file.name, success: true });
                                console.log(`✅ ${file.name} téléchargé avec succès`);
                            } else {
                                failed++;
                                results.push({ 
                                    file: file.name, 
                                    success: false, 
                                    error: data.error || 'Erreur inconnue' 
                                });
                                console.log(`❌ Échec du téléchargement de ${file.name}:`, data.error);
                            }
                        })
                        .catch(error => {
                            failed++;
                            results.push({ 
                                file: file.name, 
                                success: false, 
                                error: error.message 
                            });
                            console.error(`❌ Erreur lors du téléchargement de ${file.name}:`, error);
                        })
                        .finally(() => {
                            completed++;
                            resolve();
                        });
                    });
                };
                
                // Traiter tous les fichiers en parallèle
                Promise.all(files.map((file, index) => uploadFile(file, index)))
                    .then(() => {
                        // Supprimer le toast de chargement
                        if (loadingToast) {
                            loadingToast.classList.remove('show');
                            setTimeout(() => loadingToast.remove(), 300);
                        }
                        
                        // Afficher le résumé
                        if (successful > 0 && failed === 0) {
                            // Tous les fichiers ont été téléchargés avec succès
                            showToast(
                                `${successful} fichier(s) téléchargé(s) avec succès`, 
                                'success'
                            );
                        } else if (successful > 0 && failed > 0) {
                            // Succès partiel
                            showToast(
                                `${successful} fichier(s) téléchargé(s), ${failed} échec(s)`, 
                                'warning'
                            );
                            
                            // Afficher les détails des échecs
                            const failedFiles = results.filter(r => !r.success);
                            failedFiles.forEach(result => {
                                showToast(
                                    `Échec: ${result.file} - ${result.error}`, 
                                    'error'
                                );
                            });
                        } else {
                            // Tous les téléchargements ont échoué
                            showToast(
                                `Échec du téléchargement de tous les fichiers`, 
                                'error'
                            );
                        }
                        
                        // Recharger la page si au moins un fichier a été téléchargé
                        if (successful > 0) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    })
                    .finally(() => {
                        // Réinitialiser l'input
                        e.target.value = '';
                    });
            });
            
            console.log('✅ Gestionnaire d\'upload multiple initialisé avec succès');
        }
    }, 500);
});

/**
 * Fonction améliorée pour afficher des toasts avec support des types warning
 */
function showToast(message, type = 'info', autoClose = true) {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        </div>
        ${autoClose ? '<button class="toast-close"><i class="fas fa-times"></i></button>' : ''}
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    if (autoClose) {
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
        
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        }
    }
    
    return toast;
}
/**
 * Solution pour éviter les boîtes de dialogue de suppression en double
 * Ajoutez ce script à la fin de votre fichier index.php, avant la fermeture de </body>
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Initialisation de la correction des suppressions en double...');
    
    // Attendre que tous les scripts soient chargés
    setTimeout(function() {
        fixDeleteHandlers();
    }, 1000);
});

function fixDeleteHandlers() {
    console.log('🔄 Correction des gestionnaires de suppression...');
    
    // Trouver tous les boutons de suppression
    const deleteButtons = document.querySelectorAll('.btn-delete');
    console.log(`Trouvé ${deleteButtons.length} bouton(s) de suppression`);
    
    deleteButtons.forEach((button, index) => {
        console.log(`Traitement du bouton ${index + 1}...`);
        
        // Supprimer tous les gestionnaires d'événements existants en clonant le bouton
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        // Ajouter un seul gestionnaire d'événement propre
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Récupérer les informations de l'élément
            const item = this.closest('.filemanager-item');
            if (!item) {
                console.error('Élément parent non trouvé');
                return;
            }
            
            const itemId = item.dataset.id;
            const itemType = item.dataset.type;
            const itemName = item.querySelector('.filemanager-item-name span')?.textContent || 'cet élément';
            
            console.log('🗑️ Demande de suppression:', { itemId, itemType, itemName });
            
            // Vérifier qu'aucune boîte de dialogue n'est déjà ouverte
            const existingDialog = document.querySelector('.contextual-dialog.show');
            if (existingDialog) {
                console.log('⚠️ Une boîte de dialogue est déjà ouverte, suppression annulée');
                return;
            }
            
            // Créer et afficher la boîte de dialogue de confirmation
            showDeleteConfirmation(itemId, itemType, itemName);
        });
    });
    
    console.log('✅ Gestionnaires de suppression corrigés');
}

function showDeleteConfirmation(itemId, itemType, itemName) {
    console.log('💬 Affichage de la confirmation de suppression pour:', itemName);
    
    // Supprimer toute boîte de dialogue existante
    const existingDialogs = document.querySelectorAll('.delete-confirmation-dialog');
    existingDialogs.forEach(dialog => dialog.remove());
    
    // Créer la boîte de dialogue
    const dialog = document.createElement('div');
    dialog.className = 'contextual-dialog delete-confirmation-dialog show';
    dialog.innerHTML = `
        <div class="contextual-dialog-content">
            <div class="contextual-dialog-header">
                <h3>Confirmer la suppression</h3>
                <button class="contextual-dialog-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="contextual-dialog-body">
                <p>Êtes-vous sûr de vouloir supprimer "${itemName}" ?</p>
                <p class="text-warning">Cette action est irréversible.</p>
            </div>
            <div class="contextual-dialog-footer">
                <button type="button" class="btn btn-secondary cancel-delete">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-danger confirm-delete" 
                        data-id="${itemId}" data-type="${itemType}">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </div>
    `;
    
    // Ajouter la boîte de dialogue au body
    document.body.appendChild(dialog);
    
    // Gestionnaires pour fermer la boîte de dialogue
    const closeButtons = dialog.querySelectorAll('.contextual-dialog-close, .cancel-delete');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            closeDeleteDialog(dialog);
        });
    });
    
    // Gestionnaire pour confirmer la suppression
    const confirmButton = dialog.querySelector('.confirm-delete');
    confirmButton.addEventListener('click', function() {
        const id = this.dataset.id;
        const type = this.dataset.type;
        
        console.log('✅ Confirmation de suppression pour:', { id, type });
        
        // Fermer la boîte de dialogue immédiatement
        closeDeleteDialog(dialog);
        
        // Effectuer la suppression
        performDelete(id, type);
    });
    
    // Fermer en cliquant à côté
    dialog.addEventListener('click', function(e) {
        if (e.target === dialog) {
            closeDeleteDialog(dialog);
        }
    });
}

function closeDeleteDialog(dialog) {
    console.log('❌ Fermeture de la boîte de dialogue de suppression');
    
    if (dialog && dialog.parentNode) {
        dialog.classList.remove('show');
        setTimeout(() => {
            if (dialog.parentNode) {
                dialog.remove();
            }
        }, 300);
    }
}

function performDelete(itemId, itemType) {
    console.log('🗑️ Exécution de la suppression:', { itemId, itemType });
    
    // Afficher un message de chargement
    showToast('Suppression en cours...', 'info', false);
    
    // Préparer les données
    const formData = new FormData();
    formData.append('id', itemId);
    formData.append('type', itemType);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    // Effectuer la requête de suppression
    fetch('api/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Masquer le message de chargement
        hideLoadingMessage();
        
        if (data.success) {
            showToast('Élément supprimé avec succès', 'success');
            
            // Supprimer l'élément du DOM
            const item = document.querySelector(`[data-id="${itemId}"][data-type="${itemType}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    item.remove();
                    
                    // Vérifier s'il reste des éléments
                    const remainingItems = document.querySelectorAll('.filemanager-item');
                    if (remainingItems.length === 0) {
                        const grid = document.getElementById('filemanagerGrid');
                        if (grid) {
                            grid.innerHTML = `
                                <div class="filemanager-empty">
                                    <i class="fas fa-folder-open"></i>
                                    <p>Ce dossier est vide</p>
                                </div>
                            `;
                        }
                    }
                }, 300);
            }
        } else {
            showToast('Erreur lors de la suppression: ' + (data.error || 'Erreur inconnue'), 'error');
        }
    })
    .catch(error => {
        hideLoadingMessage();
        showToast('Erreur lors de la suppression: ' + error.message, 'error');
        console.error('Erreur de suppression:', error);
    });
}

// Fonction utilitaire pour masquer les messages de chargement
function hideLoadingMessage() {
    const loadingToasts = document.querySelectorAll('.toast.info');
    loadingToasts.forEach(toast => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    });
}

// CSS pour la boîte de dialogue de suppression
const deleteDialogCSS = `
<style>
.delete-confirmation-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.delete-confirmation-dialog.show {
    opacity: 1;
}

.delete-confirmation-dialog .contextual-dialog-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-width: 400px;
    width: 90%;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.delete-confirmation-dialog.show .contextual-dialog-content {
    transform: scale(1);
}

.delete-confirmation-dialog .contextual-dialog-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.delete-confirmation-dialog .contextual-dialog-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.delete-confirmation-dialog .contextual-dialog-close {
    background: none;
    border: none;
    font-size: 16px;
    cursor: pointer;
    color: #666;
    padding: 4px;
}

.delete-confirmation-dialog .contextual-dialog-body {
    padding: 20px;
}

.delete-confirmation-dialog .contextual-dialog-body p {
    margin: 0 0 10px 0;
    color: #333;
}

.delete-confirmation-dialog .text-warning {
    color: #856404 !important;
    font-size: 14px;
}

.delete-confirmation-dialog .contextual-dialog-footer {
    padding: 16px 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.delete-confirmation-dialog .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.delete-confirmation-dialog .btn-secondary {
    background: #6c757d;
    color: white;
}

.delete-confirmation-dialog .btn-danger {
    background: #dc3545;
    color: white;
}

.delete-confirmation-dialog .btn:hover {
    opacity: 0.9;
}
</style>
`;

// Injecter le CSS
if (!document.getElementById('delete-dialog-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'delete-dialog-styles';
    styleElement.innerHTML = deleteDialogCSS;
    document.head.appendChild(styleElement);
}
</script>
<script>
/**
 * Script de correction pour éviter les boîtes de dialogue de suppression en double
 * À placer avant la fermeture de </body> dans index.php
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Initialisation de la correction des suppressions en double...');
    
    // Attendre que tous les scripts soient chargés
    setTimeout(function() {
        fixDeleteHandlers();
    }, 1000);
});

function fixDeleteHandlers() {
    console.log('🔄 Correction des gestionnaires de suppression...');
    
    // Trouver tous les boutons de suppression
    const deleteButtons = document.querySelectorAll('.btn-delete');
    console.log(`Trouvé ${deleteButtons.length} bouton(s) de suppression`);
    
    deleteButtons.forEach((button, index) => {
        console.log(`Traitement du bouton ${index + 1}...`);
        
        // Supprimer tous les gestionnaires d'événements existants en clonant le bouton
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        // Ajouter un seul gestionnaire d'événement propre
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Récupérer les informations de l'élément
            const item = this.closest('.filemanager-item');
            if (!item) {
                console.error('Élément parent non trouvé');
                return;
            }
            
            const itemId = item.dataset.id;
            const itemType = item.dataset.type;
            const itemName = item.querySelector('.filemanager-item-name span')?.textContent || 'cet élément';
            
            console.log('🗑️ Demande de suppression:', { itemId, itemType, itemName });
            
            // Vérifier qu'aucune boîte de dialogue n'est déjà ouverte
            const existingDialog = document.querySelector('.delete-confirmation-dialog.show');
            if (existingDialog) {
                console.log('⚠️ Une boîte de dialogue est déjà ouverte, suppression annulée');
                return;
            }
            
            // Créer et afficher la boîte de dialogue de confirmation
            showDeleteConfirmation(itemId, itemType, itemName);
        });
    });
    
    console.log('✅ Gestionnaires de suppression corrigés');
}

function showDeleteConfirmation(itemId, itemType, itemName) {
    console.log('💬 Affichage de la confirmation de suppression pour:', itemName);
    
    // Supprimer toute boîte de dialogue existante
    const existingDialogs = document.querySelectorAll('.delete-confirmation-dialog');
    existingDialogs.forEach(dialog => dialog.remove());
    
    // Créer la boîte de dialogue
    const dialog = document.createElement('div');
    dialog.className = 'contextual-dialog delete-confirmation-dialog show';
    dialog.innerHTML = `
        <div class="contextual-dialog-content">
            <div class="contextual-dialog-header">
                <h3>Confirmer la suppression</h3>
                <button class="contextual-dialog-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="contextual-dialog-body">
                <p>Êtes-vous sûr de vouloir supprimer "${itemName}" ?</p>
                <p class="text-warning">Cette action est irréversible.</p>
            </div>
            <div class="contextual-dialog-footer">
                <button type="button" class="btn btn-secondary cancel-delete">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-danger confirm-delete" 
                        data-id="${itemId}" data-type="${itemType}">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </div>
    `;
    
    // Ajouter la boîte de dialogue au body
    document.body.appendChild(dialog);
    
    // Gestionnaires pour fermer la boîte de dialogue
    const closeButtons = dialog.querySelectorAll('.contextual-dialog-close, .cancel-delete');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            closeDeleteDialog(dialog);
        });
    });
    
    // Gestionnaire pour confirmer la suppression
    const confirmButton = dialog.querySelector('.confirm-delete');
    confirmButton.addEventListener('click', function() {
        const id = this.dataset.id;
        const type = this.dataset.type;
        
        console.log('✅ Confirmation de suppression pour:', { id, type });
        
        // Fermer la boîte de dialogue immédiatement
        closeDeleteDialog(dialog);
        
        // Effectuer la suppression
        performDelete(id, type);
    });
    
    // Fermer en cliquant à côté
    dialog.addEventListener('click', function(e) {
        if (e.target === dialog) {
            closeDeleteDialog(dialog);
        }
    });
}

function closeDeleteDialog(dialog) {
    console.log('❌ Fermeture de la boîte de dialogue de suppression');
    
    if (dialog && dialog.parentNode) {
        dialog.classList.remove('show');
        setTimeout(() => {
            if (dialog.parentNode) {
                dialog.remove();
            }
        }, 300);
    }
}

function performDelete(itemId, itemType) {
    console.log('🗑️ Exécution de la suppression:', { itemId, itemType });
    
    // Afficher un message de chargement
    showToast('Suppression en cours...', 'info', false);
    
    // Préparer les données
    const formData = new FormData();
    formData.append('id', itemId);
    formData.append('type', itemType);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    // Effectuer la requête de suppression
    fetch('api/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Masquer le message de chargement
        hideLoadingMessage();
        
        if (data.success) {
            showToast('Élément supprimé avec succès', 'success');
            
            // Supprimer l'élément du DOM
            const item = document.querySelector(`[data-id="${itemId}"][data-type="${itemType}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    item.remove();
                    
                    // Vérifier s'il reste des éléments
                    const remainingItems = document.querySelectorAll('.filemanager-item');
                    if (remainingItems.length === 0) {
                        const grid = document.getElementById('filemanagerGrid');
                        if (grid) {
                            grid.innerHTML = `
                                <div class="filemanager-empty">
                                    <i class="fas fa-folder-open"></i>
                                    <p>Ce dossier est vide</p>
                                </div>
                            `;
                        }
                    }
                }, 300);
            }
        } else {
            showToast('Erreur lors de la suppression: ' + (data.error || 'Erreur inconnue'), 'error');
        }
    })
    .catch(error => {
        hideLoadingMessage();
        showToast('Erreur lors de la suppression: ' + error.message, 'error');
        console.error('Erreur de suppression:', error);
    });
}

// Fonction utilitaire pour masquer les messages de chargement
function hideLoadingMessage() {
    const loadingToasts = document.querySelectorAll('.toast.info');
    loadingToasts.forEach(toast => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    });
}
/**
 * Gestionnaire de double-clic corrigé avec gestion améliorée du chargement
 * À remplacer dans votre fichier index.php
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🖱️ Initialisation du gestionnaire de double-clic pour les fichiers...');
    
    // Attendre que tous les éléments soient chargés
    setTimeout(function() {
        initializeDoubleClickHandlers();
    }, 500);
});

function initializeDoubleClickHandlers() {
    // Sélectionner tous les éléments de fichier
    const fileItems = document.querySelectorAll('.filemanager-item.file');
    
    console.log(`📁 Trouvé ${fileItems.length} fichier(s) pour le double-clic`);
    
    fileItems.forEach((fileItem, index) => {
        const fileName = fileItem.querySelector('.filemanager-item-name span')?.textContent || '';
        const fileId = fileItem.dataset.id;
        
        console.log(`📄 Configuration du fichier ${index + 1}: ${fileName} (ID: ${fileId})`);
        
        // Supprimer les anciens gestionnaires en clonant l'élément
        const newFileItem = fileItem.cloneNode(true);
        fileItem.parentNode.replaceChild(newFileItem, fileItem);
        
        // Ajouter le gestionnaire de double-clic
        newFileItem.addEventListener('dblclick', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const fileName = this.querySelector('.filemanager-item-name span')?.textContent || '';
            const fileId = this.dataset.id;
            
            console.log(`🖱️ Double-clic détecté sur: ${fileName} (ID: ${fileId})`);
            
            // Ouvrir la prévisualisation selon le type de fichier
            openFilePreview(fileId, fileName);
        });
        
        // Réattacher les gestionnaires des boutons d'action
        reattachActionButtons(newFileItem);
    });
    
    console.log('✅ Gestionnaires de double-clic initialisés avec succès');
}

// Variable globale pour garder une référence au toast de chargement
let currentLoadingToast = null;

function openFilePreview(fileId, fileName) {
    console.log('🔍 Ouverture de la prévisualisation pour:', fileName);
    
    // Fermer tout toast de chargement précédent
    hideAllLoadingMessages();
    
    // Afficher un nouveau message de chargement et garder la référence
    currentLoadingToast = showToast('Chargement de la prévisualisation...', 'info', false);
    
    // Construire l'URL de prévisualisation (GET, sans CSRF)
    const previewUrl = `api/preview.php?id=${fileId}&type=file`;
    
    // Récupérer les éléments de la modal
    const modal = document.getElementById('previewModal');
    const modalTitle = document.getElementById('previewModalTitle');
    const modalBody = document.getElementById('previewModalBody');
    const downloadLink = document.getElementById('downloadFile');
    
    if (!modal || !modalTitle || !modalBody) {
        console.error('❌ Éléments de modal non trouvés');
        hideAllLoadingMessages();
        showToast('Erreur: Interface de prévisualisation non disponible', 'error');
        return;
    }
    
    // Configurer la modal
    modalTitle.textContent = `Prévisualisation - ${fileName}`;
    
    // Déterminer le type de fichier
    const fileExtension = fileName.split('.').pop().toLowerCase();
    
    // Configurer le lien de téléchargement
    if (downloadLink) {
        downloadLink.href = `api/download.php?id=${fileId}&type=file`;
        downloadLink.download = fileName;
    }
    
    // Créer le contenu selon le type de fichier
    switch (fileExtension) {
        case 'pdf':
            createPdfPreview(modalBody, previewUrl, fileName);
            break;
            
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
            createImagePreview(modalBody, previewUrl, fileName);
            break;
            
        case 'txt':
        case 'md':
        case 'log':
            createTextPreview(modalBody, previewUrl, fileName);
            break;
            
        case 'html':
        case 'htm':
            createHtmlPreview(modalBody, previewUrl, fileName);
            break;
            
        default:
            createGenericPreview(modalBody, fileId, fileName, fileExtension);
            break;
    }
    
    // Afficher la modal
    modal.classList.add('show');
    
    console.log('📋 Modal ouverte pour:', fileName);
}

function createPdfPreview(modalBody, previewUrl, fileName) {
    modalBody.innerHTML = `
        <div class="pdf-preview-container" style="width: 100%; height: 70vh; position: relative; background: #f5f5f5;">
            <iframe 
                src="${previewUrl}" 
                style="width: 100%; height: 100%; border: none; border-radius: 4px; background: white;"
                title="Prévisualisation PDF - ${fileName}"
                onload="window.hideAllLoadingMessages(); console.log('✅ PDF chargé: ${fileName}')"
                onerror="window.showPreviewError('PDF', '${fileName}')">
                Votre navigateur ne supporte pas l'affichage des PDF.
                <br><a href="${previewUrl}" target="_blank">Ouvrir dans un nouvel onglet</a>
            </iframe>
            <div class="loading-overlay" id="loadingOverlay" style="
                position: absolute; 
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                border-radius: 4px;
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #007bff; margin-bottom: 15px;"></i>
                <p style="margin: 0; color: #666; font-size: 16px;">Chargement du PDF...</p>
            </div>
        </div>
    `;
    
    // Timeout de sécurité pour masquer le chargement après 10 secondes
    setTimeout(() => {
        hideAllLoadingMessages();
        hideModalLoadingOverlay();
    }, 10000);
}

function createImagePreview(modalBody, previewUrl, fileName) {
    modalBody.innerHTML = `
        <div class="image-preview-container" style="
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 70vh; 
            background: #f8f9fa;
            position: relative;
        ">
            <img 
                src="${previewUrl}" 
                alt="${fileName}"
                style="
                    max-width: 100%; 
                    max-height: 100%; 
                    border-radius: 4px; 
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    object-fit: contain;
                "
                onload="window.hideAllLoadingMessages(); window.hideModalLoadingOverlay(); console.log('✅ Image chargée: ${fileName}')"
                onerror="window.showPreviewError('Image', '${fileName}')"
            />
            <div class="loading-overlay" id="loadingOverlay" style="
                position: absolute; 
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.9);
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff; margin-bottom: 10px;"></i>
                <p style="margin: 0; color: #666;">Chargement de l'image...</p>
            </div>
        </div>
    `;
    
    // Timeout de sécurité
    setTimeout(() => {
        hideAllLoadingMessages();
        hideModalLoadingOverlay();
    }, 8000);
}

function createTextPreview(modalBody, previewUrl, fileName) {
    modalBody.innerHTML = `
        <div class="text-preview-container" style="height: 70vh; position: relative;">
            <div class="loading-overlay" id="loadingOverlay" style="
                position: absolute; 
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff; margin-bottom: 10px;"></i>
                <p style="margin: 0; color: #666;">Chargement du fichier...</p>
            </div>
        </div>
    `;
    
    // Charger le contenu texte via fetch
    fetch(previewUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.text();
        })
        .then(content => {
            modalBody.innerHTML = `
                <div class="text-preview-container" style="padding: 20px; height: 70vh; overflow: auto;">
                    <pre style="
                        white-space: pre-wrap; 
                        font-family: 'Consolas', 'Monaco', 'Courier New', monospace; 
                        background: #f8f9fa; 
                        padding: 20px; 
                        border-radius: 6px; 
                        margin: 0;
                        font-size: 14px;
                        line-height: 1.5;
                        color: #333;
                        border: 1px solid #e9ecef;
                    ">${escapeHtml(content)}</pre>
                </div>
            `;
            hideAllLoadingMessages();
            console.log('✅ Fichier texte chargé:', fileName);
        })
        .catch(error => {
            console.error('❌ Erreur chargement texte:', error);
            showPreviewError('Fichier texte', fileName);
        });
}

function createHtmlPreview(modalBody, previewUrl, fileName) {
    modalBody.innerHTML = `
        <div class="html-preview-container" style="width: 100%; height: 70vh; position: relative;">
            <iframe 
                src="${previewUrl}" 
                style="width: 100%; height: 100%; border: 1px solid #ddd; border-radius: 4px;"
                title="Prévisualisation HTML - ${fileName}"
                onload="window.hideAllLoadingMessages(); window.hideModalLoadingOverlay(); console.log('✅ HTML chargé: ${fileName}')"
                onerror="window.showPreviewError('HTML', '${fileName}')">
            </iframe>
            <div class="loading-overlay" id="loadingOverlay" style="
                position: absolute; 
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.9);
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff; margin-bottom: 10px;"></i>
                <p style="margin: 0; color: #666;">Chargement du HTML...</p>
            </div>
        </div>
    `;
    
    // Timeout de sécurité
    setTimeout(() => {
        hideAllLoadingMessages();
        hideModalLoadingOverlay();
    }, 8000);
}

function createGenericPreview(modalBody, fileId, fileName, fileExtension) {
    hideAllLoadingMessages();
    
    modalBody.innerHTML = `
        <div class="generic-preview-container" style="
            display: flex; 
            flex-direction: column;
            align-items: center; 
            justify-content: center; 
            height: 70vh; 
            text-align: center;
            color: #666;
        ">
            <i class="fas fa-file-alt" style="font-size: 64px; margin-bottom: 20px; color: #ccc;"></i>
            <h3 style="margin: 0 0 10px 0; color: #333;">Prévisualisation non disponible</h3>
            <p style="margin: 0 0 20px 0;">
                Les fichiers de type <strong>${fileExtension.toUpperCase()}</strong> ne peuvent pas être prévisualisés.
            </p>
            <p style="margin: 0 0 30px 0; font-size: 14px;">
                Nom du fichier: <strong>${fileName}</strong>
            </p>
            <div style="display: flex; gap: 15px;">
                <a href="api/download.php?id=${fileId}&type=file" 
                   class="btn btn-primary" 
                   style="text-decoration: none; padding: 10px 20px; background: #007bff; color: white; border-radius: 4px;"
                   download="${fileName}">
                    <i class="fas fa-download"></i> Télécharger
                </a>
                <button onclick="window.openInNewTab('api/preview.php?id=${fileId}&type=file')" 
                        class="btn btn-secondary"
                        style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-external-link-alt"></i> Ouvrir dans un nouvel onglet
                </button>
            </div>
        </div>
    `;
}

// Fonction améliorée pour masquer tous les messages de chargement
function hideAllLoadingMessages() {
    console.log('🧹 Masquage de tous les messages de chargement...');
    
    // Fermer le toast de chargement actuel
    if (currentLoadingToast) {
        currentLoadingToast.classList.remove('show');
        setTimeout(() => {
            if (currentLoadingToast.parentNode) {
                currentLoadingToast.remove();
            }
        }, 300);
        currentLoadingToast = null;
    }
    
    // Fermer tous les toasts qui contiennent "chargement" ou "Chargement"
    const loadingToasts = document.querySelectorAll('.toast');
    loadingToasts.forEach(toast => {
        const text = toast.textContent.toLowerCase();
        if (text.includes('chargement') || text.includes('loading')) {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    });
    
    // Masquer les overlays de chargement dans les modals
    hideModalLoadingOverlay();
}

function hideModalLoadingOverlay() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.opacity = '0';
        setTimeout(() => {
            if (loadingOverlay.parentNode) {
                loadingOverlay.remove();
            }
        }, 300);
    }
}

function showPreviewError(fileType, fileName) {
    hideAllLoadingMessages();
    
    const modalBody = document.getElementById('previewModalBody');
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="error-container" style="
                display: flex; 
                flex-direction: column;
                align-items: center; 
                justify-content: center; 
                height: 70vh; 
                text-align: center;
                color: #dc3545;
            ">
                <i class="fas fa-exclamation-triangle" style="font-size: 64px; margin-bottom: 20px;"></i>
                <h3 style="margin: 0 0 10px 0;">Erreur de chargement</h3>
                <p style="margin: 0 0 10px 0;">
                    Impossible de charger la prévisualisation du ${fileType}.
                </p>
                <p style="margin: 0 0 30px 0; font-size: 14px; color: #666;">
                    Fichier: <strong>${fileName}</strong>
                </p>
                <button onclick="document.getElementById('downloadFile').click()" 
                        class="btn btn-primary"
                        style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-download"></i> Télécharger le fichier
                </button>
            </div>
        `;
    }
    showToast(`Erreur lors du chargement du ${fileType}`, 'error');
}

function reattachActionButtons(fileItem) {
    // Réattacher les gestionnaires des boutons de renommage
    const renameBtn = fileItem.querySelector('.btn-rename');
    if (renameBtn) {
        renameBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('🖊️ Bouton renommer cliqué');
            // Votre logique de renommage existante
        });
    }
    
    // Réattacher les gestionnaires des boutons de suppression
    const deleteBtn = fileItem.querySelector('.btn-delete');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('🗑️ Bouton supprimer cliqué');
            // Votre logique de suppression existante
        });
    }
    
    // Réattacher les gestionnaires des boutons d'analyse
    const analyzeBtn = fileItem.querySelector('.btn-analyze');
    if (analyzeBtn) {
        analyzeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('🔍 Bouton analyser cliqué');
            // Votre logique d'analyse existante
        });
    }
}

// Fonctions utilitaires
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openInNewTab(url) {
    window.open(url, '_blank');
}

// Exposer les fonctions importantes à window pour qu'elles soient accessibles depuis les iframes
window.hideAllLoadingMessages = hideAllLoadingMessages;
window.hideModalLoadingOverlay = hideModalLoadingOverlay;
window.showPreviewError = showPreviewError;
window.openInNewTab = openInNewTab;

// Gestionnaire pour fermer les messages de chargement quand on ferme la modal
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter des gestionnaires aux boutons de fermeture de modal
    const modalCloseButtons = document.querySelectorAll('#closePreview, .modal-close, .contextual-dialog-close');
    modalCloseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            hideAllLoadingMessages();
        });
    });
    
    // Fermer les messages de chargement si on clique à côté de la modal
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideAllLoadingMessages();
            }
        });
    }
});
/**
 * Fonctionnalité de glisser-déposer CORRIGÉE (sans scintillement)
 * À remplacer dans votre fichier index.php
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Initialisation du glisser-déposer (version stable)...');
    initializeDragAndDropStable();
});

function initializeDragAndDropStable() {
    // Variables globales pour gérer l'état du drag
    let isDragging = false;
    let dragCounter = 0;
    let dragTimer = null;

    // Créer l'overlay de drop
    createDropOverlay();

    console.log('📂 Configuration du glisser-déposer sur document');

    // Empêcher le comportement par défaut pour toute la page
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        document.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
        
        // Aussi sur window pour être sûr
        window.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    // Gestionnaire pour dragenter sur document
    document.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Vérifier si ce sont vraiment des fichiers
        if (e.dataTransfer && e.dataTransfer.types && e.dataTransfer.types.includes('Files')) {
            dragCounter++;
            
            console.log('📥 Drag enter - Counter:', dragCounter);
            
            if (!isDragging) {
                isDragging = true;
                console.log('🎯 Activation de l\'overlay de drop');
                showDropOverlay();
            }
            
            // Annuler tout timer de masquage en cours
            if (dragTimer) {
                clearTimeout(dragTimer);
                dragTimer = null;
            }
        }
    });

    // Gestionnaire pour dragover sur document
    document.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'copy';
        }
        
        // Maintenir l'overlay visible
        if (!isDragging) {
            isDragging = true;
            showDropOverlay();
        }
    });

    // Gestionnaire pour dragleave sur document
    document.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        dragCounter--;
        console.log('📤 Drag leave - Counter:', dragCounter);
        
        // Si on sort vraiment de la fenêtre (coordonnées négatives ou hors limites)
        if (e.clientX <= 0 || e.clientY <= 0 || 
            e.clientX >= window.innerWidth || e.clientY >= window.innerHeight) {
            
            console.log('🚪 Sortie de la fenêtre détectée');
            resetDragState();
        } else {
            // Utiliser un timer court pour éviter les faux positifs
            if (dragTimer) {
                clearTimeout(dragTimer);
            }
            
            dragTimer = setTimeout(() => {
                if (dragCounter <= 0) {
                    console.log('⏰ Timer expiré - Masquage de l\'overlay');
                    resetDragState();
                }
            }, 100); // 100ms de délai
        }
    });

    // Gestionnaire pour drop sur document
    document.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🎯 Drop détecté sur document');
        
        resetDragState();
        
        const files = Array.from(e.dataTransfer.files);
        
        if (files.length === 0) {
            console.warn('⚠️ Aucun fichier détecté dans le drop');
            showToast('Aucun fichier à télécharger', 'warning');
            return;
        }
        
        console.log(`📁 ${files.length} fichier(s) déposé(s):`, files.map(f => f.name));
        handleDroppedFiles(files);
    });

    // Fonction pour réinitialiser l'état du drag
    function resetDragState() {
        isDragging = false;
        dragCounter = 0;
        
        if (dragTimer) {
            clearTimeout(dragTimer);
            dragTimer = null;
        }
        
        hideDropOverlay();
    }

    // Gestionnaires pour les événements de fenêtre
    window.addEventListener('blur', resetDragState);
    window.addEventListener('focus', resetDragState);
    
    // Reset si on appuie sur Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isDragging) {
            console.log('⌨️ Escape pressé - Reset du drag');
            resetDragState();
        }
    });

    console.log('✅ Glisser-déposer stable initialisé');
}

function createDropOverlay() {
    // Supprimer l'overlay existant s'il y en a un
    const existingOverlay = document.getElementById('dragDropOverlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }

    // Créer le nouvel overlay
    const overlay = document.createElement('div');
    overlay.id = 'dragDropOverlay';
    overlay.innerHTML = `
        <div class="drop-zone-content">
            <div class="drop-zone-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="drop-zone-text">
                <h3>Déposez vos fichiers ici</h3>
                <p>Relâchez pour télécharger les fichiers</p>
                <div class="supported-formats">
                    <small>PDF, Images, Documents, etc.</small>
                </div>
            </div>
        </div>
    `;

    // Styles pour l'overlay
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 123, 255, 0.1);
        backdrop-filter: blur(3px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        border: 4px dashed #007bff;
        box-sizing: border-box;
        pointer-events: none;
    `;

    // Ajouter les styles si pas déjà présents
    if (!document.getElementById('dropOverlayStyles')) {
        const style = document.createElement('style');
        style.id = 'dropOverlayStyles';
        style.textContent = `
            .drop-zone-content {
                text-align: center;
                padding: 40px;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                border: 3px dashed #007bff;
                max-width: 450px;
                margin: 20px;
                backdrop-filter: blur(10px);
                transform: scale(0.8);
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            }
            
            #dragDropOverlay.show .drop-zone-content {
                transform: scale(1);
            }
            
            .drop-zone-icon {
                font-size: 72px;
                color: #007bff;
                margin-bottom: 20px;
                animation: float 3s ease-in-out infinite;
            }
            
            .drop-zone-text h3 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 28px;
                font-weight: 700;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .drop-zone-text p {
                margin: 0 0 15px 0;
                color: #666;
                font-size: 16px;
                font-weight: 500;
            }
            
            .supported-formats {
                margin-top: 15px;
                padding: 8px 16px;
                background: rgba(0, 123, 255, 0.1);
                border-radius: 20px;
                display: inline-block;
            }
            
            .supported-formats small {
                color: #007bff;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            @keyframes float {
                0%, 100% {
                    transform: translateY(0px);
                }
                50% {
                    transform: translateY(-15px);
                }
            }
            
            /* Animation d'entrée */
            #dragDropOverlay {
                opacity: 0;
                transition: opacity 0.2s ease;
            }
            
            #dragDropOverlay.show {
                opacity: 1;
            }
            
            /* Animation de la bordure */
            #dragDropOverlay.show {
                animation: borderPulse 2s infinite;
            }
            
            @keyframes borderPulse {
                0%, 100% {
                    border-color: #007bff;
                }
                50% {
                    border-color: #0056b3;
                }
            }
        `;

        document.head.appendChild(style);
    }

    document.body.appendChild(overlay);
    return overlay;
}

function showDropOverlay() {
    const overlay = document.getElementById('dragDropOverlay');
    if (overlay && overlay.style.display !== 'flex') {
        console.log('👁️ Affichage de l\'overlay');
        overlay.style.display = 'flex';
        
        // Forcer le reflow avant d'ajouter la classe
        overlay.offsetHeight;
        
        setTimeout(() => {
            overlay.classList.add('show');
        }, 10);
    }
}

function hideDropOverlay() {
    const overlay = document.getElementById('dragDropOverlay');
    if (overlay && overlay.style.display !== 'none') {
        console.log('🙈 Masquage de l\'overlay');
        
        overlay.classList.remove('show');
        
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 200);
    }
}

function handleDroppedFiles(files) {
    console.log(`🎯 Traitement de ${files.length} fichier(s) déposé(s)`);
    
    // Vérifications de base
    if (files.length === 0) {
        showToast('Aucun fichier à télécharger', 'warning');
        return;
    }
    
    // Filtrer les fichiers vides ou corrompus
    const validFiles = files.filter(file => file.size > 0);
    if (validFiles.length !== files.length) {
        const emptyCount = files.length - validFiles.length;
        showToast(`${emptyCount} fichier(s) vide(s) ignoré(s)`, 'warning');
    }
    
    if (validFiles.length === 0) {
        showToast('Aucun fichier valide à télécharger', 'error');
        return;
    }
    
    // Vérifier la taille des fichiers (limite à 50MB par fichier)
    const maxSize = 50 * 1024 * 1024; // 50MB
    const oversizedFiles = validFiles.filter(file => file.size > maxSize);
    
    if (oversizedFiles.length > 0) {
        const fileNames = oversizedFiles.map(f => f.name).join(', ');
        showToast(`Fichier(s) trop volumineux (max 50MB): ${fileNames}`, 'error');
        return;
    }
    
    // Vérifier les types de fichiers (optionnel)
    const forbiddenExtensions = ['exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js'];
    const dangerousFiles = validFiles.filter(file => {
        const extension = file.name.split('.').pop().toLowerCase();
        return forbiddenExtensions.includes(extension);
    });
    
    if (dangerousFiles.length > 0) {
        const fileNames = dangerousFiles.map(f => f.name).join(', ');
        showToast(`Type de fichier non autorisé: ${fileNames}`, 'error');
        return;
    }
    
    // Afficher une confirmation pour beaucoup de fichiers
    if (validFiles.length > 10) {
        if (!confirm(`Vous allez télécharger ${validFiles.length} fichiers. Continuer ?`)) {
            return;
        }
    }
    
    // Afficher le toast de progression
    const progressToast = showProgressToast(validFiles.length);
    
    // Uploader les fichiers
    uploadMultipleFiles(validFiles, progressToast);
}

function showProgressToast(totalFiles) {
    // Supprimer tout toast de progression existant
    const existingToast = document.getElementById('uploadProgressToast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'toast toast-progress';
    toast.id = 'uploadProgressToast';
    
    toast.style.cssText = `
        background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
        color: #1565c0;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        border: 1px solid rgba(33, 150, 243, 0.2);
        border-left: 4px solid #2196f3;
        min-width: 350px;
        max-width: 450px;
        backdrop-filter: blur(10px);
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
            <div style="
                width: 40px; 
                height: 40px; 
                background: #2196f3; 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center;
                animation: pulse 2s infinite;
            ">
                <i class="fas fa-cloud-upload-alt" style="font-size: 18px; color: white;"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">
                    Téléchargement en cours...
                </div>
                <div style="font-size: 14px; opacity: 0.8;">
                    <span id="progressText">0 / ${totalFiles} fichier(s) téléchargé(s)</span>
                </div>
            </div>
        </div>
        <div style="margin-bottom: 12px;">
            <div style="
                background: rgba(33, 150, 243, 0.2); 
                border-radius: 20px; 
                height: 8px; 
                overflow: hidden;
                position: relative;
            ">
                <div id="progressBar" style="
                    background: linear-gradient(90deg, #2196f3, #21cbf3); 
                    height: 100%; 
                    width: 0%; 
                    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    border-radius: 20px;
                    position: relative;
                "></div>
            </div>
        </div>
        <div id="progressDetails" style="
            font-size: 13px; 
            opacity: 0.7; 
            min-height: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        "></div>
    `;
    
    // Ajouter à la zone de toasts
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100000;
            max-width: 500px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    return toast;
}

function updateProgressToast(toast, completed, total, currentFile = '', success = 0, failed = 0) {
    const progressText = toast.querySelector('#progressText');
    const progressBar = toast.querySelector('#progressBar');
    const progressDetails = toast.querySelector('#progressDetails');
    
    if (progressText) {
        progressText.textContent = `${completed} / ${total} fichier(s) traité(s)`;
    }
    
    if (progressBar) {
        const percentage = (completed / total) * 100;
        progressBar.style.width = `${percentage}%`;
    }
    
    if (progressDetails) {
        let details = '';
        if (currentFile) {
            details += `<i class="fas fa-file" style="color: #2196f3;"></i> ${currentFile}`;
        }
        if (completed > 0) {
            details += `<br><i class="fas fa-check-circle" style="color: #4caf50;"></i> ${success} réussi(s)`;
            if (failed > 0) {
                details += ` <i class="fas fa-exclamation-circle" style="color: #f44336;"></i> ${failed} échoué(s)`;
            }
        }
        progressDetails.innerHTML = details;
    }
}

function uploadMultipleFiles(files, progressToast) {
    const totalFiles = files.length;
    let completed = 0;
    let successful = 0;
    let failed = 0;
    const results = [];
    
    console.log(`🚀 Début de l'upload de ${totalFiles} fichier(s)`);
    
    // Fonction pour uploader un fichier
    const uploadFile = (file, index) => {
        return new Promise((resolve) => {
            console.log(`📤 Upload du fichier ${index + 1}/${totalFiles}: ${file.name}`);
            
            // Mettre à jour le toast de progression
            updateProgressToast(progressToast, completed, totalFiles, file.name, successful, failed);
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder_id', document.getElementById('currentFolderId').value);
            formData.append('csrf_token', document.getElementById('csrfToken').value);
            
            fetch('api/upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successful++;
                    results.push({ file: file.name, success: true });
                    console.log(`✅ ${file.name} téléchargé avec succès`);
                } else {
                    failed++;
                    results.push({ 
                        file: file.name, 
                        success: false, 
                        error: data.error || 'Erreur inconnue' 
                    });
                    console.log(`❌ Échec du téléchargement de ${file.name}:`, data.error);
                }
            })
            .catch(error => {
                failed++;
                results.push({ 
                    file: file.name, 
                    success: false, 
                    error: error.message 
                });
                console.error(`❌ Erreur lors du téléchargement de ${file.name}:`, error);
            })
            .finally(() => {
                completed++;
                
                // Mettre à jour le toast de progression
                updateProgressToast(progressToast, completed, totalFiles, '', successful, failed);
                
                resolve();
            });
        });
    };
    
    // Traiter tous les fichiers en parallèle (max 3 à la fois pour éviter la surcharge)
    const processInBatches = async (items, batchSize = 3) => {
        for (let i = 0; i < items.length; i += batchSize) {
            const batch = items.slice(i, i + batchSize);
            await Promise.all(batch.map((file, index) => uploadFile(file, i + index)));
        }
    };
    
    processInBatches(files).then(() => {
        console.log('🏁 Tous les uploads terminés');
        
        // Mettre à jour le toast final
        const progressDetails = progressToast.querySelector('#progressDetails');
        if (progressDetails) {
            progressDetails.innerHTML = `
                <i class="fas fa-check-circle" style="color: #4caf50;"></i> 
                Terminé ! ${successful} réussi(s), ${failed} échec(s)
            `;
        }
        
        // Masquer le toast de progression après un délai
        setTimeout(() => {
            if (progressToast.parentNode) {
                progressToast.style.opacity = '0';
                progressToast.style.transform = 'translateX(100%)';
                setTimeout(() => progressToast.remove(), 300);
            }
        }, 3000);
        
        // Afficher le résumé
        if (successful > 0 && failed === 0) {
            showToast(`🎉 ${successful} fichier(s) téléchargé(s) avec succès`, 'success');
        } else if (successful > 0 && failed > 0) {
            showToast(`⚠️ ${successful} réussi(s), ${failed} échec(s)`, 'warning');
            
            // Afficher les détails des échecs
            const failedFiles = results.filter(r => !r.success);
            if (failedFiles.length <= 3) {
                failedFiles.forEach(result => {
                    showToast(`❌ ${result.file}: ${result.error}`, 'error');
                });
            } else {
                showToast(`❌ ${failed} fichier(s) ont échoué (voir la console pour les détails)`, 'error');
                console.table(failedFiles);
            }
        } else {
            showToast(`❌ Échec du téléchargement de tous les fichiers`, 'error');
        }
        
        // Recharger la page si au moins un fichier a été téléchargé
        if (successful > 0) {
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    });
}

// Fonction showToast améliorée
function showToast(message, type = 'info', autoClose = true) {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100000;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const colors = {
        success: { bg: '#d4edda', color: '#155724', border: '#c3e6cb', icon: 'fa-check-circle' },
        error: { bg: '#f8d7da', color: '#721c24', border: '#f5c6cb', icon: 'fa-exclamation-circle' },
        warning: { bg: '#fff3cd', color: '#856404', border: '#ffeaa7', icon: 'fa-exclamation-triangle' },
        info: { bg: '#d1ecf1', color: '#0c5460', border: '#bee5eb', icon: 'fa-info-circle' }
    };
    
    const colorSet = colors[type] || colors.info;
    
    toast.style.cssText = `
        background: ${colorSet.bg};
        color: ${colorSet.color};
        border: 1px solid ${colorSet.border};
        padding: 12px 16px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 380px;
        word-wrap: break-word;
        backdrop-filter: blur(10px);
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <i class="fas ${colorSet.icon}" style="margin-top: 2px; flex-shrink: 0; font-size: 16px;"></i>
            <span style="flex: 1; line-height: 1.4;">${message}</span>
            ${autoClose ? '<button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; margin-left: 8px; cursor: pointer; flex-shrink: 0; opacity: 0.7; padding: 2px;"><i class="fas fa-times"></i></button>' : ''}
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    if (autoClose) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 5000);
    }
    
    return toast;
}

console.log('✅ Script de glisser-déposer stable initialisé');
</script>

<style>
    /* Styles CSS pour le glisser-déposer */
/* À ajouter dans la section <style> de votre index.php */

/* Animation de pulsation pour indiquer les zones de drop */
.drag-over {
    background: rgba(0, 123, 255, 0.1) !important;
    border: 2px dashed #007bff !important;
    border-radius: 8px;
    animation: pulse-border 1s infinite;
}

@keyframes pulse-border {
    0% {
        border-color: #007bff;
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4);
    }
    50% {
        border-color: #0056b3;
        box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
    }
    100% {
        border-color: #007bff;
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
    }
}

/* Overlay de glisser-déposer */
#dragDropOverlay {
    transition: all 0.3s ease;
    opacity: 0;
}

#dragDropOverlay.show {
    opacity: 1;
}

/* Zone de drop principale */
.drop-zone-content {
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

#dragDropOverlay.show .drop-zone-content {
    transform: scale(1);
}

/* Styles pour les toasts de progression */
.toast-progress {
    position: relative;
    overflow: hidden;
}

.toast-progress::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}

/* Indicateur visuel pour les zones de drop potentielles */
.filemanager-grid.drag-active {
    position: relative;
}

.filemanager-grid.drag-active::after {
    content: "📁 Déposez vos fichiers ici";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 123, 255, 0.9);
    color: white;
    padding: 20px 30px;
    border-radius: 10px;
    font-size: 18px;
    font-weight: 600;
    text-align: center;
    z-index: 1000;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    animation: float 2s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translate(-50%, -50%) translateY(0px);
    }
    50% {
        transform: translate(-50%, -50%) translateY(-10px);
    }
}

/* Styles pour les différents types de toasts */
.toast {
    position: relative;
    backdrop-filter: blur(10px);
}

.toast-success {
    border-left: 4px solid #28a745;
}

.toast-error {
    border-left: 4px solid #dc3545;
}

.toast-warning {
    border-left: 4px solid #ffc107;
}

.toast-info {
    border-left: 4px solid #17a2b8;
}

/* Animation pour les toasts */
.toast {
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive design pour mobile */
@media (max-width: 768px) {
    .drop-zone-content {
        padding: 20px;
        margin: 10px;
    }
    
    .drop-zone-icon {
        font-size: 48px !important;
        margin-bottom: 15px !important;
    }
    
    .drop-zone-text h3 {
        font-size: 20px !important;
    }
    
    .drop-zone-text p {
        font-size: 14px !important;
    }
    
    .toast {
        max-width: 280px;
        margin-right: 10px;
    }
    
    #toastContainer {
        right: 10px !important;
        top: 10px !important;
    }
}

/* Styles pour les états de hover sur la zone de fichiers */
.filemanager-content {
    transition: all 0.3s ease;
}

.filemanager-content.drag-hover {
    background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(0, 123, 255, 0.1));
    border-radius: 12px;
    transform: scale(1.02);
}

/* Amélioration visuelle pour les zones vides */
.filemanager-empty.drag-ready {
    border: 2px dashed #007bff;
    background: rgba(0, 123, 255, 0.05);
    color: #007bff;
    transition: all 0.3s ease;
}

.filemanager-empty.drag-ready i {
    animation: bounce 2s infinite;
}

/* Curseur personnalisé pendant le drag */
.dragging {
    cursor: copy !important;
}

.dragging * {
    cursor: copy !important;
}

/* Styles pour la barre de progression */
.progress-bar-smooth {
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Effet de brillance pour les éléments en cours de chargement */
.uploading {
    position: relative;
    overflow: hidden;
}

.uploading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    animation: shine 1.5s infinite;
}

@keyframes shine {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}
.delete-confirmation-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.delete-confirmation-dialog.show {
    opacity: 1;
}

.delete-confirmation-dialog .contextual-dialog-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-width: 400px;
    width: 90%;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.delete-confirmation-dialog.show .contextual-dialog-content {
    transform: scale(1);
}

.delete-confirmation-dialog .contextual-dialog-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.delete-confirmation-dialog .contextual-dialog-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.delete-confirmation-dialog .contextual-dialog-close {
    background: none;
    border: none;
    font-size: 16px;
    cursor: pointer;
    color: #666;
    padding: 4px;
}

.delete-confirmation-dialog .contextual-dialog-body {
    padding: 20px;
}

.delete-confirmation-dialog .contextual-dialog-body p {
    margin: 0 0 10px 0;
    color: #333;
}

.delete-confirmation-dialog .text-warning {
    color: #856404 !important;
    font-size: 14px;
}

.delete-confirmation-dialog .contextual-dialog-footer {
    padding: 16px 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.delete-confirmation-dialog .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.delete-confirmation-dialog .btn-secondary {
    background: #6c757d;
    color: white;
}

.delete-confirmation-dialog .btn-danger {
    background: #dc3545;
    color: white;
}

.delete-confirmation-dialog .btn:hover {
    opacity: 0.9;
}
</style>
</body>

</html>