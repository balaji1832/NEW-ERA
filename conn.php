<?php
// config.php
$host = "localhost";          // your database host
$username = "root";           // your DB username old pwd - WHCwUZEDL0Pm
$password = "";               // your DB password    new pwd -- gwO9_(uD0Qup
$dbname = "new-era";          // your DB name

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
