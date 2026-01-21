<?php
session_start();
session_regenerate_id(true); 
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
?>