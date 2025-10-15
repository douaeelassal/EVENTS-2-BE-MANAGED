<?php
declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

$error = '';
$success = '';

// Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

// CAPTCHA temporairement désactivé

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }
        
        // Vérification reCAPTCHA temporairement désactivée pour le développement
        // $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        // if (!Security::verifyRecaptcha($recaptchaResponse)) {
        //     throw new Exception('Vérification reCAPTCHA échouée. Veuillez cocher la case "Je ne suis pas un robot".');
        // }
        
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            throw new Exception('Tous les champs sont obligatoires');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }
        
        // Recherche utilisateur
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT id, nom_complet, email, mot_de_passe_hash, role, email_verifie, date_creation
            FROM utilisateurs
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Aucun compte trouvé avec cette adresse email. Créez un compte ou utilisez un compte de test.');
        }

        // Vérifier si l'utilisateur a un mot de passe défini
        if (empty($user['mot_de_passe_hash'])) {
            throw new Exception('Ce compte n\'a pas de mot de passe défini. Utilisez un compte de test ou créez un nouveau compte.');
        }

        if (!$user['email_verifie']) {
            // Temporairement permettre la connexion même sans email vérifié
            error_log("Utilisateur non vérifié tente de se connecter: " . $email);
        }

        if (!Security::verifyPassword($password, $user['mot_de_passe_hash'])) {
            // Messages d'aide spécifiques selon le type d'utilisateur
            if (in_array($email, ['admin@event2.com', 'organisateur@event2.com', 'participant@event2.com'])) {
                $role = explode('@', $email)[0];
                throw new Exception("Mot de passe incorrect. Utilisez: {$role}123");
            }
            throw new Exception('Mot de passe incorrect. Vérifiez votre mot de passe ou utilisez un compte de test.');
        }
        
        // Connexion réussie
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['nom_complet'];

        // Log de connexion
        $stmt = $db->prepare("
            INSERT INTO logs_audit (user_id, action, details)
            VALUES (?, 'login', ?)
        ");
        $stmt->execute([$user['id'], "Connexion depuis IP: " . $_SERVER['REMOTE_ADDR']]);

        // Redirection selon le rôle
       // Dans auth/login.php, après validation des credentials :
switch ($user['role']) {
    case 'admin':
        header('Location: ../admin/dashboard.php');
        break;
    case 'organisateur':
        header('Location: ../organisateur/dashboard.php');
        break;
    case 'participant':
        header('Location: ../participant/dashboard.php');
        break;
}
exit;

        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

 
// Masquer le header sur la page de connexion
$hide_header = true;
include '../includes/header.php';
?>

<main class="auth-page">
    <div class="auth-container">
        <div class="auth-form">
            <h2>Connexion</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <!-- reCAPTCHA temporairement désactivé pour le développement -->
                <!-- <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" style="display: flex; justify-content: center; margin: 15px 0;"></div>
                </div> -->

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Se connecter
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <a href="register.php" style="color: var(--cerise-primary);">Créer un compte</a>
                <a href="forgot_password.php" style="color: var(--cerise-primary);">Mot de passe oublié ?</a>
            </div>
        </div>
    </div>

    <!-- Script reCAPTCHA temporairement désactivé -->
    <!-- <script src="https://www.google.com/recaptcha/api.js" async defer></script> -->
</main>

<?php include '../includes/footer.php'; ?>
