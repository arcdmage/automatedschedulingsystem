<?php
session_start();
require_once('../../db_connect.php');

header('Content-Type: application/json');

// Set timezone to ensure date calculations are consistent
date_default_timezone_set('Asia/Manila'); 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // this needs actual VALIDATE INPUTS i think
    $section_id = intval($_POST['section_id'] ?? 0);
    $start_date_str = $_POST['start_date'] ?? '';
    $end_date_str = $_POST['end_date'] ?? '';

    if ($section_id <= 0 || empty($start_date_str) || empty($end_date_str)) {
        echo json_encode(['success' => false, 'message' => 'Invalid inputs provided.']);
        exit;
    }

    $start_ts = strtotime($start_date_str);
    $end_ts = strtotime($end_date_str);

    if ($start_ts > $end_ts) {
        echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
        exit;
    }

    // Fetch Time Slots
    $slots_query = "SELECT time_slot_id, start_time, end_time FROM time_slots WHERE is_break = 0 ORDER BY slot_order";
    $time_slots = $conn->query($slots_query)->fetch_all(MYSQLI_ASSOC);
    $slots_per_day = count($time_slots);

    if ($slots_per_day === 0) {
        echo json_encode(['success' => false, 'message' => 'No time slots found. Please configure Time Slots first.']);
        exit;
    }

    // Fetch Requirements
    $req_query = "SELECT requirement_id, subject_id, faculty_id, hours_per_week 
                  FROM subject_requirements 
                  WHERE section_id = ?";
    $stmt = $conn->prepare($req_query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($requirements)) {
        echo json_encode(['success' => false, 'message' => 'No subjects configured. Go to Subject Setup first.']);
        exit;
    }
    
    $total_config_hours = 0;
    foreach($requirements as $req) $total_config_hours += $req['hours_per_week'];

    $force_daily_mode = false;
    // If configured hours are close to 1 day's capacity (e.g., 7 hours for 7 slots), but we have 5 days...
    if ($total_config_hours <= ($slots_per_day + 2) && $total_config_hours >= ($slots_per_day - 2)) {
        $force_daily_mode = true; 
    }

    $has_ts_column = ($conn->query("SHOW COLUMNS FROM schedules LIKE 'time_slot_id'")->num_rows > 0);

    if ($has_ts_column) {
        $insert_sql = "INSERT INTO schedules (section_id, subject_id, faculty_id, time_slot_id, schedule_date, start_time, end_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    } else {
        $insert_sql = "INSERT INTO schedules (section_id, subject_id, faculty_id, schedule_date, start_time, end_time, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    }
    
    $conflict_sql = "SELECT schedule_id FROM schedules WHERE faculty_id = ? AND schedule_date = ? AND start_time = ?";

    $insert_stmt = $conn->prepare($insert_sql);
    $conflict_stmt = $conn->prepare($conflict_sql);

    // GENERATION LOOP
    $schedules_created = 0;
    $conflicts_found = 0;
    $current_date = $start_ts;
    
    // Helper to initialize weekly counters
    $init_counters = function() use ($requirements, $force_daily_mode) {
        $counts = [];
        foreach ($requirements as $req) {
            // If Heuristic detected "Daily" intent, give them 5 hours/week instead of 1
            $counts[$req['requirement_id']] = $force_daily_mode ? 5 : $req['hours_per_week'];
        }
        return $counts;
    };
    
    $weekly_remaining = $init_counters();

    while ($current_date <= $end_ts) {
        $day_num = date('N', $current_date); // 1=Mon, 7=Sun
        $date_db = date('Y-m-d', $current_date);

        // Reset counters every Monday
        if ($day_num == 1) {
            $weekly_remaining = $init_counters();
        }

        // Skip Weekends
        if ($day_num >= 6) {
            $current_date = strtotime('+1 day', $current_date);
            continue;
        }

        $today_subject_ids = [];

        foreach ($time_slots as $slot) {
            // Shuffle requirements for fair distribution
            shuffle($requirements);

            foreach ($requirements as $req) {
                $req_id = $req['requirement_id'];
                $sub_id = $req['subject_id'];
                $fac_id = $req['faculty_id'];

                // 1. Check Allowance (Do we have hours left for this subject?)
                if ($weekly_remaining[$req_id] <= 0) continue;

                // 2. Prevent same subject twice in one day
                // (Unless total hours > 5, implying double periods are needed)
                $allowance = $force_daily_mode ? 5 : $req['hours_per_week'];
                if (in_array($sub_id, $today_subject_ids) && $allowance <= 5) {
                     continue;
                }

                // 3. Check Teacher Conflict
                $conflict_stmt->bind_param("iss", $fac_id, $date_db, $slot['start_time']);
                $conflict_stmt->execute();
                if ($conflict_stmt->get_result()->num_rows > 0) {
                    $conflicts_found++;
                    continue;
                }

                // 4. Insert Schedule
                if ($has_ts_column) {
                    $insert_stmt->bind_param("iiiisss", $section_id, $sub_id, $fac_id, $slot['time_slot_id'], $date_db, $slot['start_time'], $slot['end_time']);
                } else {
                    $insert_stmt->bind_param("iiisss", $section_id, $sub_id, $fac_id, $date_db, $slot['start_time'], $slot['end_time']);
                }

                if ($insert_stmt->execute()) {
                    $schedules_created++;
                    $weekly_remaining[$req_id]--;
                    $today_subject_ids[] = $sub_id;
                    break; // Slot filled, move to next time slot
                }
            }
        }
        $current_date = strtotime('+1 day', $current_date);
    }

    // ---------------------------------------------------------
    // 6. RETURN RESPONSE
    // ---------------------------------------------------------
    $msg = "Generated $schedules_created schedules.";
    if ($force_daily_mode) {
        $msg .= " (Note: Automatically applied Daily Schedule mode because subjects were configured with low hours.)";
    }

    echo json_encode([
        'success' => true,
        'schedules_created' => $schedules_created,
        'conflicts_found' => $conflicts_found,
        'message' => $msg
    ]);

    $insert_stmt->close();
    $conflict_stmt->close();
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>