<?php
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

session_start();

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = "Profil Administrateur - EVENT2";
$db = Database::getInstance();

// Récupérer les informations de l'administrateur actuel
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nom_complet = trim($_POST['nom_complet'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nom_complet) || empty($email)) {
            $message = "Tous les champs sont requis";
            $message_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Email invalide";
            $message_type = "error";
        } else {
            try {
                $stmt = $db->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ? WHERE id = ?");
                $stmt->execute([$nom_complet, $email, $_SESSION['user_id']]);

                // Mettre à jour la session
                $_SESSION['user_name'] = $nom_complet;
                $_SESSION['user_email'] = $email;

                $message = "Profil mis à jour avec succès";
                $message_type = "success";

                // Recharger les données
                $admin['nom_complet'] = $nom_complet;
                $admin['email'] = $email;

            } catch (Exception $e) {
                $message = "Erreur lors de la mise à jour";
                $message_type = "error";
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "Tous les champs sont requis";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "Les nouveaux mots de passe ne correspondent pas";
            $message_type = "error";
        } elseif (strlen($new_password) < 6) {
            $message = "Le mot de passe doit contenir au moins 6 caractères";
            $message_type = "error";
        } else {
            // Vérifier le mot de passe actuel
            if (Security::verifyPassword($current_password, $admin['mot_de_passe_hash'])) {
                try {
                    $new_hash = Security::hashPassword($new_password);
                    $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $_SESSION['user_id']]);

                    $message = "Mot de passe changé avec succès";
                    $message_type = "success";

                } catch (Exception $e) {
                    $message = "Erreur lors du changement de mot de passe";
                    $message_type = "error";
                }
            } else {
                $message = "Mot de passe actuel incorrect";
                $message_type = "error";
            }
        }
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
                    <i data-lucide="user"></i>
                    Profil Administrateur
                </h1>
                <p class="dashboard-subtitle">
                    Gérez vos informations personnelles
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

        <div class="profile-container">
            <!-- Informations générales -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i data-lucide="user"></i>
                        Informations Générales
                    </h3>
                </div>
                <div class="card-content">
                    <form method="post" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label for="nom_complet">
                                <i data-lucide="user"></i>
                                Nom complet
                            </label>
                            <input type="text" id="nom_complet" name="nom_complet" class="form-control"
                                   value="<?php echo htmlspecialchars($admin['nom_complet']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i data-lucide="mail"></i>
                                Email
                            </label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>
                                <i data-lucide="shield"></i>
                                Rôle
                            </label>
                            <div class="role-display">
                                <span class="role-badge role-admin">Administrateur</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i data-lucide="calendar"></i>
                                Membre depuis
                            </label>
                            <div class="date-display">
                                <?php echo date('d F Y', strtotime($admin['date_creation'])); ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save"></i>
                                Sauvegarder
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Changement de mot de passe -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i data-lucide="lock"></i>
                        Sécurité
                    </h3>
                </div>
                <div class="card-content">
                    <form method="post" class="profile-form">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">
                                <i data-lucide="key"></i>
                                Mot de passe actuel
                            </label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i data-lucide="unlock"></i>
                                Nouveau mot de passe
                            </label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i data-lucide="check-circle"></i>
                                Confirmer le mot de passe
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i data-lucide="lock"></i>
                                Changer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.profile-container {
    display: grid;
    gap: 2rem;
    max-width: 800px;
    margin: 0 auto;
}

.profile-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--grey-dark);
}

.form-control {
    padding: 0.75rem;
    border: 2px solid var(--grey-light);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--cerise-primary);
    box-shadow: 0 0 0 3px rgba(210, 10, 46, 0.1);
}

.role-display, .date-display {
    padding: 0.75rem;
    background: var(--grey-light);
    border-radius: 12px;
    font-weight: 600;
    color: var(--grey-dark);
}

.form-actions {
    margin-top: 1rem;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
lucide.createIcons();

// Validation du mot de passe en temps réel
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;

    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
        this.style.borderColor = '#dc3545';
    } else {
        this.style.borderColor = '';
    }
});
</script>

<?php include '../includes/footer.php'; ?>