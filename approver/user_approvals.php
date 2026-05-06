<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['hod']);

$stmt = $pdo->prepare(
    'SELECT id, name, email, role, department, regd_no, emp_id
     FROM users
     WHERE status = ? AND department = ?
     ORDER BY id DESC'
);
$stmt->execute(['pending', $current_user['department']]);
$users = $stmt->fetchAll();

$token = generate_csrf_token();

include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="mb-4">
        <h2 class="fw-bold">User Approvals</h2>
        <p class="text-muted">
            Review and approve new users from <?= strtoupper(h($current_user['department'])) ?> department.
        </p>
    </div>

    <?php if (empty($users)): ?>
        <div class="alert alert-info text-center">
            No pending user requests.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Regd/Emp ID</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h($u['name']) ?></td>
                            <td class="text-center"><?= strtoupper(h($u['role'])) ?></td>
                            <td class="text-center"><?= h($u['regd_no'] ?: ($u['emp_id'] ?: '-')) ?></td>
                            <td class="text-center"><?= strtoupper(h($u['department'])) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td class="text-center">
                                <form method="POST" action="approve_user.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success btn-sm" onclick="return confirm('Approve this user?')">
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" action="approve_user.php" class="d-inline ms-1">
                                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                    <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Reject this user?')">
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
