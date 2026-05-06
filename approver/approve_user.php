<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['hod']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('/approver/user_approvals.php');
}

$userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$userId || !in_array($action, ['approve', 'reject'], true)) {
    redirect('/approver/user_approvals.php');
}

$status = $action === 'approve' ? 'approved' : 'rejected';

// Only allow HOD to approve users from their department
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND department = ? AND status = ?');
$stmt->execute([$userId, $current_user['department'], 'pending']);
$target = $stmt->fetch();

if (!$target) {
    redirect('/approver/user_approvals.php');
}

$update = $pdo->prepare('UPDATE users SET status = ?, approved_by = ? WHERE id = ?');
$update->execute([$status, $current_user['id'], $userId]);

redirect('/approver/user_approvals.php');
