<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

$db = Database::getInstance();
$logs = $db->query("
    SELECT l.id, u.nom_complet, l.action, l.date_action, l.details 
    FROM logs_audit l 
    JOIN utilisateurs u ON u.id = l.user_id 
    ORDER BY l.date_action DESC
")->fetchAll();

include '../includes/header.php';
?>
<main class="dashboard">
  <div class="container">
    <h1>Logs d’Audit</h1>
    <table class="table">
      <thead><tr><th>Utilisateur</th><th>Action</th><th>Date</th><th>Détails</th></tr></thead>
      <tbody>
        <?php foreach($logs as $log): ?>
        <tr>
          <td><?=htmlspecialchars($log['nom_complet'])?></td>
          <td><?=htmlspecialchars($log['action'])?></td>
          <td><?=date('d/m/Y H:i', strtotime($log['date_action']))?></td>
          <td><?=htmlspecialchars($log['details'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
