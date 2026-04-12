<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['student']);

$stmt = $pdo->prepare('SELECT l.*, u.name AS approver_name FROM leaves l LEFT JOIN users u ON l.current_approver_id = u.id WHERE l.user_id = ? ORDER BY l.id DESC');
$stmt->execute([$current_user['id']]);
$leaves = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0">My Leaves</h2>
                <p class="text-muted mb-0">Review your leave history and current request status.</p>
            </div>
            <a href="<?= APP_ROOT ?>/student/apply_leave.php" class="btn btn-success">Apply for Leave</a>
        </div>

        <?php if (empty($leaves)): ?>
            <div class="alert alert-info">You have no leave requests yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Type</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Approver</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td><?= h($leave['from_date']) ?></td>
                                <td><?= h($leave['to_date']) ?></td>
                                <td><?= h($leave['leave_type'] ?? 'N/A') ?></td>
                                <td><?= h($leave['reason']) ?></td>
                                <td><?= h($leave['approver_name'] ?? 'Not assigned') ?></td>
                                <td>
                                    <span class="badge <?= $leave['status'] === 'approved' ? 'bg-success' : ($leave['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary') ?>">
                                        <?= h(ucfirst($leave['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>