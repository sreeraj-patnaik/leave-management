<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('/leave_management_system/auth/login.php');
}

$error = '';
$success = '';
$needs_forced_change = mustChangePassword();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$needs_forced_change && empty($current_password)) {
        $errors[] = 'Current password is required.';
    }

    if (empty($errors) && !$needs_forced_change) {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    $password_errors = validatePasswordStrength($new_password);
    if (!empty($password_errors)) {
        $errors = array_merge($errors, $password_errors);
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($new_password, $user['password'])) {
            $errors[] = 'New password must be different from the current password.';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION['user_id']);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $_SESSION['must_change_password'] = 0;
            session_regenerate_id(true);
            setFlashMessage('success', 'Password updated successfully.');

            redirect(getDashboardPathForRole($_SESSION['role']));
        } else {
            $errors[] = 'Failed to update password.';
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($errors)) {
        $error = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Leave Management System</title>
    <link rel="stylesheet" href="/leave_management_system/css/style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="https://lendi.edu.in/assets/img/lendi-full-logo.png" alt="Lendi Institute of Engineering & Technology" class="site-logo login-logo">
                <h1><?php echo $needs_forced_change ? 'Set New Password' : 'Change Password'; ?></h1>
                <p>Leave Management System</p>
            </div>

            <?php if ($error): ?>
            <div class="flash-message flash-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <?php if (!$needs_forced_change): ?>
                <div class="form-group">
                    <label for="current_password" class="required">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <?php else: ?>
                <div class="flash-message flash-warning">
                    You are using a temporary password. Please set a new one to continue.
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="new_password" class="required">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                    <div class="form-hint">Minimum 8 characters with uppercase, lowercase, number, and special character.</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="required">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>

            <div class="login-footer">
                <p><a href="/leave_management_system/auth/logout.php">Logout</a></p>
            </div>
        </div>
    </div>
</body>
</html>
