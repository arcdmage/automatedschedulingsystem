<?php
require_once(__DIR__ . '/../db_connect.php');
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

<h1>List of Subjects</h1>
<p>Shows the list of subjects, categorized by grade level and STRAND.</p>

<!-- Subject Table Section -->
<div class="subject-table-container">
  <div id="subject-table-content">
    <!-- Table will load here via AJAX -->
  </div>
</div>

<!-- Add Subject Button -->
<button onclick="document.getElementById('id02').style.display='block'" class="add-subject-btn">Add Subject</button>

<!-- Modal -->
<div id="id02" class="modal">
  <form id="subject-form" class="modal-content animate" action="/mainscheduler/tabs/actions/subject_create.php" method="post">
    <div class="imgcontainer">
      <span onclick="document.getElementById('id02').style.display='none'" class="close" title="Close">&times;</span>
      <h2>Add Subject</h2>
    </div>

    <div class="container">
      <label for="subject_name"><b>Subject Name</b></label>
      <input type="text" placeholder="Subject Name" name="subject_name" required>

      <label for="special"><b>In Specialization</b></label> <!--Make this a drop down outputting the choices as the list of teachers in the main faculty database.-->
      <input type="text" placeholder="In Specialization" name="special" required>

      <label><b>Grade Level</b></label><br>
      <label><input type="radio" name="grade_level" value="11" required> Grade 11</label>
      <label><input type="radio" name="grade_level" value="12"> Grade 12</label>
      <br><br>
      <label><b>Strand</b></label><br>
      <label><input type="radio" name="strand" value="HUMMS" required> HUMMS</label>
      <label><input type="radio" name="strand" value="STEM"> STEM</label>
      <label><input type="radio" name="strand" value="ABM"> ABM</label>
      <label><input type="radio" name="strand" value="GAS"> GAS</label> 
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

  // Handle form submission with AJAX
  const form = document.getElementById('subject-form');
  
  if (form) {
    form.addEventListener("submit", function(e) {
      e.preventDefault(); // Prevent page reload
      e.stopPropagation(); // Stop event bubbling
      
      console.log("Form submitted via AJAX");
      
      const formData = new FormData(form);
      
      fetch("/mainscheduler/tabs/actions/subject_create.php", {
        method: "POST",
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        console.log("Subject added successfully:", data);
        alert("Subject added successfully!");
        document.getElementById('id02').style.display = 'none';
        form.reset();
        loadSubjectPage(1, defaultLimit);
      })
      .catch(error => {
        console.error("Error adding subject:", error);
        alert("Error adding subject. Please try again.");
      });
      
      return false; // Extra prevention
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
