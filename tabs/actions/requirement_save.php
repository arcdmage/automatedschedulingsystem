<?php
session_start();
require_once('../../db_connect.php');

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
  // Validate and sanitize inputs
  $section_id = intval($_POST['section_id']);
  $subject_id = intval($_POST['subject_id']);
  $faculty_id = intval($_POST['faculty_id']);
  $hours_per_week = intval($_POST['hours_per_week']);
  $preferred_days = isset($_POST['preferred_days']) ? trim($_POST['preferred_days']) : null;
  $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

  // Validate required fields
  if ($section_id <= 0 || $subject_id <= 0 || $faculty_id <= 0 || $hours_per_week <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
  }

  // Validate hours per week range
  if ($hours_per_week < 1 || $hours_per_week > 10) {
    echo json_encode(['success' => false, 'message' => 'Hours per week must be between 1 and 10']);
    exit;
  }

  // Check if section exists
  $check_section = $conn->prepare("SELECT section_id FROM sections WHERE section_id = ?");
  $check_section->bind_param("i", $section_id);
  $check_section->execute();
  if ($check_section->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Section not found']);
    exit;
  }
  $check_section->close();

  // Check if subject exists
  $check_subject = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
  $check_subject->bind_param("i", $subject_id);
  $check_subject->execute();
  if ($check_subject->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
    exit;
  }
  $check_subject->close();

  // Check if faculty exists
  $check_faculty = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_id = ?");
  $check_faculty->bind_param("i", $faculty_id);
  $check_faculty->execute();
  if ($check_faculty->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
    exit;
  }
  $check_faculty->close();

  // Check for duplicate requirement (same section, subject, and faculty)
  $duplicate_check = $conn->prepare("SELECT requirement_id FROM subject_requirements 
                                     WHERE section_id = ? AND subject_id = ? AND faculty_id = ?");
  $duplicate_check->bind_param("iii", $section_id, $subject_id, $faculty_id);
  $duplicate_check->execute();
  if ($duplicate_check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This subject requirement already exists for this section and teacher']);
    exit;
  }
  $duplicate_check->close();

  // Prepare and execute insert query
  $sql = "INSERT INTO subject_requirements (section_id, subject_id, faculty_id, hours_per_week, preferred_days, notes, created_at)
          VALUES (?, ?, ?, ?, ?, ?, NOW())";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iiisss", $section_id, $subject_id, $faculty_id, $hours_per_week, $preferred_days, $notes);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true, 
      'message' => 'Subject requirement added successfully',
      'requirement_id' => $conn->insert_id
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
