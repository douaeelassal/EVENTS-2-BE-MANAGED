<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="evenements_export_' . date('Y-m-d_H-i-s') . '.csv"');

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
    $organisateur_filter = $_GET['organisateur'] ?? '';

    // Construction de la requête (similaire à evenements.php)
    $where_conditions = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "e.titre LIKE ?";
        $params[] = "%$search%";
    }

    if (!empty($status_filter)) {
        $where_conditions[] = "e.statut = ?";
        $params[] = $status_filter;
    }

    if (!empty($organisateur_filter)) {
        $where_conditions[] = "e.organisateur_id = ?";
        $params[] = $organisateur_filter;
    }

    // Requête pour récupérer tous les événements avec infos organisateur
    $sql = "
        SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.lieu,
               e.places_max, e.statut, e.date_creation,
               u.nom_complet as organisateur_nom, u.email as organisateur_email,
               COUNT(i.id) as total_inscriptions,
               COUNT(CASE WHEN i.statut = 'confirme' THEN 1 END) as inscriptions_confirmees
        FROM evenements e
        LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
        LEFT JOIN inscriptions i ON e.id = i.evenement_id
        WHERE " . implode(" AND ", $where_conditions) . "
        GROUP BY e.id
        ORDER BY e.date_creation DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ouvrir la sortie standard pour écrire le CSV
    $output = fopen('php://output', 'w');

    // En-têtes UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-tête du CSV
    fputcsv($output, [
        'ID',
        'Titre',
        'Description',
        'Date début',
        'Date fin',
        'Lieu',
        'Places max',
        'Statut',
        'Organisateur',
        'Email organisateur',
        'Inscriptions totales',
        'Inscriptions confirmées',
        'Date création'
    ], ';');

    // Données des événements
    foreach ($evenements as $evenement) {
        fputcsv($output, [
            $evenement['id'],
            $evenement['titre'],
            $evenement['description'] ?? '',
            date('d/m/Y H:i:s', strtotime($evenement['date_debut'])),
            date('d/m/Y H:i:s', strtotime($evenement['date_fin'])),
            $evenement['lieu'] ?? '',
            $evenement['places_max'] ?? 'Illimité',
            getStatusLabel($evenement['statut']),
            $evenement['organisateur_nom'] ?? 'N/A',
            $evenement['organisateur_email'] ?? '',
            $evenement['total_inscriptions'],
            $evenement['inscriptions_confirmees'],
            date('d/m/Y H:i:s', strtotime($evenement['date_creation']))
        ], ';');
    }

    fclose($output);

    // Log de l'export
    $stmt = $db->prepare("
        INSERT INTO logs_audit (user_id, action, details)
        VALUES (?, 'export_events', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Export de " . count($evenements) . " événements (recherche: '$search', statut: '$status_filter', organisateur: '$organisateur_filter')"
    ]);

} catch (Exception $e) {
    error_log('Erreur export événements: ' . $e->getMessage());

    // En cas d'erreur, retourner une page d'erreur simple
    http_response_code(500);
    echo "Erreur lors de l'export: " . $e->getMessage();
    exit;
}

// Fonction pour obtenir le label du statut
function getStatusLabel($status) {
    $labels = [
        'brouillon' => 'Brouillon',
        'publie' => 'Publié',
        'termine' => 'Terminé',
        'rejete' => 'Rejeté',
        'archive' => 'Archivé'
    ];

    return $labels[$status] ?? $status;
}
?>