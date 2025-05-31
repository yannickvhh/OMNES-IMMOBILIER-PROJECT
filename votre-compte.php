<?php
session_start();

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'omnes_immobilier');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connexion à la base de données
function connectDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

// Base URL pour les liens
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/\\');
$current_url = $base_url . '/auth.php';

// Traitement des actions
$action = $_GET['action'] ?? 'login';
$error = '';

// Déconnexion
if ($action === 'logout') {
    session_destroy();
    header('Location: ' . $current_url . '?action=login');
    exit;
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = connectDB();
    
    if ($action === 'register') {
        // INSCRIPTION
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $telephone = trim($_POST['telephone']);
        
        if (empty($nom) || empty($email) || empty($password)) {
            $error = 'Tous les champs obligatoires doivent être remplis';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cet email est déjà utilisé';
            } else {
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, telephone, role) VALUES (?, ?, ?, ?, 'client')");
                $stmt->execute([$nom, $email, $password, $telephone]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['role'] = 'client';
                $_SESSION['nom'] = $nom;
                header('Location: ' . $current_url . '?action=account');
                exit;
            }
        }
    } elseif ($action === 'login') {
        // CONNEXION
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        if (empty($email) || empty($password)) {
            $error = 'Email et mot de passe requis';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['mot_de_passe'] === $password) {
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nom'] = $user['nom'];
                header('Location: ' . $current_url . '?action=account');
                exit;
            } else {
                $error = 'Identifiants incorrects';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Omnes Immobilier - Authentification</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .auth-container { display: flex; min-height: 100vh; }
        .left-panel { flex: 1; background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
        .right-panel { flex: 1; padding: 40px; display: flex; align-items: center; justify-content: center; }
        .form-container { width: 100%; max-width: 400px; }
        .logo { font-size: 2rem; font-weight: bold; margin-bottom: 30px; }
        .slogan { font-size: 1.5rem; margin-bottom: 20px; }
        .description { line-height: 1.6; opacity: 0.9; }
        .form-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-bottom: 2px solid #eee; }
        .tab.active { border-bottom: 2px solid #1e3c72; font-weight: bold; color: #1e3c72; }
        h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        .btn { width: 100%; background: #1e3c72; color: white; border: none; padding: 12px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #2a5298; }
        .error { color: #e74c3c; margin-bottom: 15px; text-align: center; }
        .switch-text { text-align: center; margin-top: 15px; }
        .switch-text a { color: #1e3c72; text-decoration: none; }
        .switch-text a:hover { text-decoration: underline; }
        .account-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 600px; margin: 50px auto; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="left-panel">
            <div class="logo">Omnes Immobilier</div>
            <div class="slogan">Votre partenaire immobilier de confiance</div>
            <div class="description">
                <p>Découvrez notre sélection exclusive de biens immobiliers à travers toute la France.</p>
                <p>Créez votre compte pour :</p>
                <ul style="margin-top: 10px; padding-left: 20px;">
                    <li>Enregistrer vos recherches favorites</li>
                    <li>Recevoir des alertes personnalisées</li>
                    <li>Prendre rendez-vous avec nos agents</li>
                </ul>
            </div>
        </div>
        
        <div class="right-panel">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- ESPACE CLIENT -->
                <div class="account-box">
                    <h2>Bienvenue, <?= htmlspecialchars($_SESSION['nom']) ?> !</h2>
                    <p>Vous êtes connecté en tant que <strong><?= htmlspecialchars($_SESSION['role']) ?></strong></p>
                    <div style="margin: 30px 0;">
                        <p>Que souhaitez-vous faire aujourd'hui ?</p>
                        <ul style="margin-top: 15px; padding-left: 20px;">
                            <li>Consulter nos biens disponibles</li>
                            <li>Modifier vos préférences</li>
                            <li>Prendre rendez-vous avec un agent</li>
                        </ul>
                    </div>
                    <a href="<?= $current_url ?>?action=logout" class="btn" style="text-decoration: none; text-align: center;">Déconnexion</a>
                </div>
            <?php else: ?>
                <!-- FORMULAIRES -->
                <div class="form-container">
                    <div class="tabs">
                        <div class="tab <?= $action === 'login' ? 'active' : '' ?>" onclick="location.href='<?= $current_url ?>?action=login'">Connexion</div>
                        <div class="tab <?= $action === 'register' ? 'active' : '' ?>" onclick="location.href='<?= $current_url ?>?action=register'">Inscription</div>
                    </div>
                    
                    <div class="form-box">
                        <?php if ($error): ?>
                            <div class="error"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($action === 'register'): ?>
                            <!-- FORMULAIRE D'INSCRIPTION -->
                            <h2>Créer un compte</h2>
                            <form method="POST" action="<?= $current_url ?>?action=register">
                                <div class="form-group">
                                    <label>Nom complet*</label>
                                    <input type="text" name="nom" required>
                                </div>
                                <div class="form-group">
                                    <label>Email*</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label>Mot de passe*</label>
                                    <input type="password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label>Téléphone</label>
                                    <input type="text" name="telephone">
                                </div>
                                <button type="submit" class="btn">S'inscrire</button>
                            </form>
                            <div class="switch-text">
                                Déjà inscrit ? <a href="<?= $current_url ?>?action=login">Se connecter</a>
                            </div>
                        <?php else: ?>
                            <!-- FORMULAIRE DE CONNEXION -->
                            <h2>Connexion</h2>
                            <form method="POST" action="<?= $current_url ?>?action=login">
                                <div class="form-group">
                                    <label>Email*</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label>Mot de passe*</label>
                                    <input type="password" name="password" required>
                                </div>
                                <button type="submit" class="btn">Se connecter</button>
                            </form>
                            <div class="switch-text">
                                Pas encore de compte ? <a href="<?= $current_url ?>?action=register">Créer un compte</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>