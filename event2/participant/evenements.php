<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/Database.php';

$db = Database::getInstance();

// Récupération des événements publiés
$stmt = $db->prepare("
    SELECT e.id, e.titre, e.date_debut, e.lieu, e.statut,
      (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id) AS inscrits,
      e.places_max
    FROM evenements e
    WHERE e.statut = 'publie'
    ORDER BY e.date_debut
");
$stmt->execute();
$events = $stmt->fetchAll();

include '../includes/header.php';
?>

<main class="dashboard">
  <div class="container">
    <h1>Événements disponibles</h1>
    <?php if (empty($events)): ?>
      <p>Aucun événement publié pour le moment.</p>
    <?php else: ?>
      <div class="dashboard-grid">
        <?php foreach ($events as $e): ?>
          <div class="card">
            <h3><?= htmlspecialchars($e['titre']) ?></h3>
            <p><?= date('d/m/Y H:i', strtotime($e['date_debut'])) ?> – <?= htmlspecialchars($e['lieu']) ?></p>
            <p>
              <?= $e['inscrits'] ?>/<?= $e['places_max'] ?? '∞' ?> inscrits
            </p>
            <?php if ($e['inscrits'] < $e['places_max']): ?>
              <a href="inscriptions.php?event_id=<?= $e['id'] ?>"
                 class="btn btn-primary">S’inscrire</a>
            <?php else: ?>
              <button class="btn btn-secondary" disabled>Complet</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../includes/footer.php'; ?>
