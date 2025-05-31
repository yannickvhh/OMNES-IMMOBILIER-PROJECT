// OMNES IMMOBILIER - Script principal

document.addEventListener('DOMContentLoaded', function() {
    // Navigation mobile
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
    
    // Fermer le menu mobile quand on clique sur un lien
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            navMenu.classList.remove('active');
        });
    });
    
    // Carousel
    const carousel = document.querySelector('.carousel');
    if (carousel) {
        const carouselInner = carousel.querySelector('.carousel-inner');
        const carouselItems = carousel.querySelectorAll('.carousel-item');
        const prevBtn = carousel.querySelector('.carousel-control-prev');
        const nextBtn = carousel.querySelector('.carousel-control-next');
        
        let currentIndex = 0;
        const itemCount = carouselItems.length;
        
        // Fonction pour afficher un slide spécifique
        function showSlide(index) {
            if (index < 0) {
                currentIndex = itemCount - 1;
            } else if (index >= itemCount) {
                currentIndex = 0;
            } else {
                currentIndex = index;
            }
            
            carouselInner.style.transform = `translateX(-${currentIndex * 100}%)`;
        }
        
        // Événements pour les boutons de navigation
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                showSlide(currentIndex - 1);
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                showSlide(currentIndex + 1);
            });
        }
        
        // Défilement automatique
        setInterval(function() {
            showSlide(currentIndex + 1);
        }, 5000);
    }
    
    // Animation au défilement
    const animateOnScroll = function() {
        const elements = document.querySelectorAll('.section');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('fade-in');
            }
        });
    };
    
    // Exécuter l'animation au chargement et au défilement
    animateOnScroll();
    window.addEventListener('scroll', animateOnScroll);
});
