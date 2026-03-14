<?php
ini_set('display_errors', '0');
ini_set('html_errors',    '0');
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false,
            'message' => 'Fatal: ' . $err['message'] . ' (line ' . $err['line'] . ')']);
    } else {
        ob_end_flush();
    }
});

require_once(__DIR__ . '/../../db_connect.php');
ob_clean();
if (!headers_sent()) header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if ($errno & (E_WARNING | E_ERROR)) {
        ob_clean();
        echo json_encode(['success' => false,
            'message' => "PHP Error [$errno]: $errstr at line $errline"]);
        exit;
    }
    return false;
});

$debug_log = [];
function dlog($m) { global $debug_log; $debug_log[] = $m; }

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

try {
    if (!isset($conn) || !$conn) throw new Exception('DB connection failed.');

    $section_id = intval($_POST['section_id'] ?? 0);
    if (!$section_id) throw new Exception('Missing section_id.');

    // ── Inspect schedules table columns ───────────────────────────────────────
    $cr = $conn->query("SHOW COLUMNS FROM schedules");
    if (!$cr) throw new Exception('Cannot inspect schedules table: ' . $conn->error);

    $cols = []; $nullable = []; $col_defaults = [];
    while ($c = $cr->fetch_assoc()) {
        $cols[]                    = $c['Field'];
        $nullable[$c['Field']]     = ($c['Null'] === 'YES');
        $col_defaults[$c['Field']] = $c['Default'];
    }

    $has_day      = in_array('day_of_week',       $cols);
    $has_auto     = in_array('is_auto_generated',  $cols);
    $has_room     = in_array('room',               $cols);
    $date_ok_null = ($nullable['schedule_date']    ?? false);

    dlog("Cols: has_day=$has_day has_auto=$has_auto date_ok_null=$date_ok_null");

    // ── Load requirements (subject + faculty + hours needed) ──────────────────
    $r = $conn->prepare(
        "SELECT sr.requirement_id, sr.subject_id, sr.faculty_id, sr.hours_per_week,
                subj.subject_name
         FROM subject_requirements sr
         JOIN subjects subj ON sr.subject_id = subj.subject_id
         WHERE sr.section_id = ?"
    );
    if (!$r) throw new Exception('Prepare requirements: ' . $conn->error);
    $r->bind_param('i', $section_id);
    $r->execute();
    $reqs = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();

    if (empty($reqs)) throw new Exception('No subject requirements for this section.');
    dlog(count($reqs) . ' requirements loaded');

    // ── Load non-break time slots for this section ────────────────────────────
    $ts_stmt = $conn->prepare(
        "SELECT time_slot_id, start_time, end_time, slot_order
         FROM time_slots
         WHERE section_id = ? AND (is_break = 0 OR is_break IS NULL)
         ORDER BY slot_order"
    );
    if (!$ts_stmt) throw new Exception('Prepare time slots: ' . $conn->error);
    $ts_stmt->bind_param('i', $section_id);
    $ts_stmt->execute();
    $time_slots = $ts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ts_stmt->close();

    if (empty($time_slots)) {
        throw new Exception(
            'No time slots found for this section. ' .
            'Add time slots in Setup before generating a random schedule.'
        );
    }
    dlog(count($time_slots) . ' non-break time slots available');

    // ── Build the full pool of (day, slot) pairs ──────────────────────────────
    // Each entry: ['day'=>'Monday', 'slot_id'=>3, 'start'=>'07:30:00', 'end'=>'08:30:00']
    $pool = [];
    foreach ($days as $day) {
        foreach ($time_slots as $ts) {
            $pool[] = [
                'day'      => $day,
                'slot_id'  => (int)$ts['time_slot_id'],
                'start'    => $ts['start_time'],
                'end'      => $ts['end_time'],
            ];
        }
    }

    $total_slots_available = count($pool);
    $total_hours_needed    = array_sum(array_column($reqs, 'hours_per_week'));
    dlog("Pool size: $total_slots_available | Hours needed: $total_hours_needed");

    if ($total_hours_needed > $total_slots_available) {
        throw new Exception(
            "Not enough time slots: need $total_hours_needed slots but only " .
            "$total_slots_available are available (Mon–Fri × " . count($time_slots) . " slots). " .
            "Reduce hours per week or add more time slots."
        );
    }

    $conn->begin_transaction();

    $del = $conn->prepare(
        "DELETE FROM schedules WHERE section_id = ? AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%')"
    );
    if (!$del) throw new Exception('Prepare delete: ' . $conn->error);
    $del->bind_param('i', $section_id);
    $del->execute();
    dlog('Deleted ' . $del->affected_rows . ' old auto rows');
    $del->close();

    $monday = new DateTime();
    $dow = (int)$monday->format('N');
    if ($dow !== 1) $monday->modify('-' . ($dow - 1) . ' days');
    $sentinels = [];
    $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    for ($i = 0; $i < 5; $i++) {
        $d = clone $monday;
        $d->modify("+$i days");
        $sentinels[$dayNames[$i]] = $d->format('Y-m-d');
    }
    dlog('Week dates: ' . implode(', ', $sentinels));

    $icols = ['faculty_id', 'subject_id', 'section_id'];
    $ivals = ['?', '?', '?'];
    $types = 'sii';

    $icols[] = 'schedule_date';
    // Always use real week dates so view/delete date-range queries work
    $ivals[] = '?';
    $types   .= 's';
    $date_ok_null = false; // force real-date branch in bind_param logic

    array_push($icols, 'start_time', 'end_time', 'time_slot_id', 'notes');
    array_push($ivals, '?', '?', '?', '?');
    $types .= 'ssis';

    if ($has_day) {
        $icols[] = 'day_of_week';
        $ivals[] = '?';
        $types   .= 's';
    }
    if ($has_auto) {
        $icols[] = 'is_auto_generated';
        $ivals[] = '1';
    }
    if ($has_room && !$nullable['room'] && $col_defaults['room'] === null) {
        $icols[] = 'room';
        $ivals[] = "''";
    }

    $sql = 'INSERT INTO schedules ('
         . implode(', ', $icols) . ') VALUES ('
         . implode(', ', $ivals) . ')';

    dlog('SQL: ' . $sql);
    dlog('Types: "' . $types . '" (' . strlen($types) . ' params)');

    $ins = $conn->prepare($sql);
    if (!$ins) throw new Exception('Prepare insert: ' . $conn->error . ' | SQL: ' . $sql);

    $used_slots = [];   // "Day|slot_id" => true
    $used_fac   = [];   // [d, f, s, e]

    $created   = 0;
    $skipped   = 0;

    shuffle($reqs);

    foreach ($reqs as $req) {
        $subj       = (int)$req['subject_id'];
        $fac        = (string)($req['faculty_id'] ?? '');
        $hours      = (int)$req['hours_per_week'];
        $subj_name  = $req['subject_name'];

        dlog("Subject: $subj_name | need $hours slots | faculty='$fac'");
        $candidates = [];
        foreach ($pool as $entry) {
            $key = $entry['day'] . '|' . $entry['slot_id'];
            if (isset($used_slots[$key])) continue; // slot taken

            // Faculty overlap check
            $fc = false;
            if ($fac !== '') {
                foreach ($used_fac as $uf) {
                    if ($uf['d'] !== $entry['day'] || $uf['f'] !== $fac) continue;
                    if ($uf['s'] < $entry['end'] && $uf['e'] > $entry['start']) {
                        $fc = true; break;
                    }
                }
            }
            if (!$fc) $candidates[] = $entry;
        }

        if (count($candidates) < $hours) {
            $available = count($candidates);
            dlog("WARNING: $subj_name needs $hours slots but only $available available — assigning $available");
            $hours = $available; // assign as many as possible
            $skipped += ((int)$req['hours_per_week'] - $available);
        }

        shuffle($candidates);
        $chosen = array_slice($candidates, 0, $hours);

        foreach ($chosen as $entry) {
            $day   = $entry['day'];
            $slot  = $entry['slot_id'];
            $ts    = $entry['start'];
            $te    = $entry['end'];
            $note  = 'Auto-generated';
            $sdate = $sentinels[$day];
            $key   = "$day|$slot";

            $args = [$types, $fac, $subj, $section_id];
            if (!$date_ok_null) $args[] = $sdate;
            array_push($args, $ts, $te, $slot, $note);
            if ($has_day) $args[] = $day;

            $ins->bind_param(...$args);

            if ($ins->execute()) {
                $created++;
                $used_slots[$key] = true;
                if ($fac !== '') {
                    $used_fac[] = ['d' => $day, 'f' => $fac, 's' => $ts, 'e' => $te];
                }
                dlog("  OK: $day slot=$slot for $subj_name");
            } else {
                dlog("  FAIL insert: " . $ins->error);
                $skipped++;
            }
        }
    }

    $ins->close();
    $conn->commit();

    ob_clean();
    echo json_encode([
        'success'           => true,
        'schedules_created' => $created,
        'conflicts_found'   => $skipped,
        'message'           => "Random schedule generated: $created slots assigned" .
                               ($skipped > 0 ? ", $skipped could not be placed" : ''),
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