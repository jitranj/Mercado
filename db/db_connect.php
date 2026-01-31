<?php
error_reporting(0); 
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
?>
<?php
$host = "localhost";
$user = "root"; 
$pass = "";
$dbname = "floor_map_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
