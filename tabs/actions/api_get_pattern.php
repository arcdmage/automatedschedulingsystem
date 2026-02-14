<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$requirement_id = intval($_GET['requirement_id']);

$query = "SELECT * FROM schedule_patterns WHERE requirement_id = ? ORDER BY day_of_week, time_slot_id";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $requirement_id);
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