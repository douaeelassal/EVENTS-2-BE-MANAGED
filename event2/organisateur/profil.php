<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token de sécurité invalide');
        }
        $nom = Security::sanitizeInput($_POST['nom_complet'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $gmail_app_password = Security::sanitizeInput($_POST['gmail_app_password'] ?? '');

        if (!$nom || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Nom et email valides requis');
        }

        $stmt = $db->prepare("
            UPDATE utilisateurs
            SET nom_complet = ?, email = ?, gmail_app_password = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $email, $gmail_app_password, $userId]);
        $_SESSION['user_name'] = $nom;
        $success = 'Profil mis à jour avec succès.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Chargement des données existantes
$stmt = $db->prepare("SELECT nom_complet, email, gmail_app_password FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Génération CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

include __DIR__ . '/../includes/header.php';
?>

<main class="dashboard">
  <div class="container">
    <h1>Mon Profil Organisateur</h1>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group">
        <label>Nom complet</label>
        <input name="nom_complet" class="form-control" value="<?= htmlspecialchars($user['nom_complet']) ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
      </div>
      <div class="form-group">
        <label for="gmail_app_password">Mot de passe d'application Gmail</label>
        <input name="gmail_app_password" type="password" class="form-control"
               value="<?= htmlspecialchars($user['gmail_app_password'] ?? '') ?>"
               placeholder="16 caractères générés par Google">
        <small class="form-help">
          Mot de passe d'application Gmail pour l'envoi d'emails.
          <a href="<?php echo __DIR__ . '/../GMAIL_CONFIG_GUIDE.md'; ?>" target="_blank">Guide de configuration</a>
        </small>
      </div>
      <button class="btn btn-primary" type="submit">Enregistrer</button>
    </form>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
