-- Script SQL pour la création de la base de données Omnes Immobilier
-- Ce script crée toutes les tables nécessaires pour le fonctionnement du site

-- Suppression de la base de données si elle existe déjà
DROP DATABASE IF EXISTS omnes_immobilier;

-- Création de la base de données
CREATE DATABASE omnes_immobilier;
USE omnes_immobilier;

-- Table des utilisateurs (commune à tous les types d'utilisateurs)
CREATE TABLE utilisateurs (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    telephone VARCHAR(20),
    type_utilisateur ENUM('client', 'agent', 'admin') NOT NULL,

);

-- Table des clients (informations spécifiques aux clients)
CREATE TABLE clients (
    id_client INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    adresse_ligne1 VARCHAR(100),
    adresse_ligne2 VARCHAR(100),
    ville VARCHAR(50),
    code_postal VARCHAR(10),
    pays VARCHAR(50),
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur) ON DELETE CASCADE
);

-- Table des informations de paiement des clients
CREATE TABLE informations_paiement (
    id_paiement INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    type_carte ENUM('Visa', 'MasterCard', 'American Express', 'PayPal'),
    numero_carte VARCHAR(255),
    nom_carte VARCHAR(100),
    date_expiration VARCHAR(7),
    code_securite VARCHAR(255),
    FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE
);

-- Table des agents immobiliers
CREATE TABLE agents (
    id_agent INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    specialite VARCHAR(100),
    photo VARCHAR(255),
    video VARCHAR(255),
    cv TEXT,
    actif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur) ON DELETE CASCADE
);

-- Table des catégories de biens immobiliers
CREATE TABLE categories_bien (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(100) NOT NULL,
    description TEXT
);

-- Table des biens immobiliers
CREATE TABLE biens_immobiliers (
    id_bien INT AUTO_INCREMENT PRIMARY KEY,
    id_categorie INT NOT NULL,
    id_agent INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    prix DECIMAL(12, 2) NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
    pays VARCHAR(50) DEFAULT 'France',
    surface DECIMAL(8, 2),
    nb_pieces INT,
    nb_chambres INT,
    etage INT,
    balcon BOOLEAN DEFAULT FALSE,
    parking BOOLEAN DEFAULT FALSE,
    disponible BOOLEAN DEFAULT TRUE,
    en_vedette BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categorie) REFERENCES categories_bien(id_categorie),
    FOREIGN KEY (id_agent) REFERENCES agents(id_agent)
);

-- Table des enchères pour les biens en vente par enchère
CREATE TABLE encheres (
    id_enchere INT AUTO_INCREMENT PRIMARY KEY,
    id_bien INT NOT NULL,
    prix_depart DECIMAL(12, 2) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    conditions TEXT,
    statut ENUM('en_attente', 'en_cours', 'terminee', 'annulee') DEFAULT 'en_attente',
    FOREIGN KEY (id_bien) REFERENCES biens_immobiliers(id_bien) ON DELETE CASCADE
);

-- Table des offres d'enchères
CREATE TABLE offres_enchere (
    id_offre INT AUTO_INCREMENT PRIMARY KEY,
    id_enchere INT NOT NULL,
    id_client INT NOT NULL,
    montant DECIMAL(12, 2) NOT NULL,
    date_offre DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_enchere) REFERENCES encheres(id_enchere) ON DELETE CASCADE,
    FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE
);

-- Table des images des biens immobiliers
CREATE TABLE images_bien (
    id_image INT AUTO_INCREMENT PRIMARY KEY,
    id_bien INT NOT NULL,
    url_image VARCHAR(255) NOT NULL,
    ordre INT DEFAULT 0,
    FOREIGN KEY (id_bien) REFERENCES biens_immobiliers(id_bien) ON DELETE CASCADE
);

-- Table des disponibilités des agents
CREATE TABLE disponibilites_agent (
    id_disponibilite INT AUTO_INCREMENT PRIMARY KEY,
    id_agent INT NOT NULL,
    date_heure DATETIME NOT NULL,
    duree INT DEFAULT 60, -- Durée en minutes
    FOREIGN KEY (id_agent) REFERENCES agents(id_agent) ON DELETE CASCADE,
    UNIQUE (id_agent, date_heure)
);

-- Table des rendez-vous
CREATE TABLE rendez_vous (
    id_rdv INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    id_agent INT NOT NULL,
    id_bien INT,
    date_heure DATETIME NOT NULL,
    motif TEXT,
    statut ENUM('confirme', 'annule', 'termine') DEFAULT 'confirme',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur),
    FOREIGN KEY (id_agent) REFERENCES agents(id_agent),
    FOREIGN KEY (id_bien) REFERENCES biens_immobiliers(id_bien) ON DELETE SET NULL
);

-- Table des messages entre clients et agents
CREATE TABLE messages (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    id_expediteur INT NOT NULL,
    id_destinataire INT NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_expediteur) REFERENCES utilisateurs(id_utilisateur),
    FOREIGN KEY (id_destinataire) REFERENCES utilisateurs(id_utilisateur)
);

-- Table des événements hebdomadaires
CREATE TABLE evenements (
    id_evenement INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    lieu VARCHAR(255),
    image VARCHAR(255)
);

-- Insertion des données de base

-- Insertion des catégories de biens
INSERT INTO categories_bien (nom_categorie, description) VALUES
('Immobilier résidentiel', 'Toute propriété utilisée à des fins résidentielles. Les exemples incluent les maisons unifamiliales, les condos, les coopératives, les duplex, les maisons en rangée et les résidences multifamiliales.'),
('Immobilier commercial', 'Toute propriété utilisée exclusivement à des fins commerciales, comme les complexes d''appartements, les stations-service, les épiceries, les hôpitaux, les hôtels, les bureaux, les parkings, les restaurants, les centres commerciaux, les magasins et les théâtres.'),
('Terrain', 'Comprend les propriétés non développées, les terrains vacants et les terres agricoles telles que les fermes, les vergers, les ranchs et les terres à bois.'),
('Appartement à louer', 'C''est prendre une propriété en location pour une durée limitée.'),
('Immobilier en vente par enchère', 'C''est une vente publique où le bien immobilier est proposé à un prix de départ et les acheteurs potentiels font des offres successives. Le bien est attribué au plus offrant.');

-- Insertion d'un administrateur par défaut
INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, type_utilisateur) VALUES
('admin@omnesimmobilier.fr', 'admin123', 'Admin', 'Omnes', '01 23 45 67 89', 'admin');

-- Insertion d'un agent immobilier par défaut
INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, type_utilisateur) VALUES
('jean-pierre.segado@omnesimmobilier.fr', 'agent123', 'Segado', 'Jean-Pierre', '01 23 45 67 90', 'agent');

INSERT INTO agents (id_utilisateur, specialite, photo, cv) VALUES
(2, 'Immobilier résidentiel', 'RESSOURCES/IMAGES/agents/agent1.png', 'Jean-Pierre SEGADO est un agent immobilier expérimenté spécialisé dans l''immobilier résidentiel. Avec plus de 10 ans d''expérience dans le secteur, il a aidé de nombreuses familles à trouver leur maison idéale.');

-- Insertion d'un client par défaut
INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, type_utilisateur) VALUES
('client@exemple.fr', 'client123', 'Dupont', 'Marie', '06 12 34 56 78', 'client');

INSERT INTO clients (id_utilisateur, adresse_ligne1, ville, code_postal, pays) VALUES
(3, '123 Rue de Paris', 'Paris', '75015', 'France');

-- Insertion de quelques biens immobiliers
INSERT INTO biens_immobiliers (id_categorie, id_agent, titre, description, prix, adresse, ville, code_postal, surface, nb_pieces, nb_chambres, etage, balcon, parking, en_vedette) VALUES
(1, 1, 'Appartement lumineux à Paris', 'Bel appartement de 3 pièces avec vue dégagée, proche des commerces et transports.', 450000.00, '15 Rue de la Paix', 'Paris', '75002', 75.50, 3, 2, 3, TRUE, FALSE, TRUE),
(1, 1, 'Maison familiale à Bordeaux', 'Grande maison de 5 chambres avec jardin et piscine, idéale pour une famille.', 850000.00, '25 Avenue des Pins', 'Bordeaux', '33000', 180.00, 7, 5, 0, FALSE, TRUE, TRUE),
(2, 1, 'Local commercial en centre-ville', 'Local commercial de 120m² en plein centre-ville, forte affluence.', 350000.00, '8 Place du Marché', 'Lyon', '69002', 120.00, 2, 0, 0, FALSE, FALSE, FALSE),
(3, 1, 'Terrain constructible de 1000m²', 'Beau terrain plat et viabilisé, idéal pour construction de maison individuelle.', 180000.00, 'Route des Collines', 'Aix-en-Provence', '13100', 1000.00, 0, 0, 0, FALSE, FALSE, TRUE),
(4, 1, 'Studio meublé pour étudiant', 'Studio entièrement meublé et équipé, proche des universités.', 550.00, '3 Rue des Étudiants', 'Toulouse', '31000', 25.00, 1, 0, 2, TRUE, FALSE, FALSE),
(5, 1, 'Villa de luxe - Vente aux enchères', 'Magnifique villa avec vue mer, piscine à débordement et jardin tropical.', 1200000.00, '18 Chemin des Criques', 'Nice', '06000', 250.00, 8, 5, 0, TRUE, TRUE, TRUE);

-- Insertion des images pour les biens
INSERT INTO images_bien (id_bien, url_image, ordre) VALUES
(1, 'RESSOURCES/IMAGES/biens/appartement_paris_1.jpg', 1),
(1, 'RESSOURCES/IMAGES/biens/appartement_paris_2.jpg', 2),
(2, 'RESSOURCES/IMAGES/biens/maison_bordeaux_1.jpg', 1),
(2, 'RESSOURCES/IMAGES/biens/maison_bordeaux_2.jpg', 2),
(3, 'RESSOURCES/IMAGES/biens/local_lyon_1.jpg', 1),
(4, 'RESSOURCES/IMAGES/biens/terrain_aix_1.jpg', 1),
(5, 'RESSOURCES/IMAGES/biens/studio_toulouse_1.jpg', 1),
(6, 'RESSOURCES/IMAGES/biens/villa_nice_1.jpg', 1),
(6, 'RESSOURCES/IMAGES/biens/villa_nice_2.jpg', 2);

-- Insertion d'une enchère
INSERT INTO encheres (id_bien, prix_depart, date_debut, date_fin, conditions, statut) VALUES
(6, 1200000.00, '2025-06-01 10:00:00', '2025-06-15 18:00:00', 'La vente est ferme et définitive : aucun droit de rétractation. Le bien est vendu en l''état, sans garantie de vice caché.', 'en_cours');

-- Insertion d'un événement hebdomadaire
INSERT INTO evenements (titre, description, date_debut, date_fin, lieu, image) VALUES
('Journée portes ouvertes', 'Ce samedi : journée portes ouvertes à notre agence avec visite guidée de 5 propriétés phares, et un séminaire sur l''investissement locatif à 15h.', '2025-06-05 10:00:00', '2025-06-05 18:00:00', 'Agence Omnes Immobilier, 37 quai de Grenelle, 75015 Paris', 'RESSOURCES/IMAGES/evenements/portes_ouvertes.jpg');

-- Insertion des disponibilités pour l'agent Jean-Pierre
-- Lundi
INSERT INTO disponibilites_agent (id_agent, date_heure, duree) VALUES
(1, '2025-06-02 14:00:00', 60),
(1, '2025-06-02 15:00:00', 60),
(1, '2025-06-02 16:00:00', 60),
(1, '2025-06-02 17:00:00', 60);

-- Mercredi
INSERT INTO disponibilites_agent (id_agent, date_heure, duree) VALUES
(1, '2025-06-04 09:00:00', 60),
(1, '2025-06-04 10:00:00', 60),
(1, '2025-06-04 11:00:00', 60),
(1, '2025-06-04 14:00:00', 60),
(1, '2025-06-04 15:00:00', 60),
(1, '2025-06-04 16:00:00', 60),
(1, '2025-06-04 17:00:00', 60);

-- Vendredi
INSERT INTO disponibilites_agent (id_agent, date_heure, duree) VALUES
(1, '2025-06-06 09:00:00', 60),
(1, '2025-06-06 10:00:00', 60),
(1, '2025-06-06 11:00:00', 60),
(1, '2025-06-06 14:00:00', 60),
(1, '2025-06-06 15:00:00', 60),
(1, '2025-06-06 16:00:00', 60),
(1, '2025-06-06 17:00:00', 60);

-- Insertion d'un rendez-vous déjà pris
INSERT INTO rendez_vous (id_utilisateur, id_agent, id_bien, date_heure, motif) VALUES
(3, 1, 1, '2025-06-04 10:00:00', 'Visite de l''appartement à Paris');
