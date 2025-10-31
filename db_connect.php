 <?php
$servername = "localhost"; // db address or host
$database = "main"; // database name
$username = "root"; // database username
$password = ""; // database password

// Create connection
$conn = new mysqli($servername, $username, $password, $database); // Create connection

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error); // terminate script if connection fails
}
?> 