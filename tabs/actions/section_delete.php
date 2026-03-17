<?php
// mainscheduler/tabs/actions/section_delete.php
// API endpoint to delete a section (and rely on DB cascades for related data).
// Expects POST { section_id: int }
// Returns JSON { success: bool, message: string }

header('Content-Type: application/json; charset=utf-8');

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }

    // Ensure DB connection file is available and include it
    $dbPath = __DIR__ . '/../../db_connect.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database configuration not found.');
    }
    require_once $dbPath;

    // Read and validate input
    $sectionIdRaw = $_POST['section_id'] ?? $_POST['id'] ?? null;
    $section_id = $sectionIdRaw !== null ? intval($sectionIdRaw) : 0;
    if ($section_id <= 0) {
        throw new Exception('section_id is required and must be a positive integer.');
    }

    // Confirm the section exists
    $check = $conn->prepare('SELECT section_id, section_name FROM sections WHERE section_id = ? LIMIT 1');
    if (!$check) {
        throw new Exception('Database error (prepare): ' . $conn->error);
    }
    $check->bind_param('i', $section_id);
    if (!$check->execute()) {
        $check->close();
        throw new Exception('Database error (execute): ' . $check->error);
    }
    $res = $check->get_result();
    if (!$res || $res->num_rows === 0) {
        $check->close();
        throw new Exception('Section not found.');
    }
    $section = $res->fetch_assoc();
    $check->close();

    // Begin transaction for safe delete
    if (!$conn->begin_transaction()) {
        // If unable to start a transaction, continue but warn (we won't throw).
        // Some environments may not support transactions for the engine; proceed anyway.
    }

    // Perform delete - foreign keys in DB will cascade related deletes if configured
    $del = $conn->prepare('DELETE FROM sections WHERE section_id = ?');
    if (!$del) {
        $conn->rollback();
        throw new Exception('Database error (prepare delete): ' . $conn->error);
    }
    $del->bind_param('i', $section_id);
    if (!$del->execute()) {
        $del->close();
        $conn->rollback();
        throw new Exception('Failed to delete section: ' . $del->error);
    }

    $affected = $del->affected_rows;
    $del->close();

    if ($affected === 0) {
        $conn->rollback();
        throw new Exception('No section deleted. It may not exist anymore.');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Section deleted successfully.',
        'section_id' => $section_id,
        'section_name' => $section['section_name'] ?? null
    ]);
    exit;
} catch (Exception $e) {
    // Attempt rollback if possible
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
