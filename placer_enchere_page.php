<?php
$path_prefix = ''; // Chemins relatifs, base du site
$page_title = "Détails de la Propriété";
require_once 'php/config/db.php'; // DB d'abord, header après ($pdo)
require_once 'php/includes/header.php';

$propriete_id = null; // Init vars
$propriete = null;
$agent_responsable = null;
$enchere_details = null;
$offres_enchere = [];
$prix_actuel_enchere = null;
$enchere_active = false;
$gagnant_enchere = null; // Pour le gagnant, si y'en a un

$error_message_prop = ''; // Messages flash
$success_message_prop = ''; 

if(isset($_SESSION['success_message_prop'])) {
    $success_message_prop = $_SESSION['success_message_prop'];
    unset($_SESSION['success_message_prop']); // Clean session
}
if(isset($_SESSION['error_message_prop'])) {
    $error_message_prop = $_SESSION['error_message_prop'];
    unset($_SESSION['error_message_prop']); // Idem
}

if (!$pdo) {
    $error_message_prop = "GROS PB: DB KO. Check config."; // Oups DB...
} else {
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT) && $_GET['id'] > 0) { // Check ID proprio
        $propriete_id = $_GET['id'];

        try {
           // Recup infos proprio + agent
            $sql_propriete = "SELECT p.*, 
                                   u.nom as agent_nom, u.prenom as agent_prenom, u.email as agent_email, 
                                   ai.telephone_pro as agent_tel_pro, ai.photo_filename as agent_photo, ai.specialite as agent_specialite, ai.bureau as agent_bureau
                            FROM Proprietes p 
                            LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur 
                            LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id 
                            WHERE p.id = :propriete_id AND (p.statut = 'disponible' OR p.statut = 'enchere_active')"; // Doit être dispo ou enchère, logique
            
            $stmt_prop = $pdo->prepare($sql_propriete);
            $stmt_prop->execute([':propriete_id' => $propriete_id]);
            $propriete = $stmt_prop->fetch(PDO::FETCH_ASSOC);

            if ($propriete) {
                $page_title = htmlspecialchars($propriete['titre']) . " | OMNES IMMOBILIER"; // Titre dynamique, cool
                if ($propriete['id_agent_responsable']) { // Si agent lié
                    $agent_responsable = [ // Array pour l'agent
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

                // Si enchère, choper détails
                $sql_enchere_check = "SELECT e.id as enchere_id, e.date_heure_debut, e.date_heure_fin, e.prix_depart, 
                                        COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel 
                                    FROM Encheres e 
                                    LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
                                    WHERE e.id_propriete = :propriete_id 
                                    GROUP BY e.id";
                $stmt_ench_check = $pdo->prepare($sql_enchere_check);
                $stmt_ench_check->execute([':propriete_id' => $propriete_id]);
                $enchere_details = $stmt_ench_check->fetch(PDO::FETCH_ASSOC);

                if ($enchere_details) { // Si y'a une enchère active sur ce bien
                    $prix_actuel_enchere = $enchere_details['prix_actuel'];
                    // Enchère en cours ou pas?
                    $now = new DateTime(); // Maintenant!
                    $date_debut_enchere = new DateTime($enchere_details['date_heure_debut']);
                    $date_fin_enchere = new DateTime($enchere_details['date_heure_fin']);
                    if ($now >= $date_debut_enchere && $now <= $date_fin_enchere) {
                        $enchere_active = true; // Yes, c'est parti
                    }

                    // Historique offres, pour voir qui mise
                    $sql_offres = "SELECT oe.montant_offre, oe.date_heure_offre, 
                                       CONCAT(SUBSTRING(u.prenom, 1, 1), '***', SUBSTRING(u.nom, 1, 1), '***') as nom_offrant_masque
                                FROM OffresEncheres oe
                                JOIN Utilisateurs u ON oe.id_client = u.id
                                WHERE oe.id_enchere = :enchere_id 
                                ORDER BY oe.montant_offre DESC, oe.date_heure_offre DESC"; // Classique
                    $stmt_offres = $pdo->prepare($sql_offres);
                    $stmt_offres->execute([':enchere_id' => $enchere_details['enchere_id']]);
                    $offres_enchere = $stmt_offres->fetchAll(PDO::FETCH_ASSOC);

                    // Gagnant si enchère finie + offres existent
                    if (!$enchere_active && $now > $date_fin_enchere && !empty($offres_enchere)) {
                        $gagnant_enchere = $offres_enchere[0]; // Le premier car trié DESC
                        
                        // Si enchère juste terminée et proprio encore en 'enchere_active'
                        // MAJ statut proprio -> 'enchere_terminee_gagnee' (automatique)
                        if ($propriete['statut'] === 'enchere_active') {
                            try {
                                $sql_update_statut = "UPDATE Proprietes SET statut = 'enchere_terminee_gagnee' WHERE id = :id_propriete";
                                $stmt_update = $pdo->prepare($sql_update_statut);
                                $stmt_update->execute([':id_propriete' => $propriete_id]);
                                $propriete['statut'] = 'enchere_terminee_gagnee'; // MAJ var locale pour affichage direct, pas redemander à la BDD
                                // Log c'est bien.
                                error_log("Proprio ID: $propriete_id statut MAJ -> enchere_terminee_gagnee post-enchere.");
                            } catch (PDOException $e) {
                                $error_message_prop .= "<br>Erreur MAJ statut proprio post-enchère: " . $e->getMessage(); // Aie
                                error_log("PDO Erreur MAJ statut proprio post-enchere ID $propriete_id: " . $e->getMessage());
                            }
                        }
                    }
                }
            } else {
                $error_message_prop = "Proprio pas trouvée / pas dispo."; 
            }
        } catch (PDOException $e) {
            $error_message_prop = "Erreur DB: " . htmlspecialchars($e->getMessage()); 
            error_log("PDO Erreur in propriete_details.php: " . $e->getMessage()); 
        }
    } else {
        $error_message_prop = "ID proprio invalide/manquant."; 
    }
}

// Chemins photos, avec defaults au cas où ça foire
$main_photo_path = $path_prefix . "assets/properties/" . htmlspecialchars($propriete['photo_principale'] ?? 'default_property.jpg');
$agent_photo_path = $path_prefix . "assets/agents/photos/" . htmlspecialchars($agent_responsable['photo_filename'] ?? 'default_agent.png');

?>

<div class="container mt-5 mb-5">
    <?php if (!empty($success_message_prop)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($success_message_prop); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endif; ?>
    <?php if (!empty($error_message_prop)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error_message_prop); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php elseif ($propriete): // Si tout va bien, on affiche ?>
        <h1 class="mb-4 property-title"><?php echo htmlspecialchars($propriete['titre']); ?></h1>
        
        <!-- Section proprio & agent -->
        <div class="row mb-4">
            <div class="col-md-8">
                <img src="<?php echo $main_photo_path; ?>" class="img-fluid rounded shadow-sm w-100 property-main-img" alt="Photo principale de <?php echo htmlspecialchars($propriete['titre']); ?>">
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100"> <!-- Carte infos rapides -->
                    <div class="card-body">
                        <h4 class="card-title text-primary"><?php echo number_format($propriete['prix'], 0, ',', ' '); ?> €</h4>
                        <p class="text-muted small">(Prix vente direct. Si enchère, voir section Enchères)</p>
                        <hr>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($propriete['type_propriete'])); ?></p>
                        <p><strong>Adresse:</strong> <?php echo htmlspecialchars($propriete['adresse'] . ", " . $propriete['code_postal'] . " " . $propriete['ville']); ?></p>
                        <hr>
                        <ul class="list-unstyled"> <!-- Specs techniques -->
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
                        <?php // Logique boutons action client (rdv, chat) si pas enchère
                        if (!$enchere_details && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client' && $agent_responsable): ?>
                            <a href="<?php echo $path_prefix; ?>rendez-vous.php?action=take_rdv&agent_id=<?php echo $agent_responsable['id']; ?>&property_id=<?php echo $propriete_id; ?>" class="btn btn-primary w-100 mb-2"><i class="fas fa-calendar-check me-2"></i>Prendre RDV</a>
                            <a href="<?php echo $path_prefix; ?>chat.php?contact_id=<?php echo $agent_responsable['id']; ?>&contact_name=<?php echo urlencode($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?>" class="btn btn-info w-100"><i class="fas fa-comments me-2"></i>Contacter Agent</a>
                        <?php elseif (!$enchere_details && $agent_responsable): // Pas loggué mais agent existe ?>
                             <p class="text-center"><a href="<?php echo $path_prefix; ?>votre-compte.php">Co</a> ou <a href="<?php echo $path_prefix; ?>votre-compte.php#inscription">crée compte</a> pour RDV/contact.</p>
                        <?php elseif (!$agent_responsable && !$enchere_details): // Ni agent, ni enchère ?>
                             <p class="text-muted text-center"><i>Nous contacter pour infos.</i></p>
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
                        <?php echo nl2br(htmlspecialchars($propriete['description'])); // nl2br pour les sauts de ligne ?>
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
                            <iframe src="<?php echo htmlspecialchars(str_replace("watch?v=", "embed/", $propriete['url_video'])); // Formatage URL YouTube pour embed ?>" title="Vidéo de la propriété" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION ENCHERES SI YEN A UNE -->
        <?php if ($enchere_details): ?>
        <section id="enchere" class="mb-5 pt-3">
            <div class="card shadow-sm"> <!-- Carte Enchère -->
                <div class="card-header bg-warning text-dark"><h3 class="mb-0"><i class="fas fa-gavel me-2"></i>Détails Enchère</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6"> <!-- Col infos enchère -->
                            <h4>Infos enchère</h4>
                            <p><strong>Prix départ :</strong> <?php echo number_format($enchere_details['prix_depart'], 0, ',', ' '); ?> €</p>
                            <p><strong>Prix actuel :</strong> <strong class="text-success fs-4"><?php echo number_format($prix_actuel_enchere, 0, ',', ' '); ?> €</strong></p>
                            <p><strong>Début :</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_debut']))); ?></p>
                            <p class="mb-3"><strong>Fin :</strong> <strong class="text-danger"><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_fin']))); ?></strong></p>
                            <?php 
                                // Calcul temps restant
                                $temps_restant_secondes = strtotime($enchere_details['date_heure_fin']) - time();
                                $jours_restants = floor($temps_restant_secondes / (60 * 60 * 24));
                                $heures_restantes = floor(($temps_restant_secondes % (60 * 60 * 24)) / (60 * 60));
                                $minutes_restantes = floor(($temps_restant_secondes % (60 * 60)) / 60);
                            ?>
                            <div class="alert <?php echo $enchere_active ? 'alert-info' : 'alert-secondary'; // Style selon statut ?>">
                                <i class="fas fa-clock me-1"></i> 
                                <?php if ($enchere_active && $temps_restant_secondes > 0): ?>
                                    Temps restant: 
                                    <?php if ($jours_restants > 0) echo $jours_restants . "j "; ?>
                                    <?php if ($heures_restantes > 0 || $jours_restants > 0) echo $heures_restantes . "h "; ?>
                                    <?php echo $minutes_restantes . "min"; ?>
                                <?php elseif (!$enchere_active && $now < $date_debut_enchere): ?>
                                    Enchère pas encore commencée.
                                <?php elseif ($gagnant_enchere): ?>
                                    <strong class="text-success"><i class="fas fa-trophy me-1"></i>Enchère Finie !</strong><br>
                                    Gagnant: <strong><?php echo htmlspecialchars($gagnant_enchere['nom_offrant_masque']); // Nom masqué ?></strong><br>
                                    Offre gagnante: <strong><?php echo number_format($gagnant_enchere['montant_offre'], 0, ',', ' '); ?> €</strong>
                                <?php else: ?>
                                    Enchère terminée<?php echo empty($offres_enchere) ? ' sans offres.' : '.'; // Si y'a eu des offres ou pas ?>
                                <?php endif; ?>
                            </div>

                            <?php // Bouton pour placer enchère si client loggué, enchère active, etc.
                            if ($enchere_active && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client' && ($_SESSION['user_id'] != $propriete['id_agent_responsable'])): // Agent peut pas enchérir sur ses trucs, normal
                                ?>
                                <a href="<?php echo $path_prefix; ?>placer_enchere_page.php?id_propriete=<?php echo $propriete_id; ?>&enchere_id=<?php echo $enchere_details['enchere_id']; ?>" class="btn btn-warning btn-lg w-100 mt-3 shadow-sm">
                                    <i class="fas fa-gavel me-2"></i>Go Page Enchère
                                </a>
                            <?php elseif ($enchere_active && (!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'client')) : // Pas loggué client ?>
                                <p class="text-muted text-center mt-3">Connectez-vous en tant que client pour enchérir.</p>
                            <?php elseif (!$enchere_active && $gagnant_enchere && isset($_SESSION['loggedin']) && $_SESSION['user_id'] == $gagnant_enchere['id_client_original']): // TODO: id_client_original pas dispo ici, juste le nom masqué. Voir comment afficher ça au gagnant proprement.
                                // $sql_get_winner_id = "SELECT id_client FROM OffresEncheres WHERE id_enchere = :enchere_id ORDER BY montant_offre DESC, date_heure_offre DESC LIMIT 1"; ... fetch ...
                                // if($_SESSION['user_id'] == $actual_winner_id) { ... }
                                // Actuellement, on ne peut pas confirmer si le user connecté EST le gagnant juste avec nom_offrant_masque.
                                // Pour l'instant, on affiche rien de spécial au gagnant ici. À améliorer si temps.
                            ?>
                                <!-- <p class="alert alert-success mt-3">Félicitations, vous avez remporté cette enchère !</p> -->
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6"> <!-- Col historique offres -->
                            <h4>Historique des Offres</h4>
                            <?php if (!empty($offres_enchere)): ?>
                                <ul class="list-group list-group-flush auction-offers-list"> <!-- Liste simple -->
                                    <?php foreach ($offres_enchere as $offre): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($offre['nom_offrant_masque']); // Masqué pour anonymat ?> - <?php echo htmlspecialchars(date("d/m/y H:i", strtotime($offre['date_heure_offre']))); ?></span>
                                            <span class="badge bg-secondary rounded-pill"><?php echo number_format($offre['montant_offre'], 0, ',', ' '); ?> €</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Aucune offre pour le moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Section Agent Responsable -->
        <?php if ($agent_responsable): ?>
        <section id="agent-responsable" class="mt-5 mb-4 pt-3">
            <div class="card shadow-sm"> <!-- Carte Agent -->
                <div class="card-header bg-light"><h3 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent Responsable</h3></div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0"> <!-- Photo agent -->
                            <img src="<?php echo $agent_photo_path; ?>" class="img-fluid rounded-circle shadow-sm agent-details-photo" alt="Photo de <?php echo htmlspecialchars($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?>" style="max-width: 150px; height: 150px; object-fit: cover;">
                        </div>
                        <div class="col-md-9"> <!-- Infos agent -->
                            <h4><?php echo htmlspecialchars($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?></h4>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($agent_responsable['specialite'] ?? 'Agent Immobilier'); ?></p>
                            <p><i class="fas fa-briefcase me-2 text-muted"></i>Bureau: <?php echo htmlspecialchars($agent_responsable['bureau'] ?? 'Non spécifié'); ?></p>
                            <p><i class="fas fa-phone-alt me-2 text-muted"></i>Tél: <?php echo htmlspecialchars($agent_responsable['telephone_pro'] ?? 'Non communiqué'); ?></p>
                            <p><i class="fas fa-envelope me-2 text-muted"></i>Email: <a href="mailto:<?php echo htmlspecialchars($agent_responsable['email']); ?>"><?php echo htmlspecialchars($agent_responsable['email']); ?></a></p>
                            <?php // Boutons actions si pas enchère en cours (redondant avec plus haut mais bon...)
                            if (!$enchere_details && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client'): ?>
                                <a href="<?php echo $path_prefix; ?>rendez-vous.php?action=take_rdv&agent_id=<?php echo $agent_responsable['id']; ?>&property_id=<?php echo $propriete_id; ?>" class="btn btn-outline-primary mt-2 me-2"><i class="fas fa-calendar-check me-2"></i>Prendre RDV</a>
                                <a href="<?php echo $path_prefix; ?>chat.php?contact_id=<?php echo $agent_responsable['id']; ?>&contact_name=<?php echo urlencode($agent_responsable['prenom'] . ' ' . $agent_responsable['nom']); ?>" class="btn btn-outline-info mt-2"><i class="fas fa-comments me-2"></i>Contacter</a>
                            <?php elseif (!$enchere_details): // Pas loggué client ?>
                                <p class="mt-2"><small><a href="<?php echo $path_prefix; ?>votre-compte.php">Connectez-vous</a> ou <a href="<?php echo $path_prefix; ?>votre-compte.php#inscription">créez un compte client</a> pour interagir avec l'agent.</small></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section Carte (si adresse existe) -->
        <?php if (!empty($propriete['adresse'])): ?>
        <section id="map-localisation" class="mt-5 mb-4 pt-3">
            <div class="card shadow-sm"> <!-- Carte Google Maps -->
                <div class="card-header bg-light"><h3 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Localisation</h3></div>
                <div class="card-body map-container">
                    <?php
                    $map_query = urlencode($propriete['adresse'] . ", " . $propriete['code_postal'] . " " . $propriete['ville']);
                    $map_url = "https://www.google.com/maps/embed/v1/place?key=YOUR_GOOGLE_MAPS_API_KEY&q=" . $map_query; // REMPLACER API KEY ABSOLUMENT
                    // Note: Pour que ça marche, il FAUT une clé API Google Maps Platform valide et activée pour Embed API.
                    // Sans clé valide, la carte ne s'affichera pas ou affichera une erreur.
                    // Vu que c'est un projet étudiant, on peut laisser une iframe générique ou un placeholder si pas de clé.
                    // $map_url_placeholder = "https://via.placeholder.com/800x400.png?text=Carte+Indisponible+(API+Key+Manquante)";
                    // echo '<iframe width="100%" height="400" style="border:0;" loading="lazy" allowfullscreen src="' . $map_url_placeholder . '"></iframe>';
                    // Pour l'instant, on assume que la clé sera ajoutée... ou pas.
                    ?>
                    <iframe
                        class="map-iframe"
                        width="100%"
                        height="450"
                        style="border:0;"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="<?php echo $map_url; ?>">
                    </iframe>
                    <p class="mt-2 text-muted small">Adresse approximative. L'adresse exacte est communiquée sur demande.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>

    <?php else: // Si $propriete est null (ou $error_message_prop est set) ?>
        <div class="alert alert-warning" role="alert">
            La propriété demandée n'a pas pu être chargée. Veuillez vérifier l'ID ou <a href="<?php echo $path_prefix; ?>index.php" class="alert-link">retourner à l'accueil</a>. <!-- Fallback si rien ne charge -->
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'php/includes/footer.php';
?> 
