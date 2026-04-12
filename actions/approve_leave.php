<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['faculty', 'hod', 'principal']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('/approver/dashboard.php');
}

$leaveId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$leaveId || !in_array($action, ['approve', 'reject'], true)) {
    redirect('/approver/dashboard.php');
}

$stmt = $pdo->prepare('SELECT * FROM leaves WHERE id = ? AND current_approver_id = ?');
$stmt->execute([$leaveId, $current_user['id']]);
$leave = $stmt->fetch();

if (!$leave) {
    redirect('/approver/dashboard.php');
}

$pdo->beginTransaction();

try {
    if ($action === 'reject') {
        $update = $pdo->prepare('UPDATE leaves SET status = ?, current_approver_id = NULL WHERE id = ?');
        $update->execute(['rejected', $leaveId]);
        $logStatus = 'rejected';
    } else {
        $update = $pdo->prepare('UPDATE leaves SET status = ?, current_approver_id = NULL WHERE id = ?');
        $update->execute(['approved', $leaveId]);
        $logStatus = 'approved';
    }

    $log = $pdo->prepare('INSERT INTO approval_log (leave_id, approved_by, role, status) VALUES (?, ?, ?, ?)');
    $log->execute([$leaveId, $current_user['id'], $current_user['role'], $logStatus]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

redirect('/approver/dashboard.php');
?>