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
    <strong>ℹ️ How it works:</strong> The system will build a reusable Monday&ndash;Friday weekly template
    from your configured subject patterns. No dates needed &mdash; the template covers the full week.
  </div>

  <div class="alert alert-warning">
    <strong>⚠️ Note:</strong> Generating a new template will replace any existing auto-generated rows
    for this section. Manually-entered schedules are not affected.
  </div>

  <!-- Template Generate Form (no dates needed) -->
  <form id="generate-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">

    <!-- Mode toggle -->
    <div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:18px; margin-bottom:18px;">
      <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer; margin:0;">
        <input type="checkbox" name="random_mode" id="random-mode-toggle" value="1"
               style="margin-top:3px; width:18px; height:18px; accent-color:#e67e22; cursor:pointer; flex-shrink:0;">
        <span>
          <strong style="font-size:15px;">🎲 Random Schedule Mode</strong><br>
          <span style="color:#666; font-size:13px; line-height:1.5;">
            Skip saved patterns and randomly distribute each subject across available
            Mon&ndash;Fri time slots based on its <em>hours per week</em>. Useful for quickly
            generating a draft or when patterns haven't been configured yet.
          </span>
        </span>
      </label>
    </div>

    <button type="submit" class="btn-generate" id="generate-btn">
      🚀 Generate Weekly Template
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

// Update button label when random mode is toggled
document.getElementById('random-mode-toggle')?.addEventListener('change', function() {
  const btn = document.getElementById('generate-btn');
  if (btn) {
    btn.textContent = this.checked
      ? '🎲 Generate Random Schedule'
      : '🚀 Generate Weekly Template';
  }
});

// Handle form submission
document.getElementById('generate-form')?.addEventListener('submit', function(e) {
  e.preventDefault();

  const isRandom = document.getElementById('random-mode-toggle')?.checked ?? false;

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

  const endpoint = isRandom
    ? '/mainscheduler/tabs/actions/schedule_random_generate.php'
    : '/mainscheduler/tabs/actions/schedule_auto_generate.php';

  addLog(isRandom
    ? 'Random mode: distributing subjects across available slots...'
    : 'Pattern mode: applying saved schedule patterns...', 'info');
  progressFill.style.width = '30%';
  progressFill.textContent = '30%';
  
  fetch(endpoint, {
    method: 'POST',
    body: formData
  })
  .then(response => {
    // Get raw text first so we can show it if JSON parsing fails
    return response.text().then(text => {
      if (!text || text.trim() === '') {
        throw new Error('Server returned empty response. Check PHP error logs.');
      }
      try {
        return JSON.parse(text);
      } catch(e) {
        // Show first 300 chars of raw response to help diagnose
        const preview = text.substring(0, 300).replace(/</g, '&lt;').replace(/>/g, '&gt;');
        throw new Error('Invalid JSON from server. Raw response:<br><pre style="font-size:11px;margin-top:5px;">' + preview + '</pre>');
      }
    });
  })
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
    addLog('✗ ' + error.message.replace(/<[^>]*>/g,'').substring(0,120), 'error');
    
    resultMessage.innerHTML = `
      <div class="alert alert-danger">
        <h3>❌ Error</h3>
        <p>${error.message}</p>
      </div>
    `;
    
    generateBtn.disabled = false;
    generateBtn.textContent = '🚀 Generate Weekly Template';
  });
});
</script>

</body>
</html>