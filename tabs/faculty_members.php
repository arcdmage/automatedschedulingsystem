<?php
require_once('db_connect.php');
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
  <div class="table-wrapper">
    <table class="faculty-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Last Name</th>
          <th>Gender</th>
          <th>Phone Number</th>
          <th>Address</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
          // Set limit and current page
          $limit = 10;
          $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
          $offset = ($page - 1) * $limit;

          // Count total rows for pagination
          $total_query = "SELECT COUNT(*) as total FROM faculty";
          $total_result = $conn->query($total_query);
          $total_row = $total_result->fetch_assoc();
          $total_records = $total_row['total'];
          $total_pages = ceil($total_records / $limit);

          // Now get the limited faculty records
          $sql = "SELECT * FROM faculty ORDER BY id ASC LIMIT $limit OFFSET $offset";
          $result = $conn->query($sql);

        if ($result->num_rows > 0) {
while($row = $result->fetch_assoc()) {
           echo '<tr>'
               . '<td>' . htmlspecialchars($row['id']) . '</td>'
               . '<td>' . htmlspecialchars($row['fname']) . '</td>'
               . '<td>' . htmlspecialchars($row['mname']) . '</td>'
               . '<td>' . htmlspecialchars($row['lname']) . '</td>'
               . '<td>' . htmlspecialchars($row['gender']) . '</td>'
               . '<td>' . htmlspecialchars($row['pnumber']) . '</td>'
               . '<td>' . htmlspecialchars($row['address']) . '</td>'
               . '<td>' . htmlspecialchars($row['status']) . '</td>'
               . '</tr>';
          }
        } else {
          echo "<tr><td colspan='8' style='text-align:center;'>No faculty found.</td></tr>";
        }
        $conn->close();
        ?>
      </tbody>
    </table>
  </div>

  <!-- page selector (idk how this works thanks stackoverflow) -->
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?tab=faculty_members&page=<?php echo $page - 1; ?>">&laquo; Prev</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <a href="?tab=faculty_members&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
      <?php echo $i; ?>
    </a>
  <?php endfor; ?>

  <?php if ($page < $total_pages): ?>
    <a href="?tab=faculty_members&page=<?php echo $page + 1; ?>">Next &raquo;</a>
  <?php endif; ?>
</div>


<!-- Add Faculty Button -->
<button onclick="document.getElementById('id01').style.display='block'" class="add-faculty-btn">Add Faculty</button>

<!-- Modal/Faculty Creation form popup or something bruh -->
<div id="id01" class="modal">
  <form class="modal-content animate" action="actions/faculty_create.php" method="post">
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
window.onclick = function(event) {
  const modal = document.getElementById('id01');
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
</script>

</body>
</html>
