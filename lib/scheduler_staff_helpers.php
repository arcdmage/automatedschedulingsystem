<?php

function ensure_relieve_tables(mysqli $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS relieve_requests (
            relieve_id INT(11) NOT NULL AUTO_INCREMENT,
            faculty_id INT(11) NOT NULL,
            subject_id INT(11) DEFAULT NULL,
            section_id INT(11) DEFAULT NULL,
            request_date DATE NOT NULL,
            leave_until_date DATE DEFAULT NULL,
            day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            status ENUM('Pending','Assigned','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (relieve_id),
            KEY idx_relieve_faculty (faculty_id),
            KEY idx_relieve_date (request_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $columnStmt = $conn->prepare("SHOW COLUMNS FROM relieve_requests LIKE 'leave_until_date'");
    if ($columnStmt) {
        $columnStmt->execute();
        $columnExists = $columnStmt->get_result()->num_rows > 0;
        $columnStmt->close();
        if (!$columnExists) {
            $conn->query("ALTER TABLE relieve_requests ADD COLUMN leave_until_date DATE DEFAULT NULL AFTER request_date");
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS relieve_assignments (
            assignment_id INT(11) NOT NULL AUTO_INCREMENT,
            relieve_id INT(11) NOT NULL,
            original_schedule_id INT(11) DEFAULT NULL,
            replacement_faculty_id INT(11) NOT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (assignment_id),
            UNIQUE KEY uniq_relieve_assignment (relieve_id),
            KEY idx_relieve_replacement (replacement_faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $initialized = true;
}

function faculty_has_active_relieve(
    mysqli $conn,
    int $facultyId,
    string $requestDate,
    string $startTime,
    string $endTime,
    ?int $ignoreRelieveId = null
): bool {
    if ($facultyId <= 0 || $requestDate === '' || $startTime === '' || $endTime === '') {
        return false;
    }

    $sql = "SELECT COUNT(*) AS total
            FROM relieve_requests
            WHERE faculty_id = ?
              AND status NOT IN ('Cancelled', 'Completed')
              AND request_date <= ?
              AND COALESCE(leave_until_date, request_date) >= ?
              AND NOT (end_time <= ? OR start_time >= ?)";

    if ($ignoreRelieveId !== null && $ignoreRelieveId > 0) {
        $sql .= " AND relieve_id <> ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($ignoreRelieveId !== null && $ignoreRelieveId > 0) {
        $stmt->bind_param('issssi', $facultyId, $requestDate, $requestDate, $startTime, $endTime, $ignoreRelieveId);
    } else {
        $stmt->bind_param('issss', $facultyId, $requestDate, $requestDate, $startTime, $endTime);
    }
    $stmt->execute();
    $total = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
    $stmt->close();

    return $total > 0;
}

function faculty_on_leave_today(mysqli $conn, int $facultyId, ?string $referenceDate = null): bool
{
    if ($facultyId <= 0) {
        return false;
    }

    $referenceDate = $referenceDate ?: date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM relieve_requests
         WHERE faculty_id = ?
           AND status NOT IN ('Cancelled', 'Completed')
           AND request_date <= ?
           AND COALESCE(leave_until_date, request_date) >= ?"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iss', $facultyId, $referenceDate, $referenceDate);
    $stmt->execute();
    $total = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
    $stmt->close();

    return $total > 0;
}

function available_faculty_rows(mysqli $conn, ?string $referenceDate = null): array
{
    ensure_relieve_tables($conn);

    $referenceDate = $referenceDate ?: date('Y-m-d');
    $rows = [];
    $result = $conn->query("SELECT faculty_id, fname, lname FROM faculty ORDER BY lname, fname");
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $facultyId = (int) ($row['faculty_id'] ?? 0);
        if ($facultyId <= 0) {
            continue;
        }
        if (faculty_on_leave_today($conn, $facultyId, $referenceDate)) {
            continue;
        }
        $rows[] = $row;
    }

    return $rows;
}

function scheduler_faculty_directory(mysqli $conn): array
{
    $map = [];
    $result = $conn->query(
        "SELECT faculty_id, fname, mname, lname FROM faculty ORDER BY lname, fname"
    );
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $facultyId = (int) $row['faculty_id'];
        $map[$facultyId] = [
            'faculty_id' => $facultyId,
            'lname' => trim((string) ($row['lname'] ?? '')),
            'full_name' => trim(
                trim((string) ($row['lname'] ?? '')) . ', ' . trim((string) ($row['fname'] ?? ''))
            ),
        ];
    }

    return $map;
}

function subject_specialist_ids(mysqli $conn, int $subjectId): array
{
    $stmt = $conn->prepare("SELECT special FROM subjects WHERE subject_id = ? LIMIT 1");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $subjectId);
    $stmt->execute();
    $special = trim((string) (($stmt->get_result()->fetch_assoc()['special'] ?? '')));
    $stmt->close();

    if ($special === '') {
        return [];
    }

    $tokens = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $special))));
    if (empty($tokens)) {
        return [];
    }

    $directory = scheduler_faculty_directory($conn);
    $matches = [];
    foreach ($tokens as $token) {
        $needle = strtolower($token);
        foreach ($directory as $facultyId => $faculty) {
            $lastName = strtolower($faculty['lname'] ?? '');
            $fullName = strtolower($faculty['full_name'] ?? '');
            if ($needle === $lastName || $needle === $fullName) {
                $matches[] = (int) $facultyId;
            }
        }
    }

    return array_values(array_unique($matches));
}

function faculty_available_for_slot(
    mysqli $conn,
    int $facultyId,
    string $dayOfWeek,
    string $startTime,
    string $endTime,
    ?string $scheduleDate = null
): bool {
    if ($facultyId <= 0) {
        return false;
    }

    $availabilityStmt = $conn->prepare(
        "SELECT COUNT(*) AS matches
         FROM faculty_availability
         WHERE faculty_id = ?
           AND day_of_week = ?
           AND is_available = 1
           AND start_time <= ?
           AND end_time >= ?"
    );
    $availabilityRows = 0;
    $matchingAvailability = 0;
    if ($availabilityStmt) {
        $availabilityStmt->bind_param('isss', $facultyId, $dayOfWeek, $startTime, $endTime);
        $availabilityStmt->execute();
        $matchingAvailability = (int) (($availabilityStmt->get_result()->fetch_assoc()['matches'] ?? 0));
        $availabilityStmt->close();

        $countStmt = $conn->prepare("SELECT COUNT(*) AS total_rows FROM faculty_availability WHERE faculty_id = ?");
        if ($countStmt) {
            $countStmt->bind_param('i', $facultyId);
            $countStmt->execute();
            $availabilityRows = (int) (($countStmt->get_result()->fetch_assoc()['total_rows'] ?? 0));
            $countStmt->close();
        }
    }

    if ($availabilityRows > 0 && $matchingAvailability === 0) {
        return false;
    }

    if ($scheduleDate !== null) {
        $conflictStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM schedules s
             JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
             WHERE s.faculty_id = ?
               AND s.schedule_date = ?
               AND NOT (ts.end_time <= ? OR ts.start_time >= ?)"
        );
        if ($conflictStmt) {
            $conflictStmt->bind_param('isss', $facultyId, $scheduleDate, $startTime, $endTime);
            $conflictStmt->execute();
            $conflicts = (int) (($conflictStmt->get_result()->fetch_assoc()['total'] ?? 0));
            $conflictStmt->close();
            if ($conflicts > 0) {
                return false;
            }
        }
    }

    return true;
}

function relieve_candidate_rows(
    mysqli $conn,
    int $subjectId,
    int $excludeFacultyId,
    string $requestDate,
    string $dayOfWeek,
    string $startTime,
    string $endTime
): array {
    $directory = scheduler_faculty_directory($conn);
    $rows = [];

    foreach (subject_specialist_ids($conn, $subjectId) as $facultyId) {
        if ($facultyId === $excludeFacultyId) {
            continue;
        }

        if (faculty_on_leave_today($conn, $facultyId, $requestDate)) {
            continue;
        }

        if (!faculty_available_for_slot($conn, $facultyId, $dayOfWeek, $startTime, $endTime, $requestDate)) {
            continue;
        }

        $rows[] = [
            'faculty_id' => $facultyId,
            'full_name' => $directory[$facultyId]['full_name'] ?? ('Faculty #' . $facultyId),
        ];
    }

    return $rows;
}


function ensure_subject_requirements_auto_assign(mysqli $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $conn->query(
        "ALTER TABLE subject_requirements MODIFY faculty_id INT(11) NULL DEFAULT NULL"
    );

    $initialized = true;
}



function add_minutes_to_time_string(string $timeValue, int $minutes): string
{
    $dt = DateTime::createFromFormat('H:i:s', $timeValue) ?: DateTime::createFromFormat('H:i', $timeValue);
    if (!$dt) {
        $dt = new DateTime('07:30:00');
    }
    $dt->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes');
    return $dt->format('H:i:s');
}

function ensure_section_time_slot_capacity(mysqli $conn, int $sectionId, int $requiredSubjects): bool
{
    if ($requiredSubjects <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT time_slot_id, start_time, end_time, is_break, slot_order
         FROM time_slots
         WHERE section_id = ?
         ORDER BY slot_order"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $existingSlots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $existingClassCount = 0;
    $lastEndTime = '07:30:00';
    $nextOrder = 1;
    if (!empty($existingSlots)) {
        foreach ($existingSlots as $slot) {
            if (!(int) ($slot['is_break'] ?? 0)) {
                $existingClassCount++;
            }
            $lastEndTime = $slot['end_time'] ?? $lastEndTime;
            $nextOrder = max($nextOrder, ((int) ($slot['slot_order'] ?? 0)) + 1);
        }
    }

    if ($existingClassCount >= $requiredSubjects) {
        return false;
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO time_slots (start_time, end_time, is_break, break_label, slot_order, section_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        return false;
    }

    $createdAny = false;
    $currentStart = $lastEndTime;
    for ($classNumber = $existingClassCount + 1; $classNumber <= $requiredSubjects; $classNumber++) {
        if ($classNumber > 1 && (($classNumber - 1) % 2 === 0)) {
            $breakStart = $currentStart;
            $breakEnd = add_minutes_to_time_string($breakStart, 15);
            $isBreak = 1;
            $breakLabel = 'BREAK';
            $insertStmt->bind_param('ssisii', $breakStart, $breakEnd, $isBreak, $breakLabel, $nextOrder, $sectionId);
            if (!$insertStmt->execute()) {
                $insertStmt->close();
                return $createdAny;
            }
            $createdAny = true;
            $nextOrder++;
            $currentStart = $breakEnd;
        }

        $classStart = $currentStart;
        $classEnd = add_minutes_to_time_string($classStart, 60);
        $isBreak = 0;
        $breakLabel = '';
        $insertStmt->bind_param('ssisii', $classStart, $classEnd, $isBreak, $breakLabel, $nextOrder, $sectionId);
        if (!$insertStmt->execute()) {
            $insertStmt->close();
            return $createdAny;
        }
        $createdAny = true;
        $nextOrder++;
        $currentStart = $classEnd;
    }

    $insertStmt->close();
    return $createdAny;
}

function ensure_default_section_time_slots(mysqli $conn, int $sectionId): bool
{
    return ensure_section_time_slot_capacity($conn, $sectionId, 7);
}

