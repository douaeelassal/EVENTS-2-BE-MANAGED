<?php
declare(strict_types=1);
require_once 'includes/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();

    echo "<h2>🔧 Mise à jour de la base de données pour le système de vérification des organisateurs</h2>";

    // 1. Ajouter les colonnes de vérification à la table utilisateurs
    echo "<h3>1. Mise à jour de la table utilisateurs...</h3>";

    // Vérifier si les colonnes existent déjà
    $columns = $db->query("DESCRIBE utilisateurs")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    if (!in_array('statut_verification', $columnNames)) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN statut_verification ENUM('en_attente', 'verifie', 'rejete') DEFAULT 'en_attente'");
        echo "<p class='success'>✓ Colonne statut_verification ajoutée</p>";
    } else {
        echo "<p class='warning'>⚠ Colonne statut_verification existe déjà</p>";
    }

    if (!in_array('date_demande_verification', $columnNames)) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN date_demande_verification DATETIME NULL");
        echo "<p class='success'>✓ Colonne date_demande_verification ajoutée</p>";
    } else {
        echo "<p class='warning'>⚠ Colonne date_demande_verification existe déjà</p>";
    }

    if (!in_array('date_verification', $columnNames)) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN date_verification DATETIME NULL");
        echo "<p class='success'>✓ Colonne date_verification ajoutée</p>";
    } else {
        echo "<p class='warning'>⚠ Colonne date_verification existe déjà</p>";
    }

    if (!in_array('verifie_par_admin_id', $columnNames)) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN verifie_par_admin_id INT NULL");
        echo "<p class='success'>✓ Colonne verifie_par_admin_id ajoutée</p>";
    } else {
        echo "<p class='warning'>⚠ Colonne verifie_par_admin_id existe déjà</p>";
    }

    // 2. Créer la table des cartes d'adhésion
    echo "<h3>2. Création de la table cartes_adhesion...</h3>";

    $db->exec("
        CREATE TABLE IF NOT EXISTS cartes_adhesion (
            id INT PRIMARY KEY AUTO_INCREMENT,
            utilisateur_id INT NOT NULL,
            numero_carte VARCHAR(50) UNIQUE NOT NULL,
            nom_club VARCHAR(255) NOT NULL,
            date_emission DATE NOT NULL,
            date_expiration DATE NOT NULL,
            fichier_carte VARCHAR(255) NULL,
            statut ENUM('active', 'expiree', 'revoquee') DEFAULT 'active',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
            INDEX idx_utilisateur_id (utilisateur_id),
            INDEX idx_numero_carte (numero_carte),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✓ Table cartes_adhesion créée</p>";

    // 3. Créer la table des demandes de vérification
    echo "<h3>3. Création de la table demandes_verification...</h3>";

    $db->exec("
        CREATE TABLE IF NOT EXISTS demandes_verification (
            id INT PRIMARY KEY AUTO_INCREMENT,
            utilisateur_id INT NOT NULL,
            type_demande ENUM('organisateur', 'carte_adhesion') NOT NULL,
            statut ENUM('en_attente', 'approuvee', 'rejetee') DEFAULT 'en_attente',
            commentaire TEXT NULL,
            fichiers_joints TEXT NULL,
            admin_id INT NULL,
            date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_traitement DATETIME NULL,
            FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
            INDEX idx_utilisateur_id (utilisateur_id),
            INDEX idx_statut (statut),
            INDEX idx_type_demande (type_demande)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✓ Table demandes_verification créée</p>";

    // 4. Modifier la table evenements pour s'assurer qu'un seul organisateur par événement
    echo "<h3>4. Vérification de la table evenements...</h3>";

    $eventColumns = $db->query("DESCRIBE evenements")->fetchAll(PDO::FETCH_ASSOC);
    $eventColumnNames = array_column($eventColumns, 'Field');

    if (!in_array('organisateur_exclusif', $eventColumnNames)) {
        $db->exec("ALTER TABLE evenements ADD COLUMN organisateur_exclusif BOOLEAN DEFAULT TRUE");
        echo "<p class='success'>✓ Colonne organisateur_exclusif ajoutée à evenements</p>";
    } else {
        echo "<p class='warning'>⚠ Colonne organisateur_exclusif existe déjà</p>";
    }

    // 5. Mettre à jour les organisateurs existants
    echo "<h3>5. Mise à jour des organisateurs existants...</h3>";

    // Compter les organisateurs actuels
    $organisateursCount = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'organisateur'")->fetchColumn();
    echo "<p>📊 {$organisateursCount} organisateurs trouvés</p>";

    // Vérifier combien n'ont pas encore de statut de vérification
    $nonVerifiesCount = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'organisateur' AND statut_verification = 'en_attente'")->fetchColumn();
    echo "<p>⏳ {$nonVerifiesCount} organisateurs en attente de vérification</p>";

    // 6. Créer quelques organisateurs de test vérifiés
    echo "<h3>6. Création d'organisateurs de test...</h3>";

    // Vérifier si un organisateur de test existe déjà
    $testOrgExists = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE email = 'organisateur@test.com'")->fetchColumn();

    if ($testOrgExists == 0) {
        $db->exec("
            INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role, statut_verification, date_creation)
            VALUES (
                'Organisateur Test',
                'organisateur@test.com',
                '" . password_hash('password123', PASSWORD_DEFAULT) . "',
                'organisateur',
                'verifie',
                NOW()
            )
        ");
        echo "<p class='success'>✓ Organisateur de test créé (organisateur@test.com / password123)</p>";
    } else {
        echo "<p class='warning'>⚠ Organisateur de test existe déjà</p>";
    }

    echo "<hr>";
    echo "<h2>✅ Configuration terminée!</h2>";
    echo "<p>Le système de vérification des organisateurs est maintenant actif.</p>";
    echo "<p><a href='admin/dashboard.php'>🏠 Retour au dashboard admin</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
}

echo "
<style>
.success { color: green; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.error { color: red; font-weight: bold; }
</style>
";
?>