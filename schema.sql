CREATE DATABASE IF NOT EXISTS leave_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE leave_management;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student', 'faculty', 'hod', 'principal', 'admin') NOT NULL,
  department ENUM('cse', 'ece', 'eee', 'mec', 'css', 'cit', 'csm') NOT NULL,
  regd_no VARCHAR(50) NULL,
  emp_no VARCHAR(50) NULL,
  designation VARCHAR(120) NULL,
  student_year TINYINT UNSIGNED NULL,
  student_section VARCHAR(20) NULL,
  admin_team TINYINT(1) NOT NULL DEFAULT 0,
  casual_leave_quota SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  password_changed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_regd_no (regd_no),
  UNIQUE KEY uq_users_emp_no (emp_no),
  KEY idx_users_role_department (role, department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  leave_type ENUM('casual', 'medical', 'on_duty', 'academic', 'vacation') NOT NULL,
  from_date DATE NOT NULL,
  to_date DATE NULL,
  expected_duration VARCHAR(255) NULL,
  reason TEXT NOT NULL,
  proof VARCHAR(255) NULL,
  is_medical TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending', 'approved', 'rejected', 'open', 'closed') NOT NULL DEFAULT 'pending',
  hod_remarks TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leave_user_created (user_id, created_at),
  KEY idx_leave_status_created (status, created_at),
  KEY idx_leave_user_status (user_id, status),
  KEY idx_leave_type (leave_type),
  CONSTRAINT fk_leave_requests_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
