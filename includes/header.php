<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', '/leave-management');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Leave Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-p3wr8PfYB+ZjP2G+F0O/0g+siqY/0s97Q2DeDWJx5Lvh0P7xz5D2kXlxgDI1pdC+" crossorigin="anonymous">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= APP_ROOT ?>/index.php">Leave Management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= APP_ROOT ?>/index.php">Home</a></li>
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= APP_ROOT ?>/student/my_leaves.php">My Leaves</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= APP_ROOT ?>/student/apply_leave.php">Apply</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= APP_ROOT ?>/approver/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link text-danger" href="<?= APP_ROOT ?>/auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= APP_ROOT ?>/auth/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
<?php if (!empty($_SESSION['user_id'])): ?>
    <div class="alert alert-secondary small mb-4 d-flex justify-content-between align-items-center">
        <div>Signed in as <strong><?= h($_SESSION['name'] ?? $_SESSION['role']) ?></strong> · Role: <strong><?= h($_SESSION['role']) ?></strong></div>
        <div><a href="<?= APP_ROOT ?>/auth/logout.php" class="link-secondary">Sign out</a></div>
    </div>
<?php endif; ?>
