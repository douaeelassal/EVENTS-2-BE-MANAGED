<?php
declare(strict_types=1);

// Chemins absolus pour éviter les problèmes d'inclusion
$config_path = __DIR__ . '/../includes/config.php';
$db_path = __DIR__ . '/../includes/Database.php';
$security_path = __DIR__ . '/../includes/Security.php';

if (!file_exists($config_path) || !file_exists($db_path) || !file_exists($security_path)) {
    die('Erreur: Fichiers d\'inclusion manquants. Vérifiez l\'installation.');
}

require_once $config_path;
require_once $db_path;
require_once $security_path;

// Vérification de PHPMailer
$phpmailer_path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($phpmailer_path)) {
    die('Erreur: PHPMailer non installé. Exécutez: composer require phpmailer/phpmailer');
}
require_once $phpmailer_path;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Vérification de sécurité avec gestion d'erreur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ' . __DIR__ . '/../auth/login.php');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

// Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

// Récupération des événements de l'organisateur
$stmt = $db->prepare("
    SELECT id, titre, statut,
           (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = evenements.id) as inscrits
    FROM evenements
    WHERE organisateur_id = ?
    ORDER BY date_debut DESC
");
$stmt->execute([$_SESSION['user_id']]);
$evenements = $stmt->fetchAll();

// Gestion de l'envoi d'email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }

        $event_id = (int)($_POST['event_id'] ?? 0);
        $sujet = Security::sanitizeInput($_POST['sujet'] ?? '');
        $message = Security::sanitizeInput($_POST['message'] ?? '');
        $type_destinataire = $_POST['type_destinataire'] ?? '';

        if (!$event_id || !$sujet || !$message || !$type_destinataire) {
            throw new Exception('Tous les champs sont obligatoires');
        }

        // Vérifier que l'événement appartient à l'organisateur
        $stmt = $db->prepare("SELECT titre FROM evenements WHERE id = ? AND organisateur_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $event = $stmt->fetch();

        if (!$event) {
            throw new Exception('Événement non trouvé ou accès non autorisé');
        }

        // Récupération des destinataires selon le type choisi
        $destinataires = [];
        switch ($type_destinataire) {
            case 'inscrits':
                $stmt = $db->prepare("
                    SELECT DISTINCT u.email, u.nom_complet
                    FROM utilisateurs u
                    INNER JOIN inscriptions i ON u.id = i.utilisateur_id
                    WHERE i.evenement_id = ? AND u.email IS NOT NULL
                ");
                $stmt->execute([$event_id]);
                $destinataires = $stmt->fetchAll();
                break;

            case 'tous_participants':
                $stmt = $db->prepare("
                    SELECT DISTINCT email, nom_complet
                    FROM utilisateurs
                    WHERE role = 'participant' AND email IS NOT NULL
                ");
                $stmt->execute();
                $destinataires = $stmt->fetchAll();
                break;

            default:
                throw new Exception('Type de destinataire invalide');
        }

        if (empty($destinataires)) {
            throw new Exception('Aucun destinataire trouvé');
        }

        // Configuration PHPMailer
        $mail = new PHPMailer(true);

        // Récupérer l'email et le mot de passe d'application de l'organisateur
        $stmt = $db->prepare("SELECT email, gmail_app_password FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $organisateur = $stmt->fetch();

        if (!$organisateur || empty($organisateur['email'])) {
            throw new Exception('Email de l\'organisateur non trouvé. Veuillez configurer votre email dans votre profil.');
        }

        if (empty($organisateur['gmail_app_password'])) {
            throw new Exception('Mot de passe d\'application Gmail non configuré. Veuillez le configurer dans votre profil pour pouvoir envoyer des emails.');
        }

        // Configuration SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $organisateur['email']; // Email de l'organisateur
        $mail->Password   = $organisateur['gmail_app_password']; // Mot de passe d'application depuis la BDD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Configuration de l'expéditeur
        $mail->setFrom($organisateur['email'], $_SESSION['user_name'] . ' - Organisateur');
        $mail->addReplyTo($organisateur['email'], $_SESSION['user_name'] . ' - Organisateur');

        // Options pour Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Gestion des fichiers joints
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $upload_dir = '../uploads/emails/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = Security::sanitizeInput($_FILES['attachments']['name'][$key]);
                    $filepath = $upload_dir . uniqid() . '_' . $filename;

                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $attachments[] = [
                            'path' => $filepath,
                            'name' => $filename
                        ];
                    }
                }
            }
        }

        // Envoi des emails
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->CharSet = 'UTF-8';

        $sentCount = 0;
        $failed = [];

        foreach ($destinataires as $destinataire) {
            try {
                // Nouveau destinataire
                $mail->clearAddresses();
                $mail->addAddress($destinataire['email']);

                // Message personnalisé
                $personalizedMessage = "Bonjour {$destinataire['nom_complet']},<br><br>" . nl2br(htmlspecialchars($message));
                $mail->Body = $personalizedMessage;
                $mail->AltBody = "Bonjour {$destinataire['nom_complet']},\n\n" . strip_tags($message);

                // Ajouter les pièces jointes
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }

                // Envoi
                if ($mail->send()) {
                    $sentCount++;
                } else {
                    $failed[] = $destinataire['email'] . ' (' . $mail->ErrorInfo . ')';
                }

            } catch (Exception $e) {
                $failed[] = $destinataire['email'] . ' (' . $e->getMessage() . ')';
            }
        }

        // Nettoyer les fichiers joints après envoi
        foreach ($attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                unlink($attachment['path']);
            }
        }

        // Log de l'envoi
        $stmt = $db->prepare("
            INSERT INTO logs_audit (user_id, action, details)
            VALUES (?, 'envoi_email', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Email envoyé à {$sentCount} participants de l'événement ID: {$event_id}"
        ]);

        $success = "✅ Emails envoyés avec succès à {$sentCount} participant(s) de l'événement '{$event['titre']}'.";
        if (!empty($failed)) {
            $success .= "\n❌ Échecs: " . implode(", ", array_slice($failed, 0, 3));
            if (count($failed) > 3) {
                $success .= " et " . (count($failed) - 3) . " autres.";
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Inclusion du header avec gestion d'erreur robuste
$header_path = __DIR__ . '/../includes/header.php';
if (file_exists($header_path)) {
    include $header_path;
} else {
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event2 - Gestionnaire d\'événements</title>
    <link href="https://cdn.jsdelivr.net/npm/lucide@0.263.1/dist/umd/lucide.js" rel="stylesheet">
    <style>
        :root {
            --cerise-primary: #e91e63;
            --cerise-secondary: #c2185b;
            --white: #ffffff;
            --grey-light: #f5f5f5;
            --grey-medium: #666;
            --grey-dark: #333;
        }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .navbar { background: var(--cerise-primary); color: white; padding: 1rem; }
        .navbar-brand { font-size: 1.5rem; font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Event2 - Gestionnaire d\'événements</div>
    </nav>';
}
?>

<main class="dashboard fade-in">
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div class="dashboard-header-content">
                <h1>
                    <i data-lucide="mail"></i>
                    Envoyer un Email
                </h1>
                <p class="dashboard-subtitle">
                    Communiquez avec les participants de vos événements
                </p>
                <div class="info-box">
                    <h4>ℹ️ Configuration requise pour l'envoi d'emails</h4>
                    <p>Chaque organisateur doit configurer son propre email Gmail :</p>
                    <ol>
                        <li>Activez la <strong>validation en deux étapes</strong> sur votre compte Gmail</li>
                        <li>Générez un <strong>mot de passe d'application</strong> pour "Mail"</li>
                        <li>Remplacez <code>MOT_DE_PASSE_APP_ORGANISATEUR</code> par votre mot de passe d'application</li>
                        <li>Vérifiez que votre email est configuré dans votre <a href="profil.php">profil</a></li>
                    </ol>
                    <p><strong>Note :</strong> Chaque organisateur envoie les emails depuis SON propre email Gmail.</p>
                </div>
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
                <div>
                    <strong>Erreur:</strong>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle"></i>
                <div>
                    <strong>Succès:</strong>
                    <?= nl2br(htmlspecialchars($success)) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Indicateur de chargement -->
        <div id="loadingIndicator" class="loading-indicator" style="display: none;">
            <div class="loading-content">
                <i data-lucide="loader-2" class="spinning"></i>
                <span>Envoi des emails en cours...</span>
            </div>
        </div>

        <div class="email-form-container">
            <form method="post" enctype="multipart/form-data" class="email-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Sélection de l'événement -->
                <div class="form-section">
                    <h3>
                        <i data-lucide="calendar"></i>
                        Événement concerné
                    </h3>
                    <div class="form-group">
                        <label for="event_id">Sélectionnez un événement *</label>
                        <select id="event_id" name="event_id" class="form-control" required onchange="updateDestinataires()">
                            <option value="">Choisissez un événement...</option>
                            <?php foreach ($evenements as $evenement): ?>
                                <option value="<?= $evenement['id'] ?>"
                                        data-inscrits="<?= $evenement['inscrits'] ?>">
                                    <?= htmlspecialchars($evenement['titre']) ?>
                                    (<?= $evenement['inscrits'] ?> inscrits)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Destinataires -->
                <div class="form-section">
                    <h3>
                        <i data-lucide="users"></i>
                        Destinataires
                    </h3>
                    <div class="form-group">
                        <label for="type_destinataire">Type de destinataires *</label>
                        <select id="type_destinataire" name="type_destinataire" class="form-control" required onchange="updateDestinataires()">
                            <option value="">Choisissez le type...</option>
                            <option value="inscrits">Participants inscrits à cet événement</option>
                            <option value="tous_participants">Tous les participants</option>
                        </select>
                    </div>
                    <div id="destinataires-info" class="destinataires-info" style="display: none;">
                        <p><strong>Destinataires:</strong> <span id="destinataires-count">0</span> personne(s)</p>
                    </div>
                </div>

                <!-- Contenu de l'email -->
                <div class="form-section">
                    <h3>
                        <i data-lucide="edit-3"></i>
                        Contenu du message
                    </h3>
                    <div class="form-group">
                        <label for="sujet">Sujet *</label>
                        <input type="text" id="sujet" name="sujet" class="form-control"
                               placeholder="Objet de votre email..." required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control"
                                  rows="8" placeholder="Votre message..." required></textarea>
                        <small class="form-help">Vous pouvez utiliser du HTML simple pour formater votre message.</small>
                    </div>
                </div>

                <!-- Fichiers joints -->
                <div class="form-section">
                    <h3>
                        <i data-lucide="paperclip"></i>
                        Pièces jointes
                    </h3>
                    <div class="form-group">
                        <label for="attachments">Fichiers à joindre</label>
                        <input type="file" id="attachments" name="attachments[]" class="form-control"
                               multiple accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png">
                        <small class="form-help">
                            Formats acceptés: PDF, Word, TXT, JPG, PNG. Taille max: 5MB par fichier.
                        </small>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="send"></i>
                        Envoyer l'Email
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="previewEmail()">
                        <i data-lucide="eye"></i>
                        Aperçu
                    </button>
                    <a href="dashboard.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <!-- Aperçu de l'email (modal) -->
        <div id="emailPreview" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Aperçu de l'email</h3>
                    <button type="button" class="modal-close" onclick="closePreview()">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="preview-email">
                        <div class="preview-header">
                            <strong>De:</strong> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Organisateur') ?> (<?= htmlspecialchars($organisateur['email'] ?? 'Non configuré - Voir profil') ?>)<br>
                            <strong>À:</strong> <span id="preview-destinataires">Destinataires</span><br>
                            <strong>Sujet:</strong> <span id="preview-sujet">Sujet</span>
                        </div>
                        <div class="preview-content">
                            <div id="preview-message">Message</div>
                        </div>
                        <div class="preview-attachments" id="preview-attachments" style="display: none;">
                            <strong>Pièces jointes:</strong>
                            <ul id="preview-attachments-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.email-form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    background: var(--white);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-section h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    color: var(--grey-dark);
    border-bottom: 2px solid var(--grey-light);
    padding-bottom: 0.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--grey-dark);
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--grey-light);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--cerise-primary);
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--grey-medium);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    padding-top: 2rem;
    border-top: 1px solid var(--grey-light);
}

.destinataires-info {
    background: var(--grey-light);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

/* Modal d'aperçu */
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
    max-width: 600px;
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
    overflow-y: auto;
    max-height: 60vh;
}

.preview-email {
    border: 1px solid var(--grey-light);
    border-radius: 8px;
    padding: 1.5rem;
}

.preview-header {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--grey-light);
    font-size: 0.875rem;
    line-height: 1.6;
}

.preview-content {
    margin-bottom: 1rem;
    line-height: 1.6;
}

.preview-attachments {
    background: var(--grey-light);
    padding: 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}

.preview-attachments ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.5rem;
}

/* Indicateur de chargement */
.loading-indicator {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-content {
    background: var(--white);
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Alertes améliorées */
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

.alert div {
    flex: 1;
}

/* Amélioration des pièces jointes */
.attachments-list {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--grey-light);
    border-radius: 8px;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--white);
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.attachment-item:last-child {
    margin-bottom: 0;
}

.attachment-remove {
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive */
@media (max-width: 768px) {
    .form-section {
        padding: 1.5rem;
    }

    .form-actions {
        flex-direction: column;
    }

    .modal-content {
        width: 95%;
        margin: 1rem;
    }

    .loading-content {
        margin: 1rem;
        padding: 1.5rem;
    }
}

/* Boîte d'information de configuration */
.info-box {
    background: linear-gradient(135deg, #e3f2fd, #f0f8ff);
    border: 2px solid #2196f3;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    box-shadow: 0 2px 8px rgba(33, 150, 243, 0.1);
}

.info-box h4 {
    color: #1976d2;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box ol {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

.info-box li {
    margin: 0.5rem 0;
    line-height: 1.5;
}

.info-box code {
    background: rgba(255, 255, 255, 0.8);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    border: 1px solid #ddd;
}

.info-box a {
    color: #1976d2;
    text-decoration: none;
    font-weight: 600;
}

.info-box a:hover {
    text-decoration: underline;
}
</style>

<script>
// Initialiser les icônes et événements
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    updateDestinataires();
    setupFormValidation();
});

function updateDestinataires() {
    const eventSelect = document.getElementById('event_id');
    const typeSelect = document.getElementById('type_destinataire');
    const infoDiv = document.getElementById('destinataires-info');
    const countSpan = document.getElementById('destinataires-count');

    if (eventSelect.value && typeSelect.value) {
        let count = 0;
        if (typeSelect.value === 'inscrits') {
            const selectedOption = eventSelect.selectedOptions[0];
            count = parseInt(selectedOption.getAttribute('data-inscrits')) || 0;
        } else if (typeSelect.value === 'tous_participants') {
            count = 'tous les participants';
        }

        countSpan.textContent = count;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

function previewEmail() {
    const sujet = document.getElementById('sujet').value.trim();
    const message = document.getElementById('message').value.trim();
    const eventSelect = document.getElementById('event_id');
    const typeSelect = document.getElementById('type_destinataire');

    if (!sujet || !message || !eventSelect.value || !typeSelect.value) {
        showAlert('Veuillez remplir tous les champs obligatoires avant de prévisualiser.', 'error');
        return;
    }

    // Mettre à jour l'aperçu
    document.getElementById('preview-sujet').textContent = sujet;
    document.getElementById('preview-message').innerHTML = message.replace(/\n/g, '<br>');

    const eventTitle = eventSelect.selectedOptions[0].text.split(' (')[0];
    const destinataireType = typeSelect.selectedOptions[0].text;
    document.getElementById('preview-destinataires').textContent = destinataireType + ' - ' + eventTitle;

    // Afficher la modal
    document.getElementById('emailPreview').style.display = 'flex';
}

function closePreview() {
    document.getElementById('emailPreview').style.display = 'none';
}

function setupFormValidation() {
    const form = document.querySelector('.email-form');
    const submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function(e) {
        const sujet = document.getElementById('sujet').value.trim();
        const message = document.getElementById('message').value.trim();
        const eventSelect = document.getElementById('event_id');
        const typeSelect = document.getElementById('type_destinataire');

        if (!sujet || !message || !eventSelect.value || !typeSelect.value) {
            e.preventDefault();
            showAlert('Veuillez remplir tous les champs obligatoires.', 'error');
            return false;
        }

        // Afficher l'indicateur de chargement
        document.getElementById('loadingIndicator').style.display = 'flex';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="spinning"></i> Envoi en cours...';
        lucide.createIcons();
    });
}

function showAlert(message, type) {
    // Créer une alerte temporaire
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i data-lucide="${type === 'error' ? 'alert-circle' : 'check-circle'}"></i>
        <div>${message}</div>
    `;

    // Insérer l'alerte après l'en-tête
    const header = document.querySelector('.dashboard-header');
    header.insertAdjacentElement('afterend', alertDiv);

    // Recréer les icônes
    lucide.createIcons();

    // Supprimer l'alerte après 5 secondes
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);

    // Scroll vers l'alerte
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Gestion des pièces jointes avec prévisualisation
document.getElementById('attachments').addEventListener('change', function(e) {
    const files = e.target.files;
    const attachmentsList = document.createElement('div');
    attachmentsList.className = 'attachments-list';

    if (files.length > 0) {
        let listHtml = '<h4>Fichiers sélectionnés:</h4>';

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // Taille en MB

            listHtml += `
                <div class="attachment-item">
                    <i data-lucide="file"></i>
                    <div>
                        <strong>${file.name}</strong><br>
                        <small>Taille: ${fileSize} MB</small>
                    </div>
                </div>
            `;
        }

        attachmentsList.innerHTML = listHtml;

        // Insérer après le input file
        const fileInput = document.getElementById('attachments');
        fileInput.parentNode.insertBefore(attachmentsList, fileInput.nextSibling);

        lucide.createIcons();
    }
});
</script>

<?php
// Inclusion du footer avec gestion d'erreur robuste
$footer_path = __DIR__ . '/../includes/footer.php';
if (file_exists($footer_path)) {
    include $footer_path;
} else {
    echo '<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            lucide.createIcons();
        });
    </script>
</body>
</html>';
}
?>