/**
 * FileManager Core - Module principal
 * Initialise et coordonne tous les modules du gestionnaire de fichiers
 */

class FileManagerCore {
    constructor() {
        // Attendre que le DOM soit chargé avant d'initialiser
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initialize());
        } else {
            this.initialize();
        }
    }

    /**
     * Initialise tous les composants
     */
    initialize() {
        console.log('Initialisation du gestionnaire de fichiers');
        
        // Configuration globale
        this.config = {
            currentFolderId: parseInt(document.querySelector('#currentFolderId')?.value || 0),
            csrfToken: document.querySelector('#csrfToken')?.value || '',
            apiEndpoints: {
                preview: 'api/preview.php',
                download: 'api/download.php',
                rename: 'api/rename.php',
                delete: 'api/delete.php',
                move: 'api/move.php',
                create: 'api/create.php',
                upload: 'api/upload.php',
                folders: 'api/folders.php'
            }
        };
        
        // Éléments DOM principaux
        this.elements = {
            grid: document.getElementById('filemanagerGrid'),
            contextMenu: document.getElementById('contextMenu'),
            toastContainer: document.getElementById('toastContainer') || this.createToastContainer()
        };
        
        // Initialiser les modules
        this.contextMenu = new FileManagerContextMenu(this);
        this.dialogs = new FileManagerDialogs(this);
        this.dragDrop = new FileManagerDragDrop(this);
        this.itemEvents = new FileManagerItemEvents(this);
        this.fileOperations = new FileManagerOperations(this);
        
        // Initialiser les gestionnaires d'événements globaux
        this.setupGlobalEvents();
        
        console.log('Gestionnaire de fichiers initialisé avec succès');
    }
    
    /**
     * Crée un conteneur pour les notifications toast
     */
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    }
    
    /**
     * Configure les événements globaux
     */
    setupGlobalEvents() {
        // Échap pour fermer les boîtes de dialogue
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show, .contextual-dialog.show').forEach(el => {
                    el.classList.remove('show');
                });
            }
        });
        
        // Fermeture des fenêtres modales au clic sur l'arrière-plan
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    }
    
    /**
     * Affiche une notification toast
     */
    showToast(message, type = 'info', autoClose = true) {
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
        
        this.elements.toastContainer.appendChild(toast);
        
        // Animation d'entrée
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Fermeture
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
}

// Initialiser le gestionnaire de fichiers quand la page est chargée
document.addEventListener('DOMContentLoaded', () => {
    try {
        window.fileManager = new FileManagerCore();
    } catch (error) {
        console.error('Erreur d\'initialisation:', error);
        alert('Une erreur est survenue lors de l\'initialisation du gestionnaire de fichiers.');
    }
});
// Dans filemanager-core.js, ajouter cette ligne après l'initialisation des modules :
document.addEventListener('DOMContentLoaded', () => {
    // Vérifier si l'input de téléchargement existe
    const uploadFileInput = document.getElementById('uploadFile');
    if (uploadFileInput) {
        // Supprimer tous les gestionnaires d'événements existants
        const newUploadInput = uploadFileInput.cloneNode(true);
        uploadFileInput.parentNode.replaceChild(newUploadInput, uploadFileInput);
        
        // Ajouter un seul gestionnaire d'événements
        newUploadInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Afficher un log pour déboguer
                console.log('Téléchargement du fichier:', file.name);
                // Utiliser la fonction des opérations pour télécharger
                window.fileManager.fileOperations.uploadFile(file)
                    .finally(() => {
                        // Réinitialiser l'input
                        e.target.value = '';
                    });
            }
        });
        
        console.log('✅ Gestionnaire de téléchargement configuré');
    }
});