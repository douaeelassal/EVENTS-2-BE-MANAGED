<?php
declare(strict_types=1);
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/Database.php';

try {
    $db = Database::getInstance();

    // Vérifier le statut de vérification de l'organisateur
    $stmt = $db->prepare("SELECT statut_verification FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_id = $_SESSION['user_id'];
    
    // Statistiques de l'organisateur
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_events
        FROM evenements 
        WHERE organisateur_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    $total_events = $stats['total_events'];
    
    // Nombre total d'inscriptions
    $stmt = $db->prepare("
        SELECT COUNT(i.id) as total_inscriptions
        FROM inscriptions i
        JOIN evenements e ON e.id = i.evenement_id
        WHERE e.organisateur_id = ?
    ");
    $stmt->execute([$user_id]);
    $inscriptions_stats = $stmt->fetch();
    $total_inscriptions = $inscriptions_stats['total_inscriptions'];
    
    // Événements récents (5 derniers)
    $stmt = $db->prepare("
        SELECT id, titre, date_debut, statut, places_max,
               (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = evenements.id) as inscrits
        FROM evenements
        WHERE organisateur_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_events = $stmt->fetchAll();
    
    // Inscriptions récentes
    $stmt = $db->prepare("
        SELECT i.id, i.date_inscription, i.statut,
               u.nom_complet as participant_nom,
               e.titre as evenement_titre
        FROM inscriptions i
        JOIN utilisateurs u ON u.id = i.utilisateur_id
        JOIN evenements e ON e.id = i.evenement_id
        WHERE e.organisateur_id = ?
        ORDER BY i.date_inscription DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_inscriptions = $stmt->fetchAll();

    
} catch (Exception $e) {
    $error = "Erreur de chargement : " . $e->getMessage();
}

include '../includes/header.php';

// Si erreur de base de données, afficher le message d'erreur
if (isset($error)) {
    echo "<div style='text-align: center; padding: 50px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 10px; margin: 50px auto; max-width: 600px;'>";
    echo "<h2>Erreur de chargement</h2>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
    echo "<p><a href='../index.php'>Retour à l'accueil</a></p>";
    echo "</div>";
    include '../includes/footer.php';
    exit;
}
?>

<main class="dashboard">
    <div class="container">
        <div class="dashboard-header">
            <h1>Dashboard Organisateur</h1>
            <p>Bienvenue, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> ! Gérez vos événements et suivez vos inscriptions.</p>

            <!-- Statut de vérification -->
            <?php if ($user['statut_verification'] !== 'verifie'): ?>
                <div class="verification-status-card" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 12px; padding: 1rem; margin-top: 1rem; text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i data-lucide="shield-alert" style="color: #856404; width: 24px; height: 24px;"></i>
                        <strong style="color: #856404;">Vérification Requise</strong>
                    </div>
                    <p style="color: #856404; margin: 0; font-size: 0.9rem;">
                        Votre compte doit être vérifié par l'administration avant de pouvoir créer des événements.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques rapides -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Mes Événements</h3>
                <div class="stat-number"><?= $total_events ?></div>
                <a href="evenements.php" class="btn btn-secondary">Voir tous</a>
            </div>
            
            <div class="stat-card">
                <h3>Inscriptions</h3>
                <div class="stat-number"><?= $total_inscriptions ?></div>
                <a href="inscriptions.php" class="btn btn-secondary">Gérer</a>
            </div>
            
        </div>

        <!-- Événements actifs et récents -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Mes Événements</h2>
                <div class="section-actions">
                    <?php if ($user['statut_verification'] === 'verifie'): ?>
                        <a href="create_event.php" class="btn btn-primary">
                            <i data-lucide="plus"></i>
                            Créer un événement
                        </a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled id="create-event-btn-disabled"
                                style="opacity: 0.6; cursor: not-allowed; position: relative;"
                                title="Votre compte doit être vérifié avant de pouvoir créer des événements">
                            <i data-lucide="plus"></i>
                            Créer un événement
                            <span class="disabled-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7);"></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($user['statut_verification'] !== 'verifie'): ?>
                        <a href="request_verification.php" class="btn btn-warning">
                            <i data-lucide="shield-alert"></i>
                            Demander Vérification
                        </a>
                    <?php endif; ?>
                    <a href="evenements.php" class="btn btn-outline">Voir Tous</a>
                </div>
            </div>

            <?php if (empty($recent_events)): ?>
                <div class="empty-state">
                    <i data-lucide="calendar-x"></i>
                    <h3>Aucun événement créé</h3>
                    <p>Vous n'avez pas encore créé d'événement.</p>
                    <a href="create_event.php" class="btn btn-primary">
                        <i data-lucide="plus"></i>
                        Créer votre premier événement
                    </a>
                </div>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($recent_events as $event): ?>
                        <div class="event-card">
                            <div class="event-info">
                                <div class="event-header">
                                    <h4><?= htmlspecialchars($event['titre']) ?></h4>
                                    <span class="status-badge status-<?= $event['statut'] ?>">
                                        <?= ucfirst($event['statut']) ?>
                                    </span>
                                </div>
                                <p class="event-date">
                                    <i data-lucide="calendar"></i>
                                    <?= date('d/m/Y H:i', strtotime($event['date_debut'])) ?>
                                </p>
                                <div class="event-stats">
                                    <span class="inscription-count">
                                        <i data-lucide="users"></i>
                                        <?= $event['inscrits'] ?>/<?= $event['places_max'] ?? '∞' ?> inscrits
                                    </span>
                                    <?php if ($event['statut'] === 'publie'): ?>
                                        <span class="visibility-badge visible">
                                            <i data-lucide="eye"></i>
                                            Visible aux participants
                                        </span>
                                    <?php else: ?>
                                        <span class="visibility-badge hidden">
                                            <i data-lucide="eye-off"></i>
                                            Non visible
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a href="edit_event.php?id=<?= $event['id'] ?>"
                                   class="btn btn-primary btn-sm">
                                    <i data-lucide="edit"></i>
                                    Modifier
                                </a>
                                <a href="inscriptions.php?event_id=<?= $event['id'] ?>"
                                   class="btn btn-secondary btn-sm">
                                    <i data-lucide="users"></i>
                                    Inscriptions (<?= $event['inscrits'] ?>)
                                </a>
                                <a href="send_email.php?event_id=<?= $event['id'] ?>"
                                   class="btn btn-outline btn-sm">
                                    <i data-lucide="mail"></i>
                                    Email
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>


        <!-- Inscriptions récentes -->
        <?php if (!empty($recent_inscriptions)): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Inscriptions Récentes</h2>
                    <a href="inscriptions.php" class="btn btn-outline">Voir Toutes</a>
                </div>

                <div class="inscriptions-list">
                    <?php foreach ($recent_inscriptions as $inscription): ?>
                        <div class="inscription-item">
                            <div class="participant-info">
                                <strong><?= htmlspecialchars($inscription['participant_nom']) ?></strong>
                                <span class="event-title">s'est inscrit à "<?= htmlspecialchars($inscription['evenement_titre']) ?>"</span>
                            </div>
                            <div class="inscription-meta">
                                <span class="date"><?= date('d/m/Y', strtotime($inscription['date_inscription'])) ?></span>
                                <span class="status-badge status-<?= $inscription['statut'] ?>">
                                    <?= ucfirst($inscription['statut']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Dashboard organisateur amélioré */
.dashboard-header {
    text-align: center;
    margin-bottom: 3rem;
}

.dashboard-header h1 {
    color: var(--cerise-primary);
    margin-bottom: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: var(--white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: var(--cerise-primary);
    margin: 1rem 0;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.dashboard-section {
    margin-bottom: 3rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h2 {
    color: var(--grey-dark);
    margin: 0;
}

.section-actions {
    display: flex;
    gap: 1rem;
}

/* Événements */
.events-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.event-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid var(--cerise-primary);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.event-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.event-header h4 {
    margin: 0;
    color: var(--grey-dark);
}

.event-date {
    margin: 0.25rem 0;
    color: var(--grey-medium);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.event-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 0.5rem;
}

.inscription-count {
    color: var(--grey-medium);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-publie {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
}

.status-brouillon {
    background: rgba(108, 117, 125, 0.1);
    color: #495057;
}

.status-termine {
    background: rgba(23, 162, 184, 0.1);
    color: #0c5460;
}

.attestation-eligible {
    border-left: 4px solid #28a745 !important;
}

.visibility-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.visibility-badge.visible {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
}

.visibility-badge.hidden {
    background: rgba(108, 117, 125, 0.1);
    color: #495057;
}

.event-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--grey-medium);
}

.empty-state i {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.5rem;
    opacity: 0.5;
}

.empty-state h3 {
    color: var(--grey-dark);
    margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .section-actions {
        justify-content: center;
    }

    .event-card {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .event-actions {
        justify-content: center;
    }
}
</style>

<script>
// Initialiser les icônes et gérer la vérification
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Vérifier le statut de vérification toutes les 30 secondes
    <?php if ($user['statut_verification'] !== 'verifie'): ?>
    setInterval(checkVerificationStatus, 30000);

    // Vérifier immédiatement au chargement
    checkVerificationStatus();
    <?php endif; ?>

    function checkVerificationStatus() {
        fetch('../includes/check_verification_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.verified) {
                    // Recharger la page pour mettre à jour l'interface
                    location.reload();
                }
            })
            .catch(error => {
                console.log('Erreur lors de la vérification du statut:', error);
            });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
