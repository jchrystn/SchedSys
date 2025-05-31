-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2025 at 01:06 PM
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
-- Database: `schedsys`
--

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `msg_id` int(11) NOT NULL COMMENT '\r\n',
  `incoming_msg_id` int(255) NOT NULL,
  `outgoing_msg_id` int(255) NOT NULL,
  `msg` varchar(1000) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT 'pending; 1=read;',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '\r\n'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `college_code` varchar(10) NOT NULL,
  `status_type` varchar(100) NOT NULL,
  `user_type` varchar(10) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `cvsu_email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_admin`
--

INSERT INTO `tbl_admin` (`college_code`, `status_type`, `user_type`, `first_name`, `middle_initial`, `last_name`, `cvsu_email`, `password`) VALUES
('CEIT', 'Online', 'Admin', 'Florence', 'M', 'Banasihan', 'admin.ceit@cvsu.edu.ph', '$2y$10$F6YUX6kkQDg/G6mvymtG8OfmEzyNG9OQ5qYcFcIMR6nTrhX.PmPMe');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointment`
--

CREATE TABLE `tbl_appointment` (
  `id` int(11) NOT NULL,
  `appointment_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointment`
--

INSERT INTO `tbl_appointment` (`id`, `appointment_code`) VALUES
(3, 'Assistant Professor I'),
(4, 'Assistant Professor II'),
(5, 'Assistant Professor III'),
(6, 'Assistant Professor IV'),
(7, 'Associate Professor 1'),
(8, 'Associate Professor 2'),
(9, 'Associate Professor 3'),
(10, 'Associate Professor 4'),
(11, 'Instructor 1'),
(12, 'Instructor 2');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_assigned_course`
--

CREATE TABLE `tbl_assigned_course` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `prof_name` varchar(100) NOT NULL,
  `course_code` varchar(100) NOT NULL,
  `year_level` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `course_counter` int(100) NOT NULL,
  `ay_code` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ay`
--

CREATE TABLE `tbl_ay` (
  `id` int(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `ay_code` int(50) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_name` varchar(100) NOT NULL,
  `active` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ay`
--

INSERT INTO `tbl_ay` (`id`, `college_code`, `ay_code`, `semester`, `ay_name`, `active`) VALUES
(2, 'CAS', 2425, '1st Semester', '2024 - 2025', 1),
(10, 'CEIT', 2425, '1st Semester', '2024 - 2025', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_code`
--

CREATE TABLE `tbl_code` (
  `cvsu_email` varchar(100) NOT NULL,
  `code` int(6) NOT NULL,
  `code_created_at` int(50) NOT NULL,
  `code_expiry` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_college`
--

CREATE TABLE `tbl_college` (
  `college_code` varchar(100) NOT NULL,
  `college_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_college`
--

INSERT INTO `tbl_college` (`college_code`, `college_name`) VALUES
('CAS', 'COLLEGE OF ARTS AND SCIENCES'),
('CEIT', 'COLLEGE OF ENGINEERING AND INFORMATION TECHNOLOGY');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_course`
--

CREATE TABLE `tbl_course` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `petition` int(2) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `allowed_rooms` varchar(10) NOT NULL,
  `computer_room` int(1) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` int(20) NOT NULL,
  `course_code` varchar(100) NOT NULL,
  `course_name` varchar(200) NOT NULL,
  `course_type` varchar(100) NOT NULL,
  `credit` int(20) NOT NULL,
  `lec_hrs` float NOT NULL,
  `lab_hrs` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_course`
--

INSERT INTO `tbl_course` (`id`, `dept_code`, `program_code`, `petition`, `curriculum`, `allowed_rooms`, `computer_room`, `year_level`, `semester`, `ay_code`, `course_code`, `course_name`, `course_type`, `credit`, `lec_hrs`, `lab_hrs`) VALUES
(53, 'DIT', 'BSIT', 1, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 2425, 'ITEC 111A', 'IT ELECTIVE 3 (INTEGRATED PROGRAMMING AND TECHNOLOGIES 2)', 'Major', 3, 2, 3),
(54, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'ITEC 116', 'IT ELECTIVE 4 (SYSTEMS INTEGRATION AND ARCHITECTURE 2)', 'Major', 3, 2, 3),
(55, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'ITEC 110', 'SYSTEMS ADMINISTRATION AND MAINTENANCE', 'Major', 3, 2, 3),
(56, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ITEC 200B', 'CAPSTONE PROJECT AND RESEARCH 2', 'Major', 3, 0, 0),
(57, 'DSS', 'BSIT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'GNED 09', 'RIZAL: LIFE, WORKS, AND WRITINGS', 'Minor', 3, 3, 0),
(58, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ITEC 95', 'QUANTITATIVE METHODS (MODELING &  SIMULATION) ', 'Major', 3, 3, 0),
(59, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ITEC 101', 'IT ELECTIVE 1 (HUMAN COMPUTER  INTERACTION 2) ', 'Major', 3, 2, 3),
(60, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ITEC 106', 'IT ELECTIVE 2 (WEB SYSTEM AND  TECHNOLOGIES 2) ', 'Major', 3, 2, 3),
(61, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ITEC 100', 'INFORMATION ASSURANCE AND  SECURITY 2 ', 'Major', 3, 2, 3),
(62, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ITEC 105', 'NETWORK MANAGEMENT ', 'Major', 3, 2, 3),
(64, 'DIET', 'BSECE', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'AP', 'ARALING PANLIPUNAN', 'Major', 3, 3, 3),
(65, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'COSC 75', 'SOFTWARE ENGINEERING II ', 'Major', 3, 3, 0),
(68, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'COSC 80', 'OPERATING SYSTEMS ', 'Major', 3, 2, 3),
(69, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'COSC 85 ', 'NETWORKS AND COMMUNICATION', 'Major', 3, 2, 3),
(70, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'COSC 101 ', 'CS ELECTIVE 1 (COMPUTER GRAPHICS AND VISUAL COMPUTING)', 'Major', 3, 2, 3),
(71, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'DCIT 26', 'APPLICATIONS DEVELOPMENT AND EMERGING TECHNOLOGIES ', 'Major', 3, 2, 3),
(72, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'DCIT 65 ', 'SOCIAL AND PROFESSIONAL ISSUES', 'Major', 3, 2, 0),
(73, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'MATH 3', 'LINEAR ALGEBRA', 'Minor', 3, 3, 0),
(74, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ITEC 80', 'HUMAN COMPUTER INTERACTION ', 'Major', 1, 3, 0),
(75, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'COSC 100', 'AUTOMATA THEORY AND FORMAL LANGUAGES', 'Major', 3, 2, 3),
(76, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'COSC 105 ', 'NTELLIGENT SYSTEMS', 'Major', 3, 2, 3),
(77, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'COSC 200A', 'UNDERGRADUATE THESIS I ', 'Major', 3, 1, 0),
(78, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'COSC 111', 'CS ELECTIVE 3 (INTERNET OF THINGS)', 'Major', 3, 2, 3),
(79, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 04 ', 'MGA BABASAHIN HINGGIL SA KASAYSAYAN NG PILIPINAS', 'Minor', 3, 3, 0),
(80, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'MATH 1', 'ANALYTIC GEOMETRY', 'Minor', 3, 3, 0),
(81, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'COSC 55', 'DISCRETE STRUCTURES II', 'Major', 3, 3, 0),
(82, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'COSC 60', 'DIGITAL LOGIC DESIGN', 'Major', 3, 2, 3),
(83, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'DCIT 50', 'OBJECT ORIENTED PROGRAMMING ', 'Major', 3, 2, 3),
(84, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'DCIT 24', 'INFORMATION MANAGEMENT ', 'Major', 3, 2, 3),
(85, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'INSY 50 ', 'FUNDAMENTALS OF INFORMATION SYSTEMS', 'Major', 3, 3, 0),
(86, 'CSPEAR', 'BSCS', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'FITT 3 ', 'PHYSICAL ACTIVITIES TOWARDS HEALTH AND FITNESS 1 ', 'Minor', 2, 2, 2),
(87, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 02 ', 'ETHICS', 'Minor', 3, 3, 0),
(88, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 05', 'PURPOSIVE COMMUNICATION', 'Minor', 3, 3, 0),
(89, 'DSS', 'BSCS', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'KONTESKTWALISADONG KOMUNIKASYON SA FILIPINO ', 'Minor', 3, 3, 0),
(90, 'DIT', 'BSCS', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'COSC 50 ', 'DISCRETE STRUCTURES I', 'Major', 3, 3, 0),
(91, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'DCIT 21 ', 'INTRODUCTION TO COMPUTING ', 'Major', 3, 2, 3),
(92, 'DIT', 'BSCS', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'DCIT 22', 'COMPUTER PROGRAMMING I ', 'Major', 3, 2, 3),
(93, 'CSPEAR', 'BSCS', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'FITT 1', 'MOVEMENT ENHANCEMENT ', 'Minor', 3, 2, 0),
(94, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 0, '3rd Year', '1st Semester', 0, 'ITEC 80', 'INTRODUCTION TO HUMAN COMPUTER INTERACTION ', 'Major', 3, 2, 3),
(95, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ITEC 85', 'INFORMATION ASSURANCE AND SECURITY 1', 'Major', 3, 2, 3),
(96, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ITEC 90', 'NETWORK FUNDAMENTALS', 'Major', 3, 2, 3),
(97, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'INSY 55', 'SYSTEM ANALYSIS AND DESIGN', 'Major', 3, 2, 3),
(98, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'DCIT 26', 'APPLICATION DEVELOPMENT AND EMERGING TECHNOLOGIES', 'Major', 3, 2, 3),
(99, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'DCIT 60', 'METHODS OF RESEARCH', 'Major', 3, 3, 0),
(100, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 04', 'MGA BABASAHIN HINGGIL SA KASAYSAYAN NG PILIPINAS', 'Minor', 3, 3, 0),
(101, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 07', 'THE CONTEMPORARY WORLD', 'Minor', 3, 3, 0),
(102, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 10', 'GENDER AND SOCIETY', 'Minor', 3, 3, 0),
(103, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 14', 'PANITIKANG PANLIPUNAN', 'Minor', 3, 3, 0),
(104, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'ITEC 55', 'PLATFORM TECHNOLOGIES', 'Major', 3, 2, 3),
(105, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'DCIT 24', 'INFORMATION MANAGEMENT', 'Major', 3, 2, 3),
(106, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'DCIT 50', 'OBJECT ORIENTED PROGRAMMING', 'Major', 3, 2, 3),
(107, 'CSPEAR', 'BSIT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'FITT 3', 'PHYSICAL ACTIVITIES TOWARDS HEALTH AND FITNESS I ', 'Minor', 2, 2, 0),
(108, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 02', 'ETHICS', 'Minor', 3, 3, 0),
(110, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'KONTESKTWALISADONG KOMUNIKASYON SA FILIPINO ', 'Minor', 3, 3, 0),
(111, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'COSC 50 ', 'DISCRETE STRUCTURES I', 'Major', 3, 3, 0),
(112, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'DCIT 21 ', 'INTRODUCTION TO COMPUTING ', 'Major', 3, 2, 3),
(113, 'DIT', 'BSIT', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'DCIT 22', 'COMPUTER PROGRAMMING I ', 'Major', 3, 2, 6),
(114, 'CSPEAR', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'FITT 1', 'MOVEMENT ENHANCEMENT ', 'Minor', 3, 2, 0),
(115, 'CAS', 'BSTAT', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'STAT', 'STATISTIC', 'Major', 3, 3, 3),
(116, 'CAS', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 05', 'PURPOSIVE COMMUNICATION', 'Minor', 3, 3, 0),
(117, 'DIT', 'BSIT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CVSU 101', 'INSTITUTIONAL ORIENTATION', 'Minor', 1, 1, 0),
(118, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 05', 'RIZAL', 'Minor', 3, 1, 0),
(119, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'AFGDSF', 'Minor', 3, 2, 0),
(120, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CVSU 101', 'Institutional Orientation', 'Minor', 1, 1, 0),
(121, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 03', 'Mathematics in the Modern World', 'Minor', 3, 3, 0),
(122, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 05', 'Purposive Communication', 'Minor', 3, 3, 0),
(123, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 08', 'Understanding the Self', 'Minor', 3, 3, 0),
(124, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'Kontekstwalisadong Komunikasyon sa Fil.', 'Minor', 3, 3, 0),
(125, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'MATH 11', 'Calculus 1', 'Major', 3, 3, 0),
(126, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'CHEM 14', 'Chemistry for Engineers', 'Major', 4, 3, 3),
(127, 'DCEE', 'BSECE', 0, 'New', 'labR', 1, '1st Year', '1st Semester', 0, 'DRAW 23', 'CADD', 'Major ', 1, 0, 3),
(128, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'NSTP 1', 'Nationa Service Training Program I', 'Minor ', 3, 3, 0),
(129, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'MATH 12', 'Calculus 2', 'Major ', 3, 3, 0),
(130, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'PHYS 14', 'Physics for Engineering', 'Major ', 4, 3, 3),
(131, 'DCEE', 'BSECE', 0, 'New', 'labR', 1, '1st Year', '2nd Semester', 0, 'CpEN 21', 'Programming ogic and Design', 'Major ', 2, 0, 6),
(132, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'MATH 14', 'Engineering Data Analysis', 'Major ', 3, 3, 0),
(133, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ECEN 55', 'Material Science and Engineering', 'Major ', 3, 3, 0),
(134, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'PHYS 24', 'Physics for ECE', 'Major ', 4, 3, 4),
(135, 'CSPEAR', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'FITT 2', 'Fitness Exercise', 'Minor ', 2, 2, 0),
(136, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'NSTP 2', 'National Service Training Program II', 'Minor ', 3, 3, 0),
(137, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 06', 'Science, Technoogy and Engg. in Society', 'Minor', 3, 3, 0),
(138, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 14', 'Panitikang Panlipunan', 'Minor', 3, 3, 0),
(139, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '2ndst Year', '1st Semester', 0, 'MATH 13', 'Differential Equations', 'Major', 3, 3, 0),
(140, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ENGS 30', 'Engineering Economics', 'Major', 3, 3, 0),
(141, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'EENG 50', 'Electrical Circuits 1', 'Major', 4, 3, 0),
(142, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'ECEN 60', 'Electronics 1 (Electronic Devices and Ckts.)', 'Major', 4, 3, 3),
(143, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ECEN 65', 'ECE Laws and Ethics', 'Major', 3, 3, 0),
(144, 'CSPEAR', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'FITT 3', 'Physical Activities Towards Heath and Fitness 1', 'Minor ', 2, 2, 0),
(145, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'ENSC 21a', 'Advanced Engineering Mathematics', 'Major', 4, 3, 3),
(146, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'DCEE 27', 'Electromagnetics', 'Major', 4, 3, 3),
(147, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'ECEN 80', 'Communications 1 (Principes of Comm Sys.)', 'Major', 4, 3, 3),
(148, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ENSC 31a', 'Engineering Management', 'Major', 2, 2, 0),
(149, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'EENG 60', 'Electrical Circuits 2', 'Major', 4, 3, 3),
(150, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'ECEN 70', 'Electronics 2 (Electronic Ckts Analysis & Des.)', 'Major', 4, 3, 3),
(151, 'CSPEAR', 'BSECE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'FITT 4', 'Physical Activities Towards Heath and Fitness 2', 'Minor ', 2, 2, 0),
(152, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'DCEE 26', 'Methods of Research', 'Major', 3, 3, 0),
(153, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ECEN 75', 'Signals, Spectra and Signal Processing', 'Major', 4, 3, 3),
(154, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'GNED 12', 'Dalumat Ng/Sa Fiipino', 'Minor', 3, 3, 0),
(155, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ECEN 85', 'Electronics 3 (Electronic System & Design)', 'Major', 4, 3, 3),
(156, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ECEN 90', 'Communications 2', 'Major', 4, 3, 3),
(157, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'CpEN 75', 'Digital Electronics 1 (Logic Circuits and Design)', 'Major', 4, 3, 3),
(158, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ENGS 33', 'Technopreneurship 101', 'Major', 3, 3, 0),
(159, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'DCEE 24', 'Feedback and Control Systems', 'Major', 4, 3, 3),
(160, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ECEN 95', 'Communications 3 (Transmission Media & Antenna)', 'Major', 4, 3, 3),
(161, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'CpEN 110', 'Digital Electronics 2 (Microprocessor and Microcontroller Systems', 'Major', 4, 3, 3),
(162, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'GNED 05', 'Mga Babasahin Hinggil sa Kasaysayan Ph', 'Minor', 3, 3, 0),
(163, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ECEN 196', 'Competency Appraisal 1', 'Major', 2, 1, 3),
(164, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'GNED 01', 'Art Appreciation ( Creativity in Engg Design)', 'Minor', 3, 3, 0),
(165, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'GNED 09', 'Life and Works of Rizal', 'Minor', 3, 3, 0),
(166, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'GNED 10', 'Gender and Society', 'Minor', 3, 3, 0),
(167, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ECEN 100', 'Communications 4 (Data Communications)', 'Major', 4, 3, 0),
(168, 'DCEE', 'BSECE', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'ECEN 101', 'ECE Elective 1', 'Major', 4, 3, 3),
(169, 'DCEE', 'BSECE', 0, 'New', 'labR', 1, '4th Year', '1st Semester', 0, 'ECEN 197', 'Competency Appraisal 2', 'Major', 2, 0, 3),
(170, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ECEN 200a', 'Design 1/ Capstone Project 1', 'Major', 1, 3, 0),
(171, 'DCEE', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'CPEN 140', 'CpE Competency', 'Major', 1, 1, 0),
(172, 'DCEE', 'BSECE', 0, 'New', 'labR', 1, '4th Year', '2nd Semester', 0, 'CPEN 190', 'Seminars and Fieldtrips', 'Major', 1, 0, 3),
(173, 'DCEE', 'BSECE', 0, 'New', 'labR', 1, '4th Year', '2nd Semester', 0, 'CPN 200b', 'CpE Design Project 2', 'Major', 2, 0, 6),
(174, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'GNED 09', 'Life and Works of Rizal', 'Minor', 3, 3, 0),
(175, 'CAS', 'BSECE', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'GNED 11', 'Panitikang Panlipunan', 'Minor', 3, 3, 0),
(176, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'CHEM 14', 'Chemistry for Engineers', 'Major', 4, 3, 3),
(177, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '1st Year', '1st Semester', 0, 'CPEN 21', 'Programming Logic and Design', 'Major', 2, 0, 6),
(178, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CPEN 50', 'Computer Engineering as a Discipline', 'Major', 1, 1, 0),
(179, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CVSU 101', 'Institutional Orientation', 'Minor', 1, 1, 0),
(180, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 03', 'Mathematics in the Modern World', 'Minor', 3, 3, 0),
(181, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 08', 'Understanding the Self', 'Minor', 3, 3, 0),
(182, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'Kontekstwaisadong Komunikasyon sa Filipino', 'Minor', 3, 3, 0),
(183, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'MATH 11', 'Calculus 1', 'Major', 3, 3, 0),
(184, 'CSPEAR', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'FITT 1', 'Movement Enhancement', 'Minor', 2, 2, 0),
(185, 'CAS', 'BSCpE', 0, 'New', 'lecR&labR', 0, '1st Year', '1st Semester', 0, 'NSTP 1', 'National Service Training Program I (CWTS/LTS/ROTC)', 'Minor', 3, 3, 3),
(186, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '1st Year', '2nd Semester', 0, 'CPEN 55', 'Computer Hardware Fundamentals', 'Major', 1, 0, 3),
(187, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '1st Year', '2nd Semester', 0, 'CPEN 60', 'Object Oriented Programming', 'Major', 2, 0, 6),
(188, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'DCEE 21', 'Discrete Mathematics', 'Major', 3, 3, 0),
(189, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'GNED 02', 'Ethics', 'Minor', 3, 3, 0),
(190, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'GNED 12', 'Dalumat Ng/Sa Filipino', 'Minor', 3, 3, 0),
(191, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'MATH 12', 'Caculus 2', 'Major', 3, 3, 0),
(192, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'PHYS 14', 'Physics fir Engineers', 'Major', 4, 3, 3),
(193, 'CSPEAR', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'FITT 2', 'Fitness Exercise', 'Minor', 2, 2, 0),
(194, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'NSTP 2', 'Natinal Service Training II ( CWTA/LTS/ROTC)', 'Minor', 3, 3, 0),
(195, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '2nd Year', '1st Semester', 0, 'CPEN 65', 'Data Structures and Algorithm Analysis', 'Major', 3, 0, 6),
(196, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 0, '2nd Year', '1st Semester', 0, 'EENG 50', 'Electrical Circuits 1', 'Major', 4, 3, 3),
(197, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ENGS 31', 'Engineering Economics', 'Major', 3, 3, 0),
(198, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 06', 'Science, Technology, and Society', 'Minor', 3, 3, 0),
(199, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 10', 'Gender and Society', 'Minor', 3, 3, 0),
(200, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'MATH 13', 'Differential Equations', 'Major', 3, 3, 0),
(201, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '2nd Year', '1st Semester', 0, 'DRAW 23', 'Computer Aided Drafting and Design (CADD)', 'Major', 1, 0, 3),
(202, 'CSPEAR', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'FITT 3', 'Physical Activities  toward Heath and Fitness 1', 'Minor', 2, 2, 0),
(203, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'CPEN 70', 'Software Design', 'Major', 4, 3, 3),
(204, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'DCEE 23', 'Numerical Methods and Analysis ', 'Major', 3, 2, 3),
(205, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 0, '2nd Year', '2nd Semester', 0, 'ECEN 60', 'Electronics 1 (Electronic Devices and Circuits', 'Major', 4, 3, 3),
(206, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ENGS 3', 'Engineering Management', 'Major', 2, 2, 0),
(207, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 01', 'Art Appreciation', 'Minor', 3, 3, 0),
(208, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 05', 'Purposive Communication', 'Minor', 3, 3, 0),
(209, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 07', 'The Contemporary World', 'Minor', 3, 3, 0),
(210, 'CSPEAR', 'BSCpE', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'FITT 4', 'Physical Activities toward Health and Fitness 2', 'Minor', 2, 2, 0),
(211, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '1st Semester', 0, 'CPEN 75', 'Logic Circuits and Design', 'Major', 4, 3, 3),
(212, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '1st Semester', 0, 'CPEN 80', 'Data and Digital Comunications', 'Major', 3, 3, 0),
(213, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '1st Semester', 0, 'CPEN 85', 'Fundamentals of Mixed Signals and Sensors', 'Major', 3, 2, 3),
(214, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '3nd Year', '1st Semester', 0, 'CPEN 90', 'Computer Engineering Drafting and Design', 'Major', 1, 0, 3),
(215, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '3nd Year', '1st Semester', 0, 'CPEN 101', 'Elective Course #1', 'Major', 3, 0, 3),
(216, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '1st Semester', 0, 'DCEE 24', 'Feedback and Control System', 'Major', 4, 3, 3),
(217, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '1st Semester', 0, 'DCEE 25', 'Basic Occupational Health and Safety', 'Major', 3, 3, 0),
(218, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '1st Semester', 0, 'MATH 14', 'Engineering Data Analysis', 'Major', 3, 3, 0),
(219, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '2nd Semester', 0, 'CPEN 95', 'Operating System', 'Major', 3, 2, 3),
(220, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '2nd Semester', 0, 'CPEN 100', 'Microprocessors and Microcontrolers System', 'Major', 4, 3, 3),
(221, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '3nd Year', '2nd Semester', 0, 'CPEN 105', 'Computer Networks and Security', 'Major', 4, 3, 3),
(222, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '2nd Semester', 0, 'CPEN 110', 'CpE Laws and Professional Practice', 'Major', 2, 2, 0),
(223, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '3nd Year', '2nd Semester', 0, 'CPEN 115', 'Introduction to HDL', 'Major', 1, 0, 3),
(224, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '2nd Semester', 0, 'CPEN 106', 'Elective Course #2', 'Major', 3, 3, 0),
(225, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '2nd Semester', 0, 'DCEE 26', 'Methods of Research', 'Major', 3, 3, 0),
(226, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '3nd Year', '2nd Semester', 0, 'ENGGS 35', 'Technoprenuership 101', 'Major', 3, 3, 0),
(227, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'CPEN 111', 'Elective Course #3', 'Major', 3, 3, 0),
(228, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'CPEN 120', 'Embedded Systems', 'Major', 4, 3, 3),
(229, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'CPEN 125', 'Computer Architecture and Organization', 'Major', 4, 3, 3),
(230, 'DCEE', 'BSCpE', 0, 'New', 'lecR&labR', 1, '4th Year', '1st Semester', 0, 'CPEN 135', 'Digital Signal Processing', 'Major', 4, 3, 3),
(231, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'CPEN 130', 'Emerging Technologies in CpE', 'Major', 3, 3, 0),
(232, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '4th Year', '1st Semester', 0, 'CPEN 200a', 'CpE Design Project 1', 'Major', 1, 0, 3),
(233, 'CAS', 'BSCpE', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'GNED 04', 'Mga Babasahin Hinngil sa Kasaysayan', 'Minor', 3, 3, 0),
(234, 'DCEE', 'BSCpE', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'CPEN 140', 'CpE Competency', 'Major', 1, 1, 0),
(235, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '4th Year', '2nd Semester', 0, 'CPEN 190', 'Seminars and Fieldtrips', 'Major', 1, 0, 3),
(236, 'DCEE', 'BSCpE', 0, 'New', 'labR', 1, '4th Year', '2nd Semester', 0, 'CPEN 200b', 'CpE Design Project 2', 'Major', 2, 0, 6),
(237, 'CAS', 'BSCpE', 0, 'New', 'lecR', 1, '4th Year', '2nd Semester', 0, 'GNED 09', 'Life and Works of Rizal', 'Minor', 3, 3, 0),
(238, 'CAS', 'BSCpE', 0, 'New', 'lecR', 1, '4th Year', '2nd Semester', 0, 'GNED 11', 'Panitikang Panlipunan', 'Minor', 3, 3, 0),
(239, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'AUTO 55', 'Engine Oberhauling and Performance Testing', 'Major', 3, 2, 1),
(240, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 03', 'Mathematics in Modern World ', 'Minor', 3, 3, 0),
(241, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 05 ', 'Purposive Communication', 'Minor', 3, 3, 0),
(242, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 11', 'Kontekstwalisadong Komunikasyon sa Filipino', 'Minor', 3, 3, 0),
(243, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '1st Year', '1st Semester', 0, 'DRAW 24', 'Industrial Technoogy Drawing I (CADD 2D)', 'Major', 1, 0, 1),
(244, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'INDT 21', 'Basic Occupational Health and Safety', 'Major', 2, 2, 0),
(245, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CvSU 101', 'Introduction to Industrial Technoogy', 'Minor', 1, 1, 0),
(246, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '1st Year', '1st Semester', 0, 'AUTO 50', 'Auto Electrical and Electronics System', 'Major', 5, 2, 3),
(247, 'CSPEAR', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'FITT 1', 'Movement Enhancement', 'Minor', 2, 2, 0),
(248, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'NSTP 1', 'National Service Training Program 1', 'Minor', 3, 3, 0),
(249, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'AUTO 60', 'Diesel Engine and Injection System', 'Major', 5, 2, 3),
(250, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'AUTO 65', 'Power Train Conversion System', 'Major', 3, 2, 1),
(251, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '1st Year', '2nd Semester', 0, 'DRAW 25', 'Industrial Technology I  (CADD 3D)', 'Major', 1, 0, 1),
(252, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'GNED 06', 'Science, Technoogy and Society', 'Minor', 3, 3, 0),
(253, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'GNED 12', 'Dalumat ng/sa Filipino', 'Minor', 3, 3, 0),
(254, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '1st Year', '2nd Semester', 0, 'CpEn 21', 'Programming Logic and Design', 'Major', 2, 0, 2),
(255, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '1st Year', '2nd Semester', 0, 'INDT 22', 'Digital Electronics', 'Major', 2, 1, 1),
(256, 'CSPEAR', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'FITT 2', 'Fitness Exercises', 'Minor', 2, 2, 0),
(257, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'NSTP 2', 'National Service Training Program 2', 'Minor', 3, 3, 0),
(258, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '1st Year', '2nd Semester- Mid Year', 0, 'AUTO 199a', 'Supervised Industrial Training 1', 'Major', 3, 0, 3),
(259, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'AUTO 70', 'Carburetion and Fuel Injection Calibration', 'Major', 5, 2, 3),
(260, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '1st Semester', 0, 'AUTO 75', 'Car Care Servicing Emission Control and Tune-up', 'Major', 3, 2, 1),
(261, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ENGS 35', 'Technoprenuership 101', 'Major', 3, 3, 0),
(262, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 04', 'Mga Babasahin Hinggil sa Kasaysayan', 'Minor', 3, 3, 0),
(263, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 08', 'Understanding the Self', 'Minor', 3, 3, 0),
(264, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 10', 'Gender and Society', 'Minor', 3, 3, 0),
(265, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'MATH 11', 'Differentia and Integral Calculus', 'Minor', 3, 3, 0),
(266, 'CSPEAR', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'FITT 3', 'Physical Activities Toward Health & Fitness 1', 'Minor', 2, 2, 0),
(267, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'AUTO 80', 'Automotive LPG System', 'Major', 3, 2, 1),
(268, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'AUTO 85', 'Eectronics Engine Management Control System', 'Major', 3, 2, 1),
(269, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'AUTO 90', 'Automotive Air-conditionin System ', 'Major', 2, 1, 1),
(270, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '2nd Year', '2nd Semester', 0, 'INDT 23', 'Programmable Contros ', 'Major', 2, 1, 1),
(271, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ENGS 24b', 'Mechanics of Deformable Bodies', 'Major', 3, 3, 0),
(272, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 01', 'Art Appreciation', 'Minor', 3, 3, 0),
(273, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 02', 'Ethics', 'Minor', 3, 3, 0),
(274, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 07', 'The Contemporary World', 'Minor', 3, 3, 0),
(275, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 14', 'Panitikan Filipinon', 'Minor', 3, 3, 0),
(276, 'CSPEAR', 'BSINDT', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'FITT 4', 'Physical Activities Toward Health & Fitness II', 'Minor', 2, 2, 0),
(277, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'AUTO 95', 'Diagnose and Overhau Diesel Engine', 'Major', 5, 2, 3),
(278, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'AUTO 100', 'Body Repair and Painting', 'Major', 3, 2, 1),
(279, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '3rd Year', '1st Semester', 0, 'AUTO 200a', 'AUTO Design Project 1', 'Major', 1, 0, 1),
(280, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ALAN 21', 'Foreign Language', 'Minor', 3, 3, 0),
(281, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ENGS 33', 'Environmental Science', 'Major', 3, 3, 0),
(282, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'INDT 24', 'Pneumatics and Hydraulics (P&H)', 'Major', 2, 1, 1),
(283, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'INDT 25', 'Intellectual Property Rights', 'Major', 3, 3, 0),
(284, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'INDT 26', 'Human Resource Management for Technology', 'Major', 3, 3, 0),
(285, 'DIET', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'INDT 27', 'Materials and Business Technoogy Management', 'Major', 3, 3, 0),
(286, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'AUTO 105', 'Auto Shop Service Management ', 'Major', 2, 1, 1),
(287, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'AUTO 110', 'Body Management and Under chassis ', 'Major', 3, 2, 1),
(288, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'AUTO 115', 'Driving', 'Major', 3, 1, 2),
(289, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '3rd Year', '2nd Semester', 0, 'AUTO 200b', 'AUTO Design Projext 2 ', 'Major', 2, 0, 2),
(290, 'DIET', 'BSINDT', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ENGS 29', 'Appied Thermodynamics', 'Major', 2, 1, 1),
(291, 'CAS', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'GNED 09', 'Life and Works of Rizal', 'Minor', 3, 0, 0),
(292, 'DIT', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'INDT 28', 'Industria Organization and Management Practices', 'Major', 3, 0, 0),
(293, 'DIT', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'INDT 29', 'Quality Control', 'Major', 3, 0, 0),
(294, 'DIT', 'BSINDT', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'INDT 30', 'Production Technology Management', 'Major', 3, 0, 0),
(295, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '4th Year', '2nd Semester', 0, 'AUTO 199c', 'Supervised Industrial Training 3 ', 'Major', 8, 0, 8),
(296, 'DIET', 'BSINDT', 0, 'New', 'labR', 1, '4th Year', '1st Semester', 0, 'AUTO 199b', 'Supervised Industrial Training 2 ', 'Major', 8, 0, 8),
(297, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 01', 'Art Appreciation', 'Minor', 3, 3, 0),
(298, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'GNED 08', 'Understanding the Self', 'Minor', 3, 3, 0),
(299, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'MATH 9', 'Solid Mesuration', 'Minor', 2, 2, 0),
(300, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'ARCH 22', 'Graphics I', 'Major', 2, 2, 0),
(301, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'ARCH 50', 'Architecture Design  I', 'Major', 2, 2, 0),
(302, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'ARCH 55', 'Theory of Architecture I', 'Major', 2, 2, 0),
(303, 'CSPEAR', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'FITT 1', 'Movement Enhancement', 'Minor', 2, 2, 0),
(304, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'NSTP 1', 'National Training Program', 'Minor', 3, 3, 0),
(305, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '1st Semester', 0, 'CvSU 101', 'Institutional Orientation', 'Minor', 1, 1, 0),
(306, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'GNED 11', 'Kontekstwalisadong Komunikasyon sa Filipino', 'Minor', 3, 3, 0),
(307, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'MATH 8a', 'Differential & Integral Calculus', 'Minor', 3, 3, 0),
(308, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ARCH 23', 'Graphics II', 'Major', 3, 3, 0),
(309, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ARCH 24', 'Visual Techniques II', 'Major', 2, 2, 0),
(310, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ARCH 60', 'Architecture Design II', 'Major', 2, 2, 0),
(311, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ARCH 65', ' Theory of Architecture II', 'Major', 2, 2, 0),
(312, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'ARCH 70', 'Architectural Interiors', 'Major', 2, 2, 0),
(313, 'CSPEAR', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd Semester', 0, 'FITT 2', 'Fitness Exercise', 'Minor', 2, 2, 0),
(314, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '1st Year', '2nd  Semester', 0, 'NSTP 2', 'National  Service Training Program', 'Minor', 3, 3, 0),
(315, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 03', 'Mathematics in the Modern World', 'Minor', 3, 3, 0),
(316, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 14', 'Panitikang Panlipunan', 'Minor', 3, 3, 0),
(317, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'GNED 02', 'Ethics', 'Minor', 3, 3, 0),
(318, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ARCH 25', 'Visual Techniques III', 'Major', 2, 2, 0),
(319, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ARCH 75', 'Architecture Design III', 'Major', 3, 3, 0),
(320, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ARCH 80', 'Building Technology I', 'Major', 3, 3, 0),
(321, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ARCH 85', 'History of Architecture I', 'Major', 2, 2, 0),
(322, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'ARCH 90', 'Building Utilities I', 'Major', 3, 3, 0),
(323, 'CSPEAR', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '1st Semester', 0, 'FITT 3', 'Physical Activities Toward Heath & Fitness 1', 'Minor', 2, 2, 0),
(324, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 04', 'Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'Minor', 3, 3, 0),
(325, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'GNED 05', 'Purposive Communication', 'Minor', 3, 3, 0),
(326, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ENGS 21', 'Statics of Rigid Bodies', 'Major', 3, 3, 0),
(327, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ARCH 95', 'Architectural Design IV', 'Major', 3, 3, 0),
(328, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ARCH 100', 'Buiding Utilities II', 'Major', 3, 3, 0),
(329, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ARCH 105', 'History of Architecture II', 'Major', 2, 2, 0),
(330, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'ARCH 110', 'Tropical Design ', 'Major', 2, 2, 0),
(331, 'CSPEAR', 'BSArch', 0, 'New', 'lecR', 0, '2nd Year', '2nd Semester', 0, 'FITT 4', 'Physical Activities Toward Heath & Fitness 2', 'Minor', 2, 2, 0),
(332, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'GNED 06', 'Science, Technology & Society', 'Minor', 3, 3, 0),
(333, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'GNED 09', 'Life and Works of Rizal', 'Minor', 3, 3, 0),
(334, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ENGS 24b', 'Mechanics of Deformable Bodies', 'Major', 3, 3, 0),
(335, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ENGS 25', 'Surveying', 'Major', 3, 3, 0),
(336, 'DCEA', 'BSArch', 0, 'New', 'lecR&labR', 1, '3rd Year', '1st Semester', 0, 'ARCH 115', 'Architecture Design V', 'Major', 4, 3, 1),
(337, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ARCH 120', 'Building Technology II', 'Major', 3, 3, 0),
(338, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ARCH 125', 'History of Architecture III', 'Major', 2, 2, 0),
(339, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '1st Semester', 0, 'ARCH 130', 'CADD 1', 'Major', 2, 2, 0),
(340, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'GNED 07', 'The Contemporary World', 'Minor', 3, 3, 0),
(341, 'CAS', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'GNED 10', 'Gender & Society', 'Minor', 3, 3, 0),
(342, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ARCH 26', 'Theory of Structures', 'Major', 3, 3, 0),
(343, 'DCEA', 'BSArch', 0, 'New', 'lecR&labR', 1, '3rd Year', '2nd Semester', 0, 'ARCH 135', 'Architecture Design VI', 'Major', 4, 3, 1),
(344, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ARCH 140', 'Building Technology III', 'Major', 3, 3, 0),
(345, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ARCH 145', 'History of Architecture IV', 'Major', 2, 2, 0),
(346, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ARCH 150', 'Professional Practice I', 'Major', 3, 3, 0),
(347, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '3rd Year', '2nd Semester', 0, 'ARCH 155', 'CADD 2', 'Major', 2, 2, 0),
(348, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 27', 'Steel & Timber Design', 'Major', 3, 3, 0),
(349, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 160', 'Architecture Design VII', 'Major', 5, 3, 2),
(350, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 165', 'Building Utilities III', 'Major', 3, 3, 0),
(351, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 170', 'Planning I', 'Major', 3, 3, 0),
(352, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 175', 'Professional Practice II', 'Major', 3, 3, 0),
(353, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '1st Semester', 0, 'ARCH 180', 'Research Methods for Architecture', 'Major', 3, 3, 0),
(354, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'ARCH 28', 'Architecture Structures', 'Major', 3, 3, 0),
(355, 'DCEA', 'BSArch', 0, 'New', 'lecR&labR', 0, '4th Year', '2nd Semester', 0, 'ARCH 185', 'Architecture Design VIII', 'Major', 5, 3, 2),
(356, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'ARCH 190', 'Building Technology IV', 'Major', 3, 3, 0),
(357, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'ARCH 195', 'Planning III', 'Major', 3, 3, 0),
(358, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'ARCH 200', 'Professional Practice III', 'Major', 3, 3, 0),
(359, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '4th Year', '2nd Semester', 0, 'ARCH 101', 'Specialization I (Project  Management)', 'Major', 3, 3, 0),
(360, 'DCEA', 'BSArch', 0, 'New', 'lecR&labR', 1, '5th Year', '1st Semester', 0, 'ARCH 205', 'Architecture Design IX', 'Major', 5, 3, 2),
(361, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '1st Semester', 0, 'ARCH 210', 'Building Technology V', 'Major', 3, 3, 0),
(362, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '1st Semester', 0, 'ARCH 215', 'Planning III', 'Major', 3, 3, 0),
(363, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '1st Semester', 0, 'ARCH 220', 'Business Management & Application for Architecture 1', 'Major', 3, 3, 0),
(364, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '1st Semester', 0, 'ARCH 106', 'Specialization II (Construction Management)', 'Major', 3, 3, 0),
(365, 'DCEA', 'BSArch', 0, 'New', 'lecR&labR', 1, '5th Year', '2nd Semester', 0, 'ARCH 225', 'Architectural Desin X-Thesis', 'Major', 5, 3, 2),
(366, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '2nd Semester', 0, 'ARCH 230', 'Housing', 'Major', 2, 2, 0),
(367, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '2nd Semester', 0, 'ARCH 235', 'Business Management & Application for Architecture 1', 'Major', 3, 3, 0),
(368, 'DCEA', 'BSArch', 0, 'New', 'lecR', 0, '5th Year', '2nd Semester', 0, 'ARCH 111', 'Specialization III (Facilities Management)', 'Major', 3, 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_department`
--

CREATE TABLE `tbl_department` (
  `college_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `program_units` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_department`
--

INSERT INTO `tbl_department` (`college_code`, `dept_code`, `dept_name`, `program_units`) VALUES
('CEIT', 'DAFE', 'Department of Agriculture and Food Engineering', 'ABE'),
('CEIT', 'DCEA', 'Department of Civil Engineering ', 'CE,ARCH'),
('CEIT', 'DCEE', 'Department of Computer, Electronics, and Electrical Engineering', 'CpE,ECE,EE'),
('CEIT', 'DIET', 'Department of Industrial Engineering and Technology', 'INDT'),
('CEIT', 'DIT', 'Department of Information Technology', 'IT,CS,IS');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_messages`
--

CREATE TABLE `tbl_messages` (
  `id` int(10) NOT NULL,
  `sender_email` varchar(100) NOT NULL,
  `receiver_email` varchar(100) NOT NULL,
  `message` varchar(200) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('new','read') DEFAULT 'new',
  `file_url` varchar(255) DEFAULT NULL,
  `ay_code` int(50) NOT NULL,
  `semester` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notifications`
--

CREATE TABLE `tbl_notifications` (
  `id` int(100) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `section_sched_code` varchar(200) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_code` varchar(20) NOT NULL,
  `receiver_email` varchar(50) DEFAULT NULL,
  `date_sent` datetime NOT NULL,
  `sender_email` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_no_students`
--

CREATE TABLE `tbl_no_students` (
  `id` int(11) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `course_code` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `no_students` int(10) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `ay_code` int(50) NOT NULL,
  `semester` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pcontact_counter`
--

CREATE TABLE `tbl_pcontact_counter` (
  `id` int(10) NOT NULL,
  `contact_code` varchar(100) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `prof_sched_code` varchar(255) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `consultation_hrs` int(100) NOT NULL,
  `current_consultation_hrs` int(11) NOT NULL,
  `research_hrs` int(100) NOT NULL,
  `extension_hrs` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pcontact_schedstatus`
--

CREATE TABLE `tbl_pcontact_schedstatus` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `prof_sched_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` int(100) NOT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pcontact_sched_dit_2425`
--

CREATE TABLE `tbl_pcontact_sched_dit_2425` (
  `sec_sched_id` int(11) NOT NULL,
  `prof_sched_code` varchar(200) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `day` varchar(50) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `consultation_hrs_type` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prof`
--

CREATE TABLE `tbl_prof` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `prof_type` varchar(100) NOT NULL,
  `reg_adviser` int(11) NOT NULL,
  `academic_rank` varchar(100) NOT NULL,
  `employ_status` int(10) NOT NULL,
  `prof_name` varchar(100) NOT NULL,
  `prof_unit` varchar(100) NOT NULL,
  `acc_status` int(1) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `ay_code` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prof`
--

INSERT INTO `tbl_prof` (`id`, `dept_code`, `prof_code`, `prof_type`, `reg_adviser`, `academic_rank`, `employ_status`, `prof_name`, `prof_unit`, `acc_status`, `semester`, `ay_code`) VALUES
(2, 'DIT', 'IT 1', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(3, 'DIT', 'IT 2', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(5, 'DIT', 'CS 1', '', 0, '', 0, '', 'CS', 1, '1st Semester', 2425),
(6, 'DIT', 'IT 3', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(7, 'DIT', 'IT 4', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(9, 'DIT', 'IT 5', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(51, 'DCEE', 'ECE 1', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(53, 'DIET', 'INDT 1', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425),
(54, 'DIET', 'INDT 2', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425),
(55, 'DIET', 'INDT 3', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425),
(56, 'DIET', 'INDT 4', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425),
(57, 'DIT', 'IT 6', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(58, 'DCEE', 'ECE 2', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(59, 'DCEE', 'ECE 3', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(60, 'DCEE', 'ECE 4', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(61, 'DCEE', 'ECE 5', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(62, 'DCEE', 'ECE 6', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(63, 'DIT', 'IT 7', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(64, 'DIT', 'IT 8', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(65, 'DCEE', 'ECE 7', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(66, 'DIT', 'IT 9', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(67, 'DIT', 'IT 10', '', 0, '', 0, '', 'IT', 1, '1st Semester', 2425),
(68, 'DCEE', 'ECE 8', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(69, 'DCEE', 'ECE 9', '', 0, '', 0, '', 'ECE', 1, '1st Semester', 2425),
(71, 'DAFE', 'ABE 1', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(72, 'DAFE', 'ABE 2', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(73, 'DAFE', 'ABE 3', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(74, 'DAFE', 'ABE 4', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(75, 'DAFE', 'ABE 5', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(76, 'DAFE', 'ABE 6', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(77, 'DAFE', 'ABE 7', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(78, 'DAFE', 'ABE 8', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(79, 'DAFE', 'ABE 9', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(80, 'DAFE', 'ABE 10', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(81, 'DCEA', 'CE 1', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(82, 'DCEA', 'CE 2', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(83, 'DCEA', 'CE 3', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(84, 'DCEA', 'CE 4', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(85, 'DCEA', 'CE 5', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(86, 'DCEA', 'CE 6', '', 0, '', 0, '', 'CE', 1, '1st Semester', 2425),
(87, 'DCEA', 'ARCH 1', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(88, 'DCEA', 'ARCH 2', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(89, 'DCEA', 'ARCH 3', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(90, 'DCEA', 'ARCH 4', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(91, 'DAFE', 'ABE 11', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(92, 'DAFE', 'ABE 12', '', 0, '', 0, '', 'ABE', 1, '1st Semester', 2425),
(94, 'DCEA', 'ARCH 5', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(95, 'DCEA', 'ARCH 6', '', 0, '', 0, '', 'ARCH', 1, '1st Semester', 2425),
(100, 'DIET', 'INDT 5', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425),
(101, 'DIET', 'INDT 6', '', 0, '', 0, '', 'INDT', 1, '1st Semester', 2425);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prof_acc`
--

CREATE TABLE `tbl_prof_acc` (
  `id` int(11) NOT NULL,
  `college_code` varchar(10) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `status_type` varchar(100) NOT NULL,
  `default_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(100) NOT NULL,
  `suffix` varchar(100) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `prof_unit` varchar(100) NOT NULL,
  `prof_type` varchar(100) NOT NULL,
  `academic_rank` varchar(100) NOT NULL,
  `part_time` int(1) NOT NULL,
  `user_type` varchar(100) NOT NULL,
  `reg_adviser` int(1) NOT NULL,
  `cvsu_email` varchar(100) NOT NULL,
  `password` varchar(200) NOT NULL,
  `status` varchar(100) NOT NULL,
  `acc_status` int(1) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `ay_code` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prof_acc`
--

INSERT INTO `tbl_prof_acc` (`id`, `college_code`, `dept_code`, `status_type`, `default_code`, `prof_code`, `last_name`, `first_name`, `middle_initial`, `suffix`, `designation`, `prof_unit`, `prof_type`, `academic_rank`, `part_time`, `user_type`, `reg_adviser`, `cvsu_email`, `password`, `status`, `acc_status`, `semester`, `ay_code`) VALUES
(2, 'CEIT', 'DIT', 'Online', 'IT 1', '', 'Bihis', 'Aiza', 'E', '', '', 'IT', '', '', 0, 'Department Secretary', 1, 'deptsec.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(3, 'CEIT', 'DIT', 'Offline', 'IT 2', '', 'Perey', 'Gladys', 'G', '', '', 'IT', '', '', 0, 'Professor', 1, 'professor9.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(5, 'CEIT', 'DIT', 'Offline', 'CS 1', '', 'Rocillo', 'Stephen', 'A', '', '', 'CS', '', '', 0, 'Professor', 0, 'professor8.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(6, 'CEIT', 'DIT', 'Offline', 'IT 3', '', 'Benedicto', 'Gerami', 'L', '', '', 'IT', '', '', 0, 'Professor', 0, 'professor7.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(7, 'CEIT', 'DIT', 'Offline', 'IT 4', '', 'Uncad', 'Jayson', 'C', '', '', 'IT', '', '', 1, 'Professor', 1, 'professor6.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(9, 'CEIT', 'DIT', 'Offline', 'IT 5', '', 'Cruzate', 'Regina ', 'F', '', '', 'IT', '', '', 1, 'Professor', 0, 'professor5.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(51, 'CEIT', 'DCEE', 'Offline', 'ECE 1', '', 'Sarmiento', 'Bienvenido', 'C', 'Jr', '', 'ECE', '', '', 0, 'CCL Head', 0, 'ccl.head@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(53, 'CEIT', 'DIET', 'Offline', 'INDT 1', '', 'Llano', 'Alexander', 'L', '', '', 'INDT', '', '', 0, 'Professor', 1, 'professor1.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(54, 'CEIT', 'DIET', 'Offline', 'INDT 2', '', 'Escover', 'Miguel', 'A', '', '', 'INDT', '', '', 0, 'Professor', 0, 'professor2.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(55, 'CEIT', 'DIET', 'Offline', 'INDT 3', '', 'Estrella', 'Robin', 'A', '', '', 'INDT', '', '', 1, 'Professor', 0, 'professor3.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(56, 'CEIT', 'DIET', 'Offline', 'INDT 4', '', 'Hintay', 'Aarol', 'A', '', '', 'INDT', '', '', 1, 'Professor', 0, 'professor5.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(57, 'CEIT', 'DIT', 'Offline', 'IT 6', '', 'Carandang', 'Charolette', 'C', '', '', 'IT', '', '', 0, 'Department Chairperson', 1, 'deptchair.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(58, 'CEIT', 'DCEE', 'Offline', 'ECE 2', '', 'Reyes', 'Anna', 'C', '', '', 'ECE', '', '', 0, 'Professor', 1, 'professor1.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(59, 'CEIT', 'DCEE', 'Offline', 'ECE 3', '', 'Dela Cruz', 'Carl', 'L', '', '', 'ECE', '', '', 0, 'Professor', 1, 'professor2.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(60, 'CEIT', 'DCEE', 'Offline', 'ECE 4', '', 'Mojica', 'Mark', 'L', '', '', 'ECE', '', '', 0, 'Professor', 0, 'professor3.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(61, 'CEIT', 'DCEE', 'Offline', 'ECE 5', '', 'Villaobos', 'Anna Marie', 'A', '', '', 'ECE', '', '', 0, 'Professor', 0, 'professor4.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(62, 'CEIT', 'DCEE', 'Offline', 'ECE 6', '', 'Pascua', 'John Mark', 'C', '', '', 'ECE', '', '', 0, 'Professor', 0, 'professor5.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(63, 'CEIT', 'DIT', 'Offline', 'IT 7', '', 'Pascua', 'John Mark', 'C', '', '', 'IT', '', '', 0, 'Professor', 0, 'professor1.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(64, 'CEIT', 'DIT', 'Offline', 'IT 8', '', 'Aron', 'Miguel', 'C', '', '', 'IT', '', '', 0, 'Professor', 0, 'professor2.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(65, 'CEIT', 'DCEE', 'Offline', 'ECE 7', '', 'Javier', 'Julie', 'M', '', '', 'ECE', '', '', 0, 'Professor', 0, 'professor6.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(66, 'CEIT', 'DIT', 'Offline', 'IT 9', '', 'Salazar', 'Jefferson', 'A', '', '', 'IT', '', '', 0, 'Professor', 0, 'professor3.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(67, 'CEIT', 'DIT', 'Offline', 'IT 10', '', 'Poniente', 'Aaron', 'B', '', '', 'IT', '', '', 0, 'Professor', 0, 'professor4.dit@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(68, 'CEIT', 'DCEE', 'Offline', 'ECE 8', '', 'Manuel', 'John Neil', 'A', '', '', 'ECE', '', '', 0, 'Department Chairperson', 1, 'deptchair.dcee@cvsu.edu ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(69, 'CEIT', 'DCEE', 'Offline', 'ECE 9', '', 'Garcia', 'Ericka', 'A', '', '', 'ECE', '', '', 0, 'Department Secretary', 0, 'deptsec.dcee@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(71, 'CEIT', 'DAFE', 'Offline', 'ABE 1', '', 'De Leon', 'Juan Miguel', 'A', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor1.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(72, 'CEIT', 'DAFE', 'Offline', 'ABE 2', '', 'Garcia', 'Emmanuel', 'D', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor2.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(73, 'CEIT', 'DAFE', 'Offline', 'ABE 3', '', 'Reyes', 'Marivic', 'A', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor3.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(74, 'CEIT', 'DAFE', 'Offline', 'ABE 4', '', 'Delos Santos', 'Rafael', 'B', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor4.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(75, 'CEIT', 'DAFE', 'Offline', 'ABE 5', '', 'Lopez', 'Cristina', 'L', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor5.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(76, 'CEIT', 'DAFE', 'Offline', 'ABE 6', '', 'Mendoza', 'John', 'R', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor6.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(77, 'CEIT', 'DAFE', 'Offline', 'ABE 7', '', 'Villanueva', 'Sophia', 'M', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor7.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(78, 'CEIT', 'DAFE', 'Offline', 'ABE 8', '', 'Fernandez', 'Michael', 'P', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor8.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(79, 'CEIT', 'DAFE', 'Offline', 'ABE 9', '', 'Cruz', 'Andrea', 'Q', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor9.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(80, 'CEIT', 'DAFE', 'Offline', 'ABE 10', '', 'Torres', 'Miguel', 'E', '', '', 'ABE', '', '', 0, 'Professor', 0, 'professor10.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(81, 'CEIT', 'DCEA', 'Offline', 'CE 1', '', 'Aguilar', 'Joseph', 'A', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor1.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(82, 'CEIT', 'DCEA', 'Offline', 'CE 2', '', 'Bautista', 'Clarisse', 'B', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor2.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(83, 'CEIT', 'DCEA', 'Offline', 'CE 3', '', 'Castro', 'Ivan', 'C', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor3.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(84, 'CEIT', 'DCEA', 'Offline', 'CE 4', '', 'Diaz', 'Emily', 'D', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor4.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(85, 'CEIT', 'DCEA', 'Offline', 'CE 5', '', 'Evangelista', 'Sophia', 'E', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor5.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(86, 'CEIT', 'DCEA', 'Offline', 'CE 6', '', 'Fernandez', 'Mark', 'F', '', '', 'CE', '', '', 0, 'Professor', 1, 'professor6.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(87, 'CEIT', 'DCEA', 'Offline', 'ARCH 1', '', 'Gomez', 'Isabella', 'G', '', '', 'ARCH', '', '', 0, 'Professor', 1, 'professor7.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(88, 'CEIT', 'DCEA', 'Offline', 'ARCH 2', '', 'Hernandez', 'Miguel', 'H', '', '', 'ARCH', '', '', 0, 'Professor', 1, 'professor8.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(89, 'CEIT', 'DCEA', 'Offline', 'ARCH 3', '', 'Ilagan', 'Marie', 'I', '', '', 'ARCH', '', '', 0, 'Professor', 1, 'professor9.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(90, 'CEIT', 'DCEA', 'Offline', 'ARCH 4', '', 'Javier', 'Noah', 'J', '', '', 'ARCH', '', '', 0, 'Professor', 1, 'professor10.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(91, 'CEIT', 'DAFE', 'Offline', 'ABE 11', '', 'Ramirez', 'Nicole', 'C', '', '', 'ABE', '', '', 0, 'Department Chairperson', 1, 'deptchair.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(92, 'CEIT', 'DAFE', 'Offline', 'ABE 12', '', 'Lopez', 'Maria', 'A', '', '', 'ABE', '', '', 0, 'Department Secretary', 1, 'deptsec.dafe@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(94, 'CEIT', 'DCEA', 'Offline', 'ARCH 5', '', 'Villanueva', 'Andrea', 'R', '', '', 'ARCH', '', '', 0, 'Department Secretary', 1, 'deptsec.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(95, 'CEIT', 'DCEA', 'Offline', 'ARCH 6', '', 'Del Rosario', 'Michael', 'T', '', '', 'ARCH', '', '', 0, 'Department Chairperson', 1, 'deptchair.dcea@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(96, 'CAS', 'CAS', 'Offline', '', '', 'Ramos', 'Marie', 'R', '', '', '', '', '', 0, 'Department Secretary', 0, 'cas.sec@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(97, 'CSPEAR', 'CSPEAR', 'Offline', '', '', 'Reyes', 'Josephine', 'R', '', '', '', '', '', 0, 'Department Secretary', 0, 'cspear.sec@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(98, 'CEMDS', 'CEMDS', 'Offline', '', '', 'Robles', 'Jhoana', 'R', '', '', '', '', '', 0, 'Department Secretary', 0, 'cemds.sec@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(100, 'CEIT', 'DIET', 'Online', 'INDT 5', '', 'Flores', 'Honey', 'D', '', '', 'INDT', '', '', 1, 'Department Secretary', 0, 'deptsec.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425'),
(101, 'CEIT', 'DIET', 'Offline', 'INDT 6', '', 'Gomez', 'Danica', 'A', '', '', 'INDT', '', '', 1, 'Department Chairperson', 0, 'deptchair.diet@cvsu.edu.ph', '$2y$10$3p6XKaFyUwd2qJKnxJ9rEOvftbsX7.aZXmFCDRMiU0gbZH3nztnoS', 'approve', 1, '1st Semester', '2425');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prof_schedstatus`
--

CREATE TABLE `tbl_prof_schedstatus` (
  `id` int(100) NOT NULL,
  `prof_sched_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_program`
--

CREATE TABLE `tbl_program` (
  `id` int(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `program_name` varchar(200) NOT NULL,
  `num_year` int(10) NOT NULL,
  `curriculum` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_program`
--

INSERT INTO `tbl_program` (`id`, `college_code`, `dept_code`, `program_code`, `program_name`, `num_year`, `curriculum`) VALUES
(10, 'CEIT', 'DIT', 'BSCS', 'Bachelor of Science in Computer Science', 4, 'New'),
(12, 'CEIT', 'DIT', 'BSCS', 'Bachelor of Science in Computer Science', 4, 'Old'),
(13, 'CEIT', 'DIT', 'BSIT', 'Bachelor of Science in Information Technology', 4, 'New'),
(14, 'CEIT', 'DIT', 'BSIT', 'Bachelor of Science in Information Technology', 5, 'Old'),
(17, 'CEIT', 'DCEE', 'BSEE', 'Bachelor of Science in Electrical Engineering', 4, 'New'),
(18, 'CEIT', 'DCEE', 'BSCpE', 'Bachelor of Science in Computer Engineering', 4, 'New'),
(19, 'CEIT', 'DCEE', 'BSECE', 'Bachelor of Science in Electronic Engineering', 4, 'New'),
(20, 'CEIT', 'DAFE', 'BSABE', 'BS Agricultural and Biosystems Engineering ', 4, 'New'),
(21, 'CEIT', 'DCEA', 'BSCE', 'Bachelor of Science in Civil Engineering', 5, 'New'),
(22, 'CEIT', 'DCEA', 'BSARCH', 'Bachelor of Science in Architecture', 5, 'New'),
(23, 'CEIT', 'DIET', 'BSINDT', 'Bachelor of Science in Industrial Technology', 4, 'New');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_psched`
--

CREATE TABLE `tbl_psched` (
  `prof_sched_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `ay_code` int(50) NOT NULL,
  `semester` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_psched_counter`
--

CREATE TABLE `tbl_psched_counter` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `prof_sched_code` varchar(100) NOT NULL,
  `semester` varchar(250) NOT NULL,
  `teaching_hrs` float NOT NULL,
  `prep_hrs` float NOT NULL,
  `consultation_hrs` float NOT NULL,
  `ay_code` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_psched_dit_2425`
--

CREATE TABLE `tbl_psched_dit_2425` (
  `id` int(11) NOT NULL,
  `sec_sched_id` int(11) NOT NULL,
  `prof_sched_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `day` varchar(255) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `section_code` varchar(255) NOT NULL,
  `room_code` varchar(255) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `ay_code` varchar(255) NOT NULL,
  `class_type` varchar(255) NOT NULL,
  `cell_color` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_registration_adviser`
--

CREATE TABLE `tbl_registration_adviser` (
  `id` int(11) NOT NULL,
  `dept_code` varchar(10) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `ay_code_assign` int(10) NOT NULL,
  `current_ay_code` varchar(100) NOT NULL,
  `reg_adviser` varchar(100) NOT NULL,
  `num_year` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room`
--

CREATE TABLE `tbl_room` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `room_code` varchar(100) NOT NULL,
  `room_type` varchar(100) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `room_in_charge` varchar(255) DEFAULT NULL,
  `status` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_room`
--

INSERT INTO `tbl_room` (`id`, `dept_code`, `college_code`, `room_code`, `room_type`, `room_name`, `room_in_charge`, `status`) VALUES
(1, 'DIT', 'CEIT', 'CCL 101', 'Computer Laboratory', 'Network And Simulation Lab', 'Mr. Micheal A. Espartero', 'Available'),
(2, 'DIT', 'CEIT', 'CCL 102', 'Computer Laboratory', 'Office Productivity Lab', 'Mr. Micheal A. Espartero', 'Available'),
(3, 'DIT', 'CEIT', 'CCL 201', 'Computer Laboratory', 'Internet Lab 1', 'Mr. Mark Clifford Alano', 'Available'),
(4, 'DIT', 'CEIT', 'CCL 202', 'Computer Laboratory', 'Multimedia And Computer Graphics Lab', 'Mr. Mark Clifford Alano', 'Available'),
(5, 'DIT', 'CEIT', 'CCL 203', 'Computer Laboratory', 'Computer Programming Lab 1', 'Mr. Mark Clifford Alano', 'Available'),
(6, 'DIT', 'CEIT', 'CCL 208', 'Computer Laboratory', 'Computer Programming Lab 2', 'Mr. Mark Clifford Alano', 'Available'),
(7, 'DIT', 'CEIT', 'CCL 204', 'Computer Laboratory', 'Computer Programming Lab 3', 'Mr. Mark Clifford Alano', 'Available'),
(8, 'DIT', 'CEIT', 'CCL 301', 'Computer Laboratory', 'Internet Lab 1', 'Mr. Mark Kevin Medina', 'Available'),
(9, 'DIT', 'CEIT', 'CCL 302 ', 'Computer Laboratory', 'Computer Programming Lab 4', 'Mr. Mark Kevin Medina', 'Available'),
(10, 'DIT', 'CEIT', 'CCL 303', 'Computer Laboratory', 'Application Development Lab', 'Mr. Mark Kevin Medina', 'Available'),
(11, 'DIT', 'CEIT', 'CCL 304', 'Computer Laboratory', 'Cadd Lab 1', 'Mr. Mark Kevin Medina', 'Available'),
(12, 'DIT', 'CEIT', 'CCL 305', 'Computer Laboratory', 'Computer Organization And Architecture Lab', 'Mr. Mark Kevin Medina', 'Available'),
(13, 'DIT', 'CEIT', 'CCL 306 ', 'Computer Laboratory', 'Database Systems Lab', 'Mr. Mark Kevin Medina', 'Available'),
(14, 'DIT', 'CEIT', 'CCL 401', 'Computer Laboratory', 'Cadd Lab 2', 'Mr. Mark Clifford Alano', 'Available'),
(15, 'DIT', 'CEIT', 'CCL 402', 'Computer Laboratory', 'Multimedia And Computer Graphics Lab', 'Mr. Mark Clifford Alano', 'Available'),
(16, 'DIT', 'CEIT', 'ITC 201', 'Lecture', 'N/A', 'Carandang', 'Available'),
(17, 'DIT', 'CEIT', 'ITC 401', 'Lecture', 'N/A', 'Rocillo', 'Available'),
(18, 'DIT', 'CEIT', 'ITC 402', 'Lecture', 'N/A', 'Benedicto', 'Available'),
(19, 'DIT', 'CEIT', 'ITC 403', 'Lecture', 'N/A', 'Coronado', 'Available'),
(20, 'DIT', 'CEIT', 'ITC 404', 'Lecture', 'N/A', 'Perena', 'Available'),
(21, 'DIT', 'CEIT', 'ITC 408', 'Lecture', 'N/A', 'Malicsi', 'Available'),
(22, 'DIET', 'CEIT', 'ITC 501', 'Lecture', 'N/A', 'Almares', 'Available'),
(23, 'DIET', 'CEIT', 'ITC 502', 'Lecture', 'N/A', 'Cerezo', 'Available'),
(24, 'DIET', 'CEIT', 'ITC 503', 'Lecture', 'N/A', 'Sotto', 'Available'),
(29, 'DIET', 'CEIT', 'ITC 505', 'Lecture', 'N/A', 'Nicole,Abutin,Gerami', 'Available'),
(30, 'DIT', 'CEIT', 'ITC 505', 'Lecture', 'Lec Room', 'Barredo', 'Not Available'),
(31, 'DIT', 'CEIT', 'CCL 402', 'Computer Laboratory', 'Multimedia And Computer Graphics Lab', 'Mr. Mark Clifford Alano', 'Not Available'),
(34, 'DCEE', 'CEIT', 'ES 101', 'Lecture', 'N/A', 'Garcia', 'Available'),
(35, 'DCEE', 'CEIT', 'ES 102', 'Lecture', 'N/A', 'Garcia', 'Available'),
(36, 'DCEE', 'CEIT', 'ES 103', 'Lecture', 'N/A', 'Garcia', 'Available'),
(37, 'DCEE', 'CEIT', 'ES 104', 'Lecture', 'N/A', 'Garcia', 'Available'),
(38, 'DCEE', 'CEIT', 'ES 201', 'Lecture', 'N/A', 'Garcia', 'Available'),
(39, 'DCEE', 'CEIT', 'ES 202', 'Lecture', 'N/A', 'Garcia', 'Available'),
(40, 'DCEE', 'CEIT', 'ES 203', 'Lecture', 'N/A', 'Garcia', 'Available'),
(41, 'DCEE', 'CEIT', 'ES 204', 'Lecture', 'N/A', 'Garcia', 'Available'),
(42, 'DCEE', 'CEIT', 'ELEX LAB 1', 'Laboratory', 'N/A', 'Garcia', 'Available'),
(43, 'DCEE', 'CEIT', 'ELEX LAB 2', 'Laboratory', 'N/A', 'Garcia', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roomsched_ceit_2425`
--

CREATE TABLE `tbl_roomsched_ceit_2425` (
  `id` int(11) NOT NULL,
  `sec_sched_id` int(100) DEFAULT NULL,
  `room_sched_code` varchar(255) NOT NULL,
  `room_code` varchar(255) NOT NULL,
  `room_in_charge` varchar(255) NOT NULL,
  `day` varchar(255) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `section_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `prof_name` varchar(255) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `room_type` varchar(255) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` varchar(255) NOT NULL,
  `class_type` varchar(255) NOT NULL,
  `cell_color` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roomsched_dit_2425`
--

CREATE TABLE `tbl_roomsched_dit_2425` (
  `id` int(11) NOT NULL,
  `sec_sched_id` int(100) DEFAULT NULL,
  `room_sched_code` varchar(255) NOT NULL,
  `room_code` varchar(255) NOT NULL,
  `room_in_charge` varchar(255) NOT NULL,
  `day` varchar(255) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `section_code` varchar(255) NOT NULL,
  `prof_code` varchar(255) NOT NULL,
  `prof_name` varchar(255) NOT NULL,
  `dept_code` varchar(255) NOT NULL,
  `room_type` varchar(255) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `ay_code` varchar(255) NOT NULL,
  `class_type` varchar(255) NOT NULL,
  `cell_color` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_schedstatus`
--

CREATE TABLE `tbl_room_schedstatus` (
  `id` int(100) NOT NULL,
  `room_sched_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rsched`
--

CREATE TABLE `tbl_rsched` (
  `room_sched_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `room_code` varchar(100) NOT NULL,
  `room_type` varchar(100) NOT NULL,
  `ay_code` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_schedstatus`
--

CREATE TABLE `tbl_schedstatus` (
  `id` int(100) NOT NULL,
  `section_sched_code` varchar(100) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL,
  `cell_color` varchar(100) NOT NULL,
  `petition` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_secschedlist`
--

CREATE TABLE `tbl_secschedlist` (
  `id` int(100) NOT NULL,
  `section_sched_code` varchar(200) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `ay_code` int(50) NOT NULL,
  `petition` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_secsched_dit_2425`
--

CREATE TABLE `tbl_secsched_dit_2425` (
  `sec_sched_id` int(11) NOT NULL,
  `section_sched_code` varchar(200) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `day` varchar(50) NOT NULL,
  `curriculum` varchar(100) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `course_code` varchar(100) NOT NULL,
  `room_code` varchar(100) NOT NULL,
  `prof_code` varchar(100) NOT NULL,
  `prof_name` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `cell_color` varchar(100) NOT NULL,
  `shared_sched` varchar(100) NOT NULL,
  `shared_to` varchar(100) NOT NULL,
  `class_type` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_section`
--

CREATE TABLE `tbl_section` (
  `id` int(100) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `section_no` varchar(100) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `curriculum` varchar(200) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `petition` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_shared_sched`
--

CREATE TABLE `tbl_shared_sched` (
  `id` int(11) NOT NULL,
  `sender_dept_code` varchar(100) NOT NULL,
  `sender_email` varchar(100) NOT NULL,
  `receiver_dept_code` varchar(100) NOT NULL,
  `receiver_email` varchar(100) NOT NULL,
  `shared_section` varchar(100) NOT NULL,
  `section_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `ay_code` varchar(100) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_signatory`
--

CREATE TABLE `tbl_signatory` (
  `id` int(11) NOT NULL,
  `college_code` varchar(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `user_type` varchar(100) NOT NULL,
  `recommending` varchar(255) NOT NULL,
  `reviewed` varchar(255) NOT NULL,
  `approved` varchar(255) NOT NULL,
  `position_recommending` varchar(100) NOT NULL,
  `position_reviewed` varchar(100) NOT NULL,
  `position_approved` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_signatory`
--

INSERT INTO `tbl_signatory` (`id`, `college_code`, `dept_code`, `semester`, `user_type`, `recommending`, `reviewed`, `approved`, `position_recommending`, `position_reviewed`, `position_approved`) VALUES
(19, 'CEIT', 'DCEE', '1st Semester', 'CCL Head', 'EMELINE C. GUEVARRA', 'FLORENCE M. BANASIHAN', 'WILLIE C. BUCLATIN', 'University Computer Center', 'College Registrar, CEIT', 'Dean'),
(20, 'CEIT', 'DIT', '1st Semester', 'Department Secretary', 'CHARLOTTE B. CARANDANG', 'FLORENCE M. BANASIHAN', 'WILLIE C. BUCLATIN', 'Department Chairperson', 'Registrar', 'Dean');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stud_acc`
--

CREATE TABLE `tbl_stud_acc` (
  `id` int(11) NOT NULL,
  `college_code` varchar(10) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `student_no` int(100) NOT NULL,
  `password` varchar(200) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `suffix` varchar(100) NOT NULL,
  `middle_initial` varchar(100) NOT NULL,
  `cvsu_email` varchar(100) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `acc_status` int(11) NOT NULL,
  `reg_adviser` varchar(100) NOT NULL,
  `section_code` varchar(20) NOT NULL,
  `remaining_years` int(3) NOT NULL,
  `num_year` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stud_prof_notif`
--

CREATE TABLE `tbl_stud_prof_notif` (
  `id` int(11) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `message` varchar(255) NOT NULL,
  `sched_code` varchar(50) NOT NULL,
  `receiver_type` varchar(100) NOT NULL,
  `sender_email` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `date_sent` datetime DEFAULT current_timestamp(),
  `sec_ro_prof_code` varchar(50) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `ay_code` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_timeslot`
--

CREATE TABLE `tbl_timeslot` (
  `id` int(100) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `table_start_time` varchar(100) NOT NULL,
  `table_end_time` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_timeslot`
--

INSERT INTO `tbl_timeslot` (`id`, `dept_code`, `table_start_time`, `table_end_time`) VALUES
(3, 'DIT', '7:00 AM', '7:00 PM'),
(5, 'DIT', '7:00 AM', '9:00 PM'),
(9, 'DIET', '7:00 AM', '8:00 PM');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_timeslot_active`
--

CREATE TABLE `tbl_timeslot_active` (
  `id` int(11) NOT NULL,
  `dept_code` varchar(100) NOT NULL,
  `ay_code` int(100) NOT NULL,
  `table_start_time` varchar(100) NOT NULL,
  `table_end_time` varchar(100) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `active` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_timeslot_active`
--

INSERT INTO `tbl_timeslot_active` (`id`, `dept_code`, `ay_code`, `table_start_time`, `table_end_time`, `semester`, `active`) VALUES
(30, 'DIT', 2425, '7:00 AM', '9:00 PM', '1st Semester', 1),
(33, 'DIET', 2425, '7:00 AM', '8:00 PM', '1st Semester', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `type` int(11) NOT NULL COMMENT '0=student; 1=prof',
  `status` varchar(200) NOT NULL DEFAULT 'Offline now',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 0, 'Offline now', '2024-09-18 12:39:39', '2024-09-18 12:40:50'),
(2, 0, 'Offline now', '2024-09-18 12:39:39', '2024-09-18 12:41:09'),
(8, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-30 14:24:12'),
(9, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-18 12:26:46'),
(10, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-30 14:08:11'),
(11, 1, 'Active now', '2024-09-18 12:26:46', '2024-09-30 14:46:38'),
(12, 1, 'Active now', '2024-09-18 12:26:46', '2024-09-25 16:07:13'),
(13, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-18 12:26:46'),
(14, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-18 12:26:46'),
(15, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-18 12:26:46'),
(16, 1, 'Offline now', '2024-09-18 12:26:46', '2024-09-18 12:26:46'),
(17, 1, 'Active now', '2024-09-18 12:26:46', '2024-09-18 14:16:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`msg_id`);

--
-- Indexes for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`college_code`);

--
-- Indexes for table `tbl_appointment`
--
ALTER TABLE `tbl_appointment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_assigned_course`
--
ALTER TABLE `tbl_assigned_course`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_ay`
--
ALTER TABLE `tbl_ay`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_code`
--
ALTER TABLE `tbl_code`
  ADD PRIMARY KEY (`cvsu_email`);

--
-- Indexes for table `tbl_college`
--
ALTER TABLE `tbl_college`
  ADD PRIMARY KEY (`college_code`);

--
-- Indexes for table `tbl_course`
--
ALTER TABLE `tbl_course`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_department`
--
ALTER TABLE `tbl_department`
  ADD PRIMARY KEY (`dept_code`);

--
-- Indexes for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_no_students`
--
ALTER TABLE `tbl_no_students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_pcontact_counter`
--
ALTER TABLE `tbl_pcontact_counter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contact` (`contact_code`,`prof_sched_code`),
  ADD KEY `dept_code` (`dept_code`),
  ADD KEY `prof_code` (`prof_code`);

--
-- Indexes for table `tbl_pcontact_schedstatus`
--
ALTER TABLE `tbl_pcontact_schedstatus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_pcontact_sched_dit_2425`
--
ALTER TABLE `tbl_pcontact_sched_dit_2425`
  ADD PRIMARY KEY (`sec_sched_id`);

--
-- Indexes for table `tbl_prof`
--
ALTER TABLE `tbl_prof`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_prof_acc`
--
ALTER TABLE `tbl_prof_acc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_prof_schedstatus`
--
ALTER TABLE `tbl_prof_schedstatus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_program`
--
ALTER TABLE `tbl_program`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dept_code` (`dept_code`);

--
-- Indexes for table `tbl_psched`
--
ALTER TABLE `tbl_psched`
  ADD PRIMARY KEY (`prof_sched_code`),
  ADD KEY `ay_code` (`ay_code`),
  ADD KEY `dept_code` (`dept_code`),
  ADD KEY `prof_code` (`prof_code`);

--
-- Indexes for table `tbl_psched_counter`
--
ALTER TABLE `tbl_psched_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_psched_dit_2425`
--
ALTER TABLE `tbl_psched_dit_2425`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_registration_adviser`
--
ALTER TABLE `tbl_registration_adviser`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_room`
--
ALTER TABLE `tbl_room`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_roomsched_ceit_2425`
--
ALTER TABLE `tbl_roomsched_ceit_2425`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_roomsched_dit_2425`
--
ALTER TABLE `tbl_roomsched_dit_2425`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_room_schedstatus`
--
ALTER TABLE `tbl_room_schedstatus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_rsched`
--
ALTER TABLE `tbl_rsched`
  ADD PRIMARY KEY (`room_sched_code`);

--
-- Indexes for table `tbl_schedstatus`
--
ALTER TABLE `tbl_schedstatus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_secschedlist`
--
ALTER TABLE `tbl_secschedlist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_secsched_dit_2425`
--
ALTER TABLE `tbl_secsched_dit_2425`
  ADD PRIMARY KEY (`sec_sched_id`);

--
-- Indexes for table `tbl_section`
--
ALTER TABLE `tbl_section`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dept_code` (`dept_code`),
  ADD KEY `program_code` (`program_code`);

--
-- Indexes for table `tbl_shared_sched`
--
ALTER TABLE `tbl_shared_sched`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_signatory`
--
ALTER TABLE `tbl_signatory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_stud_acc`
--
ALTER TABLE `tbl_stud_acc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_stud_prof_notif`
--
ALTER TABLE `tbl_stud_prof_notif`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_timeslot`
--
ALTER TABLE `tbl_timeslot`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_timeslot_active`
--
ALTER TABLE `tbl_timeslot_active`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_appointment`
--
ALTER TABLE `tbl_appointment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_assigned_course`
--
ALTER TABLE `tbl_assigned_course`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;

--
-- AUTO_INCREMENT for table `tbl_ay`
--
ALTER TABLE `tbl_ay`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_course`
--
ALTER TABLE `tbl_course`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=369;

--
-- AUTO_INCREMENT for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT for table `tbl_no_students`
--
ALTER TABLE `tbl_no_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_pcontact_counter`
--
ALTER TABLE `tbl_pcontact_counter`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_pcontact_schedstatus`
--
ALTER TABLE `tbl_pcontact_schedstatus`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_pcontact_sched_dit_2425`
--
ALTER TABLE `tbl_pcontact_sched_dit_2425`
  MODIFY `sec_sched_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_prof`
--
ALTER TABLE `tbl_prof`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=479;

--
-- AUTO_INCREMENT for table `tbl_prof_acc`
--
ALTER TABLE `tbl_prof_acc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `tbl_prof_schedstatus`
--
ALTER TABLE `tbl_prof_schedstatus`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `tbl_program`
--
ALTER TABLE `tbl_program`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tbl_psched_counter`
--
ALTER TABLE `tbl_psched_counter`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `tbl_psched_dit_2425`
--
ALTER TABLE `tbl_psched_dit_2425`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_registration_adviser`
--
ALTER TABLE `tbl_registration_adviser`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_room`
--
ALTER TABLE `tbl_room`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `tbl_roomsched_ceit_2425`
--
ALTER TABLE `tbl_roomsched_ceit_2425`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_roomsched_dit_2425`
--
ALTER TABLE `tbl_roomsched_dit_2425`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_room_schedstatus`
--
ALTER TABLE `tbl_room_schedstatus`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `tbl_schedstatus`
--
ALTER TABLE `tbl_schedstatus`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=470;

--
-- AUTO_INCREMENT for table `tbl_secschedlist`
--
ALTER TABLE `tbl_secschedlist`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `tbl_secsched_dit_2425`
--
ALTER TABLE `tbl_secsched_dit_2425`
  MODIFY `sec_sched_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_section`
--
ALTER TABLE `tbl_section`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tbl_shared_sched`
--
ALTER TABLE `tbl_shared_sched`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `tbl_signatory`
--
ALTER TABLE `tbl_signatory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_stud_acc`
--
ALTER TABLE `tbl_stud_acc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `tbl_stud_prof_notif`
--
ALTER TABLE `tbl_stud_prof_notif`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT for table `tbl_timeslot`
--
ALTER TABLE `tbl_timeslot`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_timeslot_active`
--
ALTER TABLE `tbl_timeslot_active`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
