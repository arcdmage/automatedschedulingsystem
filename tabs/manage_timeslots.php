<?php
require_once __DIR__ . "/../db_connect.php";

// Fetch all sections
$sections_query =
    "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);

$selected_section = isset($_GET["section_id"])
    ? intval($_GET["section_id"])
    : null;

// Fetch time slots for selected section
$timeslots_result = null;
if ($selected_section) {
    $timeslots_query =
        "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
    $stmt = $conn->prepare($timeslots_query);
    $stmt->bind_param("i", $selected_section);
    $stmt->execute();
    $timeslots_result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/schedule.css">
<link rel="stylesheet" href="css/manage_timeslots.css">
</head>
<body>

<!-- Top bar with back button -->
<div class="ts-topbar">
  <a href="/mainscheduler/tabs/schedule_setup.php<?php echo $selected_section
      ? "?section_id=" . $selected_section
      : ""; ?>" class="btn-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"></polyline>
    </svg>
    Back to Subject Setup
  </a>
  <div>
    <p class="ts-topbar-title">Manage Time Slots</p>
    <p class="ts-topbar-sub">Configure schedule time blocks per section</p>
  </div>
</div>

<div class="ts-wrap">

  <!-- Section Selector -->
  <div class="ts-card">
    <div class="ts-section-row">
      <label for="section-select">Section</label>
      <select id="section-select" onchange="window.location.href='?section_id='+this.value" style="max-width:380px;">
        <option value="">-- Choose a Section --</option>
        <?php
        $sections_result->data_seek(0);
        while ($section = $sections_result->fetch_assoc()): ?>
          <option value="<?php echo $section["section_id"]; ?>"
                  <?php echo $selected_section == $section["section_id"]
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
        <?php endwhile;
        ?>
      </select>
    </div>
  </div>

  <?php if ($selected_section): ?>

  <!-- Copy from Another Section -->
  <div class="ts-copy-banner">
    <div class="ts-card-title">Copy Time Slots from Another Section</div>
    <div class="ts-copy-row">
      <div class="form-group">
        <label>Copy from</label>
        <select id="copy-from-section">
          <option value="">-- Select source section --</option>
          <?php
          $sections_result->data_seek(0);
          while ($sec = $sections_result->fetch_assoc()):
              if ($sec["section_id"] != $selected_section): ?>
            <option value="<?php echo $sec["section_id"]; ?>">
              <?php echo htmlspecialchars(
                  $sec["section_name"] . " (" . $sec["track"] . ")",
              ); ?>
            </option>
          <?php endif;
          endwhile;
          ?>
        </select>
      </div>
      <button onclick="copyTimeSlots()" class="btn btn-ghost">Copy Slots</button>
    </div>
  </div>

  <!-- Add New Time Slot -->
  <div class="ts-card">
    <div class="ts-card-title">Add New Time Slot</div>
    <form id="add-timeslot-form">
      <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Start Time *</label>
          <input type="time" name="start_time" required>
        </div>
        <div class="form-group">
          <label>End Time *</label>
          <input type="time" name="end_time" required>
        </div>
        <div class="form-group">
          <label>Type</label>
          <div class="toggle-wrap">
            <label class="toggle-switch">
              <input type="checkbox" name="is_break" id="is_break_toggle" onchange="toggleBreakLabel()">
              <span class="toggle-slider"></span>
            </label>
            <span class="toggle-label" id="break_toggle_label">Class period</span>
          </div>
        </div>
        <div class="form-group" id="break_label_group" style="display:none;">
          <label>Break Label *</label>
          <input type="text" name="break_label" id="break_label_input" placeholder="e.g., LUNCH BREAK">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Add Time Slot</button>
    </form>
  </div>

  <!-- Existing Time Slots -->
  <div class="ts-card">
    <div class="ts-card-title">Configured Time Slots</div>
    <p class="ts-hint">Use the arrows to reorder. Order determines how slots appear in schedules.</p>

    <?php if ($timeslots_result && $timeslots_result->num_rows > 0): ?>
      <table class="ts-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Time Range</th>
            <th>Type</th>
            <th>Label</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($slot = $timeslots_result->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="order-controls">
                  <button class="order-btn" onclick="moveSlot(<?php echo $slot[
                      "time_slot_id"
                  ]; ?>, 'up')" title="Move Up">▲</button>
                  <span class="order-num"><?php echo $slot[
                      "slot_order"
                  ]; ?></span>
                  <button class="order-btn" onclick="moveSlot(<?php echo $slot[
                      "time_slot_id"
                  ]; ?>, 'down')" title="Move Down">▼</button>
                </div>
              </td>
              <td>
                <span class="time-range">
                  <?php echo date("g:i A", strtotime($slot["start_time"])) .
                      " – " .
                      date("g:i A", strtotime($slot["end_time"])); ?>
                </span>
              </td>
              <td>
                <?php if ($slot["is_break"]): ?>
                  <span class="badge badge-break">Break</span>
                <?php else: ?>
                  <span class="badge badge-class">Class</span>
                <?php endif; ?>
              </td>
              <td style="color:#6b7280;">
                <?php echo $slot["is_break"]
                    ? htmlspecialchars($slot["break_label"])
                    : "—"; ?>
              </td>
              <td>
                <div class="timeslot-actions">
                  <button class="btn btn-ghost btn-sm"
                          onclick="openEditModal(<?php echo htmlspecialchars(
                              json_encode($slot),
                              ENT_QUOTES,
                          ); ?>)">
                    Edit
                  </button>
                  <button class="btn btn-danger btn-sm"
                          onclick="deleteTimeslot(<?php echo $slot[
                              "time_slot_id"
                          ]; ?>)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="ts-alert">No time slots configured yet. Add one above or copy from another section.</div>
    <?php endif; ?>
  </div>

  <?php else: ?>
    <div class="ts-alert" style="margin-top:0;">Select a section above to manage its time slots.</div>
  <?php endif; ?>

</div><!-- /.ts-wrap -->

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Time Slot</h2>
      <button class="btn-close" onclick="closeEditModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="edit-timeslot-form">
        <input type="hidden" name="time_slot_id" id="edit_time_slot_id">
        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Start Time *</label>
            <input type="time" name="start_time" id="edit_start_time" required>
          </div>
          <div class="form-group">
            <label>End Time *</label>
            <input type="time" name="end_time" id="edit_end_time" required>
          </div>
          <div class="form-group">
            <label>Type</label>
            <div class="toggle-wrap">
              <label class="toggle-switch">
                <input type="checkbox" name="is_break" id="edit_is_break" onchange="toggleEditBreakLabel()">
                <span class="toggle-slider"></span>
              </label>
              <span class="toggle-label" id="edit_break_toggle_label">Class period</span>
            </div>
          </div>
          <div class="form-group" id="edit_break_label_group" style="display:none;">
            <label>Break Label *</label>
            <input type="text" name="break_label" id="edit_break_label" placeholder="e.g., LUNCH BREAK">
          </div>
        </div>
        <button type="submit" class="btn btn-success">Save Changes</button>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" onclick="closeEditModal()" class="btn btn-ghost">Cancel</button>
    </div>
  </div>
</div>

<script>
const currentSectionId = <?php echo $selected_section
    ? $selected_section
    : "null"; ?>;

function toggleBreakLabel() {
  const isBreak = document.getElementById('is_break_toggle').checked;
  document.getElementById('break_label_group').style.display = isBreak ? 'flex' : 'none';
  document.getElementById('break_label_input').required = isBreak;
  if (!isBreak) document.getElementById('break_label_input').value = '';
  document.getElementById('break_toggle_label').textContent = isBreak ? 'Break / Lunch' : 'Class period';
}

function toggleEditBreakLabel() {
  const isBreak = document.getElementById('edit_is_break').checked;
  document.getElementById('edit_break_label_group').style.display = isBreak ? 'flex' : 'none';
  document.getElementById('edit_break_label').required = isBreak;
  if (!isBreak) document.getElementById('edit_break_label').value = '';
  document.getElementById('edit_break_toggle_label').textContent = isBreak ? 'Break / Lunch' : 'Class period';
}

async function copyTimeSlots() {
  const fromSectionId = document.getElementById('copy-from-section').value;
  if (!fromSectionId) { alert('Please select a section to copy from'); return; }
  if (!confirm('This will replace all time slots in the current section. Continue?')) return;
  const formData = new FormData();
  formData.append('from_section_id', fromSectionId);
  formData.append('to_section_id', currentSectionId);
  try {
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_copy.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) { alert(`Copied ${data.slots_copied} time slot(s).`); window.location.reload(); }
    else alert('Error: ' + data.message);
  } catch { alert('Error copying time slots'); }
}

document.getElementById('add-timeslot-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.set('is_break', document.getElementById('is_break_toggle').checked ? '1' : '0');
  try {
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_create.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else alert('Error: ' + data.message);
  } catch { alert('Error adding time slot'); }
});

function openEditModal(slot) {
  document.getElementById('edit_time_slot_id').value = slot.time_slot_id;
  document.getElementById('edit_start_time').value   = slot.start_time.substring(0, 5);
  document.getElementById('edit_end_time').value     = slot.end_time.substring(0, 5);
  document.getElementById('edit_is_break').checked   = slot.is_break == 1;
  document.getElementById('edit_break_label').value  = slot.break_label || '';
  toggleEditBreakLabel();
  document.getElementById('edit-modal').classList.add('open');
}

function closeEditModal() {
  document.getElementById('edit-modal').classList.remove('open');
}

document.getElementById('edit-timeslot-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.set('is_break', document.getElementById('edit_is_break').checked ? '1' : '0');
  try {
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_update.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else alert('Error: ' + data.message);
  } catch { alert('Error updating time slot'); }
});

async function deleteTimeslot(id) {
  if (!confirm('Delete this time slot? This may affect existing schedules.')) return;
  const formData = new FormData();
  formData.append('time_slot_id', id);
  formData.append('section_id', currentSectionId);
  try {
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_delete.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else alert('Error: ' + data.message);
  } catch { alert('Error deleting time slot'); }
}

async function moveSlot(id, direction) {
  const formData = new FormData();
  formData.append('time_slot_id', id);
  formData.append('direction', direction);
  formData.append('section_id', currentSectionId);
  try {
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_reorder.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else alert('Error: ' + data.message);
  } catch { alert('Error reordering time slot'); }
}

window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('edit-modal')) closeEditModal();
});
</script>

</body>
</html>
