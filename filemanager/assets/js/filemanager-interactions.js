/**
 * FileManager Interactions Améliorées
 * Gère les interactions modernes avec des fenêtres contextuelles et drag & drop
 */

class FileManagerInteractions {
    /**
     * Initialise les interactions du gestionnaire de fichiers
     */
    constructor() {
        // Éléments du DOM
        this.filemanagerGrid = document.getElementById('filemanagerGrid');
        this.contextMenu = document.getElementById('contextMenu');
        this.renameContextual = null; // Sera créé dynamiquement
        this.dragIndicator = null; // Sera créé dynamiquement

        // Configuration
        this.currentFolderId = parseInt(document.querySelector('input[name="current_folder_id"]')?.value || document.getElementById('currentFolderId')?.value || 0);
        this.csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        // État des interactions
        this.activeItem = null;
        this.draggedItem = null;
        this.dragClone = null;

        this.init();
    }

    /**
     * Initialise toutes les fonctionnalités d'interaction
     */
    init() {
        this.setupContextMenu();
        this.setupRenameContextual();
        this.setupDragDrop();
        this.setupFileManagerItems();
        this.setupNewFolderButton();
        this.setupModals();
        this.setupFileUpload();
    }
    /**
 * Configure les événements pour les éléments du gestionnaire
 */
    setupFileManagerItems() {
        if (!this.filemanagerGrid) return;

        // Double-clic pour ouvrir un dossier ou prévisualiser un fichier
        this.filemanagerGrid.addEventListener('dblclick', (e) => {
            const item = e.target.closest('.filemanager-item');
            if (!item) return;

            const type = item.dataset.type;
            const id = item.dataset.id;

            if (type === 'folder') {
                // Ouvrir le dossier
                window.location.href = `index.php?folder=${id}`;
            } else if (type === 'file') {
                // Prévisualiser le fichier
                this.previewFile(id);
            }
        });

        // Configurer les actions pour tous les éléments existants
        document.querySelectorAll('.filemanager-item').forEach(item => {
            this.setupItemEvents(item);
        });
    }
    setupModals() {
        // Fermer les modales quand on clique sur le fond ou le bouton de fermeture
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        document.querySelectorAll('.modal-close').forEach(button => {
            button.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Fermer les modales avec Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
                document.querySelectorAll('.contextual-dialog.show').forEach(dialog => {
                    dialog.classList.remove('show');
                });
            }
        });
    }
    hideContextMenu() {
        if (this.contextMenu) {
            this.contextMenu.classList.remove('show');
        }
    }
    /**
     * Configure les événements pour un élément spécifique
     */
    setupItemEvents(item) {
        // Boutons de renommage
        const renameBtn = item.querySelector('.btn-rename');
        if (renameBtn) {
            renameBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showRenameContextual(item);
            });
        }

        // Boutons de suppression
        const deleteBtn = item.querySelector('.btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const name = item.querySelector('.filemanager-item-name span').textContent;
                this.showDeleteConfirmation(item.dataset.id, item.dataset.type, name);
            });
        }

        // Configurer le glisser-déposer pour cet élément
        this.setupItemDragEvents(item);

        // Si c'est un dossier, le configurer comme zone de dépôt
        if (item.classList.contains('folder')) {
            this.setupDropZoneEvents(item);
        }
    }

    /**
     * Configure le bouton de création de nouveau dossier
     */
    setupNewFolderButton() {
        const btnNewFolder = document.getElementById('btnNewFolder');
        if (btnNewFolder) {
            btnNewFolder.addEventListener('click', () => {
                this.showNewFolderDialog();
            });
        }
    }

    /**
     * Configure le téléchargement de fichiers
     */
    setupFileUpload() {
        const uploadFile = document.getElementById('uploadFile');
        if (uploadFile) {
            uploadFile.addEventListener('change', (e) => {
                this.handleFileUpload(e);
            });
        }
    }

    /**
     * Gère le téléchargement de fichiers
     */
    handleFileUpload(e) {
        const file = e.target.files[0];

        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', this.currentFolderId);
        formData.append('csrf_token', this.csrfToken);

        // Afficher un toast de progression
        const toast = this.showToast(`Téléchargement de "${file.name}" en cours...`, 'info', false);

        // Ajouter une barre de progression au toast
        const progressContainer = document.createElement('div');
        progressContainer.className = 'toast-progress-container';

        const progressBar = document.createElement('div');
        progressBar.className = 'toast-progress-bar';
        progressBar.style.width = '0%';

        progressContainer.appendChild(progressBar);
        toast.appendChild(progressContainer);

        fetch('api/upload.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mise à jour de la barre de progression
                    progressBar.style.width = '100%';
                    progressBar.style.backgroundColor = 'var(--fm-success)';

                    setTimeout(() => {
                        // Fermer le toast de progression
                        toast.remove();

                        // Afficher un message de succès
                        this.showToast(`Le fichier "${file.name}" a été téléchargé`, 'success');

                        // Actualiser la page
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }, 500);
                } else {
                    toast.remove();
                    this.showToast(data.error || 'Une erreur est survenue lors du téléchargement', 'error');
                }
            })
            .catch(error => {
                toast.remove();
                this.showToast('Une erreur est survenue lors du téléchargement', 'error');
                console.error('Erreur:', error);
            })
            .finally(() => {
                // Réinitialiser l'input pour permettre de télécharger à nouveau le même fichier
                e.target.value = '';
            });
    }

    /**
     * Prévisualise un fichier
     */
    previewFile(fileId) {
        // Vérifier si la modale existe, sinon la créer
        if (!document.getElementById('previewModal')) {
            const previewModal = document.createElement('div');
            previewModal.id = 'previewModal';
            previewModal.className = 'modal modal-large';
            previewModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="previewModalTitle">Prévisualisation</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body" id="previewModalBody">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="closePreview">Fermer</button>
                    <a href="#" class="btn btn-primary" id="downloadFile" download>Télécharger</a>
                </div>
            </div>
        `;
            document.body.appendChild(previewModal);

            // Configurer les événements
            document.querySelector('#previewModal .modal-close').addEventListener('click', () => {
                this.hidePreviewModal();
            });

            document.getElementById('closePreview').addEventListener('click', () => {
                this.hidePreviewModal();
            });
        }

        // Afficher un indicateur de chargement
        document.getElementById('previewModalBody').innerHTML = '<div class="loader"></div>';
        document.getElementById('previewModalTitle').textContent = 'Chargement...';
        document.getElementById('previewModal').classList.add('show');

        fetch(`api/preview.php?id=${fileId}&csrf_token=${this.csrfToken}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('previewModalTitle').textContent = data.name;
                    document.getElementById('downloadFile').href = `api/download.php?id=${fileId}&csrf_token=${this.csrfToken}`;

                    let previewContent = '';

                    if (data.type === 'image') {
                        previewContent = `<img src="api/download.php?id=${fileId}&csrf_token=${this.csrfToken}" alt="${data.name}" class="preview-image">`;
                    } else if (data.type === 'pdf') {
                        previewContent = `<iframe src="api/download.php?id=${fileId}&csrf_token=${this.csrfToken}" class="preview-pdf"></iframe>`;
                    } else if (data.type === 'text') {
                        previewContent = `<pre class="preview-text">${data.content}</pre>`;
                    } else {
                        previewContent = `
                        <div class="preview-not-available">
                            <i class="fas fa-file"></i>
                            <p>La prévisualisation n'est pas disponible pour ce type de fichier.</p>
                            <p>Vous pouvez télécharger ce fichier pour le visualiser.</p>
                        </div>
                    `;
                    }

                    document.getElementById('previewModalBody').innerHTML = previewContent;
                } else {
                    document.getElementById('previewModalTitle').textContent = 'Erreur';
                    document.getElementById('previewModalBody').innerHTML = `
                    <div class="preview-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>${data.error || 'Une erreur est survenue lors du chargement du fichier'}</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                document.getElementById('previewModalTitle').textContent = 'Erreur';
                document.getElementById('previewModalBody').innerHTML = `
                <div class="preview-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Une erreur est survenue lors du chargement du fichier</p>
                </div>
            `;
                console.error('Erreur:', error);
            });
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
     * Affiche la boîte de dialogue de création de dossier
     */
    showNewFolderDialog() {
        // Vérifier si la boîte de dialogue existe, sinon la créer
        if (!document.getElementById('newFolderDialog')) {
            const dialog = document.createElement('div');
            dialog.id = 'newFolderDialog';
            dialog.className = 'contextual-dialog';
            dialog.innerHTML = `
            <div class="contextual-dialog-content">
                <div class="contextual-dialog-header">
                    <h3>Nouveau dossier</h3>
                    <button class="contextual-dialog-close" id="closeNewFolderDialog">&times;</button>
                </div>
                <div class="contextual-dialog-body">
                    <form id="newFolderForm">
                        <div class="form-group">
                            <label for="folderName" class="form-label">Nom du dossier</label>
                            <input type="text" id="folderName" class="form-control" required>
                        </div>
                    </form>
                </div>
                <div class="contextual-dialog-footer">
                    <button type="button" id="cancelNewFolder" class="btn btn-light">Annuler</button>
                    <button type="button" id="submitNewFolder" class="btn btn-primary">Créer</button>
                </div>
            </div>
        `;
            document.body.appendChild(dialog);

            // Configurer les événements
            document.getElementById('closeNewFolderDialog').addEventListener('click', () => {
                document.getElementById('newFolderDialog').classList.remove('show');
            });

            document.getElementById('cancelNewFolder').addEventListener('click', () => {
                document.getElementById('newFolderDialog').classList.remove('show');
            });

            document.getElementById('submitNewFolder').addEventListener('click', () => {
                this.createNewFolder();
            });

            document.getElementById('folderName').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.createNewFolder();
                }
            });
        }

        // Afficher la boîte de dialogue centrée
        const dialog = document.getElementById('newFolderDialog');
        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';
        dialog.classList.add('show');

        // Focus sur le champ de saisie
        setTimeout(() => {
            document.getElementById('folderName').focus();
        }, 100);
    }

    /**
     * Crée un nouveau dossier
     */
    createNewFolder() {
        const folderName = document.getElementById('folderName').value.trim();
    
        if (!folderName) {
            this.core.showToast('Veuillez saisir un nom de dossier', 'error');
            return;
        }
    
        // Récupérer des références directes aux valeurs
        const formData = new FormData();
        formData.append('name', folderName);
        formData.append('parent_id', this.core.config.currentFolderId);
        formData.append('csrf_token', this.core.config.csrfToken);
    
        // Désactiver le bouton pendant la requête
        const submitBtn = document.getElementById('submitNewFolder');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';
            submitBtn.disabled = true;
        }
    
        // Faire la requête avec fetch
        fetch(this.core.config.apiEndpoints.create, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.core.showToast(`Le dossier "${folderName}" a été créé`, 'success');
                this.closeDialog('newFolder');
                
                // Rafraîchir la page après un court délai
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue', 'error');
                
                // Réactiver le bouton
                if (submitBtn) {
                    submitBtn.innerHTML = 'Créer';
                    submitBtn.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.core.showToast('Une erreur est survenue', 'error');
            
            // Réactiver le bouton
            if (submitBtn) {
                submitBtn.innerHTML = 'Créer';
                submitBtn.disabled = false;
            }
        });
    }
    /**
     * Supprime un élément (fichier ou dossier)
     */
    deleteItem(id, type) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('type', type);
        formData.append('csrf_token', this.csrfToken);

        // Trouver l'élément pour l'animation
        const item = document.querySelector(`.filemanager-item[data-id="${id}"][data-type="${type}"]`);

        if (item) {
            // Appliquer une animation de disparition
            item.classList.add('deleting');
            setTimeout(() => {
                item.classList.add('fade-out');
            }, 300);
        }

        fetch('api/delete.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} supprimé avec succès`, 'success');

                    // Supprimer l'élément du DOM après l'animation
                    if (item) {
                        setTimeout(() => {
                            item.remove();

                            // Si la grille est vide, afficher le message "dossier vide"
                            if (this.filemanagerGrid && this.filemanagerGrid.children.length === 0) {
                                this.filemanagerGrid.innerHTML = `
                            <div class="filemanager-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>Ce dossier est vide</p>
                            </div>
                            `;
                            }
                        }, 500);
                    }
                } else {
                    this.showToast(data.error || 'Une erreur est survenue lors de la suppression', 'error');

                    // Restaurer l'élément en cas d'erreur
                    if (item) {
                        item.classList.remove('fade-out', 'deleting');
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.showToast('Une erreur est survenue lors de la suppression', 'error');

                // Restaurer l'élément en cas d'erreur
                if (item) {
                    item.classList.remove('fade-out', 'deleting');
                }
            });
    }

    /**
     * Affiche un toast de notification
     */
    showToast(message, type = 'info', autoClose = true) {
        // Créer le conteneur de toasts s'il n'existe pas
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

        // Déterminer l'icône appropriée
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

        // Configurer la fermeture du toast
        const closeToast = () => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        };

        // Fermeture automatique après 5 secondes
        if (autoClose) {
            setTimeout(closeToast, 5000);
        }

        // Fermeture manuelle
        toast.querySelector('.toast-close').addEventListener('click', closeToast);

        return toast;
    }
    /**
     * Configure le menu contextuel
     */
    setupContextMenu() {
        // Écouter le clic droit sur les éléments
        document.addEventListener('contextmenu', (e) => {
            const item = e.target.closest('.filemanager-item');
            if (!item) {
                this.hideContextMenu();
                return;
            }

            // Empêcher le menu contextuel par défaut
            e.preventDefault();

            // Définir l'élément actif
            this.activeItem = item;

            // Afficher/masquer les options en fonction du type d'élément
            const type = item.dataset.type;
            document.querySelectorAll('.context-menu-folder').forEach(el => {
                el.style.display = type === 'folder' ? 'block' : 'none';
            });
            document.querySelectorAll('.context-menu-file').forEach(el => {
                el.style.display = type === 'file' ? 'block' : 'none';
            });

            // Positionner et afficher le menu
            const rect = item.getBoundingClientRect();
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const scrollLeft = window.scrollX || document.documentElement.scrollLeft;

            // Calculer la position optimale
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            const menuHeight = this.contextMenu.offsetHeight || 200; // Hauteur estimée si non disponible
            const menuWidth = this.contextMenu.offsetWidth || 200; // Largeur estimée

            let top, left;

            // Position verticale
            if (rect.bottom + menuHeight <= viewportHeight) {
                // Afficher en dessous si assez d'espace
                top = rect.bottom + scrollTop;
            } else {
                // Afficher au-dessus si pas assez d'espace en dessous
                top = rect.top + scrollTop - menuHeight;
                if (top < scrollTop) top = scrollTop + 5; // Éviter d'aller hors écran en haut
            }

            // Position horizontale
            if (rect.left + menuWidth <= viewportWidth) {
                // Afficher à droite si assez d'espace
                left = rect.left + scrollLeft;
            } else {
                // Afficher à gauche si pas assez d'espace à droite
                left = rect.right + scrollLeft - menuWidth;
                if (left < scrollLeft) left = scrollLeft + 5; // Éviter d'aller hors écran à gauche
            }

            this.contextMenu.style.top = `${top}px`;
            this.contextMenu.style.left = `${left}px`;
            this.contextMenu.classList.add('show');

            // Ajouter une classe d'animation à chaque élément
            const menuItems = this.contextMenu.querySelectorAll('li');
            menuItems.forEach((item, index) => {
                // Réinitialiser les animations
                item.style.animation = 'none';
                item.offsetHeight; // Force reflow
                item.style.animation = `slideIn 0.2s forwards ${index * 0.05}s`;
            });
        });

        // Fermer le menu contextuel lors d'un clic ailleurs
        document.addEventListener('click', () => {
            this.hideContextMenu();
        });

        // Configurer les actions du menu contextuel
        this.setupContextMenuItems();
    }

    /**
     * Cache le menu contextuel
     */
    hideContextMenu() {
        this.contextMenu.classList.remove('show');
    }

    /**
     * Configure les actions du menu contextuel
     */
    setupContextMenuItems() {
        document.querySelectorAll('#contextMenu li').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();

                if (!this.activeItem) return;

                const action = item.dataset.action;
                const id = this.activeItem.dataset.id;
                const type = this.activeItem.dataset.type;
                const name = this.activeItem.querySelector('.filemanager-item-name span').textContent;

                switch (action) {
                    case 'open':
                        if (type === 'folder') {
                            window.location.href = `index.php?folder=${id}`;
                        }
                        break;
                    case 'view':
                        if (type === 'file') {
                            this.previewFile(id);
                        }
                        break;
                    case 'download':
                        if (type === 'file') {
                            window.location.href = `api/download.php?id=${id}&csrf_token=${this.csrfToken}`;
                        }
                        break;
                    case 'rename':
                        this.showRenameContextual(this.activeItem);
                        break;
                    case 'move':
                        if (type === 'folder') {
                            // Pour les dossiers, on utilise le drag & drop
                            this.showToast('Utilisez le glisser-déposer pour déplacer le dossier', 'info');
                        } else {
                            // Pour les fichiers, on peut montrer une modale
                            this.showMoveModal(id, type, name);
                        }
                        break;
                    case 'delete':
                        this.showDeleteConfirmation(id, type, name);
                        break;
                }

                this.hideContextMenu();
            });
        });
    }

    /**
     * Crée et affiche une boîte de dialogue de confirmation contextuelle
     */
    showDeleteConfirmation(id, type, name) {
        // Créer l'élément s'il n'existe pas
        if (!document.getElementById('deleteConfirmContextual')) {
            const deleteConfirm = document.createElement('div');
            deleteConfirm.id = 'deleteConfirmContextual';
            deleteConfirm.className = 'contextual-dialog delete-confirm';
            deleteConfirm.innerHTML = `
                <div class="contextual-dialog-content">
                    <div class="contextual-dialog-header">
                        <h3>Confirmer la suppression</h3>
                        <button class="contextual-dialog-close" id="closeDeleteConfirm">&times;</button>
                    </div>
                    <div class="contextual-dialog-body">
                        <p id="deleteConfirmMessage">Êtes-vous sûr de vouloir supprimer cet élément ?</p>
                    </div>
                    <div class="contextual-dialog-footer">
                        <button id="cancelDelete" class="btn btn-light">Annuler</button>
                        <button id="confirmDelete" class="btn btn-danger">Supprimer</button>
                    </div>
                </div>
                <input type="hidden" id="deleteItemId" value="">
                <input type="hidden" id="deleteItemType" value="">
            `;
            document.body.appendChild(deleteConfirm);

            // Configurer les événements de fermeture
            document.getElementById('closeDeleteConfirm').addEventListener('click', () => {
                document.getElementById('deleteConfirmContextual').classList.remove('show');
            });

            document.getElementById('cancelDelete').addEventListener('click', () => {
                document.getElementById('deleteConfirmContextual').classList.remove('show');
            });

            document.getElementById('confirmDelete').addEventListener('click', () => {
                const deleteId = document.getElementById('deleteItemId').value;
                const deleteType = document.getElementById('deleteItemType').value;
                this.deleteItem(deleteId, deleteType);
                document.getElementById('deleteConfirmContextual').classList.remove('show');
            });

            // Fermer quand on clique en dehors
            document.addEventListener('click', (e) => {
                const dialog = document.getElementById('deleteConfirmContextual');
                if (dialog && dialog.classList.contains('show') && !dialog.contains(e.target) && e.target.id !== 'confirmDelete' && e.target.id !== 'cancelDelete') {
                    dialog.classList.remove('show');
                }
            });
        }

        // Mettre à jour le contenu
        document.getElementById('deleteConfirmMessage').textContent = `Êtes-vous sûr de vouloir supprimer "${name}" ?`;
        document.getElementById('deleteItemId').value = id;
        document.getElementById('deleteItemType').value = type;

        // Calculer la position optimale (centrée ou près de l'élément)
        const dialog = document.getElementById('deleteConfirmContextual');

        // Centrer dans la fenêtre
        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';

        // Afficher
        dialog.classList.add('show');
    }

    /**
     * Configure le renommage contextuel
     */
    setupRenameContextual() {
        // Créer l'élément s'il n'existe pas déjà
        if (!document.getElementById('renameContextual')) {
            const renameContextual = document.createElement('div');
            renameContextual.id = 'renameContextual';
            renameContextual.className = 'contextual-dialog rename-contextual';
            renameContextual.innerHTML = `
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

            // Ajouter au DOM
            document.body.appendChild(renameContextual);
            this.renameContextual = renameContextual;

            // Configurer les événements
            document.getElementById('renameContextualInput').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.submitRenameContextual();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hideRenameContextual();
                }
            });

            document.getElementById('submitRenameContextual').addEventListener('click', () => {
                this.submitRenameContextual();
            });

            document.getElementById('cancelRenameContextual').addEventListener('click', () => {
                this.hideRenameContextual();
            });

            document.getElementById('closeRenameContextual').addEventListener('click', () => {
                this.hideRenameContextual();
            });

            // Fermer quand on clique ailleurs
            document.addEventListener('click', (e) => {
                if (this.renameContextual.classList.contains('show') &&
                    !this.renameContextual.contains(e.target) &&
                    !e.target.closest('.btn-rename')) {
                    this.hideRenameContextual();
                }
            });
        }
    }

    /**
     * Affiche la boîte de dialogue de renommage contextuel
     */
    showRenameContextual(item) {
        const id = item.dataset.id;
        const type = item.dataset.type;
        const nameElement = item.querySelector('.filemanager-item-name span');
        let name = nameElement.textContent;

        // Pour les fichiers, ne pas afficher l'extension dans le champ
        let extension = '';
        if (type === 'file') {
            const nameParts = name.split('.');
            if (nameParts.length > 1) {
                extension = nameParts.pop();
                name = nameParts.join('.');
            }
        }

        // Remplir les champs
        document.getElementById('renameContextualTitle').textContent = `Renommer ${type === 'folder' ? 'le dossier' : 'le fichier'}`;
        document.getElementById('renameContextualItemId').value = id;
        document.getElementById('renameContextualItemType').value = type;
        document.getElementById('renameContextualExtension').value = extension;
        document.getElementById('renameContextualInput').value = name;

        // Ajouter une classe de mise en évidence à l'élément
        item.classList.add('highlight');

        // Positionner la boîte de dialogue à côté de l'élément
        const rect = item.getBoundingClientRect();
        const dialog = this.renameContextual;

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
     * Cache la boîte de dialogue de renommage contextuel
     */
    hideRenameContextual() {
        if (this.renameContextual) {
            this.renameContextual.classList.remove('show');

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
     * Soumet le formulaire de renommage contextuel
     */
    submitRenameContextual() {
        const id = document.getElementById('renameContextualItemId').value;
        const type = document.getElementById('renameContextualItemType').value;
        let newName = document.getElementById('renameContextualInput').value.trim();

        // Restaurer l'extension pour les fichiers
        const extension = document.getElementById('renameContextualExtension').value;
        if (type === 'file' && extension) {
            newName = `${newName}.${extension}`;
        }

        if (!newName) {
            this.showToast('Veuillez saisir un nom', 'error');
            return;
        }

        // Désactiver les boutons pendant le traitement
        document.getElementById('submitRenameContextual').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        document.getElementById('submitRenameContextual').disabled = true;
        document.getElementById('cancelRenameContextual').disabled = true;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('new_name', newName);
        formData.append('type', type);
        formData.append('csrf_token', this.csrfToken);

        fetch('api/rename.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour le nom dans le DOM
                    const item = document.querySelector(`.filemanager-item[data-id="${id}"][data-type="${type}"]`);
                    if (item) {
                        const nameElement = item.querySelector('.filemanager-item-name span');
                        nameElement.textContent = data.new_name || newName;

                        // Animation de réussite
                        nameElement.style.color = 'var(--fm-success)';
                        setTimeout(() => {
                            nameElement.style.transition = 'color 1s ease';
                            nameElement.style.color = '';
                        }, 50);
                    }

                    this.showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} renommé avec succès`, 'success');
                    this.hideRenameContextual();
                } else {
                    this.showToast(data.error || 'Une erreur est survenue lors du renommage', 'error');

                    // Réactiver les boutons
                    document.getElementById('submitRenameContextual').innerHTML = 'Renommer';
                    document.getElementById('submitRenameContextual').disabled = false;
                    document.getElementById('cancelRenameContextual').disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.showToast('Une erreur est survenue lors du renommage', 'error');

                // Réactiver les boutons
                document.getElementById('submitRenameContextual').innerHTML = 'Renommer';
                document.getElementById('submitRenameContextual').disabled = false;
                document.getElementById('cancelRenameContextual').disabled = false;
            });
    }

    /**
     * Configure la fonctionnalité de déplacement
     */
    showMoveModal(id, type, name) {
        // Vérifier si la modale existe, sinon la créer
        if (!document.getElementById('moveModal')) {
            const moveModal = document.createElement('div');
            moveModal.id = 'moveModal';
            moveModal.className = 'modal';
            moveModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="moveModalTitle">Déplacer</h2>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="moveForm">
                            <div class="form-group">
                                <label for="destinationFolder" class="form-label">Dossier de destination</label>
                                <select id="destinationFolder" name="destinationFolder" class="form-control" required>
                                    <!-- Options chargées dynamiquement -->
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" id="cancelMove">Annuler</button>
                        <button class="btn btn-primary" id="submitMove">Déplacer</button>
                    </div>
                </div>
            `;
            document.body.appendChild(moveModal);

            // Configurer les événements
            document.querySelector('#moveModal .modal-close').addEventListener('click', () => {
                document.getElementById('moveModal').classList.remove('show');
            });

            document.getElementById('cancelMove').addEventListener('click', () => {
                document.getElementById('moveModal').classList.remove('show');
            });

            document.getElementById('submitMove').addEventListener('click', () => {
                const sourceId = document.getElementById('moveItemId').value;
                const sourceType = document.getElementById('moveItemType').value;
                const destinationId = document.getElementById('destinationFolder').value;

                this.moveItem(sourceId, sourceType, destinationId);
                document.getElementById('moveModal').classList.remove('show');
            });
        }

        // Mettre à jour le contenu
        document.getElementById('moveModalTitle').textContent = `Déplacer ${type === 'folder' ? 'le dossier' : 'le fichier'} "${name}"`;

        // Créer les champs cachés s'ils n'existent pas
        if (!document.getElementById('moveItemId')) {
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.id = 'moveItemId';
            document.getElementById('moveForm').appendChild(idInput);
        }
        if (!document.getElementById('moveItemType')) {
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.id = 'moveItemType';
            document.getElementById('moveForm').appendChild(typeInput);
        }

        // Mettre à jour les valeurs
        document.getElementById('moveItemId').value = id;
        document.getElementById('moveItemType').value = type;

        // Charger la liste des dossiers disponibles
        fetch(`api/folders.php?csrf_token=${this.csrfToken}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('destinationFolder');
                    select.innerHTML = '';

                    data.folders.forEach(folder => {
                        // Ne pas afficher le dossier actuel ou le dossier à déplacer
                        if (folder.id != this.currentFolderId && (type !== 'folder' || folder.id != id)) {
                            const option = document.createElement('option');
                            option.value = folder.id;
                            option.textContent = folder.path || folder.name;
                            select.appendChild(option);
                        }
                    });

                    // Afficher la modale
                    document.getElementById('moveModal').classList.add('show');
                } else {
                    this.showToast(data.error || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                this.showToast('Une erreur est survenue lors du chargement des dossiers', 'error');
                console.error('Erreur:', error);
            });
    }

    /**
     * Configure la fonctionnalité de glisser-déposer pour le déplacement
     */
    setupDragDrop() {
        // Créer l'indicateur de drag & drop s'il n'existe pas
        this.createDragIndicator();

        // Configurer les événements pour chaque élément
        document.querySelectorAll('.filemanager-item').forEach(item => {
            this.setupItemDragEvents(item);
        });

        // Configurer les zones de dépôt (dossiers)
        document.querySelectorAll('.filemanager-item.folder').forEach(folder => {
            this.setupDropZoneEvents(folder);
        });
    }

    /**
     * Crée l'indicateur de déplacement
     */
    createDragIndicator() {
        if (!document.getElementById('dragIndicator')) {
            const dragIndicator = document.createElement('div');
            dragIndicator.id = 'dragIndicator';
            dragIndicator.className = 'drag-indicator';
            dragIndicator.innerHTML = `
                <i class="fas fa-arrows-alt"></i>
                <span>Déplacez vers un dossier</span>
            `;
            document.body.appendChild(dragIndicator);
            this.dragIndicator = dragIndicator;
        } else {
            this.dragIndicator = document.getElementById('dragIndicator');
        }
    }

    /**
     * Configure les événements de glisser-déposer pour un élément
     */
    setupItemDragEvents(item) {
        // Rendre l'élément déplaçable
        item.setAttribute('draggable', 'true');

        // Début du déplacement
        item.addEventListener('dragstart', (e) => {
            // Stocker les données nécessaires pour le déplacement
            const data = {
                id: item.dataset.id,
                type: item.dataset.type,
                name: item.querySelector('.filemanager-item-name span').textContent
            };
            e.dataTransfer.setData('text/plain', JSON.stringify(data));

            // Appliquer la classe de style pour l'élément en cours de déplacement
            item.classList.add('dragging');

            // Enregistrer l'élément en cours de déplacement
            this.draggedItem = item;

            // Afficher l'indicateur
            this.showDragIndicator(data.name);
        });

        // Fin du déplacement
        item.addEventListener('dragend', () => {
            // Nettoyer les styles et références
            item.classList.remove('dragging');
            this.draggedItem = null;

            // Masquer l'indicateur
            this.hideDragIndicator();

            // Nettoyer les classes des zones de dépôt
            document.querySelectorAll('.drop-possible, .drop-active, .drop-forbidden').forEach(el => {
                el.classList.remove('drop-possible', 'drop-active', 'drop-forbidden');
            });
        });
    }

    /**
     * Configure les événements pour les zones de dépôt (dossiers)
     */
    setupDropZoneEvents(folder) {
        // Survol d'un dossier
        folder.addEventListener('dragover', (e) => {
            e.preventDefault();

            // Ne pas permettre de déposer un dossier dans lui-même
            if (this.draggedItem &&
                this.draggedItem.dataset.type === 'folder' &&
                this.draggedItem.dataset.id === folder.dataset.id) {
                folder.classList.add('drop-forbidden');
                return;
            }

            folder.classList.add('drop-possible');
        });

        // Entrée dans un dossier
        folder.addEventListener('dragenter', (e) => {
            e.preventDefault();

            // Ne pas permettre de déposer un dossier dans lui-même
            if (this.draggedItem &&
                this.draggedItem.dataset.type === 'folder' &&
                this.draggedItem.dataset.id === folder.dataset.id) {
                folder.classList.add('drop-forbidden');
                return;
            }

            folder.classList.add('drop-active');
        });

        // Sortie d'un dossier
        folder.addEventListener('dragleave', () => {
            folder.classList.remove('drop-active');
            folder.classList.remove('drop-forbidden');
        });

        // Dépôt sur un dossier
        folder.addEventListener('drop', (e) => {
            e.preventDefault();

            // Nettoyer les classes visuelles
            folder.classList.remove('drop-possible', 'drop-active');

            try {
                // Récupérer les données de l'élément déposé
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                const sourceId = data.id;
                const sourceType = data.type;
                const targetId = folder.dataset.id;

                // Ne pas déposer un dossier dans lui-même
                if (sourceType === 'folder' && sourceId === targetId) {
                    folder.classList.remove('drop-forbidden');
                    this.showToast('Impossible de déplacer un dossier dans lui-même', 'error');
                    return;
                }

                // Effectuer le déplacement
                this.moveItem(sourceId, sourceType, targetId);
            } catch (error) {
                console.error('Erreur lors du traitement du déplacement:', error);
            }
        });
    }

    /**
     * Affiche l'indicateur de déplacement
     */
    showDragIndicator(name) {
        if (this.dragIndicator) {
            // Mettre à jour le texte
            this.dragIndicator.querySelector('span').textContent = `Déplacer "${name.length > 15 ? name.substring(0, 15) + '...' : name}"`;

            // Afficher l'indicateur
            this.dragIndicator.classList.add('show');
        }
    }

    /**
     * Masque l'indicateur de déplacement
     */
    hideDragIndicator() {
        if (this.dragIndicator) {
            this.dragIndicator.classList.remove('show');
        }
    }

    /**
     * Déplace un élément vers un dossier de destination
     */
    moveItem(sourceId, sourceType, targetId) {
        const formData = new FormData();
        formData.append('id', sourceId);
        formData.append('destination_id', targetId);
        formData.append('type', sourceType);
        formData.append('csrf_token', this.csrfToken);

        // Recherche de l'élément source et cible pour l'animation
        const sourceItem = document.querySelector(`.filemanager-item[data-id="${sourceId}"][data-type="${sourceType}"]`);
        const targetFolder = document.querySelector(`.filemanager-item[data-id="${targetId}"][data-type="folder"]`);

        // Animation visuelle du déplacement si les deux éléments sont visibles
        if (sourceItem && targetFolder) {
            // Appliquer une animation de déplacement
            sourceItem.classList.add('moving');
            targetFolder.classList.add('receiving');

            // Ajouter une animation de disparition à l'élément source
            setTimeout(() => {
                sourceItem.classList.add('fade-out');
            }, 300);
        }

        // Envoyer la requête au serveur
        fetch('api/move.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher un message de succès
                    // Afficher un message de succès
                    this.showToast(`${sourceType === 'folder' ? 'Dossier' : 'Fichier'} déplacé avec succès`, 'success');

                    // Supprimer l'élément du DOM après l'animation
                    if (sourceItem) {
                        setTimeout(() => {
                            sourceItem.remove();

                            // Si la grille est vide, afficher le message "dossier vide"
                            if (this.filemanagerGrid && this.filemanagerGrid.children.length === 0) {
                                this.filemanagerGrid.innerHTML = `
            <div class="filemanager-empty">
                <i class="fas fa-folder-open"></i>
                <p>Ce dossier est vide</p>
            </div>
            `;
                            }
                        }, 500);
                    }
                } else {
                    // Afficher un message d'erreur
                    this.showToast(data.error || 'Une erreur est survenue lors du déplacement', 'error');

                    // Restaurer l'élément en cas d'erreur
                    if (sourceItem) {
                        sourceItem.classList.remove('fade-out', 'moving');
                    }
                    if (targetFolder) {
                        targetFolder.classList.remove('receiving');
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.showToast('Une erreur est survenue lors du déplacement', 'error');

                // Restaurer l'élément en cas d'erreur
                if (sourceItem) {
                    sourceItem.classList.remove('fade-out', 'moving');
                }
                if (targetFolder) {
                    targetFolder.classList.remove('receiving');
                }
            });
    }
}