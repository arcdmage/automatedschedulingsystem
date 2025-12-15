<?php
// mainscheduler/tabs/actions/schedule_delete_auto.php
session_start();
require_once('../../db_connect.php');

// Ensure we output JSON even if an error occurs
header('Content-Type: application/json');

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception('Invalid request method. POST required.');
    }

    // 1. Debug: Log incoming parameters (Optional, remove in production if needed)
    // error_log(print_r($_POST, true)); 

    // 2. Validate Inputs
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

    if ($section_id <= 0) {
        throw new Exception('Invalid Section ID provided.');
    }

    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Start Date and End Date are required.');
    }

    // 3. Prepare Delete Query
    // We explicitly cast the dates to ensure MySQL handles them correctly
    $sql = "DELETE FROM schedules 
            WHERE section_id = ? 
            AND schedule_date >= CAST(? AS DATE) 
            AND schedule_date <= CAST(? AS DATE)";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("iss", $section_id, $start_date, $end_date);

    if ($stmt->execute()) {
        $deleted_count = $stmt->affected_rows;
        
        // Return success even if 0 rows were deleted (it just means it was already empty)
        echo json_encode([
            'success' => true, 
            'message' => "Operation successful. Deleted $deleted_count schedule entries.",
            'schedules_deleted' => $deleted_count
        ]);
    } else {
        throw new Exception('Database execution failed: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>