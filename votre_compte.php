<?php
/**
 * Page Votre Compte d'Omnes Immobilier
 * Ce fichier gère l'authentification, l'inscription et la gestion du profil utilisateur
 */

// Inclure le fichier de modèle
require_once 'BACK-END/model.php';

// Démarrer la session
session_start();

// Initialiser les variables
$action = isset($_GET['action']) ? $_GET['action'] : 'connexion';
$erreur = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'connexion' && isset($_POST['email']) && isset($_POST['mot_de_passe'])) {
        $email = nettoyerEntree($_POST['email']);
        $motDePasse = $_POST['mot_de_passe'];
        
        // Vérifier les identifiants
        $utilisateur = verifierIdentifiants($email, $motDePasse);
        
        if ($utilisateur) {
            // Connexion réussie
            $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
            $_SESSION['nom'] = $utilisateur['nom'];
            $_SESSION['prenom'] = $utilisateur['prenom'];
            $_SESSION['email'] = $utilisateur['email'];
            $_SESSION['role'] = $utilisateur['type_utilisateur'];
            
            // Rediriger vers la page précédente ou l'accueil
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $erreur = 'Identifiants incorrects. Veuillez réessayer.';
        }
    } elseif ($action === 'inscription') {
        // Traitement du formulaire d'inscription
        $nom = nettoyerEntree($_POST['nom']);
        $prenom = nettoyerEntree($_POST['prenom']);
        $email = nettoyerEntree($_POST['email']);
        $motDePasse = $_POST['mot_de_passe'];
        $confirmMotDePasse = $_POST['confirm_mot_de_passe'];
        $telephone = nettoyerEntree($_POST['telephone']);
        $adresseLigne1 = isset($_POST['adresse_ligne1']) ? nettoyerEntree($_POST['adresse_ligne1']) : '';
        $adresseLigne2 = isset($_POST['adresse_ligne2']) ? nettoyerEntree($_POST['adresse_ligne2']) : '';
        $ville = isset($_POST['ville']) ? nettoyerEntree($_POST['ville']) : '';
        $codePostal = isset($_POST['code_postal']) ? nettoyerEntree($_POST['code_postal']) : '';
        $pays = isset($_POST['pays']) ? nettoyerEntree($_POST['pays']) : 'France';
        
        // Vérifier que les mots de passe correspondent
        if ($motDePasse !== $confirmMotDePasse) {
            $erreur = 'Les mots de passe ne correspondent pas.';
        } else {
            // Créer le compte client
            $idUtilisateur = creerCompteClient($email, $motDePasse, $nom, $prenom, $telephone, $adresseLigne1, $adresseLigne2, $ville, $codePostal, $pays);
            
            if ($idUtilisateur) {
                // Connexion automatique après inscription
                $_SESSION['id_utilisateur'] = $idUtilisateur;
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'client';
                
                // Rediriger vers la page d'accueil
                header('Location: index.php?inscription=success');
                exit;
            } else {
                $erreur = 'Une erreur est survenue lors de la création de votre compte.';
            }
        }
    }
}

// Traitement de la déconnexion
if ($action === 'deconnexion') {
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Rediriger vers la page d'accueil
    header('Location: index.php');
    exit;
}

// Charger le template HTML
$template = file_get_contents('FRONT-END/votre_compte.html');

// Corriger les chemins des ressources
$template = str_replace('href="style_commun.css"', 'href="RESSOURCES/style_commun.css"', $template);
$template = str_replace('href="style_votre_compte.css"', 'href="RESSOURCES/style_votre_compte.css"', $template);
$template = str_replace('src="logo.png"', 'src="RESSOURCES/IMAGES/logo.png"', $template);

// Corriger les liens de navigation
$template = str_replace('href="homepage.html"', 'href="index.php"', $template);
$template = str_replace('href="tout_parcourir.html"', 'href="tout-parcourir.php"', $template);
$template = str_replace('href="recherche.html"', 'href="recherche.php"', $template);
$template = str_replace('href="rendez_vous.html"', 'href="rendez_vous.php"', $template);
$template = str_replace('href="votre_compte.html"', 'href="votre_compte.php"', $template);

// Préparer le contenu selon l'action et l'état de connexion
$contenuHTML = '';

// Afficher les messages d'erreur ou de succès
if (!empty($erreur)) {
    $contenuHTML .= '
    <div class="alert alert-danger">
        ' . $erreur . '
    </div>';
}

if (!empty($success)) {
    $contenuHTML .= '
    <div class="alert alert-success">
        ' . $success . '
    </div>';
}

// Afficher le message de connexion requise
if (isset($_GET['message']) && $_GET['message'] === 'connexion_requise') {
    $contenuHTML .= '
    <div class="alert alert-warning">
        Vous devez être connecté pour accéder à cette fonctionnalité.
    </div>';
}

// Afficher le contenu selon l'action
if (isset($_SESSION['id_utilisateur'])) {
    // Utilisateur connecté
    if ($action === 'profil') {
        // Récupérer les informations du profil
        $profil = getClientInfo($_SESSION['id_utilisateur']);
        
        $contenuHTML .= '
        <h2 class="mb-4">Modifier votre profil</h2>
        <form method="post" action="votre_compte.php?action=profil">
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" value="' . $profil['nom'] . '" required>
            </div>
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" class="form-control" id="prenom" name="prenom" value="' . $profil['prenom'] . '" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" value="' . $profil['email'] . '" disabled>
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" class="form-control" id="telephone" name="telephone" value="' . $profil['telephone'] . '">
            </div>';
            
        // Afficher les champs spécifiques au client
        if ($_SESSION['role'] === 'client') {
            $contenuHTML .= '
            <h4 class="mt-4">Adresse</h4>
            <div class="form-group">
                <label for="adresse_ligne1">Adresse (ligne 1)</label>
                <input type="text" class="form-control" id="adresse_ligne1" name="adresse_ligne1" value="' . $profil['adresse_ligne1'] . '">
            </div>
            <div class="form-group">
                <label for="adresse_ligne2">Adresse (ligne 2)</label>
                <input type="text" class="form-control" id="adresse_ligne2" name="adresse_ligne2" value="' . $profil['adresse_ligne2'] . '">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="ville">Ville</label>
                    <input type="text" class="form-control" id="ville" name="ville" value="' . $profil['ville'] . '">
                </div>
                <div class="form-group col-md-3">
                    <label for="code_postal">Code postal</label>
                    <input type="text" class="form-control" id="code_postal" name="code_postal" value="' . $profil['code_postal'] . '">
                </div>
                <div class="form-group col-md-3">
                    <label for="pays">Pays</label>
                    <input type="text" class="form-control" id="pays" name="pays" value="' . $profil['pays'] . '">
                </div>
            </div>';
        }
            
        $contenuHTML .= '
            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            <a href="votre_compte.php" class="btn btn-secondary">Annuler</a>
        </form>';
    } else {
        // Tableau de bord utilisateur
        $contenuHTML .= '
        <h2 class="mb-4">Bienvenue, ' . $_SESSION['prenom'] . ' ' . $_SESSION['nom'] . '</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Votre profil</h5>
                        <p class="card-text">Consultez et modifiez vos informations personnelles.</p>
                        <a href="votre_compte.php?action=profil" class="btn btn-primary">Modifier le profil</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Vos rendez-vous</h5>
                        <p class="card-text">Consultez et gérez vos rendez-vous avec nos agents immobiliers.</p>
                        <a href="rendez_vous.php" class="btn btn-primary">Voir les rendez-vous</a>
                    </div>
                </div>
            </div>';
            
        // Afficher les options spécifiques selon le rôle
        if ($_SESSION['role'] === 'admin') {
            $contenuHTML .= '
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Administration</h5>
                        <p class="card-text">Gérez les biens, les agents et les utilisateurs.</p>
                        <a href="admin.php" class="btn btn-primary">Accéder à l\'administration</a>
                    </div>
                </div>
            </div>';
        } elseif ($_SESSION['role'] === 'agent') {
            $contenuHTML .= '
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Vos disponibilités</h5>
                        <p class="card-text">Gérez votre calendrier et vos disponibilités.</p>
                        <a href="disponibilites.php" class="btn btn-primary">Gérer les disponibilités</a>
                    </div>
                </div>
            </div>';
        } else {
            $contenuHTML .= '
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Favoris</h5>
                        <p class="card-text">Consultez les biens que vous avez ajoutés à vos favoris.</p>
                        <a href="favoris.php" class="btn btn-primary">Voir les favoris</a>
                    </div>
                </div>
            </div>';
        }
            
        $contenuHTML .= '
        </div>
        
        <div class="mt-4">
            <a href="votre_compte.php?action=deconnexion" class="btn btn-danger">Se déconnecter</a>
        </div>';
    }
} else {
    // Utilisateur non connecté
    if ($action === 'inscription') {
        // Formulaire d'inscription
        $contenuHTML .= '
        <h2 class="mb-4">Créer un compte</h2>
        <form method="post" action="votre_compte.php?action=inscription">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="nom">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="prenom">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" class="form-control" id="telephone" name="telephone">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="confirm_mot_de_passe">Confirmer le mot de passe</label>
                    <input type="password" class="form-control" id="confirm_mot_de_passe" name="confirm_mot_de_passe" required>
                </div>
            </div>
            <div class="form-group">
                <label for="adresse_ligne1">Adresse (ligne 1)</label>
                <input type="text" class="form-control" id="adresse_ligne1" name="adresse_ligne1">
            </div>
            <div class="form-group">
                <label for="adresse_ligne2">Adresse (ligne 2)</label>
                <input type="text" class="form-control" id="adresse_ligne2" name="adresse_ligne2">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="ville">Ville</label>
                    <input type="text" class="form-control" id="ville" name="ville">
                </div>
                <div class="form-group col-md-3">
                    <label for="code_postal">Code postal</label>
                    <input type="text" class="form-control" id="code_postal" name="code_postal">
                </div>
                <div class="form-group col-md-3">
                    <label for="pays">Pays</label>
                    <input type="text" class="form-control" id="pays" name="pays" value="France">
                </div>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="conditions" required>
                <label class="form-check-label" for="conditions">J\'accepte les conditions d\'utilisation et la politique de confidentialité</label>
            </div>
            <button type="submit" class="btn btn-primary">S\'inscrire</button>
            <p class="mt-3">Vous avez déjà un compte ? <a href="votre_compte.php">Connectez-vous</a></p>
        </form>';
    } else {
        // Formulaire de connexion
        $contenuHTML .= '
        <h2 class="mb-4">Connexion</h2>
        <form method="post" action="votre_compte.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Se souvenir de moi</label>
            </div>
            <button type="submit" class="btn btn-primary">Se connecter</button>
            <p class="mt-3">Vous n\'avez pas de compte ? <a href="votre_compte.php?action=inscription">Inscrivez-vous</a></p>
        </form>';
    }
}

// Insérer le contenu dans le template
$template = str_replace('{CONTENU_COMPTE}', $contenuHTML, $template);

// Afficher la page
echo $template;
?>
