/**
 * FileManager Dialogs - Gestion des boîtes de dialogue
 */

class FileManagerDialogs {
    constructor(core) {
        this.core = core;
        this.dialogs = {};
    }

    /**
     * Affiche une boîte de dialogue pour confirmer la suppression
     */
    showDeleteConfirmation(id, type, name) {
        if (!this.dialogs.deleteConfirm) {
            this.createDeleteDialog();
        }

        // Mettre à jour le contenu
        document.getElementById('deleteConfirmMessage').textContent = `Êtes-vous sûr de vouloir supprimer "${name}" ?`;
        document.getElementById('deleteItemId').value = id;
        document.getElementById('deleteItemType').value = type;

        // Positionner et afficher
        const dialog = this.dialogs.deleteConfirm;
        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';
        dialog.classList.add('show');
    }

    /**
     * Crée la boîte de dialogue de suppression
     */
    createDeleteDialog() {
        const dialog = document.createElement('div');
        dialog.id = 'deleteConfirmContextual';
        dialog.className = 'contextual-dialog delete-confirm';
        dialog.innerHTML = `
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

        document.body.appendChild(dialog);
        this.dialogs.deleteConfirm = dialog;

        // Événements
        dialog.querySelector('#closeDeleteConfirm').addEventListener('click', () => {
            dialog.classList.remove('show');
        });

        dialog.querySelector('#cancelDelete').addEventListener('click', () => {
            dialog.classList.remove('show');
        });

        dialog.querySelector('#confirmDelete').addEventListener('click', () => {
            const id = document.getElementById('deleteItemId').value;
            const type = document.getElementById('deleteItemType').value;

            this.core.fileOperations.deleteItem(id, type);
            dialog.classList.remove('show');
        });

        // Clic en dehors
        document.addEventListener('click', (e) => {
            if (dialog.classList.contains('show') &&
                !dialog.contains(e.target) &&
                e.target.id !== 'confirmDelete' &&
                e.target.id !== 'cancelDelete') {
                dialog.classList.remove('show');
            }
        });
    }

    /**
     * Affiche la boîte de dialogue de renommage
     */
    showRenameDialog(item) {
        if (!this.dialogs.rename) {
            this.createRenameDialog();
        }

        const id = item.dataset.id;
        const type = item.dataset.type;
        const nameElement = item.querySelector('.filemanager-item-name span');
        let name = nameElement.textContent;

        // Traiter l'extension pour les fichiers
        let extension = '';
        if (type === 'file') {
            const nameParts = name.split('.');
            if (nameParts.length > 1) {
                extension = nameParts.pop();
                name = nameParts.join('.');
            }
        }

        // Mettre à jour le contenu
        document.getElementById('renameContextualTitle').textContent =
            `Renommer ${type === 'folder' ? 'le dossier' : 'le fichier'}`;
        document.getElementById('renameContextualItemId').value = id;
        document.getElementById('renameContextualItemType').value = type;
        document.getElementById('renameContextualExtension').value = extension;
        document.getElementById('renameContextualInput').value = name;

        // Mettre en évidence l'élément
        item.classList.add('highlight');

        // Afficher la boîte de dialogue
        const dialog = this.dialogs.rename;
        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';
        dialog.classList.add('show');

        // Focus sur le champ
        setTimeout(() => {
            const input = document.getElementById('renameContextualInput');
            input.focus();
            input.select();
        }, 50);
    }

    /**
     * Crée la boîte de dialogue de renommage
     */
    createRenameDialog() {
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
        this.dialogs.rename = dialog;

        // Événements
        const submitFn = () => this.submitRename();

        dialog.querySelector('#renameContextualInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitFn();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.hideRenameDialog();
            }
        });

        dialog.querySelector('#submitRenameContextual').addEventListener('click', submitFn);

        dialog.querySelector('#cancelRenameContextual').addEventListener('click', () => {
            this.hideRenameDialog();
        });

        dialog.querySelector('#closeRenameContextual').addEventListener('click', () => {
            this.hideRenameDialog();
        });

        // Clic en dehors
        document.addEventListener('click', (e) => {
            if (dialog.classList.contains('show') &&
                !dialog.contains(e.target) &&
                !e.target.closest('.btn-rename')) {
                this.hideRenameDialog();
            }
        });
    }

    /**
     * Cache la boîte de dialogue de renommage
     */
    hideRenameDialog() {
        if (this.dialogs.rename) {
            this.dialogs.rename.classList.remove('show');

            // Enlever la mise en évidence
            const itemId = document.getElementById('renameContextualItemId').value;
            const itemType = document.getElementById('renameContextualItemType').value;
            const item = document.querySelector(`.filemanager-item[data-id="${itemId}"][data-type="${itemType}"]`);
            if (item) {
                item.classList.remove('highlight');
            }
        }
    }

    /**
     * Soumet le formulaire de renommage en utilisant l'approche qui fonctionne
     */
    submitRename() {
        const id = document.getElementById('renameContextualItemId').value;
        const type = document.getElementById('renameContextualItemType').value;
        let newName = document.getElementById('renameContextualInput').value.trim();
    
        // Restaurer l'extension pour les fichiers
        const extension = document.getElementById('renameContextualExtension').value;
        if (type === 'file' && extension) {
            newName = `${newName}.${extension}`;
        }
    
        if (!newName) {
            this.core.showToast('Veuillez saisir un nom', 'error');
            return;
        }
    
        // Désactiver les boutons pendant le traitement
        const submitBtn = document.getElementById('submitRenameContextual');
        const cancelBtn = document.getElementById('cancelRenameContextual');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        submitBtn.disabled = true;
        cancelBtn.disabled = false; // Garder annuler actif pour l'utilisateur
    
        // Utiliser directement la requête fetch ici, sans passer par renameItem
        // qui causerait une indirection problématique
        const formData = new FormData();
        formData.append('id', id);
        formData.append('new_name', newName);
        formData.append('type', type);
        formData.append('csrf_token', this.core.config.csrfToken);
    
        // Afficher un toast de chargement
        this.core.showToast(`Renommage en cours...`, 'info', false);
    
        fetch(this.core.config.apiEndpoints.rename, {
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
            if (data.success) {
                // Trouver l'élément pour mettre à jour son nom sans recharger la page
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
                
                this.core.showToast(`${type === 'folder' ? 'Dossier' : 'Fichier'} renommé avec succès`, 'success');
                this.hideRenameDialog();
            } else {
                this.core.showToast(data.error || 'Une erreur est survenue lors du renommage', 'error');
                
                // Réactiver les boutons
                submitBtn.innerHTML = 'Renommer';
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erreur lors du renommage:', error);
            this.core.showToast('Erreur: ' + error.message, 'error');
            
            // Réactiver les boutons
            submitBtn.innerHTML = 'Renommer';
            submitBtn.disabled = false;
        });
    }
    

    /**
     * Affiche la boîte de dialogue de déplacement
     */
    showMoveDialog(id, type, name) {
        // Créer ou réutiliser la modale de déplacement
        // Similaire aux autres fonctions, mais utilisant une modale standard
    }

    /**
     * Affiche la boîte de dialogue pour créer un nouveau dossier
     */
    showNewFolderDialog() {
        if (!this.dialogs.newFolder) {
            this.createNewFolderDialog();
        }

        const dialog = this.dialogs.newFolder;
        document.getElementById('folderName').value = '';

        dialog.style.top = '50%';
        dialog.style.left = '50%';
        dialog.style.transform = 'translate(-50%, -50%)';
        dialog.classList.add('show');

        setTimeout(() => {
            document.getElementById('folderName').focus();
        }, 100);
    }

    /**
     * Crée la boîte de dialogue pour un nouveau dossier
     */
    createNewFolderDialog() {
        // Créer un formulaire simple et robuste
        const dialog = document.createElement('div');
        dialog.id = 'newFolderDialog';
        dialog.className = 'contextual-dialog';
        dialog.innerHTML = `
            <div class="contextual-dialog-content">
                <div class="contextual-dialog-header">
                    <h3>Nouveau dossier</h3>
                    <button type="button" class="contextual-dialog-close" id="closeNewFolderDialog">&times;</button>
                </div>
                <div class="contextual-dialog-body">
                    <!-- Formulaire simplifié -->
                    <div class="form-group">
                        <label for="folderName">Nom du dossier:</label>
                        <input type="text" id="folderName" class="form-control" autocomplete="off">
                        <div id="folderNameHelp" class="form-text mt-1"></div>
                    </div>
                </div>
                <div class="contextual-dialog-footer">
                    <button type="button" id="cancelNewFolder" class="btn btn-secondary">Annuler</button>
                    <button type="button" id="submitNewFolder" class="btn btn-primary">Créer le dossier</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        this.dialogs.newFolder = dialog;
        
        // Récupérer les références
        const folderNameInput = dialog.querySelector('#folderName');
        const folderNameHelp = dialog.querySelector('#folderNameHelp');
        const submitButton = dialog.querySelector('#submitNewFolder');
        
        // Validation en temps réel
        folderNameInput.addEventListener('input', () => {
            const value = folderNameInput.value.trim();
            
            if (value) {
                folderNameHelp.textContent = `Nom valide: "${value}"`;
                folderNameHelp.style.color = 'green';
                submitButton.disabled = false;
            } else {
                folderNameHelp.textContent = 'Veuillez saisir un nom';
                folderNameHelp.style.color = 'red';
                submitButton.disabled = true;
            }
        });
        
        // Focus sur le champ
        setTimeout(() => folderNameInput.focus(), 100);
        
        // Soumission par Enter
        folderNameInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter' && folderNameInput.value.trim()) {
                e.preventDefault();
                this.createNewFolder();
            }
        });
        
        // Événements des boutons
        dialog.querySelector('#closeNewFolderDialog').addEventListener('click', () => {
            this.closeDialog('newFolder');
        });
        
        dialog.querySelector('#cancelNewFolder').addEventListener('click', () => {
            this.closeDialog('newFolder');
        });
        
        dialog.querySelector('#submitNewFolder').addEventListener('click', () => {
            this.createNewFolder();
        });
        
        // Afficher la boîte de dialogue
        dialog.style.display = 'flex';
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

        this.core.fileOperations.createFolder(folderName);
    }

    /**
     * Ferme une boîte de dialogue
     */
    closeDialog(dialogName) {
        if (this.dialogs[dialogName]) {
            this.dialogs[dialogName].classList.remove('show');
        }
    }
}