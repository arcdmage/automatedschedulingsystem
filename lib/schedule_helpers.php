<?php
// Helper utilities used by Weekly View and auto-generator
// - load saved patterns for a section (returns array[dayName][slot_order] => ['subject_id'=>..., 'requirement_id'=>...])
// - apply patterns to a date range and merge with explicit schedules (explicit schedules override patterns)

function load_section_patterns(mysqli $conn, int $section_id): array {
    $sql = "
      SELECT sp.day_of_week, ts.slot_order, sr.requirement_id, sr.subject_id
      FROM schedule_patterns sp
      JOIN subject_requirements sr ON sp.requirement_id = sr.requirement_id
      JOIN time_slots ts ON sp.time_slot_id = ts.time_slot_id
      WHERE sr.section_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $patterns = [];
    while ($r = $res->fetch_assoc()) {
        $day = $r['day_of_week']; // stored as 'Monday' etc
        $slot_order = (int)$r['slot_order'];
        $patterns[$day][$slot_order] = [
            'requirement_id' => (int)$r['requirement_id'],
            'subject_id' => (int)$r['subject_id']
        ];
    }
    $stmt->close();
    return $patterns;
}

// Fetch explicit schedules in a date range for a section (returns array[date][slot_order] => ['subject_id'=>..., 'schedule_id'=>...])
function load_explicit_schedules(mysqli $conn, int $section_id, string $from_date, string $to_date): array {
    $sql = "
      SELECT schedule_id, schedule_date, time_slot_id, subject_id
      FROM schedules
      WHERE section_id = ? AND schedule_date BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $section_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();

    // Need slot_order for time_slot_id
    $patterns = [];
    $timeSlotStmt = $conn->prepare("SELECT slot_order FROM time_slots WHERE time_slot_id = ?");
    while ($r = $res->fetch_assoc()) {
        $slot_time_id = (int)$r['time_slot_id'];
        $timeSlotStmt->bind_param("i", $slot_time_id);
        $timeSlotStmt->execute();
        $sr = $timeSlotStmt->get_result()->fetch_assoc();
        $slot_order = $sr ? (int)$sr['slot_order'] : null;
        $date = $r['schedule_date'];
        if ($slot_order !== null) {
            $patterns[$date][$slot_order] = [
                'schedule_id' => (int)$r['schedule_id'],
                'subject_id' => (int)$r['subject_id'],
                'source' => 'explicit'
            ];
        }
    }
    $timeSlotStmt->close();
    $stmt->close();
    return $patterns;
}

// Build a week date list (Monday..Friday) from any date inside that week
function week_date_range_from(string $anyDate): array {
    $d = new DateTime($anyDate);
    // find Monday
    $d->setISODate((int)$d->format('o'), (int)$d->format('W'), 1);
    $dates = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $dates;
}

// Merge patterns (day->slot_order) into a concrete week (dates). explicit schedules override patterns.
function build_weekly_grid(mysqli $conn, int $section_id, string $anyDate): array {
    $weekDates = week_date_range_from($anyDate); // Monday..Friday
    $patterns = load_section_patterns($conn, $section_id);
    // map patterns to dates
    $grid = [];
    foreach ($weekDates as $date) {
        $dayName = (new DateTime($date))->format('l'); // Monday..Friday
        $grid[$date] = [];
        if (isset($patterns[$dayName])) {
            foreach ($patterns[$dayName] as $slot_order => $info) {
                $grid[$date][$slot_order] = [
                    'subject_id' => $info['subject_id'],
                    'requirement_id' => $info['requirement_id'],
                    'source' => 'pattern'
                ];
            }
        }
    }
    // overlay explicit schedules (override)
    $from = $weekDates[0];
    $to = end($weekDates);
    $explicit = load_explicit_schedules($conn, $section_id, $from, $to);
    foreach ($explicit as $date => $slots) {
        foreach ($slots as $slot_order => $sinfo) {
            $grid[$date][$slot_order] = $sinfo; // explicit overrides pattern
            $grid[$date][$slot_order]['source'] = 'explicit';
        }
    }
    return $grid; // date => slot_order => info
}
?>