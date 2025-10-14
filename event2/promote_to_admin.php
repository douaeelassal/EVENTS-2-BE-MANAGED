<?php
declare(strict_types=1);
require_once 'includes/config.php';
require_once 'includes/Database.php';

echo "<h1>👑 Promouvoir un utilisateur administrateur</h1>\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        try {
            $db = Database::getInstance();

            // Vérifier que l'utilisateur existe
            $stmt = $db->prepare("SELECT id, nom_complet, email, role FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("Utilisateur non trouvé");
            }

            // Promouvoir l'utilisateur
            $stmt = $db->prepare("UPDATE utilisateurs SET role = 'admin' WHERE id = ?");
            $stmt->execute([$user_id]);

            // Ajouter dans la table administrateurs
            $stmt = $db->prepare("INSERT IGNORE INTO administrateurs (utilisateur_id, privileges) VALUES (?, 'all')");
            $stmt->execute([$user_id]);

            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;'>\n";
            echo "<strong>✅ Utilisateur promu avec succès!</strong><br>\n";
            echo "👤 {$user['nom_complet']} ({$user['email']}) est maintenant administrateur.<br>\n";
            echo "<a href='admin/dashboard.php'>Accéder au dashboard admin</a>\n";
            echo "</div>\n";

        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;'>\n";
            echo "<strong>❌ Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
            echo "</div>\n";
        }
    }
}

try {
    $db = Database::getInstance();

    // Récupérer les utilisateurs (sauf les admins existants)
    $stmt = $db->query("
        SELECT u.id, u.nom_complet, u.email, u.role
        FROM utilisateurs u
        WHERE u.role != 'admin'
        ORDER BY u.nom_complet
    ");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<p style='color: orange;'>⚠️ Aucun utilisateur à promouvoir. Tous les utilisateurs sont déjà administrateurs ou aucun utilisateur n'existe.</p>\n";
        echo "<p><a href='auth/register.php'>Créer un compte utilisateur</a></p>\n";
    } else {
        echo "<form method='post'>\n";
        echo "<div style='margin: 20px 0;'>\n";
        echo "<label for='user_id'><strong>Choisir un utilisateur à promouvoir:</strong></label><br><br>\n";
        echo "<select name='user_id' id='user_id' style='width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;'>\n";
        echo "<option value=''>Sélectionner un utilisateur...</option>\n";

        foreach ($users as $user) {
            echo "<option value='{$user['id']}'>👤 {$user['nom_complet']} - {$user['email']} ({$user['role']})</option>\n";
        }

        echo "</select>\n";
        echo "</div>\n";
        echo "<button type='submit' style='background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>\n";
        echo "👑 Promouvoir administrateur\n";
        echo "</button>\n";
        echo "</form>\n";

        echo "<hr>\n";
        echo "<h3>👥 Utilisateurs disponibles:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f8f9fa;'>\n";
        echo "<th>ID</th><th>Nom</th><th>Email</th><th>Rôle actuel</th>\n";
        echo "</tr>\n";

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
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;'>\n";
    echo "<strong>❌ Erreur de connexion:</strong><br>\n";
    echo htmlspecialchars($e->getMessage()) . "\n";
    echo "</div>\n";
}

echo "<hr>\n";
echo "<p><a href='diagnose_user_access.php'>🔍 Diagnostic complet</a> | ";
echo "<a href='create_admin_user.php'>Créer nouvel admin</a> | ";
echo "<a href='index.php'>Accueil</a></p>\n";
?>