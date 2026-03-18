<?php
header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method. Use POST.");
    }

    require_once __DIR__ . "/../../db_connect.php";

    $subject_name = isset($_POST["subject_name"])
        ? trim($_POST["subject_name"])
        : "";
    $special = $_POST["special"] ?? "";
    $grade_level = isset($_POST["grade_level"])
        ? trim($_POST["grade_level"])
        : "";
    $strand = isset($_POST["strand"]) ? trim($_POST["strand"]) : "";

    if (is_array($special)) {
        $special = implode(", ", array_filter(array_map("trim", $special)));
    } else {
        $special = trim((string) $special);
    }

    if ($subject_name === "" || $grade_level === "") {
        throw new Exception(
            "Missing required fields (subject_name, grade_level).",
        );
    }

    $stmt = $conn->prepare(
        "INSERT INTO subjects (subject_name, special, grade_level, strand) VALUES (?, ?, ?, ?)",
    );

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $subject_name, $special, $grade_level, $strand);

    if (!$stmt->execute()) {
        throw new Exception("Failed to create subject: " . $stmt->error);
    }

    echo json_encode([
        "success" => true,
        "message" => "Subject created successfully.",
        "subject_id" => (int) $conn->insert_id,
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
