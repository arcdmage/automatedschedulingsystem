<?php
// mainscheduler/tabs/actions/section_update.php
// Endpoint to update or delete a section
// POST params:
//  - action=delete -> expects section_id
//  - otherwise update -> expects section_id, section_name, grade_level
//    optional: track, adviser_id (empty string -> NULL), school_year, semester
//
// Returns JSON: { success: bool, message: string, [section_id:int] }

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }

    // load DB connection
    $dbPath = __DIR__ . '/../../db_connect.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database connection file missing.');
    }
    require_once $dbPath;
    require_once __DIR__ . '/../../lib/scheduler_staff_helpers.php';

    // Basic input extraction
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'update';

    // Accept either 'section_id' or 'id' (compatibility)
    $sectionIdRaw = $_POST['section_id'] ?? $_POST['id'] ?? null;
    $section_id = $sectionIdRaw !== null ? intval($sectionIdRaw) : 0;

    if ($section_id <= 0) {
        throw new Exception('section_id is required and must be a positive integer.');
    }

    if ($action === 'delete') {
        // Delete the section. Wrap in transaction to capture errors and roll back if needed.
        if (!$conn->begin_transaction()) {
            // proceed but warn if cannot begin transaction
        }

        $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
        if (!$stmt) {
            if ($conn->errno) throw new Exception('Database prepare error: ' . $conn->error);
            throw new Exception('Failed to prepare delete statement.');
        }
        $stmt->bind_param('i', $section_id);

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            throw new Exception('Failed to delete section: ' . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->commit();

        if ($affected === 0) {
            echo json_encode(['success' => false, 'message' => 'No section found with that ID.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Section deleted successfully.']);
        exit;
    }

    // UPDATE path
    // Required fields
    $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
    $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';

    if ($section_name === '') {
        throw new Exception('section_name is required.');
    }
    if ($grade_level === '') {
        throw new Exception('grade_level is required.');
    }

    // Optional fields
    $track = isset($_POST['track']) ? trim($_POST['track']) : null;
    $school_year = isset($_POST['school_year']) ? trim($_POST['school_year']) : null;
    $semester = isset($_POST['semester']) ? trim($_POST['semester']) : null;

    // adviser_id may be empty string => treat as NULL
    $adviser_id = null;
    if (isset($_POST['adviser_id']) && $_POST['adviser_id'] !== '') {
        $adviser_id = intval($_POST['adviser_id']);
        if ($adviser_id <= 0) $adviser_id = null;
    }

    if ($adviser_id !== null && faculty_on_leave_today($conn, $adviser_id)) {
        throw new Exception('Selected advisor is currently on leave and cannot be assigned.');
    }

    // If adviser_id provided, verify it exists
    if ($adviser_id !== null) {
        $chk = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_id = ? LIMIT 1");
        if (!$chk) throw new Exception('Database error (prepare): ' . $conn->error);
        $chk->bind_param('i', $adviser_id);
        if (!$chk->execute()) {
            $chk->close();
            throw new Exception('Database error (execute): ' . $chk->error);
        }
        $res = $chk->get_result();
        if (!$res || $res->num_rows === 0) {
            $chk->close();
            throw new Exception('Advisor not found.');
        }
        $chk->close();
    }

    // Prepare update statement
    // We'll update the common columns and set updated_at = NOW()
    $sql = "UPDATE sections
            SET section_name = ?, grade_level = ?, track = ?, school_year = ?, semester = ?, adviser_id = ?, updated_at = NOW()
            WHERE section_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Database error (prepare): ' . $conn->error);

    // When binding, adviser_id may be null. mysqli_bind_param accepts null variables.
    // Bind types: s, s, s, s, s, i, i  -> 'sssssii'
    $track_bind = $track !== null ? $track : null;
    $school_year_bind = $school_year !== null ? $school_year : null;
    $semester_bind = $semester !== null ? $semester : null;
    // Note: binding null values with 's' or 'i' is supported; mysqli will send SQL NULL.
    $stmt->bind_param(
        'sssssii',
        $section_name,
        $grade_level,
        $track_bind,
        $school_year_bind,
        $semester_bind,
        $adviser_id,
        $section_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update section: ' . $stmt->error);
    }

    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows === 0) {
        // Could be no change or missing row; check existence
        $check = $conn->prepare("SELECT 1 FROM sections WHERE section_id = ? LIMIT 1");
        if ($check) {
            $check->bind_param('i', $section_id);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Section not found.']);
                exit;
            }
            $check->close();
        }
        // No changes but successful
        echo json_encode(['success' => true, 'message' => 'No changes were made to the section.', 'section_id' => $section_id]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Section updated successfully.', 'section_id' => $section_id]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    $msg = $e->getMessage();
    // Do not leak DB credentials/errors in production; this echoes messages for developer convenience.
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
?>
