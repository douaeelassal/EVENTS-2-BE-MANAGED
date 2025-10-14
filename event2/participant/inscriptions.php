<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

// Gestion de l'inscription à un événement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }

        $event_id = (int)($_POST['event_id'] ?? 0);

        if (!$event_id) {
            throw new Exception('ID d\'événement manquant');
        }

        // Vérifier que l'événement existe et est publié
        $stmt = $db->prepare("
            SELECT id, titre, places_max, statut,
                   (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ?) as inscrits
            FROM evenements
            WHERE id = ? AND statut = 'publie'
        ");
        $stmt->execute([$event_id, $event_id]);
        $event = $stmt->fetch();

        if (!$event) {
            throw new Exception('Événement non trouvé ou non disponible');
        }

        // Vérifier si l'utilisateur est déjà inscrit
        // Détecter automatiquement la bonne colonne
        $columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_COLUMN);
        $user_column = in_array('participant_id', $columns) ? 'participant_id' : 'utilisateur_id';

        $stmt = $db->prepare("
            SELECT id FROM inscriptions
            WHERE evenement_id = ? AND $user_column = ?
        ");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            throw new Exception('Vous êtes déjà inscrit à cet événement');
        }

        // Vérifier la capacité
        if ($event['places_max'] && $event['inscrits'] >= $event['places_max']) {
            throw new Exception('Cet événement est complet');
        }

        // Créer l'inscription avec statut "en_attente" pour les participants
        $user_column = in_array('participant_id', $columns) ? 'participant_id' : 'utilisateur_id';
        $stmt = $db->prepare("
            INSERT INTO inscriptions (evenement_id, $user_column, statut, date_inscription)
            VALUES (?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([$event_id, $_SESSION['user_id']]);

        // Log de l'inscription
        $stmt = $db->prepare("
            INSERT INTO logs_audit (user_id, action, details)
            VALUES (?, 'inscription', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Inscription à l'événement ID: $event_id"
        ]);

        $success = 'Inscription enregistrée avec succès ! Elle sera examinée par l\'organisateur.';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des inscriptions de l'utilisateur
$columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_COLUMN);
$user_column = in_array('participant_id', $columns) ? 'participant_id' : 'utilisateur_id';

$stmt = $db->prepare("
    SELECT e.*, i.statut, i.date_inscription as date_inscription_user
    FROM inscriptions i
    JOIN evenements e ON e.id = i.evenement_id
    WHERE i.$user_column = ?
    ORDER BY e.date_debut DESC
");
$stmt->execute([$_SESSION['user_id']]);
$inscriptions = $stmt->fetchAll();

// Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="bookmark"></i>
                    Mes Inscriptions
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo count($inscriptions); ?> événement(s) - Gérez vos participations
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="evenements.php" class="btn btn-primary">
                    <i data-lucide="calendar"></i>
                    Découvrir d'autres événements
                </a>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Inscription rapide (si événement spécifié en GET) -->
        <?php if (isset($_GET['event_id']) && !$error && !$success): ?>
            <?php
            $event_id = (int)$_GET['event_id'];
            $stmt = $db->prepare("
                SELECT id, titre, date_debut, lieu, places_max, statut,
                       (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ?) as inscrits
                FROM evenements
                WHERE id = ? AND statut = 'publie'
            ");
            $stmt->execute([$event_id, $event_id]);
            $event = $stmt->fetch();
            ?>
            <?php if ($event): ?>
                <div class="inscription-form card">
                    <h3>Inscription à : <?= htmlspecialchars($event['titre']) ?></h3>
                    <div class="event-summary">
                        <p><i data-lucide="calendar"></i> <?= date('d/m/Y H:i', strtotime($event['date_debut'])) ?></p>
                        <p><i data-lucide="map-pin"></i> <?= htmlspecialchars($event['lieu']) ?></p>
                        <p><i data-lucide="users"></i> <?= $event['inscrits'] ?>/<?= $event['places_max'] ?? '∞' ?> inscrits</p>
                    </div>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="user-plus"></i>
                            Confirmer l'inscription
                        </button>
                        <a href="inscriptions.php" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Liste des inscriptions -->
        <?php if (empty($inscriptions)): ?>
            <div class="empty-state card">
                <i data-lucide="bookmark-x"></i>
                <h3>Aucune inscription</h3>
                <p>Vous n'êtes inscrit à aucun événement pour le moment.</p>
                <a href="evenements.php" class="btn btn-primary">
                    <i data-lucide="calendar"></i>
                    Découvrir les événements
                </a>
            </div>
        <?php else: ?>
            <div class="inscriptions-grid">
                <?php foreach ($inscriptions as $inscription): ?>
                    <div class="inscription-card card <?php echo 'status-' . $inscription['statut']; ?>">
                        <div class="inscription-header">
                            <h3><?php echo htmlspecialchars($inscription['titre']); ?></h3>
                            <span class="status-badge status-<?php echo $inscription['statut']; ?>">
                                <?php echo ucfirst($inscription['statut']); ?>
                            </span>
                        </div>

                        <div class="inscription-content">
                            <div class="inscription-meta">
                                <div class="meta-item">
                                    <i data-lucide="calendar"></i>
                                    <span><?php echo date('d/m/Y H:i', strtotime($inscription['date_debut'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i data-lucide="clock"></i>
                                    <span>Inscrit le <?php echo date('d/m/Y', strtotime($inscription['date_inscription_user'])); ?></span>
                                </div>
                                <?php if ($inscription['lieu']): ?>
                                    <div class="meta-item">
                                        <i data-lucide="map-pin"></i>
                                        <span><?php echo htmlspecialchars($inscription['lieu']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($inscription['description']): ?>
                                <div class="inscription-description">
                                    <?php echo htmlspecialchars(substr($inscription['description'], 0, 150)) .
                                             (strlen($inscription['description']) > 150 ? '...' : ''); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="inscription-footer">
                            <div class="inscription-date">
                                <i data-lucide="clock"></i>
                                Inscrit le <?php echo date('d/m/Y', strtotime($inscription['date_inscription_user'])); ?>
                            </div>
                            <div class="inscription-actions">
                                <?php if ($inscription['statut'] === 'confirme'): ?>
                                    <a href="attestations.php?event_id=<?= $inscription['id'] ?>"
                                       class="btn btn-success btn-sm">
                                        <i data-lucide="award"></i>
                                        Attestation
                                    </a>
                                <?php elseif ($inscription['statut'] === 'annule'): ?>
                                    <span class="btn btn-danger btn-sm disabled">
                                        <i data-lucide="x-circle"></i>
                                        Annulé
                                    </span>
                                <?php else: ?>
                                    <span class="btn btn-warning btn-sm disabled">
                                        <i data-lucide="clock"></i>
                                        En attente
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Inscription rapide */
.inscription-form {
    background: linear-gradient(135deg, var(--cerise-primary) 0%, #D20A2E 100%);
    color: white;
    text-align: center;
    margin-bottom: 2rem;
}

.inscription-form h3 {
    color: white;
    margin-bottom: 1rem;
}

.event-summary {
    margin-bottom: 2rem;
}

.event-summary p {
    margin: 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.inscription-form .btn {
    margin: 0.5rem;
}

/* Grille des inscriptions */
.inscriptions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 2rem;
}

.inscription-card {
    position: relative;
    transition: all 0.3s ease;
    border-left: 4px solid var(--cerise-primary);
}

.inscription-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(210, 10, 46, 0.15);
}

.inscription-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.inscription-header h3 {
    margin: 0;
    color: var(--grey-dark);
    line-height: 1.3;
}

.inscription-content {
    margin-bottom: 1.5rem;
}

.inscription-meta {
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: var(--grey-medium);
}

.inscription-description {
    color: var(--grey-dark);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.inscription-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--grey-light);
}

.inscription-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--grey-medium);
}

.inscription-actions {
    display: flex;
    gap: 0.5rem;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-en_attente { background: rgba(255, 193, 7, 0.1); color: #856404; }
.status-confirme { background: rgba(40, 167, 69, 0.1); color: #155724; }
.status-annule { background: rgba(220, 53, 69, 0.1); color: #721c24; }

/* États vides */
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
    margin-bottom: 1rem;
    color: var(--grey-dark);
}

/* Responsive */
@media (max-width: 768px) {
    .inscriptions-grid {
        grid-template-columns: 1fr;
    }

    .inscription-card {
        margin-bottom: 1.5rem;
    }

    .inscription-header {
        flex-direction: column;
        gap: 1rem;
    }

    .inscription-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .inscription-actions {
        justify-content: center;
    }
}
</style>

<script>
// Initialiser les icônes
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

<?php include '../includes/footer.php'; ?>
