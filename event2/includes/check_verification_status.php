<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'Database.php';

session_start();

// Vérification de sécurité - seulement les organisateurs peuvent vérifier leur statut
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

try {
    $db = Database::getInstance();

    // Vérifier le statut actuel de l'organisateur
    $stmt = $db->prepare("SELECT statut_verification FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    echo json_encode([
        'verified' => $user['statut_verification'] === 'verifie',
        'status' => $user['statut_verification']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>