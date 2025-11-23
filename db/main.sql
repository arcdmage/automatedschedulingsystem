-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2025 at 12:16 PM
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
(100000, 'Mark Andrie', 'Blanco', 'Santos', 'Male', 0, 'Facoma Street, Brgy. Labangan Poblacion', 'markandrieblancosantos@gmail.com', ''),
(100001, 'Mark Andrie', 'Blanco', 'Santos', 'Male', 9933364675, 'Facoma Street, Brgy. Labangan Poblacion', 'markandrieblancosantos@gmail.com', ''),
(100002, 'Mark Andrie', 'Blanco', 'Santos', 'Male', 9933364675, 'Facoma St. Brgy. Labangan', 'markandrieblancosantos@gmail.com', ''),
(100003, 'Mark Andrie', 'Blanco', 'Santos', 'Male', 9933364675, 'Facoma St. Brgy. Labangan', 'markandrieblancosantos@gmail.com', 'dead inside'),
(100004, 'test', 'test', 'test', 'male', 98786534123, 'facoma street', '', 'feelin giffy'),
(100005, 'test12', 'test12', 'test12', 'other', 9933364675, 'dream land', '', 'fucked'),
(100006, 'test12313', 'test1231', 'test121313', 'other', 993336467513, 'dream landwsdawd', '', 'fuckedawdawd'),
(100007, 'test12313123123', 'test1231213123', 'test121313231', 'other', 993336467513123, 'dream landwsdawd123123123123', '', 'fuckedawdawd123123'),
(100008, 'finaltest', 'a2e', 'aga', 'other', 993336467513123, 'dream landwsdawd123123123123', '', 'fuckedawdawd123123'),
(100009, 'actualy final test', 'a2e', 'aga', 'other', 993336467513123, 'dream landwsdawd123123123123', '', 'fuckedawdawd123123'),
(100010, 'actualy final test aga', 'a2e', 'aga', 'other', 993336467513123, 'dream landwsdawd123123123123', '', 'fuckedawdawd123123'),
(100011, 'final test for product', 'final test for product', 'final test for product', 'other', 993336467513123, 'final test for product', '', 'final test for product'),
(100012, 'Samantha', 'Soemthing', 'Blanco', 'female', 123123123123, 'Somwhere', '', 'Dead'),
(100013, 'Hams', 'Hams', 'Hams', 'male', 99833713233, 'Earth', '', 'prob alive'),
(100014, 'savior', 'savior', 'savior', 'other', 213123123, 'savior', '', 'savior'),
(100015, 'Angerlo', 'Angerlo', 'Angerlo', 'male', 123123123, 'Angerlo', '', 'Angerlo'),
(100016, 'Advent Matthew', 'Sabado', 'Domingo', 'female', 9933364675, 'Somewhere in Earth', '', 'Probably alive but idk tbh'),
(100017, 'test', 'test', '213123', 'other', 12312312, 'rtste', '', 'test'),
(100018, 'test', 'test', 'tes1w', 'other', 123123124657900, 'red', '', 'bluer'),
(100019, '123', '123', '123', 'male', 123456889, 'ytuyuoi', '', 'yuriu'),
(100020, 'tes', 'sa', '4345', 'other', 123567890, 'awd', '', 'awd');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Physics 2', 'Mafeth Jinco', 0, 'STEM'),
(2, 'Physics 22', 'Mafeth Jinco2', 0, 'STEM'),
(3, 'Genereal Physics 1', 'Mafeth Jinco', 0, ''),
(4, 'General Physics 2', 'Mafeth Jinco', 0, 'STEM'),
(5, 'GP1', 'Mafeth Jinco', 12, 'STEM'),
(6, 'DRRM', 'Evelyn', 12, 'STEM'),
(7, 'Entrepreneurship', 'Mam', 12, 'STEM'),
(8, 'test', 'test', 12, 'STEM'),
(9, 'test', 'test', 12, 'ABM'),
(10, 'qwed', 'qwe', 12, 'GAS'),
(11, 'tesa1', 'te23', 12, 'HUMMS');

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
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_schedule_date` (`schedule_date`),
  ADD KEY `idx_faculty_id` (`faculty_id`),
  ADD KEY `idx_subject_id` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(6) NOT NULL AUTO_INCREMENT COMMENT 'needs to be 6 characters', AUTO_INCREMENT=100021;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
