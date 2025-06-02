/**
 * FileManager DragDrop - Gestion du glisser-déposer
 */

class FileManagerDragDrop {
    constructor(core) {
        this.core = core;
        this.draggedItem = null;
        this.dragIndicator = null;
        
        this.createDragIndicator();
        this.setupDragDrop();
    }
    
    /**
     * Crée l'indicateur de glisser-déposer
     */
    createDragIndicator() {
        if (!document.getElementById('dragIndicator')) {
            const indicator = document.createElement('div');
            indicator.id = 'dragIndicator';
            indicator.className = 'drag-indicator';
            indicator.innerHTML = `
                <i class="fas fa-arrows-alt"></i>
                <span>Déplacez vers un dossier</span>
            `;
            document.body.appendChild(indicator);
            this.dragIndicator = indicator;
        } else {
            this.dragIndicator = document.getElementById('dragIndicator');
        }
    }
    
    /**
     * Configure les événements de glisser-déposer
     */
    setupDragDrop() {
        // Configurer les éléments existants
        document.querySelectorAll('.filemanager-item').forEach(item => {
            this.setupItemDragEvents(item);
        });
        
        // Configurer les zones de dépôt
        document.querySelectorAll('.filemanager-item.folder').forEach(folder => {
            this.setupDropZoneEvents(folder);
        });
        
        // Pour les éléments qui seraient ajoutés dynamiquement
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1 && node.classList.contains('filemanager-item')) {
                            this.setupItemDragEvents(node);
                            
                            if (node.classList.contains('folder')) {
                                this.setupDropZoneEvents(node);
                            }
                        }
                    });
                }
            });
        });
        
        if (this.core.elements.grid) {
            observer.observe(this.core.elements.grid, { childList: true });
        }
    }
    
    /**
     * Configure les événements pour un élément déplaçable
     */
    setupItemDragEvents(item) {
        item.setAttribute('draggable', 'true');
        
        // Début du déplacement
        item.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            
            // Stocker les informations sur l'élément
            const data = {
                id: item.dataset.id,
                type: item.dataset.type,
                name: item.querySelector('.filemanager-item-name span').textContent
            };
            
            e.dataTransfer.setData('text/plain', JSON.stringify(data));
            
            // Appliquer des styles
            item.classList.add('dragging');
            
            // Enregistrer l'élément
            this.draggedItem = item;
            
            // Afficher l'indicateur
            this.showDragIndicator(data.name);
        });
        
        // Fin du déplacement
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            this.draggedItem = null;
            this.hideDragIndicator();
            
            // Nettoyer les classes des zones de dépôt
            document.querySelectorAll('.drop-possible, .drop-active, .drop-forbidden').forEach(el => {
                el.classList.remove('drop-possible', 'drop-active', 'drop-forbidden');
            });
        });
    }
    
    /**
     * Configure les événements pour une zone de dépôt
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
        
        // Dépôt dans un dossier
        folder.addEventListener('drop', (e) => {
            e.preventDefault();
            
            // Nettoyer les styles
            folder.classList.remove('drop-possible', 'drop-active');
            
            try {
                // Récupérer les données
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                const sourceId = data.id;
                const sourceType = data.type;
                const targetId = folder.dataset.id;
                
                // Ne pas déposer un dossier dans lui-même
                if (sourceType === 'folder' && sourceId === targetId) {
                    folder.classList.remove('drop-forbidden');
                    this.core.showToast('Impossible de déplacer un dossier dans lui-même', 'error');
                    return;
                }
                
                // Effectuer le déplacement
                this.core.fileOperations.moveItem(sourceId, sourceType, targetId);
            } catch (error) {
                console.error('Erreur lors du traitement du déplacement:', error);
                this.core.showToast('Une erreur est survenue lors du déplacement', 'error');
            }
        });
    }
    
    /**
     * Affiche l'indicateur de déplacement
     */
    showDragIndicator(name) {
        if (this.dragIndicator) {
            // Limiter la longueur du nom
            const displayName = name.length > 15 ? name.substring(0, 15) + '...' : name;
            this.dragIndicator.querySelector('span').textContent = `Déplacer "${displayName}"`;
            
            // Afficher avec animation
            this.dragIndicator.classList.add('show');
        }
    }
    
    /**
     * Cache l'indicateur de déplacement
     */
    hideDragIndicator() {
        if (this.dragIndicator) {
            this.dragIndicator.classList.remove('show');
        }
    }
}