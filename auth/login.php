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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role, department, regd_no, emp_no, designation, student_year, student_section, admin_team, must_change_password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['regd_no'] = $user['regd_no'];
                $_SESSION['emp_no'] = $user['emp_no'];
                $_SESSION['designation'] = $user['designation'];
                $_SESSION['student_year'] = $user['student_year'];
                $_SESSION['student_section'] = $user['student_section'];
                $_SESSION['admin_team'] = $user['admin_team'];
                $_SESSION['identifier_no'] = !empty($user['regd_no']) ? $user['regd_no'] : $user['emp_no'];
                $_SESSION['must_change_password'] = (int)$user['must_change_password'];
                session_regenerate_id(true);
                
                // Redirect based on role
                $role = $user['role'];
                if ($user['must_change_password']) {
                    setFlashMessage('warning', 'Please change your password to continue.');
                    redirect('/leave_management_system/auth/change_password.php');
                }
                setFlashMessage('success', 'Welcome back, ' . $user['name'] . '!');
                redirect(getDashboardPathForRole($role));
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
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
    <title>Login - Leave Management System</title>
    <link rel="stylesheet" href="/leave_management_system/css/style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="https://lendi.edu.in/assets/img/lendi-full-logo.png" alt="Lendi Institute of Engineering & Technology" class="site-logo login-logo">
                <h1>Leave Management System</h1>
                <p>Lendi Institute of Engineering & Technology</p>
            </div>
            
            <?php if ($error): ?>
            <div class="flash-message flash-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
