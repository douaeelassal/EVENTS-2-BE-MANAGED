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

$db = Database::getInstance();
$error = '';
$success = '';

// Gestion des actions (changer statut, supprimer)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }

        $action = $_POST['action'] ?? '';
        $inscription_id = (int)($_POST['inscription_id'] ?? 0);

        if (!$inscription_id) {
            throw new Exception('ID d\'inscription manquant');
        }

        // Vérifier que l'inscription appartient bien à l'organisateur
        $stmt = $db->prepare("
            SELECT i.*, e.titre, u.nom_complet
            FROM inscriptions i
            JOIN evenements e ON e.id = i.evenement_id
            JOIN utilisateurs u ON u.id = i.utilisateur_id
            WHERE i.id = ? AND e.organisateur_id = ?
        ");
        $stmt->execute([$inscription_id, $_SESSION['user_id']]);
        $inscription = $stmt->fetch();

        if (!$inscription) {
            throw new Exception('Inscription non trouvée ou accès non autorisé');
        }

        switch ($action) {
            case 'confirmer':
                $stmt = $db->prepare("UPDATE inscriptions SET statut = 'confirme' WHERE id = ?");
                $stmt->execute([$inscription_id]);
                $success = "Inscription de {$inscription['nom_complet']} confirmée avec succès";
                break;

            case 'attente':
                $stmt = $db->prepare("UPDATE inscriptions SET statut = 'en_attente' WHERE id = ?");
                $stmt->execute([$inscription_id]);
                $success = "Inscription de {$inscription['nom_complet']} remise en attente";
                break;

            case 'supprimer':
                $stmt = $db->prepare("DELETE FROM inscriptions WHERE id = ?");
                $stmt->execute([$inscription_id]);
                $success = "Inscription de {$inscription['nom_complet']} supprimée";
                break;

            default:
                throw new Exception('Action non reconnue');
        }

        // Log de l'action
        $stmt = $db->prepare("
            INSERT INTO logs_audit (user_id, action, details)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            'gestion_inscription',
            "Action '$action' sur inscription ID: $inscription_id"
        ]);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des inscriptions avec détails
$stmt = $db->prepare("
    SELECT i.*, u.nom_complet, u.email, e.titre, e.date_debut
    FROM inscriptions i
    JOIN utilisateurs u ON u.id = i.utilisateur_id
    JOIN evenements e ON e.id = i.evenement_id
    WHERE e.organisateur_id = ?
    ORDER BY i.date_inscription DESC
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
                    <i data-lucide="users"></i>
                    Gestion des Inscriptions
                </h1>
                <p class="dashboard-subtitle">
                    Gérez les inscriptions à vos événements
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left"></i>
                    Retour au Dashboard
                </a>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?= array_sum(array_column($inscriptions, 'statut') === [] ? [0] : array_map(fn($ins) => $ins['statut'] === 'confirme' ? 1 : 0, $inscriptions)) ?>
                    </div>
                    <div class="stat-label">Confirmées</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?= array_sum(array_column($inscriptions, 'statut') === [] ? [0] : array_map(fn($ins) => $ins['statut'] === 'en_attente' ? 1 : 0, $inscriptions)) ?>
                    </div>
                    <div class="stat-label">En attente</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="calendar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?= count(array_unique(array_column($inscriptions, 'evenement_id'))) ?>
                    </div>
                    <div class="stat-label">Événements</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?= count($inscriptions) ?>
                    </div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
        </div>

        <!-- Table des inscriptions -->
        <div class="table-container">
            <?php if (empty($inscriptions)): ?>
                <div class="empty-state">
                    <i data-lucide="inbox"></i>
                    <h3>Aucune inscription</h3>
                    <p>Vous n'avez pas encore d'inscriptions à vos événements.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Événement</th>
                                <th>Statut</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscriptions as $inscription): ?>
                                <tr>
                                    <td>
                                        <div class="participant-info">
                                            <div class="participant-name">
                                                <?= htmlspecialchars($inscription['nom_complet']) ?>
                                            </div>
                                            <div class="participant-email">
                                                <?= htmlspecialchars($inscription['email']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="event-info">
                                            <div class="event-title">
                                                <?= htmlspecialchars($inscription['titre']) ?>
                                            </div>
                                            <div class="event-date">
                                                <?= date('d/m/Y H:i', strtotime($inscription['date_debut'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $inscription['statut'] ?>">
                                            <?= htmlspecialchars($inscription['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($inscription['date_inscription'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($inscription['statut'] === 'en_attente'): ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Confirmer cette inscription ?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="inscription_id" value="<?= $inscription['id'] ?>">
                                                    <input type="hidden" name="action" value="confirmer">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Confirmer">
                                                        <i data-lucide="check"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Remettre en attente ?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="inscription_id" value="<?= $inscription['id'] ?>">
                                                    <input type="hidden" name="action" value="attente">
                                                    <button type="submit" class="btn btn-warning btn-sm" title="Remettre en attente">
                                                        <i data-lucide="clock"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-info btn-sm" title="Détails"
                                                    onclick="showParticipantDetails(<?= htmlspecialchars(json_encode($inscription)) ?>)">
                                                <i data-lucide="eye"></i>
                                            </button>

                                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette inscription ?')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="inscription_id" value="<?= $inscription['id'] ?>">
                                                <input type="hidden" name="action" value="supprimer">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal des détails participant -->
<div id="participantModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Détails du Participant</h3>
            <button type="button" class="modal-close" onclick="closeParticipantModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="participantDetails">
                <!-- Les détails seront insérés ici par JavaScript -->
            </div>
        </div>
    </div>
</div>
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

.dashboard-actions {
    display: flex;
    gap: 1rem;
}

/* Alertes */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-error {
    background: #fee;
    border: 1px solid #fcc;
    color: #c33;
}

.alert-success {
    background: #efe;
    border: 1px solid #cfc;
    color: #363;
}

.alert i {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

/* Statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--white);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--cerise-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--grey-dark);
    line-height: 1;
}

.stat-label {
    color: var(--grey-medium);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Table */
.table-container {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--grey-light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--grey-dark);
    border-bottom: 2px solid var(--cerise-primary);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.data-table tr:hover {
    background: #f8f9fa;
}

/* Participant info */
.participant-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.participant-name {
    font-weight: 600;
    color: var(--grey-dark);
}

.participant-email {
    font-size: 0.875rem;
    color: var(--grey-medium);
}

/* Event info */
.event-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.event-title {
    font-weight: 600;
    color: var(--grey-dark);
}

.event-date {
    font-size: 0.875rem;
    color: var(--grey-medium);
}

/* Statut badges */
.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-confirme {
    background: #d4edda;
    color: #155724;
}

.status-en_attente {
    background: #fff3cd;
    color: #856404;
}

.status-annule {
    background: #f8d7da;
    color: #721c24;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-buttons form {
    margin: 0;
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

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
    transform: translateY(-2px);
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
    transform: translateY(-2px);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
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

.empty-state p {
    margin: 0;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--white);
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--grey-light);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--grey-medium);
    padding: 0.5rem;
}

.modal-body {
    padding: 1.5rem;
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

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .stat-card {
        padding: 1rem;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }

    .btn-sm {
        justify-content: center;
    }
}
</style>

<script>
// Initialiser les icônes
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});

// Fonction pour afficher les détails du participant
function showParticipantDetails(inscription) {
    const modal = document.getElementById('participantModal');
    const details = document.getElementById('participantDetails');

    details.innerHTML = `
        <div class="participant-details">
            <div class="detail-section">
                <h4>Informations du Participant</h4>
                <div class="detail-row">
                    <strong>Nom complet:</strong>
                    <span>${inscription.nom_complet}</span>
                </div>
                <div class="detail-row">
                    <strong>Email:</strong>
                    <span>${inscription.email}</span>
                </div>
            </div>

            <div class="detail-section">
                <h4>Informations de l'Événement</h4>
                <div class="detail-row">
                    <strong>Titre:</strong>
                    <span>${inscription.titre}</span>
                </div>
                <div class="detail-row">
                    <strong>Date:</strong>
                    <span>${new Date(inscription.date_debut).toLocaleDateString('fr-FR', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</span>
                </div>
            </div>

            <div class="detail-section">
                <h4>Informations d'Inscription</h4>
                <div class="detail-row">
                    <strong>Statut:</strong>
                    <span class="status-badge status-${inscription.statut}">${inscription.statut}</span>
                </div>
                <div class="detail-row">
                    <strong>Date d'inscription:</strong>
                    <span>${new Date(inscription.date_inscription).toLocaleDateString('fr-FR', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</span>
                </div>
            </div>
        </div>

        <style>
        .participant-details {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .detail-section {
            background: var(--grey-light);
            padding: 1rem;
            border-radius: 8px;
        }

        .detail-section h4 {
            margin: 0 0 0.75rem 0;
            color: var(--grey-dark);
            border-bottom: 1px solid #ddd;
            padding-bottom: 0.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-row strong {
            color: var(--grey-dark);
        }

        .detail-row span {
            color: var(--grey-medium);
            text-align: right;
        }
        </style>
    `;

    modal.style.display = 'flex';
}

// Fonction pour fermer la modal
function closeParticipantModal() {
    document.getElementById('participantModal').style.display = 'none';
}

// Fermer la modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('participantModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
