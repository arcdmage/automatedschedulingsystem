<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../lib/scheduler_staff_helpers.php';
header('Content-Type: application/json');

function matching_schedule_rows(
    mysqli $conn,
    int $facultyId,
    int $scheduleId,
    int $subjectId,
    int $sectionId,
    string $requestDate,
    string $leaveUntilDate,
    string $leaveScope
): array {
    if ($leaveScope === 'whole_section') {
        $stmt = $conn->prepare(
            "SELECT schedule_id, subject_id, section_id, schedule_date, day_of_week, start_time, end_time
             FROM schedules
             WHERE faculty_id = ?
               AND section_id = ?
               AND schedule_date BETWEEN ? AND ?
             ORDER BY schedule_date ASC, start_time ASC"
        );
        if (!$stmt) {
            throw new Exception('Unable to load section schedules.');
        }
        $stmt->bind_param('iiss', $facultyId, $sectionId, $requestDate, $leaveUntilDate);
    } elseif ($leaveScope === 'subject_section') {
        $stmt = $conn->prepare(
            "SELECT schedule_id, subject_id, section_id, schedule_date, day_of_week, start_time, end_time
             FROM schedules
             WHERE faculty_id = ?
               AND subject_id = ?
               AND section_id = ?
               AND schedule_date BETWEEN ? AND ?
             ORDER BY schedule_date ASC, start_time ASC"
        );
        if (!$stmt) {
            throw new Exception('Unable to load matching schedules.');
        }
        $stmt->bind_param('iiiss', $facultyId, $subjectId, $sectionId, $requestDate, $leaveUntilDate);
    } else {
        $stmt = $conn->prepare(
            "SELECT schedule_id, subject_id, section_id, schedule_date, day_of_week, start_time, end_time
             FROM schedules
             WHERE schedule_id = ? AND faculty_id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            throw new Exception('Unable to load selected schedule.');
        }
        $stmt->bind_param('ii', $scheduleId, $facultyId);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function relieve_request_exists(
    mysqli $conn,
    int $facultyId,
    int $subjectId,
    int $sectionId,
    string $requestDate,
    string $startTime,
    string $endTime
): bool {
    $stmt = $conn->prepare(
        "SELECT relieve_id
         FROM relieve_requests
         WHERE faculty_id = ?
           AND subject_id = ?
           AND section_id = ?
           AND request_date = ?
           AND start_time = ?
           AND end_time = ?
           AND status NOT IN ('Cancelled', 'Completed')
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiisss', $facultyId, $subjectId, $sectionId, $requestDate, $startTime, $endTime);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

try {
    ensure_relieve_tables($conn);

    $faculty_id = intval($_POST['faculty_id'] ?? 0);
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $replacement_faculty_id = intval($_POST['replacement_faculty_id'] ?? 0);
    $request_date = trim((string) ($_POST['request_date'] ?? ''));
    $day_of_week = trim((string) ($_POST['day_of_week'] ?? ''));
    $leave_until_date = trim((string) ($_POST['leave_until_date'] ?? ''));
    $start_time = trim((string) ($_POST['start_time'] ?? ''));
    $end_time = trim((string) ($_POST['end_time'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $leave_scope = trim((string) ($_POST['leave_scope'] ?? 'single'));
    if (!in_array($leave_scope, ['single', 'subject_section', 'whole_section'], true)) {
        $leave_scope = 'single';
    }

    if ($faculty_id <= 0 || $request_date === '' || $start_time === '' || $end_time === '') {
        throw new Exception('Missing required relieve data.');
    }

    if ($leave_until_date === '') {
        $leave_until_date = $request_date;
    }

    if (strtotime($leave_until_date) === false || strtotime($request_date) === false) {
        throw new Exception('Invalid leave date.');
    }

    if ($leave_until_date < $request_date) {
        throw new Exception('Leave until date cannot be earlier than the start date.');
    }

    if ($replacement_faculty_id > 0 && faculty_on_leave_today($conn, $replacement_faculty_id, $request_date)) {
        throw new Exception('Selected replacement faculty is currently on leave and cannot be assigned.');
    }

    $scheduleRows = matching_schedule_rows(
        $conn,
        $faculty_id,
        $schedule_id,
        $subject_id,
        $section_id,
        $request_date,
        $leave_until_date,
        $leave_scope
    );

    if (empty($scheduleRows)) {
        throw new Exception('No matching schedules found for the selected leave scope.');
    }

    $rowsToInsert = [];
    foreach ($scheduleRows as $row) {
        $rowDate = trim((string) ($row['schedule_date'] ?? ''));
        $rowStart = trim((string) ($row['start_time'] ?? ''));
        $rowEnd = trim((string) ($row['end_time'] ?? ''));
        if ($rowDate === '' || $rowStart === '' || $rowEnd === '') {
            continue;
        }
        if (relieve_request_exists(
            $conn,
            $faculty_id,
            (int) ($row['subject_id'] ?? $subject_id),
            (int) ($row['section_id'] ?? $section_id),
            $rowDate,
            $rowStart,
            $rowEnd
        )) {
            continue;
        }
        if ($replacement_faculty_id > 0) {
            if (faculty_on_leave_today($conn, $replacement_faculty_id, $rowDate)) {
                throw new Exception('Selected replacement faculty is on leave during one or more matching schedules.');
            }
            if (!faculty_available_for_slot(
                $conn,
                $replacement_faculty_id,
                (string) ($row['day_of_week'] ?? $day_of_week),
                $rowStart,
                $rowEnd,
                $rowDate
            )) {
                throw new Exception('Selected replacement faculty is not available for one or more matching schedules.');
            }
        }
        $rowsToInsert[] = $row;
    }

    if (empty($rowsToInsert)) {
        throw new Exception('Matching leave requests already exist for this scope.');
    }

    $conn->begin_transaction();

    $insertStmt = $conn->prepare(
        "INSERT INTO relieve_requests
        (faculty_id, subject_id, section_id, request_date, leave_until_date, day_of_week, start_time, end_time, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        throw new Exception('Unable to create relieve request.');
    }

    $assignmentStmt = null;
    if ($replacement_faculty_id > 0) {
        $assignmentStmt = $conn->prepare(
            "INSERT INTO relieve_assignments (relieve_id, original_schedule_id, replacement_faculty_id, notes)
             VALUES (?, ?, ?, ?)"
        );
        if (!$assignmentStmt) {
            throw new Exception('Unable to save relieve assignment.');
        }
    }

    $status = $replacement_faculty_id > 0 ? 'Assigned' : 'Pending';
    $notes = $leave_scope === 'whole_section'
        ? 'Created from faculty schedule view (whole-section scope)'
        : ($leave_scope === 'subject_section'
            ? 'Created from faculty schedule view (subject-section scope)'
            : 'Created from faculty schedule view');
    $createdCount = 0;

    foreach ($rowsToInsert as $row) {
        $rowSubjectId = (int) ($row['subject_id'] ?? $subject_id);
        $rowSectionId = (int) ($row['section_id'] ?? $section_id);
        $rowScheduleId = (int) ($row['schedule_id'] ?? 0);
        $rowDate = (string) ($row['schedule_date'] ?? $request_date);
        $rowDay = (string) ($row['day_of_week'] ?? $day_of_week);
        $rowStart = (string) ($row['start_time'] ?? $start_time);
        $rowEnd = (string) ($row['end_time'] ?? $end_time);

        $insertStmt->bind_param(
            'iiisssssss',
            $faculty_id,
            $rowSubjectId,
            $rowSectionId,
            $rowDate,
            $leave_until_date,
            $rowDay,
            $rowStart,
            $rowEnd,
            $reason,
            $status
        );
        if (!$insertStmt->execute()) {
            throw new Exception('Failed to save relieve request: ' . $insertStmt->error);
        }
        $relieve_id = (int) $conn->insert_id;

        if ($assignmentStmt && $replacement_faculty_id > 0) {
            $assignmentStmt->bind_param('iiis', $relieve_id, $rowScheduleId, $replacement_faculty_id, $notes);
            if (!$assignmentStmt->execute()) {
                throw new Exception('Failed to save relieve assignment: ' . $assignmentStmt->error);
            }
        }

        $createdCount++;
    }

    $insertStmt->close();
    if ($assignmentStmt) {
        $assignmentStmt->close();
    }
    $conn->commit();

    $message = $createdCount === 1
        ? ($replacement_faculty_id > 0 ? 'Relieve request and replacement saved.' : 'Relieve request saved.')
        : ($replacement_faculty_id > 0
            ? ($leave_scope === 'whole_section'
                ? "{$createdCount} relieve requests and replacements saved for the whole section."
                : "{$createdCount} relieve requests and replacements saved for this subject in the section.")
            : ($leave_scope === 'whole_section'
                ? "{$createdCount} relieve requests saved for the whole section."
                : "{$createdCount} relieve requests saved for this subject in the section."));

    echo json_encode([
        'success' => true,
        'message' => $message,
        'created_requests' => $createdCount,
        'leave_scope' => $leave_scope,
    ]);
} catch (Exception $e) {
    if ($conn->errno === 0) {
        $conn->rollback();
    } else {
        @ $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
