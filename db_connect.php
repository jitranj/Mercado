<?php
// --- SECURITY CONFIGURATION ---

// 1. Hide Errors from User (Production Mode)
// Change to '1' only when you are debugging. Keep '0' for normal use.
error_reporting(0); 
ini_set('display_errors', 0);

// 2. Start Session Securely (If not already started)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ... rest of your db connection code ...
$servername = "localhost";
// ...
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
