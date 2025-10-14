<?php
declare(strict_types=1);
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/Security.php';

echo "<h1>Création d'un administrateur</h1>";
echo "<pre>";

try {
    $db = Database::getInstance();

    // Informations de l'administrateur
    $admin_email = 'admin@event2.com';
    $admin_nom = 'Administrateur';
    $admin_password = 'admin123'; // Mot de passe en clair
    $admin_role = 'admin';

    echo "🔐 Création de l'administrateur...\n";
    echo "Email: $admin_email\n";
    echo "Nom: $admin_nom\n";
    echo "Mot de passe: $admin_password\n";
    echo "Rôle: $admin_role\n\n";

    // Vérifier si l'admin existe déjà
    $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $stmt->execute([$admin_email]);
    $existing_admin = $stmt->fetch();

    if ($existing_admin) {
        echo "⚠️  L'administrateur existe déjà (ID: {$existing_admin['id']})\n";

        // Mettre à jour le mot de passe
        $password_hash = Security::hashPassword($admin_password);
        $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ?, email_verifie = 1 WHERE email = ?");
        $stmt->execute([$password_hash, $admin_email]);

        echo "✅ Mot de passe mis à jour\n";
    } else {
        // Créer le nouvel administrateur
        $password_hash = Security::hashPassword($admin_password);
        $stmt = $db->prepare("
            INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role, email_verifie, date_creation)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$admin_nom, $admin_email, $password_hash, $admin_role]);

        $new_admin_id = $db->lastInsertId();
        echo "✅ Nouvel administrateur créé (ID: $new_admin_id)\n";
    }

    // Vérifier la création
    $stmt = $db->prepare("SELECT id, nom_complet, email, role, email_verifie FROM utilisateurs WHERE email = ?");
    $stmt->execute([$admin_email]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo "\n📋 Informations de l'administrateur:\n";
        echo "- ID: {$admin['id']}\n";
        echo "- Nom: {$admin['nom_complet']}\n";
        echo "- Email: {$admin['email']}\n";
        echo "- Rôle: {$admin['role']}\n";
        echo "- Email vérifié: " . ($admin['email_verifie'] ? 'Oui' : 'Non') . "\n";
        echo "- Mot de passe: $admin_password\n";

        // Vérifier le hash
        $is_valid = Security::verifyPassword($admin_password, $password_hash);
        echo "- Mot de passe valide: " . ($is_valid ? 'Oui' : 'Non') . "\n";
    }

    echo "\n🎉 Administrateur créé avec succès!\n";
    echo "Vous pouvez maintenant vous connecter avec:\n";
    echo "Email: $admin_email\n";
    echo "Mot de passe: $admin_password\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>