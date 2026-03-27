<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../lib/scheduler_staff_helpers.php';
header('Content-Type: application/json');

try {
    ensure_relieve_tables($conn);

    $relieve_id = intval($_POST['relieve_id'] ?? 0);
    $faculty_id = intval($_POST['faculty_id'] ?? 0);

    if ($relieve_id <= 0 || $faculty_id <= 0) {
        throw new Exception('Missing relieve request details.');
    }

    $checkStmt = $conn->prepare(
        "SELECT relieve_id FROM relieve_requests WHERE relieve_id = ? AND faculty_id = ? LIMIT 1"
    );
    if (!$checkStmt) {
        throw new Exception('Unable to verify relieve request.');
    }
    $checkStmt->bind_param('ii', $relieve_id, $faculty_id);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$exists) {
        throw new Exception('Relieve request not found.');
    }

    $deleteAssignmentStmt = $conn->prepare("DELETE FROM relieve_assignments WHERE relieve_id = ?");
    if (!$deleteAssignmentStmt) {
        throw new Exception('Unable to remove relieve assignment.');
    }
    $deleteAssignmentStmt->bind_param('i', $relieve_id);
    $deleteAssignmentStmt->execute();
    $deleteAssignmentStmt->close();

    $deleteRequestStmt = $conn->prepare("DELETE FROM relieve_requests WHERE relieve_id = ? AND faculty_id = ?");
    if (!$deleteRequestStmt) {
        throw new Exception('Unable to delete relieve request.');
    }
    $deleteRequestStmt->bind_param('ii', $relieve_id, $faculty_id);
    if (!$deleteRequestStmt->execute()) {
        throw new Exception('Failed to delete relieve request: ' . $deleteRequestStmt->error);
    }
    if ($deleteRequestStmt->affected_rows < 1) {
        $deleteRequestStmt->close();
        throw new Exception('Relieve request not found.');
    }
    $deleteRequestStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Relieve request deleted.',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
