<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $from_section_id = intval($_POST['from_section_id']);
    $to_section_id = intval($_POST['to_section_id']);
    
    if (!$from_section_id || !$to_section_id) {
        throw new Exception('Source and destination section IDs are required');
    }
    
    if ($from_section_id === $to_section_id) {
        throw new Exception('Cannot copy to the same section');
    }
    
    // Fetch time slots from source section
    $fetch_stmt = $conn->prepare("SELECT start_time, end_time, is_break, break_label, slot_order FROM time_slots WHERE section_id = ? ORDER BY slot_order");
    $fetch_stmt->bind_param("i", $from_section_id);
    $fetch_stmt->execute();
    $slots = $fetch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fetch_stmt->close();
    
    if (count($slots) === 0) {
        throw new Exception('No time slots found in the source section');
    }

    $conn->begin_transaction();

    // Delete dependent schedule_patterns rows first (foreign key: schedule_patterns.time_slot_id → time_slots.time_slot_id)
    $del_patterns_stmt = $conn->prepare(
        "DELETE sp FROM schedule_patterns sp
         INNER JOIN time_slots ts ON sp.time_slot_id = ts.time_slot_id
         WHERE ts.section_id = ?"
    );
    $del_patterns_stmt->bind_param("i", $to_section_id);
    $del_patterns_stmt->execute();
    $del_patterns_stmt->close();

    // Also remove any schedules referencing these time slots
    $del_schedules_stmt = $conn->prepare(
        "DELETE s FROM schedules s
         INNER JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
         WHERE ts.section_id = ?"
    );
    $del_schedules_stmt->bind_param("i", $to_section_id);
    $del_schedules_stmt->execute();
    $del_schedules_stmt->close();

    // Now safe to delete existing time slots in destination section
    $delete_stmt = $conn->prepare("DELETE FROM time_slots WHERE section_id = ?");
    $delete_stmt->bind_param("i", $to_section_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Copy time slots to destination section
    $insert_stmt = $conn->prepare("INSERT INTO time_slots (section_id, start_time, end_time, is_break, break_label, slot_order) VALUES (?, ?, ?, ?, ?, ?)");
    
    $slots_copied = 0;
    foreach ($slots as $slot) {
        $insert_stmt->bind_param("issisi", 
            $to_section_id,
            $slot['start_time'],
            $slot['end_time'],
            $slot['is_break'],
            $slot['break_label'],
            $slot['slot_order']
        );
        
        if ($insert_stmt->execute()) {
            $slots_copied++;
        }
    }
    
    $insert_stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Time slots copied successfully',
        'slots_copied' => $slots_copied
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>