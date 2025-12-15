<?php
require_once(__DIR__ . '/../db_connect.php');

// Fetch all faculty members for dropdown
$faculty_query = "SELECT faculty_id, fname, mname, lname FROM faculty ORDER BY lname, fname";
$faculty_result = $conn->query($faculty_query);

// Fetch all subjects for dropdown
$subjects_query = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Fetch all sections for automation
$sections_query = "SELECT section_id, section_name, grade_level, track, school_year, semester FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule.css">
<style>
/* Additional styles for integrated view */
.schedule-mode-tabs {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.mode-tab {
  flex: 1;
  padding: 15px;
  text-align: center;
  cursor: pointer;
  border: none;
  background: white;
  font-weight: bold;
  color: #666;
  transition: all 0.3s;
  border-right: 1px solid #eee;
}

.mode-tab:last-child {
  border-right: none;
}

.mode-tab:hover {
  background: #f5f5f5;
}

.mode-tab.active {
  background: #4CAF50;
  color: white;
}

.mode-content {
  display: none;
}

.mode-content.active {
  display: block;
}

.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}

.stat-card h3 {
  margin: 0 0 10px 0;
  font-size: 14px;
  opacity: 0.9;
}

.stat-card .number {
  font-size: 32px;
  font-weight: bold;
}
</style>
</head>

<body>

<h1>Schedule Management</h1>
<p>Create and manage teacher schedules, classes, and events.</p>

<!-- Mode Selection Tabs -->
<div class="schedule-mode-tabs">
  <button class="mode-tab active" onclick="switchMode('manual')"> Manual Entry</button>
  <button class="mode-tab" onclick="switchMode('setup')"> Subject Setup</button>
  <button class="mode-tab" onclick="switchMode('generate')"> Auto Generate</button>
  <button class="mode-tab" onclick="switchMode('view')"> Weekly View</button>
</div>

<!-- Manual Entry Mode (Updated Layout) -->
<div id="mode-manual" class="mode-content active">
  
  <!-- Action Buttons -->
  <div class="card-section">
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
      <button onclick="openScheduleModal()" class="btn btn-primary" style="flex: 1;">
        + Add Class Schedule
      </button>
      <button onclick="openEventModal()" class="btn btn-success" style="flex: 1;">
        + Add Event/Meeting
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card-section">
    <div class="form-grid">
      
      <div class="form-group">
        <label for="view-type">View Mode</label>
        <select id="view-type" onchange="changeView()">
          <option value="all">All Teachers</option>
          <option value="specific">Specific Teacher</option>
        </select>
      </div>

      <div class="form-group" id="teacher-filter-group" style="display:none;">
        <label for="teacher-filter">Select Teacher</label>
        <select id="teacher-filter" onchange="loadCalendar()">
          <option value="">-- Select Teacher --</option>
          <?php 
          $faculty_result->data_seek(0);
          while($faculty = $faculty_result->fetch_assoc()): 
          ?>
            <option value="<?php echo $faculty['faculty_id']; ?>">
              <?php echo htmlspecialchars($faculty['lname'] . ', ' . $faculty['fname']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="month-select">Month</label>
        <input type="month" id="month-select" onchange="loadCalendar()">
      </div>

    </div>
  </div>

  <!-- Calendar Container -->
  <div id="calendar-container" class="card-section" style="padding: 0; overflow: hidden;">
    <!-- Calendar loads here via AJAX -->
  </div>
</div>

<!-- Subject Setup Mode -->
<div id="mode-setup" class="mode-content">
  <iframe src="/mainscheduler/tabs/schedule_setup.php" 
          style="width:100%; height:800px; border:none; border-radius:8px;">
  </iframe>
</div>

<!-- Auto Generate Mode -->
<div id="mode-generate" class="mode-content">
  <iframe src="/mainscheduler/tabs/schedule_generate.php" 
          style="width:100%; height:800px; border:none; border-radius:8px;">
  </iframe>
</div>

<!-- Weekly View Mode -->
<div id="mode-view" class="mode-content">
  <iframe src="/mainscheduler/tabs/schedule_view.php" 
          style="width:100%; height:800px; border:none; border-radius:8px;">
  </iframe>
</div>

<!-- Modals remain the same -->
<!-- Add Schedule Modal -->
<div id="schedule-modal" class="modal">
  <form class="modal-content animate" id="schedule-form">
    <div class="imgcontainer">
      <span onclick="closeScheduleModal()" class="close" title="Close">&times;</span>
      <h2>Add Schedule</h2>
    </div>

    <div class="container">
      <label for="faculty_id"><b>Teacher</b></label>
      <select name="faculty_id" id="faculty_id" required>
        <option value="">Select Teacher</option>
        <?php 
        $faculty_result->data_seek(0);
        while($faculty = $faculty_result->fetch_assoc()): 
        ?>
          <option value="<?php echo $faculty['faculty_id']; ?>">
            <?php echo htmlspecialchars($faculty['lname'] . ', ' . $faculty['fname'] . ' ' . $faculty['mname']); ?>
          </option>
        <?php endwhile; ?>
      </select>

      <label for="subject_id"><b>Subject</b></label>
      <select name="subject_id" id="subject_id" required>
        <option value="">Select Subject</option>
        <?php 
        $subjects_result->data_seek(0);
        while($subject = $subjects_result->fetch_assoc()): 
        ?>
          <option value="<?php echo $subject['subject_id']; ?>">
            <?php echo htmlspecialchars($subject['subject_name']); ?>
          </option>
        <?php endwhile; ?>
      </select>

      <label for="schedule_date"><b>Date</b></label>
      <input type="date" name="schedule_date" id="schedule_date" required>

      <label for="start_time"><b>Start Time</b></label>
      <input type="time" name="start_time" id="start_time" required>

      <label for="end_time"><b>End Time</b></label>
      <input type="time" name="end_time" id="end_time" required>

      <label for="room"><b>Room</b></label>
      <input type="text" name="room" id="room" placeholder="e.g., Room 101">

      <label for="notes"><b>Notes</b></label>
      <textarea name="notes" id="notes" rows="3" placeholder="Additional notes..."></textarea>

      <button type="submit" class="btn btn-primary">Create Schedule</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeScheduleModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<!-- Add Event Modal -->
<div id="event-modal" class="modal">
  <form class="modal-content animate" id="event-form">
    <div class="imgcontainer">
      <span onclick="closeEventModal()" class="close" title="Close">&times;</span>
      <h2>Add Event/Meeting</h2>
    </div>

    <div class="container">
      <label for="event_title"><b>Event Title</b></label>
      <input type="text" name="event_title" id="event_title" placeholder="e.g., Faculty Meeting" required>

      <label for="event_type"><b>Event Type</b></label>
      <select name="event_type" id="event_type" required>
        <option value="">Select Type</option>
        <option value="meeting">Meeting</option>
        <option value="training">Training</option>
        <option value="seminar">Seminar</option>
        <option value="holiday">Holiday</option>
        <option value="other">Other</option>
      </select>

      <label for="event_date"><b>Date</b></label>
      <input type="date" name="event_date" id="event_date" required>

      <label for="event_start_time"><b>Start Time</b></label>
      <input type="time" name="event_start_time" id="event_start_time" required>

      <label for="event_end_time"><b>End Time</b></label>
      <input type="time" name="event_end_time" id="event_end_time" required>

      <label for="event_location"><b>Location</b></label>
      <input type="text" name="event_location" id="event_location" placeholder="e.g., Conference Room">

      <label for="event_description"><b>Description</b></label>
      <textarea name="event_description" id="event_description" rows="3" placeholder="Event details..."></textarea>

      <button type="submit" class="btn btn-secondary">Create Event</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeEventModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
// Mode Switching
function switchMode(mode) {
  // Hide all modes
  document.querySelectorAll('.mode-content').forEach(content => {
    content.classList.remove('active');
  });
  
  // Deactivate all tabs
  document.querySelectorAll('.mode-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected mode
  document.getElementById('mode-' + mode).classList.add('active');
  event.target.classList.add('active');
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function() {
  const now = new Date();
  const currentMonth = now.toISOString().slice(0, 7);
  document.getElementById('month-select').value = currentMonth;
  
  loadCalendar();
});

function changeView() {
  const viewType = document.getElementById('view-type').value;
  const teacherFilter = document.getElementById('teacher-filter');
  const teacherLabel = document.getElementById('teacher-filter-label');
  
  if (viewType === 'specific') {
    teacherFilter.style.display = 'block';
    teacherLabel.style.display = 'block';
  } else {
    teacherFilter.style.display = 'none';
    teacherLabel.style.display = 'none';
    teacherFilter.value = '';
  }
  
  loadCalendar();
}

function loadCalendar() {
  const viewType = document.getElementById('view-type').value;
  const teacherId = document.getElementById('teacher-filter').value;
  const month = document.getElementById('month-select').value;
  
  const params = new URLSearchParams({
    view: viewType,
    month: month
  });
  
  if (viewType === 'specific' && teacherId) {
    params.append('teacher_id', teacherId);
  }
  
  // Include section_id if available from URL or context
  const urlParams = new URLSearchParams(window.location.search);
  const sectionId = urlParams.get('section_id');
  if (sectionId) {
    params.append('section_id', sectionId);
  }
  
  fetch(`/mainscheduler/tabs/calendar_view.php?${params.toString()}`)
    .then(response => response.text())
    .then(data => {
      document.getElementById('calendar-container').innerHTML = data;
    })
    .catch(error => {
      console.error('Error loading calendar:', error);
      document.getElementById('calendar-container').innerHTML = 
        '<p style="color:red;">Error loading calendar. Please try again.</p>';
    });
}

// Modal Functions
function openScheduleModal() {
  document.getElementById('schedule-modal').style.display = 'block';
  document.getElementById('schedule_date').value = new Date().toISOString().split('T')[0];
}

function closeScheduleModal() {
  document.getElementById('schedule-modal').style.display = 'none';
  document.getElementById('schedule-form').reset();
}

function openEventModal() {
  document.getElementById('event-modal').style.display = 'block';
  document.getElementById('event_date').value = new Date().toISOString().split('T')[0];
}

function closeEventModal() {
  document.getElementById('event-modal').style.display = 'none';
  document.getElementById('event-form').reset();
}

// Form Submissions
document.getElementById('schedule-form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  fetch('/mainscheduler/tabs/actions/schedule_create.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Schedule created successfully!');
      closeScheduleModal();
      loadCalendar();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error creating schedule. Please try again.');
  });
});

document.getElementById('event-form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  fetch('/mainscheduler/tabs/actions/event_create.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Event created successfully!');
      closeEventModal();
      loadCalendar();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error creating event. Please try again.');
  });
});

// Close modals when clicking outside
window.onclick = function(event) {
  const scheduleModal = document.getElementById('schedule-modal');
  const eventModal = document.getElementById('event-modal');
  a
  if (event.target == scheduleModal) {
    closeScheduleModal();
  }
  if (event.target == eventModal) {
    closeEventModal();
  }
}
</script>

</body>
</html>