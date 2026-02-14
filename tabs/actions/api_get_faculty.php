<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

$query = "SELECT faculty_id, fname, lname, mname FROM faculty ORDER BY lname, fname";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>