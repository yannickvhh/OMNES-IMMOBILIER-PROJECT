<?php 
$page_title = "Tout Parcourir | OMNES IMMOBILIER";
require_once 'php/includes/header.php'; 
// Ensure db.php now provides a PDO connection object, typically named $pdo
require_once 'php/config/db.php'; 

// Fetch property categories dynamically (example, can be hardcoded if static)
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

// Property types mapping for display consistency
$property_types_for_display = [
    'residentiel' => 'Immobilier Résidentiel',
    'commercial' => 'Immobilier Commercial',
    'terrain' => 'Terrain',
    'location' => 'Appartement à Louer',
    'enchere' => 'Vente par Enchère'
];

// Fetch a few featured properties from the database
$featured_properties = [];
$diagnostic_message = ""; // For diagnostic messages

if (!isset($pdo)) {
    $diagnostic_message = "<div class='alert alert-danger'>Erreur: La connexion à la base de données (objet \$pdo) n'est pas initialisée. Vérifiez votre fichier <code>php/config/db.php</code> et son inclusion.</div>";
} else {
    try {
        $sql_featured = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_nom 
                         FROM Proprietes p
                         LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur
                         LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id
                         WHERE p.statut = 'disponible'
                         ORDER BY p.date_ajout DESC
                         LIMIT 3"; // Get 3 latest available properties
        
        $stmt_featured = $pdo->query($sql_featured); // Simple query, no parameters
        $featured_properties = $stmt_featured->fetchAll(PDO::FETCH_ASSOC);

        // if (empty($featured_properties)) {
        //     $diagnostic_message = "<div class='alert alert-info'>Aucun bien sélectionné (statut 'disponible') n'a été trouvé pour le moment.</div>";
        // }
        // No specific message if results are found, the page will just display them.

    } catch (PDOException $e) {
        $diagnostic_message = "<div class='alert alert-danger'>Erreur lors de la récupération des biens sélectionnés: " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("PDO Error fetching featured properties: " . $e->getMessage() . " SQL: " . $sql_featured);
    }
}

?>

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
                        <img src="<?php echo htmlspecialchars($category['image']); ?>" class="card-img-top category-card-img" alt="<?php echo htmlspecialchars($category['name']); ?>">
                        <div class="card-body d-flex flex-column">
                            <div class="icon-wrapper">
                                <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($category['description']); ?></p>
                            <a href="<?php echo htmlspecialchars($category['link']); ?>" class="btn mt-auto">Voir les biens</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Properties Section -->
    <section class="section featured-properties-section py-5">
        <div class="container">
            <div class="section-title text-center">
                <h2>Quelques Biens Sélectionnés</h2>
                <p>Découvrez une sélection de nos meilleures offres actuelles.</p>
            </div>

            <?php if (!empty($diagnostic_message)): ?>
                <div class="row">
                    <div class="col-12">
                        <?php echo $diagnostic_message; // Display connection or SQL errors here ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row properties-grid">
                <?php if (!empty($featured_properties)): ?>
                    <?php foreach ($featured_properties as $prop): ?>
                    <div class="col-md-6 col-lg-4 mb-4 d-flex align-items-stretch">
                        <div class="card property-card h-100 shadow-sm">
                            <img src="assets/properties/<?php echo htmlspecialchars(!empty($prop['photo_principale_filename']) ? $prop['photo_principale_filename'] : 'default_property.jpg'); ?>" class="card-img-top property-card-img-featured" alt="<?php echo htmlspecialchars($prop['titre'] ?? 'Propriété'); ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($prop['titre'] ?? 'Titre non disponible'); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(($prop['ville'] ?? 'Ville inconnue') . (!empty($prop['code_postal']) ? ', ' . $prop['code_postal'] : '')); ?></h6>
                                <p class="card-text type-tag">Type: <?php echo htmlspecialchars($property_types_for_display[$prop['type_propriete']] ?? $prop['type_propriete'] ?? 'Type inconnu'); ?></p>
                                <p class="card-text flex-grow-1 description-truncate"><?php echo nl2br(htmlspecialchars(substr($prop['description'] ?? '', 0, 100))); ?>...</p>
                                <p class="card-text price-tag fs-5 text-primary fw-bold">
                                    <?php echo (isset($prop['type_propriete']) && $prop['type_propriete'] == 'location' && isset($prop['prix'])) ? number_format($prop['prix'], 2, ',', ' ') . ' € / mois' : (isset($prop['prix']) ? number_format($prop['prix'], 2, ',', ' ') . ' €' : 'Prix non spécifié'); ?>
                                </p>
                                <?php if(!empty($prop['agent_nom'])): ?>
                                    <p class="card-text text-muted small">Agent: <?php echo htmlspecialchars($prop['agent_nom']); ?></p>
                                <?php endif; ?>
                                <a href="propriete_details.php?id=<?php echo $prop['id'] ?? '#'; ?>" class="btn btn-primary mt-auto">Plus de détails</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php elseif (empty($diagnostic_message)): // Only show this specific message if no other diagnostic (like a connection error) was set ?>
                    <div class="col-12">
                        <p class="text-center text-muted">Aucun bien (avec statut 'disponible') à afficher dans cette section pour le moment. Veuillez vérifier les ajouts récents dans la base de données ou le statut des propriétés.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="recherche.php" class="btn btn-outline-secondary btn-lg">Voir tous les biens</a>
            </div>
        </div>
    </section>

<?php require_once 'php/includes/footer.php'; ?> 