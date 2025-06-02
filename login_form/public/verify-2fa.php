<?php
require_once '../init.php';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Déboguer l'état actuel
error_log('verify-2fa.php - Session state: ' . print_r($_SESSION, true));

// Si l'utilisateur est déjà complètement connecté, rediriger vers index
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    error_log('User already fully logged in, redirecting to index.php');
    header('Location: /../EXTRACTYS/filemanager/index.php');
    exit;
}

// Vérifier si l'utilisateur est dans le processus de vérification 2FA
if (!isset($_SESSION['awaiting_2fa']) || $_SESSION['awaiting_2fa'] !== true || !isset($_SESSION['user_id'])) {
    error_log('User not in 2FA verification process, redirecting to login.php');
    header('Location: login.php');
    exit;
}

$auth = new Auth($db);
$twoFactor = $auth->getTwoFactorAuth();

// Récupérer le secret de l'utilisateur depuis la base de données
$secret = $twoFactor->getSecret($_SESSION['user_id']);

if (!$secret) {
    // Aucun secret trouvé, rediriger vers la configuration
    error_log('No 2FA secret found, setting up 2FA');
    $_SESSION['setup_2fa'] = true;
    unset($_SESSION['awaiting_2fa']);
    header('Location: setup-2fa.php');
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    // Vérifier si le code est valide
    if ($twoFactor->verifyCode($secret, $code)) {
        // Le code est valide, compléter la connexion
        $remember = isset($_SESSION['remember_me']) ? $_SESSION['remember_me'] : false;
        unset($_SESSION['remember_me']);
        unset($_SESSION['awaiting_2fa']);

        $auth->completeLogin($remember);

        error_log('2FA verification successful, completing login');

        // Rediriger vers la page d'accueil
        header('Location: /../EXTRACTYS/filemanager/index.php');
        exit;
    } else {
        $error = "Code de vérification incorrect. Veuillez réessayer.";
        error_log('Invalid 2FA code provided');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification à deux facteurs | EXTRACTYS</title>
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
    <!-- CSS spécifique à la vérification 2FA -->
    <style>
        .verification-code {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .verification-code input {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .verification-code input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.25);
            transform: translateY(-2px);
        }

        .verification-code input.filled {
            background-color: rgba(78, 115, 223, 0.1);
            border-color: var(--primary);
        }

        .verification-instructions {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }

        .verification-icon {
            font-size: 48px;
            margin-bottom: var(--spacing-md);
            color: var(--primary);
        }

        .countdown-container {
            margin-top: var(--spacing-md);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .countdown-bar {
            height: 4px;
            width: 100%;
            max-width: 200px;
            background-color: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
            margin-top: var(--spacing-sm);
        }

        .countdown-progress {
            height: 100%;
            background-color: var(--primary);
            width: 100%;
            animation: countdown 30s linear forwards;
        }

        @keyframes countdown {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        .cancel-link {
            display: block;
            text-align: center;
            margin-top: var(--spacing-lg);
        }
    </style>
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
                <h1 class="auth-title">Authentification 2FA</h1>
                <p class="auth-subtitle">Vérification de sécurité</p>
            </div>

            <div class="auth-body">
                <?php if (isset($error)): ?>
                    <div class="error">
                        <div class="error-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <div class="error-message">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="verification-instructions">
                    <div class="verification-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <p>Veuillez entrer le code à 6 chiffres généré par votre application d'authentification.</p>
                </div>

                <form method="POST" action="verify-2fa.php" id="verifyForm">
                    <div class="verification-code">
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="code-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="hidden" name="code" id="code" value="">
                    </div>

                    <div class="countdown-container">
                        <span>Validité du code:</span>
                        <div class="countdown-bar">
                            <div class="countdown-progress"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-ripple mt-4">
                        <span class="btn-text">Vérifier</span>
                        <span class="btn-icon-right">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path d="m12 5 7 7-7 7"></path>
                            </svg>
                        </span>
                    </button>
                </form>

                <a href="logout.php" class="btn-link cancel-link">Annuler et se déconnecter</a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const codeDigits = document.querySelectorAll('.code-digit');
            const codeInput = document.getElementById('code');
            const form = document.getElementById('verifyForm');

            // Fonction pour mettre à jour le champ caché avec tous les chiffres
            function updateHiddenInput() {
                let code = '';
                codeDigits.forEach(input => {
                    code += input.value || '';
                });
                codeInput.value = code;
            }

            // Ajouter des écouteurs d'événements à chaque champ de chiffre
            codeDigits.forEach((input, index) => {
                // Focus sur ce champ
                input.addEventListener('focus', function() {
                    this.select();
                });

                // Gérer la saisie de caractères
                input.addEventListener('input', function() {
                    updateHiddenInput();
                    this.value = this.value.replace(/[^0-9]/g, '');

                    if (this.value) {
                        this.classList.add('filled');

                        // Passer au champ suivant si disponible
                        if (index < codeDigits.length - 1 && this.value) {
                            codeDigits[index + 1].focus();
                        }
                    } else {
                        this.classList.remove('filled');
                    }
                });

                // Déplacement avec les touches (flèches, backspace)
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        // Si backspace et champ vide, retourner au précédent
                        codeDigits[index - 1].focus();
                    } else if (e.key === 'ArrowLeft' && index > 0) {
                        // Flèche gauche
                        codeDigits[index - 1].focus();
                    } else if (e.key === 'ArrowRight' && index < codeDigits.length - 1) {
                        // Flèche droite
                        codeDigits[index + 1].focus();
                    }
                });
            });

            // Valider que tous les champs sont remplis avant soumission
            form.addEventListener('submit', function(e) {
                let isValid = true;
                let code = '';

                codeDigits.forEach(input => {
                    if (!input.value) {
                        isValid = false;
                    }
                    code += input.value || '';
                });

                if (!isValid || code.length !== 6) {
                    e.preventDefault();
                    alert('Veuillez saisir un code à 6 chiffres complet.');
                } else {
                    codeInput.value = code;
                }
            });

            // Focus sur le premier champ au chargement
            codeDigits[0].focus();
        });
    </script>
</body>

</html>