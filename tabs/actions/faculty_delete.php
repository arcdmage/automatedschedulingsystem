<?php
// ===== faculty_delete.php =====
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $faculty_id = intval($_POST['faculty_id']);
    if (!$faculty_id) throw new Exception('Faculty ID is required');

    // Check if faculty is referenced in schedules
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM schedules WHERE faculty_id = ?");
    $check->bind_param("i", $faculty_id);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();

    if ($cnt > 0) {
        throw new Exception("Cannot delete: this faculty member is assigned to $cnt schedule(s). Remove those first.");
    }

    $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $faculty_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Faculty deleted successfully']);
    } else {
        throw new Exception('Failed to delete faculty');
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>