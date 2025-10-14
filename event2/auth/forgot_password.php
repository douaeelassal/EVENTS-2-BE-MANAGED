<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';

$error = '';
$success = '';

// Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }

        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('Tous les champs sont obligatoires');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('Les mots de passe ne correspondent pas');
        }

        if (strlen($new_password) < 6) {
            throw new Exception('Le mot de passe doit contenir au moins 6 caractères');
        }

        // Vérifier si l'utilisateur existe
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, nom_complet, email FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Aucun compte trouvé avec cette adresse email');
        }

        // Mettre à jour le mot de passe directement
        $password_hash = Security::hashPassword($new_password);
        $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE email = ?");
        $stmt->execute([$password_hash, $email]);

        // Log de l'action
        $stmt = $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'password_reset', 'Mot de passe réinitialisé via formulaire')");
        $stmt->execute([$user['id']]);

        $success = '✅ Mot de passe changé avec succès !<br><br>';
        $success .= 'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.<br><br>';
        $success .= '<a href="login.php" class="btn btn-primary">Aller à la connexion</a>';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Masquer le header sur la page d'authentification
$hide_header = true;
include '../includes/header.php';
?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-form">
            <h2>
                <i data-lucide="key"></i>
                Changer le mot de passe
            </h2>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="votre.email@exemple.com" required>
                        <small class="form-help">
                            Email du compte pour lequel vous voulez changer le mot de passe
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               placeholder="Minimum 6 caractères" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Retapez le même mot de passe" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i data-lucide="key"></i>
                        Changer le mot de passe
                    </button>
                </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--grey-light);">
                <a href="login.php" style="color: var(--cerise-primary);">
                    <i data-lucide="arrow-left"></i>
                    Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</main>

<style>
.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--cerise-primary), var(--cerise-secondary));
    padding: 1rem;
}

.auth-container {
    width: 100%;
    max-width: 400px;
}

.auth-form {
    background: var(--white);
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.auth-form h2 {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--grey-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
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

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--cerise-primary);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--cerise-secondary);
    transform: translateY(-1px);
}

.alert {
    padding: 1rem;
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

/* Responsive */
@media (max-width: 480px) {
    .auth-form {
        padding: 2rem 1.5rem;
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