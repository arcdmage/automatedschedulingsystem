// Loads faculty data into the table via AJAX and updates the table content
/* to be honest I really still don't understand AJAX lol but thanks to open-sources 
 i can somewhat understand it now :D */
function loadFaculty(page = 1, limit = 10) {
  fetch(`tabs/subject_create.php?page=${page}&limit=${limit}`)
    .then(response => response.text())
    .then(html => {
      document.querySelector('#subject_list').innerHTML = html;
    });
}