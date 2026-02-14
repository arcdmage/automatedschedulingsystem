<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $time_slot_id = intval($_POST['time_slot_id']);
    $section_id = intval($_POST['section_id']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $is_break = intval($_POST['is_break']);
    $break_label = isset($_POST['break_label']) ? $_POST['break_label'] : null;
    
    if (!$time_slot_id || !$section_id || !$start_time || !$end_time) {
        throw new Exception('Missing required fields');
    }
    
    if ($is_break && empty($break_label)) {
        throw new Exception('Break label is required for break periods');
    }
    
    // Verify the time slot belongs to this section
    $verify_stmt = $conn->prepare("SELECT section_id FROM time_slots WHERE time_slot_id = ?");
    $verify_stmt->bind_param("i", $time_slot_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();
    
    if (!$verify_result || $verify_result['section_id'] != $section_id) {
        throw new Exception('Time slot does not belong to this section');
    }
    
    $stmt = $conn->prepare("UPDATE time_slots SET start_time = ?, end_time = ?, is_break = ?, break_label = ? WHERE time_slot_id = ? AND section_id = ?");
    $stmt->bind_param("ssiiii", $start_time, $end_time, $is_break, $break_label, $time_slot_id, $section_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Time slot updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update time slot');
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