<?php
// Include database connection
session_start();
require_once('../../db_connect.php');

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
  // Get form values safely
  $fname = $_POST['fname'];
  $mname = $_POST['mname'];
  $lname = $_POST['lname'];
  $gender = $_POST['gender'];
  $pnumber = $_POST['pnumber'];
  $address = $_POST['address'];
  $status = $_POST['status'];

  // Prepare and execute SQL insert query
  $sql = "INSERT INTO faculty (fname, mname, lname, gender, pnumber, address, status)
          VALUES ('$fname', '$mname', '$lname', '$gender', '$pnumber', '$address', '$status')";

  if ($conn->query($sql) === TRUE) {
    echo "<script>alert('Faculty added successfully!'); window.location.href='../../index.php';</script>";
  } else {
    echo "Error: " . $sql . "<br>" . $conn->error;
  }

  // Close connection
  $conn->close();
}
?>
