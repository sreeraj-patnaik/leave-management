<?php
include 'config/db.php';
include 'includes/auth_check.php';

$role = $current_user['role'];

if ($role === 'student') {
    $stats = $pdo->prepare('SELECT status, COUNT(*) AS total FROM leaves WHERE user_id = ? GROUP BY status');
    $stats->execute([$current_user['id']]);
    $summary = $stats->fetchAll();
} else {
    $pending = $pdo->prepare('SELECT COUNT(*) AS total FROM leaves WHERE current_approver_id = ?');
    $pending->execute([$current_user['id']]);
    $summary = $pending->fetchAll();
}

include 'includes/header.php';
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h1 class="card-title">Welcome, <?= h($current_user['name'] ?? $current_user['email']) ?>!</h1>
                <p class="card-text">You are signed in as <strong><?= h($role) ?></strong>. Use the dashboard links to manage your leaves.</p>
                <?php if ($role === 'student'): ?>
                    <a href="<?= APP_ROOT ?>/student/apply_leave.php" class="btn btn-success me-2">Apply for Leave</a>
                    <a href="<?= APP_ROOT ?>/student/my_leaves.php" class="btn btn-outline-primary">View My Leaves</a>
                <?php else: ?>
                    <a href="<?= APP_ROOT ?>/approver/dashboard.php" class="btn btn-primary">View Pending Requests</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title">Overview</h4>
                <?php if (empty($summary)): ?>
                    <p class="text-muted">No records found yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($summary as $row): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <?= h($row['status'] ?? 'Pending') ?>
                                <span class="badge bg-secondary rounded-pill"><?= h($row['total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>