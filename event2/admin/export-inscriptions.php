<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inscriptions_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Accès refusé');
}

try {
    $db = Database::getInstance();

    // Récupération des paramètres de recherche depuis l'URL
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $event_filter = $_GET['event'] ?? '';

    // Construction de la requête (similaire à inscriptions.php)
    $where_conditions = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(u.nom_complet LIKE ? OR u.email LIKE ? OR e.titre LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($status_filter)) {
        $where_conditions[] = "i.statut = ?";
        $params[] = $status_filter;
    }

    if (!empty($event_filter)) {
        $where_conditions[] = "i.evenement_id = ?";
        $params[] = $event_filter;
    }

    // Requête pour récupérer toutes les inscriptions avec infos détaillées
    $sql = "
        SELECT i.id, i.statut, i.date_inscription,
               u.nom_complet as participant_nom, u.email as participant_email, u.role as participant_role,
               e.titre as evenement_titre, e.date_debut, e.lieu as evenement_lieu,
               o.nom_complet as organisateur_nom, o.email as organisateur_email
        FROM inscriptions i
        JOIN utilisateurs u ON u.id = i.utilisateur_id
        JOIN evenements e ON e.id = i.evenement_id
        LEFT JOIN utilisateurs o ON e.organisateur_id = o.id
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY i.date_inscription DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ouvrir la sortie standard pour écrire le CSV
    $output = fopen('php://output', 'w');

    // En-têtes UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-tête du CSV
    fputcsv($output, [
        'ID',
        'Participant',
        'Email participant',
        'Rôle participant',
        'Événement',
        'Date événement',
        'Lieu événement',
        'Organisateur',
        'Email organisateur',
        'Statut inscription',
        'Date inscription'
    ], ';');

    // Données des inscriptions
    foreach ($inscriptions as $inscription) {
        fputcsv($output, [
            $inscription['id'],
            $inscription['participant_nom'],
            $inscription['participant_email'],
            ucfirst($inscription['participant_role']),
            $inscription['evenement_titre'],
            date('d/m/Y H:i:s', strtotime($inscription['date_debut'])),
            $inscription['evenement_lieu'] ?? '',
            $inscription['organisateur_nom'] ?? 'N/A',
            $inscription['organisateur_email'] ?? '',
            getStatusLabel($inscription['statut']),
            date('d/m/Y H:i:s', strtotime($inscription['date_inscription']))
        ], ';');
    }

    fclose($output);

    // Log de l'export
    $stmt = $db->prepare("
        INSERT INTO logs_audit (user_id, action, details)
        VALUES (?, 'export_inscriptions', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Export de " . count($inscriptions) . " inscriptions (recherche: '$search', statut: '$status_filter', événement: '$event_filter')"
    ]);

} catch (Exception $e) {
    error_log('Erreur export inscriptions: ' . $e->getMessage());

    // En cas d'erreur, retourner une page d'erreur simple
    http_response_code(500);
    echo "Erreur lors de l'export: " . $e->getMessage();
    exit;
}

// Fonction pour obtenir le label du statut
function getStatusLabel($status) {
    $labels = [
        'en_attente' => 'En attente',
        'confirme' => 'Confirmée',
        'annule' => 'Annulée'
    ];

    return $labels[$status] ?? $status;
}
?>