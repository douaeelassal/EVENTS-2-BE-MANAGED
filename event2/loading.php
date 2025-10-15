<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENT2 - Loading Screen</title>

    
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Main Layout */
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #02273f 0%, #0a1628 100%);
            overflow: hidden;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading-container {
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .site-title {
            font-size: 5rem;
            font-weight: bold;
            color: #ffffff;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.8), 0 0 60px rgba(0, 136, 255, 0.6);
            margin-bottom: 1rem;
            opacity: 0;
            animation: titlePulse 2s ease-out forwards;
            letter-spacing: 8px;
        }

        .subtitle {
            color: #0088ff;
            font-size: 1.3rem;
            margin-bottom: 3rem;
            opacity: 0;
            animation: fadeIn 1s ease-out 0.5s forwards;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .calendar-animation {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
            opacity: 0;
            animation: fadeIn 1s ease-out 1s forwards;
        }

        .calendar-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 180px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            border-top-color: #cb1010;
            border-right-color: #0088ff;
            animation: rotate 2s linear infinite;
        }

        .calendar-ring-2 {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            height: 140px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-bottom-color: #d47f79;
            border-left-color: #0088ff;
            animation: rotate 3s linear infinite reverse;
        }

        .event-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #cb1010, #d47f79);
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(203, 16, 16, 0.6);
            animation: pulse 2s ease-in-out infinite;
        }

        .event-icon::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 8px;
            background: #ffffff;
            border-radius: 4px;
        }

        .event-icon::after {
            content: '';
            position: absolute;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 6px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 3px;
        }

        .date-number {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2rem;
            font-weight: bold;
            color: #ffffff;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #0088ff;
            border-radius: 50%;
            opacity: 0;
            animation: particleFloat 4s ease-in-out infinite;
        }

        .particle:nth-child(2n) {
            background: #cb1010;
            animation-delay: 0.5s;
        }

        .particle:nth-child(3n) {
            background: #ffffff;
            animation-delay: 1s;
        }

        .loading-text {
            color: #ffffff;
            font-size: 1.1rem;
            opacity: 0;
            margin-top: 2rem;
            letter-spacing: 3px;
            animation: fadeIn 1s ease-out 1.5s forwards;
        }

        .dots {
            display: inline-block;
            width: 60px;
            text-align: left;
        }

        .dots::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }

        .stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .star {
            position: absolute;
            background: #ffffff;
            border-radius: 50%;
            animation: twinkle 3s ease-in-out infinite;
        }

        /* Animation Keyframes */
        @keyframes titlePulse {
            0% {
                opacity: 0;
                transform: scale(0.5);
                filter: blur(10px);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                opacity: 1;
                transform: scale(1);
                filter: blur(0);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        @keyframes rotate {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                box-shadow: 0 0 40px rgba(203, 16, 16, 0.6);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
                box-shadow: 0 0 60px rgba(203, 16, 16, 0.9);
            }
        }

        @keyframes particleFloat {
            0% {
                opacity: 0;
                transform: translateY(100vh) scale(0);
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: translateY(-100vh) scale(1);
            }
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div class="stars" id="stars"></div>
    <div class="particles" id="particles"></div>

    <div class="loading-container">
        <h1 class="site-title">EVENT 2</h1>
        <p class="subtitle">Gestion d'Événements</p>
        
        <div class="calendar-animation">
            <div class="calendar-ring"></div>
            <div class="calendar-ring-2"></div>
            <div class="event-icon">
                <span class="date-number">02</span>
            </div>
        </div>
        
        <p class="loading-text">Chargement<span class="dots"></span></p>
    </div>


    <script>

        function createStars() {
            const starsContainer = document.getElementById('stars');
            const numberOfStars = 150;

            for (let i = 0; i < numberOfStars; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                
                const size = Math.random() * 2 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.animationDelay = Math.random() * 3 + 's';
                star.style.animationDuration = (Math.random() * 2 + 2) + 's';
                
                starsContainer.appendChild(star);
            }
        }

        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const numberOfParticles = 30;

            for (let i = 0; i < numberOfParticles; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 4 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
                
                particlesContainer.appendChild(particle);
            }
        }

        /**
         * Redirect to main site after loading animation
         * Automatically forwards users to home page
         */
        function redirectToHome() {
            setTimeout(() => {
                window.location.href = 'home.php';
            }, 1000);
        }

        /**
         * Initialize loading page
         * Sets up all visual effects and auto-redirect
         */
        window.addEventListener('load', () => {
            createStars();
            createParticles();
            redirectToHome();
        });
    </script>
</body>
</html>