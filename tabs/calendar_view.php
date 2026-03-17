<?php
require_once __DIR__ . "/../db_connect.php";

$month = isset($_GET["month"]) ? $_GET["month"] : date("Y-m");

// Parse the month
$year = date("Y", strtotime($month . "-01"));
$monthNum = date("m", strtotime($month . "-01"));
$monthName = date("F Y", strtotime($month . "-01"));

// Get first and last day of month
$firstDay = date("Y-m-01", strtotime($month . "-01"));
$lastDay = date("Y-m-t", strtotime($month . "-01"));

// Get day of week for first day (0=Sunday, 6=Saturday)
$firstDayOfWeek = date("w", strtotime($firstDay));

// Get total days in month
$totalDays = date("t", strtotime($month . "-01"));

// Calendar is used for special events/meetings only.

// Fetch events for the month
$event_query = "SELECT * FROM events
                WHERE event_date BETWEEN ? AND ?
                ORDER BY event_date, start_time";
$stmt = $conn->prepare($event_query);
$stmt->bind_param("ss", $firstDay, $lastDay);
$stmt->execute();
$events = $stmt->get_result();

// Organize events by date
$eventsByDate = [];
while ($event = $events->fetch_assoc()) {
    $date = $event["event_date"];
    if (!isset($eventsByDate[$date])) {
        $eventsByDate[$date] = [];
    }
    $eventsByDate[$date][] = $event;
}

$conn->close();
?>

<style>
.calendar {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

.calendar-header {
  background-color: #4CAF50;
  color: white;
  text-align: center;
  padding: 15px;
  font-size: 24px;
  font-weight: bold;
}

.calendar th {
  background-color: #e7eef7;
  padding: 10px;
  text-align: center;
  font-weight: 700;
  border: 1px solid #d2dae3;
  color: #1f2937;
  text-shadow: 0 1px 0 rgba(255,255,255,0.6);
}

.calendar td {
  border: 1px solid #ddd;
  padding: 5px;
  vertical-align: top;
  height: 100px;
  width: 14.28%;
  position: relative;
}

.calendar td.empty {
  background-color: #f9f9f9;
}

.calendar td.today {
  background-color: #fff3cd;
}

.day-number {
  font-weight: bold;
  font-size: 14px;
  margin-bottom: 5px;
  color: #333;
}

.event-item {
  background-color: #fff3e0;
  border-left: 3px solid #ff9800;
  padding: 3px 5px;
  margin-bottom: 3px;
  font-size: 11px;
  border-radius: 3px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.event-item:hover {
  background-color: #ffe0b2;
}

.item-time {
  font-weight: bold;
  color: #1976D2;
}

.item-subject {
  color: #333;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-teacher {
  color: #666;
  font-size: 10px;
}

.more-items {
  background-color: #f5f5f5;
  padding: 2px 5px;
  margin-top: 3px;
  font-size: 10px;
  text-align: center;
  border-radius: 3px;
  color: #666;
  cursor: pointer;
}

.legend {
  margin-top: 15px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.legend-color {
  width: 20px;
  height: 20px;
  border-radius: 3px;
}

.legend-color.event {
  background-color: #fff3e0;
  border-left: 3px solid #ff9800;
}
</style>

<div class="calendar-header">
  <?php echo $monthName; ?>
</div>

<table class="calendar">
  <thead>
    <tr>
      <th>Sunday</th>
      <th>Monday</th>
      <th>Tuesday</th>
      <th>Wednesday</th>
      <th>Thursday</th>
      <th>Friday</th>
      <th>Saturday</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $dayCount = 1;
    $today = date("Y-m-d");

    // Calculate total weeks needed
    $weeksNeeded = ceil(($totalDays + $firstDayOfWeek) / 7);

    for ($week = 0; $week < $weeksNeeded; $week++) {
        echo "<tr>";

        for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
            // First week: add empty cells before first day
            if ($week == 0 && $dayOfWeek < $firstDayOfWeek) {
                echo "<td class='empty'></td>";
            }
            // Days that exist in the month
            elseif ($dayCount <= $totalDays) {
                $currentDate = sprintf(
                    "%s-%02d-%02d",
                    $year,
                    $monthNum,
                    $dayCount,
                );
                $isToday = $currentDate == $today ? "today" : "";

                echo "<td class='$isToday'>";
                echo "<div class='day-number'>$dayCount</div>";

                // Display events for this date
                if (isset($eventsByDate[$currentDate])) {
                    foreach ($eventsByDate[$currentDate] as $event) {
                        $startTime = date(
                            "g:i A",
                            strtotime($event["start_time"]),
                        );
                        $endTime = date("g:i A", strtotime($event["end_time"]));
                        $timeRange = trim($startTime . " - " . $endTime);
                        $safeTitle = htmlspecialchars(
                            $event["event_title"] ?? "",
                            ENT_QUOTES,
                        );
                        $safeType = htmlspecialchars(
                            $event["event_type"] ?? "",
                            ENT_QUOTES,
                        );
                        $safeDate = htmlspecialchars(
                            $event["event_date"] ?? "",
                            ENT_QUOTES,
                        );
                        $safeLocation = htmlspecialchars(
                            $event["location"] ?? "",
                            ENT_QUOTES,
                        );
                        $safeDescription = htmlspecialchars(
                            $event["description"] ?? "",
                            ENT_QUOTES,
                        );
                        $safeTime = htmlspecialchars(
                            $timeRange ?? "",
                            ENT_QUOTES,
                        );
                        echo "<div class='event-item' title='{$safeTitle}' data-title='{$safeTitle}' data-type='{$safeType}' data-date='{$safeDate}' data-time='{$safeTime}' data-location='{$safeLocation}' data-description='{$safeDescription}'>";
                        echo "<span class='item-time'>$timeRange</span> ";
                        echo "<span class='item-subject'>{$safeTitle}</span>";
                        echo "</div>";
                    }
                }

                echo "</td>";
                $dayCount++;
            }
            // Empty cells after last day
            else {
                echo "<td class='empty'></td>";
            }
        }

        echo "</tr>";
    }
    ?>
  </tbody>
</table>

<div class="legend">
  <div class="legend-item">
    <div class="legend-color event"></div>
    <span>Event/Meeting</span>
  </div>
</div>
