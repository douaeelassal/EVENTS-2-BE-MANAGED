<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

$error = '';
$success = '';

// Initialisation CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }

        // Vérification reCAPTCHA
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        if (!Security::verifyRecaptcha($recaptchaResponse)) {
            throw new Exception('Vérification reCAPTCHA échouée. Veuillez cocher la case "Je ne suis pas un robot".');
        }

        // Récupération et validation des données
        $nom = Security::sanitizeInput($_POST['nom_complet'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = Security::sanitizeInput($_POST['role'] ?? '');

        // Validation des champs
        if (empty($nom) || empty($email) || empty($password) || empty($role)) {
            throw new Exception('Tous les champs sont obligatoires');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }

        // Vérifier l'existence du domaine MX (emails réels uniquement)
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX')) {
            throw new Exception('Domaine email invalide ou serveur mail non trouvé. Utilisez un email réel.');
        }

        if (strlen($password) < 6) {
            throw new Exception('Le mot de passe doit contenir au moins 6 caractères');
        }

        $allowedRoles = ['participant', 'organisateur'];
        if (!in_array($role, $allowedRoles)) {
            throw new Exception('Rôle invalide');
        }

        // Vérifier si l'email existe déjà
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Cette adresse email est déjà utilisée');
        }

        // Hashage du mot de passe
        $hash = Security::hashPassword($password);

        // Statut de vérification selon le rôle
        $statut_verification = ($role === 'organisateur') ? 'en_attente' : 'verifie';

        // Insertion utilisateur
        $stmt = $db->prepare("
            INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role, email_verifie, date_creation, statut_verification)
            VALUES (?, ?, ?, ?, FALSE, NOW(), ?)
        ");
        $stmt->execute([$nom, $email, $hash, $role, $statut_verification]);
        $userId = (int)$db->lastInsertId();

        // Insertion dans les tables spécifiques selon le rôle
        if ($role === 'organisateur') {
            $db->prepare("INSERT INTO organisateurs (utilisateur_id) VALUES (?)")
               ->execute([$userId]);
        } elseif ($role === 'participant') {
            $db->prepare("INSERT INTO participants (utilisateur_id) VALUES (?)")
               ->execute([$userId]);
        }

        // Log d'audit
        $db->prepare("INSERT INTO logs_audit (user_id, action, details) VALUES (?, 'register', ?)")
           ->execute([$userId, "Inscription depuis IP: " . $_SERVER['REMOTE_ADDR']]);

        $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';

        // CAPTCHA temporairement désactivé

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include '../includes/header.php';
?>
<main class="auth-page">
  <div class="auth-container">
    <div class="auth-form">
      <h2>Inscription</h2>
      <?= $error ? "<div class='alert alert-error'>{$error}</div>" : '' ?>
      <?= $success ? "<div class='alert alert-success'>{$success}</div>" : '' ?>
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
          <label for="nom_complet">Nom complet</label>
          <input type="text" id="nom_complet" name="nom_complet" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="role">Rôle</label>
          <select id="role" name="role" class="form-control" required>
            <option value="">Choisir</option>
            <option value="participant">Participant</option>
            <option value="organisateur">Organisateur</option>
          </select>
        </div>


        <!-- reCAPTCHA -->
        <div class="form-group">
          <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" style="display: flex; justify-content: center; margin: 15px 0;"></div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">S'inscrire</button>
      </form>
      <div style="text-align:center;margin-top:1rem;">
        <a href="login.php" style="color:var(--cerise-primary);">Déjà inscrit ?</a>
      </div>
    </div>
  </div>

  <!-- Script reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Initialize Lucide icons
    document.addEventListener('DOMContentLoaded', function() {
      lucide.createIcons();
    });
  </script>

  <style>
    /* Styles minimalistes pour l'inscription */
    .form-group {
      margin-bottom: 1.5rem;
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

    .btn-primary {
      background: linear-gradient(135deg, #D20A2E 0%, #b80926 100%);
      color: white;
      border: none;
      padding: 0.75rem 2rem;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(210, 10, 46, 0.3);
    }
  </style>
</main>
<?php include '../includes/footer.php'; ?>
