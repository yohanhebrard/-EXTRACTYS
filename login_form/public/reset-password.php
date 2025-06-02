<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer une adresse e-mail valide.";
    } else {
        // Vérifier si l'email existe dans la base de données
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Générer un token de réinitialisation
            $token = bin2hex(random_bytes(50));
            $stmt = $pdo->prepare("UPDATE users SET reset_token = :token WHERE email = :email");
            $stmt->execute(['token' => $token, 'email' => $email]);

            // Envoyer un e-mail avec le lien de réinitialisation
            $resetLink = "http://yourdomain.com/reset-password.php?token=" . $token;
            $subject = "Réinitialisation de votre mot de passe";
            $message = "Cliquez sur ce lien pour réinitialiser votre mot de passe: " . $resetLink;
            mail($email, $subject, $message);

            $success = "Un lien de réinitialisation a été envoyé à votre adresse e-mail.";
        } else {
            $error = "Aucun utilisateur trouvé avec cette adresse e-mail.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Réinitialiser le mot de passe</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <label for="email">Adresse e-mail:</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Envoyer le lien de réinitialisation</button>
        </form>
        <p><a href="login.php">Retour à la connexion</a></p>
    </div>
</body>
</html>