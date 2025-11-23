<?php
require_once(__DIR__ . '/../db_connect.php');

// Fetch all faculty members for dropdown
$faculty_query = "SELECT faculty_id, fname, mname, lname FROM faculty ORDER BY lname, fname";
$faculty_result = $conn->query($faculty_query);

// Fetch all subjects for dropdown
$subjects_query = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule.css">
</head>

<body>

<h1>Schedule Management</h1>
<p>Create and manage teacher schedules, classes, and events.</p>

<div class="schedule-header">
  <div class="btn-group">
    <button onclick="openScheduleModal()" class="btn btn-primary">Add Schedule</button>
    <button onclick="openEventModal()" class="btn btn-secondary">Add Event/Meeting</button>
  </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
  <div class="filter-group">
    <label for="view-type">View:</label>
    <select id="view-type" onchange="changeView()">
      <option value="all">All Teachers</option>
      <option value="specific">Specific Teacher</option>
    </select>

    <label for="teacher-filter" id="teacher-filter-label" style="display:none;">Teacher:</label>
    <select id="teacher-filter" style="display:none;" onchange="loadCalendar()">
      <option value="">Select Teacher</option>
      <?php while($faculty = $faculty_result->fetch_assoc()): ?>
        <option value="<?php echo $faculty['faculty_id']; ?>">
          <?php echo htmlspecialchars($faculty['lname'] . ', ' . $faculty['fname'] . ' ' . $faculty['mname']); ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label for="month-select">Month:</label>
    <input type="month" id="month-select" onchange="loadCalendar()">
  </div>
</div>

<!-- Calendar Container -->
<div id="calendar-container">
  <!-- Calendar will load here via AJAX -->
</div>

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
        $faculty_result->data_seek(0); // Reset pointer
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
        $subjects_result->data_seek(0); // Reset pointer
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
// Initialize on page load
document.addEventListener("DOMContentLoaded", function() {
  // Set current month
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

// Schedule Modal Functions
function openScheduleModal() {
  document.getElementById('schedule-modal').style.display = 'block';
  // Set today's date as default
  document.getElementById('schedule_date').value = new Date().toISOString().split('T')[0];
}

function closeScheduleModal() {
  document.getElementById('schedule-modal').style.display = 'none';
  document.getElementById('schedule-form').reset();
}

// Event Modal Functions
function openEventModal() {
  document.getElementById('event-modal').style.display = 'block';
  // Set today's date as default
  document.getElementById('event_date').value = new Date().toISOString().split('T')[0];
}

function closeEventModal() {
  document.getElementById('event-modal').style.display = 'none';
  document.getElementById('event-form').reset();
}

// Handle Schedule Form Submission
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

// Handle Event Form Submission
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