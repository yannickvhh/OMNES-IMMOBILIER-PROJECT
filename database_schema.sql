CREATE TABLE Utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    type_compte ENUM('client', 'agent', 'admin') NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Clients (
    id_utilisateur INT PRIMARY KEY,
    adresse_ligne1 VARCHAR(255),
    adresse_ligne2 VARCHAR(255),
    ville VARCHAR(100),
    code_postal VARCHAR(20),
    pays VARCHAR(100),
    telephone VARCHAR(20),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE AgentsImmobiliers (
    id_utilisateur INT PRIMARY KEY,
    specialite VARCHAR(255),
    bureau VARCHAR(255),
    telephone_pro VARCHAR(20),
    cv_filename VARCHAR(255),
    photo_filename VARCHAR(255),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE Proprietes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type_propriete ENUM('residentiel', 'commercial', 'terrain', 'location', 'enchere') NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(100),
    code_postal VARCHAR(20),
    prix DECIMAL(12, 2),
    nombre_pieces INT,
    nombre_chambres INT,
    surface DECIMAL(10, 2),
    etage INT,
    balcon BOOLEAN DEFAULT FALSE,
    parking BOOLEAN DEFAULT FALSE,
    photo_principale_filename VARCHAR(255),
    video_url VARCHAR(255),
    statut ENUM('disponible', 'vendu', 'enchere_active', 'enchere_terminee', 'retire') DEFAULT 'disponible',
    id_agent_responsable INT,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agent_responsable) REFERENCES AgentsImmobiliers(id_utilisateur) ON DELETE SET NULL
);

CREATE TABLE DisponibilitesAgents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_agent INT NOT NULL,
    jour_semaine ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    est_reserve BOOLEAN DEFAULT FALSE,
    UNIQUE KEY (id_agent, jour_semaine, heure_debut, heure_fin),
    FOREIGN KEY (id_agent) REFERENCES AgentsImmobiliers(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE RendezVous (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_agent INT NOT NULL,
    id_propriete INT,
    id_disponibilite INT,
    date_heure_rdv DATETIME NOT NULL,
    statut ENUM('planifie', 'confirme', 'annule_client', 'annule_agent', 'termine') DEFAULT 'planifie',
    notes_client TEXT,
    lieu_rdv VARCHAR(255),
    informations_supplementaires TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES Clients(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_agent) REFERENCES AgentsImmobiliers(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_propriete) REFERENCES Proprietes(id) ON DELETE SET NULL,
    FOREIGN KEY (id_disponibilite) REFERENCES DisponibilitesAgents(id) ON DELETE SET NULL
);

CREATE TABLE Encheres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_propriete INT NOT NULL UNIQUE,
    prix_depart DECIMAL(12, 2) NOT NULL,
    prix_actuel DECIMAL(12, 2),
    date_heure_debut DATETIME NOT NULL,
    date_heure_fin DATETIME NOT NULL,
    id_dernier_encherisseur INT,
    statut_enchere ENUM('active', 'terminee_vendue', 'terminee_non_vendue', 'annulee') DEFAULT 'active',
    FOREIGN KEY (id_propriete) REFERENCES Proprietes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_dernier_encherisseur) REFERENCES Clients(id_utilisateur) ON DELETE SET NULL
);

CREATE TABLE OffresEncheres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_enchere INT NOT NULL,
    id_client INT NOT NULL,
    montant_offre DECIMAL(12, 2) NOT NULL,
    date_heure_offre TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_enchere) REFERENCES Encheres(id) ON DELETE CASCADE,
    FOREIGN KEY (id_client) REFERENCES Clients(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE Messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_expediteur INT NOT NULL,
    id_destinataire INT NOT NULL,
    contenu_message TEXT NOT NULL,
    date_heure_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type_communication ENUM('texte', 'email', 'system') DEFAULT 'texte',
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_expediteur) REFERENCES Utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (id_destinataire) REFERENCES Utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE Paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_service_concerne INT,
    type_service VARCHAR(100),
    montant DECIMAL(10, 2) NOT NULL,
    methode_paiement VARCHAR(50),
    reference_paiement VARCHAR(255),
    statut_paiement ENUM('reussi', 'echoue', 'en_attente') DEFAULT 'en_attente',
    date_paiement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES Clients(id_utilisateur) ON DELETE CASCADE
);

INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte)
VALUES ('Dupont', 'Admin', 'admin.dupont@omnesimmobilier.fr', 'Adm!nPass123', 'admin');

INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte)
VALUES ('Martin', 'Sophie', 'sophie.martin@email.com', 'Cl!entPass456', 'client');
SET @last_client_id = LAST_INSERT_ID();
INSERT INTO Clients (id_utilisateur, adresse_ligne1, ville, code_postal, pays, telephone)
VALUES (@last_client_id, '123 Rue de la Paix', 'Paris', '75001', 'France', '0123456789');

INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte)
VALUES ('Bernard', 'Lucas', 'lucas.bernard@omnesimmobilier.fr', 'Ag#ntPass789', 'agent');
SET @last_agent_id = LAST_INSERT_ID();
INSERT INTO AgentsImmobiliers (id_utilisateur, specialite, bureau, telephone_pro, cv_filename, photo_filename)
VALUES (@last_agent_id, 'Appartements Parisiens', 'Bureau A-101', '0612345678', 'cv_lucas_bernard.pdf', 'photo_lucas_bernard.jpg');

INSERT INTO Proprietes (titre, description, type_propriete, adresse, ville, code_postal, prix, nombre_pieces, nombre_chambres, surface, photo_principale_filename, statut, id_agent_responsable)
VALUES 
('Bel Appartement Haussmannien', 'Superbe appartement de 4 pièces dans un immeuble haussmannien classique. Parquet, moulures, cheminées. Proche des commerces et transports.', 'residentiel', '75 Boulevard Haussmann', 'Paris', '75008', 950000.00, 4, 2, 120.50, 'appartement_haussmannien_paris.jpg', 'disponible', @last_agent_id),
('Studio Moderne - Quartier Calme', 'Studio refait à neuf, lumineux et fonctionnel. Idéal pour étudiant ou jeune actif. Petite cuisine équipée, salle d\'eau moderne.', 'location', '12 Rue des Lilas', 'Lyon', '69003', 650.00, 1, 1, 30.00, 'studio_moderne_lyon.jpg', 'disponible', @last_agent_id),
('Grande Villa avec Jardin et Piscine', 'Magnifique villa d\'architecte offrant de beaux volumes, un grand jardin paysager sans vis-à-vis et une piscine chauffée. 5 chambres, 3 salles de bain.', 'residentiel', 'Chemin des Lavandes', 'Aix-en-Provence', '13100', 1200000.00, 7, 5, 250.75, 'villa_aix_piscine.jpg', 'disponible', @last_agent_id),
('Local Commercial - Emplacement N°1', 'Excellent local commercial avec grande vitrine sur axe passant. Idéal pour boutique ou services. Surface modulable.', 'commercial', '1 Place du Commerce', 'Bordeaux', '33000', 2500.00, 0, 0, 85.00, 'local_commercial_bordeaux.jpg', 'disponible', @last_agent_id);

INSERT INTO Proprietes (titre, description, type_propriete, adresse, ville, code_postal, prix, nombre_pieces, nombre_chambres, surface, photo_principale_filename, statut, id_agent_responsable)
VALUES ('Maison Vendue Centre Historique', 'Cette maison a été vendue.', 'residentiel', '5 Rue Vieille', 'Rennes', '35000', 300000.00, NULL, NULL, NULL, NULL, 'vendu', @last_agent_id);

