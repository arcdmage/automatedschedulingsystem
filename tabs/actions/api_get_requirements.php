<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$section_id = intval($_GET['section_id']);

if (!$section_id) {
    echo json_encode([]);
    exit;
}

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
$stmt->close();
$conn->close();
?>