<?php
/**
 * Page Tout Parcourir d'Omnes Immobilier
 * Ce fichier récupère les données des catégories et des biens immobiliers
 * et les injecte dans le template HTML
 */

// Inclure le fichier de modèle
require_once 'BACK-END/model.php';

// Démarrer la session
session_start();

// Récupérer toutes les catégories
$categories = getToutesCategories();

// Charger le template HTML
$template = file_get_contents('FRONT-END/tout_parcourir.html');

// AJOUTEZ CES LIGNES pour corriger les chemins
$template = str_replace('href="style_commun.css"', 'href="RESSOURCES/style_commun.css"', $template);
$template = str_replace('href="style_parcourir.css"', 'href="RESSOURCES/style_parcourir.css"', $template);
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

// Préparer les onglets des catégories
$ongletsCategories = '';
$contenuCategories = '';
$estPremier = true;

foreach ($categories as $index => $categorie) {
    // Créer l'onglet
    $classeActive = $estPremier ? 'active' : '';
    $ongletsCategories .= '
    <li class="nav-item">
        <a class="nav-link ' . $classeActive . '" id="categorie-' . $categorie['id_categorie'] . '-tab" data-toggle="tab" 
           href="#categorie-' . $categorie['id_categorie'] . '" role="tab">' . $categorie['nom_categorie'] . '</a>
    </li>';
    
    // Récupérer les biens de cette catégorie
    $biens = getBiensParCategorie($categorie['id_categorie']);
    
    // Créer le contenu de l'onglet
    $classeAffichage = $estPremier ? 'show active' : '';
    $contenuCategories .= '
    <div class="tab-pane fade ' . $classeAffichage . '" id="categorie-' . $categorie['id_categorie'] . '" role="tabpanel">
        <div class="category-description mt-4 mb-4">
            <p>' . $categorie['nom_categorie'] . '</p>
        </div>
        <div class="row">';
    
    // Ajouter les biens
    if (count($biens) > 0) {
        foreach ($biens as $bien) {
            $contenuCategories .= '
            <div class="col-md-4 mb-4">
                <div class="card property-card">
                    <img src="' . $bien['image'] . '" class="card-img-top" alt="' . $bien['titre'] . '">
                    <div class="card-body">
                        <h5 class="card-title">' . $bien['titre'] . '</h5>
                        <p class="card-text"><strong>Localisation:</strong> ' . $bien['ville'] . '</p>
                        <p class="card-text"><strong>Surface:</strong> ' . formaterSurface($bien['surface']) . '</p>';
            
            if ($bien['nb_chambres']) {
                $contenuCategories .= '<p class="card-text"><strong>Chambres:</strong> ' . $bien['nb_chambres'] . '</p>';
            }
            
            $contenuCategories .= '
                        <p class="card-text price">' . formaterPrix($bien['prix']) . '</p>
                        <a href="bien.php?id=' . $bien['id_bien'] . '" class="btn btn-primary">Voir détails</a>
                    </div>
                </div>
            </div>';
        }
    } else {
        $contenuCategories .= '
            <div class="col-12">
                <div class="alert alert-info">
                    Aucun bien disponible dans cette catégorie pour le moment.
                </div>
            </div>';
    }
    
    $contenuCategories .= '
        </div>
        <div class="pagination-container mt-4 text-center">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Précédent</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>';
    
    $estPremier = false;
}

// Insérer les onglets et le contenu dans le template
$template = str_replace('{ONGLETS_CATEGORIES}', $ongletsCategories, $template);
$template = str_replace('{CONTENU_CATEGORIES}', $contenuCategories, $template);

// Afficher la page
echo $template;
?>
