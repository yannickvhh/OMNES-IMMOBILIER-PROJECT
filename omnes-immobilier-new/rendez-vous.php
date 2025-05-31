<?php
session_start(); // Required for session variables
require_once 'php/config/db.php'; // Database connection
require_once 'php/utils/functions.php'; // Utility functions like isUserLoggedIn

$is_logged_in = isUserLoggedIn();
$page_title = "Rendez-vous | OMNES IMMOBILIER";

$action = isset($_GET['action']) ? $_GET['action'] : 'form'; // 'form', 'view', 'take_rdv'
$agent_id_for_rdv = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
$property_id_for_rdv = isset($_GET['property_id']) ? intval($_GET['property_id']) : null;

$user_appointments = [];
$agent_details = null;
$agent_schedule = []; // To hold [day_of_week => [slots]]
$property_details_for_rdv = null; // To hold property details if property_id is present

// Fetch property details if a property_id is provided for the RDV
if ($property_id_for_rdv) {
    $sql_prop = "SELECT id, titre, adresse, ville FROM Proprietes WHERE id = ?";
    if ($stmt_prop = $mysqli->prepare($sql_prop)) {
        $stmt_prop->bind_param("i", $property_id_for_rdv);
        if ($stmt_prop->execute()) {
            $res_prop = $stmt_prop->get_result();
            if ($res_prop->num_rows == 1) {
                $property_details_for_rdv = $res_prop->fetch_assoc();
            }
        }
        $stmt_prop->close();
    }
}

if ($is_logged_in) {
    if ($_SESSION['user_type'] == 'client' && $action == 'view') {
        // Client viewing their appointments
        $sql_client_rdv = "SELECT r.*, p.titre as propriete_titre, p.adresse as propriete_adresse, CONCAT(u.prenom, ' ', u.nom) as agent_nom, u.email as agent_email, d.jour_semaine, DATE_FORMAT(d.heure_debut, '%H:%i') as heure_debut, da.photo_filename as agent_photo
                           FROM RendezVous r
                           LEFT JOIN Proprietes p ON r.id_propriete = p.id
                           JOIN Utilisateurs u ON r.id_agent = u.id
                           LEFT JOIN AgentsImmobiliers da ON r.id_agent = da.id_utilisateur
                           LEFT JOIN DisponibilitesAgents d ON r.id_disponibilite = d.id
                           WHERE r.id_client = ? ORDER BY r.date_heure_rdv DESC";
        if ($stmt = $mysqli->prepare($sql_client_rdv)) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $user_appointments[] = $row;
                }
            }
            $stmt->close();
        }
    } elseif ($action == 'take_rdv' && $agent_id_for_rdv) {
        // Fetch agent details for taking an RDV
        $sql_agent = "SELECT u.id, u.nom, u.prenom, u.email, ai.specialite, ai.photo_filename FROM Utilisateurs u JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur WHERE u.id = ? AND u.type_compte = 'agent'";
        if ($stmt_agent = $mysqli->prepare($sql_agent)) {
            $stmt_agent->bind_param("i", $agent_id_for_rdv);
            if ($stmt_agent->execute()) {
                $res_agent = $stmt_agent->get_result();
                if ($res_agent->num_rows == 1) {
                    $agent_details = $res_agent->fetch_assoc();
                    $sql_avail = "SELECT id, jour_semaine, DATE_FORMAT(heure_debut, '%H:%i') as heure_debut, DATE_FORMAT(heure_fin, '%H:%i') as heure_fin, est_reserve FROM DisponibilitesAgents WHERE id_agent = ? AND est_reserve = FALSE ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), heure_debut";
                    if($stmt_avail = $mysqli->prepare($sql_avail)){
                        $stmt_avail->bind_param("i", $agent_id_for_rdv);
                        if($stmt_avail->execute()){
                            $res_avail = $stmt_avail->get_result();
                            while($slot = $res_avail->fetch_assoc()){
                                $agent_schedule[$slot['jour_semaine']][] = $slot;
                            }
                        }
                        $stmt_avail->close();
                    }
                }
            }
            $stmt_agent->close();
        }
    } elseif ($_SESSION['user_type'] == 'agent' && $action == 'view') {
        // Agent viewing their own schedule/appointments (to be implemented)
        // This would be more detailed, perhaps in agent_dashboard.php
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_rdv'])) {
    if ($is_logged_in && $_SESSION['user_type'] == 'client') {
        $slot_id = isset($_POST['slot_id']) ? intval($_POST['slot_id']) : null;
        $id_agent_rdv = isset($_POST['id_agent']) ? intval($_POST['id_agent']) : null;
        // Use property_id_for_rdv if available from GET, otherwise check POST (though less likely for this flow)
        $id_propriete_rdv_final = $property_id_for_rdv ? $property_id_for_rdv : (isset($_POST['id_propriete']) ? intval($_POST['id_propriete']) : null);
        $notes_client = isset($_POST['notes_client']) ? trim($_POST['notes_client']) : null;

        if ($slot_id && $id_agent_rdv) {
            $mysqli->begin_transaction();
            try {
                // Fetch slot details to get day and time for date_heure_rdv calculation
                $stmt_slot_details = $mysqli->prepare("SELECT jour_semaine, heure_debut FROM DisponibilitesAgents WHERE id = ? AND id_agent = ? AND est_reserve = FALSE");
                $stmt_slot_details->bind_param("ii", $slot_id, $id_agent_rdv);
                $stmt_slot_details->execute();
                $result_slot_details = $stmt_slot_details->get_result();
                if ($result_slot_details->num_rows == 0) {
                    throw new Exception("Ce créneau n'est plus disponible ou une erreur est survenue.");
                }
                $slot_info = $result_slot_details->fetch_assoc();
                $stmt_slot_details->close();

                // Calculate the actual date_heure_rdv
                $date_heure_rdv = calculateNextAvailableDateTime($slot_info['jour_semaine'], $slot_info['heure_debut']);
                if (!$date_heure_rdv) {
                    throw new Exception("Impossible de calculer la date du rendez-vous pour le créneau sélectionné.");
                }

                $stmt_update_slot = $mysqli->prepare("UPDATE DisponibilitesAgents SET est_reserve = TRUE WHERE id = ? AND id_agent = ? AND est_reserve = FALSE");
                $stmt_update_slot->bind_param("ii", $slot_id, $id_agent_rdv);
                $stmt_update_slot->execute();
                if ($stmt_update_slot->affected_rows == 0) {
                    // This check is somewhat redundant due to the one above but good for safety
                    throw new Exception("Ce créneau n'est plus disponible ou une erreur est survenue lors de la réservation.");
                }
                $stmt_update_slot->close();

                $stmt_insert_rdv = $mysqli->prepare("INSERT INTO RendezVous (id_client, id_agent, id_propriete, id_disponibilite, date_heure_rdv, notes_client, statut) VALUES (?, ?, ?, ?, ?, ?, 'planifie')");
                // Make id_propriete nullable in DB or handle NULL value properly if it can be optional
                $stmt_insert_rdv->bind_param("iiiiss", $_SESSION['user_id'], $id_agent_rdv, $id_propriete_rdv_final, $slot_id, $date_heure_rdv, $notes_client);
                if (!$stmt_insert_rdv->execute()) {
                    throw new Exception("Erreur lors de la création du rendez-vous: " . $stmt_insert_rdv->error);
                }
                $stmt_insert_rdv->close();

                $mysqli->commit();
                $_SESSION['rdv_success'] = "Votre rendez-vous a été confirmé pour le " . htmlspecialchars(date('d/m/Y \à H:i', strtotime($date_heure_rdv))) . ". Vous recevrez une notification.";
                header("Location: rendez-vous.php?action=view");
                exit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $_SESSION['rdv_error'] = "Erreur : " . $e->getMessage();
                // Redirect back to the RDV taking page, preserving parameters
                $redirect_url = "rendez-vous.php?action=take_rdv&agent_id=" . $id_agent_rdv;
                if ($property_id_for_rdv) {
                    $redirect_url .= "&property_id=" . $property_id_for_rdv;
                }
                header("Location: " . $redirect_url);
                exit();
            }
        }
    } else {
        $_SESSION['rdv_error'] = "Vous devez être connecté en tant que client pour prendre un rendez-vous.";
        header("Location: votre-compte.php?redirect_to=rendez-vous.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_rdv'])) {
    if ($is_logged_in && $_SESSION['user_type'] == 'client') {
        $rdv_id_to_cancel = intval($_POST['rdv_id']);
        $slot_id_to_free = isset($_POST['slot_id']) ? intval($_POST['slot_id']) : null;
        $mysqli->begin_transaction();
        try {
            $stmt_cancel = $mysqli->prepare("UPDATE RendezVous SET statut = 'annule_client' WHERE id = ? AND id_client = ?");
            $stmt_cancel->bind_param("ii", $rdv_id_to_cancel, $_SESSION['user_id']);
            $stmt_cancel->execute();
            if($stmt_cancel->affected_rows == 0){
                throw new Exception("Rendez-vous non trouvé ou non autorisé à annuler.");
            }
            $stmt_cancel->close();

            if ($slot_id_to_free) {
                $stmt_free_slot = $mysqli->prepare("UPDATE DisponibilitesAgents SET est_reserve = FALSE WHERE id = ?");
                $stmt_free_slot->bind_param("i", $slot_id_to_free);
                $stmt_free_slot->execute();
                // We don't strictly need to check affected_rows here, as it's possible the slot was already freed or didn't exist (e.g. old RDV system)
                $stmt_free_slot->close();
            }
            $mysqli->commit();
            $_SESSION['rdv_cancel_success'] = "Votre rendez-vous a été annulé.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['rdv_cancel_error'] = "Erreur lors de l'annulation: " . $e->getMessage();
        }
        header("Location: rendez-vous.php?action=view");
        exit();
    }
}

$days_of_week_ordered = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

// The rest of your HTML doctype, head, body, etc. will follow here.
// The PHP variables $is_logged_in, $action, $user_appointments, $agent_details, $agent_schedule, $property_details_for_rdv,
// $_SESSION['rdv_success'], $_SESSION['rdv_error'], $_SESSION['rdv_cancel_success'], $_SESSION['rdv_cancel_error'] 
// can be used within the HTML part to display dynamic content.
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
    <?php include 'php/includes/header.php'; // Standard header include ?>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container navbar-container">
            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="index.html" class="nav-link">Accueil</a></li>
                <li class="nav-item"><a href="tout-parcourir.php" class="nav-link">Tout Parcourir</a></li>
                <li class="nav-item"><a href="recherche.php" class="nav-link">Recherche</a></li>
                <li class="nav-item"><a href="rendez-vous.php" class="nav-link active">Rendez-vous</a></li>
                <li class="nav-item"><a href="votre-compte.php" class="nav-link">Votre Compte</a></li>
            </ul>
        </div>
    </nav>

    <!-- Page Title -->
    <section class="section py-5">
        <div class="container">
            <div class="section-title text-center">
                <!-- Title changes based on action -->
                <?php if ($action == 'view' && $is_logged_in && $_SESSION['user_type'] == 'client'): ?>
                    <h2>Mes Rendez-vous</h2>
                    <p>Consultez et gérez vos rendez-vous programmés.</p>
                <?php elseif ($action == 'take_rdv' && $agent_details): ?>
                    <h2>Prendre Rendez-vous avec <?php echo htmlspecialchars($agent_details['prenom'] . ' ' . $agent_details['nom']); ?></h2>
                    <?php if ($property_details_for_rdv): ?>
                        <p>Pour la propriété : <?php echo htmlspecialchars($property_details_for_rdv['titre']); ?> située à <?php echo htmlspecialchars($property_details_for_rdv['adresse'] . ', ' . $property_details_for_rdv['ville']); ?>.</p>
                    <?php else: ?>
                        <p>Choisissez un créneau pour rencontrer cet agent.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Prendre un Rendez-vous</h2>
                    <p>Connectez-vous pour gérer vos rendez-vous ou sélectionnez un agent pour un nouveau rendez-vous.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section rdv-section py-5 bg-light">
        <div class="container">

            <?php if (isset($_SESSION['rdv_success'])): ?>
                <div class="alert alert-success text-center"><?php echo $_SESSION['rdv_success']; unset($_SESSION['rdv_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['rdv_error'])): ?>
                <div class="alert alert-danger text-center"><?php echo $_SESSION['rdv_error']; unset($_SESSION['rdv_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['rdv_cancel_success'])): ?>
                <div class="alert alert-success text-center"><?php echo $_SESSION['rdv_cancel_success']; unset($_SESSION['rdv_cancel_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['rdv_cancel_error'])): ?>
                <div class="alert alert-danger text-center"><?php echo $_SESSION['rdv_cancel_error']; unset($_SESSION['rdv_cancel_error']); ?></div>
            <?php endif; ?>

            <?php if (!$is_logged_in && ($action == 'view' || $action == 'take_rdv')): ?>
                <div class="alert alert-warning text-center">
                    Veuillez vous <a href="votre-compte.php?redirect_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">connecter</a> pour gérer vos rendez-vous ou en prendre un nouveau.
                </div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
                <?php if ($action == 'take_rdv' && $agent_details): ?>
                    <!-- RDV Taking Form -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Disponibilités de <?php echo htmlspecialchars($agent_details['prenom'] . ' ' . $agent_details['nom']); ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if ($property_details_for_rdv): ?>
                                <p class="lead">Pour la propriété #<?php echo htmlspecialchars($property_details_for_rdv['id']); ?>: <?php echo htmlspecialchars($property_details_for_rdv['titre']); ?>. 
                                    <a href="propriete_details.php?id=<?php echo htmlspecialchars($property_details_for_rdv['id']); ?>" target="_blank">Voir détails</a>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($agent_schedule)): ?>
                                <form method="POST" action="rendez-vous.php?action=take_rdv&agent_id=<?php echo $agent_id_for_rdv; ?><?php echo $property_id_for_rdv ? '&property_id='.$property_id_for_rdv : ''; ?>">
                                    <input type="hidden" name="id_agent" value="<?php echo $agent_id_for_rdv; ?>">
                                    <?php if ($property_id_for_rdv): ?>
                                        <input type="hidden" name="id_propriete" value="<?php echo $property_id_for_rdv; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="slot_id" class="form-label">Sélectionnez un créneau :</label>
                                        <select name="slot_id" id="slot_id" class="form-select" required>
                                            <option value="">-- Choisissez un jour et une heure --</option>
                                            <?php foreach ($days_of_week_ordered as $day): ?>
                                                <?php if (isset($agent_schedule[$day]) && !empty($agent_schedule[$day])): ?>
                                                    <optgroup label="<?php echo htmlspecialchars($day); ?>">
                                                        <?php foreach ($agent_schedule[$day] as $slot): ?>
                                                            <option value="<?php echo $slot['id']; ?>">
                                                                <?php echo htmlspecialchars($slot['heure_debut']); ?> - <?php echo htmlspecialchars($slot['heure_fin']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes_client" class="form-label">Notes pour l'agent (optionnel) :</label>
                                        <textarea name="notes_client" id="notes_client" class="form-control" rows="3" placeholder="Ex: Précisions sur votre recherche, questions spécifiques..."></textarea>
                                    </div>

                                    <p class="text-muted small">En cliquant sur "Confirmer le RDV", vous réservez ce créneau.</p>
                                    <button type="submit" name="confirm_rdv" class="btn btn-success"><i class="fas fa-check-circle"></i> Confirmer le RDV</button>
                                    <a href="agent_details.php?id=<?php echo $agent_id_for_rdv; ?>" class="btn btn-outline-secondary">Retour au profil de l'agent</a>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info">Cet agent n'a pas de créneaux disponibles pour le moment ou son planning n'est pas encore configuré.</div>
                                <a href="index.php#nos-agents" class="btn btn-primary">Voir d'autres agents</a>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($action == 'view' && $_SESSION['user_type'] == 'client'): ?>
                    <!-- Client's Appointments View -->
                    <?php if (!empty($user_appointments)): ?>
                        <div class="list-group shadow-sm">
                            <?php foreach ($user_appointments as $rdv): ?>
                                <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 p-3 position-relative">
                                    <div class="d-flex w-100 justify-content-between mb-2">
                                        <h5 class="mb-1 text-primary">
                                            RDV avec <?php echo htmlspecialchars($rdv['agent_nom']); ?> 
                                            (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $rdv['statut']))); ?>)
                                        </h5>
                                        <small class="text-muted">
                                            <?php 
                                            // Attempt to format the date/time. If it fails, display raw.
                                            try {
                                                $date_rdv = new DateTime($rdv['date_heure_rdv']);
                                                echo "Prévu pour le " . $date_rdv->format('d/m/Y \à H:i');
                                            } catch (Exception $e) {
                                                echo "Date/Heure: " . htmlspecialchars($rdv['date_heure_rdv']); // Fallback for invalid date format
                                            }
                                            ?>
                                        </small>
                                    </div>

                                    <?php if (!empty($rdv['propriete_titre'])): ?>
                                        <p class="mb-1"><strong>Propriété :</strong> <?php echo htmlspecialchars($rdv['propriete_titre']); ?></p>
                                        <?php if (!empty($rdv['propriete_adresse'])): ?>
                                            <p class="mb-1"><small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rdv['propriete_adresse']); ?></small></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                         <p class="mb-1"><em>Rendez-vous général (pas de propriété spécifique).</em></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($rdv['notes_client'])): ?>
                                        <p class="mb-1"><strong>Vos notes :</strong> <?php echo nl2br(htmlspecialchars($rdv['notes_client'])); ?></p>
                                    <?php endif; ?>

                                    <?php if ($rdv['statut'] == 'planifie' || $rdv['statut'] == 'confirme_agent'): ?>
                                        <form method="POST" action="rendez-vous.php?action=view" class="mt-2 position-absolute bottom-0 end-0 p-3">
                                            <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                            <input type="hidden" name="slot_id" value="<?php echo htmlspecialchars($rdv['id_disponibilite']); // Important to free up the slot ?>">
                                            <button type="submit" name="cancel_rdv" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                                <i class="fas fa-times-circle"></i> Annuler ce RDV
                                            </button>
                                        </form>
                                    <?php elseif ($rdv['statut'] == 'annule_client' || $rdv['statut'] == 'annule_agent'): ?>
                                        <span class="badge bg-danger">Annulé</span>
                                    <?php elseif ($rdv['statut'] == 'termine'): ?>
                                        <span class="badge bg-secondary">Terminé</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">Vous n'avez aucun rendez-vous à venir.</div>
                    <?php endif; ?>
                    <div class="text-center mt-4">
                         <a href="index.php#nos-agents" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Prendre un nouveau RDV (Chercher un agent)</a>
                    </div>
                <?php elseif ($_SESSION['user_type'] == 'agent' && $action == 'view'): ?>
                    <div class="alert alert-info">Fonctionnalité de gestion du planning agent à implémenter ici ou dans le tableau de bord agent. 
                        <a href="agent_dashboard.php" class="btn btn-info">Aller à mon tableau de bord</a>
                    </div>
                <?php else: ?>
                     <div class="alert alert-info text-center">Pour prendre ou consulter vos rendez-vous, veuillez d'abord <a href="votre-compte.php?redirect_to=rendez-vous.php">vous connecter</a> ou sélectionner un agent depuis <a href="index.php#nos-agents">notre liste d'agents</a> ou une <a href="recherche.php">propriété</a>.</div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </section>

    <?php include 'php/includes/footer.php'; // Standard footer include ?>

    <script src="js/main.js"></script> 
</body>
</html> 