<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité - Admin et organisateurs peuvent voir les événements
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'organisateur'])) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestion des Événements - Administration EVENT2";
$db = Database::getInstance();

// Gestion de la recherche et des filtres
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.titre LIKE ? OR e.description LIKE ? OR o.nom_club LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    if ($status_filter === 'actif') {
        $where_conditions[] = "e.statut = 'actif'";
    } elseif ($status_filter === 'termine') {
        $where_conditions[] = "e.statut = 'termine'";
    } elseif ($status_filter === 'publie') {
        $where_conditions[] = "e.statut = 'publie'";
    } elseif ($status_filter === 'brouillon') {
        $where_conditions[] = "e.statut = 'brouillon'";
    } elseif ($status_filter === 'annule') {
        $where_conditions[] = "e.statut = 'annule'";
    }
}

// Requête pour compter le total
$count_sql = "
    SELECT COUNT(*)
    FROM evenements e
    LEFT JOIN organisateurs o ON e.organisateur_id = o.utilisateur_id
    LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
    WHERE " . implode(" AND ", $where_conditions);

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_events = $count_stmt->fetchColumn();

// Requête principale avec pagination
$sql = "
    SELECT e.*, o.nom_club as organisateur_nom, u.nom_complet as organisateur_utilisateur,
           COALESCE(o.nom_club, u.nom_complet, 'N/A') as nom_affichage
    FROM evenements e
    LEFT JOIN organisateurs o ON e.organisateur_id = o.utilisateur_id
    LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY e.date_debut DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Statistiques rapides basées sur le statut réel
$total_actifs = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'actif'")->fetchColumn();
$total_termines = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'termine'")->fetchColumn();
$total_publies = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'publie'")->fetchColumn();
$total_brouillons = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'brouillon'")->fetchColumn();
$total_annules = $db->query("SELECT COUNT(*) FROM evenements WHERE statut = 'annule'")->fetchColumn();

// Gestion des actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $event_id = (int)($_POST['event_id'] ?? 0);

    if ($action === 'delete_event' && $event_id > 0) {
        try {
            // Récupérer les informations de l'événement avant suppression
            $stmt = $db->prepare("SELECT titre FROM evenements WHERE id = ?");
            $stmt->execute([$event_id]);
            $event_info = $stmt->fetch();

            if (!$event_info) {
                throw new Exception("Événement non trouvé");
            }

            // Supprimer les inscriptions liées
            $stmt = $db->prepare("DELETE FROM inscriptions WHERE evenement_id = ?");
            $stmt->execute([$event_id]);

            // Supprimer l'événement
            $stmt = $db->prepare("DELETE FROM evenements WHERE id = ?");
            $stmt->execute([$event_id]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'delete_event', ?)");
            $stmt->execute([$_SESSION['user_id'], "Suppression événement: {$event_info['titre']}"]);

            $message = "Événement supprimé avec succès";
            $message_type = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
        }
    }

    if ($action === 'change_status' && $event_id > 0) {
        $new_status = $_POST['new_status'] ?? '';

        if (in_array($new_status, ['publie', 'annule', 'brouillon'])) {
            try {
                $stmt = $db->prepare("UPDATE evenements SET statut = ? WHERE id = ?");
                $stmt->execute([$new_status, $event_id]);

                // Log de l'action
                $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'change_event_status', ?)");
                $stmt->execute([$_SESSION['user_id'], "Changement statut événement ID: $event_id vers $new_status"]);

                $message = "Statut de l'événement mis à jour avec succès";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour du statut";
                $message_type = "error";
            }
        }
    }

    // Recharger la page pour refléter les changements
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
                    Gestion des Événements
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo number_format($total_events); ?> événements au total
                </p>
            </div>
        </div>

        <!-- Message d'alerte -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> fade-in">
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres et recherche -->
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
                               placeholder="Titre, description, organisateur...">
                    </div>

                    <div class="filter-group">
                        <label for="status">
                            <i data-lucide="filter"></i>
                            Statut
                        </label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="publie" <?php echo $status_filter === 'publie' ? 'selected' : ''; ?>>Publié</option>
                            <option value="termine" <?php echo $status_filter === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                            <option value="annule" <?php echo $status_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                            <option value="brouillon" <?php echo $status_filter === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
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
                <span><?php echo $total_actifs; ?> actifs</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="globe"></i>
                <span><?php echo $total_publies; ?> publiés</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="check-circle"></i>
                <span><?php echo $total_termines; ?> terminés</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="edit"></i>
                <span><?php echo $total_brouillons; ?> brouillons</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="x-circle"></i>
                <span><?php echo $total_annules; ?> annulés</span>
            </div>
        </div>

        <!-- Table des événements -->
        <div class="events-table-section card">
            <div class="table-container">
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Organisateur</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Inscrits</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i data-lucide="calendar"></i>
                                    <p>Aucun événement trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <tr class="event-row">
                                    <td>
                                        <div class="event-info">
                                            <div class="event-details">
                                                <div class="event-title">
                                                    <?php echo htmlspecialchars($event['titre']); ?>
                                                </div>
                                                <div class="event-description">
                                                    <?php echo htmlspecialchars(substr($event['description'], 0, 100) . (strlen($event['description']) > 100 ? '...' : '')); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="organisateur-info">
                                            <?php echo htmlspecialchars($event['nom_affichage'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($event['date_debut'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($event); ?>">
                                            <?php echo getStatusLabel($event); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ?");
                                        $stmt->execute([$event['id']]);
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewEvent(<?php echo $event['id']; ?>)" class="btn btn-sm btn-secondary" data-tooltip="Voir détails">
                                                <i data-lucide="eye"></i>
                                            </button>
                                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                                <?php if ($event['statut'] === 'annule'): ?>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir réactiver cet événement ?')">
                                                        <input type="hidden" name="action" value="change_status">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <input type="hidden" name="new_status" value="publie">
                                                        <button type="submit" class="btn btn-sm btn-success" data-tooltip="Réactiver">
                                                            <i data-lucide="check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" data-tooltip="Supprimer">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<style>
/* Filtres */
.filters-section {
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--grey-dark);
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

/* Badges de statistiques */
.stats-summary {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--grey-light);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-size: 0.875rem;
    color: var(--grey-dark);
}

/* Table des événements */
.events-table-section {
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
}

.events-table {
    width: 100%;
    border-collapse: collapse;
}

.events-table th {
    background: var(--grey-light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--grey-dark);
    position: sticky;
    top: 0;
}

.events-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--grey-light);
}

.event-row:hover {
    background: rgba(210, 10, 46, 0.02);
}

.event-title {
    font-weight: 600;
    color: var(--grey-dark);
    margin-bottom: 0.25rem;
}

.event-description {
    font-size: 0.875rem;
    color: var(--grey-medium);
    line-height: 1.4;
}

/* Statuts */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-actif { background: rgba(40, 167, 69, 0.1); color: #155724; }
.status-publie { background: rgba(0, 123, 255, 0.1); color: #004085; }
.status-termine { background: rgba(108, 117, 125, 0.1); color: #495057; }
.status-annule { background: rgba(220, 53, 69, 0.1); color: #721c24; }
.status-brouillon { background: rgba(255, 193, 7, 0.1); color: #856404; }

/* Messages d'alerte */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.alert-error {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

/* Actions */
.action-buttons {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.dropdown {
    position: relative;
}

.dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid var(--grey-light);
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 1000;
    min-width: 180px;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.5rem 1rem;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--grey-dark);
    text-align: left;
}

.dropdown-item:hover {
    background: var(--grey-light);
}

.dropdown-item:first-child {
    border-radius: 8px 8px 0 0;
}

.dropdown-item:last-child {
    border-radius: 0 0 8px 8px;
}

/* Modal d'événement */
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
</style>

<script>
// Initialiser les icônes
lucide.createIcons();

// Gestion des dropdowns
function toggleDropdown(eventId) {
    const dropdown = document.getElementById('dropdown-' + eventId);
    const isVisible = dropdown.classList.contains('show');

    // Fermer tous les dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
    });

    // Ouvrir le dropdown cliqué si nécessaire
    if (!isVisible) {
        dropdown.classList.add('show');
    }
}

// Fermer les dropdowns en cliquant ailleurs
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Fonctions d'action
async function viewEvent(eventId) {
    try {
        // Créer une modale pour afficher les détails
        const modal = document.createElement('div');
        modal.className = 'event-modal';
        modal.innerHTML = `
            <div class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Détails de l'événement</h3>
                        <button onclick="closeEventModal()" class="btn-close">
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
        if (!document.querySelector('#event-modal-styles')) {
            const styles = document.createElement('style');
            styles.id = 'event-modal-styles';
            styles.textContent = `
                .event-modal {
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
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(modal);

        // Charger les détails via requête AJAX
        const response = await fetch(`get_event_details.php?event_id=${eventId}`);
        const data = await response.json();

        if (data.success) {
            const event = data.event;
            modal.querySelector('.modal-body').innerHTML = `
                <div class="event-details">
                    <div class="event-header-details">
                        <h4>${event.titre}</h4>
                        <span class="status-badge status-${event.statut}">${event.statut_label}</span>
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

                        ${event.date_fin ? `
                            <div class="detail-item">
                                <i data-lucide="calendar-check"></i>
                                <div>
                                    <label>Date de fin</label>
                                    <p>${new Date(event.date_fin).toLocaleDateString('fr-FR', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}</p>
                                </div>
                            </div>
                        ` : ''}

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
                </div>
            `;
        } else {
            modal.querySelector('.modal-body').innerHTML = `
                <div style="text-align: center; color: #dc3545;">
                    <i data-lucide="alert-circle" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                    <h4>Erreur</h4>
                    <p>${data.message || 'Impossible de charger les détails de l\'événement.'}</p>
                </div>
            `;
        }

        lucide.createIcons();

    } catch (error) {
        alert('Erreur lors du chargement des détails: ' + error.message);
    }
}

function closeEventModal() {
    const modal = document.querySelector('.event-modal');
    if (modal) {
        modal.remove();
    }
}

// Fermer modal en cliquant en dehors
document.addEventListener('click', function(event) {
    const modal = document.querySelector('.event-modal');
    if (modal && event.target === modal) {
        closeEventModal();
    }
});

// Export des événements
function exportEvents() {
    const searchParams = new URLSearchParams(window.location.search);
    window.open('export-events.php?' + searchParams.toString(), '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>

<?php
// Fonctions utilitaires PHP
function getStatusClass($event) {
    $status = $event['statut'] ?? 'brouillon';

    switch ($status) {
        case 'actif':
            return 'status-actif';
        case 'publie':
            return 'status-publie';
        case 'termine':
            return 'status-termine';
        case 'annule':
            return 'status-annule';
        case 'brouillon':
        default:
            return 'status-brouillon';
    }
}

function getStatusLabel($event) {
    $status = $event['statut'] ?? 'brouillon';

    switch ($status) {
        case 'actif':
            return 'Actif';
        case 'publie':
            return 'Publié';
        case 'termine':
            return 'Terminé';
        case 'annule':
            return 'Annulé';
        case 'brouillon':
        default:
            return 'Brouillon';
    }
}

// Helper function for JavaScript
function getStatusLabelJS($status) {
    switch ($status) {
        case 'actif':
            return 'Actif';
        case 'publie':
            return 'Publié';
        case 'termine':
            return 'Terminé';
        case 'annule':
            return 'Annulé';
        case 'brouillon':
        default:
            return 'Brouillon';
    }
}
?>