<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $time_slot_id = intval($_POST['time_slot_id']);
    $section_id = intval($_POST['section_id']);
    $direction = $_POST['direction']; // 'up' or 'down'
    
    if (!$time_slot_id || !$section_id || !in_array($direction, ['up', 'down'])) {
        throw new Exception('Invalid parameters');
    }
    
    // Get current slot
    $stmt = $conn->prepare("SELECT slot_order FROM time_slots WHERE time_slot_id = ? AND section_id = ?");
    $stmt->bind_param("ii", $time_slot_id, $section_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$current) {
        throw new Exception('Time slot not found');
    }
    
    $current_order = $current['slot_order'];
    $new_order = $direction === 'up' ? $current_order - 1 : $current_order + 1;
    
    // Check if swap is possible within the same section
    $check_stmt = $conn->prepare("SELECT time_slot_id FROM time_slots WHERE slot_order = ? AND section_id = ?");
    $check_stmt->bind_param("ii", $new_order, $section_id);
    $check_stmt->execute();
    $swap_slot = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if (!$swap_slot) {
        throw new Exception('Cannot move in that direction');
    }
    
    // Swap orders
    $conn->begin_transaction();
    
    // Use negative value as temporary to avoid unique constraint violation
    $temp_order = -999;
    $temp_stmt = $conn->prepare("UPDATE time_slots SET slot_order = ? WHERE time_slot_id = ?");
    $temp_stmt->bind_param("ii", $temp_order, $time_slot_id);
    $temp_stmt->execute();
    $temp_stmt->close();
    
    // Update swap slot
    $swap_stmt = $conn->prepare("UPDATE time_slots SET slot_order = ? WHERE time_slot_id = ?");
    $swap_stmt->bind_param("ii", $current_order, $swap_slot['time_slot_id']);
    $swap_stmt->execute();
    $swap_stmt->close();
    
    // Update current slot
    $current_stmt = $conn->prepare("UPDATE time_slots SET slot_order = ? WHERE time_slot_id = ?");
    $current_stmt->bind_param("ii", $new_order, $time_slot_id);
    $current_stmt->execute();
    $current_stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Time slot reordered successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();