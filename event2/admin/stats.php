<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

$db = Database::getInstance();
$totalUsers = $db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
$totalEvents = $db->query("SELECT COUNT(*) FROM evenements")->fetchColumn();
$totalRegistrations = $db->query("SELECT COUNT(*) FROM inscriptions")->fetchColumn();

include '../includes/header.php';
?>
<main class="dashboard">
  <div class="container">
    <h1>Statistiques Générales</h1>
    <div class="dashboard-grid">
      <div class="card"><h3>Utilisateurs</h3><p><?= $totalUsers ?></p></div>
      <div class="card"><h3>Événements</h3><p><?= $totalEvents ?></p></div>
      <div class="card"><h3>Inscriptions</h3><p><?= $totalRegistrations ?></p></div>
    </div>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
