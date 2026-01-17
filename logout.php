<?php
session_start();
// Security: Prevent session fixation attacks
session_regenerate_id(true); 
// Destroy all data
$_SESSION = [];
session_destroy();
// Redirect to login
header("Location: login.php");
exit;
?>