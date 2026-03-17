<?php
ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function () {
    $err = error_get_last();
    if (
        $err &&
        in_array($err["type"], [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ])
    ) {
        ob_end_clean();
        if (!headers_sent()) {
            header("Content-Type: application/json");
        }
        echo json_encode([
            "success" => false,
            "message" =>
                "Fatal error: " .
                $err["message"] .
                " (line " .
                $err["line"] .
                ")",
        ]);
    } else {
        ob_end_flush();
    }
});

require_once __DIR__ . "/../../db_connect.php";
require_once __DIR__ . "/action_helper.php";
ob_clean();
if (!headers_sent()) {
    header("Content-Type: application/json");
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if ($errno & (E_WARNING | E_ERROR | E_PARSE)) {
        ob_clean();
        echo json_encode([
            "success" => false,
            "message" => "PHP Error [$errno]: $errstr at line $errline",
        ]);
        exit();
    }
    return false;
});

$debug_log = [];
function dlog($m)
{
    global $debug_log;
    $debug_log[] = $m;
}

$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

function detect_random_conflicts(
    mysqli $conn,
    array $entries,
    array $labels,
): array {
    global $debug_log;

    $conflict_details = [];
    $filtered = [];
    $total_external_conflicts = 0;

    $conflict_check_stmt = $conn->prepare(
        "CALL sp_check_schedule_conflict(?, ?, ?, ?, ?, @conflict_type, @conflict_message)",
    );
    if (!$conflict_check_stmt) {
        throw new Exception(
            "Prepare sp_check_schedule_conflict failed: " . $conn->error,
        );
    }

    $get_conflict_result_stmt = $conn->prepare(
        "SELECT @conflict_type AS conflict_type, @conflict_message AS conflict_message",
    );
    if (!$get_conflict_result_stmt) {
        throw new Exception(
            "Prepare get conflict result failed: " . $conn->error,
        );
    }

    foreach ($entries as $entry) {
        $conflict_check_stmt->bind_param(
            "iisii",
            $entry["p_faculty_id"],
            $entry["p_section_id"],
            $entry["p_schedule_date"],
            $entry["p_time_slot_id"],
            $entry["p_room_id"],
        );
        $conflict_check_stmt->execute();

        if ($res = $conflict_check_stmt->get_result()) {
            $res->free();
        }
        while ($conflict_check_stmt->more_results()) {
            $conflict_check_stmt->next_result();
            if ($r = $conflict_check_stmt->get_result()) {
                $r->free();
            }
        }

        $get_conflict_result_stmt->execute();
        $conflict_result = $get_conflict_result_stmt
            ->get_result()
            ->fetch_assoc();

        if ($conflict_result["conflict_type"] !== null) {
            $faculty_label = format_entity_label(
                $labels["faculties"],
                $entry["p_faculty_id"],
                "Faculty",
            );
            $subject_label = format_entity_label(
                $labels["subjects"],
                $entry["subject_id"],
                "Subject",
            );
            $section_label = format_entity_label(
                $labels["sections"],
                $entry["p_section_id"],
                "Section",
            );

            $conflict_details[] = [
                "type" => $conflict_result["conflict_type"],
                "message" => $conflict_result["conflict_message"],
                "details" => "$subject_label (Faculty: $faculty_label, Section: $section_label) on {$entry["day_of_week"]} ({$entry["p_schedule_date"]}) at {$entry["start_time"]}-{$entry["end_time"]}",
            ];
            $total_external_conflicts++;
            dlog(
                "EXTERNAL CONFLICT DETECTED: {$conflict_result["conflict_type"]} - {$conflict_result["conflict_message"]} for $subject_label on {$entry["day_of_week"]} {$entry["start_time"]} (Faculty: $faculty_label)",
            );
        } else {
            $filtered[] = $entry;
        }
    }

    $conflict_check_stmt->close();
    $get_conflict_result_stmt->close();

    return [$filtered, $conflict_details, $total_external_conflicts];
}

try {
    if (!isset($conn) || !$conn) {
        throw new Exception(
            "Database connection failed. Check db_connect.php.",
        );
    }

    $entity_labels = prepare_entity_labels($conn);

    $section_id = intval($_POST["section_id"] ?? 0);
    if (!$section_id) {
        throw new Exception("Missing section_id.");
    }

    // ── Inspect schedules table columns ───────────────────────────────────────
    $cr = $conn->query("SHOW COLUMNS FROM schedules");
    if (!$cr) {
        throw new Exception("Cannot inspect schedules table: " . $conn->error);
    }

    $cols = [];
    $nullable = [];
    $col_defaults = [];
    while ($c = $cr->fetch_assoc()) {
        $cols[] = $c["Field"];
        $nullable[$c["Field"]] = $c["Null"] === "YES";
        $col_defaults[$c["Field"]] = $c["Default"];
    }

    $has_day = in_array("day_of_week", $cols);
    $has_auto = in_array("is_auto_generated", $cols);
    $has_room = in_array("room", $cols);
    $date_ok_null = $nullable["schedule_date"] ?? false;

    dlog(
        "Cols: has_day=$has_day has_auto=$has_auto has_room=$has_room date_ok_null=$date_ok_null",
    );

    // ── Load requirements (subject + faculty + hours needed) ──────────────────
    $r = $conn->prepare(
        "SELECT sr.requirement_id, sr.subject_id, sr.faculty_id, sr.hours_per_week,
                subj.subject_name
         FROM subject_requirements sr
         JOIN subjects subj ON sr.subject_id = subj.subject_id
         WHERE sr.section_id = ?",
    );
    if (!$r) {
        throw new Exception("Prepare requirements: " . $conn->error);
    }
    $r->bind_param("i", $section_id);
    $r->execute();
    $reqs = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();

    if (empty($reqs)) {
        throw new Exception("No subject requirements for this section.");
    }
    dlog(count($reqs) . " requirements loaded");

    // ── Load non-break time slots for this section ────────────────────────────
    $ts_stmt = $conn->prepare(
        "SELECT time_slot_id, start_time, end_time, slot_order
         FROM time_slots
         WHERE section_id = ? AND (is_break = 0 OR is_break IS NULL)
         ORDER BY slot_order",
    );
    if (!$ts_stmt) {
        throw new Exception("Prepare time slots: " . $conn->error);
    }
    $ts_stmt->bind_param("i", $section_id);
    $ts_stmt->execute();
    $time_slots = $ts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ts_stmt->close();

    if (empty($time_slots)) {
        throw new Exception(
            "No time slots found for this section. " .
                "Add time slots in Setup before generating a random schedule.",
        );
    }
    dlog(count($time_slots) . " non-break time slots available");

    // ── Build the full pool of (day, slot) pairs ──────────────────────────────
    // Each entry: ['day'=>'Monday', 'slot_id'=>3, 'start'=>'07:30:00', 'end'=>'08:30:00']
    $pool = [];
    foreach ($days as $day) {
        foreach ($time_slots as $ts) {
            $pool[] = [
                "day" => $day,
                "slot_id" => (int) $ts["time_slot_id"],
                "start" => $ts["start_time"],
                "end" => $ts["end_time"],
            ];
        }
    }

    $total_slots_available = count($pool);
    $total_hours_needed = array_sum(array_column($reqs, "hours_per_week"));
    dlog(
        "Pool size: $total_slots_available | Hours needed: $total_hours_needed",
    );

    if ($total_hours_needed > $total_slots_available) {
        throw new Exception(
            "Not enough time slots: need $total_hours_needed slots but only " .
                "$total_slots_available are available (Mon–Fri × " .
                count($time_slots) .
                " slots). " .
                "Reduce hours per week or add more time slots.",
        );
    }

    $monday = new DateTime();
    $dow = (int) $monday->format("N");
    if ($dow !== 1) {
        $monday->modify("-" . ($dow - 1) . " days");
    }
    $sentinels = [];
    $dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    for ($i = 0; $i < 5; $i++) {
        $d = clone $monday;
        $d->modify("+$i days");
        $sentinels[$dayNames[$i]] = $d->format("Y-m-d");
    }
    dlog("Week dates: " . implode(", ", $sentinels));

    // ── Build INSERT SQL + type string ────────────────────────────────────────
    $icols = ["faculty_id", "subject_id", "section_id"];
    $ivals = ["?", "?", "?"];
    $types = "sii";

    $icols[] = "schedule_date";
    $ivals[] = "?";
    $types .= "s";
    $date_ok_null = false;

    array_push($icols, "start_time", "end_time", "time_slot_id", "notes");
    array_push($ivals, "?", "?", "?", "?");
    $types .= "ssis";

    if ($has_day) {
        $icols[] = "day_of_week";
        $ivals[] = "?";
        $types .= "s";
    }
    if ($has_auto) {
        $icols[] = "is_auto_generated";
        $ivals[] = "1";
    }
    if ($has_room && !$nullable["room"] && $col_defaults["room"] === null) {
        $icols[] = "room";
        $ivals[] = "''";
        dlog("Adding empty room column (NOT NULL, no default)");
    }

    $sql =
        "INSERT INTO schedules (" .
        implode(", ", $icols) .
        ") VALUES (" .
        implode(", ", $ivals) .
        ")";

    dlog("SQL: " . $sql);
    dlog('Types: "' . $types . '" (' . strlen($types) . " params)");

    // ── Collect prospective schedules and perform preliminary checks ──────────
    $prospective_schedules = [];
    $internal_slot_usage = []; // Track slots used *within this generation batch*

    shuffle($pool); // Randomize the pool of available slots
    shuffle($reqs); // Randomize the order of requirements

    $current_pool_index = 0;
    $total_skipped_from_insufficient_slots = 0;

    foreach ($reqs as $req) {
        $subj = (int) $req["subject_id"];
        $fac = (string) ($req["faculty_id"] ?? "");
        $hours_needed = (int) $req["hours_per_week"];
        $subj_name = $req["subject_name"];

        dlog(
            "Subject: $subj_name | needs $hours_needed slots | faculty='$fac'",
        );

        $assigned_slots_for_subject = 0;
        // Attempt to assign slots from the shuffled pool
        for (
            $i = $current_pool_index;
            $i < count($pool) && $assigned_slots_for_subject < $hours_needed;
            $i++
        ) {
            $entry = $pool[$i];
            $day = $entry["day"];
            $slot = (int) $entry["slot_id"];
            $ts = $entry["start"];
            $te = $entry["end"];
            $note = "Auto-generated";
            $sdate = $sentinels[$day];
            $room_id = null; // Random schedules don't assign rooms by default, so pass NULL to SP

            $internal_key = "$day|$slot";

            // Check if this slot (day + time_slot_id) is already used by another schedule in this *prospective batch*
            if (isset($internal_slot_usage[$internal_key])) {
                dlog(
                    "  INTERNAL CONFLICT: Slot $day|$slot already assigned to another subject in this generation. Skipping.",
                );
                continue;
            }

            // Check for faculty time overlap within the *prospective batch*
            $faculty_internal_conflict = false;
            if ($fac !== "") {
                foreach ($prospective_schedules as $ps) {
                    if (
                        $ps["p_faculty_id"] == $fac &&
                        $ps["day_of_week"] === $day
                    ) {
                        // Check for time overlap
                        // Existing: ps['start_time'] to ps['end_time']
                        // New: ts to te
                        if ($ps["start_time"] < $te && $ps["end_time"] > $ts) {
                            $faculty_internal_conflict = true;
                            break;
                        }
                    }
                }
            }

            if ($faculty_internal_conflict) {
                dlog(
                    "  INTERNAL CONFLICT: Faculty $fac already has an overlapping slot on $day at $ts (for $subj_name). Skipping.",
                );
                continue;
            }

            // If no internal conflicts, add to prospective schedules
            $prospective_schedules[] = [
                "p_faculty_id" => $fac === "" ? null : (int) $fac,
                "p_section_id" => $section_id,
                "p_schedule_date" => $sdate,
                "p_time_slot_id" => $slot,
                "p_room_id" => $room_id,
                "subject_id" => $subj,
                "start_time" => $ts,
                "end_time" => $te,
                "day_of_week" => $day,
                "notes" => $note,
            ];
            $internal_slot_usage[$internal_key] = true;
            $assigned_slots_for_subject++;
            dlog(
                "  ASSIGNED (internally): $day slot=$slot for $subj_name (Faculty: $fac)",
            );
        }
        $total_skipped_from_insufficient_slots +=
            $hours_needed - $assigned_slots_for_subject;
    }
    dlog(
        count($prospective_schedules) .
            " prospective schedule entries collected from random assignment. " .
            $total_skipped_from_insufficient_slots .
            " slots could not be internally placed due to limited availability or internal conflicts.",
    );

    // --- Step 2: Perform comprehensive conflict checks using sp_check_schedule_conflict against existing schedules ---
    $prospective_backup = $prospective_schedules;
    [
        $prospective_schedules,
        $external_conflict_details,
        $total_external_conflicts,
    ] = detect_random_conflicts($conn, $prospective_schedules, $entity_labels);
    $total_conflicts_found =
        $total_skipped_from_insufficient_slots + $total_external_conflicts;

    // If any conflicts were found (internal or external), require confirmation from the user
    // before replacing existing auto-generated schedules.
    $force =
        isset($_POST["confirm_force"]) &&
        ($_POST["confirm_force"] == "1" || $_POST["confirm_force"] === true);

    // If any conflicts were found (internal or external), we either require confirmation
    // or, if the user has confirmed (force), delete previous auto rows and re-check conflicts
    if (
        !empty($external_conflict_details) ||
        $total_skipped_from_insufficient_slots > 0
    ) {
        if (!$force) {
            // Ask the frontend to confirm replacement
            ob_clean();
            echo json_encode([
                "success" => false,
                "schedules_created" => 0,
                "conflicts_found" => $total_conflicts_found,
                "needs_confirmation" => true,
                "message" =>
                    "Conflicts detected. Review the conflicts and confirm to proceed with replacing auto-generated schedules.",
                "conflict_details" => $external_conflict_details,
                "internal_conflicts_count" => $total_skipped_from_insufficient_slots,
                "confirmation_instructions" =>
                    "Resubmit this request with POST parameter 'confirm_force' = '1' to proceed and replace existing auto-generated schedules.",
                "debug_log" => $debug_log,
            ]);
            // Close connection on early exit
            if (isset($conn) && $conn) {
                $conn->close();
            }
            exit();
        } else {
            $prospective_schedules = $prospective_backup;
            [
                $prospective_schedules,
                $external_conflict_details,
                $total_external_conflicts,
            ] = detect_random_conflicts(
                $conn,
                $prospective_schedules,
                $entity_labels,
            );
            $total_conflicts_found =
                $total_skipped_from_insufficient_slots +
                $total_external_conflicts;
            dlog(
                "After confirmation rechecked conflicts; ${total_external_conflicts} external remain.",
            );
            if (empty($prospective_schedules)) {
                throw new Exception(
                    "After conflict resolution there were no slots to save; generation was cancelled to preserve the existing schedule.",
                );
            }
        }
    }
    // If $force is true, we proceed normally; the later deletion/inserts will run and replace auto-generated rows.

    // --- Step 3: If conflicts are resolved, delete the prior schedule and insert the fresh template ---
    if (empty($prospective_schedules)) {
        throw new Exception(
            "After conflict resolution there were no slots to save; generation was cancelled to preserve the existing schedule.",
        );
    }

    $conn->begin_transaction(); // Start a new transaction for the actual DML operations

    $del = $conn->prepare("DELETE FROM schedules WHERE section_id = ?");
    if (!$del) {
        throw new Exception("Prepare delete: " . $conn->error);
    }
    $del->bind_param("i", $section_id);
    $del->execute();
    dlog(
        "Deleted " .
            $del->affected_rows .
            " existing schedule rows for section {$section_id} before inserting random template.",
    );
    $del->close();

    // Prepare insert statement for actual insertion
    $ins = $conn->prepare($sql); // $sql and $types are already defined above
    if (!$ins) {
        throw new Exception(
            "Prepare insert failed: " . $conn->error . " | SQL: " . $sql,
        );
    }

    $created = 0; // Reset created count as we are about to insert now

    foreach ($prospective_schedules as $entry) {
        // Build bind_param args array in exact SQL column order from $entry
        $args = [$types]; // Start with types string

        $args[] = $entry["p_faculty_id"];
        $args[] = $entry["subject_id"];
        $args[] = $entry["p_section_id"];
        if (!$date_ok_null) {
            $args[] = $entry["p_schedule_date"];
        }
        array_push(
            $args,
            $entry["start_time"],
            $entry["end_time"],
            $entry["p_time_slot_id"],
            $entry["notes"],
        );
        if ($has_day) {
            $args[] = $entry["day_of_week"];
        }

        $ins->bind_param(...$args);

        if ($ins->execute()) {
            $created++;
            dlog(
                "OK inserted: {$entry["day_of_week"]} subj={$entry["subject_id"]} slot={$entry["p_time_slot_id"]} fac='{$entry["p_faculty_id"]}'",
            );
        } else {
            dlog(
                "FAIL insert (after conflict check!): " .
                    $ins->error .
                    " | Details: {$entry["day_of_week"]} subj={$entry["subject_id"]} slot={$entry["p_time_slot_id"]}",
            );
        }
    }

    $ins->close();
    $conn->commit();

    ob_clean();
    echo json_encode([
        "success" => true,
        "schedules_created" => $created,
        "conflicts_found" => 0, // No conflicts if we reach here
        "message" => "Random weekly template generated successfully.",
        "debug_log" => $debug_log,
    ]);
} catch (Exception $e) {
    try {
        if (isset($conn) && $conn) {
            $conn->rollback();
        }
    } catch (Exception $re) {
        dlog("Rollback failed: " . $re->getMessage());
    }
    ob_clean();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "debug_log" => $debug_log,
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
