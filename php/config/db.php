<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'omnes_immobilier_db');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    error_log("ERREUR: Impossible de se connecter à la base de données PDO. " . $e->getMessage());
    die("ERREUR: Impossible de se connecter à la base de données. Veuillez réessayer plus tard ou contacter l'administrateur.");
}

?>
