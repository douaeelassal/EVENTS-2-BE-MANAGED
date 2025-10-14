<?php
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/Security.php';

echo "=== Gestionnaire d'Administrateur EVENT2 ===\n\n";

try {
    $db = Database::getInstance();

    // Vérifier si l'admin existe déjà
    $existingAdmin = $db->query("SELECT id, email FROM utilisateurs WHERE role = 'admin'")->fetch();

    if ($existingAdmin) {
        echo "✅ Administrateur existant trouvé:\n";
        echo "   Email: " . $existingAdmin['email'] . "\n";
        echo "   ID: " . $existingAdmin['id'] . "\n\n";

        echo "Voulez-vous supprimer cet administrateur et en créer un nouveau? (y/n): ";
        $confirmation = trim(fgets(STDIN));

        if (strtolower($confirmation) === 'y' || strtolower($confirmation) === 'yes') {
            // Supprimer l'admin existant
            $db->prepare("DELETE FROM utilisateurs WHERE role = 'admin'")->execute();
            echo "✅ Administrateur supprimé avec succès!\n\n";
        } else {
            echo "❌ Opération annulée.\n";
            exit;
        }
    }

    // Créer le nouvel administrateur
    echo "=== Création du nouvel administrateur ===\n";
    echo "Votre email réel: ";
    $adminEmail = trim(fgets(STDIN));

    if (empty($adminEmail)) {
        die("❌ Email requis!\n");
    }

    // Valider l'email
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        die("❌ Email invalide!\n");
    }

    // Vérifier le domaine MX
    $domain = substr(strrchr($adminEmail, "@"), 1);
    if (!checkdnsrr($domain, 'MX')) {
        echo "⚠️  Attention: Impossible de vérifier le serveur mail pour le domaine '$domain'\n";
        echo "Voulez-vous continuer quand même? (y/n): ";
        $continue = trim(fgets(STDIN));
        if (strtolower($continue) !== 'y' && strtolower($continue) !== 'yes') {
            die("❌ Création annulée.\n");
        }
    }

    echo "Mot de passe (sera masqué): ";
    $adminPassword = trim(fgets(STDIN));

    if (strlen($adminPassword) < 6) {
        die("❌ Le mot de passe doit contenir au moins 6 caractères!\n");
    }

    // Hacher le mot de passe
    $passwordHash = Security::hashPassword($adminPassword);

    // Insérer le nouvel administrateur
    $stmt = $db->prepare("
        INSERT INTO utilisateurs (email, mot_de_passe_hash, nom_complet, role, email_verifie, date_creation)
        VALUES (?, ?, ?, 'admin', 1, NOW())
    ");

    $result = $stmt->execute([$adminEmail, $passwordHash, 'Administrateur']);

    if ($result) {
        echo "\n🎉 Administrateur créé avec succès!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Email: $adminEmail\n";
        echo "Rôle: Administrateur\n";
        echo "Statut: Actif ✅\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\nVous pouvez maintenant vous connecter sur:\n";
        echo "http://localhost:3000/auth/login.php\n";
    } else {
        echo "❌ Erreur lors de la création de l'administrateur.\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>