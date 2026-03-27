<?php
require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../lib/subject_duration_helpers.php";
require_once __DIR__ . "/../../lib/scheduler_staff_helpers.php";
header("Content-Type: application/json");

try {
    ensure_subject_requirements_auto_assign($conn);
    $requirement_id = intval($_POST["requirement_id"]);
    $faculty_id = intval($_POST["faculty_id"] ?? 0);
    $faculty_id = $faculty_id > 0 ? $faculty_id : null;
    $hours_per_week = read_subject_duration_minutes_from_request($_POST);

    if ($faculty_id !== null && faculty_on_leave_today($conn, $faculty_id)) {
        throw new Exception("Selected teacher is currently on leave and cannot be assigned.");
    }

    if (!$requirement_id || !$hours_per_week) {
        throw new Exception("Missing required fields");
    }

    if ($hours_per_week < 30 || $hours_per_week > 1200) {
        throw new Exception(
            "Hour per subject must be between 30 minutes and 20 hours",
        );
    }

    $stmt = $conn->prepare(
        "UPDATE subject_requirements SET faculty_id = ?, hours_per_week = ? WHERE requirement_id = ?",
    );
    $stmt->bind_param("iii", $faculty_id, $hours_per_week, $requirement_id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Subject updated successfully",
        ]);
    } else {
        throw new Exception("Failed to update subject");
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
    ]);
}

$conn->close();
?>
