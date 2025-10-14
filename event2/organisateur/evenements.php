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

$page_title = "Gestion des Événements - Organisateur EVENT2";
$db = Database::getInstance();

// Gestion des filtres
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Construction de la requête
$where_conditions = ["organisateur_id = ?"];
$params = [$_SESSION['user_id']];

if (!empty($status_filter)) {
    $where_conditions[] = "statut = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "titre LIKE ?";
    $params[] = "%$search%";
}

// Requête principale
$sql = "
    SELECT e.*, u.nom_complet as organisateur_nom,
           COUNT(i.id) as total_inscriptions,
           COUNT(CASE WHEN i.statut = 'confirme' THEN 1 END) as inscriptions_confirmees
    FROM evenements e
    LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
    LEFT JOIN inscriptions i ON e.id = i.evenement_id
    WHERE " . implode(" AND ", $where_conditions) . "
    GROUP BY e.id
    ORDER BY e.date_debut DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

// Statistiques
$total_evenements = count($evenements);
$evenements_actifs = count(array_filter($evenements, fn($e) => $e['statut'] === 'actif'));
$evenements_termines = count(array_filter($evenements, fn($e) => $e['statut'] === 'termine'));
$total_inscriptions = array_sum(array_column($evenements, 'total_inscriptions'));

// Gestion des actions rapides
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $event_id = (int)($_POST['event_id'] ?? 0);

    if ($action === 'cancel_event' && $event_id > 0) {
        try {
            // Vérifier que l'événement appartient à l'organisateur
            $stmt = $db->prepare("SELECT titre, statut FROM evenements WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $event = $stmt->fetch();

            if (!$event) {
                throw new Exception("Événement non trouvé ou accès non autorisé");
            }

            // Changer le statut en "annule"
            $stmt = $db->prepare("UPDATE evenements SET statut = 'annule' WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'cancel_event', ?)");
            $stmt->execute([$_SESSION['user_id'], "Annulation événement ID: $event_id - {$event['titre']}"]);

            $message = "Événement annulé avec succès";
            $message_type = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
        }
    }

    if ($action === 'delete_event' && $event_id > 0) {
        try {
            // Vérifier que l'événement appartient à l'organisateur
            $stmt = $db->prepare("SELECT titre FROM evenements WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $event = $stmt->fetch();

            if (!$event) {
                throw new Exception("Événement non trouvé ou accès non autorisé");
            }

            // Supprimer d'abord les inscriptions liées
            $stmt = $db->prepare("DELETE FROM inscriptions WHERE evenement_id = ?");
            $stmt->execute([$event_id]);

            // Supprimer l'événement
            $stmt = $db->prepare("DELETE FROM evenements WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'delete_event', ?)");
            $stmt->execute([$_SESSION['user_id'], "Suppression événement ID: $event_id - {$event['titre']}"]);

            $message = "Événement supprimé définitivement";
            $message_type = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
        }
    }

    if ($action === 'toggle_status' && $event_id > 0) {
        try {
            // Récupérer le statut actuel
            $stmt = $db->prepare("SELECT statut FROM evenements WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $current_event = $stmt->fetch();

            if (!$current_event) {
                throw new Exception("Événement non trouvé");
            }

            // Nouveau statut
            $new_status = $current_event['statut'] === 'actif' ? 'inactif' : 'actif';

            $stmt = $db->prepare("UPDATE evenements SET statut = ? WHERE id = ? AND organisateur_id = ?");
            $stmt->execute([$new_status, $event_id, $_SESSION['user_id']]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'toggle_event_status', ?)");
            $stmt->execute([$_SESSION['user_id'], "Changement statut événement ID: $event_id vers $new_status"]);

            $message = "Statut de l'événement mis à jour";
            $message_type = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
        }
    }

    if ($message_type === 'success') {
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }
}

include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="calendar"></i>
                    Mes Événements
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo number_format($total_evenements); ?> événements créés
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="create_event.php" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Créer un Événement
                </a>
                <a href="stats.php" class="btn btn-secondary">
                    <i data-lucide="bar-chart-3"></i>
                    Statistiques
                </a>
            </div>
        </div>

        <!-- Message d'alerte -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> fade-in">
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filters-section card">
            <form method="GET" class="filters-form">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">
                            <i data-lucide="search"></i>
                            Recherche
                        </label>
                        <input type="text" id="search" name="search" class="form-control"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Titre de l'événement...">
                    </div>
                    <div class="filter-group">
                        <label for="status">
                            <i data-lucide="filter"></i>
                            Statut
                        </label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $status_filter === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="termine" <?php echo $status_filter === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                            <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="search"></i>
                            Filtrer
                        </button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                            <i data-lucide="x"></i>
                            Réinitialiser
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistiques rapides -->
        <div class="stats-summary">
            <div class="stat-badge">
                <i data-lucide="play-circle"></i>
                <span><?php echo $evenements_actifs; ?> actifs</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="check-circle"></i>
                <span><?php echo $evenements_termines; ?> terminés</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="users"></i>
                <span><?php echo number_format($total_inscriptions); ?> inscriptions</span>
            </div>
        </div>

        <!-- Grille des événements -->
        <?php if (empty($evenements)): ?>
            <div class="empty-state card">
                <i data-lucide="calendar-x"></i>
                <h3>Aucun événement trouvé</h3>
                <p>Vous n'avez pas encore créé d'événement ou aucun ne correspond à vos critères.</p>
                <a href="create_event.php" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Créer votre premier événement
                </a>
            </div>
        <?php else: ?>
             <div class="events-container">
                 <div class="table-responsive">
                     <table class="events-table">
                         <thead>
                             <tr class="table-header">
                                 <th class="col-event">Événement</th>
                                 <th class="col-date">Date</th>
                                 <th class="col-lieu">Lieu</th>
                                 <th class="col-statut">Statut</th>
                                 <th class="col-inscriptions">Inscriptions</th>
                                 <th class="col-actions">Actions</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($evenements as $index => $evenement): ?>
                                 <tr class="event-row status-<?php echo htmlspecialchars($evenement['statut']); ?>"
                                     style="animation-delay: <?php echo $index * 0.05; ?>s"
                                     data-event-id="<?php echo $evenement['id']; ?>">
                                    <!-- Colonne Événement -->
                                    <td class="event-cell">
                                        <div class="event-info">
                                            <h4 class="event-title"><?php echo htmlspecialchars($evenement['titre']); ?></h4>
                                            <p class="event-description">
                                                <?php echo htmlspecialchars(substr($evenement['description'] ?? '', 0, 80)) .
                                                      (strlen($evenement['description'] ?? '') > 80 ? '...' : ''); ?>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Colonne Date -->
                                    <td class="date-cell">
                                        <div class="date-info">
                                            <span class="date-main"><?php echo date('d/m/Y', strtotime($evenement['date_debut'])); ?></span>
                                            <span class="date-sub"><?php echo date('H:i', strtotime($evenement['date_debut'])); ?></span>
                                        </div>
                                    </td>

                                    <!-- Colonne Lieu -->
                                    <td class="lieu-cell">
                                        <span class="lieu-text">
                                            <i data-lucide="map-pin"></i>
                                            <?php echo htmlspecialchars($evenement['lieu'] ?? 'Non spécifié'); ?>
                                        </span>
                                    </td>

                                    <!-- Colonne Statut -->
                                    <td class="statut-cell">
                                        <span class="status-badge status-<?php echo htmlspecialchars($evenement['statut']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($evenement['statut'])); ?>
                                        </span>
                                    </td>

                                    <!-- Colonne Inscriptions -->
                                    <td class="inscriptions-cell">
                                        <div class="inscriptions-info">
                                            <span class="inscriptions-count">
                                                <i data-lucide="users"></i>
                                                <?php echo $evenement['total_inscriptions']; ?>/<?php echo $evenement['places_max'] ?? '∞'; ?>
                                            </span>
                                            <div class="inscriptions-bar">
                                                <?php if (!empty($evenement['places_max']) && $evenement['places_max'] > 0): ?>
                                                    <div class="progress-fill" style="width: <?php echo min(($evenement['total_inscriptions'] / $evenement['places_max']) * 100, 100); ?>%"></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Colonne Actions -->
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <button onclick="viewDetails(<?php echo $evenement['id']; ?>)" class="btn btn-info btn-sm" title="Voir détails">
                                                <i data-lucide="eye"></i>
                                            </button>
                                            <a href="edit_event.php?id=<?php echo $evenement['id']; ?>" class="btn btn-primary btn-sm" title="Modifier">
                                                <i data-lucide="edit"></i>
                                            </a>
                                            <?php if ($evenement['statut'] === 'termine'): ?>
                                                <a href="../generate_attestation.php?event_id=<?php echo $evenement['id']; ?>"
                                                   class="btn btn-success btn-sm"
                                                   title="Générer les attestations PDF"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir générer les attestations pour tous les participants ?')">
                                                    <i data-lucide="award"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="cancelEvent(<?php echo $evenement['id']; ?>)" class="btn btn-warning btn-sm" title="Annuler">
                                                <i data-lucide="x-circle"></i>
                                            </button>
                                            <button onclick="deleteEvent(<?php echo $evenement['id']; ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* ===== TABLEAU MODERNE AVEC ANIMATIONS ===== */

/* Animation d'entrée des lignes */
@keyframes slideInFromLeft {
    0% {
        opacity: 0;
        transform: translateX(-50px) scaleX(0.8);
    }
    100% {
        opacity: 1;
        transform: translateX(0) scaleX(1);
    }
}

@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Conteneur principal */
.events-container {
    margin: 2rem 0;
    animation: fadeInUp 0.6s ease-out;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    background: white;
}

.events-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

/* En-tête du tableau */
.table-header {
    background: linear-gradient(135deg, #2d3436 0%, #D20A2E 100%);
    color: white;
    position: sticky;
    top: 0;
    z-index: 100;
}

.table-header th {
    padding: 1.2rem 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.table-header th:first-child {
    border-top-left-radius: 12px;
    padding-left: 1.5rem;
}

.table-header th:last-child {
    border-top-right-radius: 12px;
    text-align: center;
}

/* Lignes du tableau */
.event-row {
    border-bottom: 1px solid #f1f3f4;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: slideInFromLeft 0.6s ease-out forwards;
    opacity: 0;
    background: white;
}

.event-row:nth-child(even) {
    background: #fafbfc;
}

.event-row:hover {
    background: linear-gradient(135deg, rgba(210, 10, 46, 0.05) 0%, rgba(255, 255, 255, 0.8) 100%);
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 8px 25px rgba(210, 10, 46, 0.1);
    z-index: 10;
}

.event-row.status-actif:hover {
    border-left: 4px solid #28a745;
}

.event-row.status-termine:hover {
    border-left: 4px solid #17a2b8;
}

/* Cellules du tableau */
.event-cell,
.date-cell,
.lieu-cell,
.statut-cell,
.inscriptions-cell,
.actions-cell {
    padding: 1.2rem 1rem;
    vertical-align: middle;
    position: relative;
}

/* Cellule Événement */
.event-cell {
    max-width: 300px;
}

.event-title {
    font-size: 1rem;
    font-weight: 600;
    color: #2d3436;
    margin-bottom: 0.3rem;
    line-height: 1.3;
}

.event-description {
    font-size: 0.8rem;
    color: #636e72;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Cellule Date */
.date-cell {
    min-width: 100px;
}

.date-main {
    display: block;
    font-size: 0.95rem;
    font-weight: 600;
    color: #2d3436;
}

.date-sub {
    display: block;
    font-size: 0.8rem;
    color: #74b9ff;
    font-weight: 500;
}

/* Cellule Lieu */
.lieu-cell {
    min-width: 150px;
}

.lieu-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #636e72;
}

.lieu-text i {
    color: #D20A2E;
    width: 16px;
    height: 16px;
}

/* Cellule Statut */
.statut-cell {
    min-width: 100px;
}

.status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-actif {
    background: rgba(40, 167, 69, 0.15);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.status-inactif {
    background: rgba(108, 117, 125, 0.15);
    color: #495057;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-termine {
    background: rgba(23, 162, 184, 0.15);
    color: #0c5460;
    border: 1px solid rgba(23, 162, 184, 0.3);
}

.status-en_attente {
    background: rgba(255, 193, 7, 0.15);
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

/* Cellule Inscriptions */
.inscriptions-cell {
    min-width: 120px;
}

.inscriptions-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #2d3436;
    margin-bottom: 0.5rem;
}

.inscriptions-count i {
    color: #D20A2E;
    width: 16px;
    height: 16px;
}

.inscriptions-bar {
    width: 100%;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #D20A2E 0%, #fd79a8 100%);
    border-radius: 3px;
    transition: width 0.6s ease;
}

/* Cellule Actions */
.actions-cell {
    min-width: 200px;
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
}

.btn {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-decoration: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}

.btn-primary {
    background: #D20A2E;
    color: white;
}

.btn-primary:hover {
    background: #b80926;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.btn i {
    width: 14px;
    height: 14px;
}

/* Animation des éléments au survol */
.event-row:hover .event-title {
    color: #D20A2E;
    transition: color 0.3s ease;
}

.event-row:hover .inscriptions-count {
    color: #D20A2E;
    transition: color 0.3s ease;
}

/* Responsive */
@media (max-width: 1024px) {
    .table-responsive {
        margin: 0 -1rem;
        border-radius: 0;
    }

    .events-table {
        font-size: 0.8rem;
    }

    .table-header th,
    .event-cell,
    .date-cell,
    .lieu-cell,
    .statut-cell,
    .inscriptions-cell,
    .actions-cell {
        padding: 0.8rem 0.5rem;
    }

    .action-buttons {
        flex-wrap: wrap;
        gap: 0.3rem;
    }

    .btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 768px) {
    .events-container {
        margin: 1rem 0;
    }

    .events-table {
        font-size: 0.75rem;
    }

    .table-header {
        display: none;
    }

    .event-row {
        display: block;
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }

    .event-row:before {
        content: "Événement";
        display: block;
        font-weight: bold;
        color: #D20A2E;
        margin-bottom: 0.5rem;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .event-cell,
    .date-cell,
    .lieu-cell,
    .statut-cell,
    .inscriptions-cell,
    .actions-cell {
        display: block;
        padding: 0.3rem 0;
        border: none;
    }

    .event-cell:before { content: "Titre: "; font-weight: 600; }
    .date-cell:before { content: "Date: "; font-weight: 600; }
    .lieu-cell:before { content: "Lieu: "; font-weight: 600; }
    .statut-cell:before { content: "Statut: "; font-weight: 600; }
    .inscriptions-cell:before { content: "Inscriptions: "; font-weight: 600; }
    .actions-cell:before { content: "Actions: "; font-weight: 600; }

    .action-buttons {
        justify-content: flex-start;
        margin-top: 0.5rem;
    }
}
</style>

<script>
// Initialiser les icônes et animations
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    // Animation progressive des lignes
    const rows = document.querySelectorAll('.event-row');
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.style.animationPlayState = 'running';
        }, index * 50);
    });
});

// Nouvelles fonctions pour les boutons du tableau
function viewDetails(eventId) {
    // Créer une modale avec les détails de l'événement
    const modal = document.createElement('div');
    modal.className = 'details-modal';
    modal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Détails de l'événement</h3>
                    <button onclick="closeDetailsModal()" class="btn-close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Chargement des détails...</p>
                </div>
            </div>
        </div>
    `;

    // Ajouter les styles pour la modale
    if (!document.querySelector('#modal-styles')) {
        const styles = document.createElement('style');
        styles.id = 'modal-styles';
        styles.textContent = `
            .details-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(5px);
            }

            .modal-content {
                background: white;
                border-radius: 16px;
                width: 90%;
                max-width: 600px;
                max-height: 80vh;
                overflow: hidden;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                animation: modalSlideIn 0.3s ease-out;
                position: relative;
                z-index: 2001;
            }

            @keyframes modalSlideIn {
                0% {
                    opacity: 0;
                    transform: scale(0.8) translateY(-20px);
                }
                100% {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            .modal-header {
                padding: 1.5rem;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            }

            .modal-header h3 {
                margin: 0;
                color: #2d3436;
            }

            .btn-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #6c757d;
                padding: 0.5rem;
                border-radius: 50%;
                transition: all 0.3s ease;
            }

            .btn-close:hover {
                background: rgba(220, 53, 69, 0.1);
                color: #dc3545;
            }

            .modal-body {
                padding: 1.5rem;
                max-height: 60vh;
                overflow-y: auto;
            }

            .event-details {
                max-width: 100%;
            }

            .event-header-details {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid #f1f3f4;
            }

            .event-header-details h4 {
                margin: 0;
                color: #2d3436;
                font-size: 1.5rem;
            }

            .details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .detail-item {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #D20A2E;
            }

            .detail-item i {
                width: 24px;
                height: 24px;
                color: #D20A2E;
                margin-top: 0.2rem;
                flex-shrink: 0;
            }

            .detail-item label {
                font-weight: 600;
                color: #2d3436;
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
                display: block;
            }

            .detail-item p {
                margin: 0;
                color: #636e72;
                font-size: 1rem;
                line-height: 1.4;
            }

            .event-description-full {
                background: #fff9e6;
                padding: 1.5rem;
                border-radius: 8px;
                border-left: 4px solid #ffc107;
                margin-bottom: 2rem;
            }

            .event-description-full h5 {
                margin: 0 0 1rem 0;
                color: #856404;
                font-size: 1.1rem;
            }

            .event-description-full p {
                margin: 0;
                color: #495057;
                line-height: 1.6;
            }

            .event-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
        `;
        document.head.appendChild(styles);
    }

    document.body.appendChild(modal);

    // Charger les détails via requête AJAX
    fetch(`get_event_details.php?event_id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.event;
                modal.querySelector('.modal-body').innerHTML = `
                    <div class="event-details">
                        <div class="event-header-details">
                            <h4>${event.titre}</h4>
                            <span class="status-badge status-${event.statut}">${event.statut}</span>
                        </div>

                        <div class="details-grid">
                            <div class="detail-item">
                                <i data-lucide="calendar"></i>
                                <div>
                                    <label>Date de début</label>
                                    <p>${new Date(event.date_debut).toLocaleDateString('fr-FR', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}</p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <i data-lucide="calendar"></i>
                                <div>
                                    <label>Date de fin</label>
                                    <p>${event.date_fin ? new Date(event.date_fin).toLocaleDateString('fr-FR', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    }) : 'Non spécifiée'}</p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <i data-lucide="map-pin"></i>
                                <div>
                                    <label>Lieu</label>
                                    <p>${event.lieu || 'Non spécifié'}</p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <i data-lucide="users"></i>
                                <div>
                                    <label>Capacité</label>
                                    <p>${event.places_max ? event.places_max + ' places' : 'Illimitée'}</p>
                                </div>
                            </div>

                            <div class="detail-item">
                                <i data-lucide="user-check"></i>
                                <div>
                                    <label>Inscriptions</label>
                                    <p>${event.inscriptions_confirmees || 0} confirmées / ${event.total_inscriptions || 0} totales</p>
                                </div>
                            </div>

                        </div>

                        ${event.description ? `
                            <div class="event-description-full">
                                <h5>Description</h5>
                                <p>${event.description}</p>
                            </div>
                        ` : ''}

                        <div class="event-actions">
                            <a href="edit_event.php?id=${eventId}" class="btn btn-primary">
                                <i data-lucide="edit"></i>
                                Modifier
                            </a>
                            <a href="../generate_attestation.php?event_id=${eventId}" class="btn btn-success">
                                <i data-lucide="award"></i>
                                Générer attestations
                            </a>
                        </div>
                    </div>
                `;
            } else {
                modal.querySelector('.modal-body').innerHTML = `
                    <div style="text-align: center; color: #dc3545;">
                        <i data-lucide="alert-circle" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                        <h4>Erreur</h4>
                        <p>${data.message || 'Impossible de charger les détails de l\'événement.'}</p>
                        <button onclick="closeDetailsModal()" class="btn btn-primary" style="margin-top: 1rem;">Fermer</button>
                    </div>
                `;
            }
            lucide.createIcons();
        })
        .catch(error => {
            modal.querySelector('.modal-body').innerHTML = `
                <div style="text-align: center; color: #dc3545;">
                    <i data-lucide="alert-circle" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                    <h4>Erreur de connexion</h4>
                    <p>Impossible de charger les détails. Veuillez réessayer.</p>
                    <button onclick="closeDetailsModal()" class="btn btn-primary" style="margin-top: 1rem;">Fermer</button>
                </div>
            `;
            lucide.createIcons();
        });
}

function closeDetailsModal() {
    const modal = document.querySelector('.details-modal');
    if (modal) {
        modal.style.animation = 'modalSlideOut 0.3s ease-in forwards';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function cancelEvent(eventId) {
    if (confirm('Êtes-vous sûr de vouloir annuler cet événement ? Cette action peut être réversible.')) {
        // Créer et soumettre le formulaire
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="cancel_event">
            <input type="hidden" name="event_id" value="${eventId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteEvent(eventId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer définitivement cet événement ? Cette action est irréversible !')) {
        // Créer et soumettre le formulaire
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="event_id" value="${eventId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Animation pour la suppression de ligne
function animateRowDeletion(row) {
    row.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
    row.style.transform = 'translateX(-100%) scaleX(0.8)';
    row.style.opacity = '0';
    setTimeout(() => {
        row.remove();
    }, 400);
}

// Gestionnaire de clic pour les animations de lignes
document.addEventListener('click', function(e) {
    const row = e.target.closest('.event-row');
    if (row && e.target.closest('.action-buttons')) {
        // Animation de pulse sur la ligne cliquée
        row.style.animation = 'pulse 0.3s ease-out';
    }
});
</script>

<?php include '../includes/footer.php'; ?>