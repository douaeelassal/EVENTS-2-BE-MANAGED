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

$page_title = "Gestion des Utilisateurs - Administration EVENT2";
$db = Database::getInstance();

// Gestion de la recherche et des filtres
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nom_complet LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'verified') {
        $where_conditions[] = "email_verifie = TRUE";
    } elseif ($status_filter === 'unverified') {
        $where_conditions[] = "email_verifie = FALSE";
    } elseif ($status_filter === 'recent') {
        $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
}

// Requête pour compter le total
$count_sql = "SELECT COUNT(*) FROM utilisateurs WHERE " . implode(" AND ", $where_conditions);
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();

// Requête principale avec pagination
$sql = "
    SELECT id, nom_complet, email, role, date_creation, email_verifie
    FROM utilisateurs
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY date_creation DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Statistiques rapides
$total_verified = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE email_verifie = TRUE")->fetchColumn();
$total_unverified = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE email_verifie = FALSE")->fetchColumn();

// Gestion des actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_verify' && $user_id > 0) {
        try {
            $stmt = $db->prepare("UPDATE utilisateurs SET email_verifie = !email_verifie WHERE id = ?");
            $stmt->execute([$user_id]);

            // Log de l'action
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'toggle_verify', ?)");
            $stmt->execute([$_SESSION['user_id'], "Vérification email utilisateur ID: $user_id"]);

            $message = "Statut de vérification mis à jour avec succès";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Erreur lors de la mise à jour";
            $message_type = "error";
        }
    }

    if ($action === 'delete_user' && $user_id > 0) {
        try {
            // Vérifier que ce n'est pas l'admin actuel
            if ($user_id === $_SESSION['user_id']) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte");
            }

            // Récupérer les informations de l'utilisateur avant suppression
            $stmt = $db->prepare("SELECT nom_complet, email FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();

            if (!$user_info) {
                throw new Exception("Utilisateur non trouvé");
            }

            // Supprimer d'abord les enregistrements liés dans logs_audit
            $stmt = $db->prepare("DELETE FROM logs_audit WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Supprimer les inscriptions de l'utilisateur
            $stmt = $db->prepare("DELETE FROM inscriptions WHERE utilisateur_id = ?");
            $stmt->execute([$user_id]);

            // Supprimer l'utilisateur
            $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);

            // Log de l'action (après suppression car l'utilisateur n'existe plus)
            $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'delete_user', ?)");
            $stmt->execute([$_SESSION['user_id'], "Suppression utilisateur: {$user_info['nom_complet']} ({$user_info['email']})"]);

            $message = "Utilisateur supprimé avec succès";
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
                    <i data-lucide="users"></i>
                    Gestion des Utilisateurs
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo number_format($total_users); ?> utilisateurs au total
                </p>
            </div>
            <div class="dashboard-actions">
                <button onclick="exportUsers()" class="btn btn-secondary">
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
                               placeholder="Nom, email...">
                    </div>

                    <div class="filter-group">
                        <label for="role">
                            <i data-lucide="filter"></i>
                            Rôle
                        </label>
                        <select id="role" name="role" class="form-control">
                            <option value="">Tous les rôles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                            <option value="organisateur" <?php echo $role_filter === 'organisateur' ? 'selected' : ''; ?>>Organisateur</option>
                            <option value="participant" <?php echo $role_filter === 'participant' ? 'selected' : ''; ?>>Participant</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="status">
                            <i data-lucide="activity"></i>
                            Statut
                        </label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Vérifié</option>
                            <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Non vérifié</option>
                            <option value="recent" <?php echo $status_filter === 'recent' ? 'selected' : ''; ?>>Récent (7j)</option>
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
                <i data-lucide="check-circle"></i>
                <span><?php echo $total_verified; ?> vérifiés</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="x-circle"></i>
                <span><?php echo $total_unverified; ?> non vérifiés</span>
            </div>
            <div class="stat-badge">
                <i data-lucide="clock"></i>
                <span><?php echo $total_users - $total_verified - $total_unverified; ?> autres</span>
            </div>
        </div>

        <!-- Table des utilisateurs -->
        <div class="users-table-section card">
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select-all">
                                Utilisateur
                            </th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Dernière connexion</th>
                            <th>Inscrit le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i data-lucide="users"></i>
                                    <p>Aucun utilisateur trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                                    <td>
                                        <div class="user-info">
                                            <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>">
                                            <div class="user-details">
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($user['nom_complet']); ?>
                                                </div>
                                                <div class="user-email">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['email_verifie'] ? 'status-verified' : 'status-unverified'; ?>">
                                            <i data-lucide="<?php echo $user['email_verifie'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                            <?php echo $user['email_verifie'] ? 'Vérifié' : 'Non vérifié'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">Non disponible</span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-secondary" data-tooltip="Voir détails">
                                                <i data-lucide="eye"></i>
                                            </button>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr ?')">
                                                <input type="hidden" name="action" value="toggle_verify">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $user['email_verifie'] ? 'btn-warning' : 'btn-success'; ?>"
                                                        data-tooltip="<?php echo $user['email_verifie'] ? 'Désactiver' : 'Vérifier'; ?>">
                                                    <i data-lucide="<?php echo $user['email_verifie'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                                </button>
                                            </form>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
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

            <!-- Pagination -->
            <?php if ($total_users > $per_page): ?>
                <div class="pagination">
                    <?php
                    $total_pages = ceil($total_users / $per_page);
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

<!-- Modal de détails utilisateur -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Détails de l'utilisateur</h2>
            <button onclick="closeModal()" class="modal-close">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" id="userDetails">
            <!-- Les détails seront chargés ici -->
        </div>
    </div>
</div>

<style>
/* Filtres */
.filters-section {
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
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

/* Table des utilisateurs */
.users-table-section {
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th {
    background: var(--grey-light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--grey-dark);
    position: sticky;
    top: 0;
}

.users-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--grey-light);
}

.user-row:hover {
    background: rgba(210, 10, 46, 0.02);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--grey-dark);
}

.user-email {
    font-size: 0.875rem;
    color: var(--grey-medium);
}

/* Badges */
.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-admin { background: #dc3545; color: white; }
.role-organisateur { background: #28a745; color: white; }
.role-participant { background: #007bff; color: white; }

.status-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-verified { background: rgba(40, 167, 69, 0.1); color: #155724; }
.status-unverified { background: rgba(220, 53, 69, 0.1); color: #721c24; }

.text-muted {
    color: var(--grey-medium);
}

/* Actions */
.action-buttons {
    display: flex;
    gap: 0.25rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    padding: 1rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.loading {
    text-align: center;
    padding: 2rem;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--grey-light);
    border-top: 4px solid var(--cerise-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.user-profile {
    max-width: 100%;
}

.profile-header {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--grey-light);
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--cerise-primary), #D20A2E);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
}

.profile-info h3 {
    margin: 0 0 0.5rem 0;
    color: var(--grey-dark);
}

.profile-email {
    color: var(--grey-medium);
    margin-bottom: 0.5rem;
}

.details-section {
    margin-bottom: 2rem;
}

.details-section h4 {
    color: var(--cerise-primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.detail-item label {
    font-weight: 600;
    color: var(--grey-dark);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-item {
    background: var(--grey-light);
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}

.stat-item label {
    display: block;
    font-size: 0.875rem;
    color: var(--grey-medium);
    margin-bottom: 0.5rem;
}

.stat-item span {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--cerise-primary);
}

.activity-list {
    max-height: 200px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: var(--grey-light);
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: var(--cerise-primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-details {
    flex: 1;
}

.activity-action {
    font-weight: 600;
    color: var(--grey-dark);
    margin-bottom: 0.25rem;
}

.activity-date {
    font-size: 0.875rem;
    color: var(--grey-medium);
}

.no-activity {
    text-align: center;
    color: var(--grey-medium);
    font-style: italic;
    padding: 2rem;
}

.error-message {
    text-align: center;
    padding: 2rem;
    color: #721c24;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.error-message i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.error-text {
    margin-top: 0.5rem;
    font-weight: 600;
}

.modal-content {
    background: white;
    margin: 5% auto;
    width: 90%;
    max-width: 600px;
    border-radius: 20px;
    overflow: hidden;
    animation: slideIn 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    border-bottom: 1px solid var(--grey-light);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--grey-medium);
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--grey-light);
    color: var(--cerise-primary);
}

.modal-body {
    padding: 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        justify-content: center;
    }

    .users-table {
        font-size: 0.875rem;
    }

    .users-table th,
    .users-table td {
        padding: 0.75rem 0.5rem;
    }

    .action-buttons {
        flex-wrap: wrap;
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
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Fonctions d'action
async function viewUser(userId) {
    const modal = document.getElementById('userModal');
    const details = document.getElementById('userDetails');

    // Afficher le loader
    details.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Chargement des détails...</p>
        </div>
    `;

    modal.style.display = 'block';

    try {
        // Récupérer les détails de l'utilisateur
        const response = await fetch(`get_user_details.php?user_id=${userId}`);
        const user = await response.json();

        if (user.error) {
            throw new Error(user.error);
        }

        // Récupérer les statistiques de l'utilisateur
        const statsResponse = await fetch(`get_user_stats.php?user_id=${userId}`);
        const stats = await statsResponse.json();

        // Afficher les détails
        details.innerHTML = `
            <div class="user-profile">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i data-lucide="user"></i>
                    </div>
                    <div class="profile-info">
                        <h3>${user.nom_complet}</h3>
                        <p class="profile-email">${user.email}</p>
                        <p class="profile-role">Rôle: <span class="role-badge role-${user.role}">${ucFirst(user.role)}</span></p>
                    </div>
                </div>

                <div class="profile-details">
                    <div class="details-section">
                        <h4>Informations générales</h4>
                        <div class="detail-item">
                            <label>Date d'inscription:</label>
                            <span>${formatDate(user.date_creation)}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email vérifié:</label>
                            <span class="status-badge ${user.email_verifie ? 'status-verified' : 'status-unverified'}">
                                <i data-lucide="${user.email_verifie ? 'check-circle' : 'x-circle'}"></i>
                                ${user.email_verifie ? 'Oui' : 'Non'}
                            </span>
                        </div>
                    </div>

                    <div class="details-section">
                        <h4>Statistiques d'activité</h4>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <label>Événements créés:</label>
                                <span>${stats.events_created || 0}</span>
                            </div>
                            <div class="stat-item">
                                <label>Inscriptions:</label>
                                <span>${stats.total_inscriptions || 0}</span>
                            </div>
                            <div class="stat-item">
                                <label>Actions de modération:</label>
                                <span>${stats.moderation_actions || 0}</span>
                            </div>
                        </div>
                    </div>

                    <div class="details-section">
                        <h4>Activité récente</h4>
                        <div class="activity-list">
                            ${stats.recent_activity && stats.recent_activity.length > 0 ?
                                stats.recent_activity.map(activity => `
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i data-lucide="${getActivityIcon(activity.action)}"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-action">${activity.action_label}</div>
                                            <div class="activity-date">${formatDate(activity.date)}</div>
                                        </div>
                                    </div>
                                `).join('') :
                                '<p class="no-activity">Aucune activité récente</p>'
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Réinitialiser les icônes
        lucide.createIcons();

    } catch (error) {
        details.innerHTML = `
            <div class="error-message">
                <i data-lucide="alert-circle"></i>
                <p>Erreur lors du chargement des détails:</p>
                <p class="error-text">${error.message}</p>
            </div>
        `;
        lucide.createIcons();
    }
}

function editUser(userId) {
    alert('Édition de l\'utilisateur #' + userId + ' - Fonctionnalité à implémenter');
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Fermer modal en cliquant en dehors
window.addEventListener('click', function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Fonctions utilitaires
function ucFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getActivityIcon(action) {
    const icons = {
        'login': 'log-in',
        'logout': 'log-out',
        'create_event': 'calendar-plus',
        'inscription': 'user-plus',
        'delete_user': 'user-minus',
        'toggle_verify': 'shield-check',
        'envoi_email': 'mail'
    };
    return icons[action] || 'activity';
}

// Export des utilisateurs
function exportUsers() {
    const searchParams = new URLSearchParams(window.location.search);
    window.open('export-users.php?' + searchParams.toString(), '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
