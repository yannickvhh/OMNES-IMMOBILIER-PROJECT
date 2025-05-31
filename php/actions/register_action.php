<?php
session_start();
require_once '../config/db.php'; // Provides $pdo

// Initialize variables to store form data and error messages
$nom = $prenom = $email = $telephone = $password = $confirm_password = "";
$type_compte = "client"; // Default to client registration
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Nom
    if (empty(trim($_POST["nom"]))) {
        $errors['nom'] = "Veuillez entrer votre nom.";
    } else {
        $nom = trim($_POST["nom"]);
    }

    // Validate Prénom
    if (empty(trim($_POST["prenom"]))) {
        $errors['prenom'] = "Veuillez entrer votre prénom.";
    } else {
        $prenom = trim($_POST["prenom"]);
    }

    // Validate Email
    if (empty(trim($_POST["email"]))) {
        $errors['email'] = "Veuillez entrer votre email.";
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Format d'email invalide.";
        } else {
            if (!isset($pdo)) {
                $errors['database'] = "Erreur critique: La connexion à la base de données n'a pas pu être établie.";
            } else {
                try {
                    $sql_check_email = "SELECT id FROM Utilisateurs WHERE email = :email";
                    $stmt_check_email = $pdo->prepare($sql_check_email);
                    $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt_check_email->execute();
                    if ($stmt_check_email->rowCount() > 0) {
                        $errors['email'] = "Cette adresse email est déjà utilisée.";
                    }
                } catch (PDOException $e) {
                    $errors['database'] = "Oops! Quelque chose s'est mal passé lors de la vérification de l'email. Veuillez réessayer plus tard.";
                    error_log("PDO Email Check Error: " . $e->getMessage());
                }
            }
        }
    }

    // Validate Téléphone (optional, but if provided, validate)
    if (!empty(trim($_POST["telephone"]))) {
        $telephone = trim($_POST["telephone"]);
    } else {
        $telephone = null;
    }

    // Validate Mot de passe
    if (empty(trim($_POST["password"]))) {
        $errors['password'] = "Veuillez entrer un mot de passe.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $errors['password'] = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate Confirmer le mot de passe
    if (empty(trim($_POST["confirm_password"]))) {
        $errors['confirm_password'] = "Veuillez confirmer le mot de passe.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($errors['password']) && ($password != $confirm_password)) {
            $errors['confirm_password'] = "Les mots de passe ne correspondent pas.";
        }
    }

    // Validate Conditions générales
    if (empty($_POST["conditions"])) {
        $errors['conditions'] = "Vous devez accepter les conditions générales.";
    }

    // If no errors, proceed to insert into database
    if (empty($errors)) {
        if (!isset($pdo)) {
            // This check is somewhat redundant if already done for email check, but good for robustness
            $errors['database'] = "Erreur critique: La connexion à la base de données n'a pas pu être établie avant l'inscription.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // Insert into Utilisateurs table
                $sql_user = "INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte) VALUES (:nom, :prenom, :email, :mot_de_passe, :type_compte)";
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->bindParam(':nom', $nom, PDO::PARAM_STR);
                $stmt_user->bindParam(':prenom', $prenom, PDO::PARAM_STR);
                $stmt_user->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_user->bindParam(':mot_de_passe', $hashed_password, PDO::PARAM_STR);
                $stmt_user->bindParam(':type_compte', $type_compte, PDO::PARAM_STR);

                if (!$stmt_user->execute()) {
                    throw new Exception("Erreur lors de la création de l'utilisateur.");
                }
                $id_utilisateur = $pdo->lastInsertId();

                // If user is a client, insert into Clients table
                if ($type_compte === 'client') {
                    $sql_client = "INSERT INTO Clients (id_utilisateur, telephone) VALUES (:id_utilisateur, :telephone)";
                    $stmt_client = $pdo->prepare($sql_client);
                    $stmt_client->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
                    $stmt_client->bindParam(':telephone', $telephone, PDO::PARAM_STR);
                    if (!$stmt_client->execute()) {
                        throw new Exception("Erreur lors de la création du profil client.");
                    }
                }

                $pdo->commit();
                $_SESSION['registration_success'] = "Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.";
                header("location: ../../votre-compte.php");
                exit();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['database'] = "Erreur lors de l'inscription: " . $e->getMessage();
                error_log("PDO Registration Error: " . $e->getMessage());
            }
        }
    }

    // If there were errors, store them in session and redirect back to registration form
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_input'] = $_POST;
        header("location: ../../votre-compte.php#inscription");
        exit();
    }
}
?> 