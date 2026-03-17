<?php
require_once __DIR__ . "/../db_connect.php";

// Fetch all faculty members for dropdown
$faculty_query =
    "SELECT faculty_id, fname, mname, lname FROM faculty ORDER BY lname, fname";
$faculty_result = $conn->query($faculty_query);

// Fetch all subjects for dropdown
$subjects_query =
    "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Fetch all sections for automation (kept in case other fragments use them)
$sections_query =
    "SELECT section_id, section_name, grade_level, track, school_year, semester FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);
?>

<!--
  Fragment: schedule.php
  - This file is intended to be included inside a parent page (no DOCTYPE/head/body here)
  - It exports a small UI for the Schedule tab and exposes a handful of global functions:
    switchMode, changeView, loadCalendar, openScheduleModal, closeScheduleModal,
    openEventModal, closeEventModal
-->

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

/* Basic modal styles (kept small) */
.modal[aria-hidden="true"] {
  display: none;
}
.modal[aria-hidden="false"] {
  display: block;
  position: fixed;
  z-index: 10000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.4);
}
.modal .modal-content {
  background-color: #fefefe;
  margin: 60px auto;
  padding: 20px;
  border: 1px solid #888;
  width: 90%;
  max-width: 720px;
  border-radius: 8px;
}
.close {
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}
</style>

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
    <div class="form-grid" style="display:flex;gap:16px;flex-wrap:wrap;">
      <div class="form-group" style="flex:1;min-width:180px;">
        <label for="view-type">View Mode</label>
        <select id="view-type" onchange="changeView()" class="form-control">
          <option value="all">All Teachers</option>
          <option value="specific">Specific Teacher</option>
        </select>
      </div>

      <div class="form-group" id="teacher-filter-group" style="display:none;flex:1;min-width:200px;">
        <label for="teacher-filter">Select Teacher</label>
        <select id="teacher-filter" onchange="loadCalendar()" class="form-control">
          <option value="">-- Select Teacher --</option>
          <?php if ($faculty_result) {
              $faculty_result->data_seek(0);
              while ($faculty = $faculty_result->fetch_assoc()): ?>
              <option value="<?php echo (int) $faculty[
                  "faculty_id"
              ]; ?>"><?php echo htmlspecialchars(
    $faculty["lname"] . ", " . $faculty["fname"],
); ?></option>
          <?php endwhile;
          } ?>
        </select>
      </div>

      <div class="form-group" style="flex:1;min-width:180px;">
        <label for="month-select">Month</label>
        <input type="month" id="month-select" onchange="loadCalendar()" class="form-control">
      </div>
    </div>
  </div>

  <div id="calendar-container" class="card-section" style="padding: 0; overflow: hidden;">
    <!-- calendar content injected via AJAX by loadCalendar() -->
  </div>
</div>

<!-- Setup Mode -->
<div id="mode-setup" class="mode-content" data-mode-id="setup">
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

<!-- Modals -->
<div id="schedule-modal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <span onclick="closeScheduleModal()" class="close" title="Close">&times;</span>
    <h2>Add Schedule</h2>
    <form id="schedule-form">
      <div style="display:flex;flex-direction:column;gap:8px;">
        <label for="faculty_id"><b>Teacher</b></label>
        <select name="faculty_id" id="faculty_id" required>
          <option value="">Select Teacher</option>
          <?php if ($faculty_result) {
              $faculty_result->data_seek(0);
              while ($faculty = $faculty_result->fetch_assoc()): ?>
              <option value="<?php echo (int) $faculty[
                  "faculty_id"
              ]; ?>"><?php echo htmlspecialchars(
    $faculty["lname"] . ", " . $faculty["fname"] . " " . $faculty["mname"],
); ?></option>
          <?php endwhile;
          } ?>
        </select>

        <label for="subject_id"><b>Subject</b></label>
        <select name="subject_id" id="subject_id" required>
          <option value="">Select Subject</option>
          <?php if ($subjects_result) {
              $subjects_result->data_seek(0);
              while ($subject = $subjects_result->fetch_assoc()): ?>
              <option value="<?php echo (int) $subject[
                  "subject_id"
              ]; ?>"><?php echo htmlspecialchars(
    $subject["subject_name"],
); ?></option>
          <?php endwhile;
          } ?>
        </select>

        <label for="start_time"><b>Start Time</b></label>
        <input type="time" name="start_time" id="start_time" required>

        <label for="end_time"><b>End Time</b></label>
        <input type="time" name="end_time" id="end_time" required>

        <label for="room"><b>Room</b></label>
        <input type="text" name="room" id="room" placeholder="e.g., Room 101">

        <label for="notes"><b>Notes</b></label>
        <textarea name="notes" id="notes" rows="3" placeholder="Additional notes..."></textarea>

        <div style="display:flex;gap:8px;margin-top:8px;">
          <button type="submit" class="btn btn-primary">Create Schedule</button>
          <button type="button" onclick="closeScheduleModal()" class="btn">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div id="event-modal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <span onclick="closeEventModal()" class="close" title="Close">&times;</span>
    <h2>Add Event/Meeting</h2>
    <form id="event-form">
      <div style="display:flex;flex-direction:column;gap:8px;">
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

        <div style="display:flex;gap:8px;margin-top:8px;">
          <button type="submit" class="btn btn-secondary">Create Event</button>
          <button type="button" onclick="closeEventModal()" class="btn">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  // Expose a handful of functions to the global scope because the markup uses inline onclick attributes.
  // Attach to window explicitly so that other scripts can call them as well.
  function getIframeForMode(mode) {
    const container = document.getElementById('mode-' + mode);
    return container ? container.querySelector('iframe[data-src]') : null;
  }

  function setModeIframe(mode, load) {
    const iframe = getIframeForMode(mode);
    if (!iframe) return;
    if (load) {
      const src = iframe.getAttribute('data-src');
      // If iframe already has the src but we want to reload, do a quick blank->src cycle
      if (!iframe.src || iframe.src === 'about:blank') {
        iframe.src = src;
      } else {
        iframe.src = 'about:blank';
        setTimeout(function () { iframe.src = src; }, 50);
      }
    } else {
      // clear the iframe to free memory
      iframe.src = 'about:blank';
    }
  }

  // Make switchMode globally available
  window.switchMode = function (mode, el) {
    try {
      const prev = document.querySelector('.mode-content.active');
      const prevMode = prev ? prev.getAttribute('data-mode-id') : null;

      if (prev && prevMode === mode) {
        return;
      }

      if (prev) {
        prev.classList.remove('active');
        if (prevMode) setModeIframe(prevMode, false);
      }

      document.querySelectorAll('.mode-tab').forEach(function (t) { t.classList.remove('active'); });
      if (el && el.classList) el.classList.add('active');

      const target = document.getElementById('mode-' + mode);
      if (!target) return;
      target.classList.add('active');

      // load iframe for the shown mode (if any)
      setModeIframe(mode, true);

      if (mode === 'manual') {
        // when switching to calendar mode, reload calendar
        loadCalendar();
      }
    } catch (err) {
      // don't throw; just log
      console.error('switchMode error', err);
    }
  };

  // Change the view (all vs specific teacher)
  window.changeView = function () {
    var viewTypeEl = document.getElementById('view-type');
    if (!viewTypeEl) return;
    var viewType = viewTypeEl.value;
    var teacherGroup = document.getElementById('teacher-filter-group');
    var teacherFilter = document.getElementById('teacher-filter');
    if (viewType === 'specific') {
      if (teacherGroup) teacherGroup.style.display = 'block';
    } else {
      if (teacherGroup) teacherGroup.style.display = 'none';
      if (teacherFilter) teacherFilter.value = '';
    }
    loadCalendar();
  };

  // Calendar loader via AJAX (returns HTML fragment inserted into #calendar-container)
  window.loadCalendar = function () {
    var viewTypeEl = document.getElementById('view-type');
    var viewType = viewTypeEl ? viewTypeEl.value : 'all';
    var teacherIdEl = document.getElementById('teacher-filter');
    var teacherId = teacherIdEl ? teacherIdEl.value : '';
    var monthEl = document.getElementById('month-select');
    var month = monthEl ? monthEl.value : '';

    var params = new URLSearchParams({ view: viewType, month: month });
    if (viewType === 'specific' && teacherId) params.append('teacher_id', teacherId);

    var url = '/mainscheduler/tabs/calendar_view.php?' + params.toString();
    fetch(url, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.text();
      })
      .then(function (html) {
        var container = document.getElementById('calendar-container');
        if (container) container.innerHTML = html;
      })
      .catch(function (err) {
        console.error('Error loading calendar:', err);
        var container = document.getElementById('calendar-container');
        if (container) container.innerHTML = '<div style="padding:16px;color:#a00;">Error loading calendar. Check console for details.</div>';
      });
  };

  // Simple modal open/close functions
  window.openScheduleModal = function () {
    var m = document.getElementById('schedule-modal');
    if (m) m.setAttribute('aria-hidden', 'false');
  };
  window.closeScheduleModal = function () {
    var m = document.getElementById('schedule-modal');
    if (m) m.setAttribute('aria-hidden', 'true');
  };
  window.openEventModal = function () {
    var m = document.getElementById('event-modal');
    if (m) m.setAttribute('aria-hidden', 'false');
  };
  window.closeEventModal = function () {
    var m = document.getElementById('event-modal');
    if (m) m.setAttribute('aria-hidden', 'true');
  };

  // Wire up simple form handlers to prevent page navigation and close the modal on submit.
  // You can replace these with real AJAX submissions later.
  function bindFormHandlers() {
    var scheduleForm = document.getElementById('schedule-form');
    if (scheduleForm) {
      scheduleForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        // TODO: submit via fetch to server endpoint; for now just close modal and reload calendar
        closeScheduleModal();
        setTimeout(loadCalendar, 200);
      });
    }

    var eventForm = document.getElementById('event-form');
    if (eventForm) {
      eventForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        // TODO: submit via fetch; for now close modal and reload calendar
        closeEventModal();
        setTimeout(loadCalendar, 200);
      });
    }
  }

  // Initialization on DOMContentLoaded: set current month, initial calendar load if this fragment is active,
  // and prepare iframes according to which mode is active.
  document.addEventListener('DOMContentLoaded', function () {
    try {
      var now = new Date();
      var currentMonth = now.toISOString().slice(0, 7);
      var monthEl = document.getElementById('month-select');
      if (monthEl && !monthEl.value) monthEl.value = currentMonth;

      bindFormHandlers();

      // If the schedule tab (container) is active in the page, do initial loads.
      var scheduleContainer = document.getElementById('schedule');
      var fragmentActive = scheduleContainer ? scheduleContainer.classList.contains('active') : true;

      // Load calendar only when the schedule fragment is active
      if (fragmentActive) {
        loadCalendar();
      }

      // Load iframe for whichever mode-content is active
      document.querySelectorAll('.mode-content').forEach(function (c) {
        var mode = c.getAttribute('data-mode-id');
        if (!mode) return;
        if (c.classList.contains('active')) {
          if (mode !== 'manual') setModeIframe(mode, true);
        } else {
          if (mode !== 'manual') setModeIframe(mode, false);
        }
      });

      // Listen for custom events dispatched by the global tab switcher (if present)
      if (scheduleContainer) {
        scheduleContainer.addEventListener('tab-hidden', function () {
          document.querySelectorAll('.mode-content').forEach(function (c) {
            var mode = c.getAttribute('data-mode-id');
            if (mode && mode !== 'manual') setModeIframe(mode, false);
          });
          var calContainer = document.getElementById('calendar-container');
          if (calContainer) calContainer.innerHTML = '';
        });

        scheduleContainer.addEventListener('tab-shown', function () {
          var active = document.querySelector('.mode-content.active');
          if (active) {
            var mode = active.getAttribute('data-mode-id');
            if (mode && mode !== 'manual') setModeIframe(mode, true);
            if (mode === 'manual') loadCalendar();
          }
        });
      }

      // Also attach to any element that might receive the tab events (fallback)
      document.querySelectorAll('[data-tab-content]').forEach(function (el) {
        el.addEventListener('tab-hidden', function (ev) {
          if (ev && ev.detail && ev.detail.id && ev.detail.id === 'schedule') {
            document.querySelectorAll('.mode-content').forEach(function (c) {
              var mode = c.getAttribute('data-mode-id');
              if (mode && mode !== 'manual') setModeIframe(mode, false);
            });
          }
        });
        el.addEventListener('tab-shown', function (ev) {
          if (ev && ev.detail && ev.detail.id && ev.detail.id === 'schedule') {
            var active = document.querySelector('.mode-content.active');
            if (active) {
              var mode = active.getAttribute('data-mode-id');
              if (mode && mode !== 'manual') setModeIframe(mode, true);
              if (mode === 'manual') loadCalendar();
            }
          }
        });
      });
    } catch (initErr) {
      console.error('schedule fragment init error', initErr);
    }
  });
})();
</script>
