<?php
$path_prefix = '../';
$page_title = "Tableau de Bord Agent | OMNES IMMOBILIER";
require_once '../php/includes/header.php';
require_once '../php/config/db.php';

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

if (isset($pdo)) {
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
                $sql_check_overlap_precise = "SELECT id FROM DisponibilitesAgents WHERE id_agent = :agent_id AND jour_semaine = :jour_semaine AND heure_debut < :heure_fin AND heure_fin > :heure_debut";
                $stmt_check = $pdo->prepare($sql_check_overlap_precise);
                $stmt_check->execute([':agent_id' => $agent_id, ':jour_semaine' => $jour_semaine, ':heure_fin' => $heure_fin, ':heure_debut' => $heure_debut]);
                if ($stmt_check->fetch()) {
                    $_SESSION['error_message'] = "Ce créneau chevauche un créneau existant.";
                }

                if (!isset($_SESSION['error_message'])) {
                    $sql_add_slot = "INSERT INTO DisponibilitesAgents (id_agent, jour_semaine, heure_debut, heure_fin) VALUES (:agent_id, :jour_semaine, :heure_debut, :heure_fin)";
                    $stmt_add = $pdo->prepare($sql_add_slot);
                    $stmt_add->execute([':agent_id' => $agent_id, ':jour_semaine' => $jour_semaine, ':heure_debut' => $heure_debut, ':heure_fin' => $heure_fin]);
                    $_SESSION['success_message'] = "Créneau de disponibilité ajouté avec succès.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
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
                $sql_delete_slot = "DELETE FROM DisponibilitesAgents WHERE id = :slot_id AND id_agent = :agent_id AND est_reserve = FALSE";
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

    try {
        $sql_slots = "SELECT id, jour_semaine, DATE_FORMAT(heure_debut, '%H:%i') as heure_debut_f, DATE_FORMAT(heure_fin, '%H:%i') as heure_fin_f, est_reserve 
                      FROM DisponibilitesAgents 
                      WHERE id_agent = :agent_id 
                      ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), heure_debut_f ASC";
        $stmt_slots = $pdo->prepare($sql_slots);
        $stmt_slots->execute([':agent_id' => $agent_id]);
        $availability_slots_raw = $stmt_slots->fetchAll(PDO::FETCH_ASSOC);
        $availability_slots = [];
        foreach ($availability_slots_raw as $slot) {
            $availability_slots[$slot['jour_semaine']][] = $slot;
        }
    } catch (PDOException $e) {
        $error_message_slots = " Erreur récupération disponibilités: " . $e->getMessage();
        error_log("PDO Fetch Slots Error: " . $e->getMessage());
        if(empty($error_message)) $error_message = $error_message_slots;
    }

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
                    if ($stmt_free_slot->rowCount() == 0 && $pdo->errorCode() !== '00000') { 
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
            <p class="text-muted"><?php echo htmlspecialchars($agent_profile['specialite'] ?? 'Spécialité non définie'); ?></p>
            <a href="<?php echo $path_prefix; ?>agent_profile_edit.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Modifier mon profil</a>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Mes Informations</h5>
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($agent_profile['email']); ?></p>
                    <p><strong>Téléphone Pro:</strong> <?php echo htmlspecialchars($agent_profile['telephone_pro'] ?? 'Non défini'); ?></p>
                    <p><strong>Bureau:</strong> <?php echo htmlspecialchars($agent_profile['bureau'] ?? 'Non défini'); ?></p>
                    <?php if (!empty($agent_profile['cv_filename'])): ?>
                        <p><strong>CV:</strong> <a href="<?php echo $cv_path; ?>" target="_blank" class="btn btn-link p-0"><i class="fas fa-file-pdf"></i> Télécharger mon CV</a></p>
                    <?php else: ?>
                        <p><strong>CV:</strong> Non fourni</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Gérer mes Disponibilités Hebdomadaires</h5>
                </div>
                <div class="card-body">
                    <form action="agent_dashboard.php" method="POST" class="row g-3 align-items-end mb-4">
                        <div class="col-md-3">
                            <label for="jour_semaine" class="form-label">Jour</label>
                            <select name="jour_semaine" id="jour_semaine" class="form-select" required>
                                <?php foreach($days_of_week_fr_select as $val => $text): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $text; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="heure_debut" class="form-label">De</label>
                            <input type="time" name="heure_debut" id="heure_debut" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="heure_fin" class="form-label">À</label>
                            <input type="time" name="heure_fin" id="heure_fin" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="add_availability" class="btn btn-primary w-100"><i class="fas fa-plus-circle"></i> Ajouter Créneau</button>
                        </div>
                    </form>

                    <?php if (!empty($availability_slots)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jour</th>
                                        <th>Heure Début</th>
                                        <th>Heure Fin</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($days_of_week_fr_select as $day_key => $day_name): ?>
                                    <?php if (!empty($availability_slots[$day_key])): ?>
                                        <?php foreach ($availability_slots[$day_key] as $index => $slot): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo count($availability_slots[$day_key]); ?>" class="align-middle fw-bold"><?php echo htmlspecialchars($day_name); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($slot['heure_debut_f']); ?></td>
                                            <td><?php echo htmlspecialchars($slot['heure_fin_f']); ?></td>
                                            <td>
                                                <?php if ($slot['est_reserve']): ?>
                                                    <span class="badge bg-warning text-dark">Réservé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Libre</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$slot['est_reserve']): ?>
                                                    <a href="agent_dashboard.php?action=delete_slot&slot_id=<?php echo $slot['id']; ?>"
                                                       class="btn btn-danger btn-sm" 
                                                       title="Supprimer ce créneau"
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce créneau de disponibilité ? Cette action ne peut pas être annulée.');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Créneau réservé, ne peut être supprimé"><i class="fas fa-lock"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted fst-italic">Vous n'avez pas encore défini de créneaux de disponibilité.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Mes Rendez-vous à Venir (Statut: Planifié ou Confirmé)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date et Heure</th>
                                        <th>Client</th>
                                        <th>Propriété (si applicable)</th>
                                        <th>Lieu</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $rdv): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date("d/m/Y à H:i", strtotime($rdv['date_heure_rdv']))); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($rdv['client_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($rdv['propriete_titre'])): ?>
                                                <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $rdv['propriete_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($rdv['propriete_titre']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($rdv['propriete_adresse']); ?></small>
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($rdv['lieu_rdv'] ?? 'Non précisé'); ?></td>
                                        <td>
                                             <span class="badge <?php echo $rdv_status_display_classes[$rdv['statut']] ?? 'bg-secondary'; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $rdv['statut']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <form action="agent_dashboard.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                                    <input type="hidden" name="slot_id" value="<?php echo $rdv['id_disponibilite'] ?? ''; ?>"> 
                                                    <button type="submit" name="update_appointment_status" value="termine" class="btn btn-success btn-sm" title="Marquer comme Terminé" onclick="return confirm('Marquer ce RDV comme terminé ?');"><i class="fas fa-check-circle"></i></button>
                                                    <button type="submit" name="update_appointment_status" value="annule_agent" class="btn btn-danger btn-sm" title="Annuler le RDV" onclick="return confirm('Annuler ce RDV ? Cela pourrait libérer le créneau associé.');"><i class="fas fa-times-circle"></i></button>
                                                </form>
                                                <a href="<?php echo $path_prefix; ?>chat.php?contact_id=<?php echo $rdv['id_client']; ?>&contact_name=<?php echo urlencode($rdv['client_prenom'] . ' ' . $rdv['client_nom']); ?>&rdv_id=<?php echo $rdv['id']; ?>" class="btn btn-info btn-sm" title="Contacter le client pour ce RDV"><i class="fas fa-comments"></i></a>
                                            </div>
                                            <?php if (!empty($rdv['notes_client'])): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-1 w-100" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($rdv['notes_client']); ?>">
                                                    <i class="fas fa-info-circle"></i> Notes Client
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted fst-italic">Aucun rendez-vous à venir pour le moment.</p>
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
