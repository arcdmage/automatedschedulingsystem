<?php
// index.php
?>
<!DOCTYPE html> <!--this is not yet a php file just an html-->
<html lang="en">
    <head>
        <script src="script.js" defer></script>
        <link href="styles.css" rel="stylesheet">
        <title>SJNHS Faculty Panel</title>
    </head>
    <body>
        <ul class="tabs"> <!--essentially creates the different tabs at barebones level-->
            <li data-tab-target="#home">Home</li> <!--Shows global datas, date, current events, current number of online users-->
            <li data-tab-target="#faculty_members">Faculty Members</li> <!--Inside will have Teachers, Staff, Non-teaching personell- their status, what subejct they specialize in, etc-->
            <li data-tab-target="#faculty_schedules">Faculty Schedules</li> <!--[FOR ADMIN] Shows all schedules of each individual and lets them EDIT-->
            <li data-tab-target="#subject_list">Subject List</li> <!--Shows all subjects within Grade 11 and 12 including ALL STRANDS-->
            <li data-tab-target="#schedule">Schedule</li> <!--Can vie and edit currently logged in faculty's schedule-->
        </ul>

        <div class="tab-content"> <!--displays the content of the created tabs-->
            <div id="home" data-tab-content class="active"> <!--this tab will always be opened when website starts-->
                <?php include 'tabs\home.php' ?>
            </div>
            <div id="faculty_members" data-tab-content>
                <?php include 'tabs\faculty_members.php' ?>
            </div>
            <div id="faculty_schedules" data-tab-content>
                <?php include 'tabs\faculty_schedules.php' ?>
            </div>
            <div id="subject_list" data-tab-content>
                <?php include 'tabs\subject_list.php' ?>
            </div>
            <div id="schedule" data-tab-content>
                <?php include 'tabs\schedule.php' ?>
            </div>
        </div>
        <div class="googlemap"> 
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3897.241406248389!2d121.0579418752357!3d12.366755387895779!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bb03405c456aab%3A0x781129caa5d5e5ee!2sSan%20Jose%20National%20High%20School!5e0!3m2!1sen!2sph!4v1763306682561!5m2!1sen!2sph" 
                width="600" height="400" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </body>
    <!--a comment tag-->
</html>