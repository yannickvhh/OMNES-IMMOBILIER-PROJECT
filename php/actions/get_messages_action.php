<?php
session_start();
require_once '../config/db.php';

$email = $password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["email"]))) {
        $errors['email'] = "Veuillez entrer votre email.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty(trim($_POST["password"]))) {
        $errors['password'] = "Veuillez entrer votre mot de passe.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($errors)) {
        if (!$pdo) {
            $errors['database'] = "Erreur critique: La connexion à la base de données n\\\'a pas pu être établie.";
        } else {
            $sql = "SELECT id, nom, prenom, email, mot_de_passe, type_compte FROM Utilisateurs WHERE email = :email";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    if ($stmt->rowCount() == 1) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (password_verify($password, $user['mot_de_passe'])) {
                            session_regenerate_id();
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user['id'];
                            $_SESSION["user_email"] = $user['email'];
                            $_SESSION["user_nom"] = $user['nom'];
                            $_SESSION["user_prenom"] = $user['prenom'];
                            $_SESSION["user_type"] = $user['type_compte'];

                            header("location: ../../index.php"); 
                            exit();
                        } else {
                            $errors['login'] = "Email ou mot de passe incorrect.";
                        }
                    } else {
                        $errors['login'] = "Email ou mot de passe incorrect.";
                    }
                } else {
                    $errors['database'] = "Oops! Quelque chose s\\\'est mal passé lors de l\\\'exécution de la requête. Veuillez réessayer plus tard.";
                }
            } catch (PDOException $e) {
                $errors['database'] = "Erreur de base de données: " . $e->getMessage();
                error_log("PDO Login Error: " . $e->getMessage());
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_input_email'] = $email;
        header("location: ../../votre-compte.php#connexion");
        exit();
    }
}
?> 
