<?php
// Start the current session
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the current session
session_destroy();

// Redirect to the login and registration page
header("Location: ../login_registration.html");
exit();
?>