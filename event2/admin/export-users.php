<?php
declare(strict_types=1);
require_once '../includes/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="utilisateurs_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Accès refusé');
}

try {
    $db = Database::getInstance();

    // Récupération des paramètres de recherche depuis l'URL
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';

    // Construction de la requête (similaire à users.php)
    $where_conditions = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(nom_complet LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($role_filter)) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'verified') {
            $where_conditions[] = "email_verifie = TRUE";
        } elseif ($status_filter === 'unverified') {
            $where_conditions[] = "email_verifie = FALSE";
        } elseif ($status_filter === 'recent') {
            $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }

    // Requête pour récupérer tous les utilisateurs (pas de LIMIT pour l'export)
    $sql = "
        SELECT id, nom_complet, email, role, date_creation, email_verifie
        FROM utilisateurs
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY date_creation DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ouvrir la sortie standard pour écrire le CSV
    $output = fopen('php://output', 'w');

    // En-têtes UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-tête du CSV
    fputcsv($output, [
        'ID',
        'Nom complet',
        'Email',
        'Rôle',
        'Date d\'inscription',
        'Email vérifié',
        'Statut'
    ], ';');

    // Données des utilisateurs
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['nom_complet'],
            $user['email'],
            ucfirst($user['role']),
            date('d/m/Y H:i:s', strtotime($user['date_creation'])),
            $user['email_verifie'] ? 'Oui' : 'Non',
            $user['email_verifie'] ? 'Vérifié' : 'Non vérifié'
        ], ';');
    }

    fclose($output);

    // Log de l'export
    $stmt = $db->prepare("
        INSERT INTO logs_audit (user_id, action, details)
        VALUES (?, 'export_users', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Export de " . count($users) . " utilisateurs (recherche: '$search', rôle: '$role_filter', statut: '$status_filter')"
    ]);

} catch (Exception $e) {
    error_log('Erreur export utilisateurs: ' . $e->getMessage());

    // En cas d'erreur, retourner une page d'erreur simple
    http_response_code(500);
    echo "Erreur lors de l'export: " . $e->getMessage();
    exit;
}
?>