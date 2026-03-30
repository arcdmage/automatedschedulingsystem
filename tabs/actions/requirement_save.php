<?php
ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $err['message'] . ' (line ' . $err['line'] . ')',
        ]);
    } else {
        ob_end_flush();
    }
});

session_start();
require_once "../../db_connect.php";
require_once __DIR__ . "/../../lib/subject_duration_helpers.php";
require_once __DIR__ . "/../../lib/scheduler_staff_helpers.php";

ob_clean();
header("Content-Type: application/json");

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if ($errno & (E_WARNING | E_ERROR | E_PARSE)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => "PHP Error [$errno]: $errstr at line $errline",
        ]);
        exit();
    }
    return false;
});

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ensure_subject_requirements_auto_assign($conn);

    $section_id = intval($_POST["section_id"] ?? 0);
    $subject_id = intval($_POST["subject_id"] ?? 0);
    $faculty_id = intval($_POST["faculty_id"] ?? 0);
    $faculty_id = $faculty_id > 0 ? $faculty_id : null;
    $hours_per_week = read_subject_duration_minutes_from_request($_POST);
    $preferred_days = isset($_POST["preferred_days"]) ? trim($_POST["preferred_days"]) : null;
    $notes = isset($_POST["notes"]) ? trim($_POST["notes"]) : null;
    $auto_create_time_slots = !empty($_POST["auto_create_time_slots"]);

    if ($section_id <= 0 || $subject_id <= 0 || $hours_per_week <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid input parameters",
        ]);
        exit();
    }

    if ($hours_per_week < 30 || $hours_per_week > 1200) {
        echo json_encode([
            "success" => false,
            "message" => "Hour per subject must be between 30 minutes and 20 hours",
        ]);
        exit();
    }

    $check_section = $conn->prepare("SELECT section_id FROM sections WHERE section_id = ?");
    $check_section->bind_param("i", $section_id);
    $check_section->execute();
    if ($check_section->get_result()->num_rows == 0) {
        echo json_encode(["success" => false, "message" => "Section not found"]);
        exit();
    }
    $check_section->close();

    $check_subject = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
    $check_subject->bind_param("i", $subject_id);
    $check_subject->execute();
    if ($check_subject->get_result()->num_rows == 0) {
        echo json_encode(["success" => false, "message" => "Subject not found"]);
        exit();
    }
    $check_subject->close();

    if ($faculty_id !== null && $faculty_id > 0) {
        $check_faculty = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_id = ?");
        $check_faculty->bind_param("i", $faculty_id);
        $check_faculty->execute();
        if ($check_faculty->get_result()->num_rows == 0) {
            echo json_encode(["success" => false, "message" => "Faculty member not found"]);
            exit();
        }
        $check_faculty->close();

        if (faculty_on_leave_today($conn, $faculty_id)) {
            echo json_encode(["success" => false, "message" => "Selected teacher is currently on leave and cannot be assigned."]);
            exit();
        }
    }

    if ($faculty_id !== null) {
        $duplicate_check = $conn->prepare("SELECT requirement_id FROM subject_requirements WHERE section_id = ? AND subject_id = ? AND faculty_id = ?");
        $duplicate_check->bind_param("iii", $section_id, $subject_id, $faculty_id);
    } else {
        $duplicate_check = $conn->prepare("SELECT requirement_id FROM subject_requirements WHERE section_id = ? AND subject_id = ? AND faculty_id IS NULL");
        $duplicate_check->bind_param("ii", $section_id, $subject_id);
    }
    $duplicate_check->execute();
    if ($duplicate_check->get_result()->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => $faculty_id !== null
                ? "This subject requirement already exists for this section and teacher"
                : "This subject requirement already exists for this section",
        ]);
        exit();
    }
    $duplicate_check->close();

    $sql = "INSERT INTO subject_requirements (section_id, subject_id, faculty_id, hours_per_week, preferred_days, notes, created_at)
          VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiss", $section_id, $subject_id, $faculty_id, $hours_per_week, $preferred_days, $notes);

    if ($stmt->execute()) {
        $defaultTimeSlotsCreated = false;
        if ($auto_create_time_slots) {
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM subject_requirements WHERE section_id = ?");
            if ($countStmt) {
                $countStmt->bind_param("i", $section_id);
                $countStmt->execute();
                $requiredSubjects = (int) (($countStmt->get_result()->fetch_assoc()["total"] ?? 0));
                $countStmt->close();
                $defaultTimeSlotsCreated = ensure_section_time_slot_capacity($conn, $section_id, $requiredSubjects);
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Subject requirement added successfully",
            "requirement_id" => $conn->insert_id,
            "default_time_slots_created" => $defaultTimeSlotsCreated,
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error,
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method",
    ]);
}
?>

