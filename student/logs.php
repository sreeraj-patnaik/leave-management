<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['student']);

$stmt = $pdo->prepare(
    'SELECT al.*, l.leave_type, l.from_date, l.to_date,
            u.name AS approver_name, u.emp_id, u.regd_no
     FROM approval_log al
     JOIN leaves l ON al.leave_id = l.id
     LEFT JOIN users u ON al.approved_by = u.id
     WHERE l.user_id = ?
     ORDER BY al.created_at DESC'
);

$stmt->execute([$current_user['id']]);
$logs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="mb-4">
        <h2 class="fw-bold">Approval Logs</h2>
        <p class="text-muted">Track all decisions on your leave requests.</p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info text-center">
            No approval logs available.
        </div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light text-center">
                    <tr>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Approver</th>
                        <th>Approver ID</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>

                            <td class="text-center">
                                <?= h($log['leave_type'] ?? '-') ?>
                            </td>

                            <td>
                                <strong><?= h($log['from_date']) ?></strong>
                                <br>
                                <small class="text-muted">to</small>
                                <br>
                                <strong><?= h($log['to_date']) ?></strong>
                            </td>

                            <td><?= h($log['approver_name'] ?? '-') ?></td>

                            <td class="text-center">
                                <?= h($log['emp_id'] ?: ($log['regd_no'] ?: '-')) ?>
                            </td>

                            <td class="text-center">
                                <?= strtoupper(h($log['role'])) ?>
                            </td>

                            <td class="text-center">
                                <?php if ($log['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>

