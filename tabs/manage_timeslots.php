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
.timeslot-actions {
  display: flex;
  gap: 5px;
}

.timeslot-actions button {
  padding: 5px 10px;
  font-size: 12px;
}

.badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: bold;
}

.badge-break {
  background: #fff3cd;
  color: #856404;
}

.badge-class {
  background: #d1ecf1;
  color: #0c5460;
}

.order-controls {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.order-controls button {
  padding: 2px 6px;
  font-size: 10px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
}

.order-controls button:hover {
  background: #5a6268;
}

.modal-body {
  padding: 20px;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 30px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .4s;
  border-radius: 30px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: #4CAF50;
}

input:checked + .toggle-slider:before {
  transform: translateX(30px);
}

.copy-template-section {
  background: #e7f3ff;
  border: 2px dashed #2196F3;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.copy-template-section h3 {
  margin-top: 0;
  color: #1976D2;
}
</style>
</head>
<body>

<div class="page-header">
  <h1>⏰ Time Slot Manager</h1>
  <p>Configure schedule time blocks for each section (strand-specific)</p>
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
        <?php echo htmlspecialchars($section['grade_level'] . ' - ' . $section['section_name'] . ' (' . $section['track'] . ')'); ?>
      </option>
    <?php endwhile; ?>
  </select>
</div>

<?php if ($selected_section): ?>

<!-- Copy from Another Section -->
<div class="card-section copy-template-section">
  <h3>📋 Copy Time Slots from Another Section</h3>
  <p style="margin-bottom:15px;">Quickly set up time slots by copying from an existing section's configuration</p>
  
  <div style="display:flex; gap:15px; align-items:end;">
    <div style="flex:1;">
      <label>Copy from:</label>
      <select id="copy-from-section">
        <option value="">-- Select Section --</option>
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
    <button onclick="copyTimeSlots()" class="btn btn-primary">Copy Time Slots</button>
  </div>
</div>

<!-- Add New Time Slot -->
<div class="card-section">
  <h2>➕ Add New Time Slot</h2>
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
        <label>Is this a Break/Lunch?</label>
        <label class="toggle-switch">
          <input type="checkbox" name="is_break" id="is_break_toggle" onchange="toggleBreakLabel()">
          <span class="toggle-slider"></span>
        </label>
        <small style="display:block; margin-top:5px; color:#666;">Toggle ON for breaks</small>
      </div>
      
      <div class="form-group" id="break_label_group" style="display:none;">
        <label>Break Label *</label>
        <input type="text" name="break_label" id="break_label_input" placeholder="e.g., BREAK TIME, LUNCH BREAK">
      </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Add Time Slot</button>
  </form>
</div>

<!-- Existing Time Slots -->
<div class="card-section">
  <h2>📋 Configured Time Slots</h2>
  <p style="color:#666; font-size:14px; margin-bottom:15px;">
    Use the arrows to reorder slots. The order determines how they appear in schedules.
  </p>
  
  <?php if ($timeslots_result && $timeslots_result->num_rows > 0): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Time Range</th>
          <th>Type</th>
          <th>Label (if break)</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while($slot = $timeslots_result->fetch_assoc()): ?>
          <tr>
            <td>
              <div class="order-controls">
                <button onclick="moveSlot(<?php echo $slot['time_slot_id']; ?>, 'up')" 
                        title="Move Up">▲</button>
                <strong><?php echo $slot['slot_order']; ?></strong>
                <button onclick="moveSlot(<?php echo $slot['time_slot_id']; ?>, 'down')" 
                        title="Move Down">▼</button>
              </div>
            </td>
            <td>
              <strong>
                <?php 
                  echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . 
                       date('g:i A', strtotime($slot['end_time'])); 
                ?>
              </strong>
            </td>
            <td>
              <?php if ($slot['is_break']): ?>
                <span class="badge badge-break">🍽️ BREAK</span>
              <?php else: ?>
                <span class="badge badge-class">📚 CLASS</span>
              <?php endif; ?>
            </td>
            <td>
              <?php echo $slot['is_break'] ? htmlspecialchars($slot['break_label']) : '-'; ?>
            </td>
            <td>
              <div class="timeslot-actions">
                <button class="btn btn-primary" 
                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>)">
                  Edit
                </button>
                <button class="btn btn-danger" 
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
    <div class="alert alert-warning">
      No time slots configured for this section yet. Add time slots above or copy from another section.
    </div>
  <?php endif; ?>
</div>

<?php else: ?>
  <div class="alert alert-info">Please select a section above to manage its time slots.</div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
  <div class="modal-content animate" style="max-width:600px;">
    <div class="imgcontainer">
      <span onclick="closeEditModal()" class="close">&times;</span>
      <h2>Edit Time Slot</h2>
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
            <label>Is this a Break/Lunch?</label>
            <label class="toggle-switch">
              <input type="checkbox" name="is_break" id="edit_is_break" onchange="toggleEditBreakLabel()">
              <span class="toggle-slider"></span>
            </label>
          </div>
          
          <div class="form-group" id="edit_break_label_group" style="display:none;">
            <label>Break Label *</label>
            <input type="text" name="break_label" id="edit_break_label" placeholder="e.g., BREAK TIME, LUNCH BREAK">
          </div>
        </div>
        
        <button type="submit" class="btn btn-success">💾 Update Time Slot</button>
      </form>
    </div>

    <div class="container" style="background-color:#f1f1f1; padding:15px;">
      <button type="button" onclick="closeEditModal()" class="cancelbtn">Cancel</button>
    </div>
  </div>
</div>

<script>
const currentSectionId = <?php echo $selected_section ? $selected_section : 'null'; ?>;

// Toggle break label visibility in ADD form
function toggleBreakLabel() {
  const isBreak = document.getElementById('is_break_toggle').checked;
  const breakLabelGroup = document.getElementById('break_label_group');
  const breakLabelInput = document.getElementById('break_label_input');
  
  if (isBreak) {
    breakLabelGroup.style.display = 'block';
    breakLabelInput.required = true;
  } else {
    breakLabelGroup.style.display = 'none';
    breakLabelInput.required = false;
    breakLabelInput.value = '';
  }
}

// Toggle break label visibility in EDIT form
function toggleEditBreakLabel() {
  const isBreak = document.getElementById('edit_is_break').checked;
  const breakLabelGroup = document.getElementById('edit_break_label_group');
  const breakLabelInput = document.getElementById('edit_break_label');
  
  if (isBreak) {
    breakLabelGroup.style.display = 'block';
    breakLabelInput.required = true;
  } else {
    breakLabelGroup.style.display = 'none';
    breakLabelInput.required = false;
    breakLabelInput.value = '';
  }
}

// Copy time slots from another section
async function copyTimeSlots() {
  const fromSectionId = document.getElementById('copy-from-section').value;
  
  if (!fromSectionId) {
    alert('Please select a section to copy from');
    return;
  }
  
  if (!confirm('This will copy all time slots from the selected section. Continue?')) {
    return;
  }
  
  const formData = new FormData();
  formData.append('from_section_id', fromSectionId);
  formData.append('to_section_id', currentSectionId);
  
  try {
    const response = await fetch('/mainscheduler/tabs/actions/timeslot_copy.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      alert(`Successfully copied ${data.slots_copied} time slots!`);
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error copying time slots');
  }
}

// Add new time slot
document.getElementById('add-timeslot-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  // Convert checkbox to 1/0
  formData.set('is_break', document.getElementById('is_break_toggle').checked ? '1' : '0');
  
  try {
    const response = await fetch('/mainscheduler/tabs/actions/timeslot_create.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      alert('Time slot added successfully!');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error adding time slot');
  }
});

// Open edit modal
function openEditModal(slot) {
  document.getElementById('edit_time_slot_id').value = slot.time_slot_id;
  document.getElementById('edit_start_time').value = slot.start_time.substring(0, 5);
  document.getElementById('edit_end_time').value = slot.end_time.substring(0, 5);
  document.getElementById('edit_is_break').checked = slot.is_break == 1;
  document.getElementById('edit_break_label').value = slot.break_label || '';
  
  toggleEditBreakLabel();
  document.getElementById('edit-modal').style.display = 'block';
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
}

// Update time slot
document.getElementById('edit-timeslot-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  // Convert checkbox to 1/0
  formData.set('is_break', document.getElementById('edit_is_break').checked ? '1' : '0');
  
  try {
    const response = await fetch('/mainscheduler/tabs/actions/timeslot_update.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      alert('Time slot updated successfully!');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error updating time slot');
  }
});

// Delete time slot
async function deleteTimeslot(id) {
  if (!confirm('Delete this time slot? This may affect existing schedules and patterns.')) return;
  
  const formData = new FormData();
  formData.append('time_slot_id', id);
  formData.append('section_id', currentSectionId);
  
  try {
    const response = await fetch('/mainscheduler/tabs/actions/timeslot_delete.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      alert('Time slot deleted successfully!');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error deleting time slot');
  }
}

// Move slot up/down
async function moveSlot(id, direction) {
  const formData = new FormData();
  formData.append('time_slot_id', id);
  formData.append('direction', direction);
  formData.append('section_id', currentSectionId);
  
  try {
    const response = await fetch('/mainscheduler/tabs/actions/timeslot_reorder.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error reordering time slot');
  }
}

// Close modal on outside click
window.onclick = function(event) {
  if (event.target == document.getElementById('edit-modal')) {
    closeEditModal();
  }
}
</script>

</body>
</html>