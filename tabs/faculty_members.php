<?php
require_once __DIR__ . "/../db_connect.php"; ?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/faculty_table.css">
<link rel="stylesheet" href="/mainscheduler/tabs/css/faculty_modal.css">
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

<script>
const tableContent = document.getElementById('faculty-table-content');
const defaultLimit = 5;

/* ─────────────────────────────────────────
   TABLE LOADING
───────────────────────────────────────── */
function loadFacultyPage(page = 1, limit = defaultLimit) {
  tableContent.innerHTML = '<p style="text-align:center;padding:20px;color:#9ca3af;">Loading…</p>';
  fetch(`/mainscheduler/tabs/faculty_table.php?page=${page}&limit=${limit}`)
    .then(r => { if (!r.ok) throw new Error('Status ' + r.status); return r.text(); })
    .then(html => { tableContent.innerHTML = html; })
    .catch(err => {
      console.error('Load error:', err);
      tableContent.innerHTML = "<p style='color:red;text-align:center;padding:20px;'>Error loading faculty data.</p>";
    });
}

// Expose globally so inline onclick handlers in the injected HTML can trigger a reload
window.loadFacultyPage = loadFacultyPage;

/* ─────────────────────────────────────────
   PAGINATION & ROWS-PER-PAGE
   (delegated — works after every reload)
───────────────────────────────────────── */
tableContent.addEventListener('click', function(e) {
  if (e.target.matches('.page-btn')) {
    const page  = e.target.getAttribute('data-page');
    const limit = document.getElementById('rows-per-page')?.value || defaultLimit;
    if (page) loadFacultyPage(page, limit);
  }
});

tableContent.addEventListener('change', function(e) {
  if (e.target.matches('#rows-per-page')) {
    loadFacultyPage(1, e.target.value);
  }
});

/* ─────────────────────────────────────────
   SEARCH FILTER
───────────────────────────────────────── */
function filterTable(q) {
  document.querySelectorAll('#faculty-data-table tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}

function toggleFacultyStatus(btn) {
  const row = btn.closest('tr');
  if (!row) return;
  const status = row.querySelector('.name-status');
  if (!status) return;
  status.style.display = status.style.display === 'none' || status.style.display === '' ? 'block' : 'none';
}

/* ─────────────────────────────────────────
   INLINE EDIT
───────────────────────────────────────── */
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
  btn.textContent = 'Saving…';
  btn.disabled = true;

  try {
    const res  = await fetch('/mainscheduler/tabs/actions/faculty_update.php', { method: 'POST', body: data });
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

/* ─────────────────────────────────────────
   DELETE
───────────────────────────────────────── */
async function deleteRow(btn) {
  if (!confirm('Delete this faculty member? This cannot be undone.')) return;
  const row  = btn.closest('tr');
  const id   = row.getAttribute('data-id');
  const data = new FormData();
  data.append('faculty_id', id);

  const orig = btn.innerHTML;
  btn.textContent = 'Deleting…';
  btn.disabled = true;

  try {
    const res  = await fetch('/mainscheduler/tabs/actions/faculty_delete.php', { method: 'POST', body: data });
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

/* ─────────────────────────────────────────
   ADD FACULTY MODAL
───────────────────────────────────────── */
function openAddModal() {
  document.getElementById('id01').style.display = 'block';
}

function closeAddModal() {
  document.getElementById('id01').style.display = 'none';
  document.getElementById('add-faculty-form').reset();
}

document.getElementById('add-faculty-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const submitBtn = this.querySelector('[type="submit"]');
  submitBtn.textContent = 'Creating…';
  submitBtn.disabled = true;

  try {
    const res  = await fetch('/mainscheduler/tabs/actions/faculty_create.php', { method: 'POST', body: formData });
    const json = await res.json();
    if (json.success) {
      closeAddModal();
      loadFacultyPage(1, defaultLimit);
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

window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('id01')) closeAddModal();
});

/* ─────────────────────────────────────────
   INIT
───────────────────────────────────────── */
loadFacultyPage(1, defaultLimit);
</script>

</body>
</html>
