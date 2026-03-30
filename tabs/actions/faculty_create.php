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

    if ($pnumber === "") {
        $pnumber = "00000000000";
    }

    if ($status === "") {
        $status = "Unknown";
    }

    if (!$fname || !$lname) {
        throw new Exception("First name and last name are required");
    }

    $duplicateStmt = $conn->prepare(
        "SELECT faculty_id
         FROM faculty
         WHERE LOWER(TRIM(fname)) = LOWER(TRIM(?))
           AND LOWER(TRIM(mname)) = LOWER(TRIM(?))
           AND LOWER(TRIM(lname)) = LOWER(TRIM(?))
         LIMIT 1",
    );
    if (!$duplicateStmt) {
        throw new Exception("Database error while checking duplicates");
    }
    $duplicateStmt->bind_param("sss", $fname, $mname, $lname);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    if ($duplicateResult && $duplicateResult->num_rows > 0) {
        throw new Exception(
            "A faculty member with the same full name already exists",
        );
    }
    $duplicateStmt->close();

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
