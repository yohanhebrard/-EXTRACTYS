<?php
require_once '../../init.php';
require_once '../../../filemanager/includes/functions.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'utilisateur est admin
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    // Rediriger vers la page d'accueil si ce n'est pas un admin
    header('Location: ../index.php');
    exit;
}

// Traitement des actions CRUD
$message = '';
$error = '';

// Création d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Jeton CSRF invalide';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Validation basique
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Tous les champs sont requis';
        } else {
            try {
                // Vérifier si l'utilisateur existe déjà
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);

                if ($stmt->rowCount() > 0) {
                    $error = 'Cet email ou nom d\'utilisateur existe déjà';
                } else {
                    // Hachage du mot de passe
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insertion du nouvel utilisateur
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, role, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");

                    $stmt->execute([$username, $email, $hashed_password, $role]);

                    $message = 'Utilisateur créé avec succès';
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la création de l\'utilisateur: ' . $e->getMessage();
            }
        }
    }
}

// Suppression d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Jeton CSRF invalide';
    } else {
        $id = intval($_POST['user_id']);

        // Empêcher de supprimer son propre compte
        if ($id === $user_id) {
            $error = 'Vous ne pouvez pas supprimer votre propre compte';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);

                $message = 'Utilisateur supprimé avec succès';
            } catch (Exception $e) {
                $error = 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage();
            }
        }
    }
}

// Récupérer tous les utilisateurs
$stmt = $db->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY id");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Générer un token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - EXTRACTYS</title>

    <!-- Fichiers CSS de base -->
    <link rel="stylesheet" href="../../assets/css/reset.css">
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/layout.css">
    <link rel="stylesheet" href="../../assets/css/components.css">
    <link rel="stylesheet" href="../../assets/css/buttons.css">
    <link rel="stylesheet" href="../../assets/css/animations.css">

    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Styles pour la page d'administration -->
    <style>
        .users-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            margin-bottom: 20px;
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .card-title {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }

        .card-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: #fff;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .users-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .users-table tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-admin {
            background-color: #4a6cf7;
            color: white;
        }

        .badge-user {
            background-color: #6c757d;
            color: white;
        }

        .action-btn {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }
    </style>

    <!-- Styles améliorés pour la page d'administration -->
    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            background-color: #f5f7fa;
            color: #444;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }

        .app-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .app-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .app-header-title h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .app-header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 8px 16px;
            transition: var(--transition);
            border: none;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
        }

        .btn-light {
            background-color: var(--light-color);
            color: var(--dark-color);
            border: 1px solid #e0e0e0;
        }

        .btn-light:hover {
            background-color: #e2e6ea;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #3a5bd9;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .card {
            border-radius: 10px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            overflow: hidden;
            margin-bottom: 30px;
            border: none;
        }

        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: white;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .card-body {
            padding: 25px;
            background-color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .form-control,
        .form-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
            outline: none;
        }

        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .users-table th,
        .users-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .users-table th {
            background-color: #f9fafc;
            font-weight: 600;
            color: var(--dark-color);
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .users-table tr:hover td {
            background-color: #f9fafc;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background-color: var(--primary-color);
            color: white;
        }

        .badge-user {
            background-color: var(--secondary-color);
            color: white;
        }

        .action-btn {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: var(--transition);
        }

        .btn-delete {
            background-color: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

    <!-- Responsive toujours en dernier -->
    <link rel="stylesheet" href="../../assets/css/responsive.css">
</head>

<body>
    <div class="app-container">
        <header class="app-header">

            <div class="app-header-title">
                <h1>Administration des utilisateurs</h1>
            </div>
            <div class="app-header-actions">
                <a href="../../../filemanager/index.php" class="btn btn-light btn-sm">
                    <i class="fas fa-folder"></i> Gestionnaire de fichiers
                </a>
                <a href="../logout.php" class="btn btn-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </header>

        <main class="app-content">
            <div class="users-container">
                <h2 class="page-title">Gestion des utilisateurs</h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ajouter un nouvel utilisateur</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="form-group">
                                <label for="username">Nom d'utilisateur</label>
                                <input type="text" id="username" name="username" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Mot de passe</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="user">Utilisateur</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Créer l'utilisateur
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Liste des utilisateurs</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <p>Aucun utilisateur trouvé.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="users-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom d'utilisateur</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                                        <?php echo $user['role'] === 'admin' ? 'Administrateur' : 'Utilisateur'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($user['id'] !== $user_id): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="action-btn btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                                <i class="fas fa-trash"></i> Supprimer
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted"><i class="fas fa-user-check"></i> Votre compte</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>