<?php
// Database configuration
define('DB_SERVER', 'localhost'); // or your db server
define('DB_USERNAME', 'root'); // replace with your db username
define('DB_PASSWORD', ''); // replace with your db password
define('DB_NAME', 'omnes_immobilier_db'); // replace with your database name
define('DB_CHARSET', 'utf8mb4');

// PDO Data Source Name (DSN)
$dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Turn on errors in the form of exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Make the default fetch be an associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Turn off emulation mode for prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    // Log the error to the server's error log
    error_log("ERREUR: Impossible de se connecter à la base de données PDO. " . $e->getMessage());
    // Display a generic error message to the user
    die("ERREUR: Impossible de se connecter à la base de données. Veuillez réessayer plus tard ou contacter l'administrateur.");
}

// The old mysqli connection is no longer needed if the whole site moves to PDO.
// You can comment it out or remove it once you've transitioned all relevant files.
/*
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME); // TEST LINE

// Check connection
if ($mysqli->connect_errno) {
    die("ERREUR: Impossible de se connecter à la base de données. Erreur (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
if (!$mysqli->set_charset("utf8mb4")) {
    printf("Erreur lors du chargement du jeu de caractères utf8mb4 : %s\n", $mysqli->error);
    exit();
}
*/

// Optional: You might want to set the default timezone for your application
// date_default_timezone_set('Europe/Paris');

?> 