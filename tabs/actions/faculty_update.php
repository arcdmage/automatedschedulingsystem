<?php
require_once __DIR__ . "/../../db_connect.php";
header("Content-Type: application/json");

try {
    $faculty_id = intval($_POST["faculty_id"]);
    if (!$faculty_id) {
        throw new Exception("Faculty ID is required");
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
        throw new Exception("First and last name are required");
    }

    $duplicateStmt = $conn->prepare(
        "SELECT faculty_id
         FROM faculty
         WHERE LOWER(TRIM(fname)) = LOWER(TRIM(?))
           AND LOWER(TRIM(mname)) = LOWER(TRIM(?))
           AND LOWER(TRIM(lname)) = LOWER(TRIM(?))
           AND faculty_id <> ?
         LIMIT 1",
    );
    if (!$duplicateStmt) {
        throw new Exception("Database error while checking duplicates");
    }
    $duplicateStmt->bind_param("sssi", $fname, $mname, $lname, $faculty_id);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    if ($duplicateResult && $duplicateResult->num_rows > 0) {
        throw new Exception(
            "A faculty member with the same full name already exists",
        );
    }
    $duplicateStmt->close();

    $stmt = $conn->prepare(
        "UPDATE faculty SET fname=?, mname=?, lname=?, gender=?, pnumber=?, address=?, status=?
         WHERE faculty_id=?",
    );
    $stmt->bind_param(
        "sssssssi",
        $fname,
        $mname,
        $lname,
        $gender,
        $pnumber,
        $address,
        $status,
        $faculty_id,
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Faculty updated successfully",
        ]);
    } else {
        throw new Exception("Failed to update faculty");
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
$conn->close();
?>
