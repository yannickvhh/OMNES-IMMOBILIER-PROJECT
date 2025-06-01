<?php
$path_prefix = '../'; 
$page_title = "Gérer les Utilisateurs | Admin OMNES IMMOBILIER";
require_once '../php/includes/header.php';
require_once '../php/config/db.php'; 

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: " . $path_prefix . "votre-compte.php");
    exit;
}

$users_list = [];
$success_message = '';
$error_message = '';

$current_admin_id = $_SESSION['user_id'] ?? null; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_type'])) {
    $user_id_to_change = intval($_POST['user_id']);
    $new_type = $_POST['new_type'];
    $allowed_types = ['client', 'agent', 'admin'];

    if ($user_id_to_change == $current_admin_id && $new_type !== 'admin') {
        $error_message = "Vous ne pouvez pas modifier le type de votre propre compte administrateur.";
    } elseif (in_array($new_type, $allowed_types)) {
        try {
            $sql_change_type = "UPDATE Utilisateurs SET type_compte = :new_type WHERE id = :user_id";
            $stmt_change = $pdo->prepare($sql_change_type);
            $stmt_change->execute([':new_type' => $new_type, ':user_id' => $user_id_to_change]);
            
            if ($stmt_change->rowCount() > 0) {
                 if ($new_type !== 'agent' && $_POST['original_type'] === 'agent') {
                    $success_message = "Type de compte utilisateur mis à jour. L'ancien profil d'agent peut nécessiter une vérification manuelle.";
                } elseif ($new_type === 'agent' && $_POST['original_type'] !== 'agent'){
                    $success_message = "Type de compte mis à jour vers Agent. Veuillez compléter le profil de l'agent dans la section 'Gérer les Agents' si ce n'est pas déjà fait.";
                } else {
                    $success_message = "Type de compte utilisateur mis à jour avec succès.";
                }
            } else {
                $error_message = "Aucune modification effectuée. Le type est peut-être déjà correct ou l'utilisateur est introuvable.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour du type de compte: " . $e->getMessage();
            error_log("PDO Error changing user type: " . $e->getMessage());
        }
    } else {
        $error_message = "Type de compte invalide.";
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);

    if ($delete_id == $current_admin_id) {
        $_SESSION['error_message_redirect'] = "Vous ne pouvez pas supprimer votre propre compte administrateur.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_get_user = $pdo->prepare("SELECT u.type_compte, ai.photo_filename, ai.cv_filename 
                                            FROM Utilisateurs u 
                                            LEFT JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur 
                                            WHERE u.id = :id");
            $stmt_get_user->execute([':id' => $delete_id]);
            $user_to_delete_data = $stmt_get_user->fetch(PDO::FETCH_ASSOC);

            if ($user_to_delete_data) {
                if ($user_to_delete_data['type_compte'] === 'agent') {
                    if (!empty($user_to_delete_data['photo_filename']) && file_exists("../assets/agents/photos/" . $user_to_delete_data['photo_filename'])) {
                        unlink("../assets/agents/photos/" . $user_to_delete_data['photo_filename']);
                    }
                    if (!empty($user_to_delete_data['cv_filename']) && file_exists("../assets/agents/cvs/" . $user_to_delete_data['cv_filename'])) {
                        unlink("../assets/agents/cvs/" . $user_to_delete_data['cv_filename']);
                    }
                    $stmt_delete_agent_profile = $pdo->prepare("DELETE FROM AgentsImmobiliers WHERE id_utilisateur = :id_utilisateur");
                    $stmt_delete_agent_profile->execute([':id_utilisateur' => $delete_id]);
                }

                $sql_delete_user = "DELETE FROM Utilisateurs WHERE id = :id";
                $stmt_delete_user = $pdo->prepare($sql_delete_user);
                $stmt_delete_user->execute([':id' => $delete_id]);

                if ($stmt_delete_user->rowCount() > 0) {
                    $_SESSION['success_message_redirect'] = "Utilisateur supprimé avec succès.";
                } else {
                    $_SESSION['error_message_redirect'] = "Utilisateur non trouvé ou suppression échouée (rowCount 0).";
                }
            } else {
                 $_SESSION['error_message_redirect'] = "Utilisateur non trouvé pour la suppression.";
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_message_redirect'] = "Erreur lors de la suppression de l'utilisateur: " . $e->getMessage();
            error_log("PDO Error deleting user: " . $e->getMessage());
        }
    }
    header("Location: manage_users.php");
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

try {
    $sql_users_list = "SELECT u.id, u.nom, u.prenom, u.email, u.type_compte, u.date_creation 
                       FROM Utilisateurs u 
                       ORDER BY u.date_creation DESC";
    $stmt_users_list = $pdo->query($sql_users_list);
    $users_list = $stmt_users_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération de la liste des utilisateurs: " . $e->getMessage();
    error_log("PDO Error fetching users list: " . $e->getMessage());
    $users_list = []; 
}

$account_types_options = [
    'client' => 'Client',
    'agent' => 'Agent Immobilier',
    'admin' => 'Administrateur'
];

?>
<div class="container mt-5 mb-5">
    <h2 class="mb-4">Gestion des Utilisateurs</h2>

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

    <div class="card shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">Liste des Utilisateurs Enregistrés</h4>
        </div>
        <div class="card-body">
            <?php if (empty($users_list)): ?>
                <p class="text-center">Aucun utilisateur n'est enregistré pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Type de Compte</th>
                                <th>Date Création</th>
                                <th class="actions-column-width">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $user_item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                    <td>
                                        <form action="manage_users.php" method="POST" class="d-inline-flex align-items-center">
                                            <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                            <input type="hidden" name="original_type" value="<?php echo $user_item['type_compte']; ?>">
                                            <select name="new_type" class="form-select form-select-sm me-2" style="width: auto;" <?php if ($user_item['id'] == $current_admin_id) echo 'disabled'; ?>>
                                                <?php foreach ($account_types_options as $type_value => $type_label): ?>
                                                    <option value="<?php echo $type_value; ?>" <?php echo ($user_item['type_compte'] == $type_value) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="change_type" class="btn btn-sm btn-outline-info" <?php if ($user_item['id'] == $current_admin_id && $user_item['type_compte'] === 'admin') echo 'disabled'; ?>>Changer</button>
                                        </form>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($user_item['date_creation']))); ?></td>
                                    <td>
                                        <a href="<?php echo $path_prefix; ?>chat.php?contact_id=<?php echo $user_item['id']; ?>&contact_name=<?php echo urlencode($user_item['prenom'] . ' ' . $user_item['nom']); ?>" class="btn btn-sm btn-outline-primary mb-1" title="Contacter cet utilisateur"><i class="fas fa-comments"></i></a>
                                        <?php if ($user_item['type_compte'] === 'agent'): ?>
                                            <a href="manage_agents.php?action=edit&id=<?php echo $user_item['id']; ?>" class="btn btn-sm btn-outline-secondary mb-1" title="Gérer profil agent"><i class="fas fa-user-tie"></i> Profil</a>
                                        <?php endif; ?>
                                        <?php if ($user_item['id'] != $current_admin_id): 
                                            <a href="manage_users.php?action=delete&id=<?php echo $user_item['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" title="Supprimer Utilisateur" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur (ID: <?php echo $user_item['id']; ?>) ? Cette action est irréversible et supprimera toutes les données associées (profil, rdv, messages, etc.).');"><i class="fas fa-trash"></i></a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger mb-1" disabled title="Supprimer Utilisateur"><i class="fas fa-trash"></i></button>
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

<?php require_once '../php/includes/footer.php'; ?> 
