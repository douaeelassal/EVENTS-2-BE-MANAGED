<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-info">
                <p>&copy; <?=date('Y')?> EVENT2. Tous droits réservés.</p>
                <p class="footer-description">
                    Plateforme professionnelle de gestion d'événements
                </p>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="footer-actions">
                    <span class="user-info">
                        Connecté en tant que <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        (<?php echo ucfirst($_SESSION['user_role']); ?>)
                    </span>
                    <a href="../<?php echo $_SESSION['user_role']; ?>/logout.php" class="btn btn-secondary">
                        <i data-lucide="log-out"></i>
                        Déconnexion
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-bottom">
            <div class="footer-links">
                <a href="../index.php">Accueil</a>
                <a href="../contact.php">Contact</a>
                <a href="../mentions-legales.php">Mentions légales</a>
                <a href="../politique-confidentialite.php">Politique de confidentialité</a>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer amélioré */
.main-footer {
    background: linear-gradient(135deg, var(--grey-dark) 0%, #2C3E50 100%);
    color: var(--white);
    margin-top: 4rem;
    position: relative;
}

.main-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient);
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem 0;
    flex-wrap: wrap;
    gap: 2rem;
}

.footer-info {
    flex: 1;
}

.footer-info p {
    margin: 0 0 0.5rem 0;
    opacity: 0.9;
}

.footer-description {
    font-size: 0.875rem;
    opacity: 0.7;
}

.footer-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.user-info {
    font-size: 0.875rem;
    opacity: 0.8;
    color: var(--grey-light);
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1.5rem;
    margin-top: 1.5rem;
}

.footer-links {
    display: flex;
    gap: 2rem;
    justify-content: center;
    flex-wrap: wrap;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: var(--cerise-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
    }

    .footer-actions {
        justify-content: center;
    }

    .footer-links {
        gap: 1rem;
    }
}
</style>

<script>
// Initialiser les icônes du footer si nécessaire
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

</body>
</html>
