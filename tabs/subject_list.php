<?php
require_once(__DIR__ . '/../db_connect.php');
?>
<!--copied from faculty_member.php just re-configured.-->
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/table.css">
<link rel="stylesheet" href="/mainscheduler/tabs/css/faculty_modal.css">
</head>

<body>

<h1>List of Subjects</h1>
<p>Shows the list of subjects, categorized by grade level and STRAND.</p>

<!-- Subject Table Section -->
<div class="faculty-table-container">
  <div id="faculty-table-content">
    <!-- Table will load here via AJAX -->
  </div>
</div>

<!-- Add Faculty Button -->
<button onclick="document.getElementById('id02').style.display='block'" class="add-subject-btn">Add Subject</button>

<!-- Modal -->
<div id="id02" class="modal">
  <form class="modal-content animate" action="/mainscheduler/tabs/actions/subject_create.php" method="post">
    <div class="imgcontainer">
      <span onclick="document.getElementById('id02').style.display='none'" class="close" title="Close">&times;</span>
      <h2>Add Subject</h2>
    </div>

    <div class="container">
      <label for="fname"><b>Subject Name</b></label>
      <input type="text" placeholder="Subject Name" name="sname" required>

      <label for="lname"><b>In Specialization</b></label> <!--Make this a drop down outputting the choices as the list of teachers in the main faculty database.-->
      <input type="text" placeholder="In Specialization" name="special" required>

      <label><b>Grade Level</b></label><br> <!--Output this into database-->
      <label><input type="radio" name="gradelvl" value="Grade 11"> Grade 11</label>
      <label><input type="radio" name="gradelvl" value="Grade 12"> Grade 12</label>
      <br><br>

      <button type="submit">Create</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="document.getElementById('id02').style.display='none'" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const tableContent = document.getElementById("faculty-table-content");
  const defaultLimit = 5;

    /**
     * @param {number} page
     * @param {number} limit
     */
  
  function loadFacultyPage(page = 1, limit = defaultLimit) {

    // Use an absolute path starting with your project's root folder instead of Relative REMEBER PLEZ.
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
        tableContent.innerHTML = "<p style='color:red; text-align:center;'>Error loading faculty data. Please try again later.</p>";
      });
  }
  tableContent.addEventListener('click', function(event) {
    if (event.target.matches('.page-btn')) {
      const pageNum = event.target.getAttribute("data-page");
      const limit = document.getElementById('rows-per-page').value;
      if (pageNum) {
        loadFacultyPage(pageNum, limit);
      }
    }
  });

  tableContent.addEventListener('change', function(event) {
    if (event.target.matches('#rows-per-page')) {
      const newLimit = event.target.value;
      loadFacultyPage(1, newLimit);
    }
  });

  loadFacultyPage(1, defaultLimit);
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
