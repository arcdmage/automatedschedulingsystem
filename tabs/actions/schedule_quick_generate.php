<?php
ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/../../lib/subject_duration_helpers.php";
require_once __DIR__ . "/../../lib/scheduler_staff_helpers.php";

header("Content-Type: application/json");

function week_dates_for_current_week(): array
{
    $monday = new DateTime();
    $monday->setISODate((int) $monday->format("o"), (int) $monday->format("W"), 1);

    $dates = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = [
            "day" => $monday->format("l"),
            "date" => $monday->format("Y-m-d"),
        ];
        $monday->modify("+1 day");
    }
    return $dates;
}

function section_has_schedule(mysqli $conn, int $sectionId, string $scheduleDate, int $timeSlotId): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM schedules WHERE section_id = ? AND schedule_date = ? AND time_slot_id = ?"
    );
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param("isi", $sectionId, $scheduleDate, $timeSlotId);
    $stmt->execute();
    $total = (int) (($stmt->get_result()->fetch_assoc()["total"] ?? 0));
    $stmt->close();
    return $total > 0;
}



try {
    $section_id = intval($_POST["section_id"] ?? 0);
    if ($section_id <= 0) {
        throw new Exception("Missing section_id.");
    }

    $createdDefaultTimeSlots = false;

    $requirementsStmt = $conn->prepare(
        "SELECT requirement_id, subject_id, faculty_id, hours_per_week
         FROM subject_requirements
         WHERE section_id = ?
         ORDER BY requirement_id"
    );
    $requirementsStmt->bind_param("i", $section_id);
    $requirementsStmt->execute();
    $requirements = $requirementsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $requirementsStmt->close();

    if (empty($requirements)) {
        throw new Exception("No subjects configured for this section.");
    }

    $createdDefaultTimeSlots = ensure_section_time_slot_capacity($conn, $section_id, count($requirements));

    $timeslotStmt = $conn->prepare(
        "SELECT time_slot_id, start_time, end_time, slot_order
         FROM time_slots
         WHERE section_id = ? AND is_break = 0
         ORDER BY slot_order"
    );
    $timeslotStmt->bind_param("i", $section_id);
    $timeslotStmt->execute();
    $timeSlots = $timeslotStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $timeslotStmt->close();

    if (empty($timeSlots)) {
        throw new Exception("No non-break time slots found for this section.");
    }

    $subjectNames = [];
    $subjectResult = $conn->query("SELECT subject_id, subject_name FROM subjects");
    if ($subjectResult) {
        while ($row = $subjectResult->fetch_assoc()) {
            $subjectNames[(int) $row['subject_id']] = $row['subject_name'];
        }
    }

    $facultyDirectory = scheduler_faculty_directory($conn);
    $weekDates = week_dates_for_current_week();
    $startDate = $weekDates[0]['date'];
    $endDate = $weekDates[count($weekDates) - 1]['date'];

    $deleteStmt = $conn->prepare(
        "DELETE FROM schedules
         WHERE section_id = ?
           AND schedule_date BETWEEN ? AND ?
           AND (is_auto_generated = 1 OR notes LIKE 'Quick auto-generated%')"
    );
    if ($deleteStmt) {
        $deleteStmt->bind_param("iss", $section_id, $startDate, $endDate);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO schedules
        (section_id, faculty_id, subject_id, schedule_date, day_of_week, time_slot_id, start_time, end_time, notes, is_auto_generated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    if (!$insertStmt) {
        throw new Exception("Unable to prepare schedule insert.");
    }

    $created = 0;
    $conflicts = [];

    foreach ($requirements as $requirement) {
        $subjectId = (int) $requirement['subject_id'];
        $assignedFacultyId = (int) ($requirement['faculty_id'] ?? 0);
        $perSubjectMinutes = normalize_subject_duration_minutes((int) ($requirement['hours_per_week'] ?? 0));
        if ($perSubjectMinutes <= 0) {
            $perSubjectMinutes = 60;
        }

        $candidateFacultyIds = [];
        if ($assignedFacultyId > 0) {
            $candidateFacultyIds[] = $assignedFacultyId;
        }
        foreach (subject_specialist_ids($conn, $subjectId) as $facultyId) {
            if (!in_array($facultyId, $candidateFacultyIds, true)) {
                $candidateFacultyIds[] = $facultyId;
            }
        }

        if (empty($candidateFacultyIds)) {
            foreach ($weekDates as $weekDay) {
                $conflicts[] = [
                    'subject' => $subjectNames[$subjectId] ?? ('Subject #' . $subjectId),
                    'day' => $weekDay['day'],
                    'message' => 'No specialist found in Subject List.',
                ];
            }
            continue;
        }

        if ($assignedFacultyId <= 0) {
            shuffle($candidateFacultyIds);
        }

        $placed = false;
        $selectedFacultyId = 0;
        $plannedSlots = [];

        foreach ($candidateFacultyIds as $facultyId) {
            $candidatePlans = [];
            $candidateValid = true;

            foreach ($weekDates as $weekDay) {
                $dayName = $weekDay['day'];
                $scheduleDate = $weekDay['date'];
                $slotFound = null;

                foreach ($timeSlots as $slot) {
                    $slotDuration = minutes_between_times($slot['start_time'], $slot['end_time']);
                    if ($slotDuration < $perSubjectMinutes) {
                        continue;
                    }

                    if (section_has_schedule($conn, $section_id, $scheduleDate, (int) $slot['time_slot_id'])) {
                        continue;
                    }

                    if (!faculty_available_for_slot(
                        $conn,
                        (int) $facultyId,
                        $dayName,
                        $slot['start_time'],
                        $slot['end_time'],
                        $scheduleDate
                    )) {
                        continue;
                    }

                    $slotFound = [
                        'day' => $dayName,
                        'date' => $scheduleDate,
                        'time_slot_id' => (int) $slot['time_slot_id'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                    ];
                    break;
                }

                if ($slotFound === null) {
                    $candidateValid = false;
                    break;
                }

                $candidatePlans[] = $slotFound;
            }

            if (!$candidateValid) {
                continue;
            }

            $selectedFacultyId = (int) $facultyId;
            $plannedSlots = $candidatePlans;
            $placed = true;
            break;
        }

        if (!$placed) {
            foreach ($weekDates as $weekDay) {
                $conflicts[] = [
                    'subject' => $subjectNames[$subjectId] ?? ('Subject #' . $subjectId),
                    'day' => $weekDay['day'],
                    'message' => 'No single specialist could be assigned for the full week with available 1-hour slots.',
                ];
            }
            continue;
        }

        foreach ($plannedSlots as $plannedSlot) {
            $notes = 'Quick auto-generated';
            $insertStmt->bind_param(
                "iiississs",
                $section_id,
                $selectedFacultyId,
                $subjectId,
                $plannedSlot['date'],
                $plannedSlot['day'],
                $plannedSlot['time_slot_id'],
                $plannedSlot['start_time'],
                $plannedSlot['end_time'],
                $notes
            );
            if (!$insertStmt->execute()) {
                throw new Exception('Failed to save schedule: ' . $insertStmt->error);
            }
            $created++;
        }
    }

    $insertStmt->close();

    echo json_encode([
        'success' => true,
        'message' => $createdDefaultTimeSlots ? 'Quick generation completed. Default time slots were created automatically for this section.' : 'Quick generation completed.',
        'schedules_created' => $created,
        'conflicts_found' => count($conflicts),
        'default_time_slots_created' => $createdDefaultTimeSlots,
        'conflict_details' => $conflicts,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
