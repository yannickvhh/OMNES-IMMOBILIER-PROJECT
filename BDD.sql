-- Script SQL pour la création de la base de données Omnes Immobilier
-- Ce script crée toutes les tables nécessaires avec leurs relations

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS omnes_immobilier;
USE omnes_immobilier;

-- Suppression des tables si elles existent déjà (pour éviter les erreurs)
DROP TABLE IF EXISTS Messages;
DROP TABLE IF EXISTS Cartes_Paiement;
DROP TABLE IF EXISTS Paiements;
DROP TABLE IF EXISTS Rendez_Vous;
DROP TABLE IF EXISTS Images_Bien;
DROP TABLE IF EXISTS Biens_Immobiliers;
DROP TABLE IF EXISTS Categories_Bien;
DROP TABLE IF EXISTS Disponibilites_Agent;
DROP TABLE IF EXISTS Agents;
DROP TABLE IF EXISTS Clients;
DROP TABLE IF EXISTS Utilisateurs;

-- Table Utilisateurs (table de base pour tous les types d'utilisateurs)
CREATE TABLE Utilisateurs (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    telephone VARCHAR(20),
    role ENUM('admin', 'agent', 'client') NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table Clients (extension de Utilisateurs)
CREATE TABLE Clients (
    id_client INT PRIMARY KEY,
    adresse_ligne1 VARCHAR(100),
    adresse_ligne2 VARCHAR(100),
    ville VARCHAR(50),
    code_postal VARCHAR(10),
    pays VARCHAR(50),
    FOREIGN KEY (id_client) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE
);

-- Table Agents (extension de Utilisateurs)
CREATE TABLE Agents (
    id_agent INT PRIMARY KEY,
    specialite VARCHAR(100),
    cv VARCHAR(255),
    photo VARCHAR(255),
    video VARCHAR(255),
    FOREIGN KEY (id_agent) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE
);

-- Table Disponibilites_Agent
CREATE TABLE Disponibilites_Agent (
    id_disponibilite INT AUTO_INCREMENT PRIMARY KEY,
    id_agent INT,
    jour DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    statut ENUM('disponible', 'occupe', 'conge') DEFAULT 'disponible',
    FOREIGN KEY (id_agent) REFERENCES Agents(id_agent) ON DELETE CASCADE
);

-- Table Categories_Bien
CREATE TABLE Categories_Bien (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(50) NOT NULL,
    description TEXT
);

-- Table Biens_Immobiliers
CREATE TABLE Biens_Immobiliers (
    id_bien INT AUTO_INCREMENT PRIMARY KEY,
    id_categorie INT,
    titre VARCHAR(100) NOT NULL,
    description TEXT,
    prix DECIMAL(10, 2) NOT NULL,
    surface DECIMAL(8, 2) NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(50) NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
    nombre_chambres INT,
    nombre_salles_bain INT,
    etage INT,
    balcon BOOLEAN DEFAULT FALSE,
    parking BOOLEAN DEFAULT FALSE,
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('disponible', 'vendu', 'loue') DEFAULT 'disponible',
    type_vente ENUM('vente', 'location', 'enchere') DEFAULT 'vente',
    date_fin_enchere DATETIME,
    prix_depart_enchere DECIMAL(10, 2),
    FOREIGN KEY (id_categorie) REFERENCES Categories_Bien(id_categorie)
);

-- Table Images_Bien
CREATE TABLE Images_Bien (
    id_image INT AUTO_INCREMENT PRIMARY KEY,
    id_bien INT,
    chemin_image VARCHAR(255) NOT NULL,
    est_principale BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_bien) REFERENCES Biens_Immobiliers(id_bien) ON DELETE CASCADE
);

-- Table Rendez_Vous
CREATE TABLE Rendez_Vous (
    id_rdv INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT,
    id_agent INT,
    id_bien INT,
    date_heure DATETIME NOT NULL,
    duree INT NOT NULL, -- en minutes
    statut ENUM('confirme', 'annule', 'termine') DEFAULT 'confirme',
    type_rdv ENUM('en_personne', 'videoconference', 'telephone') DEFAULT 'en_personne',
    notes TEXT,
    FOREIGN KEY (id_client) REFERENCES Clients(id_client) ON DELETE CASCADE,
    FOREIGN KEY (id_agent) REFERENCES Agents(id_agent) ON DELETE CASCADE,
    FOREIGN KEY (id_bien) REFERENCES Biens_Immobiliers(id_bien) ON DELETE SET NULL
);

-- Table Paiements
CREATE TABLE Paiements (
    id_paiement INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT,
    id_bien INT,
    id_rdv INT,
    montant DECIMAL(10, 2) NOT NULL,
    date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
    type_service VARCHAR(50) NOT NULL,
    statut ENUM('approuve', 'refuse', 'en_attente') DEFAULT 'en_attente',
    FOREIGN KEY (id_client) REFERENCES Clients(id_client) ON DELETE CASCADE,
    FOREIGN KEY (id_bien) REFERENCES Biens_Immobiliers(id_bien) ON DELETE SET NULL,
    FOREIGN KEY (id_rdv) REFERENCES Rendez_Vous(id_rdv) ON DELETE SET NULL
);

-- Table Cartes_Paiement
CREATE TABLE Cartes_Paiement (
    id_carte INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT,
    type_carte ENUM('visa', 'mastercard', 'amex', 'paypal') NOT NULL,
    nom_carte VARCHAR(100) NOT NULL,
    numero_carte VARCHAR(255) NOT NULL, -- Crypté
    date_expiration VARCHAR(7) NOT NULL, -- Format MM/YYYY
    code_securite VARCHAR(255) NOT NULL, -- Crypté
    FOREIGN KEY (id_client) REFERENCES Clients(id_client) ON DELETE CASCADE
);

-- Table Messages
CREATE TABLE Messages (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    id_expediteur INT,
    id_destinataire INT,
    contenu TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    type_message ENUM('texto', 'audio', 'video') DEFAULT 'texto',
    FOREIGN KEY (id_expediteur) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_destinataire) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE
);

-- Insertion de données de test pour les catégories
INSERT INTO Categories_Bien (nom_categorie, description) VALUES
('Immobilier résidentiel', 'Toute propriété utilisée à des fins résidentielles. Les exemples incluent les maisons unifamiliales, les condos, les coopératives, les duplex, les maisons en rangée et les résidences multifamiliales.'),
('Immobilier commercial', 'Toute propriété utilisée exclusivement à des fins commerciales, comme les complexes d''appartements, les stations-service, les épiceries, les hôpitaux, les hôtels, les bureaux, les parkings, les restaurants, les centres commerciaux, les magasins et les théâtres.'),
('Terrain', 'Comprend les propriétés non développées, les terrains vacants et les terres agricoles telles que les fermes, les vergers, les ranchs et les terres à bois.'),
('Appartement à louer', 'Propriétés disponibles à la location pour une durée limitée.'),
('Immobiliers en vente par enchère', 'Propriétés vendues aux enchères avec un prix de départ et une date de fin d''enchère.');

-- Insertion d'un utilisateur administrateur de test
INSERT INTO Utilisateurs (email, mot_de_passe, nom, prenom, telephone, role) VALUES
('admin@omnesimmobilier.fr', '$2y$10$8MuRXOo0bIs4J.mQzZnIXe6fS5hEJLVvE/JbVH.qpHmxVUBCiIJHa', 'Admin', 'Omnes', '0123456789', 'admin');
-- Note: Le mot de passe hashé correspond à "admin123"

-- Insertion d'un agent immobilier de test
INSERT INTO Utilisateurs (email, mot_de_passe, nom, prenom, telephone, role) VALUES
('agent@omnesimmobilier.fr', '$2y$10$vKu8.aPfKBCVxLm/WmKkLO8eVm5UBJUmSSYDoYkIMN1oG9.4gfOSS', 'Dupont', 'Jean', '0123456788', 'agent');
-- Note: Le mot de passe hashé correspond à "agent123"

INSERT INTO Agents (id_agent, specialite, cv, photo, video) VALUES
(LAST_INSERT_ID(), 'Immobilier résidentiel', 'cv_jean_dupont.pdf', 'photos/jean_dupont.jpg', NULL);

-- Insertion d'un client de test
INSERT INTO Utilisateurs (email, mot_de_passe, nom, prenom, telephone, role) VALUES
('client@example.com', '$2y$10$QwU0frPRj.q7LzQO1AHNYOefrq9e1lFZVxMTF7I.cdOzKg36Uipba', 'Martin', 'Sophie', '0612345678', 'client');
-- Note: Le mot de passe hashé correspond à "client123"

INSERT INTO Clients (id_client, adresse_ligne1, adresse_ligne2, ville, code_postal, pays) VALUES
(LAST_INSERT_ID(), '15 rue des Fleurs', 'Apt 3', 'Paris', '75015', 'France');

-- Insertion de disponibilités pour l'agent
INSERT INTO Disponibilites_Agent (id_agent, jour, heure_debut, heure_fin, statut) VALUES
((SELECT id_agent FROM Agents LIMIT 1), CURDATE(), '09:00:00', '12:00:00', 'disponible'),
((SELECT id_agent FROM Agents LIMIT 1), CURDATE(), '14:00:00', '18:00:00', 'disponible'),
((SELECT id_agent FROM Agents LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '12:00:00', 'disponible'),
((SELECT id_agent FROM Agents LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '18:00:00', 'disponible');

-- Insertion de quelques biens immobiliers de test
INSERT INTO Biens_Immobiliers (id_categorie, titre, description, prix, surface, adresse, ville, code_postal, nombre_chambres, nombre_salles_bain, etage, balcon, parking, statut, type_vente) VALUES
(1, 'Appartement moderne', 'Magnifique appartement lumineux avec vue dégagée', 450000.00, 85.00, '123 Avenue Victor Hugo', 'Paris', '75016', 3, 2, 4, TRUE, TRUE, 'disponible', 'vente'),
(1, 'Maison familiale', 'Grande maison avec jardin dans quartier calme', 580000.00, 120.00, '45 Rue des Cerisiers', 'Lyon', '69003', 4, 2, 0, FALSE, TRUE, 'disponible', 'vente'),
(2, 'Local commercial', 'Local commercial en centre-ville', 320000.00, 95.00, '78 Rue de la République', 'Marseille', '13001', NULL, 1, 0, FALSE, FALSE, 'disponible', 'vente'),
(3, 'Terrain constructible', 'Beau terrain plat avec vue dégagée', 180000.00, 800.00, 'Route des Vignes', 'Bordeaux', '33000', NULL, NULL, NULL, FALSE, FALSE, 'disponible', 'vente'),
(4, 'Studio meublé', 'Studio entièrement rénové et meublé', 850.00, 28.00, '12 Rue Saint-Michel', 'Paris', '75005', 1, 1, 3, FALSE, FALSE, 'disponible', 'location');

-- Insertion d'images pour les biens
INSERT INTO Images_Bien (id_bien, chemin_image, est_principale) VALUES
(1, 'images/biens/appartement1_main.jpg', TRUE),
(1, 'images/biens/appartement1_salon.jpg', FALSE),
(1, 'images/biens/appartement1_cuisine.jpg', FALSE),
(2, 'images/biens/maison1_main.jpg', TRUE),
(2, 'images/biens/maison1_jardin.jpg', FALSE),
(3, 'images/biens/local1_main.jpg', TRUE),
(4, 'images/biens/terrain1_main.jpg', TRUE),
(5, 'images/biens/studio1_main.jpg', TRUE);

-- Insertion d'un rendez-vous de test
INSERT INTO Rendez_Vous (id_client, id_agent, id_bien, date_heure, duree, statut, type_rdv, notes) VALUES
((SELECT id_client FROM Clients LIMIT 1), 
 (SELECT id_agent FROM Agents LIMIT 1), 
 1, 
 DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 DAY), 
 60, 
 'confirme', 
 'en_personne', 
 'Première visite pour découvrir le bien');

-- Insertion d'un paiement de test
INSERT INTO Paiements (id_client, id_bien, id_rdv, montant, type_service, statut) VALUES
((SELECT id_client FROM Clients LIMIT 1), 
 1, 
 (SELECT id_rdv FROM Rendez_Vous LIMIT 1), 
 50.00, 
 'Frais de dossier', 
 'approuve');

-- Insertion d'une carte de paiement de test (avec des données fictives)
INSERT INTO Cartes_Paiement (id_client, type_carte, nom_carte, numero_carte, date_expiration, code_securite) VALUES
((SELECT id_client FROM Clients LIMIT 1), 
 'visa', 
 'Sophie Martin', 
 'ENCRYPTED_4532XXXXXXXX1234', -- Ceci représente un numéro crypté
 '12/2025', 
 'ENCRYPTED_123'); -- Ceci représente un code crypté

-- Insertion d'un message de test
INSERT INTO Messages (id_expediteur, id_destinataire, contenu, lu, type_message) VALUES
((SELECT id_agent FROM Agents LIMIT 1), 
 (SELECT id_client FROM Clients LIMIT 1), 
 'Bonjour, je confirme notre rendez-vous pour la visite de l''appartement.', 
 FALSE, 
 'texto');
