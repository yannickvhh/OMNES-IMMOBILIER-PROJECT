<?php 
$page_title = "Accueil | OMNES IMMOBILIER";
require_once 'php/includes/header.php'; 
?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="hero-title">Bienvenue chez OMNES IMMOBILIER</h1>
            <p class="hero-subtitle">Votre partenaire de confiance pour tous vos besoins immobiliers.</p>
            <a href="tout-parcourir.php" class="btn btn-primary btn-lg">Découvrir nos biens</a>
        </div>
    </section>

    <!-- Événement de la semaine -->
    <section class="section event-section">
        <div class="container">
            <div class="section-title">
                <h2>L'Événement de la Semaine</h2>
            </div>
            <div class="event-card">
                <div class="row">
                    <div class="col-md-6 event-image-container">
                        <img src="assets/images/event_placeholder.jpg" alt="Événement OMNES IMMOBILIER" class="img-fluid rounded">
                        <!-- Replace with actual event image -->
                    </div>
                    <div class="col-md-6 event-details">
                        <h3>Portes Ouvertes : Découvrez nos Nouveautés</h3>
                        <p class="event-date"><i class="fas fa-calendar-alt"></i> Samedi prochain, 10h00 - 18h00</p>
                        <p>Venez découvrir en avant-première nos nouvelles propriétés exclusives et rencontrer nos agents. Une occasion unique de trouver le bien de vos rêves ou d'obtenir des conseils personnalisés pour votre projet immobilier.</p>
                        <p><i class="fas fa-map-marker-alt"></i> Agence principale - 37, quai de Grenelle, 75015 Paris</p>
                        <a href="#" class="btn btn-secondary">En savoir plus</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Carrousel des propriétés (Optional) -->
    <section class="section properties-carousel-section bg-light">
        <div class="container">
            <div class="section-title">
                <h2>Nos Biens à la Une</h2>
            </div>
            <div id="propertiesCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#propertiesCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#propertiesCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#propertiesCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="assets/images/property1_placeholder.jpg" class="d-block w-100" alt="Propriété 1">
                        <div class="carousel-caption d-none d-md-block bg-dark-transparent">
                            <h5>Appartement de Luxe - Paris Centre</h5>
                            <p>Vue imprenable, finitions haut de gamme.</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="assets/images/property2_placeholder.jpg" class="d-block w-100" alt="Propriété 2">
                        <div class="carousel-caption d-none d-md-block bg-dark-transparent">
                            <h5>Maison Familiale avec Jardin</h5>
                            <p>Idéale pour une famille, proche des commodités.</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="assets/images/property3_placeholder.jpg" class="d-block w-100" alt="Propriété 3">
                        <div class="carousel-caption d-none d-md-block bg-dark-transparent">
                            <h5>Terrain Constructible Vue Mer</h5>
                            <p>Opportunité rare, potentiel exceptionnel.</p>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#propertiesCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#propertiesCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Introduction OMNES IMMOBILIER -->
    <section class="section about-us-short">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>Qui sommes-nous ?</h2>
                    <p>OMNES IMMOBILIER est une agence dédiée à la communauté Omnes Éducation et au grand public, offrant une gamme complète de services pour l'achat, la vente, la location et la gestion de biens immobiliers. Notre mission est de vous accompagner à chaque étape de votre projet avec expertise et professionnalisme.</p>
                    <p>Nous mettons à votre disposition une équipe d'agents qualifiés, une plateforme en ligne intuitive pour explorer nos offres, prendre des rendez-vous et communiquer facilement.</p>
                </div>
                <div class="col-md-6 text-center">
                    <img src="assets/images/team_placeholder.jpg" alt="Équipe Omnes Immobilier" class="img-fluid rounded shadow">
                    <!-- Replace with actual team or agency image -->
                </div>
            </div>
        </div>
    </section>

<?php require_once 'php/includes/footer.php'; ?> 