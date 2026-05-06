CREATE DATABASE IF NOT EXISTS `leave_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `leave_management`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `regd_no` VARCHAR(30) DEFAULT NULL UNIQUE,
  `emp_id` VARCHAR(30) DEFAULT NULL UNIQUE,
  `role` ENUM('student','faculty','hod','principal') NOT NULL DEFAULT 'student',
  `department` VARCHAR(100) DEFAULT NULL,
  `assigned_faculty_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_users_department` (`department`),
  INDEX `idx_users_status` (`status`),
  FOREIGN KEY (`assigned_faculty_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leaves` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `leave_type` VARCHAR(50) NOT NULL DEFAULT 'Casual',
  `from_date` DATE NOT NULL,
  `to_date` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `current_approver_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_leaves_user` (`user_id`),
  INDEX `idx_leaves_approver` (`current_approver_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`current_approver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `approval_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_id` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `role` ENUM('student','faculty','hod','principal') NOT NULL,
  `status` ENUM('approved','rejected') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_approval_leave` (`leave_id`),
  INDEX `idx_approval_by` (`approved_by`),
  FOREIGN KEY (`leave_id`) REFERENCES `leaves`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

