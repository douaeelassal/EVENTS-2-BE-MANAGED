<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token invalide');
        }
        $eventId = (int)$_POST['event_id'];
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur upload');
        }
        if ($_FILES['file']['size'] > MAX_FILE_SIZE) {
            throw new Exception('Fichier trop lourd');
        }
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $filename = Security::generateToken() . ".$ext";
        $target = UPLOAD_PATH . "fichiers/$filename";

        // Ensure the fichiers directory exists
        $fichiers_dir = UPLOAD_PATH . "fichiers/";
        if (!file_exists($fichiers_dir)) {
            mkdir($fichiers_dir, 0755, true);
        }

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            throw new Exception('Erreur lors du déplacement du fichier vers: ' . $target);
        }
        $db = Database::getInstance();
        $db->prepare("
            INSERT INTO fichiers_evenements (evenement_id, nom_fichier, chemin_fichier, type_contenu)
            VALUES (?, ?, ?, ?)
        ")->execute([$eventId, $_FILES['file']['name'], $filename, $_FILES['file']['type']]);
        $success = 'Fichier uploadé.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$db = Database::getInstance();
$events = $db->query("SELECT id, titre FROM evenements WHERE organisateur_id = {$_SESSION['user_id']}")->fetchAll();

include '../includes/header.php';
?>
<main class="dashboard">
  <div class="container">
    <h1>Gérer les fichiers</h1>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="form-group"><label>Événement</label>
        <select name="event_id" class="form-control" required>
          <?php foreach($events as $e): ?>
            <option value="<?=$e['id']?>"><?=htmlspecialchars($e['titre'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Fichier (max 5MB)</label>
        <input type="file" name="file" class="form-control" accept="application/pdf,image/*" required>
      </div>
      <button class="btn btn-primary" type="submit">Uploader</button>
    </form>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
