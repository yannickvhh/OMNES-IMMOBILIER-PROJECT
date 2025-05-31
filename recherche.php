<?php
/**
 * Page Recherche d'Omnes Immobilier
 * Ce fichier gère la recherche de biens immobiliers, d'agents ou de villes
 */

// Inclure le fichier de modèle
require_once 'BACK-END/model.php';

// Démarrer la session
session_start();

// Initialiser les variables
$resultats = [];
$terme = '';
$type = '';

// Vérifier si une recherche a été soumise
if (isset($_GET['terme']) && !empty($_GET['terme'])) {
    $terme = nettoyerEntree($_GET['terme']);
    $type = isset($_GET['type']) ? nettoyerEntree($_GET['type']) : 'bien';
    
    // Effectuer la recherche selon le type
    switch ($type) {
        case 'agent':
            // Recherche d'un agent par nom
            $resultats = rechercherAgentParNom($terme);
            break;
        case 'ville':
            // Recherche de biens par ville
            $resultats = rechercherBiensParVille($terme);
            break;
        default:
            // Recherche générale (par défaut)
            $resultats = rechercherBiensParVille($terme);
    }
}

// Charger le template HTML
$template = file_get_contents('FRONT-END/recherche.html');

// Corriger les chemins des ressources
$template = str_replace('href="style_commun.css"', 'href="RESSOURCES/style_commun.css"', $template);
$template = str_replace('href="style_recherche.css"', 'href="RESSOURCES/style_recherche.css"', $template);
$template = str_replace('src="logo.png"', 'src="RESSOURCES/IMAGES/logo.png"', $template);
$template = str_replace('src="propriete1.png"', 'src="RESSOURCES/IMAGES/propriete1.png"', $template);
$template = str_replace('src="propriete2.png"', 'src="RESSOURCES/IMAGES/propriete2.png"', $template);

// Corriger les liens de navigation
$template = str_replace('href="homepage.html"', 'href="index.php"', $template);
$template = str_replace('href="tout_parcourir.html"', 'href="tout-parcourir.php"', $template);
$template = str_replace('href="recherche.html"', 'href="recherche.php"', $template);
$template = str_replace('href="rendez_vous.html"', 'href="rendez_vous.php"', $template);
$template = str_replace('href="votre_compte.html"', 'href="votre_compte.php"', $template);

// Préparer le HTML pour les résultats de recherche
$resultatsHTML = '';

if (!empty($terme)) {
    if (count($resultats) > 0) {
        switch ($type) {
            case 'agent':
                // Affichage des agents trouvés
                foreach ($resultats as $agent) {
                    $resultatsHTML .= '
                    <div class="col-md-4 mb-4">
                        <div class="card agent-card">
                            <img src="' . $agent['photo'] . '" class="card-img-top" alt="' . $agent['prenom'] . ' ' . $agent['nom'] . '">
                            <div class="card-body">
                                <h5 class="card-title">' . $agent['prenom'] . ' ' . $agent['nom'] . '</h5>
                                <p class="card-text"><strong>Spécialité:</strong> ' . $agent['specialite'] . '</p>
                                <p class="card-text"><strong>Email:</strong> ' . $agent['email'] . '</p>
                                <p class="card-text"><strong>Téléphone:</strong> ' . $agent['telephone'] . '</p>
                                <a href="agent.php?id=' . $agent['id_agent'] . '" class="btn btn-primary">Voir profil</a>
                            </div>
                        </div>
                    </div>';
                }
                break;
                
            case 'bien':
            case 'ville':
            default:
                // Affichage des biens trouvés
                foreach ($resultats as $bien) {
                    $resultatsHTML .= '
                    <div class="col-md-4 mb-4">
                        <div class="card property-card">
                            <img src="' . $bien['image'] . '" class="card-img-top" alt="' . $bien['titre'] . '">
                            <div class="card-body">
                                <h5 class="card-title">' . $bien['titre'] . '</h5>
                                <p class="card-text"><strong>Localisation:</strong> ' . $bien['ville'] . '</p>
                                <p class="card-text"><strong>Surface:</strong> ' . formaterSurface($bien['surface']) . '</p>';
                    
                    if ($bien['nb_chambres']) {
                        $resultatsHTML .= '<p class="card-text"><strong>Chambres:</strong> ' . $bien['nb_chambres'] . '</p>';
                    }
                    
                    $resultatsHTML .= '
                                <p class="card-text price">' . formaterPrix($bien['prix']) . '</p>
                                <a href="bien.php?id=' . $bien['id_bien'] . '" class="btn btn-primary">Voir détails</a>
                            </div>
                        </div>
                    </div>';
                }
        }
    } else {
        $resultatsHTML = '
        <div class="col-12">
            <div class="alert alert-info">
                Aucun résultat trouvé pour votre recherche "' . $terme . '".
            </div>
        </div>';
    }
}

// Insérer les résultats dans le template
$template = str_replace('{RESULTATS_RECHERCHE}', $resultatsHTML, $template);
$template = str_replace('{TERME_RECHERCHE}', $terme, $template);

// Marquer le type de recherche sélectionné
$template = str_replace('value="' . $type . '"', 'value="' . $type . '" selected', $template);

// Afficher la page
echo $template;
?>
