<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../lib/scheduler_staff_helpers.php';

$faculty_id = intval($_GET['faculty_id'] ?? 0);
if ($faculty_id <= 0) {
    echo '<p style="color:#b91c1c;">Invalid faculty member.</p>';
    exit;
}

ensure_relieve_tables($conn);

$facultyStmt = $conn->prepare("SELECT faculty_id, fname, mname, lname, status FROM faculty WHERE faculty_id = ? LIMIT 1");
$facultyStmt->bind_param('i', $faculty_id);
$facultyStmt->execute();
$faculty = $facultyStmt->get_result()->fetch_assoc();
$facultyStmt->close();

if (!$faculty) {
    echo '<p style="color:#b91c1c;">Faculty member not found.</p>';
    exit;
}

$scheduleStmt = $conn->prepare(
    "SELECT s.schedule_id, s.subject_id, s.section_id, s.schedule_date, s.day_of_week, s.start_time, s.end_time,
            sub.subject_name, sec.section_name, sec.grade_level
     FROM schedules s
     JOIN subjects sub ON sub.subject_id = s.subject_id
     JOIN sections sec ON sec.section_id = s.section_id
     WHERE s.faculty_id = ?
     ORDER BY s.schedule_date DESC, s.start_time ASC
     LIMIT 20"
);
$scheduleStmt->bind_param('i', $faculty_id);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

$scheduleGroups = [];
foreach ($schedules as $row) {
    $sectionLabel = trim((string) (($row['grade_level'] ?? '') . ' - ' . ($row['section_name'] ?? '')));
    if ($sectionLabel === '-' || $sectionLabel === '') {
        $sectionLabel = 'Unassigned Section';
    }

    if (!isset($scheduleGroups[$sectionLabel])) {
        $scheduleGroups[$sectionLabel] = [];
    }
    $scheduleGroups[$sectionLabel][] = $row;
}

$relieveStmt = $conn->prepare(
    "SELECT rr.relieve_id, rr.request_date, rr.leave_until_date, rr.day_of_week, rr.start_time, rr.end_time, rr.reason, rr.status,
            sub.subject_name, sec.section_name,
            CONCAT(rep.lname, ', ', rep.fname) AS replacement_name
     FROM relieve_requests rr
     LEFT JOIN subjects sub ON sub.subject_id = rr.subject_id
     LEFT JOIN sections sec ON sec.section_id = rr.section_id
     LEFT JOIN relieve_assignments ra ON ra.relieve_id = rr.relieve_id
     LEFT JOIN faculty rep ON rep.faculty_id = ra.replacement_faculty_id
     WHERE rr.faculty_id = ?
     ORDER BY rr.request_date DESC, rr.start_time ASC
     LIMIT 10"
);
$relieveStmt->bind_param('i', $faculty_id);
$relieveStmt->execute();
$relieveRows = $relieveStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$relieveStmt->close();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTime12Hour($time24)
{
    $timestamp = strtotime((string) $time24);
    if ($timestamp === false) {
        return (string) $time24;
    }
    return date('g:i A', $timestamp);
}
?>
<div style="display:flex; flex-direction:column; gap:18px; color:#1f2937;">
  <div>
    <h2 style="margin:0 0 4px;"><?php echo h($faculty['lname'] . ', ' . $faculty['fname']); ?></h2>
    <p style="margin:0; color:#64748b;">Status: <?php echo h($faculty['status'] ?: 'No status'); ?></p>
  </div>

  <div>
    <h3 style="margin:0 0 10px;">Recent Teaching Schedule</h3>
    <?php if (empty($scheduleGroups)): ?>
      <p style="margin:0; color:#64748b;">No schedule entries found.</p>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($scheduleGroups as $sectionLabel => $sectionSchedules): ?>
          <?php $sectionModalId = 'relieve-section-' . md5($sectionLabel); ?>
          <details style="border:1px solid #e2e8f0; border-radius:12px; background:#fff; overflow:hidden;">
            <summary style="padding:14px 16px; cursor:pointer; font-weight:700; color:#0f172a; background:#f8fafc; display:flex; align-items:center; justify-content:space-between; gap:12px;">
              <span><?php echo h($sectionLabel); ?></span>
              <button
                type="button"
                onclick="event.preventDefault(); event.stopPropagation(); toggleSectionRelieveModal('<?php echo h($sectionModalId); ?>', true);"
                style="padding:5px 10px; border:0; border-radius:6px; background:#0f766e; color:#fff; cursor:pointer; font-weight:700; font-size:12px;"
              >Relieve</button>
            </summary>
            <div style="display:flex; flex-direction:column; gap:10px; padding:12px;">
              <?php foreach ($sectionSchedules as $row): ?>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#fff;">
                  <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                    <div>
                      <div style="font-weight:700;"><?php echo h($row['subject_name']); ?></div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                      <div style="text-align:right; color:#475569;">
                        <div><?php echo h($row['day_of_week']); ?>, <?php echo h($row['schedule_date']); ?></div>
                        <div><?php echo h(formatTime12Hour($row['start_time'])); ?> - <?php echo h(formatTime12Hour($row['end_time'])); ?></div>
                      </div>
                      <button
                        type="button"
                        onclick="toggleSectionRelieveModal('subject-relieve-<?php echo (int) $row['schedule_id']; ?>', true);"
                        style="padding:5px 10px; border:0; border-radius:6px; background:#0f766e; color:#fff; cursor:pointer; font-size:12px; font-weight:700;"
                      >Relieve</button>
                    </div>
                  </div>
                </div>

                <div id="subject-relieve-<?php echo (int) $row['schedule_id']; ?>" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:1250; padding:24px; overflow:auto;">
                  <div style="max-width:640px; margin:0 auto; background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(15,23,42,0.24); overflow:hidden;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e2e8f0;">
                      <div>
                        <div style="font-size:18px; font-weight:700; color:#0f172a;">Relieve <?php echo h($row['subject_name']); ?></div>
                        <div style="font-size:13px; color:#64748b;"><?php echo h($sectionLabel); ?> | <?php echo h($row['day_of_week']); ?>, <?php echo h($row['schedule_date']); ?></div>
                      </div>
                      <button type="button" onclick="toggleSectionRelieveModal('subject-relieve-<?php echo (int) $row['schedule_id']; ?>', false);" style="border:0; background:transparent; font-size:28px; line-height:1; cursor:pointer; color:#64748b;">&times;</button>
                    </div>
                    <div style="padding:16px;">
                      <?php $candidates = relieve_candidate_rows(
                          $conn,
                          (int) ($row['subject_id'] ?? 0),
                          $faculty_id,
                          $row['schedule_date'],
                          $row['day_of_week'],
                          $row['start_time'],
                          $row['end_time'],
                      ); ?>
                      <form class="relieve-form" style="display:grid; gap:8px;" data-schedule-id="<?php echo (int) $row['schedule_id']; ?>">
                        <input type="hidden" name="faculty_id" value="<?php echo (int) $faculty_id; ?>">
                        <input type="hidden" name="schedule_id" value="<?php echo (int) $row['schedule_id']; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo (int) $row['subject_id']; ?>">
                        <input type="hidden" name="section_id" value="<?php echo (int) $row['section_id']; ?>">
                        <input type="hidden" name="request_date" value="<?php echo h($row['schedule_date']); ?>">
                        <input type="hidden" name="day_of_week" value="<?php echo h($row['day_of_week']); ?>">
                        <input type="hidden" name="start_time" value="<?php echo h($row['start_time']); ?>">
                        <input type="hidden" name="end_time" value="<?php echo h($row['end_time']); ?>">
                        <div>
                          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Relieve reason</label>
                          <input type="text" name="reason" placeholder="Meeting, sick leave, training..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div>
                          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Leave until</label>
                          <input type="date" name="leave_until_date" value="<?php echo h($row['schedule_date']); ?>" min="<?php echo h($row['schedule_date']); ?>" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
<input type="hidden" name="leave_scope" value="single">
                        <div>
                          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Replacement faculty</label>
                          <select name="replacement_faculty_id" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                            <option value="0">Leave unassigned</option>
                            <?php foreach ($candidates as $candidate): ?>
                              <option value="<?php echo (int) $candidate['faculty_id']; ?>"><?php echo h($candidate['full_name']); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div style="display:flex; justify-content:flex-end;">
                          <button type="submit" style="padding:8px 12px; border:0; border-radius:6px; background:#0f766e; color:#fff; cursor:pointer;">Create Relieve Request</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </details>

          <?php
            $sectionFirstRow = $sectionSchedules[0];
            $sectionStartDate = (string) ($sectionFirstRow['schedule_date'] ?? '');
            $sectionStartTime = (string) ($sectionFirstRow['start_time'] ?? '00:00:00');
            $sectionEndTime = (string) ($sectionFirstRow['end_time'] ?? '23:59:59');
            $sectionScheduleId = (int) ($sectionFirstRow['schedule_id'] ?? 0);
            $sectionSubjectCount = count(array_unique(array_map(static fn($item) => (int) ($item['subject_id'] ?? 0), $sectionSchedules)));
          ?>
          <div id="<?php echo h($sectionModalId); ?>" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:1200; padding:24px; overflow:auto;">
            <div style="max-width:720px; margin:0 auto; background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(15,23,42,0.24); overflow:hidden;">
              <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e2e8f0;">
                <div>
                  <div style="font-size:18px; font-weight:700; color:#0f172a;">Relieve <?php echo h($sectionLabel); ?></div>
                  <div style="font-size:13px; color:#64748b;">This will apply leave to all classes this faculty teaches in the section.</div>
                </div>
                <button type="button" onclick="toggleSectionRelieveModal('<?php echo h($sectionModalId); ?>', false);" style="border:0; background:transparent; font-size:28px; line-height:1; cursor:pointer; color:#64748b;">&times;</button>
              </div>
              <div style="padding:16px; display:grid; gap:12px;">
                <div style="padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; color:#475569;">
                  <div><strong>Section:</strong> <?php echo h($sectionLabel); ?></div>
                  <div><strong>Classes in this section:</strong> <?php echo (int) count($sectionSchedules); ?></div>
                  <div><strong>Subjects covered:</strong> <?php echo (int) $sectionSubjectCount; ?></div>
                </div>
                <form class="relieve-form" style="display:grid; gap:8px;" data-schedule-id="<?php echo (int) $sectionScheduleId; ?>">
                  <input type="hidden" name="faculty_id" value="<?php echo (int) $faculty_id; ?>">
                  <input type="hidden" name="schedule_id" value="<?php echo (int) $sectionScheduleId; ?>">
                  <input type="hidden" name="subject_id" value="0">
                  <input type="hidden" name="section_id" value="<?php echo (int) ($sectionFirstRow['section_id'] ?? 0); ?>">
                  <input type="hidden" name="request_date" value="<?php echo h($sectionStartDate); ?>">
                  <input type="hidden" name="day_of_week" value="">
                  <input type="hidden" name="start_time" value="<?php echo h($sectionStartTime); ?>">
                  <input type="hidden" name="end_time" value="<?php echo h($sectionEndTime); ?>">
                  <input type="hidden" name="leave_scope" value="whole_section">
                  <div>
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Leave until</label>
                    <input type="date" name="leave_until_date" value="<?php echo h($sectionStartDate); ?>" min="<?php echo h($sectionStartDate); ?>" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                  </div>
                  <div>
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Relieve reason</label>
                    <input type="text" name="reason" placeholder="Meeting, sick leave, training..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                  </div>
                  <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" style="padding:8px 12px; border:0; border-radius:6px; background:#0f766e; color:#fff; cursor:pointer;">Create Section Relieve Request</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <h3 style="margin:0 0 10px;">Recent Relieve Requests</h3>
    <?php if (empty($relieveRows)): ?>
      <p style="margin:0; color:#64748b;">No relieve requests yet.</p>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($relieveRows as $row): ?>
          <div style="border:1px solid #e2e8f0; border-radius:10px; padding:10px; background:#f8fafc;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
              <div style="flex:1; min-width:220px;">
                <div style="font-weight:700;"><?php echo h(($row['subject_name'] ?: 'Schedule duty') . ' - ' . ($row['section_name'] ?: 'No section')); ?></div>
                <div style="color:#475569;"><?php echo h($row['request_date'] . ' ' . formatTime12Hour($row['start_time']) . ' - ' . formatTime12Hour($row['end_time'])); ?></div>
                <div style="color:#475569;">Leave until: <?php echo h($row['leave_until_date'] ?: $row['request_date']); ?></div>
                <div style="color:#475569;">Status: <?php echo h($row['status']); ?><?php if (!empty($row['replacement_name'])): ?> | Replacement: <?php echo h($row['replacement_name']); ?><?php endif; ?></div>
                <?php if (!empty($row['reason'])): ?><div style="color:#64748b;"><?php echo h($row['reason']); ?></div><?php endif; ?>
              </div>
              <div>
                <button
                  type="button"
                  class="relieve-delete-btn"
                  data-relieve-id="<?php echo (int) $row['relieve_id']; ?>"
                  data-faculty-id="<?php echo (int) $faculty_id; ?>"
                  style="padding:8px 12px; border:0; border-radius:6px; background:#b91c1c; color:#fff; cursor:pointer;"
                >Delete</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
