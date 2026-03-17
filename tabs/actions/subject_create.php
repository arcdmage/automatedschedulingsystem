<?php
session_start();
require_once "../../db_connect.php";

// Detect AJAX / fetch JSON expectation
$isAjax =
    (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
        strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") ||
    (isset($_SERVER["HTTP_ACCEPT"]) &&
        strpos($_SERVER["HTTP_ACCEPT"], "application/json") !== false);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject_name = $_POST["subject_name"] ?? "";
    $special = $_POST["special"] ?? "";
    $grade_level = $_POST["grade_level"] ?? "";
    $strand = $_POST["strand"] ?? "";

    // Validate required fields
    if (empty($subject_name) || empty($grade_level) || empty($strand)) {
        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode([
                "success" => false,
                "message" =>
                    "Missing required fields (subject_name, grade_level, strand)",
            ]);
            exit();
        } else {
            die(
                "Error: Missing required fields (subject_name, grade_level, strand)"
            );
        }
    }

    // Use prepared statement
    $stmt = $conn->prepare(
        "INSERT INTO subjects (subject_name, special, grade_level, strand) VALUES (?, ?, ?, ?)",
    );

    if (!$stmt) {
        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode([
                "success" => false,
                "message" => "Prepare failed: " . $conn->error,
            ]);
            exit();
        } else {
            die("Prepare failed: " . $conn->error);
        }
    }

    $stmt->bind_param("ssss", $subject_name, $special, $grade_level, $strand);

    if ($stmt->execute()) {
        // Get inserted id
        $newId = $conn->insert_id;
        $stmt->close();
        $conn->close();

        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode([
                "success" => true,
                "message" => "Subject created",
                "subject_id" => (int) $newId,
            ]);
            exit();
        } else {
            header("Location: /mainscheduler/index.php?tab=subject_list");
            exit();
        }
    } else {
        $error = htmlspecialchars($stmt->error);
        $stmt->close();
        $conn->close();

        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode([
                "success" => false,
                "message" => "Error: " . $error,
            ]);
            exit();
        } else {
            echo "Error: " . $error;
        }
    }
}
?>
