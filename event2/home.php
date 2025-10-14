<?php
require_once 'includes/config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT titre, date_debut, lieu, description
        FROM evenements
        WHERE statut = 'actif'
        ORDER BY date_debut DESC
        LIMIT 10
    ");
    $stmt->execute();
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    $events = [];
}
?>

<main class="hero">
    <div class="hero-content container">
        <div class="hero-logo-container">
            <img src="assets/img/logo-event2.jpeg" alt="EVENT2" class="hero-logo" onerror="this.style.display='none';">
        </div>

        <h1 class="hero-title">EVENT2</h1>
        <p class="hero-subtitle">Plateforme de Gestion d'Événements</p>

        <p class="hero-description">
            La solution complète pour organiser et gérer vos événements en toute simplicité.
        </p>

        <?php if (!empty($events)): ?>
        <div class="events-slideshow">
            <h2 class="slideshow-title">📅 Événements Disponibles</h2>
            <div class="slideshow-container">
                <div class="slideshow-wrapper">
                    <div class="slideshow-track" id="slideshowTrack">
                        <?php foreach ($events as $event): ?>
                        <div class="slide">
                            <div class="slide-content">
                                <h3 class="slide-title"><?php echo htmlspecialchars($event['titre']); ?></h3>
                                <p class="slide-date">
                                    <i data-lucide="calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($event['date_debut'])); ?>
                                </p>
                                <?php if ($event['lieu']): ?>
                                <p class="slide-location">
                                    <i data-lucide="map-pin"></i>
                                    <?php echo htmlspecialchars($event['lieu']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($event['description']): ?>
                                <p class="slide-description">
                                    <?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($events, 0, 3) as $event): ?>
                        <div class="slide">
                            <div class="slide-content">
                                <h3 class="slide-title"><?php echo htmlspecialchars($event['titre']); ?></h3>
                                <p class="slide-date">
                                    <i data-lucide="calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($event['date_debut'])); ?>
                                </p>
                                <?php if ($event['lieu']): ?>
                                <p class="slide-location">
                                    <i data-lucide="map-pin"></i>
                                    <?php echo htmlspecialchars($event['lieu']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($event['description']): ?>
                                <p class="slide-description">
                                    <?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="slideshow-btn slideshow-btn-prev" id="prevBtn">
                    <i data-lucide="chevron-left"></i>
                </button>
                <button class="slideshow-btn slideshow-btn-next" id="nextBtn">
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
            <div class="slideshow-indicators">
                <?php foreach ($events as $index => $event): ?>
                <span class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="hero-buttons">
            <a href="auth/login.php" class="btn btn-primary">
                <i data-lucide="log-in"></i>
                <span class="btn-text">Se connecter</span>
                <span class="btn-shine"></span>
            </a>
            <a href="auth/register.php" class="btn btn-secondary">
                <i data-lucide="user-plus"></i>
                <span class="btn-text">S'inscrire</span>
                <span class="btn-shine"></span>
            </a>
        </div>
    </div>
</main>

<style>
.hero {
    background: linear-gradient(135deg, #D20A2E 0%, #A0081F 100%);
    color: white;
    text-align: center;
    padding: 4rem 2rem;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.hero-logo-container {
    margin-bottom: 2rem;
}

.hero-logo {
    width: 150px;
    height: 150px;
    animation: bounce 2s ease-in-out infinite;
}

.hero-title {
    font-size: 4rem;
    font-weight: 800;
    margin-bottom: 1rem;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.hero-subtitle {
    font-size: 1.5rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    font-weight: 300;
}

.hero-description {
    font-size: 1.25rem;
    line-height: 1.6;
    margin-bottom: 3rem;
    opacity: 0.95;
}

.hero-buttons {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.hero-buttons .btn {
    padding: 1rem 2rem;
    font-size: 1.125rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 50px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
}

.hero-buttons .btn-secondary {
    background: linear-gradient(135deg, #28a745, #20c997);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
}

.hero-buttons .btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.hero-buttons .btn:hover::before {
    left: 100%;
}

.hero-buttons .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(0, 123, 255, 0.4);
}

.hero-buttons .btn-secondary:hover {
    box-shadow: 0 12px 35px rgba(40, 167, 69, 0.4);
}

.btn-shine {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.2) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.hero-buttons .btn:hover .btn-shine {
    transform: translateX(100%);
}

.events-slideshow {
    margin: 3rem 0;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.slideshow-title {
    font-size: 2rem;
    color: white;
    margin-bottom: 2rem;
    text-align: center;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.slideshow-container {
    position: relative;
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    height: 200px;
    overflow: hidden;
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.slideshow-wrapper {
    width: 100%;
    height: 100%;
    position: relative;
}

.slideshow-track {
    display: flex;
    height: 100%;
    transition: transform 0.5s ease-in-out;
}

.slide {
    min-width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.slide-content {
    text-align: center;
    color: white;
}

.slide-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
    color: #ffd700;
    text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
}

.slide-date, .slide-location {
    font-size: 1.1rem;
    margin: 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.slide-description {
    font-size: 1rem;
    margin-top: 1rem;
    opacity: 0.9;
    line-height: 1.4;
}

.slideshow-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    font-size: 1.2rem;
}

.slideshow-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-50%) scale(1.1);
}

.slideshow-btn-prev {
    left: 20px;
}

.slideshow-btn-next {
    right: 20px;
}

.slideshow-indicators {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 1rem;
}

.indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

.indicator.active {
    background: #ffd700;
    transform: scale(1.3);
}

@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0, -20px, 0);
    }
    70% {
        transform: translate3d(0, -10px, 0);
    }
    90% {
        transform: translate3d(0, -4px, 0);
    }
}

@media (max-width: 768px) {
    .hero {
        padding: 3rem 1rem;
    }

    .hero-title {
        font-size: 3rem;
    }

    .hero-subtitle {
        font-size: 1.25rem;
    }

    .hero-description {
        font-size: 1.125rem;
    }

    .hero-logo {
        width: 100px;
        height: 100px;
    }

    .hero-buttons {
        flex-direction: column;
        align-items: center;
    }

    .hero-buttons .btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    initSlideshow();
});

function initSlideshow() {
    const track = document.getElementById('slideshowTrack');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const indicators = document.querySelectorAll('.indicator');
    const slides = document.querySelectorAll('.slide');

    if (!track || slides.length === 0) return;

    let currentSlide = 0;
    const totalSlides = slides.length;
    const autoSlideInterval = 4000; // 4 secondes
    let autoSlideTimer;

    // Fonction pour mettre à jour la position du diaporama
    function updateSlide() {
        const translateX = -currentSlide * 100;
        track.style.transform = `translateX(${translateX}%)`;

        // Mettre à jour les indicateurs
        indicators.forEach((indicator, index) => {
            if (index === currentSlide % indicators.length) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
    }

    // Fonction pour aller au slide suivant
    function nextSlide() {
        currentSlide++;
        updateSlide();

        // Si on atteint la fin des slides originales, revenir au début
        if (currentSlide >= indicators.length) {
            setTimeout(() => {
                currentSlide = 0;
                track.style.transition = 'none';
                updateSlide();
                setTimeout(() => {
                    track.style.transition = 'transform 0.5s ease-in-out';
                }, 50);
            }, 500);
        }
    }

    // Fonction pour aller au slide précédent
    function prevSlide() {
        currentSlide--;
        if (currentSlide < 0) {
            currentSlide = indicators.length - 1;
            track.style.transition = 'none';
            updateSlide();
            setTimeout(() => {
                track.style.transition = 'transform 0.5s ease-in-out';
                currentSlide = indicators.length - 1;
                updateSlide();
            }, 50);
        } else {
            updateSlide();
        }
    }

    // Démarrer le défilement automatique
    function startAutoSlide() {
        autoSlideTimer = setInterval(nextSlide, autoSlideInterval);
    }

    // Arrêter le défilement automatique
    function stopAutoSlide() {
        clearInterval(autoSlideTimer);
    }

    // Écouteurs d'événements
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            stopAutoSlide();
            nextSlide();
            startAutoSlide();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            stopAutoSlide();
            prevSlide();
            startAutoSlide();
        });
    }

    // Écouteurs pour les indicateurs
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            stopAutoSlide();
            currentSlide = index;
            updateSlide();
            startAutoSlide();
        });
    });

    // Pause au survol
    const slideshowContainer = document.querySelector('.slideshow-container');
    if (slideshowContainer) {
        slideshowContainer.addEventListener('mouseenter', stopAutoSlide);
        slideshowContainer.addEventListener('mouseleave', startAutoSlide);
    }

    // Démarrer le diaporama automatique
    startAutoSlide();

    // Remettre la transition après un cycle complet
    setInterval(() => {
        if (currentSlide >= indicators.length) {
            track.style.transition = 'none';
            currentSlide = 0;
            updateSlide();
            setTimeout(() => {
                track.style.transition = 'transform 0.5s ease-in-out';
            }, 50);
        }
    }, autoSlideInterval);
}

function createBackgroundParticles() {
    const hero = document.querySelector('.hero');
    if (!hero) return;

    const particlesContainer = document.createElement('div');
    particlesContainer.className = 'bg-particles';
    particlesContainer.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        z-index: 1;
    `;

    hero.appendChild(particlesContainer);

    // Créer 20 particules
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'bg-particle';
        particle.style.cssText = `
            position: absolute;
            width: ${Math.random() * 4 + 2}px;
            height: ${Math.random() * 4 + 2}px;
            background: rgba(255, 255, 255, ${Math.random() * 0.3 + 0.1});
            border-radius: 50%;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: particleFloat ${Math.random() * 10 + 10}s linear infinite;
            animation-delay: ${Math.random() * 10}s;
        `;

        particlesContainer.appendChild(particle);
    }
}

// Ajouter l'animation des particules
const particleAnimationCSS = `
@keyframes particleFloat {
    0% {
        transform: translateY(100vh) translateX(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) translateX(${Math.random() * 200 - 100}px);
        opacity: 0;
    }
}
`;

// Ajouter le CSS des particules
const style = document.createElement('style');
style.textContent = particleAnimationCSS;
document.head.appendChild(style);

// Initialiser les particules
document.addEventListener('DOMContentLoaded', createBackgroundParticles);
</script>

<?php include 'includes/footer.php'; ?>