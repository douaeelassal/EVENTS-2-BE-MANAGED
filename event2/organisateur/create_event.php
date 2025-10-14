<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

// Vérifier si l'organisateur est vérifié
$db = Database::getInstance();
$stmt = $db->prepare("SELECT statut_verification FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['statut_verification'] !== 'verifie') {
    $verification_required = true;
} else {
    $verification_required = false;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token invalide');
        }
        // Input sanitization
        $titre = Security::sanitizeInput($_POST['titre'] ?? '');
        $description = Security::sanitizeInput($_POST['description'] ?? '');
        $debut = Security::sanitizeInput($_POST['date_debut'] ?? '');
        $fin = Security::sanitizeInput($_POST['date_fin'] ?? '');
        $lieu = Security::sanitizeInput($_POST['lieu'] ?? '');
        $max = (int)($_POST['places_max'] ?? 0);
        if (!$titre || !$debut || !$fin || !$lieu || $max < 1) {
            throw new Exception('Tous les champs sont obligatoires et valides');
        }
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO evenements
            (titre, description, date_debut, date_fin, lieu, places_max, organisateur_id, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'publie')
        ");
        $stmt->execute([$titre, $description, $debut, $fin, $lieu, $max, $_SESSION['user_id']]);
        $success = 'Événement créé avec succès.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Generate CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

include '../includes/header.php';
?>

<main class="dashboard">
  <div class="container">
    <h1>Créer un événement</h1>

    <?php if (isset($verification_required) && $verification_required): ?>
      <div class="verification-required card">
        <div class="verification-icon">
          <i data-lucide="shield-alert"></i>
        </div>
        <h3>Vérification Requise</h3>
        <p>Pour pouvoir créer des événements, vous devez d'abord être vérifié par l'administration.</p>
        <a href="request_verification.php" class="btn btn-primary">
          <i data-lucide="send"></i>
          Demander une Vérification
        </a>
      </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group"><label>Titre</label>
        <input class="form-control" name="titre" required>
      </div>
      <div class="form-group"><label>Description</label>
        <textarea class="form-control" name="description"></textarea>
      </div>
      <div class="form-group"><label>Date début</label>
        <input type="datetime-local" class="form-control" name="date_debut" required>
      </div>
      <div class="form-group"><label>Date fin</label>
        <input type="datetime-local" class="form-control" name="date_fin" required>
      </div>
      <div class="form-group"><label>Lieu</label>
        <input class="form-control" name="lieu" required>
      </div>
      <div class="form-group"><label>Places max</label>
        <input type="number" class="form-control" name="places_max" min="1" required>
      </div>
        <button class="btn btn-primary" type="submit">Créer</button>
      </form>
    <?php endif; ?>
  </div>
</main>

<style>
.verification-required {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 2px solid #ffc107;
    border-radius: 16px;
    margin-bottom: 2rem;
}

.verification-icon {
    width: 80px;
    height: 80px;
    background: #ffc107;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: white;
    font-size: 2rem;
}

.verification-required h3 {
    color: #856404;
    margin-bottom: 1rem;
}

.verification-required p {
    color: #856404;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

<?php include '../includes/footer.php'; ?>
