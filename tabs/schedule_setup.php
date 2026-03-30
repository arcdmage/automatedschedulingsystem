<?php
require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../lib/subject_duration_helpers.php";
require_once __DIR__ . "/../lib/scheduler_staff_helpers.php";

// Get selected section from URL parameter - MUST BE DEFINED FIRST
$selected_section = isset($_GET["section_id"])
    ? intval($_GET["section_id"])
    : null;

// Fetch all sections
$sections_query =
    "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);

// Fetch subjects
$subjects_query =
    "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Fetch faculty
$faculty_rows = available_faculty_rows($conn);

// Fetch time slots for the selected section
$timeslots_result = null;
if ($selected_section) {
    $timeslots_query =
        "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
    $stmt = $conn->prepare($timeslots_query);
    $stmt->bind_param("i", $selected_section);
    $stmt->execute();
    $timeslots_result = $stmt->get_result();
    $stmt->close();
}

// Fetch requirements if section is selected
$requirements = [];
if ($selected_section) {
    $req_query = "SELECT sr.*, s.subject_name, COALESCE(CONCAT(f.lname, ', ', f.fname), 'Auto-assign in Quick Generate') AS teacher_name,
                (SELECT COUNT(*) FROM schedule_patterns WHERE requirement_id = sr.requirement_id) as pattern_count
                FROM subject_requirements sr
                JOIN subjects s ON sr.subject_id = s.subject_id
                LEFT JOIN faculty f ON sr.faculty_id = f.faculty_id
                WHERE sr.section_id = ?
                ORDER BY s.subject_name";
    $stmt = $conn->prepare($req_query);
    $stmt->bind_param("i", $selected_section);
    $stmt->execute();
    $requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function subject_duration_value(int $storedValue): array
{
    return split_subject_duration_minutes($storedValue);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule.css">
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule_setup.css">
</head>
<body>

<div class="page-header">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h1>Subject Setup</h1>
      <p>Configure subjects, teachers, and schedule patterns</p>
    </div>
  </div>
</div>

<!-- Section Selector -->
<div class="card-section">
  <label for="section-select">Select Section</label>
  <select id="section-select">
    <option value="">-- Choose a Section --</option>
    <option value="__create_new">+ Create New Section</option>
    <?php
    $sections_result->data_seek(0);
    while ($section = $sections_result->fetch_assoc()): ?>
      <option value="<?php echo $section["section_id"]; ?>"
              <?php echo $selected_section == $section["section_id"]
                  ? "selected"
                  : ""; ?>>
        <?php echo htmlspecialchars(
            $section["grade_level"] .
                " - " .
                $section["section_name"] .
                " (" .
                $section["track"] .
                ")",
        ); ?>
      </option>
    <?php endwhile;
    ?>
  </select>
</div>

<?php if ($selected_section): ?>

<!-- Quick Add Subject -->
<div class="card-section">
  <h2>➕ Add Subject</h2>
  <form id="quick-add-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">

    <div class="form-grid">
      <div class="form-group">
        <label>Subject <span style="color:red">*</span></label>
        <select name="subject_id" required>
          <option value="">-- Select Subject --</option>
          <?php
          $subjects_result->data_seek(0);
          while ($s = $subjects_result->fetch_assoc()): ?>
            <option value="<?php echo $s["subject_id"]; ?>">
              <?php echo htmlspecialchars($s["subject_name"]); ?>
            </option>
          <?php endwhile;
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Teacher</label>
        <select name="faculty_id">
          <option value="0">Auto-assign in Quick Generate</option>
          <?php
          foreach ($faculty_rows as $f): ?>
            <option value="<?php echo $f["faculty_id"]; ?>">
              <?php echo htmlspecialchars($f["lname"] . ", " . $f["fname"]); ?>
            </option>
          <?php endforeach;
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Hour Per Subject <span style="color:red">*</span></label>
        <input type="hidden" name="hours_per_week" id="quick_duration_total" value="60">
        <div style="display:flex; gap:10px;">
          <input type="number" id="quick_duration_hours" name="duration_hours" min="0" max="20" value="1" required style="flex:1;">
          <input type="number" id="quick_duration_minutes" name="duration_minutes" min="0" max="59" value="0" required style="flex:1;">
        </div>
        <small style="color:#666;">Enter hours and minutes, for example 1 hour and 30 minutes. Leave teacher on auto-assign to let Quick Generate pick from the Subject List specialists.</small>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Add Subject</button>
  </form>
</div>

<!-- Configured Subjects Table -->
<div class="card-section">
  <h2>📚 Configured Subjects</h2>
  <?php if (count($requirements) > 0): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th>Teacher</th>
          <th>Hour Per Subject</th>
          <th>Pattern Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requirements as $req): ?>
          <?php $req_duration = subject_duration_value(
              (int) $req["hours_per_week"],
          ); ?>
          <tr>
            <td><?php echo htmlspecialchars($req["subject_name"] ?? "Unknown subject"); ?></td>
            <td><?php echo htmlspecialchars($req["teacher_name"] ?? "Auto-assign in Quick Generate"); ?></td>
                <td><?php echo htmlspecialchars(
                    format_subject_duration_minutes(
                        $req_duration["total_minutes"],
                    ),
                ); ?></td>
            <td>
              <?php if ($req["pattern_count"] > 0): ?>
                <span style="color:#4CAF50;">✓ <?php echo $req[
                    "pattern_count"
                ]; ?> slots configured</span>
              <?php else: ?>
                <span style="color:#f44336;">⚠ Not configured</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int) $req["faculty_id"] > 0): ?>
                <button class="btn btn-primary" style="padding:5px 10px; font-size:12px;"
                        onclick="openPatternModal(<?php echo $req[
                            "requirement_id"
                        ]; ?>, '<?php echo htmlspecialchars(
    $req["subject_name"],
    ENT_QUOTES,
); ?>', <?php echo $req_duration["total_minutes"]; ?>, <?php echo $req[
    "faculty_id"
]; ?>)">
                  Configure Pattern
                </button>
              <?php else: ?>
                <span style="display:inline-block;padding:6px 10px;background:#fff7ed;color:#9a3412;border-radius:6px;font-size:12px;font-weight:600;">Quick Generate only</span>
              <?php endif; ?>
              <button class="btn btn-danger" style="padding:5px 10px; font-size:12px;"
                      onclick="deleteRequirement(<?php echo $req[
                          "requirement_id"
                      ]; ?>)">
                Delete
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-warning">No subjects configured yet. Add a subject above to get started.</div>
  <?php endif; ?>
</div>

<?php else: ?>
  <div class="alert alert-info">Please select a section above to start configuration.</div>
<?php endif; ?>

<!-- Create Section Modal -->
<div id="create-section-modal" class="modal">
  <div class="modal-content">
    <div class="imgcontainer" style="padding:15px; border-bottom:1px solid #ddd;">
      <span onclick="closeCreateSectionModal()" class="close" style="float:right; cursor:pointer; font-size:28px;">&times;</span>
      <h2 id="create-section-title">Create New Section</h2>
      <p style="color:#666; margin:5px 0 0 0;">Fill in the details for the new section</p>
    </div>

    <div style="padding:15px;">
      <form id="create-section-form">
        <div class="form-grid">
          <div class="form-group">
            <label>Section Name <span style="color:red">*</span></label>
            <input type="text" name="section_name" id="section_name" required placeholder="e.g. STEM VENUS">
          </div>

          <div class="form-group">
            <label>Grade Level <span style="color:red">*</span></label>
            <select name="grade_level" id="grade_level" required>
              <option value="">-- Select Grade Level --</option>
              <option value="Grade 11">Grade 11</option>
              <option value="Grade 12">Grade 12</option>
            </select>
          </div>

          <div class="form-group">
            <label>STRAND</label>
            <input type="text" name="track" id="track" placeholder="e.g. STEM, ABM">
          </div>

          <div class="form-group">
            <label>Advisor</label>
            <select name="adviser_id" id="adviser_id">
              <option value="">-- Select Advisor --</option>
              <?php
              foreach ($faculty_rows as $f): ?>
                <option value="<?php echo $f["faculty_id"]; ?>">
                  <?php echo htmlspecialchars(
                      $f["lname"] . ", " . $f["fname"],
                  ); ?>
                </option>
              <?php endforeach;
              ?>
            </select>
          </div>

          <div class="form-group">
            <label>Co-Advisor</label>
            <select name="co_adviser_id" id="co_adviser_id">
              <option value="">-- Select Co-Advisor --</option>
              <?php
              foreach ($faculty_rows as $f2): ?>
                <option value="<?php echo $f2["faculty_id"]; ?>">
                  <?php echo htmlspecialchars(
                      $f2["lname"] . ", " . $f2["fname"],
                  ); ?>
                </option>
              <?php endforeach;
              ?>
            </select>
          </div>
        </div>

        <div style="text-align:right; margin-top:15px;">
          <button type="button" onclick="closeCreateSectionModal()" class="btn" style="background:#e0e0e0;">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Section</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Pattern Configuration Modal -->
<div id="pattern-modal" class="modal">
  <div class="modal-content-large">
    <div class="imgcontainer" style="padding:15px; border-bottom:1px solid #ddd;">
      <span onclick="closePatternModal()" class="close" style="float:right; cursor:pointer; font-size:28px;">&times;</span>
      <h2 id="pattern-modal-title">Configure Schedule Pattern</h2>
      <p style="color:#666; margin:5px 0 0 0;">Select when this subject should be scheduled</p>
    </div>

    <div class="tab-buttons">
      <button class="tab-btn active" onclick="switchTab('pattern')">📅 Schedule Pattern</button>
      <button class="tab-btn" onclick="switchTab('details')">ℹ️ Subject Details</button>
    </div>

    <!-- Pattern Tab -->
    <div id="tab-pattern" class="tab-content active">
      <div id="pattern-grid-container"></div>
      <div class="hours-summary" id="hours-summary"></div>
      <div style="text-align:right; margin-top:20px;">
        <button onclick="closePatternModal()" class="btn" style="background:#e0e0e0;">Cancel</button>
        <button onclick="savePattern()" class="btn btn-primary">💾 Save Pattern</button>
      </div>
    </div>

    <!-- Details Tab -->
    <div id="tab-details" class="tab-content">
      <form id="update-form">
        <input type="hidden" id="update_requirement_id" name="requirement_id">
        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>Subject</label>
            <input type="text" id="update_subject" disabled style="background:#f5f5f5;">
          </div>
          <div class="form-group">
            <label>Teacher</label>
            <select name="faculty_id" id="update_faculty_id">
              <option value="0">Auto-assign in Quick Generate</option>
              <?php
              foreach ($faculty_rows as $f): ?>
                <option value="<?php echo $f["faculty_id"]; ?>">
                  <?php echo htmlspecialchars(
                      $f["lname"] . ", " . $f["fname"],
                  ); ?>
                </option>
              <?php endforeach;
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>Hour Per Subject *</label>
            <input type="hidden" name="hours_per_week" id="update_duration_total" value="60">
            <div style="display:flex; gap:10px;">
              <input type="number" name="duration_hours" id="update_hours" min="0" max="20" required style="flex:1;">
              <input type="number" name="duration_minutes" id="update_minutes" min="0" max="59" required style="flex:1;">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-success">💾 Update Details</button>
      </form>
    </div>

  </div>
</div>

<script>
let currentRequirementId = null;
let currentRequiredMinutes = 0;
let currentPerSubjectMinutes = 0;
let selectedSlots = new Set();
let timeSlots = <?php echo $timeslots_result
    ? json_encode($timeslots_result->fetch_all(MYSQLI_ASSOC))
    : "[]"; ?>;

function toWeeklyMinutes(minutesPerSubject) {
  return Math.max(0, Number(minutesPerSubject || 0)) * 5;
}

function formatDuration(minutes) {
  const totalMinutes = Math.max(0, Number(minutes || 0));
  const hours = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  const parts = [];

  if (hours > 0) parts.push(`${hours} hr${hours === 1 ? '' : 's'}`);
  if (mins > 0) parts.push(`${mins} min`);

  return parts.length ? parts.join(' ') : '0 min';
}

function getSelectedMinutes() {
  return selectedSlots.size * currentPerSubjectMinutes;
}

function syncDurationInput(hoursId, minutesId, totalId) {
  const hoursInput = document.getElementById(hoursId);
  const minutesInput = document.getElementById(minutesId);
  const totalInput = document.getElementById(totalId);
  if (!hoursInput || !minutesInput || !totalInput) return;

  const hours = Math.max(0, parseInt(hoursInput.value || '0', 10) || 0);
  const minutes = Math.max(0, parseInt(minutesInput.value || '0', 10) || 0);
  totalInput.value = (hours * 60) + minutes;
}

// Function to convert 24-hour time to 12-hour format
function formatTime12Hour(time24) {
  // time24 format: "HH:MM:SS" or "HH:MM"
  const timeParts = time24.split(':');
  let hours = parseInt(timeParts[0]);
  const minutes = timeParts[1];

  const ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  hours = hours ? hours : 12; // 0 should be 12

  return `${hours}:${minutes} ${ampm}`;
}

// Handle section dropdown change
function handleSectionChange(value) {
  if (!value) return;
  if (value === '__create_new') {
    openCreateSectionModal();
    // reset selection back to placeholder
    const sel = document.getElementById('section-select');
    sel.value = '';
    return;
  }
  window.location.href = '?section_id=' + encodeURIComponent(value);
}

// Open create section modal
function openCreateSectionModal() {
  const modal = document.getElementById('create-section-modal');
  if (!modal) return;
  modal.classList.add('open');
  modal.style.display = 'flex';
}

// Close create section modal
function closeCreateSectionModal() {
  const modal = document.getElementById('create-section-modal');
  const form = document.getElementById('create-section-form');
  if (modal) {
    modal.classList.remove('open');
    modal.style.display = 'none';
  }
  if (form) {
    form.reset();
  }
}

const sectionSelect = document.getElementById('section-select');
sectionSelect?.addEventListener('change', function() {
  handleSectionChange(this.value);
});

// Quick add form submission
document.getElementById('quick-add-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  try {
    const response = await fetch('/mainscheduler/tabs/actions/requirement_save.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      alert('Subject added! Now configure its schedule pattern.');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error adding subject');
  }
});

// Create section form submission
document.getElementById('create-section-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = this;
  const formData = new FormData(form);

  // Basic client validation
  const name = formData.get('section_name')?.trim();
  const grade = formData.get('grade_level')?.trim();
  if (!name || !grade) {
    alert('Please provide Section Name and Grade Level.');
    return;
  }

  try {
    const response = await fetch('/mainscheduler/tabs/actions/section_create.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success && data.section_id) {
      // Add to select dropdown
      const sel = document.getElementById('section-select');
      const opt = document.createElement('option');
      opt.value = data.section_id;
      const track = formData.get('track') ? formData.get('track') : '';
      opt.text = `${grade} - ${name}` + (track ? ` (${track})` : '');
      sel.appendChild(opt);

      // Close modal and navigate to new section
      closeCreateSectionModal();
      window.location.href = '?section_id=' + encodeURIComponent(data.section_id);
    } else {
      alert('Error creating section: ' + (data.message || 'Unknown error'));
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error creating section');
  }
});

// Delete requirement
function deleteRequirement(id) {
  if (!confirm('Delete this subject and its pattern configuration?')) return;

  const formData = new FormData();
  formData.append('requirement_id', id);

  fetch('/mainscheduler/tabs/actions/requirement_delete.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('Subject deleted successfully');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error deleting subject');
  });
}

// Open pattern modal
async function openPatternModal(requirementId, subjectName, hoursPerWeek, facultyId) {
  currentRequirementId = requirementId;
  currentPerSubjectMinutes = Math.max(0, Number(hoursPerWeek || 0));
  currentRequiredMinutes = toWeeklyMinutes(hoursPerWeek);
  selectedSlots.clear();

  document.getElementById('pattern-modal-title').textContent = `Configure Pattern: ${subjectName}`;
  document.getElementById('pattern-modal').style.display = 'block';

  // Set details tab values
  document.getElementById('update_requirement_id').value = requirementId;
  document.getElementById('update_subject').value = subjectName;
  document.getElementById('update_faculty_id').value = facultyId;
  document.getElementById('update_hours').value = Math.floor(hoursPerWeek / 60);
  document.getElementById('update_minutes').value = hoursPerWeek % 60;
  syncDurationInput('update_hours', 'update_minutes', 'update_duration_total');

  // Load existing pattern
  try {
    const response = await fetch(`/mainscheduler/tabs/actions/api_get_pattern.php?requirement_id=${requirementId}`);
    const existingPattern = await response.json();

    existingPattern.forEach(p => {
      selectedSlots.add(`${p.day_of_week}-${p.time_slot_id}`);
    });

    renderPatternGrid();

  } catch (error) {
    console.error('Error loading pattern:', error);
    renderPatternGrid();
  }
}

function renderPatternGrid() {
  const container = document.getElementById('pattern-grid-container');
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

  // Check if time slots are available
  if (!timeSlots || timeSlots.length === 0) {
    container.innerHTML = `
      <div class="alert alert-warning" style="margin:20px;">
        <strong>⚠️ No time slots configured!</strong>
        <p>This section doesn't have any time slots configured yet. You need to set up time slots before you can create schedule patterns.</p>
        <p><a href="/mainscheduler/tabs/manage_timeslots.php?section_id=<?php echo $selected_section; ?>"
           style="color:#856404; text-decoration:underline; font-weight:bold;">
          Go to Time Slot Manager →
        </a></p>
      </div>
    `;
    return;
  }

  let html = '<div class="pattern-grid">';

  // Headers
  html += '<div></div>';
  days.forEach(day => {
    html += `<div class="pattern-header">${day}</div>`;
  });

  // Time slots
  timeSlots.forEach(slot => {
    // Convert to 12-hour format
    const startTime = formatTime12Hour(slot.start_time);
    const endTime = formatTime12Hour(slot.end_time);
    html += `<div class="pattern-time">${startTime} - ${endTime}</div>`;

    days.forEach(day => {
      const key = `${day}-${slot.time_slot_id}`;
      const isBreak = slot.is_break == 1;
      const isSelected = selectedSlots.has(key);

      if (isBreak) {
        html += `<div class="pattern-cell">
          <div class="pattern-checkbox break-time">${slot.break_label || 'BREAK'}</div>
        </div>`;
      } else {
        html += `<div class="pattern-cell">
          <div class="pattern-checkbox ${isSelected ? 'selected' : ''}"
               onclick="toggleSlot('${key}')">
            ${isSelected ? '✓' : ''}
          </div>
        </div>`;
      }
    });
  });

  html += '</div>';
  container.innerHTML = html;

  updateHoursSummary();
}

function toggleSlot(key) {
  if (selectedSlots.has(key)) {
    selectedSlots.delete(key);
  } else {
    selectedSlots.add(key);
  }

  renderPatternGrid();
}

function updateHoursSummary() {
  const selected = getSelectedMinutes();
  const remaining = currentRequiredMinutes - selected;

  let html = `<strong>Selected:</strong> ${formatDuration(selected)} | <strong>Required:</strong> ${formatDuration(currentRequiredMinutes)} | `;

  if (remaining > 0) {
    html += `<span style="color:#f44336;">⚠ Please select ${remaining} more slot(s)</span>`;
  } else if (remaining < 0) {
    html += `<span style="color:#f44336;">⚠ You selected ${Math.abs(remaining)} too many</span>`;
  } else {
    html += `<span style="color:#4CAF50;">✓ Perfect match!</span>`;
  }

  document.getElementById('hours-summary').innerHTML = html;
}

async function savePattern() {
  if (selectedSlots.size === 0) {
    alert('Please select at least one time slot');
    return;
  }

  if (selectedSlots.size !== currentRequiredHours) {
    if (!confirm(`You selected ${selectedSlots.size} slots but need ${currentRequiredHours}. Continue anyway?`)) {
      return;
    }
  }

  const pattern = Array.from(selectedSlots).map(key => {
    const [day, slotId] = key.split('-');
    return { day_of_week: day, time_slot_id: parseInt(slotId) };
  });

  try {
    const response = await fetch('/mainscheduler/tabs/actions/pattern_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        requirement_id: currentRequirementId,
        pattern: pattern
      })
    });

    const data = await response.json();

    if (data.success) {
      alert('Pattern saved successfully!');
      closePatternModal();
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error saving pattern');
  }
}

function closePatternModal() {
  document.getElementById('pattern-modal').style.display = 'none';
  selectedSlots.clear();
}

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

  event.target.classList.add('active');
  document.getElementById('tab-' + tab).classList.add('active');
}

// Update form submission
document.getElementById('update-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  syncDurationInput('update_hours', 'update_minutes', 'update_duration_total');
  const formData = new FormData(this);

  try {
    const response = await fetch('/mainscheduler/tabs/actions/requirement_update.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      alert('Subject updated successfully!');
      closePatternModal();
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error updating subject');
  }
});

// Close modals when clicking outside
window.onclick = function(event) {
  if (event.target == document.getElementById('pattern-modal')) {
    closePatternModal();
  }
  if (event.target == document.getElementById('create-section-modal')) {
    closeCreateSectionModal();
  }
}

function openPatternModal(requirementId, subjectName, hoursPerSubject, facultyId) {
  currentRequirementId = requirementId;
  currentPerSubjectMinutes = Math.max(0, Number(hoursPerSubject || 0));
  currentRequiredMinutes = toWeeklyMinutes(hoursPerSubject);
  selectedSlots.clear();

  document.getElementById('pattern-modal-title').textContent = `Configure Pattern: ${subjectName}`;
  document.getElementById('pattern-modal').style.display = 'block';
  document.getElementById('update_requirement_id').value = requirementId;
  document.getElementById('update_subject').value = subjectName;
  document.getElementById('update_faculty_id').value = facultyId;
  document.getElementById('update_hours').value = Math.floor(hoursPerSubject / 60);
  document.getElementById('update_minutes').value = hoursPerSubject % 60;
  syncDurationInput('update_hours', 'update_minutes', 'update_duration_total');

  fetch(`/mainscheduler/tabs/actions/api_get_pattern.php?requirement_id=${requirementId}`)
    .then(response => response.json())
    .then(existingPattern => {
      existingPattern.forEach(p => {
        selectedSlots.add(`${p.day_of_week}-${p.time_slot_id}`);
      });
      renderPatternGrid();
    })
    .catch(error => {
      console.error('Error loading pattern:', error);
      renderPatternGrid();
    });
}

function updateHoursSummary() {
  const selected = getSelectedMinutes();
  const remaining = currentRequiredMinutes - selected;

  let html = `<strong>Selected:</strong> ${formatDuration(selected)} | <strong>Required:</strong> ${formatDuration(currentRequiredMinutes)} | `;

  if (remaining > 0) {
    html += `<span style="color:#f44336;">Please select ${formatDuration(remaining)} more</span>`;
  } else if (remaining < 0) {
    html += `<span style="color:#f44336;">You selected ${formatDuration(Math.abs(remaining))} too much</span>`;
  } else {
    html += `<span style="color:#4CAF50;">Perfect match!</span>`;
  }

  document.getElementById('hours-summary').innerHTML = html;
}

async function savePattern() {
  if (selectedSlots.size === 0) {
    alert('Please select at least one time slot');
    return;
  }

  const selectedMinutes = getSelectedMinutes();
  if (selectedMinutes !== currentRequiredMinutes) {
    if (!confirm(`You selected ${formatDuration(selectedMinutes)} but need ${formatDuration(currentRequiredMinutes)}. Continue anyway?`)) {
      return;
    }
  }

  const pattern = Array.from(selectedSlots).map(key => {
    const [day, slotId] = key.split('-');
    return { day_of_week: day, time_slot_id: parseInt(slotId, 10) };
  });

  try {
    const response = await fetch('/mainscheduler/tabs/actions/pattern_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        requirement_id: currentRequirementId,
        pattern: pattern
      })
    });

    const data = await response.json();

    if (data.success) {
      alert('Pattern saved successfully!');
      closePatternModal();
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error saving pattern');
  }
}

['quick_duration_hours', 'quick_duration_minutes'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', () => {
    syncDurationInput('quick_duration_hours', 'quick_duration_minutes', 'quick_duration_total');
  });
});

['update_hours', 'update_minutes'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', () => {
    syncDurationInput('update_hours', 'update_minutes', 'update_duration_total');
  });
});

syncDurationInput('quick_duration_hours', 'quick_duration_minutes', 'quick_duration_total');
</script>

</body>
</html>

