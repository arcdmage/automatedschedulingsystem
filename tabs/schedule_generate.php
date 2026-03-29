<?php
require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../lib/schedule_helpers.php";
require_once __DIR__ . "/../lib/subject_duration_helpers.php";
$sections_query = "SELECT s.section_id, s.section_name, s.grade_level, s.track, s.school_year, s.semester,
                   CONCAT(f.lname, ', ', f.fname) AS adviser_name
                   FROM sections s
                   LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                   ORDER BY s.grade_level, s.section_name";
$sections_result = $conn->query($sections_query);
$selected_section = isset($_GET["section_id"]) ? intval($_GET["section_id"]) : null;
$requirements_count = 0;
$total_minutes = 0;
if ($selected_section) {
    $count_query = "SELECT hours_per_week FROM subject_requirements WHERE section_id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $selected_section);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requirements_count++;
        $total_minutes += weekly_subject_duration_minutes($row["hours_per_week"] ?? 0);
    }
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
<div class="section-selector">
  <label for="section-select">Select Section:</label>
  <select id="section-select" onchange="loadSection()">
    <option value="">-- Choose a Section --</option>
    <?php while ($section = $sections_result->fetch_assoc()): ?>
      <option value="<?php echo $section["section_id"]; ?>" <?php echo $selected_section == $section["section_id"] ? "selected" : ""; ?>>
        <?php echo htmlspecialchars(
            $section["grade_level"] .
                " - " .
                $section["section_name"] .
                " (" .
                $section["track"] .
                ") - " .
                $section["school_year"] .
                " " .
                $section["semester"],
        ); ?>
        <?php if ($section["adviser_name"]): ?>
          - Adviser: <?php echo htmlspecialchars($section["adviser_name"]); ?>
        <?php endif; ?>
      </option>
    <?php endwhile; ?>
  </select>
</div>

<?php if ($selected_section): ?>
  <?php if ($requirements_count > 0): ?>
<div class="generator-section">
  <h2>Generation Settings</h2>
  <div class="info-grid">
    <div class="info-card success">
      <h3>Subjects Configured</h3>
      <div class="big-number"><?php echo $requirements_count; ?></div>
      <p>subjects ready</p>
    </div>
    <div class="info-card">
      <h3>Total Time/Week</h3>
      <div class="big-number"><?php echo htmlspecialchars(format_subject_duration_minutes($total_minutes)); ?></div>
      <p>time to schedule</p>
    </div>
    <div class="info-card warning">
      <h3>Available Slots/Week</h3>
      <div class="big-number">35</div>
      <p>time slots (7 per day)</p>
    </div>
  </div>
  <div class="alert alert-info">
    <strong>How it works:</strong> The system will build a reusable Monday-Friday weekly template
    from your configured subject patterns. No dates needed.
  </div>
  <div class="alert alert-warning">
    <strong>Note:</strong> Generating a new template will replace any existing auto-generated rows
    for this section. Manually-entered schedules are not affected.
  </div>
  <form id="generate-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
    <div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:18px; margin-bottom:18px;">
      <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer; margin:0;">
        <input type="checkbox" name="random_mode" id="random-mode-toggle" value="1" style="margin-top:3px; width:18px; height:18px; accent-color:#e67e22; cursor:pointer; flex-shrink:0;">
        <span>
          <strong style="font-size:15px;">Random Schedule Mode</strong><br>
          <span style="color:#666; font-size:13px; line-height:1.5;">Skip saved patterns and randomly distribute each subject across available Mon-Friday time slots based on its hour per subject.</span>
        </span>
      </label>
    </div>
    <button type="submit" class="btn-generate" id="generate-btn">Generate Weekly Template</button>
  </form>
</div>
<div class="progress-section" id="progress-section">
  <h2>Generation Progress</h2>
  <div class="progress-bar"><div class="progress-fill" id="progress-fill">0%</div></div>
  <div class="progress-log" id="progress-log"><div class="log-entry info">Waiting to start...</div></div>
  <div id="result-message" style="margin-top: 20px;"></div>
</div>
  <?php else: ?>
<div class="alert alert-warning">
  <strong>No subjects configured.</strong>
  <p>You need to configure subject requirements before generating schedules.</p>
  <p><a href="/mainscheduler/tabs/schedule_setup.php?section_id=<?php echo $selected_section; ?>" style="color: #856404; text-decoration: underline; font-weight: bold;">Go to Subject Setup</a></p>
</div>
  <?php endif; ?>
<?php else: ?>
<div class="alert alert-warning"><strong>Please select a section</strong> to generate schedules.</div>
<?php endif; ?>

<div id="conflictModal" class="modal" aria-hidden="true" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; z-index:1000;">
  <div class="modal-content" style="background:#fff; padding:18px; border-radius:6px; max-width:800px; width:90%; box-shadow:0 4px 16px rgba(0,0,0,0.2);">
    <h3 id="modalTitle" style="margin-top:0;">Conflicts detected</h3>
    <p id="modalMessage" style="margin-bottom:12px;">The generation detected conflicts. Review below and confirm to overwrite auto-generated schedules.</p>
    <div id="conflictList" style="max-height:320px; overflow:auto; border:1px solid #eee; padding:8px; border-radius:4px; background:#fafafa; margin-bottom:10px;"></div>
    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
      <button id="modalCancelBtn" style="padding:8px 12px; background:#eee; border:0; border-radius:4px; cursor:pointer;">Cancel</button>
      <button id="modalConfirmBtn" style="padding:8px 12px; background:#c62828; color:#fff; border:0; border-radius:4px; cursor:pointer;">Proceed & Replace</button>
    </div>
  </div>
</div>
<script src="js/schedule_generate_conflicts.js"></script>
</body>
</html>
