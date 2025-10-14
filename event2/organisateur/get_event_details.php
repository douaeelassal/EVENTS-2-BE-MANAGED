<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/Security.php';

header('Content-Type: application/json');

try {
    // Vérifier la session
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
        throw new Exception('Accès non autorisé');
    }

    $event_id = (int)($_GET['event_id'] ?? 0);

    if ($event_id <= 0) {
        throw new Exception('ID d\'événement invalide');
    }

    $db = Database::getInstance();

    // Récupérer les détails de l'événement
    $stmt = $db->prepare("
        SELECT e.*,
               COUNT(i.id) as total_inscriptions,
               COUNT(CASE WHEN i.statut = 'confirme' THEN 1 END) as inscriptions_confirmees
        FROM evenements e
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        WHERE e.id = ? AND e.organisateur_id = ?
        GROUP BY e.id
    ");

    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $event = $stmt->fetch();

    if (!$event) {
        throw new Exception('Événement non trouvé ou accès non autorisé');
    }

    // Retourner les données au format JSON
    echo json_encode([
        'success' => true,
        'event' => [
            'id' => $event['id'],
            'titre' => $event['titre'],
            'description' => $event['description'],
            'date_debut' => $event['date_debut'],
            'date_fin' => $event['date_fin'],
            'lieu' => $event['lieu'],
            'duree' => $event['duree'],
            'places_max' => $event['places_max'],
            'prix' => $event['prix'],
            'statut' => $event['statut'],
            'total_inscriptions' => $event['total_inscriptions'],
            'inscriptions_confirmees' => $event['inscriptions_confirmees']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>