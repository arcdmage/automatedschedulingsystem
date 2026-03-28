<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="script.js" defer></script>
        <link href="styles.css" rel="stylesheet">
        <link rel="stylesheet" href="tabs/css/subject_table.css">
        <link rel="stylesheet" href="tabs/css/subject_modal.css">
        <title>SJNHS Faculty Panel</title>
    </head>
    <body>
        <ul class="tabs">
            <li data-tab-target="#home">Home</li>
            <li data-tab-target="#faculty_members">Faculty Members</li>
            <li data-tab-target="#subject_list">Subject List</li>
            <li data-tab-target="#sections_list">Sections List</li>
            <li data-tab-target="#schedule">Schedule</li>
        </ul>

        <div class="tab-content">
            <div id="home" data-tab-content class="active"> <!--this tab will always be opened when website starts-->
                <?php include __DIR__ . "/tabs/home.php"; ?>
            </div>
            <div id="faculty_members" data-tab-content>
                <?php include __DIR__ . "/tabs/faculty_members.php"; ?>
            </div>
            <div id="subject_list" data-tab-content>
                <?php include __DIR__ . "/tabs/subject_list.php"; ?>
            </div>
            <div id="sections_list" data-tab-content>
                <?php include __DIR__ . "/tabs/sections_list.php"; ?>
            </div>
            <div id="schedule" data-tab-content>
                <?php include __DIR__ . "/tabs/schedule.php"; ?>
            </div>
        </div>
    </body>
</html>
