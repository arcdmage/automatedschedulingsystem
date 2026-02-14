<?php
// ===== timeslot_delete.php =====
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $time_slot_id = intval($_POST['time_slot_id']);
    $section_id = intval($_POST['section_id']);
    
    if (!$time_slot_id || !$section_id) {
        throw new Exception('Time slot ID and section ID are required');
    }
    
    // Check if time slot is used in schedules
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedules WHERE time_slot_id = ?");
    $check_stmt->bind_param("i", $time_slot_id);
    $check_stmt->execute();
    $count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($count > 0) {
        throw new Exception("Cannot delete: This time slot is used in $count schedule(s). Please remove those schedules first.");
    }
    
    // Check if used in patterns
    $pattern_check = $conn->prepare("SELECT COUNT(*) as count FROM schedule_patterns WHERE time_slot_id = ?");
    $pattern_check->bind_param("i", $time_slot_id);
    $pattern_check->execute();
    $pattern_count = $pattern_check->get_result()->fetch_assoc()['count'];
    $pattern_check->close();
    
    if ($pattern_count > 0) {
        throw new Exception("Cannot delete: This time slot is used in $pattern_count schedule pattern(s). Please remove those patterns first.");
    }
    
    // Delete the time slot
    $stmt = $conn->prepare("DELETE FROM time_slots WHERE time_slot_id = ? AND section_id = ?");
    $stmt->bind_param("ii", $time_slot_id, $section_id);
    
    if ($stmt->execute()) {
        // Reorder remaining slots for this section
        $reorder_query = "SET @order = 0; 
                         UPDATE time_slots 
                         SET slot_order = (@order := @order + 1) 
                         WHERE section_id = ? 
                         ORDER BY slot_order";
        $conn->query("SET @order = 0");
        $reorder_stmt = $conn->prepare("UPDATE time_slots SET slot_order = (@order := @order + 1) WHERE section_id = ? ORDER BY slot_order");
        $reorder_stmt->bind_param("i", $section_id);
        $reorder_stmt->execute();
        $reorder_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Time slot deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete time slot');
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