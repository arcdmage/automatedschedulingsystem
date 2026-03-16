<?php
/**
 * schedule_delete_auto.php
 *
 * Deletes auto-generated schedule rows for a section.
 *
 * POST params:
 *   section_id  (required)
 *   start_date  (optional) - if provided, restricts deletion to date range
 *   end_date    (optional) - if provided, restricts deletion to date range
 *   force       (optional, '1') - delete ALL rows in range, not just auto-generated
 */

ini_set('display_errors', '0');
ini_set('html_errors',    '0');
ob_start();

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false,
            'message' => 'Fatal: ' . $err['message'] . ' (line ' . $err['line'] . ')']);
    } else {
        ob_end_flush();
    }
});

session_start();
require_once(__DIR__ . '/../../db_connect.php');
ob_clean();
if (!headers_sent()) header('Content-Type: application/json');

try {
    $section_id = intval($_POST['section_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date']   ?? '');
    $force      = isset($_POST['force']) && $_POST['force'] === '1';

    if (!$section_id) {
        throw new Exception('Missing required parameter: section_id');
    }

    // ── Check which columns exist ─────────────────────────────────────────────
    $cr = $conn->query("SHOW COLUMNS FROM schedules");
    $cols = [];
    while ($c = $cr->fetch_assoc()) $cols[] = $c['Field'];
    $has_auto    = in_array('is_auto_generated', $cols);
    $has_day_col = in_array('day_of_week',        $cols);

    // ── Build SELECT to find candidate rows ───────────────────────────────────
    // Auto-generated rows are identified by ANY of:
    //   • is_auto_generated = 1  (if column exists)
    //   • notes = 'Auto-generated'
    //   • notes LIKE 'Auto-generated|%'  (old pipe format)
    // If force=1, we take everything in the section (optionally within date range).

    if ($force) {
        // Delete everything for this section (optionally date-bounded)
        if ($start_date && $end_date) {
            $selSql = "SELECT schedule_id FROM schedules
                       WHERE section_id = ?
                         AND schedule_date BETWEEN ? AND ?";
            $selStmt = $conn->prepare($selSql);
            if (!$selStmt) throw new Exception('Prepare failed: ' . $conn->error);
            $selStmt->bind_param('iss', $section_id, $start_date, $end_date);
        } else {
            $selSql = "SELECT schedule_id FROM schedules WHERE section_id = ?";
            $selStmt = $conn->prepare($selSql);
            if (!$selStmt) throw new Exception('Prepare failed: ' . $conn->error);
            $selStmt->bind_param('i', $section_id);
        }
    } else {
        // Delete only auto-generated rows
        $auto_condition = $has_auto
            ? "(is_auto_generated = 1 OR notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')"
            : "(notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')";

        if ($start_date && $end_date) {
            $selSql = "SELECT schedule_id FROM schedules
                       WHERE section_id = ?
                         AND schedule_date BETWEEN ? AND ?
                         AND $auto_condition";
            $selStmt = $conn->prepare($selSql);
            if (!$selStmt) throw new Exception('Prepare failed: ' . $conn->error);
            $selStmt->bind_param('iss', $section_id, $start_date, $end_date);
        } else {
            // No date range — find all auto rows for this section
            $selSql = "SELECT schedule_id FROM schedules
                       WHERE section_id = ? AND $auto_condition";
            $selStmt = $conn->prepare($selSql);
            if (!$selStmt) throw new Exception('Prepare failed: ' . $conn->error);
            $selStmt->bind_param('i', $section_id);
        }
    }

    $selStmt->execute();
    $res = $selStmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['schedule_id'];
    $selStmt->close();

    if (empty($ids)) {
        echo json_encode([
            'success'           => true,
            'message'           => 'No auto-generated schedules found for this section.',
            'deleted_schedules' => 0,
            'matched_count'     => 0,
        ]);
        exit;
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    $conn->begin_transaction();

    $id_list = implode(',', $ids);
    $delSql  = "DELETE FROM schedules WHERE schedule_id IN ($id_list)";
    if (!$conn->query($delSql)) {
        $conn->rollback();
        throw new Exception('Delete failed: ' . $conn->error);
    }
    $deleted = $conn->affected_rows;
    $conn->commit();

    ob_clean();
    echo json_encode([
        'success'           => true,
        'message'           => "Deleted $deleted auto-generated schedule(s).",
        'deleted_schedules' => $deleted,
        'matched_count'     => count($ids),
    ]);

} catch (Exception $e) {
    try { if (isset($conn) && $conn) $conn->rollback(); } catch (Exception $re) {}
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn) && $conn) $conn->close();