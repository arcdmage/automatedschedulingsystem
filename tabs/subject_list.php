<?php
require_once __DIR__ . "/../db_connect.php";

$faculty_options = [];
$faculty_query = $conn->query(
    "SELECT CONCAT(lname, ', ', fname) AS name FROM faculty ORDER BY lname, fname",
);
if ($faculty_query) {
    while ($faculty = $faculty_query->fetch_assoc()) {
        $faculty_options[] = $faculty["name"];
    }
}
?>
<!--copied from faculty_member.php just re-configured.-->
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/subject_table.css">
<link rel="stylesheet" href="/mainscheduler/tabs/css/subject_modal.css">
</head>

<body>


<!-- Subject Table Section -->
<div class="subject-table-container">
  <div id="subject-table-content">
    <!-- Table will load here via AJAX -->
  </div>
</div>



<!-- Modal -->
<div id="id02" class="modal">
  <form id="subject-form" class="modal-content animate" action="/mainscheduler/tabs/actions/subject_create.php" method="post">
    <div class="imgcontainer">
      <span onclick="closeSubjectModal()" class="close" title="Close">&times;</span>
      <h2 id="subject-modal-title">Add Subject</h2>
    </div>

    <div class="container">
      <!-- Hidden ID for edit operations -->
      <input type="hidden" name="subject_id" id="subject_id" value="">

      <label for="subject_name"><b>Subject Name</b></label>
      <input type="text" placeholder="Subject Name" name="subject_name" id="subject_name" required>

      <label for="special"><b>In Specialization</b></label>
      <select name="special" id="special" required>
        <option value="">-- Select Faculty --</option>
        <?php foreach ($faculty_options as $faculty_name): ?>
          <option value="<?= htmlspecialchars(
              $faculty_name,
          ) ?>"><?= htmlspecialchars($faculty_name) ?></option>
        <?php endforeach; ?>
      </select>

      <label><b>Grade Level</b></label><br>
      <label><input type="radio" name="grade_level" value="11" id="grade_level_11" required> Grade 11</label>
      <label><input type="radio" name="grade_level" value="12" id="grade_level_12"> Grade 12</label>
      <br><br>

      <label><b>Strand</b></label><br>
      <label><input type="radio" name="strand" value="HUMMS" id="strand_humms" required> HUMMS</label>
      <label><input type="radio" name="strand" value="STEM" id="strand_stem"> STEM</label>
      <label><input type="radio" name="strand" value="ABM" id="strand_abm"> ABM</label>
      <label><input type="radio" name="strand" value="GAS" id="strand_gas"> GAS</label>
      <br><br>

      <button type="submit" id="subject-form-submit">Create</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="closeSubjectModal()" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const tableContent = document.getElementById("subject-table-content");
  const defaultLimit = 5;

  function loadSubjectPage(page = 1, limit = defaultLimit) {
    const url = `/mainscheduler/tabs/subject_table.php?page=${page}&limit=${limit}`;

    fetch(url)
      .then(response => {
        if (!response.ok) {
          throw new Error(`Network response was not ok, status: ${response.status}`);
        }
        return response.text();
      })
      .then(data => {
        tableContent.innerHTML = data;
      })
      .catch(error => {
        console.error("Error loading data:", error);
        tableContent.innerHTML = "<p style='color:red; text-align:center;'>Error loading subject data. Please try again later.</p>";
      });
  }

  // Expose global loader so other code and inline actions can refresh the subject list.
  // Mirrors the pattern used in the Faculty list.
  // Expose globally so inline onclick handlers in the injected HTML can trigger a reload
  window.loadSubjectPage = loadSubjectPage;

  // Move inline edit handlers to the wrapper so they exist before the fragment is injected.
  // These mirror the handlers previously defined inside the injected fragment so that
  // inline Edit/Save/Delete work immediately after the fragment HTML is inserted.
  window.filterSubjectTable = function(q) {
    document.querySelectorAll('#subject-data-table tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes((q||'').toLowerCase()) ? '' : 'none';
    });
  };

  /* Inline edit behavior for subjects (mirrors faculty inline editing) */
  window.startEditSubject = function(btn) {
    const row = btn.closest('tr');
    row.classList.add('editing');
    // hide view elements, show edit inputs/actions
    row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = 'none');
    row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = '');
    const nameDiv = row.querySelector('.e-name');
    if (nameDiv) nameDiv.style.display = 'block';
  };

  window.cancelEditSubject = function(btn) {
    const row = btn.closest('tr');
    row.classList.remove('editing');
    row.querySelectorAll('.v-field, .v-name, .v-actions').forEach(el => el.style.display = '');
    row.querySelectorAll('.e-field, .e-name, .e-actions').forEach(el => el.style.display = 'none');
    const nameDiv = row.querySelector('.e-name');
    if (nameDiv) nameDiv.style.display = 'none';
  };

  window.saveSubject = async function(btn) {
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
  };

  window.deleteSubject = async function(btn) {
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
  };

  // Handle form submission with AJAX (supports both create and update)
  const form = document.getElementById('subject-form');

  function closeSubjectModal() {
    document.getElementById('id02').style.display = 'none';
    form.reset();
    document.getElementById('subject_id').value = '';
    document.getElementById('subject-modal-title').textContent = 'Add Subject';
    document.getElementById('subject-form-submit').textContent = 'Create';
  }

  if (form) {
    form.addEventListener("submit", function(e) {
      e.preventDefault(); // Prevent page reload
      e.stopPropagation();

      const formData = new FormData(form);
      const subjectId = formData.get('subject_id');
      const isEdit = subjectId && subjectId !== '';

      const endpoint = isEdit
        ? '/mainscheduler/tabs/actions/subject_update.php'
        : '/mainscheduler/tabs/actions/subject_create.php';

      // Provide user feedback
      const submitBtn = document.getElementById('subject-form-submit');
      const origText = submitBtn ? submitBtn.textContent : null;
      if (submitBtn) { submitBtn.textContent = isEdit ? 'Saving…' : 'Creating…'; submitBtn.disabled = true; }

      fetch(endpoint, {
        method: 'POST',
        body: formData
      })
      .then(res => {
        // prefer json response; if server returns text, try to parse
        return res.json ? res.json() : res.text();
      })
      .then(resp => {
        // If server returned plain text (legacy), consider it success
        const success = resp && (resp.success === true || typeof resp === 'string');
        if (success) {
          alert(isEdit ? 'Subject updated successfully!' : 'Subject added successfully!');
          closeSubjectModal();
          // reload current listing page (use the JS default if the selector is missing)
          loadSubjectPage(1, document.getElementById('rows-per-page')?.value || defaultLimit);
        } else {
          const msg = (resp && resp.message) ? resp.message : 'Unknown error';
          alert('Error: ' + msg);
        }
      })
      .catch(error => {
        console.error('Error saving subject:', error);
        alert('Error saving subject. Please try again.');
      })
      .finally(() => {
        if (submitBtn) { submitBtn.textContent = origText; submitBtn.disabled = false; }
      });

      return false;
    });
  }

  tableContent.addEventListener('click', function(event) {
    if (event.target.matches('.page-btn')) {
      const pageNum = event.target.getAttribute("data-page");
      const limit = document.getElementById('rows-per-page').value;
      if (pageNum) {
        loadSubjectPage(pageNum, limit);
      }
    }
  });

  tableContent.addEventListener('change', function(event) {
    if (event.target.matches('#rows-per-page')) {
      const newLimit = event.target.value;
      loadSubjectPage(1, newLimit);
    }
  });

  loadSubjectPage(1, defaultLimit);
});

window.onclick = function(event) {
  const modal = document.getElementById('id02');
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
</script>

</body>
</html>
