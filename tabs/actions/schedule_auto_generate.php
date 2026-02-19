<?php
require_once(__DIR__ . '/../../db_connect.php');
require_once(__DIR__ . '/action_helper.php');
require_once(__DIR__ . '/../../lib/schedule_helpers.php');
header('Content-Type: application/json');

// Debug mode - set to true to see detailed conflict information
$debug_mode = true;
$debug_log = [];

function debug_log($message) {
    global $debug_log, $debug_mode;
    if ($debug_mode) {
        $debug_log[] = $message;
    }
}

try {
    $section_id = intval($_POST['section_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (!$section_id || !$start_date || !$end_date) {
        throw new Exception('Missing required fields');
    }
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($start > $end) {
        throw new Exception('Start date must be before end date');
    }
    
    // Fetch subject requirements with patterns and faculty
    $stmt = $conn->prepare("SELECT requirement_id, subject_id, hours_per_week, faculty_id FROM subject_requirements WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = [];
    while ($row = $res->fetch_assoc()) {
        $subjects[$row['requirement_id']] = [
            'requirement_id' => (int)$row['requirement_id'],
            'subject_id' => (int)$row['subject_id'],
            'remaining'  => (int)$row['hours_per_week'],
            'faculty_id' => $row['faculty_id'], // Keep as-is (could be string or int)
        ];
        debug_log("Loaded requirement {$row['requirement_id']}: subject={$row['subject_id']}, faculty='{$row['faculty_id']}'");
    }
    $stmt->close();
    
    // Fetch patterns for each requirement
    $patterns = [];
    foreach ($subjects as $req) {
        $req_id = $req['requirement_id'];
        
        $pattern_query = "SELECT sp.*, ts.start_time, ts.end_time, ts.is_break
                         FROM schedule_patterns sp
                         JOIN time_slots ts ON sp.time_slot_id = ts.time_slot_id
                         WHERE sp.requirement_id = ?
                         ORDER BY sp.day_of_week, ts.slot_order";
        $stmt = $conn->prepare($pattern_query);
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $patterns[$req_id] = [];
        while ($row = $result->fetch_assoc()) {
            $patterns[$req_id][] = $row;
        }
        $stmt->close();
    }
    
    // Check if all requirements have patterns
    $missing_patterns = [];
    foreach ($subjects as $req) {
        if (empty($patterns[$req['requirement_id']])) {
            $missing_patterns[] = $req['subject_name'];
        }
    }
    
    if (!empty($missing_patterns)) {
        throw new Exception('The following subjects are missing schedule patterns: ' . implode(', ', $missing_patterns) . '. Please configure patterns before generating.');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    $schedules_created = 0;
    $conflicts_found = 0;
    
    // Prepare insert statement
    $insert_stmt = $conn->prepare("
        INSERT INTO schedules (faculty_id, subject_id, section_id, schedule_date, start_time, end_time, time_slot_id, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Auto-generated')
    ");
    
    // Debug conflict check - shows what conflicts are found
    $debug_conflict_stmt = $conn->prepare("
        SELECT 
            s.schedule_id,
            s.faculty_id,
            s.subject_id,
            s.section_id,
            sec.section_name,
            subj.subject_name,
            ts.start_time,
            ts.end_time,
            CASE 
                WHEN s.section_id = ? AND s.time_slot_id = ? AND s.subject_id = ? THEN 'Same section/subject/time'
                WHEN s.faculty_id = ? AND s.faculty_id IS NOT NULL AND s.faculty_id != '' 
                     AND ts.start_time < ? AND ts.end_time > ? THEN 'Faculty time conflict'
                ELSE 'Unknown'
            END as conflict_type
        FROM schedules s
        JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
        JOIN sections sec ON s.section_id = sec.section_id
        JOIN subjects subj ON s.subject_id = subj.subject_id
        WHERE s.schedule_date = ?
          AND (
              (s.section_id = ? AND s.time_slot_id = ? AND s.subject_id = ?)
              OR 
              (s.faculty_id = ? AND s.faculty_id IS NOT NULL AND s.faculty_id != '' AND 
               ts.start_time < ? AND ts.end_time > ?)
          )
    ");
    
    // Simple conflict count check
    $conflict_stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedules s
        JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
        WHERE s.schedule_date = ?
          AND (
              (s.section_id = ? AND s.time_slot_id = ? AND s.subject_id = ?)
              OR 
              (s.faculty_id = ? AND s.faculty_id IS NOT NULL AND s.faculty_id != '' AND 
               ts.start_time < ? AND ts.end_time > ?)
          )
    ");
    
    // Generate schedules for each date in range
    $current = clone $start;
    
    while ($current <= $end) {
        $day_name = $current->format('l');
        
        // Skip weekends
        if ($day_name === 'Saturday' || $day_name === 'Sunday') {
            $current->modify('+1 day');
            continue;
        }
        
        $date_str = $current->format('Y-m-d');
        debug_log("Processing date: $date_str ($day_name)");
        
        // For each requirement, create schedules based on patterns
        foreach ($subjects as $req) {
            $req_id = $req['requirement_id'];
            
            if (empty($patterns[$req_id])) continue;
            
            foreach ($patterns[$req_id] as $pattern) {
                // Check if this pattern is for current day
                if ($pattern['day_of_week'] !== $day_name) continue;
                
                $time_slot_id = $pattern['time_slot_id'];
                $start_time = $pattern['start_time'];
                $end_time = $pattern['end_time'];
                $faculty_id = $req['faculty_id'];
                
                debug_log("  Attempting: subject={$req['subject_id']}, time=$start_time-$end_time, faculty='$faculty_id'");
                
                // Check for conflicts with debug info
                if ($debug_mode) {
                    $debug_conflict_stmt->bind_param(
                        "iiissssiiiiss",
                        $section_id, $time_slot_id, $req['subject_id'],
                        $faculty_id, $end_time, $start_time,
                        $date_str,
                        $section_id, $time_slot_id, $req['subject_id'],
                        $faculty_id, $end_time, $start_time
                    );
                    $debug_conflict_stmt->execute();
                    $debug_result = $debug_conflict_stmt->get_result();
                    
                    if ($debug_result->num_rows > 0) {
                        debug_log("    CONFLICTS FOUND:");
                        while ($conflict_row = $debug_result->fetch_assoc()) {
                            debug_log("      - {$conflict_row['section_name']}: {$conflict_row['subject_name']} "
                                    . "({$conflict_row['start_time']}-{$conflict_row['end_time']}) "
                                    . "faculty='{$conflict_row['faculty_id']}' "
                                    . "type={$conflict_row['conflict_type']}");
                        }
                        $conflicts_found++;
                        continue;
                    }
                }
                
                // Regular conflict check
                $conflict_stmt->bind_param(
                    "siiisss",
                    $date_str,
                    $section_id,
                    $time_slot_id,
                    $req['subject_id'],
                    $faculty_id,
                    $end_time,
                    $start_time
                );
                $conflict_stmt->execute();
                $conflict_result = $conflict_stmt->get_result()->fetch_assoc();
                
                if ($conflict_result['count'] > 0 && !$debug_mode) {
                    $conflicts_found++;
                    continue;
                }
                
                // Insert schedule
                $insert_stmt->bind_param(
                    "siisssi",
                    $faculty_id,
                    $req['subject_id'],
                    $section_id,
                    $date_str,
                    $start_time,
                    $end_time,
                    $time_slot_id
                );
                
                if ($insert_stmt->execute()) {
                    $schedules_created++;
                    debug_log("    ✓ Created schedule");
                } else {
                    debug_log("    ✗ Failed to insert: " . $insert_stmt->error);
                }
            }
        }
        
        $current->modify('+1 day');
    }
    
    $insert_stmt->close();
    $conflict_stmt->close();
    if ($debug_mode) {
        $debug_conflict_stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'schedules_created' => $schedules_created,
        'conflicts_found' => $conflicts_found,
        'message' => 'Schedule generation completed'
    ];
    
    if ($debug_mode) {
        $response['debug_log'] = $debug_log;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    if ($debug_mode && !empty($debug_log)) {
        $response['debug_log'] = $debug_log;
    }
    
    echo json_encode($response);
}

$conn->close();
?>