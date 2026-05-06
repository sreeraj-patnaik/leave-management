<?php
require __DIR__ . '/../config/db.php';

$email = 'student1@liet.edu';   // change when needed
$password = 'Student@123';

// Check if user exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found.";
    exit;
}

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update password
$update = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
$update->execute([$hash, $email]);

echo "Password updated successfully.";
?>