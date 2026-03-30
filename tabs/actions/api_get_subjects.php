<?php
require_once __DIR__ . "/../../db_connect.php";
header("Content-Type: application/json; charset=utf-8");

// Query subjects
$result = $conn->query(
    "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name",
);

if (!$result) {
    // Return a JSON error if the query failed
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database query error: " . $conn->error,
    ]);
    $conn->close();
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Return structured JSON so callers can reliably check a success flag
// and access the payload under `data`.
echo json_encode(
    [
        "success" => true,
        "data" => $data,
    ],
    JSON_UNESCAPED_UNICODE,
);

$conn->close();
exit();
?>
