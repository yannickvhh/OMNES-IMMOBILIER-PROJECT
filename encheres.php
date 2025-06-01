<?php
$path_prefix = ''; // Path prefix, standard
$page_title = "Propriétés aux Enchères | OMNES IMMOBILIER";
require_once 'php/config/db.php'; // Requis: DB
require_once 'php/includes/header.php'; // Requis: Header

$encheres_actives = []; // Init array enchères
$error_message_encheres = ''; // Init msg erreur

if (!isset($pdo)) { // PDO doit être là
    $error_message_encheres = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
} else {
    // SQL pour récupérer les enchères actives
    $sql_encheres = "SELECT 
        p.id as propriete_id,
        p.titre as propriete_titre,
        p.type_propriete,
        p.adresse as propriete_adresse,
        p.ville as propriete_ville,
        p.photo_principale_filename,
        e.id as enchere_id,
        e.date_heure_debut,
        e.date_heure_fin,
        e.prix_depart,
        COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel
    FROM Encheres e
    JOIN Proprietes p ON e.id_propriete = p.id
    LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
    WHERE NOW() BETWEEN e.date_heure_debut AND e.date_heure_fin AND p.statut = 'enchere_active' // Filtre: en cours et statut propriété ok
    GROUP BY p.id, e.id // Group pour MAX() sur offres
    ORDER BY e.date_heure_fin ASC"; // Tri par date de fin

    try {
        $stmt_encheres = $pdo->query($sql_encheres); // Exec SQL (pas d'input user ici)
        $encheres_actives = $stmt_encheres->fetchAll(PDO::FETCH_ASSOC); // Récup résultats
    } catch (PDOException $e) {
        $error_message_encheres = "Erreur lors de la récupération des enchères: " . htmlspecialchars($e->getMessage()); // Erreur DB
        error_log("PDO Error fetching active auctions: " . $e->getMessage()); // Log technique
    }
}

?>

<div class="container mt-5 mb-5"> <!-- Conteneur principal -->
    <div class="section-title text-center mb-5">
        <h2><i class="fas fa-gavel me-2"></i>Propriétés aux Enchères</h2>
        <p>Découvrez nos biens immobiliers actuellement disponibles aux enchères. Placez votre offre !</p> <!-- Titre et sous-titre -->
    </div>

    <?php if (!empty($error_message_encheres)): // Si erreur, affichage ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message_encheres); ?></div>
    <?php endif; ?>

    <?php if (empty($encheres_actives) && empty($error_message_encheres)): // Pas d'enchères et pas d'erreur -> message info ?>
        <div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>Aucune propriété n'est actuellement disponible aux enchères. Revenez bientôt !</div>
    <?php elseif (!empty($encheres_actives)): // Si des enchères existent, boucle affichage ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 properties-grid"> <!-- Grille Bootstrap -->
            <?php foreach ($encheres_actives as $enchere): ?>
                <?php 
                    $photo_url = $path_prefix . "assets/properties/" . htmlspecialchars($enchere['photo_principale_filename'] ?? 'default_property.jpg'); // URL photo + fallback
                    // Calcul temps restant (approx.)
                    $temps_restant_secondes = strtotime($enchere['date_heure_fin']) - time();
                    $jours_restants = floor($temps_restant_secondes / (60 * 60 * 24));
                    $heures_restantes = floor(($temps_restant_secondes % (60 * 60 * 24)) / (60 * 60));
                    $minutes_restantes = floor(($temps_restant_secondes % (60 * 60)) / 60);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm property-card"> <!-- Card pour chaque bien -->
                        <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>" class="text-decoration-none text-dark">
                            <img src="<?php echo $photo_url; ?>" class="card-img-top property-card-img-featured" alt="<?php echo htmlspecialchars($enchere['propriete_titre']); ?>">
                        </a>
                        <div class="card-body d-flex flex-column"> <!-- d-flex pour bouton en bas -->
                            <h5 class="card-title"><a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($enchere['propriete_titre']); ?></a></h5>
                            <p class="card-text text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($enchere['propriete_adresse'] . ", " . $enchere['propriete_ville']); ?></p>
                            <hr>
                            <div class="mb-2"> <!-- Section prix -->
                                <span class="d-block">Prix de départ : <strong><?php echo number_format($enchere['prix_depart'], 0, ',', ' '); ?> €</strong></span>
                                <span class="d-block">Prix actuel : <strong class="text-success fs-5"><?php echo number_format($enchere['prix_actuel'], 0, ',', ' '); ?> €</strong></span>
                            </div>
                            <div class="text-danger small fw-bold mb-3"><i class="fas fa-clock me-1"></i> <!-- Affichage temps restant -->
                                <?php if ($temps_restant_secondes > 0): ?>
                                    Se termine dans: 
                                    <?php if ($jours_restants > 0) echo $jours_restants . "j "; ?>
                                    <?php if ($heures_restantes > 0 || $jours_restants > 0) echo $heures_restantes . "h "; ?>
                                    <?php echo $minutes_restantes . "min"; ?>
                                <?php else: ?>
                                    Enchère terminée <!-- Message si finie -->
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $enchere['propriete_id']; ?>#enchere" class="btn btn-primary mt-auto"><i class="fas fa-gavel me-2"></i>Voir l'enchère</a> <!-- Lien détails enchère -->
                        </div>
                    </div>
                </div>
            <?php endforeach; // Fin boucle sur $encheres_actives ?>
        </div>
    <?php endif; // Fin condition affichage des enchères ?>
</div>

<?php require_once 'php/includes/footer.php'; // Footer page ?> 
