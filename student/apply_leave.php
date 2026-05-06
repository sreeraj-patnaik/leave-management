<?php
include '../config/db.php';
include '../includes/auth_check.php';
require_role(['student', 'faculty', 'hod']);

$error = '';
$success = '';
$from = '';
$to = '';
$reason = '';
$leaveType = 'Casual';
$maxReasonLength = 500;

$leaveTypes = ['Casual','Sick','Earned','Maternity','Study','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission.';
    } else {
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $leaveType = trim($_POST['leave_type'] ?? 'Casual');

        $fromDate = DateTime::createFromFormat('Y-m-d', $from);
        $toDate = DateTime::createFromFormat('Y-m-d', $to);

        if (!$fromDate || !$toDate) {
            $error = 'Enter valid dates.';
        } elseif ($fromDate > $toDate) {
            $error = 'End date must be after start date.';
        } elseif ($reason === '') {
            $error = 'Reason is required.';
        } elseif (strlen($reason) > $maxReasonLength) {
            $error = 'Max 500 characters allowed.';
        } elseif (!in_array($leaveType, $leaveTypes, true)) {
            $error = 'Invalid leave type.';
        } else {
            $approverId = get_final_approver_for_requester($pdo, $current_user);

            if (!$approverId) {
                $error = 'Approver not found. Contact admin.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO leaves 
                     (user_id, leave_type, from_date, to_date, reason, current_approver_id, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $current_user['id'],
                    $leaveType,
                    $from,
                    $to,
                    $reason,
                    $approverId,
                    'pending'
                ]);

                $success = 'Leave request submitted successfully.';
                $from = $to = $reason = '';
                $leaveType = 'Casual';
            }
        }
    }
}

$token = generate_csrf_token();
include '../includes/header.php';
?>

<div class="container mt-4">

    <div class="mb-4">
        <h2 class="fw-bold">Apply for Leave</h2>
        <p class="text-muted">Submit your request and track approval status.</p>
    </div>

    <div class="card shadow-sm p-4">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="from" class="form-control" required value="<?= h($from) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date</label>
                    <input type="date" name="to" class="form-control" required value="<?= h($to) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Leave Type</label>
                <select name="leave_type" class="form-select">
                    <?php foreach ($leaveTypes as $type): ?>
                        <option value="<?= h($type) ?>" <?= $leaveType === $type ? 'selected' : '' ?>>
                            <?= h($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="4" maxlength="500"><?= h($reason) ?></textarea>
                <small class="text-muted">Max 500 characters</small>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    Submit Request
                </button>

                <a href="<?= APP_ROOT ?>/student/my_leaves.php" class="btn btn-outline-secondary">
                    View My Leaves
                </a>
            </div>

        </form>

    </div>

</div>

<?php include '../includes/footer.php'; ?>