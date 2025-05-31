<?php
/**
 * Page d'accueil d'Omnes Immobilier
 * Ce fichier récupère les données et les injecte dans le template HTML
 */

// Inclure le fichier de modèle
require_once 'BACK-END/model.php';

// Démarrer la session
session_start();

// Récupérer les données nécessaires
$biensEnVedette = getProprietesEnVedette();
$evenementHebdomadaire = getEvenementHebdomadaire();

// Charger le template HTML
$template = file_get_contents('FRONT-END/homepage.html');

// Remplacer les marqueurs de l'événement par les données réelles
$template = str_replace('{TITRE_EVENEMENT}', $evenementHebdomadaire['titre'], $template);
$template = str_replace('{DESCRIPTION_EVENEMENT}', $evenementHebdomadaire['description'], $template);
$template = str_replace('{DATE_EVENEMENT}', date('d/m/Y', strtotime($evenementHebdomadaire['date_debut'])), $template);
$template = str_replace('{HEURE_EVENEMENT}', date('H:i', strtotime($evenementHebdomadaire['date_debut'])), $template);
$template = str_replace('{LIEU_EVENEMENT}', $evenementHebdomadaire['lieu'], $template);

// AJOUTEZ CES LIGNES pour corriger les chemins
$template = str_replace('href="style_commun.css"', 'href="RESSOURCES/style_commun.css"', $template);
$template = str_replace('href="style_accueil.css"', 'href="RESSOURCES/style_accueil.css"', $template);
$template = str_replace('src="logo.png"', 'src="RESSOURCES/IMAGES/logo.png"', $template);
$template = str_replace('src="propriete1.png"', 'src="RESSOURCES/IMAGES/propriete1.png"', $template);
$template = str_replace('src="propriete2.png"', 'src="RESSOURCES/IMAGES/propriete2.png"', $template);
$template = str_replace('src="propriete3.png"', 'src="RESSOURCES/IMAGES/propriete3.png"', $template);
$template = str_replace('src="propriete4.png"', 'src="RESSOURCES/IMAGES/propriete4.png"', $template);

$template = str_replace('href="homepage.html"', 'href="index.php"', $template);
$template = str_replace('href="tout_parcourir.html"', 'href="tout-parcourir.php"', $template);
$template = str_replace('href="recherche.html"', 'href="recherche.php"', $template);
$template = str_replace('href="rendez_vous.html"', 'href="rendez_vous.php"', $template);
$template = str_replace('href="votre_compte.html"', 'href="votre_compte.php"', $template);

// Préparer le HTML pour les propriétés en vedette
$biensHTML = '';
foreach ($biensEnVedette as $bien) {
    $biensHTML .= '
    <div class="col-md-3 mb-4">
        <div class="card property-card">
            <img src="' . $bien['image'] . '" class="card-img-top" alt="' . $bien['titre'] . '">
            <div class="card-body">
                <h5 class="card-title">' . $bien['titre'] . '</h5>
                <p class="card-text"><strong>Localisation:</strong> ' . $bien['ville'] . '</p>
                <p class="card-text"><strong>Surface:</strong> ' . formaterSurface($bien['surface']) . '</p>';
    
    if ($bien['nb_chambres']) {
        $biensHTML .= '<p class="card-text"><strong>Chambres:</strong> ' . $bien['nb_chambres'] . '</p>';
    }
    
    $biensHTML .= '
                <p class="card-text price">' . formaterPrix($bien['prix']) . '</p>
                <a href="bien.php?id=' . $bien['id_bien'] . '" class="btn btn-primary">Voir détails</a>
            </div>
        </div>
    </div>';
}

// Insérer le HTML des propriétés dans le template
$template = str_replace('{BIENS_EN_VEDETTE}', $biensHTML, $template);

// Afficher la page
echo $template;
?>
