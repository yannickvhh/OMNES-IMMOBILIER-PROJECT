# OMNES IMMOBILIER PROJECT

## Description du projet
Omnes Immobilier est une plateforme web dynamique dédiée à la gestion immobilière, permettant aux utilisateurs de consulter, rechercher et prendre rendez-vous pour visiter des biens immobiliers. Le système offre également des fonctionnalités avancées pour les agents immobiliers et les administrateurs.

## Fonctionnalités principales
- Consultation et recherche de biens immobiliers
- Prise de rendez-vous avec des agents immobiliers
- Gestion de profil utilisateur (client, agent, administrateur)
- Messagerie interne entre clients et agents
- Gestion des paiements et des transactions

## Structure du projet

### Pages HTML
- `homepage.html` : Page d'accueil du site
- `tout_parcourir.html` : Liste complète des biens immobiliers
- `recherche.html` : Formulaire de recherche avancée
- `rendez_vous.html` : Système de prise de rendez-vous
- `votre_compte.html` : Gestion du compte utilisateur

### Fichiers CSS
- `style_commun.css` : Styles communs à toutes les pages
- `style_accueil.css` : Styles spécifiques à la page d'accueil
- `style_parcourir.css` : Styles pour la page de parcours des biens
- `style_recherche.css` : Styles pour la page de recherche
- `style_rendez_vous.css` : Styles pour la page de rendez-vous
- `style_votre_compte.css` : Styles pour la page de gestion de compte

### Base de données
- `BDD.sql` : Script SQL pour la création et l'initialisation de la base de données

### Ressources
- `logo.png` : Logo d'Omnes Immobilier
- `propriete1.png`, `propriete2.png`, etc. : Images des propriétés immobilières

## Technologies utilisées
- **Front-end** : HTML5, CSS3, JavaScript, Bootstrap
- **Back-end** : PHP (non inclus dans ce dépôt)
- **Base de données** : MySQL/MariaDB
- **Services externes** : Google Maps (intégration de carte)

## Installation et déploiement

### Prérequis
- Serveur web (Apache, Nginx)
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur

### Étapes d'installation
1. Cloner ce dépôt sur votre serveur web
   ```
   git clone https://github.com/yannickvhh/OMNES-IMMOBILIER-PROJECT.git
   ```

2. Importer le fichier `BDD.sql` dans votre base de données MySQL
   ```
   mysql -u [username] -p [database_name] < BDD.sql
   ```

3. Configurer les paramètres de connexion à la base de données (à implémenter)

4. Accéder au site via votre navigateur
   ```
   http://localhost/OMNES-IMMOBILIER-PROJECT/homepage.html
   ```

## Structure de la base de données
La base de données comprend plusieurs tables principales :
- `Utilisateurs` : Informations sur tous les utilisateurs du système
- `Clients` : Extension de la table Utilisateurs pour les clients
- `Agents` : Extension de la table Utilisateurs pour les agents immobiliers
- `Biens_Immobiliers` : Détails sur les propriétés disponibles
- `Categories_Bien` : Catégories de biens immobiliers
- `Images_Bien` : Liens vers les images des propriétés
- `Rendez_Vous` : Gestion des rendez-vous entre clients et agents
- `Disponibilites_Agent` : Créneaux disponibles des agents
- `Paiements` : Transactions financières
- `Messages` : Système de messagerie interne

## Fonctionnalités à implémenter
- Authentification et gestion des sessions
- Système de recherche avancée
- Intégration complète de la base de données avec le front-end
- Système de messagerie en temps réel
- Passerelle de paiement sécurisée

## Captures d'écran
Les captures d'écran du site sont disponibles dans les fichiers propriete1.png, propriete2.png, etc.

## Contributeurs
- [Yannick VHH](https://github.com/yannickvhh)
- [Silouane Chouteau](https://github.com/SilouaneChouteau)

## Licence
Ce projet est développé dans le cadre d'un projet académique pour l'ECE Paris.

## Contact
Pour toute question ou suggestion concernant ce projet, veuillez contacter :
- Email : [leflutistedu78.lckl@gmail.com]


