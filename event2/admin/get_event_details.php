<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

header('Content-Type: application/json');

try {
    // Vérifier la session admin
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        throw new Exception('Accès non autorisé');
    }

    $event_id = (int)($_GET['event_id'] ?? 0);

    if ($event_id <= 0) {
        throw new Exception('ID d\'événement invalide');
    }

    $db = Database::getInstance();

    // Récupérer les détails de l'événement avec informations de l'organisateur
    $stmt = $db->prepare("
        SELECT e.*,
               u.nom_complet as organisateur_nom,
               u.email as organisateur_email,
               COUNT(i.id) as total_inscriptions,
               COUNT(CASE WHEN i.statut = 'confirme' THEN 1 END) as inscriptions_confirmees
        FROM evenements e
        JOIN utilisateurs u ON e.organisateur_id = u.id
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        WHERE e.id = ?
        GROUP BY e.id
    ");

    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        throw new Exception('Événement non trouvé');
    }

    // Fonction pour obtenir le label du statut
    function getStatusLabel($status) {
        switch ($status) {
            case 'actif':
                return 'Actif';
            case 'publie':
                return 'Publié';
            case 'termine':
                return 'Terminé';
            case 'annule':
                return 'Annulé';
            case 'brouillon':
            default:
                return 'Brouillon';
        }
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
            'places_max' => $event['places_max'],
            'prix' => $event['prix'],
            'statut' => $event['statut'],
            'statut_label' => getStatusLabel($event['statut']),
            'organisateur_nom' => $event['organisateur_nom'],
            'organisateur_email' => $event['organisateur_email'],
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