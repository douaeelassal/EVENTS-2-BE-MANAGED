<?php
require_once 'includes/config.php';
require_once 'includes/Database.php';

echo "<h1>📋 Vérification/Création Table Attestations</h1>";
echo "<style>
    .success { color: green; background: #e6ffe6; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .error { color: red; background: #ffe6e6; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .info { color: blue; background: #e6f3ff; padding: 10px; margin: 10px 0; border-radius: 5px; }
</style>";

try {
    $db = Database::getInstance();

    // Vérifier si la table existe
    try {
        $db->query("DESCRIBE attestations");
        echo "<div class='success'>✅ Table 'attestations' existe déjà !</div>";
    } catch (PDOException $e) {
        echo "<div class='info'>ℹ️ Table 'attestations' n'existe pas. Création en cours...</div>";

        // Créer la table
        $sql = "CREATE TABLE IF NOT EXISTS attestations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            evenement_id INT NOT NULL,
            participant_id INT NOT NULL,
            fichier_pdf VARCHAR(255) NOT NULL,
            date_generation DATETIME NOT NULL,
            INDEX idx_evenement (evenement_id),
            INDEX idx_participant (participant_id),
            UNIQUE KEY unique_participant_event (evenement_id, participant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->query($sql);
        echo "<div class='success'>✅ Table 'attestations' créée avec succès !</div>";
    }

    // Vérifier la structure de la table
    echo "<div class='info'>📋 Structure de la table :</div>";
    $stmt = $db->query("DESCRIBE attestations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='padding: 10px;'>Champ</th>";
    echo "<th style='padding: 10px;'>Type</th>";
    echo "<th style='padding: 10px;'>Null</th>";
    echo "<th style='padding: 10px;'>Clé</th>";
    echo "<th style='padding: 10px;'>Default</th>";
    echo "</tr>";

    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $column['Field'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Type'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Null'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Key'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='success'>🎉 Configuration terminée ! La génération d'attestations devrait maintenant fonctionner.</div>";
    echo "<p><a href='organisateur/dashboard.php'>← Retour au dashboard</a></p>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . $e->getMessage() . "</div>";
}
?>