USE leave_management;

INSERT INTO users (
  name,
  email,
  password,
  role,
  department,
  regd_no,
  emp_no,
  designation,
  student_year,
  student_section,
  admin_team,
  casual_leave_quota,
  must_change_password,
  password_changed_at
)
VALUES
(
  'System Admin',
  'admin@lendi.edu.in',
  '$2y$10$zhdVAA3bdyL1IIy7XxDOlONRwp9HES8A.UJxlkoY3AWHn8UZnJ.Tq',
  'admin',
  'css',
  NULL,
  'ADM-001',
  'System Admin',
  NULL,
  NULL,
  1,
  15,
  1,
  NULL
),
(
  'Office Admin',
  'office.admin@lendi.edu.in',
  '$2y$10$zhdVAA3bdyL1IIy7XxDOlONRwp9HES8A.UJxlkoY3AWHn8UZnJ.Tq',
  'admin',
  'css',
  NULL,
  'ADM-002',
  'Office Admin',
  NULL,
  NULL,
  1,
  15,
  1,
  NULL
),
(
  'HR Admin',
  'hr.admin@lendi.edu.in',
  '$2y$10$zhdVAA3bdyL1IIy7XxDOlONRwp9HES8A.UJxlkoY3AWHn8UZnJ.Tq',
  'admin',
  'css',
  NULL,
  'ADM-003',
  'HR Admin',
  NULL,
  NULL,
  1,
  15,
  1,
  NULL
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password = VALUES(password),
  role = VALUES(role),
  department = VALUES(department),
  regd_no = VALUES(regd_no),
  emp_no = VALUES(emp_no),
  designation = VALUES(designation),
  student_year = VALUES(student_year),
  student_section = VALUES(student_section),
  admin_team = VALUES(admin_team),
  casual_leave_quota = VALUES(casual_leave_quota),
  must_change_password = VALUES(must_change_password),
  password_changed_at = VALUES(password_changed_at);

-- Temporary password for all rows above: Admin@123
