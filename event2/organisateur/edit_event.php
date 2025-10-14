<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM evenements WHERE id = ? AND organisateur_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$evt = $stmt->fetch();
if (!$evt) {
    header('Location: evenements.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token invalide');
        }
        $titre = Security::sanitizeInput($_POST['titre']);
        $description = Security::sanitizeInput($_POST['description']);
        $debut = Security::sanitizeInput($_POST['date_debut']);
        $fin = Security::sanitizeInput($_POST['date_fin']);
        $lieu = Security::sanitizeInput($_POST['lieu']);
        $max = (int)$_POST['places_max'];
        $statut = Security::sanitizeInput($_POST['statut']);
        $db->prepare("
            UPDATE evenements
            SET titre=?, description=?, date_debut=?, date_fin=?, lieu=?, places_max=?, statut=?
            WHERE id=? AND organisateur_id=?
        ")->execute([$titre,$description,$debut,$fin,$lieu,$max,$statut,$id,$_SESSION['user_id']]);
        $success = 'Événement mis à jour.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateToken();
}

include '../includes/header.php';
?>
<main class="dashboard">
  <div class="container">
    <h1>Modifier un événement</h1>
    <?php if ($error): ?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group"><label>Titre</label>
        <input class="form-control" name="titre" value="<?=htmlspecialchars($evt['titre'])?>" required>
      </div>
      <div class="form-group"><label>Description</label>
        <textarea class="form-control" name="description"><?=htmlspecialchars($evt['description'])?></textarea>
      </div>
      <div class="form-group"><label>Date début</label>
        <input type="datetime-local" class="form-control" name="date_debut" value="<?=str_replace(' ','T',$evt['date_debut'])?>" required>
      </div>
      <div class="form-group"><label>Date fin</label>
        <input type="datetime-local" class="form-control" name="date_fin" value="<?=str_replace(' ','T',$evt['date_fin'])?>" required>
      </div>
      <div class="form-group"><label>Lieu</label>
        <input class="form-control" name="lieu" value="<?=htmlspecialchars($evt['lieu'])?>" required>
      </div>
      <div class="form-group"><label>Places max</label>
        <input type="number" class="form-control" name="places_max" value="<?=$evt['places_max']?>" min="1" required>
      </div>
      <div class="form-group"><label>Statut</label>
        <select class="form-control" name="statut">
          <option <?=$evt['statut']==='brouillon'?'selected':''?> value="brouillon">Brouillon</option>
          <option <?=$evt['statut']==='publie'?'selected':''?> value="publie">Publié</option>
          <option <?=$evt['statut']==='termine'?'selected':''?> value="termine">Terminé</option>
          <option <?=$evt['statut']==='archive'?'selected':''?> value="archive">Archivé</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Enregistrer</button>
    </form>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
