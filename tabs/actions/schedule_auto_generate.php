<?php
/**
 * schedule_auto_generate.php
 * Generates a Mon-Fri weekly template from configured subject patterns.
 * No date inputs needed - creates template rows identified by notes LIKE 'Auto-generated%'
 */

// Suppress HTML error output - must be before anything else
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

// Output buffer lets us wipe stray output before echoing JSON
ob_start();

// Only intercept FATAL errors (E_ERROR etc) - these skip the try/catch
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean(); // discard any partial HTML output
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $err['message'] . ' (line ' . $err['line'] . ')'
        ]);
    } else {
        ob_end_flush(); // normal exit - flush buffered JSON response
    }
});

require_once(__DIR__ . '/../../db_connect.php');

// Clear anything db_connect may have printed, then set header
ob_clean();
if (!headers_sent()) header('Content-Type: application/json');

// Catch non-fatal PHP errors (notices, warnings) and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only treat E_WARNING and above as fatal for our purposes
    if ($errno & (E_WARNING | E_ERROR | E_PARSE)) {
        ob_clean();
        echo json_encode([
            'success'  => false,
            'message'  => "PHP Error [$errno]: $errstr at line $errline"
        ]);
        exit;
    }
    return false; // let PHP handle notices normally (logged, not printed)
});

$debug_log = [];
function dlog($m) { global $debug_log; $debug_log[] = $m; }

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

try {
    // ── Validate DB connection ────────────────────────────────────────────────
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed. Check db_connect.php.');
    }

    // ── Input ─────────────────────────────────────────────────────────────────
    $section_id = intval($_POST['section_id'] ?? 0);
    if (!$section_id) throw new Exception('Missing section_id');
    dlog("section_id=$section_id");

    // ── Inspect schedules table ───────────────────────────────────────────────
    $cr = $conn->query("SHOW COLUMNS FROM schedules");
    if (!$cr) throw new Exception('Cannot inspect schedules table: ' . $conn->error);

    $cols = []; $nullable = []; $col_defaults = [];
    while ($c = $cr->fetch_assoc()) {
        $cols[]                  = $c['Field'];
        $nullable[$c['Field']]   = ($c['Null'] === 'YES');
        $col_defaults[$c['Field']] = $c['Default'];
    }
    dlog('Cols: ' . implode(', ', $cols));

    $has_day      = in_array('day_of_week',       $cols);
    $has_auto     = in_array('is_auto_generated',  $cols);
    $has_room     = in_array('room',               $cols);
    $date_ok_null = ($nullable['schedule_date']    ?? false);
    dlog("has_day=$has_day has_auto=$has_auto has_room=$has_room date_ok_null=$date_ok_null");

    // ── Load requirements ─────────────────────────────────────────────────────
    $r = $conn->prepare(
        "SELECT requirement_id, subject_id, faculty_id
         FROM subject_requirements WHERE section_id = ?"
    );
    if (!$r) throw new Exception('Prepare requirements: ' . $conn->error);
    $r->bind_param('i', $section_id);
    $r->execute();
    $reqs = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();

    if (empty($reqs)) throw new Exception('No subject requirements for this section.');
    dlog(count($reqs) . ' requirements loaded');

    // ── Load patterns ─────────────────────────────────────────────────────────
    $pats = [];
    foreach ($reqs as $req) {
        $rid = (int)$req['requirement_id'];
        $p = $conn->prepare(
            "SELECT sp.day_of_week, sp.time_slot_id, ts.start_time, ts.end_time
             FROM schedule_patterns sp
             JOIN time_slots ts ON sp.time_slot_id = ts.time_slot_id
             WHERE sp.requirement_id = ?
             ORDER BY ts.slot_order"
        );
        if (!$p) throw new Exception('Prepare patterns: ' . $conn->error);
        $p->bind_param('i', $rid);
        $p->execute();
        $pats[$rid] = $p->get_result()->fetch_all(MYSQLI_ASSOC);
        $p->close();

        if (empty($pats[$rid])) {
            throw new Exception("Requirement #$rid has no patterns. Set up patterns in Setup first.");
        }
        dlog("Req $rid: " . count($pats[$rid]) . ' pattern(s)');
    }

    // ── Transaction start ─────────────────────────────────────────────────────
    $conn->begin_transaction();

    // ── Delete old auto-generated rows for this section ───────────────────────
    $del = $conn->prepare(
        "DELETE FROM schedules WHERE section_id = ? AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')"
    );
    if (!$del) throw new Exception('Prepare delete: ' . $conn->error);
    $del->bind_param('i', $section_id);
    $del->execute();
    dlog('Deleted ' . $del->affected_rows . ' old auto rows');
    $del->close();

    // ── Real dates for the CURRENT Mon–Fri week ─────────────────────────────
    // Using real dates means view/delete queries (which filter by date range) work.
    // We anchor to this week's Monday so every regeneration uses a consistent,
    // predictable date that is easy to query (e.g. "next 7 days" or "this week").
    $monday = new DateTime();
    $dow = (int)$monday->format('N'); // 1=Mon, 7=Sun
    if ($dow !== 1) $monday->modify('-' . ($dow - 1) . ' days');
    $sentinels = [];
    $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    for ($i = 0; $i < 5; $i++) {
        $d = clone $monday;
        $d->modify("+$i days");
        $sentinels[$dayNames[$i]] = $d->format('Y-m-d');
    }
    dlog('Week dates: ' . implode(', ', $sentinels));

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

    $icols = ['faculty_id', 'subject_id', 'section_id'];
    $ivals = ['?', '?', '?'];
    $types = 'sii';

    // schedule_date
    $icols[] = 'schedule_date';
    // Always use real week dates (never NULL) so view/delete date-range queries work
    $ivals[] = '?';
    $types   .= 's';
    $date_ok_null = false; // force sentinel-date branch in bind_param logic below

    // start_time, end_time, time_slot_id, notes
    array_push($icols, 'start_time', 'end_time', 'time_slot_id', 'notes');
    array_push($ivals, '?', '?', '?', '?');
    $types .= 'ssis';

    // day_of_week (optional column)
    if ($has_day) {
        $icols[] = 'day_of_week';
        $ivals[] = '?';
        $types   .= 's';
    }

    // is_auto_generated (literal, no bind param)
    if ($has_auto) {
        $icols[] = 'is_auto_generated';
        $ivals[] = '1';
    }

    // room (literal empty string if NOT NULL with no default)
    if ($has_room && !$nullable['room'] && $col_defaults['room'] === null) {
        $icols[] = 'room';
        $ivals[] = "''";
        dlog('Adding empty room column (NOT NULL, no default)');
    }

    $sql = 'INSERT INTO schedules ('
         . implode(', ', $icols)
         . ') VALUES ('
         . implode(', ', $ivals) . ')';

    dlog('SQL: ' . $sql);
    dlog('Types: "' . $types . '" (' . strlen($types) . ' bound params)');

    $ins = $conn->prepare($sql);
    if (!$ins) throw new Exception('Prepare insert failed: ' . $conn->error . ' | SQL: ' . $sql);

    // ── Generate template entries Mon–Fri ─────────────────────────────────────
    $created   = 0;
    $conflicts = 0;
    $used_slots = [];  // "Day|slot_id" => true
    $used_fac   = [];  // [d, f, s, e] arrays

    foreach ($days as $day) {
        foreach ($reqs as $req) {
            $rid  = (int)$req['requirement_id'];
            $subj = (int)$req['subject_id'];
            $fac  = (string)($req['faculty_id'] ?? '');

            foreach ($pats[$rid] as $pat) {
                if ($pat['day_of_week'] !== $day) continue;

                $slot  = (int)$pat['time_slot_id'];
                $ts    = (string)$pat['start_time'];
                $te    = (string)$pat['end_time'];
                $note  = 'Auto-generated';
                $sdate = $sentinels[$day];

                // Slot conflict check
                $key = "$day|$slot";
                if (isset($used_slots[$key])) {
                    $conflicts++;
                    dlog("SKIP slot conflict: $key");
                    continue;
                }

                // Faculty time overlap check
                $fc = false;
                if ($fac !== '') {
                    foreach ($used_fac as $uf) {
                        if ($uf['d'] !== $day || $uf['f'] !== $fac) continue;
                        if ($uf['s'] < $te && $uf['e'] > $ts) { $fc = true; break; }
                    }
                }
                if ($fc) { $conflicts++; dlog("SKIP faculty conflict: $fac $day $ts"); continue; }

                // Build bind_param args array in exact SQL column order
                $args = [$types, $fac, $subj, $section_id];
                if (!$date_ok_null) $args[] = $sdate;   // schedule_date (only if bound)
                array_push($args, $ts, $te, $slot, $note); // start, end, slot, notes
                if ($has_day) $args[] = $day;            // day_of_week (only if bound)

                $ins->bind_param(...$args);

                if ($ins->execute()) {
                    $created++;
                    $used_slots[$key] = true;
                    if ($fac !== '') {
                        $used_fac[] = ['d' => $day, 'f' => $fac, 's' => $ts, 'e' => $te];
                    }
                    dlog("OK: $day subj=$subj slot=$slot fac='$fac'");
                } else {
                    dlog("FAIL insert: " . $ins->error . " | $day subj=$subj slot=$slot");
                }
            }
        }
    }

    $ins->close();
    $conn->commit();

    ob_clean();
    echo json_encode([
        'success'           => true,
        'schedules_created' => $created,
        'conflicts_found'   => $conflicts,
        'message'           => 'Weekly template generated successfully',
        'debug_log'         => $debug_log,
    ]);

} catch (Exception $e) {
    try { if (isset($conn) && $conn) $conn->rollback(); } catch (Exception $re) {}
    ob_clean();
    echo json_encode([
        'success'   => false,
        'message'   => $e->getMessage(),
        'debug_log' => $debug_log,
    ]);
}

if (isset($conn) && $conn) $conn->close();