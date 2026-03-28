<?php
require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../lib/scheduler_staff_helpers.php";
$appBase = app_url();

$selected_section = isset($_GET["section_id"])
    ? intval($_GET["section_id"])
    : null;
$sections_result = $conn->query(
    "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name",
);
$subjects_result = $conn->query(
    "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name",
);
$faculty_rows = available_faculty_rows($conn);

$requirements = [];
if ($selected_section) {
    $stmt = $conn->prepare(
        "SELECT sr.requirement_id, sr.subject_id, sr.faculty_id, s.subject_name,
                COALESCE(CONCAT(f.lname, ', ', f.fname), 'Auto-assign in Automated Generate') AS teacher_name
         FROM subject_requirements sr
         JOIN subjects s ON sr.subject_id = s.subject_id
         LEFT JOIN faculty f ON sr.faculty_id = f.faculty_id
         WHERE sr.section_id = ?
         ORDER BY s.subject_name",
    );
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
<link rel="stylesheet" href="<?= htmlspecialchars(
    app_url("tabs/css/schedule.css"),
    ENT_QUOTES,
    "UTF-8",
) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(
    app_url("tabs/css/schedule_setup.css"),
    ENT_QUOTES,
    "UTF-8",
) ?>">
</head>
<body>
<div class="page-header">
  <div>
    <h1>Automated Subject Setup</h1>
    <p>Choose the section subjects. Automated scheduling will default each class to 1 hour.</p>
  </div>
</div>

<div class="card-section">
  <label for="section-select">Select Section</label>
  <select id="section-select" onchange="handleSectionChange(this.value)">
    <option value="">-- Choose a Section --</option>
    <?php while ($section = $sections_result->fetch_assoc()): ?>
      <option value="<?php echo $section[
          "section_id"
      ]; ?>" <?php echo $selected_section == $section["section_id"]
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

<?php if ($selected_section): ?>
<div class="card-section">
  <h2>Add Subject</h2>
  <form id="automated-add-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
    <input type="hidden" name="hours_per_week" value="60">
    <input type="hidden" name="auto_create_time_slots" value="1">
    <div class="form-grid">
      <div class="form-group">
        <label>Subject <span style="color:red">*</span></label>
        <select name="subject_id" required>
          <option value="">-- Select Subject --</option>
          <?php
          $subjects_result->data_seek(0);
          while ($subject = $subjects_result->fetch_assoc()): ?>
            <option value="<?php echo $subject[
                "subject_id"
            ]; ?>"><?php echo htmlspecialchars(
    $subject["subject_name"],
); ?></option>
          <?php endwhile;
          ?>
        </select>
      </div>
      <div class="form-group">
        <label>Teacher</label>
        <select name="faculty_id">
          <option value="0">Auto-assign in Automated Generate</option>
          <?php foreach ($faculty_rows as $faculty): ?>
            <option value="<?php echo $faculty[
                "faculty_id"
            ]; ?>"><?php echo htmlspecialchars(
    $faculty["lname"] . ", " . $faculty["fname"],
); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Add Subject</button>
  </form>
</div>

<div class="card-section">
  <h2>Configured Automated Subjects</h2>
  <?php if (!empty($requirements)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th>Teacher</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requirements as $req): ?>
          <tr>
            <td><?php echo htmlspecialchars(
                $req["subject_name"] ?? "Unknown subject",
            ); ?></td>
            <td><?php echo htmlspecialchars(
                $req["teacher_name"] ?? "Auto-assign in Automated Generate",
            ); ?></td>
            <td>
              <button class="btn btn-danger" style="padding:5px 10px; font-size:12px;" onclick="deleteRequirement(<?php echo (int) $req[
                  "requirement_id"
              ]; ?>)">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-info">No subjects configured yet for automated scheduling.</div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="alert alert-info">Please select a section above to configure automated subjects.</div>
<?php endif; ?>

<script>
const APP_BASE = <?= json_encode($appBase) ?>;
function handleSectionChange(value) {
  if (!value) return;
  window.location.href = '?section_id=' + encodeURIComponent(value);
}

document.getElementById('automated-add-form')?.addEventListener('submit', async function (e) {
  e.preventDefault();
  try {
    const response = await fetch(`${APP_BASE}/tabs/actions/requirement_save.php`, {
      method: 'POST',
      body: new FormData(this)
    });
    const text = await response.text();
    const json = JSON.parse(text);
    if (!json.success) {
      alert(json.message || 'Failed to add subject.');
      return;
    }
    if (json.default_time_slots_created) {
      alert('Subject added. Default time slots were created automatically for this section.');
    }
    window.location.reload();
  } catch (error) {
    console.error(error);
    alert(error.message || 'Failed to add subject.');
  }
});

async function deleteRequirement(id) {
  if (!confirm('Delete this automated subject assignment?')) return;
  const formData = new FormData();
  formData.append('requirement_id', id);
  const response = await fetch(`${APP_BASE}/tabs/actions/requirement_delete.php`, {
    method: 'POST',
    body: formData
  });
  const json = await response.json();
  if (!json.success) {
    alert(json.message || 'Failed to delete subject.');
    return;
  }
  window.location.reload();
}
</script>
</body>
</html>
