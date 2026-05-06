# Leave Management

A secure, role-based leave management system built with PHP and MySQL.

## Features

- Secure authentication with hashed passwords and session hardening
- Student leave application with leave-type support and improved validation
- Approver workflow for faculty, HOD, and principal with CSRF-protected actions
- Approval logs for transparency and audits
- College-inspired, responsive UI styling
- Prepared SQL statements for database safety

## Setup

1. Place the folder under your web server document root.
2. Create a MySQL database named `leave_management`.
3. Import `schema.sql` to create the recommended tables and columns.
4. Update database credentials in `config/db.php` as needed.
5. Ensure your `users` table includes `role`, `department`, and `assigned_faculty_id` fields.
6. Open `/leave-management/auth/login.php` in your browser.

## Notes

- Existing users must have hashed passwords before login.
- Use `APP_ROOT` in `config/db.php` if the application is hosted in a different folder.
