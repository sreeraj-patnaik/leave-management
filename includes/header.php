<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', '/leave-management');
}

$isAuthenticated = !empty($_SESSION['user_id']);
$currentRole = $_SESSION['role'] ?? '';
$currentName = $_SESSION['name'] ?? $currentRole;

$userIdLabel = '';
$userIdValue = '';
if (isset($current_user) && is_array($current_user)) {
    if (($current_user['role'] ?? '') === 'student') {
        $userIdLabel = 'Regd No';
        $userIdValue = $current_user['regd_no'] ?? '';
    } else {
        $userIdLabel = 'Employee ID';
        $userIdValue = $current_user['emp_id'] ?? '';
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_ROOT ?>/assets/css/app.css" rel="stylesheet">
</head>

<body class="lendi-body">

<!-- TOP UTILITY BAR -->
<div class="utility-bar">
    <div class="container d-flex justify-content-between flex-wrap small">
        <div>Campus: LIET, Vizianagaram</div>
        <div>
            Helpdesk: helpdesk@lendi.edu.in | Contact: 08922-241188
        </div>
    </div>
</div>

<!-- MAIN HEADER -->
<header class="lendi-topbar">
    <div class="container d-flex justify-content-between align-items-center py-3">

        <div class="d-flex align-items-center gap-3">
            <div class="crest">LIET</div>
            <div>
                <div class="fw-bold">Lendi Institute of Engineering &amp; Technology</div>
                <small class="text-light">Leave Management Portal</small>
            </div>
        </div>

        <div>
            <?php if ($isAuthenticated): ?>
                <span class="me-3">Role: <strong><?= h($currentRole) ?></strong></span>
                <a href="<?= APP_ROOT ?>/auth/logout.php" class="btn btn-sm btn-light">Logout</a>
            <?php else: ?>
                <a href="<?= APP_ROOT ?>/auth/login.php" class="btn btn-sm btn-light">Login</a>
            <?php endif; ?>
        </div>

    </div>
</header>

<?php if ($isAuthenticated): ?>

<!-- AUTHENTICATED LAYOUT -->
<div class="d-flex">

    <!-- SIDEBAR -->
    <aside class="lendi-sidebar p-3">

        <div class="mb-3 fw-bold text-uppercase small text-muted">
            Navigation
        </div>

        <a class="sidebar-link" href="<?= APP_ROOT ?>/index.php">Overview</a>

        <?php if ($currentRole === 'student'): ?>
            <a class="sidebar-link" href="<?= APP_ROOT ?>/student/apply_leave.php">Apply Leave</a>
            <a class="sidebar-link" href="<?= APP_ROOT ?>/student/my_leaves.php">My Requests</a>
            <a class="sidebar-link" href="<?= APP_ROOT ?>/student/logs.php">Logs</a>
        <?php else: ?>
            <a class="sidebar-link" href="<?= APP_ROOT ?>/approver/dashboard.php">Approvals</a>
            <a class="sidebar-link" href="<?= APP_ROOT ?>/approver/logs.php">Logs</a>
            <?php if ($currentRole === 'hod'): ?>
                <a class="sidebar-link" href="<?= APP_ROOT ?>/approver/user_approvals.php">User Approvals</a>
            <?php endif; ?>
        <?php endif; ?>

        <a class="sidebar-link text-danger mt-3" href="<?= APP_ROOT ?>/auth/logout.php">Logout</a>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-grow-1 p-4">

        <div class="mb-4 p-3 bg-light rounded d-flex justify-content-between align-items-center">
            <div>
                <strong><?= h($currentName) ?></strong>
                <div class="text-muted small"><?= h($currentRole) ?></div>
                <?php if ($userIdValue !== ''): ?>
                    <div class="text-muted small"><?= h($userIdLabel) ?>: <?= h($userIdValue) ?></div>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                Leave Management System
            </div>
        </div>

<?php else: ?>

<!-- PUBLIC LAYOUT -->
<main class="container py-5">

<?php endif; ?>

