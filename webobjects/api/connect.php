<?php

include(__DIR__ . "/../../password.php");

$servername = "localhost";
$username = "timeuser";
$dbname = "winlogonlimiter";

if (empty($password)){
	die ("You must include a password variable in the ../../password.php file.");
}

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>
