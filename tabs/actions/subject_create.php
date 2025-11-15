<?php
session_start();
require_once('../../db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $subject_name = $_POST['subject_name'] ?? '';
  $special = $_POST['special'] ?? '';
  $grade_level = $_POST['grade_level'] ?? '';
  $strand = $_POST['strand'] ?? '';

  // Validate required fields
  if (empty($subject_name) || empty($grade_level) || empty($strand)) {
    die("Error: Missing required fields (subject_name, grade_level, strand)");
  }

  // Use prepared statement
  $stmt = $conn->prepare("INSERT INTO subjects (subject_name, special, grade_level, strand) VALUES (?, ?, ?, ?)");
  
  if (!$stmt) {
    die("Prepare failed: " . $conn->error);
  }

  $stmt->bind_param('ssss', $subject_name, $special, $grade_level, $strand);

  if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: /mainscheduler/index.php?tab=subject_list');
    exit;
  } else {
    echo "Error: " . htmlspecialchars($stmt->error);
    $stmt->close();
    $conn->close();
  }
}
?>