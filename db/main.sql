-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2026 at 01:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `main`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_schedule_conflict` (IN `p_faculty_id` INT, IN `p_section_id` INT, IN `p_schedule_date` DATE, IN `p_time_slot_id` INT, IN `p_room_id` INT, OUT `p_conflict_type` VARCHAR(50), OUT `p_conflict_message` VARCHAR(255))   BEGIN
    DECLARE teacher_conflict INT DEFAULT 0;
    DECLARE room_conflict INT DEFAULT 0;
    DECLARE section_conflict INT DEFAULT 0;
    DECLARE candidate_start TIME DEFAULT NULL;
    DECLARE candidate_end TIME DEFAULT NULL;

    -- Initialize output parameters
    SET p_conflict_type = NULL;
    SET p_conflict_message = NULL;

    -- Debugging: Log input parameters
    SELECT CONCAT('DEBUG SP: Inputs - FacultyID:', IFNULL(p_faculty_id, 'NULL'), ', SectionID:', IFNULL(p_section_id, 'NULL'), ', Date:', p_schedule_date, ', TimeSlotID:', p_time_slot_id, ', RoomID:', IFNULL(p_room_id, 'NULL')) AS debug_message;

    -- Capture candidate slot times (if available)
    IF p_time_slot_id IS NOT NULL AND p_time_slot_id != 0 THEN
        SELECT start_time, end_time INTO candidate_start, candidate_end
        FROM time_slots
        WHERE time_slot_id = p_time_slot_id
        LIMIT 1;
    END IF;

    -- Check teacher conflict
    IF p_faculty_id IS NOT NULL AND p_faculty_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO teacher_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.faculty_id = p_faculty_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO teacher_conflict
            FROM schedules
            WHERE faculty_id = p_faculty_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log teacher conflict check result
        SELECT CONCAT('DEBUG SP: Teacher Conflict Check - Count:', teacher_conflict) AS debug_message;

        IF teacher_conflict > 0 THEN
            SET p_conflict_type = 'Teacher Conflict';
            SET p_conflict_message = 'Teacher is already scheduled at this time';
        END IF;
    END IF;

    -- Check room conflict (only if p_room_id is provided)
    IF p_conflict_type IS NULL AND p_room_id IS NOT NULL AND p_room_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO room_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.room_id = p_room_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO room_conflict
            FROM schedules
            WHERE room_id = p_room_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log room conflict check result
        SELECT CONCAT('DEBUG SP: Room Conflict Check - Count:', room_conflict) AS debug_message;

        IF room_conflict > 0 THEN
            SET p_conflict_type = 'Room Conflict';
            SET p_conflict_message = 'Room is already scheduled at this time';
        END IF;
    END IF;

    -- Check section conflict
    -- This checks if *another* schedule exists for the *same* section at the *same* time slot.
    -- This might be redundant if the calling script guarantees unique assignments within a section,
    -- but it catches manually added duplicates or cross-generation issues.
    IF p_conflict_type IS NULL AND p_section_id IS NOT NULL AND p_section_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO section_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.section_id = p_section_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO section_conflict
            FROM schedules
            WHERE section_id = p_section_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log section conflict check result
        SELECT CONCAT('DEBUG SP: Section Conflict Check - Count:', section_conflict) AS debug_message;

        IF section_conflict > 0 THEN
            SET p_conflict_type = 'Section Conflict';
            SET p_conflict_message = 'Section already has a schedule at this time';
        END IF;
    END IF;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_type` enum('meeting','training','seminar','holiday','other') NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_title`, `event_type`, `event_date`, `start_time`, `end_time`, `location`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Day Remembrance', 'meeting', '2026-03-20', '06:00:00', '09:00:00', 'Conference Room', 'ExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExampleExample', '2026-03-17 14:33:04', '2026-03-17 14:33:04');

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(6) NOT NULL COMMENT 'needs to be 6 characters',
  `fname` varchar(100) NOT NULL,
  `mname` varchar(100) NOT NULL,
  `lname` varchar(100) NOT NULL,
  `gender` varchar(10) NOT NULL COMMENT 'male, female, or whatever else',
  `pnumber` bigint(16) NOT NULL COMMENT 'phone contact number in 63+',
  `address` varchar(255) NOT NULL COMMENT 'where they live',
  `email` varchar(255) NOT NULL COMMENT 'gmail, yahoo, proton, etc.',
  `status` text NOT NULL COMMENT 'for the state of the individual; sick, on maternity leave, retired, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `fname`, `mname`, `lname`, `gender`, `pnumber`, `address`, `email`, `status`) VALUES
(100031, 'Aileen', 'M.', 'Santos', 'female', 9171234567, 'Bagong Sikat, San Jose', '', 'Married'),
(100032, 'Mark', 'D.', 'Reyes', 'male', 9281234567, 'Poblacion, San Jose', '', 'Single'),
(100033, 'Liza', 'Q.', 'Garcia', 'female', 9091230001, 'Labangan, San Jose', '', 'Single'),
(100034, 'John', 'P.', 'Cruz', 'male', 9181230002, 'San Agustin, San Jose', '', 'Married'),
(100035, 'Erika', 'N.', 'Lopez', 'female', 9211230003, 'Bubog, San Jose', '', 'Single'),
(100036, 'Paolo', 'V.', 'Mendoza', 'male', 9331230004, 'Mangarin, San Jose', '', 'Single'),
(100037, 'Joy', 'S.', 'Villanueva', 'female', 9161230005, 'Pag-asa, San Jose', '', ''),
(100038, 'Ivan', 'T.', 'Ramos', 'male', 9271230006, 'Labangan, San Jose', '', 'Single'),
(100039, 'Chloe', 'B.', 'Diaz', 'female', 9191230007, 'Bagong Sikat, San Jose', '', 'Single'),
(100040, 'Noel', 'R.', 'Torres', 'male', 9081230008, 'Poblacion, San Jose', '', 'Married'),
(100041, 'Cathy', 'L.', 'Navarro', 'female', 9172223344, 'Camburay, San Jose', '', 'Single'),
(100042, 'Oscar', 'J.', 'Serrano', 'male', 9283334455, 'San Isidro, San Jose', '', 'Single'),
(100043, 'Mona', 'E.', 'Domingo', 'female', 9394445566, 'Bubog, San Jose', '', ''),
(100044, 'Gina', 'F.', 'Perez', 'female', 9175556677, 'Poblacion, San Jose', '', 'Single'),
(100045, 'Allan', 'K.', 'Sison', 'male', 9286667788, 'Labo, San Jose', '', 'Married'),
(100046, 'Nina', 'R.', 'Castillo', 'female', 9397778899, 'Mangarin, San Jose', '', 'Single'),
(100047, 'Rodel', 'M.', 'Flores', 'male', 9178889900, 'Bagong Sikat, San Jose', '', ''),
(100048, 'Jessa', 'P.', 'Alvarez', 'female', 9289990011, 'Pag-asa, San Jose', '', 'Single'),
(100049, 'Marvin', 'C.', 'Aguilar', 'male', 9390001122, 'Labangan, San Jose', '', 'Married'),
(100050, 'Diane', 'T.', 'Francisco', 'female', 9171112223, 'San Agustin, San Jose', '', ''),
(100051, 'Leo', 'B.', 'Bautista', 'male', 9282223334, 'Poblacion, San Jose', '', 'Single'),
(100052, 'Karla', 'V.', 'DelosReyes', 'female', 9393334445, 'Mangarin, San Jose', '', 'Single'),
(100053, 'Arvin', 'S.', 'Santiago', 'male', 9174445556, 'Bubog, San Jose', '', 'Married'),
(100054, 'Mira', 'G.', 'Padilla', 'female', 9285556667, 'Bagong Sikat, San Jose', '', ''),
(100055, 'Julio', 'A.', 'Rizal', 'male', 9396667778, 'San Isidro, San Jose', '', 'Single'),
(100056, 'Sheila', 'H.', 'Malonzo', 'female', 9177778889, 'Camburay, San Jose', '', 'Single'),
(100057, 'Carlo', 'D.', 'Rosales', 'male', 9288889990, 'Labangan, San Jose', '', ''),
(100058, 'Tessa', 'I.', 'Lim', 'female', 9399990001, 'Poblacion, San Jose', '', 'Married'),
(100059, 'Ben', 'O.', 'Soriano', 'male', 9170001112, 'Pag-asa, San Jose', '', ''),
(100060, 'Yna', 'W.', 'Vega', 'female', 9281112223, 'Bagong Sikat, San Jose', '', 'Single'),
(100061, 'Randy', 'U.', 'Tolentino', 'male', 9392223334, 'San Agustin, San Jose', '', 'Single'),
(100062, 'Hazel', 'D.', 'Ortega', 'female', 9173334445, 'Mangarin, San Jose', '', ''),
(100063, 'Rhea', 'S.', 'Cortez', 'female', 9284445556, 'Poblacion, San Jose', '', 'Single'),
(100064, 'Enzo', 'M.', 'Pascual', 'male', 9395556667, 'Bubog, San Jose', '', 'Single'),
(100065, 'Liam', 'J.', 'Yu', 'male', 9176667778, 'Labangan, San Jose', '', ''),
(100066, 'Faith', 'P.', 'Cabrera', 'female', 9287778889, 'Bagong Sikat, San Jose', '', 'Single'),
(100067, 'Gio', 'K.', 'Marquez', 'male', 9398889990, 'San Isidro, San Jose', '', 'Married'),
(100068, 'Lara', 'Q.', 'Aguirre', 'female', 9179990001, 'Poblacion, San Jose', '', 'Single'),
(100069, 'Mika', 'L.', 'Tan', 'female', 9280001112, 'Mangarin, San Jose', '', ''),
(100070, 'Noah', 'E.', 'Co', 'male', 9391112223, 'Bubog, San Jose', '', 'Single'),
(100071, 'Bianca', 'Y.', 'Sanchez', 'female', 9172223345, 'Pag-asa, San Jose', '', 'Single'),
(100072, 'Rico', 'N.', 'Villamor', 'male', 9283334456, 'Labangan, San Jose', '', ''),
(100073, 'Kristel', 'I.', 'Abad', 'female', 9394445567, 'San Agustin, San Jose', '', 'Single'),
(100074, 'Ian', 'C.', 'Gonzales', 'male', 9175556678, 'Poblacion, San Jose', '', 'Single'),
(100075, 'Shane', 'E.', 'Salazar', 'female', 9286667789, 'Bagong Sikat, San Jose', '', 'Married'),
(100076, 'Omar', 'F.', 'Lucero', 'male', 9397778890, 'Camburay, San Jose', '', ''),
(100077, 'Pia', 'R.', 'Valdez', 'female', 9178889901, 'Mangarin, San Jose', '', 'Single'),
(100078, 'Ken', 'S.', 'Batista', 'male', 9289990012, 'Bubog, San Jose', '', 'Single'),
(100079, 'Zara', 'T.', 'Morales', 'female', 9390001123, 'Poblacion, San Jose', '', 'Single');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_availability`
--

CREATE TABLE `faculty_availability` (
  `availability_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `room_type` enum('Classroom','Laboratory','Gym','Conference Room','Other') DEFAULT 'Classroom',
  `capacity` int(11) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `room_type`, `capacity`, `building`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'Room 101', 'Classroom', 40, 'Main Building', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50'),
(2, 'Room 102', 'Classroom', 40, 'Main Building', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50'),
(3, 'Room 103', 'Classroom', 40, 'Main Building', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50'),
(4, 'Science Lab 1', 'Laboratory', 30, 'Science Building', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50'),
(5, 'Computer Lab 1', 'Laboratory', 35, 'Tech Building', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50'),
(6, 'Gymnasium', 'Gym', 100, 'Sports Complex', 1, '2025-12-06 05:57:50', '2025-12-06 05:57:50');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `schedule_date` date DEFAULT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `time_slot_id` int(11) DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `section_id`, `faculty_id`, `subject_id`, `schedule_date`, `day_of_week`, `time_slot_id`, `start_time`, `end_time`, `room`, `room_id`, `notes`, `is_auto_generated`, `created_at`, `updated_at`) VALUES
(5935, 13, 100073, 31, '2026-03-16', 'Monday', 76, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5936, 13, 100049, 34, '2026-03-16', 'Monday', 77, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5937, 13, 100068, 32, '2026-03-16', 'Monday', 79, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5938, 13, 100048, 26, '2026-03-16', 'Monday', 80, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5939, 13, 100078, 24, '2026-03-16', 'Monday', 82, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5940, 13, 100051, 25, '2026-03-16', 'Monday', 83, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5941, 13, 100066, 30, '2026-03-16', 'Monday', 85, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5942, 13, 100073, 31, '2026-03-17', 'Tuesday', 76, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5943, 13, 100049, 34, '2026-03-17', 'Tuesday', 77, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5944, 13, 100068, 32, '2026-03-17', 'Tuesday', 79, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5945, 13, 100048, 26, '2026-03-17', 'Tuesday', 80, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5946, 13, 100078, 24, '2026-03-17', 'Tuesday', 82, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5947, 13, 100051, 25, '2026-03-17', 'Tuesday', 83, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5948, 13, 100066, 30, '2026-03-17', 'Tuesday', 85, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5949, 13, 100073, 31, '2026-03-18', 'Wednesday', 76, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5950, 13, 100049, 34, '2026-03-18', 'Wednesday', 77, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5951, 13, 100068, 32, '2026-03-18', 'Wednesday', 79, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5952, 13, 100048, 26, '2026-03-18', 'Wednesday', 80, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5953, 13, 100078, 24, '2026-03-18', 'Wednesday', 82, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5954, 13, 100051, 25, '2026-03-18', 'Wednesday', 83, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5955, 13, 100066, 30, '2026-03-18', 'Wednesday', 85, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5956, 13, 100073, 31, '2026-03-19', 'Thursday', 76, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5957, 13, 100049, 34, '2026-03-19', 'Thursday', 77, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5958, 13, 100068, 32, '2026-03-19', 'Thursday', 79, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5959, 13, 100048, 26, '2026-03-19', 'Thursday', 80, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5960, 13, 100078, 24, '2026-03-19', 'Thursday', 82, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5961, 13, 100051, 25, '2026-03-19', 'Thursday', 83, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5962, 13, 100066, 30, '2026-03-19', 'Thursday', 85, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5963, 13, 100073, 31, '2026-03-20', 'Friday', 76, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5964, 13, 100049, 34, '2026-03-20', 'Friday', 77, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5965, 13, 100068, 32, '2026-03-20', 'Friday', 79, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5966, 13, 100048, 26, '2026-03-20', 'Friday', 80, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5967, 13, 100078, 24, '2026-03-20', 'Friday', 82, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5968, 13, 100051, 25, '2026-03-20', 'Friday', 83, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06'),
(5969, 13, 100066, 30, '2026-03-20', 'Friday', 85, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 1, '2026-03-17 16:41:06', '2026-03-17 16:41:06');

-- --------------------------------------------------------

--
-- Table structure for table `schedules_backup`
--

CREATE TABLE `schedules_backup` (
  `schedule_id` int(11) NOT NULL DEFAULT 0,
  `section_id` int(11) DEFAULT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `time_slot_id` int(11) DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules_backup`
--

INSERT INTO `schedules_backup` (`schedule_id`, `section_id`, `faculty_id`, `subject_id`, `schedule_date`, `day_of_week`, `time_slot_id`, `start_time`, `end_time`, `room`, `room_id`, `notes`, `is_auto_generated`, `created_at`, `updated_at`) VALUES
(1297, 3, 100017, 6, '2026-02-02', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1298, 3, 100017, 7, '2026-02-02', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1299, 3, 100020, 4, '2026-02-02', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1300, 3, 100010, 3, '2026-02-02', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1301, 3, 100016, 2, '2026-02-02', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1302, 3, 100020, 9, '2026-02-02', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1303, 3, 100010, 5, '2026-02-02', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1304, 3, 100017, 6, '2026-02-03', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1305, 3, 100017, 7, '2026-02-03', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1306, 3, 100020, 4, '2026-02-03', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1307, 3, 100010, 3, '2026-02-03', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1308, 3, 100016, 2, '2026-02-03', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1309, 3, 100020, 9, '2026-02-03', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1310, 3, 100010, 5, '2026-02-03', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1311, 3, 100017, 6, '2026-02-04', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1312, 3, 100017, 7, '2026-02-04', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1313, 3, 100020, 4, '2026-02-04', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1314, 3, 100010, 3, '2026-02-04', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1315, 3, 100016, 2, '2026-02-04', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1316, 3, 100020, 9, '2026-02-04', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1317, 3, 100010, 5, '2026-02-04', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1318, 3, 100017, 6, '2026-02-05', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1319, 3, 100017, 7, '2026-02-05', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1320, 3, 100020, 4, '2026-02-05', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1321, 3, 100010, 3, '2026-02-05', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1322, 3, 100016, 2, '2026-02-05', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1323, 3, 100020, 9, '2026-02-05', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1324, 3, 100010, 5, '2026-02-05', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:04', '2026-01-27 14:48:04'),
(1325, 3, 100017, 6, '2026-02-06', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1326, 3, 100017, 7, '2026-02-06', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1327, 3, 100020, 4, '2026-02-06', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1328, 3, 100010, 3, '2026-02-06', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1329, 3, 100016, 2, '2026-02-06', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1330, 3, 100020, 9, '2026-02-06', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1331, 3, 100010, 5, '2026-02-06', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1367, 3, 100017, 6, '2026-02-16', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1368, 3, 100017, 7, '2026-02-16', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1369, 3, 100020, 4, '2026-02-16', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1370, 3, 100010, 3, '2026-02-16', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1371, 3, 100016, 2, '2026-02-16', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1372, 3, 100020, 9, '2026-02-16', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1373, 3, 100010, 5, '2026-02-16', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1374, 3, 100017, 6, '2026-02-17', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1375, 3, 100017, 7, '2026-02-17', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1376, 3, 100020, 4, '2026-02-17', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1377, 3, 100010, 3, '2026-02-17', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1378, 3, 100016, 2, '2026-02-17', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1379, 3, 100020, 9, '2026-02-17', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1380, 3, 100010, 5, '2026-02-17', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1381, 3, 100017, 6, '2026-02-18', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1382, 3, 100017, 7, '2026-02-18', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1383, 3, 100020, 4, '2026-02-18', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1384, 3, 100010, 3, '2026-02-18', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1385, 3, 100016, 2, '2026-02-18', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1386, 3, 100020, 9, '2026-02-18', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1387, 3, 100010, 5, '2026-02-18', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1388, 3, 100017, 6, '2026-02-19', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1389, 3, 100017, 7, '2026-02-19', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1390, 3, 100020, 4, '2026-02-19', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1391, 3, 100010, 3, '2026-02-19', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1392, 3, 100016, 2, '2026-02-19', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1393, 3, 100020, 9, '2026-02-19', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1394, 3, 100010, 5, '2026-02-19', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1395, 3, 100017, 6, '2026-02-20', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1396, 3, 100017, 7, '2026-02-20', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1397, 3, 100020, 4, '2026-02-20', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1398, 3, 100010, 3, '2026-02-20', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1399, 3, 100016, 2, '2026-02-20', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1400, 3, 100020, 9, '2026-02-20', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1401, 3, 100010, 5, '2026-02-20', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1402, 3, 100017, 6, '2026-02-23', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1403, 3, 100017, 7, '2026-02-23', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1404, 3, 100020, 4, '2026-02-23', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1405, 3, 100010, 3, '2026-02-23', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1406, 3, 100016, 2, '2026-02-23', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1407, 3, 100020, 9, '2026-02-23', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1408, 3, 100010, 5, '2026-02-23', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1409, 3, 100017, 6, '2026-02-24', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1410, 3, 100017, 7, '2026-02-24', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1411, 3, 100020, 4, '2026-02-24', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1412, 3, 100010, 3, '2026-02-24', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1413, 3, 100016, 2, '2026-02-24', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1414, 3, 100020, 9, '2026-02-24', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1415, 3, 100010, 5, '2026-02-24', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1416, 3, 100017, 6, '2026-02-25', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1417, 3, 100017, 7, '2026-02-25', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1418, 3, 100020, 4, '2026-02-25', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1419, 3, 100010, 3, '2026-02-25', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1420, 3, 100016, 2, '2026-02-25', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1421, 3, 100020, 9, '2026-02-25', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1422, 3, 100010, 5, '2026-02-25', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1423, 3, 100017, 6, '2026-02-26', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1424, 3, 100017, 7, '2026-02-26', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1425, 3, 100020, 4, '2026-02-26', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1426, 3, 100010, 3, '2026-02-26', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1427, 3, 100016, 2, '2026-02-26', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1428, 3, 100020, 9, '2026-02-26', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1429, 3, 100010, 5, '2026-02-26', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1430, 3, 100017, 6, '2026-02-27', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1431, 3, 100017, 7, '2026-02-27', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1432, 3, 100020, 4, '2026-02-27', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1433, 3, 100010, 3, '2026-02-27', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1434, 3, 100016, 2, '2026-02-27', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1435, 3, 100020, 9, '2026-02-27', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1436, 3, 100010, 5, '2026-02-27', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:48:05', '2026-01-27 14:48:05'),
(1507, 3, 100017, 6, '2026-02-09', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1508, 3, 100017, 7, '2026-02-09', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1509, 3, 100020, 4, '2026-02-09', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1510, 3, 100010, 3, '2026-02-09', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1511, 3, 100016, 2, '2026-02-09', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1512, 3, 100020, 9, '2026-02-09', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1513, 3, 100010, 5, '2026-02-09', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1514, 3, 100017, 6, '2026-02-10', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1515, 3, 100017, 7, '2026-02-10', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1516, 3, 100020, 4, '2026-02-10', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1517, 3, 100010, 3, '2026-02-10', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1518, 3, 100016, 2, '2026-02-10', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1519, 3, 100020, 9, '2026-02-10', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1520, 3, 100010, 5, '2026-02-10', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1521, 3, 100017, 6, '2026-02-11', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1522, 3, 100017, 7, '2026-02-11', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1523, 3, 100020, 4, '2026-02-11', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1524, 3, 100010, 3, '2026-02-11', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1525, 3, 100016, 2, '2026-02-11', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1526, 3, 100020, 9, '2026-02-11', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1527, 3, 100010, 5, '2026-02-11', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1528, 3, 100017, 6, '2026-02-12', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1529, 3, 100017, 7, '2026-02-12', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1530, 3, 100020, 4, '2026-02-12', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1531, 3, 100010, 3, '2026-02-12', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1532, 3, 100016, 2, '2026-02-12', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1533, 3, 100020, 9, '2026-02-12', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1534, 3, 100010, 5, '2026-02-12', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1535, 3, 100017, 6, '2026-02-13', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1536, 3, 100017, 7, '2026-02-13', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1537, 3, 100020, 4, '2026-02-13', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1538, 3, 100010, 3, '2026-02-13', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1539, 3, 100016, 2, '2026-02-13', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1540, 3, 100020, 9, '2026-02-13', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1541, 3, 100010, 5, '2026-02-13', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 14:58:37', '2026-01-27 14:58:37'),
(1612, 2, 100017, 6, '2026-02-02', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1613, 2, 100017, 7, '2026-02-02', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1614, 2, 100020, 4, '2026-02-02', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1615, 2, 100010, 3, '2026-02-02', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1616, 2, 100010, 5, '2026-02-02', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1617, 2, 100020, 2, '2026-02-02', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1618, 2, 100020, 8, '2026-02-02', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1619, 2, 100017, 6, '2026-02-03', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1620, 2, 100017, 7, '2026-02-03', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1621, 2, 100020, 4, '2026-02-03', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1622, 2, 100010, 3, '2026-02-03', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1623, 2, 100010, 5, '2026-02-03', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1624, 2, 100020, 2, '2026-02-03', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1625, 2, 100020, 8, '2026-02-03', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1626, 2, 100017, 6, '2026-02-04', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1627, 2, 100017, 7, '2026-02-04', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1628, 2, 100020, 4, '2026-02-04', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1629, 2, 100010, 3, '2026-02-04', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1630, 2, 100010, 5, '2026-02-04', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1631, 2, 100020, 2, '2026-02-04', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1632, 2, 100020, 8, '2026-02-04', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1633, 2, 100017, 6, '2026-02-05', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1634, 2, 100017, 7, '2026-02-05', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1635, 2, 100020, 4, '2026-02-05', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1636, 2, 100010, 3, '2026-02-05', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1637, 2, 100010, 5, '2026-02-05', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1638, 2, 100020, 2, '2026-02-05', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1639, 2, 100020, 8, '2026-02-05', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1640, 2, 100017, 6, '2026-02-06', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1641, 2, 100017, 7, '2026-02-06', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1642, 2, 100020, 4, '2026-02-06', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1643, 2, 100010, 3, '2026-02-06', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1644, 2, 100010, 5, '2026-02-06', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1645, 2, 100020, 2, '2026-02-06', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1646, 2, 100020, 8, '2026-02-06', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1647, 2, 100017, 6, '2026-02-09', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1648, 2, 100017, 7, '2026-02-09', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1649, 2, 100020, 4, '2026-02-09', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1650, 2, 100010, 3, '2026-02-09', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1651, 2, 100010, 5, '2026-02-09', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1652, 2, 100020, 2, '2026-02-09', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1653, 2, 100020, 8, '2026-02-09', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1654, 2, 100017, 6, '2026-02-10', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1655, 2, 100017, 7, '2026-02-10', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1656, 2, 100020, 4, '2026-02-10', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1657, 2, 100010, 3, '2026-02-10', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1658, 2, 100010, 5, '2026-02-10', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1659, 2, 100020, 2, '2026-02-10', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1660, 2, 100020, 8, '2026-02-10', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1661, 2, 100017, 6, '2026-02-11', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1662, 2, 100017, 7, '2026-02-11', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1663, 2, 100020, 4, '2026-02-11', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1664, 2, 100010, 3, '2026-02-11', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1665, 2, 100010, 5, '2026-02-11', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1666, 2, 100020, 2, '2026-02-11', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1667, 2, 100020, 8, '2026-02-11', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1668, 2, 100017, 6, '2026-02-12', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1669, 2, 100017, 7, '2026-02-12', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1670, 2, 100020, 4, '2026-02-12', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1671, 2, 100010, 3, '2026-02-12', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1672, 2, 100010, 5, '2026-02-12', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1673, 2, 100020, 2, '2026-02-12', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1674, 2, 100020, 8, '2026-02-12', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1675, 2, 100017, 6, '2026-02-13', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1676, 2, 100017, 7, '2026-02-13', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1677, 2, 100020, 4, '2026-02-13', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1678, 2, 100010, 3, '2026-02-13', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1679, 2, 100010, 5, '2026-02-13', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1680, 2, 100020, 2, '2026-02-13', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1681, 2, 100020, 8, '2026-02-13', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1682, 2, 100017, 6, '2026-02-16', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1683, 2, 100017, 7, '2026-02-16', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1684, 2, 100020, 4, '2026-02-16', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1685, 2, 100010, 3, '2026-02-16', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1686, 2, 100010, 5, '2026-02-16', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1687, 2, 100020, 2, '2026-02-16', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1688, 2, 100020, 8, '2026-02-16', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1689, 2, 100017, 6, '2026-02-17', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1690, 2, 100017, 7, '2026-02-17', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1691, 2, 100020, 4, '2026-02-17', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1692, 2, 100010, 3, '2026-02-17', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1693, 2, 100010, 5, '2026-02-17', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1694, 2, 100020, 2, '2026-02-17', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1695, 2, 100020, 8, '2026-02-17', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1696, 2, 100017, 6, '2026-02-18', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1697, 2, 100017, 7, '2026-02-18', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1698, 2, 100020, 4, '2026-02-18', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1699, 2, 100010, 3, '2026-02-18', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1700, 2, 100010, 5, '2026-02-18', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1701, 2, 100020, 2, '2026-02-18', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1702, 2, 100020, 8, '2026-02-18', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1703, 2, 100017, 6, '2026-02-19', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1704, 2, 100017, 7, '2026-02-19', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1705, 2, 100020, 4, '2026-02-19', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1706, 2, 100010, 3, '2026-02-19', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1707, 2, 100010, 5, '2026-02-19', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1708, 2, 100020, 2, '2026-02-19', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1709, 2, 100020, 8, '2026-02-19', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1710, 2, 100017, 6, '2026-02-20', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1711, 2, 100017, 7, '2026-02-20', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1712, 2, 100020, 4, '2026-02-20', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1713, 2, 100010, 3, '2026-02-20', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1714, 2, 100010, 5, '2026-02-20', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1715, 2, 100020, 2, '2026-02-20', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1716, 2, 100020, 8, '2026-02-20', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1717, 2, 100017, 6, '2026-02-23', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1718, 2, 100017, 7, '2026-02-23', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1719, 2, 100020, 4, '2026-02-23', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1720, 2, 100010, 3, '2026-02-23', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1721, 2, 100010, 5, '2026-02-23', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1722, 2, 100020, 2, '2026-02-23', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1723, 2, 100020, 8, '2026-02-23', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1724, 2, 100017, 6, '2026-02-24', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1725, 2, 100017, 7, '2026-02-24', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1726, 2, 100020, 4, '2026-02-24', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1727, 2, 100010, 3, '2026-02-24', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1728, 2, 100010, 5, '2026-02-24', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1729, 2, 100020, 2, '2026-02-24', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1730, 2, 100020, 8, '2026-02-24', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1731, 2, 100017, 6, '2026-02-25', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1732, 2, 100017, 7, '2026-02-25', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1733, 2, 100020, 4, '2026-02-25', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1734, 2, 100010, 3, '2026-02-25', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1735, 2, 100010, 5, '2026-02-25', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1736, 2, 100020, 2, '2026-02-25', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1737, 2, 100020, 8, '2026-02-25', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1738, 2, 100017, 6, '2026-02-26', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1739, 2, 100017, 7, '2026-02-26', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1740, 2, 100020, 4, '2026-02-26', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1741, 2, 100010, 3, '2026-02-26', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1742, 2, 100010, 5, '2026-02-26', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1743, 2, 100020, 2, '2026-02-26', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1744, 2, 100020, 8, '2026-02-26', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1745, 2, 100017, 6, '2026-02-27', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1746, 2, 100017, 7, '2026-02-27', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1747, 2, 100020, 4, '2026-02-27', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1748, 2, 100010, 3, '2026-02-27', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1749, 2, 100010, 5, '2026-02-27', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1750, 2, 100020, 2, '2026-02-27', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1751, 2, 100020, 8, '2026-02-27', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-27 15:07:43', '2026-01-27 15:07:43'),
(1997, 3, 100017, 6, '2026-01-26', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(1998, 3, 100017, 7, '2026-01-26', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(1999, 3, 100020, 4, '2026-01-26', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2000, 3, 100010, 3, '2026-01-26', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2001, 3, 100016, 2, '2026-01-26', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2002, 3, 100020, 9, '2026-01-26', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2003, 3, 100010, 5, '2026-01-26', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2004, 3, 100017, 6, '2026-01-27', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2005, 3, 100017, 7, '2026-01-27', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2006, 3, 100020, 4, '2026-01-27', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2007, 3, 100010, 3, '2026-01-27', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2008, 3, 100016, 2, '2026-01-27', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2009, 3, 100020, 9, '2026-01-27', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2010, 3, 100010, 5, '2026-01-27', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2011, 3, 100017, 6, '2026-01-28', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2012, 3, 100017, 7, '2026-01-28', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2013, 3, 100020, 4, '2026-01-28', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2014, 3, 100010, 3, '2026-01-28', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2015, 3, 100016, 2, '2026-01-28', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2016, 3, 100020, 9, '2026-01-28', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2017, 3, 100010, 5, '2026-01-28', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2018, 3, 100017, 6, '2026-01-29', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2019, 3, 100017, 7, '2026-01-29', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2020, 3, 100020, 4, '2026-01-29', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2021, 3, 100010, 3, '2026-01-29', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2022, 3, 100016, 2, '2026-01-29', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2023, 3, 100020, 9, '2026-01-29', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2024, 3, 100010, 5, '2026-01-29', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2025, 3, 100017, 6, '2026-01-30', NULL, 14, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2026, 3, 100017, 7, '2026-01-30', NULL, 15, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2027, 3, 100020, 4, '2026-01-30', NULL, 17, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2028, 3, 100010, 3, '2026-01-30', NULL, 18, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2029, 3, 100016, 2, '2026-01-30', NULL, 21, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2030, 3, 100020, 9, '2026-01-30', NULL, 23, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2031, 3, 100010, 5, '2026-01-30', NULL, 20, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:33', '2026-01-28 01:08:33'),
(2032, 2, 100017, 6, '2026-01-26', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2033, 2, 100017, 7, '2026-01-26', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2034, 2, 100020, 4, '2026-01-26', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2035, 2, 100010, 3, '2026-01-26', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2036, 2, 100010, 5, '2026-01-26', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2037, 2, 100020, 2, '2026-01-26', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2038, 2, 100020, 8, '2026-01-26', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2039, 2, 100017, 6, '2026-01-27', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2040, 2, 100017, 7, '2026-01-27', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2041, 2, 100020, 4, '2026-01-27', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2042, 2, 100010, 3, '2026-01-27', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2043, 2, 100010, 5, '2026-01-27', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2044, 2, 100020, 2, '2026-01-27', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2045, 2, 100020, 8, '2026-01-27', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2046, 2, 100017, 6, '2026-01-28', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2047, 2, 100017, 7, '2026-01-28', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2048, 2, 100020, 4, '2026-01-28', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2049, 2, 100010, 3, '2026-01-28', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2050, 2, 100010, 5, '2026-01-28', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2051, 2, 100020, 2, '2026-01-28', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2052, 2, 100020, 8, '2026-01-28', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2053, 2, 100017, 6, '2026-01-29', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2054, 2, 100017, 7, '2026-01-29', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41');
INSERT INTO `schedules_backup` (`schedule_id`, `section_id`, `faculty_id`, `subject_id`, `schedule_date`, `day_of_week`, `time_slot_id`, `start_time`, `end_time`, `room`, `room_id`, `notes`, `is_auto_generated`, `created_at`, `updated_at`) VALUES
(2055, 2, 100020, 4, '2026-01-29', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2056, 2, 100010, 3, '2026-01-29', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2057, 2, 100010, 5, '2026-01-29', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2058, 2, 100020, 2, '2026-01-29', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2059, 2, 100020, 8, '2026-01-29', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2060, 2, 100017, 6, '2026-01-30', NULL, 37, '07:30:00', '08:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2061, 2, 100017, 7, '2026-01-30', NULL, 38, '08:30:00', '09:30:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2062, 2, 100020, 4, '2026-01-30', NULL, 40, '09:45:00', '10:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2063, 2, 100010, 3, '2026-01-30', NULL, 41, '10:45:00', '11:45:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2064, 2, 100010, 5, '2026-01-30', NULL, 43, '13:00:00', '14:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2065, 2, 100020, 2, '2026-01-30', NULL, 44, '14:00:00', '15:00:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41'),
(2066, 2, 100020, 8, '2026-01-30', NULL, 46, '15:15:00', '16:15:00', NULL, NULL, 'Auto-generated', 0, '2026-01-28 01:08:41', '2026-01-28 01:08:41');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_generation_logs`
--

CREATE TABLE `schedule_generation_logs` (
  `log_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generation_status` enum('Success','Failed','Partial') NOT NULL,
  `schedules_created` int(11) DEFAULT 0,
  `conflicts_found` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `generation_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_patterns`
--

CREATE TABLE `schedule_patterns` (
  `pattern_id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_patterns`
--

INSERT INTO `schedule_patterns` (`pattern_id`, `requirement_id`, `day_of_week`, `time_slot_id`, `created_at`) VALUES
(175, 43, 'Monday', 76, '2026-03-17 16:36:14'),
(176, 43, 'Tuesday', 76, '2026-03-17 16:36:14'),
(177, 43, 'Wednesday', 76, '2026-03-17 16:36:14'),
(178, 43, 'Thursday', 76, '2026-03-17 16:36:14'),
(179, 43, 'Friday', 76, '2026-03-17 16:36:14'),
(180, 44, 'Monday', 77, '2026-03-17 16:36:19'),
(181, 44, 'Tuesday', 77, '2026-03-17 16:36:19'),
(182, 44, 'Wednesday', 77, '2026-03-17 16:36:19'),
(183, 44, 'Thursday', 77, '2026-03-17 16:36:19'),
(184, 44, 'Friday', 77, '2026-03-17 16:36:19'),
(185, 45, 'Monday', 79, '2026-03-17 16:36:23'),
(186, 45, 'Tuesday', 79, '2026-03-17 16:36:23'),
(187, 45, 'Wednesday', 79, '2026-03-17 16:36:23'),
(188, 45, 'Thursday', 79, '2026-03-17 16:36:23'),
(189, 45, 'Friday', 79, '2026-03-17 16:36:23'),
(190, 46, 'Monday', 80, '2026-03-17 16:36:28'),
(191, 46, 'Tuesday', 80, '2026-03-17 16:36:28'),
(192, 46, 'Wednesday', 80, '2026-03-17 16:36:28'),
(193, 46, 'Thursday', 80, '2026-03-17 16:36:28'),
(194, 46, 'Friday', 80, '2026-03-17 16:36:28'),
(195, 47, 'Friday', 82, '2026-03-17 16:36:39'),
(196, 47, 'Thursday', 82, '2026-03-17 16:36:39'),
(197, 47, 'Wednesday', 82, '2026-03-17 16:36:39'),
(198, 47, 'Tuesday', 82, '2026-03-17 16:36:39'),
(199, 47, 'Monday', 82, '2026-03-17 16:36:39'),
(200, 48, 'Monday', 83, '2026-03-17 16:37:01'),
(201, 48, 'Tuesday', 83, '2026-03-17 16:37:01'),
(202, 48, 'Wednesday', 83, '2026-03-17 16:37:01'),
(203, 48, 'Thursday', 83, '2026-03-17 16:37:01'),
(204, 48, 'Friday', 83, '2026-03-17 16:37:01'),
(210, 49, 'Monday', 85, '2026-03-17 16:41:00'),
(211, 49, 'Tuesday', 85, '2026-03-17 16:41:00'),
(212, 49, 'Wednesday', 85, '2026-03-17 16:41:00'),
(213, 49, 'Thursday', 85, '2026-03-17 16:41:00'),
(214, 49, 'Friday', 85, '2026-03-17 16:41:00');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_templates`
--

CREATE TABLE `schedule_templates` (
  `template_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `track` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `grade_level` varchar(50) NOT NULL,
  `track` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `semester` enum('First Semester','Second Semester','Summer') NOT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level`, `track`, `school_year`, `semester`, `adviser_id`, `created_at`, `updated_at`) VALUES
(13, 'MARS', 'Grade 11', 'STEM', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:32:40'),
(14, 'VENUS', 'Grade 11', 'STEM', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:31:37'),
(15, 'SATURN', 'Grade 12', 'ABM', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:32:14'),
(16, 'JUPITER', 'Grade 12', 'ABM', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:32:50'),
(17, 'MERCURY', 'Grade 11', 'GAS', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:32:32'),
(18, 'PLUTO', 'Grade 12', 'HUMMS', '', '', NULL, '2026-03-17 16:30:43', '2026-03-17 16:32:42');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL COMMENT 'subject name',
  `special` varchar(255) NOT NULL COMMENT 'teacher who specializes',
  `grade_level` tinyint(255) NOT NULL COMMENT 'grade level',
  `strand` varchar(255) NOT NULL COMMENT 'strand'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `special`, `grade_level`, `strand`) VALUES
(24, 'General Physics 1', 'Santos, Reyes', 12, ''),
(25, 'General Physics 2', 'Reyes, Mendoza', 12, ''),
(26, 'Entrepreneurship', 'Garcia, Cruz', 11, ''),
(27, 'Reading and Writing', 'Lopez, Villanueva', 11, ''),
(28, 'Practical Research 1', 'Diaz, Torres, Navarro', 11, ''),
(29, 'Practical Research 2', 'Torres, Perez', 12, ''),
(30, 'Media and Information Literacy', 'Ramos, Domingo', 11, ''),
(31, 'Applied Economics', 'Mendoza, Serrano', 12, ''),
(32, 'DRRM', 'Cruz, Santos', 11, ''),
(33, 'Physical Education', 'Diaz, Flores', 11, ''),
(34, 'Contemporary Philippine Arts', 'Lopez, Villanueva', 12, ''),
(35, 'Oral Communication', 'Garcia, Padilla', 11, '');

-- --------------------------------------------------------

--
-- Table structure for table `subject_requirements`
--

CREATE TABLE `subject_requirements` (
  `requirement_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `hours_per_week` int(11) NOT NULL DEFAULT 1,
  `preferred_days` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `schedule_pattern` text DEFAULT NULL COMMENT 'JSON: {"Monday":["1","2"],"Tuesday":["1","2"]}'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_requirements`
--

INSERT INTO `subject_requirements` (`requirement_id`, `section_id`, `subject_id`, `faculty_id`, `hours_per_week`, `preferred_days`, `notes`, `created_at`, `updated_at`, `schedule_pattern`) VALUES
(43, 13, 31, 100073, 5, NULL, NULL, '2026-03-17 16:33:21', '2026-03-17 16:33:21', NULL),
(44, 13, 34, 100049, 5, NULL, NULL, '2026-03-17 16:33:29', '2026-03-17 16:33:29', NULL),
(45, 13, 32, 100068, 5, NULL, NULL, '2026-03-17 16:33:34', '2026-03-17 16:33:34', NULL),
(46, 13, 26, 100048, 5, NULL, NULL, '2026-03-17 16:33:39', '2026-03-17 16:33:39', NULL),
(47, 13, 24, 100078, 5, NULL, NULL, '2026-03-17 16:33:45', '2026-03-17 16:33:45', NULL),
(48, 13, 25, 100051, 5, NULL, NULL, '2026-03-17 16:33:51', '2026-03-17 16:33:51', NULL),
(49, 13, 30, 100066, 5, NULL, NULL, '2026-03-17 16:34:00', '2026-03-17 16:34:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `time_slot_id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_break` tinyint(1) DEFAULT 0,
  `break_label` varchar(50) DEFAULT NULL,
  `slot_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`time_slot_id`, `start_time`, `end_time`, `is_break`, `break_label`, `slot_order`, `created_at`, `section_id`) VALUES
(76, '07:30:00', '08:30:00', 0, '', 1, '2026-03-17 16:34:14', 13),
(77, '08:30:00', '09:30:00', 0, '', 2, '2026-03-17 16:34:19', 13),
(78, '09:30:00', '09:45:00', 1, 'RECESS', 3, '2026-03-17 16:34:29', 13),
(79, '09:45:00', '10:45:00', 0, '0', 4, '2026-03-17 16:34:37', 13),
(80, '10:45:00', '11:45:00', 0, '0', 5, '2026-03-17 16:34:42', 13),
(81, '11:45:00', '13:00:00', 1, 'LUNCH BREAK', 6, '2026-03-17 16:35:00', 13),
(82, '13:00:00', '14:00:00', 0, '0', 7, '2026-03-17 16:35:20', 13),
(83, '14:00:00', '15:00:00', 0, '0', 8, '2026-03-17 16:35:23', 13),
(84, '15:00:00', '15:15:00', 1, 'RECESS', 9, '2026-03-17 16:35:32', 13),
(85, '15:15:00', '16:15:00', 0, '', 10, '2026-03-17 16:35:43', 13);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_room_utilization`
-- (See below for the actual view)
--
CREATE TABLE `vw_room_utilization` (
`room_id` int(11)
,`room_name` varchar(50)
,`room_type` enum('Classroom','Laboratory','Gym','Conference Room','Other')
,`total_bookings` bigint(21)
,`days_used` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_schedule_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_schedule_details` (
`schedule_id` int(11)
,`section_name` varchar(100)
,`grade_level` varchar(50)
,`track` varchar(50)
,`school_year` varchar(20)
,`semester` enum('First Semester','Second Semester','Summer')
,`adviser_name` varchar(202)
,`subject_name` varchar(255)
,`teacher_name` varchar(303)
,`schedule_date` date
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
,`start_time` time
,`end_time` time
,`time_range` varchar(19)
,`room_name` varchar(50)
,`notes` text
,`is_auto_generated` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_teacher_workload`
-- (See below for the actual view)
--
CREATE TABLE `vw_teacher_workload` (
`faculty_id` int(6)
,`teacher_name` varchar(202)
,`total_classes` bigint(21)
,`sections_taught` bigint(21)
,`subjects_taught` bigint(21)
,`sections` mediumtext
);

-- --------------------------------------------------------

--
-- Structure for view `vw_room_utilization`
--
DROP TABLE IF EXISTS `vw_room_utilization`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_room_utilization`  AS SELECT `r`.`room_id` AS `room_id`, `r`.`room_name` AS `room_name`, `r`.`room_type` AS `room_type`, count(`s`.`schedule_id`) AS `total_bookings`, count(distinct `s`.`day_of_week`) AS `days_used` FROM (`rooms` `r` left join `schedules` `s` on(`r`.`room_id` = `s`.`room_id`)) GROUP BY `r`.`room_id`, `r`.`room_name`, `r`.`room_type` ORDER BY count(`s`.`schedule_id`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_schedule_details`
--
DROP TABLE IF EXISTS `vw_schedule_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_schedule_details`  AS SELECT `s`.`schedule_id` AS `schedule_id`, `sec`.`section_name` AS `section_name`, `sec`.`grade_level` AS `grade_level`, `sec`.`track` AS `track`, `sec`.`school_year` AS `school_year`, `sec`.`semester` AS `semester`, concat(`adv`.`lname`,', ',`adv`.`fname`) AS `adviser_name`, `sub`.`subject_name` AS `subject_name`, concat(`f`.`lname`,', ',`f`.`fname`,' ',`f`.`mname`) AS `teacher_name`, `s`.`schedule_date` AS `schedule_date`, `s`.`day_of_week` AS `day_of_week`, `ts`.`start_time` AS `start_time`, `ts`.`end_time` AS `end_time`, concat(time_format(`ts`.`start_time`,'%h:%i %p'),' - ',time_format(`ts`.`end_time`,'%h:%i %p')) AS `time_range`, `r`.`room_name` AS `room_name`, `s`.`notes` AS `notes`, `s`.`is_auto_generated` AS `is_auto_generated` FROM ((((((`schedules` `s` join `sections` `sec` on(`s`.`section_id` = `sec`.`section_id`)) left join `faculty` `adv` on(`sec`.`adviser_id` = `adv`.`faculty_id`)) join `subjects` `sub` on(`s`.`subject_id` = `sub`.`subject_id`)) join `faculty` `f` on(`s`.`faculty_id` = `f`.`faculty_id`)) left join `time_slots` `ts` on(`s`.`time_slot_id` = `ts`.`time_slot_id`)) left join `rooms` `r` on(`s`.`room_id` = `r`.`room_id`)) WHERE `ts`.`is_break` = 0 OR `ts`.`is_break` is null ORDER BY `sec`.`section_name` ASC, `s`.`schedule_date` ASC, `ts`.`slot_order` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_teacher_workload`
--
DROP TABLE IF EXISTS `vw_teacher_workload`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_teacher_workload`  AS SELECT `f`.`faculty_id` AS `faculty_id`, concat(`f`.`lname`,', ',`f`.`fname`) AS `teacher_name`, count(distinct `s`.`schedule_id`) AS `total_classes`, count(distinct `s`.`section_id`) AS `sections_taught`, count(distinct `s`.`subject_id`) AS `subjects_taught`, group_concat(distinct `sec`.`section_name` separator ', ') AS `sections` FROM ((`faculty` `f` join `schedules` `s` on(`f`.`faculty_id` = `s`.`faculty_id`)) join `sections` `sec` on(`s`.`section_id` = `sec`.`section_id`)) GROUP BY `f`.`faculty_id`, `f`.`lname`, `f`.`fname` ORDER BY count(distinct `s`.`schedule_id`) DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_event_type` (`event_type`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`);

--
-- Indexes for table `faculty_availability`
--
ALTER TABLE `faculty_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_day` (`day_of_week`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_name` (`room_name`),
  ADD KEY `idx_room_type` (`room_type`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_schedule_date` (`schedule_date`),
  ADD KEY `idx_faculty_id` (`faculty_id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `fk_schedule_room` (`room_id`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_day_of_week` (`day_of_week`),
  ADD KEY `idx_time_slot` (`time_slot_id`),
  ADD KEY `idx_schedule_lookup` (`section_id`,`schedule_date`,`day_of_week`,`time_slot_id`);

--
-- Indexes for table `schedule_generation_logs`
--
ALTER TABLE `schedule_generation_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `idx_section` (`section_id`),
  ADD KEY `idx_generation_time` (`generation_time`);

--
-- Indexes for table `schedule_patterns`
--
ALTER TABLE `schedule_patterns`
  ADD PRIMARY KEY (`pattern_id`),
  ADD UNIQUE KEY `unique_pattern` (`requirement_id`,`day_of_week`,`time_slot_id`),
  ADD KEY `time_slot_id` (`time_slot_id`);

--
-- Indexes for table `schedule_templates`
--
ALTER TABLE `schedule_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_track` (`track`),
  ADD KEY `idx_grade_level` (`grade_level`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `adviser_id` (`adviser_id`),
  ADD KEY `idx_section_name` (`section_name`),
  ADD KEY `idx_school_year` (`school_year`,`semester`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- Indexes for table `subject_requirements`
--
ALTER TABLE `subject_requirements`
  ADD PRIMARY KEY (`requirement_id`),
  ADD UNIQUE KEY `unique_section_subject` (`section_id`,`subject_id`),
  ADD KEY `idx_section` (`section_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_faculty` (`faculty_id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`time_slot_id`),
  ADD KEY `idx_slot_order` (`slot_order`),
  ADD KEY `idx_section_timeslots` (`section_id`,`slot_order`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(6) NOT NULL AUTO_INCREMENT COMMENT 'needs to be 6 characters', AUTO_INCREMENT=100080;

--
-- AUTO_INCREMENT for table `faculty_availability`
--
ALTER TABLE `faculty_availability`
  MODIFY `availability_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5970;

--
-- AUTO_INCREMENT for table `schedule_generation_logs`
--
ALTER TABLE `schedule_generation_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `schedule_patterns`
--
ALTER TABLE `schedule_patterns`
  MODIFY `pattern_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=215;

--
-- AUTO_INCREMENT for table `schedule_templates`
--
ALTER TABLE `schedule_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `subject_requirements`
--
ALTER TABLE `subject_requirements`
  MODIFY `requirement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `time_slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `faculty_availability`
--
ALTER TABLE `faculty_availability`
  ADD CONSTRAINT `faculty_availability_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedule_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_schedule_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`time_slot_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_generation_logs`
--
ALTER TABLE `schedule_generation_logs`
  ADD CONSTRAINT `schedule_generation_logs_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_generation_logs_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL;

--
-- Constraints for table `schedule_patterns`
--
ALTER TABLE `schedule_patterns`
  ADD CONSTRAINT `schedule_patterns_ibfk_1` FOREIGN KEY (`requirement_id`) REFERENCES `subject_requirements` (`requirement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_patterns_ibfk_2` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`time_slot_id`);

--
-- Constraints for table `schedule_templates`
--
ALTER TABLE `schedule_templates`
  ADD CONSTRAINT `schedule_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`adviser_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL;

--
-- Constraints for table `subject_requirements`
--
ALTER TABLE `subject_requirements`
  ADD CONSTRAINT `subject_requirements_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_requirements_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_requirements_ibfk_3` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD CONSTRAINT `time_slots_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
