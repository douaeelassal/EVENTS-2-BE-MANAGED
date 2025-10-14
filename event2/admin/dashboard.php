<?php
require_once '../includes/config.php';
require_once '../includes/Database.php';
session_start();

// Vérification de sécurité - Seulement les vrais administrateurs peuvent accéder au dashboard admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Tableau de Bord Administrateur - EVENT2";
$db = Database::getInstance();

// Statistiques principales
$totalUsers = $db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
$totalEvents = $db->query("SELECT COUNT(*) FROM evenements")->fetchColumn();
$totalInscriptions = $db->query("SELECT COUNT(*) FROM inscriptions")->fetchColumn();
$totalOrganisateurs = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'organisateur'")->fetchColumn();
$totalParticipants = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'participant'")->fetchColumn();

// Statistiques avancées
$recentUsers = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$activeEvents = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'actif'")->fetchColumn();
$publieEvents = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'publie'")->fetchColumn();
$pendingEvents = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'en_attente'")->fetchColumn();
$termineEvents = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'termine'")->fetchColumn();

try {
    // Événements récents - Utiliser date_debut au lieu de date_creation
    $stmt = $db->query("
        SELECT e.titre, e.date_debut, u.nom_complet as organisateur,
               COUNT(i.id) as inscriptions
        FROM evenements e
        LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        GROUP BY e.id
        ORDER BY e.date_debut DESC
        LIMIT 5
    ");
} catch (PDOException $e) {
    // Fallback si date_debut n'existe pas non plus
    $stmt = $db->query("
        SELECT e.titre, NOW() as date_debut, u.nom_complet as organisateur,
               COUNT(i.id) as inscriptions
        FROM evenements e
        LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        GROUP BY e.id
        ORDER BY e.id DESC
        LIMIT 5
    ");
}
$recentEvents = $stmt->fetchAll();

// Logs récents
try {
    $stmt = $db->query("
        SELECT la.action, la.details, la.date_creation, u.nom_complet
        FROM logs_audit la
        LEFT JOIN utilisateurs u ON la.user_id = u.id
        ORDER BY la.date_creation DESC
        LIMIT 10
    ");
} catch (PDOException $e) {
    // Fallback si la colonne date_creation n'existe pas dans logs_audit
    $stmt = $db->query("
        SELECT la.action, la.details, NOW() as date_creation, u.nom_complet
        FROM logs_audit la
        LEFT JOIN utilisateurs u ON la.user_id = u.id
        ORDER BY la.id DESC
        LIMIT 10
    ");
}
$recentLogs = $stmt->fetchAll();

include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête du dashboard -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="shield"></i>
                    Administration EVENT2
                </h1>
                <p class="dashboard-subtitle">
                    Vue d'ensemble du système - <?php echo date('d F Y'); ?>
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="verification_requests.php" class="btn btn-primary">
                    <i data-lucide="shield-check"></i>
                    Vérifications (<?php echo $db->query("SELECT COUNT(*) FROM demandes_verification WHERE statut = 'en_attente'")->fetchColumn(); ?>)
                </a>
                <a href="stats.php" class="btn btn-secondary">
                    <i data-lucide="bar-chart-3"></i>
                    Statistiques Détaillées
                </a>
                <a href="users.php" class="btn btn-secondary">
                    <i data-lucide="users"></i>
                    Gérer Utilisateurs
                </a>
            </div>
        </div>

        <!-- Cartes de statistiques principales -->
        <div class="dashboard-grid">
            <!-- Utilisateurs -->
            <div class="card stats-card">
                <div class="card-header">
                    <h3>
                        <i data-lucide="users"></i>
                        Utilisateurs
                    </h3>
                    <span class="status-indicator status-active" data-tooltip="Tous les utilisateurs actifs"></span>
                </div>
                <div class="card-content">
                    <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-details">
                        <div class="stat-item">
                            <span class="stat-label">Organisateurs:</span>
                            <span class="stat-value"><?php echo $totalOrganisateurs; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Participants:</span>
                            <span class="stat-value"><?php echo $totalParticipants; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Nouveaux (7j):</span>
                            <span class="stat-value text-success">+<?php echo $recentUsers; ?></span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($totalParticipants / $totalUsers) * 100, 100); ?>%"></div>
                    </div>
                </div>
                <div class="card-actions">
                    <a href="users.php" class="btn btn-primary btn-sm">Gérer</a>
                </div>
            </div>

            <!-- Événements -->
            <div class="card stats-card">
                <div class="card-header">
                    <h3>
                        <i data-lucide="calendar"></i>
                        Événements
                    </h3>
                    <span class="status-indicator <?php echo $activeEvents > 0 ? 'status-active' : 'status-inactive'; ?>"></span>
                </div>
                <div class="card-content">
                    <div class="stat-number"><?php echo number_format($totalEvents); ?></div>
                    <div class="stat-details">
                        <div class="stat-item">
                            <span class="stat-label">Actifs:</span>
                            <span class="stat-value text-success"><?php echo $activeEvents; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Publiés:</span>
                            <span class="stat-value" style="color: #007bff;"><?php echo $publieEvents; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">En attente:</span>
                            <span class="stat-value text-warning"><?php echo $pendingEvents; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Terminés:</span>
                            <span class="stat-value" style="color: #6c757d;"><?php echo $termineEvents; ?></span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo (($activeEvents + $publieEvents) / max($totalEvents, 1)) * 100; ?>%"></div>
                    </div>
                </div>
                <div class="card-actions">
                    <a href="evenements.php" class="btn btn-primary btn-sm">Voir Tout</a>
                </div>
            </div>

            <!-- Inscriptions -->
            <div class="card stats-card">
                <div class="card-header">
                    <h3>
                        <i data-lucide="user-check"></i>
                        Inscriptions
                    </h3>
                    <span class="status-indicator status-active"></span>
                </div>
                <div class="card-content">
                    <div class="stat-number"><?php echo number_format($totalInscriptions); ?></div>
                    <div class="stat-details">
                        <div class="stat-item">
                            <span class="stat-label">Taux moyen:</span>
                            <span class="stat-value">
                                <?php echo $totalEvents > 0 ? round(($totalInscriptions / $totalEvents), 1) : 0; ?>/événement
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-actions">
                    <a href="inscriptions.php" class="btn btn-primary btn-sm">Détails</a>
                </div>
            </div>
        </div>

        <!-- Section événements récents -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>
                    <i data-lucide="clock"></i>
                    Événements Récents
                </h2>
                <a href="evenements.php" class="btn btn-secondary btn-sm">Voir Tous</a>
            </div>

            <div class="recent-events">
                <?php if (empty($recentEvents)): ?>
                    <div class="empty-state">
                        <i data-lucide="calendar-x"></i>
                        <p>Aucun événement récent</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentEvents as $event): ?>
                        <div class="event-item card">
                            <div class="event-info">
                                <h4><?php echo htmlspecialchars($event['titre']); ?></h4>
                                <p class="event-organisateur">
                                    <i data-lucide="user"></i>
                                    <?php echo htmlspecialchars($event['organisateur']); ?>
                                </p>
                                <p class="event-date">
                                    <i data-lucide="calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($event['date_debut'])); ?>
                                </p>
                            </div>
                            <div class="event-stats">
                                <div class="inscription-count">
                                    <i data-lucide="user-check"></i>
                                    <?php echo $event['inscriptions']; ?> inscriptions
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section logs récents -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>
                    <i data-lucide="activity"></i>
                    Activité Récente
                </h2>
                <a href="logs.php" class="btn btn-secondary btn-sm">Voir Tous</a>
            </div>

            <div class="recent-logs">
                <?php if (empty($recentLogs)): ?>
                    <div class="empty-state">
                        <i data-lucide="file-x"></i>
                        <p>Aucune activité récente</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item card">
                            <div class="log-info">
                                <div class="log-action">
                                    <i data-lucide="activity"></i>
                                    <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                </div>
                                <div class="log-details">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </div>
                                <div class="log-user">
                                    <i data-lucide="user"></i>
                                    <?php echo htmlspecialchars($log['nom_complet'] ?? 'Utilisateur inconnu'); ?>
                                </div>
                            </div>
                            <div class="log-time">
                                <i data-lucide="clock"></i>
                                <?php echo date('d/m H:i', strtotime($log['date_creation'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
/* Dashboard specific styles */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.dashboard-header-content h1 {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.dashboard-subtitle {
    color: var(--grey-medium);
    margin: 0;
}

.dashboard-actions {
    display: flex;
    gap: 1rem;
}

/* Stats cards */
.stats-card {
    position: relative;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.card-content {
    margin-bottom: 1.5rem;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: var(--cerise-primary);
    margin-bottom: 1rem;
}

.stat-details {
    margin-bottom: 1rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.stat-label {
    color: var(--grey-medium);
}

.stat-value {
    font-weight: 600;
    color: var(--grey-dark);
}

.text-success { color: #28a745; }
.text-warning { color: #ffc107; }

.card-actions {
    text-align: center;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* Sections */
.dashboard-section {
    margin-top: 3rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

/* Événements récents */
.recent-events, .recent-logs {
    display: grid;
    gap: 1rem;
}

.event-item, .log-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-left: 4px solid var(--cerise-primary);
}

.event-info h4 {
    margin-bottom: 0.5rem;
    color: var(--grey-dark);
}

.event-organisateur, .event-date, .log-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--grey-medium);
    margin-bottom: 0.25rem;
}

.log-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.log-details {
    color: var(--grey-medium);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.log-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--grey-medium);
}

.inscription-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--cerise-primary);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--grey-medium);
}

.empty-state i {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .dashboard-actions {
        justify-content: center;
    }

    .event-item, .log-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
}
</style>

<script>
// Initialiser les icônes
lucide.createIcons();

// Animation des compteurs
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');

    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[^\d]/g, ''));
        const duration = 1000;
        const step = target / (duration / 16);
        let current = 0;

        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                counter.textContent = counter.textContent.includes('.') ?
                    Math.round(target) : target.toLocaleString();
                clearInterval(timer);
            } else {
                counter.textContent = Math.round(current).toLocaleString();
            }
        }, 16);
    });
}

// Lancer l'animation après le chargement
document.addEventListener('DOMContentLoaded', animateCounters);
</script>

<?php include '../includes/footer.php'; ?>
