<?php 
$page_title = "Votre Compte | OMNES IMMOBILIER";
require_once 'php/includes/header.php'; 
require_once 'php/config/db.php'; // For any direct DB interactions on this page, if needed later

// Retrieve registration errors and input from session if they exist
$registration_errors = isset($_SESSION['registration_errors']) ? $_SESSION['registration_errors'] : [];
$registration_input = isset($_SESSION['registration_input']) ? $_SESSION['registration_input'] : [];
unset($_SESSION['registration_errors']);
unset($_SESSION['registration_input']);

// Retrieve login errors and input from session if they exist
$login_errors = isset($_SESSION['login_errors']) ? $_SESSION['login_errors'] : [];
$login_input_email = isset($_SESSION['login_input_email']) ? $_SESSION['login_input_email'] : '';
unset($_SESSION['login_errors']);
unset($_SESSION['login_input_email']);

// Retrieve registration success message
$registration_success = isset($_SESSION['registration_success']) ? $_SESSION['registration_success'] : null;
unset($_SESSION['registration_success']);

?>

<!-- Page Title -->
<section class="section py-5">
    <div class="container">
        <div class="section-title text-center">
            <h2><?php echo ($is_logged_in) ? 'Tableau de Bord' : 'Votre Compte'; ?></h2>
            <p><?php echo ($is_logged_in) ? 'Bienvenue, ' . htmlspecialchars($_SESSION["user_prenom"]) . '. Gérez votre profil et vos activités.' : 'Connectez-vous ou créez un compte pour gérer votre profil et suivre vos activités immobilières'; ?></p>
        </div>
    </div>
</section>

<?php if ($is_logged_in): ?>
    <section class="section bg-light py-5">
        <div class="container">
            <h3>Vos Informations</h3>
            <p><strong>Nom:</strong> <?php echo htmlspecialchars($_SESSION['user_nom']); ?></p>
            <p><strong>Prénom:</strong> <?php echo htmlspecialchars($_SESSION['user_prenom']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            <p><strong>Type de compte:</strong> <?php echo htmlspecialchars($_SESSION['user_type']); ?></p>
            
            <?php if ($_SESSION['user_type'] == 'client'): ?>
                <h4>Mes Rendez-vous</h4>
                <p><em>(Section des rendez-vous à venir ici)</em></p>
                <!-- TODO: Fetch and display client's appointments -->
                 <a href="rendez-vous.php" class="btn btn-info">Voir mes rendez-vous</a>

            <?php elseif ($_SESSION['user_type'] == 'agent'): ?>
                <h4>Mon Tableau de Bord Agent</h4>
                <p><em>(Accès aux fonctionnalités agent ici)</em></p>
                <a href="agent/agent_dashboard.php" class="btn btn-info">Accéder à mon tableau de bord</a>
                <!-- TODO: Link to agent-specific functionalities -->

            <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
                <h4>Panneau d'Administration</h4>
                <p><em>(Accès aux fonctionnalités admin ici)</em></p>
                <div class="list-group">
                    <a href="admin/manage_properties.php" class="list-group-item list-group-item-action">Gérer les Propriétés</a>
                    <a href="admin/manage_agents.php" class="list-group-item list-group-item-action">Gérer les Agents</a>
                    <a href="admin/manage_users.php" class="list-group-item list-group-item-action">Gérer les Utilisateurs</a>
                    <a href="admin/view_appointments.php" class="list-group-item list-group-item-action">Voir tous les RDV</a>
                    <!-- Add more admin links as needed -->
                </div>
            <?php endif; ?>

            <hr class="my-4">
            <a href="php/actions/logout_action.php" class="btn btn-danger">Se déconnecter</a>
        </div>
    </section>

<?php else: ?>
    <!-- Connexion / Inscription Forms -->
    <section class="section py-5">
        <div class="container">

            <?php if ($registration_success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($registration_success); ?>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <!-- Connexion -->
                <div class="col-lg-5 mb-4 mb-lg-0" id="connexion">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="card-title text-center mb-4">Connexion</h3>
                            <?php if (isset($login_errors['login'])): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_errors['login']); ?></div>
                            <?php endif; ?>
                             <?php if (isset($login_errors['database'])): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_errors['database']); ?></div>
                            <?php endif; ?>
                            <form action="php/actions/login_action.php" method="POST">
                                <div class="form-group mb-3">
                                    <label for="loginEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control <?php echo isset($login_errors['email']) ? 'is-invalid' : ''; ?>" id="loginEmail" name="email" value="<?php echo htmlspecialchars($login_input_email); ?>" required>
                                    <?php if (isset($login_errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($login_errors['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="loginPassword" class="form-label">Mot de passe</label>
                                    <input type="password" class="form-control <?php echo isset($login_errors['password']) ? 'is-invalid' : ''; ?>" id="loginPassword" name="password" required>
                                    <?php if (isset($login_errors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($login_errors['password']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-sign-in-alt"></i> Se connecter
                                    </button>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="#" class="text-muted">Mot de passe oublié ?</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Inscription -->
                <div class="col-lg-7" id="inscription">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="card-title text-center mb-4">Inscription</h3>
                             <?php if (isset($registration_errors['database'])): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($registration_errors['database']); ?></div>
                            <?php endif; ?>
                            <form action="php/actions/register_action.php" method="POST">
                                <div class="row">
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="regNom" class="form-label">Nom</label>
                                        <input type="text" class="form-control <?php echo isset($registration_errors['nom']) ? 'is-invalid' : ''; ?>" id="regNom" name="nom" value="<?php echo htmlspecialchars($registration_input['nom'] ?? ''); ?>" required>
                                        <?php if (isset($registration_errors['nom'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['nom']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="regPrenom" class="form-label">Prénom</label>
                                        <input type="text" class="form-control <?php echo isset($registration_errors['prenom']) ? 'is-invalid' : ''; ?>" id="regPrenom" name="prenom" value="<?php echo htmlspecialchars($registration_input['prenom'] ?? ''); ?>" required>
                                        <?php if (isset($registration_errors['prenom'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['prenom']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="regEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control <?php echo isset($registration_errors['email']) ? 'is-invalid' : ''; ?>" id="regEmail" name="email" value="<?php echo htmlspecialchars($registration_input['email'] ?? ''); ?>" required>
                                    <?php if (isset($registration_errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="regTel" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control <?php echo isset($registration_errors['telephone']) ? 'is-invalid' : ''; ?>" id="regTel" name="telephone" value="<?php echo htmlspecialchars($registration_input['telephone'] ?? ''); ?>" required>
                                     <?php if (isset($registration_errors['telephone'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['telephone']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="regPassword" class="form-label">Mot de passe</label>
                                        <input type="password" class="form-control <?php echo isset($registration_errors['password']) ? 'is-invalid' : ''; ?>" id="regPassword" name="password" required>
                                        <?php if (isset($registration_errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['password']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="regConfirmPassword" class="form-label">Confirmer le mot de passe</label>
                                        <input type="password" class="form-control <?php echo isset($registration_errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="regConfirmPassword" name="confirm_password" required>
                                        <?php if (isset($registration_errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['confirm_password']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group form-check mb-3">
                                    <input type="checkbox" class="form-check-input <?php echo isset($registration_errors['conditions']) ? 'is-invalid' : ''; ?>" id="conditions" name="conditions" required>
                                    <label class="form-check-label" for="conditions">J'accepte les conditions générales d'utilisation</label>
                                    <?php if (isset($registration_errors['conditions'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($registration_errors['conditions']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-user-plus"></i> Créer un compte
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Avantages du compte (This section can remain as is or be conditional) -->
<?php if (!$is_logged_in): // Optionally hide this section if logged in ?>
<section class="section py-5">
    <div class="container">
        <div class="section-title text-center">
            <h2>Les avantages de votre compte</h2>
        </div>
        
        <div class="row">
            <!-- Avantage 1 -->
            <div class="col-md-6 col-lg-3 mb-4 d-flex align-items-stretch">
                <div class="card property-card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Favoris personnalisés</h5>
                        <p class="card-text">Enregistrez vos biens préférés et recevez des alertes personnalisées.</p>
                    </div>
                </div>
            </div>
            
            <!-- Avantage 2 -->
            <div class="col-md-6 col-lg-3 mb-4 d-flex align-items-stretch">
                <div class="card property-card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Gestion des rendez-vous</h5>
                        <p class="card-text">Suivez vos rendez-vous, modifiez-les et recevez des rappels.</p>
                    </div>
                </div>
            </div>
            
            <!-- Avantage 3 -->
            <div class="col-md-6 col-lg-3 mb-4 d-flex align-items-stretch">
                <div class="card property-card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-comments fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Messagerie privée</h5>
                        <p class="card-text">Communiquez directement avec nos agents immobiliers.</p>
                    </div>
                </div>
            </div>
            
            <!-- Avantage 4 -->
            <div class="col-md-6 col-lg-3 mb-4 d-flex align-items-stretch">
                <div class="card property-card h-100 text-center shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-file-contract fa-3x text-info mb-3"></i>
                        <h5 class="card-title">Suivi de dossier</h5>
                        <p class="card-text">Accédez à l'historique de vos transactions et projets.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'php/includes/footer.php'; ?> 