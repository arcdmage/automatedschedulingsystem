<?php
session_start();
require_once('../../db_connect.php');

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
  // Validate and sanitize inputs
  $faculty_id = intval($_POST['faculty_id']);
  $subject_id = intval($_POST['subject_id']);
  $schedule_date = $_POST['schedule_date'];
  $start_time = $_POST['start_time'];
  $end_time = $_POST['end_time'];
  $room = isset($_POST['room']) ? $_POST['room'] : '';
  $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

  // Validate that end time is after start time
  if (strtotime($end_time) <= strtotime($start_time)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
  }

  // Check for scheduling conflicts
  $conflict_query = "SELECT s.*, f.fname, f.lname, sub.subject_name 
                     FROM schedules s
                     JOIN faculty f ON s.faculty_id = f.faculty_id
                     JOIN subjects sub ON s.subject_id = sub.subject_id
                     WHERE s.faculty_id = ? 
                     AND s.schedule_date = ?
                     AND (
                       (s.start_time < ? AND s.end_time > ?) OR
                       (s.start_time < ? AND s.end_time > ?) OR
                       (s.start_time >= ? AND s.end_time <= ?)
                     )";
  
  $stmt = $conn->prepare($conflict_query);
  $stmt->bind_param("isssssss", 
    $faculty_id, 
    $schedule_date, 
    $end_time, $start_time,
    $end_time, $start_time,
    $start_time, $end_time
  );
  $stmt->execute();
  $conflict_result = $stmt->get_result();

  if ($conflict_result->num_rows > 0) {
    $conflict = $conflict_result->fetch_assoc();
    echo json_encode([
      'success' => false, 
      'message' => 'Schedule conflict detected! Teacher already has a class scheduled at this time.'
    ]);
    exit;
  }

  // Prepare and execute insert query
  $sql = "INSERT INTO schedules (faculty_id, subject_id, schedule_date, start_time, end_time, room, notes, created_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iisssss", $faculty_id, $subject_id, $schedule_date, $start_time, $end_time, $room, $notes);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true, 
      'message' => 'Schedule created successfully',
      'schedule_id' => $conn->insert_id
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