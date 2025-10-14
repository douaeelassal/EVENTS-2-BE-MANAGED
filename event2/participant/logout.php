<?php
session_start();
// Message flash d'au revoir
$_SESSION['flash_message'] = 'Au revoir ! Vous avez été déconnecté avec succès.';
// Vidage de la session
$_SESSION = [];
// Suppression du cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
// Redirection accueil
header('Location: ../index.php');
exit;
