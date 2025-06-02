<?php
// filepath: C:\laragon\www\EXTRACTYS\login_form\fix_admin_password.php

require_once 'config/database.php';

// Utilisateur à corriger
$username = 'admin';
$password = 'Admin123!';

// Générer le hachage correct
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Mettre à jour le mot de passe dans la base de données
$sql = "UPDATE users SET password = :password WHERE username = :username";
$stmt = $db->prepare($sql);
$stmt->bindParam(':password', $hashedPassword);
$stmt->bindParam(':username', $username);

echo "<h2>Correction du mot de passe pour l'utilisateur admin</h2>";

if ($stmt->execute()) {
    echo "✅ Mot de passe corrigé avec succès pour '$username'<br>";
    echo "Nouveau hachage : $hashedPassword<br>";
    echo "<p>Vous pouvez maintenant vous connecter avec admin / Admin123!</p>";
    echo "<p><a href='public/login.php'>Aller à la page de connexion</a></p>";
} else {
    echo "❌ Erreur lors de la mise à jour du mot de passe.<br>";
}
