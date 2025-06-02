<?php
// filepath: filemanager/includes/FileManager.php
class FileManager
{
    private $db;
    private $user_id;
    private $base_path;
    private $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip'];

    public function __construct($db, $user_id)
    {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->base_path = dirname(__DIR__, 2) . '/storage/users/' . $user_id . '/';

        // S'assurer que le dossier de stockage de l'utilisateur existe
        if (!file_exists($this->base_path)) {
            // Créer le dossier avec permissions complètes
            if (!mkdir($this->base_path, 0777, true)) {
                error_log("Impossible de créer le répertoire: " . $this->base_path);
            } else {
                // Ajuster les permissions pour plus de sécurité après la création
                chmod($this->base_path, 0755);
            }
        }

        // Vérifier si un dossier racine existe pour cet utilisateur
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM folders 
            WHERE parent_id IS NULL AND user_id = :user_id
        ");
        $stmt->execute(['user_id' => $this->user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Créer le dossier racine s'il n'existe pas
        if ($result['count'] == 0) {
            $stmt = $this->db->prepare("
                INSERT INTO folders (name, parent_id, user_id)
                VALUES ('Racine', NULL, :user_id)
            ");
            $stmt->execute(['user_id' => $this->user_id]);
        }
    }

    /**
     * Récupère le contenu d'un dossier
     */
    public function getFolderContents($folder_id = null)
    {
        // Si aucun dossier n'est spécifié, récupérer le dossier racine
        if ($folder_id === null || $folder_id === 0) {
            $stmt = $this->db->prepare("
                SELECT id FROM folders 
                WHERE parent_id IS NULL AND user_id = :user_id 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $this->user_id]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$folder) {
                throw new Exception("Aucun dossier racine trouvé pour cet utilisateur");
            }

            $folder_id = $folder['id'];
        }

        // Vérifier si l'utilisateur a accès à ce dossier
        $this->checkFolderAccess($folder_id);

        // Récupérer les sous-dossiers
        $stmt = $this->db->prepare("
            SELECT id, name, created_at, updated_at
            FROM folders
            WHERE parent_id = :folder_id AND user_id = :user_id
            ORDER BY name
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les fichiers
        $stmt = $this->db->prepare("
            SELECT id, name, type, size, created_at, updated_at
            FROM files
            WHERE folder_id = :folder_id AND user_id = :user_id
            ORDER BY name
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'current_folder' => $this->getFolderInfo($folder_id),
            'breadcrumb' => $this->getBreadcrumb($folder_id),
            'folders' => $folders,
            'files' => $files
        ];
    }

    /**
     * Récupère les informations d'un dossier
     */
    public function getFolderInfo($folder_id)
    {
        $stmt = $this->db->prepare("
            SELECT id, name, parent_id, created_at, updated_at
            FROM folders
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le fil d'Ariane pour un dossier
     */
    public function getBreadcrumb($folder_id)
    {
        $breadcrumb = [];
        $current = $folder_id;

        while ($current) {
            $stmt = $this->db->prepare("
                SELECT id, name, parent_id
                FROM folders
                WHERE id = :folder_id AND user_id = :user_id
            ");
            $stmt->execute([
                'folder_id' => $current,
                'user_id' => $this->user_id
            ]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$folder) break;

            // Ajouter le dossier au début du fil d'Ariane
            array_unshift($breadcrumb, $folder);
            $current = $folder['parent_id'];
        }

        return $breadcrumb;
    }

    /**
     * Crée un nouveau dossier
     */
    public function createFolder($name, $parent_id)
    {
        // Valider le nom du dossier
        $name = $this->sanitizeFileName($name);
        if (empty($name)) {
            throw new Exception("Le nom du dossier n'est pas valide");
        }

        // Vérifier si l'utilisateur a accès au dossier parent
        $this->checkFolderAccess($parent_id);

        // Vérifier si un dossier du même nom existe déjà
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM folders
            WHERE name = :name AND parent_id = :parent_id AND user_id = :user_id
        ");
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parent_id,
            'user_id' => $this->user_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception("Un dossier portant ce nom existe déjà");
        }

        // Créer le dossier dans la base de données
        $stmt = $this->db->prepare("
            INSERT INTO folders (name, parent_id, user_id)
            VALUES (:name, :parent_id, :user_id)
        ");
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parent_id,
            'user_id' => $this->user_id
        ]);

        return [
            'id' => $this->db->lastInsertId(),
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Renomme un dossier
     */
    public function renameFolder($folder_id, $new_name)
    {
        // Valider le nom du dossier
        $new_name = $this->sanitizeFileName($new_name);
        if (empty($new_name)) {
            throw new Exception("Le nom du dossier n'est pas valide");
        }

        // Vérifier si l'utilisateur a accès au dossier
        $folder = $this->checkFolderAccess($folder_id);

        // Récupérer l'ancien nom du dossier
        $old_name = $folder['name'];

        // Vérifier si un dossier du même nom existe déjà dans le même parent
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM folders
            WHERE name = :name AND parent_id = :parent_id AND user_id = :user_id AND id != :folder_id
        ");
        $stmt->execute([
            'name' => $new_name,
            'parent_id' => $folder['parent_id'],
            'user_id' => $this->user_id,
            'folder_id' => $folder_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception("Un dossier portant ce nom existe déjà");
        }

        // Renommer physiquement le dossier
        // Récupérer le chemin physique du dossier parent
        $parent_path = null;
        if ($folder['parent_id']) {
            $parent_folder = $this->getFolderById($folder['parent_id']);
            if ($parent_folder) {
                $parent_path = $this->base_path . '/' . $parent_folder['path'];
            }
        } else {
            $parent_path = $this->base_path;
        }

        // Si le parent_path a été trouvé, renommer le dossier physique
        if ($parent_path) {
            $old_path = $parent_path . '/' . $old_name;
            $new_path = $parent_path . '/' . $new_name;

            // Vérifier si le dossier physique ancien existe
            if (is_dir($old_path)) {
                // Vérifier si la nouvelle destination n'existe pas déjà
                if (file_exists($new_path)) {
                    throw new Exception("Impossible de renommer le dossier : la destination existe déjà");
                }

                // CORRECTION: Ajouter l'appel à rename() qui était manquant
                if (!rename($old_path, $new_path)) {
                    throw new Exception("Erreur lors du renommage physique du dossier");
                }

                error_log("Dossier renommé de $old_path vers $new_path");
            } else {
                error_log("Avertissement: Le dossier physique $old_path n'existe pas");

                // Créer le dossier physique s'il n'existe pas
                if (!file_exists($parent_path)) {
                    mkdir($parent_path, 0755, true);
                }

                // Créer le dossier avec le nouveau nom
                mkdir($new_path, 0755, true);
            }
        }

        // Renommer le dossier dans la base de données
        $stmt = $this->db->prepare("
            UPDATE folders
            SET name = :name
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'name' => $new_name,
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);

        // Mettre à jour le chemin dans la base de données pour ce dossier et tous les sous-dossiers
        $this->updateFolderPath($folder_id);

        return true;
    }

    /**
     * Met à jour le chemin d'un dossier et de tous ses sous-dossiers
     */
    private function updateFolderPath($folder_id)
    {
        // Récupérer les informations du dossier
        $folder = $this->getFolderById($folder_id);
        if (!$folder) {
            return false;
        }

        // Calculer le nouveau chemin
        $path = '';
        if ($folder['parent_id']) {
            $parent = $this->getFolderById($folder['parent_id']);
            if ($parent && $parent['path']) {
                $path = $parent['path'] . '/' . $folder['name'];
            } else {
                $path = $folder['name'];
            }
        } else {
            // Dossier racine
            $path = $folder['name'];
        }

        // Mettre à jour le chemin du dossier
        $stmt = $this->db->prepare("UPDATE folders SET path = :path WHERE id = :id");
        $stmt->execute(['path' => $path, 'id' => $folder_id]);

        // Récupérer tous les sous-dossiers
        $stmt = $this->db->prepare("SELECT id FROM folders WHERE parent_id = :parent_id");
        $stmt->execute(['parent_id' => $folder_id]);
        $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mettre à jour récursivement les chemins des sous-dossiers
        foreach ($subfolders as $subfolder) {
            $this->updateFolderPath($subfolder['id']);
        }

        return true;
    }

    /**
     * Déplace un dossier
     */
    public function moveFolder($folder_id, $new_parent_id)
    {
        // Vérifier si l'utilisateur a accès aux dossiers
        $folder = $this->checkFolderAccess($folder_id);
        $this->checkFolderAccess($new_parent_id);

        // Empêcher de déplacer un dossier dans lui-même ou dans un sous-dossier
        if ($folder_id == $new_parent_id) {
            throw new Exception("Impossible de déplacer un dossier dans lui-même");
        }

        // Vérifier que le nouveau parent n'est pas un sous-dossier du dossier déplacé
        if ($this->isSubfolder($folder_id, $new_parent_id)) {
            throw new Exception("Impossible de déplacer un dossier dans l'un de ses sous-dossiers");
        }

        // Vérifier si un dossier du même nom existe déjà dans le nouveau parent
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM folders
            WHERE name = :name AND parent_id = :parent_id AND user_id = :user_id AND id != :folder_id
        ");
        $stmt->execute([
            'name' => $folder['name'],
            'parent_id' => $new_parent_id,
            'user_id' => $this->user_id,
            'folder_id' => $folder_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception("Un dossier portant ce nom existe déjà dans le dossier de destination");
        }

        // Récupérer le chemin physique actuel du dossier
        $folder = $this->getFolderById($folder_id);
        $old_physical_path = $this->getPhysicalPath($folder_id, 'folder');

        // Récupérer le chemin physique du dossier de destination
        $new_parent_physical_path = $this->getPhysicalPath($new_parent_id, 'folder');
        $new_physical_path = $new_parent_physical_path . '/' . $folder['name'];

        // Vérifier si les dossiers physiques existent
        if (is_dir($old_physical_path)) {
            // Créer le dossier parent de destination s'il n'existe pas
            if (!file_exists($new_parent_physical_path)) {
                mkdir($new_parent_physical_path, 0755, true);
            }

            // Déplacer le dossier physique
            if (!rename($old_physical_path, $new_physical_path)) {
                throw new Exception("Impossible de déplacer le dossier physique");
            }
        }

        // Déplacer le dossier dans la base de données
        $stmt = $this->db->prepare("
            UPDATE folders
            SET parent_id = :parent_id
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'parent_id' => $new_parent_id,
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);

        // Mettre à jour les chemins dans la base de données
        $this->updateFolderPath($folder_id);

        return true;
    }

    /**
     * Supprime un dossier et son contenu
     */
    public function deleteFolder($folder_id)
    {
        // Vérifier si l'utilisateur a accès au dossier
        $folder = $this->checkFolderAccess($folder_id);

        // Empêcher de supprimer le dossier racine
        if ($folder['parent_id'] === null) {
            throw new Exception("Impossible de supprimer le dossier racine");
        }

        // Supprimer d'abord les fichiers physiques
        $this->deleteAllFilesInFolder($folder_id);

        // Supprimer le dossier de la base de données
        // (Les contraintes CASCADE supprimeront les sous-dossiers et fichiers)
        $stmt = $this->db->prepare("
            DELETE FROM folders
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);

        return true;
    }

    /**
     * Télécharge un fichier
     */
    public function uploadFile($file, $folder_id)
    {
        // Vérifier si l'utilisateur a accès au dossier
        $this->checkFolderAccess($folder_id);

        // Vérifier le fichier
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors du téléchargement du fichier");
        }

        // Valider l'extension du fichier
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            throw new Exception("Type de fichier non autorisé");
        }

        // Valider et sécuriser le nom du fichier
        $filename = $this->sanitizeFileName(pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $filename . '.' . $extension;

        // Vérifier si un fichier du même nom existe déjà
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM files
            WHERE name = :name AND folder_id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'name' => $filename,
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            // Ajouter un suffixe unique pour éviter les conflits
            $filename = $filename . '_' . time() . '.' . $extension;
        }

        // Créer le dossier physique si nécessaire
        $folder_path = $this->getPhysicalPath($folder_id, 'folder');
        if (!file_exists($folder_path)) {
            mkdir($folder_path, 0755, true);
        }

        // Déplacer le fichier
        $file_path = $folder_path . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Erreur lors de l'enregistrement du fichier");
        }

        // Enregistrer le fichier dans la base de données
        $stmt = $this->db->prepare("
            INSERT INTO files (name, path, type, size, folder_id, user_id)
            VALUES (:name, :path, :type, :size, :folder_id, :user_id)
        ");
        $stmt->execute([
            'name' => $filename,
            'path' => str_replace($this->base_path, '', $file_path),
            'type' => $file['type'],
            'size' => $file['size'],
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);

        return [
            'id' => $this->db->lastInsertId(),
            'name' => $filename,
            'type' => $file['type'],
            'size' => $file['size'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Renomme un fichier
     */
    public function renameFile($file_id, $new_name)
    {
        // Récupérer le fichier
        $stmt = $this->db->prepare("
            SELECT * FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception("Fichier non trouvé ou accès refusé");
        }

        // Valider le nouveau nom
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = $this->sanitizeFileName($new_name);

        if (empty($new_name)) {
            throw new Exception("Le nom du fichier n'est pas valide");
        }

        $new_full_name = $new_name . '.' . $extension;

        // Vérifier si un fichier du même nom existe déjà
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM files
            WHERE name = :name AND folder_id = :folder_id AND user_id = :user_id AND id != :file_id
        ");
        $stmt->execute([
            'name' => $new_full_name,
            'folder_id' => $file['folder_id'],
            'user_id' => $this->user_id,
            'file_id' => $file_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception("Un fichier portant ce nom existe déjà");
        }

        // Renommer le fichier physique
        $folder_path = $this->getPhysicalPath($file['folder_id'], 'folder');
        $old_path = $folder_path . '/' . $file['name'];
        $new_path = $folder_path . '/' . $new_full_name;

        if (!rename($old_path, $new_path)) {
            throw new Exception("Erreur lors du renommage du fichier");
        }

        // Mettre à jour la base de données
        $stmt = $this->db->prepare("
            UPDATE files
            SET name = :name, path = :path
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'name' => $new_full_name,
            'path' => str_replace($this->base_path, '', $new_path),
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);

        return true;
    }

    /**
     * Déplace un fichier
     */
    public function moveFile($file_id, $new_folder_id)
    {
        // Récupérer le fichier
        $stmt = $this->db->prepare("
            SELECT * FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception("Fichier non trouvé ou accès refusé");
        }

        // Vérifier si l'utilisateur a accès au nouveau dossier
        $this->checkFolderAccess($new_folder_id);

        // Vérifier si un fichier du même nom existe déjà dans le nouveau dossier
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM files
            WHERE name = :name AND folder_id = :folder_id AND user_id = :user_id AND id != :file_id
        ");
        $stmt->execute([
            'name' => $file['name'],
            'folder_id' => $new_folder_id,
            'user_id' => $this->user_id,
            'file_id' => $file_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception("Un fichier portant ce nom existe déjà dans le dossier de destination");
        }

        // Déplacer le fichier physique
        $old_folder_path = $this->getPhysicalPath($file['folder_id'], 'folder');
        $new_folder_path = $this->getPhysicalPath($new_folder_id, 'folder');

        if (!file_exists($new_folder_path)) {
            mkdir($new_folder_path, 0755, true);
        }

        $old_path = $old_folder_path . '/' . $file['name'];
        $new_path = $new_folder_path . '/' . $file['name'];

        if (!rename($old_path, $new_path)) {
            throw new Exception("Erreur lors du déplacement du fichier");
        }

        // Mettre à jour la base de données
        $stmt = $this->db->prepare("
            UPDATE files
            SET folder_id = :folder_id, path = :path
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $new_folder_id,
            'path' => str_replace($this->base_path, '', $new_path),
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);

        return true;
    }

    /**
     * Supprime un fichier
     */
    public function deleteFile($file_id)
    {
        // Récupérer le fichier
        $stmt = $this->db->prepare("
            SELECT * FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception("Fichier non trouvé ou accès refusé");
        }

        // Supprimer le fichier physique
        $folder_path = $this->getPhysicalPath($file['folder_id'], 'folder');
        $file_path = $folder_path . '/' . $file['name'];

        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Supprimer de la base de données
        $stmt = $this->db->prepare("
            DELETE FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);

        return true;
    }

    /**
     * Récupère un fichier pour affichage/téléchargement
     */
    public function getFile($file_id)
    {
        // Récupérer le fichier
        $stmt = $this->db->prepare("
            SELECT * FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception("Fichier non trouvé ou accès refusé");
        }

        // Vérifier si le chemin du fichier est valide
        if (empty($file['path']) || !is_string($file['path'])) {
            error_log("Chemin de fichier invalide pour ID: $file_id");
            return [
                'info' => $file,
                'path' => null,
                'exists' => false
            ];
        }

        // Vérifier si le fichier physique existe
        $folder_path = $this->getPhysicalPath($file['folder_id'], 'folder');
        $file_path = $folder_path . '/' . $file['name'];

        // Vérifier si le fichier physique existe
        if (!file_exists($file_path)) {
            return [
                'info' => $file,
                'path' => $file_path,
                'exists' => false
            ];
        }

        return [
            'info' => $file,
            'path' => $file_path,
            'exists' => true
        ];
    }

    /**
     * Vérifie si l'utilisateur a accès au dossier
     */
    private function checkFolderAccess($folder_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM folders
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$folder) {
            throw new Exception("Dossier non trouvé ou accès refusé");
        }

        return $folder;
    }

    /**
     * Vérifie si un dossier est un sous-dossier d'un autre
     */
    private function isSubfolder($parent_id, $child_id)
    {
        // Si les IDs sont identiques, ce n'est pas un sous-dossier
        if ($parent_id == $child_id) {
            return false;
        }

        // Récupérer le dossier parent du dossier enfant
        $stmt = $this->db->prepare("
            SELECT parent_id FROM folders
            WHERE id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $child_id,
            'user_id' => $this->user_id
        ]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si le dossier n'a pas de parent, ce n'est pas un sous-dossier
        if (!$folder || $folder['parent_id'] === null) {
            return false;
        }

        // Si le parent est le dossier recherché, c'est un sous-dossier
        if ($folder['parent_id'] == $parent_id) {
            return true;
        }

        // Vérifier récursivement
        return $this->isSubfolder($parent_id, $folder['parent_id']);
    }

    /**
     * Obtient le chemin physique d'un élément
     * 
     * @param int $id Identifiant de l'élément
     * @param string $type Type d'élément ('file' ou 'folder')
     * @return string|false Chemin physique ou false si non trouvé
     */
    public function getPhysicalPath($id, $type)
    {
        try {
            if ($type === 'file') {
                $query = "SELECT f.*, p.path AS folder_path 
                        FROM files f 
                        JOIN folders p ON f.folder_id = p.id 
                        WHERE f.id = ? AND f.user_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$id, $this->user_id]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($file) {
                    // Construire le chemin physique complet
                    return $this->base_path . '/' . $file['folder_path'] . '/' . $file['name'];
                }
            } else if ($type === 'folder') {
                // Récupérer le chemin complet du dossier
                $query = "SELECT path FROM folders WHERE id = ? AND user_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$id, $this->user_id]);
                $folder = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($folder) {
                    return $this->base_path . '/' . $folder['path'];
                }
            }

            return false;
        } catch (Exception $e) {
            error_log("Erreur getPhysicalPath: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les détails d'un fichier par son ID
     */
    public function getFileById($id)
    {
        $query = "SELECT * FROM files WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id, $this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails d'un dossier par son ID
     */
    public function getFolderById($id)
    {
        $query = "SELECT * FROM folders WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id, $this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime tous les fichiers d'un dossier récursivement
     */
    private function deleteAllFilesInFolder($folder_id)
    {
        // Récupérer tous les fichiers du dossier
        $stmt = $this->db->prepare("
            SELECT * FROM files
            WHERE folder_id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Supprimer chaque fichier physique
        $folder_path = $this->getPhysicalPath($folder_id, 'folder');
        foreach ($files as $file) {
            $file_path = $folder_path . '/' . $file['name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Récupérer tous les sous-dossiers
        $stmt = $this->db->prepare("
            SELECT id FROM folders
            WHERE parent_id = :folder_id AND user_id = :user_id
        ");
        $stmt->execute([
            'folder_id' => $folder_id,
            'user_id' => $this->user_id
        ]);
        $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Supprimer récursivement les fichiers des sous-dossiers
        foreach ($subfolders as $subfolder) {
            $this->deleteAllFilesInFolder($subfolder['id']);
        }
    }

    /**
     * Nettoie et sécurise un nom de fichier
     */
    private function sanitizeFileName($name)
    {
        // Version moins restrictive pour permettre plus de caractères
        // Supprimer les caractères spéciaux vraiment dangereux
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '', $name);

        // Limiter la longueur
        $name = substr($name, 0, 100);

        // Supprimer les espaces multiples et les espaces en début/fin
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name;
    }

    /**
     * Sécurise un nom de chemin
     */
    private function sanitizePath($path)
    {
        // Empêcher les remontées de répertoire
        $path = str_replace(['..', '/'], '', $path);
        return $path;
    }

    /**
     * Obtient l'extension à partir du type MIME
     */
    private function getExtensionFromMimeType($mime_type)
    {
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip'
        ];

        return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : '';
    }

    /**
     * Réconcilie un fichier
     */
    public function reconcileFile($file_id)
    {
        // Récupérer les infos du fichier
        $stmt = $this->db->prepare("SELECT * FROM files WHERE id = :file_id AND user_id = :user_id");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            return false; // Fichier pas trouvé dans la BD
        }

        // Construire le chemin physique
        $folder_path = $this->getPhysicalPath($file['folder_id'], 'folder');
        $file_path = $folder_path . '/' . $file['name'];

        // Si le fichier n'existe pas physiquement, le marquer comme perdu dans la BD
        if (!file_exists($file_path)) {
            $stmt = $this->db->prepare("UPDATE files SET status = 'missing' WHERE id = :file_id");
            $stmt->execute(['file_id' => $file_id]);
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur a accès à un fichier
     */
    public function userCanAccessFile($file_id)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM files
            WHERE id = :file_id AND user_id = :user_id
        ");
        $stmt->execute([
            'file_id' => $file_id,
            'user_id' => $this->user_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['count'] > 0);
    }

    /**
     * Renomme un fichier ou un dossier
     * 
     * @param int $id Identifiant de l'élément
     * @param string $newName Nouveau nom
     * @param string $type Type ('file' ou 'folder')
     * @return bool Succès ou échec
     */
    public function renameItem($id, $newName, $type)
    {
        try {
            if ($type === 'file') {
                // Pour les fichiers, on utilise la méthode existante renameFile
                return $this->renameFile($id, $newName);
            } else if ($type === 'folder') {
                // Pour les dossiers, on utilise la méthode existante renameFolder
                return $this->renameFolder($id, $newName);
            } else {
                throw new Exception("Type d'élément non reconnu: " . $type);
            }
        } catch (Exception $e) {
            error_log("Erreur renameItem: " . $e->getMessage());
            throw $e;
        }
    }
}
