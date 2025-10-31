function loadFaculty(page = 1, limit = 10) {
  fetch(`tabs/faculty_members.php?page=${page}&limit=${limit}`)
    .then(response => response.text())
    .then(html => {
      document.querySelector('#faculty_members').innerHTML = html;
    });
}