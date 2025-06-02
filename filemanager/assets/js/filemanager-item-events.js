/**
 * FileManager Item Events - Gestion des événements sur les éléments
 */

class FileManagerItemEvents {
    constructor(core) {
        this.core = core;
        
        this.setupItems();
        this.setupNewFolderButton();
        this.setupFileUpload();
    }
    
    /**
     * Configure les événements pour tous les éléments
     */
    setupItems() {
        const grid = this.core.elements.grid;
        if (!grid) return;
        
        // Double-clic pour ouvrir un dossier ou prévisualiser un fichier
        grid.addEventListener('dblclick', (e) => {
            const item = e.target.closest('.filemanager-item');
            if (!item) return;
            
            const type = item.dataset.type;
            const id = item.dataset.id;
            
            if (type === 'folder') {
                window.location.href = `index.php?folder=${id}`;
            } else if (type === 'file') {
                this.core.fileOperations.previewFile(id);
            }
        });
        
        // Configurer chaque élément individuellement
        document.querySelectorAll('.filemanager-item').forEach(item => {
            this.setupItemEvents(item);
        });
    }
    
    /**
     * Configure les événements pour un élément spécifique
     */
    setupItemEvents(item) {
        // Bouton de renommage
        const renameBtn = item.querySelector('.btn-rename');
        if (renameBtn) {
            // Supprimer les écouteurs d'événements existants
            const newRenameBtn = renameBtn.cloneNode(true);
            renameBtn.parentNode.replaceChild(newRenameBtn, renameBtn);
            
            // Ajouter un nouvel écouteur avec stopPropagation
            newRenameBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('🖊️ Clic sur le bouton renommer pour:', item.dataset.id, item.dataset.type);
                
                // Vérifier que la méthode existe
                if (this.core.dialogs && typeof this.core.dialogs.showRenameDialog === 'function') {
                    this.core.dialogs.showRenameDialog(item);
                } else {
                    console.error('❌ Méthode showRenameDialog non trouvée');
                }
            });
        }
        
        // Bouton de suppression (laissez le code existant)
        const deleteBtn = item.querySelector('.btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const name = item.querySelector('.filemanager-item-name span').textContent;
                this.core.dialogs.showDeleteConfirmation(item.dataset.id, item.dataset.type, name);
            });
        }
    }
    
    /**
     * Configure le bouton de création de dossier
     */
    setupNewFolderButton() {
        const btnNewFolder = document.getElementById('btnNewFolder');
        if (btnNewFolder) {
            btnNewFolder.addEventListener('click', () => {
                this.core.dialogs.showNewFolderDialog();
            });
        }
    }
    
    /**
     * Configure l'input de téléchargement de fichier
     */
    setupFileUpload() {
        const uploadFile = document.getElementById('uploadFile');
        if (uploadFile) {
            uploadFile.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.core.fileOperations.uploadFile(file)
                        .finally(() => {
                            // Réinitialiser l'input
                            e.target.value = '';
                        });
                }
            });
        }
    }
}