<?php
/**
 * Common helpers for action scripts:
 * - detect AJAX
 * - determine safe return URL
 * - respond JSON or redirect
 */

require_once __DIR__ . "/../../lib/app_path.php";

function is_ajax_request(): bool
{
    return !empty($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
        strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest";
}

function get_return_url(): string
{
    // prefer explicit return_url from POST/GET
    $candidate =
        $_POST["return_url"] ??
        ($_GET["return_url"] ??
            ($_SERVER["HTTP_REFERER"] ?? app_url("tabs/schedule_view.php")));
    // basic sanitize: allow only internal paths (no protocol/host)
    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
        // convert to path-only if host matches or fallback to home
        $u = parse_url($candidate);
        $path = $u["path"] ?? "/";
        $query = isset($u["query"]) ? "?" . $u["query"] : "";
        $candidate = $path . $query;
    }
    // ensure it starts with a slash or relative path
    if (strpos($candidate, "/") !== 0) {
        $candidate = "/" . ltrim($candidate, "/");
    }
    return $candidate;
}

function respond_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit();
}

function finish_action(array $data): void
{
    if (is_ajax_request()) {
        respond_json($data, $data["success"] ? 200 : 400);
    } else {
        // non-AJAX: redirect back to return_url (POST-Redirect-GET)
        $return = htmlspecialchars(get_return_url(), ENT_QUOTES, "UTF-8");
        header("Location: " . $return);
        exit();
    }
}

function load_name_map(
    mysqli $conn,
    string $sql,
    string $key_column,
    string $value_column,
): array {
    $map = [];
    $result = $conn->query($sql);
    if (!$result) {
        return $map;
    }
    while ($row = $result->fetch_assoc()) {
        $map[(int) $row[$key_column]] = $row[$value_column];
    }
    return $map;
}

function format_entity_label(array $map, $id, string $label_prefix): string
{
    if (!$id) {
        return "$label_prefix unknown";
    }

    $id = (int) $id;
    return $map[$id] ?? "$label_prefix #$id";
}

function prepare_entity_labels(mysqli $conn): array
{
    return [
        "faculties" => load_name_map(
            $conn,
            "SELECT faculty_id, CONCAT(lname, ', ', fname) AS label FROM faculty",
            "faculty_id",
            "label",
        ),
        "subjects" => load_name_map(
            $conn,
            "SELECT subject_id, subject_name AS label FROM subjects",
            "subject_id",
            "label",
        ),
        "sections" => load_name_map(
            $conn,
            "SELECT section_id, CONCAT(section_name, ' (', grade_level, ')') AS label FROM sections",
            "section_id",
            "label",
        ),
    ];
}

function build_entity_signature(array $labels, array $entry): string
{
    $teacher = format_entity_label(
        $labels["faculties"],
        $entry["p_faculty_id"],
        "Faculty",
    );
    $subject = format_entity_label(
        $labels["subjects"],
        $entry["subject_id"],
        "Subject",
    );
    $section = format_entity_label(
        $labels["sections"],
        $entry["p_section_id"],
        "Section",
    );
    return "$subject_$teacher_$section";
}

function build_schedule_description(array $labels, array $entry): string
{
    $teacher = format_entity_label(
        $labels["faculties"],
        $entry["p_faculty_id"] ?? null,
        "Faculty",
    );
    $subject = format_entity_label(
        $labels["subjects"],
        $entry["subject_id"] ?? null,
        "Subject",
    );
    $section = format_entity_label(
        $labels["sections"],
        $entry["p_section_id"] ?? null,
        "Section",
    );
    return "$subject ({$teacher}) — {$section}";
}

function format_section_label_for_message(string $label): string
{
    $clean = str_replace([" (", ")"], [", ", ""], $label);
    return trim($clean);
}

function format_day_date(?string $day, ?string $date): string
{
    if ($date) {
        $dt = DateTime::createFromFormat("Y-m-d", $date);
        if ($dt) {
            return $dt->format("D, M j, Y");
        }
    }
    return $day ?: "Unknown day";
}

function format_time_range(?string $start, ?string $end): string
{
    $start_label = $start;
    $end_label = $end;
    if ($start) {
        $s =
            DateTime::createFromFormat("H:i:s", $start) ?:
            DateTime::createFromFormat("H:i", $start);
        if ($s) {
            $start_label = $s->format("g:i A");
        }
    }
    if ($end) {
        $e =
            DateTime::createFromFormat("H:i:s", $end) ?:
            DateTime::createFromFormat("H:i", $end);
        if ($e) {
            $end_label = $e->format("g:i A");
        }
    }
    if ($start_label && $end_label) {
        return "{$start_label}-{$end_label}";
    }
    return $start_label ?: ($end_label ?: "Unknown time");
}

function build_clear_conflict_message(
    array $labels,
    array $entry,
    ?array $other_row,
    string $conflict_type,
): string {
    $teacher_label = format_entity_label(
        $labels["faculties"],
        $entry["p_faculty_id"] ?? null,
        "Faculty",
    );
    $subject_label = format_entity_label(
        $labels["subjects"],
        $entry["subject_id"] ?? null,
        "Subject",
    );
    $section_label = format_section_label_for_message(
        format_entity_label(
            $labels["sections"],
            $entry["p_section_id"] ?? null,
            "Section",
        ),
    );

    $other_subject = "another class";
    $other_section = "another section";
    if ($other_row) {
        $other_subject = format_entity_label(
            $labels["subjects"],
            $other_row["subject_id"] ?? null,
            "Subject",
        );
        $other_section = format_section_label_for_message(
            format_entity_label(
                $labels["sections"],
                $other_row["section_id"] ?? null,
                "Section",
            ),
        );
    }

    $day_date = format_day_date(
        $entry["day_of_week"] ?? null,
        $entry["p_schedule_date"] ?? null,
    );
    $time_range = format_time_range(
        $entry["start_time"] ?? null,
        $entry["end_time"] ?? null,
    );

    if ($conflict_type === "Teacher Conflict") {
        return "Teacher conflict: {$teacher_label} is already teaching {$other_subject} ({$other_section}) at {$day_date} {$time_range}, so {$subject_label} ({$section_label}) can't be scheduled then.";
    }

    return "Conflict: {$subject_label} ({$section_label}) overlaps with {$other_subject} ({$other_section}) at {$day_date} {$time_range}.";
}

function fetch_conflict_row(
    mysqli $conn,
    array $entry,
    string $conflict_type,
): ?array {
    $params = [
        "p_section_id" => $entry["p_section_id"] ?? null,
        "p_schedule_date" => $entry["p_schedule_date"] ?? null,
        "p_time_slot_id" => $entry["p_time_slot_id"] ?? null,
        "p_day_of_week" => $entry["day_of_week"] ?? null,
    ];

    switch ($conflict_type) {
        case "Teacher Conflict":
            $sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                 WHERE faculty_id = ? AND schedule_date = ? AND time_slot_id = ?
                 LIMIT 1";
            $values = [
                $entry["p_faculty_id"] ?? null,
                $params["p_schedule_date"],
                $params["p_time_slot_id"],
            ];
            break;
        case "Section Conflict":
            $sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                 WHERE section_id = ? AND schedule_date = ? AND time_slot_id = ?
                 LIMIT 1";
            $values = [
                $params["p_section_id"],
                $params["p_schedule_date"],
                $params["p_time_slot_id"],
            ];
            break;
        case "Room Conflict":
            $sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                 WHERE room_id = ? AND schedule_date = ? AND time_slot_id = ?
                 LIMIT 1";
            $values = [
                $entry["p_room_id"] ?? null,
                $params["p_schedule_date"],
                $params["p_time_slot_id"],
            ];
            break;
        default:
            return null;
    }

    if (!$values[0] || !$params["p_time_slot_id"]) {
        return null;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $types = "isi";
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return $row;
    }

    // Fallback: use time overlap on the same schedule_date (different time_slot_id).
    if (!empty($entry["start_time"]) && !empty($entry["end_time"])) {
        switch ($conflict_type) {
            case "Teacher Conflict":
                $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                    FROM schedules s
                    JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                    WHERE s.faculty_id = ? AND s.schedule_date = ?
                      AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                    LIMIT 1";
                $fallback_values = [
                    $entry["p_faculty_id"] ?? null,
                    $params["p_schedule_date"],
                    $entry["start_time"],
                    $entry["end_time"],
                ];
                $fallback_types = "isss";
                break;
            case "Section Conflict":
                $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                    FROM schedules s
                    JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                    WHERE s.section_id = ? AND s.schedule_date = ?
                      AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                    LIMIT 1";
                $fallback_values = [
                    $params["p_section_id"],
                    $params["p_schedule_date"],
                    $entry["start_time"],
                    $entry["end_time"],
                ];
                $fallback_types = "isss";
                break;
            case "Room Conflict":
                $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                    FROM schedules s
                    JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                    WHERE s.room_id = ? AND s.schedule_date = ?
                      AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                    LIMIT 1";
                $fallback_values = [
                    $entry["p_room_id"] ?? null,
                    $params["p_schedule_date"],
                    $entry["start_time"],
                    $entry["end_time"],
                ];
                $fallback_types = "isss";
                break;
            default:
                $fallback_sql = null;
                $fallback_values = [];
                $fallback_types = "";
        }

        if (!empty($fallback_sql) && !empty($fallback_values[0])) {
            $stmt = $conn->prepare($fallback_sql);
            if ($stmt) {
                $stmt->bind_param($fallback_types, ...$fallback_values);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return $row;
                }
            }
        }
    }

    if (!$params["p_day_of_week"]) {
        return null;
    }

    switch ($conflict_type) {
        case "Teacher Conflict":
            $fallback_sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                WHERE faculty_id = ? AND time_slot_id = ?
                  AND (schedule_date IS NULL AND day_of_week = ?)
                LIMIT 1";
            $fallback_values = [
                $entry["p_faculty_id"] ?? null,
                $params["p_time_slot_id"],
                $params["p_day_of_week"],
            ];
            $fallback_types = "iis";
            break;
        case "Section Conflict":
            $fallback_sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                WHERE section_id = ? AND time_slot_id = ?
                  AND (schedule_date IS NULL AND day_of_week = ?)
                LIMIT 1";
            $fallback_values = [
                $params["p_section_id"],
                $params["p_time_slot_id"],
                $params["p_day_of_week"],
            ];
            $fallback_types = "iis";
            break;
        case "Room Conflict":
            $fallback_sql = "SELECT faculty_id, section_id, subject_id FROM schedules
                WHERE room_id = ? AND time_slot_id = ?
                  AND (schedule_date IS NULL AND day_of_week = ?)
                LIMIT 1";
            $fallback_values = [
                $entry["p_room_id"] ?? null,
                $params["p_time_slot_id"],
                $params["p_day_of_week"],
            ];
            $fallback_types = "iis";
            break;
        default:
            return null;
    }

    if (!$fallback_values[0]) {
        return null;
    }

    $stmt = $conn->prepare($fallback_sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($fallback_types, ...$fallback_values);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return $row;
    }

    // Final fallback: overlap on day_of_week when schedule_date is NULL.
    if (empty($entry["start_time"]) || empty($entry["end_time"])) {
        return null;
    }

    switch ($conflict_type) {
        case "Teacher Conflict":
            $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                FROM schedules s
                JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                WHERE s.faculty_id = ? AND s.schedule_date IS NULL AND s.day_of_week = ?
                  AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                LIMIT 1";
            $fallback_values = [
                $entry["p_faculty_id"] ?? null,
                $params["p_day_of_week"],
                $entry["start_time"],
                $entry["end_time"],
            ];
            $fallback_types = "isss";
            break;
        case "Section Conflict":
            $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                FROM schedules s
                JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                WHERE s.section_id = ? AND s.schedule_date IS NULL AND s.day_of_week = ?
                  AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                LIMIT 1";
            $fallback_values = [
                $params["p_section_id"],
                $params["p_day_of_week"],
                $entry["start_time"],
                $entry["end_time"],
            ];
            $fallback_types = "isss";
            break;
        case "Room Conflict":
            $fallback_sql = "SELECT s.faculty_id, s.section_id, s.subject_id
                FROM schedules s
                JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
                WHERE s.room_id = ? AND s.schedule_date IS NULL AND s.day_of_week = ?
                  AND NOT (ts.end_time <= ? OR ts.start_time >= ?)
                LIMIT 1";
            $fallback_values = [
                $entry["p_room_id"] ?? null,
                $params["p_day_of_week"],
                $entry["start_time"],
                $entry["end_time"],
            ];
            $fallback_types = "isss";
            break;
        default:
            return null;
    }

    if (!$fallback_values[0]) {
        return null;
    }

    $stmt = $conn->prepare($fallback_sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($fallback_types, ...$fallback_values);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
?>
