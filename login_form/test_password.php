<?php
require_once 'config/database.php';

// Identifiants à tester
$username = 'admin'; // Utilisez l'identifiant que vous essayez
$password = 'Admin123!'; // Utilisez le mot de passe que vous essayez

// Récupérer l'utilisateur depuis la base de données
$sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->bindParam(':username', $username);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Test d'authentification</h2>";

if (!$user) {
    echo "❌ Utilisateur '$username' non trouvé dans la base de données.<br>";
    exit;
}

echo "✅ Utilisateur trouvé dans la base de données:<br>";
echo "ID: " . $user['id'] . "<br>";
echo "Email: " . $user['email'] . "<br>";
echo "Username: " . $user['username'] . "<br>";
echo "Mot de passe haché: " . $user['password'] . "<br><br>";

// Tester si le mot de passe fourni correspond au hachage stocké
$passwordVerified = password_verify($password, $user['password']);

if ($passwordVerified) {
    echo "✅ Le mot de passe est <strong>correct</strong>!<br>";
} else {
    echo "❌ Le mot de passe est <strong>incorrect</strong>!<br><br>";

    // Test supplémentaire: créer un nouveau hachage pour vérifier le format
    echo "Test d'un nouveau hachage avec le même mot de passe:<br>";
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    echo "Nouveau hachage généré: " . $newHash . "<br>";
    echo "Vérification avec ce nouveau hachage: " . (password_verify($password, $newHash) ? "Réussi" : "Échoué") . "<br><br>";

    echo "Conseils possibles:<br>";
    echo "- Le mot de passe stocké n'a peut-être pas été haché avec password_hash()<br>";
    echo "- Il pourrait y avoir un problème d'encodage ou de caractères spéciaux<br>";
    echo "- Le hachage stocké pourrait être corrompu ou utiliser un algorithme différent<br>";
}
