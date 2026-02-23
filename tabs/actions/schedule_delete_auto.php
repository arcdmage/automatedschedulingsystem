<?php
session_start();
require_once(__DIR__ . '/../../db_connect.php');
require_once(__DIR__ . '/action_helper.php');

header('Content-Type: application/json');

try {
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $force      = isset($_POST['force']) && $_POST['force'] === '1';

    if (!$section_id || !$start_date || !$end_date) {
        throw new Exception('Missing parameters: section_id, start_date and end_date are required.');
    }

    // Fetch schedules in range for this section
    // Identifies auto-generated rows by:
    //   1. is_auto_generated = 1 (if column exists), OR
    //   2. notes = 'Auto-generated' (set by schedule_auto_generate.php), OR
    //   3. force=1 (delete everything in range)
    $selSql = "SELECT schedule_id, schedule_date, time_slot_id, subject_id,
                      COALESCE(is_auto_generated, 0) AS is_auto_generated,
                      COALESCE(notes, '') AS notes
               FROM schedules
               WHERE section_id = ? AND schedule_date BETWEEN ? AND ?
               ORDER BY schedule_date, time_slot_id";
    $selStmt = $conn->prepare($selSql);
    if (!$selStmt) throw new Exception('Prepare failed (select): ' . $conn->error);
    $selStmt->bind_param("iss", $section_id, $start_date, $end_date);
    $selStmt->execute();
    $res = $selStmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $selStmt->close();

    $matched = count($rows);
    if ($matched === 0) {
        finish_action([
            'success'          => true,
            'message'          => 'No schedules found in the given date range for this section.',
            'matched_count'    => 0,
            'deleted_schedules'=> 0
        ]);
    }

    // Prepare pattern check fallback query (checks if schedule matches a saved pattern for the section's subject)
    $patternCheckSql = "
      SELECT 1
      FROM schedule_patterns sp
      JOIN subject_requirements sr ON sp.requirement_id = sr.requirement_id
      WHERE sr.section_id = ? AND sp.time_slot_id = ? AND sp.day_of_week = ? AND sr.subject_id = ?
      LIMIT 1
    ";
    $patternCheckStmt = $conn->prepare($patternCheckSql);
    if (!$patternCheckStmt) throw new Exception('Prepare failed (pattern check): ' . $conn->error);

    $toDeleteIds     = [];
    $auto_flagged_ids = [];

    foreach ($rows as $r) {
        $sid     = (int)$r['schedule_id'];
        $is_auto = (int)$r['is_auto_generated'];
        $notes   = trim($r['notes']);


        if ($is_auto === 1 || $notes === 'Auto-generated') {
            $toDeleteIds[]      = $sid;
            $auto_flagged_ids[] = $sid;
            continue;
        }

        if ($force) {
            $toDeleteIds[] = $sid;
            continue;
        }

        $time_slot_id = (int)$r['time_slot_id'];
        $subject_id   = (int)$r['subject_id'];
        $day_of_week  = (new DateTime($r['schedule_date']))->format('l');

        $patternCheckStmt->bind_param("iisi", $section_id, $time_slot_id, $day_of_week, $subject_id);
        $patternCheckStmt->execute();
        $pRes = $patternCheckStmt->get_result();
        if ($pRes && $pRes->num_rows > 0) {
            $toDeleteIds[] = $sid;
        }
    }

    $patternCheckStmt->close();

    if (empty($toDeleteIds)) {
        finish_action([
            'success'          => true,
            'message'          => 'Found schedules but none were recognized as auto-generated. Use force=1 to delete all matched rows.',
            'matched_count'    => $matched,
            'auto_count'       => count($auto_flagged_ids),
            'deleted_schedules'=> 0,
            'sample_rows'      => array_slice($rows, 0, 10)
        ]);
    }

    // Perform delete in a transaction
    $conn->begin_transaction();
    $ids_sql = implode(',', array_map('intval', $toDeleteIds));
    $delSql  = "DELETE FROM schedules WHERE schedule_id IN ($ids_sql)";
    if (!$conn->query($delSql)) {
        $conn->rollback();
        throw new Exception('Delete failed: ' . $conn->error);
    }
    $deleted = $conn->affected_rows;
    $conn->commit();

    finish_action([
        'success'          => true,
        'message'          => "Deleted {$deleted} schedule(s).",
        'matched_count'    => $matched,
        'deleted_schedules'=> $deleted,
        'auto_count'       => count($auto_flagged_ids)
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping() && $conn->in_transaction) $conn->rollback();
    finish_action(['success' => false, 'message' => $e->getMessage()]);
}
?>