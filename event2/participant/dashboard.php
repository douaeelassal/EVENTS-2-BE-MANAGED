<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();

// Récupération des événements publiés récents (5 derniers)
$stmt = $db->prepare("
    SELECT e.id, e.titre, e.date_debut, e.lieu, e.statut,
           (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id) AS inscrits,
           e.places_max
    FROM evenements e
    WHERE e.statut = 'publie'
    ORDER BY e.date_debut ASC
    LIMIT 5
");
$stmt->execute();
$recent_events = $stmt->fetchAll();

// Récupération des inscriptions du participant
// Vérifier d'abord quelle colonne existe
$columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_COLUMN);
$user_column = in_array('participant_id', $columns) ? 'participant_id' : 'utilisateur_id';

$stmt = $db->prepare("
    SELECT COUNT(*) as total_inscriptions
    FROM inscriptions i
    WHERE i.$user_column = ?
");
$stmt->execute([$_SESSION['user_id']]);
$inscription_stats = $stmt->fetch();
$total_inscriptions = $inscription_stats['total_inscriptions'];

include '../includes/header.php';
?>

<main class="dashboard" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #D20A2E; margin-bottom: 10px;">Mon espace participant</h1>
        <p style="font-size: 18px; color: #666;">
            Bienvenue, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> !
            Découvrez les événements et gérez vos inscriptions.
        </p>
    </div>

    <!-- Statistiques rapides -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #333;">Mes Inscriptions</h3>
            <div style="font-size: 36px; font-weight: bold; color: #D20A2E; margin: 15px 0;">
                <?= $total_inscriptions ?>
            </div>
            <a href="inscriptions.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Voir mes inscriptions
            </a>
        </div>

        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #333;">Événements Disponibles</h3>
            <div style="font-size: 36px; font-weight: bold; color: #D20A2E; margin: 15px 0;">
                <?= count($recent_events) ?>
            </div>
            <a href="evenements.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Voir tous les événements
            </a>
        </div>

        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #333;">Actions Rapides</h3>
            <div style="margin: 15px 0;">
                <a href="profil.php" style="background: #D20A2E; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; margin: 5px;">
                    Mon profil
                </a>
                <a href="attestations.php" style="background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; margin: 5px;">
                    Mes attestations
                </a>
            </div>
        </div>
    </div>

    <!-- Événements disponibles -->
    <div style="margin-bottom: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #333; margin: 0;">Événements Disponibles</h2>
            <a href="evenements.php" style="background: #D20A2E; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Voir Tous
            </a>
        </div>

        <?php if (empty($recent_events)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #666;">
                <h3 style="margin-bottom: 15px;">Aucun événement disponible</h3>
                <p>Il n'y a pas d'événement publié pour le moment.</p>
                <p>Revenez plus tard pour découvrir de nouveaux événements !</p>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($recent_events as $event): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-left: 4px solid #D20A2E;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 8px 0; color: #333; font-size: 18px;">
                                <?= htmlspecialchars($event['titre']) ?>
                            </h4>
                            <p style="margin: 4px 0; color: #666; font-size: 14px;">
                                📅 <?= date('d/m/Y H:i', strtotime($event['date_debut'])) ?>
                            </p>
                            <p style="margin: 4px 0; color: #666; font-size: 14px;">
                                📍 <?= htmlspecialchars($event['lieu']) ?>
                            </p>
                            <div style="margin-top: 8px;">
                                <span style="color: #666; font-size: 14px;">
                                    <?= $event['inscrits'] ?>/<?= $event['places_max'] ?? '∞' ?> inscrits
                                </span>
                                <?php if ($event['inscrits'] >= ($event['places_max'] ?? PHP_INT_MAX)): ?>
                                    <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                                        Complet
                                    </span>
                                <?php else: ?>
                                    <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                                        Disponible
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <?php if ($event['inscrits'] < ($event['places_max'] ?? PHP_INT_MAX)): ?>
                                <a href="inscriptions.php?event_id=<?= $event['id'] ?>"
                                   style="background: #D20A2E; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-size: 14px;">
                                    S'inscrire
                                </a>
                            <?php else: ?>
                                <button style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 5px; font-size: 14px; cursor: not-allowed;">
                                    Complet
                                </button>
                            <?php endif; ?>
                            <a href="evenements.php?event_id=<?= $event['id'] ?>"
                               style="background: transparent; color: #D20A2E; padding: 8px 16px; border: 2px solid #D20A2E; text-decoration: none; border-radius: 5px; font-size: 14px;">
                                Détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Liens rapides restants -->
    <div style="margin-bottom: 40px;">
        <h2 style="color: #333; margin-bottom: 20px;">Accès Rapide</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <a href="evenements.php" style="display: flex; flex-direction: column; align-items: center; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333; transition: transform 0.3s ease;">
                <div style="width: 60px; height: 60px; background: #D20A2E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: white; font-size: 24px;">📅</div>
                <h3 style="margin: 0 0 8px 0;">Tous les Événements</h3>
                <p style="margin: 0; font-size: 14px; opacity: 0.8; text-align: center;">Voir la liste complète des événements</p>
            </a>
            <a href="inscriptions.php" style="display: flex; flex-direction: column; align-items: center; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333; transition: transform 0.3s ease;">
                <div style="width: 60px; height: 60px; background: #D20A2E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: white; font-size: 24px;">🔖</div>
                <h3 style="margin: 0 0 8px 0;">Mes Inscriptions</h3>
                <p style="margin: 0; font-size: 14px; opacity: 0.8; text-align: center;">Gérer mes participations</p>
            </a>
            <a href="attestations.php" style="display: flex; flex-direction: column; align-items: center; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333; transition: transform 0.3s ease;">
                <div style="width: 60px; height: 60px; background: #D20A2E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: white; font-size: 24px;">🏆</div>
                <h3 style="margin: 0 0 8px 0;">Attestations</h3>
                <p style="margin: 0; font-size: 14px; opacity: 0.8; text-align: center;">Télécharger mes certificats</p>
            </a>
            <a href="profil.php" style="display: flex; flex-direction: column; align-items: center; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333; transition: transform 0.3s ease;">
                <div style="width: 60px; height: 60px; background: #D20A2E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: white; font-size: 24px;">👤</div>
                <h3 style="margin: 0 0 8px 0;">Profil</h3>
                <p style="margin: 0; font-size: 14px; opacity: 0.8; text-align: center;">Gérer mon compte</p>
            </a>
        </div>
    </div>
</main>


<?php include '../includes/footer.php'; ?>

