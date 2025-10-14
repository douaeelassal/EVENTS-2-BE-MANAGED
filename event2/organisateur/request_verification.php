<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité - seulement les organisateurs peuvent demander une vérification
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Vérifier le statut actuel de l'organisateur
$stmt = $db->prepare("SELECT statut_verification, nom_complet, email FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_demande = $_POST['type_demande'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';

    if ($type_demande === 'organisateur') {
        try {
            // Vérifier s'il y a déjà une demande en attente
            $stmt = $db->prepare("SELECT id FROM demandes_verification WHERE utilisateur_id = ? AND type_demande = 'organisateur' AND statut = 'en_attente'");
            $stmt->execute([$_SESSION['user_id']]);

            if ($stmt->fetch()) {
                $message = "Vous avez déjà une demande de vérification en attente.";
                $message_type = "warning";
            } else {
                // Créer la demande de vérification
                $stmt = $db->prepare("
                    INSERT INTO demandes_verification (utilisateur_id, type_demande, commentaire, statut)
                    VALUES (?, 'organisateur', ?, 'en_attente')
                ");
                $stmt->execute([$_SESSION['user_id'], $commentaire]);

                $verification_id = $db->lastInsertId();

                // Mettre à jour la date de demande
                $stmt = $db->prepare("UPDATE utilisateurs SET date_demande_verification = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);

                // Handle file uploads
                $upload_dir = UPLOAD_PATH . 'verification_documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $uploaded_files = [];

                // Process ID document (required)
                if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['id_document'];
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (in_array($file_extension, $allowed_extensions) && $file['size'] <= 5 * 1024 * 1024) {
                        $new_filename = 'id_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $stmt = $db->prepare("
                                INSERT INTO documents_verification (demande_id, type_document, nom_fichier, chemin_fichier)
                                VALUES (?, 'piece_identite', ?, ?)
                            ");
                            $stmt->execute([$verification_id, $file['name'], $new_filename]);
                            $uploaded_files[] = 'Pièce d\'identité';
                        } else {
                            error_log("Failed to move uploaded file: " . $file['tmp_name'] . " to " . $file_path);
                        }
                    } else {
                        error_log("Invalid file: " . $file['name'] . " - Extension: " . $file_extension . " - Size: " . $file['size']);
                    }
                }

                // Process club card (optional)
                if (isset($_FILES['club_card']) && $_FILES['club_card']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['club_card'];
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'pdf']) && $file['size'] <= 5 * 1024 * 1024) {
                        $new_filename = 'club_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $stmt = $db->prepare("
                                INSERT INTO documents_verification (demande_id, type_document, nom_fichier, chemin_fichier)
                                VALUES (?, 'carte_club', ?, ?)
                            ");
                            $stmt->execute([$verification_id, $file['name'], $new_filename]);
                            $uploaded_files[] = 'Carte d\'adhésion';
                        } else {
                            error_log("Failed to move uploaded club card: " . $file['tmp_name'] . " to " . $file_path);
                        }
                    } else {
                        error_log("Invalid club card file: " . $file['name'] . " - Extension: " . $file_extension . " - Size: " . $file['size']);
                    }
                }

                // Process address proof (optional)
                if (isset($_FILES['address_proof']) && $_FILES['address_proof']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['address_proof'];
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'pdf']) && $file['size'] <= 5 * 1024 * 1024) {
                        $new_filename = 'address_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $stmt = $db->prepare("
                                INSERT INTO documents_verification (demande_id, type_document, nom_fichier, chemin_fichier)
                                VALUES (?, 'justificatif_domicile', ?, ?)
                            ");
                            $stmt->execute([$verification_id, $file['name'], $new_filename]);
                            $uploaded_files[] = 'Justificatif de domicile';
                        } else {
                            error_log("Failed to move uploaded address proof: " . $file['tmp_name'] . " to " . $file_path);
                        }
                    } else {
                        error_log("Invalid address proof file: " . $file['name'] . " - Extension: " . $file_extension . " - Size: " . $file['size']);
                    }
                }

                // Save additional information
                $nom_club = $_POST['nom_club'] ?? '';
                $telephone = $_POST['telephone'] ?? '';
                $experience = $_POST['experience'] ?? '';
                $motivation = $_POST['motivation'] ?? '';

                if (!empty($nom_club) || !empty($telephone) || !empty($experience) || !empty($motivation)) {
                    $stmt = $db->prepare("
                        UPDATE demandes_verification
                        SET informations_complementaires = ?
                        WHERE id = ?
                    ");
                    $additional_info = json_encode([
                        'nom_club' => $nom_club,
                        'telephone' => $telephone,
                        'experience' => $experience,
                        'motivation' => $motivation
                    ]);
                    $stmt->execute([$additional_info, $verification_id]);
                }

                $files_message = '';
                if (!empty($uploaded_files)) {
                    $files_message = ' Documents téléchargés: ' . implode(', ', $uploaded_files) . '.';
                }

                $message = "Votre demande de vérification a été envoyée à l'administration." . $files_message;
                $message_type = "success";
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'envoi de la demande: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

$page_title = "Demande de Vérification - Organisateur EVENT2";
include '../includes/header.php';
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="shield-check"></i>
                    Vérification de Compte
                </h1>
                <p class="dashboard-subtitle">
                    Demandez votre vérification pour pouvoir créer des événements
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
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'alert-triangle' : 'alert-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statut actuel -->
        <div class="verification-status card">
            <h3>
                <i data-lucide="user-check"></i>
                Statut de votre compte
            </h3>
            <div class="status-info">
                <?php
                $statusClass = 'status-';
                $statusText = '';

                switch ($user['statut_verification']) {
                    case 'verifie':
                        $statusClass .= 'success';
                        $statusText = 'Compte vérifié - Vous pouvez créer des événements';
                        break;
                    case 'rejete':
                        $statusClass .= 'danger';
                        $statusText = 'Compte rejeté - Contactez l\'administration';
                        break;
                    case 'en_attente':
                    default:
                        $statusClass .= 'warning';
                        $statusText = 'En attente de vérification - Vous ne pouvez pas encore créer d\'événements';
                        break;
                }
                ?>
                <div class="status-badge <?php echo $statusClass; ?>">
                    <i data-lucide="<?php echo $statusClass === 'status-success' ? 'check-circle' : ($statusClass === 'status-warning' ? 'clock' : 'x-circle'); ?>"></i>
                    <?php echo $statusText; ?>
                </div>
            </div>
        </div>

        <?php if ($user['statut_verification'] !== 'verifie'): ?>
        <!-- Formulaire unique de demande de vérification avec documents -->
        <div class="verification-form card">
            <h3>
                <i data-lucide="file-text"></i>
                Demande de Vérification Organisateur
            </h3>
            <p class="form-description">
                Pour pouvoir créer et gérer des événements, vous devez être vérifié par l'administration.
                Remplissez le formulaire ci-dessous et téléchargez vos documents justificatifs.
            </p>

            <form method="POST" enctype="multipart/form-data" class="verification-form-content">
                <input type="hidden" name="type_demande" value="organisateur">

                <!-- Informations personnelles -->
                <div class="form-section">
                    <h4>
                        <i data-lucide="user"></i>
                        Informations Personnelles
                    </h4>

                    <div class="form-group">
                        <label for="nom_complet">
                            <i data-lucide="user"></i>
                            Nom complet
                        </label>
                        <input type="text" id="nom_complet" class="form-control"
                               value="<?php echo htmlspecialchars($user['nom_complet']); ?>" readonly>
                        <small class="form-text">Votre nom tel qu'enregistré dans le système</small>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i data-lucide="mail"></i>
                            Email
                        </label>
                        <input type="email" id="email" class="form-control"
                               value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        <small class="form-text">Votre email de contact</small>
                    </div>

                    <div class="form-group">
                        <label for="commentaire">
                            <i data-lucide="message-square"></i>
                            Commentaire (optionnel)
                        </label>
                        <textarea id="commentaire" name="commentaire" class="form-control" rows="4"
                                  placeholder="Décrivez votre expérience en tant qu'organisateur, le type d'événements que vous souhaitez créer, etc."></textarea>
                        <small class="form-text">Informations supplémentaires pour aider l'administration à traiter votre demande</small>
                    </div>
                </div>

                <!-- Documents justificatifs -->
                <div class="form-section">
                    <h4>
                        <i data-lucide="file-text"></i>
                        Documents Justificatifs
                    </h4>
                    <p class="form-description">
                        Téléchargez vos documents justificatifs pour accélérer le processus de vérification.
                        Vous devez fournir au moins votre pièce d'identité.
                    </p>

                    <!-- Pièce d'identité -->
                    <div class="document-upload">
                        <label class="document-label">
                            <i data-lucide="user-check"></i>
                            Pièce d'Identité (Carte Nationale, Passeport, etc.)
                            <span class="required">*</span>
                        </label>
                        <div class="upload-area" onclick="document.getElementById('id_document').click()">
                            <div class="upload-zone" id="id-upload-zone">
                                <i data-lucide="upload"></i>
                                <p>Cliquez pour télécharger votre pièce d'identité</p>
                                <small>Formats: JPG, PNG, PDF (max 5MB)</small>
                            </div>
                            <input type="file" id="id_document" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="file-info" id="id-file-info" style="display: none;">
                            <i data-lucide="file-check"></i>
                            <span id="id-file-name"></span>
                            <button type="button" onclick="clearFile('id_document', 'id-file-info')" class="btn-clear">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Carte d'adhésion club -->
                    <div class="document-upload">
                        <label class="document-label">
                            <i data-lucide="credit-card"></i>
                            Carte d'Adhésion au Club (optionnel)
                        </label>
                        <div class="upload-area" onclick="document.getElementById('club_card').click()">
                            <div class="upload-zone" id="club-upload-zone">
                                <i data-lucide="upload"></i>
                                <p>Cliquez pour télécharger votre carte d'adhésion</p>
                                <small>Formats: JPG, PNG, PDF (max 5MB)</small>
                            </div>
                            <input type="file" id="club_card" name="club_card" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="file-info" id="club-file-info" style="display: none;">
                            <i data-lucide="file-check"></i>
                            <span id="club-file-name"></span>
                            <button type="button" onclick="clearFile('club_card', 'club-file-info')" class="btn-clear">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Justificatif de domicile -->
                    <div class="document-upload">
                        <label class="document-label">
                            <i data-lucide="home"></i>
                            Justificatif de Domicile (optionnel)
                        </label>
                        <div class="upload-area" onclick="document.getElementById('address_proof').click()">
                            <div class="upload-zone" id="address-upload-zone">
                                <i data-lucide="upload"></i>
                                <p>Cliquez pour télécharger un justificatif de domicile</p>
                                <small>Formats: JPG, PNG, PDF (max 5MB)</small>
                            </div>
                            <input type="file" id="address_proof" name="address_proof" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="file-info" id="address-file-info" style="display: none;">
                            <i data-lucide="file-check"></i>
                            <span id="address-file-name"></span>
                            <button type="button" onclick="clearFile('address_proof', 'address-file-info')" class="btn-clear">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Informations complémentaires -->
                <div class="form-section">
                    <h4>
                        <i data-lucide="user"></i>
                        Informations Complémentaires
                    </h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom_club">Nom du Club/Organisation</label>
                            <input type="text" id="nom_club" name="nom_club" class="form-control"
                                   placeholder="Si vous représentez un club ou une organisation">
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control"
                                   placeholder="+212 6XX XXX XXX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="experience">Expérience en Organisation d'Événements</label>
                        <textarea id="experience" name="experience" class="form-control" rows="3"
                                  placeholder="Décrivez votre expérience dans l'organisation d'événements..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="motivation">Motivation</label>
                        <textarea id="motivation" name="motivation" class="form-control" rows="3"
                                  placeholder="Expliquez pourquoi vous souhaitez organiser des événements sur notre plateforme..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="send"></i>
                        Soumettre la Demande de Vérification
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.verification-status, .verification-form, .membership-card-section {
    margin-bottom: 2rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
}

.status-success {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.status-warning {
    background: rgba(255, 193, 7, 0.1);
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.status-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.form-description {
    color: #666;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2d3436;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #D20A2E;
}

.form-control[readonly] {
    background-color: #f8f9fa;
    color: #6c757d;
}

.form-text {
    display: block;
    margin-top: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.form-actions {
    text-align: center;
    margin-top: 2rem;
}

.card-upload-area {
    text-align: center;
}

.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 3rem 2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.upload-zone:hover {
    border-color: #D20A2E;
    background: rgba(210, 10, 46, 0.02);
}

.upload-zone i {
    font-size: 3rem;
    color: #D20A2E;
    margin-bottom: 1rem;
}

.form-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    border-left: 4px solid #D20A2E;
}

.form-section h4 {
    margin: 0 0 1rem 0;
    color: #2d3436;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.required {
    color: #dc3545;
    font-weight: bold;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .status-badge {
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    // File upload handling for all document types
    document.getElementById('id_document').addEventListener('change', function(e) {
        handleFileSelect(e.target, 'id-upload-zone', 'id-file-info', 'id-file-name');
    });

    document.getElementById('club_card').addEventListener('change', function(e) {
        handleFileSelect(e.target, 'club-upload-zone', 'club-file-info', 'club-file-name');
    });

    document.getElementById('address_proof').addEventListener('change', function(e) {
        handleFileSelect(e.target, 'address-upload-zone', 'address-file-info', 'address-file-name');
    });

    function handleFileSelect(input, zoneId, infoId, nameId) {
        const file = input.files[0];
        if (file) {
            // Check file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('Le fichier ne doit pas dépasser 5MB');
                input.value = '';
                return;
            }

            // Update UI
            document.getElementById(zoneId).style.display = 'none';
            const fileInfo = document.getElementById(infoId);
            fileInfo.style.display = 'flex';
            document.getElementById(nameId).textContent = file.name;
        }
    }

    // Clear file function
    window.clearFile = function(inputId, infoId) {
        document.getElementById(inputId).value = '';
        document.getElementById(infoId).style.display = 'none';

        // Show upload zone again
        const input = document.getElementById(inputId);
        const zoneId = inputId.replace('_', '-upload-zone');
        document.getElementById(zoneId).style.display = 'block';
    };

    // Form validation
    document.querySelector('.verification-form-content').addEventListener('submit', function(e) {
        const idFile = document.getElementById('id_document').files[0];
        if (!idFile) {
            e.preventDefault();
            alert('Vous devez télécharger au moins votre pièce d\'identité.');
            return false;
        }

        // Show loading state
        const submitBtn = document.querySelector('.btn-primary');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i data-lucide="loader-2"></i> Envoi en cours...';
        submitBtn.disabled = true;

        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 10000);

        return true;
    });
});
</script>

<?php include '../includes/footer.php'; ?>