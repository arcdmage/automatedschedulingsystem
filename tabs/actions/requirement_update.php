<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $requirement_id = intval($_POST['requirement_id']);
    $faculty_id = intval($_POST['faculty_id']);
    $hours_per_week = intval($_POST['hours_per_week']);
    
    if (!$requirement_id || !$faculty_id || !$hours_per_week) {
        throw new Exception('Missing required fields');
    }
    
    $stmt = $conn->prepare("UPDATE subject_requirements SET faculty_id = ?, hours_per_week = ? WHERE requirement_id = ?");
    $stmt->bind_param("iii", $faculty_id, $hours_per_week, $requirement_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Subject updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update subject');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>