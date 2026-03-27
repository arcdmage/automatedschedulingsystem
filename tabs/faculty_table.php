<?php
require_once __DIR__ . "/../db_connect.php";

$limit_options = [5, 10, 25, 50, 100];
$default_limit = 5;

$limit =
    isset($_GET["limit"]) && in_array((int) $_GET["limit"], $limit_options)
        ? (int) $_GET["limit"]
        : $default_limit;
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$search = isset($_GET["search"]) ? trim((string) $_GET["search"]) : "";
$offset = ($page - 1) * $limit;

$where_sql = "";
$search_param = "";

if ($search !== "") {
    $where_sql =
        "WHERE CONCAT_WS(' ', fname, mname, lname, gender, pnumber, address, email, status) LIKE ?";
    $search_param = "%" . $search . "%";
}

if ($where_sql !== "") {
    $stmt = $conn->prepare(
        "SELECT * FROM faculty $where_sql ORDER BY faculty_id ASC LIMIT ? OFFSET ?",
    );
    $stmt->bind_param("sii", $search_param, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $count_stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM faculty $where_sql",
    );
    $count_stmt->bind_param("s", $search_param);
    $count_stmt->execute();
    $total_records =
        (int) ($count_stmt->get_result()->fetch_assoc()["total"] ?? 0);
} else {
    $result = $conn->query(
        "SELECT * FROM faculty ORDER BY faculty_id ASC LIMIT $limit OFFSET $offset",
    );
    $total_records =
        (int) ($conn
            ->query("SELECT COUNT(*) as total FROM faculty")
            ->fetch_assoc()["total"] ?? 0);
}

$total_pages = max(1, ceil($total_records / $limit));
$page = min($page, $total_pages);
?>

<!-- Toolbar -->
<div class="table-toolbar">
  <div class="table-toolbar-left">
    <span class="toolbar-title">Faculty Members</span>
    <span style="font-size:12px;color:#9ca3af;"><?= $total_records ?> total</span>
  </div>
  <div class="table-toolbar-right">
    <input type="text" id="faculty-search-input" class="search-input" placeholder="Search faculty…" value="<?= htmlspecialchars(
        $search,
    ) ?>" oninput="handleFacultySearch(this.value)" style="width:200px;">
    <button class="add-faculty-btn" onclick="openAddModal()">+ Add Faculty</button>
  </div>
</div>

<!-- Table -->
<div class="table-wrapper">
  <table class="faculty-table" id="faculty-data-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Gender</th>
        <th>Address</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()):

            $initials = strtoupper(
                substr($row["fname"], 0, 1) . substr($row["lname"], 0, 1),
            );
            $gClass = "gender-" . strtolower($row["gender"] ?? "other");
            $phoneEmpty = empty(trim($row["pnumber"] ?? ""));
            $statusEmpty = empty(trim($row["status"] ?? ""));
            ?>
        <tr data-id="<?= (int) $row["faculty_id"] ?>">

          <!-- Name -->
          <td>
            <div class="name-cell">
              <div class="name-avatar"><?= htmlspecialchars($initials) ?></div>
              <div style="min-width:0;">
                <!-- View -->
                <div class="v-name name-primary">
                  <button type="button" class="name-toggle" onclick="toggleFacultyStatus(this)">
                    <?= htmlspecialchars(
                        $row["fname"] .
                            " " .
                            $row["mname"] .
                            " " .
                            $row["lname"],
                    ) ?>
                  </button>
                </div>
                <div class="name-status v-field" style="display:none;">
                  <div class="name-meta-line">
                    <span class="status-label">Phone:</span>
                    <span><?= $phoneEmpty
                        ? "No phone number"
                        : htmlspecialchars($row["pnumber"]) ?></span>
                  </div>
                  <div class="name-meta-line">
                  <span class="status-label">Status:</span>
                  <span class="status-badge <?= $statusEmpty ? "empty" : "" ?>">
                    <?= $statusEmpty
                        ? "No status"
                        : htmlspecialchars($row["status"]) ?>
                  </span>
                  </div>
                </div>
                <!-- Edit -->
                <div class="e-name" style="display:none; flex-wrap:wrap; gap:4px;">
                  <input class="edit-input" name="fname"  value="<?= htmlspecialchars(
                      $row["fname"],
                  ) ?>" placeholder="First"  style="width:78px;">
                  <input class="edit-input" name="mname"  value="<?= htmlspecialchars(
                      $row["mname"],
                  ) ?>" placeholder="Middle" style="width:68px;">
                  <input class="edit-input" name="lname"  value="<?= htmlspecialchars(
                      $row["lname"],
                  ) ?>" placeholder="Last"   style="width:78px;">
                </div>
                <div class="e-field edit-extra" style="display:none;">
                  <input class="edit-input" name="status" value="<?= htmlspecialchars(
                      $row["status"] ?? "",
                  ) ?>" placeholder="Status" style="width:160px;">
                  <input class="edit-input" name="pnumber" value="<?= htmlspecialchars(
                      $row["pnumber"] ?? "",
                  ) ?>" placeholder="Phone" style="width:140px;">
                </div>
              </div>
            </div>
          </td>

          <!-- Gender -->
          <td>
            <span class="v-field gender-badge <?= $gClass ?>"><?= htmlspecialchars(
    ucfirst($row["gender"] ?? ""),
) ?></span>
            <select class="e-field edit-select" name="gender" style="display:none;">
              <option value="female" <?= strtolower($row["gender"]) === "female"
                  ? "selected"
                  : "" ?>>Female</option>
              <option value="male"   <?= strtolower($row["gender"]) === "male"
                  ? "selected"
                  : "" ?>>Male</option>
              <option value="other"  <?= strtolower($row["gender"]) === "other"
                  ? "selected"
                  : "" ?>>Other</option>
            </select>
          </td>

          <!-- Address -->
          <td>
            <span class="v-field"><?= htmlspecialchars(
                $row["address"] ?? "",
            ) ?></span>
            <input class="e-field edit-input" name="address" value="<?= htmlspecialchars(
                $row["address"] ?? "",
            ) ?>" placeholder="Address" style="display:none;">
          </td>

          <!-- Actions -->
          <td>
            <!-- View mode buttons -->
            <div class="v-actions action-group">
              <button class="action-btn" style="background:#0f766e;color:#fff;" onclick="openFacultyScheduleModal(<?= (int) $row["faculty_id"] ?>)">
                Schedule
              </button>
              <button class="action-btn action-btn-edit" onclick="startEdit(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button class="action-btn action-btn-delete" onclick="deleteRow(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                Delete
              </button>
            </div>
            <!-- Edit mode buttons -->
            <div class="e-actions action-group" style="display:none;">
              <button class="action-btn action-btn-save" onclick="saveRow(this)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Save
              </button>
              <button class="action-btn action-btn-cancel" onclick="cancelEdit(this)">Cancel</button>
            </div>
          </td>

        </tr>
        <?php
        endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4"><div class="empty-state"><?= $search !== ""
            ? "No faculty members found for \"" .
                htmlspecialchars($search) .
                "\"."
            : "No faculty members found." ?></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="pagination-container">
  <div class="rows-selector">
    <label for="rows-per-page">Rows per page:</label>
    <select id="rows-per-page">
      <?php foreach ($limit_options as $opt): ?>
        <option value="<?= $opt ?>" <?= $limit == $opt
    ? "selected"
    : "" ?>><?= $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <button class="page-btn" data-page="<?= $page - 1 ?>">&laquo;</button>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <button class="page-btn <?= $i == $page
          ? "active"
          : "" ?>" data-page="<?= $i ?>"><?= $i ?></button>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <button class="page-btn" data-page="<?= $page + 1 ?>">&raquo;</button>
    <?php endif; ?>
  </div>
</div>
