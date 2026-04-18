-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Apr 17, 2026 at 03:50 PM
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
-- Database: `antcareers`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `seeker_id` int(10) UNSIGNED NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume_url` varchar(500) DEFAULT NULL,
  `status` enum('Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered','Accepted','Declined') NOT NULL DEFAULT 'Pending',
  `employer_notes` text DEFAULT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `job_id`, `seeker_id`, `cover_letter`, `resume_url`, `status`, `employer_notes`, `applied_at`, `reviewed_at`, `updated_at`) VALUES
(1, 1, 23, 'i am interested at this job', 'uploads/resumes/resume_23_1775522903.pdf', 'Pending', NULL, '2026-04-07 00:30:50', '2026-04-15 18:45:30', '2026-04-16 23:22:23'),
(3, 7, 1, 'hahahah', 'uploads/resumes/resume_1_1775318857.pdf', 'Pending', NULL, '2026-04-15 10:46:51', '2026-04-15 10:47:33', '2026-04-16 23:22:23'),
(4, 9, 1, NULL, 'uploads/resumes/resume_1_1775318857.pdf', 'Pending', NULL, '2026-04-15 15:40:56', '2026-04-16 23:35:03', '2026-04-16 23:35:03'),
(5, 6, 23, NULL, 'uploads/resumes/resume_23_1775522903.pdf', 'Pending', NULL, '2026-04-16 20:04:49', '2026-04-16 21:03:45', '2026-04-16 23:22:23'),
(6, 4, 35, NULL, NULL, 'Declined', NULL, '2026-04-17 00:00:34', '2026-04-17 00:02:48', '2026-04-17 00:02:48'),
(7, 9, 23, NULL, 'uploads/resumes/resume_23_1775522903.pdf', 'Pending', NULL, '2026-04-17 02:27:35', NULL, '2026-04-17 02:27:35'),
(8, 4, 12, NULL, NULL, 'Offered', NULL, '2026-04-17 11:51:29', '2026-04-17 11:52:20', '2026-04-17 11:52:20');

-- --------------------------------------------------------

--
-- Table structure for table `company_follows`
--

CREATE TABLE `company_follows` (
  `id` int(10) UNSIGNED NOT NULL,
  `follower_user_id` int(10) UNSIGNED NOT NULL,
  `employer_user_id` int(10) UNSIGNED NOT NULL,
  `followed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_follows`
--

INSERT INTO `company_follows` (`id`, `follower_user_id`, `employer_user_id`, `followed_at`) VALUES
(1, 23, 27, '2026-04-07 02:16:15'),
(2, 35, 27, '2026-04-16 20:08:42');

-- --------------------------------------------------------

--
-- Table structure for table `company_profiles`
--

CREATE TABLE `company_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(150) NOT NULL DEFAULT '',
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(30) DEFAULT NULL,
  `company_type` varchar(50) DEFAULT NULL,
  `founded_year` year(4) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `tagline` varchar(120) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `social_website` varchar(500) DEFAULT NULL,
  `social_linkedin` varchar(500) DEFAULT NULL,
  `social_facebook` varchar(500) DEFAULT NULL,
  `social_twitter` varchar(500) DEFAULT NULL,
  `social_instagram` varchar(500) DEFAULT NULL,
  `social_youtube` varchar(500) DEFAULT NULL,
  `perks` text DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `cover_path` varchar(500) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_profiles`
--

INSERT INTO `company_profiles` (`id`, `user_id`, `company_name`, `industry`, `company_size`, `company_type`, `founded_year`, `website`, `location`, `address_line`, `province`, `city`, `country`, `zip_code`, `about`, `tagline`, `contact_email`, `contact_phone`, `social_website`, `social_linkedin`, `social_facebook`, `social_twitter`, `social_instagram`, `social_youtube`, `perks`, `logo_path`, `cover_path`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 27, 'ryepagodna', 'Other', '501–1,000 employees', 'Private', '2010', NULL, NULL, NULL, 'Bulacan', 'Malolos', 'Philippines', '3001', 'dito tuturuan kita matulog maghapon', 'ayko na ayoko na', NULL, NULL, 'https://www.asurion.com/', NULL, NULL, NULL, NULL, NULL, '[\"Remote Work\",\"Free Snacks / Meals\",\"International Exposure\"]', 'uploads/logos/logo_27_1775528323.jpg', 'uploads/covers/cover_27_1775528333.jpg', 0, '2026-04-06 19:18:42', '2026-04-06 19:28:03'),
(2, 2, 'deyangcorp', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-04-15 11:26:26', '2026-04-15 11:26:26');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `conversation_key` varchar(80) NOT NULL,
  `participant_a_id` int(10) UNSIGNED NOT NULL,
  `participant_b_id` int(10) UNSIGNED NOT NULL,
  `latest_message_id` int(10) UNSIGNED DEFAULT NULL,
  `latest_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `created_at`, `updated_at`, `conversation_key`, `participant_a_id`, `participant_b_id`, `latest_message_id`, `latest_message_at`) VALUES
(1, '2026-04-11 07:04:49', '2026-04-11 07:04:49', 'direct:21:25', 21, 25, 3, '2026-04-10 07:56:00'),
(2, '2026-04-11 07:04:49', '2026-04-11 07:04:49', 'direct:20:32', 20, 32, 2, '2026-04-10 04:00:17'),
(3, '2026-04-11 07:04:49', '2026-04-11 07:29:36', 'direct:25:32', 25, 32, 6, '2026-04-11 07:29:36'),
(31, '2026-04-11 07:09:31', '2026-04-15 11:26:26', 'direct:1:2', 1, 2, 21, '2026-04-15 11:26:26'),
(995, '2026-04-11 19:24:48', '2026-04-14 03:58:05', 'direct:25:27', 25, 27, 13, '2026-04-14 03:58:05'),
(2177, '2026-04-14 01:26:06', '2026-04-16 21:03:45', 'direct:23:27', 23, 27, 24, '2026-04-16 21:03:45'),
(37156, '2026-04-14 22:29:46', '2026-04-14 22:29:51', 'direct:23:25', 23, 25, 20, '2026-04-14 22:29:51'),
(43240, '2026-04-15 11:30:37', '2026-04-15 11:30:39', 'direct:27:33', 27, 33, 22, '2026-04-15 11:30:39'),
(48471, '2026-04-15 14:49:24', '2026-04-15 14:49:24', 'direct:16:33', 16, 33, NULL, NULL),
(56016, '2026-04-15 17:21:56', '2026-04-15 17:21:56', 'direct:1:33', 1, 33, NULL, NULL),
(56177, '2026-04-15 17:22:01', '2026-04-15 17:22:01', 'direct:5:33', 5, 33, NULL, NULL),
(57450, '2026-04-15 17:44:26', '2026-04-15 17:44:26', 'direct:12:33', 12, 33, NULL, NULL),
(57963, '2026-04-15 17:48:00', '2026-04-15 17:48:00', 'direct:25:33', 25, 33, NULL, NULL),
(58948, '2026-04-15 18:49:59', '2026-04-15 18:49:59', 'direct:16:23', 16, 23, NULL, NULL),
(71445, '2026-04-17 00:01:41', '2026-04-17 00:01:41', 'direct:27:35', 27, 35, 25, '2026-04-17 00:01:41'),
(71842, '2026-04-17 00:05:35', '2026-04-17 11:52:20', 'direct:12:27', 12, 27, 27, '2026-04-17 11:52:20'),
(75513, '2026-04-17 01:19:59', '2026-04-17 01:19:59', 'direct:22:27', 22, 27, NULL, NULL),
(75704, '2026-04-17 01:20:08', '2026-04-17 01:20:08', 'direct:19:27', 19, 27, NULL, NULL),
(78035, '2026-04-17 01:27:45', '2026-04-17 01:27:45', 'direct:16:27', 16, 27, NULL, NULL),
(78166, '2026-04-17 01:27:50', '2026-04-17 01:27:50', 'direct:10:27', 10, 27, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hired_credentials`
--

CREATE TABLE `hired_credentials` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `seeker_id` int(10) UNSIGNED NOT NULL,
  `recruiter_user_id` int(10) UNSIGNED NOT NULL,
  `company_id` int(10) UNSIGNED NOT NULL,
  `temp_username` varchar(255) NOT NULL,
  `temp_password_hash` varchar(255) NOT NULL,
  `is_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `claimed_at` datetime DEFAULT NULL,
  `notification_sent` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_schedules`
--

CREATE TABLE `interview_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `seeker_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_mins` smallint(5) UNSIGNED DEFAULT 60,
  `interview_type` enum('Online','Phone','On-site') NOT NULL DEFAULT 'Online',
  `meeting_link` varchar(500) DEFAULT NULL,
  `location` varchar(300) DEFAULT NULL,
  `venue_name` varchar(300) DEFAULT NULL,
  `full_address` varchar(500) DEFAULT NULL,
  `map_link` varchar(500) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Scheduled','Cancelled','Completed','No-show') NOT NULL DEFAULT 'Scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interview_schedules`
--

INSERT INTO `interview_schedules` (`id`, `application_id`, `employer_id`, `seeker_id`, `scheduled_at`, `duration_mins`, `interview_type`, `meeting_link`, `location`, `venue_name`, `full_address`, `map_link`, `phone_number`, `contact_person`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 27, 23, '2026-04-09 02:00:00', 60, 'Online', 'https://ph.jobstreet.com/', NULL, NULL, NULL, NULL, NULL, NULL, 'interview', 'Cancelled', '2026-04-07 22:34:02', '2026-04-09 00:30:29'),
(2, 1, 27, 23, '2026-04-09 02:00:00', 60, 'Online', 'https://ph.jobstreet.com/', NULL, NULL, NULL, NULL, NULL, NULL, 'interview', 'Cancelled', '2026-04-07 22:34:02', '2026-04-09 00:30:29'),
(3, 1, 27, 23, '2026-04-09 02:00:00', 60, 'Online', 'https://ph.jobstreet.com/', NULL, NULL, NULL, NULL, NULL, NULL, 'interview', 'Cancelled', '2026-04-07 22:34:03', '2026-04-09 00:30:29'),
(4, 1, 27, 23, '2026-04-09 02:00:00', 60, 'Online', 'https://ph.jobstreet.com/', NULL, NULL, NULL, NULL, NULL, NULL, 'interview', 'Cancelled', '2026-04-07 22:34:04', '2026-04-09 00:30:29'),
(5, 1, 27, 23, '2026-04-09 02:00:00', 60, 'Online', 'https://ph.jobstreet.com/', NULL, NULL, NULL, NULL, NULL, NULL, 'interview', 'Cancelled', '2026-04-07 22:34:07', '2026-04-09 00:30:29'),
(6, 1, 27, 23, '2026-04-09 02:00:00', 60, 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'interview f2f', 'Cancelled', '2026-04-07 22:42:27', '2026-04-09 00:30:29'),
(7, 1, 27, 23, '2026-05-11 14:00:00', 60, 'On-site', NULL, 'BGC Asurion 4th Floor Room 402', 'BGC Asurion 4th Floor Room 402', '17th Floor Accralaw Tower 30th Street corner 2nd Avenue, Crescent Park West, Taguig, Manila', 'https://maps.app.goo.gl/64Hmrg9SBdWFp19q6', NULL, NULL, NULL, 'Scheduled', '2026-04-08 23:32:57', '2026-04-14 22:21:44'),
(8, 5, 27, 23, '2026-04-17 14:00:00', 60, 'Online', 'https://meet.google.com/mxq-gcmn-gyw', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Scheduled', '2026-04-16 20:12:02', '2026-04-16 20:12:02'),
(9, 4, 2, 1, '2026-04-25 16:00:00', 60, 'Online', 'https://meet.google.com/mxq-gcmn-gyw', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Scheduled', '2026-04-16 20:18:09', '2026-04-16 20:18:09');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `recruiter_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `job_type` enum('Full-time','Part-time','Contract','Freelance','Internship','Remote') NOT NULL DEFAULT 'Full-time',
  `setup` enum('On-site','Remote','Hybrid') NOT NULL DEFAULT 'On-site',
  `status` enum('Active','Closed','Draft') NOT NULL DEFAULT 'Active',
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `deadline` date DEFAULT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT 'PHP',
  `industry` varchar(100) DEFAULT NULL,
  `experience_level` enum('Entry','Junior','Mid','Senior','Lead','Executive') DEFAULT NULL,
  `skills_required` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `employer_id`, `recruiter_id`, `title`, `description`, `requirements`, `location`, `job_type`, `setup`, `status`, `approval_status`, `deadline`, `salary_min`, `salary_max`, `salary_currency`, `industry`, `experience_level`, `skills_required`, `created_at`, `updated_at`) VALUES
(1, 27, NULL, 'IT Support', 'Responsible for providing technical support and assistance to users by diagnosing and resolving hardware, software, and network issues. Handles installation, maintenance, and monitoring of computer systems and IT equipment to ensure smooth daily operations. Also assists in user account setup, system updates, and technical documentation.', 'Bachelor’s degree in Information Technology, Computer Science, or related field\nAt least 1 year of experience in IT support, technical support, or help desk is preferred\nFresh graduates with relevant internship or OJT experience are welcome to apply', 'Quezon City', 'Full-time', 'On-site', 'Active', 'approved', '2026-06-18', 20000.00, 35000.00, 'PHP', 'Technology', 'Entry', 'Technical Support & Troubleshooting, Hardware/Software Installation, Windows OS and Basic Network Support', '2026-04-06 21:10:22', '2026-04-14 08:28:41'),
(2, 25, NULL, 'secret', 'scam', 'magaling tumakbo, magtago,', 'qc', 'Contract', 'Remote', 'Active', 'approved', '2026-04-22', 100000.00, 500000.00, 'PHP', 'gambling', NULL, 'diff types of gamble', '2026-04-11 20:46:56', '2026-04-11 20:46:56'),
(4, 27, NULL, 'Engineering - Network', 'We are looking for a skilled Network Engineer to design, implement, maintain, and support our growing network infrastructure. You will be responsible for ensuring the stability, security, and efficient operation of all network systems.', 'Bachelor’s degree in IT, Computer Science, or related field\nProven experience as a Network Engineer or similar role\nKnowledge of networking concepts (TCP/IP, DNS, DHCP, VPN)\nExperience with routers, switches, and firewalls (e.g., Cisco)\nFamiliarity with network monitoring tools\nStrong problem-solving and troubleshooting skills\n\nPreferred (but not required):\n\nCertifications like Cisco CCNA / CCNP\nExperience with cloud networking (AWS, Azure)', 'Quezon City', 'Full-time', 'Hybrid', 'Active', 'approved', '2026-05-14', 70000.00, 120000.00, 'PHP', 'Information & Communication Technology', 'Senior', 'Design and install network systems (LAN, WAN, Wi-Fi), Monitor network performance and troubleshoot issues,Configure routers, switches, firewalls, and other devices', '2026-04-14 21:49:50', '2026-04-14 21:49:50'),
(6, 27, NULL, 'Product Design', 'We are looking for a creative and user-focused Product Designer to design intuitive, engaging, and visually appealing digital products. You will work closely with product managers, developers, and stakeholders to create user-centered designs that solve real problems.', 'Bachelor’s degree in Design, IT, or related field (or equivalent experience)\nExperience in UI/UX or product design\nProficiency in design tools like Figma, Adobe XD, or Sketch\nStrong understanding of UX principles and user-centered design\nPortfolio showcasing design projects\n\nExperience with prototyping tools (Figma, InVision)\nBasic knowledge of HTML/CSS\nFamiliarity with user research methods', 'Quezon City', 'Full-time', 'On-site', 'Active', 'approved', '2026-05-14', 35000.00, 70000.00, 'PHP', 'Design & Architecture', 'Mid', 'Design user interfaces for web and mobile applications, Create wireframes, prototypes, and high-fidelity designs', '2026-04-14 22:19:48', '2026-04-16 21:00:46'),
(7, 2, NULL, 'Accounts Officers / Clerks', 'gwefewf', 'efwfwef', 'Bulacan', 'Full-time', 'On-site', 'Active', 'approved', '2026-05-07', 2313213.00, 99999999.99, 'PHP', 'Accounting', 'Entry', 'haha', '2026-04-15 10:46:22', '2026-04-15 10:46:22'),
(8, 2, 33, 'Art Direction', 'fesfsef', 'efsfse', 'Bulacan', 'Contract', 'Hybrid', 'Active', 'pending', '2026-04-23', 3243243.00, 423432.00, 'PHP', 'Advertising, Arts & Media', 'Junior', 'hahadf', '2026-04-15 12:57:36', '2026-04-15 16:59:54'),
(9, 2, 33, 'CEO', 'fefe', 'fefe', 'baidhaid', 'Full-time', 'On-site', 'Active', 'pending', '2026-04-22', 3432432.00, 423424.00, 'PHP', 'CEO & General Management', 'Entry', 'fsf', '2026-04-15 15:17:14', '2026-04-15 15:17:14'),
(10, 2, NULL, 'Administrative Assistants', '', '', 'fsfe', 'Full-time', 'On-site', 'Active', 'approved', '2026-05-05', 3424.00, 34243.00, 'PHP', 'Administration & Office Support', 'Junior', 'dffefeffseffs', '2026-04-15 15:31:57', '2026-04-15 15:31:57');

-- --------------------------------------------------------

--
-- Table structure for table `job_invitations`
--

CREATE TABLE `job_invitations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` int(10) UNSIGNED NOT NULL,
  `recruiter_id` int(10) UNSIGNED NOT NULL,
  `jobseeker_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `custom_note` text DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite` (`job_id`, `jobseeker_id`),
  KEY `idx_inv_job` (`job_id`),
  KEY `idx_inv_recruiter` (`recruiter_id`),
  KEY `idx_inv_seeker` (`jobseeker_id`),
  KEY `idx_inv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_invitations`
--

INSERT INTO `job_invitations` (`id`, `job_id`, `recruiter_id`, `jobseeker_id`, `status`, `custom_note`, `sent_at`, `responded_at`) VALUES
(1, 8, 33, 23, 'pending', NULL, '2026-04-17 02:11:42', NULL),
(2, 9, 33, 26, 'pending', NULL, '2026-04-17 02:24:26', NULL),
(3, 9, 33, 23, 'accepted', NULL, '2026-04-17 02:24:47', '2026-04-17 02:27:35'),
(4, 8, 33, 5, 'pending', NULL, '2026-04-17 02:36:24', NULL),
(5, 8, 33, 1, 'pending', NULL, '2026-04-17 02:36:47', NULL),
(6, 8, 33, 16, 'pending', NULL, '2026-04-17 11:46:13', NULL),
(7, 8, 33, 35, 'pending', NULL, '2026-04-17 11:46:55', NULL),
(8, 8, 33, 12, 'pending', NULL, '2026-04-17 11:48:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `user_agent`, `success`, `attempted_at`) VALUES
(1, 'dollanodea10@gmail.com', '139.135.192.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-21 04:11:21'),
(2, 'dollanodea10@gmail.com', '139.135.192.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-21 04:17:59'),
(3, 'dollanodea10@gmail.com', '139.135.192.48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-21 04:29:17'),
(4, 'dryaleve@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 07:35:41'),
(5, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 07:45:22'),
(6, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 07:45:42'),
(7, 'dollanodea10@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-03-21 08:40:21'),
(8, 'dollanodea10@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-21 08:41:27'),
(9, 'dollanodea10@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-21 09:38:59'),
(10, 'dryaleve11502@gmai.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 10:07:08'),
(11, 'dryaleve111502@gmai.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 10:07:16'),
(12, 'dryaleve111502@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 10:07:22'),
(13, 'dryaleve11502@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 10:07:39'),
(14, 'dryaleve11502@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 10:20:52'),
(15, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 10:24:56'),
(16, 'dryaleve11502@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 10:40:47'),
(17, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:09:03'),
(18, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:09:26'),
(19, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:39:01'),
(20, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:44:32'),
(21, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:45:17'),
(22, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:45:44'),
(23, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:47:02'),
(24, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:54:38'),
(25, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 12:56:33'),
(26, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 13:16:39'),
(27, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 13:34:29'),
(28, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 14:52:48'),
(29, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 15:02:31'),
(30, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 15:03:32'),
(31, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 15:03:58'),
(32, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:02:16'),
(33, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:06:00'),
(34, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:29:47'),
(35, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:31:01'),
(36, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:36:53'),
(37, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 20:54:23'),
(38, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:06:52'),
(39, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:07:39'),
(40, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:21:56'),
(41, 'deadae@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:27:25'),
(42, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-21 21:33:07'),
(43, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:33:14'),
(44, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 21:46:09'),
(45, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:17:22'),
(46, 'rey@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:30:24'),
(47, 'boaz@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:30:52'),
(48, 'rey@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:31:24'),
(49, 'Medranacorp@yahoo.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:33:34'),
(50, 'rey@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:34:07'),
(51, 'Medranacorp@yahoo.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:35:35'),
(52, 'rey@gmail.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-21 22:38:39'),
(53, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-23 01:17:29'),
(54, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-23 01:17:40'),
(55, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-23 01:17:46'),
(56, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-23 01:17:49'),
(57, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-23 01:17:53'),
(58, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-23 01:22:04'),
(59, 'admin@antcareers.com', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-23 01:22:52'),
(60, 'dollanodea10@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-23 01:48:43'),
(61, 'dollanodea10@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-23 19:49:22'),
(62, 'dollanodea10@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-23 21:17:41'),
(63, 'dollanodea10@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-23 21:19:16'),
(64, 'deadae@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-24 00:11:01'),
(65, 'dollanodea10@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-24 00:27:10'),
(66, 'deadae@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-24 00:45:36'),
(67, 'dollanodea10@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-24 00:47:17'),
(68, 'deadae@gmail.com', '122.53.142.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-24 01:17:09'),
(69, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-29 03:20:05'),
(70, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-29 03:20:11'),
(71, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-29 03:21:19'),
(72, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-29 07:32:42'),
(73, 'leo@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-29 09:02:04'),
(74, 'sdasdasdasd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-30 06:32:25'),
(75, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-30 06:32:32'),
(76, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-30 06:32:49'),
(77, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-30 06:52:28'),
(78, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-30 07:18:11'),
(79, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-30 07:20:29'),
(80, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-30 07:20:33'),
(81, 'sigma@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-30 07:20:41'),
(82, 'chad@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-31 02:12:04'),
(83, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:12:14'),
(84, 'asd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-31 02:12:59'),
(85, 'ads@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-31 02:13:09'),
(86, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:18:29'),
(87, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:36:29'),
(88, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-03-31 02:37:02'),
(89, 'asd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:40:32'),
(90, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:44:35'),
(91, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:47:26'),
(92, 'asd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 02:47:32'),
(93, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:00:06'),
(94, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:02:18'),
(95, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:20:42'),
(96, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:31:34'),
(97, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 03:32:46'),
(98, 'asd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:39:37'),
(99, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 03:40:39'),
(100, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 03:45:08'),
(101, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 04:02:59'),
(102, 'asd@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 04:15:59'),
(103, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 04:16:27'),
(104, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 04:25:48'),
(105, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 04:30:37'),
(106, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 05:17:25'),
(107, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 05:19:02'),
(108, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 05:25:13'),
(109, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 05:26:04'),
(110, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 05:35:16'),
(111, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 05:36:19'),
(112, 'mark@gmail.com', '136.158.63.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 05:47:27'),
(113, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 06:26:27'),
(114, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 06:32:07'),
(115, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 06:38:10'),
(116, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 06:59:20'),
(117, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-03-31 07:13:30'),
(118, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 07:22:59'),
(119, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 07:27:17'),
(120, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 07:30:54'),
(121, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 07:39:03'),
(122, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-03-31 07:44:13'),
(123, 'mark@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-01 23:29:16'),
(124, 'chad@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-04-01 23:33:18'),
(125, 'asd@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-01 23:33:22'),
(126, 'asd@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-02 01:19:34'),
(127, 'mark@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-02 01:29:41'),
(128, 'mark@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-02 03:31:32'),
(129, 'asd@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-02 03:31:38'),
(130, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-02 04:07:48'),
(131, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-02 23:53:57'),
(132, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-02 23:56:03'),
(133, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 00:43:11'),
(134, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 00:43:24'),
(135, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 00:46:03'),
(136, 'dollanodea@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 00:54:06'),
(137, 'dollanodea@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 01:00:53'),
(138, 'dollanodae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 01:00:59'),
(139, 'dollanodae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 01:01:09'),
(140, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 01:01:17'),
(141, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-03 01:01:21'),
(142, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:01:52'),
(143, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:17:58'),
(144, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:29:55'),
(145, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:30:10'),
(146, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:38:09'),
(147, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:58:08'),
(148, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 01:58:21'),
(149, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', 1, '2026-04-03 02:01:28'),
(150, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:03:51'),
(151, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:05:37'),
(152, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:06:20'),
(153, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:07:25'),
(154, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:13:22'),
(155, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:43:59'),
(156, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:55:16'),
(157, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:55:55'),
(158, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 02:56:43'),
(159, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 03:32:16'),
(160, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 03:32:35'),
(161, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 04:27:25'),
(162, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 08:25:31'),
(163, 'deadae@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 22:51:05'),
(164, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-03 22:54:08'),
(165, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 01:02:43'),
(166, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 01:28:57'),
(167, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 01:47:17'),
(168, 'dollanodea10@gmail.com', '180.190.227.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 02:02:07'),
(169, 'mark@gmail.com', '136.158.63.221', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-04 08:02:11'),
(170, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 08:02:43'),
(171, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 08:40:04'),
(172, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 08:41:48'),
(173, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 08:55:47'),
(174, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-04 08:57:47'),
(175, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 08:57:53'),
(176, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:00:16'),
(177, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:03:12'),
(178, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:20:17'),
(179, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:21:05'),
(180, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:28:18'),
(181, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:34:18'),
(182, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:35:52'),
(183, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:43:08'),
(184, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:43:27'),
(185, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:44:09'),
(186, 'deadae@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:45:10'),
(187, 'dollanodea10@gmail.com', '180.190.227.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-04 09:53:03'),
(188, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 17:46:56'),
(189, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 17:49:27'),
(190, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 18:42:58'),
(191, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 18:43:16'),
(192, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 19:09:30'),
(193, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 19:10:22'),
(194, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 19:18:14'),
(195, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 20:11:22'),
(196, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 20:53:10'),
(197, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:12:49'),
(198, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:14:55'),
(199, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:17:09'),
(200, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:18:20'),
(201, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:24:59'),
(202, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 21:29:44'),
(203, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-06 22:12:20'),
(204, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 22:12:23'),
(205, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 22:31:18'),
(206, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 22:37:50'),
(207, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 22:40:15'),
(208, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 22:41:53'),
(209, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 23:14:47'),
(210, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 23:14:56'),
(211, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 23:17:50'),
(212, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 23:21:08'),
(213, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-06 23:25:15'),
(214, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 00:31:18'),
(215, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 00:32:51'),
(216, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 01:01:32'),
(217, 'rye@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 01:01:58'),
(218, 'ryecorp@gmail.com', '157.10.33.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 03:19:34'),
(219, 'rye@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:09:19'),
(220, 'ryecorp@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:21:39'),
(221, 'rye@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:36:12'),
(222, 'ryecorp@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:38:18'),
(223, 'rye@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:39:12'),
(224, 'ryecorp@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:41:46'),
(225, 'rye@gmail.com', '175.176.24.141', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-07 22:43:28'),
(226, 'rye@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-08 21:55:58'),
(227, 'ryecorp@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-08 21:57:21'),
(228, 'rye@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-08 23:33:19'),
(229, 'ryecorp@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-08 23:39:57'),
(230, 'rye@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-08 23:55:26'),
(231, 'ryecorp@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-09 00:03:51'),
(232, 'rye@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-09 00:13:49'),
(233, 'ryecorp@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-09 00:30:18'),
(234, 'rye@gmail.com', '124.106.77.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-09 00:31:30'),
(235, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 0, '2026-04-10 03:19:27'),
(236, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:19:38'),
(237, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:21:27'),
(238, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:23:57'),
(239, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:25:24'),
(240, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:55:43'),
(241, 'mark@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 03:58:17'),
(242, 'mark@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 04:00:27'),
(243, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 04:00:37'),
(244, 'mark@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 07:56:14'),
(245, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 08:06:20'),
(246, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 08:07:25'),
(247, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 08:07:52'),
(248, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 08:08:05'),
(249, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 08:08:33'),
(250, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 11:01:31'),
(251, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 11:02:39'),
(252, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 13:00:01'),
(253, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 13:04:47'),
(254, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:04:57'),
(255, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:06:23'),
(256, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:19:40'),
(257, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:30:31'),
(258, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:31:00'),
(259, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-10 18:35:08'),
(260, 'mark1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 1, '2026-04-11 02:44:34'),
(261, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-11 19:19:13'),
(262, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-11 19:23:03'),
(263, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-11 19:25:37'),
(264, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-11 19:25:48');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `user_agent`, `success`, `attempted_at`) VALUES
(265, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:16:01'),
(266, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:20:48'),
(267, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:22:03'),
(268, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:23:35'),
(269, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:26:33'),
(270, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:29:11'),
(271, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:33:51'),
(272, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-14 01:34:47'),
(273, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:34:52'),
(274, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:36:21'),
(275, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:37:13'),
(276, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 01:37:51'),
(277, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 02:30:52'),
(278, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 02:47:47'),
(279, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 02:49:11'),
(280, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 02:53:29'),
(281, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:16:39'),
(282, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:32:48'),
(283, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:36:30'),
(284, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:37:34'),
(285, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:41:19'),
(286, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 03:57:18'),
(287, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 04:01:42'),
(288, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:02:01'),
(289, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:02:35'),
(290, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:03:32'),
(291, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:05:57'),
(292, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:12:15'),
(293, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:18:01'),
(294, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:18:41'),
(295, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:19:50'),
(296, 'leo@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 0, '2026-04-14 08:41:59'),
(297, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:42:13'),
(298, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 08:46:14'),
(299, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 21:24:03'),
(300, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 21:38:41'),
(301, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 21:54:34'),
(302, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:02:54'),
(303, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:13:10'),
(304, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:16:29'),
(305, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:24:34'),
(306, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:40:49'),
(307, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:41:31'),
(308, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 1, '2026-04-14 22:43:31'),
(309, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-15 09:51:13'),
(310, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 09:51:17'),
(311, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:43:55'),
(312, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:46:36'),
(313, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:47:01'),
(314, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:47:56'),
(315, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:48:40'),
(316, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 10:50:42'),
(317, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 11:26:39'),
(318, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 11:27:38'),
(319, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 12:02:38'),
(320, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 12:55:25'),
(321, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 14:51:28'),
(322, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 14:53:28'),
(323, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 14:54:48'),
(324, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:14:42'),
(325, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:16:15'),
(326, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:18:34'),
(327, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:21:15'),
(328, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:38:21'),
(329, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:39:37'),
(330, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:40:35'),
(331, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 15:49:57'),
(332, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:07:34'),
(333, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:10:38'),
(334, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:32:51'),
(335, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:53:03'),
(336, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:55:53'),
(337, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 16:56:23'),
(338, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:11:45'),
(339, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:12:06'),
(340, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:14:37'),
(341, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:16:13'),
(342, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:16:50'),
(343, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:17:52'),
(344, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:20:38'),
(345, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:21:41'),
(346, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:26:12'),
(347, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:27:07'),
(348, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:27:32'),
(349, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:28:25'),
(350, 'dollanoda10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-15 17:53:08'),
(351, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 17:53:13'),
(352, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:42:41'),
(353, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:45:03'),
(354, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:47:38'),
(355, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:50:58'),
(356, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:51:57'),
(357, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:51:58'),
(358, 'deadae@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-15 18:52:29'),
(359, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 19:55:00'),
(360, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:05:13'),
(361, 'asd@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:06:46'),
(362, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:09:09'),
(363, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:12:27'),
(364, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:13:37'),
(365, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:14:05'),
(366, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:14:25'),
(367, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:15:00'),
(368, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:20:38'),
(369, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:27:53'),
(370, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:37:13'),
(371, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:42:59'),
(372, 'reycorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 20:45:52'),
(373, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:46:01'),
(374, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:52:41'),
(375, 'reycorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 20:53:50'),
(376, 'reycorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 20:53:57'),
(377, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:54:03'),
(378, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:58:35'),
(379, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 20:59:18'),
(380, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 21:04:22'),
(381, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 21:08:26'),
(382, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 21:27:20'),
(383, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 21:51:34'),
(384, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:17:53'),
(385, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:19:23'),
(386, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:20:00'),
(387, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:21:48'),
(388, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:23:43'),
(389, 'ryecrop@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 22:24:10'),
(390, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:24:15'),
(391, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:25:28'),
(392, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:35:47'),
(393, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:51:10'),
(394, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:55:03'),
(395, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 22:55:42'),
(396, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:01:57'),
(397, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:03:47'),
(398, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:04:33'),
(399, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:06:54'),
(400, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:16:36'),
(401, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 23:17:25'),
(402, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:17:29'),
(403, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:19:34'),
(404, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:24:58'),
(405, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:25:42'),
(406, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.8115', 0, '2026-04-16 23:29:47'),
(407, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:35:40'),
(408, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:49:33'),
(409, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:50:14'),
(410, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:51:35'),
(411, 'reyal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-16 23:59:39'),
(412, 'reyal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-16 23:59:47'),
(413, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:00:58'),
(414, 'reyal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:01:56'),
(415, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:03:02'),
(416, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:04:33'),
(417, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:04:54'),
(418, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:05:55'),
(419, 'a.dionisio@ryepagodna.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:08:07'),
(420, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:09:32'),
(421, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:29:22'),
(422, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 00:44:38'),
(423, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 01:07:10'),
(424, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 01:07:15'),
(425, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 01:07:38'),
(426, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 01:08:03'),
(427, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 01:42:02'),
(428, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:05:05'),
(429, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:05:17'),
(430, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:12:04'),
(431, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:13:37'),
(432, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:25:06'),
(433, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:26:19'),
(434, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 02:27:22'),
(435, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:27:26'),
(436, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:27:55'),
(437, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:32:32'),
(438, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:33:17'),
(439, 'rye@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:33:43'),
(440, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:36:06'),
(441, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 02:52:14'),
(442, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:52:18'),
(443, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 02:58:04'),
(444, 'reycorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 03:05:23'),
(445, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 03:05:29'),
(446, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 03:30:50'),
(447, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 03:36:32'),
(448, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:39:43'),
(449, 'dollanodea10@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:43:17'),
(450, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:44:23'),
(451, 'reyalib@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 11:47:12'),
(452, 'reyalib@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 11:47:21'),
(453, 'reyalib@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 0, '2026-04-17 11:47:34'),
(454, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:47:46'),
(455, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:48:31'),
(456, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:50:05'),
(457, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:50:42'),
(458, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:51:48'),
(459, 'anne@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 11:52:37'),
(460, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 19:11:36'),
(461, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 19:12:38'),
(462, 'd.dollano@deyangcorp.work', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 19:13:35'),
(463, 'ryecorp@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 1, '2026-04-17 20:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `receiver_id` int(10) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `message_type` varchar(20) DEFAULT 'text',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `conversation_id`, `subject`, `body`, `message_type`, `is_read`, `seen_at`, `created_at`, `updated_at`) VALUES
(1, 25, 21, 1, NULL, 'hiiii', 'text', 0, NULL, '2026-04-10 03:57:08', '2026-04-11 07:04:49'),
(2, 32, 20, 2, NULL, 'hey', 'text', 0, NULL, '2026-04-10 04:00:17', '2026-04-11 07:04:49'),
(3, 25, 21, 1, NULL, 'ADADADADA', 'text', 0, NULL, '2026-04-10 07:56:00', '2026-04-11 07:04:49'),
(4, 32, 25, 3, NULL, 'sakit na ng ulo ko', 'text', 1, NULL, '2026-04-10 08:08:22', '2026-04-11 07:04:49'),
(5, 25, 32, 3, NULL, 'pake ko kiupal', 'text', 1, NULL, '2026-04-10 11:01:41', '2026-04-11 07:04:49'),
(6, 32, 25, 3, NULL, 'ayos na messages', 'text', 1, '2026-04-11 20:43:15', '2026-04-11 07:29:36', '2026-04-11 20:43:15'),
(7, 27, 25, 995, NULL, 'kupal', 'text', 1, '2026-04-11 19:26:22', '2026-04-11 19:24:51', '2026-04-11 19:26:22'),
(8, 23, 27, 2177, NULL, 'hello', 'text', 1, '2026-04-14 01:26:48', '2026-04-14 01:26:14', '2026-04-14 01:26:48'),
(9, 27, 23, 2177, NULL, 'hi', 'text', 1, '2026-04-14 01:33:19', '2026-04-14 01:26:52', '2026-04-14 01:33:19'),
(10, 23, 27, 2177, NULL, 'hi', 'text', 1, '2026-04-14 01:35:08', '2026-04-14 01:33:33', '2026-04-14 01:35:08'),
(11, 23, 27, 2177, NULL, 'bakit  minsan pahaba minsan hind', 'text', 1, '2026-04-14 01:35:08', '2026-04-14 01:34:28', '2026-04-14 01:35:08'),
(12, 27, 23, 2177, NULL, 'hey', 'text', 1, '2026-04-14 04:02:15', '2026-04-14 03:57:46', '2026-04-14 04:02:15'),
(13, 27, 25, 995, NULL, 'wow', 'text', 0, NULL, '2026-04-14 03:58:05', '2026-04-14 03:58:05'),
(14, 27, 23, 2177, NULL, 'hhihihihihihihihihihihihii', 'text', 1, '2026-04-14 04:02:15', '2026-04-14 03:58:41', '2026-04-14 04:02:15'),
(15, 27, 23, 2177, NULL, 'hi youve been hired congratulations', 'text', 1, '2026-04-14 21:58:47', '2026-04-14 21:53:25', '2026-04-14 21:58:47'),
(16, 23, 27, 2177, NULL, 'hello', 'text', 1, '2026-04-14 22:11:10', '2026-04-14 21:58:59', '2026-04-14 22:11:10'),
(17, 27, 23, 2177, NULL, 'hi', 'text', 1, '2026-04-14 22:29:12', '2026-04-14 22:11:25', '2026-04-14 22:29:12'),
(18, 27, 23, 2177, NULL, 'how are u', 'text', 1, '2026-04-14 22:29:12', '2026-04-14 22:22:42', '2026-04-14 22:29:12'),
(19, 23, 27, 2177, NULL, 'im fine thank u', 'text', 1, '2026-04-16 20:05:39', '2026-04-14 22:29:23', '2026-04-16 20:05:39'),
(20, 23, 25, 37156, NULL, 'hello helo', 'text', 0, NULL, '2026-04-14 22:29:51', '2026-04-14 22:29:51'),
(21, 2, 1, 31, 'Your Recruiter Account Credentials', 'Hello Dea,\n\nYou have been added as a Recruiter for deyangcorp.\n\nHere are your login credentials:\n• Platform Email: d.dollano@deyangcorp.work\n• Temporary Password: nuzWV4hCva!\n\nPlease log in at http://localhost/antcareers and change your password immediately.\n\n— deyangcorp Admin', 'text', 1, '2026-04-15 11:26:46', '2026-04-15 11:26:26', '2026-04-15 11:26:46'),
(22, 33, 27, 43240, NULL, 'hi', 'text', 1, '2026-04-17 01:34:32', '2026-04-15 11:30:39', '2026-04-17 01:34:32'),
(23, 27, 23, 2177, 'Congratulations — You\'ve Been Offered!', 'Hi rye del rosario,\n\nGreat news — you\'ve been offered the position \"IT Support\"!\n\nHere are your recruiter portal credentials:\n• Platform Email: r.rosario@ryepagodna.work\n• Temporary Password: FthxzE#3Vf$V\n\nPlease log in and change your password immediately.\n\nWelcome to the team!', 'text', 1, '2026-04-15 18:47:44', '2026-04-15 18:45:30', '2026-04-15 18:47:44'),
(24, 27, 23, 2177, 'Congratulations — You\'ve Been Offered!', 'Hi rye del rosario,\n\nGreat news — you\'ve been offered the position \"Product Design\"!\n\nHere are your recruiter portal credentials:\n• Platform Email: r.rosario1@ryepagodna.work\n• Temporary Password: SfNXm#ZV9QnV\n\nPlease log in and change your password immediately.\n\nWelcome to the team!', 'text', 1, '2026-04-16 21:04:35', '2026-04-16 21:03:45', '2026-04-16 21:04:35'),
(25, 27, 35, 71445, 'Congratulations — You\'ve Been Offered!', 'Hi reyal ib,\n\nGreat news — you\'ve been offered the position \"Engineering - Network\"!\n\nPlease review the offer details and respond at your earliest convenience. You can accept or decline this offer from your applications page.\n\nWe look forward to hearing from you!', 'text', 1, '2026-04-17 00:02:08', '2026-04-17 00:01:41', '2026-04-17 00:02:08'),
(26, 27, 12, 71842, 'Your Recruiter Account Credentials', 'Hello Anne,\n\nYou have been added as a Recruiter for ryepagodna.\n\nHere are your login credentials:\n• Platform Email: a.dionisio@ryepagodna.work\n• Temporary Password: sigP3QaUMQ!\n\nPlease log in at http://localhost/AntCareers and change your password immediately.\n\n— ryepagodna Admin', 'text', 1, '2026-04-17 00:06:09', '2026-04-17 00:05:35', '2026-04-17 00:06:09'),
(27, 27, 12, 71842, 'Congratulations — You\'ve Been Offered!', 'Hi anne dionisio,\n\nGreat news — you\'ve been offered the position \"Engineering - Network\"!\n\nPlease review the offer details and respond at your earliest convenience. You can accept or decline this offer from your applications page.\n\nWe look forward to hearing from you!', 'text', 1, '2026-04-17 11:52:50', '2026-04-17 11:52:20', '2026-04-17 11:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `content` text NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `actor_id`, `type`, `content`, `reference_id`, `reference_type`, `is_read`, `created_at`) VALUES
(1, 21, 25, 'message', 'asdasd asdasdas sent you a new message.', 25, 'user', 0, '2026-04-10 03:57:08'),
(2, 20, 32, 'message', 'mark admin sent you a new message.', 32, 'user', 0, '2026-04-10 04:00:17'),
(3, 21, 25, 'message', 'asdasd asdasdas sent you a new message.', 25, 'user', 0, '2026-04-10 07:56:00'),
(4, 25, 32, 'message', 'mark admin sent you a new message.', 32, 'user', 1, '2026-04-10 08:08:22'),
(5, 32, 25, 'message', 'asdasd asdasdas sent you a new message.', 25, 'user', 1, '2026-04-10 11:01:41'),
(6, 25, 32, 'message', 'adsadmar sent you a new message.', 32, 'user', 1, '2026-04-11 07:29:36'),
(7, 25, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-11 19:24:51'),
(9, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 01:26:52'),
(12, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 03:57:46'),
(13, 25, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 0, '2026-04-14 03:58:05'),
(14, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 03:58:41'),
(15, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 21:53:25'),
(17, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 22:11:25'),
(18, 23, 27, 'message', 'ryepagodna sent you a new message.', 27, 'user', 1, '2026-04-14 22:22:42'),
(20, 25, 23, 'message', 'rye del rosario sent you a new message.', 23, 'user', 0, '2026-04-14 22:29:51'),
(22, 2, NULL, 'recruiter_added', 'Recruiter Dea Dollano (d.dollano@deyangcorp.work) has been added.', 33, NULL, 1, '2026-04-15 11:26:26'),
(25, 23, NULL, 'interview_invite', 'Great news! Your application for \"Product Design\" has been shortlisted and an interview has been scheduled.', 5, NULL, 1, '2026-04-16 20:12:02'),
(29, 35, NULL, 'offer', 'Congratulations! You\'ve been offered the position \"Engineering - Network\". Check your messages for details.', 6, NULL, 1, '2026-04-17 00:01:41'),
(31, 12, NULL, 'recruiter_credentials', 'You\'ve been added as a recruiter for ryepagodna. Check your messages for login credentials.', 38, NULL, 1, '2026-04-17 00:05:35'),
(33, 23, 33, 'job_invite', 'Dea Dollano invited you to apply for \"Art Direction\" at deyangcorp', 1, 'invitation', 1, '2026-04-17 02:11:42'),
(34, 26, 33, 'job_invite', 'Dea Dollano invited you to apply for \"CEO\" at deyangcorp', 2, 'invitation', 0, '2026-04-17 02:24:26'),
(35, 23, 33, 'job_invite', 'Dea Dollano invited you to apply for \"CEO\" at deyangcorp', 3, 'invitation', 1, '2026-04-17 02:24:47'),
(36, 33, 23, 'invite_accepted', 'rye del rosario accepted your invitation and applied for \"CEO\"', 9, 'job', 1, '2026-04-17 02:27:35'),
(37, 2, 23, 'invite_accepted', 'rye del rosario accepted your invitation and applied for \"CEO\"', 9, 'job', 0, '2026-04-17 02:27:35'),
(38, 5, 33, 'job_invite', 'Dea Dollano invited you to apply for \"Art Direction\" at deyangcorp', 4, 'invitation', 0, '2026-04-17 02:36:24'),
(40, 16, 33, 'job_invite', 'Dea Dollano invited you to apply for \"Art Direction\" at deyangcorp', 6, 'invitation', 0, '2026-04-17 11:46:13'),
(41, 35, 33, 'job_invite', 'Dea Dollano invited you to apply for \"Art Direction\" at deyangcorp', 7, 'invitation', 0, '2026-04-17 11:46:55'),
(42, 12, 33, 'job_invite', 'Dea Dollano invited you to apply for \"Art Direction\" at deyangcorp', 8, 'invitation', 1, '2026-04-17 11:48:18'),
(43, 12, 27, 'offer', 'Congratulations! You\'ve been offered the position \"Engineering - Network\". Check your messages for details.', 8, 'application', 1, '2026-04-17 11:52:20'),
(44, 2, 23, 'new_application', 'rye del rosario applied for Administrative Assistants', 10, 'job', 0, '2026-04-17 21:15:24'),
(45, 33, 23, 'new_application', 'rye del rosario applied for CEO', 9, 'job', 0, '2026-04-17 21:30:23');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recruiters`
--

CREATE TABLE `recruiters` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `role` enum('recruiter','co-admin','viewer') NOT NULL DEFAULT 'recruiter',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `invited_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruiters`
--

INSERT INTO `recruiters` (`id`, `user_id`, `company_id`, `employer_id`, `role`, `is_active`, `invited_at`, `accepted_at`, `deactivated_at`, `created_at`, `updated_at`) VALUES
(1, 33, 2, 2, 'recruiter', 1, '2026-04-15 11:26:26', '2026-04-15 11:26:26', NULL, '2026-04-15 11:26:26', '2026-04-15 11:26:26'),
(5, 38, 1, 27, 'recruiter', 1, '2026-04-17 00:05:35', '2026-04-17 00:05:35', NULL, '2026-04-17 00:05:35', '2026-04-17 00:05:35');

-- --------------------------------------------------------

--
-- Table structure for table `recruiter_profiles`
--

CREATE TABLE `recruiter_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `personal_email` varchar(255) DEFAULT NULL,
  `position` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruiter_profiles`
--

INSERT INTO `recruiter_profiles` (`id`, `user_id`, `personal_email`, `position`, `department`, `phone`, `bio`, `linkedin_url`, `created_at`, `updated_at`) VALUES
(1, 33, 'dollanodea10@gmail.com', 'Senior Recruiter', NULL, NULL, NULL, NULL, '2026-04-15 11:26:26', '2026-04-15 11:26:26'),
(5, 38, 'anne@gmail.com', 'Intern Recuriter', '', '', '', NULL, '2026-04-17 00:05:35', '2026-04-17 00:08:28');

-- --------------------------------------------------------

--
-- Table structure for table `recruiter_stats`
--

CREATE TABLE `recruiter_stats` (
  `id` int(10) UNSIGNED NOT NULL,
  `recruiter_id` int(10) UNSIGNED NOT NULL,
  `jobs_posted` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `applicants_reviewed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `interviews_scheduled` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `hires_made` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruiter_stats`
--

INSERT INTO `recruiter_stats` (`id`, `recruiter_id`, `jobs_posted`, `applicants_reviewed`, `interviews_scheduled`, `hires_made`, `updated_at`) VALUES
(1, 1, 0, 0, 0, 0, '2026-04-15 11:26:26'),
(2, 5, 0, 0, 0, 0, '2026-04-17 00:05:35');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `ip_address`, `user_agent`, `created_at`) VALUES
(8, 3, '7c906d6bf2deecffc5602954cd1d4040:25341bde54cfc47c3901cb688af033d95078a65da1df2926cb5c6d4771140217', '2026-04-20 13:40:47', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 10:40:47'),
(41, 11, 'e16f76cfa32c82e29c1bcceaa4445b5e:cb47d95518e74faa7edf3068c82be7ee861880935aa6087b34048417517c4a48', '2026-04-21 01:35:35', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 22:35:35'),
(42, 10, '1d89d3b0e731dabe35b09ff7ee1b3a3f:7f2e1606d6d2c8feb7f600f93c9310114198c1a7fe2caef6f12ab6b46b2c2ecb', '2026-04-21 01:38:39', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 22:38:39'),
(44, 7, 'faa548aa7689cca2de98d3c6d7a3c7d8:1afbe00efb9f36eb125e03160d284e1dea0df41baeabf904a4f434f4b10b5d1a', '2026-04-22 04:22:52', '157.10.33.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 01:22:52'),
(222, 32, '438f71236b30eed56283b81f419125c5:5e4842fa29c46b70e252775eacd08ad3e7585629cd892a377a8d751a0240fae1', '2026-05-10 20:44:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 02:44:35'),
(315, 2, 'db5f13d6e5a961acdeb2f721667d6504:492611149cc52d24df57b586c636436495c0c5f7a24af196ae77d43c1a542794', '2026-05-15 12:52:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-15 18:52:29'),
(402, 12, '94f65784fbcdbe83ef01e2b4e4554045:11d80a5add2e41c0628e904df821ac89816bda2082730c84f1cebf8ec6dfaa0f', '2026-05-17 05:52:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-17 11:52:37'),
(405, 33, 'f2a5c9498acc6d87b02d37b5d1161703:d7519b4e90b7cdcc29a862065f716c7a77a3c443a8d932296cb6fb40c8642031', '2026-05-17 13:13:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-17 19:13:35'),
(406, 27, '68a26707492a6c5a07c18823a9a2cd8b:7264be238ad0c0ca8538b8938cd8d862b158c6cb6bb8f056360fdad1c3a86bf1', '2026-05-17 14:58:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-17 20:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `saved_jobs`
--

CREATE TABLE `saved_jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `saved_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_jobs`
--

INSERT INTO `saved_jobs` (`id`, `user_id`, `job_id`, `saved_at`) VALUES
(12, 23, 6, '2026-04-14 22:26:38'),
(13, 23, 10, '2026-04-15 18:43:30'),
(14, 23, 7, '2026-04-15 18:43:39');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_certifications`
--

CREATE TABLE `seeker_certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `cert_name` varchar(255) NOT NULL,
  `issuing_org` varchar(255) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `no_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_certifications`
--

INSERT INTO `seeker_certifications` (`id`, `user_id`, `cert_name`, `issuing_org`, `issue_date`, `expiry_date`, `no_expiry`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(10, 1, 'dwad', 'dawdawd', NULL, NULL, 1, 'dwa', 0, '2026-04-04 09:08:32', '2026-04-04 09:08:32');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_education`
--

CREATE TABLE `seeker_education` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `education_level` enum('elementary','junior_high','senior_high','college') NOT NULL DEFAULT 'college',
  `school_name` varchar(255) DEFAULT NULL,
  `degree_course` varchar(255) DEFAULT NULL,
  `start_year` year(4) DEFAULT NULL,
  `end_year` year(4) DEFAULT NULL,
  `graduation_date` date DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `no_schooling` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_education`
--

INSERT INTO `seeker_education` (`id`, `user_id`, `education_level`, `school_name`, `degree_course`, `start_year`, `end_year`, `graduation_date`, `remarks`, `no_schooling`, `sort_order`, `created_at`, `updated_at`) VALUES
(12, 1, 'college', 'dawdaw', 'awdawdaw', '1991', '1990', NULL, NULL, 0, 0, '2026-04-04 09:08:32', '2026-04-04 09:08:32');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_experience`
--

CREATE TABLE `seeker_experience` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `job_title` varchar(200) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_experience`
--

INSERT INTO `seeker_experience` (`id`, `user_id`, `company_name`, `job_title`, `start_date`, `end_date`, `is_current`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(13, 1, 'dawdawd', 'dawdaw', NULL, NULL, 1, 'dawdawd', 0, '2026-04-04 09:08:32', '2026-04-04 09:08:32'),
(14, 23, 'Asurion', 'Web Development Intern', '0000-00-00', '0000-00-00', 0, NULL, 0, '2026-04-06 17:48:07', '2026-04-06 17:48:07');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_languages`
--

CREATE TABLE `seeker_languages` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(100) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_languages`
--

INSERT INTO `seeker_languages` (`id`, `user_id`, `language_name`, `sort_order`, `created_at`) VALUES
(9, 1, 'Mandarin', 0, '2026-04-04 09:08:32');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_profiles`
--

CREATE TABLE `seeker_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `headline` varchar(200) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `desired_position` varchar(200) DEFAULT NULL,
  `professional_summary` text DEFAULT NULL,
  `show_in_people_search` tinyint(1) NOT NULL DEFAULT 1,
  `experience_level` varchar(100) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `region_code` varchar(20) DEFAULT NULL,
  `region_name` varchar(100) DEFAULT NULL,
  `province_code` varchar(20) DEFAULT NULL,
  `province_name` varchar(100) DEFAULT NULL,
  `city_code` varchar(20) DEFAULT NULL,
  `city_name` varchar(100) DEFAULT NULL,
  `barangay_code` varchar(20) DEFAULT NULL,
  `barangay_name` varchar(100) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `github_url` varchar(500) DEFAULT NULL,
  `portfolio_url` varchar(500) DEFAULT NULL,
  `other_url` varchar(500) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `nr_availability` varchar(100) DEFAULT NULL,
  `nr_work_types` varchar(255) DEFAULT NULL,
  `nr_locations` text DEFAULT NULL,
  `nr_right_to_work` varchar(255) DEFAULT NULL,
  `nr_salary` varchar(100) DEFAULT NULL,
  `nr_salary_period` varchar(50) DEFAULT NULL,
  `nr_classification` varchar(255) DEFAULT NULL,
  `nr_approachability` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_profiles`
--

INSERT INTO `seeker_profiles` (`id`, `user_id`, `phone`, `headline`, `industry`, `bio`, `desired_position`, `professional_summary`, `show_in_people_search`, `experience_level`, `address_line`, `landmark`, `country_name`, `region_code`, `region_name`, `province_code`, `province_name`, `city_code`, `city_name`, `barangay_code`, `barangay_name`, `linkedin_url`, `github_url`, `portfolio_url`, `other_url`, `banner_url`, `nr_availability`, `nr_work_types`, `nr_locations`, `nr_right_to_work`, `nr_salary`, `nr_salary_period`, `nr_classification`, `nr_approachability`, `created_at`, `updated_at`) VALUES
(1, 1, '+63 wqe12312', 'eqeqw', NULL, 'dfafafw', NULL, NULL, 1, 'Mid-Level (3–5 years)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'eqweqweqwe', NULL, NULL, NULL, 'https://github.com/deyangg', NULL, NULL, 'uploads/banners/banner_1_1775318791.png', 'Now', 'Part-time', 'dawdaw', 'United States — Citizen', '21312', 'Annually', 'Administration & Support — fwefsef', 'Shown', '2026-04-04 09:06:31', '2026-04-04 09:08:32'),
(2, 23, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/banners/banner_23_1775531558.jpg', NULL, NULL, NULL, NULL, NULL, 'per month', NULL, NULL, '2026-04-06 17:48:07', '2026-04-06 20:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_resumes`
--

CREATE TABLE `seeker_resumes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_resumes`
--

INSERT INTO `seeker_resumes` (`id`, `user_id`, `original_filename`, `stored_filename`, `file_path`, `file_size`, `mime_type`, `is_active`, `uploaded_at`) VALUES
(1, 1, 'Black and White Minimalist Accountant Resume.pdf', 'resume_1_1775318857.pdf', 'uploads/resumes/resume_1_1775318857.pdf', 40690, 'application/pdf', 1, '2026-04-04 09:07:38'),
(2, 23, 'Activity-6-MongoDB.pdf', 'resume_23_1775522903.pdf', 'uploads/resumes/resume_23_1775522903.pdf', 63438, 'application/pdf', 1, '2026-04-06 17:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_skills`
--

CREATE TABLE `seeker_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `skill_level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Intermediate',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeker_skills`
--

INSERT INTO `seeker_skills` (`id`, `user_id`, `skill_name`, `skill_level`, `sort_order`, `created_at`) VALUES
(14, 1, 'PHP', 'Intermediate', 0, '2026-04-04 09:08:32');

-- --------------------------------------------------------

--
-- Table structure for table `social_accounts`
--

CREATE TABLE `social_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `provider` enum('google','linkedin') NOT NULL,
  `provider_user_id` varchar(255) NOT NULL,
  `provider_email` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `account_type` enum('seeker','employer','admin','recruiter') NOT NULL DEFAULT 'seeker',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `contact` varchar(30) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `account_type`, `is_verified`, `is_active`, `must_change_password`, `contact`, `company_name`, `avatar_url`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'dollanodea10@gmail.com', '$2y$10$M24b2K93aqbsSm3pELpvLeOUTr60DlHFa/ew3C9jW.IBqeGENvjV2', 'Dea Dollano', 'seeker', 0, 1, 0, '+639619557612', NULL, 'uploads/avatars/avatar_1_1775292408.png', '2026-04-17 11:43:17', '2026-03-21 04:11:21', '2026-04-17 11:43:17'),
(2, 'deadae@gmail.com', '$2y$10$5YzRy/WYDGjyNuBYw6Oc5u5QKWvNs4X0zjuW9SRPFOEwQpbzO2fPq', 'Dea Dollano', 'employer', 0, 1, 0, '+639619557612', 'deyangcorp', NULL, '2026-04-15 18:52:29', '2026-03-21 08:40:54', '2026-04-15 18:52:29'),
(3, 'dryaleve11502@gmail.com', '$2y$10$fQHnJPIU6.v/W1qU8UFLHOQHQdpaqXKbz5alM3Q/6TftPFQoGX6H6', 'Rye Del Rosario', 'seeker', 0, 1, 0, '09685990044', NULL, NULL, '2026-03-21 10:40:47', '2026-03-21 08:45:24', '2026-03-21 10:40:47'),
(4, 'dollano10@gmail.com', '$2y$10$AhHJjR/eABvN/0LQtJeY1.HNTBSr4W3Uv2H/euKxrpO/dwJ9vs8am', 'Dea Dollano', 'seeker', 0, 1, 0, '+639619557612', NULL, NULL, NULL, '2026-03-21 09:33:30', '2026-03-21 09:33:30'),
(5, 'boaz@gmail.com', '$2y$10$b1kOg84NSEw7F/Tg0.iVn.AVfqu0hy3ShGmQv.7pmE7xnBuxlY8Z.', 'boaz del rosario', 'seeker', 1, 1, 0, '09876543212', NULL, NULL, '2026-03-21 22:30:52', '2026-03-21 10:44:23', '2026-03-21 22:30:52'),
(6, 'asurionph@gmail.com', '$2y$10$lQ5tqkZ5PZr.W6aOJw1rA.VY7TytzEoX1VT6qgjAIeyZaIxAsAmqS', 'Dada Cortez', 'employer', 1, 1, 0, '09176934043', 'Asurion', NULL, NULL, '2026-03-21 10:46:32', '2026-03-21 10:46:32'),
(7, 'admin@antcareers.com', '$2y$10$gnsV2xapLuCbX.ElmoPPzum5e52jYCKmdIGE/WVgO5ZgWpkClql5q', 'AntCareers Admin', 'admin', 1, 1, 0, NULL, NULL, NULL, '2026-03-23 01:22:52', '2026-03-21 12:08:15', '2026-03-23 01:22:52'),
(8, 'larry@gmail.com', '$2y$10$usG2rfaZ5DdeuI044ZDrk.kqy1IROfRP2m6HsEVbFa47sVxXoG8yW', 'larry salamat', 'seeker', 1, 1, 0, '09669625492', NULL, NULL, NULL, '2026-03-21 13:36:35', '2026-03-21 13:36:35'),
(9, 'vgmalolos@victory.com', '$2y$10$BFM2F4SQ2p7D6bMfHYn9z.j.Hn6ZUOvz/zcX7jcmp/4XNRo7be.tG', 'ella cortez', 'employer', 1, 1, 0, '09876543212', 'vgmalolos', NULL, NULL, '2026-03-21 13:38:53', '2026-03-21 13:38:53'),
(10, 'rey@gmail.com', '$2y$10$bJXEyLsIH/r85ba.UdYWLuDNIvPhmiq1/w4570/Wp1Jn9IBQTPxA6', 'Rey Carlos', 'seeker', 1, 1, 0, '09876543213', NULL, NULL, '2026-03-21 22:38:39', '2026-03-21 22:29:30', '2026-03-21 22:38:39'),
(11, 'Medranacorp@yahoo.com', '$2y$10$lmhmkmdL8eb88dIVE7fOmeMQRg9SP5tThizfznJY4shTRHBhWvEua', 'Juan Medrana', 'employer', 1, 1, 0, '09876543214', 'MedranaGroup', NULL, '2026-03-21 22:35:35', '2026-03-21 22:32:36', '2026-03-21 22:35:35'),
(12, 'anne@gmail.com', '$2y$10$Qa9/mIex9ncFl2L8yER8DuELvdExFsc7dz.ywp7m4X7tca6FfCSF.', 'anne dionisio', 'seeker', 1, 1, 0, '0976543215', NULL, NULL, '2026-04-17 11:52:38', '2026-03-23 01:10:57', '2026-04-17 11:52:38'),
(13, 'globe@corp.com', '$2y$10$iFD0Ec95CGuQQijA0c7pzuYktWD1crhwsmXJ9zA1L/IDpUqAb4Mhy', 'lem castro', 'employer', 1, 1, 0, '09876543218', 'Globe', NULL, NULL, '2026-03-23 01:14:05', '2026-03-23 01:14:05'),
(16, 'ariannepauleneaspiras@gmail.com', '$2y$10$U9IvBn9Ib2PuW.UTjCy.MejiBO3giDxcTyW1mlIyy4nsuL0G1zYJO', 'ARIANNE ASPIRAS', 'seeker', 1, 1, 0, '+639321046250', NULL, NULL, NULL, '2026-03-24 01:32:38', '2026-03-24 01:32:38'),
(17, 'acme@gmail.com', '$2y$10$I/NYmACgJL.kvEG56fDLIeDLWZEDAetnlxq3sZq7SdTjAmrFZs9Qm', 'ARIANNE ASPIRAS', 'employer', 1, 1, 0, '+639323107907', 'AcmeCorp', NULL, NULL, '2026-03-24 01:40:08', '2026-03-24 01:40:08'),
(18, 'dollanodeys10@gmail.com', '$2y$10$GnLij/7dO85OcEvrY7Inh.9vtvnk3aEx1kJ51T6T9/kbw4jlamp22', 'Dea Dollano', 'seeker', 1, 1, 0, '+639619557612', NULL, NULL, NULL, '2026-03-25 02:37:26', '2026-03-25 02:37:26'),
(19, 'deydey@gmail.com', '$2y$10$ynI6HCJ05lfVIRJ02djjZuduv4u576KrfkVD7XwlY2hnDKe3H3NF2', 'Dea Cassandra Dollano', 'seeker', 1, 1, 0, '092024184629', NULL, NULL, NULL, '2026-03-25 03:50:31', '2026-03-25 03:50:31'),
(20, 'riot@gmail.com', '$2y$10$4f07aeRnbTg6XgJJno6mhOKPfR.oVyTLifiKWg8coSETinsgSa.f6', 'Mark Reeze Maniego', 'employer', 1, 1, 0, '63 0923 928 7754', 'Riot Games', NULL, NULL, '2026-03-29 03:23:13', '2026-03-29 03:23:13'),
(21, 'mark@gmail.com', '$2y$10$XTiRfvhIhPgqbp4w1JuV2.mVQ0FUvZ88QG1WqPi6JxcWp4z8lWkCq', 'Mark Santos', 'seeker', 1, 1, 0, '12345678', NULL, NULL, '2026-04-10 07:56:14', '2026-03-29 03:48:49', '2026-04-10 07:56:14'),
(22, 'asd@mail.com', '$2y$10$5G7YlyPLWmO2fJNM0MYjYO0FS2fdsk6bvyBSgqljhaCu4ZU8XVTn.', 'Mark Sigma', 'employer', 1, 1, 0, '1231231231321', 'adsadasdsa', NULL, NULL, '2026-03-30 07:21:19', '2026-03-30 07:21:19'),
(23, 'rye@gmail.com', '$2y$10$5UJ1UZEDso2l9iGEUAINg.rht9a7K1L55465fubEzvhs9va7v2wCO', 'rye del rosario', 'seeker', 1, 1, 0, '09876543212', NULL, 'uploads/avatars/avatar_23_1775522944.jpg', '2026-04-17 02:33:43', '2026-03-30 21:49:38', '2026-04-17 02:33:43'),
(24, 'ryalcorp@gmail.com', '$2y$10$IalyXI6vBJj.JkH2RRb9tuU8p0PI6t46yI2Hw6XoIX6yxhVyeIF8W', 'Ryal Eve', 'employer', 1, 1, 0, '09876543213', 'RyalCorp', NULL, NULL, '2026-03-30 21:53:39', '2026-03-30 21:53:39'),
(25, 'asd@gmail.com', '$2y$10$Y/SrlWSQ5uqpfm1wdX7daeE.hF9P2iH1kek4zvrbr4jilaJqSXZZi', 'asdasd asdasdas', 'employer', 1, 1, 0, '123123123123123', 'asdasd', NULL, '2026-04-16 20:06:46', '2026-03-31 02:13:31', '2026-04-16 20:06:46'),
(26, 'ryerye@gmail.com', '$2y$10$73O4jx09sotgwPeQFqBf8eshWRUyCuC3Jl6tBMSesSMYwnU1tjURm', 'rye cortez', 'seeker', 1, 1, 0, '09685990044', NULL, NULL, NULL, '2026-03-31 02:15:39', '2026-03-31 02:15:39'),
(27, 'ryecorp@gmail.com', '$2y$10$LkqO.aFFwWq5LaGOCxI4refhiHCxMk6tz8dZvZVRkSpKvN0Wv6Xym', 'ryal eve', 'employer', 1, 1, 0, '09876543214', 'ryepagodna', NULL, '2026-04-17 20:58:58', '2026-03-31 02:37:51', '2026-04-17 20:58:58'),
(28, 'employer@antcareers.test', '$2y$12$PlaceholderHashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'Maria Santos', 'employer', 1, 1, 0, NULL, 'TechNova PH', NULL, NULL, '2026-04-02 02:41:03', '2026-04-02 02:41:03'),
(29, 'seeker@antcareers.test', '$2y$12$PlaceholderHashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'Juan dela Cruz', 'seeker', 1, 1, 0, NULL, NULL, NULL, NULL, '2026-04-02 02:41:03', '2026-04-02 02:41:03'),
(32, 'mark1@gmail.com', '$2y$10$XTpx6oOtfKDYm6WxgFpRXeFrzms79ZPmw/GJh9E3L8PUG0ptWE1Qq', 'mark admin', 'employer', 1, 1, 0, '3123123123', 'adsadmar', NULL, '2026-04-11 02:44:35', '2026-04-10 03:59:46', '2026-04-11 02:44:35'),
(33, 'd.dollano@deyangcorp.work', '$2y$10$5cxo12uDzUHwN2BqElwKOu2wZ734SUmWfrsxfhibShF8LwgHrmt.2', 'Dea Dollano', 'recruiter', 0, 1, 0, NULL, NULL, NULL, '2026-04-17 19:13:35', '2026-04-15 11:26:26', '2026-04-17 19:13:35'),
(35, 'reyal@gmail.com', '$2y$10$BkpAXd/X8pw3BvtQWv4Gr.AZO71u6e8aVfh2aNrV/s6pIiOaqNRs6', 'reyal ib', 'seeker', 1, 1, 0, '09876543212', NULL, NULL, '2026-04-17 00:01:56', '2026-04-16 20:07:35', '2026-04-17 00:01:56'),
(38, 'a.dionisio@ryepagodna.work', '$2y$10$C9DzeB.iISQSltoCsQX5puCY4GlpN82D7j5ZLKetrc6w7VraqLD3y', 'Anne Dionisio', 'recruiter', 0, 1, 0, NULL, NULL, NULL, '2026-04-17 00:08:07', '2026-04-17 00:05:35', '2026-04-17 00:08:19');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `email_new_message` tinyint(1) NOT NULL DEFAULT 1,
  `email_application_status` tinyint(1) NOT NULL DEFAULT 1,
  `email_interview_invite` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_application` (`job_id`,`seeker_id`),
  ADD KEY `idx_app_job` (`job_id`),
  ADD KEY `idx_app_seeker` (`seeker_id`),
  ADD KEY `idx_app_status` (`status`);

--
-- Indexes for table `company_follows`
--
ALTER TABLE `company_follows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_follow` (`follower_user_id`,`employer_user_id`);

--
-- Indexes for table `company_profiles`
--
ALTER TABLE `company_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_user` (`user_id`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conversation_key` (`conversation_key`);

--
-- Indexes for table `hired_credentials`
--
ALTER TABLE `hired_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hired_application` (`application_id`),
  ADD KEY `idx_hired_seeker` (`seeker_id`),
  ADD KEY `idx_hired_company` (`company_id`),
  ADD KEY `fk_hired_recruiter_user` (`recruiter_user_id`);

--
-- Indexes for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_interview_app` (`application_id`),
  ADD KEY `idx_interview_employer` (`employer_id`),
  ADD KEY `idx_interview_seeker` (`seeker_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_employer` (`employer_id`),
  ADD KEY `idx_job_status` (`status`),
  ADD KEY `idx_jobs_recruiter` (`recruiter_id`),
  ADD KEY `idx_jobs_approval` (`approval_status`);
ALTER TABLE `jobs` ADD FULLTEXT KEY `ft_jobs_search` (`title`,`description`,`skills_required`);

--
-- Indexes for table `job_invitations`
--
ALTER TABLE `job_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invite` (`job_id`,`jobseeker_id`),
  ADD KEY `idx_inv_job` (`job_id`),
  ADD KEY `idx_inv_recruiter` (`recruiter_id`),
  ADD KEY `idx_inv_seeker` (`jobseeker_id`),
  ADD KEY `idx_inv_status` (`status`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_email` (`email`),
  ADD KEY `idx_attempts_ip` (`ip_address`),
  ADD KEY `idx_attempts_time` (`attempted_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_sender` (`sender_id`),
  ADD KEY `idx_msg_receiver` (`receiver_id`),
  ADD KEY `idx_msg_read` (`is_read`),
  ADD KEY `idx_msg_conversation` (`conversation_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`is_read`),
  ADD KEY `idx_notif_type` (`type`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reset_token` (`token_hash`),
  ADD KEY `idx_reset_user` (`user_id`),
  ADD KEY `idx_reset_expiry` (`expires_at`);

--
-- Indexes for table `recruiters`
--
ALTER TABLE `recruiters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_recruiter_user` (`user_id`),
  ADD KEY `idx_recruiter_company` (`company_id`),
  ADD KEY `idx_recruiter_employer` (`employer_id`),
  ADD KEY `idx_recruiter_active` (`is_active`);

--
-- Indexes for table `recruiter_profiles`
--
ALTER TABLE `recruiter_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rec_profile_user` (`user_id`);

--
-- Indexes for table `recruiter_stats`
--
ALTER TABLE `recruiter_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stats_recruiter` (`recruiter_id`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_token` (`token_hash`),
  ADD KEY `idx_remember_user` (`user_id`),
  ADD KEY `idx_remember_expiry` (`expires_at`);

--
-- Indexes for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_saved` (`user_id`,`job_id`),
  ADD KEY `fk_saved_job` (`job_id`);

--
-- Indexes for table `seeker_certifications`
--
ALTER TABLE `seeker_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cert_user` (`user_id`);

--
-- Indexes for table `seeker_education`
--
ALTER TABLE `seeker_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_edu_user` (`user_id`);

--
-- Indexes for table `seeker_experience`
--
ALTER TABLE `seeker_experience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exp_user` (`user_id`);

--
-- Indexes for table `seeker_languages`
--
ALTER TABLE `seeker_languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lang_user` (`user_id`,`language_name`),
  ADD KEY `idx_lang_user` (`user_id`);

--
-- Indexes for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seeker_user` (`user_id`);

--
-- Indexes for table `seeker_resumes`
--
ALTER TABLE `seeker_resumes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resume_user` (`user_id`),
  ADD KEY `idx_resume_active` (`is_active`);

--
-- Indexes for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_skill_user` (`user_id`);

--
-- Indexes for table `social_accounts`
--
ALTER TABLE `social_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_social_provider` (`provider`,`provider_user_id`),
  ADD KEY `idx_social_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_type` (`account_type`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `company_follows`
--
ALTER TABLE `company_follows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `company_profiles`
--
ALTER TABLE `company_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92117;

--
-- AUTO_INCREMENT for table `hired_credentials`
--
ALTER TABLE `hired_credentials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `job_invitations`
--
ALTER TABLE `job_invitations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=464;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recruiters`
--
ALTER TABLE `recruiters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recruiter_profiles`
--
ALTER TABLE `recruiter_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `recruiter_stats`
--
ALTER TABLE `recruiter_stats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=407;

--
-- AUTO_INCREMENT for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `seeker_certifications`
--
ALTER TABLE `seeker_certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `seeker_education`
--
ALTER TABLE `seeker_education`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `seeker_experience`
--
ALTER TABLE `seeker_experience`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `seeker_languages`
--
ALTER TABLE `seeker_languages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seeker_resumes`
--
ALTER TABLE `seeker_resumes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `social_accounts`
--
ALTER TABLE `social_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_app_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_seeker` FOREIGN KEY (`seeker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `company_profiles`
--
ALTER TABLE `company_profiles`
  ADD CONSTRAINT `fk_company_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hired_credentials`
--
ALTER TABLE `hired_credentials`
  ADD CONSTRAINT `fk_hired_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hired_recruiter_user` FOREIGN KEY (`recruiter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hired_seeker` FOREIGN KEY (`seeker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD CONSTRAINT `fk_interview_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interview_employer` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interview_seeker` FOREIGN KEY (`seeker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `fk_job_employer` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recruiters`
--
ALTER TABLE `recruiters`
  ADD CONSTRAINT `fk_recruiter_company` FOREIGN KEY (`company_id`) REFERENCES `company_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recruiter_employer` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recruiter_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recruiter_profiles`
--
ALTER TABLE `recruiter_profiles`
  ADD CONSTRAINT `fk_rec_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `recruiter_stats`
--
ALTER TABLE `recruiter_stats`
  ADD CONSTRAINT `fk_stats_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD CONSTRAINT `fk_saved_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_certifications`
--
ALTER TABLE `seeker_certifications`
  ADD CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_education`
--
ALTER TABLE `seeker_education`
  ADD CONSTRAINT `fk_edu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_experience`
--
ALTER TABLE `seeker_experience`
  ADD CONSTRAINT `fk_exp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_languages`
--
ALTER TABLE `seeker_languages`
  ADD CONSTRAINT `fk_lang_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  ADD CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_resumes`
--
ALTER TABLE `seeker_resumes`
  ADD CONSTRAINT `fk_resume_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `seeker_skills`
--
ALTER TABLE `seeker_skills`
  ADD CONSTRAINT `fk_skill_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `social_accounts`
--
ALTER TABLE `social_accounts`
  ADD CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
