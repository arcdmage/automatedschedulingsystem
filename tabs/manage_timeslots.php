<?php
require_once(__DIR__ . '/../db_connect.php');

// Fetch all sections
$sections_query = "SELECT section_id, section_name, grade_level, track FROM sections ORDER BY grade_level, section_name";
$sections_result = $conn->query($sections_query);

$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;

// Fetch time slots for selected section
$timeslots_result = null;
if ($selected_section) {
  $timeslots_query = "SELECT * FROM time_slots WHERE section_id = ? ORDER BY slot_order";
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
<style>
  *, *::before, *::after { box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: #f5f6fa;
    color: #2d3748;
    margin: 0;
    padding: 0 0 48px;
    font-size: 14px;
  }

  /* ── Top bar ── */
  .ts-topbar {
    background: #fff;
    border-bottom: 1px solid #e8eaed;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, border-color .15s;
    white-space: nowrap;
  }
  .btn-back:hover { background: #f3f4f6; border-color: #9ca3af; }
  .ts-topbar-title { font-size: 15px; font-weight: 600; color: #111827; margin: 0; }
  .ts-topbar-sub   { font-size: 12px; color: #6b7280; margin: 0; }

  /* ── Page wrapper ── */
  .ts-wrap { max-width: 900px; margin: 0 auto; padding: 24px 20px 0; }

  /* ── Cards ── */
  .ts-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 22px 24px;
    margin-bottom: 18px;
  }
  .ts-card-title {
    font-size: 11px;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: .07em;
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f3f4f6;
  }

  /* ── Section selector row ── */
  .ts-section-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .ts-section-row label { font-size: 13px; font-weight: 600; color: #374151; white-space: nowrap; }

  /* ── Form controls ── */
  select, input[type="time"], input[type="text"] {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    color: #111827;
    background: #fff;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
  }
  select:focus, input:focus {
    border-color: #6b7280;
    box-shadow: 0 0 0 3px rgba(107,114,128,.1);
  }

  /* ── Form grid ── */
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; margin-bottom: 18px; }
  @media (max-width: 580px) { .form-grid { grid-template-columns: 1fr; } }
  .form-group { display: flex; flex-direction: column; gap: 5px; }
  .form-group label { font-size: 12px; font-weight: 600; color: #374151; }

  /* ── Buttons ── */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    white-space: nowrap;
  }
  .btn:active { opacity: .85; }
  .btn-primary { background: #22c55e; color: #fff; }
  .btn-primary:hover { background: #16a34a; }
  .btn-success { background: #22c55e; color: #fff; }
  .btn-success:hover { background: #16a34a; }
  .btn-danger  { background: #fff; color: #dc2626; border-color: #fca5a5; }
  .btn-danger:hover  { background: #fef2f2; }
  .btn-ghost   { background: #fff; color: #374151; border-color: #d1d5db; }
  .btn-ghost:hover   { background: #f9fafb; }
  .btn-sm { padding: 5px 10px; font-size: 12px; }

  /* ── Copy banner ── */
  .ts-copy-banner {
    background: #fafafa;
    border: 1px dashed #d1d5db;
    border-radius: 10px;
    padding: 18px 24px;
    margin-bottom: 18px;
  }
  .ts-copy-banner .ts-card-title { margin-bottom: 12px; }
  .ts-copy-row { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
  .ts-copy-row > .form-group { flex: 1; min-width: 180px; }

  /* ── Table ── */
  .ts-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .ts-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #9ca3af;
    padding: 10px 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    background: #fafafa;
  }
  .ts-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .12s; }
  .ts-table tbody tr:last-child { border-bottom: none; }
  .ts-table tbody tr:hover { background: #fafafa; }
  .ts-table td { padding: 12px 14px; vertical-align: middle; color: #374151; }
  .ts-table td:first-child { width: 80px; text-align: center; }

  /* ── Order controls ── */
  .order-controls { display: flex; flex-direction: column; align-items: center; gap: 2px; }
  .order-controls .order-num { font-size: 12px; font-weight: 700; color: #374151; line-height: 1.6; }
  .order-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 20px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    cursor: pointer;
    font-size: 10px;
    color: #6b7280;
    line-height: 1;
    transition: background .12s, color .12s;
  }
  .order-btn:hover { background: #e5e7eb; color: #111827; }

  /* ── Time cell ── */
  .time-range { font-weight: 600; color: #111827; font-size: 13px; }

  /* ── Badges ── */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .03em;
  }
  .badge-break { background: #fef9c3; color: #854d0e; }
  .badge-class { background: #f0fdf4; color: #166534; }

  .timeslot-actions { display: flex; gap: 6px; }

  /* ── Alerts ── */
  .ts-alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    color: #6b7280;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
  }

  /* ── Toggle ── */
  .toggle-wrap { display: flex; align-items: center; gap: 10px; padding-top: 2px; }
  .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
  .toggle-switch input { opacity: 0; width: 0; height: 0; }
  .toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: #d1d5db;
    border-radius: 24px;
    transition: .25s;
  }
  .toggle-slider::before {
    content: "";
    position: absolute;
    height: 16px; width: 16px;
    left: 4px; bottom: 4px;
    background: #fff;
    border-radius: 50%;
    transition: .25s;
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
  }
  input:checked + .toggle-slider { background: #22c55e; }
  input:checked + .toggle-slider::before { transform: translateX(20px); }
  .toggle-label { font-size: 12px; color: #6b7280; }

  /* ── Modal ── */
  .modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.3);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }
  .modal.open { display: flex; }
  .modal-content {
    background: #fff;
    border-radius: 12px;
    width: 95%;
    max-width: 520px;
    box-shadow: 0 20px 50px rgba(0,0,0,.12);
    animation: tsSlideUp .2s ease;
    overflow: hidden;
  }
  @keyframes tsSlideUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .modal-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .modal-header h2 { margin: 0; font-size: 14px; font-weight: 700; color: #111827; }
  .modal-body { padding: 20px 22px; }
  .modal-footer {
    padding: 12px 22px;
    background: #fafafa;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }
  .btn-close { background: none; border: none; font-size: 20px; line-height: 1; cursor: pointer; color: #9ca3af; padding: 0; transition: color .15s; }
  .btn-close:hover { color: #374151; }

  .ts-hint { font-size: 12px; color: #9ca3af; margin: 0 0 14px; }
</style>
</head>
<body>

<!-- Top bar with back button -->
<div class="ts-topbar">
  <a href="javascript:history.back()" class="btn-back">
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
        while($section = $sections_result->fetch_assoc()): 
        ?>
          <option value="<?php echo $section['section_id']; ?>" 
                  <?php echo ($selected_section == $section['section_id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($section['grade_level'] . ' - ' . $section['section_name'] . ' (' . $section['track'] . ')'); ?>
          </option>
        <?php endwhile; ?>
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
          while($sec = $sections_result->fetch_assoc()): 
            if ($sec['section_id'] != $selected_section):
          ?>
            <option value="<?php echo $sec['section_id']; ?>">
              <?php echo htmlspecialchars($sec['section_name'] . ' (' . $sec['track'] . ')'); ?>
            </option>
          <?php 
            endif;
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
          <?php while($slot = $timeslots_result->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="order-controls">
                  <button class="order-btn" onclick="moveSlot(<?php echo $slot['time_slot_id']; ?>, 'up')" title="Move Up">▲</button>
                  <span class="order-num"><?php echo $slot['slot_order']; ?></span>
                  <button class="order-btn" onclick="moveSlot(<?php echo $slot['time_slot_id']; ?>, 'down')" title="Move Down">▼</button>
                </div>
              </td>
              <td>
                <span class="time-range">
                  <?php echo date('g:i A', strtotime($slot['start_time'])) . ' – ' . date('g:i A', strtotime($slot['end_time'])); ?>
                </span>
              </td>
              <td>
                <?php if ($slot['is_break']): ?>
                  <span class="badge badge-break">Break</span>
                <?php else: ?>
                  <span class="badge badge-class">Class</span>
                <?php endif; ?>
              </td>
              <td style="color:#6b7280;">
                <?php echo $slot['is_break'] ? htmlspecialchars($slot['break_label']) : '—'; ?>
              </td>
              <td>
                <div class="timeslot-actions">
                  <button class="btn btn-ghost btn-sm"
                          onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>)">
                    Edit
                  </button>
                  <button class="btn btn-danger btn-sm"
                          onclick="deleteTimeslot(<?php echo $slot['time_slot_id']; ?>)">
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
const currentSectionId = <?php echo $selected_section ? $selected_section : 'null'; ?>;

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
    const res  = await fetch('/mainscheduler/tabs/actions/timeslot_create.php', { method: 'POST', body: formData });
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