<?php
session_start();
require_once '../config/db.php'; // Provides $pdo

$email = $password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Email
    if (empty(trim($_POST["email"]))) {
        $errors['email'] = "Veuillez entrer votre email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Validate Mot de passe
    if (empty(trim($_POST["password"]))) {
        $errors['password'] = "Veuillez entrer votre mot de passe.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($errors)) {
        if (!$pdo) {
            $errors['database'] = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
        } else {
            $sql = "SELECT id, nom, prenom, email, mot_de_passe, type_compte FROM Utilisateurs WHERE email = :email";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    if ($stmt->rowCount() == 1) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (password_verify($password, $user['mot_de_passe'])) {
                            // Password is correct, start a new session
                            session_regenerate_id(); // Good practice
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user['id'];
                            $_SESSION["user_email"] = $user['email'];
                            $_SESSION["user_nom"] = $user['nom'];
                            $_SESSION["user_prenom"] = $user['prenom'];
                            $_SESSION["user_type"] = $user['type_compte'];

                            // Redirect user based on type or to a default dashboard
                            header("location: ../../index.php"); 
                            exit();
                        } else {
                            // Password is not valid
                            $errors['login'] = "Email ou mot de passe incorrect.";
                        }
                    } else {
                        // Email doesn't exist
                        $errors['login'] = "Email ou mot de passe incorrect.";
                    }
                } else {
                    $errors['database'] = "Oops! Quelque chose s'est mal passé lors de l\'exécution de la requête. Veuillez réessayer plus tard.";
                }
            } catch (PDOException $e) {
                $errors['database'] = "Erreur de base de données: " . $e->getMessage();
                error_log("PDO Login Error: " . $e->getMessage()); // Log error
            }
        }
    }

    // If there were errors, store them in session and redirect back to login form
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_input_email'] = $email; // To repopulate email field
        header("location: ../../votre-compte.php#connexion"); // Redirect back to the login part of the page
        exit();
    }
    // No need to close $pdo explicitly here, it's generally handled when the script ends or by PHP's garbage collection.
}
?> 