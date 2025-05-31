<?php 
$page_title = "Recherche | OMNES IMMOBILIER";
require_once 'php/includes/header.php'; 
// Ensure db.php now provides a PDO connection object, typically named $pdo
require_once 'php/config/db.php'; 

// --- Search Logic ---
$search_results = [];
$search_term = '';
$search_type = 'property_city'; 
$property_type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$diagnostic_search_message = ""; // For diagnostic messages
$executed_sql_query = ""; // To store the executed SQL query for display
$is_initial_load_or_empty_search = false; // Flag for initial load

// Check DB connection first (using $pdo)
if (!isset($pdo)) {
    $diagnostic_search_message = "<div class='alert alert-danger'>Erreur: La connexion à la base de données (objet \$pdo) n'est pas initialisée. Vérifiez votre fichier <code>php/config/db.php</code> et son inclusion.</div>";
} else {
    // Determine if it's an active search or an initial load/empty search
    $search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
    $search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'property_city';
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $property_type_filter = $_GET['type']; // Prioritize type from URL param if present
    }

    $sql = "";
    $params = []; // Associative array for named parameters

    // Check if any search/filter criteria are actually active
    $has_search_criteria = !empty($search_term) || !empty($property_type_filter);

    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        if ($has_search_criteria) {
            // --- Active Search Logic ---
            if (!empty($property_type_filter) && empty($search_term) && $search_type == 'property_city') {
                $sql = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_nom 
                        FROM Proprietes p 
                        LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur
                        LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id
                        WHERE p.type_propriete = :type_propriete AND (p.statut = 'disponible' OR p.statut = 'enchere_active')
                        ORDER BY p.date_ajout DESC";
                $params[':type_propriete'] = $property_type_filter;
            } else { 
                switch ($search_type) {
                    case 'agent_name':
                        if (!empty($search_term)) {
                            $sql = "SELECT u.id, u.nom, u.prenom, u.email, u.type_compte, 
                                    ai.specialite, ai.bureau, ai.telephone_pro, ai.photo_filename 
                                    FROM Utilisateurs u 
                                    JOIN AgentsImmobiliers ai ON u.id = ai.id_utilisateur 
                                    WHERE (u.nom LIKE :search_term_like OR u.prenom LIKE :search_term_like) 
                                    AND u.type_compte = 'agent'";
                            $params[':search_term_like'] = "%" . $search_term . "%";
                        } else {
                            $diagnostic_search_message = "<div class='alert alert-info'>Veuillez entrer un nom d'agent pour la recherche.</div>";
                        }
                        break;
                    case 'property_id':
                        if (!empty($search_term)) {
                            $sql = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_nom 
                                    FROM Proprietes p 
                                    LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur
                                    LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id
                                    WHERE p.id = :property_id AND (p.statut = 'disponible' OR p.statut = 'enchere_active')";
                            $params[':property_id'] = $search_term;
                        } else {
                            $diagnostic_search_message = "<div class='alert alert-info'>Veuillez entrer un ID de propriété pour la recherche.</div>";
                        }
                        break;
                    case 'property_city': 
                    default:
                        $base_sql = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_nom 
                                     FROM Proprietes p 
                                     LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur
                                     LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id";
                        
                        $where_clauses = ["(p.statut = 'disponible' OR p.statut = 'enchere_active')"];
                        // No need to add p.statut = :status_disponible to $params here as it's hardcoded string

                        if (!empty($search_term)) {
                            $where_clauses[] = "(p.ville LIKE :search_term_ville OR p.titre LIKE :search_term_titre OR p.description LIKE :search_term_descr)";
                            $params[':search_term_ville'] = "%" . $search_term . "%";
                            $params[':search_term_titre'] = "%" . $search_term . "%";
                            $params[':search_term_descr'] = "%" . $search_term . "%";
                        }
                        if(!empty($property_type_filter)) {
                            $where_clauses[] = "p.type_propriete = :type_propriete";
                            $params[':type_propriete'] = $property_type_filter;
                        }

                        if (!empty($where_clauses)) {
                            $sql = $base_sql . " WHERE " . implode(" AND ", $where_clauses);
                            $sql .= " ORDER BY p.date_ajout DESC";
                        } else { 
                            $diagnostic_search_message = "<div class='alert alert-info'>Critères de recherche invalides.</div>";
                        }
                        break;
                }
            }
        } else {
            // --- Initial Load / No Search Criteria --- 
            $is_initial_load_or_empty_search = true;
            $sql = "SELECT p.*, CONCAT(u.prenom, ' ', u.nom) as agent_nom 
                    FROM Proprietes p 
                    LEFT JOIN AgentsImmobiliers ai ON p.id_agent_responsable = ai.id_utilisateur
                    LEFT JOIN Utilisateurs u ON ai.id_utilisateur = u.id
                    WHERE (p.statut = 'disponible' OR p.statut = 'enchere_active') 
                    ORDER BY p.date_ajout DESC";
            // No $params needed for this default query
            $diagnostic_search_message = "<div class='alert alert-info'>Affichage de tous les biens disponibles ou en enchère. Utilisez le formulaire pour affiner votre recherche.</div>";
        }

        // --- Execute Query (if $sql is set and $pdo is available) ---
        if (!empty($sql) && isset($pdo)) {
            $executed_sql_query = $sql; // For display/debug
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params); // Pass associative array of params
                $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $num_results = count($search_results);

                if ($is_initial_load_or_empty_search && $num_results == 0) {
                     $diagnostic_search_message = "<div class='alert alert-warning'>Aucun bien avec le statut 'disponible' ou 'enchere_active' n'a été trouvé dans la base de données.</div>";
                } elseif (!$is_initial_load_or_empty_search) {
                    $diagnostic_search_message .= "<div class='alert alert-info mt-2'>" . $num_results . " résultat(s) trouvé(s) pour vos critères.</div>";
                } 
                // Else, for initial load with results, the existing message is fine.

            } catch (PDOException $e) {
                $diagnostic_search_message .= "<div class='alert alert-danger mt-2'>Erreur lors de l'exécution de la recherche: " . htmlspecialchars($e->getMessage()) . "</div>";
                error_log("PDO Search Error: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            }
        }
    } // End of if ($_SERVER["REQUEST_METHOD"] == "GET")
}

$property_types_for_select = [
    'residentiel' => 'Immobilier Résidentiel',
    'commercial' => 'Immobilier Commercial',
    'terrain' => 'Terrain',
    'location' => 'Appartement à Louer',
    'enchere' => 'Vente par Enchère'
];

?>

    <!-- Page Title -->
    <section class="section py-5">
        <div class="container">
            <div class="section-title text-center">
                <h2>Recherche Immobilière</h2>
                <p>Trouvez rapidement un agent, une propriété ou explorez par ville.</p>
                 <?php if ($is_initial_load_or_empty_search && !empty($search_results)): ?>
                    <p class="text-success fw-bold">Affichage de tous les biens disponibles.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Search Form Section -->
    <section class="section search-form-section py-5 bg-light">
        <div class="container">
            <form method="GET" action="recherche.php">
                <div class="row g-3 align-items-center justify-content-center">
                    <div class="col-md-5">
                        <label for="q" class="visually-hidden">Terme de recherche</label>
                        <input type="text" class="form-control form-control-lg" id="q" name="q" placeholder="Nom agent, N° propriété, Ville..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="search_type" class="visually-hidden">Type de recherche</label>
                        <select class="form-select form-select-lg" id="search_type" name="search_type">
                            <option value="property_city" <?php echo ($search_type == 'property_city') ? 'selected' : ''; ?>>Propriété (Ville, Titre)</option>
                            <option value="property_id" <?php echo ($search_type == 'property_id') ? 'selected' : ''; ?>>Propriété (Numéro)</option>
                            <option value="agent_name" <?php echo ($search_type == 'agent_name') ? 'selected' : ''; ?>>Agent Immobilier (Nom)</option>
                        </select>
                    </div>
                     <div class="col-md-3">
                        <label for="property_type_filter" class="visually-hidden">Type de Propriété</label>
                        <select class="form-select form-select-lg" id="property_type_filter" name="type">
                            <option value="">Tous les types</option>
                            <?php foreach($property_types_for_select as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($property_type_filter == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 text-center">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Search Results Section -->
    <section class="section search-results-section py-5">
        <div class="container">
            <?php 
            // Display diagnostic messages accumulated during the PHP logic
            if (!empty($diagnostic_search_message)) {
                 // Check if the only message is the default initial load message AND there are results.
                // In that case, we might not want to show the generic "Affichage de tous les biens..." if specific results title is shown.
                $is_default_initial_msg_with_results = $is_initial_load_or_empty_search && 
                                                       strpos($diagnostic_search_message, "Affichage de tous les biens disponibles") !== false && 
                                                       !empty($search_results);
                if (!$is_default_initial_msg_with_results) {
                    echo "<div class='mb-3'>" . $diagnostic_search_message . "</div>";
                }
            }
            ?>
            <?php if (!empty($search_results)): ?>
                <?php if (!$is_initial_load_or_empty_search): // Only show specific search title if it was an active search ?>
                 <h3 class="mb-4">Résultats de la recherche <?php 
                    if (!empty($search_term)) { echo 'pour \'' . htmlspecialchars($search_term) . '\''; }
                    if (!empty($property_type_filter)) { 
                        echo !empty($search_term) ? ' et ' : 'pour ';
                        echo '(Type: ' . htmlspecialchars($property_types_for_select[$property_type_filter] ?? $property_type_filter) . ")"; 
                    }
                ?></h3>
                <?php endif; ?>
                <div class="row">
                    <?php foreach ($search_results as $result): // $result is an associative array by default with PDO::FETCH_ASSOC
                        if ($search_type == 'agent_name' || (isset($result['type_compte']) && $result['type_compte'] == 'agent')): ?>
                            <!-- Agent Card -->
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card agent-card h-100 shadow-sm">
                                    <img src="assets/agents/photos/<?php echo htmlspecialchars(!empty($result['photo_filename']) ? $result['photo_filename'] : 'default_agent.png'); ?>" class="card-img-top agent-card-img" alt="Photo de <?php echo htmlspecialchars(($result['prenom'] ?? '') . ' ' . ($result['nom'] ?? '')); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars(($result['prenom'] ?? 'Prénom inconnu') . ' ' . ($result['nom'] ?? 'Nom inconnu')); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($result['specialite'] ?? 'Agent Immobilier'); ?></h6>
                                        <p class="card-text"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($result['email'] ?? 'Email non disponible'); ?><br>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($result['telephone_pro'] ?? 'N/A'); ?><br>
                                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($result['bureau'] ?? 'N/A'); ?></p>
                                        <a href="agent_details.php?id=<?php echo $result['id'] ?? '#'; ?>" class="btn btn-primary">Voir Profil</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: // Property Card (default assumption if not agent_name search type) ?>
                            <!-- Property Card -->
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card property-card h-100 shadow-sm">
                                     <img src="assets/properties/<?php echo htmlspecialchars(!empty($result['photo_principale_filename']) ? $result['photo_principale_filename'] : 'default_property.jpg'); ?>" class="card-img-top property-card-img-search" alt="<?php echo htmlspecialchars($result['titre'] ?? 'Propriété'); ?>">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($result['titre'] ?? 'Titre non disponible'); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(($result['ville'] ?? 'Ville inconnue') . ', ' . ($result['code_postal'] ?? '')); ?></h6>
                                        <p class="card-text type-tag">Type: <?php echo htmlspecialchars($property_types_for_select[$result['type_propriete']] ?? $result['type_propriete'] ?? 'Type inconnu'); ?></p>
                                        <p class="card-text flex-grow-1 description-truncate"><?php echo nl2br(htmlspecialchars(substr($result['description'] ?? '', 0, 100))); ?>...</p>
                                        <p class="card-text price-tag fs-5 text-primary fw-bold">
                                            <?php echo (isset($result['type_propriete']) && $result['type_propriete'] == 'location' && isset($result['prix'])) ? number_format($result['prix'], 2, ',', ' ') . ' € / mois' : (isset($result['prix']) ? number_format($result['prix'], 2, ',', ' ') . ' €' : 'Prix non spécifié'); ?>
                                        </p>
                                        <?php if(!empty($result['agent_nom'])): ?>
                                            <p class="card-text text-muted small">Agent: <?php echo htmlspecialchars($result['agent_nom']); ?></p>
                                        <?php endif; ?>
                                        <a href="propriete_details.php?id=<?php echo $result['id'] ?? '#'; ?>" class="btn btn-primary mt-auto">Plus de détails</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php 
            // This condition checks if it was an active search (not initial load) that yielded 0 results, 
            // and no critical error message was already set in $diagnostic_search_message that would indicate a DB problem.
            elseif ($_SERVER["REQUEST_METHOD"] == "GET" && 
                    !$is_initial_load_or_empty_search && 
                    empty($search_results) && 
                    (strpos($diagnostic_search_message, "résultat(s) trouvé(s)") !== false || 
                     (strpos($diagnostic_search_message, "Erreur") === false && strpos($diagnostic_search_message, "Veuillez entrer") === false) // Not an error and not a prompt to enter search terms
                    )
                  ):
                // The diagnostic message from PHP logic (e.g., "0 résultat(s) trouvé(s)") is already displayed above.
                // No need for an additional message here unless you want to customize it further.
            ?>
            <?php 
            // This covers the very initial state of the page before any search, or if an initial load found no available properties and no error occurred.
            elseif ($is_initial_load_or_empty_search && 
                    empty($search_results) && 
                    strpos($diagnostic_search_message, "Erreur") === false && 
                    strpos($diagnostic_search_message, "Aucun bien avec le statut 'disponible'") === false // Don't show if already covered by specific "no available" msg
                   ):
                 if (empty($diagnostic_search_message) || strpos($diagnostic_search_message, "Affichage de tous les biens disponibles") !== false) { // Only show if no other specific diagnostic is more relevant
                     echo '<p class="text-center text-muted">Veuillez utiliser le formulaire ci-dessus pour lancer une recherche.</p>';
                 }
            ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'php/includes/footer.php'; ?> 