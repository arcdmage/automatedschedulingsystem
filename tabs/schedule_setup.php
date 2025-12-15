<?php
require_once(__DIR__ . '/../db_connect.php');

// Fetch data logic remains the same...
$sections_query = "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);

$subjects_result = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
$faculty_result = $conn->query("SELECT faculty_id, fname, lname FROM faculty ORDER BY lname");

$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$requirements = [];
if ($selected_section) {
  $req_query = "SELECT sr.*, s.subject_name, CONCAT(f.lname, ', ', f.fname) AS teacher_name
                FROM subject_requirements sr
                JOIN subjects s ON sr.subject_id = s.subject_id
                JOIN faculty f ON sr.faculty_id = f.faculty_id
                WHERE sr.section_id = ? ORDER BY s.subject_name";
  $stmt = $conn->prepare($req_query);
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
<!-- LINK TO NEW CSS -->
<link rel="stylesheet" href="css/schedule.css"> 
</head>
<body>

<div class="page-header">
  <div>
    <h1>Subject Setup</h1>
    <p>Configure required subjects and teachers for each section</p>
  </div>
</div>

<!-- Section Selector -->
<div class="card-section">
  <label for="section-select">Select Section</label>
  <select id="section-select" onchange="window.location.href='?section_id='+this.value">
    <option value="">-- Choose a Section --</option>
    <?php 
    $sections_result->data_seek(0);
    while($section = $sections_result->fetch_assoc()): 
    ?>
      <option value="<?php echo $section['section_id']; ?>" 
              <?php echo ($selected_section == $section['section_id']) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($section['grade_level'] . ' - ' . $section['section_name']); ?>
      </option>
    <?php endwhile; ?>
  </select>
</div>

<?php if ($selected_section): ?>

<!-- Add Requirement Form -->
<div class="card-section">
  <h2>Add New Subject</h2>
  <form id="requirement-form">
    <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
    
    <div class="form-grid">
      <div class="form-group">
        <label>Subject <span style="color:red">*</span></label>
        <select name="subject_id" required>
          <option value="">-- Select Subject --</option>
          <?php 
          $subjects_result->data_seek(0);
          while($s = $subjects_result->fetch_assoc()) echo "<option value='{$s['subject_id']}'>{$s['subject_name']}</option>"; 
          ?>
        </select>
      </div>
      
      <div class="form-group">
        <label>Teacher <span style="color:red">*</span></label>
        <select name="faculty_id" required>
          <option value="">-- Select Teacher --</option>
          <?php 
          $faculty_result->data_seek(0);
          while($f = $faculty_result->fetch_assoc()) echo "<option value='{$f['faculty_id']}'>{$f['lname']}, {$f['fname']}</option>"; 
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Hours / Week <span style="color:red">*</span></label>
        <input type="number" name="hours_per_week" min="1" max="10" value="1" required>
      </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Add Subject Requirement</button>
  </form>
</div>

<!-- Table -->
<div class="card-section">
  <h2>Configured Subjects</h2>
  <?php if (count($requirements) > 0): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th>Teacher</th>
          <th>Hours</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($requirements as $req): ?>
          <tr>
            <td><?php echo htmlspecialchars($req['subject_name']); ?></td>
            <td><?php echo htmlspecialchars($req['teacher_name']); ?></td>
            <td><?php echo $req['hours_per_week']; ?> hrs</td>
            <td>
              <button class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" 
                      onclick="deleteRequirement(<?php echo $req['requirement_id']; ?>)">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-warning">No subjects configured yet.</div>
  <?php endif; ?>
</div>

<?php else: ?>
  <div class="alert alert-info">Please select a section above to start configuration.</div>
<?php endif; ?>

<script>
// Keep your existing AJAX logic here for adding/deleting
document.getElementById('requirement-form')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('/mainscheduler/tabs/actions/requirement_save.php', { method: 'POST', body: formData })
    .then(r => r.json()).then(data => {
      if(data.success) window.location.reload();
      else alert(data.message);
    });
});
function deleteRequirement(id) {
  if(!confirm('Delete this?')) return;
  const fd = new FormData(); fd.append('requirement_id', id);
  fetch('/mainscheduler/tabs/actions/requirement_delete.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => { if(data.success) window.location.reload(); });
}
</script>

</body>
</html>