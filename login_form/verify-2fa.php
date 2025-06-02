<?php
require_once 'init.php';
require_once 'db_connect.php';
require_once 'functions.php'; // Ajoutez cette ligne pour inclure la fonction verifyTfaCode

// Vérifier si l'utilisateur attend une vérification 2FA
if (!isset($_SESSION['awaiting_2fa']) || $_SESSION['awaiting_2fa'] != 1) {
    // Rediriger vers la page de connexion s'il n'y a pas de vérification 2FA en attente
    header('Location: login.php');
    exit;
}

// Traitement de la soumission du code 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfa_code'])) {
    $tfaCode = trim($_POST['tfa_code']);
    $userId = $_SESSION['user_id'];

    // Vérifier le code 2FA (remplacer par votre logique actuelle)
    $isValid = verifyTfaCode($userId, $tfaCode);

    if ($isValid) {
        // Log de la réussite
        error_log("2FA verification successful, completing login");

        // Marquer l'utilisateur comme complètement authentifié
        $_SESSION['awaiting_2fa'] = 0;
        $_SESSION['fully_authenticated'] = 1;

        // Rediriger vers la page d'accueil ou la destination prévue
        $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'dashboard.php';
        unset($_SESSION['redirect_after_login']); // Nettoyer la session

        header("Location: $redirect");
        exit;
    } else {
        $error = "Code 2FA incorrect. Veuillez réessayer.";
        // Incrémenter le compteur d'échecs pour limiter les tentatives
        $_SESSION['tfa_attempts'] = isset($_SESSION['tfa_attempts']) ? $_SESSION['tfa_attempts'] + 1 : 1;

        // Après X tentatives échouées, déconnecter l'utilisateur
        if ($_SESSION['tfa_attempts'] >= 3) {
            error_log("Trop de tentatives 2FA échouées pour l'utilisateur ID: $userId");
            session_unset();
            session_destroy();
            header('Location: login.php?error=too_many_attempts');
            exit;
        }
    }
}

// Gérer la redirection en boucle (anti-redirect loop)
$redirectCount = isset($_SESSION['redirect_count']) ? $_SESSION['redirect_count'] : 0;
$_SESSION['redirect_count'] = $redirectCount + 1;
$_SESSION['last_redirect_time'] = time();

// Si trop de redirections en peu de temps, débloquer potentiellement l'utilisateur
if ($redirectCount > 10 && (time() - $_SESSION['last_redirect_time'] < 60)) {
    error_log("Possible redirect loop détecté pour l'utilisateur ID: {$_SESSION['user_id']}");
    // Option: forcer une réinitialisation ou afficher une page d'aide
}

// Inclure la page HTML pour la vérification 2FA
?>

<!DOCTYPE html>
<html>

<head>
    <title>Vérification à deux facteurs</title>
    <!-- Inclure vos styles CSS ici -->
</head>

<body>
    <div class="container">
        <h2>Vérification à deux facteurs</h2>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="tfa_code">Entrez le code 2FA :</label>
                <input type="text" id="tfa_code" name="tfa_code" required autofocus>
            </div>

            <button type="submit">Vérifier</button>
        </form>
    </div>
</body>

</html>