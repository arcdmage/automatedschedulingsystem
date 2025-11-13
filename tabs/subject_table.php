<?php
require_once(__DIR__ . '/../db_connect.php');

// Define the available options for rows per page
$limit_options = [5, 10, 25, 50, 100];
// Define a default limit in case none is selected
$default_limit = 5;

// Get the limit from the URL. If it's not set or not a valid option, use the default.
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : $default_limit;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch data for this page using the dynamic limit
$sql = "SELECT * FROM subjects ORDER BY subject_name ASC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Count total records for pagination
$total_query = "SELECT COUNT(*) as total FROM subjects";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);
?>

<!-- Subject Table -->
<div class="subject-table-wrapper">
  <table class="subject-table">
    <thead>
      <tr>
        <th>Subject Name</th>
        <th>In Specialization of</th>
        <th>Grade Level</th>
        <th>Strand</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
          // Your table row output remains the same
          echo "<tr>
            <td>{$row['subject_name']}</td>
            <td>{$row['special']}</td>
            <td>{$row['grade_level']}</td>
            <td>{$row['strand']}</td>
          </tr>";
        }
      } else {
        echo "<tr><td colspan='8' style='text-align:center;'>No subject found.</td></tr>"; //if no data
      }
      ?>
    </tbody>
  </table>
</div>

<!-- Container for Pagination and Row Selector -->
<div class="pagination-container">
  <!-- Rows Per Page Selector -->
  <div class="rows-selector">
    <label for="rows-per-page">Rows per page:</label>
    <select id="rows-per-page">
      <?php foreach ($limit_options as $option): ?>
        <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>>
          <?= $option ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Pagination Buttons -->
  <div class="pagination">
    <?php if ($page > 1): ?>
      <button class="page-btn" data-page="<?= $page - 1 ?>">&laquo; Prev</button>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <button class="page-btn <?= $i == $page ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <button class="page-btn" data-page="<?= $page + 1 ?>">Next &raquo;</button>
    <?php endif; ?>
  </div>
</div>