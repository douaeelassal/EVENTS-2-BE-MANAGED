<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

$db = Database::getInstance();
$user = $db->prepare("
    SELECT nom_complet, email, role, date_creation 
    FROM utilisateurs WHERE id = ?
");
$user->execute([$_SESSION['user_id']]);
$data = $user->fetch();

include '../includes/header.php';
?>
<main class="dashboard">
  <div class="container">
    <h1>Mon Profil</h1>
    <table class="table">
      <tr><th>Nom complet</th><td><?=htmlspecialchars($data['nom_complet'])?></td></tr>
      <tr><th>Email</th><td><?=htmlspecialchars($data['email'])?></td></tr>
      <tr><th>Rôle</th><td><?=htmlspecialchars($data['role'])?></td></tr>
      <tr><th>Inscrit le</th><td><?=date('d/m/Y',strtotime($data['date_creation']))?></td></tr>
    </table>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
