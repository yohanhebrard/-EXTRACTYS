/**
 * FileManager Operations - Opérations sur les fichiers et dossiers
 */

class FileManagerOperations {
    constructor(core) {
        this.core = core;
    }

    /**
     * Renomme un élément (fichier ou dossier) en utilisant la même approche
     * que le renommage d'urgence qui fonctionne
     */
    async renameItem(id, type, newName) {
        console.log("Début de renameItem", { id, type, newName });

        // Validation de base
        if (!newName || !newName.trim()) {
            this.core.showToast('Le nom ne peut pas être vide', 'error');
            return false;
        }

        // Utiliser une approche asynchrone avec fetch
        const formData = new FormData();
        formData.append('id', id);
        formData.append('new_name', newName);
        formData.append('type', type);
        formData.append('csrf_token', this.core.config.csrfToken);

        try {
            // Afficher un toast de chargement
            this.core.showToast(`Renommage en cours...`, 'info', false);

            const response = await fetch(this.core.config.apiEndpoints.rename, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.core.showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} renommé avec succès`, 'success');

                // Mettre à jour l'interface sans recharger la page
                const item = document.querySelector(`.filemanager-item[data-id="${id}"][data-type="${type}"]`);
                if (item) {
                    const nameElement = item.querySelector('.filemanager-item-name span');
                    nameElement.textContent = data.new_name || newName;

                    // Animation de succès
                    nameElement.style.color = 'var(--fm-success)';
                    setTimeout(() => {
                        nameElement.style.transition = 'color 1s ease';
                        nameElement.style.color = '';
                    }, 1500);
                }

                return true;
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue lors du renommage', 'error');
                return false;
            }
        } catch (error) {
            console.error('Erreur lors du renommage:', error);
            this.core.showToast('Erreur: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Supprime un élément
     */
    async deleteItem(id, type) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('type', type);
        formData.append('csrf_token', this.core.config.csrfToken);

        // Animation
        const item = document.querySelector(`.filemanager-item[data-id="${id}"][data-type="${type}"]`);
        if (item) {
            item.classList.add('deleting');
            setTimeout(() => {
                item.classList.add('fade-out');
            }, 300);
        }

        try {
            const response = await fetch(this.core.config.apiEndpoints.delete, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.core.showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} supprimé avec succès`, 'success');

                if (item) {
                    setTimeout(() => {
                        item.remove();

                        // Si la grille est vide, afficher le message
                        const grid = this.core.elements.grid;
                        if (grid && grid.children.length === 0) {
                            grid.innerHTML = `
                            <div class="filemanager-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>Ce dossier est vide</p>
                            </div>`;
                        }
                    }, 500);
                }

                return true;
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue lors de la suppression', 'error');

                // Annuler l'animation
                if (item) {
                    item.classList.remove('fade-out', 'deleting');
                }

                return false;
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.core.showToast('Une erreur est survenue lors de la suppression', 'error');

            // Annuler l'animation
            if (item) {
                item.classList.remove('fade-out', 'deleting');
            }

            return false;
        }
    }

    /**
     * Crée un nouveau dossier
     */
    async createFolder(folderName) {
        const formData = new FormData();
        formData.append('name', folderName);
        formData.append('parent_id', this.core.config.currentFolderId);
        formData.append('type', 'folder');
        formData.append('csrf_token', this.core.config.csrfToken);

        // Indicateur de chargement
        const submitBtn = document.getElementById('submitNewFolder');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(this.core.config.apiEndpoints.create, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                document.getElementById('newFolderDialog').classList.remove('show');
                this.core.showToast(`Le dossier "${folderName}" a été créé`, 'success');

                // Rafraîchir la page pour afficher le nouveau dossier
                setTimeout(() => {
                    window.location.reload();
                }, 500);

                return true;
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue', 'error');

                // Réactiver le bouton
                submitBtn.innerHTML = 'Créer';
                submitBtn.disabled = false; return false;
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.core.showToast('Une erreur est survenue', 'error');

            // Réactiver le bouton
            submitBtn.innerHTML = 'Créer';
            submitBtn.disabled = false;

            return false;
        }
    }

    /**
     * Déplace un élément
     */
    async moveItem(sourceId, sourceType, targetId) {
        const formData = new FormData();
        formData.append('id', sourceId);
        formData.append('destination_id', targetId);
        formData.append('type', sourceType);
        formData.append('csrf_token', this.core.config.csrfToken);

        // Référencer les éléments pour l'animation
        const sourceItem = document.querySelector(`.filemanager-item[data-id="${sourceId}"][data-type="${sourceType}"]`);
        const targetFolder = document.querySelector(`.filemanager-item[data-id="${targetId}"][data-type="folder"]`);

        // Animation de déplacement
        if (sourceItem && targetFolder) {
            sourceItem.classList.add('moving');
            targetFolder.classList.add('receiving');

            setTimeout(() => {
                sourceItem.classList.add('fade-out');
            }, 300);
        }

        try {
            const response = await fetch(this.core.config.apiEndpoints.move, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.core.showToast(`${sourceType === 'folder' ? 'Dossier' : 'Fichier'} déplacé avec succès`, 'success');

                // Finaliser l'animation et supprimer l'élément
                if (sourceItem) {
                    setTimeout(() => {
                        sourceItem.remove();

                        // Si la grille est vide, afficher le message
                        const grid = this.core.elements.grid;
                        if (grid && grid.children.length === 0) {
                            grid.innerHTML = `
                            <div class="filemanager-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>Ce dossier est vide</p>
                            </div>`;
                        }
                    }, 500);
                }

                return true;
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue lors du déplacement', 'error');

                // Annuler l'animation
                if (sourceItem) {
                    sourceItem.classList.remove('fade-out', 'moving');
                }
                if (targetFolder) {
                    targetFolder.classList.remove('receiving');
                }

                return false;
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.core.showToast('Une erreur est survenue lors du déplacement', 'error');

            // Annuler l'animation
            if (sourceItem) {
                sourceItem.classList.remove('fade-out', 'moving');
            }
            if (targetFolder) {
                targetFolder.classList.remove('receiving');
            }

            return false;
        }
    }

    /**
     * Prévisualise un fichier
     */
    async previewFile(fileId) {
        console.log('Prévisualisation du fichier ID:', fileId);

        try {
            const url = `${this.core.config.apiEndpoints.preview}?id=${fileId}&csrf_token=${encodeURIComponent(this.core.config.csrfToken)}`;
            console.log('URL de requête:', url);

            // Afficher l'indicateur de chargement
            document.getElementById('previewModalBody').innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div>';

            const response = await fetch(url);
            console.log('Statut de réponse:', response.status);
            console.log('Fetch a fini de se charger :', response.method + ' "' + response.url + '".');

            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }

            // Vérifier le type de contenu
            const contentType = response.headers.get('content-type');
            console.log('Content-Type de la réponse:', contentType);

            if (!contentType || !contentType.includes('application/json')) {
                console.error('La réponse n\'est pas au format JSON:', contentType);
                const textResponse = await response.text();
                console.log('Contenu brut de la réponse:', textResponse.substring(0, 500)); // Affiche les 500 premiers caractères
                throw new Error('La réponse n\'est pas au format JSON.');
            }

            // Récupérer le JSON
            const data = await response.json();
            console.log('Données reçues:', data);

            // Créer le conteneur de prévisualisation s'il n'existe pas
            let previewContainer = document.getElementById('file-preview-container');
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.id = 'file-preview-container';
                previewContainer.className = 'file-preview-container';
                document.body.appendChild(previewContainer);

                // Créer l'en-tête avec le bouton de fermeture
                const header = document.createElement('div');
                header.className = 'file-preview-header';

                const title = document.createElement('h3');
                title.id = 'preview-file-name';

                const closeButton = document.createElement('button');
                closeButton.id = 'close-preview-btn';
                closeButton.className = 'close-btn';
                closeButton.innerHTML = '&times;';
                closeButton.addEventListener('click', closePreview);

                header.appendChild(title);
                header.appendChild(closeButton);
                previewContainer.appendChild(header);

                // Ajouter l'iframe ou la div de contenu
                const content = document.createElement('div');
                content.id = 'file-preview-content';
                previewContainer.appendChild(content);
            }

            // Traiter selon le type de fichier
            if (data.type === 'text') {
                // Afficher le contenu texte
                document.getElementById('previewModalBody').innerHTML = `
                    <div class="preview-text">
                        <pre>${this.escapeHtml(data.content)}</pre>
                    </div>
                `;
            } else if (data.type === 'binary' && data.mime_type.startsWith('image/')) {
                // Afficher l'image
                document.getElementById('previewModalBody').innerHTML = `
                    <div class="preview-image">
                        <img src="${data.preview_url}" alt="${data.name}" class="img-fluid" />
                    </div>
                `;
            } else if (data.type === 'binary' && data.mime_type === 'application/pdf') {
                // Afficher le PDF via iframe
                document.getElementById('previewModalBody').innerHTML = `
                    <div class="preview-pdf">
                        <iframe src="${data.preview_url}" width="100%" height="500px"></iframe>
                    </div>
                `;
            } else {
                // Afficher un message pour les autres types de fichiers
                document.getElementById('previewModalBody').innerHTML = `
                    <div class="preview-unsupported">
                        <i class="fas fa-file"></i>
                        <p>Ce type de fichier (${data.mime_type}) ne peut pas être prévisualisé.</p>
                        <a href="${data.preview_url}" class="btn btn-primary" download="${data.name}">Télécharger</a>
                    </div>
                `;
            }

        } catch (error) {
            console.error('Erreur de prévisualisation:', error);
            document.getElementById('previewModalBody').innerHTML = `
                <div class="preview-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Une erreur est survenue lors du chargement du fichier: ${error.message}</p>
                </div>
            `;
        }
    }

    /**
     * Cache la modale de prévisualisation
     */
    hidePreviewModal() {
        const modal = document.getElementById('previewModal');
        if (modal) {
            modal.classList.remove('show');
            // Vider le contenu pour libérer les ressources
            setTimeout(() => {
                document.getElementById('previewModalBody').innerHTML = '';
            }, 300);
        }
    }

    /**
     * Télécharge un fichier
     */

    /**
     * Téléverse un fichier
     */
    /**
 * Téléverse un fichier avec protection contre les téléchargements multiples
 */
    async uploadFile(file) {
        // Protection contre les téléchargements multiples/concurrents
        if (FileManagerOperations.isUploading) {
            this.core.showToast('Un téléchargement est déjà en cours, veuillez patienter', 'warning');
            return false;
        }

        // Définir l'indicateur de téléchargement en cours
        FileManagerOperations.isUploading = true;

        // Vérification basique
        if (!file) {
            FileManagerOperations.isUploading = false;
            return false;
        }

        // Enregistrer le nom du fichier pour la vérification
        const fileName = file.name;
        console.log(`📤 Début du téléchargement: "${fileName}"`);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', this.core.config.currentFolderId);
        formData.append('csrf_token', this.core.config.csrfToken);
        formData.append('upload_id', Math.random().toString(36).substring(2, 15)); // Identifiant unique

        // Afficher un toast de progression
        const toast = this.core.showToast(`Téléchargement de "${fileName}" en cours...`, 'info', false);

        // Ajouter une barre de progression
        const progressContainer = document.createElement('div');
        progressContainer.className = 'toast-progress-container';

        const progressBar = document.createElement('div');
        progressBar.className = 'toast-progress-bar';
        progressBar.style.width = '0%';

        progressContainer.appendChild(progressBar);
        toast.appendChild(progressContainer);

        try {
            // Simuler une progression
            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += 5;
                    progressBar.style.width = `${progress}%`;
                }
            }, 100);

            const response = await fetch(this.core.config.apiEndpoints.upload, {
                method: 'POST',
                body: formData
            });

            clearInterval(progressInterval);

            const data = await response.json();
            console.log(`📬 Réponse du serveur pour "${fileName}":`, data);

            if (data.success) {
                // Mise à jour de la barre de progression à 100%
                progressBar.style.width = '100%';
                progressBar.style.backgroundColor = 'var(--fm-success)';

                setTimeout(() => {
                    // Fermer le toast de progression
                    toast.remove();

                    // Afficher un message de succès
                    this.core.showToast(`Le fichier "${fileName}" a été téléchargé avec succès`, 'success');

                    // Actualiser la page pour afficher le nouveau fichier
                    setTimeout(() => {
                        console.log(`🔄 Actualisation de la page après téléchargement de "${fileName}"`);
                        window.location.reload();
                    }, 500);
                }, 500);

                return true;
            } else {
                toast.remove();
                this.core.showToast(data.error || `Une erreur est survenue lors du téléchargement de "${fileName}"`, 'error');
                return false;
            }
        } catch (error) {
            console.error(`❌ Erreur lors du téléchargement de "${fileName}":`, error);
            toast.remove();
            this.core.showToast(`Une erreur est survenue lors du téléchargement de "${fileName}"`, 'error');
            return false;
        } finally {
            // S'assurer que le verrouillage est toujours libéré, quel que soit le résultat
            console.log(`📥 Fin du processus de téléchargement pour "${fileName}"`);
            FileManagerOperations.isUploading = false;
        }
    }

    /**
     * Échappe le HTML
     */
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Recherchez la fonction qui traite la fermeture et modifiez-la comme suit:

function closePreview() {
    // Fermer le conteneur fixe
    const previewContainer = document.getElementById('file-preview-container');
    if (previewContainer) {
        previewContainer.style.display = 'none';

        // Vider le contenu pour libérer les ressources
        const previewFrame = document.getElementById('file-preview-frame');
        if (previewFrame) {
            previewFrame.src = 'about:blank';
        }

        const previewContent = document.getElementById('file-preview-content');
        if (previewContent) {
            previewContent.innerHTML = '';
        }
    }

    // Fermer également la modal si elle existe
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            const modalBody = document.getElementById('previewModalBody');
            if (modalBody) {
                modalBody.innerHTML = '';
            }
        }, 300);
    }
}

// Assurez-vous que cette fonction est exportée correctement
window.closePreview = closePreview;

// Assurez-vous que cette fonction est bien associée à l'événement de clic du bouton
document.addEventListener('DOMContentLoaded', function () {
    const closeBtn = document.getElementById('close-preview-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closePreview);
    }
});
/**
 * Solution complète et simplifiée avec rechargement de page
 * Copiez entièrement ce code et placez-le dans une balise script à la fin de votre index.php
 */
document.addEventListener('DOMContentLoaded', function () {
    // Attendre que tout soit initialisé
    setTimeout(function () {
        // 1. Créer la boîte de dialogue de renommage si elle n'existe pas
        if (!document.getElementById('renameContextual')) {
            createRenameDialog();
        }

        // 2. Remplacer les gestionnaires d'événements sur tous les boutons de renommage
        document.querySelectorAll('.btn-rename').forEach(function (button) {
            // Cloner le bouton pour supprimer tous les gestionnaires existants
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            // Ajouter le nouveau gestionnaire d'événements
            newButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Trouver l'élément parent (fichier ou dossier)
                const item = this.closest('.filemanager-item');
                if (item) {
                    showRenameDialog(item);
                }
            });
        });

        console.log("✅ Gestionnaires d'événements des boutons de renommage reconfigurés");
    }, 500);
});

/**
 * Crée la boîte de dialogue de renommage
 */
function createRenameDialog() {
    const dialog = document.createElement('div');
    dialog.id = 'renameContextual';
    dialog.className = 'contextual-dialog rename-contextual';
    dialog.innerHTML = `
        <div class="contextual-dialog-content">
            <div class="contextual-dialog-header">
                <h3 id="renameContextualTitle">Renommer</h3>
                <button class="contextual-dialog-close" id="closeRenameContextual">&times;</button>
            </div>
            <div class="contextual-dialog-body">
                <form id="renameContextualForm">
                    <div class="form-group">
                        <label for="renameContextualInput" class="form-label">Nouveau nom</label>
                        <input type="text" id="renameContextualInput" class="form-control" required>
                    </div>
                </form>
            </div>
            <div class="contextual-dialog-footer">
                <button type="button" id="cancelRenameContextual" class="btn btn-light">Annuler</button>
                <button type="button" id="submitRenameContextual" class="btn btn-primary">Renommer</button>
            </div>
        </div>
        <input type="hidden" id="renameContextualItemId" value="">
        <input type="hidden" id="renameContextualItemType" value="">
        <input type="hidden" id="renameContextualExtension" value="">
    `;

    document.body.appendChild(dialog);

    // Configurer les gestionnaires d'événements
    dialog.querySelector('#renameContextualInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitRename();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            hideRenameDialog();
        }
    });

    dialog.querySelector('#submitRenameContextual').addEventListener('click', () => {
        submitRename();
    });

    dialog.querySelector('#cancelRenameContextual').addEventListener('click', () => {
        hideRenameDialog();
    });

    dialog.querySelector('#closeRenameContextual').addEventListener('click', () => {
        hideRenameDialog();
    });

    // Fermer quand on clique ailleurs
    document.addEventListener('click', (e) => {
        if (dialog.classList.contains('show') &&
            !dialog.contains(e.target) &&
            !e.target.closest('.btn-rename')) {
            hideRenameDialog();
        }
    });

    console.log("✅ Boîte de dialogue de renommage créée");
    return dialog;
}

/**
 * Affiche la boîte de dialogue de renommage
 */
function showRenameDialog(item) {
    const id = item.dataset.id;
    const type = item.dataset.type;
    const nameElement = item.querySelector('.filemanager-item-name span');
    let name = nameElement ? nameElement.textContent : '';

    console.log("📝 Renommage de l'élément:", { id, type, name });

    // Pour les fichiers, séparer le nom et l'extension
    let extension = '';
    if (type === 'file') {
        const nameParts = name.split('.');
        if (nameParts.length > 1) {
            extension = nameParts.pop();
            name = nameParts.join('.');
        }
    }

    // S'assurer que la boîte de dialogue existe
    const dialog = document.getElementById('renameContextual') || createRenameDialog();

    // Remplir les champs
    document.getElementById('renameContextualTitle').textContent =
        `Renommer ${type === 'folder' ? 'le dossier' : 'le fichier'}`;
    document.getElementById('renameContextualItemId').value = id;
    document.getElementById('renameContextualItemType').value = type;
    document.getElementById('renameContextualExtension').value = extension;
    document.getElementById('renameContextualInput').value = name;

    // Ajouter une classe de mise en évidence à l'élément
    item.classList.add('highlight');

    // Centrer dans la fenêtre
    dialog.style.top = '50%';
    dialog.style.left = '50%';
    dialog.style.transform = 'translate(-50%, -50%)';

    // Afficher
    dialog.classList.add('show');

    // Focus sur le champ avec sélection du texte
    setTimeout(() => {
        const input = document.getElementById('renameContextualInput');
        input.focus();
        input.select();
    }, 50);
}

/**
 * Cache la boîte de dialogue de renommage
 */
function hideRenameDialog() {
    const dialog = document.getElementById('renameContextual');
    if (dialog) {
        dialog.classList.remove('show');

        // Retirer la mise en évidence
        const itemId = document.getElementById('renameContextualItemId').value;
        const itemType = document.getElementById('renameContextualItemType').value;
        const item = document.querySelector(`.filemanager-item[data-id="${itemId}"][data-type="${itemType}"]`);
        if (item) {
            item.classList.remove('highlight');
        }
    }
}

/**
 * Soumet le formulaire de renommage
 */
function submitRename() {
    const id = document.getElementById('renameContextualItemId').value;
    const type = document.getElementById('renameContextualItemType').value;
    let newName = document.getElementById('renameContextualInput').value.trim();

    // Restaurer l'extension pour les fichiers
    const extension = document.getElementById('renameContextualExtension').value;
    if (type === 'file' && extension) {
        newName = `${newName}.${extension}`;
    }

    if (!newName) {
        showToast('Veuillez saisir un nom', 'error');
        return;
    }

    console.log("🔄 Soumission du renommage:", { id, type, newName });

    // Désactiver les boutons pendant le traitement
    const submitBtn = document.getElementById('submitRenameContextual');
    const cancelBtn = document.getElementById('cancelRenameContextual');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    submitBtn.disabled = true;
    cancelBtn.disabled = true;

    // Récupérer le jeton CSRF
    const csrfToken = document.getElementById('csrfToken').value;

    // Préparer les données de la requête
    const formData = new FormData();
    formData.append('id', id);
    formData.append('new_name', newName);
    formData.append('type', type);
    formData.append('csrf_token', csrfToken);

    // Afficher un toast de chargement
    const loadingToast = showToast(`Renommage en cours...`, 'info', false);

    // Envoyer la requête
    fetch('api/rename.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("✅ Réponse du serveur:", data);

            // Fermer le toast de chargement
            if (loadingToast) {
                loadingToast.remove();
            }

            if (data.success) {
                // La solution la plus sûre : toujours recharger la page après un renommage réussi
                hideRenameDialog();
                showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} renommé avec succès. Actualisation...`, 'success');

                // Recharger la page après un court délai
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.error || 'Une erreur est survenue lors du renommage', 'error');

                // Réactiver les boutons
                submitBtn.innerHTML = 'Renommer';
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('❌ Erreur lors du renommage:', error);

            // Fermer le toast de chargement
            if (loadingToast) {
                loadingToast.remove();
            }

            showToast('Erreur: ' + error.message, 'error');

            // Réactiver les boutons
            submitBtn.innerHTML = 'Renommer';
            submitBtn.disabled = false;
            cancelBtn.disabled = false;
        });
}

/**
 * Crée un conteneur de toasts s'il n'existe pas
 */
function createToastContainer() {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Affiche un toast de notification
 */
function showToast(message, type = 'info', autoClose = true) {
    // Utiliser le conteneur existant ou en créer un
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();

    // Créer le toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    // Déterminer l'icône
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icon}"></i>
        </div>
        <div class="toast-message">${message}</div>
        <button class="toast-close">&times;</button>
    `;

    // Ajouter le toast au conteneur
    toastContainer.appendChild(toast);

    // Animation d'entrée
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    // Configurer la fermeture
    const closeToast = () => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    };

    // Fermeture automatique
    if (autoClose) {
        setTimeout(closeToast, 5000);
    }

    // Fermeture manuelle
    toast.querySelector('.toast-close').addEventListener('click', closeToast);

    return toast;
}