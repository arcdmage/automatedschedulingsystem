<?php
/**
 * Common helpers for action scripts:
 * - detect AJAX
 * - determine safe return URL
 * - respond JSON or redirect
 */

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
            ($_SERVER["HTTP_REFERER"] ??
                "/mainscheduler/tabs/schedule_view.php"));
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
?>
