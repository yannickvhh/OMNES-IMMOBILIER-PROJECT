<?php
// Initialize the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
if (session_destroy()) {
    // If the session was destroyed successfully, regenerate the session ID as an extra security measure
    // though it's less critical after destruction if no new session is immediately started for the same user.
    // session_regenerate_id(true); // Optional: can cause issues if output already started.
} else {
    // Handle error if session_destroy fails, though it rarely does.
    // error_log("Failed to destroy session.");
}

// Clear session cookie as well, though session_destroy() usually handles this.
// This is an extra measure for some configurations.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page or home page
header("location: ../../index.php");
exit;
?> 