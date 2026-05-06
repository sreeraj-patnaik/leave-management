<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['faculty', 'hod', 'principal']);

$stmt = $pdo->prepare(
    'SELECT al.*, l.leave_type, l.from_date, l.to_date,
            u.name AS requester_name, u.department, u.regd_no, u.emp_id
     FROM approval_log al
     JOIN leaves l ON al.leave_id = l.id
     JOIN users u ON l.user_id = u.id
     WHERE al.approved_by = ?
     ORDER BY al.created_at DESC'
);

$stmt->execute([$current_user['id']]);
$logs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="mb-4">
        <h2 class="fw-bold">Approval Logs</h2>
        <p class="text-muted">History of your decisions on leave requests.</p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info text-center">
            No logs available yet.
        </div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light text-center">
                    <tr>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Requester</th>
                        <th>Regd/Emp ID</th>
                        <th>Dept</th>
                        <th>Your Role</th>
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

                            <td><?= h($log['requester_name']) ?></td>

                            <td class="text-center">
                                <?= h($log['regd_no'] ?: ($log['emp_id'] ?: '-')) ?>
                            </td>

                            <td class="text-center">
                                <?= strtoupper(h($log['department'] ?? '-')) ?>
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

