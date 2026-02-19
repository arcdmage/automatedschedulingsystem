<?php
session_start();
require_once('../../db_connect.php');

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
  // Validate and sanitize input
  $requirement_id = intval($_POST['requirement_id']);

  // Validate required field
  if ($requirement_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid requirement ID']);
    exit;
  }

  // Check if requirement exists
  $check_query = "SELECT requirement_id FROM subject_requirements WHERE requirement_id = ?";
  $stmt = $conn->prepare($check_query);
  $stmt->bind_param("i", $requirement_id);
  $stmt->execute();
  $check_result = $stmt->get_result();

  if ($check_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Requirement not found']);
    exit;
  }

  // Delete the requirement
  $delete_query = "DELETE FROM subject_requirements WHERE requirement_id = ?";
  $stmt = $conn->prepare($delete_query);
  $stmt->bind_param("i", $requirement_id);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true, 
      'message' => 'Subject requirement deleted successfully'
    ]);
  } else {
    echo json_encode([
      'success' => false, 
      'message' => 'Database error: ' . $conn->error
    ]);
  }

  $stmt->close();
  $conn->close();
  
} else {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>