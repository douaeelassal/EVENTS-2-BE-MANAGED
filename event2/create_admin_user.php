<?php
declare(strict_types=1);
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/Security.php';

try {
    $db = Database::getInstance();
    echo "<h2>Vérification et création d'un compte administrateur</h2>\n";

    // Vérifier si un admin existe déjà
    $stmt = $db->prepare("SELECT id, nom_complet, email FROM utilisateurs WHERE role = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        echo "<p style='color: green;'>✓ Un administrateur existe déjà: {$admin['nom_complet']} ({$admin['email']})</p>\n";
        echo "<p>Vous pouvez vous connecter avec ce compte pour accéder aux pages d'administration.</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ Aucun administrateur trouvé. Création d'un compte administrateur...</p>\n";

        // Créer un compte admin
        $nom = 'Administrateur';
        $email = 'admin@event2.com';
        $password = 'admin123'; // Mot de passe par défaut
        $hash = Security::hashPassword($password);

        $stmt = $db->prepare("
            INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role, email_verifie, statut_verification)
            VALUES (?, ?, ?, 'admin', TRUE, 'verifie')
        ");
        $stmt->execute([$nom, $email, $hash]);

        $adminId = (int)$db->lastInsertId();

        // Ajouter dans la table administrateurs
        $stmt = $db->prepare("INSERT INTO administrateurs (utilisateur_id, privileges) VALUES (?, 'all')");
        $stmt->execute([$adminId]);

        echo "<p style='color: green;'>✓ Compte administrateur créé avec succès!</p>\n";
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<strong>Informations de connexion:</strong><br>\n";
        echo "Email: admin@event2.com<br>\n";
        echo "Mot de passe: admin123<br>\n";
        echo "</div>\n";
    }

    // Afficher tous les utilisateurs
    echo "<h3>Utilisateurs dans la base de données:</h3>\n";
    $stmt = $db->query("SELECT id, nom_complet, email, role FROM utilisateurs ORDER BY id");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<p style='color: orange;'>Aucun utilisateur trouvé. Vous devrez créer des comptes via l'inscription.</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f5f5f5;'><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th></tr>\n";
        foreach ($users as $user) {
            echo "<tr>\n";
            echo "<td>{$user['id']}</td>\n";
            echo "<td>{$user['nom_complet']}</td>\n";
            echo "<td>{$user['email']}</td>\n";
            echo "<td>{$user['role']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='index.php'>Retour à l'accueil</a></p>\n";
?>