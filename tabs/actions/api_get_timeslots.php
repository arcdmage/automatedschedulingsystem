<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

// Get section_id from query parameter
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

if ($section_id) {
    // Fetch time slots for specific section
    $query = "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
} else {
    // No section specified, return empty array
    $data = [];
}

echo json_encode($data);
$conn->close();
?>