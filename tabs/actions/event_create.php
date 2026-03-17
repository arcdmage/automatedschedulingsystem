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

    if ($title === "" || $type === "" || $date === "" || $start === "" || $end === "") {
        throw new Exception("Missing required event fields.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO events (event_title, event_type, event_date, start_time, end_time, location, description)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
    );
    if (!$stmt) {
        throw new Exception("Database error (prepare): " . $conn->error);
    }

    $stmt->bind_param("sssssss", $title, $type, $date, $start, $end, $location, $description);
    if (!$stmt->execute()) {
        throw new Exception("Failed to create event: " . $stmt->error);
    }

    echo json_encode(["success" => true, "message" => "Event created"]);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}
?>
