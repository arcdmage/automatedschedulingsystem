<?php
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/../lib/schedule_helpers.php');
// Fetch all sections
$sections_query = "SELECT s.section_id, s.section_name, s.grade_level, s.track, s.school_year, s.semester,
                   CONCAT(f.lname, ', ', f.fname) AS adviser_name
                   FROM sections s
                   LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                   ORDER BY s.grade_level, s.section_name";
$sections_result = $conn->query($sections_query);

// Get selected section if any
$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

// Fetch subject requirements count for selected section
$requirements_count = 0;
$total_hours = 0;
if ($selected_section) {
  $count_query = "SELECT COUNT(*) as count, SUM(hours_per_week) as total_hours
                  FROM subject_requirements 
                  WHERE section_id = ?";
  $stmt = $conn->prepare($count_query);
  $stmt->bind_param("i", $selected_section);
  $stmt->execute();
  $count_result = $stmt->get_result()->fetch_assoc();
  $requirements_count = $count_result['count'];
  $total_hours = $count_result['total_hours'] ?? 0;
  $stmt->close();
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
  <h1>Auto-Generate Schedules</h1>
  <p>Automatically generate weekly schedules based on configured subject requirements</p>
</div>

<!-- Section Selector -->
<div class="section-selector">
  <label for="section-select">Select Section:</label>
  <select id="section-select" onchange="loadSection()">
    <option value="">-- Choose a Section --</option>
    <?php while($section = $sections_result->fetch_assoc()): ?>
      <option value="<?php echo $section['section_id']; ?>" 
              <?php echo ($selected_section == $section['section_id']) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($section['grade_level'] . ' - ' . $section['section_name'] . 
                   ' (' . $section['track'] . ') - ' . $section['school_year'] . ' ' . $section['semester']); ?>
        <?php if ($section['adviser_name']): ?>
          - Adviser: <?php echo htmlspecialchars($section['adviser_name']); ?>
        <?php endif; ?>
      </option>
    <?php endwhile; ?>
  </select>
</div>

<?php if ($selected_section): ?>

  <?php if ($requirements_count > 0): ?>

<!-- Generator Section -->
<div class="generator-section">
  <h2>Generation Settings</h2>
  
  <!-- Info Cards -->
  <div class="info-grid">
    <div class="info-card success">
      <h3>Subjects Configured</h3>
      <div class="big-number"><?php echo $requirements_count; ?></div>
      <p>subjects ready</p>
    </div>
    
    <div class="info-card">
      <h3>Total Hours/Week</h3>
      <div class="big-number"><?php echo $total_hours; ?></div>
      <p>hours to schedule</p>
    </div>
    
    <div class="info-card warning">
      <h3>Available Slots/Week</h3>
      <div class="big-number">35</div>
      <p>time slots (7 per day)</p>
    </div>
  </div>
  
  <div class="alert alert-info">
    <strong>ℹ️ How it works:</strong> The system will automatically distribute <?php echo $total_hours; ?> hours 
    of classes across the week, avoiding conflicts and break times. Each subject will be placed according to 
    its configured hours per week.
  </div>
  
  <!-- Date Range Selection -->
  <form id="generate-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
    
    <div class="date-range-group">
      <div class="form-group">
        <label for="start_date">Start Date <span style="color:red;">*</span></label>
        <input type="date" name="start_date" id="start_date" required>
      </div>
      
      <div class="form-group">
        <label for="end_date">End Date <span style="color:red;">*</span></label>
        <input type="date" name="end_date" id="end_date" required>
      </div>
    </div>
    
    <div class="alert alert-warning">
      <strong>⚠️ Note:</strong> This will generate schedules for all weekdays (Monday-Friday) within the selected 
      date range. Existing schedules for this section in the date range will be preserved unless there are conflicts.
    </div>
    
    <button type="submit" class="btn-generate" id="generate-btn">
       Generate Schedules
    </button>
  </form>
</div>

<!-- Progress Section -->
<div class="progress-section" id="progress-section">
  <h2>Generation Progress</h2>
  
  <div class="progress-bar">
    <div class="progress-fill" id="progress-fill">0%</div>
  </div>
  
  <div class="progress-log" id="progress-log">
    <div class="log-entry info">Waiting to start...</div>
  </div>
  
  <div id="result-message" style="margin-top: 20px;"></div>
</div>

  <?php else: ?>

<div class="alert alert-warning">
  <strong>⚠️ No subjects configured!</strong> 
  <p>You need to configure subject requirements before generating schedules.</p>
  <p>
    <a href="/mainscheduler/tabs/schedule_setup.php?section_id=<?php echo $selected_section; ?>" 
       style="color: #856404; text-decoration: underline; font-weight: bold;">
      Go to Subject Setup →
    </a>
  </p>
</div>

  <?php endif; ?>

<?php else: ?>

<div class="alert alert-warning">
  <strong> Please select a section</strong> to generate schedules.
</div>

<?php endif; ?>

<script>
function loadSection() {
  const sectionId = document.getElementById('section-select').value;
  if (sectionId) {
    window.location.href = '/mainscheduler/tabs/schedule_generate.php?section_id=' + sectionId;
  }
}

// Set default dates (current week)
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const nextMonth = new Date(today);
    
    // Handle the "Next Month" rollover correctly
    // (e.g., prevent Jan 31 + 1 month becoming March 3)
    nextMonth.setMonth(today.getMonth() + 1);

    // Helper function to get YYYY-MM-DD in LOCAL time
    function getLocalDateString(date) {
        const year = date.getFullYear();
        // padStart ensures we get '05' instead of just '5'
        const month = String(date.getMonth() + 1).padStart(2, '0'); 
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // Check if elements exist to prevent errors
    if (startDateInput) {
        startDateInput.value = getLocalDateString(today);
    }
    if (endDateInput) {
        endDateInput.value = getLocalDateString(nextMonth);
    }
});

// Handle form submission
document.getElementById('generate-form')?.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const generateBtn = document.getElementById('generate-btn');
  const progressSection = document.getElementById('progress-section');
  const progressFill = document.getElementById('progress-fill');
  const progressLog = document.getElementById('progress-log');
  const resultMessage = document.getElementById('result-message');
  
  // Disable button and show progress
  generateBtn.disabled = true;
  generateBtn.textContent = '⏳ Generating...';
  progressSection.classList.add('active');
  progressLog.innerHTML = '<div class="log-entry info">Starting generation process...</div>';
  progressFill.style.width = '10%';
  progressFill.textContent = '10%';
  resultMessage.innerHTML = '';
  
  const formData = new FormData(this);
  
  // Add log entry
  function addLog(message, type = 'info') {
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + type;
    entry.textContent = message;
    progressLog.appendChild(entry);
    progressLog.scrollTop = progressLog.scrollHeight;
  }
  
  addLog('Sending request to server...', 'info');
  progressFill.style.width = '30%';
  progressFill.textContent = '30%';
  
  fetch('/mainscheduler/tabs/actions/schedule_auto_generate.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    progressFill.style.width = '100%';
    progressFill.textContent = '100%';
    
    // Display debug log if available
    if (data.debug_log && data.debug_log.length > 0) {
      addLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
      addLog('📋 DEBUG LOG (showing conflict details):', 'info');
      addLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
      data.debug_log.forEach(msg => {
        if (msg.includes('CONFLICTS FOUND')) {
          addLog(msg, 'error');
        } else if (msg.includes('✓')) {
          addLog(msg, 'success');
        } else {
          addLog(msg, 'info');
        }
      });
      addLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
    }
    
    if (data.success) {
      addLog('✓ Generation completed successfully!', 'success');
      addLog(`✓ Created ${data.schedules_created} schedule entries`, 'success');
      
      if (data.conflicts_found > 0) {
        addLog(`⚠ ${data.conflicts_found} conflicts detected and skipped`, 'error');
      }
      
      resultMessage.innerHTML = `
        <div class="alert alert-success">
          <h3>Success!</h3>
          <p><strong>${data.schedules_created}</strong> schedules created successfully.</p>
          ${data.conflicts_found > 0 ? `<p>⚠️ ${data.conflicts_found} conflicts were skipped.</p>` : ''}
          <p>
            <a href="/mainscheduler/tabs/schedule_view.php?section_id=${formData.get('section_id')}" 
               style="color: #155724; text-decoration: underline; font-weight: bold;">
              View Generated Schedules →
            </a>
          </p>
        </div>
      `;
      
      // Re-enable button
      setTimeout(() => {
        generateBtn.disabled = false;
        generateBtn.textContent = '🚀 Generate Schedules';
      }, 2000);
      
    } else {
      addLog('✗ Generation failed: ' + data.message, 'error');
      
      resultMessage.innerHTML = `
        <div class="alert alert-danger">
          <h3>❌ Error</h3>
          <p>${data.message}</p>
        </div>
      `;
      
      generateBtn.disabled = false;
      generateBtn.textContent = '🚀 Generate Schedules';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    addLog('✗ Network error occurred', 'error');
    
    resultMessage.innerHTML = `
      <div class="alert alert-danger">
        <h3>❌ Error</h3>
        <p>Failed to generate schedules. Please try again.</p>
      </div>
    `;
    
    generateBtn.disabled = false;
    generateBtn.textContent = '🚀 Generate Schedules';
  });
});
</script>

</body>
</html>