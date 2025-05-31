<?php

// --- Database Configuration ---
// !!! IMPORTANT: Update these details to match your database connection !!!
$db_host = 'localhost';
$db_name = 'omnes_immobilier_db'; // <<< RENAME THIS TO YOUR ACTUAL DATABASE NAME
$db_user = 'root';                // Replace if your MySQL username is different
$db_pass = '';                    // Replace if your MySQL root user has a password
// --- End Database Configuration ---

echo "<!DOCTYPE html><html><head><title>User Seeding</title></head><body>";
echo "<h1>Omnes Immobilier User Seeding Script</h1>";

// --- User Data ---
$users_data = [
    [
        'nom' => 'Dupont',
        'prenom' => 'Admin',
        'email' => 'admin.dupont@omnesimmobilier.fr',
        'plain_password' => 'Adm!nPass123',
        'type_compte' => 'admin',
    ],
    [
        'nom' => 'Martin',
        'prenom' => 'Sophie',
        'email' => 'sophie.martin@email.com',
        'plain_password' => 'Cl!entPass456',
        'type_compte' => 'client',
        'client_details' => [
            'adresse_ligne1' => '123 Rue de la Paix',
            'adresse_ligne2' => null, // Can be a string or null
            'ville' => 'Paris',
            'code_postal' => '75001',
            'pays' => 'France',
            'telephone' => '0123456789',
        ],
    ],
    [
        'nom' => 'Bernard',
        'prenom' => 'Lucas',
        'email' => 'lucas.bernard@omnesimmobilier.fr',
        'plain_password' => 'Ag#ntPass789',
        'type_compte' => 'agent', // As per your schema ENUM
        'agent_details' => [
            'specialite' => 'Appartements Parisiens',
            'bureau' => 'Bureau A-101',
            'telephone_pro' => '0612345678',
            'cv_filename' => 'cv_lucas_bernard.pdf',
            'photo_filename' => 'photo_lucas_bernard.jpg',
        ],
    ],
];

// --- Database Connection (PDO) ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green; font-weight:bold;'>Successfully connected to the database: '$db_name' on '$db_host'.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>DATABASE CONNECTION FAILED:</p>";
    echo "<p style='color:red;'>Error message: " . $e->getMessage() . "</p>";
    echo "<p><strong>Please check your database configuration at the top of this script (<code>seed_users.php</code>).</strong></p>";
    echo "<p>Ensure the database '<strong>$db_name</strong>' exists and the credentials (user '<strong>$db_user</strong>') are correct.</p>";
    echo "</body></html>";
    die();
}

// --- Insertion Logic ---
foreach ($users_data as $user_data) {
    echo "<h3>Processing user: {$user_data['prenom']} {$user_data['nom']} ({$user_data['email']})</h3>";

    // Hash the password
    $hashed_password = password_hash($user_data['plain_password'], PASSWORD_DEFAULT);
    if (!$hashed_password) {
        echo "<p style='color:red;'>Error hashing password for {$user_data['email']}. Skipping.</p>";
        continue;
    }
    echo "<p>Password hashed: [{$user_data['plain_password']}] -> [HASHED]</p>";

    try {
        // Check if user already exists by email
        $stmt_check = $pdo->prepare("SELECT id FROM Utilisateurs WHERE email = :email");
        $stmt_check->bindParam(':email', $user_data['email']);
        $stmt_check->execute();

        if ($existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
            echo "<p style='color:orange;'>User with email {$user_data['email']} (ID: {$existing_user['id']}) already exists. Skipping insertion.</p>";
            // Optionally, you could add logic here to update existing users or their related tables if needed.
        } else {
            // Insert into Utilisateurs table
            $stmt_user = $pdo->prepare("INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe, type_compte) VALUES (:nom, :prenom, :email, :mot_de_passe, :type_compte)");
            $stmt_user->bindParam(':nom', $user_data['nom']);
            $stmt_user->bindParam(':prenom', $user_data['prenom']);
            $stmt_user->bindParam(':email', $user_data['email']);
            $stmt_user->bindParam(':mot_de_passe', $hashed_password);
            $stmt_user->bindParam(':type_compte', $user_data['type_compte']);
            $stmt_user->execute();
            $last_user_id = $pdo->lastInsertId();
            echo "<p style='color:green;'>User {$user_data['email']} inserted into Utilisateurs table with ID: $last_user_id.</p>";

            // Insert into Clients table if applicable
            if ($user_data['type_compte'] === 'client' && isset($user_data['client_details'])) {
                $client_details = $user_data['client_details'];
                $stmt_client = $pdo->prepare("INSERT INTO Clients (id_utilisateur, adresse_ligne1, adresse_ligne2, ville, code_postal, pays, telephone) VALUES (:id_utilisateur, :adresse_ligne1, :adresse_ligne2, :ville, :code_postal, :pays, :telephone)");
                $stmt_client->bindParam(':id_utilisateur', $last_user_id);
                $stmt_client->bindParam(':adresse_ligne1', $client_details['adresse_ligne1']);
                $stmt_client->bindParam(':adresse_ligne2', $client_details['adresse_ligne2']); // Binds NULL if $client_details['adresse_ligne2'] is null
                $stmt_client->bindParam(':ville', $client_details['ville']);
                $stmt_client->bindParam(':code_postal', $client_details['code_postal']);
                $stmt_client->bindParam(':pays', $client_details['pays']);
                $stmt_client->bindParam(':telephone', $client_details['telephone']);
                $stmt_client->execute();
                echo "<p style='color:green;'>Client details inserted for user ID: $last_user_id.</p>";
            }

            // Insert into AgentsImmobiliers table if applicable
            if ($user_data['type_compte'] === 'agent' && isset($user_data['agent_details'])) {
                $agent_details = $user_data['agent_details'];
                $stmt_agent = $pdo->prepare("INSERT INTO AgentsImmobiliers (id_utilisateur, specialite, bureau, telephone_pro, cv_filename, photo_filename) VALUES (:id_utilisateur, :specialite, :bureau, :telephone_pro, :cv_filename, :photo_filename)");
                $stmt_agent->bindParam(':id_utilisateur', $last_user_id);
                $stmt_agent->bindParam(':specialite', $agent_details['specialite']);
                $stmt_agent->bindParam(':bureau', $agent_details['bureau']);
                $stmt_agent->bindParam(':telephone_pro', $agent_details['telephone_pro']);
                $stmt_agent->bindParam(':cv_filename', $agent_details['cv_filename']);
                $stmt_agent->bindParam(':photo_filename', $agent_details['photo_filename']);
                $stmt_agent->execute();
                echo "<p style='color:green;'>Agent details inserted for user ID: $last_user_id.</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color:red;'><strong>Error inserting data for {$user_data['email']}:</strong> " . $e->getMessage() . "</p>";
         if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., duplicate entry for unique key)
            echo "<p style='color:orange;'>This might be because the user (or related data for client/agent) already exists with a conflicting key. Please check your database and table structures.</p>";
        }
    }
    echo "<hr>";
}

echo "<h2>User seeding process complete.</h2>";
echo "<p><strong>IMPORTANT REMINDERS:</strong></p>";
echo "<ul>";
echo "<li>If you ran this script successfully and users are in the database, you should now <strong>delete <code>seed_users.php</code></strong> from your server or rename it to prevent accidental re-execution.</li>";
echo "<li>Ensure your main application's login system uses <strong><code>password_verify()</code></strong> to check passwords against the hashed values now stored in the database.</li>";
echo "<li>If you encountered errors, please review the messages above, check your database configuration in this script, and ensure your database schema (<code>database_schema.sql</code>) has been imported correctly.</li>";
echo "</ul>";
echo "</body></html>";

?> 