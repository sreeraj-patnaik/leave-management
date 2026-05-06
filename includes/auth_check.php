<?php
// Ensure session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in -> redirect
if (empty($_SESSION['user_id'])) {
    redirect('/auth/login.php');
    exit;
}

// Fetch user fresh from DB
$stmt = $pdo->prepare(
    'SELECT id, email, role, name, department, regd_no, emp_id, status
     FROM users
     WHERE id = ?'
);

$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// If user not found or not approved -> destroy session
if (!$current_user || ($current_user['status'] ?? 'approved') !== 'approved') {
    session_unset();
    session_destroy();
    redirect('/auth/login.php');
    exit;
}

// Sync session with DB
$_SESSION['role'] = $current_user['role'];
$_SESSION['name'] = $current_user['name'] ?? $current_user['email'];

/**
 * Restrict access to specific roles
 */
function require_role(array $allowedRoles): void
{
    global $current_user;

    if (!in_array($current_user['role'], $allowedRoles, true)) {
        $_SESSION['error'] = 'Unauthorized access.';
        redirect('/');
        exit;
    }
}
?>

