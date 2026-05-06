<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (mustChangePassword()) {
        redirect('/leave_management_system/auth/change_password.php');
    }
    redirect(getDashboardPathForRole($_SESSION['role']));
}

$departments = getDepartments($conn);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $regd_no = sanitize($_POST['regd_no'] ?? '');
    $emp_no = sanitize($_POST['emp_no'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!in_array($role, ['student', 'faculty'])) {
        $errors[] = 'Invalid role selected.';
    }
    
    if (empty($department)) {
        $errors[] = 'Department is required.';
    }

    if ($role === 'student' && empty($regd_no)) {
        $errors[] = 'Registration number is required for students.';
    }

    if ($role === 'faculty' && empty($emp_no)) {
        $errors[] = 'Employee number is required for faculty.';
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'Email already registered.';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Insert user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($role === 'student') {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (name, email, password, role, department, regd_no, emp_no, must_change_password, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, NULL, 0, NOW())"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $name,
                $email,
                $hashed_password,
                $role,
                $department,
                $regd_no
            );
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (name, email, password, role, department, regd_no, emp_no, must_change_password, password_changed_at) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, NOW())"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $name,
                $email,
                $hashed_password,
                $role,
                $department,
                $emp_no
            );
        }
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage('success', 'Registration successful! Please login.');
            redirect('login.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Leave Management System</title>
    <link rel="stylesheet" href="/leave_management_system/css/style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="login-box" style="max-width: 500px;">
            <div class="login-header">
                <img src="https://lendi.edu.in/assets/img/lendi-full-logo.png" alt="Lendi Institute of Engineering & Technology" class="site-logo login-logo">
                <h1>Create Account</h1>
                <p>Leave Management System</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="name" class="required">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="role" class="required">Role</label>
                    <select id="role" name="role" class="form-control" required onchange="toggleRollNumber()">
                        <option value="">Select Role</option>
                        <option value="student" <?php echo ($role ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="faculty" <?php echo ($role ?? '') === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="department" class="required">Department</label>
                    <select id="department" name="department" class="form-control" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['code']); ?>" 
                                <?php echo ($department ?? '') === $dept['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="regd-number-group" style="display: none;">
                    <label for="regd_no" class="required">Registration Number</label>
                    <input type="text" id="regd_no" name="regd_no" class="form-control" 
                           value="<?php echo htmlspecialchars($regd_no ?? ''); ?>">
                </div>

                <div class="form-group" id="emp-number-group" style="display: none;">
                    <label for="emp_no" class="required">Employee Number</label>
                    <input type="text" id="emp_no" name="emp_no" class="form-control" 
                           value="<?php echo htmlspecialchars($emp_no ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="required">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            
            <div class="login-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script>
    function toggleRollNumber() {
        const role = document.getElementById('role').value;
        const regdGroup = document.getElementById('regd-number-group');
        const regdInput = document.getElementById('regd_no');
        const empGroup = document.getElementById('emp-number-group');
        const empInput = document.getElementById('emp_no');
        
        if (role === 'student') {
            regdGroup.style.display = 'block';
            regdInput.required = true;
            empGroup.style.display = 'none';
            empInput.required = false;
            empInput.value = '';
        } else if (role === 'faculty') {
            regdGroup.style.display = 'none';
            regdInput.required = false;
            regdInput.value = '';
            empGroup.style.display = 'block';
            empInput.required = true;
        } else {
            regdGroup.style.display = 'none';
            regdInput.required = false;
            regdInput.value = '';
            empGroup.style.display = 'none';
            empInput.required = false;
            empInput.value = '';
        }
    }
    
    // Run on page load
    toggleRollNumber();
    </script>
</body>
</html>
