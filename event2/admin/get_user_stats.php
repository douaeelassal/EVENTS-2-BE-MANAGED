<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

header('Content-Type: application/json');

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID utilisateur manquant']);
    exit;
}

try {
    $db = Database::getInstance();

    // Vérifier que l'utilisateur existe
    $stmt = $db->prepare("SELECT id, role FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Utilisateur non trouvé']);
        exit;
    }

    $stats = [];

    // Statistiques selon le rôle
    if ($user['role'] === 'organisateur') {
        // Événements créés
        $stmt = $db->prepare("SELECT COUNT(*) FROM evenements WHERE organisateur_id = ?");
        $stmt->execute([$user_id]);
        $stats['events_created'] = $stmt->fetchColumn();

        // Inscriptions à ses événements
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM inscriptions i
            JOIN evenements e ON e.id = i.evenement_id
            WHERE e.organisateur_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats['total_inscriptions'] = $stmt->fetchColumn();
    } else {
        // Inscriptions du participant
        $stmt = $db->prepare("SELECT COUNT(*) FROM inscriptions WHERE utilisateur_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_inscriptions'] = $stmt->fetchColumn();
    }

    // Actions de modération (si admin)
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM logs_audit WHERE user_id = ? AND action IN ('delete_user', 'toggle_verify')");
        $stmt->execute([$user_id]);
        $stats['moderation_actions'] = $stmt->fetchColumn();
    }

    // Activité récente (10 derniers logs)
    $stmt = $db->prepare("
        SELECT action, details, created_at as date
        FROM logs_audit
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter les labels d'action
    foreach ($recent_activity as &$activity) {
        $activity['action_label'] = getActionLabel($activity['action']);
    }

    $stats['recent_activity'] = $recent_activity;

    echo json_encode($stats);

} catch (Exception $e) {
    error_log('Erreur get_user_stats: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}

// Fonction pour obtenir le label d'une action
function getActionLabel($action) {
    $labels = [
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'create_event' => 'Création d\'événement',
        'inscription' => 'Inscription à un événement',
        'delete_user' => 'Suppression d\'utilisateur',
        'toggle_verify' => 'Changement de statut de vérification',
        'envoi_email' => 'Envoi d\'email',
        'edit_event' => 'Modification d\'événement',
        'view_event' => 'Consultation d\'événement'
    ];

    return $labels[$action] ?? ucfirst($action);
}
?>