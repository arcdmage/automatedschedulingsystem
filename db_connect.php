<?php
require_once __DIR__ . "/lib/app_path.php";

$servername = "db.fr-pari1.bengt.wasmernet.com"; // db address or host
$database = "db8WqMRmz97PJdi4SJsjGmVZ"; // database name
$username = "843b3d48797a800056eef0c5fc31"; // database username
$password = "069c843b-3d48-7a90-8000-3f9fd4c5f214"; // database password

// Create connection
$conn = new mysqli($servername, $username, $password, $database); // Create connection

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error); // terminate script if connection fails
}
?>
