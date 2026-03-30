<?php
// mainscheduler/tabs/actions/subject_update.php
// Endpoint to update or delete a subject
// Accepts POST:
// - For update: subject_id (or id), subject_name, special, grade_level, strand
// - For delete: subject_id (or id) and action=delete
//
// Returns JSON { success: bool, message: string, [subject_id: int] }

header("Content-Type: application/json");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method. Use POST.");
    }

    require_once __DIR__ . "/../../db_connect.php";

    // Accept either subject_id or id for compatibility with different UI fragments
    $subjectIdRaw = $_POST["subject_id"] ?? ($_POST["id"] ?? null);
    $subject_id = $subjectIdRaw !== null ? intval($subjectIdRaw) : 0;

    if ($subject_id <= 0) {
        throw new Exception("Subject ID is required.");
    }

    $action = isset($_POST["action"]) ? trim($_POST["action"]) : "";

    if ($action === "delete") {
        // Delete subject
        // IMPORTANT: Ensure referential integrity is handled by DB (FK constraints).
        // If there are dependent rows (e.g. subject_requirements), DB may block deletion.
        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
        if (!$stmt) {
            throw new Exception("Database error (prepare): " . $conn->error);
        }
        $stmt->bind_param("i", $subject_id);
        if (!$stmt->execute()) {
            // If deletion fails due to FK constraint or other DB issue, show message
            throw new Exception("Failed to delete subject: " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            echo json_encode([
                "success" => false,
                "message" => "No subject found with that ID.",
            ]);
            exit();
        }

        echo json_encode([
            "success" => true,
            "message" => "Subject deleted successfully.",
        ]);
        exit();
    }

    // Otherwise, update subject fields
    $subject_name = isset($_POST["subject_name"])
        ? trim($_POST["subject_name"])
        : "";
    $special = isset($_POST["special"]) ? $_POST["special"] : null;
    $grade_level = isset($_POST["grade_level"])
        ? trim($_POST["grade_level"])
        : null;
    if (is_array($special)) {
        $special = implode(", ", array_filter(array_map("trim", $special)));
    } elseif ($special !== null) {
        $special = trim($special);
    }

    if ($subject_name === "") {
        throw new Exception("Subject name is required.");
    }

    // Prepare update statement. Only update columns that exist in the table schema.
    // We'll attempt to update the common columns: subject_name, special, grade_level, strand
    $sql = "UPDATE subjects
            SET subject_name = ?, special = ?, grade_level = ?
            WHERE subject_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error (prepare): " . $conn->error);
    }

    // Bind parameters. Use null for optional fields if empty string provided.
    $special_bind = $special !== "" ? $special : null;
    $grade_bind = $grade_level !== "" ? $grade_level : null;
    // Bind as strings; allow null values
    $stmt->bind_param(
        "sssi",
        $subject_name,
        $special_bind,
        $grade_bind,
        $subject_id,
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to update subject: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        // No rows changed — could mean no change or row not found
        // Check if row exists
        $check = $conn->prepare(
            "SELECT 1 FROM subjects WHERE subject_id = ? LIMIT 1",
        );
        if ($check) {
            $check->bind_param("i", $subject_id);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->num_rows === 0) {
                echo json_encode([
                    "success" => false,
                    "message" => "Subject not found.",
                ]);
                exit();
            }
        }
        // Otherwise, it's fine — no change
        echo json_encode([
            "success" => true,
            "message" => "No changes were made to the subject.",
        ]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => "Subject updated successfully.",
        "subject_id" => $subject_id,
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}
