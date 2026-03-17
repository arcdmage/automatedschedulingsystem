<?php
/**
 * schedule_auto_generate.php
 * Generates a Mon-Fri weekly template from configured subject patterns.
 * No date inputs needed - creates template rows identified by notes LIKE 'Auto-generated%'
 */

// Suppress HTML error output - must be before anything else
ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

// Output buffer lets us wipe stray output before echoing JSON
ob_start();

// Only intercept FATAL errors (E_ERROR etc) - these skip the try/catch
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
        ob_end_clean(); // discard any partial HTML output
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
        ob_end_flush(); // normal exit - flush buffered JSON response
    }
});

require_once __DIR__ . "/../../db_connect.php";

// Clear anything db_connect may have printed, then set header
ob_clean();
if (!headers_sent()) {
    header("Content-Type: application/json");
}

// Catch non-fatal PHP errors (notices, warnings) and return as JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Only treat E_WARNING and above as fatal for our purposes
    if ($errno & (E_WARNING | E_ERROR | E_PARSE)) {
        ob_clean();
        echo json_encode([
            "success" => false,
            "message" => "PHP Error [$errno]: $errstr at line $errline",
        ]);
        exit();
    }
    return false; // let PHP handle notices normally (logged, not printed)
});

require_once __DIR__ . "/action_helper.php";

$debug_log = [];
function dlog($m)
{
    global $debug_log;
    $debug_log[] = $m;
}

$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

/**
 * Runs the conflict stored procedure against the supplied prospective entries.
 * Returns the filtered list (entries with no conflicts) and the list of conflict details.
 *
 * @param array $labels signature returned by prepare_entity_labels()
 */
function detect_conflicts(
    mysqli $conn,
    array $entries,
    bool $has_day,
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

        if ($conflict_result && $conflict_result["conflict_type"] !== null) {
            $total_external_conflicts++;
            $conflict_row = fetch_conflict_row(
                $conn,
                $entry,
                $conflict_result["conflict_type"],
            );
            $entry_description = build_schedule_description($labels, $entry);
            $other_description = "unknown entry";
            if ($conflict_row) {
                $other_description = build_schedule_description($labels, [
                    "p_faculty_id" => $conflict_row["faculty_id"],
                    "subject_id" => $conflict_row["subject_id"],
                    "p_section_id" => $conflict_row["section_id"],
                ]);
            }

            $clear_message = build_clear_conflict_message(
                $labels,
                $entry,
                $conflict_row,
                $conflict_result["conflict_type"],
            );
            $conflict_details[] = [
                "type" => $conflict_result["conflict_type"],
                "details" => $clear_message,
                "conflict_with" => $other_description,
            ];
            dlog("EXTERNAL CONFLICT DETECTED: " . $clear_message);
        } else {
            $filtered[] = $entry;
        }
    }

    $conflict_check_stmt->close();
    $get_conflict_result_stmt->close();

    return [$filtered, $conflict_details, $total_external_conflicts];
}

try {
    $entity_labels = prepare_entity_labels($conn);
    // ── Validate DB connection ────────────────────────────────────────────────
    if (!isset($conn) || !$conn) {
        throw new Exception(
            "Database connection failed. Check db_connect.php.",
        );
    }

    // ── Input ─────────────────────────────────────────────────────────────────
    $section_id = intval($_POST["section_id"] ?? 0);
    if (!$section_id) {
        throw new Exception("Missing section_id");
    }
    dlog("section_id=$section_id");

    // ── Inspect schedules table ───────────────────────────────────────────────
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
    dlog("Cols: " . implode(", ", $cols));

    $has_day = in_array("day_of_week", $cols);
    $has_auto = in_array("is_auto_generated", $cols);
    $has_room = in_array("room", $cols);
    $date_ok_null = $nullable["schedule_date"] ?? false;
    dlog(
        "has_day=$has_day has_auto=$has_auto has_room=$has_room date_ok_null=$date_ok_null",
    );

    // ── Load requirements ─────────────────────────────────────────────────────
    $r = $conn->prepare(
        "SELECT requirement_id, subject_id, faculty_id
         FROM subject_requirements WHERE section_id = ?",
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

    // ── Load patterns ─────────────────────────────────────────────────────────
    $pats = [];
    foreach ($reqs as $req) {
        $rid = (int) $req["requirement_id"];
        $p = $conn->prepare(
            "SELECT sp.day_of_week, sp.time_slot_id, ts.start_time, ts.end_time
             FROM schedule_patterns sp
             JOIN time_slots ts ON sp.time_slot_id = ts.time_slot_id
             WHERE sp.requirement_id = ?
             ORDER BY ts.slot_order",
        );
        if (!$p) {
            throw new Exception("Prepare patterns: " . $conn->error);
        }
        $p->bind_param("i", $rid);
        $p->execute();
        $pats[$rid] = $p->get_result()->fetch_all(MYSQLI_ASSOC);
        $p->close();

        if (empty($pats[$rid])) {
            throw new Exception(
                "Requirement #$rid has no patterns. Set up patterns in Setup first.",
            );
        }
        dlog("Req $rid: " . count($pats[$rid]) . " pattern(s)");
    }

    // ── Transaction start ─────────────────────────────────────────────────────
    $conn->begin_transaction();

    // ── (Deleted moved) Placeholder — deletion of old auto-generated rows is deferred
    // to after conflict checks and will only run if the user confirms replacing
    // existing auto-generated schedules. This avoids accidentally removing entries
    // before the user reviews any detected conflicts.
    //
    // The actual DELETE will be executed later (after conflict detection) only when
    // the request includes a confirmation flag: POST parameter 'confirm_force' = '1'.

    // ── Real dates for the CURRENT Mon–Fri week ─────────────────────────────
    // Using real dates means view/delete queries (which filter by date range) work.
    // We anchor to this week's Monday so every regeneration uses a consistent,
    // predictable date that is easy to query (e.g. "next 7 days" or "this week").
    $monday = new DateTime();
    $dow = (int) $monday->format("N"); // 1=Mon, 7=Sun
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
    // Rules:
    //   - Only ? placeholders appear in $types
    //   - Literals (NULL, 1, '') are written directly into SQL, NOT in $types
    //
    // Params always bound (in order):
    //   faculty_id(s), subject_id(i), section_id(i),
    //   [schedule_date(s) — only when NOT nullable],
    //   start_time(s), end_time(s), time_slot_id(i), notes(s),
    //   [day_of_week(s) — only when column exists]

    $icols = ["faculty_id", "subject_id", "section_id"];
    $ivals = ["?", "?", "?"];
    $types = "sii";

    // schedule_date
    $icols[] = "schedule_date";
    // Always use real week dates (never NULL) so view/delete date-range queries work
    $ivals[] = "?";
    $types .= "s";
    $date_ok_null = false; // force sentinel-date branch in bind_param logic below

    // start_time, end_time, time_slot_id, notes
    array_push($icols, "start_time", "end_time", "time_slot_id", "notes");
    array_push($ivals, "?", "?", "?", "?");
    $types .= "ssis";

    // day_of_week (optional column)
    if ($has_day) {
        $icols[] = "day_of_week";
        $ivals[] = "?";
        $types .= "s";
    }

    // is_auto_generated (literal, no bind param)
    if ($has_auto) {
        $icols[] = "is_auto_generated";
        $ivals[] = "1";
    }

    // room (literal empty string if NOT NULL with no default)
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
    dlog('Types: "' . $types . '" (' . strlen($types) . " bound params)");

    $ins = $conn->prepare($sql);
    if (!$ins) {
        throw new Exception(
            "Prepare insert failed: " . $conn->error . " | SQL: " . $sql,
        );
    }

    // ── Generate template entries Mon–Fri ─────────────────────────────────────
    $internal_skipped_count = 0; // Counts conflicts found *within* the generation batch
    $prospective_schedules = []; // Stores details of schedules that *could* be created
    $external_conflict_details = []; // Stores detailed messages from sp_check_schedule_conflict

    // --- Step 1: Collect prospective schedules and perform preliminary checks ---
    $internal_slot_usage = []; // Track slots used *within this generation batch*

    foreach ($days as $day) {
        foreach ($reqs as $req) {
            $rid = (int) $req["requirement_id"];
            $subj = (int) $req["subject_id"];
            $fac = (string) ($req["faculty_id"] ?? "");

            foreach ($pats[$rid] as $pat) {
                if ($pat["day_of_week"] !== $day) {
                    continue;
                }

                $slot = (int) $pat["time_slot_id"];
                $ts = (string) $pat["start_time"];
                $te = (string) $pat["end_time"];
                $note = "Auto-generated";
                $sdate = $sentinels[$day];
                $room_id = null; // Auto-generated schedules don't assign rooms by default, so pass NULL to SP

                // Internal (within this batch) slot conflict check
                $internal_key = "$day|$slot";
                if (isset($internal_slot_usage[$internal_key])) {
                    dlog(
                        "INTERNAL CONFLICT: Slot $day|$slot already assigned to another subject in this generation. Skipping.",
                    );
                    $internal_skipped_count++;
                    continue;
                }

                // Add to prospective schedules.
                $prospective_schedules[] = [
                    "p_faculty_id" => $fac === "" ? null : (int) $fac, // Cast to int for stored proc
                    "p_section_id" => $section_id,
                    "p_schedule_date" => $sdate,
                    "p_time_slot_id" => $slot,
                    "p_room_id" => $room_id, // Always NULL for auto-generation as no room assigned
                    "subject_id" => $subj,
                    "start_time" => $ts,
                    "end_time" => $te,
                    "day_of_week" => $day,
                    "notes" => $note,
                ];
                $internal_slot_usage[$internal_key] = true;
            }
        }
    }
    dlog(
        count($prospective_schedules) .
            " prospective schedule entries collected from patterns. " .
            $internal_skipped_count .
            " internal pattern conflicts skipped.",
    );

    // --- Step 2: Perform comprehensive conflict checks using sp_check_schedule_conflict ---
    $prospective_backup = $prospective_schedules;
    [
        $prospective_schedules,
        $external_conflict_details,
        $total_external_conflicts,
    ] = detect_conflicts(
        $conn,
        $prospective_schedules,
        $has_day,
        $entity_labels,
    );
    $total_conflicts_found =
        $internal_skipped_count + $total_external_conflicts;

    // If any conflicts were found (internal or external), require confirmation from the user
    // before replacing existing auto-generated schedules.
    if (!empty($external_conflict_details) || $internal_skipped_count > 0) {
        // The frontend can confirm replacement by resubmitting this same request
        // with POST parameter 'confirm_force' = '1'. If not provided, return the
        // conflict details and instruct the UI to show a confirmation button.
        $force =
            isset($_POST["confirm_force"]) &&
            ($_POST["confirm_force"] == "1" ||
                $_POST["confirm_force"] === true);

        if (!$force) {
            ob_clean();
            echo json_encode([
                "success" => false,
                "schedules_created" => 0,
                "conflicts_found" => $total_conflicts_found,
                "message" =>
                    "Conflicts detected. Review the conflicts and confirm to proceed with replacing auto-generated schedules.",
                "conflict_details" => $external_conflict_details,
                "internal_conflicts_count" => $internal_skipped_count,
                "needs_confirmation" => true,
                "confirmation_instructions" =>
                    "Resubmit this request with POST parameter 'confirm_force' = '1' to proceed and replace existing auto-generated schedules.",
                "debug_log" => $debug_log,
            ]);
            // Close connection on early exit
            if (isset($conn) && $conn) {
                $conn->close();
            }
            exit();
        }

        $prospective_schedules = $prospective_backup;
        [
            $prospective_schedules,
            $external_conflict_details,
            $total_external_conflicts,
        ] = detect_conflicts(
            $conn,
            $prospective_schedules,
            $has_day,
            $entity_labels,
        );
        $total_conflicts_found =
            $internal_skipped_count + $total_external_conflicts;
        dlog(
            "After confirmation rechecked conflicts; ${total_external_conflicts} external remain.",
        );
        if (empty($prospective_schedules)) {
            throw new Exception(
                "After resolving conflicts there were no schedules left to insert.",
            );
        }

        // Proceed with insertion below (the rest of the flow remains unchanged)
    }

    // --- Step 3: If no conflicts, delete the existing rows and insert the new ones ---
    if (empty($prospective_schedules)) {
        throw new Exception(
            "After resolving conflicts there were no schedules left to insert.",
        );
    }

    $del = $conn->prepare("DELETE FROM schedules WHERE section_id = ?");
    if (!$del) {
        throw new Exception("Prepare delete: " . $conn->error);
    }
    $del->bind_param("i", $section_id);
    $del->execute();
    dlog(
        "Deleted " .
            $del->affected_rows .
            " existing schedule rows for section {$section_id} before inserting new template.",
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
        // Only insert non-conflicting schedules
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
            // Even if an internal error occurs here, it's not a conflict but a DB issue.
            // We should still report it, but the primary conflict detection is done.
        }
    }

    $ins->close();
    $conn->commit();

    ob_clean();
    echo json_encode([
        "success" => true,
        "schedules_created" => $created,
        "conflicts_found" => 0, // No conflicts if we reach here
        "message" => "Weekly template generated successfully.",
        "debug_log" => $debug_log,
    ]);
} catch (Exception $e) {
    // Attempt rollback for any transaction that might be active (e.g., the final DML one)
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
    // Ensure the database connection is always closed
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
