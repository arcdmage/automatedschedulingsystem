<?php
// ===== api_get_requirements.php =====
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$section_id = intval($_GET['section_id']);

$query = "SELECT sr.*, s.subject_name, CONCAT(f.lname, ', ', f.fname) AS teacher_name,
          (SELECT COUNT(*) FROM schedule_patterns WHERE requirement_id = sr.requirement_id) as pattern_count
          FROM subject_requirements sr
          JOIN subjects s ON sr.subject_id = s.subject_id
          JOIN faculty f ON sr.faculty_id = f.faculty_id
          WHERE sr.section_id = ?
          ORDER BY s.subject_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();

// ===== api_get_pattern.php =====
/*
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$requirement_id = intval($_GET['requirement_id']);

$query = "SELECT * FROM schedule_patterns WHERE requirement_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $requirement_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
*/

// ===== api_get_timeslots.php =====
/*
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT * FROM time_slots ORDER BY slot_order";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
*/

// ===== api_get_sections.php =====
/*
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT * FROM sections ORDER BY grade_level, section_name";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
*/

// ===== api_get_subjects.php =====
/*
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT * FROM subjects ORDER BY subject_name";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
*/

// ===== api_get_faculty.php =====
/*
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT * FROM faculty ORDER BY lname, fname";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
*/
?>