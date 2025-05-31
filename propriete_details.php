<?php
$path_prefix = ''; // Ce fichier est à la racine
$page_title = "Détails de la Propriété";
require_once 'php/config/db.php'; // MUST BE BEFORE HEADER - provides $pdo
require_once 'php/includes/header.php';

$propriete_id = null;
$propriete = null;
$agent_responsable = null;
$enchere_details = null;
$offres_enchere = [];
$prix_actuel_enchere = null;
$enchere_active = false;
$gagnant_enchere = null; // Variable to store winner details

$error_message_prop = '';
$success_message_prop = ''; // Pour les messages d'enchères réussies etc.

if(isset($_SESSION['success_message_prop'])) {
    $success_message_prop = $_SESSION['success_message_prop'];
    unset($_SESSION['success_message_prop']);
}
if(isset($_SESSION['error_message_prop'])) {
    $error_message_prop = $_SESSION['error_message_prop'];
    unset($_SESSION['error_message_prop']);
}

if (!$pdo) {
    $error_message_prop = "Erreur critique: La connexion à la base de données n'a pas pu être établie. Vérifiez la configuration.";
} else {
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT) && $_GET['id'] > 0) {
        $propriete_id = $_GET['id'];

        try {
            // 1. Récupérer les détails de la propriété et de l'agent
            $sql_propriete = "SELECT p.*, 
                                   u.nom as agent_nom, u.prenom as agent_prenom, u.email as agent_email, 
                                   ai.telephone_pro as agent_tel_pro, ai.photo_filename as agent_photo, ai.specialite as agent_specialite, ai.bureau as agent_bureau
                            FROM Proprietes p 
                            LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur 
                            LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id 
                            WHERE p.id = :propriete_id AND (p.statut = 'disponible' OR p.statut = 'enchere_active')"; // Statut disponible ou enchere_active
            
            $stmt_prop = $pdo->prepare($sql_propriete);
            $stmt_prop->execute([':propriete_id' => $propriete_id]);
            $propriete = $stmt_prop->fetch(PDO::FETCH_ASSOC);

            if ($propriete) {
                $page_title = htmlspecialchars($propriete['titre']) . " | OMNES IMMOBILIER";
                if ($propriete['id_agent_responsable']) {
                    $agent_responsable = [
                        'id' => $propriete['id_agent_responsable'],
                        'nom' => $propriete['agent_nom'],
                        'prenom' => $propriete['agent_prenom'],
                        'email' => $propriete['agent_email'],
                        'telephone_pro' => $propriete['agent_tel_pro'],
                        'photo_filename' => $propriete['agent_photo'],
                        'specialite' => $propriete['agent_specialite'],
                        'bureau' => $propriete['agent_bureau']
                    ];
                }

                // 2. Vérifier si c'est une enchère et récupérer ses détails
                $sql_enchere_check = "SELECT e.id as enchere_id, e.date_heure_debut, e.date_heure_fin, e.prix_depart, 
                                        COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel 
                                    FROM Encheres e 
                                    LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
                                    WHERE e.id_propriete = :propriete_id 
                                    GROUP BY e.id";
                $stmt_ench_check = $pdo->prepare($sql_enchere_check);
                $stmt_ench_check->execute([':propriete_id' => $propriete_id]);
                $enchere_details = $stmt_ench_check->fetch(PDO::FETCH_ASSOC);

                if ($enchere_details) {
                    $prix_actuel_enchere = $enchere_details['prix_actuel'];
                    // Vérifier si l'enchère est active
                    $now = new DateTime();
                    $date_debut_enchere = new DateTime($enchere_details['date_heure_debut']);
                    $date_fin_enchere = new DateTime($enchere_details['date_heure_fin']);
                    if ($now >= $date_debut_enchere && $now <= $date_fin_enchere) {
                        $enchere_active = true;
                    }

                    // 3. Récupérer l'historique des offres pour cette enchère
                    $sql_offres = "SELECT oe.montant_offre, oe.date_heure_offre, 
                                       CONCAT(SUBSTRING(u.prenom, 1, 1), '***', SUBSTRING(u.nom, 1, 1), '***') as nom_offrant_masque
                                FROM OffresEncheres oe
                                JOIN Utilisateurs u ON oe.id_client = u.id
                                WHERE oe.id_enchere = :enchere_id 
                                ORDER BY oe.montant_offre DESC, oe.date_heure_offre DESC";
                    $stmt_offres = $pdo->prepare($sql_offres);
                    $stmt_offres->execute([':enchere_id' => $enchere_details['enchere_id']]);
                    $offres_enchere = $stmt_offres->fetchAll(PDO::FETCH_ASSOC);

                    // Determine winner if auction is finished and has bids
                    if (!$enchere_active && $now > $date_fin_enchere && !empty($offres_enchere)) {
                        $gagnant_enchere = $offres_enchere[0]; // Highest bid is the first one due to ORDER BY
                        
                        // If auction just ended with a winner, and property status is still 'enchere_active',
                        // update property status to 'enchere_terminee_gagnee'
                        if ($propriete['statut'] === 'enchere_active') {
                            try {
                                $sql_update_statut = "UPDATE Proprietes SET statut = 'enchere_terminee_gagnee' WHERE id = :id_propriete";
                                $stmt_update = $pdo->prepare($sql_update_statut);
                                $stmt_update->execute([':id_propriete' => $propriete_id]);
                                $propriete['statut'] = 'enchere_terminee_gagnee'; // Update local variable for immediate display consistency if needed
                                // Optionally, set a success message: 
                                // $_SESSION['success_message_prop'] = "L'enchère est terminée et le statut de la propriété a été mis à jour."; 
                                // However, this might be confusing if the user just landed on the page.
                                // A log message is good.
                                error_log("Property ID: $propriete_id status updated to enchere_terminee_gagnee after auction end.");
                            } catch (PDOException $e) {
                                $error_message_prop .= "<br>Erreur lors de la mise à jour du statut de la propriété après l'enchère: " . $e->getMessage();
                                error_log("PDO Error updating property status post-auction for ID $propriete_id: " . $e->getMessage());
                            }
                        }
                    }
                }
            } else {
                $error_message_prop = "Propriété non trouvée ou non disponible.";
            }
        } catch (PDOException $e) {
            $error_message_prop = "Erreur de base de données: " . htmlspecialchars($e->getMessage());
            error_log("PDO Error in propriete_details.php: " . $e->getMessage());
        }
    } else {
        $error_message_prop = "ID de propriété invalide ou manquant.";
    }
}

$main_photo_path = $path_prefix . "assets/properties/" . htmlspecialchars($propriete['photo_principale'] ?? 'default_property.jpg');
$agent_photo_path = $path_prefix . "assets/agents/photos/" . htmlspecialchars($agent_responsable['photo_filename'] ?? 'default_agent.png');

?>

<div class="container mt-5 mb-5">
    <?php if (!empty($success_message_prop)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($success_message_prop); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endif; ?>
    <?php if (!empty($error_message_prop)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error_message_prop); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php elseif ($propriete): ?>
        <h1 class="mb-4 property-title"><?php echo htmlspecialchars($propriete['titre']); ?></h1>
        
        <!-- Section principale propriété & agent -->
        <div class="row mb-4">
            <div class="col-md-8">
                <img src="<?php echo $main_photo_path; ?>" class="img-fluid rounded shadow-sm w-100 property-main-img" alt="Photo principale de <?php echo htmlspecialchars($propriete['titre']); ?>">
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><?php echo number_format($propriete['prix'], 0, ',', ' '); ?> €</h4>
                        <p class="text-muted small">(Note: Le prix affiché ici est le prix de vente direct. Si une enchère est en cours, voir la section Enchères ci-dessous.)</p>
                        <hr>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($propriete['type_propriete'])); ?></p>
                        <p><strong>Adresse:</strong> <?php echo htmlspecialchars($propriete['adresse'] . ", " . $propriete['code_postal'] . " " . $propriete['ville']); ?></p>
                        <hr>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-ruler-combined me-2 text-muted"></i><strong>Surface:</strong> <?php echo htmlspecialchars($propriete['surface']); ?> m²</li>
                            <li><i class="fas fa-door-open me-2 text-muted"></i><strong>Pièces:</strong> <?php echo htmlspecialchars($propriete['nombre_pieces']); ?></li>
                            <li><i class="fas fa-bed me-2 text-muted"></i><strong>Chambres:</strong> <?php echo htmlspecialchars($propriete['nombre_chambres']); ?></li>
                            <?php if ($propriete['etage'] !== null): ?>
                                <li><i class="fas fa-building me-2 text-muted"></i><strong>Étage:</strong> <?php echo htmlspecialchars($propriete['etage']); ?></li>
                            <?php endif; ?>
                            <li><i class="fas fa-sign me-2 text-muted"></i><strong>Balcon:</strong> <?php echo $propriete['balcon'] ? 'Oui' : 'Non'; ?></li>
                            <li><i class="fas fa-car me-2 text-muted"></i><strong>Parking:</strong> <?php echo $propriete['parking'] ? 'Oui' : 'Non'; ?></li>
                        </ul>
                        <hr>
                        <?php if (!$enchere_details && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client' && $agent_responsable): ?>
                            <a href="<?php echo $path_prefix; ?>rendez-vous.php?action=take_rdv&agent_id=<?php echo $agent_responsable['id']; ?>&property_id=<?php echo $propriete_id; ?>" class="btn btn-primary w-100 mb-2"><i class="fas fa-calendar-check me-2"></i>Prendre Rendez-vous</a>
                            <a href="<?php echo $path_prefix; ?>chat.php?contact_id=<?php echo $agent_responsable['id']; ?>&contact_name=<?php echo urlencode($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?>" class="btn btn-info w-100"><i class="fas fa-comments me-2"></i>Contacter l'agent</a>
                        <?php elseif (!$enchere_details && $agent_responsable): ?>
                             <p class="text-center"><a href="<?php echo $path_prefix; ?>votre-compte.php">Connectez-vous</a> ou <a href="<?php echo $path_prefix; ?>votre-compte.php#inscription">créez un compte client</a> pour prendre rendez-vous ou contacter l'agent.</p>
                        <?php elseif (!$agent_responsable && !$enchere_details): ?>
                             <p class="text-muted text-center"><i>Contactez-nous pour plus d'informations.</i></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><h5 class="mb-0">Description</h5></div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($propriete['description'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($propriete['url_video'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><h5 class="mb-0">Vidéo</h5></div>
                    <div class="card-body">
                        <div class="ratio ratio-16x9">
                            <iframe src="<?php echo htmlspecialchars(str_replace("watch?v=", "embed/", $propriete['url_video'])); ?>" title="Vidéo de la propriété" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION ENCHÈRES -->
        <?php if ($enchere_details): ?>
        <section id="enchere" class="mb-5 pt-3">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark"><h3 class="mb-0"><i class="fas fa-gavel me-2"></i>Détails de l'Enchère</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Informations sur l'enchère</h4>
                            <p><strong>Prix de départ :</strong> <?php echo number_format($enchere_details['prix_depart'], 0, ',', ' '); ?> €</p>
                            <p><strong>Prix actuel :</strong> <strong class="text-success fs-4"><?php echo number_format($prix_actuel_enchere, 0, ',', ' '); ?> €</strong></p>
                            <p><strong>Début de l'enchère :</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_debut']))); ?></p>
                            <p class="mb-3"><strong>Fin de l'enchère :</strong> <strong class="text-danger"><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_fin']))); ?></strong></p>
                            <?php 
                                $temps_restant_secondes = strtotime($enchere_details['date_heure_fin']) - time();
                                $jours_restants = floor($temps_restant_secondes / (60 * 60 * 24));
                                $heures_restantes = floor(($temps_restant_secondes % (60 * 60 * 24)) / (60 * 60));
                                $minutes_restantes = floor(($temps_restant_secondes % (60 * 60)) / 60);
                            ?>
                            <div class="alert <?php echo $enchere_active ? 'alert-info' : 'alert-secondary'; ?>">
                                <i class="fas fa-clock me-1"></i> 
                                <?php if ($enchere_active && $temps_restant_secondes > 0): ?>
                                    Temps restant: 
                                    <?php if ($jours_restants > 0) echo $jours_restants . "j "; ?>
                                    <?php if ($heures_restantes > 0 || $jours_restants > 0) echo $heures_restantes . "h "; ?>
                                    <?php echo $minutes_restantes . "min"; ?>
                                <?php elseif (!$enchere_active && $now < $date_debut_enchere): ?>
                                    L'enchère n'a pas encore commencé.
                                <?php elseif ($gagnant_enchere): ?>
                                    <strong class="text-success"><i class="fas fa-trophy me-1"></i>Enchère Terminée !</strong><br>
                                    Gagnant: <strong><?php echo htmlspecialchars($gagnant_enchere['nom_offrant_masque']); ?></strong><br>
                                    Offre gagnante: <strong><?php echo number_format($gagnant_enchere['montant_offre'], 0, ',', ' '); ?> €</strong>
                                <?php else: ?>
                                    Enchère terminée<?php echo empty($offres_enchere) ? ' sans offres.' : '.'; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($enchere_active && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client' && ($_SESSION['user_id'] != $propriete['id_agent_responsable'])): ?>
                                <a href="<?php echo $path_prefix; ?>placer_enchere_page.php?id_propriete=<?php echo $propriete_id; ?>&enchere_id=<?php echo $enchere_details['enchere_id']; ?>" class="btn btn-warning btn-lg w-100 mt-3 shadow-sm">
                                    <i class="fas fa-gavel me-2"></i>Accéder à la Page d'Enchère
                                </a>
                            <?php elseif ($enchere_active && (!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'client')) : ?>
                                <p class="mt-3"><a href="<?php echo $path_prefix; ?>votre-compte.php">Connectez-vous en tant que client</a> pour placer une offre.</p>
                            <?php elseif ($enchere_active && isset($_SESSION['loggedin']) && $_SESSION['user_id'] == $propriete['id_agent_responsable']): ?>
                                <p class="mt-3 text-muted"><i>Vous ne pouvez pas enchérir sur une propriété dont vous êtes l'agent responsable.</i></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h4>Historique des offres</h4>
                            <?php if (empty($offres_enchere)): ?>
                                <p class="text-muted fst-italic">Aucune offre n'a été placée pour le moment. Soyez le premier !</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush auction-offers-list">
                                    <?php foreach ($offres_enchere as $offre): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <strong class="text-success"><?php echo number_format($offre['montant_offre'], 0, ',', ' '); ?> €</strong> 
                                                par <?php echo htmlspecialchars($offre['nom_offrant_masque']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo htmlspecialchars(date("d/m/y H:i", strtotime($offre['date_heure_offre']))); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <!-- FIN SECTION ENCHÈRES -->

        <?php if ($agent_responsable): ?>
        <div class="row mb-4">
             <div class="col-md-8">
                <div class="card shadow-sm h-100">
                     <div class="card-header bg-light"><h5 class="mb-0">Agent Responsable</h5></div>
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <img src="<?php echo $agent_photo_path; ?>" alt="Photo de <?php echo htmlspecialchars($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?>" class="img-fluid rounded-circle agent-details-photo">
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title"><?php echo htmlspecialchars($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($agent_responsable['specialite'] ?? 'Agent Immobilier'); ?></p>
                            <p class="card-text"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($agent_responsable['email']); ?><br>
                               <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($agent_responsable['telephone_pro'] ?? 'N/A'); ?><br>
                               <i class="fas fa-briefcase me-2"></i>Bureau: <?php echo htmlspecialchars($agent_responsable['bureau'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
             <div class="col-md-4">
                 <div class="card shadow-sm h-100">
                    <div class="card-header bg-light"><h5 class="mb-0">Localisation</h5></div>
                    <div class="card-body p-0 map-container">
                        <iframe src="https://maps.google.com/maps?q=<?php echo urlencode($propriete['adresse'] . ", " . $propriete['code_postal'] . " " . $propriete['ville']); ?>&output=embed" width="100%" height="100%" class="map-iframe" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <?php if (empty($error_message_prop)): ?>
             <div class="alert alert-warning">La propriété demandée n'a pas pu être chargée ou n'est pas disponible.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'php/includes/footer.php'; ?> 