 <?php
$servername = "localhost"; // db address or name
$database = "main"; // database name
$username = "root"; // database username
$password = ""; // database password

// Create connection
$conn = new mysqli($servername, $username, $password, $database);date_get_last_errors(); // Create connection

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error); // terminate script if connection fails
}
echo "Connected successfully to ", $database; // confirm successful connection
?> 