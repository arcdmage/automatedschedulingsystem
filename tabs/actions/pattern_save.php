<?php
/**
 * IMPROVED pattern_save_api.php
 * Enhanced version with better error handling and debugging
 */

// Prevent any output before headers
ob_start();

require_once __DIR__ . "/../../db_connect.php";

// Set error reporting but don't display errors (they break JSON)
error_reporting(E_ALL);
ini_set("display_errors", 0);

// Set JSON header
header("Content-Type: application/json");

// Enable error logging to file
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/pattern_save_errors.log");

function format_time_label(?string $start, ?string $end): string
{
    $start_label = $start ?? "";
    $end_label = $end ?? "";
    if ($start) {
        $s =
            DateTime::createFromFormat("H:i:s", $start) ?:
            DateTime::createFromFormat("H:i", $start);
        if ($s) {
            $start_label = $s->format("g:i A");
        }
    }
    if ($end) {
        $e =
            DateTime::createFromFormat("H:i:s", $end) ?:
            DateTime::createFromFormat("H:i", $end);
        if ($e) {
            $end_label = $e->format("g:i A");
        }
    }
    if ($start_label && $end_label) {
        return $start_label . " - " . $end_label;
    }
    return $start_label ?: ($end_label ?: "Unknown time");
}

try {
    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception(
            "Database connection failed: " .
                ($conn->connect_error ?? "Connection object not found"),
        );
    }

    // Get raw input
    $raw_input = file_get_contents("php://input");

    if (empty($raw_input)) {
        throw new Exception("No input data received");
    }

    // Log the raw input for debugging
    error_log("Pattern Save - Raw Input: " . $raw_input);

    // Decode JSON
    $input = json_decode($raw_input, true);

    // Check for JSON errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }

    // Validate required fields
    if (!isset($input["requirement_id"])) {
        throw new Exception("Missing required field: requirement_id");
    }

    if (!isset($input["pattern"])) {
        throw new Exception("Missing required field: pattern");
    }

    $requirement_id = intval($input["requirement_id"]);
    $pattern = $input["pattern"];

    // Validate requirement_id
    if ($requirement_id <= 0) {
        throw new Exception(
            "Invalid requirement_id: must be a positive integer",
        );
    }

    // Validate pattern is array
    if (!is_array($pattern)) {
        throw new Exception("Pattern must be an array");
    }

    if (count($pattern) === 0) {
        throw new Exception(
            "Pattern cannot be empty - please select at least one time slot",
        );
    }

    // Log what we're processing
    error_log(
        "Pattern Save - Processing requirement_id: $requirement_id with " .
            count($pattern) .
            " slots",
    );

    // Get the section_id from the requirement
    $section_query =
        "SELECT section_id FROM subject_requirements WHERE requirement_id = ?";
    $stmt = $conn->prepare($section_query);

    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $requirement_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to fetch requirement: " . $stmt->error);
    }

    $section_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$section_result) {
        throw new Exception(
            "Requirement not found. The subject may have been deleted.",
        );
    }

    $section_id = $section_result["section_id"];
    error_log("Pattern Save - Section ID: $section_id");

    // Day name normalization map (accepts names or numeric days)
    $day_map = [
        "monday" => "Monday",
        "tuesday" => "Tuesday",
        "wednesday" => "Wednesday",
        "thursday" => "Thursday",
        "friday" => "Friday",
        "saturday" => "Saturday",
        "sunday" => "Sunday",
        "1" => "Monday", // ISO numeric (1 = Monday)
        "2" => "Tuesday",
        "3" => "Wednesday",
        "4" => "Thursday",
        "5" => "Friday",
        "6" => "Saturday",
        "7" => "Sunday",
        "0" => "Sunday", // some clients use 0=Sunday
    ];

    // Prepare time slot validation once (reuse)
    $validate_query =
        "SELECT section_id, is_break, start_time, end_time FROM time_slots WHERE time_slot_id = ?";
    $validate_stmt = $conn->prepare($validate_query);
    if (!$validate_stmt) {
        throw new Exception(
            "Database prepare failed for time slot validation: " . $conn->error,
        );
    }

    // Additional prepared statements for overlap checks:
    // - check existing schedule_patterns for same section/day/slot (other requirements)
    $check_pattern_sql = "
      SELECT sp.requirement_id,
             s.subject_name,
             sec.section_name,
             sec.grade_level,
             sec.track
      FROM schedule_patterns sp
      JOIN subject_requirements sr ON sp.requirement_id = sr.requirement_id
      JOIN subjects s ON sr.subject_id = s.subject_id
      JOIN sections sec ON sr.section_id = sec.section_id
      WHERE sr.section_id = ? AND sp.time_slot_id = ? AND sp.day_of_week = ? AND sp.requirement_id != ?
      LIMIT 1
    ";
    $check_pattern_stmt = $conn->prepare($check_pattern_sql);
    if (!$check_pattern_stmt) {
        throw new Exception(
            "Failed to prepare pattern-overlap check: " . $conn->error,
        );
    }

    // - check existing actual schedules for same section/day/slot (schedules uses time_slot_id directly)
    $check_schedule_sql = "
      SELECT sch.schedule_id,
             s.subject_name,
             sec.section_name,
             sec.grade_level,
             sec.track
      FROM schedules sch
      JOIN subjects s ON sch.subject_id = s.subject_id
      JOIN sections sec ON sch.section_id = sec.section_id
      WHERE sch.section_id = ? AND sch.time_slot_id = ? AND sch.day_of_week = ?
      LIMIT 1
    ";
    $check_schedule_stmt = $conn->prepare($check_schedule_sql);
    if (!$check_schedule_stmt) {
        throw new Exception(
            "Failed to prepare schedule-overlap check: " . $conn->error,
        );
    }

    // Validate that all time_slot_ids belong to this section and are not breaks
    foreach ($pattern as $index => $slot) {
        // Validate slot structure
        if (!isset($slot["time_slot_id"])) {
            throw new Exception(
                "Pattern slot #$index is missing 'time_slot_id'",
            );
        }

        if (!isset($slot["day_of_week"])) {
            throw new Exception(
                "Pattern slot #$index is missing 'day_of_week'",
            );
        }

        $time_slot_id = intval($slot["time_slot_id"]);
        if ($time_slot_id <= 0) {
            throw new Exception("Invalid time_slot_id at pattern slot #$index");
        }

        $day_raw = trim((string) $slot["day_of_week"]);
        $day_key = strtolower($day_raw);

        // Normalize numeric strings that may contain whitespace
        if (is_numeric($day_raw)) {
            $day_key = (string) intval($day_raw);
        }

        if (!isset($day_map[$day_key])) {
            throw new Exception(
                "Invalid day_of_week at pattern slot #$index: '$day_raw'",
            );
        }

        $day_of_week = $day_map[$day_key];

        // Validate time slot exists and belongs to section
        $validate_stmt->bind_param("i", $time_slot_id);

        if (!$validate_stmt->execute()) {
            throw new Exception(
                "Failed to validate time slot: " . $validate_stmt->error,
            );
        }

        $validate_result = $validate_stmt->get_result()->fetch_assoc();

        if (!$validate_result) {
            throw new Exception(
                "Time slot ID $time_slot_id does not exist. Please refresh the page.",
            );
        }

        if ($validate_result["section_id"] != $section_id) {
            throw new Exception(
                "Time slot ID $time_slot_id does not belong to this section. Please refresh the page and try again.",
            );
        }

        // Prevent scheduling during break times
        if ((int) $validate_result["is_break"] === 1) {
            throw new Exception(
                "Cannot schedule classes during break time (slot $time_slot_id)",
            );
        }

        $time_label = format_time_label(
            $validate_result["start_time"] ?? null,
            $validate_result["end_time"] ?? null,
        );

        // CHECK for pattern overlap with other saved patterns (same section/day/slot)
        $check_pattern_stmt->bind_param(
            "iisi",
            $section_id,
            $time_slot_id,
            $day_of_week,
            $requirement_id,
        );
        if (!$check_pattern_stmt->execute()) {
            throw new Exception(
                "Failed to check existing patterns: " .
                    $check_pattern_stmt->error,
            );
        }
        $existing_pattern = $check_pattern_stmt->get_result()->fetch_assoc();
        if ($existing_pattern) {
            $conf_subject =
                $existing_pattern["subject_name"] ?? "Another subject";
            $conf_section = trim(
                ($existing_pattern["grade_level"] ?? "") .
                    " - " .
                    ($existing_pattern["section_name"] ?? ""),
            );
            $conf_track = $existing_pattern["track"]
                ? " ({$existing_pattern["track"]})"
                : "";
            throw new Exception(
                "Conflict: {$conf_subject} in {$conf_section}{$conf_track} already has a pattern on {$day_of_week} at {$time_label}.",
            );
        }

        // CHECK for overlap with already created schedules (actual assignments)
        $check_schedule_stmt->bind_param(
            "iis",
            $section_id,
            $time_slot_id,
            $day_of_week,
        );
        if (!$check_schedule_stmt->execute()) {
            throw new Exception(
                "Failed to check existing schedules: " .
                    $check_schedule_stmt->error,
            );
        }
        $existing_schedule = $check_schedule_stmt->get_result()->fetch_assoc();
        if ($existing_schedule) {
            $conf_subject =
                $existing_schedule["subject_name"] ?? "Another subject";
            $conf_section = trim(
                ($existing_schedule["grade_level"] ?? "") .
                    " - " .
                    ($existing_schedule["section_name"] ?? ""),
            );
            $conf_track = $existing_schedule["track"]
                ? " ({$existing_schedule["track"]})"
                : "";
            throw new Exception(
                "Conflict: {$conf_subject} in {$conf_section}{$conf_track} is already scheduled on {$day_of_week} at {$time_label}.",
            );
        }
    }
    $validate_stmt->close();
    $check_pattern_stmt->close();
    $check_schedule_stmt->close();

    // Start transaction
    if (!$conn->begin_transaction()) {
        throw new Exception(
            "Failed to start database transaction: " . $conn->error,
        );
    }

    error_log("Pattern Save - Transaction started");

    try {
        // Delete existing patterns
        $delete_stmt = $conn->prepare(
            "DELETE FROM schedule_patterns WHERE requirement_id = ?",
        );

        if (!$delete_stmt) {
            throw new Exception(
                "Failed to prepare delete statement: " . $conn->error,
            );
        }

        $delete_stmt->bind_param("i", $requirement_id);

        if (!$delete_stmt->execute()) {
            throw new Exception(
                "Failed to delete existing patterns: " . $delete_stmt->error,
            );
        }

        $deleted_count = $delete_stmt->affected_rows;
        $delete_stmt->close();

        error_log("Pattern Save - Deleted $deleted_count existing patterns");

        // Insert new patterns
        $insert_stmt = $conn->prepare(
            "INSERT INTO schedule_patterns (requirement_id, day_of_week, time_slot_id) VALUES (?, ?, ?)",
        );

        if (!$insert_stmt) {
            throw new Exception(
                "Failed to prepare insert statement: " . $conn->error,
            );
        }

        $slots_saved = 0;
        foreach ($pattern as $slot) {
            $day_raw = trim((string) $slot["day_of_week"]);
            $day_key = strtolower($day_raw);
            if (is_numeric($day_raw)) {
                $day_key = (string) intval($day_raw);
            }
            $day = $day_map[$day_key];
            $time_slot_id = intval($slot["time_slot_id"]);

            $insert_stmt->bind_param(
                "isi",
                $requirement_id,
                $day,
                $time_slot_id,
            );

            if (!$insert_stmt->execute()) {
                throw new Exception(
                    "Failed to insert pattern for $day at slot $time_slot_id: " .
                        $insert_stmt->error,
                );
            }

            $slots_saved++;
        }

        $insert_stmt->close();

        error_log("Pattern Save - Inserted $slots_saved new patterns");

        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception(
                "Failed to commit transaction: " . $conn->error,
            );
        }

        error_log("Pattern Save - Transaction committed successfully");

        // Clear output buffer and send success response
        ob_end_clean();

        echo json_encode([
            "success" => true,
            "message" => "Pattern saved successfully",
            "slots_saved" => $slots_saved,
            "slots_deleted" => $deleted_count,
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
            error_log("Pattern Save - Transaction rolled back");
        }
        throw $e;
    }
} catch (Exception $e) {
    // Log the error
    error_log("Pattern Save Error: " . $e->getMessage());
    error_log("Pattern Save Error Trace: " . $e->getTraceAsString());

    // Rollback if needed
    if (isset($conn) && $conn->ping()) {
        try {
            $conn->rollback();
        } catch (Exception $rollback_error) {
            error_log("Rollback failed: " . $rollback_error->getMessage());
        }
    }

    // Clear output buffer
    ob_end_clean();

    // Send error response
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "debug_info" => [
            "file" => basename($e->getFile()),
            "line" => $e->getLine(),
        ],
    ]);
}

// Close connection if it exists
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
