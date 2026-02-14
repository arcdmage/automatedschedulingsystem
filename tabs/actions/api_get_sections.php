<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT section_id, section_name, grade_level, track, school_year, semester FROM sections ORDER BY grade_level, section_name";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>