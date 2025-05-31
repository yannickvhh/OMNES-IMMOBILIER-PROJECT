<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Determine the current page to set the active class in navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Path prefix for assets - to be defined in each including page
// Default to empty if not set, for pages in the root directory.
$path_prefix = isset($path_prefix) ? $path_prefix : '';

// Check if the user is logged in and what type of user they are
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_type = $is_logged_in ? $_SESSION["user_type"] : null;
$user_name = $is_logged_in ? htmlspecialchars($_SESSION["user_prenom"]) . ' ' . htmlspecialchars($_SESSION["user_nom"]) : null;
$user_id = $is_logged_in ? $_SESSION["user_id"] : null;

$total_unread_messages = 0;
if ($is_logged_in && isset($mysqli) && $mysqli && isset($user_id)) {
    // $mysqli is assumed to be globally available from the script that included this header.
    $sql_unread_count = "SELECT COUNT(*) as unread_total FROM Messages WHERE id_destinataire = ? AND lu = FALSE";
    if ($stmt_unread = $mysqli->prepare($sql_unread_count)) {
        $stmt_unread->bind_param("i", $user_id);
        if ($stmt_unread->execute()) {
            $result_unread = $stmt_unread->get_result();
            if ($row_unread = $result_unread->fetch_assoc()) {
                $total_unread_messages = $row_unread['unread_total'];
            }
        }
        $stmt_unread->close();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The title will be set by each individual page -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Your custom CSS (should come after Bootstrap to override styles) -->
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>php/includes/style.css">

    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'OMNES IMMOBILIER'; ?></title>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-content">
            <div class="logo-container">
                <img src="<?php echo $path_prefix; ?>assets/images/logo.png" alt="Logo OMNES IMMOBILIER">
                <h1>OMNES IMMOBILIER</h1>
            </div>
            <?php if ($is_logged_in): ?>
                <div class="user-info ms-auto">
                    <span class="me-3">Bienvenue, <?php echo $user_name; ?>! (<?php echo htmlspecialchars($user_type); ?>)</span>
                    <a href="php/actions/logout_action.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg bg-light sticky-top shadow-sm">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'tout-parcourir.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>tout-parcourir.php">Tout Parcourir</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'recherche.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>recherche.php">Recherche</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'rendez-vous.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>rendez-vous.php">Rendez-vous</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'votre-compte.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>votre-compte.php">Votre Compte</a>
                    </li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>chat.php">
                                Messagerie
                                <?php if ($total_unread_messages > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $total_unread_messages; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_logged_in && $user_type === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Administration
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="<?php echo $path_prefix; ?>admin/manage_properties.php">Gérer Propriétés</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path_prefix; ?>admin/manage_agents.php">Gérer Agents</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path_prefix; ?>admin/manage_users.php">Gérer Utilisateurs</a></li>
                                <!-- Add more admin links as needed -->
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_logged_in && $user_type === 'agent'): ?>
                         <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($current_page, 'agent_dashboard.php') !== false) ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>agent/agent_dashboard.php">Tableau de Bord Agent</a>
                        </li>
                        <!-- Add more agent-specific links here -->
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content will go here -->

</body>
</html> 