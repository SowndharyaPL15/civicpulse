-- ============================================================
-- CivicPulse Database Schema
-- Database: otp_verification
-- ============================================================

CREATE DATABASE IF NOT EXISTS `otp_verification`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `otp_verification`;

-- ============================================================
-- DEPARTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `dept_id` INT AUTO_INCREMENT PRIMARY KEY,
  `dept_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed departments
INSERT IGNORE INTO `departments` (`dept_id`, `dept_name`) VALUES
(1, 'Road'),
(2, 'Sanitation'),
(3, 'Electricity'),
(4, 'Water'),
(5, 'Drainage');

-- ============================================================
-- ISSUE TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS `issue_types` (
  `type_id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_name` VARCHAR(150) NOT NULL,
  `dept_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dept_id`) REFERENCES `departments`(`dept_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed issue types
INSERT IGNORE INTO `issue_types` (`type_id`, `issue_name`, `dept_id`) VALUES
(1, 'Pothole', 1),
(2, 'Road Damage', 1),
(3, 'Speed Breaker Issue', 1),
(4, 'Garbage Accumulation', 2),
(5, 'Waste Not Collected', 2),
(6, 'Open Dumping', 2),
(7, 'Streetlight Not Working', 3),
(8, 'Power Outage', 3),
(9, 'Exposed Wiring', 3),
(10, 'Water Leakage', 4),
(11, 'No Water Supply', 4),
(12, 'Contaminated Water', 4),
(13, 'Blocked Drain', 5),
(14, 'Overflowing Drain', 5),
(15, 'Sewage Leak', 5);

-- ============================================================
-- USERS (Citizens)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user` (
  `uid` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `otp` VARCHAR(10) DEFAULT NULL,
  `activation_code` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'inactive',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- ADMIN (Department Admins)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `dept_id` INT DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 0,
  `temp_pass` TINYINT(1) DEFAULT 1,
  `otp` VARCHAR(10) DEFAULT NULL,
  `otp_expiry` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dept_id`) REFERENCES `departments`(`dept_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- COMPLAINTS (Citizen-submitted reports)
-- ============================================================
CREATE TABLE IF NOT EXISTS `complaints` (
  `cid` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `issue_type_id` INT NOT NULL,
  `description` TEXT NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `latitude` DOUBLE DEFAULT NULL,
  `longitude` DOUBLE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `priority` ENUM('LOW','MEDIUM','HIGH') DEFAULT 'LOW',
  `issue_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user`(`uid`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`dept_id`) ON DELETE CASCADE,
  FOREIGN KEY (`issue_type_id`) REFERENCES `issue_types`(`type_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ISSUES (Grouped/clustered complaints by AI)
-- ============================================================
CREATE TABLE IF NOT EXISTS `issues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_title` VARCHAR(255) NOT NULL,
  `department_id` INT DEFAULT NULL,
  `issue_type_id` INT DEFAULT NULL,
  `complaint_count` INT DEFAULT 1,
  `priority` ENUM('LOW','MEDIUM','HIGH') DEFAULT 'LOW',
  `status` ENUM('Open','In Progress','Resolved') DEFAULT 'Open',
  `latitude` DOUBLE DEFAULT NULL,
  `longitude` DOUBLE DEFAULT NULL,
  `assigned_worker_id` INT DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`dept_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- WORKERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `workers` (
  `worker_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `department_id` INT NOT NULL,
  `status` ENUM('Available','Busy') DEFAULT 'Available',
  `latitude` DOUBLE DEFAULT NULL,
  `longitude` DOUBLE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`dept_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- WORK ASSIGNMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `work_assignments` (
  `assignment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT NOT NULL,
  `worker_id` INT NOT NULL,
  `assigned_by` INT DEFAULT NULL,
  `status` ENUM('Assigned','In Progress','Completed') DEFAULT 'Assigned',
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`issue_id`) REFERENCES `issues`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`worker_id`) REFERENCES `workers`(`worker_id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ISSUE CONFIRMATIONS ("I also face this")
-- ============================================================
CREATE TABLE IF NOT EXISTS `issue_confirmations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_confirmation` (`issue_id`, `user_id`),
  FOREIGN KEY (`issue_id`) REFERENCES `issues`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `user`(`uid`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ISSUE UPDATES (Timeline entries for tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `issue_updates` (
  `update_id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT NOT NULL,
  `update_message` TEXT NOT NULL,
  `updated_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`issue_id`) REFERENCES `issues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ISSUE STATUS HISTORY (Audit trail of status changes)
-- ============================================================
CREATE TABLE IF NOT EXISTS `issue_status_history` (
  `history_id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT NOT NULL,
  `old_status` VARCHAR(30) DEFAULT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`issue_id`) REFERENCES `issues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
