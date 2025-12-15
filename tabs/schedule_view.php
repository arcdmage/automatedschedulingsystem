<?php
require_once(__DIR__ . '/../db_connect.php');

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

// setup grid - time slots
$all_time_slots = $conn->query("SELECT * FROM time_slots ORDER BY slot_order")->fetch_all(MYSQLI_ASSOC);
$schedule_grid = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

foreach ($all_time_slots as $time_slot) {
  if (!$time_slot['is_break']) {
    $slot_id = (int)$time_slot['time_slot_id'];
    $schedule_grid[$slot_id] = [];
    foreach ($days as $day) $schedule_grid[$slot_id][$day] = null;
  }
}

// 6. FETCH SCHEDULES
if ($selected_section) {
  $has_ts_id = $conn->query("SHOW COLUMNS FROM schedules LIKE 'time_slot_id'")->num_rows > 0;
  
  $sql = "SELECT s.*, sub.subject_name, 
          CONCAT(f.lname, ', ', f.fname) AS teacher_name
          FROM schedules s
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN faculty f ON s.faculty_id = f.faculty_id
          WHERE s.section_id = ? 
          AND s.schedule_date BETWEEN ? AND ?
          ORDER BY s.schedule_date, s.start_time";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iss", $selected_section, $week_start_str, $week_end_str);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $day_name = date('l', strtotime($row['schedule_date']));
    if (!in_array($day_name, $days)) continue;

    $matched = false;
    
    // Method 1: Match by Time Slot ID
    if ($has_ts_id && !empty($row['time_slot_id'])) {
        $tid = (int)$row['time_slot_id'];
        if (isset($schedule_grid[$tid])) {
            $schedule_grid[$tid][$day_name] = [
                'subject' => $row['subject_name'],
                'teacher' => $row['teacher_name']
            ];
            $matched = true;
        }
    }

    // Method 2: Match by Time (Fallback)
    if (!$matched) {
        $db_time_str = date('H:i', strtotime($row['start_time']));
        foreach ($all_time_slots as $slot) {
            if ($slot['is_break']) continue;
            $slot_time_str = date('H:i', strtotime($slot['start_time']));
            if ($db_time_str === $slot_time_str) {
                $tid = (int)$slot['time_slot_id'];
                if (isset($schedule_grid[$tid])) {
                    $schedule_grid[$tid][$day_name] = [
                        'subject' => $row['subject_name'],
                        'teacher' => $row['teacher_name']
                    ];
                    $matched = true;
                    break;
                }
            }
        }
    }
  }
  $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- LINK TO NEW CSS -->
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
        <?php foreach($days as $day) echo "<th>" . strtoupper($day) . "</th>"; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($all_time_slots as $slot): ?>
        <tr>
          <td class="time-cell">
            <?php echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])); ?>
          </td>
          
          <?php if ($slot['is_break']): ?>
            <td colspan="5" class="break-cell"><?php echo htmlspecialchars($slot['break_label']); ?></td>
          <?php else: ?>
            <?php 
            $tid = (int)$slot['time_slot_id'];
            foreach ($days as $day): 
              $cell = $schedule_grid[$tid][$day] ?? null;
            ?>
              <?php if ($cell): ?>
                <td class="subject-cell">
                  <div class="subject-name"><?php echo htmlspecialchars($cell['subject']); ?></div>
                  <div class="teacher-name"><?php echo htmlspecialchars($cell['teacher']); ?></div>
                </td>
              <?php else: ?>
                <td class="empty-cell">-</td>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
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

  const formData = new FormData();
  formData.append('section_id', sectionId);
  formData.append('start_date', startDate);
  formData.append('end_date', endDate);

  fetch('/mainscheduler/tabs/actions/schedule_delete_auto.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
    if(data.success) window.location.reload();
  })
  .catch(err => alert("Error: " + err));
}
</script>

</body>
</html>