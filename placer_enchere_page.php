<?php
$path_prefix = '';
$page_title = "Placer une Enchère";
require_once 'php/config/db.php';
require_once 'php/includes/header.php';

$propriete_id = null;
$enchere_id = null;
$propriete = null;
$enchere_details = null;
$prix_actuel_enchere = null;
$enchere_active = false;

$error_message = '';
$success_message = '';

if(isset($_SESSION['success_message_prop'])) {
    $success_message = $_SESSION['success_message_prop'];
    unset($_SESSION['success_message_prop']);
}
if(isset($_SESSION['error_message_prop'])) {
    $error_message = $_SESSION['error_message_prop'];
    unset($_SESSION['error_message_prop']);
}

if (!$pdo) {
    $error_message = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
} else {
    if (isset($_GET['id_propriete']) && filter_var($_GET['id_propriete'], FILTER_VALIDATE_INT) && $_GET['id_propriete'] > 0 &&
        isset($_GET['enchere_id']) && filter_var($_GET['enchere_id'], FILTER_VALIDATE_INT) && $_GET['enchere_id'] > 0) {
        
        $propriete_id = (int)$_GET['id_propriete'];
        $enchere_id = (int)$_GET['enchere_id'];

        try {
            $sql_propriete = "SELECT id, titre, photo_principale_filename, id_agent_responsable FROM Proprietes WHERE id = :id_propriete";
            $stmt_prop = $pdo->prepare($sql_propriete);
            $stmt_prop->execute([':id_propriete' => $propriete_id]);
            $propriete = $stmt_prop->fetch(PDO::FETCH_ASSOC);

            if (!$propriete) {
                throw new Exception("Propriété non trouvée.");
            }

            $sql_enchere = "SELECT e.id, e.id_propriete, e.date_heure_debut, e.date_heure_fin, e.prix_depart, 
                                COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel 
                            FROM Encheres e 
                            LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
                            WHERE e.id = :enchere_id AND e.id_propriete = :id_propriete
                            GROUP BY e.id, e.id_propriete, e.date_heure_debut, e.date_heure_fin, e.prix_depart";
            $stmt_ench = $pdo->prepare($sql_enchere);
            $stmt_ench->execute([':enchere_id' => $enchere_id, ':id_propriete' => $propriete_id]);
            $enchere_details = $stmt_ench->fetch(PDO::FETCH_ASSOC);

            if (!$enchere_details) {
                throw new Exception("Détails de l'enchère non trouvés pour cette propriété.");
            }

            $prix_actuel_enchere = $enchere_details['prix_actuel'];
            $now = new DateTime();
            $date_debut_enchere = new DateTime($enchere_details['date_heure_debut']);
            $date_fin_enchere = new DateTime($enchere_details['date_heure_fin']);
            if ($now >= $date_debut_enchere && $now <= $date_fin_enchere) {
                $enchere_active = true;
            }
            $page_title = "Enchérir sur: " . htmlspecialchars($propriete['titre']);

        } catch (Exception $e) {
            $error_message = "Erreur: " . $e->getMessage();
        }
    } else {
        $error_message = "ID de propriété ou d'enchère manquant ou invalide.";
    }
}

$main_photo_path = $path_prefix . "assets/properties/" . htmlspecialchars($propriete['photo_principale_filename'] ?? 'default_property.jpg');

?>

<div class="container mt-5 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $path_prefix; ?>index.php">Accueil</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $propriete_id; ?>"><?php echo htmlspecialchars($propriete['titre'] ?? 'Propriété'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Placer une Enchère</li>
        </ol>
    </nav>

    <h2 class="mb-4"><?php echo $page_title; ?></h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($propriete && $enchere_details && empty($error_message)): ?>
        <div class="row">
            <div class="col-md-5 mb-4">
                <div class="card shadow-sm">
                    <img src="<?php echo $main_photo_path; ?>" class="card-img-top property-main-img" alt="Photo de <?php echo htmlspecialchars($propriete['titre']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($propriete['titre']); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark"><h4 class="mb-0"><i class="fas fa-gavel me-2"></i>Informations sur l'enchère</h4></div>
                    <div class="card-body">
                        <p><strong>Prix de départ :</strong> <?php echo number_format($enchere_details['prix_depart'], 0, ',', ' '); ?> €</p>
                        <p><strong>Prix actuel :</strong> <strong class="text-success fs-4"><?php echo number_format($prix_actuel_enchere, 0, ',', ' '); ?> €</strong></p>
                        <p><strong>Début de l'enchère :</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_debut']))); ?></p>
                        <p class="mb-3"><strong>Fin de l'enchère :</strong> <strong class="text-danger"><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($enchere_details['date_heure_fin']))); ?></strong></p>
                        <?php 
                            $temps_restant_secondes = 0;
                            if ($enchere_active) {
                                $temps_restant_secondes = strtotime($enchere_details['date_heure_fin']) - time();
                                $jours_restants = floor($temps_restant_secondes / (60 * 60 * 24));
                                $heures_restantes = floor(($temps_restant_secondes % (60 * 60 * 24)) / (60 * 60));
                                $minutes_restantes = floor(($temps_restant_secondes % (60 * 60)) / 60);
                            }
                        ?>
                        <div class="alert <?php echo $enchere_active ? 'alert-info' : 'alert-secondary'; ?>">
                            <i class="fas fa-clock me-1"></i> 
                            <?php if ($enchere_active && $temps_restant_secondes > 0): ?>
                                Temps restant: 
                                <?php if ($jours_restants > 0) echo $jours_restants . "j "; ?>
                                <?php if ($heures_restantes > 0 || $jours_restants > 0) echo $heures_restantes . "h "; ?>
                                <?php echo $minutes_restantes . "min"; ?>
                            <?php elseif (!$enchere_active && isset($date_debut_enchere) && $now < $date_debut_enchere): ?>
                                L'enchère n'a pas encore commencé.
                            <?php else: ?>
                                Enchère terminée.
                            <?php endif; ?>
                        </div>

                        <?php if ($enchere_active && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['user_type'] === 'client' && ($_SESSION['user_id'] != $propriete['id_agent_responsable'])): ?>
                            <form action="<?php echo $path_prefix; ?>php/actions/placer_offre_action.php" method="POST" class="mt-3 p-3 border rounded bg-light">
                                <h5 class="mb-3">Placer votre offre</h5>
                                <input type="hidden" name="id_enchere" value="<?php echo $enchere_details['id']; ?>">
                                <input type="hidden" name="id_propriete" value="<?php echo $propriete['id']; ?>">
                                <div class="mb-3">
                                    <label for="montant_offre" class="form-label">Votre offre (€)</label>
                                    <input type="number" class="form-control form-control-lg" id="montant_offre" name="montant_offre" min="<?php echo $prix_actuel_enchere + 1; ?>" step="1" required>
                                    <div class="form-text">L'offre doit être supérieure à <?php echo number_format($prix_actuel_enchere, 0, ',', ' '); ?> €.</div>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100"><i class="fas fa-paper-plane me-2"></i>Soumettre mon offre</button>
                            </form>
                        <?php elseif ($enchere_active && (!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'client')) : ?>
                            <p class="mt-3 text-center"><a href="<?php echo $path_prefix; ?>votre-compte.php" class="btn btn-primary">Connectez-vous en tant que client</a> pour placer une offre.</p>
                        <?php elseif ($enchere_active && isset($_SESSION['loggedin']) && $_SESSION['user_id'] == $propriete['id_agent_responsable']): ?>
                            <p class="mt-3 text-muted fst-italic">Vous ne pouvez pas enchérir sur une propriété dont vous êtes l'agent responsable.</p>
                        <?php elseif (!$enchere_active): ?>
                             <p class="mt-3 text-center text-muted fst-italic">Cette enchère est terminée ou n'a pas encore commencé. Vous ne pouvez plus placer d'offres.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($error_message)): ?>
        <?php echo "<div class='alert alert-warning'>Impossible de charger les détails de l'enchère. Veuillez vérifier le lien ou retourner à la page de la propriété.</div>"; ?>
    <?php endif; ?>
</div>

<?php require_once 'php/includes/footer.php'; ?> 
