<?php 
$page_title = "Rendez-vous | OMNES IMMOBILIER";
require_once 'php/includes/header.php'; 
require_once 'php/config/db.php'; // Provides $pdo

// This page will have different views based on user type and whether they are taking an RDV or viewing existing ones.

$action = isset($_GET['action']) ? $_GET['action'] : 'view'; // 'view', 'take_rdv'
$agent_id_for_rdv = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
$property_id_for_rdv = isset($_GET['property_id']) ? intval($_GET['property_id']) : null;

$user_appointments = [];
$agent_schedule = [];
$agent_details = null;
$error_page_message = ''; // For general errors like DB connection

if (!isset($pdo)) {
    $error_page_message = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
} elseif ($is_logged_in) {
    try {
        if ($_SESSION['user_type'] == 'client' && $action == 'view') {
            // Fetch client's appointments
            $sql_client_rdv = "SELECT rdv.*, p.titre as nom_propriete, CONCAT(u_agent.prenom, ' ', u_agent.nom) as nom_agent, 
                                     p.adresse as adresse_propriete, p.ville as ville_propriete, p.code_postal as cp_propriete
                               FROM RendezVous rdv
                               LEFT JOIN Proprietes p ON rdv.id_propriete = p.id
                               JOIN AgentsImmobiliers ai ON rdv.id_agent = ai.id_utilisateur
                               JOIN Utilisateurs u_agent ON ai.id_utilisateur = u_agent.id
                               WHERE rdv.id_client = :user_id AND rdv.statut NOT IN ('annule_client', 'annule_agent', 'termine')
                               ORDER BY rdv.date_heure_rdv ASC";
            $stmt = $pdo->prepare($sql_client_rdv);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($action == 'take_rdv' && $agent_id_for_rdv) {
            // Fetch agent details for taking an RDV
            $sql_agent = "SELECT u.id, u.nom, u.prenom, u.email, ai.specialite, ai.photo_filename 
                          FROM Utilisateurs u 
                          JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur 
                          WHERE u.id = :agent_id AND u.type_compte = 'agent'";
            $stmt_agent = $pdo->prepare($sql_agent);
            $stmt_agent->execute([':agent_id' => $agent_id_for_rdv]);
            $agent_details = $stmt_agent->fetch(PDO::FETCH_ASSOC);

            if ($agent_details) {
                // Fetch agent's available slots for the upcoming week (simplified)
                // This needs more complex logic for a real calendar with specific dates
                $sql_avail = "SELECT id, jour_semaine, DATE_FORMAT(heure_debut, '%H:%i') as heure_debut, DATE_FORMAT(heure_fin, '%H:%i') as heure_fin, est_reserve 
                              FROM DisponibilitesAgents 
                              WHERE id_agent = :agent_id AND est_reserve = FALSE 
                              ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), heure_debut";
                $stmt_avail = $pdo->prepare($sql_avail);
                $stmt_avail->execute([':agent_id' => $agent_id_for_rdv]);
                while($slot = $stmt_avail->fetch(PDO::FETCH_ASSOC)){
                    $agent_schedule[$slot['jour_semaine']][] = $slot;
                }
            } else {
                $_SESSION['rdv_error'] = "Agent non trouvé ou invalide pour la prise de RDV.";
            }
        } elseif ($_SESSION['user_type'] == 'agent' && $action == 'view') {
            // Agent viewing their own schedule - Placeholder
        }
    } catch (PDOException $e) {
        $error_page_message = "Erreur de base de données: " . htmlspecialchars($e->getMessage());
        error_log("PDO Error in rendez-vous.php (data fetching): " . $e->getMessage());
    }
}

// Handle RDV confirmation (POST request after selecting a slot)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_rdv'])) {
    if (!isset($pdo)) {
        $_SESSION['rdv_error'] = "Erreur critique: Connexion DB perdue avant confirmation RDV.";
    } elseif ($is_logged_in && $_SESSION['user_type'] == 'client') {
        $slot_id = isset($_POST['slot_id']) ? intval($_POST['slot_id']) : null;
        $id_agent_rdv = isset($_POST['id_agent']) ? intval($_POST['id_agent']) : null;
        $id_propriete_rdv = isset($_POST['id_propriete']) ? (empty($_POST['id_propriete']) ? null : intval($_POST['id_propriete'])) : null;
        $notes_client = isset($_POST['notes_client']) ? trim($_POST['notes_client']) : null;
        // More validation needed here

        if ($slot_id && $id_agent_rdv) {
            try {
                $pdo->beginTransaction();

                $stmt_slot_details = $pdo->prepare("SELECT jour_semaine, heure_debut FROM DisponibilitesAgents WHERE id = :slot_id AND id_agent = :agent_id AND est_reserve = FALSE FOR UPDATE");
                $stmt_slot_details->execute([':slot_id' => $slot_id, ':agent_id' => $id_agent_rdv]);
                $slot_info = $stmt_slot_details->fetch(PDO::FETCH_ASSOC);

                if (!$slot_info) {
                    throw new Exception("Ce créneau n'est plus disponible ou invalide.");
                }

                $stmt_update_slot = $pdo->prepare("UPDATE DisponibilitesAgents SET est_reserve = TRUE WHERE id = :slot_id");
                $stmt_update_slot->execute([':slot_id' => $slot_id]);
                if ($stmt_update_slot->rowCount() == 0) {
                    throw new Exception("Impossible de réserver le créneau. Il a peut-être été pris.");
                }
                
                // Calculate the actual date and time for the RDV
                $jour_rdv_en = str_replace(
                    ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'],
                    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    $slot_info['jour_semaine']
                );
                $date_heure_rdv_calc_str = "next {$jour_rdv_en} {$slot_info['heure_debut']}";
                $date_heure_rdv_obj = new DateTime($date_heure_rdv_calc_str);
                $date_heure_rdv_sql = $date_heure_rdv_obj->format('Y-m-d H:i:s');

                $sql_insert_rdv = "INSERT INTO RendezVous (id_client, id_agent, id_propriete, id_disponibilite, date_heure_rdv, notes_client, statut) VALUES (:id_client, :id_agent, :id_propriete, :id_disponibilite, :date_heure_rdv, :notes_client, 'planifie')";
                $stmt_insert_rdv = $pdo->prepare($sql_insert_rdv);
                $params_insert = [
                    ':id_client' => $_SESSION['user_id'],
                    ':id_agent' => $id_agent_rdv,
                    ':id_propriete' => $id_propriete_rdv,
                    ':id_disponibilite' => $slot_id,
                    ':date_heure_rdv' => $date_heure_rdv_sql,
                    ':notes_client' => $notes_client
                ];
                if (!$stmt_insert_rdv->execute($params_insert)) {
                     throw new Exception("Erreur lors de la création du rendez-vous.");
                }
                
                $pdo->commit();
                $_SESSION['rdv_success'] = "Votre rendez-vous a été confirmé pour le " . $date_heure_rdv_obj->format('d/m/Y à H:i') . ". Vous recevrez une notification.";
                header("Location: rendez-vous.php?action=view");
                exit();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['rdv_error'] = "Erreur : " . $e->getMessage();
                error_log("PDO RDV Confirm Error: " . $e->getMessage());
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        } else {
            $_SESSION['rdv_error'] = "Informations manquantes pour confirmer le RDV.";
        }
    } else {
        $_SESSION['rdv_error'] = "Vous devez être connecté en tant que client pour prendre un rendez-vous.";
        if (!isset($pdo)) {
            $_SESSION['rdv_error'] = "Erreur critique: Connexion DB perdue avant confirmation RDV.";
        }
        if (!isset($is_logged_in)) {
            header("Location: votre-compte.php");
        }
        // else if user is agent/admin, they can't book, so error is fine, stay on page or redirect to their dashboard?
        // For now, if logged in but not client, error message will show on current page if they somehow got here.
    }
    if (isset($_SESSION['rdv_error'])) { // Ensure redirect if error was set in non-client case
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Handle RDV cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_rdv'])) {
    if (!isset($pdo)) {
         $_SESSION['rdv_cancel_error'] = "Erreur critique: Connexion DB perdue avant annulation RDV.";
    } elseif ($is_logged_in && $_SESSION['user_type'] == 'client') {
        $rdv_id_to_cancel = intval($_POST['rdv_id']);
        $slot_id_to_free = isset($_POST['slot_id']) ? (empty($_POST['slot_id']) ? null : intval($_POST['slot_id'])) : null;

        try {
            $pdo->beginTransaction();
            $stmt_cancel = $pdo->prepare("UPDATE RendezVous SET statut = 'annule_client' WHERE id = :rdv_id AND id_client = :client_id");
            $stmt_cancel->execute([':rdv_id' => $rdv_id_to_cancel, ':client_id' => $_SESSION['user_id']]);
            
            if($stmt_cancel->rowCount() == 0){
                throw new Exception("Rendez-vous non trouvé ou non autorisé à annuler.");
            }

            if ($slot_id_to_free) {
                $stmt_free_slot = $pdo->prepare("UPDATE DisponibilitesAgents SET est_reserve = FALSE WHERE id = :slot_id");
                $stmt_free_slot->execute([':slot_id' => $slot_id_to_free]);
            }
            $pdo->commit();
            $_SESSION['rdv_cancel_success'] = "Votre rendez-vous a été annulé.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['rdv_cancel_error'] = "Erreur lors de l'annulation: " . $e->getMessage();
            error_log("PDO RDV Cancel Error: " . $e->getMessage());
        }
    } else {
        $_SESSION['rdv_cancel_error'] = "Action non autorisée.";
    }
    header("Location: rendez-vous.php?action=view");
    exit();
}

$days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

?>

    <!-- Page Title -->
    <section class="section py-5">
        <div class="container">
            <div class="section-title text-center">
                <h2><?php 
                    if ($action == 'take_rdv' && $agent_details) {
                        echo "Prendre Rendez-vous avec ".htmlspecialchars($agent_details['prenom'] . ' ' . $agent_details['nom']);
                    } elseif ($is_logged_in && $_SESSION['user_type'] == 'client') {
                        echo "Mes Rendez-vous";
                    } elseif ($is_logged_in && $_SESSION['user_type'] == 'agent') {
                        echo "Mon Planning de Rendez-vous";
                    } else {
                        echo "Rendez-vous";
                    }
                ?></h2>
                <p><?php 
                    if ($action == 'take_rdv' && $agent_details) {
                        echo "Choisissez un créneau disponible dans le calendrier de l'agent.";
                    } elseif ($is_logged_in && $_SESSION['user_type'] == 'client') {
                        echo "Consultez et gérez vos rendez-vous confirmés.";
                    } else {
                        echo "Connectez-vous pour gérer vos rendez-vous ou prendre un nouveau rendez-vous.";
                    }
                ?></p>
                 <?php if (!empty($error_page_message)): ?>
                    <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($error_page_message); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section rdv-content py-5 bg-light">
        <div class="container">
            <?php if (isset($_SESSION['rdv_success'])): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($_SESSION['rdv_success']); unset($_SESSION['rdv_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['rdv_error'])): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_SESSION['rdv_error']); unset($_SESSION['rdv_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['rdv_cancel_success'])): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($_SESSION['rdv_cancel_success']); unset($_SESSION['rdv_cancel_success']); ?></div>
            <?php endif; ?>
             <?php if (isset($_SESSION['rdv_cancel_error'])): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_SESSION['rdv_cancel_error']); unset($_SESSION['rdv_cancel_error']); ?></div>
            <?php endif; ?>

            <?php if (!isset($is_logged_in) && $action != 'take_rdv' && empty($error_page_message)): ?>
                <div class="alert alert-warning text-center">Veuillez <a href="votre-compte.php">vous connecter</a> pour voir vos rendez-vous ou en prendre un nouveau.</div>
            <?php endif; ?>

            <?php if ($action == 'take_rdv' && $agent_details && empty($error_page_message)): ?>
                <!-- Calendar/Slot selection for taking RDV -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0">Disponibilités de <?php echo htmlspecialchars($agent_details['prenom'] . ' ' . $agent_details['nom']); ?></h4>
                        <?php if ($property_id_for_rdv): ?>
                            <?php 
                            // Fetch property title for display
                            $property_title_for_rdv = 'Propriété inconnue';
                            if (isset($pdo)) {
                                try {
                                    $stmt_prop_title = $pdo->prepare("SELECT titre FROM Proprietes WHERE id = :prop_id");
                                    $stmt_prop_title->execute([':prop_id' => $property_id_for_rdv]);
                                    $prop_title_res = $stmt_prop_title->fetch(PDO::FETCH_ASSOC);
                                    if ($prop_title_res) $property_title_for_rdv = $prop_title_res['titre'];
                                } catch (PDOException $e) { /* Log or handle error if necessary, but don't break page */ }
                            }
                            ?>
                            <p class="text-muted mb-0">Pour la propriété : <?php echo htmlspecialchars($property_title_for_rdv); ?> (#<?php echo $property_id_for_rdv; ?>). <a href="propriete_details.php?id=<?php echo $property_id_for_rdv; ?>">Voir détails</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agent_schedule)): ?>
                            <p class="text-center">Cet agent n'a pas de créneaux disponibles pour le moment ou son planning n'est pas encore configuré.</p>
                        <?php else: ?>
                            <p>Sélectionnez un créneau :</p>
                            <form method="POST" action="rendez-vous.php?action=take_rdv&agent_id=<?php echo $agent_id_for_rdv; echo $property_id_for_rdv ? '&property_id='.$property_id_for_rdv : ''; ?>">
                                <input type="hidden" name="id_agent" value="<?php echo $agent_id_for_rdv; ?>">
                                <?php if ($property_id_for_rdv): ?>
                                    <input type="hidden" name="id_propriete" value="<?php echo $property_id_for_rdv; ?>">
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center calendar-table">
                                        <thead>
                                            <tr>
                                                <?php foreach ($days_of_week as $day): ?>
                                                    <th><?php echo $day; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr> 
                                            <?php foreach ($days_of_week as $day): ?>
                                                <td>
                                                    <?php if (isset($agent_schedule[$day])): ?>
                                                        <?php foreach ($agent_schedule[$day] as $slot): ?>
                                                            <button type="submit" name="slot_id" value="<?php echo $slot['id']; ?>" class="btn btn-outline-success btn-sm d-block mb-1 <?php echo $slot['est_reserve'] ? 'disabled' : ''; ?>" <?php echo ($slot['est_reserve'] || !isset($is_logged_in) || $_SESSION['user_type'] != 'client') ? 'disabled' : ''; ?>>
                                                                <?php echo $slot['heure_debut']; ?> - <?php echo $slot['heure_fin']; ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <small class="text-muted">Aucun</small>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mb-3">
                                     <label for="notes_client" class="form-label">Notes pour l'agent (optionnel) :</label>
                                     <textarea class="form-control" id="notes_client" name="notes_client" rows="2" <?php echo (!isset($is_logged_in) || $_SESSION['user_type'] != 'client') ? 'disabled' : ''; ?>></textarea>
                                 </div>
                                <input type="hidden" name="confirm_rdv" value="1">
                                <p class="form-text text-muted">En cliquant sur un créneau, vous confirmerez votre demande de rendez-vous.</p>
                                 <?php if (!isset($is_logged_in)): ?>
                                    <div class="alert alert-info">Vous devez être <a href="votre-compte.php?redirect_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">connecté en tant que client</a> pour prendre un rendez-vous.</div>
                                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] != 'client'): ?>
                                     <div class="alert alert-warning">Seuls les clients peuvent prendre des rendez-vous.</div>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (isset($is_logged_in) && $_SESSION['user_type'] == 'client' && $action == 'view' && empty($error_page_message)): ?>
                <!-- Client's existing appointments -->
                <?php if (empty($user_appointments)): ?>
                    <p class="text-center">Vous n'avez aucun rendez-vous à venir.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($user_appointments as $rdv): ?>
                            <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 shadow-sm">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">RDV avec <?php echo htmlspecialchars($rdv['nom_agent']); ?></h5>
                                    <small class="text-muted"><?php echo date("d/m/Y H:i", strtotime($rdv['date_heure_rdv'])); ?></small>
                                </div>
                                <?php if (!empty($rdv['nom_propriete'])): ?>
                                <p class="mb-1"><strong>Propriété :</strong> <?php echo htmlspecialchars($rdv['nom_propriete']); ?> </p>
                                 <p class="mb-1"><small><strong>Lieu :</strong> <?php echo htmlspecialchars($rdv['adresse_propriete'] . ', ' . $rdv['cp_propriete'] . ' ' . $rdv['ville_propriete']); ?></small></p>
                                <?php elseif(!empty($rdv['lieu_rdv'])): ?>
                                 <p class="mb-1"><strong>Lieu :</strong> <?php echo htmlspecialchars($rdv['lieu_rdv']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><strong>Statut :</strong> <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ', $rdv['statut']))); ?></span></p>
                                <?php if(!empty($rdv['notes_client'])): ?>
                                    <p class="mb-1"><small><strong>Vos notes :</strong> <?php echo htmlspecialchars($rdv['notes_client']); ?></small></p>
                                <?php endif; ?>
                                <?php if(!empty($rdv['informations_supplementaires'])): ?>
                                    <p class="mb-1"><small><strong>Infos suppl. :</strong> <?php echo htmlspecialchars($rdv['informations_supplementaires']); ?></small></p>
                                <?php endif; ?>
                                <?php if ($rdv['statut'] == 'planifie' || $rdv['statut'] == 'confirme'): ?>
                                <form method="POST" action="rendez-vous.php" class="mt-2" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <input type="hidden" name="slot_id" value="<?php echo $rdv['id_disponibilite']; ?>">
                                    <button type="submit" name="cancel_rdv" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-times-circle"></i> Annuler ce RDV
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-4">
                     <a href="recherche.php" class="btn btn-primary">Prendre un nouveau RDV (Chercher un agent/propriété)</a>
                </div>

            <?php elseif (isset($is_logged_in) && $_SESSION['user_type'] == 'agent' && $action == 'view' && empty($error_page_message)): ?>
                <!-- Agent's view of their schedule (Placeholder) -->
                <p class="text-center">Fonctionnalité de gestion du planning agent à implémenter ici ou dans le tableau de bord agent.</p>
                <p class="text-center"><a href="agent/agent_dashboard.php" class="btn btn-info">Aller à mon tableau de bord</a></p>
            <?php elseif (empty($error_page_message)): // Fallback if no other condition met and no critical error ?>
                <div class="alert alert-info text-center">Le contenu demandé n'est pas disponible ou une action est requise.</div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'php/includes/footer.php'; ?> 