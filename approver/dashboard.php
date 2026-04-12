<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['faculty', 'hod', 'principal']);

$stmt = $pdo->prepare('SELECT l.*, u.name AS student_name, u.department FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.current_approver_id = ? ORDER BY l.id DESC');
$stmt->execute([$current_user['id']]);
$requests = $stmt->fetchAll();
$token = generate_csrf_token();

include '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="mb-4">
            <h2 class="mb-1">Pending Leave Requests</h2>
            <p class="text-muted">Review and approve or reject requests assigned to you.</p>
        </div>

        <?php if (empty($requests)): ?>
            <div class="alert alert-info">No pending requests assigned to you at this time.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Student</th>
                            <th scope="col">Department</th>
                            <th scope="col">Type</th>
                            <th scope="col">Period</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= h($request['id']) ?></td>
                                <td><?= h($request['student_name']) ?></td>
                                <td><?= h($request['department']) ?></td>
                                <td><?= h($request['leave_type'] ?? 'N/A') ?></td>
                                <td><?= h($request['from_date']) ?> → <?= h($request['to_date']) ?></td>
                                <td><?= h($request['reason']) ?></td>
                                <td><span class="badge bg-secondary"><?= h(ucfirst($request['status'])) ?></span></td>
                                <td class="text-end">
                                    <form method="post" action="<?= APP_ROOT ?>/actions/approve_leave.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="id" value="<?= h($request['id']) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <form method="post" action="<?= APP_ROOT ?>/actions/approve_leave.php" class="d-inline ms-2">
                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="id" value="<?= h($request['id']) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
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