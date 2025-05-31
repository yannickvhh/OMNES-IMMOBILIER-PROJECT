<?php
require_once '../config/db.php'; // Provides $pdo
session_start();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unexpected error occurred.']; // Default error

if (!isset($pdo)) {
    $response['message'] = 'Erreur critique: La connexion à la base de données n\'a pas pu être établie.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Authentification requise.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Méthode non autorisée.';
    echo json_encode($response);
    exit;
}

$id_expediteur = $_SESSION['user_id'];
$id_destinataire = filter_input(INPUT_POST, 'id_destinataire', FILTER_VALIDATE_INT);
$contenu_message = filter_input(INPUT_POST, 'contenu_message', FILTER_SANITIZE_SPECIAL_CHARS); // Basic sanitization
// Consider more robust sanitization if HTML is ever allowed or for XSS prevention

if (!$id_destinataire) {
    $response['message'] = 'ID du destinataire manquant ou invalide.';
    echo json_encode($response);
    exit;
}

if (empty(trim($contenu_message))) {
    $response['message'] = 'Le message ne peut pas être vide.';
    echo json_encode($response);
    exit;
}

// Additional check: a user cannot send a message to themselves
if ($id_expediteur == $id_destinataire) {
    $response['message'] = 'Vous ne pouvez pas vous envoyer de message à vous-même.';
    echo json_encode($response);
    exit;
}

try {
    // Check if destination user exists
    $stmt_check_user = $pdo->prepare("SELECT id FROM Utilisateurs WHERE id = :id_destinataire");
    $stmt_check_user->execute([':id_destinataire' => $id_destinataire]);
    if ($stmt_check_user->fetchColumn() === false) {
        $response['message'] = 'Le destinataire n\'existe pas.';
        echo json_encode($response);
        exit;
    }

    // Insert the message
    $sql = "INSERT INTO Messages (id_expediteur, id_destinataire, contenu_message, type_communication) VALUES (:id_expediteur, :id_destinataire, :contenu_message, 'texte')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_expediteur' => $id_expediteur,
        ':id_destinataire' => $id_destinataire,
        ':contenu_message' => $contenu_message
    ]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Message envoyé avec succès.';
        $response['message_id'] = $pdo->lastInsertId(); 
    } else {
        $response['message'] = 'Erreur lors de l\'envoi du message (aucune ligne affectée).';
    }

} catch (PDOException $e) {
    error_log("PDO Error in send_message_action.php: " . $e->getMessage());
    $response['message'] = 'Erreur de base de données lors de l\'envoi du message.';
    // For debugging, you might include $e->getMessage() in the response, but not for production.
}

echo json_encode($response);
?> 