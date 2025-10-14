<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Gestion des Inscriptions - Administration EVENT2";
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
    $where_conditions[] = "(u.nom_complet LIKE ? OR u.email LIKE ? OR e.titre LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    if ($status_filter === 'en_attente') {
        $where_conditions[] = "i.statut = 'en_attente'";
    } elseif ($status_filter === 'confirme') {
        $where_conditions[] = "i.statut = 'confirme'";
    } elseif ($status_filter === 'annule') {
        $where_conditions[] = "i.statut = 'annule'";
    }
}

// Requête pour compter le total
$count_sql = "
    SELECT COUNT(*)
    FROM inscriptions i
    JOIN utilisateurs u ON i.utilisateur_id = u.id
    JOIN evenements e ON i.evenement_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_inscriptions = $count_stmt->fetchColumn();

// Requête principale avec pagination
$sql = "
    SELECT i.*, u.nom_complet, u.email, e.titre as evenement_titre
    FROM inscriptions i
    JOIN utilisateurs u ON i.utilisateur_id = u.id
    JOIN evenements e ON i.evenement_id = e.id
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY i.date_inscription DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$inscriptions = $stmt->fetchAll();

// Statistiques rapides
$total_en_attente = $db->query("SELECT COUNT(*) FROM inscriptions WHERE statut = 'en_attente'")->fetchColumn();
$total_confirme = $db->query("SELECT COUNT(*) FROM inscriptions WHERE statut = 'confirme'")->fetchColumn();
$total_annule = $db->query("SELECT COUNT(*) FROM inscriptions WHERE statut = 'annule'")->fetchColumn();

// Gestion des actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inscription_id = (int)($_POST['inscription_id'] ?? 0);

    if ($action === 'update_status' && $inscription_id > 0) {
        $new_status = $_POST['new_status'] ?? '';

        if (in_array($new_status, ['en_attente', 'confirme', 'annule'])) {
            try {
                $stmt = $db->prepare("UPDATE inscriptions SET statut = ? WHERE id = ?");
                $stmt->execute([$new_status, $inscription_id]);

                // Log de l'action
                $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'update_inscription_status', ?)");
                $stmt->execute([$_SESSION['user_id'], "Changement statut inscription ID: $inscription_id vers $new_status"]);

                $message = "Statut de l'inscription mis à jour avec succès";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour";
                $message_type = "error";
            }
        }
    }

    if ($action === 'delete_inscription' && $inscription_id > 0) {
        try {
            // Récupérer les informations de l'inscription avant suppression
            $stmt = $db->prepare("
                SELECT i.id, u.nom_complet, e.titre
                FROM inscriptions i
                JOIN utilisateurs u ON i.utilisateur_id = u.id
                JOIN evenements e ON i.evenement_id = e.id
                WHERE i.id = ?
            ");
            $stmt->execute([$inscription_id]);
            $inscription_info = $stmt->fetch();

            if (!$inscription_info) {
                throw new Exception("Inscription non trouvée");
            }

            // Supprimer l'inscription
            $stmt = $db->prepare("DELETE FROM inscriptions WHERE id = ?");
            $stmt->execute([$inscription_id]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'delete_inscription', ?)");
            $stmt->execute([$_SESSION['user_id'], "Suppression inscription: {$inscription_info['nom_complet']} - {$inscription_info['titre']}"]);

            $message = "Inscription supprimée avec succès";
            $message_type = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
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
                    <i data-lucide="user-check"></i>
                    Gestion des Inscriptions
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo number_format($total_inscriptions); ?> inscriptions au total
                </p>
            </div>
            <div class="dashboard-actions">
                <button onclick="exportInscriptions()" class="btn btn-secondary">
                    <i data-lucide="download"></i>
                    Exporter
                </button>
                <a href="stats.php" class="btn btn-primary">
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
                               placeholder="Participant, email, événement...">
                    </div>

                    <div class="filter-group">
                        <label for="status">
                            <i data-lucide="filter"></i>
                            Statut
                        </label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="confirme" <?php echo $status_filter === 'confirme' ? 'selected' : ''; ?>>Confirmé</option>
                            <option value="annule" <?php echo $status_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
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
                <i data-lucide="clock"></i>
                <span><?php echo $total_en_attente; ?> en attente</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="check-circle"></i>
                <span><?php echo $total_confirme; ?> confirmées</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="x-circle"></i>
                <span><?php echo $total_annule; ?> annulées</span>
            </div>
        </div>

        <!-- Table des inscriptions -->
        <div class="inscriptions-table-section card">
            <div class="table-container">
                <table class="inscriptions-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select-all">
                                Inscription
                            </th>
                            <th>Participant</th>
                            <th>Événement</th>
                            <th>Date d'inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscriptions)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i data-lucide="user-check"></i>
                                    <p>Aucune inscription trouvée</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inscriptions as $inscription): ?>
                                <tr class="inscription-row" data-inscription-id="<?php echo $inscription['id']; ?>">
                                    <td>
                                        <div class="inscription-info">
                                            <input type="checkbox" class="inscription-checkbox" value="<?php echo $inscription['id']; ?>">
                                            <div class="inscription-details">
                                                <div class="inscription-id">
                                                    #<?php echo $inscription['id']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="participant-info">
                                            <div class="participant-name">
                                                <?php echo htmlspecialchars($inscription['nom_complet']); ?>
                                            </div>
                                            <div class="participant-email">
                                                <?php echo htmlspecialchars($inscription['email']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="event-info">
                                            <?php echo htmlspecialchars($inscription['evenement_titre']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($inscription['date_inscription'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($inscription['statut']); ?>">
                                            <?php echo getStatusLabel($inscription['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="dropdown">
                                                <button onclick="toggleDropdown(<?php echo $inscription['id']; ?>)" class="btn btn-sm btn-secondary dropdown-toggle">
                                                    <i data-lucide="settings"></i>
                                                    Modifier statut
                                                </button>
                                                <div id="dropdown-<?php echo $inscription['id']; ?>" class="dropdown-menu">
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                                        <button type="submit" name="new_status" value="en_attente" class="dropdown-item">
                                                            <i data-lucide="clock"></i>
                                                            En attente
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                                        <button type="submit" name="new_status" value="confirme" class="dropdown-item">
                                                            <i data-lucide="check-circle"></i>
                                                            Confirmer
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                                        <button type="submit" name="new_status" value="annule" class="dropdown-item">
                                                            <i data-lucide="x-circle"></i>
                                                            Annuler
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette inscription ?')">
                                                <input type="hidden" name="action" value="delete_inscription">
                                                <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" data-tooltip="Supprimer">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_inscriptions > $per_page): ?>
                <div class="pagination">
                    <?php
                    $total_pages = ceil($total_inscriptions / $per_page);
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    // Lien précédent
                    if ($page > 1): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                           class="btn btn-secondary btn-sm">
                            <i data-lucide="chevron-left"></i>
                            Précédent
                        </a>
                    <?php endif; ?>

                    <!-- Numéros de page -->
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                           class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Lien suivant -->
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                           class="btn btn-secondary btn-sm">
                            Suivant
                            <i data-lucide="chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

/* Table des inscriptions */
.inscriptions-table-section {
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
}

.inscriptions-table {
    width: 100%;
    border-collapse: collapse;
}

.inscriptions-table th {
    background: var(--grey-light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--grey-dark);
    position: sticky;
    top: 0;
}

.inscriptions-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--grey-light);
}

.inscription-row:hover {
    background: rgba(210, 10, 46, 0.02);
}

.inscription-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.inscription-details {
    flex: 1;
}

.inscription-id {
    font-weight: 600;
    color: var(--cerise-primary);
    font-size: 0.875rem;
}

.participant-name {
    font-weight: 600;
    color: var(--grey-dark);
    margin-bottom: 0.25rem;
}

.participant-email {
    font-size: 0.875rem;
    color: var(--grey-medium);
}

/* Statuts */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-en_attente { background: rgba(255, 193, 7, 0.1); color: #856404; }
.status-confirme { background: rgba(40, 167, 69, 0.1); color: #155724; }
.status-annule { background: rgba(220, 53, 69, 0.1); color: #721c24; }

/* Actions */
.action-buttons {
    display: flex;
    gap: 0.5rem;
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
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    min-width: 150px;
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    padding: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        justify-content: center;
    }

    .inscriptions-table {
        font-size: 0.875rem;
    }

    .inscriptions-table th,
    .inscriptions-table td {
        padding: 0.75rem 0.5rem;
    }

    .action-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .dropdown-menu {
        position: static;
        box-shadow: none;
        border: none;
        background: var(--grey-light);
        margin-top: 0.5rem;
    }

    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Initialiser les icônes
lucide.createIcons();

// Sélection multiple
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.inscription-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Gestion des dropdowns
function toggleDropdown(inscriptionId) {
    const dropdown = document.getElementById('dropdown-' + inscriptionId);
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

// Export des inscriptions
function exportInscriptions() {
    const searchParams = new URLSearchParams(window.location.search);
    window.open('export-inscriptions.php?' + searchParams.toString(), '_blank');
}

// Fonctions utilitaires
function getStatusClass(status) {
    const classes = {
        'en_attente': 'status-en_attente',
        'confirme': 'status-confirme',
        'annule': 'status-annule'
    };
    return classes[status] || 'status-en_attente';
}

function getStatusLabel(status) {
    const labels = {
        'en_attente': 'En attente',
        'confirme': 'Confirmé',
        'annule': 'Annulé'
    };
    return labels[status] || status;
}
</script>

<?php include '../includes/footer.php'; ?>

<?php
// Fonctions utilitaires PHP
function getStatusClass($status) {
    $classes = [
        'en_attente' => 'status-en_attente',
        'confirme' => 'status-confirme',
        'annule' => 'status-annule'
    ];
    return $classes[$status] ?? 'status-en_attente';
}

function getStatusLabel($status) {
    $labels = [
        'en_attente' => 'En attente',
        'confirme' => 'Confirmé',
        'annule' => 'Annulé'
    ];
    return $labels[$status] ?? $status;
}
?>