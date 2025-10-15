<?php
if (!isset($_SESSION)) session_start();
// Affichage message flash si présent
if (!empty($_SESSION['flash_message'])) {
    echo '<div class="flash-message">'.htmlspecialchars($_SESSION['flash_message']).'</div>';
    unset($_SESSION['flash_message']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EVENT2 - Plateforme de gestion d'événements professionnelle">
    <meta name="keywords" content="événements, gestion, organisation, participants">
    <title><?php echo $page_title ?? 'EVENT2'; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- JavaScript -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../assets/js/utils.js"></script>

    <!-- Notifications -->
    <?php
    if (isset($_SESSION['user_id'])) {
        $unreadCount = $notificationSystem->countUnread($_SESSION['user_id']);
    }
    ?>
</head>
<body>
    <?php if (!isset($hide_header) || !$hide_header): ?>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <a href="../index.php" class="logo-link">
                        <img src="../assets/img/logo-event2.jpeg" alt="EVENT2" class="logo">
                    </a>
                </div>

                <?php
                // Masquer la navigation sur les pages d'authentification
                $isAuthPage = strpos($_SERVER['PHP_SELF'], '/auth/') !== false;
                $isLoginPage = basename($_SERVER['PHP_SELF']) === 'login.php';
                $isRegisterPage = basename($_SERVER['PHP_SELF']) === 'register.php';
                ?>

                <?php if (isset($_SESSION['user_id']) && ($isAuthPage || $isLoginPage || $isRegisterPage)): ?>
                    <div class="auth-options" style="margin-left: auto;">
                        <span style="color: var(--grey-medium); font-size: 0.875rem;">
                            Connecté en tant que <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </span>
                       <a href="../auth/logout.php" class="btn-logout">Se déconnecter</a>

                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && !($isAuthPage || $isLoginPage || $isRegisterPage)): ?>
                    <nav class="main-nav">
                        <a href="../index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
                            <i data-lucide="home"></i> Accueil
                        </a>

                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <a href="../admin/dashboard.php" <?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false && basename($_SERVER['PHP_SELF']) !== 'verification_requests.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="shield"></i> Administration
                            </a>
                            <a href="../admin/verification_requests.php" <?php echo basename($_SERVER['PHP_SELF']) == 'verification_requests.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="shield-check"></i> Vérifications
                            </a>
                            <a href="../admin/users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="users"></i> Utilisateurs
                            </a>
                            <a href="../admin/evenements.php" <?php echo basename($_SERVER['PHP_SELF']) == 'evenements.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="calendar"></i> Événements
                            </a>
                            <a href="../admin/stats.php" <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="bar-chart-3"></i> Statistiques
                            </a>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_role'] === 'organisateur'): ?>
                            <a href="../organisateur/dashboard.php" <?php echo strpos($_SERVER['PHP_SELF'], '/organisateur/') !== false ? 'class="active"' : ''; ?>>
                                <i data-lucide="layout-dashboard"></i> Dashboard
                            </a>
                            <a href="../organisateur/evenements.php" <?php echo basename($_SERVER['PHP_SELF']) == 'evenements.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="calendar"></i> Mes Événements
                            </a>
                            <a href="../organisateur/inscriptions.php" <?php echo basename($_SERVER['PHP_SELF']) == 'inscriptions.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="user-check"></i> Inscriptions
                            </a>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_role'] === 'participant'): ?>
                            <a href="../participant/dashboard.php" <?php echo strpos($_SERVER['PHP_SELF'], '/participant/') !== false ? 'class="active"' : ''; ?>>
                                <i data-lucide="layout-dashboard"></i> Dashboard
                            </a>
                            <a href="../participant/evenements.php" <?php echo basename($_SERVER['PHP_SELF']) == 'evenements.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="calendar"></i> Événements
                            </a>
                            <a href="../participant/inscriptions.php" <?php echo basename($_SERVER['PHP_SELF']) == 'inscriptions.php' ? 'class="active"' : ''; ?>>
                                <i data-lucide="bookmark"></i> Mes Inscriptions
                            </a>
                        <?php endif; ?>
                    </nav>

                    <div class="user-menu">

                        <div class="user-info">
                            <span class="user-name">
                                <i data-lucide="user"></i>
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </span>
                            <span class="user-role">
                                <?php echo ucfirst($_SESSION['user_role']); ?>
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" onclick="toggleDropdown()">
                                <i data-lucide="chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdownMenu">
                                <a href="../<?php echo $_SESSION['user_role']; ?>/profil.php">
                                    <i data-lucide="user"></i> Profil
                                </a>
                                <a href="../<?php echo $_SESSION['user_role']; ?>/parametres.php">
                                    <i data-lucide="settings"></i> Paramètres
                                </a>
                                <hr>
                                <a href="../<?= $_SESSION['user_role'] ?>/logout.php" class="text-danger">
                                    <i data-lucide="log-out"></i> Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Dropdown functionality
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdownMenu');
            const dropdownToggle = event.target.closest('.dropdown-toggle');

            if (!dropdownToggle && dropdown) {
                dropdown.style.display = 'none';
            }
        });

        // Mobile menu toggle (si nécessaire)
        function toggleMobileMenu() {
            const nav = document.querySelector('.main-nav');
            nav.classList.toggle('mobile-active');
        }
    </script>

    <style>
        /* Header specific styles */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-name {
            font-weight: 600;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--grey-medium);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            background: none;
                border: none;
                padding: 0.5rem;
                cursor: pointer;
                color: var(--grey-dark);
            }

            .dropdown-menu {
                display: none;
                position: absolute;
                top: 100%;
                right: 0;
                background: var(--white);
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                min-width: 200px;
                z-index: 1000;
                padding: 0.5rem 0;
            }

            .dropdown-menu a {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                text-decoration: none;
                color: var(--grey-dark);
                transition: all 0.3s ease;
            }

            .dropdown-menu a:hover {
                background: var(--grey-light);
                color: var(--cerise-primary);
            }

            .dropdown-menu hr {
                margin: 0.5rem 0;
                border: none;
                border-top: 1px solid var(--grey-light);
            }

            .text-danger {
                color: #dc3545 !important;
            }

            .text-danger:hover {
                background: rgba(220, 53, 69, 0.1) !important;
                color: #dc3545 !important;
            }

            /* Logo link styles */
            .logo-link {
                display: inline-block;
                transition: all 0.3s ease;
                border-radius: 8px;
                padding: 0.5rem;
            }

            .logo-link:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 15px rgba(210, 10, 46, 0.2);
            }

            .logo {
                transition: all 0.3s ease;
                border-radius: 8px;
            }

            .logo-link:hover .logo {
                filter: brightness(1.1);
            }

            /* Mobile responsiveness */
            @media (max-width: 768px) {
                .main-nav {
                    display: none;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: var(--white);
                    box-shadow: 0 4px 20px var(--shadow);
                    flex-direction: column;
                    padding: 1rem;
                    gap: 0.5rem;
                }

                .main-nav.mobile-active {
                    display: flex;
                }

                .user-menu {
                    gap: 0.5rem;
                }

                .user-info {
                    display: none;
                }
            }
    </style>
