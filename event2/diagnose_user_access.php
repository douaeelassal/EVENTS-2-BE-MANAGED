<?php
declare(strict_types=1);
require_once 'includes/config.php';
require_once 'includes/Database.php';

echo "<h1>🔍 Diagnostic d'accès utilisateur</h1>\n";

try {
    $db = Database::getInstance();
    echo "<h2>✅ Connexion base de données réussie</h2>\n";

    // Vérifier les utilisateurs
    echo "<h3>👥 Utilisateurs dans la base de données:</h3>\n";
    $stmt = $db->query("
        SELECT u.id, u.nom_complet, u.email, u.role, u.statut_verification,
               a.privileges as admin_privileges,
               o.est_approuve as organisateur_approuve
        FROM utilisateurs u
        LEFT JOIN administrateurs a ON u.id = a.utilisateur_id
        LEFT JOIN organisateurs o ON u.id = o.utilisateur_id
        ORDER BY u.id
    ");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<div style='background: #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<strong>⚠️ Aucun utilisateur trouvé!</strong><br>\n";
        echo "Vous devez d'abord créer des comptes via la page d'inscription.\n";
        echo "<a href='auth/register.php'>Créer un compte</a>\n";
        echo "</div>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f8f9fa;'>\n";
        echo "<th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Accès Admin</th><th>Accès Organisateur</th>\n";
        echo "</tr>\n";

        foreach ($users as $user) {
            $can_access_admin = ($user['role'] === 'admin' || $user['role'] === 'organisateur');
            $status_color = $can_access_admin ? 'green' : 'red';

            echo "<tr>\n";
            echo "<td>{$user['id']}</td>\n";
            echo "<td>{$user['nom_complet']}</td>\n";
            echo "<td>{$user['email']}</td>\n";
            echo "<td><strong>{$user['role']}</strong></td>\n";
            echo "<td>{$user['statut_verification']}</td>\n";
            echo "<td style='color: " . ($user['admin_privileges'] ? 'green' : 'gray') . "'>" .
                 ($user['admin_privileges'] ? '✅ Admin' : '❌') . "</td>\n";
            echo "<td style='color: " . ($user['organisateur_approuve'] ? 'green' : 'orange') . "'>" .
                 ($user['organisateur_approuve'] ? '✅ Approuvé' : '⏳ En attente') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";

        // Résumé des accès
        echo "<h3>📊 Résumé des accès:</h3>\n";
        $admin_count = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
        $org_count = count(array_filter($users, fn($u) => $u['role'] === 'organisateur'));
        $part_count = count(array_filter($users, fn($u) => $u['role'] === 'participant'));

        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;'>\n";
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<strong>👑 Administrateurs</strong><br>{$admin_count} utilisateur(s)<br>";
        echo $admin_count > 0 ? "✅ Peuvent accéder à tout" : "❌ Aucun admin";
        echo "</div>\n";

        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<strong>🏢 Organisateurs</strong><br>{$org_count} utilisateur(s)<br>";
        echo $org_count > 0 ? "✅ Peuvent voir les événements" : "❌ Aucun organisateur";
        echo "</div>\n";

        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<strong>👤 Participants</strong><br>{$part_count} utilisateur(s)<br>";
        echo "❌ Ne peuvent pas accéder à l'admin";
        echo "</div>\n";
        echo "</div>\n";
    }

    // Vérifier les événements
    echo "<h3>📅 Événements dans la base:</h3>\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM evenements");
    $event_count = $stmt->fetch()['total'];

    if ($event_count === 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<strong>⚠️ Aucun événement trouvé!</strong><br>\n";
        echo "Créez d'abord des événements pour qu'ils apparaissent dans la page admin.\n";
        echo "</div>\n";
    } else {
        echo "<p style='color: green;'>✅ {$event_count} événement(s) trouvé(s)</p>\n";

        // Détail des événements par statut
        $stmt = $db->query("
            SELECT statut, COUNT(*) as count
            FROM evenements
            GROUP BY statut
        ");
        $status_stats = $stmt->fetchAll();

        echo "<div style='margin-left: 20px;'>\n";
        foreach ($status_stats as $stat) {
            echo "• {$stat['statut']}: {$stat['count']} événement(s)<br>\n";
        }
        echo "</div>\n";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<strong>❌ Erreur de connexion:</strong><br>\n";
    echo htmlspecialchars($e->getMessage()) . "\n";
    echo "</div>\n";

    echo "<h3>🔧 Vérification de la configuration:</h3>\n";
    echo "<ul>\n";
    echo "<li>Vérifiez que le serveur MySQL/MariaDB est démarré</li>\n";
    echo "<li>Vérifiez les paramètres de connexion dans <code>includes/config.php</code></li>\n";
    echo "<li>Assurez-vous que la base <code>envent_2</code> existe</li>\n";
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<h3>🛠️ Actions recommandées:</h3>\n";
echo "<ul>\n";
echo "<li><a href='create_admin_user.php'>Créer un compte administrateur</a></li>\n";
echo "<li><a href='auth/register.php'>Créer un compte de test</a></li>\n";
echo "<li><a href='admin/evenements.php'>Tester l'accès admin</a></li>\n";
echo "</ul>\n";

echo "<hr>\n";
echo "<p><a href='index.php'>Retour à l'accueil</a></p>\n";
?>