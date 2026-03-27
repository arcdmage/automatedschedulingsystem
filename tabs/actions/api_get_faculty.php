<?php
require_once(__DIR__ . '/../../db_connect.php');
require_once __DIR__ . '/../../lib/scheduler_staff_helpers.php';
header('Content-Type: application/json');

$data = [];
foreach (available_faculty_rows($conn) as $row) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>