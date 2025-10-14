<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Statistiques - Organisateur EVENT2";
$db = Database::getInstance();

// Récupérer les statistiques de l'organisateur
try {
    // Statistiques générales
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_evenements,
            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as evenements_actifs,
            COUNT(CASE WHEN statut = 'termine' THEN 1 END) as evenements_termines,
            COUNT(CASE WHEN statut = 'annule' THEN 1 END) as evenements_annules,
            COALESCE(SUM(places_max), 0) as total_places,
            COALESCE(AVG(prix), 0) as prix_moyen
        FROM evenements
        WHERE organisateur_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats_generales = $stmt->fetch();

    // Statistiques des inscriptions
    $stmt = $db->prepare("
        SELECT
            COUNT(i.id) as total_inscriptions,
            COUNT(CASE WHEN i.statut = 'confirme' THEN 1 END) as inscriptions_confirmees,
            COUNT(CASE WHEN i.statut = 'en_attente' THEN 1 END) as inscriptions_attente,
            COUNT(CASE WHEN i.statut = 'annule' THEN 1 END) as inscriptions_annulees,
            COALESCE(AVG(e.prix), 0) as revenu_moyen_par_inscription
        FROM evenements e
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        WHERE e.organisateur_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats_inscriptions = $stmt->fetch();

    // Événements récents (30 derniers jours)
    $stmt = $db->prepare("
        SELECT titre, date_debut, statut, total_inscriptions
        FROM (
            SELECT e.titre, e.date_debut, e.statut,
                   COUNT(i.id) as total_inscriptions,
                   ROW_NUMBER() OVER (ORDER BY e.date_debut DESC) as rn
            FROM evenements e
            LEFT JOIN inscriptions i ON e.id = i.evenement_id
            WHERE e.organisateur_id = ? AND e.date_debut >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY e.id, e.titre, e.date_debut, e.statut
        ) ranked
        WHERE rn <= 5
        ORDER BY date_debut DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $evenements_recents = $stmt->fetchAll();

    // Statistiques par mois (12 derniers mois)
    $stmt = $db->prepare("
        SELECT
            DATE_FORMAT(e.date_debut, '%Y-%m') as mois,
            COUNT(*) as nombre_evenements,
            COUNT(CASE WHEN e.statut = 'termine' THEN 1 END) as evenements_termines,
            COALESCE(SUM(COUNT(i.id)), 0) as total_inscriptions
        FROM evenements e
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        WHERE e.organisateur_id = ? AND e.date_debut >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(e.date_debut, '%Y-%m')
        ORDER BY mois DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats_mensuelles = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Erreur récupération statistiques: " . $e->getMessage());
    $stats_generales = $stats_inscriptions = [];
    $evenements_recents = $stats_mensuelles = [];
}

include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="bar-chart-3"></i>
                    Mes Statistiques
                </h1>
                <p class="dashboard-subtitle">
                    Analyse détaillée de vos événements et inscriptions
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="evenements.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left"></i>
                    Retour aux événements
                </a>
            </div>
        </div>

        <!-- Cartes de statistiques principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="calendar"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_generales['total_evenements'] ?? 0); ?></h3>
                    <p>Événements créés</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="play-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_generales['evenements_actifs'] ?? 0); ?></h3>
                    <p>Événements actifs</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_generales['evenements_termines'] ?? 0); ?></h3>
                    <p>Événements terminés</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_inscriptions['total_inscriptions'] ?? 0); ?></h3>
                    <p>Total inscriptions</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_inscriptions['inscriptions_confirmees'] ?? 0); ?></h3>
                    <p>Inscriptions confirmées</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="trending-up"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format(($stats_inscriptions['inscriptions_confirmees'] ?? 0) / max(($stats_generales['total_evenements'] ?? 1), 1), 1); ?></h3>
                    <p>Inscriptions par événement</p>
                </div>
            </div>
        </div>

        <div class="stats-content">
            <!-- Événements récents -->
            <div class="stats-section">
                <h2>
                    <i data-lucide="clock"></i>
                    Événements récents (30 derniers jours)
                </h2>
                <?php if (empty($evenements_recents)): ?>
                    <div class="empty-state">
                        <i data-lucide="calendar-x"></i>
                        <p>Aucun événement récent trouvé.</p>
                    </div>
                <?php else: ?>
                    <div class="recent-events">
                        <?php foreach ($evenements_recents as $evenement): ?>
                            <div class="event-item">
                                <div class="event-info">
                                    <h4><?php echo htmlspecialchars($evenement['titre']); ?></h4>
                                    <p><?php echo date('d/m/Y', strtotime($evenement['date_debut'])); ?></p>
                                </div>
                                <div class="event-stats">
                                    <span class="status-badge status-<?php echo $evenement['statut']; ?>">
                                        <?php echo ucfirst($evenement['statut']); ?>
                                    </span>
                                    <span class="inscriptions-count">
                                        <i data-lucide="users"></i>
                                        <?php echo $evenement['total_inscriptions']; ?> inscriptions
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistiques mensuelles -->
            <?php if (!empty($stats_mensuelles)): ?>
            <div class="stats-section">
                <h2>
                    <i data-lucide="trending-up"></i>
                    Évolution mensuelle
                </h2>
                <div class="monthly-stats">
                    <?php foreach ($stats_mensuelles as $stat): ?>
                        <div class="month-stat">
                            <div class="month-header">
                                <h4><?php echo date('M Y', strtotime($stat['mois'] . '-01')); ?></h4>
                            </div>
                            <div class="month-data">
                                <div class="stat-item">
                                    <span class="stat-label">Événements</span>
                                    <span class="stat-value"><?php echo $stat['nombre_evenements']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Inscriptions</span>
                                    <span class="stat-value"><?php echo $stat['total_inscriptions']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(210, 10, 46, 0.1);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(210, 10, 46, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #D20A2E 0%, #b80926 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #2d3436;
    margin: 0;
    line-height: 1;
}

.stat-content p {
    color: #636e72;
    margin: 0.5rem 0 0 0;
    font-size: 0.9rem;
    font-weight: 500;
}

.stats-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.stats-section {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(210, 10, 46, 0.1);
}

.stats-section h2 {
    color: #2d3436;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.3rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #636e72;
}

.empty-state i {
    width: 48px;
    height: 48px;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.recent-events {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.event-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #D20A2E;
}

.event-info h4 {
    margin: 0 0 0.3rem 0;
    color: #2d3436;
    font-size: 1rem;
}

.event-info p {
    margin: 0;
    color: #636e72;
    font-size: 0.9rem;
}

.event-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-actif {
    background: rgba(40, 167, 69, 0.15);
    color: #155724;
}

.inscriptions-count {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #636e72;
    font-size: 0.9rem;
}

.monthly-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.month-stat {
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #D20A2E;
}

.month-header h4 {
    margin: 0 0 1rem 0;
    color: #2d3436;
    font-size: 1rem;
}

.month-data {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-label {
    color: #636e72;
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    color: #2d3436;
}

@media (max-width: 768px) {
    .stats-content {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .event-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .event-stats {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

<?php include '../includes/footer.php'; ?>