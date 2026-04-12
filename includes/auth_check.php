<?php
if (empty($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

$stmt = $pdo->prepare('SELECT id, email, role, name, department FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_unset();
    session_destroy();
    redirect('/auth/login.php');
}

$_SESSION['role'] = $current_user['role'];
$_SESSION['name'] = $current_user['name'] ?? $current_user['email'];

function require_role(array $allowedRoles): void
{
    global $current_user;

    if (!in_array($current_user['role'], $allowedRoles, true)) {
        redirect('/');
    }
}
?>