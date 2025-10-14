<?php
declare(strict_types=1);

// Configuration sécurisée de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'envent_2');
define('DB_USER', 'root');
define('DB_PASS', 'NouveauMotDePasse123');
define('DB_CHARSET', 'utf8mb4');

// Configuration sécurité
define('SITE_KEY', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'); // Clé site reCAPTCHA de test
define('SECRET_KEY', '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'); // Clé secrète reCAPTCHA de test
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Configuration email
define('MAIL_FROM', 'noreply@event2.com');
define('MAIL_FROM_NAME', 'EVENT2 Platform');

// Sécurité des sessions
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_strict_mode', '1');

session_start();
?>
