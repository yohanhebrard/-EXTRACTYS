<?php
require_once '../init.php';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/QRCode.php';

// Si l'utilisateur est déjà complètement connecté, rediriger vers index
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ../filemanager/index.php');
    exit;
}

// Vérifier si l'utilisateur est dans le processus de configuration 2FA
if (!isset($_SESSION['setup_2fa']) || $_SESSION['setup_2fa'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$auth = new Auth($db);
$twoFactor = $auth->getTwoFactorAuth();

// Générer un secret pour l'utilisateur s'il n'existe pas déjà en session
if (!isset($_SESSION['temp_2fa_secret'])) {
    $secret = $twoFactor->generateSecret($_SESSION['user_id'], $_SESSION['username']);
} else {
    $secret = $_SESSION['temp_2fa_secret'];
}

// Obtenir l'URL de provisionnement pour créer le QR code
$provisioningUrl = $twoFactor->getProvisioningUrl($_SESSION['username'], $secret);

// Générer l'URL du QR code
$qrUrl = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($provisioningUrl);

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    // Vérifier si le code est valide
    if ($twoFactor->verifyCode($secret, $code)) {
        // Le code est valide, activer la 2FA pour l'utilisateur
        if ($twoFactor->enable($_SESSION['user_id'], $secret)) {
            // Supprimer les variables de session temporaires
            unset($_SESSION['setup_2fa']);
            unset($_SESSION['temp_2fa_secret']);

            // Compléter la connexion
            $remember = isset($_SESSION['remember_me']) ? $_SESSION['remember_me'] : false;
            unset($_SESSION['remember_me']);

            $auth->completeLogin($remember);

            // Rediriger vers la page d'accueil
            header('Location: ../filemanager/index.php');
            exit;
        } else {
            $error = "Erreur lors de l'activation de l'authentification à deux facteurs. Veuillez réessayer.";
        }
    } else {
        $error = "Code de vérification incorrect. Veuillez réessayer.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration 2FA | EXTRACTYS</title>
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
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>

    <!-- CSS spécifique à la configuration 2FA -->
    <style>
        .qrcode-container {
            display: flex;
            justify-content: center;
            margin: var(--spacing-xl) 0;
            perspective: 1000px;
        }

        #qrcode {
            border: 10px solid white;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.5s ease;
            transform-style: preserve-3d;
            transform: rotateY(0deg);
        }

        #qrcode:hover {
            transform: rotateY(3deg) scale(1.05);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
        }

        .setup-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .setup-instructions {
            margin-bottom: var(--spacing-xl);
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.5);
            border-radius: var(--border-radius);
            padding: var(--spacing-md);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .setup-instructions h3 {
            font-size: var(--font-size-lg);
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .setup-instructions h3 svg {
            color: var(--primary);
        }

        .setup-instructions ol {
            padding-left: var(--spacing-xl);
            margin-top: var(--spacing-lg);
            counter-reset: step-counter;
        }

        .setup-instructions li {
            margin-bottom: var(--spacing-lg);
            position: relative;
            padding-left: var(--spacing-sm);
            counter-increment: step-counter;
        }

        .setup-instructions li::before {
            content: counter(step-counter);
            position: absolute;
            left: -30px;
            top: -2px;
            background-color: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: var(--font-size-sm);
        }

        .setup-instructions small {
            font-size: var(--font-size-xs);
            color: var(--text-light);
            display: inline-block;
            margin-top: var(--spacing-xs);
        }

        .app-links {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-sm);
        }

        .app-link {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            background: white;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: 20px;
            font-size: var(--font-size-xs);
            font-weight: 500;
            color: var(--text-primary);
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .app-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            color: var(--primary);
            text-decoration: none;
        }

        .app-link svg {
            width: 16px;
            height: 16px;
        }

        .manual-setup {
            background-color: rgba(255, 255, 255, 0.8);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            margin: var(--spacing-xl) 0;
            border-left: 4px solid var(--primary-light);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .manual-setup:hover {
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        .manual-setup p {
            margin-bottom: var(--spacing-md);
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
        }

        .manual-setup-header {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-md);
            gap: var(--spacing-sm);
        }

        .manual-setup-header svg {
            color: var(--primary);
        }

        .manual-setup-header h4 {
            font-weight: 600;
            font-size: var(--font-size-md);
            color: var(--text-primary);
            margin: 0;
        }

        .secret-key {
            display: block;
            font-family: monospace;
            font-size: var(--font-size-lg);
            letter-spacing: 2px;
            text-align: center;
            padding: var(--spacing-md);
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-top: var(--spacing-md);
            user-select: all;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .secret-key:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .secret-key::after {
            content: 'Cliquez pour copier';
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: var(--font-size-xs);
            color: var(--text-light);
            opacity: 0;
            transition: opacity 0.3s ease;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: 4px;
            pointer-events: none;
        }

        .secret-key:hover::after {
            opacity: 1;
        }

        .secret-key.copied::after {
            content: 'Copié !';
            background: var(--success);
            opacity: 1;
        }

        .verification-code {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .verification-code input {
            width: 180px;
            height: 54px;
            text-align: center;
            font-size: var(--font-size-xl);
            font-weight: bold;
            letter-spacing: 4px;
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

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-xl);
            background: rgba(255, 255, 255, 0.5);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-sm);
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 33%;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 16px;
            right: -50%;
            width: 100%;
            height: 3px;
            background-color: var(--border-color);
            z-index: 1;
            transition: background-color 0.3s ease;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--background);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-sm);
            font-weight: 600;
            color: var(--text-light);
            z-index: 2;
            transition: all 0.3s ease;
        }

        .step-text {
            font-size: var(--font-size-xs);
            color: var(--text-light);
            text-align: center;
            line-height: 1.3;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(78, 115, 223, 0.25);
        }

        .step.active .step-text {
            color: var(--primary);
            font-weight: 500;
        }

        .step.completed .step-number {
            background-color: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step.completed .step-text {
            color: var(--success);
        }

        .step.completed:not(:last-child)::after {
            background-color: var(--success);
        }

        .action-button {
            margin-top: var(--spacing-lg);
        }

        .action-button .btn-primary {
            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.4);
        }

        /* Styles responsives pour mobile */
        @media (max-width: 576px) {
            .step-text {
                display: none;
            }

            .app-links {
                flex-direction: column;
                gap: var(--spacing-xs);
            }

            .secret-key {
                font-size: var(--font-size-md);
                letter-spacing: 1px;
                padding: var(--spacing-sm);
            }

            #qrcode {
                border-width: 6px;
                width: 180px !important;
                height: 180px !important;
            }

            .verification-code input {
                width: 140px;
            }

            .setup-instructions ol {
                padding-left: var(--spacing-lg);
            }

            .setup-instructions li {
                margin-bottom: var(--spacing-md);
            }

            .setup-instructions li::before {
                left: -25px;
                width: 20px;
                height: 20px;
                font-size: 11px;
            }
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
                <h1 class="auth-title">Configuration 2FA</h1>
                <p class="auth-subtitle">Sécurisez votre compte</p>
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

                <div class="step-indicator">
                    <div class="step completed">
                        <div class="step-number">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <div class="step-text">Connexion</div>
                    </div>
                    <div class="step active">
                        <div class="step-number">2</div>
                        <div class="step-text">Configuration 2FA</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-text">Terminé</div>
                    </div>
                </div>

                <div class="setup-container">
                    <div class="setup-instructions">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            Configuration requise
                        </h3>
                        <p>Pour renforcer la sécurité de votre compte, l'authentification à deux facteurs est nécessaire. Suivez ces étapes pour la configurer :</p>
                        <ol>
                            <li>
                                Téléchargez l'application <strong>Google Authenticator</strong> sur votre smartphone
                                <div class="app-links">
                                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener" class="app-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 3l14 9-14 9V3z"></path>
                                        </svg>
                                        Android
                                    </a>
                                    <a href="https://apps.apple.com/fr/app/google-authenticator/id388497605" target="_blank" rel="noopener" class="app-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 20.94c1.5 0 2.75 1.06 4 1.06 3 0 4-6 4-11 0-3.5-2-4.5-4-4.5-1.5 0-2 .5-3 .5s-2-.5-4-.5c-2 0-4 1-4 4.5 0 5 1 11 4 11 1.25 0 2.5-1.06 4-1.06z"></path>
                                            <path d="M12 7c1-3 2.5-5 4-5 .5 0 1 0 1.5.5"></path>
                                        </svg>
                                        iOS
                                    </a>
                                </div>
                            </li>
                            <li>Scannez le QR code ci-dessous avec l'application</li>
                            <li>Entrez le code à 6 chiffres généré par l'application pour valider</li>
                        </ol>
                    </div>

                    <div class="qrcode-container">
                        <canvas id="qrcode" width="200" height="200"></canvas>
                    </div>

                    <div class="manual-setup">
                        <div class="manual-setup-header">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="4 7 4 4 20 4 20 7"></polyline>
                                <line x1="9" y1="20" x2="15" y2="20"></line>
                                <line x1="12" y1="4" x2="12" y2="20"></line>
                            </svg>
                            <h4>Configuration manuelle</h4>
                        </div>
                        <p>Si vous ne pouvez pas scanner le QR code, entrez manuellement cette clé secrète dans votre application :</p>
                        <code class="secret-key" id="secretKey"><?php echo chunk_split($secret, 4, ' '); ?></code>
                    </div>

                    <form method="POST" action="setup-2fa.php" id="setupForm">
                        <div class="form-group">
                            <label for="code" class="form-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px; vertical-align: middle;">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Code de vérification
                            </label>
                            <div class="verification-code">
                                <input type="text" name="code" id="code" placeholder="Code à 6 chiffres" required autocomplete="off" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="form-control">
                            </div>
                        </div>
                        <div class="action-button">
                            <button type="submit" class="btn btn-primary btn-block btn-ripple">
                                <span class="btn-text">Vérifier et activer</span>
                                <span class="btn-icon-right">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="auth-footer">
                <div class="security-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Pour votre sécurité, cette étape est obligatoire</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Générer le QR code avec qrcode.js
            QRCode.toCanvas(
                document.getElementById('qrcode'),
                "<?php echo addslashes($provisioningUrl); ?>", {
                    width: 200,
                    margin: 4,
                    color: {
                        dark: '#4e73df', // Couleur primaire
                        light: '#ffffff'
                    }
                }
            );

            // Focus automatique sur l'input du code
            document.getElementById('code').focus();

            // Copier la clé secrète au clic
            const secretKey = document.getElementById('secretKey');
            secretKey.addEventListener('click', function() {
                const text = this.innerText.replace(/\s/g, ''); // Enlever les espaces
                navigator.clipboard.writeText(text).then(() => {
                    this.classList.add('copied');
                    setTimeout(() => {
                        this.classList.remove('copied');
                    }, 2000);
                });
            });

            // Gestion des boutons d'étapes pour une meilleure accessibilité
            document.querySelectorAll('.step').forEach(step => {
                step.setAttribute('aria-label', step.querySelector('.step-text').textContent);
            });
        });
    </script>
</body>

</html>