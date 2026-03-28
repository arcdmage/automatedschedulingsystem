<?php
require_once __DIR__ . "/../db_connect.php"; ?>
<?php $appBase = app_url(); ?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(
    app_url("tabs/css/faculty_table.css"),
    ENT_QUOTES,
    "UTF-8",
) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(
    app_url("tabs/css/faculty_modal.css"),
    ENT_QUOTES,
    "UTF-8",
) ?>">
</head>
<body>

<!-- Faculty Table Section -->
<div class="faculty-table-container">
  <div id="faculty-table-content">

    <!-- Table loads here via AJAX -->
  </div>
</div>

<!-- Add Faculty Modal -->
<div id="id01" class="modal">
  <form class="modal-content animate" id="add-faculty-form">
    <div class="imgcontainer">
      <span onclick="closeAddModal()" class="close" title="Close">&times;</span>
      <h2>Add Faculty</h2>
    </div>
    <div class="container">
      <label for="fname"><b>First Name</b></label>
      <input type="text" placeholder="First Name" name="fname" required>

      <label for="mname"><b>Middle Name</b></label>
      <input type="text" placeholder="Middle Name" name="mname">

      <label for="lname"><b>Last Name</b></label>
      <input type="text" placeholder="Last Name" name="lname" required>

      <label><b>Gender</b></label><br>
      <label><input type="radio" name="gender" value="female"> Female</label>
      <label><input type="radio" name="gender" value="male"> Male</label>
      <label><input type="radio" name="gender" value="other"> Other</label>
      <br><br>

      <label for="pnumber"><b>Phone</b></label>
      <input type="text" placeholder="Phone Number (optional)" name="pnumber">

      <label for="address"><b>Address</b></label>
      <input type="text" placeholder="Address (optional)" name="address">

      <label for="status"><b>Status</b></label>
      <input type="text" placeholder="Status (optional)" name="status">

      <button type="submit">Create</button>
    </div>
    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeAddModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>



<div id="faculty-import-modal" class="modal">
  <form class="modal-content animate" id="faculty-import-form" enctype="multipart/form-data">
    <div class="imgcontainer">
      <span onclick="closeImportModal()" class="close" title="Close">&times;</span>
      <h2>Import Faculty</h2>
    </div>
    <div class="container">
      <p style="margin-top:0; color:#475569; line-height:1.5;">
        Upload the faculty CSV template to import multiple faculty members at once.
      </p>

      <label for="faculty_file"><b>CSV File</b></label>
      <input type="file" name="faculty_file" accept=".csv,text/csv" required>

      <div style="display:flex; gap:10px; flex-wrap:wrap; margin:8px 0 14px;">
        <a href="<?= htmlspecialchars(
            app_url("tabs/actions/faculty_import_template.php"),
            ENT_QUOTES,
            "UTF-8",
        ) ?>" style="display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:10px 14px; border-radius:4px;">
          Download Template
        </a>
      </div>

      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; color:#475569; font-size:13px; line-height:1.5;">
        Header order: <code>first_name, middle_name, last_name, gender, phone_number, address, status</code><br>
        Valid gender values: <code>female</code>, <code>male</code>, <code>other</code><br>
        Duplicate full names are skipped automatically.
      </div>

      <button type="submit">Import Faculty</button>
    </div>
    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeImportModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<div id="faculty-schedule-modal" class="modal">
  <div class="modal-content animate" style="max-width:900px; width:92%;">
    <div class="imgcontainer">
      <span onclick="closeFacultyScheduleModal()" class="close" title="Close">&times;</span>
      <h2>Faculty Schedule</h2>
    </div>
    <div class="container">
      <div id="faculty-schedule-content" style="min-height:160px; color:#475569;">Loading...</div>
    </div>
  </div>
</div>

<script>
const APP_BASE = <?= json_encode($appBase) ?>;
const tableContent = document.getElementById('faculty-table-content');
const defaultLimit = 5;
let facultySearchTerm = '';
let facultySearchTimer = null;
let shouldRestoreFacultySearchFocus = false;

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   TABLE LOADING
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function loadFacultyPage(page = 1, limit = defaultLimit, search = facultySearchTerm) {
  facultySearchTerm = search || '';
  tableContent.innerHTML = '<p style="text-align:center;padding:20px;color:#9ca3af;">Loadingâ€¦</p>';
  const params = new URLSearchParams({
    page: String(page),
    limit: String(limit)
  });
  if (facultySearchTerm) {
    params.set('search', facultySearchTerm);
  }

  fetch(`${APP_BASE}/tabs/faculty_table.php?${params.toString()}`)
    .then(r => { if (!r.ok) throw new Error('Status ' + r.status); return r.text(); })
    .then(html => {
      tableContent.innerHTML = html;

      if (shouldRestoreFacultySearchFocus) {
        const searchInput = document.getElementById('faculty-search-input');
        if (searchInput) {
          const cursorPos = searchInput.value.length;
          searchInput.focus();
          searchInput.setSelectionRange(cursorPos, cursorPos);
        }
      }
    })
    .catch(err => {
      console.error('Load error:', err);
      tableContent.innerHTML = "<p style='color:red;text-align:center;padding:20px;'>Error loading faculty data.</p>";
    })
    .finally(() => {
      shouldRestoreFacultySearchFocus = false;
    });
}

// Expose globally so inline onclick handlers in the injected HTML can trigger a reload
window.loadFacultyPage = loadFacultyPage;

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   PAGINATION & ROWS-PER-PAGE
   (delegated â€” works after every reload)
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
tableContent.addEventListener('click', function(e) {
  if (e.target.matches('.page-btn')) {
    const page  = e.target.getAttribute('data-page');
    const limit = document.getElementById('rows-per-page')?.value || defaultLimit;
    if (page) loadFacultyPage(page, limit, facultySearchTerm);
  }
});

tableContent.addEventListener('change', function(e) {
  if (e.target.matches('#rows-per-page')) {
    loadFacultyPage(1, e.target.value, facultySearchTerm);
  }
});

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   SEARCH FILTER
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function handleFacultySearch(q) {
  facultySearchTerm = (q || '').trim();
  const limit = document.getElementById('rows-per-page')?.value || defaultLimit;

  if (facultySearchTimer) {
    clearTimeout(facultySearchTimer);
  }

  facultySearchTimer = setTimeout(() => {
    shouldRestoreFacultySearchFocus = true;
    loadFacultyPage(1, limit, facultySearchTerm);
  }, 300);
}

function toggleFacultyStatus(btn) {
  const row = btn.closest('tr');
  if (!row) return;
  const status = row.querySelector('.name-status');
  if (!status) return;
  status.style.display = status.style.display === 'none' || status.style.display === '' ? 'block' : 'none';
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   INLINE EDIT
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function startEdit(btn) {
  const row = btn.closest('tr');
  row.classList.add('editing');
  row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.e-field, .e-actions').forEach(el => el.style.display = '');
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'flex';
}

function cancelEdit(btn) {
  const row = btn.closest('tr');
  row.classList.remove('editing');
  row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = '');
  row.querySelectorAll('.e-field, .e-actions').forEach(el => el.style.display = 'none');
  const nameDiv = row.querySelector('.e-name');
  if (nameDiv) nameDiv.style.display = 'none';
}

async function saveRow(btn) {
  const row  = btn.closest('tr');
  const id   = row.getAttribute('data-id');
  const data = new FormData();
  data.append('faculty_id', id);
  row.querySelectorAll('.e-field, .e-name input').forEach(el => {
    if (el.name) data.append(el.name, el.value);
  });

  const orig = btn.innerHTML;
  btn.textContent = 'Savingâ€¦';
  btn.disabled = true;

  try {
    const res  = await fetch(`${APP_BASE}/tabs/actions/faculty_update.php`, { method: 'POST', body: data });
    const json = await res.json();
    if (json.success) {
      loadFacultyPage(1, document.getElementById('rows-per-page')?.value || defaultLimit);
    } else {
      alert('Error: ' + json.message);
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  } catch(e) {
    console.error(e);
    alert('Error saving changes.');
    btn.innerHTML = orig;
    btn.disabled = false;
  }
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   DELETE
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
async function deleteRow(btn) {
  if (!confirm('Delete this faculty member? This cannot be undone.')) return;
  const row  = btn.closest('tr');
  const id   = row.getAttribute('data-id');
  const data = new FormData();
  data.append('faculty_id', id);

  const orig = btn.innerHTML;
  btn.textContent = 'Deletingâ€¦';
  btn.disabled = true;

  try {
    const res  = await fetch(`${APP_BASE}/tabs/actions/faculty_delete.php`, { method: 'POST', body: data });
    const json = await res.json();
    if (json.success) {
      row.style.transition = 'opacity .2s, transform .2s';
      row.style.opacity = '0';
      row.style.transform = 'translateX(8px)';
      setTimeout(() => loadFacultyPage(1, document.getElementById('rows-per-page')?.value || defaultLimit), 220);
    } else {
      alert('Error: ' + json.message);
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  } catch(e) {
    console.error(e);
    alert('Error deleting faculty member.');
    btn.innerHTML = orig;
    btn.disabled = false;
  }
}


async function openFacultyScheduleModal(facultyId) {
  const modal = document.getElementById('faculty-schedule-modal');
  const content = document.getElementById('faculty-schedule-content');
  if (!modal || !content) return;

  modal.style.display = 'block';
  content.innerHTML = '<p style="text-align:center;padding:20px;color:#64748b;">Loading schedule...</p>';

  try {
    const response = await fetch(`${APP_BASE}/tabs/actions/faculty_schedule_view.php?faculty_id=${encodeURIComponent(facultyId)}`);
    const html = await response.text();
    content.innerHTML = html;
    bindRelieveForms();
    bindRelieveDeleteButtons();
  } catch (error) {
    console.error(error);
    content.innerHTML = '<p style="color:#b91c1c;">Failed to load faculty schedule.</p>';
  }
}

function closeFacultyScheduleModal() {
  const modal = document.getElementById('faculty-schedule-modal');
  const content = document.getElementById('faculty-schedule-content');
  if (modal) modal.style.display = 'none';
  if (content) content.innerHTML = 'Loading...';
}

function toggleSectionRelieveModal(modalId, shouldOpen) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  modal.style.display = shouldOpen ? 'block' : 'none';
}

window.toggleSectionRelieveModal = toggleSectionRelieveModal;

function bindRelieveDeleteButtons() {
  document.querySelectorAll('.relieve-delete-btn').forEach(button => {
    if (button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    button.addEventListener('click', async function() {
      const relieveId = button.getAttribute('data-relieve-id');
      const facultyId = button.getAttribute('data-faculty-id');
      if (!relieveId || !facultyId) return;
      if (!confirm('Delete this relieve request?')) return;

      const originalLabel = button.textContent;
      button.disabled = true;
      button.textContent = 'Deleting...';

      try {
        const formData = new FormData();
        formData.append('relieve_id', relieveId);
        formData.append('faculty_id', facultyId);

        const response = await fetch(`${APP_BASE}/tabs/actions/relieve_request_delete.php`, {
          method: 'POST',
          body: formData
        });
        const json = await response.json();
        if (!json.success) {
          throw new Error(json.message || 'Failed to delete relieve request.');
        }
        alert(json.message || 'Relieve request deleted.');
        openFacultyScheduleModal(facultyId);
      } catch (error) {
        console.error(error);
        alert(error.message || 'Failed to delete relieve request.');
        button.disabled = false;
        button.textContent = originalLabel;
      }
    });
  });
}

function bindRelieveForms() {
  document.querySelectorAll('.relieve-form').forEach(form => {
    if (form.dataset.bound === '1') return;
    form.dataset.bound = '1';
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(form);
      const button = form.querySelector('button[type="submit"]');
      const originalLabel = button ? button.textContent : '';
      if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
      }

      try {
        const response = await fetch(`${APP_BASE}/tabs/actions/relieve_request_save.php`, {
          method: 'POST',
          body: formData
        });
        const json = await response.json();
        if (!json.success) {
          throw new Error(json.message || 'Failed to save relieve request.');
        }
        alert(json.message || 'Relieve request saved.');
        const facultyId = formData.get('faculty_id');
        if (facultyId) {
          openFacultyScheduleModal(facultyId);
        }
      } catch (error) {
        console.error(error);
        alert(error.message || 'Failed to save relieve request.');
      } finally {
        if (button) {
          button.disabled = false;
          button.textContent = originalLabel;
        }
      }
    });
  });
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   ADD FACULTY MODAL
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function openAddModal() {
  document.getElementById('id01').style.display = 'block';
}

function closeAddModal() {
  document.getElementById('id01').style.display = 'none';
  document.getElementById('add-faculty-form').reset();
}

function openImportModal() {
  document.getElementById('faculty-import-modal').style.display = 'block';
}
function closeImportModal() {
  document.getElementById('faculty-import-modal').style.display = 'none';
  document.getElementById('faculty-import-form').reset();
}

document.getElementById('add-faculty-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const submitBtn = this.querySelector('[type="submit"]');
  submitBtn.textContent = 'Creatingâ€¦';
  submitBtn.disabled = true;

  try {
    const res  = await fetch(`${APP_BASE}/tabs/actions/faculty_create.php`, { method: 'POST', body: formData });
    const json = await res.json();
    if (json.success) {
      closeAddModal();
      facultySearchTerm = '';
      const limit = parseInt(document.getElementById('rows-per-page')?.value || defaultLimit, 10);
      const totalRecords = parseInt(json.total_records || 0, 10);
      const targetPage = totalRecords > 0 ? Math.ceil(totalRecords / limit) : 1;
      loadFacultyPage(targetPage, limit, '');
    } else {
      alert('Error: ' + json.message);
    }
  } catch(e) {
    console.error(e);
    alert('Error adding faculty.');
  } finally {
    submitBtn.textContent = 'Create';
    submitBtn.disabled = false;
  }
});

document.getElementById('faculty-import-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const submitBtn = this.querySelector('[type="submit"]');
  submitBtn.textContent = 'Importing...';
  submitBtn.disabled = true;
  try {
    const res = await fetch(`${APP_BASE}/tabs/actions/faculty_import.php`, { method: 'POST', body: formData });
    const json = await res.json();
    if (!json.success) {
      throw new Error(json.message || 'Faculty import failed.');
    }
    let message = json.message || 'Faculty imported successfully.';
    if (Array.isArray(json.errors) && json.errors.length > 0) {
      message += "\n\nFailed rows:\n- " + json.errors.join("\n- ");
    }
    alert(message);
    closeImportModal();
    facultySearchTerm = '';
    const limit = parseInt(document.getElementById('rows-per-page')?.value || defaultLimit, 10);
    const totalRecords = parseInt(json.total_records || 0, 10);
    const targetPage = totalRecords > 0 ? Math.ceil(totalRecords / limit) : 1;
    loadFacultyPage(targetPage, limit, '');
  } catch (e) {
    console.error(e);
    alert(e.message || 'Faculty import failed.');
  } finally {
    submitBtn.textContent = 'Import Faculty';
    submitBtn.disabled = false;
  }
});
window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('id01')) closeAddModal();
  if (e.target === document.getElementById('faculty-import-modal')) closeImportModal();
  if (e.target === document.getElementById('faculty-schedule-modal')) closeFacultyScheduleModal();
});

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   INIT
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
loadFacultyPage(1, defaultLimit);
</script>

</body>
</html>
