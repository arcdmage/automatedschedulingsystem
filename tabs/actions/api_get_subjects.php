php<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>