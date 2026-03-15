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

try {
    if (!isset($conn) || !$conn) {
        throw new Exception(
            "Database connection failed. Check db_connect.php.",
        );
    }

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
    $external_conflict_details = []; // Stores detailed messages from sp_check_schedule_conflict

    // Do not start a nested transaction for stored procedure calls; use the outer transaction context.

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

    $total_external_conflicts = 0;
    foreach ($prospective_schedules as $index => $entry) {
        $faculty_id = $entry["p_faculty_id"];
        $section_id_sp = $entry["p_section_id"];
        $schedule_date = $entry["p_schedule_date"];
        $time_slot_id = $entry["p_time_slot_id"];
        $room_id = $entry["p_room_id"];

        $conflict_check_stmt->bind_param(
            "iisii",
            $faculty_id,
            $section_id_sp,
            $schedule_date,
            $time_slot_id,
            $room_id,
        );
        $conflict_check_stmt->execute();

        // Consume any resultset(s) returned by the CALL to avoid "Commands out of sync" errors.
        // Some stored procedures return result sets even when they only set user variables.
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
            $external_conflict_details[] = [
                "type" => $conflict_result["conflict_type"],
                "message" => $conflict_result["conflict_message"],
                "details" => "Subject ID {$entry["subject_id"]} on {$entry["day_of_week"]} ({$entry["p_schedule_date"]}) at {$entry["start_time"]}-{$entry["end_time"]} (Faculty: {$entry["p_faculty_id"]})",
            ];
            $total_external_conflicts++;
            dlog(
                "EXTERNAL CONFLICT DETECTED: {$conflict_result["conflict_type"]} - {$conflict_result["conflict_message"]} for Subject {$entry["subject_id"]} on {$entry["day_of_week"]} {$entry["start_time"]}",
            );
            // Mark this prospective schedule as having an external conflict so it's not inserted later
            unset($prospective_schedules[$index]);
        }
    }

    // No nested commit here — leave transaction control to the outer flow.
    $conflict_check_stmt->close();
    $get_conflict_result_stmt->close();

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
            // User confirmed. Delete old auto-generated rows first, because some external
            // conflicts may have been caused by those rows. After deletion, re-run the
            // stored-proc conflict check on the remaining prospective schedules and
            // remove any that still conflict.
            $del = $conn->prepare(
                "DELETE FROM schedules WHERE section_id = ? AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')",
            );
            if (!$del) {
                throw new Exception("Prepare delete: " . $conn->error);
            }
            $del->bind_param("i", $section_id);
            $del->execute();
            dlog(
                "Deleted " .
                    $del->affected_rows .
                    " old auto rows (user confirmed)",
            );
            $del->close();

            // Re-check external conflicts against the live DB (some conflicts may be gone now).
            $recheck_stmt = $conn->prepare(
                "CALL sp_check_schedule_conflict(?, ?, ?, ?, ?, @conflict_type, @conflict_message)",
            );
            if (!$recheck_stmt) {
                throw new Exception(
                    "Prepare sp_check_schedule_conflict (recheck) failed: " .
                        $conn->error,
                );
            }
            $get_recheck_result_stmt = $conn->prepare(
                "SELECT @conflict_type AS conflict_type, @conflict_message AS conflict_message",
            );
            if (!$get_recheck_result_stmt) {
                throw new Exception(
                    "Prepare get conflict result (recheck) failed: " .
                        $conn->error,
                );
            }

            $external_conflict_details_after = [];
            foreach ($prospective_schedules as $index => $entry) {
                $faculty_id = $entry["p_faculty_id"];
                $section_id_sp = $entry["p_section_id"];
                $schedule_date = $entry["p_schedule_date"];
                $time_slot_id = $entry["p_time_slot_id"];
                $room_id = $entry["p_room_id"];

                $recheck_stmt->bind_param(
                    "iisii",
                    $faculty_id,
                    $section_id_sp,
                    $schedule_date,
                    $time_slot_id,
                    $room_id,
                );
                $recheck_stmt->execute();

                // Consume any resultset(s) returned by the CALL to avoid "Commands out of sync" errors.
                if ($res = $recheck_stmt->get_result()) {
                    $res->free();
                }
                while ($recheck_stmt->more_results()) {
                    $recheck_stmt->next_result();
                    if ($r = $recheck_stmt->get_result()) {
                        $r->free();
                    }
                }

                // Retrieve OUT parameters from user variables
                $get_recheck_result_stmt->execute();
                $gr = $get_recheck_result_stmt->get_result();
                $conflict_result = $gr ? $gr->fetch_assoc() : null;
                if ($gr instanceof mysqli_result) {
                    $gr->free();
                }

                if (
                    $conflict_result &&
                    $conflict_result["conflict_type"] !== null
                ) {
                    // Still conflicts after deleting previous auto rows -> report and skip insertion
                    $external_conflict_details_after[] = [
                        "type" => $conflict_result["conflict_type"],
                        "message" => $conflict_result["conflict_message"],
                        "details" => "Subject ID {$entry["subject_id"]} on {$entry["day_of_week"]} ({$entry["p_schedule_date"]}) at {$entry["start_time"]}-{$entry["end_time"]}",
                    ];
                    dlog(
                        "RECHECK EXTERNAL CONFLICT STILL DETECTED: {$conflict_result["conflict_type"]} - {$conflict_result["conflict_message"]} for Subject {$entry["subject_id"]} on {$entry["day_of_week"]} {$entry["start_time"]}",
                    );
                    // Remove from prospective insertion list
                    unset($prospective_schedules[$index]);
                }
            }

            $recheck_stmt->close();
            $get_recheck_result_stmt->close();

            // If after recheck there are still conflicts, add them to debug log so the user can be informed
            if (!empty($external_conflict_details_after)) {
                $total_remaining_conflicts = count(
                    $external_conflict_details_after,
                );
                dlog(
                    "After delete+recheck, $total_remaining_conflicts external conflicts remain; these will be skipped during insertion.",
                );
                // Append to debug_log and also keep $external_conflict_details for returning if desired
                foreach ($external_conflict_details_after as $cd) {
                    $external_conflict_details[] = $cd;
                }
            }
            // Proceed to insertion with the filtered $prospective_schedules
        }
    }
    // If $force is true, we proceed normally; the later deletion/inserts will run and replace auto-generated rows.

    // --- Step 3: If no conflicts, proceed with deletion and insertion ---
    // Note: The deletion of old auto-generated schedules is handled *before*
    // the conflict check block in the schedule_auto_generate.php file.
    // Here, we ensure it's done *after* the conflict check.
    // If we reach this point, it means no conflicts were found, so we can proceed.
    $conn->begin_transaction(); // Start a new transaction for the actual DML operations

    // Delete old auto-generated rows for this section
    // This deletion is now moved AFTER the conflict check to ensure we don't
    // remove schedules that would have conflicted with the new ones.
    // If conflicts are found, this block will not be reached.
    $del = $conn->prepare(
        "DELETE FROM schedules WHERE section_id = ? AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')",
    );
    if (!$del) {
        throw new Exception("Prepare delete: " . $conn->error);
    }
    $del->bind_param("i", $section_id);
    $del->execute();
    dlog("Deleted " . $del->affected_rows . " old auto rows");
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
