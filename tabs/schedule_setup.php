<?php
require_once(__DIR__ . '/../db_connect.php');

// Get selected section from URL parameter - MUST BE DEFINED FIRST
$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

// Fetch all sections
$sections_query = "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);

// Fetch subjects
$subjects_query = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Fetch faculty
$faculty_query = "SELECT faculty_id, fname, lname FROM faculty ORDER BY lname, fname";
$faculty_result = $conn->query($faculty_query);

// Fetch time slots for the selected section
$timeslots_result = null;
if ($selected_section) {
  $timeslots_query = "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
  $stmt = $conn->prepare($timeslots_query);
  $stmt->bind_param("i", $selected_section);
  $stmt->execute();
  $timeslots_result = $stmt->get_result();
  $stmt->close();
}

// Fetch requirements if section is selected
$requirements = [];
if ($selected_section) {
  $req_query = "SELECT sr.*, s.subject_name, CONCAT(f.lname, ', ', f.fname) AS teacher_name,
                (SELECT COUNT(*) FROM schedule_patterns WHERE requirement_id = sr.requirement_id) as pattern_count
                FROM subject_requirements sr
                JOIN subjects s ON sr.subject_id = s.subject_id
                JOIN faculty f ON sr.faculty_id = f.faculty_id
                WHERE sr.section_id = ?
                ORDER BY s.subject_name";
  $stmt = $conn->prepare($req_query);
  $stmt->bind_param("i", $selected_section);
  $stmt->execute();
  $requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/schedule.css">
<style>
.pattern-config {
  margin-top: 20px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.pattern-grid {
  display: grid;
  grid-template-columns: auto repeat(5, 1fr);
  gap: 5px;
  margin-top: 15px;
  background: white;
  padding: 15px;
  border-radius: 8px;
}

.pattern-header {
  font-weight: bold;
  text-align: center;
  padding: 10px;
  background: #4CAF50;
  color: white;
  border-radius: 4px;
}

.pattern-time {
  font-weight: bold;
  padding: 10px;
  background: #e8f5e9;
  border-radius: 4px;
  text-align: center;
  font-size: 11px;
}

.pattern-cell {
  position: relative;
}

.pattern-checkbox {
  width: 100%;
  height: 50px;
  border: 2px dashed #ddd;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.pattern-checkbox:hover {
  border-color: #4CAF50;
  background: #f1f8f4;
}

.pattern-checkbox.selected {
  background: #4CAF50;
  border-color: #4CAF50;
  color: white;
  font-weight: bold;
}

.pattern-checkbox.break-time {
  background: #f5f5f5;
  border-style: solid;
  cursor: not-allowed;
  color: #999;
}

.hours-summary {
  background: #fff3cd;
  padding: 10px 15px;
  border-radius: 6px;
  margin-top: 10px;
  font-weight: bold;
}

.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.4);
}

.modal-content-large {
  background-color: #fefefe;
  margin: 2% auto;
  padding: 0;
  border: 1px solid #888;
  width: 95%;
  max-width: 1200px;
  border-radius: 8px;
  max-height: 90vh;
  overflow-y: auto;
}

.tab-buttons {
  display: flex;
  border-bottom: 2px solid #ddd;
  background: #f8f9fa;
  border-radius: 8px 8px 0 0;
  overflow: hidden;
}

.tab-btn {
  flex: 1;
  padding: 15px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-weight: bold;
  color: #666;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #e9ecef;
}

.tab-btn.active {
  background: white;
  color: #4CAF50;
  border-bottom: 3px solid #4CAF50;
}

.tab-content {
  display: none;
  padding: 20px;
}

.tab-content.active {
  display: block;
}
</style>
</head>
<body>

<div class="page-header">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h1>Subject Setup</h1>
      <p>Configure subjects, teachers, and schedule patterns</p>
    </div>
    <a href="/mainscheduler/tabs/manage_timeslots.php" class="btn btn-secondary" style="text-decoration:none;">
      ⏰ Manage Time Slots
    </a>
  </div>
</div>

<!-- Section Selector -->
<div class="card-section">
  <label for="section-select">Select Section</label>
  <select id="section-select" onchange="window.location.href='?section_id='+this.value">
    <option value="">-- Choose a Section --</option>
    <?php 
    $sections_result->data_seek(0);
    while($section = $sections_result->fetch_assoc()): 
    ?>
      <option value="<?php echo $section['section_id']; ?>" 
              <?php echo ($selected_section == $section['section_id']) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($section['grade_level'] . ' - ' . $section['section_name'] . ' (' . $section['track'] . ')'); ?>
      </option>
    <?php endwhile; ?>
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
          while($s = $subjects_result->fetch_assoc()): 
          ?>
            <option value="<?php echo $s['subject_id']; ?>">
              <?php echo htmlspecialchars($s['subject_name']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label>Teacher <span style="color:red">*</span></label>
        <select name="faculty_id" required>
          <option value="">-- Select Teacher --</option>
          <?php 
          $faculty_result->data_seek(0);
          while($f = $faculty_result->fetch_assoc()): 
          ?>
            <option value="<?php echo $f['faculty_id']; ?>">
              <?php echo htmlspecialchars($f['lname'] . ', ' . $f['fname']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Hours / Week <span style="color:red">*</span></label>
        <input type="number" name="hours_per_week" min="1" max="20" value="1" required>
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
          <th>Hours/Week</th>
          <th>Pattern Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($requirements as $req): ?>
          <tr>
            <td><?php echo htmlspecialchars($req['subject_name']); ?></td>
            <td><?php echo htmlspecialchars($req['teacher_name']); ?></td>
            <td><?php echo $req['hours_per_week']; ?> hrs</td>
            <td>
              <?php if ($req['pattern_count'] > 0): ?>
                <span style="color:#4CAF50;">✓ <?php echo $req['pattern_count']; ?> slots configured</span>
              <?php else: ?>
                <span style="color:#f44336;">⚠ Not configured</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-primary" style="padding:5px 10px; font-size:12px;" 
                      onclick="openPatternModal(<?php echo $req['requirement_id']; ?>, '<?php echo htmlspecialchars($req['subject_name'], ENT_QUOTES); ?>', <?php echo $req['hours_per_week']; ?>, <?php echo $req['faculty_id']; ?>)">
                📅 Configure Pattern
              </button>
              <button class="btn btn-danger" style="padding:5px 10px; font-size:12px;" 
                      onclick="deleteRequirement(<?php echo $req['requirement_id']; ?>)">
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

<!-- Pattern Configuration Modal -->
<div id="pattern-modal" class="modal">
  <div class="modal-content-large">
    <div class="imgcontainer" style="padding:15px; border-bottom:1px solid #ddd;">
      <span onclick="closePatternModal()" class="close" style="float:right; cursor:pointer; font-size:28px;">&times;</span>
      <h2 id="pattern-modal-title">Configure Schedule Pattern</h2>
      <p style="color:#666; margin:5px 0 0 0;">Select when this subject should be scheduled each week</p>
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
            <label>Teacher *</label>
            <select name="faculty_id" id="update_faculty_id" required>
              <option value="">-- Select Teacher --</option>
              <?php 
              $faculty_result->data_seek(0);
              while($f = $faculty_result->fetch_assoc()): 
              ?>
                <option value="<?php echo $f['faculty_id']; ?>">
                  <?php echo htmlspecialchars($f['lname'] . ', ' . $f['fname']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Hours/Week *</label>
            <input type="number" name="hours_per_week" id="update_hours" min="1" max="20" required>
          </div>
        </div>
        <button type="submit" class="btn btn-success">💾 Update Details</button>
      </form>
    </div>

  </div>
</div>

<script>
let currentRequirementId = null;
let currentRequiredHours = 0;
let selectedSlots = new Set();
let timeSlots = <?php echo $timeslots_result ? json_encode($timeslots_result->fetch_all(MYSQLI_ASSOC)) : '[]'; ?>;

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

// Quick add form submission
document.getElementById('quick-add-form').addEventListener('submit', async function(e) {
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
  currentRequiredHours = hoursPerWeek;
  selectedSlots.clear();
  
  document.getElementById('pattern-modal-title').textContent = `Configure Pattern: ${subjectName}`;
  document.getElementById('pattern-modal').style.display = 'block';
  
  // Set details tab values
  document.getElementById('update_requirement_id').value = requirementId;
  document.getElementById('update_subject').value = subjectName;
  document.getElementById('update_faculty_id').value = facultyId;
  document.getElementById('update_hours').value = hoursPerWeek;
  
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
  const selected = selectedSlots.size;
  const remaining = currentRequiredHours - selected;
  
  let html = `<strong>Selected:</strong> ${selected} slots | <strong>Required:</strong> ${currentRequiredHours} hrs | `;
  
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
document.getElementById('update-form').addEventListener('submit', async function(e) {
  e.preventDefault();
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

// Close modal when clicking outside
window.onclick = function(event) {
  if (event.target == document.getElementById('pattern-modal')) {
    closePatternModal();
  }
}
</script>

</body>
</html>