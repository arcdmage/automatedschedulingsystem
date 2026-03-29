<?php
require_once __DIR__ . "/../db_connect.php";
$sections_result = $conn->query(
    "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name",
);
$selected_section = isset($_GET["section_id"])
    ? intval($_GET["section_id"])
    : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/schedule.css">
</head>
<body>
<div class="page-header">
  <h1>Automated Schedule Generator</h1>
  <p>Select a section and let the system automate subject placement, teacher assignment, and default time slots when none exist yet.</p>
</div>

<div class="generator-section">
  <form id="automated-generate-form">
    <div class="section-selector">
      <label for="section-select">Section</label>
      <select id="section-select" name="section_id" required>
        <option value="">-- Choose a Section --</option>
        <?php while ($section = $sections_result->fetch_assoc()): ?>
          <option value="<?php echo $section[
              "section_id"
          ]; ?>" <?php echo $selected_section === (int) $section["section_id"]
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
        <?php endwhile; ?>
      </select>
    </div>
    <button type="submit" class="btn-generate" id="automate-btn">Automate Schedules</button>
  </form>
</div>

<div class="progress-section" id="progress-section">
  <h2>Automation Progress</h2>
  <div class="progress-bar"><div class="progress-fill" id="progress-fill">0%</div></div>
  <div class="progress-log" id="progress-log"><div class="log-entry info">Waiting to start...</div></div>
  <div id="result-message" style="margin-top:20px;"></div>
</div>

<script>
function notifyParent(payload) {
  try {
    if (window.parent && window.parent !== window && typeof window.parent.notifyAutomatedStateChange === 'function') {
      window.parent.notifyAutomatedStateChange(payload);
    }
  } catch (error) {
    console.error(error);
  }
}

function addLog(message, type) {
  const log = document.getElementById('progress-log');
  if (!log) return;
  const row = document.createElement('div');
  row.className = 'log-entry ' + (type || 'info');
  row.textContent = message;
  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
}

function setProgress(value, label) {
  const fill = document.getElementById('progress-fill');
  if (!fill) return;
  fill.style.width = value + '%';
  fill.textContent = label || (value + '%');
}

document.getElementById('automated-generate-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const button = document.getElementById('automate-btn');
  const result = document.getElementById('result-message');
  const sectionId = document.getElementById('section-select').value;
  if (!sectionId) {
    alert('Select a section first.');
    return;
  }

  button.disabled = true;
  button.textContent = 'Automating...';
  document.getElementById('progress-log').innerHTML = '';
  result.innerHTML = '';
  setProgress(10, 'Starting...');
  addLog('Preparing automated schedule generation...', 'info');

  try {
    const response = await fetch('/mainscheduler/tabs/actions/schedule_quick_generate.php', {
      method: 'POST',
      body: new FormData(this)
    });
    const json = await response.json();
    if (!json.success) {
      throw new Error(json.message || 'Automation failed.');
    }

    setProgress(100, 'Completed');
    addLog('Automation completed.', 'success');
    addLog((json.schedules_created || 0) + ' schedules created.', 'success');
    notifyParent({ type: 'schedules-generated', source: 'generate', sectionId: sectionId });
    if (Array.isArray(json.conflict_details) && json.conflict_details.length > 0) {
      json.conflict_details.forEach(item => {
        addLog((item.subject ? item.subject + ' - ' : '') + (item.day ? item.day + ': ' : '') + (item.message || 'Conflict'), 'warning');
      });
    }

    result.innerHTML = `
      <div class="alert alert-success">
        <h3>Success</h3>
        <p><strong>${json.schedules_created || 0}</strong> schedules created.</p>
        ${json.default_time_slots_created ? '<p>Default time slots were created automatically for this section.</p>' : ''}
        ${(json.conflicts_found || 0) > 0 ? `<p>${json.conflicts_found} item(s) could not be placed.</p>` : ''}
        <p><a href="#" onclick="return openView('${sectionId}')" style="color:#155724;text-decoration:underline;font-weight:bold;">View Automated Schedules</a></p>
      </div>
    `;
  } catch (error) {
    setProgress(100, 'Error');
    addLog(error.message || 'Automation failed.', 'error');
    result.innerHTML = `<div class="alert alert-danger"><h3>Error</h3><p>${error.message || 'Automation failed.'}</p></div>`;
  } finally {
    button.disabled = false;
    button.textContent = 'Automate Schedules';
  }
});

function openView(sectionId) {
  try {
    if (window.parent && window.parent !== window && typeof window.parent.openScheduleViewForSection === 'function') {
      window.parent.openScheduleViewForSection(sectionId, 'automated');
      return false;
    }
  } catch (error) {
    console.error(error);
  }
  window.location.href = '/mainscheduler/tabs/schedule_view.php?section_id=' + encodeURIComponent(sectionId || '');
  return false;
}

document.getElementById('section-select')?.addEventListener('change', function () {
  notifyParent({ type: 'section-change', source: 'generate', sectionId: this.value || '' });
});
</script>
</body>
</html>
