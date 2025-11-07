<?php
// Include database connection
session_start();
require_once('../../db_connect.php');

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
  // Get form values safely
  $sname = $_POST['sname'];
  $special = $_POST['special'];
  $gradelvl = $_POST['gradelvl'];

  // Prepare and execute SQL insert query
  $sql = "INSERT INTO faculty (sname, special, gradelvl)
          VALUES ('$sname', '$special', '$gradelvl')";

  if ($conn->query($sql) === TRUE) {
    echo "<script>alert('Faculty added successfully!'); window.location.href='../../index.php';</script>";
  } else {
    echo "Error: " . $sql . "<br>" . $conn->error;
  }

  // Close connection
  $conn->close();
}
?>
