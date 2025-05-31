<?php
/**
 * Fichier de modèle pour Omnes Immobilier
 * Contient toutes les fonctions d'accès aux données et utilitaires
 */

// Fonction pour se connecter à la base de données
function connecterBDD() {
    $serveur = "localhost";
    $utilisateur = "root";
    $motDePasse = "";
    $baseDeDonnees = "omnes_immobilier";
    
    // Créer la connexion
    $conn = new mysqli($serveur, $utilisateur, $motDePasse, $baseDeDonnees);
    
    // Vérifier la connexion
    if ($conn->connect_error) {
        die("La connexion à la base de données a échoué : " . $conn->connect_error);
    }
    
    // Définir le jeu de caractères
    $conn->set_charset("utf8");
    
    return $conn;
}

// Fonction pour nettoyer les entrées utilisateur
function nettoyerEntree($donnee) {
    $donnee = trim($donnee);
    $donnee = stripslashes($donnee);
    $donnee = htmlspecialchars($donnee);
    return $donnee;
}

// Fonction pour formater le prix
function formaterPrix($prix) {
    return number_format($prix, 0, ',', ' ') . ' €';
}

// Fonction pour formater la surface
function formaterSurface($surface) {
    return number_format($surface, 2, ',', ' ') . ' m²';
}

// Fonction pour récupérer l'événement hebdomadaire
function getEvenementHebdomadaire() {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer l'événement hebdomadaire
    $sql = "SELECT * FROM evenements WHERE date_debut <= NOW() AND date_fin >= NOW() ORDER BY date_debut ASC LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $evenement = $result->fetch_assoc();
        $conn->close();
        return $evenement;
    } else {
        // Événement par défaut si aucun n'est trouvé
        $conn->close();
        return [
            'id_evenement' => 0,
            'titre' => 'Journée portes ouvertes',
            'description' => 'Ce samedi : journée portes ouvertes à notre agence avec visite guidée de 5 propriétés phares, et un séminaire sur l\'investissement locatif à 15h.',
            'date_debut' => date('Y-m-d', strtotime('next Saturday')) . ' 10:00:00',
            'date_fin' => date('Y-m-d', strtotime('next Saturday')) . ' 18:00:00',
            'lieu' => 'Agence Omnes Immobilier, 37 quai de Grenelle, 75015 Paris'
        ];
    }
}

// Fonction pour récupérer les propriétés en vedette
function getProprietesEnVedette($limite = 4) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les propriétés en vedette
    $sql = "SELECT b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image 
            FROM biens_immobiliers b 
            WHERE b.en_vedette = 1 AND b.disponible = 1 
            ORDER BY b.date_creation DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $biens = [];
    while ($row = $result->fetch_assoc()) {
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($row['image'])) {
            $row['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        $biens[] = $row;
    }
    
    $conn->close();
    return $biens;
}

// Fonction pour récupérer toutes les catégories
function getToutesCategories() {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer toutes les catégories
    $sql = "SELECT * FROM categories_bien ORDER BY nom_categorie";
    
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $conn->close();
    return $categories;
}

// Fonction pour récupérer les biens d'une catégorie
function getBiensParCategorie($idCategorie) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les biens d'une catégorie
    $sql = "SELECT b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image 
            FROM biens_immobiliers b 
            WHERE b.id_categorie = ? AND b.disponible = 1 
            ORDER BY b.date_creation DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idCategorie);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $biens = [];
    while ($row = $result->fetch_assoc()) {
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($row['image'])) {
            $row['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        $biens[] = $row;
    }
    
    $conn->close();
    return $biens;
}

// Fonction pour récupérer les détails d'un bien par son ID
function getBienParId($idBien) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les détails du bien et sa catégorie
    $sql = "SELECT b.*, c.nom_categorie 
            FROM biens_immobiliers b 
            LEFT JOIN categories_bien c ON b.id_categorie = c.id_categorie 
            WHERE b.id_bien = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idBien);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bien = $result->fetch_assoc();
        $conn->close();
        return $bien;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour récupérer les images d'un bien
function getImagesBien($idBien) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les images du bien
    $sql = "SELECT * FROM images_bien WHERE id_bien = ? ORDER BY ordre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idBien);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    $conn->close();
    
    // Si aucune image n'est trouvée, utiliser une image par défaut
    if (empty($images)) {
        $images[] = [
            'id_image' => 0,
            'id_bien' => $idBien,
            'url_image' => 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg',
            'ordre' => 1
        ];
    }
    
    return $images;
}

// Fonction pour récupérer les informations d'un agent
function getAgentById($idAgent) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les informations de l'agent
    $sql = "SELECT a.*, u.nom, u.prenom, u.email, u.telephone 
            FROM agents a 
            JOIN utilisateurs u ON a.id_utilisateur = u.id_utilisateur 
            WHERE a.id_agent = ? AND a.actif = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idAgent);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $agent = $result->fetch_assoc();
        
        // Si l'agent n'a pas de photo, utiliser une photo par défaut
        if (empty($agent['photo'])) {
            $agent['photo'] = 'RESSOURCES/IMAGES/agents/agent1.png';
        }
        
        $conn->close();
        return $agent;
    } else {
        // Agent par défaut si non trouvé
        $conn->close();
        return [
            'id_agent' => 1,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'contact@omnesimmobilier.fr',
            'telephone' => '01 23 45 67 89',
            'photo' => 'RESSOURCES/IMAGES/agents/agent1.png'
        ];
    }
}

// Fonction pour récupérer l'agent responsable d'un bien
function getAgentBien($idBien) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer l'agent responsable du bien
    $sql = "SELECT id_agent FROM biens_immobiliers WHERE id_bien = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idBien);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $idAgent = $row['id_agent'];
        $conn->close();
        return getAgentById($idAgent);
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour récupérer des biens similaires
function getBiensSimilaires($idBien, $idCategorie, $ville, $limite = 3) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer des biens similaires
    $sql = "SELECT b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image 
            FROM biens_immobiliers b 
            WHERE b.id_bien != ? 
            AND (b.id_categorie = ? OR b.ville = ?) 
            AND b.disponible = 1 
            ORDER BY RAND() 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $idBien, $idCategorie, $ville, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $biensSimilaires = [];
    while ($row = $result->fetch_assoc()) {
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($row['image'])) {
            $row['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        $biensSimilaires[] = $row;
    }
    
    $conn->close();
    return $biensSimilaires;
}

// Fonction pour récupérer tous les agents
function getAllAgents() {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer tous les agents
    $sql = "SELECT a.*, u.nom, u.prenom, u.email, u.telephone 
            FROM agents a 
            JOIN utilisateurs u ON a.id_utilisateur = u.id_utilisateur 
            WHERE a.actif = 1 
            ORDER BY u.nom, u.prenom";
    
    $result = $conn->query($sql);
    
    $agents = [];
    while ($row = $result->fetch_assoc()) {
        // Si l'agent n'a pas de photo, utiliser une photo par défaut
        if (empty($row['photo'])) {
            $row['photo'] = 'RESSOURCES/IMAGES/agents/agent1.png';
        }
        
        $agents[] = $row;
    }
    
    $conn->close();
    return $agents;
}

// Fonction pour récupérer les disponibilités d'un agent
function getDisponibilitesAgent($idAgent) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les disponibilités de l'agent
    $sql = "SELECT * FROM disponibilites_agent 
            WHERE id_agent = ? AND date_heure > NOW() 
            ORDER BY date_heure ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idAgent);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $disponibilites = [];
    while ($row = $result->fetch_assoc()) {
        $disponibilites[] = $row;
    }
    
    $conn->close();
    return $disponibilites;
}

// Fonction pour vérifier si un créneau est disponible
function estCreneauDisponible($idAgent, $dateHeure) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si le créneau existe dans les disponibilités
    $sql1 = "SELECT COUNT(*) as count FROM disponibilites_agent 
             WHERE id_agent = ? AND date_heure = ?";
    
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("is", $idAgent, $dateHeure);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    // Si le créneau n'existe pas dans les disponibilités, il n'est pas disponible
    if ($row1['count'] == 0) {
        $conn->close();
        return false;
    }
    
    // Vérifier si le créneau est déjà pris par un rendez-vous
    $sql2 = "SELECT COUNT(*) as count FROM rendez_vous 
             WHERE id_agent = ? AND date_heure = ? AND statut != 'annule'";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("is", $idAgent, $dateHeure);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    
    $conn->close();
    
    // Si aucun rendez-vous n'existe à cette date et heure, le créneau est disponible
    return $row2['count'] == 0;
}

// Fonction pour prendre un rendez-vous
function prendreRendezVous($idUtilisateur, $idAgent, $idBien, $dateHeure, $motif) {
    // Vérifier si le créneau est disponible
    if (!estCreneauDisponible($idAgent, $dateHeure)) {
        return false;
    }
    
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour insérer le rendez-vous
    $sql = "INSERT INTO rendez_vous (id_utilisateur, id_agent, id_bien, date_heure, motif, statut, date_creation) 
            VALUES (?, ?, ?, ?, ?, 'confirme', NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $idUtilisateur, $idAgent, $idBien, $dateHeure, $motif);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $idRdv = $stmt->insert_id;
        $conn->close();
        return $idRdv;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour récupérer les rendez-vous d'un utilisateur
function getRendezVousUtilisateur($idUtilisateur) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les rendez-vous de l'utilisateur
    $sql = "SELECT r.*, 
                   u_agent.nom as nom_agent, u_agent.prenom as prenom_agent,
                   b.titre as titre_bien, b.adresse as adresse_bien, b.ville as ville_bien, b.code_postal as code_postal_bien
            FROM rendez_vous r
            LEFT JOIN agents a ON r.id_agent = a.id_agent
            LEFT JOIN utilisateurs u_agent ON a.id_utilisateur = u_agent.id_utilisateur
            LEFT JOIN biens_immobiliers b ON r.id_bien = b.id_bien
            WHERE r.id_utilisateur = ? AND r.statut != 'annule'
            ORDER BY r.date_heure ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUtilisateur);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rendezVous = [];
    while ($row = $result->fetch_assoc()) {
        $rendezVous[] = $row;
    }
    
    $conn->close();
    return $rendezVous;
}

// Fonction pour vérifier qu'un rendez-vous appartient à un utilisateur
function appartientRendezVousUtilisateur($idRdv, $idUtilisateur) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour vérifier que le rendez-vous appartient à l'utilisateur
    $sql = "SELECT COUNT(*) as count FROM rendez_vous WHERE id_rdv = ? AND id_utilisateur = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idRdv, $idUtilisateur);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $conn->close();
    
    return $row['count'] > 0;
}

// Fonction pour annuler un rendez-vous
function annulerRendezVous($idRdv) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour annuler le rendez-vous
    $sql = "UPDATE rendez_vous SET statut = 'annule', date_modification = NOW() WHERE id_rdv = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idRdv);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour récupérer les détails d'un rendez-vous
function getRendezVousDetails($idRdv) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les détails du rendez-vous
    $sql = "SELECT r.*, 
                   u_agent.nom as nom_agent, u_agent.prenom as prenom_agent,
                   b.titre as titre_bien, b.adresse as adresse_bien, b.ville as ville_bien, b.code_postal as code_postal_bien
            FROM rendez_vous r
            LEFT JOIN agents a ON r.id_agent = a.id_agent
            LEFT JOIN utilisateurs u_agent ON a.id_utilisateur = u_agent.id_utilisateur
            LEFT JOIN biens_immobiliers b ON r.id_bien = b.id_bien
            WHERE r.id_rdv = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idRdv);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rdv = $result->fetch_assoc();
        
        // Définir le lieu du rendez-vous
        if ($rdv['id_bien']) {
            $rdv['lieu'] = $rdv['adresse_bien'] . ', ' . $rdv['code_postal_bien'] . ' ' . $rdv['ville_bien'];
        } else {
            $rdv['lieu'] = "Agence Omnes Immobilier, 37 quai de Grenelle, 75015 Paris";
        }
        
        $conn->close();
        return $rdv;
    } else {
        $conn->close();
        return null;
    }
}

// Fonction pour rechercher des biens par ville
function rechercherBiensParVille($ville) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour rechercher des biens par ville
    $sql = "SELECT b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image,
                   c.nom_categorie
            FROM biens_immobiliers b
            LEFT JOIN categories_bien c ON b.id_categorie = c.id_categorie
            WHERE b.ville LIKE ? AND b.disponible = 1
            ORDER BY b.date_creation DESC";
    
    $villeParam = '%' . $ville . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $villeParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $biens = [];
    while ($row = $result->fetch_assoc()) {
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($row['image'])) {
            $row['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        $biens[] = $row;
    }
    
    $conn->close();
    return $biens;
}

// Fonction pour rechercher un bien par son ID
function rechercherBienParId($idBien) {
    return getBienParId($idBien);
}

// Fonction pour rechercher un agent par son nom
function rechercherAgentParNom($nom) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour rechercher un agent par son nom
    $sql = "SELECT a.*, u.nom, u.prenom, u.email, u.telephone 
            FROM agents a 
            JOIN utilisateurs u ON a.id_utilisateur = u.id_utilisateur 
            WHERE (u.nom LIKE ? OR u.prenom LIKE ?) AND a.actif = 1
            ORDER BY u.nom, u.prenom";
    
    $nomParam = '%' . $nom . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nomParam, $nomParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $agents = [];
    while ($row = $result->fetch_assoc()) {
        // Si l'agent n'a pas de photo, utiliser une photo par défaut
        if (empty($row['photo'])) {
            $row['photo'] = 'RESSOURCES/IMAGES/agents/agent1.png';
        }
        
        $agents[] = $row;
    }
    
    $conn->close();
    return $agents;
}

// Fonction pour vérifier les identifiants de connexion
function verifierIdentifiants($email, $motDePasse) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer l'utilisateur
    $sql = "SELECT * FROM utilisateurs WHERE email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $utilisateur = $result->fetch_assoc();
        
        // Vérifier le mot de passe 
        if ($motDePasse == $utilisateur['mot_de_passe']) {
            $conn->close();
            return $utilisateur;
        }
    }
    
    $conn->close();
    return false;
}

// Fonction pour créer un nouveau compte client
function creerCompteClient($email, $motDePasse, $nom, $prenom, $telephone, $adresseLigne1, $adresseLigne2, $ville, $codePostal, $pays) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si l'email existe déjà
    $sql1 = "SELECT COUNT(*) as count FROM utilisateurs WHERE email = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("s", $email);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    if ($row1['count'] > 0) {
        $conn->close();
        return false; // Email déjà utilisé
    }
    
    // Commencer une transaction
    $conn->begin_transaction();
    
    try {
        // Insérer l'utilisateur
        $sql2 = "INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, type_utilisateur) 
                VALUES (?, ?, ?, ?, ?, 'client')";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("sssss", $email, $motDePasse, $nom, $prenom, $telephone);
        $stmt2->execute();
        
        $idUtilisateur = $stmt2->insert_id;
        
        // Insérer le client
        $sql3 = "INSERT INTO clients (id_utilisateur, adresse_ligne1, adresse_ligne2, ville, code_postal, pays) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("isssss", $idUtilisateur, $adresseLigne1, $adresseLigne2, $ville, $codePostal, $pays);
        $stmt3->execute();
        
        // Valider la transaction
        $conn->commit();
        $conn->close();
        
        return $idUtilisateur;
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollback();
        $conn->close();
        return false;
    }
}

// Fonction pour récupérer les informations d'un client
function getClientInfo($idUtilisateur) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les informations du client
    $sql = "SELECT u.*, c.* 
            FROM utilisateurs u 
            JOIN clients c ON u.id_utilisateur = c.id_utilisateur 
            WHERE u.id_utilisateur = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUtilisateur);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $client = $result->fetch_assoc();
        $conn->close();
        return $client;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour récupérer les informations de paiement d'un client
function getInfosPaiementClient($idClient) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les informations de paiement du client
    $sql = "SELECT * FROM informations_paiement WHERE id_client = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idClient);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $infoPaiement = $result->fetch_assoc();
        $conn->close();
        return $infoPaiement;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour enregistrer les informations de paiement d'un client
function enregistrerInfosPaiement($idClient, $typeCarte, $numeroCarte, $nomCarte, $dateExpiration, $codeSecurite) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si des informations de paiement existent déjà
    $sql1 = "SELECT COUNT(*) as count FROM informations_paiement WHERE id_client = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $idClient);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    if ($row1['count'] > 0) {
        // Mettre à jour les informations existantes
        $sql = "UPDATE informations_paiement 
                SET type_carte = ?, numero_carte = ?, nom_carte = ?, date_expiration = ?, code_securite = ? 
                WHERE id_client = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $typeCarte, $numeroCarte, $nomCarte, $dateExpiration, $codeSecurite, $idClient);
    } else {
        // Insérer de nouvelles informations
        $sql = "INSERT INTO informations_paiement (id_client, type_carte, numero_carte, nom_carte, date_expiration, code_securite) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $idClient, $typeCarte, $numeroCarte, $nomCarte, $dateExpiration, $codeSecurite);
    }
    
    $stmt->execute();
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour récupérer les enchères en cours
function getEncheresEnCours() {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les enchères en cours
    $sql = "SELECT e.*, b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image,
                   (SELECT MAX(montant) FROM offres_enchere WHERE id_enchere = e.id_enchere) as offre_max
            FROM encheres e
            JOIN biens_immobiliers b ON e.id_bien = b.id_bien
            WHERE e.statut = 'en_cours' AND e.date_fin > NOW()
            ORDER BY e.date_fin ASC";
    
    $result = $conn->query($sql);
    
    $encheres = [];
    while ($row = $result->fetch_assoc()) {
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($row['image'])) {
            $row['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        // Si aucune offre n'a été faite, utiliser le prix de départ
        if (empty($row['offre_max'])) {
            $row['offre_max'] = $row['prix_depart'];
        }
        
        $encheres[] = $row;
    }
    
    $conn->close();
    return $encheres;
}

// Fonction pour récupérer les détails d'une enchère
function getEnchereDetails($idEnchere) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les détails de l'enchère
    $sql = "SELECT e.*, b.*, 
                   (SELECT url_image FROM images_bien WHERE id_bien = b.id_bien ORDER BY ordre ASC LIMIT 1) as image,
                   (SELECT MAX(montant) FROM offres_enchere WHERE id_enchere = e.id_enchere) as offre_max,
                   (SELECT id_client FROM offres_enchere WHERE id_enchere = e.id_enchere ORDER BY montant DESC LIMIT 1) as id_client_max
            FROM encheres e
            JOIN biens_immobiliers b ON e.id_bien = b.id_bien
            WHERE e.id_enchere = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idEnchere);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $enchere = $result->fetch_assoc();
        
        // Si le bien n'a pas d'image, utiliser une image par défaut
        if (empty($enchere['image'])) {
            $enchere['image'] = 'RESSOURCES/IMAGES/biens/apartement_paris_1.jpg';
        }
        
        // Si aucune offre n'a été faite, utiliser le prix de départ
        if (empty($enchere['offre_max'])) {
            $enchere['offre_max'] = $enchere['prix_depart'];
            $enchere['id_client_max'] = null;
        }
        
        // Récupérer les offres pour cette enchère
        $sql2 = "SELECT o.*, u.nom, u.prenom 
                FROM offres_enchere o
                JOIN clients c ON o.id_client = c.id_client
                JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur
                WHERE o.id_enchere = ?
                ORDER BY o.montant DESC";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $idEnchere);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        $offres = [];
        while ($row = $result2->fetch_assoc()) {
            $offres[] = $row;
        }
        
        $enchere['offres'] = $offres;
        
        $conn->close();
        return $enchere;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour faire une offre sur une enchère
function faireOffre($idEnchere, $idClient, $montant) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si l'enchère est toujours en cours
    $sql1 = "SELECT * FROM encheres WHERE id_enchere = ? AND statut = 'en_cours' AND date_fin > NOW()";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $idEnchere);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    
    if ($result1->num_rows == 0) {
        $conn->close();
        return false; // L'enchère est terminée ou n'existe pas
    }
    
    $enchere = $result1->fetch_assoc();
    
    // Vérifier si le montant est supérieur au prix de départ
    if ($montant < $enchere['prix_depart']) {
        $conn->close();
        return false; // Montant inférieur au prix de départ
    }
    
    // Vérifier si le montant est supérieur à l'offre maximale actuelle
    $sql2 = "SELECT MAX(montant) as max_montant FROM offres_enchere WHERE id_enchere = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $idEnchere);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    
    if (!empty($row2['max_montant']) && $montant <= $row2['max_montant']) {
        $conn->close();
        return false; // Montant inférieur ou égal à l'offre maximale actuelle
    }
    
    // Insérer la nouvelle offre
    $sql3 = "INSERT INTO offres_enchere (id_enchere, id_client, montant) VALUES (?, ?, ?)";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("iid", $idEnchere, $idClient, $montant);
    $stmt3->execute();
    
    $success = $stmt3->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour envoyer un message
function envoyerMessage($idExpediteur, $idDestinataire, $contenu) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour insérer le message
    $sql = "INSERT INTO messages (id_expediteur, id_destinataire, contenu) VALUES (?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $idExpediteur, $idDestinataire, $contenu);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour récupérer les messages entre deux utilisateurs
function getMessages($idUtilisateur1, $idUtilisateur2) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les messages entre deux utilisateurs
    $sql = "SELECT m.*, 
                   u_exp.nom as nom_expediteur, u_exp.prenom as prenom_expediteur,
                   u_dest.nom as nom_destinataire, u_dest.prenom as prenom_destinataire
            FROM messages m
            JOIN utilisateurs u_exp ON m.id_expediteur = u_exp.id_utilisateur
            JOIN utilisateurs u_dest ON m.id_destinataire = u_dest.id_utilisateur
            WHERE (m.id_expediteur = ? AND m.id_destinataire = ?) 
               OR (m.id_expediteur = ? AND m.id_destinataire = ?)
            ORDER BY m.date_envoi ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $idUtilisateur1, $idUtilisateur2, $idUtilisateur2, $idUtilisateur1);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    // Marquer les messages comme lus
    $sql2 = "UPDATE messages SET lu = 1 
             WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("ii", $idUtilisateur2, $idUtilisateur1);
    $stmt2->execute();
    
    $conn->close();
    return $messages;
}

// Fonction pour récupérer les conversations d'un utilisateur
function getConversations($idUtilisateur) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour récupérer les conversations d'un utilisateur
    $sql = "SELECT 
                CASE 
                    WHEN m.id_expediteur = ? THEN m.id_destinataire
                    ELSE m.id_expediteur
                END as id_interlocuteur,
                CASE 
                    WHEN m.id_expediteur = ? THEN u_dest.nom
                    ELSE u_exp.nom
                END as nom_interlocuteur,
                CASE 
                    WHEN m.id_expediteur = ? THEN u_dest.prenom
                    ELSE u_exp.prenom
                END as prenom_interlocuteur,
                MAX(m.date_envoi) as derniere_date,
                COUNT(CASE WHEN m.lu = 0 AND m.id_destinataire = ? THEN 1 END) as non_lus
            FROM messages m
            JOIN utilisateurs u_exp ON m.id_expediteur = u_exp.id_utilisateur
            JOIN utilisateurs u_dest ON m.id_destinataire = u_dest.id_utilisateur
            WHERE m.id_expediteur = ? OR m.id_destinataire = ?
            GROUP BY id_interlocuteur, nom_interlocuteur, prenom_interlocuteur
            ORDER BY derniere_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $idUtilisateur, $idUtilisateur, $idUtilisateur, $idUtilisateur, $idUtilisateur, $idUtilisateur);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    $conn->close();
    return $conversations;
}

// Fonction pour ajouter un bien immobilier (administrateur)
function ajouterBienImmobilier($idCategorie, $idAgent, $titre, $description, $prix, $adresse, $ville, $codePostal, $pays, $surface, $nbPieces, $nbChambres, $etage, $balcon, $parking, $enVedette) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour insérer le bien immobilier
    $sql = "INSERT INTO biens_immobiliers (id_categorie, id_agent, titre, description, prix, adresse, ville, code_postal, pays, surface, nb_pieces, nb_chambres, etage, balcon, parking, en_vedette) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissdssssiiiiiii", $idCategorie, $idAgent, $titre, $description, $prix, $adresse, $ville, $codePostal, $pays, $surface, $nbPieces, $nbChambres, $etage, $balcon, $parking, $enVedette);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $idBien = $stmt->insert_id;
        $conn->close();
        return $idBien;
    } else {
        $conn->close();
        return false;
    }
}

// Fonction pour ajouter une image à un bien immobilier
function ajouterImageBien($idBien, $urlImage, $ordre) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour insérer l'image
    $sql = "INSERT INTO images_bien (id_bien, url_image, ordre) VALUES (?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $idBien, $urlImage, $ordre);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour supprimer un bien immobilier (administrateur)
function supprimerBienImmobilier($idBien) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour supprimer le bien immobilier
    $sql = "UPDATE biens_immobiliers SET disponible = 0 WHERE id_bien = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idBien);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour ajouter un agent immobilier (administrateur)
function ajouterAgentImmobilier($email, $motDePasse, $nom, $prenom, $telephone, $specialite, $photo, $cv) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si l'email existe déjà
    $sql1 = "SELECT COUNT(*) as count FROM utilisateurs WHERE email = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("s", $email);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    if ($row1['count'] > 0) {
        $conn->close();
        return false; // Email déjà utilisé
    }
    
    // Commencer une transaction
    $conn->begin_transaction();
    
    try {
        // Insérer l'utilisateur
        $sql2 = "INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, type_utilisateur) 
                VALUES (?, ?, ?, ?, ?, 'agent')";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("sssss", $email, $motDePasse, $nom, $prenom, $telephone);
        $stmt2->execute();
        
        $idUtilisateur = $stmt2->insert_id;
        
        // Insérer l'agent
        $sql3 = "INSERT INTO agents (id_utilisateur, specialite, photo, cv) 
                VALUES (?, ?, ?, ?)";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("isss", $idUtilisateur, $specialite, $photo, $cv);
        $stmt3->execute();
        
        $idAgent = $stmt3->insert_id;
        
        // Valider la transaction
        $conn->commit();
        $conn->close();
        
        return $idAgent;
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollback();
        $conn->close();
        return false;
    }
}

// Fonction pour supprimer un agent immobilier (administrateur)
function supprimerAgentImmobilier($idAgent) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour désactiver l'agent
    $sql = "UPDATE agents SET actif = 0 WHERE id_agent = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idAgent);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour ajouter une disponibilité à un agent (administrateur)
function ajouterDisponibiliteAgent($idAgent, $dateHeure, $duree = 60) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si la disponibilité existe déjà
    $sql1 = "SELECT COUNT(*) as count FROM disponibilites_agent WHERE id_agent = ? AND date_heure = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("is", $idAgent, $dateHeure);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    if ($row1['count'] > 0) {
        $conn->close();
        return false; // Disponibilité déjà existante
    }
    
    // Insérer la disponibilité
    $sql2 = "INSERT INTO disponibilites_agent (id_agent, date_heure, duree) VALUES (?, ?, ?)";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("isi", $idAgent, $dateHeure, $duree);
    $stmt2->execute();
    
    $success = $stmt2->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour supprimer une disponibilité d'un agent (administrateur)
function supprimerDisponibiliteAgent($idDisponibilite) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Requête SQL pour supprimer la disponibilité
    $sql = "DELETE FROM disponibilites_agent WHERE id_disponibilite = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idDisponibilite);
    $stmt->execute();
    
    $success = $stmt->affected_rows > 0;
    $conn->close();
    
    return $success;
}

// Fonction pour créer une enchère (administrateur)
function creerEnchere($idBien, $prixDepart, $dateDebut, $dateFin, $conditions) {
    // Connexion à la base de données
    $conn = connecterBDD();
    
    // Vérifier si le bien existe et est disponible
    $sql1 = "SELECT COUNT(*) as count FROM biens_immobiliers WHERE id_bien = ? AND disponible = 1";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $idBien);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    
    if ($row1['count'] == 0) {
        $conn->close();
        return false; // Bien non disponible ou inexistant
    }
    
    // Vérifier si une enchère existe déjà pour ce bien
    $sql2 = "SELECT COUNT(*) as count FROM encheres WHERE id_bien = ? AND statut IN ('en_attente', 'en_cours')";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $idBien);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    
    if ($row2['count'] > 0) {
        $conn->close();
        return false; // Une enchère existe déjà pour ce bien
    }
    
    // Insérer l'enchère
    $sql3 = "INSERT INTO encheres (id_bien, prix_depart, date_debut, date_fin, conditions, statut) 
             VALUES (?, ?, ?, ?, ?, 'en_attente')";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("idsss", $idBien, $prixDepart, $dateDebut, $dateFin, $conditions);
    $stmt3->execute();
    
    if ($stmt3->affected_rows > 0) {
        $idEnchere = $stmt3->insert_id;
        $conn->close();
        return $idEnchere;
    } else {
        $conn->close();
        return false;
    }
}
