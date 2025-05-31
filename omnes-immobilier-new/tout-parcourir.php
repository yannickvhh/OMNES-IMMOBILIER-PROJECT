<?php 
$page_title = "Tout Parcourir | OMNES IMMOBILIER";
// require_once 'php/includes/header.php'; // Assuming header is included in the HTML below or handled differently
// require_once 'php/config/db.php'; // Assuming DB connection is not needed for this specific static category list

// Define categories array as provided by the user
$categories = [
    [
        'name' => 'Immobilier Résidentiel',
        'description' => 'Maisons unifamiliales, condos, duplex, etc.',
        'icon' => 'fas fa-home',
        'link' => 'recherche.php?type=residentiel',
        'image' => 'assets/images/residential_placeholder.jpg'
    ],
    [
        'name' => 'Immobilier Commercial',
        'description' => 'Bureaux, magasins, hôtels, etc.',
        'icon' => 'fas fa-building',
        'link' => 'recherche.php?type=commercial',
        'image' => 'assets/images/commercial_placeholder.jpg'
    ],
    [
        'name' => 'Terrains',
        'description' => 'Terrains non développés, terres agricoles, etc.',
        'icon' => 'fas fa-tree',
        'link' => 'recherche.php?type=terrain',
        'image' => 'assets/images/terrain_placeholder.jpg'
    ],
    [
        'name' => 'Appartements à Louer',
        'description' => 'Propriétés en location pour une durée limitée.',
        'icon' => 'fas fa-city',
        'link' => 'recherche.php?type=location',
        'image' => 'assets/images/apartment_placeholder.jpg'
    ],
    [
        'name' => 'Vente par Enchère',
        'description' => 'Biens immobiliers vendus au plus offrant.',
        'icon' => 'fas fa-gavel',
        'link' => 'recherche.php?type=enchere',
        'image' => 'assets/images/auction_placeholder.jpg'
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-content">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Logo OMNES IMMOBILIER">
                <h1>OMNES IMMOBILIER</h1>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container navbar-container">
            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="index.html" class="nav-link">Accueil</a></li>
                <li class="nav-item"><a href="tout-parcourir.php" class="nav-link active">Tout Parcourir</a></li>
                <li class="nav-item"><a href="recherche.php" class="nav-link">Recherche</a></li>
                <li class="nav-item"><a href="rendez-vous.html" class="nav-link">Rendez-vous</a></li>
                <li class="nav-item"><a href="votre-compte.html" class="nav-link">Votre Compte</a></li>
            </ul>
        </div>
    </nav>

    <!-- Page Title -->
    <section class="section py-5">
        <div class="container">
            <div class="section-title text-center">
                <h2>Tout Parcourir</h2>
                <p>Explorez toutes nos catégories de biens immobiliers.</p>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="section categories-section py-5 bg-light">
        <div class="container">
            <div class="row">
                <?php foreach ($categories as $category): ?>
                <div class="col-md-6 col-lg-4 mb-4 d-flex align-items-stretch">
                    <div class="card category-card h-100 shadow-sm text-center">
                        <img src="<?php echo htmlspecialchars($category['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($category['name']); ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <i class="<?php echo htmlspecialchars($category['icon']); ?> fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($category['description']); ?></p>
                            <a href="<?php echo htmlspecialchars($category['link']); ?>" class="btn btn-outline-primary mt-auto">Voir les biens</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Filtres -->
    <section class="section" style="padding-top: 0; padding-bottom: var(--spacing-lg);">
        <div class="container">
            <div style="background-color: white; padding: var(--spacing-lg); border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm);">
                <h3 class="mb-3">Filtrer les résultats</h3>
                <form class="row">
                    <div class="form-group" style="flex: 1; margin-right: var(--spacing-md);">
                        <label class="form-label">Type de bien</label>
                        <select class="form-control">
                            <option value="">Tous les types</option>
                            <option value="appartement">Appartement</option>
                            <option value="maison">Maison</option>
                            <option value="villa">Villa</option>
                            <option value="terrain">Terrain</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-right: var(--spacing-md);">
                        <label class="form-label">Budget max</label>
                        <select class="form-control">
                            <option value="">Sans limite</option>
                            <option value="100000">100 000 €</option>
                            <option value="200000">200 000 €</option>
                            <option value="300000">300 000 €</option>
                            <option value="500000">500 000 €</option>
                            <option value="1000000">1 000 000 €</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-right: var(--spacing-md);">
                        <label class="form-label">Surface min</label>
                        <select class="form-control">
                            <option value="">Toutes surfaces</option>
                            <option value="20">20 m²</option>
                            <option value="50">50 m²</option>
                            <option value="100">100 m²</option>
                            <option value="200">200 m²</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Chambres</label>
                        <select class="form-control">
                            <option value="">Indifférent</option>
                            <option value="1">1+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                        </select>
                    </div>
                    <div style="width: 100%; margin-top: var(--spacing-md); text-align: right;">
                        <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Liste des propriétés (Placeholder Content) -->
    <section class="section" style="background-color: #f8f9fa;">
        <div class="container">
            <div class="section-title text-center">
                 <h2>Quelques Biens Sélectionnés</h2>
                 <p>Découvrez une sélection de nos meilleures offres actuelles.</p>
            </div>
            <div class="properties-grid">
                <!-- Propriété en Vedette 1 -->
                <div class="property-card">
                    <img src="assets/images/propriete_vedette1.jpg" alt="Propriété en Vedette 1" class="property-image">
                    <div class="property-content">
                        <h3 class="property-title">Villa Spacieuse avec Piscine</h3>
                        <p class="property-location">Nice, Côte d'Azur</p>
                        <p class="property-description">Magnifique villa offrant 5 chambres, un grand jardin paysager et une piscine privée. Vue mer panoramique.</p>
                        <p class="property-price">1 250 000 €</p>
                        <div class="property-footer">
                            <a href="#" class="btn btn-secondary">Plus de détails</a>
                        </div>
                    </div>
                </div>
                <!-- Propriété en Vedette 2 -->
                <div class="property-card">
                    <img src="assets/images/propriete_vedette2.jpg" alt="Propriété en Vedette 2" class="property-image">
                    <div class="property-content">
                        <h3 class="property-title">Appartement Moderne en Centre-Ville</h3>
                        <p class="property-location">Lyon, Rhône</p>
                        <p class="property-description">Superbe appartement T3 refait à neuf, lumineux et fonctionnel, avec balcon. Proche de toutes commodités.</p>
                        <p class="property-price">380 000 €</p>
                        <div class="property-footer">
                            <a href="#" class="btn btn-secondary">Plus de détails</a>
                        </div>
                    </div>
                </div>
                <!-- Propriété en Vedette 3 -->
                <div class="property-card">
                    <img src="assets/images/propriete_vedette3.jpg" alt="Propriété en Vedette 3" class="property-image">
                    <div class="property-content">
                        <h3 class="property-title">Terrain à Bâtir Viabilisé</h3>
                        <p class="property-location">Bordeaux, Gironde</p>
                        <p class="property-description">Belle parcelle de 800m² dans un quartier calme et recherché, prête à accueillir votre projet de construction.</p>
                        <p class="property-price">150 000 €</p>
                        <div class="property-footer">
                            <a href="#" class="btn btn-secondary">Plus de détails</a>
                        </div>
                    </div>
                </div>
            </div>
             <div class="text-center mt-4">
                <a href="recherche.php" class="btn btn-primary btn-lg">Voir tous les biens</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h6>Notre Éthique</h6>
                    <p>Tous nos collaborateurs sont disponibles sur les créneaux libres affichés dans leur planning. Prenez rendez-vous quand cela vous convient !</p>
                </div>
                <div class="footer-column">
                    <h6>Liens Rapides</h6>
                    <ul class="footer-links">
                        <li><a href="index.html">Accueil</a></li>
                        <li><a href="tout-parcourir.php">Tout Parcourir</a></li>
                        <li><a href="recherche.php">Recherche</a></li>
                        <li><a href="rendez-vous.html">Rendez-vous</a></li>
                        <li><a href="votre-compte.html">Votre Compte</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h6>Contact</h6>
                    <p>37, quai de Grenelle, 75015 Paris, France<br>
                    info@omnesimmobilier.ece.fr<br>
                    +33 1 02 03 04 05<br>
                    +33 1 03 02 05 04</p>
                </div>
            </div>
            
            <div class="map-container">
                <iframe src="https://www.google.com/maps?q=37+quai+de+Grenelle,+75015+Paris&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 OMNES IMMOBILIER | Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html> 