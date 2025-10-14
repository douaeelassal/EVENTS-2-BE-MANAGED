<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité - seulement les admins peuvent gérer les vérifications
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

$message = '';
$message_type = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $demande_id = (int)($_POST['demande_id'] ?? 0);

    if ($action === 'approve' && $demande_id > 0) {
        try {
            // Récupérer les informations de la demande
            $stmt = $db->prepare("
                SELECT dv.*, u.nom_complet, u.email, u.role
                FROM demandes_verification dv
                JOIN utilisateurs u ON dv.utilisateur_id = u.id
                WHERE dv.id = ?
            ");
            $stmt->execute([$demande_id]);
            $demande = $stmt->fetch();

            if (!$demande) {
                throw new Exception("Demande non trouvée");
            }

            // Approuver la demande
            $stmt = $db->prepare("
                UPDATE demandes_verification
                SET statut = 'approuvee', admin_id = ?, date_traitement = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $demande_id]);

            // Mettre à jour le statut de l'utilisateur
            $stmt = $db->prepare("
                UPDATE utilisateurs
                SET statut_verification = 'verifie', date_verification = NOW(), verifie_par_admin_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $demande['utilisateur_id']]);

            $message = "Demande de vérification approuvée pour {$demande['nom_complet']}";
            $message_type = "success";

        } catch (Exception $e) {
            $message = "Erreur lors de l'approbation: " . $e->getMessage();
            $message_type = "error";
        }
    }

    if ($action === 'reject' && $demande_id > 0) {
        try {
            // Récupérer les informations de la demande
            $stmt = $db->prepare("
                SELECT dv.*, u.nom_complet, u.email, u.role
                FROM demandes_verification dv
                JOIN utilisateurs u ON dv.utilisateur_id = u.id
                WHERE dv.id = ?
            ");
            $stmt->execute([$demande_id]);
            $demande = $stmt->fetch();

            if (!$demande) {
                throw new Exception("Demande non trouvée");
            }

            // Rejeter la demande
            $stmt = $db->prepare("
                UPDATE demandes_verification
                SET statut = 'rejetee', admin_id = ?, date_traitement = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $demande_id]);

            // Mettre à jour le statut de l'utilisateur
            $stmt = $db->prepare("
                UPDATE utilisateurs
                SET statut_verification = 'rejete', date_verification = NOW(), verifie_par_admin_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $demande['utilisateur_id']]);

            $message = "Demande de vérification rejetée pour {$demande['nom_complet']}";
            $message_type = "success";

        } catch (Exception $e) {
            $message = "Erreur lors du rejet: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Récupérer les demandes de vérification en attente avec leurs documents
$stmt = $db->query("
    SELECT dv.*, u.nom_complet, u.email, u.role, u.date_creation as date_inscription
    FROM demandes_verification dv
    JOIN utilisateurs u ON dv.utilisateur_id = u.id
    WHERE dv.statut = 'en_attente'
    ORDER BY dv.date_demande DESC
");
$pending_requests = $stmt->fetchAll();

// Récupérer les documents pour chaque demande
foreach ($pending_requests as &$request) {
    $stmt = $db->prepare("
        SELECT id, type_document, nom_fichier, chemin_fichier
        FROM documents_verification
        WHERE demande_id = ?
        ORDER BY type_document
    ");
    $stmt->execute([$request['id']]);
    $request['documents'] = $stmt->fetchAll();
}

// Récupérer les statistiques
$total_pending = count($pending_requests);
$total_verified = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_verification = 'verifie' AND role = 'organisateur'")->fetchColumn();
$total_rejected = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_verification = 'rejete' AND role = 'organisateur'")->fetchColumn();

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Helper function to check if file exists and get proper path
function getDocumentPath($filename) {
    $upload_dir = UPLOAD_PATH . 'verification_documents/';
    $absolute_path = $upload_dir . $filename;

    // Return absolute path for web access if file exists
    if (file_exists($absolute_path)) {
        return "/uploads/verification_documents/" . $filename;
    } else {
        return "/uploads/verification_documents/" . $filename; // Will show as broken link if file doesn't exist
    }
}

$page_title = "Demandes de Vérification - Administration EVENT2";
include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="shield-check"></i>
                    Gestion des Vérifications
                </h1>
                <p class="dashboard-subtitle">
                    Gérez les demandes de vérification des organisateurs
                </p>
            </div>
            <div class="dashboard-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left"></i>
                    Retour au Dashboard
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

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_pending; ?></h3>
                    <p>En attente</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_verified; ?></h3>
                    <p>Vérifiés</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="x-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_rejected; ?></h3>
                    <p>Rejetés</p>
                </div>
            </div>
        </div>

        <!-- Demandes en attente -->
        <?php if (empty($pending_requests)): ?>
            <div class="empty-state card">
                <i data-lucide="check-circle"></i>
                <h3>Aucune demande en attente</h3>
                <p>Toutes les demandes de vérification ont été traitées.</p>
            </div>
        <?php else: ?>
            <div class="requests-section card">
                <h3>
                    <i data-lucide="clock"></i>
                    Demandes en Attente (<?php echo $total_pending; ?>)
                </h3>

                <div class="requests-list">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <div class="request-info">
                                    <h4><?php echo htmlspecialchars($request['nom_complet']); ?></h4>
                                    <p class="request-email"><?php echo htmlspecialchars($request['email']); ?></p>
                                    <p class="request-date">
                                        <i data-lucide="calendar"></i>
                                        Demandé le <?php echo date('d/m/Y à H:i', strtotime($request['date_demande'])); ?>
                                    </p>
                                </div>
                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="demande_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                                onclick="return confirm('Êtes-vous sûr de vouloir approuver cette demande ?')">
                                            <i data-lucide="check"></i>
                                            Approuver
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="demande_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?')">
                                            <i data-lucide="x"></i>
                                            Rejeter
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <?php if ($request['commentaire']): ?>
                                <div class="request-comment">
                                    <h5>Commentaire:</h5>
                                    <p><?php echo htmlspecialchars($request['commentaire']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($request['documents'])): ?>
                                <div class="request-documents">
                                    <div class="documents-header">
                                        <h5>
                                            <i data-lucide="file-text"></i>
                                            Documents Justificatifs (<?php echo count($request['documents']); ?>)
                                        </h5>
                                        <div class="documents-summary">
                                            <span class="document-count">
                                                <i data-lucide="check-circle"></i>
                                                <?php echo count($request['documents']); ?> document(s)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="documents-grid">
                                        <?php foreach ($request['documents'] as $document): ?>
                                            <div class="document-item">
                                                <div class="document-icon">
                                                    <?php
                                                    $extension = strtolower(pathinfo($document['nom_fichier'], PATHINFO_EXTENSION));
                                                    $icon = 'file-text';
                                                    $file_path = getDocumentPath($document['chemin_fichier']);

                                                    if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                                                        $icon = 'image';
                                                        $preview_available = true;
                                                    } elseif ($extension === 'pdf') {
                                                        $icon = 'file-text';
                                                        $preview_available = false;
                                                    } else {
                                                        $preview_available = false;
                                                    }
                                                    ?>
                                                    <i data-lucide="<?php echo $icon; ?>"></i>
                                                </div>
                                                <div class="document-info">
                                                    <div class="document-name" title="<?php echo htmlspecialchars($document['nom_fichier']); ?>">
                                                        <?php echo htmlspecialchars($document['nom_fichier']); ?>
                                                    </div>
                                                    <div class="document-type">
                                                        <?php
                                                        $type_labels = [
                                                            'piece_identite' => 'Pièce d\'identité',
                                                            'carte_club' => 'Carte d\'adhésion',
                                                            'justificatif_domicile' => 'Justificatif de domicile'
                                                        ];
                                                        echo $type_labels[$document['type_document']] ?? 'Document';
                                                        ?>
                                                    </div>
                                                    <div class="document-size">
                                                        <?php
                                                        $file_path_full = UPLOAD_PATH . 'verification_documents/' . $document['chemin_fichier'];
                                                        if (file_exists($file_path_full)) {
                                                            $file_size = filesize($file_path_full);
                                                            echo formatFileSize($file_size);
                                                        } else {
                                                            echo '<span style="color: #dc3545;">Fichier non trouvé</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <?php if ($preview_available): ?>
                                                        <button type="button"
                                                                class="btn btn-info btn-xs preview-btn"
                                                                onclick="openDocumentModal('<?php echo $file_path; ?>', '<?php echo htmlspecialchars($document['nom_fichier']); ?>')"
                                                                title="Aperçu du document">
                                                            <i data-lucide="eye"></i>
                                                            Aperçu
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="<?php echo $file_path; ?>"
                                                       target="_blank"
                                                       class="btn btn-primary btn-xs"
                                                       title="Voir le document">
                                                        <i data-lucide="eye"></i>
                                                        Voir
                                                    </a>
                                                    <a href="<?php echo $file_path; ?>"
                                                       download
                                                       class="btn btn-secondary btn-xs"
                                                       title="Télécharger le document">
                                                        <i data-lucide="download"></i>
                                                        Télécharger
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Liste des organisateurs vérifiés -->
        <div class="verified-organizers-section card">
            <h3>
                <i data-lucide="users"></i>
                Organisateurs Vérifiés (<?php echo $total_verified; ?>)
            </h3>

            <?php
            // Récupérer les organisateurs vérifiés
            $stmt = $db->query("
                SELECT u.id, u.nom_complet, u.email, u.date_verification, a.nom_complet as admin_nom
                FROM utilisateurs u
                LEFT JOIN utilisateurs a ON u.verifie_par_admin_id = a.id
                WHERE u.statut_verification = 'verifie' AND u.role = 'organisateur'
                ORDER BY u.date_verification DESC
            ");
            $verified_organizers = $stmt->fetchAll();
            ?>

            <?php if (empty($verified_organizers)): ?>
                <div class="empty-state">
                    <i data-lucide="user-x"></i>
                    <p>Aucun organisateur vérifié pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="organizers-grid">
                    <?php foreach ($verified_organizers as $organizer): ?>
                        <div class="organizer-card">
                            <div class="organizer-info">
                                <h4><?php echo htmlspecialchars($organizer['nom_complet']); ?></h4>
                                <p class="organizer-email"><?php echo htmlspecialchars($organizer['email']); ?></p>
                                <p class="organizer-verified">
                                    <i data-lucide="check-circle"></i>
                                    Vérifié le <?php echo date('d/m/Y', strtotime($organizer['date_verification'])); ?>
                                </p>
                                <?php if ($organizer['admin_nom']): ?>
                                    <p class="organizer-admin">
                                        <i data-lucide="shield"></i>
                                        Par <?php echo htmlspecialchars($organizer['admin_nom']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Document Preview Modal -->
<div id="documentModal" class="document-modal" onclick="closeDocumentModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">Aperçu du Document</h3>
            <button type="button" class="modal-close" onclick="closeDocumentModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="documentPreview" class="document-preview">
                <!-- Document content will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDocumentModal()">
                <i data-lucide="x"></i>
                Fermer
            </button>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #D20A2E 0%, #b80926 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    color: #2d3436;
}

.stat-content p {
    margin: 0.5rem 0 0 0;
    color: #636e72;
    font-size: 0.9rem;
}

.requests-section, .verified-organizers-section {
    margin-bottom: 2rem;
}

.request-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-left: 4px solid #ffc107;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.request-info h4 {
    margin: 0 0 0.5rem 0;
    color: #2d3436;
    font-size: 1.2rem;
}

.request-email {
    color: #636e72;
    margin: 0 0 0.5rem 0;
}

.request-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #74b9ff;
    font-size: 0.9rem;
    margin: 0;
}

.request-actions {
    display: flex;
    gap: 0.5rem;
}

.request-comment {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.request-comment h5 {
    margin: 0 0 0.5rem 0;
    color: #2d3436;
    font-size: 1rem;
}

.request-comment p {
    margin: 0;
    color: #636e72;
    line-height: 1.5;
}

.request-documents {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    border-left: 4px solid #D20A2E;
}

.documents-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.request-documents h5 {
    margin: 0;
    color: #2d3436;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.documents-summary {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.document-count {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #28a745;
    font-size: 0.875rem;
    font-weight: 500;
}

.documents-grid {
    display: grid;
    gap: 0.75rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.document-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-color: #D20A2E;
}

.document-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #D20A2E 0%, #b80926 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.document-info {
    flex: 1;
    min-width: 0;
}

.document-name {
    font-weight: 600;
    color: #2d3436;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.document-type {
    color: #636e72;
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.document-size {
    color: #74b9ff;
    font-size: 0.75rem;
}

.document-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 6px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
}

.organizers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.organizer-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-left: 4px solid #28a745;
}

.organizer-info h4 {
    margin: 0 0 0.5rem 0;
    color: #2d3436;
    font-size: 1.1rem;
}

.organizer-email {
    color: #636e72;
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
}

.organizer-verified, .organizer-admin {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    margin: 0.25rem 0;
}

.organizer-verified {
    color: #28a745;
}

.organizer-admin {
    color: #74b9ff;
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

@media (max-width: 768px) {
    .request-header {
        flex-direction: column;
        gap: 1rem;
    }

    .request-actions {
        width: 100%;
        justify-content: center;
    }

    .organizers-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Document Modal Styles */
.document-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    animation: fadeIn 0.3s ease;
}

.document-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideIn 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0;
    color: #2d3436;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #636e72;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background-color: #f8f9fa;
    color: #2d3436;
}

.modal-body {
    flex: 1;
    padding: 1.5rem;
    overflow: hidden;
}

.document-preview {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.document-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 6px;
}

.document-preview iframe {
    width: 100%;
    height: 500px;
    border: none;
    border-radius: 6px;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-info:hover {
    background: #138496;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});

// Document Modal Functions
function openDocumentModal(filePath, fileName) {
    const modal = document.getElementById('documentModal');
    const modalTitle = document.getElementById('modalTitle');
    const preview = document.getElementById('documentPreview');

    // Determine file type
    const extension = filePath.split('.').pop().toLowerCase();

    modalTitle.innerHTML = `<i data-lucide="file-text"></i> ${fileName}`;
    lucide.createIcons();

    if (['jpg', 'jpeg', 'png'].includes(extension)) {
        // Image preview
        preview.innerHTML = `<img src="${filePath}" alt="${fileName}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
    } else if (extension === 'pdf') {
        // PDF preview using iframe
        preview.innerHTML = `<iframe src="${filePath}" title="${fileName}"></iframe>`;
    } else {
        // Fallback for other file types
        preview.innerHTML = `
            <div style="text-align: center; color: #636e72;">
                <i data-lucide="file-text" style="width: 64px; height: 64px; margin-bottom: 1rem;"></i>
                <p>Aperçu non disponible pour ce type de fichier</p>
                <p><a href="${filePath}" target="_blank" class="btn btn-primary">Ouvrir le fichier</a></p>
            </div>
        `;
        lucide.createIcons();
    }

    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeDocumentModal() {
    const modal = document.getElementById('documentModal');
    modal.classList.remove('show');
    document.body.style.overflow = ''; // Restore scrolling

    // Clear preview content
    const preview = document.getElementById('documentPreview');
    preview.innerHTML = '';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('documentModal');
    if (event.target === modal) {
        closeDocumentModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDocumentModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>