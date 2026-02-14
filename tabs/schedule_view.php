<?php
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/../lib/schedule_helpers.php');

$sections_query = "SELECT s.section_id, s.section_name, s.grade_level, s.track, s.school_year, s.semester,
                   CONCAT(f.lname, ', ', f.fname) AS adviser_name
                   FROM sections s
                   LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                   ORDER BY s.grade_level, s.section_name";
$sections_result = $conn->query($sections_query);

$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$selected_week = isset($_GET['week']) ? $_GET['week'] : null;

// Default to current week if not set
if (!$selected_week) {
  $now = new DateTime();
  $selected_week = $now->format('o-\WW');
}

// fetch selected section details
$section_details = null;
if ($selected_section) {
  $stmt = $conn->prepare("SELECT s.*, CONCAT(f.lname, ', ', f.fname) AS adviser_name FROM sections s LEFT JOIN faculty f ON s.adviser_id = f.faculty_id WHERE s.section_id = ?");
  $stmt->bind_param("i", $selected_section);
  $stmt->execute();
  $section_details = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// calculate date range for the selected week
try {
  $selected_week_clean = strtoupper($selected_week);
  if (strpos($selected_week_clean, 'W') !== false) {
      $week_parts = explode('W', $selected_week_clean);
  } else {
      $week_parts = explode('-', $selected_week_clean);
  }

  $year = (int)str_replace('-', '', $week_parts[0]);
  $week_num = (int)$week_parts[1];

  $week_date = new DateTime();
  $week_date->setISODate($year, $week_num, 1); // Monday
  $week_start_str = $week_date->format('Y-m-d');
  $week_end = clone $week_date;
  $week_end->modify('+4 days'); // Friday
  $week_end_str = $week_end->format('Y-m-d');
} catch (Exception $e) {
  $week_start_str = date('Y-m-d', strtotime('monday this week'));
  $week_end_str = date('Y-m-d', strtotime('friday this week'));
}

// setup grid - time slots (section-specific)
$all_time_slots = [];
if ($selected_section) {
  $timeslots_query = "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
  $stmt = $conn->prepare($timeslots_query);
  $stmt->bind_param("i", $selected_section);
  $stmt->execute();
  $all_time_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Build mappings: slot_order <-> time_slot_id and time labels
$slotOrderByTimeId = [];
$timeIdBySlotOrder = [];
$timeLabelBySlotOrder = [];
foreach ($all_time_slots as $slot) {
    $so = (int)$slot['slot_order'];
    $tid = (int)$slot['time_slot_id'];
    $slotOrderByTimeId[$tid] = $so;
    $timeIdBySlotOrder[$so] = $tid;
    $timeLabelBySlotOrder[$so] = $slot['start_time'] . ' - ' . $slot['end_time'];
}

// Build list of week dates and map DayName => date (Monday..Friday)
$week_dates = [];
$dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$dt = new DateTime($week_start_str);
foreach ($dayNames as $i => $day) {
    $week_dates[$day] = $dt->format('Y-m-d');
    $dt->modify('+1 day');
}

// Use helper to build weekly grid (patterns + explicit schedules). grid[date][slot_order] => info
$grid = [];
if ($selected_section) {
    $grid = build_weekly_grid($conn, $selected_section, $week_start_str);
}

// Collect subject IDs used in grid to fetch names
$subjectIds = [];
foreach ($grid as $date => $slots) {
    foreach ($slots as $slotOrder => $info) {
        if (!empty($info['subject_id'])) $subjectIds[] = (int)$info['subject_id'];
    }
}
$subjectIds = array_values(array_unique($subjectIds));

// Fetch subject names
$subjects = [];
if (!empty($subjectIds)) {
    // safe integer list
    $ids = implode(',', array_map('intval', $subjectIds));
    $sql = "SELECT subject_id, subject_name FROM subjects WHERE subject_id IN ($ids)";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) $subjects[(int)$r['subject_id']] = $r['subject_name'];
}

// Fetch explicit schedules for week to get teacher names (map by date & slot_order)
$teachers = []; // teachers[date][slot_order] = "Lastname, Firstname"
if ($selected_section) {
    $sql = "SELECT s.schedule_date, s.time_slot_id, f.lname, f.fname
            FROM schedules s
            LEFT JOIN faculty f ON s.faculty_id = f.faculty_id
            WHERE s.section_id = ? AND s.schedule_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $selected_section, $week_start_str, $week_end_str);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $date = $r['schedule_date'];
        $tid = (int)$r['time_slot_id'];
        $slotOrder = $slotOrderByTimeId[$tid] ?? null;
        if ($slotOrder !== null) {
            $teachers[$date][$slotOrder] = trim(($r['lname'] ?? '') . ', ' . ($r['fname'] ?? ''));
        }
    }
    $stmt->close();
}

// Function to format time in 12-hour format
function formatTime12Hour($time24) {
  $timestamp = strtotime($time24);
  return date('g:i A', $timestamp);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/schedule.css">
</head>
<body>

<div class="page-header">
  <div>
    <h1>Weekly View</h1>
    <p>View and print class schedules</p>
  </div>
</div>

<!-- Controls -->
<div class="card-section">
  <div class="controls-row">
    <div style="flex: 1;">
      <label>Section</label>
      <select id="section-select" onchange="loadSchedule()">
        <option value="">-- Select --</option>
        <?php 
        if ($sections_result) {
            $sections_result->data_seek(0);
            while($sec = $sections_result->fetch_assoc()): ?>
              <option value="<?php echo $sec['section_id']; ?>" 
                      <?php echo ($selected_section == $sec['section_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sec['section_name']); ?>
              </option>
            <?php endwhile; 
        } ?>
      </select>
    </div>
    
    <div style="flex: 1;">
      <label>Week</label>
      <input type="week" id="week-select" value="<?php echo htmlspecialchars($selected_week); ?>" onchange="loadSchedule()">
    </div>
    
    <div>
      <?php if ($selected_section): ?>
        <button class="btn btn-success btn-print" onclick="window.print()">🖨️ Print</button>
        <button class="btn btn-danger btn-delete" onclick="document.getElementById('delete-modal').style.display='block'">🗑️ Delete Auto</button>
      <?php else: ?>
        <button class="btn" disabled style="background:#eee; color:#999; cursor:not-allowed;">Select Section First</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($selected_section && $section_details): ?>

<div class="card-section printable">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
    <h2 style="border:none; margin:0;"><?php echo htmlspecialchars($section_details['section_name']); ?></h2>
    <span style="color:#666; font-weight:bold;"><?php echo date('M d', strtotime($week_start_str)); ?> - <?php echo date('M d, Y', strtotime($week_end_str)); ?></span>
  </div>
  
  <table class="schedule-grid">
    <thead>
      <tr>
        <th>TIME</th>
        <?php foreach($dayNames as $day) echo "<th>" . strtoupper($day) . "</th>"; ?>
      </tr>
    </thead>
    <tbody>
      <?php
      // Determine max slot_order to iterate in display order
      $maxSlotOrder = empty($timeLabelBySlotOrder) ? 0 : max(array_keys($timeLabelBySlotOrder));
      for ($slot = 1; $slot <= $maxSlotOrder; $slot++):
          $isBreak = false;
          // find corresponding time slot entry to check is_break and label
          $timeSlotEntry = null;
          foreach ($all_time_slots as $ts) {
              if ((int)$ts['slot_order'] === $slot) { $timeSlotEntry = $ts; break; }
          }
          if ($timeSlotEntry && (int)$timeSlotEntry['is_break']) {
              $isBreak = true;
          }
      ?>
        <tr>
          <td class="time-cell">
            <?php
              if ($timeSlotEntry) {
                echo formatTime12Hour($timeSlotEntry['start_time']) . ' - ' . formatTime12Hour($timeSlotEntry['end_time']);
              } else {
                echo "Slot $slot";
              }
            ?>
          </td>
          
          <?php if ($isBreak): ?>
            <td colspan="5" class="break-cell"><?php echo htmlspecialchars($timeSlotEntry['break_label'] ?? 'BREAK'); ?></td>
          <?php else: ?>
            <?php foreach ($dayNames as $day): 
                $date = $week_dates[$day];
                $cell = $grid[$date][$slot] ?? null;
            ?>
              <?php if ($cell): 
                  $sname = $subjects[$cell['subject_id']] ?? ("Subject #" . ($cell['subject_id'] ?? ''));
                  $teacher = ($cell['source'] === 'explicit') ? ($teachers[$date][$slot] ?? '') : '';
              ?>
                <td class="subject-cell">
                  <div class="subject-name"><?php echo htmlspecialchars($sname); ?></div>
                  <?php if ($teacher): ?>
                    <div class="teacher-name"><?php echo htmlspecialchars($teacher); ?></div>
                  <?php elseif ($cell['source'] === 'pattern'): ?>
                    <div class="teacher-name" style="color:#4b5563; font-size:0.9em;">(pattern)</div>
                  <?php endif; ?>
                </td>
              <?php else: ?>
                <td class="empty-cell">-</td>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div id="delete-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999;">
  <div style="background:white; width:400px; margin:100px auto; padding:25px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.2);">
    <h3 style="margin-top:0;">Confirm Delete</h3>
    <p>Are you sure you want to delete all generated schedules for this week?</p>
    <div style="text-align:right; margin-top:20px;">
      <button onclick="document.getElementById('delete-modal').style.display='none'" class="btn" style="background:#eee;">Cancel</button>
      <button onclick="deleteSchedules()" class="btn btn-danger">Confirm Delete</button>
    </div>
  </div>
</div>

<?php else: ?>
  <div class="alert alert-warning">⚠️ Please select a section to view the schedule.</div>
<?php endif; ?>

<script>
function loadSchedule() {
  const sec = document.getElementById('section-select').value;
  const week = document.getElementById('week-select').value;
  if(sec) window.location.href = `?section_id=${sec}&week=${week}`;
}

function deleteSchedules() {
  const sectionId = '<?php echo $selected_section; ?>';
  const startDate = '<?php echo $week_start_str; ?>';
  const endDate = '<?php echo $week_end_str; ?>';

  const runDelete = (force = false) => {
    const formData = new FormData();
    formData.append('section_id', sectionId);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    if (force) formData.append('force', '1');

    fetch('/mainscheduler/tabs/actions/schedule_delete_auto.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    })
    .then(async res => {
      if (!res.ok) {
        const txt = await res.text();
        throw new Error('Server error: ' + txt);
      }
      return res.json();
    })
    .then(data => {
      if (!data.success) {
        alert('Error: ' + (data.message || 'Unknown'));
        return;
      }
      if (data.deleted_schedules === 0 && data.matched_count > 0 && data.auto_count === 0 && !force) {
        // nothing auto-marked; ask user if they want to force-delete all matched rows
        const proceed = confirm(`Found ${data.matched_count} schedules in the range but none are marked as auto-generated.\n\nDo you want to FORCE delete all ${data.matched_count} matched schedules?`);
        if (proceed) runDelete(true);
        else alert('No schedules were deleted.');
        return;
      }
      alert(data.message || 'Done');
      if (data.deleted_schedules > 0) window.location.reload();
    })
    .catch(err => {
      console.error(err);
      alert('Error: ' + err.message);
    });
  };

  if (!sectionId) return alert('Select a section first.');
  if (!confirm('Are you sure you want to delete generated schedules for this week?')) return;
  runDelete(false);
}
</script>

</body>
</html>