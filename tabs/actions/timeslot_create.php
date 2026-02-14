<?php
// ===== timeslot_create.php (UPDATED) =====
require_once(__DIR__ . '/../../db_connect.php');
require_once(__DIR__ . '/action_helper.php'); // <-- include the helper
header('Content-Type: application/json');

try {
    // Accept either single slot fields or a 'pattern' JSON array of slots
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    if (!$section_id) {
        throw new Exception('Missing section_id');
    }

    // Read optional single-slot fields
    $start_time = $_POST['start_time'] ?? null;
    $end_time   = $_POST['end_time'] ?? null;
    $is_break   = isset($_POST['is_break']) ? intval($_POST['is_break']) : 0;
    $break_label = $_POST['break_label'] ?? '';

    // Pattern: optional JSON array of {start_time,end_time,is_break,break_label}
    $pattern_raw = $_POST['pattern'] ?? null;
    $slots_to_insert = [];

    if ($pattern_raw) {
        $pattern = json_decode($pattern_raw, true);
        if (!is_array($pattern)) {
            throw new Exception('Invalid pattern format');
        }
        foreach ($pattern as $p) {
            if (empty($p['start_time']) || empty($p['end_time'])) continue;
            $slots_to_insert[] = [
                'start_time' => $p['start_time'],
                'end_time'   => $p['end_time'],
                'is_break'   => isset($p['is_break']) ? intval($p['is_break']) : 0,
                'break_label'=> $p['break_label'] ?? ''
            ];
        }
        if (empty($slots_to_insert)) {
            throw new Exception('Pattern contains no valid slots');
        }
    } else {
        // single slot mode: require start/end
        if (empty($start_time) || empty($end_time)) {
            throw new Exception('Missing start_time or end_time');
        }
        $slots_to_insert[] = [
            'start_time' => $start_time,
            'end_time'   => $end_time,
            'is_break'   => $is_break,
            'break_label'=> $break_label
        ];
    }

    // Start transaction
    $conn->begin_transaction();

    // Get starting slot_order for this section (next available)
    $order_stmt = $conn->prepare("SELECT COALESCE(MAX(slot_order), 0) + 1 as next_order FROM time_slots WHERE section_id = ?");
    $order_stmt->bind_param("i", $section_id);
    $order_stmt->execute();
    $order_res = $order_stmt->get_result()->fetch_assoc();
    $next_order = (int)$order_res['next_order'];
    $order_stmt->close();

    // Prepare duplicate check and insert statements
    $check_dup = $conn->prepare("SELECT time_slot_id FROM time_slots WHERE section_id = ? AND start_time = ? AND end_time = ?");
    $insert_stmt = $conn->prepare("INSERT INTO time_slots (section_id, start_time, end_time, is_break, break_label, slot_order) VALUES (?, ?, ?, ?, ?, ?)");

    $inserted_ids = [];
    $duplicates = [];
    $errors = [];

    foreach ($slots_to_insert as $slot) {
        $s_start = $slot['start_time'];
        $s_end = $slot['end_time'];
        $s_is_break = $slot['is_break'] ? 1 : 0;
        $s_break_label = $slot['break_label'] ?? '';

        // Duplicate check
        $check_dup->bind_param("iss", $section_id, $s_start, $s_end);
        $check_dup->execute();
        $check_res = $check_dup->get_result();
        if ($check_res && $check_res->num_rows > 0) {
            $duplicates[] = ['start_time' => $s_start, 'end_time' => $s_end];
            continue; // skip inserting this slot
        }

        // Insert
        // Ensure break_label is a string (no nulls for bind_param)
        $insert_stmt->bind_param("issisi", $section_id, $s_start, $s_end, $s_is_break, $s_break_label, $next_order);
        if (!$insert_stmt->execute()) {
            $errors[] = "Failed to insert slot {$s_start}-{$s_end}: " . $insert_stmt->error;
        } else {
            $inserted_ids[] = $conn->insert_id;
            $next_order++; // increment for next inserted slot
        }
    }

    $check_dup->close();
    $insert_stmt->close();

    // Commit or rollback based on errors
    if (!empty($errors)) {
        $conn->rollback();
        throw new Exception('One or more inserts failed: ' . implode('; ', $errors));
    } else {
        $conn->commit();
        // on success:
        $result = [
            'success' => true,
            'inserted_ids' => $inserted_ids,
            'duplicates' => $duplicates,
            'message' => count($inserted_ids) . ' slot(s) created'
        ];
        // use common finish helper
        finish_action($result);
    }

} catch (Exception $e) {
    // return error via helper
    finish_action(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>