<?php
declare(strict_types=1);
require_once '../includes/config.php';

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vider le tableau de session
$_SESSION = [];

// Détruire la session
session_destroy();

// Redirection vers la page d'accueil
header('Location: ../index.php');
exit;
