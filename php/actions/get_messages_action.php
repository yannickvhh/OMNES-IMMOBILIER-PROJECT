<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Erreur critique: La connexion à la base de données n\'a pas pu être établie.']);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$contact_id = filter_input(INPUT_GET, 'contact_id', FILTER_VALIDATE_INT);

if (!$contact_id) {
    echo json_encode(['success' => false, 'message' => 'ID de contact manquant ou invalide.']);
    exit;
}

$messages = [];

try {
    $sql = "SELECT id, id_expediteur, id_destinataire, contenu_message, date_heure_envoi, lu
            FROM Messages 
            WHERE (id_expediteur = :current_user_id1 AND id_destinataire = :contact_id1) 
               OR (id_expediteur = :contact_id2 AND id_destinataire = :current_user_id2) 
            ORDER BY date_heure_envoi ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':current_user_id1' => $current_user_id,
        ':contact_id1' => $contact_id,
        ':contact_id2' => $contact_id,
        ':current_user_id2' => $current_user_id
    ]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $update_sql = "UPDATE Messages SET lu = TRUE WHERE id_expediteur = :contact_id AND id_destinataire = :current_user_id AND lu = FALSE";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':contact_id' => $contact_id,
        ':current_user_id' => $current_user_id
    ]);

    echo json_encode($messages);

} catch (PDOException $e) {
    error_log("PDO Error in get_messages_action.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors de la récupération des messages.']);
    exit;
}
?> 
