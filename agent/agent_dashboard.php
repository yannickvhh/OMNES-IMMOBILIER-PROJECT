<?php
$path_prefix = '../';
$page_title = "Tableau de Bord Agent | OMNES IMMOBILIER";
require_once '../php/includes/header.php';
require_once '../php/config/db.php';

// Security: Check if user is logged in and is an agent
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'agent') {
    header("location: " . $path_prefix . "votre-compte.php");
    exit;
}

$agent_id = $_SESSION['user_id'];
$agent_profile = null;
$availability_slots = [];
$upcoming_appointments = [];

$success_message = '';
$error_message = '';

if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (!isset($pdo)) {
    $error_message = "Erreur critique: La connexion à la base de données n\'a pas pu être établie.";
} else {
    // Fetch agent profile details
    try {
        $sql_agent_profile = "SELECT u.nom, u.prenom, u.email, ai.specialite, ai.bureau, ai.telephone_pro, ai.photo_filename, ai.cv_filename 
                              FROM Utilisateurs u
                              JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur
                              WHERE u.id = :agent_id";
        $stmt_profile = $pdo->prepare($sql_agent_profile);
        $stmt_profile->execute([':agent_id' => $agent_id]);
        $agent_profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);

        if (!$agent_profile) {
            $_SESSION['logout_message'] = "Erreur profil agent introuvable. Veuillez vous reconnecter.";
            header("location: " . $path_prefix . "php/actions/logout_action.php");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération du profil: " . $e->getMessage();
        error_log("PDO Agent Profile Error: " . $e->getMessage());
    }
}

$photo_path = $path_prefix . "assets/agents/photos/" . htmlspecialchars($agent_profile['photo_filename'] ?? 'default_agent.png');
$cv_path = !empty($agent_profile['cv_filename']) ? $path_prefix . "assets/agents/cvs/" . htmlspecialchars($agent_profile['cv_filename']) : '#';

$days_of_week_fr_select = [ 
    'Lundi' => 'Lundi', 'Mardi' => 'Mardi', 'Mercredi' => 'Mercredi', 
    'Jeudi' => 'Jeudi', 'Vendredi' => 'Vendredi', 'Samedi' => 'Samedi', 'Dimanche' => 'Dimanche'
];

// --- Availability Management --- 

if (isset($pdo)) { // Only proceed if DB connection is available
    // Handle ADD Availability Slot POST request
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_availability'])) {
        $jour_semaine = $_POST['jour_semaine'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];

        if (empty($jour_semaine) || empty($heure_debut) || empty($heure_fin)) {
            $_SESSION['error_message'] = "Veuillez remplir tous les champs pour le créneau.";
        } elseif (strtotime($heure_fin) <= strtotime($heure_debut)) {
            $_SESSION['error_message'] = "L'heure de fin doit être postérieure à l'heure de début.";
        } elseif (!in_array($jour_semaine, array_keys($days_of_week_fr_select))) {
            $_SESSION['error_message'] = "Jour de la semaine invalide.";
        } else {
            try {
                // Precise overlap check
                $sql_check_overlap_precise = "SELECT id FROM DisponibilitesAgents WHERE id_agent = :agent_id AND jour_semaine = :jour_semaine AND heure_debut < :heure_fin AND heure_fin > :heure_debut";
                $stmt_check = $pdo->prepare($sql_check_overlap_precise);
                $stmt_check->execute([':agent_id' => $agent_id, ':jour_semaine' => $jour_semaine, ':heure_fin' => $heure_fin, ':heure_debut' => $heure_debut]);
                if ($stmt_check->fetch()) { // If a row is fetched, there is an overlap
                    $_SESSION['error_message'] = "Ce créneau chevauche un créneau existant.";
                }

                if (!isset($_SESSION['error_message'])) {
                    $sql_add_slot = "INSERT INTO DisponibilitesAgents (id_agent, jour_semaine, heure_debut, heure_fin) VALUES (:agent_id, :jour_semaine, :heure_debut, :heure_fin)";
                    $stmt_add = $pdo->prepare($sql_add_slot);
                    $stmt_add->execute([':agent_id' => $agent_id, ':jour_semaine' => $jour_semaine, ':heure_debut' => $heure_debut, ':heure_fin' => $heure_fin]);
                    $_SESSION['success_message'] = "Créneau de disponibilité ajouté avec succès.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') { // Unique constraint violation (code for MySQL)
                     $_SESSION['error_message'] = "Ce créneau exact existe déjà.";
                } else {
                    $_SESSION['error_message'] = "Erreur lors de l'ajout du créneau: " . $e->getMessage();
                }
                error_log("PDO Add Slot Error: " . $e->getMessage());
            }
        }
        header("Location: agent_dashboard.php");
        exit;
    }

    // Handle DELETE Availability Slot GET request
    if (isset($_GET['action']) && $_GET['action'] == 'delete_slot' && isset($_GET['slot_id'])) {
        $slot_id_to_delete = intval($_GET['slot_id']);
        try {
            $stmt_check_res = $pdo->prepare("SELECT est_reserve FROM DisponibilitesAgents WHERE id = :slot_id AND id_agent = :agent_id");
            $stmt_check_res->execute([':slot_id' => $slot_id_to_delete, ':agent_id' => $agent_id]);
            $slot_data = $stmt_check_res->fetch(PDO::FETCH_ASSOC);

            if (!$slot_data) {
                 $_SESSION['error_message'] = "Créneau non trouvé.";
            } elseif($slot_data['est_reserve'] == 1){
                $_SESSION['error_message'] = "Impossible de supprimer un créneau réservé.";
            } else {
                $sql_delete_slot = "DELETE FROM DisponibilitesAgents WHERE id = :slot_id AND id_agent = :agent_id AND est_reserve = FALSE"; // est_reserve condition is a safeguard
                $stmt_delete = $pdo->prepare($sql_delete_slot);
                $stmt_delete->execute([':slot_id' => $slot_id_to_delete, ':agent_id' => $agent_id]);
                if ($stmt_delete->rowCount() > 0) {
                    $_SESSION['success_message'] = "Créneau de disponibilité supprimé avec succès.";
                } else {
                    $_SESSION['error_message'] = "Créneau non trouvé ou déjà supprimé (ou était réservé).";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de la suppression/vérification du créneau: " . $e->getMessage();
            error_log("PDO Delete Slot Error: " . $e->getMessage());
        }
        header("Location: agent_dashboard.php");
        exit; 
    }

    // Fetch current availability slots for the agent
    try {
        $sql_slots = "SELECT id, jour_semaine, DATE_FORMAT(heure_debut, '%H:%i') as heure_debut_f, DATE_FORMAT(heure_fin, '%H:%i') as heure_fin_f, est_reserve 
                      FROM DisponibilitesAgents 
                      WHERE id_agent = :agent_id 
                      ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), heure_debut_f ASC";
        $stmt_slots = $pdo->prepare($sql_slots);
        $stmt_slots->execute([':agent_id' => $agent_id]);
        $availability_slots_raw = $stmt_slots->fetchAll(PDO::FETCH_ASSOC);
        $availability_slots = []; // Reset before populating
        foreach ($availability_slots_raw as $slot) {
            $availability_slots[$slot['jour_semaine']][] = $slot;
        }
    } catch (PDOException $e) {
        $error_message_slots = " Erreur récupération disponibilités: " . $e->getMessage();
        error_log("PDO Fetch Slots Error: " . $e->getMessage());
        if(empty($error_message)) $error_message = $error_message_slots;
    }

    // Handle Update Appointment Status POST request
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_appointment_status'])) {
        $rdv_id_to_update = intval($_POST['rdv_id']);
        $new_status = $_POST['new_status']; 
        $slot_id_to_free = isset($_POST['slot_id']) && !empty($_POST['slot_id']) ? intval($_POST['slot_id']) : null;
        $allowed_agent_statuses = ['termine', 'annule_agent'];

        if (in_array($new_status, $allowed_agent_statuses)) {
            try {
                $pdo->beginTransaction();
                $sql_update_rdv = "UPDATE RendezVous SET statut = :new_status WHERE id = :rdv_id AND id_agent = :agent_id";
                $stmt_update_rdv = $pdo->prepare($sql_update_rdv);
                $stmt_update_rdv->execute([':new_status' => $new_status, ':rdv_id' => $rdv_id_to_update, ':agent_id' => $agent_id]);
                
                if ($stmt_update_rdv->rowCount() == 0) {
                    throw new Exception("RDV non trouvé ou non autorisé à modifier.");
                }

                if ($new_status == 'annule_agent' && $slot_id_to_free) {
                    $sql_free_slot = "UPDATE DisponibilitesAgents SET est_reserve = FALSE WHERE id = :slot_id AND id_agent = :agent_id";
                    $stmt_free_slot = $pdo->prepare($sql_free_slot);
                    $stmt_free_slot->execute([':slot_id' => $slot_id_to_free, ':agent_id' => $agent_id]);
                    // Log error if it fails but don't necessarily fail transaction if RDV status updated
                    if ($stmt_free_slot->rowCount() == 0 && $pdo->errorCode() !== '00000') { // Check if it actually failed vs. slot already free
                         error_log("Agent Dashboard: Failed to free slot $slot_id_to_free for agent $agent_id after RDV cancellation. Error: " . implode(", ", $pdo->errorInfo()));
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = "Statut du rendez-vous mis à jour.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['error_message'] = $e->getMessage();
                error_log("PDO Update RDV Status Error: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Statut de mise à jour invalide.";
        }
        header("Location: agent_dashboard.php");
        exit;
    }

    // Fetch upcoming appointments for the agent
    try {
        $sql_appointments = "SELECT rdv.id, rdv.date_heure_rdv, rdv.statut, rdv.notes_client, rdv.lieu_rdv, rdv.id_disponibilite, 
                                 uc.nom as client_nom, uc.prenom as client_prenom, uc.email as client_email, 
                                 p.titre as propriete_titre, p.adresse as propriete_adresse, p.id as propriete_id
                          FROM RendezVous rdv
                          JOIN Utilisateurs uc ON rdv.id_client = uc.id
                          LEFT JOIN Proprietes p ON rdv.id_propriete = p.id
                          WHERE rdv.id_agent = :agent_id AND rdv.statut IN ('planifie', 'confirme')
                          ORDER BY rdv.date_heure_rdv ASC";
        $stmt_app = $pdo->prepare($sql_appointments);
        $stmt_app->execute([':agent_id' => $agent_id]);
        $upcoming_appointments = $stmt_app->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message_app = " Erreur récupération rendez-vous: " . $e->getMessage();
        error_log("PDO Fetch Appointments Error: " . $e->getMessage());
        if(empty($error_message)) $error_message = $error_message_app;
    }
}

$rdv_status_display_classes = [
    'planifie' => 'bg-primary',
    'confirme' => 'bg-success',
    'annule_client' => 'bg-warning text-dark',
    'annule_agent' => 'bg-danger',
    'termine' => 'bg-secondary'
];

?>

<div class="container mt-5 mb-5">
    <div class="section-title">
        <h2>Tableau de Bord Agent</h2>
        <p>Gérez votre profil, vos disponibilités et vos rendez-vous.</p>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($agent_profile): ?>
    <div class="row mb-4">
        <div class="col-md-4 text-center">
            <img src="<?php echo $photo_path; ?>" alt="Photo de <?php echo htmlspecialchars($agent_profile['prenom'] . ' ' . $agent_profile['nom']); ?>" class="img-fluid rounded-circle shadow-sm mb-3" style="width: 150px; height: 150px; object-fit: cover;">
            <h4><?php echo htmlspecialchars($agent_profile['prenom'] . ' ' . $agent_profile['nom']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($agent_profile['specialite'] ?? 'Agent Immobilier'); ?></p>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Informations Professionnelles</h5>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($agent_profile['email']); ?></p>
                    <p><strong>Téléphone Pro:</strong> <?php echo htmlspecialchars($agent_profile['telephone_pro'] ?? 'N/A'); ?></p>
                    <p><strong>Bureau:</strong> <?php echo htmlspecialchars($agent_profile['bureau'] ?? 'N/A'); ?></p>
                    <?php if (!empty($agent_profile['cv_filename'])): ?>
                        <p><strong>CV:</strong> <a href="<?php echo $cv_path; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-pdf me-1"></i> Voir CV</a></p>
                    <?php endif; ?>
                    <a href="<?php echo $path_prefix; ?>chat.php" class="btn btn-primary mt-2"><i class="fas fa-comments me-2"></i>Consulter votre Messagerie</a>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row mb-4">
        <!-- Availability Management -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Gérer mes Disponibilités Hebdomadaires</h4>
                </div>
                <div class="card-body">
                    <form action="agent_dashboard.php" method="POST" class="mb-4 p-3 border rounded bg-light">
                        <h5 class="mb-3">Ajouter un nouveau créneau</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="jour_semaine" class="form-label">Jour</label>
                                <select class="form-select" id="jour_semaine" name="jour_semaine" required>
                                    <?php foreach ($days_of_week_fr_select as $day_val => $day_label): ?>
                                        <option value="<?php echo $day_val; ?>"><?php echo $day_label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="heure_debut" class="form-label">De</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            <div class="col-md-3">
                                <label for="heure_fin" class="form-label">À</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>
                            <div class="col-md-2 align-self-end">
                                <button type="submit" name="add_availability" class="btn btn-success w-100"><i class="fas fa-plus-circle"></i> Ajouter</button>
                            </div>
                        </div>
                    </form>

                    <h5 class="mt-4 mb-3">Mes créneaux actuels :</h5>
                    <?php if (empty($availability_slots)): ?>
                        <p class="text-center text-muted fst-italic">Aucun créneau de disponibilité défini pour le moment.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($days_of_week_fr_select as $day_key => $day_name): ?>
                                <?php if (!empty($availability_slots[$day_key])): ?>
                                    <h6 class="mt-3 text-primary"><?php echo $day_name; ?></h6>
                                    <?php foreach ($availability_slots[$day_key] as $slot): ?>
                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $slot['est_reserve'] ? 'list-group-item-light text-muted' : ''; ?>">
                                            <span>
                                                <i class="far fa-clock me-2"></i> <?php echo htmlspecialchars($slot['heure_debut_f']); ?> - <?php echo htmlspecialchars($slot['heure_fin_f']); ?>
                                                <?php if ($slot['est_reserve']): ?>
                                                    <span class="badge bg-warning ms-2"><i class="fas fa-lock me-1"></i>Réservé</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!$slot['est_reserve']): ?>
                                                <a href="agent_dashboard.php?action=delete_slot&slot_id=<?php echo $slot['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?');"><i class="fas fa-trash-alt"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Mes Rendez-vous à Venir</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <p class="text-center text-muted fst-italic">Vous n'avez aucun rendez-vous à venir pour le moment.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date & Heure</th>
                                        <th>Client</th>
                                        <th>Contact Client</th>
                                        <th>Propriété / Lieu</th>
                                        <th>Statut</th>
                                        <th>Notes Client</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($appointment['date_heure_rdv']))); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['client_prenom'] . ' ' . $appointment['client_nom']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['client_email']); ?></td>
                                            <td>
                                                <?php if (!empty($appointment['propriete_titre'])): ?>
                                                    <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $appointment['propriete_id']; ?>" target="_blank"><?php echo htmlspecialchars($appointment['propriete_titre']); ?></a><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['propriete_adresse']); ?></small>
                                                <?php elseif (!empty($appointment['lieu_rdv'])): ?>
                                                    <?php echo htmlspecialchars($appointment['lieu_rdv']); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $rdv_status_display_classes[$appointment['statut']] ?? 'bg-secondary'; ?> p-2"><?php echo htmlspecialchars(ucfirst(str_replace("_", " ", $appointment['statut']))); ?></span>
                                            </td>
                                            <td><small><?php echo !empty($appointment['notes_client']) ? nl2br(htmlspecialchars($appointment['notes_client'])) : 'Aucune'; ?></small></td>
                                            <td>
                                                <?php if ($appointment['statut'] === 'planifie' || $appointment['statut'] === 'confirme'): ?>
                                                    <form action="agent_dashboard.php" method="POST" class="d-inline-block mb-1">
                                                        <input type="hidden" name="rdv_id" value="<?php echo $appointment['id']; ?>">
                                                        <input type="hidden" name="slot_id" value="<?php echo htmlspecialchars($appointment['id_disponibilite'] ?? ''); ?>">
                                                        <button type="submit" name="update_appointment_status" value="termine" class="btn btn-sm btn-outline-success" onclick="return confirm('Marquer ce RDV comme terminé ?');"><i class="fas fa-check-circle"></i> Terminé</button>
                                                    </form>
                                                    <form action="agent_dashboard.php" method="POST" class="d-inline-block">
                                                        <input type="hidden" name="rdv_id" value="<?php echo $appointment['id']; ?>">
                                                        <input type="hidden" name="slot_id" value="<?php echo htmlspecialchars($appointment['id_disponibilite'] ?? ''); ?>">
                                                        <button type="submit" name="update_appointment_status" value="annule_agent" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce RDV ?');"><i class="fas fa-times-circle"></i> Annuler</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="alert alert-danger">Impossible de charger les informations de l'agent. Veuillez contacter l'administrateur.</div>
    <?php endif; ?>
</div>

<?php require_once '../php/includes/footer.php'; ?>