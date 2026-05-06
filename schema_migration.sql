USE leave_management;

-- 1) Normalize department values to the new enum codes.
UPDATE users
SET department = CASE LOWER(TRIM(department))
  WHEN 'cse' THEN 'cse'
  WHEN 'computer science and engineering' THEN 'cse'
  WHEN 'ece' THEN 'ece'
  WHEN 'electronics and communication engineering' THEN 'ece'
  WHEN 'eee' THEN 'eee'
  WHEN 'electrical and electronics engineering' THEN 'eee'
  WHEN 'mec' THEN 'mec'
  WHEN 'mechanical engineering' THEN 'mec'
  WHEN 'css' THEN 'css'
  WHEN 'cit' THEN 'cit'
  WHEN 'computer science and systems' THEN 'csm'
  WHEN 'csm' THEN 'csm'
  ELSE department
END;

-- 2) Remove the old foreign key so the department column can be reshaped.
-- Use the exact foreign key name from SHOW CREATE TABLE users if it differs.
SET @fk_name := (
  SELECT CONSTRAINT_NAME
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'department'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);
SET @sql := IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE users DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Convert the old roll number field into registration number, if needed.
SET @has_roll := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'roll_number'
);
SET @has_regd := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'regd_no'
);
SET @has_emp := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'emp_no'
);
SET @has_designation := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'designation'
);
SET @has_student_year := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'student_year'
);
SET @has_student_section := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'student_section'
);
SET @has_admin_team := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'admin_team'
);
SET @has_quota := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'casual_leave_quota'
    
);

SET @sql := IF(@has_roll = 1 AND @has_regd = 0,
  'ALTER TABLE users CHANGE COLUMN roll_number regd_no VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_emp = 0,
  'ALTER TABLE users ADD COLUMN emp_no VARCHAR(50) NULL AFTER regd_no',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_designation = 0,
  'ALTER TABLE users ADD COLUMN designation VARCHAR(120) NULL AFTER emp_no',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_student_year = 0,
  'ALTER TABLE users ADD COLUMN student_year TINYINT UNSIGNED NULL AFTER designation',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_student_section = 0,
  'ALTER TABLE users ADD COLUMN student_section VARCHAR(20) NULL AFTER student_year',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_admin_team = 0,
  'ALTER TABLE users ADD COLUMN admin_team TINYINT(1) NOT NULL DEFAULT 0 AFTER student_section',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_quota = 0,
  'ALTER TABLE users ADD COLUMN casual_leave_quota SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER emp_no',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_must_change := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'must_change_password'
);
SET @has_changed_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'password_changed_at'
);

SET @sql := IF(@has_must_change = 0,
  'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER emp_no',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_changed_at = 0,
  'ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL DEFAULT NULL AFTER must_change_password',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Update the users table to use the new department enum.
ALTER TABLE users
  MODIFY COLUMN role ENUM('student', 'faculty', 'hod', 'principal', 'admin') NOT NULL,
  MODIFY COLUMN department ENUM('cse', 'ece', 'eee', 'mec', 'css', 'cit', 'csm') NOT NULL;

-- 5) Expand leave request columns for the current workflow.
ALTER TABLE leave_requests
  MODIFY COLUMN leave_type ENUM('casual', 'medical', 'on_duty', 'academic', 'vacation') NOT NULL,
  MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'open', 'closed') NOT NULL DEFAULT 'pending',
  MODIFY COLUMN to_date DATE NULL;

SET @has_is_medical := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leave_requests'
    AND COLUMN_NAME = 'is_medical'
);
SET @has_remarks := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leave_requests'
    AND COLUMN_NAME = 'hod_remarks'
);

SET @sql := IF(@has_is_medical = 0,
  'ALTER TABLE leave_requests ADD COLUMN is_medical TINYINT(1) NOT NULL DEFAULT 0 AFTER proof',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_remarks = 0,
  'ALTER TABLE leave_requests ADD COLUMN hod_remarks TEXT NULL AFTER status',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6) The departments table is no longer used by the current code.
DROP TABLE IF EXISTS departments;
