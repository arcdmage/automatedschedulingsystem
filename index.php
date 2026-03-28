<?php
// index.php
require_once __DIR__ . "/lib/app_path.php"; ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="<?= htmlspecialchars(
            app_url("script.js"),
            ENT_QUOTES,
            "UTF-8",
        ) ?>" defer></script>
        <link href="<?= htmlspecialchars(
            app_url("styles.css"),
            ENT_QUOTES,
            "UTF-8",
        ) ?>" rel="stylesheet">
        <link rel="stylesheet" href="<?= htmlspecialchars(
            app_url("tabs/css/subject_table.css"),
            ENT_QUOTES,
            "UTF-8",
        ) ?>">
        <link rel="stylesheet" href="<?= htmlspecialchars(
            app_url("tabs/css/subject_modal.css"),
            ENT_QUOTES,
            "UTF-8",
        ) ?>">
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
