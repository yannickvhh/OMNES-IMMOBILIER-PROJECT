<?php
/**
 * Page de prise de rendez-vous d'Omnes Immobilier
 * Ce fichier gère l'affichage et la prise de rendez-vous avec les agents immobiliers
 */

// Inclure le fichier de modèle
require_once 'BACK-END/model.php';

// Démarrer la session
session_start();

// Initialiser les variables
$message = '';
$messageType = '';
$idAgent = isset($_GET['agent']) ? intval($_GET['agent']) : null;
$idBien = isset($_GET['bien']) ? intval($_GET['bien']) : null;
$agent = null;
$bien = null;
$disponibilites = [];

// Vérifier si l'utilisateur est connecté
$estConnecte = isset($_SESSION['id_utilisateur']);

// Si un agent est spécifié, récupérer ses informations et disponibilités
if ($idAgent) {
    $agent = getAgentById($idAgent);
    if ($agent) {
        $disponibilites = getDisponibilitesAgent($idAgent);
    }
}

// Si un bien est spécifié, récupérer ses informations
if ($idBien) {
    $bien = getBienParId($idBien);
}

// Traitement du formulaire de prise de rendez-vous
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prendre_rdv'])) {
    if (!$estConnecte) {
        // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
        $_SESSION['redirect_after_login'] = "rendez_vous.php?agent=$idAgent&bien=$idBien";
        header('Location: votre_compte.php?message=connexion_requise');
        exit;
    }
    
    $idAgent = intval($_POST['id_agent']);
    $idBien = isset($_POST['id_bien']) ? intval($_POST['id_bien']) : null;
    $date = $_POST['date'];
    $heure = $_POST['heure'];
    $motif = $_POST['motif'];
    
    // Vérifier que la date et l'heure sont valides
    $dateHeure = "$date $heure:00";
    $timestamp = strtotime($dateHeure);
    $now = time();
    
    if ($timestamp <= $now) {
        $message = "La date et l'heure du rendez-vous doivent être dans le futur.";
        $messageType = "danger";
    } else {
        // Vérifier que le créneau est disponible
        if (estCreneauDisponible($idAgent, $dateHeure)) {
            // Enregistrer le rendez-vous
            $idRdv = prendreRendezVous($_SESSION['id_utilisateur'], $idAgent, $idBien, $dateHeure, $motif);
            
            if ($idRdv) {
                $message = "Votre rendez-vous a été pris avec succès. Un email de confirmation vous a été envoyé.";
                $messageType = "success";
                
                // Rediriger vers la page de confirmation
                header("Location: rendez_vous.php?confirmation=$idRdv");
                exit;
            } else {
                $message = "Une erreur est survenue lors de la prise de rendez-vous. Veuillez réessayer.";
                $messageType = "danger";
            }
        } else {
            $message = "Ce créneau n'est plus disponible. Veuillez en choisir un autre.";
            $messageType = "warning";
        }
    }
}

// Récupérer les rendez-vous de l'utilisateur connecté
$rendezVous = [];
if ($estConnecte) {
    $rendezVous = getRendezVousUtilisateur($_SESSION['id_utilisateur']);
}

// Traitement de l'annulation d'un rendez-vous
if (isset($_GET['action']) && $_GET['action'] == 'annuler' && isset($_GET['id'])) {
    $idRdv = intval($_GET['id']);
    
    // Vérifier que le rendez-vous appartient bien à l'utilisateur connecté
    if ($estConnecte && appartientRendezVousUtilisateur($idRdv, $_SESSION['id_utilisateur'])) {
        annulerRendezVous($idRdv);
        // Rediriger pour éviter les soumissions multiples
        header('Location: rendez_vous.php?annulation=success');
        exit;
    }
}

// Charger le template HTML
$template = file_get_contents('FRONT-END/rendez_vous.html');

// Corriger les chemins des ressources
$template = str_replace('href="style_commun.css"', 'href="RESSOURCES/style_commun.css"', $template);
$template = str_replace('href="style_rendez_vous.css"', 'href="RESSOURCES/style_rendez_vous.css"', $template);
$template = str_replace('src="logo.png"', 'src="RESSOURCES/IMAGES/logo.png"', $template);

// Corriger les liens de navigation
$template = str_replace('href="homepage.html"', 'href="index.php"', $template);
$template = str_replace('href="tout_parcourir.html"', 'href="tout-parcourir.php"', $template);
$template = str_replace('href="recherche.html"', 'href="recherche.php"', $template);
$template = str_replace('href="rendez_vous.html"', 'href="rendez_vous.php"', $template);
$template = str_replace('href="votre_compte.html"', 'href="votre_compte.php"', $template);

// Préparer le contenu selon le contexte
$contenuHTML = '';

// Afficher les messages
if (!empty($message)) {
    $contenuHTML .= '
    <div class="alert alert-' . $messageType . '">
        ' . $message . '
    </div>';
}

// Afficher la confirmation d'annulation
if (isset($_GET['annulation']) && $_GET['annulation'] == 'success') {
    $contenuHTML .= '
    <div class="alert alert-success">
        Votre rendez-vous a été annulé avec succès.
    </div>';
}

// Afficher la confirmation de prise de rendez-vous
if (isset($_GET['confirmation'])) {
    $idRdv = intval($_GET['confirmation']);
    $rdvDetails = getRendezVousDetails($idRdv);
    
    if ($rdvDetails) {
        $contenuHTML .= '
        <div class="alert alert-success">
            <h4>Rendez-vous confirmé !</h4>
            <p>Votre rendez-vous a été enregistré avec succès.</p>
            <ul>
                <li><strong>Date :</strong> ' . date('d/m/Y', strtotime($rdvDetails['date_heure'])) . '</li>
                <li><strong>Heure :</strong> ' . date('H:i', strtotime($rdvDetails['date_heure'])) . '</li>
                <li><strong>Agent :</strong> ' . $rdvDetails['prenom_agent'] . ' ' . $rdvDetails['nom_agent'] . '</li>
                <li><strong>Lieu :</strong> ' . $rdvDetails['lieu'] . '</li>
            </ul>
            <p>Un email de confirmation vous a été envoyé.</p>
        </div>';
    }
}

// Si un agent est spécifié, afficher le formulaire de prise de rendez-vous
if ($agent) {
    $contenuHTML .= '
    <div class="row">
        <div class="col-md-8">
            <h2>Prendre rendez-vous avec ' . $agent['prenom'] . ' ' . $agent['nom'] . '</h2>';
    
    if (!$estConnecte) {
        $contenuHTML .= '
            <div class="alert alert-warning">
                Vous devez être <a href="votre_compte.php">connecté</a> pour prendre rendez-vous.
            </div>';
    } else {
        $contenuHTML .= '
            <form method="post" action="rendez_vous.php">
                <input type="hidden" name="id_agent" value="' . $agent['id_agent'] . '">';
        
        if ($bien) {
            $contenuHTML .= '
                <input type="hidden" name="id_bien" value="' . $bien['id_bien'] . '">
                <div class="form-group">
                    <label>Bien immobilier</label>
                    <input type="text" class="form-control" value="' . $bien['titre'] . '" readonly>
                </div>';
        }
        
        $contenuHTML .= '
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" min="' . date('Y-m-d') . '" required>
                </div>
                <div class="form-group">
                    <label for="heure">Heure</label>
                    <select class="form-control" id="heure" name="heure" required>
                        <option value="">Sélectionnez une heure</option>';
        
        // Heures de bureau (9h-18h)
        for ($h = 9; $h <= 17; $h++) {
            $contenuHTML .= '
                        <option value="' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00">' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00</option>
                        <option value="' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':30">' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':30</option>';
        }
        
        $contenuHTML .= '
                    </select>
                </div>
                <div class="form-group">
                    <label for="motif">Motif du rendez-vous</label>
                    <textarea class="form-control" id="motif" name="motif" rows="3" required>' . ($bien ? 'Visite du bien : ' . $bien['titre'] : '') . '</textarea>
                </div>
                <button type="submit" name="prendre_rdv" class="btn btn-primary">Prendre rendez-vous</button>
            </form>';
    }
    
    $contenuHTML .= '
        </div>
        <div class="col-md-4">
            <div class="card agent-card">
                <div class="card-body">
                    <h3>Votre agent</h3>
                    <div class="agent-info">
                        <img src="' . $agent['photo'] . '" alt="' . $agent['nom'] . '" class="agent-photo">
                        <div>
                            <h4>' . $agent['prenom'] . ' ' . $agent['nom'] . '</h4>
                            <p><i class="fas fa-phone"></i> ' . $agent['telephone'] . '</p>
                            <p><i class="fas fa-envelope"></i> ' . $agent['email'] . '</p>
                        </div>
                    </div>';
    
    if ($bien) {
        $contenuHTML .= '
                    <hr>
                    <h5>Bien concerné</h5>
                    <p>' . $bien['titre'] . '</p>
                    <p>' . $bien['adresse'] . ', ' . $bien['ville'] . '</p>
                    <p><strong>Prix:</strong> ' . formaterPrix($bien['prix']) . '</p>';
    }
    
    $contenuHTML .= '
                </div>
            </div>
        </div>
    </div>';
} else {
    // Afficher la liste des rendez-vous de l'utilisateur
    if ($estConnecte) {
        if (isset($_GET['annulation']) && $_GET['annulation'] == 'success') {
            $contenuHTML .= '
            <div class="alert alert-success">
                Votre rendez-vous a été annulé avec succès.
            </div>';
        }
        
        if (count($rendezVous) > 0) {
            $contenuHTML .= '
            <h2 class="mb-4">Vos rendez-vous</h2>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Agent</th>
                            <th>Bien</th>
                            <th>Adresse</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($rendezVous as $rdv) {
                $contenuHTML .= '
                    <tr>
                        <td>' . date('d/m/Y', strtotime($rdv['date_heure'])) . '</td>
                        <td>' . date('H:i', strtotime($rdv['date_heure'])) . '</td>
                        <td>' . $rdv['prenom_agent'] . ' ' . $rdv['nom_agent'] . '</td>
                        <td>' . $rdv['titre_bien'] . '</td>
                        <td>' . $rdv['adresse_bien'] . '</td>
                        <td><span class="badge badge-' . ($rdv['statut'] == 'confirme' ? 'success' : 'secondary') . '">' . ucfirst($rdv['statut']) . '</span></td>
                        <td>
                            <a href="rendez_vous.php?action=annuler&id=' . $rdv['id_rdv'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Êtes-vous sûr de vouloir annuler ce rendez-vous ?\')">Annuler</a>
                        </td>
                    </tr>';
            }
            
            $contenuHTML .= '
                    </tbody>
                </table>
            </div>';
        } else {
            $contenuHTML .= '
            <div class="alert alert-info">
                Vous n\'avez aucun rendez-vous programmé.
            </div>
            <p>Consultez nos <a href="tout-parcourir.php">biens immobiliers</a> pour prendre rendez-vous avec un agent.</p>';
        }
        
        // Ajouter une section pour prendre rendez-vous avec un agent
        $contenuHTML .= '
        <h3 class="mt-5">Prendre rendez-vous avec un agent</h3>
        <div class="row">';
        
        // Récupérer la liste des agents
        $agents = getAllAgents();
        
        foreach ($agents as $agent) {
            $contenuHTML .= '
            <div class="col-md-4 mb-4">
                <div class="card agent-card">
                    <img src="' . $agent['photo'] . '" class="card-img-top" alt="' . $agent['prenom'] . ' ' . $agent['nom'] . '">
                    <div class="card-body">
                        <h5 class="card-title">' . $agent['prenom'] . ' ' . $agent['nom'] . '</h5>
                        <p class="card-text"><strong>Spécialité:</strong> ' . $agent['specialite'] . '</p>
                        <a href="rendez_vous.php?agent=' . $agent['id_agent'] . '" class="btn btn-primary">Prendre rendez-vous</a>
                    </div>
                </div>
            </div>';
        }
        
        $contenuHTML .= '
        </div>';
    } else {
        $contenuHTML .= '
        <div class="alert alert-warning">
            Vous devez être connecté pour voir vos rendez-vous.
        </div>
        <p>Veuillez <a href="votre_compte.php">vous connecter</a> ou <a href="votre_compte.php?action=inscription">créer un compte</a> pour accéder à cette fonctionnalité.</p>';
    }
}

// Insérer le contenu dans le template
$template = str_replace('{CONTENU_RENDEZ_VOUS}', $contenuHTML, $template);

// Afficher la page
echo $template;
?>
