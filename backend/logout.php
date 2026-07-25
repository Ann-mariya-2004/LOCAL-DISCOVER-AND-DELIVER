<?php

session_start();


// Remove all session data

session_unset();


// Destroy session

session_destroy();


// Redirect to login page

header("Location: ../login_registration.html");

exit();

?>