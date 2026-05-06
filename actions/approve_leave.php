<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['faculty', 'hod', 'principal']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../approver/dashboard.php");
    exit;
}

$leaveId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$leaveId || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['error'] = "Invalid input.";
    header("Location: ../approver/dashboard.php");
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM leaves WHERE id = ? AND current_approver_id = ?');
$stmt->execute([$leaveId, $current_user['id']]);
$leave = $stmt->fetch();

if (!$leave) {
    $_SESSION['error'] = "Unauthorized or invalid leave.";
    header("Location: ../approver/dashboard.php");
    exit;
}

$pdo->beginTransaction();

try {
    $status = ($action === 'reject') ? 'rejected' : 'approved';

    // UPDATE LEAVE
    $update = $pdo->prepare('UPDATE leaves SET status = ?, current_approver_id = NULL WHERE id = ?');
    $update->execute([$status, $leaveId]);

    // LOG ACTION
    $log = $pdo->prepare('INSERT INTO approval_log (leave_id, approved_by, role, status)
                          VALUES (?, ?, ?, ?)');
    $log->execute([$leaveId, $current_user['id'], $current_user['role'], $status]);

    $pdo->commit();

    $_SESSION['success'] = "Leave successfully " . strtoupper($status) . ".";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Something went wrong.";
}

header("Location: ../approver/dashboard.php");
exit;
?>