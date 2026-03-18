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
$search = isset($_GET["search"]) ? trim((string) $_GET["search"]) : "";
$offset = ($page - 1) * $limit;
$where_sql = "";
$search_param = "";

if ($search !== "") {
    $where_sql =
        "WHERE CONCAT_WS(' ', s.section_name, s.grade_level, s.track, CONCAT(f.lname, ', ', f.fname)) LIKE ?";
    $search_param = "%" . $search . "%";
}

// Fetch sections with adviser name (if any)
$sql = "SELECT s.section_id, s.section_name, s.grade_level, s.track, s.adviser_id,
        CONCAT(f.lname, ', ', f.fname) AS adviser_name
        FROM sections s
        LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
        $where_sql
        ORDER BY s.grade_level, s.section_name
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // prepared statement succeeded — bind and execute
    if ($search !== "") {
        $stmt->bind_param("sii", $search_param, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Prepared statements are not available or prepare failed.
    // Fallback to a non-prepared query using safely cast integer values.
    $safeLimit = (int) $limit;
    $safeOffset = (int) $offset;
    $safeSearch =
        $search !== "" ? $conn->real_escape_string($search_param) : "";
    $fallbackWhere =
        $search !== ""
            ? "WHERE CONCAT_WS(' ', s.section_name, s.grade_level, s.track, CONCAT(f.lname, ', ', f.fname)) LIKE '{$safeSearch}'"
            : "";
    $rawSql = "SELECT s.section_id, s.section_name, s.grade_level, s.track, s.adviser_id,
                      CONCAT(f.lname, ', ', f.fname) AS adviser_name
               FROM sections s
               LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
               {$fallbackWhere}
               ORDER BY s.grade_level, s.section_name
               LIMIT {$safeLimit} OFFSET {$safeOffset}";

    $result = $conn->query($rawSql);
    if ($result === false) {
        // Surface database error so the issue is visible during development.
        // You can change this to a more user-friendly message in production.
        die("Database error when fetching sections: " . $conn->error);
    }
}

// Total count
if ($search !== "") {
    $count_sql = "SELECT COUNT(*) as total
                  FROM sections s
                  LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                  $where_sql";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("s", $search_param);
        $count_stmt->execute();
        $total_records =
            (int) ($count_stmt->get_result()->fetch_assoc()["total"] ?? 0);
    } else {
        $safeSearch = $conn->real_escape_string($search_param);
        $count_result = $conn->query("SELECT COUNT(*) as total
                                      FROM sections s
                                      LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                                      WHERE CONCAT_WS(' ', s.section_name, s.grade_level, s.track, CONCAT(f.lname, ', ', f.fname)) LIKE '{$safeSearch}'");
        $total_records = (int) ($count_result->fetch_assoc()["total"] ?? 0);
    }
} else {
    $total_records = (int) $conn
        ->query("SELECT COUNT(*) as total FROM sections")
        ->fetch_assoc()["total"];
}
$total_pages = max(1, ceil($total_records / $limit));
$is_fragment = isset($_GET["fragment"]) && $_GET["fragment"] === "1";
ob_start();
?>
<div class="sections-card">
  <div class="table-toolbar">
  <div class="table-toolbar-left">
    <span class="toolbar-title">Sections List</span>
    <span style="font-size:12px;color:#9ca3af;"><?= (int) $total_records ?> total</span>
  </div>

  <div class="table-toolbar-right">
    <input type="text" class="search-input" id="sections-search" placeholder="Search section..." value="<?= htmlspecialchars(
        $search,
    ) ?>" oninput="handleSectionsSearch(this.value)">
    <button class="add-subject-btn" id="open-create-btn">+ Add Section</button>
  </div>
</div>

<div class="table-wrapper">
  <table class="subject-table faculty-table" id="sections-data-table">
    <thead>
      <tr>
        <th>Section Name</th>
        <th>Grade Level</th>
        <th>STRAND</th>
        <th>Advisor</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr data-id="<?= (int) ($row["section_id"] ?? 0) ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="min-width:0;">
                <!-- View -->
                <div class="v-name name-primary"><?= htmlspecialchars(
                    $row["section_name"] ?? "",
                ) ?></div>

                <!-- Inline edit name -->
                <div class="e-name" style="display:none;">
                  <input class="edit-input e-field" name="section_name" value="<?= htmlspecialchars(
                      $row["section_name"] ?? "",
                  ) ?>" placeholder="Section Name" style="width:180px;">
                </div>

                <div class="name-secondary" style="font-size:12px;color:#6b7280;"><?= htmlspecialchars(
                    $row["track"] ?? "",
                ) ?></div>
              </div>
            </div>
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["grade_level"] ?? "",
            ) ?></span>
            <select class="e-field edit-select" name="grade_level" style="display:none;">
              <option value="">--</option>
              <option value="Grade 11" <?= strpos(
                  (string) ($row["grade_level"] ?? ""),
                  "11",
              ) !== false
                  ? "selected"
                  : "" ?>>Grade 11</option>
              <option value="Grade 12" <?= strpos(
                  (string) ($row["grade_level"] ?? ""),
                  "12",
              ) !== false
                  ? "selected"
                  : "" ?>>Grade 12</option>
            </select>
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["track"] ?? "",
            ) ?></span>
            <input class="e-field edit-input" name="track" value="<?= htmlspecialchars(
                $row["track"] ?? "",
            ) ?>" placeholder="Track/Strand" style="display:none;">
          </td>

          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["adviser_name"] ?? "",
            ) ?></span>

            <select class="e-field edit-select" name="adviser_id" style="display:none;">
              <option value="">-- Select Advisor --</option>
              <?php
              // Inline adviser options (match modal's population)
              $fac_options = $conn->query(
                  "SELECT faculty_id, fname, lname FROM faculty ORDER BY lname, fname",
              );
              if ($fac_options && $fac_options->num_rows > 0) {
                  $currentAdviser = trim($row["adviser_name"] ?? "");
                  while ($fopt = $fac_options->fetch_assoc()) {
                      $optText = htmlspecialchars(
                          $fopt["lname"] . ", " . $fopt["fname"],
                      );
                      $sel = $optText === $currentAdviser ? " selected" : "";
                      echo '<option value="' .
                          (int) $fopt["faculty_id"] .
                          '"' .
                          $sel .
                          ">" .
                          $optText .
                          "</option>";
                  }
              }
              ?>
            </select>
          </td>

          <td>
            <div class="v-actions action-group" style="display:flex; justify-content:flex-end; gap:6px;">
              <button class="action-btn action-btn-edit" onclick="startEditSectionInline(this)">
                Edit
              </button>
              <button class="action-btn action-btn-delete" onclick="deleteSection(this)">
                Delete
              </button>
            </div>

            <div class="e-actions action-group" style="display:none;">
              <button class="action-btn action-btn-save" onclick="saveSection(this)">
                Save
              </button>
              <button class="action-btn action-btn-cancel" onclick="cancelEditSection(this)">
                Cancel
              </button>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5"><div class="empty-state"><?= $search !== ""
            ? "No sections found for \"" . htmlspecialchars($search) . "\"."
            : "No sections found." ?></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
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
</div>
<?php
$sections_fragment = ob_get_clean();
echo $sections_fragment;
if ($is_fragment) {
    return;
}
?>

<!-- Create / Edit Section Modal -->
<div id="id03" class="modal">
  <form id="create-section-form" class="modal-content animate" action="/mainscheduler/tabs/actions/section_create.php" method="post">
    <input type="hidden" id="section_id" name="section_id" value="">
    <div class="imgcontainer">
      <span onclick="closeCreateSectionModal()" class="close" title="Close">&times;</span>
      <h2 id="create-section-title">Create Section</h2>
    </div>

    <div class="container">
      <label for="section_name"><b>Section Name</b></label>
      <input type="text" placeholder="Section Name" name="section_name" id="section_name" required>

      <label><b>Grade Level</b></label><br>
      <label><input type="radio" name="grade_level" id="grade_11" value="Grade 11" required> Grade 11</label>
      <label><input type="radio" name="grade_level" id="grade_12" value="Grade 12"> Grade 12</label>
      <br><br>

      <label for="track"><b>STRAND</b></label>
      <input type="text" placeholder="e.g. STEM, ABM" name="track" id="track">

      <label for="adviser_id"><b>Advisor</b></label>
      <select name="adviser_id" id="adviser_id">
        <option value="">-- Select Advisor --</option>
        <?php
        // populate advisors list
        $fac = $conn->query(
            "SELECT faculty_id, fname, lname FROM faculty ORDER BY lname, fname",
        );
        if ($fac && $fac->num_rows > 0) {
            while ($f = $fac->fetch_assoc()) {
                echo '<option value="' .
                    (int) $f["faculty_id"] .
                    '">' .
                    htmlspecialchars($f["lname"] . ", " . $f["fname"]) .
                    "</option>";
            }
        }
        ?>
      </select>

      <br><br>
      <button type="submit" id="section-form-submit">Create</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeCreateSectionModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
// Client-side behaviors for Sections List page
let sectionsSearchTerm = <?= json_encode($search) ?>;
let sectionsSearchTimer = null;
let shouldRestoreSectionsSearchFocus = false;

function retainSectionsTab() {
  try {
    sessionStorage.setItem("mainscheduler-active-tab", "sections_list");
  } catch (err) {
    console.warn("Unable to persist active tab:", err);
  }
}

function loadSectionsPage(page = 1, limit = <?= $default_limit ?>, search = sectionsSearchTerm) {
  sectionsSearchTerm = search || '';
  const params = new URLSearchParams({
    page: String(page),
    limit: String(limit),
    fragment: '1'
  });
  if (sectionsSearchTerm) {
    params.set('search', sectionsSearchTerm);
  }

  const host = document.getElementById('sections_list');
  if (!host) return;

  fetch(`/mainscheduler/tabs/sections_list.php?${params.toString()}`)
    .then(r => {
      if (!r.ok) throw new Error('Status ' + r.status);
      return r.text();
    })
    .then(html => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html;
      const nextCard = wrapper.querySelector('.sections-card');
      const currentCard = host.querySelector('.sections-card');
      if (nextCard && currentCard) {
        currentCard.replaceWith(nextCard);
      } else if (nextCard) {
        host.prepend(nextCard);
      }

      if (shouldRestoreSectionsSearchFocus) {
        const searchInput = document.getElementById('sections-search');
        if (searchInput) {
          const cursorPos = searchInput.value.length;
          searchInput.focus();
          searchInput.setSelectionRange(cursorPos, cursorPos);
        }
      }
    })
    .catch(err => {
      console.error('Error loading sections:', err);
    })
    .finally(() => {
      shouldRestoreSectionsSearchFocus = false;
    });
}

window.loadSectionsPage = loadSectionsPage;

function handleSectionsSearch(q) {
  sectionsSearchTerm = (q || '').trim();
  const limit = document.getElementById('rows-per-page')?.value || <?= $default_limit ?>;
  if (sectionsSearchTimer) {
    clearTimeout(sectionsSearchTimer);
  }
  sectionsSearchTimer = setTimeout(() => {
    shouldRestoreSectionsSearchFocus = true;
    loadSectionsPage(1, limit, sectionsSearchTerm);
  }, 300);
}

document.addEventListener('click', function(event) {
  const pageBtn = event.target.closest('.page-btn');
  if (!pageBtn) return;
  const page = pageBtn.getAttribute('data-page');
  const limit = document.getElementById('rows-per-page')?.value || <?= $default_limit ?>;
  if (page) {
    retainSectionsTab();
    loadSectionsPage(page, limit, sectionsSearchTerm);
  }
});

document.addEventListener('change', function(event) {
  if (event.target.matches('#rows-per-page')) {
    retainSectionsTab();
    loadSectionsPage(1, event.target.value, sectionsSearchTerm);
  }
});

// Open create modal
document.getElementById('open-create-btn').addEventListener('click', function() {
  openCreateSectionModal();
});

// Modal helpers
function openCreateSectionModal() {
  const form = document.getElementById('create-section-form');
  form.reset();
  document.getElementById('section_id').value = '';
  form.setAttribute('action', '/mainscheduler/tabs/actions/section_create.php');
  document.getElementById('create-section-title').textContent = 'Create Section';
  document.getElementById('section-form-submit').textContent = 'Create';
  document.getElementById('id03').style.display = 'block';
}

function closeCreateSectionModal() {
  const modal = document.getElementById('id03');
  modal.style.display = 'none';
  const form = document.getElementById('create-section-form');
  form.reset();
  document.getElementById('section_id').value = '';
}

// Submit handler for create/update
document.getElementById('create-section-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  const submitBtn = document.getElementById('section-form-submit');
  const origText = submitBtn.textContent;
  submitBtn.textContent = 'Saving...';
  submitBtn.disabled = true;

  const formData = new FormData(form);
  // If editing, send to update endpoint
  const isEdit = !!formData.get('section_id');
  const endpoint = isEdit ? '/mainscheduler/tabs/actions/section_update.php' : '/mainscheduler/tabs/actions/section_create.php';

  fetch(endpoint, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(json => {
      if (json && json.success) {
        retainSectionsTab();
        if (!isEdit && typeof window.loadSectionsPage === 'function') {
          sectionsSearchTerm = '';
          const limit = parseInt(document.getElementById('rows-per-page')?.value || <?= $default_limit ?>, 10);
          const totalRecords = parseInt(json.total_records || 0, 10);
          const targetPage = totalRecords > 0 ? Math.ceil(totalRecords / limit) : 1;
          closeCreateSectionModal();
          window.loadSectionsPage(targetPage, limit, '');
        } else if (typeof window.loadSectionsPage === 'function') {
          const limit = document.getElementById('rows-per-page')?.value || <?= $default_limit ?>;
          closeCreateSectionModal();
          window.loadSectionsPage(1, limit, sectionsSearchTerm);
        } else {
          window.location.reload();
        }
      } else {
        alert('Error: ' + (json && json.message ? json.message : 'Unknown error'));
      }
    })
    .catch(err => {
      console.error('Error saving section:', err);
      alert('Error saving section');
    })
    .finally(() => {
      submitBtn.textContent = origText;
      submitBtn.disabled = false;
    });
});

// Edit action - populate modal with row data
function startEditSection(btn) {
  const row = btn.closest('tr');
  const sectionId = row.getAttribute('data-id');
  const tds = row.querySelectorAll('td');
  const name = row.querySelector('.name-primary')?.textContent?.trim() || '';
  const grade = tds[1]?.textContent?.trim() || '';
  const track = tds[2]?.textContent?.trim() || '';
  // adviser is now the next visible column after track
  const adviser = tds[3]?.textContent?.trim() || '';

  // populate form
  document.getElementById('section_id').value = sectionId;
  document.getElementById('section_name').value = name;
  if (grade.includes('11')) {
    document.getElementById('grade_11').checked = true;
  } else {
    document.getElementById('grade_12').checked = true;
  }
  document.getElementById('track').value = track;

  // Try to select adviser option by matching text
  const adviserSelect = document.getElementById('adviser_id');
  if (adviserSelect) {
    for (let i = 0; i < adviserSelect.options.length; i++) {
      const opt = adviserSelect.options[i];
      if (opt.textContent.trim() === adviser) {
        adviserSelect.value = opt.value;
        break;
      }
    }
  }

  // switch to update endpoint
  const form = document.getElementById('create-section-form');
  form.setAttribute('action', '/mainscheduler/tabs/actions/section_update.php');
  document.getElementById('create-section-title').textContent = 'Edit Section';
  document.getElementById('section-form-submit').textContent = 'Save Changes';
  document.getElementById('id03').style.display = 'block';
}

// Delete action
function deleteSection(btn) {
  if (!confirm('Delete this section? This will remove associated schedules and requirements.')) return;
  const row = btn.closest('tr');
  const sectionId = row.getAttribute('data-id');
  const data = new FormData();
  data.append('section_id', sectionId);

  fetch('/mainscheduler/tabs/actions/section_delete.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(json => {
      if (json && json.success) {
        // remove row visually then reload
        row.style.transition = 'opacity .2s, transform .2s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(8px)';
        setTimeout(() => {
          retainSectionsTab();
          const limit = document.getElementById('rows-per-page')?.value || <?= $default_limit ?>;
          loadSectionsPage(1, limit, sectionsSearchTerm);
        }, 220);
      } else {
        alert('Delete failed: ' + (json && json.message ? json.message : 'Unknown'));
      }
    })
    .catch(err => {
      console.error('Delete error', err);
      alert('Error deleting section');
    });
}

// Inline edit: show inputs inside the row
function startEditSectionInline(btn) {
  const row = btn.closest('tr');
  row.classList.add('editing');
  row.querySelectorAll('.v-field, .v-name, .v-actions, .name-secondary').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = '');
  // ensure name edit input is visible as block
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'block';
}

function cancelEditSection(btn) {
  const row = btn.closest('tr');
  row.classList.remove('editing');
  row.querySelectorAll('.v-field, .v-name, .v-actions, .name-secondary').forEach(el => el.style.display = '');
  row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = 'none');
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'none';
}

async function saveSection(btn) {
  const row = btn.closest('tr');
  const sectionId = row.getAttribute('data-id');
  const data = new FormData();
  data.append('section_id', sectionId);

  // collect editable inputs
  row.querySelectorAll('.e-field, .e-name input, .e-name select').forEach(el => {
    if (el.name) data.append(el.name, el.value);
  });

  const orig = btn.innerHTML;
  btn.textContent = 'Saving...';
  btn.disabled = true;

  try {
    // Use section_update.php to perform update
    const res = await fetch('/mainscheduler/tabs/actions/section_update.php', { method: 'POST', body: data });
    const json = await res.json();
    if (json && json.success) {
      // refresh row listing using location reload or global loader if present
      if (typeof window.loadSectionsPage === 'function') {
        const limit = document.getElementById('rows-per-page')?.value || <?= $default_limit ?>;
        window.loadSectionsPage(1, limit, sectionsSearchTerm);
      } else {
        // fallback: update the visible cells from returned data if provided,
        // otherwise reload the page to show changes.
        retainSectionsTab();
        window.location.reload();
      }
    } else {
      alert('Error saving: ' + (json && json.message ? json.message : 'Unknown'));
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  } catch (err) {
    console.error('Save error', err);
    alert('Error saving section');
    btn.innerHTML = orig;
    btn.disabled = false;
  }
}

// Close modal clicking outside
window.addEventListener('click', function(event) {
  const modal = document.getElementById('id03');
  if (event.target === modal) closeCreateSectionModal();
});
</script>
