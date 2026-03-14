<?php
require_once(__DIR__ . '/../../db_connect.php');
header('Content-Type: application/json');

try {
    $faculty_id = intval($_POST['faculty_id']);
    if (!$faculty_id) throw new Exception('Faculty ID is required');

    $fname   = trim($_POST['fname']   ?? '');
    $mname   = trim($_POST['mname']   ?? '');
    $lname   = trim($_POST['lname']   ?? '');
    $gender  = trim($_POST['gender']  ?? '');
    $pnumber = trim($_POST['pnumber'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status  = trim($_POST['status']  ?? '');

    if (!$fname || !$lname) throw new Exception('First and last name are required');

    $stmt = $conn->prepare(
        "UPDATE faculty SET fname=?, mname=?, lname=?, gender=?, pnumber=?, address=?, status=?
         WHERE faculty_id=?"
    );
    $stmt->bind_param("sssssssi", $fname, $mname, $lname, $gender, $pnumber, $address, $status, $faculty_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Faculty updated successfully']);
    } else {
        throw new Exception('Failed to update faculty');
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>