function loadFaculty(page = 1, limit = 10) {
  fetch(`tabs/faculty_members.php?page=${page}&limit=${limit}`)
    .then(response => response.text())
    .then(html => {
      document.querySelector('#faculty_members').innerHTML = html;
    });
}

// ✅ This matches the onclick="openAddModal()" in your PHP button
function openAddModal() {
  const modal   = document.getElementById('addFacultyModal');
  const content = modal.querySelector('.modal-content');

  // Reset animation so it pops every time
  content.style.animation = 'none';
  content.offsetHeight; // force reflow
  content.style.animation = '';

  modal.style.display = 'block';
}

// ✅ Close functions also need to be global for the same reason
function closeAddModal() {
  document.getElementById('addFacultyModal').style.display = 'none';
}

// Close when clicking the dark overlay
window.addEventListener('click', function (e) {
  const modal = document.getElementById('addFacultyModal');
  if (e.target === modal) {
    modal.style.display = 'none';
  }
});