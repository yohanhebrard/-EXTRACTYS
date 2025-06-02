<?php
require_once '../init.php';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les entrées
    $email_username = trim($_POST['email_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Vérifier si $db est défini
    if (!isset($db) || $db === null) {
        die("Erreur : La connexion à la base de données n'est pas disponible.");
    }

    // Créer une instance de Auth avec la connexion à la base de données
    $auth = new Auth($db);

    // Tentative de connexion
    $loginResult = $auth->login($email_username, $password, $remember);

    if ($loginResult === 'require_2fa') {
        // L'utilisateur doit vérifier avec 2FA
        header('Location: verify-2fa.php');
        exit;
    } else if ($loginResult === 'require_setup_2fa') {
        // L'utilisateur doit configurer 2FA
        header('Location: setup-2fa.php');
        exit;
    } else if ($loginResult === false) {
        $error_message = "Identifiants incorrects. Veuillez réessayer.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | EXTRACTYS</title>
    <!-- Fichiers CSS de base -->
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/buttons.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <!-- Fichier CSS spécifique à la page -->
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/login-layout.css">
    <!-- Responsive toujours en dernier -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="login-background">
        <div class="login-background-pattern"></div>
        <div class="login-background-circles"></div>
    </div>

    <div class="auth-container">
        <div class="auth-card fade-in">
            <div class="auth-header">
                <div class="auth-card-gradient"></div>
                <div class="auth-logo">
                    <img src="../assets/img/extractys.png" alt="EXTRACTYS" class="logo">
                </div>
                <h1 class="auth-title">Connexion</h1>
                <p class="auth-subtitle">Accédez à votre compte sécurisé</p>
            </div>

            <div class="auth-body">
                <?php if (isset($error_message)): ?>
                    <div class="error">
                        <div class="error-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <div class="error-message">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" id="loginForm" class="login-form">
                    <div class="form-group">
                        <label for="email_username" class="form-label">Email ou nom d'utilisateur</label>
                        <div class="form-control-with-icon">
                            <input type="text" name="email_username" id="email_username" class="form-control" required autofocus>
                            <span class="form-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="form-control-with-icon password-input-wrapper">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <span class="form-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <button type="button" id="togglePassword" class="password-toggle" aria-label="Afficher/masquer le mot de passe">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">Se souvenir de moi</label>
                        </div>
                        <a href="reset-password.php" class="btn-link">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-ripple">
                        <span class="btn-text">Se connecter</span>
                        <span class="btn-icon-right">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path d="m12 5 7 7-7 7"></path>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <div class="security-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Connexion sécurisée avec 2FA</span>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/validation.js"></script>
    <script>
        // Fonction pour basculer l'affichage du mot de passe
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Changer l'icône
            this.innerHTML = type === 'password' ?
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' :
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        });
    </script>
</body>

</html>