<?php
require_once(__DIR__ . '/../../db_connect.php');

// Test conflict detection time for STEM MARS
$section_id = 3; // STEM MARS from your screenshot
$test_date = '2026-02-02';

// Array to store all 7 subjects
$subjects = [
    ['id' => 6, 'name' => 'DRRM', 'faculty' => '100017', 'time_slot' => 14],
    ['id' => 7, 'name' => 'Entrepreneurship', 'faculty' => '100017', 'time_slot' => 15],
    ['id' => 4, 'name' => 'General Physics 2', 'faculty' => '100020', 'time_slot' => 17],
    ['id' => 3, 'name' => 'General Physics 1', 'faculty' => '100010', 'time_slot' => 18],
    ['id' => 5, 'name' => 'GP1', 'faculty' => '100010', 'time_slot' => 20],
    ['id' => 2, 'name' => 'Physics 22', 'faculty' => '100020', 'time_slot' => 23],
    ['id' => 8, 'name' => 'test', 'faculty' => '100020', 'time_slot' => 24]
];

echo "Subject,Trial,Detection_Time_ms\n";

foreach ($subjects as $subject) {
    // Prepare the conflict check query
    $conflict_stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM schedules s
        JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
        WHERE s.schedule_date = ?
          AND (
              (s.section_id = ? AND s.time_slot_id = ? AND s.subject_id = ?)
              OR 
              (s.faculty_id = ? AND s.faculty_id IS NOT NULL AND s.faculty_id != '' 
               AND ts.start_time < '23:59:59' AND ts.end_time > '00:00:00')
          )
    ");
    
    // Run 50 trials per subject
    for ($trial = 1; $trial <= 50; $trial++) {
        $start = microtime(true);
        
        $conflict_stmt->bind_param(
            "siiis",
            $test_date,
            $section_id,
            $subject['time_slot'],
            $subject['id'],
            $subject['faculty']
        );
        $conflict_stmt->execute();
        $result = $conflict_stmt->get_result()->fetch_assoc();
        
        $end = microtime(true);
        $time_ms = ($end - $start) * 1000;
        
        echo "{$subject['name']},$trial," . round($time_ms, 4) . "\n";
    }
    
    $conflict_stmt->close();
}

$conn->close();
?>