-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2025 at 04:18 PM
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
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(6) NOT NULL COMMENT 'needs to be 6 characters',
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

INSERT INTO `faculty` (`id`, `fname`, `mname`, `lname`, `gender`, `pnumber`, `address`, `email`, `status`) VALUES
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
(100017, 'test', 'test', '213123', 'other', 12312312, 'rtste', '', 'test');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_name` varchar(255) NOT NULL COMMENT 'subject name',
  `special` varchar(255) NOT NULL COMMENT 'teacher who specializes',
  `grade_level` tinyint(255) NOT NULL COMMENT 'grade level',
  `strand` varchar(255) NOT NULL COMMENT 'strand'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_name`, `special`, `grade_level`, `strand`) VALUES
('Physics 2', 'Mafeth Jinco', 0, 'STEM'),
('Physics 22', 'Mafeth Jinco2', 0, 'STEM'),
('Genereal Physics 1', 'Mafeth Jinco', 0, ''),
('General Physics 2', 'Mafeth Jinco', 0, 'STEM'),
('GP1', 'Mafeth Jinco', 12, 'STEM'),
('DRRM', 'Evelyn', 12, 'STEM');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT COMMENT 'needs to be 6 characters', AUTO_INCREMENT=100018;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
