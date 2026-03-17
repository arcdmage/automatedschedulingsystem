<?php
require_once __DIR__ . "/../db_connect.php";

// Pagination options
$limit_options = [5, 10, 25, 50, 100];
$default_limit = 5;

$limit =
    isset($_GET["limit"]) && in_array((int) $_GET["limit"], $limit_options)
        ? (int) $_GET["limit"]
        : $default_limit;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$offset = ($page - 1) * $limit;

// Fetch subjects and totals
$result = $conn->query(
    "SELECT * FROM subjects ORDER BY subject_name ASC LIMIT $limit OFFSET $offset",
);
$faculty_options = [];
$faculty_query = $conn->query(
    "SELECT CONCAT(lname, ', ', fname) AS name FROM faculty ORDER BY lname, fname",
);
if ($faculty_query) {
    while ($faculty = $faculty_query->fetch_assoc()) {
        $faculty_options[] = $faculty["name"];
    }
}
$total_records = $conn
    ->query("SELECT COUNT(*) as total FROM subjects")
    ->fetch_assoc()["total"];
$total_pages = max(1, ceil($total_records / $limit));
?>

<!-- Toolbar -->
<style>
/* Add Subject button — copied from faculty styles, kept local so subject list can customize separately */
.add-subject-btn {
    margin-top: -2px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 12px 12px;
    background: #22c55e;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition:
        background 0.15s,
        transform 0.12s;
    white-space: nowrap;
    flex: 0 0 auto; /* don't grow or shrink unexpectedly */
    max-width: 140px; /* keep the button from overflowing the toolbar */
    overflow: hidden;
    text-overflow: ellipsis; /* truncate label if it would overflow */
    box-sizing: border-box;
}
.add-subject-btn:hover {
    background: #16a34a;
    transform: scale(1.02);
}
</style>

<div class="table-toolbar">
  <div class="table-toolbar-left">
    <span class="toolbar-title">Subject List</span>
    <span style="font-size:12px;color:#9ca3af;"><?= $total_records ?> total</span>
  </div>

  <div class="table-toolbar-right">
    <input type="text" class="search-input" placeholder="Search subject…" oninput="filterSubjectTable(this.value)">
    <button class="add-subject-btn" onclick="document.getElementById('id02').style.display='block'">+ Add Subject</button>
  </div>
</div>

<!-- Table -->
<div class="table-wrapper">
  <table class="faculty-table" id="subject-data-table">
    <thead>
      <tr>
        <th>Subject Name</th>
        <th>In Specialization Of</th>
        <th>Grade Level</th>
        <th>Strand</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr data-id="<?= (int) ($row["subject_id"] ?? 0) ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="min-width:0;">
                <!-- View -->
                <div class="v-name name-primary"><?= htmlspecialchars(
                    $row["subject_name"] ?? "",
                ) ?></div>
                <!-- Edit -->
                <div class="e-name" style="display:none;">
                  <input class="edit-input" name="subject_name" value="<?= htmlspecialchars(
                      $row["subject_name"] ?? "",
                  ) ?>" placeholder="Subject name" style="width:240px;">
                </div>
              </div>
            </div>
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["special"] ?? "",
            ) ?></span>
            <select class="e-field edit-select" name="special" style="display:none;">
              <option value="">--</option>
              <?php foreach ($faculty_options as $faculty_name): ?>
                <option value="<?= htmlspecialchars(
                    $faculty_name,
                ) ?>" <?= ($row["special"] ?? "") === $faculty_name
    ? "selected"
    : "" ?>><?= htmlspecialchars($faculty_name) ?></option>
              <?php endforeach; ?>
            </select>
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["grade_level"] ?? "",
            ) ?></span>
            <select class="e-field edit-select" name="grade_level" style="display:none;">
              <option value="" <?= ($row["grade_level"] ?? "") === ""
                  ? "selected"
                  : "" ?>>--</option>
              <option value="11" <?= strpos(
                  (string) ($row["grade_level"] ?? ""),
                  "11",
              ) !== false
                  ? "selected"
                  : "" ?>>11</option>
              <option value="12" <?= strpos(
                  (string) ($row["grade_level"] ?? ""),
                  "12",
              ) !== false
                  ? "selected"
                  : "" ?>>12</option>
            </select>
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["strand"] ?? "",
            ) ?></span>
            <input class="e-field edit-input" name="strand" value="<?= htmlspecialchars(
                $row["strand"] ?? "",
            ) ?>" placeholder="Strand" style="display:none;">
          </td>

          <td>
            <div class="v-actions action-group">
              <button class="action-btn action-btn-edit" onclick="startEditSubject(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button class="action-btn action-btn-delete" onclick="deleteSubject(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                Delete
              </button>
            </div>

            <div class="e-actions action-group" style="display:none;">
              <button class="action-btn action-btn-save" onclick="saveSubject(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Save
              </button>
              <button class="action-btn action-btn-cancel" onclick="cancelEditSubject(this)">Cancel</button>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5"><div class="empty-state">No subjects found.</div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="pagination-container">
  <div class="rows-selector">
    <label for="rows-per-page">Rows per page:</label>
    <select id="rows-per-page">
      <?php foreach ($limit_options as $opt): ?>
        <option value="<?= $opt ?>" <?= $limit == $opt
    ? "selected"
    : "" ?>><?= $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <button class="page-btn" data-page="<?= $page - 1 ?>">&laquo;</button>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <button class="page-btn <?= $i == $page
          ? "active"
          : "" ?>" data-page="<?= $i ?>"><?= $i ?></button>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <button class="page-btn" data-page="<?= $page + 1 ?>">&raquo;</button>
    <?php endif; ?>
  </div>
</div>

<script>
/* Client-side helpers for the injected subject table fragment.
   subject_list.php already wires pagination and rows-per-page events at a higher level,
   but include small helpers so search and inline actions feel consistent. */

function filterSubjectTable(q) {
  document.querySelectorAll('#subject-data-table tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}

/* Inline edit behavior for subjects (mirrors faculty inline editing) */
function startEditSubject(btn) {
  const row = btn.closest('tr');
  row.classList.add('editing');
  // hide view elements, show edit inputs/actions
  row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = '');
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'block';
}

function cancelEditSubject(btn) {
  const row = btn.closest('tr');
  row.classList.remove('editing');
  row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = '');
  row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = 'none');
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'none';
}

async function saveSubject(btn) {
  const row = btn.closest('tr');
  const id = row.getAttribute('data-id');
  const data = new FormData();
  data.append('subject_id', id);

  // collect editable inputs from the row
  row.querySelectorAll('.e-field, .e-name input, .e-name select').forEach(el => {
    if (el.name) data.append(el.name, el.value);
  });

  const orig = btn.innerHTML;
  btn.textContent = 'Saving…';
  btn.disabled = true;

  try {
    const res = await fetch('/mainscheduler/tabs/actions/subject_update.php', { method: 'POST', body: data });
    const json = await res.json();
    if (json && json.success) {
      // reload listing (use rows-per-page selector or fallback)
      if (typeof loadSubjectPage === 'function') {
        const limit = document.getElementById('rows-per-page')?.value || 5;
        loadSubjectPage(1, limit);
      } else {
        window.location.reload();
      }
    } else {
      alert('Error: ' + (json && json.message ? json.message : 'Unknown error'));
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  } catch (e) {
    console.error('Save error:', e);
    alert('Error saving subject.');
    btn.innerHTML = orig;
    btn.disabled = false;
  }
}

async function deleteSubject(btn) {
  if (!confirm('Delete this subject? This cannot be undone.')) return;
  const row = btn.closest('tr');
  const subjectId = row.getAttribute('data-id');
  const data = new FormData();
  data.append('subject_id', subjectId);
  data.append('action', 'delete');

  const orig = btn.innerHTML;
  btn.textContent = 'Deleting…';
  btn.disabled = true;

  try {
    const res = await fetch('/mainscheduler/tabs/actions/subject_update.php', { method: 'POST', body: data });
    const json = await res.json();
    if (json && json.success) {
      row.style.transition = 'opacity .2s, transform .2s';
      row.style.opacity = '0';
      row.style.transform = 'translateX(8px)';
      setTimeout(() => {
        if (typeof loadSubjectPage === 'function') {
          const limit = document.getElementById('rows-per-page')?.value || 5;
          loadSubjectPage(1, limit);
        } else {
          window.location.reload();
        }
      }, 220);
    } else {
      alert('Delete failed: ' + (json && json.message ? json.message : 'Unknown'));
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  } catch (err) {
    console.error('Delete error', err);
    alert('Error deleting subject');
    btn.innerHTML = orig;
    btn.disabled = false;
  }
}
</script>
