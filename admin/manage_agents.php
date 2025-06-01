<?php
$path_prefix = '../'; 
$page_title = "Gérer les Agents | Admin OMNES IMMOBILIER";
require_once '../php/includes/header.php';

require_once '../php/config/db.php'; 


if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: " . $path_prefix . "votre-compte.php");
    exit;
}


$agents_list = [];
$form_data = [
    'id_utilisateur' => null, 
    'nom' => '', 'prenom' => '', 'email' => '', 'mot_de_passe' => '',
    'specialite' => '', 'bureau' => '', 'telephone_pro' => '',
    'cv_filename' => '', 'photo_filename' => '',
    'existing_photo_filename' => '', 'existing_cv_filename' => '' 
];
$edit_mode = false;
$success_message = '';
$error_message = '';

$photo_upload_dir = "../assets/agents/photos/";
$cv_upload_dir = "../assets/agents/cvs/";

if (!is_dir($photo_upload_dir)) { mkdir($photo_upload_dir, 0777, true); }
if (!is_dir($cv_upload_dir)) { mkdir($cv_upload_dir, 0777, true); }


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_data['id_utilisateur'] = isset($_POST['id_utilisateur']) ? intval($_POST['id_utilisateur']) : null;
    $form_data['nom'] = trim($_POST['nom']);
    $form_data['prenom'] = trim($_POST['prenom']);
    $form_data['email'] = trim($_POST['email']);
    $form_data['mot_de_passe'] = (!$form_data['id_utilisateur'] && !empty($_POST['mot_de_passe'])) ? $_POST['mot_de_passe'] : ''; 

    $form_data['specialite'] = trim($_POST['specialite']);
    $form_data['bureau'] = trim($_POST['bureau']);
    $form_data['telephone_pro'] = trim($_POST['telephone_pro']);
    
    $form_data['existing_photo_filename'] = $_POST['existing_photo_filename'] ?? '';
    $form_data['existing_cv_filename'] = $_POST['existing_cv_filename'] ?? '';

   
    if (isset($_FILES['photo_agent']) && $_FILES['photo_agent']['error'] == 0) {
        $photo_filename_new = time() . '_' . basename($_FILES["photo_agent"]["name"]);
        $target_photo_file = $photo_upload_dir . $photo_filename_new;
        if (move_uploaded_file($_FILES["photo_agent"]["tmp_name"], $target_photo_file)) {
            if ($form_data['id_utilisateur'] && !empty($form_data['existing_photo_filename']) && file_exists($photo_upload_dir . $form_data['existing_photo_filename'])) {
                unlink($photo_upload_dir . $form_data['existing_photo_filename']);
            }
            $form_data['photo_filename'] = $photo_filename_new;
        } else {
            $error_message .= "Erreur lors du téléchargement de la photo. ";
        }
    } else {
        $form_data['photo_filename'] = $form_data['existing_photo_filename'];
    }

    
    if (isset($_FILES['cv_agent']) && $_FILES['cv_agent']['error'] == 0) {
        $cv_filename_new = time() . '_' . basename($_FILES["cv_agent"]["name"]);
        $target_cv_file = $cv_upload_dir . $cv_filename_new;
        $allowed_cv_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (in_array($_FILES['cv_agent']['type'], $allowed_cv_types)) {
            if (move_uploaded_file($_FILES["cv_agent"]["tmp_name"], $target_cv_file)) {
                if ($form_data['id_utilisateur'] && !empty($form_data['existing_cv_filename']) && file_exists($cv_upload_dir . $form_data['existing_cv_filename'])) {
                    unlink($cv_upload_dir . $form_data['existing_cv_filename']);
                }
                $form_data['cv_filename'] = $cv_filename_new;
            } else {
                $error_message .= "Erreur lors du téléchargement du CV. ";
            }
        } else {
            $error_message .= "Type de fichier CV non autorisé. ";
        }
    } else {
        $form_data['cv_filename'] = $form_data['existing_cv_filename'];
    }

   
    if (empty($form_data['nom']) || empty($form_data['prenom']) || empty($form_data['email'])) {
        $error_message .= "Nom, prénom et email sont obligatoires. ";
    }
    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message .= "Format d'email invalide. ";
    }
    if (!$form_data['id_utilisateur'] && empty($form_data['mot_de_passe'])) {
        $error_message .= "Le mot de passe est obligatoire pour un nouvel agent. ";
    }
    if (!$form_data['id_utilisateur'] && !empty($form_data['mot_de_passe']) && strlen($form_data['mot_de_passe']) < 6) {
         $error_message .= "Le mot de passe doit contenir au moins 6 caractères. ";
    }

    if (empty($error_message)) {
        try {
            $pdo->beginTransaction();

            if ($form_data['id_utilisateur']) { 
                $edit_mode = true;
                
                $sql_user = "UPDATE Utilisateurs SET nom = :nom, prenom = :prenom, email = :email WHERE id = :id_utilisateur";
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute([
                    ':nom' => $form_data['nom'],
                    ':prenom' => $form_data['prenom'],
                    ':email' => $form_data['email'],
                    ':id_utilisateur' => $form_data['id_utilisateur']
                ]);

                
                $sql_agent = "UPDATE AgentsImmobiliers SET specialite = :specialite, bureau = :bureau, telephone_pro = :telephone_pro, cv_filename = :cv_filename, photo_filename = :photo_filename WHERE id_utilisateur = :id_utilisateur";
                $stmt_agent = $pdo->prepare($sql_agent);
                $stmt_agent->execute([
                    ':specialite' => $form_data['specialite'],
                    ':bureau' => $form_data['bureau'],
                    ':telephone_pro' => $form_data['telephone_pro'],
                    ':cv_filename' => $form_data['cv_filename'],
                    ':photo_filename' => $form_data['photo_filename'],
                    ':id_utilisateur' => $form_data['id_utilisateur']
                ]);
                $success_message = "Agent mis à jour avec succès !";

            } else { 
                
                $sql_check_email = "SELECT id FROM Utilisateurs WHERE email = :email";
                $stmt_check_email = $pdo->prepare($sql_check_email);
                $stmt_check_email->execute([':email' => $form_data['email']]);
                if ($stmt_check_email->fetchColumn()) {
                    throw new Exception("Cette adresse email est déjà utilisée.");
                }

                
                $hashed_password = password_hash($form_data['mot_de_passe'], PASSWORD_DEFAULT);
                $type_compte = 'agent';
                $sql_user = "INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte) VALUES (:nom, :prenom, :email, :mot_de_passe, :type_compte)";
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute([
                    ':nom' => $form_data['nom'],
                    ':prenom' => $form_data['prenom'],
                    ':email' => $form_data['email'],
                    ':mot_de_passe' => $hashed_password,
                    ':type_compte' => $type_compte
                ]);
                $id_new_user = $pdo->lastInsertId();

                
                $sql_agent = "INSERT INTO AgentsImmobiliers (id_utilisateur, specialite, bureau, telephone_pro, cv_filename, photo_filename) VALUES (:id_utilisateur, :specialite, :bureau, :telephone_pro, :cv_filename, :photo_filename)";
                $stmt_agent = $pdo->prepare($sql_agent);
                $stmt_agent->execute([
                    ':id_utilisateur' => $id_new_user,
                    ':specialite' => $form_data['specialite'],
                    ':bureau' => $form_data['bureau'],
                    ':telephone_pro' => $form_data['telephone_pro'],
                    ':cv_filename' => $form_data['cv_filename'],
                    ':photo_filename' => $form_data['photo_filename']
                ]);
                $success_message = "Nouvel agent ajouté avec succès !";
                
                $form_data = [
                    'id_utilisateur' => null, 'nom' => '', 'prenom' => '', 'email' => '', 'mot_de_passe' => '',
                    'specialite' => '', 'bureau' => '', 'telephone_pro' => '',
                    'cv_filename' => '', 'photo_filename' => '',
                    'existing_photo_filename' => '', 'existing_cv_filename' => ''
                ];
                 $edit_mode = false; 
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Erreur: " . $e->getMessage();
            error_log("Agent Management Error: " . $e->getMessage() . " Form Data: " . print_r($form_data, true));
        }
    }
}


if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    try {
        
        $sql_get_files = "SELECT photo_filename, cv_filename FROM AgentsImmobiliers WHERE id_utilisateur = :id_utilisateur";
        $stmt_get_files = $pdo->prepare($sql_get_files);
        $stmt_get_files->execute([':id_utilisateur' => $delete_id]);
        $files_to_delete = $stmt_get_files->fetch(PDO::FETCH_ASSOC);

        
        $sql_delete_agent_profile = "DELETE FROM AgentsImmobiliers WHERE id_utilisateur = :id_utilisateur";
        $stmt_delete_agent_profile = $pdo->prepare($sql_delete_agent_profile);
        $stmt_delete_agent_profile->execute([':id_utilisateur' => $delete_id]);

        $sql_delete_user = "DELETE FROM Utilisateurs WHERE id = :id AND type_compte = 'agent'";
        $stmt_delete_user = $pdo->prepare($sql_delete_user);
        $stmt_delete_user->execute([':id' => $delete_id]);

        if ($stmt_delete_user->rowCount() > 0) {
            
            if ($files_to_delete) {
                if (!empty($files_to_delete['photo_filename']) && file_exists($photo_upload_dir . $files_to_delete['photo_filename'])) {
                    unlink($photo_upload_dir . $files_to_delete['photo_filename']);
                }
                if (!empty($files_to_delete['cv_filename']) && file_exists($cv_upload_dir . $files_to_delete['cv_filename'])) {
                    unlink($cv_upload_dir . $files_to_delete['cv_filename']);
                }
            }
            $_SESSION['success_message_redirect'] = "Agent supprimé avec succès !";
        } else {
             $_SESSION['error_message_redirect'] = "Agent non trouvé ou suppression échouée.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message_redirect'] = "Erreur lors de la suppression de l'agent: " . $e->getMessage();
        error_log("PDO Error deleting agent: " . $e->getMessage());
    }
    
    header("Location: manage_agents.php");
    exit;
}


if (isset($_SESSION['success_message_redirect'])) {
    $success_message = $_SESSION['success_message_redirect'];
    unset($_SESSION['success_message_redirect']);
}
if (isset($_SESSION['error_message_redirect'])) {
    $error_message = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}



if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    try {
        $sql_edit = "SELECT u.id as id_utilisateur, u.nom, u.prenom, u.email, 
                        ai.specialite, ai.bureau, ai.telephone_pro, 
                        ai.cv_filename, ai.photo_filename 
                     FROM Utilisateurs u 
                     JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur 
                     WHERE u.id = :id_utilisateur AND u.type_compte = 'agent'";
        $stmt_edit = $pdo->prepare($sql_edit);
        $stmt_edit->execute([':id_utilisateur' => $edit_id]);
        $agent_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);

        if ($agent_to_edit) {
            $form_data = $agent_to_edit; 
            $form_data['existing_photo_filename'] = $agent_to_edit['photo_filename']; 
            $form_data['existing_cv_filename'] = $agent_to_edit['cv_filename']; 
            $edit_mode = true;
        } else {
            $error_message = "Agent non trouvé pour l'édition.";
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération de l'agent pour édition: " . $e->getMessage();
        error_log("PDO Error fetching agent for edit: " . $e->getMessage());
    }
}


try {
    $sql_agents = "SELECT u.id, u.nom, u.prenom, u.email, ai.specialite, ai.bureau, ai.telephone_pro, ai.photo_filename, ai.cv_filename
                   FROM Utilisateurs u
                   JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur
                   WHERE u.type_compte = 'agent'
                   ORDER BY u.nom ASC";
    $stmt_agents = $pdo->query($sql_agents);
    $agents_list = $stmt_agents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération de la liste des agents: " . $e->getMessage();
    error_log("PDO Error fetching agents list: " . $e->getMessage());
    $agents_list = [];
}

?>
<div class="container mt-5 mb-5">
    <h2 class="mb-4">Gestion des Agents Immobiliers</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    
    <div class="card shadow-sm mb-5">
        <div class="card-header">
            <h4 class="mb-0"><?php echo $edit_mode ? 'Modifier l\'Agent (ID: ' . htmlspecialchars($form_data['id_utilisateur']) . ')' : 'Ajouter un Nouvel Agent'; ?></h4>
        </div>
        <div class="card-body">
            <form action="manage_agents.php<?php echo $edit_mode ? '?action=edit&id='.htmlspecialchars($form_data['id_utilisateur']) : ''; ?>" method="POST" enctype="multipart/form-data">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id_utilisateur" value="<?php echo htmlspecialchars($form_data['id_utilisateur']); ?>">
                <?php endif; ?>
                <input type="hidden" name="existing_photo_filename" value="<?php echo htmlspecialchars($form_data['existing_photo_filename'] ?? ''); ?>">
                <input type="hidden" name="existing_cv_filename" value="<?php echo htmlspecialchars($form_data['existing_cv_filename'] ?? ''); ?>">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($form_data['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($form_data['prenom'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="mot_de_passe" class="form-label">Mot de Passe <?php echo $edit_mode ? '(Laisser vide pour ne pas changer)' : '<span class="text-danger">*</span>'; ?></label>
                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" <?php echo !$edit_mode ? 'required' : ''; ?> autocomplete="new-password">
                    <?php if ($edit_mode): ?><small class="form-text text-muted">Requis uniquement si vous souhaitez changer le mot de passe existant.</small><?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="specialite" class="form-label">Spécialité</label>
                        <input type="text" class="form-control" id="specialite" name="specialite" value="<?php echo htmlspecialchars($form_data['specialite'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="bureau" class="form-label">Bureau</label>
                        <input type="text" class="form-control" id="bureau" name="bureau" value="<?php echo htmlspecialchars($form_data['bureau'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="telephone_pro" class="form-label">Téléphone Professionnel</label>
                    <input type="text" class="form-control" id="telephone_pro" name="telephone_pro" value="<?php echo htmlspecialchars($form_data['telephone_pro'] ?? ''); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="photo_agent" class="form-label">Photo de Profil (JPG, PNG, GIF)</label>
                        <input class="form-control" type="file" id="photo_agent" name="photo_agent" accept="image/*">
                        <?php if ($edit_mode && !empty($form_data['photo_filename'])): ?>
                            <small class="form-text text-muted">
                                Actuelle: <?php echo htmlspecialchars($form_data['photo_filename']); ?> 
                                <img src="<?php echo $path_prefix . htmlspecialchars($photo_upload_dir) . htmlspecialchars($form_data['photo_filename']); ?>" alt="Photo Agent" style="max-height: 50px; margin-left: 10px; border-radius: 4px;">
                            </small>
                        <?php elseif($edit_mode): ?>
                             <small class="form-text text-muted">Aucune photo actuelle.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cv_agent" class="form-label">CV (PDF, DOC, DOCX)</label>
                        <input class="form-control" type="file" id="cv_agent" name="cv_agent" accept=".pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <?php if ($edit_mode && !empty($form_data['cv_filename'])): ?>
                            <small class="form-text text-muted">
                                Actuel: <a href="<?php echo $path_prefix . htmlspecialchars($cv_upload_dir) . htmlspecialchars($form_data['cv_filename']); ?>" target="_blank"><?php echo htmlspecialchars($form_data['cv_filename']); ?></a>
                            </small>
                         <?php elseif($edit_mode): ?>
                             <small class="form-text text-muted">Aucun CV actuel.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="my-4">
                <button type="submit" class="btn btn-primary me-2"><?php echo $edit_mode ? 'Mettre à Jour l\'Agent' : 'Ajouter l\'Agent'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="manage_agents.php" class="btn btn-secondary">Annuler l'Édition</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    
    <h3 class="mt-5 mb-3">Liste des Agents Immobiliers</h3>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($agents_list)): ?>
                <p class="text-center">Aucun agent n'a été ajouté ou configuré pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Spécialité</th>
                                <th>Bureau</th>
                                <th>Téléphone Pro</th>
                                <th>CV</th>
                                <th style="min-width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents_list as $agent_item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($agent_item['id']); ?></td>
                                    <td>
                                        <?php if (!empty($agent_item['photo_filename'])): ?>
                                            <img src="<?php echo $path_prefix . htmlspecialchars($photo_upload_dir) . htmlspecialchars($agent_item['photo_filename']); ?>" alt="Photo Agent" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($agent_item['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($agent_item['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($agent_item['email']); ?></td>
                                    <td><?php echo htmlspecialchars($agent_item['specialite'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($agent_item['bureau'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($agent_item['telephone_pro'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($agent_item['cv_filename'])): ?>
                                            <a href="<?php echo $path_prefix . htmlspecialchars($cv_upload_dir) . htmlspecialchars($agent_item['cv_filename']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Voir CV"><i class="fas fa-file-alt"></i></a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="manage_agents.php?action=edit&id=<?php echo $agent_item['id']; ?>" class="btn btn-sm btn-outline-primary mb-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="manage_agents.php?action=delete&id=<?php echo $agent_item['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" title="Supprimer Agent" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet agent (ID: <?php echo $agent_item['id']; ?>) ? Cette action supprimera son profil et son compte utilisateur associé.');"><i class="fas fa-trash"></i></a>
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

<?php require_once '../php/includes/footer.php'; ?> 
