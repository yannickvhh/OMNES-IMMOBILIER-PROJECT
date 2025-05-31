<?php
$path_prefix = ''; // Ce fichier est à la racine
$page_title = "Propriétés aux Enchères | OMNES IMMOBILIER";
require_once 'php/config/db.php'; // MUST BE BEFORE HEADER - provides $pdo
require_once 'php/includes/header.php';

$encheres_actives = [];
$error_message_encheres = '';

if (!isset($pdo)) {
    $error_message_encheres = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
} else {
    // Récupérer les enchères actives et les informations associées
    // Une enchère est active si NOW() est entre date_heure_debut et date_heure_fin
    $sql_encheres = "SELECT 
        p.id as propriete_id,
        p.titre as propriete_titre,
        p.type_propriete,
        p.adresse as propriete_adresse,
        p.ville as propriete_ville,
        p.photo_principale_filename, -- Corrected
        e.id as enchere_id,
        e.date_heure_debut, -- Corrected
        e.date_heure_fin,   -- Corrected
        e.prix_depart,
        COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel
    FROM Encheres e
    JOIN Proprietes p ON e.id_propriete = p.id
    LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
    WHERE NOW() BETWEEN e.date_heure_debut AND e.date_heure_fin AND p.statut = 'enchere_active' -- Corrected status
    GROUP BY p.id, e.id -- Grouping by e.id is primary since it's the auction itself. p.id is for property details.
    ORDER BY e.date_heure_fin ASC";

    try {
        $stmt_encheres = $pdo->query($sql_encheres); // Query directly as no user input
        $encheres_actives = $stmt_encheres->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message_encheres = "Erreur lors de la récupération des enchères: " . htmlspecialchars($e->getMessage());
        error_log("PDO Error fetching active auctions: " . $e->getMessage());
    }
}

?>

<div class="container mt-5 mb-5">
    <div class="section-title text-center mb-5">
        <h2><i class="fas fa-gavel me-2"></i>Propriétés aux Enchères</h2>
        <p>Découvrez nos biens immobiliers actuellement disponibles aux enchères. Placez votre offre !</p>
    </div>

    <?php if (!empty($error_message_encheres)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message_encheres); ?></div>
    <?php endif; ?>

    <?php if (empty($encheres_actives) && empty($error_message_encheres)): ?>
        <div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>Aucune propriété n'est actuellement disponible aux enchères. Revenez bientôt !</div>
    <?php elseif (!empty($encheres_actives)): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 properties-grid"> {/* Added properties-grid for consistent styling */}
            <?php foreach ($encheres_actives as $enchere): ?>
                <?php 
                    // Use photo_principale_filename from the query result
                    $photo_url = $path_prefix . "assets/properties/" . htmlspecialchars($enchere['photo_principale_filename'] ?? 'default_property.jpg');
                    $temps_restant_secondes = strtotime($enchere['date_heure_fin']) - time();
                    $jours_restants = floor($temps_restant_secondes / (60 * 60 * 24));
                    $heures_restantes = floor(($temps_restant_secondes % (60 * 60 * 24)) / (60 * 60));
                    $minutes_restantes = floor(($temps_restant_secondes % (60 * 60)) / 60);
                ?>
                <div class="col">
                    {/* Applied .property-card directly for styling from style.css */}
                    <div class="card h-100 shadow-sm property-card"> 
                        <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>" class="text-decoration-none text-dark">
                            {/* Ensured property-card-img-featured class for consistent image sizing if defined, or adjust as needed */}
                            <img src="<?php echo $photo_url; ?>" class="card-img-top property-card-img-featured" alt="<?php echo htmlspecialchars($enchere['propriete_titre']); ?>">
                        </a>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($enchere['propriete_titre']); ?></a></h5>
                            <p class="card-text text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($enchere['propriete_adresse'] . ", " . $enchere['propriete_ville']); ?></p>
                            <hr>
                            <div class="mb-2">
                                <span class="d-block">Prix de départ : <strong><?php echo number_format($enchere['prix_depart'], 0, ',', ' '); ?> €</strong></span>
                                <span class="d-block">Prix actuel : <strong class="text-success fs-5"><?php echo number_format($enchere['prix_actuel'], 0, ',', ' '); ?> €</strong></span>
                            </div>
                            <div class="text-danger small fw-bold mb-3"><i class="fas fa-clock me-1"></i>
                                <?php if ($temps_restant_secondes > 0): ?>
                                    Se termine dans: 
                                    <?php if ($jours_restants > 0) echo $jours_restants . "j "; ?>
                                    <?php if ($heures_restantes > 0 || $jours_restants > 0) echo $heures_restantes . "h "; ?>
                                    <?php echo $minutes_restantes . "min"; ?>
                                <?php else: ?>
                                    Enchère terminée
                                <?php endif; ?>
                            </div>
                            {/* Corrected button classes: ensure btn-primary is styled as desired or use a more specific class like btn-auction */}
                            <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>#enchere" class="btn btn-primary mt-auto"><i class="fas fa-gavel me-2"></i>Voir l'enchère</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'php/includes/footer.php'; ?> 