<?php
// mainscheduler/tabs/actions/section_create.php
// API endpoint to create a new section

header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method. Use POST.");
    }

    require_once __DIR__ . "/../../db_connect.php";
    require_once __DIR__ . "/../../lib/scheduler_staff_helpers.php";

    // Read and sanitize inputs
    $section_name = isset($_POST["section_name"])
        ? trim($_POST["section_name"])
        : "";
    $grade_level_raw = isset($_POST["grade_level"])
        ? trim($_POST["grade_level"])
        : "";
    $track = isset($_POST["track"]) ? trim($_POST["track"]) : null;
    $adviser_id =
        isset($_POST["adviser_id"]) && $_POST["adviser_id"] !== ""
            ? intval($_POST["adviser_id"])
            : null;
    // co_adviser_id is accepted by the frontend but there is no co_adviser column in sections table.
    // We'll accept it here but not store it (frontend may ignore or handle separately).
    $co_adviser_id =
        isset($_POST["co_adviser_id"]) && $_POST["co_adviser_id"] !== ""
            ? intval($_POST["co_adviser_id"])
            : null;
    $school_year = isset($_POST["school_year"])
        ? trim($_POST["school_year"])
        : null;
    $semester = isset($_POST["semester"]) ? trim($_POST["semester"]) : null;

    // Basic validation
    if ($section_name === "") {
        throw new Exception("Section name is required.");
    }

    // Normalize grade level values: accept "11", "12", "Grade 11", "Grade 12"
    $grade_level = "";
    if ($grade_level_raw !== "") {
        if (preg_match('/^\\s*11\\s*$/', $grade_level_raw)) {
            $grade_level = "Grade 11";
        } elseif (preg_match('/^\\s*12\\s*$/', $grade_level_raw)) {
            $grade_level = "Grade 12";
        } elseif (preg_match("/Grade\\s*11/i", $grade_level_raw)) {
            $grade_level = "Grade 11";
        } elseif (preg_match("/Grade\\s*12/i", $grade_level_raw)) {
            $grade_level = "Grade 12";
        } else {
            // fallback: use provided value
            $grade_level = $grade_level_raw;
        }
    } else {
        throw new Exception("Grade level is required.");
    }

    // Provide sensible defaults for school_year and semester if not provided
    if (!$school_year) {
        // compute school year like "SY 2025-2026" using current date
        $nowYear = intval(date("Y"));
        $nowMonth = intval(date("n"));
        // If month is July-December, school year usually "SY Y-(Y+1)", else use "(Y-1)-Y"
        if ($nowMonth >= 7) {
            $school_year = "SY " . $nowYear . "-" . ($nowYear + 1);
        } else {
            $school_year = "SY " . ($nowYear - 1) . "-" . $nowYear;
        }
    }

    if (!$semester) {
        // Choose semester based on month: approximate rule
        $month = intval(date("n"));
        if (in_array($month, [6, 7, 8, 9, 10, 11, 12])) {
            $semester = "First Semester";
        } else {
            $semester = "Second Semester";
        }
    }

    // Prevent duplicate section (same name + grade_level)
    $checkStmt = $conn->prepare(
        "SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? LIMIT 1",
    );
    if (!$checkStmt) {
        throw new Exception("Database error (prepare): " . $conn->error);
    }
    $checkStmt->bind_param("ss", $section_name, $grade_level);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    if ($checkRes && $checkRes->num_rows > 0) {
        $existing = $checkRes->fetch_assoc();
        throw new Exception(
            "A section with that name and grade level already exists (id: " .
                $existing["section_id"] .
                ").",
        );
    }
    $checkStmt->close();

    if ($adviser_id !== null && faculty_on_leave_today($conn, $adviser_id)) {
        throw new Exception("Selected advisor is currently on leave and cannot be assigned.");
    }

    // Prepare insert statement. adviser_id may be null.
    $sql = "INSERT INTO sections (section_name, grade_level, track, school_year, semester, adviser_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error (prepare insert): " . $conn->error);
    }

    // Bind adviser_id as integer or null. mysqli will send null if PHP var is null.
    $stmt->bind_param(
        "sssssi",
        $section_name,
        $grade_level,
        $track,
        $school_year,
        $semester,
        $adviser_id,
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to create section: " . $stmt->error);
    }

    $new_section_id = $conn->insert_id;
    $stmt->close();
    $count_result = $conn->query("SELECT COUNT(*) AS total FROM sections");
    $total_records = $count_result
        ? (int) ($count_result->fetch_assoc()["total"] ?? 0)
        : 0;

    // Note: co-adviser was accepted but not stored (no column in sections table).
    // If you want to store it, add a column or separate mapping table and handle here.

    echo json_encode([
        "success" => true,
        "message" => "Section created successfully.",
        "section_id" => $new_section_id,
        "total_records" => $total_records,
    ]);
    exit();
} catch (Exception $ex) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $ex->getMessage(),
    ]);
    exit();
}
?>
