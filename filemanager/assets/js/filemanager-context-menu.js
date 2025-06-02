/**
 * FileManager Context Menu - Gestion du menu contextuel
 */

class FileManagerContextMenu {
    constructor(core) {
        this.core = core;
        this.activeItem = null;
        this.contextMenu = core.elements.contextMenu;
        
        // Créer le menu contextuel s'il n'existe pas
        if (!this.contextMenu) {
            this.createContextMenu();
        }
        
        this.setupEventListeners();
    }
    
    /**
     * Crée le menu contextuel s'il n'existe pas
     */
    createContextMenu() {
        const menu = document.createElement('div');
        menu.id = 'contextMenu';
        menu.className = 'context-menu';
        menu.innerHTML = `
            <ul>
                <li data-action="open" class="context-menu-folder"><i class="fas fa-folder-open"></i> Ouvrir</li>
                <li data-action="view" class="context-menu-file"><i class="fas fa-eye"></i> Visualiser</li>
                <li data-action="download" class="context-menu-file"><i class="fas fa-download"></i> Télécharger</li>
                <li data-action="rename"><i class="fas fa-pen"></i> Renommer</li>
                <li data-action="move"><i class="fas fa-cut"></i> Déplacer</li>
                <li data-action="delete" class="context-menu-danger"><i class="fas fa-trash"></i> Supprimer</li>
            </ul>
        `;
        document.body.appendChild(menu);
        this.contextMenu = menu;
        this.core.elements.contextMenu = menu;
    }
    
    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Clic droit pour afficher le menu
        document.addEventListener('contextmenu', (e) => {
            const item = e.target.closest('.filemanager-item');
            if (!item) {
                this.hide();
                return;
            }
            
            e.preventDefault();
            this.show(item, e.pageX, e.pageY);
        });
        
        // Clic normal pour masquer le menu
        document.addEventListener('click', () => {
            this.hide();
        });
        
        // Actions du menu
        this.contextMenu.querySelectorAll('li').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                if (!this.activeItem) return;
                
                const action = item.dataset.action;
                const id = this.activeItem.dataset.id;
                const type = this.activeItem.dataset.type;
                const name = this.activeItem.querySelector('.filemanager-item-name span').textContent;
                
                this.executeAction(action, id, type, name);
            });
        });
    }
    
    /**
     * Affiche le menu contextuel
     */
    show(item, x, y) {
        this.activeItem = item;
        
        // Afficher/masquer les options selon le type
        const type = item.dataset.type;
        this.contextMenu.querySelectorAll('.context-menu-folder').forEach(el => {
            el.style.display = type === 'folder' ? 'block' : 'none';
        });
        this.contextMenu.querySelectorAll('.context-menu-file').forEach(el => {
            el.style.display = type === 'file' ? 'block' : 'none';
        });
        
        // Optimiser la position
        this.contextMenu.style.left = `${x}px`;
        this.contextMenu.style.top = `${y}px`;
        
        // S'assurer que le menu est dans les limites de l'écran
        setTimeout(() => {
            const rect = this.contextMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (rect.right > windowWidth) {
                this.contextMenu.style.left = `${windowWidth - rect.width - 10}px`;
            }
            
            if (rect.bottom > windowHeight) {
                this.contextMenu.style.top = `${y - rect.height}px`;
            }
        }, 0);
        
        this.contextMenu.classList.add('show');
        
        // Animations des éléments
        this.contextMenu.querySelectorAll('li').forEach((item, index) => {
            item.style.animation = 'none';
            item.offsetHeight; // Force reflow
            item.style.animation = `slideIn 0.2s forwards ${index * 0.05}s`;
        });
    }
    
    /**
     * Cache le menu contextuel
     */
    hide() {
        if (this.contextMenu) {
            this.contextMenu.classList.remove('show');
        }
    }
    
    /**
     * Exécute l'action sélectionnée
     */
    executeAction(action, id, type, name) {
        switch (action) {
            case 'open':
                if (type === 'folder') {
                    window.location.href = `index.php?folder=${id}`;
                }
                break;
                
            case 'view':
                if (type === 'file') {
                    this.core.fileOperations.previewFile(id);
                }
                break;
                
            case 'download':
                if (type === 'file') {
                    const url = `${this.core.config.apiEndpoints.download}?id=${id}&csrf_token=${this.core.config.csrfToken}`;
                    window.location.href = url;
                }
                break;
                
            case 'rename':
                this.core.dialogs.showRenameDialog(this.activeItem);
                break;
                
            case 'move':
                if (type === 'folder') {
                    this.core.showToast('Utilisez le glisser-déposer pour déplacer le dossier', 'info');
                } else {
                    this.core.dialogs.showMoveDialog(id, type, name);
                }
                break;
                
            case 'delete':
                this.core.dialogs.showDeleteConfirmation(id, type, name);
                break;
        }
        
        this.hide();
    }
}