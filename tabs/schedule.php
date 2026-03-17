<?php
require_once __DIR__ . "/../db_connect.php";

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
/* Local styles retained for schedule UI */
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

/* keep iframes visually consistent */
.mode-content iframe {
  width: 100%;
  height: 800px;
  border: none;
  border-radius: 8px;
}

/* simple card layout used in the page */
.card-section {
  background: white;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
</style>
</head>

<body>

<!-- Mode Selection Tabs -->
<div class="schedule-mode-tabs" role="tablist" aria-label="Schedule modes">
  <button class="mode-tab active" data-mode="manual" onclick="switchMode('manual', this)" role="tab" aria-selected="true">Calendar</button>
  <button class="mode-tab" data-mode="setup" onclick="switchMode('setup', this)" role="tab">Setup</button>
  <button class="mode-tab" data-mode="generate" onclick="switchMode('generate', this)" role="tab">Generate</button>
  <button class="mode-tab" data-mode="view" onclick="switchMode('view', this)" role="tab">Schedules</button>
</div>

<!-- Manual Entry Mode (Calendar) -->
<div id="mode-manual" class="mode-content active" data-mode-id="manual">
  <div class="card-section">
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
      <button onclick="openScheduleModal()" class="btn btn-primary" style="flex: 1;">+ Add Class Schedule</button>
      <button onclick="openEventModal()" class="btn btn-success" style="flex: 1;">+ Add Event/Meeting</button>
    </div>
  </div>

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
          while ($faculty = $faculty_result->fetch_assoc()): ?>
            <option value="<?php echo $faculty["faculty_id"]; ?>"><?php echo htmlspecialchars($faculty["lname"] . ", " . $faculty["fname"]); ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="month-select">Month</label>
        <input type="month" id="month-select" onchange="loadCalendar()">
      </div>

    </div>
  </div>

  <div id="calendar-container" class="card-section" style="padding: 0; overflow: hidden;">
    <!-- calendar content injected via AJAX by loadCalendar() -->
  </div>
</div>

<!-- Setup Mode -->
<div id="mode-setup" class="mode-content" data-mode-id="setup">
  <!-- Use data-src to store the real URL and keep src blank until the mode is shown -->
  <iframe data-src="/mainscheduler/tabs/schedule_setup.php" src="about:blank" loading="lazy" title="Schedule Setup"></iframe>
</div>

<!-- Generate Mode -->
<div id="mode-generate" class="mode-content" data-mode-id="generate">
  <iframe data-src="/mainscheduler/tabs/schedule_generate.php" src="about:blank" loading="lazy" title="Auto Generate"></iframe>
</div>

<!-- Weekly View Mode -->
<div id="mode-view" class="mode-content" data-mode-id="view">
  <iframe data-src="/mainscheduler/tabs/schedule_view.php" src="about:blank" loading="lazy" title="Schedules View"></iframe>
</div>

<!-- Modals (unchanged) -->
<div id="schedule-modal" class="modal" aria-hidden="true">
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
        while ($faculty = $faculty_result->fetch_assoc()): ?>
          <option value="<?php echo $faculty["faculty_id"]; ?>"><?php echo htmlspecialchars($faculty["lname"] . ", " . $faculty["fname"] . " " . $faculty["mname"]); ?></option>
        <?php endwhile; ?>
      </select>

      <label for="subject_id"><b>Subject</b></label>
      <select name="subject_id" id="subject_id" required>
        <option value="">Select Subject</option>
        <?php
        $subjects_result->data_seek(0);
        while ($subject = $subjects_result->fetch_assoc()): ?>
          <option value="<?php echo $subject["subject_id"]; ?>"><?php echo htmlspecialchars($subject["subject_name"]); ?></option>
        <?php endwhile; ?>
      </select>

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

<div id="event-modal" class="modal" aria-hidden="true">
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
/*
  Behavior goals implemented here:
  - When switching between schedule modes (Calendar / Setup / Generate / View),
    any iframe belonging to the mode being hidden will be reset (src cleared)
    so that when you return the iframe reloads fresh.
  - When the Schedule tab (the whole schedule page) is hidden (user switches to a different top-level tab),
    all mode iframes are reset. When the Schedule tab is shown again, the currently active mode's iframe is loaded.
  - We use data-src attributes on the iframes to keep the original URL, and only set iframe.src when needed.
*/

/* Utility: set or clear iframe for a given mode (manual has no iframe) */
function setModeIframe(mode, load) {
  const container = document.getElementById('mode-' + mode);
  if (!container) return;
  const iframe = container.querySelector('iframe[data-src]');
  if (!iframe) return;
  if (load) {
    const src = iframe.getAttribute('data-src');
    if (iframe.src === '' || iframe.src === 'about:blank') {
      iframe.src = src;
    } else {
      // force reload: clear then set (small delay ensures navigation)
      iframe.src = 'about:blank';
      setTimeout(() => { iframe.src = src; }, 50);
    }
  } else {
    // clear iframe to free memory and reset state
    iframe.src = 'about:blank';
  }
}

/* Switch mode (attached to each mode tab button) */
function switchMode(mode, el) {
  // Find current active mode
  const prev = document.querySelector('.mode-content.active');
  const prevMode = prev ? prev.getAttribute('data-mode-id') : null;

  if (prev && prevMode === mode) {
    // clicking same tab: do nothing
    return;
  }

  // Hide previous mode and reset its iframe if it had one
  if (prev) {
    prev.classList.remove('active');
    if (prevMode) setModeIframe(prevMode, false);
  }

  // Deactivate all mode tabs, then activate clicked one
  document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');

  // Show selected mode
  const target = document.getElementById('mode-' + mode);
  if (!target) return;
  target.classList.add('active');

  // Load iframe for the shown mode (if any)
  setModeIframe(mode, true);

  // If we just switched to the manual calendar, ensure calendar refresh
  if (mode === 'manual') {
    loadCalendar();
  }
}

/* Initial setup: only load iframe for the active mode (if any) */
document.addEventListener('DOMContentLoaded', function() {
  // set default month for calendar
  const now = new Date();
  const currentMonth = now.toISOString().slice(0,7);
  const monthEl = document.getElementById('month-select');
  if (monthEl) monthEl.value = currentMonth;

  // load calendar initially
  loadCalendar();

  // Load iframe for whichever mode is active on page load
  document.querySelectorAll('.mode-content').forEach(c => {
    if (c.classList.contains('active')) {
      const mode = c.getAttribute('data-mode-id');
      if (mode && mode !== 'manual') setModeIframe(mode, true);
    } else {
      const mode = c.getAttribute('data-mode-id');
      if (mode && mode !== 'manual') setModeIframe(mode, false);
    }
  });

  // Listen for the main tab being hidden/shown via custom events dispatched by the global tab-switcher
  const scheduleContainer = document.getElementById('schedule');
  if (scheduleContainer) {
    // When schedule tab is hidden, reset all iframes
    scheduleContainer.addEventListener('tab-hidden', function () {
      document.querySelectorAll('.mode-content').forEach(c => {
        const mode = c.getAttribute('data-mode-id');
        if (mode && mode !== 'manual') setModeIframe(mode, false);
      });
      // also reset calendar container to keep consistent state
      const calContainer = document.getElementById('calendar-container');
      if (calContainer) calContainer.innerHTML = '';
    });

    // When schedule tab is shown, reload the currently active mode's iframe (if any)
    scheduleContainer.addEventListener('tab-shown', function () {
      const active = document.querySelector('.mode-content.active');
      if (active) {
        const mode = active.getAttribute('data-mode-id');
        if (mode && mode !== 'manual') setModeIframe(mode, true);
        if (mode === 'manual') loadCalendar();
      }
    });
  }

  // Also listen for the custom events on the schedule page root (in case the global dispatcher attaches to the element)
  document.querySelectorAll('[data-tab-content]').forEach(el => {
    // support for older dispatch patterns: if event is fired directly on the element we'll handle it
    el.addEventListener('tab-hidden', function (ev) {
      if (ev && ev.detail && ev.detail.id && ev.detail.id === 'schedule') {
        document.querySelectorAll('.mode-content').forEach(c => {
          const mode = c.getAttribute('data-mode-id');
          if (mode && mode !== 'manual') setModeIframe(mode, false);
        });
      }
    });
    el.addEventListener('tab-shown', function (ev) {
      if (ev && ev.detail && ev.detail.id && ev.detail.id === 'schedule') {
        const active = document.querySelector('.mode-content.active');
        if (active) {
          const mode = active.getAttribute('data-mode-id');
          if (mode && mode !== 'manual') setModeIframe(mode, true);
          if (mode === 'manual') loadCalendar();
        }
      }
    });
  });
});

/* Calendar loader (AJAX) */
function changeView() {
  const viewType = document.getElementById('view-type').value;
  const teacherFilter = document.getElementById('teacher-filter');
  if (viewType === 'specific') {
    document.getElementById('teacher-filter-group').style.display = 'block';
  } else {
    document.getElementById('teacher-filter-group').style.display = 'none';
    if (teacherFilter) teacherFilter.value = '';
  }
  loadCalendar();
}

function loadCalendar() {
  const viewType = document.getElementById('view-type') ? document.getElementById('view-type').value : 'all';
  const teacherId = document.getElementById('teacher-filter') ? document.getElementById('teacher-filter').value : '';
  const month = document.getElementById('month-select') ? document.getElementById('month-select').value : '';

  const params = new URLSearchParams({ view: viewType, month: month });
  if (viewType === 'specific' && teacherId) params.append('teacher_id', teacherId);

  fetch(`/mainscheduler/tabs/calendar_view.php?${params.toString()}`)
    .then(response => response.text())
    .then(html => {
      document.getElementById('calendar-container').innerHTML = html;
    })
    .catch(err => {
      console.error('Error loading