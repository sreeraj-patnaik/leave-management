<?php
include 'config/db.php';
include 'includes/auth_check.php';

$role = $current_user['role'];
$idLabel = $role === 'student' ? 'Regd No' : 'Employee ID';
$idValue = $role === 'student' ? ($current_user['regd_no'] ?? '') : ($current_user['emp_id'] ?? '');

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$rows = [];

// ================= LEAVE STATS =================
if ($role === 'student') {

    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS total 
         FROM leaves 
         WHERE user_id = ? 
         GROUP BY status'
    );
    $stmt->execute([$current_user['id']]);
    $rows = $stmt->fetchAll();

} else {

    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS total 
         FROM leaves 
         WHERE current_approver_id = ? 
         GROUP BY status'
    );
    $stmt->execute([$current_user['id']]);
    $rows = $stmt->fetchAll();
}

foreach ($rows as $row) {
    $counts[$row['status']] = (int)$row['total'];
}

// ================= USER APPROVAL COUNT (HOD ONLY) =================
$userApprovalCount = 0;

if ($role === 'hod') {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM users 
         WHERE status='pending' AND department=?"
    );
    $stmt->execute([$current_user['department']]);
    $userApprovalCount = $stmt->fetchColumn();
}

include 'includes/header.php';
?>

<div class="container mt-4">

    <!-- HERO -->
    <div class="mb-4">
        <h2 class="fw-bold">
            Welcome, <?= h($current_user['name'] ?? $current_user['email']) ?>
        </h2>

        <p class="text-muted">
            Role: <strong><?= strtoupper(h($role)) ?></strong>
            <?php if ($idValue !== ''): ?>
                <span class="ms-2"><?= h($idLabel) ?>: <strong><?= h($idValue) ?></strong></span>
            <?php endif; ?>
        </p>

        <div class="mt-3">

            <?php if ($role === 'student'): ?>

                <a href="<?= APP_ROOT ?>/student/apply_leave.php" class="btn btn-primary me-2">
                    Apply Leave
                </a>

                <a href="<?= APP_ROOT ?>/student/my_leaves.php" class="btn btn-outline-secondary">
                    My Leaves
                </a>

            <?php else: ?>

                <a href="<?= APP_ROOT ?>/approver/dashboard.php" class="btn btn-primary me-2">
                    Pending Requests
                </a>

                <?php if ($role === 'hod'): ?>
                    <a href="<?= APP_ROOT ?>/approver/user_approvals.php" class="btn btn-warning">
                        User Approvals (<?= $userApprovalCount ?>)
                    </a>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

    <!-- STATS -->
    <div class="row text-center">

        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="text-muted">Pending</div>
                <h3><?= $counts['pending'] ?></h3>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="text-muted">Approved</div>
                <h3><?= $counts['approved'] ?></h3>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="text-muted">Rejected</div>
                <h3><?= $counts['rejected'] ?></h3>
            </div>
        </div>

    </div>

    <!-- EXTRA (HOD ONLY) -->
    <?php if ($role === 'hod'): ?>
        <div class="card mt-3 p-3">
            <h5>Pending User Approvals</h5>
            <p class="mb-0">
                <?= $userApprovalCount ?> users waiting for approval
            </p>
        </div>
    <?php endif; ?>

    <!-- OVERVIEW -->
    <div class="card mt-4 p-3">
        <h5>Overview</h5>

        <?php if (empty($rows)): ?>
            <p class="text-muted">No records yet.</p>
        <?php else: ?>

            <ul class="list-group list-group-flush">
                <?php foreach ($rows as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <?= ucfirst($row['status']) ?>
                        <span class="badge bg-secondary"><?= $row['total'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

