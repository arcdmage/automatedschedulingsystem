<?php
require_once __DIR__ . "/../db_connect.php"; ?>
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule.css">

<style>
.schedule-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.schedule-primary-bar,
.schedule-secondary-bar {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: nowrap;
  overflow: visible;
  background: #ffffff;
  border-radius: 10px;
  padding: 14px 16px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.schedule-primary-bar label {
  font-weight: 700;
  color: #0f172a;
}
.schedule-primary-bar select {
  min-width: 220px;
  max-width: 220px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-weight: 600;
  flex: 0 0 auto;
}
.secondary-dropdown {
  position: relative;
  flex: 1 1 0;
}
.secondary-dropdown > .secondary-btn {
  width: 100%;
}
.secondary-group {
  display: none;
  gap: 10px;
  flex-wrap: nowrap;
  align-items: center;
  width: 100%;
}
.secondary-group.active {
  display: flex;
}
.secondary-btn {
  border: 0;
  border-radius: 8px;
  padding: 11px 12px;
  background: #e2e8f0;
  color: #334155;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  flex: 1 1 0;
}
.secondary-btn.active {
  background: #16a34a;
  color: #ffffff;
}
.secondary-dropdown {
  position: relative;
}
.secondary-dropdown-menu {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  min-width: 220px;
  background: #ffffff;
  border: 1px solid #dbe2ea;
  border-radius: 10px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
  padding: 8px;
  z-index: 20;
}
.secondary-dropdown.open .secondary-dropdown-menu {
  display: block;
}
.secondary-dropdown-menu button {
  width: 100%;
  border: 0;
  background: #ffffff;
  color: #334155;
  font-weight: 600;
  text-align: left;
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
}
.secondary-dropdown-menu button.active,
.secondary-dropdown-menu button:hover {
  background: #f0fdf4;
  color: #15803d;
}
.schedule-pane {
  display: none;
  position: relative;
}
.schedule-pane.active {
  display: block;
}
.schedule-pane iframe {
  width: 100%;
  height: 840px;
  border: 0;
  border-radius: 10px;
  background: #ffffff;
}
.pane-loader {
  display: none;
  position: absolute;
  inset: 0;
  z-index: 5;
  align-items: center;
  justify-content: center;
  padding: 24px;
  border-radius: 10px;
  background: rgba(248, 250, 252, 0.96);
  color: #334155;
  text-align: center;
}
.pane-loader.active {
  display: flex;
}
.pane-loader-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  max-width: 280px;
}
.pane-loader-spinner {
  width: 36px;
  height: 36px;
  border: 4px solid #dbeafe;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: pane-spin 0.8s linear infinite;
}
.pane-loader-title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}
.pane-loader-text {
  font-size: 13px;
  line-height: 1.5;
  color: #64748b;
}
@keyframes pane-spin {
  to {
    transform: rotate(360deg);
  }
}
.card-section {
  background: white;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.calendar-controls {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}
.modal[aria-hidden="true"] { display: none; }
.modal[aria-hidden="false"] {
  display: block;
  position: fixed;
  z-index: 10000;
  inset: 0;
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
  color: #1f2937;
}
.close {
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}
</style>

<div class="schedule-shell">
  <div class="schedule-primary-bar">
    <label for="schedule-primary-mode">Schedule Mode</label>
    <select id="schedule-primary-mode" onchange="switchPrimaryMode(this.value)">
      <option value="manual">Manual</option>
      <option value="automated">Automated</option>
    </select>
  </div>

  <div class="schedule-secondary-bar">
    <div id="secondary-manual" class="secondary-group active">
      <button class="secondary-btn active" data-primary="manual" data-secondary="calendar" onclick="switchSecondaryMode('manual','calendar', this)">Calendar</button>
      <div class="secondary-dropdown" id="manual-setup-dropdown">
        <button class="secondary-btn" type="button" onclick="toggleManualSetupDropdown(event)" id="manual-setup-trigger">Setup</button>
        <div class="secondary-dropdown-menu">
          <button type="button" class="active" data-setup-view="subject" onclick="selectManualSetupView('subject', event)">Subject Setup</button>
          <button type="button" data-setup-view="timeslots" onclick="selectManualSetupView('timeslots', event)">Manage Time Slots</button>
        </div>
      </div>
      <button class="secondary-btn" data-primary="manual" data-secondary="generate" onclick="switchSecondaryMode('manual','generate', this)">Generate</button>
      <button class="secondary-btn" data-primary="manual" data-secondary="view" onclick="switchSecondaryMode('manual','view', this)">Schedules</button>
    </div>

    <div id="secondary-automated" class="secondary-group">
      <button class="secondary-btn active" data-primary="automated" data-secondary="setup" onclick="switchSecondaryMode('automated','setup', this)">Setup</button>
      <button class="secondary-btn" data-primary="automated" data-secondary="generate" onclick="switchSecondaryMode('automated','generate', this)">Generate</button>
      <button class="secondary-btn" data-primary="automated" data-secondary="view" onclick="switchSecondaryMode('automated','view', this)">Schedules</button>
    </div>
  </div>

  <div id="pane-manual-calendar" class="schedule-pane active">
    <div class="card-section">
      <div class="calendar-controls">
        <button type="button" onclick="openEventModal()" class="btn btn-success" style="flex: 1;">+ Add Event/Meeting</button>
        <button type="button" onclick="window.print()" class="btn btn-primary" style="flex: 1;">Print Calendar</button>
      </div>
    </div>
    <div id="calendar-container" class="card-section" style="padding: 0; overflow: hidden;"></div>
  </div>

  <div id="pane-manual-setup" class="schedule-pane">
    <iframe
      id="manual-setup-iframe"
      data-src-subject="/mainscheduler/tabs/schedule_setup.php"
      data-src-timeslots="/mainscheduler/tabs/manage_timeslots.php"
      src="about:blank"
      loading="lazy"
      title="Manual Setup"></iframe>
  </div>

  <div id="pane-manual-generate" class="schedule-pane">
    <iframe id="manual-generate-iframe" data-src="/mainscheduler/tabs/schedule_generate.php" src="about:blank" loading="lazy" title="Manual Generate"></iframe>
  </div>

  <div id="pane-manual-view" class="schedule-pane">
    <iframe id="manual-view-iframe" data-src="/mainscheduler/tabs/schedule_view.php" src="about:blank" loading="lazy" title="Manual Schedule View"></iframe>
  </div>

  <div id="pane-automated-setup" class="schedule-pane">
    <div class="pane-loader" id="loader-automated-setup">
      <div class="pane-loader-card">
        <div class="pane-loader-spinner"></div>
        <div class="pane-loader-title">Loading Automated Setup</div>
        <div class="pane-loader-text">Fetching section subjects and automated scheduling options.</div>
      </div>
    </div>
    <iframe id="automated-setup-iframe" data-src="/mainscheduler/tabs/automated_setup.php" src="about:blank" loading="lazy" title="Automated Setup"></iframe>
  </div>

  <div id="pane-automated-generate" class="schedule-pane">
    <div class="pane-loader" id="loader-automated-generate">
      <div class="pane-loader-card">
        <div class="pane-loader-spinner"></div>
        <div class="pane-loader-title">Loading Automated Generate</div>
        <div class="pane-loader-text">Preparing automated scheduling tools for this section.</div>
      </div>
    </div>
    <iframe id="automated-generate-iframe" data-src="/mainscheduler/tabs/automated_generate.php" src="about:blank" loading="lazy" title="Automated Generate"></iframe>
  </div>

  <div id="pane-automated-view" class="schedule-pane">
    <div class="pane-loader" id="loader-automated-view">
      <div class="pane-loader-card">
        <div class="pane-loader-spinner"></div>
        <div class="pane-loader-title">Loading Automated Schedules</div>
        <div class="pane-loader-text">Retrieving the generated schedule view.</div>
      </div>
    </div>
    <iframe id="automated-view-iframe" data-src="/mainscheduler/tabs/schedule_view.php" src="about:blank" loading="lazy" title="Automated Schedule View"></iframe>
  </div>
</div>

<div id="event-modal" class="modal" aria-hidden="true">
  <div class="modal-content">
    <span onclick="closeEventModal()" class="close" title="Close">&times;</span>
    <h2 id="event-modal-title">Add Event/Meeting</h2>
    <form id="event-form">
      <input type="hidden" name="event_id" id="event_id">
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
          <button type="submit" class="btn btn-secondary" id="event-submit-btn">Create Event</button>
          <button type="button" id="event-delete-btn" onclick="deleteEvent()" class="btn" style="background:#dc2626;display:none;">Delete Event</button>
          <button type="button" onclick="closeEventModal()" class="btn">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var currentPrimaryMode = 'manual';
  var currentManualSecondary = 'calendar';
  var currentAutomatedSecondary = 'setup';
  var currentManualSetupView = 'subject';
  var lastManualSetupSectionId = '';
  var currentAutomatedSectionId = '';

  function paneId(primary, secondary) {
    return 'pane-' + primary + '-' + secondary;
  }

  function iframeId(primary, secondary) {
    return primary + '-' + secondary + '-iframe';
  }

  function getIframe(primary, secondary) {
    return document.getElementById(iframeId(primary, secondary));
  }

  function getPaneLoader(primary, secondary) {
    return document.getElementById('loader-' + primary + '-' + secondary);
  }

  function setPaneLoading(primary, secondary, isLoading) {
    const loader = getPaneLoader(primary, secondary);
    if (!loader) return;
    loader.classList.toggle('active', !!isLoading);
  }

  function getActiveSecondary(primary) {
    return primary === 'manual' ? currentManualSecondary : currentAutomatedSecondary;
  }

  function getAutomatedSectionId() {
    if (currentAutomatedSectionId) {
      return currentAutomatedSectionId;
    }
    ['setup', 'generate', 'view'].some(function (secondary) {
      const iframe = getIframe('automated', secondary);
      if (!iframe || !iframe.src || iframe.src === 'about:blank') return false;
      try {
        const url = new URL(iframe.src, window.location.origin);
        const sectionId = url.searchParams.get('section_id') || '';
        if (sectionId) {
          currentAutomatedSectionId = sectionId;
          return true;
        }
      } catch (err) {
        return false;
      }
      return false;
    });
    return currentAutomatedSectionId;
  }

  function buildAutomatedSrc(src) {
    const automatedSectionId = getAutomatedSectionId();
    if (!src) return src;
    const url = new URL(src, window.location.origin);
    if (automatedSectionId) {
      url.searchParams.set('section_id', automatedSectionId);
    } else {
      url.searchParams.delete('section_id');
    }
    return url.pathname + url.search;
  }

  function getManualSetupSectionId() {
    const iframe = getIframe('manual', 'setup');
    if (!iframe || !iframe.src || iframe.src === 'about:blank') {
      return lastManualSetupSectionId;
    }
    try {
      const url = new URL(iframe.src, window.location.origin);
      const sectionId = url.searchParams.get('section_id') || '';
      if (sectionId) lastManualSetupSectionId = sectionId;
      return sectionId;
    } catch (err) {
      return lastManualSetupSectionId;
    }
  }

  function loadIframe(primary, secondary, forceUrl) {
    const iframe = getIframe(primary, secondary);
    if (!iframe) return;

    let src = forceUrl || iframe.getAttribute('data-src');
    if (primary === 'manual' && secondary === 'setup') {
      src = currentManualSetupView === 'timeslots'
        ? iframe.getAttribute('data-src-timeslots')
        : iframe.getAttribute('data-src-subject');
      const sectionId = getManualSetupSectionId();
      if (sectionId) {
        const url = new URL(src, window.location.origin);
        url.searchParams.set('section_id', sectionId);
        src = url.pathname + url.search;
      }
    } else if (primary === 'automated') {
      src = buildAutomatedSrc(src);
    }

    if (!src) return;
    if (primary === 'automated') {
      setPaneLoading(primary, secondary, true);
    }
    if (!iframe.src || iframe.src === 'about:blank') {
      iframe.src = src;
    } else if (iframe.src !== new URL(src, window.location.origin).href) {
      iframe.src = src;
    } else if (primary === 'automated') {
      setPaneLoading(primary, secondary, false);
    }
  }

  function unloadIframe(primary, secondary) {
    const iframe = getIframe(primary, secondary);
    if (!iframe) return;
    if (primary === 'manual' && secondary === 'setup') {
      getManualSetupSectionId();
    }
    if (primary === 'automated') {
      setPaneLoading(primary, secondary, false);
    }
    iframe.src = 'about:blank';
  }

  function refreshAutomatedPane(secondary, options) {
    const isActive = currentPrimaryMode === 'automated' && currentAutomatedSecondary === secondary;
    unloadIframe('automated', secondary);
    if (isActive && !(options && options.unloadOnly)) {
      loadIframe('automated', secondary, options && options.forceUrl ? options.forceUrl : null);
    }
  }

  function resetAutomatedDependents(source) {
    if (source === 'setup') {
      refreshAutomatedPane('generate');
      refreshAutomatedPane('view');
      return;
    }
    if (source === 'generate') {
      refreshAutomatedPane('view');
    }
  }

  function refreshSecondaryButtons(primary, secondary) {
    document.querySelectorAll('.secondary-btn[data-primary="' + primary + '"]').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-secondary') === secondary);
    });
    if (primary === 'manual') {
      document.getElementById('manual-setup-trigger').classList.toggle('active', secondary === 'setup');
    }
  }

  function activatePane(primary, secondary, options) {
    options = options || {};
    document.querySelectorAll('.schedule-pane').forEach(function (pane) {
      pane.classList.remove('active');
    });
    const pane = document.getElementById(paneId(primary, secondary));
    if (pane) pane.classList.add('active');

    if (primary === 'manual') {
      currentManualSecondary = secondary;
    } else {
      currentAutomatedSecondary = secondary;
    }
    refreshSecondaryButtons(primary, secondary);

    if (primary === 'manual' && secondary === 'calendar') {
      loadCalendar();
      return;
    }

    loadIframe(primary, secondary, options.forceUrl || null);
  }

  window.switchPrimaryMode = function (mode) {
    currentPrimaryMode = mode === 'automated' ? 'automated' : 'manual';
    const select = document.getElementById('schedule-primary-mode');
    if (select) select.value = currentPrimaryMode;

    document.querySelectorAll('.secondary-group').forEach(function (group) {
      group.classList.remove('active');
    });
    const activeGroup = document.getElementById('secondary-' + currentPrimaryMode);
    if (activeGroup) activeGroup.classList.add('active');

    activatePane(currentPrimaryMode, getActiveSecondary(currentPrimaryMode));
  };

  window.switchSecondaryMode = function (primary, secondary, el) {
    if (primary !== currentPrimaryMode) {
      switchPrimaryMode(primary);
    }
    closeManualSetupDropdown();
    activatePane(primary, secondary);
  };

  window.toggleManualSetupDropdown = function (event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    const dropdown = document.getElementById('manual-setup-dropdown');
    if (dropdown) dropdown.classList.toggle('open');
  };

  function closeManualSetupDropdown() {
    const dropdown = document.getElementById('manual-setup-dropdown');
    if (dropdown) dropdown.classList.remove('open');
  }

  function refreshManualSetupDropdown() {
    document.querySelectorAll('#manual-setup-dropdown [data-setup-view]').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-setup-view') === currentManualSetupView);
    });
    document.getElementById('manual-setup-trigger').textContent = currentManualSetupView === 'timeslots' ? 'Setup: Time Slots' : 'Setup';
  }

  window.selectManualSetupView = function (view, event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    currentManualSetupView = view === 'timeslots' ? 'timeslots' : 'subject';
    refreshManualSetupDropdown();
    closeManualSetupDropdown();
    switchSecondaryMode('manual', 'setup');
  };

  window.openScheduleViewForSection = function (sectionId, preferredMode) {
    const primary = preferredMode === 'manual' ? 'manual' : 'automated';
    if (primary === 'automated') {
      currentAutomatedSectionId = sectionId ? String(sectionId) : '';
    }
    switchPrimaryMode(primary);
    const iframe = getIframe(primary, 'view');
    if (!iframe) return;
    const baseSrc = iframe.getAttribute('data-src') || '/mainscheduler/tabs/schedule_view.php';
    const url = new URL(baseSrc, window.location.origin);
    if (sectionId) url.searchParams.set('section_id', sectionId);
    activatePane(primary, 'view', { forceUrl: url.pathname + url.search });
  };

  window.notifyAutomatedStateChange = function (payload) {
    payload = payload || {};
    const changeType = payload.type || '';
    const sectionId = payload.sectionId ? String(payload.sectionId) : '';
    const source = payload.source || '';

    if (changeType === 'section-change') {
      currentAutomatedSectionId = sectionId;
      if (source !== 'setup') refreshAutomatedPane('setup');
      if (source !== 'generate') refreshAutomatedPane('generate');
      if (source !== 'view') refreshAutomatedPane('view');
      return;
    }

    if (changeType === 'requirements-updated') {
      if (sectionId) currentAutomatedSectionId = sectionId;
      resetAutomatedDependents('setup');
      return;
    }

    if (changeType === 'schedules-generated') {
      if (sectionId) currentAutomatedSectionId = sectionId;
      resetAutomatedDependents('generate');
    }
  };

  window.loadCalendar = function () {
    var now = new Date();
    var month = now.toISOString().slice(0, 7);
    fetch('/mainscheduler/tabs/calendar_view.php?month=' + encodeURIComponent(month), { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.text();
      })
      .then(function (html) {
        var container = document.getElementById('calendar-container');
        if (container) container.innerHTML = html;
      })
      .catch(function (err) {
        var container = document.getElementById('calendar-container');
        if (container) container.innerHTML = '<div style="padding:16px;color:#a00;">Error loading calendar.</div>';
        console.error(err);
      });
  };

  function resetEventForm() {
    var form = document.getElementById('event-form');
    if (form) form.reset();
    var eventId = document.getElementById('event_id');
    if (eventId) eventId.value = '';
    var title = document.getElementById('event-modal-title');
    if (title) title.textContent = 'Add Event/Meeting';
    var submitBtn = document.getElementById('event-submit-btn');
    if (submitBtn) submitBtn.textContent = 'Create Event';
    var deleteBtn = document.getElementById('event-delete-btn');
    if (deleteBtn) deleteBtn.style.display = 'none';
  }

  window.openEventModal = function (data) {
    resetEventForm();
    var modal = document.getElementById('event-modal');
    if (!modal) return;
    if (data && data.eventId) {
      document.getElementById('event_id').value = data.eventId || '';
      document.getElementById('event_title').value = data.title || '';
      document.getElementById('event_type').value = data.type || '';
      document.getElementById('event_date').value = data.date || '';
      document.getElementById('event_start_time').value = data.startTime || '';
      document.getElementById('event_end_time').value = data.endTime || '';
      document.getElementById('event_location').value = data.location || '';
      document.getElementById('event_description').value = data.description || '';
      document.getElementById('event-modal-title').textContent = 'Edit Event/Meeting';
      document.getElementById('event-submit-btn').textContent = 'Save Changes';
      document.getElementById('event-delete-btn').style.display = 'inline-block';
    }
    modal.setAttribute('aria-hidden', 'false');
  };

  window.closeEventModal = function () {
    var modal = document.getElementById('event-modal');
    if (modal) modal.setAttribute('aria-hidden', 'true');
    resetEventForm();
  };

  window.deleteEvent = function () {
    var eventId = document.getElementById('event_id');
    if (!eventId || !eventId.value || !window.confirm('Delete this event?')) return;
    var formData = new FormData();
    formData.append('event_id', eventId.value);
    fetch('/mainscheduler/tabs/actions/event_delete.php', { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (!json || !json.success) throw new Error(json && json.message ? json.message : 'Delete failed');
        closeEventModal();
        setTimeout(loadCalendar, 200);
      })
      .catch(function (err) { alert(err.message || 'Delete failed'); });
  };

  document.addEventListener('DOMContentLoaded', function () {
    refreshManualSetupDropdown();
    var eventForm = document.getElementById('event-form');
    if (eventForm) {
      eventForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        fetch('/mainscheduler/tabs/actions/event_create.php', { method: 'POST', body: new FormData(eventForm), credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (json) {
            if (!json || !json.success) throw new Error(json && json.message ? json.message : 'Save failed');
            closeEventModal();
            setTimeout(loadCalendar, 200);
          })
          .catch(function (err) { alert(err.message || 'Save failed'); });
      });
    }
    const manualSetupIframe = getIframe('manual', 'setup');
    if (manualSetupIframe) {
      manualSetupIframe.addEventListener('load', function () { getManualSetupSectionId(); });
    }
    ['setup', 'generate', 'view'].forEach(function (secondary) {
      const iframe = getIframe('automated', secondary);
      if (!iframe) return;
      iframe.addEventListener('load', function () {
        setPaneLoading('automated', secondary, false);
      });
    });
    switchPrimaryMode('manual');
  });

  document.addEventListener('click', function (event) {
    const dropdown = document.getElementById('manual-setup-dropdown');
    if (dropdown && dropdown.classList.contains('open') && !dropdown.contains(event.target)) {
      closeManualSetupDropdown();
    }
  });
})();
</script>
