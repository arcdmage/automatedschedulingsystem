<?php
header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method. Use POST.");
    }

    require_once __DIR__ . "/../../db_connect.php";

    $title = trim($_POST["event_title"] ?? "");
    $type = trim($_POST["event_type"] ?? "");
    $date = trim($_POST["event_date"] ?? "");
    $start = trim($_POST["event_start_time"] ?? "");
    $end = trim($_POST["event_end_time"] ?? "");
    $location = trim($_POST["event_location"] ?? "");
    $description = trim($_POST["event_description"] ?? "");
    $event_id = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;

    if (
        $title === "" ||
        $type === "" ||
        $date === "" ||
        $start === "" ||
        $end === ""
    ) {
        throw new Exception("Missing required event fields.");
    }

    if ($event_id > 0) {
        $stmt = $conn->prepare(
            "UPDATE events
             SET event_title = ?, event_type = ?, event_date = ?, start_time = ?, end_time = ?, location = ?, description = ?
             WHERE event_id = ?",
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO events (event_title, event_type, event_date, start_time, end_time, location, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
        );
    }
    if (!$stmt) {
        throw new Exception("Database error (prepare): " . $conn->error);
    }

    if ($event_id > 0) {
        $stmt->bind_param(
            "sssssssi",
            $title,
            $type,
            $date,
            $start,
            $end,
            $location,
            $description,
            $event_id,
        );
    } else {
        $stmt->bind_param(
            "sssssss",
            $title,
            $type,
            $date,
            $start,
            $end,
            $location,
            $description,
        );
    }
    if (!$stmt->execute()) {
        throw new Exception(
            ($event_id > 0
                ? "Failed to update event: "
                : "Failed to create event: ") . $stmt->error,
        );
    }

    if ($event_id > 0 && $stmt->affected_rows === 0) {
        throw new Exception("Event not found or no changes were made.");
    }

    echo json_encode([
        "success" => true,
        "message" => $event_id > 0 ? "Event updated" : "Event created",
        "event_id" => $event_id > 0 ? $event_id : (int) $conn->insert_id,
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}
?>
