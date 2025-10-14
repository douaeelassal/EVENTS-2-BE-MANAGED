<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité - seulement les participants connectés
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$participant_id = $_SESSION['user_id'];

// Récupération des attestations du participant
$stmt = $db->prepare("
    SELECT a.*, e.titre as event_title, e.date_debut as event_date,
           e.lieu as event_lieu, i.date_inscription
    FROM attestations a
    JOIN inscriptions i ON a.inscription_id = i.id
    JOIN evenements e ON i.evenement_id = e.id
    WHERE i.utilisateur_id = ?
    ORDER BY a.date_generation DESC
");
$stmt->execute([$participant_id]);
$attestations = $stmt->fetchAll();

include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="award"></i>
                    Mes Attestations
                </h1>
                <p class="dashboard-subtitle">
                    Téléchargez vos attestations de participation
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left"></i>
                    Retour au Dashboard
                </a>
            </div>
        </div>

        <?php if (empty($attestations)): ?>
            <div class="empty-state">
                <i data-lucide="file-x"></i>
                <h3>Aucune attestation disponible</h3>
                <p>Vous n'avez pas encore d'attestations de participation.</p>
                <p>Les attestations sont générées automatiquement une fois l'événement terminé.</p>
            </div>
        <?php else: ?>
            <div class="attestations-grid">
                <?php foreach ($attestations as $attestation): ?>
                    <div class="attestation-card">
                        <div class="attestation-header">
                            <h3><?= htmlspecialchars($attestation['event_title']) ?></h3>
                            <span class="attestation-date">
                                <?= date('d/m/Y', strtotime($attestation['date_generation'])) ?>
                            </span>
                        </div>

                        <div class="attestation-info">
                            <div class="info-item">
                                <i data-lucide="calendar"></i>
                                <span><?= date('d/m/Y H:i', strtotime($attestation['event_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <i data-lucide="map-pin"></i>
                                <span><?= htmlspecialchars($attestation['event_lieu'] ?? 'Non spécifié') ?></span>
                            </div>
                            <div class="info-item">
                                <i data-lucide="user-check"></i>
                                <span>Inscrit le <?= date('d/m/Y', strtotime($attestation['date_inscription'])) ?></span>
                            </div>
                        </div>

                        <div class="attestation-actions">
                            <a href="../attestations/<?= htmlspecialchars($attestation['chemin_fichier_pdf']) ?>"
                               class="btn btn-primary" target="_blank">
                                <i data-lucide="download"></i>
                                Télécharger PDF
                            </a>
                        </div>

                        <div class="attestation-footer">
                            <small class="attestation-number">
                                N° <?= htmlspecialchars($attestation['numero_unique']) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Dashboard Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--grey-light);
}

.dashboard-header-content h1 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 0.5rem 0;
    color: var(--grey-dark);
}

.dashboard-header-content .dashboard-subtitle {
    color: var(--grey-medium);
    margin: 0;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--grey-medium);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: var(--grey-dark);
}

/* Attestations grid */
.attestations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.attestation-card {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.attestation-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.attestation-header {
    background: linear-gradient(135deg, #e91e63, #c2185b);
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.attestation-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.attestation-date {
    background: rgba(255,255,255,0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.attestation-info {
    padding: 1.5rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    color: var(--grey-medium);
    font-size: 0.9rem;
}

.info-item i {
    color: var(--cerise-primary);
    width: 16px;
}

.attestation-actions {
    padding: 1.5rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.attestation-footer {
    background: var(--grey-light);
    padding: 1rem 1.5rem;
    text-align: center;
    border-top: 1px solid #eee;
}

.attestation-number {
    color: var(--grey-medium);
    font-size: 0.8rem;
}

/* Boutons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
    justify-content: center;
}

.btn-primary {
    background: var(--cerise-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--cerise-secondary);
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--grey-light);
    color: var(--grey-dark);
}

.btn-secondary:hover {
    background: #ddd;
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .dashboard-actions {
        justify-content: center;
    }

    .attestations-grid {
        grid-template-columns: 1fr;
    }

    .attestation-actions {
        flex-direction: column;
    }

    .btn {
        flex: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

<?php include '../includes/footer.php'; ?>
