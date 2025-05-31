<?php
require_once '../config/db.php'; // Provides $pdo
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error_message_prop'] = "Accès non autorisé.";
    header("Location: ../../index.php");
    exit;
}

$id_enchere = filter_input(INPUT_POST, 'id_enchere', FILTER_VALIDATE_INT);
$id_propriete = filter_input(INPUT_POST, 'id_propriete', FILTER_VALIDATE_INT);
$montant_offre = filter_input(INPUT_POST, 'montant_offre', FILTER_VALIDATE_FLOAT);
$id_client = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Define redirect URLs
$redirect_url_success = "../../propriete_details.php?id=" . ($id_propriete ?? '') . "#enchere";
$redirect_url_failure_page = "../../placer_enchere_page.php?id_propriete=" . ($id_propriete ?? '') . "&enchere_id=" . ($id_enchere ?? '');
$redirect_url_default_fallback = "../../index.php"; // Fallback if IDs are missing early

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'client') {
    $_SESSION['error_message_prop'] = "Vous devez être connecté en tant que client pour placer une offre.";
    // If property and enchere IDs are available, redirect to placer_enchere_page, otherwise to login page
    $redirect_target = ($id_propriete && $id_enchere) ? $redirect_url_failure_page : "../../votre-compte.php";
    header("Location: " . $redirect_target);
    exit;
}

if (!$id_client) { // Should not happen if logged in as client, but good to check
    $_SESSION['error_message_prop'] = "Utilisateur non identifié. Veuillez vous reconnecter.";
    header("Location: " . $redirect_url_default_fallback);
    exit;
}

if (!$id_enchere || !$id_propriete || $montant_offre === false || $montant_offre <= 0) {
    $_SESSION['error_message_prop'] = "Données de l'offre invalides. Veuillez vérifier le montant.";
    header("Location: " . ($id_propriete && $id_enchere ? $redirect_url_failure_page : $redirect_url_default_fallback));
    exit;
}

if (!$pdo) {
    $_SESSION['error_message_prop'] = "Erreur de connexion à la base de données.";
    header("Location: " . $redirect_url_failure_page);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Vérifier les détails de l'enchère et le prix actuel
    $sql_check_enchere = "SELECT e.date_heure_debut, e.date_heure_fin, e.prix_depart, p.id_agent_responsable, 
                            COALESCE(MAX(oe.montant_offre), e.prix_depart) as prix_actuel 
                        FROM Encheres e
                        JOIN Proprietes p ON e.id_propriete = p.id
                        LEFT JOIN OffresEncheres oe ON e.id = oe.id_enchere
                        WHERE e.id = :id_enchere AND e.id_propriete = :id_propriete
                        GROUP BY e.id, e.date_heure_debut, e.date_heure_fin, e.prix_depart, p.id_agent_responsable";
    
    $stmt_check = $pdo->prepare($sql_check_enchere);
    $stmt_check->execute([':id_enchere' => $id_enchere, ':id_propriete' => $id_propriete]);
    $enchere_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$enchere_data) {
        throw new Exception("Enchère non trouvée ou invalide.");
    }

    // 2. Vérifier si l'enchère est active
    $now = new DateTime();
    $date_debut_enchere = new DateTime($enchere_data['date_heure_debut']);
    $date_fin_enchere = new DateTime($enchere_data['date_heure_fin']);
    if (!($now >= $date_debut_enchere && $now <= $date_fin_enchere)) {
        throw new Exception("Cette enchère n'est plus active.");
    }

    // 3. Vérifier que le client n'est pas l'agent responsable de la propriété
    if ($id_client == $enchere_data['id_agent_responsable']) {
        throw new Exception("En tant qu'agent responsable, vous ne pouvez pas enchérir sur cette propriété.");
    }

    // 4. Vérifier que le montant de l'offre est supérieur au prix actuel
    $prix_actuel = floatval($enchere_data['prix_actuel']);
    if ($montant_offre <= $prix_actuel) {
        throw new Exception("Votre offre doit être supérieure au prix actuel de " . number_format($prix_actuel, 2, ',', ' ') . " €.");
    }

    // 5. Insérer la nouvelle offre
    $sql_insert_offre = "INSERT INTO OffresEncheres (id_enchere, id_client, montant_offre, date_heure_offre) VALUES (:id_enchere, :id_client, :montant_offre, NOW())";
    $stmt_insert = $pdo->prepare($sql_insert_offre);
    $stmt_insert->execute([
        ':id_enchere' => $id_enchere,
        ':id_client' => $id_client,
        ':montant_offre' => $montant_offre
    ]);

    $pdo->commit();
    $_SESSION['success_message_prop'] = "Votre offre de " . number_format($montant_offre, 2, ',', ' ') . " € a été placée avec succès !";
    header("Location: " . $redirect_url_success); // Redirect to property details on success
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message_prop'] = "Erreur de base de données: " . $e->getMessage();
    error_log("PDO Error in placer_offre_action.php: " . $e->getMessage()); 
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message_prop'] = $e->getMessage();
    error_log("General Error in placer_offre_action.php: " . $e->getMessage());
}

// If any exception occurred and was caught, redirect to failure page
header("Location: " . $redirect_url_failure_page);
exit;
?> 