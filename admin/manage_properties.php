<?php
$path_prefix = '../';
$page_title = "Gérer les Propriétés | Admin OMNES IMMOBILIER";
require_once '../php/includes/header.php';
require_once '../php/config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: " . $path_prefix . "votre-compte.php");
    exit;
}

$property_types_options = [
    'residentiel' => 'Immobilier Résidentiel',
    'commercial' => 'Immobilier Commercial',
    'terrain' => 'Terrain',
    'location' => 'Appartement à Louer',
    'enchere' => 'Vente par Enchère'
];
$property_status_options = [
    'disponible' => 'Disponible',
    'vendu' => 'Vendu',
    'enchere_active' => 'Enchère Active',
    'enchere_terminee' => 'Enchère Terminée (sans gagnant)',
    'enchere_terminee_gagnee' => 'Enchère Terminée (avec gagnant)',
    'retire' => 'Retiré'
];

$properties = [];
$agents = [];
$form_data = [
    'id' => null,
    'titre' => '', 'description' => '', 'type_propriete' => 'residentiel', 'adresse' => '',
    'ville' => '', 'code_postal' => '', 'prix' => '', 'nombre_pieces' => '', 'nombre_chambres' => '',
    'surface' => '', 'etage' => '', 'balcon' => 0, 'parking' => 0, 'id_agent_responsable' => '',
    'statut' => 'disponible', 'photo_principale_filename' => '', 'video_url' => '',
    'prix_depart_enchere' => '', 'date_heure_debut_enchere' => '', 'date_heure_fin_enchere' => ''
];
$edit_mode = false;
$edit_property_id = null;
$success_message = '';
$error_message = '';

try {
    $sql_agents = "SELECT u.id, CONCAT(u.prenom, ' ', u.nom) as agent_name 
                   FROM Utilisateurs u 
                   JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur 
                   WHERE u.type_compte = 'agent' 
                   ORDER BY u.nom ASC";
    $stmt_agents = $pdo->query($sql_agents);
    $agents = $stmt_agents->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des agents: " . $e->getMessage();
    error_log("PDO Error fetching agents: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_data['titre'] = trim($_POST['titre']);
    $form_data['description'] = trim($_POST['description']);
    $form_data['type_propriete'] = $_POST['type_propriete'];
    $form_data['adresse'] = trim($_POST['adresse']);
    $form_data['ville'] = trim($_POST['ville']);
    $form_data['code_postal'] = trim($_POST['code_postal']);
    $form_data['prix'] = !empty($_POST['prix']) ? (float)$_POST['prix'] : null;
    $form_data['nombre_pieces'] = !empty($_POST['nombre_pieces']) ? (int)$_POST['nombre_pieces'] : null;
    $form_data['nombre_chambres'] = !empty($_POST['nombre_chambres']) ? (int)$_POST['nombre_chambres'] : null;
    $form_data['surface'] = !empty($_POST['surface']) ? (float)$_POST['surface'] : null;
    $form_data['etage'] = !empty($_POST['etage']) ? (int)$_POST['etage'] : null;
    $form_data['balcon'] = isset($_POST['balcon']) ? 1 : 0;
    $form_data['parking'] = isset($_POST['parking']) ? 1 : 0;
    $form_data['id_agent_responsable'] = !empty($_POST['id_agent_responsable']) ? (int)$_POST['id_agent_responsable'] : null;
    
    $form_data['prix_depart_enchere'] = !empty($_POST['prix_depart_enchere']) ? (float)$_POST['prix_depart_enchere'] : null;
    $form_data['date_heure_debut_enchere'] = !empty($_POST['date_heure_debut_enchere']) ? $_POST['date_heure_debut_enchere'] : null;
    $form_data['date_heure_fin_enchere'] = !empty($_POST['date_heure_fin_enchere']) ? $_POST['date_heure_fin_enchere'] : null;

    if (isset($_POST['statut']) && array_key_exists($_POST['statut'], $property_status_options)) {
        $form_data['statut'] = $_POST['statut'];
    } else {
        error_log("Admin manage_properties.php - Invalid or missing statut POSTed: " . ($_POST['statut'] ?? '[NOT SET]') . ". Defaulting logic may apply.");
        if (!isset($_POST['edit_property_id'])) {
             $form_data['statut'] = 'disponible'; 
        }
    }

    if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] == 0) {
        $target_dir = "../assets/properties/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $photo_filename = time() . '_' . basename($_FILES["photo_principale"]["name"]);
        $target_file = $target_dir . $photo_filename;
        if (move_uploaded_file($_FILES["photo_principale"]["tmp_name"], $target_file)) {
            $form_data['photo_principale_filename'] = $photo_filename;
        } else {
            $error_message = "Erreur lors du téléchargement de la photo.";
        }
    } elseif (!empty($_POST['existing_photo_filename'])){
        $form_data['photo_principale_filename'] = $_POST['existing_photo_filename'];
    } else {
        $form_data['photo_principale_filename'] = null;
    }

    $form_data['video_url'] = !empty($_POST['video_url']) ? trim($_POST['video_url']) : null;
    $edit_property_id = isset($_POST['edit_property_id']) ? (int)$_POST['edit_property_id'] : null;

    if (empty($form_data['titre']) || empty($form_data['adresse']) || empty($form_data['ville'])) {
        $error_message = "Le titre, l'adresse et la ville sont obligatoires.";
    }

    if ($form_data['statut'] === 'enchere_active') {
        if (empty($form_data['prix_depart_enchere']) || $form_data['prix_depart_enchere'] <= 0) {
            $error_message .= "<br>Le prix de départ de l'enchère est obligatoire et doit être positif.";
        }
        if (empty($form_data['date_heure_debut_enchere'])) {
            $error_message .= "<br>La date et l'heure de début de l'enchère sont obligatoires.";
        }
        if (empty($form_data['date_heure_fin_enchere'])) {
            $error_message .= "<br>La date et l'heure de fin de l'enchère sont obligatoires.";
        }
        if (!empty($form_data['date_heure_debut_enchere']) && !empty($form_data['date_heure_fin_enchere'])) {
            $debut_dt = new DateTime($form_data['date_heure_debut_enchere']);
            $fin_dt = new DateTime($form_data['date_heure_fin_enchere']);
            if ($fin_dt <= $debut_dt) {
                $error_message .= "<br>La date de fin de l'enchère doit être postérieure à la date de début.";
            }
        }
    }

    if (empty($error_message)) {
        try {
            $pdo->beginTransaction();

            $current_property_id = null;

            if ($edit_property_id) {
                $current_property_id = $edit_property_id;
                $sql_prop = "UPDATE Proprietes SET 
                            titre = :titre, description = :description, type_propriete = :type_propriete, 
                            adresse = :adresse, ville = :ville, code_postal = :code_postal, prix = :prix, 
                            nombre_pieces = :nombre_pieces, nombre_chambres = :nombre_chambres, 
                            surface = :surface, etage = :etage, balcon = :balcon, parking = :parking, 
                            photo_principale_filename = :photo_principale_filename, video_url = :video_url, 
                            statut = :statut, id_agent_responsable = :id_agent_responsable 
                        WHERE id = :id";
                $stmt_prop = $pdo->prepare($sql_prop);
                $params_prop = [
                    ':titre' => $form_data['titre'], ':description' => $form_data['description'], 
                    ':type_propriete' => $form_data['type_propriete'], ':adresse' => $form_data['adresse'], 
                    ':ville' => $form_data['ville'], ':code_postal' => $form_data['code_postal'], 
                    ':prix' => $form_data['prix'], ':nombre_pieces' => $form_data['nombre_pieces'], 
                    ':nombre_chambres' => $form_data['nombre_chambres'], ':surface' => $form_data['surface'], 
                    ':etage' => $form_data['etage'], ':balcon' => $form_data['balcon'], 
                    ':parking' => $form_data['parking'], 
                    ':photo_principale_filename' => $form_data['photo_principale_filename'], 
                    ':video_url' => $form_data['video_url'], ':statut' => $form_data['statut'], 
                    ':id_agent_responsable' => $form_data['id_agent_responsable'], ':id' => $edit_property_id
                ];
                $stmt_prop->execute($params_prop);
            } else {
                $sql_prop = "INSERT INTO Proprietes (titre, description, type_propriete, adresse, ville, code_postal, 
                            prix, nombre_pieces, nombre_chambres, surface, etage, balcon, parking, 
                            photo_principale_filename, video_url, statut, id_agent_responsable, date_ajout) 
                        VALUES (:titre, :description, :type_propriete, :adresse, :ville, :code_postal, 
                            :prix, :nombre_pieces, :nombre_chambres, :surface, :etage, :balcon, :parking, 
                            :photo_principale_filename, :video_url, :statut, :id_agent_responsable, NOW())";
                $stmt_prop = $pdo->prepare($sql_prop);
                $params_prop = [
                    ':titre' => $form_data['titre'], ':description' => $form_data['description'],
                    ':type_propriete' => $form_data['type_propriete'], ':adresse' => $form_data['adresse'],
                    ':ville' => $form_data['ville'], ':code_postal' => $form_data['code_postal'],
                    ':prix' => $form_data['prix'], ':nombre_pieces' => $form_data['nombre_pieces'],
                    ':nombre_chambres' => $form_data['nombre_chambres'], ':surface' => $form_data['surface'],
                    ':etage' => $form_data['etage'], ':balcon' => $form_data['balcon'],
                    ':parking' => $form_data['parking'],
                    ':photo_principale_filename' => $form_data['photo_principale_filename'],
                    ':video_url' => $form_data['video_url'], ':statut' => $form_data['statut'],
                    ':id_agent_responsable' => $form_data['id_agent_responsable']
                ];
                $stmt_prop->execute($params_prop);
                $current_property_id = $pdo->lastInsertId();
            }

            if ($form_data['statut'] === 'enchere_active' && $current_property_id) {
                $sql_check_enchere = "SELECT id FROM Encheres WHERE id_propriete = :id_propriete";
                $stmt_check = $pdo->prepare($sql_check_enchere);
                $stmt_check->execute([':id_propriete' => $current_property_id]);
                $existing_enchere_id = $stmt_check->fetchColumn();

                $params_enchere = [
                    ':id_propriete' => $current_property_id,
                    ':prix_depart' => $form_data['prix_depart_enchere'],
                    ':date_heure_debut' => $form_data['date_heure_debut_enchere'],
                    ':date_heure_fin' => $form_data['date_heure_fin_enchere']
                ];

                if ($existing_enchere_id) {
                    $sql_enchere = "UPDATE Encheres SET prix_depart = :prix_depart, date_heure_debut = :date_heure_debut, date_heure_fin = :date_heure_fin WHERE id = :id";
                    $params_enchere[':id'] = $existing_enchere_id;
                } else {
                    $sql_enchere = "INSERT INTO Encheres (id_propriete, prix_depart, date_heure_debut, date_heure_fin) VALUES (:id_propriete, :prix_depart, :date_heure_debut, :date_heure_fin)";
                }
                $stmt_enchere = $pdo->prepare($sql_enchere);
                $stmt_enchere->execute($params_enchere);
            } else if ($current_property_id) {
                
            }

            $pdo->commit();

            $success_message = $edit_property_id ? "Propriété et détails d'enchère (si applicable) mis à jour avec succès !" : "Propriété et détails d'enchère (si applicable) ajoutés avec succès !";
            
            if(!$edit_property_id) {
                 $form_data = array_fill_keys(array_keys($form_data), '');
                 $form_data['type_propriete'] = 'residentiel'; $form_data['statut'] = 'disponible'; 
                 $form_data['balcon'] = 0; $form_data['parking'] = 0; $form_data['id'] = null;
            } else {
                
            }

        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'opération : " . $e->getMessage();
            error_log("PDO Error on property save: " . $e->getMessage() . " SQL: " . (isset($sql_prop) ? $sql_prop : (isset($sql_enchere) ? $sql_enchere : 'N/A')) . " Params: " . json_encode(isset($params_prop) ? $params_prop : (isset($params_enchere) ? $params_enchere : [])));
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        
        $sql_delete = "DELETE FROM Proprietes WHERE id = :id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([':id' => $delete_id]);

        $_SESSION['success_message_redirect'] = "Propriété supprimée avec succès !";
    } catch (PDOException $e) {
        $_SESSION['error_message_redirect'] = "Erreur lors de la suppression : " . $e->getMessage();
        error_log("PDO Error deleting property: " . $e->getMessage());
    }
    header("Location: manage_properties.php");
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

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && !$edit_mode && $_SERVER["REQUEST_METHOD"] != "POST") {
    $current_edit_id = (int)$_GET['id'];
    try {
        $sql_edit = "SELECT p.*, 
                           e.prix_depart as prix_depart_enchere, 
                           e.date_heure_debut as date_heure_debut_enchere, 
                           e.date_heure_fin as date_heure_fin_enchere 
                     FROM Proprietes p 
                     LEFT JOIN Encheres e ON p.id = e.id_propriete
                     WHERE p.id = :id";
        $stmt_edit = $pdo->prepare($sql_edit);
        $stmt_edit->execute([':id' => $current_edit_id]);
        $property_to_edit = $stmt_edit->fetch();

        if ($property_to_edit) {
            $form_data = $property_to_edit;
            $edit_mode = true;
            $edit_property_id = $current_edit_id;
            $form_data['balcon'] = (int)($form_data['balcon'] ?? 0);
            $form_data['parking'] = (int)($form_data['parking'] ?? 0);

        } else {
            $error_message = "Propriété non trouvée pour l'édition.";
            $edit_mode = false;
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération de la propriété pour édition: " . $e->getMessage();
        error_log("PDO Error fetching property for edit: " . $e->getMessage());
        $edit_mode = false;
    }
}

try {
    $sql_properties = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_name 
                       FROM Proprietes p 
                       LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur 
                       LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id 
                       ORDER BY p.date_ajout DESC";
    $stmt_properties = $pdo->query($sql_properties);
    $properties = $stmt_properties->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération de la liste des propriétés: " . $e->getMessage();
    error_log("PDO Error fetching properties list: " . $e->getMessage());
}

?>
<div class="container mt-5 mb-5">
    <h2 class="mb-4">Gestion des Propriétés</h2>

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
            <h4 class="mb-0"><?php echo $edit_mode ? 'Modifier la Propriété (ID: ' . htmlspecialchars($edit_property_id) . ')' : 'Ajouter une Nouvelle Propriété'; ?></h4>
        </div>
        <div class="card-body">
            <form action="manage_properties.php<?php echo $edit_mode ? '?action=edit&id='.htmlspecialchars($edit_property_id) : ''; ?>" method="POST" enctype="multipart/form-data">
                <?php if ($edit_mode && $edit_property_id): ?>
                    <input type="hidden" name="edit_property_id" value="<?php echo htmlspecialchars($edit_property_id); ?>">
                <?php endif; ?>
                <input type="hidden" name="existing_photo_filename" value="<?php echo htmlspecialchars($form_data['photo_principale_filename'] ?? ''); ?>">


                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="titre" class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titre" name="titre" value="<?php echo htmlspecialchars($form_data['titre'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="type_propriete" class="form-label">Type de Propriété <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_propriete" name="type_propriete" required>
                            <?php foreach ($property_types_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo (($form_data['type_propriete'] ?? 'residentiel') == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="adresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="adresse" name="adresse" value="<?php echo htmlspecialchars($form_data['adresse'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="ville" class="form-label">Ville <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ville" name="ville" value="<?php echo htmlspecialchars($form_data['ville'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="code_postal" class="form-label">Code Postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($form_data['code_postal'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="prix" class="form-label">Prix (€)</label>
                        <input type="number" step="0.01" class="form-control" id="prix" name="prix" value="<?php echo htmlspecialchars($form_data['prix'] ?? ''); ?>">
                    </div>
                     <div class="col-md-3 mb-3">
                        <label for="surface" class="form-label">Surface (m²)</label>
                        <input type="number" step="0.01" class="form-control" id="surface" name="surface" value="<?php echo htmlspecialchars($form_data['surface'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="id_agent_responsable" class="form-label">Agent Responsable</label>
                        <select class="form-select" id="id_agent_responsable" name="id_agent_responsable">
                            <option value="">-- Sélectionner un agent --</option>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>" <?php echo (($form_data['id_agent_responsable'] ?? '') == $agent['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agent['agent_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="nombre_pieces" class="form-label">Nombre de Pièces</label>
                        <input type="number" class="form-control" id="nombre_pieces" name="nombre_pieces" value="<?php echo htmlspecialchars($form_data['nombre_pieces'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="nombre_chambres" class="form-label">Nombre de Chambres</label>
                        <input type="number" class="form-control" id="nombre_chambres" name="nombre_chambres" value="<?php echo htmlspecialchars($form_data['nombre_chambres'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="etage" class="form-label">Étage</label>
                        <input type="number" class="form-control" id="etage" name="etage" value="<?php echo htmlspecialchars($form_data['etage'] ?? ''); ?>">
                    </div>
                     <div class="col-md-3 mb-3">
                        <label for="statut" class="form-label">Statut <span class="text-danger">*</span></label>
                        <select class="form-select" id="statut" name="statut" required onchange="toggleAuctionFields()">
                            <?php foreach ($property_status_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo (($form_data['statut'] ?? 'disponible') == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="auction_fields_container" class="row mt-3 border-top pt-3" style="display: none;">
                    <h5 class="mb-3 text-primary">Détails de l'Enchère (si statut 'Enchère Active')</h5>
                    <div class="col-md-4 mb-3">
                        <label for="prix_depart_enchere" class="form-label">Prix de Départ Enchère (€) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="prix_depart_enchere" name="prix_depart_enchere" value="<?php echo htmlspecialchars($form_data['prix_depart_enchere'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_heure_debut_enchere" class="form-label">Début Enchère (Date et Heure) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="date_heure_debut_enchere" name="date_heure_debut_enchere" value="<?php echo htmlspecialchars(!empty($form_data['date_heure_debut_enchere']) ? date('Y-m-d\TH:i', strtotime($form_data['date_heure_debut_enchere'])) : ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_heure_fin_enchere" class="form-label">Fin Enchère (Date et Heure) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="date_heure_fin_enchere" name="date_heure_fin_enchere" value="<?php echo htmlspecialchars(!empty($form_data['date_heure_fin_enchere']) ? date('Y-m-d\TH:i', strtotime($form_data['date_heure_fin_enchere'])) : ''); ?>">
                    </div>
                </div>

                <div class="row align-items-center">
                    <div class="col-md-6 mb-3">
                        <label for="photo_principale" class="form-label">Photo Principale</label>
                        <input class="form-control" type="file" id="photo_principale" name="photo_principale" accept="image/*">
                        <?php if ($edit_mode && !empty($form_data['photo_principale_filename'])): ?>
                            <small class="form-text text-muted">Actuelle: <?php echo htmlspecialchars($form_data['photo_principale_filename']); ?> 
                            <img src="<?php echo $path_prefix . 'assets/properties/' . htmlspecialchars($form_data['photo_principale_filename']); ?>" alt="Photo actuelle" style="max-height: 50px; margin-left: 10px; border-radius: 4px;">
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="video_url" class="form-label">URL Vidéo (YouTube, Vimeo, etc.)</label>
                        <input type="url" class="form-control" id="video_url" name="video_url" value="<?php echo htmlspecialchars($form_data['video_url'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3 form-check form-switch ps-5 pt-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="balcon" name="balcon" value="1" <?php echo !empty($form_data['balcon']) && $form_data['balcon'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="balcon">Balcon</label>
                    </div>
                    <div class="col-md-3 mb-3 form-check form-switch ps-5 pt-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="parking" name="parking" value="1" <?php echo !empty($form_data['parking']) && $form_data['parking'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="parking">Parking</label>
                    </div>
                </div>

                <hr class="my-4">
                <button type="submit" class="btn btn-primary me-2"><?php echo $edit_mode ? 'Mettre à Jour la Propriété' : 'Ajouter la Propriété'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="manage_properties.php" class="btn btn-secondary">Annuler l'Édition</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <h3 class="mb-3">Liste des Propriétés Existantes</h3>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($properties)): ?>
                <p class="text-center">Aucune propriété n'a été ajoutée pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Titre</th>
                                <th>Type</th>
                                <th>Ville</th>
                                <th>Prix</th>
                                <th>Agent</th>
                                <th>Statut</th>
                                <th style="min-width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                                <tr>
                                    <td><?php echo $property['id']; ?></td>
                                    <td>
                                        <?php if (!empty($property['photo_principale_filename'])): ?>
                                            <img src="<?php echo $path_prefix . 'assets/properties/' . htmlspecialchars($property['photo_principale_filename']); ?>" alt="<?php echo htmlspecialchars($property['titre']); ?>" style="width: 100px; height: 60px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <small class="text-muted">N/A</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($property['titre']); ?></td>
                                    <td><?php echo htmlspecialchars($property_types_options[$property['type_propriete']] ?? $property['type_propriete']); ?></td>
                                    <td><?php echo htmlspecialchars($property['ville']); ?></td>
                                    <td><?php echo ($property['prix'] > 0) ? number_format($property['prix'], 2, ',', ' ') . ' €' : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($property['agent_name'] ?? 'Non assigné'); ?></td>
                                    <td>
                                        <?php 
                                            $status_class = 'secondary';
                                            if (isset($property_status_options[$property['statut']])) {
                                                switch ($property['statut']) {
                                                    case 'disponible': $status_class = 'success'; break;
                                                    case 'vendu': case 'retire': $status_class = 'danger'; break;
                                                    case 'enchere_active': case 'enchere_terminee': $status_class = 'warning'; break;
                                                }
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo htmlspecialchars($property_status_options[$property['statut']] ?? $property['statut']); ?></span>
                                    </td>
                                    <td>
                                        <a href="manage_properties.php?action=edit&id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-primary mb-1" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="<?php echo $path_prefix; ?>propriete_details.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-info mb-1" target="_blank" title="Voir"><i class="fas fa-eye"></i></a>
                                        <a href="manage_properties.php?action=delete&id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette propriété (ID: <?php echo $property['id']; ?>) ? Cette action est irréversible.');" title="Supprimer"><i class="fas fa-trash"></i></a>
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

<script>
function toggleAuctionFields() {
    var statutSelect = document.getElementById('statut');
    var auctionFieldsContainer = document.getElementById('auction_fields_container');
    var prixDepartInput = document.getElementById('prix_depart_enchere');
    var dateDebutInput = document.getElementById('date_heure_debut_enchere');
    var dateFinInput = document.getElementById('date_heure_fin_enchere');

    if (statutSelect.value === 'enchere_active') {
        auctionFieldsContainer.style.display = 'flex';
        prixDepartInput.required = true;
        dateDebutInput.required = true;
        dateFinInput.required = true;
    } else {
        auctionFieldsContainer.style.display = 'none';
        prixDepartInput.required = false;
        dateDebutInput.required = false;
        dateFinInput.required = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleAuctionFields();
});
</script>

<?php require_once '../php/includes/footer.php'; ?> 
