<?php
// ===== faculty_create.php =====
session_start();
require_once __DIR__ . "/../../db_connect.php";
header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    $fname = trim($_POST["fname"] ?? "");
    $mname = trim($_POST["mname"] ?? "");
    $lname = trim($_POST["lname"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $pnumber = trim($_POST["pnumber"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $status = trim($_POST["status"] ?? "");

    if (!$fname || !$lname) {
        throw new Exception("First name and last name are required");
    }

    $stmt = $conn->prepare(
        "INSERT INTO faculty (fname, mname, lname, gender, pnumber, address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
    );
    $stmt->bind_param(
        "sssssss",
        $fname,
        $mname,
        $lname,
        $gender,
        $pnumber,
        $address,
        $status,
    );

    if ($stmt->execute()) {
        $countResult = $conn->query("SELECT COUNT(*) AS total FROM faculty");
        $totalRecords = $countResult
            ? (int) ($countResult->fetch_assoc()["total"] ?? 0)
            : 0;

        echo json_encode([
            "success" => true,
            "message" => "Faculty added successfully",
            "faculty_id" => $conn->insert_id,
            "total_records" => $totalRecords,
        ]);
    } else {
        throw new Exception("Failed to add faculty: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
