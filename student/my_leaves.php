<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['student']);

$stmt = $pdo->prepare(
    'SELECT l.*, u.name AS approver_name, u.emp_id, u.regd_no
     FROM leaves l
     LEFT JOIN users u ON l.current_approver_id = u.id
     WHERE l.user_id = ?
     ORDER BY l.id DESC'
);

$stmt->execute([$current_user['id']]);
$leaves = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold">My Leaves</h2>
            <p class="text-muted mb-0">Track all your leave requests and approval status.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_ROOT ?>/student/apply_leave.php" class="btn btn-primary">
                Apply Leave
            </a>
            <a href="<?= APP_ROOT ?>/student/logs.php" class="btn btn-outline-secondary">
                View Logs
            </a>
        </div>
    </div>

    <?php if (empty($leaves)): ?>
        <div class="alert alert-info text-center">
            No leave requests found.
        </div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light text-center">
                    <tr>
                        <th>Period</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Current Approver</th>
                        <th>Approver ID</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($leaves as $leave): ?>
                        <tr>

                            <td>
                                <strong><?= h($leave['from_date']) ?></strong>
                                <br>
                                <small class="text-muted">to</small>
                                <br>
                                <strong><?= h($leave['to_date']) ?></strong>
                            </td>

                            <td class="text-center">
                                <?= h($leave['leave_type'] ?? '-') ?>
                            </td>

                            <td style="max-width: 200px;">
                                <?= h($leave['reason']) ?>
                            </td>

                            <td class="text-center">
                                <?= h($leave['approver_name'] ?? '-') ?>
                            </td>

                            <td class="text-center">
                                <?= h($leave['emp_id'] ?: ($leave['regd_no'] ?: '-')) ?>
                            </td>

                            <td class="text-center">
                                <?php if ($leave['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($leave['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>

