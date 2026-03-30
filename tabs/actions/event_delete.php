<?php
header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method. Use POST.");
    }

    require_once __DIR__ . "/../../db_connect.php";

    $event_id = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;
    if ($event_id <= 0) {
        throw new Exception("Event ID is required.");
    }

    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    if (!$stmt) {
        throw new Exception("Database error (prepare): " . $conn->error);
    }

    $stmt->bind_param("i", $event_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete event: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Event not found.");
    }

    echo json_encode([
        "success" => true,
        "message" => "Event deleted successfully.",
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
    ]);
    exit();
}
?>
