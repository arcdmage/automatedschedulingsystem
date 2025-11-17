<?php
require_once(__DIR__ . '/../db_connect.php');
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/mainscheduler/tabs/css/faculty_table.css">
<link rel="stylesheet" href="/mainscheduler/tabs/css/faculty_modal.css">
</head>

<body>

<h1>Faculty</h1>
<p>Will show different categories of faculty, teachers, staff, and non-teaching personnel.</p>

<!-- Faculty Table Section -->
<div class="faculty-table-container">
  <div id="faculty-table-content">
    <!-- Table will load here via AJAX -->
  </div>
</div>

<!-- Add Faculty Button -->
<button onclick="document.getElementById('id01').style.display='block'" class="add-faculty-btn">Add Faculty</button>

<!-- Modal -->
<div id="id01" class="modal">
  <form class="modal-content animate" action="/mainscheduler/tabs/actions/faculty_create.php" method="post">
    <div class="imgcontainer">
      <span onclick="document.getElementById('id01').style.display='none'" class="close" title="Close">&times;</span>
      <h2>Add Faculty</h2>
    </div>

    <div class="container">
      <label for="fname"><b>First Name</b></label>
      <input type="text" placeholder="First Name" name="fname" required>

      <label for="mname"><b>Middle Name</b></label>
      <input type="text" placeholder="Middle Name" name="mname" required>

      <label for="lname"><b>Last Name</b></label>
      <input type="text" placeholder="Last Name" name="lname" required>

      <label><b>Gender</b></label><br>
      <label><input type="radio" name="gender" value="female"> Female</label>
      <label><input type="radio" name="gender" value="male"> Male</label>
      <label><input type="radio" name="gender" value="other"> Other</label>
      <br><br>

      <label for="pnumber"><b>Phone</b></label>
      <input type="number" placeholder="Phone Number" name="pnumber" required>

      <label for="address"><b>Address</b></label>
      <input type="text" placeholder="Address" name="address" required>

      <label for="status"><b>Status</b></label>
      <input type="text" placeholder="Status" name="status" required>

      <button type="submit">Create</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="document.getElementById('id01').style.display='none'" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const tableContent = document.getElementById("faculty-table-content");
  const defaultLimit = 5;

  function loadFacultyPage(page = 1, limit = defaultLimit) {
    const url = `/mainscheduler/tabs/faculty_table.php?page=${page}&limit=${limit}`;

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

  // Handle form submission with AJAX
  const form = document.querySelector(".modal-content");
  if (form) {
    form.addEventListener("submit", function(e) {
      e.preventDefault(); // Prevent page reload
      
      const formData = new FormData(form);
      
      fetch("/mainscheduler/tabs/actions/faculty_create.php", {
        method: "POST",
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        console.log("Faculty added successfully");
        document.getElementById('id01').style.display = 'none'; // Close modal
        form.reset(); // Clear the form
        loadFacultyPage(1, defaultLimit); // Reload table
      })
      .catch(error => {
        console.error("Error adding faculty:", error);
        alert("Error adding faculty. Please try again.");
      });
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
  const modal = document.getElementById('id01');
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
</script>

</body>
</html>
