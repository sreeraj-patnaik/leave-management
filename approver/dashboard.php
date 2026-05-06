<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['faculty', 'hod', 'principal']);

$stmt = $pdo->prepare(
    'SELECT l.*, u.name AS student_name, u.department, u.regd_no, u.emp_id
     FROM leaves l
     JOIN users u ON l.user_id = u.id
     WHERE l.current_approver_id = ? AND l.status = ?
     ORDER BY l.id DESC'
);

$stmt->execute([$current_user['id'], 'pending']);
$requests = $stmt->fetchAll();
$token = generate_csrf_token();

include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="mb-4">
        <h2 class="fw-bold">Leave Approval Desk</h2>
        <p class="text-muted">Review requests and take action.</p>
    </div>

    <div class="d-flex justify-content-between mb-3">
        <h5>Pending Requests</h5>
        <a href="<?= APP_ROOT ?>/approver/logs.php" class="btn btn-outline-primary btn-sm">View Logs</a>
    </div>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info text-center">
            No pending requests.
        </div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light text-center">
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Regd/Emp ID</th>
                        <th>Dept</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>

                            <td class="text-center"><?= h($r['id']) ?></td>

                            <td><?= h($r['student_name']) ?></td>

                            <td class="text-center">
                                <?= h($r['regd_no'] ?: ($r['emp_id'] ?: '-')) ?>
                            </td>

                            <td class="text-center"><?= strtoupper(h($r['department'])) ?></td>

                            <td class="text-center"><?= h($r['leave_type'] ?? '-') ?></td>

                            <td>
                                <strong><?= h($r['from_date']) ?></strong>
                                <br>
                                <small class="text-muted">to</small>
                                <br>
                                <strong><?= h($r['to_date']) ?></strong>
                            </td>

                            <td style="max-width:200px;">
                                <?= h($r['reason']) ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-warning text-dark">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>

                            <td class="text-center">

                                <form method="post" action="<?= APP_ROOT ?>/actions/approve_leave.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm"
                                        onclick="return confirm('Approve this leave?')">
                                        Approve
                                    </button>
                                </form>

                                <form method="post" action="<?= APP_ROOT ?>/actions/approve_leave.php" class="d-inline ms-1">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Reject this leave?')">
                                        Reject
                                    </button>
                                </form>

                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>

